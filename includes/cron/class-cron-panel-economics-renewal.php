<?php
/**
 * Admin alerts for panel infrastructure cost line expiry (paid_at / expires_at).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Cron_Panel_Economics_Renewal
 */
class SimpleVPBot_Cron_Panel_Economics_Renewal {

	const TRANSIENT_PREFIX = 'simplevpbot_panel_econ_exp_';

	/**
	 * Run on cron.
	 */
	public static function run() {
		if ( ! SimpleVPBot_Settings::get( 'enabled', true ) ) {
			return;
		}
		if ( ! SimpleVPBot_Settings::get( 'notify_panel_cost_expiry', true ) ) {
			return;
		}
		if ( ! class_exists( 'SimpleVPBot_Model_Panel_Economics_Line' ) ) {
			return;
		}

		global $wpdb;
		$t = SimpleVPBot_Model_Panel_Economics_Line::table();
		$p = $wpdb->prefix . 'svp_panels';
		$offsets  = class_exists( 'SimpleVPBot_Unit_Economics_Overview' )
			? SimpleVPBot_Unit_Economics_Overview::reminder_day_offsets()
			: array( 7, 1, 0 );
		$days_sql = implode( ',', array_map( 'intval', $offsets ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT l.*, pn.label AS panel_label
			FROM {$t} l
			LEFT JOIN {$p} pn ON pn.id = l.panel_id
			WHERE l.active = 1
			AND l.expires_at IS NOT NULL
			AND DATEDIFF(l.expires_at, UTC_DATE()) IN ({$days_sql})
			ORDER BY l.expires_at ASC, l.id ASC
			LIMIT 200"
		);

		if ( empty( $rows ) ) {
			return;
		}

		foreach ( $rows as $row ) {
			$line_id = (int) ( $row->id ?? 0 );
			if ( $line_id < 1 ) {
				continue;
			}
			$expires = (string) ( $row->expires_at ?? '' );
			if ( '' === $expires ) {
				continue;
			}
			$days_left = (int) floor( ( strtotime( $expires . ' 00:00:00 UTC' ) - strtotime( gmdate( 'Y-m-d' ) . ' 00:00:00 UTC' ) ) / 86400 );
			if ( ! in_array( $days_left, $offsets, true ) ) {
				continue;
			}
			self::maybe_notify( $line_id, $days_left, $row );
		}
	}

	/**
	 * @param int    $line_id   Line id.
	 * @param int    $days_left Days until expiry.
	 * @param object $row       Line + panel_label.
	 */
	private static function maybe_notify( $line_id, $days_left, $row ) {
		$key = self::TRANSIENT_PREFIX . $line_id . '_' . $days_left;
		if ( get_transient( $key ) ) {
			return;
		}
		set_transient( $key, 1, 23 * HOUR_IN_SECONDS );

		$panel_id   = (int) ( $row->panel_id ?? 0 );
		$panel_lbl  = trim( (string) ( $row->panel_label ?? '' ) );
		if ( '' === $panel_lbl && $panel_id > 0 ) {
			$panel_lbl = '#' . $panel_id;
		}
		$label    = trim( (string) ( $row->label ?? '' ) );
		$category = trim( (string) ( $row->category ?? '' ) );
		$expires  = (string) ( $row->expires_at ?? '' );

		$msg  = '📅 ';
		$msg .= SimpleVPBot_Texts::get( 'msg.cron.admin.panel_cost_renewal_title' );
		$msg .= "\n\n";
		if ( 0 === $days_left ) {
			$msg .= '⏰ ';
			$msg .= SimpleVPBot_Texts::get( 'msg.cron.admin.expires_today' );
		} elseif ( 1 === $days_left ) {
			$msg .= '⏰ ';
			$msg .= SimpleVPBot_Texts::get( 'msg.cron.admin.expires_tomorrow' );
		} else {
			$msg .= '⏰ ';
			$msg .= SimpleVPBot_Texts::format(
				SimpleVPBot_Texts::get( 'msg.cron.admin.expires_in_days' ),
				array( 'days' => (string) $days_left )
			);
		}
		$msg .= "\n\n";
		$msg .= '📛 ';
		$msg .= SimpleVPBot_Texts::get( 'msg.cron.admin.panel_label' ) . ' ' . $panel_lbl;
		$msg .= "\n📂 ";
		$msg .= SimpleVPBot_Texts::get( 'msg.cron.admin.category_label' ) . ' ' . $category;
		$msg .= "\n🏷 ";
		$msg .= SimpleVPBot_Texts::get( 'msg.cron.admin.title_label' ) . ' ' . $label;
		$msg .= "\n📆 ";
		$msg .= SimpleVPBot_Texts::get( 'msg.cron.admin.expiry_date_label' ) . ' ' . $expires;

		$dash = '';
		if ( class_exists( 'SimpleVPBot_Dashboard_Front' ) ) {
			$dash = (string) SimpleVPBot_Dashboard_Front::base_url();
		}
		if ( '' === $dash ) {
			$dash = home_url( '/dashboard/' );
		}
		$dash = trailingslashit( $dash ) . 'xui_panels/';
		if ( $panel_id > 0 ) {
			$dash = add_query_arg( 'panel_costs', (string) $panel_id, $dash );
		}
		$msg .= "\n\n🔗 " . $dash;

		self::send_admin_message( $msg );
	}

	/**
	 * @param string $msg Message text.
	 */
	private static function send_admin_message( $msg ) {
		$s      = SimpleVPBot_Settings::all();
		$tg_tok = (string) ( $s['telegram_token'] ?? '' );
		$bl_tok = (string) ( $s['bale_token'] ?? '' );
		$tg_ids = (array) ( $s['admin_telegram_ids'] ?? array() );
		$bl_ids = (array) ( $s['admin_bale_ids'] ?? array() );

		if ( $tg_tok ) {
			$tg = new SimpleVPBot_Telegram_Client( $tg_tok );
			foreach ( $tg_ids as $cid ) {
				$tg->send_message( array( 'chat_id' => (int) $cid, 'text' => $msg ) );
				usleep( 200000 );
			}
		}
		if ( $bl_tok ) {
			$bl = new SimpleVPBot_Bale_Client( $bl_tok );
			foreach ( $bl_ids as $cid ) {
				$bl->send_message(
					array(
						'chat_id' => (int) $cid,
						'text'    => class_exists( 'SimpleVPBot_Bot_Runtime' ) ? SimpleVPBot_Bot_Runtime::scrub_bale_text( $msg ) : $msg,
					)
				);
				usleep( 200000 );
			}
		}
	}
}
