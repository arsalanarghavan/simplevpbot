<?php

namespace App\Modules\Core\Bot\Handlers\Admin;

use App\Models\SvpUser;

class AdminBulkHandler extends AbstractAdminHandler
{
    use AdminHandlerTrait;

    protected function navLabel(SvpUser $user): string
    {
        return $this->texts->getForUser('btn.admin.bulk', $user, '⚡ Bulk');
    }

    protected function sectionIntro(SvpUser $user): string
    {
        return 'Bulk user operations';
    }
}
