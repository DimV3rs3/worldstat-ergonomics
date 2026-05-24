/**
 * Превью k-means: карта стран и графики (админка «Эргономичность страны»).
 */
(function ($) {
	'use strict';

	var cfg = window.wsergoClusterViz || {};
	var timers = {};
	var mapState = {};

	var ID_A2 = {
		4: 'AF', 8: 'AL', 12: 'DZ', 20: 'AD', 24: 'AO', 28: 'AG', 32: 'AR', 36: 'AU', 40: 'AT', 31: 'AZ',
		44: 'BS', 48: 'BH', 50: 'BD', 51: 'AM', 52: 'BB', 56: 'BE', 64: 'BT', 68: 'BO', 70: 'BA', 72: 'BW',
		76: 'BR', 84: 'BZ', 90: 'SB', 96: 'BN', 100: 'BG', 104: 'MM', 108: 'BI', 112: 'BY', 116: 'KH',
		120: 'CM', 124: 'CA', 132: 'CV', 136: 'KY', 140: 'CF', 144: 'LK', 148: 'TD', 152: 'CL', 156: 'CN',
		158: 'TW', 170: 'CO', 174: 'KM', 175: 'YT', 178: 'CG', 180: 'CD', 188: 'CR', 191: 'HR', 192: 'CU',
		196: 'CY', 203: 'CZ', 204: 'BJ', 208: 'DK', 212: 'DM', 214: 'DO', 218: 'EC', 222: 'SV', 226: 'GQ',
		231: 'ET', 232: 'ER', 233: 'EE', 234: 'FO', 238: 'FK', 242: 'FJ', 246: 'FI', 250: 'FR', 254: 'GF',
		258: 'PF', 262: 'DJ', 266: 'GA', 268: 'GE', 270: 'GM', 275: 'PS', 276: 'DE', 288: 'GH', 292: 'GI',
		296: 'KI', 300: 'GR', 304: 'GL', 308: 'GD', 312: 'GP', 320: 'GT', 324: 'GN', 328: 'GY', 332: 'HT',
		336: 'VA', 340: 'HN', 344: 'HK', 348: 'HU', 352: 'IS', 356: 'IN', 360: 'ID', 364: 'IR', 368: 'IQ',
		372: 'IE', 376: 'IL', 380: 'IT', 384: 'CI', 388: 'JM', 392: 'JP', 398: 'KZ', 400: 'JO', 404: 'KE',
		408: 'KP', 410: 'KR', 414: 'KW', 417: 'KG', 418: 'LA', 422: 'LB', 426: 'LS', 428: 'LV', 430: 'LR',
		434: 'LY', 438: 'LI', 440: 'LT', 442: 'LU', 450: 'MG', 454: 'MW', 458: 'MY', 462: 'MV', 466: 'ML',
		470: 'MT', 478: 'MR', 480: 'MU', 484: 'MX', 492: 'MC', 496: 'MN', 498: 'MD', 499: 'ME', 504: 'MA',
		508: 'MZ', 512: 'OM', 516: 'NA', 520: 'NR', 524: 'NP', 528: 'NL', 540: 'NC', 548: 'VU', 554: 'NZ',
		558: 'NI', 562: 'NE', 566: 'NG', 570: 'NU', 574: 'NF', 578: 'NO', 580: 'MP', 581: 'UM', 582: 'MH',
		583: 'FM', 584: 'PW', 585: 'PW', 586: 'PK', 591: 'PA', 598: 'PG', 600: 'PY', 604: 'PE', 608: 'PH',
		616: 'PL', 620: 'PT', 624: 'GW', 626: 'TL', 630: 'PR', 634: 'QA', 642: 'RO', 643: 'RU', 646: 'RW',
		652: 'BL', 659: 'KN', 660: 'AI', 662: 'LC', 663: 'MF', 670: 'VC', 674: 'SM', 678: 'ST', 682: 'SA',
		686: 'SN', 688: 'RS', 690: 'SC', 694: 'SL', 702: 'SG', 703: 'SK', 704: 'VN', 705: 'SI', 706: 'SO',
		710: 'ZA', 716: 'ZW', 724: 'ES', 728: 'SS', 729: 'SD', 740: 'SR', 748: 'SZ', 752: 'SE', 756: 'CH',
		760: 'SY', 762: 'TJ', 764: 'TH', 768: 'TG', 776: 'TO', 780: 'TT', 784: 'AE', 788: 'TN', 792: 'TR',
		795: 'TM', 796: 'TC', 798: 'TV', 800: 'UG', 804: 'UA', 807: 'MK', 818: 'EG', 826: 'GB', 834: 'TZ',
		840: 'US', 850: 'VI', 854: 'BF', 858: 'UY', 860: 'UZ', 862: 'VE', 876: 'WF', 882: 'WS', 887: 'YE',
		894: 'ZM', 10: 'AQ', 732: 'EH', 383: 'XK', 638: 'RE', 260: 'TF',
	};

	function esc(s) {
		return String(s || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	}

	function i18n(key, fallback) {
		return (cfg.i18n && cfg.i18n[key]) || fallback || '';
	}

	function colorsFromData(data) {
		if (data && data.colors && data.colors.length) {
			return data.colors;
		}
		return cfg.defaultColors || [
			'#2563eb', '#16a34a', '#dc2626', '#9333ea', '#ea580c', '#0891b2',
		];
	}

	function vizHost(scope) {
		return $('#wsergo-cluster-viz-' + scope);
	}

	function featuresHost(scope) {
		return $('#wsergo-cluster-features-' + scope);
	}

	function kInput(scope) {
		return scope === 'city' ? '#wsergo_city_macro_k_clusters' : '#wsergo_macro_k_clusters';
	}

	function selectedFeatures(scope) {
		var out = [];
		featuresHost(scope)
			.find('input[type="checkbox"]:checked')
			.each(function () {
				out.push(String($(this).val()));
			});
		return out;
	}

	function setStatus($host, text) {
		$host.find('.wsergo-cluster-viz-status').text(text || '');
	}

	function renderLegend($host, data) {
		var colors = colorsFromData(data);
		var html = '';
		var k = data.k || 0;
		for (var c = 1; c <= k; c++) {
			var cnt = (data.cluster_sizes && data.cluster_sizes[c - 1]) || 0;
			html +=
				'<span><i style="background:' +
				esc(colors[c - 1] || '#999') +
				'"></i> ' +
				esc(i18n('cluster', 'Кластер')) +
				' ' +
				c +
				' (' +
				cnt +
				')</span>';
		}
		$host.find('.wsergo-cluster-viz-legend').html(html);
	}

	function renderTable($host, data) {
		var $wrap = $host.find('.wsergo-cluster-viz-table-wrap');
		if (!data.countries || !data.countries.length) {
			$wrap.empty();
			return;
		}
		var html =
			'<table class="widefat striped"><thead><tr><th>' +
			esc(i18n('cluster', 'Кластер')) +
			'</th><th>' +
			esc(i18n('country', 'Страна')) +
			'</th><th>ISO2</th></tr></thead><tbody>';
		data.countries.forEach(function (row) {
			html +=
				'<tr><td>' +
				esc(row.cluster) +
				'</td><td>' +
				esc(row.name) +
				'</td><td><code>' +
				esc(row.iso2 || '—') +
				'</code></td></tr>';
		});
		html += '</tbody></table>';
		$wrap.html(html);
	}

	function destroyCharts($host) {
		var st = $host.data('wsergoCharts');
		if (st) {
			if (st.doughnut) {
				st.doughnut.destroy();
			}
			if (st.scatter) {
				st.scatter.destroy();
			}
		}
		$host.removeData('wsergoCharts');
	}

	function renderCharts($host, data) {
		if (typeof Chart === 'undefined') {
			return;
		}
		destroyCharts($host);
		var colors = colorsFromData(data);
		var labels = (data.feature_labels || {});
		var axisX = labels[data.axis_x] || data.axis_x || 'X';
		var axisY = labels[data.axis_y] || data.axis_y || 'Y';

		var doughnutEl = $host.find('.wsergo-cluster-viz-chart-doughnut')[0];
		var scatterEl = $host.find('.wsergo-cluster-viz-chart-scatter')[0];
		if (!doughnutEl || !scatterEl) {
			return;
		}

		var doughnutLabels = [];
		var doughnutData = [];
		var doughnutColors = [];
		for (var c = 1; c <= data.k; c++) {
			doughnutLabels.push(i18n('cluster', 'Кластер') + ' ' + c);
			doughnutData.push((data.cluster_sizes && data.cluster_sizes[c - 1]) || 0);
			doughnutColors.push(colors[c - 1] || '#999');
		}

		var doughnut = new Chart(doughnutEl.getContext('2d'), {
			type: 'doughnut',
			data: {
				labels: doughnutLabels,
				datasets: [
					{
						data: doughnutData,
						backgroundColor: doughnutColors,
					},
				],
			},
			options: {
				plugins: {
					title: {
						display: true,
						text: i18n('distribution', 'Распределение по кластерам'),
					},
					legend: { position: 'bottom' },
				},
				maintainAspectRatio: false,
			},
		});

		var datasets = [];
		for (c = 1; c <= data.k; c++) {
			var pts = (data.countries || []).filter(function (row) {
				return parseInt(row.cluster, 10) === c;
			});
			datasets.push({
				label: i18n('cluster', 'Кластер') + ' ' + c,
				data: pts.map(function (p) {
					return { x: p.x, y: p.y, country: p.name };
				}),
				backgroundColor: colors[c - 1] || '#999',
				borderColor: colors[c - 1] || '#999',
				pointRadius: 5,
			});
		}

		var scatter = new Chart(scatterEl.getContext('2d'), {
			type: 'scatter',
			data: { datasets: datasets },
			options: {
				plugins: {
					title: {
						display: true,
						text: i18n('scatterTitle', 'Страны в пространстве признаков (z-score)'),
					},
					tooltip: {
						callbacks: {
							label: function (ctx) {
								var p = ctx.raw || {};
								return (p.country || '') + ': (' + ctx.parsed.x + ', ' + ctx.parsed.y + ')';
							},
						},
					},
					legend: { position: 'bottom' },
				},
				scales: {
					x: { title: { display: true, text: axisX } },
					y: { title: { display: true, text: axisY } },
				},
				maintainAspectRatio: false,
			},
		});

		$host.data('wsergoCharts', { doughnut: doughnut, scatter: scatter });
	}

	function getNumId(feature) {
		var r = feature.id !== undefined ? feature.id : feature.properties ? feature.properties.id : 0;
		return parseInt(r, 10) || 0;
	}

	function colorForCluster(clusterId, colors) {
		var idx = Math.max(0, parseInt(clusterId, 10) - 1);
		return colors[idx] || '#b0bec5';
	}

	function applyMapColors(scope, data) {
		var st = mapState[scope];
		if (!st || !st.geoLayer) {
			return;
		}
		var colors = colorsFromData(data);
		var choro = data.choropleth || {};
		st.geoLayer.eachLayer(function (layer) {
			if (!layer.feature) {
				return;
			}
			var a2 = ID_A2[getNumId(layer.feature)] || '';
			var cid = choro[a2];
			var fill = cid ? colorForCluster(cid, colors) : '#e2e8f0';
			layer.setStyle({
				fillColor: fill,
				fillOpacity: cid ? 0.85 : 0.35,
				weight: cid ? 1 : 0.5,
				color: '#fff',
			});
			var country = (data.countries || []).find(function (c) {
				return c.iso2 === a2;
			});
			var tip =
				(country ? country.name : a2) +
				(cid ? ' — ' + i18n('cluster', 'Кластер') + ' ' + cid : '');
			layer.bindTooltip(tip, { sticky: true });
		});
	}

	function initMap(scope, $host, data) {
		if (typeof L === 'undefined' || typeof topojson === 'undefined' || !cfg.topoUrl) {
			$host
				.find('.wsergo-cluster-viz-map')
				.html(
					'<p class="wsergo-cluster-viz-empty">' + esc(i18n('noMap', 'Карта недоступна.')) + '</p>'
				);
			return;
		}
		var el = $host.find('.wsergo-cluster-viz-map')[0];
		if (!el) {
			return;
		}

		if (!mapState[scope]) {
			mapState[scope] = { map: null, geoLayer: null, loading: false };
		}
		var st = mapState[scope];

		if (!st.map) {
			st.map = L.map(el, {
				zoomControl: true,
				attributionControl: false,
				maxBoundsViscosity: 1,
			}).setView([20, 10], 2);
			L.tileLayer('https://{s}.basemaps.cartocdn.com/light_nolabels/{z}/{x}/{y}{r}.png', {
				maxZoom: 6,
				attribution: '',
			}).addTo(st.map);
		}

		if (st.geoLayer) {
			applyMapColors(scope, data);
			setTimeout(function () {
				st.map.invalidateSize();
			}, 100);
			return;
		}

		if (st.loading) {
			return;
		}
		st.loading = true;

		fetch(cfg.topoUrl)
			.then(function (r) {
				return r.json();
			})
			.then(function (topo) {
				var geo = topojson.feature(topo, topo.objects.countries);
				st.geoLayer = L.geoJSON(geo, {
					style: function () {
						return {
							fillColor: '#e2e8f0',
							fillOpacity: 0.5,
							weight: 0.6,
							color: '#fff',
						};
					},
				}).addTo(st.map);
				st.loading = false;
				applyMapColors(scope, data);
				setTimeout(function () {
					st.map.invalidateSize();
				}, 150);
			})
			.catch(function () {
				st.loading = false;
				$(el).html(
					'<p class="wsergo-cluster-viz-empty">' + esc(i18n('noMap', 'Карта недоступна.')) + '</p>'
				);
			});
	}

	function summaryText(data) {
		var tpl = i18n('summary', 'k = %1$d, стран: %2$d, год: %3$d');
		return tpl
			.replace('%1$d', String(data.k || ''))
			.replace('%2$d', String(data.n_countries || ''))
			.replace('%3$d', String(data.year || '—'));
	}

	function fetchPreview(scope) {
		var $host = vizHost(scope);
		if (!$host.length || !cfg.ajaxUrl) {
			return;
		}
		var feats = selectedFeatures(scope);
		if (feats.length < 2) {
			setStatus($host, i18n('needFeatures', 'Отметьте не меньше двух признаков.'));
			destroyCharts($host);
			$host.find('.wsergo-cluster-viz-table-wrap').empty();
			$host.find('.wsergo-cluster-viz-legend').empty();
			return;
		}

		setStatus($host, i18n('loading', 'Расчёт кластеров…'));

		$.post(cfg.ajaxUrl, {
			action: cfg.previewAction || 'wsergo_cluster_preview',
			nonce: cfg.nonce,
			scope: scope,
			k: $(kInput(scope)).val() || 4,
			features: feats,
		})
			.done(function (res) {
				if (!res || !res.success || !res.data) {
					setStatus(
						$host,
						(res && res.data && res.data.message) || i18n('error', 'Ошибка')
					);
					return;
				}
				var data = res.data;
				renderLegend($host, data);
				renderTable($host, data);
				var mode = $host.find('.wsergo-cluster-viz-mode.is-active').data('mode') || 'map';
				if (mode === 'chart') {
					renderCharts($host, data);
				} else {
					initMap(scope, $host, data);
				}
				setStatus($host, summaryText(data));
				$host.data('wsergoPreview', data);
			})
			.fail(function () {
				setStatus($host, i18n('error', 'Ошибка'));
			});
	}

	function scheduleRefresh(scope) {
		clearTimeout(timers[scope]);
		timers[scope] = setTimeout(function () {
			fetchPreview(scope);
		}, 450);
	}

	function setMode($host, mode) {
		$host.find('.wsergo-cluster-viz-mode').removeClass('is-active');
		$host.find('.wsergo-cluster-viz-mode[data-mode="' + mode + '"]').addClass('is-active');
		if (mode === 'map') {
			$host.find('.wsergo-cluster-viz-map-pane').show();
			$host.find('.wsergo-cluster-viz-chart-pane').hide();
			var data = $host.data('wsergoPreview');
			var scope = $host.data('scope');
			if (data) {
				initMap(scope, $host, data);
			}
		} else {
			$host.find('.wsergo-cluster-viz-map-pane').hide();
			$host.find('.wsergo-cluster-viz-chart-pane').show();
			var dataChart = $host.data('wsergoPreview');
			if (dataChart) {
				renderCharts($host, dataChart);
			}
			setTimeout(function () {
				var st = $host.data('wsergoCharts');
				if (st && st.scatter) {
					st.scatter.resize();
				}
				if (st && st.doughnut) {
					st.doughnut.resize();
				}
			}, 80);
		}
	}

	$(document).on('change', '.wsergo-cluster-features-host input[type="checkbox"]', function () {
		var scope = $(this).closest('.wsergo-cluster-features-host').data('scope') || 'country';
		scheduleRefresh(scope);
	});

	$(document).on('input', '#wsergo_macro_k_clusters, #wsergo_city_macro_k_clusters', function () {
		var id = this.id || '';
		var scope = id.indexOf('city') >= 0 ? 'city' : 'country';
		scheduleRefresh(scope);
	});

	$(document).on('click', '.wsergo-cluster-viz-mode', function (e) {
		e.preventDefault();
		var mode = $(this).data('mode') || 'map';
		var $host = $(this).closest('.wsergo-cluster-viz-host');
		setMode($host, mode);
	});

	$(document).on('wsergo-cluster-preview-refresh', function (_e, scope) {
		scheduleRefresh(scope || 'country');
	});

	$(document).on('wsergo-cluster-features-ready', function (_e) {
		$('.wsergo-cluster-viz-host:visible').each(function () {
			var scope = $(this).data('scope') || 'country';
			scheduleRefresh(scope);
		});
	});

	$(document).ready(function () {
		$('.wsergo-cluster-viz-host').each(function () {
			var $h = $(this);
			var scope = $h.data('scope') || 'country';
			if ($h.closest('.wsergo-scope-panel').length && !$h.closest('.wsergo-scope-panel--active').length) {
				var $panel = $h.closest('#wsergo-panel-country');
				if ($panel.length && $panel.is(':hidden')) {
					return;
				}
			}
			scheduleRefresh(scope);
		});
	});

	$(document).on('wsergo-scope-activated', function (_e, scope) {
		if (scope === 'country') {
			setTimeout(function () {
				scheduleRefresh('country');
				var st = mapState.country;
				if (st && st.map) {
					st.map.invalidateSize();
				}
			}, 200);
		}
	});

	$(document).on('click', '.wsergo-country-inner-nav a[data-tab="tab-data"]', function () {
		setTimeout(function () {
			scheduleRefresh('country');
			var st = mapState.country;
			if (st && st.map) {
				st.map.invalidateSize();
			}
		}, 300);
	});
}(jQuery));
