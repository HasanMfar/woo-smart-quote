<?php
/**
 * Plugin Name: نرخنگار
 * Description: سیستم استعلام قیمت و صدور پیش‌فاکتور رسمی ماده ۱۶۹ برای ووکامرس؛ همراه با اپلیکیشن اندروید و اتصال مستقیم به بله و ایتا.
 * Version:     1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Requires Plugins: woocommerce
 * Author:      نرخنگار
 * License:     GPL-2.0+
 * Text Domain: woo-smart-quote
 * Domain Path: /languages
 * WC requires at least: 7.0
 * WC tested up to: 9.9
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Check server runtime requirements.
$wsq_missing_requirements = array();
if ( PHP_VERSION_ID < 80000 ) {
	$wsq_missing_requirements[] = 'PHP 8.0+';
}
foreach ( array( 'gd', 'mbstring' ) as $wsq_extension ) {
	if ( ! extension_loaded( $wsq_extension ) ) {
		$wsq_missing_requirements[] = $wsq_extension;
	}
}
/**
 * Deactivation: drop the plugin's cron events so a deactivated install stops
 * waking WP-Cron every minute. Registered before the runtime gate so a
 * degraded install can still be deactivated cleanly. Options and quote data
 * are left untouched; both events are rescheduled on the next request after
 * reactivation.
 */
register_deactivation_hook( __FILE__, function () {
	wp_clear_scheduled_hook( 'wsq_daily_cleanup' );
	wp_clear_scheduled_hook( 'wsq_poll_updates' );
} );

if ( $wsq_missing_requirements ) {
	$wsq_requirements_notice = static function () use ( $wsq_missing_requirements ) {
		$message = sprintf( __( 'افزونه نرخنگار غیرفعال است. پیش‌نیازهای زیر باید روی سرور نصب یا به‌روزرسانی شوند: %s', 'woo-smart-quote' ), implode( ', ', $wsq_missing_requirements ) );
		printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $message ) );
	};
	add_action( 'admin_notices', $wsq_requirements_notice );
	add_action( 'network_admin_notices', $wsq_requirements_notice );
	register_activation_hook( __FILE__, static function () use ( $wsq_missing_requirements ) {
		$message = sprintf( __( 'افزونه نرخنگار غیرفعال است. پیش‌نیازهای زیر باید روی سرور نصب یا به‌روزرسانی شوند: %s', 'woo-smart-quote' ), implode( ', ', $wsq_missing_requirements ) );
		wp_die( esc_html( $message ), 'نرخنگار', array( 'back_link' => true ) );
	} );
	return;
}

// Define constants.
define( 'WSQ_VERSION', '1.0.0' );
define( 'WSQ_PLUGIN_FILE', __FILE__ );
define( 'WSQ_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WSQ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main instance of Nerkhnegar (woo-smart-quote).
 */
function woo_smart_quote_init() {
	// Check if WooCommerce is active
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'wsq_woocommerce_missing_notice' );
		return;
	}

	// Load core classes
	require_once WSQ_PLUGIN_DIR . 'includes/class-wsq-plugin.php';

	// Initialize the plugin
	WSQ_Plugin::instance();
}

// Register plugin initialization directly and unconditionally.
add_action( 'plugins_loaded', 'woo_smart_quote_init', 20 );

/**
 * Activation: create the inbound-messages table. The polling schedule is
 * registered later on init (the wsq_minute recurrence is added by the
 * WSQ_Updates class, which is not loaded yet at activation time).
 *
 * The class file is loaded directly because the normal bootstrap runs on
 * plugins_loaded, which has not fired yet during activation.
 */
register_activation_hook( __FILE__, function () {
	require_once WSQ_PLUGIN_DIR . 'includes/class-wsq-logger.php';
	require_once WSQ_PLUGIN_DIR . 'includes/class-wsq-updates.php';

	WSQ_Updates::install();
} );

/**
 * Notice if WooCommerce is missing.
 */
function wsq_woocommerce_missing_notice() {
	?>
	<div class="notice notice-error is-dismissible">
		<p><?php esc_html_e( 'افزونه نرخنگار برای فعالیت نیازمند نصب و فعال‌سازی ووکامرس است.', 'woo-smart-quote' ); ?></p>
	</div>
	<?php
}

/**
 * Declare compatibility with High-Performance Order Storage (HPOS) and
 * WooCommerce checkout blocks, so WooCommerce does not flag the plugin
 * as incompatible under WooCommerce > Status > Features.
 */
add_action( 'before_woocommerce_init', function () {
	if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		return;
	}
	\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
		'custom_order_tables',
		WSQ_PLUGIN_FILE,
		true
	);
	\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
		'cart_checkout_blocks',
		WSQ_PLUGIN_FILE,
		true
	);
} );
