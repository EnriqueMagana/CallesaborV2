<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->setPaymentsBlock(true);
    }

    public function down(): void
    {
        $this->setPaymentsBlock(false);
    }

    private function setPaymentsBlock(bool $enabled): void
    {
        DB::table('ticket_templates')
            ->where('key', 'customer')
            ->orderBy('id')
            ->each(function ($template) use ($enabled): void {
                $blocks = json_decode($template->blocks ?: '[]', true);
                if (! is_array($blocks)) {
                    return;
                }

                $found = false;
                foreach ($blocks as &$block) {
                    if (($block['key'] ?? null) === 'payments') {
                        $block['enabled'] = $enabled;
                        $found = true;
                    }
                }
                unset($block);

                if (! $found && $enabled) {
                    $blocks[] = ['key' => 'payments', 'label' => 'Formas de pago', 'enabled' => true];
                }

                DB::table('ticket_templates')->where('id', $template->id)->update([
                    'blocks' => json_encode($blocks, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
            });
    }
};
