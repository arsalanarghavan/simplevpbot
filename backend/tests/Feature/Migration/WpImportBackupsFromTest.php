<?php

namespace Tests\Feature\Migration;

use App\Services\Migration\WpImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class WpImportBackupsFromTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    public function test_backups_from_copies_matching_zip_files(): void
    {
        $src = storage_path('framework/testing/backups-src');
        File::ensureDirectoryExists($src);
        $zip = $src.'/simplevpbot-backup-test123.zip';
        File::put($zip, 'fake-zip');

        $path = base_path('tests/fixtures/wp-minimal-dump.sql');
        $result = app(WpImportService::class)->run($path, 'wp_', false, false, $src);

        $this->assertSame(1, (int) ($result['backup_files'] ?? 0));
        $this->assertFileExists(storage_path('app/backups/simplevpbot-backup-test123.zip'));

        File::deleteDirectory($src);
    }
}
