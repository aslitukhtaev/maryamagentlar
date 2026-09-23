<?php

declare(strict_types=1);

namespace Maryam;

use RuntimeException;

/**
 * Vertex AI orqali Gemini — Google Cloud $300 trial krediti bilan ishlaydi.
 * ADC (Application Default Credentials) yoki GOOGLE_APPLICATION_CREDENTIALS orqali autentifikatsiya.
 *
 * Foyda: $300 Cloud trial krediti to'g'ridan-to'g'ri shu yerdan ishlatiladi.
 * Talabalar: gcloud va service account key, yoki ~/.config/gcloud/application_default_credentials.json
 */
class GeminiVertex
{
    private const URL = 'https://%s-aiplatform.googleapis.com/v1beta1/projects/%s/locations/%s/publishers/google/models/%s:generateContent';

    public function __construct(
        private string $projectId,
        private string $location = 'us-central1', // yoki 'europe-west1' / 'us-west1'
        public readonly string $fastModel = 'gemini-2.5-flash',
        public readonly string $smartModel = 'gemini-2.5-pro',
        public readonly array $fallbackModels = ['gemini-1.5-flash', 'gemini-1.5-pro-preview-0514'],
    ) {
        if ($projectId === '') {
            throw new RuntimeException('GOOGLE_CLOUD_PROJECT_ID bo\'sh. .env ga qo\'shing.');
        }
    }

    /** AI'dan JSON javob so'raydi. */
    public function json(string $system, string $user, float $temperature = 0.8, bool $smart = false): array
    {
        $result = $this->generate($system, $user, $temperature, $smart ? $this->smartModel : $this->fastModel);
        $result['data'] = self::decodeJson($result['text']);
        return $result;
    }

    /**
     * Diagnostika: shu loyiha/region uchun Vertex AI'da haqiqatda MAVJUD bo'lgan
     * Google modellarini so'rab ko'radi (model nomlarini taxmin qilib sinash o'rniga).
     *
     * @return array<int, array{name: string, generateContent: bool}>
     */
    public function listModels(): array
    {
        $url = sprintf(
            'https://%s-aiplatform.googleapis.com/v1/publishers/google/models?pageSize=1000',
            $this->location
        );
        $token = $this->getAccessToken();

        $ch = curl_init($url);
        Http::applyCaBundle($ch);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'x-goog-user-project: ' . $this->projectId,
            ],
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($body === false || $status !== 200) {
            throw new RuntimeException("Modellar ro'yxatini olishda xato ($status): " . mb_substr((string) $body, 0, 500));
        }

