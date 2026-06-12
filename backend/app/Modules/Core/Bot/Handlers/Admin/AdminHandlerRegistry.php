<?php

namespace App\Modules\Core\Bot\Handlers\Admin;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\TextService;

class AdminHandlerRegistry
{
    public function __construct(
        protected BotRuntime $runtime,
        protected TextService $texts,
        protected AdminPnlHandler $pnl,
        protected AdminPanelHandler $panel,
        protected AdminUsersHandler $users,
        protected AdminReceiptsHandler $receipts,
        protected AdminCatalogHandler $catalog,
        protected AdminFinanceHandler $finance,
        protected AdminSettingsHandler $settings,
        protected AdminTextsHandler $textsHandler,
        protected AdminStatsHandler $stats,
        protected AdminMarketingHandler $marketing,
        protected AdminEconomicsHandler $economics,
        protected AdminInboundHandler $inbound,
        protected AdminResellersHandler $resellers,
        protected AdminBulkHandler $bulk,
        protected AdminBackupHandler $backup,
        protected AdminLogsHandler $logs,
        protected AdminHandler $admin,
    ) {}

    /** @param  array<int, string>  $parts */
    public function handlePnl(BotContext $ctx, array $parts, ?SvpUser $user, int $chatId, int $msgId, int $fromId): void
    {
        if (isset($parts[1]) && $parts[1] === 'cat') {
            $this->catalog->handleCallback($ctx, $parts, $user, $chatId, $msgId);

            return;
        }
        $this->pnl->handle($ctx, $parts, $user, $chatId, $msgId, $fromId);
    }

    public function handleRegistration(BotContext $ctx, string $action, int $uid, array $from, int $chatId, string $cbId): void
    {
        $this->users->handleRegistration($ctx, $action, $uid, $from, $chatId, $cbId);
    }

    /** @param  array<string, mixed>  $from */
    public function handleAdminReplyText(BotContext $ctx, SvpUser $user, int $chatId, string $text, array $from): void
    {
        $this->admin->routeReplyText($ctx, $user, $chatId, $text, $from);
    }
}
