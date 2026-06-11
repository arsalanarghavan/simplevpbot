<?php
/**
 * Bot admin — marketing section handlers.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Handler_Admin_Marketing
 */
class SimpleVPBot_Handler_Admin_Marketing {

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param object $user     User.
	 * @param string $tab_key  Tab.
	 * @return bool
	 */
	public static function open_tab( $platform, $chat_id, $user, $tab_key ) {
		$tab_key = sanitize_key( (string) $tab_key );
		switch ( $tab_key ) {
			case 'referral':
				return self::open_referral( $platform, $chat_id, $user );
			case 'marketing_lifecycle':
				return self::open_lifecycle( $platform, $chat_id, $user );
			case 'discounts':
				return self::open_discounts( $platform, $chat_id, $user );
		}
		return false;
	}

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param object $user     User.
	 * @return bool
	 */
	private static function open_referral( $platform, $chat_id, $user ) {
		if ( ! SimpleVPBot_Reseller_Permission_Gate::may_call_op( (int) $user->id, 'referral_manage' ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_permission', $user ) );
			return true;
		}
		$s    = SimpleVPBot_Settings::all();
		$body = SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.tutorial.referral', $user );
		$body .= "\n\n";
		$body .= SimpleVPBot_Bot_Admin_Texts::msg(
			'msg.admin.referral_status',
			$user,
			array( 'state' => ! empty( $s['referral_enabled'] ) ? '✅' : '❌' )
		);
		$body .= "\n";
		$body .= SimpleVPBot_Bot_Admin_Texts::msg(
			'msg.admin.referral_percent',
			$user,
			array( 'percent' => (string) (int) ( $s['referral_percent'] ?? 0 ) )
		);
		$perm_actor = SimpleVPBot_Reseller_Permission_Gate::permission_actor_id( (int) $user->id );
		$link_tg    = class_exists( 'SimpleVPBot_Referral_Service' )
			? SimpleVPBot_Referral_Service::invite_link_for_platform( 'telegram', (int) $user->id, $perm_actor )
			: '';
		if ( '' !== $link_tg ) {
			$body .= "\n\n";
			$body .= SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.referral_invite_link', $user, array( 'url' => $link_tg ) );
		}
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			$body,
			array( 'reply_markup' => SimpleVPBot_Keyboards::admin_marketing_referral_reply( $user, $perm_actor < 1 ) )
		);
		return true;
	}

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param object $user     User.
	 * @return bool
	 */
	private static function open_lifecycle( $platform, $chat_id, $user ) {
		if ( ! SimpleVPBot_Reseller_Permission_Gate::may_call_op( (int) $user->id, 'marketing_lifecycle' ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_permission', $user ) );
			return true;
		}
		$perm_actor = SimpleVPBot_Reseller_Permission_Gate::permission_actor_id( (int) $user->id );
		$owner      = $perm_actor > 0 ? $perm_actor : 0;
		$site_admin = $perm_actor < 1;
		$rules      = class_exists( 'SimpleVPBot_Model_Marketing_Rule' )
			? SimpleVPBot_Model_Marketing_Rule::list_for_dashboard( $owner, $site_admin )
			: array();
		$body = SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.tutorial.marketing_lifecycle', $user );
		$body .= "\n\n";
		if ( empty( $rules ) ) {
			$body .= SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.marketing_rules_empty', $user );
		} else {
			foreach ( $rules as $r ) {
				$p = SimpleVPBot_Model_Marketing_Rule::to_payload( $r );
				$body .= SimpleVPBot_Bot_Admin_Texts::msg(
					'msg.admin.lifecycle_rule_line',
					$user,
					array(
						'id'       => (string) (int) ( $p['id'] ?? 0 ),
						'segment'  => (string) ( $p['segment_key'] ?? '' ),
						'enabled'  => ! empty( $p['enabled'] ) ? '✅' : '⏸',
						'cooldown' => (string) (int) ( $p['cooldown_days'] ?? 0 ),
						'after'    => (string) (int) ( $p['after_days'] ?? 0 ),
					)
				) . "\n";
			}
		}
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			$body,
			array( 'reply_markup' => SimpleVPBot_Keyboards::admin_marketing_lifecycle_reply( $user ) )
		);
		return true;
	}

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param object $user     User.
	 * @return bool
	 */
	private static function open_discounts( $platform, $chat_id, $user ) {
		if ( ! SimpleVPBot_Reseller_Permission_Gate::may_call_op( (int) $user->id, 'discount_manage' ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_permission', $user ) );
			return true;
		}
		$perm_actor = SimpleVPBot_Reseller_Permission_Gate::permission_actor_id( (int) $user->id );
		$owner      = $perm_actor > 0 ? $perm_actor : null;
		$rows       = class_exists( 'SimpleVPBot_Model_Discount_Code' )
			? SimpleVPBot_Model_Discount_Code::all_ordered_for_owner( $owner )
			: array();
		$body = SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.tutorial.discounts', $user );
		$body .= "\n\n";
		if ( empty( $rows ) ) {
			$body .= SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.discounts_empty', $user );
		} else {
			$n = 0;
			foreach ( $rows as $row ) {
				if ( $n >= 12 ) {
					$body .= "…\n";
					break;
				}
				$body .= '• ' . (string) ( $row->code ?? '' ) . ' — ' . (string) ( $row->discount_type ?? '' );
				$body .= ' ' . (string) ( $row->discount_value ?? '' );
				$body .= ' ' . ( ! empty( $row->active ) ? '✅' : '⏸' ) . "\n";
				++$n;
			}
		}
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			$body,
			array( 'reply_markup' => SimpleVPBot_Keyboards::admin_marketing_discounts_reply( $user ) )
		);
		return true;
	}