        $data = json_decode($body, true) ?? [];
        $models = [];
        foreach ($data['publisherModels'] ?? [] as $m) {
            $name = $m['name'] ?? '';
            // Faqat "gemini" so'zi bor modellarni ko'rsatamiz (gemma, imagen, veo va h.k.ni yashiramiz)
            if (str_contains($name, 'gemini')) {
                $methods = $m['supportedActions']['generateContent'] ?? $m['supportedGenerationMethods'] ?? null;
                $models[] = [
                    'name' => $name,
                    'generateContent' => $methods !== null,
                ];
            }
        }
        return $models;
    }

    protected function generate(string $system, string $user, float $temperature, string $model): array
    {
        $main = $this->smartModel ? $this->smartModel : $this->fastModel;
        $models = array_values(array_unique([$model, ...$this->fallbackModels]));
        $lastError = null;

        foreach ($models as $i => $m) {
            try {
                $result = $this->call($system, $user, $temperature, $m);
                $result['model_used'] = $m;
                return $result;
            } catch (RuntimeException $e) {
                $lastError = $e;
                $isLast = $i === count($models) - 1;
                if ($isLast || !preg_match('/\((429|503|401|403)\)/', $e->getMessage())) {
                    throw $e;
                }
            }
        }
        throw $lastError ?? new RuntimeException('Hech bir model javob bermadi.');
    }

    private function call(string $system, string $user, float $temperature, string $model): array
    {
        $url = sprintf(self::URL, $this->location, $this->projectId, $this->location, $model);

        $payload = [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $user]]]],
            'generationConfig' => [
                'temperature' => $temperature,
                'responseMimeType' => 'application/json',
            ],
        ];

        $started = microtime(true);
        $token = $this->getAccessToken();

        $ch = curl_init($url);
        Http::applyCaBundle($ch);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 180,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
                // Shaxsiy hisob (gcloud login) orqali ishlaganda billing/quota shu loyihaga yozilsin
                'x-goog-user-project: ' . $this->projectId,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException("Vertex AI bilan aloqa yo'q: $error");
        }

        $response = json_decode($body, true) ?? [];

        if ($status !== 200) {
            $message = $response['error']['message'] ?? $body;
            if ($status === 401 || $status === 403) {
                $message = "Autentifikatsiya xatosi ($status). GOOGLE_APPLICATION_CREDENTIALS tekshiring. ($message)";
            } elseif ($status === 429) {
                $message = "Quota limit tugagan (429). $message";
            } elseif ($status === 503) {
                $message = "Model band (503). $message";
            }
            throw new RuntimeException("Vertex AI xatosi ($status): $message");
        }

        $candidate = $response['candidates'][0] ?? null;
        if ($candidate === null) {
            $reason = $response['promptFeedback']['blockReason'] ?? "noma'lum";
            throw new RuntimeException("Vertex AI javob bermadi (sabab: $reason)");
        }

        $text = '';
        foreach ($candidate['content']['parts'] ?? [] as $part) {
            if (empty($part['thought'])) {
                $text .= $part['text'] ?? '';
            }
        }

        if (trim($text) === '') {
            throw new RuntimeException("Vertex AI bo'sh javob qaytardi");
        }

        return [
            'text' => $text,
            'model' => $model,
            'tokens_in' => (int) ($response['usageMetadata']['promptTokenCount'] ?? 0),
            'tokens_out' => (int) ($response['usageMetadata']['candidatesTokenCount'] ?? 0),
            'ms' => (int) ((microtime(true) - $started) * 1000),
        ];
    }

    /**
     * Access token oladi. Windows'da ham, Linux/Mac'da ham ishlashi uchun
     * avval ADC JSON faylini TO'G'RIDAN-TO'G'RI o'qiymiz (gcloud CLI'ni PATH'dan
     * qidirib, uni ishga tushirishga suyanmaymiz — bu Windows'da ko'p muammo beradi).
     * `gcloud` orqali chaqirish faqat oxirgi zaxira variant sifatida qoladi.
     */
    private function getAccessToken(): string
    {
        // 1) GOOGLE_APPLICATION_CREDENTIALS — service account key fayli
        $credFile = getenv('GOOGLE_APPLICATION_CREDENTIALS');
        if ($credFile && is_file($credFile)) {
            $cred = json_decode(file_get_contents($credFile), true);
            if (is_array($cred) && isset($cred['private_key'], $cred['client_email'])) {
                return $this->getTokenFromServiceAccount($cred);
            }
        }

        // 2) ADC fayli — `gcloud auth application-default login` shuni yaratadi.
        //    Windows: %APPDATA%\gcloud\application_default_credentials.json
        //    Linux/Mac: ~/.config/gcloud/application_default_credentials.json
        $adcPath = ($credFile && is_file($credFile)) ? $credFile : self::defaultAdcPath();
        if ($adcPath && is_file($adcPath)) {
            $cred = json_decode((string) file_get_contents($adcPath), true);
            if (is_array($cred)) {
                if (($cred['type'] ?? '') === 'authorized_user' && isset($cred['refresh_token'])) {
                    return $this->getTokenFromRefreshToken($cred);
                }
                if (isset($cred['private_key'], $cred['client_email'])) {
                    return $this->getTokenFromServiceAccount($cred);
                }
            }
        }

        // 3) Oxirgi zaxira: gcloud CLI orqali (agar PATH'da bo'lsa)
        if (function_exists('exec')) {
            $nullDevice = self::isWindows() ? 'NUL' : '/dev/null';
            $out = [];
            @exec("gcloud auth application-default print-access-token 2>$nullDevice", $out, $code);
            if ($code === 0 && isset($out[0]) && trim($out[0]) !== '') {
                return trim($out[0]);
            }
        }

        throw new RuntimeException(
            "Access token olib bo'lmadi. Tekshiring:\n"
            . "  1. `gcloud auth application-default login` qilinganmi?\n"
            . "  2. Fayl mavjudmi: " . (self::defaultAdcPath() ?? "(HOME/APPDATA aniqlanmadi)") . "\n"
            . "  3. Yoki GOOGLE_APPLICATION_CREDENTIALS=/path/to/key.json o'rnatilganmi?"
        );
    }

    private static function isWindows(): bool
    {
        return stripos(PHP_OS, 'WIN') === 0;
    }

    /** ADC faylining standart joylashuvi (OS'ga qarab). */
    private static function defaultAdcPath(): ?string
    {
        if (self::isWindows()) {
            $appData = getenv('APPDATA');
            return $appData !== false ? $appData . '\\gcloud\\application_default_credentials.json' : null;
        }
        $home = getenv('HOME');
        return $home !== false ? $home . '/.config/gcloud/application_default_credentials.json' : null;
    }

    /** ADC fayldagi refresh_token orqali yangi access_token so'raydi (gcloud CLI shart emas). */
    private function getTokenFromRefreshToken(array $cred): string
    {
        $ch = curl_init('https://oauth2.googleapis.com/token');
        Http::applyCaBundle($ch);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_POSTFIELDS => http_build_query([
                'client_id' => $cred['client_id'] ?? '',
                'client_secret' => $cred['client_secret'] ?? '',
                'refresh_token' => $cred['refresh_token'] ?? '',
                'grant_type' => 'refresh_token',
            ]),
        ]);
        $body = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException("ADC token yangilashda tarmoq xatosi: $curlError");
        }

        $response = json_decode($body, true) ?? [];
        if (!isset($response['access_token'])) {
            $message = $response['error_description'] ?? $response['error'] ?? "noma'lum xato";
            throw new RuntimeException(
                "ADC token yangilab bo'lmadi ($message). Qayta urinib ko'ring: gcloud auth application-default login"
            );
        }
        return $response['access_token'];
    }

    /** Service account key'dan JWT token oladi va Vertex AI'dan access token so'raydi. */
    private function getTokenFromServiceAccount(array $cred): string
    {
        $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_UNESCAPED_SLASHES);
        $now = time();
        $payload = json_encode([
            'iss' => $cred['client_email'],
            'scope' => 'https://www.googleapis.com/auth/cloud-platform',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now,
        ], JSON_UNESCAPED_SLASHES);

        $message = base64_encode($header) . '.' . base64_encode($payload);
        $signature = '';
        openssl_sign($message, $signature, $cred['private_key'], 'sha256');
        $jwt = $message . '.' . base64_encode($signature);

        $ch = curl_init('https://oauth2.googleapis.com/token');
        Http::applyCaBundle($ch);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt]),
        ]);
        $body = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($body, true) ?? [];
        if (!isset($response['access_token'])) {
            throw new RuntimeException("Service account token olib bo'lmadi: " . ($response['error'] ?? 'noma\'lum'));
        }
        return $response['access_token'];
    }

    public static function decodeJson(string $text): array
    {
        $clean = trim($text);
        if (preg_match('/```(?:json)?\s*(.*?)```/s', $clean, $m)) {
            $clean = trim($m[1]);
        }
        $data = json_decode($clean, true);
        if (!is_array($data)) {
            throw new RuntimeException("AI javobi JSON emas: " . mb_substr($text, 0, 300));
        }
        return $data;
    }
}
