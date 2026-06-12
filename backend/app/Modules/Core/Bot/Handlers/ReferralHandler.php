<?php

namespace App\Modules\Core\Bot\Handlers;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\TextService;
use App\Services\SettingsStore;

class ReferralHandler
{
    public function __construct(
        protected BotRuntime $runtime,
        protected TextService $texts,
        protected SettingsStore $settings,
    ) {}

    public function showReferral(BotContext $ctx, SvpUser $user, int $chatId): void
    {
        $botName = (string) $this->settings->get('telegram_bot_username', 'bot');
        $link = "https://t.me/{$botName}?start=ref_{$user->id}";
        $msg = $this->texts->format(
            $this->texts->getForUser('msg.referral.link', $user, 'Your link: {link}'),
            ['link' => $link]
        );
        $this->runtime->sendMessage($ctx, $chatId, $msg);
    }
}
