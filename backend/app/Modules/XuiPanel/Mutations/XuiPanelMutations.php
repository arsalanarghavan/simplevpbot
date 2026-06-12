<?php

namespace App\Modules\XuiPanel\Mutations;

use App\Models\SvpService;
use App\Modules\XuiPanel\Services\ConfigsSyncService;
use App\Modules\XuiPanel\Services\ServicePanelTransferService;
use App\Services\Commerce\ServiceProvisioner;
use App\Modules\XuiPanel\Services\XuiClient;
use App\Services\UnitEconomicsService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class XuiPanelMutations
{
    public function __construct(
        protected XuiClient $xui,
        protected ConfigsSyncService $configs,
        protected UnitEconomicsService $economics,
        protected ServicePanelTransferService $panelTransfer,
    ) {}

    /** @return array<string, array{0: class-string, 1: string}> */
    public function handlers(): array
    {
        return [
            'panel_xp' => [self::class, 'panelXp'],
            'panel_test' => [self::class, 'panelTest'],
            'service_panel_sync' => [self::class, 'servicePanelSync'],
            'service_panel_refresh' => [self::class, 'servicePanelRefresh'],
            'service_panel_delete_client' => [self::class, 'servicePanelDeleteClient'],
            'service_panel_transfer' => [self::class, 'servicePanelTransfer'],
            'service_apply_canonical_panel_identity' => [self::class, 'serviceApplyCanonicalPanelIdentity'],
            'service_regen_key' => [self::class, 'serviceRegenKey'],
            'service_regen_sub_id' => [self::class, 'serviceRegenSubId'],
            'service_set_limit_ip' => [self::class, 'serviceSetLimitIp'],
            'service_alerts_patch' => [self::class, 'serviceAlertsPatch'],
            'configs_panel_client_patch' => [self::class, 'configsPanelClientPatch'],
            'configs_clients_batch' => [self::class, 'configsClientsBatch'],
            'configs_assign_plan' => [self::class, 'configsAssignPlan'],
            'configs_client_toggle_enable' => [self::class, 'configsClientToggleEnable'],
            'configs_client_reset_traffic' => [self::class, 'configsClientResetTraffic'],
            'configs_client_delete' => [self::class, 'configsClientDelete'],
            'configs_delete_expired_linked' => [self::class, 'configsDeleteExpiredLinked'],
            'inbound_link' => [self::class, 'inboundLink'],
            'inbound_autolink' => [self::class, 'inboundAutolink'],
            'purge_expired_run_cron' => [self::class, 'purgeExpiredRunCron'],
            'purge_expired_purge_ready' => [self::class, 'purgeExpiredPurgeReady'],
            'purge_expired_purge_one' => [self::class, 'purgeExpiredPurgeOne'],
            'panel_economics_save' => [self::class, 'panelEconomicsSave'],
            'panel_economics_mark_paid' => [self::class, 'panelEconomicsMarkPaid'],
            'shared_economics_save' => [self::class, 'sharedEconomicsSave'],
            'unit_economics_save' => [self::class, 'unitEconomicsSave'],
            'unit_economics_config_save' => [self::class, 'unitEconomicsConfigSave'],
        ];
    }

    /** @param  array<string, mixed>  $payload */
    public function panelXp(array $payload, ?Authenticatable $actor): array
    {
        $id = (int) ($payload['id'] ?? 0);
        $data = collect($payload)->except(['op', 'id'])->all();
        if ($id > 0) {
            DB::table('svp_panels')->where('id', $id)->update($data);

            return svp_ok(['panel_id' => $id]);
        }
        $newId = DB::table('svp_panels')->insertGetId(array_merge($data, ['created_at' => now()]));

        return svp_ok(['panel_id' => $newId]);
    }

    /** @param  array<string, mixed>  $payload */
    public function panelTest(array $payload, ?Authenticatable $actor): array
    {
        $panel = DB::table('svp_panels')->where('id', (int) ($payload['panel_id'] ?? 0))->first();
        if (! $panel) {
            return svp_err('not_found');
        }

        return svp_ok($this->xui->testConnection((array) $panel));
    }

    /** @param  array<string, mixed>  $payload */
    public function servicePanelSync(array $payload, ?Authenticatable $actor): array
    {
        $serviceId = (int) ($payload['service_id'] ?? 0);
        $svc = SvpService::query()->find($serviceId);
        if (! $svc) {
            return svp_err('not_found');
        }
        $panel = DB::table('svp_panels')->where('id', (int) $svc->panel_id)->first();
        if (! $panel) {
            return svp_err('panel_not_found');
        }

        $result = $this->xui->syncService((array) $panel, $serviceId);
        $this->configs->syncInboundsAfterMutation((int) $svc->panel_id, [(int) $svc->inbound_id]);

        return svp_ok($result);
    }

    /** @param  array<string, mixed>  $payload */
    public function servicePanelRefresh(array $payload, ?Authenticatable $actor): array
    {
        $serviceId = (int) ($payload['service_id'] ?? 0);
        $svc = SvpService::query()->find($serviceId);
        if (! $svc) {
            return svp_err('not_found');
        }
        $panel = DB::table('svp_panels')->where('id', (int) $svc->panel_id)->first();
        if (! $panel) {
            return svp_err('panel_not_found');
        }

        return svp_ok($this->xui->refreshInbound((array) $panel, $serviceId));
    }

    /** @param  array<string, mixed>  $payload */
    public function servicePanelDeleteClient(array $payload, ?Authenticatable $actor): array
    {
        $serviceId = (int) ($payload['service_id'] ?? 0);
        $svc = SvpService::query()->find($serviceId);
        if (! $svc) {
            return svp_err('not_found');
        }
        $panel = DB::table('svp_panels')->where('id', (int) $svc->panel_id)->first();

        $result = $this->xui->deleteClient((array) ($panel ?? []), $serviceId);
        if ($svc) {
            $this->configs->syncInboundsAfterMutation((int) $svc->panel_id, [(int) $svc->inbound_id]);
        }

        return svp_ok($result);
    }

    /** @param  array<string, mixed>  $payload */
    public function servicePanelTransfer(array $payload, ?Authenticatable $actor): array
    {
        return $this->panelTransfer->transferFromPayload($payload, $actor);
    }

    /** @param  array<string, mixed>  $payload */
    public function serviceApplyCanonicalPanelIdentity(array $payload, ?Authenticatable $actor): array
    {
        $serviceId = (int) ($payload['service_id'] ?? 0);
        if ($serviceId < 1) {
            return svp_err('invalid');
        }
        $svc = DB::table('svp_services')->where('id', $serviceId)->whereNull('deleted_at')->first();
        if (! $svc) {
            return svp_err('not_found');
        }
        $user = DB::table('svp_users')->where('id', (int) $svc->user_id)->first();
        $canonical = \App\Support\Xui\ServiceNaming::provisionCanonicalLabel($user, null, 1);
        $email = \App\Support\Xui\ServiceNaming::uniquePanelClientId($canonical);
        DB::table('svp_services')->where('id', $serviceId)->update([
            'remark' => $canonical,
            'email' => $email,
            'display_label' => $canonical,
        ]);
        if ((int) $svc->panel_id > 0 && (int) $svc->inbound_id > 0 && trim((string) $svc->email) !== '') {
            $this->configs->patchClient([
                'service_id' => $serviceId,
                'client_remark' => $canonical,
                'client_email_new' => $email,
            ]);
        }

        return svp_ok(['service_id' => $serviceId, 'canonical' => $canonical, 'email' => $email]);
    }

    /** @param  array<string, mixed>  $payload */
    public function serviceRegenKey(array $payload, ?Authenticatable $actor): array
    {
        $serviceId = (int) ($payload['service_id'] ?? 0);
        if ($serviceId < 1) {
            return svp_err('invalid');
        }
        if (! SvpService::query()->find($serviceId)) {
            return svp_err('not_found');
        }

        return svp_ok($this->xui->regenerateKey($serviceId));
    }

    /** @param  array<string, mixed>  $payload */
    public function serviceRegenSubId(array $payload, ?Authenticatable $actor): array
    {
        $serviceId = (int) ($payload['service_id'] ?? 0);
        if ($serviceId < 1) {
            return svp_err('invalid');
        }
        if (! SvpService::query()->find($serviceId)) {
            return svp_err('not_found');
        }
        $r = $this->xui->regenerateSubId($serviceId);

        return svp_ok(['sub_id' => $r['sub_id'] ?? '']);
    }

    /** @param  array<string, mixed>  $payload */
    public function serviceSetLimitIp(array $payload, ?Authenticatable $actor): array
    {
        $serviceId = (int) ($payload['service_id'] ?? 0);
        $limit = (int) ($payload['limit_ip'] ?? $payload['limit'] ?? 0);
        if ($serviceId < 1) {
            return svp_err('invalid');
        }

        return svp_ok($this->xui->setLimitIp($serviceId, $limit));
    }

    /** @param  array<string, mixed>  $payload */
    public function serviceAlertsPatch(array $payload, ?Authenticatable $actor): array
    {
        $serviceId = (int) ($payload['service_id'] ?? 0);
        if ($serviceId < 1) {
            return svp_err('invalid');
        }
        $alerts = $payload['alerts'] ?? $payload;
        DB::table('svp_services')->where('id', $serviceId)->update([
            'alerts_json' => json_encode(is_array($alerts) ? $alerts : []),
        ]);

        return svp_ok(['service_id' => $serviceId]);
    }

    /** @param  array<string, mixed>  $payload */
    public function configsPanelClientPatch(array $payload, ?Authenticatable $actor): array
    {
        return $this->configs->patchClient($payload);
    }

    /** @param  array<string, mixed>  $payload */
    public function configsClientsBatch(array $payload, ?Authenticatable $actor): array
    {
        return $this->configs->clientsBatch($payload);
    }

    /** @param  array<string, mixed>  $payload */
    public function configsAssignPlan(array $payload, ?Authenticatable $actor): array
    {
        return $this->configs->assignPlan($payload);
    }

    /** @param  array<string, mixed>  $payload */
    public function configsClientToggleEnable(array $payload, ?Authenticatable $actor): array
    {
        return $this->configs->toggleEnable($payload);
    }

    /** @param  array<string, mixed>  $payload */
    public function configsClientResetTraffic(array $payload, ?Authenticatable $actor): array
    {
        return $this->configs->resetTraffic($payload);
    }

    /** @param  array<string, mixed>  $payload */
    public function configsClientDelete(array $payload, ?Authenticatable $actor): array
    {
        return $this->configs->deleteClient($payload);
    }

    /** @param  array<string, mixed>  $payload */
    public function configsDeleteExpiredLinked(array $payload, ?Authenticatable $actor): array
    {
        return $this->configs->deleteExpiredLinked();
    }

    /** @param  array<string, mixed>  $payload */
    public function inboundLink(array $payload, ?Authenticatable $actor): array
    {
        $inboundId = (int) ($payload['inbound_id'] ?? 0);
        $panelId = (int) ($payload['panel_id'] ?? 0);
        if ($inboundId < 1 || $panelId < 1) {
            return svp_err('invalid');
        }
        if (Schema::hasTable('svp_inbounds')) {
            DB::table('svp_inbounds')->updateOrInsert(
                ['panel_id' => $panelId, 'inbound_id' => $inboundId],
                ['linked_at' => now()]
            );
        }

        return svp_ok(['inbound_id' => $inboundId, 'panel_id' => $panelId]);
    }

    /** @param  array<string, mixed>  $payload */
    public function inboundAutolink(array $payload, ?Authenticatable $actor): array
    {
        $panelId = (int) ($payload['panel_id'] ?? 1);
        $inboundId = (int) ($payload['inbound_id'] ?? 0);
        if ($inboundId < 1) {
            return svp_err('invalid');
        }
        $linked = 0;
        if (Schema::hasTable('svp_panel_inbound_clients')) {
            $clients = DB::table('svp_panel_inbound_clients')
                ->where('panel_id', $panelId)
                ->where('inbound_id', $inboundId)
                ->get(['email', 'remark']);
            foreach ($clients as $client) {
                $email = trim((string) ($client->email ?? ''));
                if ($email === '') {
                    continue;
                }
                $q = DB::table('svp_services')
                    ->whereNull('deleted_at')
                    ->where('panel_id', $panelId)
                    ->where(function ($sub) use ($email, $client) {
                        $sub->where('email', $email);
                        $remark = trim((string) ($client->remark ?? ''));
                        if ($remark !== '') {
                            $sub->orWhere('remark', $remark);
                        }
                    })
                    ->where(function ($q) use ($inboundId) {
                        $q->where('inbound_id', 0)->orWhereNull('inbound_id')->orWhere('inbound_id', '!=', $inboundId);
                    });
                $linked += (int) $q->update(['inbound_id' => $inboundId]);
            }
        }
        if (Schema::hasTable('svp_plans')) {
            $linked += (int) DB::table('svp_plans')
                ->where('panel_id', $panelId)
                ->where(function ($q) {
                    $q->where('inbound_id', 0)->orWhereNull('inbound_id');
                })
                ->limit(50)
                ->update(['inbound_id' => $inboundId]);
        }

        return svp_ok(['panel_id' => $panelId, 'inbound_id' => $inboundId, 'linked' => $linked]);
    }

    /** @param  array<string, mixed>  $payload */
    public function purgeExpiredRunCron(array $payload, ?Authenticatable $actor): array
    {
        \App\Modules\XuiPanel\Jobs\PurgeExpiredJob::dispatchSync();

        return svp_ok();
    }

    /** @param  array<string, mixed>  $payload */
    public function purgeExpiredPurgeReady(array $payload, ?Authenticatable $actor): array
    {
        if (empty($payload['confirm'])) {
            return svp_err('confirm_required');
        }
        $limit = max(1, min(100, (int) ($payload['limit'] ?? 50)));
        for ($i = 0; $i < $limit; ++$i) {
            \App\Modules\XuiPanel\Jobs\PurgeExpiredJob::dispatchSync();
        }
        $ready = DB::table('svp_services')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->whereNull('deleted_at')
            ->count();

        return svp_ok(['data' => ['purged' => $limit, 'failed' => 0, 'ready' => $ready]]);
    }

    /** @param  array<string, mixed>  $payload */
    public function purgeExpiredPurgeOne(array $payload, ?Authenticatable $actor): array
    {
        $serviceId = (int) ($payload['service_id'] ?? 0);
        if ($serviceId < 1) {
            return svp_err('invalid');
        }
        $svc = DB::table('svp_services')->where('id', $serviceId)->whereNull('deleted_at')->first();
        if (! $svc) {
            return svp_err('not_found');
        }
        if ((int) ($svc->inbound_id ?? 0) > 0 && trim((string) ($svc->email ?? '')) !== '') {
            $this->xui->deleteClient([], $serviceId);
        }
        DB::table('svp_services')->where('id', $serviceId)->update(['deleted_at' => now()]);

        return svp_ok(['service_id' => $serviceId]);
    }

    /** @param  array<string, mixed>  $payload */
    public function panelEconomicsSave(array $payload, ?Authenticatable $actor): array
    {
        return $this->economics->savePanelEconomics($payload);
    }

    /** @param  array<string, mixed>  $payload */
    public function panelEconomicsMarkPaid(array $payload, ?Authenticatable $actor): array
    {
        return $this->economics->markPanelPaid($payload);
    }

    /** @param  array<string, mixed>  $payload */
    public function sharedEconomicsSave(array $payload, ?Authenticatable $actor): array
    {
        return $this->economics->saveSharedEconomics($payload);
    }

    /** @param  array<string, mixed>  $payload */
    public function unitEconomicsSave(array $payload, ?Authenticatable $actor): array
    {
        return $this->economics->saveUnitEconomics($payload);
    }

    /** @param  array<string, mixed>  $payload */
    public function unitEconomicsConfigSave(array $payload, ?Authenticatable $actor): array
    {
        return $this->economics->saveUnitEconomicsConfig($payload);
    }
}
