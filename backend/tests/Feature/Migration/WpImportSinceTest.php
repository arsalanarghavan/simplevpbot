<?php

namespace Tests\Feature\Migration;

use App\Services\Migration\WpImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class WpImportSinceTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    public function test_incremental_import_skips_old_rows(): void
    {
        $path = base_path('tests/fixtures/wp-minimal-dump.sql');
        $svc = app(WpImportService::class);
        $first = $svc->run($path, 'wp_');
        $this->assertGreaterThan(0, (int) ($first['tables']['inserted'] ?? 0));

        $since = now()->addHour()->format('Y-m-d H:i:s');
        $second = $svc->run($path, 'wp_', false, false, null, 'changeme', $since);
        $this->assertSame(0, (int) ($second['tables']['inserted'] ?? -1));
    }
}
