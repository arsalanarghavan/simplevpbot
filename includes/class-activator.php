<?php
/**
 * Activation: tables + schedules + secrets.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Activator
 */
class SimpleVPBot_Activator {

	const DB_VERSION = '2.3.7';

	/**
	 * Activate plugin.
	 */
	public static function activate() {
		add_filter( 'cron_schedules', array( 'SimpleVPBot_Settings', 'add_cron_schedules' ), 20 );
		self::create_tables();
		self::seed_plan_categories_if_empty();
		self::seed_texts();
		update_option( 'simplevpbot_db_version', self::DB_VERSION );
		SimpleVPBot_Settings::ensure_secrets();
		if ( class_exists( 'SimpleVPBot_Cron_Manager' ) ) {
			SimpleVPBot_Cron_Manager::schedule_all();
		} else {
			require_once SIMPLEVPBOT_PLUGIN_DIR . 'includes/cron/class-cron-manager.php';
			SimpleVPBot_Cron_Manager::schedule_all();
		}
		flush_rewrite_rules();
	}

	/**
	 * Create DB tables.
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $wpdb->get_charset_collate();
		$p               = $wpdb->prefix;

		$sql_users = "CREATE TABLE {$p}svp_users (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			tg_user_id bigint(20) DEFAULT NULL,
			bale_user_id bigint(20) DEFAULT NULL,
			first_name varchar(191) DEFAULT '',
			last_name varchar(191) DEFAULT '',
			username varchar(191) DEFAULT '',
			phone varchar(32) DEFAULT '',
			role varchar(20) NOT NULL DEFAULT 'user',
			balance decimal(15,2) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'pending',
			approved_by varchar(64) DEFAULT NULL,
			approved_at datetime DEFAULT NULL,
			admin_mode tinyint(1) NOT NULL DEFAULT 0,
			state varchar(64) DEFAULT NULL,
			state_data longtext NULL,
			bot_locale varchar(5) NOT NULL DEFAULT '',
			invited_by bigint(20) unsigned DEFAULT NULL,
			signup_reseller_svp_id bigint(20) unsigned DEFAULT NULL,
			wp_user_id bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY svp_users_tg (tg_user_id),
			UNIQUE KEY svp_users_bale (bale_user_id),
			UNIQUE KEY svp_users_wp (wp_user_id),
			KEY status (status),
			KEY role (role),
			KEY invited_by (invited_by),
			KEY signup_reseller (signup_reseller_svp_id)
		) $charset_collate;";

		$sql_cards = "CREATE TABLE {$p}svp_cards (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			owner_svp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			card_number varchar(32) NOT NULL,
			holder_name varchar(191) NOT NULL,
			bank_name varchar(64) NOT NULL DEFAULT '',
			method_key varchar(16) NOT NULL DEFAULT 'c2c',
			daily_limit decimal(15,2) NOT NULL DEFAULT 0,
			priority int NOT NULL DEFAULT 0,
			note text NULL,
			active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY active (active),
			KEY owner_svp_user (owner_svp_user_id)
		) $charset_collate;";

		$sql_tx = "CREATE TABLE {$p}svp_transactions (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			service_id bigint(20) unsigned DEFAULT NULL,
			amount decimal(15,2) NOT NULL,
			type varchar(32) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			meta_json longtext NULL,
			referral_amount decimal(15,2) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY status (status),
			KEY type (type)
		) $charset_collate;";

		$sql_receipts = "CREATE TABLE {$p}svp_receipts (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			transaction_id bigint(20) unsigned NOT NULL,
			tg_file_id varchar(191) DEFAULT '',
			bale_file_id varchar(191) DEFAULT '',
			stored_image_path varchar(512) NOT NULL DEFAULT '',
			amount decimal(15,2) NOT NULL,
			card_id bigint(20) unsigned DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			admin_messages_json longtext NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			decided_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY transaction_id (transaction_id),
			KEY status (status)
		) $charset_collate;";

		$sql_pending = "CREATE TABLE {$p}svp_pending_approvals (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			bot varchar(8) NOT NULL,
			admin_messages_json longtext NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			decided_at datetime DEFAULT NULL,
			decided_by varchar(64) DEFAULT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY status (status)
		) $charset_collate;";

		$sql_sync = "CREATE TABLE {$p}svp_sync_codes (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			code varchar(16) NOT NULL,
			generated_bot varchar(8) NOT NULL,
			consumed tinyint(1) NOT NULL DEFAULT 0,
			expires_at datetime NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY code (code),
			KEY user_id (user_id)
		) $charset_collate;";

		$sql_texts = "CREATE TABLE {$p}svp_texts (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			key_name varchar(191) NOT NULL,
			category varchar(64) NOT NULL DEFAULT 'general',
			locale varchar(5) NOT NULL DEFAULT 'fa',
			value longtext NOT NULL,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY svp_texts_key_locale (key_name, locale),
			KEY category (category)
		) $charset_collate;";

		$sql_broadcasts = "CREATE TABLE {$p}svp_broadcasts (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			owner_svp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			type varchar(16) NOT NULL,
			content longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'draft',
			sent_count int NOT NULL DEFAULT 0,
			failed_count int NOT NULL DEFAULT 0,
			total_targets int NOT NULL DEFAULT 0,
			blocked_count int NOT NULL DEFAULT 0,
			meta_json longtext NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY owner_svp_user (owner_svp_user_id)
		) $charset_collate;";

		$sql_queue = "CREATE TABLE {$p}svp_broadcast_queue (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			broadcast_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			bot varchar(8) NOT NULL,
			chat_id bigint(20) NOT NULL,
			payload_json longtext NOT NULL,
			status varchar(16) NOT NULL DEFAULT 'pending',
			tries int NOT NULL DEFAULT 0,
			last_error text NULL,
			failure_kind varchar(32) NULL,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY broadcast_id (broadcast_id),
			KEY status (status)
		) $charset_collate;";
		$sql_users_bulk_jobs = "CREATE TABLE {$p}svp_users_bulk_jobs (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			operation varchar(32) NOT NULL,
			scope varchar(32) NOT NULL DEFAULT 'all_approved',
			payload_json longtext NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			created_by_wp bigint(20) unsigned NOT NULL DEFAULT 0,
			created_by_svp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			finished_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY status (status),
			KEY created_by_svp (created_by_svp_user_id)
		) $charset_collate;";
		$sql_users_bulk_items = "CREATE TABLE {$p}svp_users_bulk_job_items (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			job_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			panel_id bigint(20) unsigned NOT NULL DEFAULT 0,
			inbound_id bigint(20) unsigned NOT NULL DEFAULT 0,
			client_email varchar(191) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'pending',
			tries int NOT NULL DEFAULT 0,
			last_error text NULL,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY job_id (job_id),
			KEY status (status),
			KEY job_user (job_id, user_id),
			KEY job_panel_client (job_id, panel_id, inbound_id, client_email(120))
		) $charset_collate;";

		$sql_logs = "CREATE TABLE {$p}svp_logs (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			level varchar(16) NOT NULL DEFAULT 'info',
			message text NOT NULL,
			context_json longtext NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY level (level),
			KEY created_at (created_at)
		) $charset_collate;";

		dbDelta( $sql_users );
		dbDelta( self::sql_plans_table( $p, $charset_collate ) );
		dbDelta( self::sql_plan_categories( $p, $charset_collate ) );
		dbDelta( self::sql_svp_services( $p, $charset_collate ) );
		dbDelta( self::sql_l2tp_servers( $p, $charset_collate ) );
		dbDelta( $sql_cards );
		dbDelta( $sql_tx );
		dbDelta( self::sql_referral_events( $p, $charset_collate ) );
		dbDelta( $sql_receipts );
		dbDelta( $sql_pending );
		dbDelta( $sql_sync );
		dbDelta( $sql_texts );
		dbDelta( $sql_broadcasts );
		dbDelta( $sql_queue );
		dbDelta( $sql_logs );
		dbDelta( $sql_users_bulk_jobs );
		dbDelta( $sql_users_bulk_items );
		dbDelta( self::sql_user_activity( $p, $charset_collate ) );
		dbDelta( self::sql_svp_service_ip_log( $p, $charset_collate ) );
		dbDelta( self::sql_panels_table( $p, $charset_collate ) );
		dbDelta( self::sql_panel_online_daily( $p, $charset_collate ) );
		dbDelta( self::sql_monitor_hosts( $p, $charset_collate ) );
		dbDelta( self::sql_discount_codes( $p, $charset_collate ) );
		dbDelta( self::sql_discount_redemptions( $p, $charset_collate ) );
		dbDelta( self::sql_panel_inbound_clients( $p, $charset_collate ) );
		dbDelta( self::sql_panel_inbound_api( $p, $charset_collate ) );
		dbDelta( self::sql_reseller_panel_prices( $p, $charset_collate ) );
		dbDelta( self::sql_reseller_parent_panel_floors( $p, $charset_collate ) );
		dbDelta( self::sql_reseller_bot_profiles( $p, $charset_collate ) );
		dbDelta( self::sql_reseller_inbound_display_names( $p, $charset_collate ) );
		dbDelta( self::sql_reseller_closure( $p, $charset_collate ) );
		dbDelta( self::sql_audit_log( $p, $charset_collate ) );
		if ( class_exists( 'SimpleVPBot_Service_Transfer' ) ) {
			SimpleVPBot_Service_Transfer::ensure_table();
		}
	}

	/**
	 * Discount codes table DDL.
	 *
	 * @param string $p Prefix.
	 * @param string $charset_collate Collation.
	 * @return string
	 */
	public static function sql_discount_codes( $p, $charset_collate ) {
		return "CREATE TABLE {$p}svp_discount_codes (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			owner_svp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			code varchar(64) NOT NULL,
			active tinyint(1) NOT NULL DEFAULT 1,
			discount_type varchar(16) NOT NULL DEFAULT 'percent',
			discount_value decimal(15,2) NOT NULL DEFAULT 0,
			max_uses int DEFAULT NULL,
			uses_count int NOT NULL DEFAULT 0,
			valid_from datetime DEFAULT NULL,
			valid_until datetime DEFAULT NULL,
			min_order_toman decimal(15,2) DEFAULT NULL,
			allow_new_purchase tinyint(1) NOT NULL DEFAULT 1,
			allow_renew_same tinyint(1) NOT NULL DEFAULT 1,
			allow_add_volume tinyint(1) NOT NULL DEFAULT 1,
			allow_add_user_slots tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY owner_code (owner_svp_user_id, code),
			KEY active (active),
			KEY owner_svp_user (owner_svp_user_id)
		) $charset_collate;";
	}

	/**
	 * Referral /start deep-link visits (ref_*).
	 *
	 * @param string $p Prefix.
	 * @param string $charset_collate Collation.
	 * @return string
	 */
	public static function sql_referral_events( $p, $charset_collate ) {
		return "CREATE TABLE {$p}svp_referral_events (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			inviter_svp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			platform varchar(16) NOT NULL DEFAULT '',
			visitor_chat_id bigint(20) NOT NULL DEFAULT 0,
			visitor_platform_user_id bigint(20) NOT NULL DEFAULT 0,
			start_payload varchar(128) NOT NULL DEFAULT '',
			outcome varchar(32) NOT NULL DEFAULT '',
			resulting_svp_user_id bigint(20) unsigned DEFAULT NULL,
			PRIMARY KEY (id),
			KEY inviter_created (inviter_svp_user_id, created_at),
			KEY created_at (created_at)
		) $charset_collate;";
	}

	/**
	 * User activity log (admin REST + bot).
	 *
	 * @param string $p Prefix.
	 * @param string $charset_collate Collation.
	 * @return string
	 */
	public static function sql_user_activity( $p, $charset_collate ) {
		return "CREATE TABLE {$p}svp_user_activity (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			subject_svp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			channel varchar(16) NOT NULL DEFAULT 'rest',
			actor_kind varchar(20) NOT NULL DEFAULT 'system',
			actor_wp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			actor_svp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			platform_chat_id bigint(20) NOT NULL DEFAULT 0,
			event_type varchar(64) NOT NULL DEFAULT '',
			payload_json longtext NULL,
			PRIMARY KEY (id),
			KEY subject_id (subject_svp_user_id, id),
			KEY channel (channel),
			KEY created_at (created_at)
		) $charset_collate;";
	}

	/**
	 * Per-service IP log (from panel clientIps sync).
	 *
	 * @param string $p Prefix.
	 * @param string $charset_collate Collation.
	 * @return string
	 */
	public static function sql_svp_service_ip_log( $p, $charset_collate ) {
		return "CREATE TABLE {$p}svp_service_ip_log (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			service_id bigint(20) unsigned NOT NULL,
			ip varchar(64) NOT NULL DEFAULT '',
			first_seen_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			last_seen_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			hit_count int unsigned NOT NULL DEFAULT 1,
			PRIMARY KEY (id),
			UNIQUE KEY svc_ip (service_id, ip),
			KEY service_seen (service_id, last_seen_at)
		) $charset_collate;";
	}

	/**
	 * svp_services table DDL.
	 *
	 * @param string $p Prefix.
	 * @param string $charset_collate Collation.
	 * @return string
	 */
	private static function sql_svp_services( $p, $charset_collate ) {
		return "CREATE TABLE {$p}svp_services (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			panel_id bigint(20) unsigned NOT NULL DEFAULT 1,
			inbound_id int NOT NULL,
			xui_client_id varchar(191) DEFAULT NULL,
			xui_client_uuid varchar(64) DEFAULT NULL,
			email varchar(191) NOT NULL,
			remark varchar(255) DEFAULT '',
			display_label varchar(255) NOT NULL DEFAULT '',
			service_note varchar(512) DEFAULT '',
			plan_id bigint(20) unsigned DEFAULT NULL,
			expires_at datetime DEFAULT NULL,
			total_traffic bigint NOT NULL DEFAULT 0,
			used_traffic bigint NOT NULL DEFAULT 0,
			autorenew tinyint(1) NOT NULL DEFAULT 0,
			alerts_enabled tinyint(1) NOT NULL DEFAULT 1,
			alerts_volume tinyint(1) NOT NULL DEFAULT 1,
			alerts_expiry tinyint(1) NOT NULL DEFAULT 1,
			alerts_users tinyint(1) NOT NULL DEFAULT 1,
			alert_low_pct smallint DEFAULT NULL,
			alert_expiry_days varchar(64) DEFAULT NULL,
			alert_ip_fill_pct smallint DEFAULT NULL,
			alert_schedule_json longtext NULL,
			last_warn_sent_at datetime DEFAULT NULL,
			panel_limit_ip int unsigned DEFAULT NULL,
			panel_client_enabled tinyint(1) DEFAULT NULL,
			sub_id varchar(128) DEFAULT NULL,
			provision_type varchar(20) NOT NULL DEFAULT 'plan',
			service_type varchar(16) NOT NULL DEFAULT 'xray',
			l2tp_server_id bigint(20) unsigned DEFAULT NULL,
			l2tp_username varchar(64) DEFAULT NULL,
			l2tp_password_enc text DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			deleted_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY email (email),
			KEY inbound_id (inbound_id),
			KEY service_type (service_type),
			KEY l2tp_username (l2tp_username),
			KEY deleted_at (deleted_at)
		) $charset_collate;";
	}

	/**
	 * svp_l2tp_servers table DDL.
	 *
	 * @param string $p Prefix.
	 * @param string $charset_collate Collation.
	 * @return string
	 */
	private static function sql_l2tp_servers( $p, $charset_collate ) {
		return "CREATE TABLE {$p}svp_l2tp_servers (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			label varchar(128) NOT NULL DEFAULT '',
			ssh_host varchar(191) NOT NULL DEFAULT '',
			ssh_port int NOT NULL DEFAULT 22,
			ssh_user varchar(64) NOT NULL DEFAULT '',
			ssh_auth varchar(16) NOT NULL DEFAULT 'key',
			ssh_password_enc text NULL,
			ssh_private_key_enc longtext NULL,
			ssh_key_passphrase_enc text NULL,
			l2tp_host varchar(191) NOT NULL DEFAULT '',
			l2tp_psk_enc text NULL,
			chap_path varchar(255) NOT NULL DEFAULT '/etc/ppp/chap-secrets',
			reload_cmd varchar(255) NOT NULL DEFAULT 'sudo /bin/systemctl reload xl2tpd',
			usage_cmd_template text NULL,
			apps_note text NULL,
			active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY active (active)
		) $charset_collate;";
	}

	/**
	 * svp_plans table DDL.
	 *
	 * @param string $p Prefix.
	 * @param string $charset_collate Collation.
	 * @return string
	 */
	private static function sql_plans_table( $p, $charset_collate ) {
		return "CREATE TABLE {$p}svp_plans (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL,
			category varchar(32) NOT NULL DEFAULT 'normal',
			duration_days int NOT NULL DEFAULT 30,
			traffic_gb bigint NOT NULL DEFAULT 0,
			price decimal(15,2) NOT NULL DEFAULT 0,
			pricing_type varchar(20) NOT NULL DEFAULT 'fixed',
			price_per_gb decimal(15,2) NOT NULL DEFAULT 0,
			traffic_gb_min int NOT NULL DEFAULT 0,
			traffic_gb_max int NOT NULL DEFAULT 0,
			clients_count int NOT NULL DEFAULT 1,
			inbound_id int NOT NULL DEFAULT 0,
			panel_id bigint(20) unsigned NOT NULL DEFAULT 1,
			wholesale_line_id bigint(20) unsigned DEFAULT NULL,
			service_type varchar(16) NOT NULL DEFAULT 'xray',
			l2tp_server_id bigint(20) unsigned DEFAULT NULL,
			active tinyint(1) NOT NULL DEFAULT 1,
			sort_order int NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY category (category),
			KEY active (active),
			KEY service_type (service_type),
			KEY panel_id (panel_id),
			KEY wholesale_line_id (wholesale_line_id)
		) $charset_collate;";
	}

	/**
	 * SQL for plan purchase categories table.
	 *
	 * @param string $p Prefix with underscore trail? Actually prefix is wp_ .
	 * @param string $charset_collate Charset.
	 * @return string
	 */
	private static function sql_plan_categories( $p, $charset_collate ) {
		return "CREATE TABLE {$p}svp_plan_categories (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			panel_id bigint(20) unsigned NOT NULL DEFAULT 1,
			slug varchar(32) NOT NULL,
			label varchar(191) NOT NULL,
			sort_order int NOT NULL DEFAULT 0,
			active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY panel_slug (panel_id, slug),
			KEY active (active),
			KEY panel_id (panel_id)
		) $charset_collate;";
	}

	/**
	 * 3x-ui panel credentials (multiple instances).
	 *
	 * @param string $p Prefix.
	 * @param string $charset_collate Charset.
	 * @return string
	 */
	private static function sql_panels_table( $p, $charset_collate ) {
		return "CREATE TABLE {$p}svp_panels (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			label varchar(191) NOT NULL DEFAULT '',
			panel_url text NOT NULL,
			panel_username varchar(191) NOT NULL DEFAULT '',
			panel_password text NOT NULL,
			panel_api_base varchar(191) NOT NULL DEFAULT 'panel/api',
			panel_login_secret varchar(255) NOT NULL DEFAULT '',
			panel_api_token text NULL,
			subscription_public_base text NULL,
			sort_order int NOT NULL DEFAULT 0,
			active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY active (active)
		) $charset_collate;";
	}

	/**
	 * Daily max online clients per panel (filled by cron).
	 *
	 * @param string $p Prefix.
	 * @param string $charset_collate Charset.
	 * @return string
	 */
	private static function sql_panel_online_daily( $p, $charset_collate ) {
		return "CREATE TABLE {$p}svp_panel_online_daily (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			panel_id bigint(20) unsigned NOT NULL,
			stat_date date NOT NULL,
			max_online int NOT NULL DEFAULT 0,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY panel_stat (panel_id, stat_date),
			KEY stat_date (stat_date)
		) $charset_collate;";
	}

	/**
	 * External JSON metrics URLs (optional monitoring targets).
	 *
	 * @param string $p Prefix.
	 * @param string $charset_collate Charset.
	 * @return string
	 */
	private static function sql_monitor_hosts( $p, $charset_collate ) {
		return "CREATE TABLE {$p}svp_monitor_hosts (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			label varchar(191) NOT NULL DEFAULT '',
			metrics_url text NOT NULL,
			bearer_token varchar(512) NOT NULL DEFAULT '',
			sort_order int NOT NULL DEFAULT 0,
			active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY active (active)
		) $charset_collate;";
	}

	/**
	 * Seed default categories when table is empty.
	 */
	public static function seed_plan_categories_if_empty() {
		if ( class_exists( 'SimpleVPBot_Model_Plan_Category' ) ) {
			SimpleVPBot_Model_Plan_Category::seed_if_empty();
			return;
		}
		$file = SIMPLEVPBOT_PLUGIN_DIR . 'includes/models/class-model-plan-category.php';
		if ( is_readable( $file ) ) {
			require_once $file;
			SimpleVPBot_Model_Plan_Category::seed_if_empty();
		}
	}

	/**
	 * DB upgrade for existing installs (new tables / columns).
	 */
	public static function maybe_migrate() {
		$current = get_option( 'simplevpbot_db_version', '0' );
		if ( version_compare( (string) $current, self::DB_VERSION, '>=' ) ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $wpdb->get_charset_collate();
		$p               = $wpdb->prefix;
		dbDelta( self::sql_plans_table( $p, $charset_collate ) );
		dbDelta( self::sql_plan_categories( $p, $charset_collate ) );
		dbDelta( self::sql_svp_services( $p, $charset_collate ) );
		dbDelta( self::sql_l2tp_servers( $p, $charset_collate ) );
		dbDelta( self::sql_panels_table( $p, $charset_collate ) );
		$cards_table = $p . 'svp_cards';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$col_bank = $wpdb->get_var( "SHOW COLUMNS FROM {$cards_table} LIKE 'bank_name'" );
		if ( ! $col_bank ) {
			$wpdb->query( "ALTER TABLE {$cards_table} ADD COLUMN bank_name varchar(64) NOT NULL DEFAULT '' AFTER holder_name" ); // phpcs:ignore
			$wpdb->query( "ALTER TABLE {$cards_table} ADD COLUMN method_key varchar(16) NOT NULL DEFAULT 'c2c' AFTER bank_name" ); // phpcs:ignore
		}

		$plans_table = $p . 'svp_plans';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$col_stype = $wpdb->get_var( "SHOW COLUMNS FROM {$plans_table} LIKE 'service_type'" );
		if ( ! $col_stype ) {
			$wpdb->query( "ALTER TABLE {$plans_table} ADD COLUMN service_type varchar(16) NOT NULL DEFAULT 'xray' AFTER inbound_id" ); // phpcs:ignore
			$wpdb->query( "ALTER TABLE {$plans_table} ADD COLUMN l2tp_server_id bigint(20) unsigned DEFAULT NULL AFTER service_type" ); // phpcs:ignore
		}

		$svcs_table = $p . 'svp_services';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$col_svctype = $wpdb->get_var( "SHOW COLUMNS FROM {$svcs_table} LIKE 'service_type'" );
		if ( ! $col_svctype ) {
			$wpdb->query( "ALTER TABLE {$svcs_table} ADD COLUMN service_type varchar(16) NOT NULL DEFAULT 'xray' AFTER provision_type" ); // phpcs:ignore
			$wpdb->query( "ALTER TABLE {$svcs_table} ADD COLUMN l2tp_server_id bigint(20) unsigned DEFAULT NULL AFTER service_type" ); // phpcs:ignore
			$wpdb->query( "ALTER TABLE {$svcs_table} ADD COLUMN l2tp_username varchar(64) DEFAULT NULL AFTER l2tp_server_id" ); // phpcs:ignore
			$wpdb->query( "ALTER TABLE {$svcs_table} ADD COLUMN l2tp_password_enc text DEFAULT NULL AFTER l2tp_username" ); // phpcs:ignore
		}

		self::seed_plan_categories_if_empty();
		self::migrate_subscription_panel_text();
		// Inserts missing fa/en rows from Bot_Text_Defaults (+ Extended); does not overwrite customized DB values.
		self::seed_missing_text_keys();
		if ( version_compare( (string) $current, '1.0.8', '<' ) ) {
			self::dedupe_users_by_bot_ids();
			self::maybe_add_user_unique_indexes();
		}
		if ( version_compare( (string) $current, '1.0.9', '<' ) ) {
			self::maybe_add_service_alert_columns( $p );
		}
		if ( version_compare( (string) $current, '1.1.0', '<' ) ) {
			self::maybe_migrate_multi_panel_110( $p );
		}
		if ( version_compare( (string) $current, '1.2.0', '<' ) ) {
			self::maybe_migrate_120( $p, $charset_collate );
		}
		if ( version_compare( (string) $current, '1.2.1', '<' ) ) {
			dbDelta( self::sql_panel_online_daily( $p, $charset_collate ) );
		}
		if ( version_compare( (string) $current, '1.3.0', '<' ) ) {
			self::maybe_add_wp_user_id_column( $p );
		}
		if ( version_compare( (string) $current, '1.4.0', '<' ) ) {
			self::maybe_migrate_broadcast_140( $p, $charset_collate );
		}
		if ( version_compare( (string) $current, '1.5.0', '<' ) ) {
			dbDelta( self::sql_user_activity( $p, $charset_collate ) );
		}
		if ( version_compare( (string) $current, '1.6.0', '<' ) ) {
			self::maybe_migrate_160( $p, $charset_collate );
		}
		if ( version_compare( (string) $current, '1.7.0', '<' ) ) {
			self::maybe_add_service_alert_schedule_json( $p );
		}
		if ( version_compare( (string) $current, '1.8.0', '<' ) ) {
			dbDelta( self::sql_monitor_hosts( $p, $charset_collate ) );
		}
		if ( version_compare( (string) $current, '1.9.0', '<' ) ) {
			self::maybe_add_service_deleted_at( $p );
		}
		if ( version_compare( (string) $current, '2.0.0', '<' ) ) {
			self::maybe_migrate_200( $p, $charset_collate );
		}
		if ( version_compare( (string) $current, '2.0.1', '<' ) ) {
			self::maybe_migrate_201( $p, $charset_collate );
		}
		if ( version_compare( (string) $current, '2.0.2', '<' ) ) {
			self::maybe_migrate_202( $p, $charset_collate );
		}
		if ( version_compare( (string) $current, '2.0.3', '<' ) ) {
			self::maybe_migrate_203( $p, $charset_collate );
		}
		if ( version_compare( (string) $current, '2.0.4', '<' ) ) {
			self::maybe_migrate_204( $p, $charset_collate );
		}
		if ( version_compare( (string) $current, '2.0.5', '<' ) ) {
			self::maybe_migrate_205( $p, $charset_collate );
		}
		if ( version_compare( (string) $current, '2.0.6', '<' ) ) {
			self::maybe_migrate_206( $p );
		}
		if ( version_compare( (string) $current, '2.0.7', '<' ) ) {
			self::maybe_migrate_207( $p, $charset_collate );
		}
		if ( version_compare( (string) $current, '2.0.8', '<' ) ) {
			self::maybe_migrate_208( $p );
		}
		if ( version_compare( (string) $current, '2.1.0', '<' ) ) {
			self::maybe_migrate_210( $p, $charset_collate );
		}
		if ( version_compare( (string) $current, '2.1.1', '<' ) ) {
			self::maybe_migrate_211( $p );
		}
		if ( version_compare( (string) $current, '2.2.0', '<' ) ) {
			self::maybe_migrate_220_wholesale_lines( $p, $charset_collate );
		}
		if ( version_compare( (string) $current, '2.2.1', '<' ) ) {
			self::maybe_migrate_221_panel_api_token( $p );
		}
		if ( version_compare( (string) $current, '2.2.2', '<' ) ) {
			self::maybe_migrate_222_discount_enhancements( $p, $charset_collate );
		}
		if ( version_compare( (string) $current, '2.2.3', '<' ) ) {
			self::maybe_migrate_223_reseller_bot_enhancements( $p );
		}
		if ( version_compare( (string) $current, '2.2.4', '<' ) ) {
			self::maybe_migrate_224_reseller_signup_backfill( $p );
		}
		if ( version_compare( (string) $current, '2.3.0', '<' ) ) {
			self::maybe_migrate_230_branding_closure_audit( $p, $charset_collate );
		}
		if ( version_compare( (string) $current, '2.3.1', '<' ) ) {
			self::maybe_migrate_231_bulk_panel_items( $p, $charset_collate );
		}
		if ( version_compare( (string) $current, '2.3.2', '<' ) ) {
			self::maybe_migrate_232_receipt_stored_image( $p );
		}
		if ( version_compare( (string) $current, '2.3.3', '<' ) ) {
			self::maybe_migrate_233_service_note( $p );
		}
		if ( version_compare( (string) $current, '2.3.4', '<' ) ) {
			self::maybe_migrate_234_config_label_override( $p );
		}
		if ( version_compare( (string) $current, '2.3.5', '<' ) ) {
			self::maybe_migrate_235_config_label_prefix( $p );
		}
		if ( version_compare( (string) $current, '2.3.6', '<' ) ) {
			self::maybe_migrate_236_reseller_inbound_display_names( $p );
		}
		if ( version_compare( (string) $current, '2.3.7', '<' ) ) {
			self::maybe_migrate_237_service_display_label( $p );
		}
		update_option( 'simplevpbot_db_version', self::DB_VERSION );
	}

	/**
	 * Receipts: permanent local image path for dashboard.
	 *
	 * @param string $p Table prefix.
	 */
	public static function maybe_migrate_232_receipt_stored_image( $p ) {
		global $wpdb;
		$t = $p . 'svp_receipts';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$has = $wpdb->get_var( "SHOW COLUMNS FROM {$t} LIKE 'stored_image_path'" );
		if ( ! $has ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$t} ADD COLUMN stored_image_path varchar(512) NOT NULL DEFAULT '' AFTER bale_file_id" );
		}
	}

	/**
	 * Services: optional auto-note / panel note storage (platform_slug naming).
	 *
	 * @param string $p Table prefix.
	 */
	public static function maybe_migrate_233_service_note( $p ) {
		global $wpdb;
		$t = $p . 'svp_services';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$has = $wpdb->get_var( "SHOW COLUMNS FROM {$t} LIKE 'service_note'" );
		if ( ! $has ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$t} ADD COLUMN service_note varchar(512) NOT NULL DEFAULT '' AFTER remark" );
		}
	}

	/**
	 * Reseller bot profiles: optional config line label override for end users.
	 *
	 * @param string $p Table prefix.
	 */
	public static function maybe_migrate_234_config_label_override( $p ) {
		global $wpdb;
		$t = $p . 'svp_reseller_bot_profiles';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$has = $wpdb->get_var( "SHOW COLUMNS FROM {$t} LIKE 'config_label_override'" );
		if ( ! $has ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$t} ADD COLUMN config_label_override varchar(255) NOT NULL DEFAULT '' AFTER custom_domain" );
		}
	}

	/**
	 * Reseller bot profiles: prefix for prefix_numbered config labels.
	 *
	 * @param string $p Table prefix.
	 */
	public static function maybe_migrate_235_config_label_prefix( $p ) {
		global $wpdb;
		$t = $p . 'svp_reseller_bot_profiles';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$has = $wpdb->get_var( "SHOW COLUMNS FROM {$t} LIKE 'config_label_prefix'" );
		if ( ! $has ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$t} ADD COLUMN config_label_prefix varchar(255) NOT NULL DEFAULT '' AFTER config_label_override" );
		}
	}

	/**
	 * Per-reseller inbound display name aliases.
	 *
	 * @param string $p Table prefix.
	 */
	public static function maybe_migrate_236_reseller_inbound_display_names( $p ) {
		global $wpdb;
		$t = $p . 'svp_reseller_inbound_display_names';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) );
		if ( $exists === $t ) {
			return;
		}
		$charset = $wpdb->get_charset_collate();
		dbDelta( self::sql_reseller_inbound_display_names( $p, $charset ) );
	}

	/**
	 * Services: bot-only display name (does not change canonical remark / panel).
	 *
	 * @param string $p Table prefix.
	 */
	public static function maybe_migrate_237_service_display_label( $p ) {
		global $wpdb;
		$t = $p . 'svp_services';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$has = $wpdb->get_var( "SHOW COLUMNS FROM {$t} LIKE 'display_label'" );
		if ( ! $has ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$t} ADD COLUMN display_label varchar(255) NOT NULL DEFAULT '' AFTER remark" );
		}
	}

	/**
	 * Per-reseller inbound display name aliases.
	 *
	 * @param string $p               Prefix.
	 * @param string $charset_collate Collation.
	 * @return string
	 */
	public static function sql_reseller_inbound_display_names( $p, $charset_collate ) {
		return "CREATE TABLE {$p}svp_reseller_inbound_display_names (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			reseller_svp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			panel_id bigint(20) unsigned NOT NULL DEFAULT 0,
			inbound_id int(11) NOT NULL DEFAULT 0,
			label varchar(255) NOT NULL DEFAULT '',
			PRIMARY KEY (id),
			UNIQUE KEY reseller_panel_inbound (reseller_svp_user_id, panel_id, inbound_id),
			KEY panel_inbound (panel_id, inbound_id)
		) {$charset_collate};";
	}

	/**
	 * Bot texts: locale column (fa/en), user bot_locale; seed English rows.
	 *
	 * @param string $p Table prefix.
	 */
	public static function maybe_migrate_206( $p ) {
		global $wpdb;
		$t     = $p . 'svp_texts';
		$users = $p . 'svp_users';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$has_locale = $wpdb->get_var( "SHOW COLUMNS FROM {$t} LIKE 'locale'" );
		if ( ! $has_locale ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$t} DROP INDEX key_name" );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$t} ADD COLUMN locale varchar(5) NOT NULL DEFAULT 'fa' AFTER category" );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$t} ADD UNIQUE KEY svp_texts_key_locale (key_name, locale)" );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$has_bot_loc = $wpdb->get_var( "SHOW COLUMNS FROM {$users} LIKE 'bot_locale'" );
		if ( ! $has_bot_loc ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$users} ADD COLUMN bot_locale varchar(5) NOT NULL DEFAULT '' AFTER state_data" );
		}
		self::insert_default_text_rows_if_missing();
	}

	/**
	 * Per-reseller ownership for cards/discounts/broadcasts; panel_access on reseller panel prices.
	 *
	 * @param string $p Prefix.
	 * @param string $charset_collate Collation.
	 */
	public static function maybe_migrate_207( $p, $charset_collate ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		global $wpdb;
		dbDelta( self::sql_reseller_panel_prices( $p, $charset_collate ) );

		$cards = $p . 'svp_cards';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$cards} LIKE 'owner_svp_user_id'" ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$cards} ADD COLUMN owner_svp_user_id bigint(20) unsigned NOT NULL DEFAULT 0 AFTER id, ADD KEY owner_svp_user (owner_svp_user_id)" );
		}

		$disc = $p . 'svp_discount_codes';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$disc} LIKE 'owner_svp_user_id'" ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$disc} ADD COLUMN owner_svp_user_id bigint(20) unsigned NOT NULL DEFAULT 0 AFTER id" );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$idx_rows = $wpdb->get_results( "SHOW INDEX FROM {$disc}", ARRAY_A );
		$key_names = array();
		if ( is_array( $idx_rows ) ) {
			foreach ( $idx_rows as $ir ) {
				if ( is_array( $ir ) && ! empty( $ir['Key_name'] ) ) {
					$key_names[ (string) $ir['Key_name'] ] = true;
				}
			}
		}
		if ( empty( $key_names['owner_code'] ) ) {
			if ( ! empty( $key_names['code'] ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( "ALTER TABLE {$disc} DROP INDEX `code`" );
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$disc} ADD UNIQUE KEY owner_code (owner_svp_user_id, code)" );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$idx_rows2 = $wpdb->get_results( "SHOW INDEX FROM {$disc}", ARRAY_A );
		$key_names2 = array();
		if ( is_array( $idx_rows2 ) ) {
			foreach ( $idx_rows2 as $ir ) {
				if ( is_array( $ir ) && ! empty( $ir['Key_name'] ) ) {
					$key_names2[ (string) $ir['Key_name'] ] = true;
				}
			}
		}
		if ( empty( $key_names2['owner_svp_user'] ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$disc} ADD KEY owner_svp_user (owner_svp_user_id)" );
		}

		$bc = $p . 'svp_broadcasts';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$bc} LIKE 'owner_svp_user_id'" ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$bc} ADD COLUMN owner_svp_user_id bigint(20) unsigned NOT NULL DEFAULT 0 AFTER id, ADD KEY owner_svp_user (owner_svp_user_id)" );
		}

		$rp = $p . 'svp_reseller_panel_prices';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$rp} LIKE 'panel_access'" ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$rp} ADD COLUMN panel_access tinyint(1) NOT NULL DEFAULT 1 AFTER price_per_gb" );
		}
	}

	/**
	 * Reseller-owned users bulk jobs (scope isolation in UI/API).
	 *
	 * @param string $p Table prefix.
	 */
	public static function maybe_migrate_208( $p ) {
		global $wpdb;
		$t = $p . 'svp_users_bulk_jobs';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$t} LIKE 'created_by_svp_user_id'" ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$t} ADD COLUMN created_by_svp_user_id bigint(20) unsigned NOT NULL DEFAULT 0 AFTER created_by_wp, ADD KEY created_by_svp (created_by_svp_user_id)" );
		}
	}

	/**
	 * Parent reseller floor table (direct parent -> direct child).
	 *
	 * @param string $p Prefix.
	 * @param string $charset_collate Collation.
	 */
	public static function maybe_migrate_210( $p, $charset_collate ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( self::sql_reseller_parent_panel_floors( $p, $charset_collate ) );
	}

	public static function maybe_migrate_204( $p, $charset_collate ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$sql_users_bulk_jobs = "CREATE TABLE {$p}svp_users_bulk_jobs (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			operation varchar(32) NOT NULL,
			scope varchar(32) NOT NULL DEFAULT 'all_approved',
			payload_json longtext NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			created_by_wp bigint(20) unsigned NOT NULL DEFAULT 0,
			created_by_svp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			finished_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY status (status),
			KEY created_by_svp (created_by_svp_user_id)
		) $charset_collate;";
		$sql_users_bulk_items = "CREATE TABLE {$p}svp_users_bulk_job_items (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			job_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			panel_id bigint(20) unsigned NOT NULL DEFAULT 0,
			inbound_id bigint(20) unsigned NOT NULL DEFAULT 0,
			client_email varchar(191) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'pending',
			tries int NOT NULL DEFAULT 0,
			last_error text NULL,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY job_id (job_id),
			KEY status (status),
			KEY job_user (job_id, user_id),
			KEY job_panel_client (job_id, panel_id, inbound_id, client_email(120))
		) $charset_collate;";
		dbDelta( $sql_users_bulk_jobs );
		dbDelta( $sql_users_bulk_items );
	}

	/**
	 * Bulk job items: panel client target columns for panel-first volume/extend.
	 *
	 * @param string $p               Table prefix.
	 * @param string $charset_collate Charset.
	 */
	public static function maybe_migrate_231_bulk_panel_items( $p, $charset_collate ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		global $wpdb;
		$t = $p . 'svp_users_bulk_job_items';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$has = $wpdb->get_var( "SHOW COLUMNS FROM {$t} LIKE 'panel_id'" );
		if ( ! $has ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$t} ADD COLUMN panel_id bigint(20) unsigned NOT NULL DEFAULT 0 AFTER user_id" );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$t} ADD COLUMN inbound_id bigint(20) unsigned NOT NULL DEFAULT 0 AFTER panel_id" );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$t} ADD COLUMN client_email varchar(191) NOT NULL DEFAULT '' AFTER inbound_id" );
		}
		$sql_users_bulk_items = "CREATE TABLE {$p}svp_users_bulk_job_items (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			job_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			panel_id bigint(20) unsigned NOT NULL DEFAULT 0,
			inbound_id bigint(20) unsigned NOT NULL DEFAULT 0,
			client_email varchar(191) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'pending',
			tries int NOT NULL DEFAULT 0,
			last_error text NULL,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY job_id (job_id),
			KEY status (status),
			KEY job_user (job_id, user_id),
			KEY job_panel_client (job_id, panel_id, inbound_id, client_email(120))
		) $charset_collate;";
		dbDelta( $sql_users_bulk_items );
	}

	/**
	 * Reseller pricing, optional bot tokens, plan ownership column.
	 *
	 * @param string $p Prefix.
	 * @param string $charset_collate Charset.
	 */
	public static function maybe_migrate_202( $p, $charset_collate ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( self::sql_reseller_panel_prices( $p, $charset_collate ) );
		dbDelta( self::sql_reseller_bot_profiles( $p, $charset_collate ) );
		global $wpdb;
		$plans = $p . 'svp_plans';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$col = $wpdb->get_var( "SHOW COLUMNS FROM {$plans} LIKE 'owner_svp_user_id'" );
		if ( ! $col ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$plans} ADD COLUMN owner_svp_user_id bigint(20) unsigned DEFAULT NULL AFTER sort_order, ADD KEY owner_svp_user (owner_svp_user_id)" );
		}
	}

	/**
	 * @param string $p Prefix.
	 * @param string $charset_collate Collation.
	 * @return string
	 */
	public static function sql_reseller_panel_prices( $p, $charset_collate ) {
		return "CREATE TABLE {$p}svp_reseller_panel_prices (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			reseller_svp_user_id bigint(20) unsigned NOT NULL,
			panel_id bigint(20) unsigned NOT NULL,
			price_per_gb decimal(15,4) NOT NULL DEFAULT 0,
			panel_access tinyint(1) NOT NULL DEFAULT 1,
			default_service_type varchar(16) NOT NULL DEFAULT 'xray',
			default_inbound_id int NOT NULL DEFAULT 0,
			default_l2tp_server_id bigint(20) unsigned NOT NULL DEFAULT 0,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY reseller_panel (reseller_svp_user_id, panel_id),
			KEY panel_id (panel_id)
		) $charset_collate;";
	}

	/**
	 * Reseller panel rows: admin presets for inbound / protocol (dashboard plan form).
	 *
	 * @param string $p Prefix.
	 */
	public static function maybe_migrate_211( $p ) {
		global $wpdb;
		$t = $p . 'svp_reseller_panel_prices';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$t} LIKE 'default_service_type'" ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$t} ADD COLUMN default_service_type varchar(16) NOT NULL DEFAULT 'xray' AFTER panel_access" );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$t} LIKE 'default_inbound_id'" ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$t} ADD COLUMN default_inbound_id int NOT NULL DEFAULT 0 AFTER default_service_type" );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$t} LIKE 'default_l2tp_server_id'" ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$t} ADD COLUMN default_l2tp_server_id bigint(20) unsigned NOT NULL DEFAULT 0 AFTER default_inbound_id" );
		}
	}

	/**
	 * Reseller wholesale lines (abstract catalog), tiers, assignments, accruals; plan.wholesale_line_id.
	 *
	 * @param string $p Prefix.
	 * @param string $charset_collate Charset.
	 */
	public static function maybe_migrate_220_wholesale_lines( $p, $charset_collate ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		global $wpdb;

		$sql_lines = "CREATE TABLE {$p}svp_reseller_wholesale_lines (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			label varchar(191) NOT NULL DEFAULT '',
			badge_color varchar(32) NOT NULL DEFAULT '',
			panel_id bigint(20) unsigned NOT NULL DEFAULT 1,
			default_service_type varchar(16) NOT NULL DEFAULT 'xray',
			default_inbound_id int NOT NULL DEFAULT 0,
			default_l2tp_server_id bigint(20) unsigned NOT NULL DEFAULT 0,
			active tinyint(1) NOT NULL DEFAULT 1,
			sort_order int NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY panel_id (panel_id),
			KEY active_sort (active, sort_order)
		) $charset_collate;";

		$sql_tiers = "CREATE TABLE {$p}svp_reseller_wholesale_tiers (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			line_id bigint(20) unsigned NOT NULL,
			sort_order int NOT NULL DEFAULT 0,
			price_per_gb decimal(15,4) NOT NULL DEFAULT 0,
			min_total_gb bigint NOT NULL DEFAULT 0,
			min_total_toman decimal(15,2) NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY line_sort (line_id, sort_order)
		) $charset_collate;";

		$sql_assign = "CREATE TABLE {$p}svp_reseller_wholesale_line_assignments (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			reseller_svp_user_id bigint(20) unsigned NOT NULL,
			line_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY reseller_line (reseller_svp_user_id, line_id),
			KEY line_id (line_id)
		) $charset_collate;";

		$sql_acc = "CREATE TABLE {$p}svp_reseller_wholesale_accruals (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			reseller_svp_user_id bigint(20) unsigned NOT NULL,
			line_id bigint(20) unsigned NOT NULL,
			delta_gb bigint NOT NULL DEFAULT 0,
			delta_wholesale_toman decimal(15,2) NOT NULL DEFAULT 0,
			unit_price_applied decimal(15,4) NOT NULL DEFAULT 0,
			transaction_id bigint(20) unsigned DEFAULT NULL,
			service_id bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY u_tx (transaction_id),
			KEY reseller_line (reseller_svp_user_id, line_id),
			KEY created (created_at)
		) $charset_collate;";

		dbDelta( $sql_lines );
		dbDelta( $sql_tiers );
		dbDelta( $sql_assign );
		dbDelta( $sql_acc );

		$plans = $p . 'svp_plans';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$plans} LIKE 'wholesale_line_id'" ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$plans} ADD COLUMN wholesale_line_id bigint(20) unsigned DEFAULT NULL AFTER panel_id, ADD KEY wholesale_line_id (wholesale_line_id)" );
		}
	}

	/**
	 * @param string $p Prefix.
	 * @param string $charset_collate Collation.
	 * @return string
	 */
	public static function sql_reseller_parent_panel_floors( $p, $charset_collate ) {
		return "CREATE TABLE {$p}svp_reseller_parent_panel_floors (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			parent_svp_user_id bigint(20) unsigned NOT NULL,
			child_svp_user_id bigint(20) unsigned NOT NULL,
			panel_id bigint(20) unsigned NOT NULL,
			min_price_per_gb decimal(15,4) NOT NULL DEFAULT 0,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY parent_child_panel (parent_svp_user_id, child_svp_user_id, panel_id),
			KEY child_panel (child_svp_user_id, panel_id),
			KEY parent_child (parent_svp_user_id, child_svp_user_id)
		) $charset_collate;";
	}

	/**
	 * @param string $p Prefix.
	 * @param string $charset_collate Collation.
	 * @return string
	 */
	public static function sql_reseller_bot_profiles( $p, $charset_collate ) {
		return "CREATE TABLE {$p}svp_reseller_bot_profiles (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			reseller_svp_user_id bigint(20) unsigned NOT NULL,
			telegram_token text NULL,
			bale_token text NULL,
			webhook_secret varchar(128) NOT NULL DEFAULT '',
			brand_name varchar(255) NOT NULL DEFAULT '',
			logo_url varchar(512) NOT NULL DEFAULT '',
			favicon_url varchar(512) NOT NULL DEFAULT '',
			theme_primary varchar(16) NOT NULL DEFAULT '',
			theme_accent varchar(16) NOT NULL DEFAULT '',
			custom_domain varchar(255) NOT NULL DEFAULT '',
			config_label_override varchar(255) NOT NULL DEFAULT '',
			config_label_prefix varchar(255) NOT NULL DEFAULT '',
			telegram_secret_token varchar(255) NOT NULL DEFAULT '',
			enabled tinyint(1) NOT NULL DEFAULT 1,
			admin_telegram_ids longtext NULL,
			admin_bale_ids longtext NULL,
			bale_wallet_provider_token varchar(255) NOT NULL DEFAULT '',
			telegram_bot_username varchar(128) NOT NULL DEFAULT '',
			bale_bot_username varchar(128) NOT NULL DEFAULT '',
			text_overrides_json longtext NULL,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY reseller_one (reseller_svp_user_id),
			KEY custom_domain (custom_domain)
		) $charset_collate;";
	}

	/**
	 * Closure table for invited_by tree.
	 *
	 * @param string $p Prefix.
	 * @param string $charset_collate Collation.
	 * @return string
	 */
	public static function sql_reseller_closure( $p, $charset_collate ) {
		return "CREATE TABLE {$p}svp_reseller_closure (
			ancestor_id bigint(20) unsigned NOT NULL,
			descendant_id bigint(20) unsigned NOT NULL,
			depth smallint unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY (ancestor_id, descendant_id),
			KEY descendant (descendant_id),
			KEY ancestor_depth (ancestor_id, depth)
		) $charset_collate;";
	}

	/**
	 * Admin audit log.
	 *
	 * @param string $p Prefix.
	 * @param string $charset_collate Collation.
	 * @return string
	 */
	public static function sql_audit_log( $p, $charset_collate ) {
		return "CREATE TABLE {$p}svp_audit_log (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			domain varchar(32) NOT NULL DEFAULT 'admin',
			event_type varchar(64) NOT NULL DEFAULT '',
			actor_kind varchar(20) NOT NULL DEFAULT 'system',
			actor_wp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			actor_svp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			target_type varchar(32) NOT NULL DEFAULT '',
			target_id bigint(20) unsigned NOT NULL DEFAULT 0,
			reseller_scope_id bigint(20) unsigned NOT NULL DEFAULT 0,
			payload_json longtext NULL,
			ip_hash char(64) NOT NULL DEFAULT '',
			PRIMARY KEY (id),
			KEY domain_created (domain, created_at),
			KEY event_type (event_type),
			KEY target (target_type, target_id),
			KEY actor_svp (actor_svp_user_id)
		) $charset_collate;";
	}

	/**
	 * Branding columns, closure + audit tables, closure backfill.
	 *
	 * @param string $p Prefix.
	 * @param string $charset_collate Collation.
	 */
	public static function maybe_migrate_230_branding_closure_audit( $p, $charset_collate ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( self::sql_reseller_bot_profiles( $p, $charset_collate ) );
		dbDelta( self::sql_reseller_closure( $p, $charset_collate ) );
		dbDelta( self::sql_audit_log( $p, $charset_collate ) );

		global $wpdb;
		$t = $p . 'svp_reseller_bot_profiles';
		$cols = array(
			'logo_url'       => "ADD COLUMN logo_url varchar(512) NOT NULL DEFAULT '' AFTER brand_name",
			'favicon_url'    => "ADD COLUMN favicon_url varchar(512) NOT NULL DEFAULT '' AFTER logo_url",
			'theme_primary'  => "ADD COLUMN theme_primary varchar(16) NOT NULL DEFAULT '' AFTER favicon_url",
			'theme_accent'   => "ADD COLUMN theme_accent varchar(16) NOT NULL DEFAULT '' AFTER theme_primary",
			'custom_domain'         => "ADD COLUMN custom_domain varchar(255) NOT NULL DEFAULT '' AFTER theme_accent",
			'config_label_override' => "ADD COLUMN config_label_override varchar(255) NOT NULL DEFAULT '' AFTER custom_domain",
			'config_label_prefix'   => "ADD COLUMN config_label_prefix varchar(255) NOT NULL DEFAULT '' AFTER config_label_override",
		);
		foreach ( $cols as $col => $ddl ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$t} LIKE '{$col}'" ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( "ALTER TABLE {$t} {$ddl}" );
			}
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$idx = $wpdb->get_results( "SHOW INDEX FROM {$t} WHERE Key_name = 'custom_domain'", ARRAY_A );
		if ( empty( $idx ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$t} ADD KEY custom_domain (custom_domain)" );
		}

		if ( ! get_option( 'simplevpbot_closure_backfill_v1_done' ) && class_exists( 'SimpleVPBot_Reseller_Closure' ) ) {
			SimpleVPBot_Reseller_Closure::rebuild_all();
			update_option( 'simplevpbot_closure_backfill_v1_done', true, false );
		}
	}

	/**
	 * signup_reseller_svp_id on users + one-time billing/invited_by backfill.
	 *
	 * @param string $p Table prefix.
	 */
	public static function maybe_migrate_224_reseller_signup_backfill( $p ) {
		global $wpdb;
		$users = $p . 'svp_users';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$users} LIKE 'signup_reseller_svp_id'" ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$users} ADD COLUMN signup_reseller_svp_id bigint(20) unsigned DEFAULT NULL AFTER invited_by" );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$users} ADD KEY signup_reseller (signup_reseller_svp_id)" );
		}
		if ( class_exists( 'SimpleVPBot_Reseller_Backfill' ) ) {
			SimpleVPBot_Reseller_Backfill::run_one_time_migrations();
		}
	}

	/**
	 * Reseller bot profile: bot usernames + text overrides column.
	 *
	 * @param string $p Table prefix.
	 */
	public static function maybe_migrate_223_reseller_bot_enhancements( $p ) {
		global $wpdb;
		$t = $p . 'svp_reseller_bot_profiles';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$t} LIKE 'telegram_bot_username'" ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$t} ADD COLUMN telegram_bot_username varchar(128) NOT NULL DEFAULT '' AFTER bale_wallet_provider_token" );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$t} LIKE 'bale_bot_username'" ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$t} ADD COLUMN bale_bot_username varchar(128) NOT NULL DEFAULT '' AFTER telegram_bot_username" );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$t} LIKE 'text_overrides_json'" ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$t} ADD COLUMN text_overrides_json longtext NULL AFTER bale_bot_username" );
		}
	}

	/**
	 * Reseller bot profiles: per-bot enable, admin id lists, Bale wallet token.
	 *
	 * @param string $p Prefix.
	 * @param string $charset_collate Collation.
	 */
	public static function maybe_migrate_205( $p, $charset_collate ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( self::sql_reseller_bot_profiles( $p, $charset_collate ) );
		global $wpdb;
		$t = $p . 'svp_reseller_bot_profiles';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$t} LIKE 'enabled'" ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$t} ADD COLUMN enabled tinyint(1) NOT NULL DEFAULT 1 AFTER telegram_secret_token" );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$t} LIKE 'admin_telegram_ids'" ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$t} ADD COLUMN admin_telegram_ids longtext NULL AFTER enabled" );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$t} LIKE 'admin_bale_ids'" ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$t} ADD COLUMN admin_bale_ids longtext NULL AFTER admin_telegram_ids" );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$t} LIKE 'bale_wallet_provider_token'" ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$t} ADD COLUMN bale_wallet_provider_token varchar(255) NOT NULL DEFAULT '' AFTER admin_bale_ids" );
		}
	}

	/**
	 * Reseller bot profile: webhook secret, brand, optional Telegram secret token.
	 *
	 * @param string $p Prefix.
	 * @param string $charset_collate Collation.
	 */
	public static function maybe_migrate_203( $p, $charset_collate ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( self::sql_reseller_bot_profiles( $p, $charset_collate ) );
		global $wpdb;
		$t = $p . 'svp_reseller_bot_profiles';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$t} LIKE 'webhook_secret'" ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$t} ADD COLUMN webhook_secret varchar(128) NOT NULL DEFAULT '' AFTER bale_token" );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$t} LIKE 'brand_name'" ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$t} ADD COLUMN brand_name varchar(255) NOT NULL DEFAULT '' AFTER webhook_secret" );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$t} LIKE 'telegram_secret_token'" ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$t} ADD COLUMN telegram_secret_token varchar(255) NOT NULL DEFAULT '' AFTER brand_name" );
		}
	}

	/**
	 * Dashboard inbound client cache + API snapshot tables.
	 *
	 * @param string $p Prefix.
	 * @param string $charset_collate Charset.
	 */
	public static function maybe_migrate_201( $p, $charset_collate ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( self::sql_panel_inbound_clients( $p, $charset_collate ) );
		dbDelta( self::sql_panel_inbound_api( $p, $charset_collate ) );
	}

	/**
	 * Cached panel inbound clients (one row per client email per inbound).
	 *
	 * @param string $p Prefix.
	 * @param string $charset_collate Collation.
	 * @return string
	 */
	public static function sql_panel_inbound_clients( $p, $charset_collate ) {
		return "CREATE TABLE {$p}svp_panel_inbound_clients (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			panel_id bigint(20) unsigned NOT NULL,
			inbound_id int NOT NULL,
			inbound_remark varchar(255) NOT NULL DEFAULT '',
			protocol varchar(32) NOT NULL DEFAULT '',
			port int NOT NULL DEFAULT 0,
			email varchar(191) NOT NULL,
			xui_client_id varchar(191) NOT NULL DEFAULT '',
			remark varchar(255) NOT NULL DEFAULT '',
			comment varchar(500) NOT NULL DEFAULT '',
			tg_id varchar(64) NOT NULL DEFAULT '',
			sub_id varchar(128) NOT NULL DEFAULT '',
			enable tinyint(1) NOT NULL DEFAULT 1,
			total_gb int NOT NULL DEFAULT 0,
			expiry_ms bigint NOT NULL DEFAULT 0,
			used_bytes bigint NOT NULL DEFAULT 0,
			limit_bytes bigint NOT NULL DEFAULT 0,
			is_online tinyint(1) NOT NULL DEFAULT 0,
			client_ips_json longtext NULL,
			client_json longtext NULL,
			synced_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY p_i_e (panel_id, inbound_id, email),
			KEY panel_i (panel_id, inbound_id),
			KEY panel_synced (panel_id, synced_at)
		) $charset_collate;";
	}

	/**
	 * Full inbound JSON from X-UI per (panel, inbound) for rebuilding share URIs from cache.
	 *
	 * @param string $p Prefix.
	 * @param string $charset_collate Collation.
	 * @return string
	 */
	public static function sql_panel_inbound_api( $p, $charset_collate ) {
		return "CREATE TABLE {$p}svp_panel_inbound_api (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			panel_id bigint(20) unsigned NOT NULL,
			inbound_id int NOT NULL,
			inbound_json longtext NOT NULL,
			synced_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY p_in (panel_id, inbound_id)
		) $charset_collate;";
	}

	/**
	 * Referral accounting column + referral events table + new text keys.
	 *
	 * @param string $p Prefix.
	 * @param string $charset_collate Charset.
	 */
	public static function maybe_migrate_160( $p, $charset_collate ) {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$tx = $p . 'svp_transactions';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$tx} LIKE 'referral_amount'" ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$tx} ADD COLUMN referral_amount decimal(15,2) NOT NULL DEFAULT 0 AFTER meta_json" );
		}
		dbDelta( self::sql_referral_events( $p, $charset_collate ) );
		self::seed_missing_text_keys();
	}

	/**
	 * Referral column + discount codes table.
	 *
	 * @param string $p Table prefix.
	 * @param string $charset_collate Charset.
	 */
	public static function maybe_migrate_120( $p, $charset_collate ) {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$users_table = $p . 'svp_users';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$col = $wpdb->get_var( "SHOW COLUMNS FROM {$users_table} LIKE 'invited_by'" );
		if ( ! $col ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$users_table} ADD COLUMN invited_by bigint(20) unsigned DEFAULT NULL AFTER state_data" );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$users_table} ADD KEY invited_by (invited_by)" );
		}
		dbDelta( self::sql_discount_codes( $p, $charset_collate ) );
		dbDelta( self::sql_discount_redemptions( $p, $charset_collate ) );
	}

	/**
	 * Link bot user rows to WordPress accounts for /dashboard scoped views.
	 *
	 * @param string $p Table prefix.
	 */
	public static function maybe_add_wp_user_id_column( $p ) {
		global $wpdb;
		$users_table = $p . 'svp_users';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$col = $wpdb->get_var( "SHOW COLUMNS FROM {$users_table} LIKE 'wp_user_id'" );
		if ( ! $col ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$users_table} ADD COLUMN wp_user_id bigint(20) unsigned DEFAULT NULL AFTER invited_by" );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$users_table} ADD UNIQUE KEY svp_users_wp (wp_user_id)" );
		}
	}

	/**
	 * Broadcast tables: stats columns, queue error columns (dbDelta + column checks).
	 *
	 * @param string $p Prefix.
	 * @param string $charset_collate Charset.
	 */
	public static function maybe_migrate_broadcast_140( $p, $charset_collate ) {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$sql_broadcasts = "CREATE TABLE {$p}svp_broadcasts (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			type varchar(16) NOT NULL,
			content longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'draft',
			sent_count int NOT NULL DEFAULT 0,
			failed_count int NOT NULL DEFAULT 0,
			total_targets int NOT NULL DEFAULT 0,
			blocked_count int NOT NULL DEFAULT 0,
			meta_json longtext NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $charset_collate;";
		$sql_queue      = "CREATE TABLE {$p}svp_broadcast_queue (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			broadcast_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			bot varchar(8) NOT NULL,
			chat_id bigint(20) NOT NULL,
			payload_json longtext NOT NULL,
			status varchar(16) NOT NULL DEFAULT 'pending',
			tries int NOT NULL DEFAULT 0,
			last_error text NULL,
			failure_kind varchar(32) NULL,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY broadcast_id (broadcast_id),
			KEY status (status)
		) $charset_collate;";
		dbDelta( $sql_broadcasts );
		dbDelta( $sql_queue );
		$bt = $p . 'svp_broadcasts';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$bt} LIKE 'total_targets'" ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$bt} ADD COLUMN total_targets int NOT NULL DEFAULT 0 AFTER failed_count" );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$bt} LIKE 'blocked_count'" ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$bt} ADD COLUMN blocked_count int NOT NULL DEFAULT 0 AFTER total_targets" );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$bt} LIKE 'meta_json'" ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$bt} ADD COLUMN meta_json longtext NULL AFTER blocked_count" );
		}
		$qt = $p . 'svp_broadcast_queue';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$qt} LIKE 'last_error'" ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$qt} ADD COLUMN last_error text NULL AFTER tries" );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$qt} LIKE 'failure_kind'" ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$qt} ADD COLUMN failure_kind varchar(32) NULL AFTER last_error" );
		}
	}

	/**
	 * Multi 3x-ui panels: table svp_panels, panel_id on plans/categories, widen service.panel_id, seed row #1 from settings.
	 *
	 * @param string $p Table prefix.
	 */
	public static function maybe_migrate_multi_panel_110( $p ) {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $wpdb->get_charset_collate();
		dbDelta( self::sql_panels_table( $p, $charset_collate ) );

		$pcat = $p . 'svp_plan_categories';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$col_pc = $wpdb->get_var( "SHOW COLUMNS FROM {$pcat} LIKE 'panel_id'" );
		if ( ! $col_pc ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$idx = $wpdb->get_results( "SHOW INDEX FROM {$pcat} WHERE Key_name = 'slug'" );
			if ( ! empty( $idx ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( "ALTER TABLE {$pcat} DROP INDEX slug" );
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$pcat} ADD COLUMN panel_id bigint(20) unsigned NOT NULL DEFAULT 1 AFTER id" );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "UPDATE {$pcat} SET panel_id = 1" );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$idx_ps = $wpdb->get_results( "SHOW INDEX FROM {$pcat} WHERE Key_name = 'panel_slug'" );
		if ( empty( $idx_ps ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$pcat} ADD UNIQUE KEY panel_slug (panel_id, slug)" );
		}

		$plans_t = $p . 'svp_plans';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$col_pl = $wpdb->get_var( "SHOW COLUMNS FROM {$plans_t} LIKE 'panel_id'" );
		if ( ! $col_pl ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$plans_t} ADD COLUMN panel_id bigint(20) unsigned NOT NULL DEFAULT 1 AFTER inbound_id" );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "UPDATE {$plans_t} SET panel_id = 1" );
		}

		$svc_t = $p . 'svp_services';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$col_svc_panel = $wpdb->get_var( "SHOW COLUMNS FROM {$svc_t} LIKE 'panel_id'" );
		if ( ! $col_svc_panel ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$svc_t} ADD COLUMN panel_id bigint(20) unsigned NOT NULL DEFAULT 1 AFTER user_id" );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "UPDATE {$svc_t} SET panel_id = 1" );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$svc_t} MODIFY COLUMN panel_id bigint(20) unsigned NOT NULL DEFAULT 1" );
		}

		$panels_t = $p . 'svp_panels';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$n = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$panels_t}" );
		if ( $n < 1 && class_exists( 'SimpleVPBot_Settings' ) ) {
			$s = SimpleVPBot_Settings::all();
			$url = trim( (string) ( $s['panel_url'] ?? '' ) );
			if ( '' !== $url ) {
				$wpdb->insert(
					$panels_t,
					array(
						'label'                     => __( 'پنل اصلی', 'simplevpbot' ),
						'panel_url'                 => $url,
						'panel_username'            => (string) ( $s['panel_username'] ?? '' ),
						'panel_password'            => (string) ( $s['panel_password'] ?? '' ),
						'panel_api_base'            => (string) ( $s['panel_api_base'] ?? 'panel/api' ),
						'panel_login_secret'        => (string) ( $s['panel_login_secret'] ?? '' ),
						'panel_api_token'           => (string) ( $s['panel_api_token'] ?? '' ),
						'subscription_public_base' => (string) ( $s['subscription_public_base'] ?? '' ),
						'sort_order'                => 0,
						'active'                    => 1,
					),
					array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d' )
				);
			}
		}
	}

	/**
	 * Add 3x-ui v3 Bearer API token storage for existing panel rows.
	 *
	 * @param string $p Table prefix.
	 */
	public static function maybe_migrate_221_panel_api_token( $p ) {
		global $wpdb;
		$panels_t = $p . 'svp_panels';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$col = $wpdb->get_var( "SHOW COLUMNS FROM {$panels_t} LIKE 'panel_api_token'" );
		if ( ! $col ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$panels_t} ADD COLUMN panel_api_token text NULL AFTER panel_login_secret" );
		}
	}

	/**
	 * Discount caps, user/plan restrictions, redemption history.
	 *
	 * @param string $p Prefix.
	 * @param string $charset_collate Collation.
	 */
	public static function maybe_migrate_222_discount_enhancements( $p, $charset_collate ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		global $wpdb;
		dbDelta( self::sql_discount_redemptions( $p, $charset_collate ) );
		$disc = $p . 'svp_discount_codes';
		$cols = array(
			'max_order_toman'        => "ALTER TABLE {$disc} ADD COLUMN max_order_toman decimal(15,2) DEFAULT NULL AFTER min_order_toman",
			'max_discount_toman'     => "ALTER TABLE {$disc} ADD COLUMN max_discount_toman decimal(15,2) DEFAULT NULL AFTER max_order_toman",
			'restricted_svp_user_id' => "ALTER TABLE {$disc} ADD COLUMN restricted_svp_user_id bigint(20) unsigned DEFAULT NULL AFTER max_discount_toman",
			'allowed_plan_ids'       => "ALTER TABLE {$disc} ADD COLUMN allowed_plan_ids text NULL AFTER restricted_svp_user_id",
		);
		foreach ( $cols as $name => $sql ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$disc} LIKE '{$name}'" ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( $sql );
			}
		}
	}

	/**
	 * Discount redemption audit rows.
	 *
	 * @param string $p Prefix.
	 * @param string $charset_collate Collation.
	 * @return string
	 */
	public static function sql_discount_redemptions( $p, $charset_collate ) {
		return "CREATE TABLE {$p}svp_discount_redemptions (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			discount_code_id bigint(20) unsigned NOT NULL DEFAULT 0,
			transaction_id bigint(20) unsigned NOT NULL DEFAULT 0,
			svp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			subtotal_toman decimal(15,2) NOT NULL DEFAULT 0,
			discount_toman decimal(15,2) NOT NULL DEFAULT 0,
			volume_gb decimal(12,3) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY tx_once (transaction_id),
			KEY code_id (discount_code_id),
			KEY user_id (svp_user_id)
		) $charset_collate;";
	}

	/**
	 * Per-service alert channels + thresholds (3x-ui compatible).
	 *
	 * @param string $p Table prefix.
	 */
	public static function maybe_add_service_alert_columns( $p ) {
		global $wpdb;
		$t = $p . 'svp_services';
		$add = array(
			'alerts_volume'     => "ALTER TABLE {$t} ADD COLUMN alerts_volume tinyint(1) NOT NULL DEFAULT 1 AFTER alerts_enabled",
			'alerts_expiry'     => "ALTER TABLE {$t} ADD COLUMN alerts_expiry tinyint(1) NOT NULL DEFAULT 1 AFTER alerts_volume",
			'alerts_users'      => "ALTER TABLE {$t} ADD COLUMN alerts_users tinyint(1) NOT NULL DEFAULT 1 AFTER alerts_expiry",
			'alert_low_pct'     => "ALTER TABLE {$t} ADD COLUMN alert_low_pct smallint DEFAULT NULL AFTER alerts_users",
			'alert_expiry_days' => "ALTER TABLE {$t} ADD COLUMN alert_expiry_days varchar(64) DEFAULT NULL AFTER alert_low_pct",
			'alert_ip_fill_pct' => "ALTER TABLE {$t} ADD COLUMN alert_ip_fill_pct smallint DEFAULT NULL AFTER alert_expiry_days",
		);
		foreach ( $add as $col => $sql ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$exists = $wpdb->get_var( "SHOW COLUMNS FROM {$t} LIKE '" . esc_sql( $col ) . "'" );
			if ( ! $exists ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->query( $sql );
			}
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "UPDATE {$t} SET alerts_volume = alerts_enabled, alerts_expiry = alerts_enabled, alerts_users = alerts_enabled" );
	}

	/**
	 * Optional per-service JSON schedule overrides (expiry_days, low_traffic_pct, ip_fill_pct).
	 *
	 * @param string $p Prefix.
	 */
	public static function maybe_add_service_alert_schedule_json( $p ) {
		global $wpdb;
		$t = $p . 'svp_services';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$exists = $wpdb->get_var( "SHOW COLUMNS FROM {$t} LIKE 'alert_schedule_json'" );
		if ( ! $exists ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$t} ADD COLUMN alert_schedule_json longtext NULL AFTER alert_ip_fill_pct" );
		}
	}

	/**
	 * Soft-delete timestamp for services (admin removes row from active lists).
	 *
	 * @param string $p Table prefix.
	 */
	public static function maybe_add_service_deleted_at( $p ) {
		global $wpdb;
		$t = $p . 'svp_services';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$exists = $wpdb->get_var( "SHOW COLUMNS FROM {$t} LIKE 'deleted_at'" );
		if ( ! $exists ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$t} ADD COLUMN deleted_at datetime DEFAULT NULL AFTER created_at" );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$idx = $wpdb->get_results( "SHOW INDEX FROM {$t} WHERE Key_name = 'deleted_at'" );
		if ( empty( $idx ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$t} ADD KEY deleted_at (deleted_at)" );
		}
	}

	/**
	 * Dashboard: panel client cache columns + IP log table.
	 *
	 * @param string $p Prefix.
	 * @param string $charset_collate Charset.
	 */
	public static function maybe_migrate_200( $p, $charset_collate ) {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t = $p . 'svp_services';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$t} LIKE 'panel_limit_ip'" ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$t} ADD COLUMN panel_limit_ip int unsigned DEFAULT NULL AFTER last_warn_sent_at" );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$t} LIKE 'panel_client_enabled'" ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$t} ADD COLUMN panel_client_enabled tinyint(1) DEFAULT NULL AFTER panel_limit_ip" );
		}
		dbDelta( self::sql_svp_service_ip_log( $p, $charset_collate ) );
	}

	/**
	 * Merge duplicate svp_users rows that share the same Telegram or Bale id.
	 */
	public static function dedupe_users_by_bot_ids() {
		if ( ! class_exists( 'SimpleVPBot_Model_User' ) ) {
			return;
		}
		global $wpdb;
		$t = $wpdb->prefix . 'svp_users';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$tg_dups = $wpdb->get_col( "SELECT tg_user_id FROM {$t} WHERE tg_user_id IS NOT NULL AND tg_user_id > 0 GROUP BY tg_user_id HAVING COUNT(*) > 1" );
		foreach ( (array) $tg_dups as $tg ) {
			$tg = (int) $tg;
			if ( $tg < 1 ) {
				continue;
			}
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$t} WHERE tg_user_id = %d ORDER BY id ASC", $tg ) ); // phpcs:ignore
			if ( ! is_array( $ids ) || count( $ids ) < 2 ) {
				continue;
			}
			$keep = (int) array_shift( $ids );
			foreach ( $ids as $drop ) {
				SimpleVPBot_Model_User::merge_users( $keep, (int) $drop, 'internal' );
			}
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$bale_dups = $wpdb->get_col( "SELECT bale_user_id FROM {$t} WHERE bale_user_id IS NOT NULL AND bale_user_id > 0 GROUP BY bale_user_id HAVING COUNT(*) > 1" );
		foreach ( (array) $bale_dups as $bid ) {
			$bid = (int) $bid;
			if ( $bid < 1 ) {
				continue;
			}
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$t} WHERE bale_user_id = %d ORDER BY id ASC", $bid ) ); // phpcs:ignore
			if ( ! is_array( $ids ) || count( $ids ) < 2 ) {
				continue;
			}
			$keep = (int) array_shift( $ids );
			foreach ( $ids as $drop ) {
				SimpleVPBot_Model_User::merge_users( $keep, (int) $drop, 'internal' );
			}
		}
	}

	/**
	 * Add UNIQUE indexes on tg_user_id / bale_user_id if missing (after dedupe).
	 */
	public static function maybe_add_user_unique_indexes() {
		global $wpdb;
		$t = $wpdb->prefix . 'svp_users';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$tg = $wpdb->get_results( "SHOW INDEX FROM {$t} WHERE Key_name = 'svp_users_tg'" );
		if ( empty( $tg ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$t} ADD UNIQUE KEY svp_users_tg (tg_user_id)" );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$bl = $wpdb->get_results( "SHOW INDEX FROM {$t} WHERE Key_name = 'svp_users_bale'" );
		if ( empty( $bl ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$t} ADD UNIQUE KEY svp_users_bale (bale_user_id)" );
		}
	}

	/**
	 * If msg.subscription_panel still matches the legacy English seed, replace
	 * it with the new Persian usage-only template. Custom edits are preserved.
	 */
	public static function migrate_subscription_panel_text() {
		global $wpdb;
		$t      = $wpdb->prefix . 'svp_texts';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$has_locale = $wpdb->get_var( "SHOW COLUMNS FROM {$t} LIKE 'locale'" );
		$legacy = "🖥 Subscription Info\n──────────\n🆔 Subscription ID : {sub_id}\n📶 Status          : {status_emoji} {status}\n⬇️ Downloaded      : {down_gb} GB\n⬆️ Uploaded        : {up_gb} GB\n📊 Usage           : {used_gb} GB\n🧮 Total quota     : {total_quota}\n🕒 Last Online     : {last_online}\n⏳ Expiry          : {expiry}\n──────────\n🚀 {remark}\n`{config_link}`";
		$old_fa = "📊 وضعیت سرویس\n➖➖➖➖➖➖➖➖\n🏷 نام: {remark}\n🆔 شناسه: {sub_id}\n📶 وضعیت: {status_emoji} {status}\n➖➖➖➖➖➖➖➖\n⬇️ دانلود: {down_h}\n⬆️ آپلود: {up_h}\n📊 مصرف کل: {used_h}\n🧮 حجم کل: {total_quota}\n🎯 باقی‌مانده: {remained_h}\n➖➖➖➖➖➖➖➖\n🕒 آخرین اتصال: {last_online}\n⏳ انقضا: {expiry}";
		$new    = "📊 وضعیت سرویس\n──────────\n🏷 نام: {remark}\n🆔 شناسه: {sub_id}\n📶 وضعیت: {status_emoji} {status}\n──────────\n⬇️ دانلود: {down_h}\n⬆️ آپلود: {up_h}\n📊 مصرف کل: {used_h}\n🧮 سهمیه: {total_quota}\n🎯 باقی‌مانده: {remained_h}\n──────────\n🕒 آخرین اتصال: {last_online}\n⏳ انقضا: {expiry}";
		if ( $has_locale ) {
			$cur = $wpdb->get_var( $wpdb->prepare( "SELECT value FROM {$t} WHERE key_name = %s AND locale = %s", 'msg.subscription_panel', 'fa' ) ); // phpcs:ignore
			if ( is_string( $cur ) && ( $cur === $legacy || $cur === $old_fa ) ) {
				$wpdb->update( $t, array( 'value' => $new ), array( 'key_name' => 'msg.subscription_panel', 'locale' => 'fa' ) );
				if ( class_exists( 'SimpleVPBot_Texts' ) ) {
					SimpleVPBot_Texts::clear_cache();
				}
			}
			return;
		}
		$cur = $wpdb->get_var( $wpdb->prepare( "SELECT value FROM {$t} WHERE key_name = %s", 'msg.subscription_panel' ) ); // phpcs:ignore
		if ( is_string( $cur ) && ( $cur === $legacy || $cur === $old_fa ) ) {
			$wpdb->update( $t, array( 'value' => $new ), array( 'key_name' => 'msg.subscription_panel' ) );
			if ( class_exists( 'SimpleVPBot_Texts' ) ) {
				SimpleVPBot_Texts::clear_cache();
			}
		}
	}

	/**
	 * Overwrite all editable bot texts with values from default_text_rows() (WP admin + bot hub).
	 *
	 * @return void
	 */
	public static function reset_texts_to_defaults() {
		foreach ( self::default_text_rows() as $row ) {
			SimpleVPBot_Model_Text::set(
				$row['key_name'],
				$row['value'],
				$row['category'],
				(string) ( $row['locale'] ?? 'fa' )
			);
		}
		if ( class_exists( 'SimpleVPBot_Texts' ) ) {
			SimpleVPBot_Texts::clear_cache();
		}
	}

	/**
	 * Insert rows from default_text_rows() only when key_name is absent (does not overwrite).
	 *
	 * @return void
	 */
	private static function insert_default_text_rows_if_missing() {
		global $wpdb;
		$table = $wpdb->prefix . 'svp_texts';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$has_locale = $wpdb->get_var( "SHOW COLUMNS FROM {$table} LIKE 'locale'" );
		foreach ( self::default_text_rows() as $row ) {
			$loc = (string) ( $row['locale'] ?? 'fa' );
			if ( $has_locale ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$exists = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT id FROM {$table} WHERE key_name = %s AND locale = %s",
						$row['key_name'],
						$loc
					)
				);
			} else {
				// Legacy single-locale table (pre-2.0.6): only insert primary FA row per key.
				if ( 'fa' !== $loc ) {
					continue;
				}
				$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE key_name = %s", $row['key_name'] ) ); // phpcs:ignore
			}
			if ( $exists ) {
				continue;
			}
			if ( $has_locale ) {
				$wpdb->insert(
					$table,
					array(
						'key_name' => $row['key_name'],
						'category' => $row['category'],
						'locale'   => $loc,
						'value'    => $row['value'],
					),
					array( '%s', '%s', '%s', '%s' )
				);
			} else {
				$wpdb->insert(
					$table,
					array(
						'key_name' => $row['key_name'],
						'category' => $row['category'],
						'value'    => $row['value'],
					),
					array( '%s', '%s', '%s' )
				);
			}
		}
	}

	/**
	 * Seed default texts (emoji + structure).
	 */
	public static function seed_texts() {
		self::insert_default_text_rows_if_missing();
	}

	/**
	 * Ensure new default keys exist for upgraded installs without touching customized rows.
	 *
	 * @return void
	 */
	public static function seed_missing_text_keys() {
		self::insert_default_text_rows_if_missing();
		if ( class_exists( 'SimpleVPBot_Texts' ) ) {
			SimpleVPBot_Texts::clear_cache();
		}
	}

	/**
	 * Default text rows (fa + en per logical key).
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function default_text_rows() {
		if ( ! class_exists( 'SimpleVPBot_Bot_Text_Defaults', false ) ) {
			require_once SIMPLEVPBOT_PLUGIN_DIR . 'includes/class-bot-text-defaults.php';
		}
		return SimpleVPBot_Bot_Text_Defaults::all_rows();
	}

	/**
	 * Map text key => array with fa/en default snippets (from default_text_rows).
	 *
	 * @return array<string, array{fa:string, en:string}>
	 */
	public static function default_text_values_map() {
		$out = array();
		foreach ( self::default_text_rows() as $row ) {
			$kn = (string) ( $row['key_name'] ?? '' );
			if ( '' === $kn ) {
				continue;
			}
			if ( ! isset( $out[ $kn ] ) ) {
				$out[ $kn ] = array(
					'fa' => '',
					'en' => '',
				);
			}
			$loc = (string) ( $row['locale'] ?? 'fa' );
			if ( 'en' === $loc ) {
				$out[ $kn ]['en'] = (string) ( $row['value'] ?? '' );
			} else {
				$out[ $kn ]['fa'] = (string) ( $row['value'] ?? '' );
			}
		}
		return $out;
	}

	/**
	 * Default row for a text key and locale, or null.
	 *
	 * @param string $key_name Key.
	 * @param string $locale   fa|en.
	 * @return array{key_name:string,category:string,value:string,locale:string}|null
	 */
	public static function default_row_for_text_key( $key_name, $locale = 'fa' ) {
		$k = trim( (string) $key_name );
		if ( '' === $k ) {
			return null;
		}
		$loc = ( 'en' === (string) $locale ) ? 'en' : 'fa';
		foreach ( self::default_text_rows() as $row ) {
			if ( isset( $row['key_name'] ) && (string) $row['key_name'] === $k && (string) ( $row['locale'] ?? 'fa' ) === $loc ) {
				return array(
					'key_name' => $k,
					'category' => (string) ( $row['category'] ?? 'general' ),
					'value'    => (string) ( $row['value'] ?? '' ),
					'locale'   => $loc,
				);
			}
		}
		return null;
	}
}
