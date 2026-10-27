<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
echo 'http_proxy=' . ( getenv( 'http_proxy' ) ?: '(none)' ) . "\n";
echo 'https_proxy=' . ( getenv( 'https_proxy' ) ?: '(none)' ) . "\n";
echo 'HTTP_PROXY=' . ( getenv( 'HTTP_PROXY' ) ?: '(none)' ) . "\n";
echo 'ALL_PROXY=' . ( getenv( 'ALL_PROXY' ) ?: '(none)' ) . "\n";

$ch = curl_init( 'http://127.0.0.1:9999/botXXX/sendMessage' );
curl_setopt( $ch, CURLOPT_POST, 1 );
curl_setopt( $ch, CURLOPT_POSTFIELDS, 'chat_id=1&text=t' );
curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
curl_setopt( $ch, CURLOPT_TIMEOUT, 6 );
$out = curl_exec( $ch );
echo 'curl raw: ' . var_export( $out, true ) . "\n";
echo 'curl err: ' . curl_error( $ch ) . ' (' . curl_errno( $ch ) . ")\n";
curl_close( $ch );

// Retry explicitly bypassing proxy
$ch = curl_init( 'http://127.0.0.1:9999/botXXX/sendMessage' );
curl_setopt( $ch, CURLOPT_POST, 1 );
curl_setopt( $ch, CURLOPT_POSTFIELDS, 'chat_id=1&text=t' );
curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
curl_setopt( $ch, CURLOPT_TIMEOUT, 6 );
curl_setopt( $ch, CURLOPT_NOPROXY, '*' );
curl_setopt( $ch, CURLOPT_PROXY, '' );
$out = curl_exec( $ch );
echo 'curl noproxy: ' . var_export( $out, true ) . "\n";
echo 'curl err: ' . curl_error( $ch ) . ' (' . curl_errno( $ch ) . ")\n";
curl_close( $ch );
