<?php
/**
 * Inbound messenger engine (دریافت پیام از بله و ایتا).
 *
 * Bale is Telegram-compatible: it supports setWebhook and getUpdates.
 * Eitaa has no documented webhook, so it is served by long polling; if the
 * panel answers 404/"method not found" the poller disables itself for that
 * messenger instead of failing on every cron tick.
 *
 * Every inbound and outbound message is stored in a dedicated table so the
 * admin inbox can show the conversation and a per-chat delivery state:
 *   out: sent | failed        (API accepted / rejected)
 *   in : received             (proof the chat is alive — outbound rows for
 *                              the same chat flip to "seen")
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSQ_Updates {

	/** Messages table (without prefix). */
	const TABLE = 'wsq_messages';

	const REST_NAMESPACE = 'woo-smart-quote/v1';

	protected static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	protected function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'wsq_poll_updates', array( $this, 'poll_all' ) );
		add_action( 'init', array( $this, 'schedule_polling' ) );
		add_filter( 'cron_schedules', array( $this, 'add_minute_schedule' ) );
	}

	/* ---------------------------------------------------------------------
	 * Schema
	 * ------------------------------------------------------------------ */

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Create the messages table. Runs from the plugin activation hook.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			messenger VARCHAR(16) NOT NULL DEFAULT '',
			direction VARCHAR(8) NOT NULL DEFAULT 'in',
			chat_id VARCHAR(64) NOT NULL DEFAULT '',
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			remote_message_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			order_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			status VARCHAR(24) NOT NULL DEFAULT '',
			text LONGTEXT NULL,
			meta VARCHAR(255) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY messenger_chat (messenger, chat_id),
			KEY order_id (order_id),
			KEY direction_created (direction, created_at)
		) {$charset};";

		dbDelta( $sql );
	}

	/* ---------------------------------------------------------------------
	 * Polling schedule
	 * ------------------------------------------------------------------ */

	public function add_minute_schedule( $schedules ) {
		if ( ! isset( $schedules['wsq_minute'] ) ) {
			$schedules['wsq_minute'] = array(
				'interval' => 60,
				'display'  => 'Every minute (WSQ)',
			);
		}
		return $schedules;
	}

	public function schedule_polling() {
		if ( ! wp_next_scheduled( 'wsq_poll_updates' ) ) {
			wp_schedule_event( time() + 20, 'wsq_minute', 'wsq_poll_updates' );
		}
	}

	/* ---------------------------------------------------------------------
	 * Webhook (Bale)
	 * ------------------------------------------------------------------ */

	/**
	 * Shared secret embedded in the webhook URL. Bale has no secret_token
	 * parameter, so the URL itself is the authentication factor.
	 *
	 * @return string
	 */
	public function webhook_secret() {
		$secret = (string) get_option( 'wsq_webhook_secret', '' );
		if ( '' === $secret ) {
			$secret = wp_generate_password( 40, false, false );
			update_option( 'wsq_webhook_secret', $secret, false );
		}
		return $secret;
	}

	/**
	 * Public URL Bale should POST updates to.
	 *
	 * @return string
	 */
	public function webhook_url() {
		return add_query_arg( 's', $this->webhook_secret(), rest_url( self::REST_NAMESPACE . '/bale-hook' ) );
	}

	public function register_routes() {
		$args = array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'rest_webhook' ),
			'permission_callback' => '__return_true',
		);
		register_rest_route( self::REST_NAMESPACE, '/bale-hook', $args );
		// Installations that registered the pre-v1 URL keep receiving updates.
		register_rest_route( 'woo-smart-quote', '/bale-hook', $args );
	}

	/**
	 * Webhook entry point. Validates the secret, then feeds the update into
	 * the same pipeline used by polling.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function rest_webhook( $request ) {
		$given  = (string) $request->get_param( 's' );
		$secret = $this->webhook_secret();

		if ( '' === $secret || ! hash_equals( $secret, $given ) ) {
			WSQ_Logger::warning( 'updates', 'Webhook rejected: bad secret.' );
			return new WP_REST_Response( array( 'ok' => false ), 403 );
		}

		$raw = $request->get_json_params();
		if ( empty( $raw ) ) {
			// Some panels post text/plain; fall back to the raw body.
			$raw = json_decode( (string) file_get_contents( 'php://input' ), true );
		}

		if ( is_array( $raw ) ) {
			// Bale retries a webhook until it observes a 200. Without this
			// guard every retry would relay the buyer message again.
			$update_id = isset( $raw['update_id'] ) ? (int) $raw['update_id'] : 0;
			if ( $update_id > 0 && $update_id <= (int) get_option( 'wsq_bale_last_update_id', 0 ) ) {
				return new WP_REST_Response( array( 'ok' => true, 'duplicate' => true ), 200 );
			}

			$count = $this->process_updates( 'bale', array( $raw ) );

			if ( $update_id > 0 ) {
				update_option( 'wsq_bale_last_update_id', $update_id, false );
			}

			WSQ_Logger::info( 'updates', 'Webhook update accepted.', array( 'events' => $count ) );
		}

		// Always answer 200 so the messenger does not retry-storm us.
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Register or remove the Bale webhook.
	 *
	 * @param string $action 'set' or 'delete'.
	 * @return array Normalized API response.
	 */
	public function manage_webhook( $action ) {
		$token = trim( (string) get_option( 'wsq_bale_token', '' ) );
		if ( '' === $token ) {
			return array( 'ok' => false, 'description' => __( 'توکن بله تنظیم نشده است.', 'woo-smart-quote' ) );
		}

		$api = new WSQ_Bale_API( $token );

		if ( 'delete' === $action ) {
			$result = $api->delete_webhook();
			if ( ! empty( $result['ok'] ) ) {
				delete_option( 'wsq_bale_webhook_url' );
			}
			return $result;
		}

		$url    = $this->webhook_url();
		$result = $api->set_webhook( $url );
		if ( ! empty( $result['ok'] ) ) {
			update_option( 'wsq_bale_webhook_url', $url );
		}
		return $result;
	}

	/* ---------------------------------------------------------------------
	 * Polling
	 * ------------------------------------------------------------------ */

	/**
	 * Poll every enabled messenger once.
	 *
	 * @return void
	 */
	public function poll_all() {
		foreach ( array( 'bale', 'eitaa' ) as $messenger ) {
			if ( 'yes' !== get_option( 'wsq_polling_enabled', 'no' ) ) {
				continue;
			}
			$this->poll_once( $messenger );
		}
	}

	/**
	 * Fetch pending updates for one messenger and process them.
	 *
	 * @param string $messenger bale|eitaa.
	 * @return int Number of events processed.
	 */
	public function poll_once( $messenger ) {
		if ( ! empty( get_option( 'wsq_poll_off', array() )[ $messenger ] ) ) {
			return 0;
		}

		$token = trim( (string) get_option( 'wsq_' . $messenger . '_token', '' ) );
		if ( '' === $token ) {
			return 0;
		}

		// A registered webhook already delivers Bale updates; polling the
		// same bot would race with it.
		if ( 'bale' === $messenger && '' !== (string) get_option( 'wsq_bale_webhook_url', '' ) ) {
			return 0;
		}

		$offset = (int) get_option( 'wsq_' . $messenger . '_offset', 0 );
		$api    = self::api_for( $messenger, $token );
		$result = $api->get_updates( $offset, 0 );

		if ( empty( $result['ok'] ) ) {
			$code        = isset( $result['http_code'] ) ? (int) $result['http_code'] : 0;
			$description = isset( $result['description'] ) ? strtolower( (string) $result['description'] ) : '';

			// 404 or "method not found": this panel does not expose
			// getUpdates. Stop polling it instead of erroring every minute.
			if ( 404 === $code || false !== strpos( $description, 'not found' ) || false !== strpos( $description, 'unknown method' ) ) {
				$off             = (array) get_option( 'wsq_poll_off', array() );
				$off[ $messenger ] = 1;
				update_option( 'wsq_poll_off', $off );
				WSQ_Logger::warning( 'updates', 'getUpdates is not supported by this messenger panel; polling disabled for it.', array(
					'messenger'   => $messenger,
					'description' => isset( $result['description'] ) ? $result['description'] : '',
				) );
			}
			return 0;
		}

		$updates = isset( $result['result'] ) && is_array( $result['result'] ) ? $result['result'] : array();
		if ( empty( $updates ) ) {
			return 0;
		}

		$count = $this->process_updates( $messenger, $updates );

		// Advance past the highest update_id seen. The API already returns
		// updates from the stored offset, so an equal id must still advance.
		$max = null;
		foreach ( $updates as $update ) {
			if ( isset( $update['update_id'] ) && ( null === $max || (int) $update['update_id'] > $max ) ) {
				$max = (int) $update['update_id'];
			}
		}
		if ( null !== $max && $max >= $offset ) {
			update_option( 'wsq_' . $messenger . '_offset', $max + 1 );
		}

		return $count;
	}

	/**
	 * Build an API wrapper for a messenger.
	 *
	 * @param string $messenger bale|eitaa.
	 * @param string $token
	 * @return WSQ_Messenger_API
	 */
	public static function api_for( $messenger, $token = null ) {
		if ( null === $token ) {
			$token = trim( (string) get_option( 'wsq_' . $messenger . '_token', '' ) );
		}

		if ( 'eitaa' === $messenger ) {
			return new WSQ_Eitaa_API( $token );
		}
		return new WSQ_Bale_API( $token );
	}

	/* ---------------------------------------------------------------------
	 * Update pipeline
	 * ------------------------------------------------------------------ */

	/**
	 * Normalize and store a batch of raw updates.
	 *
	 * @param string $messenger bale|eitaa.
	 * @param array  $updates Raw update objects.
	 * @return int Events handled.
	 */
	public function process_updates( $messenger, $updates ) {
		$handled = 0;
		foreach ( $updates as $raw ) {
			$update = self::normalize_update( $raw, $messenger );
			if ( null === $update ) {
				continue;
			}
			$this->handle_incoming( $messenger, $update );
			$handled++;
		}
		return $handled;
	}

	/**
	 * Flatten a Telegram-style Update object into a simple array.
	 *
	 * @param array  $raw
	 * @param string $messenger
	 * @return array|null Null when the update carries no usable message.
	 */
	public static function normalize_update( $raw, $messenger ) {
		if ( ! is_array( $raw ) ) {
			return null;
		}

		$message = null;
		foreach ( array( 'message', 'edited_message', 'channel_post' ) as $key ) {
			if ( isset( $raw[ $key ] ) && is_array( $raw[ $key ] ) ) {
				$message = $raw[ $key ];
				break;
			}
		}

		$callback = '';
		if ( null === $message && isset( $raw['callback_query'] ) && is_array( $raw['callback_query'] ) ) {
			$cb      = $raw['callback_query'];
			$message = isset( $cb['message'] ) && is_array( $cb['message'] ) ? $cb['message'] : array();
			$callback = isset( $cb['data'] ) ? (string) $cb['data'] : '';
		}

		if ( ! is_array( $message ) ) {
			return null;
		}

		$chat = isset( $message['chat'] ) && is_array( $message['chat'] ) ? $message['chat'] : array();
		$sender = isset( $cb ) ? $cb : $message;
		$from = isset( $sender['from'] ) && is_array( $sender['from'] ) ? $sender['from'] : array();

		$chat_id = isset( $chat['id'] ) ? (string) $chat['id'] : '';
		if ( '' === $chat_id ) {
			return null;
		}

		$text = '';
		if ( isset( $message['text'] ) ) {
			$text = (string) $message['text'];
		} elseif ( isset( $message['caption'] ) ) {
			$text = (string) $message['caption'];
		} elseif ( '' !== $callback ) {
			$text = $callback;
		}

		$contact = null;
		if ( ! isset( $cb ) && isset( $message['contact'] ) && is_array( $message['contact'] ) ) {
			$contact = array(
				'phone' => isset( $message['contact']['phone_number'] ) ? (string) $message['contact']['phone_number'] : '',
				'name'  => isset( $message['contact']['first_name'] ) ? (string) $message['contact']['first_name'] : '',
				'user'  => isset( $message['contact']['user_id'] ) ? (string) $message['contact']['user_id'] : '',
			);
		}

		$name = '';
		if ( isset( $from['first_name'] ) ) {
			$name = (string) $from['first_name'];
		}
		if ( isset( $from['last_name'] ) ) {
			$name = trim( $name . ' ' . (string) $from['last_name'] );
		}

		return array(
			'update_id'  => isset( $raw['update_id'] ) ? (int) $raw['update_id'] : 0,
			'messenger'  => $messenger,
			'message_id' => isset( $message['message_id'] ) ? (int) $message['message_id'] : 0,
			'chat_id'    => $chat_id,
			'chat_type'  => isset( $chat['type'] ) ? (string) $chat['type'] : '',
			'from_id'    => isset( $from['id'] ) ? (string) $from['id'] : '',
			'from_name'  => $name,
			'username'   => isset( $from['username'] ) ? (string) $from['username'] : '',
			'text'       => $text,
			'date'       => isset( $message['date'] ) ? (int) $message['date'] : time(),
			'contact'    => $contact,
		);
	}

	/**
	 * Persist an inbound update and hand it to the bot router.
	 *
	 * @param string $messenger
	 * @param array  $update Normalized update.
	 * @return void
	 */
	public function handle_incoming( $messenger, $update ) {
		global $wpdb;

		$user_id = 0;
		if ( ! empty( $update['from_id'] ) && 'private' === $update['chat_type'] ) {
			$user_id = (int) $update['from_id'];
		}

		$meta = '';
		if ( ! empty( $update['contact']['phone'] ) ) {
			$meta = 'contact:' . $update['contact']['phone'];
		}

		$wpdb->insert(
			self::table(),
			array(
				'messenger'         => $messenger,
				'direction'         => 'in',
				'chat_id'           => $update['chat_id'],
				'user_id'           => $user_id,
				'remote_message_id' => (int) $update['message_id'],
				'status'            => 'received',
				'text'              => $update['text'],
				'meta'              => $meta,
				'created_at'        => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		// An inbound message proves the chat is alive: mark our outbound
		// messages there as seen.
		$this->mark_chat_seen( $messenger, $update['chat_id'] );

		// The admin inbox badge must reflect the new message right away.
		delete_transient( 'wsq_inbox_unread' );

		do_action( 'wsq_incoming_update', $messenger, $update );
	}

	/* ---------------------------------------------------------------------
	 * Inbox read state
	 * ------------------------------------------------------------------- */

	/**
	 * Number of inbound messages the admin has not marked as read yet.
	 *
	 * The value is cached for a short while because the admin menu renders on
	 * every WordPress admin page.
	 *
	 * @return int
	 */
	public function unread_count() {
		global $wpdb;

		$count = get_transient( 'wsq_inbox_unread' );
		if ( false === $count ) {
			$count = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM " . self::table() . " WHERE direction = 'in' AND status = 'received'"
			);
			set_transient( 'wsq_inbox_unread', $count, 5 * MINUTE_IN_SECONDS );
		}

		return (int) $count;
	}

	/**
	 * Inbox counters for the admin UI.
	 *
	 * @return array{total:int,unread:int}
	 */
	public function inbox_stats() {
		global $wpdb;

		$row = $wpdb->get_row(
			"SELECT
				COUNT(*) AS total,
				SUM(CASE WHEN status = 'received' THEN 1 ELSE 0 END) AS unread
			 FROM " . self::table() . " WHERE direction = 'in'",
			ARRAY_A
		);

		return array(
			'total'  => isset( $row['total'] ) ? (int) $row['total'] : 0,
			'unread' => isset( $row['unread'] ) ? (int) $row['unread'] : 0,
		);
	}

	/**
	 * Mark inbound messages as read.
	 *
	 * @param array       $ids       Message ids to mark (empty = all).
	 * @param string      $messenger Optional messenger filter.
	 * @param string      $chat_id   Optional chat filter.
	 * @return int Number of affected rows.
	 */
	public function mark_read( $ids = array(), $messenger = '', $chat_id = '' ) {
		global $wpdb;

		$ids = array_map( 'absint', (array) $ids );
		$ids = array_filter( $ids );

		$where = "direction = 'in' AND status = 'received'";
		$args  = array();

		if ( $ids ) {
			$where .= ' AND id IN ( ' . implode( ', ', $ids ) . ' )';
		}
		if ( '' !== (string) $messenger ) {
			$where .= ' AND messenger = %s';
			$args[] = (string) $messenger;
		}
		if ( '' !== (string) $chat_id ) {
			$where .= ' AND chat_id = %s';
			$args[] = (string) $chat_id;
		}

		$sql = "UPDATE " . self::table() . " SET status = 'read' WHERE " . $where;
		if ( $args ) {
			$sql = $wpdb->prepare( $sql, $args );
		}

		$affected = $wpdb->query( $sql );

		delete_transient( 'wsq_inbox_unread' );

		return (int) $affected;
	}

	/* ---------------------------------------------------------------------
	 * Outbound tracking
	 * ------------------------------------------------------------------ */

	/**
	 * Record an outbound messenger call for the inbox and delivery states.
	 *
	 * @param string  $messenger bale|eitaa.
	 * @param string  $chat_id
	 * @param string  $method API method used.
	 * @param array   $response Normalized response (ok/http_code/description).
	 * @param string  $text Message text (truncated before storage).
	 * @param int     $order_id Related order when known.
	 * @return void
	 */
	public static function log_outbound( $messenger, $chat_id, $method, $response, $text = '', $order_id = 0 ) {
		global $wpdb;

		if ( '' === (string) $chat_id ) {
			return;
		}

		$ok          = ! empty( $response['ok'] );
		$remote_id   = 0;
		if ( $ok && isset( $response['result']['message_id'] ) ) {
			$remote_id = (int) $response['result']['message_id'];
		}

		$meta = $method;
		if ( ! $ok && ! empty( $response['description'] ) ) {
			$meta .= ': ' . mb_substr( (string) $response['description'], 0, 180 );
		}

		$wpdb->insert(
			self::table(),
			array(
				'messenger'         => $messenger,
				'direction'         => 'out',
				'chat_id'           => (string) $chat_id,
				'order_id'          => (int) $order_id,
				'remote_message_id' => $remote_id,
				'status'            => $ok ? 'sent' : 'failed',
				'text'              => mb_substr( (string) $text, 0, 2000 ),
				'meta'              => mb_substr( $meta, 0, 250 ),
				'created_at'        => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Flip outbound rows for a chat to "seen" once the chat talks to us.
	 *
	 * @param string $messenger
	 * @param string $chat_id
	 * @return void
	 */
	public function mark_chat_seen( $messenger, $chat_id ) {
		global $wpdb;

		$wpdb->query( $wpdb->prepare(
			"UPDATE " . self::table() . " SET status = 'seen'
			 WHERE messenger = %s AND chat_id = %s AND direction = 'out' AND status IN ( 'sent', 'delivered' )",
			$messenger,
			$chat_id
		) );
	}

	/* ---------------------------------------------------------------------
	 * Chat links (buyer <-> chat)
	 * ------------------------------------------------------------------ */

	/**
	 * Link a messenger chat to a buyer phone (and user when one exists).
	 *
	 * @param string $messenger
	 * @param string $chat_id
	 * @param string $phone Normalized phone.
	 * @param string $messenger_user_id Verified self-contact sender.
	 * @return bool
	 */
	public function link_chat( $messenger, $chat_id, $phone, $messenger_user_id ) {
		$row = array( 'phone' => $phone, 'messenger_user_id' => (string) $messenger_user_id );
		if ( ! $this->is_verified_link( $messenger, $chat_id, $row ) ) {
			return false;
		}

		$links   = (array) get_option( 'wsq_chat_links', array() );
		$user_id = 0;

		$users = get_users( array(
			'meta_key'   => 'billing_phone',
			'meta_value' => $phone,
			'number'     => 1,
			'fields'     => 'ID',
		) );
		if ( ! empty( $users ) ) {
			$user_id = (int) $users[0];
		}

		$row['user_id'] = $user_id;
		$row['created'] = time();
		$key = $messenger . ':' . $chat_id;
		unset( $links[ $key ] );

		// Keep the option from growing without bound.
		if ( count( $links ) >= 500 ) {
			uksort( $links, function ( $a, $b ) use ( $links ) {
				$a_created = isset( $links[ $a ]['created'] ) ? (int) $links[ $a ]['created'] : 0;
				$b_created = isset( $links[ $b ]['created'] ) ? (int) $links[ $b ]['created'] : 0;
				return $a_created === $b_created ? strcmp( (string) $a, (string) $b ) : ( $a_created <=> $b_created );
			} );
			$links = array_slice( $links, -499, null, true );
		}
		$links[ $key ] = $row;

		update_option( 'wsq_chat_links', $links, false );
		return true;
	}

	private function is_verified_link( $messenger, $chat_id, $row ) {
		return in_array( $messenger, array( 'bale', 'eitaa' ), true )
			&& preg_match( '/^[1-9][0-9]*$/D', (string) $chat_id )
			&& is_array( $row )
			&& isset( $row['messenger_user_id'], $row['phone'] )
			&& (string) $row['messenger_user_id'] === (string) $chat_id
			&& is_string( $row['phone'] )
			&& preg_match( '/^09[0-9]{9}$/D', $row['phone'] );
	}

	/**
	 * Look up the link record for a chat.
	 *
	 * @param string $messenger
	 * @param string $chat_id
	 * @return array|null
	 */
	public function find_link( $messenger, $chat_id ) {
		$links = (array) get_option( 'wsq_chat_links', array() );
		$key   = $messenger . ':' . $chat_id;
		return isset( $links[ $key ] ) && $this->is_verified_link( $messenger, $chat_id, $links[ $key ] ) ? $links[ $key ] : null;
	}

	/**
	 * Chat id a buyer phone is linked to on one messenger.
	 *
	 * A buyer can reach the bot from more than one chat, so the most recently
	 * linked one wins.
	 *
	 * @param string $messenger
	 * @param string $phone
	 * @return string Chat id, or an empty string when the phone is not linked.
	 */
	public function find_chat_by_phone( $messenger, $phone ) {
		$phone = preg_replace( '/[^0-9]/', '', (string) $phone );
		if ( '' === $phone ) {
			return '';
		}

		$links = (array) get_option( 'wsq_chat_links', array() );
		$best  = '';
		$newest = 0;

		foreach ( $links as $key => $row ) {
			if ( 0 !== strpos( (string) $key, $messenger . ':' ) ) {
				continue;
			}
			$chat_id = substr( (string) $key, strlen( $messenger ) + 1 );
			if ( ! $this->is_verified_link( $messenger, $chat_id, $row ) || $row['phone'] !== $phone ) {
				continue;
			}

			$created = isset( $row['created'] ) ? (int) $row['created'] : 0;
			if ( $created >= $newest ) {
				$newest = $created;
				$best   = $chat_id;
			}
		}

		return $best;
	}

	/* ---------------------------------------------------------------------
	 * Helpers for the bot and admin UI
	 * ------------------------------------------------------------------ */

	/**
	 * Chat ids that host the store administrators for a messenger.
	 *
	 * @param string $messenger
	 * @return string[]
	 */
	public function admin_chat_ids( $messenger ) {
		$raw   = (string) get_option( 'wsq_' . $messenger . '_admin_chat_ids', '' );
		$ids   = preg_split( '/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY );
		$clean = array();
		foreach ( (array) $ids as $id ) {
			$id = preg_replace( '/[^0-9-]/', '', $id );
			if ( '' !== $id ) {
				$clean[] = $id;
			}
		}
		return array_unique( $clean );
	}

	/**
	 * Whether a chat is one of the admin chats for that messenger.
	 *
	 * @param string $messenger
	 * @param string $chat_id
	 * @return bool
	 */
	public function is_admin_chat( $messenger, $chat_id ) {
		return in_array( (string) $chat_id, $this->admin_chat_ids( $messenger ), true );
	}

	/**
	 * Send a reply through the right API wrapper and track it.
	 *
	 * @param string $messenger
	 * @param string $chat_id
	 * @param string $text
	 * @param array  $extra Extra API parameters (reply_markup etc).
	 * @return array Normalized response.
	 */
	public function reply( $messenger, $chat_id, $text, $extra = array() ) {
		$token = trim( (string) get_option( 'wsq_' . $messenger . '_token', '' ) );
		if ( '' === $token ) {
			return array( 'ok' => false, 'description' => 'token-missing' );
		}

		$api      = self::api_for( $messenger, $token );
		$response = $api->send_message( $chat_id, $text, $extra );

		// Outbound logging happens centrally in WSQ_Messenger_API::execute(),
		// so every send (bot, expert alert, buyer notice) is tracked once.
		return $response;
	}
}
