<?php

namespace App\Services\Firebase;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FirebaseAccessTokenProvider
{
    public function __construct(
        private readonly FirebaseConfiguration $configuration,
        private readonly FirebaseJwtSigner $signer,
    ) {}

    public function token(): string
    {
        $credentials = $this->configuration->credentials();
        $cacheKey = 'firebase-rtdb:oauth:'.sha1((string) $credentials['client_email']);

        return Cache::remember($cacheKey, now()->addMinutes(45), function () use ($credentials): string {
            $now = time();
            $assertion = $this->signer->sign([
                'iss' => (string) $credentials['client_email'],
                'scope' => implode(' ', [
                    'https://www.googleapis.com/auth/userinfo.email',
                    'https://www.googleapis.com/auth/firebase.database',
                ]),
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ], (string) $credentials['private_key'], $credentials['private_key_id'] ?? null);

            $response = Http::asForm()
                ->acceptJson()
                ->timeout(max(1, (int) config('firebase.realtime.request_timeout', 3)))
                ->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $assertion,
                ]);

            if (! $response->successful()) {
                throw new RuntimeException('Google OAuth respondió con HTTP '.$response->status().'.');
            }

            $token = $response->json();

            if (empty($token['access_token'])) {
                throw new RuntimeException('Firebase no devolvió un token OAuth válido.');
            }

            return (string) $token['access_token'];
        });
    }
}
