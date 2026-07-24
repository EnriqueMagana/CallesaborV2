<?php

namespace Tests\Feature;

use App\Models\SidebarMenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SidebarModuleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_sidebar_module_cannot_be_opened_directly_by_url(): void
    {
        $user = User::factory()->create();
        SidebarMenuItem::create([
            'label' => 'Dashboard',
            'type' => 'link',
            'route_name' => 'app.dashboard',
            'is_active' => false,
            'sort_order' => 10,
        ]);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertForbidden();
    }

    public function test_inactive_parent_also_blocks_a_visible_child_route(): void
    {
        $user = User::factory()->create();
        $section = SidebarMenuItem::create([
            'label' => 'Operación',
            'type' => 'section',
            'is_active' => false,
            'sort_order' => 10,
        ]);
        SidebarMenuItem::create([
            'parent_id' => $section->id,
            'label' => 'Dashboard',
            'type' => 'link',
            'route_name' => 'app.dashboard',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertForbidden();
    }

    public function test_sidebar_permission_is_enforced_even_when_route_has_no_can_middleware(): void
    {
        $user = User::factory()->create();
        Permission::findOrCreate('crear ordenes', 'web');
        SidebarMenuItem::create([
            'label' => 'Punto de venta',
            'type' => 'link',
            'route_name' => 'app.pos',
            'permission' => 'crear ordenes',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $this->actingAs($user)
            ->get(route('app.pos'))
            ->assertForbidden();
    }

    public function test_unregistered_app_route_is_denied_once_sidebar_configuration_exists(): void
    {
        $user = User::factory()->create();
        Permission::findOrCreate('crear ordenes', 'web');
        $user->givePermissionTo('crear ordenes');
        SidebarMenuItem::create([
            'label' => 'Dashboard',
            'type' => 'link',
            'route_name' => 'app.dashboard',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $this->actingAs($user)
            ->get(route('app.pos'))
            ->assertForbidden();
    }

    public function test_active_dashboard_module_remains_available(): void
    {
        $user = User::factory()->create();
        SidebarMenuItem::create([
            'label' => 'Dashboard',
            'type' => 'link',
            'route_name' => 'app.dashboard',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('Todos a descansar');
    }
}
