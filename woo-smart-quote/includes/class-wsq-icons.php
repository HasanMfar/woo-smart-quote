<?php
/**
 * Inline SVG icon set.
 *
 * WordPress replaces every emoji in a page with an SVG image hosted on
 * s.w.org. On the plugin screens that turns decorative characters into a
 * third-party request, so every icon is shipped locally as an inline,
 * currentColor SVG instead. Icons scale with the surrounding font size and
 * inherit its colour, so they keep working in RTL, in dark admin schemes and
 * at 200% zoom.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSQ_Icons {

	/**
	 * Return an inline SVG icon.
	 *
	 * @param string $name  Icon name (see self::paths()).
	 * @param string $class Extra CSS classes.
	 * @return string SVG markup, or an empty string for an unknown name.
	 */
	public static function get( $name, $class = '' ) {
		$paths = self::paths();

		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}

		$classes = 'wsq-icon' . ( '' !== $class ? ' ' . $class : '' );

		return '<svg class="' . esc_attr( $classes ) . '" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
	}

	/**
	 * Drawable shapes keyed by icon name.
	 *
	 * @return array
	 */
	private static function paths() {
		return array(
			'warning' => '<path d="M12 4.2 21 19.5H3L12 4.2Z"/><path d="M12 9.6v4.2"/><path d="M12 16.5h.01"/>',
			'inbox'   => '<path d="M4.2 5.2h15.6l1.5 8v4.3a2.2 2.2 0 0 1-2.2 2.2H4.9a2.2 2.2 0 0 1-2.2-2.2V13.2l1.5-8Z"/><path d="M2.7 13.4h5.1l1.5 2.9h5.4l1.5-2.9h5.1"/>',
			'calendar' => '<rect x="3.6" y="5.4" width="16.8" height="15" rx="2.4"/><path d="M8.2 3.4v3.6M15.8 3.4v3.6M3.6 10.2h16.8"/>',
			'clock'   => '<circle cx="12" cy="12" r="8.4"/><path d="M12 7.4V12l3.2 2"/>',
			'trend'   => '<path d="M4 19.5V4.5M4 19.5h16"/><path d="M7.6 15.6 11.6 11l3 2.4 5-6"/>',
			'refresh' => '<path d="M20.4 12a8.4 8.4 0 1 1-2.5-6"/><path d="M20.4 4.2v5.6h-5.6"/>',
			'bell'    => '<path d="M18 15.8V11a6 6 0 1 0-12 0v4.8L4.2 18.4h15.6L18 15.8Z"/><path d="M10 21h4"/>',
			'trash'   => '<path d="M4.4 6.6h15.2"/><path d="M9.6 6.6V4.6h4.8v2"/><path d="M6.6 6.6 7.6 19a1.6 1.6 0 0 0 1.6 1.5h5.6A1.6 1.6 0 0 0 16.4 19l1-12.4"/>',
			'user'    => '<circle cx="12" cy="8.6" r="3.9"/><path d="M4.6 20.2a7.4 7.4 0 0 1 14.8 0"/>',
			'bot'     => '<rect x="4.2" y="8.2" width="15.6" height="11.3" rx="3"/><path d="M12 4.2v4"/><path d="M9.2 13.4h.01M14.8 13.4h.01"/><path d="M2.6 11.8v3.2M21.4 11.8v3.2"/>',
			'chart'   => '<path d="M4.4 20V11.4M9.4 20V4.6M14.4 20v-6.2M19.4 20V8.2"/><path d="M3.4 20.6h17.2"/>',
			'file'    => '<path d="M13.8 3.4H7.4a2 2 0 0 0-2 2v13.2a2 2 0 0 0 2 2h9.2a2 2 0 0 0 2-2V8.2l-4.8-4.8Z"/><path d="M13.8 3.4v4.8h4.8"/><path d="M8.6 13.2h6.8M8.6 16.4h4.4"/>',
			'bolt'    => '<path d="M13.4 3 6.6 13.4h4.6L10.6 21l6.8-10.4h-4.6L13.4 3Z"/>',
			'phone'   => '<rect x="6.6" y="2.6" width="10.8" height="18.8" rx="2.6"/><path d="M8.4 6.6h7.2"/><path d="M10.6 18.6h2.8"/>',
			'target'  => '<circle cx="12" cy="12" r="8.4"/><circle cx="12" cy="12" r="4.4"/><circle cx="12" cy="12" r="1.1"/>',
			'search'  => '<circle cx="10.8" cy="10.8" r="6.6"/><path d="m15.6 15.6 4.4 4.4"/>',
			'palette' => '<path d="M12 3.6a8.4 8.4 0 0 0 0 16.8c1.3 0 1.9-.9 1.9-1.9s-.6-1.9-.6-1.9.5-1 1.6-1h2.3a3.2 3.2 0 0 0 3.2-3.2c0-4.5-4.2-8.8-8.4-8.8Z"/><path d="M8.2 10.4h.01M11.2 7.6h.01M15.2 8.6h.01"/>',
			'bulb'    => '<path d="M9.4 17.4h5.2M10.4 20.6h3.2"/><path d="M12 3.4a6 6 0 0 0-3.4 10.9v3.1h6.8v-3.1A6 6 0 0 0 12 3.4Z"/>',
			'clipboard' => '<path d="M9.4 4.6H7.4a2 2 0 0 0-2 2v12.2a2 2 0 0 0 2 2h9.2a2 2 0 0 0 2-2V6.6a2 2 0 0 0-2-2h-2"/><rect x="9.4" y="2.6" width="5.2" height="4" rx="1.6"/><path d="M8.6 12.4h6.8M8.6 15.6h4.4"/>',
			'check'   => '<path d="M4.8 12.6l4.6 4.6L19.4 6.8"/>',
			'bag'     => '<path d="M5.6 8.4h12.8l1 10.4a2.1 2.1 0 0 1-2.1 2.3H6.7a2.1 2.1 0 0 1-2.1-2.3l1-10.4Z"/><path d="M8.7 8.4V6.7a3.3 3.3 0 0 1 6.6 0v1.7"/>',
			'sparkles' => '<path d="M11.4 3.4 13 8.2l4.8 1.6-4.8 1.6-1.6 4.8-1.6-4.8L5 9.8l4.8-1.6 1.6-4.8Z"/><path d="M18.2 15.2l.7 1.9 1.9.7-1.9.7-.7 1.9-.7-1.9-1.9-.7 1.9-.7.7-1.9Z"/>',
			'seal'    => '<circle cx="12" cy="12" r="8.6"/><circle cx="12" cy="12" r="6.2"/><path d="M9.6 12.2l1.7 1.7 3.3-3.6"/>',
			'ledger'  => '<rect x="4.6" y="3.4" width="14.8" height="17.2" rx="2.2"/><path d="M8.2 3.4v17.2M11.4 8.2h5M11.4 12h5M11.4 15.8h5"/>',
			'chat'    => '<path d="M20.6 11.6a7.6 7.6 0 0 1-11 6.8l-4.8 1.7 1.6-4.6a7.6 7.6 0 1 1 14.2-3.9Z"/>',
			'send'    => '<path d="M20.8 3.4 10.6 13.6"/><path d="M20.8 3.4 14.6 21l-4-7-7-4 17.2-6.6Z"/>',
			'mail'    => '<rect x="3" y="5.5" width="18" height="13" rx="2.5"/><path d="M4 7.5l7.1 5.3a1.5 1.5 0 0 0 1.8 0L20 7.5"/>',
			'link'    => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
			'rocket'  => '<path d="M12 3.2c2.6 1.9 4.2 4.8 4.2 8v3.4L12 19l-4.2-4.4V11.2c0-3.2 1.6-6.1 4.2-8Z"/><circle cx="12" cy="10.2" r="1.7"/><path d="M7.8 13.2 5 17l3.6-.9M16.2 13.2 19 17l-3.6-.9"/><path d="M12 19v2.4"/>',
		);
	}
}
