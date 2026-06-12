<?php

namespace Tests\Feature\Migration;

use App\Services\Migration\WpImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class WpImportTablesTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    public function test_imports_svp_tables_from_fixture(): void
    {
        $path = base_path('tests/fixtures/wp-minimal-dump.sql');
        $result = app(WpImportService::class)->run($path, 'wp_');

        $this->assertSame(2, (int) DB::table('svp_users')->count());
        $this->assertSame(1, (int) DB::table('svp_services')->count());
        $this->assertTrue($result['verify']['ok'] ?? false);
    }
}
