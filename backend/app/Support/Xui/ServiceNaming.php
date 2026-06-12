<?php

namespace App\Support\Xui;

use Illuminate\Support\Facades\DB;

class ServiceNaming
{
    public static function mode(): string
    {
        $m = (string) (DB::table('svp_settings')->where('key_name', 'service_naming_mode')->value('value') ?? 'legacy');

        return in_array($m, ['legacy', 'platform_slug', 'prefix_numbered', 'numbered'], true) ? $m : 'legacy';
    }

    public static function usesPlatformSlugForNew(): bool
    {
        return self::mode() === 'platform_slug';
    }

    /** @param  object|array<string,mixed>|null  $user */
    public static function provisionCanonicalLabel($user, ?string $platform = null, int $lineIndex = 1): string
    {
        $uid = is_object($user) ? (int) ($user->id ?? 0) : (int) ($user['id'] ?? 0);
        if (self::usesPlatformSlugForNew()) {
            return self::generatePlatformSlug($user, $platform);
        }

        return self::generateLegacyCanonicalEmail($uid);
    }

    /**
     * @param  object|array<string,mixed>|null  $user
     */
    public static function provisionPanelEmail($user, string $canonical, ?string $platform = null): string
    {
        $canonical = trim($canonical);
        $uid = is_object($user) ? (int) ($user->id ?? 0) : (int) ($user['id'] ?? 0);
        if (self::usesPlatformSlugForNew()) {
            if (str_contains($canonical, '@')) {
                return strtolower($canonical);
            }

            return strtolower($canonical).'@svp.local';
        }
        if (self::mode() === 'legacy') {
            if (str_contains($canonical, '@')) {
                return strtolower($canonical);
            }

            return self::generateLegacyCanonicalEmail($uid);
        }

        return self::uniquePanelClientId($canonical !== '' ? $canonical : self::generateLegacyCanonicalEmail($uid));
    }

    public static function generateLegacyCanonicalEmail(int $userId): string
    {
        return 'u'.$userId.'@svp.local';
    }

    /**
     * @param  object|array<string,mixed>|null  $user
     */
    public static function generatePlatformSlug($user, ?string $platform = null): string
    {
        $uid = is_object($user) ? (int) ($user->id ?? 0) : (int) ($user['id'] ?? 0);
        $plat = $platform ?? 'tg';
        $username = is_object($user) ? trim((string) ($user->username ?? '')) : trim((string) ($user['username'] ?? ''));
        $base = $username !== '' ? preg_replace('/[^a-z0-9_-]/i', '', $username) : 'u'.$uid;

        return strtolower($plat.'_'.$base);
    }

    public static function uniquePanelClientId(string $canonical): string
    {
        $s = preg_replace('/[^a-z0-9._-]/i', '', strtolower(trim($canonical))) ?? '';

        return $s !== '' ? $s : 'client_'.bin2hex(random_bytes(4));
    }
}
