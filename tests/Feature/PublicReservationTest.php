<?php

namespace Tests\Feature;

use App\Livewire\PublicReservation;
use App\Models\Area;
use App\Models\BusinessSetting;
use App\Models\Mesa;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationAvailabilityService;
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
            ->assertSee('class="reservation-modal"', false)
            ->assertSee('x-cloak', false)
            ->assertDontSee('wire:click="selectDate', false)
            ->assertDontSee('wire:model.blur', false);
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

    public function test_capacity_uses_tables_and_seats_and_can_register_a_waitlist_request(): void
    {
        Carbon::setTestNow('2026-08-03 10:00:00');
        $hours = BusinessSetting::DEFAULT_HOURS;
        $hours[0] = ['key' => 'monday', 'label' => 'Lunes', 'enabled' => true, 'opens' => '12:00', 'closes' => '18:00'];
        $business = BusinessSetting::current();
        $business->update(['business_hours' => $hours]);

        $area = Area::create(['name' => 'Salón']);
        Mesa::create(['area_id' => $area->id, 'number' => 1, 'capacity' => 4, 'status' => 'disponible']);
        Mesa::create(['area_id' => $area->id, 'number' => 2, 'capacity' => 2, 'status' => 'disponible']);
        Reservation::create([
            'customer_name' => 'Reserva existente',
            'customer_phone' => '5500000000',
            'guests' => 4,
            'reserved_at' => '2026-08-03 12:30:00',
            'status' => 'confirmada',
            'source' => 'admin',
        ]);

        $availability = app(ReservationAvailabilityService::class)->forMoment(
            $business,
            Carbon::parse('2026-08-03 12:30:00'),
            3,
        );

        $this->assertTrue($availability['enforced']);
        $this->assertFalse($availability['can_fit']);
        $this->assertSame(2, $availability['remaining_seats']);
        $this->assertSame(1, $availability['remaining_tables']);

        $request = fn () => Livewire::test(PublicReservation::class)
            ->set('selectedDate', '2026-08-03')
            ->set('selectedTime', '12:30')
            ->set('guests', 3)
            ->set('customerName', 'Cliente en espera')
            ->set('customerPhone', '5512345678');

        $request()->call('submit')->assertHasErrors(['selectedTime']);
        $this->assertSame(1, Reservation::count());

        $request()
            ->set('acceptWaitlist', true)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('confirmationCode', fn ($value) => is_string($value));

        $this->assertDatabaseHas('reservations', [
            'customer_name' => 'Cliente en espera',
            'guests' => 3,
            'is_waitlist' => true,
            'status' => 'pendiente',
        ]);
        $waitlist = Reservation::where('customer_name', 'Cliente en espera')->firstOrFail();
        $this->assertStringStartsWith('EN ESPERA · 12:30 PM', $waitlist->toCalendarEvent()['title']);

        $afterWaitlist = app(ReservationAvailabilityService::class)->forMoment(
            $business,
            Carbon::parse('2026-08-03 12:30:00'),
            2,
        );
        $this->assertTrue($afterWaitlist['can_fit']);

        Reservation::create([
            'customer_name' => 'Mesa pequeña',
            'customer_phone' => '5500000001',
            'guests' => 2,
            'reserved_at' => '2026-08-03 16:00:00',
            'status' => 'confirmada',
            'source' => 'admin',
        ]);
        $optimizedAllocation = app(ReservationAvailabilityService::class)->forMoment(
            $business,
            Carbon::parse('2026-08-03 16:00:00'),
            4,
        );
        $this->assertTrue($optimizedAllocation['can_fit']);
        $this->assertSame(4, $optimizedAllocation['remaining_seats']);
        $this->assertSame(1, $optimizedAllocation['remaining_tables']);
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
