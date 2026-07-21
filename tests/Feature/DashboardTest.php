<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_receives_the_business_dashboard(): void
    {
        $user = $this->userWithRole('owner');

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('Visión general del negocio')
            ->assertSee('Ventas cobradas')
            ->assertSee($user->name);
    }

    public function test_waiter_receives_the_table_dashboard_without_financial_totals(): void
    {
        $user = $this->userWithRole('mesero');

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('Tu turno, organizado')
            ->assertSee('Mis mesas activas')
            ->assertDontSee('Ventas cobradas');
    }

    public function test_a_custom_delivery_role_receives_the_delivery_dashboard(): void
    {
        $user = $this->userWithRole('repartidor nocturno');

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('Entregas del turno')
            ->assertSee('Listos para salir')
            ->assertSee('Contra entrega');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        Role::create(['name' => $role, 'guard_name' => 'web']);
        $user->assignRole($role);

        return $user;
    }
}
