/**
 * Автоподбор k-means и ленивая загрузка списка признаков в админке.
 */
(function ($) {
	'use strict';

	var cfg = window.wsergoClusterTune || {};
	var loadedScopes = {};

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
		if (!$host.length) {
			if (typeof onReady === 'function') {
				onReady();
			}
			return;
		}
		if ($host.find('input[type="checkbox"]').length) {
			loadedScopes[scope] = 'done';
			if (typeof onReady === 'function') {
				onReady();
			}
			return;
		}
		if (!cfg.ajaxUrl) {
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
		$.post(cfg.ajaxUrl, {
			action: 'wsergo_load_cluster_features_ui',
			nonce: cfg.nonce,
			scope: scope
		})
			.done(function (res) {
				if (res && res.success && res.data && res.data.html) {
					$host.html(res.data.html);
				} else {
					$host.html('<p class="wsp-muted">' + esc((cfg.i18n && cfg.i18n.error) || 'Error') + '</p>');
				}
				loadedScopes[scope] = 'done';
				$host.trigger('wsergo-cluster-features-ready');
				if (typeof onReady === 'function') {
					onReady();
				}
			})
			.fail(function () {
				$host.html('<p class="wsp-muted">' + esc((cfg.i18n && cfg.i18n.error) || 'Error') + '</p>');
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

		$.post(cfg.ajaxUrl, {
			action: 'wsergo_auto_tune_macro_clusters',
			nonce: cfg.nonce,
			scope: scope,
			apply: applySave ? '1' : ''
		})
			.done(function (res) {
				if (!res || !res.success || !res.data) {
					$status.text((res && res.data && res.data.message) || (cfg.i18n && cfg.i18n.error) || 'Error');
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
			.fail(function () {
				$status.text((cfg.i18n && cfg.i18n.error) || 'Error');
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

	$(document).ready(function () {
		if (!$('.wsergo-cluster-features-host').length) {
			return;
		}
		// Загрузка списка при открытии вкладки «Данные».
		$(document).on('click', '.wsergo-country-inner-nav a[data-tab="tab-data"]', function () {
			loadFeaturesUi('country');
		});
		$(document).on('click', '.wsergo-city-inner-nav a[data-city-tab="tab-city-data"]', function () {
			loadFeaturesUi('city');
		});
		var hash = window.location.hash || '';
		if (hash === '#tab-data' || hash.indexOf('tab-data') >= 0) {
			loadFeaturesUi('country');
		}
		if (hash.indexOf('tab-city-data') >= 0) {
			loadFeaturesUi('city');
		}
		initLazyHosts();
	});
}(jQuery));
