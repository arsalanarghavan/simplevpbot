<?php

namespace App\Services\Commerce;

use App\Models\SvpPlan;
use App\Models\SvpService;
use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\KeyboardBuilder;
use App\Modules\Core\Bot\Services\TextService;
use Illuminate\Support\Facades\DB;

class ReceiptProcessorService
{
    public function __construct(
        protected ServiceProvisioner $serviceProvisioner,
        protected BotRuntime $runtime,
        protected TextService $texts,
        protected KeyboardBuilder $keyboards,
    ) {}

    /** @return array<string, mixed> */
    public function approve(int $receiptId, string $adminLabel): array
    {
        $rec = DB::table('svp_receipts')->where('id', $receiptId)->first();
        if (! $rec) {
            return ['ok' => false, 'reason' => 'not_found'];
        }
        if ((string) $rec->status === 'approved') {
            return ['ok' => true, 'reason' => 'already_approved'];
        }
        if ((string) $rec->status !== 'pending') {
            return ['ok' => false, 'reason' => 'not_pending'];
        }

        if (! $this->claimPending($receiptId)) {
            return ['ok' => false, 'reason' => 'already_processing'];
        }

        return $this->approveContinue($receiptId, $adminLabel);
    }

    /** @return array<string, mixed> */
    protected function approveContinue(int $receiptId, string $adminLabel): array
    {
        $rec = DB::table('svp_receipts')->where('id', $receiptId)->first();
        if (! $rec) {
            return ['ok' => false, 'reason' => 'not_found'];
        }

        $tx = DB::table('svp_transactions')->where('id', (int) $rec->transaction_id)->first();
        $meta = $tx ? json_decode((string) ($tx->meta_json ?? '{}'), true) : [];
        $meta = is_array($meta) ? $meta : [];

        $provisionError = null;
        $serviceId = null;

        if ($tx && (string) $tx->type === 'purchase' && ! empty($meta['plan_id'])) {
            $result = $this->provisionPurchase((int) $rec->user_id, (int) $meta['plan_id']);
            if (empty($result['ok'])) {
                $provisionError = (string) ($result['message'] ?? $result['reason'] ?? 'purchase_failed');
            } else {
                $serviceId = (int) ($result['service_id'] ?? 0);
            }
        } elseif ($tx && (string) $tx->type === 'wallet_topup') {
            $this->creditWallet((int) $rec->user_id, (float) $rec->amount, $receiptId);
        }

        DB::table('svp_receipts')->where('id', $receiptId)->update([
            'status' => $provisionError ? 'approved' : 'approved',
            'decided_at' => now(),
            'decided_by' => $adminLabel,
        ]);

        DB::table('svp_transactions')->where('id', (int) $rec->transaction_id)->update([
            'status' => $provisionError ? 'completed_with_error' : 'completed',
        ]);

        $this->notifyUser((int) $rec->user_id, $receiptId, $provisionError, $serviceId);

        if ($provisionError) {
            return [
                'ok' => true,
                'purchase_failed' => true,
                'provision_error' => $provisionError,
                'reason' => $provisionError,
            ];
        }

        return ['ok' => true, 'receipt_id' => $receiptId, 'service_id' => $serviceId];
    }

    /** @return array<string, mixed> */
    public function reject(int $receiptId, string $adminLabel, string $reason = ''): array
    {
        DB::table('svp_receipts')->where('id', $receiptId)->update([
            'status' => 'rejected',
            'decided_at' => now(),
            'decided_by' => $adminLabel,
            'reject_reason' => $reason,
        ]);

        $rec = DB::table('svp_receipts')->where('id', $receiptId)->first();
        if ($rec) {
            $this->notifyUserRejected((int) $rec->user_id, $reason);
        }

        return ['ok' => true, 'receipt_id' => $receiptId];
    }

    protected function claimPending(int $receiptId): bool
    {
        return DB::table('svp_receipts')
            ->where('id', $receiptId)
            ->where('status', 'pending')
            ->update(['status' => 'processing']) === 1;
    }

    /** @return array<string, mixed> */
    protected function provisionPurchase(int $userId, int $planId): array
    {
        $result = $this->serviceProvisioner->createFromPlan($userId, $planId);
        if (empty($result['ok'])) {
            return svp_err((string) ($result['reason'] ?? 'provision_failed'), $result);
        }
        if (! empty($result['service_id'])) {
            DB::table('svp_services')->where('id', (int) $result['service_id'])->update(['provision_type' => 'receipt']);
        }

        return svp_ok(['service_id' => (int) ($result['service_id'] ?? 0)]);
    }

    protected function creditWallet(int $userId, float $amount, int $receiptId): void
    {
        if ($userId < 1 || $amount <= 0) {
            return;
        }
        $user = SvpUser::query()->lockForUpdate()->find($userId);
        if (! $user) {
            return;
        }
        $user->balance = bcadd((string) $user->balance, (string) $amount, 2);
        $user->save();
    }

    protected function notifyUser(int $userId, int $receiptId, ?string $provisionError, ?int $serviceId): void
    {
        $user = SvpUser::query()->find($userId);
        if (! $user) {
            return;
        }

        $platform = $user->tg_user_id ? 'telegram' : 'bale';
        $chatId = (int) ($user->tg_user_id ?: $user->bale_user_id);
        if ($chatId < 1) {
            return;
        }

        $ctx = new BotContext($platform);
        if ($provisionError) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.receipt.approved_provision_error', $user, $provisionError));
        } else {
            $msg = $this->texts->getForUser('msg.receipt.approved', $user, 'Receipt approved.');
            if ($serviceId) {
                $msg .= "\n".'Service #'.$serviceId;
            }
            $this->runtime->sendMessage($ctx, $chatId, $msg);
        }
    }

    protected function notifyUserRejected(int $userId, string $reason): void
    {
        $user = SvpUser::query()->find($userId);
        if (! $user) {
            return;
        }
        $platform = $user->tg_user_id ? 'telegram' : 'bale';
        $chatId = (int) ($user->tg_user_id ?: $user->bale_user_id);
        if ($chatId < 1) {
            return;
        }
        $ctx = new BotContext($platform);
        $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.receipt.rejected', $user, $reason ?: 'Rejected'));
    }
}
