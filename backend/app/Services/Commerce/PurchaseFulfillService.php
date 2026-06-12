<?php

namespace App\Services\Commerce;

use App\Models\SvpUser;
use App\Modules\Core\Services\UserBotNotifyService;
use Illuminate\Support\Facades\DB;

class PurchaseFulfillService
{
    public function __construct(
        protected ServiceProvisioner $provisioner,
        protected UserBotNotifyService $notify,
    ) {}

    /** @return array{ok:bool, reason?:string, service_id?:int} */
    public function fulfillByTransaction(int $txId, string $source = 'nowpayments'): array
    {
        $tx = DB::table('svp_transactions')->where('id', $txId)->first();
        if (! $tx) {
            return ['ok' => false, 'reason' => 'bad_tx'];
        }
        if ((string) $tx->status === 'approved') {
            return ['ok' => true, 'reason' => 'already_approved'];
        }
        if ((string) $tx->status !== 'pending' || (string) $tx->type !== 'purchase') {
            return ['ok' => false, 'reason' => 'bad_tx'];
        }

        $meta = json_decode((string) ($tx->meta_json ?? '{}'), true);
        if (! is_array($meta) || empty($meta['plan_id'])) {
            return ['ok' => false, 'reason' => 'no_plan_id'];
        }

        $userId = (int) $tx->user_id;
        $planId = (int) $meta['plan_id'];
        $volumeGb = isset($meta['volume_gb']) ? (int) $meta['volume_gb'] : null;
        $platform = isset($meta['platform']) ? (string) $meta['platform'] : null;

        $result = $this->provisioner->createFromPlan($userId, $planId, $volumeGb, $platform);
        if (empty($result['ok'])) {
            return ['ok' => false, 'reason' => (string) ($result['reason'] ?? 'provision_failed')];
        }

        $serviceId = (int) ($result['service_id'] ?? 0);
        DB::table('svp_transactions')->where('id', $txId)->update([
            'status' => 'approved',
            'service_id' => $serviceId > 0 ? $serviceId : null,
        ]);

        $user = SvpUser::query()->find($userId);
        if ($user) {
            $msg = 'پرداخت شما تایید شد. ممنون!';
            if ($serviceId > 0) {
                $msg .= "\n".'Service #'.$serviceId;
            }
            $this->notify->sendToUser($user, $msg);
        }

        return ['ok' => true, 'service_id' => $serviceId];
    }
}
