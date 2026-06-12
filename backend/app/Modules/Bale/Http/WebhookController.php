<?php

namespace App\Modules\Bale\Http;

use App\Http\Controllers\Controller;
use App\Modules\Bale\Jobs\ProcessBaleUpdateJob;
use App\Services\SettingsStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function bale(Request $request, SettingsStore $settings, string $secret): JsonResponse
    {
        $expected = (string) $settings->get('bale_webhook_secret', '');
        if ($expected === '' || ! hash_equals($expected, $secret)) {
            return response()->json(svp_err('Forbidden'), 403);
        }

        ProcessBaleUpdateJob::dispatch($request->all());

        return response()->json(['ok' => true]);
    }
}
