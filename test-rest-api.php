<?php
/**
 * Test Suite for WSQ REST API & QR Code Pairing
 *
 * Run: powershell -ExecutionPolicy Bypass -File .\wp.ps1 eval-file test-rest-api.php
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

echo "=== WSQ REST API & Pairing Test Suite ===\n";

// 1. Check REST API Controller Class
ok( 'WSQ_REST_API class exists', class_exists( 'WSQ_REST_API' ) );
$rest = WSQ_REST_API::instance();
ok( 'WSQ_REST_API instance created', ! empty( $rest ) );

// 2. Test Pairing Session Generation
$admin_user = get_user_by( 'login', 'admin' );
if ( ! $admin_user ) {
	$users = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
	$admin_user = ! empty( $users ) ? $users[0] : null;
}
$admin_id = $admin_user ? $admin_user->ID : 1;
wp_set_current_user( $admin_id );

delete_transient( WSQ_REST_API::TRANSIENT_PAIR );
$session = WSQ_REST_API::get_or_create_pairing_session( $admin_id, true );
ok( 'Pairing session generated', ! empty( $session['pairing_code'] ) );
ok( 'Pairing code format is XXXX-XXXX', (bool) preg_match( '/^\d{4}-\d{4}$/', $session['pairing_code'] ), $session['pairing_code'] );
ok( 'Pairing expires in ~600 seconds', $session['expires_in'] > 550 && $session['expires_in'] <= 600, (string) $session['expires_in'] );

$payload = json_decode( $session['payload_json'], true );
ok( 'Payload JSON is valid', is_array( $payload ) && isset( $payload['pairing_code'] ) );
ok( 'Payload contains api_base', isset( $payload['api_base'] ) && false !== strpos( $payload['api_base'], 'wsq/v1' ) );

// 3. Test REST Route: POST /wsq/v1/auth/pair with invalid code
$server = rest_get_server();

$req_invalid = new WP_REST_Request( 'POST', '/wsq/v1/auth/pair' );
$req_invalid->set_header( 'Content-Type', 'application/json' );
$req_invalid->set_body( wp_json_encode( array(
	'pairing_code' => '9999-9999',
	'device_name'  => 'Test Phone',
	'device_id'    => 'test_dev_01',
) ) );
$res_invalid = $server->dispatch( $req_invalid );
ok( 'Pairing with wrong code is rejected with 403', 403 === $res_invalid->get_status() );

// 4. Test REST Route: POST /wsq/v1/auth/pair with valid code
$req_valid = new WP_REST_Request( 'POST', '/wsq/v1/auth/pair' );
$req_valid->set_header( 'Content-Type', 'application/json' );
$req_valid->set_body( wp_json_encode( array(
	'pairing_code' => $session['pairing_code'],
	'device_name'  => 'Galaxy S24 Ultra',
	'device_id'    => 'galaxy_s24_test_id',
) ) );
$res_valid = $server->dispatch( $req_valid );
ok( 'Pairing with valid code returns 200', 200 === $res_valid->get_status() );
$data_valid = $res_valid->get_data();
ok( 'Pairing response has token', ! empty( $data_valid['token'] ) && 0 === strpos( $data_valid['token'], 'wsq_sec_' ) );
ok( 'Pairing response has store name', ! empty( $data_valid['store_name'] ) );
ok( 'Pairing response has currency', ! empty( $data_valid['currency'] ) );

$issued_token = $data_valid['token'];

// Transient must be consumed
$session_after = get_transient( WSQ_REST_API::TRANSIENT_PAIR );
ok( 'Pairing transient consumed after success', empty( $session_after ) );

// 5. Test Paired Devices List
$devices = WSQ_REST_API::get_paired_devices();
ok( 'Device saved in paired devices option', isset( $devices['galaxy_s24_test_id'] ) );
ok( 'Device has correct name', 'Galaxy S24 Ultra' === $devices['galaxy_s24_test_id']['device_name'] );

// 6. Test Authentication with Token
wp_set_current_user( 0 ); // Log out WordPress session

$req_unauth = new WP_REST_Request( 'GET', '/wsq/v1/quotes' );
$res_unauth = $server->dispatch( $req_unauth );
ok( 'Request without token is rejected with 401', 401 === $res_unauth->get_status() );

$req_auth = new WP_REST_Request( 'GET', '/wsq/v1/quotes' );
$req_auth->set_header( 'Authorization', 'Bearer ' . $issued_token );
$res_auth = $server->dispatch( $req_auth );
ok( 'Request with Bearer token is accepted with 200', 200 === $res_auth->get_status() );
$quotes_data = $res_auth->get_data();
ok( 'Response contains quotes array and pagination', isset( $quotes_data['quotes'] ) && isset( $quotes_data['pagination'] ) );

// 7. Seed a Test Quote Order
$order = wc_create_order( array(
	'status'        => 'quote-request',
	'customer_note' => 'درخواست پیش‌فاکتور برای ۱۰ عدد مانیتور',
) );
$order->set_billing_first_name( 'کامران' );
$order->set_billing_last_name( 'نجفی' );
$order->set_billing_email( 'kamran@example.test' );
$order->set_billing_phone( '09123456789' );
$order->update_meta_data( '_wsq_customer_phone', '09123456789' );

$item = new WC_Order_Item_Product();
$item->set_name( 'مانیتور ۲۷ اینچ ایسوس' );
$item->set_quantity( 1 );
$item->set_subtotal( 0 );
$item->set_total( 0 );
$item_id = $order->add_item( $item );
$order->calculate_totals();
$order->save();
$order_id = $order->get_id();

ok( 'Seeded test quote order', $order_id > 0, "ID: $order_id" );

// 8. Test Single Quote Details: GET /wsq/v1/quotes/{id}
$req_detail = new WP_REST_Request( 'GET', '/wsq/v1/quotes/' . $order_id );
$req_detail->set_header( 'Authorization', 'Bearer ' . $issued_token );
$res_detail = $server->dispatch( $req_detail );
ok( 'GET /wsq/v1/quotes/{id} returns 200', 200 === $res_detail->get_status() );
$detail_data = $res_detail->get_data();
ok( 'Detail has customer name', 'کامران نجفی' === $detail_data['customer']['name'] );
ok( 'Detail has customer phone', '09123456789' === $detail_data['customer']['phone'] );
ok( 'Detail has customer note', false !== strpos( $detail_data['customer']['note'], 'مانیتور' ) );
ok( 'Detail has line items', count( $detail_data['items'] ) >= 1 );

// 9. Test Mobile Pricing Desk: POST /wsq/v1/quotes/{id}/price
$real_item_id = ! empty( $detail_data['items'][0]['item_id'] ) ? $detail_data['items'][0]['item_id'] : $item->get_id();
$req_price = new WP_REST_Request( 'POST', '/wsq/v1/quotes/' . $order_id . '/price' );
$req_price->set_header( 'Authorization', 'Bearer ' . $issued_token );
$req_price->set_header( 'Content-Type', 'application/json' );
$req_price->set_body( wp_json_encode( array(
	'items' => array(
		array(
			'item_id'          => $real_item_id,
			'custom_price'     => 12500000,
			'discount_percent' => 5, // 5% discount
		),
	),
	'quote_validity' => '۴۸ ساعت کاری',
	'admin_note'     => 'قیمت همکاری ویژه با تخفیف ۵ درصدی اعمال شد.',
	'auto_notify'    => false,
) ) );
$res_price = $server->dispatch( $req_price );
ok( 'POST /wsq/v1/quotes/{id}/price returns 200', 200 === $res_price->get_status() );
$price_data = $res_price->get_data();
ok( 'Price response marked success', true === $price_data['success'] );
ok( 'Status updated to quote-sent', 'quote-sent' === $price_data['status'] );
// 12,500,000 * 0.95 = 11,875,000
ok( 'Grand total calculated with 5% discount', 11875000.0 === (float) $price_data['total'], "Total: " . $price_data['total'] );
ok( 'PDF generated and URL returned', ! empty( $price_data['pdf_url'] ) );

// 10. Test Pricing History in GET /wsq/v1/quotes/{id}
$req_detail_priced = new WP_REST_Request( 'GET', '/wsq/v1/quotes/' . $order_id );
$req_detail_priced->set_header( 'Authorization', 'Bearer ' . $issued_token );
$res_detail_priced = $server->dispatch( $req_detail_priced );
$detail_priced_data = $res_detail_priced->get_data();
ok( 'Detail includes pricing history array', isset( $detail_priced_data['pricing_history'] ) && is_array( $detail_priced_data['pricing_history'] ) );
ok( 'Pricing history has recent entry', ! empty( $detail_priced_data['pricing_history'] ) && false !== strpos( $detail_priced_data['pricing_history'][0]['admin_note'], 'تخفیف ۵ درصدی' ) );

// 11. Test PDF Endpoint: POST /wsq/v1/quotes/{id}/pdf
$req_pdf = new WP_REST_Request( 'POST', '/wsq/v1/quotes/' . $order_id . '/pdf' );
$req_pdf->set_header( 'Authorization', 'Bearer ' . $issued_token );
$res_pdf = $server->dispatch( $req_pdf );
ok( 'POST /wsq/v1/quotes/{id}/pdf returns 200', 200 === $res_pdf->get_status() );
$pdf_data = $res_pdf->get_data();
ok( 'PDF URL targets the signed download endpoint', false !== strpos( $pdf_data['pdf_url'], 'action=wsq_download_pdf' ) );

// 12. Test Customer Notification Dispatch: POST /wsq/v1/quotes/{id}/notify
$req_notify = new WP_REST_Request( 'POST', '/wsq/v1/quotes/' . $order_id . '/notify' );
$req_notify->set_header( 'Authorization', 'Bearer ' . $issued_token );
$res_notify = $server->dispatch( $req_notify );
ok( 'POST /wsq/v1/quotes/{id}/notify returns 200', 200 === $res_notify->get_status() );
$notify_data = $res_notify->get_data();
ok( 'Notify response marked success', true === $notify_data['success'] );

// 13. Test Background Polling: GET /wsq/v1/updates?since={ts}
$req_upd = new WP_REST_Request( 'GET', '/wsq/v1/updates' );
$req_upd->set_header( 'Authorization', 'Bearer ' . $issued_token );
$req_upd->set_param( 'since', time() - 3600 );
$res_upd = $server->dispatch( $req_upd );
ok( 'GET /wsq/v1/updates returns 200', 200 === $res_upd->get_status() );
$upd_data = $res_upd->get_data();
ok( 'Updates payload contains pending_count', isset( $upd_data['pending_count'] ) );
ok( 'Updates payload contains server_time', isset( $upd_data['server_time'] ) );

// 14. Test Device Revocation
$revoked = WSQ_REST_API::revoke_device( 'galaxy_s24_test_id' );
ok( 'Device revoked successfully', true === $revoked );
$devices_after = WSQ_REST_API::get_paired_devices();
ok( 'Device removed from devices list', ! isset( $devices_after['galaxy_s24_test_id'] ) );

// 15. Verify Revoked Token is Rejected
$res_revoked = $server->dispatch( $req_auth );
ok( 'Revoked token rejected with 401', 401 === $res_revoked->get_status() );

// 16. Test Persian digits in pairing code normalization
$persian_session = WSQ_REST_API::get_or_create_pairing_session( $admin_id, true );
$raw_code = $persian_session['pairing_code'];
$persian_map = array( '0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹' );
$persian_code = strtr( $raw_code, $persian_map );
$req_persian = new WP_REST_Request( 'POST', '/wsq/v1/auth/pair' );
$req_persian->set_header( 'Content-Type', 'application/json' );
$req_persian->set_body( wp_json_encode( array(
	'pairing_code' => $persian_code,
	'device_name'  => 'Persian Digits Device',
	'device_id'    => 'persian_test_dev',
) ) );
$res_persian = $server->dispatch( $req_persian );
ok( 'Pairing accepts Persian numerals (۱۲۳۴-۵۶۷۸)', 200 === $res_persian->get_status() );
WSQ_REST_API::revoke_device( 'persian_test_dev' );

// Cleanup test order
$order->delete( true );
ok( 'Cleaned up test quote order', true );

echo "\n=== Test Results: {$GLOBALS['wsq_pass']} passed, {$GLOBALS['wsq_fail']} failed ===\n";
