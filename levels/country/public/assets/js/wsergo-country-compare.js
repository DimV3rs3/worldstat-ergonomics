/**
 * Вкладка «Сравнение»: классификация + регрессия (совмещённый / отдельные графики, легенда, анализ).
 */
(function ($) {
	'use strict';

	var chartStore = {};
	var lastPayload = null;

	function cfg() {
		return window.wsergoCountryCompare || {};
	}

	function i18n(key, fallback) {
		var c = cfg();
		return (c.i18n && c.i18n[key]) ? c.i18n[key] : fallback;
	}

	function esc(s) {
		return String(s || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function parseAjaxJson(xhr) {
		if (xhr && xhr.responseJSON) {
			return xhr.responseJSON;
		}
		var raw = (xhr && xhr.responseText) ? xhr.responseText : '';
		raw = raw.replace(/^\uFEFF/, '');
		if (!raw) {
			return null;
		}
		try {
			return JSON.parse(raw);
		} catch (e) {
			return null;
		}
	}

	function ajaxErrorMessage(xhr, fallback) {
		var res = parseAjaxJson(xhr);
		if (res && res.data && res.data.message) {
			return res.data.message;
		}
		return fallback || i18n('networkError', 'Ошибка сети.');
	}

	function $explorer(uid) {
		if (!uid) {
			return $();
		}
		return $('#' + uid);
	}

	function selectedIso2($root) {
		var out = [];
		$root.find('.wsergo-country-compare-cb:checked').each(function () {
			var v = String($(this).val() || '').toUpperCase();
			if (v.length === 2) {
				out.push(v);
			}
		});
		return out;
	}

	function pageIso2($root) {
		var id = $root.attr('id');
		if (id) {
			var el = document.getElementById(id + '-json');
			if (el && el.textContent) {
				try {
					var payload = JSON.parse(el.textContent);
					if (payload && payload.highlight_iso2) {
						return String(payload.highlight_iso2).toUpperCase();
					}
				} catch (e) { /* ignore */ }
			}
		}
		var $pageRow = $root.find('.wsergo-country-compare-row.is-page-country').first();
		if ($pageRow.length) {
			return String($pageRow.attr('data-iso2') || '').toUpperCase();
		}
		return '';
	}

	var clsAnalysisTimer = null;

	function updateSelectionStatus($root) {
		var $st = $root.find('.wsergo-country-explorer__selection-status');
		if (!$st.length) {
			return;
		}
		$st.text(i18n('selected', 'Выбрано стран') + ': ' + selectedIso2($root).length);
	}

	function classificationAnalysisHost($root) {
		var id = $root.attr('id');
		if (!id) {
			return $();
		}
		return $('#' + id + '-cls-analysis');
	}

	function refreshClassificationAnalysis($root) {
		var $host = classificationAnalysisHost($root);
		if (!$host.length) {
			return;
		}
		var iso2 = selectedIso2($root);
		var c = cfg();
		var action = c.classificationAction || 'wsergo_country_classification_analysis';
		if (!iso2.length) {
			$host.empty().append('<p class="wsp-muted">' + esc(i18n('pickCountry', 'Отметьте страны в таблице.')) + '</p>');
			return;
		}
		$host.html('<p class="wsp-muted">' + esc(i18n('running', 'Расчёт…')) + '</p>');
		$.post(c.ajaxUrl || '', {
			action: action,
			nonce: c.nonce || '',
			iso2: iso2
		}).done(function (res) {
			$host.empty();
			if (res && res.success && res.data) {
				renderAnalysis($host, res.data, 'wsergo-compare-analysis--ergo');
			} else {
				$host.append('<p class="wsp-muted">' + esc((res && res.data && res.data.message) ? res.data.message : i18n('error', 'Ошибка')) + '</p>');
			}
		}).fail(function (xhr) {
			$host.empty().append('<p class="wsp-muted">' + esc(ajaxErrorMessage(xhr, i18n('error', 'Ошибка'))) + '</p>');
		});
	}

	function scheduleClassificationAnalysis($root) {
		if (clsAnalysisTimer) {
			clearTimeout(clsAnalysisTimer);
		}
		clsAnalysisTimer = setTimeout(function () {
			refreshClassificationAnalysis($root);
		}, 280);
	}

	function setStatus($root, text) {
		$root.find('.wsergo-country-compare-status').text(text || '');
	}

	function destroyCharts(keys) {
		var k;
		for (k = 0; k < keys.length; k++) {
			var id = keys[k];
			if (chartStore[id] && typeof chartStore[id].destroy === 'function') {
				chartStore[id].destroy();
			}
			delete chartStore[id];
			if (window.WSPChart && window.WSPChart.instances && window.WSPChart.instances[id]) {
				try {
					window.WSPChart.instances[id].destroy();
				} catch (e) { /* ignore */ }
				delete window.WSPChart.instances[id];
			}
		}
	}

	function renderChartCanvas(canvasId, chartCfg, legendItems) {
		var chartConfig = {
			type: chartCfg.type || 'line',
			labels: chartCfg.labels || [],
			datasets: chartCfg.datasets || [],
			xLabel: chartCfg.x_label || chartCfg.xLabel || '',
			yLabel: chartCfg.y_label || chartCfg.yLabel || '',
			legend: false
		};

		function tryRender(attempt) {
			var canvas = document.getElementById(canvasId);
			if (!canvas || typeof Chart === 'undefined' || !window.WSPChart) {
				if ((attempt || 0) < 50) {
					setTimeout(function () { tryRender((attempt || 0) + 1); }, 100);
				}
				return;
			}
			requestAnimationFrame(function () {
				window.WSPChart.render(canvasId, chartConfig);
				if (window.WSPChart.instances && window.WSPChart.instances[canvasId]) {
					chartStore[canvasId] = window.WSPChart.instances[canvasId];
					bindLegendToggles(canvasId, legendItems);
				}
			});
		}
		tryRender(0);
	}

	function bindLegendToggles(canvasId, legendItems) {
		if (!legendItems || !legendItems.length) {
			return;
		}
		var chart = chartStore[canvasId];
		if (!chart) {
			return;
		}
		$('.wsergo-compare-legend[data-chart="' + canvasId + '"] .wsergo-compare-legend__item').each(function () {
			var $btn = $(this);
			var idx = parseInt($btn.attr('data-dataset-index'), 10);
			$btn.off('click.wsergoLegend').on('click.wsergoLegend', function (e) {
				e.preventDefault();
				var meta = chart.getDatasetMeta(idx);
				if (!meta) {
					return;
				}
				meta.hidden = !meta.hidden;
				$btn.toggleClass('is-hidden', !!meta.hidden);
				chart.update();
			});
		});
	}

	function buildLegendHtml(canvasId, items) {
		var html = '<div class="wsergo-compare-legend" data-chart="' + esc(canvasId) + '">';
		html += '<div class="wsergo-compare-legend__title">' + esc(i18n('legendTitle', 'Серии (цвета)')) + '</div>';
		html += '<div class="wsergo-compare-legend__scroll">';
		var i;
		for (i = 0; i < items.length; i++) {
			var it = items[i];
			var dash = (it.series_type === 'trend') ? ' wsergo-compare-legend__swatch--dashed' : '';
			html += '<button type="button" class="wsergo-compare-legend__item" data-dataset-index="' + esc(String(it.index)) + '" title="' + esc(it.label) + '">';
			html += '<span class="wsergo-compare-legend__swatch' + dash + '" style="background-color:' + esc(it.color) + ';border-color:' + esc(it.color) + '"></span>';
			html += '<span class="wsergo-compare-legend__label">' + esc(it.label) + '</span>';
			html += '</button>';
		}
		html += '</div></div>';
		return html;
	}

	function renderAnalysis($container, analysis, extraClass) {
		if (!analysis || (!analysis.summary && !(analysis.insights && analysis.insights.length))) {
			return;
		}
		var cls = 'wsergo-compare-analysis' + (extraClass ? ' ' + extraClass : '');
		// NOTE: do not detect by substring "ergo" because "wsergo" contains it.
		// We only want the ergonomics title for the explicit ergo analysis block.
		var isErgo = !!(extraClass && /(^|\s)wsergo-compare-analysis--ergo(\s|$)/.test(extraClass));
		var title = isErgo
			? i18n('ergoAnalysisTitle', 'Классификация эргономичности')
			: i18n('analysisTitle', 'Аналитический вывод по показателю');
		var html = '<article class="' + cls + '">';
		html += '<h4 class="wsergo-compare-analysis__title">' + esc(title) + '</h4>';
		if (analysis.summary) {
			html += '<p class="wsergo-compare-analysis__summary">' + esc(analysis.summary) + '</p>';
		}
		if (analysis.highlights && analysis.highlights.length) {
			html += '<div class="wsergo-compare-analysis__highlights">';
			analysis.highlights.forEach(function (h) {
				html += '<div class="wsergo-compare-analysis__highlight"><span class="wsergo-compare-analysis__hl-label">' + esc(h.label) + '</span>';
				html += '<strong>' + esc(h.value) + '</strong></div>';
			});
			html += '</div>';
		}
		if (analysis.insights && analysis.insights.length) {
			html += '<ul class="wsergo-compare-analysis__insights">';
			analysis.insights.forEach(function (line) {
				html += '<li>' + esc(line) + '</li>';
			});
			html += '</ul>';
		}
		html += '</article>';
		$container.append(html);
	}

	function renderCountryStats($container, countries) {
		if (!countries || !countries.length) {
			return;
		}
		var html = '<div class="wsergo-compare-stats"><h4 class="wsergo-compare-stats__title">' + esc(i18n('statsTitle', 'Показатели по странам')) + '</h4><table class="wsp-table wsergo-compare-stats__table"><thead><tr>';
		html += '<th>' + esc(i18n('countryCol', 'Страна')) + '</th><th>' + esc(i18n('r2', 'R²')) + '</th><th>' + esc(i18n('trend', 'Тренд')) + '</th><th>' + esc(i18n('pctChange', 'Δ к базе, %')) + '</th><th>' + esc(i18n('forecast', 'Прогноз')) + ' 2050</th></tr></thead><tbody>';
		countries.forEach(function (c) {
			if (!c.ok || !c.regression || !c.regression.stats) {
				return;
			}
			var st = c.regression.stats;
			var fc = st.forecast ? (st.forecast.value + ' (' + st.forecast.year + ' ' + i18n('year', 'г.') + ')') : '—';
			var pct = (c.pct_change !== undefined && c.pct_change !== null) ? (c.pct_change + '%') : '—';
			html += '<tr><td>' + esc(c.name || c.iso2) + '</td><td>' + esc(st.r2) + '</td><td>' + esc(st.direction) + '</td><td>' + esc(pct) + '</td><td>' + esc(fc) + '</td></tr>';
		});
		html += '</tbody></table></div>';
		$container.append(html);
	}

	function renderViewToolbar($res, mode) {
		var html = '<div class="wsergo-compare-view-toolbar" role="group">';
		html += '<button type="button" class="wsp-btn wsp-btn-sm wsergo-compare-view-btn' + (mode === 'combined' ? ' is-active' : '') + '" data-view="combined">' + esc(i18n('viewCombined', 'Совмещённый')) + '</button>';
		html += '<button type="button" class="wsp-btn wsp-btn-sm wsergo-compare-view-btn' + (mode === 'separate' ? ' is-active' : '') + '" data-view="separate">' + esc(i18n('viewSeparate', 'Отдельные графики')) + '</button>';
		html += '</div>';
		$res.append(html);
	}

	function renderCombinedBlock($res, data) {
		var canvasId = 'wsergo-cc-combined-' + Date.now();
		var chart = data.chart || {};
		var html = '<div class="wsergo-compare-charts wsergo-compare-charts--combined">';
		if (data.title) {
			html += '<h4 class="wsp-chart-title">' + esc(data.title) + '</h4>';
		}
		if (data.description) {
			html += '<p class="wsp-muted">' + esc(data.description) + '</p>';
		}
		html += buildLegendHtml(canvasId, data.legend || []);
		html += '<div class="wsp-chart-canvas-wrap wsergo-compare-chart-wrap" style="position:relative;height:' + (chart.height || 380) + 'px;">';
		html += '<canvas id="' + canvasId + '"></canvas></div></div>';
		$res.append(html);
		renderChartCanvas(canvasId, chart, data.legend || []);
	}

	function renderSeparateBlock($res, data) {
		var list = data.charts_separate || [];
		if (!list.length) {
			return;
		}
		var $wrap = $('<div class="wsergo-compare-charts wsergo-compare-charts--separate"></div>');
		if (data.title) {
			$wrap.append('<h4 class="wsp-chart-title">' + esc(data.title) + '</h4>');
		}
		list.forEach(function (entry, idx) {
			var canvasId = 'wsergo-cc-sep-' + idx + '-' + Date.now();
			var chart = entry.chart || {};
			var legend = [];
			if (chart.datasets) {
				chart.datasets.forEach(function (ds, di) {
					legend.push({
						index: di,
						label: ds.label || '',
						color: ds.color || '#3366cc',
						country: entry.name,
						iso2: entry.iso2,
						series_type: ds.series_type || ''
					});
				});
			}
			var block = '<div class="wsergo-compare-chart-card">';
			block += '<h5 class="wsergo-compare-chart-card__title"><span class="wsergo-compare-chart-card__dot" style="background:' + esc(entry.color || '#3366cc') + '"></span>' + esc(entry.name) + '</h5>';
			block += buildLegendHtml(canvasId, legend);
			block += '<div class="wsp-chart-canvas-wrap wsergo-compare-chart-wrap" style="position:relative;height:' + (chart.height || 260) + 'px;">';
			block += '<canvas id="' + canvasId + '"></canvas></div></div>';
			$wrap.append(block);
			renderChartCanvas(canvasId, chart, legend);
		});
		$res.append($wrap);
	}

	function paintResults($root, data, viewMode) {
		var $res = $root.find('.wsergo-country-compare-results');
		var oldKeys = $res.data('chartKeys') || [];
		destroyCharts(oldKeys);

		$res.empty().attr('data-view-mode', viewMode);
		lastPayload = data;

		if (!data || !data.ok) {
			$res.append('<p class="wsp-ca-notice">' + esc(data && data.message ? data.message : i18n('error', 'Ошибка')) + '</p>');
			return;
		}

		renderViewToolbar($res, viewMode);

		var keys = [];
		if (viewMode === 'separate') {
			renderSeparateBlock($res, data);
		} else {
			renderCombinedBlock($res, data);
			$res.find('canvas').each(function () {
				keys.push(this.id);
			});
		}
		$res.find('canvas').each(function () {
			if (keys.indexOf(this.id) < 0) {
				keys.push(this.id);
			}
		});
		$res.data('chartKeys', keys);

		renderCountryStats($res, data.countries || []);
		renderAnalysis($res, data.analysis || {}, 'wsergo-compare-analysis--trend');
	}

	function runRegression($root) {
		var c = cfg();
		var metricId = $root.find('.wsergo-country-compare-metric').val();
		var iso2 = selectedIso2($root);

		if (!metricId) {
			setStatus($root, i18n('pickMetric', 'Выберите показатель.'));
			return;
		}
		if (!iso2.length) {
			setStatus($root, i18n('pickCountry', 'Отметьте страны.'));
			return;
		}

		setStatus($root, i18n('running', 'Расчёт…'));

		$.ajax({
			url: c.ajaxUrl || '/wp-admin/admin-ajax.php',
			type: 'POST',
			dataType: 'json',
			data: {
				action: c.action || 'wsergo_country_compare_trends',
				nonce: c.nonce,
				metric_id: metricId,
				iso2: iso2,
				page_iso2: pageIso2($root)
			}
		}).done(function (res) {
			if (res && res.success && res.data) {
				setStatus($root, i18n('done', 'Готово'));
				var mode = $root.find('.wsergo-country-compare-results').attr('data-view-mode') || 'combined';
				paintResults($root, res.data, mode);
			} else {
				setStatus($root, i18n('error', 'Ошибка'));
				paintResults($root, (res && res.data) ? res.data : { ok: false, message: i18n('error', 'Ошибка') }, 'combined');
			}
		}).fail(function (xhr) {
			setStatus($root, ajaxErrorMessage(xhr, i18n('error', 'Ошибка')));
		});
	}

	function initExplorer($root) {
		if (!$root.length || $root.attr('data-wsergo-compare-ready') === '1') {
			return;
		}
		$root.attr('data-wsergo-compare-ready', '1');
		updateSelectionStatus($root);
		scheduleClassificationAnalysis($root);
	}

	function scan(ctx) {
		var $ctx = ctx ? $(ctx) : $(document);
		$ctx.find('[data-wsergo-country-explorer="1"]').each(function () {
			initExplorer($(this));
		});
	}

	function onTabLoaded(panel) {
		if (panel) {
			scan(panel);
			return;
		}
		scan(document);
	}

	/* Делегирование — работает после AJAX-загрузки вкладки */
	$(document).on('click', '.wsergo-country-compare-select-all', function (e) {
		e.preventDefault();
		var uid = $(this).attr('data-explorer') || $(this).closest('.wsergo-country-compare-toolbar').attr('data-explorer');
		var $root = $explorer(uid);
		if (!$root.length) {
			return;
		}
		$root.find('.wsergo-country-compare-cb').prop('checked', true);
		updateSelectionStatus($root);
		scheduleClassificationAnalysis($root);
	});

	$(document).on('click', '.wsergo-country-compare-clear-all', function (e) {
		e.preventDefault();
		var uid = $(this).attr('data-explorer') || $(this).closest('.wsergo-country-compare-toolbar').attr('data-explorer');
		var $root = $explorer(uid);
		if (!$root.length) {
			return;
		}
		$root.find('.wsergo-country-compare-cb').prop('checked', false);
		var $pageCb = $root.find('.wsergo-country-compare-row.is-page-country .wsergo-country-compare-cb');
		if ($pageCb.length) {
			$pageCb.prop('checked', true);
		}
		updateSelectionStatus($root);
		scheduleClassificationAnalysis($root);
	});

	$(document).on('change', '.wsergo-country-compare-cb', function () {
		var $root = $(this).closest('[data-wsergo-country-explorer="1"]');
		updateSelectionStatus($root);
		scheduleClassificationAnalysis($root);
	});

	$(document).on('click', '.wsergo-country-compare-run', function (e) {
		e.preventDefault();
		var uid = $(this).attr('data-explorer');
		var $root = $explorer(uid);
		if ($root.length) {
			runRegression($root);
		}
	});

	$(document).on('click', '.wsergo-compare-view-btn', function (e) {
		e.preventDefault();
		if (!lastPayload || !lastPayload.ok) {
			return;
		}
		var view = $(this).attr('data-view') || 'combined';
		var $res = $(this).closest('.wsergo-country-compare-results');
		var $root = $res.closest('[data-wsergo-country-explorer="1"]');
		$res.find('.wsergo-compare-view-btn').removeClass('is-active');
		$(this).addClass('is-active');
		paintResults($root, lastPayload, view);
	});

	$(function () {
		scan(document);
	});

	$(document).on('wsp:tab:loaded', function (e, tabId, iso2, $panel) {
		if ($panel && $panel.length) {
			onTabLoaded($panel[0]);
			return;
		}
		if (e && e.originalEvent && e.originalEvent.detail && e.originalEvent.detail.panel) {
			onTabLoaded(e.originalEvent.detail.panel);
			return;
		}
		onTabLoaded(null);
	});

	document.addEventListener('wsp:tab:loaded', function (ev) {
		var panel = ev && ev.detail ? ev.detail.panel : null;
		onTabLoaded(panel);
	});

	window.wsergoCountryCompareInit = onTabLoaded;
}(jQuery));
