<?php
/**
 * WooCommerce Smart Quote REST API Controller (wsq/v1)
 *
 * Dedicated REST endpoints for the companion mobile app (Pricing Desk,
 * Real-time notifications, QR pairing, quote updates, and PDF invoices).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSQ_REST_API {

	protected static $instance = null;

	const ROUTE_NAMESPACE = 'wsq/v1';
	const OPTION_DEVICES  = 'wsq_paired_devices';
	const TRANSIENT_PAIR  = 'wsq_pairing_session';

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	protected function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register all wsq/v1 REST routes.
	 */
	public function register_routes() {
		// 1. Mobile pairing
		register_rest_route( self::ROUTE_NAMESPACE, '/auth/pair', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'rest_pair_device' ),
			'permission_callback' => '__return_true',
		) );

		// 2. Quotes list
		register_rest_route( self::ROUTE_NAMESPACE, '/quotes', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'rest_get_quotes' ),
			'permission_callback' => array( $this, 'check_auth' ),
		) );

		// 3. Single quote details
		register_rest_route( self::ROUTE_NAMESPACE, '/quotes/(?P<id>\d+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'rest_get_quote' ),
			'permission_callback' => array( $this, 'check_auth' ),
			'args'                => array(
				'id' => array(
					'validate_callback' => function( $param ) {
						return is_numeric( $param );
					},
				),
			),
		) );

		// 4. Update pricing & discounts
		register_rest_route( self::ROUTE_NAMESPACE, '/quotes/(?P<id>\d+)/price', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'rest_price_quote' ),
			'permission_callback' => array( $this, 'check_auth' ),
			'args'                => array(
				'id' => array(
					'validate_callback' => function( $param ) {
						return is_numeric( $param );
					},
				),
			),
		) );

		// 5. PDF generation & download URL
		register_rest_route( self::ROUTE_NAMESPACE, '/quotes/(?P<id>\d+)/pdf', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'rest_get_pdf' ),
			'permission_callback' => array( $this, 'check_auth' ),
			'args'                => array(
				'id' => array(
					'validate_callback' => function( $param ) {
						return is_numeric( $param );
					},
				),
			),
		) );

		// 6. Direct customer notification dispatch
		register_rest_route( self::ROUTE_NAMESPACE, '/quotes/(?P<id>\d+)/notify', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'rest_notify_customer' ),
			'permission_callback' => array( $this, 'check_auth' ),
			'args'                => array(
				'id' => array(
					'validate_callback' => function( $param ) {
						return is_numeric( $param );
					},
				),
			),
		) );

		// 7. Ultra-lightweight background polling updates
		register_rest_route( self::ROUTE_NAMESPACE, '/updates', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'rest_get_updates' ),
			'permission_callback' => array( $this, 'check_auth' ),
		) );
	}

	/**
	 * Permission check: accepts Bearer token from paired devices or WP admin session.
	 *
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public function check_auth( $request ) {
		$auth_header = $request->get_header( 'authorization' );
		$token       = '';

		if ( ! empty( $auth_header ) && preg_match( '/Bearer\s+(.*)$/i', $auth_header, $matches ) ) {
			$token = trim( $matches[1] );
		}

		if ( empty( $token ) ) {
			$token = $request->get_header( 'x-wsq-token' );
		}

		if ( ! empty( $token ) ) {
			$token_hash = hash( 'sha256', $token );
			$devices    = get_option( self::OPTION_DEVICES, array() );

			foreach ( $devices as $device_id => $device ) {
				if ( hash_equals( $device['token_hash'], $token_hash ) ) {
					// Update last active timestamp (throttled to avoid heavy DB writes on every GET)
					if ( empty( $device['last_active'] ) || ( time() - (int) $device['last_active'] ) > 60 ) {
						$devices[ $device_id ]['last_active'] = time();
						update_option( self::OPTION_DEVICES, $devices );
					}

					// Log in user context if user exists
					if ( ! empty( $device['user_id'] ) ) {
						wp_set_current_user( $device['user_id'] );
					}
					return true;
				}
			}

			return new WP_Error(
				'wsq_unauthorized',
				__( 'توکن ارائه‌شده نامعتبر است یا منقضی گردیده است.', 'woo-smart-quote' ),
				array( 'status' => 401 )
			);
		}

		// Fallback for logged-in WP users with WooCommerce management rights
		if ( current_user_can( 'edit_shop_orders' ) || current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		return new WP_Error(
			'wsq_forbidden',
			__( 'برای دسترسی به این بخش نیاز به احراز هویت است.', 'woo-smart-quote' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * POST /wsq/v1/auth/pair
	 *
	 * Validates 10-minute temporary pairing code from QR scan and issues device token.
	 */
	public function rest_pair_device( $request ) {
		$params       = $request->get_json_params();
		$pairing_code = isset( $params['pairing_code'] ) ? sanitize_text_field( $params['pairing_code'] ) : '';
		$device_name  = isset( $params['device_name'] ) ? sanitize_text_field( $params['device_name'] ) : __( 'دستگاه نامشخص', 'woo-smart-quote' );
		$device_id    = isset( $params['device_id'] ) ? sanitize_text_field( $params['device_id'] ) : '';

		if ( empty( $pairing_code ) ) {
			return new WP_Error( 'invalid_code', __( 'کد جفت‌سازی ارسال نشده است.', 'woo-smart-quote' ), array( 'status' => 400 ) );
		}

		// Normalize Persian / Arabic numbers
		$persian_nums = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
		$english_nums = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
		$pairing_code = str_replace( $persian_nums, $english_nums, trim( $pairing_code ) );

		$session = get_transient( self::TRANSIENT_PAIR );
		if ( ! $session || empty( $session['code'] ) || ! hash_equals( (string) $session['code'], $pairing_code ) ) {
			return new WP_Error( 'pairing_expired', __( 'کد جفت‌سازی نامعتبر است یا مهلت ۱۰ دقیقه‌ای آن منقضی شده است.', 'woo-smart-quote' ), array( 'status' => 403 ) );
		}

		if ( empty( $device_id ) ) {
			$device_id = 'dev_' . wp_generate_password( 16, false );
		}

		// Issue permanent device secret token
		$token      = 'wsq_sec_' . wp_generate_password( 48, false, false );
		$token_hash = hash( 'sha256', $token );

		$devices = get_option( self::OPTION_DEVICES, array() );
		$devices[ $device_id ] = array(
			'device_id'   => $device_id,
			'device_name' => $device_name,
			'token_hash'  => $token_hash,
			'user_id'     => isset( $session['user_id'] ) ? (int) $session['user_id'] : get_current_user_id(),
			'paired_at'   => time(),
			'last_active' => time(),
		);
		update_option( self::OPTION_DEVICES, $devices );

		// Consume one-time pairing transient
		delete_transient( self::TRANSIENT_PAIR );

		$user = get_userdata( $devices[ $device_id ]['user_id'] );
		$display_name = $user ? $user->display_name : __( 'مدیر فروشگاه', 'woo-smart-quote' );

		return rest_ensure_response( array(
			'success'           => true,
			'token'             => $token,
			'device_id'         => $device_id,
			'store_name'        => get_bloginfo( 'name' ),
			'store_url'         => home_url(),
			'currency'          => get_woocommerce_currency(),
			'currency_symbol'   => get_woocommerce_currency_symbol(),
			'user_display_name' => $display_name,
		) );
	}

	/**
	 * GET /wsq/v1/quotes
	 *
	 * Returns paginated quotes with filters.
	 */
	public function rest_get_quotes( $request ) {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = max( 1, min( 100, (int) ( $request->get_param( 'per_page' ) ?: 20 ) ) );
		$status   = sanitize_text_field( $request->get_param( 'status' ) ?: 'all' );
		$search   = sanitize_text_field( $request->get_param( 'search' ) ?: '' );

		$query_args = array(
			'paginate' => true,
			'page'     => $page,
			'limit'    => $per_page,
			'orderby'  => 'date',
			'order'    => 'DESC',
		);

		if ( 'pending' === $status ) {
			$query_args['status'] = array( 'wc-quote-request', 'quote-request' );
		} elseif ( 'priced' === $status ) {
			$query_args['status'] = array( 'wc-quote-sent', 'quote-sent' );
		} elseif ( 'all' !== $status && ! empty( $status ) ) {
			$query_args['status'] = array( $status );
		} else {
			$query_args['status'] = array( 'wc-quote-request', 'quote-request', 'wc-quote-sent', 'quote-sent', 'processing', 'completed' );
		}

		if ( ! empty( $search ) ) {
			$query_args['s'] = $search;
		}

		$results = wc_get_orders( $query_args );
		$orders  = is_object( $results ) && isset( $results->orders ) ? $results->orders : (array) $results;
		$total   = is_object( $results ) && isset( $results->total ) ? (int) $results->total : count( $orders );
		$max_pg  = is_object( $results ) && isset( $results->max_num_pages ) ? (int) $results->max_num_pages : 1;

		$quotes_data = array();
		foreach ( $orders as $order ) {
			if ( ! $order instanceof WC_Order ) {
				$order = wc_get_order( $order );
			}
			if ( ! $order ) {
				continue;
			}
			$quotes_data[] = $this->format_quote_summary( $order );
		}

		return rest_ensure_response( array(
			'quotes'     => $quotes_data,
			'pagination' => array(
				'page'        => $page,
				'per_page'    => $per_page,
				'total_items' => $total,
				'total_pages' => $max_pg,
			),
		) );
	}

	/**
	 * GET /wsq/v1/quotes/{id}
	 *
	 * Detailed view of single quote.
	 */
	public function rest_get_quote( $request ) {
		$order_id = absint( $request['id'] );
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			return new WP_Error( 'not_found', __( 'استعلام مورد نظر یافت نشد.', 'woo-smart-quote' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $this->format_quote_detail( $order ) );
	}

	/**
	 * POST /wsq/v1/quotes/{id}/price
	 *
	 * Mobile Pricing Desk: Updates line item prices, discounts, validity, and triggers proposal.
	 */
	public function rest_price_quote( $request ) {
		$order_id = absint( $request['id'] );
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			return new WP_Error( 'not_found', __( 'استعلام مورد نظر یافت نشد.', 'woo-smart-quote' ), array( 'status' => 404 ) );
		}

		$params         = $request->get_json_params();
		$items_data     = isset( $params['items'] ) && is_array( $params['items'] ) ? $params['items'] : array();
		$quote_validity = isset( $params['quote_validity'] ) ? sanitize_text_field( $params['quote_validity'] ) : __( '۲۴ ساعت کاری', 'woo-smart-quote' );
		$admin_note     = isset( $params['admin_note'] ) ? sanitize_textarea_field( $params['admin_note'] ) : '';
		$auto_notify    = ! empty( $params['auto_notify'] );

		// Update quote validity
		$order->update_meta_data( '_wsq_quote_validity', $quote_validity );

		// Index items by item_id
		$items_map = array();
		foreach ( $items_data as $it ) {
			if ( isset( $it['item_id'] ) ) {
				$items_map[ absint( $it['item_id'] ) ] = $it;
			}
		}

		// Apply custom unit prices & discounts to line items
		foreach ( $order->get_items() as $item_id => $item ) {
			if ( isset( $items_map[ $item_id ] ) ) {
				$entry        = $items_map[ $item_id ];
				$qty          = max( 1, $item->get_quantity() );
				$custom_price = isset( $entry['custom_price'] ) ? max( 0.0, floatval( $entry['custom_price'] ) ) : null;
				$discount_pct = isset( $entry['discount_percent'] ) ? max( 0.0, min( 100.0, floatval( $entry['discount_percent'] ) ) ) : 0.0;

				if ( null !== $custom_price ) {
					$line_subtotal = $custom_price * $qty;
					$line_total    = $line_subtotal * ( 1.0 - ( $discount_pct / 100.0 ) );

					$item->set_subtotal( $line_subtotal );
					$item->set_total( $line_total );

					if ( isset( $entry['tax'] ) || isset( $entry['line_tax'] ) ) {
						$line_tax = max( 0.0, floatval( isset( $entry['tax'] ) ? $entry['tax'] : $entry['line_tax'] ) );
						$item->set_total_tax( $line_tax );
					}

					if ( $discount_pct > 0 ) {
						$item->update_meta_data( '_wsq_item_discount_pct', $discount_pct );
					} else {
						$item->delete_meta_data( '_wsq_item_discount_pct' );
					}
					$item->save();
				}
			}
		}

		// Calculate items net total after line item discounts
		$items_net_total = 0.0;
		foreach ( $order->get_items() as $item ) {
			$items_net_total += (float) $item->get_total();
		}

		// Support custom tax total or rate at quote level
		if ( isset( $params['tax_rate'] ) || isset( $params['tax_percent'] ) ) {
			$custom_tax_rate = max( 0.0, floatval( isset( $params['tax_rate'] ) ? $params['tax_rate'] : $params['tax_percent'] ) );
			$order->update_meta_data( '_wsq_custom_tax_rate', $custom_tax_rate );
		}
		if ( isset( $params['tax_total'] ) || isset( $params['tax'] ) ) {
			$custom_tax_total = max( 0.0, floatval( isset( $params['tax_total'] ) ? $params['tax_total'] : $params['tax'] ) );
			$order->set_cart_tax( $custom_tax_total );
			$order->set_total( $items_net_total + $custom_tax_total );
		} elseif ( isset( $custom_tax_rate ) && $custom_tax_rate > 0 ) {
			$calculated_tax = round( $items_net_total * ( $custom_tax_rate / 100.0 ), wc_get_price_decimals() );
			$order->set_cart_tax( $calculated_tax );
			$order->set_total( $items_net_total + $calculated_tax );
		} else {
			$order->calculate_totals( false );
		}
		$order->save();

		// Record structured pricing history
		$history = (array) $order->get_meta( '_wsq_pricing_history' );
		if ( ! is_array( $history ) ) {
			$history = array();
		}

		$current_user = wp_get_current_user();
		$author_name  = ( $current_user && $current_user->exists() ) ? $current_user->display_name : __( 'اپلیکیشن همراه', 'woo-smart-quote' );

		$history_entry = array(
			'timestamp'       => time(),
			'date'            => WSQ_PDF_Generator::instance()->format_jalali_date( time() ),
			'date_iso'        => gmdate( 'c' ),
			'total'           => (float) $order->get_total(),
			'total_formatted' => wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ),
			'validity'        => $quote_validity,
			'admin_note'      => $admin_note,
			'author'          => $author_name,
			'items_count'     => count( $items_data ),
		);
		array_unshift( $history, $history_entry );
		$order->update_meta_data( '_wsq_pricing_history', $history );

		// Record note
		if ( ! empty( $admin_note ) ) {
			$order->add_order_note( sprintf( __( 'یادداشت قیمت‌گذاری موبایل: %s', 'woo-smart-quote' ), $admin_note ) );
		}

		// Update status to quote-sent
		$order->update_status( 'quote-sent', __( 'قیمت‌گذاری توسط اپلیکیشن همراه ذخیره شد و وضعیت به پیش‌فاکتور صادر شد تغییر یافت.', 'woo-smart-quote' ) );
		$order->save();

		// Generate updated PDF immediately
		$pdf_generator = WSQ_PDF_Generator::instance();
		$pdf_path      = $pdf_generator->generate( $order_id );
		$pdf_url       = $pdf_generator->get_pdf_url( $order_id );

		// Immediate customer notification if requested
		$notify_results = array();
		if ( $auto_notify ) {
			$notify_results = WSQ_Buyer_Notifier::instance()->dispatch_to_buyer( $order_id, true );
		}

		return rest_ensure_response( array(
			'success'        => true,
			'message'        => __( 'قیمت‌ها با موفقیت ثبت شدند و پیش‌فاکتور به‌روزرسانی شد.', 'woo-smart-quote' ),
			'order_id'       => $order_id,
			'status'         => 'quote-sent',
			'total'          => (float) $order->get_total(),
			'total_formatted'=> wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ),
			'pdf_url'        => $pdf_url,
			'notified'       => $notify_results,
		) );
	}

	/**
	 * POST /wsq/v1/quotes/{id}/pdf
	 *
	 * Fetches or generates PDF pre-invoice URL.
	 */
	public function rest_get_pdf( $request ) {
		$order_id = absint( $request['id'] );
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			return new WP_Error( 'not_found', __( 'استعلام مورد نظر یافت نشد.', 'woo-smart-quote' ), array( 'status' => 404 ) );
		}

		$pdf_gen = WSQ_PDF_Generator::instance();
		$pdf_url = $pdf_gen->get_pdf_url( $order_id, true );

		if ( ! $pdf_url ) {
			return new WP_Error( 'pdf_failed', __( 'خطا در صدور فایل پیش‌فاکتور PDF.', 'woo-smart-quote' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( array(
			'success'   => true,
			'order_id'  => $order_id,
			'pdf_url'   => $pdf_url,
			'file_name' => basename( $pdf_url ),
		) );
	}

	/**
	 * POST /wsq/v1/quotes/{id}/notify
	 *
	 * Manually triggers customer notification dispatch via chosen channel.
	 */
	public function rest_notify_customer( $request ) {
		$order_id = absint( $request['id'] );
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			return new WP_Error( 'not_found', __( 'استعلام مورد نظر یافت نشد.', 'woo-smart-quote' ), array( 'status' => 404 ) );
		}

		$notifier = WSQ_Buyer_Notifier::instance();
		$results  = $notifier->dispatch_to_buyer( $order_id, true );

		return rest_ensure_response( array(
			'success'   => true,
			'order_id'  => $order_id,
			'results'   => $results,
			'sent_at'   => current_time( 'mysql' ),
			'message'   => __( 'درخواست ارسال اعلان به خریدار پردازش شد.', 'woo-smart-quote' ),
		) );
	}

	/**
	 * GET /wsq/v1/updates?since={unix_timestamp}
	 *
	 * Ultra-lightweight background polling endpoint for WorkManager background service.
	 */
	public function rest_get_updates( $request ) {
		$since = absint( $request->get_param( 'since' ) ?: 0 );

		// 1. Pending quotes count
		$pending_orders = wc_get_orders( array(
			'status' => array( 'wc-quote-request', 'quote-request' ),
			'return' => 'ids',
			'limit'  => -1,
		) );
		$pending_count = count( $pending_orders );

		// 2. Quotes updated or created since timestamp
		$new_count     = 0;
		$updated_count = 0;
		$latest_id     = 0;

		if ( $since > 0 ) {
			$recent_orders = wc_get_orders( array(
				'date_modified' => '>' . gmdate( 'Y-m-d H:i:s', $since ),
				'limit'         => 50,
				'orderby'       => 'date_modified',
				'order'         => 'DESC',
			) );

			foreach ( $recent_orders as $o ) {
				$created = $o->get_date_created() ? $o->get_date_created()->getTimestamp() : 0;
				if ( $created > $since ) {
					$new_count++;
				} else {
					$updated_count++;
				}
				if ( 0 === $latest_id ) {
					$latest_id = $o->get_id();
				}
			}
		}

		return rest_ensure_response( array(
			'pending_count'        => $pending_count,
			'new_quotes_count'     => $new_count,
			'updated_quotes_count' => $updated_count,
			'server_time'          => time(),
			'latest_quote_id'      => $latest_id,
		) );
	}

	/**
	 * Helpers: Format Quote Summary for Lists
	 */
	private function format_quote_summary( WC_Order $order ) {
		$created     = $order->get_date_created();
		$created_ts  = $created ? $created->getTimestamp() : time();
		$jalali_date = WSQ_PDF_Generator::instance()->format_jalali_date( $created_ts );

		$buyer_name  = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		if ( empty( $buyer_name ) ) {
			$buyer_name = $order->get_formatted_billing_full_name();
		}
		if ( empty( $buyer_name ) ) {
			$buyer_name = __( 'خریدار نامشخص', 'woo-smart-quote' );
		}

		$phone = $order->get_billing_phone();
		if ( empty( $phone ) ) {
			$phone = (string) $order->get_meta( '_wsq_customer_phone' );
		}

		$status = $order->get_status();
		$status_label = wc_get_order_status_name( $status );

		return array(
			'id'                => $order->get_id(),
			'number'            => $order->get_order_number(),
			'status'            => $status,
			'status_label'      => $status_label,
			'created_at'        => $created ? $created->date( 'c' ) : '',
			'created_at_jalali' => $jalali_date,
			'customer'          => array(
				'name'  => $buyer_name,
				'phone' => $phone,
				'email' => $order->get_billing_email(),
			),
			'items_count'       => $order->get_item_count(),
			'total'             => (float) $order->get_total(),
			'total_formatted'   => wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ),
			'currency'          => $order->get_currency(),
			'validity'          => (string) $order->get_meta( '_wsq_quote_validity' ),
		);
	}

	/**
	 * Helpers: Format Full Quote Details
	 */
	private function format_quote_detail( WC_Order $order ) {
		$summary = $this->format_quote_summary( $order );

		$items_data = array();
		foreach ( $order->get_items() as $item_id => $item ) {
			$product      = $item->get_product();
			$qty          = $item->get_quantity();
			$subtotal     = (float) $item->get_subtotal();
			$total        = (float) $item->get_total();
			$unit_price   = $qty > 0 ? ( $subtotal / $qty ) : 0;
			$discount_pct = (float) $item->get_meta( '_wsq_item_discount_pct' );

			$img_url = '';
			$sku     = '';
			if ( $product ) {
				$img_id  = $product->get_image_id();
				$img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'thumbnail' ) : '';
				$sku     = $product->get_sku();
			}

			$items_data[] = array(
				'item_id'          => $item_id,
				'product_id'       => $item->get_product_id(),
				'variation_id'     => $item->get_variation_id(),
				'name'             => $item->get_name(),
				'quantity'         => $qty,
				'unit_price'       => $unit_price,
				'discount_percent' => $discount_pct,
				'subtotal'         => $subtotal,
				'total'            => $total,
				'image_url'        => $img_url ? $img_url : '',
				'sku'              => $sku,
			);
		}

		// Order notes
		$order_notes = array();
		if ( function_exists( 'wc_get_order_notes' ) ) {
			$notes = wc_get_order_notes( array( 'order_id' => $order->get_id() ) );
			foreach ( $notes as $n ) {
				$order_notes[] = array(
					'id'            => $n->id,
					'content'       => $n->content,
					'date'          => $n->date_created ? $n->date_created->date( 'Y-m-d H:i' ) : '',
					'customer_note' => (bool) $n->customer_note,
				);
			}
		}

		$pdf_url = WSQ_PDF_Generator::instance()->get_pdf_url( $order->get_id(), false );

		$history = (array) $order->get_meta( '_wsq_pricing_history' );
		if ( ! is_array( $history ) ) {
			$history = array();
		}

		$summary['customer']['note']    = $order->get_customer_note();
		$summary['items']               = $items_data;
		$summary['financials']          = array(
			'subtotal'        => (float) $order->get_subtotal(),
			'discount_total'  => (float) $order->get_discount_total(),
			'tax_total'       => (float) $order->get_total_tax(),
			'total'           => (float) $order->get_total(),
			'currency'        => $order->get_currency(),
			'currency_symbol' => get_woocommerce_currency_symbol( $order->get_currency() ),
		);
		$summary['payment_url']         = $order->get_checkout_payment_url();
		$summary['pdf_url']             = $pdf_url ? $pdf_url : '';
		$summary['order_notes']         = $order_notes;
		$summary['pricing_history']     = $history;

		return $summary;
	}

	/* ------------------------------------------------------------------
	 * Pairing Session Management (Called by WP Admin Tab)
	 * ------------------------------------------------------------------ */

	/**
	 * Generate or fetch active 10-minute pairing session for Admin.
	 *
	 * @param int  $user_id
	 * @param bool $force_new
	 * @return array{pairing_code:string,expires_in:int,expires_at:int,payload_json:string}
	 */
	public static function get_or_create_pairing_session( $user_id = 0, $force_new = false ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$session = $force_new ? false : get_transient( self::TRANSIENT_PAIR );
		$now     = time();

		if ( ! $session || empty( $session['code'] ) || empty( $session['expires_at'] ) || $session['expires_at'] <= $now ) {
			$code       = sprintf( '%04d-%04d', wp_rand( 1000, 9999 ), wp_rand( 1000, 9999 ) );
			$expires_at = $now + 600; // 10 minutes TTL
			$session    = array(
				'code'       => $code,
				'user_id'    => $user_id,
				'expires_at' => $expires_at,
			);
			set_transient( self::TRANSIENT_PAIR, $session, 600 );
		}

		$payload = array(
			'site_url'     => home_url(),
			'api_base'     => rest_url( self::ROUTE_NAMESPACE ),
			'pairing_code' => $session['code'],
			'site_name'    => get_bloginfo( 'name' ),
			'currency'     => get_woocommerce_currency(),
			'expires_at'   => $session['expires_at'],
		);

		return array(
			'pairing_code' => $session['code'],
			'expires_in'   => max( 0, $session['expires_at'] - $now ),
			'expires_at'   => $session['expires_at'],
			'payload_json' => wp_json_encode( $payload ),
		);
	}

	/**
	 * Get list of all currently paired devices.
	 *
	 * @return array
	 */
	public static function get_paired_devices() {
		return (array) get_option( self::OPTION_DEVICES, array() );
	}

	/**
	 * Revoke access for a single paired device.
	 *
	 * @param string $device_id
	 * @return bool
	 */
	public static function revoke_device( $device_id ) {
		$devices = get_option( self::OPTION_DEVICES, array() );
		if ( isset( $devices[ $device_id ] ) ) {
			unset( $devices[ $device_id ] );
			update_option( self::OPTION_DEVICES, $devices );
			return true;
		}
		return false;
	}
}
