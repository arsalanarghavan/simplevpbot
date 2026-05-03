<?php
/**
 * Shared admin operations (WP AJAX + bot). No capability check here — caller must authorize.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Service_Admin_Ops
 */
class SimpleVPBot_Service_Admin_Ops {

	/**
	 * Test 3x-ui panel connection.
	 *
	 * @param int $panel_id 0 = legacy keys in simplevpbot_settings; >=1 = row in svp_panels.
	 * @return array{ok:bool, data?:array<string,mixed>, message?:string}
	 */
	public static function test_panel( $panel_id = 0 ) {
		$pid = max( 0, (int) $panel_id );
		if ( $pid >= 1 ) {
			if ( ! class_exists( 'SimpleVPBot_Model_Panel' ) || ! SimpleVPBot_Model_Panel::find( $pid ) ) {
				return array(
					'ok'      => false,
					'message' => __( 'پنل با این شناسه یافت نشد.', 'simplevpbot' ),
				);
			}
		}
		return SimpleVPBot_Xui_Client::run_with_panel(
			$pid,
			function () use ( $pid ) {
				$labels          = SimpleVPBot_Xui_Client::diag_binding_labels();
				$panel_url_raw   = (string) ( $labels['panel_url'] ?? '' );
				$api_base_raw    = (string) ( $labels['panel_api_base'] ?? 'panel/api' );
				$diag            = array(
					'panel_id'     => $pid,
					'panel_url'    => $panel_url_raw,
					'api_base'     => $api_base_raw,
					'login_url'    => SimpleVPBot_Xui_Client::diag_login_url(),
					'status_url'   => SimpleVPBot_Xui_Client::diag_url( 'server/status', 'api' ),
					'inbounds_url' => SimpleVPBot_Xui_Client::diag_url( 'inbounds/list', 'api' ),
				);
				if ( '' === trim( $panel_url_raw ) ) {
					return array(
						'ok'      => false,
						'message' => __( 'آدرس پنل (Panel URL) تنظیم نشده است.', 'simplevpbot' ),
						'data'    => array( 'diag' => $diag ),
					);
				}
				if ( ! SimpleVPBot_Xui_Client::login_with_retries( 6, 300000 ) ) {
					return array(
						'ok'      => false,
						'message' => __( 'ورود به پنل ناموفق بود. معمولاً یعنی یوزر/پس اشتباه است، یا webBasePath پنل در Panel URL شما نیست. در تب «لاگ‌ها» ورودی x-ui login rejected را ببینید.', 'simplevpbot' ),
						'data'    => array(
							'diag' => $diag,
							'hint' => __( 'اگر در 3x-ui مسیر پایه (webBasePath) ست کرده‌اید، آن را در انتهای Panel URL بیاورید؛ مثل https://example.com:2053/abc/', 'simplevpbot' ),
						),
					);
				}

				$r_status = SimpleVPBot_Xui_Client::request( 'server/status', 'GET', array(), false, 1, 'api' );
				$r_inb    = SimpleVPBot_Xui_Client::request( 'inbounds/list', 'GET', array(), false, 1, 'api' );

				$probes = array(
					'server_status' => array( 'url' => $r_status['url'], 'http' => (int) $r_status['code'], 'ok' => ! empty( $r_status['ok'] ), 'sample' => mb_substr( (string) $r_status['body'], 0, 300 ) ),
					'inbounds_list' => array( 'url' => $r_inb['url'], 'http' => (int) $r_inb['code'], 'ok' => ! empty( $r_inb['ok'] ), 'count' => ( is_array( $r_inb['json']['obj'] ?? null ) ? count( $r_inb['json']['obj'] ) : 0 ) ),
				);

				$suggested_base = null;
				if ( empty( $probes['inbounds_list']['ok'] ) ) {
					$current = trim( (string) $diag['api_base'], " \t\n\r\0\x0B/" );
					$candidates = array_filter(
						array_unique( array( 'panel/api', 'xui/api', 'api', 'panel' ) ),
						function ( $c ) use ( $current ) {
							return $c !== $current;
						}
					);
					foreach ( $candidates as $cand ) {
						$url = trailingslashit( SimpleVPBot_Xui_Client::panel_root() ) . $cand . '/inbounds/list';
						$res = wp_remote_get(
							$url,
							array(
								'timeout' => 20,
								'headers' => array( 'Cookie' => SimpleVPBot_Xui_Client::cookie_header() ),
							)
						);
						if ( is_wp_error( $res ) ) {
							continue;
						}
						$code = (int) wp_remote_retrieve_response_code( $res );
						$body = (string) wp_remote_retrieve_body( $res );
						$json = json_decode( $body, true );
						$probes[ 'try_' . str_replace( '/', '_', $cand ) ] = array( 'url' => $url, 'http' => $code, 'json_ok' => is_array( $json ) && ! empty( $json['success'] ) );
						if ( is_array( $json ) && ! empty( $json['success'] ) ) {
							$suggested_base = $cand;
							break;
						}
					}
				}

				if ( empty( $probes['server_status']['ok'] ) && empty( $probes['inbounds_list']['ok'] ) ) {
					$msg = __( 'ورود موفق بود ولی هیچ endpoint آزمایشی پاسخ معتبر نداد.', 'simplevpbot' );
					if ( $suggested_base ) {
						$msg .= ' ' . sprintf( /* translators: %s = path */ __( 'پیشنهاد: مقدار «API base path» را به %s تغییر دهید.', 'simplevpbot' ), $suggested_base );
					}
					return array(
						'ok'      => false,
						'message' => $msg,
						'data'    => array(
							'diag'             => $diag,
							'probes'           => $probes,
							'suggested_base'   => $suggested_base,
							'hint'             => __( 'در 3x-ui اصلی (و Postman رسمی)، همهٔ API شامل server/status زیر همان پایهٔ API است؛ مثلاً /panel/api/server/status و /panel/api/inbounds/list. webBasePath فقط قبل از /panel/api می‌آید.', 'simplevpbot' ),
						),
					);
				}

				return array(
					'ok'   => true,
					'data' => array(
						'message'        => __( 'اتصال پنل برقرار است.', 'simplevpbot' ),
						'diag'           => $diag,
						'probes'         => $probes,
						'suggested_base' => $suggested_base,
						'server_status'  => $r_status['json'],
					),
				);
			}
		);
	}

