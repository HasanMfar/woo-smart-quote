<?php
// Test the inbound (webhook + polling) pipeline against the mock API.
// Usage: wp eval-file wsq-test-in.php <mode>
//   modes: info | inject | poll | webhook | reset

$mode = isset( $args[0] ) ? $args[0] : 'info';

// Point both messengers at the local mock server.
add_filter( 'wsq_bale_base_url', function () {
	return 'http://127.0.0.1:9999/bot';
} );
add_filter( 'wsq_eitaa_base_url', function () {
	return 'http://127.0.0.1:9999/api/';
} );

$mock = 'http://127.0.0.1:9999';
$u    = WSQ_Updates::instance();

echo "MODE=$mode\n";

if ( 'info' === $mode ) {
	echo "webhook_url=" . $u->webhook_url() . "\n";
	echo "secret=" . substr( get_option( 'wsq_webhook_secret', '' ), 0, 8 ) . "...\n";
	echo "bale_token=" . ( get_option( 'wsq_bale_token' ) ? 'set' : 'EMPTY' ) . "\n";
	echo "eitaa_token=" . ( get_option( 'wsq_eitaa_token' ) ? 'set' : 'EMPTY' ) . "\n";
	echo "bale_admin=" . var_export( $u->admin_chat_ids( 'bale' ), true ) . "\n";
	echo "poll_off=" . var_export( get_option( 'wsq_poll_off', array() ), true ) . "\n";
	global $wpdb;
	$t = WSQ_Updates::table();
	$exists = $wpdb->get_var( "SHOW TABLES LIKE '{$t}'" );
	echo "table_exists=" . ( $exists ? 'yes' : 'NO' ) . "\n";
	if ( $exists ) {
		$rows = $wpdb->get_results( "SELECT id,messenger,direction,chat_id,status,LEFT(text,40) AS t FROM {$t} ORDER BY id DESC LIMIT 10", ARRAY_A );
		echo "recent_rows=" . count( $rows ) . "\n";
		foreach ( $rows as $r ) {
			echo '  #' . $r['id'] . " {$r['messenger']}/{$r['direction']}/{$r['chat_id']}/{$r['status']}: {$r['t']}\n";
		}
	}
	return;
}

if ( 'inject' === $mode ) {
	// Queue a buyer /start via the mock's inject endpoint, then poll.
	$payload = array(
		'update_id' => 5001,
		'message'   => array(
			'message_id' => 77,
			'from'       => array( 'id' => 424242, 'is_bot' => false, 'first_name' => 'مشتری نمونه', 'username' => 'demo_buyer' ),
			'chat'       => array( 'id' => 424242, 'type' => 'private' ),
			'date'       => time(),
			'text'       => '/start',
		),
	);
	$res = wp_remote_post( $mock . '/mock-inject', array( 'body' => wp_json_encode( $payload ), 'timeout' => 15, 'headers' => array( 'Content-Type' => 'application/json' ) ) );
	echo "inject=" . ( is_wp_error( $res ) ? $res->get_error_message() : wp_remote_retrieve_body( $res ) ) . "\n";
	return;
}

if ( 'inject-contact' === $mode ) {
	$payload = array(
		'update_id' => 5002,
		'message'   => array(
			'message_id' => 78,
			'from'       => array( 'id' => 424242, 'is_bot' => false, 'first_name' => 'مشتری نمونه' ),
			'chat'       => array( 'id' => 424242, 'type' => 'private' ),
			'date'       => time(),
			'contact'    => array( 'phone_number' => '+989121112233', 'first_name' => 'مشتری نمونه', 'user_id' => 424242 ),
		),
	);
	$res = wp_remote_post( $mock . '/mock-inject', array( 'body' => wp_json_encode( $payload ), 'timeout' => 15, 'headers' => array( 'Content-Type' => 'application/json' ) ) );
	echo "inject=" . ( is_wp_error( $res ) ? $res->get_error_message() : wp_remote_retrieve_body( $res ) ) . "\n";
	return;
}

if ( 'poll' === $mode ) {
	// Poll bale only (webhook flag cleared so polling is allowed).
	delete_option( 'wsq_bale_webhook_url' );
	$count = $u->poll_once( 'bale' );
	echo "polled_events=$count\n";
	return;
}

if ( 'poll-eitaa' === $mode ) {
	$count = $u->poll_once( 'eitaa' );
	echo "polled_events=$count\n";
	echo "poll_off=" . var_export( get_option( 'wsq_poll_off', array() ), true ) . "\n";
	return;
}

