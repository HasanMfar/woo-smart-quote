<?php
/**
 * Local-site fixture bootstrap: products + category for testing.
 * Run via: php wp-cli.phar --path=<site> eval-file fixtures.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$make = function ( $title, $price ) {
	$p = new WC_Product_Simple();
	$p->set_name( $title );
	$p->set_regular_price( $price > 0 ? (string) $price : '' );
	$p->set_status( 'publish' );
	$p->set_description( 'Test product for quote flow' );
	return $p->save();
};

$a = $make( 'Industrial Bearing ABEC-5', 18500000 );
$b = $make( 'Custom Sheet Metal Panel', 0 );
$c = $make( 'Hydraulic Jack 20T', 6400000 );

$term = wp_insert_term( 'Industrial', 'product_cat' );
$term_id = is_wp_error( $term ) ? (int) get_term_by( 'slug', 'industrial', 'product_cat' )->term_id : $term['term_id'];

foreach ( array( $a, $b, $c ) as $pid ) {
	wp_set_object_terms( $pid, array( 'industrial' ), 'product_cat', true );
}

// Category routing stub so determine_routes() has data to work with.
update_option( 'wsq_bale_default_chat_ids', "168621\n987654" );
update_option( 'wsq_eitaa_default_chat_ids', "321654" );
update_option( 'wsq_category_routes', array(
	$term_id => array(
		'bale'  => '555000',
		'eitaa' => '777000',
	),
) );

echo 'created: ' . $a . ', ' . $b . ', ' . $c . ' | term_id: ' . $term_id . "\n";
