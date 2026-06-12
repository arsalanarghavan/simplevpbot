<?php

namespace Database\Seeders;

use App\Models\DashboardUser;
use App\Services\SettingsStore;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        DashboardUser::query()->updateOrCreate(
            ['username' => env('SVP_ADMIN_USERNAME', 'admin')],
            [
                'password' => Hash::make(env('SVP_ADMIN_PASSWORD', 'changeme')),
                'role' => 'admin',
            ]
        );

        app(SettingsStore::class)->merge([
            'site_name' => 'SimpleVPBot',
            'enabled' => true,
        ]);
    }
}
