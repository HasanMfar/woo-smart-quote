<?php
/**
 * Quote analytics used by the admin dashboard.
 *
 * All numbers are derived from WooCommerce orders so the dashboard always
 * matches what the team sees in the orders list (works with both the legacy
 * post storage and High-Performance Order Storage).
 *
 * Definitions:
 *  - A quote order is any order in the quote flow: it currently sits in
 *    quote-request / quote-sent, or it carries the buyer phone meta that the
 *    quote form writes on creation (so a quote that was later paid for is
 *    still recognised as a quote and not as a regular store order).
 *  - Converted = the buyer actually placed the order (processing/completed).
 *    A quote-sent order only has a proposal out and is not counted yet.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSQ_Stats {

	/**
	 * Order statuses that mean "the buyer turned the quote into an order".
	 */
	const CONVERTED_STATUSES = array( 'processing', 'completed' );

	/**
	 * Statuses that only the quote flow produces.
	 */
	const QUOTE_STATUSES = array( 'quote-request', 'quote-sent' );

	/**
	 * Window used for the conversion rate / status distribution.
	 */
	const PERIOD_DAYS = 30;

	/**
	 * Bars shown in the trend chart.
	 */
	const CHART_DAYS = 14;

	/**
	 * Rows listed in "waiting for review".
	 */
	const PENDING_ROWS = 6;

	/**
	 * Safety cap for the object scan of the period window. Every order in the
	 * window has to be inspected because a quote that was paid for looks like
	 * a regular paid order, so the scan stops here on extremely busy stores
	 * and the numbers then describe the most recent orders only.
	 */
	const SCAN_LIMIT = 2000;

	/**
	 * Whether the last scan hit SCAN_LIMIT.
	 *
	 * @var bool
	 */
	protected $truncated = false;

	protected static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Short Persian weekday names, indexed by PHP's "w" (0 = Sunday).
	 */
	private function weekday_labels() {
		return array(
			__( 'یک', 'woo-smart-quote' ),
			__( 'دو', 'woo-smart-quote' ),
			__( 'سه', 'woo-smart-quote' ),
			__( 'چهار', 'woo-smart-quote' ),
			__( 'پنج', 'woo-smart-quote' ),
			__( 'جمعه', 'woo-smart-quote' ),
			__( 'شنبه', 'woo-smart-quote' ),
		);
	}

	/**
	 * Shift a local Y-m-d date by a number of days without touching the
	 * server timezone (mixing strtotime() with gmdate() shifts dates around
	 * midnight, so the arithmetic is done in UTC on purpose).
	 *
	 * @param string $ymd   Date as Y-m-d.
	 * @param int    $days  Days to add (negative to go back).
	 * @return string
	 */
	public function shift_date( $ymd, $days ) {
		$parts = array_map( 'intval', explode( '-', $ymd ) );
		if ( 3 !== count( $parts ) ) {
			return $ymd;
		}

		return gmdate( 'Y-m-d', gmmktime( 0, 0, 0, $parts[1], $parts[2] + (int) $days, $parts[0] ) );
	}

	/**
	 * Today's date in site time.
	 *
	 * @return string Y-m-d
	 */
	public function today() {
		return gmdate( 'Y-m-d', current_time( 'timestamp' ) );
	}

	/**
	 * First day of the current week in site time, following the site's
	 * "week starts on" setting (Saturday for Iranian installs).
	 *
	 * @param string $today Y-m-d.
	 * @return string Y-m-d
	 */
	public function week_start( $today ) {
		$start_of_week = (int) get_option( 'start_of_week', 6 );
		if ( $start_of_week < 0 || $start_of_week > 6 ) {
			$start_of_week = 6;
		}

		$parts = array_map( 'intval', explode( '-', $today ) );
		$dow   = (int) gmdate( 'w', gmmktime( 12, 0, 0, $parts[1], $parts[2], $parts[0] ) );
		$back  = ( $dow - $start_of_week + 7 ) % 7;

		return $this->shift_date( $today, -$back );
	}

	/**
	 * Whether an order belongs to the quote flow.
	 *
	 * @param WC_Order|null $order Order object.
	 * @return bool
	 */
	public function is_quote_order( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		if ( in_array( $order->get_status(), self::QUOTE_STATUSES, true ) ) {
			return true;
		}

		return '' !== trim( (string) $order->get_meta( '_wsq_customer_phone' ) );
	}

	/**
	 * Fetch every order created since a local date and keep the quote ones.
	 *
	 * @param string $since Y-m-d (inclusive).
	 * @return WC_Order[]
	 */
	public function get_quotes_since( $since ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$limit         = (int) apply_filters( 'wsq_stats_scan_limit', self::SCAN_LIMIT );
		$this->truncated = false;

		$orders = wc_get_orders(
			array(
				'limit'        => $limit,
				'orderby'      => 'date',
				'order'        => 'DESC',
				'date_created' => '>=' . $since,
			)
		);

		if ( ! is_array( $orders ) ) {
			return array();
		}

		if ( $limit > 0 && count( $orders ) >= $limit ) {
			$this->truncated = true;
		}

		$quotes = array();
		foreach ( $orders as $order ) {
			if ( $this->is_quote_order( $order ) ) {
				$quotes[] = $order;
			}
		}

		return $quotes;
	}

	/**
	 * Local Y-m-d of an order's creation date.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	private function order_date( $order ) {
		$created = $order->get_date_created();
		if ( ! $created ) {
			return '';
		}

		return $created->format( 'Y-m-d' );
	}

	/**
	 * Count the backlog of quotes waiting for a price.
	 *
	 * @return array{total:int,oldest_days:int|null,orders:array}
	 */
	public function get_pending() {
		$data = array(
			'total'       => 0,
			'oldest_days' => null,
			'orders'      => array(),
		);

		if ( ! class_exists( 'WC_Order_Query' ) ) {
			return $data;
		}

		$query  = new WC_Order_Query(
			array(
				'status'   => array( 'quote-request' ),
				'limit'    => self::PENDING_ROWS,
				'orderby'  => 'date',
				'order'    => 'ASC',
				'paginate' => true,
			)
		);
		$result = $query->get_orders();

		if ( ! is_object( $result ) || ! isset( $result->orders ) ) {
			return $data;
		}

		$data['total'] = isset( $result->total ) ? (int) $result->total : count( $result->orders );

		$now = current_time( 'timestamp' );
		foreach ( $result->orders as $order ) {
			$created  = $order->get_date_created();
			$waiting  = $created ? max( 0, (int) floor( ( $now - $created->getTimestamp() ) / DAY_IN_SECONDS ) ) : null;

			$data['orders'][] = array(
				'id'        => $order->get_id(),
				'number'    => $order->get_order_number(),
				'buyer'     => $order->get_formatted_billing_full_name(),
				'phone'     => (string) $order->get_meta( '_wsq_customer_phone' ),
				'items'     => count( $order->get_items() ),
				'total'     => (float) $order->get_total(),
				'created'   => $created ? $created->format( 'Y-m-d H:i' ) : '',
				'days'      => $waiting,
				'edit_url'  => $order->get_edit_order_url(),
			);
		}

		if ( ! empty( $data['orders'][0]['days'] ) ) {
			$data['oldest_days'] = $data['orders'][0]['days'];
		}

		return $data;
	}

	/**
	 * Everything the dashboard needs, in one call.
	 *
	 * @return array
	 */
	public function get_summary() {
		$today      = $this->today();
		$week_start = $this->week_start( $today );
		$period_from = $this->shift_date( $today, -( self::PERIOD_DAYS - 1 ) );

		// One query covers the 30-day window; the chart, today and the week
		// buckets are all cut out of it.
		$quotes = $this->get_quotes_since( $period_from );

		$per_day = array();
		$status_counts = array(
			'quote-request' => 0,
			'quote-sent'    => 0,
			'processing'    => 0,
			'completed'     => 0,
			'other'         => 0,
		);
		$converted   = 0;
		$sent        = 0;
		$revenue     = 0.0;

		foreach ( $quotes as $order ) {
			$date = $this->order_date( $order );
			if ( '' === $date ) {
				continue;
			}

			$status = $order->get_status();
			$is_converted = in_array( $status, self::CONVERTED_STATUSES, true );

			if ( ! isset( $per_day[ $date ] ) ) {
				$per_day[ $date ] = array(
					'total'     => 0,
					'converted' => 0,
				);
			}
			$per_day[ $date ]['total']++;
			if ( $is_converted ) {
				$per_day[ $date ]['converted']++;
				$converted++;
				$revenue += (float) $order->get_total();
			}

			if ( 'quote-sent' === $status ) {
				$sent++;
			}

			if ( isset( $status_counts[ $status ] ) ) {
				$status_counts[ $status ]++;
			} else {
				$status_counts['other']++;
			}
		}

		$total = count( $quotes );

		// Today.
		$today_total = isset( $per_day[ $today ] ) ? (int) $per_day[ $today ]['total'] : 0;
		$yesterday   = $this->shift_date( $today, -1 );
		$yest_total  = isset( $per_day[ $yesterday ] ) ? (int) $per_day[ $yesterday ]['total'] : 0;

		// This week and the week before (both inside the 30-day window).
		$week_total = 0;
		for ( $i = 0; $i < 7; $i++ ) {
			$day = $this->shift_date( $week_start, $i );
			if ( isset( $per_day[ $day ] ) ) {
				$week_total += (int) $per_day[ $day ]['total'];
			}
		}

		$prev_week_start = $this->shift_date( $week_start, -7 );
		$prev_week_total = 0;
		for ( $i = 0; $i < 7; $i++ ) {
			$day = $this->shift_date( $prev_week_start, $i );
			if ( isset( $per_day[ $day ] ) ) {
				$prev_week_total += (int) $per_day[ $day ]['total'];
			}
		}

		// Trend chart: the last CHART_DAYS days, oldest first.
		$weekdays = $this->weekday_labels();
		$max      = 0;
		$chart    = array();
		for ( $i = self::CHART_DAYS - 1; $i >= 0; $i-- ) {
			$day   = $this->shift_date( $today, -$i );
			$parts = array_map( 'intval', explode( '-', $day ) );
			$ts    = gmmktime( 12, 0, 0, $parts[1], $parts[2], $parts[0] );
			$jalali = WSQ_PDF_Generator::instance()->format_jalali_date( $ts );
			$jalali_parts = explode( '/', $jalali );

			$count = isset( $per_day[ $day ] ) ? (int) $per_day[ $day ]['total'] : 0;
			$conv  = isset( $per_day[ $day ] ) ? (int) $per_day[ $day ]['converted'] : 0;
			$max   = max( $max, $count );

			$chart[] = array(
				'date'      => $day,
				'jalali'    => $jalali,
				'day'       => isset( $jalali_parts[2] ) ? (int) $jalali_parts[2] : 0,
				'month'     => isset( $jalali_parts[1] ) ? (int) $jalali_parts[1] : 0,
				'weekday'   => $weekdays[ (int) gmdate( 'w', $ts ) ],
				'total'     => $count,
				'converted' => $conv,
				'is_today'  => ( $day === $today ),
			);
		}

		$rate = $total > 0 ? ( $converted / $total ) * 100 : 0.0;

		return array(
			'today'      => array(
				'current'  => $today_total,
				'previous' => $yest_total,
				'delta'    => $this->delta( $today_total, $yest_total ),
			),
			'week'       => array(
				'current'    => $week_total,
				'previous'   => $prev_week_total,
				'delta'      => $this->delta( $week_total, $prev_week_total ),
				'starts'     => $week_start,
			),
			'pending'    => $this->get_pending(),
			'conversion' => array(
				'total'     => $total,
				'converted' => $converted,
				'sent'      => $sent,
				'rate'      => $rate,
				'revenue'   => $revenue,
				'days'      => self::PERIOD_DAYS,
			),
			'chart'      => $chart,
			'chart_max'  => max( 1, $max ),
			'truncated'  => (bool) $this->truncated,
			'statuses'   => $status_counts,
			'period'     => array(
				'from' => $period_from,
				'to'   => $today,
			),
		);
	}

	/**
	 * Percent change from the previous period, or null when there is no
	 * meaningful baseline to compare against.
	 *
	 * @param int $current  Current value.
	 * @param int $previous Previous value.
	 * @return int|null
	 */
	private function delta( $current, $previous ) {
		if ( $previous <= 0 ) {
			return null;
		}

		return (int) round( ( ( $current - $previous ) / $previous ) * 100 );
	}
}
