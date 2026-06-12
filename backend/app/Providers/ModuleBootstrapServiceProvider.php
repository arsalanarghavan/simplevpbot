<?php

namespace App\Providers;

use App\Modules\ModuleManager;
use Illuminate\Support\ServiceProvider;

class ModuleBootstrapServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleManager::class);

        foreach (config('modules.modules', []) as $module) {
            $provider = $module['provider'] ?? null;
            if (is_string($provider) && class_exists($provider)) {
                $this->app->register($provider);
            }
        }
    }
}
