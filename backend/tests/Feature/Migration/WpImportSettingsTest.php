<?php

namespace Tests\Feature\Migration;

use App\Services\Migration\WpImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class WpImportSettingsTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    public function test_imports_settings_with_encrypted_secrets(): void
    {
        $path = base_path('tests/fixtures/wp-minimal-dump.sql');
        app(WpImportService::class)->run($path, 'wp_');

        $enabled = DB::table('svp_settings')->where('key_name', 'enabled')->value('value');
        $this->assertSame('1', $enabled);

        $token = (string) DB::table('svp_settings')->where('key_name', 'telegram_bot_token')->value('value');
        $this->assertNotSame('secret-token-123', $token);
        $this->assertSame('secret-token-123', Crypt::decryptString($token));
    }
}
