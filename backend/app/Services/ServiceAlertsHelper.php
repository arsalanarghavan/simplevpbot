<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ServiceAlertsHelper
{
    public function effectiveIpFillPct(object $svc): int
    {
        $sched = $this->parseAlertSchedule($svc);
        if (isset($sched['ip_fill_pct'])) {
            $p = (int) $sched['ip_fill_pct'];
            if ($p >= 50 && $p <= 100) {
                return $p;
            }
        }
        if (isset($svc->alert_ip_fill_pct) && $svc->alert_ip_fill_pct !== null && (string) $svc->alert_ip_fill_pct !== '') {
            $p = (int) $svc->alert_ip_fill_pct;
            if ($p >= 50 && $p <= 100) {
                return $p;
            }
        }

        return 90;
    }

    public function clientLimitIp(object $svc): int
    {
        $limit = (int) ($svc->panel_limit_ip ?? $svc->limit_ip ?? 0);
        if ($limit > 0) {
            return $limit;
        }
        return 0;
    }

    public function clientIpCount(object $svc): int
    {
        if (Schema::hasTable('svp_panel_inbound_clients')) {
            $row = DB::table('svp_panel_inbound_clients')
                ->where('panel_id', max(1, (int) ($svc->panel_id ?? 1)))
                ->where('inbound_id', (int) ($svc->inbound_id ?? 0))
                ->where('email', (string) ($svc->email ?? ''))
                ->first();
            if ($row && ! empty($row->client_ips_json)) {
                $ips = json_decode((string) $row->client_ips_json, true);
                if (is_array($ips)) {
                    return count($ips);
                }
            }
        }
        if (Schema::hasTable('svp_service_ip_log')) {
            return (int) DB::table('svp_service_ip_log')
                ->where('service_id', (int) $svc->id)
                ->distinct()
                ->count('ip');
        }

        return 0;
    }

    public function usersAlertEnabled(object $svc): bool
    {
        if (isset($svc->alert_users_enabled) && (int) $svc->alert_users_enabled === 0) {
            return false;
        }

        return true;
    }

    public function expiryAlertEnabled(object $svc): bool
    {
        if (isset($svc->alert_expiry_enabled) && (int) $svc->alert_expiry_enabled === 0) {
            return false;
        }

        return true;
    }

    /** @return array<string, mixed> */
    protected function parseAlertSchedule(object $svc): array
    {
        if (empty($svc->alert_schedule_json)) {
            return [];
        }
        $dec = json_decode((string) $svc->alert_schedule_json, true);

        return is_array($dec) ? $dec : [];
    }
}
