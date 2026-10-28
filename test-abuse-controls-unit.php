<?php
// Offline abuse-control regressions; php -n test-abuse-controls-unit.php
if ( PHP_SAPI !== 'cli' || defined( 'ABSPATH' ) ) {
	exit( 'Run standalone from PHP CLI.' );
}
define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
error_reporting( E_ALL );
set_error_handler( function ( $severity, $message, $file, $line ) {
	throw new ErrorException( $message, 0, $severity, $file, $line );
} );

function __( $text, $domain = '' ) { return $text; }
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {}
function absint( $value ) { return abs( (int) $value ); }
function wp_unslash( $value ) { return $value; }
function sanitize_text_field( $value ) { return trim( (string) $value ); }
function sanitize_textarea_field( $value ) { return trim( (string) $value ); }
function sanitize_email( $value ) { return (string) $value; }
function is_email( $value ) { return false !== filter_var( $value, FILTER_VALIDATE_EMAIL ) ? $value : false; }
function check_ajax_referer( $action = -1, $query_arg = false, $stop = true ) { return 1; }
function get_current_user_id() { return 0; }
function is_user_logged_in() { return false; }
function wc_create_order( $args = array() ) {
	$GLOBALS['order_create_calls']++;
	return $GLOBALS['order_create_result'];
}

$GLOBALS['transients'] = array();
function get_transient( $key ) {
	return array_key_exists( $key, $GLOBALS['transients'] ) ? $GLOBALS['transients'][ $key ] : false;
}
function set_transient( $key, $value, $ttl = 0 ) {
	$GLOBALS['transients'][ $key ] = $value;
	return true;
}
function delete_transient( $key ) {
	unset( $GLOBALS['transients'][ $key ] );
	return true;
}

$GLOBALS['options'] = array();
function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['options'] ) ? $GLOBALS['options'][ $key ] : $default;
}
function update_option( $key, $value ) {
	$GLOBALS['options'][ $key ] = $value;
	return true;
}

