<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** @return array<string, mixed> */
    protected function migrateFreshUsing(): array
    {
        return [
            '--path' => [
                'database/migrations/2026_06_11_000001_create_dashboard_users_table.php',
                'database/migrations/2026_06_11_000002_create_svp_settings_table.php',
            ],
        ];
    }
}
