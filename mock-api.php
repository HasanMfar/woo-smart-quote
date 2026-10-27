<?php
/**
 * Strict mock of the Bale + Eitaayar bot APIs for integration testing.
 *
 * Behaves enough like the real services to exercise the plugin's error
 * handling:
 *  - /bot{token}/sendMessage|sendDocument   (Bale / Telegram-compatible)
 *  - /api/{token}/sendMessage|sendFile      (Eitaayar; sendDocument is 404)
 *  - HTML parse errors are rejected with 400 like Telegram/Bale do.
 *
 * Every request is captured under mock-captures/ for inspection.
 */
$path = urldecode( (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) );
$mock_dir = __DIR__ . '/mock-captures';
if ( ! is_dir( $mock_dir ) ) {
	mkdir( $mock_dir, 0777, true );
}

$segments = array_values( array_filter( explode( '/', $path ), 'strlen' ) );
$method   = end( $segments );
$is_eitaa = ( '/' === substr( $path, 0, 5 ) || false !== strpos( $path, '/api/' ) );

$stamp = date( 'His' ) . '-' . substr( (string) round( microtime( true ) * 1000 ), -6 );
$meta  = strtoupper( $_SERVER['REQUEST_METHOD'] ) . ' ' . $path . "\n";
foreach ( $_SERVER as $k => $v ) {
	if ( 0 === strpos( $k, 'HTTP_' ) || 'CONTENT_TYPE' === $k || 'CONTENT_LENGTH' === $k ) {
		$meta .= $k . ': ' . $v . "\n";
	}
}
$meta .= "--\n";

$files = array();
if ( ! empty( $_FILES ) ) {
	$meta .= '_POST: ' . json_encode( $_POST, JSON_UNESCAPED_UNICODE ) . "\n";
	foreach ( $_FILES as $field => $f ) {
		$meta .= "FILE[{$field}]: name={$f['name']} size={$f['size']} type={$f['type']} error={$f['error']}\n";
		$dest  = sprintf( '%s/%s-upload.bin', $mock_dir, $stamp );
		if ( is_uploaded_file( $f['tmp_name'] ) ) {
			move_uploaded_file( $f['tmp_name'], $dest );
		}
		$files[ $field ] = $f;
	}
} else {
	$meta .= 'BODY: ' . (string) file_get_contents( 'php://input' ) . "\n";
}
file_put_contents( sprintf( '%s/%s.req', $mock_dir, $stamp ), $meta );

// Chat id 888 simulates a recipient that refuses documents but accepts text.
$early_method = $method;
if ( isset( $_POST['chat_id'] ) && '888' === (string) $_POST['chat_id'] && in_array( $early_method, array( 'sendDocument', 'sendFile' ), true ) ) {
	http_response_code( 400 );
	header( 'Content-Type: application/json' );
	echo json_encode( array( 'ok' => false, 'error_code' => 400, 'description' => 'Bad Request: file type not supported (simulated)' ) );
	exit;
}

// Chat id 999 simulates a permanently failing recipient.
if ( isset( $_POST['chat_id'] ) && '999' === (string) $_POST['chat_id'] ) {
	http_response_code( 500 );
	header( 'Content-Type: application/json' );
	echo json_encode( array( 'ok' => false, 'error_code' => 500, 'description' => 'Internal Server Error (simulated)' ) );
	exit;
}

$reply = function ( $code, $payload ) {
	http_response_code( $code );
	header( 'Content-Type: application/json' );
	echo json_encode( $payload, JSON_UNESCAPED_UNICODE );
	exit;
};

// --- Buyer channels (IPPanel, Kavenegar, Safir Bale) -----------------------
$raw_body = (string) file_get_contents( 'php://input' );

if ( false !== strpos( $path, 'sms/pattern' ) ) {
	$data = json_decode( $raw_body, true );
	if ( ! is_array( $data ) || empty( $data['code'] ) || empty( $data['recipient'] ) || ! isset( $data['variable'] ) ) {
		$reply( 400, array( 'status' => 'error', 'message' => 'invalid pattern payload' ) );
	}
	if ( substr( (string) $data['recipient'], -4 ) === '0000' ) {
		$reply( 500, array( 'status' => 'error', 'message' => 'gateway failure (simulated)' ) );
	}
	$reply( 200, array( 'status' => 'ok', 'data' => array( 'message_id' => rand( 100, 999 ) ) ) );
}

