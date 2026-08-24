<?php

namespace Tests\Feature;

use App\Livewire\Admin\SidebarMenuManager;
use App\Models\CashRegister;
use App\Models\SidebarMenuItem;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SidebarMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SidebarMenuManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, SidebarMenuSeeder::class]);
    }

    public function test_admin_receives_read_access_but_not_sidebar_crud_by_default(): void
    {
        $admin = Role::findByName('admin');
        $this->assertTrue($admin->hasPermissionTo('ver menu sidebar'));
        $this->assertFalse($admin->hasPermissionTo('crear menu sidebar'));
        $this->assertFalse($admin->hasPermissionTo('editar menu sidebar'));
        $this->assertFalse($admin->hasPermissionTo('eliminar menu sidebar'));
        $this->assertFalse($admin->hasPermissionTo('gestionar bloqueos por caja'));
    }

    public function test_read_only_user_enters_menu_subsection_without_accessing_business_data(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('ver menu sidebar');

        $this->actingAs($user)->get(route('app.configuracion-negocio.menu'))
            ->assertOk()
            ->assertSee('Elementos del menú lateral')
            ->assertDontSee('Razón social');
    }

    public function test_owner_can_create_group_move_link_and_delete_it(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $this->actingAs($owner);

        $section = SidebarMenuItem::where('label', 'Restaurante')->firstOrFail();
        Livewire::test(SidebarMenuManager::class)
            ->call('createItem', 'group', $section->id)
            ->set('label', 'Reportes')
            ->set('icon', 'bx-chart')
            ->call('saveItem')
            ->assertHasNoErrors();

        $group = SidebarMenuItem::where('label', 'Reportes')->firstOrFail();
        Livewire::test(SidebarMenuManager::class)
            ->call('createItem', 'link', $group->id)
            ->set('label', 'Dashboard de ventas')
            ->set('routeName', 'app.caja.corte')
            ->set('permission', 'ver reportes')
            ->call('saveItem')
            ->assertHasNoErrors();

        $link = SidebarMenuItem::where('label', 'Dashboard de ventas')->firstOrFail();
        $this->assertSame($group->id, $link->parent_id);
        $this->assertSame('bx-chart', $group->icon);

        Livewire::test(SidebarMenuManager::class)
            ->call('handleConfirmation', 'delete-sidebar-item', ['id' => $group->id]);

        $this->assertDatabaseMissing('sidebar_menu_items', ['id' => $group->id]);
        $this->assertDatabaseMissing('sidebar_menu_items', ['id' => $link->id]);
    }

    public function test_sidebar_tree_hides_links_when_user_lacks_the_required_permission(): void
    {
        $user = User::factory()->create();
        $tree = SidebarMenuItem::visibleTreeFor($user);
        $labels = $tree->flatMap(function ($root) {
            return collect([$root->label])
                ->concat($root->children->pluck('label'))
                ->concat($root->children->flatMap(fn ($child) => $child->children->pluck('label')));
        });

        $this->assertTrue($labels->contains('Mi perfil'));
        $this->assertFalse($labels->contains('Usuarios'));
        $this->assertFalse($labels->contains('Punto de venta'));
    }

    public function test_sidebar_seed_registers_the_digital_menu_route(): void
    {
        $this->assertTrue(app('router')->has('app.menu-digital'));
        $this->assertDatabaseHas('sidebar_menu_items', [
            'system_key' => 'restaurant.digital-menu',
            'route_name' => 'app.menu-digital',
            'permission' => 'gestionar menu digital',
        ]);
    }

    public function test_menu_builder_uses_the_bootstrap_paginator(): void
    {
        $view = file_get_contents(resource_path('views/livewire/menu/menu-builder.blade.php'));

        $this->assertStringContainsString("links('pagination::bootstrap-5')", $view);
        $this->assertStringNotContainsString('products->links()', $view);
    }

    public function test_sidebar_parent_modules_use_accessible_livewire_safe_buttons(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $this->actingAs($owner)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('sidebar-parent-toggle', false)
            ->assertSee('sidebar-parent-chevron', false)
            ->assertSee('aria-expanded=', false)
            ->assertDontSee('href="javascript:void(0);" class="menu-link menu-toggle"', false);

        $script = file_get_contents(public_path('assets/js/main.js'));
        $this->assertStringContainsString('_sidebarParentToggleBound', $script);
        $this->assertStringContainsString("closest('#layout-menu .sidebar-parent-toggle')", $script);
        $this->assertStringContainsString('_clearNavigationUiLocks', $script);
        $this->assertStringContainsString("document.addEventListener('livewire:navigating'", $script);
        $this->assertStringContainsString("classList.remove('overflow-y-hidden', 'modal-open')", $script);
    }

    public function test_owner_can_require_an_open_register_for_an_internal_module(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $item = SidebarMenuItem::where('route_name', 'app.pos')->firstOrFail();

        Livewire::actingAs($owner)
            ->test(SidebarMenuManager::class)
            ->call('editItem', $item->id)
            ->set('requiresOpenRegister', true)
            ->call('saveItem')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sidebar_menu_items', [
            'id' => $item->id,
            'requires_open_register' => true,
        ]);
    }

    public function test_pos_sidebar_link_uses_a_full_load_because_it_has_a_standalone_layout(): void
    {
        $item = SidebarMenuItem::where('route_name', 'app.pos')->firstOrFail();
        $item->setAttribute('register_locked', false);
        $item->setRelation('children', collect());

        $html = view('components.sidebar.menu-node', ['item' => $item])->render();

        $this->assertStringContainsString('href="'.route('app.pos').'"', $html);
        $this->assertStringNotContainsString('wire:navigate', $html);
    }

    public function test_configured_module_is_blocked_by_url_until_a_register_is_open(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        SidebarMenuItem::where('route_name', 'app.dashboard')->update(['requires_open_register' => true]);

        $this->actingAs($owner)
            ->get(route('app.dashboard'))
            ->assertRedirect(route('app.caja'))
            ->assertSessionHas('cash_register_required');

        $this->actingAs($owner)
            ->get(route('app.caja'))
            ->assertOk()
            ->assertSee('sidebar-register-locked', false)
            ->assertSee('sidebar-parent-chevron sidebar-register-lock-icon', false)
            ->assertSee('disabled aria-label=', false);

        CashRegister::create([
            'name' => 'Caja principal',
            'opened_by' => $owner->id,
            'initial_amount' => 500,
            'opened_at' => now(),
            'is_open' => true,
        ]);

        $this->actingAs($owner)
            ->get(route('app.dashboard'))
            ->assertOk();
    }

    public function test_cash_and_configuration_routes_cannot_be_marked_as_dependent_on_cash(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $item = SidebarMenuItem::where('route_name', 'app.caja')->firstOrFail();

        Livewire::actingAs($owner)
            ->test(SidebarMenuManager::class)
            ->call('editItem', $item->id)
            ->set('requiresOpenRegister', true)
            ->call('saveItem')
            ->assertHasErrors(['requiresOpenRegister']);

        $this->assertDatabaseHas('sidebar_menu_items', [
            'id' => $item->id,
            'requires_open_register' => false,
        ]);
    }

    public function test_owner_can_save_multiple_register_policies_in_one_action(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $component = Livewire::actingAs($owner)
            ->test(SidebarMenuManager::class)
            ->call('openRegisterPolicy');

        $policies = collect($component->get('registerPolicies'))
            ->map(function (array $policy): array {
                $policy['requires'] = in_array($policy['route'], ['app.pos', 'app.mesas'], true);

                return $policy;
            })->values()->all();

        $component
            ->set('registerPolicies', $policies)
            ->call('saveRegisterPolicies')
            ->assertHasNoErrors()
            ->assertSet('showRegisterPolicy', false);

        $this->assertDatabaseHas('sidebar_menu_items', ['route_name' => 'app.pos', 'requires_open_register' => true]);
        $this->assertDatabaseHas('sidebar_menu_items', ['route_name' => 'app.mesas', 'requires_open_register' => true]);
        $this->assertDatabaseHas('sidebar_menu_items', ['route_name' => 'app.ordenes', 'requires_open_register' => false]);
        $this->assertDatabaseHas('sidebar_menu_items', ['route_name' => 'app.dashboard', 'requires_open_register' => false]);
    }

    public function test_reseeding_does_not_duplicate_or_restore_a_module_that_owner_moved(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $restaurant = SidebarMenuItem::where('system_key', 'section.restaurant')->firstOrFail();
        $tables = SidebarMenuItem::where('route_name', 'app.mesas')->firstOrFail();

        Livewire::actingAs($owner)
            ->test(SidebarMenuManager::class)
            ->call('editItem', $tables->id)
            ->set('parentId', $restaurant->id)
            ->call('saveItem')
            ->assertHasNoErrors();

        $this->seed(SidebarMenuSeeder::class);

        $this->assertSame(1, SidebarMenuItem::where('route_name', 'app.mesas')->count());
        $this->assertDatabaseHas('sidebar_menu_items', [
            'route_name' => 'app.mesas',
            'system_key' => 'operations.tables',
            'parent_id' => $restaurant->id,
        ]);
    }

    public function test_parent_items_can_move_even_when_siblings_share_the_same_position(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $restaurant = SidebarMenuItem::where('system_key', 'section.restaurant')->firstOrFail();
        $pos = SidebarMenuItem::where('system_key', 'restaurant.pos')->firstOrFail();
        $operations = SidebarMenuItem::where('system_key', 'group.operations')->firstOrFail();
        $pos->update(['sort_order' => 30]);
        $operations->update(['sort_order' => 30]);

        Livewire::actingAs($owner)
            ->test(SidebarMenuManager::class)
            ->call('moveItem', $operations->id, -1)
            ->assertHasNoErrors();

        $orderedIds = SidebarMenuItem::where('parent_id', $restaurant->id)
            ->orderBy('sort_order')->orderBy('id')->pluck('id')->all();
        $this->assertLessThan(array_search($pos->id, $orderedIds, true), array_search($operations->id, $orderedIds, true));
        $this->assertSame([10, 20, 30, 40, 50], SidebarMenuItem::where('parent_id', $restaurant->id)->orderBy('sort_order')->pluck('sort_order')->all());
    }

    public function test_same_internal_route_cannot_be_registered_twice(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        Livewire::actingAs($owner)
            ->test(SidebarMenuManager::class)
            ->call('createItem', 'link')
            ->set('label', 'Mesas duplicadas')
            ->set('routeName', 'app.mesas')
            ->call('saveItem')
            ->assertHasErrors(['routeName']);

        $this->assertSame(1, SidebarMenuItem::where('route_name', 'app.mesas')->count());
    }
}
