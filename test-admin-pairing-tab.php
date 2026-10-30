<?php
/**
 * Test Suite for Admin App Pairing Tab & Actions
 *
 * Run: powershell -ExecutionPolicy Bypass -File .\wp.ps1 eval-file test-admin-pairing-tab.php
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

echo "=== Admin App Pairing Tab Test Suite ===\n";

$admin_user = get_user_by( 'login', 'admin' );
if ( ! $admin_user ) {
	$users = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
	$admin_user = ! empty( $users ) ? $users[0] : null;
}
$admin_id = $admin_user ? $admin_user->ID : 1;
wp_set_current_user( $admin_id );

if ( ! defined( 'WSQ_VERSION' ) ) {
	define( 'WSQ_VERSION', '1.0.0' );
}
if ( ! defined( 'WSQ_PLUGIN_URL' ) ) {
	define( 'WSQ_PLUGIN_URL', plugins_url( '/woo-smart-quote/', WP_PLUGIN_DIR ) );
}
if ( ! defined( 'WSQ_PLUGIN_DIR' ) ) {
	define( 'WSQ_PLUGIN_DIR', WP_PLUGIN_DIR . '/woo-smart-quote/' );
}

if ( ! class_exists( 'WSQ_Plugin' ) ) {
	require_once WP_PLUGIN_DIR . '/woo-smart-quote/includes/class-wsq-plugin.php';
	WSQ_Plugin::instance();
}
if ( ! class_exists( 'WSQ_Admin' ) ) {
	require_once WP_PLUGIN_DIR . '/woo-smart-quote/includes/class-wsq-admin.php';
}
$admin = WSQ_Admin::instance();
ok( 'WSQ_Admin instance available', ! empty( $admin ) );

// Simulate GET request to the 'app' tab
$_GET['page'] = 'woo-smart-quote';
$_GET['tab']  = 'app';

ob_start();
$admin->settings_page_html();
$html = ob_get_clean();

ok( 'Settings page rendered for tab=app', ! empty( $html ) );
ok( 'HTML contains QR code container', false !== strpos( $html, 'id="wsq-pairing-qrcode"' ) );
ok( 'HTML contains pairing code display', false !== strpos( $html, 'id="wsq-pairing-code-text"' ) );
ok( 'HTML contains countdown timer box', false !== strpos( $html, 'id="wsq-pairing-timer-box"' ) );
ok( 'HTML contains refresh button', false !== strpos( $html, 'wsq-refresh-pairing-btn' ) );
ok( 'HTML contains setup guide steps', false !== strpos( $html, 'میز کار قیمت‌گذاری سیار' ) );
ok( 'HTML contains APK download button in Step 1', false !== strpos( $html, 'wsq-apk-download-btn' ) );
ok( 'HTML contains APK download direct link ID', false !== strpos( $html, 'id="wsq-apk-primary-link"' ) );
ok( 'HTML contains APK QR code container', false !== strpos( $html, 'id="wsq-apk-qrcode"' ) );
ok( 'HTML contains paired devices section', false !== strpos( $html, 'دستگاه‌های متصل فعال' ) );

// 2. Test AJAX refresh pairing
if ( ! defined( 'DOING_AJAX' ) ) {
	define( 'DOING_AJAX', true );
}
$_REQUEST['nonce'] = $_POST['nonce'] = wp_create_nonce( 'wsq_admin_test_nonce' );

// Mock wp_die to catch wp_send_json responses via exception
add_filter( 'wp_die_ajax_handler', function() {
	return function( $message ) {
		throw new \RuntimeException( (string) $message );
	};
} );

// Call ajax_refresh_pairing
ob_start();
try {
	$admin->ajax_refresh_pairing();
} catch ( \Throwable $e ) {
}
$ajax_refresh_out = ob_get_clean();

$refresh_json = json_decode( $ajax_refresh_out, true );
ok( 'AJAX wsq_refresh_pairing returns valid JSON', is_array( $refresh_json ) );
ok( 'AJAX wsq_refresh_pairing success is true', true === ( $refresh_json['success'] ?? false ) );
ok( 'AJAX response contains new pairing code', ! empty( $refresh_json['data']['pairing_code'] ?? '' ) );

// 3. Test Device Revocation AJAX
// Seed a device first
$devices = get_option( WSQ_REST_API::OPTION_DEVICES, array() );
$devices['dev_test_revoke_99'] = array(
	'device_id'   => 'dev_test_revoke_99',
	'device_name' => 'Tablet Admin',
	'token_hash'  => hash( 'sha256', 'dummy_token' ),
	'user_id'     => $admin_id,
	'paired_at'   => time(),
	'last_active' => time(),
);
update_option( WSQ_REST_API::OPTION_DEVICES, $devices );

$_REQUEST['device_id'] = $_POST['device_id'] = 'dev_test_revoke_99';
ob_start();
try {
	$admin->ajax_revoke_device();
} catch ( \Throwable $e ) {
}
$ajax_revoke_out = ob_get_clean();

$revoke_json = json_decode( $ajax_revoke_out, true );
ok( 'AJAX wsq_revoke_device returns valid JSON', is_array( $revoke_json ) );
ok( 'AJAX wsq_revoke_device success is true', true === ( $revoke_json['success'] ?? false ) );

$devices_after = get_option( WSQ_REST_API::OPTION_DEVICES, array() );
ok( 'Device successfully removed from options', ! isset( $devices_after['dev_test_revoke_99'] ) );

echo "\n=== Tab Test Results: {$GLOBALS['wsq_pass']} passed, {$GLOBALS['wsq_fail']} failed ===\n";
