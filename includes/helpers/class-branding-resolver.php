<?php
/**
 * Resolve site / reseller branding (logo, theme colors, custom domain).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Branding_Resolver
 */
class SimpleVPBot_Branding_Resolver {

	/**
	 * Normalize host for comparison (lowercase, no port).
	 *
	 * @param string $host Host header or domain setting.
	 * @return string
	 */
	public static function normalize_host( $host ) {
		$h = strtolower( trim( (string) $host ) );
		if ( '' === $h ) {
			return '';
		}
		$h = preg_replace( '#:\d+$#', '', $h );
		return is_string( $h ) ? $h : '';
	}

	/**
	 * Current request HTTP host.
	 *
	 * @return string
	 */
	public static function request_host() {
		if ( empty( $_SERVER['HTTP_HOST'] ) ) {
			return '';
		}
		return self::normalize_host( wp_unslash( (string) $_SERVER['HTTP_HOST'] ) );
	}

	/**
	 * Branding for HTTP request (custom domain → reseller or main).
	 *
	 * @return array<string, mixed>
	 */
	public static function resolve_for_request() {
		$host = self::request_host();
		if ( '' !== $host ) {
			$by_domain = self::resolve_by_custom_domain( $host );
			if ( ! empty( $by_domain ) ) {
				return $by_domain;
			}
		}
		return self::resolve_main();
	}

	/**
	 * Main site branding from settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function resolve_main() {
		$s = SimpleVPBot_Settings::all();
		$name = trim( (string) ( $s['dashboard_site_name'] ?? '' ) );
		if ( '' === $name ) {
			$name = (string) get_bloginfo( 'name' );
		}
		$icon = trim( (string) ( $s['dashboard_site_icon_url'] ?? '' ) );
		$logo = trim( (string) ( $s['branding_logo_url'] ?? '' ) );
		if ( '' === $logo ) {
			$logo = $icon;
		}
		$fav = trim( (string) ( $s['branding_favicon_url'] ?? '' ) );
		if ( '' === $fav ) {
			$fav = $icon;
		}
		return self::pack(
			0,
			'main',
			$name,
			$logo,
			$fav,
			(string) ( $s['branding_theme_primary'] ?? '' ),
			(string) ( $s['branding_theme_accent'] ?? '' ),
			(string) ( $s['branding_custom_domain'] ?? '' )
		);
	}

	/**
	 * Reseller branding (profile overrides + user display name).
	 *
	 * @param int $reseller_svp_user_id Reseller id.
	 * @return array<string, mixed>
	 */
	public static function resolve_for_reseller( $reseller_svp_user_id ) {
		$rid = (int) $reseller_svp_user_id;
		if ( $rid < 1 ) {
			return self::resolve_main();
		}
		$prof = class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' )
			? SimpleVPBot_Model_Reseller_Bot_Profile::find_by_reseller( $rid )
			: null;
		$name = '';
		if ( class_exists( 'SimpleVPBot_Reseller_Branding' ) ) {
			$name = SimpleVPBot_Reseller_Branding::display_brand_for_reseller( $rid );
		}
		$logo   = $prof ? trim( (string) ( $prof->logo_url ?? '' ) ) : '';
		$fav    = $prof ? trim( (string) ( $prof->favicon_url ?? '' ) ) : '';
		$prim   = $prof ? trim( (string) ( $prof->theme_primary ?? '' ) ) : '';
		$accent = $prof ? trim( (string) ( $prof->theme_accent ?? '' ) ) : '';
		$domain = $prof ? trim( (string) ( $prof->custom_domain ?? '' ) ) : '';
		$packed = self::pack( $rid, 'reseller', $name, $logo, $fav, $prim, $accent, $domain );
		if ( '' === $packed['siteName'] ) {
			$main = self::resolve_main();
			$packed['siteName'] = (string) $main['siteName'];
		}
		if ( '' === $packed['logoUrl'] ) {
			$packed['logoUrl'] = (string) ( self::resolve_main()['logoUrl'] ?? '' );
		}
		return $packed;
	}

