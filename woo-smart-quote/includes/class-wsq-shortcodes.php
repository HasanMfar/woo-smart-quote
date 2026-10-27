<?php
/**
 * Shortcodes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSQ_Shortcodes {

	protected static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	protected function __construct() {
		add_shortcode( 'woo_smart_quote_list', array( $this, 'quote_list_shortcode' ) );
		// Note: wsq_remove_item AJAX is handled by WSQ_Frontend (returns the
		// updated count the frontend JS relies on).
	}

	public function quote_list_shortcode( $atts ) {
		ob_start();

		$session = WSQ_Session::instance();
		$items   = $session->get_items();

		// The template also renders the styled empty state; always go
		// through it so the shortcode and the template stay in sync.
		wc_get_template(
			'quote-list.php',
			array( 'items' => $items ),
			'woo-smart-quote/',
			WSQ_PLUGIN_DIR . 'templates/'
		);

		return ob_get_clean();
	}
}
