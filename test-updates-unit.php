<?php
// Offline inbound-webhook regressions; php -n test-updates-unit.php
if ( PHP_SAPI !== 'cli' || defined( 'ABSPATH' ) ) {
	exit( 'Run standalone from PHP CLI.' );
}
define( 'ABSPATH', __DIR__ . '/' );
error_reporting( E_ALL );
set_error_handler( function ( $severity, $message, $file, $line ) {
	throw new ErrorException( $message, 0, $severity, $file, $line );
} );

class WP_REST_Response {
	public $data;
	public $status;
	public function __construct( $data, $status = 200 ) { $this->data = $data; $this->status = $status; }
}
class Updates_Request {
	private $params;
	private $json;
	public function __construct( $params = array(), $json = null ) { $this->params = $params; $this->json = $json; }
	public function get_param( $key ) { return isset( $this->params[ $key ] ) ? $this->params[ $key ] : null; }
	public function get_json_params() { return $this->json; }
}
class WSQ_Logger {
	public static function info( $scope, $message, $context = array() ) {}
	public static function warning( $scope, $message, $context = array() ) {}
}
class WSQ_Bale_API {
	public static $updates = array();
	public function __construct( $token = '' ) {}
	public function get_updates( $offset = 0, $timeout = 0 ) { return array( 'ok' => true, 'result' => self::$updates ); }
	public function set_webhook( $url ) { return array( 'ok' => true ); }
	public function delete_webhook() { return array( 'ok' => true ); }
}
class WSQ_Eitaa_API extends WSQ_Bale_API {}
function __( $text, $domain = '' ) { return $text; }
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {}
function register_rest_route( $namespace, $route, $args ) { $GLOBALS['routes'][ $namespace . $route ] = $args; }
function rest_url( $path = '' ) { return 'https://example.invalid/wp-json/' . ltrim( $path, '/' ); }
function add_query_arg( $key, $value, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $key . '=' . $value; }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['options'] ) ? $GLOBALS['options'][ $key ] : $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][ $key ] = $value; $GLOBALS['writes'][] = $key; return true; }
function delete_option( $key ) { unset( $GLOBALS['options'][ $key ] ); }
function trailingslashit( $value ) { return rtrim( $value, '/' ) . '/'; }
function wp_generate_password( $length, $special = true, $extra = false ) { return str_repeat( 'a', $length ); }

require_once __DIR__ . '/woo-smart-quote/includes/class-wsq-updates.php';

class Test_Updates extends WSQ_Updates {
	public $processed = array();
	public function process_updates( $messenger, $updates ) {
		$this->processed[] = array( $messenger, $updates );
		return count( $updates );
	}
}

function updates_assert( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}
function updates_instance() {
	return ( new ReflectionClass( 'Test_Updates' ) )->newInstanceWithoutConstructor();
}
function updates_reset() {
	$GLOBALS['options'] = array( 'wsq_bale_token' => 'token', 'wsq_eitaa_token' => 'token', 'wsq_polling_enabled' => 'yes' );
	$GLOBALS['writes']  = array();
	$GLOBALS['routes']  = array();
	WSQ_Bale_API::$updates = array();
}

