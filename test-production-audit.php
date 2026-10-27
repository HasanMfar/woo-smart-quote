<?php
/**
 * Production Readiness & Deep Audit Test Suite
 *
 * Run: powershell -ExecutionPolicy Bypass -File .\wp.ps1 eval-file test-production-audit.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['wsq_pass'] = 0;
$GLOBALS['wsq_fail'] = 0;

function ok( $label, $cond, $extra = '' ) {
	if ( $cond ) {
		$GLOBALS['wsq_pass']++;
		echo "PASS: $label" . ( '' !== $extra ? " | $extra" : '' ) . "\n";
	} else {
		$GLOBALS['wsq_fail']++;
		echo "FAIL: $label" . ( '' !== $extra ? " | $extra" : '' ) . "\n";
	}
}

echo "=== WSQ Production Readiness & Edge Case Audit Suite ===\n";

// Point Bale/Eitaa at mock server
add_filter( 'wsq_bale_base_url', function () {
	return 'http://127.0.0.1:9999/bot';
} );
add_filter( 'wsq_eitaa_base_url', function () {
	return 'http://127.0.0.1:9999/';
} );

// 1. Create a test order
$order = wc_create_order( array( 'status' => 'quote-request' ) );
$product_ids = wc_get_products( array( 'limit' => 1, 'return' => 'ids' ) );
$pid = ! empty( $product_ids ) ? $product_ids[0] : 0;
$p = wc_get_product( $pid );
if ( ! $p ) {
	$p = new WC_Product_Simple();
	$p->set_name( 'کالای تست بررسی تولید' );
	$p->set_regular_price( '500000' );
	$p->set_status( 'publish' );
	$pid = $p->save();
	$p = wc_get_product( $pid );
}

$order->add_product( $p, 2 );
$order->set_billing_first_name( 'حسین' );
$order->set_billing_last_name( 'رستمی' );
$order->set_billing_phone( '09129876543' );
$order->set_billing_email( 'hossein@example.test' );
$order->save();
$order_id = $order->get_id();

ok( 'Seeded audit test order', $order_id > 0, "ID: $order_id" );

// Test Case 1: dispatch_to_buyer with force=true when wsq_buyer_sms_enabled is 'no'
update_option( 'wsq_buyer_sms_enabled', 'no' );
$notifier = WSQ_Buyer_Notifier::instance();
$results_auto = $notifier->dispatch_to_buyer( $order_id, false );
ok( 'Automatic dispatch returns empty when wsq_buyer_sms_enabled is no', empty( $results_auto ) );

$results_force = $notifier->dispatch_to_buyer( $order_id, true );
ok( 'Manual/force dispatch bypasses wsq_buyer_sms_enabled=no', ! empty( $results_force ) );
ok( 'Email attempt processed in force dispatch', isset( $results_force['email'] ) );

// Test Case 2: ajax_resend_buyer_proposal acknowledges Bale and Eitaa channels
$admin_user = get_user_by( 'login', 'admin' );
if ( ! $admin_user ) {
	$users = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
	$admin_user = ! empty( $users ) ? $users[0] : null;
}
wp_set_current_user( $admin_user ? $admin_user->ID : 1 );

if ( ! defined( 'DOING_AJAX' ) ) {
	define( 'DOING_AJAX', true );
}
$_REQUEST['nonce'] = $_POST['nonce'] = wp_create_nonce( 'wsq_buyer_resend_nonce' );
$_POST['order_id'] = $order_id;

add_filter( 'wp_die_ajax_handler', function() {
	return function( $message ) {
		throw new \RuntimeException( (string) $message );
	};
} );

// Mock dispatch_to_buyer returning only bale=true
add_filter( 'wsq_buyer_proposal_dispatch_mock', function() {
	return array( 'email' => false, 'sms' => false, 'bale' => true, 'eitaa' => false );
} );

ob_start();
try {
	$notifier->ajax_resend_buyer_proposal();
} catch ( \Throwable $e ) {
}
$ajax_resend_out = ob_get_clean();
$resend_json = json_decode( $ajax_resend_out, true );

ok( 'ajax_resend_buyer_proposal returns JSON', is_array( $resend_json ) );
ok( 'ajax_resend_buyer_proposal succeeds when at least one messenger channel succeeds', true === ( $resend_json['success'] ?? false ) );
ok( 'ajax_resend_buyer_proposal message includes Bale status', false !== strpos( $resend_json['data']['message'] ?? '', 'ربات بله' ) );

// Test Case 3: REST API negative price clamping
$rest = WSQ_REST_API::instance();
$line_items = $order->get_items();
$item_ids = array_keys( $line_items );
$first_item_id = $item_ids[0];

$req = new WP_REST_Request( 'POST', '/wsq/v1/quotes/' . $order_id . '/price' );
$req->set_url_params( array( 'id' => $order_id ) );
$req->set_header( 'content-type', 'application/json' );
$req->set_body( wp_json_encode( array(
	'items' => array(
		array(
			'item_id' => $first_item_id,
			'custom_price' => -250000, // Negative price attempt
			'discount_percent' => 0,
		),
	),
	'quote_validity' => '۴۸ ساعت',
) ) );

$res = $rest->rest_price_quote( $req );
$data = is_wp_error( $res ) ? null : $res->get_data();

$refreshed_order = wc_get_order( $order_id );
$refreshed_item  = $refreshed_order->get_item( $first_item_id );
ok( 'REST API clamps negative prices to 0', (float) $refreshed_item->get_subtotal() === 0.0, "Subtotal: " . $refreshed_item->get_subtotal() );

// Test Case 4: REST API tax_rate automatic tax calculation
$req_tax = new WP_REST_Request( 'POST', '/wsq/v1/quotes/' . $order_id . '/price' );
$req_tax->set_url_params( array( 'id' => $order_id ) );
$req_tax->set_header( 'content-type', 'application/json' );
$req_tax->set_body( wp_json_encode( array(
	'items' => array(
		array(
			'item_id' => $first_item_id,
			'custom_price' => 1000000, // 1,000,000 * 2 = 2,000,000
			'discount_percent' => 10,  // 10% discount = 1,800,000 net
		),
	),
	'tax_rate' => 10, // 10% Iranian VAT = 180,000 tax
	'quote_validity' => '۷۲ ساعت',
) ) );

$res_tax = $rest->rest_price_quote( $req_tax );
$refreshed_tax_order = wc_get_order( $order_id );

ok( 'REST API calculates custom tax from tax_rate', (float) $refreshed_tax_order->get_total_tax() === 180000.0, "Tax: " . $refreshed_tax_order->get_total_tax() );
ok( 'REST API total includes calculated custom tax', (float) $refreshed_tax_order->get_total() === 1980000.0, "Total: " . $refreshed_tax_order->get_total() );
ok( 'Custom tax rate meta persisted', (float) $refreshed_tax_order->get_meta( '_wsq_custom_tax_rate' ) === 10.0 );

// Test Case 5: Pricing history recorded with authors and formatted values
$history = $refreshed_tax_order->get_meta( '_wsq_pricing_history' );
ok( 'Pricing history array populated', is_array( $history ) && count( $history ) >= 2 );
ok( 'Recent history entry contains total and validity', isset( $history[0]['total'] ) && '۷۲ ساعت' === $history[0]['validity'] );

// Cleanup
$order->delete( true );
ok( 'Cleaned up audit test order', true );

echo "\n=== Production Readiness Audit Results: {$GLOBALS['wsq_pass']} passed, {$GLOBALS['wsq_fail']} failed ===\n";
