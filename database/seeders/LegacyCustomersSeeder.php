<?php

namespace Database\Seeders;

use App\Models\Customer;
use Database\Seeders\Support\LegacyCustomerSqlReader;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LegacyCustomersSeeder extends Seeder
{
    private const PLACEHOLDER_PHONE = '/^(?:0+|1+|9+)$/';

    /**
     * Import manually with:
     * php artisan db:seed --class=LegacyCustomersSeeder
     *
     * This seeder is intentionally not registered in DatabaseSeeder.
     */
    public function run(): void
    {
        $source = database_path('customers.sql');
        $prepared = $this->prepare($source);
        $created = 0;
        $existing = 0;

        DB::transaction(function () use ($prepared, &$created, &$existing): void {
            foreach ($prepared['customers'] as $attributes) {
                if ($this->customerAlreadyExists($attributes)) {
                    $existing++;

                    continue;
                }

                $customer = new Customer;
                $customer->fill(collect($attributes)->except(['created_at', 'updated_at'])->all());
                $customer->created_at = $attributes['created_at'];
                $customer->updated_at = $attributes['updated_at'];
                $customer->save();
                $created++;
            }
        });

        $this->command?->info(sprintf(
            'Clientes heredados: %d creados, %d ya existentes, %d duplicados combinados, %d eliminados omitidos y %d teléfonos ficticios descartados.',
            $created,
            $existing,
            $prepared['duplicates_merged'],
            $prepared['deleted_skipped'],
            $prepared['invalid_phones']
        ));
    }

    /**
     * Prepare a dry-run representation. This method does not access the current database.
     *
     * @return array{
     *     customers: array<int, array<string, string|null>>,
     *     source_rows: int,
     *     duplicates_merged: int,
     *     deleted_skipped: int,
     *     invalid_phones: int
     * }
     */
    public function prepare(string $source): array
    {
        $rows = (new LegacyCustomerSqlReader)->read($source);
        $customers = [];
        $deletedSkipped = 0;
        $invalidPhones = 0;
        $duplicatesMerged = 0;

        foreach ($rows as $row) {
            if ($this->clean($row['deleted_at'] ?? null) !== null) {
                $deletedSkipped++;

                continue;
            }

            $phone = $this->normalizePhone($row['phone_number'] ?? null);

            if ($phone === null && $this->clean($row['phone_number'] ?? null) !== null) {
                $invalidPhones++;
            }

            [$address, $neighborhood] = $this->splitAddress($row['address'] ?? null);
            $legacyId = (string) ($row['id'] ?? '');
            $name = $this->normalizeName($row['name'] ?? null, $legacyId);
            $email = $this->normalizeEmail($row['email'] ?? null);
            $customer = [
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'address' => $this->limit($address, 255),
                'neighborhood' => $this->limit($neighborhood, 120),
                'references' => $this->clean($row['references'] ?? null),
                'created_at' => $this->normalizeDate($row['created_at'] ?? null),
                'updated_at' => $this->normalizeDate($row['updated_at'] ?? null),
            ];
            $identity = $this->identity($customer);

            if (isset($customers[$identity])) {
                $customers[$identity] = $this->merge($customers[$identity], $customer);
                $duplicatesMerged++;
            } else {
                $customers[$identity] = $customer;
            }
        }

        return [
            'customers' => array_values($customers),
            'source_rows' => count($rows),
            'duplicates_merged' => $duplicatesMerged,
            'deleted_skipped' => $deletedSkipped,
            'invalid_phones' => $invalidPhones,
        ];
    }

    /**
     * Existing records are only detected and skipped; they are never updated.
     * A phone or email is paired with the name so relatives may share contact data.
     *
     * @param  array<string, string|null>  $attributes
     */
    private function customerAlreadyExists(array $attributes): bool
    {
        $name = mb_strtolower($attributes['name'], 'UTF-8');
        $query = Customer::query()->whereRaw('LOWER(name) = ?', [$name]);

        if ($attributes['phone']) {
            return $query->where('phone', $attributes['phone'])->exists();
        }

        if ($attributes['email']) {
            return $query->whereRaw('LOWER(email) = ?', [mb_strtolower($attributes['email'], 'UTF-8')])->exists();
        }

        if ($attributes['address']) {
            return $query->where('address', $attributes['address'])->exists();
        }

        return $query->whereNull('phone')->whereNull('email')->whereNull('address')->exists();
    }

    private function normalizeName(?string $value, string $legacyId): string
    {
        $name = $this->clean($value);

        if ($name === null) {
            return 'Cliente heredado #'.($legacyId !== '' ? $legacyId : 'sin-id');
        }

        return $this->limit(mb_convert_case(mb_strtolower($name, 'UTF-8'), MB_CASE_TITLE, 'UTF-8'), 120)
            ?? 'Cliente heredado #'.$legacyId;
    }

    private function normalizePhone(?string $value): ?string
    {
        $phone = preg_replace('/\D+/', '', (string) $value);

        if ($phone === '' || preg_match(self::PLACEHOLDER_PHONE, $phone)) {
            return null;
        }

        if (strlen($phone) === 13 && str_starts_with($phone, '521')) {
            $phone = substr($phone, 3);
        } elseif (strlen($phone) === 12 && str_starts_with($phone, '52')) {
            $phone = substr($phone, 2);
        }

        return strlen($phone) >= 7 && strlen($phone) <= 15 ? $phone : null;
    }

    private function normalizeEmail(?string $value): ?string
    {
        $email = $this->clean($value);

        if ($email === null) {
            return null;
        }

        $email = mb_strtolower($email, 'UTF-8');

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $this->limit($email, 160) : null;
    }

    /**
     * Extract only explicit/common neighborhood suffixes, keeping uncertain text in address.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function splitAddress(?string $value): array
    {
        $address = $this->clean($value);

        if ($address === null || ! str_contains($address, ',')) {
            return [$address, null];
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $address))));
        $candidate = (string) end($parts);
        $normalized = Str::ascii(mb_strtolower($candidate, 'UTF-8'));
        $explicit = preg_match('/^(?:col(?:onia)?\.?|fracc(?:ionamiento)?\.?|barrio|residencial|unidad)\s+/i', $normalized);
        $known = preg_match('/^(?:centro|santiago|san roman|san juan(?: oriente)?|mejorada|obrera|san enrique|san jose|san sebastian|guadalupe|fatima|la ermita)$/i', $normalized);

        if (! $explicit && ! $known) {
            return [$address, null];
        }

        array_pop($parts);

        return [$this->clean(implode(', ', $parts)), $candidate];
    }

    /** @param array<string, string|null> $customer */
    private function identity(array $customer): string
    {
        $name = Str::ascii(mb_strtolower($customer['name'], 'UTF-8'));

        if ($customer['phone']) {
            return 'phone:'.$customer['phone'].'|name:'.$name;
        }

        if ($customer['email']) {
            return 'email:'.$customer['email'].'|name:'.$name;
        }

        return 'profile:'.$name.'|'.Str::ascii(mb_strtolower((string) $customer['address'], 'UTF-8'));
    }

    /**
     * @param  array<string, string|null>  $current
     * @param  array<string, string|null>  $incoming
     * @return array<string, string|null>
     */
    private function merge(array $current, array $incoming): array
    {
        foreach (['name', 'phone', 'email', 'address', 'neighborhood', 'references'] as $field) {
            if ($incoming[$field] !== null) {
                $current[$field] = $incoming[$field];
            }
        }

        $current['created_at'] = collect([$current['created_at'], $incoming['created_at']])->filter()->min();
        $current['updated_at'] = collect([$current['updated_at'], $incoming['updated_at']])->filter()->max();

        return $current;
    }

    private function normalizeDate(?string $value): ?string
    {
        $value = $this->clean($value);

        if ($value === null || ! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
            return null;
        }

        return $value;
    }

    private function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = preg_replace('/\s+/u', ' ', trim($value));

        return $value === '' ? null : $value;
    }

    private function limit(?string $value, int $length): ?string
    {
        return $value === null ? null : mb_substr($value, 0, $length, 'UTF-8');
    }
}
