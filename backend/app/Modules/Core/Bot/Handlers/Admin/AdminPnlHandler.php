<?php

namespace App\Modules\Core\Bot\Handlers\Admin;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use Illuminate\Support\Facades\DB;

class AdminPnlHandler extends AbstractAdminHandler
{
    use AdminHandlerTrait;

    protected function navLabel(SvpUser $user): string
    {
        return $this->texts->getForUser('btn.admin.pnl', $user, '📊 PnL');
    }

    /** @param  array<int, string>  $parts */
    public function handle(BotContext $ctx, array $parts, ?SvpUser $user, int $chatId, int $msgId, int $fromId): void
    {
        $sub = $parts[1] ?? '';
        if ($sub === 'dash') {
            $users = DB::table('svp_users')->count();
            $services = DB::table('svp_services')->whereNull('deleted_at')->count();
            $this->send($ctx, $chatId, "Users: {$users}\nServices: {$services}");

            return;
        }
        $this->send($ctx, $chatId, $this->sectionIntro($user ?? new SvpUser));
    }
}
