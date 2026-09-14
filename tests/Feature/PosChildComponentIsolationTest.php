<?php

namespace Tests\Feature;

use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El catálogo y los paneles son componentes hijos para que el padre deje de
 * reconstruirlos en cada click. Eso solo funciona si Livewire reconoce al hijo
 * entre un render y el siguiente, y para reconocerlo compara una clave.
 *
 * Sin un `wire:key` explícito, Livewire genera la clave a partir de la posición
 * en el Blade y le agrega un sufijo que depende del contexto del render. El
 * resultado es que la clave del render inicial (`lw-1430169160-0`) nunca coincide
 * con la del update (`lw-1430169160-0-15`), el hijo no se reconoce, y se vuelve a
 * renderizar entero. Con 88 productos eso son 276 KB por click en vez de 59 KB.
 *
 * Esta prueba mide lo que el navegador recibe de verdad —el POST a
 * /livewire/update— y no el HTML del componente padre. Es una distinción que
 * importa: `Livewire::test()->html()` devuelve solo la parte del padre, así que
 * da por bueno un hijo que en realidad viaja completo en la respuesta.
 */
class PosChildComponentIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_touching_a_product_does_not_resend_the_catalog(): void
    {
        [$user, $product] = $this->posScenario();

        $page = $this->actingAs($user)->get(route('app.pos'))->assertOk()->getContent();

        $this->assertStringContainsString(
            'pos-product-'.$product->id,
            $page,
            'El catálogo no se dibujó en la carga inicial.'
        );

        $response = $this->updatePointOfSale($user, $page, 'openCustomizeModal', [$product->id]);
        $body = $response->getContent();

        $this->assertStringNotContainsString(
            'pos-product-'.$product->id,
            $body,
            "El catálogo viajó completo en la respuesta del click (".round(strlen($body) / 1024)." KB). "
            .'Revisa que `<livewire:pos.catalog>` conserve su `wire:key` en `point-of-sale.blade.php`: '
            .'sin él Livewire no reconoce al hijo entre renders y lo reconstruye cada vez.'
        );
    }

    public function test_every_child_of_the_point_of_sale_declares_a_stable_key(): void
    {
        $views = [
            resource_path('views/livewire/pos/point-of-sale.blade.php'),
            resource_path('views/livewire/pos/partials/header.blade.php'),
        ];

        foreach ($views as $view) {
            preg_match_all('/<livewire:[^>]*>/', file_get_contents($view), $matches);

            foreach ($matches[0] as $tag) {
                $this->assertStringContainsString(
                    'wire:key=',
                    $tag,
                    "Este componente hijo no tiene `wire:key`, así que Livewire lo reconstruirá en cada "
                    ."respuesta en vez de omitirlo:\n  {$tag}\n  en ".basename($view)
                );
            }
        }
    }

    /**
     * Hace el mismo POST que haría el navegador, con el snapshot de la página.
     */
    private function updatePointOfSale(User $user, string $page, string $method, array $params)
    {
        preg_match('/wire:snapshot="([^"]*)"/', $page, $match);

        $snapshot = html_entity_decode($match[1], ENT_QUOTES);
        $this->assertStringContainsString('point-of-sale', $snapshot, 'El primer componente de la página no es el POS.');

        return $this->actingAs($user)->postJson('/livewire/update', [
            '_token' => csrf_token(),
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => (object) [],
                'calls' => [['path' => '', 'method' => $method, 'params' => $params]],
            ]],
        ])->assertOk();
    }

    /**
     * @return array{0: User, 1: Product}
     */
    private function posScenario(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('owner');

        CashRegister::create([
            'name' => 'Caja aislamiento',
            'opened_by' => $user->id,
            'initial_amount' => 0,
            'opened_at' => now(),
            'is_open' => true,
        ]);

        $category = Category::create(['name' => 'Aislamiento', 'is_active' => true]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Producto aislado',
            'price' => 60,
            'is_customizable' => false,
            'is_active' => true,
        ]);

        return [$user, $product];
    }
}
