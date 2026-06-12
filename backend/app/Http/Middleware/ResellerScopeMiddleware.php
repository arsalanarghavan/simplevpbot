<?php

namespace App\Http\Middleware;

use App\Models\DashboardUser;
use App\Modules\Reseller\Services\ResellerScopeService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResellerScopeMiddleware
{
    public function __construct(protected ResellerScopeService $scope) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof DashboardUser && $user->role === 'reseller' && (int) ($user->svp_user_id ?? 0) > 0) {
            $request->attributes->set('reseller_scope_ids', $this->scope->downlineUserIds((int) $user->svp_user_id));
            $request->attributes->set('reseller_actor_id', (int) $user->svp_user_id);
        }

        return $next($request);
    }
}
