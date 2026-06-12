<?php

namespace App\Modules\Core\Bot\Handlers\Admin;

use App\Models\SvpUser;

class AdminFinanceHandler extends AbstractAdminHandler
{
    use AdminHandlerTrait;

    protected function navLabel(SvpUser $user): string
    {
        return $this->texts->getForUser('btn.admin.finance', $user, '💰 Finance');
    }

    protected function sectionIntro(SvpUser $user): string
    {
        return 'Finance';
    }
}
