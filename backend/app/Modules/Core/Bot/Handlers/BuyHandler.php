<?php

namespace App\Modules\Core\Bot\Handlers;

use App\Models\SvpPlan;
use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\BotStateService;
use App\Modules\Core\Bot\Services\KeyboardBuilder;
use App\Modules\Core\Bot\Services\TextService;
use Illuminate\Support\Facades\DB;

class BuyHandler
{
    public function __construct(
        protected BotRuntime $runtime,
        protected TextService $texts,
        protected BotStateService $state,
        protected KeyboardBuilder $keyboards,
    ) {}

    public function showPlanPicker(BotContext $ctx, SvpUser $user, int $chatId): void
    {
        $plans = SvpPlan::query()->where('active', true)->orderBy('sort_order')->limit(20)->get();
        $rows = [];
        foreach ($plans as $plan) {
            $rows[] = [[
                'text' => (string) $plan->name.' — '.number_format((float) $plan->price),
                'callback_data' => 'buy:p:'.$plan->id,
            ]];
        }
        if ($rows === []) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.buy.no_plans', $user));

            return;
        }
        $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.buy.pick_plan', $user, 'Choose a plan:'), [
            'reply_markup' => $this->keyboards->inline($rows),
        ]);
    }

    /** @param  array<string, mixed>  $payload */
    public function handleCallback(BotContext $ctx, SvpUser $user, array $payload): void
    {
        $parts = (array) ($payload['parts'] ?? []);
        $chatId = (int) ($payload['chat_id'] ?? 0);
        $head = $parts[0] ?? '';

        if ($head !== 'buy' || ! isset($parts[1])) {
            return;
        }

        $action = (string) $parts[1];
        if ($action === 'p' && isset($parts[2])) {
            $this->selectPlan($ctx, $user, $chatId, (int) $parts[2]);

            return;
        }
        if ($action === 'pm' && isset($parts[2]) && $parts[2] === 'c2c') {
            $this->startCardPayment($ctx, $user, $chatId);

            return;
        }
        if ($action === 'cf' && isset($parts[2])) {
            $this->confirmCheckout($ctx, $user, $chatId);

            return;
        }
    }

    protected function selectPlan(BotContext $ctx, SvpUser $user, int $chatId, int $planId): void
    {
        $plan = SvpPlan::query()->find($planId);
        if (! $plan) {
            return;
        }

        $this->state->set($user, 'buy_checkout', ['plan_id' => $planId]);
        $summary = $this->texts->format(
            $this->texts->getForUser('msg.buy.plan_checkout_summary', $user, "{name} — {amount}"),
            ['name' => $plan->name, 'amount' => number_format((float) $plan->price)]
        );
        $this->runtime->sendMessage($ctx, $chatId, $summary, [
            'reply_markup' => $this->keyboards->inline([
                [['text' => $this->texts->getForUser('btn.buy.pay_c2c', $user, 'Card'), 'callback_data' => 'buy:pm:c2c']],
                [['text' => $this->texts->getForUser('btn.buy.confirm', $user, 'Confirm'), 'callback_data' => 'buy:cf:1']],
            ]),
        ]);
    }

    protected function startCardPayment(BotContext $ctx, SvpUser $user, int $chatId): void
    {
        $data = $this->state->data($user);
        $planId = (int) ($data['plan_id'] ?? 0);
        $plan = SvpPlan::query()->find($planId);
        if (! $plan) {
            return;
        }

        $card = DB::table('svp_cards')->where('active', true)->orderBy('priority')->first();
        $cardText = $card
            ? "Card: {$card->card_number}\n{$card->holder_name}"
            : 'No card configured';

        $txId = DB::table('svp_transactions')->insertGetId([
            'user_id' => $user->id,
            'amount' => $plan->price,
            'type' => 'purchase',
            'status' => 'pending',
            'meta_json' => json_encode(['plan_id' => $planId]),
            'created_at' => now(),
        ]);

        $receiptId = DB::table('svp_receipts')->insertGetId([
            'user_id' => $user->id,
            'transaction_id' => $txId,
            'amount' => $plan->price,
            'status' => 'pending',
            'created_at' => now(),
        ]);

        $this->state->set($user, 'awaiting_receipt_photo', [
            'receipt_id' => $receiptId,
            'transaction_id' => $txId,
            'plan_id' => $planId,
        ]);

        $this->runtime->sendMessage($ctx, $chatId, $cardText."\n\n".$this->texts->getForUser('msg.buy.upload_receipt', $user));
    }

    protected function confirmCheckout(BotContext $ctx, SvpUser $user, int $chatId): void
    {
        $this->startCardPayment($ctx, $user, $chatId);
    }

    /** @param  array<string, mixed>  $message */
    public function handleReceiptPhoto(BotContext $ctx, SvpUser $user, int $chatId, array $message): void
    {
        $data = $this->state->data($user);
        $receiptId = (int) ($data['receipt_id'] ?? 0);
        if ($receiptId < 1) {
            return;
        }

        $fileId = $message['photo'][0]['file_id'] ?? $message['document']['file_id'] ?? null;
        if ($fileId) {
            DB::table('svp_receipts')->where('id', $receiptId)->update([
                'file_id' => $fileId,
                'status' => 'pending',
            ]);
        }

        $this->state->clear($user);
        $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.buy.receipt_received', $user, 'Receipt received, pending review.'));
    }

    /** @param  array<string, mixed>  $preCheckout */
    public function handleBalePreCheckout(BotContext $ctx, array $preCheckout): void
    {
        $this->runtime->answerCallbackQuery($ctx, [
            'callback_query_id' => (string) ($preCheckout['id'] ?? ''),
            'ok' => true,
        ]);
    }

    /** @param  array<string, mixed>  $message */
    public function handleSuccessfulPayment(BotContext $ctx, array $message): void
    {
        $from = $message['from'] ?? [];
        $chatId = (int) ($message['chat']['id'] ?? 0);
        $user = app(\App\Modules\Core\Bot\Services\UserResolver::class)->resolve($ctx, $from);
        if (! $user) {
            return;
        }

        $payment = $message['successful_payment'] ?? [];
        $payload = (string) ($payment['invoice_payload'] ?? '');
        $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.buy.payment_ok', $user, 'Payment received.'));
    }
}
