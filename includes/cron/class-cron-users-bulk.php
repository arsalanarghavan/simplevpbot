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

	/**
	 * @param array<string, mixed> $payload Job payload.
	 * @param string               $op      Operation key.
	 * @return bool
	 */
	private static function service_op_needs_active_only( $payload, $op ) {
		unset( $payload );
		return in_array( $op, array( 'volume', 'extend' ), true );
	}

	/**
	 * Panel-target job item (volume / extend on one cached panel client).
	 *
	 * @param array<string, mixed> $item Queue row.
	 * @return array{ok:bool, reason?:string}
	 */
	private static function run_one_panel_item( array $item ) {
		$job_id  = (int) ( $item['job_id'] ?? 0 );
		$panel_id = (int) ( $item['panel_id'] ?? 0 );
		$inbound_id = (int) ( $item['inbound_id'] ?? 0 );
		$email   = trim( (string) ( $item['client_email'] ?? '' ) );
		if ( $job_id < 1 || $panel_id < 1 || $inbound_id < 1 || '' === $email ) {
			return array( 'ok' => false, 'reason' => 'invalid_panel_item' );
		}
		$job = SimpleVPBot_Model_Users_Bulk_Job::get_job( $job_id );
		if ( ! is_array( $job ) ) {
			return array( 'ok' => false, 'reason' => 'missing_job' );
		}
		$op         = (string) ( $job['operation'] ?? '' );
		$job_status = (string) ( $job['status'] ?? '' );
		if ( 'cancelled' === $job_status ) {
			return array( 'ok' => false, 'reason' => 'job_cancelled' );
		}
		$payload = json_decode( (string) ( $job['payload_json'] ?? '{}' ), true );
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}
		$actor = (int) ( $payload['__actor_svp_user_id'] ?? 0 );
		if ( $actor > 0 && class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			$check_uid = $user_id;
			if ( $check_uid < 1 ) {
				$check_uid = SimpleVPBot_Dashboard_Admin_Mutations::users_bulk_user_id_for_panel_row(
					array(
						'panel_id'   => $panel_id,
						'inbound_id' => $inbound_id,
						'email'      => $email,
					)
				);
			}
			if ( $check_uid > 0 && ! SimpleVPBot_Bot_Reseller_Scope::reseller_may_moderate_user_for( $actor, $check_uid ) ) {
				return array( 'ok' => false, 'reason' => 'forbidden_scope' );
			}
		}
		$panel_opts = array(
			'force_enable' => false,
			'touch_remark' => false,
			'sync_db'      => true,
		);
		$reduce = ! empty( $payload['reduce'] );
		$r      = null;
		if ( 'volume' === $op ) {
			$gb = max( 1, (int) ( $payload['extra_gb'] ?? 1 ) );
			$r  = SimpleVPBot_Service_Renew::apply_panel_volume_delta( $panel_id, $inbound_id, $email, $gb, $reduce, $panel_opts );
		} elseif ( 'extend' === $op ) {
			$days = max( 1, (int) ( $payload['days'] ?? 1 ) );
			$r    = SimpleVPBot_Service_Renew::apply_panel_extend_days( $panel_id, $inbound_id, $email, $days, $reduce, $panel_opts );
		} else {
			return array( 'ok' => false, 'reason' => 'unsupported_panel_op' );
		}
		if ( empty( $r['ok'] ) ) {
			return array( 'ok' => false, 'reason' => (string) ( $r['message'] ?? 'panel_op_failed' ) );
		}
		$user_id = (int) ( $item['user_id'] ?? 0 );
		if ( $user_id < 1 ) {
			$user_id = SimpleVPBot_Dashboard_Admin_Mutations::users_bulk_user_id_for_panel_row(
				array(
					'panel_id'   => $panel_id,
					'inbound_id' => $inbound_id,
					'email'      => $email,
				)
			);
		}
		if ( $user_id > 0 && in_array( $op, array( 'volume', 'extend' ), true ) ) {
			SimpleVPBot_Dashboard_Admin_Mutations::users_bulk_maybe_notify_service_op( $user_id, $op, $payload, true );
		}
		return array( 'ok' => true );
	}

	private static function run_one_item( array $item ) {
		$panel_id = (int) ( $item['panel_id'] ?? 0 );
		if ( $panel_id > 0 ) {
			return self::run_one_panel_item( $item );
		}

		$job_id  = (int) ( $item['job_id'] ?? 0 );
		$user_id = (int) ( $item['user_id'] ?? 0 );
		if ( $job_id < 1 || $user_id < 1 ) {
			return array( 'ok' => false, 'reason' => 'invalid_item' );
		}
		$job = SimpleVPBot_Model_Users_Bulk_Job::get_job( $job_id );
		if ( ! is_array( $job ) ) {
			return array( 'ok' => false, 'reason' => 'missing_job' );
		}
		$op         = (string) ( $job['operation'] ?? '' );
		$job_status = (string) ( $job['status'] ?? '' );
		if ( 'cancelled' === $job_status ) {
			return array( 'ok' => false, 'reason' => 'job_cancelled' );
		}
		$payload = json_decode( (string) ( $job['payload_json'] ?? '{}' ), true );
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}
		$actor = (int) ( $payload['__actor_svp_user_id'] ?? 0 );
		if ( $actor > 0 && class_exists( 'SimpleVPBot_Bot_Reseller_Scope' ) ) {
			if ( ! SimpleVPBot_Bot_Reseller_Scope::reseller_may_moderate_user_for( $actor, $user_id ) ) {
				return array( 'ok' => false, 'reason' => 'forbidden_scope' );
			}
		}
		if ( 'wallet' === $op ) {
			$delta  = isset( $payload['delta'] ) ? (float) $payload['delta'] : 0.0;
			$notify = ! empty( $payload['notify'] );
			$mutate_params = array(
				'svp_user_id' => $user_id,
				'delta'       => $delta,
				'notify'      => $notify,
			);
			if ( $actor > 0 ) {
				$mutate_params['__actor_svp_user_id'] = $actor;
			}
			$res = SimpleVPBot_Dashboard_Admin_Mutations::apply(
				'user_balance_delta',
				$mutate_params
			);
			return ! empty( $res['ok'] ) ? array( 'ok' => true ) : array( 'ok' => false, 'reason' => (string) ( $res['message'] ?? 'failed' ) );
		}

		$active_only = self::service_op_needs_active_only( $payload, $op );
		$svc_ids     = SimpleVPBot_Dashboard_Admin_Mutations::users_bulk_service_ids_for_user( $user_id, $payload, $active_only );
		if ( empty( $svc_ids ) ) {
			return array( 'ok' => true );
		}

		$ok   = 0;
		$fail = 0;
		foreach ( $svc_ids as $sid ) {
			if ( 'volume' === $op ) {
				$gb     = max( 1, (int) ( $payload['extra_gb'] ?? 1 ) );
				$reduce = ! empty( $payload['reduce'] );
				$r      = $reduce
					? SimpleVPBot_Admin_User_Ops::admin_reduce_volume( $sid, $gb, 'free' )
					: SimpleVPBot_Admin_User_Ops::admin_add_volume( $sid, $gb, 'free' );
				if ( ! empty( $r['ok'] ) ) {
					++$ok;
				} else {
					++$fail;
				}
			} elseif ( 'extend' === $op ) {
				$days   = max( 1, (int) ( $payload['days'] ?? 1 ) );
				$reduce = ! empty( $payload['reduce'] );
				$r      = $reduce
					? SimpleVPBot_Service_Renew::apply_reduce_days_free( $sid, $days )
					: SimpleVPBot_Service_Renew::apply_extend_days_free( $sid, $days );
				if ( ! empty( $r['ok'] ) ) {
					++$ok;
				} else {
					++$fail;
				}
			} elseif ( 'slots' === $op ) {
				$n      = max( 1, (int) ( $payload['extra_users'] ?? 1 ) );
				$reduce = ! empty( $payload['reduce'] );
				if ( $reduce ) {
					$r = SimpleVPBot_Admin_User_Ops::admin_reduce_user_slots( $sid, $n, 'free' );
				} else {
					$r = SimpleVPBot_Admin_User_Ops::admin_add_user_slots( $sid, $n, 'free' );
				}
				if ( ! empty( $r['ok'] ) ) {
					++$ok;
				} else {
					++$fail;
				}
			} elseif ( 'alerts' === $op ) {
				$patch = array();
				foreach ( array( 'alerts_enabled', 'alerts_volume', 'alerts_expiry', 'alerts_users' ) as $k ) {
					if ( array_key_exists( $k, $payload ) ) {
						$patch[ $k ] = ! empty( $payload[ $k ] ) ? 1 : 0;
					}
				}
				if ( empty( $patch ) ) {
					continue;
				}
				SimpleVPBot_Model_Service::update( $sid, $patch );
				++$ok;
			}
		}
		if ( $fail > 0 && $ok < 1 ) {
			return array( 'ok' => false, 'reason' => 'all_service_actions_failed' );
		}
		if ( in_array( $op, array( 'volume', 'extend', 'slots' ), true ) && $ok > 0 ) {
			SimpleVPBot_Dashboard_Admin_Mutations::users_bulk_maybe_notify_service_op( $user_id, $op, $payload, true );
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
			$r       = self::run_one_item( is_array( $it ) ? $it : array() );
			if ( ! empty( $r['ok'] ) ) {
				SimpleVPBot_Model_Users_Bulk_Job::update_item(
					$item_id,
					array(
						'status'     => 'success',
						'tries'      => $tries,
						'last_error' => null,
					)
				);
			} else {
				SimpleVPBot_Model_Users_Bulk_Job::update_item(
					$item_id,
					array(
						'status'     => 'failed',
						'tries'      => $tries,
						'last_error' => isset( $r['reason'] ) ? (string) $r['reason'] : 'failed',
					)
				);
			}
			SimpleVPBot_Model_Users_Bulk_Job::maybe_mark_job_done( $job_id );
		}
	}
}
