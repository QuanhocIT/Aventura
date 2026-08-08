<?php

namespace Tests;

use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->seed(PermissionsSeeder::class);
    }

    protected function tearDown(): void
    {
        \App\Http\Middleware\SetTenantContext::$enforceShiftLockInTests = false;
        parent::tearDown();
    }
}
