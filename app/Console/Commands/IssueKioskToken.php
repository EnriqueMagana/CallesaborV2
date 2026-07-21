<?php

namespace App\Console\Commands;

use App\Models\KioskTerminal;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class IssueKioskToken extends Command
{
    protected $signature = 'kiosk:issue-token {name=Kiosco principal} {--user= : Correo del usuario responsable}';

    protected $description = 'Crea un terminal de kiosco y muestra su URL pública (el token solo se muestra una vez)';

    public function handle(): int
    {
        $user = $this->option('user')
            ? User::where('email', $this->option('user'))->first()
            : User::query()->first();

        if (! $user) {
            $this->error('Debe existir al menos un usuario para responsabilizar las órdenes del kiosco.');
            return self::FAILURE;
        }

        $token = Str::random(64);
        $terminal = KioskTerminal::create([
            'name' => $this->argument('name'),
            'token_hash' => hash('sha256', $token),
            'token_hint' => Str::substr($token, -8),
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $this->info("Terminal #{$terminal->id} creado para {$user->email}");
        $this->line(route('kiosk.order', ['token' => $token]));
        $this->warn('Guarde esta URL: el token no se puede recuperar después.');

        return self::SUCCESS;
    }
}
