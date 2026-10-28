<?php
/**
 * Handle Quote Session Storage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSQ_Session {

	protected static $instance = null;

	const SESSION_KEY = 'wsq_quote_items';

	/**
	 * Session key holding recently removed items so the buyer can undo a
	 * removal without re-adding the product from the catalogue.
	 */
	const RECENT_KEY = 'wsq_quote_removed';

	/**
	 * Session key holding all items when the entire cart is cleared,
	 * enabling a full single-click undo.
	 */
	const CLEARED_ALL_KEY = 'wsq_quote_cleared_all';

	/**
	 * How many removed items stay restorable.
	 */
	const RECENT_LIMIT = 5;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	protected function __construct() {
		// Ensure WC session is loaded before we try to use it
		add_action( 'woocommerce_init', array( $this, 'init_session' ) );

		// AJAX requests never pass through woocommerce_init's normal frontend
		// flow (WC()->session is not bootstrapped there), so initialize the
		// session inside the AJAX handler as well.
		add_action( 'wp_ajax_wsq_add_to_quote', array( $this, 'init_session' ), 1 );
		add_action( 'wp_ajax_nopriv_wsq_add_to_quote', array( $this, 'init_session' ), 1 );
		add_action( 'wp_ajax_wsq_remove_item', array( $this, 'init_session' ), 1 );
		add_action( 'wp_ajax_nopriv_wsq_remove_item', array( $this, 'init_session' ), 1 );
		add_action( 'wp_ajax_wsq_update_item', array( $this, 'init_session' ), 1 );
		add_action( 'wp_ajax_nopriv_wsq_update_item', array( $this, 'init_session' ), 1 );
		add_action( 'wp_ajax_wsq_restore_item', array( $this, 'init_session' ), 1 );
		add_action( 'wp_ajax_nopriv_wsq_restore_item', array( $this, 'init_session' ), 1 );
		add_action( 'wp_ajax_wsq_clear_all', array( $this, 'init_session' ), 1 );
		add_action( 'wp_ajax_nopriv_wsq_clear_all', array( $this, 'init_session' ), 1 );
		add_action( 'wp_ajax_wsq_restore_all', array( $this, 'init_session' ), 1 );
		add_action( 'wp_ajax_nopriv_wsq_restore_all', array( $this, 'init_session' ), 1 );
		add_action( 'wp_ajax_wsq_submit_quote', array( $this, 'init_session' ), 1 );
		add_action( 'wp_ajax_nopriv_wsq_submit_quote', array( $this, 'init_session' ), 1 );
	}

	public function init_session() {
		if ( isset( WC()->session ) && ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}
	}

	/**
	 * Add item to quote session
	 */
	public function add_item( $item_data ) {
		if ( ! isset( WC()->session ) ) {
			return false;
		}

		$item_data = wp_parse_args( $item_data, array(
			'product_id'   => 0,
			'variation_id' => 0,
			'variations'   => array(),
			'quantity'     => 1,
		) );
		$items = $this->get_items();

		// Generate a unique ID for the item based on product and variations
		$item_key = $this->make_key( $item_data );
		$items = $this->merge_items( $items, array( $item_key => $item_data ) );
		if ( false === $items ) {
			return false;
		}

		$this->set_items( $items );
		return true;
	}

	/**
	 * Build a bounded merge without writing session data; current row metadata wins.
	 *
	 * @return array|false Merged items, or false if the union exceeds 100 rows.
	 */
	protected function merge_items( $items, $incoming ) {
		if ( count( $items + $incoming ) > 100 ) {
			return false;
		}

		foreach ( $incoming as $item_key => $item ) {
			$quantity = max( 1, min( 9999, (int) $item['quantity'] ) );
			if ( isset( $items[ $item_key ] ) ) {
				$quantity += max( 1, min( 9999, (int) $items[ $item_key ]['quantity'] ) );
				$items[ $item_key ]['quantity'] = min( 9999, $quantity );
			} else {
				$item['quantity'] = $quantity;
				$items[ $item_key ] = $item;
			}
		}

		return $items;
	}

	/**
	 * Stable key for an item (product + variation + selected attributes).
	 *
	 * @param array $item_data
	 * @return string
	 */
	public function make_key( $item_data ) {
		$variation_id = isset( $item_data['variation_id'] ) ? (string) $item_data['variation_id'] : '0';
		$variations   = isset( $item_data['variations'] ) ? $item_data['variations'] : array();
		if ( is_array( $variations ) ) {
			ksort( $variations );
		}

		return md5( $item_data['product_id'] . '_' . $variation_id . '_' . serialize( $variations ) );
	}

	/**
	 * Whether an item with the same product/variation/attributes exists.
	 *
	 * @param string $item_key
	 * @return bool
	 */
	public function has_item( $item_key ) {
		$items = $this->get_items();
		return isset( $items[ $item_key ] );
	}

	/**
	 * Set a new quantity for one item in the quote session.
	 *
	 * The quantity is clamped to 1..9999 so a hostile client cannot turn the
	 * session into an unbounded order.
	 *
	 * @param string $item_key  Item key as stored in the session.
	 * @param int    $quantity  Desired quantity.
	 * @return bool True when the item exists and was updated.
	 */
	public function update_quantity( $item_key, $quantity ) {
		$items = $this->get_items();

		if ( ! isset( $items[ $item_key ] ) ) {
			return false;
		}

		$quantity = (int) $quantity;
		if ( $quantity < 1 ) {
			$quantity = 1;
		}
		if ( $quantity > 9999 ) {
			$quantity = 9999;
		}

		$items[ $item_key ]['quantity'] = $quantity;
		$this->set_items( $items );

		return true;
	}

	/**
	 * Find every session item that belongs to a product (and optionally to a
	 * specific variation).
	 *
	 * @param int $product_id   Product id.
	 * @param int $variation_id Variation id (0 = any).
	 * @return array Map of item_key => item data.
	 */
	public function find_items_by_product( $product_id, $variation_id = 0 ) {
		$product_id   = (int) $product_id;
		$variation_id = (int) $variation_id;

		$found = array();
		foreach ( $this->get_items() as $item_key => $item ) {
			if ( (int) $item['product_id'] !== $product_id ) {
				continue;
			}
			$item_var = ! empty( $item['variation_id'] ) ? (int) $item['variation_id'] : 0;
			if ( $variation_id && $item_var !== $variation_id ) {
				continue;
			}
			$found[ $item_key ] = $item;
		}

		return $found;
	}

	/**
	 * Total quantity of a product already present in the quote session.
	 *
	 * @param int $product_id   Product id.
	 * @param int $variation_id Variation id (0 = any).
	 * @return int
	 */
	public function get_product_quantity( $product_id, $variation_id = 0 ) {
		$total = 0;
		foreach ( $this->find_items_by_product( $product_id, $variation_id ) as $item ) {
			$total += (int) $item['quantity'];
		}
		return $total;
	}

	/**
	 * Get all items from quote
	 */
	public function get_items() {
		if ( ! isset( WC()->session ) ) {
			return array();
		}
		
		$items = WC()->session->get( self::SESSION_KEY );
		return is_array( $items ) ? $items : array();
	}

	/**
	 * Set items to session
	 */
	public function set_items( $items ) {
		if ( isset( WC()->session ) ) {
			WC()->session->set( self::SESSION_KEY, $items );
		}
	}

	/**
	 * Remove a single item from the quote session by its key
	 *
	 * @param string $item_key
	 * @return bool True if the item existed and was removed.
	 */
	public function remove_item( $item_key ) {
		$items = $this->get_items();

		if ( ! isset( $items[ $item_key ] ) ) {
			return false;
		}

		// Keep the removed payload around so the removal can be undone.
		$recent = $this->get_recent();
		$recent[ $item_key ] = $items[ $item_key ];
		if ( count( $recent ) > self::RECENT_LIMIT ) {
			$recent = array_slice( $recent, -self::RECENT_LIMIT, null, true );
		}
		$this->set_recent( $recent );

		unset( $items[ $item_key ] );
		$this->set_items( $items );
		return true;
	}

	/**
	 * Recently removed items, keyed by their original item key.
	 *
	 * @return array
	 */
	public function get_recent() {
		if ( ! isset( WC()->session ) ) {
			return array();
		}
		$recent = WC()->session->get( self::RECENT_KEY );
		return is_array( $recent ) ? $recent : array();
	}

	/**
	 * Store the recently removed items.
	 *
	 * @param array $recent Items keyed by item key.
	 */
	public function set_recent( $recent ) {
		if ( isset( WC()->session ) ) {
			WC()->session->set( self::RECENT_KEY, $recent );
		}
	}

	/**
	 * Restore a previously removed item under its original key.
	 *
	 * @param string $item_key Item key to restore.
	 * @return bool True when the item was restored.
	 */
	public function restore_item( $item_key ) {
		$recent = $this->get_recent();

		if ( ! isset( $recent[ $item_key ] ) ) {
			return false;
		}

		$items = $this->merge_items( $this->get_items(), array( $item_key => $recent[ $item_key ] ) );
		if ( false === $items ) {
			return false;
		}
		$this->set_items( $items );

		unset( $recent[ $item_key ] );
		$this->set_recent( $recent );

		return true;
	}

	/**
	 * Clear all items from quote session while preserving them for undo.
	 *
	 * @return bool True if there were items to clear.
	 */
	public function clear_all() {
		$items = $this->get_items();
		if ( empty( $items ) ) {
			return false;
		}

		if ( isset( WC()->session ) ) {
			WC()->session->set( self::CLEARED_ALL_KEY, $items );
			WC()->session->set( self::SESSION_KEY, null );
		}

		return true;
	}

	/**
	 * Restore all items previously cleared with clear_all().
	 *
	 * @return bool True if items were restored.
	 */
	public function restore_all() {
		if ( ! isset( WC()->session ) ) {
			return false;
		}

		$cleared = WC()->session->get( self::CLEARED_ALL_KEY );
		if ( ! is_array( $cleared ) || empty( $cleared ) ) {
			return false;
		}

		$items = $this->merge_items( $this->get_items(), $cleared );
		if ( false === $items ) {
			return false;
		}
		$this->set_items( $items );
		WC()->session->set( self::CLEARED_ALL_KEY, null );
		return true;
	}

	/**
	 * Clear quote session
	 */
	public function clear() {
		if ( isset( WC()->session ) ) {
			WC()->session->set( self::SESSION_KEY, null );
			WC()->session->set( self::RECENT_KEY, null );
			WC()->session->set( self::CLEARED_ALL_KEY, null );
		}
	}

	/**
	 * Get total quantity of all items in the quote.
	 *
	 * This is the canonical count shown on the storefront: badges, sticky
	 * bar and table headings all use the same number.
	 *
	 * @return int
	 */
	public function get_item_count() {
		$items = $this->get_items();
		$count = 0;
		foreach ( $items as $item ) {
			$count += isset( $item['quantity'] ) ? (int) $item['quantity'] : 0;
		}
		return $count;
	}

	/**
	 * Get the number of distinct lines (products/variations) in the quote.
	 *
	 * @return int
	 */
	public function get_line_count() {
		return count( $this->get_items() );
	}
}
