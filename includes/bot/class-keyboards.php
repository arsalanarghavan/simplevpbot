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
	 * Localized label wrapped for inline/reply buttons.
	 *
	 * @param string      $key     Text key.
	 * @param object|null $user    Bot user row.
	 * @param int         $max_len Max length.
	 * @return string
	 */
	public static function i18n_btn( $key, $user = null, $max_len = 64 ) {
		return self::glass_button_text( SimpleVPBot_Texts::label( $key, $user ), $max_len );
	}

	/**
	 * Resolve bot user row for localized keyboards.
	 *
	 * @param int $svp_user_id svp_users.id.
	 * @return object|null
	 */
	private static function user_for_labels( $svp_user_id = 0 ) {
		$uid = (int) $svp_user_id;
		if ( $uid > 0 && class_exists( 'SimpleVPBot_Model_User' ) ) {
			return SimpleVPBot_Model_User::find( $uid );
		}
		return null;
	}

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
	 * @param object|null $user Bot user row for localized button labels.
	 * @return array<string, mixed>
	 */
	public static function user_main_reply( $user = null ) {
		if ( class_exists( 'SimpleVPBot_UI_Layout' ) ) {
			return SimpleVPBot_UI_Layout::build_reply_keyboard( 'user_main', $user );
		}
		$t = function ( $key ) use ( $user ) {
			return ( $user && is_object( $user ) )
				? SimpleVPBot_Texts::get_for_user( $key, $user )
				: SimpleVPBot_Texts::get( $key, '' );
		};
		return array(
			'keyboard'          => array(
				array(
					array( 'text' => $t( 'btn.main.buy' ) ),
					array( 'text' => $t( 'btn.main.manage' ) ),
				),
				array(
					array( 'text' => $t( 'btn.main.apps' ) ),
					array( 'text' => $t( 'btn.main.support' ) ),
				),
				array(
					array( 'text' => $t( 'btn.main.account' ) ),
					array( 'text' => $t( 'btn.main.wallet' ) ),
				),
				array(
					array( 'text' => $t( 'btn.main.referral' ) ),
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
	 * @param object|null $user Bot user row for localized labels.
	 * @return array<string, mixed>
	 */
	public static function admin_main_reply( $user = null ) {
		$rows = self::admin_main_keyboard_rows( $user );
		return array(
			'keyboard'          => $rows,
			'resize_keyboard'   => true,
			'one_time_keyboard' => false,
		);
	}

	/**
	 * Keyboard rows only (for merging with portal rows).
	 *
	 * @param object|null $user Bot user row for localized labels.
	 * @return array<int, array<int, array<string, string>>>
	 */
	public static function admin_main_keyboard_rows( $user = null ) {
		if ( class_exists( 'SimpleVPBot_UI_Layout' ) ) {
			$k = SimpleVPBot_UI_Layout::build_reply_keyboard( 'admin_main', $user );
			return isset( $k['keyboard'] ) && is_array( $k['keyboard'] ) ? $k['keyboard'] : array();
		}
		$t = function ( $key ) use ( $user ) {
			return ( $user && is_object( $user ) )
				? SimpleVPBot_Texts::get_for_user( $key, $user )
				: SimpleVPBot_Texts::get( $key, '' );
		};
		return array(
			array(
				array( 'text' => $t( 'btn.admin.dashboard' ) ),
				array( 'text' => $t( 'btn.admin.users' ) ),
			),
			array(
				array( 'text' => $t( 'btn.admin.finance' ) ),
				array( 'text' => $t( 'btn.admin.settings' ) ),
			),
			array(
				array( 'text' => $t( 'btn.admin.advanced' ) ),
				array( 'text' => $t( 'btn.admin.exit' ) ),
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
				$extra[] = array( array( 'text' => SimpleVPBot_Texts::get_for_user( 'btn.admin.send_my_portal', $me ) ) );
			}
			$adm = SimpleVPBot_Portal_Link::build_admin_url( (int) $me->id );
			if ( '' !== $adm ) {
				$extra[] = array( array( 'text' => SimpleVPBot_Texts::get_for_user( 'btn.admin.send_admin_portal', $me ) ) );
			}
		}
		$kb = self::admin_main_keyboard_rows( $me );
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
		$approve = SimpleVPBot_Texts::format( SimpleVPBot_Texts::get( 'btn.admin.reg_approve' ), array( 'id' => $uid ) );
		$reject  = SimpleVPBot_Texts::format( SimpleVPBot_Texts::get( 'btn.admin.reg_reject' ), array( 'id' => $uid ) );
		return self::admin_reply_wrap_rows(
			array(
				array(
					array( 'text' => self::glass_button_text( $approve, 256 ) ),
					array( 'text' => self::glass_button_text( $reject, 256 ) ),
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
		$approve = SimpleVPBot_Texts::format( SimpleVPBot_Texts::get( 'btn.admin.receipt_approve' ), array( 'id' => $rid ) );
		$reject  = SimpleVPBot_Texts::format( SimpleVPBot_Texts::get( 'btn.admin.receipt_reject' ), array( 'id' => $rid ) );
		return self::admin_reply_wrap_rows(
			array(
				array(
					array( 'text' => self::glass_button_text( $approve, 256 ) ),
					array( 'text' => self::glass_button_text( $reject, 256 ) ),
				),
			)
		);
	}

	/**
	 * Admin: زیرمنوی مدیریت کاربران (جستجو / صف ثبت‌نام).
	 *
	 * @param object|null $user Bot user row for localized labels.
	 * @return array<string, mixed>
	 */
	public static function admin_users_submenu_reply( $user = null ) {
		if ( class_exists( 'SimpleVPBot_UI_Layout' ) ) {
			return SimpleVPBot_UI_Layout::build_reply_submenu_with_back( 'admin_users_submenu', $user );
		}
		$t = function ( $key ) use ( $user ) {
			return SimpleVPBot_Texts::label( $key, $user );
		};
		return self::admin_reply_wrap_rows(
			array(
				array(
					array( 'text' => $t( 'btn.admin.users_search' ) ),
					array( 'text' => $t( 'btn.admin.users_queue' ) ),
				),
				array(
					array( 'text' => $t( 'btn.admin.transfer' ) ),
					array( 'text' => $t( 'btn.admin.bulk_short' ) ),
				),
				array(
					array( 'text' => $t( 'btn.admin.broadcast' ) ),
				),
			)
		);
	}

	/**
	 * Admin: مالی (رسیدها و …).
	 *
	 * @param object|null $user Bot user row.
	 * @return array<string, mixed>
	 */
	public static function admin_finance_submenu_reply( $user = null ) {
		if ( class_exists( 'SimpleVPBot_UI_Layout' ) ) {
			return SimpleVPBot_UI_Layout::build_reply_submenu_with_back( 'admin_finance_submenu', $user );
		}
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
	 * @param object|null $user Bot user row.
	 * @return array<string, mixed>
	 */
	public static function admin_settings_catalog_reply( $user = null ) {
		if ( class_exists( 'SimpleVPBot_UI_Layout' ) ) {
			return SimpleVPBot_UI_Layout::build_reply_submenu_with_back( 'admin_settings_catalog', $user );
		}
		$t = function ( $key ) use ( $user ) {
			return SimpleVPBot_Texts::label( $key, $user );
		};
		return self::admin_reply_wrap_rows(
			array(
				array(
					array( 'text' => $t( 'btn.admin.cat.plan_cats' ) ),
					array( 'text' => $t( 'btn.admin.cat.plans' ) ),
					array( 'text' => $t( 'btn.admin.cat.cards' ) ),
				),
				array(
					array( 'text' => $t( 'btn.admin.cat.panel' ) ),
					array( 'text' => $t( 'btn.admin.cat.l2tp' ) ),
					array( 'text' => $t( 'btn.admin.cat.config' ) ),
				),
				array(
					array( 'text' => $t( 'btn.admin.cat.crypto' ) ),
					array( 'text' => $t( 'btn.admin.cat.bots' ) ),
				),
			)
		);
	}

	/**
	 * Admin: تنظیمات پیشرفته — عمومی، نوتیف، متن، لاگ، گزارش همگانی.
	 *
	 * @param object|null $user Bot user row.
	 * @return array<string, mixed>
	 */
	public static function admin_settings_advanced_reply( $user = null ) {
		if ( class_exists( 'SimpleVPBot_UI_Layout' ) ) {
			return SimpleVPBot_UI_Layout::build_reply_submenu_with_back( 'admin_settings_advanced', $user );
		}
		$t = function ( $key ) use ( $user ) {
			return SimpleVPBot_Texts::label( $key, $user );
		};
		return self::admin_reply_wrap_rows(
			array(
				array(
					array( 'text' => $t( 'btn.admin.backup' ) ),
				),
				array(
					array( 'text' => $t( 'btn.admin.adv.general' ) ),
					array( 'text' => $t( 'btn.admin.adv.notif' ) ),
				),
				array(
					array( 'text' => $t( 'btn.admin.adv.texts' ) ),
					array( 'text' => $t( 'btn.admin.adv.logs' ) ),
				),
				array(
					array( 'text' => $t( 'btn.admin.adv.broadcast' ) ),
				),
			)
		);
	}

	/**
	 * Hub subsection: عمومی (Reply).
	 *
	 * @param object|null $user Bot user row.
	 * @return array<string, mixed>
	 */
	public static function admin_general_submenu_reply( $user = null ) {
		if ( class_exists( 'SimpleVPBot_UI_Layout' ) ) {
			return SimpleVPBot_UI_Layout::build_reply_submenu_with_back( 'admin_general_submenu', $user );
		}
		$t = function ( $key ) use ( $user ) {
			return SimpleVPBot_Texts::label( $key, $user );
		};
		return self::admin_reply_wrap_rows(
			array(
				array(
					array( 'text' => $t( 'btn.admin.hub.toggle_enabled' ) ),
					array( 'text' => $t( 'btn.admin.hub.toggle_test' ) ),
				),
				array(
					array( 'text' => $t( 'btn.admin.wiz.gen_at' ) ),
					array( 'text' => $t( 'btn.admin.wiz.gen_ab' ) ),
				),
				array( array( 'text' => $t( 'btn.admin.wiz.gen_pp' ) ) ),
				array( array( 'text' => $t( 'btn.admin.wiz.gen_dp' ) ) ),
			)
		);
	}

	/**
	 * Hub subsection: ربات‌ها.
	 *
	 * @param object|null $user Bot user row.
	 * @return array<string, mixed>
	 */
	public static function admin_bot_submenu_reply( $user = null ) {
		if ( class_exists( 'SimpleVPBot_UI_Layout' ) ) {
			return SimpleVPBot_UI_Layout::build_reply_submenu_with_back( 'admin_bot_submenu', $user );
		}
		$t = function ( $key ) use ( $user ) {
			return SimpleVPBot_Texts::label( $key, $user );
		};
		return self::admin_reply_wrap_rows(
			array(
				array(
					array( 'text' => $t( 'btn.admin.op.getme' ) ),
					array( 'text' => $t( 'btn.admin.op.wh_tg' ) ),
					array( 'text' => $t( 'btn.admin.op.wh_bl' ) ),
				),
				array(
					array( 'text' => $t( 'btn.admin.wiz.bot_tt' ) ),
					array( 'text' => $t( 'btn.admin.wiz.bot_bt' ) ),
				),
				array(
					array( 'text' => $t( 'btn.admin.wiz.bot_ts' ) ),
					array( 'text' => $t( 'btn.admin.wiz.bot_bs' ) ),
				),
				array(
					array( 'text' => $t( 'btn.admin.wiz.bot_th' ) ),
					array( 'text' => $t( 'btn.admin.wiz.bot_bw' ) ),
				),
			)
		);
	}

	/**
	 * Hub subsection: پنل 3x-ui.
	 *
	 * @param object|null $user Bot user row.
	 * @return array<string, mixed>
	 */
	public static function admin_panel_submenu_reply( $user = null ) {
		if ( class_exists( 'SimpleVPBot_UI_Layout' ) ) {
			return SimpleVPBot_UI_Layout::build_reply_submenu_with_back( 'admin_panel_submenu', $user );
		}
		$t = function ( $key ) use ( $user ) {
			return SimpleVPBot_Texts::label( $key, $user );
		};
		return self::admin_reply_wrap_rows(
			array(
				array( array( 'text' => $t( 'btn.admin.op.pan_test' ) ) ),
				array(
					array( 'text' => $t( 'btn.admin.wiz.pan_u' ) ),
					array( 'text' => $t( 'btn.admin.wiz.pan_n' ) ),
					array( 'text' => $t( 'btn.admin.wiz.pan_p' ) ),
				),
				array(
					array( 'text' => $t( 'btn.admin.wiz.pan_a' ) ),
					array( 'text' => $t( 'btn.admin.wiz.pan_l' ) ),
				),
				array( array( 'text' => $t( 'btn.admin.wiz.pan_s' ) ) ),
			)
		);
	}

	/**
	 * Hub subsection: نوتیف.
	 *
	 * @param object|null $user Bot user row.
	 * @return array<string, mixed>
	 */
	public static function admin_notif_submenu_reply( $user = null ) {
		if ( class_exists( 'SimpleVPBot_UI_Layout' ) ) {
			return SimpleVPBot_UI_Layout::build_reply_submenu_with_back( 'admin_notif_submenu', $user );
		}
		$t = function ( $key ) use ( $user ) {
			return SimpleVPBot_Texts::label( $key, $user );
		};
		return self::admin_reply_wrap_rows(
			array(
				array( array( 'text' => $t( 'btn.admin.wiz.not_l' ) ) ),
				array( array( 'text' => $t( 'btn.admin.wiz.not_e' ) ) ),
				array( array( 'text' => $t( 'btn.admin.wiz.not_d' ) ) ),
				array( array( 'text' => $t( 'btn.admin.wiz.not_p' ) ) ),
			)
		);
	}

	/**
	 * Hub subsection: گروهی Xray.
	 *
	 * @param object|null $user Bot user row.
	 * @return array<string, mixed>
	 */
	public static function admin_bulk_submenu_reply( $user = null ) {
		if ( class_exists( 'SimpleVPBot_UI_Layout' ) ) {
			return SimpleVPBot_UI_Layout::build_reply_submenu_with_back( 'admin_bulk_submenu', $user );
		}
		$t = function ( $key ) use ( $user ) {
			return SimpleVPBot_Texts::label( $key, $user );
		};
		return self::admin_reply_wrap_rows(
			array(
				array(
					array( 'text' => $t( 'btn.admin.bulk.d1' ) ),
					array( 'text' => $t( 'btn.admin.bulk.d7' ) ),
					array( 'text' => $t( 'btn.admin.bulk.d30' ) ),
				),
				array(
					array( 'text' => $t( 'btn.admin.bulk.g1' ) ),
					array( 'text' => $t( 'btn.admin.bulk.g5' ) ),
				),
				array( array( 'text' => $t( 'btn.admin.bulk.confirm_text' ) ) ),
			)
		);
	}

	/**
	 * Hub subsection: کانفیگ / inbound.
	 *
	 * @param object|null $user Bot user row.
	 * @return array<string, mixed>
	 */
	public static function admin_inbound_submenu_reply( $user = null ) {
		if ( class_exists( 'SimpleVPBot_UI_Layout' ) ) {
			return SimpleVPBot_UI_Layout::build_reply_submenu_with_back( 'admin_inbound_submenu', $user );
		}
		return self::admin_reply_wrap_rows(
			array(
				array( array( 'text' => SimpleVPBot_Texts::label( 'btn.admin.inbound.list', $user ) ) ),
			)
		);
	}

	/**
	 * Hub subsection: کریپتو.
	 *
	 * @param object|null $user Bot user row.
	 * @return array<string, mixed>
	 */
	public static function admin_crypto_submenu_reply( $user = null ) {
		if ( class_exists( 'SimpleVPBot_UI_Layout' ) ) {
			return SimpleVPBot_UI_Layout::build_reply_submenu_with_back( 'admin_crypto_submenu', $user );
		}
		$t = function ( $key ) use ( $user ) {
			return SimpleVPBot_Texts::label( $key, $user );
		};
		return self::admin_reply_wrap_rows(
			array(
				array(
					array( 'text' => $t( 'btn.admin.wiz.cry_ak' ) ),
					array( 'text' => $t( 'btn.admin.wiz.cry_in' ) ),
					array( 'text' => $t( 'btn.admin.wiz.cry_cu' ) ),
				),
				array( array( 'text' => $t( 'btn.admin.hub.crypto_ipn_path' ) ) ),
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
						'text'          => self::i18n_btn( 'btn.approve' ),
						'callback_data' => 'reg:a:' . (int) $user_id,
					),
					array(
						'text'          => self::i18n_btn( 'btn.reject' ),
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
	public static function inline_card_payment( array $cards, $transaction_id, $amount_toman, $platform, $user = null ) {
		$rows   = array();
		$amount_plain = (string) (int) round( (float) $amount_toman );
		foreach ( $cards as $c ) {
			$pan = preg_replace( '/\D+/', '', (string) $c->card_number );
			$label = SimpleVPBot_Texts::format(
				SimpleVPBot_Texts::label( 'btn.pay.card_label', $user ),
				array(
					'suffix' => mb_substr( $pan, -4 ),
					'holder' => mb_substr( (string) $c->holder_name, 0, 10 ),
				)
			);
			$row = array(
				array(
					'text'          => self::glass_button_text( $label, 64 ),
					'callback_data' => 'buy:cd:' . (int) $c->id . ':' . (int) $transaction_id,
				),
			);
			if ( 'telegram' === $platform ) {
				$row[] = array(
					'text'      => self::i18n_btn( 'btn.common.copy_card' ),
					'copy_text' => array( 'text' => $pan ),
				);
			}
			$rows[] = $row;
		}
		if ( 'telegram' === $platform ) {
			$rows[] = array(
				array(
					'text'      => self::i18n_btn( 'btn.common.copy_amount' ),
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
	public static function inline_payment_method( array $cards, $transaction_id, $show_bale_wallet = false, $show_site_wallet = false, $user = null ) {
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
		if ( $show_site_wallet && $tid > 0 ) {
			$sw = 'buy:sw:' . $tid;
			if ( strlen( $sw ) <= 64 ) {
				$rows[] = array(
					array(
						'text'          => self::i18n_btn( 'btn.pay.site_wallet', $user ),
						'callback_data' => $sw,
					),
				);
			}
		}
		if ( $show_bale_wallet && $tid > 0 ) {
			$bw = 'buy:bw:' . $tid;
			if ( strlen( $bw ) <= 64 ) {
				$rows[] = array(
					array(
						'text'          => self::i18n_btn( 'btn.pay.bale_wallet', $user ),
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
	public static function inline_invoice_actions( $card, $amount_toman, $platform, $url_optional = '', $user = null ) {
		$am = (string) (int) round( (float) $amount_toman );
		if ( SimpleVPBot_Model_Card::is_crypto_manual( $card ) ) {
			$addr = trim( (string) $card->card_number );
			$rows  = array();
			if ( $addr !== '' ) {
				$rows[] = array(
					array(
						'text'      => self::i18n_btn( 'btn.common.copy_wallet', $user ),
						'copy_text' => array( 'text' => $addr ),
					),
				);
			}
			$rows[] = array(
				array(
					'text'      => self::i18n_btn( 'btn.common.copy_amount_toman', $user ),
					'copy_text' => array( 'text' => $am ),
				),
			);
			$note = trim( (string) ( $card->note ?? '' ) );
			if ( $note !== '' && strlen( $note ) <= 256 ) {
				$rows[] = array(
					array(
						'text'      => self::i18n_btn( 'btn.common.copy_memo', $user ),
						'copy_text' => array( 'text' => $note ),
					),
				);
			}
			$url = (string) $url_optional;
			if ( $url !== '' && (bool) filter_var( $url, FILTER_VALIDATE_URL ) ) {
				$rows[] = array( array( 'text' => self::i18n_btn( 'btn.common.link', $user ), 'url' => $url ) );
			}
			return array( 'inline_keyboard' => $rows );
		}
		$pan = preg_replace( '/\D+/', '', (string) $card->card_number );
		$rows = array(
			array(
				array(
					'text'      => self::i18n_btn( 'btn.common.copy_card_number', $user ),
					'copy_text' => array( 'text' => $pan ),
				),
			),
			array(
				array(
					'text'      => self::i18n_btn( 'btn.common.copy_amount_toman', $user ),
					'copy_text' => array( 'text' => $am ),
				),
			),
		);
		$url = (string) $url_optional;
		if ( $url !== '' && (bool) filter_var( $url, FILTER_VALIDATE_URL ) ) {
			$rows[] = array( array( 'text' => self::i18n_btn( 'btn.common.link', $user ), 'url' => $url ) );
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
						'text'          => self::i18n_btn( 'btn.pay.approve_receipt' ),
						'callback_data' => 'rc:a:' . (int) $receipt_id,
					),
					array(
						'text'          => self::i18n_btn( 'btn.pay.reject_receipt' ),
						'callback_data' => 'rc:r:' . (int) $receipt_id,
					),
				),
			),
		);
	}

	/**
	 * Receipt reject: pick a configured reason (inline on admin receipt photo).
	 *
	 * @param int $receipt_id Receipt id.
	 * @return array<string, mixed>
	 */
	public static function inline_receipt_reject_reasons( $receipt_id ) {
		$rid     = (int) $receipt_id;
		$reasons = class_exists( 'SimpleVPBot_Receipt_Processor' )
			? SimpleVPBot_Receipt_Processor::reject_reasons_list()
			: array();
		$rows    = array();
		foreach ( array_slice( $reasons, 0, 8 ) as $idx => $text ) {
			$rows[] = array(
				array(
					'text'          => self::glass_button_text( (string) $text, 60 ),
					'callback_data' => 'rc:rr:' . $rid . ':' . (int) $idx,
				),
			);
		}
		if ( empty( $rows ) ) {
			$rows[] = array(
				array(
					'text'          => self::glass_button_text( '❌ رد رسید', 60 ),
					'callback_data' => 'rc:rr:' . $rid . ':0',
				),
			);
		}
		$rows[] = array(
			array(
				'text'          => self::i18n_btn( 'btn.pay.receipt_reject_back' ),
				'callback_data' => 'rc:rb:' . $rid,
			),
		);
		return array( 'inline_keyboard' => $rows );
	}

	/**
	 * Service list row buttons (one per service).
	 *
	 * @param array<int, object> $services Services.
	 * @return array<string, mixed>
	 */
	public static function inline_service_list( array $services, $user = null ) {
		$rows = array();
		foreach ( $services as $s ) {
			$svc_label = class_exists( 'SimpleVPBot_Service_Naming' )
				? SimpleVPBot_Service_Naming::public_label_for_service( $s )
				: (string) $s->remark;
			$label = SimpleVPBot_Texts::format(
				SimpleVPBot_Texts::label( 'btn.svc.list_item', $user ),
				array( 'remark' => mb_substr( $svc_label, 0, 24 ) )
			);
			$rows[] = array(
				array(
					'text'          => self::glass_button_text( $label, 64 ),
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
	public static function inline_telegram_config_extras( $service_id, array $data, $portal_url, $user = null ) {
		$id  = (int) $service_id;
		$pu  = (string) $portal_url;
		$rows = array();
		if ( $pu !== '' ) {
			$rows[] = array( array( 'text' => self::i18n_btn( 'btn.common.web_panel', $user ), 'url' => $pu ) );
		}
		$rows[] = array( array( 'text' => self::i18n_btn( 'btn.common.back', $user ), 'callback_data' => 'svc:m:' . $id ) );
		return array( 'inline_keyboard' => $rows );
	}

	/**
	 * Bale: no config in chat; URL opens portal only.
	 *
	 * @param int    $service_id Service id.
	 * @param string $portal_url Portal URL.
	 * @return array<string, mixed>
	 */
	public static function inline_bale_portal_back( $service_id, $portal_url, $user = null ) {
		$id = (int) $service_id;
		return array(
			'inline_keyboard' => array(
				array( array( 'text' => self::i18n_btn( 'btn.common.web_panel_cfg_qr', $user ), 'url' => (string) $portal_url ) ),
				array( array( 'text' => self::i18n_btn( 'btn.common.back', $user ), 'callback_data' => 'svc:m:' . $id ) ),
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
		$u      = self::user_for_labels( $uid );
		$portal = ( $uid > 0 && $id > 0 ) ? SimpleVPBot_Portal_Link::build_service_url( $uid, $id ) : '';

		if ( $is_l2tp ) {
			if ( class_exists( 'SimpleVPBot_UI_Layout' ) ) {
				return self::inline_service_menu_l2tp_from_layout( $id, $portal, $show_admin_soft_delete, $uid );
			}
			$rows = array(
				array( array( 'text' => self::i18n_btn( 'btn.svc.show_connection', $u ), 'callback_data' => 'svc:p:' . $id ) ),
				array( array( 'text' => self::i18n_btn( 'btn.svc.show_usage', $u ), 'callback_data' => 'svc:us:' . $id ) ),
			);
			if ( $portal ) {
				$rows[] = array( array( 'text' => self::i18n_btn( 'btn.common.web_panel', $u ), 'url' => $portal ) );
			}
			$rows[] = array(
				array( 'text' => self::i18n_btn( 'btn.svc.change_password', $u ), 'callback_data' => 'svc:k:' . $id ),
				array( 'text' => self::i18n_btn( 'btn.svc.renew', $u ), 'callback_data' => 'svc:r:' . $id ),
			);
			$rows[] = array(
				array( 'text' => self::i18n_btn( 'btn.svc.auto_renew', $u ), 'callback_data' => 'svc:ar:' . $id ),
				array( 'text' => self::i18n_btn( 'btn.svc.alerts', $u ), 'callback_data' => 'svc:al:' . $id ),
			);
			$rows[] = array(
				array( 'text' => self::i18n_btn( 'btn.svc.rename', $u ), 'callback_data' => 'svc:rn:' . $id ),
			);
			$rows[] = array(
				array( 'text' => self::i18n_btn( 'btn.svc.faq', $u ), 'callback_data' => 'svc:f:' . $id ),
				array( 'text' => self::i18n_btn( 'btn.svc.support', $u ), 'callback_data' => 'svc:su:' . $id ),
			);
			$rows[] = array( array( 'text' => self::i18n_btn( 'btn.svc.transfer', $u ), 'callback_data' => 'svc:tx:' . $id ) );
			if ( $show_admin_soft_delete ) {
				$cb = 'adm:svc_del:' . $id;
				if ( strlen( $cb ) <= 64 ) {
					$rows[] = array(
						array(
							'text'          => self::i18n_btn( 'btn.admin.delete_service_soft', $u, 64 ),
							'callback_data' => $cb,
						),
					);
				}
			}
			$rows[] = array( array( 'text' => self::i18n_btn( 'btn.common.back', $u ), 'callback_data' => 'svc:b:' . $id ) );
			return array( 'inline_keyboard' => $rows );
		}

		if ( class_exists( 'SimpleVPBot_UI_Layout' ) ) {
			return self::inline_service_menu_xray_from_layout( $id, $platform, $uid, $portal, $show_admin_soft_delete );
		}

		$rows = array(
			array(
				array(
					'text'          => self::i18n_btn( 'btn.service.show_panel', $u ),
					'callback_data' => 'svc:p:' . $id,
				),
				array(
					'text'          => self::i18n_btn( 'btn.svc.show_usage', $u ),
					'callback_data' => 'svc:us:' . $id,
				),
			),
		);
		if ( 'bale' === $platform && $portal ) {
			$rows[] = array( array( 'text' => self::i18n_btn( 'btn.common.web_panel_cfg', $u ), 'url' => $portal ) );
		} else {
			$rows[] = array( array( 'text' => self::i18n_btn( 'btn.svc.config_qr', $u ), 'callback_data' => 'svc:l:' . $id ) );
		}
		$rows[] = array(
			array( 'text' => self::i18n_btn( 'btn.svc.regenerate_key', $u ), 'callback_data' => 'svc:k:' . $id ),
			array( 'text' => self::i18n_btn( 'btn.svc.update_servers', $u ), 'callback_data' => 'svc:u:' . $id ),
		);
		$renew_vol = array(
			array( 'text' => self::i18n_btn( 'btn.svc.renew_short', $u ), 'callback_data' => 'svc:r:' . $id ),
			array( 'text' => self::i18n_btn( 'btn.svc.add_volume', $u ), 'callback_data' => 'svc:v:' . $id ),
		);
		if ( (float) SimpleVPBot_Settings::get( 'price_per_extra_user', 0 ) > 0 ) {
			$renew_vol[] = array( 'text' => self::i18n_btn( 'btn.svc.add_users', $u ), 'callback_data' => 'svc:sl:' . $id );
		}
		$rows[] = $renew_vol;
		$rows[] = array(
			array( 'text' => self::i18n_btn( 'btn.svc.rename', $u ), 'callback_data' => 'svc:rn:' . $id ),
			array( 'text' => self::i18n_btn( 'btn.svc.panel_note', $u ), 'callback_data' => 'svc:n:' . $id ),
		);
		$rows[] = array( array( 'text' => self::i18n_btn( 'btn.svc.alerts', $u ), 'callback_data' => 'svc:al:' . $id ) );
		$rows[] = array(
			array( 'text' => self::i18n_btn( 'btn.svc.active_connections', $u ), 'callback_data' => 'svc:ip:' . $id ),
			array( 'text' => self::i18n_btn( 'btn.svc.faq_short', $u ), 'callback_data' => 'svc:f:' . $id ),
		);
		$rows[] = array(
			array( 'text' => self::i18n_btn( 'btn.svc.transfer', $u ), 'callback_data' => 'svc:tx:' . $id ),
		);
		$rows[] = array(
			array( 'text' => self::i18n_btn( 'btn.svc.support', $u ), 'callback_data' => 'svc:su:' . $id ),
			array( 'text' => self::i18n_btn( 'btn.common.back', $u ), 'callback_data' => 'svc:b:' . $id ),
		);
		if ( $show_admin_soft_delete ) {
			$cb = 'adm:svc_del:' . $id;
			if ( strlen( $cb ) <= 64 ) {
				$rows[] = array(
					array(
						'text'          => self::i18n_btn( 'btn.admin.delete_service_soft', $u, 64 ),
						'callback_data' => $cb,
					),
				);
			}
		}
		return array( 'inline_keyboard' => $rows );
	}

	/**
	 * Xray service menu from Bot UI layout (svc_menu_xray).
	 *
	 * @param int         $service_id Service id.
	 * @param string      $platform   telegram|bale.
	 * @param int         $user_id    svp user id.
	 * @param string      $portal_url Signed portal URL.
	 * @param bool        $show_admin_soft_delete Soft-delete row for admins.
	 * @return array<string, mixed>
	 */
	private static function inline_service_menu_xray_from_layout( $service_id, $platform, $user_id, $portal_url, $show_admin_soft_delete ) {
		$id          = (int) $service_id;
		$portal      = (string) $portal_url;
		$price_slots = (float) SimpleVPBot_Settings::get( 'price_per_extra_user', 0 ) > 0;
		$rows        = array();
		$layout_rows = SimpleVPBot_UI_Layout::effective_rows_for_surface( 'svc_menu_xray' );
		foreach ( $layout_rows as $line ) {
			$rline = array();
			foreach ( $line as $cell ) {
				if ( empty( $cell['enabled'] ) ) {
					continue;
				}
				$slot = (string) ( $cell['id'] ?? '' );
				if ( 'svc_xray.slots' === $slot && ! $price_slots ) {
					continue;
				}
				if ( 'svc_xray.del_admin' === $slot && ! $show_admin_soft_delete ) {
					continue;
				}
				$b = self::svc_xray_slot_button( $slot, $id, $platform, $portal, self::user_for_labels( $user_id ) );
				if ( array() !== $b ) {
					$rline[] = $b;
				}
			}
			if ( array() !== $rline ) {
				$rows[] = $rline;
			}
		}
		return array( 'inline_keyboard' => $rows );
	}

	/**
	 * Single inline button for an Xray layout slot.
	 *
	 * @param string $slot       Slot id.
	 * @param int    $service_id Service id.
	 * @param string $platform   telegram|bale.
	 * @param string $portal_url Portal URL.
	 * @return array<string, string>
	 */
	private static function svc_xray_slot_button( $slot, $service_id, $platform, $portal_url, $user = null ) {
		$id = (int) $service_id;
		$pu = (string) $portal_url;
		switch ( $slot ) {
			case 'svc_xray.panel':
				return array(
					'text'          => self::i18n_btn( 'btn.service.show_panel', $user, 64 ),
					'callback_data' => 'svc:p:' . $id,
				);
			case 'svc_xray.usage':
				return array(
					'text'          => self::i18n_btn( 'btn.svc.show_usage', $user, 64 ),
					'callback_data' => 'svc:us:' . $id,
				);
			case 'svc_xray.config':
				if ( 'bale' === $platform && $pu !== '' ) {
					return array(
						'text' => self::i18n_btn( 'btn.common.web_panel_cfg', $user, 64 ),
						'url'  => $pu,
					);
				}
				return array(
					'text'          => self::i18n_btn( 'btn.svc.config_qr', $user, 64 ),
					'callback_data' => 'svc:l:' . $id,
				);
			case 'svc_xray.regen':
				return array(
					'text'          => self::i18n_btn( 'btn.svc.regenerate_key', $user, 64 ),
					'callback_data' => 'svc:k:' . $id,
				);
			case 'svc_xray.refresh':
				return array(
					'text'          => self::i18n_btn( 'btn.svc.update_servers', $user, 64 ),
					'callback_data' => 'svc:u:' . $id,
				);
			case 'svc_xray.renew':
				return array(
					'text'          => self::i18n_btn( 'btn.svc.renew_short', $user, 64 ),
					'callback_data' => 'svc:r:' . $id,
				);
			case 'svc_xray.volume':
				return array(
					'text'          => self::i18n_btn( 'btn.svc.add_volume', $user, 64 ),
					'callback_data' => 'svc:v:' . $id,
				);
			case 'svc_xray.slots':
				return array(
					'text'          => self::i18n_btn( 'btn.svc.add_users', $user, 64 ),
					'callback_data' => 'svc:sl:' . $id,
				);
			case 'svc_xray.rename':
				return array(
					'text'          => self::i18n_btn( 'btn.svc.rename', $user, 64 ),
					'callback_data' => 'svc:rn:' . $id,
				);
			case 'svc_xray.note':
				return array(
					'text'          => self::i18n_btn( 'btn.svc.panel_note', $user, 64 ),
					'callback_data' => 'svc:n:' . $id,
				);
			case 'svc_xray.alerts':
				return array(
					'text'          => self::i18n_btn( 'btn.svc.alerts', $user, 64 ),
					'callback_data' => 'svc:al:' . $id,
				);
			case 'svc_xray.ip':
				return array(
					'text'          => self::i18n_btn( 'btn.svc.active_connections', $user, 64 ),
					'callback_data' => 'svc:ip:' . $id,
				);
			case 'svc_xray.faq':
				return array(
					'text'          => self::i18n_btn( 'btn.svc.faq_short', $user, 64 ),
					'callback_data' => 'svc:f:' . $id,
				);
			case 'svc_xray.transfer':
				return array(
					'text'          => self::i18n_btn( 'btn.svc.transfer', $user, 64 ),
					'callback_data' => 'svc:tx:' . $id,
				);
			case 'svc_xray.support':
				return array(
					'text'          => self::i18n_btn( 'btn.svc.support', $user, 64 ),
					'callback_data' => 'svc:su:' . $id,
				);
			case 'svc_xray.back':
				return array(
					'text'          => self::i18n_btn( 'btn.common.back', $user, 64 ),
					'callback_data' => 'svc:b:' . $id,
				);
			case 'svc_xray.del_admin':
				$cb = 'adm:svc_del:' . $id;
				if ( strlen( $cb ) > 64 ) {
					return array();
				}
				return array(
					'text'          => self::i18n_btn( 'btn.admin.delete_service_soft', $user, 64 ),
					'callback_data' => $cb,
				);
			default:
				return array();
		}
	}

	/**
	 * L2TP service menu from Bot UI layout (svc_menu_l2tp).
	 *
	 * @param int    $service_id Service id.
	 * @param string $portal_url Portal URL.
	 * @param bool   $show_admin_soft_delete Admin delete row.
	 * @return array<string, mixed>
	 */
	private static function inline_service_menu_l2tp_from_layout( $service_id, $portal_url, $show_admin_soft_delete, $user_id = 0 ) {
		$id          = (int) $service_id;
		$portal      = (string) $portal_url;
		$rows        = array();
		$layout_rows = SimpleVPBot_UI_Layout::effective_rows_for_surface( 'svc_menu_l2tp' );
		foreach ( $layout_rows as $line ) {
			$rline = array();
			foreach ( $line as $cell ) {
				if ( empty( $cell['enabled'] ) ) {
					continue;
				}
				$slot = (string) ( $cell['id'] ?? '' );
				if ( 'svc_l2tp.del_admin' === $slot && ! $show_admin_soft_delete ) {
					continue;
				}
				$b = self::svc_l2tp_slot_button( $slot, $id, $portal, self::user_for_labels( $user_id ) );
				if ( array() !== $b ) {
					$rline[] = $b;
				}
			}
			if ( array() !== $rline ) {
				$rows[] = $rline;
			}
		}
		return array( 'inline_keyboard' => $rows );
	}

	/**
	 * @param string $slot       Slot id.
	 * @param int    $service_id Id.
	 * @param string $portal_url Portal.
	 * @return array<string, string>
	 */
	private static function svc_l2tp_slot_button( $slot, $service_id, $portal_url, $user = null ) {
		$id = (int) $service_id;
		$pu = (string) $portal_url;
		switch ( $slot ) {
			case 'svc_l2tp.conn':
				return array(
					'text'          => self::i18n_btn( 'btn.svc.show_connection', $user, 64 ),
					'callback_data' => 'svc:p:' . $id,
				);
			case 'svc_l2tp.usage':
				return array(
					'text'          => self::i18n_btn( 'btn.svc.show_usage', $user, 64 ),
					'callback_data' => 'svc:us:' . $id,
				);
			case 'svc_l2tp.portal':
				if ( '' === $pu ) {
					return array();
				}
				return array(
					'text' => self::i18n_btn( 'btn.common.web_panel', $user, 64 ),
					'url'  => $pu,
				);
			case 'svc_l2tp.pass':
				return array(
					'text'          => self::i18n_btn( 'btn.svc.change_password', $user, 64 ),
					'callback_data' => 'svc:k:' . $id,
				);
			case 'svc_l2tp.renew':
				return array(
					'text'          => self::i18n_btn( 'btn.svc.renew', $user, 64 ),
					'callback_data' => 'svc:r:' . $id,
				);
			case 'svc_l2tp.autorenew':
				return array(
					'text'          => self::i18n_btn( 'btn.svc.auto_renew', $user, 64 ),
					'callback_data' => 'svc:ar:' . $id,
				);
			case 'svc_l2tp.alerts':
				return array(
					'text'          => self::i18n_btn( 'btn.svc.alerts', $user, 64 ),
					'callback_data' => 'svc:al:' . $id,
				);
			case 'svc_l2tp.rename':
				return array(
					'text'          => self::i18n_btn( 'btn.svc.rename', $user, 64 ),
					'callback_data' => 'svc:rn:' . $id,
				);
			case 'svc_l2tp.faq':
				return array(
					'text'          => self::i18n_btn( 'btn.svc.faq', $user, 64 ),
					'callback_data' => 'svc:f:' . $id,
				);
			case 'svc_l2tp.support':
				return array(
					'text'          => self::i18n_btn( 'btn.svc.support', $user, 64 ),
					'callback_data' => 'svc:su:' . $id,
				);
			case 'svc_l2tp.transfer':
				return array(
					'text'          => self::i18n_btn( 'btn.svc.transfer', $user, 64 ),
					'callback_data' => 'svc:tx:' . $id,
				);
			case 'svc_l2tp.del_admin':
				$cb = 'adm:svc_del:' . $id;
				if ( strlen( $cb ) > 64 ) {
					return array();
				}
				return array(
					'text'          => self::i18n_btn( 'btn.admin.delete_service_soft', $user, 64 ),
					'callback_data' => $cb,
				);
			case 'svc_l2tp.back':
				return array(
					'text'          => self::i18n_btn( 'btn.common.back', $user, 64 ),
					'callback_data' => 'svc:b:' . $id,
				);
			default:
				return array();
		}
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
	public static function inline_subscription_back_only( $service_id, $user = null ) {
		return array(
			'inline_keyboard' => array(
				array(
					array(
						'text'          => self::i18n_btn( 'btn.svc.back_manage', $user ),
						'callback_data' => 'svc:m:' . (int) $service_id,
					),
				),
			),
		);
	}
}