class WP_Error {
	private $code;
	private $message;
	private $data;
	public function __construct( $code = '', $message = '', $data = '' ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}
function is_wp_error( $thing ) { return $thing instanceof WP_Error; }

class Json_Exit extends Exception {
	public $payload;
	public $status;
	public function __construct( $payload, $status ) {
		parent::__construct( 'json response' );
		$this->payload = $payload;
		$this->status  = $status;
	}
}
function wp_send_json_error( $payload = null, $status = null ) { throw new Json_Exit( $payload, $status ); }
function wp_send_json_success( $payload = null, $status = null ) { throw new Json_Exit( $payload, $status ); }

function rest_ensure_response( $response ) { return $response; }
function get_bloginfo( $key = '' ) { return 'فروشگاه آزمون'; }
function home_url( $path = '' ) { return 'https://example.test/' . ltrim( (string) $path, '/' ); }
function get_woocommerce_currency() { return 'IRR'; }
function get_woocommerce_currency_symbol() { return 'ریال'; }
function wp_generate_password( $length = 12, $special = true, $extra_special = false ) { return str_repeat( 'x', $length ); }

class Pair_User {
	public $ID = 7;
	public $display_name = 'مدیر فروشگاه';
	public function exists() { return true; }
}
function get_userdata( $id ) { return isset( $GLOBALS['pair_user'] ) ? $GLOBALS['pair_user'] : false; }
function user_can( $user, $cap ) { return true; }

class Pair_Request {
	private $params;
	public function __construct( $params ) { $this->params = $params; }
	public function get_json_params() { return $this->params; }
}

class Fake_Session {
	private $items;
	public function __construct( $items ) { $this->items = $items; }
	public function get_items() { return $this->items; }
	public function clear() {}
}
class WSQ_Session {
	public static $items = array();
	public static function instance() { return new Fake_Session( self::$items ); }
}
class WSQ_Buyer_Notifier {
	public static function instance() { return new self(); }
	public function normalize_mobile( $phone ) { return (string) $phone; }
}

require_once __DIR__ . '/woo-smart-quote/includes/class-wsq-order.php';
require_once __DIR__ . '/woo-smart-quote/includes/class-wsq-rest-api.php';

function abuse_assert( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}
function call_private( $object, $method, $args = array() ) {
	$ref = new ReflectionMethod( $object, $method );
	return $ref->invokeArgs( $object, $args );
}
function reset_transients() { $GLOBALS['transients'] = array(); }
function throttle_keys() {
	return array_values( array_filter( array_keys( $GLOBALS['transients'] ), function ( $key ) {
		return 0 === strpos( $key, 'wsq_quote_throttle_' );
	} ) );
}
function failed_pairing( $api, $code ) {
	return $api->rest_pair_device( new Pair_Request( array( 'pairing_code' => $code ) ) );
}
function error_status( $error ) {
	abuse_assert( $error instanceof WP_Error, 'Expected WP_Error' );
	$data = $error->get_error_data();
	return is_array( $data ) && isset( $data['status'] ) ? $data['status'] : 0;
}

$GLOBALS['pair_user'] = new Pair_User();
$order                = WSQ_Order::instance();
$api                  = WSQ_REST_API::instance();

$tests = array();

$tests['quote throttle allows five submissions then answers 429'] = function () use ( $order ) {
	reset_transients();
	$_SERVER['REMOTE_ADDR'] = '10.0.0.1';
	for ( $i = 0; $i < 5; $i++ ) {
		abuse_assert( true === call_private( $order, 'throttle_quote_submission' ), 'Attempt ' . ( $i + 1 ) . ' rejected early' );
	}
	$blocked = call_private( $order, 'throttle_quote_submission' );
	abuse_assert( is_wp_error( $blocked ), 'Sixth attempt was not throttled' );
	abuse_assert( 429 === error_status( $blocked ), 'Throttle did not return 429' );
};

$tests['quote throttle is scoped per client IP'] = function () use ( $order ) {
	reset_transients();
	$_SERVER['REMOTE_ADDR'] = '10.0.0.1';
	for ( $i = 0; $i < 6; $i++ ) {
		call_private( $order, 'throttle_quote_submission' );
	}
	$_SERVER['REMOTE_ADDR'] = '10.0.0.2';
	abuse_assert( true === call_private( $order, 'throttle_quote_submission' ), 'Second client inherited the first client quota' );
};

$tests['quote throttle ignores spoofed forwarding headers'] = function () use ( $order ) {
	reset_transients();
	$_SERVER['REMOTE_ADDR']       = '10.2.0.1';
	$_SERVER['HTTP_X_FORWARDED_FOR'] = '192.0.2.10';
	for ( $i = 0; $i < 6; $i++ ) {
		call_private( $order, 'throttle_quote_submission' );
	}
	$_SERVER['HTTP_X_FORWARDED_FOR'] = '192.0.2.11';
	abuse_assert( is_wp_error( call_private( $order, 'throttle_quote_submission' ) ), 'Forwarding header reset the quota' );
	unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
};

$tests['invalid client addresses share a single bucket'] = function () use ( $order ) {
	reset_transients();
	$_SERVER['REMOTE_ADDR'] = 'not-an-ip';
	abuse_assert( '0.0.0.0' === call_private( $order, 'client_ip' ), 'Invalid address was trusted' );
	for ( $i = 0; $i < 6; $i++ ) {
		call_private( $order, 'throttle_quote_submission' );
	}
	$_SERVER['REMOTE_ADDR'] = 'also-not-an-ip';
	abuse_assert( is_wp_error( call_private( $order, 'throttle_quote_submission' ) ), 'Invalid addresses got separate quotas' );
};

$tests['invalid form input never consumes the quote quota'] = function () use ( $order ) {
	reset_transients();
	$_SERVER['REMOTE_ADDR'] = '10.0.0.3';
	$_POST = array(
		'wsq_name'        => '',
		'wsq_email'       => 'buyer@example.test',
		'wsq_phone'       => '09121234567',
		'wsq_submit_nonce' => 'nonce',
	);
	try {
		$order->ajax_submit_quote();
		throw new RuntimeException( 'Handler accepted a missing name' );
	} catch ( Json_Exit $exit ) {
		abuse_assert( null === $exit->status || 0 === (int) $exit->status, 'Validation failure used an HTTP error status' );
	}
	abuse_assert( array() === throttle_keys(), 'Validation failure consumed quota' );
};

$tests['quote submission stops at the throttle before order creation'] = function () use ( $order ) {
	reset_transients();
	$_SERVER['REMOTE_ADDR']   = '10.0.0.4';
	$_POST                    = array(
		'wsq_name'         => 'علی رضایی',
		'wsq_email'        => 'buyer@example.test',
		'wsq_phone'        => '۰۹۱۲۱۲۳۴۵۶۷',
		'wsq_channel'      => 'bale',
		'wsq_message'      => 'سلام',
		'wsq_submit_nonce' => 'nonce',
	);
	WSQ_Session::$items        = array( array( 'product_id' => 10, 'variation_id' => 0, 'quantity' => 1 ) );
	$GLOBALS['order_create_result'] = new WP_Error( 'boom', 'create failed' );
	$GLOBALS['order_create_calls']  = 0;

	for ( $i = 0; $i < 5; $i++ ) {
		try {
			$order->ajax_submit_quote();
			throw new RuntimeException( 'Handler returned without responding' );
		} catch ( Json_Exit $exit ) {
			abuse_assert( null === $exit->status || 0 === (int) $exit->status, 'Allowed attempt answered with HTTP error' );
		}
	}
	abuse_assert( 5 === $GLOBALS['order_create_calls'], 'Allowed attempts did not reach order creation' );

	try {
		$order->ajax_submit_quote();
		throw new RuntimeException( 'Sixth submission was accepted' );
	} catch ( Json_Exit $exit ) {
		abuse_assert( 429 === $exit->status, 'Sixth submission did not answer 429' );
	}
	abuse_assert( 5 === $GLOBALS['order_create_calls'], 'Throttled submission still created an order' );
};

$tests['pairing rejects the sixth failed attempt from one client'] = function () use ( $api ) {
	reset_transients();
	$_SERVER['REMOTE_ADDR'] = '10.3.0.1';
	set_transient( WSQ_REST_API::TRANSIENT_PAIR, array(
		'code'       => '1234-5678',
		'user_id'    => 7,
		'expires_at' => time() + 600,
	), 600 );

	for ( $i = 0; $i < 5; $i++ ) {
		$error = failed_pairing( $api, '0000-0000' );
		abuse_assert( 403 === error_status( $error ), 'Wrong code was not rejected with 403' );
	}
	abuse_assert( 429 === error_status( failed_pairing( $api, '0000-0000' ) ), 'Sixth guess was not locked out' );
	abuse_assert( 429 === error_status( failed_pairing( $api, '1234-5678' ) ), 'Lockout let the correct code through' );
};

$tests['pairing burns the code after distributed misses'] = function () use ( $api ) {
	reset_transients();
	set_transient( WSQ_REST_API::TRANSIENT_PAIR, array(
		'code'       => '4321-8765',
		'user_id'    => 7,
		'expires_at' => time() + 600,
	), 600 );

	for ( $i = 0; $i < 10; $i++ ) {
		$_SERVER['REMOTE_ADDR'] = '10.4.0.' . ( $i + 1 );
		failed_pairing( $api, '0000-0000' );
	}
	abuse_assert( false === get_transient( WSQ_REST_API::TRANSIENT_PAIR ), 'Code survived ten distributed misses' );

	$_SERVER['REMOTE_ADDR'] = '10.4.1.1';
	abuse_assert( 403 === error_status( failed_pairing( $api, '4321-8765' ) ), 'Burned code still paired a device' );
};

$tests['pairing success issues a token and clears failures'] = function () use ( $api ) {
	reset_transients();
	$GLOBALS['options']     = array();
	$_SERVER['REMOTE_ADDR'] = '10.5.0.1';
	set_transient( WSQ_REST_API::TRANSIENT_PAIR, array(
		'code'       => '1234-5678',
		'user_id'    => 7,
		'expires_at' => time() + 600,
	), 600 );
	failed_pairing( $api, '0000-0000' );
	abuse_assert( 1 === (int) get_transient( 'wsq_pair_fail_' . md5( '10.5.0.1' ) ), 'Failure was not counted' );

	$response = $api->rest_pair_device( new Pair_Request( array(
		'pairing_code' => '۱۲۳۴-۵۶۷۸',
		'device_name'  => 'گوشی مدیر',
		'device_id'    => 'dev_test',
	) ) );

	abuse_assert( is_array( $response ) && true === $response['success'], 'Pairing did not succeed' );
	abuse_assert( 0 === strpos( $response['token'], 'wsq_sec_' ), 'Issued token has the wrong shape' );
	abuse_assert( 'dev_test' === $response['device_id'], 'Device id was not preserved' );

	$devices = get_option( WSQ_REST_API::OPTION_DEVICES, array() );
	abuse_assert( isset( $devices['dev_test'] ), 'Device was not stored' );
	abuse_assert( hash( 'sha256', $response['token'] ) === $devices['dev_test']['token_hash'], 'Stored hash does not match the token' );
	abuse_assert( 7 === $devices['dev_test']['user_id'], 'Device was attached to the wrong user' );
	abuse_assert( false === get_transient( 'wsq_pair_fail_' . md5( '10.5.0.1' ) ), 'Success did not clear failures' );
	abuse_assert( false === get_transient( WSQ_REST_API::TRANSIENT_PAIR ), 'Pairing code was not consumed' );
};

$tests['pairing refuses an expired session'] = function () use ( $api ) {
	reset_transients();
	$_SERVER['REMOTE_ADDR'] = '10.6.0.1';
	set_transient( WSQ_REST_API::TRANSIENT_PAIR, array(
		'code'       => '9999-8888',
		'user_id'    => 7,
		'expires_at' => time() - 1,
	), 600 );

	abuse_assert( 403 === error_status( failed_pairing( $api, '9999-8888' ) ), 'Expired session paired a device' );
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

printf( "%d/%d abuse-control tests passed\n", $passed, count( $tests ) );
exit( $passed === count( $tests ) ? 0 : 1 );
