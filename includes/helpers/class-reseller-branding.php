<?php
/**
 * Reseller white-label: nearest ancestor reseller + config URI fragment.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Reseller_Branding
 */
class SimpleVPBot_Reseller_Branding {

	/**
	 * Walk invited_by chain upward; return first user with role reseller, or 0.
	 *
	 * @param int $svp_user_id svp_users.id (service owner / portal user).
	 * @return int
	 */
	public static function nearest_reseller_id_for_user( $svp_user_id ) {
		$id = (int) $svp_user_id;
		for ( $i = 0; $i < 64; $i++ ) {
			if ( $id < 1 ) {
				return 0;
			}
			$u = SimpleVPBot_Model_User::find( $id );
			if ( ! $u ) {
				return 0;
			}
			if ( SimpleVPBot_Model_User::is_reseller_row( $u ) ) {
				return (int) $u->id;
			}
			$inv = (int) ( $u->invited_by ?? 0 );
			if ( $inv < 1 ) {
				return 0;
			}
			$id = $inv;
		}
		return 0;
	}

	/**
	 * Display string for # fragment: profile brand_name, else user name/username.
	 *
	 * @param int $reseller_svp_user_id Reseller svp id.
	 * @return string Non-empty or ''.
	 */
	public static function display_brand_for_reseller( $reseller_svp_user_id ) {
		$rid = (int) $reseller_svp_user_id;
		if ( $rid < 1 || ! class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
			return '';
		}
		$prof = SimpleVPBot_Model_Reseller_Bot_Profile::find_by_reseller( $rid );
		$bn   = $prof ? trim( (string) ( $prof->brand_name ?? '' ) ) : '';
		if ( '' !== $bn ) {
			return $bn;
		}
		$u = SimpleVPBot_Model_User::find( $rid );
		if ( ! $u ) {
			return '';
		}
		$name = trim( (string) ( $u->first_name ?? '' ) . ' ' . (string) ( $u->last_name ?? '' ) );
		if ( '' !== $name ) {
			return $name;
		}
		$un = trim( (string) ( $u->username ?? '' ) );
		if ( '' !== $un ) {
			return '@' . $un;
		}
		return '';
	}

	/**
	 * Optional config line label override for an end user (nearest reseller, else site setting).
	 *
	 * @param int $svp_user_id Service owner's svp user id.
	 * @return string Empty = use panel subscription #fragment labels.
	 */
	public static function config_label_override_for_user( $svp_user_id ) {
		$uid = (int) $svp_user_id;
		$rid = self::nearest_reseller_id_for_user( $uid );
		if ( $rid > 0 && class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
			$prof = SimpleVPBot_Model_Reseller_Bot_Profile::find_by_reseller( $rid );
			$ov   = $prof ? trim( (string) ( $prof->config_label_override ?? '' ) ) : '';
			if ( '' !== $ov ) {
				return $ov;
			}
		}
		if ( class_exists( 'SimpleVPBot_Settings' ) ) {
			return trim( (string) SimpleVPBot_Settings::get( 'subscription_config_label_override', '' ) );
		}
		return '';
	}

	/**
	 * Prefix for prefix_numbered labels (reseller profile, site setting, then brand/site name).
	 *
	 * @param int $svp_user_id Service owner's svp user id.
	 * @return string
	 */
	public static function config_label_prefix_for_user( $svp_user_id ) {
		$uid = (int) $svp_user_id;
		$rid = self::nearest_reseller_id_for_user( $uid );
		if ( $rid > 0 && class_exists( 'SimpleVPBot_Model_Reseller_Bot_Profile' ) ) {
			$prof = SimpleVPBot_Model_Reseller_Bot_Profile::find_by_reseller( $rid );
			$pref = $prof ? trim( (string) ( $prof->config_label_prefix ?? '' ) ) : '';
			if ( '' !== $pref ) {
				return $pref;
			}
		}
		if ( class_exists( 'SimpleVPBot_Settings' ) ) {
			$pref = trim( (string) SimpleVPBot_Settings::get( 'config_label_prefix', '' ) );
			if ( '' !== $pref ) {
				return $pref;
			}
		}
		if ( $rid > 0 ) {
			$brand = self::display_brand_for_reseller( $rid );
			if ( '' !== $brand ) {
				return $brand;
			}
		}
		if ( class_exists( 'SimpleVPBot_Settings' ) ) {
			return trim( (string) SimpleVPBot_Settings::get( 'dashboard_site_name', '' ) );
		}
		return '';
	}

	/**
	 * Fragment text for subscription/config lines for an end user (chain from invited_by).
	 *
	 * @param int $svp_user_id Service owner's svp user id.
	 * @return string Empty means keep panel default fragment.
	 */
	public static function brand_fragment_for_user( $svp_user_id ) {
		$rid = self::nearest_reseller_id_for_user( (int) $svp_user_id );
		if ( $rid < 1 ) {
			return '';
		}
		return self::display_brand_for_reseller( $rid );
	}

	/**
	 * Public alias for effective brand resolution (user -> nearest reseller).
	 *
	 * @param int $svp_user_id Service owner user id.
	 * @return string
	 */
	public static function effective_brand_for_user( $svp_user_id ) {
		return self::brand_fragment_for_user( (int) $svp_user_id );
	}

