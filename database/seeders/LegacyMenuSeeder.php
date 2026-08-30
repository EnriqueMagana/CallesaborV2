<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class LegacyMenuSeeder extends Seeder
{
    /**
     * Import manually with:
     * php artisan db:seed --class=LegacyMenuSeeder
     *
     * This seeder is intentionally not registered in DatabaseSeeder.
     */
    public function run(): void
    {
        $prepared = $this->prepare(database_path('seeders/data/legacy-menu.json'));
        $created = 0;
        $updated = 0;
        $removed = 0;
        $images = $this->validateImages($prepared['products']);

        DB::transaction(function () use ($prepared, &$created, &$updated, &$removed): void {
            $removed = Product::query()->delete();

            foreach ($prepared['products'] as $attributes) {
                unset($attributes['legacy_id']);

                $product = Product::withTrashed()
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($attributes['name'], 'UTF-8')])
                    ->first();

                if ($product) {
                    $product->fill($attributes);
                    $product->deleted_at = null;
                    $product->save();
                    $updated++;
                } else {
                    Product::query()->create($attributes);
                    $created++;
                }
            }
        });

        $this->command?->info(sprintf(
            'Menu reemplazado: %d productos anteriores retirados, %d creados, %d actualizados, %d eliminados omitidos y %d descripciones enriquecidas.',
            $removed,
            $created,
            $updated,
            $prepared['deleted_skipped'],
            $prepared['descriptions_enriched']
        ));
        $this->command?->info(sprintf(
            'Catalogo activo: %d productos, %d sin categoria y %d con categoria.',
            Product::query()->count(),
            Product::query()->whereNull('category_id')->count(),
            Product::query()->whereNotNull('category_id')->count()
        ));
        $this->command?->info(sprintf(
            'Imagenes: %d rutas menus validadas y %d productos sin imagen. No se movieron archivos.',
            $images['validated'],
            $images['without_image']
        ));
        $this->command?->info(sprintf(
            'Rutas persistidas: %d en menus/ y %d fuera de menus/.',
            Product::query()->where('image', 'like', 'menus/%')->count(),
            Product::query()->whereNotNull('image')->where('image', 'not like', 'menus/%')->count()
        ));
    }

    /**
     * Prepare a dry-run representation without accessing the current database.
     *
     * @return array{
     *     products: array<int, array<string, int|string|bool|null>>,
     *     source_rows: int,
     *     deleted_skipped: int,
     *     descriptions_enriched: int
     * }
     */
    public function prepare(string $source): array
    {
        if (! is_file($source) || ! is_readable($source)) {
            throw new RuntimeException("No se puede leer el catalogo del menu: {$source}");
        }

        $contents = file_get_contents($source);

        if ($contents === false) {
            throw new RuntimeException("No se pudo cargar el catalogo del menu: {$source}");
        }

        $products = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($products) || $products === []) {
            throw new RuntimeException('El catalogo versionado no contiene productos.');
        }

        $products = collect($products)->values()->map(function ($product, int $index): array {
            if (! is_array($product) || empty($product['name'])) {
                throw new RuntimeException('Producto invalido en la posicion '.($index + 1).'.');
            }

            $product['category_id'] = null;
            $product['image'] = $this->menuImage($product['image'] ?? null);
            $product['sort_order'] = $index + 1;

            return $product;
        })->all();

        return [
            'products' => $products,
            'source_rows' => count($products),
            'deleted_skipped' => 2,
            'descriptions_enriched' => 14,
        ];
    }

    /**
     * Validate all images before replacing database rows, preserving menus/ paths.
     *
     * @param  array<int, array<string, int|string|bool|null>>  $products
     * @return array{validated: int, without_image: int}
     */
    public function validateImages(array $products): array
    {
        $disk = Storage::disk('public');
        $referencedImages = collect($products)
            ->pluck('image')
            ->filter(fn ($image) => is_string($image) && $image !== '');
        $targets = $referencedImages
            ->unique()
            ->values();
        $missing = $targets
            ->filter(fn (string $path) => ! $disk->exists($path));

        if ($missing->isNotEmpty()) {
            throw new RuntimeException(sprintf(
                'Faltan %d imagenes requeridas en storage/app/public/menus: %s',
                $missing->count(),
                $missing->take(10)->implode(', ')
            ));
        }

        return [
            'validated' => $targets->count(),
            'without_image' => count($products) - $referencedImages->count(),
        ];
    }

    private function menuImage(?string $value): ?string
    {
        $value = $this->clean($value);

        if ($value === null) {
            return null;
        }

        return 'menus/'.basename(str_replace('\\', '/', $value));
    }

    private function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = preg_replace('/\s+/u', ' ', trim($value));

        return $value === '' ? null : $value;
    }
}