$tests = array();
$tests['advertised webhook URL is actually registered'] = function () {
	updates_reset();
	$updates = updates_instance();
	$updates->register_routes();
	$url   = $updates->webhook_url();
	$path  = substr( $url, strpos( $url, '/wp-json/' ) + 9 );
	$path  = substr( $path, 0, strpos( $path, '?' ) );
	updates_assert( false !== strpos( $url, '/wp-json/woo-smart-quote/v1/bale-hook' ), 'Advertised URL lacks the v1 namespace' );
	updates_assert( isset( $GLOBALS['routes'][ $path ] ), 'Advertised path is not a registered route' );
	updates_assert( isset( $GLOBALS['routes']['woo-smart-quote/bale-hook'] ), 'Legacy registered webhook URL stopped working' );
};
$tests['webhook rejects a missing or wrong secret'] = function () {
	updates_reset();
	$updates = updates_instance();
	$secret  = $updates->webhook_secret();
	$response = $updates->rest_webhook( new Updates_Request( array( 's' => 'wrong' ), array( 'update_id' => 1 ) ) );
	updates_assert( 403 === $response->status, 'Wrong secret accepted' );
	$response = $updates->rest_webhook( new Updates_Request( array( 's' => $secret ), array( 'update_id' => 1, 'message' => array( 'chat' => array( 'id' => 1, 'type' => 'private' ), 'from' => array( 'id' => 1 ), 'text' => '/start' ) ) ) );
	updates_assert( 200 === $response->status && true === $response->data['ok'], 'Valid secret rejected' );
};
$tests['webhook ignores replayed update ids'] = function () {
	updates_reset();
	$updates = updates_instance();
	$secret  = $updates->webhook_secret();
	$payload = array(
		'update_id' => 42,
		'message'   => array( 'chat' => array( 'id' => 1, 'type' => 'private' ), 'from' => array( 'id' => 1 ), 'text' => 'سلام' ),
	);

	$first = $updates->rest_webhook( new Updates_Request( array( 's' => $secret ), $payload ) );
	updates_assert( 200 === $first->status && empty( $first->data['duplicate'] ), 'First delivery was treated as a duplicate' );
	updates_assert( 1 === count( $updates->processed ), 'First delivery was not processed' );

	$second = $updates->rest_webhook( new Updates_Request( array( 's' => $secret ), $payload ) );
	updates_assert( 200 === $second->status && ! empty( $second->data['duplicate'] ), 'Replay was not answered as a duplicate' );
	updates_assert( 1 === count( $updates->processed ), 'Replayed delivery was processed twice' );

	$third = $updates->rest_webhook( new Updates_Request( array( 's' => $secret ), array_merge( $payload, array( 'update_id' => 43 ) ) ) );
	updates_assert( 200 === $third->status && empty( $third->data['duplicate'] ), 'Newer update was dropped' );
	updates_assert( 2 === count( $updates->processed ), 'Newer update was not processed' );
};
$tests['polling advances past an update id equal to the stored offset'] = function () {
	updates_reset();
	$GLOBALS['options']['wsq_bale_offset'] = 5;
	WSQ_Bale_API::$updates = array( array( 'update_id' => 5, 'message' => array( 'chat' => array( 'id' => 1, 'type' => 'private' ), 'from' => array( 'id' => 1 ), 'text' => 'hi' ) ) );
	$updates = updates_instance();
	updates_assert( 1 === $updates->poll_once( 'bale' ), 'Update was not processed' );
	updates_assert( 6 === get_option( 'wsq_bale_offset' ), 'Offset did not advance past the repeated id' );
};
$tests['polling does not invent an offset for unidentified updates'] = function () {
	updates_reset();
	$GLOBALS['options']['wsq_bale_offset'] = 9;
	WSQ_Bale_API::$updates = array( array( 'message' => array( 'chat' => array( 'id' => 1, 'type' => 'private' ), 'from' => array( 'id' => 1 ), 'text' => 'hi' ) ) );
	$updates = updates_instance();
	$updates->poll_once( 'bale' );
	updates_assert( 9 === get_option( 'wsq_bale_offset' ), 'Offset changed without an update id' );
};
$tests['registered webhook still disables duplicate polling'] = function () {
	updates_reset();
	$GLOBALS['options']['wsq_bale_webhook_url'] = 'https://example.invalid/hook';
	WSQ_Bale_API::$updates = array( array( 'update_id' => 50 ) );
	$updates = updates_instance();
	updates_assert( 0 === $updates->poll_once( 'bale' ) && ! $updates->processed, 'Polling raced a registered webhook' );
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
printf( "%d/%d webhook tests passed\n", $passed, count( $tests ) );
exit( $passed === count( $tests ) ? 0 : 1 );
