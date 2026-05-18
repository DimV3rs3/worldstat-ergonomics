/**
 * Автоподбор k-means и ленивая загрузка списка признаков в админке.
 */
(function ($) {
	'use strict';

	var cfg = window.wsergoClusterTune || {};
	var loadedScopes = {};

	function stripJsonPayload(text) {
		if (typeof text !== 'string') {
			return text;
		}
		return text.replace(/^\uFEFF+/, '').trim();
	}

	function parseJsonText(text) {
		try {
			return JSON.parse(stripJsonPayload(text));
		} catch (e) {
			return null;
		}
	}

	/**
	 * POST admin-ajax с dataType text — обход BOM/notice перед JSON.
	 */
	function ajaxPost(data) {
		return $.ajax({
			url: cfg.ajaxUrl,
			type: 'POST',
			data: data,
			dataType: 'text',
		}).then(function (text, _status, xhr) {
			var res = parseJsonText(text);
			if (res) {
				return res;
			}
			if (xhr && xhr.responseJSON) {
				return xhr.responseJSON;
			}
			return { success: false, data: { message: (cfg.i18n && cfg.i18n.error) || 'Error' } };
		});
	}

	function messageFromXhr(xhr) {
		var res = null;
		if (xhr && xhr.responseJSON) {
			res = xhr.responseJSON;
		} else if (xhr && xhr.responseText) {
			res = parseJsonText(xhr.responseText);
		}
		if (res && res.data && res.data.message) {
			return res.data.message;
		}
		if (xhr && xhr.status === 403) {
			return 'Сессия истекла — обновите страницу и повторите.';
		}
		if (xhr && xhr.statusText === 'parsererror') {
			return 'Некорректный ответ сервера (проверьте PHP notice/BOM в файлах плагина).';
		}
		return (cfg.i18n && cfg.i18n.error) || 'Error';
	}

	function statusForScope(scope) {
		return $('.wsergo-auto-tune-clusters[data-scope="' + scope + '"]').closest('p').find('.wsergo-auto-tune-status');
	}

	function kInputForScope(scope) {
		return scope === 'city' ? '#wsergo_city_macro_k_clusters' : '#wsergo_macro_k_clusters';
	}

	function reportEl(scope) {
		return $('#wsergo-cluster-tune-report-' + scope);
	}

	function hostEl(scope) {
		return $('#wsergo-cluster-features-' + scope);
	}

	function loadFeaturesUi(scope, onReady) {
		var $host = hostEl(scope);
		if (!$host.length || !cfg.ajaxUrl) {
			if (typeof onReady === 'function') {
				onReady();
			}
			return;
		}
		if ($host.attr('data-wsergo-features-inline') === '1' && $host.find('input[type="checkbox"]').length) {
			loadedScopes[scope] = 'done';
			if (typeof onReady === 'function') {
				onReady();
			}
			return;
		}
		if (loadedScopes[scope] === 'done') {
			if (typeof onReady === 'function') {
				onReady();
			}
			return;
		}
		if (loadedScopes[scope] === 'loading') {
			$host.one('wsergo-cluster-features-ready', function () {
				if (typeof onReady === 'function') {
					onReady();
				}
			});
			return;
		}
		loadedScopes[scope] = 'loading';
		ajaxPost({
			action: 'wsergo_load_cluster_features_ui',
			nonce: cfg.nonce,
			scope: scope,
		})
			.done(function (res) {
				if (res && res.success && res.data && res.data.html) {
					$host.html(res.data.html);
				} else {
					var msg = (res && res.data && res.data.message) || (cfg.i18n && cfg.i18n.error) || 'Error';
					$host.html('<p class="wsp-muted">' + esc(msg) + '</p>');
				}
				loadedScopes[scope] = 'done';
				$host.trigger('wsergo-cluster-features-ready');
				if (typeof onReady === 'function') {
					onReady();
				}
			})
			.fail(function (xhr) {
				$host.html('<p class="wsp-muted">' + esc(messageFromXhr(xhr)) + '</p>');
				loadedScopes[scope] = 'error';
				if (typeof onReady === 'function') {
					onReady();
				}
			});
	}

	function esc(s) {
		return String(s || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	}

	function applyFeatures(scope, features) {
		var $host = hostEl(scope);
		if (!$host.length) {
			return;
		}
		$host.find('input[type="checkbox"]').each(function () {
			var $cb = $(this);
			var val = $cb.val();
			$cb.prop('checked', features.indexOf(val) !== -1);
		});
	}

	function renderReport(scope, data) {
		var $rep = reportEl(scope);
		if (!$rep.length || !data.feature_report) {
			return;
		}
		var html = '<p><strong>' + esc(data.message || '') + '</strong></p>';
		html += '<table class="widefat striped" style="margin-top:8px;"><thead><tr>';
		html += '<th>' + esc('Показатель') + '</th><th>' + esc((cfg.i18n && cfg.i18n.cv) || 'CV') + '</th>';
		html += '<th>' + esc((cfg.i18n && cfg.i18n.coverage) || '%') + '</th><th></th></tr></thead><tbody>';
		data.feature_report.forEach(function (row) {
			if (!row) {
				return;
			}
			html += '<tr' + (row.selected ? ' style="background:#f0fdf4;"' : '') + '>';
			html += '<td><code>' + esc(row.signal) + '</code><br/><span>' + esc(row.label) + '</span></td>';
			html += '<td>' + esc(row.cv) + '</td>';
			html += '<td>' + esc(row.coverage) + '%</td>';
			html += '<td>' + (row.selected ? esc((cfg.i18n && cfg.i18n.selected) || '✓') : '') + '</td>';
			html += '</tr>';
		});
		html += '</tbody></table>';
		$rep.html(html).show();
	}

	function runTune(scope, applySave) {
		var $status = statusForScope(scope);
		$status.text((cfg.i18n && cfg.i18n.running) || '…');
		reportEl(scope).hide().empty();

		ajaxPost({
			action: 'wsergo_auto_tune_macro_clusters',
			nonce: cfg.nonce,
			scope: scope,
			apply: applySave ? '1' : '',
		})
			.done(function (res) {
				if (!res || !res.success) {
					$status.text((res && res.data && res.data.message) || (cfg.i18n && cfg.i18n.error) || 'Error');
					return;
				}
				if (!res.data) {
					$status.text((cfg.i18n && cfg.i18n.error) || 'Error');
					return;
				}
				var data = res.data;
				loadFeaturesUi(scope, function () {
					applyFeatures(scope, data.features || []);
					$(kInputForScope(scope)).val(data.k || 4);
					renderReport(scope, data);
					if (data.saved) {
						$status.text((cfg.i18n && cfg.i18n.saved) || 'Saved');
					} else {
						$status.text((cfg.i18n && cfg.i18n.done) || 'Done');
					}
				});
			})
			.fail(function (xhr) {
				$status.text(messageFromXhr(xhr));
			});
	}

	function initLazyHosts() {
		$('.wsergo-cluster-features-host').each(function () {
			var scope = $(this).data('scope') || 'country';
			if ($(this).is(':visible')) {
				loadFeaturesUi(scope);
			}
		});
	}

	$(document).on('click', '.wsergo-auto-tune-clusters', function (e) {
		e.preventDefault();
		runTune($(this).data('scope') || 'country', false);
	});

	$(document).on('click', '.wsergo-auto-tune-clusters-save', function (e) {
		e.preventDefault();
		runTune($(this).data('scope') || 'country', true);
	});

	$(document).on('wsergo-scope-activated', function (_e, scope) {
		if (scope === 'country' || scope === 'city') {
			loadFeaturesUi(scope);
		}
	});

	$(document).ready(function () {
		if (!$('.wsergo-cluster-features-host').length) {
			return;
		}
		$(document).on('click', '.wsergo-country-inner-nav a[data-tab="tab-data"]', function () {
			loadFeaturesUi('country');
		});
		$(document).on('click', '.wsergo-city-inner-nav a[data-city-tab="tab-city-data"]', function () {
			loadFeaturesUi('city');
		});
		var hash = window.location.hash || '';
		if (hash === '#tab-data' || hash.indexOf('tab-data') >= 0 || hash === '#ergo-country') {
			loadFeaturesUi('country');
		}
		if (hash.indexOf('tab-city-data') >= 0 || hash === '#ergo-city') {
			loadFeaturesUi('city');
		}
		var $active = $('.wsergo-scope-panel.wsergo-scope-panel--active');
		if ($active.length && $active.attr('id') === 'wsergo-panel-country') {
			loadFeaturesUi('country');
		}
		initLazyHosts();
	});
}(jQuery));
