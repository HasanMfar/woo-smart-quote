<?php
// Offline document-delivery regressions; php -n test-delivery-unit.php
if ( PHP_SAPI !== 'cli' || defined( 'ABSPATH' ) ) {
	exit( 'Run standalone from PHP CLI.' );
}
define( 'ABSPATH', __DIR__ . '/' );
error_reporting( E_ALL );
set_error_handler( function ( $severity, $message, $file, $line ) {
	throw new ErrorException( $message, 0, $severity, $file, $line );
} );

class Delivery_Response {
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
function wp_generate_password( $length = 12, $special = true, $extra = false ) { return str_repeat( 'b', $length ); }
function wp_remote_post( $url, $args = array() ) {
	$GLOBALS['requests'][] = array( 'url' => $url, 'args' => $args );
	$method = false !== strpos( $url, '/sendDocument' ) ? 'document' : 'message';
	return isset( $GLOBALS['responses'][ $method ] ) ? $GLOBALS['responses'][ $method ] : new Delivery_Response( 200, '{"ok":true}' );
}
function wp_remote_get( $url, $args = array() ) { return new Delivery_Response( 200, '{"ok":true}' ); }
function wp_remote_retrieve_response_code( $response ) { return $response->code; }
function wp_remote_retrieve_body( $response ) { return $response->body; }

require_once __DIR__ . '/woo-smart-quote/includes/class-wsq-messenger-api.php';
require_once __DIR__ . '/woo-smart-quote/includes/class-wsq-bale-api.php';

function delivery_assert( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}
function delivery_reset( $document = null, $message = null ) {
	$GLOBALS['requests'] = array();
	$GLOBALS['responses'] = array(
		'document' => null !== $document ? $document : new Delivery_Response( 200, '{"ok":true}' ),
		'message'  => null !== $message ? $message : new Delivery_Response( 200, '{"ok":true}' ),
	);
	return new WSQ_Bale_API( 'token' );
}
function delivery_methods() {
	return array_map( function ( $request ) {
		return false !== strpos( $request['url'], '/sendDocument' ) ? 'document' : 'message';
	}, $GLOBALS['requests'] );
}

$GLOBALS['fixture'] = tempnam( sys_get_temp_dir(), 'wsq-delivery-' );
file_put_contents( $GLOBALS['fixture'], '%PDF-1.4 fixture' );

$tests = array();

$tests['missing PDF falls back to a text alert'] = function () {
	$result = delivery_reset()->send_document( '42', false, 'Quote caption' );
	delivery_assert( true === $result['ok'], 'Text fallback not counted as delivered' );
	delivery_assert( ! empty( $result['degraded'] ), 'Text fallback not marked degraded' );
	delivery_assert( array( 'message' ) === delivery_methods(), 'Missing PDF still attempted a document upload' );
	$text = $GLOBALS['requests'][0]['args']['body']['text'];
	delivery_assert( false !== strpos( $text, 'Quote caption' ), 'Fallback text lost the alert caption' );
	delivery_assert( false !== strpos( $text, 'PDF' ), 'Fallback text lacks the attachment note' );
};

$tests['nonexistent path falls back to a text alert'] = function () {
	$result = delivery_reset()->send_document( '42', sys_get_temp_dir() . '/wsq-does-not-exist.pdf', 'Quote' );
	delivery_assert( true === $result['ok'] && ! empty( $result['degraded'] ), 'Missing file did not degrade' );
	delivery_assert( array( 'message' ) === delivery_methods(), 'Missing file still attempted a document upload' );
};

$tests['present PDF uploads without a text fallback'] = function () {
	$result = delivery_reset()->send_document( '42', $GLOBALS['fixture'], 'Quote' );
	delivery_assert( true === $result['ok'], 'Successful upload reported a failure' );
	delivery_assert( empty( $result['degraded'] ), 'Successful upload marked degraded' );
	delivery_assert( array( 'document' ) === delivery_methods(), 'Successful upload also sent text' );
};

$tests['rejected upload still delivers the text alert'] = function () {
	$result = delivery_reset( new Delivery_Response( 500, '{"ok":false,"description":"boom"}' ) )->send_document( '42', $GLOBALS['fixture'], 'Quote' );
	delivery_assert( true === $result['ok'] && ! empty( $result['degraded'] ), 'Upload failure did not degrade' );
	delivery_assert( array( 'document', 'message' ) === delivery_methods(), 'Upload failure did not retry as text' );
};

$tests['undeliverable text keeps the failure'] = function () {
	$failure = new Delivery_Response( 500, '{"ok":false,"description":"down"}' );
	$result = delivery_reset( null, $failure )->send_document( '42', false, 'Quote' );
	delivery_assert( false === $result['ok'], 'Failed text alert reported as delivered' );
	delivery_assert( '' !== (string) $result['description'], 'Failure lost its description' );
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

unlink( $GLOBALS['fixture'] );
printf( "%d/%d delivery tests passed\n", $passed, count( $tests ) );
exit( $passed === count( $tests ) ? 0 : 1 );
