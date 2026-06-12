<?php

namespace App\Modules\Core\Bot\Handlers;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\BotStateService;
use App\Modules\Core\Bot\Services\KeyboardBuilder;
use App\Modules\Core\Bot\Services\TextService;
use Illuminate\Support\Facades\DB;

class WalletHandler
{
    public function __construct(
        protected BotRuntime $runtime,
        protected TextService $texts,
        protected BotStateService $state,
        protected KeyboardBuilder $keyboards,
    ) {}

    public function showWallet(BotContext $ctx, SvpUser $user, int $chatId): void
    {
        $msg = $this->texts->format(
            $this->texts->getForUser('msg.wallet.balance', $user, 'Balance: {balance}'),
            ['balance' => number_format((float) $user->balance)]
        );
        $this->runtime->sendMessage($ctx, $chatId, $msg, [
            'reply_markup' => $this->keyboards->inline([
                [['text' => $this->texts->getForUser('btn.wallet.topup', $user, 'Top up'), 'callback_data' => 'wal:tu']],
                [['text' => $this->texts->getForUser('btn.wallet.history', $user, 'History'), 'callback_data' => 'wal:h']],
            ]),
        ]);
    }

    public function beginTopup(BotContext $ctx, SvpUser $user, int $chatId): void
    {
        $this->state->set($user, 'wallet_topup_amount', []);
        $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.wallet.topup_prompt', $user, 'Enter amount:'));
    }

    public function showHistory(BotContext $ctx, SvpUser $user, int $chatId): void
    {
        $rows = DB::table('svp_transactions')->where('user_id', $user->id)->orderByDesc('id')->limit(10)->get();
        $lines = [];
        foreach ($rows as $row) {
            $lines[] = "{$row->type}: {$row->amount} ({$row->status})";
        }
        $this->runtime->sendMessage($ctx, $chatId, $lines === [] ? '—' : implode("\n", $lines));
    }
}
