<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('ticket_templates')
            ->where('key', 'cash_cut')
            ->orderBy('id')
            ->each(function ($template): void {
                $blocks = json_decode($template->blocks ?: '[]', true);
                if (! is_array($blocks) || collect($blocks)->contains('key', 'cut_order_benefits')) {
                    return;
                }

                $newBlock = ['key' => 'cut_order_benefits', 'label' => 'Promociones y descuentos por orden', 'enabled' => true];
                $paymentIndex = collect($blocks)->search(fn (array $block): bool => ($block['key'] ?? null) === 'cut_payment_methods');
                $insertAt = $paymentIndex === false ? count($blocks) : $paymentIndex + 1;
                array_splice($blocks, $insertAt, 0, [$newBlock]);

                DB::table('ticket_templates')->where('id', $template->id)->update([
                    'blocks' => json_encode($blocks, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        DB::table('ticket_templates')
            ->where('key', 'cash_cut')
            ->orderBy('id')
            ->each(function ($template): void {
                $blocks = collect(json_decode($template->blocks ?: '[]', true))
                    ->reject(fn (array $block): bool => ($block['key'] ?? null) === 'cut_order_benefits')
                    ->values()
                    ->all();

                DB::table('ticket_templates')->where('id', $template->id)->update([
                    'blocks' => json_encode($blocks, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
            });
    }
};
