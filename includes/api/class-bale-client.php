<?php
/**
 * Bale Bot API client.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Bale_Client
 */
class SimpleVPBot_Bale_Client extends SimpleVPBot_Bot_Client {

	/**
	 * {@inheritdoc}
	 */
	protected function base_url() {
		return 'https://tapi.bale.ai/bot' . rawurlencode( $this->token ) . '/';
	}
}
