<?php
/**
 * Economics summary for dashboard overview tab.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Unit_Economics_Overview
 */
class SimpleVPBot_Unit_Economics_Overview {

	/**
	 * Build overview economics payload for REST.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function build() {
		if ( ! class_exists( 'SimpleVPBot_Unit_Economics_Calculator' ) ) {
			return null;
		}

		$config = class_exists( 'SimpleVPBot_Model_Unit_Economics_Config' )
			? SimpleVPBot_Model_Unit_Economics_Config::global_inputs()
			: array();
		$config_clean = SimpleVPBot_Unit_Economics_Calculator::sanitize_global_config( $config );
		$sales        = SimpleVPBot_Unit_Economics_Calculator::sales_volume_snapshot( $config_clean );
		$site_calc    = SimpleVPBot_Unit_Economics_Calculator::calculate_from_db();
		$breakdown    = isset( $site_calc['breakdownByPanel'] ) && is_array( $site_calc['breakdownByPanel'] )
			? $site_calc['breakdownByPanel']
			: array();

		$window = (int) $config_clean['volume_window_days'];
		$receipts = class_exists( 'SimpleVPBot_Unit_Economics_Revenue' )
			? SimpleVPBot_Unit_Economics_Revenue::receipt_totals( $window )
			: array( 'total' => 0.0, 'by_panel' => array() );

		$site_revenue = class_exists( 'SimpleVPBot_Unit_Economics_Revenue' )
			? SimpleVPBot_Unit_Economics_Revenue::revenue_from_sales_gb( 0, $config_clean, $sales )
			: 0.0;
		$panels_out = array();
		if ( class_exists( 'SimpleVPBot_Model_Panel' ) ) {
			foreach ( SimpleVPBot_Model_Panel::all_active_ordered() as $pn ) {
				$pid = (int) ( $pn->id ?? 0 );
				if ( $pid < 1 ) {
					continue;
				}
				$metrics = isset( $breakdown[ $pid ] ) ? $breakdown[ $pid ] : array();
				$m       = isset( $metrics['metrics'] ) ? $metrics['metrics'] : array();
				$alloc   = isset( $metrics['costAllocation'] ) ? $metrics['costAllocation'] : array();
				$vol     = SimpleVPBot_Unit_Economics_Calculator::resolve_volume_for_panel( $pid, $config_clean, $sales );
				$revenue = class_exists( 'SimpleVPBot_Unit_Economics_Revenue' )
					? SimpleVPBot_Unit_Economics_Revenue::revenue_from_sales_gb( $pid, $config_clean, $sales )
					: 0.0;
				$cost_monthly = (float) ( $m['total_fixed_monthly_costs'] ?? 0 )
					+ (float) ( $m['total_variable_cost_per_gb'] ?? 0 ) * $vol;
				$profit       = $revenue - $cost_monthly;
				$panels_out[] = array(
					'panel_id'                   => $pid,
					'label'                      => (string) ( $pn->label ?? '' ),
					'sales_volume_gb'            => round( $vol, 4 ),
					'revenue_est'                => round( $revenue, 4 ),
					'receipts_approved_sum'      => round( (float) ( $receipts['by_panel'][ $pid ] ?? 0 ), 4 ),
					'cost_panel_fixed'           => (float) ( $alloc['panel_fixed_monthly'] ?? 0 ),
					'cost_shared_alloc'          => (float) ( $alloc['shared_fixed_alloc_monthly'] ?? 0 ),
					'cost_monthly_total'         => round( $cost_monthly, 4 ),
					'profit_est'                 => round( $profit, 4 ),
					'profit_margin_pct'          => $revenue > 0 ? round( ( $profit / $revenue ) * 100, 2 ) : null,
				);
			}
		}

		$site_vol   = max( 0.0, (float) ( $site_calc['inputs']['effective_volume_gb'] ?? 0 ) );
		$site_cost  = (float) ( $site_calc['metrics']['total_fixed_monthly_costs'] ?? 0 )
			+ (float) ( $site_calc['metrics']['total_variable_cost_per_gb'] ?? 0 ) * $site_vol;
		$site_profit = (float) ( $site_calc['metrics']['total_net_profit_monthly'] ?? 0 );

		return array(
			'window_days'        => $window,
			'site'               => array(
				'sales_volume_gb'       => round( (float) ( $sales['total_gb'] ?? 0 ), 4 ),
				'revenue_est'           => round( $site_revenue, 4 ),
				'receipts_approved_sum' => round( (float) $receipts['total'], 4 ),
				'cost_monthly_total'    => round( $site_cost, 4 ),
				'profit_est'            => round( $site_profit, 4 ),
				'shared_costs'          => $site_calc['sharedCosts'] ?? array(),
			),
			'panels'             => $panels_out,
			'upcomingPayments'   => self::upcoming_payments_payload(),
			'receipt_stats'      => $sales['receipt_stats'] ?? array(),
		);
	}

	/**
	 * Upcoming infrastructure payments for dashboard alert.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function upcoming_payments_payload() {
		if ( ! class_exists( 'SimpleVPBot_Model_Panel_Economics_Line' ) ) {
			return array();
		}
		$max_days = self::reminder_max_days();
		$out      = array();
		foreach ( SimpleVPBot_Model_Panel_Economics_Line::upcoming_expiring_lines( $max_days ) as $row ) {
			$expires = (string) ( $row->expires_at ?? '' );
			$days    = 0;
			if ( '' !== $expires ) {
				$days = (int) floor( ( strtotime( $expires . ' 00:00:00 UTC' ) - strtotime( gmdate( 'Y-m-d' ) . ' 00:00:00 UTC' ) ) / 86400 );
			}
			$pid   = (int) ( $row->panel_id ?? 0 );
			$label = trim( (string) ( $row->panel_label ?? '' ) );
			if ( '' === $label ) {
				$label = 0 === $pid ? __( 'مشترک', 'simplevpbot' ) : '#' . $pid;
			}
			$out[] = array(
				'line_id'        => (int) ( $row->id ?? 0 ),
				'panel_id'       => $pid,
				'panel_label'    => $label,
				'category'       => (string) ( $row->category ?? '' ),
				'label'          => (string) ( $row->label ?? '' ),
				'cost_amount'    => (float) ( $row->cost_amount ?? 0 ),
				'expires_at'     => $expires,
				'days_left'      => $days,
				'payment_method' => (string) ( $row->payment_method ?? '' ),
			);
		}
		return $out;
	}

	/**
	 * Max days ahead for upcoming payment list (from settings).
	 *
	 * @return int
	 */
	public static function reminder_max_days() {
		$raw = class_exists( 'SimpleVPBot_Settings' )
			? (string) SimpleVPBot_Settings::get( 'panel_cost_reminder_days', '7,1,0' )
			: '7,1,0';
		$max = 7;
		foreach ( preg_split( '/\s*,\s*/', $raw ) as $part ) {
			$d = (int) $part;
			if ( $d > $max ) {
				$max = $d;
			}
		}
		return max( 1, $max );
	}

	/**
	 * Parse reminder day offsets from settings.
	 *
	 * @return array<int, int>
	 */
	public static function reminder_day_offsets() {
		$raw = class_exists( 'SimpleVPBot_Settings' )
			? (string) SimpleVPBot_Settings::get( 'panel_cost_reminder_days', '7,1,0' )
			: '7,1,0';
		$out = array();
		foreach ( preg_split( '/\s*,\s*/', $raw ) as $part ) {
			if ( '' === trim( $part ) ) {
				continue;
			}
			$d = (int) $part;
			if ( $d >= 0 && ! in_array( $d, $out, true ) ) {
				$out[] = $d;
			}
		}
		if ( empty( $out ) ) {
			$out = array( 7, 1, 0 );
		}
		return $out;
	}
}
