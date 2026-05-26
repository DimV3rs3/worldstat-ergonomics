/**
 * Boot «Анализ города» — инициализация после AJAX-вкладок платформы (wsp:tab:loaded).
 */
( function ( window, $ ) {
	'use strict';

	var L10N = window.wsergoCityExplorerL10n || {};
	var AXL_DEFAULT = L10N.dimLabels || {};

	function esc( x ) {
		var d = document.createElement( 'div' );
		d.textContent = x == null ? '' : String( x );
		return d.innerHTML;
	}

	function th( t ) {
		return '<th>' + esc( t ) + '</th>';
	}

	function td( x, c ) {
		return '<td' + ( c ? ' class="' + c + '"' : '' ) + '>' + x + '</td>';
	}

	/**
	 * @param {string} uid Root element id.
	 */
	function initCityExplorer( uid ) {
		var r = document.getElementById( uid );
		if ( ! r || r.getAttribute( 'data-wsergo-booted' ) === '1' ) {
			return;
		}
		r.setAttribute( 'data-wsergo-booted', '1' );

		var s = document.getElementById( uid + '-sel' );
		var b = r.querySelector( '.wsergo-city-explorer__body' );
		var j = document.getElementById( uid + '-json' );
		if ( ! j || ! b ) {
			return;
		}

		var pack = {};
		var packCountry = null;
		var packGlobal = null;
		var scope = 'country';

		try {
			pack = JSON.parse( j.textContent || '{}' );
			packCountry = JSON.parse( JSON.stringify( pack ) );
		} catch ( e ) {
			return;
		}

		var L = pack.cities || {};
		var UI = pack.__ui || {};
		var dimKeys = UI.dim_keys || [];
		var dimLab = UI.dim_labels || {};
		var reg = pack.regression || {};
		var AXL = AXL_DEFAULT;

		var regSum = document.getElementById( uid + '-reg-summary' );
		var chartWrap = document.getElementById( uid + '-chart-wrap' );
		var chartPoints = document.getElementById( uid + '-chart-points-desc' );
		var chartHiDesc = document.getElementById( uid + '-chart-highlight-desc' );
		var compareWrap = document.getElementById( uid + '-compare-wrap' );
		var compareBody = document.getElementById( uid + '-compare-body' );
		var compareIds = [];
		var compareReq = 0;
		var cmpTimer = null;
		var cityIndex = {};

		var chartCv = document.getElementById( uid + '-chart-cv' );
		var chartSel = document.getElementById( uid + '-chart-ind' );
		var chartCap = document.getElementById( uid + '-chart-cap' );

		function cmpSlotId( slot ) {
			return uid + '-cmp-' + slot;
		}

		function cityCountryKey( c ) {
			if ( ! c ) {
				return '';
			}
			var iso = c.country_iso2 ? String( c.country_iso2 ).toUpperCase() : '';
			if ( iso ) {
				return iso;
			}
			return decodeHtml( c.country_name || '' )
				.toLowerCase()
				.replace( /\s+/g, ' ' )
				.trim();
		}

		function getBlockedCountriesForSlot( slot ) {
			var blocked = [];
			[ 'a', 'b', 'c' ].forEach( function ( s ) {
				if ( s === slot ) {
					return;
				}
				var root = document.getElementById( cmpSlotId( s ) );
				var hid = root && root.querySelector( '.wsergo-city-search__id' );
				var id = hid && hid.value;
				if ( ! id || ! cityIndex[ id ] ) {
					return;
				}
				var key = cityCountryKey( cityIndex[ id ] );
				if ( key && blocked.indexOf( key ) < 0 ) {
					blocked.push( key );
				}
			} );
			return blocked;
		}

		function compareIdsHaveSameCountry( ids ) {
			var seen = {};
			var i, key;
			for ( i = 0; i < ids.length; i++ ) {
				key = cityCountryKey( cityIndex[ ids[ i ] ] );
				if ( ! key ) {
					continue;
				}
				if ( seen[ key ] ) {
					return true;
				}
				seen[ key ] = true;
			}
			return false;
		}

		function refreshCompareSearchEntries() {
			[ 'a', 'b', 'c' ].forEach( function ( slot ) {
				var root = document.getElementById( cmpSlotId( slot ) );
				if ( root && typeof root.wsergoUpdateCityIndex === 'function' ) {
					root.wsergoUpdateCityIndex( cityIndex );
				}
			} );
		}

		function clearConflictingCompareSlots( changedSlot ) {
			var order = [ 'a', 'b', 'c' ];
			var start = order.indexOf( changedSlot );
			if ( start < 0 ) {
				return;
			}
			var si;
			for ( si = start + 1; si < order.length; si++ ) {
				var slot = order[ si ];
				var root = document.getElementById( cmpSlotId( slot ) );
				if ( ! root ) {
					continue;
				}
				var hid = root.querySelector( '.wsergo-city-search__id' );
				var id = hid && hid.value;
				if ( ! id || ! cityIndex[ id ] ) {
					continue;
				}
				if ( getBlockedCountriesForSlot( slot ).indexOf( cityCountryKey( cityIndex[ id ] ) ) >= 0 ) {
					if ( typeof root.wsergoClearCity === 'function' ) {
						root.wsergoClearCity();
					}
				}
			}
		}

		function decodeHtml( s ) {
			var str = s == null ? '' : String( s );
			if ( str.indexOf( '&' ) < 0 ) {
				return str;
			}
			var t = document.createElement( 'textarea' );
			t.innerHTML = str;
			return t.value;
		}

		function cityLabel( c ) {
			var n = decodeHtml( c.name || '' );
			if ( scope === 'global' && c.country_name ) {
				n += n ? ' (' + decodeHtml( c.country_name ) + ')' : decodeHtml( c.country_name );
			}
			return n;
		}

		function chartModels() {
			var list = reg.univariate_full || [];
			var out = [];
			var ci;
			for ( ci = 0; ci < list.length; ci++ ) {
				var uv = list[ ci ];
				if ( uv && uv.scatter && uv.scatter.length >= 2 ) {
					out.push( uv );
				}
			}
			return out;
		}

		function render() {
			if ( ! s ) {
				return;
			}
			var id = s.value;
			var c = L[ id ];
			if ( ! c ) {
				b.innerHTML = '';
				return;
			}
			var h = '<div class="wsergo-city-detail"><h4 class="wsergo-city-detail__head">' + esc( c.name );
			if ( scope === 'global' && c.country_name ) {
				h += ' <span class="wsergo-city-detail__head-meta">(' + esc( c.country_name ) + ')</span>';
			}
			h += '</h4>';
			var te = c.table_e != null && c.table_e !== '' ? c.table_e : c.leaf_e != null ? c.leaf_e : '—';
			var eLab = UI.e_label || 'E';
			h +=
				'<p class="wsergo-city-detail__e"><span class="wsergo-city-detail__e-badge">' +
				esc( eLab ) +
				'</span><span class="wsergo-city-detail__e-val">' +
				esc( te ) +
				'</span></p>';
			h += '<h5 class="wsergo-city-detail__sub-title">' + esc( UI.rec_title || 'Рекомендации' ) + '</h5><ul class="wsergo-city-detail__rec-list">';
			var rec = c.recommendations || [];
			if ( ! rec.length ) {
				h += '<li>' + esc( UI.no_rec || '' ) + '</li>';
			} else {
				var ri;
				for ( ri = 0; ri < rec.length; ri++ ) {
					h += '<li>' + esc( rec[ ri ] ) + '</li>';
				}
			}
			h += '</ul>';
			h += '<h5 class="wsergo-city-detail__sub-title">' + esc( UI.table_summary_title || '' ) + '</h5>';
			h +=
				'<div class="wsp-stats-grid wsergo-city-stats-grid" style="--wsp-grid-cols:repeat(auto-fill,minmax(128px,1fr))">';
			h +=
				'<article class="wsp-stat-card"><div class="wsp-stat-value">' +
				esc( te ) +
				'</div><div class="wsp-stat-label">' +
				esc( eLab ) +
				'</div></article>';
			var ts = c.table_subindices || {};
			var di;
			for ( di = 0; di < dimKeys.length; di++ ) {
				var dk = dimKeys[ di ];
				var vv = ts[ dk ];
				if ( vv == null || vv === '' ) {
					vv = '—';
				}
				var lb = dimLab[ dk ] || AXL[ dk ] || dk;
				h +=
					'<article class="wsp-stat-card"><div class="wsp-stat-value">' +
					esc( vv ) +
					'</div><div class="wsp-stat-label">' +
					esc( lb ) +
					'</div></article>';
			}
			h += '</div>';
			var ind = c.indicators || [];
			h += '<h5 class="wsergo-city-detail__sub-title">' + esc( UI.ind_detail_title || '' ) + '</h5>';
			if ( ! ind.length ) {
				h += '<p class="wsergo-city-detail__empty">' + esc( UI.no_indicators || '' ) + '</p>';
			} else {
				h += '<div class="wsp-table-wrap wsergo-city-detail__table-wrap"><table class="wsp-table"><thead><tr>';
				h +=
					th( UI.col_indicator || 'Показатель' ) +
					th( UI.col_dimension || 'Измерение' ) +
					th( UI.col_raw || 'Сырое' ) +
					th( UI.col_score || 'Балл 0–100' ) +
					th( UI.col_source || 'Источник' );
				h += '</tr></thead><tbody>';
				var jj;
				for ( jj = 0; jj < ind.length; jj++ ) {
					var it = ind[ jj ];
					var rv = it.raw_value;
					var sv = it.score_value;
					var dm = it.dimension || '';
					var dlab = AXL[ dm ] || dm;
					h += '<tr>';
					h += td( esc( it.label || it.id ) );
					h += td( esc( dlab ) );
					h += td( rv != null ? esc( rv ) : '—', 'num' );
					h += td( sv != null ? esc( sv ) : '—', 'num' );
					h += td( esc( it.source_field || '' ) );
					h += '</tr>';
				}
				h += '</tbody></table></div>';
			}
			h += '</div>';
			b.innerHTML = h;
		}

		function rebuildCitySelect() {
			if ( ! s ) {
				return;
			}
			var prev = s.value;
			var ids = Object.keys( L );
			ids.sort( function ( a, bb ) {
				return cityLabel( L[ a ] || {} ).localeCompare( cityLabel( L[ bb ] || {} ), 'ru' );
			} );
			var h = '';
			var oi;
			for ( oi = 0; oi < ids.length; oi++ ) {
				var cid = ids[ oi ];
				h +=
					'<option value="' +
					esc( cid ) +
					'"' +
					( cid === prev ? ' selected' : '' ) +
					'>' +
					esc( cityLabel( L[ cid ] || {} ) ) +
					'</option>';
			}
			s.innerHTML = h;
			if ( prev && L[ prev ] ) {
				s.value = prev;
			} else if ( ids.length ) {
				s.value = ids[ 0 ];
			}
		}

		function rebuildChartSelect() {
			if ( ! chartSel ) {
				return;
			}
			var models = chartModels();
			var prev = chartSel.value;
			chartSel.innerHTML = '';
			var mi;
			for ( mi = 0; mi < models.length; mi++ ) {
				var cm = models[ mi ];
				var iid = String( cm.id || '' );
				var lab = cm.label || iid;
				var r2 = cm.r2 != null ? cm.r2 : '—';
				chartSel.innerHTML += '<option value="' + esc( iid ) + '">' + esc( lab ) + ' — R²=' + esc( r2 ) + '</option>';
			}
			if ( prev && models.some( function ( m ) {
				return String( m.id ) === prev;
			} ) ) {
				chartSel.value = prev;
			}
			if ( chartWrap && scope !== 'global' ) {
				chartWrap.classList.toggle( 'is-empty', ! models.length );
			}
		}

		function updateRegSummary() {
			if ( ! regSum ) {
				return;
			}
			var nl = reg.n_leaf || Object.keys( cityIndex ).length || 0;
			if ( scope === 'global' ) {
				var tpl = UI.reg_summary_global_index || '';
				regSum.textContent = tpl.replace( '%1$d', nl );
				return;
			}
			var usable = !! reg.usable;
			var med = reg.median_leaf;
			var meds = med != null && isFinite( med ) ? String( med ) : '—';
			if ( usable ) {
				var tpl2 = UI.reg_summary_country || '';
				regSum.textContent = tpl2.replace( '%1$d', nl ).replace( '%2$s', meds );
			} else {
				var fail = UI.reg_summary_fail_country || '';
				var note = reg.notice || UI.reg_summary_fail_default || '';
				regSum.innerHTML = '<strong>' + esc( fail ) + ':</strong> ' + esc( note );
			}
		}

		function resetComparePickers() {
			[ 'a', 'b', 'c' ].forEach( function ( slot ) {
				var root = document.getElementById( cmpSlotId( slot ) );
				if ( ! root ) {
					return;
				}
				if ( typeof window.wsergoDestroyCitySearch === 'function' ) {
					window.wsergoDestroyCitySearch( root );
				}
				var hid = root.querySelector( '.wsergo-city-search__id' );
				var lbl = root.querySelector( '.wsergo-city-select__label' );
				if ( hid ) {
					hid.value = '';
				}
				if ( lbl ) {
					lbl.textContent = slot === 'c' ? ( UI.cmp_none || '—' ) : ( UI.select_placeholder || '' );
				}
			} );
		}

		function getCompareIds() {
			var out = [];
			[ 'a', 'b', 'c' ].forEach( function ( slot ) {
				var root = document.getElementById( cmpSlotId( slot ) );
				if ( ! root ) {
					return;
				}
				var hid = root.querySelector( '.wsergo-city-search__id' );
				var id = hid && hid.value;
				if ( id && cityIndex[ id ] && out.indexOf( id ) < 0 ) {
					out.push( id );
				}
			} );
			return out;
		}

		function initCompareSearch() {
			if ( typeof window.wsergoInitCitySearch !== 'function' ) {
				return;
			}
			var slots = [
				{ s: 'a', empty: false },
				{ s: 'b', empty: false },
				{ s: 'c', empty: true },
			];
			slots.forEach( function ( cfg ) {
				var root = document.getElementById( cmpSlotId( cfg.s ) );
				if ( ! root ) {
					return;
				}
				window.wsergoInitCitySearch( root, cityIndex, {
					allowEmpty: cfg.empty,
					placeholder: UI.search_placeholder || '',
					selectLabel: cfg.empty ? ( UI.cmp_none || '' ) : ( UI.select_placeholder || '' ),
					emptyLabel: UI.cmp_none || '',
					noResults: UI.search_no_results || '',
					filterCity: function ( c ) {
						if ( scope !== 'global' ) {
							return false;
						}
						var blocked = getBlockedCountriesForSlot( cfg.s );
						var key = cityCountryKey( c );
						return ! key || blocked.indexOf( key ) < 0;
					},
					onChange: function () {
						refreshCompareSearchEntries();
						clearConflictingCompareSlots( cfg.s );
						renderCompareView();
					},
				} );
			} );
		}

		function fillDefaultCompareSlots() {
			if ( scope !== 'global' ) {
				return;
			}
			var byCountry = {};
			Object.keys( cityIndex ).forEach( function ( id ) {
				var key = cityCountryKey( cityIndex[ id ] );
				if ( ! key ) {
					return;
				}
				if ( ! byCountry[ key ] ) {
					byCountry[ key ] = [];
				}
				byCountry[ key ].push( id );
			} );
			var countries = Object.keys( byCountry );
			if ( countries.length < 2 ) {
				return;
			}
			countries.sort( function ( a, bb ) {
				return byCountry[ bb ].length - byCountry[ a ].length;
			} );
			var ra = document.getElementById( cmpSlotId( 'a' ) );
			var rb = document.getElementById( cmpSlotId( 'b' ) );
			if ( ra && ra.wsergoSetCity ) {
				ra.wsergoSetCity( byCountry[ countries[ 0 ] ][ 0 ], true );
			}
			if ( rb && rb.wsergoSetCity ) {
				rb.wsergoSetCity( byCountry[ countries[ 1 ] ][ 0 ], true );
			}
		}

		function renderCompareView() {
			if ( cmpTimer ) {
				clearTimeout( cmpTimer );
			}
			cmpTimer = setTimeout( function () {
				cmpTimer = null;
				var req = ++compareReq;
				compareIds = getCompareIds();
				if ( ! compareBody ) {
					return;
				}
				if ( scope !== 'global' ) {
					compareBody.innerHTML =
						'<p class="wsergo-cmp-placeholder">' + esc( UI.cmp_country_only || '' ) + '</p>';
					return;
				}
				if ( compareIds.length < 2 ) {
					compareBody.innerHTML =
						'<p class="wsergo-cmp-placeholder">' + esc( UI.cmp_need_two || '' ) + '</p>';
					return;
				}
				if ( compareIdsHaveSameCountry( compareIds ) ) {
					compareBody.innerHTML =
						'<p class="wsergo-cmp-error">' + esc( UI.cmp_same_country || '' ) + '</p>';
					return;
				}
				compareBody.innerHTML =
					'<p class="wsergo-cmp-placeholder">' + esc( UI.loading_compare || UI.loading || '' ) + '</p>';
				fetch( UI.ajax_url, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: new URLSearchParams( {
						action: 'wsergo_city_compare',
						ids: compareIds.join( ',' ),
						nonce: UI.nonce,
					} ),
				} )
					.then( function ( res ) {
						return res.json().catch( function () {
							return null;
						} );
					} )
					.then( function ( data ) {
						if ( req !== compareReq ) {
							return;
						}
						if ( ! data || ! data.success ) {
							var msg =
								data && data.data && data.data.message
									? data.data.message
									: UI.load_error || '';
							throw new Error( msg );
						}
						var cities = data.data.cities || {};
						if ( Object.keys( cities ).length < 2 ) {
							throw new Error( UI.cmp_need_two || UI.load_error || '' );
						}
						if ( typeof window.wsergoRenderCityCompare !== 'function' ) {
							throw new Error( UI.load_error || 'Compare JS missing' );
						}
						window.wsergoRenderCityCompare( compareBody, cities, compareIds, {
							dimKeys: dimKeys,
							dimLabels: dimLab,
							eLabel: UI.e_label || 'E',
							axisLabels: AXL,
							help: data.data.help || {},
							strings: {
								need_two: UI.cmp_need_two,
								metric: UI.cmp_metric,
								stronger: UI.cmp_stronger,
								weaker: UI.cmp_weaker,
								no_lead: UI.cmp_no_lead,
								legend_best: UI.cmp_legend_best,
								legend_worst: UI.cmp_legend_worst,
								section_axes: UI.cmp_section_axes,
								section_indicators: UI.cmp_section_indicators,
								summary_title: UI.cmp_summary_title,
								help_method: UI.cmp_help_method,
								help_values: UI.cmp_help_values,
								help_source_e: UI.cmp_help_source_e,
								help_source_dim: UI.cmp_help_source_dim,
								help_source_ind: UI.cmp_help_source_ind,
								help_dim_about: UI.cmp_help_dim_about,
								help_ind_norm: UI.cmp_help_ind_norm,
								help_higher: UI.cmp_help_higher,
								help_lower: UI.cmp_help_lower,
								help_raw: UI.cmp_help_raw,
								help_dimension: UI.cmp_help_dimension,
								help_toggle: UI.cmp_help_toggle,
							},
						} );
					} )
					.catch( function ( err ) {
						if ( req !== compareReq ) {
							return;
						}
						compareBody.innerHTML =
							'<p class="wsergo-cmp-error">' +
							esc( err && err.message ? err.message : UI.load_error || '' ) +
							'</p>';
					} );
			}, 280 );
		}

		function setExplorerMode() {
			r.classList.toggle( 'wsergo-city-explorer--global', scope === 'global' );
			if ( compareWrap ) {
				compareWrap.hidden = scope !== 'global';
			}
			if ( chartWrap ) {
				if ( scope === 'global' ) {
					chartWrap.classList.add( 'is-empty' );
				} else {
					chartWrap.classList.toggle( 'is-empty', ! chartModels().length );
				}
			}
			if ( chartHiDesc ) {
				chartHiDesc.textContent =
					scope === 'global'
						? UI.chart_highlight_multi_desc || ''
						: UI.chart_highlight_single_desc || '';
			}
		}

		function drawRegChart() {
			if ( ! chartCv || ! chartWrap || chartWrap.classList.contains( 'is-empty' ) ) {
				return;
			}
			var run = function () {
				if ( typeof window.wsergoDrawRegressionScatter !== 'function' ) {
					return;
				}
				var iid = chartSel ? String( chartSel.value ) : '';
				var list = reg.univariate_full || [];
				var m = null;
				var zi;
				for ( zi = 0; zi < list.length; zi++ ) {
					if ( String( ( list[ zi ] || {} ).id ) === iid ) {
						m = list[ zi ];
						break;
					}
				}
				if ( ! m || ! m.scatter ) {
					return;
				}
				var hid =
					scope === 'global' && compareIds.length >= 2
						? compareIds.map( function ( x ) {
							return parseInt( x, 10 ) || 0;
						} )
						: parseInt( s.value, 10 ) || 0;
				var hp =
					scope === 'global' && compareIds.length >= 2
						? ( UI.chart_highlight_multi || '' ) + ' '
						: ( UI.reg_chart_highlight || '' ) + ' ';
				window.wsergoDrawRegressionScatter( chartCv, chartCap, m, hid, {
					xAxis: UI.reg_chart_x_axis || 'x',
					yAxis: UI.reg_chart_y_axis || 'E',
					highlightPrefix: hp,
				} );
			};
			window.requestAnimationFrame( function () {
				window.requestAnimationFrame( run );
			} );
		}

		function renderAll() {
			render();
			drawRegChart();
		}

		function applyPack( np, sc ) {
			var ui = pack.__ui || UI;
			pack = np || {};
			pack.__ui = ui;
			UI = pack.__ui || UI;
			L = pack.cities || {};
			reg = pack.regression || {};
			scope = sc || pack.scope || 'country';
			cityIndex = L;

			resetComparePickers();
			setExplorerMode();
			if ( chartPoints ) {
				chartPoints.textContent =
					scope === 'global' ? UI.chart_points_global || '' : UI.chart_points_country || '';
			}
			updateRegSummary();
			initCompareSearch();
			if ( scope === 'global' ) {
				fillDefaultCompareSlots();
				renderCompareView();
			} else {
				rebuildCitySelect();
				rebuildChartSelect();
				if ( compareBody ) {
					compareBody.innerHTML =
						'<p class="wsergo-cmp-placeholder">' + esc( UI.cmp_country_only || '' ) + '</p>';
				}
				renderAll();
			}
		}

		function loadScope( sc ) {
			if ( sc === 'country' ) {
				applyPack( packCountry, 'country' );
				return;
			}
			if ( packGlobal ) {
				applyPack( packGlobal, 'global' );
				return;
			}
			if ( ! UI.ajax_url || ! UI.nonce ) {
				return;
			}
			if ( regSum ) {
				regSum.textContent = UI.loading || '';
			}
			if ( compareBody ) {
				compareBody.innerHTML =
					'<p class="wsergo-cmp-placeholder">' + esc( UI.loading || '' ) + '</p>';
			}
			fetch( UI.ajax_url, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: new URLSearchParams( {
					action: 'wsergo_city_explorer_payload',
					scope: 'global',
					nonce: UI.nonce,
				} ),
			} )
				.then( function ( res ) {
					return res.json();
				} )
				.then( function ( data ) {
					if ( ! data || ! data.success ) {
						throw new Error( 'load' );
					}
					packGlobal = data.data;
					applyPack( packGlobal, 'global' );
				} )
				.catch( function () {
					if ( regSum ) {
						regSum.textContent = UI.load_error || '';
					}
					if ( compareBody ) {
						compareBody.innerHTML =
							'<p class="wsergo-cmp-error">' + esc( UI.load_error || '' ) + '</p>';
					}
				} );
		}

		if ( s ) {
			s.addEventListener( 'change', renderAll );
		}
		if ( chartSel ) {
			chartSel.addEventListener( 'change', drawRegChart );
		}
		var scopeRadios = r.querySelectorAll( 'input[name="' + uid + '-scope"]' );
		var si;
		for ( si = 0; si < scopeRadios.length; si++ ) {
			scopeRadios[ si ].addEventListener( 'change', function ( ev ) {
				if ( ev.target.checked ) {
					loadScope( ev.target.value );
				}
			} );
		}

		var resizeT = null;
		window.addEventListener( 'resize', function () {
			if ( resizeT ) {
				clearTimeout( resizeT );
			}
			resizeT = setTimeout( drawRegChart, 150 );
		} );

		var subNav = r.closest( '.wsergo-country-subtabs' );
		if ( subNav ) {
			subNav.querySelectorAll( '.wsp-tab-btn' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					if ( btn.getAttribute( 'data-tab' ) === 'cities' ) {
						setTimeout( drawRegChart, 120 );
					}
				} );
			} );
		}

		cityIndex = L;
		applyPack( packCountry, 'country' );
	}

	function scanExplorers( root ) {
		var scope = root || document;
		scope.querySelectorAll( '.wsergo-city-explorer[id]' ).forEach( function ( el ) {
			if ( el.id ) {
				initCityExplorer( el.id );
			}
		} );
	}

	window.wsergoInitCityExplorer = initCityExplorer;
	window.wsergoScanCityExplorers = scanExplorers;

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			scanExplorers();
		} );
	} else {
		scanExplorers();
	}

	$( document ).on( 'wsp:tab:loaded', function ( ev, tabId, iso2, $panel ) {
		if ( $panel && $panel.length ) {
			scanExplorers( $panel[ 0 ] );
		} else {
			scanExplorers();
		}
	} );

	/** После lazy-load country tab подхватываем новый HTML исследователя. */
	function watchLazyExplorerSections() {
		if ( ! ( 'MutationObserver' in window ) ) {
			return;
		}
		document.querySelectorAll( '[data-wsergo-explorer-lazy="1"]' ).forEach( function ( sec ) {
			if ( sec.getAttribute( 'data-wsergo-lazy-watched' ) === '1' ) {
				return;
			}
			sec.setAttribute( 'data-wsergo-lazy-watched', '1' );
			var obs = new MutationObserver( function () {
				if ( window.wsergoScanCityExplorers ) {
					window.wsergoScanCityExplorers( sec );
				}
			} );
			obs.observe( sec, { childList: true, subtree: true } );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', watchLazyExplorerSections );
	} else {
		watchLazyExplorerSections();
	}
	$( document ).on( 'wsp:tab:loaded', watchLazyExplorerSections );
} )( window, window.jQuery );
