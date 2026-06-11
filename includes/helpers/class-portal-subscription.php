<?php
/**
 * Dual-mode portal URLs: HTML in browsers, base64 subscription body for Xray clients.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Portal_Subscription
 */
class SimpleVPBot_Portal_Subscription {

	/**
	 * Serve subscription body before WordPress renders HTML (template_redirect -1).
	 */
	public static function maybe_serve() {
		if ( ! empty( $_GET['svp_adm'] ) && '1' === (string) wp_unslash( $_GET['svp_adm'] ) ) { // phpcs:ignore
			return;
		}
		if ( empty( $_GET['svp_p'] ) || '1' !== (string) wp_unslash( $_GET['svp_p'] ) ) { // phpcs:ignore
			return;
		}
		$uid = SimpleVPBot_Portal_Link::current_user_id();
		if ( $uid < 1 ) {
			return;
		}
		if ( self::is_browser_request() ) {
			return;
		}

		$sid    = SimpleVPBot_Portal_Link::current_service_id();
		$result = self::collect_uris( $uid, $sid );
		$uris   = isset( $result['uris'] ) && is_array( $result['uris'] ) ? $result['uris'] : array();
		if ( empty( $uris ) ) {
			if ( function_exists( 'status_header' ) ) {
				status_header( 404 );
			}
			if ( function_exists( 'nocache_headers' ) ) {
				nocache_headers();
			}
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo 'subscription not available';
			exit;
		}

		$userinfo = isset( $result['userinfo'] ) ? (string) $result['userinfo'] : '';
		self::render_and_exit( $uris, '' !== $userinfo ? $userinfo : null );
	}

	/**
	 * True when the client likely wants HTML (browser / in-app webview).
	 *
	 * @return bool
	 */
	public static function is_browser_request() {
		if ( self::force_subscription_format() ) {
			return false;
		}
		$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? (string) $_SERVER['HTTP_ACCEPT'] : '';
		return '' !== $accept && false !== stripos( $accept, 'text/html' );
	}

	/**
	 * Explicit subscription override (?svp_fmt=sub).
	 *
	 * @return bool
	 */
	public static function force_subscription_format() {
		return isset( $_GET['svp_fmt'] ) && 'sub' === (string) wp_unslash( $_GET['svp_fmt'] ); // phpcs:ignore
	}

	/**
	 * Config URI lines for a signed portal context.
	 *
	 * @param int $uid svp_users.id.
	 * @param int $sid svp_services.id (0 = merge all Xray services).
	 * @return array{uris:array<int,string>,userinfo?:string}
	 */
	public static function collect_uris( $uid, $sid = 0 ) {
		$uid = (int) $uid;
		$sid = (int) $sid;
		if ( $uid < 1 || ! class_exists( 'SimpleVPBot_Model_User' ) ) {
			return array( 'uris' => array() );
		}
		$user = SimpleVPBot_Model_User::find( $uid );
		if ( ! $user || 'approved' !== (string) $user->status ) {
			return array( 'uris' => array() );
		}

		if ( $sid > 0 ) {
			return self::collect_uris_for_service( $uid, $sid );
		}

		return self::collect_uris_merged( $uid );
	}

	/**
	 * @param int $uid svp_users.id.
	 * @param int $sid svp_services.id.
	 * @return array{uris:array<int,string>,userinfo?:string}
	 */
	private static function collect_uris_for_service( $uid, $sid ) {
		if ( ! class_exists( 'SimpleVPBot_Model_Service' ) || ! class_exists( 'SimpleVPBot_Handler_Service' ) ) {
			return array( 'uris' => array() );
		}
		$svc = SimpleVPBot_Model_Service::find( (int) $sid );
		if ( ! $svc ) {
			return array( 'uris' => array() );
		}
		if ( class_exists( 'SimpleVPBot_Feature_L2tp' ) && ! SimpleVPBot_Feature_L2tp::service_visible( $svc ) ) {
			return array( 'uris' => array() );
		}
		if ( SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
			return array( 'uris' => array() );
		}
		$data = SimpleVPBot_Handler_Service::get_portal_service_data( $svc, (int) $uid );
		if ( ! empty( $data['_deleted'] ) ) {
			return array( 'uris' => array() );
		}
		$uris = isset( $data['config_uris'] ) && is_array( $data['config_uris'] ) ? $data['config_uris'] : array();
		$uris = array_values(
			array_unique(
				array_filter(
					array_map(
						static function ( $u ) {
							return trim( (string) $u );
						},
						$uris
					)
				)
			)
		);
		if ( empty( $uris ) ) {
			return array( 'uris' => array() );
		}
		return array(
			'uris'     => $uris,
			'userinfo' => self::userinfo_from_service( $svc, $data ),
		);
	}

