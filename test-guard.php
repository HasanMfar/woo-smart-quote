<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 1. Duplicate guard: non-forced dispatch must skip when flag exists.
$before_mail = did_action( 'wp_mail_succeeded' );
$results = WSQ_Buyer_Notifier::instance()->dispatch_to_buyer( 25, false );
echo 'guard result: ' . wp_json_encode( $results ) . ' | mail hooks: ' . ( did_action( 'wp_mail_succeeded' ) - $before_mail ) . "\n";

// 2. Manual resend with force: must actually send (email again).
$results2 = WSQ_Buyer_Notifier::instance()->dispatch_to_buyer( 25, true );
echo 'forced result: ' . wp_json_encode( $results2 ) . ' | mail hooks: ' . ( did_action( 'wp_mail_succeeded' ) - $before_mail ) . "\n";
