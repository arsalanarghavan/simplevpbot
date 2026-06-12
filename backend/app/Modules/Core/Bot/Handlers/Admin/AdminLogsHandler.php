<?php

namespace App\Modules\Core\Bot\Handlers\Admin;

use App\Models\SvpUser;

class AdminLogsHandler extends AbstractAdminHandler
{
    use AdminHandlerTrait;

    protected function navLabel(SvpUser $user): string
    {
        return $this->texts->getForUser('btn.admin.logs', $user, '📋 Logs');
    }

    protected function sectionIntro(SvpUser $user): string
    {
        return 'System logs';
    }
}
