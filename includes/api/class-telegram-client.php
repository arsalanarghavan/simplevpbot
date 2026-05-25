<?php
/**
 * Telegram Bot API client.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Telegram_Client
 */
class SimpleVPBot_Telegram_Client extends SimpleVPBot_Bot_Client {

	/**
	 * {@inheritdoc}
	 */
	protected function base_url() {
		if ( class_exists( 'SimpleVPBot_Telegram_Http' ) ) {
			return SimpleVPBot_Telegram_Http::bot_api_base_url( $this->token );
		}
		return 'https://api.telegram.org/bot' . rawurlencode( $this->token ) . '/';
	}
}
