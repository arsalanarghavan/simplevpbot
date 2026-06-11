<?php
/**
 * Unit economics & profitability calculator (panel cost lines + global volume/price).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Unit_Economics_Calculator
 */
class SimpleVPBot_Unit_Economics_Calculator {

	const HOURS_PER_MONTH = 730;

	const DAYS_PER_MONTH = 30;

	/**
	 * Normalize fixed cost to 30-day month (not per_gb).
	 *
	 * @param float  $cost_amount   Raw cost.
	 * @param string $billing_cycle Cycle key.
	 * @return float
	 */
	public static function line_monthly_fixed_cost( $cost_amount, $billing_cycle ) {
		$cost  = max( 0.0, (float) $cost_amount );
		$cycle = sanitize_key( (string) $billing_cycle );
		if ( 'hourly' === $cycle ) {
			return $cost * self::HOURS_PER_MONTH;
		}
		if ( 'daily' === $cycle ) {
			return $cost * self::DAYS_PER_MONTH;
		}
		if ( 'per_gb' === $cycle ) {
			return 0.0;
		}
		return $cost;
	}

	/**
	 * Sanitize cost lines for calculation.
	 *
	 * @param array<int, array<string, mixed>> $lines Lines.
	 * @return array<int, array<string, mixed>>
	 */
	public static function sanitize_lines( array $lines ) {
		$out = array();
		foreach ( $lines as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( isset( $row['active'] ) && empty( $row['active'] ) ) {
				continue;
			}
			$label = sanitize_text_field( (string) ( $row['label'] ?? '' ) );
			if ( '' === $label ) {
				continue;
			}
			$cycle = sanitize_key( (string) ( $row['billing_cycle'] ?? 'monthly' ) );
			if ( ! in_array( $cycle, array( 'hourly', 'daily', 'monthly', 'per_gb' ), true ) ) {
				$cycle = 'monthly';
			}
			$cat = sanitize_key( (string) ( $row['category'] ?? 'external_server' ) );
			if ( class_exists( 'SimpleVPBot_Model_Panel_Economics_Line' )
				&& ! in_array( $cat, SimpleVPBot_Model_Panel_Economics_Line::CATEGORIES, true ) ) {
				$cat = 'external_server';
			}
			$out[] = array(
				'panel_id'      => max( 0, (int) ( $row['panel_id'] ?? 0 ) ),
				'category'      => $cat,
				'label'         => $label,
				'provider'      => sanitize_text_field( (string) ( $row['provider'] ?? '' ) ),
				'cost_amount'   => max( 0.0, (float) ( $row['cost_amount'] ?? 0 ) ),
				'billing_cycle' => $cycle,
			);
		}
		return $out;
	}

	/**
	 * Global config inputs only.
	 *
	 * @param array<string, mixed> $config Config.
	 * @return array<string, mixed>
	 */
	public static function sanitize_global_config( array $config ) {
		$mode = sanitize_key( (string) ( $config['volume_mode'] ?? 'auto_sales' ) );
		if ( ! in_array( $mode, array( 'manual', 'auto_sales' ), true ) ) {
			$mode = 'auto_sales';
		}
		return array(
			'total_sold_volume_gb' => max( 0.0, (float) ( $config['total_sold_volume_gb'] ?? 0 ) ),
			'selling_price_per_gb' => max( 0.0, (float) ( $config['selling_price_per_gb'] ?? 0 ) ),
			'volume_mode'          => $mode,
			'volume_window_days'   => max( 1, min( 365, (int) ( $config['volume_window_days'] ?? 30 ) ) ),
		);
	}

	/**
	 * Load rolling sales volume snapshot for calculator.
	 *
	 * @param array<string, mixed> $config_clean Sanitized config.
	 * @return array{by_panel: array<int, float>, total_gb: float, window_days: int, computed_at: string, receipt_stats: array<string, mixed>}
	 */
	public static function sales_volume_snapshot( array $config_clean ) {
		$days = (int) ( $config_clean['volume_window_days'] ?? 30 );
		if ( class_exists( 'SimpleVPBot_Unit_Economics_Sales_Volume' ) ) {
			return SimpleVPBot_Unit_Economics_Sales_Volume::rolling_volume_by_panel( $days );
		}
		return array(
			'by_panel'      => array(),
			'total_gb'      => 0.0,
			'window_days'   => $days,
			'computed_at'   => gmdate( 'Y-m-d H:i:s' ),
			'receipt_stats' => array(
				'pending_count'       => 0,
				'pending_gb_estimate' => 0.0,
			),
		);
	}

