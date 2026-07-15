<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Mesa;
use Illuminate\Database\Seeder;

class MesasSeeder extends Seeder
{
    public function run(): void
    {
        // ── Áreas ──
        $salon = Area::create([
            'name'       => 'Salón',
            'color'      => '#696cff',
            'icon'       => 'bx-home-alt',
            'description'=> 'Salón principal interior',
            'sort_order' => 1,
        ]);

        $terraza = Area::create([
            'name'       => 'Terraza',
            'color'      => '#22c55e',
            'icon'       => 'bx-sun',
            'description'=> 'Terraza exterior',
            'sort_order' => 2,
        ]);

        // ── Mesas Salón (1-20) ──
        $capacidades = [2, 2, 4, 4, 4, 4, 4, 6, 6, 6, 4, 4, 4, 4, 2, 2, 6, 8, 8, 4];
        foreach (range(1, 20) as $i) {
            Mesa::create([
                'area_id'  => $salon->id,
                'number'   => $i,
                'capacity' => $capacidades[$i - 1] ?? 4,
                'status'   => 'disponible',
            ]);
        }

        // ── Mesas Terraza (1-20) ──
        $capTerraza = [2, 2, 4, 4, 4, 4, 6, 6, 4, 4, 4, 4, 4, 2, 2, 4, 4, 6, 8, 4];
        foreach (range(1, 20) as $i) {
            Mesa::create([
                'area_id'  => $terraza->id,
                'number'   => $i,
                'capacity' => $capTerraza[$i - 1] ?? 4,
                'status'   => 'disponible',
            ]);
        }
    }
}
