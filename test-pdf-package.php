<?php
/**
 * PDF smoke test: renders a Persian invoice PDF with the packaged plugin
 * (mPDF + Vazirmatn fonts) and verifies the file is produced and usable.
 *
 * Run: wp eval-file test-pdf-package.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$orders = wc_get_orders( array(
	'limit'        => 20,
	'orderby'      => 'date',
	'order'        => 'DESC',
	'status'       => array( 'wc-quote-request', 'wc-quote-sent', 'processing', 'completed' ),
	'return'       => 'ids',
) );

if ( empty( $orders ) ) {
	echo "FAIL: no quote order available to render\n";
	exit( 1 );
}

$order_id = $orders[0];
$order    = wc_get_order( $order_id );

// Make sure the order has a buyer phone so the template renders fully.
if ( ! $order->get_meta( '_wsq_buyer_phone' ) ) {
	$order->update_meta_data( '_wsq_buyer_phone', '09121234567' );
	$order->save();
}

echo 'order: ' . $order_id . ' total=' . $order->get_total() . "\n";

$generator = WSQ_PDF_Generator::instance();
$result    = $generator->generate( $order_id );

if ( is_wp_error( $result ) ) {
	echo 'FAIL: PDF error: ' . $result->get_error_message() . "\n";
	exit( 1 );
}

$file = is_string( $result ) ? $result : '';
if ( ! $file || ! file_exists( $file ) ) {
	echo "FAIL: PDF file not found\n";
	exit( 1 );
}

$size = filesize( $file );
$head = substr( (string) file_get_contents( $file ), 0, 5 );

echo 'file: ' . $file . "\n";
echo 'size: ' . number_format( $size ) . " bytes\n";
echo 'header: ' . $head . "\n";

$ok = ( '%PDF-' === $head ) && ( $size > 20000 );
echo ( $ok ? 'PASS' : 'FAIL' ) . ": PDF generated and non-trivial\n";

// Font check: the Persian text must be embedded as a TTF subset, not tofu.
$raw  = (string) file_get_contents( $file );
$font = ( false !== strpos( $raw, 'Vazirmatn' ) ) || ( false !== strpos( $raw, 'Vazir' ) );
echo ( $font ? 'PASS' : 'FAIL' ) . ": Vazirmatn font embedded\n";

echo ( $ok && $font ) ? "ALL_PASS\n" : "SOME_FAIL\n";
