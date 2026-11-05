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
	bootstrap_check( false === strpos( $notice, 'ionCube Loader' ), 'Notice should not require ionCube Loader.' );
	$found_missing = false;
	foreach ( array( 'gd', 'mbstring' ) as $extension ) {
		if ( ! extension_loaded( $extension ) ) {
			bootstrap_check( false !== strpos( $notice, $extension ), 'Notice omits missing extension ' . $extension );
			$found_missing = true;
		}
	}
	bootstrap_check( $found_missing, 'Expected at least one missing extension under php -n' );
	bootstrap_check( is_callable( $GLOBALS['bootstrap_activation'] ), 'Missing activation guard.' );
	$activation_blocked = false;
	try {
		call_user_func( $GLOBALS['bootstrap_activation'] );
	} catch ( RuntimeException $error ) {
		$activation_blocked = ( false !== strpos( $error->getMessage(), 'gd' ) || false !== strpos( $error->getMessage(), 'mbstring' ) );
	}
	bootstrap_check( $activation_blocked, 'Activation did not reject unavailable runtime (missing gd/mbstring).' );
	bootstrap_check( is_callable( $GLOBALS['bootstrap_deactivation'] ), 'Missing deactivation hook.' );
	call_user_func( $GLOBALS['bootstrap_deactivation'] );
	bootstrap_check(
		array( 'wsq_daily_cleanup', 'wsq_poll_updates' ) === $GLOBALS['bootstrap_cleared'],
		'Deactivation did not clear both plugin cron events.'
	);
	$entry = file_get_contents( __DIR__ . '/woo-smart-quote/woo-smart-quote.php' );
	$readme = file_get_contents( __DIR__ . '/woo-smart-quote/readme.txt' );
	bootstrap_check( preg_match( '/Requires PHP:\s*8\.0\b/', $entry ) && preg_match( '/Requires PHP:\s*8\.0\b/', $readme ), 'Runtime headers disagree with bundled dependencies.' );
	bootstrap_check( false === strpos( $entry, 'RTL_License_' ) && false === strpos( $entry, 'ProFeature' ), 'Entrypoint contains dead license loader references.' );
	foreach ( get_included_files() as $file ) {
		bootstrap_check( 'ProFeature.php' !== basename( $file ) && 0 !== strpos( basename( $file ), 'RTL_License_' ), 'Encoded file loaded despite missing loader.' );
	}

	// Verify standard PHP activates cleanly without ionCube Loader
	$ini = file_exists( __DIR__ . '/php-test.ini' ) ? __DIR__ . '/php-test.ini' : ( file_exists( __DIR__ . '/wp-cli.ini' ) ? __DIR__ . '/wp-cli.ini' : '' );
	if ( $ini ) {
		$code_str = '<?php '
			. 'if ( extension_loaded( "ionCube Loader" ) ) { exit( 2 ); } '
			. 'define( "ABSPATH", ' . var_export( __DIR__ . '/', true ) . ' ); '
			. '$GLOBALS["actions"] = array(); '
			. 'function add_action( $h, $c, $p = 10 ) { $GLOBALS["actions"][$h][] = $c; } '
			. 'function register_activation_hook( $f, $c ) {} '
			. 'function register_deactivation_hook( $f, $c ) {} '
			. 'function wp_clear_scheduled_hook( $h ) {} '
			. 'function __( $t, $d = "" ) { return $t; } '
			. 'function esc_html( $v ) { return $v; } '
			. 'function plugin_dir_path( $f ) { return dirname( $f ) . "/"; } '
			. 'function plugin_dir_url( $f ) { return "http://example.com/plugins/"; } '
			. 'require ' . var_export( __DIR__ . '/woo-smart-quote/woo-smart-quote.php', true ) . '; '
			. 'if ( empty( $GLOBALS["actions"]["plugins_loaded"] ) ) { exit( 3 ); } '
			. 'if ( ! empty( $GLOBALS["actions"]["admin_notices"] ) ) { exit( 4 ); } '
			. 'exit( 0 );';
		$clean_test_file = sys_get_temp_dir() . '/wsq_clean_test_' . getmypid() . '.php';
		file_put_contents( $clean_test_file, $code_str );
		$sub_cmd = 'php -c ' . escapeshellarg( $ini ) . ' ' . escapeshellarg( $clean_test_file ) . ' 2>&1';
		exec( $sub_cmd, $sub_out, $sub_ret );
		@unlink( $clean_test_file );
		$detail = ! empty( $sub_out ) ? ' (' . implode( "\n", $sub_out ) . ')' : '';
		bootstrap_check( 0 === $sub_ret, "Standard PHP without ionCube failed clean bootstrap (exit code: $sub_ret)$detail." );
	} else {
		bootstrap_check( false, 'Missing php-test.ini or wp-cli.ini for clean bootstrap test.' );
	}

	fwrite( STDOUT, "PASS: missing prerequisites return safely, display notices, and standard PHP activates without ionCube.\n" );
} catch ( Throwable $error ) {
	fwrite( STDERR, 'FAIL: ' . $error->getMessage() . "\n" );
	exit( 1 );
}
