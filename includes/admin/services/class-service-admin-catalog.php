<?php
/**
 * Shared catalog CRUD (plans, plan categories) for WP admin and bot.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Service_Admin_Catalog
 */
class SimpleVPBot_Service_Admin_Catalog {

	/**
	 * Sanitize plan fields from POST-like array (already wp_unslash'd).
	 *
	 * @param array<string, mixed> $post Post data.
	 * @return array<string, int|float|string|null>
	 */
	public static function sanitize_plan_post_array( array $post ) {
		$ptype = isset( $post['plan_pricing_type'] ) ? sanitize_key( (string) $post['plan_pricing_type'] ) : 'fixed';
		if ( 'per_gb' !== $ptype ) {
			$ptype = 'fixed';
		}
		$stype = isset( $post['service_type'] ) ? sanitize_key( (string) $post['service_type'] ) : 'xray';
		if ( ! in_array( $stype, array( 'xray', 'l2tp' ), true ) ) {
			$stype = 'xray';
		}
		if ( class_exists( 'SimpleVPBot_Feature_L2tp' ) && ! SimpleVPBot_Feature_L2tp::enabled() ) {
			$stype = 'xray';
		}
		$owner = isset( $post['owner_svp_user_id'] ) ? max( 0, (int) $post['owner_svp_user_id'] ) : 0;
		return array(
			'name'               => sanitize_text_field( (string) ( $post['name'] ?? '' ) ),
			'category'           => sanitize_key( (string) ( $post['category'] ?? 'normal' ) ),
			'duration_days'      => max( 0, (int) ( $post['duration_days'] ?? 0 ) ),
			'traffic_gb'         => max( 0, (int) ( $post['traffic_gb'] ?? 0 ) ),
			'price'              => max( 0, (float) ( $post['price'] ?? 0 ) ),
			'pricing_type'       => $ptype,
			'price_per_gb'       => max( 0, (float) ( $post['price_per_gb'] ?? 0 ) ),
			'traffic_gb_min'     => max( 0, (int) ( $post['traffic_gb_min'] ?? 0 ) ),
			'traffic_gb_max'     => max( 0, (int) ( $post['traffic_gb_max'] ?? 0 ) ),
			'clients_count'      => max( 0, (int) ( $post['clients_count'] ?? 1 ) ),
			'inbound_id'         => (int) ( $post['inbound_id'] ?? 0 ),
			'panel_id'           => max( 1, (int) ( $post['plan_panel_id'] ?? 1 ) ),
			'wholesale_line_id'  => isset( $post['wholesale_line_id'] ) && (int) $post['wholesale_line_id'] > 0 ? (int) $post['wholesale_line_id'] : null,
			'owner_svp_user_id'  => $owner,
			'sort_order'         => (int) ( $post['sort_order'] ?? 0 ),
			'service_type'       => $stype,
			'l2tp_server_id'     => max( 0, (int) ( $post['l2tp_server_id'] ?? 0 ) ) ?: null,
		);
	}

	/**
	 * Validate plan row for fixed vs per-GB.
	 *
	 * @param array<string, mixed> $row Data.
	 * @return bool
	 */
	/**
	 * Reseller may edit catalog on panel via legacy panel prices or wholesale line assignment.
	 *
	 * @param int $actor    svp_users id.
	 * @param int $panel_id Panel id.
	 * @return bool
	 */
	private static function reseller_may_use_panel_catalog( $actor, $panel_id ) {
		$actor = (int) $actor;
		$pid   = (int) $panel_id;
		if ( $actor < 1 || $pid < 1 ) {
			return false;
		}
		if ( class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			return SimpleVPBot_Bot_Reseller_Scope::reseller_can_sell_on_panel_for( $actor, $pid );
		}
		if ( class_exists( 'SimpleVPBot_Model_Reseller_Panel_Price' )
			&& SimpleVPBot_Model_Reseller_Panel_Price::has_panel_access( $actor, $pid ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Whether a reseller actor may delete/toggle a category (no foreign-owned plans on slug).
	 *
	 * @param int    $actor    svp_users id; 0 = site admin.
	 * @param string $slug     Category slug.
	 * @param int    $panel_id Panel id.
	 * @return bool True when blocked (deny mutation).
	 */
	private static function reseller_plan_category_blocked_by_foreign_plans( $actor, $slug, $panel_id ) {
		$actor = (int) $actor;
		if ( $actor < 1 || '' === (string) $slug ) {
			return false;
		}
		global $wpdb;
		$t = SimpleVPBot_Model_Plan::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$cnt = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$t} WHERE category = %s AND panel_id = %d AND owner_svp_user_id <> %d",
				(string) $slug,
				(int) $panel_id,
				$actor
			)
		);
		return $cnt > 0;
	}

