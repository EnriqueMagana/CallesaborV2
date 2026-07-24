<?php

namespace App\Livewire\Actions;

use App\Services\SingleSessionManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class Logout
{
    /**
     * Log the current user out of the application.
     */
    public function __invoke(): void
    {
        if ($user = Auth::guard('web')->user()) {
            app(SingleSessionManager::class)->end($user, Session::driver());
        }

        Auth::guard('web')->logout();

        Session::invalidate();
        Session::regenerateToken();
    }
}
