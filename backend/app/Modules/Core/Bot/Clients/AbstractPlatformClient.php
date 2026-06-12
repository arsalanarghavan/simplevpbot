<?php

namespace App\Modules\Core\Bot\Clients;

use Illuminate\Support\Facades\Http;

abstract class AbstractPlatformClient
{
    public function __construct(
        protected string $token,
        protected ?string $httpProxy = null,
    ) {}

    abstract protected function baseUrl(): string;

    /** @param  array<string, mixed>  $params */
    public function sendMessage(array $params): ?array
    {
        return $this->post('sendMessage', $params);
    }

    /** @param  array<string, mixed>  $params */
    public function sendPhoto(array $params): ?array
    {
        return $this->post('sendPhoto', $params);
    }

    /** @param  array<string, mixed>  $params */
    public function sendMediaGroup(array $params): ?array
    {
        return $this->post('sendMediaGroup', $params);
    }

    /** @param  array<string, mixed>  $params */
    public function apiCall(string $method, array $params): ?array
    {
        return $this->post($method, $params);
    }

    /** @param  array<string, mixed>  $params */
    public function answerCallbackQuery(array $params): ?array
    {
        return $this->post('answerCallbackQuery', $params);
    }

    /** @param  array<string, mixed>  $params */
    public function editMessageText(array $params): ?array
    {
        return $this->post('editMessageText', $params);
    }

    /** @param  array<string, mixed>  $params */
    public function setWebhook(array $params): ?array
    {
        return $this->post('setWebhook', $params);
    }

  /** @param  array<string, mixed>  $params */
    public function deleteWebhook(array $params = []): ?array
    {
        return $this->post('deleteWebhook', $params);
    }

    public function getMe(): ?array
    {
        return $this->post('getMe', []);
    }

    /** @param  array<string, mixed>  $params */
    protected function post(string $method, array $params): ?array
    {
        if ($this->token === '') {
            return null;
        }

        try {
            $pending = Http::timeout(30);
            $proxy = trim((string) ($this->httpProxy ?? ''));
            if ($proxy !== '') {
                $pending = $pending->withOptions(['proxy' => $proxy]);
            }
            $response = $pending->post($this->baseUrl().$method, $params);

            return $response->json();
        } catch (\Throwable) {
            return null;
        }
    }
}
