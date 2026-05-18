/**
 * Вкладки настроек: уровни из levels-registry и внутренние вкладки панелей.
 */
( function () {
	'use strict';

	var cfg = window.wsergoAdminSettings || {};

	function qs( sel, root ) {
		return ( root || document ).querySelector( sel );
	}

	function qsa( sel, root ) {
		return Array.prototype.slice.call( ( root || document ).querySelectorAll( sel ) );
	}

	function wsergoReplaceHash( hash ) {
		if ( ! window.history || ! window.history.replaceState ) {
			return;
		}
		window.history.replaceState( null, '', window.location.pathname + window.location.search + hash );
	}

	function wsergoFirstScope() {
		var link = qs( '.wsergo-ergo-scope-nav a[data-wsergo-scope]' );
		return link ? link.getAttribute( 'data-wsergo-scope' ) || 'country' : 'country';
	}

	function wsergoDefaultInnerTab( scope ) {
		if ( scope === 'city' ) {
			return 'city-tab-overview';
		}
		if ( scope === 'country' ) {
			return 'tab-data';
		}
		var panel = document.getElementById( 'wsergo-panel-' + scope );
		if ( panel ) {
			var inner = panel.querySelector( '[class*="inner-nav"] a[data-tab], [class*="inner-nav"] a[data-city-tab]' );
			if ( inner ) {
				return inner.getAttribute( 'data-tab' ) || inner.getAttribute( 'data-city-tab' ) || '';
			}
		}
		return '';
	}

	function wsergoScopeHash( scope ) {
		return '#ergo-' + scope;
	}

	function wsergoTriggerScopeActivated( scope ) {
		if ( typeof jQuery !== 'undefined' ) {
			jQuery( document ).trigger( 'wsergo-scope-activated', [ scope ] );
		}
	}

	function wsergoActivateScope( scope ) {
		scope = scope || wsergoFirstScope();
		qsa( '.wsergo-ergo-scope-nav a[data-wsergo-scope]' ).forEach( function ( a ) {
			a.classList.toggle( 'nav-tab-active', a.getAttribute( 'data-wsergo-scope' ) === scope );
		} );
		qsa( '.wsergo-scope-panel' ).forEach( function ( p ) {
			p.classList.remove( 'wsergo-scope-panel--active' );
		} );
		var panel = document.getElementById( 'wsergo-panel-' + scope );
		if ( panel ) {
			panel.classList.add( 'wsergo-scope-panel--active' );
		} else {
			var first = qs( '.wsergo-scope-panel' );
			if ( first ) {
				first.classList.add( 'wsergo-scope-panel--active' );
			}
		}
		wsergoTriggerScopeActivated( scope );
	}

	function wsergoShowInnerPanels( formSel, panelClass, panelId ) {
		var form = qs( formSel );
		if ( ! form ) {
			return;
		}
		qsa( '.' + panelClass, form ).forEach( function ( el ) {
			el.style.display = 'none';
		} );
		var panel = document.getElementById( panelId );
		if ( panel ) {
			panel.style.display = '';
		}
	}

	function wsergoActivateInnerTab( id ) {
		if ( ! id ) {
			return;
		}
		qsa( '.wsergo-country-inner-nav a[data-tab]' ).forEach( function ( a ) {
			a.classList.toggle( 'nav-tab-active', a.getAttribute( 'data-tab' ) === id );
		} );
		wsergoShowInnerPanels( '#wsergo-panel-country form.wsergo-settings-form', 'wsergo-tab-panel', id );
		if ( id === 'tab-data' ) {
			wsergoTriggerScopeActivated( 'country' );
		}
	}

	function wsergoActivateCityInnerTab( id ) {
		if ( ! id ) {
			return;
		}
		qsa( '.wsergo-city-inner-nav a[data-city-tab]' ).forEach( function ( a ) {
			a.classList.toggle( 'nav-tab-active', a.getAttribute( 'data-city-tab' ) === id );
		} );
		wsergoShowInnerPanels( '#wsergo-panel-city form.wsergo-settings-form', 'wsergo-city-tab-panel', id );
	}

	function wsergoApplyHash() {
		var h = window.location.hash || '';
		var scopeLinks = qsa( '.wsergo-ergo-scope-nav a[data-wsergo-scope]' );
		var matchedScope = null;
		scopeLinks.forEach( function ( a ) {
			var sc = a.getAttribute( 'data-wsergo-scope' ) || '';
			if ( h === wsergoScopeHash( sc ) ) {
				matchedScope = sc;
			}
		} );

		if ( matchedScope ) {
			wsergoActivateScope( matchedScope );
			var def = wsergoDefaultInnerTab( matchedScope );
			if ( matchedScope === 'city' ) {
				wsergoActivateCityInnerTab( def );
			} else if ( matchedScope === 'country' ) {
				if ( h.indexOf( '#tab-' ) === 0 ) {
					wsergoActivateInnerTab( h.slice( 1 ) );
				} else {
					wsergoActivateInnerTab( def );
				}
			}
			return;
		}

		if ( h.indexOf( '#tab-' ) === 0 ) {
			wsergoActivateScope( 'country' );
			wsergoActivateInnerTab( h.slice( 1 ) );
			return;
		}

		var first = wsergoFirstScope();
		wsergoActivateScope( first );
		if ( first === 'city' ) {
			wsergoActivateCityInnerTab( wsergoDefaultInnerTab( 'city' ) );
		} else if ( first === 'country' ) {
			wsergoActivateInnerTab( wsergoDefaultInnerTab( 'country' ) );
		}
	}

	function wsergoMacroNextIndex( tbody, axis ) {
		var prefix = ( cfg.macroAxisOpt || 'wsergo_macro_axis_terms' ) + '[' + axis + '][';
		var maxIx = -1;
		qsa( 'tr', tbody ).forEach( function ( tr ) {
			if ( tr.classList.contains( 'wsergo-macro-term-template' ) ) {
				return;
			}
			qsa( 'select[name], input[name]', tr ).forEach( function ( el ) {
				var n = el.name || '';
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
		return maxIx + 1;
	}

	function wsergoBindTabs() {
		if ( ! qs( '.wsergo-scope-panel' ) ) {
			return;
		}

		document.addEventListener(
			'click',
			function ( e ) {
				var scopeLink = e.target.closest( '.wsergo-ergo-scope-nav a[data-wsergo-scope]' );
				if ( scopeLink ) {
					e.preventDefault();
					var scope = scopeLink.getAttribute( 'data-wsergo-scope' ) || '';
					wsergoActivateScope( scope );
					wsergoReplaceHash( wsergoScopeHash( scope ) );
					if ( scope === 'city' ) {
						wsergoActivateCityInnerTab( wsergoDefaultInnerTab( 'city' ) );
					} else if ( scope === 'country' ) {
						wsergoActivateInnerTab( wsergoDefaultInnerTab( 'country' ) );
					}
					return;
				}

				var countryTab = e.target.closest( '.wsergo-country-inner-nav a[data-tab]' );
				if ( countryTab ) {
					e.preventDefault();
					var tabId = countryTab.getAttribute( 'data-tab' ) || '';
					wsergoActivateScope( 'country' );
					wsergoActivateInnerTab( tabId );
					wsergoReplaceHash( '#' + tabId );
					return;
				}

				var cityTab = e.target.closest( '.wsergo-city-inner-nav a[data-city-tab]' );
				if ( cityTab ) {
					e.preventDefault();
					var cityTabId = cityTab.getAttribute( 'data-city-tab' ) || '';
					wsergoActivateScope( 'city' );
					wsergoActivateCityInnerTab( cityTabId );
					wsergoReplaceHash( '#ergo-city' );
					return;
				}

				var deep = e.target.closest( 'a.wsergo-tab-deep-link[href^="#tab-"]' );
				if ( deep ) {
					var href = deep.getAttribute( 'href' ) || '';
					if ( href.indexOf( '#tab-' ) === 0 ) {
						e.preventDefault();
						wsergoActivateScope( 'country' );
						wsergoActivateInnerTab( href.slice( 1 ) );
						wsergoReplaceHash( href );
					}
					return;
				}

				var addMacro = e.target.closest( '.wsergo-add-macro-term-row' );
				if ( addMacro ) {
					var axis = addMacro.getAttribute( 'data-axis' ) || '';
					var tbody = qs( 'tbody.wsergo-macro-axis-tbody[data-axis="' + axis + '"]' );
					if ( ! tbody ) {
						return;
					}
					var tpl = qs( 'tr.wsergo-macro-term-template', tbody );
					if ( ! tpl ) {
						return;
					}
					var nextIx = wsergoMacroNextIndex( tbody, axis );
					var row = tpl.cloneNode( true );
					row.classList.remove( 'wsergo-macro-term-template' );
					row.removeAttribute( 'style' );
					row.removeAttribute( 'aria-hidden' );
					row.style.display = '';
					qsa( 'input, select', row ).forEach( function ( el ) {
						el.disabled = false;
						if ( el.name ) {
							el.name = el.name.replace( /999999/g, String( nextIx ) );
						}
					} );
					tbody.appendChild( row );
				}
			},
			true
		);

		wsergoApplyHash();
		window.addEventListener( 'hashchange', wsergoApplyHash );
	}

	function wsergoBindJqueryExtras() {
		if ( typeof jQuery === 'undefined' ) {
			return;
		}
		var $ = jQuery;

		$( '#wsergo-test-formula-btn' ).on( 'click', function () {
			var formula = $( '#wsergo_test_formula_inline' ).length
				? $( '#wsergo_test_formula_inline' ).val()
				: $( 'textarea[name="wsergo_models[0][leaf_formula]"]' ).first().val();
			$.post(
				ajaxurl,
				{
					action: 'wsergo_test_formula',
					nonce: cfg.formulaNonce,
					formula: formula,
				},
				function ( r ) {
					if ( r.success ) {
						$( '#wsergo-formula-test-result' ).text( 'E ≈ ' + r.data.value );
					} else {
						$( '#wsergo-formula-test-result' ).text( r.data.message || 'Error' );
					}
				}
			);
		} );

		$( '#wsergo-city-test-formula-btn' ).on( 'click', function () {
			var formula = $( '#wsergo_city_test_formula_inline' ).length
				? $( '#wsergo_city_test_formula_inline' ).val()
				: $( 'textarea[name="wsergo_models[0][leaf_formula]"]' ).first().val();
			$.post(
				ajaxurl,
				{
					action: 'wsergo_test_formula',
					nonce: cfg.formulaNonce,
					formula: formula,
				},
				function ( r ) {
					if ( r.success ) {
						$( '#wsergo-city-formula-test-result' ).text( 'E ≈ ' + r.data.value );
					} else {
						$( '#wsergo-city-formula-test-result' ).text( r.data.message || 'Error' );
					}
				}
			);
		} );

		$( document ).on( 'click', '#wsergo-add-indicator-row', function () {
			var $rows = $( '#wsergo-indicator-rows' );
			var $tpl = $rows.find( 'tr.wsergo-indicator-template' ).first();
			var $n;
			if ( $tpl.length ) {
				$n = $tpl.clone().removeClass( 'wsergo-indicator-template' ).removeAttr( 'style' ).show();
			} else {
				var $last = $rows.find( 'tr' ).last();
				if ( ! $last.length ) {
					return;
				}
				$n = $last.clone();
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
			var $n;
			if ( $tpl.length ) {
				$n = $tpl.clone().removeClass( 'wsergo-city-map-template' ).removeAttr( 'style' ).show();
			} else {
				var $last = $rows.find( 'tr' ).last();
				if ( ! $last.length ) {
					return;
				}
				$n = $last.clone();
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
			var $n;
			if ( $tpl.length ) {
				$n = $tpl.clone().removeClass( 'wsergo-city-indicator-template' ).removeAttr( 'style' ).show();
			} else {
				var $last = $rows.find( 'tr' ).last();
				if ( ! $last.length ) {
					return;
				}
				$n = $last.clone();
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
	}

	function wsergoInit() {
		wsergoBindTabs();
		wsergoBindJqueryExtras();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', wsergoInit );
	} else {
		wsergoInit();
	}
} )();
