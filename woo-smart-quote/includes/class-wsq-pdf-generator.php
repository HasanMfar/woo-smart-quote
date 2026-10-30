<?php
/**
 * PDF Generator with RTL/Persian support.
 *
 * Rendering is handled by mPDF (bundled under lib/mpdf), which implements
 * Arabic/Persian letter joining through the font's OpenType tables (GSUB)
 * and a full bidi algorithm. This is why the templates contain plain,
 * unshaped UTF-8 text: no manual presentation-form conversion is needed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSQ_PDF_Generator {

	protected static $instance = null;

	/** 30 days, matching the default PDF retention window. */
	const PDF_TOKEN_TTL = 2592000;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	protected function __construct() {
		// PDFs live under uploads with a directory deny rule. Apache honours
		// it, nginx does not, so downloads always go through WordPress with a
		// per-order token instead of a direct static URL.
		add_action( 'admin_post_wsq_download_pdf', array( $this, 'handle_pdf_download' ) );
		add_action( 'admin_post_nopriv_wsq_download_pdf', array( $this, 'handle_pdf_download' ) );

		// Include mPDF and its Composer dependencies (bundled, no Composer needed).
		if ( ! class_exists( 'Mpdf\Mpdf' ) ) {
			$mpdf_autoload = WSQ_PLUGIN_DIR . 'lib/mpdf/vendor/autoload.php';
			if ( file_exists( $mpdf_autoload ) ) {
				require_once $mpdf_autoload;
			}
		}
	}

	/**
	 * Generate PDF for an order and return the file path.
	 *
	 * @param int $order_id
	 * @return string|false File path on success, false on failure.
	 */
	public function generate( $order_id ) {
		if ( ! class_exists( 'Mpdf\Mpdf' ) ) {
			WSQ_Logger::error( 'pdf', 'mPDF library is not available; PDF skipped.' );
			return false;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		// Prepare data
		$customer_name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		$created_date  = $order->get_date_created();
		$date_greg     = wc_format_datetime( $created_date );
		$date_jalali   = $this->format_jalali_date( $created_date ? $created_date->getTimestamp() : time() );
		$items         = $order->get_items();
		$total         = wp_strip_all_tags( $order->get_formatted_order_total() );

		// Logo: convert URL to local file path for reliability
		$logo_url    = get_option( 'wsq_pdf_logo', '' );
		$logo_source = $this->resolve_logo( $logo_url );

		// Official Company Settings
		$company_name        = get_option( 'wsq_company_name', get_bloginfo( 'name' ) );
		$economic_code       = get_option( 'wsq_economic_code', '' );
		$national_id         = get_option( 'wsq_national_id', '' );
		$registration_no     = get_option( 'wsq_registration_no', '' );
		$company_postal_code = get_option( 'wsq_company_postal_code', '' );
		$company_phone       = get_option( 'wsq_company_phone', '' );
		$company_address     = get_option( 'wsq_company_address', '' );
		$company_bank_name   = get_option( 'wsq_company_bank_name', '' );
		$company_bank_account= get_option( 'wsq_company_bank_account', '' );
		$company_bank_shaba  = get_option( 'wsq_company_bank_shaba', '' );
		$company_bank_owner  = get_option( 'wsq_company_bank_owner', '' );
		$company_seal_image      = get_option( 'wsq_company_seal_image', '' );
		$seal_image_source       = $this->resolve_logo( $company_seal_image );
		$show_stamp              = get_option( 'wsq_show_digital_stamp', 'yes' ) === 'yes';
		$stamp_text              = get_option( 'wsq_stamp_text', __( 'تأیید شد - استعلام قیمت رسمی', 'woo-smart-quote' ) );
		$company_signature_image = get_option( 'wsq_company_signature_image', '' );
		$signature_image_source  = $this->resolve_logo( $company_signature_image );
		$show_signature          = get_option( 'wsq_show_digital_signature', 'yes' ) === 'yes';

		// Extra PDF look & feel customization
		$pdf_header_note = get_option( 'wsq_pdf_header_note', __( 'سامانه رسمی استعلام و سفارشات سازمانی', 'woo-smart-quote' ) );
		$pdf_footer_text = get_option( 'wsq_pdf_footer_text', '' );
		$pdf_validity    = (int) get_option( 'wsq_pdf_validity_days', 7 );
		if ( $pdf_validity <= 0 ) {
			$pdf_validity = 7;
		}

		// Calculate Jalali Expiry Date
		$day_seconds         = defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400;
		$created_ts          = $created_date ? $created_date->getTimestamp() : time();
		$expiry_ts           = $created_ts + ( $pdf_validity * $day_seconds );
		$date_expiry_jalali  = $this->format_jalali_date( $expiry_ts );

		// Buyer Tax 169 Identifiers (from order meta if set by checkout or B2B extensions)
		$buyer_national_id     = (string) ( $order->get_meta( '_billing_national_id' ) ?: $order->get_meta( 'billing_national_id' ) ?: $order->get_meta( '_billing_melli_code' ) ?: $order->get_meta( 'billing_melli_code' ) ?: $order->get_meta( '_wsq_buyer_national_id' ) ?: '' );
		$buyer_economic_code   = (string) ( $order->get_meta( '_billing_economic_code' ) ?: $order->get_meta( 'billing_economic_code' ) ?: $order->get_meta( '_wsq_buyer_economic_code' ) ?: '' );
		$buyer_registration_no = (string) ( $order->get_meta( '_billing_registration_no' ) ?: $order->get_meta( 'billing_registration_no' ) ?: $order->get_meta( '_wsq_buyer_registration_no' ) ?: '' );

		// Render HTML template
		ob_start();
		wc_get_template(
			'pdf/quote-fa.php',
			array(
				'order'                 => $order,
				'order_id'              => $order_id,
				'customer_name'         => $customer_name,
				'date'                  => $date_jalali,
				'date_gregorian'        => $date_greg,
				'date_expiry'           => $date_expiry_jalali,
				'items'                 => $items,
				'total'                 => $total,
				'logo_url'              => $logo_source,
				'company_name'          => $company_name,
				'economic_code'         => $economic_code,
				'national_id'           => $national_id,
				'registration_no'       => $registration_no,
				'company_postal_code'   => $company_postal_code,
				'company_phone'         => $company_phone,
				'company_address'       => $company_address,
				'company_bank_name'     => $company_bank_name,
				'company_bank_account'  => $company_bank_account,
				'company_bank_shaba'    => $company_bank_shaba,
				'company_bank_owner'    => $company_bank_owner,
				'seal_image_url'        => $seal_image_source,
				'show_stamp'            => $show_stamp,
				'stamp_text'            => $stamp_text,
				'signature_image_url'   => $signature_image_source,
				'show_signature'        => $show_signature,
				'header_note'           => $pdf_header_note,
				'footer_text'           => $pdf_footer_text,
				'validity_days'         => $pdf_validity,
				'buyer_national_id'     => $buyer_national_id,
				'buyer_economic_code'   => $buyer_economic_code,
				'buyer_registration_no' => $buyer_registration_no,
			),
			'woo-smart-quote/',
			WSQ_PLUGIN_DIR . 'templates/'
		);
		$html = ob_get_clean();

		// Save PDF
		$upload_dir = wp_upload_dir();
		$pdf_dir    = $upload_dir['basedir'] . '/woo-smart-quote-pdfs';

		if ( ! file_exists( $pdf_dir ) ) {
			wp_mkdir_p( $pdf_dir );
			// Protect directory from direct web access. modern Apache (2.4+)
			// and legacy 2.2 syntaxes are both handled; nginx users must rely
			// on the fact that the file is only read server-side.
			$htaccess = "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder Deny,Allow\nDeny from all\n</IfModule>\n";
			file_put_contents( $pdf_dir . '/.htaccess', $htaccess );
			file_put_contents( $pdf_dir . '/index.php', '<?php // Silence is golden.' );
		}

		// mPDF writes OTL/font caches and temporary files. Keeping them under
		// uploads means the plugin folder itself can stay read-only on hosts
		// that do not allow writing inside wp-content/plugins.
		$temp_dir = $pdf_dir . '/tmp';
		if ( ! file_exists( $temp_dir ) ) {
			wp_mkdir_p( $temp_dir );
		}

		try {
			$mpdf = $this->create_mpdf( $temp_dir );
			$mpdf->WriteHTML( $html );
		} catch ( \Throwable $e ) {
			WSQ_Logger::error( 'pdf', 'mPDF render failed: ' . $e->getMessage(), array( 'order_id' => $order_id ) );
			return false;
		}

		// One stable, unguessable file per order prevents unbounded uploads
		// growth (regeneration overwrites). A per-order random secret keeps
		// the filename non-enumerable after the first generation.
		$pdf_secret = $order->get_meta( '_wsq_pdf_secret' );
		if ( empty( $pdf_secret ) ) {
			$pdf_secret = wp_generate_password( 16, false );
			$order->update_meta_data( '_wsq_pdf_secret', $pdf_secret );
			$order->save();
		}

		$file_name = sprintf( 'quote-%d-%s.pdf', $order_id, $pdf_secret );
		$file_path = $pdf_dir . '/' . $file_name;

		try {
			$mpdf->Output( $file_path, \Mpdf\Output\Destination::FILE );
		} catch ( \Throwable $e ) {
			WSQ_Logger::error( 'pdf', 'Failed to write PDF file: ' . $e->getMessage(), array(
				'order_id' => $order_id,
				'path'     => $file_path,
			) );
			return false;
		}

		WSQ_Logger::info( 'pdf', 'PDF generated.', array(
			'order_id' => $order_id,
			'file'     => $file_name,
		) );

		return $file_path;
	}

	/**
	 * Resolve the generated file for an order.
	 *
	 * @param int  $order_id
	 * @param bool $auto_generate Whether to render the PDF when missing.
	 * @return string|false Absolute path.
	 */
	public function get_pdf_path( $order_id, $auto_generate = false ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		$pdf_secret = $order->get_meta( '_wsq_pdf_secret' );
		$upload_dir = wp_upload_dir();
		$pdf_dir    = $upload_dir['basedir'] . '/woo-smart-quote-pdfs';
		$file_path  = ! empty( $pdf_secret )
			? $pdf_dir . '/' . sprintf( 'quote-%d-%s.pdf', $order_id, $pdf_secret )
			: '';

		if ( '' !== $file_path && file_exists( $file_path ) ) {
			return $file_path;
		}

		if ( ! $auto_generate ) {
			return false;
		}

		return $this->generate( $order_id );
	}

	/**
	 * Token bound to one order's PDF secret; regenerating rotates the file and
	 * the token with it.
	 *
	 * @param WC_Order $order
	 * @return string Empty when the order has no PDF secret yet.
	 */
	private function pdf_token( $order ) {
		$secret = (string) $order->get_meta( '_wsq_pdf_secret' );
		if ( '' === $secret ) {
			return '';
		}

		$order_id = $order->get_id();
		$expires  = time() + self::PDF_TOKEN_TTL;
		$signature = $this->pdf_signature( $order_id, $secret, $expires );

		return $expires . '.' . $signature;
	}

	/**
	 * @param int    $order_id
	 * @param string $secret
	 * @param int    $expires
	 * @return string
	 */
	private function pdf_signature( $order_id, $secret, $expires ) {
		return hash_hmac( 'sha256', 'wsq-pdf|' . $order_id . '|' . $secret . '|' . $expires, wp_salt( 'auth' ) );
	}

	/**
	 * Whether a download token matches the order's current PDF and is unexpired.
	 *
	 * @param int    $order_id
	 * @param string $token
	 * @return bool
	 */
	public function is_valid_pdf_token( $order_id, $token ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		$secret = (string) $order->get_meta( '_wsq_pdf_secret' );
		if ( '' === $secret ) {
			return false;
		}

		$parts = explode( '.', (string) $token, 2 );
		if ( 2 !== count( $parts ) ) {
			return false;
		}

		$expires = (int) $parts[0];
		if ( $expires <= time() ) {
			return false;
		}

		return hash_equals( $this->pdf_signature( $order->get_id(), $secret, $expires ), $parts[1] );
	}

	/**
	 * Download URL for an order's generated PDF.
	 *
	 * @param int  $order_id
	 * @param bool $auto_generate If file does not exist, whether to generate it.
	 * @return string|false
	 */
	public function get_pdf_url( $order_id, $auto_generate = true ) {
		$file = $this->get_pdf_path( $order_id, $auto_generate );
		if ( ! $file ) {
			return false;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		return add_query_arg(
			array(
				'action'  => 'wsq_download_pdf',
				'wsq_pdf' => $order_id,
				'token'   => $this->pdf_token( $order ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Human-readable file name for the generated PDF.
	 *
	 * @param int $order_id
	 * @return string
	 */
	public function get_pdf_filename( $order_id ) {
		$file = $this->get_pdf_path( $order_id, false );
		return $file ? basename( $file ) : '';
	}

	/**
	 * Stream a PDF after checking staff capability or the order token.
	 *
	 * @return void
	 */
	public function handle_pdf_download() {
		$order_id = isset( $_GET['wsq_pdf'] ) ? absint( wp_unslash( $_GET['wsq_pdf'] ) ) : 0;
		$order    = $order_id ? wc_get_order( $order_id ) : false;

		if ( ! $order ) {
			wp_die( esc_html__( 'فایل درخواستی یافت نشد.', 'woo-smart-quote' ), '', array( 'response' => 404 ) );
		}

		$is_staff = current_user_can( 'edit_shop_order', $order_id ) || current_user_can( 'manage_woocommerce' );
		if ( ! $is_staff ) {
			$token = isset( $_GET['token'] ) ? (string) wp_unslash( $_GET['token'] ) : '';
			if ( ! $this->is_valid_pdf_token( $order_id, $token ) ) {
				wp_die( esc_html__( 'دسترسی به این فایل مجاز نیست.', 'woo-smart-quote' ), '', array( 'response' => 403 ) );
			}
		}

		$file = $this->get_pdf_path( $order_id, false );
		if ( ! $file ) {
			wp_die( esc_html__( 'فایل درخواستی یافت نشد.', 'woo-smart-quote' ), '', array( 'response' => 404 ) );
		}

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: inline; filename="' . basename( $file ) . '"' );
		header( 'Content-Length: ' . filesize( $file ) );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $file );
		exit;
	}

	/**
	 * Build the mPDF instance used for invoice rendering.
	 *
	 * Vazirmatn is registered with useOTL so mPDF can pull real joined glyphs
	 * out of the font's GSUB table, which is what makes Persian text render
	 * correctly instead of as disconnected letters.
	 *
	 * @param string $temp_dir Writable directory for mPDF caches.
	 * @return \Mpdf\Mpdf
	 */
	private function create_mpdf( $temp_dir ) {
		$config_defaults = ( new \Mpdf\Config\ConfigVariables() )->getDefaults();
		$font_defaults   = ( new \Mpdf\Config\FontVariables() )->getDefaults();

		return new \Mpdf\Mpdf(
			array(
				'mode'          => 'utf-8',
				'format'        => 'A4',
				'margin_left'   => 12,
				'margin_right'  => 12,
				'margin_top'    => 14,
				'margin_bottom' => 16,
				'margin_header' => 6,
				'margin_footer' => 10,
				'default_font'  => 'vazirmatn',
				'fontDir'       => array_merge(
					$config_defaults['fontDir'],
					array( WSQ_PLUGIN_DIR . 'lib/mpdf/fonts' )
				),
				'fontdata'      => $font_defaults['fontdata'] + array(
					'vazirmatn' => array(
						'R'          => 'Vazirmatn-Regular.ttf',
						'B'          => 'Vazirmatn-Bold.ttf',
						'useOTL'     => 0xFF,
						'useKashida' => 75,
					),
				),
				'tempDir'       => $temp_dir,
				'img_dpi'       => 96,
			)
		);
	}

	/**
	 * Find the deepest common filesystem ancestor of two paths so a single
	 * string chroot can cover both locations.
	 *
	 * @param string $path_a
	 * @param string $path_b
	 * @return string
	 */
	private function get_common_chroot( $path_a, $path_b ) {
		$path_a = rtrim( wp_normalize_path( realpath( $path_a ) ?: $path_a ), '/' );
		$path_b = rtrim( wp_normalize_path( realpath( $path_b ) ?: $path_b ), '/' );

		$parts_a = explode( '/', strtolower( $path_a ) );
		$parts_b = explode( '/', strtolower( $path_b ) );

		$common = array();
		$count  = min( count( $parts_a ), count( $parts_b ) );
		for ( $i = 0; $i < $count; $i++ ) {
			if ( $parts_a[ $i ] !== $parts_b[ $i ] ) {
				break;
			}
			$common[] = $parts_a[ $i ];
		}

		// Rebuild with original casing from path_a; keep the drive root at minimum.
		if ( empty( $common ) ) {
			return wp_normalize_path( ABSPATH );
		}

		$normalized = implode( '/', $common );
		if ( '' === $normalized || strlen( $normalized ) > strlen( $path_a ) ) {
			return wp_normalize_path( ABSPATH );
		}

		// Restore original case by taking the corresponding prefix of path_a.
		$prefix_a = substr( $path_a, 0, strlen( $normalized ) );
		return '' === $prefix_a ? wp_normalize_path( ABSPATH ) : $prefix_a;
	}

	/**
	 * Resolve logo URL to a local file path for mPDF.
	 * Remote URLs are unreliable in PDF renderers. If the logo is in wp-content,
	 * convert it to an absolute filesystem path.
	 *
	 * @param string $url
	 * @return string
	 */
	private function resolve_logo( $url ) {
		if ( empty( $url ) ) {
			return '';
		}

		// Check if the URL points to our own site
		$site_url   = site_url();
		$upload_dir = wp_upload_dir();

		if ( strpos( $url, $site_url ) === 0 ) {
			// Convert URL to local path (URLs always use forward slashes)
			$relative  = str_replace( $site_url, '', $url );
			$local_path = wp_normalize_path( ABSPATH ) . ltrim( $relative, '/' );

			if ( file_exists( $local_path ) ) {
				return $local_path;
			}
		}

		// Fallback: return URL as-is (requires isRemoteEnabled = true)
		return $url;
	}

	/**
	 * Format timestamp to Jalali date string (YYYY/MM/DD)
	 *
	 * @param int $timestamp
	 * @return string
	 */
	public function format_jalali_date( $timestamp ) {
		$g_y = (int) gmdate( 'Y', $timestamp );
		$g_m = (int) gmdate( 'm', $timestamp );
		$g_d = (int) gmdate( 'd', $timestamp );

		list( $j_y, $j_m, $j_d ) = $this->gregorian_to_jalali( $g_y, $g_m, $g_d );

		return sprintf( '%04d/%02d/%02d', $j_y, $j_m, $j_d );
	}

	/**
	 * Convert Gregorian to Jalali (Solar Hijri)
	 *
	 * @param int $gy
	 * @param int $gm
	 * @param int $gd
	 * @return array array(j_y, j_m, j_d)
	 */
	public function gregorian_to_jalali( $gy, $gm, $gd ) {
		$g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
		$gy2   = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
		$days  = 355666 + ( 365 * $gy ) + ( (int) ( ( $gy2 + 3 ) / 4 ) ) - ( (int) ( ( $gy2 + 99 ) / 100 ) ) + ( (int) ( ( $gy2 + 399 ) / 400 ) ) + $gd + $g_d_m[ $gm - 1 ];
		$jy    = -1595 + ( 33 * ( (int) ( $days / 12053 ) ) );
		$days %= 12053;
		$jy   += 4 * ( (int) ( $days / 1461 ) );
		$days %= 1461;
		if ( $days > 365 ) {
			$jy   += (int) ( ( $days - 1 ) / 365 );
			$days  = ( $days - 1 ) % 365;
		}
		if ( $days < 186 ) {
			$jm = 1 + (int) ( $days / 31 );
			$jd = 1 + ( $days % 31 );
		} else {
			$jm = 7 + (int) ( ( $days - 186 ) / 30 );
			$jd = 1 + ( ( $days - 186 ) % 30 );
		}
		return array( $jy, $jm, $jd );
	}

	/**
	 * Convert English digits in string to Persian digits.
	 *
	 * @param string|int|float $str
	 * @return string
	 */
	public static function to_persian_digits( $str ) {
		$en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
		$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
		return str_replace( $en, $fa, (string) $str );
	}

	/**
	 * Format numeric amount with thousand-separators and Persian digits.
	 *
	 * @param float|int $number
	 * @param int       $decimals
	 * @return string
	 */
	public static function format_persian_number( $number, $decimals = 0 ) {
		$formatted = number_format( (float) $number, $decimals );
		return self::to_persian_digits( $formatted );
	}

	/**
	 * Convert numeric amount into formal Persian words.
	 *
	 * @param float|int $number
	 * @param string    $currency Currency name (default 'تومان').
	 * @return string
	 */
	public static function number_to_persian_words( $number, $currency = 'تومان' ) {
		$number = (int) round( (float) $number );
		if ( $number <= 0 ) {
			return '';
		}

		$ones = array(
			0 => '', 1 => 'یک', 2 => 'دو', 3 => 'سه', 4 => 'چهار', 5 => 'پنج',
			6 => 'شش', 7 => 'هفت', 8 => 'هشت', 9 => 'نه', 10 => 'ده',
			11 => 'یازده', 12 => 'دوازده', 13 => 'سیزده', 14 => 'چهارده', 15 => 'پانزده',
			16 => 'شانزده', 17 => 'هفده', 18 => 'هجده', 19 => 'نوزده',
		);

		$tens = array(
			2 => 'بیست', 3 => 'سی', 4 => 'چهل', 5 => 'پنجاه',
			6 => 'شصت', 7 => 'هفتاد', 8 => 'هشتاد', 9 => 'نود',
		);

		$hundreds = array(
			1 => 'یکصد', 2 => 'دویست', 3 => 'سیصد', 4 => 'چهارصد', 5 => 'پانصد',
			6 => 'ششصد', 7 => 'هفتصد', 8 => 'هشتصد', 9 => 'نهصد',
		);

		$scales = array( '', 'هزار', 'میلیون', 'میلیارد', 'تریلیون' );

		$groups    = array();
		$scale_idx = 0;

		while ( $number > 0 ) {
			$triplet = $number % 1000;
			if ( $triplet > 0 ) {
				$parts = array();
				$h     = (int) ( $triplet / 100 );
				$rem   = $triplet % 100;
				if ( $h > 0 && isset( $hundreds[ $h ] ) ) {
					$parts[] = $hundreds[ $h ];
				}
				if ( $rem > 0 ) {
					if ( $rem < 20 ) {
						$parts[] = $ones[ $rem ];
					} else {
						$t = (int) ( $rem / 10 );
						$o = $rem % 10;
						if ( isset( $tens[ $t ] ) ) {
							$parts[] = $tens[ $t ];
						}
						if ( $o > 0 && isset( $ones[ $o ] ) ) {
							$parts[] = $ones[ $o ];
						}
					}
				}
				$words = implode( ' و ', $parts );
				if ( $scale_idx > 0 && isset( $scales[ $scale_idx ] ) ) {
					$words .= ' ' . $scales[ $scale_idx ];
				}
				array_unshift( $groups, $words );
			}
			$number = (int) ( $number / 1000 );
			$scale_idx++;
		}

		$cur_text = ! empty( $currency ) ? ' ' . trim( $currency ) : '';
		return implode( ' و ', $groups ) . $cur_text . ' تمام';
	}

	/**
	 * Build authentic Iranian corporate seal SVG with zero missing font glyphs.
	 *
	 * @param string $seller_name
	 * @param string $reg_no
	 * @param string $national_id
	 * @param string $date
	 * @param string $stamp_text
	 * @return string
	 */
	public static function get_vector_seal( $seller_name, $reg_no = '', $national_id = '', $date = '', $stamp_text = '' ) {
		$seller_display = ! empty( $seller_name ) ? esc_html( $seller_name ) : esc_html__( 'شرکت رسمی', 'woo-smart-quote' );
		$stamp_display  = ! empty( $stamp_text ) ? esc_html( $stamp_text ) : esc_html__( 'تأیید شد — پیش‌فاکتور رسمی', 'woo-smart-quote' );
		$reg_fa         = ! empty( $reg_no ) && '—' !== $reg_no ? self::to_persian_digits( $reg_no ) : '';
		$nat_fa         = ! empty( $national_id ) && '—' !== $national_id ? self::to_persian_digits( $national_id ) : '';
		$date_fa        = ! empty( $date ) ? self::to_persian_digits( $date ) : '';

		$reg_line = '';
		if ( '' !== $reg_fa ) {
			$reg_line = '<text x="70" y="42" font-size="6.8" fill="#991b1b" text-anchor="middle" font-family="vazirmatn">' . esc_html__( 'شماره ثبت:', 'woo-smart-quote' ) . ' ' . esc_html( $reg_fa ) . '</text>';
		}

		$nat_line = '';
		if ( '' !== $nat_fa ) {
			$nat_line = '<text x="70" y="79" font-size="7" font-weight="bold" fill="#b91c1c" text-anchor="middle" font-family="vazirmatn">' . esc_html__( 'شناسه ملی:', 'woo-smart-quote' ) . ' ' . esc_html( $nat_fa ) . '</text>';
		}

		$date_line = '';
		if ( '' !== $date_fa ) {
			$date_line = '<text x="70" y="99" font-size="6.8" font-weight="bold" fill="#b91c1c" text-anchor="middle" font-family="vazirmatn">' . esc_html__( 'تاریخ صدور:', 'woo-smart-quote' ) . ' ' . esc_html( $date_fa ) . '</text>';
		}

		return '<svg width="124" height="124" viewBox="0 0 140 140" xmlns="http://www.w3.org/2000/svg">
  <g transform="rotate(-3 70 70)">
    <!-- Outer Double Rings -->
    <circle cx="70" cy="70" r="66" stroke="#b91c1c" stroke-width="2.5" fill="#fffcfc" fill-opacity="0.97" />
    <circle cx="70" cy="70" r="61" stroke="#b91c1c" stroke-width="0.9" stroke-dasharray="2.5,2" fill="none" />
    <circle cx="70" cy="70" r="58" stroke="#b91c1c" stroke-width="0.8" fill="none" />

    <!-- Corner/Edge Vector Diamond Ornaments (No font glyph dependency) -->
    <polygon points="70,14 72,17 70,20 68,17" fill="#b91c1c" />
    <polygon points="126,70 123,72 120,70 123,68" fill="#b91c1c" />
    <polygon points="14,70 17,72 20,70 17,68" fill="#b91c1c" />

    <!-- Company Name Arc/Top -->
    <text x="70" y="32" font-size="8.8" font-weight="bold" fill="#b91c1c" text-anchor="middle" font-family="vazirmatn">' . $seller_display . '</text>

    <!-- Subtitle / Registration No -->
    ' . $reg_line . '

    <!-- Center Cartouche Badge -->
    <rect x="16" y="47" width="108" height="21" rx="4" fill="#fef2f2" stroke="#b91c1c" stroke-width="1.2" />
    <text x="70" y="61" font-size="8.4" font-weight="bold" fill="#b91c1c" text-anchor="middle" font-family="vazirmatn">' . $stamp_display . '</text>

    <!-- Legal Identifiers -->
    ' . $nat_line . '
    <text x="70" y="89" font-size="6.5" fill="#991b1b" text-anchor="middle" font-family="vazirmatn">' . esc_html__( 'واحد امور مالی و بازرگانی', 'woo-smart-quote' ) . '</text>
    ' . $date_line . '

    <!-- Bottom Decorative Accent -->
    <polygon points="70,126 72,123 70,120 68,123" fill="#b91c1c" />
  </g>
</svg>';
	}

	/**
	 * Executive fluid fountain pen signature SVG.
	 *
	 * @return string
	 */
	public static function get_vector_signature() {
		return '<svg width="125" height="48" viewBox="0 0 140 54" xmlns="http://www.w3.org/2000/svg" style="display:block; margin-top:-4px;">
  <!-- Fluid executive calligraphic strokes in royal fountain pen blue -->
  <path d="M 8 34 C 24 14, 38 46, 56 16 C 66 2, 78 42, 94 20 C 104 6, 118 32, 134 14" stroke="#1d4ed8" stroke-width="2.2" fill="none" stroke-linecap="round" stroke-linejoin="round" opacity="0.94" />
  <path d="M 22 40 Q 62 24, 112 32 M 42 44 C 72 36, 98 38, 128 30" stroke="#1e40af" stroke-width="1.6" fill="none" stroke-linecap="round" stroke-linejoin="round" opacity="0.88" />
  <path d="M 14 46 C 50 42, 88 44, 136 36" stroke="#1d4ed8" stroke-width="1.2" fill="none" stroke-linecap="round" opacity="0.75" />
</svg>';
	}
}

