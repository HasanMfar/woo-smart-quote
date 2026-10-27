<?php
/**
 * Action Scheduler Queue & Routing Logic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSQ_Queue {

	protected static $instance = null;

	const GROUP = 'woo_smart_quote';

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	protected function __construct() {
		add_action( 'wsq_process_new_quote', array( $this, 'process_new_quote' ), 10, 1 );
		add_action( 'wsq_retry_expert_notifications', array( $this, 'retry_expert_notifications' ), 10, 1 );
	}

	/**
	 * Process a new quote: determine routes, generate PDF, send messages
	 *
	 * @param int $order_id
	 */
	public function process_new_quote( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			WSQ_Logger::warning( 'queue', 'Order not found for quote processing.', array( 'order_id' => $order_id ) );
			return;
		}

		// 1. Generate PDF
		$pdf_generator = WSQ_PDF_Generator::instance();
		$pdf_path      = $pdf_generator->generate( $order_id );

		if ( ! $pdf_path ) {
			WSQ_Logger::error( 'queue', 'PDF generation failed; messenger alerts will be sent without the attachment.', array( 'order_id' => $order_id ) );
		}

		// 2. Generate Text Message
		$message = $this->generate_message_text( $order );

		// 3. Routing (Determine which messengers and chat IDs to notify)
		$routes = $this->determine_routes( $order );

		// 4. Send Notifications
		$results = $this->dispatch_notifications( $routes, $message, $pdf_path, $order_id );

		// 5. Retry once (max 2 attempts total) when any individual
		// delivery failed. Action Scheduler re-schedules; the attempt
		// counter lives in order meta so a cron crash cannot hijack it.
		$failed = array();
		foreach ( $results as $target => $response ) {
			if ( ! self::is_delivered( $response ) ) {
				$failed[] = $target;
			}
		}

		if ( ! empty( $failed ) ) {
			$attempts = (int) $order->get_meta( '_wsq_expert_notify_attempts' );
			$attempts++;
			$order->update_meta_data( '_wsq_expert_notify_attempts', $attempts );
			$order->save();

			WSQ_Logger::error( 'queue', 'Some messenger deliveries failed; retry scheduled.', array(
				'order_id' => $order_id,
				'attempts' => $attempts,
				'failed'   => implode( ',', $failed ),
			) );

			if ( $attempts < 2 && function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action(
					time() + 300,
					'wsq_retry_expert_notifications',
					array( 'order_id' => $order_id ),
					self::GROUP
				);
			}
		}
	}

	/**
	 * Re-attempt only the failed deliveries of the initial quote notification.
	 *
	 * @param int $order_id
	 */
	public function retry_expert_notifications( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$failing = $order->get_meta( '_wsq_expert_notify_failed_targets' );
		if ( empty( $failing ) || ! is_array( $failing ) ) {
			return;
		}

		$pdf_generator = WSQ_PDF_Generator::instance();
		$pdf_path      = $pdf_generator->generate( $order_id );
		$message       = $this->generate_message_text( $order );

		$still_failing = $this->dispatch_notifications( $this->split_targets( $failing ), $message, $pdf_path, $order_id );

		$remaining = array();
		foreach ( $still_failing as $target => $response ) {
			if ( ! self::is_delivered( $response ) ) {
				$remaining[] = $target;
			}
		}

		if ( empty( $remaining ) ) {
			WSQ_Logger::info( 'queue', 'Retry succeeded; all expert deliveries delivered.', array( 'order_id' => $order_id ) );
			$order->delete_meta_data( '_wsq_expert_notify_failed_targets' );
			$order->add_order_note( __( 'تلاش مجدد اعلان کارشناسان با موفقیت انجام شد.', 'woo-smart-quote' ) );
			$order->save();
		} else {
			$failing = array_map( 'strval', $remaining );
			$order->update_meta_data( '_wsq_expert_notify_failed_targets', $failing );
			$order->save();
			WSQ_Logger::error( 'queue', 'Retry exhausted; targets still failing.', array(
				'order_id' => $order_id,
				'failed'   => implode( ',', $failing ),
			) );
		}
	}

	/**
	 * Rebuild a routes array from the stored failing-targets list.
	 * Target format: "bale:123456" or "eitaa:654321".
	 *
	 * @param array $targets
	 * @return array
	 */
	private function split_targets( $targets ) {
		$routes = array(
			'bale'  => array(),
			'eitaa' => array(),
		);
		foreach ( $targets as $target ) {
			$parts = explode( ':', (string) $target, 2 );
			if ( count( $parts ) === 2 && isset( $routes[ $parts[0] ] ) ) {
				$routes[ $parts[0] ][] = $parts[1];
			}
		}
		$routes['bale']  = array_unique( $routes['bale'] );
		$routes['eitaa'] = array_unique( $routes['eitaa'] );
		return $routes;
	}

	/**
	 * Determine which chat IDs to send the quote to based on product categories.
	 *
	 * @param WC_Order $order
	 * @return array Array of grouped routes: ['bale' => [chat_id1, chat_id2], 'eitaa' => [chat_id3]]
	 */
	private function determine_routes( $order ) {
		$routes = array(
			'bale'  => array(),
			'eitaa' => array(),
		);

		// Get global chat IDs as fallback
		$default_bale  = array_filter( array_map( 'trim', explode( "\n", get_option( 'wsq_bale_default_chat_ids', '' ) ) ) );
		$default_eitaa = array_filter( array_map( 'trim', explode( "\n", get_option( 'wsq_eitaa_default_chat_ids', '' ) ) ) );

		// Category specific routing config
		$routing_config = get_option( 'wsq_category_routes', array() ); 
		// Format: [ cat_id => [ 'bale' => '123,456', 'eitaa' => '789' ] ]

		$has_specific_route = false;

		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();
			$term_ids   = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );

			foreach ( $term_ids as $term_id ) {
				$term_id = (int) $term_id;
				if ( isset( $routing_config[ $term_id ] ) && is_array( $routing_config[ $term_id ] ) ) {
					$has_specific_route = true;

					if ( ! empty( $routing_config[ $term_id ]['bale'] ) ) {
						$ids = array_map( 'trim', explode( ',', $routing_config[ $term_id ]['bale'] ) );
						$routes['bale'] = array_merge( $routes['bale'], $ids );
					}
					
					if ( ! empty( $routing_config[ $term_id ]['eitaa'] ) ) {
						$ids = array_map( 'trim', explode( ',', $routing_config[ $term_id ]['eitaa'] ) );
						$routes['eitaa'] = array_merge( $routes['eitaa'], $ids );
					}
				}
			}
		}

		if ( ! $has_specific_route ) {
			$routes['bale']  = $default_bale;
			$routes['eitaa'] = $default_eitaa;
		} else {
			// If a category route exists but omits one messenger, fall back
			// to the defaults for that messenger only.
			if ( empty( $routes['bale'] ) ) {
				$routes['bale'] = $default_bale;
			}
			if ( empty( $routes['eitaa'] ) ) {
				$routes['eitaa'] = $default_eitaa;
			}
		}

		// Ensure uniqueness
		$routes['bale']  = array_unique( $routes['bale'] );
		$routes['eitaa'] = array_unique( $routes['eitaa'] );

		return $routes;
	}

	/**
	 * Generate text message from template
	 */
	private function generate_message_text( $order ) {
		$template = get_option( 'wsq_message_template', '' );
		if ( empty( $template ) ) {
			$template = __( "📋 استعلام جدید #{{order_number}}\n👤 {{customer_name}}\n📞 {{customer_phone}}\n🛒 {{items_count}} محصول — جمع: {{total}}\n📝 {{note}}", 'woo-smart-quote' );
		}

		$created_date = $order->get_date_created();
		$date_jalali  = WSQ_PDF_Generator::instance()->format_jalali_date( $created_date ? $created_date->getTimestamp() : time() );

		$tokens = array(
			'{{order_number}}'   => $order->get_order_number(),
			'{{customer_name}}'  => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'{{customer_phone}}' => $order->get_billing_phone(),
			'{{total}}'          => wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ),
			'{{items_count}}'    => $order->get_item_count(),
			'{{date}}'           => wc_format_datetime( $order->get_date_created() ),
			'{{date_fa}}'        => $date_jalali,
			'{{note}}'           => $order->get_customer_note(),
		);

		return str_replace( array_keys( $tokens ), array_values( $tokens ), $template );
	}

	/**
	 * Whether a messenger API response indicates a delivered message.
	 *
	 * @param mixed $response Raw API response array (or anything on failure).
	 * @return bool
	 */
	private static function is_delivered( $response ) {
		return is_array( $response ) && ! empty( $response['ok'] );
	}

	/**
	 * Dispatch notifications
	 *
	 * Each target is keyed as "{messenger}:{chat_id}" so failures can be
	 * retried individually on the next pass.
	 *
	 * @param array  $routes   ['bale' => [ids], 'eitaa' => [ids]]
	 * @param string $message  Caption/alert text.
	 * @param string|false $pdf_path Generated PDF path (false when unavailable).
	 * @param int    $order_id Order identifier.
	 * @return array Associative map target => delivered (bool).
	 */
	private function dispatch_notifications( $routes, $message, $pdf_path, $order_id ) {
		$bale_token  = get_option( 'wsq_bale_token', '' );
		$eitaa_token = get_option( 'wsq_eitaa_token', '' );

		$results = array();

		$has_bale  = ! empty( $bale_token ) && ! empty( $routes['bale'] );
		$has_eitaa = ! empty( $eitaa_token ) && ! empty( $routes['eitaa'] );

		if ( ! $has_bale && ! $has_eitaa ) {
			WSQ_Logger::warning( 'queue', 'No messenger configured for expert alerts; nothing sent.', array( 'order_id' => $order_id ) );
		} else {
			if ( $has_bale ) {
				$bale_api = new WSQ_Bale_API( $bale_token );
				foreach ( $routes['bale'] as $chat_id ) {
					$results[ 'bale:' . $chat_id ] = $bale_api->send_document( $chat_id, $pdf_path, $message );
					// Gentle spacing keeps multi-chat fan-out inside bot rate limits.
					usleep( 250000 );
				}
			}

			if ( $has_eitaa ) {
				$eitaa_api = new WSQ_Eitaa_API( $eitaa_token );
				foreach ( $routes['eitaa'] as $chat_id ) {
					$results[ 'eitaa:' . $chat_id ] = $eitaa_api->send_document( $chat_id, $pdf_path, $message );
					usleep( 250000 );
				}
			}
		}

		// Note: send_document always returns an array; `ok` flags delivery.
		$delivered = array();
		$failed    = array();
		$degraded  = array();
		foreach ( $results as $target => $response ) {
			if ( self::is_delivered( $response ) ) {
				$is_degraded = ! empty( $response['degraded'] );
				WSQ_Logger::info( 'queue', $is_degraded ? 'Expert alert delivered without the PDF attachment.' : 'Expert notification delivered.', array(
					'order_id' => $order_id,
					'target'   => $target,
				) );
				$delivered[] = $target;
				if ( $is_degraded ) {
					$degraded[] = $target;
				}
			} else {
				$response_text = '';
				if ( is_array( $response ) && isset( $response['description'] ) ) {
					$response_text = ' | ' . $response['description'];
				}
				WSQ_Logger::error( 'queue', 'Expert notification failed.' . $response_text, array(
					'order_id' => $order_id,
					'target'   => $target,
				) );
				$failed[] = $target;
			}
		}

		// Record failures on the order for the retry pass and the admin order note.
		$order = wc_get_order( $order_id );
		if ( $order ) {
			if ( ! empty( $failed ) ) {
				$order->update_meta_data( '_wsq_expert_notify_failed_targets', $failed );
			} else {
				$order->delete_meta_data( '_wsq_expert_notify_failed_targets' );
			}

			$targets_summary = array();
			$targets_summary[] = count( $delivered ) > 0
				? sprintf( __( 'تحویل موفق: %s', 'woo-smart-quote' ), implode( '، ', $delivered ) )
				: '';

			if ( ! empty( $degraded ) ) {
				$targets_summary[] = sprintf( __( 'بدون پیوست PDF (فقط متن): %s', 'woo-smart-quote' ), implode( '، ', $degraded ) );
			}
			if ( ! empty( $failed ) ) {
				$targets_summary[] = sprintf( __( 'نیازمند تلاش مجدد: %s', 'woo-smart-quote' ), implode( '، ', $failed ) );
			}
			if ( false === $pdf_path ) {
				$targets_summary[] = __( 'فایل PDF تولید نشد', 'woo-smart-quote' );
			}
			if ( empty( $results ) ) {
				$targets_summary[] = __( 'هیچ توکن یا شناسه چتی برای بله/ایتا تنظیم نشده است', 'woo-smart-quote' );
			}

			$note_template = empty( $delivered )
				? __( 'اعلان استعلام برای کارشناسان ارسال نشد. %s', 'woo-smart-quote' )
				: __( 'اعلان استعلام برای کارشناسان ارسال شد. %s', 'woo-smart-quote' );

			$order->add_order_note(
				sprintf( $note_template, implode( ' | ', array_filter( $targets_summary ) ) )
			);
			$order->save();
		}

		return $results;
	}

	/**
	 * Send test notification directly
	 *
	 * @param string $messenger 'bale' or 'eitaa'
	 * @param string $token
	 * @param string $chat_id
	 * @return array
	 */
	public function send_test_notification( $messenger, $token, $chat_id ) {
		$test_msg = sprintf(
			/* translators: 1: messenger name (Bale or Eitaa), 2: test date */
			__( "🔔 پیام آزمایشی اتصال افزونه WooCommerce Smart Quote\n\n✅ اتصال به API %1\$s با موفقیت برقرار شد!\nتاریخ تست: %2\$s", 'woo-smart-quote' ),
			'bale' === $messenger ? __( 'بله', 'woo-smart-quote' ) : __( 'ایتا', 'woo-smart-quote' ),
			WSQ_PDF_Generator::instance()->format_jalali_date( time() )
		);

		if ( 'bale' === $messenger ) {
			$api = new WSQ_Bale_API( $token );
		} else {
			$api = new WSQ_Eitaa_API( $token );
		}

		return $api->send_message( $chat_id, $test_msg );
	}
}

