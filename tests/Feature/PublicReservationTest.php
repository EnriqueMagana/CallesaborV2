<?php

namespace Tests\Feature;

use App\Livewire\PublicReservation;
use App\Models\BusinessSetting;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PublicReservationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_public_home_and_menu_are_separate_experiences(): void
    {
        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('Reservar una mesa')
            ->assertSee('aria-haspopup="dialog"', false)
            ->assertDontSee('class="reservation-modal"', false);
        $this->get(route('public.menu'))->assertOk()->assertSee('Buscar platillo');
    }

    public function test_customer_can_register_a_pending_reservation_during_business_hours(): void
    {
        Carbon::setTestNow('2026-08-03 10:00:00');
        $hours = BusinessSetting::DEFAULT_HOURS;
        $hours[0] = ['key' => 'monday', 'label' => 'Lunes', 'enabled' => true, 'opens' => '12:00', 'closes' => '18:00'];
        BusinessSetting::current()->update(['business_hours' => $hours]);

        Livewire::test(PublicReservation::class)
            ->call('openModal')
            ->assertSet('isOpen', true)
            ->assertSee('Elige tu mesa')
            ->set('selectedDate', '2026-08-03')
            ->call('continueToTime')
            ->assertSet('step', 2)
            ->set('selectedTime', '12:30')
            ->set('guests', 4)
            ->call('continueToDetails')
            ->assertSet('step', 3)
            ->set('customerName', 'Ana Rivera')
            ->set('customerPhone', '5512345678')
            ->set('customerEmail', 'ana@example.com')
            ->set('occasion', 'Cumpleanos')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('confirmationCode', fn ($value) => is_string($value) && strlen($value) === 8);

        $this->assertDatabaseHas('reservations', [
            'customer_name' => 'Ana Rivera',
            'customer_phone' => '5512345678',
            'guests' => 4,
            'status' => 'pendiente',
            'source' => 'public',
            'created_by' => null,
        ]);

        $this->assertSame('12:30 PM · Ana Rivera · 4 personas', Reservation::first()->toCalendarEvent()['title']);
    }

    public function test_server_rejects_a_manipulated_time_outside_business_hours(): void
    {
        Carbon::setTestNow('2026-08-03 10:00:00');
        $hours = BusinessSetting::DEFAULT_HOURS;
        $hours[0] = ['key' => 'monday', 'label' => 'Lunes', 'enabled' => true, 'opens' => '12:00', 'closes' => '18:00'];
        BusinessSetting::current()->update(['business_hours' => $hours]);

        Livewire::test(PublicReservation::class)
            ->set('selectedDate', '2026-08-03')
            ->call('continueToTime')
            ->set('selectedTime', '23:30')
            ->call('continueToDetails')
            ->assertHasErrors(['selectedTime'])
            ->assertSet('step', 2);

        $this->assertSame(0, Reservation::count());
    }

    public function test_admin_redirects_guests_to_login_and_authenticated_users_to_app(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/admin')->assertRedirect(route('app.dashboard'));
    }

    public function test_complete_address_generates_a_google_maps_search_link(): void
    {
        $business = BusinessSetting::current();
        $business->update([
            'address' => 'C. 33 185A, Ticul, 97860 Ticul, Yuc.',
            'city' => 'Ticul',
            'state' => 'Yucatán',
            'maps_url' => null,
        ]);

        $this->assertSame('C. 33 185A, Ticul, 97860 Ticul, Yuc.', $business->fresh()->full_address);
        $this->assertSame(
            'https://www.google.com/maps/search/?api=1&query=C.%2033%20185A%2C%20Ticul%2C%2097860%20Ticul%2C%20Yuc.',
            $business->fresh()->map_link,
        );

        $this->get(route('public.contact'))
            ->assertOk()
            ->assertSee($business->fresh()->map_link);
    }
}
