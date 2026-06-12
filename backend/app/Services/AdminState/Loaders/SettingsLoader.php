<?php

namespace App\Services\AdminState\Loaders;

use App\Services\AdminState\AdminStateContext;
use App\Services\AdminState\AdminStateResult;
use App\Services\DashboardBootBuilder;
use App\Services\SettingsStore;

class SettingsLoader extends AbstractLoader
{
    public function __construct(
        protected SettingsStore $settings,
        protected DashboardBootBuilder $bootBuilder,
    ) {}

    protected function shouldLoad(AdminStateContext $ctx): bool
    {
        return true;
    }

    protected function load(AdminStateContext $ctx, AdminStateResult $result): void
    {
        $boot = $this->bootBuilder->bootstrapApiPayload($ctx->actor);
        $all = $this->settings->all();
        $all['features'] = $boot['features'] ?? [];

        if ($ctx->isReseller) {
            foreach (['telegram_bot_token', 'bale_bot_token', 'relay_master_secret'] as $secret) {
                unset($all[$secret]);
            }
        }

        $result->merge([
            'settings' => $all,
            'textDefaults' => is_array($all['text_defaults'] ?? null) ? $all['text_defaults'] : [],
            'paymentMethods' => $all['payment_methods'] ?? null,
        ]);
    }
}
