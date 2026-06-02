<?php
/**
 * Platform slug vs legacy service naming (provision + display).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Service_Naming
 */
class SimpleVPBot_Service_Naming {

	/**
	 * @return string legacy|platform_slug|prefix_numbered|numbered
	 */
	public static function mode() {
		if ( ! class_exists( 'SimpleVPBot_Settings' ) ) {
			return 'legacy';
		}
		$m = sanitize_key( (string) SimpleVPBot_Settings::get( 'service_naming_mode', 'legacy' ) );
		return in_array( $m, array( 'legacy', 'platform_slug', 'prefix_numbered', 'numbered' ), true ) ? $m : 'legacy';
	}

	/**
	 * Whether new provisions should use platform_slug naming.
	 *
	 * @return bool
	 */
	public static function uses_platform_slug_for_new() {
		return 'platform_slug' === self::mode();
	}

	/**
	 * Whether new provisions should use prefix_numbered naming.
	 *
	 * @return bool
	 */
	public static function uses_prefix_numbered_for_new() {
		return 'prefix_numbered' === self::mode();
	}

	/**
	 * Whether new provisions should use numbered-only naming.
	 *
	 * @return bool
	 */
	public static function uses_numbered_for_new() {
		return 'numbered' === self::mode();
	}

	/**
	 * Whether config list labels use prefix_numbered pattern.
	 *
	 * @return bool
	 */
	public static function uses_prefix_numbered_labels() {
		return self::uses_prefix_numbered_for_new();
	}

	/**
	 * Whether config list labels use numbered-only pattern.
	 *
	 * @return bool
	 */
	public static function uses_numbered_labels() {
		return self::uses_numbered_for_new();
	}

	/**
	 * Starting number for prefix_numbered / numbered labels (site setting).
	 *
	 * @return int
	 */
	public static function config_label_number_start() {
		if ( ! class_exists( 'SimpleVPBot_Settings' ) ) {
			return 1001;
		}
		return max( 1, (int) SimpleVPBot_Settings::get( 'config_label_number_start', 1001 ) );
	}

	/**
	 * Build one prefix_numbered label, e.g. Heydas-1001.
	 *
	 * @param string $prefix     Non-empty prefix.
	 * @param int    $line_index 1-based line index within a service subscription.
	 * @return string
	 */
	public static function format_prefix_numbered_label( $prefix, $line_index = 1 ) {
		$pref = trim( (string) $prefix );
		if ( '' === $pref ) {
			return '';
		}
		$n = self::config_label_number_start() + max( 0, (int) $line_index - 1 );
		return $pref . '-' . $n;
	}

	/**
	 * Build one numbered-only label, e.g. 1001.
	 *
	 * @param int $line_index 1-based line index.
	 * @return string
	 */
	public static function format_numbered_label( $line_index = 1 ) {
		$n = self::config_label_number_start() + max( 0, (int) $line_index - 1 );
		return (string) $n;
	}

	/**
	 * Build one prefix_numbered label from an explicit number.
	 *
	 * @param string $prefix Non-empty prefix.
	 * @param int    $number Absolute number.
	 * @return string
	 */
	public static function format_prefix_numbered_label_from_number( $prefix, $number ) {
		$pref = trim( (string) $prefix );
		$n    = max( 1, (int) $number );
		if ( '' === $pref ) {
			return '';
		}
		return $pref . '-' . $n;
	}

