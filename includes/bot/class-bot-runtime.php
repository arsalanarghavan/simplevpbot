<?php
/**
 * Send helpers for Telegram/Bale.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Bot_Runtime
 */
class SimpleVPBot_Bot_Runtime {

	/**
	 * Get API client.
	 *
	 * @param string $platform telegram|bale.
	 * @return SimpleVPBot_Bot_Client|null
	 */
	public static function client( $platform ) {
		return self::client_for_reseller( $platform, 0 );
	}

	/**
	 * API client for a specific reseller (0 = current context or site default).
	 *
	 * @param string $platform          telegram|bale.
	 * @param int    $reseller_svp_user_id Reseller id (0 = use webhook context / main).
	 * @return SimpleVPBot_Bot_Client|null
	 */
	public static function client_for_reseller( $platform, $reseller_svp_user_id = 0 ) {
		$t = self::bot_token_for_reseller( $platform, (int) $reseller_svp_user_id );
		if ( '' === $t ) {
			return null;
		}
		if ( 'bale' === $platform ) {
			return new SimpleVPBot_Bale_Client( $t );
		}
		return new SimpleVPBot_Telegram_Client( $t );
	}

	/**
	 * Active bot token: reseller webhook context overrides site defaults.
	 *
	 * @param string $platform telegram|bale.
	 * @return string Empty if unset.
	 */
	public static function bot_token_for_current_context( $platform ) {
		$plat = ( 'bale' === $platform ) ? 'bale' : 'telegram';
		if ( class_exists( 'SimpleVPBot_Bot_Context' ) && SimpleVPBot_Bot_Context::is_reseller_bot() ) {
			$prof = SimpleVPBot_Bot_Context::reseller_profile();
			if ( $prof && class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
				$t = SimpleVPBot_Model_Reseller_Bot_Profile::token_for_platform( $prof, $plat );
				if ( '' !== $t ) {
					return $t;
				}
			}
			// In reseller webhook context, never fall back to global bot token.
			return '';
		}
		if ( 'bale' === $plat ) {
			return (string) SimpleVPBot_Settings::get( 'bale_token', '' );
		}
		return (string) SimpleVPBot_Settings::get( 'telegram_token', '' );
	}

	/**
	 * Send text message.
	 *
	 * @param string               $platform Platform.
	 * @param int                  $chat_id Chat id.
	 * @param string               $text Text.
	 * @param array<string, mixed> $extra Extra params (reply_markup parse_mode).
	 * @return array<string, mixed>|null
	 */
	/**
	 * Bot token for reseller id (0 = current context or site default).
	 *
	 * @param string $platform             telegram|bale.
	 * @param int    $reseller_svp_user_id Reseller svp_users.id.
	 * @return string
	 */
	public static function bot_token_for_reseller( $platform, $reseller_svp_user_id = 0 ) {
		$rid = (int) $reseller_svp_user_id;
		if ( $rid < 1 && class_exists( 'SimpleVPBot_Bot_Context' ) && SimpleVPBot_Bot_Context::is_reseller_bot() ) {
			return self::bot_token_for_current_context( $platform );
		}
		if ( $rid > 0 && class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
			$prof = SimpleVPBot_Model_Reseller_Bot_Profile::find_by_reseller( $rid );
			if ( $prof ) {
				$t = SimpleVPBot_Model_Reseller_Bot_Profile::token_for_platform( $prof, $platform );
				if ( '' !== $t ) {
					return $t;
				}
			}
			return '';
		}
		return self::bot_token_for_current_context( $platform );
	}

