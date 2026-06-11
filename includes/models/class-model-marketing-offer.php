<?php
/**
 * Per-user marketing offers (issued discount + delivery status).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Model_Marketing_Offer
 */
class SimpleVPBot_Model_Marketing_Offer {

	const STATUSES = array( 'issued', 'sent', 'converted', 'expired', 'skipped' );

	/**
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'svp_marketing_offers';
	}

	/**
	 * @param int $id Id.
	 * @return object|null
	 */
	public static function find( $id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id )
		); // phpcs:ignore
	}

	/**
	 * @param int $rule_id Rule id.
	 * @param int $user_id User id.
	 * @return object|null
	 */
	public static function find_by_rule_user( $rule_id, $user_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE rule_id = %d AND svp_user_id = %d',
				(int) $rule_id,
				(int) $user_id
			)
		); // phpcs:ignore
	}

	/**
	 * @param string $code Normalized discount code.
	 * @return object|null
	 */
	public static function find_by_discount_code( $code ) {
		if ( ! class_exists( 'SimpleVPBot_Model_Discount_Code' ) ) {
			return null;
		}
		$row = SimpleVPBot_Model_Discount_Code::find_by_code( $code );
		if ( ! $row ) {
			return null;
		}
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE discount_code_id = %d ORDER BY id DESC LIMIT 1',
				(int) $row->id
			)
		); // phpcs:ignore
	}

	/**
	 * Latest active offer for user (sent or issued, not converted/expired).
	 *
	 * @param int $user_id User id.
	 * @return object|null
	 */
	public static function latest_open_for_user( $user_id ) {
		global $wpdb;
		$uid = (int) $user_id;
		if ( $uid < 1 ) {
			return null;
		}
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . self::table() . " WHERE svp_user_id = %d AND status IN ('issued','sent') ORDER BY id DESC LIMIT 1",
				$uid
			)
		); // phpcs:ignore
	}

	/**
	 * Cooldown: last sent_at for rule+user.
	 *
	 * @param int $rule_id Rule.
	 * @param int $user_id User.
	 * @return int Unix timestamp.
	 */
	public static function last_sent_timestamp( $rule_id, $user_id ) {
		global $wpdb;
		$ts = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT UNIX_TIMESTAMP(sent_at) FROM ' . self::table() . ' WHERE rule_id = %d AND svp_user_id = %d AND sent_at IS NOT NULL ORDER BY sent_at DESC LIMIT 1',
				(int) $rule_id,
				(int) $user_id
			)
		); // phpcs:ignore
		return $ts ? (int) $ts : 0;
	}

	/**
	 * @param array<string, mixed> $data Row.
	 * @return int
	 */
	public static function insert( array $data ) {
		global $wpdb;
		$wpdb->insert( self::table(), $data );
		return (int) $wpdb->insert_id;
	}

	/**
	 * @param int                  $id Id.
	 * @param array<string, mixed> $data Row.
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		$wpdb->update( self::table(), $data, array( 'id' => (int) $id ) );
	}

	/**
	 * Recent offers for one user (user detail card).
	 *
	 * @param int $user_id User id.
	 * @param int $limit Max rows.
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_for_user( $user_id, $limit = 15 ) {
		$uid = (int) $user_id;
		if ( $uid < 1 ) {
			return array();
		}
		global $wpdb;
		$ot = self::table();
		$rt = SimpleVPBot_Model_Marketing_Rule::table();
		$dt = SimpleVPBot_Model_Discount_Code::table();
		$lim = max( 1, min( 50, (int) $limit ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT o.*, r.segment_key, r.owner_svp_user_id AS rule_owner_id, d.code AS discount_code
				FROM {$ot} o
				INNER JOIN {$rt} r ON r.id = o.rule_id
				LEFT JOIN {$dt} d ON d.id = o.discount_code_id
				WHERE o.svp_user_id = %d
				ORDER BY o.id DESC
				LIMIT %d",
				$uid,
				$lim
			)
		);
		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[] = self::to_payload( $row );
		}
		return $out;
	}

	/**
	 * Users on current page with an open marketing offer (issued/sent).
	 *
	 * @param array<int, int> $user_ids User ids.
	 * @return array<int, bool> user_id => true
	 */
	public static function open_offer_flags_for_users( array $user_ids ) {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $user_ids ) ) ) );
		if ( empty( $ids ) ) {
			return array();
		}
		global $wpdb;
		$t   = self::table();
		$in  = implode( ',', array_map( 'absint', $ids ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$found = $wpdb->get_col(
			"SELECT DISTINCT svp_user_id FROM {$t} WHERE svp_user_id IN ({$in}) AND status IN ('issued','sent')"
		);
		$out = array();
		foreach ( (array) $found as $uid ) {
			$out[ (int) $uid ] = true;
		}
		return $out;
	}

	/**
	 * Paginated list for dashboard.
	 *
	 * @param int $owner_svp_user_id Scope.
	 * @param int $limit Limit.
	 * @param int $offset Offset.
	 * @param bool $site_admin See all site offers.
	 * @return array{rows:array<int,object>,total:int}
	 */
	public static function list_recent( $owner_svp_user_id, $limit, $offset, $site_admin = true, $status_filter = '' ) {
		global $wpdb;
		$ot = self::table();
		$rt = SimpleVPBot_Model_Marketing_Rule::table();
		$ut = SimpleVPBot_Model_User::table();
		$dt = SimpleVPBot_Model_Discount_Code::table();
		$tx = SimpleVPBot_Model_Transaction::table();
		$lim = max( 1, min( 100, (int) $limit ) );
		$off = max( 0, (int) $offset );
		$oid = max( 0, (int) $owner_svp_user_id );
		$where = '';
		$vals  = array();
		if ( ! $site_admin || $oid > 0 ) {
			$where = ' WHERE r.owner_svp_user_id = %d ';
			$vals[] = $oid;
		}
		$st = sanitize_key( (string) $status_filter );
		if ( '' !== $st && in_array( $st, self::STATUSES, true ) ) {
			$where .= $where ? ' AND o.status = %s ' : ' WHERE o.status = %s ';
			$vals[] = $st;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$cnt_sql = "SELECT COUNT(*) FROM {$ot} o INNER JOIN {$rt} r ON r.id = o.rule_id{$where}";
		$total   = $vals
			? (int) $wpdb->get_var( $wpdb->prepare( $cnt_sql, $vals ) ) // phpcs:ignore
			: (int) $wpdb->get_var( $cnt_sql ); // phpcs:ignore
		$list_vals = array_merge( $vals, array( $lim, $off ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT o.*, r.segment_key, r.owner_svp_user_id AS rule_owner_id,
			u.username, u.first_name, u.last_name, d.code AS discount_code,
			ABS(t.amount) AS revenue_toman
			FROM {$ot} o
			INNER JOIN {$rt} r ON r.id = o.rule_id
			LEFT JOIN {$ut} u ON u.id = o.svp_user_id
			LEFT JOIN {$dt} d ON d.id = o.discount_code_id
			LEFT JOIN {$tx} t ON t.id = o.converted_transaction_id AND t.status = 'approved'
			{$where}
			ORDER BY o.id DESC LIMIT %d OFFSET %d";
		$rows = $vals
			? $wpdb->get_results( $wpdb->prepare( $sql, $list_vals ) ) // phpcs:ignore
			: $wpdb->get_results( $wpdb->prepare( $sql, $lim, $off ) ); // phpcs:ignore
		return array(
			'rows'  => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * @param object $row Joined row.
	 * @return array<string, mixed>
	 */
	public static function to_payload( $row ) {
		if ( ! $row || ! is_object( $row ) ) {
			return array();
		}
		$fn = trim( (string) ( $row->first_name ?? '' ) . ' ' . (string) ( $row->last_name ?? '' ) );
		return array(
			'id'                       => (int) ( $row->id ?? 0 ),
			'rule_id'                  => (int) ( $row->rule_id ?? 0 ),
			'svp_user_id'              => (int) ( $row->svp_user_id ?? 0 ),
			'discount_code_id'         => (int) ( $row->discount_code_id ?? 0 ),
			'discount_code'            => (string) ( $row->discount_code ?? '' ),
			'status'                   => (string) ( $row->status ?? '' ),
			'sent_at'                  => (string) ( $row->sent_at ?? '' ),
			'converted_transaction_id' => (int) ( $row->converted_transaction_id ?? 0 ),
			'segment_key'              => (string) ( $row->segment_key ?? '' ),
			'rule_owner_id'            => (int) ( $row->rule_owner_id ?? 0 ),
			'user_label'               => $fn !== '' ? $fn : (string) ( $row->username ?? '' ),
			'created_at'               => (string) ( $row->created_at ?? '' ),
			'revenue_toman'            => round( (float) ( $row->revenue_toman ?? 0 ), 2 ),
		);
	}

	/**
	 * Mark converted when tx uses marketing offer meta.
	 *
	 * @param int $offer_id Offer id.
	 * @param int $tx_id Transaction id.
	 */
	public static function mark_converted( $offer_id, $tx_id ) {
		$oid = (int) $offer_id;
		if ( $oid < 1 ) {
			return;
		}
		self::update(
			$oid,
			array(
				'status'                   => 'converted',
				'converted_transaction_id' => (int) $tx_id,
			)
		);
		if ( class_exists( 'SimpleVPBot_Audit_Log' ) ) {
			$row = self::find( $oid );
			SimpleVPBot_Audit_Log::record(
				'marketing.offer_converted',
				array(
					'offer_id' => $oid,
					'rule_id'  => $row ? (int) $row->rule_id : 0,
					'user_id'  => $row ? (int) $row->svp_user_id : 0,
					'tx_id'    => (int) $tx_id,
				)
			);
		}
	}
}
