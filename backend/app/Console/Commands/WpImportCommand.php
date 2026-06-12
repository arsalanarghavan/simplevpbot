<?php

namespace App\Console\Commands;

use App\Services\Migration\WpImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class WpImportCommand extends Command
{
    protected $signature = 'wp:import
        {dump? : Path to WordPress MySQL dump SQL file}
        {--prefix=wp_ : WP table prefix}
        {--dry-run : Parse and report without writing}
        {--force : Overwrite existing rows by id}
        {--verify-only : Compare dump row counts with current database}
        {--backups-from= : Copy backup zips from WP uploads directory}
        {--default-password=changeme : Default bcrypt password for imported dashboard users}
        {--mysql-dsn= : Live MySQL DSN user:pass@host:port/database — dumps to temp file then imports}
        {--since= : Incremental import — skip rows with created_at/updated_at before this UTC datetime (Y-m-d H:i:s)}';

    protected $description = 'Import WordPress SimpleVPBot data from SQL dump into Laravel';

    public function handle(WpImportService $import): int
    {
        $path = (string) ($this->argument('dump') ?? '');
        $prefix = (string) $this->option('prefix');
        $dsn = (string) $this->option('mysql-dsn');
        if ($dsn !== '') {
            $path = $this->dumpFromMysqlDsn($dsn, $prefix);
            if ($path === '') {
                return self::FAILURE;
            }
        }

        if ($this->option('verify-only')) {
            if ($path === '' || ! File::exists($path)) {
                $this->error('Dump path required for --verify-only');

                return self::FAILURE;
            }

            return $this->renderVerify($import->verifyOnly($path, $prefix));
        }

        if ($path === '' || ! File::exists($path)) {
            $this->error("Dump not found: {$path}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('Dry run — no data will be written.');
        }

        $result = $import->run(
            $path,
            $prefix,
            $dryRun,
            (bool) $this->option('force'),
            $this->option('backups-from') ?: null,
            (string) $this->option('default-password'),
            $this->option('since') ?: null,
        );

        $tables = $result['tables'] ?? [];
        $this->info(sprintf(
            'Tables: %d tables, %d rows inserted, %d skipped',
            (int) ($tables['tables'] ?? 0),
            (int) ($tables['inserted'] ?? 0),
            (int) ($tables['skipped'] ?? 0),
        ));
        $this->info('Settings keys: '.(int) ($result['settings'] ?? 0));
        $this->info('Dashboard users: '.(int) ($result['dashboard_users'] ?? 0));
        $this->info('Reseller perms: '.(int) ($result['reseller_perms'] ?? 0));
        $this->info('Backup files: '.(int) ($result['backup_files'] ?? 0));

        if (! $dryRun) {
            return $this->renderVerify($result['verify'] ?? ['ok' => false, 'rows' => []]);
        }

        $this->info('Dry run complete.');

        return self::SUCCESS;
    }

    /** @param  array{ok:bool, rows:array<int, array<string, mixed>>}  $verify */
    protected function renderVerify(array $verify): int
    {
        $rows = $verify['rows'] ?? [];
        if ($rows !== []) {
            $this->table(
                ['table', 'source', 'target', 'match'],
                array_map(fn ($r) => [
                    $r['table'],
                    $r['source'],
                    $r['target'],
                    ($r['match'] ?? false) ? 'yes' : 'NO',
                ], $rows)
            );
        }

        if (! empty($verify['ok'])) {
            $this->info('Verification passed.');

            return self::SUCCESS;
        }

        $this->error('Verification failed — row count mismatch.');

        return self::FAILURE;
    }

    protected function dumpFromMysqlDsn(string $dsn, string $prefix): string
    {
        if (! preg_match('#^([^:]+):([^@]*)@([^:]+):(\d+)/(.+)$#', $dsn, $m)) {
            $this->error('Invalid --mysql-dsn format. Use user:pass@host:port/database');

            return '';
        }
        [, $user, $pass, $host, $port, $db] = $m;
        $out = storage_path('app/wp-live-'.date('Ymd-His').'.sql');
        $full = sprintf(
            'mysqldump -h %s -P %s -u %s %s %s > %s',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($user),
            $pass !== '' ? '-p'.escapeshellarg($pass) : '',
            escapeshellarg($db),
            escapeshellarg($out),
        );
        $this->info('Dumping live MySQL to '.$out);
        exec($full, $output, $code);
        if ($code !== 0 || ! File::exists($out)) {
            $this->error('mysqldump failed');

            return '';
        }

        return $out;
    }
}
