<?php
// Offline provider-response regressions; php -n test-provider-unit.php
if ( PHP_SAPI !== 'cli' || defined( 'ABSPATH' ) ) {
	exit( 'Run standalone from PHP CLI.' );
}
define( 'ABSPATH', __DIR__ . '/' );
error_reporting( E_ALL );
set_error_handler( function ( $severity, $message, $file, $line ) {
	throw new ErrorException( $message, 0, $severity, $file, $line );
} );

class Provider_Response {
	public $code;
	public $body;
	public function __construct( $code, $body ) { $this->code = $code; $this->body = $body; }
}
class WSQ_Logger {
	public static function debug( $scope, $message, $context = array() ) {}
	public static function info( $scope, $message, $context = array() ) {}
	public static function warning( $scope, $message, $context = array() ) {}
	public static function error( $scope, $message, $context = array() ) {}
}
function __( $text, $domain = '' ) { return $text; }
function apply_filters( $hook, $value ) { return $value; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function is_wp_error( $value ) { return false; }
function wp_remote_post( $url, $args = array() ) { $GLOBALS['requests'][] = array( $url, $args ); return $GLOBALS['response']; }
function wp_remote_get( $url, $args = array() ) { $GLOBALS['requests'][] = array( $url, $args ); return $GLOBALS['response']; }
function wp_remote_retrieve_response_code( $response ) { return $response->code; }
function wp_remote_retrieve_body( $response ) { return $response->body; }

require_once __DIR__ . '/woo-smart-quote/includes/class-wsq-messenger-api.php';
require_once __DIR__ . '/woo-smart-quote/includes/class-wsq-bale-api.php';

function provider_assert( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}
function provider_from( $code, $body ) {
	$GLOBALS['response'] = new Provider_Response( $code, $body );
	return new WSQ_Bale_API( 'token' );
}

$tests = array();
$tests['JSON ok response succeeds'] = function () {
	provider_assert( true === provider_from( 200, '{"ok":true,"result":{}}' )->set_webhook( 'https://example.invalid/hook' )['ok'], 'Valid JSON response rejected' );
};
$tests['plain true response succeeds'] = function () {
	provider_assert( true === provider_from( 200, ' TRUE ' )->set_webhook( 'https://example.invalid/hook' )['ok'], 'Plain true response rejected' );
};
$tests['HTTP 200 with ok=false fails'] = function () {
	$result = provider_from( 200, '{"ok":false,"description":"token invalid"}' )->set_webhook( 'https://example.invalid/hook' );
	provider_assert( false === $result['ok'] && false !== strpos( $result['description'], 'token invalid' ), 'Provider error body counted as success' );
};
$tests['HTTP 200 with empty or unreadable body fails'] = function () {
	provider_assert( false === provider_from( 200, '' )->delete_webhook()['ok'], 'Empty body counted as success' );
	provider_assert( false === provider_from( 200, '<html>maintenance</html>' )->delete_webhook()['ok'], 'Unreadable body counted as success' );
};
$tests['HTTP 500 with ok=true fails'] = function () {
	$result = provider_from( 500, '{"ok":true}' )->set_webhook( 'https://example.invalid/hook' );
	provider_assert( false === $result['ok'] && 500 === $result['http_code'], 'Server error counted as success' );
};
$tests['unreachable provider fails with a description'] = function () {
	$GLOBALS['response'] = new Provider_Response( 0, '' );
	$result = provider_from( 200, '' )->set_webhook( 'https://example.invalid/hook' );
	provider_assert( false === $result['ok'] && '' !== (string) $result['description'], 'Failure has no description' );
};

$passed = 0;
foreach ( $tests as $name => $test ) {
	try {
		$test();
		$passed++;
	} catch ( Throwable $error ) {
		fwrite( STDERR, 'FAIL: ' . $name . ' — ' . $error->getMessage() . "\n" );
	}
}
printf( "%d/%d provider tests passed\n", $passed, count( $tests ) );
exit( $passed === count( $tests ) ? 0 : 1 );
