<?php
// Offline unit test for Admin APK download functionality
// Run via: php -n test-admin-apk-download-unit.php
if ( PHP_SAPI !== 'cli' || defined( 'ABSPATH' ) ) {
	exit( 'Run standalone from PHP CLI.' );
}
define( 'ABSPATH', __DIR__ . '/' );
define( 'WSQ_PLUGIN_URL', 'http://example.com/wp-content/plugins/woo-smart-quote/' );
define( 'WSQ_PLUGIN_DIR', __DIR__ . '/woo-smart-quote/' );
define( 'WSQ_VERSION', '1.0.0' );

error_reporting( E_ALL );
set_error_handler( function ( $severity, $message, $file, $line ) {
	if ( $severity === E_DEPRECATED || $severity === E_USER_DEPRECATED ) {
		return;
	}
	throw new ErrorException( $message, 0, $severity, $file, $line );
} );

// Mock WordPress functions & state
$GLOBALS['options']   = array();
$GLOBALS['filters']   = array();
$GLOBALS['current_user_can_manage'] = true;
$GLOBALS['json_response'] = null;

function __( $text, $domain = '' ) { return $text; }
function esc_html__( $text, $domain = '' ) { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr__( $text, $domain = '' ) { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function esc_html_e( $text, $domain = '' ) { echo esc_html__( $text, $domain ); }
function esc_attr_e( $text, $domain = '' ) { echo esc_attr__( $text, $domain ); }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $url ) { return filter_var( $url, FILTER_SANITIZE_URL ); }
function esc_url_raw( $url ) { return filter_var( $url, FILTER_SANITIZE_URL ); }
function wp_parse_url( $url ) { return parse_url( $url ); }
function sanitize_text_field( $str ) { return trim( strip_tags( (string) $str ) ); }
function wp_unslash( $val ) { return $val; }
function current_user_can( $cap ) { return ! empty( $GLOBALS['current_user_can_manage'] ); }
function check_ajax_referer( $action, $query_arg = false, $die = true ) { return true; }

function wp_send_json_success( $data = null ) {
	$GLOBALS['json_response'] = array( 'success' => true, 'data' => $data );
	throw new RuntimeException( 'wp_send_json_success' );
}
function wp_send_json_error( $data = null ) {
	$GLOBALS['json_response'] = array( 'success' => false, 'data' => $data );
	throw new RuntimeException( 'wp_send_json_error' );
}

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['options'] ) ? $GLOBALS['options'][ $key ] : $default;
}
function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['options'][ $key ] = $value;
	return true;
}
function delete_option( $key ) {
	unset( $GLOBALS['options'][ $key ] );
	return true;
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['filters'][ $hook ][] = array( 'callback' => $callback, 'accepted_args' => $accepted_args );
}
function apply_filters( $hook, $value, ...$args ) {
	if ( ! empty( $GLOBALS['filters'][ $hook ] ) ) {
		foreach ( $GLOBALS['filters'][ $hook ] as $f ) {
			$params = array_merge( array( $value ), $args );
			$value  = call_user_func_array( $f['callback'], array_slice( $params, 0, $f['accepted_args'] ) );
		}
	}
	return $value;
}
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}
function register_setting( $group, $option_name, $args = array() ) {}
function delete_transient( $transient ) {}

// Mock WSQ dependencies
class WSQ_REST_API {
	public static function get_or_create_pairing_session() {
		return array(
			'pairing_code' => '998877',
			'expires_at'   => time() + 600,
			'payload_json' => '{"endpoint":"https://example.com","token":"test"}',
		);
	}
	public static function get_paired_devices() {
		return array();
	}
	public static function revoke_device( $id ) {
		return 'dev_valid' === $id;
	}
}

class WSQ_PDF_Generator {
	private static $instance = null;
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	public function format_jalali_date( $ts ) {
		return '۱۴۰۳/۰۸/۰۹';
	}
}

require_once __DIR__ . '/woo-smart-quote/includes/class-wsq-icons.php';
require_once __DIR__ . '/woo-smart-quote/includes/class-wsq-admin.php';

