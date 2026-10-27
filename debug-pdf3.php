<?php
$order_id = ( wc_get_orders( array( 'limit' => 1, 'return' => 'ids' ) ?: array() ) )[0] ?? null;
try {
	$p = WSQ_PDF_Generator::instance()->generate( (int) $order_id );
	echo "PATH: " . ( $p ? basename( $p ) : 'FALSE' ) . "\n";
} catch ( \Throwable $e ) {
	echo "THROW: " . get_class( $e ) . " :: " . $e->getMessage() . " @ " . $e->getFile() . ":" . $e->getLine() . "\n";
}
