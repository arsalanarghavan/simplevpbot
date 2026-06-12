<?php

namespace Tests\Feature;

use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

class WebhookModuleDisabledTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        app(SettingsStore::class)->set('telegram_webhook_secret', 'test-secret');
    }

    public function test_telegram_webhook_returns_503_when_module_disabled(): void
    {
        $this->setModuleEnabled('telegram', false);

        $this->postJson('/api/v1/webhook/telegram/test-secret', ['update_id' => 1])
            ->assertStatus(503)
            ->assertJsonPath('message', 'module_missing');
    }
}
