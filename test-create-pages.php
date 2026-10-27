<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WC_Install' ) && defined( 'WC_PLUGIN_FILE' ) ) {
	include_once WC_PLUGIN_FILE;
	require_once dirname( WC_PLUGIN_FILE ) . '/includes/admin/class-wc-install.php';
}
if ( class_exists( 'WC_Install' ) ) {
	WC_Install::create_pages();
}
echo 'shop=' . get_option( 'woocommerce_shop_page_id' );
echo ' checkout=' . get_option( 'woocommerce_checkout_page_id' );
echo ' myaccount=' . get_option( 'woocommerce_myaccount_page_id' );
echo ' cart=' . get_option( 'woocommerce_cart_page_id' );
echo "\n";
