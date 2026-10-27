/**
 * WooCommerce Smart Quote Frontend JavaScript
 *
 * Handles: add-to-quote, remove-item (with undo), quantity editing, the
 * quote request form validation and the sticky mobile action bar.
 * Depends on the `wsq_params` object localized from PHP.
 */
jQuery(function($) {
	'use strict';

	var params = (typeof wsq_params !== 'undefined') ? wsq_params : { ajax_url: '', nonce: '', quote_page_url: '', shop_url: '', initial_count: 0, initial_lines: 0, i18n: {} };
	var i18n = params.i18n || {};

	var state = {
		count: toInt(params.initial_count),
		lines: toInt(params.initial_lines)
	};

	/* ------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	function toInt(value) {
		var parsed = parseInt(normalizeDigits(value), 10);
		return isNaN(parsed) ? 0 : parsed;
	}

	function normalizeDigits(str) {
		if (!str) {
			return '';
		}
		return String(str)
			.replace(/[۰-۹]/g, function(d) { return String.fromCharCode(d.charCodeAt(0) - 1728); })
			.replace(/[٠-٩]/g, function(d) { return String.fromCharCode(d.charCodeAt(0) - 1584); });
	}

	function clampQty(value) {
		var qty = toInt(value);
		if (qty < 1) {
			qty = 1;
		}
		if (qty > 9999) {
			qty = 9999;
		}
		return qty;
	}

	function formatCount(units, lines) {
		var template = i18n.count_format || '%1$s / %2$s';
		return template.replace('%1$s', String(units)).replace('%2$s', String(lines));
	}

	function prefersReducedMotion() {
		return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
	}

	function isMobileBar() {
		return !!(window.matchMedia && window.matchMedia('(max-width: 768px)').matches);
	}

	function debounce(fn, wait) {
		var timer = null;
		return function() {
			var args = arguments;
			var context = this;
			clearTimeout(timer);
			timer = setTimeout(function() {
				fn.apply(context, args);
			}, wait);
		};
	}

	function announce(message) {
		if (!message) {
			return;
		}
		var $live = $('[data-wsq-live]').first();
		if (!$live.length) {
			$live = $('<span class="screen-reader-text" data-wsq-live role="status" aria-live="polite"></span>').appendTo(document.body);
		}
		$live.text(message);
	}

	/* ------------------------------------------------------------------
	 * Canonical counters
	 * ------------------------------------------------------------------ */

	function updateCounters(count, lines, options) {
		options = options || {};

		if (typeof count !== 'undefined' && count !== null) {
			state.count = Math.max(0, toInt(count));
		}
		if (typeof lines !== 'undefined' && lines !== null) {
			state.lines = Math.max(0, toInt(lines));
		}

		var $floating = $('[data-wsq-floating]');
		$floating.toggleClass('is-visible', state.count > 0);
		$floating.find('.wsq-floating-link').attr(
			'aria-label',
			(i18n.view_quote_cart || '') + (state.count > 0 ? ' — ' + state.count + ' ' + (i18n.items_suffix || '') : '')
		);

		$('[data-wsq-floating-count]').text(state.count);

		if (options.pulse !== false) {
			var $badge = $('[data-wsq-floating-count]');
			$badge.addClass('pulse');
			setTimeout(function() { $badge.removeClass('pulse'); }, 500);
		}

		$('[data-wsq-sticky-count]').text(state.count);
		$('[data-wsq-count]').text(formatCount(state.count, state.lines));

		$('.wsq-quote-wrapper[data-wsq-quote-root]').each(function() {
			updateSticky($(this));
		});

		if (options.announce !== false) {
			var template = i18n.count_announce || '%1$s / %2$s';
			announce(template.replace('%1$s', String(state.count)).replace('%2$s', String(state.lines)));
		}
	}

	function syncButtons(quantities) {
		if (!quantities) {
			return;
		}

		$('.wsq-is-added[data-product_id]').each(function() {
			var $added = $(this);
			var $wrap = $added.closest('.wsq-btn-wrap, .wsq-loop-btn-wrap');
			var $button = $wrap.find('.wsq-add-to-quote-button').first();

			var productId = String($added.attr('data-product_id'));
			var quantity = Object.prototype.hasOwnProperty.call(quantities, productId) ? toInt(quantities[productId]) : 0;
			var inList = quantity > 0;

			$added.prop('hidden', !inList);
			if ($button.length) {
				$button.prop('hidden', inList);
			}
			$added.find('.wsq-added-count').text(quantity).attr('data-count', quantity);
		});
	}

	function applyResponse(response, options) {
		if (!response || !response.success || !response.data) {
			return;
		}
		updateCounters(response.data.count, response.data.line_count, options);
		if (response.data.product_quantities) {
			syncButtons(response.data.product_quantities);
		}
	}

	function updateSticky($wrapper) {
		if (!$wrapper || !$wrapper.length) {
			return;
		}
		var $bar = $wrapper.find('[data-wsq-sticky]');
		if (!$bar.length) {
			return;
		}

		var visible = state.count > 0
			&& !$wrapper.data('wsqSent')
			&& $wrapper.data('wsqStickyAllowed') !== false
			&& isMobileBar();

		$bar.prop('hidden', !visible);
		$('body').toggleClass('wsq-has-sticky', $('[data-wsq-sticky]:not([hidden])').length > 0);
	}

	/* ------------------------------------------------------------------
	 * Toasts (one persistent live region)
	 * ------------------------------------------------------------------ */

	function ensureToastRegion() {
		var $region = $('.wsq-toast-region');
		if (!$region.length) {
			$region = $('<div class="wsq-toast-region" role="status" aria-live="polite" aria-atomic="false"></div>').appendTo(document.body);
		}
		return $region;
	}

	function toast(message, options) {
		options = options || {};
		var $region = ensureToastRegion();

		while ($region.children('.wsq-toast').length >= 3) {
			var $oldest = $region.children('.wsq-toast').first();
			clearTimeout($oldest.data('wsqToastTimer'));
			$oldest.remove();
		}

		var $toast = $('<div class="wsq-toast"></div>').addClass(options.type === 'error' ? 'wsq-toast-error' : 'wsq-toast-success');
		$toast.append($('<span class="wsq-toast-text"></span>').text(message));

		var timer = null;

		function dismiss() {
			clearTimeout(timer);
			timer = null;
			$toast.removeClass('is-visible');
			setTimeout(function() { $toast.remove(); }, 250);
		}

		function schedule() {
			clearTimeout(timer);
			timer = setTimeout(dismiss, 8000);
			$toast.data('wsqToastTimer', timer);
		}

		if (options.actionLabel) {
			var $action = $('<button type="button" class="wsq-toast-action"></button>').text(options.actionLabel);
			$action.on('click', function() {
				dismiss();
				if (typeof options.onAction === 'function') {
					options.onAction();
				}
			});
			$toast.append($action);
		}

		var $close = $('<button type="button" class="wsq-toast-close"><span aria-hidden="true">&times;</span></button>').attr('aria-label', i18n.close || 'Close');
		$close.on('click', dismiss);
		$toast.append($close);

		$region.append($toast);

		requestAnimationFrame(function() {
			$toast.addClass('is-visible');
		});

		$toast.on('mouseenter focusin', function() {
			clearTimeout(timer);
			timer = null;
		});
		$toast.on('mouseleave focusout', function(e) {
			if (e.type === 'focusout' && e.relatedTarget && $toast[0].contains(e.relatedTarget)) {
				return;
			}
			if ($toast.find(':focus').length) {
				return;
			}
			schedule();
		});

		schedule();
		return $toast;
	}

	/* ------------------------------------------------------------------
	 * Add to quote button
	 * ------------------------------------------------------------------ */

	function showNotice($slot, ok, message, url) {
		if (!$slot || !$slot.length) {
			return;
		}

		var $notice = $('<span class="wsq-msg"></span>')
			.addClass(ok ? 'wsq-msg-success' : 'wsq-msg-error')
			.attr('role', ok ? 'status' : 'alert');

		$notice.append($('<span aria-hidden="true">').html(ok ? '&#10003;' : '&#10005;'));
		$notice.append(document.createTextNode(' ' + message));

		if (url) {
			$notice.append(document.createTextNode(' '));
			$notice.append($('<a></a>').attr('href', url).text(i18n.view_quote || ''));
		}

		$slot.empty().append($notice).prop('hidden', false);
	}

	$(document).on('click', '.wsq-add-to-quote-button', function(e) {
		e.preventDefault();

		var $button = $(this);
		var productId = $button.attr('data-product_id');
		var $wrap = $button.closest('.wsq-btn-wrap, .wsq-loop-btn-wrap');
		var $message = $wrap.find('.wsq-add-to-quote-message').first();
		var wasFocused = document.activeElement === $button[0];

		var quantity = 1;
		var $qty = $button.closest('form.cart').find('input[name="quantity"]');
		if ($qty.length) {
			quantity = toInt($qty.val()) || 1;
		}

		var variationId = 0;
		var variations = {};
		var $varInput = $button.closest('form.cart').find('input[name="variation_id"]');
		if ($varInput.length) {
			variationId = parseInt($varInput.val(), 10) || 0;
		}
		if (variationId > 0) {
			$button.closest('form.cart').find('.variations select').each(function() {
				var vname = $(this).attr('name');
				var vval = $(this).val();
				if (vname && vval) {
					variations[vname] = vval;
				}
			});
		}

		$button.addClass('loading').prop('disabled', true).attr('aria-busy', 'true');
		$message.prop('hidden', true);

		$.ajax({
			url: params.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'wsq_add_to_quote',
				nonce: params.nonce,
				product_id: productId,
				quantity: quantity,
				variation_id: variationId,
				variations: variations
			},
			success: function(response) {
				$button.removeClass('loading').prop('disabled', false).removeAttr('aria-busy');
				if (response && response.success) {
					applyResponse(response);
					showNotice($message, true, i18n.added || '', params.quote_page_url);

					var $added = $wrap.find('.wsq-is-added').first();
					if (wasFocused && $added.length && !$added.prop('hidden')) {
						$added.trigger('focus');
					}
				} else {
					var message = (response && response.data && response.data.message) || i18n.error || 'Error.';
					showNotice($message, false, message);
					toast(message, { type: 'error' });
				}
			},
			error: function() {
				$button.removeClass('loading').prop('disabled', false).removeAttr('aria-busy');
				showNotice($message, false, i18n.error || 'Error.');
			}
		});
	});

	/* ------------------------------------------------------------------
	 * Quantity stepper (independent state per row)
	 * ------------------------------------------------------------------ */

	function updateStepperButtons($stepper, value) {
		var qty = clampQty(value);
		$stepper.find('.wsq-qty-dec').prop('disabled', qty <= 1);
		$stepper.find('.wsq-qty-inc').prop('disabled', qty >= 9999);
	}

	function initStepper($stepper) {
		if ($stepper.data('wsqReady')) {
			return;
		}
		$stepper.data('wsqReady', true);

		var $input = $stepper.find('.wsq-qty-input');
		var confirmed = clampQty($stepper.attr('data-confirmed') || $input.val());
		$stepper.data('wsqConfirmed', confirmed);
		$stepper.data('wsqVersion', 0);
		$input.val(confirmed);
		updateStepperButtons($stepper, confirmed);
	}

	function failQuantity($stepper, $row, message) {
		var confirmed = $stepper.data('wsqConfirmed');
		$stepper.removeClass('is-saving').removeAttr('aria-busy');
		$stepper.find('.wsq-qty-input').val(confirmed);
		updateStepperButtons($stepper, confirmed);
		$row.addClass('wsq-qty-error');
		toast(message || i18n.qty_error || i18n.error || 'Error.', { type: 'error' });
	}

	function sendQuantity($stepper, $row, value, version) {
		if ($stepper.data('wsqVersion') !== version) {
			return;
		}

		var previous = $stepper.data('wsqXhr');
		if (previous && previous.readyState !== 4) {
			previous.abort();
		}

		var xhr = $.ajax({
			url: params.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'wsq_update_item',
				nonce: params.nonce,
				item_key: $stepper.attr('data-item-key') || $stepper.attr('data-item_key'),
				quantity: value
			},
			success: function(response) {
				if ($stepper.data('wsqVersion') !== version) {
					return;
				}
				$stepper.removeData('wsqXhr');
				$stepper.removeClass('is-saving').removeAttr('aria-busy');

				if (response && response.success) {
					var $input = $stepper.find('.wsq-qty-input');
					var saved = clampQty(response.data.quantity);
					$stepper.data('wsqConfirmed', saved);
					if (clampQty($input.val()) === value) {
						$input.val(saved);
					}
					updateStepperButtons($stepper, saved);
					$row.removeClass('wsq-qty-error');
					applyResponse(response);
				} else {
					failQuantity($stepper, $row, response && response.data ? response.data.message : '');
				}
			},
			error: function(xhrObject, status) {
				if (status === 'abort' || $stepper.data('wsqVersion') !== version) {
					return;
				}
				$stepper.removeData('wsqXhr');
				failQuantity($stepper, $row);
			}
		});

		$stepper.data('wsqXhr', xhr);
	}

	function pushQuantity($stepper, rawValue) {
		var $row = $stepper.closest('.wsq-quote-item');
		var $input = $stepper.find('.wsq-qty-input');
		var confirmed = clampQty($stepper.data('wsqConfirmed'));
		var value = clampQty(rawValue);
		var version = ($stepper.data('wsqVersion') || 0) + 1;

		$stepper.data('wsqVersion', version);
		$input.val(value);
		updateStepperButtons($stepper, value);

		clearTimeout($stepper.data('wsqTimer'));

		var inflight = $stepper.data('wsqXhr');
		if (inflight && inflight.readyState !== 4) {
			inflight.abort();
		}
		$stepper.removeData('wsqXhr');

		if (value === confirmed) {
			$stepper.removeClass('is-saving').removeAttr('aria-busy');
			$row.removeClass('wsq-qty-error');
			return;
		}

		$stepper.addClass('is-saving').attr('aria-busy', 'true');

		var timer = setTimeout(function() {
			sendQuantity($stepper, $row, value, version);
		}, 300);
		$stepper.data('wsqTimer', timer);
	}

	$(document).on('click', '.wsq-qty-btn', function(e) {
		e.preventDefault();

		var $button = $(this);
		if ($button.prop('disabled')) {
			return;
		}

		var $stepper = $button.closest('[data-wsq-stepper]');
		var $input = $stepper.find('.wsq-qty-input');
		var current = clampQty($input.val());
		var step = $button.hasClass('wsq-qty-inc') ? 1 : -1;

		pushQuantity($stepper, current + step);
	});

	$(document).on('change', '.wsq-qty-input', function() {
		var $input = $(this);
		pushQuantity($input.closest('[data-wsq-stepper]'), $input.val());
	});

	$(document).on('input paste', '.wsq-qty-input', function() {
		var $input = $(this);
		var normalized = normalizeDigits($input.val());
		if ($input.val() !== normalized) {
			$input.val(normalized);
		}
	});

	$(document).on('keydown', '.wsq-qty-input', function(e) {
		var $input = $(this);
		var $stepper = $input.closest('[data-wsq-stepper]');
		var current = clampQty($input.val());
		var next = null;

		switch (e.key) {
			case 'ArrowUp':
				next = current + 1;
				break;
			case 'ArrowDown':
				next = current - 1;
				break;
			case 'PageUp':
				next = current + 10;
				break;
			case 'PageDown':
				next = current - 10;
				break;
			case 'Home':
				next = 1;
				break;
			case 'End':
				next = 9999;
				break;
			case 'Enter':
				e.preventDefault();
				$input.blur();
				return;
			default:
				return;
		}

		e.preventDefault();
		pushQuantity($stepper, next);
	});

	/* ------------------------------------------------------------------
	 * Empty state
	 * ------------------------------------------------------------------ */

	function buildFallbackEmptyState() {
		var $empty = $('<div class="wsq-empty" role="status"></div>');
		var $icon = $('<div class="wsq-empty-icon" aria-hidden="true"></div>');
		$icon.append($('<span class="wsq-icon"></span>').html('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" style="width:56px;height:56px;"><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path></svg>'));
		$empty.append($icon);
		$empty.append($('<h2 class="wsq-empty-title" tabindex="-1"></h2>').text(i18n.empty_title || ''));
		$empty.append($('<p></p>').text(i18n.empty_desc || ''));
		var $action = $('<p class="wsq-empty-action"></p>');
		$action.append($('<a class="wsq-empty-cta"></a>').attr('href', params.shop_url || '#').text(i18n.empty_cta || ''));
		$empty.append($action);
		return $empty;
	}

	function showEmptyState($wrapper, focus) {
		$wrapper.find('.wsq-steps, .wsq-table-card, .wsq-quote-form-wrapper, [data-wsq-sticky]').prop('hidden', true);

		var $empty = $wrapper.children('.wsq-empty').first();
		if (!$empty.length) {
			var template = $wrapper.find('template[data-wsq-empty-template]')[0];
			if (template && template.content) {
				var holder = document.createElement('div');
				holder.appendChild(template.content.cloneNode(true));
				$empty = $(holder).children('.wsq-empty').first();
			}
			if (!$empty.length) {
				$empty = buildFallbackEmptyState();
			}
			$wrapper.append($empty);
		}

		$empty.prop('hidden', false);

		if (focus) {
			var title = $empty.find('.wsq-empty-title')[0];
			if (title && title.focus) {
				title.focus();
			}
		}

		updateSticky($wrapper);
	}

	function hideEmptyState($wrapper) {
		$wrapper.children('.wsq-empty').remove();
		$wrapper.find('.wsq-steps, .wsq-table-card, .wsq-quote-form-wrapper').prop('hidden', false);
	}

	/* ------------------------------------------------------------------
	 * Row controls
	 * ------------------------------------------------------------------ */

	function disableRow($row) {
		$row.addClass('is-pending').attr('aria-busy', 'true');
		$row.find('button, input').prop('disabled', true);
	}

	function enableRow($row) {
		$row.removeClass('is-pending').removeAttr('aria-busy');
		$row.find('button, input').prop('disabled', false);

		var $stepper = $row.find('[data-wsq-stepper]');
		if ($stepper.length) {
			updateStepperButtons($stepper, clampQty($stepper.find('.wsq-qty-input').val()));
		}
	}

	function reenableRows($rows) {
		$rows.each(function() {
			enableRow($(this));
		});
	}

	/* ------------------------------------------------------------------
	 * Remove item (with undo)
	 * ------------------------------------------------------------------ */

	function restoreRow($row, $wrapper) {
		var itemKey = $row.attr('data-item-key') || $row.attr('data-item_key');

		disableRow($row);

		$.ajax({
			url: params.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'wsq_restore_item',
				nonce: params.nonce,
				item_key: itemKey
			},
			success: function(response) {
				if (response && response.success) {
					enableRow($row);
					hideEmptyState($wrapper);
					$wrapper.find('.wsq-quote-table tbody').append($row);
					applyResponse(response, { announce: false });
					updateSticky($wrapper);
					$row.find('.wsq-remove-item').trigger('focus');
				} else {
					var message = (response && response.data && response.data.message) || i18n.network_error || 'Error.';
					toast(message, { type: 'error' });
				}
			},
			error: function() {
				toast(i18n.network_error || 'Error.', { type: 'error' });
			}
		});
	}

	$(document).on('click', '.wsq-remove-item', function(e) {
		e.preventDefault();

		var $button = $(this);
		var $row = $button.closest('.wsq-quote-item');
		var $wrapper = $row.closest('.wsq-quote-wrapper');

		if ($row.hasClass('is-pending')) {
			return;
		}

		var itemKey = $row.attr('data-item-key') || $row.attr('data-item_key');
		disableRow($row);

		$.ajax({
			url: params.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'wsq_remove_item',
				nonce: params.nonce,
				item_key: itemKey
			},
			success: function(response) {
				if (!response || !response.success) {
					enableRow($row);
					var message = (response && response.data && response.data.message) || i18n.network_error || 'Error.';
					toast(message, { type: 'error' });
					return;
				}

				applyResponse(response, { announce: false });

				var $next = $row.next('.wsq-quote-item');
				var $previous = $row.prev('.wsq-quote-item');
				$row.detach();

				if ($wrapper.find('.wsq-quote-item').length) {
					var $target = ($next.length ? $next : $previous).find('.wsq-remove-item');
					if ($target.length) {
						$target.trigger('focus');
					}
				} else {
					showEmptyState($wrapper, true);
				}

				toast(i18n.removed || i18n.all_removed || '', {
					actionLabel: i18n.undo || '',
					onAction: function() {
						restoreRow($row, $wrapper);
					}
				});
			},
			error: function() {
				enableRow($row);
				toast(i18n.network_error || 'Error.', { type: 'error' });
			}
		});
	});

	/* ------------------------------------------------------------------
	 * Clear all (with undo)
	 * ------------------------------------------------------------------ */

	function restoreAllRows($wrapper, $rows) {
		$.ajax({
			url: params.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'wsq_restore_all',
				nonce: params.nonce
			},
			success: function(response) {
				if (response && response.success) {
					hideEmptyState($wrapper);
					$wrapper.find('.wsq-quote-table tbody').append($rows);
					reenableRows($rows);
					applyResponse(response, { announce: false });
					updateSticky($wrapper);
					$wrapper.find('.wsq-table-card-title').trigger('focus');
				} else {
					var message = (response && response.data && response.data.message) || i18n.network_error || 'Error.';
					toast(message, { type: 'error' });
				}
			},
			error: function() {
				toast(i18n.network_error || 'Error.', { type: 'error' });
			}
		});
	}

	$(document).on('click', '[data-wsq-clear-all]', function(e) {
		e.preventDefault();

		var $button = $(this);
		var $wrapper = $button.closest('.wsq-quote-wrapper');

		if ($wrapper.data('wsqClearing')) {
			return;
		}

		var $rows = $wrapper.find('.wsq-quote-item');
		if (!$rows.length) {
			return;
		}

		if (!window.confirm(i18n.confirm_clear || '')) {
			return;
		}

		$wrapper.data('wsqClearing', true);
		$button.prop('disabled', true).attr('aria-busy', 'true');
		$rows.each(function() {
			disableRow($(this));
		});

		$.ajax({
			url: params.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'wsq_clear_all',
				nonce: params.nonce
			},
			success: function(response) {
				$wrapper.removeData('wsqClearing');
				$button.prop('disabled', false).removeAttr('aria-busy');

				if (!response || !response.success) {
					reenableRows($rows);
					var message = (response && response.data && response.data.message) || i18n.network_error || 'Error.';
					toast(message, { type: 'error' });
					return;
				}

				applyResponse(response, { announce: false });
				var $detached = $rows.detach();
				showEmptyState($wrapper, true);

				toast(i18n.cleared || i18n.all_removed || '', {
					actionLabel: i18n.undo || '',
					onAction: function() {
						restoreAllRows($wrapper, $detached);
					}
				});
			},
			error: function() {
				$wrapper.removeData('wsqClearing');
				$button.prop('disabled', false).removeAttr('aria-busy');
				reenableRows($rows);
				toast(i18n.network_error || 'Error.', { type: 'error' });
			}
		});
	});

	/* ------------------------------------------------------------------
	 * Quote request form
	 * ------------------------------------------------------------------ */

	function fieldErrorElement($field) {
		var describedBy = $field.attr('aria-describedby');
		if (!describedBy) {
			return $();
		}
		var element = document.getElementById(describedBy.split(/\s+/)[0]);
		return element ? $(element) : $();
	}

	function fieldLabel($field) {
		var $label = $field.closest('.wsq-field').find('label').first();
		return $label.length ? $.trim($label.text().replace(/\*/g, '')) : '';
	}

	function clearFieldError($field) {
		$field.removeAttr('aria-invalid');
		$field.closest('.wsq-field').removeClass('has-error');
		fieldErrorElement($field).text('');
	}

	function markFieldValid($field) {
		$field.removeAttr('aria-invalid');
		var $wrap = $field.closest('.wsq-field');
		$wrap.removeClass('has-error').addClass('is-valid');
		fieldErrorElement($field).text('');
	}

	function setFieldError($field, message) {
		var $wrap = $field.closest('.wsq-field');
		$field.attr('aria-invalid', 'true');
		$wrap.removeClass('is-valid').addClass('has-error');
		fieldErrorElement($field).text(message);
	}

	function normalizePhone(value) {
		var raw = normalizeDigits(value).trim();
		var cleaned = raw.replace(/[\s\-()]/g, '');
		cleaned = cleaned.replace(/^0098/, '0').replace(/^\+98/, '0').replace(/^98(?=9)/, '0');
		if (cleaned.length === 10 && cleaned.charAt(0) === '9') {
			cleaned = '0' + cleaned;
		}
		return cleaned;
	}

	function fieldError($field) {
		var name = $field.attr('name');
		var value = $.trim($field.val() || '');

		if (name === 'wsq_name') {
			return value === '' ? (i18n.err_name || '') : '';
		}
		if (name === 'wsq_email') {
			return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value) ? '' : (i18n.err_email || '');
		}
		if (name === 'wsq_phone') {
			return /^09\d{9}$/.test(normalizePhone(value)) ? '' : (i18n.err_phone || '');
		}
		return '';
	}

	function validateField($field) {
		if ($field.attr('name') === 'wsq_phone') {
			var cleaned = normalizePhone($field.val() || '');
			if (/^09\d{9}$/.test(cleaned)) {
				$field.val(cleaned);
			} else {
				$field.val(normalizeDigits($field.val() || ''));
			}
		}

		var error = fieldError($field);
		if (error) {
			setFieldError($field, error);
			return false;
		}
		markFieldValid($field);
		return true;
	}

	function buildErrorSummary($form, $fields) {
		var $box = $form.find('[data-wsq-form-errors]').first();
		if (!$box.length) {
			return;
		}

		$box.empty();
		$box.append($('<p></p>').text(i18n.err_summary || ''));

		var $list = $('<ul></ul>');
		$fields.each(function() {
			var $field = $(this);
			var $item = $('<li></li>');
			var $link = $('<a></a>').attr('href', '#' + ($field.attr('id') || '')).text(fieldLabel($field) || $field.attr('name') || '');
			$link.on('click', function(event) {
				event.preventDefault();
				$field.trigger('focus');
			});
			$item.append($link);
			$list.append($item);
		});

		$box.append($list).prop('hidden', false).trigger('focus');
	}

	function hideErrorSummary($form) {
		$form.find('[data-wsq-form-errors]').prop('hidden', true).empty();
	}

	function validateForm($form) {
		var $invalid = $();

		['wsq_name', 'wsq_email', 'wsq_phone'].forEach(function(name) {
			var $field = $form.find('[name="' + name + '"]').first();
			if ($field.length && !validateField($field)) {
				$invalid = $invalid.add($field);
			}
		});

		return $invalid;
	}

	function persistSuccess(orderId, orderKey) {
		if (!window.history || !window.history.replaceState || !orderId || !orderKey) {
			return;
		}

		try {
			var url = new URL(window.location.href);
			url.searchParams.set('wsq_order', orderId);
			url.searchParams.set('wsq_key', orderKey);
			window.history.replaceState({ wsqOrder: orderId }, '', url.toString());
		} catch (error) {
			var separator = window.location.search ? '&' : '?';
			var query = window.location.search + separator
				+ 'wsq_order=' + encodeURIComponent(orderId)
				+ '&wsq_key=' + encodeURIComponent(orderKey);
			window.history.replaceState({ wsqOrder: orderId }, '', window.location.pathname + query + window.location.hash);
		}
	}

	function buildSuccessCard(data) {
		var $card = $('<div class="wsq-success-card" role="status"></div>');

		$('<div class="wsq-success-icon-wrap" aria-hidden="true">').html('&#10003;').appendTo($card);
		$('<h3 class="wsq-success-title" tabindex="-1"></h3>').text(i18n.success_title || '').appendTo($card);
		$('<p class="wsq-success-desc"></p>').text(data.message || '').appendTo($card);

		var number = data.order_number || data.order_id;
		if (number) {
			var $badge = $('<div class="wsq-success-order-badge"></div>');
			$badge.append(document.createTextNode((i18n.order_prefix || '') + ' '));
			$('<span dir="ltr"></span>').text('#' + number).appendTo($badge);

			var $copy = $('<button type="button" class="wsq-copy-order-btn"></button>').attr({
				'data-order': number,
				'aria-label': i18n.copy_code || i18n.copy || ''
			});
			$('<span class="wsq-copy-text"></span>').text(i18n.copy || '').appendTo($copy);
			$badge.append($copy);
			$('<span class="wsq-copy-status screen-reader-text" role="status" aria-live="polite"></span>').appendTo($badge);

			$badge.appendTo($card);
		}

		if (i18n.success_note) {
			$('<p class="wsq-success-note"></p>').text(i18n.success_note).appendTo($card);
		}

		var loggedIn = !!data.is_logged_in && data.account_url;
		var actionUrl = loggedIn ? data.account_url : data.order_url;
		if (actionUrl) {
			var $actions = $('<p class="wsq-success-actions"></p>');
			var $action = $('<a class="wsq-success-action-btn"></a>').attr('href', actionUrl);
			$action.append($('<span aria-hidden="true">').html('&#10003;'));
			$action.append(document.createTextNode(' ' + (loggedIn ? (i18n.view_order || '') : (i18n.receipt || ''))));
			$actions.append($action);
			$actions.appendTo($card);
		}

		return $card;
	}

	function setSubmitting($form, submitting) {
		var $button = $form.find('[data-wsq-submit]').first();
		var $wrapper = $form.closest('.wsq-quote-wrapper');

		if (submitting) {
			$form.data('wsqSubmitting', true);
			$form.find('input, textarea, select').prop('disabled', true);
			$button.prop('disabled', true).attr('aria-busy', 'true')
				.html('<span class="wsq-spinner" aria-hidden="true"></span> ')
				.append(document.createTextNode(i18n.submitting || ''));
			$wrapper.find('[data-wsq-sticky]').prop('hidden', true);
			$wrapper.data('wsqSent', true);
		} else {
			$form.data('wsqSubmitting', false);
			$form.find('input, textarea, select').prop('disabled', false);
			$button.prop('disabled', false).removeAttr('aria-busy');
			$wrapper.data('wsqSent', false);
			updateSticky($wrapper);
		}
	}

	$(document).on('submit', '.wsq-quote-form', function(e) {
		e.preventDefault();

		var $form = $(this);
		if ($form.data('wsqSubmitting')) {
			return;
		}

		var $button = $form.find('[data-wsq-submit]').first();
		var originalHtml = $button.html();
		var $message = $form.find('[data-wsq-submit-message]').first();
		var $wrapper = $form.closest('.wsq-quote-wrapper');

		var $invalid = validateForm($form);
		if ($invalid.length) {
			buildErrorSummary($form, $invalid);
			$message.prop('hidden', true).empty();
			return;
		}

		hideErrorSummary($form);

		if (!$form.find('input[name="wsq_channel"]:checked').length) {
			$form.append('<input type="hidden" name="wsq_channel" value="all">');
		}

		var formData = $form.serialize();
		setSubmitting($form, true);

		$.ajax({
			url: params.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: formData,
			success: function(response) {
				$button.removeAttr('aria-busy');

				if (response && response.success) {
					var data = response.data || {};

					$button.prop('disabled', true);
					applyResponse({ success: true, data: { count: 0, line_count: 0, product_quantities: {} } }, { announce: false });

					$form.find('.form-row, fieldset, noscript').prop('hidden', true);
					$wrapper.find('.wsq-table-card').prop('hidden', true);
					$wrapper.data('wsqSent', true);
					updateSticky($wrapper);
					$('[data-wsq-floating]').removeClass('is-visible');

					$message.attr('role', 'status').empty().append(buildSuccessCard(data)).prop('hidden', false);

					var $steps = $wrapper.find('.wsq-steps .wsq-step');
					$steps.removeClass('is-now').removeAttr('aria-current');
					$steps.slice(0, 2).addClass('is-done').find('.wsq-step-num').html('&#10003;');
					$steps.last().addClass('is-now').attr('aria-current', 'step');

					persistSuccess(data.order_id, data.order_key);

					var title = $message.find('.wsq-success-title')[0];
					if (title && title.focus) {
						title.focus();
					} else {
						$message.trigger('focus');
					}

					announce(i18n.success_title || '');
				} else {
					var message = (response && response.data && response.data.message) || i18n.network_error || 'Error.';
					setSubmitting($form, false);
					$button.html(originalHtml);
					$message.attr('role', 'alert').html(
						$('<div class="wsq-msg wsq-msg-error"></div>')
							.append($('<span aria-hidden="true">').html('&#10005;'))
							.append(document.createTextNode(' ' + message))
					).prop('hidden', false);
					toast(message, { type: 'error' });
				}
			},
			error: function() {
				setSubmitting($form, false);
				$button.html(originalHtml).removeAttr('aria-busy');
				$message.attr('role', 'alert').html(
					$('<div class="wsq-msg wsq-msg-error"></div>')
						.append($('<span aria-hidden="true">').html('&#10005;'))
						.append(document.createTextNode(' ' + (i18n.network_error || 'Error.')))
				).prop('hidden', false);
				toast(i18n.network_error || 'Error.', { type: 'error' });
			}
		});
	});

	// Auto-normalize Persian/Arabic numerals on phone input.
	$(document).on('input paste', '.wsq-quote-form [name="wsq_phone"]', function() {
		var $input = $(this);
		var normalized = normalizeDigits($input.val());
		if ($input.val() !== normalized) {
			$input.val(normalized);
		}
	});

	// Enter moves forward through the form instead of reloading the page.
	$(document).on('keydown', '.wsq-quote-form [name="wsq_name"]', function(e) {
		if (e.key === 'Enter') {
			e.preventDefault();
			$(this).closest('form').find('[name="wsq_email"]').trigger('focus');
		}
	});
	$(document).on('keydown', '.wsq-quote-form [name="wsq_email"]', function(e) {
		if (e.key === 'Enter') {
			e.preventDefault();
			$(this).closest('form').find('[name="wsq_phone"]').trigger('focus');
		}
	});
	$(document).on('keydown', '.wsq-quote-form [name="wsq_phone"]', function(e) {
		if (e.key === 'Enter') {
			e.preventDefault();
			$(this).closest('form').find('[name="wsq_message"]').trigger('focus');
		}
	});

	$(document).on('blur', '.wsq-quote-form .input-text', function() {
		var $field = $(this);
		if (['wsq_name', 'wsq_email', 'wsq_phone'].indexOf($field.attr('name')) !== -1) {
			validateField($field);
		}
	});

	$(document).on('input change', '.wsq-quote-form .input-text', function() {
		var $field = $(this);
		var name = $field.attr('name');
		if (['wsq_name', 'wsq_email', 'wsq_phone'].indexOf(name) === -1) {
			return;
		}

		if (name === 'wsq_phone') {
			var normalized = normalizeDigits($field.val());
			if ($field.val() !== normalized) {
				$field.val(normalized);
			}
		}

		if (!fieldError($field)) {
			markFieldValid($field);
			var $form = $field.closest('form');
			if ($form.find('[aria-invalid="true"]').length === 0) {
				hideErrorSummary($form);
			}
		}
	});

	/* ------------------------------------------------------------------
	 * Copy tracking code
	 * ------------------------------------------------------------------ */

	function fallbackCopy(text) {
		var textarea = document.createElement('textarea');
		textarea.value = text;
		textarea.setAttribute('readonly', '');
		textarea.style.position = 'fixed';
		textarea.style.top = '0';
		textarea.style.opacity = '0';
		document.body.appendChild(textarea);
		textarea.select();
		textarea.setSelectionRange(0, textarea.value.length);

		var copied = false;
		try {
			copied = document.execCommand('copy');
		} catch (error) {
			copied = false;
		}

		document.body.removeChild(textarea);
		return copied;
	}

	function copyText(text, callback) {
		function done(ok) {
			callback(!!ok);
		}

		if (navigator.clipboard && navigator.clipboard.writeText && window.isSecureContext) {
			navigator.clipboard.writeText(text).then(function() {
				done(true);
			}, function() {
				done(fallbackCopy(text));
			});
		} else {
			done(fallbackCopy(text));
		}
	}

	$(document).on('click', '.wsq-copy-order-btn', function(e) {
		e.preventDefault();

		var $button = $(this);
		var code = String($button.attr('data-order') || '');
		var $badge = $button.closest('.wsq-success-order-badge');
		var $status = $badge.find('.wsq-copy-status');
		var $label = $button.find('.wsq-copy-text');

		copyText(code, function(ok) {
			$button.toggleClass('is-copied', ok).toggleClass('is-copy-error', !ok);
			$status.text(ok ? (i18n.copied || '') : (i18n.copy_failed || ''));
			if (ok) {
				$label.text(i18n.copied || '');
			}

			setTimeout(function() {
				$button.removeClass('is-copied is-copy-error');
				$label.text(i18n.copy || '');
				$status.text('');
			}, 2200);
		});
	});

	/* ------------------------------------------------------------------
	 * Sticky mobile action bar
	 * ------------------------------------------------------------------ */

	$(document).on('click', '[data-wsq-sticky-cta]', function() {
		var $wrapper = $(this).closest('.wsq-quote-wrapper');
		var $form = $wrapper.find('[data-wsq-form]').first();
		if (!$form.length) {
			return;
		}

		var offset = $form.offset();
		if (offset) {
			window.scrollTo({
				top: Math.max(offset.top - 80, 0),
				behavior: prefersReducedMotion() ? 'auto' : 'smooth'
			});
		}

		var $target = $();
		$form.find('[required]').each(function() {
			if (!$.trim($(this).val())) {
				$target = $(this);
				return false;
			}
		});
		if (!$target.length) {
			$target = $form.find('[name="wsq_name"]');
		}

		setTimeout(function() {
			$target.trigger('focus');
		}, prefersReducedMotion() ? 0 : 360);
	});

	function initWrapper($wrapper) {
		if ($wrapper.data('wsqReady')) {
			return;
		}
		$wrapper.data('wsqReady', true);

		$wrapper.find('[data-wsq-stepper]').each(function() {
			initStepper($(this));
		});

		$wrapper.find('.wsq-quote-form [name="wsq_name"], .wsq-quote-form [name="wsq_email"], .wsq-quote-form [name="wsq_phone"]').each(function() {
			var $field = $(this);
			if ($.trim($field.val() || '') !== '' && !fieldError($field)) {
				markFieldValid($field);
			}
		});

		var form = $wrapper.find('[data-wsq-form]')[0];

		if (form && 'IntersectionObserver' in window) {
			$wrapper.data('wsqStickyAllowed', false);
			var observer = new IntersectionObserver(function(entries) {
				var entry = entries[0];
				$wrapper.data('wsqStickyAllowed', !(entry.isIntersecting && entry.intersectionRatio >= 0.15));
				updateSticky($wrapper);
			}, { rootMargin: '0px 0px -35% 0px', threshold: [0, 0.15] });
			observer.observe(form);
		} else {
			$wrapper.data('wsqStickyAllowed', true);
		}

		updateSticky($wrapper);
	}

	ensureToastRegion();

	$('.wsq-quote-wrapper[data-wsq-quote-root]').each(function() {
		initWrapper($(this));
	});

	// Keep the counters hydrated from the localized state without ever
	// resetting the server-rendered badge to zero.
	updateCounters(state.count, state.lines, { announce: false, pulse: false });

	$(window).on('resize', debounce(function() {
		$('.wsq-quote-wrapper[data-wsq-quote-root]').each(function() {
			updateSticky($(this));
		});
	}, 150));
});
