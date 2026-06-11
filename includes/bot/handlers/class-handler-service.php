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
	 * Transient TTL for Telegram config dedupe (seconds).
	 */
	const CONFIG_SENT_TTL_SEC = 900;

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
		$cb_id    = isset( $ctx['cb_id'] ) ? (string) $ctx['cb_id'] : '';

		$svc = SimpleVPBot_Model_Service::find( $svc_id );
		if ( $svc && class_exists( 'SimpleVPBot_Feature_L2tp' ) && ! SimpleVPBot_Feature_L2tp::service_visible( $svc ) ) {
			$svc = null;
		}
		if ( ! self::service_caller_can_manage( $platform, $from_id, $user, $svc ) ) {
			if ( ! $svc ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.not_found', $user ) );
			}
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
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.l2tp_password_ok', $user ) );
				self::show_l2tp_credentials( $platform, $chat_id, $svc_fresh );
			} else {
				SimpleVPBot_Bot_Runtime::send_message_with_support( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.l2tp_password_fail', $user ) );
			}
			return;
		}
		if ( SimpleVPBot_Model_Service::is_l2tp( $svc ) && in_array( $action, array( 'u', 'ip', 'f' ), true ) ) {
			if ( 'f' === $action ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'faq.l2tp', $user ) );
				return;
			}
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.l2tp_option_na', $user ) );
			return;
		}
		if ( 'us' === $action ) {
			$text   = self::build_usage_panel_text( $svc, $platform );
			$markup = SimpleVPBot_Keyboards::inline_subscription_back_only( $svc_id, $user );
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
			self::answer_svc_processing_toast( $platform, $cb_id );
			$panel_msg_id = (int) $msg_id;
			self::schedule_svc_panel_full_delivery( $platform, $chat_id, $panel_msg_id, $svc, $owner_uid, $user, 'p' );
			return;
		}
		if ( 'l' === $action || 'q' === $action ) {
			self::answer_svc_processing_toast( $platform, $cb_id );
			$portal = SimpleVPBot_Portal_Link::build_service_url( $owner_uid, (int) $svc_id );
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
			self::schedule_svc_panel_full_delivery( $platform, $chat_id, 0, $svc, $owner_uid, $user, $action );
			return;
		}
		if ( 'rs' === $action ) {
			if ( ! class_exists( 'SimpleVPBot_Service_Dashboard_Panel' ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.panel_update_fail', $user ) );
				return;
			}
			$r = SimpleVPBot_Service_Dashboard_Panel::xray_regenerate_sub_id( $svc_id );
			if ( empty( $r['ok'] ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.panel_update_fail', $user ) );
				return;
			}
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.sub_id_regenerated', $user ) );
			return;
		}
		if ( 'k' === $action ) {
			SimpleVPBot_Xui_Client::run_with_panel(
				self::svc_panel_id_xui( $svc ),
				function () use ( $platform, $chat_id, $svc, $svc_id ) {
					if ( ! SimpleVPBot_Xui_Client::login_with_retries( 5, 300000 ) ) {
						SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.panel_login_fail', $user ) );
						return;
					}
					$new = SimpleVPBot_Xui_Client::get_new_uuid();
					if ( ! $new || ! SimpleVPBot_Xui_Client::is_likely_client_uuid( (string) $new ) ) {
						SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.uuid_fail', $user ) );
						return;
					}
					if ( SimpleVPBot_Xui_Client::is_v3_clients_api() ) {
						$res = SimpleVPBot_Xui_Client::client_update_v3(
							(string) $svc->email,
							array( 'id' => $new ),
							array( (int) $svc->inbound_id )
						);
						if ( ! SimpleVPBot_Xui_Client::response_is_success( $res ) ) {
							SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.panel_update_fail', $user ) );
							return;
						}
						SimpleVPBot_Model_Service::update( $svc_id, array( 'xui_client_id' => $new, 'xui_client_uuid' => $new ) );
						SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.uuid_regenerated', $user ) );
						return;
					}
					$inbound = SimpleVPBot_Xui_Client::inbound_get( (int) $svc->inbound_id );
					if ( ! $inbound ) {
						SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.inbound_not_found', $user ) );
						return;
					}
					$old_key = SimpleVPBot_Xui_Client::resolve_client_key_for_update( (string) $svc->xui_client_id, $inbound, (string) $svc->email );
					if ( ! $old_key || ! SimpleVPBot_Xui_Client::is_likely_client_uuid( (string) $old_key ) ) {
						SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.client_id_invalid', $user ) );
						return;
					}
					$settings = isset( $inbound['settings'] ) ? $inbound['settings'] : '';
					$dec      = is_string( $settings ) ? json_decode( $settings, true ) : ( is_array( $settings ) ? $settings : array() );
					if ( empty( $dec['clients'] ) || ! is_array( $dec['clients'] ) ) {
						SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.client_list_empty', $user ) );
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
						SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.client_not_found', $user ) );
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
						SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.panel_update_fail', $user ) );
						return;
					}
					SimpleVPBot_Model_Service::update( $svc_id, array( 'xui_client_id' => $new, 'xui_client_uuid' => $new ) );
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.uuid_regenerated', $user ) );
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
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.servers_refreshed', $user ) );
			return;
		}
		if ( 'ar' === $action ) {
			$on = ! (int) $svc->autorenew;
			SimpleVPBot_Model_Service::update( $svc_id, array( 'autorenew' => $on ? 1 : 0 ) );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $on ? SimpleVPBot_Texts::get_for_user( 'msg.svc.auto_renew_on', $user ) : SimpleVPBot_Texts::get_for_user( 'msg.svc.auto_renew_off', $user ) );
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
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.prompt_panel_note', $user ) );
			return;
		}
		if ( 'rn' === $action ) {
			SimpleVPBot_State::set( (int) $user->id, 'svc_rename_' . $svc_id, array() );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.prompt_display_name', $user ) );
			return;
		}
		if ( 'ip' === $action ) {
			SimpleVPBot_Xui_Client::run_with_panel(
				self::svc_panel_id_xui( $svc ),
				function () use ( $platform, $chat_id, $svc ) {
					SimpleVPBot_Xui_Client::login_with_retries( 5, 300000 );
					$j   = SimpleVPBot_Xui_Client::client_ips( (string) $svc->email );
					$ips = SimpleVPBot_Xui_Client::parse_client_ips_response( $j, 20 );
					$txt = '🌐 اتصالات فعال' . SimpleVPBot_Service_Alerts::text_sep();
					$txt .= empty( $ips ) ? '📭 هنوز موردی نیست' : '• ' . implode( "\n• ", $ips );
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $txt );
				}
			);
			return;
		}
		if ( 'f' === $action ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'faq.connection', $user ) );
			return;
		}
		if ( 'su' === $action ) {
			SimpleVPBot_Bot_Runtime::send_message_with_support( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.support_contact_admin', $user ) );
			return;
		}
		if ( 'r' === $action ) {
			if ( SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
				SimpleVPBot_Bot_Runtime::send_message_with_support( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.renew_xray_only', $user ) );
				return;
			}
			$plan = SimpleVPBot_Model_Service::effective_plan_for_pricing( $svc );
			if ( ! $plan ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.default_plan_missing', $user ) );
				return;
			}
			if ( ! self::is_platform_admin_managing_other_users_service( $platform, $from_id, $user, $svc )
				&& ! SimpleVPBot_Service_Renew::user_may_renew_same( $svc ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Service_Renew::reject_renew_message( $user ) );
				return;
			}
			if ( self::is_platform_admin_managing_other_users_service( $platform, $from_id, $user, $svc ) ) {
				$rows = SimpleVPBot_Handler_Admin_Pnl::admin_service_payment_mode_inline_rows( 'renew', $svc_id, null );
				if ( empty( $rows ) ) {
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.internal_button_error', $user ) );
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
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.volume_xray_only', $user ) );
				return;
			}
			if ( ! self::is_platform_admin_managing_other_users_service( $platform, $from_id, $user, $svc )
				&& ! SimpleVPBot_Service_Renew::user_may_add_volume( $svc ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Service_Renew::reject_add_volume_message( $user ) );
				return;
			}
			$pid = SimpleVPBot_Model_Service::effective_plan_id_for_pricing( $svc );
			if ( $pid < 1 ) {
				SimpleVPBot_Bot_Runtime::send_message(
					$platform,
					$chat_id,
					SimpleVPBot_Texts::get_for_user( 'msg.svc.pergb_plan_missing', $user )
				);
				return;
			}
			SimpleVPBot_State::set( (int) $user->id, 'svc_addvol_' . $svc_id, array( 'plan_id' => $pid ) );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.prompt_add_volume_gb', $user ) );
			return;
		}
		if ( 'sl' === $action ) {
			if ( SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.option_wrong_type', $user ) );
				return;
			}
			$unit = (float) SimpleVPBot_Settings::get( 'price_per_extra_user', 0 );
			if ( $unit <= 0 ) {
				SimpleVPBot_Bot_Runtime::send_message_with_support(
					$platform,
					$chat_id,
					SimpleVPBot_Texts::format(
					SimpleVPBot_Texts::get_for_user( 'msg.svc.extra_user_price_unset', $user ),
					array( 'sep' => SimpleVPBot_Service_Alerts::text_sep() )
				)
				);
				return;
			}
			$pid = SimpleVPBot_Model_Service::effective_plan_id_for_pricing( $svc );
			if ( $pid < 1 ) {
				SimpleVPBot_Bot_Runtime::send_message(
					$platform,
					$chat_id,
					SimpleVPBot_Texts::get_for_user( 'msg.svc.plan_missing_for_section', $user )
				);
				return;
			}
			SimpleVPBot_State::set( (int) $user->id, 'svc_addusers_' . $svc_id, array( 'plan_id' => $pid ) );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				SimpleVPBot_Texts::get_for_user( 'msg.alerts.add_users_prompt', $user )
			);
			return;
		}
		if ( 'b' === $action ) {
			if ( $owner_uid > 0 && class_exists( 'SimpleVPBot_Service_Reconcile' ) ) {
				SimpleVPBot_Service_Reconcile::reconcile_for_user( $owner_uid );
			}
			$list   = SimpleVPBot_Model_Service::by_user( $owner_uid );
			$mk     = SimpleVPBot_Keyboards::inline_service_list( $list, $user );
			$caption = SimpleVPBot_Texts::get_for_user( 'msg.svc.list_title', $user );
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
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.transfer_code_fail', $user ) );
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
				SimpleVPBot_Texts::format(
					SimpleVPBot_Texts::get_for_user( 'msg.alerts.threshold_volume_prompt', $user ),
					array( 'pct' => (string) SimpleVPBot_Service_Alerts::effective_low_traffic_pct( $svc ) )
				)
				. SimpleVPBot_Texts::get_for_user( 'msg.alerts.threshold_cancel_hint', $user )
			);
			return;
		}
		if ( 'a6' === $action ) {
			SimpleVPBot_State::set( (int) $user->id, 'svc_al_exp_' . $svc_id, array() );
			$d = implode( ',', SimpleVPBot_Service_Alerts::effective_expiry_days( $svc ) );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				SimpleVPBot_Texts::format(
					SimpleVPBot_Texts::get_for_user( 'msg.alerts.threshold_expiry_prompt', $user ),
					array( 'days' => $d )
				)
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
				SimpleVPBot_Texts::format(
					SimpleVPBot_Texts::get_for_user( 'msg.alerts.threshold_ip_prompt', $user ),
					array( 'pct' => (string) SimpleVPBot_Service_Alerts::effective_ip_fill_pct( $svc ) )
				)
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
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.invalid_service', $user ) );
			return;
		}
		if ( 'ip' === $kind && SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
			SimpleVPBot_State::clear( (int) $user->id );
			return;
		}
		if ( 'pct' === $kind ) {
			$d = trim( SimpleVPBot_Bot_Runtime::normalize_digits( $raw ) );
			if ( ! preg_match( '/^\d+$/', $d ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.alert_days_1_99', $user ) );
				return;
			}
			$n = (int) $d;
			if ( $n < 1 || $n > 99 ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.alert_days_range', $user ) );
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
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.alert_days_min', $user ) );
				return;
			}
			SimpleVPBot_Model_Service::update( $sid, array( 'alert_expiry_days' => implode( ',', $days ) ) );
			self::alerts_sync_master_flag( $sid );
		} else {
			$d = trim( SimpleVPBot_Bot_Runtime::normalize_digits( $raw ) );
			if ( ! preg_match( '/^\d+$/', $d ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.alert_pct_50_100', $user ) );
				return;
			}
			$n = (int) $d;
			if ( $n < 50 || $n > 100 ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.alert_pct_range', $user ) );
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
			SimpleVPBot_Texts::format(
				SimpleVPBot_Texts::get_for_user( 'msg.svc.alert_threshold_saved', $user ),
				array(
					'sep'     => SimpleVPBot_Service_Alerts::text_sep(),
					'summary' => $sum,
				)
			),
			array(
				'reply_markup' => array(
					'inline_keyboard' => array(
						array( array( 'text' => SimpleVPBot_Keyboards::glass_button_text( SimpleVPBot_Texts::get_for_user( 'btn.svc.open_alerts', $user ) ), 'callback_data' => 'svc:al:' . $sid ) ),
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
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.invalid_session', $user ) );
			return;
		}
		if ( ! preg_match( '/^\d+$/u', $raw ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.integer_only', $user ) );
			return;
		}
		$gb = (int) $raw;
		if ( $gb < 1 ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.min_1_gb', $user ) );
			return;
		}
		if ( SimpleVPBot_Model_Plan::is_per_gb( $plan ) && ! SimpleVPBot_Model_Plan::is_volume_in_range( $plan, $gb ) ) {
			$min = (int) ( $plan->traffic_gb_min ?? 0 );
			$max = (int) ( $plan->traffic_gb_max ?? 0 );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				SimpleVPBot_Texts::format(
					SimpleVPBot_Texts::get_for_user( 'msg.svc.volume_range', $user ),
					array(
						'min' => (string) $min,
						'max' => (string) $max,
					)
				)
			);
			return;
		}
		if ( ! SimpleVPBot_Model_Plan::is_per_gb( $plan ) && $gb > 512 ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.max_512_gb', $user ) );
			return;
		}
		$amount = SimpleVPBot_Service_Renew::checkout_price_add_volume( $plan, $gb );
		if ( $amount <= 0 ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.invalid_amount', $user ) );
			return;
		}
		if ( ! self::is_platform_admin_managing_other_users_service( $platform, $from_id, $user, $svc )
			&& ! SimpleVPBot_Service_Renew::user_may_add_volume( $svc ) ) {
			SimpleVPBot_State::clear( (int) $user->id );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Service_Renew::reject_add_volume_message( $user ) );
			return;
		}
		SimpleVPBot_State::clear( (int) $user->id );
		if ( self::is_platform_admin_managing_other_users_service( $platform, $from_id, $user, $svc ) ) {
			$rows = SimpleVPBot_Handler_Admin_Pnl::admin_service_payment_mode_inline_rows( 'vol', $sid, $gb );
			if ( empty( $rows ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.internal_button_error', $user ) );
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
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.invalid_session', $user ) );
			return;
		}
		if ( ! preg_match( '/^\d+$/u', $raw ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.slots_integer', $user ) );
			return;
		}
		$n = (int) $raw;
		if ( $n < 1 || $n > 50 ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.slots_range', $user ) );
			return;
		}
		$amount = SimpleVPBot_Service_Renew::checkout_price_add_user_slots( $n );
		if ( $amount <= 0 ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.extra_user_price_zero', $user ) );
			return;
		}
		SimpleVPBot_State::clear( (int) $user->id );
		if ( self::is_platform_admin_managing_other_users_service( $platform, $from_id, $user, $svc ) ) {
			$rows = SimpleVPBot_Handler_Admin_Pnl::admin_service_payment_mode_inline_rows( 'slots', $sid, $n );
			if ( empty( $rows ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.internal_button_error', $user ) );
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
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.invalid_service', $user ) );
			return;
		}
		if ( '' === $text ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.empty_text', $user ) );
			return;
		}
		if ( SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
			SimpleVPBot_Model_Service::update( $sid, array( 'remark' => $text ) );
			SimpleVPBot_State::clear( (int) $user->id );
			$is_rename = ( 0 === strpos( $state, 'svc_rename_' ) );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $is_rename ? SimpleVPBot_Texts::get_for_user( 'msg.svc.display_name_updated', $user ) : SimpleVPBot_Texts::get_for_user( 'msg.svc.note_updated', $user ) );
			return;
		}
		$is_rename = ( 0 === strpos( $state, 'svc_rename_' ) );
		if ( $is_rename ) {
			SimpleVPBot_Model_Service::update( $sid, array( 'display_label' => $text ) );
			SimpleVPBot_State::clear( (int) $user->id );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.display_name_bot_updated', $user ) );
			return;
		}
		SimpleVPBot_Xui_Client::run_with_panel(
			self::svc_panel_id_xui( $svc ),
			function () use ( $platform, $chat_id, $user, $svc, $sid, $text ) {
				if ( ! SimpleVPBot_Xui_Client::login_with_retries( 5, 300000 ) ) {
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.panel_login_retry', $user ) );
					return;
				}
				if ( SimpleVPBot_Xui_Client::is_v3_clients_api() ) {
					$patch = array( 'comment' => $text );
					if ( SimpleVPBot_Service_Naming::is_platform_slug_service( $svc ) ) {
						$patch['comment'] = $text;
					}
					$res = SimpleVPBot_Xui_Client::client_update_v3(
						(string) $svc->email,
						$patch,
						array( (int) $svc->inbound_id )
					);
					if ( ! SimpleVPBot_Xui_Client::response_is_success( $res ) ) {
						SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.panel_update_fail', $user ) );
						return;
					}
					SimpleVPBot_State::clear( (int) $user->id );
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.note_updated', $user ) );
					return;
				}
				$inbound = SimpleVPBot_Xui_Client::inbound_get( (int) $svc->inbound_id );
				if ( ! $inbound ) {
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.inbound_not_found', $user ) );
					return;
				}
				$settings = isset( $inbound['settings'] ) ? $inbound['settings'] : '';
				$dec      = is_string( $settings ) ? json_decode( $settings, true ) : ( is_array( $settings ) ? $settings : array() );
				if ( empty( $dec['clients'] ) || ! is_array( $dec['clients'] ) ) {
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.client_list_empty_panel', $user ) );
					return;
				}
				$updated_client = null;
				foreach ( $dec['clients'] as &$cl ) {
					if ( isset( $cl['email'] ) && (string) $cl['email'] === (string) $svc->email ) {
						if ( SimpleVPBot_Service_Naming::is_platform_slug_service( $svc ) ) {
							$cl['remark']  = class_exists( 'SimpleVPBot_Reseller_Branding' )
								? (string) SimpleVPBot_Reseller_Branding::panel_brand_only_for_user( (int) $svc->user_id )
								: (string) get_bloginfo( 'name' );
							$cl['comment'] = $text;
						} else {
							$cl['remark'] = $text;
						}
						$updated_client = $cl;
						break;
					}
				}
				unset( $cl );
				if ( ! is_array( $updated_client ) ) {
					SimpleVPBot_State::clear( (int) $user->id );
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.client_not_found_panel', $user ) );
					return;
				}
				$old_key = SimpleVPBot_Xui_Client::resolve_client_key_for_update( (string) $svc->xui_client_id, $inbound, (string) $svc->email );
				if ( ! $old_key ) {
					SimpleVPBot_State::clear( (int) $user->id );
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.client_id_not_found_panel', $user ) );
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
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.panel_update_fail', $user ) );
					return;
				}
				SimpleVPBot_Model_Service::update(
					$sid,
					SimpleVPBot_Service_Naming::is_platform_slug_service( $svc )
						? array( 'service_note' => $text )
						: array( 'remark' => $text )
				);
				SimpleVPBot_State::clear( (int) $user->id );
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.note_and_name_updated', $user ) );
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
		$name = class_exists( 'SimpleVPBot_Service_Naming' )
			? SimpleVPBot_Service_Naming::public_label_for_service( $svc )
			: (string) $svc->remark;
		return SimpleVPBot_Texts::format(
			"📡 سرویس: {name}\n⏳ انقضا: {exp}\n",
			array(
				'name' => $name,
				'exp'  => $svc->expires_at ? self::format_datetime_fa( (string) $svc->expires_at ) : '♾️ بدون انقضا',
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

	private static function usage_identity_fields( $svc ) {
		if ( class_exists( 'SimpleVPBot_Service_Naming' ) ) {
			$canonical = SimpleVPBot_Service_Naming::canonical_label_for_service( $svc );
			$public    = SimpleVPBot_Service_Naming::public_label_for_service( $svc );
			return array(
				'sub_id'            => '',
				'subscription_id'   => '',
				'remark'            => $public,
				'subscription_name' => $canonical,
			);
		}
		$nam = trim( (string) ( $svc->remark ?? '' ) );
		return array(
			'sub_id'            => '',
			'subscription_id'   => '',
			'remark'            => $nam,
			'subscription_name' => $nam,
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
		$identity       = self::usage_identity_fields( $svc );
		$sub_id         = (string) $identity['sub_id'];
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
		return array_merge(
			$identity,
			array(
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
			'usage_footer_notes'   => self::usage_footer_notes_fa( 0, $total, $used_bytes ),
			)
		);
	}

	/**
	 * Extra lines under the usage panel (shared quota / over-cap explanation).
	 *
	 * @param int        $limit_ip Concurrent cap from panel client (0 if unknown).
	 * @param int        $total_bytes Quota bytes (0 = unlimited).
	 * @param float      $used_bytes Usage bytes.
	 * @return string Empty or multi-line Persian note without leading/trailing blank lines.
	 */
	private static function usage_footer_notes_fa( $limit_ip, $total_bytes, $used_bytes ) {
		$notes = array();
		$lip   = max( 0, (int) $limit_ip );
		$total = (int) $total_bytes;
		if ( $lip > 1 && $total > 0 ) {
			$lip_fa = SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $lip );
			$notes[] = '👥 تا ' . $lip_fa . ' اتصال هم‌زمان برای این اشتراک مجاز است؛ مصرف دانلود و آپلود برای همهٔ دستگاه‌ها یکجا محاسبه می‌شود.';
		}
		if ( $total > 0 && (float) $used_bytes > (float) $total + 1048576.0 ) {
			$notes[] = '⚠️ مصرف کل از سقف نمایش‌داده‌شده بیشتر است؛ اگر تازه حجم خریده‌اید یا چند نفر هم‌زمان استفاده می‌کنند، لحظاتی برای همگام‌سازی با پنل صبر کنید یا از پشتیبانی بپرسید.';
		}
		return $notes ? implode( "\n", $notes ) : '';
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
		$limit_ip = max( 0, (int) ( $cl['limitIp'] ?? 0 ) );
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

		$identity = self::usage_identity_fields( $svc );
		$last     = self::format_last_online( $obj['lastOnline'] ?? null );
		$exp      = $svc->expires_at ? self::format_datetime_fa( (string) $svc->expires_at ) : 'بدون انقضا';

		return array_merge(
			$identity,
			array(
			'status'               => $status_label,
			'status_label'         => $status_label,
			'status_emoji'         => $emoji,
			'is_expired'           => ( $expired || $volume_exhausted ) ? 1 : 0,
			'date_expired'         => $expired ? 1 : 0,
			'volume_exhausted'     => $volume_exhausted ? 1 : 0,
			'panel_client_enabled' => $panel_enabled ? 1 : 0,
			'deleted'              => 0,
			'usage_live_panel'     => 1,
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
			'usage_footer_notes'   => self::usage_footer_notes_fa( $limit_ip, $total, $us_bytes ),
			)
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
		return "📊 وضعیت سرویس" . $sep . "🏷 نام: {remark}\n📶 وضعیت: {status_emoji} {status}" . $sep . "⬇️ دانلود: {down_h}\n⬆️ آپلود: {up_h}\n📊 مصرف کل: {used_h}\n🧮 سهمیه: {total_quota}\n🎯 باقی‌مانده: {remained_h}" . $sep . "🕒 آخرین اتصال: {last_online}\n⏳ انقضا: {expiry}";
	}

	/**
	 * Pretty usage panel (shared by Telegram + Bale).
	 *
	 * @param object      $svc      Service.
	 * @param string|null $platform telegram|bale|null for support footer.
	 * @return string
	 */
	private static function build_usage_panel_text( $svc, $platform = null, $stats = null ) {
		$v   = is_array( $stats ) ? $stats : self::collect_usage_stats( $svc );
		if ( ! empty( $v['deleted'] ) ) {
			return SimpleVPBot_Texts::get_for_user( 'msg.svc.deleted_from_panel', $user );
		}
		$owner = isset( $svc->user_id ) ? SimpleVPBot_Model_User::find( (int) $svc->user_id ) : null;
		$tpl   = SimpleVPBot_Texts::get_for_user( 'msg.subscription_panel', $owner );
		if ( '' === $tpl ) {
			$tpl = SimpleVPBot_Texts::get_for_user( 'msg.subscription_panel', $owner, self::default_usage_template_fa() );
		}
		$txt = SimpleVPBot_Texts::format( $tpl, $v );
		if ( '' === trim( (string) ( $v['sub_id'] ?? '' ) ) ) {
			$txt = preg_replace( '/^.*🆔\s*شناسه:\s*$/mu', '', (string) $txt );
			$txt = preg_replace( "/\n{3,}/", "\n\n", (string) $txt );
			$txt = trim( (string) $txt );
		}
		$ufn = isset( $v['usage_footer_notes'] ) ? trim( (string) $v['usage_footer_notes'] ) : '';
		if ( '' !== $ufn ) {
			$txt .= "\n\n" . $ufn;
		}
		if ( ! empty( $v['usage_live_panel'] ) ) {
			$txt .= "\n\n" . SimpleVPBot_Texts::get_for_user( 'msg.svc.usage_live_footer', $owner );
		} elseif ( ! empty( $v['panel_unreachable'] ) ) {
			$txt .= "\n\n" . SimpleVPBot_Texts::get_for_user( 'msg.svc.usage_cache_footer', $owner );
		} else {
			$txt .= "\n\n" . SimpleVPBot_Texts::get_for_user( 'msg.svc.usage_stale_footer', $owner );
		}
		if ( ! empty( $v['panel_sync_uncertain'] ) ) {
			$txt .= "\n\n" . SimpleVPBot_Texts::get_for_user( 'msg.svc.usage_sync_uncertain', $owner );
		}
		if ( class_exists( 'SimpleVPBot_Support_Contacts' ) ) {
			$txt = SimpleVPBot_Support_Contacts::append_to_message( $txt, $platform );
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
		$uris   = isset( $data['config_uris'] ) && is_array( $data['config_uris'] ) ? $data['config_uris'] : array();
		$labels = isset( $data['config_labels'] ) && is_array( $data['config_labels'] ) ? $data['config_labels'] : array();
		$rows   = array();
		$port   = (string) ( $data['portal_url'] ?? $portal );
		if ( '' !== $port ) {
			$rows[] = array( array( 'text' => SimpleVPBot_Keyboards::glass_button_text( 'پنل وب' ), 'url' => $port ) );
		}
		$sid   = (int) $service_id;
		$idx   = 0;
		$n_uri = count( $uris );
		foreach ( $uris as $u ) {
			if ( '' === trim( (string) $u ) ) {
				++$idx;
				continue;
			}
			if ( $sid > 0 ) {
				$cb = 'svc:w:' . $sid . ':' . $idx;
				if ( strlen( $cb ) <= 64 ) {
					$remark = isset( $labels[ $idx ] ) ? trim( (string) $labels[ $idx ] ) : '';
					if ( '' === $remark ) {
						$remark = $n_uri > 1 ? ( 'کانفیگ ' . ( $idx + 1 ) ) : 'کانفیگ';
					}
					$lbl = '📋 ' . $remark;
					if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
						if ( mb_strlen( $lbl, 'UTF-8' ) > 30 ) {
							$lbl = mb_substr( $lbl, 0, 27, 'UTF-8' ) . '…';
						}
					} elseif ( strlen( $lbl ) > 30 ) {
						$lbl = substr( $lbl, 0, 27 ) . '...';
					}
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
	 * One Telegram message body: label + full URI in monospace (HTML).
	 *
	 * @param string $uri   Config URI (never truncated).
	 * @param string $label Optional display label; empty uses URI fragment.
	 * @param int    $index Zero-based index.
	 * @param int    $total Total config count.
	 * @return string
	 */
	private static function build_single_config_message_html( $uri, $label, $index, $total ) {
		$frag = trim( (string) $label );
		if ( '' === $frag && class_exists( 'SimpleVPBot_Config_Link' ) ) {
			$frag = SimpleVPBot_Config_Link::uri_fragment_label( (string) $uri );
		}
		$total       = max( 1, (int) $total );
		$display_idx = max( 1, (int) $index + 1 );
		if ( '' !== $frag ) {
			$title = '🧾 <b>' . esc_html( $frag ) . '</b>';
		} elseif ( $total > 1 ) {
			$title = '🧾 <b>کانفیگ ' . $display_idx . '</b>';
		} else {
			$title = '🧾 <b>کانفیگ</b>';
		}
		return $title . "\n" . '<code>' . esc_html( (string) $uri ) . '</code>';
	}

	/**
	 * Telegram chat id for outbound config delivery.
	 *
	 * @param object|null $user svp_users row.
	 * @return int
	 */
	public static function resolve_telegram_chat_id( $user ) {
		if ( ! is_object( $user ) ) {
			return 0;
		}
		return (int) ( $user->tg_user_id ?? 0 );
	}

	/**
	 * Signed dashboard / portal URL for user-facing QR and copy (never panel sub import).
	 *
	 * @param array<string, mixed> $data             Portal payload.
	 * @param string               $portal_fallback  HMAC link from caller.
	 * @return string
	 */
	private static function resolve_user_dashboard_url( array $data, $portal_fallback = '' ) {
		foreach ( array( 'portal_url', 'user_portal_url' ) as $key ) {
			$candidate = trim( (string) ( $data[ $key ] ?? '' ) );
			if ( '' !== $candidate ) {
				return $candidate;
			}
		}
		$fallback = trim( (string) $portal_fallback );
		return $fallback;
	}

	/**
	 * HTML body for copyable subscription link message.
	 *
	 * @param string $url Subscription URL.
	 * @return string
	 */
	private static function build_subscription_link_message_html( $url ) {
		$title = SimpleVPBot_Texts::get( 'msg.svc.subscription_link_title', '🔗 لینک اشتراک' );
		return '<b>' . esc_html( $title ) . '</b>' . "\n" . '<code>' . esc_html( (string) $url ) . '</code>';
	}

	/**
	 * QR photo caption: title + dashboard link.
	 *
	 * @param string $dashboard_url Signed portal URL.
	 * @return string
	 */
	private static function build_qr_caption_html( $dashboard_url ) {
		$title = SimpleVPBot_Texts::get( 'msg.svc.subscription_qr_caption', '📷 QR لینک اشتراک' );
		return '<b>' . esc_html( $title ) . '</b>' . "\n" . '<code>' . esc_html( (string) $dashboard_url ) . '</code>';
	}

	/**
	 * Plain-text QR caption fallback when HTML sendPhoto fails.
	 *
	 * @param string $dashboard_url Signed portal URL.
	 * @return string
	 */
	private static function build_qr_caption_plain( $dashboard_url ) {
		$title = SimpleVPBot_Texts::get( 'msg.svc.subscription_qr_caption', '📷 QR لینک اشتراک' );
		return $title . "\n" . (string) $dashboard_url;
	}

	/**
	 * Send dashboard QR photo; returns true when QR lib unavailable or photo delivered.
	 *
	 * @param int    $chat_id      Telegram chat.
	 * @param string $dashboard_url Dashboard URL payload.
	 * @param int    $service_id   Service id for logs.
	 * @return bool
	 */
	private static function send_config_qr_photo( $chat_id, $dashboard_url, $service_id = 0 ) {
		if ( ! class_exists( 'SimpleVPBot_Qr' ) || ! SimpleVPBot_Qr::is_available() ) {
			return true;
		}
		$dashboard = trim( (string) $dashboard_url );
		if ( '' === $dashboard ) {
			return false;
		}
		$bytes = SimpleVPBot_Qr::png_bytes( $dashboard );
		if ( ! $bytes ) {
			if ( class_exists( 'SimpleVPBot_Logger' ) ) {
				SimpleVPBot_Logger::error(
					'send_config_qr_photo png_bytes failed',
					array( 'chat_id' => (int) $chat_id, 'service_id' => (int) $service_id )
				);
			}
			return false;
		}
		$tmp = function_exists( 'wp_tempnam' ) ? wp_tempnam( 'svp_qr' ) : @tempnam( sys_get_temp_dir(), 'svp' );
		if ( ! $tmp || false === file_put_contents( $tmp, $bytes ) ) {
			return false;
		}
		$attempts = array(
			array(
				'caption' => self::build_qr_caption_html( $dashboard ),
				'extra'   => array( 'parse_mode' => 'HTML' ),
			),
			array(
				'caption' => self::build_qr_caption_plain( $dashboard ),
				'extra'   => array(),
			),
		);
		$ok = false;
		foreach ( $attempts as $attempt ) {
			$qr_res = SimpleVPBot_Bot_Runtime::send_photo_file(
				'telegram',
				(int) $chat_id,
				$tmp,
				(string) $attempt['caption'],
				(array) $attempt['extra']
			);
			if ( is_array( $qr_res ) && ! empty( $qr_res['result']['message_id'] ) ) {
				$ok = true;
				break;
			}
			if ( class_exists( 'SimpleVPBot_Logger' ) ) {
				SimpleVPBot_Logger::error(
					'send_config_qr_photo failed',
					array(
						'chat_id'    => (int) $chat_id,
						'service_id' => (int) $service_id,
						'desc'       => is_array( $qr_res ) ? (string) ( $qr_res['description'] ?? '' ) : 'no_response',
					)
				);
			}
		}
		if ( $tmp && file_exists( $tmp ) ) {
			@unlink( $tmp );
		}
		return $ok;
	}

	/**
	 * One Telegram HTML message: dashboard link + all config URIs stacked.
	 *
	 * @param string               $dashboard_url Dashboard link.
	 * @param array<int, string>   $send_uris     Config URIs keyed by original index.
	 * @param array<int, string>   $labels        Display labels keyed like $send_uris.
	 * @param string               $intro_html    Optional leading block (e.g. payment confirmed).
	 * @return string
	 */
	private static function build_combined_config_message_html( $dashboard_url, array $send_uris, array $labels, $intro_html = '' ) {
		$parts = array();
		$intro = trim( (string) $intro_html );
		if ( '' !== $intro ) {
			$parts[] = $intro;
		}
		if ( '' !== trim( (string) $dashboard_url ) ) {
			$parts[] = self::build_subscription_link_message_html( (string) $dashboard_url );
		}
		$n_uri = count( $send_uris );
		$pos   = 0;
		foreach ( $send_uris as $i => $u ) {
			$frag  = isset( $labels[ $i ] ) ? trim( (string) $labels[ $i ] ) : '';
			$parts[] = self::build_single_config_message_html( (string) $u, $frag, $pos, $n_uri );
			++$pos;
		}
		return implode( "\n\n", $parts );
	}

	/**
	 * Plain-text variant for sendDocument fallback.
	 *
	 * @param string               $dashboard_url Dashboard link.
	 * @param array<int, string>   $send_uris     Config URIs.
	 * @param array<int, string>   $labels        Labels.
	 * @return string
	 */
	private static function build_combined_config_plain_text( $dashboard_url, array $send_uris, array $labels, $intro_plain = '' ) {
		$parts = array();
		$intro = trim( (string) $intro_plain );
		if ( '' !== $intro ) {
			$parts[] = $intro;
		}
		if ( '' !== trim( (string) $dashboard_url ) ) {
			$title = SimpleVPBot_Texts::get( 'msg.svc.subscription_link_title', '🔗 لینک اشتراک' );
			$parts[] = $title . "\n" . (string) $dashboard_url;
		}
		$n_uri = count( $send_uris );
		$pos   = 0;
		foreach ( $send_uris as $i => $u ) {
			$frag = isset( $labels[ $i ] ) ? trim( (string) $labels[ $i ] ) : '';
			if ( '' === $frag && class_exists( 'SimpleVPBot_Config_Link' ) ) {
				$frag = SimpleVPBot_Config_Link::uri_fragment_label( (string) $u );
			}
			if ( '' === $frag ) {
				$frag = $n_uri > 1 ? ( 'کانفیگ ' . ( $pos + 1 ) ) : 'کانفیگ';
			}
			$parts[] = $frag . "\n" . (string) $u;
			++$pos;
		}
		return implode( "\n\n", $parts );
	}

	/**
	 * @param string $html HTML body.
	 * @return bool
	 */
	private static function telegram_html_exceeds_limit( $html ) {
		$html = (string) $html;
		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $html, 'UTF-8' ) > 4096;
		}
		return strlen( $html ) > 4096;
	}

	/**
	 * Telegram: QR photo (dashboard link caption) + one combined config message.
	 *
	 * @param int                  $chat_id    Chat.
	 * @param array<string, mixed> $data       Data.
	 * @param string               $portal     HMAC link.
	 * @param int $svp_user_id svp_users.id.
	 * @param int $service_id  svp_services.id.
	 * @return string
	 */
	private static function config_sent_transient_key( $svp_user_id, $service_id ) {
		return 'svp_cfg_sent_' . (int) $svp_user_id . '_' . (int) $service_id;
	}

	/**
	 * @param int $svp_user_id svp_users.id.
	 * @param int $service_id  svp_services.id.
	 * @return string
	 */
	private static function config_delivery_intro_transient_key( $svp_user_id, $service_id ) {
		return 'svp_cfg_intro_' . (int) $svp_user_id . '_' . (int) $service_id;
	}

	/**
	 * Queue intro HTML for the next auto config delivery message.
	 *
	 * @param int    $svp_user_id svp_users.id.
	 * @param int    $service_id  svp_services.id.
	 * @param string $intro_html  HTML block (escaped lines).
	 */
	public static function set_config_delivery_intro( $svp_user_id, $service_id, $intro_html ) {
		$uid = (int) $svp_user_id;
		$sid = (int) $service_id;
		if ( $uid < 1 || $sid < 1 || '' === trim( (string) $intro_html ) ) {
			return;
		}
		set_transient( self::config_delivery_intro_transient_key( $uid, $sid ), (string) $intro_html, 600 );
	}

	/**
	 * Build and store purchase-confirmed intro for combined config delivery.
	 *
	 * @param object|null $svc        Service row.
	 * @param object|null $context_tx Transaction context.
	 * @return string HTML intro.
	 */
	public static function build_purchase_delivery_intro_html( $svc, $context_tx = null ) {
		$lines = array( '✅ پرداخت شما تایید شد.' );
		$summary = self::service_ready_summary_line( $svc, $context_tx );
		if ( '' !== $summary ) {
			$lines[] = $summary;
		}
		return implode( "\n", array_map( 'esc_html', $lines ) );
	}

	/**
	 * @param int $svp_user_id svp_users.id.
	 * @param int $service_id  svp_services.id.
	 * @return string
	 */
	private static function peek_config_delivery_intro( $svp_user_id, $service_id ) {
		$uid = (int) $svp_user_id;
		$sid = (int) $service_id;
		if ( $uid < 1 || $sid < 1 ) {
			return '';
		}
		$intro = get_transient( self::config_delivery_intro_transient_key( $uid, $sid ) );
		return is_string( $intro ) ? trim( $intro ) : '';
	}

	/**
	 * @param int $svp_user_id svp_users.id.
	 * @param int $service_id  svp_services.id.
	 */
	private static function clear_config_delivery_intro( $svp_user_id, $service_id ) {
		$uid = (int) $svp_user_id;
		$sid = (int) $service_id;
		if ( $uid < 1 || $sid < 1 ) {
			return;
		}
		delete_transient( self::config_delivery_intro_transient_key( $uid, $sid ) );
	}

	/**
	 * @param int $svp_user_id svp_users.id.
	 * @param int $service_id  svp_services.id.
	 * @return bool
	 */
	private static function config_already_sent( $svp_user_id, $service_id ) {
		$uid = (int) $svp_user_id;
		$sid = (int) $service_id;
		if ( $uid < 1 || $sid < 1 ) {
			return false;
		}
		return (bool) get_transient( self::config_sent_transient_key( $uid, $sid ) );
	}

	/**
	 * @param int $svp_user_id svp_users.id.
	 * @param int $service_id  svp_services.id.
	 */
	private static function mark_config_sent( $svp_user_id, $service_id ) {
		$uid = (int) $svp_user_id;
		$sid = (int) $service_id;
		if ( $uid < 1 || $sid < 1 ) {
			return;
		}
		set_transient( self::config_sent_transient_key( $uid, $sid ), '1', self::CONFIG_SENT_TTL_SEC );
	}

	/**
	 * @param mixed $res Bot API response.
	 * @return int
	 */
	private static function api_message_id_from_response( $res ) {
		if ( is_array( $res ) && ! empty( $res['result']['message_id'] ) ) {
			return (int) $res['result']['message_id'];
		}
		return 0;
	}

	/**
	 * Show a short-lived preparing line; return message id for later edit.
	 *
	 * @param string $platform  telegram|bale.
	 * @param int    $chat_id   Chat id.
	 * @param int    $msg_id    Callback message id (0 = send new).
	 * @param object $user      User row.
	 * @param int    $svc_id    Service id.
	 * @param int    $owner_uid Owner user id.
	 * @return int Message id to edit when delivery completes.
	 */
	private static function resolve_preparing_panel_message( $platform, $chat_id, $msg_id, $user, $svc_id, $owner_uid ) {
		$svc_id    = (int) $svc_id;
		$chat_id   = (int) $chat_id;
		$msg_id    = (int) $msg_id;
		$owner_uid = (int) $owner_uid;
		$portal    = SimpleVPBot_Portal_Link::build_service_url( $owner_uid, $svc_id );
		$text      = SimpleVPBot_Texts::get_for_user( 'msg.svc.preparing_panel', $user, '⏳ در حال آماده‌سازی سرویس…' );
		if ( 'bale' === $platform ) {
			$markup = SimpleVPBot_Keyboards::inline_bale_portal_back( $svc_id, $portal, $user );
		} else {
			$markup = SimpleVPBot_Keyboards::inline_subscription_back_only( $svc_id, $user );
		}
		$extra = array( 'reply_markup' => $markup );
		if ( $msg_id > 0 ) {
			SimpleVPBot_Bot_Runtime::edit_message_text( $platform, $chat_id, $msg_id, $text, $extra );
			return $msg_id;
		}
		$res = SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $text, $extra );
		return self::api_message_id_from_response( $res );
	}

	/**
	 * Edit panel message or send error when deferred delivery fails.
	 *
	 * @param string $platform     telegram|bale.
	 * @param int    $chat_id      Chat id.
	 * @param int    $panel_msg_id Message to edit (0 = send new).
	 * @param object $user         User row.
	 * @param string $text_key     Text key or literal fallback body.
	 * @param string $fallback     Fallback Persian text.
	 */
	private static function notify_panel_delivery_failed( $platform, $chat_id, $panel_msg_id, $user, $text_key, $fallback ) {
		$chat_id      = (int) $chat_id;
		$panel_msg_id = (int) $panel_msg_id;
		$text         = SimpleVPBot_Texts::get_for_user( $text_key, $user, $fallback );
		if ( class_exists( 'SimpleVPBot_Support_Contacts' ) ) {
			$text = SimpleVPBot_Support_Contacts::append_to_message( $text, $platform );
		}
		if ( $panel_msg_id > 0 ) {
			SimpleVPBot_Bot_Runtime::edit_message_text( $platform, $chat_id, $panel_msg_id, $text, array() );
			return;
		}
		SimpleVPBot_Bot_Runtime::send_message_with_support( $platform, $chat_id, $text );
	}

	/**
	 * Send Telegram config once per user/service window (dedupe).
	 *
	 * @param int                  $chat_id     Telegram chat id.
	 * @param array<string, mixed> $data        Portal data.
	 * @param string               $portal      Portal URL.
	 * @param int                  $service_id  Service id.
	 * @param int                  $svp_user_id svp_users.id for dedupe.
	 * @return bool True when config is present or was already sent.
	 */
	private static function maybe_telegram_send_config_unified( $chat_id, array $data, $portal, $service_id, $svp_user_id ) {
		$uid = (int) $svp_user_id;
		$sid = (int) $service_id;
		if ( $uid > 0 && $sid > 0 && self::config_already_sent( $uid, $sid ) ) {
			return true;
		}
		return self::telegram_send_config_unified( $chat_id, $data, $portal, $sid, $uid );
	}

	/**
	 * @param int                  $chat_id     Telegram chat.
	 * @param array<string, mixed> $data        Data.
	 * @param string               $portal      HMAC link.
	 * @param int                  $service_id  Service id.
	 * @param int                  $svp_user_id svp_users.id (0 = skip dedupe mark).
	 * @return bool True when at least one config line was sent or dedupe hit.
	 */
	private static function portal_data_has_sendable_config( array $data, $portal_fallback = '' ) {
		if ( '' === self::resolve_user_dashboard_url( $data, (string) $portal_fallback ) ) {
			return false;
		}
		$uris = isset( $data['config_uris'] ) && is_array( $data['config_uris'] ) ? $data['config_uris'] : array();
		foreach ( $uris as $u ) {
			if ( '' !== trim( (string) $u ) ) {
				return true;
			}
		}
		return false;
	}

	private static function telegram_send_config_unified( $chat_id, array $data, $portal, $service_id = 0, $svp_user_id = 0 ) {
		$dashboard = self::resolve_user_dashboard_url( $data, (string) $portal );
		$uris      = isset( $data['config_uris'] ) && is_array( $data['config_uris'] ) ? $data['config_uris'] : array();
		$labels    = isset( $data['config_labels'] ) && is_array( $data['config_labels'] ) ? $data['config_labels'] : array();
		if ( ! self::portal_data_has_sendable_config( $data, (string) $portal ) ) {
			return false;
		}

		$send_uris = array();
		foreach ( $uris as $i => $u ) {
			if ( count( $send_uris ) >= 20 ) {
				break;
			}
			$u = trim( (string) $u );
			if ( '' === $u || $u === $dashboard ) {
				continue;
			}
			$send_uris[ (int) $i ] = $u;
		}
		if ( empty( $send_uris ) || '' === $dashboard ) {
			return false;
		}

		$intro_html  = self::peek_config_delivery_intro( (int) $svp_user_id, (int) $service_id );
		$intro_plain = '' !== $intro_html ? wp_strip_all_tags( $intro_html ) : '';

		$qr_sent = self::send_config_qr_photo( (int) $chat_id, $dashboard, (int) $service_id );

		$markup   = self::build_config_copy_keyboard_rows( $data, (string) $portal, (int) $service_id );
		$inline   = array( 'inline_keyboard' => $markup );
		$combined = self::build_combined_config_message_html( $dashboard, $send_uris, $labels, $intro_html );
		$extra    = array(
			'parse_mode'    => 'HTML',
			'reply_markup'  => $inline,
		);

		$msg_sent = false;
		if ( self::telegram_html_exceeds_limit( $combined ) ) {
			$plain = self::build_combined_config_plain_text( $dashboard, $send_uris, $labels, $intro_plain );
			$doc   = function_exists( 'wp_tempnam' ) ? wp_tempnam( 'svp_cfg' ) : @tempnam( sys_get_temp_dir(), 'svp_cfg' );
			if ( $doc && false !== file_put_contents( $doc, $plain ) ) {
				$doc_cap = SimpleVPBot_Texts::get( 'msg.svc.subscription_link_title', '🔗 لینک اشتراک' );
				$doc_res = SimpleVPBot_Bot_Runtime::send_document_file( 'telegram', (int) $chat_id, $doc, $doc_cap, $extra );
				$msg_sent = is_array( $doc_res ) && ! empty( $doc_res['ok'] );
				if ( $doc && file_exists( $doc ) ) {
					@unlink( $doc );
				}
			}
		} else {
			$res = SimpleVPBot_Bot_Runtime::send_message( 'telegram', (int) $chat_id, $combined, $extra );
			$msg_sent = is_array( $res ) && ! empty( $res['ok'] );
		}

		if ( ! $msg_sent || ! $qr_sent ) {
			if ( class_exists( 'SimpleVPBot_Logger' ) ) {
				SimpleVPBot_Logger::error(
					'telegram_send_config_unified failed',
					array(
						'chat_id'    => (int) $chat_id,
						'service_id' => (int) $service_id,
						'msg_sent'   => $msg_sent ? 1 : 0,
						'qr_sent'    => $qr_sent ? 1 : 0,
					)
				);
			}
			return false;
		}
		$uid = (int) $svp_user_id;
		$sid = (int) $service_id;
		if ( $uid > 0 && $sid > 0 ) {
			self::clear_config_delivery_intro( $uid, $sid );
			self::mark_config_sent( $uid, $sid );
		}
		return true;
	}

	/**
	 * Callback svc:w:{service_id}:{index}: send one config line (HTML monospace on Telegram).
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
		$cb_id    = isset( $ctx['cb_id'] ) ? (string) $ctx['cb_id'] : '';
		$svc      = SimpleVPBot_Model_Service::find( $sid );
		if ( ! self::service_caller_can_manage( $platform, $from_id, $user, $svc ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.invalid_access', $user ) );
			return;
		}
		self::answer_svc_processing_toast( $platform, $cb_id );
		$user_id = is_object( $user ) ? (int) $user->id : 0;
		$work    = static function () use ( $platform, $user_id, $sid, $idx, $chat_id ) {
			$user_row = SimpleVPBot_Model_User::find( (int) $user_id );
			if ( ! $user_row ) {
				return;
			}
			self::run_config_wire_delivery( $platform, $user_row, $sid, $idx, $chat_id );
		};
		if ( class_exists( 'SimpleVPBot_Deferred_Work' ) ) {
			SimpleVPBot_Deferred_Work::run_after_response( $work, 'svc_config_wire' );
		} else {
			$work();
		}
	}

	/**
	 * Send one config URI line after deferred fetch.
	 *
	 * @param string               $platform telegram|bale.
	 * @param object               $user     User row.
	 * @param int                  $sid      Service id.
	 * @param int                  $idx      Config index.
	 * @param int                  $chat_id  Chat id.
	 */
	private static function run_config_wire_delivery( $platform, $user, $sid, $idx, $chat_id ) {
		try {
			$svc = SimpleVPBot_Model_Service::find( (int) $sid );
			if ( ! $svc ) {
				SimpleVPBot_Bot_Runtime::send_message_with_support( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.not_found', $user ) );
				return;
			}
			$data   = self::get_portal_service_data( $svc, (int) $svc->user_id );
			$uris   = isset( $data['config_uris'] ) && is_array( $data['config_uris'] ) ? $data['config_uris'] : array();
			$labels = isset( $data['config_labels'] ) && is_array( $data['config_labels'] ) ? $data['config_labels'] : array();
			if ( ! isset( $uris[ $idx ] ) ) {
				SimpleVPBot_Bot_Runtime::send_message_with_support( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.config_unavailable', $user ) );
				return;
			}
			$line = (string) $uris[ $idx ];
			$frag = isset( $labels[ $idx ] ) ? trim( (string) $labels[ $idx ] ) : '';
			if ( 'telegram' === $platform ) {
				$text  = self::build_single_config_message_html( $line, $frag, $idx, count( $uris ) );
				$extra = array( 'parse_mode' => 'HTML' );
			} else {
				$n_uri  = count( $uris );
				$prefix = $n_uri > 1 ? ( '🧾 کانفیگ ' . ( $idx + 1 ) ) : '🧾 کانفیگ';
				$text   = $prefix . SimpleVPBot_Service_Alerts::text_sep() . $line;
				$extra  = array();
			}
			$res = SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $text, $extra );
			if ( ! is_array( $res ) || empty( $res['ok'] ) ) {
				SimpleVPBot_Bot_Runtime::send_message_with_support(
					$platform,
					$chat_id,
					SimpleVPBot_Texts::get_for_user( 'msg.svc.telegram_config_send_fail', $user )
				);
			}
		} catch ( Throwable $e ) { // phpcs:ignore
			if ( class_exists( 'SimpleVPBot_Logger' ) ) {
				SimpleVPBot_Logger::error( 'svc_config_wire failed', array( 'service_id' => (int) $sid, 'm' => $e->getMessage() ) );
			}
			SimpleVPBot_Bot_Runtime::send_message_with_support(
				$platform,
				$chat_id,
				SimpleVPBot_Texts::get_for_user( 'msg.svc.telegram_config_send_fail', $user )
			);
		}
	}

	/**
	 * Toast for deferred service callbacks.
	 *
	 * @param string $platform Platform.
	 * @param string $cb_id    Callback query id.
	 */
	private static function answer_svc_processing_toast( $platform, $cb_id ) {
		if ( '' === (string) $cb_id ) {
			return;
		}
		SimpleVPBot_Bot_Runtime::answer_callback_query(
			$platform,
			array(
				'callback_query_id' => (string) $cb_id,
				'text'              => '⏳ در حال بارگذاری…',
			)
		);
	}

	/**
	 * DB-only portal data (no XUI / subscription fetch).
	 *
	 * @param object $svc     Service.
	 * @param int    $user_id svp user id.
	 * @return array<string, mixed>
	 */
	public static function get_portal_service_data_fast( $svc, $user_id = 0 ) {
		if ( SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
			return self::get_portal_l2tp_data( $svc, $user_id );
		}
		$v      = self::collect_usage_stats_fallback_db( $svc, 'fast' );
		$uid    = (int) $user_id > 0 ? (int) $user_id : (int) ( $svc->user_id ?? 0 );
		$import = SimpleVPBot_Config_Link::subscription_url( (string) $svc->sub_id, self::svc_panel_id_xui( $svc ) );
		$portal = ( $uid > 0 && (int) $svc->id > 0 ) ? SimpleVPBot_Portal_Link::build_service_url( $uid, (int) $svc->id ) : '';
		$unified = '' !== $portal ? (string) $portal : (string) $import;
		return array_merge(
			$v,
			array(
				'panel_sub_url'    => (string) $import,
				'import_sub_url'   => $unified,
				'subscription_url' => $unified,
				'portal_url'       => (string) $portal,
				'user_portal_url'  => (string) $portal,
				'config_uris'      => array(),
				'config_labels'    => array(),
				'config_uri'       => '',
				'primary_link'     => $unified,
			)
		);
	}

	/**
	 * One-line DB summary for post-provision notify (plan + expiry).
	 *
	 * @param object|null          $svc         Service row.
	 * @param object|null          $context_tx  Purchase transaction.
	 * @return string
	 */
	public static function service_ready_summary_line( $svc, $context_tx = null ) {
		if ( ! is_object( $svc ) ) {
			return '';
		}
		$parts = array();
		if ( is_object( $context_tx ) ) {
			$meta = json_decode( (string) ( $context_tx->meta_json ?? '' ), true );
			if ( is_array( $meta ) && ! empty( $meta['plan_id'] ) ) {
				$plan = SimpleVPBot_Model_Plan::find( (int) $meta['plan_id'] );
				if ( $plan ) {
					$name = trim( (string) ( $plan->name ?? $plan->label ?? '' ) );
					if ( '' !== $name ) {
						$parts[] = '📦 ' . $name;
					}
				}
			}
		}
		if ( ! empty( $svc->expires_at ) ) {
			$parts[] = '📅 انقضا: ' . self::format_datetime_fa( (string) $svc->expires_at );
		}
		return implode( "\n", $parts );
	}

	/**
	 * Push Telegram config after provision (no user click).
	 *
	 * @param object               $user        User row.
	 * @param int                  $service_id  Service id.
	 * @param object|null          $context_tx  Purchase transaction.
	 */
	public static function enqueue_config_delivery_for_user( $user, $service_id, $context_tx = null ) {
		unset( $context_tx );
		if ( ! is_object( $user ) ) {
			return;
		}
		$chat_id = self::resolve_telegram_chat_id( $user );
		if ( $chat_id < 1 ) {
			if ( class_exists( 'SimpleVPBot_Logger' ) ) {
				SimpleVPBot_Logger::info(
					'config_delivery_skipped_no_telegram',
					array( 'user_id' => (int) $user->id, 'service_id' => (int) $service_id )
				);
			}
			return;
		}
		$svc_id = (int) $service_id;
		$uid    = (int) $user->id;
		$work   = static function () use ( $chat_id, $svc_id, $uid ) {
			self::run_svc_config_delivery( $chat_id, $svc_id, $uid, 0 );
		};
		if ( class_exists( 'SimpleVPBot_Deferred_Work' ) ) {
			SimpleVPBot_Deferred_Work::run_after_response_or_cron(
				$work,
				SimpleVPBot_Deferred_Work::SVC_CONFIG_DELIVERY_CRON_HOOK,
				array( (int) $chat_id, (int) $svc_id, (int) $uid, 0 ),
				'svc_config_delivery'
			);
		} else {
			$work();
		}
	}

	/**
	 * Cron fallback: push Telegram config after provision.
	 *
	 * @param int $chat_id Telegram chat id.
	 * @param int $svc_id  Service id.
	 * @param int $uid     svp_users.id.
	 */
	public static function deferred_svc_config_delivery_cron( $chat_id, $svc_id, $uid, $attempt = 0 ) {
		self::run_svc_config_delivery( (int) $chat_id, (int) $svc_id, (int) $uid, (int) $attempt );
	}

	/**
	 * Backoff delays (seconds) for config delivery retries after attempt 0.
	 *
	 * @return array<int, int>
	 */
	private static function config_delivery_retry_delays() {
		return array( 5, 10, 20, 30, 60, 120 );
	}

	/**
	 * @param int $chat_id Telegram chat id.
	 * @param int $svc_id  Service id.
	 * @param int $uid     svp_users.id.
	 * @param int $attempt Retry attempt (0 = first run).
	 */
	private static function run_svc_config_delivery( $chat_id, $svc_id, $uid, $attempt = 0 ) {
		$chat_id   = (int) $chat_id;
		$svc_id    = (int) $svc_id;
		$uid       = (int) $uid;
		$attempt   = max( 0, (int) $attempt );
		$cron_args = array( $chat_id, $svc_id, $uid, $attempt );
		if ( $uid > 0 && $svc_id > 0 && self::config_already_sent( $uid, $svc_id ) ) {
			if ( class_exists( 'SimpleVPBot_Deferred_Work' ) ) {
				wp_clear_scheduled_hook( SimpleVPBot_Deferred_Work::SVC_CONFIG_DELIVERY_CRON_HOOK );
			}
			return;
		}
		$svc = SimpleVPBot_Model_Service::find( $svc_id );
		if ( ! $svc || SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
			return;
		}
		$portal = SimpleVPBot_Portal_Link::build_service_url( $uid, $svc_id );
		$data   = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$data = self::get_portal_service_data_for_delivery( $svc, $uid );
			if ( self::portal_data_has_sendable_config( $data, $portal ) ) {
				break;
			}
			if ( $i < 2 ) {
				usleep( 500000 );
			}
		}
		$reason = '';
		if ( ! self::portal_data_has_sendable_config( $data, $portal ) ) {
			$reason = 'no_sendable_config';
		} else {
			$ok = self::maybe_telegram_send_config_unified( $chat_id, $data, $portal, $svc_id, $uid );
			if ( $ok ) {
				if ( class_exists( 'SimpleVPBot_Deferred_Work' ) ) {
					wp_clear_scheduled_hook( SimpleVPBot_Deferred_Work::SVC_CONFIG_DELIVERY_CRON_HOOK );
				}
				return;
			}
			$reason = 'send_failed';
		}
		$delays    = self::config_delivery_retry_delays();
		$max_retry = count( $delays );
		if ( $attempt < $max_retry && class_exists( 'SimpleVPBot_Deferred_Work' ) ) {
			$next_attempt = $attempt + 1;
			$delay        = (int) ( $delays[ $attempt ] ?? 300 );
			if ( class_exists( 'SimpleVPBot_Logger' ) ) {
				SimpleVPBot_Logger::info(
					'config_delivery_retry_scheduled',
					array(
						'chat_id'      => $chat_id,
						'service_id'   => $svc_id,
						'user_id'      => $uid,
						'attempt'      => $next_attempt,
						'delay_sec'    => $delay,
						'fail_reason'  => $reason,
					)
				);
			}
			SimpleVPBot_Deferred_Work::schedule_cron_retry(
				SimpleVPBot_Deferred_Work::SVC_CONFIG_DELIVERY_CRON_HOOK,
				array( $chat_id, $svc_id, $uid, $next_attempt ),
				$delay
			);
			return;
		}
		if ( class_exists( 'SimpleVPBot_Logger' ) ) {
			SimpleVPBot_Logger::error(
				'config_delivery_exhausted_retries',
				array(
					'chat_id'     => $chat_id,
					'service_id'  => $svc_id,
					'user_id'     => $uid,
					'attempt'     => $attempt,
					'fail_reason' => $reason,
				)
			);
		}
	}

	/**
	 * Fetch live panel + subscription after fast ack.
	 *
	 * @param string               $platform      telegram|bale.
	 * @param int                  $chat_id       Chat id.
	 * @param int                  $panel_msg_id  Message to edit when complete.
	 * @param object               $svc           Service row.
	 * @param int                  $owner_uid     Owner user id.
	 * @param object               $user          Acting user.
	 * @param string               $action        p|l|q.
	 */
	private static function schedule_svc_panel_full_delivery( $platform, $chat_id, $panel_msg_id, $svc, $owner_uid, $user, $action ) {
		$svc_id    = (int) $svc->id;
		$user_id   = is_object( $user ) ? (int) $user->id : 0;
		$panel_mid = (int) $panel_msg_id;
		$work      = static function () use ( $platform, $chat_id, $panel_mid, $svc_id, $owner_uid, $user_id, $action ) {
			self::run_svc_panel_full_delivery( $platform, $chat_id, $panel_mid, $svc_id, $owner_uid, $user_id, $action );
		};
		if ( class_exists( 'SimpleVPBot_Deferred_Work' ) ) {
			SimpleVPBot_Deferred_Work::run_after_response_or_cron(
				$work,
				SimpleVPBot_Deferred_Work::SVC_PANEL_DELIVERY_CRON_HOOK,
				array( (string) $platform, (int) $chat_id, $panel_mid, (int) $svc_id, (int) $owner_uid, (int) $user_id, (string) $action ),
				'svc_panel_delivery'
			);
		} else {
			$work();
		}
	}

	/**
	 * Cron fallback for deferred service panel/config delivery.
	 *
	 * @param string $platform     telegram|bale.
	 * @param int    $chat_id      Chat id.
	 * @param int    $panel_msg_id Message id.
	 * @param int    $svc_id       Service id.
	 * @param int    $owner_uid    Owner user id.
	 * @param int    $user_id      Acting svp_users.id.
	 * @param string $action       p|l|q.
	 */
	public static function deferred_svc_panel_delivery_cron( $platform, $chat_id, $panel_msg_id, $svc_id, $owner_uid, $user_id, $action ) {
		self::run_svc_panel_full_delivery(
			(string) $platform,
			(int) $chat_id,
			(int) $panel_msg_id,
			(int) $svc_id,
			(int) $owner_uid,
			(int) $user_id,
			(string) $action
		);
	}

	/**
	 * Deliver full service panel / config after fast ack.
	 *
	 * @param string $platform     telegram|bale.
	 * @param int    $chat_id      Chat id.
	 * @param int    $panel_msg_id Message id.
	 * @param int    $svc_id       Service id.
	 * @param int    $owner_uid    Owner user id.
	 * @param int    $user_id      Acting svp_users.id.
	 * @param string $action       p|l|q.
	 */
	private static function run_svc_panel_full_delivery( $platform, $chat_id, $panel_msg_id, $svc_id, $owner_uid, $user_id, $action ) {
		$user = SimpleVPBot_Model_User::find( (int) $user_id );
		if ( ! $user ) {
			return;
		}
		try {
			$svc = SimpleVPBot_Model_Service::find( (int) $svc_id );
			if ( ! $svc ) {
				self::notify_panel_delivery_failed( $platform, $chat_id, $panel_msg_id, $user, 'msg.svc.not_found', '⛔ سرویس یافت نشد.' );
				return;
			}
			$portal = SimpleVPBot_Portal_Link::build_service_url( (int) $owner_uid, (int) $svc_id );
			$data   = self::get_portal_service_data( $svc, (int) $owner_uid );
			if ( ! empty( $data['_deleted'] ) ) {
				self::notify_panel_delivery_failed( $platform, $chat_id, $panel_msg_id, $user, 'msg.svc.deleted_from_panel', '⛔ سرویس از پنل حذف شده است.' );
				return;
			}
			if ( 'p' === $action ) {
				$text = self::build_usage_panel_text( $svc, $platform );
				if ( 'bale' === $platform ) {
					$markup = SimpleVPBot_Keyboards::inline_bale_portal_back( (int) $svc_id, $portal, $user );
				} else {
					$markup = SimpleVPBot_Keyboards::inline_telegram_config_extras( (int) $svc_id, $data, $portal, $user );
				}
				if ( $panel_msg_id > 0 ) {
					$edit = SimpleVPBot_Bot_Runtime::edit_message_text(
						$platform,
						$chat_id,
						$panel_msg_id,
						$text,
						array( 'reply_markup' => $markup )
					);
					if ( ! is_array( $edit ) || empty( $edit['ok'] ) ) {
						$res = SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $text, array( 'reply_markup' => $markup ) );
						$panel_msg_id = self::api_message_id_from_response( $res );
					}
				} else {
					$res = SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $text, array( 'reply_markup' => $markup ) );
					$panel_msg_id = self::api_message_id_from_response( $res );
				}
				if ( 'telegram' === $platform ) {
					self::maybe_telegram_send_config_unified( $chat_id, $data, $portal, (int) $svc_id, (int) $owner_uid );
				}
				return;
			}
			if ( 'l' === $action || 'q' === $action ) {
				if ( 'bale' === $platform ) {
					return;
				}
				$import   = (string) ( $data['import_sub_url'] ?? $data['subscription_url'] ?? $data['primary_link'] ?? '' );
				$primary  = (string) ( $data['primary_link'] ?? $import );
				$port_btn = (string) ( $data['portal_url'] ?? $portal );
				if ( '' === $primary && empty( $data['config_uris'] ) && '' === $port_btn ) {
					SimpleVPBot_Bot_Runtime::send_message_with_support(
						$platform,
						$chat_id,
						SimpleVPBot_Texts::get_for_user( 'msg.svc.link_not_found', $user )
					);
					return;
				}
				self::maybe_telegram_send_config_unified( $chat_id, $data, $portal, (int) $svc_id, (int) $owner_uid );
			}
		} catch ( Throwable $e ) { // phpcs:ignore
			if ( class_exists( 'SimpleVPBot_Logger' ) ) {
				SimpleVPBot_Logger::error(
					'svc_panel_full_delivery failed',
					array(
						'service_id' => (int) $svc_id,
						'action'     => (string) $action,
						'm'          => $e->getMessage(),
					)
				);
			}
			self::notify_panel_delivery_failed(
				$platform,
				$chat_id,
				$panel_msg_id,
				$user,
				'msg.svc.telegram_config_send_fail',
				'⛔ بارگذاری سرویس ناموفق بود. لطفاً دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.'
			);
		}
	}

	/**
	 * Lightweight portal payload for Telegram config delivery (no live usage stats).
	 *
	 * @param object $svc     Service.
	 * @param int    $user_id svp user id.
	 * @return array<string, mixed>
	 */
	public static function get_portal_service_data_for_delivery( $svc, $user_id = 0 ) {
		if ( SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
			return self::get_portal_l2tp_data( $svc, $user_id );
		}
		return array_merge( self::usage_identity_fields( $svc ), self::portal_subscription_fields( $svc, $user_id ) );
	}

	/**
	 * Prefetch panel subscription lines into cache after provision.
	 *
	 * @param int $service_id svp_services.id.
	 */
	public static function warm_subscription_cache_for_service( $service_id ) {
		$svc = SimpleVPBot_Model_Service::find( (int) $service_id );
		if ( ! $svc || SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
			return;
		}
		$import = SimpleVPBot_Config_Link::subscription_url( (string) $svc->sub_id, self::svc_panel_id_xui( $svc ) );
		if ( '' === $import ) {
			return;
		}
		SimpleVPBot_Config_Link::fetch_subscription( $import, (int) ( $svc->panel_id ?? 0 ) );
	}

	/**
	 * Subscription URIs, labels, and portal links (shared by full and delivery payloads).
	 *
	 * @param object $svc     Service.
	 * @param int    $user_id svp user id.
	 * @return array<string, mixed>
	 */
	private static function portal_subscription_fields( $svc, $user_id = 0 ) {
		$uid    = (int) $user_id > 0 ? (int) $user_id : (int) ( $svc->user_id ?? 0 );
		$import = SimpleVPBot_Config_Link::subscription_url( (string) $svc->sub_id, self::svc_panel_id_xui( $svc ) );
		$portal = ( $uid > 0 && (int) $svc->id > 0 ) ? SimpleVPBot_Portal_Link::build_service_url( $uid, (int) $svc->id ) : '';
		$uris   = $import
			? SimpleVPBot_Config_Link::fetch_subscription( $import, (int) ( $svc->panel_id ?? 0 ) )
			: array();
		if ( $uid > 0 && ! empty( $uris ) && class_exists( 'SimpleVPBot_Reseller_Branding' ) ) {
			$uris = SimpleVPBot_Reseller_Branding::rewrite_subscription_uris_for_user(
				$uris,
				$uid,
				(string) ( $svc->remark ?? '' ),
				$svc
			);
		}
		$identity = self::usage_identity_fields( $svc );
		$sub_view = class_exists( 'SimpleVPBot_Service_Naming' )
			? SimpleVPBot_Service_Naming::enrich_subscription_view( $svc, $uris )
			: array(
				'subscription_id'   => '',
				'subscription_name' => (string) ( $identity['remark'] ?? '' ),
				'config_uris'       => $uris,
				'config_labels'     => array(),
				'remark'            => (string) ( $identity['remark'] ?? '' ),
				'sub_id'            => (string) ( $svc->sub_id ?? '' ),
			);
		$uris   = isset( $sub_view['config_uris'] ) && is_array( $sub_view['config_uris'] ) ? $sub_view['config_uris'] : $uris;
		$labels = isset( $sub_view['config_labels'] ) && is_array( $sub_view['config_labels'] ) ? $sub_view['config_labels'] : array();
		if ( ! empty( $uris ) && class_exists( 'SimpleVPBot_Config_Link' ) ) {
			foreach ( $uris as $i => $uri_line ) {
				$label = isset( $labels[ $i ] ) ? trim( (string) $labels[ $i ] ) : '';
				if ( '' === $label ) {
					continue;
				}
				$current = trim( (string) SimpleVPBot_Config_Link::uri_fragment_label( (string) $uri_line ) );
				if ( '' !== $current && $label === $current ) {
					continue;
				}
				$uris[ $i ] = SimpleVPBot_Config_Link::replace_uri_fragment( (string) $uri_line, $label );
			}
		}
		$primary = '';
		if ( ! empty( $uris[0] ) ) {
			$primary = (string) $uris[0];
		}
		if ( '' === $primary ) {
			$primary = (string) ( '' !== $portal ? $portal : $import );
		}
		$unified = '' !== $portal ? (string) $portal : (string) $import;
		return array_merge(
			$sub_view,
			array(
				'panel_sub_url'    => (string) $import,
				'import_sub_url'   => $unified,
				'subscription_url' => $unified,
				'portal_url'       => (string) $portal,
				'user_portal_url'  => (string) $portal,
				'config_uris'      => $uris,
				'config_uri'       => ! empty( $uris ) ? (string) $uris[0] : '',
				'primary_link'     => (string) $primary,
			)
		);
	}

	public static function get_portal_service_data( $svc, $user_id = 0 ) {
		if ( SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
			return self::get_portal_l2tp_data( $svc, $user_id );
		}
		$v = self::collect_usage_stats( $svc );
		if ( ! empty( $v['deleted'] ) ) {
			return array( '_deleted' => 1 );
		}
		$import = SimpleVPBot_Config_Link::subscription_url( (string) $svc->sub_id, self::svc_panel_id_xui( $svc ) );
		if ( ! empty( $v['panel_unreachable'] ) && '' !== $import ) {
			delete_transient( 'svp_sub_' . md5( $import ) );
		}
		return array_merge( $v, self::portal_subscription_fields( $svc, $user_id ) );
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
			'panel_sub_url'    => '',
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
			SimpleVPBot_Bot_Runtime::send_message_with_support( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.svc.connection_info_missing', $user ) );
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
