<?php

namespace Tests\Feature;

use App\Livewire\Mesas\GestionMesas;
use App\Livewire\Mesas\SplitCuenta;
use App\Livewire\Pos\PointOfSale;
use App\Models\Area;
use App\Models\BusinessSetting;
use App\Models\CashRegister;
use App\Models\KioskTerminal;
use App\Models\Mesa;
use App\Models\MesaSplit;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\TicketTemplate;
use App\Models\User;
use App\Services\MesaServiceManager;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class KioskPosWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_kiosk_orders_are_separated_by_operational_area_and_follow_the_status_flow(): void
    {
        [$user, $register, $terminal, $mesa] = $this->posContext();
        $pickup = $this->kioskOrder($register->id, $user->id, $terminal->id, 'Cliente Pickup', 'takeaway');
        $delivery = $this->kioskOrder($register->id, $user->id, $terminal->id, 'Cliente Delivery', 'delivery');
        $dineIn = $this->kioskOrder($register->id, $user->id, $terminal->id, 'Cliente Mesa', 'dine_in', $mesa->id);

        $this->actingAs($user);

        $component = Livewire::test(PointOfSale::class)
            ->assertSee('Cliente Pickup')
            ->call('openDeliveryPanel')
            ->assertSee('Cliente Delivery')
            ->call('openTablesBilling')
            ->assertSee('Cliente Mesa')
            ->assertSee('Mesa '.$mesa->number)
            ->call('openPickupPayModal', $pickup->id)
            ->assertSet('showPickupPayModal', false)
            ->assertDispatched('notify');

        $component->call('markKitchenReady', $pickup->id);
        $this->assertDatabaseHas('orders', ['id' => $pickup->id, 'status' => 'en_preparacion']);

        $component->call('markKitchenReady', $pickup->id);
        $this->assertDatabaseHas('orders', ['id' => $pickup->id, 'status' => 'lista']);

        $this->payOrder($component, $pickup->id);
        $this->assertDatabaseHas('orders', ['id' => $pickup->id, 'status' => 'pagada']);
        $this->assertDatabaseHas('order_payments', ['order_id' => $pickup->id, 'method' => 'efectivo', 'amount' => 100]);

        $component->call('reprintKitchenOrder', $delivery->id);
        $this->assertDatabaseHas('orders', ['id' => $delivery->id, 'status' => 'pendiente']);
        $component->call('markKitchenReady', $delivery->id);
        $this->assertDatabaseHas('orders', ['id' => $delivery->id, 'status' => 'en_preparacion']);

        $component->call('markKitchenReady', $dineIn->id);
        $component->call('markKitchenReady', $dineIn->id);
        $this->assertDatabaseHas('orders', ['id' => $dineIn->id, 'status' => 'lista']);
    }

    public function test_paid_delivery_remains_operational_in_pos_and_can_advance_to_ready(): void
    {
        [$user, $register] = $this->posContext();
        $delivery = Order::create([
            'cash_register_id' => $register->id,
            'customer_name' => 'Delivery transferencia',
            'customer_phone' => '5512345678',
            'customer_address' => 'Calle Prueba 15',
            'served_by' => $user->id,
            'type' => 'delivery',
            'source' => 'pos',
            'fulfillment' => 'delivery',
            'delivery_method' => 'transferencia',
            'status' => 'pendiente',
            'subtotal' => 100,
            'total' => 100,
        ]);
        OrderItem::create([
            'order_id' => $delivery->id,
            'product_name' => 'Pedido delivery',
            'product_price' => 100,
            'quantity' => 1,
            'subtotal' => 100,
        ]);
        OrderPayment::create([
            'order_id' => $delivery->id,
            'method' => 'transferencia',
            'amount' => 100,
        ]);

        $pos = Livewire::actingAs($user)
            ->test(PointOfSale::class)
            ->call('openDeliveryPanel')
            ->assertSee('Delivery transferencia')
            ->assertSee('Imprimir cocina')
            ->call('markKitchenReady', $delivery->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('orders', ['id' => $delivery->id, 'status' => 'en_preparacion']);

        $pos->call('markKitchenReady', $delivery->id)->assertHasNoErrors();
        $this->assertDatabaseHas('orders', ['id' => $delivery->id, 'status' => 'lista']);
    }

    public function test_pos_requires_and_prefills_the_customer_neighborhood(): void
    {
        [$user] = $this->posContext();

        $component = Livewire::actingAs($user)
            ->test(PointOfSale::class)
            ->call('openAddCustomerModal')
            ->set('newCustomerName', 'Cliente Zona')
            ->set('newCustomerPhone', '5511122233')
            ->call('saveNewCustomer')
            ->assertHasErrors('newCustomerNeighborhood')
            ->set('newCustomerNeighborhood', 'Roma Norte')
            ->call('saveNewCustomer')
            ->assertHasNoErrors()
            ->assertSet('customerNeighborhood', 'Roma Norte');

        $this->assertDatabaseHas('customers', [
            'name' => 'Cliente Zona',
            'phone' => '5511122233',
            'neighborhood' => 'Roma Norte',
        ]);
    }

    public function test_table_billing_queries_are_deferred_until_the_panel_is_opened(): void
    {
        [$user, $register, , $mesa] = $this->posContext();
        app(MesaServiceManager::class)
            ->resolveOrCreate($mesa, $register, $user->id)
            ->update(['status' => 'en_cuenta', 'in_account_at' => now()]);

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        $pos = Livewire::actingAs($user)->test(PointOfSale::class)
            ->assertSet('tablesBillingLoaded', false)
            ->assertSee('Mesas y comandas');

        $initialTableQueries = collect($queries)->filter(fn (string $sql) => str_contains($sql, 'mesa_services'));
        $this->assertTrue($initialTableQueries->contains(fn (string $sql) => str_contains($sql, 'count(')));
        $this->assertFalse(collect($queries)->contains(fn (string $sql) => str_contains($sql, 'mesa_service_mesa')));
        $this->assertFalse($initialTableQueries->contains(fn (string $sql) => str_contains($sql, 'select *')));

        $queries = [];
        $pos->call('openTablesBilling')
            ->assertSet('tablesBillingLoaded', true)
            ->assertSee('Mesa '.$mesa->number);

        $this->assertTrue(collect($queries)->contains(
            fn (string $sql) => str_contains($sql, 'mesa_services')
        ));

        $pos->call('closeTablesBilling')->assertSet('tablesBillingLoaded', false);
        $queries = [];
        $pos->set('productSearch', 'hamburguesa');

        $this->assertFalse(collect($queries)->contains(fn (string $sql) => str_contains($sql, 'mesa_service_mesa')));
        $this->assertFalse(collect($queries)->contains(
            fn (string $sql) => str_contains($sql, 'mesa_services') && str_contains($sql, 'select *')
        ));
    }

    public function test_toolbar_badges_are_available_before_operational_panels_are_opened(): void
    {
        [$user, $register, $terminal, $mesa] = $this->posContext();
        app(MesaServiceManager::class)->resolveOrCreate($mesa, $register, $user->id);
        $this->kioskOrder($register->id, $user->id, $terminal->id, 'Pedido para recoger', 'takeaway');
        $this->kioskOrder($register->id, $user->id, $terminal->id, 'Pedido para entregar', 'delivery');
        $this->kioskOrder($register->id, $user->id, $terminal->id, 'Delivery preparando', 'delivery', status: 'en_preparacion');
        $this->kioskOrder($register->id, $user->id, $terminal->id, 'Delivery listo', 'delivery', status: 'lista');

        Livewire::actingAs($user)
            ->test(PointOfSale::class)
            ->assertSet('tableWorkspaceLoaded', false)
            ->assertSet('deliveryPanelLoaded', false)
            ->assertSeeHtml('aria-label="1 pedidos pendientes"')
            ->assertSeeHtml('aria-label="1 servicios de mesa pendientes"')
            ->assertSeeHtml('aria-label="1 entregas nuevas sin enviar a cocina"');
    }

    public function test_operational_panels_are_exclusive_and_backend_state_is_released_when_switching(): void
    {
        [$user] = $this->posContext();

        Livewire::actingAs($user)
            ->test(PointOfSale::class)
            ->call('openTableWorkspace')
            ->assertSet('tableWorkspaceLoaded', true)
            ->assertSet('deliveryPanelLoaded', false)
            ->call('openDeliveryPanel')
            ->assertSet('tableWorkspaceLoaded', false)
            ->assertSet('tableTrackingLoaded', false)
            ->assertSet('tablesBillingLoaded', false)
            ->assertSet('deliveryPanelLoaded', true)
            ->call('openPickupPanel')
            ->assertSet('tableWorkspaceLoaded', false)
            ->assertSet('deliveryPanelLoaded', false)
            ->call('openTableWorkspace')
            ->assertSet('tableWorkspaceLoaded', true)
            ->call('openOperationsModal', 'expense')
            ->assertSet('tableWorkspaceLoaded', false)
            ->assertSet('deliveryPanelLoaded', false)
            ->assertSet('showExpenseModal', true);
    }

    public function test_desktop_pos_keeps_checkout_visible_and_exposes_operational_shortcuts(): void
    {
        $view = file_get_contents(resource_path('views/livewire/pos/point-of-sale.blade.php'));
        $cart = file_get_contents(resource_path('views/livewire/pos/partials/cart.blade.php'));
        $checkout = file_get_contents(resource_path('views/livewire/pos/partials/modals/checkout.blade.php'));
        $catalog = file_get_contents(resource_path('views/livewire/pos/partials/catalog.blade.php'));
        $header = file_get_contents(resource_path('views/livewire/pos/partials/header.blade.php'));
        $toolbar = file_get_contents(resource_path('views/livewire/pos/partials/toolbar.blade.php'));
        $mobileNavigation = file_get_contents(resource_path('views/livewire/pos/partials/mobile-navigation.blade.php'));
        $moreMenu = file_get_contents(resource_path('views/livewire/pos/partials/more-menu.blade.php'));
        $css = file_get_contents(public_path('assets/css/pos-modern.css'));
        $mobileCss = file_get_contents(public_path('assets/css/pos-mobile-navigation.css'));

        $this->assertIsString($view);
        $this->assertIsString($cart);
        $this->assertIsString($checkout);
        $this->assertIsString($catalog);
        $this->assertIsString($header);
        $this->assertIsString($toolbar);
        $this->assertIsString($mobileNavigation);
        $this->assertIsString($moreMenu);
        $this->assertIsString($css);
        $this->assertIsString($mobileCss);
        $this->assertStringContainsString('@keydown.window="handleKeyboardShortcut($event)"', $view);
        $this->assertStringContainsString("matchMedia('(min-width: 1025px)')", $view);
        $this->assertStringContainsString('@resize.window.debounce.150ms="syncSearchBreakpoint()"', $view);
        $this->assertStringContainsString('if (!this.isDesktop) this.searchExpanded = false;', $view);
        $this->assertStringContainsString('aria-keyshortcuts="F2"', $cart);
        $this->assertStringNotContainsString('data-pos-save-cart', $cart);
        $this->assertStringContainsString('data-pos-save-draft', $checkout);
        $this->assertStringContainsString('data-pos-submit-order', $checkout);
        $this->assertStringContainsString('cart-header__meta', $cart);
        $this->assertStringContainsString('cart-item__footer', $cart);
        $this->assertStringContainsString('cart-mobile-close', $cart);
        $this->assertStringContainsString('Nota para cocina', $cart);
        $this->assertStringContainsString('aria-keyshortcuts="F3 F10"', $header);
        $this->assertStringContainsString("'is-expanded': isCatalogSearchExpanded()", $header);
        $this->assertStringContainsString('pos-header-search__shortcut', $header);
        $this->assertStringContainsString('<kbd>F3</kbd><span aria-hidden="true">o</span><kbd>F10</kbd>', $header);
        $this->assertStringContainsString('x-model.debounce.160ms="catalogQuery"', $header);
        $this->assertStringContainsString('catalogQuery', $catalog);
        $this->assertStringNotContainsString('wire:model', $catalog);
        $this->assertStringContainsString('data-pos-saved', $header);
        $this->assertStringContainsString('>F4</kbd>', $header);
        $this->assertStringContainsString('>F5</kbd>', $checkout);
        $this->assertStringContainsString('aria-keyshortcuts="F2"', $checkout);
        foreach (['F6', 'F7', 'F8', 'F9', 'F11'] as $shortcut) {
            $this->assertStringContainsString('aria-keyshortcuts="'.$shortcut.'"', $toolbar);
        }
        $this->assertStringContainsString('data-pos-more', $toolbar);
        $this->assertStringContainsString("F11: '[data-pos-more]'", $view);
        $this->assertStringNotContainsString('wire:click="openOperationsModal', $toolbar);
        foreach (['Por cobrar', 'Mesas', 'Pedidos', 'Más'] as $mobileArea) {
            $this->assertStringContainsString($mobileArea, $mobileNavigation);
        }
        $this->assertStringContainsString('pos-mobile-nav__cart', $mobileNavigation);
        $this->assertStringContainsString("showOnlyPanel('pickup')", $mobileNavigation);
        $this->assertStringContainsString("showOnlyPanel('tables')", $mobileNavigation);
        $this->assertStringContainsString("showOnlyPanel('delivery')", $mobileNavigation);
        $this->assertStringContainsString('pos-more-backdrop', $moreMenu);
        $this->assertStringContainsString('role="dialog"', $moreMenu);
        foreach (['Guardados', 'Reimprimir', 'Registrar gasto', 'Ingreso de caja', 'Salida de insumos', 'Inicio'] as $allowedMoreAction) {
            $this->assertStringContainsString($allowedMoreAction, $moreMenu);
        }
        $this->assertStringContainsString('app.ordenes', $moreMenu);
        $this->assertStringContainsString('Cambiar datos', $moreMenu);
        $this->assertStringContainsString('openOrderDataModal', $moreMenu);
        $this->assertStringContainsString('Repartidores', $moreMenu);
        $this->assertStringContainsString("'reasignar pedidos delivery'", $moreMenu);
        $this->assertStringContainsString('openDeliveryDispatchModal', $moreMenu);
        $this->assertStringNotContainsString("route('app.delivery'", $moreMenu);
        $dispatchModal = file_get_contents(resource_path('views/livewire/pos/partials/modals/delivery-dispatch.blade.php'));
        $this->assertIsString($dispatchModal);
        $this->assertStringContainsString('Repartidores y pedidos', $dispatchModal);
        $this->assertStringContainsString('selectDeliveryDispatchOrder', $dispatchModal);
        $this->assertStringContainsString('reassignDeliveryFromPos', $dispatchModal);
        $this->assertStringContainsString('x-model.debounce.120ms="query"', $dispatchModal);
        $deliveryPanel = file_get_contents(resource_path('views/livewire/pos/partials/panels/delivery.blade.php'));
        $this->assertIsString($deliveryPanel);
        $this->assertStringNotContainsString('Gestionar reparto', $deliveryPanel);
        $this->assertStringContainsString('right: 25vw', $mobileCss);
        $this->assertStringContainsString('left: 25vw', $mobileCss);
        foreach (['app.clientes', 'app.historial-ventas', 'app.inventario', 'app.usuarios', 'app.reservas', 'app.constructor-menu', 'app.configuracion-negocio'] as $removedMoreRoute) {
            $this->assertStringNotContainsString($removedMoreRoute, $moreMenu);
        }
        $this->assertStringContainsString('@media (max-width: 1024px)', $mobileCss);
        $this->assertMatchesRegularExpression('/@media \(min-width:\s*1025px\)[\s\S]*?\.pos-header-search__trigger\s*\{\s*display:\s*none;/', $mobileCss);
        $this->assertMatchesRegularExpression('/@media \(min-width:\s*1025px\)[\s\S]*?\.pos-header-search__field\s*\{[^}]*position:\s*relative;[^}]*opacity:\s*1;[^}]*visibility:\s*visible;/', $mobileCss);
        $this->assertStringContainsString('transform: translateY(-50%) scaleX(1)', $mobileCss);
        $this->assertStringContainsString('--pos-mobile-nav-clearance:', $mobileCss);
        $this->assertStringContainsString('--pos-more-sheet-bottom:', $mobileCss);
        $this->assertStringContainsString('bottom: var(--pos-more-sheet-bottom)', $mobileCss);
        $this->assertStringContainsString('.pos-floating-panel > .pos-area-panel__body:not(.pos-reprint-results-shell)', $mobileCss);
        $this->assertStringContainsString('scroll-padding-bottom: var(--pos-mobile-nav-clearance)', $mobileCss);
        $this->assertStringContainsString('prefers-reduced-motion: reduce', $mobileCss);
        $this->assertMatchesRegularExpression('/\.pos-cart-fixed \.cart-items,[^{]*\{[^}]*min-height:\s*0;[^}]*overflow-y:\s*auto;/s', $css);
        $this->assertMatchesRegularExpression('/@media \(min-width:\s*1025px\) and \(max-height:\s*760px\)/', $css);
    }

    public function test_product_categories_have_mouse_touch_and_keyboard_scroll_controls(): void
    {
        [$user] = $this->posContext();

        $this->actingAs($user)
            ->get(route('app.pos'))
            ->assertOk()
            ->assertSee('pos-category-navigation', false)
            ->assertSee('Ver categorías anteriores')
            ->assertSee('Ver más categorías')
            ->assertSee('handleCategoryWheel', false)
            ->assertSee('scrollCategories', false);
    }

    public function test_pos_alpine_root_state_is_not_rendered_as_visible_text(): void
    {
        [$user] = $this->posContext();
        $html = $this->actingAs($user)->get(route('app.pos'))->assertOk()->getContent();

        $dom = new \DOMDocument;
        $previousErrors = libxml_use_internal_errors(true);
        $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        $xpath = new \DOMXPath($dom);
        $root = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' pos-root ')]")->item(0);

        $this->assertNotNull($root);
        $this->assertStringContainsString('trapFocus(event, container)', $root->getAttribute('x-data'));
        $this->assertStringContainsString('[tabindex]:not([tabindex="-1"])', $root->getAttribute('x-data'));
        $this->assertStringNotContainsString('element.offsetParent !== null', $root->textContent);
    }

    public function test_kiosk_cash_on_delivery_is_visible_but_cannot_be_charged_early_in_pos(): void
    {
        [$user, $register, $terminal] = $this->posContext();
        $delivery = $this->kioskOrder(
            $register->id,
            $user->id,
            $terminal->id,
            'Domicilio contra entrega',
            'delivery',
            status: 'lista',
        );

        Livewire::actingAs($user)
            ->test(PointOfSale::class)
            ->assertSee('Delivery')
            ->assertSee('Contra entrega')
            ->call('openDeliveryPanel')
            ->assertSet('deliveryPanelLoaded', true)
            ->assertSee('Domicilio contra entrega')
            ->assertDontSee('Gestionar reparto')
            ->assertDontSee('Cobrar ahora');

        $this->assertDatabaseMissing('order_payments', ['order_id' => $delivery->id]);
        $this->assertDatabaseHas('orders', ['id' => $delivery->id, 'status' => 'lista']);
    }

    public function test_paying_the_last_table_note_releases_the_table(): void
    {
        [$user, $register, $terminal, $mesa] = $this->posContext();
        $first = $this->kioskOrder($register->id, $user->id, $terminal->id, 'Primera nota', 'dine_in', $mesa->id, 'lista');
        $second = $this->kioskOrder($register->id, $user->id, $terminal->id, 'Última nota', 'dine_in', $mesa->id, 'lista');

        $this->actingAs($user);
        $component = Livewire::test(PointOfSale::class);

        $this->payOrder($component, $first->id);
        $this->assertDatabaseHas('mesas', ['id' => $mesa->id, 'status' => 'en_cuenta']);

        $this->payOrder($component, $second->id);
        $this->assertDatabaseHas('mesas', ['id' => $mesa->id, 'status' => 'disponible']);
    }

    public function test_table_checkout_requires_an_added_payment_and_explicit_cash_received(): void
    {
        [$user, $register, $terminal, $mesa] = $this->posContext();
        $order = $this->kioskOrder(
            $register->id,
            $user->id,
            $terminal->id,
            'Cliente pago explícito',
            'dine_in',
            $mesa->id,
            'lista',
        );

        $pos = Livewire::actingAs($user)
            ->test(PointOfSale::class)
            ->call('openMesaPayModal', $mesa->id)
            ->assertSee('Monto a aplicar')
            ->assertSee('Efectivo recibido')
            ->assertSeeHtml('id="mesa-pay-amount" type="text"')
            ->assertDontSeeHtml('data-ui="xui-1p3z5bq"')
            ->call('confirmMesaPayment')
            ->assertHasErrors('mesaPayments')
            ->assertSet('showMesaPayModal', true)
            ->assertSet('mesaPayments', []);

        $this->assertDatabaseMissing('order_payments', ['order_id' => $order->id]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'lista']);

        $pos->set('mesaPayments', [['method' => 'cash', 'amount' => 100]])
            ->call('confirmMesaPayment')
            ->assertHasErrors('mesaPayments')
            ->set('mesaPayments', [])
            ->set('mesaPayAmount', '100')
            ->call('addMesaPayment')
            ->assertHasErrors('mesaPayReceived')
            ->assertSet('mesaPayments', [])
            ->set('mesaPayReceived', '100')
            ->call('addMesaPayment')
            ->assertHasNoErrors()
            ->assertCount('mesaPayments', 1)
            ->call('confirmMesaPayment');

        $this->assertDatabaseHas('order_payments', [
            'order_id' => $order->id,
            'method' => 'efectivo',
            'amount' => 100,
            'received_amount' => 100,
        ]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pagada']);
    }

    public function test_kiosk_table_items_are_available_in_the_split_after_kitchen_marks_them_ready(): void
    {
        [$user, $register, $terminal, $mesa] = $this->posContext();
        $order = $this->kioskOrder($register->id, $user->id, $terminal->id, 'Cliente mesa', 'dine_in', $mesa->id, 'lista');
        OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Hamburguesa kiosko',
            'product_price' => 120,
            'quantity' => 1,
            'subtotal' => 120,
        ]);

        Livewire::actingAs($user)
            ->test(SplitCuenta::class, ['mesa' => $mesa])
            ->assertSee('Hamburguesa kiosko');
    }

    public function test_confirmed_split_is_persisted_and_exposed_in_pos_as_individual_accounts(): void
    {
        [$user, $register, $terminal, $mesa] = $this->posContext();
        $order = $this->kioskOrder($register->id, $user->id, $terminal->id, 'Cliente split', 'dine_in', $mesa->id, 'lista');
        $first = OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Pasta grande',
            'product_price' => 70,
            'quantity' => 1,
            'subtotal' => 70,
        ]);
        $second = OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Hamburguesa',
            'product_price' => 30,
            'quantity' => 1,
            'subtotal' => 30,
        ]);

        $this->actingAs($user);
        Livewire::test(SplitCuenta::class, ['mesa' => $mesa])
            ->call('assignItem', $first->id, 0)
            ->call('assignItem', $second->id, 1)
            ->call('confirm');

        $this->assertDatabaseHas('mesa_splits', ['mesa_id' => $mesa->id, 'status' => 'pendiente']);
        $split = MesaSplit::where('mesa_id', $mesa->id)->latest('id')->firstOrFail();
        $pos = Livewire::test(PointOfSale::class)
            ->call('openTablesBilling')
            ->assertSee('Cobrar cuenta dividida')
            ->assertSee('Cuenta 1')
            ->assertSee('Cuenta 2')
            ->assertSee('Cobrar')
            ->call('openMesaSplitPayModal', $split->id, 0)
            ->assertSet('showMesaPayModal', true)
            ->assertSet('mesaSplitId', $split->id)
            ->set('mesaPayAmount', '70')
            ->set('mesaPayReceived', '70')
            ->call('addMesaPayment')
            ->call('confirmMesaPayment')
            ->assertDispatched('pos-reprint-show', fn ($event, $params) => str_contains($params['html_cliente'] ?? '', 'Pasta grande')
                && str_contains($params['html_cliente'] ?? '', 'Cuenta 1'));

        $this->assertDatabaseHas('mesa_splits', ['id' => $split->id, 'status' => 'parcial']);
        $this->assertDatabaseHas('order_payments', ['order_id' => $order->id, 'amount' => 70]);

        $pos->call('openMesaSplitPayModal', $split->id, 1)
            ->set('mesaPayAmount', '30')
            ->set('mesaPayReceived', '30')
            ->call('addMesaPayment')
            ->call('confirmMesaPayment')
            ->assertDispatched('pos-reprint-show', fn ($event, $params) => str_contains($params['html_cliente'] ?? '', 'Hamburguesa')
                && str_contains($params['html_cliente'] ?? '', 'Cuenta 2'))
            ->assertDispatched('mesa-payment-completed', mesaId: $mesa->id, released: true)
            ->assertDontSee('Mesa '.$mesa->number);

        $this->assertDatabaseHas('mesa_splits', ['id' => $split->id, 'status' => 'completado']);
        $this->assertDatabaseHas('mesas', ['id' => $mesa->id, 'status' => 'disponible']);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pagada']);
        $this->assertDatabaseMissing('mesa_splits', ['mesa_id' => $mesa->id, 'status' => 'pendiente']);
        $this->assertDatabaseMissing('mesa_splits', ['mesa_id' => $mesa->id, 'status' => 'parcial']);

        // Un split histórico inconsistente nunca debe volver a mostrar una
        // mesa que ya fue liberada y cuyas órdenes están pagadas.
        $split->update(['status' => 'pendiente']);
        Livewire::test(PointOfSale::class)
            ->call('openTablesBilling')
            ->assertDontSee('Mesa '.$mesa->number)
            ->assertSee('No hay servicios en esta etapa');
    }

    public function test_reopening_an_unpaid_split_keeps_orders_and_includes_new_items_on_the_next_split(): void
    {
        [$user, $register, $terminal, $mesa] = $this->posContext();
        $order = $this->kioskOrder($register->id, $user->id, $terminal->id, 'Cuenta reabierta', 'dine_in', $mesa->id, 'lista');
        $oldItem = OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Pedido antes de reabrir',
            'product_price' => 60,
            'quantity' => 1,
            'subtotal' => 60,
        ]);

        $this->actingAs($user);
        Livewire::test(SplitCuenta::class, ['mesa' => $mesa])
            ->call('assignItem', $oldItem->id, 0)
            ->call('confirm');

        $split = MesaSplit::where('mesa_id', $mesa->id)->latest('id')->firstOrFail();
        $serviceId = $split->mesa_service_id;

        Livewire::test(PointOfSale::class)
            ->call('reopenMesa', $mesa->id)
            ->assertDispatched('notify');

        $this->assertSame('ocupada', $mesa->fresh()->status);
        $this->assertDatabaseMissing('mesa_splits', ['id' => $split->id]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'lista', 'mesa_service_id' => $serviceId]);

        $newOrder = Order::create([
            'cash_register_id' => $register->id,
            'mesa_id' => $mesa->id,
            'mesa_service_id' => $serviceId,
            'served_by' => $user->id,
            'type' => 'mesa',
            'status' => 'pendiente',
            'subtotal' => 40,
            'total' => 40,
        ]);
        OrderItem::create([
            'order_id' => $newOrder->id,
            'product_name' => 'Pedido después de reabrir',
            'product_price' => 40,
            'quantity' => 1,
            'subtotal' => 40,
        ]);

        Livewire::test(GestionMesas::class)
            ->call('closeMesa', $mesa->id, true)
            ->assertRedirect(route('app.mesas.split', $mesa));

        Livewire::test(SplitCuenta::class, ['mesa' => $mesa])
            ->assertSee('Pedido antes de reabrir')
            ->assertSee('Pedido después de reabrir');
    }

    public function test_partial_split_cannot_be_reopened_or_edited_after_a_payment(): void
    {
        [$user, $register, $terminal, $mesa] = $this->posContext();
        $order = $this->kioskOrder($register->id, $user->id, $terminal->id, 'Cuenta parcial', 'dine_in', $mesa->id, 'lista');
        $first = OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Cuenta ya pagada',
            'product_price' => 70,
            'quantity' => 1,
            'subtotal' => 70,
        ]);
        $second = OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Cuenta pendiente',
            'product_price' => 30,
            'quantity' => 1,
            'subtotal' => 30,
        ]);

        $this->actingAs($user);
        Livewire::test(SplitCuenta::class, ['mesa' => $mesa])
            ->call('assignItem', $first->id, 0)
            ->call('assignItem', $second->id, 1)
            ->call('confirm');
        $split = MesaSplit::where('mesa_id', $mesa->id)->latest('id')->firstOrFail();

        Livewire::test(PointOfSale::class)
            ->call('openMesaSplitPayModal', $split->id, 0)
            ->set('mesaPayAmount', '70')
            ->set('mesaPayReceived', '70')
            ->call('addMesaPayment')
            ->call('confirmMesaPayment')
            ->call('reopenMesa', $mesa->id)
            ->assertDispatched('notify');

        $this->assertSame('en_cuenta', $mesa->fresh()->status);
        $this->assertDatabaseHas('mesa_splits', ['id' => $split->id, 'status' => 'parcial']);

        Livewire::test(SplitCuenta::class, ['mesa' => $mesa])
            ->call('requestCancelConfirm')
            ->call('cancelConfirm')
            ->assertHasErrors('split');

        $this->assertDatabaseHas('mesa_splits', ['id' => $split->id, 'status' => 'parcial']);
    }

    public function test_zero_balance_split_does_not_repeat_the_original_order_total(): void
    {
        [$user, $register, $terminal, $mesa] = $this->posContext();
        $order = $this->kioskOrder(
            $register->id,
            $user->id,
            $terminal->id,
            'Cliente cuenta cero',
            'dine_in',
            $mesa->id,
            'lista',
        );
        $order->update(['subtotal' => 375, 'total' => 375]);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Consumo dividido',
            'product_price' => 125,
            'quantity' => 3,
            'subtotal' => 375,
        ]);

        $split = MesaSplit::create([
            'mesa_id' => $mesa->id,
            'mesa_service_id' => $order->mesa_service_id,
            'created_by' => $user->id,
            'split_data' => [
                ['label' => 'Cuenta 1', 'items' => [['order_item_id' => $item->id]], 'total' => 200, 'paid' => true],
                ['label' => 'Cuenta 2', 'items' => [['order_item_id' => $item->id]], 'total' => 175, 'paid' => true],
                ['label' => 'Cuenta 3', 'items' => [], 'total' => 0, 'paid' => false],
            ],
            'status' => 'parcial',
            'total' => 375,
        ]);
        OrderPayment::create(['order_id' => $order->id, 'method' => 'efectivo', 'amount' => 200]);
        OrderPayment::create(['order_id' => $order->id, 'method' => 'tarjeta', 'amount' => 175]);

        $pos = Livewire::actingAs($user)
            ->test(PointOfSale::class)
            ->call('openTablesBilling')
            ->assertSee('$0.00')
            ->assertSee('1 subcuenta pendiente')
            ->assertSee('Subcuenta restante sin consumo')
            ->assertDontSee('$375.00')
            ->assertDontSee('Orden '.$order->display_folio);

        $pos->call('discardEmptyMesaAccount', $mesa->id)
            ->assertDispatched('mesa-payment-completed', mesaId: $mesa->id, released: true)
            ->assertDispatched('notify');

        $closedSplit = $split->fresh();
        $this->assertSame('completado', $closedSplit->status);
        $this->assertTrue($closedSplit->split_data[2]['paid']);
        $this->assertTrue($closedSplit->split_data[2]['discarded']);
        $this->assertSame($user->id, $closedSplit->split_data[2]['discarded_by']);
        $this->assertNotEmpty($closedSplit->split_data[2]['discarded_at']);
        $this->assertDatabaseHas('mesas', ['id' => $mesa->id, 'status' => 'disponible']);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pagada']);
        $this->assertSame(2, OrderPayment::where('order_id', $order->id)->count());
        $this->assertSame(375.0, (float) OrderPayment::where('order_id', $order->id)->sum('amount'));
    }

    public function test_empty_table_sent_to_checkout_is_visible_and_can_be_cancelled_without_a_sale(): void
    {
        [$user, $register, , $mesa] = $this->posContext();
        $service = app(MesaServiceManager::class)->resolveOrCreate($mesa, $register, $user->id);
        $service->update(['status' => 'en_cuenta', 'in_account_at' => now()]);

        $pos = Livewire::actingAs($user)
            ->test(PointOfSale::class)
            ->call('openTablesBilling')
            ->assertSee('Mesa '.$mesa->number)
            ->assertSee('Servicio sin consumo')
            ->assertSee('Cancelar servicio');

        $pos->call('discardEmptyMesaAccount', $mesa->id)
            ->assertDispatched('notify');

        $this->assertDatabaseHas('mesa_services', [
            'id' => $service->id,
            'status' => 'liberada',
            'total_snapshot' => 0,
        ]);
        $this->assertDatabaseHas('mesas', ['id' => $mesa->id, 'status' => 'disponible']);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_payments', 0);
    }

    public function test_legacy_empty_table_without_a_service_can_be_recovered_from_checkout(): void
    {
        [$user, , , $mesa] = $this->posContext();

        Livewire::actingAs($user)
            ->test(PointOfSale::class)
            ->call('openTablesBilling')
            ->assertSee('Mesa '.$mesa->number)
            ->assertSee('Servicio sin consumo')
            ->call('discardEmptyMesaAccount', $mesa->id)
            ->assertDispatched('notify');

        $this->assertDatabaseHas('mesas', ['id' => $mesa->id, 'status' => 'disponible']);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_payments', 0);
    }

    public function test_saving_an_order_persists_it_and_clears_the_cart_and_session(): void
    {
        [$user] = $this->posContext();
        $product = Product::create([
            'name' => 'Pedido para retomar',
            'price' => 135,
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(PointOfSale::class)
            ->set('quotationName', 'Guardado de prueba')
            ->set('cart', [[
                'cart_id' => 'saved-order-test',
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_price' => 135,
                'quantity' => 1,
                'subtotal' => 135,
                'notes' => '',
                'addons' => [],
                'ingredients' => [],
            ]])
            ->call('saveQuotation')
            ->assertSet('cart', [])
            ->assertSet('quotationName', '')
            ->assertSet('showSaveQuotationModal', false)
            ->assertDispatched('notify');

        $quotation = Quotation::where('name', 'Guardado de prueba')->firstOrFail();
        $this->assertSame('135.00', $quotation->total);
        $this->assertDatabaseHas('quotation_items', [
            'quotation_id' => $quotation->id,
            'product_name' => 'Pedido para retomar',
            'subtotal' => 135,
        ]);
        $this->assertSame([], session('pos_cart_'.$user->id));
    }

    public function test_saved_order_restores_the_complete_checkout_and_uses_it_when_charged(): void
    {
        [$user] = $this->posContext();
        $product = Product::create([
            'name' => 'Latte de borrador',
            'price' => 135,
            'is_active' => true,
        ]);

        $component = Livewire::actingAs($user)
            ->test(PointOfSale::class)
            ->set('quotationName', 'Delivery incompleto')
            ->set('orderType', 'delivery')
            ->set('deliveryMethod', 'transfer')
            ->set('orderNotes', 'Sin popote')
            ->set('customerName', 'Cliente borrador')
            ->set('customerPhone', '9991112233')
            ->set('customerAddress', 'Calle 10 #20')
            ->set('customerNeighborhood', 'Centro')
            ->set('customerReferences', '')
            ->set('payMethod', 'transfer')
            ->set('payAmount', '135.00')
            ->set('payTransferRef', 'TRX-BORRADOR')
            ->set('cart', [[
                'cart_id' => 'complete-draft-test',
                'product_id' => $product->id,
                'promotion_id' => null,
                'product_name' => $product->name,
                'product_price' => 135,
                'product_image' => null,
                'quantity' => 1,
                'unit_extra' => 0,
                'unit_total' => 135,
                'subtotal' => 135,
                'promotion_discount' => 0,
                'notes' => '',
                'addons' => [],
                'ingredients' => [],
                'promotion_selections' => [],
                'promotion_rule_snapshot' => null,
                'auto_promotion_applied' => false,
            ]])
            ->call('saveQuotation')
            ->assertSet('cart', [])
            ->assertSet('orderType', 'ventanilla')
            ->assertSet('customerAddress', '')
            ->assertSet('payTransferRef', '');

        $quotation = Quotation::where('name', 'Delivery incompleto')->firstOrFail();
        $this->assertSame('delivery', $quotation->order_type);
        $this->assertSame(1, $quotation->draft_version);
        $this->assertSame('Calle 10 #20', data_get($quotation->checkout_state, 'customer.address'));
        $this->assertSame('', data_get($quotation->checkout_state, 'customer.references'));
        $this->assertSame('TRX-BORRADOR', data_get($quotation->checkout_state, 'payment.transfer_reference'));

        $component->call('loadQuotation', $quotation->id)
            ->assertSet('orderType', 'delivery')
            ->assertSet('deliveryMethod', 'transfer')
            ->assertSet('orderNotes', 'Sin popote')
            ->assertSet('customerName', 'Cliente borrador')
            ->assertSet('customerAddress', 'Calle 10 #20')
            ->assertSet('customerNeighborhood', 'Centro')
            ->assertSet('customerReferences', '')
            ->assertSet('payMethod', 'transfer')
            ->assertSet('payAmount', '135.00')
            ->assertSet('payTransferRef', 'TRX-BORRADOR')
            ->assertSet('cart.0.product_name', 'Latte de borrador')
            ->call('openCheckoutModal')
            ->assertSet('orderType', 'delivery')
            ->assertSet('deliveryMethod', 'transfer')
            ->assertSet('payTransferRef', 'TRX-BORRADOR')
            ->call('addPayment')
            ->call('submitOrder')
            ->assertHasNoErrors();

        $order = Order::latest('id')->firstOrFail();
        $this->assertSame('delivery', $order->type);
        $this->assertSame('transferencia', $order->delivery_method);
        $this->assertSame('Centro', $order->customer_neighborhood);
        $this->assertSame('', (string) $order->customer_references);
        $this->assertSame('Sin popote', $order->notes);
        $this->assertDatabaseHas('order_payments', [
            'order_id' => $order->id,
            'method' => 'transferencia',
            'amount' => 135,
            'transfer_reference' => 'TRX-BORRADOR',
        ]);
        $this->assertDatabaseMissing('quotations', ['id' => $quotation->id]);
    }

    public function test_delivery_transfer_is_registered_when_confirming_without_a_second_payment_step(): void
    {
        [$user] = $this->posContext();
        $product = Product::create([
            'name' => 'Delivery por transferencia',
            'price' => 30,
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(PointOfSale::class)
            ->set('orderType', 'delivery')
            ->set('deliveryMethod', 'transfer')
            ->set('customerName', 'Cliente transferencia')
            ->set('customerPhone', '9991112233')
            ->set('customerAddress', 'Calle 20 #10')
            ->set('customerNeighborhood', 'Centro')
            ->set('payTransferRef', 'TRX-DELIVERY-30')
            ->set('cart', [[
                'cart_id' => 'delivery-transfer-test',
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_price' => 30,
                'quantity' => 1,
                'subtotal' => 30,
                'notes' => '',
                'addons' => [],
                'ingredients' => [],
            ]])
            ->call('openCheckoutModal')
            ->assertSee('Se registrará automáticamente al confirmar el pedido.')
            ->assertDontSee('Registrar pago')
            ->call('submitOrder')
            ->assertHasNoErrors()
            ->assertSet('showOrderSuccess', true);

        $order = Order::query()->latest('id')->firstOrFail();
        $this->assertSame('delivery', $order->type);
        $this->assertSame('transferencia', $order->delivery_method);
        $this->assertDatabaseHas('order_payments', [
            'order_id' => $order->id,
            'method' => 'transferencia',
            'amount' => 30,
            'transfer_reference' => 'TRX-DELIVERY-30',
        ]);
    }

    public function test_pos_layout_does_not_load_the_admin_menu_controller(): void
    {
        [$user] = $this->posContext();

        $this->actingAs($user)
            ->get(route('app.pos'))
            ->assertOk()
            ->assertDontSee('assets/vendor/js/menu.js', false)
            ->assertDontSee('assets/js/main.js', false)
            ->assertDontSee('assets/js/theme.js', false)
            ->assertDontSee('assets/css/dark-theme.css', false)
            ->assertSee('<html lang="es" class="light-style"', false)
            ->assertSee('#pos-loading-screen', false)
            ->assertSee('requestAnimationFrame(hidePosLoader)', false)
            ->assertSee('pos-floating-panel', false)
            ->assertSee('pos-tables-accordion', false)
            ->assertSee('pos-reprint-results-shell', false)
            ->assertSee('class="pos-reprint-results"', false)
            ->assertSee('Resultados disponibles para reimpresión')
            ->assertSee('Guardados')
            ->assertSee('Mesas y comandas')
            ->assertSeeHtml('wire:loading.remove');
    }

    public function test_pos_header_uses_the_configured_restaurant_logo(): void
    {
        [$user] = $this->posContext();
        BusinessSetting::current()->update([
            'business_name' => 'Restaurante Central',
            'logo_path' => 'business/restaurante-central.png',
        ]);

        $this->actingAs($user)
            ->get(route('app.pos'))
            ->assertOk()
            ->assertSee('class="pos-logo-img"', false)
            ->assertSee('/storage/business/restaurante-central.png', false)
            ->assertSee('Logo de Restaurante Central', false);
    }

    public function test_pos_only_exposes_orders_from_the_current_open_cash_register(): void
    {
        [$user, $currentRegister, $terminal, $mesa] = $this->posContext();
        $previousRegister = CashRegister::create([
            'name' => 'Caja anterior',
            'opened_by' => $user->id,
            'initial_amount' => 0,
            'opened_at' => now()->subHour(),
            'closed_at' => now()->subMinutes(30),
            'is_open' => false,
        ]);

        $this->kioskOrder($previousRegister->id, $user->id, $terminal->id, 'Pedido caja anterior', 'takeaway');
        $this->kioskOrder($currentRegister->id, $user->id, $terminal->id, 'Pedido caja vigente', 'takeaway');

        $oldMesa = Mesa::create([
            'area_id' => $mesa->area_id,
            'number' => 99,
            'capacity' => 2,
            'status' => 'en_cuenta',
        ]);
        $this->kioskOrder($previousRegister->id, $user->id, $terminal->id, 'Mesa de caja anterior', 'dine_in', $oldMesa->id, 'lista');

        $this->actingAs($user);
        Livewire::test(PointOfSale::class)
            ->assertSee('Pedido caja vigente')
            ->call('openTablesBilling')
            ->assertDontSee('Pedido caja anterior')
            ->assertDontSee('Mesa de caja anterior')
            ->assertDontSee('Mesa 99');
    }

    public function test_pos_prints_counter_orders_with_the_counter_ticket_maker_template(): void
    {
        [$user, $register, $terminal] = $this->posContext();
        $order = $this->kioskOrder($register->id, $user->id, $terminal->id, 'Cliente mostrador', 'takeaway', status: 'lista');
        OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Orden mostrador',
            'product_price' => 100,
            'quantity' => 1,
            'subtotal' => 100,
        ]);

        TicketTemplate::current('counter')->update([
            'paper_width_mm' => 58,
            'font_size' => 15,
            'margin_mm' => 2,
            'footer_text' => 'VENTANILLA DESDE TICKET MAKER',
        ]);
        TicketTemplate::current('customer')->update(['footer_text' => 'PLANTILLA CLIENTE INCORRECTA']);

        $this->actingAs($user);

        Livewire::test(PointOfSale::class)
            ->call('openPickupPayModal', $order->id)
            ->set('pickupPayAmount', '100')
            ->set('pickupPayReceived', '100')
            ->call('addPickupPayment')
            ->call('confirmPickupPayment')
            ->assertDispatched('pos-reprint-show', fn ($event, $params) => str_contains($params['html_cliente'] ?? '', 'VENTANILLA DESDE TICKET MAKER')
                && str_contains($params['html_cliente'] ?? '', 'ticket-info-font-arial ticket-info-size-12')
                && str_contains($params['html_cliente'] ?? '', 'ticket-paper-58 ticket-margin-2')
                && str_contains($params['html_cliente'] ?? '', 'ticket-items-size-15')
                && ! str_contains($params['html_cliente'] ?? '', 'PLANTILLA CLIENTE INCORRECTA')
                && ! str_contains($params['html_cliente'] ?? '', 'window.print()'));
    }

    public function test_finishing_a_direct_sale_opens_the_ticket_maker_document_without_double_printing_iframes(): void
    {
        [$user] = $this->posContext();
        $product = Product::create([
            'name' => 'Taco directo',
            'price' => 100,
            'is_active' => true,
        ]);

        TicketTemplate::current('counter')->update([
            'footer_text' => 'TICKET FINAL DESDE MAKER',
        ]);

        $this->actingAs($user);

        Livewire::test(PointOfSale::class)
            ->set('orderType', 'ventanilla')
            ->set('cart', [[
                'cart_id' => 'direct-sale-ticket-test',
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_price' => 100,
                'quantity' => 1,
                'subtotal' => 100,
                'notes' => '',
                'addons' => [],
                'ingredients' => [],
            ]])
            ->set('payments', [[
                'method' => 'cash',
                'amount' => 100,
                'cash_received' => 100,
                'cash_change' => 0,
            ]])
            ->call('submitOrder')
            ->assertSet('showOrderSuccess', true)
            ->assertDispatched('pos-reprint-show', fn ($event, $params) => str_contains($params['html_cliente'] ?? '', 'TICKET FINAL DESDE MAKER')
                && ! str_contains($params['html_cliente'] ?? '', 'window.print()')
                && ! str_contains($params['html_cocina'] ?? '', 'window.print()'));
    }

    private function posContext(): array
    {
        $user = User::factory()->create();
        $user->assignRole('owner');
        $register = CashRegister::create([
            'name' => 'Caja principal',
            'opened_by' => $user->id,
            'initial_amount' => 0,
            'opened_at' => now(),
            'is_open' => true,
        ]);
        $terminal = KioskTerminal::create([
            'name' => 'Kiosco principal',
            'token_hash' => hash('sha256', Str::random(64)),
            'user_id' => $user->id,
            'is_active' => true,
        ]);
        $area = Area::create(['name' => 'Salón']);
        $mesa = Mesa::create([
            'area_id' => $area->id,
            'number' => 7,
            'capacity' => 4,
            'status' => 'en_cuenta',
        ]);

        return [$user, $register, $terminal, $mesa];
    }

    private function kioskOrder(
        int $registerId,
        int $userId,
        int $terminalId,
        string $customerName,
        string $fulfillment,
        ?int $mesaId = null,
        string $status = 'pendiente',
    ): Order {
        return Order::create([
            'cash_register_id' => $registerId,
            'kiosk_terminal_id' => $terminalId,
            'public_token' => Str::random(64),
            'customer_name' => $customerName,
            'served_by' => $userId,
            'type' => $mesaId ? 'mesa' : ($fulfillment === 'delivery' ? 'delivery' : 'ventanilla'),
            'mesa_id' => $mesaId,
            'delivery_method' => $fulfillment === 'delivery' ? 'contra_entrega' : null,
            'source' => 'kiosk',
            'fulfillment' => $fulfillment,
            'status' => $status,
            'subtotal' => 100,
            'total' => 100,
        ]);
    }

    private function payOrder($component, int $orderId): void
    {
        $component
            ->call('openPickupPayModal', $orderId)
            ->set('pickupPayAmount', '100')
            ->set('pickupPayReceived', '100')
            ->call('addPickupPayment')
            ->call('confirmPickupPayment');
    }
}
