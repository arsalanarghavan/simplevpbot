<?php

namespace Tests\Feature\Core;

use App\Support\Metrics\SvpMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class MetricsIncrementTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_successful_mutate_increments_mutate_op_total(): void
    {
        $before = SvpMetrics::get('mutate_op_total');

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'settings_tab',
            'tab' => 'general',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertGreaterThan($before, SvpMetrics::get('mutate_op_total'));
    }

    /** @return array<string, array{0: string}> */
    public static function sampleOpsProvider(): array
    {
        return [
            'settings_tab' => ['settings_tab'],
            'user_status' => ['user_status'],
            'plan' => ['plan'],
            'receipt_action' => ['receipt_action'],
            'broadcast_send' => ['broadcast_send'],
        ];
    }

    /** @dataProvider sampleOpsProvider */
    public function test_mutate_op_total_increments_per_op(string $op): void
    {
        $key = 'mutate_op_total:'.$op;
        $before = SvpMetrics::get($key);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', array_merge(
            ['op' => $op],
            $this->mutatePayloadFor($op),
        ))->assertOk()->assertJsonPath('ok', true);

        $this->assertGreaterThan($before, SvpMetrics::get($key));
    }
}
