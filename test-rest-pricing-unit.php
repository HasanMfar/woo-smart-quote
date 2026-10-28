<?php
// Offline companion pricing-desk regressions; php -n test-rest-pricing-unit.php
if ( PHP_SAPI !== 'cli' || defined( 'ABSPATH' ) ) {
	exit( 'Run standalone from PHP CLI.' );
}
define( 'ABSPATH', __DIR__ . '/' );
error_reporting( E_ALL );
set_error_handler( function ( $severity, $message, $file, $line ) {
	throw new ErrorException( $message, 0, $severity, $file, $line );
} );

class WP_Error {
	private $code;
	private $message;
	private $data;
	public function __construct( $code, $message, $data = array() ) {
		$this->code = $code; $this->message = $message; $this->data = $data;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}
class WSQ_Logger {
	public static function debug( $scope, $message, $context = array() ) {}
	public static function info( $scope, $message, $context = array() ) {}
	public static function warning( $scope, $message, $context = array() ) {}
	public static function error( $scope, $message, $context = array() ) {}
}
class Test_Item {
	public $quantity;
	public $subtotal;
	public $total;
	public $tax;
	public $saved = false;
	public $meta = array();
	public function __construct( $quantity = 2 ) { $this->quantity = $quantity; }
	public function get_quantity() { return $this->quantity; }
	public function set_subtotal( $value ) { $this->subtotal = $value; }
	public function set_total( $value ) { $this->total = $value; }
	public function set_total_tax( $value ) { $this->tax = $value; }
	public function update_meta_data( $key, $value ) { $this->meta[ $key ] = $value; }
	public function delete_meta_data( $key ) { unset( $this->meta[ $key ] ); }
	public function save() { $this->saved = true; }
	public function get_total() { return null === $this->total ? 0.0 : $this->total; }
}
class Test_Fee {
	public $total;
	public function __construct( $total ) { $this->total = $total; }
	public function get_total() { return $this->total; }
}
class WC_Order {
	public $status;
	public $paid;
	public $items;
	public $meta = array();
	public $total;
	public $shipping;
	public $fees;
	public $cart_tax = 0.0;
	public $saved = 0;
	public $calculated = null;
	public $new_status = null;
	public $notes = array();
	public function __construct( $status = 'quote-request', $paid = false, $total = 0, $items = null, $shipping = 0, $fees = array() ) {
		$this->status   = $status;
		$this->paid     = $paid;
		$this->total    = $total;
		$this->items    = null === $items ? array( 11 => new Test_Item(), 12 => new Test_Item() ) : $items;
		$this->shipping = $shipping;
		$this->fees     = $fees;
	}
	public function get_id() { return 55; }
	public function get_status() { return $this->status; }
	public function is_paid() { return $this->paid; }
	public function get_total() { return $this->total; }
	public function get_items() { return $this->items; }
	public function update_meta_data( $key, $value ) { $this->meta[ $key ] = $value; }
	public function get_meta( $key ) { return isset( $this->meta[ $key ] ) ? $this->meta[ $key ] : ''; }
	public function calculate_totals( $and_taxes = true ) {
		$this->calculated = $and_taxes;
		$sum = 0.0;
		foreach ( $this->items as $item ) {
			$sum += (float) $item->get_total();
		}
		foreach ( $this->fees as $fee ) {
			$sum += (float) $fee->get_total();
		}
		$this->total = $sum + (float) $this->shipping + (float) $this->cart_tax;
	}
	public function get_shipping_total() { return $this->shipping; }
	public function get_fees() { return $this->fees; }
	public function set_cart_tax( $value ) { $this->cart_tax = $value; }
	public function set_total( $value ) { $this->total = $value; }
	public function save() { $this->saved++; }
	public function update_status( $status, $note = '' ) { $this->new_status = $status; }
	public function add_order_note( $note ) { $this->notes[] = $note; }
	public function get_currency() { return 'IRR'; }
	public function get_order_number() { return '55'; }
}
class Test_Request implements ArrayAccess {
	private $id;
	private $params;
	public function __construct( $id, $params ) { $this->id = $id; $this->params = $params; }
	public function get_json_params() { return $this->params; }
	public function offsetExists( $offset ): bool { return 'id' === $offset; }
	public function offsetGet( $offset ): mixed { return 'id' === $offset ? $this->id : null; }
	public function offsetSet( $offset, $value ): void {}
	public function offsetUnset( $offset ): void {}
}
class Test_User {
	public $display_name = 'Mobile Admin';
	public function exists() { return true; }
}
class WSQ_PDF_Generator {
	public static function instance() { return new self(); }
	public function format_jalali_date( $timestamp ) { return '۱۴۰۵'; }
	public function generate( $order_id ) { return '/tmp/quote.pdf'; }
	public function get_pdf_url( $order_id, $refresh = false ) { return 'https://example.invalid/quote.pdf'; }
}
class WSQ_Buyer_Notifier {
	public static function instance() { return new self(); }
	public function dispatch_to_buyer( $order_id, $force = false ) { return array( 'email' => true ); }
}

function __( $text, $domain = '' ) { return $text; }
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_text_field( $value ) { return trim( (string) $value ); }
function sanitize_textarea_field( $value ) { return trim( (string) $value ); }
function wc_get_order( $id ) { return isset( $GLOBALS['order'] ) ? $GLOBALS['order'] : false; }
function wc_get_price_decimals() { return 0; }
function wc_price( $amount, $args = array() ) { return (string) $amount; }
function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }
function wp_get_current_user() { return new Test_User(); }
function rest_ensure_response( $data ) { return $data; }