	/**
	 * Next persistent service number for numbered/prefix_numbered provision.
	 *
	 * @param int    $svp_user_id Service owner id (for prefix resolution).
	 * @param string $prefix      Effective prefix in prefix_numbered mode.
	 * @return int
	 */
	public static function next_service_number_for_new( $svp_user_id = 0, $prefix = '' ) {
		global $wpdb;
		$mode  = self::mode();
		$start = self::config_label_number_start();
		$t     = $wpdb->prefix . 'svp_services';
		$max_n = $start - 1;

		$rows = array();
		if ( 'numbered' === $mode ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = (array) $wpdb->get_results(
				"SELECT remark,email FROM {$t} WHERE deleted_at IS NULL AND (remark REGEXP '^[0-9]+$' OR email REGEXP '^[0-9]+$')",
				ARRAY_A
			);
			foreach ( $rows as $r ) {
				foreach ( array( 'remark', 'email' ) as $k ) {
					$v = trim( (string) ( $r[ $k ] ?? '' ) );
					if ( preg_match( '/^\d+$/', $v ) ) {
						$max_n = max( $max_n, (int) $v );
					}
				}
			}
			return max( $start, $max_n + 1 );
		}

		if ( 'prefix_numbered' === $mode ) {
			$uid  = max( 0, (int) $svp_user_id );
			$pref = trim( (string) $prefix );
			if ( '' === $pref && class_exists( 'SimpleVPBot_Reseller_Branding' ) ) {
				$pref = trim( (string) SimpleVPBot_Reseller_Branding::config_label_prefix_for_user( $uid ) );
			}
			if ( '' === $pref ) {
				return $start;
			}
			$quoted  = preg_quote( $pref, '/' );
			$pattern = '/^' . $quoted . '-(\d+)$/';
			$sql_re  = '^' . $pref . '-[0-9]+$';
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = (array) $wpdb->get_results(
				$wpdb->prepare(
					"SELECT remark,email FROM {$t} WHERE deleted_at IS NULL AND (remark REGEXP %s OR email REGEXP %s)",
					$sql_re,
					$sql_re
				),
				ARRAY_A
			);
			foreach ( $rows as $r ) {
				foreach ( array( 'remark', 'email' ) as $k ) {
					$v = trim( (string) ( $r[ $k ] ?? '' ) );
					if ( preg_match( $pattern, $v, $m ) ) {
						$max_n = max( $max_n, (int) $m[1] );
					}
				}
			}
			return max( $start, $max_n + 1 );
		}

		return $start;
	}

	/**
	 * Prepend inbound display name to a suffix.
	 *
	 * @param string $inbound_display Inbound alias or panel remark.
	 * @param string $suffix          Service naming suffix.
	 * @return string
	 */
	public static function format_with_inbound( $inbound_display, $suffix ) {
		$inb = trim( (string) $inbound_display );
		$suf = trim( (string) $suffix );
		if ( '' === $suf ) {
			return $inb;
		}
		if ( '' === $inb ) {
			return $suf;
		}
		return $inb . ' - ' . $suf;
	}

	/**
	 * Build naming context from a service row.
	 *
	 * @param object|array<string,mixed>|null $svc Service row.
	 * @return array<string, mixed>
	 */
	public static function context_from_service( $svc ) {
		if ( ! $svc ) {
			return array();
		}
		$panel_id   = is_array( $svc ) ? (int) ( $svc['panel_id'] ?? 0 ) : (int) ( $svc->panel_id ?? 0 );
		$inbound_id = is_array( $svc ) ? (int) ( $svc['inbound_id'] ?? 0 ) : (int) ( $svc->inbound_id ?? 0 );
		$ctx        = array(
			'panel_id'   => $panel_id,
			'inbound_id' => $inbound_id,
			'svc'        => $svc,
		);
		if ( $panel_id > 0 && class_exists( 'SimpleVPBot_Config_Inbound_Match' ) ) {
			$ctx['inbound_catalog'] = SimpleVPBot_Config_Inbound_Match::inbound_catalog_for_panel( $panel_id );
		}
		return $ctx;
	}

	/**
	 * Canonical name stored in svp_services.remark (source of truth).
	 *
	 * @param object|array<string,mixed>|null $svc Service row.
	 * @return string
	 */
	public static function canonical_label_for_service( $svc ) {
		if ( ! $svc ) {
			return '';
		}
		return is_array( $svc ) ? trim( (string) ( $svc['remark'] ?? '' ) ) : trim( (string) ( $svc->remark ?? '' ) );
	}

	/**
	 * Bot-only display name; falls back to canonical remark.
	 *
	 * @param object|array<string,mixed>|null $svc Service row.
	 * @return string
	 */
	public static function public_label_for_service( $svc ) {
		if ( ! $svc ) {
			return '';
		}
		$display = is_array( $svc ) ? trim( (string) ( $svc['display_label'] ?? '' ) ) : trim( (string) ( $svc->display_label ?? '' ) );
		if ( '' !== $display ) {
			return $display;
		}
		return self::canonical_label_for_service( $svc );
	}

