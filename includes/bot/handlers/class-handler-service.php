<?php
/**
 * Service management + subscription panel.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Handler_Service
 */
class SimpleVPBot_Handler_Service {

	/**
	 * Service owner or platform admin (same bot) may open the full service menu and actions.
	 *
	 * @param string      $platform telegram|bale.
	 * @param int         $from_id  Platform user id of the sender.
	 * @param object|null $user     svp_users row for the sender.
	 * @param object|null $svc      Service row.
	 * @return bool
	 */
	private static function service_caller_can_manage( $platform, $from_id, $user, $svc ) {
		if ( ! $svc || ! $user ) {
			return false;
		}
		if ( (int) $svc->user_id === (int) $user->id ) {
			return true;
		}
		if ( $from_id && SimpleVPBot_Router::is_platform_admin( $platform, (int) $from_id ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @param object $svc Service row.
	 * @return int
	 */
	private static function svc_panel_id_xui( $svc ) {
		return max( 1, (int) ( is_object( $svc ) ? ( $svc->panel_id ?? 1 ) : 1 ) );
	}

	/**
	 * Platform admin managing another user's service (admin user card / impersonation).
	 *
	 * @param string      $platform telegram|bale.
	 * @param int         $from_id  Platform user id.
	 * @param object|null $user     Callback sender svp_users row.
	 * @param object|null $svc      Service row.
	 * @return bool
	 */
	public static function is_platform_admin_managing_other_users_service( $platform, $from_id, $user, $svc ) {
		if ( ! $svc || ! $user || ! $from_id ) {
			return false;
		}
		return SimpleVPBot_Router::is_platform_admin( $platform, (int) $from_id )
			&& (int) $svc->user_id !== (int) $user->id;
	}

	/**
	 * Callback entry.
	 *
	 * @param array<string, mixed> $ctx Context. Optional from_id (platform user id) for admin-as-user.
	 */
	public static function handle_callback( array $ctx ) {
		$platform = (string) $ctx['platform'];
		$user     = $ctx['user'];
		$action   = (string) $ctx['action'];
		$svc_id   = (int) $ctx['svc_id'];
		$chat_id  = (int) $ctx['chat_id'];
		$msg_id   = (int) $ctx['msg_id'];
		$from_id  = isset( $ctx['from_id'] ) ? (int) $ctx['from_id'] : 0;

		$svc = SimpleVPBot_Model_Service::find( $svc_id );
		if ( ! self::service_caller_can_manage( $platform, $from_id, $user, $svc ) ) {
			return;
		}
		$owner_uid = (int) $svc->user_id;

		if ( 'm' === $action ) {
			$text = self::service_summary_text( $svc );
			$adm_del = self::is_platform_admin_managing_other_users_service( $platform, $from_id, $user, $svc );
			$mk      = SimpleVPBot_Keyboards::inline_service_menu( $svc_id, $platform, $owner_uid, SimpleVPBot_Model_Service::is_l2tp( $svc ), $adm_del );
			if ( $msg_id <= 0 ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $text, array( 'reply_markup' => $mk ) );
			} else {
				SimpleVPBot_Bot_Runtime::edit_message_text(
					$platform,
					$chat_id,
					$msg_id,
					$text,
					array( 'reply_markup' => $mk )
				);
			}
			return;
		}
		if ( SimpleVPBot_Model_Service::is_l2tp( $svc ) && ( 'p' === $action || 'l' === $action || 'q' === $action ) ) {
			self::show_l2tp_credentials( $platform, $chat_id, $svc );
			return;
		}
		if ( SimpleVPBot_Model_Service::is_l2tp( $svc ) && 'k' === $action ) {
			$new = SimpleVPBot_L2TP_Provisioner::rotate_password( $svc );
			if ( $new ) {
				$svc_fresh = SimpleVPBot_Model_Service::find( (int) $svc->id ) ?: $svc;
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '🔑 رمز جدید برای سرویس L2TP ساخته شد.' );
				self::show_l2tp_credentials( $platform, $chat_id, $svc_fresh );
			} else {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ تغییر رمز ناموفق بود. با پشتیبانی تماس بگیرید.' );
			}
			return;
		}
		if ( SimpleVPBot_Model_Service::is_l2tp( $svc ) && in_array( $action, array( 'u', 'ip', 'f' ), true ) ) {
			if ( 'f' === $action ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get( 'faq.l2tp', "❓ اتصال L2TP\n➖➖➖➖➖➖➖➖\n• در ویندوز: Settings → VPN → Add → نوع L2TP/IPsec with pre-shared key.\n• در iOS/اندروید: Settings → VPN → Add VPN → L2TP.\n• اگر وصل نشد اینترنت/فایروال پورت UDP 500/4500/1701 را چک کنید." ) );
				return;
			}
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, 'ℹ️ این گزینه برای سرویس L2TP در دسترس نیست.' );
			return;
		}
		if ( 'us' === $action ) {
			$text   = self::build_usage_panel_text( $svc );
			$markup = SimpleVPBot_Keyboards::inline_subscription_back_only( $svc_id );
			if ( $msg_id <= 0 ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $text, array( 'reply_markup' => $markup ) );
			} else {
				SimpleVPBot_Bot_Runtime::edit_message_text(
					$platform,
					$chat_id,
					$msg_id,
					$text,
					array( 'reply_markup' => $markup )
				);
			}
			return;
		}
		if ( 'p' === $action ) {
			$portal = SimpleVPBot_Portal_Link::build_service_url( $owner_uid, (int) $svc_id );
			$data   = self::get_portal_service_data( $svc, $owner_uid );
			$text   = self::build_usage_panel_text( $svc );
			if ( 'bale' === $platform ) {
				$markup = SimpleVPBot_Keyboards::inline_bale_portal_back( $svc_id, $portal );
			} else {
				$markup = SimpleVPBot_Keyboards::inline_telegram_config_extras( $svc_id, $data, $portal );
			}
			if ( $msg_id <= 0 ) {
				SimpleVPBot_Bot_Runtime::send_message(
					$platform,
					$chat_id,
					$text,
					array( 'reply_markup' => $markup )
				);
			} else {
				SimpleVPBot_Bot_Runtime::edit_message_text(
					$platform,
					$chat_id,
					$msg_id,
					$text,
					array( 'reply_markup' => $markup )
				);
			}
			if ( 'telegram' === $platform ) {
				self::telegram_send_config_unified( $chat_id, $data, $portal, $svc_id );
			}
			return;
		}
		if ( 'l' === $action || 'q' === $action ) {
			$portal = SimpleVPBot_Portal_Link::build_service_url( $owner_uid, (int) $svc_id );
			$data   = self::get_portal_service_data( $svc, $owner_uid );
			if ( 'bale' === $platform ) {
				$text = "🌐\n" . "کانفیگ و QR فقط داخل پنل زیر. در چت ارسال نمی‌شود.";
				$bextra = array( 'reply_markup' => SimpleVPBot_Keyboards::admin_only_back_reply() );
				if ( '' !== $portal ) {
					$text .= "\n" . $portal;
				}
				SimpleVPBot_Bot_Runtime::send_message(
					$platform,
					$chat_id,
					$text,
					$bextra
				);
				return;
			}
			$import  = (string) ( $data['import_sub_url'] ?? $data['subscription_url'] ?? $data['primary_link'] ?? '' );
			$primary  = (string) ( $data['primary_link'] ?? $import );
			$port_btn = (string) ( $data['portal_url'] ?? $portal );
			if ( '' === $primary && empty( $data['config_uris'] ) && '' === $port_btn ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⚠️ لینک اتصال یافت نشد.' );
				return;
			}
			self::telegram_send_config_unified( $chat_id, $data, $portal, $svc_id );
			return;
		}
		if ( 'k' === $action ) {
			SimpleVPBot_Xui_Client::run_with_panel(
				self::svc_panel_id_xui( $svc ),
				function () use ( $platform, $chat_id, $svc, $svc_id ) {
					if ( ! SimpleVPBot_Xui_Client::login_with_retries( 5, 300000 ) ) {
						SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ ورود به پنل ناموفق است.' );
						return;
					}
					$new = SimpleVPBot_Xui_Client::get_new_uuid();
					if ( ! $new || ! SimpleVPBot_Xui_Client::is_likely_client_uuid( (string) $new ) ) {
						SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ دریافت UUID جدید ناموفق بود.' );
						return;
					}
					$inbound = SimpleVPBot_Xui_Client::inbound_get( (int) $svc->inbound_id );
					if ( ! $inbound ) {
						SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ اینباند پنل یافت نشد.' );
						return;
					}
					$old_key = SimpleVPBot_Xui_Client::resolve_client_key_for_update( (string) $svc->xui_client_id, $inbound, (string) $svc->email );
					if ( ! $old_key || ! SimpleVPBot_Xui_Client::is_likely_client_uuid( (string) $old_key ) ) {
						SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ شناسه کلاینت در پنل یافت نشد (ایمیل یا UUID نامعتبر).' );
						return;
					}
					$settings = isset( $inbound['settings'] ) ? $inbound['settings'] : '';
					$dec      = is_string( $settings ) ? json_decode( $settings, true ) : ( is_array( $settings ) ? $settings : array() );
					if ( empty( $dec['clients'] ) || ! is_array( $dec['clients'] ) ) {
						SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ فهرست کلاینت خالی است.' );
						return;
					}
					$found          = false;
					$updated_client = null;
					foreach ( $dec['clients'] as &$cl ) {
						if ( isset( $cl['email'] ) && (string) $cl['email'] === (string) $svc->email ) {
							$cl['id']       = $new;
							$updated_client = $cl;
							$found          = true;
							break;
						}
					}
					unset( $cl );
					if ( ! $found || ! is_array( $updated_client ) ) {
						SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ کلاینت این سرویس روی پنل پیدا نشد.' );
						return;
					}
					$path_ids = array( (string) $old_key );
					$em       = (string) $svc->email;
					if ( '' !== $em && $em !== (string) $old_key ) {
						$path_ids[] = $em;
					}
					$res = SimpleVPBot_Xui_Client::update_inbound_client_sequential( (int) $svc->inbound_id, $dec, $updated_client, $path_ids );
					if ( ! SimpleVPBot_Xui_Client::response_is_success( $res ) ) {
						SimpleVPBot_Logger::error(
							'regen key updateClient failed',
							array(
								'res'   => $res,
								'email' => (string) $svc->email,
								'msg'   => is_array( $res ) ? (string) ( $res['msg'] ?? '' ) : '',
							)
						);
						SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ بروزرسانی روی پنل انجام نشد.' );
						return;
					}
					SimpleVPBot_Model_Service::update( $svc_id, array( 'xui_client_id' => $new, 'xui_client_uuid' => $new ) );
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '🔑 کلید (UUID) جدید ساخته شد و روی سرویس ثبت گردید.' );
				}
			);
			return;
		}
		if ( 'u' === $action ) {
			SimpleVPBot_Xui_Client::run_with_panel(
				self::svc_panel_id_xui( $svc ),
				function () use ( $platform, $chat_id, $svc ) {
					SimpleVPBot_Xui_Client::login_with_retries( 5, 300000 );
					SimpleVPBot_Xui_Client::inbound_get( (int) $svc->inbound_id );
				}
			);
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '🔄 اطلاعات سرور به‌روز شد.' );
			return;
		}
		if ( 'ar' === $action ) {
			$on = ! (int) $svc->autorenew;
			SimpleVPBot_Model_Service::update( $svc_id, array( 'autorenew' => $on ? 1 : 0 ) );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $on ? '🔁 تمدید خودکار: ✅ روشن' : '🔁 تمدید خودکار: ❌ خاموش' );
			return;
		}
		if ( 'al' === $action ) {
			self::alerts_render_main_panel( $platform, $chat_id, $msg_id, $svc );
			return;
		}
		if ( preg_match( '/^a[0-8]$/', $action ) ) {
			self::alerts_handle_sub_callback( $platform, $chat_id, $msg_id, $user, $svc_id, $action, $from_id );
			return;
		}
		if ( 'n' === $action ) {
			SimpleVPBot_State::set( (int) $user->id, 'svc_note_' . $svc_id, array() );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '📝 یادداشت نمایش (نام روی پنل X-UI) را ارسال کنید:' );
			return;
		}
		if ( 'rn' === $action ) {
			SimpleVPBot_State::set( (int) $user->id, 'svc_rename_' . $svc_id, array() );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '✏️ نام نمایشی این سرویس (در ربات و لیست سرویس‌ها) را ارسال کنید:' );
			return;
		}
		if ( 'ip' === $action ) {
			SimpleVPBot_Xui_Client::run_with_panel(
				self::svc_panel_id_xui( $svc ),
				function () use ( $platform, $chat_id, $svc ) {
					SimpleVPBot_Xui_Client::login_with_retries( 5, 300000 );
					$j   = SimpleVPBot_Xui_Client::client_ips( (string) $svc->email );
					$obj = is_array( $j ) && isset( $j['obj'] ) ? $j['obj'] : null;
					$ips = array();
					if ( is_string( $obj ) && '' !== $obj && 'No IP Record' !== $obj ) {
						$decoded = json_decode( $obj, true );
						$ips     = is_array( $decoded ) ? $decoded : preg_split( '/[\s,]+/', $obj );
					} elseif ( is_array( $obj ) ) {
						$ips = $obj;
					}
					$ips = array_slice( array_filter( array_map( 'trim', (array) $ips ) ), 0, 20 );
					$txt = '🌐 اتصالات فعال' . SimpleVPBot_Service_Alerts::text_sep();
					$txt .= empty( $ips ) ? '📭 هنوز موردی نیست' : '• ' . implode( "\n• ", $ips );
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $txt );
				}
			);
			return;
		}
		if ( 'f' === $action ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get( 'faq.connection', 'FAQ' ) );
			return;
		}
		if ( 'su' === $action ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '🆘 با ادمین تماس بگیرید یا از بخش پشتیبانی تیکت بفرستید.' );
			return;
		}
		if ( 'r' === $action ) {
			if ( SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ تمدید با پرداخت از این مسیر فقط برای سرویس‌های Xray است؛ برای L2TP با پشتیبانی تماس بگیرید.' );
				return;
			}
			$plan = SimpleVPBot_Model_Service::effective_plan_for_pricing( $svc );
			if ( ! $plan ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ پلن سرویس برای صدور فاکتور تنظیم نشده. در تنظیمات عمومی، «پلن پیش‌فرض سرویس‌های بدون پلن» را روی یک پلن Xray فعال بگذارید.' );
				return;
			}
			if ( self::is_platform_admin_managing_other_users_service( $platform, $from_id, $user, $svc ) ) {
				$rows = SimpleVPBot_Handler_Admin_Hub::admin_service_payment_mode_inline_rows( 'renew', $svc_id, null );
				if ( empty( $rows ) ) {
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ خطای داخلی دکمه‌ها.' );
					return;
				}
				$amount = SimpleVPBot_Service_Renew::checkout_price_renew( $svc, $plan );
				$am_fa  = SimpleVPBot_Bot_Persian_Text::format_toman_fa( (float) $amount );
				$sid_fa = SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) (int) $svc_id );
				$txt    = "♻️ تمدید سرویس #{$sid_fa}\n💰 مبلغ: {$am_fa} تومان\nروش اعمال را انتخاب کنید:";
				SimpleVPBot_Bot_Runtime::send_message(
					$platform,
					$chat_id,
					$txt,
					array( 'reply_markup' => array( 'inline_keyboard' => $rows ) )
				);
				return;
			}
			$amount = SimpleVPBot_Service_Renew::checkout_price_renew( $svc, $plan );
			$meta    = array(
				'intent'     => 'renew_same',
				'service_id' => $svc_id,
				'plan_id'    => (int) $plan->id,
			);
			SimpleVPBot_Handler_Buy::send_purchase_checkout( $platform, $chat_id, $owner_uid, $amount, $meta, (int) $user->id );
			return;
		}
		if ( 'v' === $action ) {
			if ( SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ افزایش حجم از این مسیر فقط برای Xray است.' );
				return;
			}
			$pid = SimpleVPBot_Model_Service::effective_plan_id_for_pricing( $svc );
			if ( $pid < 1 ) {
				SimpleVPBot_Bot_Runtime::send_message(
					$platform,
					$chat_id,
					'⛔ پلن سرویس برای قیمت‌گذاری حجم مشخص نیست. از ادمین بخواهید در تنظیمات عمومی، «پلن پیش‌فرض سرویس‌های بدون پلن» را روی یک پلن Xray فعال بگذارد.'
				);
				return;
			}
			SimpleVPBot_State::set( (int) $user->id, 'svc_addvol_' . $svc_id, array( 'plan_id' => $pid ) );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '➕ چند گیگابایت به سقف حجم اضافه شود؟ فقط عدد (گیگ) بفرستید؛ مثلاً 10' );
			return;
		}
		if ( 'sl' === $action ) {
			if ( SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ این گزینه برای این نوع سرویس نیست.' );
				return;
			}
			$unit = (float) SimpleVPBot_Settings::get( 'price_per_extra_user', 0 );
			if ( $unit <= 0 ) {
				SimpleVPBot_Bot_Runtime::send_message(
					$platform,
					$chat_id,
					"👥 افزایش کاربر\n" . SimpleVPBot_Service_Alerts::text_sep() . "🧒 هنوز قیمتش توسط ادمین تنظیم نشده.\n✋ بعداً دوباره امتحان کن یا از پشتیبانی بپرس."
				);
				return;
			}
			$pid = SimpleVPBot_Model_Service::effective_plan_id_for_pricing( $svc );
			if ( $pid < 1 ) {
				SimpleVPBot_Bot_Runtime::send_message(
					$platform,
					$chat_id,
					'⛔ پلن سرویس برای این بخش ثبت نشده. از ادمین بخواهید در تنظیمات عمومی، «پلن پیش‌فرض سرویس‌های بدون پلن» را روی یک پلن Xray فعال بگذارد (برای هم‌خوانی با پنل).'
				);
				return;
			}
			SimpleVPBot_State::set( (int) $user->id, 'svc_addusers_' . $svc_id, array( 'plan_id' => $pid ) );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				"👥 افزایش کاربر هم‌زمان\n" . SimpleVPBot_Service_Alerts::text_sep()
				. "🧒 یعنی چی؟ چند نفر بیشتر بتوانند هم‌زمان از همین سرویس استفاده کنند.\n" . SimpleVPBot_Service_Alerts::text_sep()
				. "✋ فقط یک عدد بفرست: می‌خواهی چند نفر اضافه شود؟ (مثلاً ۱ تا ۵۰)."
			);
			return;
		}
		if ( 'b' === $action ) {
			$list   = SimpleVPBot_Model_Service::by_user( $owner_uid );
			$mk     = SimpleVPBot_Keyboards::inline_service_list( $list );
			$caption = '🧰 سرویس‌های شما';
			if ( $msg_id <= 0 ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $caption, array( 'reply_markup' => $mk ) );
			} else {
				SimpleVPBot_Bot_Runtime::edit_message_text(
					$platform,
					$chat_id,
					$msg_id,
					$caption,
					array( 'reply_markup' => $mk )
				);
			}
			return;
		}
		if ( 'tx' === $action ) {
			$res = SimpleVPBot_Service_Transfer::create_code( $svc_id, $owner_uid );
			if ( empty( $res['ok'] ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ امکان تولید کد انتقال نیست.' );
				return;
			}
			$code = (string) ( $res['code'] ?? '' );
			$txt  = "🎁 انتقال سرویس" . SimpleVPBot_Service_Alerts::text_sep() . '🔑 کد ۶ رقمی: `' . $code . "`\n⏳ اعتبار: ۱۰ دقیقه" . SimpleVPBot_Service_Alerts::text_sep() . "از طرف مقابل بخواهید در ربات وارد منوی «دریافت انتقال سرویس» شده و این کد را ارسال کند.";
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $txt );
			return;
		}
	}

	/**
	 * Keep legacy `alerts_enabled` in sync with per-channel flags (cron still reads triplet first).
	 *
	 * @param int $svc_id Service id.
	 */
	private static function alerts_sync_master_flag( $svc_id ) {
		$s = SimpleVPBot_Model_Service::find( (int) $svc_id );
		if ( ! $s ) {
			return;
		}
		$on = SimpleVPBot_Service_Alerts::any_enabled( $s ) ? 1 : 0;
		SimpleVPBot_Model_Service::update( (int) $svc_id, array( 'alerts_enabled' => $on ) );
	}

	/**
	 * Edit (or send) main alerts panel.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param int    $msg_id   Message id (0 = send new).
	 * @param object $svc      Service row.
	 */
	private static function alerts_render_main_panel( $platform, $chat_id, $msg_id, $svc ) {
		$is_l2 = SimpleVPBot_Model_Service::is_l2tp( $svc );
		$text  = SimpleVPBot_Service_Alerts::main_panel_intro( $is_l2 );
		$text .= SimpleVPBot_Service_Alerts::text_sep() . "📋 الان چطور است؟\n" . SimpleVPBot_Service_Alerts::thresholds_summary_line( $svc );
		$extra = array(
			'reply_markup' => array(
				'inline_keyboard' => SimpleVPBot_Service_Alerts::main_panel_rows( $svc ),
			),
		);
		if ( 'telegram' === $platform ) {
			$extra['parse_mode'] = 'HTML';
		}
		if ( $msg_id > 0 ) {
			SimpleVPBot_Bot_Runtime::edit_message_text( $platform, $chat_id, $msg_id, $text, $extra );
		} else {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $text, $extra );
		}
	}

	/**
	 * Edit thresholds submenu.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param int    $msg_id   Message id.
	 * @param object $svc      Service row.
	 */
	private static function alerts_render_thresholds_panel( $platform, $chat_id, $msg_id, $svc ) {
		$is_l2 = SimpleVPBot_Model_Service::is_l2tp( $svc );
		$text  = SimpleVPBot_Service_Alerts::thresholds_intro( $is_l2 );
		$text .= SimpleVPBot_Service_Alerts::text_sep() . "📋 الان:\n" . SimpleVPBot_Service_Alerts::thresholds_summary_line( $svc );
		$extra = array(
			'reply_markup' => array(
				'inline_keyboard' => SimpleVPBot_Service_Alerts::thresholds_rows( (int) $svc->id, $is_l2 ),
			),
		);
		if ( 'telegram' === $platform ) {
			$extra['parse_mode'] = 'HTML';
		}
		SimpleVPBot_Bot_Runtime::edit_message_text( $platform, $chat_id, $msg_id, $text, $extra );
	}

	/**
	 * @param string               $platform Platform.
	 * @param int                  $chat_id  Chat id.
	 * @param int                  $msg_id   Message id.
	 * @param object               $user     User.
	 * @param int                  $svc_id   Service id.
	 * @param string               $action   a0..a8.
	 * @param int                  $from_id  Platform user id (admin check).
	 */
	private static function alerts_handle_sub_callback( $platform, $chat_id, $msg_id, $user, $svc_id, $action, $from_id = 0 ) {
		$svc = SimpleVPBot_Model_Service::find( $svc_id );
		if ( ! self::service_caller_can_manage( $platform, (int) $from_id, $user, $svc ) ) {
			return;
		}
		if ( 'a1' === $action ) {
			$v = SimpleVPBot_Service_Alerts::volume_enabled( $svc ) ? 0 : 1;
			SimpleVPBot_Model_Service::update( $svc_id, array( 'alerts_volume' => $v ) );
			self::alerts_sync_master_flag( $svc_id );
			$svc = SimpleVPBot_Model_Service::find( $svc_id );
			self::alerts_render_main_panel( $platform, $chat_id, $msg_id, $svc );
			return;
		}
		if ( 'a2' === $action ) {
			$v = SimpleVPBot_Service_Alerts::expiry_enabled( $svc ) ? 0 : 1;
			SimpleVPBot_Model_Service::update( $svc_id, array( 'alerts_expiry' => $v ) );
			self::alerts_sync_master_flag( $svc_id );
			$svc = SimpleVPBot_Model_Service::find( $svc_id );
			self::alerts_render_main_panel( $platform, $chat_id, $msg_id, $svc );
			return;
		}
		if ( 'a3' === $action ) {
			if ( SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
				return;
			}
			$v = SimpleVPBot_Service_Alerts::users_enabled( $svc ) ? 0 : 1;
			SimpleVPBot_Model_Service::update( $svc_id, array( 'alerts_users' => $v ) );
			self::alerts_sync_master_flag( $svc_id );
			$svc = SimpleVPBot_Model_Service::find( $svc_id );
			self::alerts_render_main_panel( $platform, $chat_id, $msg_id, $svc );
			return;
		}
		if ( 'a0' === $action ) {
			self::alerts_render_thresholds_panel( $platform, $chat_id, $msg_id, $svc );
			return;
		}
		if ( 'a8' === $action ) {
			self::alerts_render_main_panel( $platform, $chat_id, $msg_id, $svc );
			return;
		}
		if ( 'a5' === $action ) {
			SimpleVPBot_State::set( (int) $user->id, 'svc_al_pct_' . $svc_id, array() );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				"📉 آستانهٔ حجم\n"
				. SimpleVPBot_Service_Alerts::text_sep()
				. "🧒 یعنی چی؟ وقتی از حجمت این‌قدر کم مانده که به این عدد رسید، ربات یک پیام می‌فرستد.\n"
				. SimpleVPBot_Service_Alerts::text_sep()
				. '📋 الان: ' . SimpleVPBot_Service_Alerts::effective_low_traffic_pct( $svc ) . "٪\n"
				. SimpleVPBot_Service_Alerts::text_sep()
				. "✋ تو چی کار کنی؟ فقط یک عدد ۱ تا ۹۹ بفرست مثل ۲۰.\n"
				. "🔙 برای ول کردن از منوی پایین یک دکمه بزن یا از پیام قبلی «بازگشت به هشدارها» را بزن."
			);
			return;
		}
		if ( 'a6' === $action ) {
			SimpleVPBot_State::set( (int) $user->id, 'svc_al_exp_' . $svc_id, array() );
			$d = implode( ',', SimpleVPBot_Service_Alerts::effective_expiry_days( $svc ) );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				"📅 روزهای هشدار قبل از انقضا\n"
				. SimpleVPBot_Service_Alerts::text_sep()
				. "🧒 یعنی چی؟ وقتی تا تمام شدن وقت سرویس دقیقا این تعداد روز مانده باشد، ربات یک بار خبر می‌دهد. عدد ۰ یعنی همان روز آخر.\n"
				. SimpleVPBot_Service_Alerts::text_sep()
				. "📋 الان: {$d}\n"
				. SimpleVPBot_Service_Alerts::text_sep()
				. "✋ تو چی کار کنی؟ چند عدد با کامای انگلیسی بفرست مثل ۳,۱,۰ ."
			);
			return;
		}
		if ( 'a7' === $action ) {
			if ( SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
				return;
			}
			SimpleVPBot_State::set( (int) $user->id, 'svc_al_ip_' . $svc_id, array() );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				"👥 آستانهٔ محدودیت کاربر\n"
				. SimpleVPBot_Service_Alerts::text_sep()
				. "🧒 یعنی چی؟ وقتی چند نفر هم‌زمان از سرویس استفاده می‌کنند و نزدیک همان عددی شدی که برایت ثبت شده، ربات هشدار می‌دهد. این فقط وقتی کار دارد که برایت یک عدد بیش از صفر ثبت شده باشد.\n"
				. SimpleVPBot_Service_Alerts::text_sep()
				. '📋 الان: ' . SimpleVPBot_Service_Alerts::effective_ip_fill_pct( $svc ) . "٪\n"
				. SimpleVPBot_Service_Alerts::text_sep()
				. "✋ تو چی کار کنی؟ یک عدد ۵۰ تا ۱۰۰ بفرست مثل ۸۵."
			);
			return;
		}
	}

	/**
	 * Text replies for alert threshold states.
	 *
	 * @param array<string, mixed> $ctx platform, chat_id, user, text.
	 */
	public static function handle_alert_threshold_text( array $ctx ) {
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$user     = $ctx['user'];
		$raw      = trim( (string) $ctx['text'] );
		$from_id  = isset( $ctx['from_id'] ) ? (int) $ctx['from_id'] : 0;
		$state    = (string) $user->state;
		if ( ! preg_match( '/^svc_al_(pct|exp|ip)_(\d+)$/', $state, $m ) ) {
			return;
		}
		$kind = $m[1];
		$sid  = (int) $m[2];
		$svc  = SimpleVPBot_Model_Service::find( $sid );
		if ( ! $svc || ! self::service_caller_can_manage( $platform, $from_id, $user, $svc ) ) {
			SimpleVPBot_State::clear( (int) $user->id );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ سرویس نامعتبر است.' );
			return;
		}
		if ( 'ip' === $kind && SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
			SimpleVPBot_State::clear( (int) $user->id );
			return;
		}
		if ( 'pct' === $kind ) {
			$d = trim( SimpleVPBot_Bot_Runtime::normalize_digits( $raw ) );
			if ( ! preg_match( '/^\d+$/', $d ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ فقط یک عدد ۱ تا ۹۹ بفرستید.' );
				return;
			}
			$n = (int) $d;
			if ( $n < 1 || $n > 99 ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ عدد باید بین ۱ تا ۹۹ باشد.' );
				return;
			}
			SimpleVPBot_Model_Service::update( $sid, array( 'alert_low_pct' => $n ) );
			self::alerts_sync_master_flag( $sid );
		} elseif ( 'exp' === $kind ) {
			$norm = str_replace( array( '،', ' ' ), array( ',', '' ), $raw );
			$parts = explode( ',', $norm );
			$days  = array();
			foreach ( $parts as $p ) {
				$p = trim( SimpleVPBot_Bot_Runtime::normalize_digits( $p ) );
				if ( '' === $p || ! ctype_digit( $p ) ) {
					continue;
				}
				$di = (int) $p;
				if ( $di >= 0 && $di <= 3650 ) {
					$days[] = $di;
				}
			}
			$days = array_values( array_unique( $days ) );
			if ( empty( $days ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ حداقل یک روز معتبر بفرستید، مثل ۳,۱,۰' );
				return;
			}
			SimpleVPBot_Model_Service::update( $sid, array( 'alert_expiry_days' => implode( ',', $days ) ) );
			self::alerts_sync_master_flag( $sid );
		} else {
			$d = trim( SimpleVPBot_Bot_Runtime::normalize_digits( $raw ) );
			if ( ! preg_match( '/^\d+$/', $d ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ فقط یک عدد ۵۰ تا ۱۰۰ بفرستید.' );
				return;
			}
			$n = (int) $d;
			if ( $n < 50 || $n > 100 ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ عدد باید بین ۵۰ تا ۱۰۰ باشد.' );
				return;
			}
			SimpleVPBot_Model_Service::update( $sid, array( 'alert_ip_fill_pct' => $n ) );
			self::alerts_sync_master_flag( $sid );
		}
		SimpleVPBot_State::clear( (int) $user->id );
		$svc = SimpleVPBot_Model_Service::find( $sid );
		$sum = SimpleVPBot_Service_Alerts::thresholds_summary_line( $svc );
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			"✅ ذخیره شد.\n" . SimpleVPBot_Service_Alerts::text_sep() . "📋 الان:\n{$sum}",
			array(
				'reply_markup' => array(
					'inline_keyboard' => array(
						array( array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '🔔 باز کردن پنل هشدار' ), 'callback_data' => 'svc:al:' . $sid ) ),
					),
				),
			)
		);
	}

	/**
	 * Parse GB for svc_addvol_* state and open checkout.
	 *
	 * @param array<string, mixed> $ctx Context: platform, chat_id, user, text, service_id.
	 */
	public static function handle_addvol_text( array $ctx ) {
		$platform  = (string) $ctx['platform'];
		$chat_id   = (int) $ctx['chat_id'];
		$user      = $ctx['user'];
		$sid       = (int) $ctx['service_id'];
		$from_id   = isset( $ctx['from_id'] ) ? (int) $ctx['from_id'] : 0;
		$raw       = trim( SimpleVPBot_Bot_Runtime::normalize_digits( (string) $ctx['text'] ) );
		$state     = (string) $user->state;
		if ( 'svc_addvol_' . $sid !== $state ) {
			return;
		}
		$sd   = SimpleVPBot_State::data( $user );
		$pid  = (int) ( $sd['plan_id'] ?? 0 );
		$plan = $pid ? SimpleVPBot_Model_Plan::find( $pid ) : null;
		$svc  = SimpleVPBot_Model_Service::find( $sid );
		if ( ! $plan || ! (int) $plan->active || ! $svc || ! self::service_caller_can_manage( $platform, $from_id, $user, $svc ) || SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
			SimpleVPBot_State::clear( (int) $user->id );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ جلسه نامعتبر است. دوباره از منوی سرویس شروع کنید.' );
			return;
		}
		if ( ! preg_match( '/^\d+$/u', $raw ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ فقط یک عدد صحیح بفرستید (مثلا 10).' );
			return;
		}
		$gb = (int) $raw;
		if ( $gb < 1 ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ حداقل ۱ گیگ است.' );
			return;
		}
		if ( SimpleVPBot_Model_Plan::is_per_gb( $plan ) && ! SimpleVPBot_Model_Plan::is_volume_in_range( $plan, $gb ) ) {
			$min = (int) ( $plan->traffic_gb_min ?? 0 );
			$max = (int) ( $plan->traffic_gb_max ?? 0 );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, "⛔ برای این پلن حجم اضافه باید بین {$min} و {$max} گیگ باشد." );
			return;
		}
		if ( ! SimpleVPBot_Model_Plan::is_per_gb( $plan ) && $gb > 512 ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ حداکثر ۵۱۲ گیگ در هر درخواست.' );
			return;
		}
		$amount = SimpleVPBot_Service_Renew::checkout_price_add_volume( $plan, $gb );
		if ( $amount <= 0 ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ مبلغ نامعتبر است. با ادمین تماس بگیرید.' );
			return;
		}
		SimpleVPBot_State::clear( (int) $user->id );
		if ( self::is_platform_admin_managing_other_users_service( $platform, $from_id, $user, $svc ) ) {
			$rows = SimpleVPBot_Handler_Admin_Hub::admin_service_payment_mode_inline_rows( 'vol', $sid, $gb );
			if ( empty( $rows ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ خطای داخلی دکمه‌ها.' );
				return;
			}
			$am_fa = SimpleVPBot_Bot_Persian_Text::format_toman_fa( (float) $amount );
			$gb_fa = SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $gb );
			$txt   = "➕ افزایش حجم {$gb_fa} گیگ\n💰 مبلغ: {$am_fa} تومان\nروش اعمال را انتخاب کنید:";
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				$txt,
				array( 'reply_markup' => array( 'inline_keyboard' => $rows ) )
			);
			return;
		}
		$meta = array(
			'intent'     => 'add_volume',
			'service_id' => $sid,
			'plan_id'    => (int) $plan->id,
			'extra_gb'   => $gb,
		);
		SimpleVPBot_Handler_Buy::send_purchase_checkout( $platform, $chat_id, (int) $svc->user_id, $amount, $meta, (int) $user->id );
	}

	/**
	 * Parse extra user count for svc_addusers_* state and open checkout.
	 *
	 * @param array<string, mixed> $ctx platform, chat_id, user, text, service_id.
	 */
	public static function handle_addusers_text( array $ctx ) {
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$user     = $ctx['user'];
		$sid      = (int) $ctx['service_id'];
		$from_id  = isset( $ctx['from_id'] ) ? (int) $ctx['from_id'] : 0;
		$raw      = trim( SimpleVPBot_Bot_Runtime::normalize_digits( (string) $ctx['text'] ) );
		$state    = (string) $user->state;
		if ( 'svc_addusers_' . $sid !== $state ) {
			return;
		}
		$sd   = SimpleVPBot_State::data( $user );
		$pid  = (int) ( $sd['plan_id'] ?? 0 );
		$plan = $pid ? SimpleVPBot_Model_Plan::find( $pid ) : null;
		$svc  = SimpleVPBot_Model_Service::find( $sid );
		if ( ! $plan || ! (int) $plan->active || ! $svc || ! self::service_caller_can_manage( $platform, $from_id, $user, $svc ) || SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
			SimpleVPBot_State::clear( (int) $user->id );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ جلسه نامعتبر است. دوباره از منوی سرویس شروع کنید.' );
			return;
		}
		if ( ! preg_match( '/^\d+$/u', $raw ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ فقط یک عدد بفرستید مثل ۲.' );
			return;
		}
		$n = (int) $raw;
		if ( $n < 1 || $n > 50 ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ عدد باید بین ۱ تا ۵۰ باشد.' );
			return;
		}
		$amount = SimpleVPBot_Service_Renew::checkout_price_add_user_slots( $n );
		if ( $amount <= 0 ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ قیمت هر کاربر اضافه در تنظیمات صفر است. با ادمین تماس بگیرید.' );
			return;
		}
		SimpleVPBot_State::clear( (int) $user->id );
		if ( self::is_platform_admin_managing_other_users_service( $platform, $from_id, $user, $svc ) ) {
			$rows = SimpleVPBot_Handler_Admin_Hub::admin_service_payment_mode_inline_rows( 'slots', $sid, $n );
			if ( empty( $rows ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ خطای داخلی دکمه‌ها.' );
				return;
			}
			$am_fa = SimpleVPBot_Bot_Persian_Text::format_toman_fa( (float) $amount );
			$n_fa  = SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $n );
			$txt   = "👥 افزایش {$n_fa} کاربر هم‌زمان\n💰 مبلغ: {$am_fa} تومان\nروش اعمال را انتخاب کنید:";
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				$txt,
				array( 'reply_markup' => array( 'inline_keyboard' => $rows ) )
			);
			return;
		}
		$meta = array(
			'intent'       => 'add_user_slots',
			'service_id'   => $sid,
			'plan_id'      => (int) $plan->id,
			'extra_users'  => $n,
		);
		SimpleVPBot_Handler_Buy::send_purchase_checkout( $platform, $chat_id, (int) $svc->user_id, $amount, $meta, (int) $user->id );
	}

	/**
	 * Handle note text state.
	 *
	 * @param array<string, mixed> $ctx Context.
	 */
	public static function handle_note_text( array $ctx ) {
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$user     = $ctx['user'];
		$text     = trim( (string) $ctx['text'] );
		$from_id  = isset( $ctx['from_id'] ) ? (int) $ctx['from_id'] : 0;
		$state    = (string) $user->state;
		if ( ! preg_match( '/^svc_(?:note|rename)_(\d+)$/', $state, $m ) ) {
			return;
		}
		$sid = (int) $m[1];
		$svc = SimpleVPBot_Model_Service::find( $sid );
		if ( ! $svc || ! self::service_caller_can_manage( $platform, $from_id, $user, $svc ) ) {
			SimpleVPBot_State::clear( (int) $user->id );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ سرویس نامعتبر است.' );
			return;
		}
		if ( '' === $text ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ متن خالی است.' );
			return;
		}
		if ( SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
			SimpleVPBot_Model_Service::update( $sid, array( 'remark' => $text ) );
			SimpleVPBot_State::clear( (int) $user->id );
			$is_rename = ( 0 === strpos( $state, 'svc_rename_' ) );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $is_rename ? '✅ نام نمایشی به‌روز شد.' : '✅ یادداشت به‌روز شد.' );
			return;
		}
		$is_rename = ( 0 === strpos( $state, 'svc_rename_' ) );
		if ( $is_rename ) {
			SimpleVPBot_Model_Service::update( $sid, array( 'remark' => $text ) );
			SimpleVPBot_State::clear( (int) $user->id );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '✅ نام نمایشی در ربات به‌روز شد.' );
			return;
		}
		SimpleVPBot_Xui_Client::run_with_panel(
			self::svc_panel_id_xui( $svc ),
			function () use ( $platform, $chat_id, $user, $svc, $sid, $text ) {
				if ( ! SimpleVPBot_Xui_Client::login_with_retries( 5, 300000 ) ) {
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ ورود به پنل ناموفق است. بعداً دوباره تلاش کنید.' );
					return;
				}
				$inbound = SimpleVPBot_Xui_Client::inbound_get( (int) $svc->inbound_id );
				if ( ! $inbound ) {
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ اینباند پنل یافت نشد.' );
					return;
				}
				$settings = isset( $inbound['settings'] ) ? $inbound['settings'] : '';
				$dec      = is_string( $settings ) ? json_decode( $settings, true ) : ( is_array( $settings ) ? $settings : array() );
				if ( empty( $dec['clients'] ) || ! is_array( $dec['clients'] ) ) {
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ فهرست کلاینت روی پنل خالی است.' );
					return;
				}
				$updated_client = null;
				foreach ( $dec['clients'] as &$cl ) {
					if ( isset( $cl['email'] ) && (string) $cl['email'] === (string) $svc->email ) {
						$cl['remark']   = $text;
						$updated_client = $cl;
						break;
					}
				}
				unset( $cl );
				if ( ! is_array( $updated_client ) ) {
					SimpleVPBot_State::clear( (int) $user->id );
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ کلاینت روی پنل پیدا نشد.' );
					return;
				}
				$old_key = SimpleVPBot_Xui_Client::resolve_client_key_for_update( (string) $svc->xui_client_id, $inbound, (string) $svc->email );
				if ( ! $old_key ) {
					SimpleVPBot_State::clear( (int) $user->id );
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ شناسه کلاینت روی پنل پیدا نشد.' );
					return;
				}
				$path_ids = array( (string) $old_key );
				$em       = (string) $svc->email;
				if ( '' !== $em && $em !== (string) $old_key ) {
					$path_ids[] = $em;
				}
				$res = SimpleVPBot_Xui_Client::update_inbound_client_sequential( (int) $svc->inbound_id, $dec, $updated_client, $path_ids );
				if ( ! SimpleVPBot_Xui_Client::response_is_success( $res ) ) {
					SimpleVPBot_Logger::error(
						'note/rename updateClient failed',
						array(
							'res'       => $res,
							'email'     => (string) $svc->email,
							'svc_id'    => $sid,
							'panel_msg' => is_array( $res ) ? (string) ( $res['msg'] ?? '' ) : '',
						)
					);
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ بروزرسانی روی پنل انجام نشد.' );
					return;
				}
				SimpleVPBot_Model_Service::update( $sid, array( 'remark' => $text ) );
				SimpleVPBot_State::clear( (int) $user->id );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '✅ یادداشت روی پنل و نام نمایشی در ربات به‌روز شد.' );
			}
		);
	}

	/**
	 * Summary text for service row.
	 *
	 * @param object $svc Service.
	 * @return string
	 */
	public static function service_summary_text( $svc ) {
		return SimpleVPBot_Texts::format(
			"📡 سرویس: {name}" . SimpleVPBot_Service_Alerts::text_sep() . "🆔 شناسه اتصال: {email}\n⏳ انقضا: {exp}\n",
			array(
				'name'  => (string) $svc->remark,
				'email' => (string) $svc->email,
				'exp'   => $svc->expires_at ? self::format_datetime_fa( (string) $svc->expires_at ) : '♾️ بدون انقضا',
			)
		);
	}

	/**
	 * Traffic quota bytes from 3x-ui client on inbound (authoritative when panel is edited out-of-band).
	 *
	 * @param object $svc Service row.
	 * @return int|null null if not readable; int bytes (0 = unlimited on panel).
	 */
	private static function resolve_quota_bytes_from_panel( $svc ) {
		if ( SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
			return null;
		}
		$iid = (int) $svc->inbound_id;
		if ( $iid < 1 ) {
			return null;
		}
		$inbound = SimpleVPBot_Xui_Client::inbound_get( $iid );
		if ( ! is_array( $inbound ) ) {
			return null;
		}
		$cl = SimpleVPBot_Xui_Client::inbound_client_by_email( $inbound, (string) $svc->email );
		if ( ! is_array( $cl ) ) {
			return null;
		}
		return SimpleVPBot_Inbound_Linker::resolve_quota_bytes( $cl['totalGB'] ?? 0, (string) $svc->email );
	}

	/**
	 * Usage stats from panel (shared: bot summary + portal).
	 *
	 * @param object $svc Service.
	 * @return array<string, string|float>
	 */
	private static function collect_usage_stats( $svc ) {
		return SimpleVPBot_Xui_Client::run_with_panel(
			self::svc_panel_id_xui( $svc ),
			function () use ( $svc ) {
				return self::collect_usage_stats_on_panel( $svc );
			}
		);
	}

	/**
	 * Usage summary from DB only when the panel cannot be reached (never invent live traffic).
	 *
	 * @param object $svc Service row.
	 * @param string $reason Short code for logs (login|inbound|clients_empty).
	 * @return array<string, string|float|int>
	 */
	private static function collect_usage_stats_fallback_db( $svc, $reason = 'panel' ) {
		unset( $reason );
		$now            = time();
		$sub_id         = (string) ( $svc->sub_id ?: $svc->email );
		$exp_ts         = $svc->expires_at ? strtotime( (string) $svc->expires_at . ' UTC' ) : 0;
		$expired        = ( $exp_ts && $exp_ts < $now );
		$total          = (int) $svc->total_traffic;
		$used_bytes     = (float) (int) $svc->used_traffic;
		$volume_exhausted = $total > 0 && $used_bytes >= $total;
		$total_q        = $total > 0 ? self::format_bytes( $total ) : '♾️ نامحدود';
		$remained       = $total > 0 ? self::format_bytes( max( 0, $total - $used_bytes ) ) : '♾️ نامحدود';
		if ( $expired ) {
			$status_label = 'اتمام روز';
			$emoji        = '⛔';
		} elseif ( $volume_exhausted ) {
			$status_label = 'اتمام حجم';
			$emoji        = '⛔';
		} elseif ( $total > 0 ) {
			$status_label = 'فعال (تقریبی)';
			$emoji        = '⚠️';
		} else {
			$status_label = 'نامحدود (تقریبی)';
			$emoji        = '⚠️';
		}
		$exp = $svc->expires_at ? self::format_datetime_fa( (string) $svc->expires_at ) : 'بدون انقضا';
		return array(
			'sub_id'               => $sub_id,
			'status'               => $status_label,
			'status_label'         => $status_label,
			'status_emoji'         => $emoji,
			'is_expired'           => ( $expired || $volume_exhausted ) ? 1 : 0,
			'date_expired'         => $expired ? 1 : 0,
			'volume_exhausted'     => $volume_exhausted ? 1 : 0,
			'panel_client_enabled' => 1,
			'deleted'              => 0,
			'panel_unreachable'    => 1,
			'down_gb'              => '0.00',
			'up_gb'                => '0.00',
			'used_gb'              => number_format( $used_bytes / 1073741824, 2 ),
			'down_h'               => '➖',
			'up_h'                 => '➖',
			'used_h'               => self::format_bytes( $used_bytes ),
			'remained_h'           => $remained,
			'total_quota'          => $total_q,
			'last_online'          => '➖',
			'last_online_fa'       => '➖',
			'expiry'               => $exp,
			'expiry_fa'            => $exp,
			'remark'               => (string) $svc->remark,
		);
	}

	/**
	 * @param object $svc Service.
	 * @return array<string, string|float>
	 */
	private static function collect_usage_stats_on_panel( $svc ) {
		if ( ! SimpleVPBot_Xui_Client::login_with_retries( 6, 350000 ) ) {
			return self::collect_usage_stats_fallback_db( $svc, 'login' );
		}
		$inb = null;
		for ( $ig = 0; $ig < 4; $ig++ ) {
			$inb = SimpleVPBot_Xui_Client::inbound_get( (int) $svc->inbound_id );
			if ( is_array( $inb ) ) {
				break;
			}
			if ( $ig + 1 < 4 ) {
				SimpleVPBot_Xui_Client::clear_session();
				SimpleVPBot_Xui_Client::login_with_retries( 4, 280000 );
				usleep( 220000 );
			}
		}
		if ( ! is_array( $inb ) ) {
			return self::collect_usage_stats_fallback_db( $svc, 'inbound' );
		}
		$settings_chk = isset( $inb['settings'] ) ? $inb['settings'] : '';
		$dec_chk      = is_string( $settings_chk ) ? json_decode( $settings_chk, true ) : ( is_array( $settings_chk ) ? $settings_chk : array() );
		if ( ! is_array( $dec_chk ) || empty( $dec_chk['clients'] ) || ! is_array( $dec_chk['clients'] ) ) {
			return self::collect_usage_stats_fallback_db( $svc, 'clients_empty' );
		}
		$cl = SimpleVPBot_Xui_Client::inbound_client_by_email( $inb, (string) $svc->email );
		if ( ! is_array( $cl ) ) {
			// Never auto-delete: transient API glitches, email drift, or panel lag would orphan real users.
			if ( class_exists( 'SimpleVPBot_Logger' ) ) {
				SimpleVPBot_Logger::warning(
					'usage panel: client not in inbound snapshot (using DB fallback, no delete)',
					array(
						'service_id'  => (int) $svc->id,
						'email'       => (string) $svc->email,
						'inbound_id'  => (int) $svc->inbound_id,
					)
				);
			}
			$fb = self::collect_usage_stats_fallback_db( $svc, 'client_not_in_inbound' );
			$fb['panel_sync_uncertain'] = 1;
			return $fb;
		}
		$panel_enabled = isset( $cl['enable'] ) ? (bool) (int) $cl['enable'] : true;

		$tr       = SimpleVPBot_Xui_Client::get_client_traffics( (string) $svc->email );
		$obj      = is_array( $tr ) && isset( $tr['obj'] ) && is_array( $tr['obj'] ) ? $tr['obj'] : array();
		$up_bytes = isset( $obj['up'] ) ? (float) $obj['up'] : 0.0;
		$dn_bytes = isset( $obj['down'] ) ? (float) $obj['down'] : 0.0;
		$us_bytes = $up_bytes + $dn_bytes;
		$up_gb    = $up_bytes / 1073741824;
		$dn_gb    = $dn_bytes / 1073741824;
		$us_gb    = $us_bytes / 1073741824;

		$panel_total = SimpleVPBot_Inbound_Linker::resolve_quota_bytes( $cl['totalGB'] ?? 0, '' );
		$total       = null !== $panel_total
			? (int) $panel_total
			: self::normalize_service_quota_bytes( $svc, $us_bytes );
		$total_q  = $total > 0 ? self::format_bytes( $total ) : '♾️ نامحدود';
		$remained = $total > 0 ? self::format_bytes( max( 0, $total - $us_bytes ) ) : '♾️ نامحدود';

		$now     = time();
		$exp_ts  = $svc->expires_at ? strtotime( (string) $svc->expires_at . ' UTC' ) : 0;
		$expired = ( $exp_ts && $exp_ts < $now );
		$volume_exhausted = $total > 0 && $us_bytes >= $total;

		if ( ! $panel_enabled ) {
			$status_label = 'غیرفعال (خاموش در پنل)';
			$emoji        = '⏸';
		} elseif ( $expired ) {
			$status_label = 'اتمام روز';
			$emoji        = '⛔';
		} elseif ( $volume_exhausted ) {
			$status_label = 'اتمام حجم';
			$emoji        = '⛔';
		} elseif ( $total > 0 ) {
			$status_label = 'فعال';
			$emoji        = '✅';
		} else {
			$status_label = 'نامحدود';
			$emoji        = '♾️';
		}

		$sub_id = (string) ( $svc->sub_id ?: $svc->email );
		$last   = self::format_last_online( $obj['lastOnline'] ?? null );
		$exp    = $svc->expires_at ? self::format_datetime_fa( (string) $svc->expires_at ) : 'بدون انقضا';

		$remark_for_link = trim( (string) ( $cl['remark'] ?? '' ) );
		if ( '' === $remark_for_link ) {
			$remark_for_link = (string) $svc->email;
		}
		$portal_config_uri = '';
		if ( class_exists( 'SimpleVPBot_Config_Link' ) ) {
			$portal_config_uri = (string) SimpleVPBot_Config_Link::build( $inb, $cl, $remark_for_link, self::svc_panel_id_xui( $svc ) );
		}

		return array(
			'sub_id'               => $sub_id,
			'status'               => $status_label,
			'status_label'         => $status_label,
			'status_emoji'         => $emoji,
			'is_expired'           => ( $expired || $volume_exhausted ) ? 1 : 0,
			'date_expired'         => $expired ? 1 : 0,
			'volume_exhausted'     => $volume_exhausted ? 1 : 0,
			'panel_client_enabled' => $panel_enabled ? 1 : 0,
			'deleted'              => 0,
			'down_gb'              => number_format( $dn_gb, 2 ),
			'up_gb'                => number_format( $up_gb, 2 ),
			'used_gb'              => number_format( $us_gb, 2 ),
			'down_h'               => self::format_bytes( $dn_bytes ),
			'up_h'                 => self::format_bytes( $up_bytes ),
			'used_h'               => self::format_bytes( $us_bytes ),
			'remained_h'           => $remained,
			'total_quota'          => $total_q,
			'last_online'          => $last,
			'last_online_fa'       => $last,
			'expiry'               => $exp,
			'expiry_fa'            => $exp,
			'remark'               => (string) $svc->remark,
			'_portal_config_uri'   => $portal_config_uri,
		);
	}

	/**
	 * Normalize stored quota bytes when older code stored GB as bytes twice.
	 *
	 * Heuristic: if quota is absurdly larger than live usage, divide once by GiB.
	 *
	 * @param object    $svc Service row.
	 * @param float|int $used_bytes Used bytes from panel.
	 * @return int
	 */
	private static function normalize_service_quota_bytes( $svc, $used_bytes ) {
		$raw = (int) $svc->total_traffic;
		if ( $raw <= 0 ) {
			return 0;
		}
		$used = (float) $used_bytes;
		// If usage is tiny but quota is huge, likely mis-scaled (GB treated as bytes then * GiB).
		if ( $used > 0 && $raw > $used * 5000 && $raw > 50 * 1073741824 ) {
			$adj = (int) round( $raw / 1073741824 );
			if ( $adj > 0 && $adj < $raw ) {
				return $adj;
			}
		}
		return $raw;
	}

	/**
	 * Human-readable bytes with Persian units and digits.
	 *
	 * @param float|int $bytes Raw bytes.
	 * @return string
	 */
	private static function format_bytes( $bytes ) {
		return SimpleVPBot_Bot_Persian_Text::format_bytes_fa( $bytes );
	}

	/**
	 * Default Persian usage-panel template (when `msg.subscription_panel` is unset
	 * or the old English seed is kept). No {config_link} in this template:
	 * usage and configs are separate messages now.
	 *
	 * @return string
	 */
	private static function default_usage_template_fa() {
		$sep = "\n──────────\n";
		return "📊 وضعیت سرویس" . $sep . "🏷 نام: {remark}\n🆔 شناسه: {sub_id}\n📶 وضعیت: {status_emoji} {status}" . $sep . "⬇️ دانلود: {down_h}\n⬆️ آپلود: {up_h}\n📊 مصرف کل: {used_h}\n🧮 سهمیه: {total_quota}\n🎯 باقی‌مانده: {remained_h}" . $sep . "🕒 آخرین اتصال: {last_online}\n⏳ انقضا: {expiry}";
	}

	/**
	 * Pretty usage panel (shared by Telegram + Bale).
	 *
	 * @param object $svc Service.
	 * @return string
	 */
	private static function build_usage_panel_text( $svc ) {
		$v   = self::collect_usage_stats( $svc );
		if ( ! empty( $v['deleted'] ) ) {
			return '⛔ این سرویس دیگر روی پنل نیست و از لیست شما حذف شد.';
		}
		$tpl = SimpleVPBot_Texts::get( 'msg.subscription_panel', self::default_usage_template_fa() );
		$txt = SimpleVPBot_Texts::format( $tpl, $v );
		if ( ! empty( $v['panel_unreachable'] ) ) {
			$txt .= "\n\n⚠️ اتصال زنده به پنل برقرار نشد؛ جزئیات ترافیک پنل در دسترس نیست و بخشی از اعداد از آخرین ذخیرهٔ ربات است.";
		}
		if ( ! empty( $v['panel_sync_uncertain'] ) ) {
			$txt .= "\n\n⚠️ سرویس در این لحظه روی پنل در لیست کلاینت‌ها دیده نشد؛ اشتراک شما در ربات حذف نشده است. اگر این پیام تکرار شد با پشتیبانی تماس بگیرید.";
		}
		return $txt;
	}

	/**
	 * Backward-compat wrapper (no longer includes config link in body).
	 *
	 * @param object $svc Service.
	 * @return string
	 */
	private static function build_telegram_panel_text( $svc ) {
		return self::build_usage_panel_text( $svc );
	}

	/**
	 * Keyboard rows: portal URL + per-config callback (avoids copy_text API quirks).
	 *
	 * @param array<string, mixed> $data       get_portal_service_data output.
	 * @param string                 $portal     HMAC portal URL.
	 * @param int                    $service_id svp_services.id for svc:w callbacks.
	 * @return array<int, array<int, array<string, mixed>>>
	 */
	private static function build_config_copy_keyboard_rows( array $data, $portal, $service_id = 0 ) {
		$uris = isset( $data['config_uris'] ) && is_array( $data['config_uris'] ) ? $data['config_uris'] : array();
		$rows = array();
		$port = (string) ( $data['portal_url'] ?? $portal );
		if ( '' !== $port ) {
			$rows[] = array( array( 'text' => SimpleVPBot_Keyboards::glass_button_text( 'پنل وب' ), 'url' => $port ) );
		}
		$sid   = (int) $service_id;
		$idx   = 0;
		$n_uri = count( $uris );
		foreach ( $uris as $u ) {
			if ( $sid > 0 ) {
				$cb = 'svc:w:' . $sid . ':' . $idx;
				if ( strlen( $cb ) <= 64 ) {
					$lbl = $n_uri > 1 ? ( '📋 کانفیگ ' . ( $idx + 1 ) ) : '📋 کانفیگ';
					$rows[] = array( array( 'text' => SimpleVPBot_Keyboards::glass_button_text( $lbl ), 'callback_data' => $cb ) );
				}
			}
			++$idx;
			if ( $idx >= 20 ) {
				break;
			}
		}
		return $rows;
	}

	/**
	 * HTML caption for Telegram (monospace in code / b labels).
	 *
	 * @param array<string, mixed> $data    Portal data.
	 * @param string                 $portal  Portal base.
	 * @param bool                  $truncated Set true if truncated.
	 * @return string
	 */
	private static function build_telegram_config_caption_html( array $data, $portal, &$truncated = false ) {
		$uris  = isset( $data['config_uris'] ) && is_array( $data['config_uris'] ) ? $data['config_uris'] : array();
		$lines = array();
		$idx   = 1;
		$n_uri = count( $uris );
		foreach ( $uris as $u ) {
			$title = $n_uri > 1 ? "🧾 <b>کانفیگ {$idx}</b>" : '🧾 <b>کانفیگ</b>';
			$lines[] = $title . "\n" . '<code>' . esc_html( (string) $u ) . '</code>';
			$idx++;
			if ( $idx > 20 ) {
				break;
			}
		}
		$out = implode( "\n\n", $lines );
		if ( '' === $out ) {
			$out = '🧩';
		}
		$max = 900;
		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) && mb_strlen( $out, 'UTF-8' ) > $max ) {
			$out       = mb_substr( $out, 0, $max, 'UTF-8' ) . "…\n" . 'بقیه را با دکمهٔ کانفیگ بگیرید.';
			$truncated = true;
		} elseif ( strlen( $out ) > $max + 200 ) {
			$out       = substr( $out, 0, $max ) . "…\n" . 'بقیه را با دکمهٔ کانفیگ بگیرید.';
			$truncated = true;
		}
		return $out;
	}

	/**
	 * One message: photo (QR) + HTML caption + inline rows (callback per config).
	 *
	 * @param int                  $chat_id    Chat.
	 * @param array<string, mixed> $data       Data.
	 * @param string               $portal     HMAC link.
	 * @param int                  $service_id Service id for config buttons.
	 */
	private static function telegram_send_config_unified( $chat_id, array $data, $portal, $service_id = 0 ) {
		$import = (string) ( $data['import_sub_url'] ?? $data['subscription_url'] ?? '' );
		$uris   = isset( $data['config_uris'] ) && is_array( $data['config_uris'] ) ? $data['config_uris'] : array();
		$port   = (string) ( $data['portal_url'] ?? $portal );
		if ( '' === $import && empty( $uris ) && '' === $port ) {
			SimpleVPBot_Bot_Runtime::send_message(
				'telegram',
				$chat_id,
				"⚠️ لینک اشتراک هنوز آماده نیست.\n🧒 از ادمین بخواه اشتراک را روی سرور روشن کند و آدرس عمومی اشتراک در تنظیمات سایت درست شود."
			);
			return;
		}
		$trunc   = false;
		$caption = self::build_telegram_config_caption_html( $data, (string) $portal, $trunc );
		$markup  = self::build_config_copy_keyboard_rows( $data, (string) $portal, (int) $service_id );
		$inline  = array( 'inline_keyboard' => $markup );
		$extra   = array( 'parse_mode' => 'HTML', 'reply_markup' => $inline );
		$qr_base = '';
		if ( ! empty( $uris[0] ) ) {
			$qr_base = (string) $uris[0];
		}
		if ( '' === $qr_base ) {
			$qr_base = $import;
		}
		if ( '' === $qr_base ) {
			$qr_base = $port;
		}
		$sent = false;
		if ( class_exists( 'SimpleVPBot_Qr' ) && SimpleVPBot_Qr::is_available() && $qr_base ) {
			$bytes = SimpleVPBot_Qr::png_bytes( $qr_base );
			if ( $bytes ) {
				$tmp = function_exists( 'wp_tempnam' ) ? wp_tempnam( 'svp_qr' ) : @tempnam( sys_get_temp_dir(), 'svp' );
				if ( $tmp && false !== file_put_contents( $tmp, $bytes ) ) {
					$res  = SimpleVPBot_Bot_Runtime::send_photo_file( 'telegram', (int) $chat_id, $tmp, $caption, $extra );
					$sent = is_array( $res ) && ! empty( $res['ok'] );
					if ( $tmp && file_exists( $tmp ) ) {
						@unlink( $tmp );
					}
				}
			}
		}
		if ( ! $sent ) {
			$res = SimpleVPBot_Bot_Runtime::send_message( 'telegram', (int) $chat_id, $caption, $extra );
			$sent = is_array( $res ) && ! empty( $res['ok'] );
		}
		if ( ! $sent ) {
			$plain = wp_strip_all_tags( $caption );
			SimpleVPBot_Logger::error(
				'telegram_send_config_unified failed',
				array( 'chat_id' => (int) $chat_id, 'service_id' => (int) $service_id )
			);
			SimpleVPBot_Bot_Runtime::send_message(
				'telegram',
				(int) $chat_id,
				'⛔ ارسال کانفیگ/QR در تلگرام انجام نشد. دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.' . ( $plain ? "\n\n" . $plain : '' )
			);
		}
	}

	/**
	 * Callback svc:w:{service_id}:{index}: send one config line as plain text for easy copy.
	 *
	 * @param array<string, mixed> $ctx platform, user, svc_id, uri_idx, chat_id.
	 */
	public static function handle_config_wire( array $ctx ) {
		$platform = (string) $ctx['platform'];
		$user     = $ctx['user'];
		$sid      = (int) $ctx['svc_id'];
		$idx      = (int) $ctx['uri_idx'];
		$chat_id  = (int) $ctx['chat_id'];
		$from_id  = isset( $ctx['from_id'] ) ? (int) $ctx['from_id'] : 0;
		$svc      = SimpleVPBot_Model_Service::find( $sid );
		if ( ! self::service_caller_can_manage( $platform, $from_id, $user, $svc ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ دسترسی نامعتبر است.' );
			return;
		}
		$data = self::get_portal_service_data( $svc, (int) $svc->user_id );
		$uris = isset( $data['config_uris'] ) && is_array( $data['config_uris'] ) ? $data['config_uris'] : array();
		if ( ! isset( $uris[ $idx ] ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ این کانفیگ دیگر در دسترس نیست. منوی سرویس را دوباره باز کنید.' );
			return;
		}
		$line  = (string) $uris[ $idx ];
		$n_uri = count( $uris );
		$prefix = $n_uri > 1 ? ( '🧾 کانفیگ ' . ( $idx + 1 ) ) : '🧾 کانفیگ';
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $prefix . SimpleVPBot_Service_Alerts::text_sep() . $line );
	}

	/**
	 * Data for WordPress portal page (HTML-safe values + connection URLs).
	 *
	 * @param object  $svc      Service.
	 * @param int     $user_id  svp user id (for signed portal); 0 = use $svc->user_id.
	 * @return array<string, string|array>
	 */
	public static function get_portal_service_data( $svc, $user_id = 0 ) {
		if ( SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
			return self::get_portal_l2tp_data( $svc, $user_id );
		}
		$v = self::collect_usage_stats( $svc );
		$direct_cfg = '';
		if ( isset( $v['_portal_config_uri'] ) ) {
			$direct_cfg = (string) $v['_portal_config_uri'];
			unset( $v['_portal_config_uri'] );
		}
		if ( ! empty( $v['deleted'] ) ) {
			return array( '_deleted' => 1 );
		}
		$uid   = (int) $user_id > 0 ? (int) $user_id : (int) ( $svc->user_id ?? 0 );
		$import = SimpleVPBot_Config_Link::subscription_url( (string) $svc->sub_id, self::svc_panel_id_xui( $svc ) );
		$portal = ( $uid > 0 && (int) $svc->id > 0 ) ? SimpleVPBot_Portal_Link::build_service_url( $uid, (int) $svc->id ) : '';
		if ( ! empty( $v['panel_unreachable'] ) && '' !== $import ) {
			delete_transient( 'svp_sub_' . md5( $import ) );
		}
		$uris  = $import ? SimpleVPBot_Config_Link::fetch_subscription( $import ) : array();
		if ( empty( $uris ) && '' !== $direct_cfg ) {
			$uris = array( $direct_cfg );
		}
		$primary = '';
		if ( ! empty( $uris[0] ) ) {
			$primary = (string) $uris[0];
		}
		if ( '' === $primary ) {
			$primary = (string) $import;
		}
		return array_merge(
			$v,
			array(
				'import_sub_url'  => (string) $import,
				'subscription_url' => (string) $import,
				'portal_url'      => (string) $portal,
				'user_portal_url' => (string) $portal,
				'config_uris'     => $uris,
				'config_uri'      => ! empty( $uris ) ? (string) $uris[0] : '',
				'primary_link'    => (string) $primary,
			)
		);
	}

	/**
	 * Portal data for L2TP service (no panel live-usage; local traffic only).
	 *
	 * @param object $svc     Service.
	 * @param int    $user_id svp user id.
	 * @return array<string, mixed>
	 */
	public static function get_portal_l2tp_data( $svc, $user_id = 0 ) {
		$creds = SimpleVPBot_Model_Service::l2tp_credentials( $svc );
		$uid   = (int) $user_id > 0 ? (int) $user_id : (int) ( $svc->user_id ?? 0 );
		$portal = ( $uid > 0 && (int) $svc->id > 0 ) ? SimpleVPBot_Portal_Link::build_service_url( $uid, (int) $svc->id ) : '';
		$total = (int) $svc->total_traffic;
		$used  = (int) $svc->used_traffic;
		$now   = time();
		$exp_ts             = $svc->expires_at ? strtotime( (string) $svc->expires_at . ' UTC' ) : 0;
		$expired            = ( $exp_ts && $exp_ts < $now );
		$volume_exhausted   = $total > 0 && $used >= $total;
		$exp_fa             = $svc->expires_at ? self::format_datetime_fa( (string) $svc->expires_at ) : 'بدون انقضا';
		if ( $expired ) {
			$st = 'اتمام روز';
			$em = '⛔';
		} elseif ( $volume_exhausted ) {
			$st = 'اتمام حجم';
			$em = '⛔';
		} else {
			$st = 'فعال';
			$em = '✅';
		}
		return array(
			'sub_id'               => (string) ( $creds['username'] ?? $svc->l2tp_username ?? '' ),
			'status'               => $st,
			'status_label'         => $st,
			'status_emoji'         => $em,
			'is_expired'           => ( $expired || $volume_exhausted ) ? 1 : 0,
			'date_expired'         => $expired ? 1 : 0,
			'volume_exhausted'     => $volume_exhausted ? 1 : 0,
			'panel_client_enabled' => 1,
			'down_h'           => '➖',
			'up_h'             => '➖',
			'used_h'           => $used > 0 ? self::format_bytes( $used ) : '➖',
			'total_quota'      => $total > 0 ? self::format_bytes( $total ) : '♾️ نامحدود',
			'remained_h'       => $total > 0 ? self::format_bytes( max( 0, $total - $used ) ) : '♾️ نامحدود',
			'last_online'      => '➖',
			'last_online_fa'   => '➖',
			'expiry'           => $exp_fa,
			'expiry_fa'        => $exp_fa,
			'remark'           => (string) $svc->remark,
			'import_sub_url'   => '',
			'subscription_url' => '',
			'portal_url'      => (string) $portal,
			'user_portal_url'  => (string) $portal,
			'config_uri'       => '',
			'primary_link'     => '',
			'l2tp'             => $creds ? array(
				'username' => (string) $creds['username'],
				'password' => (string) $creds['password'],
				'psk'      => (string) $creds['psk'],
				'host'     => (string) $creds['host'],
			) : null,
			'down_gb' => '0.00',
			'up_gb'   => '0.00',
			'used_gb' => $used > 0 ? number_format( $used / 1073741824, 2 ) : '0.00',
		);
	}

	/**
	 * Render L2TP credentials in bot chat (message + copy buttons on Telegram).
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id Chat id.
	 * @param object $svc Service row.
	 */
	private static function show_l2tp_credentials( $platform, $chat_id, $svc ) {
		$c = SimpleVPBot_Model_Service::l2tp_credentials( $svc );
		if ( ! $c || '' === $c['username'] ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, '⛔ اطلاعات اتصال یافت نشد. با پشتیبانی تماس بگیرید.' );
			return;
		}
		$exp = $svc->expires_at ? self::format_datetime_fa( (string) $svc->expires_at ) : 'بدون انقضا';
		$txt = '🔐 اتصال L2TP/IPsec' . SimpleVPBot_Service_Alerts::text_sep()
			. '🌐 سرور: ' . $c['host'] . "\n"
			. '🔑 PSK: ' . $c['psk'] . "\n"
			. '👤 نام کاربری: ' . $c['username'] . "\n"
			. '🔒 رمز عبور: ' . $c['password'] . "\n"
			. "⏳ اعتبار: " . $exp;

		$rows = array();
		if ( 'telegram' === $platform ) {
			$rows[] = array( array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '📋 کپی سرور' ), 'copy_text' => array( 'text' => $c['host'] ) ) );
			$rows[] = array( array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '📋 کپی PSK' ), 'copy_text' => array( 'text' => $c['psk'] ) ) );
			$rows[] = array( array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '📋 کپی نام کاربری' ), 'copy_text' => array( 'text' => $c['username'] ) ) );
			$rows[] = array( array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '📋 کپی رمز عبور' ), 'copy_text' => array( 'text' => $c['password'] ) ) );
		}
		$rows[] = array( array( 'text' => SimpleVPBot_Keyboards::glass_button_text( '⬅️ بازگشت' ), 'callback_data' => 'svc:m:' . (int) $svc->id ) );
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			$txt,
			array( 'reply_markup' => array( 'inline_keyboard' => $rows ) )
		);
	}

	/**
	 * Format `lastOnline` from panel (ms/seconds/datetime string) into Persian-friendly text.
	 *
	 * @param mixed $raw Raw value.
	 * @return string
	 */
	private static function format_last_online( $raw ) {
		if ( null === $raw || '' === $raw ) {
			return '➖';
		}
		$ts = 0;
		if ( is_numeric( $raw ) ) {
			$n = (int) $raw;
			if ( $n > 10000000000 ) {
				$n = (int) ( $n / 1000 );
			}
			if ( $n <= 0 ) {
				return '➖';
			}
			$ts = $n;
		} else {
			$parsed = strtotime( (string) $raw );
			if ( false === $parsed ) {
				return (string) $raw;
			}
			$ts = (int) $parsed;
		}
		if ( $ts <= 0 ) {
			return '➖';
		}
		$out = '';
		if ( class_exists( 'SimpleVPBot_Jalali_Date' ) ) {
			$out = SimpleVPBot_Jalali_Date::format_datetime( $ts );
		} else {
			$out = wp_date( 'Y-m-d H:i', $ts );
		}
		return SimpleVPBot_Bot_Persian_Text::digits_to_fa( $out );
	}

	/**
	 * Format a DB UTC datetime with site's timezone.
	 *
	 * @param string $datetime Datetime string.
	 * @return string
	 */
	private static function format_datetime_fa( $datetime ) {
		$ts = strtotime( $datetime . ' UTC' );
		if ( false === $ts ) {
			return $datetime;
		}
		if ( class_exists( 'SimpleVPBot_Jalali_Date' ) ) {
			return SimpleVPBot_Bot_Persian_Text::digits_to_fa( SimpleVPBot_Jalali_Date::format_datetime( $ts ) );
		}
		return SimpleVPBot_Bot_Persian_Text::digits_to_fa( wp_date( 'Y-m-d H:i', $ts ) );
	}

	/**
	 * Primary link: subscription URL or config URI.
	 *
	 * @param object $svc Service.
	 * @return string
	 */
	private static function get_primary_link( $svc ) {
		$sub = SimpleVPBot_Config_Link::subscription_url( (string) $svc->sub_id, self::svc_panel_id_xui( $svc ) );
		if ( $sub ) {
			return $sub;
		}
		return self::get_config_link( $svc );
	}

	/**
	 * Subscription or config.
	 *
	 * @param object $svc Service.
	 * @return string
	 */
	private static function get_subscription_or_config_link( $svc ) {
		return self::get_primary_link( $svc );
	}

	/**
	 * Deprecated: no longer used. We never build URIs locally: always use
	 * 3x-ui's subscription service to retrieve the exact config strings.
	 *
	 * @param object $svc Service.
	 * @return string
	 */
	private static function get_config_link( $svc ) {
		unset( $svc );
		return '';
	}
}