	/**
	 * Nearest reseller brand for an end user.
	 *
	 * @param int $svp_user_id User id.
	 * @return array<string, mixed>
	 */
	public static function resolve_for_user( $svp_user_id ) {
		$uid = (int) $svp_user_id;
		if ( $uid < 1 ) {
			return self::resolve_main();
		}
		if ( class_exists( 'SimpleVPBot_Reseller_Branding' ) ) {
			$rid = (int) SimpleVPBot_Reseller_Branding::nearest_reseller_id_for_user( $uid );
			if ( $rid > 0 ) {
				return self::resolve_for_reseller( $rid );
			}
		}
		return self::resolve_main();
	}

	/**
	 * Branding for logged-in dashboard actor.
	 *
	 * @return array<string, mixed>
	 */
	public static function resolve_for_dashboard_actor() {
		if ( ! class_exists( 'SimpleVPBot_Rest_Dashboard' ) ) {
			return self::resolve_main();
		}
		$ctx = SimpleVPBot_Rest_Dashboard::dashboard_actor_context();
		if ( ! empty( $ctx['isReseller'] ) && ! empty( $ctx['actorUserId'] ) ) {
			return self::resolve_for_reseller( (int) $ctx['actorUserId'] );
		}
		if ( ! empty( $ctx['impersonating'] ) && ! empty( $ctx['impersonationTargetId'] ) ) {
			return self::resolve_for_reseller( (int) $ctx['impersonationTargetId'] );
		}
		return self::resolve_main();
	}

	/**
	 * CSS custom properties for SPA shell.
	 *
	 * @param array<string, mixed> $branding Packed branding array.
	 * @return array<string, string>
	 */
	public static function to_css_variables( array $branding ) {
		$prim   = self::sanitize_hex_color( (string) ( $branding['themePrimary'] ?? '' ) );
		$accent = self::sanitize_hex_color( (string) ( $branding['themeAccent'] ?? '' ) );
		$vars   = array();
		if ( '' !== $prim ) {
			$vars['--svp-brand-primary'] = $prim;
		}
		if ( '' !== $accent ) {
			$vars['--svp-brand-accent'] = $accent;
		}
		return $vars;
	}

	/**
	 * Find reseller/main by custom domain host.
	 *
	 * @param string $host Normalized host.
	 * @return array<string, mixed>
	 */
	private static function resolve_by_custom_domain( $host ) {
		$host = self::normalize_host( $host );
		if ( '' === $host ) {
			return array();
		}
		$main = self::resolve_main();
		if ( self::normalize_host( (string) ( $main['customDomain'] ?? '' ) ) === $host ) {
			return $main;
		}
		if ( ! class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
			return array();
		}
		global $wpdb;
		$t = SimpleVPBot_Model_Reseller_Bot_Profile::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rid = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT reseller_svp_user_id FROM {$t} WHERE custom_domain = %s LIMIT 1",
				$host
			)
		);
		if ( $rid > 0 ) {
			return self::resolve_for_reseller( $rid );
		}
		return array();
	}

	/**
	 * @param int    $reseller_id 0 = main.
	 * @param string $scope main|reseller.
	 * @param string $site_name Display name.
	 * @param string $logo_url Logo URL.
	 * @param string $favicon_url Favicon.
	 * @param string $primary Primary hex.
	 * @param string $accent Accent hex.
	 * @param string $custom_domain Host only.
	 * @return array<string, mixed>
	 */
	private static function pack( $reseller_id, $scope, $site_name, $logo_url, $favicon_url, $primary, $accent, $custom_domain ) {
		return array(
			'resellerId'    => (int) $reseller_id,
			'scope'         => (string) $scope,
			'siteName'      => sanitize_text_field( (string) $site_name ),
			'logoUrl'       => esc_url_raw( (string) $logo_url ),
			'faviconUrl'    => esc_url_raw( (string) $favicon_url ),
			'themePrimary'  => self::sanitize_hex_color( (string) $primary ),
			'themeAccent'   => self::sanitize_hex_color( (string) $accent ),
			'customDomain'  => self::normalize_host( (string) $custom_domain ),
			'cssVariables'  => self::to_css_variables(
				array(
					'themePrimary' => $primary,
					'themeAccent'  => $accent,
				)
			),
		);
	}

	/**
	 * @param string $color Raw color.
	 * @return string Hex or empty.
	 */
	private static function sanitize_hex_color( $color ) {
		$c = trim( (string) $color );
		if ( '' === $c ) {
			return '';
		}
		if ( preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $c ) ) {
			return strtolower( $c );
		}
		return '';
	}
}
