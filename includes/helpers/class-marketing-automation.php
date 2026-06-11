<?php
/**
 * Issue marketing offers, send bot messages, apply codes.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Marketing_Automation
 */
class SimpleVPBot_Marketing_Automation {

	const BATCH_PER_RULE = 40;

	/**
	 * Hourly cron entry: all owners with active rules.
	 */
	public static function run_cron() {
		if ( ! SimpleVPBot_Settings::get( 'enabled', true ) ) {
			return;
		}
		global $wpdb;
		$rt = SimpleVPBot_Model_Marketing_Rule::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$owners = $wpdb->get_col( "SELECT DISTINCT owner_svp_user_id FROM {$rt} WHERE enabled = 1" );
		foreach ( (array) $owners as $oid ) {
			self::run_for_owner( (int) $oid, self::BATCH_PER_RULE );
		}
	}

	/**
	 * Process one owner scope.
	 *
	 * @param int $owner_svp_user_id 0 = site.
	 * @param int $limit Per rule.
	 * @return array{processed:int,sent:int}
	 */
	public static function run_for_owner( $owner_svp_user_id, $limit = 40 ) {
		$stats = array( 'processed' => 0, 'sent' => 0 );
		$rules = SimpleVPBot_Model_Marketing_Rule::list_active_for_owner( $owner_svp_user_id );
		foreach ( $rules as $rule ) {
			$uids = self::eligible_user_ids_for_rule( $rule, $limit );
			foreach ( $uids as $uid ) {
				++$stats['processed'];
				$sent = self::issue_and_send_for_user( $rule, $uid );
				if ( $sent ) {
					++$stats['sent'];
				}
			}
		}
		return $stats;
	}

	/**
	 * Run single rule now (dashboard).
	 *
	 * @param int $rule_id Rule id.
	 * @param int $limit Max users.
	 * @return array{processed:int,sent:int}
	 */
	public static function run_rule_now( $rule_id, $limit = 80 ) {
		$rule = SimpleVPBot_Model_Marketing_Rule::find( $rule_id );
		if ( ! $rule || empty( $rule->enabled ) ) {
			return array( 'processed' => 0, 'sent' => 0 );
		}
		$stats = array( 'processed' => 0, 'sent' => 0 );
		foreach ( self::eligible_user_ids_for_rule( $rule, $limit ) as $uid ) {
			++$stats['processed'];
			if ( self::issue_and_send_for_user( $rule, $uid ) ) {
				++$stats['sent'];
			}
		}
		return $stats;
	}

	/**
	 * Manual offer for one user (optional rule template).
	 *
	 * @param int $user_id Target user.
	 * @param int $rule_id Rule to copy settings from (0 = minimal default).
	 * @param int $actor_owner Owner for permission scope.
	 * @return array{ok:bool, offer_id?:int, message?:string}
	 */
	public static function send_manual( $user_id, $rule_id, $actor_owner = 0 ) {
		$uid = (int) $user_id;
		$rid = (int) $rule_id;
		if ( $uid < 1 ) {
			return array( 'ok' => false, 'message' => 'invalid_user' );
		}
		$user = SimpleVPBot_Model_User::find( $uid );
		if ( ! $user ) {
			return array( 'ok' => false, 'message' => 'user_not_found' );
		}
		if ( $rid > 0 ) {
			$rule = SimpleVPBot_Model_Marketing_Rule::find( $rid );
			if ( ! $rule ) {
				return array( 'ok' => false, 'message' => 'rule_not_found' );
			}
			if ( (int) $actor_owner > 0 && (int) $rule->owner_svp_user_id !== (int) $actor_owner ) {
				return array( 'ok' => false, 'message' => 'forbidden' );
			}
		} else {
			$rule = (object) array(
				'id'                  => 0,
				'owner_svp_user_id'   => max( 0, (int) $actor_owner ),
				'segment_key'         => 'never_purchased',
				'cooldown_days'       => 0,
				'discount_type'       => 'percent',
				'discount_value'      => 10,
				'max_discount_toman'  => null,
				'code_valid_days'     => 7,
				'max_uses_per_user'   => 1,
				'message_body'        => '',
				'channel_telegram'    => 1,
				'channel_bale'        => 1,
				'allow_new_purchase'  => 1,
				'allow_renew_same'    => 1,
				'allow_add_volume'    => 1,
			);
		}
		if ( self::issue_and_send_for_user( $rule, $uid, true ) ) {
			$open = SimpleVPBot_Model_Marketing_Offer::latest_open_for_user( $uid );
			return array(
				'ok'       => true,
				'offer_id' => $open ? (int) $open->id : 0,
			);
		}
		return array( 'ok' => false, 'message' => 'send_failed' );
	}

