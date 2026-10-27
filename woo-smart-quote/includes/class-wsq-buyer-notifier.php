<?php
/**
 * Handle Notification Dispatching to Buyer (Customer)
 *
 * Supports:
 * 1. Automatic dispatch upon WooCommerce order status change to 'wc-quote-sent'
 * 2. Mobile number extraction from quote request form (_billing_phone / _wsq_customer_phone)
 * 3. SMS Gateways (FarazSMS/IPPanel with native Bale & Eitaa routing, Kavenegar Pattern, Safir Bale)
 * 4. Official Email with PDF invoice attachment
 * 5. Quick-action direct chat links for Bale, Eitaa, WhatsApp in WooCommerce Order admin
 * 6. Push of the priced PDF + payment link through the Bale or Eitaa bot to
 *    the chat the buyer linked with their phone number
 *
 * Reliability notes:
 * - The automated flow is queued through Action Scheduler so the admin status
 *   change request is never blocked by PDF generation / external HTTP calls.
 * - A `_wsq_proposal_sent` meta flag prevents duplicate re-dispatch when the
 *   order status is toggled back and forth; the manual resend button passes
 *   a force flag and ignores it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSQ_Buyer_Notifier {

	protected static $instance = null;

	const SENT_FLAG = '_wsq_proposal_sent';

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	protected function __construct() {
		// Trigger when order status changes to quote-sent (async queueing).
		add_action( 'woocommerce_order_status_quote-sent', array( $this, 'queue_dispatch' ), 10, 1 );

		// Custom trigger hook (public API for other plugins/themes).
		add_action( 'wsq_dispatch_proposal_to_buyer', array( $this, 'queue_dispatch' ), 10, 1 );

		// Actually process the proposal (runs inside Action Scheduler, or
		// synchronously as a fallback when Action Scheduler is unavailable).
		add_action( 'wsq_process_buyer_proposal', array( $this, 'dispatch_to_buyer' ), 10, 2 );

		// AJAX manual trigger from order metabox.
		add_action( 'wp_ajax_wsq_ajax_resend_buyer_proposal', array( $this, 'ajax_resend_buyer_proposal' ) );
	}

	/**
	 * Queue a proposal dispatch as a background action.
	 *
	 * @param int $order_id
	 */
	public function queue_dispatch( $order_id ) {
		// Respect the master auto-dispatch switch set in the admin "buyer" tab.
		if ( 'yes' !== get_option( 'wsq_buyer_sms_enabled', 'no' ) ) {
			return;
		}

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action(
				'wsq_process_buyer_proposal',
				array( 'order_id' => (int) $order_id, 'force' => false ),
				'woo_smart_quote'
			);
			WSQ_Logger::debug( 'buyer', 'Proposal dispatch queued.', array( 'order_id' => $order_id ) );
			return;
		}

		// Fallback without Action Scheduler.
		$this->dispatch_to_buyer( (int) $order_id, false );
	}

	/**
	 * Main dispatch handler when proposal is ready.
	 *
	 * @param int  $order_id
	 * @param bool $force    True bypasses the duplicate-send guard (manual resend).
	 * @return array Channel results: email => bool, sms => bool, bale => bool, eitaa => bool.
	 */
	public function dispatch_to_buyer( $order_id, $force = false ) {
		$force = filter_var( $force, FILTER_VALIDATE_BOOLEAN );

		// Guard against duplicate proposals when the order status is toggled.
		if ( ! $force && $order_id ) {
			$order_for_guard = wc_get_order( $order_id );
			if ( $order_for_guard && '' !== $order_for_guard->get_meta( self::SENT_FLAG ) ) {
				WSQ_Logger::info( 'buyer', 'Proposal already sent previously; skipping.', array( 'order_id' => $order_id ) );
				return array();
			}
		}

		// Respect the master auto-dispatch switch for automatic events; explicit manual triggers ($force=true) bypass this.
		if ( ! $force && 'yes' !== get_option( 'wsq_buyer_sms_enabled', 'no' ) ) {
			return array();
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			WSQ_Logger::warning( 'buyer', 'Order not found for buyer proposal.', array( 'order_id' => $order_id ) );
			return array();
		}

		// 1. Extract customer phone number from billing or form meta
		$phone = $this->get_customer_phone( $order );
		$name  = $order->get_billing_first_name();
		if ( empty( $name ) ) {
			$name = $order->get_formatted_billing_full_name();
		}
		if ( empty( $name ) ) {
			$name = __( 'مشتری گرامی', 'woo-smart-quote' );
		}

		$payment_url = $order->get_checkout_payment_url();
		$total       = wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) );
		$validity    = $order->get_meta( '_wsq_quote_validity' );
		if ( empty( $validity ) ) {
			$validity = __( '۲۴ ساعت کاری', 'woo-smart-quote' );
		}

		// 2. Ensure official PDF is generated
		$pdf_generator = WSQ_PDF_Generator::instance();
		$pdf_path      = $pdf_generator->generate( $order_id );

		if ( ! $pdf_path ) {
			WSQ_Logger::error( 'buyer', 'PDF generation failed; proposal continues without attachment.', array( 'order_id' => $order_id ) );
		}

		// 3. Build text message for messengers / SMS
		$message = $this->build_proposal_text( $order, $name, $total, $validity, $payment_url );

		// 4. Send official WooCommerce Email with PDF attachment
		$email_sent = $this->send_proposal_email( $order, $message, $pdf_path );

		// 5. Send SMS / Messenger notification (FarazSMS / IPPanel / Kavenegar)
		$sms_sent = false;
		if ( ! empty( $phone ) ) {
			$sms_sent = $this->send_sms_or_messenger( $phone, $order, $name, $total, $validity, $payment_url );
		} else {
			WSQ_Logger::warning( 'buyer', 'No buyer phone on order; SMS channel skipped.', array( 'order_id' => $order_id ) );
		}

		// 6. Push to the buyer's linked bot chats. Buyers who never shared an
		// email still get the PDF and a tappable payment link inside the
		// messenger they already use.
		$bale_sent  = $this->send_via_bot( 'bale', $order, $message, $pdf_path, $phone );
		$eitaa_sent = $this->send_via_bot( 'eitaa', $order, $message, $pdf_path, $phone );

		$results = array(
			'email' => (bool) $email_sent,
			'sms'   => (bool) $sms_sent,
			'bale'  => (bool) $bale_sent,
			'eitaa' => (bool) $eitaa_sent,
		);

		// Only flag as sent when at least one channel confirmed delivery so a
		// total outage does not silently swallow the proposal.
		if ( $email_sent || $sms_sent || $bale_sent || $eitaa_sent ) {
			$order->update_meta_data( self::SENT_FLAG, (string) time() );
		}

		// 7. Record order notes
		/* translators: 1: customer name, 2: phone number, 3: email status (موفق/ناموفق), 4: SMS status, 5: Bale bot status, 6: Eitaa bot status */
		$log_msg = sprintf(
			__( 'پیش‌فاکتور قیمت‌دار برای خریدار (%s - %s) ارسال شد. ایمیل: %s | پیامک/پیام‌رسان: %s | ربات بله: %s | ربات ایتا: %s', 'woo-smart-quote' ),
			esc_html( $name ),
			esc_html( $phone ),
			$email_sent ? __( 'موفق', 'woo-smart-quote' ) : __( 'ناموفق یا خالی', 'woo-smart-quote' ),
			$sms_sent ? __( 'موفق', 'woo-smart-quote' ) : __( 'عدم پیکربندی یا در انتظار', 'woo-smart-quote' ),
			$bale_sent ? __( 'موفق', 'woo-smart-quote' ) : __( 'بدون چت لینک‌شده', 'woo-smart-quote' ),
			$eitaa_sent ? __( 'موفق', 'woo-smart-quote' ) : __( 'بدون چت لینک‌شده', 'woo-smart-quote' )
		);
		$order->add_order_note( $log_msg );
		$order->save();

		WSQ_Logger::info( 'buyer', 'Proposal dispatch finished.', array(
			'order_id' => $order_id,
			'email'    => $results['email'] ? 'yes' : 'no',
			'sms'      => $results['sms'] ? 'yes' : 'no',
			'bale'     => $results['bale'] ? 'yes' : 'no',
			'eitaa'    => $results['eitaa'] ? 'yes' : 'no',
			'force'    => $force ? 'yes' : 'no',
		) );

		return $results;
	}

	/**
	 * Extract phone number cleanly from order
	 *
	 * @param WC_Order $order
	 * @return string
	 */
	public function get_customer_phone( $order ) {
		$phone = $order->get_billing_phone();
		if ( empty( $phone ) ) {
			$phone = $order->get_meta( '_wsq_customer_phone' );
		}
		// Clean phone number: remove non-digits
		$cleaned = preg_replace( '/[^0-9+]/', '', (string) $phone );
		return $cleaned;
	}

	/**
	 * Build full formatted text for messenger and direct sharing
	 */
	public function build_proposal_text( $order, $customer_name, $total, $validity, $payment_url ) {
		$items_breakdown = "";
		foreach ( $order->get_items() as $item ) {
			$qty   = $item->get_quantity();
			$price = wp_strip_all_tags( wc_price( $item->get_total(), array( 'currency' => $order->get_currency() ) ) );
			$items_breakdown .= sprintf(
				/* translators: 1: product name, 2: quantity, 3: line price */
				__( '▫️ %1$s (%2$s عدد) ➔ %3$s', 'woo-smart-quote' ),
				$item->get_name(),
				$qty,
				$price
			) . "\n";
		}

		$text = sprintf(
			/* translators: 1: customer name, 2: order number, 3: items breakdown, 4: total, 5: validity, 6: payment URL */
			__( "سلام جناب %1\$s عزیز!\n\nپیشنهاد قیمت استعلام شماره #%2\$s آماده شد:\n\n📋 ریز مبالغ اقلام استعلام:\n%3\$s\n💰 جمع کل پیش‌فاکتور: %4\$s\n⏳ مهلت اعتبار این قیمت: %5\$s\n📄 پیش‌فاکتور رسمی با مهر دیجیتال صادر گردید.\n\nجهت تایید، پرداخت آنلاین درگاه شاپرک و نهایی‌سازی سفارش، روی لینک زیر کلیک فرمایید:\n%6\$s\n\nبا احترام، واحد فروش", 'woo-smart-quote' ),
			$customer_name,
			$order->get_order_number(),
			$items_breakdown,
			$total,
			$validity,
			$payment_url
		);

		return apply_filters( 'wsq_proposal_buyer_message', $text, $order );
	}

	/**
	 * Send email with PDF attachment
	 */
	private function send_proposal_email( $order, $message, $pdf_path ) {
		$to = $order->get_billing_email();
		if ( empty( $to ) || ! is_email( $to ) ) {
			WSQ_Logger::warning( 'buyer', 'Buyer email missing or invalid; email channel skipped.', array( 'order_id' => $order->get_id() ) );
			return false;
		}

		$subject     = sprintf(
			/* translators: %s: order number */
			__( 'پیشنهاد قیمت رسمی استعلام #%s', 'woo-smart-quote' ),
			$order->get_order_number()
		);
		$headers     = array( 'Content-Type: text/html; charset=UTF-8' );
		$attachments = ( ! empty( $pdf_path ) && file_exists( $pdf_path ) ) ? array( $pdf_path ) : array();

		$result = wp_mail( $to, $subject, nl2br( esc_html( $message ) ), $headers, $attachments );

		if ( ! $result ) {
			WSQ_Logger::error( 'buyer', 'wp_mail() reported failure for buyer proposal.', array(
				'order_id' => $order->get_id(),
				'to'       => $to,
			) );
		}

		return $result;
	}

	/**
	 * Send SMS / Messenger notification to buyer
	 */
	private function send_sms_or_messenger( $phone, $order, $name, $total, $validity, $payment_url ) {
		$provider = get_option( 'wsq_buyer_sms_provider', 'none' );
		if ( 'none' === $provider ) {
			// Allow external plugins/themes to hook into this
			do_action( 'wsq_send_customer_sms_proposal', $phone, $order, $total, $payment_url );
			return false;
		}

		$normalized_phone = $this->normalize_mobile( $phone );

		if ( '' === $normalized_phone ) {
			WSQ_Logger::warning( 'buyer', 'Phone is not a valid Iranian mobile number; SMS/messenger channel skipped.', array(
				'order_id' => $order->get_id(),
				'phone'    => $phone,
			) );
			return false;
		}

		switch ( $provider ) {
			case 'farazsms':
			case 'ippanel':
				return $this->send_via_ippanel( $normalized_phone, $order, $name, $total, $payment_url );

			case 'kavenegar':
				return $this->send_via_kavenegar( $normalized_phone, $order, $name, $total, $payment_url );

			case 'safir_bale':
				return $this->send_via_safir_bale( $normalized_phone, $order, $name, $total, $payment_url );

			default:
				// Unknown provider: hand the payload to integrations, but do not
				// claim delivery we cannot confirm.
				do_action( 'wsq_send_customer_sms_proposal', $normalized_phone, $order, $total, $payment_url );
				WSQ_Logger::warning( 'buyer', 'Unknown SMS provider; delivery handed to custom hook.', array( 'provider' => $provider ) );
				return false;
		}
	}

	/**
	 * Normalize a phone number to the 09xxxxxxxxx shape used by Iranian
	 * gateways. Returns an empty string when the number cannot be a mobile
	 * (landlines would only produce gateway errors).
	 *
	 * @param string $phone
	 * @return string
	 */
	public function normalize_mobile( $phone ) {
		$digits = preg_replace( '/[^0-9]/', '', (string) $phone );

		if ( 0 === strpos( $digits, '0098' ) ) {
			$digits = '0' . substr( $digits, 4 );
		} elseif ( 0 === strpos( $digits, '98' ) && 12 === strlen( $digits ) ) {
			$digits = '0' . substr( $digits, 2 );
		} elseif ( 0 === strpos( $digits, '9' ) && 10 === strlen( $digits ) ) {
			$digits = '0' . $digits;
		}

		return preg_match( '/^09\d{9}$/', $digits ) ? $digits : '';
	}

	/**
	 * Send via IPPanel / FarazSMS (Supports pattern send + automatic delivery to Bale and Eitaa)
	 */
	private function send_via_ippanel( $phone, $order, $name, $total, $payment_url ) {
		$api_key      = get_option( 'wsq_buyer_sms_apikey', '' );
		$pattern_code = get_option( 'wsq_buyer_sms_pattern', '' );
		$sender       = get_option( 'wsq_buyer_sms_sender', '+983000505' );

		if ( empty( $api_key ) || empty( $pattern_code ) ) {
			WSQ_Logger::warning( 'buyer', 'IPPanel selected but API key or pattern code is empty.', array( 'order_id' => $order->get_id() ) );
			return false;
		}

		$body = array(
			'code'      => $pattern_code,
			'sender'    => $sender,
			'recipient' => $phone,
			'variable'  => array(
				'name'  => $name,
				'order' => (string) $order->get_order_number(),
				'price' => wp_strip_all_tags( $total ),
				'link'  => $payment_url,
			),
		);

		$endpoint = strval( apply_filters( 'wsq_ippanel_url', 'https://api2.ippanel.com/api/v1/sms/pattern/normal/send' ) );

		$response = wp_remote_post( $endpoint, array(
			'headers' => array(
				'apikey'       => $api_key,
				'Content-Type' => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
			'timeout' => 20,
		) );

		$ok = ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200;

		if ( ! $ok ) {
			WSQ_Logger::error( 'buyer', 'IPPanel/FarazSMS send failed.', array(
				'order_id' => $order->get_id(),
				'error'    => is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $response ),
			) );
		}

		return $ok;
	}

	/**
	 * Send via Kavenegar Pattern Lookup
	 */
	private function send_via_kavenegar( $phone, $order, $name, $total, $payment_url ) {
		$api_key      = get_option( 'wsq_buyer_sms_apikey', '' );
		$pattern_code = get_option( 'wsq_buyer_sms_pattern', '' );

		if ( empty( $api_key ) || empty( $pattern_code ) ) {
			WSQ_Logger::warning( 'buyer', 'Kavenegar selected but API key or pattern code is empty.', array( 'order_id' => $order->get_id() ) );
			return false;
		}

		$endpoint = sprintf(
			strval( apply_filters( 'wsq_kavenegar_url', 'https://api.kavenegar.com/v1/%s/verify/lookup.json?receptor=%s&token=%s&token2=%s&token3=%s&template=%s' ) ),
			rawurlencode( $api_key ),
			rawurlencode( $phone ),
			rawurlencode( $name ),
			rawurlencode( (string) $order->get_order_number() ),
			rawurlencode( $payment_url ),
			rawurlencode( $pattern_code )
		);

		$response = wp_remote_get( $endpoint, array( 'timeout' => 20 ) );
		$ok = ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200;

		if ( ! $ok ) {
			WSQ_Logger::error( 'buyer', 'Kavenegar send failed.', array(
				'order_id' => $order->get_id(),
				'error'    => is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $response ),
			) );
		}

		return $ok;
	}

	/**
	 * Send via Safir Bale API
	 */
	private function send_via_safir_bale( $phone, $order, $name, $total, $payment_url ) {
		// The settings UI has a single credential field for buyers; accept the
		// dedicated Safir option when it exists, otherwise use that field.
		$token = get_option( 'wsq_buyer_bale_token', '' );
		if ( empty( $token ) ) {
			$token = get_option( 'wsq_buyer_sms_apikey', '' );
		}
		if ( empty( $token ) ) {
			WSQ_Logger::warning( 'buyer', 'Safir Bale selected but buyer token is empty.', array( 'order_id' => $order->get_id() ) );
			return false;
		}

		$message = sprintf(
			/* translators: 1: buyer name, 2: quote order number, 3: formatted total, 4: payment URL */
			__( "سلام %1\$s عزیز!\nپیشنهاد قیمت استعلام #%2\$s صادر شد.\nمبلغ کل: %3\$s\nپرداخت آنلاین: %4\$s", 'woo-smart-quote' ),
			$name,
			$order->get_order_number(),
			$total,
			$payment_url
		);

		$body = array(
			'receiver_phone' => $phone,
			'text'           => $message,
		);

		$response = wp_remote_post( strval( apply_filters( 'wsq_safir_bale_url', 'https://tapi.bale.ai/safir/send_message' ) ), array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
			'timeout' => 20,
		) );

		$ok = ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200;

		if ( ! $ok ) {
			WSQ_Logger::error( 'buyer', 'Safir Bale send failed.', array(
				'order_id' => $order->get_id(),
				'error'    => is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $response ),
			) );
		}

		return $ok;
	}

	/**
	 * Push the proposal to the chat a buyer linked with their phone in a bot.
	 *
	 * Chats are only known after the buyer pressed «ثبت شماره تماس» in the
	 * bot, so an unlinked phone is a normal skip rather than an error.
	 *
	 * @param string       $messenger 'bale' or 'eitaa'.
	 * @param WC_Order     $order
	 * @param string       $message   Full proposal text.
	 * @param string|false $pdf_path  Generated PDF path.
	 * @param string       $phone     Buyer phone from the order.
	 * @return bool True when the API accepted at least one message.
	 */
	private function send_via_bot( $messenger, $order, $message, $pdf_path, $phone ) {
		$token = trim( (string) get_option( 'wsq_' . $messenger . '_token', '' ) );
		if ( '' === $token ) {
			return false;
		}

		$phone = $this->normalize_mobile( $phone );
		if ( '' === $phone ) {
			return false;
		}

		$chat_id = WSQ_Updates::instance()->find_chat_by_phone( $messenger, $phone );
		if ( '' === $chat_id ) {
			WSQ_Logger::debug( 'buyer', 'No linked chat for the buyer phone; bot push skipped.', array(
				'order_id'  => $order->get_id(),
				'messenger' => $messenger,
			) );
			return false;
		}

		$api = WSQ_Updates::api_for( $messenger, $token );

		// Short caption on the document, then the full text as its own message
		// so the payment link is always tappable. Eitaa panels without a file
		// endpoint fall back to a text notice by themselves.
		/* translators: %s: order number */
		$caption  = sprintf( __( 'پیش‌فاکتور استعلام #%s', 'woo-smart-quote' ), $order->get_order_number() );
		$document = $api->send_document( $chat_id, $pdf_path, $caption );
		$text     = $api->send_message( $chat_id, $message );

		$ok = ! empty( $document['ok'] ) || ! empty( $text['ok'] );

		if ( ! $ok ) {
			WSQ_Logger::warning( 'buyer', 'Bot push failed.', array(
				'order_id'    => $order->get_id(),
				'messenger'   => $messenger,
				'chat_id'     => $chat_id,
				'description' => isset( $text['description'] ) ? $text['description'] : '',
			) );
		}

		return $ok;
	}

	/**
	 * AJAX endpoint to manually resend proposal to buyer
	 */
	public function ajax_resend_buyer_proposal() {
		check_ajax_referer( 'wsq_buyer_resend_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'woo-smart-quote' ) ) );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'شناسه سفارش نامعتبر است.', 'woo-smart-quote' ) ) );
		}

		// Manual resend bypasses the duplicate guard and reports real channel results.
		$results = $this->dispatch_to_buyer( $order_id, true );

		$email_ok = ! empty( $results['email'] );
		$sms_ok   = ! empty( $results['sms'] );
		$bale_ok  = ! empty( $results['bale'] );
		$eitaa_ok = ! empty( $results['eitaa'] );

		if ( $email_ok || $sms_ok || $bale_ok || $eitaa_ok ) {
			wp_send_json_success( array(
				'message' => sprintf(
					/* translators: 1: email, 2: SMS, 3: Bale, 4: Eitaa */
					__( 'پیش‌فاکتور ارسال شد — ایمیل: %1$s، پیامک: %2$s، ربات بله: %3$s، ربات ایتا: %4$s', 'woo-smart-quote' ),
					$email_ok ? __( 'موفق', 'woo-smart-quote' ) : __( 'مقصدی نداشت', 'woo-smart-quote' ),
					$sms_ok ? __( 'موفق', 'woo-smart-quote' ) : __( 'مقصدی نداشت', 'woo-smart-quote' ),
					$bale_ok ? __( 'موفق', 'woo-smart-quote' ) : __( 'مقصدی نداشت', 'woo-smart-quote' ),
					$eitaa_ok ? __( 'موفق', 'woo-smart-quote' ) : __( 'مقصدی نداشت', 'woo-smart-quote' )
				),
				'channels' => $results,
			) );
		}

		wp_send_json_error( array( 'message' => __( 'هیچ کانالی برای ارسال برقرار نشد. لاگ‌ها را در ووکامرس > وضعیت > گزارش‌ها بررسی کنید.', 'woo-smart-quote' ) ) );
	}
}
