<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('neighborhood', 120)->nullable()->after('address');
        });

        DB::table('customers')
            ->select(['id', 'address'])
            ->whereNull('neighborhood')
            ->whereNotNull('address')
            ->orderBy('id')
            ->eachById(function (object $customer): void {
                $parts = array_values(array_filter(
                    array_map('trim', explode(',', (string) $customer->address)),
                    fn (string $part) => $part !== ''
                ));

                if (count($parts) < 2) {
                    return;
                }

                DB::table('customers')
                    ->where('id', $customer->id)
                    ->update([
                        'address' => mb_substr(implode(', ', array_slice($parts, 0, -1)), 0, 255),
                        'neighborhood' => mb_substr((string) end($parts), 0, 120),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('neighborhood');
        });
    }
};
