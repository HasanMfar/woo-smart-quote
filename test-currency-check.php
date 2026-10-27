<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$c = get_woocommerce_currencies();
echo ( isset( $c['IRT'] ) ? 'IRT-supported ' : 'no-IRT ' );
echo ( isset( $c['IRR'] ) ? 'IRR-supported' : 'no-IRR' );
echo ' | current: ' . get_woocommerce_currency();
echo "\n";
