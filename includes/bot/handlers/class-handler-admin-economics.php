<?php
/**
 * Bot admin — unit economics (read + mutate wizards).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Handler_Admin_Economics
 */
class SimpleVPBot_Handler_Admin_Economics {

	/**
	 * @param string $platform Platform.
	 * @param int    $chat_id  Chat id.
	 * @param object $user     User.
	 * @return bool
	 */
	public static function open_tab( $platform, $chat_id, $user, $prefix = '' ) {
		if ( SimpleVPBot_Reseller_Permission_Gate::permission_actor_id( (int) $user->id ) > 0 ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.denied_tab', $user ) );
			return true;
		}
		$body = '' !== trim( (string) $prefix ) ? trim( (string) $prefix ) . "\n\n" : '';
		$body .= SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.tutorial.unit_economics', $user );
		if ( class_exists( 'SimpleVPBot_Unit_Economics_Overview' ) ) {
			$ov = SimpleVPBot_Unit_Economics_Overview::build();
			if ( is_array( $ov ) ) {
				if ( ! empty( $ov['headline'] ) ) {
					$body .= "\n\n" . (string) $ov['headline'];
				}
				if ( ! empty( $ov['site'] ) && is_array( $ov['site'] ) ) {
					$s = $ov['site'];
					$body .= "\n\n" . SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.economics_site_header', $user );
					$body .= "\n" . SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.economics_sales_gb', $user, array( 'value' => number_format( (float) ( $s['sales_volume_gb'] ?? 0 ), 1 ) ) );
					$body .= "\n" . SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.economics_revenue', $user, array( 'value' => number_format( (float) ( $s['revenue_est'] ?? 0 ) ) ) );
					$body .= "\n" . SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.economics_cost', $user, array( 'value' => number_format( (float) ( $s['cost_monthly_total'] ?? 0 ) ) ) );
					$body .= "\n" . SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.economics_profit', $user, array( 'value' => number_format( (float) ( $s['profit_est'] ?? 0 ) ) ) );
				}
				if ( ! empty( $ov['panels'] ) && is_array( $ov['panels'] ) ) {
					$body .= "\n\n" . SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.economics_panels_header', $user );
					foreach ( array_slice( $ov['panels'], 0, 8 ) as $p ) {
						if ( ! is_array( $p ) ) {
							continue;
						}
						$body .= "\n• " . (string) ( $p['label'] ?? '#' . (int) ( $p['panel_id'] ?? 0 ) );
						$body .= ' — ' . SimpleVPBot_Bot_Admin_Texts::msg(
							'msg.admin.economics_panel_profit',
							$user,
							array( 'value' => number_format( (float) ( $p['profit_est'] ?? 0 ) ) )
						);
					}
				}
			}
		}
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			$body,
			array( 'reply_markup' => SimpleVPBot_Keyboards::admin_economics_reply( $user ) )
		);
		return true;
	}

	/**
	 * @param array<string, mixed> $ctx Context.
	 * @return bool
	 */
	public static function route_text( array $ctx ) {
		$user     = $ctx['user'];
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$text     = trim( (string) $ctx['text'] );
		if ( SimpleVPBot_Reseller_Permission_Gate::permission_actor_id( (int) $user->id ) > 0 ) {
			return false;
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.economics_config', $user ) ) {
			SimpleVPBot_State::set( (int) $user->id, 'admin_economics_config', array( 'step' => 'volume_gb' ) );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_economics_volume_gb', $user ) );
			return true;
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.economics_refresh', $user ) ) {
			return self::open_tab( $platform, $chat_id, $user );
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.economics_panel_lines', $user ) ) {
			SimpleVPBot_State::set( (int) $user->id, 'admin_economics_panel_line', array( 'step' => 'panel_id' ) );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_economics_panel_id', $user ) );
			return true;
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.economics_shared_lines', $user ) ) {
			SimpleVPBot_State::set( (int) $user->id, 'admin_economics_shared_line', array( 'step' => 'label' ) );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_economics_line_label', $user ) );
			return true;
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.economics_mark_paid', $user ) ) {
			SimpleVPBot_State::set( (int) $user->id, 'admin_economics_mark_paid', array() );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_economics_line_id', $user ) );
			return true;
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.economics_delete_line', $user ) ) {
			SimpleVPBot_State::set( (int) $user->id, 'admin_economics_delete_line', array() );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_economics_line_id', $user ) );
			return true;
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.economics_edit_line', $user ) ) {
			SimpleVPBot_State::set( (int) $user->id, 'admin_economics_edit_line', array( 'step' => 'line_id' ) );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_economics_line_id', $user ) );
			return true;
		}
		if ( $text === SimpleVPBot_Texts::get_for_user( 'btn.admin.economics_deactivate_line', $user ) ) {
			SimpleVPBot_State::set( (int) $user->id, 'admin_economics_deactivate_line', array() );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_economics_line_id', $user ) );
			return true;
		}
		return false;
	}

	/**
	 * @param array<string, mixed> $ctx Context.
	 * @return bool
	 */
	public static function route_state( array $ctx ) {
		$user = $ctx['user'];
		$st   = (string) $user->state;
		if ( 'admin_economics_config' === $st ) {
			return self::route_config_state( $ctx );
		}
		if ( 'admin_economics_panel_line' === $st ) {
			return self::route_panel_line_state( $ctx );
		}
		if ( 'admin_economics_shared_line' === $st ) {
			return self::route_shared_line_state( $ctx );
		}
		if ( 'admin_economics_mark_paid' === $st ) {
			return self::route_mark_paid_state( $ctx );
		}
		if ( 'admin_economics_delete_line' === $st ) {
			return self::route_delete_line_state( $ctx );
		}
		if ( 'admin_economics_edit_line' === $st ) {
			return self::route_edit_line_state( $ctx );
		}
		if ( 'admin_economics_deactivate_line' === $st ) {
			return self::route_deactivate_line_state( $ctx );
		}
		return false;
	}

	/**
	 * @param array<string, mixed> $ctx Context.
	 * @return bool
	 */
	private static function route_config_state( array $ctx ) {
		$user     = $ctx['user'];
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$text     = trim( (string) $ctx['text'] );
		$data     = SimpleVPBot_State::get( (int) $user->id );
		$data     = is_array( $data ) ? $data : array();
		$step     = (string) ( $data['step'] ?? '' );
		if ( 'volume_gb' === $step ) {
			$data['total_sold_volume_gb'] = max( 0, (float) str_replace( ',', '.', SimpleVPBot_Bot_Runtime::normalize_digits( $text ) ) );
			$data['step']                 = 'price_per_gb';
			SimpleVPBot_State::set( (int) $user->id, 'admin_economics_config', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_economics_price_gb', $user ) );
			return true;
		}
		if ( 'price_per_gb' === $step ) {
			$data['selling_price_per_gb'] = max( 0, (float) str_replace( ',', '.', SimpleVPBot_Bot_Runtime::normalize_digits( $text ) ) );
			$data['step']                 = 'volume_mode';
			SimpleVPBot_State::set( (int) $user->id, 'admin_economics_config', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_economics_volume_mode', $user ) );
			return true;
		}
		if ( 'volume_mode' === $step ) {
			$raw = strtolower( sanitize_key( $text ) );
			if ( ! in_array( $raw, array( 'manual', 'auto_sales', 'auto' ), true ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.economics_volume_mode_invalid', $user ) );
				return true;
			}
			$data['volume_mode'] = in_array( $raw, array( 'auto', 'auto_sales' ), true ) ? 'auto_sales' : 'manual';
			$data['step']        = 'volume_window';
			SimpleVPBot_State::set( (int) $user->id, 'admin_economics_config', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_economics_volume_window', $user ) );
			return true;
		}
		if ( 'volume_window' === $step ) {
			$data['volume_window_days'] = max( 1, min( 365, (int) SimpleVPBot_Bot_Runtime::normalize_digits( $text ) ) );
			if ( ! class_exists( 'SimpleVPBot_Bot_Admin_Mutate' ) ) {
				SimpleVPBot_State::clear( (int) $user->id );
				return true;
			}
			$result = SimpleVPBot_Bot_Admin_Mutate::apply_for_user(
				(int) $user->id,
				'unit_economics_config_save',
				array(
					'total_sold_volume_gb' => $data['total_sold_volume_gb'],
					'selling_price_per_gb' => $data['selling_price_per_gb'],
					'volume_mode'          => (string) ( $data['volume_mode'] ?? 'manual' ),
					'volume_window_days'   => (int) ( $data['volume_window_days'] ?? 30 ),
				)
			);
			SimpleVPBot_State::clear( (int) $user->id );
			self::finish_line_op( $platform, $chat_id, $user, is_array( $result ) ? $result : array( 'ok' => false ) );
			return true;
		}
		return false;
	}

	/**
	 * @param array<string, mixed> $ctx Context.
	 * @return bool
	 */
	private static function route_panel_line_state( array $ctx ) {
		$user     = $ctx['user'];
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$text     = trim( (string) $ctx['text'] );
		$data     = SimpleVPBot_State::get( (int) $user->id );
		$data     = is_array( $data ) ? $data : array();
		$step     = (string) ( $data['step'] ?? '' );
		if ( 'panel_id' === $step ) {
			$panel_id = (int) SimpleVPBot_Bot_Runtime::normalize_digits( $text );
			if ( $panel_id < 1 || ! class_exists( 'SimpleVPBot_Model_Panel' ) || ! SimpleVPBot_Model_Panel::find( $panel_id ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.panel_id_invalid', $user ) );
				return true;
			}
			$data['panel_id'] = $panel_id;
			$data['step']     = 'label';
			SimpleVPBot_State::set( (int) $user->id, 'admin_economics_panel_line', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_economics_line_label', $user ) );
			return true;
		}
		if ( 'label' === $step ) {
			$data['label'] = sanitize_text_field( $text );
			$data['step']  = 'category';
			SimpleVPBot_State::set( (int) $user->id, 'admin_economics_panel_line', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_economics_line_category', $user ) );
			return true;
		}
		if ( 'category' === $step ) {
			$data['category'] = sanitize_key( $text );
			$data['step']     = 'cost';
			SimpleVPBot_State::set( (int) $user->id, 'admin_economics_panel_line', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_economics_line_cost', $user ) );
			return true;
		}
		if ( 'cost' === $step ) {
			$data['cost_amount'] = max( 0, (float) str_replace( ',', '.', SimpleVPBot_Bot_Runtime::normalize_digits( $text ) ) );
			$data['step']        = 'cycle';
			SimpleVPBot_State::set( (int) $user->id, 'admin_economics_panel_line', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_economics_line_cycle', $user ) );
			return true;
		}
		if ( 'cycle' === $step ) {
			$panel_id = (int) ( $data['panel_id'] ?? 0 );
			if ( $panel_id < 1 || ! class_exists( 'SimpleVPBot_Bot_Admin_Mutate' ) || ! class_exists( 'SimpleVPBot_Model_Panel_Economics_Line' ) ) {
				SimpleVPBot_State::clear( (int) $user->id );
				return true;
			}
			$new_line = array(
				'label'         => (string) ( $data['label'] ?? '' ),
				'category'      => (string) ( $data['category'] ?? 'external_server' ),
				'cost_amount'   => (float) ( $data['cost_amount'] ?? 0 ),
				'billing_cycle' => sanitize_key( $text ),
				'active'        => 1,
			);
			$lines = array();
			foreach ( SimpleVPBot_Model_Panel_Economics_Line::for_panel( $panel_id ) as $row ) {
				$lines[] = SimpleVPBot_Model_Panel_Economics_Line::row_to_array( $row, true );
			}
			$lines[] = $new_line;
			$result  = SimpleVPBot_Bot_Admin_Mutate::apply_for_user(
				(int) $user->id,
				'panel_economics_save',
				array(
					'panel_id' => $panel_id,
					'lines'    => $lines,
				)
			);
			SimpleVPBot_State::clear( (int) $user->id );
			self::finish_line_op( $platform, $chat_id, $user, is_array( $result ) ? $result : array( 'ok' => false ) );
			return true;
		}
		return false;
	}

	/**
	 * @param array<string, mixed> $ctx Context.
	 * @return bool
	 */
	private static function route_shared_line_state( array $ctx ) {
		$user     = $ctx['user'];
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$text     = trim( (string) $ctx['text'] );
		$data     = SimpleVPBot_State::get( (int) $user->id );
		$data     = is_array( $data ) ? $data : array();
		$step     = (string) ( $data['step'] ?? '' );
		if ( 'label' === $step ) {
			$data['label'] = sanitize_text_field( $text );
			$data['step']  = 'category';
			SimpleVPBot_State::set( (int) $user->id, 'admin_economics_shared_line', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_economics_line_category', $user ) );
			return true;
		}
		if ( 'category' === $step ) {
			$data['category'] = sanitize_key( $text );
			$data['step']     = 'cost';
			SimpleVPBot_State::set( (int) $user->id, 'admin_economics_shared_line', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_economics_line_cost', $user ) );
			return true;
		}
		if ( 'cost' === $step ) {
			$data['cost_amount'] = max( 0, (float) str_replace( ',', '.', SimpleVPBot_Bot_Runtime::normalize_digits( $text ) ) );
			$data['step']        = 'cycle';
			SimpleVPBot_State::set( (int) $user->id, 'admin_economics_shared_line', $data );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_economics_line_cycle', $user ) );
			return true;
		}
		if ( 'cycle' === $step ) {
			if ( ! class_exists( 'SimpleVPBot_Bot_Admin_Mutate' ) || ! class_exists( 'SimpleVPBot_Model_Panel_Economics_Line' ) ) {
				SimpleVPBot_State::clear( (int) $user->id );
				return true;
			}
			$new_line = array(
				'label'         => (string) ( $data['label'] ?? '' ),
				'category'      => (string) ( $data['category'] ?? 'external_server' ),
				'cost_amount'   => (float) ( $data['cost_amount'] ?? 0 ),
				'billing_cycle' => sanitize_key( $text ),
				'active'        => 1,
			);
			$lines = array();
			foreach ( SimpleVPBot_Model_Panel_Economics_Line::for_shared() as $row ) {
				$lines[] = SimpleVPBot_Model_Panel_Economics_Line::row_to_array( $row, true );
			}
			$lines[] = $new_line;
			$result  = SimpleVPBot_Bot_Admin_Mutate::apply_for_user(
				(int) $user->id,
				'shared_economics_save',
				array( 'lines' => $lines )
			);
			SimpleVPBot_State::clear( (int) $user->id );
			self::finish_line_op( $platform, $chat_id, $user, is_array( $result ) ? $result : array( 'ok' => false ) );
			return true;
		}
		return false;
	}

	/**
	 * @param array<string, mixed> $ctx Context.
	 * @return bool
	 */
	private static function route_mark_paid_state( array $ctx ) {
		$user     = $ctx['user'];
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$text     = trim( (string) $ctx['text'] );
		$line_id  = (int) SimpleVPBot_Bot_Runtime::normalize_digits( $text );
		if ( $line_id < 1 || ! class_exists( 'SimpleVPBot_Bot_Admin_Mutate' ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_economics_line_id_invalid', $user ) );
			return true;
		}
		$result = SimpleVPBot_Bot_Admin_Mutate::apply_for_user(
			(int) $user->id,
			'panel_economics_mark_paid',
			array( 'line_id' => $line_id )
		);
		SimpleVPBot_State::clear( (int) $user->id );
		self::finish_line_op( $platform, $chat_id, $user, is_array( $result ) ? $result : array( 'ok' => false ) );
		return true;
	}

	/**
	 * @param string               $platform Platform.
	 * @param int                  $chat_id  Chat id.
	 * @param object               $user     User.
	 * @param array<string, mixed> $result   Mutation result.
	 */
	private static function finish_line_op( $platform, $chat_id, $user, array $result ) {
		$msg = SimpleVPBot_Bot_Admin_Mutate::result_message( $user, $result );
		if ( ! empty( $result['ok'] ) ) {
			self::open_tab( $platform, $chat_id, $user, $msg );
			return;
		}
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $msg );
	}

	/**
	 * @param array<string, mixed> $arr Row array.
	 * @return string
	 */
	private static function line_edit_pipe_example( array $arr ) {
		return implode(
			'|',
			array(
				(string) ( $arr['label'] ?? '' ),
				(string) ( $arr['category'] ?? '' ),
				(string) ( $arr['cost_amount'] ?? '0' ),
				(string) ( $arr['billing_cycle'] ?? 'monthly' ),
				! empty( $arr['active'] ) ? '1' : '0',
				(string) ( $arr['provider'] ?? '' ),
				(string) ( $arr['payment_method'] ?? '' ),
				(string) ( $arr['paid_at'] ?? '' ),
				(string) ( $arr['expires_at'] ?? '' ),
				(string) ( $arr['host_ip'] ?? '' ),
				(string) ( $arr['tunnel_mode'] ?? '' ),
				(string) ( $arr['notes'] ?? '' ),
				(string) ( $arr['sort_order'] ?? '0' ),
			)
		);
	}

	/**
	 * @param array<string, mixed> $line  Line.
	 * @param array<int, string>   $parts Parts.
	 * @return array<string, mixed>
	 */
	private static function apply_line_edit_parts( array $line, array $parts ) {
		$line['label']         = sanitize_text_field( (string) ( $parts[0] ?? $line['label'] ?? '' ) );
		$line['category']      = sanitize_key( (string) ( $parts[1] ?? $line['category'] ?? '' ) );
		$line['cost_amount']   = max( 0, (float) str_replace( ',', '.', (string) ( $parts[2] ?? $line['cost_amount'] ?? 0 ) ) );
		$line['billing_cycle'] = sanitize_key( (string) ( $parts[3] ?? $line['billing_cycle'] ?? 'monthly' ) );
		if ( isset( $parts[4] ) && '' !== $parts[4] ) {
			$line['active'] = (int) SimpleVPBot_Bot_Runtime::normalize_digits( (string) $parts[4] ) ? 1 : 0;
		}
		if ( isset( $parts[5] ) ) {
			$line['provider'] = sanitize_text_field( (string) $parts[5] );
		}
		if ( isset( $parts[6] ) ) {
			$line['payment_method'] = sanitize_text_field( (string) $parts[6] );
		}
		if ( isset( $parts[7] ) ) {
			$line['paid_at'] = sanitize_text_field( (string) $parts[7] );
		}
		if ( isset( $parts[8] ) ) {
			$line['expires_at'] = sanitize_text_field( (string) $parts[8] );
		}
		if ( isset( $parts[9] ) ) {
			$line['host_ip'] = sanitize_text_field( (string) $parts[9] );
		}
		if ( isset( $parts[10] ) ) {
			$line['tunnel_mode'] = sanitize_key( (string) $parts[10] );
		}
		if ( isset( $parts[11] ) ) {
			$line['notes'] = sanitize_textarea_field( (string) $parts[11] );
		}
		if ( isset( $parts[12] ) && '' !== $parts[12] ) {
			$line['sort_order'] = (int) SimpleVPBot_Bot_Runtime::normalize_digits( (string) $parts[12] );
		}
		return $line;
	}

	/**
	 * Remove a cost line by id (panel or shared scope).
	 *
	 * @param array<string, mixed> $ctx Context.
	 * @return bool
	 */
	private static function route_delete_line_state( array $ctx ) {
		$user     = $ctx['user'];
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$text     = trim( (string) $ctx['text'] );
		$line_id  = (int) SimpleVPBot_Bot_Runtime::normalize_digits( $text );
		if ( $line_id < 1 || ! class_exists( 'SimpleVPBot_Model_Panel_Economics_Line' ) || ! class_exists( 'SimpleVPBot_Bot_Admin_Mutate' ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_economics_line_id_invalid', $user ) );
			return true;
		}
		$row = SimpleVPBot_Model_Panel_Economics_Line::find( $line_id );
		if ( ! $row ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.mutate.not_found', $user ) );
			SimpleVPBot_State::clear( (int) $user->id );
			return true;
		}
		$panel_id = (int) ( $row->panel_id ?? 0 );
		$lines    = array();
		$source   = $panel_id > 0
			? SimpleVPBot_Model_Panel_Economics_Line::for_panel( $panel_id )
			: SimpleVPBot_Model_Panel_Economics_Line::for_shared();
		foreach ( $source as $ln ) {
			if ( (int) ( $ln->id ?? 0 ) === $line_id ) {
				continue;
			}
			$lines[] = SimpleVPBot_Model_Panel_Economics_Line::row_to_array( $ln, true );
		}
		$result = $panel_id > 0
			? SimpleVPBot_Bot_Admin_Mutate::apply_for_user(
				(int) $user->id,
				'panel_economics_save',
				array(
					'panel_id' => $panel_id,
					'lines'    => $lines,
				)
			)
			: SimpleVPBot_Bot_Admin_Mutate::apply_for_user(
				(int) $user->id,
				'shared_economics_save',
				array( 'lines' => $lines )
			);
		SimpleVPBot_State::clear( (int) $user->id );
		self::finish_line_op( $platform, $chat_id, $user, is_array( $result ) ? $result : array( 'ok' => false ) );
		return true;
	}

	/**
	 * @param array<string, mixed> $ctx Context.
	 * @return bool
	 */
	private static function route_edit_line_state( array $ctx ) {
		$user     = $ctx['user'];
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$text     = trim( (string) $ctx['text'] );
		$data     = SimpleVPBot_State::get( (int) $user->id );
		$data     = is_array( $data ) ? $data : array();
		$step     = (string) ( $data['step'] ?? 'line_id' );
		if ( 'line_id' === $step ) {
			$line_id = (int) SimpleVPBot_Bot_Runtime::normalize_digits( $text );
			if ( $line_id < 1 || ! class_exists( 'SimpleVPBot_Model_Panel_Economics_Line' ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_economics_line_id_invalid', $user ) );
				return true;
			}
			$row = SimpleVPBot_Model_Panel_Economics_Line::find( $line_id );
			if ( ! $row ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.mutate.not_found', $user ) );
				return true;
			}
			$arr = SimpleVPBot_Model_Panel_Economics_Line::row_to_array( $row, true );
			$data['line_id']  = $line_id;
			$data['panel_id'] = (int) ( $row->panel_id ?? 0 );
			$data['step']     = 'fields';
			SimpleVPBot_State::set( (int) $user->id, 'admin_economics_edit_line', $data );
			$hint = SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_economics_line_fields', $user );
			$hint .= "\n" . self::line_edit_pipe_example( $arr );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, $hint );
			return true;
		}
		if ( 'fields' === $step ) {
			$line_id  = (int) ( $data['line_id'] ?? 0 );
			$panel_id = (int) ( $data['panel_id'] ?? 0 );
			$parts    = array_map( 'trim', explode( '|', $text ) );
			if ( $line_id < 1 || count( $parts ) < 4 || ! class_exists( 'SimpleVPBot_Bot_Admin_Mutate' ) ) {
				SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_economics_line_id_invalid', $user ) );
				return true;
			}
			$result = self::save_line_mutation(
				$user,
				$line_id,
				$panel_id,
				static function ( array $line ) use ( $parts ) {
					return self::apply_line_edit_parts( $line, $parts );
				}
			);
			SimpleVPBot_State::clear( (int) $user->id );
			self::finish_line_op( $platform, $chat_id, $user, is_array( $result ) ? $result : array( 'ok' => false ) );
			return true;
		}
		return false;
	}

	/**
	 * @param array<string, mixed> $ctx Context.
	 * @return bool
	 */
	private static function route_deactivate_line_state( array $ctx ) {
		$user     = $ctx['user'];
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$text     = trim( (string) $ctx['text'] );
		$line_id  = (int) SimpleVPBot_Bot_Runtime::normalize_digits( $text );
		if ( $line_id < 1 || ! class_exists( 'SimpleVPBot_Model_Panel_Economics_Line' ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_economics_line_id_invalid', $user ) );
			return true;
		}
		$row = SimpleVPBot_Model_Panel_Economics_Line::find( $line_id );
		if ( ! $row ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.mutate.not_found', $user ) );
			SimpleVPBot_State::clear( (int) $user->id );
			return true;
		}
		$result = self::save_line_mutation(
			$user,
			$line_id,
			(int) ( $row->panel_id ?? 0 ),
			static function ( array $line ) {
				$line['active'] = 0;
				return $line;
			}
		);
		SimpleVPBot_State::clear( (int) $user->id );
		self::finish_line_op( $platform, $chat_id, $user, is_array( $result ) ? $result : array( 'ok' => false ) );
		return true;
	}

	/**
	 * @param object               $user     User.
	 * @param int                  $line_id  Line id.
	 * @param int                  $panel_id Panel id (0 = shared).
	 * @param callable             $mutator  fn(array):array.
	 * @return array<string, mixed>
	 */
	private static function save_line_mutation( $user, $line_id, $panel_id, callable $mutator ) {
		if ( ! class_exists( 'SimpleVPBot_Model_Panel_Economics_Line' ) || ! class_exists( 'SimpleVPBot_Bot_Admin_Mutate' ) ) {
			return array( 'ok' => false );
		}
		$line_id  = (int) $line_id;
		$panel_id = (int) $panel_id;
		$source   = $panel_id > 0
			? SimpleVPBot_Model_Panel_Economics_Line::for_panel( $panel_id )
			: SimpleVPBot_Model_Panel_Economics_Line::for_shared();
		$lines    = array();
		$found    = false;
		foreach ( $source as $ln ) {
			$arr = SimpleVPBot_Model_Panel_Economics_Line::row_to_array( $ln, true );
			if ( (int) ( $ln->id ?? 0 ) === $line_id ) {
				$arr   = $mutator( $arr );
				$found = true;
			}
			$lines[] = $arr;
		}
		if ( ! $found ) {
			return array( 'ok' => false, 'code' => 'not_found' );
		}
		return $panel_id > 0
			? SimpleVPBot_Bot_Admin_Mutate::apply_for_user(
				(int) $user->id,
				'panel_economics_save',
				array(
					'panel_id' => $panel_id,
					'lines'    => $lines,
				)
			)
			: SimpleVPBot_Bot_Admin_Mutate::apply_for_user(
				(int) $user->id,
				'shared_economics_save',
				array( 'lines' => $lines )
			);
	}
}
