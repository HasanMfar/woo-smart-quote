<?php
// Offline PDF delivery regressions; php -n test-pdf-delivery-unit.php
if ( PHP_SAPI !== 'cli' || defined( 'ABSPATH' ) ) {
	exit( 'Run standalone from PHP CLI.' );
}
define( 'ABSPATH', __DIR__ . '/' );
define( 'WSQ_PLUGIN_DIR', __DIR__ . '/woo-smart-quote/' );
error_reporting( E_ALL );
set_error_handler( function ( $severity, $message, $file, $line ) {
	throw new ErrorException( $message, 0, $severity, $file, $line );
} );

// Skip the bundled mPDF bootstrap (and its platform checks) in this harness.
eval( 'namespace Mpdf; class Mpdf {}' );

class PDF_Order {
	public $id;
	public $secret;
	public function __construct( $id, $secret ) { $this->id = $id; $this->secret = $secret; }
	public function get_id() { return $this->id; }
	public function get_meta( $key ) { return '_wsq_pdf_secret' === $key ? $this->secret : ''; }
	public function get_currency() { return 'IRT'; }
	public function get_billing_company() { return 'شرکت تست'; }
	public function get_billing_phone() { return '09120000000'; }
	public function get_billing_email() { return 'buyer@example.invalid'; }
	public function get_billing_postcode() { return '1234567890'; }
	public function get_billing_state() { return 'تهران'; }
	public function get_billing_city() { return 'تهران'; }
	public function get_billing_address_1() { return 'خیابان تست'; }
	public function get_billing_address_2() { return ''; }
	public function get_total_discount() { return 0; }
	public function get_shipping_total() { return 0; }
	public function get_total_tax() { return 0; }
	public function get_subtotal() { return 10000; }
	public function get_total() { return 10000; }
	public function get_customer_note() { return ''; }
	public function get_order_number() { return $this->id; }
}
class WSQ_Logger {
	public static function debug( $scope, $message, $context = array() ) {}
	public static function info( $scope, $message, $context = array() ) {}
	public static function warning( $scope, $message, $context = array() ) {}
	public static function error( $scope, $message, $context = array() ) {}
}

function __( $text, $domain = '' ) { return $text; }
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}
function add_query_arg( $args, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args ); }
function admin_url( $path = '' ) { return 'https://example.invalid/wp-admin/' . ltrim( $path, '/' ); }
function wp_salt( $scheme = 'auth' ) { return 'test-salt'; }
function wp_unslash( $value ) { return $value; }
function wp_upload_dir() {
	return array(
		'basedir' => $GLOBALS['pdf_dir'],
		'baseurl' => 'https://example.invalid/uploads',
	);
}
function wc_get_order( $id ) { return isset( $GLOBALS['order'] ) ? $GLOBALS['order'] : false; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $url ) { return filter_var( (string) $url, FILTER_SANITIZE_URL ); }
function esc_html__( $text, $domain = '' ) { return $text; }
function esc_html_e( $text, $domain = '' ) { echo $text; }
function get_bloginfo( $show = '' ) { return 'فروشگاه تست'; }
function get_option( $key, $default = false ) {
	return isset( $GLOBALS['test_options'][ $key ] ) ? $GLOBALS['test_options'][ $key ] : $default;
}
function wp_kses_post( $s ) { return $s; }
function wpautop( $s ) { return $s; }

require_once __DIR__ . '/woo-smart-quote/includes/class-wsq-pdf-generator.php';

function pdf_assert( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}

$GLOBALS['pdf_dir'] = sys_get_temp_dir() . '/wsq-pdf-fixture-' . getmypid();
@mkdir( $GLOBALS['pdf_dir'] . '/woo-smart-quote-pdfs', 0777, true );

$secret = 'abc123def456';
$GLOBALS['order'] = new PDF_Order( 55, $secret );
file_put_contents( $GLOBALS['pdf_dir'] . '/woo-smart-quote-pdfs/quote-55-' . $secret . '.pdf', '%PDF-1.4 fixture' );

$generator = WSQ_PDF_Generator::instance();

$tests = array();

$tests['missing PDF is reported without generating'] = function () use ( $generator, $secret ) {
	$GLOBALS['order'] = new PDF_Order( 56, $secret );
	pdf_assert( false === $generator->get_pdf_path( 56, false ), 'Missing file resolved' );
	pdf_assert( '' === $generator->get_pdf_filename( 56 ), 'Missing file produced a name' );
	$GLOBALS['order'] = new PDF_Order( 55, $secret );
};

