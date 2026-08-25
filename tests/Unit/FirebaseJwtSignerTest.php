<?php

namespace Tests\Unit;

use App\Services\Firebase\FirebaseJwtSigner;
use Tests\TestCase;

class FirebaseJwtSignerTest extends TestCase
{
    public function test_it_creates_a_valid_rs256_signature(): void
    {
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if ($key === false) {
            $this->markTestSkipped('Este entorno PHP no permite generar una clave RSA de prueba.');
        }
        openssl_pkey_export($key, $privateKey);
        $publicKey = openssl_pkey_get_details($key)['key'];

        $jwt = app(FirebaseJwtSigner::class)->sign([
            'uid' => 'laravel_42',
            'iat' => 1,
            'exp' => 3601,
        ], $privateKey, 'test-key');
        [$header, $payload, $signature] = explode('.', $jwt);

        $decodedPayload = json_decode($this->decode($payload), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('laravel_42', $decodedPayload['uid']);
        $this->assertSame(1, openssl_verify(
            $header.'.'.$payload,
            $this->decode($signature),
            $publicKey,
            OPENSSL_ALGO_SHA256,
        ));
    }

    private function decode(string $value): string
    {
        return base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4));
    }
}