	/**
	 * Canonical label for a new provision (per site naming mode).
	 *
	 * @param object|null $user        User row.
	 * @param string|null $platform    telegram|bale.
	 * @param int         $line_index  1-based line index.
	 * @return string
	 */
	public static function provision_canonical_label( $user, $platform = null, $line_index = 1 ) {
		$uid = is_object( $user ) ? (int) ( $user->id ?? 0 ) : 0;
		$idx = max( 1, (int) $line_index );

		if ( self::uses_platform_slug_for_new() ) {
			return self::generate_platform_slug( $user, $platform );
		}
		if ( self::uses_numbered_for_new() ) {
			$next  = self::next_service_number_for_new( $uid );
			$label = (string) max( 1, (int) $next );
			if ( '' !== $label ) {
				return $label;
			}
		}
		if ( self::uses_prefix_numbered_for_new() && class_exists( 'SimpleVPBot_Reseller_Branding' ) ) {
			$prefix = trim( (string) SimpleVPBot_Reseller_Branding::config_label_prefix_for_user( $uid ) );
			$next   = self::next_service_number_for_new( $uid, $prefix );
			$label  = self::format_prefix_numbered_label_from_number( $prefix, $next );
			if ( '' !== $label ) {
				return $label;
			}
		}
		return self::generate_legacy_canonical_email( $uid );
	}

	/**
	 * Unique panel client email for 3x-ui API (may differ from canonical display name).
	 *
	 * @param object|null $user      User row.
	 * @param string      $canonical Canonical label from provision_canonical_label().
	 * @param string|null $platform  telegram|bale.
	 * @return string
	 */
	public static function provision_panel_email( $user, $canonical, $platform = null ) {
		$canonical = trim( (string) $canonical );
		$uid       = is_object( $user ) ? (int) ( $user->id ?? 0 ) : 0;

		if ( self::uses_platform_slug_for_new() ) {
			if ( false !== strpos( $canonical, '@' ) ) {
				return strtolower( $canonical );
			}
			return strtolower( $canonical ) . '@svp.local';
		}
		if ( 'legacy' === self::mode() ) {
			if ( false !== strpos( $canonical, '@' ) ) {
				return strtolower( $canonical );
			}
			return self::generate_legacy_canonical_email( $uid );
		}
		if ( self::uses_prefix_numbered_for_new() || self::uses_numbered_for_new() ) {
			if ( '' === $canonical ) {
				return self::generate_legacy_canonical_email( $uid );
			}
			return self::unique_panel_client_id( $canonical );
		}
		return self::generate_internal_panel_email( $uid, $canonical );
	}

	/**
	 * Sanitize canonical label for use as 3x-ui client email (no @domain).
	 *
	 * @param string $canonical Canonical or legacy email string.
	 * @return string
	 */
	public static function sanitize_panel_client_id( $canonical ) {
		$s = trim( (string) $canonical );
		if ( '' === $s ) {
			return '';
		}
		if ( false !== strpos( $s, '@' ) ) {
			$s = trim( substr( $s, 0, (int) strpos( $s, '@' ) ) );
		}
		$s = preg_replace( '/\s+/', '', $s );
		return trim( (string) $s );
	}

