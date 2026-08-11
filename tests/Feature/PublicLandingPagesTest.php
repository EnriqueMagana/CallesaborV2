<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicLandingPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_main_public_pages_load_the_shared_responsive_stylesheet(): void
    {
        foreach (['public.home', 'public.menu', 'public.hours', 'public.gallery', 'public.contact'] as $routeName) {
            $this->get(route($routeName))
                ->assertOk()
                ->assertSee('name="viewport" content="width=device-width, initial-scale=1"', false)
                ->assertSee('assets/css/public-menu.css', false);
        }
    }

    public function test_public_pages_expose_a_styled_skip_link_and_focusable_target(): void
    {
        $this->get(route('public.home'))
            ->assertSee('class="menu-skip-link" href="#experiencia"', false)
            ->assertSee('id="experiencia" tabindex="-1"', false);

        $this->get(route('public.menu'))
            ->assertSee('class="menu-skip-link" href="#menu"', false)
            ->assertSee('id="menu" tabindex="-1"', false);

        foreach (['public.hours', 'public.gallery', 'public.contact'] as $routeName) {
            $this->get(route($routeName))
                ->assertSee('class="menu-skip-link skip-link" href="#contenido"', false)
                ->assertSee('id="contenido" tabindex="-1"', false);
        }
    }

    public function test_shared_styles_cover_the_skip_link_and_mobile_status_card(): void
    {
        $css = file_get_contents(public_path('assets/css/public-menu.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString('.menu-skip-link,.skip-link', $css);
        $this->assertStringContainsString('.info-status-card{min-height:0;align-items:center', $css);
        $this->assertStringContainsString('@media(max-width:390px)', $css);
    }

    public function test_home_uses_an_automatic_restaurant_carousel_without_controls(): void
    {
        $response = $this->get(route('public.home'));

        $response->assertOk()
            ->assertSee('assets/css/public-home.css', false)
            ->assertSee('assets/img/restaurant/banner1.jpg', false)
            ->assertSee('assets/img/restaurant/banner5.jpg', false)
            ->assertSee('assets/img/restaurant/banner7.jpg', false)
            ->assertSee('data-home-hero', false)
            ->assertDontSee('data-home-hero-progress', false)
            ->assertDontSee('data-home-hero-prev', false)
            ->assertDontSee('data-home-hero-pause', false)
            ->assertDontSee('data-home-hero-next', false)
            ->assertDontSee('data-home-hero-dot', false)
            ->assertSee('data-home-reveal', false);

        $css = file_get_contents(public_path('assets/css/public-home.css'));
        $javascript = file_get_contents(public_path('assets/js/public-home.js'));

        $this->assertIsString($css);
        $this->assertIsString($javascript);
        $this->assertStringContainsString('pattern.png', $css);
        $this->assertStringContainsString('pattern2.png', $css);
        $this->assertStringContainsString('min-height: 56px;', $css);
        $this->assertStringContainsString('@media (max-width: 390px)', $css);
        $this->assertStringContainsString('prefers-reduced-motion: reduce', $css);
        $this->assertStringContainsString('window.setTimeout', $javascript);
        $this->assertStringNotContainsString('data-home-hero-progress', $javascript);
        $this->assertStringNotContainsString('data-home-hero-pause', $javascript);
        $this->assertStringContainsString('IntersectionObserver', $javascript);
    }

    public function test_every_static_home_icon_exists_in_the_installed_boxicons_font(): void
    {
        $boxicons = file_get_contents(public_path('assets/vendor/fonts/boxicons.css'));
        $views = [
            resource_path('views/public-home/index.blade.php'),
            resource_path('views/components/public-menu/footer.blade.php'),
            resource_path('views/livewire/public-reservation.blade.php'),
        ];
        $icons = [];

        foreach ($views as $view) {
            preg_match_all('/\bbx-[a-z0-9-]+\b/', file_get_contents($view), $matches);
            $icons = array_merge($icons, $matches[0]);
        }

        foreach (array_unique($icons) as $icon) {
            $this->assertStringContainsString(".{$icon}:before", $boxicons, "El icono {$icon} no existe en Boxicons.");
        }

        $this->assertStringContainsString('url("./boxicons/boxicons.woff2")', $boxicons);
        $this->assertFileExists(public_path('assets/vendor/fonts/boxicons/boxicons.woff2'));
    }

    public function test_home_marks_the_current_day_in_the_business_hours(): void
    {
        Carbon::setTestNow('2026-08-10 12:00:00');

        try {
            $this->get(route('public.home'))
                ->assertOk()
                ->assertSee('class="is-today"', false)
                ->assertSeeTextInOrder(['Lunes', 'Hoy', '09:00 – 18:00']);
        } finally {
            Carbon::setTestNow();
        }
    }
}
