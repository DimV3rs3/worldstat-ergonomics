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

	function norm( s ) {
		return String( s || '' )
			.toLowerCase()
			.replace( /\s+/g, ' ' )
			.trim();
	}

	function cityLabel( c ) {
		var n = c.name || '';
		if ( c.country_name ) {
			n += n ? ' (' + c.country_name + ')' : String( c.country_name );
		}
		return n;
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
		opts = opts || {};

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
		var entries = Object.keys( index || {} )
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
				searchInput.focus();
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
			var items = entries;
			if ( nq ) {
				items = entries.filter( function ( e ) {
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
					li.innerHTML = esc( e.label );
					li.addEventListener( 'mousedown', function ( ev ) {
						ev.preventDefault();
						pick( e.id, e.label );
					} );
					list.appendChild( li );
				} );
			}
		}

		searchInput.placeholder = opts.placeholder || searchInput.placeholder || '';
		searchInput.setAttribute( 'autocomplete', 'off' );
		labelEl.textContent = defaultLabel;

		trigger.addEventListener( 'click', function ( ev ) {
			ev.preventDefault();
			if ( root.classList.contains( 'is-open' ) ) {
				closePanel();
			} else {
				openPanel();
			}
		} );

		searchInput.addEventListener( 'input', function () {
			renderList( searchInput.value );
		} );
		searchInput.addEventListener( 'keydown', function ( ev ) {
			if ( ev.key === 'Escape' ) {
				closePanel();
				trigger.focus();
			}
		} );

		document.addEventListener( 'click', function ( ev ) {
			if ( ! root.contains( ev.target ) ) {
				closePanel();
			}
		} );

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
} )( window );