// Assert helper
$pass_count = 0;
function assert_test( $condition, $msg ) {
	global $pass_count;
	if ( ! $condition ) {
		echo "FAIL: $msg\n";
		exit( 1 );
	}
	$pass_count++;
	echo "PASS: $msg\n";
}

echo "=== Admin APK Download Unit Test Suite ===\n";

// 1. Icons test
$svg_download = WSQ_Icons::get( 'download' );
assert_test( ! empty( $svg_download ) && false !== strpos( $svg_download, '<path' ), 'WSQ_Icons returns valid download icon SVG' );

$svg_android = WSQ_Icons::get( 'android' );
assert_test( ! empty( $svg_android ) && false !== strpos( $svg_android, '<path' ), 'WSQ_Icons returns valid android icon SVG' );

// 2. Default APK download URLs
$default_url = WSQ_Admin::get_apk_download_url();
assert_test( 'https://github.com/HasanMfar/nerkhnegar-companion-releases/releases/latest/download/app-release.apk' === $default_url, 'Default universal APK URL matches official public GitHub releases URL' );

$arm64_url = WSQ_Admin::get_apk_download_url( 'arm64' );
assert_test( 'https://github.com/HasanMfar/nerkhnegar-companion-releases/releases/latest/download/app-arm64-v8a-release.apk' === $arm64_url, 'Default ARM64 APK URL matches official arm64 asset URL' );

$armeabi_url = WSQ_Admin::get_apk_download_url( 'armeabi' );
assert_test( 'https://github.com/HasanMfar/nerkhnegar-companion-releases/releases/latest/download/app-armeabi-v7a-release.apk' === $armeabi_url, 'Default 32-bit APK URL matches official armeabi-v7a asset URL' );

// 3. Filter test
add_filter( 'wsq_companion_apk_download_url', function( $url, $variant ) {
	return 'https://custom-cdn.example.com/' . $variant . '.apk';
}, 10, 2 );

$filtered_url = WSQ_Admin::get_apk_download_url( 'universal' );
assert_test( 'https://custom-cdn.example.com/universal.apk' === $filtered_url, 'Filter wsq_companion_apk_download_url successfully overrides URL' );

// Clear filter
$GLOBALS['filters']['wsq_companion_apk_download_url'] = array();

// 4. Custom option override
update_option( 'wsq_companion_apk_url', 'https://mysite.com/uploads/my-companion.apk' );
$opt_url = WSQ_Admin::get_apk_download_url();
assert_test( 'https://mysite.com/uploads/my-companion.apk' === $opt_url, 'Option wsq_companion_apk_url overrides default GitHub URL' );

// Delete option to restore default
delete_option( 'wsq_companion_apk_url' );
assert_test( 'https://github.com/HasanMfar/nerkhnegar-companion-releases/releases/latest/download/app-release.apk' === WSQ_Admin::get_apk_download_url(), 'Deleting option restores default GitHub URL' );

// 5. Releases page URL
$releases_page = WSQ_Admin::get_releases_page_url();
assert_test( 'https://github.com/HasanMfar/nerkhnegar-companion-releases/releases/latest' === $releases_page, 'Default releases page points to GitHub releases' );

add_filter( 'wsq_companion_releases_page_url', function( $url ) {
	return 'https://example.com/app/releases';
} );
assert_test( 'https://example.com/app/releases' === WSQ_Admin::get_releases_page_url(), 'Filter wsq_companion_releases_page_url overrides releases page URL' );
$GLOBALS['filters']['wsq_companion_releases_page_url'] = array();

// 6. Test App Tab HTML rendering
$admin = WSQ_Admin::instance();
$rm    = new ReflectionMethod( $admin, 'render_app_tab' );

ob_start();
$rm->invoke( $admin );
$html = ob_get_clean();

