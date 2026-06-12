<?php

namespace App\Modules\Core\Bot\Handlers;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\BotStateService;
use App\Modules\Core\Bot\Services\TextService;
use Illuminate\Support\Facades\Cache;

class SyncHandler
{
    public function __construct(
        protected BotRuntime $runtime,
        protected TextService $texts,
        protected BotStateService $state,
    ) {}

    public function generateCode(BotContext $ctx, SvpUser $user, int $chatId): void
    {
        $code = strtoupper(bin2hex(random_bytes(3)));
        Cache::put('svp_sync_'.$code, $user->id, 600);
        $this->runtime->sendMessage($ctx, $chatId, $this->texts->format(
            $this->texts->getForUser('msg.sync.code', $user, 'Code: {code}'),
            ['code' => $code]
        ));
    }

    public function promptCode(BotContext $ctx, SvpUser $user): void
    {
        $this->state->set($user, 'awaiting_sync_code', []);
    }

    public function handleCode(BotContext $ctx, SvpUser $user, int $chatId, string $code): void
    {
        $targetId = Cache::get('svp_sync_'.strtoupper($code));
        if (! $targetId) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.sync.invalid', $user));

            return;
        }
        $this->state->clear($user);
        $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.sync.ok', $user));
    }
}
