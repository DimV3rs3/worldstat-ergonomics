/**
 * Side-by-side city comparison (E, dimensions, indicators) with best/worst highlighting.
 */
( function ( window ) {
	'use strict';

	function esc( x ) {
		var d = document.createElement( 'div' );
		d.textContent = x == null ? '' : String( x );
		return d.innerHTML;
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

	function displayName( s ) {
		return esc( decodeHtml( s ) );
	}

	function num( v ) {
		if ( v == null || v === '' ) {
			return null;
		}
		var n = parseFloat( String( v ).replace( ',', '.' ) );
		return isFinite( n ) ? n : null;
	}

	function fmtNum( v ) {
		if ( v == null ) {
			return null;
		}
		var r = Math.round( v * 100 ) / 100;
		if ( Math.abs( r - Math.round( r ) ) < 0.001 ) {
			return String( Math.round( r ) );
		}
		return r.toFixed( 2 );
	}

	function cityE( c ) {
		var v = c.table_e != null && c.table_e !== '' ? c.table_e : c.leaf_e;
		return num( v );
	}

	function cityColHeader( c ) {
		var h = '<span class="wsergo-cmp-table__city-name">' + displayName( c.name || '' ) + '</span>';
		if ( c.country_name ) {
			h +=
				'<br><span class="wsergo-cmp-table__city-country">' + displayName( c.country_name ) + '</span>';
		}
		return h;
	}

	function summaryCityHeader( c, idx ) {
		var num = ( idx || 0 ) + 1;
		var h =
			'<div class="wsergo-cmp-summary__head">' +
			'<span class="wsergo-cmp-summary__badge" aria-hidden="true">' +
			num +
			'</span>' +
			'<div class="wsergo-cmp-summary__titles">' +
			'<span class="wsergo-cmp-summary__city">' +
			displayName( c.name || '' ) +
			'</span>';
		if ( c.country_name ) {
			h +=
				'<span class="wsergo-cmp-summary__country">' + displayName( c.country_name ) + '</span>';
		}
		h += '</div></div>';
		return h;
	}

	function metricListHtml( rowKeys, rowMeta, cities, ids, help, strings ) {
		if ( ! rowKeys || ! rowKeys.length ) {
			return '';
		}
		var h = '<ul class="wsergo-cmp-summary__metrics">';
		var i;
		for ( i = 0; i < rowKeys.length; i++ ) {
			var rowKey = rowKeys[ i ];
			var row = rowMeta[ rowKey ];
			var label = row && row.label ? row.label : rowKey;
			var helpBody = buildMetricHelpBody( rowKey, rowMeta, cities, ids, help, strings );
			h += '<li class="wsergo-cmp-metric-item">';
			if ( helpBody ) {
				h +=
					'<details class="wsergo-cmp-metric-details">' +
					'<summary class="wsergo-cmp-metric-summary">' +
					'<span class="wsergo-cmp-metric-summary__label">' +
					esc( label ) +
					'</span>' +
					'<span class="wsergo-cmp-metric-summary__hint">' +
					esc( strings.help_toggle || 'Как проводилось сравнение' ) +
					'</span>' +
					'</summary>' +
					'<div class="wsergo-cmp-metric-help">' +
					helpBody +
					'</div></details>';
			} else {
				h += esc( label );
			}
			h += '</li>';
		}
		h += '</ul>';
		return h;
	}

	function indicatorRawForCity( c, iid ) {
		if ( ! c || ! c.indicators ) {
			return null;
		}
		var j;
		for ( j = 0; j < c.indicators.length; j++ ) {
			if ( String( c.indicators[ j ].id ) === String( iid ) ) {
				return c.indicators[ j ];
			}
		}
		return null;
	}

	function cityLineLabel( c ) {
		var n = displayName( c.name || '' );
		if ( c.country_name ) {
			n += ' (' + displayName( c.country_name ) + ')';
		}
		return n;
	}

	function buildMetricHelpBody( rowKey, rowMeta, cities, ids, help, strings ) {
		var row = rowMeta[ rowKey ];
		if ( ! row || typeof row.get !== 'function' ) {
			return '';
		}
		help = help || {};
		strings = strings || {};

		var h = '';
		h += '<p class="wsergo-cmp-metric-help__lead">' + esc( strings.help_method || '' ) + '</p>';

		h += '<p class="wsergo-cmp-metric-help__subhead">' + esc( strings.help_values || 'Значения' ) + '</p>';
		h += '<ul class="wsergo-cmp-metric-help__vals">';
		var i;
		for ( i = 0; i < ids.length; i++ ) {
			var c = cities[ ids[ i ] ];
			var v = row.get( c );
			h +=
				'<li><span class="wsergo-cmp-metric-help__city">' +
				cityLineLabel( c ) +
				'</span>: <strong>' +
				( v != null ? esc( fmtNum( v ) ) : '—' ) +
				'</strong></li>';
		}
		h += '</ul>';

		if ( rowKey === '__e__' ) {
			h += '<p class="wsergo-cmp-metric-help__note">' + esc( strings.help_source_e || '' ) + '</p>';
		} else if ( row.isAxis ) {
			h += '<p class="wsergo-cmp-metric-help__note">' + esc( strings.help_source_dim || '' ) + '</p>';
			if ( row.dimKey && help.dimensions && help.dimensions[ row.dimKey ] ) {
				h +=
					'<p class="wsergo-cmp-metric-help__note"><strong>' +
					esc( strings.help_dim_about || '' ) +
					'</strong> ' +
					esc( help.dimensions[ row.dimKey ] ) +
					'</p>';
			}
		} else if ( rowKey.indexOf( 'ind:' ) === 0 ) {
			var indId = rowKey.slice( 4 );
			h += '<p class="wsergo-cmp-metric-help__note">' + esc( strings.help_source_ind || '' ) + '</p>';
			var def = help.indicators && help.indicators[ indId ];
			if ( def ) {
				var dimLab = '';
				if ( def.dimension && help.dimensions && help.dimensions[ def.dimension ] ) {
					dimLab = help.dimensions[ def.dimension ];
				}
				if ( dimLab ) {
					h +=
						'<p class="wsergo-cmp-metric-help__note"><strong>' +
						esc( strings.help_dimension || 'Измерение:' ) +
						'</strong> ' +
						esc( dimLab ) +
						'</p>';
				}
				var dirTxt =
					def.direction === 'lower_better'
						? strings.help_lower || ''
						: strings.help_higher || '';
				var normTpl = strings.help_ind_norm || '';
				h +=
					'<p class="wsergo-cmp-metric-help__note">' +
					esc(
						normTpl
							.replace( '%1$s', String( def.vmin ) )
							.replace( '%2$s', String( def.vmax ) )
							.replace( '%3$s', dirTxt )
					) +
					( def.unit ? ' ' + esc( '(' + def.unit + ')' ) : '' ) +
					'</p>';
			}
			var hasRaw = false;
			var rawH = '<p class="wsergo-cmp-metric-help__subhead">' + esc( strings.help_raw || 'Сырые значения' ) + '</p><ul class="wsergo-cmp-metric-help__vals">';
			for ( i = 0; i < ids.length; i++ ) {
				c = cities[ ids[ i ] ];
				var indRow = indicatorRawForCity( c, indId );
				if ( indRow && indRow.raw_value != null && indRow.raw_value !== '' ) {
					hasRaw = true;
					rawH +=
						'<li><span class="wsergo-cmp-metric-help__city">' +
						cityLineLabel( c ) +
						'</span>: ' +
						esc( indRow.raw_value ) +
						( def && def.unit ? ' ' + esc( def.unit ) : '' ) +
						'</li>';
				}
			}
			rawH += '</ul>';
			if ( hasRaw ) {
				h += rawH;
			}
		}

		return h;
	}

	function indicatorUnion( cities, ids ) {
		var map = {};
		var i, j, c, ind;
		for ( i = 0; i < ids.length; i++ ) {
			c = cities[ ids[ i ] ];
			if ( ! c || ! c.indicators ) {
				continue;
			}
			for ( j = 0; j < c.indicators.length; j++ ) {
				ind = c.indicators[ j ];
				if ( ! ind || ! ind.id ) {
					continue;
				}
				if ( ! map[ ind.id ] ) {
					map[ ind.id ] = {
						id: ind.id,
						label: ind.label || ind.id,
						dimension: ind.dimension || '',
					};
				}
			}
		}
		return Object.keys( map )
			.map( function ( k ) {
				return map[ k ];
			} )
			.sort( function ( a, b ) {
				return String( a.label ).localeCompare( String( b.label ), 'ru' );
			} );
	}

	function scoreForIndicator( c, iid ) {
		if ( ! c || ! c.indicators ) {
			return null;
		}
		var j;
		for ( j = 0; j < c.indicators.length; j++ ) {
			if ( String( c.indicators[ j ].id ) === String( iid ) ) {
				return num( c.indicators[ j ].score_value );
			}
		}
		return null;
	}

	function dimValue( c, dk ) {
		var ts = c.table_subindices || {};
		if ( ts[ dk ] != null && ts[ dk ] !== '' ) {
			return num( ts[ dk ] );
		}
		var ax = c.axes || {};
		return num( ax[ dk ] );
	}

	function rowHasAnyValue( row, cities, ids ) {
		var i;
		for ( i = 0; i < ids.length; i++ ) {
			if ( row.get( cities[ ids[ i ] ] ) != null ) {
				return true;
			}
		}
		return false;
	}

	function analyzeRow( row, cities, ids, wins, losses ) {
		var vals = [];
		var i, v, numeric, best, worst, tieBest, tieWorst, vi, bestIds, worstIds;
		for ( i = 0; i < ids.length; i++ ) {
			v = row.get( cities[ ids[ i ] ] );
			vals.push( { id: ids[ i ], v: v } );
		}
		numeric = vals.filter( function ( x ) {
			return x.v != null;
		} );
		best = null;
		worst = null;
		tieBest = false;
		tieWorst = false;
		if ( numeric.length >= 2 ) {
			best = numeric[ 0 ].v;
			worst = numeric[ 0 ].v;
			for ( vi = 1; vi < numeric.length; vi++ ) {
				if ( numeric[ vi ].v > best ) {
					best = numeric[ vi ].v;
				}
				if ( numeric[ vi ].v < worst ) {
					worst = numeric[ vi ].v;
				}
			}
			if ( best === worst ) {
				best = worst = null;
			} else {
				bestIds = numeric.filter( function ( x ) {
					return x.v === best;
				} );
				worstIds = numeric.filter( function ( x ) {
					return x.v === worst;
				} );
				tieBest = bestIds.length > 1;
				tieWorst = worstIds.length > 1;
				if ( ! tieBest && bestIds.length === 1 ) {
					wins[ bestIds[ 0 ].id ].push( row.key );
				}
				if ( ! tieWorst && worstIds.length === 1 ) {
					losses[ worstIds[ 0 ].id ].push( row.key );
				}
			}
		}
		return { vals: vals, best: best, worst: worst, tieBest: tieBest, tieWorst: tieWorst };
	}

	function buildTableRows( rows, cities, ids, metricLabel, wins, losses ) {
		var h = '';
		var ri, row, analysis, i, cls, display;
		h += '<div class="wsergo-cmp-table-wrap wsp-table-wrap"><table class="wsergo-cmp-table wsp-table">';
		h += '<colgroup><col class="wsergo-cmp-col-metric">';
		for ( i = 0; i < ids.length; i++ ) {
			h += '<col class="wsergo-cmp-col-city">';
		}
		h += '</colgroup><thead><tr>';
		h += '<th class="wsergo-cmp-table__metric-h" scope="col">' + esc( metricLabel ) + '</th>';
		for ( i = 0; i < ids.length; i++ ) {
			h += '<th class="wsergo-cmp-table__city" scope="col">' + cityColHeader( cities[ ids[ i ] ] ) + '</th>';
		}
		h += '</tr></thead><tbody>';
		for ( ri = 0; ri < rows.length; ri++ ) {
			row = rows[ ri ];
			analysis = analyzeRow( row, cities, ids, wins, losses );
			h += '<tr class="wsergo-cmp-table__row' + ( row.isAxis ? ' wsergo-cmp-table__row--axis' : '' ) + '">';
			h += '<td class="wsergo-cmp-table__metric">';
			h += '<span class="wsergo-cmp-table__metric-label">' + esc( row.label ) + '</span>';
			if ( row.sub ) {
				h += '<br><span class="wsergo-cmp-table__sub">' + esc( row.sub ) + '</span>';
			}
			h += '</td>';
			for ( i = 0; i < analysis.vals.length; i++ ) {
				cls = '';
				if ( analysis.vals[ i ].v != null && analysis.best != null && analysis.vals[ i ].v === analysis.best && ! analysis.tieBest ) {
					cls = 'wsergo-cmp-cell--best';
				} else if ( analysis.vals[ i ].v != null && analysis.worst != null && analysis.vals[ i ].v === analysis.worst && ! analysis.tieWorst ) {
					cls = 'wsergo-cmp-cell--worst';
				}
				display = analysis.vals[ i ].v != null ? esc( fmtNum( analysis.vals[ i ].v ) ) : '<span class="wsergo-cmp-cell__na">—</span>';
				h += '<td class="wsergo-cmp-table__val ' + cls + '">' + display + '</td>';
			}
			h += '</tr>';
		}
		h += '</tbody></table></div>';
		return h;
	}

	/**
	 * @param {HTMLElement} el
	 * @param {Object<string,object>} cities
	 * @param {string[]} ids
	 * @param {{dimKeys:string[],dimLabels:Object,eLabel:string,axisLabels:Object,strings:Object,help:Object}} opts
	 * @returns {number[]} numeric city ids for chart highlight
	 */
	function renderCityCompare( el, cities, ids, opts ) {
		if ( ! el ) {
			return [];
		}
		opts = opts || {};
		var dimKeys = opts.dimKeys || [];
		var dimLab = opts.dimLabels || {};
		var axl = opts.axisLabels || {};
		var s = opts.strings || {};
		var help = opts.help || {};
		var eLab = opts.eLabel || 'E';
		var rowMeta = {};

		ids = ( ids || [] ).filter( function ( id ) {
			return id && cities[ id ];
		} );
		if ( ids.length < 2 ) {
			el.innerHTML =
				'<p class="wsergo-cmp-placeholder">' +
				esc( s.need_two || 'Выберите минимум два города для сравнения.' ) +
				'</p>';
			return [];
		}

		var axisRows = [
			{ key: '__e__', label: eLab, isAxis: true, dimKey: '', get: cityE },
		];
		var di, dk;
		for ( di = 0; di < dimKeys.length; di++ ) {
			dk = dimKeys[ di ];
			axisRows.push( {
				key: 'dim:' + dk,
				label: dimLab[ dk ] || axl[ dk ] || dk,
				isAxis: true,
				dimKey: dk,
				get: ( function ( dimKey ) {
					return function ( c ) {
						return dimValue( c, dimKey );
					};
				} )( dk ),
			} );
		}

		var indicators = indicatorUnion( cities, ids );
		var indicatorRows = [];
		for ( di = 0; di < indicators.length; di++ ) {
			( function ( meta ) {
				var row = {
					key: 'ind:' + meta.id,
					label: meta.label,
					sub: axl[ meta.dimension ] || meta.dimension || '',
					isAxis: false,
					dimKey: meta.dimension || '',
					get: function ( c ) {
						return scoreForIndicator( c, meta.id );
					},
				};
				if ( rowHasAnyValue( row, cities, ids ) ) {
					indicatorRows.push( row );
				}
			} )( indicators[ di ] );
		}

		var allRows = axisRows.concat( indicatorRows );
		for ( di = 0; di < allRows.length; di++ ) {
			rowMeta[ allRows[ di ].key ] = allRows[ di ];
		}

		var wins = {};
		var losses = {};
		var i, id;
		ids.forEach( function ( cid ) {
			wins[ cid ] = [];
			losses[ cid ] = [];
		} );

		var h = '<div class="wsergo-cmp-result">';
		h +=
			'<h4 class="wsergo-cmp-summary-title">' +
			esc( s.summary_title || 'Краткий итог по выбранным городам' ) +
			'</h4>';
		h += '<div class="wsergo-cmp-summary">';
		for ( i = 0; i < ids.length; i++ ) {
			id = ids[ i ];
			h +=
				'<article class="wsergo-cmp-summary__card" data-cmp-idx="' +
				i +
				'">' +
				summaryCityHeader( cities[ id ], i ) +
				'<div class="wsergo-cmp-summary__body" data-cmp-stats="' +
				esc( id ) +
				'"></div></article>';
		}
		h += '</div>';

		h += '<h5 class="wsergo-cmp-section-title">' + esc( s.section_axes || 'Сводные индексы' ) + '</h5>';
		h += buildTableRows( axisRows, cities, ids, s.metric || 'Показатель', wins, losses );

		if ( indicatorRows.length ) {
			h += '<h5 class="wsergo-cmp-section-title wsergo-cmp-section-title--ind">' + esc( s.section_indicators || 'Детальные показатели' ) + '</h5>';
			h += buildTableRows( indicatorRows, cities, ids, s.metric || 'Показатель', wins, losses );
		}

		h +=
			'<p class="wsergo-cmp-legend"><span class="wsergo-cmp-legend__swatch wsergo-cmp-legend__swatch--best" aria-hidden="true"></span> ' +
			esc( s.legend_best || 'лучше среди выбранных' ) +
			' <span class="wsergo-cmp-legend__swatch wsergo-cmp-legend__swatch--worst" aria-hidden="true"></span> ' +
			esc( s.legend_worst || 'слабее среди выбранных' ) +
			'</p>';
		h += '</div>';

		el.innerHTML = h;

		for ( i = 0; i < ids.length; i++ ) {
			id = ids[ i ];
			var stats = el.querySelector( '[data-cmp-stats="' + id + '"]' );
			if ( ! stats ) {
				continue;
			}
			var lines = '';
			if ( wins[ id ].length ) {
				lines +=
					'<section class="wsergo-cmp-summary__block wsergo-cmp-summary__block--win">' +
					'<h5 class="wsergo-cmp-summary__block-title">' +
					esc( s.stronger || 'Сильнее' ) +
					'</h5>' +
					metricListHtml( wins[ id ], rowMeta, cities, ids, help, s ) +
					'</section>';
			}
			if ( losses[ id ].length ) {
				lines +=
					'<section class="wsergo-cmp-summary__block wsergo-cmp-summary__block--loss">' +
					'<h5 class="wsergo-cmp-summary__block-title">' +
					esc( s.weaker || 'Слабее' ) +
					'</h5>' +
					metricListHtml( losses[ id ], rowMeta, cities, ids, help, s ) +
					'</section>';
			}
			if ( ! wins[ id ].length && ! losses[ id ].length ) {
				lines +=
					'<p class="wsergo-cmp-summary__empty">' +
					esc( s.no_lead || 'Нет однозначных лидерств по строкам таблицы.' ) +
					'</p>';
			}
			stats.innerHTML = lines;
		}

		return ids.map( function ( x ) {
			return parseInt( x, 10 ) || 0;
		} );
	}

	window.wsergoRenderCityCompare = renderCityCompare;
} )( window );
