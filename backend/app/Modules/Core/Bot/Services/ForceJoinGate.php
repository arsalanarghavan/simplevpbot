<?php

namespace App\Modules\Core\Bot\Services;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Services\SettingsStore;

class ForceJoinGate
{
    public function __construct(
        protected SettingsStore $settings,
        protected BotRuntime $runtime,
        protected TextService $texts,
    ) {}

    public function shouldBlock(
        BotContext $ctx,
        int $fromId,
        int $chatId,
        ?SvpUser $user,
        string $cmd = '',
        string $cbData = '',
    ): bool {
        if (! $this->settings->get('force_join_enabled', false)) {
            return false;
        }

        if ($cmd === 'start' || str_starts_with($cbData, 'chjoin:')) {
            return false;
        }

        $channelId = (string) $this->settings->get('force_join_channel_id', '');
        if ($channelId === '') {
            return false;
        }

        // Full membership check requires getChatMember API — simplified gate for phase 5
        if ($user && (string) $user->status !== 'approved') {
            return false;
        }

        return false;
    }
}
