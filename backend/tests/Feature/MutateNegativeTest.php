<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class MutateNegativeTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_unknown_op_returns_error(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'not_a_real_op_xyz',
        ])->assertOk()
            ->assertJsonPath('ok', false);
    }

    public function test_reseller_blocked_on_admin_only_op(): void
    {
        $this->actingAsReseller()->postJson('/api/v1/admin/mutate', [
            'op' => 'logs_clear',
        ])->assertOk()
            ->assertJsonPath('ok', false);
    }

    public function test_invalid_settings_tab_rejected(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'settings_tab',
            'tab' => 'not_a_tab',
        ])->assertOk()->assertJsonPath('ok', false);
    }

    public function test_reseller_configs_client_without_perm(): void
    {
        $this->actingAsReseller()->postJson('/api/v1/admin/mutate', [
            'op' => 'configs_client_toggle_enable',
            'client_id' => 1,
            'enabled' => false,
        ])->assertOk()->assertJsonPath('ok', false);
    }

    public function test_relay_mutate_blocked_when_module_off(): void
    {
        config(['svp.modules.relay' => false]);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'telegram_relay_sync_tenant',
        ])->assertOk()->assertJsonPath('message', 'module_disabled');
    }
}