	/**
	 * @param object $rule Rule row.
	 * @param int    $limit Limit.
	 * @return array<int, int>
	 */
	public static function eligible_user_ids_for_rule( $rule, $limit = 40 ) {
		if ( ! class_exists( 'SimpleVPBot_Marketing_Lifecycle_Analytics' ) ) {
			return array();
		}
		$scope = (int) ( $rule->owner_svp_user_id ?? 0 );
		return SimpleVPBot_Marketing_Lifecycle_Analytics::eligible_user_ids_for_rule( $rule, $scope, $limit );
	}

	/**
	 * @param object $rule Rule.
	 * @param int    $user_id User.
	 * @param bool   $force_manual Skip cooldown for manual send.
	 * @return bool Sent.
	 */
	public static function issue_and_send_for_user( $rule, $user_id, $force_manual = false ) {
		$uid = (int) $user_id;
		$rid = (int) ( $rule->id ?? 0 );
		if ( $uid < 1 ) {
			return false;
		}
		$user = SimpleVPBot_Model_User::find( $uid );
		if ( ! $user || 'approved' !== (string) $user->status ) {
			return false;
		}
		if ( $rid > 0 && ! $force_manual ) {
			$existing = SimpleVPBot_Model_Marketing_Offer::find_by_rule_user( $rid, $uid );
			if ( $existing && in_array( (string) $existing->status, array( 'issued', 'sent', 'converted' ), true ) ) {
				$cool = max( 0, (int) ( $rule->cooldown_days ?? 0 ) );
				if ( $cool > 0 ) {
					$last = SimpleVPBot_Model_Marketing_Offer::last_sent_timestamp( $rid, $uid );
					if ( $last > 0 && $last > time() - $cool * DAY_IN_SECONDS ) {
						return false;
					}
				} elseif ( $existing ) {
					return false;
				}
			}
		}
		$code_id = self::ensure_discount_code( $rule, $uid );
		if ( $code_id < 1 ) {
			return false;
		}
		$offer_id = 0;
		if ( $rid > 0 ) {
			$offer_id = (int) ( SimpleVPBot_Model_Marketing_Offer::find_by_rule_user( $rid, $uid )->id ?? 0 );
			if ( $offer_id < 1 ) {
				$offer_id = SimpleVPBot_Model_Marketing_Offer::insert(
					array(
						'rule_id'           => $rid,
						'svp_user_id'       => $uid,
						'discount_code_id'  => $code_id,
						'status'            => 'issued',
						'meta_json'         => wp_json_encode( array( 'segment' => (string) ( $rule->segment_key ?? '' ) ) ),
					)
				);
			} else {
				SimpleVPBot_Model_Marketing_Offer::update(
					$offer_id,
					array(
						'discount_code_id' => $code_id,
						'status'           => 'issued',
					)
				);
			}
		} else {
			$offer_id = SimpleVPBot_Model_Marketing_Offer::insert(
				array(
					'rule_id'           => 0,
					'svp_user_id'       => $uid,
					'discount_code_id'  => $code_id,
					'status'            => 'issued',
					'meta_json'         => wp_json_encode( array( 'manual' => 1 ) ),
				)
			);
		}
		$code_row = SimpleVPBot_Model_Discount_Code::find( $code_id );
		$text     = self::build_message( $rule, $user, $code_row, $offer_id );
		$channel  = self::channel_for_rule( $rule );
		if ( ! class_exists( 'SimpleVPBot_User_Notify' ) ) {
			return false;
		}
		SimpleVPBot_User_Notify::send_to_user( $user, $text, $channel, null );
		SimpleVPBot_Model_Marketing_Offer::update(
			$offer_id,
			array(
				'status'  => 'sent',
				'sent_at' => current_time( 'mysql' ),
			)
		);
		if ( class_exists( 'SimpleVPBot_Audit_Log' ) ) {
			SimpleVPBot_Audit_Log::record(
				'marketing.offer_sent',
				array(
					'offer_id' => $offer_id,
					'rule_id'  => $rid,
					'user_id'  => $uid,
					'code_id'  => $code_id,
				)
			);
		}
		return true;
	}

