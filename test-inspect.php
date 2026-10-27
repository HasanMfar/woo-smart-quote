<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo 'WP: ' . get_bloginfo( 'version' ) . ' | WC: ' . ( function_exists( 'wc_get_version' ) ? wc_get_version() : '?' ) . ' | HPOS: ' . ( class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) && wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled() ? 'yes' : 'no' ) . "\n";

if ( function_exists( 'as_get_scheduled_actions' ) ) {
	$acts = as_get_scheduled_actions( array( 'group' => 'woo_smart_quote' ), OBJECT );
	foreach ( $acts as $a ) {
		$aid = method_exists( $a, 'get_id' ) ? $a->get_id() : $a->action_id;
		$hook = method_exists( $a, 'get_hook' ) ? $a->get_hook() : $a->hook;
		$status = method_exists( $a, 'get_status' ) ? $a->get_status() : $a->status;
		$attempts = method_exists( $a, 'get_attempts' ) ? $a->get_attempts() : $a->attempts;
		echo "AS: #{$aid} {$hook} status={$status} attempts={$attempts}\n";
	}
} else {
	echo "AS: missing\n";
}

foreach ( array( 25, 26, 27 ) as $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		continue;
	}
	echo "ORDER {$order_id}: status=" . $order->get_status() . ' total=' . $order->get_total() . ' phone=' . $order->get_billing_phone() . ' meta_phone=' . $order->get_meta( '_wsq_customer_phone' ) . ' channel=' . $order->get_meta( '_wsq_buyer_channel' ) . "\n";
	$notes = function_exists( 'wc_get_order_notes' ) ? wc_get_order_notes( array( 'order_id' => $order_id ) ) : array();
	foreach ( $notes as $note ) {
		echo '  note: ' . substr( wp_strip_all_tags( $note->content ), 0, 220 ) . "\n";
	}
	$meta = $order->get_meta( '_wsq_expert_notify_failed_targets' );
	if ( $meta ) {
		echo "  failed_targets: " . wp_json_encode( $meta ) . "\n";
	}
	$pdf_secret = $order->get_meta( '_wsq_pdf_secret' );
	if ( $pdf_secret ) {
		echo "  pdf_secret: yes (" . strlen( $pdf_secret ) . " chars)\n";
	}
	$validity = $order->get_meta( '_wsq_quote_validity' );
	if ( $validity ) {
		echo "  validity: {$validity}\n";
	}
}

$upload = wp_upload_dir()['basedir'] . '/woo-smart-quote-pdfs';
if ( is_dir( $upload ) ) {
	$files = glob( $upload . '/*.pdf' );
	echo 'PDF dir: ' . count( $files ) . " file(s): " . implode( ', ', array_map( 'basename', $files ) ) . ' | htaccess=' . ( file_exists( $upload . '/.htaccess' ) ? 'yes' : 'no' ) . "\n";
	$ht = file_get_contents( $upload . '/.htaccess' );
	echo 'htaccess: ' . str_replace( "\n", ' \\n ', $ht ) . "\n";
} else {
	echo "PDF dir: MISSING\n";
}

// WC logger files.
$base = defined( 'WC_LOG_DIR' ) ? trailingslashit( WC_LOG_DIR ) : trailingslashit( WP_CONTENT_DIR ) . 'uploads/wc-logs/';
if ( is_dir( $base ) ) {
	$logs = glob( $base . 'woocommerce-smart-quote-*{,.log}' );
	if ( $logs ) {
		$last = end( $logs );
		echo "LOG FILE: " . basename( $last ) . "\n";
		$lines = file( $last );
		echo implode( '', array_slice( $lines, 0, 45 ) ) . "\n";
	} else {
		echo "LOG: none yet ({$base})\n";
	}
} else {
	echo "LOG dir missing: {$base}\n";
}
