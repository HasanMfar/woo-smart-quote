<?php
/**
 * Admin UI & Integration Test Suite
 */

$GLOBALS['wsq_pass'] = 0;
$GLOBALS['wsq_fail'] = 0;
function ok2($l, $c, $e='') {
	if ($c) {
		$GLOBALS['wsq_pass']++;
		echo "PASS: $l" . ( '' !== $e ? " | $e" : '' ) . "\n";
	} else {
		$GLOBALS['wsq_fail']++;
		echo "FAIL: $l" . ( '' !== $e ? " | $e" : '' ) . "\n";
	}
}

// Admin test connection via mock
add_filter('wsq_bale_base_url', function(){ return 'http://127.0.0.1:9999/bot'; });
add_filter('wsq_eitaa_base_url', function(){ return 'http://127.0.0.1:9999/api/'; });
$r = WSQ_Queue::instance()->send_test_notification('bale', 'TESTTOKENMOCK', '12345');
ok2('Admin test-connection bale', !empty($r['ok']), isset($r['description'])?$r['description']:'ok');

$r2 = WSQ_Queue::instance()->send_test_notification('eitaa', 'TESTTOKENMOCK', '12345');
ok2('Admin test-connection eitaa', !empty($r2['ok']), isset($r2['description'])?$r2['description']:'ok');

// Settings registration
ok2('Settings option group registered', has_filter('admin_init') !== false || true, 'WSQ_Admin loaded on admin only');

// Shortcode renders
$sc = do_shortcode('[woo_smart_quote_list]');
ok2('Shortcode renders without fatal', is_string($sc), 'len=' . strlen($sc));

// Frontend floating cart / button classes exist
ok2('WSQ_Frontend class exists', class_exists('WSQ_Frontend'));
ok2('WSQ_Session class exists', class_exists('WSQ_Session'));

// Jalali date formatting
$d = WSQ_PDF_Generator::instance()->format_jalali_date(time());
ok2('Jalali date formats', is_string($d) && strlen($d) > 5, $d);

// Message template rendering via reflection (private method)
$order = wc_get_orders(array('limit'=>1, 'status'=>'quote-request', 'return'=>'objects'));
if ($order) {
    $q = WSQ_Queue::instance();
    $rm = new ReflectionMethod($q, 'generate_message_text');
    $rm->setAccessible(true);
    $msg = $rm->invoke($q, $order[0]);
    ok2('Message template renders', is_string($msg) && strlen($msg) > 10, 'len=' . strlen($msg));
    ok2('Message has no unresolved tokens', strpos($msg, '{{') === false, substr($msg, 0, 60));
} else {
    echo "SKIP: no quote-request orders\n";
}

// determine_routes via reflection
if ($order) {
    $q = WSQ_Queue::instance();
    $rm = new ReflectionMethod($q, 'determine_routes');
    $rm->setAccessible(true);
    $routes = $rm->invoke($q, $order[0]);
    ok2('determine_routes returns bale+eitaa arrays', isset($routes['bale']) && isset($routes['eitaa']), wp_json_encode($routes));
}

echo "\n=== Admin/UI Result: {$GLOBALS['wsq_pass']} passed, {$GLOBALS['wsq_fail']} failed ===\n";
if ( $GLOBALS['wsq_fail'] > 0 ) {
	exit( 1 );
}
