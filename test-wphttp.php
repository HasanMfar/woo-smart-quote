<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$url = 'http://127.0.0.1:9999/botXXX/sendMessage';

$r1 = wp_remote_post( $url, array( 'body' => array( 'chat_id' => '1', 'text' => 'small' ), 'timeout' => 12 ) );
echo 'r1: ' . ( is_wp_error( $r1 ) ? 'ERR ' . $r1->get_error_message() : wp_remote_retrieve_response_code( $r1 ) . ' ' . wp_remote_retrieve_body( $r1 ) ) . "\n";

// Multipart-ish large body
$boundary = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
$part     = "--{$boundary}\r\nContent-Disposition: form-data; name=\"chat_id\"\r\n\r\n555000\r\n";
$part    .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"document\"; filename=\"x.pdf\"\r\nContent-Type: application/pdf\r\n\r\n";
$part    .= str_repeat( 'PDFDATA', 20000 ) . "\r\n";
$part    .= "--{$boundary}--\r\n";

$r2 = wp_remote_post( $url, array(
	'headers' => array( 'Content-Type' => 'multipart/form-data; boundary=' . $boundary ),
	'body'    => $part,
	'timeout' => 20,
) );
echo 'r2: ' . ( is_wp_error( $r2 ) ? 'ERR ' . $r2->get_error_message() : wp_remote_retrieve_response_code( $r2 ) . ' ' . substr( wp_remote_retrieve_body( $r2 ), 0, 120 ) ) . "\n";

$r3 = wp_remote_post( $url, array( 'body' => array( 'chat_id' => '1', 'text' => 'third' ), 'timeout' => 12 ) );
echo 'r3: ' . ( is_wp_error( $r3 ) ? 'ERR ' . $r3->get_error_message() : wp_remote_retrieve_response_code( $r3 ) . ' ' . wp_remote_retrieve_body( $r3 ) ) . "\n";
