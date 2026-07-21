<?php

namespace Tests\Feature;

use App\Livewire\Orders\OrderList;
use App\Models\CashRegister;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrdersKioskFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_kiosk_orders_have_their_own_channel_and_do_not_mix_with_pos_orders(): void
    {
        $user = User::factory()->create();
        $register = CashRegister::create([
            'name' => 'Caja principal',
            'opened_by' => $user->id,
            'initial_amount' => 0,
            'opened_at' => now(),
            'is_open' => true,
        ]);

        $this->createOrder($register->id, $user->id, 'Cliente Kiosco', 'kiosk');
        $this->createOrder($register->id, $user->id, 'Cliente POS', 'pos');

        $this->actingAs($user);

        $component = Livewire::test(OrderList::class)
            ->assertSee('Kiosco')
            ->call('filterByChannel', 'kiosk')
            ->assertSee('Cliente Kiosco')
            ->assertDontSee('Cliente POS')
            ->assertSee('Para llevar');

        $component
            ->call('filterByChannel', 'ventanilla')
            ->assertSee('Cliente POS')
            ->assertDontSee('Cliente Kiosco');
    }

    public function test_order_list_only_shows_orders_from_the_open_cash_register(): void
    {
        $user = User::factory()->create();
        $open = CashRegister::create([
            'name' => 'Caja abierta', 'opened_by' => $user->id, 'initial_amount' => 0,
            'opened_at' => now(), 'is_open' => true,
        ]);
        $closed = CashRegister::create([
            'name' => 'Caja cerrada', 'opened_by' => $user->id, 'initial_amount' => 0,
            'opened_at' => now()->subDay(), 'closed_at' => now()->subHours(20), 'is_open' => false,
        ]);

        $this->createOrder($open->id, $user->id, 'Cliente activo', 'pos');
        $this->createOrder($closed->id, $user->id, 'Cliente histórico', 'pos');

        $this->actingAs($user);

        Livewire::test(OrderList::class)
            ->assertSee('Cliente activo')
            ->assertDontSee('Cliente histórico')
            ->assertSee('Caja abierta');
    }

    private function createOrder(int $registerId, int $userId, string $customerName, string $source): void
    {
        Order::create([
            'cash_register_id' => $registerId,
            'customer_name' => $customerName,
            'served_by' => $userId,
            'type' => 'ventanilla',
            'source' => $source,
            'fulfillment' => 'takeaway',
            'status' => 'pendiente',
            'subtotal' => 100,
            'total' => 100,
        ]);
    }
}
