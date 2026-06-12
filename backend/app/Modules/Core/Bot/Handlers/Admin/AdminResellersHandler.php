<?php

namespace App\Modules\Core\Bot\Handlers\Admin;

use App\Models\SvpUser;
use Illuminate\Support\Facades\DB;

class AdminResellersHandler extends AbstractAdminHandler
{
    use AdminHandlerTrait;

    protected function navLabel(SvpUser $user): string
    {
        return $this->texts->getForUser('btn.admin.resellers', $user, '🏪 Resellers');
    }

    protected function sectionIntro(SvpUser $user): string
    {
        $count = DB::table('svp_users')->where('role', 'reseller')->count();

        return "Resellers: {$count}";
    }
}
