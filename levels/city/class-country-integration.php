<?php
/**
 * Встраивание блока «Анализ города» на вкладку эргономичности страны — без правок levels/country/.
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_City_Country_Integration {

	/** @var bool */
	private static $ajax_hooks_swapped = false;


	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'swap_country_explorer_ajax' ], 100 );
	}


	/**
	 * Точка входа вкладки «Эргономичность» страны (вместо прямого вызова country renderer).
	 */
	public static function render_country_tab( string $country_code ): void {
		$iso2 = strtoupper( sanitize_text_field( $country_code ) );

		if ( self::country_has_macro_dual_scope() ) {
			ob_start();
			WSErgo_Country_Renderer::render_country_tab( $country_code );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML страны с подменой city-explorer.
			echo self::inject_city_explorer_into_html( (string) ob_get_clean(), $iso2 );
			return;
		}

		self::render_standalone_country_city_tabs( $iso2, $country_code );
	}


	/**
	 * AJAX lazy-load исследователя (перехват с country bootstrap).
	 */
	public static function ajax_load_country_city_explorer(): void {
		check_ajax_referer( 'wsergo_country_tab', 'nonce' );

		$iso2 = strtoupper( sanitize_text_field( wp_unslash( $_POST['iso2'] ?? '' ) ) );
		if ( strlen( $iso2 ) !== 2 ) {
			wp_send_json_error( [ 'message' => 'invalid' ] );
		}

		$html = self::build_city_explorer_html( $iso2 );
		wp_send_json_success( [ 'html' => $html ] );
	}


	public static function swap_country_explorer_ajax(): void {
		if ( self::$ajax_hooks_swapped ) {
			return;
		}
		self::$ajax_hooks_swapped = true;

		remove_action( 'wp_ajax_wsergo_load_country_city_explorer', [ 'WSErgo_Country_Renderer', 'ajax_load_country_city_explorer' ] );
		remove_action( 'wp_ajax_nopriv_wsergo_load_country_city_explorer', [ 'WSErgo_Country_Renderer', 'ajax_load_country_city_explorer' ] );
		add_action( 'wp_ajax_wsergo_load_country_city_explorer', [ __CLASS__, 'ajax_load_country_city_explorer' ] );
		add_action( 'wp_ajax_nopriv_wsergo_load_country_city_explorer', [ __CLASS__, 'ajax_load_country_city_explorer' ] );
	}


	/**
	 * На странице страны есть подвкладки «Страна / Город» (макро CSV).
	 */
	public static function country_has_macro_dual_scope(): bool {
		$index_source = class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::get_country_index_source() : 'macro_datasets';
		return 'macro_datasets' === $index_source && class_exists( 'WSErgo_Country_Macro_Calculator' );
	}


	/**
	 * Fallback: собственные подвкладки «Страна / Город», если макро-блок country отсутствует.
	 */
	private static function render_standalone_country_city_tabs( string $iso2, string $country_code ): void {
		ob_start();
		WSErgo_Country_Renderer::render_country_tab( $country_code );
		$country_html = self::strip_explorer_sections( (string) ob_get_clean() );

		$cities = self::get_cities_for_country( $iso2 );
		if ( ! class_exists( 'WSErgo_City_Explorer' ) || empty( $cities ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $country_html;
			return;
		}

		WSErgo_City_Explorer::enqueue_explorer_assets( true );

		$tab_root = 'wsergo-city-ergo-scope-' . ( function_exists( 'wp_unique_id' ) ? wp_unique_id() : uniqid( '', true ) );
		?>
		<div class="wsergo-city-ergo-scope" id="<?php echo esc_attr( $tab_root ); ?>">
			<div class="wsergo-wsp-tab-nav" role="tablist" aria-label="<?php esc_attr_e( 'Эргономичность: страна и город', 'worldstat-ergonomics' ); ?>">
				<button type="button" class="wsergo-wsp-tab-btn is-active" role="tab" aria-selected="true" data-wsergo-city-ergo-scope="country"><?php esc_html_e( 'Страна', 'worldstat-ergonomics' ); ?></button>
				<button type="button" class="wsergo-wsp-tab-btn" role="tab" aria-selected="false" data-wsergo-city-ergo-scope="city"><?php esc_html_e( 'Город', 'worldstat-ergonomics' ); ?></button>
			</div>
			<div class="wsergo-wsp-tab-panel is-active wsergo-city-ergo-pane" data-wsergo-city-ergo-pane="country">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML country renderer без legacy explorer.
				echo $country_html;
				?>
			</div>
			<div class="wsergo-wsp-tab-panel wsergo-city-ergo-pane" data-wsergo-city-ergo-pane="city">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo WSErgo_City_Explorer::capture_render_block( $iso2, $cities );
				?>
			</div>
		</div>
		<script>
		(function(){
			var root = document.getElementById(<?php echo wp_json_encode( $tab_root ); ?>);
			if (!root) return;
			var key = 'wsergo_city_ergo_tab_scope';
			function applyScope(mode) {
				root.querySelectorAll('[data-wsergo-city-ergo-scope]').forEach(function(btn){
					var on = btn.getAttribute('data-wsergo-city-ergo-scope') === mode;
					btn.classList.toggle('is-active', on);
					btn.setAttribute('aria-selected', on ? 'true' : 'false');
				});
				root.querySelectorAll('.wsergo-city-ergo-pane').forEach(function(pane){
					var on = pane.getAttribute('data-wsergo-city-ergo-pane') === mode;
					pane.classList.toggle('is-active', on);
				});
				try { localStorage.setItem(key, mode); } catch (e) {}
				if (mode === 'city' && window.wsergoScanCityExplorers) {
					window.wsergoScanCityExplorers(root);
				}
			}
			root.querySelectorAll('[data-wsergo-city-ergo-scope]').forEach(function(btn){
				btn.addEventListener('click', function(){
					applyScope(btn.getAttribute('data-wsergo-city-ergo-scope'));
				});
			});
			var saved = '';
			try { saved = localStorage.getItem(key) || ''; } catch (e) {}
			if (saved === 'city') applyScope('city');
		})();
		</script>
		<?php
	}


	/**
	 * Подмена legacy wsergo-city-leaf-explorer на WSErgo_City_Explorer в HTML country.
	 */
	private static function inject_city_explorer_into_html( string $html, string $iso2 ): string {
		if ( ! class_exists( 'WSErgo_City_Explorer' ) ) {
			return $html;
		}

		$cities = self::get_cities_for_country( $iso2 );
		if ( empty( $cities ) ) {
			return $html;
		}

		WSErgo_City_Explorer::enqueue_explorer_assets( true );

		$explorer_html = WSErgo_City_Explorer::capture_render_block( $iso2, $cities );

		// Немедленный legacy-блок.
		$pattern_full = '/<section class="wsp-section wsergo-city-leaf-explorer(?! wsergo-city-leaf-explorer--lazy)[^"]*"[^>]*>.*?<\/section>/s';
		if ( preg_match( $pattern_full, $html ) ) {
			return (string) preg_replace( $pattern_full, $explorer_html, $html, 1 );
		}

		// Lazy placeholder (совместим с JS country: wsergo-city-leaf-explorer__lazy-status).
		$lazy_msg = esc_html__( 'Блок «Анализ города» загрузится при прокрутке…', 'worldstat-ergonomics' );
		$pattern_lazy = '/<section class="wsp-section wsergo-city-leaf-explorer wsergo-city-leaf-explorer--lazy"[^>]*>.*?<\/section>/s';
		if ( preg_match( $pattern_lazy, $html, $m ) ) {
			$lazy_replacement = self::build_lazy_explorer_placeholder( $m[0], $lazy_msg );
			return (string) preg_replace( $pattern_lazy, $lazy_replacement, $html, 1 );
		}

		return $html;
	}


	/**
	 * Сохраняет id/data-iso2 lazy-секции, меняет только текст статуса.
	 */
	private static function build_lazy_explorer_placeholder( string $original_section, string $status_text ): string {
		$id   = '';
		$iso2 = '';
		if ( preg_match( '/\sid="([^"]+)"/', $original_section, $id_m ) ) {
			$id = $id_m[1];
		}
		if ( preg_match( '/data-iso2="([^"]+)"/', $original_section, $iso_m ) ) {
			$iso2 = $iso_m[1];
		}

		$attrs = ' class="wsp-section wsergo-city-leaf-explorer wsergo-city-leaf-explorer--lazy" data-wsergo-explorer-lazy="1"';
		if ( $id !== '' ) {
			$attrs .= ' id="' . esc_attr( $id ) . '"';
		}
		if ( $iso2 !== '' ) {
			$attrs .= ' data-iso2="' . esc_attr( $iso2 ) . '"';
		}

		return '<section' . $attrs . '><p class="wsp-muted wsergo-city-leaf-explorer__lazy-status">' . $status_text . '</p></section>';
	}


	private static function strip_explorer_sections( string $html ): string {
		$patterns = [
			'/<section class="wsp-section wsergo-city-leaf-explorer[^"]*"[^>]*>.*?<\/section>/s',
			'/<section class="wsp-section wsergo-city-explorer-lazy"[^>]*>.*?<\/section>/s',
		];
		foreach ( $patterns as $pattern ) {
			$html = (string) preg_replace( $pattern, '', $html );
		}
		return $html;
	}


	private static function build_city_explorer_html( string $iso2 ): string {
		if ( ! class_exists( 'WSErgo_City_Explorer' ) ) {
			return '';
		}
		$cities = self::get_cities_for_country( $iso2 );
		if ( empty( $cities ) ) {
			return '';
		}
		WSErgo_City_Explorer::enqueue_explorer_assets( true );
		return WSErgo_City_Explorer::capture_render_block( $iso2, $cities );
	}


	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_cities_for_country( string $iso2 ): array {
		if ( ! class_exists( 'WSCities_CPT' ) || ! method_exists( 'WSCities_CPT', 'get_cities_for_country' ) ) {
			return [];
		}
		$cities = WSCities_CPT::get_cities_for_country( $iso2 );
		return is_array( $cities ) ? $cities : [];
	}
}