if ( false !== strpos( $path, 'verify/lookup' ) ) {
	parse_str( (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_QUERY ), $q );
	if ( empty( $q['receptor'] ) || empty( $q['template'] ) ) {
		$reply( 400, array( 'return' => array( 'status' => 400, 'message' => 'missing receptor/template' ) ) );
	}
	$reply( 200, array( 'return' => array( 'status' => 200, 'message' => 'ok' ) ) );
}

if ( false !== strpos( $path, 'safir/send_message' ) ) {
	$data = json_decode( $raw_body, true );
	$auth = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
	if ( 0 !== strpos( $auth, 'Bearer ' ) || ! is_array( $data ) || empty( $data['receiver_phone'] ) || empty( $data['text'] ) ) {
		$reply( 401, array( 'ok' => false, 'description' => 'unauthorized or invalid payload' ) );
	}
	$reply( 200, array( 'ok' => true, 'result' => 'success' ) );
}

$allowed = $is_eitaa
	? array( 'sendMessage', 'sendFile', 'getMe', 'getUpdates' )
	: array( 'sendMessage', 'sendDocument', 'getMe', 'getUpdates', 'setWebhook', 'deleteWebhook' );

// --- Webhook / polling simulation -----------------------------------------
// A queue file holds updates waiting to be delivered by getUpdates.
$queue_file = __DIR__ . '/mock-updates-queue.json';

if ( 'getUpdates' === $method ) {
	// A marker file simulates a panel that does not support getUpdates.
	if ( file_exists( __DIR__ . '/mock-no-getupdates' ) ) {
		$reply( 404, array( 'ok' => false, 'error_code' => 404, 'description' => 'Not Found: unknown method getUpdates' ) );
	}
	$queued = array();
	if ( file_exists( $queue_file ) ) {
		$queued = json_decode( (string) file_get_contents( $queue_file ), true );
		if ( ! is_array( $queued ) ) {
			$queued = array();
		}
		// Empty the queue so the same updates are not delivered twice.
		file_put_contents( $queue_file, json_encode( array() ) );
	}
	$reply( 200, array( 'ok' => true, 'result' => $queued ) );
}

if ( 'setWebhook' === $method ) {
	$reply( 200, array( 'ok' => true, 'result' => true ) );
}

if ( 'deleteWebhook' === $method ) {
	$reply( 200, array( 'ok' => true, 'result' => true ) );
}

// Test helper: inject updates into the polling queue.
if ( false !== strpos( $path, 'mock-inject' ) ) {
	$data     = json_decode( $raw_body, true );
	$existing = array();
	if ( file_exists( $queue_file ) ) {
		$existing = json_decode( (string) file_get_contents( $queue_file ), true );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}
	}
	$existing[] = is_array( $data ) ? $data : array();
	file_put_contents( $queue_file, json_encode( $existing ) );
	$reply( 200, array( 'ok' => true, 'queued' => count( $existing ) ) );
}

if ( ! in_array( $method, $allowed, true ) ) {
	$reply( 404, array( 'ok' => false, 'error_code' => 404, 'description' => 'Not Found: unsupported method ' . $method ) );
}

$params = array_merge( $_POST, $_GET );

// Reject malformed HTML entities the way Telegram/Bale do.
if ( isset( $params['parse_mode'] ) && 'HTML' === $params['parse_mode'] ) {
	$text = isset( $params['text'] ) ? (string) $params['text'] : ( isset( $params['caption'] ) ? (string) $params['caption'] : '' );
	$unbalanced = preg_match( '/<b>/i', $text ) && ! preg_match( '/<\/b>/i', $text );
	if ( $unbalanced || preg_match( '/<(?![a-z\/]|\s)/i', $text ) || preg_match( '/&(?!#?[a-z0-9]+;)/i', $text ) ) {
		$reply( 400, array( 'ok' => false, 'error_code' => 400, 'description' => "Bad Request: can't parse entities: unsupported html entity" ) );
	}
}

if ( 'sendDocument' === $method && empty( $files['document'] ) ) {
	$reply( 400, array( 'ok' => false, 'error_code' => 400, 'description' => 'Bad Request: document is required' ) );
}

if ( 'sendFile' === $method && empty( $files['file'] ) ) {
	$reply( 400, array( 'ok' => false, 'error_code' => 400, 'description' => 'Bad Request: file is required' ) );
}

$reply( 200, array( 'ok' => true, 'result' => array( 'message_id' => rand( 1000, 9999 ) ) ) );
