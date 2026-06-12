<?php

namespace Tests\Feature\Migration;

use App\Models\DashboardUser;
use App\Services\Migration\WpImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class WpImportDashboardUsersTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    public function test_imports_admin_and_reseller_dashboard_users(): void
    {
        $path = base_path('tests/fixtures/wp-minimal-dump.sql');
        app(WpImportService::class)->run($path, 'wp_', false, false, null, 'import-pass');

        $admin = DashboardUser::query()->where('username', 'wpadmin')->first();
        $this->assertNotNull($admin);
        $this->assertSame('admin', $admin->role);
        $this->assertSame('blue', $admin->ui_accent);
        $this->assertTrue(Hash::check('import-pass', $admin->password));

        $reseller = DashboardUser::query()->where('username', 'reseller1')->first();
        $this->assertNotNull($reseller);
        $this->assertSame('reseller', $reseller->role);
        $this->assertSame(100, (int) $reseller->svp_user_id);
        $this->assertSame('dark', $reseller->ui_theme);
        $this->assertIsArray($reseller->permissions_json);
        $this->assertTrue($reseller->permissions_json['users.manage'] ?? false);
    }
}
