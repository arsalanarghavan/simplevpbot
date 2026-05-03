<?php
/**
 * Purchase categories (first step in buy flow: buy:c:slug).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Model_Plan_Category
 */
class SimpleVPBot_Model_Plan_Category {

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_plan_categories';
	}

	/**
	 * @param int $id Id.
	 * @return object|null
	 */
	public static function find( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id ) ); // phpcs:ignore
	}

	/**
	 * @param int    $panel_id svp_panels.id.
	 * @param string $slug Slug.
	 * @return object|null
	 */
	public static function find_by_panel_slug( $panel_id, $slug ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE panel_id = %d AND slug = %s',
				(int) $panel_id,
				(string) $slug
			)
		); // phpcs:ignore
	}

	/**
	 * @param string $slug Slug (legacy: first match on panel 1).
	 * @return object|null
	 */
	public static function find_by_slug( $slug ) {
		return self::find_by_panel_slug( 1, $slug );
	}

	/**
	 * Active rows for inline keyboard (ordered).
	 *
	 * @return array<int, object>
	 */
	public static function active_ordered() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' WHERE active = 1 ORDER BY panel_id ASC, sort_order ASC, id ASC' ); // phpcs:ignore
	}

	/**
	 * Active categories for one 3x-ui panel (buy flow).
	 *
	 * @param int $panel_id svp_panels.id.
	 * @return array<int, object>
	 */
	public static function active_ordered_for_panel( $panel_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE active = 1 AND panel_id = %d ORDER BY sort_order ASC, id ASC',
				(int) $panel_id
			)
		); // phpcs:ignore
	}

	/**
	 * Options for plan form: active categories plus current slug if it is inactive (editing legacy row).
	 *
	 * @param string|null $current_category Plan.category value.
	 * @return array<int, object>
	 */
	public static function options_for_plan_form( $current_category, $panel_id = null ) {
		$active = null !== $panel_id ? self::active_ordered_for_panel( (int) $panel_id ) : self::active_ordered();
		$have   = array();
		foreach ( $active as $r ) {
			$have[ (string) $r->slug ] = true;
		}
		$cur = (string) $current_category;
		if ( '' !== $cur && empty( $have[ $cur ] ) ) {
			$row = null !== $panel_id ? self::find_by_panel_slug( (int) $panel_id, $cur ) : self::find_by_slug( $cur );
			if ( $row ) {
				$active[] = $row;
			}
		}
		return $active;
	}

	/**
	 * All rows (admin).
	 *
	 * @return array<int, object>
	 */
	public static function all_ordered() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY panel_id ASC, sort_order ASC, id ASC' ); // phpcs:ignore
	}

	/**
	 * How many plans use this slug as category.
	 *
	 * @param string $slug Slug.
	 * @return int
	 */
	public static function count_plans_with_slug( $slug, $panel_id = null ) {
		global $wpdb;
		$t = SimpleVPBot_Model_Plan::table();
		if ( null !== $panel_id ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$t} WHERE category = %s AND panel_id = %d",
					(string) $slug,
					(int) $panel_id
				)
			);
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE category = %s", (string) $slug ) );
	}

	/**
	 * @param array<string, mixed> $data Data.
	 * @return int Insert id.
	 */
	public static function insert( array $data ) {
		global $wpdb;
		$wpdb->insert( self::table(), $data );
		return (int) $wpdb->insert_id;
	}

	/**
	 * @param int                  $id Id.
	 * @param array<string, mixed> $data Data.
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		$wpdb->update( self::table(), $data, array( 'id' => (int) $id ) );
	}

	/**
	 * @param int $id Id.
	 */
	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'id' => (int) $id ) );
	}

	/**
	 * Insert defaults if table has no rows.
	 */
	public static function seed_if_empty() {
		global $wpdb;
		$n = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() ); // phpcs:ignore
		if ( $n > 0 ) {
			return;
		}
		$defaults = array(
			array( 'slug' => 'normal', 'label' => '🟢 عادی', 'sort_order' => 0, 'active' => 1 ),
			array( 'slug' => 'vip', 'label' => '⭐ ویژه', 'sort_order' => 1, 'active' => 1 ),
			array( 'slug' => 'gaming', 'label' => '🎮 گیمینگ', 'sort_order' => 2, 'active' => 1 ),
			array( 'slug' => 'trade', 'label' => '📈 ترید', 'sort_order' => 3, 'active' => 1 ),
		);
		foreach ( $defaults as $row ) {
			$row['panel_id'] = 1;
			$wpdb->insert( self::table(), $row );
		}
	}
}
