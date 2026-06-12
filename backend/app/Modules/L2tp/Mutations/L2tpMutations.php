<?php

namespace App\Modules\L2tp\Mutations;

use App\Modules\L2tp\Services\L2tpServerService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

class L2tpMutations
{
    public function __construct(protected L2tpServerService $servers) {}

    /** @return array<string, array{0: class-string, 1: string}> */
    public function handlers(): array
    {
        return [
            'l2tp_add' => [self::class, 'l2tpAdd'],
            'l2tp_update' => [self::class, 'l2tpUpdate'],
            'l2tp_delete' => [self::class, 'l2tpDelete'],
        ];
    }

    /** @param  array<string, mixed>  $payload */
    public function l2tpAdd(array $payload, ?Authenticatable $actor): array
    {
        return $this->servers->save(collect($payload)->except(['op', 'edit_id', 'id'])->all());
    }

    /** @param  array<string, mixed>  $payload */
    public function l2tpUpdate(array $payload, ?Authenticatable $actor): array
    {
        $id = (int) ($payload['id'] ?? $payload['edit_id'] ?? 0);
        if ($id < 1) {
            return svp_err('invalid_id');
        }

        return $this->servers->save(collect($payload)->except(['op', 'edit_id'])->all(), $id);
    }

    /** @param  array<string, mixed>  $payload */
    public function l2tpDelete(array $payload, ?Authenticatable $actor): array
    {
        DB::table('svp_l2tp_servers')->where('id', (int) ($payload['id'] ?? 0))->delete();

        return svp_ok();
    }
}
