<?php

namespace App\Modules\Core\Mutations;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Services\UserBotNotifyService;
use App\Modules\Relay\Services\TelegramRelayService;
use App\Services\SettingsStore;
use App\Services\SettingsTabService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CoreMutations
{
    public function __construct(
        protected SettingsStore $settings,
        protected SettingsTabService $settingsTab,
        protected UserBotNotifyService $notify,
        protected BotRuntime $runtime,
    ) {}

    /** @return array<string, array{0: class-string, 1: string}> */
    public function handlers(): array
    {
        return [
            'settings_tab' => [self::class, 'settingsTab'],
            'logs_clear' => [self::class, 'logsClear'],
            'membership' => [self::class, 'membership'],
            'link_wp_user' => [self::class, 'linkWpUser'],
            'user_admin_message' => [self::class, 'userAdminMessage'],
        ];
    }

    /** @param  array<string, mixed>  $payload */
    public function settingsTab(array $payload, ?Authenticatable $actor): array
    {
        $tab = (string) ($payload['tab'] ?? $payload['settings_tab'] ?? '');
        if ($tab === '') {
            return svp_err('missing_tab');
        }

        $tabKey = preg_replace('/[^a-z0-9_]/', '', strtolower($tab)) ?? '';
        $modules = svp_modules();
        if (in_array($tabKey, ['bots', 'force_join'], true)
            && ! $modules->isEnabled('telegram')
            && ! $modules->isEnabled('bale')) {
            return svp_err('module_disabled');
        }
        if ($tabKey === 'relay' && ! $modules->isEnabled('relay')) {
            return svp_err('module_disabled');
        }
        if ($tabKey === 'finance' && ! $modules->isEnabled('crypto')) {
            return svp_err('module_disabled');
        }

        $values = is_array($payload['values'] ?? null) ? $payload['values'] : $payload;
        if (! $this->settingsTab->save($tab, $values)) {
            return svp_err('invalid_tab');
        }

        if (svp_modules()->isEnabled('relay')) {
            app(TelegramRelayService::class)->maybeSyncAfterSettings($tab);
        }

        return svp_ok(['message' => 'saved']);
    }

    /** @param  array<string, mixed>  $payload */
    public function logsClear(array $payload, ?Authenticatable $actor): array
    {
        if (Schema::hasTable('svp_logs')) {
            DB::table('svp_logs')->truncate();
        }

        return svp_ok();
    }

    /** @param  array<string, mixed>  $payload */
    public function membership(array $payload, ?Authenticatable $actor): array
    {
        $userId = (int) ($payload['user_id'] ?? $payload['membership_user_id'] ?? 0);
        $user = SvpUser::query()->find($userId);
        if (! $user) {
            return svp_err('user_not_found');
        }
        $channelId = (string) $this->settings->get('force_join_channel_id', '');
        if ($channelId === '' || ! $this->settings->get('force_join_enabled', false)) {
            return svp_err('not_configured');
        }
        $prompt = (string) ($this->settings->get('force_join_prompt', 'Please join our channel to continue.'));
        $ctx = new BotContext('telegram');
        if ((int) ($user->tg_user_id ?? 0) > 0) {
            $this->runtime->sendMessage($ctx, (int) $user->tg_user_id, $prompt);
        }

        return svp_ok(['user_id' => $userId, 'channel_id' => $channelId]);
    }

    /** @param  array<string, mixed>  $payload */
    public function linkWpUser(array $payload, ?Authenticatable $actor): array
    {
        return svp_err('deprecated', ['message' => 'link_wp_user is deprecated; use user_merge instead']);
    }

    /** @param  array<string, mixed>  $payload */
    public function userAdminMessage(array $payload, ?Authenticatable $actor): array
    {
        $userId = (int) ($payload['user_id'] ?? $payload['target_user_id'] ?? 0);
        $text = trim((string) ($payload['message'] ?? $payload['text'] ?? ''));
        if ($userId < 1 || $text === '') {
            return svp_err('invalid');
        }
        $user = SvpUser::query()->find($userId);
        if (! $user) {
            return svp_err('user_not_found');
        }
        $channel = (string) ($payload['channel'] ?? 'both');
        $this->notify->sendToUser($user, $text, $channel);

        return svp_ok(['user_id' => $userId]);
    }
}
