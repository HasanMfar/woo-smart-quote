/**
 * Shared clipboard helper for the Nerkhnegar admin screens.
 *
 * Returns a Promise<boolean> that resolves only when the text was actually
 * copied, so callers can show honest success/failure feedback.
 */
( function ( window, document ) {
	'use strict';

	function legacyCopy( text ) {
		var textarea = document.createElement( 'textarea' );
		var active   = document.activeElement;
		var copied   = false;

		textarea.value = text;
		textarea.setAttribute( 'readonly', 'readonly' );
		textarea.style.cssText = 'position:fixed;top:0;left:-9999px;opacity:0;';
		document.body.appendChild( textarea );
		textarea.select();
		textarea.setSelectionRange( 0, textarea.value.length );

		try {
			copied = document.execCommand( 'copy' );
		} catch ( error ) {
			copied = false;
		}

		document.body.removeChild( textarea );
		if ( active && typeof active.focus === 'function' ) {
			active.focus();
		}

		return copied;
	}

	window.wsqCopyText = function ( text ) {
		text = String( text === null || typeof text === 'undefined' ? '' : text );

		if ( navigator.clipboard && navigator.clipboard.writeText && window.isSecureContext ) {
			return navigator.clipboard.writeText( text ).then(
				function () {
					return true;
				},
				function () {
					return legacyCopy( text );
				}
			);
		}

		return Promise.resolve( legacyCopy( text ) );
	};
}( window, document ) );
