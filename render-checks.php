<?php
/**
 * Renders UI surfaces for static visual inspection (no web server needed).
 * Usage: wp eval-file render-checks.php
 */

$PHP     = 'F:/Programmes/Local/resources/extraResources/lightning-services/php-8.2.29+0/bin/win64/php.exe';
$OUT_DIR = 'F:/Programming/WordPress/Quote/ui-checks';
if ( ! is_dir( $OUT_DIR ) ) {
	mkdir( $OUT_DIR, 0777, true );
}

$ADMIN_CSS   = file_get_contents( 'F:/Programming/WordPress/Quote/woo-smart-quote/assets/css/admin.css' );
$FRONT_CSS   = file_get_contents( 'F:/Programming/WordPress/Quote/woo-smart-quote/assets/css/frontend.css' );

$ADMIN_JS    = file_get_contents( 'F:/Programming/WordPress/Quote/woo-smart-quote/assets/js/admin.js' );
$FRONT_JS    = file_get_contents( 'F:/Programming/WordPress/Quote/woo-smart-quote/assets/js/frontend.js' );

// ---------- 1. Admin settings page ----------
if ( ! class_exists( 'WSQ_Admin' ) ) {
	require_once 'F:/Programming/WordPress/Quote/woo-smart-quote/includes/class-wsq-admin.php';
}	if ( class_exists( 'WSQ_Admin' ) ) {
	file_put_contents( $OUT_DIR . '/admin.html', '<!DOCTYPE html><html dir="rtl"><meta charset="utf-8"><title>admin placeholder — render in progress</title>' );
	$admin_user = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
	wp_set_current_user( $admin_user ? $admin_user[0]->ID : 1 );
	ob_start();
	set_current_screen( 'toplevel_page_woo-smart-quote' );
	do_action( 'load-settings_page_woo-smart-quote' );
	$_GET['page'] = 'woo-smart-quote';
	$admin = WSQ_Admin::instance();
	ob_start();
	try {
		if ( method_exists( $admin, 'settings_page_html' ) ) {
			$admin->settings_page_html();
		} elseif ( method_exists( $admin, 'render_page' ) ) {
			$admin->render_page();
		} else {
			$ref  = new ReflectionClass( $admin );
			$mets = $ref->getMethods();
			echo '<!-- no known render method. Methods: ';
			foreach ( $mets as $m ) { echo $m->getName() . ' '; }
			echo ' -->';
		}
	} catch ( \Throwable $e ) {
		echo '<pre>admin render error: ' . esc_html( $e->getMessage() ) . '</pre>';
	}
	$admin_inner = ob_get_clean();

	$admin_html = '<!DOCTYPE html><html dir="rtl" lang="fa"><head><meta charset="utf-8">'
		. '<title>Admin UI check</title>'
		. '<style>body { font-family: -apple-system, "Vazirmatn", Tahoma, sans-serif; background:#f0f0f1; margin:0; }'
		. '#wpcontent{padding:0 20px 20px;} #wpbody-content{margin-top:20px;}'
		. '</style>'
		. '<style>' . $ADMIN_CSS . '</style></head><body dir="rtl">'
		. '<div id="wpcontent" dir="rtl"><div id="wpbody-content">' . $admin_inner . '</div></div>'
		. '<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>'
		. '<script>' . $ADMIN_JS . '</script>'
		. '</body></html>';
	file_put_contents( $OUT_DIR . '/admin.html', $admin_html );
	echo 'admin.html written (' . strlen( $admin_html ) . " bytes)\n";
}

// ---------- 2. Frontend quote list ----------
// Seed the quote session with two items so the list actually renders in CLI.
if ( ! WC()->session ) {
	wc_load_cart();
}
if ( WC()->session && ! WC()->session->has_session() ) {
	WC()->session->set_customer_session_cookie( true );
}
$seed_session = WSQ_Session::instance();
if ( empty( $seed_session->get_items() ) ) {
	$product_ids = wc_get_products( array( 'limit' => 2, 'return' => 'ids', 'orderby' => 'date' ) );
	foreach ( $product_ids as $idx => $pid ) {
		$seed_session->add_item( array(
			'product_id' => $pid,
			'quantity'   => $idx + 1,
		) );
	}
}
$front_inner = do_shortcode( '[woo_smart_quote_list]' );
$front_html  = '<!DOCTYPE html><html dir="rtl" lang="fa"><head><meta charset="utf-8">'
	. '<title>Frontend UI check</title>'
	. '<style>body{font-family:Tahoma,"Vazirmatn",sans-serif;max-width:860px;margin:30px auto;padding:0 16px;background:#fff;}</style>'
	. '<style>' . $FRONT_CSS . '</style></head><body dir="rtl" class="woocommerce">'
	. $front_inner
	. '<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>'
	. '<script>var wsq_params = { ajax_url: "", nonce: "", quote_page_url: "#", i18n: { added: "اضافه شد", err_phone: "شماره معتبر نیست" } };</script>'
	. '<script>' . $FRONT_JS . '</script>'
	. '</body></html>';
file_put_contents( $OUT_DIR . '/frontend.html', $front_html );
echo 'frontend.html written (' . strlen( $front_html ) . " bytes)\n";

// ---------- 3. Latest generated PDFs ----------
$pdf_dir = wp_upload_dir()['basedir'] . '/woo-smart-quote-pdfs';
$pdfs    = glob( $pdf_dir . '/*.pdf' );
if ( $pdfs ) {
	usort( $pdfs, function ( $a, $b ) { return filemtime( $b ) - filemtime( $a ); } );
	copy( $pdfs[0], $OUT_DIR . '/latest-quote.pdf' );
	echo 'PDF copied: ' . basename( $pdfs[0] ) . "\n";
} else {
	echo "NO PDF\n";
}
