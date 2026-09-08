<?php

namespace Tests\Feature;

use App\Livewire\Orders\SalesHistory;
use App\Models\CashRegister;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SidebarMenuItem;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SidebarMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

class SalesHistoryAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        $this->seed([RolesAndPermissionsSeeder::class, SidebarMenuSeeder::class]);
    }

    public function test_orders_are_not_shown_until_the_audit_is_generated(): void
    {
        [$owner, $order] = $this->sale();

        Livewire::actingAs($owner)
            ->test(SalesHistory::class)
            ->assertSet('hasSearched', false)
            ->assertSee('El historial está listo para auditar')
            ->assertDontSee($order->display_folio)
            ->set('datePreset', 'all')
            ->call('runAudit')
            ->assertHasNoErrors()
            ->assertSet('hasSearched', true)
            ->assertSee($order->display_folio)
            ->assertSee('Hamburguesa clásica');
    }

    public function test_audit_includes_sales_from_an_open_register_and_builds_metrics(): void
    {
        [$owner, $order] = $this->sale(registerOpen: true);

        $component = Livewire::actingAs($owner)
            ->test(SalesHistory::class)
            ->set('datePreset', 'all')
            ->set('statusFilter', 'accounted')
            ->call('runAudit')
            ->assertHasNoErrors()
            ->assertSee($order->display_folio);

        $summary = $component->get('summary');
        $analytics = $component->get('analytics');

        $this->assertSame(1, $summary['orders']);
        $this->assertSame(1, $summary['paid_orders']);
        $this->assertSame(180.0, $summary['sales']);
        $this->assertSame(['Hamburguesa clásica'], $analytics['products']['labels']);
        $this->assertSame([2], $analytics['products']['units']);

        $component
            ->set('priceRange', '1000_plus')
            ->call('runAudit')
            ->assertHasNoErrors();
        $this->assertSame(0, $component->get('summary')['orders']);

        $component
            ->set('priceRange', 'under_200')
            ->set('typeFilter', 'ventanilla')
            ->set('sortBy', 'duration_desc')
            ->call('runAudit')
            ->assertHasNoErrors();
        $this->assertSame(1, $component->get('summary')['orders']);

        $component
            ->set('typeFilter', 'delivery')
            ->call('runAudit')
            ->assertHasNoErrors();
        $this->assertSame(0, $component->get('summary')['orders']);
    }

    public function test_sales_history_route_cannot_be_blocked_by_the_open_register_policy(): void
    {
        [$owner] = $this->sale();
        SidebarMenuItem::where('route_name', 'app.historial-ventas')->update(['requires_open_register' => true]);
        CashRegister::query()->update(['is_open' => false, 'closed_at' => now()]);

        $this->actingAs($owner)
            ->get(route('app.historial-ventas'))
            ->assertOk()
            ->assertSee('No requiere turno activo');
    }

    private function sale(bool $registerOpen = false): array
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $register = CashRegister::create([
            'name' => 'Caja auditoría',
            'opened_by' => $owner->id,
            'closed_by' => $registerOpen ? null : $owner->id,
            'initial_amount' => 300,
            'opened_at' => now()->subHour(),
            'closed_at' => $registerOpen ? null : now(),
            'is_open' => $registerOpen,
        ]);
        $order = Order::create([
            'cash_register_id' => $register->id,
            'customer_name' => 'Ana Cliente',
            'customer_phone' => '5512345678',
            'served_by' => $owner->id,
            'type' => 'ventanilla',
            'status' => 'pagada',
            'subtotal' => 200,
            'total' => 180,
            'paid_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Hamburguesa clásica',
            'product_price' => 100,
            'quantity' => 2,
            'subtotal' => 200,
            'discount_amount' => 20,
        ]);

        return [$owner, $order];
    }
}