assert_test( ! empty( $html ), 'render_app_tab produces non-empty HTML' );
assert_test( false !== strpos( $html, 'wsq-apk-download-btn' ), 'HTML contains APK download button class' );
assert_test( false !== strpos( $html, 'id="wsq-apk-primary-link"' ), 'HTML contains primary download link ID' );
assert_test( false !== strpos( $html, 'دانلود مستقیم فایل APK (اندروید)' ), 'HTML contains download button Persian label' );
assert_test( false !== strpos( $html, 'id="wsq-apk-qrcode"' ), 'HTML contains APK QR code container' );
assert_test( false !== strpos( $html, 'id="wsq-apk-qr-container"' ), 'HTML contains APK QR code collapsible container' );
assert_test( false !== strpos( $html, 'wsq-toggle-apk-qr-btn' ), 'HTML contains APK QR toggle button' );
assert_test( false !== strpos( $html, 'wsq-toggle-custom-apk-btn' ), 'HTML contains custom APK URL toggle button' );
assert_test( false !== strpos( $html, 'id="wsq_companion_apk_url_input"' ), 'HTML contains custom APK input field' );
assert_test( false !== strpos( $html, 'ARM64-v8a' ), 'HTML contains ARM64 version option' );
assert_test( false !== strpos( $html, 'انتشارهای گیت‌هاب (Releases)' ), 'HTML contains GitHub releases link' );

// 7. AJAX save custom APK URL
// A: Unauthorized test
$GLOBALS['current_user_can_manage'] = false;
$_POST['apk_url'] = 'https://site.com/app.apk';
try {
	$admin->ajax_save_apk_url();
} catch ( RuntimeException $e ) {}
assert_test( false === $GLOBALS['json_response']['success'], 'ajax_save_apk_url rejects unauthorized user' );

// B: Authorized valid URL
$GLOBALS['current_user_can_manage'] = true;
$_POST['apk_url'] = 'https://myshop.com/downloads/nerkhnegar-app.apk';
try {
	$admin->ajax_save_apk_url();
} catch ( RuntimeException $e ) {}
assert_test( true === $GLOBALS['json_response']['success'], 'ajax_save_apk_url succeeds for valid URL' );
assert_test( 'https://myshop.com/downloads/nerkhnegar-app.apk' === get_option( 'wsq_companion_apk_url' ), 'ajax_save_apk_url saves URL in option' );

// B2: Authorized invalid URLs are rejected
$invalid_urls = array( 'javascript:alert(1)', 'ftp://example.com/app.apk', 'invalid://test' );
foreach ( $invalid_urls as $inv ) {
	$_POST['apk_url'] = $inv;
	try {
		$admin->ajax_save_apk_url();
	} catch ( RuntimeException $e ) {}
	assert_test( false === $GLOBALS['json_response']['success'], "ajax_save_apk_url rejects invalid URL: $inv" );
}

// B3: ARM64 URL remains pointing to GitHub ARM64 release when custom universal option is set
assert_test( 'https://github.com/HasanMfar/nerkhnegar-companion-releases/releases/latest/download/app-arm64-v8a-release.apk' === WSQ_Admin::get_apk_download_url( 'arm64' ), 'ARM64 variant remains distinct when custom universal option is set' );

// C: Empty URL resets to default
$_POST['apk_url'] = '';
try {
	$admin->ajax_save_apk_url();
} catch ( RuntimeException $e ) {}
assert_test( true === $GLOBALS['json_response']['success'], 'ajax_save_apk_url succeeds for empty URL reset' );
assert_test( false === get_option( 'wsq_companion_apk_url' ), 'ajax_save_apk_url deletes option on empty URL' );
assert_test( false !== strpos( $GLOBALS['json_response']['data']['apk_url'], 'github.com' ), 'Response contains default GitHub URL after reset' );

// 8. Assert native CSS classes and no hardcoded Tailwind colors in rendered HTML
assert_test( false !== strpos( $html, 'wsq-app-grid' ), 'HTML contains wsq-app-grid class' );
assert_test( false !== strpos( $html, 'wsq-app-step-num' ), 'HTML contains wsq-app-step-num class' );
assert_test( false !== strpos( $html, 'wsq-app-action-row' ), 'HTML contains wsq-app-action-row class' );
assert_test( false === strpos( $html, '#64748b' ), 'HTML is clean of legacy hex color #64748b' );
assert_test( false === strpos( $html, '#1e293b' ), 'HTML is clean of legacy hex color #1e293b' );
assert_test( false === strpos( $html, '#4338ca' ), 'HTML is clean of legacy hex color #4338ca' );

echo "\nALL {$pass_count} TESTS IN ADMIN APK DOWNLOAD UNIT SUITE PASSED!\n";
exit( 0 );