	/**
	 * Effective volume GB for a panel (or site-wide when panel_id = 0).
	 *
	 * @param int                  $panel_id     Panel id (0 = site total in auto mode).
	 * @param array<string, mixed> $config_clean Sanitized config.
	 * @param array<string, mixed> $sales        Sales snapshot.
	 * @return float
	 */
	/**
	 * Split active lines into shared (panel_id=0) and per-panel.
	 *
	 * @param array<int, array<string, mixed>> $all_lines Lines.
	 * @return array{shared: array<int, array<string, mixed>>, by_panel: array<int, array<int, array<string, mixed>>>}
	 */
	public static function split_lines_by_scope( array $all_lines ) {
		$shared   = array();
		$by_panel = array();
		foreach ( $all_lines as $line ) {
			if ( ! is_array( $line ) ) {
				continue;
			}
			$pid = (int) ( $line['panel_id'] ?? 0 );
			if ( 0 === $pid ) {
				$shared[] = $line;
				continue;
			}
			if ( ! isset( $by_panel[ $pid ] ) ) {
				$by_panel[ $pid ] = array();
			}
			$by_panel[ $pid ][] = $line;
		}
		return array(
			'shared'   => $shared,
			'by_panel' => $by_panel,
		);
	}

	/**
	 * Monthly fixed + variable per GB from line set.
	 *
	 * @param array<int, array<string, mixed>> $lines Lines.
	 * @return array{fixed_monthly: float, variable_per_gb: float}
	 */
	public static function cost_totals_from_lines( array $lines ) {
		$agg = self::aggregate_lines( self::sanitize_lines( $lines ) );
		return array(
			'fixed_monthly'   => (float) $agg['fixed_monthly'],
			'variable_per_gb' => (float) $agg['variable_per_gb'],
		);
	}

	/**
	 * Volume-weighted share of shared fixed costs for one panel.
	 *
	 * @param float $shared_fixed   Total shared fixed monthly.
	 * @param float $panel_volume   Panel volume GB.
	 * @param float $site_volume    Site volume GB.
	 * @return float
	 */
	public static function shared_fixed_alloc_for_panel( $shared_fixed, $panel_volume, $site_volume ) {
		$shared_fixed = max( 0.0, (float) $shared_fixed );
		$panel_volume = max( 0.0, (float) $panel_volume );
		$site_volume  = max( 0.0, (float) $site_volume );
		if ( $shared_fixed <= 0 || $panel_volume <= 0 || $site_volume <= 0 ) {
			return 0.0;
		}
		return $shared_fixed * ( $panel_volume / $site_volume );
	}

	public static function resolve_volume_for_panel( $panel_id, array $config_clean, array $sales ) {
		$mode = (string) ( $config_clean['volume_mode'] ?? 'auto_sales' );
		if ( 'auto_sales' === $mode ) {
			$by = isset( $sales['by_panel'] ) && is_array( $sales['by_panel'] ) ? $sales['by_panel'] : array();
			if ( (int) $panel_id > 0 ) {
				return max( 0.0, (float) ( $by[ (int) $panel_id ] ?? 0 ) );
			}
			return max( 0.0, (float) ( $sales['total_gb'] ?? 0 ) );
		}
		return (float) $config_clean['total_sold_volume_gb'];
	}

	/**
	 * Aggregate fixed + variable from lines.
	 *
	 * @param array<int, array<string, mixed>> $lines_clean Sanitized active lines.
	 * @return array{fixed_monthly: float, variable_per_gb: float, lines_normalized: array<int, array<string, mixed>>, by_category: array<string, array{fixed_monthly: float, variable_per_gb: float}>}
	 */
	public static function aggregate_lines( array $lines_clean ) {
		$fixed_monthly       = 0.0;
		$variable_per_gb     = 0.0;
		$lines_normalized    = array();
		$by_category         = array();

		foreach ( $lines_clean as $line ) {
			$cat   = (string) $line['category'];
			$cycle = (string) $line['billing_cycle'];
			$cost  = (float) $line['cost_amount'];

			if ( ! isset( $by_category[ $cat ] ) ) {
				$by_category[ $cat ] = array(
					'fixed_monthly'   => 0.0,
					'variable_per_gb' => 0.0,
				);
			}

			if ( 'per_gb' === $cycle ) {
				$variable_per_gb += $cost;
				$by_category[ $cat ]['variable_per_gb'] += $cost;
				$monthly_equiv = 0.0;
			} else {
				$monthly_equiv = self::line_monthly_fixed_cost( $cost, $cycle );
				$fixed_monthly  += $monthly_equiv;
				$by_category[ $cat ]['fixed_monthly'] += $monthly_equiv;
			}

			$lines_normalized[] = array(
				'panel_id'        => (int) $line['panel_id'],
				'category'      => $cat,
				'label'         => $line['label'],
				'provider'      => $line['provider'],
				'cost_amount'   => $cost,
				'billing_cycle' => $cycle,
				'monthly_cost'  => round( 'per_gb' === $cycle ? 0.0 : $monthly_equiv, 4 ),
				'per_gb_cost'   => 'per_gb' === $cycle ? round( $cost, 4 ) : 0.0,
			);
		}

		return array(
			'fixed_monthly'    => $fixed_monthly,
			'variable_per_gb'  => $variable_per_gb,
			'lines_normalized' => $lines_normalized,
			'by_category'      => $by_category,
		);
	}

