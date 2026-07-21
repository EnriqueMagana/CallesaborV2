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
}
