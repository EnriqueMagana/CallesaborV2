<?php

namespace Tests\Feature;

use App\Livewire\Pos\PointOfSale;
use App\Models\Area;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Mesa;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Red de seguridad de rendimiento del POS.
 *
 * Estos techos existen para que una consulta nueva en la ruta caliente falle
 * en CI en vez de degradar la caja en silencio. Bajarlos al cerrar cada fase
 * de OPTIMIZACION-POS.md; nunca subirlos sin anotar el motivo en la bitacora.
 */
class PosPerformanceTest extends TestCase
{
    use RefreshDatabase;

    // Medido en 5 sobre sqlite en memoria con la base vacia tras la fase 2
    // (eran 18 antes). 2026-09-13.
    /** Techo de consultas al tocar un producto simple (openCustomizeModal -> carrito). */
    private const ADD_TO_CART_QUERY_BUDGET = 6;

    public function test_adding_a_simple_product_stays_within_its_query_budget(): void
    {
        [$user, $product] = $this->posScenario();

        $component = Livewire::actingAs($user)->test(PointOfSale::class);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $component->call('openCustomizeModal', $product->id);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $component->assertCount('cart', 1);

        $this->assertLessThanOrEqual(
            self::ADD_TO_CART_QUERY_BUDGET,
            count($queries),
            sprintf(
                "openCustomizeModal ejecuto %d consultas, el techo es %d.\n%s",
                count($queries),
                self::ADD_TO_CART_QUERY_BUDGET,
                collect($queries)
                    ->map(fn ($query, $index) => sprintf(
                        '%2d %s',
                        $index + 1,
                        substr(preg_replace('/\s+/', ' ', $query['query']), 0, 110)
                    ))
                    ->implode("\n")
            )
        );
    }

    public function test_closed_panels_do_not_query_the_database(): void
    {
        [$user] = $this->posScenario();

        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::actingAs($user)->test(PointOfSale::class);

        $queries = collect(DB::getQueryLog())
            ->pluck('query')
            ->map(fn ($query) => mb_strtolower($query));

        DB::disableQueryLog();

        // Ningun panel esta abierto en el primer render, asi que sus tablas no
        // deberian aparecer en el log.
        foreach (['mesa_services'] as $table) {
            $this->assertSame(
                0,
                $queries->filter(fn ($query) => str_contains($query, " {$table} "))->count(),
                "El panel cerrado sigue consultando `{$table}` en el render inicial."
            );
        }
    }

    /**
     * @return array{0: User, 1: Product}
     */
    private function posScenario(): array
    {
        $user = User::factory()->create();

        CashRegister::create([
            'name' => 'Caja rendimiento',
            'opened_by' => $user->id,
            'initial_amount' => 0,
            'opened_at' => now(),
            'is_open' => true,
        ]);

        $category = Category::create(['name' => 'Rendimiento', 'is_active' => true]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Producto simple',
            'price' => 50,
            'is_customizable' => false,
            'is_active' => true,
        ]);

        return [$user, $product];
    }

    public function test_toolbar_counters_match_the_individual_queries(): void
    {
        [$user] = $this->posScenario();
        $register = CashRegister::query()->firstOrFail();
        $other = CashRegister::create([
            'name' => 'Otra caja',
            'opened_by' => $user->id,
            'initial_amount' => 0,
            'opened_at' => now()->subDay(),
            'is_open' => false,
        ]);

        $make = function (array $attributes) use ($register) {
            return Order::create(array_merge([
                'cash_register_id' => $register->id,
                'served_by' => $register->opened_by,
                'status' => 'pendiente',
                'type' => 'ventanilla',
                'subtotal' => 0,
                'total' => 0,
            ], $attributes));
        };

        // pickup: mostrador sin pagos, y kiosko takeaway.
        $make(['type' => 'ventanilla', 'status' => 'lista']);
        $make(['type' => 'pick_up', 'status' => 'en_preparacion']);
        $make(['type' => 'ventanilla', 'source' => 'kiosk', 'fulfillment' => 'takeaway', 'status' => 'pendiente']);
        // kiosko dine_in no cuenta como pickup.
        $make(['type' => 'ventanilla', 'source' => 'kiosk', 'fulfillment' => 'dine_in', 'status' => 'pendiente']);
        // entregada no cuenta.
        $make(['type' => 'ventanilla', 'status' => 'entregada']);
        // pagada no cuenta.
        $paid = $make(['type' => 'ventanilla', 'status' => 'lista']);
        OrderPayment::create([
            'order_id' => $paid->id,
            'method' => 'efectivo',
            'amount' => 10,
        ]);

        // delivery pendiente.
        $make(['type' => 'delivery', 'status' => 'pendiente']);
        $make(['type' => 'delivery', 'status' => 'lista']);

        // mesas heredadas: dos ordenes de la misma mesa cuentan una vez.
        $area = Area::create(['name' => 'Salón']);
        $mesa = Mesa::create([
            'area_id' => $area->id,
            'number' => 7,
            'capacity' => 4,
            'status' => 'ocupada',
        ]);
        $make(['mesa_id' => $mesa->id, 'type' => 'mesa', 'status' => 'pendiente']);
        $make(['mesa_id' => $mesa->id, 'type' => 'mesa', 'status' => 'lista']);

        // otra caja no debe contarse.
        Order::create([
            'cash_register_id' => $other->id,
            'served_by' => $user->id,
            'status' => 'pendiente',
            'type' => 'delivery',
            'subtotal' => 0,
            'total' => 0,
        ]);

        $counts = Livewire::actingAs($user)->test(PointOfSale::class)->instance()->toolbarPendingCounts;

        $this->assertSame(3, $counts['pickup']);
        $this->assertSame(1, $counts['delivery']);
        $this->assertSame(1, $counts['tables']);
    }
}
