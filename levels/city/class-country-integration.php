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
		$iso2           = strtoupper( sanitize_text_field( $country_code ) );
		$country_active = self::is_level_active( 'country' );
		$city_active    = self::is_level_active( 'city' );

		if ( ! $country_active && $city_active ) {
			self::render_cities_only_tab( $iso2 );
			return;
		}

		if ( ! $country_active || ! class_exists( 'WSErgo_Country_Renderer' ) ) {
			echo '<p class="wsp-muted">' . esc_html__( 'Модуль эргономичности недоступен.', 'worldstat-ergonomics' ) . '</p>';
			return;
		}

		if ( $city_active ) {
			ob_start();
			WSErgo_Country_Renderer::render_country_tab( $country_code );
			$html = (string) ob_get_clean();
			$html = self::inject_city_explorer_into_html( $html, $iso2 );
			$html = self::strip_city_macro_subtab( $html );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $html;
			return;
		}

		WSErgo_Country_Renderer::render_country_tab( $country_code );
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
	 * Уровень country|city загружен через WSErgo_Level_Registry.
	 */
	public static function is_level_active( string $level_id ): bool {
		if ( ! class_exists( 'WSErgo_Level_Registry' ) ) {
			return false;
		}
		return null !== WSErgo_Level_Registry::get_manifest( sanitize_key( $level_id ) );
	}


	/**
	 * Только city-уровень: вкладка «Города» с тем же блоком, что встраивается в country.
	 */
	private static function render_cities_only_tab( string $iso2 ): void {
		$cities = self::get_cities_for_country( $iso2 );
		if ( ! class_exists( 'WSErgo_City_Explorer' ) || empty( $cities ) ) {
			echo '<p class="wsp-muted">' . esc_html__( 'Нет городов в базе для этой страны.', 'worldstat-ergonomics' ) . '</p>';
			return;
		}

		WSErgo_City_Explorer::enqueue_explorer_assets( true );

		$tab_root = 'wsergo-city-ergo-scope-' . ( function_exists( 'wp_unique_id' ) ? wp_unique_id() : uniqid( '', true ) );
		?>
		<div class="wsergo-city-ergo-scope wsergo-city-ergo-scope--cities-only" id="<?php echo esc_attr( $tab_root ); ?>">
			<div class="wsergo-wsp-tab-nav" role="tablist" aria-label="<?php esc_attr_e( 'Эргономичность городов', 'worldstat-ergonomics' ); ?>">
				<button type="button" class="wsergo-wsp-tab-btn is-active" role="tab" aria-selected="true" data-wsergo-city-ergo-scope="cities"><?php esc_html_e( 'Города', 'worldstat-ergonomics' ); ?></button>
			</div>
			<div class="wsergo-wsp-tab-panel is-active wsergo-city-ergo-pane" data-wsergo-city-ergo-pane="cities">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo WSErgo_City_Explorer::capture_render_block( $iso2, $cities );
				self::render_cities_summary_table( $iso2, $cities );
				?>
			</div>
		</div>
		<script>
		(function(){
			if (window.wsergoScanCityExplorers) {
				var root = document.getElementById(<?php echo wp_json_encode( $tab_root ); ?>);
				if (root) window.wsergoScanCityExplorers(root);
			}
		})();
		</script>
		<?php
	}


	/**
	 * Таблица городов (когда country-уровень не подключён).
	 *
	 * @param string              $iso2
	 * @param array<int, array>   $cities
	 */
	private static function render_cities_summary_table( string $iso2, array $cities ): void {
		unset( $iso2 );
		if ( ! class_exists( 'WorldStat_UI' ) || empty( $cities ) ) {
			return;
		}

		$rows         = [];
		$any_quarters = false;
		foreach ( $cities as $c ) {
			$cid = (int) ( $c['id'] ?? 0 );
			if ( $cid <= 0 ) {
				continue;
			}
			$city_idx = class_exists( 'WSErgo_City_Data' ) ? WSErgo_City_Data::get_city_ergo_index( $cid ) : null;
			$dcount   = class_exists( 'WSErgo_CPT' ) ? count( WSErgo_CPT::get_districts_for_city( $cid ) ) : 0;
			if ( $dcount > 0 ) {
				$any_quarters = true;
			}
			$link = get_permalink( $cid );
			$name = '<a href="' . esc_url( $link ) . '">' . esc_html( (string) ( $c['name'] ?? get_the_title( $cid ) ) ) . '</a>';
			$rows[] = [
				'name'  => $name,
				'e_idx' => $city_idx !== null && $city_idx > 0 ? (string) $city_idx : '—',
				'dc'    => (string) $dcount,
			];
		}

		if ( empty( $rows ) ) {
			return;
		}

		$table_rows = [];
		foreach ( $rows as $r ) {
			if ( $any_quarters ) {
				$table_rows[] = [ $r['name'], $r['e_idx'], $r['dc'] ];
			} else {
				$table_rows[] = [ $r['name'], $r['e_idx'] ];
			}
		}

		$headers = [
			__( 'Город', 'worldstat-ergonomics' ),
			__( 'Индекс E', 'worldstat-ergonomics' ),
		];
		if ( $any_quarters ) {
			$headers[] = __( 'Кварталов', 'worldstat-ergonomics' );
		}

		WorldStat_UI::table(
			[
				'headers'    => $headers,
				'rows'       => $table_rows,
				'sortable'   => true,
				'searchable' => true,
				'allow_html' => true,
			]
		);
	}


	/**
	 * Убрать подвкладку «Город» (макро city) из HTML country, если city уже встроен в «Страна».
	 */
	private static function strip_city_macro_subtab( string $html ): string {
		$html = (string) preg_replace(
			'/<button\b[^>]*\bdata-wsergo-ergo-country-scope="city"[^>]*>.*?<\/button>\s*/is',
			'',
			$html,
			1
		);

		$html = (string) preg_replace(
			'/<div class="wsergo-wsp-tab-panel wsergo-ergo-country-pane" data-wsergo-ergo-country-pane="city"[^>]*>\s*<div class="wsergo-city-macro-lazy"[\s\S]*?<\/p>\s*<\/div>\s*/',
			'',
			$html,
			1
		);

		if ( str_contains( $html, 'wsergo-ergo-country-macro-scope' ) ) {
			$html = (string) preg_replace(
				'/(<div class="wsergo-ergo-country-macro-scope)(?![^"]*wsergo-ergo-country-macro-scope--country-only)/',
				'$1 wsergo-ergo-country-macro-scope--country-only',
				$html,
				1
			);
		}

		// Сброс сохранённой подвкладки «city» в inline-скрипте country.
		$html = (string) preg_replace(
			"/if \(saved === 'city'\) applyScope\('city'\);/",
			"if (saved === 'city') applyScope('country');",
			$html
		);

		return $html;
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

		$pattern_full = '/<section class="wsp-section wsergo-city-leaf-explorer(?! wsergo-city-leaf-explorer--lazy)[^"]*"[^>]*>.*?<\/section>/s';
		if ( preg_match( $pattern_full, $html ) ) {
			return (string) preg_replace( $pattern_full, $explorer_html, $html, 1 );
		}

		$lazy_msg = esc_html__( 'Блок «Анализ города» загрузится при прокрутке…', 'worldstat-ergonomics' );
		$pattern_lazy = '/<section class="wsp-section wsergo-city-leaf-explorer wsergo-city-leaf-explorer--lazy"[^>]*>.*?<\/section>/s';
		if ( preg_match( $pattern_lazy, $html, $m ) ) {
			$lazy_replacement = self::build_lazy_explorer_placeholder( $m[0], $lazy_msg );
			return (string) preg_replace( $pattern_lazy, $lazy_replacement, $html, 1 );
		}

		return $html;
	}


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
