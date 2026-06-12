<?php

namespace App\Services\Commerce;

use App\Models\SvpPlan;
use Illuminate\Support\Facades\DB;

class CheckoutPriceRenewService
{
    public function checkoutPriceRenew(object $service, ?SvpPlan $plan = null): float
    {
        $planId = (int) ($service->plan_id ?? 0);
        if (! $plan && $planId > 0) {
            $plan = SvpPlan::query()->find($planId);
        }
        if (! $plan) {
            return 0.0;
        }

        $renew = (float) ($plan->renew_price ?? 0);
        if ($renew > 0 && (string) ($plan->pricing_type ?? '') !== 'per_gb') {
            return $renew;
        }

        if ((string) ($plan->pricing_type ?? '') === 'per_gb') {
            $gb = $this->renewGbCount($service, $plan);

            return round((float) ($plan->price_per_gb ?? 0) * $gb, 2);
        }

        return round((float) ($plan->price ?? 0), 2);
    }

    public function renewGbCount(object $service, SvpPlan $plan): int
    {
        $cap = (int) ($service->total_traffic ?? 0);
        $gb = $cap > 0 ? (int) ceil($cap / 1073741824) : (int) ($plan->traffic_gb ?? 0);
        $min = max(1, (int) ($plan->traffic_gb_min ?? 1));
        $max = max($min, (int) ($plan->traffic_gb_max ?? $min));

        return max($min, min($max, $gb));
    }
}
