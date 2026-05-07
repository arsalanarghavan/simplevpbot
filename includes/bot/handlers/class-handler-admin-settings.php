<?php
/**
 * Admin hub: settings wizards, panel/Telegram ops, logs, users, inbound (bot-only admin).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Handler_Admin_Settings
 */
class SimpleVPBot_Handler_Admin_Settings {

	/**
	 * Run service op (test panel, webhooks, …).
	 *
	 * @param array<string, mixed> $ctx Context.
	 * @param string               $code op code: pan|tg|wtg|wbl.
	 */
	public static function handle_op( array $ctx, $code ) {
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$code     = (string) $code;
		if ( 'pan' === $code ) {
			$r = SimpleVPBot_Service_Admin_Ops::test_panel();
		} elseif ( 'tg' === $code ) {
			$r = SimpleVPBot_Service_Admin_Ops::test_telegram();
		} elseif ( 'wtg' === $code ) {
			$r = SimpleVPBot_Service_Admin_Ops::set_webhook_telegram();
		} elseif ( 'wbl' === $code ) {
			$r = SimpleVPBot_Service_Admin_Ops::set_webhook_bale();
		} else {
			return;
		}
		$msg = ! empty( $r['ok'] ) ? ( '✅ ' . (string) ( $r['data']['message'] ?? 'OK' ) ) : ( '⛔ ' . (string) ( $r['message'] ?? 'err' ) );
		if ( ! empty( $r['data'] ) && is_array( $r['data'] ) && empty( $r['ok'] ) ) {
			$msg .= "\n" . mb_substr( wp_json_encode( $r['data'] ), 0, 2000 );
		}
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $msg );
	}

	/**
	 * @param array<string, mixed> $ctx Context with user.
	 * @param string               $a First part (e.g. gen, bot, pan, not).
	 * @param string               $b Second (e.g. at, tt).
	 */
	public static function start_wizard( array $ctx, $a, $b ) {
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$user     = isset( $ctx['user'] ) ? $ctx['user'] : null;
		if ( ! $user ) {
			return;
		}
		$map  = self::wizard_map();
		$key  = (string) $a . ':' . (string) $b;
		$info = isset( $map[ $key ] ) ? $map[ $key ] : null;
		if ( ! is_array( $info ) || empty( $info['st'] ) ) {
			return;
		}
		$st = (string) $info['st'];
		SimpleVPBot_State::set( (int) $user->id, $st, array() );
		$prompt = (string) ( $info['prompt'] ?? 'مقدار جدید را ارسال کنید. /cancel' );
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $prompt );
	}

	/**
	 * @return array<string, array{st: string, prompt: string}>
	 */
	private static function wizard_map() {
		return array(
			'gen:at' => array(
				'st'     => 'admin_set_g_at',
				'prompt' => "📥 آیدی ادمین‌های تلگرام (هر خط یک عدد):\n/cancel",
			),
			'gen:ab' => array(
				'st'     => 'admin_set_g_ab',
				'prompt' => "📥 آیدی ادمین‌های بله (هر خط یک عدد):\n/cancel",
			),
			'gen:pp' => array(
				'st'     => 'admin_set_g_pp',
				'prompt' => "📄 شناسه صفحه پورتال وب (عدد؛ 0=پیش‌فرض /info):\n/cancel",
			),
			'gen:dp' => array(
				'st'     => 'admin_set_g_dp',
				'prompt' => "📦 پلن پیش‌فرض سرویس‌های بدون پلن — شناسه پلن Xray فعال (0=خاموش):\n/cancel",
			),
			'bot:tt' => array( 'st' => 'admin_set_b_tt', 'prompt' => "🤖 Telegram token:\n/cancel" ),
			'bot:bt' => array( 'st' => 'admin_set_b_bt', 'prompt' => "🤖 Bale token:\n/cancel" ),
			'bot:ts' => array( 'st' => 'admin_set_b_ts', 'prompt' => "Secret مسیر Webhook تلگرام:\n/cancel" ),
			'bot:bs' => array( 'st' => 'admin_set_b_bs', 'prompt' => "Secret مسیر Webhook بله:\n/cancel" ),
			'bot:th' => array( 'st' => 'admin_set_b_th', 'prompt' => "Telegram secret header (اختیاری):\n/cancel" ),
			'bot:bw' => array( 'st' => 'admin_set_b_bw', 'prompt' => "Bale wallet provider token:\n/cancel" ),
			'pan:u'  => array( 'st' => 'admin_set_p_u', 'prompt' => "🖥 Panel URL (3x-ui):\n/cancel" ),
			'pan:n'  => array( 'st' => 'admin_set_p_n', 'prompt' => "🖥 نام کاربری پنل:\n/cancel" ),
			'pan:p'  => array( 'st' => 'admin_set_p_p', 'prompt' => "🖥 رمز پنل:\n/cancel" ),
			'pan:a'  => array( 'st' => 'admin_set_p_a', 'prompt' => "🖥 API base path (مثلاً panel/api):\n/cancel" ),
			'pan:l'  => array( 'st' => 'admin_set_p_l', 'prompt' => "🖥 Login secret (اختیاری):\n/cancel" ),
			'pan:s'  => array( 'st' => 'admin_set_p_s', 'prompt' => "🌐 subscription public base:\n/cancel" ),
			'not:l'  => array( 'st' => 'admin_set_n_l', 'prompt' => "🔔 آستانه حجم کم (٪) — حداقل ۱:\n/cancel" ),
			'not:e'  => array( 'st' => 'admin_set_n_e', 'prompt' => "🔔 روزهای هشدار (با کاما مثل 3,1):\n/cancel" ),
			'not:d'  => array( 'st' => 'admin_set_n_d', 'prompt' => "🔔 تعداد کاربر هم‌زمان پیش‌فرض (≥0):\n/cancel" ),
			'not:p'  => array( 'st' => 'admin_set_n_p', 'prompt' => "🔔 قیمت هر کاربر اضافه (تومان):\n/cancel" ),
			'cry:ak' => array( 'st' => 'admin_set_cry_ak', 'prompt' => "₿ NOWPayments API key:\n/cancel" ),
			'cry:in' => array( 'st' => 'admin_set_cry_in', 'prompt' => "₿ NOWPayments IPN secret:\n/cancel" ),
			'cry:cu' => array( 'st' => 'admin_set_cry_cu', 'prompt' => "₿ pay_currency (مثل usdttrc20):\n/cancel" ),
		);
	}

	/**
	 * @param array<string, mixed> $ctx route_text context.
	 * @return bool Whether handled.
	 */
	public static function route_wizard_text( array $ctx ) {
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$user     = $ctx['user'];
		$text     = trim( (string) $ctx['text'] );
		$st       = (string) $user->state;
		$data     = SimpleVPBot_State::data( $user );

		if ( 'admin_txt_edit' === $st && isset( $data['key'] ) ) {
			if ( '' === $text ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⏳ متن جدید را بفرستید یا /cancel' );
				return true;
			}
			$key = (string) $data['key'];
			if ( '' !== $key && mb_strlen( $text ) <= 12000 ) {
				SimpleVPBot_Model_Text::set( $key, $text, 'general' );
				SimpleVPBot_Texts::clear_cache();
				SimpleVPBot_State::clear( (int) $user->id );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '✅ متن «' . $key . '» ذخیره شد.' );
			} else {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ محتوا نامعتبر.' );
			}
			return true;
		}

		if ( 'admin_inb_uid' === $st && isset( $data['iid'], $data['em'] ) ) {
			if ( '' === $text ) {
				return false;
			}
			if ( ! is_numeric( SimpleVPBot_Bot_Runtime::normalize_digits( $text ) ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ فقط عدد svp_users.id را بفرستید یا /cancel' );
				return true;
			}
			$uid   = (int) trim( SimpleVPBot_Bot_Runtime::normalize_digits( $text ) );
			$iid   = (int) $data['iid'];
			$email = (string) $data['em'];
			$pn    = isset( $data['panel_id'] ) ? (int) $data['panel_id'] : 1;
			if ( $pn < 0 ) {
				$pn = 0;
			}
			$r     = SimpleVPBot_Service_Admin_Ops::inbound_link( $iid, $email, $uid, $pn );
			SimpleVPBot_State::clear( (int) $user->id );
			if ( ! empty( $r['ok'] ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '✅ ' . (string) ( $r['data']['message'] ?? 'لینک انجام شد.' ) );
			} else {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ ' . (string) ( $r['message'] ?? 'ناموفق' ) );
			}
			return true;
		}

		if ( 0 === strpos( $st, 'admin_w_' ) && '' !== $text ) {
			if ( self::route_catalog_wizard( $ctx, $st, $text ) ) {
				return true;
			}
		}
		if ( 0 === strpos( $st, 'admin_w_' ) ) {
			return false;
		}
		if ( 0 !== strpos( $st, 'admin_set_' ) || '' === $text ) {
			return false;
		}

		$ok  = false;
		$msg = '';
		switch ( $st ) {
			case 'admin_set_g_at':
				$ids = SimpleVPBot_Admin_Actions::parse_id_lines( $text );
				$ok  = SimpleVPBot_Admin_Actions::apply_settings_merge( 'general', array( 'admin_telegram_ids' => implode( "\n", $ids ) ) );
				$msg = '✅ آیدی ادمین تلگرام به‌روز شد (' . count( $ids ) . ' مورد).';
				break;
			case 'admin_set_g_ab':
				$ids = SimpleVPBot_Admin_Actions::parse_id_lines( $text );
				$ok  = SimpleVPBot_Admin_Actions::apply_settings_merge( 'general', array( 'admin_bale_ids' => implode( "\n", $ids ) ) );
				$msg = '✅ آیدی ادمین بله به‌روز شد (' . count( $ids ) . ' مورد).';
				break;
			case 'admin_set_g_pp':
				if ( is_numeric( SimpleVPBot_Bot_Runtime::normalize_digits( $text ) ) ) {
					$pp  = (int) trim( SimpleVPBot_Bot_Runtime::normalize_digits( $text ) );
					$ok  = SimpleVPBot_Admin_Actions::apply_settings_merge( 'general', array( 'portal_page_id' => max( 0, $pp ) ) );
					$msg = '✅ portal_page_id=' . (int) max( 0, $pp );
				}
				break;
			case 'admin_set_g_dp':
				if ( is_numeric( SimpleVPBot_Bot_Runtime::normalize_digits( $text ) ) ) {
					$dp  = (int) trim( SimpleVPBot_Bot_Runtime::normalize_digits( $text ) );
					$dp  = max( 0, $dp );
					$ok  = SimpleVPBot_Admin_Actions::apply_settings_merge( 'general', array( 'default_service_plan_id' => $dp ) );
					$msg = '✅ default_service_plan_id=' . $dp;
				}
				break;
			case 'admin_set_b_tt':
				$ok  = SimpleVPBot_Admin_Actions::apply_settings_merge( 'bots', array( 'telegram_token' => $text ) );
				$msg = '✅ توکن تلگرام ذخیره شد.';
				break;
			case 'admin_set_b_bt':
				$ok  = SimpleVPBot_Admin_Actions::apply_settings_merge( 'bots', array( 'bale_token' => $text ) );
				$msg = '✅ توکن بله ذخیره شد.';
				break;
			case 'admin_set_b_ts':
				$ok  = SimpleVPBot_Admin_Actions::apply_settings_merge( 'bots', array( 'telegram_webhook_secret' => $text ) );
				$msg = '✅ Webhook secret تلگرام ذخیره شد.';
				break;
			case 'admin_set_b_bs':
				$ok  = SimpleVPBot_Admin_Actions::apply_settings_merge( 'bots', array( 'bale_webhook_secret' => $text ) );
				$msg = '✅ Webhook secret بله ذخیره شد.';
				break;
			case 'admin_set_b_th':
				$ok  = SimpleVPBot_Admin_Actions::apply_settings_merge( 'bots', array( 'telegram_secret_header' => $text ) );
				$msg = '✅ header ذخیره شد.';
				break;
			case 'admin_set_b_bw':
				$ok  = SimpleVPBot_Admin_Actions::apply_settings_merge( 'bots', array( 'bale_wallet_provider_token' => $text ) );
				$msg = '✅ توکن کیف پول بله ذخیره شد.';
				break;
			case 'admin_set_p_u':
				$ok  = SimpleVPBot_Admin_Actions::apply_settings_merge( 'panel', array( 'panel_url' => $text ) );
				$msg = '✅ آدرس پنل ذخیره شد.';
				break;
			case 'admin_set_p_n':
				$ok  = SimpleVPBot_Admin_Actions::apply_settings_merge( 'panel', array( 'panel_username' => $text ) );
				$msg = '✅ نام کاربری ذخیره شد.';
				break;
			case 'admin_set_p_p':
				$ok  = SimpleVPBot_Admin_Actions::apply_settings_merge( 'panel', array( 'panel_password' => $text ) );
				$msg = '✅ رمز پنل ذخیره شد.';
				break;
			case 'admin_set_p_a':
				$ok  = SimpleVPBot_Admin_Actions::apply_settings_merge( 'panel', array( 'panel_api_base' => $text ? $text : 'panel/api' ) );
				$msg = '✅ API base ذخیره شد.';
				break;
			case 'admin_set_p_l':
				$ok  = SimpleVPBot_Admin_Actions::apply_settings_merge( 'panel', array( 'panel_login_secret' => $text ) );
				$msg = '✅ login secret ذخیره شد.';
				break;
			case 'admin_set_p_s':
				$ok  = SimpleVPBot_Admin_Actions::apply_settings_merge( 'panel', array( 'subscription_public_base' => $text ) );
				$msg = '✅ آدرس subscription ذخیره شد.';
				break;
			case 'admin_set_n_l':
				$t = (int) trim( SimpleVPBot_Bot_Runtime::normalize_digits( $text ) );
				if ( $t > 0 ) {
					$ok  = SimpleVPBot_Admin_Actions::apply_settings_merge( 'notifications', array( 'notify_low_traffic_percent' => $t ) );
					$msg = '✅ آستانه ٪' . $t;
				}
				break;
			case 'admin_set_n_e':
				$ok  = SimpleVPBot_Admin_Actions::apply_settings_merge( 'notifications', array( 'notify_expiry_days' => $text ) );
				$msg = '✅ روزها ذخیره شد.';
				break;
			case 'admin_set_n_d':
				$t = (int) trim( SimpleVPBot_Bot_Runtime::normalize_digits( $text ) );
				if ( $t >= 0 ) {
					$ok  = SimpleVPBot_Admin_Actions::apply_settings_merge( 'plans_catalog', array( 'default_concurrent_users' => $t ) );
					$msg = '✅ default concurrent = ' . $t;
				}
				break;
			case 'admin_set_n_p':
				$ok  = SimpleVPBot_Admin_Actions::apply_settings_merge( 'plans_catalog', array( 'price_per_extra_user' => str_replace( ',', '.', $text ) ) );
				$msg = '✅ قیمت ذخیره شد.';
				break;
			case 'admin_set_cry_ak':
				$all = SimpleVPBot_Settings::all();
				$all['crypto_nowpayments_api_key'] = sanitize_text_field( $text );
				SimpleVPBot_Settings::update( $all );
				SimpleVPBot_Texts::clear_cache();
				$ok  = true;
				$msg = '✅ API key ذخیره شد.';
				break;
			case 'admin_set_cry_in':
				$all = SimpleVPBot_Settings::all();
				$all['crypto_nowpayments_ipn_secret'] = sanitize_text_field( $text );
				SimpleVPBot_Settings::update( $all );
				SimpleVPBot_Texts::clear_cache();
				$ok  = true;
				$msg = '✅ IPN secret ذخیره شد.';
				break;
			case 'admin_set_cry_cu':
				$all = SimpleVPBot_Settings::all();
				$all['crypto_nowpayments_pay_currency'] = sanitize_key( $text );
				SimpleVPBot_Settings::update( $all );
				SimpleVPBot_Texts::clear_cache();
				$ok  = true;
				$msg = '✅ pay_currency ذخیره شد.';
				break;
		}
		if ( $ok && '' !== $msg ) {
			SimpleVPBot_State::clear( (int) $user->id );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $msg );
			return true;
		}
		if ( 0 === strpos( $st, 'admin_set_' ) && '' !== $text ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ مقدار نامعتبر یا ذخیره ناموفق. /cancel' );
			return true;
		}
		return false;
	}

	/**
	 * /cancel for settings + edit + inbound.
	 */
	public static function is_cancelable_settings_state( $st ) {
		$st = (string) $st;
		if ( 0 === strpos( $st, 'admin_set_' ) || 0 === strpos( $st, 'admin_w_' ) ) {
			return true;
		}
		return in_array( $st, array( 'admin_txt_edit', 'admin_inb_uid' ), true ) || 0 === strpos( $st, 'admin_line_' );
	}

	/**
	 * @param array<string, mixed> $ctx Context.
	 * @param string               $code pc|pl|cd.
	 */
	public static function start_catalog_wizard( array $ctx, $code ) {
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$user     = isset( $ctx['user'] ) ? $ctx['user'] : null;
		if ( ! $user || ! $user->id ) {
			return;
		}
		$code     = (string) $code;
		$uid      = (int) $user->id;
		$prompt   = '';
		$st       = '';
		if ( 'pc' === $code ) {
			$st     = 'admin_w_pc';
			$prompt = "➕ دسته پلن — دو خط بفرستید:\n۱) slug (a-z0-9_)\n۲) برچسب\n/cancel";
		} elseif ( 'pl' === $code ) {
			$st     = 'admin_w_pl';
			$prompt = "➕ پلن Xray (قیمت ثابت) — ۷ خط پشت‌سرهم:\n"
				. "۱ نام · ۲ slug دسته · ۳ مدت (روز) · ۴ ترافیک GB · ۵ قیمت · ۶ inbound_id · ۷ تعداد کلاینت\n"
				. "مثال (هر مقدار در یک خط):\nپلن آزمایشی\nnormal\n30\n20\n100000\n1\n1\n/cancel";
		} elseif ( 'cd' === $code ) {
			$st     = 'admin_w_cd';
			$prompt = "➕ کارت — یک خط با | جدا کنید:\n"
				. 'شماره|صاحب|بانک|روش(c2c|crypto|crypto_auto)|سقف_روزانه|اولویت[|یادداشت اختیاری]' . "\n/cancel";
		} elseif ( 'l2' === $code ) {
			$st     = 'admin_w_l2';
			$prompt = "➕ سرور L2TP — یک خط با | (احراز رمز SSH):\n"
				. "label|ssh_host|port|ssh_user|l2tp_host|ssh_password|psk\n/cancel";
		} else {
			return;
		}
		SimpleVPBot_State::set( $uid, $st, array() );
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $prompt );
	}

	/**
	 * @param array<string, mixed> $ctx Context.
	 * @param string               $st  State.
	 * @param string               $text Message.
	 * @return bool
	 */
	private static function route_catalog_wizard( array $ctx, $st, $text ) {
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$user     = $ctx['user'];
		$st       = (string) $st;
		$text     = (string) $text;

		if ( 'admin_w_pc' === $st ) {
			$lines = self::lines_nonempty( $text );
			if ( count( $lines ) < 2 ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ دو خط لازم است: slug و label.' );
				return true;
			}
			$res = SimpleVPBot_Service_Admin_Catalog::apply_plan_category_action(
				'add',
				0,
				array(
					'pc_slug'   => (string) $lines[0],
					'pc_label'  => (string) $lines[1],
					'pc_sort'   => 0,
					'pc_active' => 1,
				)
			);
			SimpleVPBot_State::clear( (int) $user->id );
			if ( ! empty( $res['ok'] ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '✅ دسته اضافه شد.' );
			} else {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ خطا: ' . (string) ( $res['code'] ?? '—' ) );
			}
			return true;
		}

		if ( 'admin_w_pl' === $st ) {
			$lines = self::lines_nonempty( $text );
			if ( count( $lines ) < 7 ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ ۷ خط لازم است (نام… تا تعداد کلاینت).' );
				return true;
			}
			$post = array(
				'name'                 => (string) $lines[0],
				'category'             => (string) $lines[1],
				'duration_days'        => (int) SimpleVPBot_Bot_Runtime::normalize_digits( (string) $lines[2] ),
				'traffic_gb'           => (int) SimpleVPBot_Bot_Runtime::normalize_digits( (string) $lines[3] ),
				'price'                => (float) str_replace( ',', '.', (string) $lines[4] ),
				'inbound_id'           => (int) SimpleVPBot_Bot_Runtime::normalize_digits( (string) $lines[5] ),
				'clients_count'        => max( 1, (int) SimpleVPBot_Bot_Runtime::normalize_digits( (string) $lines[6] ) ),
				'plan_pricing_type'    => 'fixed',
				'pricing_type'         => 'fixed',
				'service_type'         => 'xray',
				'sort_order'           => 0,
				'price_per_gb'         => 0,
				'traffic_gb_min'       => 0,
				'traffic_gb_max'       => 0,
				'l2tp_server_id'       => 0,
				'plan_active'          => 1,
			);
			$res = SimpleVPBot_Service_Admin_Catalog::apply_plan_action( 'add', 0, $post );
			SimpleVPBot_State::clear( (int) $user->id );
			if ( $res && ! empty( $res['ok'] ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '✅ پلن اضافه شد.' );
			} else {
				$c = ( $res && is_array( $res ) && isset( $res['code'] ) ) ? (string) $res['code'] : 'invalid';
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ داده‌ نامعتبر یا حذف/به‌روز: ' . $c );
			}
			return true;
		}

		if ( 'admin_w_cd' === $st ) {
			$segs = array_map( 'trim', explode( '|', $text ) );
			if ( count( $segs ) < 6 ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ حداقل ۶ بخش با | لازم است.' );
				return true;
			}
			$method = SimpleVPBot_Service_Admin_Catalog::sanitize_card_method_key( (string) $segs[3] );
			SimpleVPBot_Model_Card::insert(
				array(
					'card_number' => sanitize_text_field( (string) $segs[0] ),
					'holder_name' => sanitize_text_field( (string) $segs[1] ),
					'bank_name'   => sanitize_text_field( (string) $segs[2] ),
					'method_key'  => $method,
					'daily_limit' => (float) str_replace( ',', '.', (string) $segs[4] ),
					'priority'    => (int) $segs[5],
					'note'        => isset( $segs[6] ) ? sanitize_textarea_field( (string) $segs[6] ) : '',
					'active'      => 1,
				)
			);
			SimpleVPBot_State::clear( (int) $user->id );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '✅ کارت اضافه شد.' );
			return true;
		}

		if ( 'admin_w_l2' === $st ) {
			$segs = array_map( 'trim', explode( '|', $text ) );
			if ( count( $segs ) < 7 ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ ۷ بخش با | لازم است.' );
				return true;
			}
			$post = array(
				'label'          => (string) $segs[0],
				'ssh_host'       => (string) $segs[1],
				'ssh_port'       => max( 1, (int) SimpleVPBot_Bot_Runtime::normalize_digits( (string) $segs[2] ) ),
				'ssh_user'       => (string) $segs[3],
				'ssh_auth'       => 'password',
				'l2tp_host'      => (string) $segs[4],
				'ssh_password'   => (string) $segs[5],
				'l2tp_psk'       => (string) $segs[6],
				'chap_path'      => '/etc/ppp/chap-secrets',
				'reload_cmd'     => 'sudo /bin/systemctl reload xl2tpd',
				'active'         => 1,
			);
			$row = SimpleVPBot_Service_Admin_Catalog::sanitize_l2tp_post( null, $post );
			SimpleVPBot_Model_L2TP_Server::insert( $row );
			SimpleVPBot_State::clear( (int) $user->id );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '✅ سرور L2TP اضافه شد.' );
			return true;
		}
		return false;
	}

	/**
	 * @param string $text Text.
	 * @return array<int, string>
	 */
	private static function lines_nonempty( $text ) {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $l ) {
			$t = trim( (string) $l );
			if ( '' !== $t ) {
				$out[] = $t;
			}
		}
		return $out;
	}
}
