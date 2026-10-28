<?php
// Offline pricing-guard regressions; php -n test-pricing-unit.php
if ( PHP_SAPI !== 'cli' || defined( 'ABSPATH' ) ) {
	exit( 'Run standalone from PHP CLI.' );
}
define( 'ABSPATH', __DIR__ . '/' );
error_reporting( E_ALL );
set_error_handler( function ( $severity, $message, $file, $line ) {
	throw new ErrorException( $message, 0, $severity, $file, $line );
} );

class Pricing_Stop extends Exception {
	public $payload;
	public function __construct( $payload ) {
		parent::__construct( 'handler responded' );
		$this->payload = $payload;
	}
}
class WP_Error {
	private $code;
	private $message;
	private $data;
	public function __construct( $code, $message, $data = array() ) {
		$this->code = $code; $this->message = $message; $this->data = $data;
	}
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}
class Pricing_Item {
	public $subtotal;
	public $total;
	public $saved = false;
	public function get_quantity() { return 2; }
	public function set_subtotal( $value ) { $this->subtotal = $value; }
	public function set_total( $value ) { $this->total = $value; }
	public function save() { $this->saved = true; }
}
class WC_Order {
	public $status;
	public $paid;
	public $total;
	public $items;
	public $meta = array();
	public $calculated = false;
	public $saved = 0;
	public $new_status;
	public $processing = true;
	public function __construct( $status = 'quote-request', $paid = false, $total = 1000, $items = null ) {
		$this->status = $status; $this->paid = $paid; $this->total = $total;
		$this->items = null === $items ? array( 11 => new Pricing_Item(), 12 => new Pricing_Item() ) : $items;
	}
	public function get_id() { return 55; }
	public function get_status() { return $this->status; }
	public function is_paid() { return $this->paid; }
	public function get_total() { return $this->total; }
	public function get_items() { return $this->items; }
	public function needs_processing() { return $this->processing; }
	public function update_meta_data( $key, $value ) { $this->meta[ $key ] = $value; }
	public function calculate_totals() { $this->calculated = true; }
	public function save() { $this->saved++; }
	public function update_status( $status ) { $this->new_status = $status; }
	public function get_edit_order_url() { return 'https://example.invalid/edit/55'; }
}
function __( $text, $domain = '' ) { return $text; }
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['filters'][ $hook ] = $callback; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_doing_ajax() { return true; }
function check_admin_referer( $action, $field ) {}
function current_user_can( $cap, $id = null ) {
	return isset( $GLOBALS['caps'][ $cap . ( null === $id ? '' : ':' . $id ) ] );
}
function wc_get_order( $id ) { return isset( $GLOBALS['order'] ) ? $GLOBALS['order'] : false; }
function sanitize_text_field( $value ) { return trim( (string) $value ); }
function wp_unslash( $value ) { return $value; }
function absint( $value ) { return abs( (int) $value ); }
function wp_send_json_error( $data ) { throw new Pricing_Stop( array( 'ok' => false, 'data' => $data ) ); }
function wp_send_json_success( $data ) { throw new Pricing_Stop( array( 'ok' => true, 'data' => $data ) ); }
function wp_die( $message ) { throw new Pricing_Stop( array( 'ok' => false, 'data' => array( 'message' => $message ) ) ); }
function add_query_arg( $key, $value, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $key . '=' . $value; }

require_once __DIR__ . '/woo-smart-quote/includes/class-wsq-order.php';

function pricing_assert( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}
function pricing_reset( $order ) {
	$GLOBALS['order'] = $order;
	$GLOBALS['caps']  = array( 'edit_shop_orders' => true, 'edit_shop_order:55' => true );
	$_POST = array(
		'order_id'           => 55,
		'wsq_quote_validity' => '۴۸ ساعت',
		'wsq_item_prices'    => array( 11 => '1000', 12 => '2500' ),
	);
}
function pricing_submit() {
	try {
		WSQ_Order::instance()->handle_save_and_send_proposal();
	} catch ( Pricing_Stop $stop ) {
		return $stop->payload;
	}
	throw new RuntimeException( 'Handler did not respond' );
}

