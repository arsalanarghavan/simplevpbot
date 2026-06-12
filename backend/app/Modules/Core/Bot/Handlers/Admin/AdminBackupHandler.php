<?php

namespace App\Modules\Core\Bot\Handlers\Admin;

use App\Models\SvpUser;

class AdminBackupHandler extends AbstractAdminHandler
{
    use AdminHandlerTrait;

    protected function navLabel(SvpUser $user): string
    {
        return $this->texts->getForUser('btn.admin.backup', $user, '💾 Backup');
    }

    protected function sectionIntro(SvpUser $user): string
    {
        return 'Backup & restore';
    }
}
