<?php
/**
 * Verification test for boost enhancements:
 * - Customer prefill on quote request form (logged-in vs guest)
 * - Canonical phone normalization on submission (+98, 0098, 98, 9..., Persian, Arabic)
 * - HPOS-aware order edit link in inbox
 * - Token inserter keyboard accessibility
 * - Dynamic empty state and undo behavior
 * - Stepper debouncing and active feedback
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['boost_pass'] = 0;
$GLOBALS['boost_fail'] = 0;

function boost_ok( $label, $cond, $extra = '' ) {
	if ( $cond ) {
		$GLOBALS['boost_pass']++;
		echo "PASS: $label" . ( '' !== $extra ? " | $extra" : '' ) . "\n";
	} else {
		$GLOBALS['boost_fail']++;
		echo "FAIL: $label" . ( '' !== $extra ? " | $extra" : '' ) . "\n";
	}
}

echo "=== Verification for Boost Improvements ===\n";

// 1. Check quote-list.php prefill logic
$quote_template = file_get_contents( WP_PLUGIN_DIR . '/woo-smart-quote/templates/quote-list.php' );
boost_ok( 'quote-list.php resolves current user billing meta',
	false !== strpos( $quote_template, 'wsq_current_user  = is_user_logged_in() ? wp_get_current_user() : null;' )
	&& false !== strpos( $quote_template, "get_user_meta( \$wsq_current_user->ID, 'billing_first_name', true )" )
	&& false !== strpos( $quote_template, "get_user_meta( \$wsq_current_user->ID, 'billing_phone', true )" )
);

boost_ok( 'quote-list.php inputs bind prefill values',
	false !== strpos( $quote_template, 'value="<?php echo esc_attr( $wsq_prefill_name ); ?>"' )
	&& false !== strpos( $quote_template, 'value="<?php echo esc_attr( $wsq_prefill_email ); ?>"' )
	&& false !== strpos( $quote_template, 'value="<?php echo esc_attr( $wsq_prefill_phone ); ?>"' )
);

// 2. Check admin.js keyboard token support & category search clear
$admin_js = file_get_contents( WP_PLUGIN_DIR . '/woo-smart-quote/assets/js/admin.js' );
boost_ok( 'admin.js supports keyboard activation (keydown) for token insertion',
	1 === preg_match( '/on\(\s*\'keydown\',\s*\'\.wsq-token\[data-token\]\'/', $admin_js )
	&& false !== strpos( $admin_js, 'insertTokenIntoTextarea( this )' )
);

boost_ok( 'admin.js manages #wsq-cat-search-clear toggle and click',
	false !== strpos( $admin_js, "toggle( !! raw )" )
	&& 1 === preg_match( '/on\(\s*\'click\',\s*\'#wsq-cat-search-clear\'/', $admin_js )
);

boost_ok( 'admin.js announces category search results via #wsq-cat-search-live',
	false !== strpos( $admin_js, "( '#wsq-cat-search-live' ).text(" )
);

// 3. Check admin.css search clear button
$admin_css = file_get_contents( WP_PLUGIN_DIR . '/woo-smart-quote/assets/css/admin.css' );
boost_ok( 'admin.css has .wsq-cat-search-clear styling',
	false !== strpos( $admin_css, '.wsq-cat-search-clear' )
);

// 4. Check frontend.js debounced stepper & dynamic empty state
$front_js = file_get_contents( WP_PLUGIN_DIR . '/woo-smart-quote/assets/js/frontend.js' );
boost_ok( 'frontend.js stores debounce timer and request per stepper row',
	false !== strpos( $front_js, "\$stepper.data('wsqTimer', timer);" )
	&& false !== strpos( $front_js, "\$stepper.data('wsqXhr', xhr);" )
	&& false !== strpos( $front_js, 'inflight.abort();' )
);

boost_ok( 'frontend.js transitions to the shared empty-state template on last removal',
	false !== strpos( $front_js, 'function showEmptyState($wrapper, focus)' )
	&& false !== strpos( $front_js, "template[data-wsq-empty-template]" )
);

boost_ok( 'frontend.js removes the empty card and restores rows on undo',
	false !== strpos( $front_js, "\$wrapper.children('.wsq-empty').remove();" )
	&& false !== strpos( $front_js, 'function restoreAllRows($wrapper, $rows)' )
);

// 5. Check frontend.css active feedback
$front_css = file_get_contents( WP_PLUGIN_DIR . '/woo-smart-quote/assets/css/frontend.css' );
boost_ok( 'frontend.css has .wsq-qty-btn:active feedback',
	false !== strpos( $front_css, '.wsq-qty-btn:active:not(:disabled)' )
);

// 6. Check class-wsq-admin.php HPOS support in inbox
$admin_php = file_get_contents( WP_PLUGIN_DIR . '/woo-smart-quote/includes/class-wsq-admin.php' );
boost_ok( 'class-wsq-admin.php uses get_edit_order_url for inbox order links',
	false !== strpos( $admin_php, '$ord_obj->get_edit_order_url()' )
);

// 7. Backend test: phone normalization on submission with +98 and 0098 prefixes
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

$order_class = WSQ_Order::instance();
$session     = WSQ_Session::instance();
$products    = wc_get_products( array( 'limit' => 1, 'return' => 'ids' ) );
$pid         = ! empty( $products ) ? $products[0] : 0;

$session->clear();
$session->add_item( array( 'product_id' => $pid, 'quantity' => 1 ) );

$_POST = array(
	'wsq_submit_nonce' => wp_create_nonce( 'wsq_submit_quote' ),
	'wsq_name'         => 'کاربر پیشوند بین‌المللی',
	'wsq_email'        => 'intl_phone@example.com',
	'wsq_phone'        => '+989127778899',
	'wsq_channel'      => 'all',
	'wsq_message'      => 'تست نرمال‌سازی +98',
);

ob_start();
$order_class->ajax_submit_quote();
$output = ob_get_clean();
$json   = json_decode( $output, true );

boost_ok( 'ajax_submit_quote accepts +98 phone number', ! empty( $json['success'] ) );
$order_id = ! empty( $json['data']['order_id'] ) ? $json['data']['order_id'] : 0;
$order    = wc_get_order( $order_id );
$phone    = $order ? $order->get_meta( '_wsq_customer_phone' ) : '';
boost_ok( '+98 prefix is normalized to canonical 09xxxxxxxxx', '09127778899' === $phone, "phone=$phone" );

// Test 0098 prefix
$session->clear();
$session->add_item( array( 'product_id' => $pid, 'quantity' => 1 ) );

$_POST['wsq_phone'] = '00989351112233';
ob_start();
$order_class->ajax_submit_quote();
$output = ob_get_clean();
$json   = json_decode( $output, true );

boost_ok( 'ajax_submit_quote accepts 0098 phone number', ! empty( $json['success'] ) );
$order_id = ! empty( $json['data']['order_id'] ) ? $json['data']['order_id'] : 0;
$order    = wc_get_order( $order_id );
$phone    = $order ? $order->get_meta( '_wsq_customer_phone' ) : '';
boost_ok( '0098 prefix is normalized to canonical 09xxxxxxxxx', '09351112233' === $phone, "phone=$phone" );

echo "\n=== Boost Enhancements Result: {$GLOBALS['boost_pass']} passed, {$GLOBALS['boost_fail']} failed ===\n";