$tests['download URL points at the streaming endpoint, not uploads'] = function () use ( $generator ) {
	$url = $generator->get_pdf_url( 55, false );
	pdf_assert( is_string( $url ) && '' !== $url, 'URL missing for an existing PDF' );
	pdf_assert( false !== strpos( $url, 'action=wsq_download_pdf' ), 'URL does not target the download action' );
	pdf_assert( false !== strpos( $url, 'wsq_pdf=55' ), 'URL lacks the order id' );
	pdf_assert( false === strpos( $url, 'woo-smart-quote-pdfs' ), 'URL still exposes the uploads path' );
};

$tests['token validates only for the current PDF'] = function () use ( $generator, $secret ) {
	$url   = $generator->get_pdf_url( 55, false );
	parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $query );
	$token = isset( $query['token'] ) ? $query['token'] : '';

	pdf_assert( '' !== $token, 'URL carries no token' );
	pdf_assert( true === $generator->is_valid_pdf_token( 55, $token ), 'Issued token rejected' );
	pdf_assert( false === $generator->is_valid_pdf_token( 55, '' ), 'Empty token accepted' );
	pdf_assert( false === $generator->is_valid_pdf_token( 55, str_repeat( 'a', strlen( $token ) ) ), 'Forged token accepted' );
	$GLOBALS['order'] = new PDF_Order( 56, $secret );
	pdf_assert( false === $generator->is_valid_pdf_token( 56, $token ), 'Token replayed against another order' );

	$GLOBALS['order'] = new PDF_Order( 55, 'rotated-secret' );
	pdf_assert( false === $generator->is_valid_pdf_token( 55, $token ), 'Token survived secret rotation' );
	$GLOBALS['order'] = new PDF_Order( 55, $secret );
};

$tests['expired tokens are rejected even with a valid signature'] = function () use ( $generator, $secret ) {
	$GLOBALS['order'] = new PDF_Order( 55, $secret );

	$past    = time() - 60;
	$expired = $past . '.' . hash_hmac( 'sha256', 'wsq-pdf|55|' . $secret . '|' . $past, 'test-salt' );
	pdf_assert( false === $generator->is_valid_pdf_token( 55, $expired ), 'Expired token was accepted' );

	$future = time() + 60;
	$valid  = $future . '.' . hash_hmac( 'sha256', 'wsq-pdf|55|' . $secret . '|' . $future, 'test-salt' );
	pdf_assert( true === $generator->is_valid_pdf_token( 55, $valid ), 'Signed future token was rejected' );

	pdf_assert( false === $generator->is_valid_pdf_token( 55, $future . '.short' ), 'Truncated signature was accepted' );
	pdf_assert( false === $generator->is_valid_pdf_token( 55, (string) $future ), 'Unsigned expiry was accepted' );
};

$tests['orders without a PDF secret never validate'] = function () use ( $generator, $secret ) {
	$GLOBALS['order'] = new PDF_Order( 55, '' );
	pdf_assert( false === $generator->is_valid_pdf_token( 55, 'anything' ), 'Secretless order accepted a token' );
	pdf_assert( false === $generator->get_pdf_url( 55, false ), 'Secretless order produced a URL' );
	$GLOBALS['order'] = new PDF_Order( 55, $secret );
};

$tests['vector signature SVG is valid and contains calligraphic paths'] = function () {
	$svg = WSQ_PDF_Generator::get_vector_signature();
	pdf_assert( is_string( $svg ) && false !== strpos( $svg, '<svg' ), 'Vector signature is not an SVG string' );
	pdf_assert( false !== strpos( $svg, 'stroke="#1d4ed8"' ), 'Vector signature lacks royal ink strokes' );
	pdf_assert( false !== strpos( $svg, '</svg>' ), 'Vector signature SVG not closed' );
};

