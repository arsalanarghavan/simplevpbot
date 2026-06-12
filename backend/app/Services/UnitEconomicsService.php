<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class UnitEconomicsService
{
    public function __construct(protected SettingsStore $settings) {}

    /** @param  array<string, mixed>  $payload */
    public function savePanelEconomics(array $payload): array
    {
        $panelId = (int) ($payload['panel_id'] ?? 0);
        if ($panelId < 1) {
            return svp_err('invalid');
        }

        $this->settings->set("panel_economics.{$panelId}", collect($payload)->except(['op', 'panel_id'])->all());

        return svp_ok(['panel_id' => $panelId]);
    }

    /** @param  array<string, mixed>  $payload */
    public function markPanelPaid(array $payload): array
    {
        $panelId = (int) ($payload['panel_id'] ?? 0);
        if ($panelId < 1) {
            return svp_err('invalid');
        }

        DB::table('svp_panel_economics')->updateOrInsert(
            ['panel_id' => $panelId],
            ['paid_at' => now(), 'updated_at' => now()]
        );

        return svp_ok(['panel_id' => $panelId]);
    }

    /** @param  array<string, mixed>  $payload */
    public function saveSharedEconomics(array $payload): array
    {
        $this->settings->merge(collect($payload)->except(['op'])->all());

        return svp_ok();
    }

    /** @param  array<string, mixed>  $payload */
    public function saveUnitEconomics(array $payload): array
    {
        $this->settings->set('unit_economics', collect($payload)->except(['op'])->all());

        return svp_ok();
    }

    /** @param  array<string, mixed>  $payload */
    public function saveUnitEconomicsConfig(array $payload): array
    {
        $this->settings->set('unit_economics_config', collect($payload)->except(['op'])->all());

        return svp_ok();
    }
}
