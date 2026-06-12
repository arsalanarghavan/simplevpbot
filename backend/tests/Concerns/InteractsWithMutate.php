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
            'user_create_service' => ['user_id' => 101, 'panel_id' => 1, 'plan_id' => 1],
            'service_delete' => ['service_id' => 1],
            'receipt_action' => ['receipt_id' => 1, 'action' => 'approve'],
            'receipt_set_status' => ['receipt_id' => 1, 'status' => 'approved'],
            'receipt_update' => ['receipt_id' => 1, 'amount' => 50000],
            'plan' => ['name' => 'Test Plan', 'panel_id' => 1, 'category' => 'normal'],
            'plan_category' => ['slug' => 'cat1', 'label' => 'Cat 1', 'panel_id' => 1],
            'panel_test' => ['panel_id' => 1],
            'user_add_days' => ['service_id' => 1, 'days' => 7],
            'user_reduce_days' => ['service_id' => 1, 'days' => 1],
            'user_add_volume' => ['service_id' => 1, 'extra_gb' => 5],
            'user_reduce_volume' => ['service_id' => 1, 'reduce_gb' => 1],
            'user_renew_service' => ['service_id' => 1, 'mode' => 'free'],
            'user_service_transfer' => ['service_id' => 1, 'target' => '200'],
            'user_service_toggle_enable' => ['service_id' => 1, 'enable' => 1],
            'user_service_add_slots' => ['service_id' => 1, 'slots' => 1],
            'user_service_reduce_slots' => ['service_id' => 1, 'slots' => 1],
            'user_admin_message' => ['user_id' => 101, 'text' => 'hi', 'channel' => 'telegram'],
            'service_alerts_patch' => ['service_id' => 1, 'alerts' => []],
            'service_set_note' => ['service_id' => 1, 'note' => 'note'],
            'service_panel_sync' => ['service_id' => 1],
            'service_regen_key' => ['service_id' => 1],
            'service_regen_sub_id' => ['service_id' => 1],
            'service_panel_refresh' => ['service_id' => 1],
            'service_panel_delete_client' => ['service_id' => 1],
            'service_set_limit_ip' => ['service_id' => 1, 'limit_ip' => 2],
            'membership' => ['user_id' => 101],
            'reseller_permissions_save' => ['svp_user_id' => 100, 'permissions' => ['users.manage' => true]],
            'reseller_panel_prices_save' => ['reseller_svp_user_id' => 100, 'prices' => []],
            'reseller_payment_methods_save' => ['reseller_svp_user_id' => 100, 'methods' => []],
            'reseller_wallet_topup_checkout' => ['amount' => 1000],
            'reseller_wp_provision' => ['username' => 'subres', 'parent_svp_user_id' => 100],
            'reseller_inbound_labels_save' => ['reseller_svp_user_id' => 100, 'labels' => []],
            'wholesale_line_save' => ['panel_id' => 1, 'label' => 'Line 1'],
            'l2tp_add' => ['label' => 'L2TP 1', 'ssh_host' => '10.0.0.1', 'l2tp_host' => 'l2tp.test'],
            'broadcast_send' => ['bc_text' => 'hi', 'bc_targets' => 'telegram'],
            'broadcast_cancel' => ['broadcast_id' => 1],
            'crypto_settings' => ['enabled' => true],
            'texts_save' => ['key' => 'welcome', 'value' => 'Hello'],
            'card_add' => ['title' => 'Card', 'amount' => 10000],
            'card_update' => ['card_id' => 1, 'title' => 'Card'],
            'card_delete' => ['card_id' => 1],
            'card_reorder' => ['order' => [1]],
            'discount_redemptions' => ['code' => 'TEST'],
            'bot_reseller_save' => ['reseller_svp_user_id' => 100, 'telegram_enabled' => true],
            'bot_reseller_secret_rotate' => ['reseller_svp_user_id' => 100],
            'bot_reseller_toggle_enabled' => ['reseller_svp_user_id' => 100, 'enabled' => true],
            'bot_test_telegram' => [],
            'bot_test_bale' => [],
            'bot_diagnostics' => ['platform' => 'telegram'],
            'reseller_bot_webhook_set' => ['reseller_svp_user_id' => 100],
            'reseller_bot_webhook_delete' => ['reseller_svp_user_id' => 100],
            'reseller_bot_secret_rotate' => ['reseller_svp_user_id' => 100],
            'reseller_bot_tokens_save' => ['reseller_svp_user_id' => 100, 'telegram_token' => '1:abc'],
            'telegram_relay_set_webhook_reseller' => ['reseller_svp_user_id' => 100],
            'bot_admin_id_add' => ['admin_id' => 111],
            'bot_admin_id_remove' => ['admin_id' => 111],
            'users_bulk_wallet' => ['user_ids' => [101], 'delta' => 10],
            'users_bulk_volume' => ['user_ids' => [101], 'extra_gb' => 1],
            'users_bulk_extend' => ['user_ids' => [101], 'days' => 3],
            'users_bulk_alerts' => ['user_ids' => [101], 'alerts' => []],
            'users_bulk_slots' => ['user_ids' => [101], 'slots' => 1],
            'users_bulk_job_cancel' => ['job_id' => 1],
            'users_bulk_job_resume' => ['job_id' => 1],
            'configs_client_toggle_enable' => ['panel_id' => 1, 'inbound_id' => 1, 'email' => 'child@local', 'enabled' => true],
            'configs_client_reset_traffic' => ['panel_id' => 1, 'inbound_id' => 1, 'email' => 'child@local'],
            'configs_client_delete' => ['panel_id' => 1, 'inbound_id' => 1, 'email' => 'missing@local'],
            'configs_delete_expired_linked' => ['panel_id' => 1],
            'configs_panel_client_patch' => ['panel_id' => 1, 'inbound_id' => 1, 'email' => 'child@local'],
            'configs_clients_batch' => ['panel_id' => 1, 'inbound_id' => 1, 'action' => 'noop', 'emails' => []],
            'configs_assign_plan' => ['panel_id' => 1, 'inbound_id' => 1, 'email' => 'child@local', 'plan_id' => 1],
            'marketing_rule_save' => ['segment_key' => 'never_purchased', 'enabled' => true],
            'marketing_rule_delete' => ['rule_id' => 1],
            'marketing_send_manual' => ['segment_key' => 'never_purchased', 'message_body' => 'hi'],
            'marketing_run_rule_now' => ['rule_id' => 1],
            default => [],
        };
    }
}