	/**
	 * Reseller may offer L2TP plans on panel (preset from legacy row or wholesale line).
	 *
	 * @param int $actor    svp_users id.
	 * @param int $panel_id Panel id.
	 * @return bool
	 */
	private static function reseller_l2tp_allowed_on_panel( $actor, $panel_id ) {
		$actor = (int) $actor;
		$pid   = (int) $panel_id;
		if ( $actor < 1 || $pid < 1 ) {
			return false;
		}
		$pp_chk = class_exists( 'SimpleVPBot_Model_Reseller_Panel_Price' )
			? SimpleVPBot_Model_Reseller_Panel_Price::get_panel_row( $actor, $pid )
			: null;
		$preset_l2tp = $pp_chk && isset( $pp_chk->default_service_type ) && 'l2tp' === (string) $pp_chk->default_service_type;
		if ( $preset_l2tp ) {
			return true;
		}
		if ( class_exists( 'SimpleVPBot_Model_Reseller_Wholesale_Line' )
			&& SimpleVPBot_Model_Reseller_Wholesale_Line::reseller_panel_default_is_l2tp( $actor, $pid ) ) {
			return true;
		}
		if ( class_exists( 'SimpleVPBot_Model_Reseller_Panel_Price' ) ) {
			$catalog = SimpleVPBot_Model_Reseller_Panel_Price::resolve_catalog_defaults( $actor, $pid );
			if ( 'l2tp' === (string) ( $catalog['default_service_type'] ?? '' ) ) {
				return true;
			}
		}
		return false;
	}

	public static function validate_plan_pricing( array $row ) {
		$type = isset( $row['pricing_type'] ) ? (string) $row['pricing_type'] : 'fixed';
		if ( 'per_gb' === $type ) {
			$ppg = (float) ( $row['price_per_gb'] ?? 0 );
			$min = (int) ( $row['traffic_gb_min'] ?? 0 );
			$max = (int) ( $row['traffic_gb_max'] ?? 0 );
			return $ppg > 0 && $min >= 1 && $max >= 1 && $min <= $max;
		}
		return (float) ( $row['price'] ?? 0 ) > 0;
	}

	/**
	 * Whether plan row fails structural validation.
	 *
	 * @param array<string, mixed> $rd Sanitized row.
	 * @return bool
	 */
	public static function plan_row_invalid( array $rd ) {
		if ( '' === $rd['name'] ) {
			return true;
		}
		$cat = (string) ( $rd['category'] ?? '' );
		if ( '' === $cat || 'z_svp_need_cat' === $cat ) {
			return true;
		}
		$pid = (int) ( $rd['panel_id'] ?? 1 );
		if ( ! SimpleVPBot_Model_Plan_Category::find_by_panel_slug( $pid, $cat ) ) {
			return true;
		}
		if ( 'l2tp' === (string) $rd['service_type'] ) {
			if ( empty( $rd['l2tp_server_id'] ) ) {
				return true;
			}
		} elseif ( (int) $rd['inbound_id'] <= 0 ) {
			return true;
		}
		return ! self::validate_plan_pricing( $rd );
	}

