<?php

use App\Http\Controllers\Api\V1\AdminStateController;
use App\Http\Controllers\Api\V1\AdminUserController;
use App\Http\Controllers\Api\V1\AuditController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BootstrapController;
use App\Http\Controllers\Api\V1\DashboardSessionController;
use App\Http\Controllers\Api\V1\ImpersonationController;
use App\Http\Controllers\Api\V1\InboundDisplayCatalogController;
use App\Http\Controllers\Api\V1\LogsController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\MutateController;
use App\Http\Controllers\Api\V1\PurgeExpiredController;
use App\Http\Controllers\Api\V1\UsersBulkController;
use App\Http\Middleware\AdminDashboardRateLimit;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureAdminOrReseller;
use App\Http\Middleware\EnsureBackupModule;
use App\Modules\Backup\Http\BackupController;
use App\Modules\Marketing\Http\BroadcastController;
use App\Modules\XuiPanel\Http\ConfigsController;
use App\Modules\XuiPanel\Http\PanelController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('web');
    Route::get('bootstrap', BootstrapController::class)->middleware('web');

    Route::middleware(['web', 'auth:sanctum', 'dashboard.enabled', 'reseller.scope'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('me/state', [DashboardSessionController::class, 'meState']);
        Route::post('dashboard/persona', [DashboardSessionController::class, 'setPersona']);
        Route::post('dashboard/ui-preferences', [DashboardSessionController::class, 'uiPreferences']);
        Route::get('admin/state', AdminStateController::class)->middleware([EnsureAdminOrReseller::class, AdminDashboardRateLimit::class.':state']);
        Route::get('admin/audit', [AuditController::class, 'index'])->middleware([EnsureAdminOrReseller::class, EnsureAdmin::class]);
        Route::get('admin/logs', [LogsController::class, 'index'])->middleware([EnsureAdminOrReseller::class, EnsureAdmin::class]);
        Route::get('admin/purge-expired', [PurgeExpiredController::class, 'index'])->middleware([EnsureAdminOrReseller::class, EnsureAdmin::class]);
        Route::get('admin/users-bulk-jobs', [UsersBulkController::class, 'jobs'])->middleware(EnsureAdminOrReseller::class);
        Route::get('admin/users-bulk-job-items', [UsersBulkController::class, 'jobItems'])->middleware(EnsureAdminOrReseller::class);
        Route::get('admin/inbound-display-catalog', InboundDisplayCatalogController::class)->middleware(EnsureAdminOrReseller::class);
        Route::post('admin/media', [MediaController::class, 'upload'])->middleware(EnsureAdminOrReseller::class);
        Route::get('admin/user-search', [AdminUserController::class, 'search'])->middleware(EnsureAdminOrReseller::class);
        Route::get('admin/user/{id}', [AdminUserController::class, 'show'])->middleware(EnsureAdminOrReseller::class)->whereNumber('id');
        Route::post('admin/mutate', MutateController::class)->middleware([EnsureAdminOrReseller::class, AdminDashboardRateLimit::class.':mutate']);
        Route::post('admin/impersonate/start', [ImpersonationController::class, 'start']);
        Route::post('admin/impersonate/stop', [ImpersonationController::class, 'stop']);
        Route::post('impersonate/start', [ImpersonationController::class, 'start']);
        Route::post('dashboard/impersonate/stop', [ImpersonationController::class, 'stop']);
        Route::get('admin/configs-snapshot', [ConfigsController::class, 'snapshot'])->middleware(EnsureAdminOrReseller::class);
        Route::get('admin/configs-portal-payload', [ConfigsController::class, 'portalPayload'])->middleware(EnsureAdminOrReseller::class);
        Route::post('admin/configs-sync', [ConfigsController::class, 'sync'])->middleware(EnsureAdminOrReseller::class);
        Route::get('admin/broadcast-queue', [BroadcastController::class, 'queue'])->middleware(EnsureAdminOrReseller::class);
        Route::middleware([EnsureAdminOrReseller::class, EnsureAdmin::class, EnsureBackupModule::class])->group(function () {
            Route::get('admin/backups', [BackupController::class, 'index']);
            Route::get('admin/backup/status', [BackupController::class, 'status']);
            Route::post('admin/backup/run', [BackupController::class, 'run']);
            Route::post('admin/backup/reset-stuck', [BackupController::class, 'resetStuck']);
            Route::get('admin/backup/download', [BackupController::class, 'download']);
            Route::post('admin/backup/restore', [BackupController::class, 'restore']);
            Route::post('admin/backup/restore-upload', [BackupController::class, 'restoreUpload']);
        });
        Route::get('admin/panel-inbounds', [PanelController::class, 'inbounds'])->middleware(EnsureAdminOrReseller::class);
        Route::get('admin/panel-inbound-clients', [PanelController::class, 'inboundClients'])->middleware(EnsureAdminOrReseller::class);
        Route::get('admin/panel/inbound-map', [PanelController::class, 'inboundMapGet'])->middleware(EnsureAdminOrReseller::class);
        Route::post('admin/panel/inbound-map', [PanelController::class, 'inboundMapSave'])->middleware(EnsureAdminOrReseller::class);
        Route::post('admin/panel/rebuild-from-db', [PanelController::class, 'rebuildFromDb'])->middleware([EnsureAdminOrReseller::class, EnsureAdmin::class]);
        Route::post('admin/panel/fix-51200-traffic', [PanelController::class, 'fix51200Traffic'])->middleware([EnsureAdminOrReseller::class, EnsureAdmin::class]);
    });
});
