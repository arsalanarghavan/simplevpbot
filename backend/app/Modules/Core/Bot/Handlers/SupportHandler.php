<?php

namespace App\Modules\Core\Bot\Handlers;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\TextService;

class SupportHandler
{
    public function __construct(
        protected BotRuntime $runtime,
        protected TextService $texts,
    ) {}

    public function showSupport(BotContext $ctx, SvpUser $user, int $chatId): void
    {
        $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.support.intro', $user, 'Support'), [
            'reply_markup' => [
                'inline_keyboard' => [
                    [['text' => 'FAQ', 'callback_data' => 'sup:f']],
                    [['text' => 'Contact', 'callback_data' => 'sup:c']],
                ],
            ],
        ]);
    }

    public function showFaq(BotContext $ctx, int $chatId): void
    {
        $this->runtime->sendMessage($ctx, $chatId, $this->texts->get('faq.connection', 'FAQ'));
    }

    public function showContact(BotContext $ctx, int $chatId): void
    {
        $this->runtime->sendMessage($ctx, $chatId, "📞 Support contact");
    }
}
