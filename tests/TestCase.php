<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        try {
            $stateCount = DB::table('geo_states')->count();
            if ($stateCount === 0) {
                $this->seed(\Database\Seeders\GeographySeeder::class);
            }
        } catch (\Throwable) {
            // Table may not exist yet on first run; ignore.
        }
    }
}
