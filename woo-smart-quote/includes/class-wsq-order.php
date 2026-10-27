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

		// Note: buyer notification on 'quote-sent' is handled solely by
		// WSQ_Buyer_Notifier (which hooks the same status change); do not
		// dispatch again here or the buyer receives duplicates.
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
			__( 'قیمت‌گذاری تفکیکی اقلام و ارسال به خریدار (Smart Quote)', 'woo-smart-quote' ),
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
		<div class="wsq-pricing-metabox" style="font-family:inherit;">
			<style>
				/* Local SVG icons: sized from the surrounding font, no CDN. */
				.wsq-pricing-metabox .wsq-icon { width: 1.1em; height: 1.1em; flex-shrink: 0; vertical-align: -0.16em; }
				.wsq-pricing-metabox .button > .wsq-icon { margin-inline-end: 5px; }
				.wsq-pricing-metabox .wsq-mb-dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; box-shadow: inset 0 0 0 1.5px rgba(255,255,255,0.9); }
				.wsq-pricing-metabox .wsq-mb-chips { display: flex; flex-wrap: wrap; gap: 5px; justify-content: flex-start; margin-top: 6px; }
				.wsq-pricing-metabox .wsq-mb-chip { display: inline-flex; align-items: center; gap: 4px; padding: 2px 9px; border-radius: 999px; font-size: 11px; font-weight: 700; line-height: 1.8; border: 1px solid; }
				.wsq-pricing-metabox .wsq-mb-chip.is-on { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
				.wsq-pricing-metabox .wsq-mb-chip.is-off { background: #f8fafc; border-color: #e2e8f0; color: #64748b; }
				.wsq-pricing-metabox .wsq-mb-chip.is-warn { background: #fffbeb; border-color: #fde68a; color: #92400e; }
				.wsq-pricing-metabox .wsq-mb-chip[title] { cursor: help; }
				.wsq-pricing-metabox .wsq-mb-checks { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 8px; }
				.wsq-pricing-metabox .wsq-mb-check-btn { font-size: 11px; line-height: 1.6; padding: 2px 8px; }
				.wsq-pricing-metabox .wsq-mb-check-note { color: #64748b; font-size: 11px; }
				.wsq-pricing-metabox .wsq-mb-check-result { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 6px; }
				.wsq-pricing-metabox .wsq-mb-check-result[hidden] { display: none; }
			</style>
			<div style="display:flex; justify-content:space-between; align-items:center; background:#f8fafc; padding:10px 14px; border-radius:8px; margin-bottom:15px; border:1px solid #e2e8f0;">
				<div>
					<strong><?php esc_html_e( 'وضعیت فعلی استعلام:', 'woo-smart-quote' ); ?></strong>
					<?php if ( 'quote-request' === $status ) : ?>
						<span style="color:#d97706; font-weight:800; margin-right:6px;"><?php echo WSQ_Icons::get( 'clock' ); ?> <?php esc_html_e( 'در انتظار اعلام قیمت کارشناس', 'woo-smart-quote' ); ?></span>
					<?php elseif ( 'quote-sent' === $status ) : ?>
						<span style="color:#2563eb; font-weight:800; margin-right:6px;"><?php echo WSQ_Icons::get( 'mail' ); ?> <?php esc_html_e( 'پیشنهاد قیمت برای مشتری ارسال شد', 'woo-smart-quote' ); ?></span>
					<?php else : ?>
						<span style="color:#059669; font-weight:800; margin-right:6px;"><?php echo esc_html( wc_get_order_status_name( $status ) ); ?></span>
					<?php endif; ?>
				</div>
				<div>
					<span style="color:#64748b; font-size:12px;"><?php esc_html_e( 'خریدار:', 'woo-smart-quote' ); ?> <b><?php echo esc_html( $order->get_formatted_billing_full_name() ); ?></b> (<?php echo esc_html( $order->get_billing_phone() ); ?>)</span>
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
						<span class="wsq-mb-check-note"><?php esc_html_e( 'توکن‌ها را بدون ارسال پیام از پنل بله و ایتا می‌پرسد.', 'woo-smart-quote' ); ?></span>
					</div>
					<div class="wsq-mb-check-result" id="wsq-check-bots-result" role="status" aria-live="polite" hidden></div>
				</div>
			</div>

			<div class="wsq-pricing-form">
				<input type="hidden" name="action" value="wsq_save_and_send_proposal">
				<input type="hidden" name="order_id" value="<?php echo esc_attr( $order_id ); ?>">
				<input type="hidden" id="wsq_pricing_nonce" name="wsq_pricing_nonce" value="<?php echo esc_attr( wp_create_nonce( 'wsq_pricing_proposal_nonce' ) ); ?>">
				<?php if ( isset( $_GET['wsq_proposal_sent'] ) ) : ?>
					<div style="background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; padding:10px 14px; border-radius:8px; margin-bottom:15px; font-weight:bold;">
						<?php echo WSQ_Icons::get( 'check' ); ?> <?php esc_html_e( 'قیمت‌ها ذخیره و پیش‌فاکتور برای خریدار ارسال شد.', 'woo-smart-quote' ); ?>
					</div>
				<?php endif; ?>

				<!-- Items Pricing Table -->
				<table class="wp-list-table widefat fixed striped" style="margin-bottom:15px;">
					<thead>
						<tr>
							<th style="width:5%;">#</th>
							<th style="width:40%;"><?php esc_html_e( 'شرح کالا / خدمات استعلام شده', 'woo-smart-quote' ); ?></th>
							<th style="width:12%; text-align:center;"><?php esc_html_e( 'تعداد درخواستی', 'woo-smart-quote' ); ?></th>
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
										echo '<div style="font-size:11px; color:#64748b;">';
										foreach ( $meta_data as $meta ) {
											echo esc_html( $meta->display_key ) . ': ' . esc_html( $meta->display_value ) . ' | ';
										}
										echo '</div>';
									}
									?>
								</td>
								<td style="text-align:center;">
									<span style="font-weight:bold; font-size:13px;"><?php echo esc_html( $qty ); ?></span>
								</td>
								<td>
									<input type="number" 
										   name="wsq_item_prices[<?php echo esc_attr( $item_id ); ?>]" 
										   value="<?php echo esc_attr( $unit_price ); ?>" 
										   class="wsq-calc-price regular-text" 
										   data-qty="<?php echo esc_attr( $qty ); ?>" 
										   style="width:100%; font-weight:bold; color:#0f172a;" 
										   step="1000" 
										   placeholder="<?php esc_attr_e( 'قیمت واحد را وارد کنید', 'woo-smart-quote' ); ?>" 
										   required>
								</td>
								<td style="text-align:left; font-weight:bold; color:#4f46e5;" class="wsq-row-subtotal">
									<?php echo number_format( $subtotal ); ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<!-- Proposal Details & Actions -->
				<div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
					<div style="background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #e2e8f0;">
						<label for="wsq_quote_validity" style="display:block; font-weight:bold; margin-bottom:4px;"><?php echo WSQ_Icons::get( 'clock' ); ?> <?php esc_html_e( 'مهلت اعتبار این پیشنهاد قیمت:', 'woo-smart-quote' ); ?></label>
						<input type="text" id="wsq_quote_validity" name="wsq_quote_validity" value="<?php echo esc_attr( $validity ); ?>" class="regular-text" style="width:100%;" placeholder="<?php esc_attr_e( 'مثال: ۲۴ ساعت کاری / تا پایان وقت اداری', 'woo-smart-quote' ); ?>">
						<small style="color:#64748b; display:block; margin-top:4px;"><?php esc_html_e( 'این مهلت در پیش‌فاکتور رسمی و پیام خریدار درج خواهد شد.', 'woo-smart-quote' ); ?></small>
					</div>

					<div style="background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #e2e8f0;">
						<label for="wsq-payment-url" style="display:block; font-weight:bold; margin-bottom:4px;"><?php echo WSQ_Icons::get( 'link' ); ?> <?php esc_html_e( 'لینک پرداخت مستقیم خریدار (Order Pay Link):', 'woo-smart-quote' ); ?></label>
						<input type="text" id="wsq-payment-url" readonly value="<?php echo esc_url( $payment_url ); ?>" style="width:100%; font-size:11px; direction:ltr; background:#fff;">
						<small style="color:#64748b; display:block; margin-top:4px;"><?php esc_html_e( 'خریدار با این لینک مستقیماً به درگاه پرداخت شاپرک متصل می‌شود.', 'woo-smart-quote' ); ?></small>
					</div>
				</div>

				<div id="wsq-pricing-error-notice" style="display:none; background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:10px 14px; border-radius:8px; margin-bottom:12px; font-weight:bold; font-size:13px;" role="alert"></div>

				<div style="background:#eef2ff; border:1px solid #c7d2fe; border-radius:8px; padding:14px; display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
					<div>
						<span style="font-size:13px; color:#3730a3; font-weight:bold;">
							<?php esc_html_e( 'جمع کل محاسبه‌شده استعلام:', 'woo-smart-quote' ); ?> 
							<span id="wsq-calc-grand-total" style="font-size:18px; color:#4f46e5; margin-right:6px;"><?php echo number_format( $order->get_total() ); ?></span> <?php esc_html_e( 'تومان', 'woo-smart-quote' ); ?>
						</span>
					</div>
					<div>
						<button type="button" class="button button-primary button-large wsq-send-proposal-btn" style="background:#10b981; border-color:#059669; font-weight:bold; padding:6px 20px; height:auto; font-size:14px;">
							<?php echo WSQ_Icons::get( 'rocket' ); ?> <?php esc_html_e( 'ذخیره قیمت‌ها و تغییر وضعیت به پیش‌فاکتور صادر شد', 'woo-smart-quote' ); ?>
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
				<div style="background:#fff; border:1px solid #cbd5e1; border-radius:8px; padding:14px; margin-top:10px;">
					<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; border-bottom:1px solid #f1f5f9; padding-bottom:8px;">
						<div style="display:flex; align-items:center; gap:8px;">
							<span style="font-size:16px;"><?php echo WSQ_Icons::get( 'phone' ); ?></span>
							<strong style="color:#0f172a; font-size:13px;"><?php esc_html_e( 'ارسال سریع پیش‌فاکتور به خریدار (شماره استخراج‌شده از فرم):', 'woo-smart-quote' ); ?></strong>
							<span style="direction:ltr; font-family:monospace; background:#f1f5f9; padding:2px 8px; border-radius:4px; font-weight:bold; color:#2563eb;">
								<?php echo esc_html( $buyer_phone ? $buyer_phone : __( 'شماره تلفن ثبت نشده', 'woo-smart-quote' ) ); ?>
							</span>
						</div>
						<span style="font-size:11px; color:#64748b;"><?php esc_html_e( 'با تغییر وضعیت به «پیش‌فاکتور صادر شد»، پیامک/وب‌سرویس نیز خودکار ارسال می‌شود.', 'woo-smart-quote' ); ?></span>
					</div>

					<div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
						<!-- 1-Click Bale Chat Link -->
						<a href="https://ble.ir/+<?php echo esc_attr( $intl_phone ); ?>" target="_blank" class="button" style="background:#0284c7; color:#fff; border-color:#0284c7; font-weight:bold; display:inline-flex; align-items:center; gap:5px;">
							<span class="wsq-mb-dot" style="background:#0284c7;" aria-hidden="true"></span> <?php esc_html_e( 'باز کردن چت در بله خریدار', 'woo-smart-quote' ); ?>
						</a>

						<!-- 1-Click Eitaa Web Link -->
						<a href="https://web.eitaa.com/" target="_blank" class="button" style="background:#ea580c; color:#fff; border-color:#ea580c; font-weight:bold; display:inline-flex; align-items:center; gap:5px;">
							<span class="wsq-mb-dot" style="background:#ea580c;" aria-hidden="true"></span> <?php esc_html_e( 'باز کردن ایتا خریدار', 'woo-smart-quote' ); ?>
						</a>

						<!-- 1-Click WhatsApp Link with prefilled text -->
						<a href="https://api.whatsapp.com/send?phone=<?php echo esc_attr( $intl_phone ); ?>&text=<?php echo rawurlencode( $prop_text ); ?>" target="_blank" class="button" style="background:#22c55e; color:#fff; border-color:#22c55e; font-weight:bold; display:inline-flex; align-items:center; gap:5px;">
							<span class="wsq-mb-dot" style="background:#22c55e;" aria-hidden="true"></span> <?php esc_html_e( 'باز کردن واتساپ با متن آماده', 'woo-smart-quote' ); ?>
						</a>

						<!-- Copy proposal text to clipboard -->
						<button type="button" class="button" id="wsq-copy-proposal-btn" style="border-color:#94a3b8; font-weight:bold;">
							<?php echo WSQ_Icons::get( 'clipboard' ); ?> <?php esc_html_e( 'کپی متن کامل پیشنهاد قیمت', 'woo-smart-quote' ); ?>
						</button>

						<!-- Resend via configured Web Service (FarazSMS/Kavenegar/Safir) -->
						<button type="button" class="button" id="wsq-resend-sms-btn" data-order="<?php echo esc_attr( $order_id ); ?>" style="border-color:#6366f1; color:#4f46e5; font-weight:bold;">
							<?php echo WSQ_Icons::get( 'bolt' ); ?> <?php esc_html_e( 'ارسال دستی وب‌سرویس (پیامک/سفیر بله)', 'woo-smart-quote' ); ?>
						</button>
						<span id="wsq-resend-status" style="font-size:12px; margin-right:8px; font-weight:bold;"></span>
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
				$('#wsq-resend-status').text('').css('color', '#64748b');

				$.post(ajaxurl, {
					action: 'wsq_ajax_resend_buyer_proposal',
					order_id: orderId,
					nonce: '<?php echo wp_create_nonce( 'wsq_buyer_resend_nonce' ); ?>'
				}, function(response) {
					$btn.prop('disabled', false).html(btnHtml);
					if (response.success) {
						$('#wsq-resend-status').text(response.data.message).css('color', '#059669');
					} else {
						$('#wsq-resend-status').text(response.data.message).css('color', '#dc2626');
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

		$item_prices = isset( $_POST['wsq_item_prices'] ) ? (array) $_POST['wsq_item_prices'] : array();
		$validity    = isset( $_POST['wsq_quote_validity'] ) ? sanitize_text_field( $_POST['wsq_quote_validity'] ) : __( '۲۴ ساعت کاری', 'woo-smart-quote' );

		// Save validity meta
		$order->update_meta_data( '_wsq_quote_validity', $validity );

		// Update each item's price
		foreach ( $order->get_items() as $item_id => $item ) {
			if ( isset( $item_prices[ $item_id ] ) ) {
				$unit_price = floatval( $item_prices[ $item_id ] );
				$qty        = $item->get_quantity();
				$line_total = $unit_price * $qty;

				$item->set_subtotal( $line_total );
				$item->set_total( $line_total );
				$item->save();
			}
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
	 * AJAX submit quote request from customer
	 */
	public function ajax_submit_quote() {
		check_ajax_referer( 'wsq_submit_quote', 'wsq_submit_nonce' );

		$name    = isset( $_POST['wsq_name'] ) ? sanitize_text_field( $_POST['wsq_name'] ) : '';
		$email   = isset( $_POST['wsq_email'] ) ? sanitize_email( $_POST['wsq_email'] ) : '';
		$phone   = isset( $_POST['wsq_phone'] ) ? sanitize_text_field( $_POST['wsq_phone'] ) : '';
		$channel = isset( $_POST['wsq_channel'] ) ? sanitize_text_field( $_POST['wsq_channel'] ) : 'all';
		$message = isset( $_POST['wsq_message'] ) ? sanitize_textarea_field( $_POST['wsq_message'] ) : '';

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