	/**
	 * Telegram getMe.
	 *
	 * @return array{ok:bool, data?:array<string,mixed>, message?:string}
	 */
	public static function test_telegram() {
		$t = (string) SimpleVPBot_Settings::get( 'telegram_token', '' );
		if ( ! $t ) {
			return array( 'ok' => false, 'message' => __( 'توکن تلگرام تنظیم نشده است.', 'simplevpbot' ) );
		}
		$c   = new SimpleVPBot_Telegram_Client( $t );
		$res = $c->get_me();
		if ( empty( $res['ok'] ) ) {
			return array( 'ok' => false, 'message' => __( 'Telegram getMe ناموفق بود.', 'simplevpbot' ), 'data' => array( 'response' => $res ) );
		}
		return array( 'ok' => true, 'data' => $res );
	}

	/**
	 * Set Telegram webhook.
	 *
	 * @return array{ok:bool, data?:array<string,mixed>, message?:string}
	 */
	public static function set_webhook_telegram() {
		$t   = (string) SimpleVPBot_Settings::get( 'telegram_token', '' );
		$sec = (string) SimpleVPBot_Settings::get( 'telegram_webhook_secret', '' );
		if ( '' === $t ) {
			return array( 'ok' => false, 'message' => __( 'توکن تلگرام تنظیم نشده است.', 'simplevpbot' ) );
		}
		if ( '' === $sec ) {
			return array( 'ok' => false, 'message' => __( 'Secret مسیر Webhook تلگرام تنظیم نشده است.', 'simplevpbot' ) );
		}
		$url = SimpleVPBot_Settings::public_site_url() . '/wp-json/simplevpbot/v1/webhook/telegram/' . rawurlencode( $sec );
		$c   = new SimpleVPBot_Telegram_Client( $t );
		$hdr = (string) SimpleVPBot_Settings::get( 'telegram_secret_header', '' );
		$res = $c->set_webhook(
			array(
				'url'                  => $url,
				'secret_token'         => $hdr,
				'allowed_updates'      => array( 'message', 'callback_query' ),
				'drop_pending_updates' => true,
			)
		);
		if ( empty( $res['ok'] ) ) {
			return array( 'ok' => false, 'message' => __( 'ست Webhook تلگرام ناموفق بود.', 'simplevpbot' ), 'data' => array( 'response' => $res ) );
		}
		return array( 'ok' => true, 'data' => array( 'url' => $url, 'response' => $res ) );
	}

