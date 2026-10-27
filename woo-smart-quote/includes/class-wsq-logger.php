<?php
/**
 * Central logging facility.
 *
 * Uses WooCommerce's logger when available (visible under
 * WooCommerce > Status > Logs with source "woocommerce-smart-quote");
 * falls back to WP_DEBUG / error_log otherwise.
 *
 * All messages are prefixed with a context tag so they can be filtered:
 * WSQ_Logger::info( 'queue', 'message', [ 'order' => $id ] );
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSQ_Logger {

	const SOURCE = 'woocommerce-smart-quote';

	/** @var WC_Logger|null */
	private static $logger = null;

	/**
	 * Debug-level: verbose diagnostics.
	 *
	 * @param string $context Logical area (queue, pdf, bale, eitaa, buyer, frontend).
	 * @param string $message Message or description.
	 * @param array  $ctx     Extra key/value pairs appended to the message.
	 */
	public static function debug( $context, $message, $ctx = array() ) {
		self::add( 'debug', $context, $message, $ctx );
	}

	/**
	 * Info-level: successful external interactions worth recording.
	 */
	public static function info( $context, $message, $ctx = array() ) {
		self::add( 'info', $context, $message, $ctx );
	}

	/**
	 * Warning-level: recoverable problems.
	 */
	public static function warning( $context, $message, $ctx = array() ) {
		self::add( 'warning', $context, $message, $ctx );
	}

	/**
	 * Error-level: failures needing attention.
	 */
	public static function error( $context, $message, $ctx = array() ) {
		self::add( 'error', $context, $message, $ctx );
	}

	/**
	 * Append a context array to a message as "key=value" pairs and store it.
	 *
	 * @param string $level   One of: log|error|warning|info|debug.
	 * @param string $context Logical area tag.
	 * @param string $message Message text.
	 * @param array  $ctx     Optional scalar context values.
	 */
	private static function add( $level, $context, $message, $ctx = array() ) {
		$line = sprintf( '[%s] %s', $context, $message );

		if ( ! empty( $ctx ) ) {
			$pairs = array();
			foreach ( $ctx as $key => $value ) {
				if ( is_array( $value ) || is_object( $value ) ) {
					$value = wp_json_encode( $value );
				}
				$pairs[] = sprintf( '%s=%s', $key, $value );
			}
			$line .= ' | ' . implode( ' | ', $pairs );
		}

		if ( function_exists( 'wc_get_logger' ) ) {
			if ( null === self::$logger ) {
				self::$logger = wc_get_logger();
			}
			self::$logger->log( $level, $line, array( 'source' => self::SOURCE ) );
			return;
		}

		// No WooCommerce logger: only surface warnings and above when debugging.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && in_array( $level, array( 'warning', 'error' ), true ) ) {
			error_log( '[WSQ] ' . $line );
		}
	}
}
