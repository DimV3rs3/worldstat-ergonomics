<?php
/**
 * Сравнение стран: классификация по осям эргономичности и регрессия показателей по годам.
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_Country_Explorer {

	/** @var bool */
	private static $classification_assets_enqueued = false;

	/**
	 * Классификация по осям (плагин эргономичности и классификатор).
	 */
	public static function is_classification_available(): bool {
		return function_exists( 'wsergo_classification_is_available' ) && wsergo_classification_is_available();
	}

	/**
	 * Текст при недоступной классификации.
	 */
	public static function classification_unavailable_message(): string {
		if ( function_exists( 'wsergo_classification_unavailable_message' ) ) {
			return wsergo_classification_unavailable_message();
		}
		return __( 'Классификация по уровням эргономичности недоступна: необходим плагин WorldStat Ergonomics.', 'worldstat-ergonomics' );
	}

	/**
	 * Блок-предупреждение вместо шкал и таблицы классификации.
	 */
	public static function render_classification_unavailable_notice( string $extra_class = '' ): void {
		$class = 'wsergo-classification-unavailable wsp-ca-notice';
		if ( $extra_class !== '' ) {
			$class .= ' ' . sanitize_html_class( $extra_class );
		}
		echo '<div class="' . esc_attr( $class ) . '" role="status">';
		echo '<p class="wsergo-classification-unavailable__text">' . esc_html( self::classification_unavailable_message() ) . '</p>';
		echo '</div>';
	}

	/**
	 * @return array<string, string>
	 */
	public static function classification_i18n(): array {
		return array(
			'running'       => __( 'Расчёт…', 'worldstat-ergonomics' ),
			'error'         => __( 'Ошибка', 'worldstat-ergonomics' ),
			'networkError'  => __( 'Ошибка сети. Попробуйте снова.', 'worldstat-ergonomics' ),
			'ladderTitle'   => __( 'Шкалы классификации по критериям', 'worldstat-ergonomics' ),
			'ladderHint'    => __( 'Шкала 0–100: цвет точки — уровень по оси; наведите — страна и балл.', 'worldstat-ergonomics' ),
			'ladderHintErgo' => __( 'Шкала 0–100: одна точка на оси — балл этой страны; цвет — уровень по критерию.', 'worldstat-ergonomics' ),
			'scoreLabel'    => __( 'Балл', 'worldstat-ergonomics' ),
			'levelLabel'    => __( 'Уровень', 'worldstat-ergonomics' ),
			'scatterTitle'  => __( 'Положение страны по двум осям', 'worldstat-ergonomics' ),
			'scatterHint'   => __( 'Выберите разные оси. Точки — все страны выборки; выделена страна страницы.', 'worldstat-ergonomics' ),
			'axisX'         => __( 'Ось X', 'worldstat-ergonomics' ),
			'axisY'         => __( 'Ось Y', 'worldstat-ergonomics' ),
			'ergoIndex'     => __( 'Сводный балл', 'worldstat-ergonomics' ),
			'sameAxisError' => __( 'Выберите разные оси.', 'worldstat-ergonomics' ),
			'clsVisTitle'   => __( 'Аналитика классификации', 'worldstat-ergonomics' ),
			'clsErgoTitle'  => __( 'Аналитика классификации страны', 'worldstat-ergonomics' ),
			'showUnselectedOnCharts' => __( 'Показывать невыбранные страны на шкалах и графике', 'worldstat-ergonomics' ),
		);
	}

	/**
	 * Стили и JS шкал/графика классификации (вкладки «Эргономичность» и «Сравнение»).
	 */
	public static function enqueue_classification_assets(): void {
		if ( self::$classification_assets_enqueued ) {
			return;
		}
		self::$classification_assets_enqueued = true;

		$deps_css = array();
		if ( wp_style_is( 'worldstat-platform', 'registered' ) || wp_style_is( 'worldstat-platform', 'enqueued' ) ) {
			$deps_css[] = 'worldstat-platform';
		}
		if ( wp_style_is( 'worldstat-country-analytics', 'registered' ) || wp_style_is( 'worldstat-country-analytics', 'enqueued' ) ) {
			$deps_css[] = 'worldstat-country-analytics';
		}

		$css_rel = 'public/assets/css/ergo-country-public.css';
		if ( ! wp_style_is( 'wsergo-country-public', 'enqueued' ) && ! wp_style_is( 'wsergo-country-public', 'done' ) ) {
			wp_enqueue_style(
				'wsergo-country-public',
				class_exists( 'WSErgo_Level_Registry' ) ? WSErgo_Level_Registry::url( 'country', $css_rel ) : WSERGO_URL . 'levels/country/' . $css_rel,
				$deps_css,
				class_exists( 'WSErgo_Level_Registry' ) ? WSErgo_Level_Registry::asset_version( 'country', $css_rel ) : WSERGO_VERSION
			);
		}

		$ladder_handle = 'wsergo-country-classification-ladder';
		if ( ! wp_script_is( $ladder_handle, 'registered' ) && ! wp_script_is( $ladder_handle, 'enqueued' ) ) {
			wp_register_script(
				$ladder_handle,
				class_exists( 'WSErgo_Level_Registry' )
					? WSErgo_Level_Registry::url( 'country', 'public/assets/js/wsergo-country-classification-ladder.js' )
					: WSERGO_URL . 'levels/country/public/assets/js/wsergo-country-classification-ladder.js',
				array( 'jquery' ),
				class_exists( 'WSErgo_Level_Registry' )
					? WSErgo_Level_Registry::asset_version( 'country', 'public/assets/js/wsergo-country-classification-ladder.js' )
					: WSERGO_VERSION,
				true
			);
		}

		if ( ! wp_script_is( $ladder_handle, 'enqueued' ) ) {
			wp_enqueue_script( $ladder_handle );
		}

		if ( ! wp_script_is( $ladder_handle, 'done' ) ) {
			wp_localize_script(
				$ladder_handle,
				'wsergoCountryClassification',
				array(
					'ajaxUrl'                => admin_url( 'admin-ajax.php' ),
					'nonce'                  => wp_create_nonce( 'wsergo_country_compare' ),
					'classificationAction'   => 'wsergo_country_classification_analysis',
					'i18n'                   => self::classification_i18n(),
				)
			);
		}
	}

	public static function enqueue_assets( int $reference_post_id ): void {
		if ( class_exists( 'WorldStat_UI' ) ) {
			WorldStat_UI::enqueue_chart_scripts();
		}

		if ( wp_style_is( 'worldstat-country-analytics', 'registered' ) || wp_style_is( 'worldstat-country-analytics', 'enqueued' ) ) {
			// already loaded
		} else {
			wp_enqueue_style(
				'worldstat-country-analytics',
				defined( 'WSP_ASSETS_URL' ) ? WSP_ASSETS_URL . 'css/country-analytics.css' : '',
				array(),
				defined( 'WSP_VERSION' ) ? WSP_VERSION : '1'
			);
		}

		self::enqueue_classification_assets();

		$js_deps = array( 'jquery' );
		if ( wp_script_is( 'worldstat-chart-builder', 'registered' ) ) {
			$js_deps[] = 'worldstat-chart-builder';
		}

		wp_enqueue_script(
			'wsergo-country-compare',
			class_exists( 'WSErgo_Level_Registry' )
				? WSErgo_Level_Registry::url( 'country', 'public/assets/js/wsergo-country-compare.js' )
				: WSERGO_URL . 'levels/country/public/assets/js/wsergo-country-compare.js',
			$js_deps,
			class_exists( 'WSErgo_Level_Registry' )
				? WSErgo_Level_Registry::asset_version( 'country', 'public/assets/js/wsergo-country-compare.js' )
				: WSERGO_VERSION,
			true
		);

		$post_id = $reference_post_id;
		if ( $post_id < 1 && class_exists( 'WorldStat_Country_CPT' ) ) {
			$post_id = (int) get_queried_object_id();
		}

		$metrics = class_exists( 'WSErgo_Country_Compare_Trends' )
			? WSErgo_Country_Compare_Trends::get_metric_options_for_post( $post_id )
			: array();

		$compare_i18n = array_merge(
			self::classification_i18n(),
			array(
				'done'              => __( 'Готово', 'worldstat-ergonomics' ),
				'pickMetric'        => __( 'Выберите показатель из CSV страны.', 'worldstat-ergonomics' ),
				'pickCountry'       => __( 'Отметьте страны в таблице.', 'worldstat-ergonomics' ),
				'r2'                => __( 'R²', 'worldstat-ergonomics' ),
				'trend'             => __( 'Тренд', 'worldstat-ergonomics' ),
				'forecast'          => __( 'Прогноз', 'worldstat-ergonomics' ),
				'year'              => __( 'г.', 'worldstat-ergonomics' ),
				'calculate'         => __( 'Построить график', 'worldstat-ergonomics' ),
				'selected'          => __( 'Выбрано стран', 'worldstat-ergonomics' ),
				'viewCombined'      => __( 'Совмещённый', 'worldstat-ergonomics' ),
				'viewSeparate'      => __( 'Отдельные графики', 'worldstat-ergonomics' ),
				'legendTitle'       => __( 'Серии (цвета)', 'worldstat-ergonomics' ),
				'analysisTitle'     => __( 'Аналитический вывод по показателю', 'worldstat-ergonomics' ),
				'ergoAnalysisTitle' => __( 'Классификация эргономичности', 'worldstat-ergonomics' ),
				'statsTitle'        => __( 'Показатели по странам', 'worldstat-ergonomics' ),
				'countryCol'        => __( 'Страна', 'worldstat-ergonomics' ),
				'pctChange'         => __( 'Δ к базе, %', 'worldstat-ergonomics' ),
				'scaleMin'          => '0',
				'scaleMax'          => '100',
				'scatterTitle'      => __( 'Сравнение по двум осям', 'worldstat-ergonomics' ),
				'scatterHint'       => __( 'Выберите разные оси. Точки — страны; наведите — название и сводный балл эргономичности.', 'worldstat-ergonomics' ),
			)
		);

		wp_localize_script(
			'wsergo-country-compare',
			'wsergoCountryCompare',
			array(
				'ajaxUrl'              => admin_url( 'admin-ajax.php' ),
				'nonce'                => wp_create_nonce( 'wsergo_country_compare' ),
				'action'               => 'wsergo_country_compare_trends',
				'classificationAction' => 'wsergo_country_classification_analysis',
				'metrics'              => $metrics,
				'i18n'                 => $compare_i18n,
			)
		);
	}

	/**
	 * Payload для вкладки «Эргономичность»: одна страна, шкалы без 2D-графика.
	 *
	 * @return array<string, mixed>
	 */
	public static function build_ergo_tab_classification_payload( string $iso2 ): array {
		$iso2 = strtoupper( sanitize_text_field( $iso2 ) );
		if ( ! self::is_classification_available() ) {
			return array(
				'highlight_iso2'        => $iso2,
				'ladder_single_country' => true,
				'ladder_chart'          => array(),
				'classification_visual' => array(),
			);
		}

		return array(
			'highlight_iso2'        => $iso2,
			'ladder_single_country' => true,
			'ladder_chart'          => class_exists( 'WSErgo_Tier_Classifier' )
				? WSErgo_Tier_Classifier::build_axis_ladder_chart_payload( $iso2, true )
				: array(),
			'classification_visual' => class_exists( 'WSErgo_Tier_Classifier' )
				? WSErgo_Tier_Classifier::build_classification_visual_analysis( array( $iso2 ), $iso2 )
				: array(),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function build_payload( string $highlight_iso2 ): array {
		$highlight_iso2 = strtoupper( sanitize_text_field( $highlight_iso2 ) );
		if ( ! self::is_classification_available() ) {
			return array(
				'highlight_iso2'        => $highlight_iso2,
				'classification'        => array(),
				'axis_labels'           => array(),
				'axis_keys'             => array(),
				'ladder_chart'          => array(),
				'scatter_chart'         => array(),
				'classification_visual' => array(),
			);
		}
		$classification = WSErgo_Tier_Classifier::get_global_classification_rows();
		$axis_labels = WSErgo_Tier_Classifier::axis_labels_ru();

		$ladder  = WSErgo_Tier_Classifier::build_axis_ladder_chart_payload( $highlight_iso2 );
		$scatter = WSErgo_Tier_Classifier::build_scatter_chart_payload( $highlight_iso2 );
		$cls_vis = WSErgo_Tier_Classifier::build_classification_visual_analysis( array( $highlight_iso2 ), $highlight_iso2 );

		return array(
			'highlight_iso2'        => $highlight_iso2,
			'classification'        => $classification,
			'axis_labels'           => $axis_labels,
			'axis_keys'             => WSErgo_Tier_Classifier::SCORE_KEYS,
			'ladder_chart'          => $ladder,
			'scatter_chart'         => $scatter,
			'classification_visual' => $cls_vis,
		);
	}

	public static function render_block( string $iso2 ): void {
		if ( ! class_exists( 'WSErgo_Country_Macro_Calculator' ) || ! class_exists( 'WSErgo_Settings' ) ) {
			return;
		}
		if ( WSErgo_Settings::get_country_index_source() !== 'macro_datasets' ) {
			return;
		}

		$iso2    = strtoupper( sanitize_text_field( $iso2 ) );
		$post_id = class_exists( 'WorldStat_Country_CPT' )
			? (int) WorldStat_Country_CPT::get_post_id_by_code( $iso2 )
			: (int) get_queried_object_id();

		self::enqueue_assets( $post_id );
		$payload = self::build_payload( $iso2 );
		$uid     = 'wsergo-country-explorer-' . ( function_exists( 'wp_unique_id' ) ? wp_unique_id() : uniqid( '', true ) );

		$metrics = class_exists( 'WSErgo_Country_Compare_Trends' )
			? WSErgo_Country_Compare_Trends::get_metric_options_for_post( $post_id )
			: array();

		echo '<section class="wsp-section wsergo-country-macro-explorer" id="' . esc_attr( $uid ) . '" data-wsergo-country-explorer="1">';
		echo '<h3 class="wsp-section-title">' . esc_html__( 'Сравнение стран', 'worldstat-ergonomics' ) . '</h3>';
		echo '<p class="wsp-muted">' . esc_html__( 'Классификация: по каждой оси — динамическая шкала выборки; итог согласован с уровнями шести критериев и взвешенным баллом (веса E). Профиль — k-means по осям. Регрессия — тренд CSV до 2050 г.', 'worldstat-ergonomics' ) . '</p>';

		self::render_classification_table( $payload, $iso2, $uid );

		echo '<div class="wsergo-country-compare-regression wsp-country-analytics">';
		echo '<h4 class="wsergo-city-explorer__section-title">' . esc_html__( 'Регрессия показателя по годам', 'worldstat-ergonomics' ) . '</h4>';
		echo '<p class="wsp-muted">' . esc_html__( 'Список показателей — из CSV текущей страны (как на вкладке «Обзор»). Отметьте страны в таблице и выберите показатель.', 'worldstat-ergonomics' ) . '</p>';
		echo '<p class="wsergo-country-explorer__selection-status wsp-muted" id="' . esc_attr( $uid ) . '-sel-status" aria-live="polite"></p>';

		echo '<div class="wsp-ca-settings wsergo-country-compare-controls">';
		echo '<div class="wsp-ca-control wsp-ca-control--metric">';
		echo '<label class="wsp-country-analytics__field" for="' . esc_attr( $uid ) . '-metric">' . esc_html__( 'Показатель', 'worldstat-ergonomics' ) . '</label>';
		echo '<select id="' . esc_attr( $uid ) . '-metric" class="wsp-select wsergo-country-compare-metric">';
		echo '<option value="">' . esc_html__( '— выберите показатель —', 'worldstat-ergonomics' ) . '</option>';
		foreach ( $metrics as $m ) {
			$mid = (string) ( $m['id'] ?? '' );
			printf(
				'<option value="%s">%s</option>',
				esc_attr( $mid ),
				esc_html( (string) ( $m['label'] ?? $mid ) )
			);
		}
		echo '</select></div>';
		echo '<div class="wsp-ca-settings__actions">';
		printf(
			'<button type="button" class="wsp-btn wsp-btn-primary wsergo-country-compare-run" data-explorer="%s">%s</button>',
			esc_attr( $uid ),
			esc_html__( 'Построить график', 'worldstat-ergonomics' )
		);
		echo '<span class="wsp-country-analytics__status wsergo-country-compare-status" id="' . esc_attr( $uid ) . '-status" aria-live="polite"></span>';
		echo '</div></div>';

		echo '<div class="wsp-country-analytics__results wsergo-country-compare-results" id="' . esc_attr( $uid ) . '-results" data-view-mode="combined">';
		echo '<p class="wsp-muted wsergo-country-compare-results__placeholder">' . esc_html__( 'После «Построить график» — сравнение трендов до 2050 г. и аналитический вывод по графику (темпы, R², позиция страны страницы).', 'worldstat-ergonomics' ) . '</p>';
		echo '</div></div>';

		$json = wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP );
		if ( false === $json ) {
			$json = '{}';
		}
		echo '<script type="application/json" id="' . esc_attr( $uid ) . '-json">' . $json . '</script>';
		printf(
			'<script>document.addEventListener("DOMContentLoaded",function(){if(window.wsergoCountryCompareInit){window.wsergoCountryCompareInit(document.getElementById(%s));}});if(window.wsergoCountryCompareInit){window.wsergoCountryCompareInit(document.getElementById(%s));}</script>',
			wp_json_encode( $uid ),
			wp_json_encode( $uid )
		);
		echo '</section>';
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private static function render_classification_table( array $payload, string $highlight_iso2, string $uid ): void {
		echo '<div class="wsergo-country-classification-wrap">';
		echo '<h4 class="wsergo-city-explorer__section-title">' . esc_html__( 'Классификация по критериям эргономичности', 'worldstat-ergonomics' ) . '</h4>';

		if ( ! self::is_classification_available() ) {
			self::render_classification_unavailable_notice();
			echo '</div>';
			return;
		}

		$rows = isset( $payload['classification'] ) && is_array( $payload['classification'] ) ? $payload['classification'] : array();
		if ( empty( $rows ) ) {
			echo '<p class="wsp-muted">' . esc_html__( 'Нет данных для классификации стран.', 'worldstat-ergonomics' ) . '</p>';
			echo '</div>';
			return;
		}

		$axis_keys = isset( $payload['axis_keys'] ) && is_array( $payload['axis_keys'] ) ? $payload['axis_keys'] : array();
		$labels    = isset( $payload['axis_labels'] ) && is_array( $payload['axis_labels'] ) ? $payload['axis_labels'] : array();

		echo '<p class="wsp-muted">' . esc_html__( 'В таблице — только страны (без региональных агрегатов CSV вроде CEB, EMU). Шкалы и график — баллы 0–100. Отметьте страны для уточнения выводов.', 'worldstat-ergonomics' ) . '</p>';
		echo '<div class="wsergo-country-classification-analysis wsergo-cls-visual-analysis" id="' . esc_attr( $uid ) . '-cls-analysis" aria-live="polite"></div>';
		echo '<div class="wsergo-country-compare-chart-filter" data-explorer="' . esc_attr( $uid ) . '">';
		printf(
			'<label class="wsergo-country-compare-show-unselected-label"><input type="checkbox" class="wsergo-country-compare-show-unselected" data-explorer="%1$s" checked="checked" /> %2$s</label>',
			esc_attr( $uid ),
			esc_html__( 'Показывать невыбранные страны на шкалах и графике', 'worldstat-ergonomics' )
		);
		echo '</div>';
		echo '<div class="wsergo-cls-scatter-wrap" id="' . esc_attr( $uid ) . '-cls-scatter" data-explorer="' . esc_attr( $uid ) . '" aria-live="polite"></div>';
		echo '<div class="wsergo-cls-ladder-wrap" id="' . esc_attr( $uid ) . '-cls-ladder" data-explorer="' . esc_attr( $uid ) . '" aria-live="polite"></div>';

		echo '<div class="wsergo-country-compare-toolbar" data-explorer="' . esc_attr( $uid ) . '">';
		echo '<button type="button" class="wsp-btn-link wsergo-country-compare-select-all" data-explorer="' . esc_attr( $uid ) . '">' . esc_html__( 'Выбрать все', 'worldstat-ergonomics' ) . '</button>';
		echo '<span class="wsergo-country-compare-toolbar__sep">·</span>';
		echo '<button type="button" class="wsp-btn-link wsergo-country-compare-clear-all" data-explorer="' . esc_attr( $uid ) . '">' . esc_html__( 'Снять все', 'worldstat-ergonomics' ) . '</button>';
		echo '</div>';

		echo '<div class="wsp-table-wrap wsergo-country-compare-table-wrap">';
		echo '<table class="wsp-table wsergo-country-compare-table" id="' . esc_attr( $uid ) . '-countries">';
		echo '<thead><tr>';
		echo '<th class="wsergo-country-compare-table__chk" scope="col"><span class="screen-reader-text">' . esc_html__( 'Сравнение', 'worldstat-ergonomics' ) . '</span></th>';
		echo '<th scope="col">' . esc_html__( 'Страна', 'worldstat-ergonomics' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Уровень', 'worldstat-ergonomics' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Слабее итога', 'worldstat-ergonomics' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Профиль', 'worldstat-ergonomics' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Взвеш. балл', 'worldstat-ergonomics' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Сводный индекс', 'worldstat-ergonomics' ) . '</th>';
		foreach ( $axis_keys as $k ) {
			echo '<th scope="col">' . esc_html( isset( $labels[ $k ] ) ? (string) $labels[ $k ] : $k ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$iso2_row = strtoupper( (string) ( $row['iso2'] ?? '' ) );
			$is_page  = ( $iso2_row === $highlight_iso2 );
			$checked  = $is_page ? ' checked="checked"' : '';
			$tr_class = 'wsergo-country-compare-row';
			if ( $is_page ) {
				$tr_class .= ' is-page-country';
			}

			$tier = isset( $row['tier'] ) && is_array( $row['tier'] ) ? $row['tier'] : array();
			$slug = sanitize_key( (string) ( $tier['slug'] ?? '' ) );
			$tlab = esc_html( (string) ( $tier['label'] ?? '—' ) );
			$badge = '<span class="wsergo-tier-badge wsergo-tier-badge--' . esc_attr( $slug ) . '">' . $tlab . '</span>';

			echo '<tr class="' . esc_attr( $tr_class ) . '" data-iso2="' . esc_attr( $iso2_row ) . '">';
			echo '<td class="wsergo-country-compare-table__chk">';
			if ( $iso2_row !== '' ) {
				printf(
					'<input type="checkbox" class="wsergo-country-compare-cb" value="%s" aria-label="%s"%s />',
					esc_attr( $iso2_row ),
					esc_attr( (string) ( $row['name'] ?? $iso2_row ) ),
					$checked
				);
			}
			echo '</td>';
			echo '<td>';
			if ( $is_page ) {
				echo '<strong>' . esc_html( (string) ( $row['name'] ?? '' ) ) . '</strong>';
			} else {
				echo esc_html( (string) ( $row['name'] ?? '' ) );
			}
			echo '</td>';
			echo '<td title="' . esc_attr( (string) ( $row['tier_reason'] ?? '' ) ) . '">' . $badge . '</td>';
			$lim = isset( $row['limiting_labels'] ) && is_array( $row['limiting_labels'] ) ? $row['limiting_labels'] : array();
			echo '<td class="wsergo-country-compare-limiting">' . ( ! empty( $lim ) ? esc_html( implode( ', ', $lim ) ) : '—' ) . '</td>';
			$clab = isset( $row['cluster_label'] ) ? trim( (string) $row['cluster_label'] ) : '';
			echo '<td class="wsergo-country-compare-cluster">' . ( $clab !== '' ? esc_html( $clab ) : '—' ) . '</td>';
			echo '<td>' . esc_html( isset( $row['composite'] ) ? (string) $row['composite'] : '—' ) . '</td>';
			echo '<td>' . esc_html( isset( $row['relative_overall'] ) ? (string) $row['relative_overall'] : '—' ) . '</td>';

			$axes       = isset( $row['axes'] ) && is_array( $row['axes'] ) ? $row['axes'] : array();
			$axis_tiers = isset( $row['axis_tiers'] ) && is_array( $row['axis_tiers'] ) ? $row['axis_tiers'] : array();
			foreach ( $axis_keys as $k ) {
				$val  = isset( $axes[ $k ] ) ? (string) $axes[ $k ] : '—';
				$cell = '<span class="wsergo-axis-score">' . esc_html( $val ) . '</span>';
				if ( isset( $axis_tiers[ $k ] ) && is_array( $axis_tiers[ $k ] ) ) {
					$ats  = $axis_tiers[ $k ];
					$asl  = sanitize_key( (string) ( $ats['slug'] ?? '' ) );
					$alab = (string) ( $ats['label'] ?? '' );
					if ( $asl !== '' && $alab !== '' && $alab !== '—' ) {
						$cell .= ' <span class="wsergo-tier-badge wsergo-tier-badge--axis wsergo-tier-badge--' . esc_attr( $asl ) . '" title="' . esc_attr( $alab ) . '">' . esc_html( $alab ) . '</span>';
					}
				}
				echo '<td class="wsergo-country-compare-axis-cell">' . $cell . '</td>';
			}
			echo '</tr>';
		}

		echo '</tbody></table></div></div>';
	}
}