	/**
	 * Apply plan action (delete/toggle/add/update). Caller verified capability.
	 *
	 * @param string               $action plan_action value.
	 * @param int                  $pid    plan_id.
	 * @param array<string, mixed> $post   Unslashed POST-like array.
	 * @return array<string, mixed>|null null if nothing to do; else redirect hints for WP or ok for bot.
	 */
	public static function apply_plan_action( $action, $pid, array $post ) {
		$action = sanitize_key( (string) $action );
		$pid    = (int) $pid;
		$actor  = (int) ( $post['__actor_svp_user_id'] ?? 0 );

		if ( 'delete' === $action ) {
			if ( $pid > 0 ) {
				if ( $actor > 0 ) {
					$ex = SimpleVPBot_Model_Plan::find( $pid );
					if ( $ex && 'l2tp' === (string) ( $ex->service_type ?? '' ) ) {
						if ( ! self::reseller_l2tp_allowed_on_panel( $actor, (int) ( $ex->panel_id ?? 1 ) ) ) {
							return array( 'ok' => false, 'code' => 'l2tp_forbidden_for_reseller' );
						}
					}
					if ( ! $ex || (int) ( $ex->owner_svp_user_id ?? 0 ) !== $actor ) {
						return array( 'ok' => false, 'code' => 'forbidden' );
					}
				}
				SimpleVPBot_Model_Plan::delete( $pid );
			}
			return array( 'ok' => true, 'code' => 'deleted' );
		}

		if ( 'toggle' === $action && $pid > 0 ) {
			$row = SimpleVPBot_Model_Plan::find( $pid );
			if ( $actor > 0 && $row && 'l2tp' === (string) ( $row->service_type ?? '' ) ) {
				if ( ! self::reseller_l2tp_allowed_on_panel( $actor, (int) ( $row->panel_id ?? 1 ) ) ) {
					return array( 'ok' => false, 'code' => 'l2tp_forbidden_for_reseller' );
				}
			}
			if ( $actor > 0 && ( ! $row || (int) ( $row->owner_svp_user_id ?? 0 ) !== $actor ) ) {
				return array( 'ok' => false, 'code' => 'forbidden' );
			}
			if ( $row ) {
				SimpleVPBot_Model_Plan::update( $pid, array( 'active' => (int) ! (int) $row->active ) );
			}
			return array( 'ok' => true, 'code' => 'toggled' );
		}

		$row_data = self::sanitize_plan_post_array( $post );
		if ( $actor > 0 ) {
			$row_data = self::merge_reseller_plan_defaults( $actor, $row_data );
		}

		if ( 'add' === $action ) {
			if ( self::plan_row_invalid( $row_data ) ) {
				return array( 'ok' => false, 'code' => 'invalid' );
			}
			$fr = self::apply_reseller_plan_rules( $actor, $row_data, null );
			if ( ! empty( $fr['block'] ) ) {
				return array( 'ok' => false, 'code' => (string) $fr['code'] );
			}
			$row_data = $fr['row'];
			$row_data['active'] = ! empty( $post['plan_active'] ) ? 1 : 0;
			SimpleVPBot_Model_Plan::insert( $row_data );
			return array( 'ok' => true, 'code' => 'added' );
		}

		if ( 'update' === $action && $pid > 0 ) {
			$existing = SimpleVPBot_Model_Plan::find( $pid );
			if ( $actor > 0 && $existing && 'l2tp' === (string) ( $existing->service_type ?? '' ) ) {
				if ( ! self::reseller_l2tp_allowed_on_panel( $actor, (int) ( $existing->panel_id ?? 1 ) ) ) {
					return array( 'ok' => false, 'code' => 'l2tp_forbidden_for_reseller', 'plan_id' => $pid );
				}
			}
			if ( self::plan_row_invalid( $row_data ) ) {
				return array( 'ok' => false, 'code' => 'invalid_update', 'plan_id' => $pid );
			}
			$fr       = self::apply_reseller_plan_rules( $actor, $row_data, $existing );
			if ( ! empty( $fr['block'] ) ) {
				return array( 'ok' => false, 'code' => (string) $fr['code'], 'plan_id' => $pid );
			}
			$row_data = $fr['row'];
			$row_data['active'] = ! empty( $post['plan_active'] ) ? 1 : 0;
			SimpleVPBot_Model_Plan::update( $pid, $row_data );
			return array( 'ok' => true, 'code' => 'updated' );
		}

		return null;
	}

