<?php
/**
 * L2TP user lifecycle: create/delete/rotate/refresh_usage via SSH to the L2TP VPS.
 *
 * Edits /etc/ppp/chap-secrets (tab-separated: user server secret ip). Reloads xl2tpd.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_L2TP_Provisioner
 */
class SimpleVPBot_L2TP_Provisioner {

	/**
	 * Create new user on the plan's L2TP server and a matching svp_services row.
	 *
	 * @param int $user_id SVP user id.
	 * @param int $plan_id Plan id.
	 * @return int|false Service id or false.
	 */
	public static function create_user( $user_id, $plan_id ) {
		$plan = SimpleVPBot_Model_Plan::find( $plan_id );
		if ( ! $plan || ! (int) $plan->active ) {
			return false;
		}
		$sid = isset( $plan->l2tp_server_id ) ? (int) $plan->l2tp_server_id : 0;
		if ( $sid < 1 ) {
			SimpleVPBot_Logger::error( 'l2tp: plan has no l2tp_server_id', array( 'plan' => (int) $plan->id ) );
			return false;
		}
		$srv_row = SimpleVPBot_Model_L2TP_Server::find( $sid );
		if ( ! $srv_row || ! (int) $srv_row->active ) {
			SimpleVPBot_Logger::error( 'l2tp: server missing or inactive', array( 'sid' => $sid ) );
			return false;
		}
		$srv  = SimpleVPBot_Model_L2TP_Server::decrypted( $srv_row );
		$ssh  = new SimpleVPBot_SSH_Client( $srv );
		if ( ! $ssh->connect() ) {
			SimpleVPBot_Logger::error( 'l2tp: ssh connect failed', array( 'sid' => $sid, 'err' => $ssh->last_error ) );
			return false;
		}

		$username = self::gen_username( (int) $user_id );
		$password = self::gen_password();
		$chap     = self::chap_path( $srv );
		$reload   = self::reload_cmd( $srv );

		// Append chap line: user\t*\tpass\t* ; use printf to avoid shell escaping drama.
		$cmd_add = sprintf(
			"printf '%%s\\t*\\t%%s\\t*\\n' %s %s | sudo /usr/bin/tee -a %s >/dev/null && %s",
			escapeshellarg( $username ),
			escapeshellarg( $password ),
			escapeshellarg( $chap ),
			$reload
		);
		$res = $ssh->exec( $cmd_add );
		if ( empty( $res['ok'] ) ) {
			SimpleVPBot_Logger::error( 'l2tp: add user failed', array( 'stderr' => $res['stderr'] ?? '' ) );
			$ssh->disconnect();
			return false;
		}

		$total_gb = (int) $plan->traffic_gb;
		if ( SimpleVPBot_Model_Plan::is_per_gb( $plan ) ) {
			// Per-GB plans for L2TP are not supported in MVP; use min if caller didn't pass volume.
			$total_gb = (int) ( $plan->traffic_gb_min ?? 0 );
		}
		$total_bytes = $total_gb > 0 ? $total_gb * 1073741824 : 0;
		$total_bytes = SimpleVPBot_Inbound_Linker::cap_traffic_bytes( (int) $total_bytes );
		$expires_at  = (int) $plan->duration_days > 0
			? gmdate( 'Y-m-d H:i:s', time() + (int) $plan->duration_days * DAY_IN_SECONDS )
			: null;

		$service_id = SimpleVPBot_Model_Service::insert(
			array(
				'user_id'           => (int) $user_id,
				'panel_id'          => 0,
				'inbound_id'        => 0,
				'xui_client_id'     => null,
				'xui_client_uuid'   => null,
				'email'             => 'l2tp_' . $username,
				'remark'            => (string) $plan->name,
				'plan_id'           => (int) $plan_id,
				'expires_at'        => $expires_at,
				'total_traffic'    => $total_bytes,
				'sub_id'            => $username,
				'provision_type'   => 'plan',
				'service_type'      => 'l2tp',
				'l2tp_server_id'    => $sid,
				'l2tp_username'     => $username,
				'l2tp_password_enc' => SimpleVPBot_Secret_Box::encrypt( $password ),
			)
		);
		if ( ! $service_id ) {
			SimpleVPBot_Logger::error( 'l2tp: service insert failed; rolling back ssh user', array( 'u' => $username ) );
			self::ssh_remove_user_line( $ssh, $chap, $username, $reload );
			$ssh->disconnect();
			return false;
		}
		$ssh->disconnect();
		return (int) $service_id;
	}

