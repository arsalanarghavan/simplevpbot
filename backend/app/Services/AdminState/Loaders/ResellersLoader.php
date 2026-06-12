<?php

namespace App\Services\AdminState\Loaders;

use App\Models\SvpUser;
use App\Services\AdminState\AdminRowFormatter;
use App\Services\AdminState\AdminStateContext;
use App\Services\AdminState\AdminStateResult;
use App\Services\AdminState\UserListQuery;
use Illuminate\Support\Facades\DB;

class ResellersLoader extends AbstractLoader
{
    protected function shouldLoad(AdminStateContext $ctx): bool
    {
        return $ctx->needsResellersList();
    }

    protected function load(AdminStateContext $ctx, AdminStateResult $result): void
    {
        if (! $this->tableExists('svp_users')) {
            return;
        }

        $p = $ctx->page('resellers');
        $preview = $ctx->activeTab === 'dashboard' && ! $ctx->isReseller;
        $limit = $preview ? 8 : $p['per_page'];
        $offset = $preview ? 0 : $p['offset'];

        $q = SvpUser::query()->where('role', 'reseller');
        UserListQuery::applySearch($q, (string) $ctx->request->query('resellers_q', ''));

        $status = (string) $ctx->request->query('resellers_status', '');
        if (in_array($status, ['pending', 'approved', 'rejected', 'blocked'], true)) {
            $q->where('status', $status);
        }

        if ($ctx->isReseller && $ctx->actorSvpUserId > 0 && $this->tableExists('svp_reseller_closure')) {
            $childIds = DB::table('svp_reseller_closure')
                ->where('ancestor_id', $ctx->actorSvpUserId)
                ->where('depth', 1)
                ->pluck('descendant_id')
                ->all();
            $q->whereIn('id', $childIds ?: [0]);
        }

        $total = (clone $q)->count();
        $result->setTotal('resellers', $total);

        $rows = (clone $q)->orderByDesc('id')->offset($offset)->limit($limit)->get()->all();
        $result->merge([
            'resellers' => AdminRowFormatter::usersListRows($rows, true),
            'resellerPermissionsMap' => $this->permissionsMap($rows),
        ]);
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function permissionsMap(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $arr = AdminRowFormatter::rowArray($row);
            $id = (int) ($arr['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $map[$id] = is_array($arr['permissions_json'] ?? null)
                ? $arr['permissions_json']
                : (json_decode((string) ($arr['permissions_json'] ?? ''), true) ?: []);
        }

        return $map;
    }
}
