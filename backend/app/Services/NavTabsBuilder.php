<?php

namespace App\Services;

class NavTabsBuilder
{
    /** @return list<array{key: string, label: string}> */
    public function build(bool $l2tpEnabled = true): array
    {
        $tabs = [
            ['key' => 'dashboard', 'label' => 'پیشخوان'],
            ['key' => 'monitoring', 'label' => 'مانیتورینگ'],
            ['key' => 'site_settings', 'label' => 'تنظیمات سایت'],
            ['key' => 'bots', 'label' => 'ربات‌ها'],
            ['key' => 'xui_panels', 'label' => 'پنل‌های 3x-ui'],
            ['key' => 'plan_cats', 'label' => 'دسته‌های خرید'],
            ['key' => 'plans', 'label' => 'پلن‌ها'],
            ['key' => 'cards', 'label' => 'کارت‌ها'],
        ];

        if ($l2tpEnabled && svp_modules()->isEnabled('l2tp')) {
            $tabs[] = ['key' => 'l2tp_servers', 'label' => 'سرورهای L2TP'];
        }

        return array_merge($tabs, [
            ['key' => 'receipts', 'label' => 'رسیدها'],
            ['key' => 'broadcast', 'label' => 'پیام همگانی'],
            ['key' => 'texts', 'label' => 'متن‌ها'],
            ['key' => 'users', 'label' => 'کاربران'],
            ['key' => 'backup', 'label' => 'بکاپ'],
            ['key' => 'notifications', 'label' => 'نوتیفیکیشن'],
            ['key' => 'referral', 'label' => 'ریفرال و لینک ربات'],
            ['key' => 'referral_reports', 'label' => 'گزارشات رفرال'],
            ['key' => 'reseller_reports', 'label' => 'گزارشات نمایندگان'],
            ['key' => 'marketing_lifecycle', 'label' => 'بازگشت مشتری'],
            ['key' => 'discounts', 'label' => 'کدهای تخفیف'],
            ['key' => 'logs', 'label' => 'لاگ‌ها'],
            ['key' => 'resellers', 'label' => 'نمایندگان'],
            ['key' => 'audit', 'label' => 'ممیزی'],
        ]);
    }

    /**
     * @param  list<array{key: string, label: string}>  $tabs
     * @param  array<string, bool>  $allowedMap
     * @return list<array{key: string, label: string}>
     */
    public function filterForReseller(array $tabs, array $allowedMap): array
    {
        return array_values(array_filter(
            $tabs,
            fn (array $tab) => ! empty($allowedMap[$tab['key'] ?? ''])
        ));
    }
}
