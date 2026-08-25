<?php

namespace App\Services\Firebase;

use App\Models\User;

class FirebaseCustomTokenFactory
{
    public function __construct(
        private readonly FirebaseConfiguration $configuration,
        private readonly FirebaseJwtSigner $signer,
    ) {}

    public function forUser(User $user): string
    {
        $credentials = $this->configuration->credentials();
        $now = time();
        $issuer = (string) $credentials['client_email'];

        return $this->signer->sign([
            'iss' => $issuer,
            'sub' => $issuer,
            'aud' => 'https://identitytoolkit.googleapis.com/google.identity.identitytoolkit.v1.IdentityToolkit',
            'iat' => $now,
            'exp' => $now + 3600,
            'uid' => $this->configuration->userUid($user->getKey()),
            'claims' => [
                'laravel_user_id' => (string) $user->getKey(),
            ],
        ], (string) $credentials['private_key'], $credentials['private_key_id'] ?? null);
    }
}