	/**
	 * Set Bale webhook.
	 *
	 * @return array{ok:bool, data?:array<string,mixed>, message?:string}
	 */
	public static function set_webhook_bale() {
		$t   = (string) SimpleVPBot_Settings::get( 'bale_token', '' );
		$sec = (string) SimpleVPBot_Settings::get( 'bale_webhook_secret', '' );
		if ( '' === $t ) {
			return array( 'ok' => false, 'message' => __( 'توکن بله تنظیم نشده است.', 'simplevpbot' ) );
		}
		if ( '' === $sec ) {
			return array( 'ok' => false, 'message' => __( 'Secret مسیر Webhook بله تنظیم نشده است.', 'simplevpbot' ) );
		}
		$url = SimpleVPBot_Settings::public_site_url() . '/wp-json/simplevpbot/v1/webhook/bale/' . rawurlencode( $sec );
		$c   = new SimpleVPBot_Bale_Client( $t );
		$res = $c->set_webhook( array( 'url' => $url ) );
		if ( empty( $res['ok'] ) ) {
			return array( 'ok' => false, 'message' => __( 'ست Webhook بله ناموفق بود.', 'simplevpbot' ), 'data' => array( 'response' => $res ) );
		}
		return array( 'ok' => true, 'data' => array( 'url' => $url, 'response' => $res ) );
	}

	/**
	 * Run backup cron job.
	 *
	 * @return array{ok:bool, data?:array<string,mixed>, message?:string}
	 */
	public static function backup_now() {
		try {
			$res = SimpleVPBot_Cron_Backup::run();
		} catch ( Throwable $e ) { // phpcs:ignore
			return array( 'ok' => false, 'message' => $e->getMessage() );
		}
		if ( empty( $res['built'] ) ) {
			return array( 'ok' => false, 'message' => __( 'ساخت فایل بکاپ ناموفق بود. لاگ‌ها را ببینید.', 'simplevpbot' ) );
		}
		$built_at = (int) get_option( 'simplevpbot_last_backup_built_at', 0 );
		$last     = (int) get_option( 'simplevpbot_last_backup_at', 0 );
		$data     = array(
			'last_backup_at' => $last,
			'sent'           => (int) $res['sent'],
			'failed'         => (int) $res['failed'],
			'last_built_at'  => $built_at,
		);
		if ( ! empty( $res['sent'] ) ) {
			$data['message'] = __( 'بکاپ زیپ ساخته و حداقل به یک مقصد ارسال شد.', 'simplevpbot' );
		} else {
			$data['message'] = __( 'زیپ بکاپ ساخته شد؛ به هیچ مقصدی ارسال نشد یا همه ناموفق بودند (تیک‌های مقصد را بررسی کنید).', 'simplevpbot' );
		}
		return array( 'ok' => true, 'data' => $data );
	}

	/**
	 * Restore from a local zip path (file must exist; caller deletes after).
	 *
	 * @param string $zip_path Absolute path.
	 * @param bool   $confirm  User confirmed destructive restore.
	 * @return array{ok:bool, message?:string}
	 */
	public static function restore_from_zip_path( $zip_path, $confirm ) {
		if ( ! $confirm ) {
			return array( 'ok' => false, 'message' => __( 'برای ریستور باید تایید شود.', 'simplevpbot' ) );
		}
		$zip_path = (string) $zip_path;
		if ( '' === $zip_path || ! is_readable( $zip_path ) ) {
			return array( 'ok' => false, 'message' => __( 'فایل در دسترس نیست.', 'simplevpbot' ) );
		}
		if ( 'zip' !== strtolower( pathinfo( $zip_path, PATHINFO_EXTENSION ) ) ) {
			return array( 'ok' => false, 'message' => __( 'فقط فایل .zip مجاز است.', 'simplevpbot' ) );
		}
		$res = SimpleVPBot_Backup_Restore::restore_from_zip_path( $zip_path );
		if ( is_wp_error( $res ) ) {
			return array( 'ok' => false, 'message' => $res->get_error_message() );
		}
		return array( 'ok' => true, 'message' => __( 'ریستور انجام شد.', 'simplevpbot' ) );
	}

