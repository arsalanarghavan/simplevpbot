<?php

namespace App\Modules\Telegram\Http;

use App\Http\Controllers\Controller;
use App\Modules\Telegram\Jobs\ProcessTelegramUpdateJob;
use App\Services\SettingsStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function telegram(Request $request, SettingsStore $settings, string $secret): JsonResponse
    {
        $expected = (string) $settings->get('telegram_webhook_secret', '');
        if ($expected === '' || ! hash_equals($expected, $secret)) {
            return response()->json(svp_err('Forbidden'), 403);
        }

        ProcessTelegramUpdateJob::dispatch($request->all());

        return response()->json(['ok' => true]);
    }
}
