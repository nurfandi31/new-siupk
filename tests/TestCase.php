<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        try {
            DB::connection('platform')->disconnect();
            DB::connection('tenant')->disconnect();
        } catch (\Throwable) {
        }

        parent::tearDown();
    }
}
