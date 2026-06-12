<?php

namespace App\Modules\Core\Bot\Handlers\Admin;

use App\Models\SvpUser;
use Illuminate\Support\Facades\DB;

class AdminStatsHandler extends AbstractAdminHandler
{
    use AdminHandlerTrait;

    protected function navLabel(SvpUser $user): string
    {
        return $this->texts->getForUser('btn.admin.stats', $user, '📈 Stats');
    }

    protected function sectionIntro(SvpUser $user): string
    {
        $approved = DB::table('svp_users')->where('status', 'approved')->count();

        return "Stats\nApproved users: {$approved}";
    }
}
