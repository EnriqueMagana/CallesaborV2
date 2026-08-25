<?php

namespace App\Services\Firebase;

use RuntimeException;

class FirebaseJwtSigner
{
    /** @param array<string, mixed> $payload */
    public function sign(array $payload, string $privateKey, ?string $keyId = null): string
    {
        $header = array_filter([
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => $keyId,
        ], fn ($value) => $value !== null && $value !== '');

        $segments = [
            $this->base64Url(json_encode($header, JSON_THROW_ON_ERROR)),
            $this->base64Url(json_encode($payload, JSON_THROW_ON_ERROR)),
        ];
        $signingInput = implode('.', $segments);
        $key = openssl_pkey_get_private($privateKey);

        if ($key === false || ! openssl_sign($signingInput, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('No fue posible firmar el token de Firebase con la clave privada configurada.');
        }

        $segments[] = $this->base64Url($signature);

        return implode('.', $segments);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