	/**
	 * Delete user on the L2TP server (chap-secrets line). Keeps svp_services row for audit.
	 *
	 * @param object $svc Service row.
	 * @return bool
	 */
	public static function delete_user( $svc ) {
		if ( ! $svc || empty( $svc->l2tp_username ) ) {
			return false;
		}
		$sid = isset( $svc->l2tp_server_id ) ? (int) $svc->l2tp_server_id : 0;
		if ( $sid < 1 ) {
			return false;
		}
		$srv_row = SimpleVPBot_Model_L2TP_Server::find( $sid );
		if ( ! $srv_row ) {
			return false;
		}
		$srv = SimpleVPBot_Model_L2TP_Server::decrypted( $srv_row );
		$ssh = new SimpleVPBot_SSH_Client( $srv );
		if ( ! $ssh->connect() ) {
			return false;
		}
		$ok = self::ssh_remove_user_line( $ssh, self::chap_path( $srv ), (string) $svc->l2tp_username, self::reload_cmd( $srv ) );
		$ssh->disconnect();
		return $ok;
	}

	/**
	 * Rotate password: remove + append with a new password and update DB.
	 *
	 * @param object $svc Service.
	 * @return string|false New password or false.
	 */
	public static function rotate_password( $svc ) {
		if ( ! $svc || empty( $svc->l2tp_username ) ) {
			return false;
		}
		$sid = (int) ( $svc->l2tp_server_id ?? 0 );
		if ( $sid < 1 ) {
			return false;
		}
		$srv_row = SimpleVPBot_Model_L2TP_Server::find( $sid );
		if ( ! $srv_row ) {
			return false;
		}
		$srv      = SimpleVPBot_Model_L2TP_Server::decrypted( $srv_row );
		$ssh      = new SimpleVPBot_SSH_Client( $srv );
		if ( ! $ssh->connect() ) {
			return false;
		}
		$new_pass = self::gen_password();
		$chap     = self::chap_path( $srv );
		$reload   = self::reload_cmd( $srv );
		$user     = (string) $svc->l2tp_username;

		self::ssh_remove_user_line( $ssh, $chap, $user, '' );
		$cmd = sprintf(
			"printf '%%s\\t*\\t%%s\\t*\\n' %s %s | sudo /usr/bin/tee -a %s >/dev/null && %s",
			escapeshellarg( $user ),
			escapeshellarg( $new_pass ),
			escapeshellarg( $chap ),
			$reload
		);
		$res = $ssh->exec( $cmd );
		$ssh->disconnect();
		if ( empty( $res['ok'] ) ) {
			return false;
		}
		SimpleVPBot_Model_Service::update(
			(int) $svc->id,
			array( 'l2tp_password_enc' => SimpleVPBot_Secret_Box::encrypt( $new_pass ) )
		);
		return $new_pass;
	}

