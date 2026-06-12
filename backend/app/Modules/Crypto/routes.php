<?php

use App\Modules\Crypto\Http\IpnController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->group(function () {
    Route::post('crypto-ipn/{secret}', [IpnController::class, 'handle']);
});
