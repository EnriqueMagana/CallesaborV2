<?php

namespace App\Providers;

use App\Listeners\EnforceSingleSession;
use App\Livewire\Layout\AdminNavbar;
use App\Livewire\Layout\AdminSidebar;
use App\Livewire\Ui\ConfirmModal;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // super-admin bypasses all permission checks
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super-admin') ? true : null;
        });

        Livewire::component('layout.admin-sidebar', AdminSidebar::class);
        Livewire::component('layout.admin-navbar', AdminNavbar::class);
        Livewire::component('ui.confirm-modal', ConfirmModal::class);

        Event::listen(Login::class, EnforceSingleSession::class);
    }
}
