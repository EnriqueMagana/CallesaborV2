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

    public function test_continuous_pos_controls_do_not_sync_on_every_input_event(): void
    {
        $notificationPreferences = File::get(resource_path('views/livewire/profile/notification-preferences-form.blade.php'));
        $checkout = File::get(resource_path('views/livewire/pos/partials/modals/checkout.blade.php'));
        $pickupPayment = File::get(resource_path('views/livewire/pos/partials/modals/pickup-pay.blade.php'));

        $this->assertStringNotContainsString('wire:model.live="volume"', $notificationPreferences);
        $this->assertStringContainsString('x-on:change="$wire.set(\'volume\', previewVolume)"', $notificationPreferences);
        $this->assertStringNotContainsString('wire:model.live.debounce.350ms="payCashReceived"', $checkout);
        $this->assertStringNotContainsString('wire:model.live="pickupPayAmount"', $pickupPayment);
        $this->assertStringNotContainsString('wire:model.live="pickupPayReceived"', $pickupPayment);

        foreach (File::allFiles(resource_path('views/livewire/pos')) as $view) {
            $this->assertDoesNotMatchRegularExpression(
                '/<input\b(?=[^>]*type="(?:number|range)")(?=[^>]*wire:model\.live)[^>]*>/i',
                $view->getContents(),
                "Control continuo sincronizado por cada evento en {$view->getRelativePathname()}",
            );
        }
    }
}
