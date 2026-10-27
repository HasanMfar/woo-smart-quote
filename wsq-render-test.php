<?php
// Render the bot tab in isolation to catch fatal/HTML errors without a web server.

// Ensure the admin class is loaded (is_admin() is false under WP-CLI).
require_once WSQ_PLUGIN_DIR . 'includes/class-wsq-admin.php';

$admin = WSQ_Admin::instance();

$ref = new ReflectionClass( $admin );
$method = $ref->getMethod( 'render_bot_tab' );
$method->setAccessible( true );

// Give the tab some data to render.
update_option( 'wsq_bale_admin_chat_ids', '900001' );
update_option( 'wsq_buyer_bot_enabled', 'yes' );

ob_start();
$method->invoke( $admin );
$html = ob_get_clean();

echo 'rendered_length=' . strlen( $html ) . "\n";

$checks = array(
	'webhook url field'  => false !== strpos( $html, 'wsq-webhook-url' ),
	'webhook buttons'    => false !== strpos( $html, 'wsq-webhook-btn' ),
	'polling checkbox'   => false !== strpos( $html, 'wsq_polling_enabled' ),
	'admin chat ids'     => false !== strpos( $html, 'wsq_bale_admin_chat_ids' ),
	'buyer bot toggle'   => false !== strpos( $html, 'wsq_buyer_bot_enabled' ),
	'welcome textarea'   => false !== strpos( $html, 'wsq_bot_welcome' ),
	'orders template'    => false !== strpos( $html, 'wsq_bot_orders_header' ) && false !== strpos( $html, '{#items}' ),
	'order card template'=> false !== strpos( $html, 'wsq_bot_order_card' ) && false !== strpos( $html, '{payment_section}' ),
	'token chips'        => false !== strpos( $html, 'data-token="{order_number}"' ) && false !== strpos( $html, 'data-token="{name}"' ),
	'inbox table/empty'  => false !== strpos( $html, 'صندوق ورودی' ),
	'reply button'       => false !== strpos( $html, 'wsq-reply-btn' ) || false !== strpos( $html, 'هنوز پیامی' ),
	'unbalanced php'     => false === strpos( $html, '<?' ),
);

$pass = true;
foreach ( $checks as $label => $ok ) {
	echo ( $ok ? 'PASS' : 'FAIL' ) . " - {$label}\n";
	if ( ! $ok ) {
		$pass = false;
	}
}

echo $pass ? "ALL_PASS\n" : "SOME_FAIL\n";