	/**
	 * Inbound list from panel.
	 *
	 * @return array{ok:bool, data?:array<string,mixed>, message?:string}
	 */
	public static function inbounds_list( $panel_id = 0 ) {
		$pid = (int) $panel_id;
		if ( $pid < 0 ) {
			$pid = 0;
		}
		return SimpleVPBot_Xui_Client::run_with_panel(
			$pid,
			function () {
				if ( ! SimpleVPBot_Xui_Client::login_with_retries( 6, 300000 ) ) {
					return array( 'ok' => false, 'message' => __( 'ورود پنل ناموفق.', 'simplevpbot' ) );
				}
				$raw = SimpleVPBot_Xui_Client::inbounds_list();
				if ( null === $raw || ! is_array( $raw ) ) {
					return array( 'ok' => false, 'message' => __( 'لیست Inbound دریافت نشد. API پنل، مسیر /panel/api و تست اتصال پنل را در «پنل 3x-ui» بررسی کنید.', 'simplevpbot' ) );
				}
				$list = array();
				foreach ( $raw as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$list[] = array(
						'id'       => (int) ( $row['id'] ?? 0 ),
						'remark'   => (string) ( $row['remark'] ?? '' ),
						'port'     => (int) ( $row['port'] ?? 0 ),
						'protocol' => (string) ( $row['protocol'] ?? '' ),
					);
				}
				return array( 'ok' => true, 'data' => array( 'inbounds' => $list ) );
			}
		);
	}

	/**
	 * Clients in one inbound.
	 *
	 * @param int $inbound_id Inbound id.
	 * @return array{ok:bool, data?:array<string,mixed>, message?:string}
	 */
	public static function inbound_clients( $inbound_id, $panel_id = 1 ) {
		$iid = (int) $inbound_id;
		if ( $iid < 1 ) {
			return array( 'ok' => false, 'message' => __( 'شناسه نامعتبر.', 'simplevpbot' ) );
		}
		$panel_id = (int) $panel_id;
		if ( $panel_id < 0 ) {
			$panel_id = 0;
		}
		$svc_panel = $panel_id > 0 ? $panel_id : 1;
		return SimpleVPBot_Xui_Client::run_with_panel(
			$panel_id,
			function () use ( $iid, $panel_id, $svc_panel ) {
				if ( ! SimpleVPBot_Xui_Client::login_with_retries( 6, 300000 ) ) {
					return array( 'ok' => false, 'message' => __( 'ورود پنل ناموفق.', 'simplevpbot' ) );
				}
				$inb = SimpleVPBot_Xui_Client::inbound_get( $iid );
				if ( ! $inb ) {
					return array( 'ok' => false, 'message' => __( 'Inbound یافت نشد.', 'simplevpbot' ) );
				}
				$settings    = isset( $inb['settings'] ) ? $inb['settings'] : '';
				$dec         = is_string( $settings ) ? json_decode( $settings, true ) : ( is_array( $settings ) ? $settings : array() );
				$inb_remark  = (string) ( $inb['remark'] ?? '' );
				$clients     = array();
				$first_debug = null;
				if ( is_array( $dec ) && ! empty( $dec['clients'] ) && is_array( $dec['clients'] ) ) {
					foreach ( $dec['clients'] as $c ) {
						if ( ! is_array( $c ) || empty( $c['email'] ) ) {
							continue;
						}
						$email_raw = (string) $c['email'];
						$email     = trim( $email_raw );
						$svc       = SimpleVPBot_Model_Service::find_by_inbound_email( $iid, $email, $svc_panel );
						$u         = $svc ? SimpleVPBot_Model_User::find( (int) $svc->user_id ) : null;

						$total_bytes = SimpleVPBot_Inbound_Linker::resolve_quota_bytes( $c['totalGB'] ?? 0, $email );
						$total_gb    = $total_bytes > 0 ? (int) round( $total_bytes / 1073741824 ) : 0;
						$exp_ms      = isset( $c['expiryTime'] ) ? (int) $c['expiryTime'] : 0;

						$comment_keys = array( 'comment', 'remark', 'memo', 'note', 'desc' );
						$comment_val  = '';
						foreach ( $comment_keys as $ck ) {
							if ( isset( $c[ $ck ] ) && '' !== trim( (string) $c[ $ck ] ) ) {
								$comment_val = trim( (string) $c[ $ck ] );
								break;
							}
						}
						if ( '' === $comment_val && '' !== $inb_remark ) {
							$comment_val = $inb_remark;
						}

						if ( null === $first_debug ) {
							$first_debug = array(
								'keys'       => array_keys( $c ),
								'inb_remark' => $inb_remark,
							);
						}

						$linked_sid = $svc ? (int) $svc->id : 0;
						$linked_uid = $u ? (int) $u->id : 0;
						$clients[]  = array(
							'email'             => $email,
							'id'                => (string) ( $c['id'] ?? '' ),
							'remark'            => (string) ( $c['remark'] ?? '' ),
							'comment'           => $comment_val,
							'tg_id'             => (string) ( $c['tgId'] ?? '' ),
							'sub_id'            => (string) ( $c['subId'] ?? '' ),
							'enable'            => isset( $c['enable'] ) ? ( $c['enable'] ? 1 : 0 ) : 1,
							'total_gb'          => (int) $total_gb,
							'expiry_ms'         => (int) $exp_ms,
							'linked_service_id' => $linked_sid,
							'linked_user_id'    => $linked_uid,
							'linked_user_label' => $u ? SimpleVPBot_Model_User::label( $u ) : '',
							'is_linked'         => $linked_uid > 0 ? 1 : 0,
							'provision_type'    => $svc ? (string) ( $svc->provision_type ?? 'plan' ) : '',
						);
					}
				}
				return array(
					'ok'   => true,
					'data' => array(
						'clients'     => $clients,
						'inb_remark'  => $inb_remark,
						'debug'       => $first_debug,
					),
				);
			}
		);
	}

