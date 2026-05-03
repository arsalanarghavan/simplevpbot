<?php
/**
 * DB logger.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Logger
 */
class SimpleVPBot_Logger {

	/**
	 * Log message.
	 *
	 * @param string               $level Level.
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 */
	public static function log( $level, $message, array $context = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'svp_logs';
		$wpdb->insert(
			$table,
			array(
				'level'        => sanitize_key( $level ),
				'message'      => wp_strip_all_tags( $message ),
				'context_json' => wp_json_encode( $context ),
			),
			array( '%s', '%s', '%s' )
		);
	}

	/**
	 * Info shortcut.
	 *
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 */
	public static function info( $message, array $context = array() ) {
		self::log( 'info', $message, $context );
	}

	/**
	 * Error shortcut.
	 *
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 */
	public static function error( $message, array $context = array() ) {
		self::log( 'error', $message, $context );
	}

	/**
	 * Warning shortcut.
	 *
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 */
	public static function warning( $message, array $context = array() ) {
		self::log( 'warning', $message, $context );
	}
}
