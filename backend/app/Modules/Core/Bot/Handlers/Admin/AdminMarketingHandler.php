<?php

namespace App\Modules\Core\Bot\Handlers\Admin;

use App\Models\SvpUser;

class AdminMarketingHandler extends AbstractAdminHandler
{
    use AdminHandlerTrait;

    protected function navLabel(SvpUser $user): string
    {
        return $this->texts->getForUser('btn.admin.marketing', $user, '📣 Marketing');
    }

    protected function sectionIntro(SvpUser $user): string
    {
        return 'Marketing rules & broadcasts';
    }
}
