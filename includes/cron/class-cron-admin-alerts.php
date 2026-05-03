<?php
/**
 * Admin-facing panel health alerts (Telegram/Bale) with throttling.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Cron_Admin_Alerts
 */
class SimpleVPBot_Cron_Admin_Alerts {

	const TRANSIENT_PREFIX = 'simplevpbot_admin_panel_alert_';

	/**
	 * Run on cron: probe each panel login (DB rows first; legacy settings only if table empty).
	 */
	public static function run() {
		if ( ! SimpleVPBot_Settings::get( 'enabled', true ) ) {
			return;
		}
		if ( ! SimpleVPBot_Settings::get( 'notify_admin_panel_down', true ) ) {
			return;
		}
		if ( ! class_exists( 'SimpleVPBot_Xui_Client' ) ) {
			return;
		}
		$cool = max( 5, (int) SimpleVPBot_Settings::get( 'notify_admin_panel_down_cooldown', 30 ) );

		$panels = array();
		if ( class_exists( 'SimpleVPBot_Model_Panel' ) ) {
			$panels = SimpleVPBot_Model_Panel::all_active_ordered();
			if ( empty( $panels ) && SimpleVPBot_Model_Panel::count_all() > 0 ) {
				// همه غیرفعال‌اند؛ باز هم همان رکوردها را چک کن تا به آدرس قدیمی تنظیمات گیر نکند.
				$panels = SimpleVPBot_Model_Panel::all_ordered();
			}
		}

		if ( ! empty( $panels ) ) {
			foreach ( $panels as $pn ) {
				$pid = (int) ( $pn->id ?? 0 );
				if ( $pid < 1 ) {
					continue;
				}
				$label = trim( (string) ( $pn->label ?? '' ) );
				if ( '' === $label ) {
					$label = '#' . $pid;
				}
				$detail = '';
				$ok     = SimpleVPBot_Xui_Client::run_with_panel(
					$pid,
					function () use ( &$detail ) {
						$detail = implode( "\n", SimpleVPBot_Xui_Client::probe_alert_detail_lines() );
						return SimpleVPBot_Xui_Client::login_with_retries( 6, 300000 );
					}
				);
				if ( ! $ok ) {
					self::maybe_notify( 'p' . $pid, $cool, $label, $pid, $detail );
				}
			}
			return;
		}

		// فقط وقتی هیچ ردیفی در svp_panels نیست از مسیر قدیمی «تنظیمات → پنل» استفاده کن.
		if ( class_exists( 'SimpleVPBot_Model_Panel' ) && SimpleVPBot_Model_Panel::count_all() > 0 ) {
			return;
		}

		$s = SimpleVPBot_Settings::all();
		if ( '' === trim( (string) ( $s['panel_url'] ?? '' ) ) ) {
			return;
		}

		$detail = '';
		$ok     = SimpleVPBot_Xui_Client::run_with_panel(
			0,
			function () use ( &$detail ) {
				$detail = implode( "\n", SimpleVPBot_Xui_Client::probe_alert_detail_lines() );
				return SimpleVPBot_Xui_Client::login_with_retries( 6, 300000 );
			}
		);
		if ( ! $ok ) {
			$legacy_label = __( 'پنل ذخیره‌شده در «تنظیمات افزونه → پنل X-UI» (جدول «پنل‌ها» خالی است)', 'simplevpbot' );
			self::maybe_notify( 'legacy', $cool, $legacy_label, 0, $detail );
		}
	}

	/**
	 * @param string $suffix Transient suffix.
	 * @param int    $cool_min Cooldown minutes.
	 * @param string $label    Human label.
	 * @param int    $panel_id Panel id or 0 legacy.
	 * @param string $detail   Extra lines (host, URLs).
	 */
	private static function maybe_notify( $suffix, $cool_min, $label, $panel_id, $detail = '' ) {
		$key = self::TRANSIENT_PREFIX . $suffix;
		if ( get_transient( $key ) ) {
			return;
		}
		set_transient( $key, 1, $cool_min * MINUTE_IN_SECONDS );

		$msg  = '🛠 ';
		$msg .= __( 'ورود ۳x-ui از طرف سرور وردپرس/ربات برقرار نشد.', 'simplevpbot' );
		$msg .= "\n\n";
		$msg .= '📛 ';
		$msg .= __( 'پنل:', 'simplevpbot' ) . ' ' . $label;
		if ( $panel_id > 0 ) {
			$msg .= "\n🆔 ";
			$msg .= __( 'شناسهٔ رکورد در دیتابیس (svp_panels.id):', 'simplevpbot' ) . ' ' . $panel_id;
		}
		if ( '' !== trim( $detail ) ) {
			$msg .= "\n\n" . trim( $detail );
		}
		$msg .= "\n\n";
		$msg .= __( 'اگر در مرورگر پنل باز است: مسیر webBasePath در Panel URL، Secret ورود، فایروال یا TLS بین هاست وردپرس و پنل را بررسی کنید. برای غیرفعال‌کردن این هشدار: تنظیمات ربات → اعلان قطع پنل.', 'simplevpbot' );

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