	/**
	 * Apply admin-defined inbound/protocol defaults from reseller panel row (dashboard actors only).
	 *
	 * @param int                             $actor    svp_users id.
	 * @param array<string, int|float|string|null> $row_data Sanitized plan row.
	 * @return array<string, int|float|string|null>
	 */
	public static function merge_reseller_plan_defaults( $actor, array $row_data ) {
		$actor = (int) $actor;
		if ( $actor < 1 || ! class_exists( 'SimpleVPBot_Model_Reseller_Panel_Price' ) ) {
			return $row_data;
		}
		$u = SimpleVPBot_Model_User::find( $actor );
		if ( ! $u || ! SimpleVPBot_Model_User::is_reseller_row( $u ) ) {
			return $row_data;
		}
		$panel_id = (int) ( $row_data['panel_id'] ?? 1 );
		$pp_row   = SimpleVPBot_Model_Reseller_Panel_Price::get_panel_row( $actor, $panel_id );
		if ( ! SimpleVPBot_Model_Reseller_Panel_Price::row_allows_panel_use( $pp_row ) ) {
			$catalog = SimpleVPBot_Model_Reseller_Panel_Price::resolve_catalog_defaults( $actor, $panel_id );
			$dstype  = sanitize_key( (string) ( $catalog['default_service_type'] ?? 'xray' ) );
			if ( ! in_array( $dstype, array( 'xray', 'l2tp' ), true ) ) {
				$dstype = 'xray';
			}
			$row_data['service_type'] = $dstype;
			if ( 'l2tp' === $dstype ) {
				$row_data['inbound_id']     = 0;
				$l2                         = max( 0, (int) ( $catalog['default_l2tp_server_id'] ?? 0 ) );
				$row_data['l2tp_server_id'] = $l2 > 0 ? $l2 : null;
			} else {
				$row_data['inbound_id']     = max( 0, (int) ( $catalog['default_inbound_id'] ?? 0 ) );
				$row_data['l2tp_server_id'] = null;
			}
			return $row_data;
		}
		$dstype = isset( $pp_row->default_service_type ) ? sanitize_key( (string) $pp_row->default_service_type ) : 'xray';
		if ( ! in_array( $dstype, array( 'xray', 'l2tp' ), true ) ) {
			$dstype = 'xray';
		}
		$row_data['service_type'] = $dstype;
		if ( 'l2tp' === $dstype ) {
			$row_data['inbound_id'] = 0;
			$l2                     = max( 0, (int) ( $pp_row->default_l2tp_server_id ?? 0 ) );
			$row_data['l2tp_server_id'] = $l2 > 0 ? $l2 : null;
		} else {
			$row_data['inbound_id']     = max( 0, (int) ( $pp_row->default_inbound_id ?? 0 ) );
			$row_data['l2tp_server_id'] = null;
		}
		return $row_data;
	}