	/**
	 * Build metrics from aggregated costs + global config.
	 *
	 * @param float                $fixed_monthly   Total fixed monthly.
	 * @param float                $variable_per_gb Total variable per GB.
	 * @param array<string, float> $config_clean    Global config.
	 * @return array{metrics: array<string, float|null>, warnings: array<int, string>}
	 */
	public static function metrics_from_totals( $fixed_monthly, $variable_per_gb, array $config_clean ) {
		$volume   = (float) $config_clean['total_sold_volume_gb'];
		$selling  = (float) $config_clean['selling_price_per_gb'];
		$warnings = array();

		if ( $volume <= 0 ) {
			$fixed_share = 0.0;
			$warnings[]  = 'volume_required';
		} else {
			$fixed_share = (float) $fixed_monthly / $volume;
		}

		$cost_per_gb              = $fixed_share + $variable_per_gb;
		$profit_per_gb            = $selling - $cost_per_gb;
		$total_net_profit_monthly = $profit_per_gb * ( $volume > 0 ? $volume : 0 );

		if ( $selling > 0 && $selling < $cost_per_gb ) {
			$warnings[] = 'loss_making_price';
		}

		$margin = null;
		if ( $selling > 0 ) {
			$margin = ( $profit_per_gb / $selling ) * 100;
		}

		return array(
			'metrics'  => array(
				'total_fixed_monthly_costs'  => round( (float) $fixed_monthly, 4 ),
				'fixed_cost_share_per_gb'    => round( $fixed_share, 4 ),
				'total_variable_cost_per_gb' => round( (float) $variable_per_gb, 4 ),
				'cost_per_gb'                => round( $cost_per_gb, 4 ),
				'profit_per_gb'              => round( $profit_per_gb, 4 ),
				'total_net_profit_monthly'   => round( $total_net_profit_monthly, 4 ),
				'profit_margin_percentage'   => null === $margin ? null : round( $margin, 4 ),
			),
			'warnings' => array_values( array_unique( $warnings ) ),
		);
	}

	/**
	 * Full calculate for a set of lines + global config.
	 *
	 * @param array<int, array<string, mixed>> $lines  Cost lines.
	 * @param array<string, mixed>             $config Global inputs.
	 * @return array<string, mixed>
	 */
	public static function calculate( array $lines, array $config, $volume_override = null, $sales_volume_gb_30d = null ) {
		$lines_clean  = self::sanitize_lines( $lines );
		$config_clean = self::sanitize_global_config( $config );
		$agg          = self::aggregate_lines( $lines_clean );

		$metrics_config = $config_clean;
		if ( null !== $volume_override ) {
			$metrics_config['total_sold_volume_gb'] = max( 0.0, (float) $volume_override );
		}

		$result = self::metrics_from_totals( $agg['fixed_monthly'], $agg['variable_per_gb'], $metrics_config );

		$inputs = array(
			'total_sold_volume_gb'   => $metrics_config['total_sold_volume_gb'],
			'effective_volume_gb'    => $metrics_config['total_sold_volume_gb'],
			'selling_price_per_gb'   => $config_clean['selling_price_per_gb'],
			'volume_mode'            => $config_clean['volume_mode'],
			'volume_window_days'     => $config_clean['volume_window_days'],
			'lines'                  => $agg['lines_normalized'],
		);
		if ( null !== $sales_volume_gb_30d ) {
			$inputs['sales_volume_gb_30d'] = max( 0.0, (float) $sales_volume_gb_30d );
		}

		return array(
			'inputs'              => $inputs,
			'metrics'             => $result['metrics'],
			'warnings'            => $result['warnings'],
			'breakdownByCategory' => $agg['by_category'],
		);
	}

