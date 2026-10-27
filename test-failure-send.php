<?php
/**
 * Failure-path test: base URL at a dead port.
 * Expect: failed target recorded, attempt meta=1, retry action scheduled.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'wsq_bale_base_url', function () {
	return 'http://127.0.0.1:9998/bot';
} );

update_option( 'wsq_bale_token', 'TESTTOKENMOCK' );

$order_id = 25;
do_action( 'wsq_process_new_quote', $order_id );

$order = wc_get_order( $order_id );
echo 'attempts: ' . $order->get_meta( '_wsq_expert_notify_attempts' ) . "\n";
echo 'failed: ' . wp_json_encode( $order->get_meta( '_wsq_expert_notify_failed_targets' ) ) . "\n";

// Pending actions
$acts = as_get_scheduled_actions( array(
	'group'  => 'woo_smart_quote',
	'status' => 'pending',
), OBJECT );
foreach ( $acts as $a ) {
	echo 'PENDING: ' . ( method_exists( $a, 'get_hook' ) ? $a->get_hook() : '' ) . "\n";
}