$tests = array();
$tests['paid quote cannot be repriced'] = function () {
	$order = new WC_Order( 'quote-sent', true );
	pricing_reset( $order );
	$result = pricing_submit();
	pricing_assert( false === $result['ok'], 'Paid order not rejected' );
	pricing_assert( ! $order->saved && null === $order->new_status && ! $order->meta && ! $order->items[11]->saved, 'Paid order was mutated' );
};
$tests['fulfilled or ordinary orders cannot be repriced'] = function () {
	foreach ( array( 'processing', 'completed', 'cancelled', 'pending' ) as $status ) {
		$order = new WC_Order( $status );
		pricing_reset( $order );
		$result = pricing_submit();
		pricing_assert( false === $result['ok'] && ! $order->saved && null === $order->new_status && ! $order->meta, $status . ' order was repriced' );
	}
};
$tests['object capability is required'] = function () {
	$order = new WC_Order();
	pricing_reset( $order );
	$GLOBALS['caps'] = array( 'edit_shop_orders' => true );
	$result = pricing_submit();
	pricing_assert( false === $result['ok'] && ! $order->saved && ! $order->meta, 'Object-denied staff mutated the quote' );
};
foreach ( array( '-1' => '-1', 'INF' => 'INF', 'NAN' => 'NAN', '1e999' => '1e999', 'text' => 'abc', 'bool' => true ) as $label => $value ) {
	$tests[ 'invalid price rejected before writes: ' . $label ] = function () use ( $value ) {
		$order = new WC_Order();
		pricing_reset( $order );
		$_POST['wsq_item_prices'][11] = $value;
		$result = pricing_submit();
		pricing_assert( false === $result['ok'] && ! $order->saved && ! $order->meta && ! $order->items[11]->saved && ! $order->calculated, 'Invalid price mutated the quote' );
	};
}
$tests['unknown or missing line items are rejected atomically'] = function () {
	$order = new WC_Order();
	pricing_reset( $order );
	$_POST['wsq_item_prices'][999] = '10';
	pricing_assert( false === pricing_submit()['ok'] && ! $order->saved && ! $order->meta, 'Foreign item accepted' );

	$order = new WC_Order();
	pricing_reset( $order );
	unset( $_POST['wsq_item_prices'][12] );
	pricing_assert( false === pricing_submit()['ok'] && ! $order->saved && ! $order->meta, 'Incomplete pricing accepted' );
};
$tests['valid pricing writes every line then sends proposal'] = function () {
	$order = new WC_Order();
	pricing_reset( $order );
	$result = pricing_submit();
	pricing_assert( true === $result['ok'] && false !== strpos( $result['data']['redirect'], 'wsq_proposal_sent=1' ), 'Valid request rejected' );
	pricing_assert( 2000.0 === $order->items[11]->subtotal && 5000.0 === $order->items[12]->subtotal, 'Line totals incorrect' );
	pricing_assert( '۴۸ ساعت' === $order->meta['_wsq_quote_validity'] && $order->calculated && 'quote-sent' === $order->new_status, 'Validity, totals or status not updated' );
};
$tests['payment filters registered and limited to unpaid priced quotes'] = function () {
	pricing_assert( isset( $GLOBALS['filters']['woocommerce_valid_order_statuses_for_payment'], $GLOBALS['filters']['woocommerce_payment_complete_order_status'] ), 'Payment filters not registered' );
	$api = WSQ_Order::instance();
	$unpaid = new WC_Order( 'quote-sent', false, 1000 );
	$statuses = $api->allow_payment_for_issued_quote( array( 'pending' ), $unpaid );
	pricing_assert( in_array( 'quote-sent', $statuses, true ), 'Issued quote is not payable' );
	pricing_assert( ! in_array( 'quote-sent', $api->allow_payment_for_issued_quote( array( 'pending' ), new WC_Order( 'quote-request' ) ), true ), 'Unpriced request became payable' );
	pricing_assert( ! in_array( 'quote-sent', $api->allow_payment_for_issued_quote( array( 'pending' ), new WC_Order( 'quote-sent', true ) ), true ), 'Paid quote offered payment again' );
	pricing_assert( ! in_array( 'quote-sent', $api->allow_payment_for_issued_quote( array( 'pending' ), new WC_Order( 'quote-sent', false, 0 ) ), true ), 'Zero-total quote became payable' );

	$GLOBALS['order'] = new WC_Order( 'quote-sent' );
	pricing_assert( 'processing' === $api->payment_complete_status_for_quote( 'completed', 55 ), 'Physical quote did not become processing' );
	$GLOBALS['order'] = new WC_Order( 'quote-sent' );
	$GLOBALS['order']->processing = false;
	pricing_assert( 'completed' === $api->payment_complete_status_for_quote( 'on-hold', 55 ), 'Virtual quote did not complete' );
	$GLOBALS['order'] = new WC_Order( 'processing' );
	pricing_assert( 'on-hold' === $api->payment_complete_status_for_quote( 'on-hold', 55 ), 'Unrelated order status was overridden' );
};

$passed = 0;
$GLOBALS['filters'] = array();
foreach ( $tests as $name => $test ) {
	try {
		$test();
		$passed++;
	} catch ( Throwable $error ) {
		fwrite( STDERR, 'FAIL: ' . $name . ' — ' . $error->getMessage() . "\n" );
	}
}
printf( "%d/%d pricing tests passed\n", $passed, count( $tests ) );
exit( $passed === count( $tests ) ? 0 : 1 );
