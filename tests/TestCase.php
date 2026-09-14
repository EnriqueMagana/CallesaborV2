<?php

namespace Tests;

use App\Services\DeliveryModulePolicy;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Impide que la suite corra contra una base de datos real.
     *
     * `phpunit.xml` fija `DB_CONNECTION=sqlite` con `:memory:`, pero esas
     * variables se leen con `env()`, y **`env()` se ignora cuando existe
     * `bootstrap/cache/config.php`**. Basta con haber corrido `config:cache`
     * (lo hace `composer deploy`) y lanzar `php artisan test` para que
     * `RefreshDatabase` haga `migrate:fresh` sobre MySQL y **borre los datos
     * de trabajo**. Pasó, y por eso existe esta guarda.
     *
     * Se comprueba en `refreshApplication()` porque corre antes de que
     * `setUpTraits()` dispare `RefreshDatabase`: cuando esto falla, todavía no
     * se tocó ninguna tabla.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        $isInMemory = $connection === 'sqlite' && in_array($database, [':memory:', null], true);

        if (! $isInMemory) {
            throw new RuntimeException(
                "Las pruebas iban a correr contra `{$connection}` / `{$database}`, no contra sqlite en memoria.\n"
                ."Casi siempre significa que hay configuración cacheada y `phpunit.xml` no puede sobrescribirla.\n"
                ."Solución: `php artisan config:clear` (o usa `composer test`, que ya lo hace).\n"
                .'Se abortó antes de tocar ninguna tabla.'
            );
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        // El policy memoriza el chequeo de esquema en una estatica de proceso;
        // entre casos el esquema se recrea, asi que hay que olvidarla.
        DeliveryModulePolicy::flush();
    }
}
