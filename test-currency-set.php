<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// Iranian Toman, whole numbers, right side.
update_option( 'woocommerce_currency', 'IRT' );
update_option( 'woocommerce_price_num_decimals', 0 );
update_option( 'woocommerce_price_thousand_sep', ',' );
update_option( 'woocommerce_price_display_suffix', 'تومان' );
echo 'currency set: ' . get_woocommerce_currency() . "\n";
