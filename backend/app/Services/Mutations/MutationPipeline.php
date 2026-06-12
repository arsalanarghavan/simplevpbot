<?php

namespace App\Services\Mutations;

use App\Models\DashboardUser;
use App\Services\AuditLogService;
use App\Services\MutationRegistry;
use App\Support\Metrics\SvpMetrics;
use App\Services\UserActivityLogService;
use Illuminate\Contracts\Auth\Authenticatable;

class MutationPipeline
{
    public function __construct(
        protected MutationRegistry $registry,
        protected MutatePolicyService $policy,
        protected MutateScopeGuard $scopeGuard,
        protected AuditLogService $audit,
        protected UserActivityLogService $activity,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{result: array<string, mixed>, http_status: int}
     */
    public function dispatch(string $op, array $payload, ?Authenticatable $actor): array
    {
        $op = preg_replace('/[^a-z0-9_]/', '', strtolower($op)) ?? '';

        if ($op === '') {
            return ['result' => ['ok' => false, 'message' => 'missing_op'], 'http_status' => 400];
        }

        if (! $actor instanceof DashboardUser) {
            return ['result' => ['ok' => false, 'message' => 'forbidden'], 'http_status' => 403];
        }

        if (! $this->registry->has($op)) {
            return ['result' => ['ok' => false, 'message' => 'unknown_op', 'code' => $op], 'http_status' => 422];
        }

        $policyErr = $this->policy->assertResellerMayRun($op, $actor);
        if ($policyErr !== null) {
            return ['result' => $policyErr, 'http_status' => 403];
        }

        $lifecycleErr = $this->policy->assertLifecycleFeature($op, $actor);
        if ($lifecycleErr !== null) {
            return ['result' => $lifecycleErr, 'http_status' => 403];
        }

        if (str_starts_with($op, 'telegram_relay_') && ! svp_modules()->isEnabled('relay')) {
            return ['result' => svp_err('module_disabled'), 'http_status' => 403];
        }

        $ctx = new MutateContext(
            op: $op,
            payload: $payload,
            actor: $actor,
            isReseller: $actor->role === 'reseller',
            actorSvpUserId: (int) ($actor->svp_user_id ?? 0),
            resellerContextId: (int) ($payload['reseller_context_svp_user_id'] ?? 0),
        );

        $scopeErr = $this->scopeGuard->assertPayloadScope($op, $payload, $ctx);
        if ($scopeErr !== null) {
            return ['result' => $scopeErr, 'http_status' => 403];
        }

        $enriched = $this->scopeGuard->enrichPayload($payload, $ctx);
        $handler = $this->registry->all()[$op];

        if (is_array($handler)) {
            [$class, $method] = $handler;
            $result = app($class)->{$method}($enriched, $actor);
        } else {
            $result = $handler($enriched, $actor);
        }

        if (! is_array($result)) {
            $result = ['ok' => false, 'message' => 'invalid_handler_response'];
        }

        $this->audit->recordIfSensitive($op, $enriched, $result, $actor);

        $subjectUid = (int) ($enriched['user_id'] ?? $enriched['svp_user_id'] ?? 0);
        if (! empty($result['ok']) && $subjectUid > 0) {
            $this->activity->logUserEvent($subjectUid, $op, $enriched, $actor);
        }

        if (! empty($result['ok'])) {
            SvpMetrics::inc('mutate_op_total');
        }

        return [
            'result' => $result,
            'http_status' => $this->httpStatusFor($result),
        ];
    }

    /** @param  array<string, mixed>  $result */
    protected function httpStatusFor(array $result): int
    {
        if (! empty($result['ok'])) {
            return 200;
        }

        $msg = (string) ($result['message'] ?? '');
        if (in_array($msg, ['forbidden_op', 'forbidden_perm', 'forbidden_scope', 'forbidden'], true)) {
            return 403;
        }
        if (in_array($msg, ['invalid_reseller_context', 'missing_op', 'bad_request'], true)) {
            return 400;
        }

        return 422;
    }
}
