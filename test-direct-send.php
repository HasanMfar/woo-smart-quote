<?php
/**
 * Direct send_document test with retries (proxy flake on localhost).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'wsq_bale_base_url', function () {
	return 'http://127.0.0.1:9999/bot';
} );

require_once WSQ_PLUGIN_DIR . 'includes/class-wsq-bale-api.php';

$order  = wc_get_order( 25 );
$secret = $order->get_meta( '_wsq_pdf_secret' );
$pdf_path = wp_upload_dir()['basedir'] . '/woo-smart-quote-pdfs/quote-25-' . $secret . '.pdf';

$api = new WSQ_Bale_API( 'TESTTOKENMOCK' );

$reply = null;
for ( $i = 0; $i < 6; $i++ ) {
	$reply = $api->send_document( '555000', $pdf_path, 'test caption 🚀' );
	if ( ! empty( $reply['ok'] ) ) {
		break;
	}
	usleep( 400000 );
}
echo 'reply: ' . wp_json_encode( $reply ) . "\n";

// Text-only send, retried as well.
$reply2 = null;
for ( $i = 0; $i < 6; $i++ ) {
	$reply2 = $api->send_message( '555000', 'text probe <b>bold</b>' );
	if ( ! empty( $reply2['ok'] ) ) {
		break;
	}
	usleep( 400000 );
}
echo 'reply2: ' . wp_json_encode( $reply2 ) . "\n";
