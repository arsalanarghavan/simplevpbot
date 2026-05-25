<?php
/**
 * Abstract Bot API client (Telegram-compatible JSON API).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Bot_Client
 */
abstract class SimpleVPBot_Bot_Client {

	/**
	 * Bot token.
	 *
	 * @var string
	 */
	protected $token;

	/**
	 * Constructor.
	 *
	 * @param string $token Token.
	 */
	public function __construct( $token ) {
		$this->token = (string) $token;
	}

	/**
	 * Base API URL including bot token.
	 *
	 * @return string
	 */
	abstract protected function base_url();

	/**
	 * Call method.
	 *
	 * @param string               $method Method name.
	 * @param array<string, mixed> $params Parameters.
	 * @return array{ok:bool, result:mixed, description?:string, error_code?:int}
	 */
	public function call( $method, array $params = array(), $timeout_seconds = 60 ) {
		$url  = $this->base_url() . $method;
		$to   = max( 5, min( 120, (int) $timeout_seconds ) );
		$args = array(
			'timeout' => $to,
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( $params ),
			'method'  => 'POST',
		);
		if ( class_exists( 'SimpleVPBot_Telegram_Http' ) ) {
			$args = SimpleVPBot_Telegram_Http::apply_proxy_to_args( $args, $url );
		}
		$res = wp_remote_post( $url, $args );
		if ( is_wp_error( $res ) ) {
			return array( 'ok' => false, 'result' => null, 'description' => $res->get_error_message() );
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $body ) ) {
			return array( 'ok' => false, 'result' => null, 'description' => 'invalid_json' );
		}
		return $body;
	}

	/**
	 * MIME type for multipart file uploads based on extension.
	 *
	 * @param string $path Local file path.
	 * @return string
	 */
	protected static function multipart_mime_for_path( $path ) {
		$lp = strtolower( (string) $path );
		if ( false !== strpos( $lp, '.png' ) ) {
			return 'image/png';
		}
		if ( false !== strpos( $lp, '.webp' ) ) {
			return 'image/webp';
		}
		if ( false !== strpos( $lp, '.gif' ) ) {
			return 'image/gif';
		}
		return 'image/jpeg';
	}

	/**
	 * Multipart upload for sendDocument etc.
	 *
	 * @param string                          $method Method.
	 * @param array<string, string|int|array> $params Params (file fields as local path).
	 * @return array<string, mixed>
	 */
	public function call_multipart( $method, array $params ) {
		$url    = $this->base_url() . $method;
		$boundary = wp_generate_password( 24, false );
		$body   = '';
		foreach ( $params as $name => $value ) {
			$body .= "--{$boundary}\r\n";
			if ( is_string( $value ) && is_readable( $value ) ) {
				$fn   = basename( $value );
				$data = file_get_contents( $value ); // phpcs:ignore
				$body .= "Content-Disposition: form-data; name=\"{$name}\"; filename=\"{$fn}\"\r\n";
				$body .= 'Content-Type: ' . self::multipart_mime_for_path( $value ) . "\r\n\r\n";
				$body .= $data . "\r\n";
			} else {
				$body .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n";
				$body .= (string) $value . "\r\n";
			}
		}
		$body .= "--{$boundary}--\r\n";
		$mp_args = array(
			'timeout' => 120,
			'headers' => array( 'Content-Type' => 'multipart/form-data; boundary=' . $boundary ),
			'body'    => $body,
		);
		if ( class_exists( 'SimpleVPBot_Telegram_Http' ) ) {
			$mp_args = SimpleVPBot_Telegram_Http::apply_proxy_to_args( $mp_args, $url );
		}
		$res = wp_remote_post( $url, $mp_args );
		if ( is_wp_error( $res ) ) {
			return array( 'ok' => false, 'description' => $res->get_error_message() );
		}
		$out = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		return is_array( $out ) ? $out : array( 'ok' => false );
	}

	/**
	 * sendMessage shortcut.
	 *
	 * @param array<string, mixed> $p Params.
	 * @return array<string, mixed>
	 */
	public function send_message( array $p, $timeout_seconds = 60 ) {
		return $this->call( 'sendMessage', $p, $timeout_seconds );
	}

	/**
	 * sendPhoto.
	 *
	 * @param array<string, mixed> $p Params.
	 * @param int                  $timeout_seconds HTTP timeout.
	 * @return array<string, mixed>
	 */
	public function send_photo( array $p, $timeout_seconds = 60 ) {
		return $this->call( 'sendPhoto', $p, $timeout_seconds );
	}

	/**
	 * sendMediaGroup (album). Telegram-compatible JSON API.
	 *
	 * @param array<string, mixed> $p Params chat_id, media[].
	 * @param int                  $timeout_seconds HTTP timeout.
	 * @return array<string, mixed>
	 */
	public function send_media_group( array $p, $timeout_seconds = 90 ) {
		return $this->call( 'sendMediaGroup', $p, $timeout_seconds );
	}

	/**
	 * sendDocument from file path.
	 *
	 * @param array<string, mixed> $p Params with document local path.
	 * @return array<string, mixed>
	 */
	public function send_document_file( array $p ) {
		return $this->call_multipart( 'sendDocument', $p );
	}

	/**
	 * editMessageText.
	 *
	 * @param array<string, mixed> $p Params.
	 * @return array<string, mixed>
	 */
	public function edit_message_text( array $p ) {
		return $this->call( 'editMessageText', $p );
	}

	/**
	 * editMessageReplyMarkup.
	 *
	 * @param array<string, mixed> $p Params.
	 * @return array<string, mixed>
	 */
	public function edit_message_reply_markup( array $p ) {
		return $this->call( 'editMessageReplyMarkup', $p );
	}

	/**
	 * answerCallbackQuery.
	 *
	 * @param array<string, mixed> $p Params.
	 * @return array<string, mixed>
	 */
	public function answer_callback_query( array $p ) {
		return $this->call( 'answerCallbackQuery', $p );
	}

	/**
	 * setWebhook.
	 *
	 * @param array<string, mixed> $p Params.
	 * @return array<string, mixed>
	 */
	public function set_webhook( array $p ) {
		return $this->call( 'setWebhook', $p );
	}

	/**
	 * deleteWebhook.
	 *
	 * @return array<string, mixed>
	 */
	public function delete_webhook() {
		return $this->call( 'deleteWebhook', array() );
	}

	/**
	 * getMe.
	 *
	 * @return array<string, mixed>
	 */
	public function get_me() {
		return $this->call( 'getMe', array() );
	}

	/**
	 * getFile.
	 *
	 * @param array<string, mixed> $p Params.
	 * @return array<string, mixed>
	 */
	public function get_file( array $p ) {
		return $this->call( 'getFile', $p );
	}

	/**
	 * sendInvoice (Bale / Telegram).
	 *
	 * @param array<string, mixed> $p Params.
	 * @return array<string, mixed>
	 */
	public function send_invoice( array $p ) {
		return $this->call( 'sendInvoice', $p );
	}

	/**
	 * answerPreCheckoutQuery.
	 *
	 * @param array<string, mixed> $p Params.
	 * @return array<string, mixed>
	 */
	public function answer_pre_checkout_query( array $p ) {
		return $this->call( 'answerPreCheckoutQuery', $p );
	}

	/**
	 * getChatMember.
	 *
	 * @param array<string, mixed> $p Params chat_id, user_id.
	 * @return array<string, mixed>
	 */
	public function get_chat_member( array $p ) {
		return $this->call( 'getChatMember', $p, 25 );
	}

	/**
	 * pinChatMessage.
	 *
	 * @param array<string, mixed> $p Params.
	 * @return array<string, mixed>
	 */
	public function pin_chat_message( array $p ) {
		return $this->call( 'pinChatMessage', $p, 25 );
	}
}
