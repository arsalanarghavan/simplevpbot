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

	const DB_VERSION = '1.9.0';

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
			invited_by bigint(20) unsigned DEFAULT NULL,
			wp_user_id bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY svp_users_tg (tg_user_id),
			UNIQUE KEY svp_users_bale (bale_user_id),
			UNIQUE KEY svp_users_wp (wp_user_id),
			KEY status (status),
			KEY role (role),
			KEY invited_by (invited_by)
		) $charset_collate;";

		$sql_cards = "CREATE TABLE {$p}svp_cards (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
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
			KEY active (active)
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
			value longtext NOT NULL,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY key_name (key_name),
			KEY category (category)
		) $charset_collate;";

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
		dbDelta( self::sql_user_activity( $p, $charset_collate ) );
		dbDelta( self::sql_panels_table( $p, $charset_collate ) );
		dbDelta( self::sql_panel_online_daily( $p, $charset_collate ) );
		dbDelta( self::sql_monitor_hosts( $p, $charset_collate ) );
		dbDelta( self::sql_discount_codes( $p, $charset_collate ) );
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
			UNIQUE KEY code (code),
			KEY active (active)
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
			service_type varchar(16) NOT NULL DEFAULT 'xray',
			l2tp_server_id bigint(20) unsigned DEFAULT NULL,
			active tinyint(1) NOT NULL DEFAULT 1,
			sort_order int NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY category (category),
			KEY active (active),
			KEY service_type (service_type),
			KEY panel_id (panel_id)
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
		update_option( 'simplevpbot_db_version', self::DB_VERSION );
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
						'subscription_public_base' => (string) ( $s['subscription_public_base'] ?? '' ),
						'sort_order'                => 0,
						'active'                    => 1,
					),
					array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d' )
				);
			}
		}
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
				SimpleVPBot_Model_User::merge_users( $keep, (int) $drop );
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
				SimpleVPBot_Model_User::merge_users( $keep, (int) $drop );
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
		$legacy = "🖥 Subscription Info\n──────────\n🆔 Subscription ID : {sub_id}\n📶 Status          : {status_emoji} {status}\n⬇️ Downloaded      : {down_gb} GB\n⬆️ Uploaded        : {up_gb} GB\n📊 Usage           : {used_gb} GB\n🧮 Total quota     : {total_quota}\n🕒 Last Online     : {last_online}\n⏳ Expiry          : {expiry}\n──────────\n🚀 {remark}\n`{config_link}`";
		$old_fa = "📊 وضعیت سرویس\n➖➖➖➖➖➖➖➖\n🏷 نام: {remark}\n🆔 شناسه: {sub_id}\n📶 وضعیت: {status_emoji} {status}\n➖➖➖➖➖➖➖➖\n⬇️ دانلود: {down_h}\n⬆️ آپلود: {up_h}\n📊 مصرف کل: {used_h}\n🧮 حجم کل: {total_quota}\n🎯 باقی‌مانده: {remained_h}\n➖➖➖➖➖➖➖➖\n🕒 آخرین اتصال: {last_online}\n⏳ انقضا: {expiry}";
		$new    = "📊 وضعیت سرویس\n──────────\n🏷 نام: {remark}\n🆔 شناسه: {sub_id}\n📶 وضعیت: {status_emoji} {status}\n──────────\n⬇️ دانلود: {down_h}\n⬆️ آپلود: {up_h}\n📊 مصرف کل: {used_h}\n🧮 سهمیه: {total_quota}\n🎯 باقی‌مانده: {remained_h}\n──────────\n🕒 آخرین اتصال: {last_online}\n⏳ انقضا: {expiry}";
		$cur    = $wpdb->get_var( $wpdb->prepare( "SELECT value FROM {$t} WHERE key_name = %s", 'msg.subscription_panel' ) ); // phpcs:ignore
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
			SimpleVPBot_Model_Text::set( $row['key_name'], $row['value'], $row['category'] );
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
		foreach ( self::default_text_rows() as $row ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE key_name = %s", $row['key_name'] ) ); // phpcs:ignore
			if ( $exists ) {
				continue;
			}
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
	 * Default text rows.
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function default_text_rows() {
		return array(
			array( 'key_name' => 'btn.main.buy', 'category' => 'buttons', 'value' => '🛒 خرید سرویس' ),
			array( 'key_name' => 'btn.main.manage', 'category' => 'buttons', 'value' => '🧰 مدیریت سرویس' ),
			array( 'key_name' => 'btn.main.wallet', 'category' => 'buttons', 'value' => '💰 کیف پول' ),
			array( 'key_name' => 'btn.main.apps', 'category' => 'buttons', 'value' => '📱 اپلیکیشن‌ها' ),
			array( 'key_name' => 'btn.main.support', 'category' => 'buttons', 'value' => '🆘 پشتیبانی' ),
			array( 'key_name' => 'btn.main.account', 'category' => 'buttons', 'value' => '👤 اطلاعات حساب' ),
			array( 'key_name' => 'btn.main.referral', 'category' => 'buttons', 'value' => '💎 کسب درآمد' ),
			array( 'key_name' => 'btn.admin.dashboard', 'category' => 'buttons', 'value' => '📊 آمار' ),
			array( 'key_name' => 'btn.admin.users', 'category' => 'buttons', 'value' => '👥 مدیریت کاربران' ),
			array( 'key_name' => 'btn.admin.finance', 'category' => 'buttons', 'value' => '💰 مالی' ),
			array( 'key_name' => 'btn.admin.broadcast', 'category' => 'buttons', 'value' => '📣 پیام همگانی' ),
			array( 'key_name' => 'btn.admin.settings', 'category' => 'buttons', 'value' => '⚙️ تنظیمات' ),
			array( 'key_name' => 'btn.admin.advanced', 'category' => 'buttons', 'value' => '🔧 تنظیمات پیشرفته' ),
			array( 'key_name' => 'btn.admin.users_search', 'category' => 'buttons', 'value' => '🔎 جستجوی کاربر' ),
			array( 'key_name' => 'btn.admin.users_queue', 'category' => 'buttons', 'value' => '📋 صف ثبت‌نام' ),
			array( 'key_name' => 'btn.admin.receipts', 'category' => 'buttons', 'value' => '🧾 تایید رسیدها' ),
			array( 'key_name' => 'btn.admin.backup', 'category' => 'buttons', 'value' => '💾 پشتیبان‌گیری' ),
			array( 'key_name' => 'btn.admin.full_hub', 'category' => 'buttons', 'value' => '🧩 پنل کامل' ),
			array( 'key_name' => 'btn.admin.back_menu', 'category' => 'buttons', 'value' => '⬅️ منوی مدیریت' ),
			array( 'key_name' => 'btn.admin.send_my_portal', 'category' => 'buttons', 'value' => '🌐 ارسال لینک پنل وب من' ),
			array( 'key_name' => 'btn.admin.send_admin_portal', 'category' => 'buttons', 'value' => '🖥 ارسال لینک پنل ادمین وب' ),
			array( 'key_name' => 'btn.admin.transfer', 'category' => 'buttons', 'value' => '🎁 انتقال سرویس' ),
			array( 'key_name' => 'btn.admin.dm_user', 'category' => 'buttons', 'value' => '✉️ پیام به کاربر' ),
			array( 'key_name' => 'btn.admin.exit', 'category' => 'buttons', 'value' => '🚪 خروج از پنل مدیریت' ),
			array( 'key_name' => 'btn.approve', 'category' => 'buttons', 'value' => '✅ تایید' ),
			array( 'key_name' => 'btn.reject', 'category' => 'buttons', 'value' => '❌ رد' ),
			array( 'key_name' => 'btn.approved_by', 'category' => 'buttons', 'value' => '✅ تایید شد توسط {admin}' ),
			array( 'key_name' => 'btn.rejected_by', 'category' => 'buttons', 'value' => '❌ رد شد توسط {admin}' ),
			array( 'key_name' => 'btn.service.show_panel', 'category' => 'buttons', 'value' => '🖥 جزئیات سرویس' ),
			array( 'key_name' => 'btn.wallet.topup', 'category' => 'buttons', 'value' => '➕ شارژ' ),
			array( 'key_name' => 'btn.wallet.history', 'category' => 'buttons', 'value' => '📜 تاریخچه' ),
			array( 'key_name' => 'btn.account.sync', 'category' => 'buttons', 'value' => '🔗 سینک با ربات دیگر' ),
			array( 'key_name' => 'btn.support.contact', 'category' => 'buttons', 'value' => '📞 تماس با پشتیبانی' ),
			array( 'key_name' => 'btn.support.faq', 'category' => 'buttons', 'value' => '❓ سوالات متداول' ),
			array( 'key_name' => 'msg.welcome', 'category' => 'messages', 'value' => "👋 سلام {name}!\n➖➖➖➖➖➖➖➖\nبه ربات VIP ما خوش آمدید.\nبرای شروع از منوی زیر استفاده کنید." ),
			array( 'key_name' => 'msg.approval_wait', 'category' => 'messages', 'value' => "⏳ درخواست شما برای ادمین ارسال شد.\n➖➖➖➖➖➖➖➖\nلطفاً تا تایید ثبت‌نام صبر کنید." ),
			array( 'key_name' => 'msg.approval_rejected', 'category' => 'messages', 'value' => "⛔ متأسفانه ثبت‌نام شما رد شد." ),
			array( 'key_name' => 'msg.approval_approved', 'category' => 'messages', 'value' => "✅ ثبت‌نام شما تایید شد!\n➖➖➖➖➖➖➖➖\nاز منوی زیر ادامه دهید." ),
			array( 'key_name' => 'msg.admin_approval', 'category' => 'messages', 'value' => "متن ارسالی به ادمین برای ثبت‌نام از قالب ثابت در ربات ساخته می‌شود (نام، یوزرنیم، تلگرام، بله، شناسهٔ ربات، تأیید)." ),
			array( 'key_name' => 'msg.admin_find_user_prompt', 'category' => 'messages', 'value' => "🔎 جستجوی کاربر\nشناسهٔ داخلی در ربات (عدد)، chat id تلگرام یا بله، @username، یا نام / بخشی از شمارهٔ تلفن را ارسال کنید." ),
			array( 'key_name' => 'msg.subscription_panel', 'category' => 'messages', 'value' => "📊 وضعیت سرویس\n──────────\n🏷 نام: {remark}\n🆔 شناسه: {sub_id}\n📶 وضعیت: {status_emoji} {status}\n──────────\n⬇️ دانلود: {down_h}\n⬆️ آپلود: {up_h}\n📊 مصرف کل: {used_h}\n🧮 سهمیه: {total_quota}\n🎯 باقی‌مانده: {remained_h}\n──────────\n🕒 آخرین اتصال: {last_online}\n⏳ انقضا: {expiry}" ),
			array( 'key_name' => 'app.v2rayng', 'category' => 'apps', 'value' => 'https://github.com/2dust/v2rayNG/releases' ),
			array( 'key_name' => 'app.shadowrocket', 'category' => 'apps', 'value' => 'https://apps.apple.com/app/shadowrocket/id932747118' ),
			array( 'key_name' => 'app.v2rayn', 'category' => 'apps', 'value' => 'https://github.com/2dust/v2rayN/releases' ),
			array( 'key_name' => 'app.v2rayu', 'category' => 'apps', 'value' => 'https://github.com/yanue/V2rayU/releases' ),
			array( 'key_name' => 'faq.connection', 'category' => 'faq', 'value' => "❓ مشکل اتصال\n➖➖➖➖➖➖➖➖\n📶 اینترنت پایدار داشته باشید.\n🕐 زمان دستگاه را همگام کنید.\n🔗 از لینک اشتراک تازه استفاده کنید." ),
			array( 'key_name' => 'faq.speed', 'category' => 'faq', 'value' => "❓ سرعت پایین\n➖➖➖➖➖➖➖➖\n🖥 سرور دیگری انتخاب کنید.\n📶 از Wi‑Fi بهتر استفاده کنید." ),
			array( 'key_name' => 'faq.install', 'category' => 'faq', 'value' => "❓ راهنمای نصب\n➖➖➖➖➖➖➖➖\n📱 از بخش اپلیکیشن‌ها کلاینت مناسب را دانلود کنید و لینک اشتراک را وارد کنید." ),
			array( 'key_name' => 'faq.l2tp', 'category' => 'faq', 'value' => "❓ اتصال L2TP\n➖➖➖➖➖➖➖➖\n• در ویندوز: Settings → VPN → Add → نوع L2TP/IPsec with pre-shared key.\n• در iOS/اندروید: Settings → VPN → Add VPN → L2TP.\n• اگر وصل نشد اینترنت/فایروال پورت UDP 500/4500/1701 را چک کنید." ),
			array( 'key_name' => 'msg.purchase_failed', 'category' => 'messages', 'value' => "⚠️ در آماده‌سازی سرویس مشکلی پیش آمد.\n➖➖➖➖➖➖➖➖\nلطفاً چند دقیقه دیگر دوباره تلاش کنید یا با پشتیبانی تماس بگیرید." ),
			array( 'key_name' => 'msg.renewed', 'category' => 'messages', 'value' => "✅ تمدید خودکار سرویس «{remark}» انجام شد." ),
			array( 'key_name' => 'msg.renew_failed_balance', 'category' => 'messages', 'value' => "❌ تمدید خودکار ناموفق بود: موجودی کیف پول کافی نیست." ),
			array( 'key_name' => 'msg.panel_unreachable', 'category' => 'messages', 'value' => "⚠️ ارتباط با سرور برقرار نشد. لطفاً بعداً دوباره تلاش کنید." ),
			array( 'key_name' => 'msg.rate_limited', 'category' => 'messages', 'value' => "⏳ تعداد درخواست شما زیاد شد. چند دقیقه بعد دوباره تلاش کنید." ),
			array(
				'key_name' => 'msg.referral_bonus_wallet',
				'category' => 'messages',
				'value'    => "💰 مبلغ {amount_toman} تومان پورسانت معرفی از خرید «{buyer_label}» به کیف پول شما واریز شد.\n{referrer_first}",
			),
			array(
				'key_name' => 'msg.cron_ip_distinct_warn',
				'category' => 'messages',
				'value'    => "⚠️ سرویس «{remark}»\n🧒 یعنی چی؟ تعداد آدرس/IP متفاوتی که پنل برای این اشتراک ثبت کرده بالا رفته است.\n📌 الان حدود {n_ip} IP متمایز ثبت شده است.\n📌 سقف اسلات این اشتراک {lim} است (آستانهٔ هشدار حداقل {need} IP).\n✋ اگر لازم دارید از منوی همان سرویس «افزایش کاربر» را بزنید.",
			),
			array(
				'key_name' => 'btn.admin.delete_service_soft',
				'category' => 'buttons',
				'value'    => '🗑 حذف از لیست ربات (غیرفعال‌سازی)',
			),
			array(
				'key_name' => 'msg.no_active_services',
				'category' => 'messages',
				'value'    => '🧰 سرویس فعالی ندارید.',
			),
		);
	}

	/**
	 * Map text key => default value (from default_text_rows).
	 *
	 * @return array<string, string>
	 */
	public static function default_text_values_map() {
		$out = array();
		foreach ( self::default_text_rows() as $row ) {
			if ( ! empty( $row['key_name'] ) ) {
				$out[ (string) $row['key_name'] ] = (string) ( $row['value'] ?? '' );
			}
		}
		return $out;
	}

	/**
	 * Default row (key, category, value) for a text key, or null.
	 *
	 * @param string $key_name Key.
	 * @return array{key_name:string,category:string,value:string}|null
	 */
	public static function default_row_for_text_key( $key_name ) {
		$k = trim( (string) $key_name );
		if ( '' === $k ) {
			return null;
		}
		foreach ( self::default_text_rows() as $row ) {
			if ( isset( $row['key_name'] ) && (string) $row['key_name'] === $k ) {
				return array(
					'key_name' => $k,
					'category' => (string) ( $row['category'] ?? 'general' ),
					'value'    => (string) ( $row['value'] ?? '' ),
				);
			}
		}
		return null;
	}
}
