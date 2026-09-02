<?php

namespace Tests\Feature;

use App\Livewire\Orders\OrderList;
use App\Models\CashRegister;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OrderFolioSequenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_folio_continues_across_days_while_the_same_register_is_open(): void
    {
        $user = User::factory()->create();
        $register = $this->openRegister($user, 'Turno nocturno');

        $first = $this->createOrder($register, $user);
        $this->travel(1)->days();
        $second = $this->createOrder($register, $user);

        $this->assertSame(1, $first->folio);
        $this->assertSame('ORD-001', $first->display_folio);
        $this->assertSame(2, $second->folio);
        $this->assertSame('ORD-002', $second->display_folio);
        $this->assertSame(3, $register->fresh()->next_order_folio);
    }

    public function test_folio_restarts_when_a_new_register_is_opened(): void
    {
        $user = User::factory()->create();
        $firstRegister = $this->openRegister($user, 'Caja 1');
        $this->createOrder($firstRegister, $user);
        $this->createOrder($firstRegister, $user);

        $firstRegister->update([
            'is_open' => false,
            'closed_by' => $user->id,
            'closed_at' => now(),
        ]);
        $secondRegister = $this->openRegister($user, 'Caja 2');
        $firstOrderInNewRegister = $this->createOrder($secondRegister, $user);

        $this->assertSame(1, $firstOrderInNewRegister->folio);
        $this->assertSame('ORD-001', $firstOrderInNewRegister->display_folio);
        $this->assertSame(2, $secondRegister->fresh()->next_order_folio);
    }

    public function test_order_list_accepts_the_formatted_folio_as_an_exact_search(): void
    {
        $user = User::factory()->create();
        Permission::findOrCreate('ver ordenes');
        $user->givePermissionTo('ver ordenes');
        $register = $this->openRegister($user, 'Caja buscador');
        $first = $this->createOrder($register, $user);
        $second = $this->createOrder($register, $user);

        $this->actingAs($user);
        Livewire::test(OrderList::class)
            ->set('search', 'ORD-001')
            ->assertSee($first->display_folio)
            ->assertDontSee($second->display_folio);
    }

    private function openRegister(User $user, string $name): CashRegister
    {
        return CashRegister::query()->create([
            'name' => $name,
            'opened_by' => $user->id,
            'opened_at' => now(),
            'is_open' => true,
        ]);
    }

    private function createOrder(CashRegister $register, User $user): Order
    {
        return Order::query()->create([
            'cash_register_id' => $register->id,
            'served_by' => $user->id,
            'type' => 'ventanilla',
            'status' => 'pendiente',
            'subtotal' => 100,
            'total' => 100,
        ]);
    }
}
