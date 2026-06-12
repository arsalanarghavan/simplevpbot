<?php

namespace App\Modules\Core\Bot\Handlers\Admin;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use Illuminate\Support\Facades\DB;

class AdminUsersHandler extends AbstractAdminHandler
{
    use AdminHandlerTrait;

    protected function navLabel(SvpUser $user): string
    {
        return $this->texts->getForUser('btn.admin.users', $user, '👥 Users');
    }

    public function handleRegistration(BotContext $ctx, string $action, int $uid, array $from, int $chatId, string $cbId): void
    {
        $user = SvpUser::query()->find($uid);
        if (! $user) {
            return;
        }
        if ($action === 'a') {
            $user->status = 'approved';
            $user->approved_at = now();
            $user->approved_by = (string) ($from['username'] ?? 'admin');
            $user->save();
            $this->send($ctx, $chatId, "User #{$uid} approved");
        } else {
            $user->status = 'rejected';
            $user->save();
            $this->send($ctx, $chatId, "User #{$uid} rejected");
        }
    }

    protected function sectionIntro(SvpUser $user): string
    {
        $pending = DB::table('svp_users')->where('status', 'pending')->count();

        return "Users\nPending: {$pending}";
    }
}
