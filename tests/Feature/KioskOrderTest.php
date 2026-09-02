<?php

namespace Tests\Feature;

use App\Livewire\Kiosk\OrderTracking;
use App\Livewire\Kiosk\OrderWizard;
use App\Models\Addon;
use App\Models\AddonGroup;
use App\Models\Area;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Customer;
use App\Models\KioskProductPromotion;
use App\Models\KioskTerminal;
use App\Models\Mesa;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\KioskQrCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class KioskOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_kiosk_shows_a_friendly_page_for_an_invalid_terminal_token(): void
    {
        $this->get('/kiosco/'.Str::random(64))
            ->assertOk()
            ->assertSee('Este acceso ya no está disponible')
            ->assertDontSee('404');
    }

    public function test_paused_terminal_shows_a_friendly_waiting_state(): void
    {
        [$token, $terminal] = $this->terminal();
        $terminal->update(['is_active' => false]);

        $this->get(route('kiosk.order', $token))
            ->assertOk()
            ->assertSee('Aún no hay terminales abiertas')
            ->assertSee('Comprobar nuevamente')
            ->assertDontSee('404');
    }

    public function test_valid_terminal_can_render_public_kiosk_without_authentication(): void
    {
        [$token] = $this->terminal();

        $this->get(route('kiosk.order', $token))
            ->assertOk()
            ->assertSee('Kiosco de autoservicio')
            ->assertSee('Comer aquí')
            ->assertSee('kiosk-is-booting', false)
            ->assertSee('kiosk-initial-skeleton', false);
    }

    public function test_kiosk_images_use_long_lived_cache_and_conditional_requests(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/cached-product.webp', 'fake-webp-content');

        $url = route('kiosk.media', ['path' => 'products/cached-product.webp']);
        $response = $this->get($url)->assertOk();
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $etag = $response->headers->get('ETag');

        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=31536000', $cacheControl);
        $this->assertStringContainsString('immutable', $cacheControl);
        $this->assertNotEmpty($etag);
        $this->assertNotEmpty($response->headers->get('Last-Modified'));

        $this->withHeaders(['If-None-Match' => $etag])
            ->get($url)
            ->assertNotModified();
    }

    public function test_delivery_only_appears_when_enabled_for_the_terminal(): void
    {
        [$disabledToken] = $this->terminal();

        $this->get(route('kiosk.order', $disabledToken))
            ->assertOk()
            ->assertDontSee('Para domicilio');

        [$enabledToken] = $this->terminal(['allow_delivery' => true]);

        $this->get(route('kiosk.order', $enabledToken))
            ->assertOk()
            ->assertSee('Para domicilio')
            ->assertSeeText('Lo llevamos a la dirección que nos indiques');
    }

    public function test_disabled_delivery_mode_cannot_be_selected_or_submitted(): void
    {
        [$token] = $this->terminal();

        Livewire::test(OrderWizard::class, ['token' => $token])
            ->call('chooseFulfillment', 'delivery')
            ->assertStatus(422);
    }

    public function test_customer_can_create_a_delivery_order_with_contact_and_address(): void
    {
        [$token, $terminal, $user] = $this->terminal(['allow_delivery' => true]);
        CashRegister::create([
            'name' => 'Caja principal',
            'opened_by' => $user->id,
            'initial_amount' => 0,
            'opened_at' => now(),
            'is_open' => true,
        ]);
        $product = Product::create([
            'name' => 'Pasta grande',
            'price' => 185,
            'is_active' => true,
        ]);

        $component = Livewire::test(OrderWizard::class, ['token' => $token])
            ->call('chooseFulfillment', 'delivery')
            ->assertSet('step', 2)
            ->call('openProduct', $product->id)
            ->call('reviewOrder')
            ->set('customerName', 'María')
            ->call('placeOrder')
            ->assertHasErrors(['customerPhone', 'deliveryStreet', 'deliveryNeighborhood']);

        $component
            ->set('customerPhone', '5512345678')
            ->set('deliveryStreet', 'Av. Reforma 120, interior 3')
            ->set('deliveryNeighborhood', 'Centro')
            ->set('deliveryReferences', 'Portón negro frente al parque')
            ->call('placeOrder')
            ->assertHasNoErrors()
            ->assertSet('step', 6)
            ->assertSee('kiosk-success-shell', false)
            ->assertSee('Seguimiento en vivo')
            ->assertSee('Siguiente cliente')
            ->call('startAgain')
            ->assertSet('step', 1)
            ->assertSet('selectedCustomerId', null)
            ->assertSet('customerLookup', '');

        $this->assertDatabaseHas('orders', [
            'kiosk_terminal_id' => $terminal->id,
            'source' => 'kiosk',
            'type' => 'delivery',
            'fulfillment' => 'delivery',
            'delivery_method' => 'contra_entrega',
            'customer_phone' => '5512345678',
            'customer_address' => 'Av. Reforma 120, interior 3, Centro',
            'customer_references' => 'Portón negro frente al parque',
        ]);
        $this->assertDatabaseHas('customers', [
            'name' => 'María',
            'phone' => '5512345678',
            'address' => 'Av. Reforma 120, interior 3',
            'neighborhood' => 'Centro',
        ]);
    }

    public function test_review_requires_a_ten_digit_phone_a_name_without_numbers_and_at_most_fifty_note_words(): void
    {
        [$token, $terminal, $user] = $this->terminal(['allow_delivery' => true]);
        CashRegister::create([
            'name' => 'Caja principal',
            'opened_by' => $user->id,
            'initial_amount' => 0,
            'opened_at' => now(),
            'is_open' => true,
        ]);
        $product = Product::create(['name' => 'Pasta', 'price' => 150, 'is_active' => true]);

        $component = Livewire::test(OrderWizard::class, ['token' => $token])
            ->call('chooseFulfillment', 'delivery')
            ->call('openProduct', $product->id)
            ->call('reviewOrder')
            ->set('customerName', 'Ana 2')
            ->set('customerPhone', '55123')
            ->set('deliveryStreet', 'Calle Uno 10')
            ->set('deliveryNeighborhood', 'Centro')
            ->set('orderNotes', implode(' ', array_fill(0, 51, 'palabra')))
            ->call('placeOrder')
            ->assertHasErrors(['customerName', 'customerPhone', 'orderNotes']);

        $component
            ->set('customerName', 'Ana López')
            ->set('customerPhone', '5512345678')
            ->set('orderNotes', implode(' ', array_fill(0, 50, 'palabra')))
            ->call('placeOrder')
            ->assertHasNoErrors(['customerName', 'customerPhone', 'orderNotes'])
            ->assertSet('step', 6);

        $this->assertDatabaseHas('orders', [
            'kiosk_terminal_id' => $terminal->id,
            'customer_name' => 'Ana López',
            'customer_phone' => '5512345678',
        ]);
    }

    public function test_returning_customer_can_search_by_name_verify_phone_and_prefill_delivery_data(): void
    {
        [$token, $terminal, $user] = $this->terminal(['allow_delivery' => true]);
        CashRegister::create([
            'name' => 'Caja principal',
            'opened_by' => $user->id,
            'initial_amount' => 0,
            'opened_at' => now(),
            'is_open' => true,
        ]);
        $product = Product::create(['name' => 'Pedido habitual', 'price' => 120, 'is_active' => true]);
        $customer = Customer::create([
            'name' => 'Ana Recurrente',
            'phone' => '5512345678',
            'address' => 'Calle Privada 918, interior 3, Centro',
            'references' => 'Portón negro',
        ]);

        Livewire::test(OrderWizard::class, ['token' => $token])
            ->call('chooseFulfillment', 'delivery')
            ->call('openProduct', $product->id)
            ->call('reviewOrder')
            ->set('customerLookup', 'Ana Rec')
            ->assertSee('Ana Recurrente')
            ->assertSee('••••••5678')
            ->assertDontSee('5512345678')
            ->assertDontSee('Calle Privada 918')
            ->call('chooseCustomerLookupResult', $customer->id)
            ->assertSet('pendingCustomerId', $customer->id)
            ->assertSee('Confirma que eres tú')
            ->set('customerVerificationDigits', '0000')
            ->call('confirmCustomerLookup')
            ->assertHasErrors('customerVerificationDigits')
            ->set('customerVerificationDigits', '5678')
            ->call('confirmCustomerLookup')
            ->assertHasNoErrors('customerVerificationDigits')
            ->assertSet('selectedCustomerId', $customer->id)
            ->assertSet('customerName', 'Ana Recurrente')
            ->assertSet('customerPhone', '5512345678')
            ->assertSet('deliveryStreet', 'Calle Privada 918, interior 3')
            ->assertSet('deliveryNeighborhood', 'Centro')
            ->assertSet('deliveryReferences', 'Portón negro')
            ->call('placeOrder')
            ->assertHasNoErrors()
            ->assertSet('step', 6);

        $this->assertDatabaseHas('orders', [
            'kiosk_terminal_id' => $terminal->id,
            'customer_id' => $customer->id,
            'customer_name' => 'Ana Recurrente',
            'customer_phone' => '5512345678',
            'customer_address' => 'Calle Privada 918, interior 3, Centro',
            'customer_references' => 'Portón negro',
        ]);
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'neighborhood' => 'Centro',
        ]);
    }

    public function test_exact_customer_phone_prefills_data_without_an_extra_verification_step(): void
    {
        [$token] = $this->terminal(['allow_delivery' => true]);
        $customer = Customer::create([
            'name' => 'Luis Frecuente',
            'phone' => '5598765432',
            'address' => 'Calle Uno 10, Roma',
        ]);

        Livewire::test(OrderWizard::class, ['token' => $token])
            ->call('chooseFulfillment', 'delivery')
            ->set('step', 5)
            ->set('customerLookup', '5598765432')
            ->assertSee('Luis Frecuente')
            ->call('chooseCustomerLookupResult', $customer->id)
            ->assertSet('selectedCustomerId', $customer->id)
            ->assertSet('pendingCustomerId', null)
            ->assertSet('customerName', 'Luis Frecuente')
            ->assertSet('customerPhone', '5598765432')
            ->assertSet('deliveryStreet', 'Calle Uno 10')
            ->assertSet('deliveryNeighborhood', 'Roma');
    }

    public function test_customer_can_create_a_server_priced_kiosk_order(): void
    {
        [$token, $terminal, $user] = $this->terminal();
        CashRegister::create([
            'name' => 'Caja principal',
            'opened_by' => $user->id,
            'initial_amount' => 0,
            'opened_at' => now(),
            'is_open' => true,
        ]);
        $category = Category::create(['name' => 'Favoritos', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Hamburguesa clásica',
            'price' => 129.50,
            'is_active' => true,
        ]);

        Livewire::test(OrderWizard::class, ['token' => $token])
            ->call('chooseFulfillment', 'takeaway')
            ->call('openProduct', $product->id)
            ->set('customerName', 'Ana')
            ->set('customerPhone', '5512345678')
            ->call('reviewOrder')
            ->call('placeOrder')
            ->assertSet('step', 6)
            ->assertSet('completedOrderId', 1)
            ->assertSee('Siguiente cliente')
            ->assertDontSee('Ver seguimiento');

        $order = Order::with('items')->firstOrFail();
        $this->assertSame('kiosk', $order->source);
        $this->assertSame('takeaway', $order->fulfillment);
        $this->assertSame($terminal->id, $order->kiosk_terminal_id);
        $this->assertSame('129.50', $order->total);
        $this->assertSame(64, strlen($order->public_token));
        $this->assertSame('129.50', $order->items->first()->subtotal);

        $this->get(route('kiosk.track', $order->public_token))
            ->assertOk()
            ->assertSee('Pedido '.$order->display_folio)
            ->assertSee('Hamburguesa clásica')
            ->assertSee('Actualizar estado')
            ->assertDontSee('Actualización automática');

        $tracking = Livewire::test(OrderTracking::class, ['publicToken' => $order->public_token])
            ->assertSee('Pedido recibido');

        $order->update(['status' => 'en_preparacion']);

        $tracking
            ->call('refreshStatus')
            ->assertSee('Estamos preparando tu pedido')
            ->assertNotSet('lastCheckedAt', null);

        $order->update(['status' => 'lista']);

        $tracking
            ->call('refreshStatus')
            ->assertSee('¡Tu pedido está listo!');
    }

    public function test_qr_service_generates_an_embeddable_svg(): void
    {
        $dataUri = app(KioskQrCode::class)->dataUri('https://example.test/pedido/demo');

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $dataUri);
        $this->assertStringContainsString('<svg', base64_decode(Str::after($dataUri, ',')));
    }

    public function test_kiosk_shows_and_persists_the_selected_addon_quantity(): void
    {
        [$token, $terminal, $user] = $this->terminal();
        CashRegister::create([
            'name' => 'Caja principal', 'opened_by' => $user->id,
            'initial_amount' => 0, 'opened_at' => now(), 'is_open' => true,
        ]);
        $product = Product::create([
            'name' => 'Hamburguesa especial', 'price' => 100,
            'is_customizable' => true, 'max_addons' => 2, 'is_active' => true,
        ]);
        $group = AddonGroup::create([
            'name' => 'Quesos', 'is_required' => false,
            'min_selections' => 0, 'max_selections' => 2, 'is_active' => true,
        ]);
        $addon = Addon::create([
            'addon_group_id' => $group->id, 'name' => 'Queso extra',
            'extra_price' => 10, 'is_active' => true,
        ]);
        $product->addonGroups()->attach($group->id);
        $area = Area::create(['name' => 'Terraza']);
        $mesa = Mesa::create(['area_id' => $area->id, 'number' => 4, 'capacity' => 4, 'status' => 'disponible']);

        $component = Livewire::test(OrderWizard::class, ['token' => $token])
            ->call('chooseFulfillment', 'dine_in')
            ->call('openProduct', $product->id)
            ->call('addCustomizedProduct', [$addon->id => 3], [], 1, '')
            ->assertHasErrors('customization')
            ->assertSet('cart', [])
            ->call('addCustomizedProduct', [$addon->id => 2], [], 1, '')
            ->assertHasNoErrors('customization')
            ->assertSet('cart.0.addon_names.0', 'Queso extra ×2')
            ->assertSet('cart.0.subtotal', 120.0)
            ->set('customerName', 'Luis')
            ->call('reviewOrder')
            ->call('placeOrder')
            ->assertHasErrors('selectedMesaId');

        $component
            ->set('selectedMesaId', $mesa->id)
            ->call('placeOrder')
            ->assertHasNoErrors()
            ->assertSet('step', 6);

        $this->assertDatabaseHas('order_item_addons', [
            'addon_id' => $addon->id,
            'quantity' => 2,
            'extra_price' => 10,
        ]);
        $this->assertDatabaseHas('orders', [
            'kiosk_terminal_id' => $terminal->id,
            'mesa_id' => $mesa->id,
            'type' => 'mesa',
            'total' => 120,
        ]);
        $this->assertDatabaseHas('mesas', ['id' => $mesa->id, 'status' => 'ocupada']);
    }

    public function test_customer_can_choose_a_visual_food_recommendation_before_the_menu(): void
    {
        [$token] = $this->terminal();
        $category = Category::create(['name' => 'Hamburguesas', 'description' => 'Jugosas y preparadas al momento', 'is_active' => true]);
        Product::create([
            'category_id' => $category->id,
            'name' => 'Hamburguesa clásica',
            'price' => 100,
            'image' => 'products/hamburguesa.webp',
            'is_active' => true,
        ]);
        Product::create(['category_id' => $category->id, 'name' => 'Papas crujientes', 'price' => 45, 'is_active' => true]);

        Livewire::test(OrderWizard::class, ['token' => $token])
            ->call('chooseFulfillment', 'dine_in')
            ->assertSet('step', 2)
            ->assertSee('¿Qué quieres comer hoy?')
            ->assertSee('Hamburguesas')
            ->assertSeeHtml('x-data="kioskImageLoader"')
            ->assertSeeHtml('x-ref="image"')
            ->assertSee(route('kiosk.media', ['path' => 'products/hamburguesa.webp']), false)
            ->call('chooseRecommendation', $category->id)
            ->assertSet('step', 3)
            ->assertSet('categoryFilter', $category->id)
            ->assertSet('recommendationName', 'Hamburguesas')
            ->assertSee('Te recomendamos Hamburguesas')
            ->assertSee('Hamburguesa clásica')
            ->assertSee('Papas crujientes')
            ->assertSeeHtml('x-on:submit.prevent')
            ->assertSeeHtml('x-model.debounce.120ms="query"')
            ->assertSeeHtml('x-show="matches(')
            ->assertDontSeeHtml('wire:submit="applySearch"')
            ->assertDontSeeHtml('wire:model="search"');
    }

    public function test_featured_product_uses_the_promotional_price_from_homepage_to_sale(): void
    {
        [$token, $terminal, $user] = $this->terminal([
            'promotion_enabled' => true,
            'promotion_badge' => 'Solo por hoy',
            'promotion_title' => 'El favorito de la casa',
            'promotion_message' => 'Pídelo ahora con precio especial.',
        ]);
        CashRegister::create([
            'name' => 'Caja principal',
            'opened_by' => $user->id,
            'initial_amount' => 0,
            'opened_at' => now(),
            'is_open' => true,
        ]);
        $product = Product::create([
            'name' => 'Taco especial',
            'description' => 'Preparado al momento',
            'price' => 95,
            'image' => 'products/taco-especial.webp',
            'is_active' => true,
        ]);
        KioskProductPromotion::create([
            'kiosk_terminal_id' => $terminal->id,
            'product_id' => $product->id,
            'promotional_price' => 79,
            'label' => 'Más vendido',
        ]);
        $secondProduct = Product::create([
            'name' => 'Papas crujientes',
            'description' => 'Recién hechas',
            'price' => 45,
            'image' => 'products/papas-crujientes.webp',
            'is_active' => true,
        ]);
        KioskProductPromotion::create([
            'kiosk_terminal_id' => $terminal->id,
            'product_id' => $secondProduct->id,
            'promotional_price' => null,
            'label' => 'Para compartir',
            'sort_order' => 1,
        ]);

        $this->get(route('kiosk.order', $token))
            ->assertOk()
            ->assertDontSee('Solo por hoy')
            ->assertDontSee('El favorito de la casa')
            ->assertDontSee('Pídelo ahora con precio especial.')
            ->assertSee('Taco especial')
            ->assertSee('Papas crujientes')
            ->assertSee('$79.00')
            ->assertSee('$95.00')
            ->assertSee('kiosk-carousel-dots', false)
            ->assertSee('kiosk-carousel-enter-start', false)
            ->assertSee('fetchpriority="low"', false)
            ->assertSee('kiosk-loadable-card', false)
            ->assertSee('kiosk-welcome--fullscreen', false)
            ->assertDontSee('class="kiosk-home"', false);

        Livewire::test(OrderWizard::class, ['token' => $token])
            ->call('selectFeaturedProduct', $product->id)
            ->assertSet('featuredProductIntent', $product->id)
            ->call('chooseFulfillment', 'takeaway')
            ->assertSet('step', 3)
            ->assertSet('cart.0.product_id', $product->id)
            ->assertSet('cart.0.subtotal', 79.0)
            ->set('customerName', 'Laura')
            ->call('reviewOrder')
            ->call('placeOrder')
            ->assertHasNoErrors()
            ->assertSet('step', 6);

        $this->assertDatabaseHas('orders', [
            'kiosk_terminal_id' => $terminal->id,
            'total' => 79,
        ]);
        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'product_price' => 79,
            'subtotal' => 79,
        ]);
    }

    public function test_featured_product_without_discount_keeps_the_regular_price(): void
    {
        [$token, $terminal] = $this->terminal([
            'promotion_enabled' => true,
            'promotion_badge' => 'Recomendaciones',
            'promotion_title' => 'Nuestros favoritos',
            'promotion_message' => 'Productos que vale la pena probar.',
        ]);
        $product = Product::create([
            'name' => 'Taco recomendado',
            'description' => 'Preparado al momento',
            'price' => 95,
            'is_active' => true,
        ]);
        KioskProductPromotion::create([
            'kiosk_terminal_id' => $terminal->id,
            'product_id' => $product->id,
            'promotional_price' => null,
            'label' => 'Recomendado',
        ]);

        $this->get(route('kiosk.order', $token))
            ->assertOk()
            ->assertDontSee('Nuestros favoritos')
            ->assertDontSee('Productos que vale la pena probar.')
            ->assertSee('Taco recomendado')
            ->assertSee('$95.00')
            ->assertDontSee('<del>$95.00</del>', false);

        Livewire::test(OrderWizard::class, ['token' => $token])
            ->call('selectFeaturedProduct', $product->id)
            ->call('chooseFulfillment', 'takeaway')
            ->assertSet('cart.0.product_id', $product->id)
            ->assertSet('cart.0.subtotal', 95.0);
    }

    private function terminal(array $overrides = []): array
    {
        $user = User::factory()->create();
        $token = Str::random(64);
        $terminal = KioskTerminal::create(array_merge([
            'name' => 'Kiosco de prueba',
            'token_hash' => hash('sha256', $token),
            'user_id' => $user->id,
            'is_active' => true,
        ], $overrides));

        return [$token, $terminal, $user];
    }
}
