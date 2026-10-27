<?php
/**
 * Controlled expert-notification test against the local mock API.
 * Point: verifies multipart doc dispatch end-to-end.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Route the Bale API at the local mock for this run.
add_filter( 'wsq_bale_base_url', function () {
	return 'http://127.0.0.1:9999/bot';
} );

update_option( 'wsq_bale_token', 'TESTTOKENMOCK' );

$order_id = 25;
do_action( 'wsq_process_new_quote', $order_id );

// Reset the attempt counter so later runs are clean.
$order = wc_get_order( $order_id );
$order->delete_meta_data( '_wsq_expert_notify_attempts' );
$order->save();

// Show the resulting state.
$fail = $order->get_meta( '_wsq_expert_notify_failed_targets' );
echo "failed_targets_after_send: " . ( $fail ? wp_json_encode( $fail ) : '(none)' ) . "\n";
