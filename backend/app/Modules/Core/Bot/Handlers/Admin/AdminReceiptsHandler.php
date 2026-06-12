<?php

namespace App\Modules\Core\Bot\Handlers\Admin;

use App\Models\SvpUser;
use Illuminate\Support\Facades\DB;

class AdminReceiptsHandler extends AbstractAdminHandler
{
    use AdminHandlerTrait;

    protected function navLabel(SvpUser $user): string
    {
        return $this->texts->getForUser('btn.admin.receipts', $user, '🧾 Receipts');
    }

    protected function sectionIntro(SvpUser $user): string
    {
        $pending = DB::table('svp_receipts')->where('status', 'pending')->count();

        return "Receipts\nPending: {$pending}";
    }
}