	/**
	 * Link inbound client to bot user.
	 *
	 * @param int    $inbound_id Inbound id.
	 * @param string $email      Client email on panel.
	 * @param int    $user_id    svp_users.id.
	 * @return array{ok:bool, data?:array<string,mixed>, message?:string}
	 */
	public static function inbound_link( $inbound_id, $email, $user_id, $panel_id = 1 ) {
		$pid = (int) $panel_id;
		if ( $pid < 0 ) {
			$pid = 0;
		}
		$out = SimpleVPBot_Inbound_Linker::link( (int) $inbound_id, (string) $email, (int) $user_id, $pid );
		if ( empty( $out['ok'] ) ) {
			$map = array(
				'no_user'          => __( 'کاربر ربات یافت نشد.', 'simplevpbot' ),
				'exists'           => __( 'این کلاینت قبلاً وصل شده است.', 'simplevpbot' ),
				'no_inbound'       => __( 'Inbound یافت نشد.', 'simplevpbot' ),
				'client_not_found' => __( 'ایمیل در این Inbound نیست.', 'simplevpbot' ),
				'panel_login'      => __( 'ورود پنل ناموفق.', 'simplevpbot' ),
			);
			$key = (string) ( $out['message'] ?? 'err' );
			$msg = isset( $map[ $key ] ) ? $map[ $key ] : $key;
			return array( 'ok' => false, 'message' => $msg );
		}
		return array( 'ok' => true, 'data' => $out );
	}

	/**
	 * Auto-link inbound clients.
	 *
	 * @param int $inbound_id Inbound id.
	 * @return array{ok:bool, data?:array<string,mixed>, message?:string}
	 */
	public static function inbound_autolink( $inbound_id, $panel_id = 1 ) {
		$iid = (int) $inbound_id;
		if ( $iid < 1 ) {
			return array( 'ok' => false, 'message' => __( 'شناسه نامعتبر.', 'simplevpbot' ) );
		}
		$apid = (int) $panel_id;
		if ( $apid < 0 ) {
			$apid = 0;
		}
		$out = SimpleVPBot_Inbound_Linker::auto_link_inbound_clients( $iid, $apid );
		if ( empty( $out['ok'] ) ) {
			$map = array(
				'bad_params'  => __( 'پارامترها نامعتبر است.', 'simplevpbot' ),
				'panel_login' => __( 'ورود پنل ناموفق.', 'simplevpbot' ),
				'no_inbound'  => __( 'Inbound یافت نشد.', 'simplevpbot' ),
			);
			$key = (string) ( $out['message'] ?? 'err' );
			$msg = isset( $map[ $key ] ) ? $map[ $key ] : $key;
			return array( 'ok' => false, 'message' => $msg );
		}
		return array( 'ok' => true, 'data' => $out );
	}

