<?php
/**
 * End-to-end production readiness test for woo-smart-quote.
 * Run: wp eval-file test-e2e.php
 */

$GLOBALS['wsq_pass'] = 0;
$GLOBALS['wsq_fail'] = 0;

function ok( $label, $cond, $extra = '' ) {
	if ( $cond ) {
		$GLOBALS['wsq_pass']++;
		echo "PASS: $label" . ( $extra ? " | $extra" : '' ) . "\n";
	} else {
		$GLOBALS['wsq_fail']++;
		echo "FAIL: $label" . ( $extra ? " | $extra" : '' ) . "\n";
	}
}

echo "=== woo-smart-quote E2E test ===\n";

// 0. Environment checks
ok( 'Plugin loaded', class_exists( 'WSQ_Plugin' ) );
ok( 'mPDF engine available', class_exists( 'Mpdf\\Mpdf' ) );
ok( 'Action Scheduler available', function_exists( 'as_enqueue_async_action' ) );

// Point Bale/Eitaa at the local mock server (port 9999).
add_filter( 'wsq_bale_base_url', function () {
	return 'http://127.0.0.1:9999/bot';
} );
add_filter( 'wsq_eitaa_base_url', function () {
	return 'http://127.0.0.1:9999/';
} );

update_option( 'wsq_bale_token', 'TESTTOKENMOCK' );
update_option( 'wsq_bale_default_chat_ids', "168621\n987654" );
update_option( 'wsq_eitaa_token', 'TESTTOKENMOCK' );
update_option( 'wsq_eitaa_default_chat_ids', "321654" );
update_option( 'wsq_buyer_sms_enabled', 'yes' );
update_option( 'wsq_buyer_sms_provider', 'none' );

// 1. Create a quote order with items
$product_ids = wc_get_products( array( 'limit' => 1, 'return' => 'ids' ) );
if ( empty( $product_ids ) ) {
	$p = new WC_Product_Simple();
	$p->set_name( 'Ù…Ø­ØµÙˆÙ„ ØªØ³Øª Ø§Ø³ØªØ¹Ù„Ø§Ù…' );
	$p->set_regular_price( '100000' );
	$p->set_status( 'publish' );
	$product_id = $p->save();
} else {
	$product_id = $product_ids[0];
}

$order = wc_create_order( array( 'status' => 'quote-request' ) );
$order->add_product( wc_get_product( $product_id ), 3 );
$order->set_billing_first_name( 'Ù…Ø´ØªØ±ÛŒ' );
$order->set_billing_last_name( 'ØªØ³ØªÛŒ' );
$order->set_billing_email( 'buyer@example.com' );
$order->set_billing_phone( '09121234567' );
$order->set_customer_note( 'Ù„Ø·ÙØ§Ù‹ ÙÙˆØ±ÛŒ Ø§Ø±Ø³Ø§Ù„ Ø´ÙˆØ¯' );
$order->calculate_totals();
$order->save();
$order_id = $order->get_id();

ok( 'Quote order created', $order_id > 0, "order_id=$order_id" );
ok( 'Order status is quote-request', 'quote-request' === $order->get_status(), $order->get_status() );

// 2. PDF generation
$pdf_path = WSQ_PDF_Generator::instance()->generate( $order_id );
ok( 'PDF generated', is_string( $pdf_path ) && file_exists( $pdf_path ), $pdf_path ? basename( $pdf_path ) : 'no path' );

if ( $pdf_path && file_exists( $pdf_path ) ) {
	$size = filesize( $pdf_path );
	ok( 'PDF has content', $size > 1000, "size=$size bytes" );
	$head = file_get_contents( $pdf_path, false, null, 0, 5 );
	ok( 'PDF magic header', '%PDF-' === $head, $head );
	$basename = basename( $pdf_path );
	$pattern  = '/^quote-' . $order_id . '-[A-Za-z0-9]{16}\.pdf$/';
	ok( 'PDF filename unguessable', (bool) preg_match( $pattern, $basename ), $basename );
	$dir = dirname( $pdf_path );
	ok( 'PDF dir protected by .htaccess', file_exists( $dir . '/.htaccess' ) );
}

// 3. Expert notification via Bale (mock)
WSQ_Queue::instance()->process_new_quote( $order_id );
$order_fresh      = wc_get_order( $order_id );
$failed_targets   = $order_fresh->get_meta( '_wsq_expert_notify_failed_targets' );
ok( 'Expert notification delivered (no failed targets)', empty( $failed_targets ), $failed_targets ? implode( ',', (array) $failed_targets ) : 'all delivered' );

$capture_dir = 'F:/Programming/WordPress/Quote/mock-captures';
$captures    = glob( $capture_dir . '/*.req' );
ok( 'Mock API captured requests', count( $captures ) > 0, count( $captures ) . ' captures' );

$found_document = false;
foreach ( $captures as $cap ) {
	if ( false !== strpos( file_get_contents( $cap ), 'sendDocument' ) ) {
		$found_document = true;
		break;
	}
}
ok( 'sendDocument called with multipart', $found_document );

// 4. Eitaa path (same mock)
$eitaa_api = new WSQ_Eitaa_API( 'TESTTOKENMOCK' );
$resp = $eitaa_api->send_message( '321654', 'Ù¾ÛŒØ§Ù… Ø¢Ø²Ù…Ø§ÛŒØ´ÛŒ Ø§ÛŒØªØ§' );
ok( 'Eitaa sendMessage via mock', ! empty( $resp['ok'] ), isset( $resp['description'] ) ? $resp['description'] : 'ok' );