	/**
	 * Build "brand + service" fragment for subscription/config URI suffix.
	 *
	 * @param int    $svp_user_id    Service owner user id.
	 * @param string $service_remark Service display remark.
	 * @return string Empty means keep current/default fragment.
	 */
	public static function fragment_for_service( $svp_user_id, $service_remark ) {
		$brand = trim( self::effective_brand_for_user( (int) $svp_user_id ) );
		if ( '' === $brand ) {
			return '';
		}
		$svc = trim( (string) $service_remark );
		if ( '' === $svc ) {
			return $brand;
		}
		return $brand . '-' . $svc;
	}

	/**
	 * Panel-facing remark naming for reseller-owned users.
	 *
	 * @param int    $svp_user_id    Service owner user id.
	 * @param string $service_remark Desired service/client label.
	 * @return string
	 */
	public static function panel_client_name_for_user( $svp_user_id, $service_remark ) {
		if ( class_exists( 'SimpleVPBot_Service_Naming' ) && SimpleVPBot_Service_Naming::is_platform_slug_remark( (string) $service_remark ) ) {
			$brand = self::panel_brand_only_for_user( (int) $svp_user_id );
			if ( '' !== $brand ) {
				return self::limit_text( $brand, 50 );
			}
		}
		$frag = self::fragment_for_service( (int) $svp_user_id, (string) $service_remark );
		if ( '' === $frag ) {
			return trim( (string) $service_remark );
		}
		return self::limit_text( $frag, 50 );
	}

	/**
	 * Panel client remark: brand name only (no service slug suffix).
	 *
	 * @param int $svp_user_id Service owner user id.
	 * @return string
	 */
	public static function panel_brand_only_for_user( $svp_user_id ) {
		$brand = trim( self::effective_brand_for_user( (int) $svp_user_id ) );
		if ( '' !== $brand ) {
			return $brand;
		}
		if ( class_exists( 'SimpleVPBot_Settings' ) ) {
			$site = trim( (string) SimpleVPBot_Settings::get( 'dashboard_site_name', '' ) );
			if ( '' !== $site ) {
				return $site;
			}
		}
		return (string) get_bloginfo( 'name' );
	}

	/**
	 * Limit text length safely.
	 *
	 * @param string $text Text.
	 * @param int    $max  Max length.
	 * @return string
	 */
	private static function limit_text( $text, $max ) {
		$in = trim( (string) $text );
		if ( $max < 1 ) {
			return $in;
		}
		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			return mb_strlen( $in, 'UTF-8' ) > $max ? mb_substr( $in, 0, $max, 'UTF-8' ) : $in;
		}
		return strlen( $in ) > $max ? substr( $in, 0, $max ) : $in;
	}

	/**
	 * Apply reseller brand to fetched subscription URI lines.
	 *
	 * @param array<int, string>   $uris           Lines from panel subscription.
	 * @param int                  $svp_user_id    Service owner's user id.
	 * @param string               $service_remark Service remark (legacy fallback).
	 * @param object|array|null    $svc            Optional service row for per-service naming.
	 * @return array<int, string>
	 */
	public static function rewrite_subscription_uris_for_user( array $uris, $svp_user_id, $service_remark = '', $svc = null ) {
		$row = $svc;
		if ( ! $row && '' !== trim( (string) $service_remark ) ) {
			$row = (object) array( 'remark' => (string) $service_remark );
		}
		if ( class_exists( 'SimpleVPBot_Service_Naming' ) ) {
			$ctx    = $row ? SimpleVPBot_Service_Naming::context_from_service( $row ) : array();
			$labels = SimpleVPBot_Service_Naming::config_labels_from_uris( $uris, (int) $svp_user_id, $ctx );
			$out    = array();
			$i      = 0;
			foreach ( $uris as $u ) {
				$frag = isset( $labels[ $i ] ) ? (string) $labels[ $i ] : '';
				if ( '' !== trim( $frag ) && class_exists( 'SimpleVPBot_Config_Link' ) ) {
					$out[] = SimpleVPBot_Config_Link::replace_uri_fragment( (string) $u, $frag );
				} else {
					$out[] = (string) $u;
				}
				$i++;
			}
			return $out;
		}
		$base_frag = self::fragment_for_service( (int) $svp_user_id, (string) $service_remark );
		if ( '' === $base_frag ) {
			return $uris;
		}
		$out   = array();
		$multi = count( $uris ) > 1;
		$idx   = 1;
		foreach ( $uris as $u ) {
			$frag  = $multi ? ( $base_frag . '-' . $idx ) : $base_frag;
			$out[] = SimpleVPBot_Config_Link::replace_uri_fragment( (string) $u, $frag );
			$idx++;
		}
		return $out;
	}

	/**
	 * Portal header title: reseller brand if user chain has reseller with branding.
	 *
	 * @param int $svp_user_id Portal user id.
	 * @return string
	 */
	public static function portal_header_title_for_user( $svp_user_id ) {
		$frag = self::effective_brand_for_user( (int) $svp_user_id );
		if ( '' !== $frag ) {
			return $frag;
		}
		return (string) get_bloginfo( 'name' );
	}
}
