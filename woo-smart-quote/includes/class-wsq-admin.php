<?php
/**
 * Admin Settings Page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSQ_Admin {

	protected static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	protected function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_wsq_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_wsq_webhook_action', array( $this, 'ajax_webhook_action' ) );
		add_action( 'wp_ajax_wsq_inbox_reply', array( $this, 'ajax_inbox_reply' ) );
		add_action( 'wp_ajax_wsq_inbox_read', array( $this, 'ajax_inbox_read' ) );
		add_action( 'wp_ajax_wsq_refresh_pairing', array( $this, 'ajax_refresh_pairing' ) );
		add_action( 'wp_ajax_wsq_revoke_device', array( $this, 'ajax_revoke_device' ) );
	}

	public function enqueue_scripts( $hook ) {
		if ( 'toplevel_page_woo-smart-quote' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'woo-smart-quote-admin', WSQ_PLUGIN_URL . 'assets/css/admin.css', array(), WSQ_VERSION );
		wp_enqueue_script( 'woo-smart-quote-clipboard', WSQ_PLUGIN_URL . 'assets/js/wsq-clipboard.js', array(), WSQ_VERSION, false );
		wp_enqueue_script( 'woo-smart-quote-qrcode', WSQ_PLUGIN_URL . 'assets/js/jquery.qrcode.min.js', array( 'jquery' ), WSQ_VERSION, true );
		wp_enqueue_script( 'woo-smart-quote-admin', WSQ_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery', 'woo-smart-quote-clipboard', 'woo-smart-quote-qrcode' ), WSQ_VERSION, true );

		wp_localize_script( 'woo-smart-quote-admin', 'wsq_admin_params', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'wsq_admin_test_nonce' ),
			'i18n'     => array(
				'testing'              => __( 'در حال ارسال پیام تست…', 'woo-smart-quote' ),
				'test_ok'              => __( 'پیام تست با موفقیت ارسال شد!', 'woo-smart-quote' ),
				'test_failed'          => __( 'خطا در ارسال پیام تست: ', 'woo-smart-quote' ),
				'test_timeout'         => __( 'پاسخی از سرور دریافت نشد؛ دوباره تلاش کنید.', 'woo-smart-quote' ),
				'no_chat_id'           => __( 'لطفاً حداقل یک شناسه چت (Chat ID) و توکن وارد کنید.', 'woo-smart-quote' ),
				'request_failed'       => __( 'خطا در ارتباط با سرور.', 'woo-smart-quote' ),
				'error'                => __( 'خطا', 'woo-smart-quote' ),
				'webhook_loading'      => __( 'در حال ارتباط با بله…', 'woo-smart-quote' ),
				'webhook_delete_confirm' => __( 'وبهوک بله حذف شود؟ پیام‌ها از این پس با پولینگ دریافت می‌شوند.', 'woo-smart-quote' ),
				'reply'                => __( 'ارسال', 'woo-smart-quote' ),
				'sending'              => __( 'در حال ارسال…', 'woo-smart-quote' ),
				'reply_required'       => __( 'متن پاسخ را بنویسید.', 'woo-smart-quote' ),
				'reply_ok'             => __( 'پاسخ ارسال شد.', 'woo-smart-quote' ),
				'reply_failed'         => __( 'خطا در ارسال پاسخ: ', 'woo-smart-quote' ),
				'copy'                 => __( 'کپی', 'woo-smart-quote' ),
				'copied'               => __( 'کپی شد', 'woo-smart-quote' ),
				'copy_failed'          => __( 'کپی نشد؛ متن را دستی انتخاب کنید.', 'woo-smart-quote' ),
				'copy_chat_label'      => __( 'کپی شناسه چت %s', 'woo-smart-quote' ),
				'read_all_confirm'     => __( 'همه پیام‌های خوانده‌نشده به‌عنوان خوانده‌شده علامت بخورند؟', 'woo-smart-quote' ),
				'read_all_ok'          => __( 'همه پیام‌ها خوانده شدند.', 'woo-smart-quote' ),
				'read_ok'              => __( 'پیام خوانده‌شده علامت خورد.', 'woo-smart-quote' ),
				'read_failed'          => __( 'خطا در علامت‌گذاری پیام‌ها.', 'woo-smart-quote' ),
				'inbox_empty_unread'   => __( 'همه پیام‌ها خوانده شده‌اند. پیام جدیدی برای بررسی وجود ندارد.', 'woo-smart-quote' ),
				'inbox_empty_all'      => __( 'هنوز پیامی دریافت نشده است.', 'woo-smart-quote' ),
				'search_empty'         => __( 'هیچ دسته‌بندی مطابق با جستجوی شما یافت نشد.', 'woo-smart-quote' ),
				'search_results'       => __( '%s دسته‌بندی یافت شد.', 'woo-smart-quote' ),
				'token_inserted'       => __( 'توکن %s در متن درج شد.', 'woo-smart-quote' ),
				'unsaved_changes'      => __( 'تغییرات ذخیره‌نشده دارید.', 'woo-smart-quote' ),
				'button_text_default'  => __( 'افزودن به لیست استعلام قیمت', 'woo-smart-quote' ),
				'stamp_text_default'   => __( 'تأیید شد - استعلام قیمت رسمی', 'woo-smart-quote' ),
				'revoke_confirm'       => __( 'آیا از ابطال دسترسی این دستگاه اطمینان دارید؟ دستگاه بلافاصله خارج خواهد شد.', 'woo-smart-quote' ),
				'device_revoked'       => __( 'دسترسی دستگاه با موفقیت ابطال شد.', 'woo-smart-quote' ),
				'revoke_failed'        => __( 'خطا در ابطال دسترسی دستگاه.', 'woo-smart-quote' ),
				'pairing_refreshed'    => __( 'کد جفت‌سازی جدید ایجاد شد.', 'woo-smart-quote' ),
				'refresh_failed'       => __( 'خطا در ایجاد کد جدید.', 'woo-smart-quote' ),
				'expired'              => __( 'منقضی شده - لطفاً کد جدید ایجاد کنید', 'woo-smart-quote' ),
			),
		) );
	}

	public function add_menu_page() {
		$icon_svg = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" stroke="currentColor"><path d="M4 2.5C4 1.95 4.45 1.5 5 1.5H12L16 5.5V17.5C16 18.05 15.55 18.5 15 18.5H5C4.45 18.5 4 18.05 4 17.5V2.5Z" stroke-width="1.4" stroke-linejoin="round" fill="none"/><path d="M12 1.5V5.5H16" stroke-width="1.4" stroke-linejoin="round"/><path d="M7 8C7 7 7.5 6.5 8.5 6.5" stroke-width="1.5" stroke-linecap="round"/><circle cx="8" cy="9" r="0.9" fill="currentColor"/><path d="M10.5 8C10.5 7 11 6.5 12 6.5" stroke-width="1.5" stroke-linecap="round"/><circle cx="11.5" cy="9" r="0.9" fill="currentColor"/><line x1="7" y1="12" x2="13" y2="12" stroke-width="1.3" stroke-linecap="round"/><line x1="7" y1="14.5" x2="11" y2="14.5" stroke-width="1.3" stroke-linecap="round"/></svg>' );

		// Show the number of unread buyer messages next to the menu entry so
		// the team notices new inquiries without opening the settings page.
		$unread     = WSQ_Updates::instance()->unread_count();
		$menu_title = __( 'Smart Quote', 'woo-smart-quote' );

		if ( $unread > 0 ) {
			$menu_title .= sprintf(
				' <span class="update-plugins count-%1$d"><span class="update-count">%2$s</span><span class="screen-reader-text">%3$s</span></span>',
				(int) $unread,
				number_format_i18n( $unread ),
				esc_html__( 'پیام خوانده‌نشده', 'woo-smart-quote' )
			);
		}

		add_menu_page(
			__( 'Smart Quote Settings', 'woo-smart-quote' ),
			$menu_title,
			'manage_woocommerce',
			'woo-smart-quote',
			array( $this, 'settings_page_html' ),
			$icon_svg,
			56
		);
	}

	/**
	 * Compute setup completion and live status for the settings header.
	 *
	 * Steps: (1) Bale connected, (2) Eitaa connected, (3) message template,
	 * (4) company block on the PDF tab, (5) buyer delivery fully configured.
	 * Each step is worth 20 points; chips show the live channel states.
	 *
	 * @return array{percent:int,bale_ok:bool,eitaa_ok:bool,buyer_on:bool}
	 */
	public function get_setup_progress() {
		$bale_token  = trim( (string) get_option( 'wsq_bale_token', '' ) );
		$bale_chats  = trim( (string) get_option( 'wsq_bale_default_chat_ids', '' ) );
		$eitaa_token = trim( (string) get_option( 'wsq_eitaa_token', '' ) );
		$eitaa_chats = trim( (string) get_option( 'wsq_eitaa_default_chat_ids', '' ) );

		$bale_ok  = '' !== $bale_token && '' !== $bale_chats;
		$eitaa_ok = '' !== $eitaa_token && '' !== $eitaa_chats;
		$template = trim( (string) get_option( 'wsq_message_template', '' ) );
		$company  = trim( (string) get_option( 'wsq_company_name', '' ) );

		$provider = get_option( 'wsq_buyer_sms_provider', 'none' );
		if ( ! in_array( $provider, array( 'farazsms', 'ippanel', 'kavenegar', 'safir_bale' ), true ) ) {
			$provider = 'none';
		}
		$credential = ( 'safir_bale' === $provider )
			? get_option( 'wsq_buyer_bale_token', '' )
			: get_option( 'wsq_buyer_sms_apikey', '' );
		$buyer_on   = ( 'yes' === get_option( 'wsq_buyer_sms_enabled', 'no' ) )
			&& ( 'none' !== $provider )
			&& ( '' !== trim( (string) $credential ) );

		$percent = 0;
		foreach ( array( $bale_ok, $eitaa_ok, '' !== $template, '' !== $company, $buyer_on ) as $step_done ) {
			if ( $step_done ) {
				$percent += 20;
			}
		}

		return array(
			'percent'  => (int) $percent,
			'bale_ok'  => $bale_ok,
			'eitaa_ok' => $eitaa_ok,
			'buyer_on' => $buyer_on,
		);
	}


	/**
	 * Settings API group for each tab that saves options.
	 *
	 * @return array<string,string>
	 */
	private function settings_groups() {
		return array(
			'general'    => 'wsq_general_group',
			'messengers' => 'wsq_messengers_group',
			'buyer'      => 'wsq_buyer_group',
			'bot'        => 'wsq_bot_group',
			'routing'    => 'wsq_routing_group',
			'display'    => 'wsq_display_group',
		);
	}

	/**
	 * Allow shop managers to save the settings groups they can already view.
	 *
	 * @return string
	 */
	public function settings_capability() {
		return 'manage_woocommerce';
	}

	public function register_settings() {
		$groups = $this->settings_groups();

		// options.php checks this filter before saving a group; without it the
		// page is visible to shop managers but saving returns a permission error.
		foreach ( $groups as $group ) {
			add_filter( 'option_page_capability_' . $group, array( $this, 'settings_capability' ) );
		}

		$text_cb     = array( $this, 'sanitize_text' );
		$textarea_cb = array( $this, 'sanitize_textarea' );
		$callbacks   = array(
			'wsq_textarea' => array( $this, 'sanitize_chat_ids' ),
			'wsq_checkbox' => array( $this, 'sanitize_checkbox' ),
			'wsq_url'      => array( $this, 'sanitize_url' ),
			'wsq_provider' => array( $this, 'sanitize_provider' ),
			'wsq_routes'   => array( $this, 'sanitize_category_routes' ),
		);

		// General / PDF.
		register_setting( $groups['general'], 'wsq_pdf_logo', array( 'sanitize_callback' => $callbacks['wsq_url'] ) );
		register_setting( $groups['general'], 'wsq_company_name', array( 'sanitize_callback' => $text_cb ) );
		register_setting( $groups['general'], 'wsq_national_id', array( 'sanitize_callback' => $text_cb ) );
		register_setting( $groups['general'], 'wsq_economic_code', array( 'sanitize_callback' => $text_cb ) );
		register_setting( $groups['general'], 'wsq_registration_no', array( 'sanitize_callback' => $text_cb ) );
		register_setting( $groups['general'], 'wsq_company_phone', array( 'sanitize_callback' => $text_cb ) );
		register_setting( $groups['general'], 'wsq_company_address', array( 'sanitize_callback' => $textarea_cb ) );
		register_setting( $groups['general'], 'wsq_show_digital_stamp', array( 'sanitize_callback' => $callbacks['wsq_checkbox'] ) );
		register_setting( $groups['general'], 'wsq_stamp_text', array( 'sanitize_callback' => $text_cb ) );
		register_setting( $groups['general'], 'wsq_pdf_header_note', array( 'sanitize_callback' => $text_cb ) );
		register_setting( $groups['general'], 'wsq_pdf_validity_days', array( 'sanitize_callback' => array( $this, 'sanitize_validity_days' ) ) );
		register_setting( $groups['general'], 'wsq_pdf_retention_days', array( 'sanitize_callback' => array( $this, 'sanitize_retention_days' ) ) );
		register_setting( $groups['general'], 'wsq_pdf_footer_text', array( 'sanitize_callback' => $textarea_cb ) );

		// Messengers / expert notifications.
		register_setting( $groups['messengers'], 'wsq_bale_token', array( 'sanitize_callback' => $text_cb ) );
		register_setting( $groups['messengers'], 'wsq_bale_default_chat_ids', array( 'sanitize_callback' => $callbacks['wsq_textarea'] ) );
		register_setting( $groups['messengers'], 'wsq_eitaa_token', array( 'sanitize_callback' => $text_cb ) );
		register_setting( $groups['messengers'], 'wsq_eitaa_default_chat_ids', array( 'sanitize_callback' => $callbacks['wsq_textarea'] ) );
		register_setting( $groups['messengers'], 'wsq_message_template', array( 'sanitize_callback' => $textarea_cb ) );

		// Buyer delivery.
		register_setting( $groups['buyer'], 'wsq_buyer_sms_enabled', array( 'sanitize_callback' => $callbacks['wsq_checkbox'] ) );
		register_setting( $groups['buyer'], 'wsq_buyer_sms_provider', array( 'sanitize_callback' => $callbacks['wsq_provider'] ) );
		register_setting( $groups['buyer'], 'wsq_buyer_sms_apikey', array( 'sanitize_callback' => $text_cb ) );
		register_setting( $groups['buyer'], 'wsq_buyer_sms_sender', array( 'sanitize_callback' => $text_cb ) );
		register_setting( $groups['buyer'], 'wsq_buyer_sms_pattern', array( 'sanitize_callback' => $text_cb ) );
		register_setting( $groups['buyer'], 'wsq_buyer_bale_token', array( 'sanitize_callback' => $text_cb ) );

		// Bot / inbound.
		register_setting( $groups['bot'], 'wsq_polling_enabled', array( 'sanitize_callback' => $callbacks['wsq_checkbox'] ) );
		register_setting( $groups['bot'], 'wsq_bale_admin_chat_ids', array( 'sanitize_callback' => $callbacks['wsq_textarea'] ) );
		register_setting( $groups['bot'], 'wsq_eitaa_admin_chat_ids', array( 'sanitize_callback' => $callbacks['wsq_textarea'] ) );
		register_setting( $groups['bot'], 'wsq_buyer_bot_enabled', array( 'sanitize_callback' => $callbacks['wsq_checkbox'] ) );
		register_setting( $groups['bot'], 'wsq_bot_welcome', array( 'sanitize_callback' => $textarea_cb ) );
		register_setting( $groups['bot'], 'wsq_bot_orders_header', array( 'sanitize_callback' => $textarea_cb ) );
		register_setting( $groups['bot'], 'wsq_bot_order_card', array( 'sanitize_callback' => $textarea_cb ) );

		// Routing and storefront display.
		register_setting( $groups['routing'], 'wsq_category_routes', array( 'sanitize_callback' => $callbacks['wsq_routes'] ) );
		register_setting( $groups['display'], 'wsq_button_text', array( 'sanitize_callback' => $text_cb ) );
		register_setting( $groups['display'], 'wsq_enable_archive_button', array( 'sanitize_callback' => $callbacks['wsq_checkbox'] ) );
		register_setting( $groups['display'], 'wsq_enable_floating_cart', array( 'sanitize_callback' => $callbacks['wsq_checkbox'] ) );
		register_setting( $groups['display'], 'wsq_hide_add_to_cart', array( 'sanitize_callback' => $callbacks['wsq_checkbox'] ) );
	}

	/**
	 * Sanitizers used by register_setting().
	 */
	public function sanitize_text( $value ) {
		if ( is_array( $value ) ) {
			return '';
		}
		return sanitize_text_field( strval( $value ) );
	}

	public function sanitize_textarea( $value ) {
		if ( is_array( $value ) ) {
			return '';
		}
		return sanitize_textarea_field( strval( $value ) );
	}

	public function sanitize_chat_ids( $value ) {
		if ( is_array( $value ) ) {
			return '';
		}
		$value = sanitize_textarea_field( strval( $value ) );
		$lines = preg_split( '/\r\n|\r|\n/', $value );
		$clean = array();
		foreach ( $lines as $line ) {
			$line = trim( $line );
			// Strip everything but digits and a leading minus (group ids).
			$line = preg_replace( '/[^0-9-]/', '', $line );
			if ( '' !== $line ) {
				$clean[] = $line;
			}
		}
		return implode( "\n", $clean );
	}

	public function sanitize_checkbox( $value ) {
		return ( 'yes' === $value ) ? 'yes' : 'no';
	}

	public function sanitize_url( $value ) {
		if ( is_array( $value ) ) {
			return '';
		}
		return esc_url_raw( strval( $value ) );
	}

	public function sanitize_provider( $value ) {
		$allowed = array( 'none', 'farazsms', 'ippanel', 'kavenegar', 'safir_bale' );
		return in_array( $value, $allowed, true ) ? $value : 'none';
	}

	public function sanitize_validity_days( $value ) {
		return $this->sanitize_day_count( $value, 'wsq_pdf_validity_days', 7, 365, __( 'مدت اعتبار پیش‌فاکتور', 'woo-smart-quote' ) );
	}

	public function sanitize_retention_days( $value ) {
		return $this->sanitize_day_count( $value, 'wsq_pdf_retention_days', 30, 3650, __( 'مدت نگهداری فایل PDF', 'woo-smart-quote' ) );
	}

	private function sanitize_day_count( $value, $option, $default, $max, $label ) {
		$days = absint( $value );
		if ( $days < 1 ) {
			return $default;
		}
		if ( $days > $max ) {
			add_settings_error(
				$option,
				$option . '_clamped',
				sprintf(
					/* translators: 1: field label, 2: maximum allowed days */
					__( '%1$s نمی‌تواند بیش از %2$s روز باشد؛ مقدار مجاز حداکثر ثبت شد.', 'woo-smart-quote' ),
					$label,
					number_format_i18n( $max )
				),
				'warning'
			);
			return $max;
		}
		return $days;
	}

	public function sanitize_category_routes( $value ) {
		$clean = array();
		if ( ! is_array( $value ) ) {
			return $clean;
		}

		foreach ( $value as $term_id => $route ) {
			$term_id = absint( $term_id );
			if ( ! $term_id || ! is_array( $route ) ) {
				continue;
			}
			if ( taxonomy_exists( 'product_cat' ) && ! term_exists( $term_id, 'product_cat' ) ) {
				add_settings_error(
					'wsq_category_routes',
					'wsq_category_routes_unknown_term',
					sprintf(
						/* translators: %d: product category term ID */
						__( 'دسته‌بندی با شناسه %d پیدا نشد و مسیر آن ذخیره نشد.', 'woo-smart-quote' ),
						$term_id
					),
					'warning'
				);
				continue;
			}

			$bale  = isset( $route['bale'] ) ? $route['bale'] : '';
			$eitaa = isset( $route['eitaa'] ) ? $route['eitaa'] : '';

			// Keep only digit, comma and minus sequences of chat id lists.
			$bale_ids  = preg_replace( '/[^0-9,\-,]/', '', sanitize_text_field( strval( $bale ) ) );
			$eitaa_ids = preg_replace( '/[^0-9,\-,]/', '', sanitize_text_field( strval( $eitaa ) ) );

			if ( '' === $bale_ids && '' === $eitaa_ids ) {
				continue;
			}
			$clean[ $term_id ] = array(
				'bale'  => $bale_ids,
				'eitaa' => $eitaa_ids,
			);
		}
		return $clean;
	}

	public function ajax_test_connection() {
		check_ajax_referer( 'wsq_admin_test_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'woo-smart-quote' ) ) );
		}

		$messenger = isset( $_POST['messenger'] ) ? sanitize_text_field( wp_unslash( $_POST['messenger'] ) ) : 'bale';
		$token     = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		$chat_id   = isset( $_POST['chat_id'] ) ? sanitize_text_field( wp_unslash( $_POST['chat_id'] ) ) : '';

		if ( empty( $token ) || empty( $chat_id ) ) {
			wp_send_json_error( array( 'message' => __( 'توکن و شناسه چت نباید خالی باشند.', 'woo-smart-quote' ) ) );
		}

		$result = WSQ_Queue::instance()->send_test_notification( $messenger, $token, $chat_id );

		if ( isset( $result['ok'] ) && true === $result['ok'] ) {
			wp_send_json_success( array( 'message' => __( 'پیام آزمایشی با موفقیت ارسال شد!', 'woo-smart-quote' ) ) );
		} else {
			$err = isset( $result['description'] ) ? $result['description'] : ( isset( $result['message'] ) ? $result['message'] : __( 'خطا در ارتباط با سرور.', 'woo-smart-quote' ) );
			wp_send_json_error( array( 'message' => $err ) );
		}
	}

	/* ------------------------------------------------------------------
	 * Bot & inbound tab
	 * ------------------------------------------------------------------ */

	/**
	 * Register or remove the Bale webhook.
	 */
	public function ajax_webhook_action() {
		check_ajax_referer( 'wsq_admin_test_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'woo-smart-quote' ) ) );
		}

		$action = isset( $_POST['action_type'] ) ? sanitize_text_field( wp_unslash( $_POST['action_type'] ) ) : 'set';

		if ( ! in_array( $action, array( 'set', 'delete' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'عملیات نامعتبر.', 'woo-smart-quote' ) ) );
		}

		$result  = WSQ_Updates::instance()->manage_webhook( $action );
		$success = ! empty( $result['ok'] );

		if ( 'set' === $action && $success ) {
			$message = __( 'وبهوک با موفقیت در بله ثبت شد.', 'woo-smart-quote' );
		} elseif ( 'delete' === $action && $success ) {
			$message = __( 'وبهوک حذف شد؛ پولینگ فعال می‌شود.', 'woo-smart-quote' );
		} else {
			$message = isset( $result['description'] ) ? $result['description'] : __( 'خطا در ارتباط با سرور بله.', 'woo-smart-quote' );
		}

		$payload = array( 'message' => $message, 'url' => WSQ_Updates::instance()->webhook_url() );

		if ( $success ) {
			wp_send_json_success( $payload );
		}
		wp_send_json_error( $payload );
	}

	/**
	 * Reply to a messenger conversation from the admin inbox.
	 */
	public function ajax_inbox_reply() {
		check_ajax_referer( 'wsq_admin_test_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'woo-smart-quote' ) ) );
		}

		$messenger = isset( $_POST['messenger'] ) ? sanitize_text_field( wp_unslash( $_POST['messenger'] ) ) : '';
		$chat_id   = isset( $_POST['chat_id'] ) ? sanitize_text_field( wp_unslash( $_POST['chat_id'] ) ) : '';
		$text      = isset( $_POST['reply_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reply_text'] ) ) : '';

		if ( ! in_array( $messenger, array( 'bale', 'eitaa' ), true ) || '' === $chat_id || '' === $text ) {
			wp_send_json_error( array( 'message' => __( 'پارامترهای پاسخ ناقص است.', 'woo-smart-quote' ) ) );
		}

		$response = WSQ_Updates::instance()->reply( $messenger, $chat_id, $text );

		if ( ! empty( $response['ok'] ) ) {
			// Replying means the team has read the conversation.
			WSQ_Updates::instance()->mark_read( array(), $messenger, $chat_id );
			wp_send_json_success( array( 'message' => __( 'پاسخ ارسال شد.', 'woo-smart-quote' ) ) );
		} else {
			$err = isset( $response['description'] ) ? $response['description'] : __( 'خطا در ارسال.', 'woo-smart-quote' );
			wp_send_json_error( array( 'message' => $err ) );
		}
	}

	/**
	 * Mark one or all inbox messages as read.
	 */
	public function ajax_inbox_read() {
		check_ajax_referer( 'wsq_admin_test_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'woo-smart-quote' ) ) );
		}

		$ids   = isset( $_POST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) : array();
		$all   = isset( $_POST['all'] ) && '1' === (string) $_POST['all'];
		$count = WSQ_Updates::instance()->mark_read( $all ? array() : $ids );

		wp_send_json_success( array(
			'message'  => $count > 0 ? __( 'پیام‌ها به‌عنوان خوانده‌شده علامت خوردند.', 'woo-smart-quote' ) : __( 'پیام خوانده‌نشده‌ای وجود ندارد.', 'woo-smart-quote' ),
			'count'    => $count,
			'unread'   => WSQ_Updates::instance()->unread_count(),
		) );
	}

	/**
	 * Render the bot & inbound tab body.
	 */
	/**
	 * Render clickable placeholder chips under a bot-template textarea.
	 *
	 * Clicking a chip inserts the token into the first focused textarea of
	 * the surrounding form (handled by admin.js).
	 *
	 * @param string $context welcome|orders|order_card.
	 */
	private function render_template_tokens( $context ) {
		// Accept a single context string or a list of them.
		$contexts = is_array( $context ) ? $context : array( $context );

		$groups = array(
			'welcome'    => array( '{name}', '{chat_id}' ),
			'orders'     => array( '{name}', '{count}', '{#items}', '{/#items}', '{order_number}', '{status_icon}', '{status}', '{total}', '{date}', '{items}' ),
			'order_card' => array( '{name}', '{order_number}', '{status_icon}', '{status}', '{total}', '{date}', '{items}', '{payment_section}', '{payment_url}' ),
		);

		$tokens = array();
		foreach ( $contexts as $ctx ) {
			if ( isset( $groups[ $ctx ] ) ) {
				$tokens = array_merge( $tokens, $groups[ $ctx ] );
			}
		}
		$tokens = array_values( array_unique( $tokens ) );
		if ( empty( $tokens ) ) {
			return;
		}

		echo '<div class="wsq-token-bar" role="group" aria-label="' . esc_attr__( 'توکن‌های قابل استفاده — برای درج در متن کلیک کنید', 'woo-smart-quote' ) . '">';
		foreach ( $tokens as $token ) {
			echo '<button type="button" class="wsq-token" data-token="' . esc_attr( $token ) . '" title="' . esc_attr__( 'کلیک کنید تا توکن در قالب درج شود', 'woo-smart-quote' ) . '">' . esc_html( $token ) . '</button>';
		}
		echo '</div>';
	}

	/**
	 * Link to the WooCommerce orders list filtered by one status.
	 *
	 * Covers both the legacy post table and HPOS, so the dashboard links
	 * always land on the list the store actually uses.
	 *
	 * @param string $status Status without the wc- prefix.
	 * @return string
	 */
	private function orders_list_url( $status ) {
		$hpos = false;
		if ( class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) ) {
			$hpos = wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled();
		}

		if ( $hpos ) {
			return admin_url( 'admin.php?page=wc-orders&status=' . rawurlencode( $status ) );
		}

		return admin_url( 'edit.php?post_type=shop_order&post_status=wc-' . rawurlencode( $status ) );
	}

	/**
	 * Percentage change pill for a KPI card.
	 *
	 * @param int|null $delta Percent change, or null without a baseline.
	 * @return string HTML.
	 */
	private function render_delta( $delta ) {
		if ( null === $delta ) {
			return '<span class="wsq-kpi-delta is-flat">' . esc_html__( 'بدون مبنای مقایسه', 'woo-smart-quote' ) . '</span>';
		}

		if ( 0 === (int) $delta ) {
			return '<span class="wsq-kpi-delta is-flat">۰٪ ' . esc_html__( 'بدون تغییر', 'woo-smart-quote' ) . '</span>';
		}

		$is_up  = $delta > 0;
		$arrow  = $is_up ? '▲' : '▼';
		$class  = $is_up ? 'is-up' : 'is-down';
		$amount = number_format_i18n( abs( (int) $delta ) );

		$delta_num = abs( (int) $delta );

		// Keep the number in the accessible name: aria-label replaces the text.
		$aria = $is_up
			/* translators: %s: percentage */
			? sprintf( __( 'افزایش %s درصد نسبت به دوره قبل', 'woo-smart-quote' ), $delta_num )
			/* translators: %s: percentage */
			: sprintf( __( 'کاهش %s درصد نسبت به دوره قبل', 'woo-smart-quote' ), $delta_num );

		return sprintf(
			'<span class="wsq-kpi-delta %1$s" aria-label="%2$s">%3$s %4$s٪</span>',
			esc_attr( $class ),
			esc_attr( $aria ),
			esc_html( $arrow ),
			esc_html( $amount )
		);
	}

	/**
	 * Render the quote analytics dashboard tab body.
	 */
	private function render_dashboard_tab() {
		$stats = WSQ_Stats::instance()->get_summary();

		$today     = $stats['today'];
		$week      = $stats['week'];
		$pending   = $stats['pending'];
		$conv      = $stats['conversion'];
		$statuses  = $stats['statuses'];
		$chart     = $stats['chart'];
		$chart_max = (int) $stats['chart_max'];

		$period_label = sprintf(
			/* translators: %s: number of days */
			__( '%s روز اخیر', 'woo-smart-quote' ),
			number_format_i18n( (int) $conv['days'] )
		);

		$status_rows = array(
			'quote-request' => array( __( 'در انتظار بررسی', 'woo-smart-quote' ), 'is-warn' ),
			'quote-sent'    => array( __( 'پیش‌فاکتور ارسال شد', 'woo-smart-quote' ), 'is-info' ),
			'processing'    => array( __( 'در حال پردازش', 'woo-smart-quote' ), 'is-info' ),
			'completed'     => array( __( 'تکمیل شده', 'woo-smart-quote' ), 'is-ok' ),
			'other'         => array( __( 'سایر وضعیت‌ها', 'woo-smart-quote' ), 'is-muted' ),
		);
		$status_total = max( 1, (int) $conv['total'] );

		// Screen-reader summary of the chart: the bars themselves are not
		// exposed (the chart is a single image to assistive tech).
		$chart_total = 0;
		$chart_peak  = null;
		foreach ( $chart as $day ) {
			$chart_total += (int) $day['total'];
			if ( null === $chart_peak || $day['total'] > $chart_peak['total'] ) {
				$chart_peak = $day;
			}
		}
		if ( $chart_peak && $chart_peak['total'] > 0 ) {
			$chart_summary = sprintf(
				/* translators: 1: total quotes, 2: date, 3: busiest day count */
				__( 'نمودار استعلام‌های ۱۴ روز اخیر: در مجموع %1$s استعلام، بیشترین روز %2$s با %3$s استعلام.', 'woo-smart-quote' ),
				number_format_i18n( $chart_total ),
				$chart_peak['jalali'],
				number_format_i18n( (int) $chart_peak['total'] )
			);
		} else {
			$chart_summary = __( 'نمودار استعلام‌های ۱۴ روز اخیر: در این بازه استعلامی ثبت نشده است.', 'woo-smart-quote' );
		}

		$rate       = (float) $conv['rate'];
		$rate_round = (int) round( $rate );
		?>

				<div class="wsq-dashboard">
					<?php if ( ! empty( $stats['truncated'] ) ) : ?>
						<div class="wsq-notice wsq-notice-warn">
							<span class="wsq-notice-icon"><?php echo WSQ_Icons::get( 'warning' ); ?></span>
							<div class="wsq-notice-body"><?php esc_html_e( 'تعداد سفارش‌های این بازه از سقف پویش داشبورد بیشتر است؛ اعداد بر اساس جدیدترین سفارش‌ها محاسبه شده‌اند.', 'woo-smart-quote' ); ?></div>
						</div>
					<?php endif; ?>

					<div class="wsq-kpi-grid">
						<div class="wsq-kpi is-today">
							<span class="wsq-kpi-icon"><?php echo WSQ_Icons::get( 'inbox' ); ?></span>
							<span class="wsq-kpi-label"><?php esc_html_e( 'استعلام‌های امروز', 'woo-smart-quote' ); ?></span>
							<span class="wsq-kpi-value"><?php echo esc_html( number_format_i18n( (int) $today['current'] ) ); ?></span>
							<span class="wsq-kpi-meta"><?php echo esc_html( sprintf( __( 'دیروز: %s', 'woo-smart-quote' ), number_format_i18n( (int) $today['previous'] ) ) ); ?></span>
							<?php echo wp_kses_post( $this->render_delta( $today['delta'] ) ); ?>
						</div>

						<div class="wsq-kpi is-week">
							<span class="wsq-kpi-icon"><?php echo WSQ_Icons::get( 'calendar' ); ?></span>
							<span class="wsq-kpi-label"><?php esc_html_e( 'استعلام‌های این هفته', 'woo-smart-quote' ); ?></span>
							<span class="wsq-kpi-value"><?php echo esc_html( number_format_i18n( (int) $week['current'] ) ); ?></span>
							<span class="wsq-kpi-meta"><?php echo esc_html( sprintf( __( 'هفته قبل: %s', 'woo-smart-quote' ), number_format_i18n( (int) $week['previous'] ) ) ); ?></span>
							<?php echo wp_kses_post( $this->render_delta( $week['delta'] ) ); ?>
						</div>

						<div class="wsq-kpi is-pending">
							<span class="wsq-kpi-icon"><?php echo WSQ_Icons::get( 'clock' ); ?></span>
							<span class="wsq-kpi-label"><?php esc_html_e( 'در انتظار بررسی (کل صف)', 'woo-smart-quote' ); ?></span>
							<span class="wsq-kpi-value"><?php echo esc_html( number_format_i18n( (int) $pending['total'] ) ); ?></span>
							<span class="wsq-kpi-meta">
								<?php
								if ( null !== $pending['oldest_days'] ) {
									/* translators: %s: number of days */
									echo '<span class="wsq-nowrap">' . esc_html( sprintf( __( 'قدیمی‌ترین: %s روز در صف', 'woo-smart-quote' ), number_format_i18n( (int) $pending['oldest_days'] ) ) ) . '</span>';
								} else {
									esc_html_e( 'صف بررسی خالی است', 'woo-smart-quote' );
								}
								?>
							</span>
							<a class="wsq-kpi-link" href="<?php echo esc_url( $this->orders_list_url( 'quote-request' ) ); ?>"><?php esc_html_e( 'مشاهده صف', 'woo-smart-quote' ); ?></a>
						</div>

						<div class="wsq-kpi is-rate">
							<span class="wsq-kpi-icon"><?php echo WSQ_Icons::get( 'trend' ); ?></span>
							<span class="wsq-kpi-label"><?php esc_html_e( 'نرخ تبدیل به سفارش', 'woo-smart-quote' ); ?></span>
							<span class="wsq-kpi-value"><?php echo esc_html( number_format_i18n( $rate_round ) ); ?><span class="wsq-kpi-unit">٪</span></span>
							<span class="wsq-kpi-meta">
								<?php
								echo esc_html(
									sprintf(
										/* translators: 1: converted orders, 2: total quotes, 3: period */
										__( '%1$s سفارش از %2$s استعلام — %3$s', 'woo-smart-quote' ),
										number_format_i18n( (int) $conv['converted'] ),
										number_format_i18n( (int) $conv['total'] ),
										$period_label
									)
								);
								?>
							</span>
							<span class="wsq-kpi-track" role="img" aria-label="<?php echo esc_attr( sprintf( __( 'نرخ تبدیل: %s درصد', 'woo-smart-quote' ), number_format_i18n( $rate_round ) ) ); ?>">
								<span style="width:<?php echo esc_attr( min( 100, max( 0, $rate ) ) ); ?>%;"></span>
							</span>
						</div>
					</div>

					<div class="wsq-section-card wsq-chart-card">
						<h3><?php esc_html_e( 'روند استعلام‌های ۱۴ روز اخیر', 'woo-smart-quote' ); ?></h3>
						<p class="description">
							<?php esc_html_e( 'هر ستون تعداد استعلام‌های یک روز است؛ بخش پررنگ آن‌هایی که به سفارش تبدیل شده‌اند.', 'woo-smart-quote' ); ?>
						</p>

						<div class="wsq-chart-legend">
							<span class="wsq-legend is-total"><?php esc_html_e( 'کل استعلام', 'woo-smart-quote' ); ?></span>
							<span class="wsq-legend is-converted"><?php esc_html_e( 'تبدیل به سفارش', 'woo-smart-quote' ); ?></span>
						</div>

						<div class="wsq-chart" role="group" aria-label="<?php echo esc_attr( $chart_summary ); ?>">
							<?php $prev_month = null; ?>
							<?php foreach ( $chart as $day ) : ?>
								<?php
								// Bars stop at 88% so the value label above the tallest one
								// still fits inside the track.
								$height = $day['total'] > 0 ? max( 6, round( ( $day['total'] / $chart_max ) * 88 ) ) : 0;
								$filled = $day['total'] > 0 ? min( 100, round( ( $day['converted'] / $day['total'] ) * 100 ) ) : 0;

								// Day number, prefixed with the month whenever a new Jalali
								// month starts inside the 14-day window.
								$day_label = number_format_i18n( (int) $day['day'] );
								if ( $prev_month !== (int) $day['month'] ) {
									$day_label = number_format_i18n( (int) $day['month'] ) . '/' . $day_label;
								}
								$prev_month = (int) $day['month'];
								$tip    = sprintf(
									/* translators: 1: date, 2: quote count, 3: converted count */
									__( '%1$s — %2$s استعلام، %3$s تبدیل به سفارش', 'woo-smart-quote' ),
									$day['jalali'],
									number_format_i18n( (int) $day['total'] ),
									number_format_i18n( (int) $day['converted'] )
								);
								?>
								<div class="wsq-chart-col <?php echo $day['is_today'] ? 'is-today' : ''; ?>" role="img" tabindex="0" aria-label="<?php echo esc_attr( $tip ); ?>">
									<div class="wsq-chart-track">
										<span class="wsq-chart-value" style="bottom:<?php echo esc_attr( $height ); ?>%;"><?php echo $day['total'] > 0 ? esc_html( number_format_i18n( (int) $day['total'] ) ) : ''; ?></span>
										<div class="wsq-chart-bar" style="height:<?php echo esc_attr( $height ); ?>%;">
											<span class="wsq-chart-fill" style="height:<?php echo esc_attr( $filled ); ?>%;"></span>
										</div>
									</div>
									<span class="wsq-chart-day"><?php echo esc_html( $day_label ); ?></span>
									<span class="wsq-chart-weekday"><?php echo esc_html( $day['weekday'] ); ?></span>
								</div>
							<?php endforeach; ?>
						</div>
					</div>

					<div class="wsq-section-card">
						<h3><?php esc_html_e( 'توزیع وضعیت استعلام‌ها', 'woo-smart-quote' ); ?></h3>
						<p class="description">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: period label */
									__( 'بر اساس %s — روی هر ردیف بزنید تا لیست سفارش‌ها باز شود.', 'woo-smart-quote' ),
									$period_label
								)
							);
							?>
						</p>

						<?php if ( (int) $conv['total'] < 1 ) : ?>
							<p class="description wsq-inbox-empty"><?php esc_html_e( 'در این بازه استعلامی ثبت نشده است.', 'woo-smart-quote' ); ?></p>
						<?php else : ?>
							<div class="wsq-dist-list">
								<?php foreach ( $status_rows as $status_key => $row ) : ?>
									<?php
									$count   = isset( $statuses[ $status_key ] ) ? (int) $statuses[ $status_key ] : 0;
									$percent = round( ( $count / $status_total ) * 100 );
									?>
									<a class="wsq-dist-row" href="<?php echo esc_url( $this->orders_list_url( 'other' === $status_key ? 'quote-sent' : $status_key ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: status name, 2: quote count, 3: percentage */ __( '%1$s: %2$s استعلام، %3$s درصد — مشاهده در لیست سفارش‌ها', 'woo-smart-quote' ), $row[0], number_format_i18n( $count ), number_format_i18n( $percent ) ) ); ?>">
										<span class="wsq-dist-name"><?php echo esc_html( $row[0] ); ?></span>
										<span class="wsq-dist-track <?php echo esc_attr( $row[1] ); ?>" aria-hidden="true">
											<span style="width:<?php echo esc_attr( $percent ); ?>%;"></span>
										</span>
										<span class="wsq-dist-count"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
									</a>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>

					<div class="wsq-section-card">
						<h3><?php esc_html_e( 'قدیمی‌ترین استعلام‌های در انتظار بررسی', 'woo-smart-quote' ); ?></h3>

						<?php if ( empty( $pending['orders'] ) ) : ?>
							<p class="description wsq-inbox-empty"><?php esc_html_e( 'هیچ استعلامی در انتظار بررسی نیست.', 'woo-smart-quote' ); ?></p>
						<?php else : ?>
							<div class="wsq-table-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'صف استعلام‌های در انتظار بررسی', 'woo-smart-quote' ); ?>">
							<table class="wp-list-table widefat fixed striped wsq-pending-table">
								<caption class="screen-reader-text"><?php esc_html_e( 'صف استعلام‌های در انتظار بررسی، به ترتیب قدیمی‌ترین', 'woo-smart-quote' ); ?></caption>
								<thead>
									<tr>
										<th scope="col"><?php esc_html_e( 'استعلام', 'woo-smart-quote' ); ?></th>
										<th scope="col"><?php esc_html_e( 'خریدار', 'woo-smart-quote' ); ?></th>
										<th scope="col"><?php esc_html_e( 'تلفن', 'woo-smart-quote' ); ?></th>
										<th scope="col"><?php esc_html_e( 'اقلام', 'woo-smart-quote' ); ?></th>
										<th scope="col"><?php esc_html_e( 'در انتظار', 'woo-smart-quote' ); ?></th>
										<th scope="col"><?php esc_html_e( 'اقدام', 'woo-smart-quote' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $pending['orders'] as $row ) : ?>
										<tr>
											<td><strong translate="no">#<?php echo esc_html( $row['number'] ); ?></strong></td>
											<td><?php echo esc_html( '' !== $row['buyer'] ? $row['buyer'] : '—' ); ?></td>
											<td dir="ltr" translate="no"><?php echo esc_html( '' !== $row['phone'] ? $row['phone'] : '—' ); ?></td>
											<td><?php echo esc_html( number_format_i18n( (int) $row['items'] ) ); ?></td>
											<td class="wsq-days">
												<?php
												if ( null === $row['days'] ) {
													echo '—';
												} elseif ( (int) $row['days'] < 1 ) {
													esc_html_e( 'امروز', 'woo-smart-quote' );
												} else {
													echo esc_html( sprintf( __( '%s روز', 'woo-smart-quote' ), number_format_i18n( (int) $row['days'] ) ) );
												}
												?>
											</td>
											<td>
												<a class="button button-small wsq-pending-action" href="<?php echo esc_url( $row['edit_url'] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: order number */ __( 'قیمت‌گذاری استعلام #%s', 'woo-smart-quote' ), $row['number'] ) ); ?>"><?php esc_html_e( 'قیمت‌گذاری', 'woo-smart-quote' ); ?></a>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
							</div>

							<?php if ( (int) $pending['total'] > count( $pending['orders'] ) ) : ?>
								<p class="description">
									<a href="<?php echo esc_url( $this->orders_list_url( 'quote-request' ) ); ?>">
										<?php
										echo esc_html(
											sprintf(
												/* translators: %s: number of quotes */
												__( 'مشاهده همه %s استعلام در انتظار', 'woo-smart-quote' ),
												number_format_i18n( (int) $pending['total'] )
											)
										);
										?>
									</a>
								</p>
							<?php endif; ?>
						<?php endif; ?>
					</div>
				</div>

				<?php
	}

	private function render_bot_settings() {
		$webhook_set = (string) get_option( 'wsq_bale_webhook_url', '' ) !== '';
		$webhook_url = WSQ_Updates::instance()->webhook_url();
		$poll_off    = (array) get_option( 'wsq_poll_off', array() );
		?>

					<div class="wsq-section-card">
						<h3><?php echo WSQ_Icons::get( 'refresh' ); ?><?php esc_html_e( 'دریافت پیام از بله و ایتا', 'woo-smart-quote' ); ?></h3>
						<p class="description">
							<?php esc_html_e( 'ربات فروشگاه می‌تواند پیام‌های خریداران را دریافت کند، با آن‌ها تعامل کند (استعلام، پیش‌فاکتور، لینک پرداخت) و پیام‌های پشتیبانی را به کارشناسان برساند.', 'woo-smart-quote' ); ?>
						</p>

						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><?php esc_html_e( 'وبهوک بله (Bale Webhook)', 'woo-smart-quote' ); ?></th>
								<td>
									<code class="wsq-webhook-url" id="wsq-webhook-url" dir="ltr" style="display:block;margin-bottom:8px;word-break:break-all;"><?php echo esc_html( $webhook_url ); ?></code>
									<div class="wsq-test-row">
										<button type="button" class="button <?php echo $webhook_set ? '' : 'button-primary'; ?> wsq-webhook-btn" data-action="set"><?php echo WSQ_Icons::get( 'bell' ); ?><?php esc_html_e( 'ثبت وبهوک در بله', 'woo-smart-quote' ); ?></button>
										<button type="button" class="button wsq-webhook-btn" data-action="delete"><?php echo WSQ_Icons::get( 'trash' ); ?><?php esc_html_e( 'حذف وبهوک', 'woo-smart-quote' ); ?></button>
										<span class="wsq-test-status is-idle" id="status-webhook" role="status" aria-live="polite"></span>
									</div>
									<p class="description" id="wsq_webhook_help"><?php esc_html_e( 'با ثبت وبهوک، پیام‌های بله بلافاصله دریافت می‌شود (بهتر از پولینگ). برای ایتا از پولینگ استفاده می‌شود.', 'woo-smart-quote' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wsq_polling_enabled_toggle"><?php esc_html_e( 'فعال‌سازی پولینگ (Polling)', 'woo-smart-quote' ); ?></label></th>
								<td>
									<input type="hidden" name="wsq_polling_enabled" value="no" />
									<label class="wsq-toggle">
										<input type="checkbox" id="wsq_polling_enabled_toggle" name="wsq_polling_enabled" value="yes" <?php checked( 'yes', get_option( 'wsq_polling_enabled', 'no' ) ); ?> aria-describedby="wsq_polling_help" />
										<span class="wsq-toggle-track"><span class="wsq-toggle-thumb"></span></span>
										<span class="wsq-toggle-label"><?php esc_html_e( 'هر دقیقه پیام‌های جدید از بله و ایتا بررسی شود.', 'woo-smart-quote' ); ?></span>
									</label>
									<p class="description" id="wsq_polling_help"><?php esc_html_e( 'اگر وبهوک بله ثبت شده باشد، پولینگ فقط برای ایتا به کار می‌رود.', 'woo-smart-quote' ); ?></p>
									<?php if ( ! empty( $poll_off['eitaa'] ) ) : ?>
										<p class="description" style="color:#b32d2e;"><?php esc_html_e( 'پنل ایتای شما', 'woo-smart-quote' ); ?> <code>getUpdates</code> <?php esc_html_e( 'را پشتیبانی نمی‌کند؛ پولینگ برای ایتا غیرفعال شده است. فقط ارسال فعال می‌ماند.', 'woo-smart-quote' ); ?></p>
									<?php endif; ?>
								</td>
							</tr>
						</table>
					</div>

					<div class="wsq-section-card">
						<h3><?php echo WSQ_Icons::get( 'user' ); ?><?php esc_html_e( 'چت‌های مدیریتی (Admin)', 'woo-smart-quote' ); ?></h3>
						<p class="description"><?php esc_html_e( 'شناسه چت‌هایی که پیام‌های خریداران به آن‌ها ارسال می‌شود و فرمان‌های مدیریتی (/pending, /order) را می‌پذیرند. هر شناسه در یک سطر.', 'woo-smart-quote' ); ?></p>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="wsq_bale_admin_chat_ids"><?php esc_html_e( 'چت‌های مدیر بله', 'woo-smart-quote' ); ?></label></th>
								<td>
									<textarea id="wsq_bale_admin_chat_ids" name="wsq_bale_admin_chat_ids" rows="3" class="regular-text code" aria-describedby="wsq_bale_admin_chat_ids_help"><?php echo esc_textarea( get_option( 'wsq_bale_admin_chat_ids' ) ); ?></textarea>
									<p class="description" id="wsq_bale_admin_chat_ids_help"><?php esc_html_e( 'برای گرفتن شناسه چت، در همان چت دستور /start را به ربات بفرستید و به لاگ‌ها مراجعه کنید.', 'woo-smart-quote' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wsq_eitaa_admin_chat_ids"><?php esc_html_e( 'چت‌های مدیر ایتا', 'woo-smart-quote' ); ?></label></th>
								<td>
									<textarea id="wsq_eitaa_admin_chat_ids" name="wsq_eitaa_admin_chat_ids" rows="3" class="regular-text code"><?php echo esc_textarea( get_option( 'wsq_eitaa_admin_chat_ids' ) ); ?></textarea>
								</td>
							</tr>
						</table>
					</div>

					<div class="wsq-section-card">
						<h3><?php echo WSQ_Icons::get( 'bot' ); ?><?php esc_html_e( 'ربات تعاملی خریدار', 'woo-smart-quote' ); ?></h3>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="wsq_buyer_bot_enabled_toggle"><?php esc_html_e( 'فعال‌سازی ربات خریدار', 'woo-smart-quote' ); ?></label></th>
								<td>
									<input type="hidden" name="wsq_buyer_bot_enabled" value="no" />
									<label class="wsq-toggle">
										<input type="checkbox" id="wsq_buyer_bot_enabled_toggle" name="wsq_buyer_bot_enabled" value="yes" <?php checked( 'yes', get_option( 'wsq_buyer_bot_enabled', 'no' ) ); ?> />
										<span class="wsq-toggle-track"><span class="wsq-toggle-thumb"></span></span>
										<span class="wsq-toggle-label"><?php esc_html_e( 'خریداران بتوانند با /start شماره‌شان را لینک کنند و استعلام/پیش‌فاکتور بگیرند.', 'woo-smart-quote' ); ?></span>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wsq_bot_welcome"><?php esc_html_e( 'متن خوش‌آمد (Welcome)', 'woo-smart-quote' ); ?></label></th>
								<td>
									<textarea id="wsq_bot_welcome" name="wsq_bot_welcome" rows="4" class="large-text" aria-describedby="wsq_bot_welcome_help"><?php echo esc_textarea( get_option( 'wsq_bot_welcome', '' ) ); ?></textarea>
									<?php $this->render_template_tokens( array( 'welcome' ) ); ?>
									<p class="description" id="wsq_bot_welcome_help"><?php esc_html_e( 'خالی بگذارید تا متن پیش‌فرض استفاده شود.', 'woo-smart-quote' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wsq_bot_orders_header"><?php esc_html_e( 'قالب فهرست استعلام‌ها (/orders)', 'woo-smart-quote' ); ?></label></th>
								<td>
									<textarea id="wsq_bot_orders_header" name="wsq_bot_orders_header" rows="5" class="large-text" aria-describedby="wsq_bot_orders_header_help"><?php echo esc_textarea( get_option( 'wsq_bot_orders_header', '' ) ); ?></textarea>
									<?php $this->render_template_tokens( array( 'orders' ) ); ?>
									<p class="description" id="wsq_bot_orders_header_help"><?php esc_html_e( 'متن بین', 'woo-smart-quote' ); ?> <code>{#items}</code> <?php esc_html_e( 'و', 'woo-smart-quote' ); ?> <code>{/#items}</code> <?php esc_html_e( 'برای هر استعلام یک‌بار تکرار می‌شود. خالی = قالب پیش‌فرض.', 'woo-smart-quote' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wsq_bot_order_card"><?php esc_html_e( 'کارت استعلام (وضعیت + پیش‌فاکتور)', 'woo-smart-quote' ); ?></label></th>
								<td>
									<textarea id="wsq_bot_order_card" name="wsq_bot_order_card" rows="6" class="large-text" aria-describedby="wsq_bot_order_card_help"><?php echo esc_textarea( get_option( 'wsq_bot_order_card', '' ) ); ?></textarea>
									<?php $this->render_template_tokens( array( 'order_card' ) ); ?>
									<p class="description" id="wsq_bot_order_card_help"><?php esc_html_e( 'خالی = کارت پیش‌فرض.', 'woo-smart-quote' ); ?></p>
								</td>
							</tr>
						</table>
					</div>

					<?php
	}

	private function render_bot_inbox() {
		global $wpdb;

		// Recent inbound conversations. The "unread" filter narrows the list
		// to messages the team has not opened yet.
		$messages_table = WSQ_Updates::table();
		$filter         = ( isset( $_GET['wsq_inbox'] ) && 'unread' === sanitize_text_field( wp_unslash( $_GET['wsq_inbox'] ) ) ) ? 'unread' : 'all';
		$stats          = WSQ_Updates::instance()->inbox_stats();

		if ( 'unread' === $filter ) {
			$recent = $wpdb->get_results( "SELECT * FROM {$messages_table} WHERE direction = 'in' AND status = 'received' ORDER BY id DESC LIMIT 30" );
		} else {
			$recent = $wpdb->get_results( "SELECT * FROM {$messages_table} WHERE direction = 'in' ORDER BY id DESC LIMIT 30" );
		}
		?>

				<div class="wsq-section-card">
					<h3><?php echo WSQ_Icons::get( 'inbox' ); ?><?php esc_html_e( 'صندوق ورودی پیام', 'woo-smart-quote' ); ?></h3>
					<div class="wsq-inbox-toolbar" role="group" aria-label="<?php esc_attr_e( 'فیلتر صندوق ورودی', 'woo-smart-quote' ); ?>">
						<a class="wsq-inbox-filter <?php echo 'all' === $filter ? 'is-active' : ''; ?>" data-filter="all" href="<?php echo esc_url( $this->admin_tab_url( 'bot' ) ); ?>"<?php echo 'all' === $filter ? ' aria-current="true"' : ''; ?>>
							<?php esc_html_e( 'همه', 'woo-smart-quote' ); ?>
							<span class="wsq-inbox-count" data-count="all"><?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?></span>
						</a>
						<a class="wsq-inbox-filter <?php echo 'unread' === $filter ? 'is-active' : ''; ?>" data-filter="unread" href="<?php echo esc_url( $this->admin_tab_url( 'bot', array( 'wsq_inbox' => 'unread' ) ) ); ?>"<?php echo 'unread' === $filter ? ' aria-current="true"' : ''; ?>>
							<?php esc_html_e( 'خوانده‌نشده', 'woo-smart-quote' ); ?>
							<span class="wsq-inbox-count" data-count="unread"><?php echo esc_html( number_format_i18n( $stats['unread'] ) ); ?></span>
						</a>
						<span class="wsq-inbox-toolbar-spacer"></span>
						<?php if ( $stats['unread'] > 0 ) : ?>
							<button type="button" class="button wsq-read-all-btn"><?php esc_html_e( 'خواندن همه', 'woo-smart-quote' ); ?></button>
						<?php endif; ?>
					</div>

					<?php if ( empty( $recent ) ) : ?>
						<p class="description wsq-inbox-empty" data-wsq-inbox-empty>
							<?php if ( 'unread' === $filter ) : ?>
								<?php esc_html_e( 'همه پیام‌ها خوانده شده‌اند. پیام جدیدی برای بررسی وجود ندارد.', 'woo-smart-quote' ); ?>
							<?php else : ?>
								<?php esc_html_e( 'هنوز پیامی دریافت نشده است. با ثبت وبهوک یا فعال‌سازی پولینگ، پیام‌های خریداران اینجا نمایش داده می‌شود.', 'woo-smart-quote' ); ?>
							<?php endif; ?>
						</p>
					<?php else : ?>
						<div class="wsq-inbox" data-inbox-filter="<?php echo esc_attr( $filter ); ?>">
							<?php foreach ( $recent as $msg ) : ?>
								<?php
								$is_unread    = ( 'received' === $msg->status );
								$text         = (string) $msg->text;
								$reply_id     = 'wsq-reply-' . (int) $msg->id;
								$reply_err_id = 'wsq-reply-error-' . (int) $msg->id;
								?>
								<div class="wsq-inbox-item <?php echo $is_unread ? 'is-unread' : ''; ?>" data-id="<?php echo esc_attr( $msg->id ); ?>">
									<div class="wsq-inbox-item-head">
										<span class="wsq-inbox-messenger is-<?php echo esc_attr( $msg->messenger ); ?>"><?php echo esc_html( 'bale' === $msg->messenger ? __( 'بله', 'woo-smart-quote' ) : __( 'ایتا', 'woo-smart-quote' ) ); ?></span>
										<code class="wsq-inbox-chat" dir="ltr"><?php echo esc_html( $msg->chat_id ); ?></code>
										<button type="button" class="wsq-copy-btn" data-copy="<?php echo esc_attr( $msg->chat_id ); ?>" title="<?php esc_attr_e( 'کپی شناسه چت', 'woo-smart-quote' ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: chat id */ __( 'کپی شناسه چت %s', 'woo-smart-quote' ), $msg->chat_id ) ); ?>"><?php esc_html_e( 'کپی', 'woo-smart-quote' ); ?></button>
										<?php if ( (int) $msg->order_id > 0 ) : ?>
											<?php
											$ord_obj = function_exists( 'wc_get_order' ) ? wc_get_order( (int) $msg->order_id ) : false;
											$ord_url = $ord_obj ? $ord_obj->get_edit_order_url() : admin_url( 'post.php?post=' . (int) $msg->order_id . '&action=edit' );
											?>
											<a class="wsq-inbox-order" href="<?php echo esc_url( $ord_url ); ?>"><?php esc_html_e( 'سفارش', 'woo-smart-quote' ); ?> #<?php echo esc_html( $msg->order_id ); ?></a>
										<?php endif; ?>
										<span class="wsq-inbox-time"><?php echo esc_html( wp_date( 'Y/m/d H:i', strtotime( $msg->created_at ) ) ); ?></span>
										<?php if ( $is_unread ) : ?>
											<button type="button" class="wsq-read-btn" data-id="<?php echo esc_attr( $msg->id ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: chat id */ __( 'علامت‌گذاری پیام گفتگو با شناسه %s به‌عنوان خوانده‌شده', 'woo-smart-quote' ), $msg->chat_id ) ); ?>"><?php esc_html_e( 'خوانده شد', 'woo-smart-quote' ); ?></button>
										<?php endif; ?>
									</div>
									<p class="wsq-inbox-text" title="<?php echo esc_attr( $text ); ?>"><?php echo esc_html( $text ); ?></p>
									<div class="wsq-test-row wsq-inbox-reply">
										<input type="text" id="<?php echo esc_attr( $reply_id ); ?>" class="small-text wsq-reply-input" data-chat="<?php echo esc_attr( $msg->chat_id ); ?>" data-messenger="<?php echo esc_attr( $msg->messenger ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: chat id */ __( 'پاسخ به گفتگو با شناسه %s', 'woo-smart-quote' ), $msg->chat_id ) ); ?>" aria-describedby="<?php echo esc_attr( $reply_err_id ); ?>" placeholder="<?php esc_attr_e( 'پاسخ خود را بنویسید…', 'woo-smart-quote' ); ?>" autocomplete="off" />
										<button type="button" class="button wsq-reply-btn"><?php esc_html_e( 'ارسال', 'woo-smart-quote' ); ?></button>
									</div>
									<p class="wsq-reply-error" id="<?php echo esc_attr( $reply_err_id ); ?>" role="alert" hidden></p>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>

					<?php
	}

	/**
	 * URL for a settings tab, optionally preserving extra query args.
	 *
	 * @param string $tab
	 * @param array  $extra
	 * @return string
	 */
	private function admin_tab_url( $tab, $extra = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'page' => 'woo-smart-quote',
					'tab'  => $tab,
				),
				$extra
			),
			admin_url( 'admin.php' )
		);
	}

	public function settings_page_html() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$wsq_tabs  = array(
			'dashboard'  => array(
				'label' => __( 'داشبورد استعلام‌ها', 'woo-smart-quote' ),
				'icon'  => 'chart',
			),
			'general'    => array(
				'label' => __( 'تنظیمات و مشخصات رسمی PDF', 'woo-smart-quote' ),
				'icon'  => 'file',
			),
			'messengers' => array(
				'label' => __( 'پیام‌رسان‌ها (اعلان کارشناسان)', 'woo-smart-quote' ),
				'icon'  => 'bolt',
			),
			'buyer'      => array(
				'label' => __( 'ارسال به خریدار (بله، ایتا، پیامک)', 'woo-smart-quote' ),
				'icon'  => 'phone',
			),
			'bot'        => array(
				'label' => __( 'ربات تعاملی و دریافت پیام', 'woo-smart-quote' ),
				'icon'  => 'bot',
			),
			'routing'    => array(
				'label' => __( 'مسیریابی کارشناسان (Routing)', 'woo-smart-quote' ),
				'icon'  => 'target',
			),
			'display'    => array(
				'label' => __( 'دکمه و ویجت شناور', 'woo-smart-quote' ),
				'icon'  => 'palette',
			),
			'app'        => array(
				'label' => __( 'اپلیکیشن همراه (Android)', 'woo-smart-quote' ),
				'icon'  => 'phone',
			),
		);
		$requested = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard';
		$active_tab = isset( $wsq_tabs[ $requested ] ) ? $requested : 'dashboard';

		$wsq_setup  = $this->get_setup_progress();
		$wsq_groups = $this->settings_groups();
		$wsq_group  = isset( $wsq_groups[ $active_tab ] ) ? $wsq_groups[ $active_tab ] : '';
		?>
		<div class="wrap wsq-admin-wrap">
			<span id="wsq-admin-live" class="screen-reader-text" role="status" aria-live="polite"></span>
			<div class="wsq-admin-header">
				<div class="wsq-brand-box">
					<div class="wsq-brand-id">
						<span class="wsq-brand-badge" aria-hidden="true">
							<img src="<?php echo esc_url( WSQ_PLUGIN_URL . 'assets/images/icon.svg' ); ?>" alt="" width="34" height="34" />
						</span>
						<div>
							<h1 class="wsq-brand-title"><?php esc_html_e( 'هوشمند استعلام قیمت ووکامرس', 'woo-smart-quote' ); ?></h1>
							<span class="wsq-brand-sub"><?php esc_html_e( 'اتصال بومی به بله و ایتا + صدور پیش‌فاکتور رسمی PDF', 'woo-smart-quote' ); ?></span>
						</div>
					</div>
					<div class="wsq-brand-meta">
						<span class="wsq-version-badge"><?php echo esc_html( sprintf( __( 'نسخه %s', 'woo-smart-quote' ), WSQ_VERSION ) ); ?></span>
					</div>
				</div>
				<div class="wsq-status-row" role="status" aria-live="polite">
					<span class="wsq-chip <?php echo $wsq_setup['bale_ok'] ? 'is-ok' : 'is-off'; ?>"><span class="wsq-dot" aria-hidden="true"></span><?php esc_html_e( 'بله', 'woo-smart-quote' ); ?>: <?php echo $wsq_setup['bale_ok'] ? esc_html__( 'متصل', 'woo-smart-quote' ) : esc_html__( 'تنظیم نشده', 'woo-smart-quote' ); ?></span>
					<span class="wsq-chip <?php echo $wsq_setup['eitaa_ok'] ? 'is-ok' : 'is-off'; ?>"><span class="wsq-dot" aria-hidden="true"></span><?php esc_html_e( 'ایتا', 'woo-smart-quote' ); ?>: <?php echo $wsq_setup['eitaa_ok'] ? esc_html__( 'متصل', 'woo-smart-quote' ) : esc_html__( 'تنظیم نشده', 'woo-smart-quote' ); ?></span>
					<span class="wsq-chip <?php echo $wsq_setup['buyer_on'] ? 'is-ok' : 'is-off'; ?>"><span class="wsq-dot" aria-hidden="true"></span><?php esc_html_e( 'ارسال خودکار به خریدار', 'woo-smart-quote' ); ?>: <?php echo $wsq_setup['buyer_on'] ? esc_html__( 'فعال', 'woo-smart-quote' ) : esc_html__( 'غیرفعال', 'woo-smart-quote' ); ?></span>
				</div>
				<div class="wsq-setup-progress">
					<div class="wsq-setup-progress-label"><span id="wsq-progress-label"><?php esc_html_e( 'پیشرفت راه‌اندازی', 'woo-smart-quote' ); ?></span><span><?php echo esc_html( sprintf( __( '%s٪ تکمیل شده', 'woo-smart-quote' ), number_format_i18n( $wsq_setup['percent'] ) ) ); ?></span></div>
					<div class="wsq-progress" role="progressbar" aria-labelledby="wsq-progress-label" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( $wsq_setup['percent'] ); ?>" aria-valuetext="<?php echo esc_attr( sprintf( __( '%s درصد تکمیل شده', 'woo-smart-quote' ), number_format_i18n( $wsq_setup['percent'] ) ) ); ?>"><span style="width:<?php echo esc_attr( $wsq_setup['percent'] ); ?>%;"></span></div>
				</div>
			</div>

			<div class="wsq-settings-notices" role="status" aria-live="polite" tabindex="-1">
				<?php settings_errors(); ?>
			</div>

			<nav class="wsq-tab-nav" aria-label="<?php esc_attr_e( 'بخش‌های تنظیمات', 'woo-smart-quote' ); ?>">
				<h2 class="nav-tab-wrapper">
					<?php foreach ( $wsq_tabs as $tab_key => $tab ) : ?>
						<a href="<?php echo esc_url( $this->admin_tab_url( $tab_key ) ); ?>" class="nav-tab <?php echo $tab_key === $active_tab ? 'nav-tab-active' : ''; ?>"<?php echo $tab_key === $active_tab ? ' aria-current="page"' : ''; ?>><span class="wsq-tab-icon" aria-hidden="true"><?php echo WSQ_Icons::get( $tab['icon'] ); ?></span><?php echo esc_html( $tab['label'] ); ?></a>
					<?php endforeach; ?>
				</h2>
			</nav>

			<?php if ( 'dashboard' === $active_tab ) : ?>
				<?php $this->render_dashboard_tab(); ?>

			<?php elseif ( 'app' === $active_tab ) : ?>
				<?php $this->render_app_tab(); ?>

			<?php elseif ( 'routing' !== $active_tab ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
				<?php settings_fields( $wsq_group ); ?>
				
				<?php if ( 'general' === $active_tab ) : ?>
					<div class="wsq-section-card">
						<h3><?php echo WSQ_Icons::get( 'file' ); ?><?php esc_html_e( 'مشخصات حقوقی و سربرگ پیش‌فاکتور رسمی', 'woo-smart-quote' ); ?></h3>
						<p class="description"><?php esc_html_e( 'این اطلاعات در سربرگ فایل PDF پیش‌فاکتور ارسالی برای خریدار درج می‌شوند.', 'woo-smart-quote' ); ?></p>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="wsq_pdf_logo"><?php esc_html_e( 'آدرس لوگوی فروشگاه (URL)', 'woo-smart-quote' ); ?></label></th>
								<td>
									<input type="text" id="wsq_pdf_logo" name="wsq_pdf_logo" value="<?php echo esc_attr( get_option( 'wsq_pdf_logo' ) ); ?>" class="regular-text" placeholder="https://site.com/logo.png" aria-describedby="wsq_pdf_logo_help" />
									<p class="description" id="wsq_pdf_logo_help"><?php esc_html_e( 'آدرس مستقیم تصویر لوگو جهت درج در بالای پیش‌فاکتور رسمی.', 'woo-smart-quote' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wsq_company_name"><?php esc_html_e( 'نام رسمی واحد تجاری / شرکت', 'woo-smart-quote' ); ?></label></th>
								<td>
									<input type="text" id="wsq_company_name" name="wsq_company_name" value="<?php echo esc_attr( get_option( 'wsq_company_name', get_bloginfo( 'name' ) ) ); ?>" class="regular-text" />
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wsq_national_id"><?php esc_html_e( 'شناسه ملی', 'woo-smart-quote' ); ?></label></th>
								<td>
									<input type="text" id="wsq_national_id" name="wsq_national_id" value="<?php echo esc_attr( get_option( 'wsq_national_id' ) ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'مثال: ۱۰۳۲۰۰۴۹۱۸۲', 'woo-smart-quote' ); ?>" />
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wsq_registration_no"><?php esc_html_e( 'شماره ثبت', 'woo-smart-quote' ); ?></label></th>
								<td>
									<input type="text" id="wsq_registration_no" name="wsq_registration_no" value="<?php echo esc_attr( get_option( 'wsq_registration_no' ) ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'مثال: ۵۴۳۲۱۰', 'woo-smart-quote' ); ?>" />
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wsq_economic_code"><?php esc_html_e( 'کد اقتصادی', 'woo-smart-quote' ); ?></label></th>
								<td>
									<input type="text" id="wsq_economic_code" name="wsq_economic_code" value="<?php echo esc_attr( get_option( 'wsq_economic_code' ) ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'مثال: ۴۱۱۳۷۶۹۵۸۲۱۴', 'woo-smart-quote' ); ?>" />
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wsq_company_phone"><?php esc_html_e( 'تلفن تماس شرکت', 'woo-smart-quote' ); ?></label></th>
								<td>
									<input type="text" id="wsq_company_phone" name="wsq_company_phone" value="<?php echo esc_attr( get_option( 'wsq_company_phone' ) ); ?>" class="regular-text" />
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wsq_company_address"><?php esc_html_e( 'نشانی رسمی کارخانه یا دفتر', 'woo-smart-quote' ); ?></label></th>
								<td>
									<textarea id="wsq_company_address" name="wsq_company_address" rows="2" class="large-text"><?php echo esc_textarea( get_option( 'wsq_company_address' ) ); ?></textarea>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wsq_show_digital_stamp_toggle"><?php esc_html_e( 'مهر دیجیتال رسمی', 'woo-smart-quote' ); ?></label></th>
								<td>
									<input type="hidden" name="wsq_show_digital_stamp" value="no" />
									<label class="wsq-toggle">
										<input type="checkbox" id="wsq_show_digital_stamp_toggle" name="wsq_show_digital_stamp" value="yes" <?php checked( 'yes', get_option( 'wsq_show_digital_stamp', 'yes' ) ); ?> aria-describedby="wsq_stamp_preview_help" />
										<span class="wsq-toggle-track"><span class="wsq-toggle-thumb"></span></span>
										<span class="wsq-toggle-label"><?php esc_html_e( 'درج مهر قرمز رنگ رسمی در انتهای پیش‌فاکتور', 'woo-smart-quote' ); ?></span>
									</label>
									<div class="wsq-stamp-preview-card">
										<div class="wsq-digital-seal-demo" aria-hidden="true">
											<span><?php echo esc_html( get_option( 'wsq_company_name', get_bloginfo( 'name' ) ) ); ?></span>
											<span style="font-size:8px;margin:2px 0;">★★★★★</span>
											<span id="wsq-stamp-text-preview"><?php echo esc_html( get_option( 'wsq_stamp_text', __( 'تأیید شد - استعلام قیمت رسمی', 'woo-smart-quote' ) ) ); ?></span>
										</div>
										<p class="description" id="wsq_stamp_preview_help" style="margin:0;"><?php esc_html_e( 'پیش‌نمایش نمادین مهر رسمی که به‌صورت وکتور با حاشیه دایره‌ای دوخط قرمز در انتهای سند PDF الصاق می‌شود.', 'woo-smart-quote' ); ?></p>
									</div>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wsq_stamp_text_input"><?php esc_html_e( 'متن داخل مهر دیجیتال', 'woo-smart-quote' ); ?></label></th>
								<td>
									<input type="text" id="wsq_stamp_text_input" name="wsq_stamp_text" value="<?php echo esc_attr( get_option( 'wsq_stamp_text', __( 'تأیید شد - استعلام قیمت رسمی', 'woo-smart-quote' ) ) ); ?>" class="regular-text" />
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wsq_pdf_header_note"><?php esc_html_e( 'شعار زیر لوگو (هدر PDF)', 'woo-smart-quote' ); ?></label></th>
								<td>
									<input type="text" id="wsq_pdf_header_note" name="wsq_pdf_header_note" value="<?php echo esc_attr( get_option( 'wsq_pdf_header_note', __( 'سیستم جامع استعلام و سفارشات سازمانی', 'woo-smart-quote' ) ) ); ?>" class="regular-text" aria-describedby="wsq_pdf_header_note_help" />
									<p class="description" id="wsq_pdf_header_note_help"><?php esc_html_e( 'متن کوچکی که در بالای پیش‌فاکتور زیر نام و لوگوی شرکت درج می‌شود.', 'woo-smart-quote' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wsq_pdf_validity_days"><?php esc_html_e( 'مدت اعتبار پیش‌فاکتور (روز کاری)', 'woo-smart-quote' ); ?></label></th>
								<td>
									<input type="number" id="wsq_pdf_validity_days" name="wsq_pdf_validity_days" value="<?php echo esc_attr( get_option( 'wsq_pdf_validity_days', 7 ) ); ?>" min="1" max="365" class="small-text" aria-describedby="wsq_pdf_validity_days_help" />
									<p class="description" id="wsq_pdf_validity_days_help"><?php esc_html_e( 'در بخش اطلاعات سند («مدت اعتبار») نمایش داده می‌شود؛ مقدار خالی یا صفر = ۷ روز.', 'woo-smart-quote' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wsq_pdf_retention_days"><?php esc_html_e( 'مدت نگهداری فایل PDF (روز)', 'woo-smart-quote' ); ?></label></th>
								<td>
									<input type="number" id="wsq_pdf_retention_days" name="wsq_pdf_retention_days" value="<?php echo esc_attr( get_option( 'wsq_pdf_retention_days', 30 ) ); ?>" min="1" max="3650" class="small-text" aria-describedby="wsq_pdf_retention_days_help" />
									<p class="description" id="wsq_pdf_retention_days_help"><?php esc_html_e( 'فایل‌های PDF قدیمی‌تر از این مدت به‌صورت خودکار حذف می‌شوند؛ مقدار خالی یا صفر = ۳۰ روز.', 'woo-smart-quote' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wsq_pdf_footer_text"><?php esc_html_e( 'متن دلخواه پانویس پیش‌فاکتور', 'woo-smart-quote' ); ?></label></th>
								<td>
									<textarea id="wsq_pdf_footer_text" name="wsq_pdf_footer_text" rows="2" class="large-text" placeholder="<?php esc_attr_e( 'اگر خالی بگذارید، جمله پیش‌فرض کد رهگیری نمایش داده می‌شود.', 'woo-smart-quote' ); ?>" aria-describedby="wsq_pdf_footer_text_help"><?php echo esc_textarea( get_option( 'wsq_pdf_footer_text' ) ); ?></textarea>
									<p class="description" id="wsq_pdf_footer_text_help"><?php esc_html_e( 'برای لینک‌دار کردن از تگ <a> یا شکست خط از Enter استفاده کنید.', 'woo-smart-quote' ); ?></p>
								</td>
							</tr>
						</table>
					</div>

				<?php elseif ( 'messengers' === $active_tab ) : ?>
					<div class="wsq-section-card">
						<h3>
							<span><?php echo WSQ_Icons::get( 'chat' ); ?> <?php esc_html_e( 'تنظیمات بازوی بله (Bale Bot API)', 'woo-smart-quote' ); ?></span>
							<span class="wsq-card-header-badge wsq-badge-bale">Bale API</span>
						</h3>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="wsq_bale_token"><?php esc_html_e( 'توکن بازوی بله', 'woo-smart-quote' ); ?></label></th>
								<td>
									<input type="text" id="wsq_bale_token" name="wsq_bale_token" value="<?php echo esc_attr( get_option( 'wsq_bale_token' ) ); ?>" class="regular-text code" placeholder="bot12345678:ABC…" autocomplete="off" spellcheck="false" dir="ltr" aria-describedby="wsq_bale_token_help" />
									<p class="description" id="wsq_bale_token_help"><?php esc_html_e( 'توکن دریافت شده از بازوی بات‌ساز بله (@botfather یا @balebot).', 'woo-smart-quote' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wsq_bale_default_chat_ids"><?php esc_html_e( 'شناسه‌های چت پیش‌فرض (Chat IDs)', 'woo-smart-quote' ); ?></label></th>
								<td>
									<textarea id="wsq_bale_default_chat_ids" name="wsq_bale_default_chat_ids" rows="3" class="regular-text code" aria-describedby="wsq_bale_chat_ids_help"><?php echo esc_textarea( get_option( 'wsq_bale_default_chat_ids' ) ); ?></textarea>
									<p class="description" id="wsq_bale_chat_ids_help"><?php esc_html_e( 'هر شناسه در یک سطر (مثال: 123456789). برای گروه‌ها معمولاً با علامت منفی شروع می‌شود.', 'woo-smart-quote' ); ?></p>
									<div class="wsq-test-row">
										<button type="button" class="button wsq-test-btn" data-messenger="bale"><?php echo WSQ_Icons::get( 'bolt' ); ?><?php esc_html_e( 'ارسال پیام تست به بله', 'woo-smart-quote' ); ?></button>
										<span class="wsq-test-status is-idle" id="status-bale" role="status" aria-live="polite"></span>
									</div>
								</td>
							</tr>
						</table>
					</div>

					<div class="wsq-section-card">
						<h3>
							<span><?php echo WSQ_Icons::get( 'send' ); ?> <?php esc_html_e( 'تنظیمات ربات رسمی ایتا (Eitaa Bot API)', 'woo-smart-quote' ); ?></span>
							<span class="wsq-card-header-badge wsq-badge-eitaa">Eitaa API</span>
						</h3>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="wsq_eitaa_token"><?php esc_html_e( 'توکن ربات ایتا', 'woo-smart-quote' ); ?></label></th>
								<td>
									<input type="text" id="wsq_eitaa_token" name="wsq_eitaa_token" value="<?php echo esc_attr( get_option( 'wsq_eitaa_token' ) ); ?>" class="regular-text code" placeholder="bot123456…" autocomplete="off" spellcheck="false" dir="ltr" aria-describedby="wsq_eitaa_token_help" />
									<p class="description" id="wsq_eitaa_token_help"><?php esc_html_e( 'توکن دریافتی از پنل ربات‌ساز ایتا یا ایتایار.', 'woo-smart-quote' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wsq_eitaa_default_chat_ids"><?php esc_html_e( 'شناسه‌های چت پیش‌فرض (Chat IDs)', 'woo-smart-quote' ); ?></label></th>
								<td>
									<textarea id="wsq_eitaa_default_chat_ids" name="wsq_eitaa_default_chat_ids" rows="3" class="regular-text code" aria-describedby="wsq_eitaa_chat_ids_help"><?php echo esc_textarea( get_option( 'wsq_eitaa_default_chat_ids' ) ); ?></textarea>
									<p class="description" id="wsq_eitaa_chat_ids_help"><?php esc_html_e( 'هر شناسه در یک سطر.', 'woo-smart-quote' ); ?></p>
									<div class="wsq-test-row">
										<button type="button" class="button wsq-test-btn" data-messenger="eitaa"><?php echo WSQ_Icons::get( 'bolt' ); ?><?php esc_html_e( 'ارسال پیام تست به ایتا', 'woo-smart-quote' ); ?></button>
										<span class="wsq-test-status is-idle" id="status-eitaa" role="status" aria-live="polite"></span>
									</div>
								</td>
							</tr>
						</table>
					</div>

					<div class="wsq-section-card">
						<h3><?php echo WSQ_Icons::get( 'chat' ); ?><?php esc_html_e( 'قالب پیام متنی استعلام', 'woo-smart-quote' ); ?></h3>
						<p class="wsq-section-lead"><?php esc_html_e( 'این متن به‌همراه پیش‌فاکتور PDF برای کارشناسان ارسال می‌شود. برای درج توکن در متن، روی آن کلیک کنید.', 'woo-smart-quote' ); ?></p>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="wsq_message_template"><?php esc_html_e( 'متن پیام ارسالی', 'woo-smart-quote' ); ?></label></th>
								<td>
									<textarea name="wsq_message_template" rows="8" class="large-text code" id="wsq_message_template"><?php echo esc_textarea( get_option( 'wsq_message_template', __( "📋 استعلام جدید #{{order_number}}\n👤 {{customer_name}}\n📞 {{customer_phone}}\n🛒 {{items_count}} محصول — جمع: {{total}}\n📅 تاریخ: {{date_fa}}\n📝 {{note}}", 'woo-smart-quote' ) ) ); ?></textarea>
									<div class="wsq-token-bar" role="group" aria-label="<?php esc_attr_e( 'توکن‌های قابل استفاده — برای درج در متن کلیک کنید', 'woo-smart-quote' ); ?>">
										<?php
										$wsq_tokens = array( '{{order_number}}', '{{customer_name}}', '{{customer_phone}}', '{{total}}', '{{items_count}}', '{{date}}', '{{date_fa}}', '{{note}}' );
										foreach ( $wsq_tokens as $wsq_token ) {
											?>
											<button type="button" class="wsq-token" data-token="<?php echo esc_attr( $wsq_token ); ?>" title="<?php esc_attr_e( 'کلیک کنید تا توکن در قالب درج شود', 'woo-smart-quote' ); ?>"><?php echo esc_html( $wsq_token ); ?></button>
											<?php
										}
										?>
									</div>
								</td>
							</tr>
						</table>
					</div>

				<?php elseif ( 'buyer' === $active_tab ) : ?>
					<div class="wsq-section-card">
						<h3><?php echo WSQ_Icons::get( 'phone' ); ?><?php esc_html_e( 'تنظیمات ارسال پیش‌فاکتور و لینک پرداخت برای خریدار', 'woo-smart-quote' ); ?></h3>
						<p class="description">
							<?php esc_html_e( 'هنگامی که کارشناس قیمت اقلام را وارد کرده و وضعیت سفارش به', 'woo-smart-quote' ); ?> <strong>«<?php esc_html_e( 'پیش‌فاکتور صادر شد', 'woo-smart-quote' ); ?>» (Quote Sent)</strong> <?php esc_html_e( 'تغییر می‌کند، پیش‌فاکتور با لینک پرداخت مستقیم برای شماره خریدار ارسال می‌شود.', 'woo-smart-quote' ); ?>
						</p>

						<div class="wsq-notice wsq-notice-info">
							<span class="wsq-notice-icon"><?php echo WSQ_Icons::get( 'bulb' ); ?></span>
							<div class="wsq-notice-body">
								<strong><?php esc_html_e( 'سازوکار هوشمند ارسال به شماره خریدار در ایران:', 'woo-smart-quote' ); ?></strong>
								<ul>
									<li><?php esc_html_e( 'برداشت مستقیم شماره: شماره موبایل خریدار بدون نیاز به ثبت‌نام، از فرم استعلام قیمت استخراج و در سفارش ذخیره می‌شود.', 'woo-smart-quote' ); ?></li>
									<li><?php esc_html_e( 'ارسال به بله و ایتا با شماره موبایل: با فعال‌سازی وب‌سرویس فراز اس‌ام‌اس (IPPanel)، پیام‌ها بر اساس شماره موبایل خریدار، علاوه بر پیامک، در پیام‌رسان‌های داخلی (بله و ایتا) نیز تحویل داده می‌شوند.', 'woo-smart-quote' ); ?></li>
									<li><?php esc_html_e( 'سرویس سفیر بله: اتصال مستقیم به وب‌سرویس رسمی بازوی بله جهت ارسال به شماره موبایل کاربر.', 'woo-smart-quote' ); ?></li>
									<li><?php esc_html_e( 'دکمه‌های اقدام سریع در سفارش: علاوه بر ارسال خودکار، در صفحه ویرایش سفارش ووکامرس، دکمه‌های ۱-کلیکی برای باز کردن چت بله، ایتا و واتساپ خریدار با متن آماده تعبیه شده است.', 'woo-smart-quote' ); ?></li>
								</ul>
							</div>
						</div>

						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="wsq_buyer_sms_enabled_toggle"><?php esc_html_e( 'فعال‌سازی ارسال خودکار', 'woo-smart-quote' ); ?></label></th>
								<td>
									<input type="hidden" name="wsq_buyer_sms_enabled" value="no" />
									<label class="wsq-toggle">
										<input type="checkbox" id="wsq_buyer_sms_enabled_toggle" name="wsq_buyer_sms_enabled" value="yes" <?php checked( 'yes', get_option( 'wsq_buyer_sms_enabled', 'no' ) ); ?> />
										<span class="wsq-toggle-track"><span class="wsq-toggle-thumb"></span></span>
										<span class="wsq-toggle-label"><?php esc_html_e( 'با تغییر وضعیت سفارش به «پیش‌فاکتور صادر شد»، پیام خودکار برای شماره خریدار ارسال شود.', 'woo-smart-quote' ); ?></span>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wsq_buyer_sms_provider"><?php esc_html_e( 'سامانه وب‌سرویس خریدار', 'woo-smart-quote' ); ?></label></th>
								<td>
									<select name="wsq_buyer_sms_provider" id="wsq_buyer_sms_provider" class="wsq-select-wide" aria-describedby="wsq_buyer_sms_provider_help">
										<option value="none" <?php selected( 'none', get_option( 'wsq_buyer_sms_provider', 'none' ) ); ?>><?php esc_html_e( 'غیرفعال (فقط ایمیل و دکمه‌های دستی ادمین)', 'woo-smart-quote' ); ?></option>
										<option value="farazsms" <?php selected( 'farazsms', get_option( 'wsq_buyer_sms_provider', 'none' ) ); ?>><?php esc_html_e( 'فراز اس‌ام‌اس / IPPanel (با ارسال هوشمند به بله و ایتا بر اساس شماره)', 'woo-smart-quote' ); ?></option>
										<option value="ippanel" <?php selected( 'ippanel', get_option( 'wsq_buyer_sms_provider', 'none' ) ); ?>><?php esc_html_e( 'IPPanel (ارسال مستقیم پیامک خدماتی)', 'woo-smart-quote' ); ?></option>
										<option value="kavenegar" <?php selected( 'kavenegar', get_option( 'wsq_buyer_sms_provider', 'none' ) ); ?>><?php esc_html_e( 'کاوه‌نگار (Kavenegar Verify Lookup)', 'woo-smart-quote' ); ?></option>
										<option value="safir_bale" <?php selected( 'safir_bale', get_option( 'wsq_buyer_sms_provider', 'none' ) ); ?>><?php esc_html_e( 'سفیر بله (وب‌سرویس سازمانی بله برای شماره‌های همراه)', 'woo-smart-quote' ); ?></option>
									</select>
									<p class="description" id="wsq_buyer_sms_provider_help"><?php esc_html_e( 'سامانه مورد استفاده جهت پیامک خدماتی سریع و تحویل در پیام‌رسان‌ها.', 'woo-smart-quote' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wsq_buyer_sms_apikey"><?php esc_html_e( 'کلید API / توکن دسترسی', 'woo-smart-quote' ); ?></label></th>
								<td>
									<input type="text" id="wsq_buyer_sms_apikey" name="wsq_buyer_sms_apikey" value="<?php echo esc_attr( get_option( 'wsq_buyer_sms_apikey', '' ) ); ?>" class="regular-text code" dir="ltr" autocomplete="off" spellcheck="false" aria-describedby="wsq_buyer_sms_apikey_help" />
									<p class="description" id="wsq_buyer_sms_apikey_help"><?php esc_html_e( 'API Key پنل پیامک (فراز / IPPanel / کاوه‌نگار).', 'woo-smart-quote' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wsq_buyer_bale_token"><?php esc_html_e( 'توکن بله برای اطلاع‌رسانی به خریدار', 'woo-smart-quote' ); ?></label></th>
								<td>
									<input type="text" id="wsq_buyer_bale_token" name="wsq_buyer_bale_token" value="<?php echo esc_attr( get_option( 'wsq_buyer_bale_token', '' ) ); ?>" class="regular-text code" dir="ltr" autocomplete="off" spellcheck="false" aria-describedby="wsq_buyer_bale_token_help" />
									<p class="description" id="wsq_buyer_bale_token_help"><?php esc_html_e( 'در صورت انتخاب «سفیر بله» استفاده می‌شود؛ در غیر این صورت توکن اصلی بله به کار می‌رود.', 'woo-smart-quote' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wsq_buyer_sms_pattern"><?php esc_html_e( 'کد پترن / قالب تایید شده (Pattern)', 'woo-smart-quote' ); ?></label></th>
								<td>
									<input type="text" id="wsq_buyer_sms_pattern" name="wsq_buyer_sms_pattern" value="<?php echo esc_attr( get_option( 'wsq_buyer_sms_pattern', '' ) ); ?>" class="regular-text code" dir="ltr" placeholder="<?php esc_attr_e( 'مثال: wsq-proposal', 'woo-smart-quote' ); ?>" autocomplete="off" spellcheck="false" aria-describedby="wsq_buyer_sms_pattern_help" />
									<p class="description" id="wsq_buyer_sms_pattern_help">
										<?php
										printf(
											/* translators: 1: name placeholder, 2: order placeholder, 3: price placeholder, 4: link placeholder */
											esc_html__( 'متغیرهای ارسالی به پترن: نام خریدار (%1$s)، شماره استعلام (%2$s)، مبلغ کل (%3$s)، لینک پرداخت مستقیم (%4$s).', 'woo-smart-quote' ),
											'<code>name</code>',
											'<code>order</code>',
											'<code>price</code>',
											'<code>link</code>'
										);
										?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wsq_buyer_sms_sender"><?php esc_html_e( 'شماره خط ارسال‌کننده (Sender)', 'woo-smart-quote' ); ?></label></th>
								<td>
									<input type="text" id="wsq_buyer_sms_sender" name="wsq_buyer_sms_sender" value="<?php echo esc_attr( get_option( 'wsq_buyer_sms_sender', '+983000505' ) ); ?>" class="regular-text code" dir="ltr" />
								</td>
							</tr>
						</table>
					</div>

				<?php elseif ( 'bot' === $active_tab ) : ?>
					<?php $this->render_bot_settings(); ?>

				<?php elseif ( 'display' === $active_tab ) : ?>
					<div class="wsq-display-preview-wrap">
						<div class="wsq-section-card" style="margin-bottom:0;">
							<h3><?php echo WSQ_Icons::get( 'palette' ); ?><?php esc_html_e( 'شخصی‌سازی دکمه استعلام و ویجت شناور', 'woo-smart-quote' ); ?></h3>
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><label for="wsq_button_text_input"><?php esc_html_e( 'متن دکمه استعلام', 'woo-smart-quote' ); ?></label></th>
									<td>
										<input type="text" id="wsq_button_text_input" name="wsq_button_text" value="<?php echo esc_attr( get_option( 'wsq_button_text', __( 'افزودن به لیست استعلام قیمت', 'woo-smart-quote' ) ) ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'افزودن به لیست استعلام قیمت', 'woo-smart-quote' ); ?>" aria-describedby="wsq_button_text_help" />
										<p class="description" id="wsq_button_text_help"><?php esc_html_e( 'متنی که روی دکمه استعلام در صفحات محصول نمایش داده می‌شود.', 'woo-smart-quote' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="wsq_enable_archive_button_toggle"><?php esc_html_e( 'نمایش در آرشیو فروشگاه', 'woo-smart-quote' ); ?></label></th>
									<td>
										<input type="hidden" name="wsq_enable_archive_button" value="no" />
										<label class="wsq-toggle">
											<input type="checkbox" id="wsq_enable_archive_button_toggle" name="wsq_enable_archive_button" value="yes" <?php checked( 'yes', get_option( 'wsq_enable_archive_button', 'yes' ) ); ?> />
											<span class="wsq-toggle-track"><span class="wsq-toggle-thumb"></span></span>
											<span class="wsq-toggle-label"><?php esc_html_e( 'نمایش دکمه استعلام در صفحات دسته‌بندی و لیست محصولات (علاوه بر صفحه تکی محصول)', 'woo-smart-quote' ); ?></span>
										</label>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="wsq_enable_floating_cart_toggle"><?php esc_html_e( 'ویجت شناور سبد استعلام', 'woo-smart-quote' ); ?></label></th>
									<td>
										<input type="hidden" name="wsq_enable_floating_cart" value="no" />
										<label class="wsq-toggle">
											<input type="checkbox" id="wsq_enable_floating_cart_toggle" name="wsq_enable_floating_cart" value="yes" <?php checked( 'yes', get_option( 'wsq_enable_floating_cart', 'yes' ) ); ?> />
											<span class="wsq-toggle-track"><span class="wsq-toggle-thumb"></span></span>
											<span class="wsq-toggle-label"><?php esc_html_e( 'نمایش نشانگر شناور سبد استعلام در گوشه تصویر همراه با شمارنده اقلام', 'woo-smart-quote' ); ?></span>
										</label>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="wsq_hide_add_to_cart_toggle"><?php esc_html_e( 'محصولات بدون قیمت', 'woo-smart-quote' ); ?></label></th>
									<td>
										<input type="hidden" name="wsq_hide_add_to_cart" value="no" />
										<label class="wsq-toggle">
											<input type="checkbox" id="wsq_hide_add_to_cart_toggle" name="wsq_hide_add_to_cart" value="yes" <?php checked( 'yes', get_option( 'wsq_hide_add_to_cart', 'no' ) ); ?> />
											<span class="wsq-toggle-track"><span class="wsq-toggle-thumb"></span></span>
											<span class="wsq-toggle-label"><?php esc_html_e( 'پنهان کردن خودکار دکمه «افزودن به سبد خرید» برای محصولاتی که قیمت صفر دارند یا قیمت وارد نشده است', 'woo-smart-quote' ); ?></span>
										</label>
									</td>
								</tr>
							</table>
						</div>

						<div class="wsq-live-preview-card" aria-label="<?php esc_attr_e( 'پیش‌نمایش زنده المان‌ها در فروشگاه', 'woo-smart-quote' ); ?>">
							<div class="wsq-preview-title">
								<span><?php echo WSQ_Icons::get( 'sparkles' ); ?> <?php esc_html_e( 'پیش‌نمایش زنده در فروشگاه', 'woo-smart-quote' ); ?></span>
								<span class="wsq-version-badge" style="background:#eef2ff;color:#4f46e5;border-color:#c7d2fe;padding:2px 8px;font-size:10.5px;">Live Mockup</span>
							</div>
							<div class="wsq-preview-screen">
								<div class="wsq-preview-product">
									<div class="wsq-preview-img" aria-hidden="true">
										<span aria-hidden="true"><?php echo WSQ_Icons::get( 'bag' ); ?></span>
									</div>
									<div class="wsq-preview-info">
										<h4><?php esc_html_e( 'کالای نمونه برای استعلام', 'woo-smart-quote' ); ?></h4>
										<span class="price"><?php esc_html_e( 'قیمت: تماس بگیرید / استعلام', 'woo-smart-quote' ); ?></span>
									</div>
								</div>
								<div class="wsq-preview-action">
									<div class="wsq-preview-btn-mock" id="wsq-preview-btn-mock">
										<span class="wsq-btn-icon" aria-hidden="true"><?php echo WSQ_Icons::get( 'clipboard' ); ?></span>
										<span id="wsq-preview-btn-text"><?php echo esc_html( get_option( 'wsq_button_text', __( 'افزودن به لیست استعلام قیمت', 'woo-smart-quote' ) ) ); ?></span>
									</div>
								</div>
								<div class="wsq-preview-floating-demo">
									<span style="font-size:11.5px;color:#64748b;font-weight:700;"><?php esc_html_e( 'ویجت شناور گوشه صفحه:', 'woo-smart-quote' ); ?></span>
									<div class="wsq-preview-floating-pill">
										<span aria-hidden="true"><?php echo WSQ_Icons::get( 'clipboard' ); ?></span>
										<span><?php esc_html_e( 'سبد استعلام:', 'woo-smart-quote' ); ?></span>
										<span class="wsq-preview-floating-badge">3</span>
									</div>
								</div>
							</div>
						</div>
					</div>
				<?php endif; ?>
				
				<div class="wsq-savebar">
					<span class="wsq-savebar-hint"><?php esc_html_e( 'تغییرات این برگه ذخیره می‌شود. برای ذخیره تنظیمات سایر برگه‌ها، همان برگه را باز کنید.', 'woo-smart-quote' ); ?></span>
					<span class="wsq-savebar-dirty-pill" role="status" aria-live="polite"><span class="wsq-dot" aria-hidden="true"></span> <?php esc_html_e( 'تغییرات ذخیره‌نشده دارید', 'woo-smart-quote' ); ?></span>
					<?php submit_button( __( 'ذخیره تغییرات این برگه', 'woo-smart-quote' ), 'primary large', 'submit', false ); ?>
				</div>
			</form>

			<?php if ( 'bot' === $active_tab ) : ?>
				<?php $this->render_bot_inbox(); ?>
			<?php endif; ?>

			<?php else : ?>
			<!-- Routing Tab -->
			<div class="wsq-section-card">
				<h3><?php echo WSQ_Icons::get( 'target' ); ?><?php esc_html_e( 'ماتریس مسیریابی هوشمند کارشناسان (Category Routing)', 'woo-smart-quote' ); ?></h3>
				<p>
					<?php esc_html_e( 'با استفاده از این جدول، تعیین کنید هر دسته‌بندی محصول به کدام کارشناس یا گروه در بله یا ایتا ارسال شود. در صورت خالی ماندن، استعلام به شناسه‌های پیش‌فرض ارسال خواهد شد.', 'woo-smart-quote' ); ?>
				</p>
				
				<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
					<?php settings_fields( 'wsq_routing_group' ); ?>
					
					<div class="wsq-cat-search-bar" role="search">
						<label for="wsq-cat-search" class="screen-reader-text"><?php esc_html_e( 'جستجوی دسته‌بندی محصول', 'woo-smart-quote' ); ?></label>
						<input type="text" id="wsq-cat-search" class="wsq-cat-search-input" placeholder="<?php esc_attr_e( 'جستجوی دسته‌بندی محصول…', 'woo-smart-quote' ); ?>" autocomplete="off" aria-controls="wsq-routes-table" />
						<button type="button" id="wsq-cat-search-clear" class="wsq-cat-search-clear" style="display:none;" aria-label="<?php esc_attr_e( 'پاک کردن جستجو', 'woo-smart-quote' ); ?>">&times;</button>
						<span class="wsq-cat-search-icon" aria-hidden="true"><?php echo WSQ_Icons::get( 'search' ); ?></span>
						<span id="wsq-cat-search-live" class="screen-reader-text" aria-live="polite"></span>
					</div>

					<div class="wsq-routes-table" id="wsq-routes-table" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'جدول مسیریابی دسته‌بندی‌ها', 'woo-smart-quote' ); ?>">
						<table class="wp-list-table widefat fixed striped">
							<caption class="screen-reader-text"><?php esc_html_e( 'تعیین شناسه‌های چت بله و ایتا برای هر دسته‌بندی محصول', 'woo-smart-quote' ); ?></caption>
							<thead>
								<tr>
									<th class="wsq-col-cat" scope="col"><?php esc_html_e( 'دسته‌بندی محصول', 'woo-smart-quote' ); ?></th>
									<th class="wsq-col-ids" scope="col"><?php esc_html_e( 'شناسه‌های چت بله (با کاما جدا کنید)', 'woo-smart-quote' ); ?></th>
									<th class="wsq-col-ids" scope="col"><?php esc_html_e( 'شناسه‌های چت ایتا (با کاما جدا کنید)', 'woo-smart-quote' ); ?></th>
								</tr>
							</thead>
						<tbody>
							<?php
							$categories = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
							$routes     = get_option( 'wsq_category_routes', array() );

							if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
								foreach ( $categories as $category ) {
									$bale_ids  = isset( $routes[ $category->term_id ]['bale'] ) ? $routes[ $category->term_id ]['bale'] : '';
									$eitaa_ids = isset( $routes[ $category->term_id ]['eitaa'] ) ? $routes[ $category->term_id ]['eitaa'] : '';
									?>
									<tr>
										<td>
											<strong><?php echo esc_html( $category->name ); ?></strong>
											<div class="wsq-cat-id">(<?php esc_html_e( 'شناسه دسته:', 'woo-smart-quote' ); ?> <?php echo esc_html( $category->term_id ); ?>)</div>
										</td>
										<td>
											<label for="wsq-route-bale-<?php echo esc_attr( $category->term_id ); ?>" class="screen-reader-text"><?php echo esc_html( sprintf( /* translators: %s: product category name */ __( 'شناسه‌های چت بله برای دسته‌بندی %s', 'woo-smart-quote' ), $category->name ) ); ?></label>
											<input type="text" id="wsq-route-bale-<?php echo esc_attr( $category->term_id ); ?>" name="wsq_category_routes[<?php echo esc_attr( $category->term_id ); ?>][bale]" value="<?php echo esc_attr( $bale_ids ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'مثال: 987654321,12345678', 'woo-smart-quote' ); ?>" dir="ltr">
										</td>
										<td>
											<label for="wsq-route-eitaa-<?php echo esc_attr( $category->term_id ); ?>" class="screen-reader-text"><?php echo esc_html( sprintf( /* translators: %s: product category name */ __( 'شناسه‌های چت ایتا برای دسته‌بندی %s', 'woo-smart-quote' ), $category->name ) ); ?></label>
											<input type="text" id="wsq-route-eitaa-<?php echo esc_attr( $category->term_id ); ?>" name="wsq_category_routes[<?php echo esc_attr( $category->term_id ); ?>][eitaa]" value="<?php echo esc_attr( $eitaa_ids ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'مثال: 55443322', 'woo-smart-quote' ); ?>" dir="ltr">
										</td>
									</tr>
									<?php
								}
							} else {
								echo '<tr><td colspan="3">' . esc_html__( 'هیچ دسته‌بندی محصولی در ووکامرس یافت نشد.', 'woo-smart-quote' ) . '</td></tr>';
							}
							?>
						</tbody>
					</table>
					</div>
					
					<div class="wsq-savebar">
						<span class="wsq-savebar-hint"><?php esc_html_e( 'قوانین مسیریابی برای کارشناسان و گروه‌های بله و ایتا تنظیم می‌شود.', 'woo-smart-quote' ); ?></span>
						<span class="wsq-savebar-dirty-pill" role="status" aria-live="polite"><span class="wsq-dot" aria-hidden="true"></span> <?php esc_html_e( 'تغییرات ذخیره‌نشده دارید', 'woo-smart-quote' ); ?></span>
						<button type="submit" class="button button-primary button-large"><?php esc_html_e( 'ذخیره قوانین مسیریابی کارشناسان', 'woo-smart-quote' ); ?></button>
					</div>
				</form>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the mobile companion app pairing and management tab.
	 */
	private function render_app_tab() {
		$pairing = WSQ_REST_API::get_or_create_pairing_session();
		$devices = WSQ_REST_API::get_paired_devices();
		$pdf_gen = WSQ_PDF_Generator::instance();
		?>
		<div class="wsq-app-tab">
			<div class="wsq-notice wsq-notice-info">
				<span class="wsq-notice-icon"><?php echo WSQ_Icons::get( 'phone' ); ?></span>
				<div class="wsq-notice-body">
					<strong><?php esc_html_e( 'اپلیکیشن اختصاصی همراه مدیران فروش (WooCommerce Smart Quote Companion)', 'woo-smart-quote' ); ?></strong>
					<p><?php esc_html_e( 'با استفاده از اپلیکیشن اندروید، بدون نیاز به ورود به پیشخوان وردپرس، از استعلام‌های جدید با اعلان صوتی و فوری باخبر شوید، قیمت‌های اختصاصی اقلام را تعیین کنید و پیش‌فاکتور رسمی PDF را با یک لمس برای مشتری در بله، ایتا یا پیامک ارسال نمایید.', 'woo-smart-quote' ); ?></p>
				</div>
			</div>

			<div style="display:grid; grid-template-columns: 360px 1fr; gap:20px; margin-bottom:20px;">
				<!-- QR Code Pairing Card -->
				<div class="wsq-section-card" style="text-align:center; padding:24px;">
					<h3 style="margin-top:0;"><?php esc_html_e( 'اسکن بارکد اتصال سریع (QR Code)', 'woo-smart-quote' ); ?></h3>
					<p class="description" style="margin-bottom:15px;">
						<?php esc_html_e( 'دوربین اپلیکیشن را مقابل این بارکد بگیرید تا فروشگاه به صورت خودکار متصل شود.', 'woo-smart-quote' ); ?>
					</p>

					<div style="background:#fff; padding:15px; border-radius:12px; border:2px dashed #cbd5e1; display:inline-block; margin-bottom:15px;">
						<div id="wsq-pairing-qrcode" data-payload="<?php echo esc_attr( $pairing['payload_json'] ); ?>" style="display:flex; justify-content:center;"></div>
					</div>

					<div style="margin-bottom:15px;">
						<span style="display:block; font-size:12px; color:#64748b;"><?php esc_html_e( 'کد موقت اتصال دستی:', 'woo-smart-quote' ); ?></span>
						<span id="wsq-pairing-code-text" style="font-family:monospace; font-size:22px; font-weight:bold; letter-spacing:2px; color:#4f46e5; background:#f1f5f9; padding:4px 14px; border-radius:6px; display:inline-block; margin-top:4px;">
							<?php echo esc_html( $pairing['pairing_code'] ); ?>
						</span>
					</div>

					<div id="wsq-pairing-timer-box" style="margin-bottom:15px; font-size:13px; color:#475569;" data-expires="<?php echo esc_attr( $pairing['expires_at'] ); ?>">
						<span><?php esc_html_e( 'مهلت اعتبار بارکد:', 'woo-smart-quote' ); ?> </span>
						<strong id="wsq-pairing-timer-val" style="color:#d97706;">--:--</strong>
					</div>

					<div>
						<button type="button" class="button wsq-refresh-pairing-btn" style="display:inline-flex; align-items:center; gap:6px;">
							<?php echo WSQ_Icons::get( 'refresh' ); ?>
							<span><?php esc_html_e( 'تولید بارکد جدید', 'woo-smart-quote' ); ?></span>
						</button>
					</div>
				</div>

				<!-- Setup Guide & Features -->
				<div class="wsq-section-card">
					<h3 style="margin-top:0;"><?php esc_html_e( 'راهنمای راه‌اندازی و ویژگی‌های اپلیکیشن', 'woo-smart-quote' ); ?></h3>

					<div style="display:flex; flex-direction:column; gap:16px; margin-top:15px;">
						<div style="display:flex; gap:12px; align-items:flex-start;">
							<span style="background:#e0e7ff; color:#4338ca; border-radius:50%; width:28px; height:28px; display:flex; align-items:center; justify-content:center; font-weight:bold; flex-shrink:0;">۱</span>
							<div>
								<strong style="color:#1e293b;"><?php esc_html_e( 'نصب و اجرای اپلیکیشن', 'woo-smart-quote' ); ?></strong>
								<p style="margin:2px 0 0; color:#64748b; font-size:13px;"><?php esc_html_e( 'فایل APK رسمی اپلیکیشن را دانلود کرده و روی گوشی اندرویدی خود نصب نمایید.', 'woo-smart-quote' ); ?></p>
							</div>
						</div>

						<div style="display:flex; gap:12px; align-items:flex-start;">
							<span style="background:#e0e7ff; color:#4338ca; border-radius:50%; width:28px; height:28px; display:flex; align-items:center; justify-content:center; font-weight:bold; flex-shrink:0;">۲</span>
							<div>
								<strong style="color:#1e293b;"><?php esc_html_e( 'اسکن بارکد جفت‌سازی یک‌کلیکه', 'woo-smart-quote' ); ?></strong>
								<p style="margin:2px 0 0; color:#64748b; font-size:13px;"><?php esc_html_e( 'در صفحه خوش‌آمدگویی اپلیکیشن، دکمه «اسکن کد اتصال» را لمس کرده و دوربین را روبه‌روی بارکد بالا بگیرید. اتصال به صورت رمزنگاری‌شده و بدون نیاز به تایپ کلمه عبور انجام می‌شود.', 'woo-smart-quote' ); ?></p>
							</div>
						</div>

						<div style="display:flex; gap:12px; align-items:flex-start;">
							<span style="background:#e0e7ff; color:#4338ca; border-radius:50%; width:28px; height:28px; display:flex; align-items:center; justify-content:center; font-weight:bold; flex-shrink:0;">۳</span>
							<div>
								<strong style="color:#1e293b;"><?php esc_html_e( 'میز کار قیمت‌گذاری سیار (Mobile Pricing Desk)', 'woo-smart-quote' ); ?></strong>
								<p style="margin:2px 0 0; color:#64748b; font-size:13px;"><?php esc_html_e( 'قیمت تک‌تک اقلام را با تخفیف درصدی مشخص کنید، پیش‌فاکتور با فونت زیبای وزیرمتن آنی صادر شده و مستقیماً برای مشتری ارسال می‌شود.', 'woo-smart-quote' ); ?></p>
							</div>
						</div>

						<div style="display:flex; gap:12px; align-items:flex-start;">
							<span style="background:#e0e7ff; color:#4338ca; border-radius:50%; width:28px; height:28px; display:flex; align-items:center; justify-content:center; font-weight:bold; flex-shrink:0;">۴</span>
							<div>
								<strong style="color:#1e293b;"><?php esc_html_e( 'استعلام در پس‌زمینه (مقاوم در برابر تحریم و فیلترینگ)', 'woo-smart-quote' ); ?></strong>
								<p style="margin:2px 0 0; color:#64748b; font-size:13px;"><?php esc_html_e( 'بهره‌گیری از سرویس پیشرفته Android WorkManager بدون هیچ‌گونه وابستگی به Google Firebase، تضمین‌کننده دریافت اعلان استعلام‌های جدید حتی در شرایط اختلال شبکه است.', 'woo-smart-quote' ); ?></p>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Active Paired Devices List -->
			<div class="wsq-section-card">
				<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
					<div>
						<h3 style="margin:0;"><?php esc_html_e( 'دستگاه‌های متصل فعال به این فروشگاه', 'woo-smart-quote' ); ?></h3>
						<p class="description" style="margin:4px 0 0;"><?php esc_html_e( 'لیست گوشی‌ها و تبلت‌هایی که مجوز دسترسی و تعیین قیمت از طریق اپلیکیشن دارند.', 'woo-smart-quote' ); ?></p>
					</div>
					<span class="wsq-badge" style="background:#f1f5f9; color:#475569; padding:4px 10px; border-radius:6px; font-weight:bold;">
						<?php echo esc_html( sprintf( __( '%d دستگاه متصل', 'woo-smart-quote' ), count( $devices ) ) ); ?>
					</span>
				</div>

				<?php if ( empty( $devices ) ) : ?>
					<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:20px; text-align:center; color:#64748b;">
						<?php echo WSQ_Icons::get( 'phone' ); ?>
						<p style="margin:8px 0 0; font-size:14px;"><?php esc_html_e( 'هنوز هیچ دستگاهی متصل نشده است. بارکد بالا را با اپلیکیشن اسکن نمایید.', 'woo-smart-quote' ); ?></p>
					</div>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'نام دستگاه', 'woo-smart-quote' ); ?></th>
								<th scope="col"><?php esc_html_e( 'شناسه دستگاه', 'woo-smart-quote' ); ?></th>
								<th scope="col"><?php esc_html_e( 'تاریخ جفت‌سازی', 'woo-smart-quote' ); ?></th>
								<th scope="col"><?php esc_html_e( 'آخرین فعالیت', 'woo-smart-quote' ); ?></th>
								<th scope="col" style="text-align:left;"><?php esc_html_e( 'عملیات', 'woo-smart-quote' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $devices as $dev_id => $dev ) : ?>
								<?php
								$paired_date = isset( $dev['paired_at'] ) ? $pdf_gen->format_jalali_date( $dev['paired_at'] ) : '—';
								$last_active = isset( $dev['last_active'] ) ? human_time_diff( $dev['last_active'], time() ) . ' ' . __( 'قبل', 'woo-smart-quote' ) : '—';
								?>
								<tr id="wsq-device-row-<?php echo esc_attr( $dev_id ); ?>">
									<td>
										<strong><?php echo esc_html( isset( $dev['device_name'] ) ? $dev['device_name'] : __( 'دستگاه ناشناس', 'woo-smart-quote' ) ); ?></strong>
									</td>
									<td>
										<code dir="ltr"><?php echo esc_html( substr( $dev_id, 0, 16 ) . '...' ); ?></code>
									</td>
									<td><?php echo esc_html( $paired_date ); ?></td>
									<td><span style="color:#059669; font-weight:bold;"><?php echo esc_html( $last_active ); ?></span></td>
									<td style="text-align:left;">
										<button type="button" class="button wsq-revoke-device-btn" data-device-id="<?php echo esc_attr( $dev_id ); ?>" style="color:#b91c1c; border-color:#f87171;">
											<?php echo WSQ_Icons::get( 'trash' ); ?>
											<span><?php esc_html_e( 'ابطال دسترسی', 'woo-smart-quote' ); ?></span>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX: Refresh Pairing Session Token
	 */
	public function ajax_refresh_pairing() {
		check_ajax_referer( 'wsq_admin_test_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'woo-smart-quote' ) ) );
		}

		delete_transient( WSQ_REST_API::TRANSIENT_PAIR );
		$pairing = WSQ_REST_API::get_or_create_pairing_session( 0, true );

		wp_send_json_success( array(
			'pairing_code' => $pairing['pairing_code'],
			'expires_at'   => $pairing['expires_at'],
			'payload_json' => $pairing['payload_json'],
			'message'      => __( 'کد جفت‌سازی جدید ایجاد شد.', 'woo-smart-quote' ),
		) );
	}

	/**
	 * AJAX: Revoke Paired Device
	 */
	public function ajax_revoke_device() {
		check_ajax_referer( 'wsq_admin_test_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'woo-smart-quote' ) ) );
		}

		$device_id = isset( $_POST['device_id'] ) ? sanitize_text_field( wp_unslash( $_POST['device_id'] ) ) : '';

		if ( empty( $device_id ) ) {
			wp_send_json_error( array( 'message' => __( 'شناسه دستگاه مشخص نشده است.', 'woo-smart-quote' ) ) );
		}

		$revoked = WSQ_REST_API::revoke_device( $device_id );

		if ( $revoked ) {
			wp_send_json_success( array( 'message' => __( 'دسترسی دستگاه با موفقیت ابطال شد.', 'woo-smart-quote' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'دستگاه مورد نظر یافت نشد.', 'woo-smart-quote' ) ) );
		}
	}
}
