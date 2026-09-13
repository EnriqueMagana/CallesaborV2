<?php

namespace Tests\Feature;

use App\Livewire\Orders\OrderList;
use App\Livewire\Orders\SalesHistory;
use App\Livewire\Orders\SalesHistoryDetail;
use App\Models\CashRegister;
use App\Models\Order;
use App\Models\OrderChangeRequest;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\OrderRefund;
use App\Models\SidebarMenuItem;
use App\Models\User;
use Carbon\Carbon;
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
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

    public function test_owner_can_open_the_complete_historical_record_without_an_open_register(): void
    {
        [$owner, $order] = $this->sale();
        SidebarMenuItem::where('route_name', 'app.ordenes')->update(['requires_open_register' => true]);

        $item = $order->items()->firstOrFail();
        $item->update([
            'is_cancelled' => true,
            'cancelled_by' => $owner->id,
            'cancelled_at' => now(),
        ]);
        OrderPayment::create([
            'order_id' => $order->id,
            'method' => 'efectivo',
            'amount' => 200,
            'received_amount' => 200,
            'change_amount' => 0,
        ]);
        $request = OrderChangeRequest::create([
            'order_id' => $order->id,
            'requested_by' => $owner->id,
            'type' => OrderChangeRequest::TYPE_MODIFICATION,
            'status' => OrderChangeRequest::STATUS_APPROVED,
            'reason' => 'El cliente devolvió una partida',
            'original_snapshot' => ['total' => 200],
            'proposed_changes' => ['request_context' => ['scope' => 'partial']],
            'original_total' => 200,
            'proposed_total' => 180,
            'reviewed_by' => $owner->id,
            'reviewed_at' => now(),
            'applied_at' => now(),
        ]);
        OrderRefund::create([
            'order_id' => $order->id,
            'order_change_request_id' => $request->id,
            'cash_register_id' => $order->cash_register_id,
            'processed_by' => $owner->id,
            'type' => 'partial',
            'amount' => 20,
            'allocations' => ['efectivo' => 20],
            'inventory_disposition' => 'restock',
            'status' => 'recorded',
            'reason' => 'El cliente devolvió una partida',
            'processed_at' => now(),
        ]);

        $this->actingAs($owner)
            ->get(route('app.historial-ventas.show', $order))
            ->assertOk()
            ->assertSee('Expediente completo de la orden')
            ->assertSee('Hamburguesa clásica')
            ->assertSee('Cancelada')
            ->assertSee('Reembolso parcial')
            ->assertSee('El cliente devolvió una partida')
            ->assertSee((string) config('app.business_timezone'));
    }

    public function test_historical_record_can_preview_an_auditable_ticket_with_closed_register(): void
    {
        [$owner, $order] = $this->sale();
        $owner->givePermissionTo('reimprimir tickets');
        OrderPayment::create(['order_id' => $order->id, 'method' => 'efectivo', 'amount' => 180]);
        $order->items()->firstOrFail()->update([
            'is_cancelled' => true,
            'cancelled_by' => $owner->id,
            'cancelled_at' => now(),
        ]);

        Livewire::actingAs($owner)
            ->test(SalesHistoryDetail::class, ['order' => $order])
            ->call('previewTicket')
            ->assertDispatched('sales-history-ticket-show', fn ($event, $params) => str_contains($params['html'] ?? '', 'ticket-item--cancelled')
                && str_contains($params['html'] ?? '', 'RETIRADO'));
    }

    public function test_today_filter_uses_the_super_admin_business_timezone(): void
    {
        config()->set('app.timezone', 'UTC');
        config()->set('app.business_timezone', 'America/Mexico_City');
        Carbon::setTestNow(Carbon::parse('2026-09-13 05:45:00', 'UTC'));

        [$owner, $order] = $this->sale();
        $order->forceFill(['created_at' => Carbon::parse('2026-09-13 05:30:00', 'UTC')])->saveQuietly();

        $component = Livewire::actingAs($owner)
            ->test(SalesHistory::class)
            ->set('datePreset', 'today')
            ->call('runAudit')
            ->assertHasNoErrors()
            ->assertSee($order->display_folio);

        $this->assertSame('2026-09-12', $component->get('dateFrom'));
        $this->assertSame(1, $component->get('summary')['orders']);
    }

    public function test_operational_order_date_filters_use_the_super_admin_business_timezone(): void
    {
        config()->set('app.timezone', 'UTC');
        config()->set('app.business_timezone', 'America/Mexico_City');
        Carbon::setTestNow(Carbon::parse('2026-09-13 05:45:00', 'UTC'));

        [$owner, $order] = $this->sale(registerOpen: true);
        $order->forceFill(['created_at' => Carbon::parse('2026-09-13 05:30:00', 'UTC')])->saveQuietly();

        Livewire::actingAs($owner)
            ->test(OrderList::class)
            ->set('dateFrom', '2026-09-12')
            ->set('dateTo', '2026-09-12')
            ->assertSee($order->display_folio)
            ->set('dateFrom', '2026-09-13')
            ->set('dateTo', '2026-09-13')
            ->assertDontSee($order->display_folio);

        $this->actingAs($owner)
            ->get(route('app.ordenes.show', $order))
            ->assertOk()
            ->assertSee('12/09/2026')
            ->assertSee('11:30:00 PM');
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
