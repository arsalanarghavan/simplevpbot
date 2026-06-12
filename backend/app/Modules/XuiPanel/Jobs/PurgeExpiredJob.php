<?php

namespace App\Modules\XuiPanel\Jobs;

use App\Modules\XuiPanel\Services\XuiClient;
use App\Support\Metrics\CronTimer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurgeExpiredJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(XuiClient $xui): void
    {
        CronTimer::run('svp:purge_expired', function () use ($xui) {
            $this->purge($xui);
        });
    }

    protected function purge(XuiClient $xui): void
    {
        if (! Schema::hasTable('svp_services')) {
            return;
        }
        $query = DB::table('svp_services')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->whereNull('deleted_at');
        if (Schema::hasColumn('svp_services', 'purge_ready')) {
            $query->where(function ($q) {
                $q->whereNull('purge_ready')->orWhere('purge_ready', 1);
            });
        }
        $rows = $query->limit(20)->get();
        foreach ($rows as $svc) {
            if ((int) ($svc->inbound_id ?? 0) > 0 && trim((string) ($svc->email ?? '')) !== '') {
                $xui->deleteClient([], (int) $svc->id);
            } else {
                DB::table('svp_services')->where('id', $svc->id)->update(['deleted_at' => now()]);
            }
        }
    }
}
