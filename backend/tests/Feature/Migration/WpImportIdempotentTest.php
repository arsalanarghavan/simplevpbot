<?php

namespace Tests\Feature\Migration;

use App\Services\Migration\WpImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class WpImportIdempotentTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    public function test_second_import_skips_existing_rows(): void
    {
        $path = base_path('tests/fixtures/wp-minimal-dump.sql');
        $svc = app(WpImportService::class);

        $first = $svc->run($path, 'wp_');
        $userCount = (int) DB::table('svp_users')->count();

        $second = $svc->run($path, 'wp_');
        $this->assertSame($userCount, (int) DB::table('svp_users')->count());
        $this->assertGreaterThan(0, (int) ($second['tables']['skipped'] ?? 0));
        $this->assertSame(0, (int) ($second['tables']['inserted'] ?? 0));
    }
}
