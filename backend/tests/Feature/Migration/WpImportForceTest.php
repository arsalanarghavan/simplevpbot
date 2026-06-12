<?php

namespace Tests\Feature\Migration;

use App\Services\Migration\WpImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class WpImportForceTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    public function test_force_overwrites_existing_row(): void
    {
        $path = base_path('tests/fixtures/wp-minimal-dump.sql');
        $svc = app(WpImportService::class);

        $svc->run($path, 'wp_');
        DB::table('svp_users')->where('id', 1)->update(['username' => 'stale']);

        $second = $svc->run($path, 'wp_', false, true);
        $this->assertGreaterThan(0, (int) ($second['tables']['inserted'] ?? 0));
        $this->assertSame('user1', (string) DB::table('svp_users')->where('id', 1)->value('username'));
    }
}
