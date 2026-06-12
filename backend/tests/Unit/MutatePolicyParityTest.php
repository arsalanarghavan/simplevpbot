<?php

namespace Tests\Unit;

use App\Services\Mutations\MutatePolicyService;
use Tests\TestCase;

class MutatePolicyParityTest extends TestCase
{
    public function test_reseller_map_has_fifty_eight_entries(): void
    {
        $policy = new MutatePolicyService;
        $ref = new \ReflectionClass($policy);
        $prop = $ref->getProperty('resellerMap');
        $prop->setAccessible(true);
        /** @var array<string, string> $map */
        $map = $prop->getValue($policy);

        $this->assertCount(58, $map);
    }

    public function test_admin_only_ops_return_null_permission(): void
    {
        $policy = new MutatePolicyService;
        $this->assertNull($policy->requiredResellerPermission('settings_tab'));
        $this->assertTrue($policy->isAdminOnly('user_merge'));
    }
}
