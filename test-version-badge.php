<?php
/**
 * Version alignment check: the running plugin, the file header, WSQ_VERSION and
 * the rendered admin badge must all report the same release number.
 *
 * Run: wp eval-file test-version-badge.php --path=<site>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['wsq_ver_pass'] = 0;
$GLOBALS['wsq_ver_fail'] = 0;
function okv( $label, $cond, $extra = '' ) {
	if ( $cond ) {
		$GLOBALS['wsq_ver_pass']++;
		echo "PASS: $label" . ( '' !== $extra ? " | $extra" : '' ) . "\n";
	} else {
		$GLOBALS['wsq_ver_fail']++;
		echo "FAIL: $label" . ( '' !== $extra ? " | $extra" : '' ) . "\n";
	}
}

require_once ABSPATH . 'wp-admin/includes/template.php';
require_once WP_PLUGIN_DIR . '/woo-smart-quote/includes/class-wsq-admin.php';

$expected = '1.0.0';

$header = get_file_data( WP_PLUGIN_DIR . '/woo-smart-quote/woo-smart-quote.php', array( 'Version' => 'Version' ) );
okv( 'plugin file header reports the release', $header['Version'] === $expected, 'header=' . $header['Version'] );

okv( 'WSQ_VERSION constant matches', defined( 'WSQ_VERSION' ) && WSQ_VERSION === $expected, 'const=' . ( defined( 'WSQ_VERSION' ) ? WSQ_VERSION : 'undefined' ) );

$readme = file_get_contents( WP_PLUGIN_DIR . '/woo-smart-quote/readme.txt' );
preg_match( '/^Stable tag:\s*(\S+)/m', $readme, $m );
okv( 'readme Stable tag matches', isset( $m[1] ) && $m[1] === $expected, 'stable=' . ( $m[1] ?? 'absent' ) );

$user_id = (int) get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0];
wp_set_current_user( $user_id );

$_GET = array( 'page' => 'woo-smart-quote', 'tab' => 'dashboard' );
ob_start();
WSQ_Admin::instance()->settings_page_html();
$html = ob_get_clean();

okv( 'admin page rendered', strlen( $html ) > 2000, 'len=' . strlen( $html ) );
preg_match( '/wsq-version-badge[^>]*>(.*?)<\/span>/s', $html, $m );
$badge = isset( $m[1] ) ? trim( wp_strip_all_tags( $m[1] ) ) : '';
okv( 'version badge is present', '' !== $badge, 'badge=' . $badge );
okv( 'version badge shows the release number', false !== strpos( $badge, $expected ) );

// Asset cache-busting must use the same number, otherwise browsers keep stale CSS/JS.
// The hook does not fire under WP-CLI, so call the plugin's own callback
// directly (firing admin_enqueue_scripts would boot unrelated admin screens).
WSQ_Admin::instance()->enqueue_scripts( 'toplevel_page_woo-smart-quote' );
$scripts = wp_scripts();
$found   = 0;
foreach ( $scripts->registered as $handle => $dep ) {
	if ( 0 === strpos( $handle, 'woo-smart-quote' ) && false !== strpos( (string) $dep->src, 'assets/' ) ) {
		$found++;
		okv( "asset version for $handle", (string) $dep->ver === $expected, 'ver=' . $dep->ver );
	}
}
okv( 'plugin assets are enqueued', $found > 0, 'handles=' . $found );

echo "\n=== Version alignment Result: {$GLOBALS['wsq_ver_pass']} passed, {$GLOBALS['wsq_ver_fail']} failed ===\n";
