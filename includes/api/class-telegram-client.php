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
		return 'https://api.telegram.org/bot' . rawurlencode( $this->token ) . '/';
	}
}
