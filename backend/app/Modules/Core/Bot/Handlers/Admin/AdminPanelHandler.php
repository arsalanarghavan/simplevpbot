<?php

namespace App\Modules\Core\Bot\Handlers\Admin;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;

class AdminPanelHandler extends AbstractAdminHandler
{
    use AdminHandlerTrait;

    protected function navLabel(SvpUser $user): string
    {
        return $this->texts->getForUser('btn.admin.panel', $user, '⬅️ Panel');
    }

    public function sendPanelEntry(BotContext $ctx, int $chatId, SvpUser $user): void
    {
        $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.panel_welcome', $user));
    }
}