	/**
	 * When dashboard actor is a reseller, enforce ownership + floor price from {@see SimpleVPBot_Model_Reseller_Panel_Price}.
	 *
	 * @param int                     $actor    svp_users id or 0 (admin).
	 * @param array<string, mixed>  $row_data Sanitized plan row.
	 * @param object|null           $existing Existing row for update.
	 * @return array{row:array<string,mixed>, block?:bool, code?:string}
	 */
	private static function apply_reseller_plan_rules( $actor, array $row_data, $existing ) {
		$actor = (int) $actor;
		if ( $actor < 1 ) {
			$row_data['wholesale_line_id'] = null;
			$row_data['owner_svp_user_id'] = max( 0, (int) ( $row_data['owner_svp_user_id'] ?? 0 ) );
			return array( 'row' => $row_data );
		}
		$u = SimpleVPBot_Model_User::find( $actor );
		if ( ! $u || ! SimpleVPBot_Model_User::is_reseller_row( $u ) ) {
			return array( 'row' => $row_data, 'block' => true, 'code' => 'bad_actor' );
		}
		if ( $existing ) {
			if ( (int) ( $existing->owner_svp_user_id ?? 0 ) !== $actor ) {
				return array( 'row' => $row_data, 'block' => true, 'code' => 'forbidden' );
			}
		}

		$panel_id      = (int) ( $row_data['panel_id'] ?? 1 );
		$wholesale_lid = isset( $row_data['wholesale_line_id'] ) ? (int) $row_data['wholesale_line_id'] : 0;
		if ( $wholesale_lid < 1 && class_exists( 'SimpleVPBot_Model_Reseller_Panel_Price' ) ) {
			$catalog     = SimpleVPBot_Model_Reseller_Panel_Price::resolve_catalog_defaults( $actor, $panel_id );
			$catalog_lid = (int) ( $catalog['wholesale_line_id'] ?? 0 );
			if ( $catalog_lid > 0 ) {
				$row_data['wholesale_line_id'] = $catalog_lid;
				$wholesale_lid                 = $catalog_lid;
			} elseif ( class_exists( 'SimpleVPBot_Model_Reseller_Wholesale_Line' ) ) {
				$line_ids = array();
				foreach ( SimpleVPBot_Model_Reseller_Wholesale_Line::lines_for_reseller( $actor ) as $line ) {
					if ( (int) ( $line->panel_id ?? 0 ) === $panel_id ) {
						$line_ids[] = (int) ( $line->id ?? 0 );
					}
				}
				$line_ids = array_values( array_unique( array_filter( $line_ids ) ) );
				if ( 1 === count( $line_ids ) ) {
					$row_data['wholesale_line_id'] = $line_ids[0];
					$wholesale_lid                 = $line_ids[0];
				}
			}
		}

		if ( $wholesale_lid < 1 && class_exists( 'SimpleVPBot_Model_Reseller_Wholesale_Line' ) ) {
			$assigned_on_panel = array();
			foreach ( SimpleVPBot_Model_Reseller_Wholesale_Line::lines_for_reseller( $actor ) as $line ) {
				if ( (int) ( $line->panel_id ?? 0 ) === $panel_id ) {
					$assigned_on_panel[] = (int) ( $line->id ?? 0 );
				}
			}
			$assigned_on_panel = array_values( array_unique( array_filter( $assigned_on_panel ) ) );
			if ( ! empty( $assigned_on_panel ) ) {
				return array( 'row' => $row_data, 'block' => true, 'code' => 'wholesale_line_required' );
			}
		}

		$using_line = false;
		if ( $wholesale_lid > 0 && class_exists( 'SimpleVPBot_Service_Reseller_Wholesale_Pricing' ) ) {
			$lr = SimpleVPBot_Service_Reseller_Wholesale_Pricing::apply_line_to_plan_row( $actor, $row_data );
			if ( empty( $lr['ok'] ) ) {
				return array(
					'row'   => $row_data,
					'block' => true,
					'code'  => (string) ( $lr['code'] ?? 'wholesale_line_invalid' ),
				);
			}
			$using_line = true;
		} else {
			$row_data['wholesale_line_id'] = null;
		}

		$panel_id = (int) ( $row_data['panel_id'] ?? 1 );

		if ( ! self::reseller_may_use_panel_catalog( $actor, $panel_id ) ) {
			return array( 'row' => $row_data, 'block' => true, 'code' => 'panel_not_allowed' );
		}

		$effective_unit_floor = 0.0;
		if ( $using_line && $wholesale_lid > 0 && class_exists( 'SimpleVPBot_Service_Reseller_Wholesale_Pricing' ) ) {
			$effective_unit_floor = (float) SimpleVPBot_Service_Reseller_Wholesale_Pricing::wholesale_floor_unit( $actor, $wholesale_lid, $panel_id );
		} elseif ( class_exists( 'SimpleVPBot_Model_Reseller_Panel_Price' ) ) {
			$effective_unit_floor = (float) SimpleVPBot_Model_Reseller_Panel_Price::effective_wholesale_floor( $actor, $panel_id );
		}
		$ptype = (string) ( $row_data['pricing_type'] ?? 'fixed' );
		if ( $effective_unit_floor > 0 ) {
			if ( 'per_gb' === $ptype ) {
				$ppg = (float) ( $row_data['price_per_gb'] ?? 0 );
				if ( $ppg + 0.000001 < $effective_unit_floor ) {
					return array( 'row' => $row_data, 'block' => true, 'code' => 'below_reseller_floor' );
				}
			} else {
				$gb  = max( 1, (int) ( $row_data['traffic_gb'] ?? 0 ) );
				$min = $effective_unit_floor * $gb;
				if ( (float) ( $row_data['price'] ?? 0 ) + 0.000001 < $min ) {
					return array( 'row' => $row_data, 'block' => true, 'code' => 'below_reseller_floor' );
				}
			}
		}

		if ( $using_line ) {
			$row_data['owner_svp_user_id'] = $actor;
			return array( 'row' => $row_data );
		}

		if ( ! class_exists( 'SimpleVPBot_Model_Reseller_Panel_Price' ) ) {
			return array( 'row' => $row_data, 'block' => true, 'code' => 'module_missing' );
		}
		$pp_row = SimpleVPBot_Model_Reseller_Panel_Price::get_panel_row( $actor, $panel_id );
		if ( ! SimpleVPBot_Model_Reseller_Panel_Price::row_allows_panel_use( $pp_row ) ) {
			return array( 'row' => $row_data, 'block' => true, 'code' => 'panel_not_allowed' );
		}
		$dstype = isset( $pp_row->default_service_type ) ? sanitize_key( (string) $pp_row->default_service_type ) : 'xray';
		if ( ! in_array( $dstype, array( 'xray', 'l2tp' ), true ) ) {
			$dstype = 'xray';
		}
		$row_data['service_type'] = $dstype;
		if ( 'l2tp' === $dstype ) {
			$row_data['inbound_id'] = 0;
			$l2                     = max( 0, (int) ( $pp_row->default_l2tp_server_id ?? 0 ) );
			$row_data['l2tp_server_id'] = $l2 > 0 ? $l2 : null;
		} else {
			$row_data['inbound_id']     = max( 0, (int) ( $pp_row->default_inbound_id ?? 0 ) );
			$row_data['l2tp_server_id'] = null;
		}
		$row_data['wholesale_line_id'] = null;
		$row_data['owner_svp_user_id'] = $actor;
		return array( 'row' => $row_data );
	}

