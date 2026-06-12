<?php

namespace Tests\Feature\Migration;

use App\Services\Migration\WpImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class WpImportVerifyOnlyTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    public function test_verify_only_passes_after_import(): void
    {
        $path = base_path('tests/fixtures/wp-minimal-dump.sql');
        $svc = app(WpImportService::class);
        $svc->run($path, 'wp_');

        $verify = $svc->verifyOnly($path, 'wp_');
        $this->assertTrue($verify['ok']);
    }
}
