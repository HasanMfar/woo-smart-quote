<?php
/**
 * Interactive bot router (ربات تعاملی بله و ایتا).
 *
 * Two audiences share one entry point (`wsq_incoming_update`):
 *
 *  - Buyers: /start offers verified self-contact sharing in a private chat,
 *    then they can list their quote requests, pull the latest
 *    proforma and payment link, or forward a question to the store staff.
 *  - Admins: chats listed as admin chats get a command menu (/pending,
 *    /order <num>) and receive copies of every buyer question so nothing
 *    is lost when the customer writes first.
 *
 * Replies always degrade gracefully: a rejected reply_markup (some panels
 * refuse inline keyboards) is retried as plain text, and a messenger with
 * no token simply produces no reply while the inbound message is still
 * stored for the admin inbox.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSQ_Bot {

	protected static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	protected function __construct() {
		add_action( 'wsq_incoming_update', array( $this, 'handle' ), 10, 2 );
	}

	/* ---------------------------------------------------------------------
	 * Entry point
	 * ------------------------------------------------------------------ */

	/**
	 * Route one normalized inbound update.
	 *
	 * @param string $messenger bale|eitaa.
	 * @param array  $update    Normalized update from WSQ_Updates.
	 * @return void
	 */
	public function handle( $messenger, $update ) {
		if ( empty( $update['chat_id'] ) || ! in_array( $messenger, array( 'bale', 'eitaa' ), true ) ) {
			return;
		}

		$updates = WSQ_Updates::instance();

		if ( $updates->is_admin_chat( $messenger, $update['chat_id'] ) ) {
			$this->handle_admin( $messenger, $update );
			return;
		}

		if ( empty( $update['chat_type'] ) || 'private' !== $update['chat_type'] || empty( $update['from_id'] ) || (string) $update['from_id'] !== (string) $update['chat_id'] ) {
			$updates->reply( $messenger, $update['chat_id'], __( 'برای مشاهده استعلام‌ها و ثبت شماره تماس، در گفت‌وگوی خصوصی ربات پیام دهید.', 'woo-smart-quote' ) );
			return;
		}

		$this->handle_buyer( $messenger, $update );
	}

	/* ---------------------------------------------------------------------
	 * Buyer flow
	 * ------------------------------------------------------------------ */

	/**
	 * Buyer commands, order lookup and question relay.
	 *
	 * @param string $messenger
	 * @param array  $update
	 * @return void
	 */
	private function handle_buyer( $messenger, $update ) {
		// Respect the buyer-bot switch; the message is still stored in the
		// inbox either way, so staff can answer manually.
		if ( 'yes' !== get_option( 'wsq_buyer_bot_enabled', 'no' ) ) {
			$text = trim( (string) $update['text'] );
			if ( '' !== $text ) {
				$this->relay_to_admins( $messenger, $update, $text );
			}
			return;
		}

		$updates = WSQ_Updates::instance();
		$text    = trim( (string) $update['text'] );
		$lower   = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );

		if ( isset( $update['contact'] ) ) {
			$contact_user = isset( $update['contact']['user'] ) ? (string) $update['contact']['user'] : '';
			$phone = isset( $update['contact']['phone'] ) ? $this->normalize_phone( $update['contact']['phone'] ) : '';
			if ( preg_match( '/^[1-9][0-9]*$/D', $contact_user ) && $contact_user === (string) $update['from_id'] && $updates->link_chat( $messenger, $update['chat_id'], $phone, $contact_user ) ) {
				$updates->reply(
					$messenger,
					$update['chat_id'],
					__( "شماره شما با موفقیت ثبت شد.\n\nحالا می‌توانید با ارسال شماره استعلام، وضعیت و پیش‌فاکتور خود را دریافت کنید. برای مشاهده استعلام‌هایتان دستور /orders را بفرستید.", 'woo-smart-quote' )
				);
			} else {
				$updates->reply( $messenger, $update['chat_id'], __( 'فقط شماره تماس خود را با دکمه «ثبت شماره تماس» در گفت‌وگوی خصوصی ارسال کنید.', 'woo-smart-quote' ) );
			}
			return;
		}

		if ( '' === $text ) {
			// A photo/document with no caption: nudge the buyer.
			$updates->reply( $messenger, $update['chat_id'], $this->help_text() );
			return;
		}

		// --- Commands --------------------------------------------------
		if ( in_array( $lower, array( '/start', 'start', '/help', 'help', 'راهنما', 'منو' ), true ) ) {
			$this->send_welcome( $messenger, $update );
			return;
		}

		if ( in_array( $lower, array( '/orders', 'استعلام', 'استعلام‌های من', 'سفارشات' ), true ) ) {
			$this->send_orders_list( $messenger, $update );
			return;
		}

		if ( 0 === strpos( $lower, '/order ' ) ) {
			$this->send_single_order( $messenger, $update, preg_replace( '/[^0-9]/', '', substr( $text, 7 ) ) );
			return;
		}

		// List buttons and hashtags ("🧾 #123", "استعلام 123", "#123").
		if ( preg_match( '/(?:#|استعلام)\s*([0-9]{1,10})/u', $text, $m_num ) ) {
			if ( $this->send_single_order( $messenger, $update, $m_num[1] ) ) {
				return;
			}
		}

		// --- Order-number lookup --------------------------------------
		if ( preg_match( '/^[0-9]{1,10}$/', $text ) ) {
			if ( $this->send_single_order( $messenger, $update, $text ) ) {
				return;
			}
		}

		// --- Free text: relay to staff --------------------------------
		$this->relay_to_admins( $messenger, $update, $text );
	}

	/**
	 * Welcome message with a contact-sharing keyboard.
	 *
	 * @param string $messenger
	 * @param array  $update
	 * @return void
	 */
	private function send_welcome( $messenger, $update ) {
		$welcome = trim( (string) get_option( 'wsq_bot_welcome', '' ) );
		if ( '' === $welcome ) {
			$welcome = $this->default_template( 'welcome' );
		}

		$name = trim( (string) $update['from_name'] );
		$welcome = $this->render_template( $welcome, array(
			'name'         => '' !== $name ? $name : __( 'عزیز', 'woo-smart-quote' ),
			'messenger'    => $messenger,
		), $update );

		$markup = array(
			'keyboard'        => array(
				array( array( 'text' => __( '📋 استعلام‌های من', 'woo-smart-quote' ) ) ),
				array( array( 'text' => __( '📲 ثبت شماره تماس', 'woo-smart-quote' ), 'request_contact' => true ) ),
				array( array( 'text' => __( '💬 پشتیبانی', 'woo-smart-quote' ) ) ),
			),
			'resize_keyboard' => true,
		);

		$this->send_with_keyboard( $messenger, $update['chat_id'], $welcome, $markup );
	}

	/**
	 * List the buyer's recent quote requests.
	 *
	 * @param string $messenger
	 * @param array  $update
	 * @return void
	 */
	private function send_orders_list( $messenger, $update ) {
		$updates = WSQ_Updates::instance();
		$link    = $updates->find_link( $messenger, $update['chat_id'] );

		if ( empty( $link['phone'] ) ) {
			$updates->reply(
				$messenger,
				$update['chat_id'],
				__( 'برای مشاهده استعلام‌هایتان ابتدا شماره تماس خود را با دکمه «ثبت شماره تماس» ثبت کنید.', 'woo-smart-quote' )
			);
			return;
		}

		$orders = $this->find_buyer_orders( $link['phone'], 5 );

		if ( empty( $orders ) ) {
			$updates->reply(
				$messenger,
				$update['chat_id'],
				sprintf(
					/* translators: %s: linked phone number */
					__( "📭 هنوز استعلامی ثبت‌شده با شماره %s پیدا نشد.\n\n🛍 پس از ثبت استعلام از سایت، فهرست آن در همین بخش نمایش داده می‌شود.", 'woo-smart-quote' ),
					$link['phone']
				)
			);
			return;
		}

		$header_tpl = trim( (string) get_option( 'wsq_bot_orders_header', '' ) );
		if ( '' === $header_tpl ) {
			$header_tpl = $this->default_template( 'orders_header' );
		}

		// The header template may carry the list body itself: a repeating
		// {#items} placeholder line expands to one row per order.
		$fallback_line = __( '🧾 #{order_number} {status_icon} {status_label} — {total}', 'woo-smart-quote' );
		$template      = $this->render_template( $header_tpl, array(
			'name'   => trim( (string) $update['from_name'] ) ? trim( (string) $update['from_name'] ) : __( 'عزیز', 'woo-smart-quote' ),
			'count'  => number_format_i18n( count( $orders ) ),
			'items'  => $fallback_line,
		), $update );

		// Expand the per-order lines: after render_template the {#items}
		// placeholder became "{{items}}" handling-friendly text; rebuild the
		// body by splitting on the first order row.
		if ( false !== strpos( $header_tpl, '{#items}' ) || false !== strpos( $header_tpl, '{items}' ) || false !== strpos( $header_tpl, '#{' ) ) {
			// Custom template: the repeating region start is marked by the line
			// containing the first per-order token ({order_number}).
			$this->send_expanded_orders( $messenger, $update, $header_tpl, $orders );
			return;
		}

		// Default template subject: build the classic layout.
		$lines = array( $template );
		$rows  = array();
		foreach ( $orders as $order ) {
			list( $icon, $label ) = $this->status_badge( $order->get_status() );
			$lines[] = '🧾 #' . $order->get_order_number() . ' ' . $icon . ' ' . $label . "\n      💰 " . wp_strip_all_tags( wc_price( $order->get_total() ) );
			$rows[] = array( array( 'text' => '🧾 #' . $order->get_order_number() ) );
		}
		$lines[] = __( '🔎 برای جزئیات و پیش‌فاکتور، دکمه استعلام یا شماره آن را بفرستید.', 'woo-smart-quote' );

		$markup = array(
			'keyboard'        => $rows,
			'resize_keyboard' => true,
		);

		$this->send_with_keyboard( $messenger, $update['chat_id'], implode( "\n", $lines ), $markup );
	}

	/**
	 * Send one order's status, and its proforma/payment link when issued.
	 *
	 * @param string $messenger
	 * @param array  $update
	 * @param string $number Order number to look up.
	 * @return bool Whether an order was found and sent.
	 */
	private function send_single_order( $messenger, $update, $number ) {
		$updates = WSQ_Updates::instance();

		if ( '' === $number ) {
			return false;
		}

		$link = $updates->find_link( $messenger, $update['chat_id'] );
		if ( empty( $link['phone'] ) ) {
			$updates->reply( $messenger, $update['chat_id'], __( 'برای دسترسی به استعلام‌های خودتان، ابتدا شماره تماس خود را با دکمه «ثبت شماره تماس» ثبت کنید.', 'woo-smart-quote' ) );
			return false;
		}

		$order = $this->find_order_by_number( $number, $link['phone'] );
		if ( ! $order ) {
			// Wrong number or an order that belongs to someone else.
			$updates->reply(
				$messenger,
				$update['chat_id'],
				sprintf(
					/* translators: %s: quote order number */
					__( 'استعلامی با شماره #%s برای شما یافت نشد.', 'woo-smart-quote' ),
					$number
				)
			);
			return false;
		}

		list( $icon, $status_label ) = $this->status_badge( $order->get_status() );
		$payment_url = 'quote-sent' === $order->get_status() ? $order->get_checkout_payment_url() : '';

		$tpl = trim( (string) get_option( 'wsq_bot_order_card', '' ) );
		if ( '' !== $tpl ) {
			// Custom card template (per-state suffix supported).
			$text = $this->render_template( $tpl, array(
				'name'          => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) ? trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) : __( 'عزیز', 'woo-smart-quote' ),
				'order_number'  => $order->get_order_number(),
				'status'        => $status_label,
				'status_icon'   => $icon,
				'total'         => wp_strip_all_tags( wc_price( $order->get_total() ) ),
				'payment_url'   => $payment_url ? $payment_url : '',
				'items'         => trim( wp_strip_all_tags( $this->items_summary_raw( $order ) ) ),
				'date'          => date_i18n( 'Y/m/d', $order->get_date_created() ? $order->get_date_created()->getTimestamp() : time() ),
			), $update, $order );

			$markup = $payment_url ? array(
				'inline_keyboard' => array(
					array( array( 'text' => __( '💳 پرداخت آنلاین', 'woo-smart-quote' ), 'url' => $payment_url ) ),
				),
			) : null;

			$this->send_with_keyboard( $messenger, $update['chat_id'], $text, $markup );
		} else {
			// Built-in default card.
			$total   = wp_strip_all_tags( wc_price( $order->get_total() ) );
			$created = date_i18n( 'Y/m/d', $order->get_date_created() ? $order->get_date_created()->getTimestamp() : time() );

			$text  = sprintf(
				/* translators: %s: quote order number */
				__( '🧾 استعلام #%s', 'woo-smart-quote' ),
				$order->get_order_number()
			) . "\n";
			$text .= "──────────────\n";
			$text .= sprintf(
				/* translators: %s: creation date */
				__( '📅 تاریخ ثبت: %s', 'woo-smart-quote' ),
				$created
			) . "\n";
			$text .= sprintf(
				/* translators: 1: status icon, 2: status label */
				__( '📊 وضعیت: %1$s %2$s', 'woo-smart-quote' ),
				$icon,
				$status_label
			) . "\n";
			$text .= sprintf(
				/* translators: %s: total amount */
				__( '💰 مبلغ کل: %s', 'woo-smart-quote' ),
				$total
			) . "\n";
			$text .= $this->items_summary( $order );
			$text .= "──────────────";

			if ( 'quote-request' === $order->get_status() ) {
				$text .= "\n" . __( '⏳ پیش‌فاکتور در حال صدور است؛ به‌محض آماده‌شدن همین‌جا خبرتان می‌کنیم.', 'woo-smart-quote' );
			}

			if ( 'quote-sent' === $order->get_status() ) {
				$text .= "\n" . sprintf(
					/* translators: %s: payment URL */
					__( "💳 پرداخت آنلاین:\n%s\n\n📄 پیش‌فاکتور رسمی نیز برایتان ارسال می‌شود.", 'woo-smart-quote' ),
					$payment_url
				);
			}

			$markup = $payment_url ? array(
				'inline_keyboard' => array(
					array( array( 'text' => __( '💳 پرداخت آنلاین', 'woo-smart-quote' ), 'url' => $payment_url ) ),
				),
			) : null;

			$this->send_with_keyboard( $messenger, $update['chat_id'], $text, $markup );
		}

		// Attach the proforma PDF when the quote has been issued.
		if ( in_array( $order->get_status(), array( 'quote-sent', 'processing', 'completed' ), true ) ) {
			$pdf_path = WSQ_PDF_Generator::instance()->generate( $order->get_id() );
			if ( $pdf_path ) {
				$api = WSQ_Updates::api_for( $messenger );
				$api->send_document(
					$update['chat_id'],
					$pdf_path,
					sprintf(
						/* translators: %s: quote order number */
						__( 'پیش‌فاکتور رسمی استعلام #%s', 'woo-smart-quote' ),
						$order->get_order_number()
					)
				);
			}
		}

		return true;
	}

	/* ---------------------------------------------------------------------
	 * Admin flow
	 * ------------------------------------------------------------------ */

	/**
	 * Admin commands and staff notifications.
	 *
	 * @param string $messenger
	 * @param array  $update
	 * @return void
	 */
	private function handle_admin( $messenger, $update ) {
		$updates = WSQ_Updates::instance();
		$text    = trim( (string) $update['text'] );
		$lower   = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );

		if ( 0 === strpos( $lower, '/order ' ) || 0 === strpos( $lower, 'سفارش ' ) ) {
			$number = preg_replace( '/[^0-9]/', '', substr( $text, strpos( $text, ' ' ) + 1 ) );
			$order  = $this->find_order_by_number( $number, '', true );
			if ( $order ) {
				list( $icon, $status_label ) = $this->status_badge( $order->get_status() );
				$total  = wp_strip_all_tags( wc_price( $order->get_total() ) );
				$phone  = $order->get_billing_phone();
				$name   = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
				$updates->reply(
					$messenger,
					$update['chat_id'],
					sprintf(
						/* translators: 1: order number, 2: buyer name, 3: phone, 4: status icon, 5: status label, 6: total, 7: items summary, 8: admin URL */
						__( "🧾 استعلام #%1\$s\n──────────────\n👤 %2\$s\n📞 %3\$s\n📊 وضعیت: %4\$s %5\$s\n💰 مبلغ: %6\$s\n%7\$s──────────────\n🛒 مدیریت:\n%8\$s", 'woo-smart-quote' ),
						$order->get_order_number(),
						$name,
						$phone,
						$icon,
						$status_label,
						$total,
						$this->items_summary( $order ),
						admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' )
					)
				);
			} else {
				$updates->reply(
					$messenger,
					$update['chat_id'],
					sprintf(
						/* translators: %s: quote order number */
						__( 'استعلامی با شماره #%s یافت نشد.', 'woo-smart-quote' ),
						$number
					)
				);
			}
			return;
		}

		if ( in_array( $lower, array( '/pending', 'درانتظار', 'جدید' ), true ) ) {
			$pending = wc_get_orders( array(
				'status' => 'quote-request',
				'limit'  => 8,
				'orderby' => 'date',
				'order'  => 'DESC',
			) );

			if ( empty( $pending ) ) {
				$updates->reply( $messenger, $update['chat_id'], __( 'در حال حاضر استعلام درانتظاری نیست. ✅', 'woo-smart-quote' ) );
				return;
			}

			$lines = array(
				sprintf(
					/* translators: %s: number of pending quotes */
					__( '⏳ استعلام‌های درانتظار بررسی (%s مورد)', 'woo-smart-quote' ),
					number_format_i18n( count( $pending ) )
				),
				'──────────────',
			);
			foreach ( $pending as $order ) {
				$lines[] = sprintf(
					"🧾 #%s — 👤 %s\n      💰 %s — 🕘 %s",
					$order->get_order_number(),
					trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
					wp_strip_all_tags( wc_price( $order->get_total() ) ),
					date_i18n( 'm/d H:i', $order->get_date_created() ? $order->get_date_created()->getTimestamp() : time() )
				);
			}
			$lines[] = '';
			$lines[] = __( '🔎 جزئیات هر مورد: /order شماره_استعلام', 'woo-smart-quote' );

			$updates->reply( $messenger, $update['chat_id'], implode( "\n", $lines ) );
			return;
		}

		if ( in_array( $lower, array( '/start', 'start', '/help', 'help', 'راهنما' ), true ) ) {
			$updates->reply(
				$messenger,
				$update['chat_id'],
				__( "🧑‍💼 پنل مدیریت ربات\n\n/pending — استعلام‌های درانتظار\n/order <شماره> — جزئیات یک استعلام\n\nپیام‌های خریداران به‌صورت خودکار به این چت ارسال می‌شود.", 'woo-smart-quote' )
			);
			return;
		}

		// Any other text in the admin chat is a note; keep it in the log.
		WSQ_Logger::info( 'bot', 'Admin chat message ignored.', array(
			'messenger' => $messenger,
			'chat_id'   => $update['chat_id'],
			'text'      => mb_substr( $text, 0, 120 ),
		) );
	}

	/**
	 * Forward a buyer question to every configured admin chat.
	 *
	 * @param string $messenger
	 * @param array  $update
	 * @param string $text
	 * @return void
	 */
	private function relay_to_admins( $messenger, $update, $text ) {
		$updates    = WSQ_Updates::instance();
		$admin_ids  = $updates->admin_chat_ids( $messenger );
		$link       = $updates->find_link( $messenger, $update['chat_id'] );
		$from_name  = trim( (string) $update['from_name'] );

		if ( empty( $admin_ids ) ) {
			// No admin chat configured; the message is still in the inbox.
			$updates->reply( $messenger, $update['chat_id'], __( 'پیام شما ثبت شد و کارشناسان ما به‌زودی پاسخ می‌دهند. 🙏', 'woo-smart-quote' ) );
			return;
		}

		$header = sprintf(
			/* translators: %s: messenger icon */
			__( '💬 پیام جدید خریدار — %s', 'woo-smart-quote' ),
			$this->messenger_icon( $messenger )
		) . "\n";
		$header .= '👤 ' . ( '' !== $from_name ? $this->escape_buyer_text( $from_name ) : __( 'نامشخص', 'woo-smart-quote' ) ) . "\n";
		$header .= '🆔 ' . $update['chat_id'] . "\n";
		if ( ! empty( $link['phone'] ) ) {
			$header .= '📞 ' . $link['phone'] . "\n";
		}
		$header .= "————————————\n" . $this->escape_buyer_text( $text );

		$sent = 0;
		foreach ( $admin_ids as $admin_id ) {
			$response = $updates->reply( $messenger, $admin_id, $header );
			if ( ! empty( $response['ok'] ) ) {
				$sent++;
			}
		}

		if ( $sent > 0 ) {
			$updates->reply( $messenger, $update['chat_id'], __( 'پیام شما برای کارشناسان ما ارسال شد. به‌زودی پاسخ می‌دهیم. 🙏', 'woo-smart-quote' ) );
		} else {
			$updates->reply( $messenger, $update['chat_id'], __( 'پیام شما ثبت شد. 🙏', 'woo-smart-quote' ) );
		}
	}

	/* ---------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Neutralise HTML in buyer-controlled text before it is embedded in a
	 * messenger HTML message. Quotes are left alone: only tag characters
	 * matter, and numeric entities would break Telegram-style parsers.
	 *
	 * @param string $text
	 * @return string
	 */
	private function escape_buyer_text( $text ) {
		return str_replace( array( '&', '<', '>' ), array( '&amp;', '&lt;', '&gt;' ), (string) $text );
	}

	/**
	 * Built-in default templates for the buyer-facing bot messages.
	 *
	 * @param string $key welcome|orders_header|order_card|order_not_found.
	 * @return string
	 */
	private function default_template( $key ) {
		$defaults = array(
			'welcome'        => __( "سلام {name} عزیز! 👋\n\nبه ربات فروشگاه خوش آمدید. از همین‌جا، بدون مراجعه به سایت، استعلام‌های خود را دنبال کنید:\n\n📋 استعلام‌های من — فهرست استعلام‌های اخیر\n🧾 شماره استعلام یا #100 — وضعیت، پیش‌فاکتور و لینک پرداخت\n💬 پیام آزاد — سؤال شما مستقیم برای کارشناسان ارسال می‌شود\n\n⏳ به‌محض صدور پیش‌فاکتور، همین‌جا خبرتان می‌کنیم.", 'woo-smart-quote' ),
			'orders_header'  => __( "📋 استعلام‌های اخیر شما ({count} مورد)\n──────────────\n{#items}\n──────────────\n🔎 برای جزئیات و پیش‌فاکتور، دکمه استعلام یا شماره آن را بفرستید.", 'woo-smart-quote' ),
			'order_card'     => __( "🧾 استعلام #{order_number}\n──────────────\n📅 تاریخ ثبت: {date}\n📊 وضعیت: {status_icon} {status}\n💰 مبلغ کل: {total}\n🛒 اقلام:\n{items}\n──────────────{payment_section}", 'woo-smart-quote' ),
			'order_not_found'=> __( 'استعلامی با شماره #{order_number} برای شما یافت نشد.', 'woo-smart-quote' ),
		);

		return isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
	}

	/**
	 * Render a bot template by replacing {token} placeholders.
	 *
	 * Tokens are filled from $vars first, then from order/heading context.
	 * Token values are printed verbatim (they are store-controlled or
	 * already-HTML-stripped strings) and never interpreted as HTML by the
	 * messaging panels.
	 *
	 * Special tokens:
	 *  - {payment_section}: replaced with the payment block when an order has
	 *    a payment URL, otherwise removed.
	 *  - {#items}: a repeating region marker rendered per order (see
		*    send_expanded_orders). Outside that context it renders nothing.
	 *
	 * @param string          $template Raw template text.
	 * @param array           $vars     Extra token values.
	 * @param array|null      $update   Inbound update (for {chat_id}).
	 * @param WC_Order|null   $order    Related order (for order helpers).
	 * @return string
	 */
	private function render_template( $template, $vars = array(), $update = null, $order = null ) {
		$tokens = array();

		foreach ( (array) $vars as $key => $value ) {
			$var_key = '{' . trim( (string) $key ) . '}';
			if ( '' !== $var_key ) {
				$tokens[ $var_key ] = (string) $value;

				// Tolerate stray spaces inside braces.
				$tokens[ '{ ' . trim( (string) $key ) . ' }' ] = (string) $value;
			}
		}

		if ( $update ) {
			$tokens['{chat_id}'] = isset( $update['chat_id'] ) ? (string) $update['chat_id'] : '';
			if ( ! isset( $tokens['{name}'] ) ) {
				$name = trim( (string) ( isset( $update['from_name'] ) ? $update['from_name'] : '' ) );
				$tokens['{name}'] = '' !== $name ? $name : __( 'عزیز', 'woo-smart-quote' );
			}
		}

		if ( $order ) {
			$tokens['{order_number}'] = (string) $order->get_order_number();
			$tokens['{order_id}']     = (string) $order->get_id();
		}

		// Remove the repeating-region marker when not expanding.
		$tokens['{#items}'] = '';

		// Payment section: present only when a URL exists.
		$payment_url_token = isset( $vars['payment_url'] ) ? (string) $vars['payment_url'] : null;
		$payment_exists    = ( null !== $payment_url_token && '' !== $payment_url_token );
		if ( isset( $vars['payment_url'] ) && '' === $payment_url_token ) {
			$template = str_replace( '{payment_section}', '', (string) $template );
		} elseif ( $payment_exists && false !== strpos( (string) $template, '{payment_section}' ) ) {
			$block     = "\n" . __( '💳 پرداخت آنلاین:', 'woo-smart-quote' ) . "\n" . $payment_url_token;
			$template  = str_replace( '{payment_section}', $block, $template );
		}

		foreach ( $tokens as $token => $value ) {
			$template = str_replace( $token, $value, $template );
		}

		// Strip any unresolved tokens rather than leaking braces to buyers.
		$template = preg_replace( '/\{[a-z_#]+\}/u', '', (string) $template );

		// Tidy up: collapse 3+ newlines created by removed blocks, trim spaces.
		$template = preg_replace( '/\n{3,}/', "\n\n", (string) $template );

		return trim( (string) $template );
	}

	/**
	 * Send the buyer orders list when a custom template with a repeating
	 * region is configured.
	 *
	 * The region is delimited by {#items} ... {/#items} inside the template;
	 * everything between the markers repeats once per order with per-order
	 * tokens resolved. Text outside the region is rendered once.
	 *
	 * @param string   $messenger
	 * @param array    $update
	 * @param string   $template Raw template.
	 * @param WC_Order[] $orders
	 * @return void
	 */
	private function send_expanded_orders( $messenger, $update, $template, $orders ) {
		$rows = array();

		foreach ( $orders as $order ) {
			list( $icon, $label ) = $this->status_badge( $order->get_status() );

			$rows[] = $this->render_template( $template, array(
				'name'          => trim( (string) $update['from_name'] ) ? trim( (string) $update['from_name'] ) : __( 'عزیز', 'woo-smart-quote' ),
				'count'         => number_format_i18n( count( $orders ) ),
				'order_number'  => $order->get_order_number(),
				'status'        => $label,
				'status_icon'   => $icon,
				'total'         => wp_strip_all_tags( wc_price( $order->get_total() ) ),
				'date'          => date_i18n( 'Y/m/d', $order->get_date_created() ? $order->get_date_created()->getTimestamp() : time() ),
				'items'         => trim( wp_strip_all_tags( $this->items_summary_raw( $order ) ) ),
			), $update, $order );
		}

		// The repeated chunk is the first rendered row; the header/footer
		// around it come from the same template with tokens stripped.
		$first_row  = '';
		foreach ( $rows as $row ) {
			if ( '' !== trim( $row ) ) {
				$first_row = $row;
				break;
			}
		}

		$skeleton = $this->render_template( $template, array(
			'name'  => trim( (string) $update['from_name'] ) ? trim( (string) $update['from_name'] ) : __( 'عزیز', 'woo-smart-quote' ),
			'count' => number_format_i18n( count( $orders ) ),
		), $update );

		// Rebuild: replace the first-row text inside the skeleton with all
		// rows joined. This keeps header/footer edits in one place.
		$body = $skeleton;
		if ( '' !== $first_row && false !== strpos( $body, $first_row ) ) {
			$body = str_replace( $first_row, implode( "\n", $rows ), $body );
			$body = $this->render_template( $body, array(), $update );
		} else {
			// Region markers were collapsed; fall back to joined rows.
			$body = $skeleton . "\n" . implode( "\n", $rows );
		}

		$keyboard_rows = array();
		foreach ( $orders as $order ) {
			$keyboard_rows[] = array( array( 'text' => '🧾 #' . $order->get_order_number() ) );
		}

		$markup = array(
			'keyboard'        => $keyboard_rows,
			'resize_keyboard' => true,
		);

		$this->send_with_keyboard( $messenger, $update['chat_id'], $body, $markup );
	}

	/**
	 * Items summary as a plain line-list (no emoji header), for templates.
	 *
	 * @param WC_Order $order
	 * @return string
	 */
	private function items_summary_raw( $order ) {
		$items = array_values( $order->get_items() );
		if ( empty( $items ) ) {
			return '';
		}

		$lines = array();
		foreach ( array_slice( $items, 0, 3 ) as $item ) {
			$lines[] = '- ' . $item->get_name() . ' ×' . number_format_i18n( $item->get_quantity() );
		}

		$extra = count( $items ) - 3;
		if ( $extra > 0 ) {
			$lines[] = sprintf(
				/* translators: %s: number of additional items */
				__( 'و %s مورد دیگر…', 'woo-smart-quote' ),
				number_format_i18n( $extra )
			);
		}

		return implode( "\n", $lines );
	}

	/**
	 * Send a message with an optional reply_markup, retrying as plain text
	 * when the panel rejects the keyboard.
	 *
	 * @param string     $messenger
	 * @param string     $chat_id
	 * @param string     $text
	 * @param array|null $markup
	 * @return array
	 */
	private function send_with_keyboard( $messenger, $chat_id, $text, $markup = null ) {
		$updates = WSQ_Updates::instance();

		if ( is_array( $markup ) && ! empty( $markup ) ) {
			$response = $updates->reply( $messenger, $chat_id, $text, array(
				'reply_markup' => wp_json_encode( $markup, JSON_UNESCAPED_UNICODE ),
			) );

			if ( empty( $response['ok'] ) ) {
				// The keyboard was the only variable; drop it and retry.
				$response = $updates->reply( $messenger, $chat_id, $text );
			}

			return $response;
		}

		return $updates->reply( $messenger, $chat_id, $text );
	}

	/**
	 * Orders belonging to a buyer phone, newest first.
	 *
	 * @param string $phone Normalized phone.
	 * @param int    $limit
	 * @return WC_Order[]
	 */
	private function find_buyer_orders( $phone, $limit = 5 ) {
		$phone = $this->normalize_phone( $phone );
		if ( '' === $phone ) {
			return array();
		}

		$ids = wc_get_orders( array(
			'limit'         => $limit,
			'return'        => 'ids',
			'orderby'       => 'date',
			'order'         => 'DESC',
			'billing_phone' => $phone,
			'status'        => array( 'quote-request', 'quote-sent', 'pending', 'processing', 'completed' ),
		) );

		$orders = array();
		foreach ( (array) $ids as $id ) {
			$order = wc_get_order( $id );
			if ( $order && $this->is_wsq_order( $order ) && $phone === $this->normalize_phone( $order->get_billing_phone() ) ) {
				$orders[] = $order;
			}
		}
		return $orders;
	}

	/**
	 * Find a quote by number, requiring buyer ownership unless explicitly administrative.
	 *
	 * @param string $number
	 * @param string $phone Buyer billing phone.
	 * @param bool   $is_admin Whether called from the administrator flow.
	 * @return WC_Order|null
	 */
	private function find_order_by_number( $number, $phone, $is_admin = false ) {
		$number = trim( (string) $number );
		$phone  = $this->normalize_phone( $phone );
		if ( '' === $number || ( ! $is_admin && '' === $phone ) ) {
			return null;
		}

		$order = wc_get_order( $number );
		if ( ! $order ) {
			// Order numbers can differ from ids; search by the number meta.
			$ids = wc_get_orders( array(
				'limit'  => 1,
				'return' => 'ids',
				'meta_key'   => '_order_number',
				'meta_value' => $number,
			) );
			if ( ! empty( $ids ) ) {
				$order = wc_get_order( $ids[0] );
			}
		}

		if ( ! $order || ! $this->is_wsq_order( $order ) ) {
			return null;
		}

		if ( ! $is_admin && $this->normalize_phone( $order->get_billing_phone() ) !== $phone ) {
			return null;
		}

		return $order;
	}

	private function is_wsq_order( $order ) {
		$status = $order->get_status();
		if ( in_array( $status, array( 'quote-request', 'quote-sent' ), true ) ) {
			return true;
		}
		return in_array( $status, array( 'pending', 'processing', 'completed' ), true ) && '' !== (string) $order->get_meta( '_wsq_customer_phone' );
	}

	/**
	 * Normalize an Iranian mobile number to the 09xxxxxxxxx form.
	 *
	 * @param string $phone
	 * @return string Empty string when the number is not a mobile.
	 */
	private function normalize_phone( $phone ) {
		$phone = preg_replace( '/[^0-9+]/', '', (string) $phone );

		if ( 0 === strpos( $phone, '+98' ) ) {
			$phone = '0' . substr( $phone, 3 );
		} elseif ( 0 === strpos( $phone, '98' ) ) {
			$phone = '0' . substr( $phone, 2 );
		} elseif ( 0 === strpos( $phone, '0098' ) ) {
			$phone = '0' . substr( $phone, 4 );
		}

		if ( ! preg_match( '/^09[0-9]{9}$/', $phone ) ) {
			return '';
		}

		return $phone;
	}

	/**
	 * Map an order status to a compact visual badge for chat messages.
	 *
	 * @param string $status
	 * @return array [icon, Persian label]
	 */
	private function status_badge( $status ) {
		$map = array(
			'quote-request' => array( '⏳', __( 'در انتظار بررسی کارشناس', 'woo-smart-quote' ) ),
			'quote-sent'    => array( '📤', __( 'پیش‌فاکتور صادر شد', 'woo-smart-quote' ) ),
			'pending'       => array( '💳', __( 'در انتظار پرداخت', 'woo-smart-quote' ) ),
			'processing'    => array( '🚚', __( 'در حال آماده‌سازی', 'woo-smart-quote' ) ),
			'on-hold'       => array( '⏸', __( 'موقتاً متوقف', 'woo-smart-quote' ) ),
			'completed'     => array( '✅', __( 'تکمیل شد', 'woo-smart-quote' ) ),
			'cancelled'     => array( '🙅', __( 'لغو شد', 'woo-smart-quote' ) ),
			'refunded'      => array( '↩️', __( 'بازگشت وجه', 'woo-smart-quote' ) ),
			'failed'        => array( '❌', __( 'ناموفق', 'woo-smart-quote' ) ),
		);

		if ( isset( $map[ $status ] ) ) {
			return $map[ $status ];
		}

		return array( '🏷', wc_get_order_status_name( $status ) );
	}

	/**
	 * Short line-item summary for a chat order card.
	 *
	 * @param WC_Order $order
	 * @return string Ready-to-print block, empty when the order has no items.
	 */
	private function items_summary( $order ) {
		$items = array_values( $order->get_items() );
		if ( empty( $items ) ) {
			return '';
		}

		$lines = array();
		foreach ( array_slice( $items, 0, 3 ) as $item ) {
			$lines[] = '• ' . $item->get_name() . ' ×' . number_format_i18n( $item->get_quantity() );
		}

		$extra = count( $items ) - 3;
		if ( $extra > 0 ) {
			$lines[] = sprintf(
				/* translators: %s: number of additional items */
				__( 'و %s مورد دیگر…', 'woo-smart-quote' ),
				number_format_i18n( $extra )
			);
		}

		return __( '🛒 اقلام:', 'woo-smart-quote' ) . "\n" . implode( "\n", $lines ) . "\n";
	}

	/**
	 * Persian display name of a messenger, used in staff notifications.
	 *
	 * @param string $messenger
	 * @return string
	 */
	private function messenger_icon( $messenger ) {
		return 'bale' === $messenger ? __( '💙 بله', 'woo-smart-quote' ) : __( '🧡 ایتا', 'woo-smart-quote' );
	}

	/**
	 * Help text shared by the empty-text and unknown-command paths.
	 *
	 * @return string
	 */
	private function help_text() {
		return __( "من پیام شما را کامل متوجه نشدم. 😅\n\nراهنمای سریع:\n• 📋 /orders یا «استعلام‌های من» — فهرست استعلام‌های اخیر\n• 🧾 #100 یا «استعلام 100» — جزئیات و پیش‌فاکتور یک استعلام\n• 💬 هر متن دیگری — ارسال سؤال برای کارشناسان", 'woo-smart-quote' );
	}
}