	/**
	 * Apply plan category action from POST-like array.
	 *
	 * @param string               $action pc_action.
	 * @param int                  $rid    pc_id.
	 * @param array<string, mixed> $post   Unslashed.
	 * @return array<string, mixed>|null
	 */
	public static function apply_plan_category_action( $action, $rid, array $post ) {
		$action = sanitize_key( (string) $action );
		$rid    = (int) $rid;
		$actor  = (int) ( $post['__actor_svp_user_id'] ?? 0 );

		if ( 'delete' === $action && $rid > 0 ) {
			$row = SimpleVPBot_Model_Plan_Category::find( $rid );
			if ( $actor > 0 && $row ) {
				$pid_chk = max( 1, (int) ( $row->panel_id ?? 1 ) );
				if ( ! self::reseller_may_use_panel_catalog( $actor, $pid_chk ) ) {
					return array( 'ok' => false, 'code' => 'panel_not_allowed' );
				}
				if ( self::reseller_plan_category_blocked_by_foreign_plans( $actor, (string) ( $row->slug ?? '' ), $pid_chk ) ) {
					return array( 'ok' => false, 'code' => 'category_foreign_plans' );
				}
			}
			if ( $row && SimpleVPBot_Model_Plan_Category::count_plans_with_slug( (string) $row->slug, (int) ( $row->panel_id ?? 1 ) ) > 0 ) {
				return array( 'ok' => false, 'code' => 'inuse' );
			}
			SimpleVPBot_Model_Plan_Category::delete( $rid );
			return array( 'ok' => true, 'code' => 'deleted' );
		}

		if ( 'toggle' === $action && $rid > 0 ) {
			$row = SimpleVPBot_Model_Plan_Category::find( $rid );
			if ( $actor > 0 && $row ) {
				$pid_chk = max( 1, (int) ( $row->panel_id ?? 1 ) );
				if ( ! self::reseller_may_use_panel_catalog( $actor, $pid_chk ) ) {
					return array( 'ok' => false, 'code' => 'panel_not_allowed' );
				}
				if ( self::reseller_plan_category_blocked_by_foreign_plans( $actor, (string) ( $row->slug ?? '' ), $pid_chk ) ) {
					return array( 'ok' => false, 'code' => 'category_foreign_plans' );
				}
			}
			if ( $row ) {
				SimpleVPBot_Model_Plan_Category::update( $rid, array( 'active' => (int) ! (int) $row->active ) );
			}
			return array( 'ok' => true, 'code' => 'toggled' );
		}

		$label = sanitize_text_field( (string) ( $post['pc_label'] ?? '' ) );
		$sort  = (int) ( $post['pc_sort'] ?? 0 );
		$act   = ! empty( $post['pc_active'] ) ? 1 : 0;

		if ( 'add' === $action ) {
			$slug     = strtolower( substr( preg_replace( '/[^a-z0-9_]/', '', (string) ( $post['pc_slug'] ?? '' ) ), 0, 32 ) );
			$panel_id = max( 1, (int) ( $post['pc_panel_id'] ?? 1 ) );
			if ( $actor > 0 && ! self::reseller_may_use_panel_catalog( $actor, $panel_id ) ) {
				return array( 'ok' => false, 'code' => 'panel_not_allowed' );
			}
			if ( '' === $slug || '' === $label ) {
				return array( 'ok' => false, 'code' => 'invalid' );
			}
			if ( SimpleVPBot_Model_Plan_Category::find_by_panel_slug( $panel_id, $slug ) ) {
				return array( 'ok' => false, 'code' => 'dup' );
			}
			SimpleVPBot_Model_Plan_Category::insert(
				array(
					'panel_id'   => $panel_id,
					'slug'       => $slug,
					'label'      => $label,
					'sort_order' => $sort,
					'active'     => $act,
				)
			);
			return array( 'ok' => true, 'code' => 'added' );
		}

		if ( 'update' === $action && $rid > 0 ) {
			if ( $actor > 0 ) {
				$ex_row = SimpleVPBot_Model_Plan_Category::find( $rid );
				$pid_chk = $ex_row ? max( 1, (int) ( $ex_row->panel_id ?? 1 ) ) : 1;
				if ( ! self::reseller_may_use_panel_catalog( $actor, $pid_chk ) ) {
					return array( 'ok' => false, 'code' => 'panel_not_allowed' );
				}
				if ( $ex_row && self::reseller_plan_category_blocked_by_foreign_plans( $actor, (string) ( $ex_row->slug ?? '' ), $pid_chk ) ) {
					return array( 'ok' => false, 'code' => 'category_foreign_plans' );
				}
			}
			if ( '' === $label ) {
				return array( 'ok' => false, 'code' => 'invalid' );
			}
			SimpleVPBot_Model_Plan_Category::update(
				$rid,
				array(
					'label'      => $label,
					'sort_order' => $sort,
					'active'     => $act,
				)
			);
			return array( 'ok' => true, 'code' => 'updated' );
		}

		return null;
	}

