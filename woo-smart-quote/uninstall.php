<?php
/**
 * Nerkhnegar (woo-smart-quote) uninstall routine.
 *
 * Runs only when the plugin is deleted via WordPress admin (UNINSTALL_PLUGIN
 * is only defined during a single-file uninstall, so we also allow the
 * multisite path where the file is loaded directly).
 *
 * Behaviour:
 * - Plugin options, order meta, transients and pending scheduled actions are
 *   always removed.
 * - Generated PDF files are removed when they exist.
 * - Quote orders are NOT deleted (they are real WooCommerce orders); their
 *   custom statuses remain registered by core so history stays intact.
 */

// Bail if WordPress has not invoked this as part of a real uninstall flow.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) && ! defined( 'WP_CLI' ) ) {
	exit;
}

function wsq_uninstall_cleanup() {
	global $wpdb;

	// 1. Plugin options.
	$options = array(
		'wsq_bale_token',
		'wsq_bale_default_chat_ids',
		'wsq_eitaa_token',
		'wsq_eitaa_default_chat_ids',
		'wsq_message_template',
		'wsq_pdf_logo',
		'wsq_company_name',
		'wsq_national_id',
		'wsq_economic_code',
		'wsq_registration_no',
		'wsq_company_postal_code',
		'wsq_company_phone',
		'wsq_company_address',
		'wsq_company_bank_name',
		'wsq_company_bank_account',
		'wsq_company_bank_shaba',
		'wsq_company_bank_owner',
		'wsq_company_seal_image',
		'wsq_show_digital_stamp',
		'wsq_stamp_text',
		'wsq_company_signature_image',
		'wsq_show_digital_signature',
		'wsq_button_text',
		'wsq_enable_archive_button',
		'wsq_enable_floating_cart',
		'wsq_hide_add_to_cart',
		'wsq_category_routes',
		'wsq_buyer_sms_enabled',
		'wsq_buyer_sms_provider',
		'wsq_buyer_sms_apikey',
		'wsq_buyer_sms_sender',
		'wsq_buyer_sms_pattern',
		'wsq_buyer_bale_token',
		'wsq_pdf_retention_days',
		'wsq_cleanup_scheduled',
		// Bot & inbound (1.3.0).
		'wsq_polling_enabled',
		'wsq_bale_admin_chat_ids',
		'wsq_eitaa_admin_chat_ids',
		'wsq_buyer_bot_enabled',
		'wsq_bot_welcome',
		'wsq_bot_orders_header',
		'wsq_bot_order_card',
		'wsq_webhook_secret',
		'wsq_chat_links',
		'wsq_poll_off',
		'wsq_bale_offset',
		'wsq_eitaa_offset',
		'wsq_bale_webhook_url',
		'wsq_bale_last_update_id',
		// Companion app (1.0.0).
		'wsq_paired_devices',
		'wsq_companion_apk_url',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// 2. Transient caches.
	delete_transient( 'wsq_quote_page_id' );
	delete_transient( 'wsq_pairing_session' );

	// 3. Order meta written by the plugin (billing_phone belongs to core).
	$meta_keys = array(
		'_wsq_customer_phone',
		'_wsq_buyer_channel',
		'_wsq_quote_validity',
		'_wsq_pdf_secret',
		'_wsq_proposal_sent',
		'_wsq_expert_notify_attempts',
		'_wsq_expert_notify_failed_targets',
		'_wsq_pricing_history',
		'_wsq_custom_tax_rate',
		'_wsq_item_discount_pct',
	);

	foreach ( $meta_keys as $meta_key ) {
		$wpdb->delete(
			$wpdb->postmeta,
			array( 'meta_key' => $meta_key )
		);
	}

	// 3b. HPOS custom order tables meta cleanup.
	$orders_meta_table = $wpdb->prefix . 'wc_orders_meta';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $orders_meta_table ) ) === $orders_meta_table ) {
		foreach ( $meta_keys as $meta_key ) {
			$wpdb->delete(
				$orders_meta_table,
				array( 'meta_key' => $meta_key )
			);
		}
	}

	// 4. Pending scheduled actions in the plugin's Action Scheduler group.
	if ( class_exists( 'ActionScheduler_Store' ) && class_exists( 'ActionScheduler_ActionFactory' ) ) {
		try {
			$store  = ActionScheduler_Store::instance();
			$claims = $store->query_actions(
				array(
					'group'   => 'woo_smart_quote',
					'status'  => 'pending',
					'per_page' => 500,
				)
			);
			if ( is_array( $claims ) ) {
				foreach ( $claims as $action_id ) {
					$store->cancel_action( $action_id );
				}
			}
		} catch ( Exception $e ) {
			// The store is unavailable during uninstall; nothing else to do.
		}
	}

	// 5. WP cron hook scheduled by the plugin.
	wp_clear_scheduled_hook( 'wsq_daily_cleanup' );
	wp_clear_scheduled_hook( 'wsq_poll_updates' );

	// 5b. Inbound-messages table (1.3.0).
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'wsq_messages' );

	// 6. Generated PDF directory under uploads (only when the folder belongs
	// to us and is otherwise empty of anything unexpected).
	$upload_dir = wp_upload_dir();
	$pdf_dir    = $upload_dir['basedir'] . '/woo-smart-quote-pdfs';
	if ( is_dir( $pdf_dir ) ) {
		$files = glob( $pdf_dir . '/*.pdf' );
		if ( is_array( $files ) ) {
			foreach ( $files as $file ) {
				wp_delete_file( $file );
			}
		}

		// mPDF caches fonts and temp files here; leaving them behind both
		// wastes space and blocks the rmdir below.
		$tmp_dir = $pdf_dir . '/tmp';
		if ( is_dir( $tmp_dir ) ) {
			$tmp_files = glob( $tmp_dir . '/*' );
			if ( is_array( $tmp_files ) ) {
				foreach ( $tmp_files as $tmp_file ) {
					if ( is_file( $tmp_file ) ) {
						wp_delete_file( $tmp_file );
					} elseif ( is_dir( $tmp_file ) ) {
						array_map( 'wp_delete_file', (array) glob( $tmp_file . '/*' ) );
						@rmdir( $tmp_file );
					}
				}
			}
			@rmdir( $tmp_dir );
		}

		foreach ( array( '.htaccess', 'index.php' ) as $guard_file ) {
			if ( file_exists( $pdf_dir . '/' . $guard_file ) ) {
				wp_delete_file( $pdf_dir . '/' . $guard_file );
			}
		}

		@rmdir( $pdf_dir );
	}
}

wsq_uninstall_cleanup();
