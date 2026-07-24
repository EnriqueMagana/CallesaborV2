<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;

class SingleSessionManager
{
    public const SESSION_KEY = 'auth.single_session_token';
    public const SESSION_USER_KEY = 'auth.single_session_user_id';

    public function start(User $user, Session $session): string
    {
        $token = Str::random(64);

        $user->forceFill([
            'active_session_token_hash' => hash('sha256', $token),
        ])->saveQuietly();

        $session->put(self::SESSION_KEY, $token);
        $session->put(self::SESSION_USER_KEY, (string) $user->getKey());

        return $token;
    }

    public function isCurrent(User $user, Session $session): bool
    {
        $token = $session->get(self::SESSION_KEY);
        $expectedHash = $user->active_session_token_hash;

        return is_string($token)
            && $token !== ''
            && is_string($expectedHash)
            && $expectedHash !== ''
            && hash_equals($expectedHash, hash('sha256', $token));
    }

    public function end(User $user, Session $session): void
    {
        if ($this->isCurrent($user, $session)) {
            $user->forceFill(['active_session_token_hash' => null])->saveQuietly();
        }

        $session->forget([
            self::SESSION_KEY,
            self::SESSION_USER_KEY,
        ]);
    }
}
