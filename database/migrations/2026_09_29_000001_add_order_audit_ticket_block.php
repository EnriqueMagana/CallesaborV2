<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ticket_templates')) {
            return;
        }

        DB::table('ticket_templates')
            ->whereIn('key', ['customer', 'counter', 'delivery'])
            ->orderBy('id')
            ->each(function ($template): void {
                $blocks = json_decode($template->blocks ?: '[]', true);
                if (! is_array($blocks) || collect($blocks)->contains('key', 'order_audit')) {
                    return;
                }

                $paymentIndex = collect($blocks)->search(
                    fn (array $block): bool => ($block['key'] ?? null) === 'payments'
                );
                $insertAt = $paymentIndex === false ? count($blocks) : $paymentIndex + 1;
                array_splice($blocks, $insertAt, 0, [[
                    'key' => 'order_audit',
                    'label' => 'Auditoría de modificaciones',
                    'enabled' => true,
                ]]);

                DB::table('ticket_templates')->where('id', $template->id)->update([
                    'blocks' => json_encode($blocks, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ticket_templates')) {
            return;
        }

        DB::table('ticket_templates')
            ->whereIn('key', ['customer', 'counter', 'delivery'])
            ->orderBy('id')
            ->each(function ($template): void {
                $blocks = collect(json_decode($template->blocks ?: '[]', true))
                    ->reject(fn (array $block): bool => ($block['key'] ?? null) === 'order_audit')
                    ->values()
                    ->all();

                DB::table('ticket_templates')->where('id', $template->id)->update([
                    'blocks' => json_encode($blocks, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
            });
    }
};
