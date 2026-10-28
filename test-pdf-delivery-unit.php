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
