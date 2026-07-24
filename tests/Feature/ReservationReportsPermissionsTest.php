<?php

namespace Tests\Feature;

use App\Livewire\Orders\SalesHistory;
use App\Livewire\Reservas\CalendarioReservas;
use App\Models\CashRegister;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ReservationReportsPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_reservation_route_events_and_customer_lookup_require_view_or_mutation_permissions(): void
    {
        $outsider = $this->employee([]);

        $this->actingAs($outsider)
            ->get(route('app.reservas'))
            ->assertForbidden();
        $this->actingAs($outsider)
            ->getJson(route('app.reservas.events', [
                'start' => now()->startOfMonth()->toDateString(),
                'end' => now()->endOfMonth()->toDateString(),
            ]))
            ->assertForbidden();

        $viewer = $this->employee(['ver reservas']);
        Livewire::actingAs($viewer)
            ->test(CalendarioReservas::class)
            ->call('openNew')
            ->assertForbidden();

        Livewire::actingAs($viewer)
            ->test(CalendarioReservas::class)
            ->set('panelMode', 'new')
            ->set('customerSearch', 'cliente protegido')
            ->assertForbidden();
    }

    public function test_reservation_actions_are_isolated_by_minimum_permission(): void
    {
        $creator = $this->employee(['ver reservas', 'crear reservas']);
        Livewire::actingAs($creator)
            ->test(CalendarioReservas::class)
            ->call('openNew', '2026-08-15')
            ->set('customerName', 'Familia Rivera')
            ->set('customerPhone', '5555551212')
            ->set('guests', 5)
            ->set('reservedTime', '20:30')
            ->call('save')
            ->assertHasNoErrors();

        $reservation = Reservation::where('customer_name', 'Familia Rivera')->firstOrFail();

        Livewire::actingAs($creator)
            ->test(CalendarioReservas::class)
            ->call('openDetail', $reservation->id)
            ->call('startEdit')
            ->assertForbidden();

        $editor = $this->employee(['ver reservas', 'editar reservas']);
        Livewire::actingAs($editor)
            ->test(CalendarioReservas::class)
            ->call('openDetail', $reservation->id)
            ->call('startEdit')
            ->set('customerName', 'Familia Rivera Gómez')
            ->call('update')
            ->assertHasNoErrors();

        $this->assertSame('Familia Rivera Gómez', $reservation->fresh()->customer_name);

        $statusOperator = $this->employee(['ver reservas', 'cambiar estado reservas']);
        Livewire::actingAs($statusOperator)
            ->test(CalendarioReservas::class)
            ->call('openDetail', $reservation->id)
            ->call('changeStatus', 'no_show')
            ->assertStatus(422);

        Livewire::actingAs($statusOperator)
            ->test(CalendarioReservas::class)
            ->call('openDetail', $reservation->id)
            ->call('changeStatus', 'confirmada')
            ->assertHasNoErrors();

        $this->assertSame('confirmada', $reservation->fresh()->status);

        Livewire::actingAs($statusOperator)
            ->test(CalendarioReservas::class)
            ->call('openDetail', $reservation->id)
            ->call('cancel')
            ->assertForbidden();

        $canceller = $this->employee(['ver reservas', 'cancelar reservas']);
        Livewire::actingAs($canceller)
            ->test(CalendarioReservas::class)
            ->call('openDetail', $reservation->id)
            ->set('cancelReason', 'El cliente cambió la fecha')
            ->call('cancel')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'cancelada',
            'cancellation_reason' => 'El cliente cambió la fecha',
        ]);
    }

    public function test_sales_history_separates_report_access_from_financial_access(): void
    {
        [$order] = $this->closedSale('Cliente financiero', 987.65);

        $ordersViewer = $this->employee(['ver ordenes']);
        $this->actingAs($ordersViewer)
            ->get(route('app.historial-ventas'))
            ->assertForbidden();

        $reportViewer = $this->employee(['ver reportes']);
        $this->actingAs($reportViewer)
            ->get(route('app.historial-ventas'))
            ->assertOk()
            ->assertSee('Cliente financiero')
            ->assertSee('Importes financieros')
            ->assertSee('Restringidos')
            ->assertDontSee('$987.65')
            ->assertDontSee('Efectivo $987.65');

        $financialViewer = $this->employee([
            'ver reportes',
            'ver reportes financieros',
        ]);
        $this->actingAs($financialViewer)
            ->get(route('app.historial-ventas'))
            ->assertOk()
            ->assertSee('$987.65');
        Livewire::actingAs($financialViewer)
            ->test(SalesHistory::class)
            ->call('toggleOrder', $order->id)
            ->assertSee('Efectivo $987.65');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'total' => 987.65]);
    }

    public function test_dashboard_does_not_expose_orders_or_money_without_permissions(): void
    {
        [$order] = $this->closedSale('Pedido confidencial', 741.25);
        CashRegister::create([
            'name' => 'Caja abierta',
            'opened_by' => $order->served_by,
            'initial_amount' => 0,
            'opened_at' => now(),
            'is_open' => true,
        ]);

        $restricted = $this->employee([]);
        $this->actingAs($restricted)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('Acceso limitado')
            ->assertSee('Pedidos visibles')
            ->assertDontSee('Pedido confidencial')
            ->assertDontSee('$741.25')
            ->assertDontSee('Ver todos');

        $reportViewer = $this->employee(['ver reportes']);
        $this->actingAs($reportViewer)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('Órdenes cobradas')
            ->assertDontSee('Pedido confidencial')
            ->assertDontSee('$741.25');

        $financialViewer = $this->employee([
            'ver reportes',
            'ver reportes financieros',
        ]);
        $this->actingAs($financialViewer)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('Ventas cobradas')
            ->assertSee('$741.25')
            ->assertDontSee('Pedido confidencial');
    }

    private function employee(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function closedSale(string $customer, float $total): array
    {
        $seller = User::factory()->create();
        $register = CashRegister::create([
            'name' => 'Caja cerrada',
            'opened_by' => $seller->id,
            'closed_by' => $seller->id,
            'initial_amount' => 0,
            'final_amount' => $total,
            'opened_at' => now()->subHours(2),
            'closed_at' => now()->subHour(),
            'is_open' => false,
        ]);
        $order = Order::create([
            'cash_register_id' => $register->id,
            'customer_name' => $customer,
            'served_by' => $seller->id,
            'type' => 'ventanilla',
            'status' => 'pagada',
            'subtotal' => $total,
            'total' => $total,
            'paid_at' => now()->subHour(),
        ]);
        $payment = OrderPayment::create([
            'order_id' => $order->id,
            'method' => 'efectivo',
            'amount' => $total,
            'received_amount' => $total,
            'change_amount' => 0,
        ]);

        return [$order, $payment];
    }
}
