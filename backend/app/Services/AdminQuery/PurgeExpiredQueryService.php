<?php

namespace App\Services\AdminQuery;

use App\Services\SettingsStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurgeExpiredQueryService
{
    public function __construct(protected SettingsStore $settings) {}

    /** @return array<string, mixed> */
    public function list(array $params): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($params['per_page'] ?? 20)));
        $status = (string) ($params['status'] ?? 'all');
        $graceHours = max(0, (int) $this->settings->get('purge_expired_grace_hours', 24));
        $graceCutoff = now()->subHours($graceHours);

        $items = [];
        $total = 0;
        if (Schema::hasTable('svp_services')) {
            $q = DB::table('svp_services')->whereNull('deleted_at');
            if ($status === 'expired') {
                $q->whereNotNull('expires_at')->where('expires_at', '<', now());
            } elseif ($status === 'ready') {
                $q->whereNotNull('expires_at')
                    ->where('expires_at', '<', $graceCutoff);
            } elseif ($status === 'grace') {
                $q->whereNotNull('expires_at')
                    ->where('expires_at', '<', now())
                    ->where('expires_at', '>=', $graceCutoff);
            } elseif ($status === 'active') {
                $q->where(function ($sub) {
                    $sub->whereNull('expires_at')->orWhere('expires_at', '>=', now());
                });
            }
            $total = (clone $q)->count();
            $items = $q->orderByDesc('id')->offset(($page - 1) * $perPage)->limit($perPage)->get()->all();
        }

        $panelId = (int) ($params['panel_id'] ?? 0);
        $immediate = ['count' => 0, 'ids' => []];
        if ($panelId > 0 && Schema::hasTable('svp_services')) {
            $ids = DB::table('svp_services')
                ->where('panel_id', $panelId)
                ->whereNull('deleted_at')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', now())
                ->limit(50)
                ->pluck('id')
                ->map(fn ($v) => (int) $v)
                ->all();
            $immediate = ['count' => count($ids), 'ids' => $ids];
        }

        return [
            'ok' => true,
            'items' => $items,
            'totals' => ['expired' => $total],
            'pagination' => ['page' => $page, 'perPage' => $perPage, 'total' => $total],
            'settings' => [
                'purge_expired_enabled' => (bool) $this->settings->get('purge_expired_enabled', true),
            ],
            'immediate_batch' => $immediate,
        ];
    }
}