if ( 'webhook' === $mode ) {
	// Invoke the REST handler directly (web server may be down locally).
	$secret  = $u->webhook_secret();
	$payload = array(
		'update_id' => 6001,
		'message'   => array(
			'message_id' => 90,
			'from'       => array( 'id' => 515151, 'is_bot' => false, 'first_name' => 'خریدار وبهوک' ),
			'chat'       => array( 'id' => 515151, 'type' => 'private' ),
			'date'       => time(),
			'text'       => 'سلام، وضعیت استعلامم چیست؟',
		),
	);

	$mk = function ( $given_secret ) use ( $payload ) {
		$r = new WP_REST_Request( 'POST', '/woo-smart-quote/v1/bale-hook' );
		$r->set_param( 's', $given_secret );
		$r->set_header( 'content-type', 'application/json' );
		$r->set_body( wp_json_encode( $payload ) );
		return $r;
	};

	$good = $u->rest_webhook( $mk( $secret ) );
	echo 'good_status=' . $good->get_status() . "\n";

	$bad = $u->rest_webhook( $mk( 'WRONGSECRET' ) );
	echo 'bad_status=' . $bad->get_status() . "\n";
	return;
}

if ( 'inject-admin' === $mode ) {
	$text     = isset( $args[1] ) ? $args[1] : '/pending';
	$payload = array(
		'update_id' => 7000,
		'message'   => array(
			'message_id' => 95,
			'from'       => array( 'id' => 900001, 'is_bot' => false, 'first_name' => 'مدیر' ),
			'chat'       => array( 'id' => 900001, 'type' => 'private' ),
			'date'       => time(),
			'text'       => $text,
		),
	);
	$res = wp_remote_post( $mock . '/mock-inject', array( 'body' => wp_json_encode( $payload ), 'timeout' => 15, 'headers' => array( 'Content-Type' => 'application/json' ) ) );
	echo 'inject=' . ( is_wp_error( $res ) ? $res->get_error_message() : wp_remote_retrieve_body( $res ) ) . "\n";
	return;
}

if ( 'links' === $mode ) {
	print_r( get_option( 'wsq_chat_links' ) );
	return;
}

if ( 'mkorder' === $mode ) {
	// Create a quote-sent order billed to the contact-linked buyer.
	$order = wc_create_order( array( 'status' => 'quote-sent' ) );
	$order->set_billing_first_name( 'مشتری' );
	$order->set_billing_last_name( 'نمونه' );
	$order->set_billing_phone( '+989121112233' );
	$order->set_billing_email( 'buyer@example.com' );
	$order->set_total( 1250000 );
	$order->save();
	echo 'order_number=' . $order->get_order_number() . ' id=' . $order->get_id() . "\n";
	return;
}

if ( 'inject-order' === $mode ) {
	$number  = isset( $args[1] ) ? $args[1] : '123';
	$payload = array(
		'update_id' => 5003,
		'message'   => array(
			'message_id' => 80,
			'from'       => array( 'id' => 424242, 'is_bot' => false, 'first_name' => 'مشتری نمونه' ),
			'chat'       => array( 'id' => 424242, 'type' => 'private' ),
			'date'       => time(),
			'text'       => $number,
		),
	);
	$res = wp_remote_post( $mock . '/mock-inject', array( 'body' => wp_json_encode( $payload ), 'timeout' => 15, 'headers' => array( 'Content-Type' => 'application/json' ) ) );
	echo 'inject=' . ( is_wp_error( $res ) ? $res->get_error_message() : wp_remote_retrieve_body( $res ) ) . "\n";
	return;
}

if ( 'inject-support' === $mode ) {
	$payload = array(
		'update_id' => 5004,
		'message'   => array(
			'message_id' => 81,
			'from'       => array( 'id' => 424242, 'is_bot' => false, 'first_name' => 'مشتری نمونه' ),
			'chat'       => array( 'id' => 424242, 'type' => 'private' ),
			'date'       => time(),
			'text'       => 'سلام، درباره زمان ارسال سؤال داشتم',
		),
	);
	$res = wp_remote_post( $mock . '/mock-inject', array( 'body' => wp_json_encode( $payload ), 'timeout' => 15, 'headers' => array( 'Content-Type' => 'application/json' ) ) );
	echo 'inject=' . ( is_wp_error( $res ) ? $res->get_error_message() : wp_remote_retrieve_body( $res ) ) . "\n";
	return;
}

if ( 'reset' === $mode ) {
	global $wpdb;
	$t = WSQ_Updates::table();
	$wpdb->query( "TRUNCATE TABLE {$t}" );
	delete_option( 'wsq_chat_links' );
	delete_option( 'wsq_poll_off' );
	delete_option( 'wsq_bale_offset' );
	delete_option( 'wsq_eitaa_offset' );
	delete_option( 'wsq_bale_webhook_url' );
	echo "reset done\n";
	return;
}

echo "unknown mode\n";