	/**
	 * Send using a specific reseller bot token (falls back to site bot when reseller id is 0).
	 *
	 * @param string               $platform             Platform.
	 * @param int                  $chat_id              Chat id.
	 * @param string               $text                 Text.
	 * @param int                  $reseller_svp_user_id Reseller id (0 = main / context).
	 * @param array<string, mixed> $extra                Extra params.
	 * @return array<string, mixed>|null
	 */
	public static function send_message_for_reseller( $platform, $chat_id, $text, $reseller_svp_user_id = 0, array $extra = array() ) {
		$c = self::client_for_reseller( $platform, (int) $reseller_svp_user_id );
		if ( ! $c ) {
			if ( (int) $reseller_svp_user_id > 0 ) {
				return self::send_message( $platform, $chat_id, $text, $extra );
			}
			if ( class_exists( 'SimpleVPBot_Logger' ) ) {
				SimpleVPBot_Logger::error(
					'send_message_for_reseller: no bot API client',
					array(
						'platform'             => $platform,
						'chat_id'              => (int) $chat_id,
						'reseller_svp_user_id' => (int) $reseller_svp_user_id,
					)
				);
			}
			return null;
		}
		if ( 'bale' === $platform ) {
			$text = self::scrub_bale_text( (string) $text );
		}
		$params = array_merge(
			array(
				'chat_id' => $chat_id,
				'text'    => $text,
			),
			$extra
		);
		return $c->send_message( $params );
	}

	public static function send_message( $platform, $chat_id, $text, array $extra = array() ) {
		$c = self::client( $platform );
		if ( ! $c ) {
			if ( class_exists( 'SimpleVPBot_Logger' ) ) {
				SimpleVPBot_Logger::error(
					'send_message: no bot API client (missing token for context)',
					array(
						'platform'             => $platform,
						'chat_id'              => (int) $chat_id,
						'scope'                => class_exists( 'SimpleVPBot_Bot_Context' ) && SimpleVPBot_Bot_Context::is_reseller_bot() ? 'reseller' : 'main',
						'reseller_svp_user_id' => class_exists( 'SimpleVPBot_Bot_Context' ) ? (int) SimpleVPBot_Bot_Context::reseller_svp_user_id() : 0,
					)
				);
			}
			return null;
		}
		if ( 'bale' === $platform ) {
			$text = self::scrub_bale_text( (string) $text );
		}
		$params = array_merge(
			array(
				'chat_id' => $chat_id,
				'text'    => $text,
			),
			$extra
		);
		$res = $c->send_message( $params );
		return $res;
	}

	/**
	 * Send message and append support contact footer when text mentions پشتیبانی.
	 *
	 * @param string              $platform Platform.
	 * @param int                 $chat_id Chat id.
	 * @param string              $text Message.
	 * @param array<string,mixed> $extra Extra API params.
	 * @return mixed|null
	 */
	public static function send_message_with_support( $platform, $chat_id, $text, array $extra = array() ) {
		if ( class_exists( 'SimpleVPBot_Support_Contacts' ) ) {
			$text = SimpleVPBot_Support_Contacts::append_to_message( (string) $text, $platform );
		}
		return self::send_message( $platform, $chat_id, $text, $extra );
	}

