<?php

namespace Tests\Feature;

use App\Livewire\Caja\CorteDeCaja;
use App\Models\CashRegister;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CashCutDesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_see_the_redesigned_cash_cut(): void
    {
        $user = User::factory()->create();
        CashRegister::create([
            'name' => 'Caja principal',
            'opened_by' => $user->id,
            'initial_amount' => 500,
            'opened_at' => now(),
            'is_open' => true,
        ]);

        $this->actingAs($user)
            ->get(route('app.caja.corte'))
            ->assertOk()
            ->assertSee('Conciliación de efectivo')
            ->assertSee('Ventas por área')
            ->assertSee('Efectivo esperado');
    }

    public function test_cash_cut_confirmation_keeps_the_expected_summary(): void
    {
        $user = User::factory()->create();
        CashRegister::create([
            'name' => 'Caja principal',
            'opened_by' => $user->id,
            'initial_amount' => 500,
            'opened_at' => now(),
            'is_open' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(CorteDeCaja::class)
            ->set('declaredCash', '500.00')
            ->call('confirmCut')
            ->assertHasNoErrors()
            ->assertSet('showConfirm', true)
            ->assertSee('Confirmación final')
            ->assertSee('Cuadra exacto');
    }
}
