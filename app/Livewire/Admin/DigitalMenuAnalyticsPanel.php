<?php

namespace App\Livewire\Admin;

use App\Services\DigitalMenuAnalytics;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class DigitalMenuAnalyticsPanel extends Component
{
    public int $days = 30;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('ver analitica menu digital'), 403);
    }

    public function setPeriod(int $days): void
    {
        abort_unless(in_array($days, [7, 30, 90], true), 404);
        $this->days = $days;
        unset($this->analytics);
        $this->dispatch('digital-menu-analytics-updated');
    }

    #[Computed]
    public function analytics(): array
    {
        return app(DigitalMenuAnalytics::class)->dashboard($this->days);
    }

    public function render()
    {
        return view('livewire.admin.digital-menu-analytics-panel');
    }
}