	/**
	 * Merge config URIs from all visible non-L2TP services for one user.
	 *
	 * @param int $uid svp_users.id.
	 * @return array{uris:array<int,string>,userinfo?:string}
	 */
	private static function collect_uris_merged( $uid ) {
		if ( ! class_exists( 'SimpleVPBot_Model_Service' ) || ! class_exists( 'SimpleVPBot_Handler_Service' ) ) {
			return array( 'uris' => array() );
		}
		$list = SimpleVPBot_Model_Service::by_user( (int) $uid );
		if ( class_exists( 'SimpleVPBot_Feature_L2tp' ) ) {
			$list = SimpleVPBot_Feature_L2tp::filter_services( (array) $list );
		}
		$all      = array();
		$userinfo = '';
		foreach ( (array) $list as $svc ) {
			if ( SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
				continue;
			}
			$chunk = self::collect_uris_for_service( (int) $uid, (int) ( $svc->id ?? 0 ) );
			foreach ( $chunk['uris'] as $u ) {
				$all[] = $u;
			}
			if ( '' === $userinfo && ! empty( $chunk['userinfo'] ) ) {
				$userinfo = (string) $chunk['userinfo'];
			}
		}
		$all = array_values( array_unique( array_filter( $all ) ) );
		$out = array( 'uris' => $all );
		if ( '' !== $userinfo ) {
			$out['userinfo'] = $userinfo;
		}
		return $out;
	}

	/**
	 * 3x-ui subscription-userinfo header value.
	 *
	 * @param object               $svc  Service row.
	 * @param array<string, mixed> $data Portal service data row.
	 * @return string
	 */
	public static function userinfo_from_service( $svc, array $data ) {
		$down = (int) round( (float) ( $data['down_gb'] ?? 0 ) * 1073741824 );
		$up   = (int) round( (float) ( $data['up_gb'] ?? 0 ) * 1073741824 );
		if ( 0 === $down && 0 === $up && isset( $svc->used_traffic ) ) {
			$down = (int) $svc->used_traffic;
		}
		$total = (int) ( $svc->total_traffic ?? 0 );
		$exp   = 0;
		if ( ! empty( $svc->expires_at ) ) {
			$exp = (int) strtotime( (string) $svc->expires_at . ' UTC' );
		}
		return sprintf( 'upload=%d; download=%d; total=%d; expire=%d', $up, $down, $total, $exp );
	}

	/**
	 * Base64 body matching 3x-ui public subscription output.
	 *
	 * @param array<int, string> $uris Share URIs.
	 * @return string
	 */
	public static function build_body( array $uris ) {
		$plain = implode(
			"\n",
			array_values(
				array_filter(
					array_map(
						static function ( $u ) {
							return trim( (string) $u );
						},
						$uris
					)
				)
			)
		);
		if ( '' === $plain ) {
			return '';
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return base64_encode( $plain );
	}

	/**
	 * Emit subscription HTTP response and stop WordPress.
	 *
	 * @param array<int, string> $uris     Share URIs.
	 * @param string|null        $userinfo Optional subscription-userinfo header.
	 */
	public static function render_and_exit( array $uris, $userinfo = null ) {
		if ( function_exists( 'status_header' ) ) {
			status_header( 200 );
		}
		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: inline' );
		header( 'Profile-Update-Interval: 24' );
		if ( is_string( $userinfo ) && '' !== $userinfo ) {
			header( 'subscription-userinfo: ' . $userinfo );
		}
		echo self::build_body( $uris ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}
}
