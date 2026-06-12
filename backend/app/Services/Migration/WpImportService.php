<?php

namespace App\Services\Migration;

class WpImportService
{
    public function __construct(
        protected WpDumpParser $parser,
        protected WpTableImporter $tables,
        protected WpSettingsImporter $settings,
        protected WpResellerPermsImporter $resellerPerms,
        protected WpDashboardUserImporter $dashboardUsers,
        protected WpBackupFilesImporter $backups,
        protected WpImportVerifier $verifier,
    ) {}

    public function parse(string $path, string $prefix = 'wp_'): WpDumpData
    {
        return $this->parser->parseFile($path, $prefix);
    }

    /**
     * @return array<string, mixed>
     */
    public function run(
        string $path,
        string $prefix = 'wp_',
        bool $dryRun = false,
        bool $force = false,
        ?string $backupsFrom = null,
        string $defaultPassword = 'changeme',
        ?string $since = null,
    ): array {
        $dump = $this->parse($path, $prefix);

        $tableStats = $this->tables->import($dump->tables, $force, $dryRun, $since);
        $settingsCount = $this->settings->import($dump->options, $dryRun);
        $dashboardCount = $this->dashboardUsers->import(
            $dump->wpUsers,
            $dump->wpUsermeta,
            $dump->tables,
            $defaultPassword,
            $dryRun,
        );
        $permsCount = $this->resellerPerms->import($dump->options, $dryRun);
        $backupCount = $this->backups->import($backupsFrom, $dryRun);

        $verify = $dryRun
            ? ['ok' => true, 'rows' => []]
            : $this->verifier->verify($dump);

        return [
            'tables' => $tableStats,
            'settings' => $settingsCount,
            'dashboard_users' => $dashboardCount,
            'reseller_perms' => $permsCount,
            'backup_files' => $backupCount,
            'verify' => $verify,
        ];
    }

    /** @return array{ok:bool, rows:array<int, array<string, mixed>>} */
    public function verifyOnly(string $path, string $prefix = 'wp_'): array
    {
        $dump = $this->parse($path, $prefix);

        return $this->verifier->verify($dump);
    }
}
