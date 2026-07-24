<?php

namespace App\Http\Controllers;

use App\Models\KioskTerminal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KioskLaunchController extends Controller
{
    public function __invoke(KioskTerminal $terminal): RedirectResponse
    {
        $token = DB::transaction(function () use ($terminal): string {
            $terminal = KioskTerminal::query()->lockForUpdate()->findOrFail($terminal->id);

            if ($terminal->token_secret) {
                return $terminal->token_secret;
            }

            $token = Str::random(64);
            $terminal->update([
                'token_hash' => hash('sha256', $token),
                'token_secret' => $token,
                'token_hint' => Str::substr($token, -8),
            ]);

            return $token;
        });

        return redirect()->route('kiosk.order', ['token' => $token]);
    }
}
