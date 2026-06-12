<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SimpleVPBot modules (enable/disable)
    |--------------------------------------------------------------------------
    | Set SVP_MODULE_* in .env to override (true/false).
    */
    'modules' => [
        'core' => [
            'enabled' => true,
            'label' => 'Core',
            'depends' => [],
            'provider' => \App\Modules\Core\CoreServiceProvider::class,
        ],
        'telegram' => [
            'enabled' => env('SVP_MODULE_TELEGRAM', true),
            'label' => 'Telegram Bot',
            'depends' => ['core'],
            'provider' => \App\Modules\Telegram\TelegramServiceProvider::class,
        ],
        'bale' => [
            'enabled' => env('SVP_MODULE_BALE', true),
            'label' => 'Bale Bot',
            'depends' => ['core'],
            'provider' => \App\Modules\Bale\BaleServiceProvider::class,
        ],
        'xui_panel' => [
            'enabled' => env('SVP_MODULE_XUI_PANEL', true),
            'label' => '3x-ui Panel',
            'depends' => ['core'],
            'provider' => \App\Modules\XuiPanel\XuiPanelServiceProvider::class,
        ],
        'relay' => [
            'enabled' => env('SVP_MODULE_RELAY', false),
            'label' => 'Telegram Relay',
            'depends' => ['core', 'telegram'],
            'provider' => \App\Modules\Relay\RelayServiceProvider::class,
        ],
        'crypto' => [
            'enabled' => env('SVP_MODULE_CRYPTO', true),
            'label' => 'Crypto (NOWPayments)',
            'depends' => ['core'],
            'provider' => \App\Modules\Crypto\CryptoServiceProvider::class,
        ],
        'l2tp' => [
            'enabled' => env('SVP_MODULE_L2TP', true),
            'label' => 'L2TP',
            'depends' => ['core'],
            'provider' => \App\Modules\L2tp\L2tpServiceProvider::class,
        ],
        'marketing' => [
            'enabled' => env('SVP_MODULE_MARKETING', true),
            'label' => 'Marketing',
            'depends' => ['core'],
            'provider' => \App\Modules\Marketing\MarketingServiceProvider::class,
        ],
        'reseller' => [
            'enabled' => env('SVP_MODULE_RESELLER', true),
            'label' => 'Reseller',
            'depends' => ['core'],
            'provider' => \App\Modules\Reseller\ResellerServiceProvider::class,
        ],
        'backup' => [
            'enabled' => env('SVP_MODULE_BACKUP', true),
            'label' => 'Backup',
            'depends' => ['core'],
            'provider' => \App\Modules\Backup\BackupServiceProvider::class,
        ],
    ],
];
