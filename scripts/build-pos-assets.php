<?php

/*
|--------------------------------------------------------------------------
| Build de assets del punto de venta
|--------------------------------------------------------------------------
| Minifica las hojas de estilo y los scripts que carga la terminal del POS.
|
|   php scripts/build-pos-assets.php
|
| Los archivos fuente NO se tocan: se escribe un `.min` al lado. Las pruebas
| siguen leyendo la fuente, y `layouts/pos.blade.php` referencia el `.min`.
|
| `composer deploy` lo ejecuta. `PosAssetBuildTest` falla si un `.min` quedó
| más viejo que su fuente, que es el error fácil de cometer: editar el CSS y
| no volver a construir.
*/

$root = dirname(__DIR__);
$esbuild = $root.'/node_modules/.bin/esbuild';

if (! is_file($esbuild) && ! is_file($esbuild.'.cmd')) {
    fwrite(STDERR, "No se encontró esbuild en node_modules. Corre `npm install`.\n");
    exit(1);
}

if (PHP_OS_FAMILY === 'Windows' && is_file($esbuild.'.cmd')) {
}

/** Fuentes a minificar, relativas a `public/`. */
const SOURCES = [
    'assets/vendor/css/core.css',
    'assets/vendor/css/theme-default.css',
    'assets/css/pos.css',
    'assets/css/pos-modern.css',
    'assets/css/extracted-ui.css',
    'assets/css/pos-mobile-navigation.css',
    'assets/css/notification-center.css',
    'assets/css/ticket-preview-modal.css',
    'assets/css/confirm-modal.css',
    'assets/css/demo.css',
    'assets/js/pos-root.js',
    'assets/js/config.js',
    'assets/js/ticket-preview-modal.js',
    'assets/js/notification-center.js',
];

$savedBytes = 0;
$failures = 0;

foreach (SOURCES as $relative) {
    $source = $root.'/public/'.$relative;

    if (! is_file($source)) {
        printf("  omitido (no existe)  %s\n", $relative);

        continue;
    }

    $extension = pathinfo($relative, PATHINFO_EXTENSION);
    $target = preg_replace('/\.'.$extension.'$/', '.min.'.$extension, $source);

    $command = escapeshellarg($esbuild)
        .' '.escapeshellarg($source)
        .' --minify --legal-comments=none --outfile='.escapeshellarg($target).' 2>&1';

    exec($command, $output, $status);

    if ($status !== 0 || ! is_file($target)) {
        fwrite(STDERR, "  FALLÓ  {$relative}\n".implode("\n", $output)."\n");
        $failures++;

        continue;
    }

    // `touch` para que el `.min` nunca quede más viejo que su fuente.
    touch($target);

    $before = filesize($source);
    $after = filesize($target);
    $savedBytes += $before - $after;

    printf("  %7.1f → %7.1f KB  (-%2d%%)  %s\n", $before / 1024, $after / 1024, round(100 - $after / $before * 100), $relative);
}

printf("\nAhorro total: %.1f KB\n", $savedBytes / 1024);

exit($failures > 0 ? 1 : 0);
