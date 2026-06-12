<?php

namespace App\Modules\Core\Bot\Handlers\Admin;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use Illuminate\Support\Facades\DB;

class AdminCatalogHandler extends AbstractAdminHandler
{
    use AdminHandlerTrait;

    protected function navLabel(SvpUser $user): string
    {
        return $this->texts->getForUser('btn.admin.catalog', $user, '📦 Catalog');
    }

    /** @param  array<int, string>  $parts */
    public function handleCallback(BotContext $ctx, array $parts, ?SvpUser $user, int $chatId, int $msgId): void
    {
        $plans = DB::table('svp_plans')->count();
        $this->send($ctx, $chatId, "Plans: {$plans}");
    }

    protected function sectionIntro(SvpUser $user): string
    {
        return 'Catalog management';
    }
}