	/**
	 * Calculate for one panel's lines.
	 *
	 * @param int                                $panel_id Panel id.
	 * @param array<int, array<string, mixed>> $lines    Lines (any panel_id in row ignored; set from arg).
	 * @param array<string, mixed>             $config  Global config.
	 * @return array<string, mixed>
	 */
	public static function calculate_for_panel( $panel_id, array $lines, array $config, array $sales = null, array $shared_lines = null ) {
		$panel_id     = (int) $panel_id;
		$config_clean = self::sanitize_global_config( $config );
		if ( null === $sales ) {
			$sales = self::sales_volume_snapshot( $config_clean );
		}
		$volume      = self::resolve_volume_for_panel( $panel_id, $config_clean, $sales );
		$site_volume = self::resolve_volume_for_panel( 0, $config_clean, $sales );
		$sales_panel = 'auto_sales' === (string) $config_clean['volume_mode']
			? max( 0.0, (float) ( ( $sales['by_panel'][ $panel_id ] ?? 0 ) ) )
			: null;

		$scoped = array();
		foreach ( self::sanitize_lines( $lines ) as $line ) {
			if ( $panel_id > 0 && (int) ( $line['panel_id'] ?? 0 ) !== $panel_id ) {
				continue;
			}
			$line['panel_id'] = $panel_id;
			$scoped[]         = $line;
		}

		$panel_costs = self::cost_totals_from_lines( $scoped );
		$shared_in   = is_array( $shared_lines ) ? $shared_lines : array();
		$shared_cost = self::cost_totals_from_lines( $shared_in );
		$shared_alloc_fixed = self::shared_fixed_alloc_for_panel(
			$shared_cost['fixed_monthly'],
			$volume,
			$site_volume
		);
		$total_fixed    = $panel_costs['fixed_monthly'] + $shared_alloc_fixed;
		$total_variable = $panel_costs['variable_per_gb'] + $shared_cost['variable_per_gb'];

		$metrics_config = $config_clean;
		$metrics_config['total_sold_volume_gb'] = $volume;
		$result = self::metrics_from_totals( $total_fixed, $total_variable, $metrics_config );

		$payload = self::calculate( $scoped, $config, $volume, $sales_panel );
		$payload['metrics'] = $result['metrics'];
		$payload['warnings'] = $result['warnings'];
		$payload['costAllocation'] = array(
			'panel_fixed_monthly'        => round( $panel_costs['fixed_monthly'], 4 ),
			'panel_variable_per_gb'      => round( $panel_costs['variable_per_gb'], 4 ),
			'shared_fixed_alloc_monthly' => round( $shared_alloc_fixed, 4 ),
			'shared_variable_per_gb'     => round( $shared_cost['variable_per_gb'], 4 ),
		);
		return $payload;
	}

	/**
	 * Site-wide from DB.
	 *
	 * @return array<string, mixed>
	 */
	public static function calculate_from_db() {
		$config = class_exists( 'SimpleVPBot_Model_Unit_Economics_Config' )
			? SimpleVPBot_Model_Unit_Economics_Config::global_inputs()
			: array(
				'total_sold_volume_gb' => 0.0,
				'selling_price_per_gb' => 0.0,
				'volume_mode'          => 'auto_sales',
				'volume_window_days'   => 30,
			);
		$config_clean = self::sanitize_global_config( $config );
		$sales        = self::sales_volume_snapshot( $config_clean );
		$site_volume  = self::resolve_volume_for_panel( 0, $config_clean, $sales );

		$lines = array();
		if ( class_exists( 'SimpleVPBot_Model_Panel_Economics_Line' ) ) {
			foreach ( SimpleVPBot_Model_Panel_Economics_Line::all_active_ordered() as $row ) {
				$lines[] = SimpleVPBot_Model_Panel_Economics_Line::row_to_array( $row );
			}
		}
		$split       = self::split_lines_by_scope( $lines );
		$shared_cost = self::cost_totals_from_lines( $split['shared'] );
		$payload     = self::calculate( $lines, $config, $site_volume, $sales['total_gb'] ?? 0 );
		$payload['salesVolume']   = $sales;
		$payload['sharedCosts']   = array(
			'fixed_monthly'   => round( $shared_cost['fixed_monthly'], 4 ),
			'variable_per_gb' => round( $shared_cost['variable_per_gb'], 4 ),
		);
		$payload['breakdownByPanel'] = self::breakdown_by_panel( $lines, $config, $sales, $split['shared'] );
		return $payload;
	}

