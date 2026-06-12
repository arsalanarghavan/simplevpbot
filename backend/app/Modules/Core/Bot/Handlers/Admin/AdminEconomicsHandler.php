<?php

namespace App\Modules\Core\Bot\Handlers\Admin;

use App\Models\SvpUser;

class AdminEconomicsHandler extends AbstractAdminHandler
{
    use AdminHandlerTrait;

    protected function navLabel(SvpUser $user): string
    {
        return $this->texts->getForUser('btn.admin.economics', $user, '📉 Economics');
    }

    protected function sectionIntro(SvpUser $user): string
    {
        return 'Unit & panel economics';
    }
}
