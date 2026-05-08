<?php
/**
 * Bot UI — saved layouts, validation, reply keyboard rendering.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_UI_Layout
 */
class SimpleVPBot_UI_Layout {

	const SETTINGS_KEY = 'bot_ui_layouts';

	/**
	 * Raw stored value from settings (merged key).
	 *
	 * @return array<string, mixed>
	 */
	public static function get_stored() {
		$raw = SimpleVPBot_Settings::get( self::SETTINGS_KEY, array() );
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Surfaces map: surface_id => rows of cells.
	 * Each cell: array( 'id' => action_id, 'enabled' => bool, 'glass' => bool optional ).
	 *
	 * @return array<string, array<int, array<int, array<string, mixed>>>>
	 */
	public static function get_merged_surfaces() {
		$stored   = self::get_stored();
		$surfaces = isset( $stored['surfaces'] ) && is_array( $stored['surfaces'] ) ? $stored['surfaces'] : array();
		$defaults = SimpleVPBot_UI_Action_Registry::default_surface_rows();
		$out      = array();
		foreach ( $defaults as $surface => $default_rows ) {
			$out[ $surface ] = self::merge_surface_rows( $surface, isset( $surfaces[ $surface ] ) ? $surfaces[ $surface ] : null, $default_rows );
		}
		return $out;
	}

	/**
	 * @param string                               $surface      Surface id.
	 * @param mixed                                $stored_rows  Stored rows or null.
	 * @param array<int, array<int, string>>       $default_rows Default ids per row.
	 * @return array<int, array<int, array<string, mixed>>>
	 */
	private static function merge_surface_rows( $surface, $stored_rows, array $default_rows ) {
		if ( ! is_array( $stored_rows ) || array() === $stored_rows ) {
			return self::rows_from_ids( $default_rows );
		}
		$valid_ids = array_flip( SimpleVPBot_UI_Action_Registry::surface_action_ids( $surface ) );
		$out       = array();
		foreach ( $stored_rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$built = array();
			foreach ( $row as $cell ) {
				if ( is_string( $cell ) ) {
					$cell = array( 'id' => $cell, 'enabled' => true );
				}
				if ( ! is_array( $cell ) ) {
					continue;
				}
				$id = (string) ( $cell['id'] ?? '' );
				if ( '' === $id || ! isset( $valid_ids[ $id ] ) ) {
					continue;
				}
				$built[] = array(
					'id'      => $id,
					'enabled' => ! isset( $cell['enabled'] ) || ! empty( $cell['enabled'] ),
					'glass'   => ! empty( $cell['glass'] ),
				);
			}
			if ( array() !== $built ) {
				$out[] = $built;
			}
		}
		if ( array() === $out ) {
			return self::rows_from_ids( $default_rows );
		}
		return $out;
	}

	/**
	 * @param array<int, array<int, string>> $id_rows Action ids.
	 * @return array<int, array<int, array<string, mixed>>>
	 */
	private static function rows_from_ids( array $id_rows ) {
		$out = array();
		foreach ( $id_rows as $row ) {
			$br = array();
			foreach ( $row as $id ) {
				$br[] = array(
					'id'      => (string) $id,
					'enabled' => true,
					'glass'   => false,
				);
			}
			if ( array() !== $br ) {
				$out[] = $br;
			}
		}
		return $out;
	}

	/**
	 * Effective rows for surface (enabled cells only for routing helpers).
	 *
	 * @param string      $surface Surface.
	 * @param object|null $user    User (unused; reserved).
	 * @return array<int, array<int, array<string, mixed>>>
	 */
	public static function effective_rows_for_surface( $surface, $user = null ) {
		unset( $user );
		$all = self::get_merged_surfaces();
		return isset( $all[ $surface ] ) ? $all[ $surface ] : array();
	}

	/**
	 * Submenu keyboard with trailing «back to admin root» row.
	 *
	 * @param string      $surface Surface id.
	 * @param object|null $user    User row.
	 * @return array<string, mixed>
	 */
	public static function build_reply_submenu_with_back( $surface, $user = null ) {
		$k    = self::build_reply_keyboard( $surface, $user );
		$rows = isset( $k['keyboard'] ) && is_array( $k['keyboard'] ) ? $k['keyboard'] : array();
		$rows[] = array( array( 'text' => SimpleVPBot_Keyboards::admin_back_main_label() ) );
		return array(
			'keyboard'          => $rows,
			'resize_keyboard'   => true,
			'one_time_keyboard' => false,
		);
	}

	/**
	 * Build Telegram reply_markup keyboard array for a reply surface.
	 *
	 * @param string      $surface Surface id.
	 * @param object|null $user    User for localized labels.
	 * @return array<string, mixed>
	 */
	public static function build_reply_keyboard( $surface, $user = null ) {
		$rows = self::effective_rows_for_surface( $surface, $user );
		$kb   = array();
		foreach ( $rows as $row ) {
			$r = array();
			foreach ( $row as $cell ) {
				if ( empty( $cell['enabled'] ) ) {
					continue;
				}
				$aid = (string) ( $cell['id'] ?? '' );
				$def = SimpleVPBot_UI_Action_Registry::get( $aid );
				if ( ! $def || 'reply' !== ( $def['kind'] ?? '' ) ) {
					continue;
				}
				$cell_gl = ! empty( $cell['glass'] );
				$text    = SimpleVPBot_UI_Action_Registry::reply_button_text( $aid, $user, $cell_gl );
				if ( '' === $text ) {
					continue;
				}
				$r[] = array( 'text' => $text );
			}
			if ( array() !== $r ) {
				$kb[] = $r;
			}
		}
		return array(
			'keyboard'          => $kb,
			'resize_keyboard'   => true,
			'one_time_keyboard' => false,
		);
	}

	/**
	 * Match user main menu text to action id.
	 *
	 * @param string      $text Trimmed text.
	 * @param object|null $user User row.
	 * @return string|null
	 */
	public static function match_user_main_action( $text, $user ) {
		$rows = self::effective_rows_for_surface( 'user_main', $user );
		foreach ( $rows as $row ) {
			foreach ( $row as $cell ) {
				if ( empty( $cell['enabled'] ) ) {
					continue;
				}
				$aid = (string) ( $cell['id'] ?? '' );
				if ( '' === $aid ) {
					continue;
				}
				if ( SimpleVPBot_UI_Action_Registry::text_matches_reply_action( $text, $user, $aid, ! empty( $cell['glass'] ) ) ) {
					return $aid;
				}
			}
		}
		return null;
	}

	/**
	 * Labels for enabled user_main actions (for state interrupt matching).
	 *
	 * @param object|null $user User row.
	 * @return array<int, string>
	 */
	public static function user_main_visible_labels( $user = null ) {
		$labels = array();
		$rows   = self::effective_rows_for_surface( 'user_main', $user );
		foreach ( $rows as $row ) {
			foreach ( $row as $cell ) {
				if ( empty( $cell['enabled'] ) ) {
					continue;
				}
				$aid = (string) ( $cell['id'] ?? '' );
				$t = SimpleVPBot_UI_Action_Registry::reply_button_text( $aid, $user, ! empty( $cell['glass'] ) );
				if ( '' !== $t ) {
					$labels[] = $t;
				}
			}
		}
		return array_values( array_unique( $labels ) );
	}

	/**
	 * Validate and normalize payload from dashboard.
	 *
	 * @param array<string, mixed> $payload Surfaces map from client.
	 * @return array{ok:bool, surfaces?:array<string,array<int,array<int,array<string,mixed>>>>, errors?:array<int,string>}
	 */
	public static function validate_surfaces_payload( array $payload ) {
		$errors   = array();
		$out      = array();
		$defaults = SimpleVPBot_UI_Action_Registry::default_surface_rows();
		foreach ( $defaults as $surface => $_d ) {
			if ( ! isset( $payload[ $surface ] ) ) {
				continue;
			}
			$incoming = $payload[ $surface ];
			if ( ! is_array( $incoming ) ) {
				$errors[] = 'bad_surface:' . $surface;
				continue;
			}
			// Explicit empty array = clear stored layout for this surface (merge will use code defaults).
			if ( array() === $incoming ) {
				$out[ $surface ] = array();
				continue;
			}
			$valid_ids = array_flip( SimpleVPBot_UI_Action_Registry::surface_action_ids( $surface ) );
			$surf_out  = array();
			$seen_ids  = array();
			foreach ( $incoming as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$surf_row = array();
				foreach ( $row as $cell ) {
					if ( ! is_array( $cell ) ) {
						continue;
					}
					$id = isset( $cell['id'] ) ? (string) $cell['id'] : '';
					if ( '' === $id || ! isset( $valid_ids[ $id ] ) ) {
						$errors[] = 'unknown_action:' . $surface . ':' . $id;
						continue;
					}
					if ( isset( $seen_ids[ $id ] ) ) {
						$errors[] = 'duplicate_action:' . $surface . ':' . $id;
						continue;
					}
					$seen_ids[ $id ] = true;
					$en              = ! isset( $cell['enabled'] ) || ! empty( $cell['enabled'] );
					$gl              = ! empty( $cell['glass'] );
					$surf_row[]      = array(
						'id'      => $id,
						'enabled' => $en,
						'glass'   => $gl,
					);
				}
				if ( array() !== $surf_row ) {
					$surf_out[] = $surf_row;
				}
			}
			if ( array() !== $surf_out ) {
				$out[ $surface ] = $surf_out;
			}
		}
		return array(
			'ok'       => array() === $errors,
			'surfaces' => $out,
			'errors'   => $errors,
		);
	}

	/**
	 * Persist validated surfaces (merge into stored).
	 *
	 * @param array<string, array<int, array<int, array<string, mixed>>>> $surfaces Surfaces.
	 */
	public static function save_surfaces( array $surfaces ) {
		$stored = self::get_stored();
		if ( ! isset( $stored['surfaces'] ) || ! is_array( $stored['surfaces'] ) ) {
			$stored['surfaces'] = array();
		}
		foreach ( $surfaces as $k => $rows ) {
			$stored['surfaces'][ $k ] = $rows;
		}
		$stored['version'] = SimpleVPBot_UI_Action_Registry::LAYOUT_VERSION;
		SimpleVPBot_Settings::update( array( self::SETTINGS_KEY => $stored ) );
	}

	/**
	 * Reset all layouts to defaults (remove customizations).
	 */
	public static function reset_all() {
		SimpleVPBot_Settings::update( array( self::SETTINGS_KEY => array() ) );
	}

	/**
	 * Export for dashboard API (merged layouts).
	 *
	 * @return array<string, mixed>
	 */
	public static function export_merged_for_dashboard() {
		$stored = self::get_stored();
		$surfaces_out = array();
		foreach ( self::get_merged_surfaces() as $sid => $rows ) {
			$surfaces_out[ $sid ] = $rows;
		}
		return array(
			'version'  => isset( $stored['version'] ) ? (int) $stored['version'] : SimpleVPBot_UI_Action_Registry::LAYOUT_VERSION,
			'surfaces' => $surfaces_out,
		);
	}
}
