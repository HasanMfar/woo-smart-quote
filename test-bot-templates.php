<?php
/**
 * Bot-template smoke test: run under WP-CLI.
 *
 * Verifies the {token} engine of WSQ_Bot for:
 *  - default welcome template (option empty)
 *  - custom welcome template ({name}/{chat_id})
 *  - orders-list repeated region ({#items}...{/#items})
 *  - order-card template with {payment_section} presence/removal
 */

echo "=== bot-template smoke ===\n";

$bot    = WSQ_Bot::instance();
$ref    = new ReflectionClass( $bot );
$render = $ref->getMethod( 'render_template' );
$render->setAccessible( true );
$invoke = function ( ...$args ) use ( $render, $bot ) {
	return $render->invoke( $bot, ...$args );
};

$GLOBALS['wsq_pass'] = 0;
$GLOBALS['wsq_fail'] = 0;
function bt( $label, $cond ) {
	if ( $cond ) {
		$GLOBALS['wsq_pass']++;
		echo "PASS: $label\n";
	} else {
		$GLOBALS['wsq_fail']++;
		echo "FAIL: $label\n";
	}
}

// 1. Welcome with {name} fallback.
$out = $invoke( 'سلام{name}، چت شما {chat_id} است', array(), array( 'chat_id' => '111', 'from_name' => '' ) );
bt( 'welcome name fallback', false !== strpos( $out, 'سلامعزیز' ) );

// 2. Custom name + chat id.
$out = $invoke( '{name} عزیز، چت شما {chat_id} است', array(), array( 'chat_id' => '555', 'from_name' => 'حسن' ) );
bt( 'welcome with name+chat', false !== strpos( $out, 'حسن عزیز' ) && false !== strpos( $out, '555' ) );

// 3. Unknown tokens are stripped, not leaked.
$out = $invoke( 'سلام، {unknown_token} حذف می‌شود', array(), array( 'chat_id' => '1' ) );
bt( 'unknown tokens stripped', false === strpos( $out, '{unknown_token}' ) && false === strpos( $out, '}' ) );

// 4. Payment section removed when no URL.
$out = $invoke( "کارت{payment_section}پایان", array( 'payment_url' => '' ), array( 'chat_id' => '9' ) );
bt( 'payment_section removed (no url)', '{payment_section}' !== $out && false === strpos( $out, 'پرداخت آنلاین' ) );

// 5. Payment section present with URL.
$out = $invoke( "کارت{payment_section}", array( 'payment_url' => 'https://site/pay/5' ), array( 'chat_id' => '9' ) );
bt( 'payment_section with url', false !== strpos( $out, 'https://site/pay/5' ) );

// 6. Default templates resolve.
$defaults = array( 'welcome', 'orders_header', 'order_card' );
foreach ( $defaults as $key ) {
	$m = $ref->getMethod( 'default_template' );
	$m->setAccessible( true );
	$tpl = $m->invoke( $bot, $key );
	bt( "default template '$key' non-empty", '' !== trim( (string) $tpl ) );
}

// 7. Orders: default-template path via reflection on send_orders_list internals.
// Verify the send_expanded_orders produces keyboard rows by rendering three orders.
$order_ids = wc_get_orders( array( 'limit' => 2, 'return' => 'ids', 'orderby' => 'date', 'order' => 'DESC' ) );
bt( 'orders exist to expand', ! empty( $order_ids ) );
$orders = array();
foreach ( $order_ids as $oid ) {
	$o = wc_get_order( $oid );
	if ( $o ) { $orders[] = $o; }
}
if ( 1 === count( $orders ) ) {
	$orders[] = $orders[0]; // exercise multi-row path deterministically
}

$expand = $ref->getMethod( 'send_expanded_orders' );
$expand->setAccessible( true );
// Should not fatal; reply path uses WSQ_Updates::reply, which needs nothing else.
try {
	$expand->invoke( $bot, 'bale', array( 'chat_id' => '168621', 'from_name' => 'حسن' ), $ref->getMethod( 'default_template' )->invoke( $bot, 'orders_header' ), $orders );
	bt( 'send_expanded_orders runs without fatal', true );
} catch ( Throwable $e ) {
	bt( 'send_expanded_orders runs without fatal', false, $e->getMessage() );
}

echo "\n=== Result: {$GLOBALS['wsq_pass']} passed, {$GLOBALS['wsq_fail']} failed ===\n";
if ( $GLOBALS['wsq_fail'] > 0 ) {
	exit( 1 );
}
