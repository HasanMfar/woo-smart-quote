<?php
/**
 * Messenger API Base Class
 *
 * Bale speaks a Telegram-compatible API; Eitaayar looks similar but differs in
 * the document method name and in its tolerance for `parse_mode`. Both quirks
 * are handled here so the queue only sees a normalized ok/description array.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class WSQ_Messenger_API {

	/** Document upload methods, tried in order until one is accepted. */
	const CAPTION_LIMIT = 1000;

	protected $token;
	protected $base_url;

	/**
	 * Multipart field name used for the document payload.
	 * Bale (Telegram-compatible) expects "document"; Eitaayar expects "file".
	 *
	 * @var string
	 */
	protected $file_field = 'document';

	/**
	 * Endpoint candidates for sending a document, most likely first.
	 * Each entry: array( 'method' => 'sendDocument', 'field' => 'document' ).
	 *
	 * @var array<int,array{method:string,field:string}>
	 */
	protected $document_attempts = array(
		array(
			'method' => 'sendDocument',
			'field'  => 'document',
		),
	);

	/** @var string Friendly name for logs. */
	protected $name = '';

	public function __construct( $token ) {
		$this->token = $token;
	}

	/**
	 * Send a text message.
	 *
	 * Dynamic values (customer name, note, …) can contain `<` or `&`, which
	 * Telegram-style HTML parsing rejects outright. They are escaped before
	 * sending, and a rejected `parse_mode` is retried once as plain text so a
	 * formatting hiccup never silences an alert.
	 *
	 * @param string $chat_id
	 * @param string $text
	 * @param array  $extra Extra API parameters (reply_markup, entities, …).
	 * @return array Response array carrying ok=true/false.
	 */
	public function send_message( $chat_id, $text, $extra = array() ) {
		$text = (string) $text;

		// Keep within the messenger's text limit.
		if ( function_exists( 'mb_substr' ) && mb_strlen( $text ) > 3800 ) {
			$text = mb_substr( $text, 0, 3799 ) . '…';
		}

		$parsed = $this->execute( 'sendMessage', array_merge(
			array(
				'chat_id'    => $chat_id,
				'text'       => $this->sanitize_html_fragment( $text ),
				'parse_mode' => 'HTML',
			),
			(array) $extra
		) );

		$code = isset( $parsed['http_code'] ) ? (int) $parsed['http_code'] : 0;

		// 400/422 means the request shape was rejected (some panels refuse
		// parse_mode); a formatting error names the entity problem. Both are
		// recoverable by sending the same text without formatting.
		if ( empty( $parsed['ok'] ) && ( $this->is_formatting_error( $parsed ) || in_array( $code, array( 400, 422 ), true ) ) ) {
			WSQ_Logger::warning( $this->log_context(), 'HTML formatting rejected; retrying as plain text.', array(
				'chat_id' => $chat_id,
				'reason'  => isset( $parsed['description'] ) ? $parsed['description'] : '',
			) );

			$plain = $this->execute( 'sendMessage', array_merge(
				array(
					'chat_id' => $chat_id,
					'text'    => $text,
				),
				(array) $extra
			) );

			if ( ! empty( $plain['ok'] ) ) {
				$plain['degraded'] = true;
				return $plain;
			}

			return $plain;
		}

		return $parsed;
	}

	/**
	 * Send a document (like a PDF).
	 *
	 * @param string       $chat_id
	 * @param string|false $file_path
	 * @param string       $caption
	 * @return array Response array carrying ok=true/false.
	 */
	public function send_document( $chat_id, $file_path, $caption = '' ) {
		$caption      = (string) $caption;
		$long_caption = function_exists( 'mb_strlen' ) && mb_strlen( $caption ) > self::CAPTION_LIMIT;
		$doc_caption  = $long_caption
			? mb_substr( $caption, 0, self::CAPTION_LIMIT - 1 ) . '…'
			: $caption;

		$delivered = null;
		$last      = array( 'ok' => false, 'description' => 'File not found.' );

		if ( ! empty( $file_path ) && file_exists( $file_path ) ) {
			$last = array( 'ok' => false, 'description' => 'Document endpoint unavailable.' );

			foreach ( $this->document_attempts as $attempt ) {
				$last = $this->post_document( $attempt['method'], $attempt['field'], $chat_id, $file_path, $doc_caption );

				if ( ! empty( $last['ok'] ) ) {
					$delivered = $last;
					break;
				}

				// A 404 means this messenger does not expose that method at all;
				// any other error is a real rejection and retrying another
				// endpoint would only duplicate failures.
				$code = isset( $last['http_code'] ) ? (int) $last['http_code'] : 0;
				if ( 404 !== $code ) {
					break;
				}
			}

			if ( null !== $delivered ) {
				// Captions are clipped by the API; send the whole text separately
				// so the expert still sees every line of the alert.
				if ( $long_caption ) {
					$this->send_message( $chat_id, $caption );
				}
				return $delivered;
			}

			WSQ_Logger::warning( $this->log_context(), 'Document delivery failed; falling back to text alert.', array(
				'chat_id'     => $chat_id,
				'description' => isset( $last['description'] ) ? $last['description'] : '',
			) );
		} else {
			// The PDF was never generated (or was pruned). The alert text is
			// still useful, so degrade instead of failing the whole dispatch.
			WSQ_Logger::error( $this->log_context(), 'Document file missing; falling back to text alert.', array(
				'chat_id' => $chat_id,
				'path'    => (string) $file_path,
			) );
		}

		$pdf_fallback_note = __( '⚠️ پیوست PDF ارسال نشد؛ سند در پنل فروشگاه موجود است.', 'woo-smart-quote' );
		$fallback_text     = ! empty( $caption )
			? $caption . "\n\n" . $pdf_fallback_note
			: $pdf_fallback_note;

		$fallback = $this->send_message( $chat_id, $fallback_text );
		if ( ! empty( $fallback['ok'] ) ) {
			return array(
				'ok'          => true,
				'degraded'    => true,
				'description' => 'PDF not delivered; text alert delivered.',
			);
		}

		return $last;
	}

	/**
	 * Upload one document using the given endpoint and multipart field name.
	 *
	 * @param string $method    API method (sendDocument / sendFile).
	 * @param string $field     Multipart field carrying the file.
	 * @param string $chat_id
	 * @param string $file_path
	 * @param string $caption
	 * @return array
	 */
	protected function post_document( $method, $field, $chat_id, $file_path, $caption ) {
		$url = $this->base_url . $this->token . '/' . $method;

		// Alphanumeric-only boundary: wp_generate_password's default special
		// chars include symbols that are illegal in an RFC 2046 boundary.
		$boundary = wp_generate_password( 24, false );
		$headers  = array(
			'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
		);

		$payload  = '--' . $boundary . "\r\n";
		$payload .= 'Content-Disposition: form-data; name="chat_id"' . "\r\n\r\n";
		$payload .= $chat_id . "\r\n";

		if ( '' !== $caption ) {
			$payload .= '--' . $boundary . "\r\n";
			$payload .= 'Content-Disposition: form-data; name="caption"' . "\r\n\r\n";
			$payload .= $caption . "\r\n";
		}

		$payload .= '--' . $boundary . "\r\n";
		$payload .= 'Content-Disposition: form-data; name="' . $field . '"; filename="' . basename( $file_path ) . '"' . "\r\n";
		$payload .= 'Content-Type: application/pdf' . "\r\n\r\n";
		$payload .= file_get_contents( $file_path ) . "\r\n";
		$payload .= '--' . $boundary . '--' . "\r\n";

		$response = wp_remote_post( $url, array(
			'headers' => $headers,
			'body'    => $payload,
			'timeout' => 45,
		) );

		$parsed = $this->parse_response( $response, $method, $chat_id );

		if ( class_exists( 'WSQ_Updates' ) ) {
			WSQ_Updates::log_outbound( $this->name, (string) $chat_id, $method, $parsed, (string) $caption );
		}

		return $parsed;
	}

	/**
	 * Fetch pending inbound updates (long polling).
	 *
	 * Bale documents getUpdates with offset/limit/timeout; Eitaa panels
	 * commonly expose the same method even though it is undocumented. A
	 * panel that does not answers 404, which the caller uses to stop
	 * polling.
	 *
	 * @param int $offset First update to return.
	 * @param int $timeout Long-poll wait in seconds (0 = return immediately).
	 * @return array Normalized response; result holds the update array.
	 */
	public function get_updates( $offset = 0, $timeout = 0 ) {
		$params = array(
			'offset'  => (int) $offset,
			'limit'   => 100,
			'timeout' => (int) $timeout,
		);

		$url = $this->base_url . $this->token . '/getUpdates';

		$response = wp_remote_get( add_query_arg( $params, $url ), array(
			'timeout' => ( (int) $timeout ) + 25,
		) );

		return $this->parse_response( $response, 'getUpdates', '' );
	}

	/**
	 * Point the messenger at our webhook URL (Bale).
	 *
	 * @param string $url
	 * @return array
	 */
	public function set_webhook( $url ) {
		return $this->execute( 'setWebhook', array( 'url' => (string) $url ) );
	}

	/**
	 * Remove the webhook so polling can take over.
	 *
	 * @return array
	 */
	public function delete_webhook() {
		return $this->execute( 'deleteWebhook', array() );
	}

	/**
	 * Verify the token by asking the bot who it is.
	 *
	 * @return array result holds id/username when ok.
	 */
	public function get_me() {
		return $this->execute( 'getMe', array() );
	}

	/**
	 * Perform an API request and normalize the response.
	 *
	 * @param string $method API method name appended to the token path.
	 * @param array  $params Form parameters.
	 * @return array Array carrying ok=true/false plus a description on error.
	 */
	protected function execute( $method, $params ) {
		$url = $this->base_url . $this->token . '/' . $method;

		$response = wp_remote_post( $url, array(
			'body'    => $params,
			'timeout' => 20,
		) );

		$parsed = $this->parse_response( $response, $method, isset( $params['chat_id'] ) ? $params['chat_id'] : '' );

		// Track every outbound send so the admin inbox shows delivery state.
		if ( 0 === strpos( $method, 'send' ) && isset( $params['chat_id'] ) && class_exists( 'WSQ_Updates' ) ) {
			$text = isset( $params['text'] ) ? $params['text'] : ( isset( $params['caption'] ) ? $params['caption'] : '' );
			WSQ_Updates::log_outbound( $this->name, (string) $params['chat_id'], $method, $parsed, (string) $text );
		}

		return $parsed;
	}

	/**
	 * Whether the failure is about HTML/parse_mode rather than delivery.
	 *
	 * @param array $parsed
	 * @return bool
	 */
	protected function is_formatting_error( $parsed ) {
		if ( empty( $parsed['description'] ) ) {
			return false;
		}

		$description = strtolower( (string) $parsed['description'] );
		$needles     = array( 'parse entities', "can't parse", 'cant parse', 'parse_mode', 'unsupported parse' );

		foreach ( $needles as $needle ) {
			if ( false !== strpos( $description, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Escape only characters that would break HTML parsing, leaving the
	 * formatting tags administrators may deliberately put in the template.
	 *
	 * @param string $text
	 * @return string
	 */
	protected function sanitize_html_fragment( $text ) {
		// Ampersands that do not already start an entity.
		$text = preg_replace( '/&(?!#?[a-zA-Z0-9]+;)/', '&amp;', $text );
		// Angle brackets that cannot start a tag.
		$text = preg_replace( '/<(?![a-zA-Z\/!])/', '&lt;', (string) $text );
		return $text;
	}

	/**
	 * Turn a wp_remote_post result into a normalized array.
	 *
	 * @param mixed  $response Result of wp_remote_post().
	 * @param string $method   Method name for logs.
	 * @param string $chat_id  Target chat for logs.
	 * @return array Always contains ok, http_code when known, and description when ok is false.
	 */
	protected function parse_response( $response, $method, $chat_id ) {
		if ( is_wp_error( $response ) ) {
			$message = $response->get_error_message();
			WSQ_Logger::error( $this->log_context(), 'HTTP request failed.', array(
				'method'  => $method,
				'chat_id' => $chat_id,
				'error'   => $message,
			) );
			return array( 'ok' => false, 'description' => $message );
		}

		$code    = (int) wp_remote_retrieve_response_code( $response );
		$body    = wp_remote_retrieve_body( $response );
		$decoded = json_decode( $body, true );

		$ok = is_array( $decoded ) && ! empty( $decoded['ok'] );
		$http_ok = $code >= 200 && $code < 300;

		// Some panels answer plain `true` instead of {ok:true}.
		if ( ! $ok && $http_ok && 'true' === strtolower( trim( (string) $body ) ) ) {
			$decoded = array( 'ok' => true, 'result' => true );
			$ok      = true;
		}
		$ok = $ok && $http_ok;

		if ( ! $ok ) {
			$description = '';
			if ( is_array( $decoded ) ) {
				if ( isset( $decoded['description'] ) ) {
					$description = $decoded['description'];
				} elseif ( isset( $decoded['msg'] ) ) {
					$description = $decoded['msg'];
				} elseif ( ! empty( $decoded['error'] ) ) {
					$description = is_string( $decoded['error'] ) ? $decoded['error'] : wp_json_encode( $decoded['error'] );
				}
			} else {
				$description = 'Invalid or unreadable API response';
			}

			if ( 200 !== $code && '' === $description ) {
				$description = 'HTTP ' . $code;
			}

			WSQ_Logger::error( $this->log_context(), 'API call rejected.', array(
				'method'      => $method,
				'chat_id'     => $chat_id,
				'http_code'   => $code,
				'description' => $description,
			) );

			return array(
				'ok'          => false,
				'http_code'   => $code,
				'description' => $description,
			);
		}

		WSQ_Logger::debug( $this->log_context(), 'API call accepted.', array(
			'method'  => $method,
			'chat_id' => $chat_id,
		) );

		return $decoded;
	}

	/**
	 * Logical log area tag for this messenger.
	 *
	 * @return string
	 */
	protected function log_context() {
		return $this->name ? $this->name : 'messenger';
	}
}