require_once __DIR__ . '/woo-smart-quote/includes/class-wsq-order.php';
require_once __DIR__ . '/woo-smart-quote/includes/class-wsq-rest-api.php';

function rest_pricing_assert( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}
function rest_pricing_reset( $order ) {
	$GLOBALS['order'] = $order;
}
function rest_pricing_submit( $items, $extra = array() ) {
	$params = array_merge( array( 'items' => $items, 'quote_validity' => '۴۸ ساعت' ), $extra );
	return WSQ_REST_API::instance()->rest_price_quote( new Test_Request( 55, $params ) );
}

$tests = array();

foreach ( array(
	'unpaid request'    => array( 'quote-request', false ),
	'issued quote'      => array( 'quote-sent', false ),
	'paid quote'        => array( 'quote-sent', true ),
	'fulfilled order'   => array( 'completed', false ),
	'ordinary order'    => array( 'pending', false ),
) as $label => $case ) {
	$tests[ 'priced order is locked: ' . $label ] = function () use ( $case, $label ) {
		$order = new WC_Order( $case[0], $case[1] );
		rest_pricing_reset( $order );
		$result = rest_pricing_submit( array( array( 'item_id' => 11, 'custom_price' => 100 ) ) );
		$expected_lock = $case[1] || ! in_array( $case[0], array( 'quote-request', 'quote-sent' ), true );
		if ( $expected_lock ) {
			rest_pricing_assert( $result instanceof WP_Error && 409 === $result->get_error_data()['status'], $label . ' accepted repricing' );
			rest_pricing_assert( 0 === $order->saved && ! $order->meta && ! $order->items[11]->saved, $label . ' was mutated' );
		} else {
			rest_pricing_assert( ! is_wp_error( $result ) && ! empty( $result['success'] ), $label . ' rejected valid pricing' );
		}
	};
}

foreach ( array( '-1' => -1, 'INF' => INF, 'NAN' => NAN, 'overflow' => 1e999, 'text' => 'abc', 'bool' => true ) as $label => $value ) {
	$tests[ 'invalid mobile price rejected before writes: ' . $label ] = function () use ( $value ) {
		$order = new WC_Order();
		rest_pricing_reset( $order );
		$result = rest_pricing_submit( array(
			array( 'item_id' => 11, 'custom_price' => $value ),
			array( 'item_id' => 12, 'custom_price' => 2500 ),
		) );
		rest_pricing_assert( $result instanceof WP_Error && 400 === $result->get_error_data()['status'], 'Invalid price accepted' );
		rest_pricing_assert( 0 === $order->saved && ! $order->meta && ! $order->items[11]->saved && ! $order->items[12]->saved && null === $order->calculated, 'Invalid price mutated the quote' );
	};
}

$tests['foreign, duplicate, invalid and empty lines are rejected'] = function () {
	$order = new WC_Order();
	rest_pricing_reset( $order );
	rest_pricing_assert( rest_pricing_submit( array( array( 'item_id' => 999, 'custom_price' => 10 ) ) ) instanceof WP_Error, 'Foreign item accepted' );

	$order = new WC_Order();
	rest_pricing_reset( $order );
	rest_pricing_assert( rest_pricing_submit( array(
		array( 'item_id' => 11, 'custom_price' => 10 ),
		array( 'item_id' => 11, 'custom_price' => 20 ),
	) ) instanceof WP_Error, 'Duplicate item accepted' );

	$order = new WC_Order();
	rest_pricing_reset( $order );
	rest_pricing_assert( rest_pricing_submit( array( array( 'custom_price' => 10 ) ) ) instanceof WP_Error, 'Entry without item_id accepted' );

	$order = new WC_Order();
	rest_pricing_reset( $order );
	rest_pricing_assert( rest_pricing_submit( array() ) instanceof WP_Error && 0 === $order->saved, 'Empty pricing accepted' );
};

