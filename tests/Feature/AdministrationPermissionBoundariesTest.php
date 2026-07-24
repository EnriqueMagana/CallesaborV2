<?php

namespace Tests\Feature;

use App\Livewire\Admin\RolePermissionManager;
use App\Livewire\Menu\MenuBuilder;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdministrationPermissionBoundariesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_menu_viewer_can_open_catalog_but_cannot_mutate_it(): void
    {
        $viewer = $this->employee(['ver menu']);

        $this->actingAs($viewer)
            ->get(route('app.constructor-menu'))
            ->assertOk()
            ->assertDontSee('Nuevo producto')
            ->assertDontSee('Nueva categor');

        Livewire::actingAs($viewer)->test(MenuBuilder::class)
            ->call('openProductModal')
            ->assertForbidden();

        Livewire::actingAs($viewer)->test(MenuBuilder::class)
            ->call('openCategoryModal')
            ->assertForbidden();

        Livewire::actingAs($viewer)->test(MenuBuilder::class)
            ->call('openIngredientModal')
            ->assertForbidden();

        Livewire::actingAs($viewer)->test(MenuBuilder::class)
            ->call('openAreaModal')
            ->assertForbidden();
    }

    public function test_product_creator_can_create_but_cannot_edit_or_delete_products(): void
    {
        $creator = $this->employee(['ver menu', 'crear platos']);
        $existing = Product::create([
            'name' => 'Producto existente',
            'price' => 50,
            'is_active' => true,
        ]);

        Livewire::actingAs($creator)->test(MenuBuilder::class)
            ->call('openProductModal')
            ->set('pName', 'Producto nuevo')
            ->set('pPrice', '85.00')
            ->call('saveProduct')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('products', ['name' => 'Producto nuevo']);

        Livewire::actingAs($creator)->test(MenuBuilder::class)
            ->call('openProductModal', $existing->id)
            ->assertForbidden();

        Livewire::actingAs($creator)->test(MenuBuilder::class)
            ->call('confirmDeleteProduct', $existing->id)
            ->assertForbidden();
    }

    public function test_menu_specialists_are_restricted_to_their_assigned_catalog_area(): void
    {
        $complements = $this->employee(['ver menu', 'gestionar complementos']);

        Livewire::actingAs($complements)->test(MenuBuilder::class)
            ->call('openIngredientModal')
            ->set('ingName', 'Cebolla morada')
            ->set('ingExtraPrice', '0')
            ->call('saveIngredient')
            ->assertHasNoErrors()
            ->call('openAreaModal')
            ->assertForbidden();

        $areas = $this->employee(['ver menu', 'gestionar areas impresion']);

        Livewire::actingAs($areas)->test(MenuBuilder::class)
            ->call('openAreaModal')
            ->set('areaName', 'Parrilla')
            ->set('areaColor', '#ff5500')
            ->call('saveArea')
            ->assertHasNoErrors()
            ->call('openIngredientModal')
            ->assertForbidden();

        $this->assertDatabaseHas('ingredients', ['name' => 'Cebolla morada']);
        $this->assertDatabaseHas('print_areas', ['name' => 'Parrilla']);
    }

    public function test_role_and_permission_management_are_independent_permissions(): void
    {
        $roleManager = $this->employee(['gestionar roles']);

        Livewire::actingAs($roleManager)->test(RolePermissionManager::class)
            ->assertSet('activeTab', 'roles')
            ->call('openCreateRole')
            ->set('roleName', 'supervisor-prueba')
            ->call('saveRole')
            ->assertHasNoErrors()
            ->call('openCreatePerm')
            ->assertForbidden();

        $permissionManager = $this->employee(['gestionar permisos']);

        Livewire::actingAs($permissionManager)->test(RolePermissionManager::class)
            ->assertSet('activeTab', 'permissions')
            ->call('openCreatePerm')
            ->set('permName', 'permiso temporal de prueba')
            ->set('permGroup', 'configuracion')
            ->call('savePerm')
            ->assertHasNoErrors()
            ->call('openCreateRole')
            ->assertForbidden();

        $this->assertDatabaseHas('roles', ['name' => 'supervisor-prueba']);
        $this->assertDatabaseHas('permissions', ['name' => 'permiso temporal de prueba']);
    }

    private function employee(array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }
}
