<?php

namespace App\Providers;

use App\Listeners\EnforceSingleSession;
use App\Livewire\Layout\AdminNavbar;
use App\Livewire\Layout\AdminSidebar;
use App\Livewire\Layout\NotificationCenter;
use App\Livewire\Ui\ConfirmModal;
use App\Models\BusinessSetting;
use App\Models\DeliveryAssignment;
use App\Models\Order;
use App\Observers\DeliveryAssignmentObserver;
use App\Observers\OrderObserver;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
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
        // Owner y super-admin bypassan todos los permisos.
        Gate::before(function ($user, $ability) {
            return $user->hasAnyRole(['owner', 'super-admin']) ? true : null;
        });

        Livewire::component('layout.admin-sidebar', AdminSidebar::class);
        Livewire::component('layout.admin-navbar', AdminNavbar::class);
        Livewire::component('layout.notification-center', NotificationCenter::class);
        Livewire::component('ui.confirm-modal', ConfirmModal::class);

        Order::observe(OrderObserver::class);
        DeliveryAssignment::observe(DeliveryAssignmentObserver::class);

        View::composer([
            'layouts.app',
            'layouts.pos',
            'layouts.kiosk',
            'layouts.guest',
            'components.application-logo',
            'livewire.layout.admin-sidebar',
            'livewire.pos.partials.header',
            'livewire.kiosk.*',
        ], function ($view): void {
            static $businessSettings;

            if ($businessSettings === null && Schema::hasTable('business_settings')) {
                $businessSettings = BusinessSetting::current();
            }

            $view->with('businessSettings', $businessSettings);
        });

        Event::listen(Login::class, EnforceSingleSession::class);
    }
}
