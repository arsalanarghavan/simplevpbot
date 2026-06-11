<?php
/**
 * Atomic dedupe for cron / system user notifications.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Notification_Dedup
 */
class SimpleVPBot_Notification_Dedup {

	/** @var array<string, string> */
	const SCOPE_OPTIONS = array(
		'purge_expired' => 'simplevpbot_purge_expired_sent_buckets',
		'expiry'        => 'simplevpbot_expiry_sent_buckets',
	);

	/**
	 * WP option name for a dedupe scope.
	 *
	 * @param string $scope Scope key.
	 * @return string Empty when unknown.
	 */
	public static function option_name( $scope ) {
		$scope = sanitize_key( (string) $scope );
		return isset( self::SCOPE_OPTIONS[ $scope ] ) ? self::SCOPE_OPTIONS[ $scope ] : '';
	}

	/**
	 * Transient key for atomic claim.
	 *
	 * @param string $scope      Scope.
	 * @param string $bucket_key Bucket id.
	 * @return string
	 */
	public static function transient_key( $scope, $bucket_key ) {
		return 'svp_nd_' . sanitize_key( (string) $scope ) . '_' . md5( (string) $bucket_key );
	}

	/**
	 * Claim a bucket before sending (returns false when already sent / claimed).
	 *
	 * @param string $scope      Scope (purge_expired|expiry).
	 * @param string $bucket_key Unique bucket id.
	 * @param int    $ttl_days   Retention for transient + option pruning window hint.
	 * @return bool True when caller may send.
	 */
	public static function claim( $scope, $bucket_key, $ttl_days = 90 ) {
		$opt = self::option_name( $scope );
		$bucket_key = (string) $bucket_key;
		if ( '' === $opt || '' === $bucket_key ) {
			return false;
		}
		$ttl_secs = max( DAY_IN_SECONDS, (int) $ttl_days * DAY_IN_SECONDS );
		$t_key    = self::transient_key( $scope, $bucket_key );
		if ( get_transient( $t_key ) ) {
			return false;
		}
		$sent = (array) get_option( $opt, array() );
		if ( ! empty( $sent[ $bucket_key ] ) ) {
			set_transient( $t_key, (int) $sent[ $bucket_key ], $ttl_secs );
			return false;
		}
		set_transient( $t_key, time(), $ttl_secs );
		self::mark_option( $scope, $bucket_key );
		return true;
	}

	/**
	 * Persist bucket timestamp in the scope option immediately.
	 *
	 * @param string $scope      Scope.
	 * @param string $bucket_key Bucket id.
	 */
	public static function mark_option( $scope, $bucket_key ) {
		$opt = self::option_name( $scope );
		$bucket_key = (string) $bucket_key;
		if ( '' === $opt || '' === $bucket_key ) {
			return;
		}
		$sent = (array) get_option( $opt, array() );
		$sent[ $bucket_key ] = time();
		update_option( $opt, $sent, false );
	}

	/**
	 * Whether bucket was already recorded (without claiming).
	 *
	 * @param string $scope      Scope.
	 * @param string $bucket_key Bucket id.
	 * @return bool
	 */
	public static function was_sent( $scope, $bucket_key ) {
		if ( get_transient( self::transient_key( $scope, $bucket_key ) ) ) {
			return true;
		}
		$opt = self::option_name( $scope );
		if ( '' === $opt ) {
			return false;
		}
		$sent = (array) get_option( $opt, array() );
		return ! empty( $sent[ (string) $bucket_key ] );
	}
}
