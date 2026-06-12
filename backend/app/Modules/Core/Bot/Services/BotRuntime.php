<?php

namespace App\Modules\Core\Bot\Services;

use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Clients\BaleApiClient;
use App\Modules\Core\Bot\Clients\TelegramApiClient;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\Crypt;

class BotRuntime
{
    public function __construct(protected SettingsStore $settings) {}

    public function client(BotContext $ctx): TelegramApiClient|BaleApiClient|null
    {
        $token = $this->tokenForContext($ctx);
        if ($token === '') {
            return null;
        }

        if ($ctx->platform === 'bale') {
            return new BaleApiClient($token);
        }

        $proxy = trim((string) $this->settings->get('telegram_http_proxy', ''));

        return new TelegramApiClient($token, $proxy !== '' ? $proxy : null);
    }

    public function sendMessage(BotContext $ctx, int $chatId, string $text, array $extra = []): ?array
    {
        $client = $this->client($ctx);
        if (! $client) {
            return null;
        }

        if ($ctx->platform === 'bale') {
            $text = str_replace(['**', '__'], '', $text);
        }

        return $client->sendMessage(array_merge([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $extra['parse_mode'] ?? 'HTML',
        ], $extra));
    }

    /** @param  array<string, mixed>  $params */
    public function answerCallbackQuery(BotContext $ctx, array $params): ?array
    {
        return $this->client($ctx)?->answerCallbackQuery($params);
    }

    /** @param  array<string, mixed>  $params */
    public function editMessageText(BotContext $ctx, array $params): ?array
    {
        return $this->client($ctx)?->editMessageText($params);
    }

    public function tokenForContext(BotContext $ctx): string
    {
        if ($ctx->isResellerBot()) {
            $profile = $ctx->resellerProfile ?? [];
            $key = $ctx->platform === 'bale' ? 'bale_token' : 'telegram_token';
            $stored = trim((string) ($profile[$key] ?? $profile['token'] ?? ''));
            if ($stored === '') {
                return '';
            }

            try {
                return Crypt::decryptString($stored);
            } catch (\Throwable) {
                return $stored;
            }
        }

        if ($ctx->platform === 'bale') {
            return (string) $this->settings->get('bale_token', '');
        }

        return (string) $this->settings->get('telegram_bot_token', $this->settings->get('telegram_token', ''));
    }

    public function webhookUrl(string $platform, string $secret, int $resellerId = 0): string
    {
        $base = rtrim((string) $this->settings->get('public_site_url', config('app.url')), '/');
        if ($resellerId > 0) {
            return "{$base}/api/v1/webhook/{$platform}/reseller/{$resellerId}/".rawurlencode($secret);
        }

        return "{$base}/api/v1/webhook/{$platform}/".rawurlencode($secret);
    }
}
