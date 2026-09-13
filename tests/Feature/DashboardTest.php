<?php

namespace Tests\Feature;

use App\Models\CashRegister;
use App\Models\KioskTerminal;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\DashboardDataBuilder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_receives_the_business_dashboard(): void
    {
        $user = $this->userWithRole('owner');
        $this->openRegister($user);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('Visión general del negocio')
            ->assertSee('Ventas cobradas')
            ->assertSee('data-dashboard-state="active"', false)
            ->assertSee('assets/js/dashboard.js', false)
            ->assertSee($user->name);
    }

    public function test_waiter_receives_the_table_dashboard_without_financial_totals(): void
    {
        $user = $this->userWithRole('mesero');
        $this->openRegister($user);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('Tu turno, organizado')
            ->assertSee('Mis mesas activas')
            ->assertDontSee('Ventas cobradas')
            ->assertDontSee('Kioscos activos');
    }

    public function test_a_custom_delivery_role_receives_the_delivery_dashboard(): void
    {
        $user = $this->userWithRole('repartidor nocturno');
        $this->openRegister($user);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('Entregas del turno')
            ->assertSee('Listos para salir')
            ->assertSee('Contra entrega');
    }

    public function test_cashier_role_takes_priority_over_waiter_role_on_the_dashboard(): void
    {
        $user = $this->userWithRole('mesero');
        $cashierPermission = Permission::create(['name' => 'ver caja', 'guard_name' => 'web']);
        $cashierRole = Role::create(['name' => 'turno nocturno', 'guard_name' => 'web']);
        $cashierRole->givePermissionTo($cashierPermission);
        $user->assignRole($cashierRole);
        $this->openRegister($user);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('Punto de venta bajo control')
            ->assertSee('bx bx-money', false)
            ->assertSee('app-role-summary__label">Turno Nocturno', false)
            ->assertDontSee('Tu turno, organizado');
    }

    public function test_role_summary_collapses_three_roles_to_primary_role_and_counter(): void
    {
        $user = $this->userWithRole('mesero');
        Role::create(['name' => 'cajero', 'guard_name' => 'web']);
        Role::create(['name' => 'apoyo temporal', 'guard_name' => 'web']);
        $user->assignRole(['cajero', 'apoyo temporal']);
        $this->openRegister($user);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('app-role-summary__label">Cajero', false)
            ->assertSee('app-role-summary__more" aria-hidden="true">+2', false)
            ->assertSee('Roles asignados: Cajero, Mesero, Apoyo Temporal', false);
    }

    public function test_owner_can_open_each_active_kiosk_from_the_dashboard(): void
    {
        $owner = $this->userWithRole('owner');
        $this->openRegister($owner);
        $active = KioskTerminal::create([
            'name' => 'Kiosco terraza',
            'token_hash' => hash('sha256', str_repeat('a', 64)),
            'user_id' => $owner->id,
            'is_active' => true,
        ]);
        KioskTerminal::create([
            'name' => 'Kiosco pausado',
            'token_hash' => hash('sha256', str_repeat('b', 64)),
            'user_id' => $owner->id,
            'is_active' => false,
        ]);

        $this->actingAs($owner)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('Kioscos activos')
            ->assertSee('Kiosco terraza')
            ->assertSee(route('app.kioscos.open', $active), false)
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false)
            ->assertDontSee('Kiosco pausado');
    }

    public function test_dashboard_only_renders_the_rest_state_when_there_is_no_open_register(): void
    {
        $owner = $this->userWithRole('owner');

        $this->actingAs($owner)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('Excelente trabajo por hoy')
            ->assertSee('data-dashboard-state="resting"', false)
            ->assertDontSee('Indicadores principales')
            ->assertDontSee('Actividad del periodo')
            ->assertDontSee('data-dashboard-data', false);
    }

    public function test_dashboard_chart_lifecycle_is_registered_for_livewire_navigation(): void
    {
        $script = file_get_contents(public_path('assets/js/dashboard.js'));

        $this->assertStringContainsString("document.addEventListener('livewire:navigating'", $script);
        $this->assertStringContainsString("document.addEventListener('livewire:navigated'", $script);
        $this->assertStringContainsString("Livewire.hook('morph.updated'", $script);
        $this->assertStringContainsString('requestAnimationFrame', $script);
    }

    public function test_report_viewer_sees_daily_waiter_and_product_rankings_without_financial_values(): void
    {
        $viewer = User::factory()->create();
        Permission::findOrCreate('ver reportes', 'web');
        $viewer->givePermissionTo('ver reportes');

        $leader = User::factory()->create(['name' => 'Mesera Destacada']);
        $second = User::factory()->create(['name' => 'Mesero Segundo']);
        $register = $this->openRegister($viewer);

        $firstOrder = $this->order($register, $leader, 180);
        $this->order($register, $leader, 120);
        $this->order($register, $second, 90);
        OrderItem::create([
            'order_id' => $firstOrder->id,
            'product_name' => 'Hamburguesa especial',
            'product_price' => 90,
            'quantity' => 2,
            'subtotal' => 180,
        ]);

        $this->actingAs($viewer)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('Meseros con más pedidos')
            ->assertSee('Mesera Destacada')
            ->assertSee('Hamburguesa especial')
            ->assertDontSee('Ticket promedio')
            ->assertDontSee('$300.00');
    }

    public function test_dashboard_metrics_only_include_the_current_open_register(): void
    {
        $owner = $this->userWithRole('owner');
        $closedRegister = CashRegister::create([
            'name' => 'Caja anterior',
            'opened_by' => $owner->id,
            'initial_amount' => 0,
            'opened_at' => now()->subHours(4),
            'closed_at' => now()->subHours(2),
            'is_open' => false,
        ]);
        $oldOrder = $this->order($closedRegister, $owner, 999);
        OrderItem::create([
            'order_id' => $oldOrder->id,
            'product_name' => 'Producto de caja anterior',
            'product_price' => 999,
            'quantity' => 1,
            'subtotal' => 999,
        ]);

        $openRegister = $this->openRegister($owner);
        $currentOrder = $this->order($openRegister, $owner, 80);
        OrderItem::create([
            'order_id' => $currentOrder->id,
            'product_name' => 'Producto actual',
            'product_price' => 80,
            'quantity' => 1,
            'subtotal' => 80,
        ]);

        $this->actingAs($owner);
        $dashboard = app(DashboardDataBuilder::class)->build($owner, 'today');

        $this->assertSame('$80.00', $dashboard['kpis'][0]['value']);
        $this->assertSame([0, 0, 0, 1], $dashboard['chart_data']['status']['values']);
        $this->assertSame('Producto actual', $dashboard['team_performance']['top_products']->first()['name']);
        $this->assertFalse($dashboard['team_performance']['top_products']->contains('name', 'Producto de caja anterior'));
    }

    public function test_dashboard_trend_includes_orders_from_previous_registers_and_business_days(): void
    {
        config(['app.timezone' => 'UTC', 'app.business_timezone' => 'America/Merida']);
        Carbon::setTestNow(Carbon::parse('2026-09-13 02:00:00', 'UTC'));

        try {
            $owner = $this->userWithRole('owner');
            $closedRegister = CashRegister::create([
                'name' => 'Caja del día anterior',
                'opened_by' => $owner->id,
                'initial_amount' => 0,
                'opened_at' => Carbon::parse('2026-09-12 00:00:00', 'UTC'),
                'closed_at' => Carbon::parse('2026-09-12 05:00:00', 'UTC'),
                'is_open' => false,
            ]);
            $previousBusinessDayOrder = $this->order($closedRegister, $owner, 275);
            $previousBusinessDayOrder->forceFill([
                // 11 Sep., 7:30 p. m. in America/Merida.
                'created_at' => Carbon::parse('2026-09-12 01:30:00', 'UTC'),
            ])->saveQuietly();

            $openRegister = $this->openRegister($owner);
            $this->order($openRegister, $owner, 80);

            $dashboard = app(DashboardDataBuilder::class)->build($owner, '7');
            $values = $dashboard['chart_data']['trend']['values'];

            $this->assertSame(275.0, $values[count($values) - 2]);
            $this->assertSame(80.0, $values[count($values) - 1]);
            $this->assertSame('$80.00', $dashboard['kpis'][0]['value']);
        } finally {
            Carbon::setTestNow();
        }
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        Role::create(['name' => $role, 'guard_name' => 'web']);
        $user->assignRole($role);

        return $user;
    }

    private function openRegister(User $user): CashRegister
    {
        return CashRegister::create([
            'name' => 'Caja principal',
            'opened_by' => $user->id,
            'initial_amount' => 0,
            'opened_at' => now()->subHour(),
            'is_open' => true,
        ]);
    }

    private function order(CashRegister $register, User $seller, float $total): Order
    {
        return Order::create([
            'cash_register_id' => $register->id,
            'customer_name' => 'Cliente',
            'served_by' => $seller->id,
            'type' => 'mesa',
            'status' => 'pagada',
            'subtotal' => $total,
            'total' => $total,
            'paid_at' => now(),
        ]);
    }
}
