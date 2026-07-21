<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $template = DB::table('ticket_templates')->where('key', 'cash_cut')->first();
        if (! $template) return;

        $existing = collect(json_decode($template->blocks, true) ?: [])->keyBy('key');
        $blocks = collect(\App\Models\TicketTemplate::cashCutBlocks())->map(function (array $block) use ($existing) {
            if ($existing->has($block['key'])) {
                $block['enabled'] = (bool) ($existing[$block['key']]['enabled'] ?? $block['enabled']);
            }
            return $block;
        })->values()->all();

        DB::table('ticket_templates')->where('key', 'cash_cut')->update([
            'blocks' => json_encode($blocks, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('ticket_templates')->where('key', 'cash_cut')->update([
            'blocks' => json_encode([
                ['key' => 'header', 'label' => 'Encabezado y logo', 'enabled' => true],
                ['key' => 'business', 'label' => 'Información del negocio', 'enabled' => true],
                ['key' => 'order_meta', 'label' => 'Datos del pedido', 'enabled' => true],
                ['key' => 'cut_summary', 'label' => 'Resumen del corte', 'enabled' => true],
                ['key' => 'footer', 'label' => 'Pie del ticket', 'enabled' => true],
            ], JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }
};
