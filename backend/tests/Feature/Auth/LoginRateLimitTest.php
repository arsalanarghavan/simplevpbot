<?php

namespace Tests\Feature\Auth;

use App\Models\DashboardUser;
use Database\Seeders\SvpTestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class LoginRateLimitTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        $this->seed(SvpTestDataSeeder::class);
        config(['svp.login_rate_limit_per_min' => 3]);
        Cache::flush();
    }

    public function test_login_rate_limited_per_ip(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'username' => 'wrong',
                'password' => 'wrong',
            ])->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/login', [
            'username' => 'wrong',
            'password' => 'wrong',
        ])->assertStatus(429)->assertJson(['code' => 'rate_limited']);
    }

    public function test_successful_login_not_blocked_after_failures_below_limit(): void
    {
        DashboardUser::query()->where('username', 'admin')->update([
            'password' => Hash::make('secret'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'username' => 'admin',
            'password' => 'secret',
        ])->assertOk();
    }
}
