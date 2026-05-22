<?php
/**
 * Общая оболочка страницы настроек: вкладки уровней из levels/{id}/admin/panel.php.
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_Admin_Shell {

	public const PAGE_SLUG = 'wsergo-settings';

	/**
	 * Верхняя навигация по зарегистрированным уровням с admin/panel.php.
	 */
	public static function render_scope_nav(): void {
		if ( ! class_exists( 'WSErgo_Level_Registry' ) ) {
			return;
		}
		$levels = WSErgo_Level_Registry::ids_with_admin_panel();
		if ( empty( $levels ) ) {
			return;
		}
		?>
		<h2 class="nav-tab-wrapper wsergo-ergo-scope-nav" style="margin-bottom:4px;">
			<?php
			foreach ( $levels as $i => $level_id ) :
				$active = 0 === $i ? ' nav-tab-active' : '';
				$href   = '#ergo-' . $level_id;
				?>
				<a href="<?php echo esc_attr( $href ); ?>" class="nav-tab<?php echo esc_attr( $active ); ?>" data-wsergo-scope="<?php echo esc_attr( $level_id ); ?>">
					<?php echo esc_html( WSErgo_Level_Registry::get_admin_nav_label( $level_id ) ); ?>
				</a>
			<?php endforeach; ?>
		</h2>
		<?php
	}

	/**
	 * Подключить панели всех уровней с admin/panel.php.
	 *
	 * @param array<string, mixed> $vars Переменные для extract в panel.php.
	 * @return bool true, если все панели загружены.
	 */
	public static function render_level_panels( array $vars = [] ): bool {
		if ( ! class_exists( 'WSErgo_Level_Registry' ) ) {
			return false;
		}
		$ok     = true;
		$levels = WSErgo_Level_Registry::ids_with_admin_panel();
		foreach ( $levels as $i => $level_id ) {
			$panel_vars = array_merge(
				$vars,
				[
					'wsergo_scope_hidden' => $i > 0,
				]
			);
			if ( ! WSErgo_Level_Registry::include_admin_panel( $level_id, $panel_vars ) ) {
				$ok = false;
				?>
				<div class="notice notice-error inline" style="margin:12px 0;padding:12px;">
					<p style="margin:0;">
						<?php
						printf(
							/* translators: %s: level id */
							esc_html__( 'Не удалось загрузить панель уровня «%s» (файл admin/panel.php).', 'worldstat-ergonomics' ),
							esc_html( $level_id )
						);
						?>
					</p>
				</div>
				<?php
			}
		}
		if ( ! $ok && ! empty( $levels ) ) {
			echo '<p class="description">' . esc_html__( 'Проверьте, что плагин установлен полностью и каталог levels/ не повреждён.', 'worldstat-ergonomics' ) . '</p>';
		}
		return $ok;
	}

	/**
	 * Inline-скрипт вкладок (не зависит от внешнего admin-settings.js).
	 */
	public static function render_settings_tabs_script(): void {
		?>
		<script id="wsergo-settings-tabs-inline">
		(function () {
			'use strict';

			function qs( sel, root ) {
				return ( root || document ).querySelector( sel );
			}
			function qsa( sel, root ) {
				return Array.prototype.slice.call( ( root || document ).querySelectorAll( sel ) );
			}

			function setVisible( el, on ) {
				if ( ! el ) {
					return;
				}
				el.style.setProperty( 'display', on ? 'block' : 'none', 'important' );
			}

			function setScope( scope ) {
				if ( ! scope ) {
					var first = qs( '.wsergo-ergo-scope-nav a[data-wsergo-scope]' );
					scope = first ? ( first.getAttribute( 'data-wsergo-scope' ) || 'country' ) : 'country';
				}
				qsa( '.wsergo-ergo-scope-nav a[data-wsergo-scope]' ).forEach( function ( a ) {
					a.classList.toggle( 'nav-tab-active', a.getAttribute( 'data-wsergo-scope' ) === scope );
				} );
				qsa( '.wsergo-scope-panel' ).forEach( function ( p ) {
					p.classList.remove( 'wsergo-scope-panel--active' );
					setVisible( p, false );
				} );
				var panel = document.getElementById( 'wsergo-panel-' + scope );
				if ( panel ) {
					panel.classList.add( 'wsergo-scope-panel--active' );
					setVisible( panel, true );
				}
				if ( typeof jQuery !== 'undefined' ) {
					jQuery( document ).trigger( 'wsergo-scope-activated', [ scope ] );
				}
				return scope;
			}

			function setCountryTab( id ) {
				if ( ! id ) {
					id = 'tab-data';
				}
				setScope( 'country' );
				var root = document.getElementById( 'wsergo-panel-country' );
				if ( ! root ) {
					return;
				}
				qsa( '.wsergo-country-inner-nav a[data-tab]', root ).forEach( function ( a ) {
					a.classList.toggle( 'nav-tab-active', a.getAttribute( 'data-tab' ) === id );
				} );
				var form = qs( 'form.wsergo-settings-form', root );
				if ( form ) {
					qsa( '.wsergo-tab-panel', form ).forEach( function ( el ) {
						setVisible( el, false );
					} );
					var tab = document.getElementById( id );
					if ( tab && form.contains( tab ) ) {
						setVisible( tab, true );
					}
				}
			}

			function setCityTab( id ) {
				if ( ! id ) {
					id = 'city-tab-overview';
				}
				setScope( 'city' );
				var root = document.getElementById( 'wsergo-panel-city' );
				if ( ! root ) {
					return;
				}
				qsa( '.wsergo-city-inner-nav a[data-city-tab]', root ).forEach( function ( a ) {
					a.classList.toggle( 'nav-tab-active', a.getAttribute( 'data-city-tab' ) === id );
				} );
				var form = qs( 'form.wsergo-settings-form', root );
				if ( form ) {
					qsa( '.wsergo-city-tab-panel', form ).forEach( function ( el ) {
						setVisible( el, false );
					} );
					var tab = document.getElementById( id );
					if ( tab && form.contains( tab ) ) {
						setVisible( tab, true );
					}
				}
			}

			function replaceHash( hash ) {
				if ( window.history && window.history.replaceState ) {
					window.history.replaceState( null, '', window.location.pathname + window.location.search + hash );
				}
			}

			function applyHash() {
				var h = window.location.hash || '';
				var matched = false;
				qsa( '.wsergo-ergo-scope-nav a[data-wsergo-scope]' ).forEach( function ( a ) {
					var sc = a.getAttribute( 'data-wsergo-scope' ) || '';
					if ( h === '#ergo-' + sc ) {
						matched = true;
						setScope( sc );
						if ( sc === 'country' ) {
							setCountryTab( 'tab-data' );
						} else if ( sc === 'city' ) {
							setCityTab( 'city-tab-overview' );
						}
					}
				} );
				if ( matched ) {
					return;
				}
				if ( h.indexOf( '#city-tab-' ) === 0 ) {
					setCityTab( h.slice( 1 ) );
					return;
				}
				if ( h.indexOf( '#tab-territory-' ) === 0 ) {
					setScope( 'territory' );
					return;
				}
				if ( h.indexOf( '#tab-city-' ) === 0 ) {
					var legacy = h.slice( 1 ).replace( /^tab-city-/, 'city-tab-' );
					setCityTab( legacy );
					return;
				}
				if ( h === '#tab-data' || h === '#tab-formula' ) {
					setCountryTab( h.slice( 1 ) );
					return;
				}
				setScope( 'country' );
				setCountryTab( 'tab-data' );
			}

			function onClick( e ) {
				var scopeLink = e.target.closest( '.wsergo-ergo-scope-nav a[data-wsergo-scope]' );
				if ( scopeLink ) {
					e.preventDefault();
					e.stopPropagation();
					e.stopImmediatePropagation();
					var scope = scopeLink.getAttribute( 'data-wsergo-scope' ) || '';
					setScope( scope );
					replaceHash( '#ergo-' + scope );
					if ( scope === 'country' ) {
						setCountryTab( 'tab-data' );
					} else if ( scope === 'city' ) {
						setCityTab( 'city-tab-overview' );
					}
					return;
				}
				var countryTab = e.target.closest( '.wsergo-country-inner-nav a[data-tab]' );
				if ( countryTab ) {
					e.preventDefault();
					e.stopPropagation();
					e.stopImmediatePropagation();
					var tabId = countryTab.getAttribute( 'data-tab' ) || '';
					setCountryTab( tabId );
					replaceHash( '#' + tabId );
					return;
				}
				var cityTab = e.target.closest( '.wsergo-city-inner-nav a[data-city-tab]' );
				if ( cityTab ) {
					e.preventDefault();
					e.stopPropagation();
					e.stopImmediatePropagation();
					var cityId = cityTab.getAttribute( 'data-city-tab' ) || '';
					setCityTab( cityId );
					replaceHash( '#' + cityId );
					return;
				}
				var deep = e.target.closest( 'a.wsergo-tab-deep-link[href^="#tab-"]' );
				if ( deep ) {
					var href = deep.getAttribute( 'href' ) || '';
					if ( href.indexOf( '#tab-territory-' ) === 0 ) {
						e.preventDefault();
						e.stopPropagation();
						e.stopImmediatePropagation();
						setScope( 'territory' );
						replaceHash( href );
						return;
					}
					if ( href === '#tab-data' || href === '#tab-formula' ) {
						e.preventDefault();
						e.stopPropagation();
						e.stopImmediatePropagation();
						setCountryTab( href.slice( 1 ) );
						replaceHash( href );
					}
				}
			}

			function init() {
				if ( ! qsa( '.wsergo-scope-panel' ).length ) {
					return;
				}
				document.addEventListener( 'click', onClick, true );
				applyHash();
				window.addEventListener( 'hashchange', applyHash );
				window.wsergoApplyHash = applyHash;
				window.wsergoActivateScope = setScope;
				window.wsergoActivateInnerTab = setCountryTab;
				window.wsergoActivateCityInnerTab = setCityTab;
			}

			document.addEventListener( 'DOMContentLoaded', init );
		})();
		</script>
		<?php
	}
}
