<?php

namespace App\Modules\Core\Bot\Handlers;

use App\Models\SvpService;
use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\KeyboardBuilder;
use App\Modules\Core\Bot\Services\TextService;

class ServiceHandler
{
    public function __construct(
        protected BotRuntime $runtime,
        protected TextService $texts,
        protected KeyboardBuilder $keyboards,
    ) {}

    public function listServices(BotContext $ctx, SvpUser $user, int $chatId): void
    {
        $services = SvpService::query()
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        if ($services->isEmpty()) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.service.none', $user));

            return;
        }

        $rows = [];
        foreach ($services as $svc) {
            $label = $svc->email ?: ('#'.$svc->id);
            $rows[] = [['text' => $label, 'callback_data' => 'svc:v:'.$svc->id]];
        }

        $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.service.list', $user), [
            'reply_markup' => $this->keyboards->inline($rows),
        ]);
    }

    /** @param  array<string, mixed>  $payload */
    public function handleCallback(BotContext $ctx, SvpUser $user, array $payload): void
    {
        $action = (string) ($payload['action'] ?? '');
        $svcId = (int) ($payload['svc_id'] ?? 0);
        $chatId = (int) ($payload['chat_id'] ?? 0);

        $svc = SvpService::query()->where('user_id', $user->id)->find($svcId);
        if (! $svc) {
            return;
        }

        if ($action === 'v') {
            $msg = $this->texts->format(
                $this->texts->getForUser('msg.service.detail', $user, "Service #{id}\nEmail: {email}"),
                ['id' => $svc->id, 'email' => $svc->email]
            );
            $this->runtime->sendMessage($ctx, $chatId, $msg);

            return;
        }

        if ($action === 'r') {
            app(\App\Services\Commerce\ServiceProvisionService::class)->renew($svcId, 'free');
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.service.renewed', $user));
        }
    }
}
