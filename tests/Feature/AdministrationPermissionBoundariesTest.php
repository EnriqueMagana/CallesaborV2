<?php

namespace Tests\Feature;

use App\Livewire\Admin\RolePermissionManager;
use App\Livewire\Menu\MenuBuilder;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_product_creator_can_upload_a_large_supported_image_with_preview_feedback(): void
    {
        Storage::fake('public');
        $creator = $this->employee(['ver menu', 'crear platos']);
        $image = UploadedFile::fake()->image('producto.jpg', 1600, 1200)->size(3072);

        Livewire::actingAs($creator)->test(MenuBuilder::class)
            ->call('openProductModal')
            ->set('pName', 'Producto con imagen')
            ->set('pPrice', '120.00')
            ->set('pImage', $image)
            ->assertSee('Imagen lista para guardar')
            ->call('saveProduct')
            ->assertHasNoErrors();

        $product = Product::where('name', 'Producto con imagen')->firstOrFail();
        $this->assertStringEndsWith('.webp', $product->image);
        Storage::disk('public')->assertExists($product->image);
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

    public function test_roles_page_exposes_accessible_modals_and_dark_theme_styles(): void
    {
        $manager = $this->employee(['gestionar roles', 'gestionar permisos']);

        $this->actingAs($manager)
            ->get(route('app.roles-permisos'))
            ->assertOk()
            ->assertSee('assets/css/role-permissions.css', false)
            ->assertSee('roles-tabs', false)
            ->assertSee('roles-role-card', false);

        Livewire::actingAs($manager)->test(RolePermissionManager::class)
            ->call('openCreateRole')
            ->assertSee('roles-modal-layer', false)
            ->assertSee('roles-modal-close', false)
            ->assertSee('aria-modal="true"', false)
            ->assertSee('wire:click="closeRoleForm"', false)
            ->assertSee('id="role-name"', false)
            ->call('closeRoleForm')
            ->assertSet('showRoleForm', false)
            ->call('openCreatePerm')
            ->assertSee('wire:click="closePermForm"', false)
            ->assertSee('id="permission-name"', false)
            ->call('closePermForm')
            ->assertSet('showPermForm', false);

        $styles = file_get_contents(public_path('assets/css/role-permissions.css'));

        $this->assertStringContainsString('html.dark-style .roles-page', $styles);
        $this->assertStringContainsString('.roles-modal-close', $styles);
        $this->assertStringContainsString('top: -54px', $styles);
        $this->assertStringContainsString('@media (max-width: 767.98px)', $styles);
    }

    private function employee(array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }
}
