<?php
/**
 * Resolve bot callback_data into human-readable labels for dashboard activity log.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Activity_Callback_Label
 */
class SimpleVPBot_Activity_Callback_Label {

	/**
	 * Map svc:* action segment to bot text key.
	 *
	 * @var array<string, string>
	 */
	private static $svc_action_keys = array(
		'm'  => 'btn.svc.back_manage',
		'p'  => 'btn.svc.show_connection',
		'us' => 'btn.svc.show_usage',
		'k'  => 'btn.svc.change_password',
		'r'  => 'btn.svc.renew',
		'ar' => 'btn.svc.auto_renew',
		'al' => 'btn.svc.alerts',
		'rn' => 'btn.svc.rename',
		'f'  => 'btn.svc.faq',
		'su' => 'btn.svc.support',
		'tx' => 'btn.svc.transfer',
		'b'  => 'btn.common.back',
		'l'  => 'btn.svc.config_qr',
		'u'  => 'btn.svc.update_servers',
		'v'  => 'btn.svc.add_volume',
		'sl' => 'btn.svc.add_users',
		'n'  => 'btn.svc.panel_note',
		'ip' => 'btn.svc.active_connections',
		'w'  => 'btn.svc.config',
	);

	/**
	 * Resolve callback_data for activity summary.
	 *
	 * @param string      $callback_data Raw callback_data.
	 * @param string|null $locale        fa|en or null for site default.
	 * @return string Empty if unknown.
	 */
	public static function resolve( $callback_data, $locale = null ) {
		$cb = trim( (string) $callback_data );
		if ( '' === $cb ) {
			return '';
		}
		$loc = null === $locale ? SimpleVPBot_Texts::site_default_locale() : SimpleVPBot_Model_Text::normalize_locale( $locale );
		$parts = explode( ':', $cb );
		$prefix = isset( $parts[0] ) ? (string) $parts[0] : '';

		if ( 'buy' === $prefix ) {
			return self::resolve_buy( $parts, $loc );
		}
		if ( 'svc' === $prefix ) {
			return self::resolve_svc( $parts, $loc );
		}
		if ( 'reg' === $prefix && isset( $parts[1] ) && 'a' === $parts[1] ) {
			return self::text( 'btn.reg.approve', $loc, __( 'Approve registration', 'simplevpbot' ) );
		}
		if ( 'adm' === $prefix ) {
			return self::resolve_adm( $parts, $loc );
		}
		return '';
	}

	/**
	 * @param array<int, string> $parts Callback segments.
	 * @param string             $loc   Locale.
	 * @return string
	 */
	private static function resolve_buy( array $parts, $loc ) {
		$action = isset( $parts[1] ) ? (string) $parts[1] : '';
		if ( 'g' === $action && isset( $parts[2] ) && class_exists( 'SimpleVPBot_Model_Plan_Category' ) ) {
			$cat = SimpleVPBot_Model_Plan_Category::find( (int) $parts[2] );
			if ( $cat && ! empty( $cat->label ) ) {
				return (string) $cat->label;
			}
		}
		if ( 'p' === $action && isset( $parts[2] ) && class_exists( 'SimpleVPBot_Model_Plan' ) ) {
			$plan = SimpleVPBot_Model_Plan::find( (int) $parts[2] );
			if ( $plan && ! empty( $plan->name ) ) {
				return (string) $plan->name;
			}
		}
		if ( 'pm' === $action && isset( $parts[2], $parts[3] ) && class_exists( 'SimpleVPBot_Model_Card' ) ) {
			$card = SimpleVPBot_Model_Card::find( (int) $parts[3] );
			if ( $card ) {
				$suffix = trim( (string) ( $card->card_suffix ?? $card->suffix ?? '' ) );
				$holder = trim( (string) ( $card->holder_name ?? '' ) );
				$tpl    = self::text( 'btn.pay.card_label', $loc, '💳 {suffix} · {holder}' );
				return SimpleVPBot_Texts::format( $tpl, array( 'suffix' => $suffix, 'holder' => $holder ) );
			}
		}
		if ( 'cf' === $action ) {
			return self::text( 'btn.pay.confirm_buy', $loc, 'Confirm purchase' );
		}
		if ( 'sw' === $action ) {
			return self::text( 'btn.pay.site_wallet', $loc, 'Pay with wallet' );
		}
		if ( 'bw' === $action ) {
			return self::text( 'btn.pay.bale_wallet', $loc, 'Pay with Bale wallet' );
		}
		if ( 'cd' === $action ) {
			return self::text( 'btn.pay.discount_code', $loc, 'Discount code' );
		}
		if ( 'c' === $action ) {
			return self::text( 'btn.pay.cancel', $loc, 'Cancel' );
		}
		return '';
	}

