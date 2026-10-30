<?php
/**
 * Handle Quote Order Creation, Itemized Pricing & Customer Proposal Dispatch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSQ_Order {

	protected static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	protected function __construct() {
		// AJAX submission from frontend
		add_action( 'wp_ajax_wsq_submit_quote', array( $this, 'ajax_submit_quote' ) );
		add_action( 'wp_ajax_nopriv_wsq_submit_quote', array( $this, 'ajax_submit_quote' ) );

		// WooCommerce Order Admin Meta Box
		add_action( 'add_meta_boxes', array( $this, 'add_quote_meta_box' ) );

		// Handle saving itemized prices and sending proposal
		add_action( 'admin_post_wsq_save_and_send_proposal', array( $this, 'handle_save_and_send_proposal' ) );
		// Same handler over admin-ajax.php: the metabox posts via JS because a
		// nested <form> is invalid HTML inside the order editor.
		add_action( 'wp_ajax_wsq_save_and_send_proposal', array( $this, 'handle_save_and_send_proposal' ) );

		// Live token probe behind the metabox «بررسی اتصال ربات» button.
		add_action( 'wp_ajax_wsq_check_bot_connection', array( $this, 'ajax_check_bot_connection' ) );

		// Shared clipboard helper for the proposal metabox.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// A sent quote is a real invoice, but WooCommerce does not treat the
		// custom status as payable by default.
		add_filter( 'woocommerce_valid_order_statuses_for_payment', array( $this, 'allow_payment_for_issued_quote' ), 10, 2 );
		add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'payment_complete_status_for_quote' ), 10, 2 );

		// Note: buyer notification on 'quote-sent' is handled solely by
		// WSQ_Buyer_Notifier (which hooks the same status change); do not
		// dispatch again here or the buyer receives duplicates.
	}

	/**
	 * Let a priced, unpaid quote reach the payment gateway.
	 *
	 * @param string[] $statuses Payable statuses.
	 * @param WC_Order $order    Order being checked.
	 * @return string[]
	 */
	public function allow_payment_for_issued_quote( $statuses, $order = null ) {
		if ( $order instanceof WC_Order && self::is_payable_quote( $order ) ) {
			$statuses[] = 'quote-sent';
		}
		return $statuses;
	}

	/**
	 * Settle a paid quote into a normal WooCommerce fulfilment status.
	 *
	 * @param string $status   Status chosen by WooCommerce.
	 * @param int    $order_id Order id.
	 * @return string
	 */
	public function payment_complete_status_for_quote( $status, $order_id = 0 ) {
		$order = wc_get_order( $order_id );
		if ( $order instanceof WC_Order && 'quote-sent' === $order->get_status() ) {
			return $order->needs_processing() ? 'processing' : 'completed';
		}
		return $status;
	}

	/**
	 * A quote may only be paid after it has been priced and while unpaid.
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	public static function is_payable_quote( $order ) {
		return 'quote-sent' === $order->get_status()
			&& ! $order->is_paid()
			&& (float) $order->get_total() > 0;
	}

	/**
	 * Whether line prices may still be changed on this order.
	 *
	 * @param WC_Order $order Order.
	 * @return true|WP_Error
	 */
	public static function guard_priceable_order( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return new WP_Error( 'wsq_invalid_order', __( 'سفارش یافت نشد.', 'woo-smart-quote' ), array( 'status' => 404 ) );
		}
		if ( ! in_array( $order->get_status(), array( 'quote-request', 'quote-sent' ), true ) ) {
			return new WP_Error( 'wsq_not_quote', __( 'فقط استعلام‌های در انتظار یا پیش‌فاکتورهای صادرشده قابل قیمت‌گذاری هستند.', 'woo-smart-quote' ), array( 'status' => 409 ) );
		}
		if ( $order->is_paid() ) {
			return new WP_Error( 'wsq_already_paid', __( 'این سفارش پرداخت شده و قیمت‌های آن قابل تغییر نیست.', 'woo-smart-quote' ), array( 'status' => 409 ) );
		}
		return true;
	}

	/**
	 * Validate submitted line prices before anything is written.
	 *
	 * @param WC_Order $order       Order.
	 * @param array    $prices      Map of item id => unit price.
	 * @param bool     $require_all Whether every line must be present.
	 * @return array|WP_Error Map of item id => nonnegative float.
	 */
	public static function validate_price_submission( $order, $prices, $require_all = true ) {
		$items = array();
		foreach ( $order->get_items() as $item_id => $item ) {
			$items[ (int) $item_id ] = $item;
		}
		if ( empty( $items ) ) {
			return new WP_Error( 'wsq_no_items', __( 'این استعلام قلمی برای قیمت‌گذاری ندارد.', 'woo-smart-quote' ), array( 'status' => 409 ) );
		}

		$normalized = array();
		foreach ( (array) $prices as $item_id => $price ) {
			$item_id = absint( $item_id );
			if ( ! isset( $items[ $item_id ] ) ) {
				return new WP_Error( 'wsq_unknown_item', __( 'یکی از اقلام ارسالی به این استعلام تعلق ندارد.', 'woo-smart-quote' ), array( 'status' => 400 ) );
			}
			if ( is_bool( $price ) || ! is_numeric( $price ) ) {
				return new WP_Error( 'wsq_invalid_price', __( 'قیمت واردشده معتبر نیست.', 'woo-smart-quote' ), array( 'status' => 400 ) );
			}
			$price = (float) $price;
			if ( ! is_finite( $price ) || $price < 0 ) {
				return new WP_Error( 'wsq_invalid_price', __( 'قیمت واردشده معتبر نیست.', 'woo-smart-quote' ), array( 'status' => 400 ) );
			}
			$normalized[ $item_id ] = $price;
		}

		if ( empty( $normalized ) ) {
			return new WP_Error( 'wsq_missing_prices', __( 'هیچ قیمتی برای ذخیره ارسال نشده است.', 'woo-smart-quote' ), array( 'status' => 400 ) );
		}
		if ( $require_all && count( $normalized ) !== count( $items ) ) {
			return new WP_Error( 'wsq_missing_prices', __( 'قیمت همه اقلام باید مشخص شود.', 'woo-smart-quote' ), array( 'status' => 400 ) );
		}

		return $normalized;
	}

	/**
	 * Send the AJAX/redirect response for a rejected pricing request.
	 *
	 * @param WP_Error $error  Error.
	 * @param bool     $is_ajax Whether the request came from admin-ajax.
	 */
	private function reject_pricing( $error, $is_ajax ) {
		$message = $error->get_error_message();
		if ( $is_ajax ) {
			wp_send_json_error( array( 'message' => $message ) );
		}
		wp_die( esc_html( $message ) );
	}

	/**
	 * Load the clipboard helper on order edit screens.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'post.php' !== $hook && 'woocommerce_page_wc-orders' !== $hook ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'shop_order' !== $screen->post_type && 'woocommerce_page_wc-orders' !== $screen->id && 'wc-orders' !== $screen->id ) {
			return;
		}

		wp_enqueue_script( 'woo-smart-quote-clipboard', WSQ_PLUGIN_URL . 'assets/js/wsq-clipboard.js', array(), WSQ_VERSION, false );
	}

	/**
	 * Add Meta Box in WooCommerce Order Edit Screen
	 */
	public function add_quote_meta_box() {
		$screen = class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) 
			&& wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';

		add_meta_box(
			'wsq_quote_pricing_box',
			__( 'قیمت‌گذاری تفکیکی اقلام و ارسال به خریدار (نرخنگار)', 'woo-smart-quote' ),
			array( $this, 'render_quote_pricing_meta_box' ),
			$screen,
			'normal',
			'high'
		);
	}

	/**
	 * Render Meta Box with Item-by-Item Pricing Table
	 */
	public function render_quote_pricing_meta_box( $post_or_order_object ) {
		$order = ( $post_or_order_object instanceof WC_Order ) ? $post_or_order_object : wc_get_order( $post_or_order_object->ID );
		if ( ! $order ) {
			return;
		}

		$order_id    = $order->get_id();
		$status      = $order->get_status();
		$payment_url = $order->get_checkout_payment_url();
		$items       = $order->get_items();
		$validity    = $order->get_meta( '_wsq_quote_validity' );
		if ( empty( $validity ) ) {
			$validity = __( '۲۴ ساعت کاری', 'woo-smart-quote' );
		}

		// Which bots can reach this buyer, and whether each bot could send at
		// all: a chat is linked when the buyer pressed the contact button in
		// that bot with the order phone, and an empty token means the shop
		// cannot send through that bot no matter who is linked.
		$notifier     = WSQ_Buyer_Notifier::instance();
		$link_phone   = $notifier->normalize_mobile( $notifier->get_customer_phone( $order ) );
		$bot_channels = array();

		foreach ( array(
			'bale'  => array(
				'label' => __( 'بله', 'woo-smart-quote' ),
				'icon'  => 'chat',
			),
			'eitaa' => array(
				'label' => __( 'ایتا', 'woo-smart-quote' ),
				'icon'  => 'send',
			),
		) as $slug => $meta ) {
			$chat      = '' !== $link_phone ? WSQ_Updates::instance()->find_chat_by_phone( $slug, $link_phone ) : '';
			$has_token = '' !== trim( (string) get_option( 'wsq_' . $slug . '_token', '' ) );

			if ( ! $has_token ) {
				$state = 'is-warn';
				$text  = __( 'توکن تنظیم نشده', 'woo-smart-quote' );
				$hint  = __( 'توکن این ربات در تنظیمات افزونه خالی است؛ ارسال از این پیام‌رسان انجام نمی‌شود.', 'woo-smart-quote' );
			} elseif ( '' !== $chat ) {
				$state = 'is-on';
				$text  = __( 'چت متصل', 'woo-smart-quote' );
				$hint  = __( 'خریدار در این ربات چت لینک‌شده دارد و پیش‌فاکتور به آن ارسال می‌شود.', 'woo-smart-quote' );
			} else {
				$state = 'is-off';
				$text  = __( 'بدون چت', 'woo-smart-quote' );
				$hint  = __( 'خریدار با این شماره در ربات شماره‌اش را ثبت نکرده است.', 'woo-smart-quote' );
			}

			$bot_channels[] = array(
				'label' => $meta['label'],
				'icon'  => $meta['icon'],
				'state' => $state,
				'text'  => $text,
				'hint'  => $hint,
			);
		}
		?>
		<div class="wsq-pricing-metabox">
			<style>
				/* WordPress Native Enterprise Styling for Order Pricing */
				.wsq-pricing-metabox {
					--wsq-border: #c3c4c7;
					--wsq-border-light: #dcdcde;
					--wsq-bg-light: #f6f7f7;
					--wsq-ink: #1d2327;
					--wsq-muted: #50575e;
					--wsq-primary: #2271b1;
					--wsq-primary-hover: #135e96;
					--wsq-ok: #007017;
					--wsq-ok-bg: #edfaef;
					--wsq-ok-border: #68de7c;
					--wsq-warn: #996800;
					--wsq-warn-bg: #fcf9e8;
					--wsq-warn-border: #dba617;
					--wsq-err: #d63638;
					--wsq-err-bg: #fcf0f1;
					--wsq-err-border: #f1aeb5;
					font-family: inherit;
				}
				.wsq-pricing-metabox .wsq-icon { width: 1.15em; height: 1.15em; flex-shrink: 0; vertical-align: -0.16em; }
				.wsq-pricing-metabox .button > .wsq-icon { margin-inline-end: 5px; }
				.wsq-pricing-metabox .wsq-mb-header {
					display: flex;
					justify-content: space-between;
					align-items: center;
					background: var(--wsq-bg-light);
					padding: 12px 16px;
					border-radius: 4px;
					margin-bottom: 16px;
					border: 1px solid var(--wsq-border);
					gap: 14px;
					flex-wrap: wrap;
				}
				.wsq-pricing-metabox .wsq-mb-status-lead {
					display: inline-flex;
					align-items: center;
					gap: 8px;
					font-size: 13px;
				}
				.wsq-pricing-metabox .wsq-mb-status-badge {
					display: inline-flex;
					align-items: center;
					gap: 5px;
					padding: 3px 10px;
					border-radius: 3px;
					font-size: 12px;
					font-weight: 700;
					border: 1px solid transparent;
				}
				.wsq-pricing-metabox .wsq-mb-status-badge.is-pending {
					background: var(--wsq-warn-bg);
					color: var(--wsq-warn);
					border-color: var(--wsq-warn-border);
				}
				.wsq-pricing-metabox .wsq-mb-status-badge.is-sent {
					background: #f0f6fc;
					color: #135e96;
					border-color: #c5d9ed;
				}
				.wsq-pricing-metabox .wsq-mb-status-badge.is-other {
					background: var(--wsq-ok-bg);
					color: var(--wsq-ok);
					border-color: var(--wsq-ok-border);
				}
				.wsq-pricing-metabox .wsq-mb-buyer-info {
					color: var(--wsq-muted);
					font-size: 12.5px;
				}
				.wsq-pricing-metabox .wsq-mb-dot {
					display: inline-block;
					width: 8px;
					height: 8px;
					border-radius: 50%;
					flex-shrink: 0;
				}
				.wsq-pricing-metabox .wsq-mb-chips { display: flex; flex-wrap: wrap; gap: 6px; justify-content: flex-start; margin-top: 8px; }
				.wsq-pricing-metabox .wsq-mb-chip {
					display: inline-flex;
					align-items: center;
					gap: 5px;
					padding: 3px 9px;
					border-radius: 3px;
					font-size: 11.5px;
					font-weight: 600;
					line-height: 1.5;
					border: 1px solid var(--wsq-border);
					background: #ffffff;
					color: var(--wsq-ink);
				}
				.wsq-pricing-metabox .wsq-mb-chip.is-on { background: var(--wsq-ok-bg); border-color: var(--wsq-ok-border); color: var(--wsq-ok); }
				.wsq-pricing-metabox .wsq-mb-chip.is-off { background: #f0f0f1; border-color: var(--wsq-border); color: var(--wsq-muted); }
				.wsq-pricing-metabox .wsq-mb-chip.is-warn { background: var(--wsq-warn-bg); border-color: var(--wsq-warn-border); color: var(--wsq-warn); }
				.wsq-pricing-metabox .wsq-mb-chip[title] { cursor: help; }
				.wsq-pricing-metabox .wsq-mb-checks { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 8px; }
				.wsq-pricing-metabox .wsq-mb-check-btn { font-size: 11.5px; line-height: 1.8; padding: 2px 10px; }
				.wsq-pricing-metabox .wsq-mb-check-note { color: var(--wsq-muted); font-size: 11.5px; }
				.wsq-pricing-metabox .wsq-mb-check-result { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
				.wsq-pricing-metabox .wsq-mb-check-result[hidden] { display: none; }
				.wsq-pricing-metabox .wsq-mb-success-notice {
					background: var(--wsq-ok-bg);
					border: 1px solid var(--wsq-ok-border);
					border-inline-start-width: 4px;
					color: var(--wsq-ok);
					padding: 10px 14px;
					border-radius: 3px;
					margin-bottom: 16px;
					font-weight: 600;
					font-size: 13px;
					display: flex;
					align-items: center;
					gap: 8px;
				}
				.wsq-pricing-metabox .wsq-mb-table { margin-bottom: 16px; }
				.wsq-pricing-metabox .wsq-mb-table th { font-weight: 700; color: var(--wsq-ink); }
				.wsq-pricing-metabox .wsq-calc-price { width: 100%; font-weight: 700; color: var(--wsq-ink); }
				.wsq-pricing-metabox .wsq-row-subtotal { text-align: left; font-weight: 700; color: var(--wsq-ink); }
				.wsq-pricing-metabox .wsq-mb-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px; }
				.wsq-pricing-metabox .wsq-mb-card {
					background: var(--wsq-bg-light);
					padding: 14px;
					border-radius: 4px;
					border: 1px solid var(--wsq-border);
				}
				.wsq-pricing-metabox .wsq-mb-card label { display: block; font-weight: 700; margin-bottom: 5px; color: var(--wsq-ink); font-size: 13px; }
				.wsq-pricing-metabox .wsq-mb-card small { color: var(--wsq-muted); display: block; margin-top: 5px; font-size: 12px; }
				.wsq-pricing-metabox #wsq-pricing-error-notice {
					display: none;
					background: var(--wsq-err-bg);
					border: 1px solid var(--wsq-err-border);
					border-inline-start-width: 4px;
					color: var(--wsq-err);
					padding: 10px 14px;
					border-radius: 3px;
					margin-bottom: 14px;
					font-weight: 600;
					font-size: 13px;
				}
				.wsq-pricing-metabox .wsq-mb-total-card {
					background: #ffffff;
					border: 1px solid var(--wsq-border);
					border-inline-start: 4px solid var(--wsq-primary);
					border-radius: 4px;
					padding: 14px 18px;
					display: flex;
					justify-content: space-between;
					align-items: center;
					margin-bottom: 16px;
					flex-wrap: wrap;
					gap: 12px;
				}
				.wsq-pricing-metabox .wsq-mb-total-label { font-size: 13.5px; color: var(--wsq-ink); font-weight: 700; }
				.wsq-pricing-metabox #wsq-calc-grand-total { font-size: 20px; color: var(--wsq-primary); margin-inline-start: 6px; font-weight: 800; }
				.wsq-pricing-metabox .wsq-send-proposal-btn { font-weight: 700 !important; padding: 6px 20px !important; height: auto !important; font-size: 13.5px !important; min-height: 38px !important; }
				.wsq-pricing-metabox .wsq-mb-dispatch {
					background: #ffffff;
					border: 1px solid var(--wsq-border);
					border-radius: 4px;
					padding: 14px 18px;
					margin-top: 12px;
				}
				.wsq-pricing-metabox .wsq-mb-dispatch-head {
					display: flex;
					justify-content: space-between;
					align-items: center;
					margin-bottom: 12px;
					border-bottom: 1px solid var(--wsq-border-light);
					padding-bottom: 10px;
					flex-wrap: wrap;
					gap: 8px;
				}
				.wsq-pricing-metabox .wsq-mb-phone-badge {
					direction: ltr;
					font-family: Consolas, monospace;
					background: #f0f0f1;
					border: 1px solid var(--wsq-border);
					padding: 2px 8px;
					border-radius: 3px;
					font-weight: 700;
					color: var(--wsq-primary);
				}
				.wsq-pricing-metabox .wsq-btn-bale { background: #f0f6fc !important; color: #135e96 !important; border-color: #c5d9ed !important; font-weight: 600 !important; }
				.wsq-pricing-metabox .wsq-btn-bale:hover { background: #e0eef9 !important; border-color: #2271b1 !important; }
				.wsq-pricing-metabox .wsq-btn-eitaa { background: #fcf9e8 !important; color: #996800 !important; border-color: #dba617 !important; font-weight: 600 !important; }
				.wsq-pricing-metabox .wsq-btn-eitaa:hover { background: #faf3cb !important; }
				.wsq-pricing-metabox .wsq-btn-wa { background: #edfaef !important; color: #007017 !important; border-color: #68de7c !important; font-weight: 600 !important; }
				.wsq-pricing-metabox .wsq-btn-wa:hover { background: #daf3de !important; }
				.wsq-pricing-metabox .wsq-btn-copy { border-color: var(--wsq-border) !important; font-weight: 600 !important; color: var(--wsq-ink) !important; }
				.wsq-pricing-metabox .wsq-btn-resend { border-color: var(--wsq-primary) !important; color: var(--wsq-primary) !important; font-weight: 600 !important; }
				.wsq-pricing-metabox .wsq-btn-resend:hover { background: #f0f6fc !important; }
				.wsq-pricing-metabox #wsq-resend-status { font-size: 12px; margin-inline-start: 8px; font-weight: 700; }
			</style>
			<div class="wsq-mb-header">
				<div class="wsq-mb-status-lead">
					<strong><?php esc_html_e( 'وضعیت استعلام:', 'woo-smart-quote' ); ?></strong>
					<?php if ( 'quote-request' === $status ) : ?>
						<span class="wsq-mb-status-badge is-pending"><?php echo WSQ_Icons::get( 'clock' ); ?> <?php esc_html_e( 'در انتظار اعلام قیمت', 'woo-smart-quote' ); ?></span>
					<?php elseif ( 'quote-sent' === $status ) : ?>
						<span class="wsq-mb-status-badge is-sent"><?php echo WSQ_Icons::get( 'mail' ); ?> <?php esc_html_e( 'پیشنهاد قیمت ارسال شد', 'woo-smart-quote' ); ?></span>
					<?php else : ?>
						<span class="wsq-mb-status-badge is-other"><?php echo esc_html( wc_get_order_status_name( $status ) ); ?></span>
					<?php endif; ?>
				</div>
				<div>
					<span class="wsq-mb-buyer-info"><?php esc_html_e( 'خریدار:', 'woo-smart-quote' ); ?> <b><?php echo esc_html( $order->get_formatted_billing_full_name() ); ?></b> (<?php echo esc_html( $order->get_billing_phone() ); ?>)</span>
					<div class="wsq-mb-chips">
						<?php foreach ( $bot_channels as $channel ) : ?>
							<span class="wsq-mb-chip <?php echo esc_attr( $channel['state'] ); ?>" title="<?php echo esc_attr( $channel['hint'] ); ?>">
								<?php echo WSQ_Icons::get( $channel['icon'] ); ?> <?php echo esc_html( $channel['label'] ); ?>: <?php echo esc_html( $channel['text'] ); ?>
							</span>
						<?php endforeach; ?>
						<?php if ( '' === $link_phone ) : ?>
							<span class="wsq-mb-chip is-off" title="<?php echo esc_attr( __( 'سفارش شماره موبایل ندارد؛ نه چتی لینک می‌شود نه پیامکی ارسال می‌شود.', 'woo-smart-quote' ) ); ?>"><?php echo WSQ_Icons::get( 'phone' ); ?> <?php esc_html_e( 'بدون شماره موبایل', 'woo-smart-quote' ); ?></span>
						<?php endif; ?>
					</div>
					<div class="wsq-mb-checks">
						<button type="button" class="button wsq-mb-check-btn" id="wsq-check-bots-btn"><?php echo WSQ_Icons::get( 'refresh' ); ?> <?php esc_html_e( 'بررسی اتصال ربات', 'woo-smart-quote' ); ?></button>
						<span class="wsq-mb-check-note"><?php esc_html_e( 'بررسی مستقیم توکن‌ها با وب‌سرویس بله و ایتا بدون ارسال پیام.', 'woo-smart-quote' ); ?></span>
					</div>
					<div class="wsq-mb-check-result" id="wsq-check-bots-result" role="status" aria-live="polite" hidden></div>
				</div>
			</div>

			<div class="wsq-pricing-form">
				<input type="hidden" name="action" value="wsq_save_and_send_proposal">
				<input type="hidden" name="order_id" value="<?php echo esc_attr( $order_id ); ?>">
				<input type="hidden" id="wsq_pricing_nonce" name="wsq_pricing_nonce" value="<?php echo esc_attr( wp_create_nonce( 'wsq_pricing_proposal_nonce' ) ); ?>">
				<?php if ( isset( $_GET['wsq_proposal_sent'] ) ) : ?>
					<div class="wsq-mb-success-notice">
						<?php echo WSQ_Icons::get( 'check' ); ?> <?php esc_html_e( 'قیمت‌ها با موفقیت ذخیره و پیش‌فاکتور برای خریدار ارسال شد.', 'woo-smart-quote' ); ?>
					</div>
				<?php endif; ?>

				<!-- Items Pricing Table -->
				<table class="wp-list-table widefat fixed striped wsq-mb-table">
					<thead>
						<tr>
							<th style="width:5%;">#</th>
							<th style="width:38%;"><?php esc_html_e( 'شرح کالا / خدمات استعلام شده', 'woo-smart-quote' ); ?></th>
							<th style="width:14%; text-align:center; white-space:nowrap;"><?php esc_html_e( 'تعداد درخواستی', 'woo-smart-quote' ); ?></th>
							<th style="width:23%;"><?php esc_html_e( 'قیمت واحد پیشنهادی (تومان)', 'woo-smart-quote' ); ?></th>
							<th style="width:20%; text-align:left;"><?php esc_html_e( 'مبلغ ردیف (تومان)', 'woo-smart-quote' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$i = 1;
						foreach ( $items as $item_id => $item ) :
							$qty        = $item->get_quantity();
							$subtotal   = $item->get_subtotal();
							$unit_price = $qty > 0 ? round( $subtotal / $qty ) : 0;
							?>
							<tr>
								<td><?php echo $i++; ?></td>
								<td>
									<strong><?php echo esc_html( $item->get_name() ); ?></strong>
									<?php
									$meta_data = $item->get_formatted_meta_data( '' );
									if ( ! empty( $meta_data ) ) {
										echo '<div style="font-size:11px; color:#50575e;">';
										foreach ( $meta_data as $meta ) {
											echo esc_html( $meta->display_key ) . ': ' . esc_html( $meta->display_value ) . ' | ';
										}
										echo '</div>';
									}
									?>
								</td>
								<td style="text-align:center;">
									<span style="font-weight:700; font-size:13px;"><?php echo esc_html( $qty ); ?></span>
								</td>
								<td>
									<input type="number" 
										   name="wsq_item_prices[<?php echo esc_attr( $item_id ); ?>]" 
										   value="<?php echo esc_attr( $unit_price ); ?>" 
										   class="wsq-calc-price regular-text" 
										   data-qty="<?php echo esc_attr( $qty ); ?>" 
										   step="1000" 
										   placeholder="<?php esc_attr_e( 'قیمت واحد را وارد کنید', 'woo-smart-quote' ); ?>" 
										   required>
								</td>
								<td class="wsq-row-subtotal">
									<?php echo number_format( $subtotal ); ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<!-- Proposal Details & Actions -->
				<div class="wsq-mb-grid">
					<div class="wsq-mb-card">
						<label for="wsq_quote_validity"><?php echo WSQ_Icons::get( 'clock' ); ?> <?php esc_html_e( 'مهلت اعتبار این پیشنهاد قیمت:', 'woo-smart-quote' ); ?></label>
						<input type="text" id="wsq_quote_validity" name="wsq_quote_validity" value="<?php echo esc_attr( $validity ); ?>" class="regular-text" style="width:100%;" placeholder="<?php esc_attr_e( 'مثال: ۲۴ ساعت کاری / تا پایان وقت اداری', 'woo-smart-quote' ); ?>">
						<small><?php esc_html_e( 'این مهلت در پیش‌فاکتور رسمی و پیام خریدار درج خواهد شد.', 'woo-smart-quote' ); ?></small>
					</div>

					<div class="wsq-mb-card">
						<label for="wsq-payment-url"><?php echo WSQ_Icons::get( 'link' ); ?> <?php esc_html_e( 'لینک پرداخت مستقیم خریدار:', 'woo-smart-quote' ); ?> <span dir="ltr">(Order Pay Link)</span></label>
						<input type="text" id="wsq-payment-url" readonly value="<?php echo esc_url( $payment_url ); ?>" style="width:100%; font-size:11.5px; direction:ltr; background:#fff;">
						<small><?php esc_html_e( 'خریدار با این لینک مستقیماً به درگاه پرداخت شاپرک متصل می‌شود.', 'woo-smart-quote' ); ?></small>
					</div>
				</div>

				<div id="wsq-pricing-error-notice" role="alert"></div>

				<div class="wsq-mb-total-card">
					<div>
						<span class="wsq-mb-total-label">
							<?php esc_html_e( 'جمع کل محاسبه‌شده استعلام:', 'woo-smart-quote' ); ?> 
							<span id="wsq-calc-grand-total"><?php echo number_format( $order->get_total() ); ?></span> <?php esc_html_e( 'تومان', 'woo-smart-quote' ); ?>
						</span>
					</div>
					<div>
						<button type="button" class="button button-primary button-large wsq-send-proposal-btn">
							<?php echo WSQ_Icons::get( 'check' ); ?> <?php esc_html_e( 'ذخیره قیمت‌ها و صدور پیش‌فاکتور', 'woo-smart-quote' ); ?>
						</button>
					</div>
				</div>

				<?php
				// Buyer messenger quick actions
				$buyer_phone = $order->get_billing_phone();
				if ( empty( $buyer_phone ) ) {
					$buyer_phone = $order->get_meta( '_wsq_customer_phone' );
				}
				$clean_phone = preg_replace( '/[^0-9]/', '', (string) $buyer_phone );
				$intl_phone  = ( strpos( $clean_phone, '09' ) === 0 ) ? ( '98' . substr( $clean_phone, 1 ) ) : $clean_phone;
				$buyer_name  = $order->get_billing_first_name() ? $order->get_billing_first_name() : $order->get_formatted_billing_full_name();
				$total_str   = wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) );
				$prop_text   = WSQ_Buyer_Notifier::instance()->build_proposal_text( $order, $buyer_name, $total_str, $validity, $payment_url );
				?>

				<!-- Buyer Communication & 1-Click Messenger Dispatch -->
				<div class="wsq-mb-dispatch">
					<div class="wsq-mb-dispatch-head">
						<div style="display:flex; align-items:center; gap:8px;">
							<span style="font-size:16px;"><?php echo WSQ_Icons::get( 'phone' ); ?></span>
							<strong style="color:var(--wsq-ink); font-size:13px;"><?php esc_html_e( 'ارسال سریع به خریدار:', 'woo-smart-quote' ); ?></strong>
							<span class="wsq-mb-phone-badge">
								<?php echo esc_html( $buyer_phone ? $buyer_phone : __( 'شماره تلفن ثبت نشده', 'woo-smart-quote' ) ); ?>
							</span>
						</div>
						<span style="font-size:11.5px; color:var(--wsq-muted);"><?php esc_html_e( 'با تغییر وضعیت، پیش‌فاکتور خودکار از طریق وب‌سرویس نیز ارسال می‌گردد.', 'woo-smart-quote' ); ?></span>
					</div>

					<div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
						<!-- 1-Click Bale Chat Link -->
						<a href="https://ble.ir/+<?php echo esc_attr( $intl_phone ); ?>" target="_blank" class="button wsq-btn-bale" style="display:inline-flex; align-items:center; gap:6px;">
							<span class="wsq-mb-dot" style="background:#135e96;" aria-hidden="true"></span> <?php esc_html_e( 'چت بله خریدار', 'woo-smart-quote' ); ?>
						</a>

						<!-- 1-Click Eitaa Web Link -->
						<a href="https://web.eitaa.com/" target="_blank" class="button wsq-btn-eitaa" style="display:inline-flex; align-items:center; gap:6px;">
							<span class="wsq-mb-dot" style="background:#996800;" aria-hidden="true"></span> <?php esc_html_e( 'ایتا وب خریدار', 'woo-smart-quote' ); ?>
						</a>

						<!-- 1-Click WhatsApp Link with prefilled text -->
						<a href="https://api.whatsapp.com/send?phone=<?php echo esc_attr( $intl_phone ); ?>&text=<?php echo rawurlencode( $prop_text ); ?>" target="_blank" class="button wsq-btn-wa" style="display:inline-flex; align-items:center; gap:6px;">
							<span class="wsq-mb-dot" style="background:#007017;" aria-hidden="true"></span> <?php esc_html_e( 'واتساپ با متن آماده', 'woo-smart-quote' ); ?>
						</a>

						<!-- Copy proposal text to clipboard -->
						<button type="button" class="button wsq-btn-copy" id="wsq-copy-proposal-btn">
							<?php echo WSQ_Icons::get( 'clipboard' ); ?> <?php esc_html_e( 'کپی متن پیشنهاد قیمت', 'woo-smart-quote' ); ?>
						</button>

						<!-- Resend via configured Web Service (FarazSMS/Kavenegar/Safir) -->
						<button type="button" class="button wsq-btn-resend" id="wsq-resend-sms-btn" data-order="<?php echo esc_attr( $order_id ); ?>">
							<?php echo WSQ_Icons::get( 'bolt' ); ?> <?php esc_html_e( 'ارسال مجدد وب‌سرویس', 'woo-smart-quote' ); ?>
						</button>
						<span id="wsq-resend-status"></span>
					</div>

					<!-- Hidden textarea holding proposal text -->
					<textarea id="wsq-hidden-proposal-text" style="display:none;"><?php echo esc_textarea( $prop_text ); ?></textarea>
				</div>

			</div>
		</div>

		<script>
		var wsqOrderI18n = <?php echo wp_json_encode( array(
			'saving'        => __( 'در حال ذخیره و ارسال…', 'woo-smart-quote' ),
			'unknown_error' => __( 'خطای نامشخص در ذخیره‌سازی.', 'woo-smart-quote' ),
			'server_error'  => __( 'خطا در ارتباط با سرور.', 'woo-smart-quote' ),
			'copied'        => __( '✓ متن کامل پیشنهاد قیمت کپی شد', 'woo-smart-quote' ),
			'copy_failed'   => __( 'کپی نشد؛ متن را انتخاب و دستی کپی کنید.', 'woo-smart-quote' ),
			'resending'     => __( 'در حال ارسال...', 'woo-smart-quote' ),
		) ); ?>;
		jQuery(document).ready(function($) {
			// Read-only payment URL: selecting on focus saves a manual drag.
			$('#wsq-payment-url').on('focus click', function() {
				this.select();
			});

			function normalizeDigits(str) {
				if (!str) return '';
				return String(str)
					.replace(/[۰-۹]/g, function(d) { return String.fromCharCode(d.charCodeAt(0) - 1728); })
					.replace(/[٠-٩]/g, function(d) { return String.fromCharCode(d.charCodeAt(0) - 1584); });
			}

			function recalculateTotals() {
				var grandTotal = 0;
				$('.wsq-calc-price').each(function() {
					var raw = normalizeDigits($(this).val());
					if ($(this).val() !== raw) {
						$(this).val(raw);
					}
					var unitPrice = parseFloat(raw) || 0;
					var qty = parseFloat($(this).data('qty')) || 1;
					var rowTotal = unitPrice * qty;
					$(this).closest('tr').find('.wsq-row-subtotal').text(rowTotal.toLocaleString('fa-IR'));
					grandTotal += rowTotal;
				});
				$('#wsq-calc-grand-total').text(grandTotal.toLocaleString('fa-IR'));
			}
			$('.wsq-calc-price').on('input change', recalculateTotals);

			// Save prices + switch status via AJAX (the metabox cannot carry a
			// real <form> — nested forms are invalid HTML inside the order
			// editor's outer form, so we serialize and post manually).
			$('.wsq-send-proposal-btn').on('click', function() {
				var $btn = $(this);
				var $form = $btn.closest('.wsq-pricing-form');
				var $errNotice = $('#wsq-pricing-error-notice');
				var btnHtml = $btn.html();
				$errNotice.hide().empty();
				$btn.prop('disabled', true).text(wsqOrderI18n.saving);

				var data = {
					action: 'wsq_save_and_send_proposal',
					order_id: $form.find('input[name="order_id"]').val(),
					wsq_quote_validity: $form.find('input[name="wsq_quote_validity"]').val(),
					wsq_pricing_nonce: $form.find('#wsq_pricing_nonce').val(),
					wsq_item_prices: {}
				};
				$form.find('.wsq-calc-price').each(function() {
					var match = $(this).attr('name').match(/wsq_item_prices\[(\d+)\]/);
					if (match) {
						data.wsq_item_prices[match[1]] = normalizeDigits($(this).val());
					}
				});

				$.post(ajaxurl, data).done(function(response) {
					if (response.success && response.data.redirect) {
						window.location.assign(response.data.redirect);
						return;
					}
					$btn.prop('disabled', false).html(btnHtml);
					var msg = response.data && response.data.message ? response.data.message : wsqOrderI18n.unknown_error;
					$errNotice.text(msg).slideDown(200);
				}).fail(function() {
					$btn.prop('disabled', false).html(btnHtml);
					$errNotice.text(wsqOrderI18n.server_error).slideDown(200);
				});
			});

			// Copy proposal text — success is only reported when the browser
			// actually copied; failures stay visible instead of flashing green.
			$('#wsq-copy-proposal-btn').on('click', function() {
				var $btn = $(this);
				var originalHtml = $btn.html();
				var text = $('#wsq-hidden-proposal-text').val();
				$btn.prop('disabled', true);

				var copy = window.wsqCopyText ? window.wsqCopyText(text) : Promise.resolve(false);
				copy.then(function(ok) {
					$btn.prop('disabled', false)
						.css(ok
							? { background: '#ecfdf5', borderColor: '#10b981', color: '#047857' }
							: { background: '#fef2f2', borderColor: '#dc2626', color: '#991b1b' })
						.text(ok ? wsqOrderI18n.copied : wsqOrderI18n.copy_failed);
					setTimeout(function() {
						$btn.html(originalHtml).removeAttr('style');
					}, 2500);
				});
			});

			// Live bot-token probe (getMe — nothing is sent to any buyer).
			$('#wsq-check-bots-btn').on('click', function() {
				var $btn = $(this);
				var $box = $('#wsq-check-bots-result');
				var btnHtml = $btn.html();
				$btn.prop('disabled', true).text(<?php echo wp_json_encode( __( 'در حال بررسی اتصال…', 'woo-smart-quote' ) ); ?>);
				// One pending row per messenger so each one lights up on its own.
				var pending = '';
				var pendingLabel = <?php echo wp_json_encode( array( 'bale' => __( 'بله', 'woo-smart-quote' ), 'eitaa' => __( 'ایتا', 'woo-smart-quote' ) ) ); ?>;
				var pendingText = <?php echo wp_json_encode( __( 'در حال بررسی…', 'woo-smart-quote' ) ); ?>;
				$.each(pendingLabel, function(messenger, label) {
					pending += '<span class="wsq-mb-chip is-off" data-wsq-check="' + messenger + '">' + label + ': ' + pendingText + '</span>';
				});
				$box.prop('hidden', false).html(pending);

				$.post(ajaxurl, {
					action: 'wsq_check_bot_connection',
					nonce: $('#wsq_pricing_nonce').val()
				}, function(response) {
					$btn.prop('disabled', false).html(btnHtml);
					if (!response.success) {
						$box.html('<span class="wsq-mb-chip is-warn">' + (response.data && response.data.message ? response.data.message : <?php echo wp_json_encode( __( 'خطای نامشخص در بررسی.', 'woo-smart-quote' ) ); ?>) + '</span>');
						return;
					}

					var html = '';
					$.each(response.data.checks, function(messenger, row) {
						var cls = 'ok' === row.state ? 'is-on' : ( 'unconfigured' === row.state ? 'is-off' : 'is-warn' );
						var $row = $box.find('[data-wsq-check="' + messenger + '"]');
						var text = row.label + ': ' + row.detail;
						if ($row.length) {
							$row.attr('class', 'wsq-mb-chip ' + cls).attr('title', row.detail).text(text);
						} else {
							html += '<span class="wsq-mb-chip ' + cls + '" title="' + $('<div>').text(row.detail).html() + '">' + $('<div>').text(text).html() + '</span>';
						}
					});
					if (html) {
						$box.append(html);
					}
			}).fail(function() {
				$btn.prop('disabled', false).html(btnHtml);
				$box.find('[data-wsq-check]').remove();
				$box.append('<span class="wsq-mb-chip is-warn">' + <?php echo wp_json_encode( __( 'خطا در ارتباط با سرور.', 'woo-smart-quote' ) ); ?> + '</span>');
			});
			});

			// Resend proposal via AJAX
			$('#wsq-resend-sms-btn').on('click', function() {
				var $btn = $(this);
				var orderId = $btn.data('order');
				var btnHtml = $btn.html();
				$btn.prop('disabled', true).text(wsqOrderI18n.resending);
				$('#wsq-resend-status').text('').css('color', '#50575e');

				$.post(ajaxurl, {
					action: 'wsq_ajax_resend_buyer_proposal',
					order_id: orderId,
					nonce: '<?php echo wp_create_nonce( 'wsq_buyer_resend_nonce' ); ?>'
				}, function(response) {
					$btn.prop('disabled', false).html(btnHtml);
					if (response.success) {
						$('#wsq-resend-status').text(response.data.message).css('color', '#007017');
					} else {
						$('#wsq-resend-status').text(response.data.message).css('color', '#d63638');
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Verify the bot tokens on demand (AJAX behind the metabox button).
	 *
	 * Sends one tiny getMe request per configured messenger: nothing is
	 * delivered to any buyer and no inbound update is consumed, so the button
	 * is safe to press on every order. An empty token is reported without any
	 * network call.
	 */
	public function ajax_check_bot_connection() {
		check_ajax_referer( 'wsq_pricing_proposal_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'woo-smart-quote' ) ) );
		}

		wp_send_json_success( array( 'checks' => $this->probe_bot_tokens() ) );
	}

	/**
	 * Token probe shared by the AJAX handler and the tests.
	 *
	 * @return array messenger slug => label/state/detail.
	 */
	public function probe_bot_tokens() {
		$channels = array(
			'bale'  => __( 'بله', 'woo-smart-quote' ),
			'eitaa' => __( 'ایتا', 'woo-smart-quote' ),
		);

		$checks = array();

		foreach ( $channels as $messenger => $label ) {
			$token = trim( (string) get_option( 'wsq_' . $messenger . '_token', '' ) );

			if ( '' === $token ) {
				$checks[ $messenger ] = array(
					'label'  => $label,
					'state'  => 'unconfigured',
					'detail' => __( 'توکن تنظیم نشده است.', 'woo-smart-quote' ),
				);
				continue;
			}

			$result = WSQ_Updates::api_for( $messenger, $token )->get_me();

			if ( ! empty( $result['ok'] ) ) {
				$bot = '';
				if ( isset( $result['result']['username'] ) ) {
					$bot = '@' . $result['result']['username'];
				} elseif ( isset( $result['result']['first_name'] ) ) {
					$bot = (string) $result['result']['first_name'];
				}

				$checks[ $messenger ] = array(
					'label'  => $label,
					'state'  => 'ok',
					'detail' => sprintf(
						/* translators: %s: bot username when the panel returns one. */
						__( 'ربات متصل است%s', 'woo-smart-quote' ),
						'' !== $bot ? ' — ' . $bot : '.'
					),
				);
			} else {
				$checks[ $messenger ] = array(
					'label'  => $label,
					'state'  => 'invalid',
					'detail' => ! empty( $result['description'] ) ? (string) $result['description'] : __( 'پاسخ نامعتبر از سرور پنل.', 'woo-smart-quote' ),
				);
			}
		}

		return $checks;
	}

	/**
	 * Handle admin post action: save itemized prices, update status, and notify buyer
	 */
	public function handle_save_and_send_proposal() {
		$is_ajax = wp_doing_ajax();

		check_admin_referer( 'wsq_pricing_proposal_nonce', 'wsq_pricing_nonce' );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			$is_ajax
				? wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'woo-smart-quote' ) ) )
				: wp_die( esc_html__( 'دسترسی غیرمجاز.', 'woo-smart-quote' ) );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			$is_ajax
				? wp_send_json_error( array( 'message' => __( 'سفارش یافت نشد.', 'woo-smart-quote' ) ) )
				: wp_die( esc_html__( 'سفارش یافت نشد.', 'woo-smart-quote' ) );
		}

		$item_prices = isset( $_POST['wsq_item_prices'] ) ? (array) wp_unslash( $_POST['wsq_item_prices'] ) : array();
		$validity    = isset( $_POST['wsq_quote_validity'] ) ? sanitize_text_field( wp_unslash( $_POST['wsq_quote_validity'] ) ) : __( '۲۴ ساعت کاری', 'woo-smart-quote' );

		if ( ! current_user_can( 'edit_shop_order', $order_id ) ) {
			$this->reject_pricing( new WP_Error( 'wsq_forbidden', __( 'شما اجازه دسترسی به این استعلام را ندارید.', 'woo-smart-quote' ), array( 'status' => 403 ) ), $is_ajax );
		}

		$guard = self::guard_priceable_order( $order );
		if ( is_wp_error( $guard ) ) {
			$this->reject_pricing( $guard, $is_ajax );
		}

		$prices = self::validate_price_submission( $order, $item_prices, true );
		if ( is_wp_error( $prices ) ) {
			$this->reject_pricing( $prices, $is_ajax );
		}

		// Save validity meta
		$order->update_meta_data( '_wsq_quote_validity', $validity );

		// Update each item's price
		foreach ( $order->get_items() as $item_id => $item ) {
			$unit_price = $prices[ (int) $item_id ];
			$qty        = $item->get_quantity();
			$line_total = $unit_price * $qty;

			$item->set_subtotal( $line_total );
			$item->set_total( $line_total );
			$item->save();
		}

		// Recalculate totals
		$order->calculate_totals();

		// Persist meta (validity) and recalculated totals BEFORE the status
		// change: update_status() fires the quote-sent hook, whose buyer
		// notification reads _wsq_quote_validity.
		$order->save();

		// Update status to quote-sent (triggers proposal notification)
		$order->update_status( 'quote-sent', __( 'قیمت تک‌تک اقلام توسط کارشناس تعیین شد و پیش‌فاکتور نهایی برای خریدار ارسال گردید.', 'woo-smart-quote' ) );
		$order->save();

		// get_edit_order_url() is HPOS-aware; post.php?post=... breaks when
		// WooCommerce uses custom order tables.
		$redirect = add_query_arg( 'wsq_proposal_sent', '1', $order->get_edit_order_url() );

		if ( $is_ajax ) {
			wp_send_json_success( array( 'redirect' => $redirect ) );
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Best-effort client IP. Only REMOTE_ADDR is trusted: forwarding headers
	 * are attacker-controlled unless a proxy is known to rewrite them.
	 *
	 * @return string
	 */
	private function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}

	/**
	 * Per-IP quota for public quote submissions.
	 *
	 * @return true|WP_Error
	 */
	private function throttle_quote_submission() {
		$key    = 'wsq_quote_throttle_' . md5( $this->client_ip() );
		$recent = (int) get_transient( $key );

		if ( $recent >= 5 ) {
			return new WP_Error( 'wsq_throttled', __( 'تعداد درخواست‌های استعلام بیش از حد مجاز است. لطفاً چند دقیقه بعد دوباره تلاش کنید.', 'woo-smart-quote' ), array( 'status' => 429 ) );
		}

		set_transient( $key, $recent + 1, 10 * MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * AJAX submit quote request from customer
	 */
	public function ajax_submit_quote() {
		check_ajax_referer( 'wsq_submit_quote', 'wsq_submit_nonce' );

		$name    = isset( $_POST['wsq_name'] ) ? sanitize_text_field( wp_unslash( $_POST['wsq_name'] ) ) : '';
		$email   = isset( $_POST['wsq_email'] ) ? sanitize_email( wp_unslash( $_POST['wsq_email'] ) ) : '';
		$phone   = isset( $_POST['wsq_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['wsq_phone'] ) ) : '';
		$channel = isset( $_POST['wsq_channel'] ) ? sanitize_text_field( wp_unslash( $_POST['wsq_channel'] ) ) : 'all';
		$message = isset( $_POST['wsq_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['wsq_message'] ) ) : '';

		if ( empty( $name ) || empty( $email ) || empty( $phone ) ) {
			wp_send_json_error( array( 'message' => __( 'لطفاً تمامی فیلدهای الزامی را پر نمایید.', 'woo-smart-quote' ) ) );
		}

		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'آدرس ایمیل وارد شده معتبر نیست.', 'woo-smart-quote' ) ) );
		}

		// Normalize Persian/Arabic numerals to ASCII.
		$fa_digits = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
		$ar_digits = array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
		$en_digits = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
		$phone     = str_replace( $fa_digits, $en_digits, (string) $phone );
		$phone     = str_replace( $ar_digits, $en_digits, $phone );

		// Iranian mobile required for messenger/SMS proposal delivery.
		$clean_phone = preg_replace( '/[^0-9]/', '', (string) $phone );
		if ( strlen( $clean_phone ) < 10 ) {
			wp_send_json_error( array( 'message' => __( 'شماره تماس معتبر نمی‌باشد.', 'woo-smart-quote' ) ) );
		}
		$phone = $clean_phone;

		// Canonical Iranian mobile normalization (converts +98 / 0098 / 98 / 9... to 09xxxxxxxxx).
		$normalized_mobile = WSQ_Buyer_Notifier::instance()->normalize_mobile( $phone );
		if ( '' !== $normalized_mobile ) {
			$phone = $normalized_mobile;
		}

		if ( ! in_array( $channel, array( 'all', 'bale', 'eitaa', 'sms' ), true ) ) {
			$channel = 'all';
		}

		$session = WSQ_Session::instance();
		$items   = $session->get_items();

		if ( empty( $items ) ) {
			wp_send_json_error( array( 'message' => __( 'لیست استعلام شما خالی است.', 'woo-smart-quote' ) ) );
		}

		// Cheap validations ran first so misfilled forms do not consume quota.
		$throttle = $this->throttle_quote_submission();
		if ( is_wp_error( $throttle ) ) {
			wp_send_json_error( array( 'message' => $throttle->get_error_message() ), 429 );
		}

		// Create WooCommerce Order with quote-request status
		$order = wc_create_order( array(
			'status'        => 'quote-request',
			'customer_id'   => get_current_user_id(),
			'customer_note' => $message,
		) );

		if ( is_wp_error( $order ) ) {
			wp_send_json_error( array( 'message' => __( 'خطا در ایجاد استعلام.', 'woo-smart-quote' ) ) );
		}

		// Set billing address and customer contact meta
		$address = array(
			'first_name' => $name,
			'email'      => $email,
			'phone'      => $phone,
		);
		$order->set_address( $address, 'billing' );
		$order->update_meta_data( '_wsq_customer_phone', $phone );
		$order->update_meta_data( '_wsq_buyer_channel', $channel );

		// Add items to order
		foreach ( $items as $item ) {
			$variation_id = ! empty( $item['variation_id'] ) ? (int) $item['variation_id'] : 0;
			$product_id   = $variation_id ? $variation_id : ( ! empty( $item['product_id'] ) ? (int) $item['product_id'] : 0 );
			$product      = wc_get_product( $product_id );
			
			if ( $product ) {
				$item_args = array();
				if ( ! empty( $item['variations'] ) && is_array( $item['variations'] ) ) {
					$item_args['variation'] = $item['variations'];
				}
				$order->add_product( $product, (int) $item['quantity'], $item_args );
			}
		}

		$order->calculate_totals();
		$order->save();

		// Clear session
		$session->clear();

		// Trigger Action Scheduler to send notifications to merchants
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( 'wsq_process_new_quote', array( 'order_id' => $order->get_id() ), 'woo_smart_quote' );
		} else {
			do_action( 'wsq_process_new_quote', $order->get_id() );
		}

		$is_logged_in = is_user_logged_in();

		wp_send_json_success( array(
			'order_id'      => $order->get_id(),
			'order_number'  => $order->get_order_number(),
			'order_key'     => $order->get_order_key(),
			'message'       => __( 'استعلام شما با موفقیت ثبت شد. کارشناسان ما پس از بررسی، پیش‌فاکتور تفکیکی قیمت‌دار را برای شما ارسال خواهند کرد.', 'woo-smart-quote' ),
			// The order-received page validates the key itself, so guests can
			// open it without logging in. Never send them to the login-gated
			// my-account endpoint.
			'order_url'     => $order->get_checkout_order_received_url(),
			'account_url'   => $is_logged_in ? $order->get_view_order_url() : '',
			'is_logged_in'  => $is_logged_in,
			'count'         => 0,
			'line_count'    => 0,
		) );
	}
}
