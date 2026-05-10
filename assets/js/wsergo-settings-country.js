/**
 * Калькулятор пользовательских макропараметров (вкладки «Данные» страна и город).
 */
(function ($) {
	'use strict';

	function sanitizeKey(s) {
		s = $.trim(String(s || '')).toLowerCase();
		return s.replace(/[^a-z0-9_-]/g, '').replace(/-/g, '_');
	}

	function bindCustomMetricCalculator(cfg, ids) {
		cfg = cfg || {};
		ids = ids || {};
		var btnSel = ids.btn || '#wsergo-cm-add';
		var storeSel = ids.store || '#wsergo-cm-store';
		var $btn = $(btnSel);
		var $store = $(storeSel);
		if (!$btn.length || !$store.length) {
			return;
		}

		var maxR = typeof cfg.maxRules === 'number' ? cfg.maxRules : 30;
		var msg = cfg.messages || {};
		var opSel = ids.op || '#wsergo-cm-op';
		var wrapConstSel = ids.wrapConst || '#wsergo-cm-wrap-const';
		var wrapBSel = ids.wrapB || '#wsergo-cm-wrap-b';
		var keyASel = ids.keyA || '#wsergo-cm-key-a';
		var keyBSel = ids.keyB || '#wsergo-cm-key-b';
		var slugSel = ids.slug || '#wsergo-cm-slug';
		var constSel = ids.constInp || '#wsergo-cm-const';
		var scrollTabId = ids.scrollTabId || 'tab-data';
		var sessionKey = ids.sessionKey || 'wsergo_scroll_tab_data';

		function toggleConstUi() {
			var op = $(opSel).val() || '';
			var sc = op === 'scale_mul' || op === 'scale_add';
			$(wrapConstSel).toggle(sc);
			$(wrapBSel).toggle(!sc);
		}

		toggleConstUi();
		$(opSel).on('change', toggleConstUi);

		$btn.off('click.wsergoCmCalc').on('click.wsergoCmCalc', function (e) {
			e.preventDefault();

			if ($store.find('.wsergo-cm-row').length >= maxR) {
				window.alert(msg.maxRules || '');
				return;
			}

			var op = $(opSel).val() || '';
			var ka = sanitizeKey($(keyASel).val());
			var kb = sanitizeKey($(keyBSel).val());
			var slug = sanitizeKey($(slugSel).val());
			var cRaw = String($(constSel).val() || '0')
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
				typeof cfg.ajaxUrl === 'string' && cfg.ajaxUrl
					? cfg.ajaxUrl
					: typeof window.ajaxurl === 'string' && window.ajaxurl
						? window.ajaxurl
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

			jQuery
				.ajax({
					url: ajaxUrl,
					type: 'POST',
					dataType: 'json',
					data: {
						action: action,
						nonce: nonce,
						rule: rule,
					},
				})
				.done(function (r) {
					if (r && r.success) {
						try {
							sessionStorage.setItem(sessionKey, '1');
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
				.fail(function (xhr, status) {
					var extra = '';
					if (xhr && xhr.responseText && status === 'parsererror') {
						extra = String(xhr.responseText).slice(0, 200);
					}
					window.alert((msg.ajaxFail || '') + (extra ? '\n' + extra : ''));
				})
				.always(function () {
					$btn.prop('disabled', false);
				});
		});

		try {
			if (sessionStorage.getItem(sessionKey) === '1') {
				sessionStorage.removeItem(sessionKey);
				var el = document.getElementById(scrollTabId);
				if (el && el.scrollIntoView) {
					el.scrollIntoView({ block: 'start' });
				}
			}
		} catch (err) {}
	}

	$(function () {
		bindCustomMetricCalculator(window.wsergoCmSettings || {}, {});
		var base = window.wsergoCmSettings || {};
		var cityBlock = base.city;
		var cityCfg =
			cityBlock && typeof cityBlock === 'object'
				? $.extend({}, base, cityBlock)
				: null;
		if (cityCfg && cityCfg.ajaxAction) {
			bindCustomMetricCalculator(cityCfg, {
				btn: '#wsergo-cm-add-city',
				store: '#wsergo-cm-store-city',
				op: '#wsergo-cm-op-city',
				wrapConst: '#wsergo-cm-wrap-const-city',
				wrapB: '#wsergo-cm-wrap-b-city',
				keyA: '#wsergo-cm-key-a-city',
				keyB: '#wsergo-cm-key-b-city',
				slug: '#wsergo-cm-slug-city',
				constInp: '#wsergo-cm-const-city',
				scrollTabId: 'tab-city-data',
				sessionKey: 'wsergo_scroll_tab_city_data',
			});
		}
	});
})(jQuery);
