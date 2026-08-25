<?php

namespace Tests\Feature;

use App\Livewire\Mesas\GestionMesas;
use App\Livewire\Mesas\MesaOrden;
use App\Livewire\Mesas\SplitCuenta;
use App\Models\Addon;
use App\Models\AddonGroup;
use App\Models\Area;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Mesa;
use App\Models\MesaAssignment;
use App\Models\MesaSplit;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MesasPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['mesero', 'cajero', 'gerente', 'admin', 'super-admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_table_routes_require_the_permission_for_each_flow(): void
    {
        $employee = $this->employee([]);
        $area = $this->area();
        $mesa = $this->mesa($area, status: 'ocupada');
        $this->openRegister($employee);

        $this->actingAs($employee)
            ->get(route('app.mesas'))
            ->assertForbidden();

        $employee->givePermissionTo($this->permission('ver mesas'));
        $this->actingAs($employee)
            ->get(route('app.mesas'))
            ->assertOk()
            ->assertSee('mesas-initial-skeleton', false)
            ->assertSee('wire:submit="applySearch"', false)
            ->assertDontSee('wire:model.live.debounce.300ms="search"', false);

        $this->actingAs($employee)
            ->get(route('app.mesas.ordenar', $mesa))
            ->assertForbidden();

        $this->actingAs($employee)
            ->get(route('app.mesas.split', $mesa))
            ->assertForbidden();
    }

    public function test_area_and_table_crud_honor_granular_permissions(): void
    {
        $creator = $this->employee(['ver mesas', 'crear areas de mesas', 'crear mesas']);

        $component = Livewire::actingAs($creator)
            ->test(GestionMesas::class)
            ->call('openAreaModal')
            ->set('areaName', 'Patio')
            ->set('areaColor', '#123456')
            ->set('areaIcon', 'bx-sun')
            ->call('saveArea')
            ->assertHasNoErrors();

        $area = Area::where('name', 'Patio')->firstOrFail();

        $component
            ->call('openMesaModal')
            ->set('mesaNumber', '17')
            ->set('mesaName', 'Ventana')
            ->set('mesaCapacity', 6)
            ->set('mesaAreaId', $area->id)
            ->call('saveMesa')
            ->assertHasNoErrors();

        $mesa = Mesa::where('area_id', $area->id)->where('number', 17)->firstOrFail();

        Livewire::actingAs($creator)
            ->test(GestionMesas::class)
            ->call('openMesaModal', $mesa->id)
            ->assertForbidden();

        $editor = $this->employee(['ver mesas', 'editar areas de mesas', 'editar mesas']);
        Livewire::actingAs($editor)
            ->test(GestionMesas::class)
            ->call('openAreaModal', $area->id)
            ->set('areaName', 'Patio central')
            ->call('saveArea')
            ->call('openMesaModal', $mesa->id)
            ->set('mesaName', 'Ventanal')
            ->call('saveMesa')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('areas', ['id' => $area->id, 'name' => 'Patio central']);
        $this->assertDatabaseHas('mesas', ['id' => $mesa->id, 'name' => 'Ventanal']);

        $deleter = $this->employee(['ver mesas', 'eliminar areas de mesas', 'eliminar mesas']);
        Livewire::actingAs($deleter)
            ->test(GestionMesas::class)
            ->call('openDeleteMesa', $mesa->id)
            ->call('confirmDeleteMesa')
            ->call('deleteArea', $area->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('mesas', ['id' => $mesa->id]);
        $this->assertDatabaseMissing('areas', ['id' => $area->id]);
    }

    public function test_assignment_reassignment_release_close_and_status_changes_are_isolated(): void
    {
        $area = $this->area();
        $mesa = $this->mesa($area);
        $viewer = $this->employee(['ver mesas']);

        Livewire::actingAs($viewer)
            ->test(GestionMesas::class)
            ->call('openAssign', $mesa->id)
            ->assertForbidden();

        $waiter = $this->employee(['ver mesas', 'asignar mesas']);
        Livewire::actingAs($waiter)
            ->test(GestionMesas::class)
            ->call('openAssign', $mesa->id)
            ->call('confirmAssign')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('mesa_assignments', [
            'mesa_id' => $mesa->id,
            'user_id' => $waiter->id,
            'released_at' => null,
        ]);

        Livewire::actingAs($viewer)
            ->test(GestionMesas::class)
            ->call('openReassign', $mesa->id)
            ->assertForbidden();

        $newWaiter = $this->employee(['ver mesas']);
        $supervisor = $this->employee(['ver mesas', 'reasignar mesas']);
        Livewire::actingAs($supervisor)
            ->test(GestionMesas::class)
            ->call('openReassign', $mesa->id)
            ->set('reassignUserId', $newWaiter->id)
            ->call('confirmReassign')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('mesa_assignments', [
            'mesa_id' => $mesa->id,
            'user_id' => $newWaiter->id,
            'released_at' => null,
        ]);

        Livewire::actingAs($viewer)
            ->test(GestionMesas::class)
            ->call('openRelease', $mesa->id)
            ->assertForbidden();

        $releaser = $this->employee(['ver mesas', 'liberar mesas']);
        Livewire::actingAs($releaser)
            ->test(GestionMesas::class)
            ->call('openRelease', $mesa->id)
            ->call('confirmRelease')
            ->assertHasNoErrors();

        $this->assertSame('disponible', $mesa->fresh()->status);
        $this->assertNull(MesaAssignment::where('mesa_id', $mesa->id)->whereNull('released_at')->first());

        $statusOperator = $this->employee(['ver mesas', 'cambiar estado mesas']);
        Livewire::actingAs($statusOperator)
            ->test(GestionMesas::class)
            ->call('openStatusChange', $mesa->id, 'bloqueada')
            ->call('confirmStatusChange')
            ->assertHasNoErrors();

        $this->assertSame('bloqueada', $mesa->fresh()->status);

        $mesa->update(['status' => 'ocupada']);
        $this->openRegister($statusOperator);
        Livewire::actingAs($viewer)
            ->test(GestionMesas::class)
            ->call('closeMesa', $mesa->id)
            ->assertForbidden();

        $closer = $this->employee(['ver mesas', 'cerrar mesas']);
        Livewire::actingAs($closer)
            ->test(GestionMesas::class)
            ->call('closeMesa', $mesa->id);

        $this->assertSame('en_cuenta', $mesa->fresh()->status);
    }

    public function test_only_ordering_permission_can_create_a_table_order(): void
    {
        $area = $this->area();
        $operator = $this->employee(['ver mesas', 'ordenar mesas']);
        $mesa = $this->mesa($area, status: 'ocupada');
        $this->assign($mesa, $operator);
        $this->openRegister($operator);
        $product = Product::create(['name' => 'Taco de prueba', 'price' => 25, 'is_active' => true]);

        Livewire::actingAs($operator)
            ->test(MesaOrden::class, ['mesa' => $mesa])
            ->set('cart', [[
                'cart_id' => 'test-line',
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => 25,
                'unit_total' => 25,
                'qty' => 2,
                'notes' => '',
                'addons' => [],
                'ingredients' => [],
            ]])
            ->call('placeOrder')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('orders', [
            'mesa_id' => $mesa->id,
            'served_by' => $operator->id,
            'type' => 'mesa',
            'total' => 50,
        ]);

        $viewer = $this->employee(['ver mesas']);
        $this->assign($mesa, $viewer);

        Livewire::actingAs($viewer)
            ->test(MesaOrden::class, ['mesa' => $mesa])
            ->assertForbidden();
    }

    public function test_waiter_lands_on_their_assigned_tables(): void
    {
        $area = $this->area();
        $waiter = $this->employee(['ver mesas']);
        $waiter->assignRole('mesero');
        $other = $this->employee(['ver mesas']);
        $ownMesa = $this->mesa($area, status: 'ocupada');
        $otherMesa = $this->mesa($area, status: 'ocupada');
        $this->assign($ownMesa, $waiter);
        $this->assign($otherMesa, $other);

        Livewire::actingAs($waiter)
            ->test(GestionMesas::class)
            ->assertSet('tab', 'mis_mesas')
            ->assertSee('wire:click="openDetail('.$ownMesa->id.')"', false)
            ->assertDontSee('wire:click="openDetail('.$otherMesa->id.')"', false);
    }

    public function test_viewing_all_active_tables_requires_its_own_permission(): void
    {
        $area = $this->area();
        $this->mesa($area, status: 'ocupada');

        $viewer = $this->employee(['ver mesas']);
        Livewire::actingAs($viewer)
            ->test(GestionMesas::class)
            ->assertDontSee('Todas las Mesas')
            ->call('setTab', 'todas')
            ->assertForbidden();

        $supervisor = $this->employee(['ver mesas', 'ver todas las mesas']);
        Livewire::actingAs($supervisor)
            ->test(GestionMesas::class)
            ->assertSee('Todas las Mesas')
            ->call('setTab', 'todas')
            ->assertSet('tab', 'todas')
            ->assertHasNoErrors();
    }

    public function test_close_table_button_opens_a_choice_and_supports_full_or_split_checkout(): void
    {
        $area = $this->area();
        $waiter = $this->employee(['ver mesas', 'cerrar mesas', 'dividir mesas']);
        $waiter->assignRole('mesero');
        $this->openRegister($waiter);

        $fullMesa = $this->mesa($area, status: 'ocupada');
        $this->assign($fullMesa, $waiter);
        Livewire::actingAs($waiter)
            ->test(GestionMesas::class)
            ->call('openCloseMesa', $fullMesa->id)
            ->assertSet('showCloseModal', true)
            ->assertSee('Cuenta completa')
            ->assertSee('Dividir cuenta')
            ->call('confirmCloseMesa', 'full')
            ->assertSet('showCloseModal', false);

        $this->assertSame('en_cuenta', $fullMesa->fresh()->status);

        $splitMesa = $this->mesa($area, status: 'ocupada');
        $this->assign($splitMesa, $waiter);
        Livewire::actingAs($waiter)
            ->test(GestionMesas::class)
            ->call('openCloseMesa', $splitMesa->id)
            ->call('confirmCloseMesa', 'split')
            ->assertRedirect(route('app.mesas.split', $splitMesa));

        $this->assertSame('en_cuenta', $splitMesa->fresh()->status);
    }

    public function test_table_order_accepts_more_than_two_different_products(): void
    {
        $area = $this->area();
        $operator = $this->employee(['ver mesas', 'ordenar mesas']);
        $mesa = $this->mesa($area, status: 'ocupada');
        $this->assign($mesa, $operator);
        $category = Category::create(['name' => 'Pruebas', 'is_active' => true]);
        $products = collect(range(1, 4))->map(fn ($number) => Product::create([
            'category_id' => $category->id,
            'name' => "Producto {$number}",
            'price' => 20 + $number,
            'is_active' => true,
        ]));

        $component = Livewire::actingAs($operator)
            ->test(MesaOrden::class, ['mesa' => $mesa]);

        foreach ($products as $product) {
            $component->call('openCustomize', $product->id)->assertHasNoErrors();
        }

        $component
            ->assertCount('cart', 4)
            ->assertSet('cart.0.name', 'Producto 1')
            ->assertSet('cart.3.name', 'Producto 4');
    }

    public function test_pasta_then_hamburger_and_reverse_sequence_remain_interactive(): void
    {
        $area = $this->area();
        $operator = $this->employee(['ver mesas', 'ordenar mesas']);
        $mesa = $this->mesa($area, status: 'ocupada');
        $this->assign($mesa, $operator);
        $category = Category::create(['name' => 'Platos', 'is_active' => true]);
        $pasta = Product::create([
            'category_id' => $category->id,
            'name' => 'Pasta',
            'price' => 120,
            'is_customizable' => true,
            'min_ingredients' => 1,
            'max_ingredients' => 3,
            'is_active' => true,
        ]);
        $ingredient = Ingredient::create([
            'name' => 'Salsa para pasta',
            'is_active' => true,
        ]);
        $pasta->ingredients()->attach($ingredient->id);
        $hamburger = Product::create([
            'category_id' => $category->id,
            'name' => 'Hamburguesa',
            'price' => 95,
            'is_active' => true,
        ]);

        Livewire::actingAs($operator)
            ->test(MesaOrden::class, ['mesa' => $mesa])
            ->call('openCustomize', $pasta->id)
            ->call('confirmCustomize', [], [$ingredient->id => 1], 1, '')
            ->assertSet('showCustomize', false)
            ->assertSet('customizingProduct', null)
            ->assertSet('selectedIngredients', [])
            ->call('openCustomize', $hamburger->id)
            ->assertCount('cart', 2)
            ->assertSet('cart.0.name', 'Pasta')
            ->assertSet('cart.1.name', 'Hamburguesa');

        Livewire::actingAs($operator)
            ->test(MesaOrden::class, ['mesa' => $mesa])
            ->call('openCustomize', $hamburger->id)
            ->call('openCustomize', $pasta->id)
            ->assertSet('showCustomize', true)
            ->call('confirmCustomize', [], [$ingredient->id => 1], 1, '')
            ->assertCount('cart', 2)
            ->assertSet('cart.0.name', 'Hamburguesa')
            ->assertSet('cart.1.name', 'Pasta');

        $styles = file_get_contents(public_path('assets/css/mesa-orden.css'));
        $view = file_get_contents(resource_path('views/livewire/mesas/mesa-orden.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('mo-cart-header__identity', $view);
        $this->assertStringContainsString('mo-cart-stepper', $view);
        $this->assertStringContainsString('mo-order-note', $view);
        $this->assertStringContainsString('Enviar orden a cocina', $view);
        $this->assertMatchesRegularExpression(
            '/\.mo-cart-container\s*\{[^}]*z-index:\s*1090;/s',
            $styles,
            'El carrito de mesas debe mostrarse sobre el navbar fijo.',
        );
        $this->assertMatchesRegularExpression(
            '/\.mo-modal-backdrop\s*\{[^}]*z-index:\s*1120;/s',
            $styles,
            'Los formularios de mesa deben cubrir navbar, sidebar y carrito.',
        );

        $this->assertMatchesRegularExpression(
            '/\.mo-cart-container\s*\{[^}]*pointer-events:\s*none;/s',
            $styles,
            'El contenedor invisible del carrito no debe interceptar tarjetas del catálogo.',
        );
        $this->assertMatchesRegularExpression(
            '/\.mo-cart-drawer\s*\{[^}]*visibility:\s*hidden;[^}]*pointer-events:\s*none;/s',
            $styles,
            'El cajón cerrado debe quedar fuera de la interacción.',
        );
    }

    public function test_customization_limits_are_enforced_again_when_product_is_confirmed(): void
    {
        $area = $this->area();
        $operator = $this->employee(['ver mesas', 'ordenar mesas']);
        $mesa = $this->mesa($area, status: 'ocupada');
        $this->assign($mesa, $operator);
        $category = Category::create(['name' => 'Configurables', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Producto configurable',
            'price' => 70,
            'is_customizable' => true,
            'max_addons' => 2,
            'min_ingredients' => 1,
            'max_ingredients' => 2,
            'is_active' => true,
        ]);
        $group = AddonGroup::create([
            'name' => 'Complementos',
            'min_selections' => 0,
            'max_selections' => 3,
            'is_active' => true,
        ]);
        $addons = collect(range(1, 3))->map(fn ($number) => Addon::create([
            'addon_group_id' => $group->id,
            'name' => "Complemento {$number}",
            'is_active' => true,
        ]));
        $ingredients = collect(range(1, 3))->map(fn ($number) => Ingredient::create([
            'name' => "Ingrediente {$number}",
            'is_active' => true,
        ]));
        $product->addonGroups()->attach($group->id);
        $product->ingredients()->attach($ingredients->pluck('id'));

        $component = Livewire::actingAs($operator)
            ->test(MesaOrden::class, ['mesa' => $mesa])
            ->call('openCustomize', $product->id)
            ->call('confirmCustomize', $addons->pluck('id')->all(), [$ingredients[0]->id => 1], 1, '')
            ->assertHasErrors('addons_general')
            ->call(
                'confirmCustomize',
                $addons->take(2)->pluck('id')->all(),
                [$ingredients[0]->id => 2, $ingredients[1]->id => 1],
                1,
                '',
            )
            ->assertHasErrors('ingredients');

        $component
            ->call(
                'confirmCustomize',
                $addons->take(2)->pluck('id')->all(),
                [$ingredients[0]->id => 1, $ingredients[1]->id => 1],
                1,
                'Sin picante',
            )
            ->assertHasNoErrors()
            ->assertCount('cart', 1)
            ->assertSet('cart.0.notes', 'Sin picante');
    }

    public function test_customization_uses_local_controls_and_renders_available_images(): void
    {
        $area = $this->area();
        $operator = $this->employee(['ver mesas', 'ordenar mesas']);
        $mesa = $this->mesa($area, status: 'ocupada');
        $this->assign($mesa, $operator);
        $category = Category::create(['name' => 'Imágenes', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Hamburguesa visual',
            'image' => 'menu/hamburguesa.webp',
            'price' => 90,
            'is_customizable' => true,
            'is_active' => true,
        ]);
        $ingredient = Ingredient::create([
            'name' => 'Aguacate',
            'image' => 'ingredients/aguacate.webp',
            'is_active' => true,
        ]);
        $product->ingredients()->attach($ingredient->id);

        Livewire::actingAs($operator)
            ->test(MesaOrden::class, ['mesa' => $mesa])
            ->call('openCustomize', $product->id)
            ->assertSee('menu/hamburguesa.webp', false)
            ->assertSee('ingredients/aguacate.webp', false)
            ->assertSee('x-on:click="changeIngredient', false)
            ->assertSee('x-on:click="submit($wire)"', false)
            ->assertDontSee('wire:click="setIngredientQty', false)
            ->assertDontSee('wire:click="$set(\'itemQty\'', false);
    }

    public function test_product_catalog_does_not_query_customizations_once_per_product(): void
    {
        $area = $this->area();
        $operator = $this->employee(['ver mesas', 'ordenar mesas']);
        $mesa = $this->mesa($area, status: 'ocupada');
        $this->assign($mesa, $operator);
        $category = Category::create(['name' => 'Catálogo amplio', 'is_active' => true]);

        foreach (range(1, 15) as $number) {
            Product::create([
                'category_id' => $category->id,
                'name' => "Producto catálogo {$number}",
                'price' => $number,
                'is_active' => true,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::actingAs($operator)
            ->test(MesaOrden::class, ['mesa' => $mesa])
            ->assertSee('Producto catálogo 15');

        $queries = collect(DB::getQueryLog())
            ->pluck('query')
            ->map(fn ($query) => mb_strtolower($query));

        $this->assertLessThanOrEqual(
            2,
            $queries->filter(fn ($query) => str_contains($query, 'addon_groups'))->count(),
            'El catálogo volvió a consultar los complementos por cada producto.',
        );
        $this->assertLessThanOrEqual(
            2,
            $queries->filter(fn ($query) => str_contains($query, 'product_ingredient'))->count(),
            'El catálogo volvió a consultar los ingredientes por cada producto.',
        );
    }

    public function test_split_creation_and_cancellation_use_separate_permissions(): void
    {
        $area = $this->area();
        $divider = $this->employee(['ver mesas', 'dividir mesas']);
        $mesa = $this->mesa($area, status: 'en_cuenta');
        $register = $this->openRegister($divider);
        $order = Order::create([
            'cash_register_id' => $register->id,
            'mesa_id' => $mesa->id,
            'served_by' => $divider->id,
            'type' => 'mesa',
            'status' => 'pendiente',
            'subtotal' => 80,
            'total' => 80,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Consumo de prueba',
            'product_price' => 80,
            'quantity' => 1,
            'subtotal' => 80,
        ]);

        Livewire::actingAs($divider)
            ->test(SplitCuenta::class, ['mesa' => $mesa])
            ->set('mode', 'igual')
            ->set('equalParts', 2)
            ->call('confirm')
            ->assertHasNoErrors()
            ->call('requestCancelConfirm')
            ->assertForbidden();

        $split = MesaSplit::where('mesa_id', $mesa->id)->firstOrFail();

        $canceller = $this->employee([
            'ver mesas',
            'dividir mesas',
            'cancelar divisiones mesas',
        ]);
        Livewire::actingAs($canceller)
            ->test(SplitCuenta::class, ['mesa' => $mesa])
            ->call('requestCancelConfirm')
            ->call('cancelConfirm')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('mesa_splits', ['id' => $split->id]);
        $this->assertSame('ocupada', $mesa->fresh()->status);
    }

    private function employee(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            $this->permission($permission);
        }
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function permission(string $name): Permission
    {
        return Permission::findOrCreate($name, 'web');
    }

    private function area(): Area
    {
        return Area::create(['name' => 'Salón']);
    }

    private function mesa(Area $area, string $status = 'disponible'): Mesa
    {
        return Mesa::create([
            'area_id' => $area->id,
            'number' => ((int) Mesa::max('number')) + 1,
            'capacity' => 4,
            'status' => $status,
        ]);
    }

    private function assign(Mesa $mesa, User $user): MesaAssignment
    {
        return MesaAssignment::create([
            'mesa_id' => $mesa->id,
            'user_id' => $user->id,
            'assigned_by' => $user->id,
            'assigned_at' => now(),
        ]);
    }

    private function openRegister(User $user): CashRegister
    {
        return CashRegister::create([
            'name' => 'Caja de pruebas',
            'opened_by' => $user->id,
            'initial_amount' => 0,
            'opened_at' => now(),
            'is_open' => true,
        ]);
    }
}
