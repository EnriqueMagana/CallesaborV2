<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;

class EnvironmentConfigurationService
{
    public function __construct(
        private readonly ?string $environmentPath = null,
        private readonly ?string $backupDirectory = null,
    ) {}

    /** @return array<string, array{label:string, description:string, icon:string, fields:array<string, array<string, mixed>>}> */
    public function definitions(): array
    {
        return [
            'general' => [
                'label' => 'Aplicación', 'description' => 'Identidad, idioma, zona horaria y modo de ejecución.', 'icon' => 'bx-world',
                'fields' => [
                    'APP_NAME' => $this->field('Nombre de la aplicación', 'text', 'Nombre usado por Laravel y los correos.'),
                    'APP_ENV' => $this->field('Entorno', 'select', 'Cambia el perfil operativo.', ['production', 'staging', 'local']),
                    'APP_DEBUG' => $this->field('Modo debug', 'boolean', 'Nunca debe permanecer activo en producción.'),
                    'APP_URL' => $this->field('URL pública', 'url', 'Dirección principal con protocolo HTTPS.'),
                    'BUSINESS_TIMEZONE' => $this->field('Zona horaria', 'timezone', 'Zona usada por pedidos, caja, reportes y el reloj.'),
                    'APP_LOCALE' => $this->field('Idioma principal', 'select', 'Idioma predeterminado de Laravel.', ['es', 'en']),
                    'APP_FALLBACK_LOCALE' => $this->field('Idioma de respaldo', 'select', 'Idioma usado cuando falta una traducción.', ['es', 'en']),
                ],
            ],
            'database' => [
                'label' => 'Base de datos', 'description' => 'Conexión principal. Un dato incorrecto puede impedir el acceso.', 'icon' => 'bx-data',
                'fields' => [
                    'DB_CONNECTION' => $this->field('Motor', 'select', 'Controlador de base de datos.', ['mysql', 'mariadb', 'pgsql', 'sqlsrv', 'sqlite']),
                    'DB_HOST' => $this->field('Servidor', 'text', 'Hostname o dirección IP.'),
                    'DB_PORT' => $this->field('Puerto', 'number', 'Puerto del servidor de base de datos.'),
                    'DB_DATABASE' => $this->field('Base de datos', 'text', 'Nombre del esquema principal.'),
                    'DB_USERNAME' => $this->field('Usuario', 'text', 'Usuario de conexión.'),
                    'DB_PASSWORD' => $this->field('Contraseña', 'secret', 'Déjalo vacío para conservar la contraseña actual.'),
                ],
            ],
            'mail' => [
                'label' => 'Correo', 'description' => 'Transportador, remitente y credenciales de envío.', 'icon' => 'bx-envelope',
                'fields' => [
                    'MAIL_MAILER' => $this->field('Transportador', 'select', 'Servicio usado para enviar correos.', ['resend', 'smtp', 'log']),
                    'MAIL_SCHEME' => $this->field('Esquema', 'select', 'Cifrado de la conexión SMTP.', ['null', 'tls', 'ssl']),
                    'MAIL_HOST' => $this->field('Servidor SMTP', 'text', 'Servidor del proveedor de correo.'),
                    'MAIL_PORT' => $this->field('Puerto SMTP', 'number', 'Puerto del proveedor de correo.'),
                    'MAIL_USERNAME' => $this->field('Usuario SMTP', 'text', 'Usuario entregado por el proveedor.'),
                    'MAIL_PASSWORD' => $this->field('Contraseña SMTP', 'secret', 'Déjalo vacío para conservar la contraseña actual.'),
                    'MAIL_FROM_ADDRESS' => $this->field('Correo remitente', 'email', 'Dirección autorizada para enviar.'),
                    'MAIL_FROM_NAME' => $this->field('Nombre remitente', 'text', 'Nombre visible para los clientes.'),
                    'RESEND_API_KEY' => $this->field('API key de Resend', 'secret', 'Déjalo vacío para conservar la llave actual.'),
                ],
            ],
            'integrations' => [
                'label' => 'Integraciones', 'description' => 'Firebase y almacenamiento externo.', 'icon' => 'bx-plug',
                'fields' => [
                    'FIREBASE_REALTIME_ENABLED' => $this->field('Firebase Realtime', 'boolean', 'Activa las señales de notificación en tiempo real.'),
                    'FIREBASE_DATABASE_URL' => $this->field('URL de Firebase', 'url', 'Endpoint de Realtime Database.'),
                    'FIREBASE_CREDENTIALS_PATH' => $this->field('Archivo de credenciales', 'text', 'Ruta del JSON de la cuenta de servicio.'),
                    'AWS_ACCESS_KEY_ID' => $this->field('AWS access key', 'secret', 'Déjalo vacío para conservar la llave actual.'),
                    'AWS_SECRET_ACCESS_KEY' => $this->field('AWS secret key', 'secret', 'Déjalo vacío para conservar el secreto actual.'),
                    'AWS_DEFAULT_REGION' => $this->field('Región AWS', 'text', 'Región donde se encuentran los recursos.'),
                    'AWS_BUCKET' => $this->field('Bucket AWS', 'text', 'Nombre del bucket configurado.'),
                ],
            ],
            'runtime' => [
                'label' => 'Rendimiento', 'description' => 'Logs, caché, sesiones, colas y módulos técnicos.', 'icon' => 'bx-tachometer',
                'fields' => [
                    'LOG_LEVEL' => $this->field('Nivel de log', 'select', 'Detalle escrito en el registro.', ['debug', 'info', 'notice', 'warning', 'error', 'critical']),
                    'CACHE_STORE' => $this->field('Caché', 'select', 'Almacén usado por Laravel.', ['database', 'redis', 'file', 'array']),
                    'SESSION_DRIVER' => $this->field('Sesiones', 'select', 'Almacén de sesiones autenticadas.', ['database', 'redis', 'file']),
                    'SESSION_LIFETIME' => $this->field('Duración de sesión', 'number', 'Minutos antes de expirar una sesión inactiva.'),
                    'QUEUE_CONNECTION' => $this->field('Cola', 'select', 'Conexión usada por tareas en segundo plano.', ['database', 'redis', 'sync']),
                    'PULSE_ENABLED' => $this->field('Laravel Pulse', 'boolean', 'Activa la captura de métricas técnicas.'),
                    'PROMOTION_RULE_ENGINE_ENABLED' => $this->field('Motor de promociones', 'boolean', 'Activa el cálculo automático de promociones.'),
                ],
            ],
        ];
    }

