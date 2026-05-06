/**
 * Калькулятор пользовательских макропараметров (вкладка «Данные»).
 * Сохранение через AJAX — не зависит от лимита PHP max_input_vars на большой форме.
 */
(function ($) {
	'use strict';

	function sanitizeKey(s) {
		s = $.trim(String(s || '')).toLowerCase();
		return s.replace(/[^a-z0-9_-]/g, '').replace(/-/g, '_');
	}

	function toggleConstUi() {
		var op = $('#wsergo-cm-op').val() || '';
		var sc = op === 'scale_mul' || op === 'scale_add';
		$('#wsergo-cm-wrap-const').toggle(sc);
		$('#wsergo-cm-wrap-b').toggle(!sc);
	}

	function bindCustomMetricCalculator() {
		var $btn = $('#wsergo-cm-add');
		var $store = $('#wsergo-cm-store');
		if (!$btn.length || !$store.length) {
			return;
		}

		var cfg = window.wsergoCmSettings || {};
		var maxR = typeof cfg.maxRules === 'number' ? cfg.maxRules : 30;
		var msg = cfg.messages || {};

		toggleConstUi();
		$('#wsergo-cm-op').on('change', toggleConstUi);

		$btn.on('click', function (e) {
			e.preventDefault();

			if ($store.find('.wsergo-cm-row').length >= maxR) {
				window.alert(msg.maxRules || '');
				return;
			}

			var op = $('#wsergo-cm-op').val() || '';
			var ka = sanitizeKey($('#wsergo-cm-key-a').val());
			var kb = sanitizeKey($('#wsergo-cm-key-b').val());
			var slug = sanitizeKey($('#wsergo-cm-slug').val());
			var cRaw = String($('#wsergo-cm-const').val() || '0')
				.trim()
				.replace(',', '.');
			var cNum = parseFloat(cRaw);
			if (!isFinite(cNum)) {
				cNum = 0;
			}

			if (slug === '') {
				window.alert(msg.needSlug || '');
				return;
			}

			var bin = { add: 1, sub: 1, mul: 1, div: 1 };
			if (bin[op]) {
				if (ka === '' || kb === '') {
					window.alert(msg.needAB || '');
					return;
				}
			} else if (op === 'scale_mul' || op === 'scale_add') {
				if (ka === '') {
					window.alert(msg.needA || '');
					return;
				}
				kb = '';
			}

			var existing = {};
			$store.find('input[name$="[slug]"]').each(function () {
				existing[String($(this).val()).toLowerCase()] = true;
			});
			if (existing[slug]) {
				window.alert(msg.duplicateSlug || '');
				return;
			}

			var ajaxUrl =
				typeof window.ajaxurl === 'string' && window.ajaxurl
					? window.ajaxurl
					: typeof cfg.ajaxUrl === 'string'
						? cfg.ajaxUrl
						: '';
			var nonce = cfg.nonceAppend || '';
			var action = cfg.ajaxAction || 'wsergo_append_custom_metric';

			if (!ajaxUrl || !nonce) {
				window.alert(msg.ajaxFail || '');
				return;
			}

			var rule = JSON.stringify({
				slug: slug,
				op: op,
				key_a: ka,
				key_b: kb,
				const: cNum,
			});

			$btn.prop('disabled', true);

			$.post(ajaxUrl, {
				action: action,
				nonce: nonce,
				rule: rule,
			})
				.done(function (r) {
					if (r && r.success) {
						try {
							sessionStorage.setItem(
								'wsergo_scroll_tab_data',
								'1'
							);
						} catch (err) {}
						window.location.reload();
						return;
					}
					var errMsg =
						r &&
						r.data &&
						(r.data.message ||
							(typeof r.data === 'string' ? r.data : ''));
					window.alert(errMsg || msg.ajaxFail || '');
				})
				.fail(function () {
					window.alert(msg.ajaxFail || '');
				})
				.always(function () {
					$btn.prop('disabled', false);
				});
		});
	}

	$(function () {
		bindCustomMetricCalculator();
		try {
			if (sessionStorage.getItem('wsergo_scroll_tab_data') === '1') {
				sessionStorage.removeItem('wsergo_scroll_tab_data');
				var el = document.getElementById('tab-data');
				if (el && el.scrollIntoView) {
					el.scrollIntoView({ block: 'start' });
				}
			}
		} catch (err) {}
	});
})(jQuery);
