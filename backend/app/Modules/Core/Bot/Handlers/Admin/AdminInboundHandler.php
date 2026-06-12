<?php

namespace App\Modules\Core\Bot\Handlers\Admin;

use App\Models\SvpUser;

class AdminInboundHandler extends AbstractAdminHandler
{
    use AdminHandlerTrait;

    protected function navLabel(SvpUser $user): string
    {
        return $this->texts->getForUser('btn.admin.inbound', $user, '🔗 Inbounds');
    }

    protected function sectionIntro(SvpUser $user): string
    {
        return 'Inbound links';
    }
}
