<?php
/**
 * Bot admin backup facade.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Handler_Admin_Backup
 */
class SimpleVPBot_Handler_Admin_Backup {

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param object|null $user User.
	 */
	public static function open_tab( $platform, $chat_id, $user = null ) {
		self::send_panel( $platform, $chat_id, $user );
	}

	/**
	 * Send full backup control panel.
	 *
	 * @param string      $platform Platform.
	 * @param int         $chat_id  Chat id.
	 * @param object|null $user     User.
	 */
	public static function send_panel( $platform, $chat_id, $user = null ) {
		$s = SimpleVPBot_Settings::all();
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			self::panel_caption( $s, $user ),
			array( 'reply_markup' => self::panel_reply_markup( $s, $user ) )
		);
	}

	/**
	 * @param array<string, mixed> $s    Settings.
	 * @param object|null          $user User.
	 * @return string
	 */
	public static function panel_caption( $s, $user = null ) {
		$iv = (int) ( $s['backup_interval_minutes'] ?? 60 );
		if ( $user && is_object( $user ) ) {
			$t  = SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.backup.header', $user ) . "\n";
			$t .= SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.backup.interval', $user, array( 'minutes' => (string) $iv ) ) . "\n";
			$t .= SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.backup.tg_chat', $user, array( 'id' => (string) (int) ( $s['backup_telegram_chat_id'] ?? 0 ) ) ) . "\n";
			$t .= SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.backup.bale_chat', $user, array( 'id' => (string) (int) ( $s['backup_bale_chat_id'] ?? 0 ) ) ) . "\n";
			$sta = ! empty( $s['backup_send_telegram_admins'] ) ? '✓' : '✗';
			$sba = ! empty( $s['backup_send_bale_admins'] ) ? '✓' : '✗';
			$stc = ! empty( $s['backup_send_telegram_channel'] ) ? '✓' : '✗';
			$sbc = ! empty( $s['backup_send_bale_channel'] ) ? '✓' : '✗';
			$t  .= SimpleVPBot_Bot_Admin_Texts::msg(
				'msg.admin.backup.targets',
				$user,
				array(
					'tg_admin'    => $sta,
					'bale_admin'  => $sba,
					'tg_channel'  => $stc,
					'bale_channel' => $sbc,
				)
			) . "\n";
			$lbat = (int) get_option( 'simplevpbot_last_backup_at', 0 );
			$lbui = (int) get_option( 'simplevpbot_last_backup_built_at', 0 );
			$t   .= SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.backup.last_sent', $user, array( 'ts' => self::fmt_ts( $lbat ) ) ) . "\n";
			$t   .= SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.backup.last_built', $user, array( 'ts' => self::fmt_ts( $lbui ) ) ) . "\n";
			$t   .= SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.backup.footer', $user );
			return $t;
		}
		$t  = "💾 بکاپ و ریستور\n➖➖➖➖\n";
		$t .= '⏱ فاصله: ' . $iv . " دقیقه\n";
		$t .= '📢 TG chat id: ' . (int) ( $s['backup_telegram_chat_id'] ?? 0 ) . "\n";
		$t .= '💬 Bale chat id: ' . (int) ( $s['backup_bale_chat_id'] ?? 0 ) . "\n";
		$sta  = ! empty( $s['backup_send_telegram_admins'] ) ? '✓' : '✗';
		$sba  = ! empty( $s['backup_send_bale_admins'] ) ? '✓' : '✗';
		$stc  = ! empty( $s['backup_send_telegram_channel'] ) ? '✓' : '✗';
		$sbc  = ! empty( $s['backup_send_bale_channel'] ) ? '✓' : '✗';
		$t   .= "ارسال: TG ادمین {$sta} · Bale ادمین {$sba} · TG کانال {$stc} · Bale کانال {$sbc}\n";
		$lbat = (int) get_option( 'simplevpbot_last_backup_at', 0 );
		$lbui = (int) get_option( 'simplevpbot_last_backup_built_at', 0 );
		$t   .= 'آخرین ارسال موفق: ' . self::fmt_ts( $lbat ) . "\n";
		$t   .= 'آخرین ساخت زیپ: ' . self::fmt_ts( $lbui ) . "\n";
		$t   .= "➖\nدکمه‌ها: بکاپ الان، تیک‌ها، ویرایش مقدار، ریستور (۲ مرحله).";
		return $t;
	}

	/**
	 * @param int $ts Unix.
	 * @return string
	 */
	public static function fmt_ts( $ts ) {
		$ts = (int) $ts;
		if ( $ts <= 0 ) {
			return '—';
		}
		if ( class_exists( 'SimpleVPBot_Jalali_Date' ) ) {
			return SimpleVPBot_Jalali_Date::format_datetime( $ts );
		}
		return wp_date( 'Y-m-d H:i', $ts, wp_timezone() );
	}

	/**
	 * @param array<string, mixed> $s    Settings.
	 * @param object|null          $user User.
	 * @return array<string, mixed>
	 */
	public static function panel_reply_markup( $s, $user = null ) {
		return SimpleVPBot_Keyboards::admin_backup_panel_reply( $s, $user );
	}

	/**
	 * Reply label → pnl:bk callback map (i18n when user provided).
	 *
	 * @param array<string, mixed> $s    Settings.
	 * @param object|null          $user User.
	 * @return array<string, string>
	 */
	public static function reply_label_map( array $s, $user = null ) {
		$t = function ( $key, $def = '' ) use ( $user ) {
			return ( $user && is_object( $user ) )
				? SimpleVPBot_Texts::get_for_user( $key, $user, $def )
				: SimpleVPBot_Texts::get( $key, $def );
		};
		$sta = ! empty( $s['backup_send_telegram_admins'] ) ? '✓' : '✗';
		$sba = ! empty( $s['backup_send_bale_admins'] ) ? '✓' : '✗';
		$stc = ! empty( $s['backup_send_telegram_channel'] ) ? '✓' : '✗';
		$sbc = ! empty( $s['backup_send_bale_channel'] ) ? '✓' : '✗';
		return array(
			$t( 'btn.admin.backup.now', '▶️ بکاپ الان' )                      => 'pnl:bk:run',
			$t( 'btn.admin.backup.tg_ad', 'TG ad' ) . ' ' . $sta             => 'pnl:bk:sw:tga',
			$t( 'btn.admin.backup.bl_ad', 'Bl ad' ) . ' ' . $sba             => 'pnl:bk:sw:bla',
			$t( 'btn.admin.backup.tg_ch', 'TG ch' ) . ' ' . $stc             => 'pnl:bk:sw:tgc',
			$t( 'btn.admin.backup.bl_ch', 'Bl ch' ) . ' ' . $sbc             => 'pnl:bk:sw:blc',
			$t( 'btn.admin.backup.interval', '⏱ فاصله (دقیقه)' )             => 'pnl:bk:int',
			$t( 'btn.admin.backup.tg_ch_id', '📢 TG ch id' )                 => 'pnl:bk:xtg',
			$t( 'btn.admin.backup.bl_ch_id', '💬 Bale ch id' )               => 'pnl:bk:xbl',
			$t( 'btn.admin.backup.restore', '📥 ریستور (۲ مرحله)' )         => 'pnl:bk:r1',
			$t( 'btn.admin.backup.cancel_mode', '❌ لغو حالت' )               => 'pnl:bk:ca',
			$t( 'btn.admin.backup.restore_confirm', '✅ ادامهٔ ریستور' )        => 'pnl:bk:r2',
			$t( 'btn.admin.backup.restore_cancel', '❌ لغو ریستور' )           => 'pnl:bk:ca',
		);
	}

	/**
	 * Handle pnl:bk:* inline callbacks.
	 *
	 * @param array<string, mixed> $ctx   Handler context.
	 * @param array<int, string>   $parts Callback parts.
	 */
	public static function handle_callback( array $ctx, array $parts ) {
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$user     = isset( $ctx['user'] ) ? $ctx['user'] : null;
		$op       = isset( $parts[2] ) ? (string) $parts[2] : '';

		$refresh_panel = static function () use ( $platform, $chat_id, $user ) {
			self::send_panel( $platform, $chat_id, $user );
		};

		switch ( $op ) {
			case 'run':
				$r = SimpleVPBot_Cron_Backup::run(
					array(
						'force'          => true,
						'ignore_enabled' => true,
					)
				);
				$line = "💾 نتیجه بکاپ:\n";
				if ( ! empty( $r['skipped_reason'] ) ) {
					$line .= 'رد شد: ' . (string) $r['skipped_reason'] . "\n";
				}
				$line .= 'ساخت زیپ: ' . ( ! empty( $r['built'] ) ? 'بله' : 'خیر' ) . "\n";
				$line .= 'ارسال موفق: ' . (int) ( $r['sent'] ?? 0 ) . "\n";
				$line .= 'ارسال ناموفق: ' . (int) ( $r['failed'] ?? 0 );
				if ( ! empty( $r['stored_on_site'] ) ) {
					$line .= "\nذخیره روی سایت: بله";
				}
				if ( ! empty( $r['panel_db_critical'] ) ) {
					$line .= "\n⚠️ DB پنل در zip نیست";
				}
				if ( ! empty( $r['zip'] ) ) {
					$line .= "\nفایل: " . (string) $r['zip'];
				}
				if ( ! empty( $r['built'] ) && 0 === (int) ( $r['sent'] ?? 0 ) && 0 === (int) ( $r['failed'] ?? 0 ) ) {
					$line .= "\n\nℹ️ اگر مقصدها را روشن کرده‌اید ولی ارسالی نیست: در تنظیمات عمومی شناسهٔ ادمین‌های تلگرام/بله را پر کنید؛ برای کانال، chat id تلگرام/بله را در بکاپ (داشبورد یا ربات) ذخیره کنید و ربات را ادمین کانال کنید.";
				}
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $line );
				$refresh_panel();
				return;
			case 'sw':
				$code = isset( $parts[3] ) ? (string) $parts[3] : '';
				$map  = array(
					'tga' => 'backup_send_telegram_admins',
					'bla' => 'backup_send_bale_admins',
					'tgc' => 'backup_send_telegram_channel',
					'blc' => 'backup_send_bale_channel',
				);
				if ( isset( $map[ $code ] ) ) {
					SimpleVPBot_Admin_Actions::toggle_backup_send_key( $map[ $code ] );
					$refresh_panel();
				}
				return;
			case 'int':
				if ( $user ) {
					SimpleVPBot_State::set( (int) $user->id, 'admin_bak_interval', array() );
					SimpleVPBot_Bot_Runtime::send_message(
						$platform,
						$chat_id,
						self::msg( $platform, $chat_id, 'msg.admin.backup.interval_prompt', $user )
					);
				}
				return;
			case 'xtg':
				if ( $user ) {
					SimpleVPBot_State::set( (int) $user->id, 'admin_bak_tg_chat', array() );
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::msg( $platform, $chat_id, 'msg.admin.prompt_tg_chat_id', $user ) );
				}
				return;
			case 'xbl':
				if ( $user ) {
					SimpleVPBot_State::set( (int) $user->id, 'admin_bak_bl_chat', array() );
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::msg( $platform, $chat_id, 'msg.admin.prompt_bale_chat_id', $user ) );
				}
				return;
			case 'r1':
				$confirm = $user
					? SimpleVPBot_Texts::get_for_user( 'btn.admin.backup.restore_confirm', $user, '✅ ادامهٔ ریستور' )
					: '✅ ادامهٔ ریستور';
				$cancel  = $user
					? SimpleVPBot_Texts::get_for_user( 'btn.admin.backup.restore_cancel', $user, '❌ لغو ریستور' )
					: '❌ لغو ریستور';
				$r1rows  = array(
					array(
						array( 'text' => $confirm ),
						array( 'text' => $cancel ),
					),
				);
				SimpleVPBot_Bot_Runtime::send_message(
					$platform,
					$chat_id,
					self::msg( $platform, $chat_id, 'msg.admin.backup.restore_warning', $user ),
					array( 'reply_markup' => SimpleVPBot_Keyboards::admin_reply_wrap_rows( $r1rows ) )
				);
				return;
			case 'r2':
				if ( $user ) {
					SimpleVPBot_State::set( (int) $user->id, 'admin_bak_restore', array() );
					$cancel = SimpleVPBot_Texts::get_for_user( 'btn.admin.backup.restore_cancel', $user, '❌ لغو ریستور' );
					$r2rows = array( array( array( 'text' => $cancel ) ) );
					SimpleVPBot_Bot_Runtime::send_message(
						$platform,
						$chat_id,
						self::msg( $platform, $chat_id, 'msg.admin.backup.restore_zip_prompt', $user ),
						array( 'reply_markup' => SimpleVPBot_Keyboards::admin_reply_wrap_rows( $r2rows ) )
					);
				}
				return;
			case 'ca':
				if ( $user ) {
					SimpleVPBot_State::clear( (int) $user->id );
				}
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, self::msg( $platform, $chat_id, 'msg.admin.wizard_cancelled', $user ) );
				return;
		}
	}

	/**
	 * @param string      $platform Platform.
	 * @param int         $chat_id  Chat id.
	 * @param string      $key      Text key.
	 * @param object|null $user     User.
	 * @param array<string, string> $vars Vars.
	 * @return string
	 */
	private static function msg( $platform, $chat_id, $key, $user = null, array $vars = array() ) {
		if ( $user && is_object( $user ) && class_exists( 'SimpleVPBot_Bot_Admin_Texts' ) ) {
			return SimpleVPBot_Bot_Admin_Texts::msg( $key, $user, $vars );
		}
		$me = ( 'bale' === $platform )
			? SimpleVPBot_Model_User::find_by_bale( $chat_id )
			: SimpleVPBot_Model_User::find_by_telegram( $chat_id );
		if ( $me && class_exists( 'SimpleVPBot_Bot_Admin_Texts' ) ) {
			return SimpleVPBot_Bot_Admin_Texts::msg( $key, $me, $vars );
		}
		return class_exists( 'SimpleVPBot_Texts' ) ? SimpleVPBot_Texts::get( $key ) : '';
	}
}
