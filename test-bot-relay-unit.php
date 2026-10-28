<?php
// Offline buyer-relay regressions; php -n test-bot-relay-unit.php
if ( PHP_SAPI !== 'cli' || defined( 'ABSPATH' ) ) {
	exit( 'Run standalone from PHP CLI.' );
}
define( 'ABSPATH', __DIR__ . '/' );
error_reporting( E_ALL );
set_error_handler( function ( $severity, $message, $file, $line ) {
	throw new ErrorException( $message, 0, $severity, $file, $line );
} );

function __( $text, $domain = '' ) { return $text; }
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}

class WSQ_Logger {
	public static function debug( $scope, $message, $context = array() ) {}
	public static function info( $scope, $message, $context = array() ) {}
	public static function warning( $scope, $message, $context = array() ) {}
	public static function error( $scope, $message, $context = array() ) {}
}
class WSQ_PDF_Generator {
	public static function instance() { return new self(); }
	public function generate( $order_id ) { return ''; }
}
class Fake_Updates {
	public $replies = array();
	public static function instance() { return $GLOBALS['updates']; }
	public function admin_chat_ids( $messenger ) { return array( '9001' ); }
	public function find_link( $messenger, $chat_id ) { return array( 'phone' => '09121234567' ); }
	public function reply( $messenger, $chat_id, $text, $extra = array() ) {
		$this->replies[] = array( 'chat_id' => $chat_id, 'text' => $text );
		return array( 'ok' => true );
	}
}
class WSQ_Updates extends Fake_Updates {}

require_once __DIR__ . '/woo-smart-quote/includes/class-wsq-bot.php';

function relay_assert( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}
function relay_to_admins( $bot, $text, $from_name = 'Buyer' ) {
	$method = new ReflectionMethod( $bot, 'relay_to_admins' );
	return $method->invoke( $bot, 'bale', array( 'chat_id' => '555', 'from_name' => $from_name ), $text );
}

$GLOBALS['updates'] = new Fake_Updates();
$bot                = WSQ_Bot::instance();
$tests              = array();

$tests['buyer markup reaches staff as literal text'] = function () use ( $bot ) {
	$GLOBALS['updates']->replies = array();
	relay_to_admins( $bot, '<a href="https://evil.example">تخفیف ویژه</a>' );

	$admin = $GLOBALS['updates']->replies[0]['text'];
	relay_assert( false === strpos( $admin, '<a href' ), 'Anchor tag survived the relay' );
	relay_assert( false !== strpos( $admin, '&lt;a href="https://evil.example"&gt;' ), 'Tag characters were not escaped' );
	relay_assert( false !== strpos( $admin, 'تخفیف ویژه' ), 'Message body was lost' );
};

$tests['buyer display name is escaped in the header'] = function () use ( $bot ) {
	$GLOBALS['updates']->replies = array();
	relay_to_admins( $bot, 'سلام', '<b>Mallory</b>' );

	$admin = $GLOBALS['updates']->replies[0]['text'];
	relay_assert( false === strpos( $admin, '<b>Mallory</b>' ), 'Display name markup survived' );
	relay_assert( false !== strpos( $admin, '&lt;b&gt;Mallory&lt;/b&gt;' ), 'Display name was not escaped' );
};

$tests['plain text keeps its characters'] = function () use ( $bot ) {
	$GLOBALS['updates']->replies = array();
	relay_to_admins( $bot, 'قیمت این محصول چند است؟' );

	$admin = $GLOBALS['updates']->replies[0]['text'];
	relay_assert( false !== strpos( $admin, 'قیمت این محصول چند است؟' ), 'Plain Persian text was altered' );
};

$tests['ampersands are escaped once'] = function () use ( $bot ) {
	$GLOBALS['updates']->replies = array();
	relay_to_admins( $bot, 'سلام & خداحافظ' );

	$admin = $GLOBALS['updates']->replies[0]['text'];
	relay_assert( false !== strpos( $admin, 'سلام &amp; خداحافظ' ), 'Ampersand was not escaped' );
	relay_assert( false === strpos( $admin, '&amp;amp;' ), 'Ampersand was escaped twice' );
};

$tests['buyer still receives the acknowledgement'] = function () use ( $bot ) {
	$GLOBALS['updates']->replies = array();
	relay_to_admins( $bot, '<script>alert(1)</script>' );

	relay_assert( count( $GLOBALS['updates']->replies ) >= 2, 'Buyer acknowledgement missing' );
	$buyer = end( $GLOBALS['updates']->replies );
	relay_assert( '555' === $buyer['chat_id'], 'Acknowledgement went to the wrong chat' );
	relay_assert( false === strpos( $buyer['text'], '<script>' ), 'Acknowledgement echoed buyer markup' );
};

$passed = 0;
foreach ( $tests as $name => $test ) {
	try {
		$test();
		$passed++;
	} catch ( Throwable $error ) {
		fwrite( STDERR, 'FAIL: ' . $name . ' — ' . $error->getMessage() . "\n" );
	}
}

printf( "%d/%d buyer relay tests passed\n", $passed, count( $tests ) );
exit( $passed === count( $tests ) ? 0 : 1 );
