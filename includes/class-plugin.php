<?php
/**
 * Main plugin loader.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Plugin
 */
class SimpleVPBot_Plugin {

	/**
	 * Instance.
	 *
	 * @var SimpleVPBot_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Class map for autoload.
	 *
	 * @var array<string, string>
	 */
	private static $class_map = array(
		'SimpleVPBot_Bot_Text_Defaults'   => 'class-bot-text-defaults.php',
		'SimpleVPBot_Settings'            => 'class-settings.php',
		'SimpleVPBot_Activator'           => 'class-activator.php',
		'SimpleVPBot_Deactivator'         => 'class-deactivator.php',
		'SimpleVPBot_Logger'              => 'class-logger.php',
		'SimpleVPBot_Xui_Client'          => 'api/class-xui-client.php',
		'SimpleVPBot_Bot_Client'          => 'api/class-bot-client.php',
		'SimpleVPBot_Telegram_Client'     => 'api/class-telegram-client.php',
		'SimpleVPBot_Bale_Client'         => 'api/class-bale-client.php',
		'SimpleVPBot_SSH_Client'          => 'api/class-ssh-client.php',
		'SimpleVPBot_Model_User'          => 'models/class-model-user.php',
		'SimpleVPBot_Model_Service'       => 'models/class-model-service.php',
		'SimpleVPBot_Model_Service_Ip_Log' => 'models/class-model-service-ip-log.php',
		'SimpleVPBot_Model_Reseller_Panel_Price' => 'models/class-model-reseller-panel-price.php',
		'SimpleVPBot_Model_Reseller_Parent_Panel_Floor' => 'models/class-model-reseller-parent-panel-floor.php',
		'SimpleVPBot_Model_Reseller_Bot_Profile' => 'models/class-model-reseller-bot-profile.php',
		'SimpleVPBot_Model_Plan'          => 'models/class-model-plan.php',
		'SimpleVPBot_Model_Plan_Category' => 'models/class-model-plan-category.php',
		'SimpleVPBot_Model_Panel'         => 'models/class-model-panel.php',
		'SimpleVPBot_Model_Panel_Inbound_Client' => 'models/class-model-panel-inbound-client.php',
		'SimpleVPBot_Model_Panel_Inbound_Api' => 'models/class-model-panel-inbound-api.php',
		'SimpleVPBot_Model_Panel_Online_Daily' => 'models/class-model-panel-online-daily.php',
		'SimpleVPBot_Model_Card'          => 'models/class-model-card.php',
		'SimpleVPBot_Model_L2TP_Server'   => 'models/class-model-l2tp-server.php',
		'SimpleVPBot_Model_Transaction'   => 'models/class-model-transaction.php',
		'SimpleVPBot_Model_Discount_Code' => 'models/class-model-discount-code.php',
		'SimpleVPBot_Model_Receipt'       => 'models/class-model-receipt.php',
		'SimpleVPBot_Model_Pending'       => 'models/class-model-pending.php',
		'SimpleVPBot_Model_Sync_Code'     => 'models/class-model-sync-code.php',
		'SimpleVPBot_Model_Text'          => 'models/class-model-text.php',
		'SimpleVPBot_Model_Referral_Event' => 'models/class-model-referral-event.php',
		'SimpleVPBot_Model_Broadcast'     => 'models/class-model-broadcast.php',
		'SimpleVPBot_Model_Users_Bulk_Job' => 'models/class-model-users-bulk-job.php',
		'SimpleVPBot_Model_Monitor_Host'  => 'models/class-model-monitor-host.php',
		'SimpleVPBot_Webhook'             => 'bot/class-webhook.php',
		'SimpleVPBot_Webhook_Diagnostics' => 'bot/class-webhook-diagnostics.php',
		'SimpleVPBot_Bot_Runtime'         => 'bot/class-bot-runtime.php',
		'SimpleVPBot_Bot_Context'         => 'bot/class-bot-context.php',
		'SimpleVPBot_Router'             => 'bot/class-router.php',
		'SimpleVPBot_State'              => 'bot/class-state.php',
		'SimpleVPBot_Keyboards'          => 'bot/class-keyboards.php',
		'SimpleVPBot_UI_Action_Registry' => 'bot/class-ui-action-registry.php',
		'SimpleVPBot_UI_Layout'          => 'bot/class-ui-layout.php',
		'SimpleVPBot_UI_Reply_Router'   => 'bot/class-ui-reply-router.php',
		'SimpleVPBot_Texts'              => 'bot/class-texts.php',
		'SimpleVPBot_Shared_Catalog'     => 'class-shared-catalog.php',
		'SimpleVPBot_Handler_Start'      => 'bot/handlers/class-handler-start.php',
		'SimpleVPBot_Handler_Referral'   => 'bot/handlers/class-handler-referral.php',
		'SimpleVPBot_Handler_User_Menu'  => 'bot/handlers/class-handler-user-menu.php',
		'SimpleVPBot_Handler_Buy'        => 'bot/handlers/class-handler-buy.php',
		'SimpleVPBot_Handler_Service'    => 'bot/handlers/class-handler-service.php',
		'SimpleVPBot_Handler_Wallet'     => 'bot/handlers/class-handler-wallet.php',
		'SimpleVPBot_Handler_Apps'       => 'bot/handlers/class-handler-apps.php',
		'SimpleVPBot_Handler_Support'    => 'bot/handlers/class-handler-support.php',
		'SimpleVPBot_Handler_Account'    => 'bot/handlers/class-handler-account.php',
		'SimpleVPBot_Handler_Sync'       => 'bot/handlers/class-handler-sync.php',
		'SimpleVPBot_Handler_Admin'         => 'bot/handlers/class-handler-admin.php',
		'SimpleVPBot_Handler_Admin_Hub'     => 'bot/handlers/class-handler-admin-hub.php',
		'SimpleVPBot_Handler_Admin_Settings' => 'bot/handlers/class-handler-admin-settings.php',
		'SimpleVPBot_Handler_Callback'     => 'bot/handlers/class-handler-callback.php',
		'SimpleVPBot_Service_Admin_Ops'   => 'admin/services/class-service-admin-ops.php',
		'SimpleVPBot_Service_Admin_Catalog' => 'admin/services/class-service-admin-catalog.php',
		'SimpleVPBot_Config_Link'        => 'helpers/class-config-link.php',
		'SimpleVPBot_Reseller_Branding'  => 'helpers/class-reseller-branding.php',
		'SimpleVPBot_Qr'                 => 'helpers/class-qr.php',
		'SimpleVPBot_Service_Provisioner'=> 'helpers/class-service-provisioner.php',
		'SimpleVPBot_Service_Dashboard_Panel' => 'helpers/class-service-dashboard-panel.php',
		'SimpleVPBot_Service_Panel_Transfer' => 'helpers/class-service-panel-transfer.php',
		'SimpleVPBot_L2TP_Provisioner'   => 'helpers/class-l2tp-provisioner.php',
		'SimpleVPBot_Secret_Box'         => 'helpers/class-secret-box.php',
		'SimpleVPBot_Receipt_Processor'  => 'helpers/class-receipt-processor.php',
		'SimpleVPBot_Crypto_Payment'     => 'helpers/class-crypto-payment.php',
		'SimpleVPBot_Portal_Link'        => 'helpers/class-portal-link.php',
		'SimpleVPBot_Admin_User_Ops'     => 'helpers/class-admin-user-ops.php',
		'SimpleVPBot_Admin_Dashboard_Stats' => 'helpers/class-admin-dashboard-stats.php',
		'SimpleVPBot_Dashboard_Panel_Live'  => 'helpers/class-dashboard-panel-live.php',
		'SimpleVPBot_Bot_Persian_Text'     => 'helpers/class-bot-persian-text.php',
		'SimpleVPBot_Bot_Admin_User_Caption' => 'helpers/class-bot-admin-user-caption.php',
		'SimpleVPBot_Discount_Service'   => 'helpers/class-discount-service.php',
		'SimpleVPBot_Referral_Service'   => 'helpers/class-referral-service.php',
		'SimpleVPBot_Purchase_Side_Effects' => 'helpers/class-purchase-side-effects.php',
		'SimpleVPBot_Portal_Admin'      => 'frontend/class-portal-admin.php',
		'SimpleVPBot_Dashboard_Front'  => 'frontend/class-dashboard-front.php',
		'SimpleVPBot_Rest_Dashboard'    => 'api/class-rest-dashboard.php',
		'SimpleVPBot_Dashboard_Admin_Mutations' => 'admin/class-dashboard-admin-mutations.php',
		'SimpleVPBot_Dashboard_Mutate_Policy'   => 'admin/class-dashboard-mutate-policy.php',
		'SimpleVPBot_Jalali_Date'        => 'helpers/class-jalali-date.php',
		'SimpleVPBot_Backup_Export'      => 'helpers/class-backup-export.php',
		'SimpleVPBot_Backup_Restore'   => 'helpers/class-backup-restore.php',
		'SimpleVPBot_Inbound_Linker'     => 'helpers/class-inbound-linker.php',
		'SimpleVPBot_User_Membership'   => 'helpers/class-user-membership.php',
		'SimpleVPBot_User_Activity_Log' => 'helpers/class-user-activity-log.php',
		'SimpleVPBot_Service_Transfer'   => 'helpers/class-service-transfer.php',
		'SimpleVPBot_Service_Renew'      => 'helpers/class-service-renew.php',
		'SimpleVPBot_Service_Alerts'     => 'helpers/class-service-alerts.php',
		'SimpleVPBot_Shortcode_Portal'   => 'frontend/class-shortcode-portal.php',
		'SimpleVPBot_Portal_Front'        => 'frontend/class-portal-front.php',
		'SimpleVPBot_Cron_Manager'       => 'cron/class-cron-manager.php',
		'SimpleVPBot_Cron_Backup'        => 'cron/class-cron-backup.php',
		'SimpleVPBot_Cron_Expiry'        => 'cron/class-cron-expiry.php',
		'SimpleVPBot_Cron_Autorenew'     => 'cron/class-cron-autorenew.php',
		'SimpleVPBot_Cron_Broadcast'     => 'cron/class-cron-broadcast.php',
		'SimpleVPBot_Cron_Users_Bulk'    => 'cron/class-cron-users-bulk.php',
		'SimpleVPBot_Cron_Panel_Online'   => 'cron/class-cron-panel-online.php',
		'SimpleVPBot_Cron_Panel_Service_Sync' => 'cron/class-cron-panel-service-sync.php',
		'SimpleVPBot_Cron_Inbound_Clients_Cache' => 'cron/class-cron-inbound-clients-cache.php',
		'SimpleVPBot_Cron_Idle_Offers'    => 'cron/class-cron-idle-offers.php',
		'SimpleVPBot_Cron_Admin_Alerts' => 'cron/class-cron-admin-alerts.php',
		'SimpleVPBot_Admin_Menu'         => 'admin/class-admin-menu.php',
		'SimpleVPBot_Admin_Actions'      => 'admin/class-admin-actions.php',
		'SimpleVPBot_Admin_Ajax'         => 'admin/class-admin-ajax.php',
	);