	/**
	 * Retry provisioning for receipt.
	 *
	 * @param int    $receipt_id Receipt id.
	 * @param string $label      Actor label.
	 * @return array{ok:bool, data?:array<string,mixed>, message?:string}
	 */
	public static function receipt_retry_provision( $receipt_id, $label ) {
		$rid = (int) $receipt_id;
		if ( $rid < 1 ) {
			return array( 'ok' => false, 'message' => __( 'شناسه نامعتبر.', 'simplevpbot' ) );
		}
		$res = SimpleVPBot_Receipt_Processor::retry_provision_for_receipt( $rid, (string) $label );
		if ( empty( $res['ok'] ) ) {
			$reason = (string) ( $res['reason'] ?? 'unknown' );
			$detail = (string) ( $res['detail'] ?? '' );
			return array(
				'ok'      => false,
				'message' => __( 'ناموفق:', 'simplevpbot' ) . ' ' . $reason . ( $detail ? ' (' . $detail . ')' : '' ),
			);
		}
		return array(
			'ok'   => true,
			'data' => array(
				'message'    => __( 'سرویس ساخته شد.', 'simplevpbot' ),
				'service_id' => (int) ( $res['service_id'] ?? 0 ),
			),
		);
	}

	/**
	 * Transfer service ownership.
	 *
	 * @param int    $service_id Service id.
	 * @param string $target_raw Target descriptor.
	 * @param string $label      Actor.
	 * @return array{ok:bool, data?:array<string,mixed>, message?:string}
	 */
	public static function service_transfer( $service_id, $target_raw, $label ) {
		$sid = (int) $service_id;
		$target_raw = trim( (string) $target_raw );
		if ( $sid < 1 || '' === $target_raw ) {
			return array( 'ok' => false, 'message' => __( 'پارامترها نامعتبر.', 'simplevpbot' ) );
		}
		$target = SimpleVPBot_Service_Transfer::resolve_user( $target_raw );
		if ( ! $target ) {
			return array( 'ok' => false, 'message' => __( 'کاربر مقصد یافت نشد یا مبهم است.', 'simplevpbot' ) );
		}
		$res = SimpleVPBot_Service_Transfer::transfer( $sid, (int) $target->id, (string) $label );
		if ( empty( $res['ok'] ) ) {
			return array( 'ok' => false, 'message' => (string) ( $res['reason'] ?? 'err' ) );
		}
		return array(
			'ok'   => true,
			'data' => array(
				'message'       => __( 'انتقال انجام شد.', 'simplevpbot' ),
				'target_id'     => (int) $target->id,
				'previous_user' => (int) ( $res['previous_user_id'] ?? 0 ),
			),
		);
	}