	/**
	 * Handle marketing submenu actions.
	 *
	 * @param array<string, mixed> $ctx Context.
	 * @return bool
	 */
	public static function route_text( array $ctx ) {
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$user     = $ctx['user'];
		$text     = trim( (string) $ctx['text'] );

		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.discount_new', $user ) ) {
			if ( ! SimpleVPBot_Reseller_Permission_Gate::may_call_op( (int) $user->id, 'discount_manage' ) ) {
				return false;
			}
			SimpleVPBot_State::set( (int) $user->id, 'admin_discount_code', array( 'step' => 'code' ) );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_discount_code', $user )
			);
			return true;
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.discount_delete', $user ) ) {
			if ( ! SimpleVPBot_Reseller_Permission_Gate::may_call_op( (int) $user->id, 'discount_manage' ) ) {
				return false;
			}
			SimpleVPBot_State::set( (int) $user->id, 'admin_discount_delete', array() );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_discount_delete', $user )
			);
			return true;
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.discount_toggle', $user ) ) {
			if ( ! SimpleVPBot_Reseller_Permission_Gate::may_call_op( (int) $user->id, 'discount_manage' ) ) {
				return false;
			}
			SimpleVPBot_State::set( (int) $user->id, 'admin_discount_toggle', array() );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_discount_toggle', $user )
			);
			return true;
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.discount_edit', $user ) ) {
			if ( ! SimpleVPBot_Reseller_Permission_Gate::may_call_op( (int) $user->id, 'discount_manage' ) ) {
				return false;
			}
			SimpleVPBot_State::set( (int) $user->id, 'admin_discount_edit', array( 'step' => 'code' ) );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_discount_edit_code', $user )
			);
			return true;
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.lifecycle_toggle', $user ) ) {
			if ( ! SimpleVPBot_Reseller_Permission_Gate::may_call_op( (int) $user->id, 'marketing_lifecycle' ) ) {
				return false;
			}
			SimpleVPBot_State::set( (int) $user->id, 'admin_lifecycle_toggle', array() );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_lifecycle_rule_id', $user )
			);
			return true;
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.lifecycle_new', $user ) ) {
			if ( SimpleVPBot_Reseller_Permission_Gate::permission_actor_id( (int) $user->id ) > 0 ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_permission', $user ) );
				return true;
			}
			if ( ! SimpleVPBot_Reseller_Permission_Gate::may_call_op( (int) $user->id, 'marketing_lifecycle' ) ) {
				return false;
			}
			SimpleVPBot_State::set( (int) $user->id, 'admin_lifecycle_create', array( 'step' => 'segment' ) );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_lifecycle_segment', $user ) );
			return true;
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.lifecycle_edit', $user ) ) {
			if ( SimpleVPBot_Reseller_Permission_Gate::permission_actor_id( (int) $user->id ) > 0 ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_permission', $user ) );
				return true;
			}
			if ( ! SimpleVPBot_Reseller_Permission_Gate::may_call_op( (int) $user->id, 'marketing_lifecycle' ) ) {
				return false;
			}
			SimpleVPBot_State::set( (int) $user->id, 'admin_lifecycle_edit', array( 'step' => 'rule_id' ) );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_lifecycle_rule_id', $user ) );
			return true;
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.lifecycle_delete', $user ) ) {
			if ( SimpleVPBot_Reseller_Permission_Gate::permission_actor_id( (int) $user->id ) > 0 ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_permission', $user ) );
				return true;
			}
			if ( ! SimpleVPBot_Reseller_Permission_Gate::may_call_op( (int) $user->id, 'marketing_lifecycle' ) ) {
				return false;
			}
			SimpleVPBot_State::set( (int) $user->id, 'admin_lifecycle_delete', array() );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_lifecycle_delete', $user ) );
			return true;
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.lifecycle_run', $user ) ) {
			if ( SimpleVPBot_Reseller_Permission_Gate::permission_actor_id( (int) $user->id ) > 0 ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_permission', $user ) );
				return true;
			}
			if ( ! SimpleVPBot_Reseller_Permission_Gate::may_call_op( (int) $user->id, 'marketing_lifecycle' ) ) {
				return false;
			}
			SimpleVPBot_State::set( (int) $user->id, 'admin_lifecycle_run', array() );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_lifecycle_run', $user ) );
			return true;
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.referral_toggle', $user ) ) {
			if ( SimpleVPBot_Reseller_Permission_Gate::permission_actor_id( (int) $user->id ) > 0 ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_permission', $user ) );
				return true;
			}
			if ( ! SimpleVPBot_Reseller_Permission_Gate::may_call_op( (int) $user->id, 'referral_manage' ) ) {
				return false;
			}
			$s       = SimpleVPBot_Settings::all();
			$enabled = empty( $s['referral_enabled'] );
			SimpleVPBot_Settings::update( array( 'referral_enabled' => $enabled ) );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				SimpleVPBot_Bot_Admin_Texts::msg(
					'msg.admin.referral_toggled',
					$user,
					array( 'state' => $enabled ? '✅' : '❌' )
				)
			);
			return true;
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.referral_percent', $user ) ) {
			if ( SimpleVPBot_Reseller_Permission_Gate::permission_actor_id( (int) $user->id ) > 0 ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_permission', $user ) );
				return true;
			}
			if ( ! SimpleVPBot_Reseller_Permission_Gate::may_call_op( (int) $user->id, 'referral_manage' ) ) {
				return false;
			}
			SimpleVPBot_State::set( (int) $user->id, 'admin_referral_percent', array() );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_referral_percent', $user )
			);
			return true;
		}
		return false;
	}

	/**
	 * Wizard: create discount code, delete, lifecycle toggle, referral percent.
	 *
	 * @param array<string, mixed> $ctx Context.
	 * @return bool
	 */
	public static function route_state( array $ctx ) {
		$user = $ctx['user'];
		$st   = (string) $user->state;
		if ( 'admin_discount_code' === $st ) {
			return self::route_discount_create( $ctx );
		}
		if ( 'admin_discount_delete' === $st ) {
			return self::route_discount_delete( $ctx );
		}
		if ( 'admin_discount_toggle' === $st ) {
			return self::route_discount_toggle( $ctx );
		}
		if ( 'admin_discount_edit' === $st ) {
			return self::route_discount_edit( $ctx );
		}
		if ( 'admin_lifecycle_toggle' === $st ) {
			return self::route_lifecycle_toggle( $ctx );
		}
		if ( 'admin_lifecycle_create' === $st ) {
			return self::route_lifecycle_create( $ctx );
		}
		if ( 'admin_lifecycle_edit' === $st ) {
			return self::route_lifecycle_edit( $ctx );
		}
		if ( 'admin_lifecycle_delete' === $st ) {
			return self::route_lifecycle_delete( $ctx );
		}
		if ( 'admin_lifecycle_run' === $st ) {
			return self::route_lifecycle_run( $ctx );
		}
		if ( 'admin_referral_percent' === $st ) {
			return self::route_referral_percent( $ctx );
		}
		return false;
	}

	/**
	 * @param array<string, mixed> $ctx Context.
	 * @return bool
	 */
	private static function route_discount_create( array $ctx ) {
		$user     = $ctx['user'];
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$text     = trim( (string) $ctx['text'] );
		$data     = SimpleVPBot_State::get( (int) $user->id );
		$data     = is_array( $data ) ? $data : array();
		$step     = (string) ( $data['step'] ?? '' );
		$handled  = self::route_discount_allow_minmax( $ctx, 'admin_discount_code', 'msg.admin.discount_created' );
		if ( null !== $handled ) {
			return $handled;
		}

		if ( 'code' === $step ) {
			$code = sanitize_text_field( $text );
			if ( strlen( $code ) < 2 ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.discount_code_invalid', $user ) );
				return true;
			}
			$data['code']   = $code;
			$data['step']   = 'type';
			$data['active'] = 1;
			SimpleVPBot_State::set( (int) $user->id, 'admin_discount_code', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_discount_type', $user ) );
			return true;
		}
		if ( 'type' === $step ) {
			$raw = strtolower( sanitize_key( $text ) );
			if ( in_array( $raw, array( 'fixed', 'fixed_toman', '2', 'toman' ), true ) ) {
				$data['type'] = 'fixed_toman';
			} elseif ( in_array( $raw, array( 'percent', '1' ), true ) ) {
				$data['type'] = 'percent';
			} else {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.discount_type_invalid', $user ) );
				return true;
			}
			$data['step'] = 'value';
			SimpleVPBot_State::set( (int) $user->id, 'admin_discount_code', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_discount_value', $user ) );
			return true;
		}
		if ( 'value' === $step ) {
			$type = (string) ( $data['type'] ?? 'percent' );
			$val  = (float) str_replace( ',', '.', SimpleVPBot_Bot_Runtime::normalize_digits( $text ) );
			if ( $val <= 0 || ( 'percent' === $type && $val > 100 ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.discount_value_invalid', $user ) );
				return true;
			}
			$data['value'] = $val;
			$data['step']  = 'max_uses';
			SimpleVPBot_State::set( (int) $user->id, 'admin_discount_code', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_discount_max_uses', $user ) );
			return true;
		}
		if ( 'max_uses' === $step ) {
			$raw = SimpleVPBot_Bot_Runtime::normalize_digits( $text );
			if ( '' === trim( $raw ) || '-' === $text ) {
				$data['max_uses'] = '';
			} else {
				$data['max_uses'] = max( 0, (int) $raw );
			}
			$data['step'] = 'valid_until';
			SimpleVPBot_State::set( (int) $user->id, 'admin_discount_code', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_discount_valid_until', $user ) );
			return true;
		}
		if ( 'valid_until' === $step ) {
			$vu = trim( $text );
			if ( '-' === $vu || '' === $vu ) {
				$data['valid_until'] = '';
			} elseif ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $vu ) ) {
				$data['valid_until'] = $vu;
			} else {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.discount_date_invalid', $user ) );
				return true;
			}
			if ( class_exists( 'SimpleVPBot_Bot_Admin_Plan_Picker' ) ) {
				SimpleVPBot_Bot_Admin_Plan_Picker::begin( $platform, $chat_id, $user, 'admin_discount_code', $data );
				return true;
			}
			$data['step'] = 'plan_ids';
			SimpleVPBot_State::set( (int) $user->id, 'admin_discount_code', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_discount_plan_ids', $user ) );
			return true;
		}
		if ( 'plan_ids' === $step ) {
			if ( '-' !== $text && '' !== trim( $text ) ) {
				$ids = array();
				foreach ( preg_split( '/[\s,]+/', $text ) as $part ) {
					$n = (int) SimpleVPBot_Bot_Runtime::normalize_digits( (string) $part );
					if ( $n > 0 ) {
						$ids[] = $n;
					}
				}
				$data['allowed_plan_ids'] = array_values( array_unique( $ids ) );
			} else {
				$data['allowed_plan_ids'] = array();
			}
			return self::advance_discount_after_plans( $platform, $chat_id, $user, $data, 'admin_discount_code' );
		}
		return false;
	}

	/**
	 * @param array<string, mixed> $ctx Context.
	 * @return bool
	 */
	private static function route_discount_delete( array $ctx ) {
		$user     = $ctx['user'];
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$code     = sanitize_text_field( trim( (string) $ctx['text'] ) );
		if ( strlen( $code ) < 2 ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.discount_code_invalid', $user ) );
			return true;
		}
		$row = self::find_discount_row( $user, $code );
		if ( ! $row ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.discount_not_found', $user ) );
			return true;
		}
		if ( ! class_exists( 'SimpleVPBot_Bot_Admin_Mutate' ) ) {
			SimpleVPBot_State::clear( (int) $user->id );
			return true;
		}
		$result = SimpleVPBot_Bot_Admin_Mutate::apply_for_user(
			(int) $user->id,
			'discount_delete',
			array( 'svpc_delete_id' => (int) $row->id )
		);
		SimpleVPBot_State::clear( (int) $user->id );
		if ( ! empty( $result['ok'] ) ) {
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.discount_deleted', $user, array( 'code' => $code ) )
			);
		} else {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Mutate::result_message( $user, $result ) );
		}
		return true;
	}

	/**
	 * @param array<string, mixed> $ctx Context.
	 * @return bool
	 */
	private static function route_discount_toggle( array $ctx ) {
		$user     = $ctx['user'];
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$code     = sanitize_text_field( trim( (string) $ctx['text'] ) );
		if ( strlen( $code ) < 2 ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.discount_code_invalid', $user ) );
			return true;
		}
		$row = self::find_discount_row( $user, $code );
		if ( ! $row ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.discount_not_found', $user ) );
			return true;
		}
		$new_active = empty( $row->active ) ? 1 : 0;
		$data       = self::discount_row_to_wizard( $row );
		$data['active'] = $new_active;
		if ( ! class_exists( 'SimpleVPBot_Bot_Admin_Mutate' ) ) {
			SimpleVPBot_State::clear( (int) $user->id );
			return true;
		}
		$post   = SimpleVPBot_Bot_Admin_Mutate::discount_post_from_wizard( $data );
		$result = SimpleVPBot_Bot_Admin_Mutate::apply_for_user( (int) $user->id, 'discount_save', $post );
		SimpleVPBot_State::clear( (int) $user->id );
		if ( ! empty( $result['ok'] ) ) {
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				SimpleVPBot_Bot_Admin_Texts::msg(
					'msg.admin.discount_toggled',
					$user,
					array(
						'code'  => (string) ( $row->code ?? $code ),
						'state' => $new_active ? '✅' : '⏸',
					)
				)
			);
		} else {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Mutate::result_message( $user, $result ) );
		}
		return true;
	}

	/**
	 * @param array<string, mixed> $ctx Context.
	 * @return bool
	 */
	private static function route_discount_edit( array $ctx ) {
		$user     = $ctx['user'];
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$text     = trim( (string) $ctx['text'] );
		$data     = SimpleVPBot_State::get( (int) $user->id );
		$data     = is_array( $data ) ? $data : array();
		$step     = (string) ( $data['step'] ?? '' );
		$handled  = self::route_discount_allow_minmax( $ctx, 'admin_discount_edit', 'msg.admin.discount_updated' );
		if ( null !== $handled ) {
			return $handled;
		}

		if ( 'code' === $step ) {
			$code = sanitize_text_field( $text );
			if ( strlen( $code ) < 2 ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.discount_code_invalid', $user ) );
				return true;
			}
			$row = self::find_discount_row( $user, $code );
			if ( ! $row ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.discount_not_found', $user ) );
				return true;
			}
			$data = self::discount_row_to_wizard( $row );
			$data['step'] = 'value';
			SimpleVPBot_State::set( (int) $user->id, 'admin_discount_edit', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_discount_value', $user ) );
			return true;
		}
		if ( 'value' === $step ) {
			$type = (string) ( $data['type'] ?? 'percent' );
			$val  = (float) str_replace( ',', '.', SimpleVPBot_Bot_Runtime::normalize_digits( $text ) );
			if ( $val <= 0 || ( 'percent' === $type && $val > 100 ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.discount_value_invalid', $user ) );
				return true;
			}
			$data['value'] = $val;
			$data['step']  = 'max_uses';
			SimpleVPBot_State::set( (int) $user->id, 'admin_discount_edit', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_discount_max_uses', $user ) );
			return true;
		}
		if ( 'max_uses' === $step ) {
			$raw = SimpleVPBot_Bot_Runtime::normalize_digits( $text );
			if ( '' === trim( $raw ) || '-' === $text ) {
				$data['max_uses'] = '';
			} else {
				$data['max_uses'] = max( 0, (int) $raw );
			}
			$data['step'] = 'valid_until';
			SimpleVPBot_State::set( (int) $user->id, 'admin_discount_edit', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_discount_valid_until', $user ) );
			return true;
		}
		if ( 'valid_until' === $step ) {
			$vu = trim( $text );
			if ( '-' === $vu || '' === $vu ) {
				$data['valid_until'] = '';
			} elseif ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $vu ) ) {
				$data['valid_until'] = $vu;
			} else {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.discount_date_invalid', $user ) );
				return true;
			}
			if ( class_exists( 'SimpleVPBot_Bot_Admin_Plan_Picker' ) ) {
				SimpleVPBot_Bot_Admin_Plan_Picker::begin( $platform, $chat_id, $user, 'admin_discount_edit', $data );
				return true;
			}
			$data['step'] = 'plan_ids';
			SimpleVPBot_State::set( (int) $user->id, 'admin_discount_edit', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_discount_plan_ids', $user ) );
			return true;
		}
		if ( 'plan_ids' === $step ) {
			if ( '-' !== $text && '' !== trim( $text ) ) {
				$ids = array();
				foreach ( preg_split( '/[\s,]+/', $text ) as $part ) {
					$n = (int) SimpleVPBot_Bot_Runtime::normalize_digits( (string) $part );
					if ( $n > 0 ) {
						$ids[] = $n;
					}
				}
				$data['allowed_plan_ids'] = array_values( array_unique( $ids ) );
			} else {
				$data['allowed_plan_ids'] = array();
			}
			return self::advance_discount_after_plans( $platform, $chat_id, $user, $data, 'admin_discount_edit' );
		}
		return false;
	}

	/**
	 * @param object $user User.
	 * @param string $code Code.
	 * @return object|null
	 */
	private static function find_discount_row( $user, $code ) {
		if ( ! class_exists( 'SimpleVPBot_Model_Discount_Code' ) ) {
			return null;
		}
		$perm_actor = SimpleVPBot_Reseller_Permission_Gate::permission_actor_id( (int) $user->id );
		$owner      = $perm_actor > 0 ? $perm_actor : null;
		$row        = SimpleVPBot_Model_Discount_Code::find_by_code( $code, $owner );
		return ( $row && ! empty( $row->id ) ) ? $row : null;
	}

	/**
	 * @param object $row Discount row.
	 * @return array<string, mixed>
	 */
	private static function discount_row_to_wizard( $row ) {
		$plan_ids = array();
		if ( class_exists( 'SimpleVPBot_Model_Discount_Code' ) ) {
			$plan_ids = SimpleVPBot_Model_Discount_Code::parse_allowed_plan_ids( $row );
		}
		return array(
			'id'               => (int) ( $row->id ?? 0 ),
			'code'             => (string) ( $row->code ?? '' ),
			'type'             => (string) ( $row->discount_type ?? 'percent' ),
			'value'            => (float) ( $row->discount_value ?? 0 ),
			'active'           => ! empty( $row->active ) ? 1 : 0,
			'max_uses'         => null === ( $row->max_uses ?? null ) ? '' : (int) $row->max_uses,
			'valid_until'      => (string) ( $row->valid_until ?? '' ),
			'allowed_plan_ids' => is_array( $plan_ids ) ? $plan_ids : array(),
			'allow_new'        => ! empty( $row->allow_new_purchase ) ? 1 : 0,
			'allow_renew'      => ! empty( $row->allow_renew_same ) ? 1 : 0,
			'allow_vol'        => ! empty( $row->allow_add_volume ) ? 1 : 0,
			'allow_users'      => ! empty( $row->allow_add_user_slots ) ? 1 : 0,
			'min_order'        => (string) ( $row->min_order_amount ?? '' ),
			'max_order'        => (string) ( $row->max_order_amount ?? '' ),
		);
	}

	/**
	 * After plan picker / plan_ids — collect allow flags and min/max before save.
	 *
	 * @param string               $platform Platform.
	 * @param int                  $chat_id  Chat id.
	 * @param object               $user     User.
	 * @param array<string, mixed> $data     Wizard data.
	 * @param string               $state    admin_discount_code|admin_discount_edit.
	 * @return bool
	 */
	public static function advance_discount_after_plans( $platform, $chat_id, $user, array $data, $state ) {
		$data['step'] = 'allow_flags';
		SimpleVPBot_State::set( (int) $user->id, $state, $data );
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_discount_allow_flags', $user ) );
		return true;
	}

	/**
	 * @param array<string, mixed> $data Wizard data.
	 * @param string               $text Input.
	 * @return bool Parsed ok.
	 */
	private static function parse_discount_allow_flags( array &$data, $text ) {
		$text = trim( (string) $text );
		if ( '-' === $text ) {
			return true;
		}
		$parts = preg_split( '/[\s,]+/', $text );
		if ( ! is_array( $parts ) || count( $parts ) < 4 ) {
			return false;
		}
		$data['allow_new']   = ! empty( $parts[0] ) && '0' !== (string) $parts[0] ? 1 : 0;
		$data['allow_renew'] = ! empty( $parts[1] ) && '0' !== (string) $parts[1] ? 1 : 0;
		$data['allow_vol']   = ! empty( $parts[2] ) && '0' !== (string) $parts[2] ? 1 : 0;
		$data['allow_users'] = ! empty( $parts[3] ) && '0' !== (string) $parts[3] ? 1 : 0;
		return true;
	}

	/**
	 * @param array<string, mixed> $data Wizard data.
	 * @param string               $text Input.
	 * @return bool Parsed ok.
	 */
	private static function parse_discount_min_max( array &$data, $text ) {
		$text = trim( (string) $text );
		if ( '-' === $text || '' === $text ) {
			return true;
		}
		$parts = array_map( 'trim', explode( ',', $text, 2 ) );
		if ( count( $parts ) < 2 ) {
			return false;
		}
		$data['min_order'] = '' === $parts[0] || '-' === $parts[0] ? '' : (string) max( 0, (float) str_replace( ',', '.', SimpleVPBot_Bot_Runtime::normalize_digits( $parts[0] ) ) );
		$data['max_order'] = '' === $parts[1] || '-' === $parts[1] ? '' : (string) max( 0, (float) str_replace( ',', '.', SimpleVPBot_Bot_Runtime::normalize_digits( $parts[1] ) ) );
		return true;
	}

	/**
	 * Shared allow/min-max steps for create and edit wizards.
	 *
	 * @param array<string, mixed> $ctx Context.
	 * @param string               $state State key.
	 * @param string               $ok_key Success message key.
	 * @return bool|null null if step not handled.
	 */
	private static function route_discount_allow_minmax( array $ctx, $state, $ok_key ) {
		$user     = $ctx['user'];
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$text     = trim( (string) $ctx['text'] );
		$data     = SimpleVPBot_State::get( (int) $user->id );
		$data     = is_array( $data ) ? $data : array();
		$step     = (string) ( $data['step'] ?? '' );
		if ( 'allow_flags' === $step ) {
			if ( ! self::parse_discount_allow_flags( $data, $text ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.discount_allow_invalid', $user ) );
				return true;
			}
			$data['step'] = 'min_max';
			SimpleVPBot_State::set( (int) $user->id, $state, $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_discount_min_max', $user ) );
			return true;
		}
		if ( 'min_max' === $step ) {
			if ( ! self::parse_discount_min_max( $data, $text ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.discount_min_max_invalid', $user ) );
				return true;
			}
			return self::finish_discount_mutate( $platform, $chat_id, $user, $data, $ok_key );
		}
		return null;
	}

	/**
	 * @param string               $platform Platform.
	 * @param int                  $chat_id  Chat id.
	 * @param object               $user     User.
	 * @param array<string, mixed> $data     Wizard data.
	 * @param string               $ok_key   Success message key.
	 * @return bool
	 */
	private static function finish_discount_mutate( $platform, $chat_id, $user, array $data, $ok_key ) {
		if ( ! class_exists( 'SimpleVPBot_Bot_Admin_Mutate' ) ) {
			SimpleVPBot_State::clear( (int) $user->id );
			return true;
		}
		$post   = SimpleVPBot_Bot_Admin_Mutate::discount_post_from_wizard( $data );
		$result = SimpleVPBot_Bot_Admin_Mutate::apply_for_user( (int) $user->id, 'discount_save', $post );
		SimpleVPBot_State::clear( (int) $user->id );
		if ( ! empty( $result['ok'] ) ) {
			$args = array( 'code' => (string) ( $data['code'] ?? '' ) );
			if ( 'msg.admin.discount_updated' === $ok_key ) {
				$args['percent'] = (string) ( $data['value'] ?? '' );
			}
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( $ok_key, $user, $args ) );
		} else {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Mutate::result_message( $user, $result ) );
		}
		return true;
	}

	/**
	 * Called from plan picker after selection.
	 *
	 * @param string               $platform Platform.
	 * @param int                  $chat_id  Chat id.
	 * @param object               $user     User.
	 * @param array<string, mixed> $data     Wizard data.
	 * @param string               $ok_key   Success key.
	 * @return bool
	 */
	public static function finish_discount_from_picker( $platform, $chat_id, $user, array $data, $ok_key ) {
		$state = ! empty( $data['id'] ) ? 'admin_discount_edit' : 'admin_discount_code';
		return self::advance_discount_after_plans( $platform, $chat_id, $user, $data, $state );
	}

	/**
	 * @param array<string, mixed> $ctx Context.
	 * @return bool
	 */
	private static function route_lifecycle_toggle( array $ctx ) {
		$user     = $ctx['user'];
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$rule_id  = (int) SimpleVPBot_Bot_Runtime::normalize_digits( trim( (string) $ctx['text'] ) );
		if ( $rule_id < 1 || ! class_exists( 'SimpleVPBot_Model_Marketing_Rule' ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.lifecycle_rule_invalid', $user ) );
			return true;
		}
		$row = SimpleVPBot_Model_Marketing_Rule::find( $rule_id );
		if ( ! $row ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.lifecycle_rule_not_found', $user ) );
			return true;
		}
		$perm_actor = SimpleVPBot_Reseller_Permission_Gate::permission_actor_id( (int) $user->id );
		$owner      = (int) ( $row->owner_svp_user_id ?? 0 );
		if ( $perm_actor > 0 && $owner !== $perm_actor ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_permission', $user ) );
			return true;
		}
		if ( $perm_actor < 1 && $owner > 0 ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_permission', $user ) );
			return true;
		}
		$new_enabled = empty( $row->enabled ) ? 1 : 0;
		$data        = self::lifecycle_row_to_wizard( $row );
		$data['enabled'] = $new_enabled;
		if ( ! class_exists( 'SimpleVPBot_Bot_Admin_Mutate' ) ) {
			SimpleVPBot_State::clear( (int) $user->id );
			return true;
		}
		$result = SimpleVPBot_Bot_Admin_Mutate::apply_for_user(
			(int) $user->id,
			'marketing_rule_save',
			self::lifecycle_wizard_to_post( $data, $rule_id )
		);
		SimpleVPBot_State::clear( (int) $user->id );
		if ( ! empty( $result['ok'] ) ) {
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				SimpleVPBot_Bot_Admin_Texts::msg(
					'msg.admin.lifecycle_toggled',
					$user,
					array(
						'id'    => (string) $rule_id,
						'state' => $new_enabled ? '✅' : '⏸',
					)
				)
			);
		} else {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Mutate::result_message( $user, $result ) );
		}
		return true;
	}

	/**
	 * @param array<string, mixed> $ctx Context.
	 * @return bool
	 */
	private static function route_lifecycle_create( array $ctx ) {
		$user     = $ctx['user'];
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$text     = trim( (string) $ctx['text'] );
		$data     = SimpleVPBot_State::get( (int) $user->id );
		$data     = is_array( $data ) ? $data : array();
		$step     = (string) ( $data['step'] ?? '' );

		if ( 'segment' === $step ) {
			$seg = class_exists( 'SimpleVPBot_Model_Marketing_Rule' )
				? SimpleVPBot_Model_Marketing_Rule::sanitize_segment( $text )
				: '';
			if ( '' === $seg ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.lifecycle_segment_invalid', $user ) );
				return true;
			}
			$data['segment_key'] = $seg;
			$data['step']        = 'seg_param';
			SimpleVPBot_State::set( (int) $user->id, 'admin_lifecycle_create', $data );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_lifecycle_seg_param', $user, array( 'field' => self::lifecycle_segment_field_label( $seg ) ) )
			);
			return true;
		}
		if ( 'seg_param' === $step ) {
			$data[ self::lifecycle_segment_field_key( (string) ( $data['segment_key'] ?? '' ) ) ] = max( 0, (int) SimpleVPBot_Bot_Runtime::normalize_digits( $text ) );
			$data['step'] = 'cooldown';
			SimpleVPBot_State::set( (int) $user->id, 'admin_lifecycle_create', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_lifecycle_cooldown', $user ) );
			return true;
		}
		if ( 'cooldown' === $step ) {
			$data['cooldown_days'] = max( 1, (int) SimpleVPBot_Bot_Runtime::normalize_digits( $text ) );
			$data['step']          = 'priority';
			SimpleVPBot_State::set( (int) $user->id, 'admin_lifecycle_create', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_lifecycle_priority', $user, array(), 'اولویت (عدد، پیش‌فرض ۱۰):' ) );
			return true;
		}
		if ( 'priority' === $step ) {
			$data['priority'] = max( 0, (int) SimpleVPBot_Bot_Runtime::normalize_digits( $text ) );
			if ( $data['priority'] < 1 ) {
				$data['priority'] = 10;
			}
			$data['step'] = 'discount_type';
			SimpleVPBot_State::set( (int) $user->id, 'admin_lifecycle_create', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_discount_type', $user ) );
			return true;
		}
		if ( 'discount_type' === $step ) {
			$raw = strtolower( sanitize_key( $text ) );
			$data['discount_type'] = in_array( $raw, array( 'fixed', 'fixed_toman', 'toman' ), true ) ? 'fixed_toman' : 'percent';
			$data['step']          = 'discount';
			SimpleVPBot_State::set( (int) $user->id, 'admin_lifecycle_create', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_lifecycle_discount', $user ) );
			return true;
		}
		if ( 'discount' === $step ) {
			$val = (float) str_replace( ',', '.', SimpleVPBot_Bot_Runtime::normalize_digits( $text ) );
			if ( $val < 0 || ( 'percent' === ( $data['discount_type'] ?? 'percent' ) && $val > 100 ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.discount_value_invalid', $user ) );
				return true;
			}
			$data['discount_value'] = $val;
			$data['step']           = 'max_discount';
			SimpleVPBot_State::set( (int) $user->id, 'admin_lifecycle_create', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_lifecycle_max_discount', $user, array(), 'حداکثر تخفیف (تومان، - برای بدون سقف):' ) );
			return true;
		}
		if ( 'max_discount' === $step ) {
			if ( '-' !== $text ) {
				$data['max_discount_toman'] = max( 0, (int) SimpleVPBot_Bot_Runtime::normalize_digits( $text ) );
			}
			$data['step'] = 'code_valid_days';
			SimpleVPBot_State::set( (int) $user->id, 'admin_lifecycle_create', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_lifecycle_code_days', $user, array(), 'اعتبار کد (روز):' ) );
			return true;
		}
		if ( 'code_valid_days' === $step ) {
			$data['code_valid_days'] = max( 1, (int) SimpleVPBot_Bot_Runtime::normalize_digits( $text ) );
			$data['step']            = 'max_uses';
			SimpleVPBot_State::set( (int) $user->id, 'admin_lifecycle_create', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_lifecycle_max_uses', $user, array(), 'حداکثر استفاده هر کاربر:' ) );
			return true;
		}
		if ( 'max_uses' === $step ) {
			$data['max_uses_per_user'] = max( 1, (int) SimpleVPBot_Bot_Runtime::normalize_digits( $text ) );
			$data['step']              = 'channels';
			SimpleVPBot_State::set( (int) $user->id, 'admin_lifecycle_create', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_lifecycle_channels', $user, array(), 'کانال‌ها: tg / bale / both' ) );
			return true;
		}
		if ( 'channels' === $step ) {
			$raw = strtolower( trim( $text ) );
			$data['channel_telegram'] = in_array( $raw, array( 'tg', 'telegram', 'both', '1' ), true ) ? 1 : 0;
			$data['channel_bale']     = in_array( $raw, array( 'bale', 'both', '2' ), true ) ? 1 : 0;
			if ( 'both' === $raw ) {
				$data['channel_telegram'] = 1;
				$data['channel_bale']     = 1;
			}
			$data['step'] = 'message';
			SimpleVPBot_State::set( (int) $user->id, 'admin_lifecycle_create', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_lifecycle_message', $user ) );
			return true;
		}
		if ( 'message' === $step ) {
			$data['message_body'] = ( '-' === $text ) ? '' : $text;
			return self::finish_lifecycle_mutate( $platform, $chat_id, $user, $data, 0 );
		}
		return false;
	}

	/**
	 * @param array<string, mixed> $ctx Context.
	 * @return bool
	 */
	private static function route_lifecycle_edit( array $ctx ) {
		$user     = $ctx['user'];
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$text     = trim( (string) $ctx['text'] );
		$data     = SimpleVPBot_State::get( (int) $user->id );
		$data     = is_array( $data ) ? $data : array();
		$step     = (string) ( $data['step'] ?? '' );

		if ( 'rule_id' === $step ) {
			$rule_id = (int) SimpleVPBot_Bot_Runtime::normalize_digits( $text );
			$row     = ( $rule_id > 0 && class_exists( 'SimpleVPBot_Model_Marketing_Rule' ) )
				? SimpleVPBot_Model_Marketing_Rule::find( $rule_id )
				: null;
			if ( ! $row ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.lifecycle_rule_not_found', $user ) );
				return true;
			}
			$data = self::lifecycle_row_to_wizard( $row );
			$data['step'] = 'segment';
			SimpleVPBot_State::set( (int) $user->id, 'admin_lifecycle_edit', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_lifecycle_segment', $user ) . self::lifecycle_keep_suffix( $user ) );
			return true;
		}
		if ( 'segment' === $step ) {
			if ( '-' !== $text ) {
				$seg = class_exists( 'SimpleVPBot_Model_Marketing_Rule' )
					? SimpleVPBot_Model_Marketing_Rule::sanitize_segment( $text )
					: '';
				if ( '' === $seg ) {
					SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.lifecycle_segment_invalid', $user ) );
					return true;
				}
				$data['segment_key'] = $seg;
				self::apply_lifecycle_segment_presets( $data, $seg );
			}
			$data['step'] = 'seg_param';
			SimpleVPBot_State::set( (int) $user->id, 'admin_lifecycle_edit', $data );
			$seg = (string) ( $data['segment_key'] ?? '' );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_lifecycle_seg_param', $user, array( 'field' => self::lifecycle_segment_field_label( $seg ) ) ) . self::lifecycle_keep_suffix( $user )
			);
			return true;
		}
		if ( 'seg_param' === $step ) {
			if ( '-' !== $text ) {
				$data[ self::lifecycle_segment_field_key( (string) ( $data['segment_key'] ?? '' ) ) ] = max( 0, (int) SimpleVPBot_Bot_Runtime::normalize_digits( $text ) );
			}
			$data['step'] = 'priority';
			SimpleVPBot_State::set( (int) $user->id, 'admin_lifecycle_edit', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_lifecycle_priority', $user ) . self::lifecycle_keep_suffix( $user ) );
			return true;
		}
		if ( 'priority' === $step ) {
			if ( '-' !== $text ) {
				$data['priority'] = max( 0, (int) SimpleVPBot_Bot_Runtime::normalize_digits( $text ) );
			}
			$data['step'] = 'cooldown';
			SimpleVPBot_State::set( (int) $user->id, 'admin_lifecycle_edit', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_lifecycle_cooldown', $user ) . self::lifecycle_keep_suffix( $user ) );
			return true;
		}
		if ( 'cooldown' === $step ) {
			if ( '-' !== $text ) {
				$data['cooldown_days'] = max( 1, (int) SimpleVPBot_Bot_Runtime::normalize_digits( $text ) );
			}
			$data['step'] = 'discount_type';
			SimpleVPBot_State::set( (int) $user->id, 'admin_lifecycle_edit', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_discount_type', $user ) . self::lifecycle_keep_suffix( $user ) );
			return true;
		}
		if ( 'discount_type' === $step ) {
			if ( '-' !== $text ) {
				$raw = strtolower( sanitize_key( $text ) );
				$data['discount_type'] = in_array( $raw, array( 'fixed', 'fixed_toman', 'toman' ), true ) ? 'fixed_toman' : 'percent';
			}
			$data['step'] = 'discount';
			SimpleVPBot_State::set( (int) $user->id, 'admin_lifecycle_edit', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_lifecycle_discount', $user ) );
			return true;
		}
		if ( 'discount' === $step ) {
			$val = (float) str_replace( ',', '.', SimpleVPBot_Bot_Runtime::normalize_digits( $text ) );
			if ( $val < 0 || $val > 100 ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.discount_value_invalid', $user ) );
				return true;
			}
			$data['discount_value'] = $val;
			$data['step']           = 'max_discount';
			SimpleVPBot_State::set( (int) $user->id, 'admin_lifecycle_edit', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_lifecycle_max_discount', $user ) . self::lifecycle_keep_suffix( $user ) );
			return true;
		}
		if ( 'max_discount' === $step ) {
			if ( '-' !== $text ) {
				$data['max_discount_toman'] = max( 0, (int) SimpleVPBot_Bot_Runtime::normalize_digits( $text ) );
			}
			$data['step'] = 'code_valid_days';
			SimpleVPBot_State::set( (int) $user->id, 'admin_lifecycle_edit', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_lifecycle_code_days', $user ) . self::lifecycle_keep_suffix( $user ) );
			return true;
		}
		if ( 'code_valid_days' === $step ) {
			if ( '-' !== $text ) {
				$data['code_valid_days'] = max( 1, (int) SimpleVPBot_Bot_Runtime::normalize_digits( $text ) );
			}
			$data['step'] = 'max_uses';
			SimpleVPBot_State::set( (int) $user->id, 'admin_lifecycle_edit', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_lifecycle_max_uses', $user ) . self::lifecycle_keep_suffix( $user ) );
			return true;
		}
		if ( 'max_uses' === $step ) {
			if ( '-' !== $text ) {
				$data['max_uses_per_user'] = max( 1, (int) SimpleVPBot_Bot_Runtime::normalize_digits( $text ) );
			}
			$data['step'] = 'channels';
			SimpleVPBot_State::set( (int) $user->id, 'admin_lifecycle_edit', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_lifecycle_channels', $user ) . self::lifecycle_keep_suffix( $user ) );
			return true;
		}
		if ( 'channels' === $step ) {
			if ( '-' !== $text ) {
				$raw = strtolower( trim( $text ) );
				$data['channel_telegram'] = in_array( $raw, array( 'tg', 'telegram', 'both', '1' ), true ) ? 1 : 0;
				$data['channel_bale']     = in_array( $raw, array( 'bale', 'both', '2' ), true ) ? 1 : 0;
				if ( 'both' === $raw ) {
					$data['channel_telegram'] = 1;
					$data['channel_bale']     = 1;
				}
			}
			$data['step'] = 'enabled';
			SimpleVPBot_State::set( (int) $user->id, 'admin_lifecycle_edit', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_lifecycle_enabled', $user ) . self::lifecycle_keep_suffix( $user ) );
			return true;
		}
		if ( 'enabled' === $step ) {
			if ( '-' !== $text ) {
				$raw = strtolower( trim( $text ) );
				$data['enabled'] = in_array( $raw, array( '1', 'yes', 'true', 'on', 'فعال' ), true ) ? 1 : 0;
			}
			$data['step'] = 'message';
			SimpleVPBot_State::set( (int) $user->id, 'admin_lifecycle_edit', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_lifecycle_message', $user ) );
			return true;
		}
		if ( 'message' === $step ) {
			if ( '-' !== $text ) {
				$data['message_body'] = $text;
			}
			return self::finish_lifecycle_mutate( $platform, $chat_id, $user, $data, (int) ( $data['rule_id'] ?? 0 ) );
		}
		return false;
	}

	/**
	 * @param array<string, mixed> $ctx Context.
	 * @return bool
	 */
	private static function route_lifecycle_delete( array $ctx ) {
		$user     = $ctx['user'];
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$rule_id  = (int) SimpleVPBot_Bot_Runtime::normalize_digits( trim( (string) $ctx['text'] ) );
		if ( $rule_id < 1 || ! class_exists( 'SimpleVPBot_Bot_Admin_Mutate' ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.lifecycle_rule_invalid', $user ) );
			return true;
		}
		$result = SimpleVPBot_Bot_Admin_Mutate::apply_for_user(
			(int) $user->id,
			'marketing_rule_delete',
			array( 'rule_id' => $rule_id )
		);
		SimpleVPBot_State::clear( (int) $user->id );
		if ( ! empty( $result['ok'] ) ) {
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.lifecycle_deleted', $user, array( 'id' => (string) $rule_id ) )
			);
		} else {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Mutate::result_message( $user, $result ) );
		}
		return true;
	}

	/**
	 * @param array<string, mixed> $ctx Context.
	 * @return bool
	 */
	private static function route_lifecycle_run( array $ctx ) {
		$user     = $ctx['user'];
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$rule_id  = (int) SimpleVPBot_Bot_Runtime::normalize_digits( trim( (string) $ctx['text'] ) );
		if ( $rule_id < 1 || ! class_exists( 'SimpleVPBot_Bot_Admin_Mutate' ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.lifecycle_rule_invalid', $user ) );
			return true;
		}
		$result = SimpleVPBot_Bot_Admin_Mutate::apply_for_user(
			(int) $user->id,
			'marketing_run_rule_now',
			array(
				'rule_id' => $rule_id,
				'limit'   => 80,
			)
		);
		SimpleVPBot_State::clear( (int) $user->id );
		if ( ! empty( $result['ok'] ) ) {
			$sent = isset( $result['sent'] ) ? (int) $result['sent'] : ( isset( $result['data']['sent'] ) ? (int) $result['data']['sent'] : 0 );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.lifecycle_run_ok', $user, array( 'id' => (string) $rule_id, 'sent' => (string) $sent ) )
			);
		} else {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Mutate::result_message( $user, $result ) );
		}
		return true;
	}

	/**
	 * @param string $seg Segment key.
	 * @return string
	 */
	private static function lifecycle_segment_field_key( $seg ) {
		switch ( sanitize_key( (string) $seg ) ) {
			case 'abandoned_checkout':
				return 'pending_hours';
			case 'stale_buy_funnel':
				return 'funnel_idle_hours';
			case 'expiring_renew':
				return 'expires_within_days';
			default:
				return 'after_days';
		}
	}

	/**
	 * @param string $seg Segment key.
	 * @return string
	 */
	private static function lifecycle_segment_field_label( $seg ) {
		switch ( sanitize_key( (string) $seg ) ) {
			case 'abandoned_checkout':
				return 'pending_hours';
			case 'stale_buy_funnel':
				return 'funnel_idle_hours';
			case 'expiring_renew':
				return 'expires_within_days';
			default:
				return 'after_days';
		}
	}

	/**
	 * @param object $row Rule row.
	 * @return array<string, mixed>
	 */
	private static function lifecycle_row_to_wizard( $row ) {
		$p = class_exists( 'SimpleVPBot_Model_Marketing_Rule' )
			? SimpleVPBot_Model_Marketing_Rule::to_payload( $row )
			: array();
		return array(
			'rule_id'             => (int) ( $p['id'] ?? 0 ),
			'segment_key'         => (string) ( $p['segment_key'] ?? '' ),
			'enabled'             => ! empty( $p['enabled'] ) ? 1 : 0,
			'priority'            => (int) ( $p['priority'] ?? 10 ),
			'cooldown_days'       => (int) ( $p['cooldown_days'] ?? 90 ),
			'after_days'          => (int) ( $p['after_days'] ?? 0 ),
			'pending_hours'       => (int) ( $p['pending_hours'] ?? 0 ),
			'funnel_idle_hours'   => (int) ( $p['funnel_idle_hours'] ?? 0 ),
			'expires_within_days' => (int) ( $p['expires_within_days'] ?? 0 ),
			'discount_type'       => (string) ( $p['discount_type'] ?? 'percent' ),
			'discount_value'      => (float) ( $p['discount_value'] ?? 0 ),
			'max_discount_toman'  => (int) ( $p['max_discount_toman'] ?? 0 ),
			'code_valid_days'     => (int) ( $p['code_valid_days'] ?? 7 ),
			'max_uses_per_user'   => (int) ( $p['max_uses_per_user'] ?? 1 ),
			'channel_telegram'    => ! empty( $p['channel_telegram'] ) ? 1 : 0,
			'channel_bale'        => ! empty( $p['channel_bale'] ) ? 1 : 0,
			'message_body'        => (string) ( $p['message_body'] ?? '' ),
		);
	}

	/**
	 * @param array<string, mixed> $data Wizard.
	 * @param string               $seg  Segment.
	 */
	private static function apply_lifecycle_segment_presets( array &$data, $seg ) {
		switch ( sanitize_key( (string) $seg ) ) {
			case 'abandoned_checkout':
				$data['pending_hours'] = 24;
				break;
			case 'stale_buy_funnel':
				$data['funnel_idle_hours'] = 48;
				break;
			case 'expiring_renew':
				$data['expires_within_days'] = 7;
				break;
			case 'never_purchased':
				$data['after_days'] = 14;
				break;
			default:
				$data['after_days'] = 30;
		}
	}

	/**
	 * @param array<string, mixed> $data    Wizard.
	 * @param int                  $rule_id Rule id.
	 * @return array<string, mixed>
	 */
	private static function lifecycle_wizard_to_post( array $data, $rule_id = 0 ) {
		$post = array(
			'segment_key'         => (string) ( $data['segment_key'] ?? '' ),
			'enabled'             => ! isset( $data['enabled'] ) || ! empty( $data['enabled'] ) ? 1 : 0,
			'priority'            => (int) ( $data['priority'] ?? 10 ),
			'cooldown_days'       => (int) ( $data['cooldown_days'] ?? 90 ),
			'after_days'          => (int) ( $data['after_days'] ?? 0 ),
			'pending_hours'       => (int) ( $data['pending_hours'] ?? 0 ),
			'funnel_idle_hours'   => (int) ( $data['funnel_idle_hours'] ?? 0 ),
			'expires_within_days' => (int) ( $data['expires_within_days'] ?? 0 ),
			'discount_type'       => (string) ( $data['discount_type'] ?? 'percent' ),
			'discount_value'      => (float) ( $data['discount_value'] ?? 0 ),
			'max_discount_toman'  => (int) ( $data['max_discount_toman'] ?? 0 ),
			'code_valid_days'     => (int) ( $data['code_valid_days'] ?? 7 ),
			'max_uses_per_user'   => (int) ( $data['max_uses_per_user'] ?? 1 ),
			'message_body'        => (string) ( $data['message_body'] ?? '' ),
			'channel_telegram'    => ! isset( $data['channel_telegram'] ) || ! empty( $data['channel_telegram'] ) ? 1 : 0,
			'channel_bale'        => ! isset( $data['channel_bale'] ) || ! empty( $data['channel_bale'] ) ? 1 : 0,
		);
		$rid = $rule_id > 0 ? $rule_id : (int) ( $data['rule_id'] ?? 0 );
		if ( $rid > 0 ) {
			$post['rule_id'] = $rid;
		}
		return $post;
	}

	/**
	 * @param string               $platform Platform.
	 * @param int                  $chat_id  Chat id.
	 * @param object               $user     User.
	 * @param array<string, mixed> $data     Wizard data.
	 * @param int                  $rule_id  Existing rule id (0 = create).
	 * @return bool
	 */
	private static function finish_lifecycle_mutate( $platform, $chat_id, $user, array $data, $rule_id = 0 ) {
		if ( ! class_exists( 'SimpleVPBot_Bot_Admin_Mutate' ) ) {
			SimpleVPBot_State::clear( (int) $user->id );
			return true;
		}
		$post = self::lifecycle_wizard_to_post( $data, $rule_id );
		$result = SimpleVPBot_Bot_Admin_Mutate::apply_for_user( (int) $user->id, 'marketing_rule_save', $post );
		SimpleVPBot_State::clear( (int) $user->id );
		if ( ! empty( $result['ok'] ) ) {
			$new_id = (int) ( $result['rule_id'] ?? $rule_id );
			SimpleVPBot_Bot_Runtime::send_message(
				$platform,
				$chat_id,
				SimpleVPBot_Bot_Admin_Texts::msg(
					$rule_id > 0 ? 'msg.admin.lifecycle_updated' : 'msg.admin.lifecycle_created',
					$user,
					array( 'id' => (string) $new_id )
				)
			);
		} else {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Mutate::result_message( $user, $result ) );
		}
		return true;
	}

	/**
	 * @param array<string, mixed> $ctx Context.
	 * @return bool
	 */
	private static function route_referral_percent( array $ctx ) {
		$user     = $ctx['user'];
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$val      = (int) SimpleVPBot_Bot_Runtime::normalize_digits( trim( (string) $ctx['text'] ) );
		if ( $val < 0 || $val > 100 ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.discount_value_invalid', $user ) );
			return true;
		}
		SimpleVPBot_Settings::update( array( 'referral_percent' => (float) $val ) );
		SimpleVPBot_State::clear( (int) $user->id );
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.referral_percent_saved', $user, array( 'percent' => (string) $val ) )
		);
		return true;
	}

	/**
	 * @param object $user Admin user.
	 * @return string
	 */
	private static function lifecycle_keep_suffix( $user ) {
		return SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_keep_suffix', $user );
	}
}
