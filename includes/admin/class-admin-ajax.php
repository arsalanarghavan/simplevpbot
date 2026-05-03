<?php
/**
 * Admin AJAX (test panel, set webhook, backup now).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Admin_Ajax
 */
class SimpleVPBot_Admin_Ajax {

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'wp_ajax_simplevpbot_test_panel', array( __CLASS__, 'test_panel' ) );
		add_action( 'wp_ajax_simplevpbot_test_telegram', array( __CLASS__, 'test_telegram' ) );
		add_action( 'wp_ajax_simplevpbot_set_webhook_tg', array( __CLASS__, 'set_webhook_tg' ) );
		add_action( 'wp_ajax_simplevpbot_set_webhook_bale', array( __CLASS__, 'set_webhook_bale' ) );
		add_action( 'wp_ajax_simplevpbot_backup_now', array( __CLASS__, 'backup_now' ) );
		add_action( 'wp_ajax_simplevpbot_restore_backup', array( __CLASS__, 'restore_backup' ) );
		add_action( 'wp_ajax_simplevpbot_inbounds_list', array( __CLASS__, 'inbounds_list' ) );
		add_action( 'wp_ajax_simplevpbot_inbound_clients', array( __CLASS__, 'inbound_clients' ) );
		add_action( 'wp_ajax_simplevpbot_inbound_link', array( __CLASS__, 'inbound_link' ) );
		add_action( 'wp_ajax_simplevpbot_inbound_autolink', array( __CLASS__, 'inbound_autolink' ) );
		add_action( 'wp_ajax_simplevpbot_receipt_image', array( __CLASS__, 'receipt_image' ) );
		add_action( 'wp_ajax_simplevpbot_l2tp_test', array( __CLASS__, 'l2tp_test' ) );
		add_action( 'wp_ajax_simplevpbot_receipt_retry_provision', array( __CLASS__, 'receipt_retry_provision' ) );
		add_action( 'wp_ajax_simplevpbot_service_transfer', array( __CLASS__, 'service_transfer' ) );
		add_action( 'wp_ajax_simplevpbot_user_merge_preview', array( __CLASS__, 'user_merge_preview' ) );
		add_action( 'wp_ajax_simplevpbot_user_merge', array( __CLASS__, 'user_merge' ) );
		add_action( 'wp_ajax_simplevpbot_traffic_cap_repair_db', array( __CLASS__, 'traffic_cap_repair_db' ) );
	}

	/**
	 * Test SSH connection to an L2TP server row.
	 */
	public static function l2tp_test() {
		self::verify();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$r  = SimpleVPBot_Service_Admin_Ops::l2tp_test( $id );
		if ( empty( $r['ok'] ) ) {
			wp_send_json_error( array( 'message' => (string) ( $r['message'] ?? '' ) ) );
		}
		wp_send_json_success( isset( $r['data'] ) ? $r['data'] : array() );
	}

	/**
	 * Verify nonce.
	 */
	private static function verify() {
		check_ajax_referer( 'simplevpbot_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'forbidden', 403 );
		}
	}

	/**
	 * Test 3x-ui login + status with detailed diagnostics + autodetect for API base path.
	 */
	public static function test_panel() {
		self::verify();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$panel_id = isset( $_POST['panel_id'] ) ? (int) $_POST['panel_id'] : 0;
		$r        = SimpleVPBot_Service_Admin_Ops::test_panel( $panel_id );
		if ( empty( $r['ok'] ) ) {
			$err = array( 'message' => (string) ( $r['message'] ?? '' ) );
			if ( ! empty( $r['data'] ) && is_array( $r['data'] ) ) {
				$err = array_merge( $err, $r['data'] );
			}
			wp_send_json_error( $err );
		}
		wp_send_json_success( isset( $r['data'] ) ? $r['data'] : array() );
	}

	/**
	 * Telegram getMe.
	 */
	public static function test_telegram() {
		self::verify();
		$r = SimpleVPBot_Service_Admin_Ops::test_telegram();
		if ( empty( $r['ok'] ) ) {
			wp_send_json_error( array( 'message' => (string) ( $r['message'] ?? '' ), 'response' => isset( $r['data'] ) ? $r['data'] : null ) );
		}
		wp_send_json_success( isset( $r['data'] ) ? $r['data'] : array() );
	}

	/**
	 * Set Telegram webhook.
	 */
	public static function set_webhook_tg() {
		self::verify();
		$r = SimpleVPBot_Service_Admin_Ops::set_webhook_telegram();
		if ( empty( $r['ok'] ) ) {
			wp_send_json_error( array( 'message' => (string) ( $r['message'] ?? '' ) ) );
		}
		wp_send_json_success( isset( $r['data'] ) ? $r['data'] : array() );
	}

	/**
	 * Set Bale webhook.
	 */
	public static function set_webhook_bale() {
		self::verify();
		$r = SimpleVPBot_Service_Admin_Ops::set_webhook_bale();
		if ( empty( $r['ok'] ) ) {
			wp_send_json_error( array( 'message' => (string) ( $r['message'] ?? '' ) ) );
		}
		wp_send_json_success( isset( $r['data'] ) ? $r['data'] : array() );
	}

	/**
	 * Run backup job.
	 */
	public static function backup_now() {
		self::verify();
		$r = SimpleVPBot_Service_Admin_Ops::backup_now();
		if ( empty( $r['ok'] ) ) {
			wp_send_json_error( array( 'message' => (string) ( $r['message'] ?? '' ) ) );
		}
		wp_send_json_success( isset( $r['data'] ) ? $r['data'] : array() );
	}

	/**
	 * Restore plugin tables + settings from uploaded SimpleVPBot backup zip.
	 */
	public static function restore_backup() {
		self::verify();
		if ( empty( $_POST['confirm'] ) || '1' !== (string) wp_unslash( $_POST['confirm'] ) ) { // phpcs:ignore
			wp_send_json_error( array( 'message' => __( 'برای ریستور باید کادر تایید را بزنید.', 'simplevpbot' ) ) );
		}
		if ( empty( $_FILES['file'] ) || ! isset( $_FILES['file']['tmp_name'] ) ) { // phpcs:ignore
			wp_send_json_error( array( 'message' => __( 'فایلی ارسال نشده است.', 'simplevpbot' ) ) );
		}
		if ( UPLOAD_ERR_OK !== (int) $_FILES['file']['error'] ) { // phpcs:ignore
			wp_send_json_error( array( 'message' => __( 'خطای آپلود فایل.', 'simplevpbot' ) ) );
		}
		$name = isset( $_FILES['file']['name'] ) ? (string) $_FILES['file']['name'] : '';
		if ( 'zip' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
			wp_send_json_error( array( 'message' => __( 'فقط فایل .zip مجاز است.', 'simplevpbot' ) ) );
		}
		$tmp  = (string) $_FILES['file']['tmp_name']; // phpcs:ignore
		$dest = SimpleVPBot_Backup_Export::base_tmp_dir() . 'restore-' . wp_generate_password( 12, false, false ) . '.zip';
		if ( ! @move_uploaded_file( $tmp, $dest ) ) { // phpcs:ignore
			wp_send_json_error( array( 'message' => __( 'ذخیرهٔ موقت فایل ناموفق بود.', 'simplevpbot' ) ) );
		}
		$r = SimpleVPBot_Service_Admin_Ops::restore_from_zip_path( $dest, true );
		@unlink( $dest ); // phpcs:ignore
		if ( empty( $r['ok'] ) ) {
			wp_send_json_error( array( 'message' => (string) ( $r['message'] ?? '' ) ) );
		}
		wp_send_json_success( array( 'message' => (string) ( $r['message'] ?? __( 'ریستور انجام شد.', 'simplevpbot' ) ) ) );
	}

	/**
	 * Inbound list (3x-ui).
	 */
	public static function inbounds_list() {
		self::verify();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$panel_id = isset( $_POST['panel_id'] ) ? (int) $_POST['panel_id'] : 0;
		$r        = SimpleVPBot_Service_Admin_Ops::inbounds_list( $panel_id );
		if ( empty( $r['ok'] ) ) {
			wp_send_json_error( array( 'message' => (string) ( $r['message'] ?? '' ) ) );
		}
		wp_send_json_success( isset( $r['data'] ) ? $r['data'] : array() );
	}

	/**
	 * Clients in one inbound.
	 */
	public static function inbound_clients() {
		self::verify();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$iid = isset( $_POST['inbound_id'] ) ? (int) $_POST['inbound_id'] : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$pid = isset( $_POST['panel_id'] ) ? (int) $_POST['panel_id'] : 1;
		if ( $pid < 0 ) {
			$pid = 0;
		}
		$r   = SimpleVPBot_Service_Admin_Ops::inbound_clients( $iid, $pid );
		if ( empty( $r['ok'] ) ) {
			wp_send_json_error( array( 'message' => (string) ( $r['message'] ?? '' ) ) );
		}
		wp_send_json_success( isset( $r['data'] ) ? $r['data'] : array() );
	}

	/**
	 * Human label for a user row (name / username / ids).
	 *
	 * @param object $u User row.
	 * @return string
	 */
	private static function user_label_for_admin( $u ) {
		return SimpleVPBot_Model_User::label( $u );
	}

	/**
	 * Link client to svp user.
	 */
	public static function inbound_link() {
		self::verify();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$iid   = isset( $_POST['inbound_id'] ) ? (int) $_POST['inbound_id'] : 0;
		$uid   = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
		$email = isset( $_POST['email'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['email'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$pid   = isset( $_POST['panel_id'] ) ? (int) $_POST['panel_id'] : 1;
		if ( $pid < 0 ) {
			$pid = 0;
		}
		$r     = SimpleVPBot_Service_Admin_Ops::inbound_link( $iid, $email, $uid, $pid );
		if ( empty( $r['ok'] ) ) {
			wp_send_json_error( array( 'message' => (string) ( $r['message'] ?? '' ) ) );
		}
		wp_send_json_success( isset( $r['data'] ) ? $r['data'] : array() );
	}

	/**
	 * Auto-link inbound clients to users by identifiers in names/comments.
	 */
	public static function inbound_autolink() {
		self::verify();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$iid = isset( $_POST['inbound_id'] ) ? (int) $_POST['inbound_id'] : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$pid = isset( $_POST['panel_id'] ) ? (int) $_POST['panel_id'] : 1;
		if ( $pid < 0 ) {
			$pid = 0;
		}
		$r   = SimpleVPBot_Service_Admin_Ops::inbound_autolink( $iid, $pid );
		if ( empty( $r['ok'] ) ) {
			wp_send_json_error( array( 'message' => (string) ( $r['message'] ?? '' ) ) );
		}
		wp_send_json_success( isset( $r['data'] ) ? $r['data'] : array() );
	}

	/**
	 * Cap oversized svp_services.total_traffic rows (mis-scaled bug recovery).
	 */
	public static function traffic_cap_repair_db() {
		self::verify();
		$n = SimpleVPBot_Inbound_Linker::repair_cap_total_traffic_in_database();
		wp_send_json_success( array( 'updated_rows' => $n ) );
	}

	/**
	 * Retry service provisioning for a receipt whose transaction has no service yet.
	 */
	public static function receipt_retry_provision() {
		self::verify();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$rid   = isset( $_POST['rid'] ) ? (int) $_POST['rid'] : 0;
		$label = (string) wp_get_current_user()->user_login;
		$r     = SimpleVPBot_Service_Admin_Ops::receipt_retry_provision( $rid, $label );
		if ( empty( $r['ok'] ) ) {
			wp_send_json_error( array( 'message' => (string) ( $r['message'] ?? '' ) ) );
		}
		wp_send_json_success( isset( $r['data'] ) ? $r['data'] : array() );
	}

	/**
	 * Admin-initiated service ownership transfer.
	 */
	public static function service_transfer() {
		self::verify();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$sid = isset( $_POST['service_id'] ) ? (int) $_POST['service_id'] : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$target_raw = isset( $_POST['target'] ) ? trim( (string) wp_unslash( $_POST['target'] ) ) : '';
		$label      = (string) wp_get_current_user()->user_login;
		$r          = SimpleVPBot_Service_Admin_Ops::service_transfer( $sid, $target_raw, $label );
		if ( empty( $r['ok'] ) ) {
			wp_send_json_error( array( 'message' => (string) ( $r['message'] ?? '' ) ) );
		}
		wp_send_json_success( isset( $r['data'] ) ? $r['data'] : array() );
	}

	/**
	 * Admin merge preview: counts of linked rows.
	 */
	public static function user_merge_preview() {
		self::verify();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$keep = isset( $_POST['keep_id'] ) ? (int) $_POST['keep_id'] : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$drop = isset( $_POST['drop_id'] ) ? (int) $_POST['drop_id'] : 0;
		$r    = SimpleVPBot_Service_Admin_Ops::user_merge_preview( $keep, $drop );
		if ( empty( $r['ok'] ) ) {
			wp_send_json_error( array( 'message' => (string) ( $r['message'] ?? '' ) ) );
		}
		wp_send_json_success( isset( $r['data'] ) ? $r['data'] : array() );
	}

	/**
	 * Admin merge: execute merge_users (moves related rows + deletes drop).
	 */
	public static function user_merge() {
		self::verify();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$keep = isset( $_POST['keep_id'] ) ? (int) $_POST['keep_id'] : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$drop = isset( $_POST['drop_id'] ) ? (int) $_POST['drop_id'] : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$confirm = ! empty( $_POST['confirm'] );
		$r       = SimpleVPBot_Service_Admin_Ops::user_merge( $keep, $drop, $confirm );
		if ( empty( $r['ok'] ) ) {
			wp_send_json_error( array( 'message' => (string) ( $r['message'] ?? '' ) ) );
		}
		wp_send_json_success( isset( $r['data'] ) ? $r['data'] : array() );
	}

	/**
	 * Proxy receipt image (Telegram file) for admin table.
	 */
	public static function receipt_image() {
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			status_header( 403 );
			exit;
		}
		$rid   = isset( $_GET['rid'] ) ? (int) $_GET['rid'] : 0; // phpcs:ignore
		$nonce = isset( $_GET['nonce'] ) ? (string) wp_unslash( $_GET['nonce'] ) : ''; // phpcs:ignore
		if ( $rid < 1 || ! wp_verify_nonce( $nonce, 'svp_recimg_' . $rid ) ) {
			status_header( 403 );
			exit;
		}
		$rec = SimpleVPBot_Model_Receipt::find( $rid );
		if ( ! $rec || empty( $rec->tg_file_id ) ) {
			status_header( 404 );
			exit;
		}
		$tok = (string) SimpleVPBot_Settings::get( 'telegram_token', '' );
		if ( '' === $tok ) {
			status_header( 404 );
			exit;
		}
		$tg   = new SimpleVPBot_Telegram_Client( $tok );
		$gf   = $tg->get_file( array( 'file_id' => (string) $rec->tg_file_id ) );
		$path = ( is_array( $gf ) && ! empty( $gf['result']['file_path'] ) ) ? (string) $gf['result']['file_path'] : '';
		if ( '' === $path ) {
			status_header( 404 );
			exit;
		}
		$url  = 'https://api.telegram.org/file/bot' . rawurlencode( $tok ) . '/' . $path;
		$resp = wp_remote_get( $url, array( 'timeout' => 45, 'redirection' => 2 ) );
		if ( is_wp_error( $resp ) || (int) wp_remote_retrieve_response_code( $resp ) !== 200 ) {
			status_header( 502 );
			exit;
		}
		$body = (string) wp_remote_retrieve_body( $resp );
		$ct   = (string) wp_remote_retrieve_header( $resp, 'content-type' );
		if ( $ct && false === strpos( $ct, 'text' ) ) {
			header( 'Content-Type: ' . $ct );
		} else {
			header( 'Content-Type: image/jpeg' );
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $body;
		exit;
	}

	/**
	 * Portal admin JSON (signed; no WP login).
	 */
	public static function init_portal_routes() {
		add_action( 'wp_ajax_simplevpbot_portal_admin', array( __CLASS__, 'portal_admin' ) );
		add_action( 'wp_ajax_nopriv_simplevpbot_portal_admin', array( __CLASS__, 'portal_admin' ) );
		add_action( 'wp_ajax_simplevpbot_portal_tg_avatar', array( __CLASS__, 'portal_tg_avatar' ) );
		add_action( 'wp_ajax_nopriv_simplevpbot_portal_tg_avatar', array( __CLASS__, 'portal_tg_avatar' ) );
	}

	/**
	 * Label for membership decisions from signed web admin.
	 *
	 * @param object $portal_admin_user svp_users row (verified admin).
	 * @return string
	 */
	private static function portal_membership_decided_by( $portal_admin_user ) {
		return 'web:#' . (int) ( $portal_admin_user->id ?? 0 );
	}

	/**
	 * User row for portal JSON (no state blobs).
	 *
	 * @param object|null $u User row.
	 * @return array<string, mixed>|null
	 */
	private static function portal_user_public_detail( $u ) {
		if ( ! $u ) {
			return null;
		}
		return array(
			'id'           => (int) $u->id,
			'tg_user_id'   => null === $u->tg_user_id ? null : (int) $u->tg_user_id,
			'bale_user_id' => null === $u->bale_user_id ? null : (int) $u->bale_user_id,
			'first_name'   => (string) ( $u->first_name ?? '' ),
			'last_name'    => (string) ( $u->last_name ?? '' ),
			'username'     => (string) ( $u->username ?? '' ),
			'phone'        => (string) ( $u->phone ?? '' ),
			'role'         => (string) ( $u->role ?? '' ),
			'balance'      => (string) ( $u->balance ?? '0' ),
			'status'       => (string) ( $u->status ?? '' ),
			'approved_by'  => null === ( $u->approved_by ?? null ) ? null : (string) $u->approved_by,
			'approved_at'  => null === ( $u->approved_at ?? null ) ? null : (string) $u->approved_at,
			'admin_mode'   => (int) ( $u->admin_mode ?? 0 ),
			'invited_by'   => null === ( $u->invited_by ?? null ) ? null : (int) $u->invited_by,
			'created_at'   => (string) ( $u->created_at ?? '' ),
			'label'        => SimpleVPBot_Model_User::label( $u ),
		);
	}

	/**
	 * Paged membership queue slice.
	 *
	 * @param string $status pending|approved|rejected.
	 * @param int    $offset Offset.
	 * @return array<string, mixed>
	 */
	private static function portal_membership_queue_page( $status, $offset ) {
		$st = sanitize_key( (string) $status );
		if ( ! in_array( $st, array( 'pending', 'approved', 'rejected' ), true ) ) {
			return array(
				'status'   => $st,
				'offset'   => 0,
				'limit'    => 5,
				'total'    => 0,
				'items'    => array(),
				'has_prev' => false,
				'has_next' => false,
			);
		}
		$off   = max( 0, (int) $offset );
		$limit = 5;
		$total = SimpleVPBot_Model_User::count_status( $st );
		$rows  = SimpleVPBot_Model_User::list_by_status_paged( $st, $off, $limit );
		$items = array();
		foreach ( (array) $rows as $r ) {
			$items[] = array(
				'id'         => (int) $r->id,
				'label'      => SimpleVPBot_Model_User::label( $r ),
				'status'     => (string) ( $r->status ?? '' ),
				'created_at' => (string) ( $r->created_at ?? '' ),
			);
		}
		return array(
			'status'   => $st,
			'offset'   => $off,
			'limit'    => $limit,
			'total'    => $total,
			'items'    => $items,
			'has_prev' => $off > 0,
			'has_next' => ( $off + $limit ) < $total,
		);
	}

	/**
	 * Stream Telegram profile photo for a user (signed portal admin GET).
	 */
	public static function portal_tg_avatar() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$u = isset( $_GET['svp_u'] ) ? (int) $_GET['svp_u'] : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$e = isset( $_GET['svp_e'] ) ? (int) $_GET['svp_e'] : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$s = isset( $_GET['svp_s'] ) ? (string) wp_unslash( $_GET['svp_s'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tid = isset( $_GET['target_uid'] ) ? (int) $_GET['target_uid'] : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$avn = isset( $_GET['avnonce'] ) ? (string) wp_unslash( $_GET['avnonce'] ) : '';
		$admin = SimpleVPBot_Portal_Link::verify_admin_signature( $u, $e, $s );
		if ( ! $admin || $tid < 1 ) {
			status_header( 403 );
			exit;
		}
		if ( ! wp_verify_nonce( $avn, 'svp_portal_tgav_' . (int) $admin->id . '_' . $tid ) ) {
			status_header( 403 );
			exit;
		}
		$row = SimpleVPBot_Model_User::find( $tid );
		if ( ! $row || ! (int) ( $row->tg_user_id ?? 0 ) ) {
			status_header( 404 );
			exit;
		}
		$tmp = SimpleVPBot_Bot_Runtime::telegram_user_profile_photo_temp( (int) $row->tg_user_id );
		if ( '' === $tmp || ! is_readable( $tmp ) ) {
			status_header( 404 );
			exit;
		}
		header( 'Content-Type: image/jpeg' );
		header( 'Cache-Control: private, max-age=300' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
		readfile( $tmp );
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		exit;
	}

	/**
	 * AJAX for signed web admin shell.
	 */
	public static function portal_admin() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$u = isset( $_POST['svp_u'] ) ? (int) $_POST['svp_u'] : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$e = isset( $_POST['svp_e'] ) ? (int) $_POST['svp_e'] : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$s = isset( $_POST['svp_s'] ) ? (string) wp_unslash( $_POST['svp_s'] ) : '';
		$user = SimpleVPBot_Portal_Link::verify_admin_signature( $u, $e, $s );
		if ( ! $user ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		check_ajax_referer( 'svp_portal_admin_' . (int) $user->id, 'nonce' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$op = isset( $_POST['op'] ) ? sanitize_key( (string) wp_unslash( $_POST['op'] ) ) : '';
		if ( 'stats' === $op ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$day = isset( $_POST['day'] ) ? (int) $_POST['day'] : 0;
			$day = max( 0, min( 7, $day ) );
			if ( class_exists( 'SimpleVPBot_Admin_Dashboard_Stats' ) ) {
				$payload = SimpleVPBot_Admin_Dashboard_Stats::build_payload( $day );
				$payload['text'] = SimpleVPBot_Admin_Dashboard_Stats::format_text( $day );
				wp_send_json_success( $payload );
			}
			wp_send_json_error( array( 'message' => 'stats_unavailable' ), 500 );
		}
		if ( 'membership_pending_page' === $op ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$off = isset( $_POST['offset'] ) ? (int) $_POST['offset'] : 0;
			wp_send_json_success( self::portal_membership_queue_page( 'pending', $off ) );
		}
		if ( 'membership_approved_page' === $op ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$off = isset( $_POST['offset'] ) ? (int) $_POST['offset'] : 0;
			wp_send_json_success( self::portal_membership_queue_page( 'approved', $off ) );
		}
		if ( 'membership_rejected_page' === $op ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$off = isset( $_POST['offset'] ) ? (int) $_POST['offset'] : 0;
			wp_send_json_success( self::portal_membership_queue_page( 'rejected', $off ) );
		}
		if ( 'membership_detail' === $op ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$tid = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
			$u   = $tid > 0 ? SimpleVPBot_Model_User::find( $tid ) : null;
			if ( ! $u ) {
				wp_send_json_error( array( 'message' => 'no_user' ), 404 );
			}
			$detail = self::portal_user_public_detail( $u );
			$tg     = (int) ( $u->tg_user_id ?? 0 );
			if ( $tg < 1 && (int) ( $u->bale_user_id ?? 0 ) > 0 ) {
				$detail['bale_avatar_note'] = __( 'در بله تصویر پروفایل از طریق این پنل در دسترس نیست.', 'simplevpbot' );
			}
			if ( $tg > 0 ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$e = isset( $_POST['svp_e'] ) ? (int) $_POST['svp_e'] : 0;
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$s = isset( $_POST['svp_s'] ) ? (string) wp_unslash( $_POST['svp_s'] ) : '';
				$au = (int) $user->id;
				$detail['avatar_url'] = add_query_arg(
					array(
						'action'     => 'simplevpbot_portal_tg_avatar',
						'svp_u'      => (string) $au,
						'svp_e'      => (string) $e,
						'svp_s'      => $s,
						'target_uid' => (string) $tid,
						'avnonce'    => wp_create_nonce( 'svp_portal_tgav_' . $au . '_' . $tid ),
					),
					admin_url( 'admin-ajax.php' )
				);
			}
			wp_send_json_success( $detail );
		}
		if ( 'membership_approve' === $op ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$tid = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
			$u   = $tid > 0 ? SimpleVPBot_Model_User::find( $tid ) : null;
			if ( ! $u || 'pending' !== (string) $u->status ) {
				wp_send_json_error( array( 'message' => 'not_pending' ), 400 );
			}
			$r = SimpleVPBot_User_Membership::approve( $tid, self::portal_membership_decided_by( $user ) );
			wp_send_json_success( array( 'result' => $r ) );
		}
		if ( 'membership_reject' === $op ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$tid = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
			$u   = $tid > 0 ? SimpleVPBot_Model_User::find( $tid ) : null;
			if ( ! $u || 'pending' !== (string) $u->status ) {
				wp_send_json_error( array( 'message' => 'not_pending' ), 400 );
			}
			$r = SimpleVPBot_User_Membership::reject( $tid, self::portal_membership_decided_by( $user ) );
			wp_send_json_success( array( 'result' => $r ) );
		}
		if ( 'membership_reopen' === $op ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$tid = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
			$r   = SimpleVPBot_User_Membership::reopen_rejected_to_pending( $tid );
			if ( empty( $r['ok'] ) ) {
				wp_send_json_error( array( 'message' => (string) ( $r['reason'] ?? 'failed' ) ), 400 );
			}
			wp_send_json_success( array( 'result' => $r ) );
		}
		if ( 'create_service' === $op && class_exists( 'SimpleVPBot_Admin_User_Ops' ) ) {
			$tuid = isset( $_POST['target_uid'] ) ? (int) $_POST['target_uid'] : 0;
			$pid  = isset( $_POST['plan_id'] ) ? (int) $_POST['plan_id'] : 0;
			$vol  = isset( $_POST['volume_gb'] ) ? (int) $_POST['volume_gb'] : 0;
			$mode = isset( $_POST['mode'] ) ? sanitize_key( (string) wp_unslash( $_POST['mode'] ) ) : '';
			$vol  = $vol > 0 ? $vol : null;
			$r    = SimpleVPBot_Admin_User_Ops::admin_create_service( $tuid, $pid, $vol, $mode );
			if ( empty( $r['ok'] ) ) {
				wp_send_json_error( $r );
			}
			wp_send_json_success( $r );
		}
		if ( 'renew_service' === $op && class_exists( 'SimpleVPBot_Admin_User_Ops' ) ) {
			$sid  = isset( $_POST['service_id'] ) ? (int) $_POST['service_id'] : 0;
			$mode = isset( $_POST['mode'] ) ? sanitize_key( (string) wp_unslash( $_POST['mode'] ) ) : '';
			$r    = SimpleVPBot_Admin_User_Ops::admin_renew_service( $sid, $mode );
			if ( empty( $r['ok'] ) ) {
				wp_send_json_error( $r );
			}
			wp_send_json_success( $r );
		}
		if ( 'add_volume' === $op && class_exists( 'SimpleVPBot_Admin_User_Ops' ) ) {
			$sid  = isset( $_POST['service_id'] ) ? (int) $_POST['service_id'] : 0;
			$gb   = isset( $_POST['extra_gb'] ) ? (int) $_POST['extra_gb'] : 1;
			$mode = isset( $_POST['mode'] ) ? sanitize_key( (string) wp_unslash( $_POST['mode'] ) ) : '';
			$r    = SimpleVPBot_Admin_User_Ops::admin_add_volume( $sid, $gb, $mode );
			if ( empty( $r['ok'] ) ) {
				wp_send_json_error( $r );
			}
			wp_send_json_success( $r );
		}
		if ( 'bulk_days' === $op && class_exists( 'SimpleVPBot_Admin_User_Ops' ) ) {
			if ( empty( $_POST['bulk_ack'] ) || '1' !== (string) wp_unslash( $_POST['bulk_ack'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				wp_send_json_error( array( 'message' => 'confirm_required' ), 400 );
			}
			$d = isset( $_POST['days'] ) ? (int) $_POST['days'] : 0;
			wp_send_json_success( SimpleVPBot_Admin_User_Ops::bulk_extend_days( $d, true, 200 ) );
		}
		if ( 'bulk_gb' === $op && class_exists( 'SimpleVPBot_Admin_User_Ops' ) ) {
			if ( empty( $_POST['bulk_ack'] ) || '1' !== (string) wp_unslash( $_POST['bulk_ack'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				wp_send_json_error( array( 'message' => 'confirm_required' ), 400 );
			}
			$g = isset( $_POST['gb'] ) ? (int) $_POST['gb'] : 0;
			wp_send_json_success( SimpleVPBot_Admin_User_Ops::bulk_add_volume( $g, 200 ) );
		}
		if ( 'save_crypto' === $op ) {
			$all = SimpleVPBot_Settings::all();
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$all['crypto_nowpayments_api_key'] = sanitize_text_field( (string) wp_unslash( $_POST['api_key'] ?? '' ) );
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$all['crypto_nowpayments_ipn_secret'] = sanitize_text_field( (string) wp_unslash( $_POST['ipn_secret'] ?? '' ) );
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$all['crypto_nowpayments_pay_currency'] = sanitize_key( (string) wp_unslash( $_POST['pay_currency'] ?? 'usdttrc20' ) );
			SimpleVPBot_Settings::update( $all );
			SimpleVPBot_Texts::clear_cache();
			wp_send_json_success( array( 'saved' => true ) );
		}
		if ( 'rotate_ipn_path' === $op ) {
			$all = SimpleVPBot_Settings::all();
			$all['crypto_ipn_path_secret']        = wp_generate_password( 32, false, false );
			SimpleVPBot_Settings::update( $all );
			SimpleVPBot_Texts::clear_cache();
			wp_send_json_success( array( 'ipn_url' => SimpleVPBot_Crypto_Payment::ipn_callback_url() ) );
		}
		if ( 'referral_get' === $op ) {
			$s = SimpleVPBot_Settings::all();
			wp_send_json_success(
				array(
					'referral_enabled'                  => ! empty( $s['referral_enabled'] ),
					'referral_percent'                  => (float) ( $s['referral_percent'] ?? 0 ),
					'referral_min_payout_base'          => (float) ( $s['referral_min_payout_base'] ?? 0 ),
					'referral_example_base_toman'      => (float) ( $s['referral_example_base_toman'] ?? 170000 ),
					'referral_example_invite_count'     => (int) ( $s['referral_example_invite_count'] ?? 10 ),
					'referral_require_approved_referrer' => ! empty( $s['referral_require_approved_referrer'] ),
					'telegram_bot_username'            => (string) ( $s['telegram_bot_username'] ?? '' ),
					'bale_bot_username'                 => (string) ( $s['bale_bot_username'] ?? '' ),
				)
			);
		}
		if ( 'referral_save' === $op ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$post = array(
				'referral_enabled'                  => ! empty( $_POST['referral_enabled'] ),
				'referral_percent'                  => (float) str_replace( ',', '.', (string) wp_unslash( $_POST['referral_percent'] ?? '0' ) ),
				'referral_min_payout_base'          => (float) str_replace( ',', '.', (string) wp_unslash( $_POST['referral_min_payout_base'] ?? '0' ) ),
				'referral_example_base_toman'      => (float) str_replace( ',', '.', (string) wp_unslash( $_POST['referral_example_base_toman'] ?? '170000' ) ),
				'referral_example_invite_count'     => (int) ( $_POST['referral_example_invite_count'] ?? 10 ),
				'referral_require_approved_referrer' => ! empty( $_POST['referral_require_approved_referrer'] ),
				'telegram_bot_username'            => sanitize_text_field( (string) wp_unslash( $_POST['telegram_bot_username'] ?? '' ) ),
				'bale_bot_username'                 => sanitize_text_field( (string) wp_unslash( $_POST['bale_bot_username'] ?? '' ) ),
			);
			SimpleVPBot_Admin_Actions::apply_settings_tab( 'referral', $post );
			SimpleVPBot_Texts::clear_cache();
			wp_send_json_success( array( 'saved' => true ) );
		}
		if ( 'discount_list' === $op ) {
			$items = array();
			foreach ( SimpleVPBot_Model_Discount_Code::all_ordered() as $r ) {
				$items[] = array(
					'id'                   => (int) $r->id,
					'code'                 => (string) $r->code,
					'active'               => (int) $r->active,
					'discount_type'        => (string) $r->discount_type,
					'discount_value'       => (float) $r->discount_value,
					'uses_count'           => (int) $r->uses_count,
					'max_uses'             => null === $r->max_uses ? null : (int) $r->max_uses,
				);
			}
			wp_send_json_success( array( 'items' => $items ) );
		}
		if ( 'discount_delete' === $op ) {
			$did = isset( $_POST['discount_id'] ) ? (int) $_POST['discount_id'] : 0; // phpcs:ignore
			if ( $did < 1 ) {
				wp_send_json_error( array( 'message' => 'bad_id' ), 400 );
			}
			SimpleVPBot_Model_Discount_Code::delete( $did );
			wp_send_json_success( array( 'deleted' => $did ) );
		}
		wp_send_json_error( array( 'message' => 'unknown_op' ), 400 );
	}
}
