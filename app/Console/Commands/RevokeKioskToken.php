<?php

namespace App\Console\Commands;

use App\Models\KioskTerminal;
use Illuminate\Console\Command;

class RevokeKioskToken extends Command
{
    protected $signature = 'kiosk:revoke-token {terminal : ID del terminal}';

    protected $description = 'Revoca inmediatamente el token de un terminal de kiosco';

    public function handle(): int
    {
        $terminal = KioskTerminal::find($this->argument('terminal'));

        if (! $terminal) {
            $this->error('No se encontró el terminal indicado.');
            return self::FAILURE;
        }

        $terminal->update(['is_active' => false]);
        $this->info("El acceso de {$terminal->name} fue revocado.");

        return self::SUCCESS;
    }
}
