<?php

namespace Tests\Feature\Migration;

use App\Models\DashboardUser;
use App\Services\Migration\WpImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class WpResellerPermsImportTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    public function test_imports_reseller_permissions_from_wp_options(): void
    {
        DashboardUser::query()->create([
            'username' => 'reseller1',
            'password' => bcrypt('changeme'),
            'role' => 'reseller',
            'svp_user_id' => 100,
            'permissions_json' => [],
        ]);

        $path = base_path('tests/fixtures/wp-minimal-dump.sql');
        $result = app(WpImportService::class)->run($path, 'wp_');

        $this->assertSame(1, (int) ($result['reseller_perms'] ?? 0));
        $dash = DashboardUser::query()->where('svp_user_id', 100)->first();
        $this->assertNotNull($dash);
        $perms = is_array($dash->permissions_json) ? $dash->permissions_json : [];
        $this->assertTrue($perms['users.manage'] ?? false);
    }
}
