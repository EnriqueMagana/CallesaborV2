<?php

namespace Tests\Unit;

use Database\Seeders\LegacyCustomersSeeder;
use Database\Seeders\Support\LegacyCustomerSqlReader;
use PHPUnit\Framework\TestCase;

class LegacyCustomersSeederTest extends TestCase
{
    public function test_reader_loads_every_customer_row_from_the_legacy_dump(): void
    {
        $rows = (new LegacyCustomerSqlReader)->read(dirname(__DIR__, 2).'/database/customers.sql');

        $this->assertCount(1900, $rows);
        $this->assertSame('1', $rows[0]['id']);
        $this->assertSame('1900', $rows[1899]['id']);
        $this->assertArrayHasKey('phone_number', $rows[0]);
    }

    public function test_prepare_maps_legacy_columns_without_accessing_a_database(): void
    {
        $prepared = (new LegacyCustomersSeeder)->prepare(dirname(__DIR__, 2).'/database/customers.sql');

        $this->assertSame(1900, $prepared['source_rows']);
        $this->assertNotEmpty($prepared['customers']);
        $this->assertGreaterThan(0, $prepared['duplicates_merged']);
        $this->assertGreaterThan(0, $prepared['invalid_phones']);

        foreach ($prepared['customers'] as $customer) {
            $this->assertSame(
                ['name', 'phone', 'email', 'address', 'neighborhood', 'references', 'created_at', 'updated_at'],
                array_keys($customer)
            );
            $this->assertNotSame('0000000000', $customer['phone']);
            $this->assertLessThanOrEqual(120, mb_strlen($customer['name'], 'UTF-8'));
            $this->assertLessThanOrEqual(255, mb_strlen((string) $customer['address'], 'UTF-8'));
        }
    }
}
