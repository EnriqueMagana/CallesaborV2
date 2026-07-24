<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class NoPollingTest extends TestCase
{
    public function test_no_application_view_uses_livewire_polling(): void
    {
        foreach (File::allFiles(resource_path('views')) as $view) {
            $this->assertStringNotContainsString(
                'wire:poll',
                $view->getContents(),
                "Polling encontrado en {$view->getRelativePathname()}"
            );
        }
    }

    public function test_authored_frontend_has_no_periodic_network_polling(): void
    {
        foreach ([resource_path('views'), public_path('assets/js')] as $root) {
            foreach (File::allFiles($root) as $file) {
                $relativePath = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());

                $this->assertDoesNotMatchRegularExpression(
                    '/setInterval\s*\([\s\S]{0,1200}?(?:fetch\s*\(|axios\.|Livewire\.(?:dispatch|find)|\$wire\.)/i',
                    $file->getContents(),
                    "Polling periódico de red encontrado en {$relativePath}",
                );
            }
        }

        $kiosk = File::get(resource_path('views/livewire/kiosk/order-wizard.blade.php'));
        $this->assertStringContainsString('setInterval(() => this.next(), 5000)', $kiosk);
    }
}
