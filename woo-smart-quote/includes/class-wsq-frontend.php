<?php
/**
 * Frontend Hooks and Logic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSQ_Frontend {

	protected static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	protected function __construct() {
		// Single product button
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'add_to_quote_button' ), 15 );

		// Shop catalog / loop button
		if ( 'yes' === get_option( 'wsq_enable_archive_button', 'yes' ) ) {
			add_action( 'woocommerce_after_shop_loop_item', array( $this, 'add_to_quote_button_loop' ), 15 );
		}

		// Conditionally hide add to cart if no price
		if ( 'yes' === get_option( 'wsq_hide_add_to_cart', 'no' ) ) {
			add_filter( 'woocommerce_is_purchasable', array( $this, 'hide_add_to_cart_if_no_price' ), 10, 2 );
		}

		// Floating quote cart widget
		if ( 'yes' === get_option( 'wsq_enable_floating_cart', 'yes' ) ) {
			add_action( 'wp_footer', array( $this, 'render_floating_cart' ) );
		}

		// Enqueue scripts and styles
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// AJAX handlers
		add_action( 'wp_ajax_wsq_add_to_quote', array( $this, 'ajax_add_to_quote' ) );
		add_action( 'wp_ajax_nopriv_wsq_add_to_quote', array( $this, 'ajax_add_to_quote' ) );

		add_action( 'wp_ajax_wsq_remove_item', array( $this, 'ajax_remove_item' ) );
		add_action( 'wp_ajax_nopriv_wsq_remove_item', array( $this, 'ajax_remove_item' ) );

		add_action( 'wp_ajax_wsq_update_item', array( $this, 'ajax_update_item' ) );
		add_action( 'wp_ajax_nopriv_wsq_update_item', array( $this, 'ajax_update_item' ) );

		add_action( 'wp_ajax_wsq_restore_item', array( $this, 'ajax_restore_item' ) );
		add_action( 'wp_ajax_nopriv_wsq_restore_item', array( $this, 'ajax_restore_item' ) );

		add_action( 'wp_ajax_wsq_clear_all', array( $this, 'ajax_clear_all' ) );
		add_action( 'wp_ajax_nopriv_wsq_clear_all', array( $this, 'ajax_clear_all' ) );

		add_action( 'wp_ajax_wsq_restore_all', array( $this, 'ajax_restore_all' ) );
		add_action( 'wp_ajax_nopriv_wsq_restore_all', array( $this, 'ajax_restore_all' ) );

		// Invalidate the cached quote-page id when page content changes.
		add_action( 'save_post', array( $this, 'flush_quote_page_cache' ) );
	}

	public function enqueue_scripts() {
		wp_enqueue_style( 'woo-smart-quote-frontend', WSQ_PLUGIN_URL . 'assets/css/frontend.css', array(), WSQ_VERSION );
		wp_enqueue_script( 'woo-smart-quote-frontend', WSQ_PLUGIN_URL . 'assets/js/frontend.js', array( 'jquery' ), WSQ_VERSION, true );

		$quote_page_url = $this->get_quote_page_url();
		$session        = WSQ_Session::instance();

		wp_localize_script( 'woo-smart-quote-frontend', 'wsq_params', array(
			'ajax_url'         => admin_url( 'admin-ajax.php' ),
			'nonce'            => wp_create_nonce( 'wsq-add-to-quote' ),
			'quote_page_url'   => $quote_page_url,
			'shop_url'         => esc_url( wc_get_page_permalink( 'shop' ) ),
			'is_logged_in'     => is_user_logged_in(),
			'account_url'      => esc_url( wc_get_page_permalink( 'myaccount' ) ),
			'initial_count'    => $session->get_item_count(),
			'initial_lines'    => $session->get_line_count(),
			'i18n'             => array(
				'added'         => __( 'محصول به لیست استعلام اضافه شد.', 'woo-smart-quote' ),
				'error'         => __( 'خطا در افزودن محصول به استعلام.', 'woo-smart-quote' ),
				'network_error' => __( 'ارتباط با سرور برقرار نشد. دوباره تلاش کنید.', 'woo-smart-quote' ),
				'view_quote'    => __( 'مشاهده لیست استعلام', 'woo-smart-quote' ),
				'items_suffix'  => __( 'قلم کالا', 'woo-smart-quote' ),
				'in_list'       => __( 'در لیست استعلام', 'woo-smart-quote' ),
				'removed'       => __( 'کالا از لیست استعلام حذف شد.', 'woo-smart-quote' ),
				'undo'          => __( 'بازگردانی', 'woo-smart-quote' ),
				'qty_updated'   => __( 'تعداد کالا به‌روزرسانی شد.', 'woo-smart-quote' ),
				'err_name'      => __( 'نام و نام خانوادگی را وارد کنید.', 'woo-smart-quote' ),
				'err_email'     => __( 'یک نشانی پست الکترونیک معتبر وارد کنید.', 'woo-smart-quote' ),
				'err_phone'     => __( 'شماره موبایل معتبر (مثال: 09123456789) وارد کنید.', 'woo-smart-quote' ),
				'err_fix'       => __( 'لطفاً خطاهای فرم را برطرف کنید.', 'woo-smart-quote' ),
				'success_title' => __( 'درخواست استعلام با موفقیت ثبت شد', 'woo-smart-quote' ),
				'order_prefix'  => __( 'کد پیگیری استعلام:', 'woo-smart-quote' ),
				'view_order'    => __( 'مشاهده جزئیات استعلام', 'woo-smart-quote' ),
				'empty_title'   => __( 'لیست استعلام قیمت شما خالی است.', 'woo-smart-quote' ),
				'empty_desc'    => __( 'هنوز محصولی را برای دریافت پیش‌فاکتور یا استعلام قیمت انتخاب نکرده‌اید.', 'woo-smart-quote' ),
				'empty_cta'     => __( 'مشاهده کاتالوگ محصولات و استعلام', 'woo-smart-quote' ),
				'all_removed'   => __( 'همه اقلام از لیست استعلام حذف شدند.', 'woo-smart-quote' ),
				'all_restored'  => __( 'تمامی اقلام استعلام بازگردانده شدند.', 'woo-smart-quote' ),
				'copy_code'     => __( 'کپی کد پیگیری', 'woo-smart-quote' ),
				'copied'        => __( 'کپی شد!', 'woo-smart-quote' ),
				'copy'          => __( 'کپی', 'woo-smart-quote' ),
				'copy_failed'   => __( 'کپی نشد؛ کد را دستی انتخاب کنید.', 'woo-smart-quote' ),
				'submitting'    => __( 'در حال ارسال…', 'woo-smart-quote' ),
				'lines_suffix'  => __( 'نوع کالا', 'woo-smart-quote' ),
				'count_format'  => __( '%1$s قلم کالا · %2$s نوع کالا', 'woo-smart-quote' ),
				'count_announce' => __( 'تعداد اقلام استعلام: %1$s قلم کالا در %2$s نوع.', 'woo-smart-quote' ),
				'confirm_clear' => __( 'همه اقلام از لیست استعلام حذف شوند؟', 'woo-smart-quote' ),
				'cleared'       => __( 'همه اقلام از لیست استعلام حذف شدند.', 'woo-smart-quote' ),
				'qty_error'     => __( 'تعداد کالا ذخیره نشد. مقدار قبلی بازگردانده شد.', 'woo-smart-quote' ),
				'qty_range'     => __( 'تعداد باید بین ۱ تا ۹۹۹۹ باشد.', 'woo-smart-quote' ),
				'err_summary'   => __( 'لطفاً خطاهای زیر را برطرف کنید:', 'woo-smart-quote' ),
				'close'         => __( 'بستن', 'woo-smart-quote' ),
				'receipt'       => __( 'مشاهده رسید استعلام', 'woo-smart-quote' ),
				'success_note'  => __( 'کد پیگیری را برای پیگیری وضعیت استعلام نگه دارید.', 'woo-smart-quote' ),
				'sticky_cta'    => __( 'تکمیل و ارسال درخواست', 'woo-smart-quote' ),
				'choose_options' => __( 'انتخاب گزینه‌ها و استعلام', 'woo-smart-quote' ),
				'view_quote_cart' => __( 'مشاهده سبد استعلام قیمت', 'woo-smart-quote' ),
			),
		) );
	}

	/**
	 * Resolve (and cache) the id of the page that holds the quote list
	 * shortcode.
	 *
	 * @return int Page id, 0 when not found.
	 */
	public function resolve_quote_page_id() {
		$page_id = get_transient( 'wsq_quote_page_id' );

		if ( false === $page_id ) {
			global $wpdb;
			// The shortcode registered by WSQ_Shortcodes is [woo_smart_quote_list].
			$page_id = $wpdb->get_var( $wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN (%s, %s) AND post_content LIKE %s LIMIT 1",
				'page',
				'post',
				'%[woo_smart_quote_list]%'
			) );
			set_transient( 'wsq_quote_page_id', $page_id ? (string) $page_id : '0', 12 * HOUR_IN_SECONDS );
		}

		return (int) $page_id;
	}

	/**
	 * Get the quote page URL.
	 *
	 * Scanning wp_posts on every request is expensive on large sites, so the
	 * resolved page id is cached in a transient (12h) and flushed when any
	 * post/page is saved.
	 */
	public function get_quote_page_url() {
		$page_id = $this->resolve_quote_page_id();

		if ( $page_id > 0 ) {
			$permalink = get_permalink( $page_id );
			if ( $permalink ) {
				return $permalink;
			}
		}

		// Fall back to the canonical user-editable slug.
		$slug_page = get_page_by_path( 'quote-list' );
		if ( $slug_page && 'publish' === $slug_page->post_status ) {
			return get_permalink( $slug_page );
		}

		return home_url( '/quote-list/' );
	}

	/**
	 * Flush the cached quote page id on post save.
	 *
	 * @param int $post_id
	 */
	public function flush_quote_page_cache( $post_id ) {
		delete_transient( 'wsq_quote_page_id' );
	}

	/**
	 * Add to Quote button on single product page
	 */
	public function add_to_quote_button() {
		global $product;
		if ( ! $product ) {
			return;
		}

		$btn_text = get_option( 'wsq_button_text', __( 'افزودن به لیست استعلام قیمت', 'woo-smart-quote' ) );
		if ( empty( $btn_text ) ) {
			$btn_text = __( 'افزودن به لیست استعلام قیمت', 'woo-smart-quote' );
		}

		$this->render_quote_button( $product, $btn_text, false );
	}

	/**
	 * Add to Quote button on catalog / shop archive loop
	 */
	public function add_to_quote_button_loop() {
		global $product;
		if ( ! $product ) {
			return;
		}

		$btn_text = get_option( 'wsq_button_text', __( 'افزودن به لیست استعلام', 'woo-smart-quote' ) );
		if ( empty( $btn_text ) ) {
			$btn_text = __( 'افزودن به استعلام', 'woo-smart-quote' );
		}

		$this->render_quote_button( $product, $btn_text, true );
	}

	/**
	 * Render the add-to-quote control.
	 *
	 * Two states are rendered side by side and toggled by the frontend script:
	 * the "add" button and an "already in the quote list" link. Knowing that a
	 * product is already listed saves the buyer from adding it twice.
	 *
	 * @param WC_Product $product  Product being rendered.
	 * @param string     $btn_text Button label.
	 * @param bool       $is_loop  Whether this is the compact archive variant.
	 */
	private function render_quote_button( $product, $btn_text, $is_loop ) {
		$product_id  = (int) $product->get_id();
		$in_list_qty = WSQ_Session::instance()->get_product_quantity( $product_id );

		// In the archive loop a variable product cannot be quoted without its
		// chosen options, so send the buyer to the product page instead of
		// silently quoting the (optionless) parent product.
		if ( $is_loop && $product->is_type( 'variable' ) ) {
			?>
			<div class="wsq-loop-btn-wrap">
				<a href="<?php echo esc_url( get_permalink( $product_id ) ); ?>" class="button wsq-loop-btn wsq-loop-choose">
					<span class="wsq-btn-icon"><?php echo WSQ_Icons::get( 'clipboard' ); ?></span>
					<span class="wsq-btn-text"><?php esc_html_e( 'انتخاب گزینه‌ها و استعلام', 'woo-smart-quote' ); ?></span>
				</a>
				<?php if ( $in_list_qty > 0 ) : ?>
					<a href="<?php echo esc_url( $this->get_quote_page_url() ); ?>" class="button wsq-loop-btn wsq-is-added" data-product_id="<?php echo esc_attr( $product_id ); ?>">
						<span class="wsq-btn-icon"><?php echo WSQ_Icons::get( 'check' ); ?></span>
						<span class="wsq-btn-text"><?php esc_html_e( 'در لیست استعلام', 'woo-smart-quote' ); ?> <span class="wsq-added-count" data-count="<?php echo esc_attr( $in_list_qty ); ?>"><?php echo esc_html( number_format_i18n( $in_list_qty ) ); ?></span></span>
					</a>
				<?php endif; ?>
				<div class="wsq-add-to-quote-message" role="status" aria-live="polite" hidden></div>
			</div>
			<?php
			return;
		}

		$wrap_class  = $is_loop ? 'wsq-loop-btn-wrap' : 'wsq-btn-wrap';
		$btn_class   = 'button wsq-add-to-quote-button' . ( $is_loop ? ' wsq-loop-btn' : ' alt' );
		$added_class = 'button wsq-is-added' . ( $is_loop ? ' wsq-loop-btn' : ' alt' );
		?>
			<div class="<?php echo esc_attr( $wrap_class ); ?>">
				<button type="button" class="<?php echo esc_attr( $btn_class ); ?>" data-product_id="<?php echo esc_attr( $product_id ); ?>"<?php echo $in_list_qty > 0 ? ' hidden' : ''; ?>>
					<span class="wsq-btn-icon" aria-hidden="true"><?php echo WSQ_Icons::get( 'clipboard' ); ?></span>
					<span class="wsq-btn-text"><?php echo esc_html( $btn_text ); ?></span>
				</button>
				<a href="<?php echo esc_url( $this->get_quote_page_url() ); ?>" class="<?php echo esc_attr( $added_class ); ?>" data-product_id="<?php echo esc_attr( $product_id ); ?>"<?php echo $in_list_qty > 0 ? '' : ' hidden'; ?>>
				<span class="wsq-btn-icon" aria-hidden="true"><?php echo WSQ_Icons::get( 'check' ); ?></span>
				<span class="wsq-btn-text"><?php esc_html_e( 'در لیست استعلام', 'woo-smart-quote' ); ?> <span class="wsq-added-count" data-count="<?php echo esc_attr( $in_list_qty ); ?>"><?php echo esc_html( number_format_i18n( $in_list_qty ) ); ?></span></span>
			</a>
			<div class="wsq-add-to-quote-message" role="status" aria-live="polite" hidden></div>
		</div>
		<?php
	}

	/**
	 * Hide add to cart if product has no price. Variable products get their
	 * prices from children, so the (blank) parent price must never make
	 * them unpurchasable.
	 */
	public function hide_add_to_cart_if_no_price( $purchasable, $product ) {
		if ( ! $product || $product->is_type( 'variable' ) ) {
			return $purchasable;
		}

		if ( '' === $product->get_price() || 0 === (float) $product->get_price() ) {
			return false;
		}
		return $purchasable;
	}

	/**
	 * Whether the current request renders the quote-list page.
	 *
	 * On that page the sticky action bar takes over, so the floating widget
	 * would only duplicate it (and overlap it on mobile).
	 *
	 * @return bool
	 */
	public function is_quote_page() {
		$page_id = get_transient( 'wsq_quote_page_id' );
		if ( false === $page_id ) {
			$page_id = $this->resolve_quote_page_id();
		}
		$queried = get_queried_object_id();
		return $queried && (int) $page_id === (int) $queried;
	}

	/**
	 * Floating Quote Cart Widget in wp_footer
	 */
	public function render_floating_cart() {
		if ( $this->is_quote_page() ) {
			return;
		}

		$session   = WSQ_Session::instance();
		$count     = $session->get_item_count();
		$quote_url = $this->get_quote_page_url();
		?>
		<div class="wsq-floating-cart <?php echo $count > 0 ? 'is-visible' : ''; ?>" data-wsq-floating>
			<a href="<?php echo esc_url( $quote_url ); ?>" class="wsq-floating-link" aria-label="<?php echo esc_attr( sprintf( __( 'مشاهده سبد استعلام قیمت؛ %s قلم کالا', 'woo-smart-quote' ), number_format_i18n( $count ) ) ); ?>">
				<span class="wsq-floating-icon" aria-hidden="true"><?php echo WSQ_Icons::get( 'clipboard' ); ?></span>
				<span class="wsq-floating-label"><?php esc_html_e( 'سبد استعلام قیمت:', 'woo-smart-quote' ); ?></span>
				<span class="wsq-floating-count" data-wsq-floating-count aria-hidden="true"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
			</a>
		</div>
		<?php
	}

	/**
	 * AJAX add to quote
	 */
	public function ajax_add_to_quote() {
		check_ajax_referer( 'wsq-add-to-quote', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$raw_qty    = isset( $_POST['quantity'] ) ? wp_unslash( $_POST['quantity'] ) : 1;
		$fa_digits  = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
		$ar_digits  = array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
		$en_digits  = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
		$raw_qty    = str_replace( $fa_digits, $en_digits, (string) $raw_qty );
		$raw_qty    = str_replace( $ar_digits, $en_digits, $raw_qty );
		$quantity   = wc_stock_amount( $raw_qty );

		$variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
		$variations   = isset( $_POST['variations'] ) ? (array) $_POST['variations'] : array();

		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'شناسه محصول نامعتبر است.', 'woo-smart-quote' ) ) );
		}

		// Only published, visible products may enter the quote session —
		// never trust the client-uploaded product id blindly.
		$product = wc_get_product( $variation_id ? $variation_id : $product_id );
		if ( ! $product || 'publish' !== get_post_status( $product->get_id() ) || ! $product->is_visible() ) {
			// Variable products report the parent's post status as "publish".
			WSQ_Logger::warning( 'frontend', 'Rejected add-to-quote for unavailable product.', array(
				'product_id'   => $product_id,
				'variation_id' => $variation_id,
			) );
			wp_send_json_error( array( 'message' => __( 'این محصول برای استعلام در دسترس نیست.', 'woo-smart-quote' ) ) );
		}

		// Sanity-cap the quantity to keep sessions tiny.
		$quantity = max( 1, min( 9999, $quantity ) );

		$item_data = array(
			'product_id'   => $product_id,
			'variation_id' => $variation_id,
			'quantity'     => $quantity,
			'variations'   => array_map( 'sanitize_text_field', wp_unslash( array_map( 'strval', $variations ) ) ),
		);

		// Cap total distinct items so a misbehaving client cannot inflate the
		// user session indefinitely.
		$session = WSQ_Session::instance();
		if ( count( $session->get_items() ) >= 100 && ! $session->has_item( $session->make_key( $item_data ) ) ) {
			wp_send_json_error( array( 'message' => __( 'حداکثر تعداد اقلام استعلام تکمیل شده است.', 'woo-smart-quote' ) ) );
		}

		$added = $session->add_item( $item_data );

		if ( $added ) {
			wp_send_json_success( array_merge(
				array( 'message' => __( 'محصول با موفقیت به لیست استعلام اضافه شد.', 'woo-smart-quote' ) ),
				$this->quote_state( $session )
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'خطا در افزودن محصول به استعلام.', 'woo-smart-quote' ) ) );
		}
	}

	/**
	 * Canonical quote state returned by every mutating AJAX endpoint.
	 *
	 * `count` is the sum of quantities (the number shown everywhere),
	 * `line_count` is the number of distinct rows and `product_quantities`
	 * lets the script keep every add-to-quote button in sync.
	 *
	 * @param WSQ_Session $session Quote session.
	 * @return array
	 */
	private function quote_state( $session ) {
		$quantities = array();

		foreach ( $session->get_items() as $item ) {
			$product_id = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
			if ( $product_id <= 0 ) {
				continue;
			}
			$quantity = isset( $item['quantity'] ) ? (int) $item['quantity'] : 0;
			$quantities[ $product_id ] = isset( $quantities[ $product_id ] )
				? $quantities[ $product_id ] + $quantity
				: $quantity;
		}

		return array(
			'count'              => $session->get_item_count(),
			'line_count'         => $session->get_line_count(),
			'product_quantities' => $quantities,
		);
	}

	/**
	 * AJAX update the quantity of one item in the quote list.
	 */
	public function ajax_update_item() {
		check_ajax_referer( 'wsq-add-to-quote', 'nonce' );

		$item_key  = isset( $_POST['item_key'] ) ? sanitize_text_field( wp_unslash( $_POST['item_key'] ) ) : '';
		$raw_qty   = isset( $_POST['quantity'] ) ? wp_unslash( $_POST['quantity'] ) : 0;
		$fa_digits = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
		$ar_digits = array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
		$en_digits = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
		$raw_qty   = str_replace( $fa_digits, $en_digits, (string) $raw_qty );
		$raw_qty   = str_replace( $ar_digits, $en_digits, $raw_qty );
		$quantity  = absint( $raw_qty );

		if ( '' === $item_key ) {
			wp_send_json_error( array( 'message' => __( 'کلید کالا نامعتبر است.', 'woo-smart-quote' ) ) );
		}

		$session = WSQ_Session::instance();

		$quantity = max( 1, min( 9999, $quantity ) );

		if ( ! $session->update_quantity( $item_key, $quantity ) ) {
			wp_send_json_error( array( 'message' => __( 'این کالا در لیست استعلام نیست.', 'woo-smart-quote' ) ) );
		}

		wp_send_json_success( array_merge(
			array(
				'message'  => __( 'تعداد کالا به‌روزرسانی شد.', 'woo-smart-quote' ),
				'item_key' => $item_key,
				'quantity' => $quantity,
			),
			$this->quote_state( $session )
		) );
	}

	/**
	 * AJAX restore a previously removed item (the "undo" of a removal).
	 */
	public function ajax_restore_item() {
		check_ajax_referer( 'wsq-add-to-quote', 'nonce' );

		$item_key = isset( $_POST['item_key'] ) ? sanitize_text_field( wp_unslash( $_POST['item_key'] ) ) : '';

		if ( '' === $item_key ) {
			wp_send_json_error( array( 'message' => __( 'کلید کالا نامعتبر است.', 'woo-smart-quote' ) ) );
		}

		$session = WSQ_Session::instance();

		if ( ! $session->restore_item( $item_key ) ) {
			wp_send_json_error( array( 'message' => __( 'امکان بازگردانی این کالا وجود ندارد.', 'woo-smart-quote' ) ) );
		}

		wp_send_json_success( array_merge(
			array( 'message' => __( 'کالا به لیست استعلام بازگردانده شد.', 'woo-smart-quote' ) ),
			$this->quote_state( $session )
		) );
	}

	/**
	 * AJAX remove item from quote
	 */
	public function ajax_remove_item() {
		check_ajax_referer( 'wsq-add-to-quote', 'nonce' );

		$item_key = isset( $_POST['item_key'] ) ? sanitize_text_field( wp_unslash( $_POST['item_key'] ) ) : '';

		if ( '' === $item_key ) {
			wp_send_json_error( array( 'message' => __( 'کلید کالا نامعتبر است.', 'woo-smart-quote' ) ) );
		}

		$session = WSQ_Session::instance();
		$removed = $session->remove_item( $item_key );

		if ( $removed ) {
			wp_send_json_success( array_merge(
				array( 'message' => __( 'کالا با موفقیت حذف شد.', 'woo-smart-quote' ) ),
				$this->quote_state( $session )
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'خطا در حذف کالا.', 'woo-smart-quote' ) ) );
		}
	}

	/**
	 * AJAX clear all items from quote session (with undo capability).
	 */
	public function ajax_clear_all() {
		check_ajax_referer( 'wsq-add-to-quote', 'nonce' );

		$session = WSQ_Session::instance();
		$cleared = $session->clear_all();

		if ( $cleared ) {
			wp_send_json_success( array_merge(
				array( 'message' => __( 'همه اقلام از لیست استعلام حذف شدند.', 'woo-smart-quote' ) ),
				$this->quote_state( $session )
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'لیست استعلام از قبل خالی است.', 'woo-smart-quote' ) ) );
		}
	}

	/**
	 * AJAX restore all cleared items from undo.
	 */
	public function ajax_restore_all() {
		check_ajax_referer( 'wsq-add-to-quote', 'nonce' );

		$session  = WSQ_Session::instance();
		$restored = $session->restore_all();

		if ( $restored ) {
			wp_send_json_success( array_merge(
				array( 'message' => __( 'تمامی اقلام استعلام بازگردانده شدند.', 'woo-smart-quote' ) ),
				$this->quote_state( $session )
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'اقلامی برای بازگردانی یافت نشد.', 'woo-smart-quote' ) ) );
		}
	}
}
