<?php

namespace Tests\Feature;

use App\Livewire\Admin\RolePermissionManager;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RoleIconTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_manager_persists_a_selected_icon_for_a_dynamic_role(): void
    {
        $user = User::factory()->create();
        $permission = Permission::findOrCreate('gestionar roles');
        $user->givePermissionTo($permission);

        $this->actingAs($user);
        Livewire::test(RolePermissionManager::class)
            ->call('openCreateRole')
            ->set('roleName', 'capitan-de-turno')
            ->set('roleIcon', 'bx-briefcase')
            ->call('saveRole')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('roles', [
            'name' => 'capitan-de-turno',
            'icon' => 'bx-briefcase',
        ]);
    }

    public function test_role_manager_rejects_an_icon_outside_the_supported_catalog(): void
    {
        $user = User::factory()->create();
        $permission = Permission::findOrCreate('gestionar roles');
        $user->givePermissionTo($permission);

        $this->actingAs($user);
        Livewire::test(RolePermissionManager::class)
            ->call('openCreateRole')
            ->set('roleName', 'rol-invalido')
            ->set('roleIcon', 'bx-user onmouseover=alert(1)')
            ->call('saveRole')
            ->assertHasErrors(['roleIcon']);

        $this->assertDatabaseMissing('roles', ['name' => 'rol-invalido']);
    }
}
