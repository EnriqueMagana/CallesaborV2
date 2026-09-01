<?php

namespace Tests\Feature;

use App\Livewire\Mesas\GestionMesas;
use App\Livewire\Pos\PointOfSale;
use App\Models\Area;
use App\Models\CashRegister;
use App\Models\Mesa;
use App\Models\MesaAssignment;
use App\Models\MesaSplit;
use App\Models\Order;
use App\Models\TicketTemplate;
use App\Models\User;
use App\Services\MesaServiceManager;
use App\Services\ThermalTicketRenderer;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MesaTicketPaymentDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_table_checkout_and_pos_reprint_use_template_qr_and_mixed_payments(): void
    {
        [$user, $mesa, $service, $order] = $this->context(100);
        $this->configureCustomerTemplate();

        $component = Livewire::actingAs($user)
            ->test(PointOfSale::class)
            ->call('openMesaPayModal', $mesa->id)
            ->set('mesaPayMethod', 'cash')
            ->set('mesaPayAmount', '40')
            ->set('mesaPayReceived', '40')
            ->call('addMesaPayment')
            ->set('mesaPayMethod', 'card')
            ->set('mesaPayAmount', '60')
            ->set('mesaPayCard', '4242')
            ->call('addMesaPayment')
            ->call('confirmMesaPayment')
            ->assertDispatched('pos-reprint-show', fn ($event, $params) => $this->hasCompletePaymentTicket($params['html_cliente'] ?? ''));

        $this->assertDatabaseHas('order_payments', [
            'order_id' => $order->id,
            'method' => 'efectivo',
            'amount' => 40,
        ]);
        $this->assertDatabaseHas('order_payments', [
            'order_id' => $order->id,
            'method' => 'tarjeta',
            'amount' => 60,
            'card_last4' => '4242',
        ]);

        $component
            ->call('openMesaServiceHistoryTicket', $service->id)
            ->assertDispatched('pos-reprint-show', fn ($event, $params) => $this->hasCompletePaymentTicket($params['html_cliente'] ?? ''));
    }

    public function test_partial_split_persists_its_payment_snapshot_for_waiter_reprint(): void
    {
        [$user, $mesa, $service, $order, $item] = $this->context(100);
        $this->configureCustomerTemplate();

        $split = MesaSplit::create([
            'mesa_id' => $mesa->id,
            'mesa_service_id' => $service->id,
            'created_by' => $user->id,
            'status' => 'pendiente',
            'total' => 100,
            'split_data' => [
                [
                    'label' => 'Cuenta parcial',
                    'items' => [['id' => $item->id, 'name' => $item->product_name, 'qty' => 1, 'subtotal' => 60]],
                    'total' => 60,
                    'paid' => false,
                ],
                [
                    'label' => 'Cuenta pendiente',
                    'items' => [['id' => $item->id, 'name' => $item->product_name, 'qty' => 1, 'subtotal' => 40]],
                    'total' => 40,
                    'paid' => false,
                ],
            ],
        ]);

        Livewire::actingAs($user)
            ->test(PointOfSale::class)
            ->call('openMesaSplitPayModal', $split->id, 0)
            ->set('mesaPayMethod', 'cash')
            ->set('mesaPayAmount', '20')
            ->set('mesaPayReceived', '20')
            ->call('addMesaPayment')
            ->set('mesaPayMethod', 'card')
            ->set('mesaPayAmount', '40')
            ->set('mesaPayCard', '1111')
            ->call('addMesaPayment')
            ->call('confirmMesaPayment')
            ->assertDispatched('pos-reprint-show');

        $account = $split->fresh()->split_data[0];
        $this->assertTrue($account['paid']);
        $this->assertSame($user->id, $account['paid_by']);
        $this->assertSame($order->id, $account['tracking_order_id']);
        $this->assertSame(['cash', 'card'], collect($account['payments'])->pluck('method')->all());

        Livewire::actingAs($user)
            ->test(GestionMesas::class)
            ->call('openDetail', $mesa->id)
            ->assertSee('Pagada · reimpresión disponible')
            ->call('printActiveMesaAccount', $mesa->id, $split->id, 0)
            ->assertDispatched('mesa-account-ticket-preview', fn ($event, $params) => str_contains($params['html'] ?? '', 'Efectivo')
                && str_contains($params['html'] ?? '', '$20.00')
                && str_contains($params['html'] ?? '', 'Tarjeta')
                && str_contains($params['html'] ?? '', '$40.00')
                && str_contains($params['html'] ?? '', '1111')
                && str_contains($params['html'] ?? '', 'data:image/svg+xml;base64,'));
    }

    public function test_table_history_marks_removed_items_and_excludes_them_from_the_total(): void
    {
        [, , $service, $order, $removedItem] = $this->context(100);
        $removedItem->update(['is_cancelled' => true, 'cancelled_at' => now()]);
        $order->items()->create([
            'product_name' => 'Producto sustituto',
            'product_price' => 75,
            'quantity' => 1,
            'subtotal' => 75,
        ]);
        $order->update(['subtotal' => 75, 'total' => 75]);
        $service->update(['total_snapshot' => 75]);

        $html = app(ThermalTicketRenderer::class)->renderMesaService($service->fresh());

        $this->assertStringContainsString('ticket-item--cancelled', $html);
        $this->assertStringContainsString('RETIRADO', $html);
        $this->assertStringContainsString('Producto sustituto', $html);
        $this->assertStringContainsString('$75.00', $html);
    }

    private function context(float $total): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('owner');
        $register = CashRegister::create([
            'name' => 'Caja tickets',
            'opened_by' => $user->id,
            'initial_amount' => 0,
            'opened_at' => now(),
            'is_open' => true,
        ]);
        $area = Area::create(['name' => 'Salón']);
        $mesa = Mesa::create([
            'area_id' => $area->id,
            'number' => 14,
            'capacity' => 4,
            'status' => 'en_cuenta',
        ]);
        $service = app(MesaServiceManager::class)->resolveOrCreate($mesa, $register, $user->id);
        $service->update(['status' => 'en_cuenta', 'in_account_at' => now()]);
        $order = Order::create([
            'cash_register_id' => $register->id,
            'mesa_id' => $mesa->id,
            'mesa_service_id' => $service->id,
            'served_by' => $user->id,
            'type' => 'mesa',
            'status' => 'lista',
            'subtotal' => $total,
            'total' => $total,
        ]);
        $item = $order->items()->create([
            'product_name' => 'Consumo de mesa',
            'product_price' => $total,
            'quantity' => 1,
            'subtotal' => $total,
        ]);
        MesaAssignment::create([
            'mesa_id' => $mesa->id,
            'mesa_service_id' => $service->id,
            'user_id' => $user->id,
            'assigned_by' => $user->id,
            'assigned_at' => now(),
        ]);

        return [$user, $mesa, $service, $order, $item];
    }

    private function configureCustomerTemplate(): void
    {
        $template = TicketTemplate::current('customer');
        $template->update([
            'show_qr' => true,
            'footer_text' => 'PIE PERSONALIZADO DE MESA',
            'blocks' => collect($template->blocks)->map(function (array $block): array {
                if (in_array($block['key'] ?? null, ['payments', 'qr'], true)) {
                    $block['enabled'] = true;
                }

                return $block;
            })->all(),
        ]);
    }

    private function hasCompletePaymentTicket(string $html): bool
    {
        return str_contains($html, 'PIE PERSONALIZADO DE MESA')
            && str_contains($html, 'Efectivo')
            && str_contains($html, '$40.00')
            && str_contains($html, 'Tarjeta')
            && str_contains($html, '$60.00')
            && str_contains($html, '4242')
            && str_contains($html, 'data:image/svg+xml;base64,');
    }
}
