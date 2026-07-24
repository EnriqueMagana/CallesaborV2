<?php

namespace App\Listeners;

use App\Services\SingleSessionManager;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Request;

class EnforceSingleSession
{
    public function __construct(private readonly SingleSessionManager $sessions)
    {
    }

    public function handle(Login $event): void
    {
        if (! Request::hasSession()) {
            return;
        }

        $this->sessions->start($event->user, Request::session());
    }
}
