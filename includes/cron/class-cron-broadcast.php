<?php
/**
 * Process broadcast queue.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Cron_Broadcast
 */
class SimpleVPBot_Cron_Broadcast {

	/**
	 * Classify Telegram/Bale API error for queue handling.
	 *
	 * @param array<string, mixed> $r API body.
	 * @return string blocked|rate_limit|bad_request|network|unknown
	 */
	private static function classify_error( array $r ) {
		if ( ! empty( $r['ok'] ) ) {
			return 'unknown';
		}
		$code = isset( $r['error_code'] ) ? (int) $r['error_code'] : 0;
		$desc = isset( $r['description'] ) ? strtolower( (string) $r['description'] ) : '';
		if ( 429 === $code ) {
			return 'rate_limit';
		}
		if ( in_array( $code, array( 502, 503, 504 ), true ) ) {
			return 'network';
		}
		if ( 400 === $code ) {
			return 'bad_request';
		}
		if ( 403 === $code ) {
			if ( false !== strpos( $desc, 'blocked' ) || false !== strpos( $desc, 'deactivated' ) || false !== strpos( $desc, 'forbidden' ) || false !== strpos( $desc, 'kicked' ) ) {
				return 'blocked';
			}
			return 'bad_request';
		}
		if ( '' !== $desc && ( false !== strpos( $desc, 'timeout' ) || false !== strpos( $desc, 'timed out' ) ) ) {
			return 'network';
		}
		if ( isset( $r['description'] ) && is_string( $r['description'] ) && ( false !== strpos( strtolower( $r['description'] ), 'curl error' ) || false !== strpos( strtolower( $r['description'] ), 'could not resolve' ) ) ) {
			return 'network';
		}
		return 'unknown';
	}

	/**
	 * Short error string for DB.
	 *
	 * @param array<string, mixed> $r API body.
	 * @return string
	 */
	private static function error_summary( array $r ) {
		$code = isset( $r['error_code'] ) ? (string) $r['error_code'] : '';
		$desc = isset( $r['description'] ) ? (string) $r['description'] : '';
		$desc = mb_substr( $desc, 0, 500 );
		return trim( $code . ': ' . $desc );
	}

	/**
	 * Plain text fallback when formatted send fails.
	 *
	 * @param string $html Stored canonical HTML.
	 * @return string
	 */
	private static function telegram_html_to_plain( $html ) {
		if ( class_exists( 'SimpleVPBot_Broadcast_Format' ) ) {
			return SimpleVPBot_Broadcast_Format::html_to_plain( $html );
		}
		$t = wp_strip_all_tags( (string) $html );
		return trim( preg_replace( "/\n{3,}/", "\n\n", $t ) );
	}