$tests['corporate seal and signature slots render independently in quote template'] = function () {
	$order = new PDF_Order( 101, 'dummy' );
	$items = array();

	$base_vars = array(
		'order'                 => $order,
		'order_id'              => 101,
		'customer_name'         => 'خریدار تست',
		'date'                  => '۱۴۰۳/۰۸/۰۸',
		'date_gregorian'        => '2024-10-29',
		'date_expiry'           => '۱۴۰۳/۰۸/۱۵',
		'items'                 => $items,
		'total'                 => '۱۰,۰۰۰ تومان',
		'logo_url'              => '',
		'company_name'          => 'شرکت نمونه',
		'economic_code'         => '',
		'national_id'           => '',
		'registration_no'       => '',
		'company_postal_code'   => '',
		'company_phone'         => '',
		'company_address'       => '',
		'company_bank_name'     => '',
		'company_bank_account'  => '',
		'company_bank_shaba'    => '',
		'company_bank_owner'    => '',
		'header_note'           => '',
		'footer_text'           => '',
		'validity_days'         => 7,
		'buyer_national_id'     => '',
		'buyer_economic_code'   => '',
		'buyer_registration_no' => '',
	);

	$render = function( $overrides ) use ( $base_vars ) {
		extract( array_merge( $base_vars, $overrides ) );
		ob_start();
		include WSQ_PLUGIN_DIR . 'templates/pdf/quote-fa.php';
		return ob_get_clean();
	};

	// Case 1: Uploaded seal image + uploaded signature image
	$html1 = $render( array(
		'seal_image_url'      => 'https://site.test/my-seal.png',
		'show_stamp'          => false,
		'signature_image_url' => 'https://site.test/my-sig.png',
		'show_signature'      => false,
	) );
	pdf_assert( false !== strpos( $html1, 'src="https://site.test/my-seal.png"' ), 'Case 1: Seal image missing' );
	pdf_assert( false !== strpos( $html1, 'src="https://site.test/my-sig.png"' ), 'Case 1: Signature image missing' );
	pdf_assert( false === strpos( $html1, 'stroke-dasharray="2.5,2"' ), 'Case 1: Dynamic seal vector leaked' );
	pdf_assert( false === strpos( $html1, 'stroke="#1d4ed8"' ), 'Case 1: Vector signature leaked' );

	// Case 2: No images, both toggles enabled -> fallback to both vector seal and vector signature
	$html2 = $render( array(
		'seal_image_url'      => '',
		'show_stamp'          => true,
		'signature_image_url' => '',
		'show_signature'      => true,
	) );
	pdf_assert( false !== strpos( $html2, 'stroke-dasharray="2.5,2"' ), 'Case 2: Dynamic seal vector missing' );
	pdf_assert( false !== strpos( $html2, 'stroke="#1d4ed8"' ), 'Case 2: Vector signature missing' );
	pdf_assert( false === strpos( $html2, 'src="https://site.test/' ), 'Case 2: Ghost image rendered' );

	// Case 3: Merchant disables signature toggle (show_signature=false) and seal toggle (show_stamp=false)
	$html3 = $render( array(
		'seal_image_url'      => '',
		'show_stamp'          => false,
		'signature_image_url' => '',
		'show_signature'      => false,
	) );
	pdf_assert( false === strpos( $html3, 'stroke-dasharray="2.5,2"' ), 'Case 3: Seal vector should be hidden' );
	pdf_assert( false === strpos( $html3, 'stroke="#1d4ed8"' ), 'Case 3: Signature vector should be hidden' );
	pdf_assert( false === strpos( $html3, 'alt="Signature"' ), 'Case 3: Signature img tag should not exist' );
	pdf_assert( false === strpos( $html3, 'alt="Seal"' ), 'Case 3: Seal img tag should not exist' );

	// Case 4: Uploaded signature image + vector seal enabled (hybrid)
	$html4 = $render( array(
		'seal_image_url'      => '',
		'show_stamp'          => true,
		'signature_image_url' => 'https://site.test/exec-sig.png',
		'show_signature'      => true,
	) );
	pdf_assert( false !== strpos( $html4, 'stroke-dasharray="2.5,2"' ), 'Case 4: Vector seal missing' );
	pdf_assert( false !== strpos( $html4, 'src="https://site.test/exec-sig.png"' ), 'Case 4: Uploaded signature missing' );
	pdf_assert( false === strpos( $html4, 'stroke="#1d4ed8"' ), 'Case 4: Vector signature should be suppressed when signature image is provided' );
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

@unlink( $GLOBALS['pdf_dir'] . '/woo-smart-quote-pdfs/quote-55-' . $secret . '.pdf' );
@rmdir( $GLOBALS['pdf_dir'] . '/woo-smart-quote-pdfs' );
@rmdir( $GLOBALS['pdf_dir'] );

printf( "%d/%d PDF delivery tests passed\n", $passed, count( $tests ) );
exit( $passed === count( $tests ) ? 0 : 1 );
