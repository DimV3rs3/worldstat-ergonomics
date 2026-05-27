/**
 * Классификация: шкалы 0–100, 2D-график, аналитика (вкладка «Сравнение»).
 */
(function ($) {
	'use strict';

	function cfg($root) {
		var ergoOnly = $root && $root.length && String($root.attr('data-wsergo-country-classification')) === '1';
		if (ergoOnly) {
			return window.wsergoCountryClassification || window.wsergoCountryCompare || {};
		}
		return window.wsergoCountryCompare || window.wsergoCountryClassification || {};
	}

	function analysisTitleKey($root) {
		var key = $root.data('clsAnalysisTitle');
		return key ? String(key) : 'clsVisTitle';
	}

	function ladderHintKey($root) {
		var key = $root.data('clsLadderHint');
		return key ? String(key) : 'ladderHint';
	}

	function i18n(key, fallback, $root) {
		var c = cfg($root);
		return (c.i18n && c.i18n[key]) ? c.i18n[key] : fallback;
	}

	function esc(s) {
		return String(s || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function readPayload($root) {
		var id = $root.attr('id');
		if (!id) {
			return null;
		}
		var el = document.getElementById(id + '-json');
		if (!el || !el.textContent) {
			return null;
		}
		try {
			return JSON.parse(el.textContent);
		} catch (e) {
			return null;
		}
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

	function showUnselectedCountries($root) {
		var uid = $root.attr('id') || '';
		var $cb = uid ? $('.wsergo-country-compare-show-unselected[data-explorer="' + uid + '"]') : $();
		if (!$cb.length) {
			return true;
		}
		return $cb.prop('checked') !== false;
	}

	function countryChartRole(c, selected, highlightIso) {
		var iso = String(c.iso2 || '').toUpperCase();
		if (highlightIso && iso === highlightIso) {
			return 'highlight';
		}
		if (selected.length > 0 && selected.indexOf(iso) >= 0) {
			return 'selected';
		}
		if (selected.length > 0) {
			return 'dim';
		}
		return 'neutral';
	}

	function filterCountriesForChart(countries, selected, highlightIso, showUnselected) {
		if (showUnselected || selected.length === 0) {
			return countries;
		}
		return countries.filter(function (c) {
			var role = countryChartRole(c, selected, highlightIso);
			return role === 'highlight' || role === 'selected';
		});
	}

	function dotFillStyle(c, role) {
		if (role === 'dim' || role === 'neutral') {
			return '#b8c4d0';
		}
		return c.tier_color || '#64748b';
	}

	function renderAnalysisBlock($host, analysis, titleKey, extraClass, $root) {
		if (!$host.length) {
			return;
		}
		$host.empty();
		if (!analysis || (!analysis.summary && !(analysis.insights && analysis.insights.length))) {
			return;
		}
		var title = i18n(titleKey || 'clsVisTitle', 'Аналитика классификации', $root);
		var cls = 'wsergo-compare-analysis wsergo-compare-analysis--cls-vis' + (extraClass ? ' ' + extraClass : '');
		var html = '<article class="' + cls + '">';
		html += '<h4 class="wsergo-compare-analysis__title">' + esc(title) + '</h4>';
		if (analysis.summary) {
			html += '<p class="wsergo-compare-analysis__summary">' + esc(analysis.summary) + '</p>';
		}
		if (analysis.highlights && analysis.highlights.length) {
			html += '<div class="wsergo-compare-analysis__highlights">';
			analysis.highlights.forEach(function (h) {
				html += '<div class="wsergo-compare-analysis__highlight"><span class="wsergo-compare-analysis__hl-label">' + esc(h.label) + '</span><strong>' + esc(h.value) + '</strong></div>';
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
		$host.append(html);
	}

	function dotClasses(c, selected, highlightIso) {
		var role = countryChartRole(c, selected, highlightIso);
		var cls = 'wsergo-cls-ladder__dot';
		if (role === 'highlight') {
			cls += ' is-highlight';
		} else if (role === 'selected') {
			cls += ' is-selected';
		} else if (role === 'dim' || role === 'neutral') {
			cls += ' is-dim';
		}
		return cls;
	}

	function ladderCountries(axis, $root, payload) {
		var countries = axis.countries || [];
		if (payload && payload.ladder_single_country) {
			var focus = String((payload.highlight_iso2 || '')).toUpperCase();
			if (focus) {
				countries = countries.filter(function (c) {
					return String(c.iso2 || '').toUpperCase() === focus;
				});
			}
		}
		return countries;
	}

	function renderAxisRow(axis, selected, highlightIso, $root, payload) {
		var countries = ladderCountries(axis, $root, payload);
		countries = filterCountriesForChart(countries, selected, highlightIso, showUnselectedCountries($root));
		var html = '<div class="wsergo-cls-ladder__row" data-axis="' + esc(axis.key) + '">';
		html += '<div class="wsergo-cls-ladder__label">' + esc(axis.label) + '</div>';
		html += '<div class="wsergo-cls-ladder__track-wrap">';
		html += '<div class="wsergo-cls-ladder__track">';
		html += '<div class="wsergo-cls-ladder__markers">';

		var ci;
		for (ci = 0; ci < countries.length; ci++) {
			var c = countries[ci];
			var left = Math.max(0, Math.min(100, parseFloat(c.pct != null ? c.pct : c.score) || 0));
			var tip = (c.name || c.iso2) + ' — ' + i18n('scoreLabel', 'Балл', $root) + ': ' + c.score
				+ ', ' + i18n('levelLabel', 'Уровень', $root) + ': ' + (c.tier_label || '');
			var role = countryChartRole(c, selected, highlightIso);
			var cls = dotClasses(c, selected, highlightIso);
			if (countries.length === 1) {
				cls += ' is-highlight is-single';
			}
			html += '<button type="button" class="' + cls + '" style="left:' + esc(String(left)) + '%;background:' + esc(dotFillStyle(c, role)) + ';"';
			html += ' data-tip="' + esc(tip) + '" aria-label="' + esc(tip) + '"></button>';
		}

		html += '</div></div>';
		html += '<div class="wsergo-cls-ladder__scale">';
		html += '<span>0</span><span>100</span>';
		html += '</div></div></div>';
		return html;
	}

	function renderLegend(bands) {
		var html = '<div class="wsergo-cls-ladder__legend">';
		var i;
		for (i = 0; i < bands.length; i++) {
			var b = bands[i];
			html += '<span class="wsergo-cls-ladder__legend-item"><span class="wsergo-cls-ladder__legend-swatch" style="background:' + esc(b.color) + '"></span>' + esc(b.label) + '</span>';
		}
		html += '</div>';
		return html;
	}

	function bindTooltips($host, selector) {
		var sel = selector || '.wsergo-cls-ladder__dot, .wsergo-cls-scatter__dot';
		$host.find(sel).off('mouseenter.wsergoCls mouseleave.wsergoCls focus.wsergoCls blur.wsergoCls');
		$host.find(sel).on('mouseenter.wsergoCls focus.wsergoCls', function () {
			var tip = $(this).attr('data-tip') || '';
			if (!tip) {
				return;
			}
			var $t = $host.find('.wsergo-cls-ladder__tooltip');
			if (!$t.length) {
				$t = $('<div class="wsergo-cls-ladder__tooltip" role="tooltip"></div>');
				$host.append($t);
			}
			$t.text(tip).addClass('is-visible');
		}).on('mouseleave.wsergoCls blur.wsergoCls', function () {
			$host.find('.wsergo-cls-ladder__tooltip').removeClass('is-visible');
		});
	}

	function renderLadder($root) {
		var $host = $('#' + $root.attr('id') + '-cls-ladder');
		if (!$host.length) {
			return;
		}
		var payload = readPayload($root);
		var ladder = payload && payload.ladder_chart ? payload.ladder_chart : null;
		if (!ladder || !ladder.axes || !ladder.axes.length) {
			$host.empty();
			return;
		}

		var selected = selectedIso2($root);
		var highlight = String(ladder.highlight_iso2 || payload.highlight_iso2 || '').toUpperCase();
		var bands = ladder.bands || [];
		var html = '<div class="wsergo-cls-ladder">';
		html += '<h4 class="wsergo-cls-ladder__title">' + esc(i18n('ladderTitle', 'Шкалы классификации по критериям', $root)) + '</h4>';
		html += '<p class="wsp-muted wsergo-cls-ladder__hint">' + esc(i18n(ladderHintKey($root), 'Шкала 0–100', $root)) + '</p>';
		html += renderLegend(bands);
		var ai;
		for (ai = 0; ai < ladder.axes.length; ai++) {
			html += renderAxisRow(ladder.axes[ai], selected, highlight, $root, payload);
		}
		html += '</div>';
		$host.html(html);
		bindTooltips($host);
	}

	var scatterState = {};

	function axisOptionsHtml(options, selectedKey) {
		var html = '';
		var i;
		for (i = 0; i < options.length; i++) {
			var o = options[i];
			html += '<option value="' + esc(o.key) + '"' + (o.key === selectedKey ? ' selected' : '') + '>' + esc(o.label) + '</option>';
		}
		return html;
	}

	function drawScatterCanvas(canvas, scatter, axisX, axisY, selected, highlightIso, showUnselected, $root) {
		var ctx = canvas.getContext('2d');
		var w = canvas.width;
		var h = canvas.height;
		var pad = { l: 48, r: 16, t: 16, b: 40 };
		var plotW = w - pad.l - pad.r;
		var plotH = h - pad.t - pad.b;

		ctx.clearRect(0, 0, w, h);
		ctx.fillStyle = '#f8fafc';
		ctx.fillRect(pad.l, pad.t, plotW, plotH);
		ctx.strokeStyle = '#cbd5e1';
		ctx.lineWidth = 1;
		ctx.strokeRect(pad.l + 0.5, pad.t + 0.5, plotW - 1, plotH - 1);

		var gi;
		for (gi = 0; gi <= 4; gi++) {
			var gy = pad.t + (plotH * gi) / 4;
			ctx.beginPath();
			ctx.moveTo(pad.l, gy);
			ctx.lineTo(pad.l + plotW, gy);
			ctx.strokeStyle = '#e2e8f0';
			ctx.stroke();
		}
		for (gi = 0; gi <= 4; gi++) {
			var gx = pad.l + (plotW * gi) / 4;
			ctx.beginPath();
			ctx.moveTo(gx, pad.t);
			ctx.lineTo(gx, pad.t + plotH);
			ctx.stroke();
		}

		ctx.fillStyle = '#64748b';
		ctx.font = '11px sans-serif';
		ctx.textAlign = 'center';
		var xLabel = '';
		var yLabel = '';
		var opts = scatter.axis_options || [];
		var oi;
		for (oi = 0; oi < opts.length; oi++) {
			if (opts[oi].key === axisX) {
				xLabel = opts[oi].label;
			}
			if (opts[oi].key === axisY) {
				yLabel = opts[oi].label;
			}
		}
		ctx.fillText(xLabel + ' (0–100)', pad.l + plotW / 2, h - 8);
		ctx.save();
		ctx.translate(14, pad.t + plotH / 2);
		ctx.rotate(-Math.PI / 2);
		ctx.fillText(yLabel + ' (0–100)', 0, 0);
		ctx.restore();

		var countries = filterCountriesForChart(scatter.countries || [], selected, highlightIso, showUnselected);
		var dimPoints = [];
		var activePoints = [];
		var ci;
		for (ci = 0; ci < countries.length; ci++) {
			var c = countries[ci];
			var ax = c.axes || {};
			if (ax[axisX] == null || ax[axisY] == null) {
				continue;
			}
			var xv = Math.max(0, Math.min(100, parseFloat(ax[axisX])));
			var yv = Math.max(0, Math.min(100, parseFloat(ax[axisY])));
			var px = pad.l + (xv / 100) * plotW;
			var py = pad.t + plotH - (yv / 100) * plotH;
			var role = countryChartRole(c, selected, highlightIso);
			var pt = {
				c: c,
				role: role,
				px: px,
				py: py,
				tip: (c.name || c.iso2) + ' — ' + i18n('ergoIndex', 'Сводный балл', $root) + ': ' + (c.composite != null ? c.composite : '—')
			};
			if (role === 'dim' || role === 'neutral') {
				dimPoints.push(pt);
			} else {
				activePoints.push(pt);
			}
		}

		for (ci = 0; ci < dimPoints.length; ci++) {
			var dp = dimPoints[ci];
			ctx.save();
			ctx.globalAlpha = 0.36;
			ctx.beginPath();
			ctx.arc(dp.px, dp.py, 4, 0, Math.PI * 2);
			ctx.fillStyle = '#b8c4d0';
			ctx.fill();
			ctx.strokeStyle = 'rgba(255, 255, 255, 0.65)';
			ctx.lineWidth = 1;
			ctx.stroke();
			ctx.restore();
		}

		for (ci = 0; ci < activePoints.length; ci++) {
			var p = activePoints[ci];
			var r = p.role === 'highlight' ? 7 : 6;
			ctx.save();
			ctx.beginPath();
			ctx.arc(p.px, p.py, r, 0, Math.PI * 2);
			ctx.fillStyle = dotFillStyle(p.c, p.role);
			ctx.fill();
			ctx.strokeStyle = '#fff';
			ctx.lineWidth = p.role === 'highlight' ? 2 : 1.5;
			ctx.stroke();
			ctx.restore();
		}

		canvas._wsergoScatterPoints = dimPoints.concat(activePoints);
	}

	function renderScatter($root) {
		var $host = $('#' + $root.attr('id') + '-cls-scatter');
		if (!$host.length) {
			return;
		}
		var payload = readPayload($root);
		var scatter = payload && payload.scatter_chart ? payload.scatter_chart : null;
		if (!scatter || !scatter.countries || !scatter.countries.length) {
			$host.empty();
			return;
		}

		var uid = $root.attr('id');
		var state = scatterState[uid] || {
			axisX: scatter.default_x || 'F',
			axisY: scatter.default_y || 'S'
		};
		scatterState[uid] = state;

		var html = '<div class="wsergo-cls-scatter">';
		html += '<h4 class="wsergo-cls-scatter__title">' + esc(i18n('scatterTitle', 'Сравнение по двум осям', $root)) + '</h4>';
		html += '<p class="wsp-muted wsergo-cls-scatter__hint">' + esc(i18n('scatterHint', '', $root)) + '</p>';
		html += '<div class="wsergo-cls-scatter__controls">';
		html += '<label><span>' + esc(i18n('axisX', 'Ось X', $root)) + '</span><select class="wsp-select wsergo-cls-scatter-axis-x">' + axisOptionsHtml(scatter.axis_options, state.axisX) + '</select></label>';
		html += '<label><span>' + esc(i18n('axisY', 'Ось Y', $root)) + '</span><select class="wsp-select wsergo-cls-scatter-axis-y">' + axisOptionsHtml(scatter.axis_options, state.axisY) + '</select></label>';
		html += '<span class="wsergo-cls-scatter__err wsp-muted" style="display:none;"></span>';
		html += '</div>';
		html += '<div class="wsergo-cls-scatter__canvas-wrap"><canvas class="wsergo-cls-scatter__canvas" width="640" height="380"></canvas></div>';
		html += '</div>';
		$host.html(html);

		var $canvas = $host.find('canvas');
		var selected = selectedIso2($root);
		var highlight = String(scatter.highlight_iso2 || payload.highlight_iso2 || '').toUpperCase();

		function redraw() {
			if (state.axisX === state.axisY) {
				$host.find('.wsergo-cls-scatter__err').text(i18n('sameAxisError', 'Выберите разные оси.', $root)).show();
			} else {
				$host.find('.wsergo-cls-scatter__err').hide();
			}
			drawScatterCanvas($canvas[0], scatter, state.axisX, state.axisY, selected, highlight, showUnselectedCountries($root), $root);
			bindScatterHover($host, $canvas[0]);
		}

		$host.find('.wsergo-cls-scatter-axis-x').on('change', function () {
			state.axisX = $(this).val();
			redraw();
		});
		$host.find('.wsergo-cls-scatter-axis-y').on('change', function () {
			state.axisY = $(this).val();
			redraw();
		});

		redraw();
	}

	function bindScatterHover($host, canvas) {
		var $tip = $host.find('.wsergo-cls-scatter__hover-tip');
		if (!$tip.length) {
			$tip = $('<div class="wsergo-cls-scatter__hover-tip" role="tooltip"></div>');
			$host.find('.wsergo-cls-scatter__canvas-wrap').append($tip);
		}
		$(canvas).off('mousemove.wsergoScatter mouseleave.wsergoScatter');
		$(canvas).on('mousemove.wsergoScatter', function (ev) {
			var rect = canvas.getBoundingClientRect();
			var sx = canvas.width / rect.width;
			var sy = canvas.height / rect.height;
			var mx = (ev.clientX - rect.left) * sx;
			var my = (ev.clientY - rect.top) * sy;
			var pts = canvas._wsergoScatterPoints || [];
			var hit = null;
			var i;
			for (i = 0; i < pts.length; i++) {
				var dx = pts[i].px - mx;
				var dy = pts[i].py - my;
				if (dx * dx + dy * dy < 100) {
					hit = pts[i];
					break;
				}
			}
			if (hit) {
				$tip.text(hit.tip).css({ left: (hit.px / sx) + 'px', top: (hit.py / sy - 28) + 'px' }).addClass('is-visible');
			} else {
				$tip.removeClass('is-visible');
			}
		}).on('mouseleave.wsergoScatter', function () {
			$tip.removeClass('is-visible');
		});
	}

	function refreshVisualAnalysis($root) {
		var $host = $('#' + $root.attr('id') + '-cls-analysis');
		if (!$host.length) {
			return;
		}
		var payload = readPayload($root);
		var iso2 = selectedIso2($root);
		if (!iso2.length && payload && payload.highlight_iso2) {
			iso2 = [String(payload.highlight_iso2).toUpperCase()];
		}
		if (iso2.length === 0 && payload && payload.classification_visual) {
			renderAnalysisBlock($host, payload.classification_visual, analysisTitleKey($root), '', $root);
			return;
		}
		$host.html('<p class="wsp-muted">' + esc(i18n('running', 'Расчёт…', $root)) + '</p>');
		var pageIso = (payload && payload.highlight_iso2) ? String(payload.highlight_iso2).toUpperCase() : '';
		var c = cfg($root);
		$.post(c.ajaxUrl || '', {
			action: c.classificationAction || 'wsergo_country_classification_analysis',
			nonce: c.nonce || '',
			iso2: iso2,
			page_iso2: pageIso,
			visual: '1'
		}).done(function (res) {
			$host.empty();
			if (res && res.success && res.data) {
				renderAnalysisBlock($host, res.data, analysisTitleKey($root), '', $root);
			} else {
				$host.append('<p class="wsp-muted">' + esc((res && res.data && res.data.message) ? res.data.message : i18n('error', 'Ошибка', $root)) + '</p>');
			}
		}).fail(function (xhr) {
			var msg = i18n('networkError', 'Ошибка сети. Попробуйте снова.', $root);
			if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
				msg = xhr.responseJSON.data.message;
			} else if (xhr && xhr.responseText) {
				try {
					var parsed = JSON.parse(xhr.responseText.replace(/^\uFEFF/, ''));
					if (parsed && parsed.data && parsed.data.message) {
						msg = parsed.data.message;
					}
				} catch (e) { /* ignore */ }
			}
			$host.html('<p class="wsp-ca-notice">' + esc(msg) + '</p>');
		});
	}

	function refreshForRoot($root) {
		if (!$root.length) {
			return;
		}
		if ($('#' + $root.attr('id') + '-cls-scatter').length) {
			renderScatter($root);
		}
		renderLadder($root);
		refreshVisualAnalysis($root);
	}

	window.wsergoCountryClassificationLadderRefresh = refreshForRoot;

	function scan(ctx) {
		var $ctx = ctx ? $(ctx) : $(document);
		$ctx.find('[data-wsergo-country-explorer="1"], [data-wsergo-country-classification="1"]').each(function () {
			refreshForRoot($(this));
		});
	}

	$(document).on('change', '.wsergo-country-compare-cb', function () {
		var $root = $(this).closest('[data-wsergo-country-explorer="1"]');
		refreshForRoot($root);
	});

	$(document).on('change', '.wsergo-country-compare-show-unselected', function () {
		var uid = $(this).attr('data-explorer');
		if (uid) {
			refreshForRoot($('#' + uid));
		}
	});

	$(document).on('click', '.wsergo-country-compare-select-all, .wsergo-country-compare-clear-all', function () {
		var uid = $(this).attr('data-explorer');
		setTimeout(function () {
			if (uid) {
				refreshForRoot($('#' + uid));
			}
		}, 50);
	});

	$(function () {
		scan(document);
	});

	$(document).on('wsp:tab:loaded', function (e, tabId, iso2, $panel) {
		if ($panel && $panel.length) {
			scan($panel[0]);
		}
	});

	document.addEventListener('wsp:tab:loaded', function (ev) {
		var panel = ev && ev.detail ? ev.detail.panel : null;
		if (panel) {
			scan(panel);
		}
	});
}(jQuery));
