<?php
/**
 * Plan model.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Model_Plan
 */
class SimpleVPBot_Model_Plan {

	/**
	 * Table.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_plans';
	}

	/**
	 * Find.
	 *
	 * @param int $id Id.
	 * @return object|null
	 */
	public static function find( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) ); // phpcs:ignore
	}

	/**
	 * By category.
	 *
	 * @param string $category Category.
	 * @return array<int, object>
	 */
	public static function by_category( $category, $panel_id = null ) {
		global $wpdb;
		$cat = (string) $category;
		if ( null !== $panel_id ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM ' . self::table() . ' WHERE category = %s AND panel_id = %d AND active = 1 ORDER BY sort_order ASC, id ASC',
					$cat,
					(int) $panel_id
				)
			); // phpcs:ignore
		}
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE category = %s AND active = 1 ORDER BY sort_order ASC, id ASC', $cat ) ); // phpcs:ignore
	}

	/**
	 * Active plans in a category scoped to one 3x-ui panel.
	 *
	 * @param string $category Category slug.
	 * @param int    $panel_id svp_panels.id.
	 * @return array<int, object>
	 */
	public static function by_category_and_panel( $category, $panel_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE category = %s AND panel_id = %d AND active = 1 ORDER BY sort_order ASC, id ASC',
				(string) $category,
				(int) $panel_id
			)
		); // phpcs:ignore
	}

	/**
	 * All active.
	 *
	 * @return array<int, object>
	 */
	public static function all_active() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' WHERE active = 1 ORDER BY sort_order ASC, id ASC' ); // phpcs:ignore
	}

	/**
	 * All plans (admin).
	 *
	 * @return array<int, object>
	 */
	public static function all_rows() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY sort_order ASC, id ASC' ); // phpcs:ignore
	}

	/**
	 * Insert.
	 *
	 * @param array<string, mixed> $data Data.
	 * @return int
	 */
	public static function insert( array $data ) {
		global $wpdb;
		$wpdb->insert( self::table(), $data );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Update.
	 *
	 * @param int                  $id Id.
	 * @param array<string, mixed> $data Data.
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		$wpdb->update( self::table(), $data, array( 'id' => $id ) );
	}

	/**
	 * Delete.
	 *
	 * @param int $id Id.
	 */
	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'id' => $id ) );
	}

	/**
	 * Per-GB (variable volume) plan.
	 *
	 * @param object $plan Plan row.
	 * @return bool
	 */
	public static function is_per_gb( $plan ) {
		return is_object( $plan ) && 'per_gb' === (string) ( $plan->pricing_type ?? 'fixed' );
	}

	/**
	 * For per-GB: min/max GB; otherwise ignored.
	 *
	 * @param object $plan Plan.
	 * @param int    $gb Volume in GB.
	 * @return bool
	 */
	public static function is_volume_in_range( $plan, $gb ) {
		$g = (int) $gb;
		if ( ! self::is_per_gb( $plan ) ) {
			return true;
		}
		$min = (int) ( $plan->traffic_gb_min ?? 0 );
		$max = (int) ( $plan->traffic_gb_max ?? 0 );
		if ( $min < 1 || $max < 1 || $min > $max ) {
			return false;
		}
		return $g >= $min && $g <= $max;
	}

	/**
	 * Total price: fixed uses `price`, per-GB uses price_per_gb * volume.
	 *
	 * @param object   $plan Plan.
	 * @param int|null $volume_gb Chosen GB for per-GB; ignored for fixed.
	 * @return float
	 */
	public static function total_price( $plan, $volume_gb = null ) {
		if ( self::is_per_gb( $plan ) && null !== $volume_gb ) {
			return round( (float) ( $plan->price_per_gb ?? 0 ) * (int) $volume_gb, 2 );
		}
		return (float) ( $plan->price ?? 0 );
	}

	/**
	 * Human label for list rows (short).
	 *
	 * @param object $plan Plan.
	 * @return string
	 */
	public static function list_price_label( $plan ) {
		if ( self::is_per_gb( $plan ) ) {
			return number_format( (float) ( $plan->price_per_gb ?? 0 ) ) . '/GB';
		}
		return number_format( (float) ( $plan->price ?? 0 ) );
	}

	/**
	 * Is L2TP service plan.
	 *
	 * @param object $plan Plan row.
	 * @return bool
	 */
	public static function is_l2tp( $plan ) {
		return is_object( $plan ) && 'l2tp' === (string) ( $plan->service_type ?? 'xray' );
	}

	/**
	 * Is Xray service plan.
	 *
	 * @param object $plan Plan row.
	 * @return bool
	 */
	public static function is_xray( $plan ) {
		return ! self::is_l2tp( $plan );
	}

	/**
	 * Plans tied to a panel row (svp_panels.id).
	 *
	 * @param int $panel_id Panel id.
	 * @return int
	 */
	public static function count_by_panel_id( $panel_id ) {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . self::table() . ' WHERE panel_id = %d',
				(int) $panel_id
			)
		); // phpcs:ignore
	}
}