	/**
	 * Whether Telegram rejected HTML and a plain-text resend is worth trying.
	 *
	 * @param array<string, mixed> $r       API body.
	 * @param array<string, mixed> $payload Queue payload (after platform normalize).
	 * @return bool
	 */
	private static function should_retry_telegram_html_as_plain( array $r, array $payload ) {
		if ( ! empty( $r['ok'] ) ) {
			return false;
		}
		$code = isset( $r['error_code'] ) ? (int) $r['error_code'] : 0;
		if ( 400 !== $code ) {
			return false;
		}
		$pm = isset( $payload['parse_mode'] ) ? strtoupper( trim( (string) $payload['parse_mode'] ) ) : '';
		if ( 'HTML' !== $pm ) {
			return false;
		}
		$desc = strtolower( (string) ( $r['description'] ?? '' ) );
		foreach ( array( 'parse', 'entity', 'entities', 'formatted', 'unsupported', "can't find end", 'unmatched', 'nested', 'byte offset', "can't parse", 'cannot parse', 'invalid entities', 'end tag', 'start tag' ) as $needle ) {
			if ( false !== strpos( $desc, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Send one queue row via Telegram client (used for HTML retry as plain).
	 *
	 * @param SimpleVPBot_Telegram_Client $c       Client.
	 * @param string                      $method  API method.
	 * @param array<string, mixed>        $params  Params.
	 * @param int                         $timeout Timeout.
	 * @return array<string, mixed>
	 */
	private static function telegram_send_with_method( SimpleVPBot_Telegram_Client $c, $method, array $params, $timeout ) {
		if ( 'sendMediaGroup' === $method ) {
			return $c->send_media_group( $params, $timeout );
		}
		if ( 'sendPhoto' === $method ) {
			return $c->send_photo( $params, $timeout );
		}
		return $c->send_message( $params, $timeout );
	}

	/**
	 * Per-platform body: Telegram HTML; Bale native Markdown (no parse_mode).
	 *
	 * @param array<string, mixed> $payload Queue payload.
	 * @param string               $bot tg|bale.
	 * @return array<string, mixed>
	 */
	private static function normalize_broadcast_payload_for_platform( array $payload, $bot ) {
		if ( 'bale' !== (string) $bot ) {
			return $payload;
		}
		$text = isset( $payload['text'] ) ? (string) $payload['text'] : '';
		if ( '' === $text ) {
			unset( $payload['parse_mode'] );
			return $payload;
		}
		if ( class_exists( 'SimpleVPBot_Broadcast_Format' ) ) {
			$payload['text'] = SimpleVPBot_Broadcast_Format::format_for_bale_markdown( $text );
		} else {
			$payload['text'] = self::telegram_html_to_plain( $text );
		}
		unset( $payload['parse_mode'] );
		return $payload;
	}

	/**
	 * Whether Bale rejected Markdown and a plain-text resend is worth trying.
	 *
	 * @param array<string, mixed> $r API body.
	 * @return bool
	 */
	private static function should_retry_bale_as_plain( array $r ) {
		if ( ! empty( $r['ok'] ) ) {
			return false;
		}
		$code = isset( $r['error_code'] ) ? (int) $r['error_code'] : 0;
		if ( 400 !== $code ) {
			return false;
		}
		$desc = strtolower( (string) ( $r['description'] ?? '' ) );
		foreach ( array( 'parse', 'entity', 'entities', 'formatted', 'markdown', "can't parse", 'cannot parse' ) as $needle ) {
			if ( false !== strpos( $desc, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Build API params from stored payload_json.
	 *
	 * Albums: only the first InputMediaPhoto may include caption + parse_mode (Telegram/Bale compat).
	 *
	 * @param array<string, mixed> $payload Decoded payload.
	 * @return array{0:string,1:array<string,mixed>} method name and params.
	 */
	private static function build_send_params( array $payload ) {
		$chat_id = (int) ( $payload['chat_id'] ?? 0 );
		$text    = (string) ( $payload['text'] ?? '' );
		$pm      = isset( $payload['parse_mode'] ) ? trim( (string) $payload['parse_mode'] ) : '';

		$urls = array();
		if ( ! empty( $payload['media_urls'] ) && is_array( $payload['media_urls'] ) ) {
			foreach ( $payload['media_urls'] as $u ) {
				$u = trim( (string) $u );
				if ( '' !== $u && wp_http_validate_url( $u ) ) {
					$urls[] = esc_url_raw( $u );
				}
			}
		}
		$legacy = trim( (string) ( $payload['photo'] ?? '' ) );
		if ( '' !== $legacy && wp_http_validate_url( $legacy ) && empty( $urls ) ) {
			$urls[] = esc_url_raw( $legacy );
		}

		$n = count( $urls );
		if ( $n >= 2 ) {
			$media = array();
			foreach ( $urls as $i => $u ) {
				$item = array(
					'type'  => 'photo',
					'media' => $u,
				);
				if ( 0 === $i ) {
					if ( '' !== $text ) {
						$item['caption'] = $text;
					}
					if ( '' !== $pm && 'None' !== $pm ) {
						$item['parse_mode'] = $pm;
					}
				}
				$media[] = $item;
			}
			return array( 'sendMediaGroup', array( 'chat_id' => $chat_id, 'media' => $media ) );
		}
		if ( 1 === $n ) {
			$params = array(
				'chat_id' => $chat_id,
				'photo'   => $urls[0],
				'caption' => $text,
			);
			if ( '' !== $pm && 'None' !== $pm ) {
				$params['parse_mode'] = $pm;
			}
			return array( 'sendPhoto', $params );
		}
		$params = array(
			'chat_id' => $chat_id,
			'text'    => $text,
		);
		if ( '' !== $pm && 'None' !== $pm ) {
			$params['parse_mode'] = $pm;
		}
		return array( 'sendMessage', $params );
	}

	/**
	 * API client for a queue row (reseller owner token when set).
	 *
	 * @param string $bot         tg|bale.
	 * @param int    $owner_rid   Broadcast owner_svp_user_id.
	 * @param string $tg_tok      Site telegram token.
	 * @param string $bl_tok      Site bale token.
	 * @return SimpleVPBot_Telegram_Client|SimpleVPBot_Bale_Client|null
	 */
	private static function client_for_broadcast_bot( $bot, $owner_rid, $tg_tok, $bl_tok ) {
		$plat = 'bale' === (string) $bot ? 'bale' : 'telegram';
		if ( (int) $owner_rid > 0 && class_exists( 'SimpleVPBot_Bot_Runtime' ) ) {
			$c = SimpleVPBot_Bot_Runtime::client_for_reseller( $plat, (int) $owner_rid );
			if ( $c ) {
				return $c;
			}
		}
		if ( 'bale' === $plat && '' !== $bl_tok ) {
			return new SimpleVPBot_Bale_Client( $bl_tok );
		}
		if ( '' !== $tg_tok ) {
			return new SimpleVPBot_Telegram_Client( $tg_tok );
		}
		return null;
	}

	/**
	 * Run batch.
	 */
	public static function run() {
		$timeout = max( 10, min( 90, (int) SimpleVPBot_Settings::get( 'broadcast_api_timeout_sec', 35 ) ) );
		$reclaim = max( 120, (int) SimpleVPBot_Settings::get( 'broadcast_sending_timeout_sec', 600 ) );
		SimpleVPBot_Model_Broadcast::reclaim_stuck_sending( $reclaim );

		$batch = max( 5, min( 80, (int) SimpleVPBot_Settings::get( 'broadcast_batch_size', 20 ) ) );
		$rows  = SimpleVPBot_Model_Broadcast::pop_queue( $batch );
		if ( empty( $rows ) ) {
			return;
		}
		$tg_tok = (string) SimpleVPBot_Settings::get( 'telegram_token', '' );
		$bl_tok = (string) SimpleVPBot_Settings::get( 'bale_token', '' );
		$usleep = max( 0, (int) SimpleVPBot_Settings::get( 'broadcast_usleep_us', 280000 ) );
		$maxtry = max( 1, min( 20, (int) SimpleVPBot_Settings::get( 'broadcast_max_retries', 8 ) ) );
		$owners = array();

		foreach ( $rows as $row ) {
			$payload = json_decode( (string) $row->payload_json, true );
			if ( ! is_array( $payload ) ) {
				SimpleVPBot_Model_Broadcast::update_queue(
					(int) $row->id,
					array(
						'status'       => 'failed',
						'tries'        => (int) $row->tries + 1,
						'failure_kind' => 'bad_request',
						'last_error'   => 'invalid_payload_json',
					)
				);
				SimpleVPBot_Model_Broadcast::increment_failed( (int) $row->broadcast_id );
				SimpleVPBot_Model_Broadcast::maybe_mark_broadcast_done( (int) $row->broadcast_id );
				continue;
			}
			$qid = (int) $row->id;
			$bid = (int) $row->broadcast_id;
			$fresh_st = SimpleVPBot_Model_Broadcast::get_queue_status( $qid );
			if ( 'sending' !== $fresh_st ) {
				SimpleVPBot_Model_Broadcast::maybe_mark_broadcast_done( $bid );
				continue;
			}
			$bot            = (string) $row->bot;
			$canonical_html = (string) ( $payload['text'] ?? '' );
			$payload        = self::normalize_broadcast_payload_for_platform( $payload, $bot );
			list($method, $api_params) = self::build_send_params( $payload );
			$ok = false;
			$r  = array( 'ok' => false, 'description' => 'no_token' );
			if ( ! isset( $owners[ $bid ] ) ) {
				$bcast                 = SimpleVPBot_Model_Broadcast::find( $bid );
				$owners[ $bid ]        = $bcast ? (int) ( $bcast->owner_svp_user_id ?? 0 ) : 0;
			}
			$owner_rid = (int) $owners[ $bid ];
			$c         = self::client_for_broadcast_bot( $bot, $owner_rid, $tg_tok, $bl_tok );
			if ( $c instanceof SimpleVPBot_Telegram_Client ) {
				$r = self::telegram_send_with_method( $c, $method, $api_params, $timeout );
				$ok = ! empty( $r['ok'] );
				if ( ! $ok && self::should_retry_telegram_html_as_plain( $r, $payload ) ) {
					$fb = $payload;
					$fb['text'] = self::telegram_html_to_plain( $canonical_html );
					unset( $fb['parse_mode'] );
					list($method_fb, $api_fb) = self::build_send_params( $fb );
					$r  = self::telegram_send_with_method( $c, $method_fb, $api_fb, $timeout );
					$ok = ! empty( $r['ok'] );
				}
			} elseif ( $c instanceof SimpleVPBot_Bale_Client ) {
				if ( 'sendMediaGroup' === $method ) {
					$r = $c->send_media_group( $api_params, $timeout );
				} elseif ( 'sendPhoto' === $method ) {
					$r = $c->send_photo( $api_params, $timeout );
				} else {
					$r = $c->send_message( $api_params, $timeout );
				}
				$ok = ! empty( $r['ok'] );
				if ( ! $ok && self::should_retry_bale_as_plain( $r ) ) {
					$fb = $payload;
					$fb['text'] = self::telegram_html_to_plain( $canonical_html );
					unset( $fb['parse_mode'] );
					list($method_fb, $api_fb) = self::build_send_params( $fb );
					if ( 'sendMediaGroup' === $method_fb ) {
						$r = $c->send_media_group( $api_fb, $timeout );
					} elseif ( 'sendPhoto' === $method_fb ) {
						$r = $c->send_photo( $api_fb, $timeout );
					} else {
						$r = $c->send_message( $api_fb, $timeout );
					}
					$ok = ! empty( $r['ok'] );
				}
			} else {
				$r = array( 'ok' => false, 'description' => 'no_token', 'error_code' => 400 );
			}

			if ( $ok ) {
				SimpleVPBot_Model_Broadcast::update_queue(
					$qid,
					array(
						'status'       => 'sent',
						'tries'        => (int) $row->tries + 1,
						'last_error'   => null,
						'failure_kind' => null,
					)
				);
				SimpleVPBot_Model_Broadcast::increment_sent( $bid );
			} else {
				$kind = self::classify_error( $r );
				$err  = self::error_summary( $r );
				$tries = (int) $row->tries + 1;

				if ( 'blocked' === $kind ) {
					SimpleVPBot_Model_Broadcast::update_queue(
						$qid,
						array(
							'status'       => 'failed',
							'tries'        => $tries,
							'failure_kind' => 'blocked',
							'last_error'   => $err,
						)
					);
					SimpleVPBot_Model_Broadcast::increment_blocked( $bid );
				} elseif ( 'rate_limit' === $kind ) {
					SimpleVPBot_Model_Broadcast::update_queue(
						$qid,
						array(
							'status'       => 'pending',
							'tries'        => $tries,
							'failure_kind' => 'rate_limit',
							'last_error'   => $err,
						)
					);
					if ( $usleep > 0 ) {
						usleep( min( 2000000, $usleep * 2 ) );
					}
				} elseif ( 'bad_request' === $kind ) {
					SimpleVPBot_Model_Broadcast::update_queue(
						$qid,
						array(
							'status'       => 'failed',
							'tries'        => $tries,
							'failure_kind' => 'bad_request',
							'last_error'   => $err,
						)
					);
					SimpleVPBot_Model_Broadcast::increment_failed( $bid );
				} else {
					// network, unknown: retry until max tries.
					if ( $tries >= $maxtry ) {
						SimpleVPBot_Model_Broadcast::update_queue(
							$qid,
							array(
								'status'       => 'failed',
								'tries'        => $tries,
								'failure_kind' => $kind,
								'last_error'   => $err,
							)
						);
						SimpleVPBot_Model_Broadcast::increment_failed( $bid );
					} else {
						SimpleVPBot_Model_Broadcast::update_queue(
							$qid,
							array(
								'status'       => 'pending',
								'tries'        => $tries,
								'failure_kind' => $kind,
								'last_error'   => $err,
							)
						);
					}
				}
			}

			SimpleVPBot_Model_Broadcast::maybe_mark_broadcast_done( $bid );
			if ( $usleep > 0 ) {
				usleep( $usleep );
			}
		}
	}
}
