<?php

namespace App\Livewire;

use App\Services\DashboardDataBuilder;
use Livewire\Component;

class Dashboard extends Component
{
    public string $period = '7';

    public function setPeriod(string $period): void
    {
        if (in_array($period, ['today', '7', '30'], true)) {
            $this->period = $period;
        }
    }

    public function refreshDashboard(): void
    {
        $this->dispatch('dashboard-refreshed');
    }

    public function render()
    {
        return view('livewire.pages.dashboard', [
            'dashboard' => app(DashboardDataBuilder::class)->build(auth()->user(), $this->period),
        ])->layout('layouts.app');
    }
}
