<?php
/**
 * Блок «Анализ города» на вкладке страны (сравнение, регрессия).
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_City_Explorer {


	public static function ajax_explorer_payload(): void {
		check_ajax_referer( 'wsergo_city_explorer', 'nonce' );

		if ( ! class_exists( 'WSErgo_City_Data' ) ) {
			wp_send_json_error( [ 'message' => __( 'Модуль данных недоступен.', 'worldstat-ergonomics' ) ], 500 );
		}

		$scope = sanitize_key( (string) ( $_POST['scope'] ?? 'country' ) );
		if ( $scope === 'global' ) {
			wp_send_json_success( WSErgo_City_Data::get_global_city_explorer_payload() );
		}

		$iso2 = strtoupper( sanitize_text_field( (string) ( $_POST['iso2'] ?? '' ) ) );
		if ( $iso2 === '' ) {
			wp_send_json_error( [ 'message' => __( 'Не указана страна.', 'worldstat-ergonomics' ) ], 400 );
		}

		wp_send_json_success( WSErgo_City_Data::get_country_city_explorer_payload( $iso2 ) );
	}


	public static function ajax_compare(): void {
		check_ajax_referer( 'wsergo_city_explorer', 'nonce' );

		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 );
		}

		if ( ! class_exists( 'WSErgo_City_Data' ) ) {
			wp_send_json_error( [ 'message' => __( 'Модуль данных недоступен.', 'worldstat-ergonomics' ) ], 500 );
		}

		$raw = isset( $_POST['ids'] ) ? (string) wp_unslash( $_POST['ids'] ) : '';
		$ids = array_filter(
			array_map( 'intval', preg_split( '/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY ) ?: [] )
		);

		try {
			$payload = WSErgo_City_Data::get_cities_compare_payload( $ids );
		} catch ( \Throwable $e ) {
			wp_send_json_error(
				[
					'message' => sprintf(
						/* translators: %s: error message */
						__( 'Ошибка при загрузке сравнения: %s', 'worldstat-ergonomics' ),
						$e->getMessage()
					),
				],
				500
			);
		}

		if ( isset( $payload['error'] ) ) {
			wp_send_json_error( [ 'message' => (string) $payload['error'] ], 400 );
		}

		wp_send_json_success( $payload );
	}


	/**
	 * Стили и скрипты блока «Анализ города» на singular страны (до AJAX-вкладок).
	 */
	public static function enqueue_explorer_assets( bool $force = false ): void {
		if ( ! $force && ( ! class_exists( 'WorldStat_Country_CPT' ) || ! is_singular( WorldStat_Country_CPT::SLUG ) ) ) {
			return;
		}

		$deps_css = [];
		if ( wp_style_is( 'worldstat-components', 'registered' ) ) {
			$deps_css[] = 'worldstat-components';
		}

		wp_enqueue_style(
			'wsergo-city-explorer-public',
			WSErgo_Level_Registry::url( 'city', 'public/assets/css/city-explorer-public.css' ),
			$deps_css,
			WSErgo_Level_Registry::asset_version( 'city', 'public/assets/css/city-explorer-public.css' )
		);

		wp_enqueue_script(
			'wsergo-city-regression-chart',
			WSErgo_Level_Registry::url( 'city', 'public/assets/js/wsergo-city-regression-chart.js' ),
			[],
			WSErgo_Level_Registry::asset_version( 'city', 'public/assets/js/wsergo-city-regression-chart.js' ),
			true
		);
		wp_enqueue_script(
			'wsergo-city-search',
			WSErgo_Level_Registry::url( 'city', 'public/assets/js/wsergo-city-search.js' ),
			[],
			WSErgo_Level_Registry::asset_version( 'city', 'public/assets/js/wsergo-city-search.js' ),
			true
		);
		wp_enqueue_script(
			'wsergo-city-compare',
			WSErgo_Level_Registry::url( 'city', 'public/assets/js/wsergo-city-compare.js' ),
			[],
			WSErgo_Level_Registry::asset_version( 'city', 'public/assets/js/wsergo-city-compare.js' ),
			true
		);
		wp_enqueue_script(
			'wsergo-city-explorer-boot',
			WSErgo_Level_Registry::url( 'city', 'public/assets/js/wsergo-city-explorer-boot.js' ),
			[ 'jquery', 'wsergo-city-regression-chart', 'wsergo-city-search', 'wsergo-city-compare' ],
			WSErgo_Level_Registry::asset_version( 'city', 'public/assets/js/wsergo-city-explorer-boot.js' ),
			true
		);

		wp_localize_script(
			'wsergo-city-explorer-boot',
			'wsergoCityExplorerL10n',
			[
				'dimLabels' => class_exists( 'WSErgo_Model' ) ? WSErgo_Model::get_dimension_labels() : [],
			]
		);
	}


	/**
	 * HTML блока «Анализ города» для вставки во вкладку страны (в т.ч. lazy AJAX).
	 *
	 * @param string              $iso2         ISO2 страны.
	 * @param array<int, array>   $cities_rows  Строки городов из WSCities_CPT::get_cities_for_country().
	 * @return string
	 */
	public static function capture_render_block( string $iso2, array $cities_rows ): string {
		ob_start();
		self::render_block( $iso2, $cities_rows );
		$html = ob_get_clean();
		return is_string( $html ) ? $html : '';
	}


	public static function render_block( string $iso2, array $cities_rows ): void {
		if ( ! class_exists( 'WSErgo_City_Data' ) || empty( $cities_rows ) ) {
			return;
		}
		self::enqueue_explorer_assets();
		$iso_for_payload = strtoupper( sanitize_text_field( $iso2 ) );
		if ( $iso_for_payload === '' ) {
			return;
		}

		$pack = WSErgo_City_Data::get_country_city_explorer_payload( $iso_for_payload );
		if ( empty( $pack['cities'] ) || ! is_array( $pack['cities'] ) ) {
			return;
		}

		$pack['__ui'] = [
			'e_label'             => __( 'Индекс E', 'worldstat-ergonomics' ),
			'table_summary_title' => __( 'Значения как в таблице ниже (E и шесть измерений)', 'worldstat-ergonomics' ),
			'reg_chart_x_axis'    => __( 'Балл показателя (0–100)', 'worldstat-ergonomics' ),
			'reg_chart_y_axis'    => __( 'Индекс E', 'worldstat-ergonomics' ),
			'reg_chart_highlight' => __( 'Выделен на графике:', 'worldstat-ergonomics' ),
			'reg_chart_label'     => __( 'Показатель для графика', 'worldstat-ergonomics' ),
			'ajax_url'            => admin_url( 'admin-ajax.php' ),
			'nonce'               => wp_create_nonce( 'wsergo_city_explorer' ),
			'country_iso2'        => $iso_for_payload,
			'scope'               => 'country',
			'loading'             => __( 'Загрузка выборки…', 'worldstat-ergonomics' ),
			'load_error'          => __( 'Не удалось загрузить данные для сравнения.', 'worldstat-ergonomics' ),
			'reg_summary_country' => __( 'Регрессия по стране: %1$d городов с индексом E; медиана E по стране ≈ %2$s.', 'worldstat-ergonomics' ),
			'reg_summary_global'  => __( 'Межстрановая регрессия: %1$d городов с индексом E; медиана E по выборке ≈ %2$s.', 'worldstat-ergonomics' ),
			'reg_summary_global_index' => __( 'Доступно %1$d городов с индексом E (все страны). Найдите город через поиск и сравните 2–4 выбранных.', 'worldstat-ergonomics' ),
			'search_placeholder'  => __( 'Поиск по названию или стране…', 'worldstat-ergonomics' ),
			'select_placeholder'  => __( 'Выберите город…', 'worldstat-ergonomics' ),
			'search_no_results'   => __( 'Ничего не найдено', 'worldstat-ergonomics' ),
			'loading_compare'     => __( 'Загрузка данных для сравнения…', 'worldstat-ergonomics' ),
			'reg_summary_fail_country' => __( 'Регрессия по стране', 'worldstat-ergonomics' ),
			'reg_summary_fail_global'  => __( 'Межстрановая регрессия', 'worldstat-ergonomics' ),
			'reg_summary_fail_default' => __( 'недостаточно городов с рассчитанным E для устойчивой модели.', 'worldstat-ergonomics' ),
			'chart_points_country' => __( 'Каждая точка — один город выбранной страны с рассчитанным листовым E и баллом выбранного показателя (0–100).', 'worldstat-ergonomics' ),
			'chart_points_global'  => __( 'Каждая точка — один город из разных стран с рассчитанным листовым E и баллом показателя; сравнение идёт по единой шкале E.', 'worldstat-ergonomics' ),
			'compare_title'        => __( 'Сравнение городов', 'worldstat-ergonomics' ),
			'compare_hint'         => __( 'Выберите два или три города из списка (в том числе из разных стран). В таблице зелёным отмечено лучшее значение по строке, красным — слабее.', 'worldstat-ergonomics' ),
			'compare_city_1'       => __( 'Город 1', 'worldstat-ergonomics' ),
			'compare_city_2'       => __( 'Город 2', 'worldstat-ergonomics' ),
			'compare_city_3'       => __( 'Город 3 (необязательно)', 'worldstat-ergonomics' ),
			'cmp_none'             => __( '— не выбран —', 'worldstat-ergonomics' ),
			'cmp_metric'           => __( 'Показатель', 'worldstat-ergonomics' ),
			'cmp_need_two'         => __( 'Выберите минимум два разных города для сравнения.', 'worldstat-ergonomics' ),
			'cmp_stronger'         => __( 'Сильнее', 'worldstat-ergonomics' ),
			'cmp_weaker'           => __( 'Слабее', 'worldstat-ergonomics' ),
			'cmp_no_lead'          => __( 'Нет однозначных лидерств по строкам таблицы.', 'worldstat-ergonomics' ),
			'cmp_legend_best'      => __( 'лучше среди выбранных', 'worldstat-ergonomics' ),
			'cmp_legend_worst'     => __( 'слабее среди выбранных', 'worldstat-ergonomics' ),
			'cmp_section_axes'     => __( 'Сводные индексы', 'worldstat-ergonomics' ),
			'cmp_section_indicators' => __( 'Детальные показатели', 'worldstat-ergonomics' ),
			'cmp_summary_title'    => __( 'Краткий итог по выбранным городам', 'worldstat-ergonomics' ),
			'chart_highlight_multi' => __( 'На графике выделены сравниваемые города:', 'worldstat-ergonomics' ),
			'chart_highlight_multi_desc' => __( 'Города, выбранные для сравнения (цветные точки на графике).', 'worldstat-ergonomics' ),
			'chart_highlight_single_desc' => __( 'Город, выбранный в списке выше (красная обводка).', 'worldstat-ergonomics' ),
			'rec_title'           => __( 'Рекомендации', 'worldstat-ergonomics' ),
			'no_rec'              => __( 'Нет сформулированных рекомендаций.', 'worldstat-ergonomics' ),
			'ind_detail_title'    => __( 'Показатели (детально)', 'worldstat-ergonomics' ),
			'no_indicators'       => __( 'Нет привязанных листовых показателей по карте полей: оси выше могут быть оценены упрощённо по метаданным города.', 'worldstat-ergonomics' ),
			'col_indicator'       => __( 'Показатель', 'worldstat-ergonomics' ),
			'col_dimension'       => __( 'Измерение', 'worldstat-ergonomics' ),
			'col_raw'             => __( 'Сырое', 'worldstat-ergonomics' ),
			'col_score'           => __( 'Балл 0–100', 'worldstat-ergonomics' ),
			'col_source'          => __( 'Источник', 'worldstat-ergonomics' ),
			'dim_keys'            => [
				WSErgo_Model::DIM_FUNCTIONALITY,
				WSErgo_Model::DIM_SAFETY,
				WSErgo_Model::DIM_COMFORT,
				WSErgo_Model::DIM_LIVABILITY,
				WSErgo_Model::DIM_MASTERABILITY,
				WSErgo_Model::DIM_MANAGEABILITY,
			],
			'dim_labels'          => [
				WSErgo_Model::DIM_FUNCTIONALITY   => __( 'Функциональность', 'worldstat-ergonomics' ),
				WSErgo_Model::DIM_SAFETY           => __( 'Безопасность', 'worldstat-ergonomics' ),
				WSErgo_Model::DIM_COMFORT          => __( 'Комфортность', 'worldstat-ergonomics' ),
				WSErgo_Model::DIM_LIVABILITY       => __( 'Обитаемость', 'worldstat-ergonomics' ),
				WSErgo_Model::DIM_MASTERABILITY     => __( 'Освояемость', 'worldstat-ergonomics' ),
				WSErgo_Model::DIM_MANAGEABILITY    => __( 'Управляемость', 'worldstat-ergonomics' ),
			],
		];

		$uid     = 'wsergo-ce-' . sanitize_html_class( strtolower( $iso_for_payload ) ) . '-' . wp_rand( 10000, 99999 );
		$reg     = isset( $pack['regression'] ) && is_array( $pack['regression'] ) ? $pack['regression'] : [];
		$usable  = ! empty( $reg['usable'] );
		$n_leaf  = isset( $reg['n_leaf'] ) ? (int) $reg['n_leaf'] : 0;
		$med_l   = isset( $reg['median_leaf'] ) && is_numeric( $reg['median_leaf'] ) ? (float) $reg['median_leaf'] : null;

		usort(
			$cities_rows,
			static function ( $a, $b ) {
				$na = isset( $a['name'] ) ? (string) $a['name'] : '';
				$nb = isset( $b['name'] ) ? (string) $b['name'] : '';
				return strcasecmp( $na, $nb );
			}
		);
		$first_cid = (string) (int) ( $cities_rows[0]['id'] ?? 0 );

		$chart_models = [];
		if ( $usable && ! empty( $reg['univariate_full'] ) && is_array( $reg['univariate_full'] ) ) {
			foreach ( $reg['univariate_full'] as $uv ) {
				if ( is_array( $uv ) && ! empty( $uv['scatter'] ) && is_array( $uv['scatter'] ) && count( $uv['scatter'] ) >= 2 ) {
					$chart_models[] = $uv;
				}
			}
		}
		$chart_empty_class = empty( $chart_models ) ? ' is-empty' : '';

		echo '<div class="wsergo-city-explorer wsp-ergo-wrapper" id="' . esc_attr( $uid ) . '" data-wsergo-explorer="1">';
		echo '<div class="wsergo-city-explorer__card">';
		echo '<div class="wsergo-city-explorer__header">';
		echo '<h3 class="wsergo-city-explorer__title">' . esc_html__( 'Анализ города', 'worldstat-ergonomics' ) . '</h3>';
		echo '</div>';
		echo '<p class="wsergo-city-explorer__intro">' . esc_html__( 'Выберите город: показатели (сырые значения и баллы 0–100), сравнение с другими городами и регрессионные подсказки по направлениям безопасности, комфорта, управляемости и др. Можно сравнивать города в пределах страны или между странами по общему индексу E.', 'worldstat-ergonomics' ) . '</p>';

		echo '<div class="wsergo-city-explorer__toolbar">';
		echo '<fieldset class="wsergo-city-explorer__scope">';
		echo '<legend class="wsergo-city-explorer__scope-legend">' . esc_html__( 'Контекст сравнения', 'worldstat-ergonomics' ) . '</legend>';
		echo '<div class="wsergo-city-explorer__scope-options">';
		echo '<label class="wsergo-city-explorer__scope-opt"><input type="radio" name="' . esc_attr( $uid ) . '-scope" value="country" checked> <span>' . esc_html__( 'В пределах страны', 'worldstat-ergonomics' ) . '</span></label>';
		echo '<label class="wsergo-city-explorer__scope-opt"><input type="radio" name="' . esc_attr( $uid ) . '-scope" value="global"> <span>' . esc_html__( 'Между странами (общий E)', 'worldstat-ergonomics' ) . '</span></label>';
		echo '</div></fieldset>';
		echo '<div class="wsergo-city-explorer__field wsergo-city-explorer__single-wrap">';
		echo '<label class="wsergo-city-explorer__field-label" for="' . esc_attr( $uid ) . '-sel">' . esc_html__( 'Город для детального анализа', 'worldstat-ergonomics' ) . '</label>';
		echo '<select id="' . esc_attr( $uid ) . '-sel" class="wsergo-city-explorer__select wsp-select">';
		foreach ( $cities_rows as $c ) {
			$cid = (int) ( $c['id'] ?? 0 );
			if ( $cid <= 0 ) {
				continue;
			}
			$name = isset( $c['name'] ) ? (string) $c['name'] : get_the_title( $cid );
			printf(
				'<option value="%d"%s>%s</option>',
				$cid,
				(string) $cid === $first_cid ? ' selected' : '',
				esc_html( $name )
			);
		}
		echo '</select></div>';
		echo '</div>';

		echo '<section class="wsergo-city-explorer__section wsergo-city-explorer__compare-wrap" id="' . esc_attr( $uid ) . '-compare-wrap">';
		echo '<h4 class="wsergo-city-explorer__section-title">' . esc_html__( 'Сравнение городов', 'worldstat-ergonomics' ) . '</h4>';
		echo '<p class="wsergo-city-explorer__section-hint">' . esc_html__( 'Откройте выпадающий список, найдите город через поиск и выберите 2–3 города (в том числе из разных стран). В таблице зелёным — лучшее значение, красным — слабее.', 'worldstat-ergonomics' ) . '</p>';
		echo '<div class="wsergo-cmp-pickers">';
		$cmp_labels = [
			__( 'Город 1', 'worldstat-ergonomics' ),
			__( 'Город 2', 'worldstat-ergonomics' ),
			__( 'Город 3 (необязательно)', 'worldstat-ergonomics' ),
		];
		foreach ( [ 'a', 'b', 'c' ] as $cmp_i => $cmp_suffix ) {
			$cmp_id      = $uid . '-cmp-' . $cmp_suffix;
			$is_optional = ( 'c' === $cmp_suffix );
			$default_lbl = $is_optional
				? __( '— не выбран —', 'worldstat-ergonomics' )
				: __( 'Выберите город…', 'worldstat-ergonomics' );
			echo '<div class="wsergo-cmp-pickers__item"><span class="wsergo-cmp-pickers__label">' . esc_html( $cmp_labels[ $cmp_i ] ) . '</span>';
			echo '<div class="wsergo-city-search wsergo-city-select" id="' . esc_attr( $cmp_id ) . '">';
			echo '<button type="button" class="wsergo-city-select__trigger wsp-select" aria-haspopup="listbox" aria-expanded="false">';
			echo '<span class="wsergo-city-select__label">' . esc_html( $default_lbl ) . '</span>';
			echo '<span class="wsergo-city-select__caret" aria-hidden="true"></span>';
			echo '</button>';
			echo '<input type="hidden" class="wsergo-city-search__id" value="">';
			echo '<div class="wsergo-city-select__panel" hidden>';
			echo '<div class="wsergo-city-select__search-wrap">';
			echo '<input type="search" class="wsergo-city-select__search wsp-search-input" placeholder="' . esc_attr__( 'Поиск по названию или стране…', 'worldstat-ergonomics' ) . '" autocomplete="off" aria-label="' . esc_attr__( 'Поиск города', 'worldstat-ergonomics' ) . '">';
			echo '</div>';
			echo '<ul class="wsergo-city-select__list" role="listbox"></ul>';
			echo '</div></div></div>';
		}
		echo '</div>';
		echo '<div id="' . esc_attr( $uid ) . '-compare-body" class="wsergo-cmp-body"></div>';
		echo '</section>';

		echo '<div class="wsergo-city-explorer__reg-summary" id="' . esc_attr( $uid ) . '-reg-summary">';
		if ( $usable ) {
			echo esc_html(
				sprintf(
					/* translators: 1: number of cities, 2: median E */
					__( 'Регрессия по стране: %1$d городов с индексом E; медиана E по стране ≈ %2$s.', 'worldstat-ergonomics' ),
					$n_leaf,
					$med_l !== null && is_finite( $med_l ) ? number_format_i18n( $med_l, 1 ) : '—'
				)
			);
		} else {
			$note = isset( $reg['notice'] ) ? (string) $reg['notice'] : '';
			echo '<strong>' . esc_html__( 'Регрессия по стране', 'worldstat-ergonomics' ) . ':</strong> ';
			echo $note !== '' ? esc_html( $note ) : esc_html__( 'недостаточно городов с рассчитанным E для устойчивой модели.', 'worldstat-ergonomics' );
		}
		echo '</div>';

		echo '<section class="wsergo-city-explorer__section wsergo-city-explorer__section--chart' . esc_attr( $chart_empty_class ) . '" id="' . esc_attr( $uid ) . '-chart-wrap">';
		echo '<h4 class="wsergo-city-explorer__section-title">' . esc_html__( 'График регрессии', 'worldstat-ergonomics' ) . '</h4>';
		echo '<details class="wsergo-reg-chart-help">';
		echo '<summary class="wsergo-reg-chart-help__summary">' . esc_html__( 'Как читать график', 'worldstat-ergonomics' ) . '</summary>';
		echo '<p class="wsergo-reg-chart-help__lead">' . esc_html__( 'На графике показана связь листового индекса эргономичности E с баллом выбранного показателя (0–100) по всем городам текущей выборки. Синяя линия — линейная регрессия (МНК): как в среднем меняется E при росте балла показателя. Чем выше R², тем сильнее эта связь в выборке.', 'worldstat-ergonomics' ) . '</p>';
		echo '<dl class="wsergo-reg-glossary">';
		echo '<dt>' . esc_html__( 'Индекс E (ось Y)', 'worldstat-ergonomics' ) . '</dt>';
		echo '<dd>' . esc_html__( 'Сводный листовой индекс эргономичности города по единой методике; сравним между городами и странами.', 'worldstat-ergonomics' ) . '</dd>';
		echo '<dt>' . esc_html__( 'Балл показателя (ось X)', 'worldstat-ergonomics' ) . '</dt>';
		echo '<dd>' . esc_html__( 'Нормированное значение выбранного показателя на шкале 0–100 (чем выше, тем лучше по правилам нормализации).', 'worldstat-ergonomics' ) . '</dd>';
		echo '<dt>' . esc_html__( 'Точки', 'worldstat-ergonomics' ) . '</dt>';
		echo '<dd id="' . esc_attr( $uid ) . '-chart-points-desc">' . esc_html__( 'Каждая точка — один город выбранной страны с рассчитанным E и баллом показателя.', 'worldstat-ergonomics' ) . '</dd>';
		echo '<dt>' . esc_html__( 'Синяя линия', 'worldstat-ergonomics' ) . '</dt>';
		echo '<dd>' . esc_html__( 'Прямая МНК: E ≈ a·x + b, где x — балл показателя. Уравнение и R² указаны под графиком.', 'worldstat-ergonomics' ) . '</dd>';
		echo '<dt>' . esc_html__( 'Выделенная точка', 'worldstat-ergonomics' ) . '</dt>';
		echo '<dd id="' . esc_attr( $uid ) . '-chart-highlight-desc">' . esc_html__( 'Город, выбранный в списке выше (красная обводка).', 'worldstat-ergonomics' ) . '</dd>';
		echo '</dl></details>';
		echo '<div class="wsergo-city-explorer__chart-field">';
		echo '<label class="wsergo-city-explorer__field-label" for="' . esc_attr( $uid ) . '-chart-ind">' . esc_html__( 'Показатель для графика', 'worldstat-ergonomics' ) . '</label>';
		echo '<select id="' . esc_attr( $uid ) . '-chart-ind" class="wsergo-city-explorer__chart-select wsp-select">';
		foreach ( $chart_models as $cm ) {
			$iid = isset( $cm['id'] ) ? (string) $cm['id'] : '';
			$lab = isset( $cm['label'] ) ? (string) $cm['label'] : $iid;
			$r2d = isset( $cm['r2'] ) && is_numeric( $cm['r2'] ) ? number_format_i18n( (float) $cm['r2'], 3 ) : '—';
			printf(
				'<option value="%s">%s — R²=%s</option>',
				esc_attr( $iid ),
				esc_html( $lab ),
				esc_html( $r2d )
			);
		}
		echo '</select></div>';
		echo '<div class="wsergo-city-explorer__chart-canvas-wrap">';
		echo '<canvas id="' . esc_attr( $uid ) . '-chart-cv" class="wsergo-city-explorer__chart-canvas" width="600" height="280"></canvas>';
		echo '</div>';
		echo '<p class="wsergo-city-explorer__chart-cap" id="' . esc_attr( $uid ) . '-chart-cap"></p>';
		echo '</section>';

		echo '<div class="wsergo-city-explorer__panel">';
		echo '<div class="wsergo-city-explorer__body"></div>';
		echo '</div>';

		$json = wp_json_encode(
			$pack,
			JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
		);
		if ( ! is_string( $json ) ) {
			$json = '{}';
		}
		echo '<script type="application/json" id="' . esc_attr( $uid ) . '-json">' . $json . '</script>';
		echo '</div></div>';
	}
}
