<?php
/**
 * Bale API Wrapper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSQ_Bale_API extends WSQ_Messenger_API {

	public function __construct( $token ) {
		parent::__construct( $token );
		// Kept as literal concatenation: Bale expects /bot{TOKEN}/METHOD.
		// The filter exists for staging/testing contexts only.
		$this->base_url = strval( apply_filters( 'wsq_bale_base_url', 'https://tapi.bale.ai/bot' ) );
		$this->name     = 'bale';
	}

}
