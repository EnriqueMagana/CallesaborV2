<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class EnforceSingleSession
{
    public function handle(Login $event): void
    {
        if (! Request::hasSession() || config('session.driver') !== 'database') {
            return;
        }

        // Delete all other sessions for this user except the current one
        DB::table('sessions')
            ->where('user_id', $event->user->id)
            ->where('id', '!=', Request::session()->getId())
            ->delete();
    }
}