	/**
	 * Get singleton.
	 *
	 * @return SimpleVPBot_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
		register_activation_hook( SIMPLEVPBOT_PLUGIN_FILE, array( 'SimpleVPBot_Activator', 'activate' ) );
		register_deactivation_hook( SIMPLEVPBOT_PLUGIN_FILE, array( 'SimpleVPBot_Deactivator', 'deactivate' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ), 5 );
	}

	/**
	 * Autoload classes.
	 *
	 * @param string $class Class name.
	 */
	public static function autoload( $class ) {
		if ( isset( self::$class_map[ $class ] ) ) {
			$file = SIMPLEVPBOT_PLUGIN_DIR . 'includes/' . self::$class_map[ $class ];
			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}
	}

	/**
	 * Init hooks.
	 */
	public function init() {
		load_plugin_textdomain( 'simplevpbot', false, dirname( SIMPLEVPBOT_PLUGIN_BASENAME ) . '/languages' );

		SimpleVPBot_Activator::maybe_migrate();

		SimpleVPBot_Portal_Front::init();
		SimpleVPBot_Dashboard_Front::init();
		SimpleVPBot_Rest_Dashboard::init();

		SimpleVPBot_Settings::ensure_secrets();
		SimpleVPBot_Settings::init();
		SimpleVPBot_Webhook::init();
		SimpleVPBot_Webhook_Diagnostics::init();
		SimpleVPBot_Crypto_Payment::init();
		SimpleVPBot_Cron_Manager::init();

		SimpleVPBot_Admin_Ajax::init_portal_routes();

		if ( is_admin() ) {
			SimpleVPBot_Admin_Menu::init();
			SimpleVPBot_Admin_Ajax::init();
		}
	}
}
