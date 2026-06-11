<?php
/**
 * Telegram Bot API HTTP: optional proxy and custom API base.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Telegram_Http
 */
class SimpleVPBot_Telegram_Http {

	/**
	 * Register curl hook for Telegram outbound requests.
	 */
	public static function init() {
		add_action( 'http_api_curl', array( __CLASS__, 'http_api_curl' ), 10, 3 );
	}

	/**
	 * Whether URL targets Telegram Bot API or file CDN.
	 *
	 * @param string $url Request URL.
	 * @return bool
	 */
	public static function is_telegram_url( $url ) {
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
		if ( '' === $host ) {
			return false;
		}
		$host = strtolower( $host );
		return false !== strpos( $host, 'telegram.org' ) || false !== strpos( $host, 't.me' );
	}

	/**
	 * Bot API base URL including token segment and trailing slash.
	 *
	 * @param string $token Bot token.
	 * @return string
	 */
	public static function bot_api_base_url( $token ) {
		if ( class_exists( 'SimpleVPBot_Telegram_Relay' ) && SimpleVPBot_Telegram_Relay::is_enabled() ) {
			return SimpleVPBot_Telegram_Relay::bot_api_base_url( $token );
		}
		$custom = trim( (string) SimpleVPBot_Settings::get( 'telegram_api_base_url', '' ) );
		$tok    = rawurlencode( (string) $token );
		if ( '' !== $custom ) {
			$base = trailingslashit( untrailingslashit( $custom ) );
			if ( false === strpos( $base, '{token}' ) ) {
				if ( substr( $base, -4 ) === '/bot' ) {
					return $base . $tok . '/';
				}
				return $base . 'bot' . $tok . '/';
			}
			return str_replace( '{token}', $tok, $base );
		}
		return 'https://api.telegram.org/bot' . $tok . '/';
	}

	/**
	 * Apply proxy settings to wp_remote_* args when enabled.
	 *
	 * @param array<string, mixed> $args Request args.
	 * @param string               $url  URL.
	 * @return array<string, mixed>
	 */
	public static function apply_proxy_to_args( array $args, $url ) {
		if ( ! self::is_telegram_url( $url ) || ! SimpleVPBot_Settings::get( 'telegram_proxy_enabled', false ) ) {
			return $args;
		}
		$host = trim( (string) SimpleVPBot_Settings::get( 'telegram_proxy_host', '' ) );
		$port = (int) SimpleVPBot_Settings::get( 'telegram_proxy_port', 0 );
		if ( '' === $host || $port < 1 ) {
			return $args;
		}
		$type = sanitize_key( (string) SimpleVPBot_Settings::get( 'telegram_proxy_type', 'http' ) );
		if ( 'socks5' === $type ) {
			$args['_svp_telegram_socks5'] = true;
			return $args;
		}
		$proxy = array(
			'host' => $host,
			'port' => $port,
		);
		$user = trim( (string) SimpleVPBot_Settings::get( 'telegram_proxy_username', '' ) );
		$pass = (string) SimpleVPBot_Settings::get( 'telegram_proxy_password', '' );
		if ( '' !== $user ) {
			$proxy['username'] = $user;
		}
		if ( '' !== $pass ) {
			$proxy['password'] = $pass;
		}
		$args['proxy'] = $proxy;
		return $args;
	}

	/**
	 * @param resource             $handle      cURL handle.
	 * @param array<string, mixed> $parsed_args Request args.
	 * @param string               $url         URL.
	 */
	public static function http_api_curl( $handle, $parsed_args, $url ) {
		if ( ! self::is_telegram_url( $url ) || ! SimpleVPBot_Settings::get( 'telegram_proxy_enabled', false ) ) {
			return;
		}
		if ( empty( $parsed_args['_svp_telegram_socks5'] ) ) {
			return;
		}
		$host = trim( (string) SimpleVPBot_Settings::get( 'telegram_proxy_host', '' ) );
		$port = (int) SimpleVPBot_Settings::get( 'telegram_proxy_port', 0 );
		if ( '' === $host || $port < 1 ) {
			return;
		}
		$user = trim( (string) SimpleVPBot_Settings::get( 'telegram_proxy_username', '' ) );
		$pass = (string) SimpleVPBot_Settings::get( 'telegram_proxy_password', '' );
		$proxy = 'socks5://';
		if ( '' !== $user ) {
			$proxy .= rawurlencode( $user );
			if ( '' !== $pass ) {
				$proxy .= ':' . rawurlencode( $pass );
			}
			$proxy .= '@';
		}
		$proxy .= $host . ':' . $port;
		curl_setopt( $handle, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt
		curl_setopt( $handle, CURLOPT_PROXY, $proxy ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt
	}

	/**
	 * Test Bot API connectivity (getMe).
	 *
	 * @return array{ok:bool, message?:string, username?:string}
	 */
	public static function test_connection() {
		$token = trim( (string) SimpleVPBot_Settings::get( 'telegram_token', '' ) );
		if ( '' === $token ) {
			return array( 'ok' => false, 'message' => 'no_token' );
		}
		if ( ! class_exists( 'SimpleVPBot_Telegram_Client' ) ) {
			return array( 'ok' => false, 'message' => 'no_client' );
		}
		$client = new SimpleVPBot_Telegram_Client( $token );
		$res    = $client->call( 'getMe', array(), 25 );
		if ( ! empty( $res['ok'] ) && is_array( $res['result'] ?? null ) ) {
			$uname = (string) ( $res['result']['username'] ?? '' );
			return array(
				'ok'       => true,
				'message'  => 'ok',
				'username' => $uname,
			);
		}
		$desc = isset( $res['description'] ) ? (string) $res['description'] : 'request_failed';
		return array( 'ok' => false, 'message' => $desc );
	}
}
