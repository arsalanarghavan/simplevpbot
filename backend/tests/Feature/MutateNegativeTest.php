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
}
