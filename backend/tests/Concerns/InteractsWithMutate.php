<?php

namespace Tests\Concerns;

use App\Models\DashboardUser;
use Database\Seeders\SvpTestDataSeeder;
use Illuminate\Support\Facades\Hash;

trait InteractsWithMutate
{
    use CreatesSvpTestSchema;

    protected function setUpMutateFixtures(): void
    {
        $this->createSvpTestSchema();
        $this->seed(SvpTestDataSeeder::class);
    }

    protected function actingAsAdmin(): DashboardUser
    {
        $user = DashboardUser::query()->where('username', 'admin')->first();
        $this->actingAs($user);

        return $user;
    }

    protected function actingAsReseller(): DashboardUser
    {
        $user = DashboardUser::query()->where('username', 'reseller')->first();
        $this->actingAs($user);

        return $user;
    }

    /** @return array<string, mixed> */
    protected function mutatePayloadFor(string $op): array
    {
        return match ($op) {
            'settings_tab' => ['tab' => 'general', 'foo' => 'bar'],
            'user_status' => ['user_id' => 101, 'status' => 'approved'],
            'user_balance_delta' => ['user_id' => 101, 'delta' => 10],
            'user_manual_create' => ['username' => 'newuser', 'first_name' => 'New'],
            'user_create_service' => ['user_id' => 101, 'panel_id' => 1],
            'service_delete' => ['service_id' => 1],
            'receipt_action' => ['receipt_id' => 1, 'action' => 'approve'],
            'plan' => ['name' => 'Test Plan', 'panel_id' => 1],
            'panel_test' => ['panel_id' => 1],
            'user_add_days' => ['service_id' => 1, 'days' => 7],
            'user_add_volume' => ['service_id' => 1, 'extra_gb' => 5],
            'user_renew_service' => ['service_id' => 1, 'mode' => 'free'],
            'reseller_permissions_save' => ['svp_user_id' => 100, 'permissions' => ['users.manage' => true]],
            'wholesale_line_save' => ['panel_id' => 1, 'label' => 'Line 1'],
            'l2tp_add' => ['label' => 'L2TP 1', 'ssh_host' => '10.0.0.1', 'l2tp_host' => 'l2tp.test'],
            'broadcast_send' => ['bc_text' => 'hi', 'bc_targets' => 'telegram'],
            'crypto_settings' => ['enabled' => true],
            'texts_save' => ['key' => 'welcome', 'value' => 'Hello'],
            default => [],
        };
    }
}