	/**
	 * @param object $rule Rule.
	 * @param int    $user_id User.
	 * @return int Discount code id.
	 */
	private static function ensure_discount_code( $rule, $user_id ) {
		$owner = max( 0, (int) ( $rule->owner_svp_user_id ?? 0 ) );
		$seg   = sanitize_key( (string) ( $rule->segment_key ?? 'MKT' ) );
		$prefix = strtoupper( substr( $seg, 0, 3 ) );
		$code   = $prefix . $user_id . '-' . strtoupper( substr( md5( $owner . ':' . $user_id . ':' . (int) ( $rule->id ?? 0 ) ), 0, 6 ) );
		$existing = SimpleVPBot_Model_Discount_Code::find_by_code( $code, $owner );
		if ( $existing ) {
			return (int) $existing->id;
		}
		$days = max( 1, (int) ( $rule->code_valid_days ?? 7 ) );
		$valid_until = gmdate( 'Y-m-d H:i:s', time() + $days * DAY_IN_SECONDS );
		$d_type = in_array( (string) ( $rule->discount_type ?? 'percent' ), array( 'percent', 'fixed_toman', 'percent_per_gb', 'fixed_per_gb' ), true )
			? (string) $rule->discount_type
			: 'percent';
		$seg_key = (string) ( $rule->segment_key ?? '' );
		$allow_renew = ( 'expiring_renew' === $seg_key ) ? 1 : 0;
		$allow_new   = in_array( $seg_key, array( 'never_purchased', 'abandoned_checkout', 'stale_buy_funnel', 'churned' ), true ) ? 1 : 1;
		return SimpleVPBot_Model_Discount_Code::insert(
			array(
				'owner_svp_user_id'      => $owner,
				'code'                   => $code,
				'active'                 => 1,
				'discount_type'          => $d_type,
				'discount_value'         => max( 0, (float) ( $rule->discount_value ?? 0 ) ),
				'max_uses'               => max( 1, (int) ( $rule->max_uses_per_user ?? 1 ) ),
				'valid_until'            => $valid_until,
				'restricted_svp_user_id' => (int) $user_id,
				'max_discount_toman'     => isset( $rule->max_discount_toman ) ? (float) $rule->max_discount_toman : null,
				'allow_new_purchase'     => $allow_new,
				'allow_renew_same'       => $allow_renew ? 1 : 0,
				'allow_add_volume'       => 1,
				'allow_add_user_slots'   => 0,
			)
		);
	}

	/**
	 * @param object      $rule Rule.
	 * @param object      $user User.
	 * @param object|null $code_row Code.
	 * @param int         $offer_id Offer.
	 * @return string
	 */
	public static function build_message( $rule, $user, $code_row, $offer_id = 0 ) {
		$body = trim( (string) ( $rule->message_body ?? '' ) );
		$code = $code_row ? (string) $code_row->code : '';
		if ( '' === $body ) {
			$seg = (string) ( $rule->segment_key ?? '' );
			$templates = array(
				'churned'            => 'msg.marketing.template.churned',
				'never_purchased'    => 'msg.marketing.template.never_purchased',
				'abandoned_checkout' => 'msg.marketing.template.abandoned_checkout',
				'stale_buy_funnel'   => 'msg.marketing.template.stale_buy_funnel',
				'expiring_renew'     => 'msg.marketing.template.expiring_renew',
			);
			$key  = isset( $templates[ $seg ] ) ? $templates[ $seg ] : 'msg.marketing.template.default';
			$body = SimpleVPBot_Texts::get( $key );
		}
		$text = str_replace(
			array( '{code}', '{name}', '{offer_id}' ),
			array( $code, trim( (string) ( $user->first_name ?? '' ) ), (string) (int) $offer_id ),
			$body
		);
		if ( $offer_id > 0 ) {
			$text .= "\n\n" . SimpleVPBot_Texts::get( 'msg.marketing.apply_button_hint' );
		}
		return $text;
	}

	/**
	 * @param object $rule Rule.
	 * @return string telegram|bale|both
	 */
	private static function channel_for_rule( $rule ) {
		$tg = ! isset( $rule->channel_telegram ) || ! empty( $rule->channel_telegram );
		$bl = ! isset( $rule->channel_bale ) || ! empty( $rule->channel_bale );
		if ( $tg && $bl ) {
			return 'both';
		}
		if ( $bl ) {
			return 'bale';
		}
		return 'telegram';
	}

	/**
	 * Parse /start offer_CODE payload.
	 *
	 * @param string $text Start text.
	 * @return string Normalized code or empty.
	 */
	public static function parse_offer_code_from_start( $text ) {
		$parts = preg_split( '/\s+/', trim( (string) $text ), 2 );
		if ( count( $parts ) < 2 ) {
			return '';
		}
		$payload = trim( (string) $parts[1] );
		if ( preg_match( '/^offer_(.+)$/i', $payload, $m ) ) {
			return SimpleVPBot_Model_Discount_Code::normalize_code( $m[1] );
		}
		return '';
	}

