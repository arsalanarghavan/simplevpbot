<?php
/**
 * Reply + Inline keyboards (emoji labels from Texts).
 *
 * Telegram Bot API does not expose background color or theme for KeyboardButton / InlineKeyboardButton;
 * appearance is client-defined. GLASS_PREFIX is optional (empty = no extra decoration); admin route handlers
 * strip it before matching when non-empty.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Keyboards
 */
class SimpleVPBot_Keyboards {

	/** Optional visual prefix for action buttons (empty = labels use only their own emoji/text). */
	const GLASS_PREFIX = '';

	/**
	 * Prefix button label for «glass» action rows (inline: max 64 chars; reply: use $max_len 256).
	 *
	 * @param string $label   Visible text (without prefix).
	 * @param int    $max_len Max length; 0 = no trim.
	 * @return string
	 */
	public static function glass_button_text( $label, $max_len = 64 ) {
		$label = trim( (string) $label );
		$p     = self::GLASS_PREFIX;
		if ( '' === $label ) {
			$out = $p;
		} elseif ( function_exists( 'mb_strpos' ) && mb_strpos( $label, $p, 0, 'UTF-8' ) === 0 ) {
			$out = $label;
		} else {
			$out = $p . $label;
		}
		if ( $max_len > 0 && function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) && mb_strlen( $out, 'UTF-8' ) > $max_len ) {
			return mb_substr( $out, 0, $max_len, 'UTF-8' );
		}
		return $out;
	}

	/**
	 * Strip one leading glass prefix (admin Reply routes).
	 *
	 * @param string $text Raw message text.
	 * @return string
	 */
	public static function strip_glass_prefix( $text ) {
		$text = (string) $text;
		$p    = self::GLASS_PREFIX;
		if ( '' === $text ) {
			return $text;
		}
		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) && mb_strpos( $text, $p, 0, 'UTF-8' ) === 0 ) {
			return mb_substr( $text, mb_strlen( $p, 'UTF-8' ), null, 'UTF-8' );
		}
		if ( 0 === strpos( $text, $p ) ) {
			return (string) substr( $text, strlen( $p ) );
		}
		return $text;
	}

	/**
	 * Reply keyboard JSON for main user menu.
	 *
	 * @return array<string, mixed>
	 */
	public static function user_main_reply() {
		return array(
			'keyboard'          => array(
				array(
					array( 'text' => SimpleVPBot_Texts::get( 'btn.main.buy', '🛒 خرید سرویس' ) ),
					array( 'text' => SimpleVPBot_Texts::get( 'btn.main.manage', '🧰 مدیریت سرویس' ) ),
				),
				array(
					array( 'text' => SimpleVPBot_Texts::get( 'btn.main.apps', '📱 اپلیکیشن‌ها' ) ),
					array( 'text' => SimpleVPBot_Texts::get( 'btn.main.support', '🆘 پشتیبانی' ) ),
				),
				array(
					array( 'text' => SimpleVPBot_Texts::get( 'btn.main.account', '👤 اطلاعات حساب' ) ),
					array( 'text' => SimpleVPBot_Texts::get( 'btn.main.wallet', '💰 کیف پول' ) ),
				),
				array(
					array( 'text' => SimpleVPBot_Texts::get( 'btn.main.referral', '💎 کسب درآمد' ) ),
				),
			),
			'resize_keyboard'   => true,
			'one_time_keyboard' => false,
		);
	}

	/**
	 * Reply label: back to admin root (same keyboard as main admin menu).
	 *
	 * @return string
	 */
	public static function admin_back_main_label() {
		return SimpleVPBot_Texts::get( 'btn.admin.back_menu', '⬅️ منوی مدیریت' );
	}

	/**
	 * Admin main reply keyboard (all hub sections on Reply; no inline hub).
	 *
	 * @return array<string, mixed>
	 */
	public static function admin_main_reply() {
		$rows = self::admin_main_keyboard_rows();
		return array(
			'keyboard'          => $rows,
			'resize_keyboard'   => true,
			'one_time_keyboard' => false,
		);
	}

	/**
	 * Keyboard rows only (for merging with portal rows).
	 *
	 * @return array<int, array<int, array<string, string>>>
	 */
	public static function admin_main_keyboard_rows() {
		return array(
			array(
				array( 'text' => SimpleVPBot_Texts::get( 'btn.admin.dashboard', '📊 آمار' ) ),
				array( 'text' => SimpleVPBot_Texts::get( 'btn.admin.users', '👥 مدیریت کاربران' ) ),
			),
			array(
				array( 'text' => SimpleVPBot_Texts::get( 'btn.admin.finance', '💰 مالی' ) ),
				array( 'text' => SimpleVPBot_Texts::get( 'btn.admin.settings', '⚙️ تنظیمات' ) ),
			),
			array(
				array( 'text' => SimpleVPBot_Texts::get( 'btn.admin.advanced', '🔧 تنظیمات پیشرفته' ) ),
				array( 'text' => SimpleVPBot_Texts::get( 'btn.admin.exit', '🚪 خروج از پنل مدیریت' ) ),
			),
		);
	}

	/**
	 * Main admin keyboard + optional first rows (portal links as text triggers).
	 *
	 * @param string $platform telegram|bale.
	 * @param int    $chat_id  Admin chat id on that platform.
	 * @return array<string, mixed>
	 */
	public static function admin_main_reply_for_chat( $platform, $chat_id ) {
		$me = ( 'bale' === $platform )
			? SimpleVPBot_Model_User::find_by_bale( (int) $chat_id )
			: SimpleVPBot_Model_User::find_by_telegram( (int) $chat_id );
		$extra = array();
		if ( $me && (int) $me->id > 0 ) {
			$pu = SimpleVPBot_Portal_Link::build_url( (int) $me->id );
			if ( '' !== $pu ) {
				$extra[] = array( array( 'text' => SimpleVPBot_Texts::get( 'btn.admin.send_my_portal', '🌐 ارسال لینک پنل وب من' ) ) );
			}
			$adm = SimpleVPBot_Portal_Link::build_admin_url( (int) $me->id );
			if ( '' !== $adm ) {
				$extra[] = array( array( 'text' => SimpleVPBot_Texts::get( 'btn.admin.send_admin_portal', '🖥 ارسال لینک پنل ادمین وب' ) ) );
			}
		}
		$kb = self::admin_main_keyboard_rows();
		return array(
			'keyboard'          => array_merge( $extra, $kb ),
			'resize_keyboard'   => true,
			'one_time_keyboard' => false,
		);
	}

	/**
	 * One bottom row: back to admin main menu.
	 *
	 * @return array<string, mixed>
	 */
	public static function admin_only_back_reply() {
		return array(
			'keyboard'          => array(
				array( array( 'text' => self::admin_back_main_label() ) ),
			),
			'resize_keyboard'   => true,
			'one_time_keyboard' => false,
		);
	}

	/**
	 * Append back row to keyboard rows structure.
	 *
	 * @param array<int, array<int, array<string, string>>> $rows Keyboard rows.
	 * @return array<string, mixed>
	 */
	public static function admin_reply_wrap_rows( array $rows ) {
		$rows[] = array( array( 'text' => self::admin_back_main_label() ) );
		return array(
			'keyboard'          => $rows,
			'resize_keyboard'   => true,
			'one_time_keyboard' => false,
		);
	}

	/**
	 * Bale/Telegram admin: approve registration (reply keyboard).
	 *
	 * @param int $user_id WP user id (svp_users.id).
	 * @return array<string, mixed>
	 */
	public static function registration_approval_reply( $user_id ) {
		$uid = (int) $user_id;
		return self::admin_reply_wrap_rows(
			array(
				array(
					array( 'text' => self::glass_button_text( '✅ ثبت‌نام #' . $uid, 256 ) ),
					array( 'text' => self::glass_button_text( '❌ رد ثبت‌نام #' . $uid, 256 ) ),
				),
			)
		);
	}

	/**
	 * Receipt moderation (reply).
	 *
	 * @param int $receipt_id Receipt id.
	 * @return array<string, mixed>
	 */
	public static function receipt_moderation_reply( $receipt_id ) {
		$rid = (int) $receipt_id;
		return self::admin_reply_wrap_rows(
			array(
				array(
					array( 'text' => self::glass_button_text( '✅ رسید ' . $rid, 256 ) ),
					array( 'text' => self::glass_button_text( '❌ رد رسید ' . $rid, 256 ) ),
				),
			)
		);
	}

	/**
	 * Admin: زیرمنوی مدیریت کاربران (جستجو / صف ثبت‌نام).
	 *
	 * @return array<string, mixed>
	 */
	public static function admin_users_submenu_reply() {
		return self::admin_reply_wrap_rows(
			array(
				array(
					array( 'text' => SimpleVPBot_Texts::get( 'btn.admin.users_search', '🔎 جستجوی کاربر' ) ),
					array( 'text' => SimpleVPBot_Texts::get( 'btn.admin.users_queue', '📋 صف ثبت‌نام' ) ),
				),
				array(
					array( 'text' => SimpleVPBot_Texts::get( 'btn.admin.transfer', '🎁 انتقال سرویس' ) ),
					array( 'text' => '➕ گروهی' ),
				),
				array(
					array( 'text' => SimpleVPBot_Texts::get( 'btn.admin.broadcast', '📣 پیام همگانی' ) ),
				),
			)
		);
	}

	/**
	 * Admin: مالی (رسیدها و …).
	 *
	 * @return array<string, mixed>
	 */
	public static function admin_finance_submenu_reply() {
		return self::admin_reply_wrap_rows(
			array(
				array(
					array( 'text' => SimpleVPBot_Texts::get( 'btn.admin.receipts', '🧾 تایید رسیدها' ) ),
				),
			)
		);
	}

	/**
	 * Admin: تنظیمات — پرداخت، پنل، سرور، ربات‌ها.
	 *
	 * @return array<string, mixed>
	 */
	public static function admin_settings_catalog_reply() {
		return self::admin_reply_wrap_rows(
			array(
				array(
					array( 'text' => '📂 دسته پلن' ),
					array( 'text' => '📋 پلن‌ها' ),
					array( 'text' => '💳 کارت‌ها' ),
				),
				array(
					array( 'text' => '🖥 پنل' ),
					array( 'text' => '🔌 L2TP' ),
					array( 'text' => '🔗 کانفیگ' ),
				),
				array(
					array( 'text' => '₿ کریپتو' ),
					array( 'text' => '🤖 ربات‌ها' ),
				),
			)
		);
	}

	/**
	 * Admin: تنظیمات پیشرفته — عمومی، نوتیف، متن، لاگ، گزارش همگانی.
	 *
	 * @return array<string, mixed>
	 */
	public static function admin_settings_advanced_reply() {
		return self::admin_reply_wrap_rows(
			array(
				array(
					array( 'text' => SimpleVPBot_Texts::get( 'btn.admin.backup', '💾 پشتیبان‌گیری' ) ),
				),
				array(
					array( 'text' => '⚙️ عمومی' ),
					array( 'text' => '🔔 نوتیف' ),
				),
				array(
					array( 'text' => '📝 متن‌ها' ),
					array( 'text' => '📜 لاگ' ),
				),
				array(
					array( 'text' => '📣 گزارش همگانی' ),
				),
			)
		);
	}

	/**
	 * Hub subsection: عمومی (Reply).
	 *
	 * @return array<string, mixed>
	 */
	public static function admin_general_submenu_reply() {
		return self::admin_reply_wrap_rows(
			array(
				array(
					array( 'text' => '🔛 ربات فعال/غیر' ),
					array( 'text' => '🧪 تست فعال/غیر' ),
				),
				array(
					array( 'text' => '📥 ادمین TG' ),
					array( 'text' => '📥 ادمین Bl' ),
				),
				array( array( 'text' => '📄 ID پورتال' ) ),
				array( array( 'text' => '📦 پلن پیش‌فرض سرویس' ) ),
			)
		);
	}

	/**
	 * Hub subsection: ربات‌ها.
	 *
	 * @return array<string, mixed>
	 */
	public static function admin_bot_submenu_reply() {
		return self::admin_reply_wrap_rows(
			array(
				array(
					array( 'text' => 'getMe' ),
					array( 'text' => 'Set WH TG' ),
					array( 'text' => 'Set WH Bl' ),
				),
				array(
					array( 'text' => 'tok TG' ),
					array( 'text' => 'tok Bl' ),
				),
				array(
					array( 'text' => 'wh sec TG' ),
					array( 'text' => 'wh sec Bl' ),
				),
				array(
					array( 'text' => 'hdr' ),
					array( 'text' => 'Bale $' ),
				),
			)
		);
	}

	/**
	 * Hub subsection: پنل 3x-ui.
	 *
	 * @return array<string, mixed>
	 */
	public static function admin_panel_submenu_reply() {
		return self::admin_reply_wrap_rows(
			array(
				array( array( 'text' => '🔬 تست اتصال' ) ),
				array(
					array( 'text' => 'URL' ),
					array( 'text' => 'User' ),
					array( 'text' => 'Pass' ),
				),
				array(
					array( 'text' => 'API' ),
					array( 'text' => 'Log sec' ),
				),
				array( array( 'text' => 'Sub URL' ) ),
			)
		);
	}

	/**
	 * Hub subsection: نوتیف.
	 *
	 * @return array<string, mixed>
	 */
	public static function admin_notif_submenu_reply() {
		return self::admin_reply_wrap_rows(
			array(
				array( array( 'text' => '٪ کمی' ) ),
				array( array( 'text' => 'روز هشدار' ) ),
				array( array( 'text' => 'سقف کاربر' ) ),
				array( array( 'text' => 'قیمت+کاربر' ) ),
			)
		);
	}

	/**
	 * Hub subsection: گروهی Xray.
	 *
	 * @return array<string, mixed>
	 */
	public static function admin_bulk_submenu_reply() {
		return self::admin_reply_wrap_rows(
			array(
				array(
					array( 'text' => '+۱ روز' ),
					array( 'text' => '+۷ روز' ),
					array( 'text' => '+۳۰ روز' ),
				),
				array(
					array( 'text' => '+۱ GB' ),
					array( 'text' => '+۵ GB' ),
				),
				array( array( 'text' => '📝 تأیید متنی گروهی' ) ),
			)
		);
	}

	/**
	 * Hub subsection: کانفیگ / inbound.
	 *
	 * @return array<string, mixed>
	 */
	public static function admin_inbound_submenu_reply() {
		return self::admin_reply_wrap_rows(
			array(
				array( array( 'text' => '📋 لیست Inbound' ) ),
			)
		);
	}

	/**
	 * Hub subsection: کریپتو.
	 *
	 * @return array<string, mixed>
	 */
	public static function admin_crypto_submenu_reply() {
		return self::admin_reply_wrap_rows(
			array(
				array(
					array( 'text' => '₿ API' ),
					array( 'text' => '₿ IPN' ),
					array( 'text' => '₿ Cur' ),
				),
				array( array( 'text' => '🔄 مسیر IPN' ) ),
			)
		);
	}

	/**
	 * Backup panel (Reply) — same actions as former inline panel.
	 *
	 * @param array<string, mixed> $s Settings row.
	 * @return array<string, mixed>
	 */
	public static function admin_backup_panel_reply( array $s ) {
		$sta  = ! empty( $s['backup_send_telegram_admins'] ) ? '✓' : '✗';
		$sba  = ! empty( $s['backup_send_bale_admins'] ) ? '✓' : '✗';
		$stc  = ! empty( $s['backup_send_telegram_channel'] ) ? '✓' : '✗';
		$sbc  = ! empty( $s['backup_send_bale_channel'] ) ? '✓' : '✗';
		return self::admin_reply_wrap_rows(
			array(
				array( array( 'text' => '▶️ بکاپ الان' ) ),
				array(
					array( 'text' => 'TG ad ' . $sta ),
					array( 'text' => 'Bl ad ' . $sba ),
				),
				array(
					array( 'text' => 'TG ch ' . $stc ),
					array( 'text' => 'Bl ch ' . $sbc ),
				),
				array(
					array( 'text' => '⏱ فاصله (دقیقه)' ),
					array( 'text' => '📢 TG ch id' ),
					array( 'text' => '💬 Bale ch id' ),
				),
				array(
					array( 'text' => '📥 ریستور (۲ مرحله)' ),
					array( 'text' => '❌ لغو حالت' ),
				),
			)
		);
	}

	/**
	 * Remove reply keyboard.
	 *
	 * @return array<string, mixed>
	 */
	public static function remove_reply() {
		return array( 'remove_keyboard' => true );
	}

	/**
	 * Inline keyboard: registration approval for admins.
	 *
	 * @param int $user_id WP user id.
	 * @return array<string, mixed>
	 */
	public static function inline_registration( $user_id ) {
		return array(
			'inline_keyboard' => array(
				array(
					array(
						'text'          => self::glass_button_text( SimpleVPBot_Texts::get( 'btn.approve', '✅ تایید' ) ),
						'callback_data' => 'reg:a:' . (int) $user_id,
					),
					array(
						'text'          => self::glass_button_text( SimpleVPBot_Texts::get( 'btn.reject', '❌ رد' ) ),
						'callback_data' => 'reg:r:' . (int) $user_id,
					),
				),
			),
		);
	}

	/**
	 * Card-to-card payment: pick card + copy pan + copy amount (Telegram copy_text).
	 *
	 * @param array<int, object> $cards Card rows.
	 * @param int                $transaction_id Transaction id.
	 * @param float              $amount_toman Amount.
	 * @param string             $platform telegram|bale.
	 * @return array<string, mixed>
	 */
	public static function inline_card_payment( array $cards, $transaction_id, $amount_toman, $platform ) {
		$rows   = array();
		$amount_plain = (string) (int) round( (float) $amount_toman );
		foreach ( $cards as $c ) {
			$pan = preg_replace( '/\D+/', '', (string) $c->card_number );
			$row = array(
				array(
					'text'          => self::glass_button_text( '💳 ' . mb_substr( $pan, -4 ) . ' · ' . mb_substr( (string) $c->holder_name, 0, 10 ) ),
					'callback_data' => 'buy:cd:' . (int) $c->id . ':' . (int) $transaction_id,
				),
			);
			if ( 'telegram' === $platform ) {
				$row[] = array(
					'text'      => self::glass_button_text( '📋 کپی کارت' ),
					'copy_text' => array( 'text' => $pan ),
				);
			}
			$rows[] = $row;
		}
		if ( 'telegram' === $platform ) {
			$rows[] = array(
				array(
					'text'      => self::glass_button_text( '💵 کپی مبلغ' ),
					'copy_text' => array( 'text' => $amount_plain ),
				),
			);
		}
		return array( 'inline_keyboard' => $rows );
	}

	/**
	 * Step 1: one row per card (method label) + optional Bale wallet row.
	 *
	 * @param array<int, object>   $cards Active cards.
	 * @param int                  $transaction_id Transaction id.
	 * @param bool                 $show_bale_wallet Add buy:bw row.
	 * @return array<string, mixed>
	 */
	public static function inline_payment_method( array $cards, $transaction_id, $show_bale_wallet = false ) {
		$rows = array();
		$tid  = (int) $transaction_id;
		foreach ( $cards as $c ) {
			$cd = 'buy:pm:' . $tid . ':' . (int) $c->id;
			if ( strlen( $cd ) > 64 ) {
				continue;
			}
			$label = SimpleVPBot_Model_Card::payment_button_label( $c );
			$inner_max = 64 - mb_strlen( self::GLASS_PREFIX, 'UTF-8' );
			if ( $inner_max < 8 ) {
				$inner_max = 8;
			}
			if ( mb_strlen( $label, 'UTF-8' ) > $inner_max ) {
				$label = mb_substr( $label, 0, $inner_max, 'UTF-8' );
			}
			$rows[] = array(
				array(
					'text'          => self::glass_button_text( $label, 64 ),
					'callback_data' => $cd,
				),
			);
		}
		if ( $show_bale_wallet && $tid > 0 ) {
			$bw = 'buy:bw:' . $tid;
			if ( strlen( $bw ) <= 64 ) {
				$rows[] = array(
					array(
						'text'          => self::glass_button_text( '💰 پرداخت با کیف پول بله' ),
						'callback_data' => $bw,
					),
				);
			}
		}
		return array( 'inline_keyboard' => $rows );
	}

	/**
	 * Step 2: single card invoice with copy (Telegram + Bale copy_text when supported).
	 *
	 * @param object   $card          Card row.
	 * @param float    $amount_toman  Amount in toman.
	 * @param string   $platform      telegram|bale.
	 * @param string   $url_optional  Optional open-in-bank link.
	 * @return array<string, mixed>
	 */
	public static function inline_invoice_actions( $card, $amount_toman, $platform, $url_optional = '' ) {
		$am = (string) (int) round( (float) $amount_toman );
		if ( SimpleVPBot_Model_Card::is_crypto_manual( $card ) ) {
			$addr = trim( (string) $card->card_number );
			$rows  = array();
			if ( $addr !== '' ) {
				$rows[] = array(
					array(
						'text'      => self::glass_button_text( '📋 کپی آدرس ولت' ),
						'copy_text' => array( 'text' => $addr ),
					),
				);
			}
			$rows[] = array(
				array(
					'text'      => self::glass_button_text( '💵 کپی مبلغ (تومان)' ),
					'copy_text' => array( 'text' => $am ),
				),
			);
			$note = trim( (string) ( $card->note ?? '' ) );
			if ( $note !== '' && strlen( $note ) <= 256 ) {
				$rows[] = array(
					array(
						'text'      => self::glass_button_text( '📝 کپی یادداشت / ممو' ),
						'copy_text' => array( 'text' => $note ),
					),
				);
			}
			$url = (string) $url_optional;
			if ( $url !== '' && (bool) filter_var( $url, FILTER_VALIDATE_URL ) ) {
				$rows[] = array( array( 'text' => self::glass_button_text( '🔗' ), 'url' => $url ) );
			}
			return array( 'inline_keyboard' => $rows );
		}
		$pan = preg_replace( '/\D+/', '', (string) $card->card_number );
		$rows = array(
			array(
				array(
					'text'      => self::glass_button_text( '📋 کپی شماره کارت' ),
					'copy_text' => array( 'text' => $pan ),
				),
			),
			array(
				array(
					'text'      => self::glass_button_text( '💵 کپی مبلغ (تومان)' ),
					'copy_text' => array( 'text' => $am ),
				),
			),
		);
		$url = (string) $url_optional;
		if ( $url !== '' && (bool) filter_var( $url, FILTER_VALIDATE_URL ) ) {
			$rows[] = array( array( 'text' => self::glass_button_text( '🔗' ), 'url' => $url ) );
		}
		return array( 'inline_keyboard' => $rows );
	}

	/**
	 * Receipt approve inline.
	 *
	 * @param int $receipt_id Receipt id.
	 * @return array<string, mixed>
	 */
	public static function inline_receipt( $receipt_id ) {
		return array(
			'inline_keyboard' => array(
				array(
					array(
						'text'          => self::glass_button_text( '✅ تایید رسید' ),
						'callback_data' => 'rc:a:' . (int) $receipt_id,
					),
					array(
						'text'          => self::glass_button_text( '❌ رد رسید' ),
						'callback_data' => 'rc:r:' . (int) $receipt_id,
					),
				),
			),
		);
	}

	/**
	 * Service list row buttons (one per service).
	 *
	 * @param array<int, object> $services Services.
	 * @return array<string, mixed>
	 */
	public static function inline_service_list( array $services ) {
		$rows = array();
		foreach ( $services as $s ) {
			$rows[] = array(
				array(
					'text'          => self::glass_button_text( '📡 ' . mb_substr( (string) $s->remark, 0, 24 ) ),
					'callback_data' => 'svc:m:' . (int) $s->id,
				),
			);
		}
		return array( 'inline_keyboard' => $rows );
	}

	/**
	 * Telegram: open portal URL + back (no copy rows).
	 *
	 * @param int                   $service_id Service id.
	 * @param array<string, string> $data        From get_portal_service_data (reserved).
	 * @param string                 $portal_url  Signed /info link.
	 * @return array<string, mixed>
	 */
	public static function inline_telegram_config_extras( $service_id, array $data, $portal_url ) {
		$id  = (int) $service_id;
		$pu  = (string) $portal_url;
		$rows = array();
		if ( $pu !== '' ) {
			$rows[] = array( array( 'text' => self::glass_button_text( 'پنل وب' ), 'url' => $pu ) );
		}
		$rows[] = array( array( 'text' => self::glass_button_text( '⬅️ بازگشت' ), 'callback_data' => 'svc:m:' . $id ) );
		return array( 'inline_keyboard' => $rows );
	}

	/**
	 * Bale: no config in chat; URL opens portal only.
	 *
	 * @param int    $service_id Service id.
	 * @param string $portal_url Portal URL.
	 * @return array<string, mixed>
	 */
	public static function inline_bale_portal_back( $service_id, $portal_url ) {
		$id = (int) $service_id;
		return array(
			'inline_keyboard' => array(
				array( array( 'text' => self::glass_button_text( '🌐 پنل وب (کانفیگ و QR)' ), 'url' => (string) $portal_url ) ),
				array( array( 'text' => self::glass_button_text( '⬅️ بازگشت' ), 'callback_data' => 'svc:m:' . $id ) ),
			),
		);
	}

	/**
	 * Service management menu (3 sections).
	 *
	 * @param int    $service_id Service id.
	 * @param string $platform  telegram|bale.
	 * @param int    $user_id    svp user id (for signed portal link on Bale).
	 * @param bool   $show_admin_soft_delete When true (platform admin managing another user's service), append adm:svc_del row.
	 * @return array<string, mixed>
	 */
	public static function inline_service_menu( $service_id, $platform, $user_id = 0, $is_l2tp = false, $show_admin_soft_delete = false ) {
		$id     = (int) $service_id;
		$uid    = (int) $user_id;
		$portal = ( $uid > 0 && $id > 0 ) ? SimpleVPBot_Portal_Link::build_service_url( $uid, $id ) : '';

		if ( $is_l2tp ) {
			$rows = array(
				array( array( 'text' => self::glass_button_text( '🔐 نمایش اتصال' ), 'callback_data' => 'svc:p:' . $id ) ),
				array( array( 'text' => self::glass_button_text( '📊 نمایش مصرف' ), 'callback_data' => 'svc:us:' . $id ) ),
			);
			if ( $portal ) {
				$rows[] = array( array( 'text' => self::glass_button_text( '🌐 پنل وب' ), 'url' => $portal ) );
			}
			$rows[] = array(
				array( 'text' => self::glass_button_text( '🔑 تغییر رمز عبور' ), 'callback_data' => 'svc:k:' . $id ),
				array( 'text' => self::glass_button_text( '♻️ تمدید سرویس' ), 'callback_data' => 'svc:r:' . $id ),
			);
			$rows[] = array(
				array( 'text' => self::glass_button_text( '🔁 تمدید خودکار' ), 'callback_data' => 'svc:ar:' . $id ),
				array( 'text' => self::glass_button_text( '🔔 هشدارها' ), 'callback_data' => 'svc:al:' . $id ),
			);
			$rows[] = array(
				array( 'text' => self::glass_button_text( '✏️ تغییر نام' ), 'callback_data' => 'svc:rn:' . $id ),
			);
			$rows[] = array(
				array( 'text' => self::glass_button_text( '❓ راهنمای اتصال' ), 'callback_data' => 'svc:f:' . $id ),
				array( 'text' => self::glass_button_text( '🆘 پشتیبانی' ), 'callback_data' => 'svc:su:' . $id ),
			);
			$rows[] = array( array( 'text' => self::glass_button_text( '🎁 انتقال سرویس' ), 'callback_data' => 'svc:tx:' . $id ) );
			if ( $show_admin_soft_delete ) {
				$cb = 'adm:svc_del:' . $id;
				if ( strlen( $cb ) <= 64 ) {
					$rows[] = array(
						array(
							'text'          => self::glass_button_text(
								SimpleVPBot_Texts::get( 'btn.admin.delete_service_soft', '🗑 حذف از لیست ربات (غیرفعال‌سازی)' ),
								64
							),
							'callback_data' => $cb,
						),
					);
				}
			}
			$rows[] = array( array( 'text' => self::glass_button_text( '⬅️ بازگشت' ), 'callback_data' => 'svc:b:' . $id ) );
			return array( 'inline_keyboard' => $rows );
		}

		$rows = array(
			array(
				array(
					'text'          => self::glass_button_text( SimpleVPBot_Texts::get( 'btn.service.show_panel', '🖥 جزئیات سرویس' ) ),
					'callback_data' => 'svc:p:' . $id,
				),
				array(
					'text'          => self::glass_button_text( '📊 نمایش مصرف' ),
					'callback_data' => 'svc:us:' . $id,
				),
			),
		);
		if ( 'bale' === $platform && $portal ) {
			$rows[] = array( array( 'text' => self::glass_button_text( '🌐 پنل وب (کانفیگ)' ), 'url' => $portal ) );
		} else {
			$rows[] = array( array( 'text' => self::glass_button_text( '🔗 کانفیگ و QR' ), 'callback_data' => 'svc:l:' . $id ) );
		}
		$rows[] = array(
			array( 'text' => self::glass_button_text( '🔑 بازسازی کلید' ), 'callback_data' => 'svc:k:' . $id ),
			array( 'text' => self::glass_button_text( '🔄 آپدیت سرورها' ), 'callback_data' => 'svc:u:' . $id ),
		);
		$renew_vol = array(
			array( 'text' => self::glass_button_text( '♻️ تمدید' ), 'callback_data' => 'svc:r:' . $id ),
			array( 'text' => self::glass_button_text( '➕ افزایش حجم' ), 'callback_data' => 'svc:v:' . $id ),
		);
		if ( (float) SimpleVPBot_Settings::get( 'price_per_extra_user', 0 ) > 0 ) {
			$renew_vol[] = array( 'text' => self::glass_button_text( '👥 افزایش کاربر' ), 'callback_data' => 'svc:sl:' . $id );
		}
		$rows[] = $renew_vol;
		$rows[] = array(
			array( 'text' => self::glass_button_text( '✏️ تغییر نام' ), 'callback_data' => 'svc:rn:' . $id ),
			array( 'text' => self::glass_button_text( '📝 یادداشت پنل' ), 'callback_data' => 'svc:n:' . $id ),
		);
		$rows[] = array( array( 'text' => self::glass_button_text( '🔔 هشدارها' ), 'callback_data' => 'svc:al:' . $id ) );
		$rows[] = array(
			array( 'text' => self::glass_button_text( '🌐 اتصالات فعال' ), 'callback_data' => 'svc:ip:' . $id ),
			array( 'text' => self::glass_button_text( '❓ سوالات متداول' ), 'callback_data' => 'svc:f:' . $id ),
		);
		$rows[] = array(
			array( 'text' => self::glass_button_text( '🎁 انتقال سرویس' ), 'callback_data' => 'svc:tx:' . $id ),
		);
		$rows[] = array(
			array( 'text' => self::glass_button_text( '🆘 پشتیبانی' ), 'callback_data' => 'svc:su:' . $id ),
			array( 'text' => self::glass_button_text( '⬅️ بازگشت' ), 'callback_data' => 'svc:b:' . $id ),
		);
		if ( $show_admin_soft_delete ) {
			$cb = 'adm:svc_del:' . $id;
			if ( strlen( $cb ) <= 64 ) {
				$rows[] = array(
					array(
						'text'          => self::glass_button_text(
							SimpleVPBot_Texts::get( 'btn.admin.delete_service_soft', '🗑 حذف از لیست ربات (غیرفعال‌سازی)' ),
							64
						),
						'callback_data' => $cb,
					),
				);
			}
		}
		return array( 'inline_keyboard' => $rows );
	}

	/**
	 * Copy link button row (Telegram CopyTextButton when supported; fallback url button).
	 *
	 * @param string $link Link.
	 * @param int    $service_id Service id for back.
	 * @return array<string, mixed>
	 */
	public static function inline_subscription_actions( $link, $service_id ) {
		return self::inline_subscription_back_only( (int) $service_id );
	}

	/**
	 * After usage panel: only back to service menu.
	 *
	 * @param int $service_id Service id.
	 * @return array<string, mixed>
	 */
	public static function inline_subscription_back_only( $service_id ) {
		return array(
			'inline_keyboard' => array(
				array(
					array(
						'text'          => self::glass_button_text( '⬅️ بازگشت به مدیریت سرویس' ),
						'callback_data' => 'svc:m:' . (int) $service_id,
					),
				),
			),
		);
	}
}
