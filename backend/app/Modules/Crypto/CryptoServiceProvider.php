<?php

namespace App\Modules\Crypto;

use App\Modules\AbstractModuleServiceProvider;
use App\Modules\Crypto\Mutations\CryptoMutations;

class CryptoServiceProvider extends AbstractModuleServiceProvider
{
    public function moduleKey(): string
    {
        return 'crypto';
    }

    public function mutationHandlers(): array
    {
        return app(CryptoMutations::class)->handlers();
    }

    protected function bootEnabled(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');
    }
}