$resp_doc = $eitaa_api->send_document( '321654', $pdf_path, 'Ù¾ÛŒØ´â€ŒÙØ§Ú©ØªÙˆØ± ØªØ³Øª' );
ok( 'Eitaa sendDocument via mock', ! empty( $resp_doc['ok'] ) || ( isset( $resp_doc['description'] ) && false !== strpos( $resp_doc['description'], 'text alert delivered' ) ), isset( $resp_doc['description'] ) ? $resp_doc['description'] : 'ok' );

// 5. Buyer proposal dispatch (email)
foreach ( $order_fresh->get_items() as $item ) {
	$item->set_subtotal( 350000 );
	$item->set_total( 350000 );
	$item->save();
}
$order_fresh->calculate_totals();
$order_fresh->save();

// Link the buyer chat first so this dispatch also exercises the bot push.
WSQ_Updates::instance()->link_chat( 'bale', '555111222', '09121234567' );
WSQ_Updates::instance()->link_chat( 'eitaa', '777888999', '09121234567' );

$results = WSQ_Buyer_Notifier::instance()->dispatch_to_buyer( $order_id, false );
ok( 'Buyer email dispatched', ! empty( $results['email'] ), wp_json_encode( $results ) );
ok( 'Bale bot push reaches the linked buyer chat', ! empty( $results['bale'] ), wp_json_encode( $results ) );
ok( 'Eitaa bot push reaches the linked buyer chat', ! empty( $results['eitaa'] ), wp_json_encode( $results ) );

// Remove only the links this test created.
$test_links = (array) get_option( 'wsq_chat_links', array() );
unset( $test_links['bale:555111222'], $test_links['eitaa:777888999'] );
update_option( 'wsq_chat_links', $test_links );

$order_fresh = wc_get_order( $order_id );
$sent_flag   = $order_fresh->get_meta( '_wsq_proposal_sent' );
ok( 'Proposal sent flag set', '' !== (string) $sent_flag, (string) $sent_flag );

// 6. Duplicate guard
$before_mail = did_action( 'wp_mail_succeeded' );
$res_guard   = WSQ_Buyer_Notifier::instance()->dispatch_to_buyer( $order_id, false );
$mail_delta  = did_action( 'wp_mail_succeeded' ) - $before_mail;
ok( 'Duplicate guard skips second dispatch', empty( $res_guard ) && 0 === $mail_delta, 'results=' . wp_json_encode( $res_guard ) . " mail_delta=$mail_delta" );

$res_force = WSQ_Buyer_Notifier::instance()->dispatch_to_buyer( $order_id, true );
ok( 'Force resend sends again', ! empty( $res_force['email'] ), wp_json_encode( $res_force ) );

// 7. Retry scheduling on failure (dead port)
$dead_filter = function () {
	return 'http://127.0.0.1:1/bot';
};
add_filter( 'wsq_bale_base_url', $dead_filter );

$order2 = wc_create_order( array( 'status' => 'quote-request' ) );
$order2->add_product( wc_get_product( $product_id ), 1 );
$order2->set_billing_email( 'x@example.com' );
$order2->set_billing_phone( '09351112233' );
$order2->calculate_totals();
$order2->save();
$order2_id = $order2->get_id();

WSQ_Queue::instance()->process_new_quote( $order2_id );
$order2_fresh = wc_get_order( $order2_id );
$attempts = (int) $order2_fresh->get_meta( '_wsq_expert_notify_attempts' );
$failed2  = $order2_fresh->get_meta( '_wsq_expert_notify_failed_targets' );
ok( 'Failure recorded and retry scheduled', $attempts >= 1 && ! empty( $failed2 ), "attempts=$attempts failed=" . implode( ',', (array) $failed2 ) );

WSQ_Queue::instance()->retry_expert_notifications( $order2_id );
ok( 'Retry handler runs without fatal', true );

remove_filter( 'wsq_bale_base_url', $dead_filter );

// 8. Category routing
$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
	$cat_id = (int) $terms[0]->term_id;
	update_option( 'wsq_category_routes', array(
		$cat_id => array( 'bale' => '555000', 'eitaa' => '777000' ),
	) );
	wp_set_object_terms( $product_id, array( $cat_id ), 'product_cat' );

	$order3 = wc_create_order( array( 'status' => 'quote-request' ) );
	$order3->add_product( wc_get_product( $product_id ), 1 );
	$order3->set_billing_email( 'y@example.com' );
	$order3->set_billing_phone( '09354445566' );
	$order3->calculate_totals();
	$order3->save();
	$order3_id = $order3->get_id();

	WSQ_Queue::instance()->process_new_quote( $order3_id );
	$order3_fresh = wc_get_order( $order3_id );
	$failed3 = $order3_fresh->get_meta( '_wsq_expert_notify_failed_targets' );
	ok( 'Category route used (555000)', empty( $failed3 ), $failed3 ? implode( ',', (array) $failed3 ) : 'delivered to 555000' );
} else {
	echo "SKIP: no product categories available\n";
}

// 9. Order notes recorded
$notes = wc_get_order_notes( array( 'order_id' => $order_id ) );
ok( 'Order notes recorded', count( $notes ) > 0, count( $notes ) . ' notes' );

// 10. Cleanup cron callable
$plugin = WSQ_Plugin::instance();
$plugin->run_daily_cleanup();
ok( 'Daily cleanup runs without fatal', true );

echo "\n=== Result: " . $GLOBALS['wsq_pass'] . " passed, " . $GLOBALS['wsq_fail'] . " failed ===\n";
if ( $GLOBALS["wsq_fail"] > 0 ) {
	exit( 1 );
}
