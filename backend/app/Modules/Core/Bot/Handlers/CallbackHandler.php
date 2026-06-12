<?php

namespace App\Modules\Core\Bot\Handlers;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\Admin\AdminHandlerRegistry;
use App\Modules\Core\Bot\Services\AdminGuard;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\BotStateService;
use App\Services\Commerce\ReceiptProcessorService;

class CallbackHandler
{
    public function __construct(
        protected BotRuntime $runtime,
        protected AdminGuard $adminGuard,
        protected BotStateService $state,
        protected BuyHandler $buy,
        protected ServiceHandler $service,
        protected WalletHandler $wallet,
        protected SupportHandler $support,
        protected SyncHandler $sync,
        protected AdminHandlerRegistry $adminRegistry,
        protected ReceiptProcessorService $receiptProcessor,
    ) {}

    /** @param  array<string, mixed>  $payload */
    public function handle(BotContext $ctx, array $payload): void
    {
        $cb = (array) ($payload['cb'] ?? []);
        $user = $payload['user'] ?? null;
        $from = (array) ($payload['from'] ?? ($cb['from'] ?? []));
        $fromId = (int) ($from['id'] ?? 0);
        $data = (string) ($cb['data'] ?? '');
        $cbId = (string) ($cb['id'] ?? '');
        $msg = (array) ($cb['message'] ?? []);
        $chatId = (int) ($msg['chat']['id'] ?? ($payload['chat_id'] ?? 0));
        $msgId = (int) ($msg['message_id'] ?? 0);

        if ($data === 'noop' || str_starts_with($data, 'alnoop:')) {
            $this->runtime->answerCallbackQuery($ctx, ['callback_query_id' => $cbId]);

            return;
        }

        $defer = str_starts_with($data, 'rc:') || str_starts_with($data, 'buy:cf:');
        if (! $defer) {
            $this->runtime->answerCallbackQuery($ctx, ['callback_query_id' => $cbId]);
        }

        if (str_starts_with($data, 'chjoin:')) {
            return;
        }

        $parts = explode(':', $data);
        $head = $parts[0] ?? '';

        if ($head === 'wal' && $user instanceof SvpUser) {
            if (($parts[1] ?? '') === 'h') {
                $this->wallet->showHistory($ctx, $user, $chatId);
            } elseif (($parts[1] ?? '') === 'tu') {
                $this->wallet->beginTopup($ctx, $user, $chatId);
            }

            return;
        }

        if ($head === 'sup') {
            if (($parts[1] ?? '') === 'c') {
                $this->support->showContact($ctx, $chatId);
            } else {
                $this->support->showFaq($ctx, $chatId);
            }

            return;
        }

        if ($head === 'sync' && $user instanceof SvpUser) {
            if (($parts[1] ?? '') === 'g') {
                $this->sync->generateCode($ctx, $user, $chatId);
            } elseif (($parts[1] ?? '') === 'i') {
                $this->sync->promptCode($ctx, $user);
            }

            return;
        }

        if ($head === 'reg' && isset($parts[1], $parts[2])) {
            $this->adminRegistry->handleRegistration($ctx, $parts[1], (int) $parts[2], $from, $chatId, $cbId);

            return;
        }

        if ($head === 'rc' && isset($parts[1], $parts[2])) {
            $this->handleReceiptCallback($ctx, $parts, $from, $chatId, $msgId, $cbId);

            return;
        }

        if ($head === 'buy' && $user instanceof SvpUser) {
            $this->buy->handleCallback($ctx, $user, [
                'parts' => $parts,
                'chat_id' => $chatId,
                'msg_id' => $msgId,
                'cb_id' => $cbId,
            ]);

            return;
        }

        if ($head === 'svc' && isset($parts[1], $parts[2]) && $user instanceof SvpUser) {
            $this->service->handleCallback($ctx, $user, [
                'action' => (string) $parts[1],
                'svc_id' => (int) $parts[2],
                'chat_id' => $chatId,
                'msg_id' => $msgId,
                'from_id' => $fromId,
                'cb_id' => $cbId,
            ]);

            return;
        }

        if ($head === 'pnl' && $this->adminGuard->isPlatformAdmin($ctx->platform, $fromId)) {
            $this->adminRegistry->handlePnl($ctx, $parts, $user, $chatId, $msgId, $fromId);

            return;
        }
    }

    /** @param  array<int, string>  $parts */
    protected function handleReceiptCallback(BotContext $ctx, array $parts, array $from, int $chatId, int $msgId, string $cbId): void
    {
        $action = (string) ($parts[1] ?? '');
        $rid = (int) ($parts[2] ?? 0);
        $label = (string) ($from['username'] ?? $from['first_name'] ?? 'admin');

        if ($action === 'a') {
            $result = $this->receiptProcessor->approve($rid, $label);
            $this->runtime->answerCallbackQuery($ctx, [
                'callback_query_id' => $cbId,
                'text' => ! empty($result['ok']) ? 'Approved' : ($result['reason'] ?? 'failed'),
            ]);
        } elseif ($action === 'r') {
            $this->receiptProcessor->reject($rid, $label, 'rejected');
        }
    }

    /** @param  array<string, mixed>  $from */
    public function handleAdminText(BotContext $ctx, SvpUser $user, int $chatId, string $text, array $from): void
    {
        $this->adminRegistry->handleAdminReplyText($ctx, $user, $chatId, $text, $from);
    }
}
