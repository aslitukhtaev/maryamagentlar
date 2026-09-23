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
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 180,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
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

    /** gcloud orqali access token oladi (ADC qo'llaniladi). */
    private function getAccessToken(): string
    {
        // GOOGLE_APPLICATION_CREDENTIALS o'rnatilgan bo'lsa uni ishlat
        $credFile = getenv('GOOGLE_APPLICATION_CREDENTIALS');
        if ($credFile && is_file($credFile)) {
            $cred = json_decode(file_get_contents($credFile), true);
            if (isset($cred['type'], $cred['private_key'], $cred['client_email'])) {
                return $this->getTokenFromServiceAccount($cred);
            }
        }

        // ADC (Application Default Credentials) — `gcloud auth application-default login` bilan o'rnatiladi
        if (function_exists('exec')) {
            $out = [];
            @exec('gcloud auth application-default print-access-token 2>/dev/null', $out, $code);
            if ($code === 0 && isset($out[0]) && trim($out[0]) !== '') {
                return trim($out[0]);
            }
        }

        throw new RuntimeException(
            "Access token olib bo'lmadi. Tekshiring:\n"
            . "  1. GOOGLE_APPLICATION_CREDENTIALS environment variable\n"
            . "  2. gcloud auth application-default login\n"
            . "  3. ~/.config/gcloud/application_default_credentials.json"
        );
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
