<?php

namespace Tests\Feature\Migration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WpImportMysqlDsnTest extends TestCase
{
    use RefreshDatabase;

    public function test_mysql_dsn_format_validation(): void
    {
        $this->artisan('wp:import', ['--mysql-dsn' => 'bad-format'])
            ->assertExitCode(1);
    }
}
