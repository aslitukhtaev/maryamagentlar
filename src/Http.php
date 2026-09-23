<?php

declare(strict_types=1);

namespace Maryam;

/**
 * cURL uchun kichik yordamchi.
 *
 * Ba'zi Windows'dagi PHP o'rnatishlarida SSL sertifikatlar ro'yxati (CA bundle)
 * sozlanmagan bo'ladi, shuning uchun HTTPS so'rovlar "SSL certificate problem:
 * unable to get local issuer certificate" xatosi bilan yiqiladi. Buni oldini
 * olish uchun loyihaga tayyor sertifikat faylini qo'shib qo'ydik
 * (resources/cacert.pem, curl.se/ca dan olingan rasmiy Mozilla ro'yxati)
 * va har bir cURL so'rovida shuni ko'rsatamiz.
 */
final class Http
{
    public static function applyCaBundle($ch): void
    {
        $bundle = ROOT . '/resources/cacert.pem';
        if (is_file($bundle)) {
            curl_setopt($ch, CURLOPT_CAINFO, $bundle);
        }
    }
}
