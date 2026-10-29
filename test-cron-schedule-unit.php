<?php
/**
 * Offline cron lifecycle regressions: php -n test-cron-schedule-unit.php
 * Verifies the daily cleanup reschedules itself from the event state rather
 * than a stored option flag that can outlive the event.
 */
if ( PHP_SAPI !== 'cli' || defined( 'ABSPATH' ) ) {
	exit( 'Run standalone from PHP CLI, without WordPress.' );
}
define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );
error_reporting( E_ALL );
set_error_handler( function ( $severity, $message, $file, $line ) {
	throw new ErrorException( $message, 0, $severity, $file, $line );
} );

$GLOBALS['cron_state']  = array();
$GLOBALS['cron_calls']  = array();
$GLOBALS['cron_option'] = array();

function wp_next_scheduled( $hook ) {
	return isset( $GLOBALS['cron_state'][ $hook ] ) ? $GLOBALS['cron_state'][ $hook ] : false;
}
function wp_schedule_event( $timestamp, $recurrence, $hook ) {
	$GLOBALS['cron_calls'][]           = array( $hook, $recurrence );
	$GLOBALS['cron_state'][ $hook ]    = $timestamp;
	return true;
}
function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['cron_option'] ) ? $GLOBALS['cron_option'][ $name ] : $default;
}
function update_option( $name, $value ) {
	$GLOBALS['cron_option'][ $name ] = $value;
	return true;
}

require __DIR__ . '/woo-smart-quote/includes/class-wsq-plugin.php';

$passed = 0;
$failed = 0;
function check_cron( $label, $condition, $extra = '' ) {
	global $passed, $failed;
	if ( $condition ) {
		++$passed;
		echo 'PASS: ' . $label . ( '' !== $extra ? ' | ' . $extra : '' ) . PHP_EOL;
		return;
	}
	++$failed;
	echo 'FAIL: ' . $label . ( '' !== $extra ? ' | ' . $extra : '' ) . PHP_EOL;
}

$plugin = ( new ReflectionClass( 'WSQ_Plugin' ) )->newInstanceWithoutConstructor();

// A legacy install can still carry the option while the event is gone.
$GLOBALS['cron_option']['wsq_cleanup_scheduled'] = 1;
$plugin->schedule_cleanup();
check_cron(
	'stale option does not block scheduling',
	1 === count( $GLOBALS['cron_calls'] ) && 'wsq_daily_cleanup' === $GLOBALS['cron_calls'][0][0]
);

$plugin->schedule_cleanup();
check_cron( 'existing event is not duplicated', 1 === count( $GLOBALS['cron_calls'] ) );
check_cron(
	'schedule uses the daily recurrence',
	'daily' === $GLOBALS['cron_calls'][0][1]
);

// Simulate an external cron reset (host migration, Crontrol, restore).
unset( $GLOBALS['cron_state']['wsq_daily_cleanup'] );
$plugin->schedule_cleanup();
check_cron( 'lost event is restored on the next request', 2 === count( $GLOBALS['cron_calls'] ) );

echo 'RESULT: ' . $passed . ' passed, ' . $failed . ' failed; offline doubles, not live WP-Cron.' . PHP_EOL;
exit( $failed ? 1 : 0 );
