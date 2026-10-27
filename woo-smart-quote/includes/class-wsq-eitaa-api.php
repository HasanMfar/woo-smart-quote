<?php
/**
 * Eitaa API Wrapper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSQ_Eitaa_API extends WSQ_Messenger_API {

	public function __construct( $token ) {
		parent::__construct( $token );
		// Eitaayar expects /api/{TOKEN}/METHOD — the default base already
		// carries the trailing slash, matching upstream docs.
		$this->base_url   = strval( apply_filters( 'wsq_eitaa_base_url', 'https://eitaayar.ir/api/' ) );
		$this->name       = 'eitaa';
		// Eitaayar's manual lists both sendDocument and sendFile but only
		// documents sendFile with a `file` field. Try that first, then the
		// Telegram-compatible name, so a panel that exposes either still works.
		$this->file_field        = 'file';
		$this->document_attempts = array(
			array(
				'method' => 'sendFile',
				'field'  => 'file',
			),
			array(
				'method' => 'sendDocument',
				'field'  => 'document',
			),
		);
	}

}
