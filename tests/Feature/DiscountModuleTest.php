<?php

namespace Tests\Feature;

use App\Livewire\Admin\DiscountManager;
use App\Livewire\Pos\PointOfSale;
use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\Discount;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\DiscountPricingService;
use App\Services\ThermalTicketRenderer;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SidebarMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DiscountModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_public_product_discount_is_applied_only_to_selected_products(): void
    {
        $eligible = Product::create(['name' => 'Latte', 'price' => 100, 'is_active' => true]);
        $other = Product::create(['name' => 'Postre', 'price' => 50, 'is_active' => true]);
        $discount = Discount::create([
            'name' => 'Buen Fin bebidas', 'category' => 'seasonal', 'value_type' => 'percentage',
            'value' => 20, 'scope' => 'products', 'audience' => 'everyone',
            'fulfillment_modes' => ['takeaway'], 'is_active' => true, 'auto_apply' => true,
        ]);
        $discount->products()->attach($eligible);

        $priced = app(DiscountPricingService::class)->apply([
            $this->line($eligible, 100),
            $this->line($other, 50),
        ], 'takeaway');

        $this->assertSame(80.0, (float) $priced[0]['subtotal']);
        $this->assertSame(20.0, (float) $priced[0]['discount_amount']);
        $this->assertSame(50.0, (float) $priced[1]['subtotal']);
        $this->assertSame($discount->id, $priced[0]['discount_id']);
    }

    public function test_selected_customer_discount_requires_that_customer_and_honors_minimum_purchase(): void
    {
        $customer = Customer::create(['name' => 'Cliente frecuente', 'phone' => '9991112233']);
        $otherCustomer = Customer::create(['name' => 'Otro cliente', 'phone' => '9990000000']);
        $product = Product::create(['name' => 'Consumo', 'price' => 120, 'is_active' => true]);
        $discount = Discount::create([
            'name' => 'Cliente preferente', 'value_type' => 'fixed', 'value' => 30,
            'scope' => 'order', 'audience' => 'selected_customers', 'minimum_purchase' => 100,
            'fulfillment_modes' => ['takeaway'], 'is_active' => true, 'auto_apply' => true,
        ]);
        $discount->customers()->attach($customer);
        $service = app(DiscountPricingService::class);

        $this->assertSame(120.0, (float) $service->apply([$this->line($product, 120)], 'takeaway', $otherCustomer->id)[0]['subtotal']);
        $priced = $service->apply([$this->line($product, 120)], 'takeaway', $customer->id);
        $this->assertSame(90.0, (float) $priced[0]['subtotal']);
        $this->assertSame('Cliente preferente', $priced[0]['discount_snapshot']['name']);
    }

    public function test_pos_identifies_an_employee_and_reprices_automatically(): void
    {
        $cashier = User::factory()->create();
        $cashier->givePermissionTo(['aplicar descuentos', 'crear ventas en punto de venta']);
        $employee = User::factory()->create(['name' => 'Empleado asociado']);
        $product = Product::create(['name' => 'Menú de personal', 'price' => 100, 'is_active' => true]);
        $discount = Discount::create([
            'name' => 'Descuento de asociado', 'category' => 'associate', 'value_type' => 'percentage',
            'value' => 25, 'scope' => 'order', 'audience' => 'selected_employees',
            'fulfillment_modes' => ['takeaway'], 'is_active' => true, 'auto_apply' => true,
        ]);
        $discount->employees()->attach($employee);
        CashRegister::create([
            'name' => 'Caja descuentos', 'opened_by' => $cashier->id, 'initial_amount' => 0,
            'opened_at' => now(), 'is_open' => true,
        ]);

        Livewire::actingAs($cashier)->test(PointOfSale::class)
            ->call('openCustomizeModal', $product->id)
            ->call('addToCart')
            ->set('customerName', 'Cliente mostrador')
            ->assertSet('cart.0.subtotal', 100.0)
            ->call('openCheckoutModal')
            ->call('setCheckoutIdentityType', 'employee')
            ->assertSet('checkoutIdentityType', 'employee')
            ->set('customerSearch', 'Empleado asociado')
            ->assertSee('Empleado asociado')
            ->assertSee('Empleado')
            ->call('selectCheckoutIdentity', $employee->id)
            ->assertSet('discountEmployeeId', $employee->id)
            ->assertSet('customerName', 'Empleado asociado')
            ->assertSet('customerSearch', '')
            ->assertSet('cart.0.subtotal', 75.0)
            ->assertSet('cart.0.discount_id', $discount->id)
            ->assertSee('Descuento de asociado')
            ->assertSee('Beneficio aplicado')
            ->assertSee('Total actualizado: $75.00')
            ->call('submitOrderLater')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('orders', ['discount_beneficiary_user_id' => $employee->id, 'total' => 75]);
        $this->assertDatabaseHas('order_items', ['discount_id' => $discount->id, 'discount_amount' => 25]);
        $ticket = app(ThermalTicketRenderer::class)->renderOrder(Order::with(['items.addons', 'items.ingredients', 'payments'])->sole(), 'customer');
        $this->assertStringContainsString('Descuento: Descuento de asociado (-$25.00)', $ticket);
    }

    public function test_discount_does_not_touch_promotional_lines_unless_configured(): void
    {
        $promoProduct = Product::create(['name' => 'Producto promocional', 'price' => 100, 'is_active' => true]);
        $regularProduct = Product::create(['name' => 'Producto regular', 'price' => 100, 'is_active' => true]);
        Discount::create([
            'name' => 'Diez general', 'value_type' => 'percentage', 'value' => 10,
            'scope' => 'order', 'audience' => 'everyone', 'combine_with_promotions' => false,
            'fulfillment_modes' => ['takeaway'], 'is_active' => true, 'auto_apply' => true,
        ]);
        $promo = $this->line($promoProduct, 50);
        $promo['promotion_discount'] = 50;
        $promo['promotion_rule_snapshot'] = ['label' => '2x1'];

        $priced = app(DiscountPricingService::class)->apply([$promo, $this->line($regularProduct, 100)], 'takeaway');

        $this->assertSame(50.0, (float) $priced[0]['subtotal']);
        $this->assertSame(90.0, (float) $priced[1]['subtotal']);
        $this->assertSame(10.0, (float) collect($priced)->sum('discount_amount'));
    }

    public function test_manager_permissions_sidebar_and_rule_persistence_are_complete(): void
    {
        $this->seed(SidebarMenuSeeder::class);
        $user = User::factory()->create();
        $user->givePermissionTo(['ver descuentos', 'crear descuentos']);
        $product = Product::create(['name' => 'Bebida elegible', 'price' => 80, 'is_active' => true]);

        Livewire::actingAs($user)->test(DiscountManager::class)
            ->assertSee('Descuentos')
            ->call('openCreate')
            ->set('name', 'Descuento ocasional')
            ->set('valueType', 'percentage')
            ->set('value', '15')
            ->set('scope', 'products')
            ->set('productIds', [$product->id])
            ->set('audience', 'everyone')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('discounts', ['name' => 'Descuento ocasional', 'value' => 15]);
        $this->assertDatabaseHas('discount_product', ['product_id' => $product->id]);
        $this->assertDatabaseHas('sidebar_menu_items', [
            'system_key' => 'restaurant.discounts',
            'permission' => 'ver descuentos',
            'icon' => 'bx-purchase-tag-alt',
        ]);
    }

    public function test_editor_is_rendered_above_its_backdrop_and_uses_available_icons(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['ver descuentos', 'crear descuentos']);

        Livewire::actingAs($user)->test(DiscountManager::class)
            ->call('openCreate')
            ->assertSet('showEditor', true)
            ->assertSee('Crear descuento')
            ->assertSeeHtml('discount-modal-backdrop')
            ->assertSeeHtml('discount-modal-layer');

        $moduleCss = file_get_contents(public_path('assets/css/discounts.css'));
        $this->assertMatchesRegularExpression('/\.discount-modal-backdrop\s*\{[^}]*z-index:1190/s', $moduleCss);
        $this->assertMatchesRegularExpression('/\.discount-modal-layer\s*\{[^}]*z-index:1200/s', $moduleCss);

        $views = file_get_contents(resource_path('views/livewire/admin/discount-manager.blade.php'))
            .file_get_contents(resource_path('views/livewire/pos/partials/cart.blade.php'));
        preg_match_all('/bx-[a-z0-9-]+/', $views, $matches);
        $boxicons = file_get_contents(public_path('assets/vendor/fonts/boxicons.css'));

        foreach (array_unique($matches[0]) as $icon) {
            $this->assertStringContainsString(".{$icon}:before", $boxicons, "El icono {$icon} no existe en Boxicons.");
        }
    }

    private function line(Product $product, float $subtotal): array
    {
        return [
            'cart_id' => 'line-'.$product->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price' => (float) $product->price,
            'unit_total' => (float) $product->price,
            'quantity' => 1,
            'subtotal' => $subtotal,
            'promotion_discount' => 0.0,
            'promotion_selections' => [],
            'addons' => [],
            'ingredients' => [],
            'notes' => '',
        ];
    }
}
