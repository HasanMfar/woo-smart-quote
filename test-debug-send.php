<?php
/**
 * Debugging mock send failure.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$token = 'TESTBADTOKEN';
update_option( 'wsq_bale_token', $token );
$url = 'http://127.0.0.1:9999/bot' . $token . '/sendDocument';

$resp = wp_remote_post( $url, array( 'body' => array( 'chat_id' => '555000', 'text' => 'probe' ), 'timeout' => 10 ) );
if ( is_wp_error( $resp ) ) {
	echo 'WP ERROR: ' . $resp->get_error_code() . ' ' . $resp->get_error_message() . "\n";
} else {
	echo 'HTTP CODE: ' . wp_remote_retrieve_response_code( $resp ) . "\n";
	echo 'BODY: ' . substr( wp_remote_retrieve_body( $resp ), 0, 200 ) . "\n";
}

// Share level config of the logger
echo 'log level option: ' . get_option( 'woocommerce_logger_level', '(unset)' ) . "\n";
$sources = wc_get_logger()->get_sources() ?? array();
echo 'sources: ' . wp_json_encode( $sources ) . "\n";
