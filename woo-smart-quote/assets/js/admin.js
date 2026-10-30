/**
 * Nerkhnegar Admin JavaScript
 *
 * - Test-connection buttons with loading / success / error status pills.
 * - Click-to-insert template tokens.
 * - Bale webhook register / delete buttons.
 * - Inbox reply, copy, and read actions.
 * - Per-form unsaved-changes state.
 *
 * All user-facing strings come from wsq_admin_params.i18n.
 */
jQuery( document ).ready( function ( $ ) {
	'use strict';

	var params = ( typeof wsq_admin_params !== 'undefined' ) ? wsq_admin_params : { ajax_url: '', nonce: '', i18n: {} };
	var i18n = params.i18n || {};
	var requestTimeout = 20000;
	var liveTimer = null;

	function t( key, fallback ) {
		return ( typeof i18n[ key ] === 'string' && i18n[ key ] !== '' ) ? i18n[ key ] : ( fallback || '' );
	}

	function announce( message ) {
		var $live = $( '#wsq-admin-live' );
		if ( ! $live.length || ! message ) {
			return;
		}
		$live.text( '' );
		window.clearTimeout( liveTimer );
		liveTimer = window.setTimeout( function () {
			$live.text( message );
		}, 60 );
	}

	function normalizeDigits( value ) {
		if ( ! value ) {
			return '';
		}
		return String( value )
			.replace( /[۰-۹]/g, function ( digit ) {
				return String.fromCharCode( digit.charCodeAt( 0 ) - 1728 );
			} )
			.replace( /[٠-٩]/g, function ( digit ) {
				return String.fromCharCode( digit.charCodeAt( 0 ) - 1584 );
			} );
	}

	function jsonMessage( response ) {
		return ( response && response.data && response.data.message ) ? response.data.message : '';
	}

	function xhrMessage( xhr ) {
		try {
			return jsonMessage( JSON.parse( xhr.responseText ) );
		} catch ( error ) {
			return '';
		}
	}

	function copyText( text ) {
		if ( typeof window.wsqCopyText === 'function' ) {
			return window.wsqCopyText( text );
		}
		return Promise.resolve( false );
	}

	function setStatus( $status, state, text ) {
		if ( ! $status || ! $status.length ) {
			return;
		}
		$status.removeClass( 'is-idle success error loading' );
		if ( 'idle' === state ) {
			$status.addClass( 'is-idle' ).empty().removeAttr( 'role' );
			return;
		}
		var icon = '';
		if ( 'loading' === state ) {
			icon = '<span class="wsq-spinner" aria-hidden="true"></span>';
		} else if ( 'success' === state ) {
			icon = '<span aria-hidden="true">&#10003;</span> ';
		} else if ( 'error' === state ) {
			icon = '<span aria-hidden="true">&#10005;</span> ';
		}
		$status.addClass( state ).attr( 'role', 'status' ).html( icon + $( '<span>' ).text( text || '' ).html() );
	}

	/* ------------------------------------------------------------------
	 * Test connection
	 * ------------------------------------------------------------------ */
	$( '.wsq-test-btn' ).on( 'click', function ( e ) {
		e.preventDefault();

		var $btn       = $( this );
		var messenger  = $btn.data( 'messenger' );
		var $status    = $( '#status-' + messenger );
		var token      = $( '#wsq_' + messenger + '_token' ).val();
		var chatIdsRaw = $( '#wsq_' + messenger + '_default_chat_ids' ).val();
		var firstChatId = '';

		String( chatIdsRaw || '' ).split( /\r\n|\r|\n/ ).some( function ( line ) {
			var trimmed = $.trim( line );
			if ( trimmed.length ) {
				firstChatId = trimmed;
				return true;
			}
			return false;
		} );

		if ( ! token || ! firstChatId ) {
			setStatus( $status, 'error', t( 'no_chat_id', 'Enter a token and at least one chat ID.' ) );
			return;
		}

		$btn.prop( 'disabled', true ).attr( 'aria-busy', 'true' );
		setStatus( $status, 'loading', t( 'testing', 'Sending test message…' ) );

		$.ajax( {
			url: params.ajax_url,
			type: 'POST',
			dataType: 'json',
			timeout: requestTimeout,
			data: {
				action: 'wsq_test_connection',
				nonce: params.nonce,
				messenger: messenger,
				token: token,
				chat_id: firstChatId
			}
		} ).done( function ( response ) {
			if ( response && response.success ) {
				setStatus( $status, 'success', jsonMessage( response ) || t( 'test_ok', 'Test message sent.' ) );
			} else {
				setStatus( $status, 'error', t( 'test_failed', 'Test failed: ' ) + jsonMessage( response ) );
			}
		} ).fail( function ( xhr, status ) {
			var message = 'timeout' === status ? t( 'test_timeout', 'The server did not respond. Try again.' ) : ( xhrMessage( xhr ) || t( 'request_failed', 'Server connection failed.' ) );
			setStatus( $status, 'error', t( 'test_failed', 'Test failed: ' ) + message );
		} ).always( function () {
			$btn.prop( 'disabled', false ).removeAttr( 'aria-busy' );
		} );
	} );

	// A status pill describes the previous values; clear it when they change.
	$( '#wsq_bale_token, #wsq_bale_default_chat_ids' ).on( 'input', function () {
		setStatus( $( '#status-bale' ), 'idle' );
	} );
	$( '#wsq_eitaa_token, #wsq_eitaa_default_chat_ids' ).on( 'input', function () {
		setStatus( $( '#status-eitaa' ), 'idle' );
	} );

	/* ------------------------------------------------------------------
	 * Bale webhook
	 * ------------------------------------------------------------------ */
	$( '.wsq-webhook-btn' ).on( 'click', function ( e ) {
		e.preventDefault();

		var $btn    = $( this );
		var $status = $( '#status-webhook' );
		var action  = $btn.data( 'action' ) || 'set';

		if ( 'delete' === action && ! window.confirm( t( 'webhook_delete_confirm', 'Delete the Bale webhook? Messages will be fetched by polling instead.' ) ) ) {
			return;
		}

		$btn.prop( 'disabled', true ).attr( 'aria-busy', 'true' );
		setStatus( $status, 'loading', t( 'webhook_loading', 'Contacting Bale…' ) );

		$.ajax( {
			url: params.ajax_url,
			type: 'POST',
			dataType: 'json',
			timeout: requestTimeout,
			data: {
				action: 'wsq_webhook_action',
				nonce: params.nonce,
				action_type: action
			}
		} ).done( function ( response ) {
			if ( response && response.success ) {
				setStatus( $status, 'success', jsonMessage( response ) || 'OK' );
				if ( response.data && response.data.url ) {
					$( '#wsq-webhook-url' ).text( response.data.url );
				}
			} else {
				setStatus( $status, 'error', t( 'error', 'Error' ) + ': ' + jsonMessage( response ) );
			}
		} ).fail( function ( xhr, status ) {
			var message = 'timeout' === status ? t( 'test_timeout', 'The server did not respond. Try again.' ) : ( xhrMessage( xhr ) || t( 'request_failed', 'Server connection failed.' ) );
			setStatus( $status, 'error', t( 'error', 'Error' ) + ': ' + message );
		} ).always( function () {
			$btn.prop( 'disabled', false ).removeAttr( 'aria-busy' );
		} );
	} );

	/* ------------------------------------------------------------------
	 * Inbox
	 * ------------------------------------------------------------------ */
	function currentInboxFilter() {
		var $active = $( '.wsq-inbox-filter.is-active' );
		return $active.length ? String( $active.data( 'filter' ) ) : 'all';
	}

	function getUnreadCount() {
		var $count = $( '.wsq-inbox-filter[data-filter="unread"] [data-count="unread"]' );
		var value  = parseInt( normalizeDigits( $count.text() ).replace( /[^0-9]/g, '' ), 10 );
		return isNaN( value ) ? 0 : value;
	}

	function setUnreadCount( count ) {
		var value = Math.max( 0, parseInt( count, 10 ) || 0 );
		$( '.wsq-inbox-filter[data-filter="unread"] [data-count="unread"]' ).text( String( value ) );
		if ( 0 === value ) {
			$( '.wsq-read-all-btn' ).remove();
		}
	}

	function showInboxEmpty() {
		var filter  = currentInboxFilter();
		var message = 'unread' === filter
			? t( 'inbox_empty_unread', 'All messages have been read. Nothing new to review.' )
			: t( 'inbox_empty_all', 'No messages received yet.' );
		var $inbox  = $( '.wsq-inbox' );
		if ( ! $inbox.length ) {
			return;
		}
		$inbox.replaceWith( $( '<p class="description wsq-inbox-empty" data-wsq-inbox-empty>' ).text( message ) );
	}

	function removeInboxRow( $row ) {
		$row.remove();
		if ( ! $( '.wsq-inbox-item' ).length ) {
			showInboxEmpty();
		}
	}

	function clearReplyError( $input, $error ) {
		$error.prop( 'hidden', true ).text( '' );
		$input.removeAttr( 'aria-invalid' );
	}

	function showReplyError( $input, $error, message ) {
		$error.text( message ).prop( 'hidden', false );
		$input.attr( 'aria-invalid', 'true' );
		announce( message );
		$input.trigger( 'focus' );
	}

	function sendReply( $btn ) {
		var $row      = $btn.closest( '.wsq-inbox-item' );
		var $input    = $row.find( '.wsq-reply-input' );
		var $error    = $row.find( '.wsq-reply-error' );
		var chatId    = $input.data( 'chat' );
		var messenger = $input.data( 'messenger' );
		var text      = $.trim( $input.val() );
		var wasUnread = $row.hasClass( 'is-unread' );
		var label     = $btn.data( 'label' );

		if ( ! label ) {
			label = $.trim( $btn.text() );
			$btn.data( 'label', label );
		}

		if ( ! text ) {
			showReplyError( $input, $error, t( 'reply_required', 'Write a reply first.' ) );
			return;
		}

		clearReplyError( $input, $error );
		$btn.prop( 'disabled', true ).attr( 'aria-busy', 'true' ).text( t( 'sending', 'Sending…' ) );

		$.ajax( {
			url: params.ajax_url,
			type: 'POST',
			dataType: 'json',
			timeout: requestTimeout,
			data: {
				action: 'wsq_inbox_reply',
				nonce: params.nonce,
				messenger: messenger,
				chat_id: chatId,
				reply_text: text
			}
		} ).done( function ( response ) {
			if ( response && response.success ) {
				$input.val( '' );
				if ( wasUnread ) {
					setUnreadCount( Math.max( 0, getUnreadCount() - 1 ) );
				}
				if ( 'unread' === currentInboxFilter() ) {
					removeInboxRow( $row );
				} else {
					$row.removeClass( 'is-unread' ).find( '.wsq-read-btn' ).remove();
				}
				announce( t( 'reply_ok', 'Reply sent.' ) );
			} else {
				showReplyError( $input, $error, t( 'reply_failed', 'Could not send the reply: ' ) + jsonMessage( response ) );
			}
		} ).fail( function ( xhr, status ) {
			var message = 'timeout' === status ? t( 'test_timeout', 'The server did not respond. Try again.' ) : ( xhrMessage( xhr ) || t( 'request_failed', 'Server connection failed.' ) );
			showReplyError( $input, $error, t( 'reply_failed', 'Could not send the reply: ' ) + message );
		} ).always( function () {
			$btn.prop( 'disabled', false ).removeAttr( 'aria-busy' ).text( label );
		} );
	}

	$( document ).on( 'click', '.wsq-reply-btn', function ( e ) {
		e.preventDefault();
		sendReply( $( this ) );
	} );

	$( document ).on( 'keydown', '.wsq-reply-input', function ( e ) {
		if ( 'Enter' === e.key ) {
			e.preventDefault();
			sendReply( $( this ).closest( '.wsq-inbox-item' ).find( '.wsq-reply-btn' ) );
		}
	} );

	$( document ).on( 'click', '.wsq-copy-btn', function ( e ) {
		e.preventDefault();

		var $btn  = $( this );
		var text  = String( $btn.data( 'copy' ) || '' );
		var label = $btn.data( 'label' );

		if ( ! label ) {
			label = $.trim( $btn.text() );
			$btn.data( 'label', label );
		}

		function finish( copied ) {
			if ( copied ) {
				$btn.addClass( 'is-copied' ).text( t( 'copied', 'Copied' ) );
				announce( t( 'copied', 'Copied' ) );
				window.setTimeout( function () {
					$btn.removeClass( 'is-copied' ).text( label );
				}, 1200 );
			} else {
				$btn.attr( 'title', t( 'copy_failed', 'Copy failed. Select the text manually.' ) );
				announce( t( 'copy_failed', 'Copy failed. Select the text manually.' ) );
			}
		}

		copyText( text ).then( finish );
	} );

	$( document ).on( 'click', '.wsq-read-btn', function ( e ) {
		e.preventDefault();

		var $btn = $( this );
		var id   = parseInt( $btn.data( 'id' ), 10 ) || 0;
		var $row = $btn.closest( '.wsq-inbox-item' );

		$btn.prop( 'disabled', true ).attr( 'aria-busy', 'true' );

		$.ajax( {
			url: params.ajax_url,
			type: 'POST',
			dataType: 'json',
			timeout: requestTimeout,
			data: {
				action: 'wsq_inbox_read',
				nonce: params.nonce,
				ids: [ id ],
				all: '0'
			}
		} ).done( function ( response ) {
			if ( response && response.success ) {
				if ( response.data && typeof response.data.unread !== 'undefined' ) {
					setUnreadCount( response.data.unread );
				}
				if ( 'unread' === currentInboxFilter() ) {
					removeInboxRow( $row );
				} else {
					$row.removeClass( 'is-unread' );
					$btn.remove();
				}
				announce( t( 'read_ok', 'Message marked as read.' ) );
			} else {
				announce( t( 'read_failed', 'Could not update the messages.' ) );
			}
		} ).fail( function () {
			announce( t( 'read_failed', 'Could not update the messages.' ) );
		} ).always( function () {
			$btn.prop( 'disabled', false ).removeAttr( 'aria-busy' );
		} );
	} );

	$( document ).on( 'click', '.wsq-read-all-btn', function ( e ) {
		e.preventDefault();

		if ( ! window.confirm( t( 'read_all_confirm', 'Mark every unread message as read?' ) ) ) {
			return;
		}

		var $btn = $( this );
		$btn.prop( 'disabled', true ).attr( 'aria-busy', 'true' );

		$.ajax( {
			url: params.ajax_url,
			type: 'POST',
			dataType: 'json',
			timeout: requestTimeout,
			data: {
				action: 'wsq_inbox_read',
				nonce: params.nonce,
				ids: [],
				all: '1'
			}
		} ).done( function ( response ) {
			if ( response && response.success ) {
				setUnreadCount( 0 );
				$( '.wsq-read-all-btn' ).remove();
				if ( 'unread' === currentInboxFilter() ) {
					$( '.wsq-inbox-item' ).remove();
					showInboxEmpty();
				} else {
					$( '.wsq-inbox-item' ).removeClass( 'is-unread' );
					$( '.wsq-read-btn' ).remove();
				}
				announce( t( 'read_all_ok', 'All messages marked as read.' ) );
			} else {
				announce( t( 'read_failed', 'Could not update the messages.' ) );
			}
		} ).fail( function () {
			announce( t( 'read_failed', 'Could not update the messages.' ) );
		} ).always( function () {
			$btn.prop( 'disabled', false ).removeAttr( 'aria-busy' );
		} );
	} );

	/* ------------------------------------------------------------------
	 * Template tokens
	 * ------------------------------------------------------------------ */
	function insertTokenIntoTextarea( tokenElement ) {
		var $token = $( tokenElement );
		var token  = String( $token.data( 'token' ) || '' );
		if ( ! token ) {
			return;
		}

		var $target = $token.closest( 'td' ).find( 'textarea' ).first();
		if ( ! $target.length ) {
			$target = $( '.wsq-admin-wrap textarea.large-text' ).first();
		}
		if ( ! $target.length ) {
			return;
		}

		var element = $target[ 0 ];
		var position = typeof element.selectionStart === 'number' ? element.selectionStart : element.value.length;
		var before   = element.value.slice( 0, position );
		var after    = element.value.slice( position );
		var glue     = ( before && ! /\s$/.test( before ) ) ? ' ' : '';

		element.value = before + glue + token + after;
		element.focus();
		element.selectionStart = element.selectionEnd = position + glue.length + token.length;
		$target.trigger( 'input' );

		$token.addClass( 'wsq-token-copied' );
		window.setTimeout( function () {
			$token.removeClass( 'wsq-token-copied' );
		}, 900 );

		announce( t( 'token_inserted', '%s inserted into the template.' ).replace( '%s', token ) );
	}

	$( document ).on( 'mousedown', '.wsq-token[data-token]', function ( e ) {
		e.preventDefault();
		insertTokenIntoTextarea( this );
	} );

	$( document ).on( 'keydown', '.wsq-token[data-token]', function ( e ) {
		if ( 'Enter' === e.key || ' ' === e.key ) {
			e.preventDefault();
			insertTokenIntoTextarea( this );
		}
	} );

	/* ------------------------------------------------------------------
	 * Live previews (display + PDF stamp)
	 * ------------------------------------------------------------------ */
	$( document ).on( 'input', '#wsq_button_text_input', function () {
		var value = $.trim( $( this ).val() ) || t( 'button_text_default', 'Add to quote list' );
		$( '#wsq-preview-btn-text' ).text( value );
	} );

	$( document ).on( 'input', '#wsq_stamp_text_input', function () {
		var value = $.trim( $( this ).val() ) || t( 'stamp_text_default', 'Approved — formal quote' );
		$( '#wsq-stamp-text-preview' ).text( value );
	} );

	function syncStampPreview() {
		var $checkbox = $( '#wsq_show_digital_stamp_toggle' );
		var $input = $( '#wsq_company_seal_image' );
		var $card = $( '.wsq-stamp-preview-card' );
		if ( $card.length ) {
			if ( $checkbox.length ) {
				$card.toggleClass( 'is-disabled', ! $checkbox.is( ':checked' ) );
			}
			var url = $.trim( $input.val() );
			var $sealDemo = $card.find( '.wsq-digital-seal-demo' );
			var $img = $card.find( '.wsq-seal-img-preview' );
			if ( url ) {
				if ( ! $img.length ) {
					$img = $( '<img class="wsq-seal-img-preview" alt="Seal" style="max-height:84px;max-width:110px;object-fit:contain;border-radius:4px;" />' );
					$card.prepend( $img );
				}
				$img.attr( 'src', url ).show();
				$sealDemo.hide();
			} else {
				if ( $img.length ) {
					$img.hide();
				}
				$sealDemo.show();
			}
		}
	}
	$( document ).on( 'change', '#wsq_show_digital_stamp_toggle', syncStampPreview );
	$( document ).on( 'input change', '#wsq_company_seal_image', syncStampPreview );
	syncStampPreview();

	function syncSignaturePreview() {
		var $checkbox = $( '#wsq_show_digital_signature_toggle' );
		var $input = $( '#wsq_company_signature_image' );
		var $card = $( '.wsq-signature-preview-card' );
		if ( $card.length ) {
			if ( $checkbox.length ) {
				$card.toggleClass( 'is-disabled', ! $checkbox.is( ':checked' ) );
			}
			var url = $.trim( $input.val() );
			var $sigDemo = $card.find( '.wsq-digital-signature-demo' );
			var $img = $card.find( '.wsq-signature-img-preview' );
			if ( url ) {
				if ( ! $img.length ) {
					$img = $( '<img class="wsq-signature-img-preview" alt="Signature" style="max-height:48px;max-width:120px;object-fit:contain;border-radius:4px;" />' );
					$card.prepend( $img );
				}
				$img.attr( 'src', url ).show();
				$sigDemo.hide();
			} else {
				if ( $img.length ) {
					$img.hide();
				}
				$sigDemo.show();
			}
		}
	}
	$( document ).on( 'change', '#wsq_show_digital_signature_toggle', syncSignaturePreview );
	$( document ).on( 'input change', '#wsq_company_signature_image', syncSignaturePreview );
	syncSignaturePreview();

	// Media library file picker
	$( document ).on( 'click', '.wsq-upload-media-btn', function ( e ) {
		e.preventDefault();
		var targetSelector = $( this ).data( 'target' );
		var $target = $( targetSelector );
		if ( typeof wp !== 'undefined' && wp.media ) {
			var frame = wp.media( {
				title: t( 'choose_image', 'انتخاب یا بارگذاری تصویر' ),
				button: { text: t( 'use_image', 'استفاده از این تصویر' ) },
				multiple: false
			} );
			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				if ( attachment && attachment.url ) {
					$target.val( attachment.url ).trigger( 'input' ).trigger( 'change' );
				}
			} );
			frame.open();
		}
	} );

	function syncFloatingPreview() {
		var $checkbox = $( '#wsq_enable_floating_cart_toggle' );
		if ( $checkbox.length ) {
			$( '.wsq-preview-floating-demo' ).toggleClass( 'is-disabled', ! $checkbox.is( ':checked' ) );
		}
	}
	$( document ).on( 'change', '#wsq_enable_floating_cart_toggle', syncFloatingPreview );
	syncFloatingPreview();

	/* ------------------------------------------------------------------
	 * Category search
	 * ------------------------------------------------------------------ */
	$( document ).on( 'input', '#wsq-cat-search', function () {
		var raw     = $.trim( $( this ).val() );
		var query   = raw.toLowerCase();
		var normal  = normalizeDigits( query );
		var $tbody  = $( '.wsq-routes-table tbody' );
		var $rows   = $tbody.find( 'tr' ).not( '.wsq-no-match-row' );
		var matched = 0;

		$( '#wsq-cat-search-clear' ).toggle( !! raw );

		if ( ! query ) {
			$rows.show();
			$tbody.find( '.wsq-no-match-row' ).remove();
			$( '#wsq-cat-search-live' ).text( '' );
			return;
		}

		$rows.each( function () {
			var $row     = $( this );
			var text     = $row.find( 'td' ).first().text().toLowerCase();
			var textNorm = normalizeDigits( text );
			if ( text.indexOf( query ) !== -1 || ( normal && textNorm.indexOf( normal ) !== -1 ) ) {
				$row.show();
				matched++;
			} else {
				$row.hide();
			}
		} );

		$tbody.find( '.wsq-no-match-row' ).remove();
		if ( 0 === matched ) {
			var empty = t( 'search_empty', 'No product categories match your search.' );
			$tbody.append( $( '<tr class="wsq-no-match-row"><td colspan="3"></td></tr>' ).find( 'td' ).text( empty ).end() );
			$( '#wsq-cat-search-live' ).text( empty );
		} else {
			$( '#wsq-cat-search-live' ).text( t( 'search_results', '%s categories found.' ).replace( '%s', matched ) );
		}
	} );

	$( document ).on( 'click', '#wsq-cat-search-clear', function ( e ) {
		e.preventDefault();
		$( '#wsq-cat-search' ).val( '' ).trigger( 'input' ).trigger( 'focus' );
	} );

	$( document ).on( 'keydown', '#wsq-cat-search', function ( e ) {
		if ( 'Escape' === e.key ) {
			$( this ).val( '' ).trigger( 'input' );
		}
	} );

	/* ------------------------------------------------------------------
	 * Unsaved changes, tracked per form
	 * ------------------------------------------------------------------ */
	$( '.wsq-admin-wrap form' ).on( 'input change', 'input[name], select[name], textarea[name]', function () {
		var $form = $( this ).closest( 'form' );
		if ( ! $form.length ) {
			return;
		}
		var wasDirty = true === $form.data( 'wsqDirty' );
		$form.data( 'wsqDirty', true ).find( '.wsq-savebar' ).addClass( 'is-dirty' );
		if ( ! wasDirty ) {
			announce( t( 'unsaved_changes', 'You have unsaved changes.' ) );
		}
	} );

	$( '.wsq-admin-wrap form' ).on( 'submit', function () {
		$( this ).data( 'wsqDirty', false ).find( '.wsq-savebar' ).removeClass( 'is-dirty' );
	} );

	/* ------------------------------------------------------------------
	 * Mobile App Pairing & Devices Management
	 * ------------------------------------------------------------------ */
	var pairingTimerInterval = null;

	function renderQrCode( text ) {
		var $qr = $( '#wsq-pairing-qrcode' );
		if ( ! $qr.length || typeof $.fn.qrcode !== 'function' ) {
			return;
		}
		// Convert UTF-8 characters (e.g. Persian store names) to byte sequences for jquery.qrcode
		var encodedText = text;
		try {
			encodedText = unescape( encodeURIComponent( text ) );
		} catch ( e ) {
			encodedText = text;
		}
		$qr.empty().qrcode( {
			render: 'canvas',
			width: 200,
			height: 200,
			text: encodedText,
			background: '#ffffff',
			foreground: '#0f172a'
		} );
	}

	function startPairingTimer( expiresAt ) {
		if ( pairingTimerInterval ) {
			clearInterval( pairingTimerInterval );
			pairingTimerInterval = null;
		}

		var $val = $( '#wsq-pairing-timer-val' );
		if ( ! $val.length ) {
			return;
		}

		function updateTimer() {
			var now = Math.floor( Date.now() / 1000 );
			var diff = Math.max( 0, expiresAt - now );
			if ( diff <= 0 ) {
				$val.text( t( 'expired', 'Expired' ) ).css( 'color', '#dc2626' );
				clearInterval( pairingTimerInterval );
				return;
			}
			var m = Math.floor( diff / 60 );
			var s = diff % 60;
			var display = ( m < 10 ? '0' + m : m ) + ':' + ( s < 10 ? '0' + s : s );
			$val.text( display ).css( 'color', '#d97706' );
		}

		updateTimer();
		pairingTimerInterval = setInterval( updateTimer, 1000 );
	}

	if ( $( '#wsq-pairing-qrcode' ).length ) {
		var payload = $( '#wsq-pairing-qrcode' ).attr( 'data-payload' );
		if ( payload ) {
			renderQrCode( payload );
		}
		var exp = parseInt( $( '#wsq-pairing-timer-box' ).attr( 'data-expires' ), 10 );
		if ( exp ) {
			startPairingTimer( exp );
		}
	}

	function renderApkQrCode( url ) {
		var $qr = $( '#wsq-apk-qrcode' );
		if ( ! $qr.length || typeof $.fn.qrcode !== 'function' || ! url ) {
			return;
		}
		var encodedUrl = url;
		try {
			encodedUrl = unescape( encodeURIComponent( url ) );
		} catch ( e ) {
			encodedUrl = url;
		}
		$qr.empty().qrcode( {
			render: 'canvas',
			width: 140,
			height: 140,
			text: encodedUrl,
			background: '#ffffff',
			foreground: '#1d2327'
		} );
	}

	if ( $( '#wsq-apk-qrcode' ).length ) {
		var apkUrl = $( '#wsq-apk-qrcode' ).attr( 'data-url' );
		if ( apkUrl ) {
			renderApkQrCode( apkUrl );
		}
	}

	// Toggle direct APK download QR Code box
	$( document ).on( 'click', '.wsq-toggle-apk-qr-btn', function ( e ) {
		e.preventDefault();
		var $btn = $( this );
		var $box = $( '#wsq-apk-qr-container' );
		var willOpen = ! $box.is( ':visible' );
		$btn.attr( 'aria-expanded', willOpen ? 'true' : 'false' );
		if ( willOpen ) {
			var currentUrl = $( '#wsq-apk-primary-link' ).attr( 'href' );
			renderApkQrCode( currentUrl );
		}
		$box.slideToggle( 200 );
	} );

	// Toggle Custom URL configuration box
	$( document ).on( 'click', '.wsq-toggle-custom-apk-btn', function ( e ) {
		e.preventDefault();
		var $btn = $( this );
		var $box = $( '#wsq-custom-apk-container' );
		var willOpen = ! $box.is( ':visible' );
		$btn.attr( 'aria-expanded', willOpen ? 'true' : 'false' );
		$box.slideToggle( 200, function () {
			if ( willOpen ) {
				$( '#wsq_companion_apk_url_input' ).trigger( 'focus' );
			}
		} );
	} );

	// Save Custom APK URL on Enter key inside input
	$( document ).on( 'keydown', '#wsq_companion_apk_url_input', function ( e ) {
		if ( e.which === 13 || e.keyCode === 13 ) {
			e.preventDefault();
			$( '.wsq-save-apk-url-btn' ).trigger( 'click' );
		}
	} );

	// Save Custom APK URL via AJAX
	$( document ).on( 'click', '.wsq-save-apk-url-btn', function ( e ) {
		e.preventDefault();
		var $btn = $( this );
		var $input = $( '#wsq_companion_apk_url_input' );
		var $status = $( '#wsq-custom-apk-status' );
		var val = $.trim( $input.val() );

		$btn.prop( 'disabled', true );
		$status.text( t( 'sending', 'در حال ذخیره…' ) ).css( 'color', '#646970' );

		$.post( params.ajax_url, {
			action: 'wsq_save_apk_url',
			nonce: params.nonce,
			apk_url: val
		}, function ( res ) {
			$btn.prop( 'disabled', false );
			if ( res && res.success ) {
				$status.text( res.data.message ).css( 'color', '#008a20' );
				if ( res.data.apk_url ) {
					$( '#wsq-apk-primary-link' ).attr( 'href', res.data.apk_url );
					$( '#wsq-apk-qrcode' ).attr( 'data-url', res.data.apk_url );
					renderApkQrCode( res.data.apk_url );
				}
				announce( res.data.message );
			} else {
				var err = ( res && res.data && res.data.message ) ? res.data.message : t( 'request_failed', 'خطا در ذخیره‌سازی.' );
				$status.text( err ).css( 'color', '#d63638' );
			}
		} ).fail( function () {
			$btn.prop( 'disabled', false );
			$status.text( t( 'request_failed', 'خطا در ارتباط با سرور.' ) ).css( 'color', '#d63638' );
		} );
	} );

	// Reset APK URL to official default
	$( document ).on( 'click', '.wsq-reset-apk-url-btn', function ( e ) {
		e.preventDefault();
		$( '#wsq_companion_apk_url_input' ).val( '' );
		$( '.wsq-save-apk-url-btn' ).trigger( 'click' );
	} );

	// Refresh Pairing Code
	$( document ).on( 'click', '.wsq-refresh-pairing-btn', function ( e ) {
		e.preventDefault();
		var $btn = $( this );
		var origHtml = $btn.html();
		$btn.prop( 'disabled', true );

		$.post( params.ajax_url, {
			action: 'wsq_refresh_pairing',
			nonce: params.nonce
		}, function ( response ) {
			$btn.prop( 'disabled', false ).html( origHtml );
			if ( response.success && response.data ) {
				$( '#wsq-pairing-code-text' ).text( response.data.pairing_code );
				$( '#wsq-pairing-timer-box' ).attr( 'data-expires', response.data.expires_at );
				renderQrCode( response.data.payload_json );
				startPairingTimer( response.data.expires_at );
				announce( response.data.message || t( 'pairing_refreshed', 'Pairing code refreshed.' ) );
			} else {
				alert( response.data && response.data.message ? response.data.message : t( 'refresh_failed', 'Failed to refresh.' ) );
			}
		} ).fail( function () {
			$btn.prop( 'disabled', false ).html( origHtml );
			alert( t( 'request_failed', 'Request failed.' ) );
		} );
	} );

	// Revoke Paired Device
	$( document ).on( 'click', '.wsq-revoke-device-btn', function ( e ) {
		e.preventDefault();
		var $btn = $( this );
		var deviceId = $btn.data( 'device-id' );
		if ( ! deviceId ) {
			return;
		}

		if ( ! window.confirm( t( 'revoke_confirm', 'Are you sure you want to revoke access for this device?' ) ) ) {
			return;
		}

		var origHtml = $btn.html();
		$btn.prop( 'disabled', true );

		$.post( params.ajax_url, {
			action: 'wsq_revoke_device',
			device_id: deviceId,
			nonce: params.nonce
		}, function ( response ) {
			$btn.prop( 'disabled', false ).html( origHtml );
			if ( response.success ) {
				var $row = $( '#wsq-device-row-' + deviceId );
				$row.css( 'background', '#fee2e2' ).fadeOut( 400, function () {
					$row.remove();
				} );
				announce( response.data && response.data.message ? response.data.message : t( 'device_revoked', 'Device revoked.' ) );
			} else {
				alert( response.data && response.data.message ? response.data.message : t( 'revoke_failed', 'Failed to revoke device.' ) );
			}
		} ).fail( function () {
			$btn.prop( 'disabled', false ).html( origHtml );
			alert( t( 'request_failed', 'Request failed.' ) );
		} );
	} );

	// Digital Seal & Signature Card Toggle & Live Update
	var $stampToggle = $( '#wsq_show_digital_stamp_toggle' );
	var $stampCard   = $( '.wsq-stamp-preview-card' );
	function updateStampCardState() {
		if ( $stampToggle.length && $stampCard.length ) {
			$stampCard.toggleClass( 'is-disabled', ! $stampToggle.is( ':checked' ) );
		}
	}
	$stampToggle.on( 'change', updateStampCardState );
	updateStampCardState();

	var $sigToggle = $( '#wsq_show_digital_signature_toggle' );
	var $sigCard   = $( '.wsq-signature-preview-card' );
	function updateSigCardState() {
		if ( $sigToggle.length && $sigCard.length ) {
			$sigCard.toggleClass( 'is-disabled', ! $sigToggle.is( ':checked' ) );
		}
	}
	$sigToggle.on( 'change', updateSigCardState );
	updateSigCardState();

	$( '#wsq_company_name' ).on( 'input', function () {
		var val = $( this ).val() || $( this ).attr( 'placeholder' ) || '';
		$( '#wsq-stamp-company-preview' ).text( val );
	} );
	$( '#wsq_stamp_text_input' ).on( 'input', function () {
		var val = $( this ).val() || $( this ).attr( 'placeholder' ) || '';
		$( '#wsq-stamp-text-preview' ).text( val );
	} );
} );
