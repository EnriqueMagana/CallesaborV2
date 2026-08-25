<?php

namespace App\Services\Firebase;

use RuntimeException;

class FirebaseConfiguration
{
    /** @var list<string> */
    private const WEB_KEYS = [
        'apiKey',
        'authDomain',
        'databaseURL',
        'projectId',
        'storageBucket',
        'messagingSenderId',
        'appId',
        'measurementId',
    ];

    public function requested(): bool
    {
        return (bool) config('firebase.realtime.enabled');
    }

    public function ready(): bool
    {
        if (! $this->requested() || $this->databaseUrl() === null) {
            return false;
        }

        try {
            $credentials = $this->credentials();

            return isset($credentials['client_email'], $credentials['private_key']);
        } catch (RuntimeException) {
            return false;
        }
    }

    /** @return array<string, string> */
    public function web(): array
    {
        $contents = $this->readFile((string) config('firebase.realtime.web_config_path'));
        $values = [];

        foreach (self::WEB_KEYS as $key) {
            if (preg_match('/(?:["\']?'.preg_quote($key, '/').'["\']?)\s*:\s*["\']([^"\']+)["\']/', $contents, $matches) === 1) {
                $values[$key] = $matches[1];
            }
        }

        if ($databaseUrl = $this->databaseUrl()) {
            $values['databaseURL'] = $databaseUrl;
        }

        return $values;
    }

    public function databaseUrl(): ?string
    {
        $configured = trim((string) config('firebase.realtime.database_url'));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        $contents = $this->readFile((string) config('firebase.realtime.web_config_path'));
        if (preg_match('/(?:["\']?databaseURL["\']?)\s*:\s*["\']([^"\']+)["\']/', $contents, $matches) === 1) {
            return rtrim($matches[1], '/');
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function credentials(): array
    {
        $path = (string) config('firebase.realtime.credentials_path');
        $contents = $this->readFile($path);
        $credentials = json_decode($contents, true);

        if (! is_array($credentials) || empty($credentials['client_email']) || empty($credentials['private_key'])) {
            throw new RuntimeException('La cuenta de servicio de Firebase no es válida.');
        }

        return $credentials;
    }

    /** @return list<string> */
    public function missingRequirements(): array
    {
        $missing = [];
        if ($this->databaseUrl() === null) {
            $missing[] = 'database_url';
        }

        try {
            $this->credentials();
        } catch (RuntimeException) {
            $missing[] = 'service_account';
        }

        $web = $this->web();
        foreach (['apiKey', 'authDomain', 'projectId', 'appId'] as $key) {
            if (empty($web[$key])) {
                $missing[] = 'web.'.$key;
            }
        }

        return array_values(array_unique($missing));
    }

    public function rootPath(): string
    {
        return trim((string) config('firebase.realtime.root_path', 'notifications'), '/') ?: 'notifications';
    }

    public function userUid(int|string $userId): string
    {
        return 'laravel_'.$userId;
    }

    private function readFile(string $path): string
    {
        if ($path !== '' && ! str_starts_with($path, '/') && preg_match('/^[A-Za-z]:[\\\\\/]/', $path) !== 1) {
            $path = base_path($path);
        }

        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            return '';
        }

        return (string) file_get_contents($path);
    }
}
