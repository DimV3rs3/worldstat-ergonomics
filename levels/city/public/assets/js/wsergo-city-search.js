/**
 * Searchable city dropdown (combobox) for cross-country comparison.
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

	function norm( s ) {
		return decodeHtml( s )
			.toLowerCase()
			.replace( /\s+/g, ' ' )
			.trim();
	}

	function cityLabel( c ) {
		var n = decodeHtml( c.name || '' );
		if ( c.country_name ) {
			n += n ? ' (' + decodeHtml( c.country_name ) + ')' : decodeHtml( c.country_name );
		}
		return n;
	}

	function buildEntries( index ) {
		return Object.keys( index || {} )
			.map( function ( id ) {
				var c = index[ id ];
				if ( ! c ) {
					return null;
				}
				return {
					id: String( id ),
					label: cityLabel( c ),
					search: norm(
						( c.name || '' ) + ' ' + ( c.country_name || '' ) + ' ' + ( c.country_iso2 || '' )
					),
				};
			} )
			.filter( Boolean )
			.sort( function ( a, b ) {
				return a.label.localeCompare( b.label, 'ru' );
			} );
	}

	/**
	 * @param {HTMLElement} root
	 * @param {Object<string,object>} index
	 * @param {{placeholder?:string,selectLabel?:string,emptyLabel?:string,allowEmpty?:boolean,onChange?:Function}} opts
	 */
	function initCitySearch( root, index, opts ) {
		if ( ! root ) {
			return;
		}

		if ( typeof root._wsergoSearchTeardown === 'function' ) {
			root._wsergoSearchTeardown();
		}

		opts = opts || {};
		index = index || {};

		var trigger = root.querySelector( '.wsergo-city-select__trigger' );
		var labelEl = root.querySelector( '.wsergo-city-select__label' );
		var panel = root.querySelector( '.wsergo-city-select__panel' );
		var searchInput = root.querySelector( '.wsergo-city-select__search' );
		var list = root.querySelector( '.wsergo-city-select__list' );
		var hidden = root.querySelector( '.wsergo-city-search__id' );

		if ( ! trigger || ! labelEl || ! panel || ! searchInput || ! list || ! hidden ) {
			return;
		}

		var defaultLabel = opts.selectLabel || opts.placeholder || '—';
		var entries = null;
		var destroyed = false;

		function getEntries() {
			if ( ! entries ) {
				entries = buildEntries( index );
			}
			return entries;
		}

		function refreshEntries() {
			entries = buildEntries( index );
		}

		function closePanel() {
			panel.hidden = true;
			root.classList.remove( 'is-open' );
			trigger.setAttribute( 'aria-expanded', 'false' );
		}

		function openPanel() {
			panel.hidden = false;
			root.classList.add( 'is-open' );
			trigger.setAttribute( 'aria-expanded', 'true' );
			searchInput.value = '';
			renderList( '' );
			setTimeout( function () {
				if ( ! destroyed ) {
					searchInput.focus();
				}
			}, 0 );
		}

		function pick( id, label, silent ) {
			hidden.value = id || '';
			labelEl.textContent = label || defaultLabel;
			closePanel();
			if ( ! silent && typeof opts.onChange === 'function' ) {
				opts.onChange( id );
			}
		}

		function renderList( q ) {
			var nq = norm( q );
			var all = getEntries();
			var items = all;
			if ( nq ) {
				items = all.filter( function ( e ) {
					return e.search.indexOf( nq ) >= 0;
				} );
			}
			var max = 80;
			if ( items.length > max ) {
				items = items.slice( 0, max );
			}

			list.innerHTML = '';
			if ( opts.allowEmpty ) {
				var emptyLi = document.createElement( 'li' );
				emptyLi.className = 'wsergo-city-select__option wsergo-city-select__option--empty';
				emptyLi.setAttribute( 'role', 'option' );
				emptyLi.textContent = opts.emptyLabel || '—';
				emptyLi.addEventListener( 'mousedown', function ( ev ) {
					ev.preventDefault();
					pick( '', opts.emptyLabel || defaultLabel );
				} );
				list.appendChild( emptyLi );
			}
			if ( ! items.length ) {
				var none = document.createElement( 'li' );
				none.className = 'wsergo-city-select__option wsergo-city-select__option--none';
				none.textContent = opts.noResults || 'Ничего не найдено';
				list.appendChild( none );
			} else {
				items.forEach( function ( e ) {
					var li = document.createElement( 'li' );
					li.className = 'wsergo-city-select__option';
					li.setAttribute( 'role', 'option' );
					if ( hidden.value === e.id ) {
						li.classList.add( 'is-selected' );
					}
					li.textContent = e.label;
					li.addEventListener( 'mousedown', function ( ev ) {
						ev.preventDefault();
						pick( e.id, e.label );
					} );
					list.appendChild( li );
				} );
			}
		}

		function onTriggerClick( ev ) {
			ev.preventDefault();
			ev.stopPropagation();
			if ( root.classList.contains( 'is-open' ) ) {
				closePanel();
			} else {
				openPanel();
			}
		}

		function onDocClick( ev ) {
			if ( ! root.contains( ev.target ) ) {
				closePanel();
			}
		}

		function onSearchInput() {
			renderList( searchInput.value );
		}

		function onSearchKeydown( ev ) {
			if ( ev.key === 'Escape' ) {
				closePanel();
				trigger.focus();
			}
		}

		searchInput.placeholder = opts.placeholder || searchInput.placeholder || '';
		searchInput.setAttribute( 'autocomplete', 'off' );
		labelEl.textContent = defaultLabel;

		trigger.addEventListener( 'click', onTriggerClick );
		searchInput.addEventListener( 'input', onSearchInput );
		searchInput.addEventListener( 'keydown', onSearchKeydown );
		document.addEventListener( 'click', onDocClick );

		root._wsergoSearchTeardown = function () {
			destroyed = true;
			trigger.removeEventListener( 'click', onTriggerClick );
			searchInput.removeEventListener( 'input', onSearchInput );
			searchInput.removeEventListener( 'keydown', onSearchKeydown );
			document.removeEventListener( 'click', onDocClick );
			closePanel();
			delete root._wsergoSearchTeardown;
			delete root.wsergoSetCity;
			delete root.wsergoClearCity;
			delete root.wsergoUpdateCityIndex;
		};

		root.wsergoUpdateCityIndex = function ( newIndex ) {
			index = newIndex || {};
			refreshEntries();
		};

		root.wsergoSetCity = function ( id, silent ) {
			if ( ! id || ! index[ id ] ) {
				pick( '', opts.allowEmpty ? opts.emptyLabel || defaultLabel : defaultLabel, silent );
				return;
			}
			pick( String( id ), cityLabel( index[ id ] ), silent );
		};

		root.wsergoClearCity = function () {
			pick( '', opts.emptyLabel || defaultLabel );
		};
	}

	window.wsergoInitCitySearch = initCitySearch;
	window.wsergoDestroyCitySearch = function ( root ) {
		if ( root && typeof root._wsergoSearchTeardown === 'function' ) {
			root._wsergoSearchTeardown();
		}
	};
} )( window );
