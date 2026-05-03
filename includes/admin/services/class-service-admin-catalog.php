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
		return array(
			'name'           => sanitize_text_field( (string) ( $post['name'] ?? '' ) ),
			'category'       => sanitize_key( (string) ( $post['category'] ?? 'normal' ) ),
			'duration_days'  => max( 0, (int) ( $post['duration_days'] ?? 0 ) ),
			'traffic_gb'     => max( 0, (int) ( $post['traffic_gb'] ?? 0 ) ),
			'price'          => max( 0, (float) ( $post['price'] ?? 0 ) ),
			'pricing_type'   => $ptype,
			'price_per_gb'   => max( 0, (float) ( $post['price_per_gb'] ?? 0 ) ),
			'traffic_gb_min' => max( 0, (int) ( $post['traffic_gb_min'] ?? 0 ) ),
			'traffic_gb_max' => max( 0, (int) ( $post['traffic_gb_max'] ?? 0 ) ),
			'clients_count'  => max( 1, (int) ( $post['clients_count'] ?? 1 ) ),
			'inbound_id'     => (int) ( $post['inbound_id'] ?? 0 ),
			'panel_id'       => max( 1, (int) ( $post['plan_panel_id'] ?? 1 ) ),
			'sort_order'     => (int) ( $post['sort_order'] ?? 0 ),
			'service_type'   => $stype,
			'l2tp_server_id' => max( 0, (int) ( $post['l2tp_server_id'] ?? 0 ) ) ?: null,
		);
	}

	/**
	 * Validate plan row for fixed vs per-GB.
	 *
	 * @param array<string, mixed> $row Data.
	 * @return bool
	 */
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

		if ( 'delete' === $action ) {
			if ( $pid > 0 ) {
				SimpleVPBot_Model_Plan::delete( $pid );
			}
			return array( 'ok' => true, 'code' => 'deleted' );
		}

		if ( 'toggle' === $action && $pid > 0 ) {
			$row = SimpleVPBot_Model_Plan::find( $pid );
			if ( $row ) {
				SimpleVPBot_Model_Plan::update( $pid, array( 'active' => (int) ! (int) $row->active ) );
			}
			return array( 'ok' => true, 'code' => 'toggled' );
		}

		$row_data = self::sanitize_plan_post_array( $post );

		if ( 'add' === $action ) {
			if ( self::plan_row_invalid( $row_data ) ) {
				return array( 'ok' => false, 'code' => 'invalid' );
			}
			$row_data['active'] = ! empty( $post['plan_active'] ) ? 1 : 0;
			SimpleVPBot_Model_Plan::insert( $row_data );
			return array( 'ok' => true, 'code' => 'added' );
		}

		if ( 'update' === $action && $pid > 0 ) {
			if ( self::plan_row_invalid( $row_data ) ) {
				return array( 'ok' => false, 'code' => 'invalid_update', 'plan_id' => $pid );
			}
			$row_data['active'] = ! empty( $post['plan_active'] ) ? 1 : 0;
			SimpleVPBot_Model_Plan::update( $pid, $row_data );
			return array( 'ok' => true, 'code' => 'updated' );
		}

		return null;
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

		if ( 'delete' === $action && $rid > 0 ) {
			$row = SimpleVPBot_Model_Plan_Category::find( $rid );
			if ( $row && SimpleVPBot_Model_Plan_Category::count_plans_with_slug( (string) $row->slug, (int) ( $row->panel_id ?? 1 ) ) > 0 ) {
				return array( 'ok' => false, 'code' => 'inuse' );
			}
			SimpleVPBot_Model_Plan_Category::delete( $rid );
			return array( 'ok' => true, 'code' => 'deleted' );
		}

		if ( 'toggle' === $action && $rid > 0 ) {
			$row = SimpleVPBot_Model_Plan_Category::find( $rid );
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
		return in_array( $k, array( 'c2c', 'mehr', 'crypto', 'crypto_auto' ), true ) ? $k : 'c2c';
	}
}
