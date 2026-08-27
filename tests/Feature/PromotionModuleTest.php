<?php

namespace Tests\Feature;

use App\Livewire\Admin\PromotionManager;
use App\Livewire\Kiosk\OrderWizard;
use App\Livewire\Mesas\MesaOrden;
use App\Livewire\Pos\PointOfSale;
use App\Models\CashRegister;
use App\Models\Area;
use App\Models\Ingredient;
use App\Models\KioskTerminal;
use App\Models\Mesa;
use App\Models\Order;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Quotation;
use App\Models\SidebarMenuItem;
use App\Models\User;
use App\Services\ThermalTicketRenderer;
use App\Services\PromotionPricingService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SidebarMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PromotionModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_buy_one_second_half_price_is_calculated_from_base_product_only(): void
    {
        $product = Product::create(['name' => 'Combo Latte', 'price' => 80, 'is_active' => true]);
        $promotion = Promotion::create([
            'name' => 'Lanzamiento Combo Latte',
            'presentation_type' => 'new',
            'primary_product_id' => $product->id,
            'price' => 80,
            'pricing_rule_type' => Promotion::PRICING_RULE_BUY_X_GET_Y_DISCOUNT,
            'pricing_rule_config' => [
                'version' => 1,
                'buy_quantity' => 1,
                'reward_quantity' => 1,
                'reward_discount_percentage' => 50,
                'max_applications_per_order' => null,
            ],
            'auto_apply' => true,
            'starts_on' => now()->subDay(),
            'fulfillment_modes' => ['takeaway'],
            'show_on_pos' => true,
            'show_on_kiosk' => true,
            'show_on_digital_menu' => true,
            'is_active' => true,
        ]);
        $service = app(PromotionPricingService::class);

        foreach ([1 => [80, 0], 2 => [120, 40], 3 => [200, 40], 4 => [240, 80]] as $quantity => [$total, $discount]) {
            $priced = $service->apply([[
                'cart_id' => 'latte',
                'product_id' => $product->id,
                'product_price' => 80,
                'unit_total' => 80,
                'quantity' => $quantity,
                'subtotal' => 80 * $quantity,
            ]], 'pos', 'takeaway');

            $this->assertSame((float) $total, (float) $priced[0]['subtotal']);
            $this->assertSame((float) $discount, (float) ($priced[0]['promotion_discount'] ?? 0));
        }

        $withAddon = $service->apply([[
            'cart_id' => 'latte-extra',
            'product_id' => $product->id,
            'product_price' => 80,
            'unit_total' => 90,
            'quantity' => 2,
            'subtotal' => 180,
        ]], 'pos', 'takeaway');

        $this->assertSame(140.0, $withAddon[0]['subtotal']);
        $this->assertSame(40.0, $withAddon[0]['promotion_discount']);
        $this->assertSame($promotion->id, $withAddon[0]['promotion_id']);

        $notEligible = $service->apply($withAddon, 'pos', 'delivery');
        $this->assertSame(180.0, $notEligible[0]['subtotal']);
        $this->assertSame(0.0, $notEligible[0]['promotion_discount']);
    }

    public function test_pos_reprices_the_cart_automatically_and_removes_the_offer_when_fulfillment_changes(): void
    {
        $user = User::factory()->create();
        $product = Product::create(['name' => 'Combo Latte', 'price' => 80, 'is_active' => true]);
        $promotion = Promotion::create([
            'name' => 'Segundo Latte a mitad',
            'presentation_type' => 'new',
            'primary_product_id' => $product->id,
            'price' => 80,
            'pricing_rule_type' => Promotion::PRICING_RULE_BUY_X_GET_Y_DISCOUNT,
            'pricing_rule_config' => ['buy_quantity' => 1, 'reward_quantity' => 1, 'reward_discount_percentage' => 50],
            'auto_apply' => true,
            'starts_on' => now()->subDay(),
            'fulfillment_modes' => ['takeaway'],
            'show_on_pos' => true,
            'is_active' => true,
        ]);

        $component = Livewire::actingAs($user)->test(PointOfSale::class)
            ->call('openCustomizeModal', $product->id)
            ->call('openCustomizeModal', $product->id)
            ->assertSet('cart.0.quantity', 2)
            ->assertSet('cart.0.subtotal', 120.0)
            ->assertSet('cart.0.promotion_discount', 40.0)
            ->assertSet('cart.0.promotion_id', $promotion->id);

        $component->set('orderType', 'delivery')
            ->assertSet('cart.0.subtotal', 160.0)
            ->assertSet('cart.0.promotion_discount', 0.0)
            ->assertSet('cart.0.promotion_id', null);
    }

    public function test_availability_respects_channels_dates_and_configured_weekdays(): void
    {
        $monday = now()->startOfWeek();
        $promotion = Promotion::create([
            'name' => 'Lunes de hamburguesas',
            'price' => 199,
            'starts_on' => $monday->copy()->subDay(),
            'ends_on' => $monday->copy()->addDay(),
            'weekdays' => [1],
            'show_on_pos' => true,
            'show_on_digital_menu' => false,
            'is_active' => true,
        ]);

        $this->assertTrue($promotion->isAvailableFor('pos', $monday));
        $this->assertFalse($promotion->isAvailableFor('digital_menu', $monday));
        $this->assertFalse($promotion->isAvailableFor('pos', $monday->copy()->addDay()));
        $this->assertFalse($promotion->isAvailableFor('pos', $monday->copy()->addDays(2)));

        $promotion->update(['ends_on' => null, 'weekdays' => []]);
        $this->assertTrue($promotion->fresh()->isAvailableFor('pos', $monday->copy()->addYear()));
    }

    public function test_promotion_eligibility_is_filtered_by_fulfillment_but_new_products_remain_universal(): void
    {
        $promotion = Promotion::create([
            'name' => 'Combo exclusivo para comedor',
            'price' => 150,
            'starts_on' => now()->subDay(),
            'fulfillment_modes' => ['dine_in'],
            'show_on_pos' => true,
            'show_on_digital_menu' => true,
            'show_on_kiosk' => true,
            'is_active' => true,
        ]);

        $this->assertTrue($promotion->isAvailableFor('pos', null, 'dine_in'));
        $this->assertFalse($promotion->isAvailableFor('pos', null, 'takeaway'));
        $this->assertFalse($promotion->isAvailableFor('kiosk', null, 'delivery'));
        $this->assertSame('Solo para comer aquí', $promotion->fulfillmentSummary());

        $newProduct = Promotion::create([
            'name' => 'Producto recién llegado',
            'presentation_type' => 'new',
            'price' => 90,
            'starts_on' => now()->subDay(),
            'fulfillment_modes' => ['delivery'],
            'show_on_digital_menu' => true,
            'is_active' => true,
        ]);

        $this->assertTrue($newProduct->appliesToFulfillment('dine_in'));
        $this->assertCount(4, $newProduct->fulfillmentLabels());
    }

    public function test_authorized_user_can_create_promotion_with_multiple_products_and_fixed_selection_limit(): void
    {
        Storage::fake('public');
        Permission::create(['name' => 'ver promociones', 'guard_name' => 'web']);
        Permission::create(['name' => 'crear promociones', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo(['ver promociones', 'crear promociones']);
        $products = collect([
            Product::create(['name' => 'Hamburguesa clásica', 'price' => 100, 'is_active' => true]),
            Product::create(['name' => 'Hamburguesa BBQ', 'price' => 120, 'is_active' => true]),
            Product::create(['name' => 'Hamburguesa pollo', 'price' => 110, 'is_active' => true]),
        ]);

        Livewire::actingAs($user)->test(PromotionManager::class)
            ->call('openCreate')
            ->assertSet('showEditor', true)
            ->assertSee('Crear campaña')
            ->assertSee('aria-describedby="promotion-editor-description"', false)
            ->assertSee('wire:click.self="closeEditor"', false)
            ->set('presentationType', 'discount')
            ->call('nextWizardStep')
            ->assertSet('wizardStep', 2)
            ->set('name', 'Elige dos hamburguesas')
            ->set('shortDescription', 'Dos hamburguesas a precio especial para compartir.')
            ->set('discountPercentage', '25')
            ->set('price', '179.00')
            ->set('groups.0.name', 'Escoge tus hamburguesas')
            ->set('groups.0.min_selections', 2)
            ->set('groups.0.max_selections', 2)
            ->set('groups.0.product_ids', $products->pluck('id')->all())
            ->call('nextWizardStep')
            ->assertSet('wizardStep', 3)
            ->set('image', UploadedFile::fake()->image('campaña.jpg', 2400, 800))
            ->assertSee('Vista previa del banner')
            ->set('startsOn', now()->toDateString())
            ->set('endsOn', '')
            ->set('fulfillmentModes', ['takeaway', 'delivery'])
            ->set('termsAndConditions', 'No acumulable con otras promociones.')
            ->set('showOnKiosk', true)
            ->call('nextWizardStep')
            ->assertSet('wizardStep', 4)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showEditor', false)
            ->assertSee('Campañas configuradas')
            ->assertSee('Elige dos hamburguesas');

        $promotion = Promotion::with('groups.products')->firstOrFail();
        $this->assertSame('179.00', $promotion->price);
        $this->assertSame('discount', $promotion->presentation_type);
        $this->assertSame(25, $promotion->discount_percentage);
        $this->assertSame(['takeaway', 'delivery'], $promotion->fulfillment_modes);
        $this->assertSame('No acumulable con otras promociones.', $promotion->terms_and_conditions);
        $this->assertTrue($promotion->show_on_kiosk);
        $this->assertSame('Dos hamburguesas a precio especial para compartir.', $promotion->short_description);
        $this->assertNotNull($promotion->image);
        $this->assertStringEndsWith('.webp', $promotion->image);
        Storage::disk('public')->assertExists($promotion->image);
        $optimizedImage = getimagesize(Storage::disk('public')->path($promotion->image));
        $this->assertSame('image/webp', $optimizedImage['mime']);
        $this->assertLessThanOrEqual(1600, $optimizedImage[0]);
        $this->get(route('public.menu'))
            ->assertOk()
            ->assertSee('class="promotion-banner is-discount has-image"', false)
            ->assertSee("background-image: url('".Storage::disk('public')->url($promotion->image)."')", false);
        $this->assertNull($promotion->ends_on);
        $this->assertCount(1, $promotion->groups);
        $this->assertSame(2, $promotion->groups->first()->min_selections);
        $this->assertSame(2, $promotion->groups->first()->max_selections);
        $this->assertCount(3, $promotion->groups->first()->products);

        $modalCss = file_get_contents(public_path('assets/css/promotions.css'));
        $this->assertStringContainsString('body:has(.promotion-modal-layer){overflow:hidden}', $modalCss);
        $this->assertStringContainsString('.promotion-weekdays input:focus-visible+span', $modalCss);
        $this->assertStringContainsString('pointer-events:auto', $modalCss);
    }

    public function test_pos_requires_group_limits_and_uses_only_promotional_price(): void
    {
        [$promotion, $products] = $this->promotionFixture();
        $group = $promotion->groups->first();
        $user = User::factory()->create();
        CashRegister::create([
            'name' => 'Caja para promociones',
            'opened_by' => $user->id,
            'initial_amount' => 0,
            'opened_at' => now(),
            'is_open' => true,
        ]);

        $component = Livewire::actingAs($user)->test(PointOfSale::class)
            ->call('openPromotionModal', $promotion->id)
            ->assertSee($promotion->name)
            ->assertSee('Elige de 2 a 2')
            ->call('changePromotionSelection', $group->id, $products[0]->id, 1)
            ->call('addPromotionToCart')
            ->assertHasErrors('promotion')
            ->call('changePromotionSelection', $group->id, $products[1]->id, 1)
            ->call('addPromotionToCart')
            ->assertHasNoErrors()
            ->assertCount('cart', 1)
            ->assertSet('cart.0.product_id', null)
            ->assertSet('cart.0.promotion_id', $promotion->id)
            ->assertSet('cart.0.product_price', 175.0)
            ->assertSet('cart.0.unit_total', 175.0)
            ->assertSet('cart.0.subtotal', 175.0);

        $snapshot = $component->get('cart')[0]['promotion_selections'];
        $this->assertCount(2, $snapshot[0]['items']);
        $this->assertSame(2, collect($snapshot[0]['items'])->sum('quantity'));
    }

    public function test_wizard_creates_a_new_product_campaign_without_promotion_groups(): void
    {
        Storage::fake('public');
        Permission::create(['name' => 'ver promociones', 'guard_name' => 'web']);
        Permission::create(['name' => 'crear promociones', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo(['ver promociones', 'crear promociones']);
        $image = UploadedFile::fake()->image('nuevo.jpg', 900, 700)->store('products', 'public');
        $product = Product::create([
            'name' => 'Pasta de temporada',
            'description' => 'Una pasta nueva que conserva todas sus opciones.',
            'image' => $image,
            'price' => 210,
            'is_customizable' => true,
            'is_active' => true,
        ]);
        Livewire::actingAs($user)->test(PromotionManager::class)
            ->call('openCreate')
            ->set('presentationType', 'new')
            ->call('nextWizardStep')
            ->assertSet('wizardStep', 2)
            ->set('primaryProductId', $product->id)
            ->assertSet('name', $product->name)
            ->assertSet('price', '210.00')
            ->call('nextWizardStep')
            ->assertSet('wizardStep', 3)
            ->call('nextWizardStep')
            ->assertSet('wizardStep', 4)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showEditor', false);

        $campaign = Promotion::with('groups')->firstOrFail();
        $this->assertSame('new', $campaign->presentation_type);
        $this->assertSame($product->id, $campaign->primary_product_id);
        $this->assertFalse($campaign->show_on_pos);
        $this->assertCount(0, $campaign->groups);
    }

    public function test_wizard_can_attach_a_channel_limited_launch_offer_to_a_new_product(): void
    {
        Storage::fake('public');
        Permission::create(['name' => 'ver promociones', 'guard_name' => 'web']);
        Permission::create(['name' => 'crear promociones', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo(['ver promociones', 'crear promociones']);
        $image = UploadedFile::fake()->image('latte.jpg', 900, 700)->store('products', 'public');
        $product = Product::create([
            'name' => 'Combo Latte',
            'description' => 'Café de lanzamiento con opciones personalizables.',
            'image' => $image,
            'price' => 80,
            'is_active' => true,
        ]);

        Livewire::actingAs($user)->test(PromotionManager::class)
            ->call('openCreate')
            ->set('presentationType', 'new')
            ->call('nextWizardStep')
            ->set('primaryProductId', $product->id)
            ->set('launchOfferEnabled', true)
            ->set('buyQuantity', 1)
            ->set('rewardQuantity', 1)
            ->set('rewardDiscountPercentage', 50)
            ->set('maxApplicationsPerOrder', '2')
            ->call('nextWizardStep')
            ->assertSet('wizardStep', 3)
            ->set('fulfillmentModes', ['takeaway'])
            ->set('termsAndConditions', 'Máximo dos beneficios por pedido.')
            ->set('showOnPos', true)
            ->set('showOnKiosk', false)
            ->call('nextWizardStep')
            ->call('save')
            ->assertHasNoErrors();

        $campaign = Promotion::firstOrFail();
        $this->assertTrue($campaign->hasAutomaticPricingRule());
        $this->assertSame('Compra 1 y recibe 1 al 50%', $campaign->pricingRuleLabel());
        $this->assertSame(2, $campaign->pricing_rule_config['max_applications_per_order']);
        $this->assertSame(['takeaway'], $campaign->fulfillment_modes);
        $this->assertTrue($campaign->show_on_pos);
        $this->assertFalse($campaign->show_on_kiosk);
        $this->assertSame('Máximo dos beneficios por pedido.', $campaign->terms_and_conditions);
    }

    public function test_pos_revalidates_promotion_expiry_before_checkout(): void
    {
        [$promotion, $products] = $this->promotionFixture();
        $group = $promotion->groups->first();

        $component = Livewire::test(PointOfSale::class)
            ->call('openPromotionModal', $promotion->id)
            ->call('changePromotionSelection', $group->id, $products[0]->id, 1)
            ->call('changePromotionSelection', $group->id, $products[1]->id, 1)
            ->call('addPromotionToCart')
            ->assertCount('cart', 1);

        $promotion->update(['ends_on' => now()->subDay()]);

        $component->call('openCheckoutModal')
            ->assertHasErrors('cart')
            ->assertSet('showCheckoutModal', false);
    }

    public function test_public_digital_menu_displays_active_promotion_and_selection_rules(): void
    {
        [$promotion] = $this->promotionFixture();
        $promotion->update([
            'presentation_type' => 'discount',
            'discount_percentage' => 25,
            'short_description' => 'Elige dos hamburguesas y disfruta un precio especial.',
            'fulfillment_modes' => ['takeaway'],
            'terms_and_conditions' => 'Válido únicamente para recoger en ventanilla.',
        ]);

        $this->get(route('public.menu'))
            ->assertOk()
            ->assertSee('Beneficios por tiempo limitado')
            ->assertSee($promotion->name)
            ->assertSee('$175.00')
            ->assertSee('25% de descuento')
            ->assertSee('Precio promo')
            ->assertSee('promotion-banner__price', false)
            ->assertSee('promotion-banner', false)
            ->assertSee('id="digital-promotions"', false)
            ->assertSee('data-category-link="digital-promotions"', false)
            ->assertSee('Elige de 2 a 2')
            ->assertSee('Solo para llevar')
            ->assertSee('Válido únicamente para recoger en ventanilla.')
            ->assertSee('los productos internos no suman su precio individual');

        $css = file_get_contents(public_path('assets/css/promotions-public.css'));
        $javascript = file_get_contents(public_path('assets/js/public-menu.js'));
        $this->assertStringContainsString('background-position:center;background-repeat:no-repeat;background-size:cover', $css);
        $this->assertStringContainsString('.promotion-banner{position:relative;min-width:0;aspect-ratio:3.2/1;min-height:220px', $css);
        $this->assertStringContainsString('.promotion-banner{aspect-ratio:2.15/1;min-height:158px', $css);
        $this->assertStringContainsString('.promotion-banner__overlay p{font-size:.62rem', $css);
        $this->assertStringContainsString('grid-auto-columns:20%;align-items:start', $css);
        $this->assertStringContainsString('height:auto!important;min-height:0', $css);
        $this->assertStringContainsString('height:88px!important;min-height:88px!important;max-height:88px!important', $css);
        $this->assertStringContainsString('@media(max-width:1279px){.new-products__rail{grid-auto-columns:24%', $css);
        $this->assertStringContainsString('@media(max-width:1023px){.new-products__rail{grid-auto-columns:31.5%', $css);
        $this->assertStringContainsString('.new-products__rail{grid-auto-columns:calc((100% - 10px)/2);gap:10px;', $css);
        $this->assertStringContainsString('window.setInterval', $javascript);
        $this->assertStringContainsString("[data-menu-section], [data-category-section]", $javascript);
        $menuHtml = $this->get(route('public.menu'))->getContent();
        $this->assertStringContainsString('data-promotion-modal-fulfillment', $menuHtml);
        $this->assertStringContainsString('<span data-promotion-dot', $menuHtml);
        $this->assertStringNotContainsString('data-promotion-previous', $menuHtml);
        $this->assertStringNotContainsString('data-promotion-next', $menuHtml);
    }

    public function test_kiosk_only_lists_campaigns_for_selected_fulfillment_and_persists_selection(): void
    {
        [$promotion, $products] = $this->promotionFixture();
        $promotion->update([
            'fulfillment_modes' => ['takeaway'],
            'show_on_kiosk' => true,
            'terms_and_conditions' => 'Solo en kiosco para recoger.',
        ]);
        $user = User::factory()->create();
        $token = Str::random(64);
        KioskTerminal::create([
            'name' => 'Kiosco promociones',
            'token_hash' => hash('sha256', $token),
            'user_id' => $user->id,
            'is_active' => true,
            'allow_takeaway' => true,
            'allow_delivery' => true,
        ]);
        CashRegister::create([
            'name' => 'Caja kiosco promociones',
            'opened_by' => $user->id,
            'initial_amount' => 0,
            'opened_at' => now(),
            'is_open' => true,
        ]);
        $group = $promotion->groups->first();

        Livewire::test(OrderWizard::class, ['token' => $token])
            ->call('chooseFulfillment', 'takeaway')
            ->call('chooseRecommendation')
            ->assertSee($promotion->name)
            ->call('openPromotionModal', $promotion->id)
            ->assertSee('Solo en kiosco para recoger.')
            ->call('changePromotionSelection', $group->id, $products[0]->id, 1)
            ->call('changePromotionSelection', $group->id, $products[1]->id, 1)
            ->call('addPromotionToCart')
            ->assertSet('cart.0.promotion_id', $promotion->id)
            ->set('customerName', 'Cliente Kiosco')
            ->call('placeOrder')
            ->assertHasNoErrors()
            ->assertSet('completedOrderId', fn ($value) => filled($value));

        $this->assertDatabaseHas('order_items', [
            'promotion_id' => $promotion->id,
            'product_id' => null,
            'product_price' => 175,
        ]);

        Livewire::test(OrderWizard::class, ['token' => $token])
            ->call('chooseFulfillment', 'delivery')
            ->call('chooseRecommendation')
            ->assertDontSee($promotion->name);
    }

    public function test_pos_rejects_a_promotion_when_final_order_type_is_not_allowed(): void
    {
        Permission::create(['name' => 'crear ordenes', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo('crear ordenes');
        CashRegister::create([
            'name' => 'Caja validación modalidades',
            'opened_by' => $user->id,
            'initial_amount' => 0,
            'opened_at' => now(),
            'is_open' => true,
        ]);
        [$promotion, $products] = $this->promotionFixture();
        $promotion->update(['fulfillment_modes' => ['delivery']]);
        $group = $promotion->groups->first();

        Livewire::actingAs($user)->test(PointOfSale::class)
            ->call('openPromotionModal', $promotion->id)
            ->call('changePromotionSelection', $group->id, $products[0]->id, 1)
            ->call('changePromotionSelection', $group->id, $products[1]->id, 1)
            ->call('addPromotionToCart')
            ->set('customerName', 'Cliente modalidad')
            ->set('orderType', 'ventanilla')
            ->call('submitOrderLater')
            ->assertHasErrors('cart');
    }

    public function test_pos_distinguishes_takeaway_pickup_and_hides_dine_in_only_promotions(): void
    {
        Permission::create(['name' => 'crear ordenes', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo('crear ordenes');
        CashRegister::create([
            'name' => 'Caja modalidades separadas',
            'opened_by' => $user->id,
            'initial_amount' => 0,
            'opened_at' => now(),
            'is_open' => true,
        ]);
        [$promotion, $products] = $this->promotionFixture();
        $group = $promotion->groups->first();

        $promotion->update(['fulfillment_modes' => ['dine_in']]);
        Livewire::actingAs($user)->test(PointOfSale::class)
            ->assertDontSee($promotion->name)
            ->call('openPromotionModal', $promotion->id)
            ->assertSet('showPromotionModal', false);

        $promotion->update(['fulfillment_modes' => ['pickup']]);
        Livewire::actingAs($user)->test(PointOfSale::class)
            ->assertSee($promotion->name)
            ->call('openPromotionModal', $promotion->id)
            ->call('changePromotionSelection', $group->id, $products[0]->id, 1)
            ->call('changePromotionSelection', $group->id, $products[1]->id, 1)
            ->call('addPromotionToCart')
            ->set('customerName', 'Cliente para recoger')
            ->set('orderType', 'ventanilla')
            ->call('submitOrderLater')
            ->assertHasErrors('cart');

        Livewire::actingAs($user)->test(PointOfSale::class)
            ->call('openPromotionModal', $promotion->id)
            ->call('changePromotionSelection', $group->id, $products[0]->id, 1)
            ->call('changePromotionSelection', $group->id, $products[1]->id, 1)
            ->call('addPromotionToCart')
            ->set('customerName', 'Cliente para recoger')
            ->set('orderType', 'pick_up')
            ->call('submitPickupLater')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('orders', ['type' => 'pick_up', 'customer_name' => 'Cliente para recoger']);
        $this->assertDatabaseHas('order_items', ['promotion_id' => $promotion->id]);
    }

    public function test_waiter_can_only_order_dine_in_promotions_and_snapshot_is_persisted(): void
    {
        Permission::create(['name' => 'ordenar mesas', 'guard_name' => 'web']);
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo('ordenar mesas');
        $user->assignRole('admin');
        $area = Area::create(['name' => 'Comedor principal']);
        $mesa = Mesa::create(['area_id' => $area->id, 'number' => 1, 'status' => 'ocupada']);
        CashRegister::create([
            'name' => 'Caja comedor promociones',
            'opened_by' => $user->id,
            'initial_amount' => 0,
            'opened_at' => now(),
            'is_open' => true,
        ]);
        [$promotion, $products] = $this->promotionFixture();
        $promotion->update(['fulfillment_modes' => ['dine_in']]);
        $group = $promotion->groups->first();

        Livewire::actingAs($user)->test(MesaOrden::class, ['mesa' => $mesa])
            ->assertSee($promotion->name)
            ->call('openPromotionModal', $promotion->id)
            ->call('changePromotionSelection', $group->id, $products[0]->id, 1)
            ->call('changePromotionSelection', $group->id, $products[1]->id, 1)
            ->call('addPromotionToCart')
            ->assertSet('cart.0.promotion_id', $promotion->id)
            ->call('placeOrder')
            ->assertHasNoErrors()
            ->assertSet('cart', []);

        $this->assertDatabaseHas('order_items', [
            'promotion_id' => $promotion->id,
            'product_id' => null,
            'product_price' => 175,
        ]);

        $promotion->update(['fulfillment_modes' => ['delivery']]);
        Livewire::actingAs($user)->test(MesaOrden::class, ['mesa' => $mesa])
            ->assertDontSee($promotion->name);
    }

    public function test_public_digital_menu_hides_promotional_section_when_none_are_available(): void
    {
        $this->get(route('public.menu'))
            ->assertOk()
            ->assertDontSee('id="digital-promotions-title"', false)
            ->assertDontSee('data-promotion-carousel', false);
    }

    public function test_new_product_campaign_uses_its_own_section_and_inherits_product_details(): void
    {
        Storage::fake('public');
        $image = UploadedFile::fake()->image('pasta-nueva.jpg', 900, 700)->store('products', 'public');
        $product = Product::create([
            'name' => 'Pasta Primavera',
            'description' => 'Pasta personalizable con ingredientes frescos.',
            'image' => $image,
            'price' => 189,
            'is_customizable' => true,
            'is_active' => true,
        ]);
        $ingredient = Ingredient::create([
            'name' => 'Champiñones frescos',
            'extra_price' => 15,
            'is_active' => true,
        ]);
        $product->ingredients()->attach($ingredient->id, ['sort_order' => 0]);
        $campaign = Promotion::create([
            'name' => 'Nueva Pasta Primavera',
            'short_description' => 'Conoce nuestra nueva pasta y personalízala a tu gusto.',
            'presentation_type' => 'new',
            'primary_product_id' => $product->id,
            'price' => $product->price,
            'starts_on' => now()->toDateString(),
            'show_on_pos' => false,
            'show_on_digital_menu' => true,
            'is_active' => true,
        ]);

        $this->assertFalse($campaign->isAvailableFor('pos'));

        $this->get(route('public.menu'))
            ->assertOk()
            ->assertSee('Nuevos productos')
            ->assertSee('id="new-products"', false)
            ->assertSee('data-category-link="new-products"', false)
            ->assertSee('Pasta Primavera')
            ->assertSee('Nuevo')
            ->assertSee('Pasta personalizable con ingredientes frescos.')
            ->assertSee('Champiñones frescos')
            ->assertSee('data-new-product-item', false)
            ->assertDontSee('id="digital-promotions-title"', false);
    }

    public function test_calendar_is_a_separate_view_and_uses_the_same_schedule(): void
    {
        Permission::create(['name' => 'ver promociones', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo('ver promociones');
        [$promotion] = $this->promotionFixture();

        Livewire::actingAs($user)
            ->test(PromotionManager::class)
            ->call('showCalendar')
            ->assertSet('activeView', 'calendar')
            ->assertSee('Programación')
            ->assertSee($promotion->name)
            ->call('showCatalog')
            ->assertSet('activeView', 'catalog');
    }

    public function test_saved_pos_order_preserves_promotion_and_selections_when_reopened(): void
    {
        Permission::create(['name' => 'crear ordenes', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo('crear ordenes');
        CashRegister::create([
            'name' => 'Caja promociones',
            'opened_by' => $user->id,
            'initial_amount' => 0,
            'opened_at' => now(),
            'is_open' => true,
        ]);
        [$promotion, $products] = $this->promotionFixture();
        $group = $promotion->groups->first();

        $component = Livewire::actingAs($user)->test(PointOfSale::class)
            ->call('openPromotionModal', $promotion->id)
            ->call('changePromotionSelection', $group->id, $products[0]->id, 1)
            ->call('changePromotionSelection', $group->id, $products[1]->id, 1)
            ->call('addPromotionToCart')
            ->set('quotationName', 'Pedido promocional')
            ->call('saveQuotation')
            ->assertHasNoErrors()
            ->assertSet('cart', []);

        $quotation = Quotation::firstOrFail();
        $this->assertDatabaseHas('quotation_items', [
            'quotation_id' => $quotation->id,
            'promotion_id' => $promotion->id,
            'product_id' => null,
            'product_price' => 175,
        ]);

        $component->call('loadQuotation', $quotation->id)
            ->assertSet('cart.0.promotion_id', $promotion->id)
            ->assertSet('cart.0.product_id', null)
            ->assertSet('cart.0.promotion_selections.0.group_name', 'Hamburguesas');
    }

    public function test_pos_sale_persists_promotion_snapshot_and_prints_selected_products(): void
    {
        Permission::create(['name' => 'crear ordenes', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo('crear ordenes');
        CashRegister::create([
            'name' => 'Caja venta promocional',
            'opened_by' => $user->id,
            'initial_amount' => 0,
            'opened_at' => now(),
            'is_open' => true,
        ]);
        [$promotion, $products] = $this->promotionFixture();
        $group = $promotion->groups->first();

        Livewire::actingAs($user)->test(PointOfSale::class)
            ->call('openPromotionModal', $promotion->id)
            ->call('changePromotionSelection', $group->id, $products[0]->id, 1)
            ->call('changePromotionSelection', $group->id, $products[1]->id, 1)
            ->call('addPromotionToCart')
            ->set('customerName', 'Cliente promoción')
            ->call('submitOrderLater')
            ->assertHasNoErrors()
            ->assertSet('cart', []);

        $order = Order::with('items')->firstOrFail();
        $item = $order->items->firstOrFail();
        $this->assertSame($promotion->id, $item->promotion_id);
        $this->assertNull($item->product_id);
        $this->assertSame('175.00', $item->product_price);
        $this->assertSame(2, collect($item->promotion_selections[0]['items'])->sum('quantity'));

        $ticket = app(ThermalTicketRenderer::class)->renderOrder($order, 'customer', autoPrint: false);
        $this->assertStringContainsString('Hamburguesa clásica', $ticket);
        $this->assertStringContainsString('Hamburguesa BBQ', $ticket);
        $this->assertStringNotContainsString('$100.00', $ticket);
        $this->assertStringNotContainsString('$120.00', $ticket);
    }

    public function test_seeders_register_module_and_promotion_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SidebarMenuSeeder::class);

        foreach (['ver promociones', 'crear promociones', 'editar promociones', 'eliminar promociones'] as $permission) {
            $this->assertDatabaseHas('permissions', ['name' => $permission, 'group' => 'promociones']);
        }
        $this->assertDatabaseHas('sidebar_menu_items', [
            'system_key' => 'restaurant.promotions',
            'route_name' => 'app.promociones',
            'permission' => 'ver promociones',
        ]);
        $this->assertNotNull(SidebarMenuItem::where('system_key', 'restaurant.promotions')->first());
    }

    private function promotionFixture(): array
    {
        $products = collect([
            Product::create(['name' => 'Hamburguesa clásica', 'price' => 100, 'is_active' => true]),
            Product::create(['name' => 'Hamburguesa BBQ', 'price' => 120, 'is_active' => true]),
            Product::create(['name' => 'Hamburguesa pollo', 'price' => 110, 'is_active' => true]),
        ]);
        $promotion = Promotion::create([
            'name' => 'Combo dos hamburguesas',
            'description' => 'Escoge dos de nuestras tres hamburguesas.',
            'price' => 175,
            'starts_on' => now()->subDay(),
            'ends_on' => null,
            'weekdays' => [],
            'show_on_pos' => true,
            'show_on_digital_menu' => true,
            'is_active' => true,
        ]);
        $group = $promotion->groups()->create([
            'name' => 'Hamburguesas',
            'min_selections' => 2,
            'max_selections' => 2,
        ]);
        $group->products()->attach($products->pluck('id')->all());

        return [$promotion->load('groups.products'), $products];
    }
}
