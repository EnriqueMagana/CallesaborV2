<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Version de cache-busting para los assets publicos.
 *
 * Las plantillas resolvian la version con `filemtime()` en linea, lo que
 * significaba una lectura de disco por cada etiqueta <link> y <script>: doce
 * por request solo en el layout del POS.
 *
 * Aqui la version se resuelve una vez y se memoriza en memoria para el resto
 * del request, y ademas se guarda en cache entre requests. `optimize:clear`
 * (o el script `composer deploy`) la invalida, que es exactamente cuando los
 * archivos pudieron cambiar.
 */
class AssetVersion
{
    private const CACHE_KEY = 'assets.versions';

    private const CACHE_TTL = 86400;

    /** @var array<string, string>|null */
    private static ?array $versions = null;

    /**
     * Devuelve la URL del asset con su parametro de version.
     */
    public static function url(string $path): string
    {
        return asset($path).'?v='.self::for($path);
    }

    /**
     * Devuelve solo la version del asset.
     */
    public static function for(string $path): string
    {
        $versions = self::versions();

        if (array_key_exists($path, $versions)) {
            return $versions[$path];
        }

        $versions[$path] = self::resolve($path);
        self::$versions = $versions;

        Cache::put(self::CACHE_KEY, $versions, self::CACHE_TTL);

        return $versions[$path];
    }

    /**
     * Vacia la memoria y la cache. Pensado para los tests y el despliegue.
     */
    public static function flush(): void
    {
        self::$versions = null;
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, string>
     */
    private static function versions(): array
    {
        if (self::$versions !== null) {
            return self::$versions;
        }

        $cached = Cache::get(self::CACHE_KEY);

        return self::$versions = is_array($cached) ? $cached : [];
    }

    private static function resolve(string $path): string
    {
        $file = public_path($path);

        // Un asset que no existe no debe tumbar el render: `filemtime()` emitia
        // un warning y devolvia false en ese caso.
        if (! is_file($file)) {
            return (string) config('app.version', '1');
        }

        return (string) filemtime($file);
    }
}