	/**
	 * Pull user's total bytes from server via usage_cmd_template (must print a single integer).
	 *
	 * @param object $svc Service row.
	 * @return int|null Null if template not configured / failed.
	 */
	public static function refresh_usage( $svc ) {
		if ( ! $svc || empty( $svc->l2tp_username ) ) {
			return null;
		}
		$sid = (int) ( $svc->l2tp_server_id ?? 0 );
		if ( $sid < 1 ) {
			return null;
		}
		$srv_row = SimpleVPBot_Model_L2TP_Server::find( $sid );
		if ( ! $srv_row ) {
			return null;
		}
		$srv = SimpleVPBot_Model_L2TP_Server::decrypted( $srv_row );
		$tpl = trim( (string) ( $srv->usage_cmd_template ?? '' ) );
		if ( '' === $tpl ) {
			return null;
		}
		$cmd = str_replace( '{username}', escapeshellarg( (string) $svc->l2tp_username ), $tpl );
		$ssh = new SimpleVPBot_SSH_Client( $srv );
		if ( ! $ssh->connect() ) {
			return null;
		}
		$res = $ssh->exec( $cmd );
		$ssh->disconnect();
		if ( empty( $res['ok'] ) ) {
			return null;
		}
		$n = (int) trim( (string) $res['stdout'] );
		if ( $n < 0 ) {
			$n = 0;
		}
		SimpleVPBot_Model_Service::update( (int) $svc->id, array( 'used_traffic' => $n ) );
		return $n;
	}

	/**
	 * Quick connect + echo check.
	 *
	 * @param object $decrypted_server Decrypted server row.
	 * @return array{ok:bool, message:string}
	 */
	public static function test_connection( $decrypted_server ) {
		$ssh = new SimpleVPBot_SSH_Client( $decrypted_server );
		if ( ! $ssh->connect() ) {
			return array( 'ok' => false, 'message' => $ssh->last_error );
		}
		$res = $ssh->exec( 'echo SVP_OK' );
		$ssh->disconnect();
		if ( empty( $res['ok'] ) || false === strpos( (string) $res['stdout'], 'SVP_OK' ) ) {
			return array( 'ok' => false, 'message' => 'echo failed: ' . substr( (string) $res['stderr'], 0, 200 ) );
		}
		return array( 'ok' => true, 'message' => 'SSH OK' );
	}

	/**
	 * Remove "{user}\s..." line from chap-secrets (optionally reload).
	 *
	 * @param SimpleVPBot_SSH_Client $ssh Connected client.
	 * @param string                 $chap_path Chap path.
	 * @param string                 $username  Username.
	 * @param string                 $reload_cmd Reload or ''.
	 * @return bool
	 */
	private static function ssh_remove_user_line( $ssh, $chap_path, $username, $reload_cmd ) {
		// sed pattern: ^username\s — matches user followed by whitespace.
		$pat = '/^' . preg_quote( $username, '/' ) . '[[:space:]]/d';
		$cmd = 'sudo /usr/bin/sed -i ' . escapeshellarg( $pat ) . ' ' . escapeshellarg( $chap_path );
		if ( '' !== $reload_cmd ) {
			$cmd .= ' && ' . $reload_cmd;
		}
		$res = $ssh->exec( $cmd );
		return ! empty( $res['ok'] );
	}

	/**
	 * @param object $srv Decrypted server row.
	 * @return string
	 */
	private static function chap_path( $srv ) {
		$p = trim( (string) ( $srv->chap_path ?? '' ) );
		return '' !== $p ? $p : '/etc/ppp/chap-secrets';
	}

	/**
	 * @param object $srv Decrypted server row.
	 * @return string
	 */
	private static function reload_cmd( $srv ) {
		$r = trim( (string) ( $srv->reload_cmd ?? '' ) );
		return '' !== $r ? $r : 'sudo /bin/systemctl reload xl2tpd';
	}

	/**
	 * @param int $user_id User id.
	 * @return string
	 */
	private static function gen_username( $user_id ) {
		$rand = strtolower( wp_generate_password( 6, false, false ) );
		$rand = preg_replace( '/[^a-z0-9]/', '', $rand );
		if ( strlen( $rand ) < 6 ) {
			$rand = substr( $rand . 'abcdef', 0, 6 );
		}
		return 'svp_' . (int) $user_id . '_' . $rand;
	}

	/**
	 * @return string 14-char alphanumeric password.
	 */
	private static function gen_password() {
		$p = wp_generate_password( 14, false, false );
		return preg_replace( '/[^A-Za-z0-9]/', 'X', (string) $p );
	}
}
