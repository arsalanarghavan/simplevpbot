<?php

namespace App\Modules\Core\Bot\Handlers;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\TextService;

class AccountHandler
{
    public function __construct(
        protected BotRuntime $runtime,
        protected TextService $texts,
    ) {}

    public function showAccount(BotContext $ctx, SvpUser $user, int $chatId): void
    {
        $msg = $this->texts->format(
            $this->texts->getForUser('msg.account.summary', $user, "ID: {id}\nUser: @{username}\nStatus: {status}"),
            [
                'id' => $user->id,
                'username' => $user->username ?: '—',
                'status' => $user->status,
            ]
        );
        $this->runtime->sendMessage($ctx, $chatId, $msg);
    }
}