	/**
	 * @param array<int, string> $parts Callback segments.
	 * @param string             $loc   Locale.
	 * @return string
	 */
	private static function resolve_svc( array $parts, $loc ) {
		$action = isset( $parts[1] ) ? (string) $parts[1] : '';
		if ( '' === $action ) {
			return '';
		}
		$key = isset( self::$svc_action_keys[ $action ] ) ? self::$svc_action_keys[ $action ] : '';
		if ( '' === $key ) {
			return '';
		}
		$label = self::text( $key, $loc, $action );
		if ( 'w' === $action && isset( $parts[3] ) ) {
			return SimpleVPBot_Texts::format( self::text( 'btn.svc.config_n', $loc, 'Config {n}' ), array( 'n' => (string) ( (int) $parts[3] + 1 ) ) );
		}
		if ( isset( $parts[2] ) && is_numeric( $parts[2] ) && (int) $parts[2] > 0 ) {
			return $label . ' #' . (int) $parts[2];
		}
		return $label;
	}

	/**
	 * @param array<int, string> $parts Callback segments.
	 * @param string             $loc   Locale.
	 * @return string
	 */
	private static function resolve_adm( array $parts, $loc ) {
		$action = isset( $parts[1] ) ? (string) $parts[1] : '';
		$map    = array(
			'ui'  => 'btn.adm.user_info',
			'umsg' => 'btn.adm.message_user',
			'wbp' => 'btn.adm.wallet_plus',
			'wbm' => 'btn.adm.wallet_minus',
			'rcp' => 'btn.adm.receipts',
			'aq'  => 'btn.adm.approved_queue',
			'pq'  => 'btn.adm.pending_queue',
			'rq'  => 'btn.adm.rejected_queue',
			'rr'  => 'btn.adm.reject_user',
		);
		if ( ! isset( $map[ $action ] ) ) {
			return 'Admin: ' . $action;
		}
		return self::text( $map[ $action ], $loc, 'Admin action' );
	}

	/**
	 * @param string $key     Text key.
	 * @param string $loc     Locale.
	 * @param string $default Default label.
	 * @return string
	 */
	private static function text( $key, $loc, $default ) {
		if ( ! class_exists( 'SimpleVPBot_Texts' ) ) {
			return $default;
		}
		$v = trim( SimpleVPBot_Texts::get( $key, $default, $loc ) );
		return '' !== $v ? $v : $default;
	}

	/**
	 * Build summary_display for one activity row.
	 *
	 * @param array<string, mixed> $row    Activity row with event_type, payload, channel.
	 * @param string|null          $locale fa|en.
	 * @return string
	 */
	public static function activity_summary_display( array $row, $locale = null ) {
		$ev = sanitize_key( (string) ( $row['event_type'] ?? '' ) );
		$pl = isset( $row['payload'] ) && is_array( $row['payload'] ) ? $row['payload'] : array();
		if ( 'callback_query' === $ev ) {
			$cb = isset( $pl['callback_data'] ) ? (string) $pl['callback_data'] : '';
			$resolved = self::resolve( $cb, $locale );
			if ( '' !== $resolved ) {
				return $resolved;
			}
		}
		return '';
	}
}