    /** @return array{values:array<string,string>, secrets:array<string,bool>, writable:bool, path:string} */
    public function snapshot(): array
    {
        $path = $this->path();
        $parsed = $this->parse(File::exists($path) ? File::get($path) : '');
        $values = [];
        $secrets = [];

        foreach ($this->fields() as $key => $definition) {
            $current = (string) ($parsed[$key] ?? '');
            $isSecret = $definition['type'] === 'secret';
            $values[$key] = $isSecret ? '' : $current;
            $secrets[$key] = $isSecret && ! in_array($current, ['', 'null'], true);
        }

        return ['values' => $values, 'secrets' => $secrets, 'writable' => File::exists($path) && is_writable($path), 'path' => basename($path)];
    }

    /** @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function validated(array $input): array
    {
        $rules = [];
        foreach ($this->fields() as $key => $field) {
            $rules[$key] = match ($field['type']) {
                'select' => ['nullable', 'string', Rule::in($field['options'])],
                'boolean' => ['nullable', Rule::in(['true', 'false', true, false, 1, 0, '1', '0'])],
                'timezone' => ['required', 'string', 'timezone:all'],
                'url' => ['nullable', 'url:http,https', 'max:2048'],
                'email' => ['nullable', 'email:rfc', 'max:254'],
                'number' => ['nullable', 'integer', 'min:0', 'max:65535'],
                default => ['nullable', 'string', 'max:2048'],
            };
        }

        $validator = Validator::make(array_intersect_key($input, $rules), $rules);
        $validator->after(function ($validator) use ($input): void {
            if (($input['APP_ENV'] ?? null) === 'production' && in_array($input['APP_DEBUG'] ?? null, ['true', true, 1, '1'], true)) {
                $validator->errors()->add('APP_DEBUG', 'El modo debug debe permanecer desactivado en producción.');
            }

            if (($input['APP_ENV'] ?? null) === 'production'
                && isset($input['APP_URL'])
                && ! str_starts_with(strtolower((string) $input['APP_URL']), 'https://')) {
                $validator->errors()->add('APP_URL', 'La URL pública debe usar HTTPS en producción.');
            }
        });

        $validated = $validator->validate();

        return collect($validated)->map(fn ($value): string => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value)->all();
    }

    /** @param array<string, string> $input
     * @return array{changed:array<int,string>, backup:?string}
     */
    public function update(array $input): array
    {
        return Cache::lock('developer-environment-update', 15)->block(5, function () use ($input): array {
            $path = $this->path();
            if (! File::exists($path) || ! is_writable($path)) {
                throw new RuntimeException('El archivo de entorno no existe o el proceso PHP no tiene permiso de escritura.');
            }

            $content = File::get($path);
            $current = $this->parse($content);
            $allowed = $this->fields();
            $changes = [];

            foreach ($input as $key => $value) {
                if (! isset($allowed[$key])) {
                    continue;
                }
                if ($allowed[$key]['type'] === 'secret' && $value === '') {
                    continue;
                }
                if ((string) ($current[$key] ?? '') !== $value) {
                    $changes[$key] = $value;
                }
            }

            if ($changes === []) {
                return ['changed' => [], 'backup' => null];
            }

            $backup = $this->createBackup($path);
            $updated = $this->replaceValues($content, $changes);
            File::replace($path, $updated);
            @chmod($path, 0600);
            Artisan::call('config:clear');

            return ['changed' => array_keys($changes), 'backup' => basename($backup)];
        });
    }

