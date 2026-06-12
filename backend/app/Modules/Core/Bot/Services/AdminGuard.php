<?php

namespace App\Modules\Core\Bot\Services;

use App\Models\SvpUser;
use App\Services\SettingsStore;

class AdminGuard
{
    public function __construct(protected SettingsStore $settings) {}

    public function isPlatformAdmin(string $platform, int $platformUserId): bool
    {
        if ($platformUserId < 1) {
            return false;
        }

        $ids = $this->settings->get($platform === 'bale' ? 'bale_admin_ids' : 'telegram_admin_ids', []);
        if (! is_array($ids)) {
            $ids = [];
        }

        return in_array($platformUserId, array_map('intval', $ids), true);
    }

    public function resolveAdminByPlatformId(string $platform, int $platformUserId): ?SvpUser
    {
        if (! $this->isPlatformAdmin($platform, $platformUserId)) {
            return null;
        }

        $col = $platform === 'bale' ? 'bale_user_id' : 'tg_user_id';

        return SvpUser::query()->where($col, $platformUserId)->first();
    }
}
