<?php

namespace Tests\Feature\Migration;

use App\Models\DashboardUser;
use App\Services\Migration\WpImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

/** wp_usermeta svp_dashboard_accent → dashboard_users.ui_accent (v17). */
class WpImportAccentMetaTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    public function test_wp_usermeta_accent_imported_to_ui_accent(): void
    {
        $path = base_path('tests/fixtures/wp-minimal-dump.sql');
        app(WpImportService::class)->run($path, 'wp_', false, false, null, 'import-pass');

        $admin = DashboardUser::query()->where('username', 'wpadmin')->first();
        $this->assertNotNull($admin);
        $this->assertSame('blue', $admin->ui_accent);

        $reseller = DashboardUser::query()->where('username', 'reseller1')->first();
        $this->assertNotNull($reseller);
        $this->assertSame('dark', $reseller->ui_theme);
    }
}
