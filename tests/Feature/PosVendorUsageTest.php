<?php

namespace Tests\Feature;

use App\Models\CashRegister;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El POS ya no carga jQuery, Popper ni Bootstrap JS: eran 642 KB que la
 * terminal descargaba en cada arranque sin usarlos. Toda la interactividad es
 * de Alpine, que viene incluido con Livewire.
 *
 * Esta prueba existe para que eso no se rompa en silencio. Si alguien agrega
 * un `data-bs-toggle`, un `$(...)` o un `new bootstrap.Modal` a una vista del
 * POS, el código fallaría en el navegador con un error que nadie ve hasta que
 * un cajero lo reporta. Aquí falla en CI.
 */
class PosVendorUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_pos_screen_does_not_load_jquery_popper_or_bootstrap(): void
    {
        $html = $this->posHtml();

        foreach (['jquery', 'popper', 'vendor/js/bootstrap'] as $library) {
            $this->assertStringNotContainsString(
                $library,
                mb_strtolower($html),
                "El POS volvió a cargar `{$library}`. Si de verdad hace falta, deja constancia de qué lo necesita."
            );
        }
    }

    public function test_no_pos_view_depends_on_bootstrap_javascript(): void
    {
        $html = $this->posHtml();

        $forbidden = [
            'data-bs-*' => '/data-bs-[a-z]+/',
            'jQuery `$(`' => '/[^a-zA-Z_$]\$\(/',
            '`bootstrap.Algo`' => '/\bbootstrap\.[A-Z]/',
            '`new bootstrap`' => '/new\s+bootstrap/',
            'plugins de Bootstrap' => '/\.(modal|tooltip|popover|collapse)\(/',
        ];

        foreach ($forbidden as $label => $pattern) {
            $this->assertSame(
                0,
                preg_match_all($pattern, $html),
                "Una vista del POS usa {$label}, pero la pantalla ya no carga jQuery ni Bootstrap JS. "
                ."Resuélvelo con Alpine, o vuelve a cargar la librería en `layouts/pos.blade.php` "
                .'y explica en el layout qué la necesita.'
            );
        }
    }

    public function test_the_pos_screen_loads_its_alpine_root_state(): void
    {
        $this->assertStringContainsString('assets/js/pos-root.min.js', $this->posHtml());
    }

    private function posHtml(): string
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('owner');

        CashRegister::create([
            'name' => 'Caja vendor',
            'opened_by' => $user->id,
            'initial_amount' => 0,
            'opened_at' => now(),
            'is_open' => true,
        ]);

        return $this->actingAs($user)->get(route('app.pos'))->assertOk()->getContent();
    }
}
