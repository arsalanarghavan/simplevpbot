<?php

use App\Modules\Backup\Jobs\BackupJob;
use App\Modules\Core\Jobs\AdminAlertsJob;
use App\Modules\Core\Jobs\AutorenewJob;
use App\Modules\Core\Jobs\ExpiryJob;
use App\Modules\Core\Jobs\UsersBulkWorkerJob;
use App\Modules\Marketing\Jobs\BroadcastWorkerJob;
use App\Modules\Marketing\Jobs\IdleOffersJob;
use App\Modules\Marketing\Jobs\MarketingJob;
use App\Modules\XuiPanel\Jobs\InboundClientsCacheJob;
use App\Modules\XuiPanel\Jobs\PanelEconomicsRenewalJob;
use App\Modules\XuiPanel\Jobs\PanelOnlineJob;
use App\Modules\XuiPanel\Jobs\PanelServiceSyncJob;
use App\Modules\XuiPanel\Jobs\PurgeExpiredJob;
use App\Support\Metrics\CronTimer;
use Illuminate\Support\Facades\Schedule;

// Intervals aligned with docs/LARAVEL-BACKEND-SPEC-FA.md §12 (see docs/CRON-SPEC-DEVIATIONS-FA.md).
if (svp_modules()->isEnabled('backup')) {
    $backupInterval = (int) config('svp.backup_interval_minutes', 60);
    Schedule::job(new BackupJob)->cron("*/{$backupInterval} * * * *")->name('svp:backup');
}
Schedule::job(new PurgeExpiredJob)->hourly()->name('svp:purge_expired');
Schedule::job(new BroadcastWorkerJob)->everyMinute()->name('svp:broadcast');
Schedule::job(new UsersBulkWorkerJob)->everyMinute()->name('svp:users_bulk');
if (svp_modules()->isEnabled('xui_panel')) {
    Schedule::job(new PanelOnlineJob)->everyTenMinutes()->name('svp:panel_online');
    Schedule::job(new PanelServiceSyncJob)->everyTenMinutes()->name('svp:panel_service_sync');
    Schedule::job(new InboundClientsCacheJob)->everyTenMinutes()->name('svp:inbound_clients_cache');
}
Schedule::job(new ExpiryJob)->hourly()->name('svp:expiry');
Schedule::job(new AutorenewJob)->hourly()->name('svp:autorenew');
if (svp_modules()->isEnabled('marketing')) {
    Schedule::job(new IdleOffersJob)->hourly()->name('svp:idle_offers');
    Schedule::job(new MarketingJob)->hourly()->name('svp:marketing');
}
Schedule::job(new AdminAlertsJob)->everyTenMinutes()->name('svp:admin_alerts');
Schedule::job(new PanelEconomicsRenewalJob)->hourly()->name('svp:panel_economics_renewal');
Schedule::call(fn () => CronTimer::run('svp:inbound_queue_drain', fn () => app(\App\Services\Bot\InboundQueueService::class)->drainBatch()))
    ->everyMinute()
    ->name('svp:inbound_queue_drain');