	/**
	 * User merge preview.
	 *
	 * @param int $keep_id Keep user id.
	 * @param int $drop_id Drop user id.
	 * @return array{ok:bool, data?:array<string,mixed>, message?:string}
	 */
	public static function user_merge_preview( $keep_id, $drop_id ) {
		$keep = (int) $keep_id;
		$drop = (int) $drop_id;
		if ( $keep < 1 || $drop < 1 || $keep === $drop ) {
			return array( 'ok' => false, 'message' => __( 'keep_id و drop_id معتبر و متفاوت بفرستید.', 'simplevpbot' ) );
		}
		$k = SimpleVPBot_Model_User::find( $keep );
		$d = SimpleVPBot_Model_User::find( $drop );
		if ( ! $k || ! $d ) {
			return array( 'ok' => false, 'message' => __( 'یکی از کاربران یافت نشد.', 'simplevpbot' ) );
		}
		global $wpdb;
		$p      = $wpdb->prefix;
		$counts = array(
			'services'          => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$p}svp_services WHERE user_id = %d AND deleted_at IS NULL", $drop ) ), // phpcs:ignore
			'transactions'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$p}svp_transactions WHERE user_id = %d", $drop ) ), // phpcs:ignore
			'receipts'          => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$p}svp_receipts WHERE user_id = %d", $drop ) ), // phpcs:ignore
			'pending_approvals' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$p}svp_pending_approvals WHERE user_id = %d", $drop ) ), // phpcs:ignore
			'broadcast_queue'   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$p}svp_broadcast_queue WHERE user_id = %d", $drop ) ), // phpcs:ignore
			'sync_codes'        => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$p}svp_sync_codes WHERE user_id = %d", $drop ) ), // phpcs:ignore
		);
		return array(
			'ok'   => true,
			'data' => array(
				'keep' => array(
					'id'           => (int) $k->id,
					'username'     => (string) $k->username,
					'tg_user_id'   => (int) $k->tg_user_id,
					'bale_user_id' => (int) $k->bale_user_id,
					'balance'      => (float) $k->balance,
				),
				'drop' => array(
					'id'           => (int) $d->id,
					'username'     => (string) $d->username,
					'tg_user_id'   => (int) $d->tg_user_id,
					'bale_user_id' => (int) $d->bale_user_id,
					'balance'      => (float) $d->balance,
				),
				'drop_related' => $counts,
			),
		);
	}

	/**
	 * Execute user merge.
	 *
	 * @param int  $keep_id Keep id.
	 * @param int  $drop_id Drop id.
	 * @param bool $confirm Confirmed.
	 * @return array{ok:bool, data?:array<string,mixed>, message?:string}
	 */
	public static function user_merge( $keep_id, $drop_id, $confirm ) {
		$keep = (int) $keep_id;
		$drop = (int) $drop_id;
		if ( $keep < 1 || $drop < 1 || $keep === $drop ) {
			return array( 'ok' => false, 'message' => __( 'پارامترها نامعتبر.', 'simplevpbot' ) );
		}
		if ( ! $confirm ) {
			return array( 'ok' => false, 'message' => __( 'برای اجرا تایید لازم است.', 'simplevpbot' ) );
		}
		$k = SimpleVPBot_Model_User::find( $keep );
		$d = SimpleVPBot_Model_User::find( $drop );
		if ( ! $k || ! $d ) {
			return array( 'ok' => false, 'message' => __( 'یکی از کاربران یافت نشد.', 'simplevpbot' ) );
		}
		SimpleVPBot_Model_User::merge_users( $keep, $drop );
		$still = SimpleVPBot_Model_User::find( $drop );
		return array(
			'ok'   => true,
			'data' => array(
				'message'      => __( 'ادغام انجام شد.', 'simplevpbot' ),
				'drop_deleted' => $still ? false : true,
				'keep_id'      => $keep,
			),
		);
	}

	/**
	 * Test L2TP SSH row.
	 *
	 * @param int $server_id Row id.
	 * @return array{ok:bool, data?:array<string,mixed>, message?:string}
	 */
	public static function l2tp_test( $server_id ) {
		$id  = (int) $server_id;
		$row = $id ? SimpleVPBot_Model_L2TP_Server::find( $id ) : null;
		if ( ! $row ) {
			return array( 'ok' => false, 'message' => __( 'سرور یافت نشد.', 'simplevpbot' ) );
		}
		$driver = SimpleVPBot_SSH_Client::available_driver();
		if ( '' === $driver ) {
			return array( 'ok' => false, 'message' => __( 'درایور SSH در دسترس نیست (phpseclib3/ssh2).', 'simplevpbot' ) );
		}
		$dec = SimpleVPBot_Model_L2TP_Server::decrypted( $row );
		$res = SimpleVPBot_L2TP_Provisioner::test_connection( $dec );
		if ( empty( $res['ok'] ) ) {
			return array( 'ok' => false, 'message' => (string) $res['message'] );
		}
		return array(
			'ok'   => true,
			'data' => array( 'message' => (string) $res['message'], 'driver' => $driver ),
		);
	}
}
