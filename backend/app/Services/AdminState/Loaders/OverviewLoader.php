<?php

namespace App\Services\AdminState\Loaders;

use App\Models\SvpReceipt;
use App\Models\SvpUser;
use App\Services\AdminState\AdminStateContext;
use App\Services\AdminState\AdminStateResult;
use Illuminate\Support\Facades\DB;

class OverviewLoader extends AbstractLoader
{
    protected function shouldLoad(AdminStateContext $ctx): bool
    {
        return $ctx->needsOverview();
    }

    protected function load(AdminStateContext $ctx, AdminStateResult $result): void
    {
        $overview = [
            'users_total' => 0,
            'users_pending' => 0,
            'receipts_pending' => 0,
            'panels_total' => 0,
            'panels_active' => 0,
            'services_active' => 0,
        ];

        if ($this->tableExists('svp_users')) {
            $userQ = SvpUser::query();
            if ($ctx->moderatableUserIds !== []) {
                $userQ->whereIn('id', $ctx->moderatableUserIds);
            } elseif ($ctx->isReseller) {
                $userQ->whereRaw('1=0');
            }
            $overview['users_total'] = (clone $userQ)->count();
            $overview['users_pending'] = (clone $userQ)->where('status', 'pending')->count();
        }

        if ($this->tableExists('svp_receipts')) {
            $rcptQ = SvpReceipt::query()->where('status', 'pending');
            if ($ctx->moderatableUserIds !== []) {
                $rcptQ->whereIn('user_id', $ctx->moderatableUserIds);
            } elseif ($ctx->isReseller) {
                $rcptQ->whereRaw('1=0');
            }
            $overview['receipts_pending'] = $rcptQ->count();
        }

        if ($this->tableExists('svp_panels')) {
            $panelQ = DB::table('svp_panels');
            if ($ctx->allowedPanelIds !== []) {
                $panelQ->whereIn('id', $ctx->allowedPanelIds);
            }
            $overview['panels_total'] = (clone $panelQ)->count();
            $overview['panels_active'] = (clone $panelQ)->where('active', 1)->count();
        }

        if ($this->tableExists('svp_services')) {
            $svcQ = DB::table('svp_services')->whereNull('deleted_at');
            if ($ctx->moderatableUserIds !== []) {
                $svcQ->whereIn('user_id', $ctx->moderatableUserIds);
            }
            $overview['services_active'] = $svcQ->count();
        }

        $result->merge(['overview' => $overview]);

        if ($ctx->isReseller && $ctx->actorSvpUserId > 0) {
            $result->merge([
                'resellerOverviewMetrics' => [
                    'users_total' => $overview['users_total'],
                    'receipts_pending' => $overview['receipts_pending'],
                ],
            ]);
        }
    }
}