    /** @param array<string, string> $input
     * @return array<int, string>
     */
    public function changedKeys(array $input): array
    {
        $path = $this->path();
        $current = $this->parse(File::exists($path) ? File::get($path) : '');
        $allowed = $this->fields();
        $changed = [];

        foreach ($input as $key => $value) {
            if (! isset($allowed[$key])) {
                continue;
            }
            if ($allowed[$key]['type'] === 'secret' && $value === '') {
                continue;
            }
            if ((string) ($current[$key] ?? '') !== $value) {
                $changed[] = $key;
            }
        }

        return $changed;
    }

    /** @return array<string, array<string, mixed>> */
    private function fields(): array
    {
        return collect($this->definitions())->flatMap(fn (array $group) => $group['fields'])->all();
    }

    /** @return array<string, mixed> */
    private function field(string $label, string $type, string $description, array $options = []): array
    {
        return compact('label', 'type', 'description', 'options');
    }

    /** @return array<string, string> */
    private function parse(string $content): array
    {
        $values = [];
        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            if (! preg_match('/^\s*([A-Z][A-Z0-9_]*)\s*=\s*(.*)$/', $line, $matches)) {
                continue;
            }
            $value = trim($matches[2]);
            if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
                $value = substr($value, 1, -1);
                $value = str_replace(['\\n', '\\"', '\\$', '\\\\'], ["\n", '"', '$', '\\'], $value);
            }
            $values[$matches[1]] = $value;
        }

        return $values;
    }

    /** @param array<string, string> $changes */
    private function replaceValues(string $content, array $changes): string
    {
        $remaining = $changes;
        $lines = preg_split('/\R/', $content) ?: [];
        foreach ($lines as &$line) {
            if (preg_match('/^\s*([A-Z][A-Z0-9_]*)\s*=/', $line, $matches) && array_key_exists($matches[1], $remaining)) {
                $line = $matches[1].'='.$this->encode($remaining[$matches[1]]);
                unset($remaining[$matches[1]]);
            }
        }
        unset($line);

        if ($remaining !== []) {
            $lines[] = '';
            $lines[] = '# Configuración actualizada desde el Centro técnico';
            foreach ($remaining as $key => $value) {
                $lines[] = $key.'='.$this->encode($value);
            }
        }

        return rtrim(implode(PHP_EOL, $lines)).PHP_EOL;
    }

    private function encode(string $value): string
    {
        if ($value === 'null' || in_array($value, ['true', 'false'], true) || preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            return $value;
        }

        return '"'.str_replace(['\\', '"', '$', "\n", "\r"], ['\\\\', '\\"', '\\$', '\\n', ''], $value).'"';
    }

    private function createBackup(string $path): string
    {
        $directory = $this->backupDirectory ?? storage_path('app/private/environment-backups');
        File::ensureDirectoryExists($directory, 0700, true);
        $backup = $directory.DIRECTORY_SEPARATOR.'.env.'.now()->format('Ymd_His').'_'.bin2hex(random_bytes(3)).'.backup';
        File::copy($path, $backup);
        @chmod($backup, 0600);

        collect(File::files($directory))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->slice(10)
            ->each(fn ($file) => File::delete($file->getPathname()));

        return $backup;
    }

    private function path(): string
    {
        return $this->environmentPath ?? app()->environmentFilePath();
    }
}
