<?php
/**
 * Main Plugin Class
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSQ_Plugin {

	/**
	 * Single instance of the class
	 *
	 * @var WSQ_Plugin
	 */
	protected static $instance = null;

	/**
	 * Main WSQ_Plugin Instance.
	 *
	 * @return WSQ_Plugin
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	protected function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Include required core files.
	 */
	private function includes() {
		require_once WSQ_PLUGIN_DIR . 'includes/class-wsq-logger.php';
		require_once WSQ_PLUGIN_DIR . 'includes/class-wsq-icons.php';
		require_once WSQ_PLUGIN_DIR . 'includes/class-wsq-session.php';
		require_once WSQ_PLUGIN_DIR . 'includes/class-wsq-frontend.php';
		require_once WSQ_PLUGIN_DIR . 'includes/class-wsq-shortcodes.php';
		require_once WSQ_PLUGIN_DIR . 'includes/class-wsq-order.php';
		require_once WSQ_PLUGIN_DIR . 'includes/class-wsq-pdf-generator.php';
		require_once WSQ_PLUGIN_DIR . 'includes/class-wsq-messenger-api.php';
		require_once WSQ_PLUGIN_DIR . 'includes/class-wsq-bale-api.php';
		require_once WSQ_PLUGIN_DIR . 'includes/class-wsq-eitaa-api.php';
		require_once WSQ_PLUGIN_DIR . 'includes/class-wsq-queue.php';
		require_once WSQ_PLUGIN_DIR . 'includes/class-wsq-buyer-notifier.php';
		require_once WSQ_PLUGIN_DIR . 'includes/class-wsq-updates.php';
		require_once WSQ_PLUGIN_DIR . 'includes/class-wsq-bot.php';
		require_once WSQ_PLUGIN_DIR . 'includes/class-wsq-stats.php';
		require_once WSQ_PLUGIN_DIR . 'includes/class-wsq-rest-api.php';

		// Instantiate classes
		WSQ_Session::instance();
		WSQ_Shortcodes::instance();
		WSQ_Order::instance();
		WSQ_PDF_Generator::instance();
		WSQ_Queue::instance();
		WSQ_Buyer_Notifier::instance();
		WSQ_Updates::instance();
		WSQ_Bot::instance();
		WSQ_Stats::instance();
		WSQ_REST_API::instance();
		if ( is_admin() ) {
			require_once WSQ_PLUGIN_DIR . 'includes/class-wsq-admin.php';
			WSQ_Admin::instance();
		}
		// admin-ajax.php reports is_admin() === true, so also load the frontend
		// class whenever we are servicing an AJAX request (its add-to-quote,
		// remove-item and floating-cart handlers are needed there).
		if ( ! is_admin() || defined( 'DOING_AJAX' ) ) {
			WSQ_Frontend::instance();
		}
	}

	/**
	 * Hook into actions and filters.
	 */
	private function init_hooks() {
		add_action( 'init', array( $this, 'init' ) );
		// Register custom order statuses for quotes
		add_action( 'init', array( $this, 'register_order_statuses' ) );
		add_filter( 'wc_order_statuses', array( $this, 'add_quote_order_statuses' ) );

		// Housekeeping: purge stale generated PDFs from uploads.
		add_action( 'wsq_daily_cleanup', array( $this, 'run_daily_cleanup' ) );
		add_action( 'init', array( $this, 'schedule_cleanup' ) );

		// Keep the plugin's own screens free of third-party image requests.
		add_action( 'wp_enqueue_scripts', array( $this, 'disable_emoji_on_quote_page' ), 5 );
		add_action( 'admin_enqueue_scripts', array( $this, 'disable_emoji_on_admin_page' ), 5 );
		add_action( 'admin_enqueue_scripts', array( $this, 'disable_emoji_on_order_screen' ), 5 );
	}

	/**
	 * Skip WordPress' emoji-to-image converter on the quote page.
	 *
	 * Core swaps every emoji on the page for an SVG hosted on s.w.org. That
	 * page renders buyer text and product names, so leaving it on would drag a
	 * third-party request (and a slow one on filtered networks) into the
	 * checkout-adjacent flow. Native emoji fonts are used instead.
	 */
	public function disable_emoji_on_quote_page() {
		if ( WSQ_Frontend::instance()->is_quote_page() ) {
			$this->remove_emoji_support();
		}
	}

	/**
	 * Same for the plugin's settings screen, which lists inbound buyer messages.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function disable_emoji_on_admin_page( $hook ) {
		if ( 'toplevel_page_woo-smart-quote' === $hook ) {
			$this->remove_emoji_support();
		}
	}

	/**
	 * Same for the order editor, where the pricing meta box lives.
	 *
	 * Besides the icons, the box carries the proposal message in a hidden
	 * textarea; letting core swap its emoji for images would silently drop
	 * them from the text copied for the buyer.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function disable_emoji_on_order_screen( $hook ) {
		if ( 'woocommerce_page_wc-orders' === $hook ) {
			$this->remove_emoji_support();
			return;
		}

		// Classic (post-based) order editor only; other post types keep core behaviour.
		if ( 'post.php' === $hook && 'shop_order' === get_post_type() ) {
			$this->remove_emoji_support();
		}
	}

	/**
	 * Unhook core's emoji detection script and stylesheet.
	 */
	private function remove_emoji_support() {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		add_filter( 'emoji_svg_url', '__return_false' );
	}

	/**
	 * Register a daily schedule (WP cron) and launch it once.
	 *
	 * @return void
	 */
	public function schedule_cleanup() {
		// Gate on the event itself: an option flag would leave cleanup
		// permanently unscheduled after a restore or an external cron reset.
		if ( wp_next_scheduled( 'wsq_daily_cleanup' ) ) {
			return;
		}
		update_option( 'wsq_cleanup_scheduled', 1 );
		wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'wsq_daily_cleanup' );
	}

	/**
	 * Delete generated PDF files older than the retention window and prune
	 * Action Scheduler actions stuck in our group.
	 *
	 * @return void
	 */
	public function run_daily_cleanup() {
		$retention_days = (int) get_option( 'wsq_pdf_retention_days', 30 );
		if ( $retention_days <= 0 ) {
			$retention_days = 30;
		}

		$upload_dir = wp_upload_dir();
		$pdf_dir    = $upload_dir['basedir'] . '/woo-smart-quote-pdfs';
		$count      = 0;

		if ( is_dir( $pdf_dir ) ) {
			$cutoff = time() - ( $retention_days * DAY_IN_SECONDS );

			$files = glob( $pdf_dir . '/*.pdf' );
			if ( is_array( $files ) ) {
				foreach ( $files as $file ) {
					$mtime = @filemtime( $file );
					if ( false !== $mtime && $mtime < $cutoff && wp_delete_file( $file ) ) {
						$count++;
					}
				}
			}
		}

		if ( $count > 0 ) {
			WSQ_Logger::info( 'cleanup', sprintf( 'Removed %d expired PDF file(s) older than %d day(s).', $count, $retention_days ) );
		}
	}

	/**
	 * Init plugin when WordPress Initializes.
	 */
	public function init() {
		// Set up localization
		load_plugin_textdomain( 'woo-smart-quote', false, dirname( plugin_basename( WSQ_PLUGIN_FILE ) ) . '/languages' );
	}

	/**
	 * Register custom order statuses for quote management.
	 */
	public function register_order_statuses() {
		register_post_status( 'wc-quote-request', array(
			'label'                     => _x( 'New Quote Request', 'Order status', 'woo-smart-quote' ),
			'public'                    => false,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: count */
			'label_count'               => _n_noop( 'Quote Request <span class="count">(%s)</span>', 'Quote Requests <span class="count">(%s)</span>', 'woo-smart-quote' ),
		) );

		register_post_status( 'wc-quote-sent', array(
			'label'                     => _x( 'Quote Sent', 'Order status', 'woo-smart-quote' ),
			'public'                    => false,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: count */
			'label_count'               => _n_noop( 'Quote Sent <span class="count">(%s)</span>', 'Quotes Sent <span class="count">(%s)</span>', 'woo-smart-quote' ),
		) );
	}

	/**
	 * Add custom statuses to WooCommerce order statuses array.
	 *
	 * @param array $order_statuses
	 * @return array
	 */
	public function add_quote_order_statuses( $order_statuses ) {
		$new_statuses = array();

		// Add our statuses after 'processing'
		foreach ( $order_statuses as $key => $status ) {
			$new_statuses[ $key ] = $status;
			if ( 'wc-processing' === $key ) {
				$new_statuses['wc-quote-request'] = _x( 'Quote Request', 'Order status', 'woo-smart-quote' );
				$new_statuses['wc-quote-sent']    = _x( 'Quote Sent', 'Order status', 'woo-smart-quote' );
			}
		}

		return $new_statuses;
	}
}
