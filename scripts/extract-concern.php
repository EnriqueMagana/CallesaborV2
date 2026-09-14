<?php

/*
|--------------------------------------------------------------------------
| Extractor de concerns del punto de venta
|--------------------------------------------------------------------------
| Mueve metodos completos de `PointOfSale.php` a un trait, conservando sus
| atributos (#[Computed], #[On]) y su docblock.
|
|   php scripts/extract-concern.php ManagesQuotations "Doc del trait" metodoA metodoB ...
|
| Solo mueve texto: no reescribe cuerpos ni firmas. Si un metodo no aparece
| exactamente una vez, aborta sin tocar nada.
*/

$argv = $_SERVER['argv'];

if (count($argv) < 4) {
    fwrite(STDERR, "uso: extract-concern.php <Trait> <doc> <metodo> [metodo...]\n");
    exit(1);
}

$trait = $argv[1];
$doc = $argv[2];
$methods = array_slice($argv, 3);

$source = 'app/Livewire/Pos/PointOfSale.php';
$lines = file($source);

/** Devuelve [inicio, fin] (indices 0-based, fin inclusive) del bloque del metodo. */
$locate = function (array $lines, string $name): ?array {
    $signature = null;
    $starts = [];

    foreach ($lines as $i => $line) {
        if (preg_match('/^\s*(?:public|private|protected)(?:\s+static)?\s+function\s+'.preg_quote($name, '/').'\s*\(/', $line)) {
            $starts[] = $i;
        }
    }

    if (count($starts) !== 1) {
        return null;
    }

    $start = $starts[0];

    // Sube para incluir atributos y docblock.
    $i = $start - 1;
    while ($i >= 0) {
        $trimmed = trim($lines[$i]);
        if ($trimmed === '' ) { break; }
        if (str_starts_with($trimmed, '#[') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '/**') || str_starts_with($trimmed, '*/')) {
            $start = $i;
            $i--;

            continue;
        }
        break;
    }

    // Baja contando llaves hasta cerrar el cuerpo.
    $depth = 0; $opened = false;
    for ($j = $starts[0]; $j < count($lines); $j++) {
        $depth += substr_count($lines[$j], '{') - substr_count($lines[$j], '}');
        if (str_contains($lines[$j], '{')) { $opened = true; }
        if ($opened && $depth === 0) {
            return [$start, $j];
        }
    }

    return null;
};

$ranges = [];
foreach ($methods as $name) {
    $range = $locate($lines, $name);
    if ($range === null) {
        fwrite(STDERR, "ABORTA: `{$name}` no aparece exactamente una vez.\n");
        exit(1);
    }
    $ranges[$name] = $range;
}

// Comprueba que no se solapen.
$sorted = array_values($ranges);
usort($sorted, fn ($a, $b) => $a[0] <=> $b[0]);
for ($i = 1; $i < count($sorted); $i++) {
    if ($sorted[$i][0] <= $sorted[$i - 1][1]) {
        fwrite(STDERR, "ABORTA: bloques solapados.\n");
        exit(1);
    }
}

// Cuerpo del trait, en el orden pedido.
$body = '';
foreach ($methods as $name) {
    [$from, $to] = $ranges[$name];
    $body .= implode('', array_slice($lines, $from, $to - $from + 1))."\n";
}

// Imports del componente: el trait necesita los mismos simbolos.
$imports = [];
foreach ($lines as $line) {
    if (preg_match('/^use\s+[^;]+;$/', trim($line)) && ! str_contains($line, 'Livewire\Pos\Concerns')) {
        $imports[] = trim($line);
    }
}

$traitFile = "app/Livewire/Pos/Concerns/{$trait}.php";
file_put_contents($traitFile, "<?php\n\nnamespace App\Livewire\Pos\Concerns;\n\n".implode("\n", $imports)."\n\n/**\n * ".str_replace("\n", "\n * ", $doc)."\n */\ntrait {$trait}\n{\n".rtrim($body)."\n}\n");

// Quita los bloques del componente, de atras hacia adelante.
usort($sorted, fn ($a, $b) => $b[0] <=> $a[0]);
foreach ($sorted as [$from, $to]) {
    array_splice($lines, $from, $to - $from + 1);
}

file_put_contents($source, implode('', $lines));

printf("%s: %d métodos movidos\n", $trait, count($methods));
