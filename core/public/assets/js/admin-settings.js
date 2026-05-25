/**
 * Вкладки настроек Ergonomics (уровни + внутренние вкладки страна/город).
 */
jQuery( function ( $ ) {
	'use strict';

	// Вкладки уровней — inline-скрипт в конце страницы (wsergo-settings-tabs-inline).
	if ( document.getElementById( 'wsergo-settings-tabs-inline' ) ) {
		return;
	}

	var cfg = window.wsergoAdminSettings || {};

	function wsergoReplaceHash( hash ) {
		if ( ! window.history || ! window.history.replaceState ) {
			return;
		}
		window.history.replaceState( null, '', window.location.pathname + window.location.search + hash );
	}

	function wsergoTriggerScope( scope ) {
		$( document ).trigger( 'wsergo-scope-activated', [ scope ] );
	}

	function wsergoActivateScope( scope ) {
		if ( ! scope ) {
			scope =
				$( '.wsergo-ergo-scope-nav a[data-wsergo-scope]' ).first().attr( 'data-wsergo-scope' ) ||
				'country';
		}
		$( '.wsergo-ergo-scope-nav a[data-wsergo-scope]' ).removeClass( 'nav-tab-active' );
		$( '.wsergo-ergo-scope-nav a[data-wsergo-scope="' + scope + '"]' ).addClass( 'nav-tab-active' );
		$( '.wsergo-scope-panel' ).each( function () {
			this.style.setProperty( 'display', 'none', 'important' );
		} ).removeClass( 'wsergo-scope-panel--active' );
		var $panel = $( '#wsergo-panel-' + scope );
		if ( $panel.length ) {
			$panel.each( function () {
				this.style.setProperty( 'display', 'block', 'important' );
			} ).addClass( 'wsergo-scope-panel--active' );
		}
		wsergoTriggerScope( scope );
		return scope;
	}

	function wsergoActivateInnerTab( id ) {
		if ( ! id ) {
			return;
		}
		var $root = $( '#wsergo-panel-country' );
		$root.find( '.wsergo-country-inner-nav a[data-tab]' ).removeClass( 'nav-tab-active' );
		$root.find( '.wsergo-country-inner-nav a[data-tab="' + id + '"]' ).addClass( 'nav-tab-active' );
		var $form = $root.find( 'form.wsergo-settings-form' ).first();
		$form.find( '.wsergo-tab-panel' ).hide();
		$form.find( '#' + id ).show();
		if ( id === 'tab-data' ) {
			setTimeout( function () {
				$( document ).trigger( 'wsergo-kmeans-layout-resize' );
			}, 80 );
		}
		if ( id === 'tab-formula' ) {
			setTimeout( wsergoRefreshMacroReferenceExamples, 80 );
		}
	}

	function wsergoRefreshMacroReferenceExamples() {
		var $panel = $( '#tab-formula' );
		if ( ! $panel.length || ! $panel.is( ':visible' ) ) {
			return;
		}
		var countryId = parseInt( $( '#wsergo_macro_reference_country' ).val(), 10 ) || 0;
		var year = parseInt( $( '#wsergo_macro_reference_year' ).val(), 10 ) || 0;
		var nonce = cfg.adminNonce || '';
		if ( ! countryId || ! nonce || typeof ajaxurl === 'undefined' ) {
			return;
		}
		$panel.find( '.wsergo-macro-ex-value' ).text( '…' );
		$.post( ajaxurl, {
			action: 'wsergo_macro_reference_examples',
			nonce: nonce,
			country_id: countryId,
			year: year,
		} )
			.done( function ( res ) {
				if ( ! res || ! res.success || ! res.data ) {
					return;
				}
				var vals = res.data.values || {};
				$panel.find( '[data-wsergo-macro-ex-signal]' ).each( function () {
					var sig = $( this ).attr( 'data-wsergo-macro-ex-signal' ) || '';
					$( this )
						.find( '.wsergo-macro-ex-value' )
						.text( vals[ sig ] !== undefined ? vals[ sig ] : '—' );
				} );
				var $note = $( '#wsergo-macro-ref-example-note' );
				var $text = $note.find( '.wsergo-macro-ref-example-note__text' );
				if ( $note.length && $text.length ) {
					$note.show();
					if ( res.data.note ) {
						$text.text(
							res.data.note +
								' — значения из загруженных макро-CSV (сырой ряд, опорный год).'
						);
					} else if ( ! res.data.has_row ) {
						$text.text(
							'Для выбранной эталонной страны нет строки сырых признаков в кэше расчёта: проверьте код ISO2 в карточке страны и наличие строк в CSV за опорный год.'
						);
					}
				}
			} )
			.fail( function () {
				$panel.find( '.wsergo-macro-ex-value' ).text( '—' );
			} );
	}

	function wsergoActivateCityInnerTab( id ) {
		if ( ! id ) {
			id = 'city-tab-overview';
		}
		var $root = $( '#wsergo-panel-city' );
		$root.find( '.wsergo-city-inner-nav a[data-city-tab]' ).removeClass( 'nav-tab-active' );
		$root.find( '.wsergo-city-inner-nav a[data-city-tab="' + id + '"]' ).addClass( 'nav-tab-active' );
		var $form = $root.find( 'form.wsergo-settings-form' ).first();
		$form.find( '.wsergo-city-tab-panel' ).hide();
		$form.find( '#' + id ).show();
	}

	function wsergoApplyHash() {
		var h = window.location.hash || '';
		var scopeMatched = false;

		$( '.wsergo-ergo-scope-nav a[data-wsergo-scope]' ).each( function () {
			var sc = $( this ).attr( 'data-wsergo-scope' ) || '';
			if ( h === '#ergo-' + sc ) {
				scopeMatched = true;
				wsergoActivateScope( sc );
				if ( sc === 'country' ) {
					wsergoActivateInnerTab( 'tab-data' );
				} else if ( sc === 'city' ) {
					wsergoActivateCityInnerTab( 'city-tab-overview' );
				}
				return false;
			}
		} );

		if ( scopeMatched ) {
			return;
		}

		if ( h.indexOf( '#city-tab-' ) === 0 ) {
			wsergoActivateScope( 'city' );
			wsergoActivateCityInnerTab( h.slice( 1 ) );
			return;
		}

		if ( h.indexOf( '#tab-territory-' ) === 0 ) {
			wsergoActivateScope( 'territory' );
			return;
		}
		if ( h === '#tab-data' || h === '#tab-formula' ) {
			wsergoActivateScope( 'country' );
			wsergoActivateInnerTab( h.slice( 1 ) );
			return;
		}

		var first =
			$( '.wsergo-ergo-scope-nav a[data-wsergo-scope]' ).first().attr( 'data-wsergo-scope' ) ||
			'country';
		wsergoActivateScope( first );
		if ( first === 'city' ) {
			wsergoActivateCityInnerTab( 'city-tab-overview' );
		} else if ( first === 'country' ) {
			wsergoActivateInnerTab( 'tab-data' );
		}
	}

	window.wsergoActivateScope = wsergoActivateScope;
	window.wsergoActivateInnerTab = wsergoActivateInnerTab;
	window.wsergoActivateCityInnerTab = wsergoActivateCityInnerTab;
	window.wsergoApplyHash = wsergoApplyHash;

	if ( ! $( '.wsergo-scope-panel' ).length ) {
		return;
	}

	$( document ).on( 'click', '.wsergo-ergo-scope-nav a[data-wsergo-scope]', function ( e ) {
		e.preventDefault();
		var scope = $( this ).attr( 'data-wsergo-scope' ) || '';
		wsergoActivateScope( scope );
		wsergoReplaceHash( '#ergo-' + scope );
		if ( scope === 'city' ) {
			wsergoActivateCityInnerTab( 'city-tab-overview' );
		} else if ( scope === 'country' ) {
			wsergoActivateInnerTab( 'tab-data' );
		}
	} );

	$( document ).on( 'click', '.wsergo-country-inner-nav a[data-tab]', function ( e ) {
		e.preventDefault();
		var tabId = $( this ).attr( 'data-tab' ) || '';
		wsergoActivateScope( 'country' );
		wsergoActivateInnerTab( tabId );
		wsergoReplaceHash( '#' + tabId );
	} );

	$( document ).on( 'click', '.wsergo-city-inner-nav a[data-city-tab]', function ( e ) {
		e.preventDefault();
		var cityTabId = $( this ).attr( 'data-city-tab' ) || '';
		wsergoActivateScope( 'city' );
		wsergoActivateCityInnerTab( cityTabId );
		wsergoReplaceHash( '#' + cityTabId );
	} );

	$( document ).on( 'click', 'a.wsergo-tab-deep-link[href^="#tab-"]', function ( e ) {
		var href = $( this ).attr( 'href' ) || '';
		if ( href.indexOf( '#tab-territory-' ) === 0 ) {
			e.preventDefault();
			wsergoActivateScope( 'territory' );
			wsergoReplaceHash( href );
			return;
		}
		if ( href === '#tab-data' || href === '#tab-formula' ) {
			e.preventDefault();
			wsergoActivateScope( 'country' );
			wsergoActivateInnerTab( href.slice( 1 ) );
			wsergoReplaceHash( href );
		}
	} );

	$( document ).on( 'click', '.wsergo-add-macro-term-row', function () {
		var axis = $( this ).attr( 'data-axis' ) || '';
		var $tbody = $( 'tbody.wsergo-macro-axis-tbody[data-axis="' + axis + '"]' );
		var $tpl = $tbody.find( 'tr.wsergo-macro-term-template' ).first();
		if ( ! $tbody.length || ! $tpl.length ) {
			return;
		}
		var prefix = ( cfg.macroAxisOpt || 'wsergo_macro_axis_terms' ) + '[' + axis + '][';
		var maxIx = -1;
		$tbody.find( 'tr' ).each( function () {
			if ( $( this ).hasClass( 'wsergo-macro-term-template' ) ) {
				return;
			}
			$( this )
				.find( 'select[name], input[name]' )
				.each( function () {
					var n = this.name || '';
					var p = n.indexOf( prefix );
					if ( p === -1 ) {
						return;
					}
					var m = /^(\d+)\]/.exec( n.substring( p + prefix.length ) );
					if ( m ) {
						maxIx = Math.max( maxIx, parseInt( m[ 1 ], 10 ) );
					}
				} );
		} );
		var nextIx = maxIx + 1;
		var $row = $tpl.clone();
		$row.removeClass( 'wsergo-macro-term-template' ).removeAttr( 'style aria-hidden' ).show();
		$row.find( 'input, select' ).prop( 'disabled', false );
		$row.find( 'input, select' ).each( function () {
			if ( this.name ) {
				this.name = this.name.replace( /999999/g, String( nextIx ) );
			}
		} );
		$tbody.append( $row );
	} );

	wsergoApplyHash();
	$( window ).on( 'hashchange', wsergoApplyHash );

	$( document ).on(
		'change',
		'#wsergo_macro_reference_year, #wsergo_macro_reference_country',
		function () {
			if ( $( '#tab-formula' ).is( ':visible' ) ) {
				wsergoRefreshMacroReferenceExamples();
			}
		}
	);

	$( '#wsergo-test-formula-btn' ).on( 'click', function () {
		var formula = $( '#wsergo_test_formula_inline' ).length
			? $( '#wsergo_test_formula_inline' ).val()
			: $( 'textarea[name="wsergo_models[0][leaf_formula]"]' ).first().val();
		$.post( ajaxurl, {
			action: 'wsergo_test_formula',
			nonce: cfg.formulaNonce,
			formula: formula,
		} ).done( function ( r ) {
			if ( r.success ) {
				$( '#wsergo-formula-test-result' ).text( 'E ≈ ' + r.data.value );
			} else {
				$( '#wsergo-formula-test-result' ).text( r.data.message || 'Error' );
			}
		} );
	} );

	$( '#wsergo-city-test-formula-btn' ).on( 'click', function () {
		var formula = $( '#wsergo_city_test_formula_inline' ).length
			? $( '#wsergo_city_test_formula_inline' ).val()
			: $( 'textarea[name="wsergo_models[0][leaf_formula]"]' ).first().val();
		$.post( ajaxurl, {
			action: 'wsergo_test_formula',
			nonce: cfg.formulaNonce,
			formula: formula,
		} ).done( function ( r ) {
			if ( r.success ) {
				$( '#wsergo-city-formula-test-result' ).text( 'E ≈ ' + r.data.value );
			} else {
				$( '#wsergo-city-formula-test-result' ).text( r.data.message || 'Error' );
			}
		} );
	} );

	$( document ).on( 'click', '#wsergo-add-indicator-row', function () {
		var $rows = $( '#wsergo-indicator-rows' );
		var $tpl = $rows.find( 'tr.wsergo-indicator-template' ).first();
		var $n = $tpl.length
			? $tpl.clone().removeClass( 'wsergo-indicator-template' ).removeAttr( 'style' ).show()
			: $rows.find( 'tr' ).last().clone();
		if ( ! $n.length ) {
			return;
		}
		$n.find( 'input[type=text]' ).val( '' );
		$n.find( 'select[name$="[dimension]"]' ).prop( 'selectedIndex', 0 );
		$n.find( 'select[name$="[direction]"]' ).prop( 'selectedIndex', 0 );
		$n.find( 'input[name$="[vmin]"]' ).val( '0' );
		$n.find( 'input[name$="[vmax]"]' ).val( '100' );
		$n.find( 'input[name$="[weight]"]' ).val( '1' );
		if ( $tpl.length ) {
			$tpl.before( $n );
		} else {
			$rows.append( $n );
		}
	} );

	$( document ).on( 'click', '#wsergo-add-city-map-row', function () {
		var $rows = $( '#wsergo-city-map-rows' );
		var $tpl = $rows.find( 'tr.wsergo-city-map-template' ).first();
		var $n = $tpl.length
			? $tpl.clone().removeClass( 'wsergo-city-map-template' ).removeAttr( 'style' ).show()
			: $rows.find( 'tr' ).last().clone();
		if ( ! $n.length ) {
			return;
		}
		$n.find( 'select' ).prop( 'selectedIndex', 0 );
		if ( $tpl.length ) {
			$tpl.before( $n );
		} else {
			$rows.append( $n );
		}
	} );

	$( document ).on( 'click', '#wsergo-city-add-indicator-row', function () {
		var $rows = $( '#wsergo-city-indicator-rows' );
		var $tpl = $rows.find( 'tr.wsergo-city-indicator-template' ).first();
		var $n = $tpl.length
			? $tpl.clone().removeClass( 'wsergo-city-indicator-template' ).removeAttr( 'style' ).show()
			: $rows.find( 'tr' ).last().clone();
		if ( ! $n.length ) {
			return;
		}
		$n.find( 'input[type=text]' ).val( '' );
		$n.find( 'select[name$="[dimension]"]' ).prop( 'selectedIndex', 0 );
		$n.find( 'select[name$="[direction]"]' ).prop( 'selectedIndex', 0 );
		$n.find( 'input[name$="[vmin]"]' ).val( '0' );
		$n.find( 'input[name$="[vmax]"]' ).val( '100' );
		$n.find( 'input[name$="[weight]"]' ).val( '1' );
		if ( $tpl.length ) {
			$tpl.before( $n );
		} else {
			$rows.append( $n );
		}
	} );
} );