	/**
	 * Whether a panel client id is already used (email or remark column).
	 *
	 * @param string $client_id          Panel client identifier.
	 * @param int    $exclude_service_id Optional svp_services.id to ignore (rename same row).
	 * @return bool
	 */
	public static function panel_client_id_taken( $client_id, $exclude_service_id = 0 ) {
		global $wpdb;
		$client_id = trim( (string) $client_id );
		if ( '' === $client_id ) {
			return true;
		}
		$exclude = max( 0, (int) $exclude_service_id );
		$t       = $wpdb->prefix . 'svp_services';
		if ( $exclude > 0 ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$n = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$t} WHERE deleted_at IS NULL AND id != %d AND (email = %s OR remark = %s) LIMIT 1",
					$exclude,
					$client_id,
					$client_id
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$n = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$t} WHERE deleted_at IS NULL AND (email = %s OR remark = %s) LIMIT 1",
					$client_id,
					$client_id
				)
			);
		}
		return $n > 0;
	}

	/**
	 * Build next numbered candidate from canonical base.
	 *
	 * Examples:
	 * - Heydas-1001 + 1 => Heydas-1002
	 * - 1001 + 2 => 1003
	 *
	 * @param string $base   Canonical base id.
	 * @param int    $offset Increment offset (>=1).
	 * @return string Empty when base is not numbered.
	 */
	public static function next_numbered_candidate( $base, $offset = 1 ) {
		$base   = trim( (string) $base );
		$offset = max( 1, (int) $offset );
		if ( '' === $base ) {
			return '';
		}
		if ( preg_match( '/^(\d+)$/', $base, $m ) ) {
			return (string) ( (int) $m[1] + $offset );
		}
		if ( preg_match( '/^(.*?)-(\d+)$/', $base, $m ) ) {
			$prefix = trim( (string) $m[1] );
			$num    = (int) $m[2];
			if ( '' === $prefix ) {
				return '';
			}
			return $prefix . '-' . (string) ( $num + $offset );
		}
		return '';
	}

	/**
	 * Unique 3x-ui client email = canonical label (Heydas-1001), with numeric increment on collision.
	 *
	 * @param string $canonical          Canonical label.
	 * @param int    $exclude_service_id Optional svp_services.id to ignore.
	 * @return string
	 */
	public static function unique_panel_client_id( $canonical, $exclude_service_id = 0 ) {
		$base = self::sanitize_panel_client_id( $canonical );
		if ( '' === $base ) {
			return '';
		}
		if ( ! self::panel_client_id_taken( $base, $exclude_service_id ) ) {
			return $base;
		}
		for ( $i = 1; $i <= 20; $i++ ) {
			$candidate = self::next_numbered_candidate( $base, $i );
			if ( '' === $candidate ) {
				$candidate = $base . '-' . ( $i + 1 );
			}
			if ( ! self::panel_client_id_taken( $candidate, $exclude_service_id ) ) {
				return $candidate;
			}
		}
		return $base . '-' . strtolower( wp_generate_password( 4, false, false ) );
	}

	/**
	 * True when stored email is legacy internal u{id}-…@svp.local.
	 *
	 * @param string $email Panel client email from DB or API.
	 * @return bool
	 */
	public static function is_internal_panel_email( $email ) {
		$em = strtolower( trim( (string) $email ) );
		return '' !== $em && (bool) preg_match( '/^u\d+[-_][^@]+@svp\.local$/', $em );
	}

	/**
	 * User-visible client name: canonical remark, never internal @svp.local email.
	 *
	 * @param object|array<string,mixed>|null $svc Service row.
	 * @return string
	 */
	public static function display_panel_client_name( $svc ) {
		$canonical = self::canonical_label_for_service( $svc );
		if ( '' !== $canonical && ! self::is_internal_panel_email( $canonical ) ) {
			return $canonical;
		}
		if ( ! $svc ) {
			return '';
		}
		$email = is_array( $svc ) ? trim( (string) ( $svc['email'] ?? '' ) ) : trim( (string) ( $svc->email ?? '' ) );
		if ( '' !== $email && ! self::is_internal_panel_email( $email ) ) {
			return $email;
		}
		return $canonical;
	}

	/**
	 * Legacy canonical + panel email: u{id}-{slug}@svp.local.
	 *
	 * @param int $user_id svp_users.id.
	 * @return string
	 */
	public static function generate_legacy_canonical_email( $user_id ) {
		$user_id = max( 1, (int) $user_id );
		for ( $i = 0; $i < 12; $i++ ) {
			$slug = strtolower( wp_generate_password( 8, false, false ) );
			$slug = preg_replace( '/[^a-z0-9]/', '', $slug );
			if ( ! is_string( $slug ) || strlen( $slug ) < 4 ) {
				$slug = strtolower( wp_generate_password( 8, false, false ) );
			}
			$email = 'u' . $user_id . '-' . $slug . '@svp.local';
			if ( ! self::panel_email_taken( $email ) ) {
				return $email;
			}
		}
		return 'u' . $user_id . '-' . strtolower( wp_generate_password( 8, false, false ) ) . '@svp.local';
	}

	/**
	 * Internal unique email when canonical label is not an email (numbered / prefix modes).
	 *
	 * @param int    $user_id   svp_users.id.
	 * @param string $canonical Canonical remark.
	 * @return string
	 */
	public static function generate_internal_panel_email( $user_id, $canonical = '' ) {
		$user_id = max( 1, (int) $user_id );
		$base    = 'u' . $user_id . '-';
		$hint    = strtolower( preg_replace( '/[^a-z0-9]+/', '', (string) $canonical ) );
		if ( strlen( $hint ) > 12 ) {
			$hint = substr( $hint, 0, 12 );
		}
		for ( $i = 0; $i < 12; $i++ ) {
			$slug  = '' !== $hint && 0 === $i ? $hint : strtolower( wp_generate_password( 6, false, false ) );
			$slug  = preg_replace( '/[^a-z0-9]/', '', (string) $slug );
			$email = $base . ( '' !== $slug ? $slug : wp_generate_password( 6, false, false ) ) . '@svp.local';
			if ( ! self::panel_email_taken( $email ) ) {
				return $email;
			}
			$hint = '';
		}
		return $base . strtolower( wp_generate_password( 8, false, false ) ) . '@svp.local';
	}

	/**
	 * @param string $email Panel client email.
	 * @return bool
	 */
	public static function panel_email_taken( $email ) {
		return self::panel_client_id_taken( $email );
	}

	/**
	 * Base suffix for one subscription line (no inbound prefix).
	 *
	 * @param int                             $line_index  1-based.
	 * @param int                             $svp_user_id Service owner.
	 * @param object|array<string,mixed>|null $svc         Service row.
	 * @param string                          $uri         Unused; kept for signature compatibility.
	 * @param string                          $override    Config label override if any.
	 * @return string
	 */
	public static function base_suffix_for_line( $line_index, $svp_user_id, $svc, $uri = '', $override = '' ) {
		$idx = max( 1, (int) $line_index );
		$ov  = trim( (string) $override );
		if ( '' !== $ov ) {
			return $ov;
		}

		$canonical = self::canonical_label_for_service( $svc );
		if ( '' !== $canonical && ! self::is_internal_panel_email( $canonical ) ) {
			return $canonical;
		}

		if ( self::uses_numbered_labels() ) {
			return self::format_numbered_label( $idx );
		}

		if ( self::uses_prefix_numbered_labels() && class_exists( 'SimpleVPBot_Reseller_Branding' ) ) {
			$prefix = trim( (string) SimpleVPBot_Reseller_Branding::config_label_prefix_for_user( (int) $svp_user_id ) );
			if ( '' !== $prefix ) {
				return self::format_prefix_numbered_label( $prefix, $idx );
			}
		}

		return '';
	}

	/**
	 * @param string $remark Service remark or slug.
	 * @param string $email  Optional client email.
	 * @return bool
	 */
	public static function is_platform_slug_remark( $remark, $email = '' ) {
		$rm = strtolower( trim( (string) $remark ) );
		if ( '' !== $rm && preg_match( '/^bot-(t|b|u)\d+-/', $rm ) ) {
			return true;
		}
		$em = strtolower( trim( (string) $email ) );
		if ( '' !== $em && false !== strpos( $em, '@' ) ) {
			$local = substr( $em, 0, strpos( $em, '@' ) );
			if ( preg_match( '/^bot-(t|b|u)\d+-/', $local ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether an existing service row uses platform_slug identifiers.
	 *
	 * @param object|array<string,mixed>|null $svc Service row.
	 * @return bool
	 */
	public static function is_platform_slug_service( $svc ) {
		if ( ! $svc ) {
			return false;
		}
		$remark = is_array( $svc ) ? (string) ( $svc['remark'] ?? '' ) : (string) ( $svc->remark ?? '' );
		$email  = is_array( $svc ) ? (string) ( $svc['email'] ?? '' ) : (string) ( $svc->email ?? '' );
		return self::is_platform_slug_remark( $remark, $email );
	}

	/**
	 * UI / bot list display name (bot display_label or canonical remark).
	 *
	 * @param object|array<string,mixed>|null $svc Service row.
	 * @return string
	 */
	public static function display_name_for_service( $svc ) {
		return self::public_label_for_service( $svc );
	}

	/**
	 * Canonical subscription token for display (sub_id column).
	 *
	 * @param object|array<string,mixed>|null $svc Service row.
	 * @return string
	 */
	public static function subscription_id_for_service( $svc ) {
		if ( ! $svc ) {
			return '';
		}
		$sub = is_array( $svc ) ? trim( (string) ( $svc['sub_id'] ?? '' ) ) : trim( (string) ( $svc->sub_id ?? '' ) );
		if ( '' !== $sub ) {
			return $sub;
		}
		$canonical = self::canonical_label_for_service( $svc );
		if ( '' !== $canonical && ! self::is_internal_panel_email( $canonical ) ) {
			return $canonical;
		}
		$email = is_array( $svc ) ? trim( (string) ( $svc['email'] ?? '' ) ) : trim( (string) ( $svc->email ?? '' ) );
		if ( '' !== $email && ! self::is_internal_panel_email( $email ) ) {
			return $email;
		}
		return '';
	}

	/**
	 * Labels from raw subscription URI fragments (#...), with inbound prefix.
	 *
	 * @param array<int, string>   $uris_raw    Lines from panel subscription.
	 * @param int                  $svp_user_id Service owner (for reseller/site override).
	 * @param array<string, mixed> $context     panel_id, inbound_id, svc, inbound_catalog.
	 * @return array<int, string>
	 */
	public static function config_labels_from_uris( array $uris_raw, $svp_user_id = 0, array $context = array() ) {
		$uris = array_values( array_filter( array_map( 'strval', $uris_raw ) ) );
		if ( empty( $uris ) ) {
			return array();
		}

		$svc = isset( $context['svc'] ) ? $context['svc'] : null;
		if ( ! $svc && isset( $context['service'] ) ) {
			$svc = $context['service'];
		}

		$panel_id   = (int) ( $context['panel_id'] ?? 0 );
		$inbound_id = (int) ( $context['inbound_id'] ?? 0 );
		if ( $panel_id < 1 && $svc ) {
			$panel_id = is_array( $svc ) ? (int) ( $svc['panel_id'] ?? 0 ) : (int) ( $svc->panel_id ?? 0 );
		}
		if ( $inbound_id < 1 && $svc ) {
			$inbound_id = is_array( $svc ) ? (int) ( $svc['inbound_id'] ?? 0 ) : (int) ( $svc->inbound_id ?? 0 );
		}

		$override = '';
		if ( $svp_user_id > 0 && class_exists( 'SimpleVPBot_Reseller_Branding' ) ) {
			$override = trim( (string) SimpleVPBot_Reseller_Branding::config_label_override_for_user( (int) $svp_user_id ) );
		}

		$multi  = count( $uris ) > 1;
		$labels = array();
		$idx    = 1;

		foreach ( $uris as $u ) {
			if ( '' === (string) $u ) {
				continue;
			}
			$line_override = $override;
			if ( '' !== $line_override && $multi ) {
				$line_override = $override . '-' . $idx;
			}

			$iid = class_exists( 'SimpleVPBot_Config_Inbound_Match' )
				? SimpleVPBot_Config_Inbound_Match::inbound_id_for_uri( (string) $u, $panel_id, $inbound_id, $context )
				: $inbound_id;

			$suffix = self::base_suffix_for_line( $idx, (int) $svp_user_id, $svc, (string) $u, $line_override );

			$inbound_display = '';
			if (
				$iid > 0
				&& class_exists( 'SimpleVPBot_Settings' )
				&& SimpleVPBot_Settings::config_label_prepend_inbound()
				&& class_exists( 'SimpleVPBot_Inbound_Display_Name' )
			) {
				$inbound_display = SimpleVPBot_Inbound_Display_Name::for_config_label( $panel_id, $iid, (int) $svp_user_id );
			}

			$labels[] = self::format_with_inbound( $inbound_display, $suffix );
			$idx++;
		}

		return $labels;
	}

	/**
	 * @param object|array<string,mixed>|null $svc Service row.
	 * @return int
	 */
	private static function service_owner_user_id( $svc ) {
		if ( ! $svc ) {
			return 0;
		}
		return is_array( $svc ) ? (int) ( $svc['user_id'] ?? 0 ) : (int) ( $svc->user_id ?? 0 );
	}

	/**
	 * Unified subscription view fields for bot / portal / dashboard.
	 *
	 * @param object|array<string,mixed>|null $svc      Service row.
	 * @param array<int, string>            $uris_raw Raw subscription config URIs (no rewrite).
	 * @return array{subscription_id: string, subscription_name: string, config_uris: array<int, string>, config_labels: array<int, string>, remark: string, sub_id: string}
	 */
	public static function enrich_subscription_view( $svc, array $uris_raw = array() ) {
		$uris      = array_values( array_filter( array_map( 'strval', $uris_raw ) ) );
		$canonical = self::canonical_label_for_service( $svc );
		$public    = self::public_label_for_service( $svc );
		$uid       = self::service_owner_user_id( $svc );
		$labels    = self::config_labels_from_uris( $uris, $uid, self::context_from_service( $svc ) );
		$tech_sub  = self::subscription_id_for_service( $svc );
		return array(
			'subscription_id'   => '',
			'subscription_name' => $canonical,
			'display_label'     => $public,
			'config_uris'       => $uris,
			'config_labels'     => $labels,
			'remark'            => $canonical,
			'sub_id'            => $tech_sub,
		);
	}

	/**
	 * Subscription/config URI #fragment for a service.
	 *
	 * @param int                             $svp_user_id Service owner.
	 * @param object|array<string,mixed>|null $svc         Service row.
	 * @return string Empty = keep panel default.
	 */
	public static function subscription_fragment_for_service( $svp_user_id, $svc ) {
		if ( ! class_exists( 'SimpleVPBot_Reseller_Branding' ) ) {
			return '';
		}
		$uid = (int) $svp_user_id;
		if ( self::is_platform_slug_service( $svc ) ) {
			$brand = SimpleVPBot_Reseller_Branding::panel_brand_only_for_user( $uid );
			return trim( (string) $brand );
		}
		$remark = is_array( $svc ) ? (string) ( $svc['remark'] ?? '' ) : (string) ( $svc->remark ?? '' );
		return SimpleVPBot_Reseller_Branding::fragment_for_service( $uid, $remark );
	}

	/**
	 * Panel client remark for renew/rebuild/patch.
	 *
	 * @param int                             $svp_user_id Service owner.
	 * @param object|array<string,mixed>|null $svc         Service row.
	 * @return string
	 */
	public static function panel_remark_for_service( $svp_user_id, $svc ) {
		unset( $svp_user_id );
		return self::canonical_label_for_service( $svc );
	}

	/**
	 * Auto service note with user identifiers.
	 *
	 * @param object|null $user User row.
	 * @return string
	 */
	public static function build_auto_service_note( $user ) {
		$uid = is_object( $user ) ? (int) ( $user->id ?? 0 ) : 0;
		$tg  = is_object( $user ) ? (int) ( $user->tg_user_id ?? 0 ) : 0;
		$bl  = is_object( $user ) ? (int) ( $user->bale_user_id ?? 0 ) : 0;
		return 'SVP:' . $uid . ' TG:' . $tg . ' BL:' . $bl;
	}

	/**
	 * Generate unique bot-t* / bot-b* service slug.
	 *
	 * @param object|null $user     User row.
	 * @param string|null $platform telegram|bale.
	 * @return string
	 */
	public static function generate_platform_slug( $user, $platform = null ) {
		$plat = strtolower( trim( (string) $platform ) );
		$tg   = is_object( $user ) ? (int) ( $user->tg_user_id ?? 0 ) : 0;
		$bl   = is_object( $user ) ? (int) ( $user->bale_user_id ?? 0 ) : 0;
		$uid  = is_object( $user ) ? (int) ( $user->id ?? 0 ) : 0;
		if ( 'bale' === $plat && $bl > 0 ) {
			$prefix = 'bot-b' . $bl;
		} elseif ( 'telegram' === $plat && $tg > 0 ) {
			$prefix = 'bot-t' . $tg;
		} elseif ( $tg > 0 ) {
			$prefix = 'bot-t' . $tg;
		} elseif ( $bl > 0 ) {
			$prefix = 'bot-b' . $bl;
		} else {
			$prefix = 'bot-u' . $uid;
		}
		for ( $i = 0; $i < 12; $i++ ) {
			$slug = strtolower( $prefix . '-' . wp_generate_password( 8, false, false ) );
			if ( ! self::platform_slug_taken( $slug ) ) {
				return $slug;
			}
		}
		return strtolower( $prefix . '-' . wp_generate_password( 8, false, false ) );
	}

	/**
	 * @param string $slug Service slug (without @domain).
	 * @return bool
	 */
	public static function platform_slug_taken( $slug ) {
		global $wpdb;
		$slug = strtolower( trim( (string) $slug ) );
		if ( '' === $slug ) {
			return true;
		}
		$t = $wpdb->prefix . 'svp_services';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$n = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$t} WHERE remark = %s OR email = %s LIMIT 1",
				$slug,
				$slug . '@svp.local'
			)
		);
		return $n > 0;
	}

	/**
	 * Read checkout platform from transaction meta.
	 *
	 * @param array<string, mixed> $meta Decoded meta_json.
	 * @return string|null telegram|bale|null
	 */
	public static function platform_from_meta( array $meta ) {
		$p = sanitize_key( (string) ( $meta['platform'] ?? '' ) );
		return in_array( $p, array( 'telegram', 'bale' ), true ) ? $p : null;
	}
}