	/**
	 * Per-panel metrics map.
	 *
	 * @param array<int, array<string, mixed>> $all_lines All active lines.
	 * @param array<string, float>             $config    Global config.
	 * @return array<int, array<string, mixed>>
	 */
	public static function breakdown_by_panel( array $all_lines, array $config, array $sales = null, array $shared_lines = null ) {
		$config_clean = self::sanitize_global_config( $config );
		if ( null === $sales ) {
			$sales = self::sales_volume_snapshot( $config_clean );
		}
		$split = self::split_lines_by_scope( $all_lines );
		if ( null === $shared_lines ) {
			$shared_lines = $split['shared'];
		}
		$out = array();
		foreach ( $split['by_panel'] as $pid => $panel_lines ) {
			$out[ (int) $pid ] = self::calculate_for_panel( (int) $pid, $panel_lines, $config, $sales, $shared_lines );
		}
		return $out;
	}

	/**
	 * Build panelEconomicsMap for REST (edit lines + metrics per panel).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function panel_economics_map_for_rest() {
		$config = class_exists( 'SimpleVPBot_Model_Unit_Economics_Config' )
			? SimpleVPBot_Model_Unit_Economics_Config::global_inputs()
			: array(
				'total_sold_volume_gb' => 0.0,
				'selling_price_per_gb' => 0.0,
				'volume_mode'          => 'auto_sales',
				'volume_window_days'   => 30,
			);
		$config_clean = self::sanitize_global_config( $config );
		$sales        = self::sales_volume_snapshot( $config_clean );

		$edit_map = class_exists( 'SimpleVPBot_Model_Panel_Economics_Line' )
			? SimpleVPBot_Model_Panel_Economics_Line::map_by_panel_for_edit()
			: array();

		$active_lines = array();
		if ( class_exists( 'SimpleVPBot_Model_Panel_Economics_Line' ) ) {
			foreach ( SimpleVPBot_Model_Panel_Economics_Line::all_active_ordered() as $row ) {
				$active_lines[] = SimpleVPBot_Model_Panel_Economics_Line::row_to_array( $row );
			}
		}
		$split     = self::split_lines_by_scope( $active_lines );
		$breakdown = self::breakdown_by_panel( $active_lines, $config, $sales, $split['shared'] );

		$out = array();
		$shared_edit = isset( $edit_map[0] ) ? $edit_map[0] : array();
		$out['0']    = array(
			'lines'   => $shared_edit,
			'metrics' => self::calculate( $split['shared'], $config, 0, 0 ),
			'is_shared' => true,
		);

		$all_panel_ids = array_unique( array_merge( array_keys( $edit_map ), array_keys( $breakdown ) ) );
		foreach ( $all_panel_ids as $pid ) {
			$pid = (int) $pid;
			if ( $pid < 1 ) {
				continue;
			}
			$lines_edit = isset( $edit_map[ $pid ] ) ? $edit_map[ $pid ] : array();
			$metrics    = isset( $breakdown[ $pid ] ) ? $breakdown[ $pid ] : self::calculate_for_panel( $pid, array(), $config, $sales, $split['shared'] );
			$out[ (string) $pid ] = array(
				'lines'               => $lines_edit,
				'metrics'             => $metrics,
				'sales_volume_gb_30d' => max( 0.0, (float) ( $sales['by_panel'][ $pid ] ?? 0 ) ),
			);
		}
		return $out;
	}

	/**
	 * @deprecated v1 — maps old server rows to line shape for backward compat tests.
	 */
	public static function sanitize_servers( array $servers ) {
		$out = array();
		foreach ( $servers as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$name = sanitize_text_field( (string) ( $row['name'] ?? '' ) );
			if ( '' === $name ) {
				continue;
			}
			$cycle = sanitize_key( (string) ( $row['billing_cycle'] ?? 'monthly' ) );
			if ( ! in_array( $cycle, array( 'hourly', 'daily', 'monthly' ), true ) ) {
				$cycle = 'monthly';
			}
			$out[] = array(
				'category'      => 'external_server',
				'label'         => $name,
				'cost_amount'   => max( 0.0, (float) ( $row['cost_amount'] ?? 0 ) ),
				'billing_cycle' => $cycle,
			);
		}
		return $out;
	}

	/**
	 * @deprecated v1 alias.
	 */
	public static function server_monthly_cost( $cost_amount, $billing_cycle ) {
		return self::line_monthly_fixed_cost( $cost_amount, $billing_cycle );
	}

	/**
	 * @deprecated v1 alias.
	 */
	public static function sanitize_config( array $config ) {
		return self::sanitize_global_config( $config );
	}
}
