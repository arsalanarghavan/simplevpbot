<?php
/**
 * Revenue estimates and receipt totals for unit economics overview.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Unit_Economics_Revenue
 */
class SimpleVPBot_Unit_Economics_Revenue {

	/**
	 * Estimated revenue from sold GB × selling price.
	 *
	 * @param int                  $panel_id     0 = site total.
	 * @param array<string, mixed> $config_clean Sanitized global config.
	 * @param array<string, mixed> $sales        Sales snapshot.
	 * @return float
	 */
	public static function revenue_from_sales_gb( $panel_id, array $config_clean, array $sales ) {
		$price  = max( 0.0, (float) ( $config_clean['selling_price_per_gb'] ?? 0 ) );
		$volume = 0.0;
		if ( (int) $panel_id > 0 ) {
			$by = isset( $sales['by_panel'] ) && is_array( $sales['by_panel'] ) ? $sales['by_panel'] : array();
			$volume = max( 0.0, (float) ( $by[ (int) $panel_id ] ?? 0 ) );
		} else {
			$volume = max( 0.0, (float) ( $sales['total_gb'] ?? 0 ) );
		}
		return round( $volume * $price, 4 );
	}

	/**
	 * Sum approved receipt amounts in window, optionally by panel.
	 *
	 * @param int $window_days Days.
	 * @return array{total: float, by_panel: array<int, float>}
	 */
	public static function receipt_totals( $window_days = 30 ) {
		global $wpdb;
		$window = max( 1, min( 365, (int) $window_days ) );
		$r_t    = $wpdb->prefix . 'svp_receipts';
		$x_t    = $wpdb->prefix . 'svp_transactions';
		$s_t    = $wpdb->prefix . 'svp_services';
		$p_t    = $wpdb->prefix . 'svp_plans';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(r.amount),0) FROM {$r_t} r
				INNER JOIN {$x_t} tx ON tx.id = r.transaction_id
				WHERE r.status = 'approved'
				AND tx.status = 'approved'
				AND r.created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)",
				$window
			)
		);

		$by_panel = array();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.amount, tx.service_id, tx.meta_json
				FROM {$r_t} r
				INNER JOIN {$x_t} tx ON tx.id = r.transaction_id
				WHERE r.status = 'approved'
				AND tx.status = 'approved'
				AND r.created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)",
				$window
			)
		);
		$plans    = array();
		$services = array();
		foreach ( (array) $rows as $row ) {
			$amt = (float) ( $row->amount ?? 0 );
			$pid = 0;
			if ( class_exists( 'SimpleVPBot_Unit_Economics_Sales_Volume' ) ) {
				$fake = (object) array(
					'service_id' => (int) ( $row->service_id ?? 0 ),
					'meta_json'  => $row->meta_json ?? '',
				);
				$pid = SimpleVPBot_Unit_Economics_Sales_Volume::panel_id_from_transaction_row( $fake, $plans, $services );
			}
			if ( ! isset( $by_panel[ $pid ] ) ) {
				$by_panel[ $pid ] = 0.0;
			}
			$by_panel[ $pid ] += $amt;
		}
		foreach ( $by_panel as $pid => $amt ) {
			$by_panel[ $pid ] = round( (float) $amt, 4 );
		}

		return array(
			'total'     => round( $total, 4 ),
			'by_panel'  => $by_panel,
		);
	}
}
