/**
 * Совместимость: инициализация сравнения после AJAX-вкладки (делегирует wsergo-country-compare.js).
 */
(function (window) {
	'use strict';

	function scan(ctx) {
		if (typeof window.wsergoCountryCompareInit === 'function') {
			var panel = ctx && ctx.nodeType === 1 ? ctx : null;
			window.wsergoCountryCompareInit(panel);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			scan(document);
		});
	} else {
		scan(document);
	}

	document.addEventListener('wsp:tab:loaded', function (ev) {
		var detail = ev && ev.detail ? ev.detail : {};
		scan(detail.panel || document);
	});
})(window);
