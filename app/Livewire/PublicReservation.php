<?php

namespace App\Livewire;

use App\Models\BusinessSetting;
use App\Models\Reservation;
use App\Services\ReservationAvailabilityService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

class PublicReservation extends Component
{
    public bool $isOpen = false;

    public int $step = 1;

    public string $selectedDate = '';

    public string $selectedTime = '';

    public int $guests = 2;

    public string $customerName = '';

    public string $customerPhone = '';

    public string $customerEmail = '';

    public string $occasion = '';

    public string $notes = '';

    public string $website = '';

    public bool $acceptWaitlist = false;

    public ?string $confirmationCode = null;

    public function mount(): void
    {
        $this->selectedDate = array_key_first($this->dateOptions) ?? $this->businessNow()->format('Y-m-d');
    }

    #[Computed]
    public function dateOptions(): array
    {
        $business = BusinessSetting::current();

        return collect(range(0, 20))
            ->map(fn (int $offset) => $this->businessNow()->startOfDay()->addDays($offset))
            ->filter(fn (Carbon $date) => count($business->reservationSlots($date)) > 0)
            ->take(10)
            ->mapWithKeys(fn (Carbon $date) => [$date->format('Y-m-d') => [
                'weekday' => ucfirst($date->locale('es')->isoFormat('ddd')),
                'day' => $date->format('d'),
                'month' => ucfirst($date->locale('es')->isoFormat('MMM')),
                'label' => $date->locale('es')->isoFormat('dddd D [de] MMMM'),
            ]])
            ->all();
    }

    #[Computed]
    public function timeSlots(): array
    {
        if (! $this->selectedDate) {
            return [];
        }

        return BusinessSetting::current()->reservationSlots($this->selectedDate);
    }

    #[Computed]
    public function availabilityByDate(): array
    {
        return app(ReservationAvailabilityService::class)->forDates(
            BusinessSetting::current(),
            array_keys($this->dateOptions),
        );
    }

    public function selectDate(string $date): void
    {
        abort_unless(array_key_exists($date, $this->dateOptions), 422);
        $this->selectedDate = $date;
        $this->selectedTime = '';
        unset($this->timeSlots);
        $this->resetErrorBag(['selectedDate', 'selectedTime']);
    }

    public function selectTime(string $time): void
    {
        abort_unless(in_array($time, $this->timeSlots, true), 422);
        $this->selectedTime = $time;
        $this->resetErrorBag('selectedTime');
    }

    public function openModal(): void
    {
        $this->isOpen = true;
        $this->resetErrorBag();
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
    }

    public function incrementGuests(): void
    {
        $this->guests = min(20, $this->guests + 1);
    }

    public function decrementGuests(): void
    {
        $this->guests = max(1, $this->guests - 1);
    }

    public function continueToTime(): void
    {
        $this->validate([
            'selectedDate' => ['required', 'date', 'after_or_equal:'.$this->businessNow()->toDateString()],
        ], [
            'selectedDate.required' => 'Selecciona una fecha disponible.',
        ]);

        if (! array_key_exists($this->selectedDate, $this->dateOptions)) {
            $this->addError('selectedDate', 'Esta fecha ya no está disponible. Elige otra opción.');

            return;
        }

        $this->step = 2;
    }

    public function continueToDetails(): void
    {
        $this->validate([
            'selectedTime' => ['required', 'date_format:H:i'],
            'guests' => ['required', 'integer', 'min:1', 'max:20'],
        ], [
            'selectedTime.required' => 'Selecciona una hora disponible.',
        ]);

        if (! in_array($this->selectedTime, $this->timeSlots, true)) {
            $this->addError('selectedTime', 'Este horario ya no está disponible. Elige otra hora.');

            return;
        }

        $this->step = 3;
    }

    public function backToDate(): void
    {
        $this->step = 1;
    }

    public function backToTime(): void
    {
        $this->step = 2;
    }

    public function submit(): void
    {
        $validated = $this->validate([
            'selectedDate' => ['required', 'date', 'after_or_equal:'.$this->businessNow()->toDateString()],
            'selectedTime' => ['required', 'date_format:H:i'],
            'guests' => ['required', 'integer', 'min:1', 'max:20'],
            'customerName' => ['required', 'string', 'max:100'],
            'customerPhone' => ['required', 'string', 'min:7', 'max:30'],
            'customerEmail' => ['nullable', 'email', 'max:160'],
            'occasion' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:500'],
            'website' => ['nullable', 'max:0'],
            'acceptWaitlist' => ['boolean'],
        ], [
            'customerName.required' => 'Escribe el nombre de la reservación.',
            'customerPhone.required' => 'Agrega un teléfono de contacto.',
            'customerPhone.min' => 'Revisa que el teléfono esté completo.',
            'customerEmail.email' => 'Escribe un correo válido.',
            'website.max' => 'No fue posible registrar la solicitud.',
        ]);

        $reservedAt = Carbon::createFromFormat(
            'Y-m-d H:i',
            $validated['selectedDate'].' '.$validated['selectedTime'],
            config('app.business_timezone', 'America/Mexico_City'),
        );

        if (! BusinessSetting::current()->acceptsReservationAt($reservedAt)) {
            $this->step = 2;
            $this->addError('selectedTime', 'El restaurante no está abierto en ese horario. Selecciona otra opción.');

            return;
        }

        $rateKey = 'public-reservation:'.request()->ip();
        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            $this->addError('customerName', 'Alcanzaste el límite temporal de solicitudes. Intenta más tarde.');

            return;
        }

        $token = (string) Str::uuid();
        $result = DB::transaction(function () use ($validated, $reservedAt, $token): string|false {
            $availability = app(ReservationAvailabilityService::class)->forMoment(
                BusinessSetting::current(),
                $reservedAt,
                (int) $validated['guests'],
                true,
            );
            $requiresWaitlist = $availability['enforced'] && ! $availability['can_fit'];

            if ($requiresWaitlist && ! $validated['acceptWaitlist']) {
                return false;
            }

            Reservation::create([
                'created_by' => null,
                'customer_name' => trim($validated['customerName']),
                'customer_phone' => trim($validated['customerPhone']),
                'customer_email' => trim($validated['customerEmail'] ?? '') ?: null,
                'guests' => $validated['guests'],
                'occasion' => trim($validated['occasion'] ?? '') ?: null,
                'reserved_at' => $reservedAt,
                'notes' => trim($validated['notes'] ?? '') ?: null,
                'status' => 'pendiente',
                'source' => 'public',
                'is_waitlist' => $requiresWaitlist,
                'public_token' => $token,
            ]);

            return $requiresWaitlist ? 'waitlist' : 'reserved';
        });

        if (! $result) {
            $this->step = 2;
            $this->addError('selectedTime', 'Ya no hay capacidad suficiente para este horario. Puedes elegir otra hora o aceptar la lista de espera.');

            return;
        }

        $this->acceptWaitlist = $result === 'waitlist';

        RateLimiter::hit($rateKey, 3600);
        $this->confirmationCode = strtoupper(substr(str_replace('-', '', $token), 0, 8));
    }

    public function render()
    {
        return view('livewire.public-reservation');
    }

    private function businessNow(): Carbon
    {
        return now(config('app.business_timezone', 'America/Mexico_City'));
    }
}