	/**
	 * Sanitize L2TP server POST (same rules as admin menu).
	 *
	 * @param int|null             $existing_id Existing id for update or null for insert.
	 * @param array<string, mixed> $post        Unslashed POST.
	 * @return array<string, mixed>
	 */
	public static function sanitize_l2tp_post( $existing_id, array $post ) {
		$auth = isset( $post['ssh_auth'] ) ? sanitize_key( (string) $post['ssh_auth'] ) : 'key';
		if ( ! in_array( $auth, array( 'key', 'password' ), true ) ) {
			$auth = 'key';
		}
		$data = array(
			'label'              => sanitize_text_field( (string) ( $post['label'] ?? '' ) ),
			'ssh_host'           => sanitize_text_field( (string) ( $post['ssh_host'] ?? '' ) ),
			'ssh_port'           => max( 1, (int) ( $post['ssh_port'] ?? 22 ) ),
			'ssh_user'           => sanitize_text_field( (string) ( $post['ssh_user'] ?? 'svpbot' ) ),
			'ssh_auth'           => $auth,
			'l2tp_host'          => sanitize_text_field( (string) ( $post['l2tp_host'] ?? '' ) ),
			'chap_path'          => sanitize_text_field( (string) ( $post['chap_path'] ?? '/etc/ppp/chap-secrets' ) ),
			'reload_cmd'         => sanitize_text_field( (string) ( $post['reload_cmd'] ?? 'sudo /bin/systemctl reload xl2tpd' ) ),
			'usage_cmd_template' => trim( (string) ( $post['usage_cmd_template'] ?? '' ) ),
			'apps_note'          => sanitize_textarea_field( (string) ( $post['apps_note'] ?? '' ) ),
			'active'             => ! empty( $post['active'] ) ? 1 : 0,
		);
		$data['ssh_password_enc']       = (string) ( $post['ssh_password'] ?? '' );
		$data['ssh_private_key_enc']    = (string) ( $post['ssh_private_key'] ?? '' );
		$data['ssh_key_passphrase_enc'] = (string) ( $post['ssh_key_passphrase'] ?? '' );
		$data['l2tp_psk_enc']           = (string) ( $post['l2tp_psk'] ?? '' );
		return $data;
	}

	/**
	 * Sanitize card method key.
	 *
	 * @param string $raw Raw.
	 * @return string
	 */
	public static function sanitize_card_method_key( $raw ) {
		$k = sanitize_key( (string) $raw );
		if ( 'mehr' === $k ) {
			return 'c2c';
		}
		return in_array( $k, array( 'c2c', 'crypto', 'crypto_auto' ), true ) ? $k : 'c2c';
	}
}
