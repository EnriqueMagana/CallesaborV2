<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class TicketPreviewModalTest extends TestCase
{
    public function test_reusable_ticket_modal_hides_the_document_until_its_assets_are_ready(): void
    {
        $html = Blade::render(
            '<x-ticket-preview-modal id="testTicket" title="Ticket de prueba" initial-tab="cliente" :open="true" :tabs="$tabs" />',
            ['tabs' => [[
                'key' => 'cliente',
                'label' => 'Cliente',
                'html' => '<!doctype html><html><body><img src="data:image/png;base64,AA==">Listo</body></html>',
            ]]],
        );

        $this->assertStringContainsString('data-ticket-preview-modal', $html);
        $this->assertStringContainsString('data-ticket-frame="cliente"', $html);
        $this->assertStringContainsString('aria-busy="true"', $html);
        $this->assertStringContainsString('Preparando ticket', $html);
        $this->assertStringContainsString('data-ticket-preview-print disabled', $html);

        $script = file_get_contents(public_path('assets/js/ticket-preview-modal.js'));
        $styles = file_get_contents(public_path('assets/css/ticket-preview-modal.css'));

        $this->assertStringContainsString('documentRef.fonts?.ready', $script);
        $this->assertStringContainsString('Array.from(documentRef.images)', $script);
        $this->assertStringContainsString('await nextPaint()', $script);
        $this->assertStringContainsString('.ticket-preview-frame iframe', $styles);
        $this->assertStringContainsString('opacity: 0', $styles);
        $this->assertStringContainsString('.ticket-preview-frame.is-ready iframe', $styles);
    }
}
