<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        foreach (['platform', 'tenant', 'legacy'] as $connection) {
            try {
                DB::connection($connection)->disconnect();
            } catch (\Throwable) {
                // Connection may not be configured in this test.
            }
        }

        parent::tearDown();
    }
}
