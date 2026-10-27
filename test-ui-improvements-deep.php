<?php
/**
 * Deep verification test for UI/UX improvements, digit normalization,
 * form serialization integrity, category quick-search, and dirty-state tracking.
 *
 * Run: wp eval-file test-ui-improvements-deep.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['wsq_pass'] = 0;
$GLOBALS['wsq_fail'] = 0;

function ok_test( $label, $cond, $extra = '' ) {
	if ( $cond ) {
		$GLOBALS['wsq_pass']++;
		echo "PASS: $label" . ( '' !== $extra ? " | $extra" : '' ) . "\n";
	} else {
		$GLOBALS['wsq_fail']++;
		echo "FAIL: $label" . ( '' !== $extra ? " | $extra" : '' ) . "\n";
	}
}

echo "=== Deep Verification for UI Improvements ===\n";

// 1. Verify CSS Files
$admin_css = file_get_contents( WP_PLUGIN_DIR . '/woo-smart-quote/assets/css/admin.css' );
$front_css = file_get_contents( WP_PLUGIN_DIR . '/woo-smart-quote/assets/css/frontend.css' );

ok_test( 'Admin CSS has .wsq-savebar.is-dirty', false !== strpos( $admin_css, '.wsq-savebar.is-dirty' ) );
ok_test( 'Admin CSS has .wsq-savebar-dirty-pill', false !== strpos( $admin_css, '.wsq-savebar-dirty-pill' ) );
ok_test( 'Admin CSS has .wsq-cat-search-input with logical padding-inline-end', false !== strpos( $admin_css, 'padding-inline-end: 38px' ) );
ok_test( 'Admin CSS has .wsq-routes-table tr.wsq-no-match-row td', false !== strpos( $admin_css, '.wsq-routes-table tr.wsq-no-match-row td' ) );
ok_test( 'Admin CSS has .wsq-stamp-preview-card.is-disabled', false !== strpos( $admin_css, '.wsq-stamp-preview-card.is-disabled' ) );
ok_test( 'Admin CSS has .wsq-preview-floating-demo.is-disabled', false !== strpos( $admin_css, '.wsq-preview-floating-demo.is-disabled' ) );

ok_test( 'Frontend CSS has auto-fit for trust bar', false !== strpos( $front_css, 'grid-template-columns: repeat(auto-fit, minmax(200px, 1fr))' ) );
ok_test( 'Frontend CSS has mobile 1-column fallback for channel cards',
	1 === preg_match( '/@media \(max-width: 480px\).*?\.wsq-channel-cards\s*\{[^}]*grid-template-columns:\s*1fr;/s', $front_css )
);
ok_test( 'Frontend CSS has active scale feedback for floating link', false !== strpos( $front_css, '.wsq-floating-link:active' ) );

// 2. Verify JS Files
$admin_js = file_get_contents( WP_PLUGIN_DIR . '/woo-smart-quote/assets/js/admin.js' );
$front_js = file_get_contents( WP_PLUGIN_DIR . '/woo-smart-quote/assets/js/frontend.js' );

ok_test( 'Frontend JS serializes form data BEFORE disabling inputs',
	false !== strpos( $front_js, 'var formData = $form.serialize();' )
	&& strpos( $front_js, 'var formData = $form.serialize();' ) < strpos( $front_js, 'setSubmitting($form, true);' )
);

ok_test( 'Frontend JS normalizes digits in the canonical toInt helper',
	false !== strpos( $front_js, 'parseInt(normalizeDigits(value), 10)' )
);

ok_test( 'Frontend JS normalizes digits on qty button click',
	false !== strpos( $front_js, 'var current = clampQty($input.val());' )
);

ok_test( 'Frontend JS normalizes digits in updateStepperButtons',
	false !== strpos( $front_js, 'var qty = clampQty(value);' )
);

ok_test( 'Admin JS normalizes digits in category search',
	false !== strpos( $admin_js, 'normalizeDigits' ) && false !== strpos( $admin_js, 'textNorm.indexOf(' )
);

ok_test( 'Admin JS searches category cell (name + term_id)',
	false !== strpos( $admin_js, "\$row.find( 'td' ).first().text()" )
);

ok_test( 'Admin JS excludes category search from dirty state',
	false !== strpos( $admin_js, "'input[name], select[name], textarea[name]'" )
	&& false !== strpos( $admin_js, "data( 'wsqDirty'" )
);

// 3. Test Backend Phone Normalization in ajax_submit_quote
$order_class = WSQ_Order::instance();

add_filter( 'wp_doing_ajax', '__return_true' );
add_filter( 'wp_die_ajax_handler', function() {
	return function() {};
} );
add_filter( 'wp_die_handler', function() {
	return function() {};
} );
add_filter( 'wp_die_json_handler', function() {
	return function() {};
} );

// Simulate session with an item
$session = WSQ_Session::instance();
$products = wc_get_products( array( 'limit' => 1, 'return' => 'ids' ) );
$pid = ! empty( $products ) ? $products[0] : 0;
$session->clear();
$session->add_item( array( 'product_id' => $pid, 'quantity' => 2 ) );

// Test submitting with Persian digits
$_POST = array(
	'wsq_submit_nonce' => wp_create_nonce( 'wsq_submit_quote' ),
	'wsq_name'         => 'کاربر تستی فارسی',
	'wsq_email'        => 'persian_test@example.com',
	'wsq_phone'        => '۰۹۱۲۳۴۵۶۷۸۹',
	'wsq_channel'      => 'all',
	'wsq_message'      => 'استعلام آزمایشی اعداد فارسی',
);
$_REQUEST = array_merge( $_REQUEST, $_POST );

ob_start();
try {
	// Call ajax_submit_quote; it will call wp_send_json_success or wp_send_json_error
	$order_class->ajax_submit_quote();
} catch ( \Throwable $e ) {
	// Expected wp_die from wp_send_json
}
$ajax_out = ob_get_clean();
$json = json_decode( $ajax_out, true );

ok_test( 'ajax_submit_quote accepts Persian digits in phone number',
	! empty( $json['success'] ) && true === $json['success'],
	isset( $json['data']['message'] ) ? $json['data']['message'] : $ajax_out
);

if ( ! empty( $json['data']['order_id'] ) ) {
	$submitted_order = wc_get_order( $json['data']['order_id'] );
	$saved_phone = $submitted_order ? $submitted_order->get_meta( '_wsq_customer_phone' ) : '';
	ok_test( 'Saved customer phone is normalized to ASCII', '09123456789' === $saved_phone, "saved=$saved_phone" );
} else {
	ok_test( 'Saved customer phone is normalized to ASCII', false, 'no order_id' );
}

// 4. Test Arabic digits submission
$session->add_item( array( 'product_id' => $pid, 'quantity' => 1 ) );
$_POST = array(
	'wsq_submit_nonce' => wp_create_nonce( 'wsq_submit_quote' ),
	'wsq_name'         => 'کاربر تستی عربی',
	'wsq_email'        => 'arabic_test@example.com',
	'wsq_phone'        => '٠٩٣٥٤٤٤٥٥٦٦',
	'wsq_channel'      => 'all',
	'wsq_message'      => 'استعلام آزمایشی اعداد عربی',
);
$_REQUEST = array_merge( $_REQUEST, $_POST );

ob_start();
try {
	$order_class->ajax_submit_quote();
} catch ( \Throwable $e ) {
}
$ajax_out_ar = ob_get_clean();
$json_ar = json_decode( $ajax_out_ar, true );

ok_test( 'ajax_submit_quote accepts Arabic digits in phone number',
	! empty( $json_ar['success'] ) && true === $json_ar['success'],
	isset( $json_ar['data']['message'] ) ? $json_ar['data']['message'] : $ajax_out_ar
);

if ( ! empty( $json_ar['data']['order_id'] ) ) {
	$submitted_order_ar = wc_get_order( $json_ar['data']['order_id'] );
	$saved_phone_ar = $submitted_order_ar ? $submitted_order_ar->get_meta( '_wsq_customer_phone' ) : '';
	ok_test( 'Saved customer phone from Arabic digits is normalized to ASCII', '09354445566' === $saved_phone_ar, "saved=$saved_phone_ar" );
} else {
	ok_test( 'Saved customer phone from Arabic digits is normalized to ASCII', false, 'no order_id' );
}

echo "\n=== Deep Verification Result: {$GLOBALS['wsq_pass']} passed, {$GLOBALS['wsq_fail']} failed ===\n";
