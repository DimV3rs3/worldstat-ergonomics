<?php
/**
 * Публичный исследователь городов (из ветки cities).
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_Renderer_City_Explorer {
	private static function public_asset_version( string $relative_path ): string {
		$base = defined( 'WSERGO_FILE' ) ? dirname( (string) WSERGO_FILE ) : '';
		$path = $base . '/' . ltrim( $relative_path, '/' );
		if ( $path !== '' && is_readable( $path ) ) {
			return (string) filemtime( $path );
		}
		return defined( 'WSERGO_VERSION' ) ? (string) WSERGO_VERSION : '1';
	}

	public static function ajax_city_explorer_payload(): void {
		check_ajax_referer( 'wsergo_city_explorer', 'nonce' );

		if ( ! class_exists( 'WSErgo_Data' ) ) {
			wp_send_json_error( [ 'message' => __( 'Модуль данных недоступен.', 'worldstat-ergonomics' ) ], 500 );
		}

		$scope = sanitize_key( (string) ( $_POST['scope'] ?? 'country' ) );
		if ( $scope === 'global' ) {
			wp_send_json_success( WSErgo_Data::get_global_city_explorer_payload() );
		}

		$iso2 = strtoupper( sanitize_text_field( (string) ( $_POST['iso2'] ?? '' ) ) );
		if ( $iso2 === '' ) {
			wp_send_json_error( [ 'message' => __( 'Не указана страна.', 'worldstat-ergonomics' ) ], 400 );
		}

		wp_send_json_success( WSErgo_Data::get_country_city_explorer_payload( $iso2 ) );
	}

	/**
	 * AJAX: детальные данные для сравнения 2–4 городов.
	 */
	public static function ajax_city_compare(): void {
		check_ajax_referer( 'wsergo_city_explorer', 'nonce' );

		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 );
		}

		if ( ! class_exists( 'WSErgo_Data' ) ) {
			wp_send_json_error( [ 'message' => __( 'Модуль данных недоступен.', 'worldstat-ergonomics' ) ], 500 );
		}

		$raw = isset( $_POST['ids'] ) ? (string) wp_unslash( $_POST['ids'] ) : '';
		$ids = array_filter(
			array_map( 'intval', preg_split( '/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY ) ?: [] )
		);

		try {
			$payload = WSErgo_Data::get_cities_compare_payload( $ids );
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
	public static function render_country_city_explorer_block( string $iso2, array $cities_rows ): void {
		if ( ! class_exists( 'WSErgo_Data' ) || empty( $cities_rows ) ) {
			return;
		}
		$iso_for_payload = strtoupper( sanitize_text_field( $iso2 ) );
		if ( $iso_for_payload === '' ) {
			return;
		}

		$pack = WSErgo_Data::get_country_city_explorer_payload( $iso_for_payload );
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

		echo '<div class="wsergo-city-explorer" id="' . esc_attr( $uid ) . '">';
		echo '<style>';
		echo '.wsergo-city-explorer table.wsergo-cmp-table{display:table!important;width:100%!important;border-collapse:collapse!important;table-layout:fixed!important;}';
		echo '.wsergo-city-explorer table.wsergo-cmp-table thead{display:table-header-group!important;}';
		echo '.wsergo-city-explorer table.wsergo-cmp-table tbody{display:table-row-group!important;}';
		echo '.wsergo-city-explorer table.wsergo-cmp-table tr{display:table-row!important;}';
		echo '.wsergo-city-explorer table.wsergo-cmp-table th,.wsergo-city-explorer table.wsergo-cmp-table td{display:table-cell!important;}';
		echo '.wsergo-city-explorer .wsergo-cmp-table thead th.wsergo-cmp-table__city{text-transform:none!important;letter-spacing:0!important;white-space:normal!important;}';
		echo '.wsergo-city-explorer .wsergo-cmp-table .wsergo-cmp-table__city-name,.wsergo-city-explorer .wsergo-cmp-table .wsergo-cmp-table__metric-label{display:block!important;font-weight:600;line-height:1.35;}';
		echo '.wsergo-city-explorer .wsergo-cmp-table .wsergo-cmp-table__city-country,.wsergo-city-explorer .wsergo-cmp-table .wsergo-cmp-table__sub{display:block!important;font-size:11px;font-weight:500;color:#6b7280;line-height:1.35;margin-top:2px;}';
		echo '.wsergo-city-explorer .wsergo-cmp-table td.wsergo-cmp-table__metric,.wsergo-city-explorer .wsergo-cmp-table th.wsergo-cmp-table__city{white-space:normal!important;}';
		echo '.wsergo-city-explorer .wsergo-cmp-table td.wsergo-cmp-table__val{white-space:nowrap!important;}';
		echo '</style>';
		echo '<h3 class="wsp-section-title wsergo-city-explorer__title">' . esc_html__( 'Анализ города', 'worldstat-ergonomics' ) . '</h3>';
		echo '<p class="wsergo-city-explorer__intro">' . esc_html__( 'Выберите город: показатели (сырые значения и баллы 0–100), сравнение с другими городами и регрессионные подсказки по направлениям безопасности, комфорта, управляемости и др. Можно сравнивать города в пределах страны или между странами по общему индексу E.', 'worldstat-ergonomics' ) . '</p>';

		echo '<fieldset class="wsergo-city-explorer__scope">';
		echo '<legend class="wsergo-city-explorer__scope-legend">' . esc_html__( 'Контекст сравнения', 'worldstat-ergonomics' ) . '</legend>';
		echo '<div class="wsergo-city-explorer__scope-options">';
		echo '<label class="wsergo-city-explorer__scope-opt"><input type="radio" name="' . esc_attr( $uid ) . '-scope" value="country" checked> <span>' . esc_html__( 'В пределах страны', 'worldstat-ergonomics' ) . '</span></label>';
		echo '<label class="wsergo-city-explorer__scope-opt"><input type="radio" name="' . esc_attr( $uid ) . '-scope" value="global"> <span>' . esc_html__( 'Между странами (общий E)', 'worldstat-ergonomics' ) . '</span></label>';
		echo '</div></fieldset>';

		echo '<div class="wsergo-city-explorer__single-wrap">';
		echo '<label for="' . esc_attr( $uid ) . '-sel" class="screen-reader-text">' . esc_html__( 'Город для детального анализа', 'worldstat-ergonomics' ) . '</label>';
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

		echo '<div class="wsergo-city-explorer__compare-wrap" id="' . esc_attr( $uid ) . '-compare-wrap">';
		echo '<h4 class="wsp-section-title wsergo-city-explorer__compare-title">' . esc_html__( 'Сравнение городов', 'worldstat-ergonomics' ) . '</h4>';
		echo '<p class="wsergo-city-explorer__compare-hint">' . esc_html__( 'Откройте выпадающий список, найдите город через поиск и выберите 2–3 города (в том числе из разных стран). В таблице зелёным — лучшее значение, красным — слабее.', 'worldstat-ergonomics' ) . '</p>';
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
		echo '</div>';

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

		$chart_models = [];
		if ( $usable && ! empty( $reg['univariate_full'] ) && is_array( $reg['univariate_full'] ) ) {
			foreach ( $reg['univariate_full'] as $uv ) {
				if ( is_array( $uv ) && ! empty( $uv['scatter'] ) && is_array( $uv['scatter'] ) && count( $uv['scatter'] ) >= 2 ) {
					$chart_models[] = $uv;
				}
			}
		}
		$chart_style = 'margin-bottom:14px;padding:12px;background:#fff;border:1px solid #dcdcde;border-radius:8px;';
		if ( empty( $chart_models ) ) {
			$chart_style .= 'display:none;';
		}
		echo '<div class="wsergo-city-explorer__chart" id="' . esc_attr( $uid ) . '-chart-wrap" style="' . esc_attr( $chart_style ) . '">';
		echo '<h4 class="wsp-section-title" style="margin:0 0 10px;font-size:1rem;">' . esc_html__( 'График регрессии', 'worldstat-ergonomics' ) . '</h4>';
		echo '<div class="wsergo-reg-chart-help" style="margin:0 0 12px;font-size:13px;line-height:1.5;">';
		echo '<p class="description" style="margin:0 0 8px;">' . esc_html__( 'На графике показана связь листового индекса эргономичности E с баллом выбранного показателя (0–100) по всем городам текущей выборки. Синяя линия — линейная регрессия (МНК): как в среднем меняется E при росте балла показателя. Чем выше R², тем сильнее эта связь в выборке.', 'worldstat-ergonomics' ) . '</p>';
		echo '<p class="wsergo-reg-glossary__title" style="margin:0 0 6px;font-weight:600;">' . esc_html__( 'Как читать график', 'worldstat-ergonomics' ) . '</p>';
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
		echo '</dl></div>';
		echo '<label for="' . esc_attr( $uid ) . '-chart-ind" style="display:block;margin-bottom:6px;font-weight:600;">' . esc_html__( 'Показатель для графика', 'worldstat-ergonomics' ) . '</label>';
		echo '<select id="' . esc_attr( $uid ) . '-chart-ind" class="wsergo-city-explorer__chart-select" style="max-width:100%;width:min(520px,100%);margin-bottom:10px;">';
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
		echo '</select>';
		echo '<div style="position:relative;width:100%;">';
		echo '<canvas id="' . esc_attr( $uid ) . '-chart-cv" width="600" height="280" style="display:block;max-width:100%;height:auto;border-radius:6px;background:#fafafa;"></canvas>';
		echo '</div>';
		echo '<p class="description" id="' . esc_attr( $uid ) . '-chart-cap" style="margin:8px 0 0;font-size:12px;line-height:1.45;"></p>';
		echo '</div>';
		$ver_chart   = self::public_asset_version( 'assets/js/wsergo-city-regression-chart.js' );
		$ver_search  = self::public_asset_version( 'assets/js/wsergo-city-search.js' );
		$ver_compare = self::public_asset_version( 'assets/js/wsergo-city-compare.js' );
		echo '<script src="' . esc_url( WSERGO_URL . 'assets/js/wsergo-city-regression-chart.js?ver=' . rawurlencode( $ver_chart ) ) . '"></script>';
		echo '<script src="' . esc_url( WSERGO_URL . 'assets/js/wsergo-city-search.js?ver=' . rawurlencode( $ver_search ) ) . '"></script>';
		echo '<script src="' . esc_url( WSERGO_URL . 'assets/js/wsergo-city-compare.js?ver=' . rawurlencode( $ver_compare ) ) . '"></script>';

		echo '<div class="wsergo-city-explorer__body" style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:12px;"></div>';

		$json = wp_json_encode(
			$pack,
			JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
		);
		if ( ! is_string( $json ) ) {
			$json = '{}';
		}
		echo '<script type="application/json" id="' . esc_attr( $uid ) . '-json">' . $json . '</script>';
		echo '<script>';
		echo '(function(){var u=' . wp_json_encode( $uid, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';';
		echo 'var r=document.getElementById(u);if(!r)return;var s=document.getElementById(u+"-sel"),b=r.querySelector(".wsergo-city-explorer__body"),j=document.getElementById(u+"-json");';
		echo 'var pack={},packCountry=null,packGlobal=null,scope="country",selId="";';
		echo 'try{pack=JSON.parse(j.textContent||"{}");packCountry=JSON.parse(JSON.stringify(pack));}catch(e){return;}';
		echo 'var L=pack.cities||{},UI=pack.__ui||{},dimKeys=UI.dim_keys||[],dimLab=UI.dim_labels||{},reg=pack.regression||{};';
		$axis_labels = wp_json_encode( WSErgo_Model::get_dimension_labels(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		echo 'var AXL=' . $axis_labels . ';';
		echo 'var regSum=document.getElementById(u+"-reg-summary"),chartWrap=document.getElementById(u+"-chart-wrap"),chartPoints=document.getElementById(u+"-chart-points-desc"),chartHiDesc=document.getElementById(u+"-chart-highlight-desc"),compareBody=document.getElementById(u+"-compare-body"),singleWrap=r.querySelector(".wsergo-city-explorer__single-wrap"),compareIds=[],compareReq=0,cmpTimer=null;';
		echo 'function esc(x){var d=document.createElement("div");d.textContent=x==null?"":String(x);return d.innerHTML;}';
		echo 'function th(t){return "<th style=\\"text-align:left;padding:6px 8px;border-bottom:1px solid #ddd;\\">"+esc(t)+"</th>";}';
		echo 'function td(x,c){return "<td style=\\"padding:6px 8px;border-bottom:1px solid #eee;"+(c||"")+"\\">"+x+"</td>";}';
		echo 'function render(){var id=s.value,c=L[id];if(!c){b.innerHTML="";return;}';
		echo 'var h="<h4 style=\\"margin:0 0 8px;\\">"+esc(c.name);';
		echo 'if(scope==="global"&&c.country_name){h+=" <span style=\\"color:#50575e;font-weight:normal;\\">("+esc(c.country_name)+")</span>";}';
		echo 'h+="</h4>";';
		echo 'var te=c.table_e!=null&&c.table_e!==""?c.table_e:(c.leaf_e!=null?c.leaf_e:"—");';
		echo 'var eLab=UI.e_label||"E";';
		echo 'h+="<p style=\\"margin:0 0 12px;\\"><strong>"+esc(eLab)+"</strong>: "+esc(te)+"</p>";';
		echo 'h+="<h5 style=\\"margin:0 0 8px;\\">"+esc(' . wp_json_encode( __( 'Рекомендации', 'worldstat-ergonomics' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ')+"</h5><ul style=\\"margin:0 0 14px;padding-left:1.2em;\\">";';
		echo 'var rec=(c.recommendations||[]);if(!rec.length){h+="<li>"+esc(' . wp_json_encode( __( 'Нет сформулированных рекомендаций.', 'worldstat-ergonomics' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ')+"</li>";}';
		echo 'else{for(var ri=0;ri<rec.length;ri++){h+="<li style=\\"margin-bottom:6px;\\">"+esc(rec[ri])+"</li>";}}';
		echo 'h+="</ul>";';
		echo 'h+="<h5 style=\\"margin:0 0 8px;\\">"+esc(UI.table_summary_title||"")+"</h5>";';
		echo 'h+="<div style=\\"display:grid;grid-template-columns:repeat(auto-fill,minmax(118px,1fr));gap:8px;margin-bottom:14px;\\">";';
		echo 'h+="<div style=\\"border:1px solid #e0e0e0;border-radius:8px;padding:8px;background:#fafafa;\\"><div style=\\"font-size:12px;color:#50575e;\\">"+esc(eLab)+"</div><div style=\\"font-size:1.15rem;font-weight:700;\\">"+esc(te)+"</div></div>";';
		echo 'var ts=c.table_subindices||{};';
		echo 'for(var di=0;di<dimKeys.length;di++){var dk=dimKeys[di],vv=ts[dk];if(vv==null||vv===""){vv="—";}var lb=dimLab[dk]||AXL[dk]||dk;h+="<div style=\\"border:1px solid #e0e0e0;border-radius:8px;padding:8px;background:#fafafa;\\"><div style=\\"font-size:12px;color:#50575e;\\">"+esc(lb)+"</div><div style=\\"font-size:1.15rem;font-weight:700;\\">"+esc(vv)+"</div></div>";}';
		echo 'h+="</div>";';
		echo 'var ind=c.indicators||[];';
		echo 'h+="<h5 style=\\"margin:16px 0 8px;\\">"+esc(' . wp_json_encode( __( 'Показатели (детально)', 'worldstat-ergonomics' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ')+"</h5>";';
		echo 'if(!ind.length){h+="<p class=\\"description\\">"+esc(' . wp_json_encode( __( 'Нет привязанных листовых показателей по карте полей: оси выше могут быть оценены упрощённо по метаданным города.', 'worldstat-ergonomics' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ')+"</p>";}';
		echo 'else{h+="<div style=\\"overflow-x:auto;\\"><table class=\\"widefat striped\\" style=\\"width:100%;border-collapse:collapse;\\"><thead><tr>";';
		echo 'h+=th(' . wp_json_encode( __( 'Показатель', 'worldstat-ergonomics' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ')+th(' . wp_json_encode( __( 'Измерение', 'worldstat-ergonomics' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ')+th(' . wp_json_encode( __( 'Сырое', 'worldstat-ergonomics' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ')+th(' . wp_json_encode( __( 'Балл 0–100', 'worldstat-ergonomics' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ')+th(' . wp_json_encode( __( 'Источник', 'worldstat-ergonomics' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ');h+="</tr></thead><tbody>";';
		echo 'for(var j=0;j<ind.length;j++){var it=ind[j];var rv=it.raw_value,sv=it.score_value,dm=it.dimension||"";var dlab=AXL[dm]||dm;h+="<tr>";h+=td(esc(it.label||it.id));h+=td(esc(dlab));h+=td(rv!=null?esc(rv):"—","text-align:right");h+=td(sv!=null?esc(sv):"—","text-align:right");h+=td(esc(it.source_field||""));h+="</tr>";}';
		echo 'h+="</tbody></table></div>";}';
		echo 'b.innerHTML=h;}';
		echo 'var chartCv=document.getElementById(u+"-chart-cv"),chartSel=document.getElementById(u+"-chart-ind"),chartCap=document.getElementById(u+"-chart-cap");';
		echo 'function chartModels(){var list=reg.univariate_full||[],out=[];for(var ci=0;ci<list.length;ci++){var uv=list[ci];if(uv&&uv.scatter&&uv.scatter.length>=2){out.push(uv);}}return out;}';
		echo 'function cityLabel(c){var n=c.name||"";if(scope==="global"&&c.country_name){n+=(n?" (":"(")+c.country_name+")";}return n;}';
		echo 'function rebuildCitySelect(){var prev=s.value,ids=Object.keys(L);ids.sort(function(a,b){return cityLabel(L[a]||{}).localeCompare(cityLabel(L[b]||{}),"ru");});var h="";for(var oi=0;oi<ids.length;oi++){var cid=ids[oi];h+="<option value=\\""+esc(cid)+"\\""+(cid===prev?" selected":"")+">"+esc(cityLabel(L[cid]||{}))+"</option>";}s.innerHTML=h;if(prev&&L[prev]){s.value=prev;}else if(ids.length){s.value=ids[0];}}';
		echo 'function rebuildChartSelect(){if(!chartSel)return;var models=chartModels(),prev=chartSel.value;chartSel.innerHTML="";for(var mi=0;mi<models.length;mi++){var cm=models[mi],iid=String(cm.id||""),lab=cm.label||iid,r2=cm.r2!=null?cm.r2:"—";chartSel.innerHTML+="<option value=\\""+esc(iid)+"\\">"+esc(lab)+" — R²="+esc(r2)+"</option>";}if(prev&&models.some(function(m){return String(m.id)===prev;})){chartSel.value=prev;}if(chartWrap){chartWrap.style.display=models.length?"":"none";}}';
		echo 'function updateRegSummary(){if(!regSum)return;var nl=reg.n_leaf||Object.keys(cityIndex).length||0;if(scope==="global"){var tpl=UI.reg_summary_global_index||"";regSum.textContent=tpl.replace("%1$d",nl);return;}var usable=!!reg.usable,med=reg.median_leaf,meds=med!=null&&isFinite(med)?String(med):"—";if(usable){var tpl2=UI.reg_summary_country||"";regSum.textContent=tpl2.replace("%1$d",nl).replace("%2$s",meds);}else{var fail=UI.reg_summary_fail_country||"",note=reg.notice||UI.reg_summary_fail_default||"";regSum.innerHTML="<strong>"+esc(fail)+":</strong> "+esc(note);}}';
		echo 'var cityIndex={},compareCache={};';
		echo 'function cmpSlotId(slot){return u+"-cmp-"+slot;}';
		echo 'function getCompareIds(){var out=[];["a","b","c"].forEach(function(slot){var root=document.getElementById(cmpSlotId(slot));if(!root)return;var hid=root.querySelector(".wsergo-city-search__id");var id=hid&&hid.value;if(id&&cityIndex[id]&&out.indexOf(id)<0){out.push(id);}});return out;}';
		echo 'function initCompareSearch(){var slots=[{s:"a",empty:false},{s:"b",empty:false},{s:"c",empty:true}];slots.forEach(function(cfg){var root=document.getElementById(cmpSlotId(cfg.s));if(!root||typeof window.wsergoInitCitySearch!=="function")return;if(root.dataset.wsergoInited==="1"){return;}root.dataset.wsergoInited="1";window.wsergoInitCitySearch(root,cityIndex,{allowEmpty:cfg.empty,placeholder:UI.search_placeholder||"",selectLabel:cfg.empty?(UI.cmp_none||""):(UI.select_placeholder||""),emptyLabel:UI.cmp_none||"",noResults:UI.search_no_results||"",onChange:function(){if(scope==="global"){renderCompareView();}}});});}';
		echo 'function fillDefaultCompareSlots(){var ids=Object.keys(cityIndex);ids.sort(function(a,b){return cityLabel(cityIndex[a]||{}).localeCompare(cityLabel(cityIndex[b]||{}),"ru");});var ra=document.getElementById(cmpSlotId("a")),rb=document.getElementById(cmpSlotId("b"));if(ids.length>=1&&ra&&ra.wsergoSetCity){ra.wsergoSetCity(ids[0],true);}if(ids.length>=2&&rb&&rb.wsergoSetCity){var bid=ids[1];for(var bx=0;bx<ids.length;bx++){if(ids[bx]!==ids[0]){bid=ids[bx];break;}}rb.wsergoSetCity(bid,true);}}';
		echo 'function renderCompareView(){if(cmpTimer){clearTimeout(cmpTimer);}cmpTimer=setTimeout(function(){cmpTimer=null;var req=++compareReq;compareIds=getCompareIds();if(!compareBody)return;if(compareIds.length<2){compareBody.innerHTML="<p class=\\"wsergo-cmp-placeholder\\">"+esc(UI.cmp_need_two||"")+"</p>";return;}compareBody.innerHTML="<p class=\\"wsergo-cmp-placeholder\\">"+esc(UI.loading_compare||UI.loading||"")+"</p>";fetch(UI.ajax_url,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:new URLSearchParams({action:"wsergo_city_compare",ids:compareIds.join(","),nonce:UI.nonce})}).then(function(res){return res.json().catch(function(){return null;});}).then(function(data){if(req!==compareReq)return;if(!data||!data.success){var msg=(data&&data.data&&data.data.message)?data.data.message:(UI.load_error||"");throw new Error(msg);}var cities=data.data.cities||{};if(Object.keys(cities).length<2){throw new Error(UI.cmp_need_two||UI.load_error||"");}compareCache=cities;if(typeof window.wsergoRenderCityCompare!=="function"){throw new Error(UI.load_error||"Compare JS missing");}window.wsergoRenderCityCompare(compareBody,compareCache,compareIds,{dimKeys:dimKeys,dimLabels:dimLab,eLabel:UI.e_label||"E",axisLabels:AXL,strings:{need_two:UI.cmp_need_two,metric:UI.cmp_metric,stronger:UI.cmp_stronger,weaker:UI.cmp_weaker,no_lead:UI.cmp_no_lead,legend_best:UI.cmp_legend_best,legend_worst:UI.cmp_legend_worst,section_axes:UI.cmp_section_axes,section_indicators:UI.cmp_section_indicators,summary_title:UI.cmp_summary_title}});}).catch(function(err){if(req!==compareReq)return;compareBody.innerHTML="<p class=\\"wsergo-cmp-error\\">"+esc(err&&err.message?err.message:(UI.load_error||""))+"</p>";});},280);}';
		echo 'function setExplorerMode(){r.classList.toggle("wsergo-city-explorer--global",scope==="global");if(singleWrap){singleWrap.style.display=scope==="global"?"none":"";}if(chartWrap){chartWrap.style.display=scope==="global"?"none":"";}if(chartHiDesc){chartHiDesc.textContent=scope==="global"?(UI.chart_highlight_multi_desc||""):(UI.chart_highlight_single_desc||"");}}';
		echo 'function applyPack(np,sc){var ui=pack.__ui||UI;pack=np||{};pack.__ui=ui;UI=pack.__ui;L=pack.cities||{};reg=pack.regression||{};scope=sc||pack.scope||"country";if(scope==="global"){cityIndex=L;}setExplorerMode();if(chartPoints){chartPoints.textContent=scope==="global"?(UI.chart_points_global||""):(UI.chart_points_country||"");}updateRegSummary();if(scope==="global"){initCompareSearch();fillDefaultCompareSlots();renderCompareView();}else{rebuildCitySelect();rebuildChartSelect();renderAll();}}';
		echo 'function drawRegChart(){if(!chartCv||typeof window.wsergoDrawRegressionScatter!=="function")return;var iid=chartSel?String(chartSel.value):"";var list=reg.univariate_full||[],m=null;for(var zi=0;zi<list.length;zi++){if(String((list[zi]||{}).id)===iid){m=list[zi];break;}}if(!m||!m.scatter)return;var hid=scope==="global"&&compareIds.length>=2?compareIds.map(function(x){return parseInt(x,10)||0;}):(parseInt(s.value,10)||0);var hp=scope==="global"&&compareIds.length>=2?(UI.chart_highlight_multi||"")+" ":(UI.reg_chart_highlight||"")+" ";window.wsergoDrawRegressionScatter(chartCv,chartCap,m,hid,{xAxis:UI.reg_chart_x_axis||"x",yAxis:UI.reg_chart_y_axis||"E",highlightPrefix:hp});}';
		echo 'function renderAll(){render();drawRegChart();}';
		echo 'function loadScope(sc){if(sc==="country"){applyPack(packCountry,"country");return;}if(packGlobal){applyPack(packGlobal,"global");return;}if(!UI.ajax_url||!UI.nonce){return;}if(regSum){regSum.textContent=UI.loading||"";}if(compareBody){compareBody.innerHTML="<p class=\\"wsergo-cmp-placeholder\\">"+esc(UI.loading||"")+"</p>";}fetch(UI.ajax_url,{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:new URLSearchParams({action:"wsergo_city_explorer_payload",scope:"global",nonce:UI.nonce})}).then(function(res){return res.json();}).then(function(data){if(!data||!data.success){throw new Error("load");}packGlobal=data.data;applyPack(packGlobal,"global");}).catch(function(){if(regSum){regSum.textContent=UI.load_error||"";}if(compareBody){compareBody.innerHTML="<p class=\\"wsergo-cmp-error\\">"+esc(UI.load_error||"")+"</p>";}});}';
		echo 's.addEventListener("change",renderAll);';
		echo 'if(chartSel){chartSel.addEventListener("change",drawRegChart);}';
		echo 'var scopeRadios=r.querySelectorAll("input[name=\\""+u+"-scope\\"]");for(var si=0;si<scopeRadios.length;si++){scopeRadios[si].addEventListener("change",function(ev){if(ev.target.checked){loadScope(ev.target.value);}});}';

		echo 'setExplorerMode();renderAll();';
		echo '})();';
		echo '</script>';
		echo '</div>';
	}
}
