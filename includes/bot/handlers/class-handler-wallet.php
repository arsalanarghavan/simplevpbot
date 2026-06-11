<?php
/**
 * Wallet UI.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Handler_Wallet
 */
class SimpleVPBot_Handler_Wallet {

	/**
	 * Show balance.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id Chat id.
	 * @param object $user User.
	 */
	public static function show( $platform, $chat_id, $user ) {
		$bal   = SimpleVPBot_Bot_Persian_Text::format_toman_fa( (float) $user->balance );
		$lines = array(
			SimpleVPBot_Texts::get_for_user( 'msg.wallet.title', $user ),
			'➖➖➖➖➖➖➖➖',
			SimpleVPBot_Texts::format(
				SimpleVPBot_Texts::get_for_user( 'msg.wallet.balance', $user ),
				array( 'balance' => $bal )
			),
			'➖➖➖➖➖➖➖➖',
		);
		$owner_rid = class_exists( 'SimpleVPBot_Payment_Methods' )
			? SimpleVPBot_Payment_Methods::resolve_owner_rid( null )
			: 0;
		$topup_on  = class_exists( 'SimpleVPBot_Payment_Methods' )
			? SimpleVPBot_Payment_Methods::is_enabled( 'wallet_topup', $owner_rid )
			: true;
		if ( $topup_on ) {
			$lines[] = SimpleVPBot_Texts::get_for_user( 'msg.wallet.topup_hint', $user );
		} else {
			$lines[] = SimpleVPBot_Texts::get_for_user( 'msg.wallet.topup_disabled_hint', $user );
		}
		$rows = array(
			array(
				array(
					'text'          => SimpleVPBot_Texts::get_for_user( 'btn.wallet.history', $user ),
					'callback_data' => 'wal:h',
				),
			),
		);
		if ( $topup_on ) {
			$rows[] = array(
				array(
					'text'          => SimpleVPBot_Keyboards::glass_button_text(
						SimpleVPBot_Texts::get_for_user( 'btn.wallet.topup', $user ),
						64
					),
					'callback_data' => 'wal:tu',
				),
			);
		}
		SimpleVPBot_Bot_Runtime::send_message(
			$platform,
			$chat_id,
			implode( "\n", $lines ),
			array( 'reply_markup' => array( 'inline_keyboard' => $rows ) )
		);
	}

	/**
	 * Start wallet top-up amount entry.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id Chat id.
	 * @param object $user User.
	 */
	public static function begin_topup( $platform, $chat_id, $user ) {
		$owner_rid = class_exists( 'SimpleVPBot_Payment_Methods' )
			? SimpleVPBot_Payment_Methods::resolve_owner_rid( null )
			: 0;
		if ( class_exists( 'SimpleVPBot_Payment_Methods' ) && ! SimpleVPBot_Payment_Methods::is_enabled( 'wallet_topup', $owner_rid ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.wallet.topup_disabled', $user ) );
			return;
		}
		SimpleVPBot_State::set( (int) $user->id, 'wallet_topup', array() );
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.wallet.topup_prompt', $user ) );
	}

	/**
	 * Handle wallet_topup state text (amount in toman).
	 *
	 * @param array<string, mixed> $ctx Context.
	 */
	public static function handle_topup_state( array $ctx ) {
		$platform = (string) $ctx['platform'];
		$chat_id  = (int) $ctx['chat_id'];
		$user     = $ctx['user'];
		$raw      = trim( SimpleVPBot_Bot_Runtime::normalize_digits( (string) ( $ctx['text'] ?? '' ) ) );
		if ( '' === $raw || in_array( $raw, array( 'لغو', 'انصراف', 'cancel' ), true ) ) {
			SimpleVPBot_State::clear( (int) $user->id );
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.buy.cancelled', $user ) );
			return;
		}
		$raw = str_replace( ',', '.', $raw );
		if ( ! is_numeric( $raw ) ) {
			SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Texts::get_for_user( 'msg.wallet.topup_invalid', $user ) );
			return;
		}
		$amt = round( (float) $raw, 2 );
		SimpleVPBot_State::clear( (int) $user->id );
		SimpleVPBot_Handler_Buy::create_topup_checkout( $platform, $chat_id, $user, $amt );
	}

	/**
	 * Show recent wallet-related transactions.
	 *
	 * @param string $platform Platform.
	 * @param int    $chat_id Chat id.
	 * @param object $user User.
	 */
	public static function show_history( $platform, $chat_id, $user ) {
		$hist  = SimpleVPBot_Model_Transaction::history( (int) $user->id, 10 );
		$lines = array(
			SimpleVPBot_Texts::get_for_user( 'msg.wallet.history_title', $user ),
			'➖➖➖➖➖➖➖➖',
		);
		if ( empty( $hist ) ) {
			$lines[] = SimpleVPBot_Texts::get_for_user( 'msg.wallet.history_empty', $user );
		} else {
			foreach ( $hist as $h ) {
				$lines[] = self::format_history_line( $h, $user );
			}
		}
		SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, implode( "\n", $lines ) );
	}

	/**
	 * One formatted history row for a transaction.
	 *
	 * @param object $tx Transaction row.
	 * @param object $user User.
	 * @return string
	 */
	public static function format_history_line( $tx, $user ) {
		$type_key   = sanitize_key( (string) ( $tx->type ?? '' ) );
		$status_key = sanitize_key( (string) ( $tx->status ?? '' ) );
		$type_lbl   = SimpleVPBot_Texts::get_for_user( 'msg.tx.type.' . $type_key, $user );
		if ( $type_lbl === 'msg.tx.type.' . $type_key ) {
			$type_lbl = SimpleVPBot_Texts::get_for_user( 'msg.tx.type.other', $user );
		}
		$status_lbl = SimpleVPBot_Texts::get_for_user( 'msg.tx.status.' . $status_key, $user );
		if ( $status_lbl === 'msg.tx.status.' . $status_key ) {
			$status_lbl = $status_key;
		}
		$amt = SimpleVPBot_Bot_Persian_Text::format_toman_fa( (float) ( $tx->amount ?? 0 ) );
		$tid = SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) (int) ( $tx->id ?? 0 ) );
		return SimpleVPBot_Texts::format(
			SimpleVPBot_Texts::get_for_user( 'msg.wallet.history_line', $user ),
			array(
				'type'   => $type_lbl,
				'amount' => $amt,
				'status' => $status_lbl,
				'id'     => $tid,
			)
		);
	}
}
