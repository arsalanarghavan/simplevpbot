<?php
/**
 * Process users bulk queue.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SimpleVPBot_Cron_Users_Bulk {

	private static function run_one_item( array $item ) {
		$job_id = (int) ( $item['job_id'] ?? 0 );
		$user_id = (int) ( $item['user_id'] ?? 0 );
		if ( $job_id < 1 || $user_id < 1 ) {
			return array( 'ok' => false, 'reason' => 'invalid_item' );
		}
		$job = SimpleVPBot_Model_Users_Bulk_Job::get_job( $job_id );
		if ( ! is_array( $job ) ) {
			return array( 'ok' => false, 'reason' => 'missing_job' );
		}
		$op = (string) ( $job['operation'] ?? '' );
		$job_status = (string) ( $job['status'] ?? '' );
		if ( 'cancelled' === $job_status ) {
			return array( 'ok' => false, 'reason' => 'job_cancelled' );
		}
		$payload = json_decode( (string) ( $job['payload_json'] ?? '{}' ), true );
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}
		if ( 'wallet' === $op ) {
			$delta = isset( $payload['delta'] ) ? (float) $payload['delta'] : 0.0;
			$notify = ! empty( $payload['notify'] );
			$res = SimpleVPBot_Dashboard_Admin_Mutations::apply(
				'user_balance_delta',
				array(
					'svp_user_id' => $user_id,
					'delta'       => $delta,
					'notify'      => $notify,
				)
			);
			return ! empty( $res['ok'] ) ? array( 'ok' => true ) : array( 'ok' => false, 'reason' => (string) ( $res['message'] ?? 'failed' ) );
		}
		global $wpdb;
		$s_tbl = SimpleVPBot_Model_Service::table();
		$svcs = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$s_tbl} WHERE deleted_at IS NULL AND user_id = %d",
				$user_id
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$svc_ids = array_map( 'intval', (array) $svcs );
		if ( empty( $svc_ids ) ) {
			return array( 'ok' => true );
		}
		$ok = 0;
		$fail = 0;
		foreach ( $svc_ids as $sid ) {
			if ( 'volume' === $op ) {
				$gb = max( 1, (int) ( $payload['extra_gb'] ?? 1 ) );
				$reduce = ! empty( $payload['reduce'] );
				$r = $reduce
					? SimpleVPBot_Admin_User_Ops::admin_reduce_volume( $sid, $gb, 'free' )
					: SimpleVPBot_Admin_User_Ops::admin_add_volume( $sid, $gb, 'free' );
				if ( ! empty( $r['ok'] ) ) {
					++$ok;
				} else {
					++$fail;
				}
			} elseif ( 'extend' === $op ) {
				$days = max( 1, (int) ( $payload['days'] ?? 1 ) );
				$reduce = ! empty( $payload['reduce'] );
				$r = $reduce
					? SimpleVPBot_Service_Renew::apply_reduce_days_free( $sid, $days )
					: SimpleVPBot_Service_Renew::apply_extend_days_free( $sid, $days );
				if ( ! empty( $r['ok'] ) ) {
					++$ok;
				} else {
					++$fail;
				}
			} elseif ( 'alerts' === $op ) {
				$enabled = ! empty( $payload['alerts_enabled'] ) ? 1 : 0;
				SimpleVPBot_Model_Service::update( $sid, array( 'alerts_enabled' => $enabled ) );
				++$ok;
			}
		}
		if ( $fail > 0 && $ok < 1 ) {
			return array( 'ok' => false, 'reason' => 'all_service_actions_failed' );
		}
		return array( 'ok' => true );
	}

	public static function run() {
		$batch = 20;
		$items = SimpleVPBot_Model_Users_Bulk_Job::pop_pending_items( $batch );
		if ( empty( $items ) ) {
			return;
		}
		foreach ( $items as $it ) {
			$item_id = (int) ( $it['id'] ?? 0 );
			$job_id  = (int) ( $it['job_id'] ?? 0 );
			$tries   = (int) ( $it['tries'] ?? 0 ) + 1;
			$r = self::run_one_item( is_array( $it ) ? $it : array() );
			if ( ! empty( $r['ok'] ) ) {
				SimpleVPBot_Model_Users_Bulk_Job::update_item(
					$item_id,
					array(
						'status'       => 'success',
						'tries'        => $tries,
						'last_error'   => null,
					)
				);
			} else {
				SimpleVPBot_Model_Users_Bulk_Job::update_item(
					$item_id,
					array(
						'status'       => 'failed',
						'tries'        => $tries,
						'last_error'   => isset( $r['reason'] ) ? (string) $r['reason'] : 'failed',
					)
				);
			}
			SimpleVPBot_Model_Users_Bulk_Job::maybe_mark_job_done( $job_id );
		}
	}
}

