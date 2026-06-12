<?php

namespace App\Modules\Core\Bot\Handlers\Admin;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\TextService;

class AdminHandler extends AbstractAdminHandler
{
    public function __construct(
        BotRuntime $runtime,
        TextService $texts,
        protected AdminPanelHandler $panel,
        protected AdminPnlHandler $pnl,
        protected AdminUsersHandler $users,
        protected AdminReceiptsHandler $receipts,
        protected AdminCatalogHandler $catalog,
        protected AdminFinanceHandler $finance,
        protected AdminSettingsHandler $settings,
        protected AdminStatsHandler $stats,
        protected AdminMarketingHandler $marketing,
        protected AdminEconomicsHandler $economics,
        protected AdminInboundHandler $inbound,
        protected AdminResellersHandler $resellers,
        protected AdminBulkHandler $bulk,
        protected AdminBackupHandler $backup,
        protected AdminLogsHandler $logs,
        protected AdminTextsHandler $textsHandler,
    ) {
        parent::__construct($runtime, $texts);
    }

    /** @param  array<string, mixed>  $from */
    public function routeReplyText(BotContext $ctx, SvpUser $user, int $chatId, string $text, array $from): void
    {
        $handlers = [
            $this->panel,
            $this->pnl,
            $this->users,
            $this->receipts,
            $this->catalog,
            $this->finance,
            $this->settings,
            $this->stats,
            $this->marketing,
            $this->economics,
            $this->inbound,
            $this->resellers,
            $this->bulk,
            $this->backup,
            $this->logs,
            $this->textsHandler,
        ];

        foreach ($handlers as $handler) {
            if ($handler->matchesNavText($text, $user) && $handler->handleNav($ctx, $user, $chatId, $text)) {
                return;
            }
        }

        $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.unknown_cmd', $user, 'Unknown command'));
    }
}
