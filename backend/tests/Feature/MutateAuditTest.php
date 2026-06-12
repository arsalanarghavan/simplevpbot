<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class MutateAuditTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_sensitive_op_writes_audit_log(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/mutate', [
            'op' => 'user_balance_delta',
            'user_id' => 101,
            'delta' => 5,
        ])->assertOk();

        $this->assertDatabaseHas('svp_audit_log', [
            'event_type' => 'user_balance_delta',
            'actor_kind' => 'admin',
        ]);
    }

    public function test_failed_mutate_does_not_audit(): void
    {
        $this->actingAsAdmin();
        $before = DB::table('svp_audit_log')->count();

        $this->postJson('/api/v1/admin/mutate', [
            'op' => 'user_balance_delta',
            'user_id' => 999,
            'delta' => 0,
        ]);

        $this->assertSame($before, DB::table('svp_audit_log')->count());
    }
}
