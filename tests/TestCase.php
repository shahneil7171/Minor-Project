<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Every test runs against a freshly migrated database. The baseline
     * product catalog (seed products + their category assignments) is
     * seeded inside the per-test transaction so catalog-dependent tests
     * behave exactly like production, while non-catalog tests are unaffected.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (\Illuminate\Support\Facades\Schema::hasTable('products')) {
            $this->seed(\Database\Seeders\ProductsSeeder::class);
        }
    }
}