	/**
	 * Bale: avoid disallowed product wording; keep VPS framing.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function scrub_bale_text( $text ) {
		$t = (string) $text;
		$t = str_ireplace( 'VPN', 'VPS', $t );
		$t = str_ireplace( 'وی‌پی‌ان', 'VPS', $t );
		$t = str_ireplace( ' وي پي ان ', ' VPS ', $t );
		return $t;
	}

	/**
	 * Prepare photo caption for Telegram/Bale (scrub + length limit).
	 *
	 * @param string $platform telegram|bale.
	 * @param string $caption  Raw caption.
	 * @param int    $max_len  Max characters (Telegram limit 1024).
	 * @return string
	 */
	public static function prepare_photo_caption( $platform, $caption, $max_len = 1024 ) {
		$caption = (string) $caption;
		if ( 'bale' === $platform ) {
			$caption = self::scrub_bale_text( $caption );
		}
		$max_len = max( 1, (int) $max_len );
		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			if ( mb_strlen( $caption, 'UTF-8' ) > $max_len ) {
				return mb_substr( $caption, 0, $max_len - 1, 'UTF-8' ) . '…';
			}
			return $caption;
		}
		if ( strlen( $caption ) > $max_len ) {
			return substr( $caption, 0, $max_len - 3 ) . '...';
		}
		return $caption;
	}

	/**
	 * Map Persian/Arabic-Indic digits to ASCII 0-9.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function normalize_digits( $text ) {
		$s = (string) $text;
		static $map = null;
		if ( null === $map ) {
			$map = array(
				'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
				'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
			);
		}
		return strtr( $s, $map );
	}

	/**
	 * Edit message text.
	 *
	 * @param string               $platform Platform.
	 * @param int                  $chat_id Chat id.
	 * @param int                  $message_id Message id.
	 * @param string               $text Text.
	 * @param array<string, mixed> $extra Extra.
	 * @return array<string, mixed>|null
	 */
	public static function edit_message_text( $platform, $chat_id, $message_id, $text, array $extra = array() ) {
		$c = self::client( $platform );
		if ( ! $c ) {
			return null;
		}
		if ( 'bale' === $platform ) {
			$text = self::scrub_bale_text( (string) $text );
		}
		$params = array_merge(
			array(
				'chat_id'    => $chat_id,
				'message_id' => $message_id,
				'text'       => $text,
			),
			$extra
		);
		return $c->edit_message_text( $params );
	}

	/**
	 * Edit reply markup only.
	 *
	 * @param string               $platform Platform.
	 * @param int                  $chat_id Chat id.
	 * @param int                  $message_id Message id.
	 * @param array<string, mixed> $markup Markup.
	 * @return array<string, mixed>|null
	 */
	public static function edit_reply_markup( $platform, $chat_id, $message_id, array $markup ) {
		$c = self::client( $platform );
		if ( ! $c ) {
			return null;
		}
		return $c->edit_message_reply_markup(
			array(
				'chat_id'         => $chat_id,
				'message_id'      => $message_id,
				'reply_markup'    => $markup,
			)
		);
	}

	/**
	 * Answer callback query.
	 *
	 * @param string               $platform Platform.
	 * @param array<string, mixed> $params Params.
	 * @return array<string, mixed>|null
	 */
	public static function answer_callback_query( $platform, array $params ) {
		$c = self::client( $platform );
		return $c ? $c->answer_callback_query( $params ) : null;
	}

	/**
	 * Send photo from path.
	 *
	 * @param string               $platform Platform.
	 * @param int                  $chat_id Chat id.
	 * @param string               $path File path.
	 * @param string               $caption Caption.
	 * @param array<string, mixed> $extra Extra params (reply_markup etc.). Arrays are JSON-encoded.
	 * @return array<string, mixed>|null
	 */
	public static function send_photo_file( $platform, $chat_id, $path, $caption = '', array $extra = array() ) {
		$c = self::client( $platform );
		if ( ! $c ) {
			return null;
		}
		$caption = self::prepare_photo_caption( $platform, (string) $caption );
		$params = array(
			'chat_id' => $chat_id,
			'photo'   => $path,
			'caption' => $caption,
		);
		foreach ( $extra as $k => $v ) {
			if ( is_array( $v ) ) {
				$params[ $k ] = wp_json_encode( $v );
			} else {
				$params[ $k ] = (string) $v;
			}
		}
		return $c->call_multipart( 'sendPhoto', $params );
	}

	/**
	 * Send photo by platform file_id (Telegram/Bale).
	 *
	 * @param string               $platform Platform.
	 * @param int                  $chat_id Chat id.
	 * @param string               $file_id File id.
	 * @param string               $caption Caption.
	 * @param array<string, mixed> $extra Pass reply_markup, etc.
	 * @return array<string, mixed>|null
	 */
	public static function send_photo( $platform, $chat_id, $file_id, $caption = '', array $extra = array() ) {
		$c = self::client( $platform );
		if ( ! $c ) {
			return null;
		}
		$caption = self::prepare_photo_caption( $platform, (string) $caption );
		$params = array_merge(
			array(
				'chat_id' => $chat_id,
				'photo'   => (string) $file_id,
				'caption' => $caption,
			),
			$extra
		);
		return $c->send_photo( $params );
	}

	/**
	 * sendInvoice.
	 *
	 * @param string               $platform telegram|bale.
	 * @param array<string, mixed> $params API params.
	 * @return array<string, mixed>|null
	 */
	public static function send_invoice( $platform, array $params ) {
		$c = self::client( $platform );
		if ( ! $c ) {
			return null;
		}
		return $c->send_invoice( $params );
	}

	/**
	 * answerPreCheckoutQuery (Bale / Telegram).
	 *
	 * @param string $platform      Platform.
	 * @param string $query_id    pre_checkout_query_id.
	 * @param bool   $ok            Success.
	 * @param string $err_message  Error message when not ok.
	 * @return array<string, mixed>|null
	 */
	public static function answer_pre_checkout_query( $platform, $query_id, $ok, $err_message = '' ) {
		$c = self::client( $platform );
		if ( ! $c ) {
			return null;
		}
		$payload = array(
			'pre_checkout_query_id' => (string) $query_id,
			'ok'                    => (bool) $ok,
		);
		if ( ! $ok && (string) $err_message !== '' ) {
			$payload['error_message'] = mb_substr( (string) $err_message, 0, 200 );
		}
		return $c->answer_pre_checkout_query( $payload );
	}

	/**
	 * Download a file that was sent to the bot (Telegram/Bale getFile) to a local path.
	 *
	 * @param string $platform  telegram|bale.
	 * @param string $file_id  File id from message.document.
	 * @param string $dest     Absolute local path to write.
	 * @return true|\WP_Error
	 */
	public static function download_bot_file_to_path( $platform, $file_id, $dest ) {
		$file_id = (string) $file_id;
		if ( '' === $file_id ) {
			return new WP_Error( 'svp_nofid', 'No file_id' );
		}
		$c = self::client( $platform );
		if ( ! $c ) {
			return new WP_Error( 'svp_noclient', 'No bot client' );
		}
		$gf = $c->get_file( array( 'file_id' => $file_id ) );
		if ( empty( $gf['ok'] ) || empty( $gf['result']['file_path'] ) ) {
			return new WP_Error( 'svp_getfile', is_array( $gf ) ? wp_json_encode( $gf ) : 'getFile failed' );
		}
		$rel = (string) $gf['result']['file_path'];
		$tok = self::bot_token_for_current_context( $platform );
		if ( '' === $tok ) {
			return new WP_Error( 'svp_notok', 'No token' );
		}
		$rel = ltrim( str_replace( array( "\0", '../' ), '', $rel ), '/' );
		if ( 'bale' === $platform ) {
			$url = 'https://tapi.bale.ai/file/bot' . rawurlencode( $tok ) . '/' . $rel;
		} else {
			$url = 'https://api.telegram.org/file/bot' . rawurlencode( $tok ) . '/' . $rel;
		}
		$resp = wp_remote_get( $url, array( 'timeout' => 120, 'redirection' => 3 ) );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		$body = (string) wp_remote_retrieve_body( $resp );
		if ( $code < 200 || $code >= 300 || '' === $body ) {
			return new WP_Error( 'svp_fetch', 'HTTP ' . (string) $code );
		}
		if ( false === file_put_contents( $dest, $body ) ) { // phpcs:ignore
			return new WP_Error( 'svp_write', 'Write failed' );
		}
		return true;
	}

	/**
	 * Download Telegram user's current profile photo to a temp file (smallest size).
	 *
	 * @param int $tg_user_id Telegram user id.
	 * @return string Absolute path or ''.
	 */
	public static function telegram_user_profile_photo_temp( $tg_user_id ) {
		$uid = (int) $tg_user_id;
		if ( $uid < 1 ) {
			return '';
		}
		$tok = self::bot_token_for_current_context( 'telegram' );
		if ( '' === $tok ) {
			return '';
		}
		$tg = new SimpleVPBot_Telegram_Client( $tok );
		$r  = $tg->call(
			'getUserProfilePhotos',
			array(
				'user_id' => $uid,
				'limit'   => 1,
			)
		);
		if ( empty( $r['ok'] ) || empty( $r['result']['photos'][0] ) || ! is_array( $r['result']['photos'][0] ) ) {
			return '';
		}
		$sizes = $r['result']['photos'][0];
		$last  = end( $sizes );
		$fid   = is_array( $last ) && isset( $last['file_id'] ) ? (string) $last['file_id'] : '';
		if ( '' === $fid ) {
			return '';
		}
		$tmp = wp_tempnam( 'svp-prof-' );
		if ( ! $tmp ) {
			return '';
		}
		$down = self::download_bot_file_to_path( 'telegram', $fid, $tmp );
		if ( is_wp_error( $down ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return '';
		}
		return $tmp;
	}
}
