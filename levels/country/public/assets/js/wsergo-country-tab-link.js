/**
 * Переход на вкладку страны «Сравнение» (и совместимость #ergo-compare).
 */
(function ( $ ) {
	'use strict';

	var TAB_COMPARE = 'compare';
	var TAB_LEGACY = 'ergo-compare';

	function getMainTabs() {
		return $( '.wsp-country-page > .wsp-container > .wsp-tabs' ).first();
	}

	function resolveTabId( raw ) {
		if ( ! raw ) {
			return '';
		}
		if ( raw === TAB_LEGACY ) {
			return TAB_COMPARE;
		}
		return raw;
	}

	function openCountryTab( tabId ) {
		tabId = resolveTabId( tabId );
		if ( ! tabId ) {
			return false;
		}

		var $mainTabs = getMainTabs();
		if ( ! $mainTabs.length ) {
			return false;
		}

		var $btn = $mainTabs.find(
			'> nav.wsp-tab-nav .wsp-tab-btn[data-tab="' + tabId + '"]'
		);
		if ( ! $btn.length ) {
			return false;
		}

		var $panel = $mainTabs.find(
			'> .wsp-tab-panels > .wsp-tab-panel[data-tab="' + tabId + '"]'
		);
		var needsLoad = $panel.find( '.wsp-tab-loading' ).length > 0;

		function finish() {
			if ( ! $panel || ! $panel.length ) {
				return;
			}
			$panel.find( '[data-wsergo-country-explorer="1"]' ).each( function () {
				this.removeAttribute( 'data-wsergo-country-booted' );
			} );
			document.dispatchEvent(
				new CustomEvent( 'wsp:tab:loaded', { detail: { panel: $panel[ 0 ] } } )
			);
		}

		if ( $btn.hasClass( 'wsp-tab-active' ) && ! needsLoad ) {
			finish();
			return true;
		}

		$( document ).one( 'wsp:tab:loaded', function ( e, loadedTabId ) {
			if ( loadedTabId === tabId ) {
				finish();
			}
		} );

		$btn.trigger( 'click' );

		if ( ! needsLoad ) {
			window.setTimeout( finish, 120 );
		}

		return true;
	}

	function openFromHash() {
		var raw = ( window.location.hash || '' ).replace( /^#/, '' ).trim();
		if ( ! raw ) {
			return;
		}
		var parts = raw.split( '/' ).filter( Boolean );
		var tab = resolveTabId( parts[ 0 ] || '' );
		if ( tab === TAB_COMPARE ) {
			openCountryTab( TAB_COMPARE );
		}
	}

	$( document ).on( 'click', '[data-wsp-country-tab]', function ( ev ) {
		var tabId = resolveTabId( $( this ).attr( 'data-wsp-country-tab' ) || '' );
		if ( ! tabId ) {
			return;
		}
		ev.preventDefault();
		if ( openCountryTab( tabId ) ) {
			var base = window.location.pathname + window.location.search;
			if ( window.history && window.history.replaceState ) {
				window.history.replaceState( null, '', base + '#' + tabId );
			} else {
				window.location.hash = tabId;
			}
		}
	} );

	$( document ).ready( function () {
		if ( ! getMainTabs().length ) {
			return;
		}
		openFromHash();
		$( window ).on( 'hashchange', openFromHash );
	} );
}( jQuery ) );