$tests['invalid discount and tax values are rejected'] = function () {
	$order = new WC_Order();
	rest_pricing_reset( $order );
	rest_pricing_assert( rest_pricing_submit( array( array( 'item_id' => 11, 'custom_price' => 10, 'discount_percent' => 'many' ) ) ) instanceof WP_Error, 'Invalid discount accepted' );

	$order = new WC_Order();
	rest_pricing_reset( $order );
	rest_pricing_assert( rest_pricing_submit( array( array( 'item_id' => 11, 'custom_price' => 10 ) ), array( 'tax_total' => 'free' ) ) instanceof WP_Error, 'Invalid tax total accepted' );

	$order = new WC_Order();
	rest_pricing_reset( $order );
	rest_pricing_assert( rest_pricing_submit( array( array( 'item_id' => 11, 'custom_price' => 10 ) ), array( 'tax_rate' => -5 ) ) instanceof WP_Error, 'Negative tax rate accepted' );
};

$tests['valid pricing applies discounts and recalculates totals'] = function () {
	$order = new WC_Order();
	rest_pricing_reset( $order );
	$result = rest_pricing_submit( array(
		array( 'item_id' => 11, 'custom_price' => 1000, 'discount_percent' => 10 ),
		array( 'item_id' => 12, 'custom_price' => 2500 ),
	) );
	rest_pricing_assert( ! is_wp_error( $result ) && ! empty( $result['success'] ), 'Valid pricing rejected' );
	rest_pricing_assert( 2000.0 === $order->items[11]->subtotal && 1800.0 === $order->items[11]->total, 'Discounted line totals incorrect' );
	rest_pricing_assert( 5000.0 === $order->items[12]->subtotal && 10.0 === $order->items[11]->meta['_wsq_item_discount_pct'], 'Second line or discount meta incorrect' );
	rest_pricing_assert( '۴۸ ساعت' === $order->meta['_wsq_quote_validity'] && 'quote-sent' === $order->new_status, 'Validity or status not updated' );
	rest_pricing_assert( false === $order->calculated && 6800.0 === $result['total'], 'Totals not recalculated' );
	rest_pricing_assert( $order->saved >= 1, 'Quote not persisted' );
};

$tests['explicit tax keeps shipping and fees in the payable total'] = function () {
	$order = new WC_Order( 'quote-request', false, 0, null, 200, array( new Test_Fee( 100 ) ) );
	rest_pricing_reset( $order );
	$result = rest_pricing_submit(
		array(
			array( 'item_id' => 11, 'custom_price' => 1000 ),
			array( 'item_id' => 12, 'custom_price' => 2500 ),
		),
		array( 'tax_total' => 500 )
	);
	rest_pricing_assert( ! is_wp_error( $result ), 'Explicit tax rejected' );
	rest_pricing_assert( 7800.0 === $order->total, 'Explicit tax dropped shipping or fees' );
	rest_pricing_assert( null === $order->calculated, 'Recalculation overrode explicit tax' );
};

$tests['tax rate is applied against the discounted line total'] = function () {
	$order = new WC_Order( 'quote-request', false, 0, null, 0, array() );
	rest_pricing_reset( $order );
	$result = rest_pricing_submit(
		array(
			array( 'item_id' => 11, 'custom_price' => 1000, 'discount_percent' => 10 ),
			array( 'item_id' => 12, 'custom_price' => 2500 ),
		),
		array( 'tax_rate' => 10 )
	);
	rest_pricing_assert( ! is_wp_error( $result ), 'Tax rate rejected' );
	rest_pricing_assert( 680.0 === $order->cart_tax && 7480.0 === $order->total, 'Tax rate not applied to net total' );
	rest_pricing_assert( 10.0 === $order->meta['_wsq_custom_tax_rate'], 'Tax rate not persisted' );
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
printf( "%d/%d REST pricing tests passed\n", $passed, count( $tests ) );
exit( $passed === count( $tests ) ? 0 : 1 );
