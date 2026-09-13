<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->convert('reservations', ['reserved_at'], true);
        $this->convert('discounts', ['starts_at', 'ends_at'], true);
    }

    public function down(): void
    {
        $this->convert('reservations', ['reserved_at'], false);
        $this->convert('discounts', ['starts_at', 'ends_at'], false);
    }

    /** @param list<string> $columns */
    private function convert(string $table, array $columns, bool $toStorage): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $columns = array_values(array_filter(
            $columns,
            fn (string $column): bool => Schema::hasColumn($table, $column)
        ));
        if ($columns === []) {
            return;
        }

        $businessTimezone = (string) config('app.business_timezone', 'America/Mexico_City');
        $storageTimezone = (string) config('app.timezone', 'UTC');
        $sourceTimezone = $toStorage ? $businessTimezone : $storageTimezone;
        $targetTimezone = $toStorage ? $storageTimezone : $businessTimezone;

        DB::table($table)
            ->select(['id', ...$columns])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($table, $columns, $sourceTimezone, $targetTimezone): void {
                foreach ($rows as $row) {
                    $updates = [];
                    foreach ($columns as $column) {
                        if ($row->{$column} !== null) {
                            $updates[$column] = Carbon::parse((string) $row->{$column}, $sourceTimezone)
                                ->setTimezone($targetTimezone)
                                ->format('Y-m-d H:i:s');
                        }
                    }

                    if ($updates !== []) {
                        DB::table($table)->where('id', $row->id)->update($updates);
                    }
                }
            });
    }
};
