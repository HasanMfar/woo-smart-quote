<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$order = wc_get_order( 25 );
echo 'status=' . $order->get_status() . ' total=' . $order->get_total() . ' sent_flag=' . ( '' !== $order->get_meta( '_wsq_proposal_sent' ) ? 'yes(' . $order->get_meta( '_wsq_proposal_sent' ) . ')' : 'no' ) . ' validity=' . $order->get_meta( '_wsq_quote_validity' ) . "\n";

foreach ( wc_get_order_notes( array( 'order_id' => 25 ) ) as $note ) {
	echo 'note: ' . wp_strip_all_tags( $note->content ) . "\n";
}

// Items recap.
foreach ( $order->get_items() as $item ) {
	echo 'item: ' . $item->get_name() . ' qty=' . $item->get_quantity() . ' subtotal=' . $item->get_subtotal() . ' total=' . $item->get_total() . "\n";
}

// AS state.
if ( function_exists( 'as_get_scheduled_actions' ) ) {
	$acts = as_get_scheduled_actions( array( 'group' => 'woo_smart_quote', 'per_page' => 20 ), OBJECT );
	foreach ( $acts as $a ) {
		$hook  = method_exists( $a, 'get_hook' ) ? $a->get_hook() : ( isset( $a->hook ) ? $a->hook : '?' );
		$state = method_exists( $a, 'get_status' ) ? $a->get_status() : 'done?';
		echo 'AS: ' . $hook . ' [' . $state . "]\n";
	}
}

// PDF dir state.
$upload = wp_upload_dir()['basedir'] . '/woo-smart-quote-pdfs';
$files  = glob( $upload . '/*.pdf' );
echo 'pdf files: ' . count( $files ) . ' | ' . implode( ', ', array_map( 'basename', $files ) ) . "\n";
