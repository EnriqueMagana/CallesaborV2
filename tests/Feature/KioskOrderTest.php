<?php

namespace Tests\Feature;

use App\Livewire\Kiosk\OrderTracking;
use App\Livewire\Kiosk\OrderWizard;
use App\Models\Addon;
use App\Models\AddonGroup;
use App\Models\Area;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\KioskTerminal;
use App\Models\Mesa;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\KioskQrCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('Comer aquí');
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
            ->assertSet('step', 6);

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
            ->assertSee('Preparar para el siguiente cliente')
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
            ->assertSee('Pedido #'.$order->id)
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
            ->call('changeAddonQuantity', $group->id, $addon->id, 1)
            ->call('changeAddonQuantity', $group->id, $addon->id, 1)
            ->assertSet("addonQuantities.{$addon->id}", 2)
            ->call('changeAddonQuantity', $group->id, $addon->id, 1)
            ->assertHasErrors('customization')
            ->call('addCustomizedProduct')
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
        Product::create(['category_id' => $category->id, 'name' => 'Hamburguesa clásica', 'price' => 100, 'is_active' => true]);

        Livewire::test(OrderWizard::class, ['token' => $token])
            ->call('chooseFulfillment', 'dine_in')
            ->assertSet('step', 2)
            ->assertSee('¿Qué quieres comer hoy?')
            ->assertSee('Hamburguesas')
            ->call('chooseRecommendation', $category->id)
            ->assertSet('step', 3)
            ->assertSet('categoryFilter', $category->id)
            ->assertSet('recommendationName', 'Hamburguesas')
            ->assertSee('Te recomendamos Hamburguesas')
            ->assertSee('Hamburguesa clásica');
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
