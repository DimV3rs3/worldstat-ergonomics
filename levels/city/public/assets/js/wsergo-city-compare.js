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
		var h = '<span class="wsergo-cmp-table__city-name">' + esc( c.name || '' ) + '</span>';
		if ( c.country_name ) {
			h +=
				'<br><span class="wsergo-cmp-table__city-country">' + esc( c.country_name ) + '</span>';
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
			esc( c.name || '' ) +
			'</span>';
		if ( c.country_name ) {
			h +=
				'<span class="wsergo-cmp-summary__country">' + esc( c.country_name ) + '</span>';
		}
		h += '</div></div>';
		return h;
	}

	function metricListHtml( items ) {
		if ( ! items || ! items.length ) {
			return '';
		}
		var h = '<ul class="wsergo-cmp-summary__metrics">';
		var i;
		for ( i = 0; i < items.length; i++ ) {
			h += '<li>' + esc( items[ i ] ) + '</li>';
		}
		h += '</ul>';
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
					wins[ bestIds[ 0 ].id ].push( row.label );
				}
				if ( ! tieWorst && worstIds.length === 1 ) {
					losses[ worstIds[ 0 ].id ].push( row.label );
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
	 * @param {{dimKeys:string[],dimLabels:Object,eLabel:string,axisLabels:Object,strings:Object}} opts
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
		var eLab = opts.eLabel || 'E';

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

		var axisRows = [ { key: '__e__', label: eLab, isAxis: true, get: cityE } ];
		var di, dk;
		for ( di = 0; di < dimKeys.length; di++ ) {
			dk = dimKeys[ di ];
			axisRows.push( {
				key: dk,
				label: dimLab[ dk ] || axl[ dk ] || dk,
				isAxis: true,
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
					get: function ( c ) {
						return scoreForIndicator( c, meta.id );
					},
				};
				if ( rowHasAnyValue( row, cities, ids ) ) {
					indicatorRows.push( row );
				}
			} )( indicators[ di ] );
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
					metricListHtml( wins[ id ] ) +
					'</section>';
			}
			if ( losses[ id ].length ) {
				lines +=
					'<section class="wsergo-cmp-summary__block wsergo-cmp-summary__block--loss">' +
					'<h5 class="wsergo-cmp-summary__block-title">' +
					esc( s.weaker || 'Слабее' ) +
					'</h5>' +
					metricListHtml( losses[ id ] ) +
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
