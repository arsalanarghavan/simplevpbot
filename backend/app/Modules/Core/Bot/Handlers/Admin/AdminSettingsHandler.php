<?php

namespace App\Modules\Core\Bot\Handlers\Admin;

use App\Models\SvpUser;

class AdminSettingsHandler extends AbstractAdminHandler
{
    use AdminHandlerTrait;

    protected function navLabel(SvpUser $user): string
    {
        return $this->texts->getForUser('btn.admin.settings', $user, '⚙️ Settings');
    }

    protected function sectionIntro(SvpUser $user): string
    {
        return 'Settings';
    }
}
