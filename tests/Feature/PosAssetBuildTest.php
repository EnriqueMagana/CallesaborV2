<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * El layout del POS sirve versiones `.min` de sus hojas de estilo y scripts,
 * generadas por `scripts/build-pos-assets.php`.
 *
 * El error fácil de cometer es editar `pos-modern.css`, recargar, y no entender
 * por qué el cambio no aparece: el navegador está recibiendo el `.min` viejo.
 * Esta prueba convierte ese desconcierto en un fallo de CI con instrucciones.
 */
class PosAssetBuildTest extends TestCase
{
    public function test_every_minified_asset_is_newer_than_its_source(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/pos.blade.php'));

        preg_match_all("/@assetVersion\('([^']+\.min\.(?:css|js))'\)/", $layout, $matches);

        $this->assertNotEmpty($matches[1], 'El layout del POS dejó de servir assets minificados.');

        foreach (array_unique($matches[1]) as $minified) {
            $minifiedPath = public_path($minified);
            $sourcePath = public_path(str_replace(['.min.css', '.min.js'], ['.css', '.js'], $minified));

            $this->assertFileExists(
                $minifiedPath,
                "Falta `{$minified}`. Ejecuta `composer build-pos-assets`."
            );

            if (! is_file($sourcePath)) {
                continue;
            }

            $this->assertGreaterThanOrEqual(
                filemtime($sourcePath),
                filemtime($minifiedPath),
                "`{$minified}` es más viejo que su fuente: el navegador está recibiendo una versión "
                .'anterior de ese archivo. Ejecuta `composer build-pos-assets`.'
            );
        }
    }
}