	/**
	 * Handle deep link after user exists.
	 *
	 * @param array<string, mixed> $ctx Bot context.
	 * @param string               $code Normalized code.
	 */
	public static function handle_start_offer( array $ctx, $code ) {
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$user     = $ctx['user'] ?? null;
		if ( ! $user || '' === $code ) {
			return;
		}
		$offer = SimpleVPBot_Model_Marketing_Offer::find_by_discount_code( $code );
		$row   = SimpleVPBot_Model_Discount_Code::find_by_code( $code );
		if ( ! $row || (int) $row->restricted_svp_user_id !== (int) $user->id ) {
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				SimpleVPBot_Texts::get_for_user( 'msg.marketing.offer_invalid', $user )
			);
			return;
		}
		$msg = SimpleVPBot_Texts::get_for_user( 'msg.marketing.code_active', $user ) . ' `' . $code . '`';
		$kb  = null;
		if ( $offer && (int) $offer->id > 0 && class_exists( 'SimpleVPBot_Keyboards' ) ) {
			$kb = array(
				'inline_keyboard' => array(
					array(
						array(
							'text'          => SimpleVPBot_Texts::get_for_user( 'btn.marketing.apply_purchase', $user ),
							'callback_data' => 'mkt_offer_apply:' . (int) $offer->id,
						),
					),
				),
			);
		}
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			$msg,
			array( 'reply_markup' => $kb )
		);
	}

	/**
	 * Apply offer code to latest pending purchase.
	 *
	 * @param array<string, mixed> $ctx Context.
	 * @param int                  $offer_id Offer id.
	 */
	public static function handle_callback_apply( array $ctx, $offer_id ) {
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$user     = $ctx['user'] ?? null;
		$oid      = (int) $offer_id;
		if ( ! $user || $oid < 1 ) {
			return;
		}
		$offer = SimpleVPBot_Model_Marketing_Offer::find( $oid );
		if ( ! $offer || (int) $offer->svp_user_id !== (int) $user->id ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.marketing.offer_not_found', $user ) );
			return;
		}
		$code_row = SimpleVPBot_Model_Discount_Code::find( (int) $offer->discount_code_id );
		if ( ! $code_row ) {
			return;
		}
		global $wpdb;
		$tx_t = SimpleVPBot_Model_Transaction::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$tx = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$tx_t} WHERE user_id = %d AND status = 'pending' AND type = 'purchase' ORDER BY id DESC LIMIT 1",
				(int) $user->id
			)
		);
		if ( ! $tx ) {
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				SimpleVPBot_Texts::get_for_user( 'msg.marketing.no_pending_purchase', $user ),
				array( 'reply_markup' => class_exists( 'SimpleVPBot_Keyboards' ) ? SimpleVPBot_Keyboards::user_main_reply( $user ) : null )
			);
			return;
		}
		$res = SimpleVPBot_Discount_Service::apply_to_pending_transaction( (int) $tx->id, (string) $code_row->code );
		if ( empty( $res['ok'] ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.marketing.apply_failed', $user ) );
			return;
		}
		$meta = json_decode( (string) $tx->meta_json, true );
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}
		$meta['marketing_offer_id'] = $oid;
		SimpleVPBot_Model_Transaction::update( (int) $tx->id, array( 'meta_json' => wp_json_encode( $meta ) ) );
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			SimpleVPBot_Texts::get_for_user( 'msg.marketing.apply_ok', $user )
		);
	}

	/**
	 * On approved tx, mark offer converted from meta.
	 *
	 * @param object $tx Transaction row.
	 */
	public static function maybe_mark_converted_from_tx( $tx ) {
		if ( ! $tx || ! is_object( $tx ) ) {
			return;
		}
		$meta = json_decode( (string) ( $tx->meta_json ?? '{}' ), true );
		if ( ! is_array( $meta ) || empty( $meta['marketing_offer_id'] ) ) {
			if ( ! empty( $meta['discount_code_id'] ) && class_exists( 'SimpleVPBot_Model_Marketing_Offer' ) ) {
				$cr = SimpleVPBot_Model_Discount_Code::find( (int) $meta['discount_code_id'] );
				if ( $cr ) {
					$offer = SimpleVPBot_Model_Marketing_Offer::find_by_discount_code( (string) $cr->code );
					if ( $offer && 'converted' !== (string) $offer->status ) {
						SimpleVPBot_Model_Marketing_Offer::mark_converted( (int) $offer->id, (int) $tx->id );
					}
				}
			}
			return;
		}
		SimpleVPBot_Model_Marketing_Offer::mark_converted( (int) $meta['marketing_offer_id'], (int) $tx->id );
	}
}
