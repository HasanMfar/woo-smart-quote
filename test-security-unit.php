<?php
/**
 * Offline security regressions: php -n test-security-unit.php
 * Uses real WSQ classes with in-memory WP/WC and transport doubles, not live integration.
 */
if ( PHP_SAPI !== 'cli' || defined( 'ABSPATH' ) ) {
	exit( 'Run standalone from PHP CLI, without WordPress.' );
}
define( 'ABSPATH', __DIR__ . '/' );
define( 'MONTH_IN_SECONDS', 30 * 86400 );
define( 'MINUTE_IN_SECONDS', 60 );
error_reporting( E_ALL );
set_error_handler( function ( $severity, $message, $file, $line ) {
	throw new ErrorException( $message, 0, $severity, $file, $line );
} );

class WP_Error {
	private $data;
	public function __construct( $code, $message, $data ) { $this->data = $data; }
	public function get_error_data() { return $this->data; }
}
class WP_REST_Server {
	const READABLE = 'GET';
	const CREATABLE = 'POST';
}
class Security_Request {
	private $headers;
	private $params;
	public function __construct( $headers = array(), $params = array() ) {
		$this->headers = $headers;
		$this->params = $params;
	}
	public function get_header( $key ) { return isset( $this->headers[ $key ] ) ? $this->headers[ $key ] : ''; }
	public function get_param( $key ) { return isset( $this->params[ $key ] ) ? $this->params[ $key ] : null; }
	public function get_json_params() { return $this->params; }
}
class Security_User {
	public $ID;
	public $display_name = 'Staff';
	public $caps;
	public function __construct( $id, $caps ) { $this->ID = $id; $this->caps = $caps; }
	public function exists() { return $this->ID > 0; }
}
class WC_Order {
	private $id;
	private $status;
	private $phone;
	private $meta;
	public function __construct( $id, $status = 'quote-request', $phone = '09121234567', $meta = array() ) {
		$this->id = $id; $this->status = $status; $this->phone = $phone; $this->meta = $meta;
	}
	public function get_id() { return $this->id; }
	public function get_order_number() { return $this->get_meta( '_order_number' ) ?: (string) $this->id; }
	public function get_status() { return $this->status; }
	public function get_billing_phone() { return $this->phone; }
	public function get_meta( $key ) { return isset( $this->meta[ $key ] ) ? $this->meta[ $key ] : ''; }
	public function get_total() { return 123456; }
	public function get_billing_first_name() { return 'PrivateBuyer'; }
	public function get_billing_last_name() { return 'Name'; }
	public function get_date_created() { return null; }
	public function get_items() { return array(); }
	public function get_checkout_payment_url() { return 'https://example.invalid/pay/' . $this->id; }
}
class WSQ_Bale_API {
	public function __construct( $token ) {}
	public function send_message( $chat_id, $text, $extra = array() ) {
		$GLOBALS['messages'][] = array( $chat_id, $text, $extra );
		return array( 'ok' => true );
	}
	public function send_document( $chat_id, $path, $caption ) {
		$GLOBALS['documents'][] = array( $chat_id, $path, $caption );
		return array( 'ok' => true );
	}
}
class WSQ_Eitaa_API extends WSQ_Bale_API {}
class WSQ_PDF_Generator {
	public static function instance() { return new self(); }
	public function generate( $id ) { $GLOBALS['pdfs'][] = $id; return '/in-memory/quote.pdf'; }
}
class WSQ_Logger {
	public static function info( $scope, $message, $context = array() ) {}
}
function __( $text, $domain = '' ) { return $text; }
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}
function add_filter( $hook, $callback ) {}
function register_rest_route( $namespace, $route, $args ) { $GLOBALS['routes'][ $route ] = $args; }
function get_option( $key, $default = false ) { return isset( $GLOBALS['options'][ $key ] ) ? $GLOBALS['options'][ $key ] : $default; }
function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['writes'][] = $key;
	$GLOBALS['options'][ $key ] = $value;
	return true;
}
function get_transient( $key ) { return isset( $GLOBALS['transients'][ $key ] ) ? $GLOBALS['transients'][ $key ] : false; }
function set_transient( $key, $value, $ttl = 0 ) { $GLOBALS['transients'][ $key ] = $value; return true; }
function delete_transient( $key ) { unset( $GLOBALS['transients'][ $key ] ); }
function get_users( $args ) { return array(); }
function get_userdata( $id ) { return isset( $GLOBALS['users'][ $id ] ) ? $GLOBALS['users'][ $id ] : false; }
function wp_set_current_user( $id ) { $GLOBALS['current_user'] = $id; return get_userdata( $id ); }
function get_current_user_id() { return $GLOBALS['current_user']; }
function user_can( $user, $cap, $id = null ) {
	if ( ! is_object( $user ) ) { $user = get_userdata( $user ); }
	if ( ! $user ) { return false; }
	$key = 'edit_shop_order' === $cap ? $cap . ':' . $id : $cap;
	return ! empty( $user->caps[ $key ] );
}
function current_user_can( $cap, $id = null ) { return user_can( get_current_user_id(), $cap, $id ); }
function sanitize_text_field( $value ) { return trim( (string) $value ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_generate_password( $length, $special = true, $extra = false ) { return str_repeat( 'x', $length ); }
function rest_ensure_response( $data ) { return $data; }
function get_bloginfo( $key ) { return 'Offline store'; }
function home_url() { return 'https://example.invalid'; }
function admin_url( $path ) { return home_url() . '/wp-admin/' . $path; }
function get_woocommerce_currency() { return 'IRR'; }
function get_woocommerce_currency_symbol() { return 'IRR'; }
function wc_price( $price ) { return (string) $price; }
function wp_strip_all_tags( $text ) { return strip_tags( $text ); }
function date_i18n( $format, $timestamp ) { return gmdate( $format, $timestamp ); }
function number_format_i18n( $number ) { return (string) $number; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
if ( ! function_exists( 'mb_substr' ) ) {
	function mb_substr( $text, $start, $length ) { return substr( $text, $start, $length ); }
}
function wc_get_order( $id ) {
	$GLOBALS['lookups'][] = $id;
	return isset( $GLOBALS['orders'][ $id ] ) ? $GLOBALS['orders'][ $id ] : false;
}
function wc_get_orders( $args ) {
	$GLOBALS['queries'][] = $args;
	$orders = array_filter( $GLOBALS['orders'], function ( $order ) use ( $args ) {
		if ( isset( $args['billing_phone'] ) && $args['billing_phone'] !== $order->get_billing_phone() ) { return false; }
		if ( isset( $args['status'] ) && ! in_array( $order->get_status(), (array) $args['status'], true ) ) { return false; }
		if ( isset( $args['meta_key'] ) ) {
			$value = '_billing_phone' === $args['meta_key'] ? $order->get_billing_phone() : $order->get_meta( $args['meta_key'] );
			if ( $value !== $args['meta_value'] ) { return false; }
		}
		return true;
	} );
	$orders = array_values( $orders );
	if ( isset( $args['limit'] ) && $args['limit'] > 0 ) { $orders = array_slice( $orders, 0, $args['limit'] ); }
	return isset( $args['return'] ) && 'ids' === $args['return'] ? array_map( function ( $order ) { return $order->get_id(); }, $orders ) : $orders;
}

require_once __DIR__ . '/woo-smart-quote/includes/class-wsq-rest-api.php';
require_once __DIR__ . '/woo-smart-quote/includes/class-wsq-updates.php';
require_once __DIR__ . '/woo-smart-quote/includes/class-wsq-bot.php';

function security_reset() {
	$GLOBALS['options'] = array( 'wsq_buyer_bot_enabled' => 'yes', 'wsq_bale_token' => 'offline', 'wsq_eitaa_token' => 'offline' );
	foreach ( array( 'transients', 'writes', 'messages', 'documents', 'pdfs', 'lookups', 'queries', 'routes' ) as $key ) { $GLOBALS[ $key ] = array(); }
	$GLOBALS['current_user'] = 0;
	$GLOBALS['users'] = array(
		1 => new Security_User( 1, array( 'edit_shop_orders' => true, 'edit_shop_order:101' => true ) ),
		2 => new Security_User( 2, array() ),
		3 => new Security_User( 3, array( 'manage_woocommerce' => true, 'edit_shop_order:101' => true ) ),
		9 => new Security_User( 9, array( 'manage_woocommerce' => true, 'edit_shop_order:101' => true, 'edit_shop_order:102' => true ) ),
	);
	$GLOBALS['orders'] = array( 101 => new WC_Order( 101, 'quote-sent' ) );
}
function security_assert( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}
function security_error( $result, $status ) {
	security_assert( $result instanceof WP_Error && $result->get_error_data()['status'] === $status, 'Expected WP_Error status ' . $status );
}
function security_call( $method, $args ) {
	$reflection = new ReflectionMethod( 'WSQ_Bot', $method );
	if ( PHP_VERSION_ID < 80100 ) { $reflection->setAccessible( true ); }
	return $reflection->invokeArgs( WSQ_Bot::instance(), $args );
}
function security_update( $changes = array() ) {
	return array_replace( array( 'chat_id' => '100', 'from_id' => '100', 'chat_type' => 'private', 'from_name' => 'Buyer', 'text' => '', 'contact' => null ), $changes );
}
function security_link( $messenger = 'bale', $chat = '100', $phone = '09121234567' ) {
	return WSQ_Updates::instance()->link_chat( $messenger, $chat, $phone, $chat );
}
function security_device( $user_id ) {
	$GLOBALS['options'][ WSQ_REST_API::OPTION_DEVICES ] = array( 'device' => array( 'token_hash' => hash( 'sha256', 'valid-token' ), 'user_id' => $user_id, 'last_active' => 1 ) );
}
function security_no_order_access() {
	security_assert( ! $GLOBALS['lookups'] && ! $GLOBALS['queries'] && ! $GLOBALS['pdfs'] && ! $GLOBALS['documents'], 'Order lookup or PDF access occurred' );
}
$tests = array();
$tests['normalization does not assume private chat'] = function () {
	$update = WSQ_Updates::normalize_update( array( 'message' => array( 'chat' => array( 'id' => 100 ), 'from' => array( 'id' => 100 ) ) ), 'bale' );
	security_assert( '' === $update['chat_type'], 'Missing chat type became private' );
};
$tests['normalization preserves sender and contact identities'] = function () {
	$update = WSQ_Updates::normalize_update( array( 'message' => array( 'chat' => array( 'id' => -100, 'type' => 'group' ), 'from' => array( 'id' => 200 ), 'contact' => array( 'user_id' => 300, 'phone_number' => '+989121234567' ) ) ), 'bale' );
	security_assert( '-100' === $update['chat_id'] && '200' === $update['from_id'] && '300' === $update['contact']['user'], 'Identity was inferred or replaced' );
};
$tests['normalization does not invent a missing chat identity'] = function () {
	security_assert( null === WSQ_Updates::normalize_update( array( 'message' => array( 'from' => array( 'id' => 100 ), 'chat' => array( 'type' => 'private' ) ) ), 'bale' ), 'Sender substituted for missing chat' );
};
$tests['callback normalization uses actual callback sender, not bot sender'] = function () {
	$update = WSQ_Updates::normalize_update( array( 'callback_query' => array( 'from' => array( 'id' => 100 ), 'data' => '/orders', 'message' => array( 'chat' => array( 'id' => 100, 'type' => 'private' ), 'from' => array( 'id' => 999 ) ) ) ), 'bale' );
	security_assert( '100' === $update['from_id'], 'Callback sender is not retained' );
};
$tests['unlinked buyer cannot look up order or generate PDF'] = function () {
	WSQ_Bot::instance()->handle( 'bale', security_update( array( 'text' => '101' ) ) );
	security_no_order_access();
};
$tests['empty buyer phone never implies administrator access'] = function () {
	security_assert( null === security_call( 'find_order_by_number', array( '101', '' ) ), 'Empty buyer phone granted access' );
	security_no_order_access();
};
$tests['unlinked list requires identity rather than number shortcut'] = function () {
	WSQ_Bot::instance()->handle( 'bale', security_update( array( 'text' => '/orders' ) ) );
	security_no_order_access();
	security_assert( false === strpos( $GLOBALS['messages'][0][1], 'مستقیم' ), 'List recommends unverified direct lookup' );
};
foreach ( array( 'bale', 'eitaa' ) as $messenger ) {
	$tests[ $messenger . ' verified self-contact links and supports lookup' ] = function () use ( $messenger ) {
		WSQ_Bot::instance()->handle( $messenger, security_update( array( 'contact' => array( 'phone' => '+989121234567', 'user' => '100' ) ) ) );
		$link = WSQ_Updates::instance()->find_link( $messenger, '100' );
		security_assert( isset( $link['messenger_user_id'] ) && '100' === $link['messenger_user_id'] && '09121234567' === $link['phone'], 'Verified identity missing' );
		WSQ_Bot::instance()->handle( $messenger, security_update( array( 'text' => '101' ) ) );
		security_assert( array( 101 ) === $GLOBALS['pdfs'] && 1 === count( $GLOBALS['documents'] ), 'Verified buyer did not receive own quote' );
		security_assert( '100' === WSQ_Updates::instance()->find_chat_by_phone( $messenger, '09121234567' ), 'Verified notification target missing' );
	};
}
$bad_contacts = array(
	'third-party' => array( 'contact' => array( 'phone' => '09351112233', 'user' => '200' ) ),
	'missing contact user' => array( 'contact' => array( 'phone' => '09351112233' ) ),
	'nonnumeric contact user' => array( 'from_id' => 'abc', 'contact' => array( 'phone' => '09351112233', 'user' => 'abc' ) ),
	'zero contact user' => array( 'from_id' => '0', 'contact' => array( 'phone' => '09351112233', 'user' => '0' ) ),
	'missing sender' => array( 'from_id' => '', 'contact' => array( 'phone' => '09351112233', 'user' => '100' ) ),
	'chat sender mismatch' => array( 'from_id' => '200', 'contact' => array( 'phone' => '09351112233', 'user' => '200' ) ),
	'group' => array( 'chat_type' => 'group' ),
	'channel' => array( 'chat_type' => 'channel' ),
	'missing chat type' => array( 'chat_type' => '' ),
);
foreach ( $bad_contacts as $label => $changes ) {
	$tests[ 'reject ' . $label . ' contact without changing link' ] = function () use ( $changes ) {
		security_link();
		$before = get_option( 'wsq_chat_links' );
		$GLOBALS['writes'] = array();
		$update = security_update( array( 'contact' => array( 'phone' => '09351112233', 'user' => '100' ) ) );
		WSQ_Bot::instance()->handle( 'bale', array_replace( $update, $changes ) );
		security_assert( $before === get_option( 'wsq_chat_links' ) && ! $GLOBALS['writes'], 'Rejected contact changed stored linkage' );
		security_no_order_access();
	};
}
$tests['disabled buyer bot cannot link contacts'] = function () {
	$GLOBALS['options']['wsq_buyer_bot_enabled'] = 'no';
	WSQ_Bot::instance()->handle( 'bale', security_update( array( 'contact' => array( 'phone' => '09121234567', 'user' => '100' ) ) ) );
	security_assert( ! get_option( 'wsq_chat_links' ) && ! $GLOBALS['writes'], 'Disabled bot accepted contact' );
};
foreach ( array( 'group', 'channel', '' ) as $type ) {
	$tests[ 'non-private buyer request cannot disclose orders: ' . $type ] = function () use ( $type ) {
		security_link();
		WSQ_Bot::instance()->handle( 'bale', security_update( array( 'text' => '101', 'chat_type' => $type ) ) );
		security_no_order_access();
	};
}
$tests['administrator commands are independent of buyer contact handling'] = function () {
	$GLOBALS['options']['wsq_bale_admin_chat_ids'] = '100';
	$GLOBALS['options']['wsq_buyer_bot_enabled'] = 'no';
	WSQ_Bot::instance()->handle( 'bale', security_update( array( 'text' => '/start', 'contact' => array( 'phone' => '09121234567', 'user' => '100' ) ) ) );
	security_assert( ! get_option( 'wsq_chat_links' ) && false !== strpos( $GLOBALS['messages'][0][1], '/pending' ), 'Admin command treated as buyer linkage' );
};
$tests['link_chat validates identity, messenger and canonical phone before writes'] = function () {
	$invalid = array(
		array( 'bale', '100', '09121234567', '' ), array( 'bale', '100', '09121234567', '200' ),
		array( 'other', '100', '09121234567', '100' ), array( 'bale', '-100', '09121234567', '-100' ),
		array( 'bale', 'abc', '09121234567', 'abc' ), array( 'bale', '0', '09121234567', '0' ),
		array( 'bale', '100', '+989121234567', '100' ), array( 'bale', '100', 'invalid', '100' ),
	);
	foreach ( $invalid as $args ) {
		security_assert( false === call_user_func_array( array( WSQ_Updates::instance(), 'link_chat' ), $args ), 'Invalid linkage not rejected' );
	}
	security_assert( ! $GLOBALS['writes'], 'Invalid linkage wrote options' );
	security_assert( true === security_link(), 'Valid linkage must return true' );
};
foreach ( array( 'legacy' => array(), 'mismatched' => array( 'messenger_user_id' => '200' ) ) as $label => $identity ) {
	$tests[ $label . ' links cannot authorize lookup or notifications' ] = function () use ( $identity ) {
		$GLOBALS['options']['wsq_chat_links'] = array( 'bale:100' => array_merge( array( 'phone' => '09121234567', 'created' => time(), 'user_id' => 1 ), $identity ) );
		$updates = WSQ_Updates::instance();
		security_assert( null === $updates->find_link( 'bale', '100' ) && '' === $updates->find_chat_by_phone( 'bale', '09121234567' ), 'Unverified link remains readable' );
		WSQ_Bot::instance()->handle( 'bale', security_update( array( 'text' => '101' ) ) );
		security_no_order_access();
	};
}
$tests['link cap prunes oldest deterministically even within one month'] = function () {
	$links = array();
	for ( $id = 501; $id >= 1; $id-- ) {
		$links[ 'bale:' . $id ] = array( 'phone' => '09121234567', 'messenger_user_id' => (string) $id, 'created' => time() - 1 );
	}
	$links['bale:400']['created'] = time() - 10;
	$GLOBALS['options']['wsq_chat_links'] = $links;
	security_link( 'bale', '999' );
	$result = get_option( 'wsq_chat_links' );
	security_assert( 500 === count( $result ) && ! isset( $result['bale:400'] ) && ! isset( $result['bale:1'] ) && isset( $result['bale:999'] ), 'Cap or oldest/tie pruning incorrect' );
};
$tests['link cap retains newly verified identity on timestamp ties'] = function () {
	$links = array();
	for ( $id = 1000; $id < 1500; $id++ ) {
		$links[ 'bale:' . $id ] = array( 'phone' => '09121234567', 'messenger_user_id' => (string) $id, 'created' => time() );
	}
	$GLOBALS['options']['wsq_chat_links'] = $links;
	security_assert( true === security_link( 'bale', '1' ), 'New verified link rejected' );
	security_assert( 500 === count( get_option( 'wsq_chat_links' ) ) && null !== WSQ_Updates::instance()->find_link( 'bale', '1' ), 'Newly verified link pruned during its own insertion' );
};
$tests['verified other buyer cannot retrieve quote'] = function () {
	security_link( 'bale', '100', '09351112233' );
	WSQ_Bot::instance()->handle( 'bale', security_update( array( 'text' => '101' ) ) );
	security_assert( ! $GLOBALS['pdfs'] && ! $GLOBALS['documents'], 'Other buyer received PDF' );
	security_assert( false === strpos( implode( ' ', array_column( $GLOBALS['messages'], 1 ) ), 'example.invalid/pay' ), 'Other buyer received payment URL' );
};
$tests['buyer lookup normalizes both phone operands'] = function () {
	$GLOBALS['orders'][101] = new WC_Order( 101, 'quote-request', '+98 912 123 4567' );
	security_assert( $GLOBALS['orders'][101] === security_call( 'find_order_by_number', array( '101', '00989121234567' ) ), 'Equivalent Iranian phones did not match' );
};
$tests['custom order-number fallback preserves buyer authorization'] = function () {
	$GLOBALS['orders'][101] = new WC_Order( 101, 'quote-request', '09121234567', array( '_order_number' => '7654321' ) );
	security_assert( $GLOBALS['orders'][101] === security_call( 'find_order_by_number', array( '7654321', '09121234567' ) ), 'Custom number lookup failed' );
	security_assert( null === security_call( 'find_order_by_number', array( '7654321', '09351112233' ) ), 'Custom number bypassed ownership' );
};
foreach ( array( 'pending', 'processing', 'completed' ) as $status ) {
	$tests[ $status . ' orders require WSQ provenance for buyers and admins' ] = function () use ( $status ) {
		$GLOBALS['orders'][101] = new WC_Order( 101, $status );
		security_assert( null === security_call( 'find_order_by_number', array( '101', '09121234567' ) ), 'Buyer saw ordinary WC order' );
		security_assert( null === security_call( 'find_order_by_number', array( '101', '', true ) ), 'Admin saw ordinary WC order through quote bot' );
		$GLOBALS['orders'][101] = new WC_Order( 101, $status, '09121234567', array( '_wsq_customer_phone' => '09121234567' ) );
		security_assert( $GLOBALS['orders'][101] === security_call( 'find_order_by_number', array( '101', '09121234567' ) ), 'Genuine WSQ order rejected' );
		security_assert( $GLOBALS['orders'][101] === security_call( 'find_order_by_number', array( '101', '', true ) ), 'Explicit administrator rejected' );
	};
}
$tests['administrator extracts full number after first space in both commands'] = function () {
	$GLOBALS['options']['wsq_bale_admin_chat_ids'] = '100';
	$GLOBALS['orders'][7654321] = new WC_Order( 7654321 );
	foreach ( array( '/order 7654321', 'سفارش 7654321' ) as $command ) {
		WSQ_Bot::instance()->handle( 'bale', security_update( array( 'text' => $command ) ) );
		$message = end( $GLOBALS['messages'] );
		security_assert( false !== strpos( $message[1], 'PrivateBuyer' ) && false !== strpos( $message[1], 'post=7654321' ), 'Administrator lookup lost order-number digits' );
	}
};
$tests['buyer list uses HPOS-safe billing_phone query and WSQ provenance'] = function () {
	$GLOBALS['orders'] = array(
		101 => new WC_Order( 101 ),
		102 => new WC_Order( 102, 'processing' ),
		103 => new WC_Order( 103, 'completed', '09121234567', array( '_wsq_customer_phone' => '09121234567' ) ),
		104 => new WC_Order( 104, 'quote-request', '09351112233' ),
		105 => new WC_Order( 105, 'cancelled' ),
	);
	$orders = security_call( 'find_buyer_orders', array( '09121234567', 5 ) );
	$query = $GLOBALS['queries'][0];
	security_assert( isset( $query['billing_phone'] ) && '09121234567' === $query['billing_phone'] && ! isset( $query['meta_key'] ), 'Billing phone query is not HPOS-safe' );
	security_assert( array( 101, 103 ) === array_map( function ( $order ) { return $order->get_id(); }, $orders ), 'List contains non-WSQ or other-buyer orders' );
};
foreach ( array( 'missing' => 0, 'deleted' => 999, 'demoted' => 2 ) as $label => $id ) {
	$tests[ $label . ' device user rejected before activity/context changes' ] = function () use ( $id ) {
		security_device( $id );
		wp_set_current_user( 9 );
		security_error( WSQ_REST_API::instance()->check_auth( new Security_Request( array( 'authorization' => 'Bearer valid-token' ) ) ), 401 );
		security_assert( 9 === get_current_user_id() && ! $GLOBALS['writes'], 'Rejected device changed context/activity' );
	};
}
foreach ( array( 1, 3 ) as $id ) {
	$tests[ 'authorized device capability path user ' . $id ] = function () use ( $id ) {
		security_device( $id );
		security_assert( true === WSQ_REST_API::instance()->check_auth( new Security_Request( array( 'authorization' => 'Bearer valid-token' ), array( 'id' => 101 ) ) ), 'Authorized staff denied' );
		security_assert( $id === get_current_user_id() && get_option( WSQ_REST_API::OPTION_DEVICES )['device']['last_active'] > 1, 'Authorized context/activity not updated' );
		$GLOBALS['writes'] = array();
		security_assert( true === WSQ_REST_API::instance()->check_auth( new Security_Request( array( 'x-wsq-token' => 'valid-token' ) ) ) && ! $GLOBALS['writes'], 'x-wsq-token or activity throttling broken' );
	};
}
foreach ( array( 'Bearer invalid-token', 'Bearer', 'Bearer   ' ) as $header ) {
	$tests[ 'invalid bearer cannot inherit privileged session: ' . $header ] = function () use ( $header ) {
		security_device( 1 );
		wp_set_current_user( 9 );
		security_error( WSQ_REST_API::instance()->check_auth( new Security_Request( array( 'authorization' => $header, 'x-wsq-token' => 'valid-token' ) ) ), 401 );
		security_assert( 9 === get_current_user_id() && ! $GLOBALS['writes'], 'Invalid bearer changed authentication context' );
	};
}
$tests['device object capability denial precedes context/activity changes'] = function () {
	security_device( 1 );
	wp_set_current_user( 9 );
	security_error( WSQ_REST_API::instance()->check_auth( new Security_Request( array( 'authorization' => 'Bearer valid-token' ), array( 'id' => 102 ) ) ), 403 );
	security_assert( 9 === get_current_user_id() && ! $GLOBALS['writes'], 'Denied order changed context/activity' );
};
$tests['WP authenticated session requires order-level capability'] = function () {
	wp_set_current_user( 1 );
	$api = WSQ_REST_API::instance();
	security_assert( true === $api->check_auth( new Security_Request( array(), array( 'id' => 101 ) ) ), 'Authorized WP session denied' );
	security_assert( true === $api->check_auth( new Security_Request() ), 'WP session collection denied' );
	security_error( $api->check_auth( new Security_Request( array(), array( 'id' => 102 ) ) ), 403 );
};
$tests['unauthorized WP session denied'] = function () {
	wp_set_current_user( 2 );
	security_error( WSQ_REST_API::instance()->check_auth( new Security_Request() ), 401 );
};
$tests['all order routes use common authorization callback'] = function () {
	$api = WSQ_REST_API::instance();
	$api->register_routes();
	$count = 0;
	foreach ( $GLOBALS['routes'] as $route => $args ) {
		if ( false !== strpos( $route, '(?P<id>' ) ) {
			$count++;
			security_assert( array( $api, 'check_auth' ) === $args['permission_callback'], 'Order route bypasses common permission check' );
		}
	}
	security_assert( 4 === $count, 'Expected four object routes' );
};
$pair_cases = array(
	'missing owner' => array( 'user_id' => null ),
	'deleted owner' => array( 'user_id' => 999 ),
	'demoted owner' => array( 'user_id' => 2 ),
	'expired' => array( 'expires_at' => time() - 1 ),
	'missing expiry' => array( 'expires_at' => null ),
);
foreach ( $pair_cases as $label => $changes ) {
	$tests[ 'pairing rejects ' . $label . ' before device write' ] = function () use ( $changes ) {
		wp_set_current_user( 9 );
		$session = array_replace( array( 'code' => '1234-5678', 'user_id' => 1, 'expires_at' => time() + 600 ), $changes );
		$GLOBALS['transients'][ WSQ_REST_API::TRANSIENT_PAIR ] = $session;
		security_error( WSQ_REST_API::instance()->rest_pair_device( new Security_Request( array(), array( 'pairing_code' => '1234-5678', 'device_id' => 'phone' ) ) ), 403 );
		security_assert( ! $GLOBALS['writes'] && ! get_option( WSQ_REST_API::OPTION_DEVICES ) && $session === get_transient( WSQ_REST_API::TRANSIENT_PAIR ), 'Rejected pairing wrote devices or consumed session' );
	};
}
$tests['authorized unexpired pairing uses session owner, consumes code'] = function () {
	wp_set_current_user( 9 );
	$GLOBALS['transients'][ WSQ_REST_API::TRANSIENT_PAIR ] = array( 'code' => '1234-5678', 'user_id' => 1, 'expires_at' => time() + 600 );
	$request = new Security_Request( array(), array( 'pairing_code' => '۱۲۳۴-۵۶۷۸', 'device_id' => 'phone' ) );
	$result = WSQ_REST_API::instance()->rest_pair_device( $request );
	security_assert( is_array( $result ) && ! empty( $result['success'] ) && 1 === get_option( WSQ_REST_API::OPTION_DEVICES )['phone']['user_id'], 'Pairing did not use session owner' );
	security_assert( false === get_transient( WSQ_REST_API::TRANSIENT_PAIR ), 'Pairing code not consumed' );
	security_error( WSQ_REST_API::instance()->rest_pair_device( $request ), 403 );
};

$passed = 0;
$failed = 0;
foreach ( $tests as $name => $test ) {
	security_reset();
	try {
		$test();
		$passed++;
		echo 'PASS: ' . $name . PHP_EOL;
	} catch ( Throwable $error ) {
		$failed++;
		echo 'FAIL: ' . $name . ' | ' . $error->getMessage() . PHP_EOL;
	}
}
echo 'RESULT: ' . $passed . ' passed, ' . $failed . ' failed; offline doubles, not live WP/WC integration.' . PHP_EOL;
exit( $failed ? 1 : 0 );
