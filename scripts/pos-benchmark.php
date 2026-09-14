<?php

/*
|--------------------------------------------------------------------------
| Benchmark del Punto de Venta
|--------------------------------------------------------------------------
| Mide el coste real de la accion mas frecuente de un turno: tocar un
| producto no personalizable para agregarlo al carrito.
|
|   php artisan tinker --execute="require base_path('scripts/pos-benchmark.php');"
|
| Reporta consultas, tiempo de SQL, tiempo total y peso del HTML para poder
| anotarlos en la bitacora de OPTIMIZACION-POS.md.
*/

use App\Livewire\Pos\PointOfSale;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

$user = User::query()->first();

if (! $user) {
    fwrite(STDERR, "No hay usuarios en la base de datos.\n");

    return;
}

auth()->login($user);

$product = Product::query()
    ->where('is_active', true)
    ->where('is_customizable', false)
    ->first();

if (! $product) {
    fwrite(STDERR, "No hay productos activos no personalizables.\n");

    return;
}

$component = Livewire::test(PointOfSale::class);

$queries = [];
$recording = false;

DB::listen(function ($query) use (&$queries, &$recording) {
    if ($recording) {
        $queries[] = [$query->sql, $query->time];
    }
});

$recording = true;
$start = microtime(true);
$component->call('openCustomizeModal', $product->id);
$elapsed = (microtime(true) - $start) * 1000;
$recording = false;

$html = $component->html();

printf(
    "\nProducto: %s (#%d)\n",
    $product->name ?? '?',
    $product->id
);

printf(
    "Queries: %d | SQL: %.1f ms | Total: %.1f ms | HTML: %.1f KB\n\n",
    count($queries),
    array_sum(array_column($queries, 1)),
    $elapsed,
    strlen($html) / 1024
);

foreach ($queries as $index => [$sql, $time]) {
    printf(
        "%2d [%5.1fms] %s\n",
        $index + 1,
        $time,
        substr(preg_replace('/\s+/', ' ', $sql), 0, 110)
    );
}

echo "\n";
