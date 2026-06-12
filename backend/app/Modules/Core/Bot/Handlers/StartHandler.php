<?php

namespace App\Modules\Core\Bot\Handlers;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\KeyboardBuilder;
use App\Modules\Core\Bot\Services\TextService;
use App\Modules\Core\Bot\Services\UserResolver;

class StartHandler
{
    public function __construct(
        protected UserResolver $users,
        protected BotRuntime $runtime,
        protected TextService $texts,
        protected KeyboardBuilder $keyboards,
    ) {}

    /** @param  array<string, mixed>  $payload */
    public function handle(BotContext $ctx, array $payload): void
    {
        $chatId = (int) ($payload['chat_id'] ?? 0);
        $from = (array) ($payload['from'] ?? []);
        $text = (string) ($payload['text'] ?? '');
        $user = $payload['user'] ?? null;

        if (! $user instanceof SvpUser) {
            $user = $this->users->findOrCreateFromStart($ctx, $from, $text);
        }

        $welcome = $this->texts->getForUser('msg.welcome', $user, 'Welcome!');
        $this->runtime->sendMessage($ctx, $chatId, $welcome, [
            'reply_markup' => $this->keyboards->userMainReply($user),
        ]);
    }

    public function handleLang(BotContext $ctx, SvpUser $user, int $chatId, string $text): void
    {
        $parts = preg_split('/\s+/u', trim($text), 2);
        $sub = strtolower(trim((string) ($parts[1] ?? '')));
        if (! in_array($sub, ['fa', 'en', 'persian', 'english'], true)) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.lang_usage', $user));

            return;
        }
        $user->bot_locale = in_array($sub, ['en', 'english'], true) ? 'en' : 'fa';
        $user->save();
        $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.lang_changed', $user));
    }

    public function handlePanel(BotContext $ctx, ?SvpUser $user, int $fromId, int $chatId): void
    {
        if (! $user) {
            return;
        }

        $adminIds = app(\App\Modules\Core\Bot\Services\AdminGuard::class);
        if (! $adminIds->isPlatformAdmin($ctx->platform, $fromId)) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.admin.panel_denied', $user));

            return;
        }

        $user->admin_mode = true;
        $user->save();
        app(\App\Modules\Core\Bot\Services\BotStateService::class)->clear($user);
        $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.admin.panel_welcome', $user));
    }
}
