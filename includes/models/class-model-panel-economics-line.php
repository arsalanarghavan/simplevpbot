<?php
/**
 * Per-panel infrastructure cost lines for unit economics.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Model_Panel_Economics_Line
 */
class SimpleVPBot_Model_Panel_Economics_Line {

	const CATEGORIES = array(
		'internal_server',
		'external_server',
		'cdn',
		'outbound',
		'support',
	);

	const BILLING_CYCLES = array(
		'hourly',
		'daily',
		'monthly',
		'per_gb',
	);

	/** Shared infrastructure scope (bot, DevOps, …). */
	const SHARED_PANEL_ID = 0;

	const PAYMENT_METHODS = array(
		'toman_card',
		'toman_wallet',
		'toman_transfer',
		'usdt',
		'usdt_trc20',
		'other',
	);

	/**
	 * Table.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_panel_economics_lines';
	}

	/**
	 * Lines for one panel, ordered.
	 *
	 * @param int $panel_id Panel id.
	 * @return array<int, object>
	 */
	/**
	 * Shared (site-wide) cost lines.
	 *
	 * @return array<int, object>
	 */
	public static function for_shared() {
		return self::for_panel( self::SHARED_PANEL_ID );
	}

	/**
	 * Find one line by id.
	 *
	 * @param int $id Line id.
	 * @return object|null
	 */
	public static function find( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id < 1 ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) ); // phpcs:ignore
	}

	public static function for_panel( $panel_id ) {
		global $wpdb;
		$panel_id = (int) $panel_id;
		if ( $panel_id < 0 ) {
			return array();
		}
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$t} WHERE panel_id = %d ORDER BY sort_order ASC, id ASC",
				$panel_id
			)
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * All active lines for all panels.
	 *
	 * @return array<int, object>
	 */
	public static function all_active_ordered() {
		global $wpdb;
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT * FROM {$t} WHERE active = 1 ORDER BY panel_id ASC, sort_order ASC, id ASC" );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Map panel_id => lines as arrays for API/calculator.
	 *
	 * @return array<int, array<int, array<string, mixed>>>
	 */
	public static function map_by_panel() {
		$out = array();
		foreach ( self::all_active_ordered() as $row ) {
			$pid = (int) ( $row->panel_id ?? 0 );
			if ( ! isset( $out[ $pid ] ) ) {
				$out[ $pid ] = array();
			}
			$out[ $pid ][] = self::row_to_array( $row );
		}
		return $out;
	}

	/**
	 * All lines (including inactive) per panel for editing.
	 *
	 * @return array<int, array<int, array<string, mixed>>>
	 */
	public static function map_by_panel_for_edit() {
		global $wpdb;
		$t   = self::table();
		$out = array();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT * FROM {$t} ORDER BY panel_id ASC, sort_order ASC, id ASC" );
		foreach ( (array) $rows as $row ) {
			$pid = (int) ( $row->panel_id ?? 0 );
			if ( ! isset( $out[ $pid ] ) ) {
				$out[ $pid ] = array();
			}
			$out[ $pid ][] = self::row_to_array( $row, true );
		}
		return $out;
	}

	/**
	 * @param object               $row       DB row.
	 * @param bool                 $for_edit Include inactive flag.
	 * @return array<string, mixed>
	 */
	public static function row_to_array( $row, $for_edit = false ) {
		if ( ! is_object( $row ) ) {
			return array();
		}
		$arr = array(
			'id'             => (int) ( $row->id ?? 0 ),
			'panel_id'       => (int) ( $row->panel_id ?? 0 ),
			'category'       => (string) ( $row->category ?? 'external_server' ),
			'label'          => (string) ( $row->label ?? '' ),
			'provider'       => (string) ( $row->provider ?? '' ),
			'cost_amount'    => (float) ( $row->cost_amount ?? 0 ),
			'billing_cycle'  => (string) ( $row->billing_cycle ?? 'monthly' ),
			'payment_method' => (string) ( $row->payment_method ?? '' ),
			'paid_at'        => $row->paid_at ? (string) $row->paid_at : '',
			'expires_at'     => $row->expires_at ? (string) $row->expires_at : '',
			'host_ip'        => (string) ( $row->host_ip ?? '' ),
			'tunnel_mode'    => (string) ( $row->tunnel_mode ?? '' ),
			'notes'          => (string) ( $row->notes ?? '' ),
			'sort_order'     => (int) ( $row->sort_order ?? 0 ),
		);
		if ( $for_edit ) {
			$arr['active'] = ! empty( $row->active );
		}
		return $arr;
	}

	/**
	 * Replace all lines for a panel.
	 *
	 * @param int                           $panel_id Panel id.
	 * @param array<int, array<string,mixed>> $rows     Sanitized rows.
	 */
	/**
	 * Replace shared lines (panel_id = 0).
	 *
	 * @param array<int, array<string,mixed>> $rows Rows.
	 */
	public static function replace_for_shared( array $rows ) {
		self::replace_for_panel( self::SHARED_PANEL_ID, $rows );
	}

	/**
	 * Active lines expiring within N days (for overview / alerts).
	 *
	 * @param int $max_days Max days until expiry (inclusive).
	 * @return array<int, object>
	 */
	public static function upcoming_expiring_lines( $max_days = 7 ) {
		global $wpdb;
		$max_days = max( 0, (int) $max_days );
		$t        = self::table();
		$p        = $wpdb->prefix . 'svp_panels';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.*, pn.label AS panel_label
				FROM {$t} l
				LEFT JOIN {$p} pn ON pn.id = l.panel_id
				WHERE l.active = 1
				AND l.expires_at IS NOT NULL
				AND DATEDIFF(l.expires_at, UTC_DATE()) BETWEEN 0 AND %d
				ORDER BY l.expires_at ASC, l.id ASC
				LIMIT 100",
				$max_days
			)
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Mark line paid and optionally extend expiry.
	 *
	 * @param int $line_id     Line id.
	 * @param int $extend_days Days to add from today (or from current expires_at if later).
	 * @return bool
	 */
	public static function mark_paid( $line_id, $extend_days = 30 ) {
		global $wpdb;
		$row = self::find( $line_id );
		if ( ! $row ) {
			return false;
		}
		$extend_days = max( 1, (int) $extend_days );
		$today       = gmdate( 'Y-m-d' );
		$expires     = $today;
		if ( ! empty( $row->expires_at ) ) {
			$cur = (string) $row->expires_at;
			if ( $cur > $today ) {
				$expires = gmdate( 'Y-m-d', strtotime( $cur . ' +' . $extend_days . ' days' ) );
			} else {
				$expires = gmdate( 'Y-m-d', strtotime( $today . ' +' . $extend_days . ' days' ) );
			}
		} else {
			$expires = gmdate( 'Y-m-d', strtotime( $today . ' +' . $extend_days . ' days' ) );
		}
		$wpdb->update(
			self::table(),
			array(
				'paid_at'    => $today,
				'expires_at' => $expires,
			),
			array( 'id' => (int) $line_id )
		);
		return true;
	}

	public static function replace_for_panel( $panel_id, array $rows ) {
		global $wpdb;
		$panel_id = (int) $panel_id;
		if ( $panel_id < 0 ) {
			return;
		}
		$t = self::table();
		$wpdb->delete( $t, array( 'panel_id' => $panel_id ) );
		$order = 0;
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$clean = self::sanitize_line( $row );
			if ( '' === $clean['label'] ) {
				continue;
			}
			$clean['panel_id']   = $panel_id;
			$clean['sort_order'] = $order++;
			$wpdb->insert( $t, $clean );
		}
	}

	/**
	 * Delete lines when panel removed.
	 *
	 * @param int $panel_id Panel id.
	 */
	public static function delete_for_panel( $panel_id ) {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'panel_id' => (int) $panel_id ) );
	}

	/**
	 * Sanitize one line for DB insert.
	 *
	 * @param array<string, mixed> $row Input.
	 * @return array<string, mixed>
	 */
	public static function sanitize_line( array $row ) {
		$cat = sanitize_key( (string) ( $row['category'] ?? 'external_server' ) );
		if ( ! in_array( $cat, self::CATEGORIES, true ) ) {
			$cat = 'external_server';
		}
		$cycle = sanitize_key( (string) ( $row['billing_cycle'] ?? 'monthly' ) );
		if ( ! in_array( $cycle, self::BILLING_CYCLES, true ) ) {
			$cycle = 'monthly';
		}
		if ( in_array( $cat, array( 'cdn', 'outbound' ), true ) && 'per_gb' !== $cycle && 'monthly' !== $cycle && 'daily' !== $cycle && 'hourly' !== $cycle ) {
			$cycle = 'per_gb';
		}

		$paid    = self::sanitize_date_field( $row['paid_at'] ?? '' );
		$expires = self::sanitize_date_field( $row['expires_at'] ?? '' );
		$pay     = sanitize_key( (string) ( $row['payment_method'] ?? '' ) );
		if ( '' !== $pay && ! in_array( $pay, self::PAYMENT_METHODS, true ) ) {
			$pay = 'other';
		}

		return array(
			'category'       => $cat,
			'label'          => sanitize_text_field( (string) ( $row['label'] ?? '' ) ),
			'provider'       => sanitize_text_field( (string) ( $row['provider'] ?? '' ) ),
			'cost_amount'    => max( 0.0, (float) ( $row['cost_amount'] ?? 0 ) ),
			'billing_cycle'  => $cycle,
			'payment_method' => $pay,
			'paid_at'        => $paid,
			'expires_at'     => $expires,
			'host_ip'        => sanitize_text_field( (string) ( $row['host_ip'] ?? '' ) ),
			'tunnel_mode'    => sanitize_text_field( (string) ( $row['tunnel_mode'] ?? '' ) ),
			'notes'          => sanitize_textarea_field( (string) ( $row['notes'] ?? '' ) ),
			'active'         => ! isset( $row['active'] ) || ! empty( $row['active'] ) ? 1 : 0,
		);
	}

	/**
	 * @param mixed $v Date string or empty.
	 * @return string|null
	 */
	private static function sanitize_date_field( $v ) {
		$s = trim( (string) $v );
		if ( '' === $s ) {
			return null;
		}
		$ts = strtotime( $s );
		if ( false === $ts ) {
			return null;
		}
		return gmdate( 'Y-m-d', $ts );
	}
}
