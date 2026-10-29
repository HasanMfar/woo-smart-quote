<?php
// Offline bootstrap failure-path check; no WordPress, license bypass, database, or network.
if ( PHP_SAPI !== 'cli' || defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run standalone from PHP CLI.\n" );
	exit( 1 );
}
if ( extension_loaded( 'ionCube Loader' ) ) {
	fwrite( STDERR, "This regression requires PHP without ionCube.\n" );
	exit( 1 );
}
define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['bootstrap_actions'] = array();
$GLOBALS['bootstrap_activation'] = null;
$GLOBALS['bootstrap_deactivation'] = null;
$GLOBALS['bootstrap_cleared'] = array();
function add_action( $hook, $callback ) { $GLOBALS['bootstrap_actions'][ $hook ] = $callback; }
function register_activation_hook( $file, $callback ) { $GLOBALS['bootstrap_activation'] = $callback; }
function register_deactivation_hook( $file, $callback ) { $GLOBALS['bootstrap_deactivation'] = $callback; }
function wp_clear_scheduled_hook( $hook ) { $GLOBALS['bootstrap_cleared'][] = $hook; }
function __( $text, $domain = '' ) { return $text; }
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function wp_die( $message, $title = '', $args = array() ) { throw new RuntimeException( $message ); }
function bootstrap_check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}

try {
	require __DIR__ . '/woo-smart-quote/woo-smart-quote.php';
	bootstrap_check( ! class_exists( 'WSQ_Plugin', false ), 'Unavailable runtime initialized plugin features.' );
	bootstrap_check( isset( $GLOBALS['bootstrap_actions']['admin_notices'], $GLOBALS['bootstrap_actions']['network_admin_notices'] ), 'Missing runtime notices.' );
	ob_start();
	call_user_func( $GLOBALS['bootstrap_actions']['admin_notices'] );
	$notice = ob_get_clean();
	bootstrap_check( false !== strpos( $notice, 'ionCube Loader' ), 'Notice does not identify missing ionCube.' );
	foreach ( array( 'gd', 'mbstring' ) as $extension ) {
		bootstrap_check( extension_loaded( $extension ) || false !== strpos( $notice, $extension ), 'Notice omits missing extension ' . $extension );
	}
	bootstrap_check( is_callable( $GLOBALS['bootstrap_activation'] ), 'Missing activation guard.' );
	$activation_blocked = false;
	try {
		call_user_func( $GLOBALS['bootstrap_activation'] );
	} catch ( RuntimeException $error ) {
		$activation_blocked = false !== strpos( $error->getMessage(), 'ionCube Loader' );
	}
	bootstrap_check( $activation_blocked, 'Activation did not reject unavailable runtime.' );
	bootstrap_check( is_callable( $GLOBALS['bootstrap_deactivation'] ), 'Missing deactivation hook.' );
	call_user_func( $GLOBALS['bootstrap_deactivation'] );
	bootstrap_check(
		array( 'wsq_daily_cleanup', 'wsq_poll_updates' ) === $GLOBALS['bootstrap_cleared'],
		'Deactivation did not clear both plugin cron events.'
	);
	$entry = file_get_contents( __DIR__ . '/woo-smart-quote/woo-smart-quote.php' );
	$readme = file_get_contents( __DIR__ . '/woo-smart-quote/readme.txt' );
	bootstrap_check( preg_match( '/Requires PHP:\s*8\.0\b/', $entry ) && preg_match( '/Requires PHP:\s*8\.0\b/', $readme ), 'Runtime headers disagree with bundled dependencies.' );
	foreach ( get_included_files() as $file ) {
		bootstrap_check( 'ProFeature.php' !== basename( $file ) && 0 !== strpos( basename( $file ), 'RTL_License_' ), 'Encoded file loaded despite missing loader.' );
	}
	fwrite( STDOUT, "PASS: missing prerequisites return safely, display notices, and reject activation.\n" );
} catch ( Throwable $error ) {
	fwrite( STDERR, 'FAIL: ' . $error->getMessage() . "\n" );
	exit( 1 );
}
