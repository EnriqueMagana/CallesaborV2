<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('promotions')
            ->select(['id', 'fulfillment_modes'])
            ->orderBy('id')
            ->chunkById(100, function ($promotions): void {
                foreach ($promotions as $promotion) {
                    $modes = json_decode((string) $promotion->fulfillment_modes, true);
                    if (! is_array($modes) || ! in_array('takeaway', $modes, true) || in_array('pickup', $modes, true)) {
                        continue;
                    }

                    $modes[] = 'pickup';
                    DB::table('promotions')->where('id', $promotion->id)->update([
                        'fulfillment_modes' => json_encode(array_values($modes), JSON_UNESCAPED_UNICODE),
                    ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('promotions')
            ->select(['id', 'fulfillment_modes'])
            ->orderBy('id')
            ->chunkById(100, function ($promotions): void {
                foreach ($promotions as $promotion) {
                    $modes = json_decode((string) $promotion->fulfillment_modes, true);
                    if (! is_array($modes) || ! in_array('takeaway', $modes, true)) {
                        continue;
                    }

                    $modes = array_values(array_filter($modes, fn ($mode) => $mode !== 'pickup'));
                    DB::table('promotions')->where('id', $promotion->id)->update([
                        'fulfillment_modes' => json_encode($modes, JSON_UNESCAPED_UNICODE),
                    ]);
                }
            });
    }
};
