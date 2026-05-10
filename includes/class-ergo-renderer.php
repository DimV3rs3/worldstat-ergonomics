<?php
/**
 * Вкладка страны и блок на странице города.
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_Renderer {

	/** @var bool */
	private static $city_leaf_explorer_assets_printed = false;

	/** @var bool */
	private static $city_leaf_explorer_inline_reg_css_printed = false;

	/**
	 * Точность вывода сырого значения по техническому ключу (как в прежней вёрстке).
	 */
	private static function macro_raw_metric_decimals( string $sig ): int {
		$sig = sanitize_key( $sig );
		if ( preg_match( '/__(cnt|psn)$/', $sig ) || preg_match( '/net_migration/', $sig ) ) {
			return 0;
		}
		if ( strpos( $sig, 'dens' ) !== false ) {
			return 4;
		}
		if ( preg_match( '/_01$|big_city_ratio|dependency|pressure/', $sig ) ) {
			return 3;
		}
		return 2;
	}

	/**
	 * Стили для страницы города (бейдж и карточка E).
	 */
	public static function enqueue_public_assets(): void {
		$platform_handle = 'worldstat-platform';
		$deps_country    = array();
		if ( wp_style_is( $platform_handle, 'registered' ) || wp_style_is( $platform_handle, 'enqueued' ) ) {
			$deps_country[] = $platform_handle;
		}
		// Страница записи страны в каталоге + любые экраны с блоком города/регрессии (подменю платформы без singular CPT).
		$need_country_css = class_exists( 'WorldStat_Country_CPT' ) && is_singular( WorldStat_Country_CPT::SLUG );
		if ( ! $need_country_css && class_exists( 'WSCities_CPT' ) && is_singular( WSCities_CPT::SLUG ) ) {
			$need_country_css = true;
		}
		if ( $need_country_css ) {
			wp_enqueue_style(
				'wsergo-country-public',
				WSERGO_URL . 'assets/css/ergo-country-public.css',
				$deps_country,
				WSERGO_VERSION
			);
		}
		if ( ! class_exists( 'WSCities_CPT' ) || ! is_singular( WSCities_CPT::SLUG ) ) {
			return;
		}
		wp_enqueue_style(
			'wsergo-city-public',
			WSERGO_URL . 'assets/css/city-ergo-public.css',
			$deps_country,
			WSERGO_VERSION
		);
	}

	/**
	 * Компактная карточка под сеткой показателей (хук wsp_city_after_stats).
	 *
	 * @param int   $post_id ID wsp_city.
	 * @param array $meta    Мета без префикса wscity_.
	 */
	public static function render_city_compact_card( int $post_id, array $meta ): void {
		unset( $meta );
		if ( ! class_exists( 'WSErgo_Data' ) || ! class_exists( 'WSErgo_City_Bridge' ) ) {
			return;
		}
		$show = WSErgo_City_Bridge::is_city_import_ergo_enabled() || count( WSErgo_CPT::get_districts_for_city( $post_id ) ) > 0;
		if ( ! $show ) {
			return;
		}
		$idx = WSErgo_Data::get_city_ergo_index( $post_id );
		$ver = get_option( 'wsergo_methodology_version', '1.2' );

		if ( $idx !== null && $idx > 0 ) {
		?>
			<section class="wsergo-city-ergo-card" aria-label="<?php esc_attr_e( 'Индекс эргономичности', 'worldstat-ergonomics' ); ?>">
				<div class="wsergo-city-ergo-card__head">
					<h2 class="wsergo-city-ergo-card__title"><?php esc_html_e( 'Эргономичность городской среды', 'worldstat-ergonomics' ); ?></h2>
					<div class="wsergo-city-ergo-card__e">
						<span class="wsergo-city-ergo-card__e-val"><?php echo esc_html( (string) $idx ); ?></span>
						<span class="wsergo-city-ergo-card__e-unit"><?php esc_html_e( 'E', 'worldstat-ergonomics' ); ?></span>
					</div>
				</div>
				<p class="wsergo-city-ergo-card__meta">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: methodology version */
							__( 'Сводный индекс по методике v%s (шкала 0–100).', 'worldstat-ergonomics' ),
							$ver
						)
					);
					?>
				</p>
			</section>
			<?php
			return;
		}

		if ( WSErgo_City_Bridge::is_city_import_ergo_enabled() ) {
			?>
			<section class="wsergo-city-ergo-card wsergo-city-ergo-card--muted" aria-label="<?php esc_attr_e( 'Эргономичность', 'worldstat-ergonomics' ); ?>">
				<div class="wsergo-city-ergo-card__head">
					<h2 class="wsergo-city-ergo-card__title"><?php esc_html_e( 'Эргономичность городской среды', 'worldstat-ergonomics' ); ?></h2>
				</div>
				<p class="wsergo-city-ergo-card__meta">
					<?php esc_html_e( 'Индекс E пока не рассчитан: проверьте сопоставление полей города в настройках эргономики и включите расчёт на странице «Города».', 'worldstat-ergonomics' ); ?>
				</p>
			</section>
			<?php
		}
	}

	/**
	 * Шорткод: индекс E для текущего или указанного города.
	 *
	 * Примеры: [wsergo_city_e] на singular wsp_city; [wsergo_city_e id="123"].
	 *
	 * @param array<string, string>|string $atts Атрибуты шорткода.
	 */
	public static function shortcode_city_e( $atts ): string {
		if ( ! class_exists( 'WSErgo_Data' ) ) {
			return '';
		}
		$a = shortcode_atts(
			[
				'id'    => '0',
				'class' => 'wsergo-shortcode-e',
			],
			is_array( $atts ) ? $atts : [],
			'wsergo_city_e'
		);
		$cid = (int) $a['id'];
		if ( $cid <= 0 && class_exists( 'WSCities_CPT' ) && is_singular( WSCities_CPT::SLUG ) ) {
			$cid = get_the_ID();
		}
		if ( $cid <= 0 ) {
			return '';
		}
		$idx = WSErgo_Data::get_city_ergo_index( $cid );
		if ( $idx === null || $idx <= 0 ) {
			return '';
		}
		$cls = sanitize_html_class( $a['class'] );
		return '<span class="' . esc_attr( $cls ) . '" title="' . esc_attr__( 'Индекс эргономичности, 0–100', 'worldstat-ergonomics' ) . '"><abbr title="' . esc_attr__( 'Эргономичность', 'worldstat-ergonomics' ) . '">E</abbr>&nbsp;' . esc_html( (string) $idx ) . '</span>';
	}

	/**
	 * Вкладка «Эргономичность» на странице страны.
	 *
	 * @param string $country_code ISO2.
	 */
	public static function render_country_tab( string $country_code ): void {
		$iso2 = strtoupper( $country_code );
		$idx  = WSErgo_Data::get_country_ergo_index( $iso2 );
		$dc   = WSErgo_Data::get_country_districts_count( $iso2 );
		$bc   = WSErgo_Data::get_country_buildings_count( $iso2 );

		$ver = get_option( 'wsergo_methodology_version', '1.0' );
		$agg = class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::get_aggregation() : [];
		$country_variation = class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::get_country_variation() : 'city_direct';
		$index_source      = class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::get_country_index_source() : 'macro_datasets';
		$country_mode_label = __( 'режим 1 (городской): напрямую по городам (население T3)', 'worldstat-ergonomics' );
		$macro_y            = class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::get_macro_reference_year() : 2022;
		if ( 'macro_datasets' === $index_source ) {
			$country_mode_label = sprintf(
				/* translators: %d: reference year */
				__( 'данные CSV платформы (опорный год %d, без агрегации по городам)', 'worldstat-ergonomics' ),
				(int) $macro_y
			);
		} elseif ( 'regions' === $country_variation ) {
			$country_mode = isset( $agg['region_to_country'] ) ? (string) $agg['region_to_country'] : 'pop_weighted';
			$country_mode_label = 'mean' === $country_mode
				? __( 'режим 2 (страновой): через регионы, среднее по регионам', 'worldstat-ergonomics' )
				: __( 'режим 2 (страновой): через регионы, взвешивание по населению', 'worldstat-ergonomics' );
		}

		$macro_dual = 'macro_datasets' === $index_source && class_exists( 'WSErgo_Country_Macro_Calculator' );
		$macro_country_detail = null;
		$macro_city_detail    = null;
		if ( $macro_dual ) {
			$macro_country_detail = WSErgo_Country_Macro_Calculator::get_country_macro_detail( $iso2 );
			$macro_city_detail    = WSErgo_Country_Macro_Calculator::get_city_macro_detail( $iso2 );
		}

		if ( $macro_dual ) {
			$tab_root = 'wsergo-ergo-country-' . ( function_exists( 'wp_unique_id' ) ? wp_unique_id() : uniqid( '', true ) );
			$idx_city_tile = '—';
			if ( is_array( $macro_city_detail ) && isset( $macro_city_detail['scores']['E'] ) && is_finite( (float) $macro_city_detail['scores']['E'] ) && (float) $macro_city_detail['scores']['E'] > 0 ) {
				$idx_city_tile = (string) round( (float) $macro_city_detail['scores']['E'], 2 );
			}
			$city_macro_y = class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::get_city_macro_reference_year() : 2022;
			$ver_city     = class_exists( 'WSErgo_Settings' ) ? (string) get_option( WSErgo_Settings::OPTION_CITY_METHODOLOGY_VERSION, '1.2' ) : '1.2';
			?>
			<div class="wsergo-ergo-country-macro-scope" id="<?php echo esc_attr( $tab_root ); ?>">
				<div class="wsergo-wsp-tab-nav" role="tablist" aria-label="<?php esc_attr_e( 'Режим эргономичности страны', 'worldstat-ergonomics' ); ?>">
					<button type="button" class="wsergo-wsp-tab-btn is-active" role="tab" aria-selected="true" data-wsergo-ergo-country-scope="country"><?php esc_html_e( 'Страна', 'worldstat-ergonomics' ); ?></button>
					<button type="button" class="wsergo-wsp-tab-btn" role="tab" aria-selected="false" data-wsergo-ergo-country-scope="city"><?php esc_html_e( 'Город', 'worldstat-ergonomics' ); ?></button>
				</div>
				<div class="wsergo-wsp-tab-panel is-active wsergo-ergo-country-pane" data-wsergo-ergo-country-pane="country">
					<?php
					WorldStat_UI::stats_grid(
						[
							[
								'label' => __( 'Индекс эргономичности', 'worldstat-ergonomics' ),
								'value' => $idx > 0 ? (string) $idx : '—',
								'icon'  => 'admin-home',
							],
							[
								'label' => __( 'Кварталов', 'worldstat-ergonomics' ),
								'value' => (string) $dc,
								'icon'  => 'location-alt',
							],
							[
								'label' => __( 'Зданий', 'worldstat-ergonomics' ),
								'value' => (string) $bc,
								'icon'  => 'building',
							],
						],
						[ 'columns' => 3 ]
					);
					if ( is_array( $macro_country_detail ) ) {
						self::render_country_macro_ergo_panel( $macro_country_detail, 'country' );
					}
					$explain_country = sprintf(
						/* translators: 1: data source label, 2: methodology version */
						__( 'Индекс страны в первой плитке — %1$s. Ниже: индексы городов по кварталам или импорту Cities (отдельно от сводного индекса по CSV страны). Методика v%2$s.', 'worldstat-ergonomics' ),
						esc_html( $country_mode_label ),
						esc_html( $ver )
					);
					WorldStat_UI::text_block( [ 'content' => $explain_country ] );
					self::render_country_ergo_regions_and_cities_tables( $iso2, $tab_root . '-country' );
					?>
				</div>
				<div class="wsergo-wsp-tab-panel wsergo-ergo-country-pane" data-wsergo-ergo-country-pane="city">
					<?php
					WorldStat_UI::stats_grid(
						[
							[
								'label' => __( 'Индекс эргономичности', 'worldstat-ergonomics' ),
								'value' => $idx_city_tile,
								'icon'  => 'admin-home',
							],
							[
								'label' => __( 'Кварталов', 'worldstat-ergonomics' ),
								'value' => (string) $dc,
								'icon'  => 'location-alt',
							],
							[
								'label' => __( 'Зданий', 'worldstat-ergonomics' ),
								'value' => (string) $bc,
								'icon'  => 'building',
							],
						],
						[ 'columns' => 3 ]
					);
					if ( is_array( $macro_city_detail ) ) {
						self::render_country_macro_ergo_panel( $macro_city_detail, 'city' );
					}
					$explain_city = sprintf(
						/* translators: 1: reference year, 2: city methodology version */
						__( 'Первая плитка — сводный индекс по CSV для строки страны с параметрами из «Эргономичность города» (опорный год %1$d). Таблица ниже — индекс E каждого города из кварталов или импорта (как на вкладке «Страна»). Методика города v%2$s.', 'worldstat-ergonomics' ),
						(int) $city_macro_y,
						esc_html( $ver_city )
					);
					WorldStat_UI::text_block( [ 'content' => $explain_city ] );
					self::render_country_ergo_regions_and_cities_tables( $iso2, $tab_root . '-city' );
					?>
				</div>
			</div>
			<script>
			(function(){
				var root = document.getElementById(<?php echo wp_json_encode( $tab_root ); ?>);
				if (!root) return;
				var key = 'wsergo_ergo_country_tab_scope';
				function applyScope(mode) {
					root.querySelectorAll('[data-wsergo-ergo-country-scope]').forEach(function(btn){
						var on = btn.getAttribute('data-wsergo-ergo-country-scope') === mode;
						btn.classList.toggle('is-active', on);
						btn.setAttribute('aria-selected', on ? 'true' : 'false');
					});
					root.querySelectorAll('.wsergo-ergo-country-pane').forEach(function(pane){
						var on = pane.getAttribute('data-wsergo-ergo-country-pane') === mode;
						pane.classList.toggle('is-active', on);
					});
					try { localStorage.setItem(key, mode); } catch (e) {}
				}
				root.querySelectorAll('[data-wsergo-ergo-country-scope]').forEach(function(btn){
					btn.addEventListener('click', function(){
						applyScope(btn.getAttribute('data-wsergo-ergo-country-scope'));
					});
				});
				var saved = '';
				try { saved = localStorage.getItem(key) || ''; } catch (e) {}
				if (saved === 'city') applyScope('city');
			})();
			</script>
			<?php
			return;
		}

		WorldStat_UI::stats_grid(
			[
				[
					'label' => __( 'Индекс эргономичности', 'worldstat-ergonomics' ),
					'value' => $idx > 0 ? (string) $idx : '—',
					'icon'  => 'admin-home',
				],
				[
					'label' => __( 'Кварталов', 'worldstat-ergonomics' ),
					'value' => (string) $dc,
					'icon'  => 'location-alt',
				],
				[
					'label' => __( 'Зданий', 'worldstat-ergonomics' ),
					'value' => (string) $bc,
					'icon'  => 'building',
				],
			],
			[ 'columns' => 3 ]
		);

		$explain = sprintf(
			/* translators: 1: country aggregation mode, 2: version */
			__( 'Индекс страны — с учётом правила «Регионы → страна» (%1$s); индекс города — по кварталам (модель DSL или взвешенное среднее по шести измерениям). Регион — по полю «регион» у города. Методика v%2$s.', 'worldstat-ergonomics' ),
			esc_html( $country_mode_label ),
			esc_html( $ver )
		);
		WorldStat_UI::text_block(
			[
				'content' => $explain,
			]
		);

		$explorer_uid = function_exists( 'wp_unique_id' ) ? wp_unique_id( 'wsergo-ergo-explorer-' ) : ( 'wsergo-ergo-explorer-' . uniqid( '', false ) );
		self::render_country_ergo_regions_and_cities_tables( $iso2, $explorer_uid );
	}

	/**
	 * Подключить стили блока страны/городов при выводе исследователя (вкладка платформы часто не is_singular страны).
	 */
	private static function enqueue_country_public_styles_for_explorer(): void {
		if ( wp_style_is( 'wsergo-country-public', 'enqueued' ) || wp_style_is( 'wsergo-country-public', 'done' ) ) {
			return;
		}
		$deps = array();
		if ( wp_style_is( 'worldstat-platform', 'registered' ) || wp_style_is( 'worldstat-platform', 'enqueued' ) ) {
			$deps[] = 'worldstat-platform';
		}
		wp_enqueue_style(
			'wsergo-country-public',
			WSERGO_URL . 'assets/css/ergo-country-public.css',
			$deps,
			WSERGO_VERSION
		);
	}

	/**
	 * Инлайн-стили графика регрессии (если enqueue до конца страницы не сработал).
	 */
	private static function print_city_leaf_explorer_reg_inline_css_once(): void {
		if ( self::$city_leaf_explorer_inline_reg_css_printed ) {
			return;
		}
		self::$city_leaf_explorer_inline_reg_css_printed = true;
		echo '<style id="wsergo-regression-chart-inline-css">';
		echo '.wsergo-reg-glossary__title{margin:16px 0 8px;font-size:14px;font-weight:600;color:#111827;}';
		echo '.wsergo-reg-glossary{margin:0 0 16px;padding:12px 14px;font-size:13px;line-height:1.55;color:#374151;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;display:grid;grid-template-columns:minmax(5rem,auto) 1fr;gap:6px 14px;align-items:start;}';
		echo '.wsergo-reg-glossary dt{margin:0;font-weight:600;color:#1f2937;}';
		echo '.wsergo-reg-glossary dd{margin:0;}';
		echo '.wsergo-reg-r2-chart{margin:20px 0;padding:14px 16px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;}';
		echo '.wsergo-reg-r2-chart__title{margin:0 0 12px;font-size:14px;font-weight:600;color:#111827;}';
		echo '.wsergo-reg-r2-chart__row{display:grid;grid-template-columns:minmax(100px,1fr) minmax(120px,3fr) auto;gap:10px 12px;align-items:center;margin-bottom:10px;font-size:13px;}';
		echo '.wsergo-reg-r2-chart__row:last-child{margin-bottom:0;}';
		echo '.wsergo-reg-r2-chart__label{color:#1f2937;word-break:break-word;}';
		echo '.wsergo-reg-r2-chart__bar-outer{height:16px;background:#f3f4f6;border-radius:6px;overflow:hidden;min-width:48px;border:1px solid #e5e7eb;}';
		echo '.wsergo-reg-r2-chart__bar-inner{height:100%;min-width:3px;border-radius:6px;background:linear-gradient(90deg,#15803d,#22c55e);box-sizing:border-box;}';
		echo '.wsergo-reg-r2-chart__val{font-variant-numeric:tabular-nums;font-weight:600;color:#111827;min-width:3.5rem;text-align:right;}';
		echo '.wsergo-reg-indicator-desc__title{margin:20px 0 10px;font-size:14px;font-weight:600;color:#111827;}';
		echo '.wsergo-reg-indicator-desc{display:flex;flex-direction:column;gap:14px;}';
		echo '.wsergo-reg-indicator-desc__item{padding:12px 14px;background:#f9fafb;border-radius:8px;border-left:4px solid #15803d;}';
		echo '.wsergo-reg-indicator-desc__name{font-weight:600;font-size:14px;margin-bottom:6px;color:#111827;}';
		echo '.wsergo-reg-indicator-desc__text{margin:0;font-size:13px;line-height:1.6;color:#374151;}';
		echo '</style>';
	}

	/**
	 * Один раз на страницу: строки для JS и обработчик выбора города.
	 */
	private static function print_city_leaf_explorer_assets_once(): void {
		if ( self::$city_leaf_explorer_assets_printed ) {
			return;
		}
		self::$city_leaf_explorer_assets_printed = true;
		self::enqueue_country_public_styles_for_explorer();
		self::print_city_leaf_explorer_reg_inline_css_once();
		$l10n = [
			'chooseCity'      => __( '— Выберите город —', 'worldstat-ergonomics' ),
			'cityIndexE'      => __( 'Индекс E (кварталы или импорт)', 'worldstat-ergonomics' ),
			'leafE'           => __( 'Листовой E (по карте полей)', 'worldstat-ergonomics' ),
			'axesTitle'       => __( 'Шесть осей (0–100)', 'worldstat-ergonomics' ),
			'indTitle'        => __( 'Показатели по карте полей', 'worldstat-ergonomics' ),
			'colIndicator'    => __( 'Показатель', 'worldstat-ergonomics' ),
			'colSource'       => __( 'Источник', 'worldstat-ergonomics' ),
			'colRaw'          => __( 'Сырое', 'worldstat-ergonomics' ),
			'colScore'        => __( '0–100', 'worldstat-ergonomics' ),
			'noDetail'        => __( 'Нет данных для выбранного города.', 'worldstat-ergonomics' ),
			'cityPage'        => __( 'Страница города', 'worldstat-ergonomics' ),
			'notice'          => __( 'Примечание', 'worldstat-ergonomics' ),
			'regTitle'        => __( 'Регрессия листового E на баллы 0–100', 'worldstat-ergonomics' ),
			'regIntro'        => __( 'По городам страны в этой выборке для каждого показателя оценивается простая линейная связь листового E с нормализованным баллом (Y ≈ a + b·X). Ниже — расшифровка столбцов, таблица коэффициентов, диаграмма R² и текстовые описания показателей из глобальных определений. Чем выше R², тем сильнее линейное сходство в выборке; это не причинная модель.', 'worldstat-ergonomics' ),
			'regMeta'         => __( 'Городов с листовым E в выборке: %1$s. Медиана E: %2$s.', 'worldstat-ergonomics' ),
			'regColN'         => __( 'N', 'worldstat-ergonomics' ),
			'regColMeanX'     => __( 'Среднее X', 'worldstat-ergonomics' ),
			'regColMeanY'     => __( 'Среднее E', 'worldstat-ergonomics' ),
			'regColIntercept' => __( 'a', 'worldstat-ergonomics' ),
			'regColSlope'     => __( 'Наклон b', 'worldstat-ergonomics' ),
			'regColR2'        => __( 'R²', 'worldstat-ergonomics' ),
			'regColR'         => __( 'r', 'worldstat-ergonomics' ),
			'regGlossaryTitle' => __( 'Расшифровка столбцов таблицы', 'worldstat-ergonomics' ),
			'regGlN'          => __( 'Число городов страны, для которых одновременно известны листовой E и нормализованный балл этого показателя (0–100).', 'worldstat-ergonomics' ),
			'regGlMeanX'      => __( 'Среднее арифметическое баллов показателя по этим городам.', 'worldstat-ergonomics' ),
			'regGlMeanY'      => __( 'Среднее арифметическое листового E по тем же городам.', 'worldstat-ergonomics' ),
			'regGlIntercept'  => __( 'Свободный член a линейной модели E ≈ a + b·X (ожидаемое E при нулевом X не интерпретировать буквально — X в диапазоне 0–100).', 'worldstat-ergonomics' ),
			'regGlSlope'      => __( 'Наклон b: на сколько баллов E меняется при увеличении нормализованного показателя на 1 при линейной аппроксимации.', 'worldstat-ergonomics' ),
			'regGlR'          => __( 'Коэффициент корреляции Пирсона между баллом показателя и листовым E (−1…1).', 'worldstat-ergonomics' ),
			'regGlR2'         => __( 'Коэффициент детерминации R²: доля разброса E, которую линейная модель «объясняет» различием баллов показателя по городам (0…1). Не путать с причинностью.', 'worldstat-ergonomics' ),
			'regChartTitle'   => __( 'Относительная сила связи (R²) по показателям', 'worldstat-ergonomics' ),
			'regDescTitle'    => __( 'Описание показателей из таблицы', 'worldstat-ergonomics' ),
			'regCaption'      => __( 'Рекомендации строятся по шести критериям (оси), показателям карты полей, перцентилям выборки городов страны и коэффициентам регрессии; это не индивидуальный прогноз причин.', 'worldstat-ergonomics' ),
			'recTitle'        => __( 'Рекомендации', 'worldstat-ergonomics' ),
			'recCritDimUnknown' => __( 'листовой показатель', 'worldstat-ergonomics' ),
			'recLeafBelow'    => __( 'Листовой E: %1$s при медиане выборки городов страны %2$s (отклонение %3$s). Ниже — пункты по шести критериям (осями эргономичности) и по показателям карты полей с учётом перцентилей и регрессии.', 'worldstat-ergonomics' ),
			'recLeafAbove'    => __( 'Листовой E: %1$s выше медианы выборки (%2$s, +%3$s). Сохраняйте сильные стороны по критериям; точечные улучшения — по таблице регрессии.', 'worldstat-ergonomics' ),
			'recIndHigherTail' => __( 'Критерий «%1$s». Показатель «%2$s» (нормализация «больше сырое — лучше балл»): значение %3$s ниже нижнего квартиля страны (%4$s; медиана балла по выборке %5$s). По регрессии листового E на балл: R²=%6$s, наклон b=%7$s.', 'worldstat-ergonomics' ),
			'recIndLowerTail' => __( 'Критерий «%1$s». Показатель «%2$s» (нормализация «меньше сырое — лучше балл»): значение %3$s выше верхнего квартиля (%4$s; медиана %5$s). По регрессии: R²=%6$s, b=%7$s.', 'worldstat-ergonomics' ),
			'recIndHigherWeak' => __( 'Критерий «%1$s». «%2$s»: балл %3$s ниже медианы страны (%4$s), без попадания в нижний квартиль. R²=%5$s, b=%6$s — при относительно низком листовом E имеет смысл двигаться к медиане балла по выборке.', 'worldstat-ergonomics' ),
			'recIndLowerWeak' => __( 'Критерий «%1$s». «%2$s»: балл %3$s выше медианы страны (%4$s). R²=%5$s, b=%6$s — перегрузка по признаку относительно типичных городов; снижение сырого значения повышает балл и по выборке связано с E.', 'worldstat-ergonomics' ),
			'recAxisCritLow'  => __( 'Критерий «%1$s» (ось эргономичности): %2$s баллов — ниже нижнего квартиля по стране (%3$s; медиана по оси %4$s). Усиление факторов этого измерения по методике поддерживает листовой E.', 'worldstat-ergonomics' ),
			'recDriversHeader' => __( 'По регрессии (связь балла показателя с листовым E в выборке); балл города ниже медианы — потенциал роста:', 'worldstat-ergonomics' ),
			'recDriverLine'   => __( '• «%1$s» — критерий «%2$s»: R²=%3$s, b=%4$s.', 'worldstat-ergonomics' ),
			'recNone'         => __( 'Явных отклонений по нижнему/верхнему квартилям нет; ориентируйтесь на таблицу регрессии и на приоритетные критерии из методики. При малом числе городов в стране выводы условны.', 'worldstat-ergonomics' ),
			'recFallbackNote' => __( 'Карта полей для города пуста; рекомендации строятся только по осям и листовому E.', 'worldstat-ergonomics' ),
		];
		?>
		<script>
		(function(){
			if (window.wsergoCityLeafExplorerInit) { return; }
			window.wsergoCityLeafExplorerInit = true;
			window.wsergoCityLeafExplorerL10n = <?php echo wp_json_encode( $l10n, JSON_UNESCAPED_UNICODE ); ?>;
			function esc(s) {
				return String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;');
			}
			function tr(str) {
				var s = String(str == null ? '' : str);
				for (var i = 1; i < arguments.length; i++) {
					s = s.replace(new RegExp('%' + i + '\\$s', 'g'), String(arguments[i]));
				}
				return s;
			}
			function fmtNum(v) {
				if (v == null || v === '' || !isFinite(Number(v))) { return '—'; }
				return String(Math.round(Number(v) * 100) / 100);
			}
			function fmtReg(v) {
				if (v == null || v === '' || !isFinite(Number(v))) { return '—'; }
				var x = Number(v);
				var t = Math.round(x * 10000) / 10000;
				return String(t);
			}
			function buildRecs(d, payload, L) {
				var R = payload.regression || {};
				if (!R.usable) {
					return [];
				}
				var uniFull = R.univariate_full || R.univariate || [];
				var uniMap = {};
				uniFull.forEach(function(u) {
					if (u && u.id) { uniMap[String(u.id)] = u; }
				});
				var peers = R.peer_stats || {};
				var axp = R.axis_peers || {};
				var labels = payload.axisLabels || {};
				var order = payload.dimensionOrder || [];
				var leaf = d.leaf_e != null ? parseFloat(String(d.leaf_e).replace(',', '.')) : NaN;
				var medL = R.median_leaf;
				var md = medL != null && isFinite(Number(medL)) ? Number(medL) : NaN;
				var leafLow = isFinite(leaf) && isFinite(md) && leaf < md - 2;
				var scored = [];

				function add(pri, text) {
					if (!text) { return; }
					scored.push({ p: pri, t: text });
				}

				if (isFinite(leaf) && isFinite(md)) {
					var diff = leaf - md;
					if (diff < -2) {
						add(10000 + Math.min(200, Math.round(-diff * 10)), tr(L.recLeafBelow, fmtNum(leaf), fmtNum(md), fmtNum(diff)));
					} else if (diff > 2) {
						add(8000, tr(L.recLeafAbove, fmtNum(leaf), fmtNum(md), fmtNum(diff)));
					}
				}

				(d.indicators || []).forEach(function(row) {
					var id = row.id;
					var sv = row.score_value;
					if (sv == null || !isFinite(Number(sv)) || !peers[id]) { return; }
					var p = peers[id];
					var dir = row.direction === 'lower_better' ? 'lower_better' : 'higher_better';
					var lab = row.label || id;
					var uni = uniMap[id];
					var dim = (uni && uni.dimension_label) ? uni.dimension_label : (L.recCritDimUnknown || '');
					var r2 = uni ? parseFloat(uni.r2) : NaN;
					var slope = uni ? parseFloat(uni.slope) : NaN;
					var r2s = isFinite(r2) ? fmtReg(r2) : '—';
					var bs = isFinite(slope) ? fmtReg(slope) : '—';
					var medX = p.median != null ? Number(p.median) : NaN;
					var q = isFinite(r2) ? Math.abs(r2) : 0.05;

					if (dir === 'higher_better') {
						if (Number(sv) < Number(p.p25)) {
							add(9000 + q * 200, tr(L.recIndHigherTail, dim, lab, fmtNum(sv), fmtNum(p.p25), isFinite(medX) ? fmtNum(medX) : '—', r2s, bs));
						} else if (leafLow && isFinite(medX) && Number(sv) < medX && Number(sv) >= Number(p.p25)) {
							add(5000 + q * 100, tr(L.recIndHigherWeak, dim, lab, fmtNum(sv), fmtNum(medX), r2s, bs));
						}
					} else {
						if (Number(sv) > Number(p.p75)) {
							add(9000 + q * 200, tr(L.recIndLowerTail, dim, lab, fmtNum(sv), fmtNum(p.p75), isFinite(medX) ? fmtNum(medX) : '—', r2s, bs));
						} else if (leafLow && isFinite(medX) && Number(sv) > medX && Number(sv) <= Number(p.p75)) {
							add(5000 + q * 100, tr(L.recIndLowerWeak, dim, lab, fmtNum(sv), fmtNum(medX), r2s, bs));
						}
					}
				});

				order.forEach(function(dim) {
					var v = d.axes && d.axes[dim];
					if (v == null || !isFinite(Number(v)) || !axp[dim]) { return; }
					var axName = labels[dim] || dim;
					var ap = axp[dim];
					var medAx = ap.median != null ? Number(ap.median) : NaN;
					if (Number(v) < Number(ap.p25)) {
						add(8500, tr(L.recAxisCritLow, axName, fmtNum(v), fmtNum(ap.p25), isFinite(medAx) ? fmtNum(medAx) : '—'));
					}
				});

				scored.sort(function(a, b) { return b.p - a.p; });
				var out = [];
				var seen = {};
				scored.forEach(function(it) {
					if (seen[it.t]) { return; }
					seen[it.t] = true;
					out.push(it.t);
				});

				if (leafLow && out.length < 4 && uniFull.length) {
					var drivers = [];
					var sortedU = uniFull.slice().sort(function(a, b) {
						var ra = parseFloat(a.r2); var rb = parseFloat(b.r2);
						return (isFinite(rb) ? Math.abs(rb) : 0) - (isFinite(ra) ? Math.abs(ra) : 0);
					});
					for (var si = 0; si < sortedU.length && drivers.length < 4; si++) {
						var uu = sortedU[si];
						if (!uu || !uu.id) { continue; }
						var row = null;
						(d.indicators || []).forEach(function(r) { if (r.id === uu.id) { row = r; } });
						if (!row || row.score_value == null || !isFinite(Number(row.score_value))) { continue; }
						var pv = peers[uu.id];
						if (!pv || pv.median == null) { continue; }
						var sl = parseFloat(uu.slope);
						if (!isFinite(sl) || Math.abs(sl) < 1e-8) { continue; }
						var sv = Number(row.score_value);
						var pmed = Number(pv.median);
						if (sl > 0 && sv < pmed) {
							drivers.push(tr(L.recDriverLine, uu.label || uu.id, uu.dimension_label || (L.recCritDimUnknown || ''), fmtReg(uu.r2), fmtReg(sl)));
						}
					}
					if (drivers.length) {
						out.push(L.recDriversHeader || '');
						drivers.forEach(function(line) { out.push(line); });
					}
				}

				if (out.length === 0) {
					out.push(L.recNone || '');
				}
				return out;
			}
			function appendRegressionBlock(panel, payload, L) {
				var R = payload.regression || {};
				var sec = document.createElement('div');
				sec.className = 'wsergo-city-leaf-explorer__sub wsergo-city-leaf-explorer__reg';
				var h = document.createElement('h4');
				h.className = 'wsergo-city-leaf-explorer__sub-title';
				h.textContent = L.regTitle || '';
				sec.appendChild(h);
				var intro = document.createElement('p');
				intro.className = 'wsergo-city-leaf-explorer__reg-intro wsp-muted';
				intro.textContent = L.regIntro || '';
				sec.appendChild(intro);
				if (!R.usable) {
					var px = document.createElement('p');
					px.className = 'wsp-muted';
					px.textContent = R.notice || '';
					sec.appendChild(px);
					panel.appendChild(sec);
					return;
				}
				var meta = document.createElement('p');
				meta.className = 'wsergo-city-leaf-explorer__reg-meta';
				meta.textContent = tr(L.regMeta, R.n_leaf, R.median_leaf != null ? fmtNum(R.median_leaf) : '—');
				sec.appendChild(meta);
				var glossH = document.createElement('h5');
				glossH.className = 'wsergo-reg-glossary__title';
				glossH.textContent = L.regGlossaryTitle || '';
				sec.appendChild(glossH);
				var glossDl = document.createElement('dl');
				glossDl.className = 'wsergo-reg-glossary';
				[
					[L.regColN, L.regGlN],
					[L.regColMeanX, L.regGlMeanX],
					[L.regColMeanY, L.regGlMeanY],
					[L.regColIntercept, L.regGlIntercept],
					[L.regColSlope, L.regGlSlope],
					[L.regColR, L.regGlR],
					[L.regColR2, L.regGlR2]
				].forEach(function(pair) {
					if (!pair[1]) { return; }
					var dt = document.createElement('dt');
					dt.textContent = pair[0] || '';
					var dd = document.createElement('dd');
					dd.textContent = pair[1];
					glossDl.appendChild(dt);
					glossDl.appendChild(dd);
				});
				sec.appendChild(glossDl);
				var u = R.univariate || [];
				if (u.length) {
					var wrap = document.createElement('div');
					wrap.className = 'wsp-table-wrap wsergo-city-leaf-explorer__table-wrap';
					var tbl = document.createElement('table');
					tbl.className = 'wsp-table wsergo-city-leaf-explorer__table';
					tbl.innerHTML = '<thead><tr><th>' + esc(L.colIndicator) + '</th><th>' + esc(L.regColN) + '</th><th>' + esc(L.regColMeanX) + '</th><th>' + esc(L.regColMeanY) + '</th><th>' + esc(L.regColIntercept) + '</th><th>' + esc(L.regColSlope) + '</th><th>' + esc(L.regColR) + '</th><th>' + esc(L.regColR2) + '</th></tr></thead><tbody></tbody>';
					var tb = tbl.querySelector('tbody');
					u.forEach(function(row) {
						var tr = document.createElement('tr');
						tr.innerHTML = '<td>' + esc(row.label) + '</td><td>' + esc(String(row.n)) + '</td><td>' + esc(fmtReg(row.mean_x)) + '</td><td>' + esc(fmtReg(row.mean_y)) + '</td><td>' + esc(fmtReg(row.intercept)) + '</td><td>' + esc(fmtReg(row.slope)) + '</td><td>' + esc(fmtReg(row.r)) + '</td><td>' + esc(fmtReg(row.r2)) + '</td>';
						tb.appendChild(tr);
					});
					wrap.appendChild(tbl);
					sec.appendChild(wrap);
					var chartSec = document.createElement('div');
					chartSec.className = 'wsergo-reg-r2-chart';
					var chartH = document.createElement('h5');
					chartH.className = 'wsergo-reg-r2-chart__title';
					chartH.textContent = L.regChartTitle || '';
					chartSec.appendChild(chartH);
					u.forEach(function(row) {
						var r2 = parseFloat(row.r2);
						if (!isFinite(r2)) { r2 = 0; }
						var rowEl = document.createElement('div');
						rowEl.className = 'wsergo-reg-r2-chart__row';
						var labEl = document.createElement('span');
						labEl.className = 'wsergo-reg-r2-chart__label';
						labEl.textContent = row.label || row.id || '';
						var barOuter = document.createElement('div');
						barOuter.className = 'wsergo-reg-r2-chart__bar-outer';
						var barIn = document.createElement('div');
						barIn.className = 'wsergo-reg-r2-chart__bar-inner';
						var pct = Math.min(100, Math.abs(r2) * 100);
						barIn.style.width = pct + '%';
						barOuter.appendChild(barIn);
						var valEl = document.createElement('span');
						valEl.className = 'wsergo-reg-r2-chart__val';
						valEl.textContent = fmtReg(r2);
						rowEl.appendChild(labEl);
						rowEl.appendChild(barOuter);
						rowEl.appendChild(valEl);
						chartSec.appendChild(rowEl);
					});
					sec.appendChild(chartSec);
					var descH = document.createElement('h5');
					descH.className = 'wsergo-reg-indicator-desc__title';
					descH.textContent = L.regDescTitle || '';
					sec.appendChild(descH);
					var descBox = document.createElement('div');
					descBox.className = 'wsergo-reg-indicator-desc';
					u.forEach(function(row) {
						if (!row.description) { return; }
						var art = document.createElement('div');
						art.className = 'wsergo-reg-indicator-desc__item';
						var hname = document.createElement('div');
						hname.className = 'wsergo-reg-indicator-desc__name';
						hname.textContent = row.label || row.id || '';
						var pd = document.createElement('p');
						pd.className = 'wsergo-reg-indicator-desc__text';
						pd.textContent = row.description;
						art.appendChild(hname);
						art.appendChild(pd);
						descBox.appendChild(art);
					});
					sec.appendChild(descBox);
				}
				var cap = document.createElement('p');
				cap.className = 'wsergo-city-leaf-explorer__reg-cap wsp-muted';
				cap.textContent = L.regCaption || '';
				sec.appendChild(cap);
				panel.appendChild(sec);
			}
			function appendRecsInto(host, d, payload, L, skipRecHeading) {
				var R0 = payload.regression || {};
				if (!R0.usable || !host) { return; }
				var items = buildRecs(d, payload, L);
				if (!items.length) { return; }
				var box = document.createElement('div');
				box.className = 'wsergo-city-leaf-explorer__rec-inline';
				if (!skipRecHeading) {
					var h = document.createElement('h5');
					h.className = 'wsergo-city-leaf-explorer__rec-inline-title';
					h.textContent = L.recTitle || '';
					box.appendChild(h);
				}
				var ul = document.createElement('ul');
				ul.className = 'wsergo-city-leaf-explorer__rec-list';
				items.forEach(function(t) {
					var li = document.createElement('li');
					li.textContent = t;
					ul.appendChild(li);
				});
				box.appendChild(ul);
				host.appendChild(box);
			}
			function buildPanel(wrap, cityId) {
				var panel = wrap.querySelector('.wsergo-city-leaf-explorer__panel');
				var jsonEl = wrap.querySelector('.wsergo-city-leaf-explorer__json');
				var L = window.wsergoCityLeafExplorerL10n || {};
				if (!panel || !jsonEl) { return; }
				panel.innerHTML = '';
				if (!cityId) {
					panel.setAttribute('hidden', 'hidden');
					return;
				}
				var payload;
				try { payload = JSON.parse(jsonEl.textContent || '{}'); } catch (e) { payload = { cities: {} }; }
				var d = (payload.cities || {})[String(cityId)];
				if (!d) {
					panel.innerHTML = '<p class="wsp-muted">' + esc(L.noDetail || '') + '</p>';
					panel.removeAttribute('hidden');
					return;
				}
				var h = document.createElement('div');
				h.className = 'wsergo-city-leaf-explorer__panel-head';
				var title = document.createElement('h4');
				title.className = 'wsergo-city-leaf-explorer__panel-title';
				title.textContent = d.name || '';
				h.appendChild(title);
				if (d.url) {
					var a = document.createElement('a');
					a.className = 'wsergo-city-leaf-explorer__city-link';
					a.href = d.url;
					a.textContent = L.cityPage || '';
					h.appendChild(a);
				}
				panel.appendChild(h);
				var eRow = document.createElement('p');
				eRow.className = 'wsergo-city-leaf-explorer__e-line';
				eRow.innerHTML = '<strong>' + esc(L.cityIndexE || '') + ':</strong> ' + esc(d.e != null ? d.e : '—') + ' &nbsp;|&nbsp; <strong>' + esc(L.leafE || '') + ':</strong> ' + esc(d.leaf_e != null ? d.leaf_e : '—');
				panel.appendChild(eRow);
				if (d.leaf_notice) {
					var note = document.createElement('p');
					note.className = 'wsergo-city-leaf-explorer__notice wsp-muted';
					var ns = document.createElement('strong');
					ns.textContent = (L.notice || '') + ': ';
					note.appendChild(ns);
					note.appendChild(document.createTextNode(d.leaf_notice));
					panel.appendChild(note);
				}
				var axes = d.axes || {};
				var order = payload.dimensionOrder || [];
				var labels = payload.axisLabels || {};
				var axKeys = Object.keys(axes);
				if (order.length && axKeys.length) {
					var axSec = document.createElement('div');
					axSec.className = 'wsergo-city-leaf-explorer__sub';
					var axH = document.createElement('h4');
					axH.className = 'wsergo-city-leaf-explorer__sub-title';
					axH.textContent = L.axesTitle || '';
					axSec.appendChild(axH);
					var axWrap = document.createElement('div');
					axWrap.className = 'wsp-table-wrap wsergo-city-leaf-explorer__table-wrap';
					var axTbl = document.createElement('table');
					axTbl.className = 'wsp-table wsergo-city-leaf-explorer__table';
					var thead = document.createElement('thead');
					var hr = document.createElement('tr');
					hr.innerHTML = '<th>' + esc(L.colIndicator || '') + '</th><th>' + esc(L.colScore || '') + '</th>';
					thead.appendChild(hr);
					axTbl.appendChild(thead);
					var tb = document.createElement('tbody');
					order.forEach(function(dim) {
						if (axes[dim] == null || axes[dim] === '') { return; }
						var tr = document.createElement('tr');
						var lab = labels[dim] || dim;
						tr.innerHTML = '<td>' + esc(lab) + '</td><td>' + esc(String(axes[dim])) + '</td>';
						tb.appendChild(tr);
					});
					axTbl.appendChild(tb);
					axWrap.appendChild(axTbl);
					axSec.appendChild(axWrap);
					panel.appendChild(axSec);
				}
				var inds = d.indicators || [];
				if (inds.length) {
					var iSec = document.createElement('div');
					iSec.className = 'wsergo-city-leaf-explorer__sub';
					var iH = document.createElement('h4');
					iH.className = 'wsergo-city-leaf-explorer__sub-title';
					iH.textContent = L.indTitle || '';
					iSec.appendChild(iH);
					var iWrap = document.createElement('div');
					iWrap.className = 'wsp-table-wrap wsergo-city-leaf-explorer__table-wrap';
					var tbl = document.createElement('table');
					tbl.className = 'wsp-table wsergo-city-leaf-explorer__table';
					tbl.innerHTML = '<thead><tr><th>' + esc(L.colIndicator || '') + '</th><th>' + esc(L.colSource || '') + '</th><th>' + esc(L.colRaw || '') + '</th><th>' + esc(L.colScore || '') + '</th></tr></thead><tbody></tbody>';
					var body = tbl.querySelector('tbody');
					inds.forEach(function(row) {
						var tr = document.createElement('tr');
						tr.innerHTML = '<td>' + esc(row.label || row.id) + '</td><td><code class="wsergo-city-leaf-explorer__code">' + esc(row.source) + '</code></td><td>' + esc(row.raw != null ? row.raw : '—') + '</td><td>' + esc(row.score != null ? row.score : '—') + '</td>';
						body.appendChild(tr);
					});
					iWrap.appendChild(tbl);
					iSec.appendChild(iWrap);
					appendRecsInto(iSec, d, payload, L, false);
					panel.appendChild(iSec);
				} else {
					var Rf = payload.regression || {};
					if (Rf.usable) {
						var recSec = document.createElement('div');
						recSec.className = 'wsergo-city-leaf-explorer__sub wsergo-city-leaf-explorer__rec-fallback';
						var recH = document.createElement('h4');
						recH.className = 'wsergo-city-leaf-explorer__sub-title';
						recH.textContent = L.recTitle || '';
						recSec.appendChild(recH);
						var recNote = document.createElement('p');
						recNote.className = 'wsp-muted wsergo-city-leaf-explorer__rec-fallback-note';
						recNote.textContent = L.recFallbackNote || '';
						recSec.appendChild(recNote);
						appendRecsInto(recSec, d, payload, L, true);
						panel.appendChild(recSec);
					}
				}
				appendRegressionBlock(panel, payload, L);
				panel.removeAttribute('hidden');
			}
			document.addEventListener('change', function(ev) {
				var t = ev.target;
				if (!t || !t.classList || !t.classList.contains('wsergo-city-leaf-explorer__select')) { return; }
				var wrap = t.closest('.wsergo-city-leaf-explorer');
				if (!wrap) { return; }
				buildPanel(wrap, t.value);
			});
		})();
		</script>
		<?php
	}

	/**
	 * Регионы и города под текстом на вкладке эргономичности страны.
	 *
	 * @param string $iso2        ISO2 страны.
	 * @param string $explorer_uid Уникальный префикс DOM (несколько блоков на странице при подвкладках).
	 */
	private static function render_country_ergo_regions_and_cities_tables( string $iso2, string $explorer_uid = 'wsergo-ergo-explorer' ): void {
		$regions = WSErgo_Data::list_regions_for_country( $iso2 );
		if ( ! empty( $regions ) ) {
			$rrows = [];
			foreach ( $regions as $rn ) {
				$rix = WSErgo_Data::get_region_ergo_index( $iso2, $rn );
				$rrows[] = [
					esc_html( $rn ),
					$rix !== null && $rix > 0 ? (string) $rix : '—',
				];
			}
			if ( ! empty( $rrows ) ) {
				echo '<h3 class="wsp-section-title">' . esc_html__( 'Регионы (по данным городов)', 'worldstat-ergonomics' ) . '</h3>';
				WorldStat_UI::table(
					[
						'headers'    => [
							__( 'Регион', 'worldstat-ergonomics' ),
							__( 'Индекс', 'worldstat-ergonomics' ),
						],
						'rows'       => $rrows,
						'sortable'   => true,
						'searchable' => false,
					]
				);
			}
		}

		$cities = class_exists( 'WSCities_CPT' ) && method_exists( 'WSCities_CPT', 'get_cities_for_country' )
			? WSCities_CPT::get_cities_for_country( $iso2 )
			: [];
		if ( empty( $cities ) ) {
			echo '<p class="wsp-muted">' . esc_html__( 'Нет городов в базе для этой страны.', 'worldstat-ergonomics' ) . '</p>';
			return;
		}

		$rows           = [];
		$any_quarters   = false;
		foreach ( $cities as $c ) {
			$city_idx = WSErgo_Data::get_city_ergo_index( (int) $c['id'] );
			$dcount   = count( WSErgo_CPT::get_districts_for_city( (int) $c['id'] ) );
			if ( $dcount > 0 ) {
				$any_quarters = true;
			}
			$link   = get_permalink( $c['id'] );
			$name   = '<a href="' . esc_url( $link ) . '">' . esc_html( $c['name'] ) . '</a>';
			$rows[] = array(
				'name'   => $name,
				'e_idx'  => $city_idx !== null ? (string) $city_idx : '—',
				'dc'     => (string) $dcount,
			);
		}

		$table_rows = [];
		foreach ( $rows as $r ) {
			if ( $any_quarters ) {
				$table_rows[] = array( $r['name'], $r['e_idx'], $r['dc'] );
			} else {
				$table_rows[] = array( $r['name'], $r['e_idx'] );
			}
		}

		$headers = array(
			__( 'Город', 'worldstat-ergonomics' ),
			__( 'Индекс E', 'worldstat-ergonomics' ),
		);
		if ( $any_quarters ) {
			$headers[] = __( 'Кварталов', 'worldstat-ergonomics' );
		}

		$max_explorer = (int) apply_filters( 'wsergo_city_leaf_explorer_max_cities', 600 );
		$cities_expl  = $cities;
		usort(
			$cities_expl,
			static function ( $a, $b ) {
				return strcasecmp( (string) ( $a['name'] ?? '' ), (string) ( $b['name'] ?? '' ) );
			}
		);
		$truncated = false;
		if ( count( $cities_expl ) > $max_explorer ) {
			$cities_expl = array_slice( $cities_expl, 0, $max_explorer );
			$truncated    = true;
		}

		$city_payload = [];
		foreach ( $cities_expl as $c ) {
			$cid = (int) $c['id'];
			$city_payload[ (string) $cid ] = WSErgo_Data::get_city_leaf_public_detail( $cid );
		}

		$regression = class_exists( 'WSErgo_City_Regression' )
			? WSErgo_City_Regression::analyze_country_payload( $city_payload )
			: [ 'usable' => false, 'notice' => __( 'Модуль регрессии недоступен.', 'worldstat-ergonomics' ) ];

		$payload = [
			'axisLabels'     => WSErgo_Model::get_dimension_labels(),
			'dimensionOrder' => WSErgo_Model::DIMENSION_KEYS,
			'cities'         => $city_payload,
			'regression'     => $regression,
		];
		$json = wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP );
		if ( false === $json ) {
			$json = '{}';
		}

		self::print_city_leaf_explorer_assets_once();

		echo '<section class="wsp-section wsergo-city-leaf-explorer" id="' . esc_attr( $explorer_uid ) . '" aria-labelledby="' . esc_attr( $explorer_uid ) . '-title">';
		echo '<script type="application/json" class="wsergo-city-leaf-explorer__json">' . $json . '</script>';
		echo '<h3 class="wsp-section-title" id="' . esc_attr( $explorer_uid ) . '-title">' . esc_html__( 'Город: показатели, регрессия и рекомендации', 'worldstat-ergonomics' ) . '</h3>';
		echo '<p class="wsergo-city-leaf-explorer__intro">' . esc_html__( 'Выберите город в списке: таблицы показателей и осей, рекомендации по перцентилям внутри блока «Показатели по карте полей», затем регрессия листового E по выборке городов страны.', 'worldstat-ergonomics' ) . '</p>';
		if ( $truncated ) {
			echo '<p class="description wsergo-city-leaf-explorer__trunc">' . esc_html(
				sprintf(
					/* translators: %d: max cities in explorer */
					__( 'В списке только первые %d городов по алфавиту (ограничение производительности). Полный перечень — в таблице ниже.', 'worldstat-ergonomics' ),
					$max_explorer
				)
			) . '</p>';
		}
		echo '<div class="wsergo-city-leaf-explorer__controls">';
		echo '<label for="' . esc_attr( $explorer_uid ) . '-sel" class="wsergo-city-leaf-explorer__label">' . esc_html__( 'Город', 'worldstat-ergonomics' ) . '</label>';
		echo '<select id="' . esc_attr( $explorer_uid ) . '-sel" class="wsp-select wsergo-city-leaf-explorer__select">';
		echo '<option value="">' . esc_html__( '— Выберите город —', 'worldstat-ergonomics' ) . '</option>';
		foreach ( $cities_expl as $c ) {
			echo '<option value="' . esc_attr( (string) (int) $c['id'] ) . '">' . esc_html( (string) ( $c['name'] ?? '' ) ) . '</option>';
		}
		echo '</select></div>';
		echo '<div class="wsergo-city-leaf-explorer__panel" hidden></div>';
		echo '</section>';

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
	 * Блок после основого контента страницы города.
	 *
	 * @param int   $post_id ID записи wsp_city.
	 * @param array $meta    Мета города (ключи без префикса wscity_).
	 */
	public static function render_city_section( int $post_id, array $meta ): void {
		$districts = WSErgo_CPT::get_districts_for_city( $post_id );
		// Режим «только город» — карточка E выводится через wsp_city_after_stats; здесь только кварталы/карта.
		if ( empty( $districts ) ) {
			return;
		}

		$city_idx = WSErgo_Data::get_city_ergo_index( $post_id );
		$ver      = get_option( 'wsergo_methodology_version', '1.0' );

		echo '<div class="wsp-ergo-city-section wsp-container" style="margin-top:2rem;padding-top:2rem;border-top:1px solid var(--wsp-border,#e5e7eb);">';
		echo '<h2 class="wsp-section-title">' . esc_html__( 'Эргономичность: кварталы', 'worldstat-ergonomics' ) . '</h2>';

		WorldStat_UI::stats_grid(
			[
				[
					'label' => __( 'Индекс города (агрегация по кварталам)', 'worldstat-ergonomics' ),
					'value' => $city_idx !== null && $city_idx > 0 ? (string) $city_idx : '—',
					'icon'  => 'admin-home',
				],
				[
					'label' => __( 'Кварталов с оценкой', 'worldstat-ergonomics' ),
					'value' => (string) count( $districts ),
					'icon'  => 'location-alt',
				],
			],
			[ 'columns' => 2 ]
		);

		echo '<p class="description" style="margin:1rem 0;">' . esc_html(
			sprintf(
				/* translators: %s version */
				__( 'Шесть уровней оценки района: функциональность, безопасность, комфортность, обитаемость, освояемость, управляемость. Методика v%s.', 'worldstat-ergonomics' ),
				$ver
			)
		) . '</p>';

		if ( ! empty( $districts ) ) {
			$rows = [];
			foreach ( $districts as $d ) {
				$di = (float) get_post_meta( $d->ID, WSErgo_CPT::META_INDEX, true );
				$rows[] = [
					'<a href="' . esc_url( get_permalink( $d ) ) . '">' . esc_html( get_the_title( $d ) ) . '</a>',
					$di > 0 ? (string) $di : '—',
					(string) count( WSErgo_CPT::get_buildings_for_district( $d->ID ) ),
				];
			}

			WorldStat_UI::table(
				[
					'headers'    => [
						__( 'Квартал', 'worldstat-ergonomics' ),
						__( 'Индекс', 'worldstat-ergonomics' ),
						__( 'Зданий', 'worldstat-ergonomics' ),
					],
					'rows'       => $rows,
					'sortable'   => true,
					'searchable' => false,
				]
			);
		}

		$lat = (float) ( $meta['lat'] ?? 0 );
		$lng = (float) ( $meta['lng'] ?? 0 );
		if ( $lat && $lng ) {
			$local_markers = [];
			foreach ( $districts as $dist ) {
				foreach ( WSErgo_CPT::get_buildings_for_district( $dist->ID ) as $b ) {
					$la = (float) get_post_meta( $b->ID, WSErgo_CPT::META_LAT, true );
					$ln = (float) get_post_meta( $b->ID, WSErgo_CPT::META_LNG, true );
					if ( $la && $ln ) {
						$local_markers[] = [
							'lat'    => $la,
							'lng'    => $ln,
							'title'  => get_the_title( $b ),
							'popup'  => '<strong>' . esc_html( get_the_title( $b ) ) . '</strong>',
							'color'  => '#15803d',
							'radius' => 6,
						];
					}
				}
			}

			if ( ! empty( $local_markers ) ) {
				echo '<h3 class="wsp-section-title">' . esc_html__( 'Здания на карте', 'worldstat-ergonomics' ) . '</h3>';
				WorldStat_UI::map(
					[
						'lat'          => $lat,
						'lng'          => $lng,
						'zoom'         => 11,
						'height'       => 360,
						'grid'         => true,
						'markers'      => $local_markers,
						'tile_style'   => 'countries',
					]
				);
			}
		}

		echo '</div>';
	}

	/**
	 * Блок макроэргономики (макет как на обзоре страны): оси E, диагностика пропусков, сырые признаки по вкладкам.
	 *
	 * @param array<string,mixed> $detail get_country_macro_detail() или get_city_macro_detail().
	 * @param string              $scope  'country' — настройки страны; 'city' — настройки «Эргономичность города».
	 */
	private static function render_country_macro_ergo_panel( array $detail, string $scope = 'country' ): void {
		$scope = ( 'city' === $scope ) ? 'city' : 'country';
		$uid   = 'wsergo-macro-' . $scope . '-' . ( function_exists( 'wp_unique_id' ) ? wp_unique_id() : uniqid( '', true ) );

		$analysis_url = '';
		if ( class_exists( 'WorldStat_Pages' ) ) {
			$analysis_url = WorldStat_Pages::get_page_url( 'analysis' );
		}
		if ( ! $analysis_url ) {
			$analysis_url = home_url( '/analysis-data/' );
		}

		$raw        = isset( $detail['raw_row'] ) && is_array( $detail['raw_row'] ) ? $detail['raw_row'] : null;
		$scores     = isset( $detail['scores'] ) && is_array( $detail['scores'] ) ? $detail['scores'] : null;
		$year       = (int) ( $detail['year'] ?? 0 );
		$iso3       = (string) ( $detail['iso3'] ?? '' );

		$dn = static function ( string $k ) use ( $scope ): string {
			$k = sanitize_key( $k );
			if ( $k === '' ) {
				return '—';
			}
			if ( 'city' === $scope && class_exists( 'WSErgo_Settings' ) ) {
				$cl = WSErgo_Settings::get_city_data_labels_ru();
				if ( isset( $cl[ $k ] ) && (string) $cl[ $k ] !== '' ) {
					return (string) $cl[ $k ];
				}
				$defc = WSErgo_Settings::default_city_data_label_ru( $k );
				if ( $defc !== '' ) {
					return $defc;
				}
			}
			return class_exists( 'WSErgo_Country_Macro_Calculator' ) ? WSErgo_Country_Macro_Calculator::data_label_ru( $k ) : $k;
		};

		$fmt = static function ( $v, int $decimals = 2 ): string {
			if ( ! is_numeric( $v ) || ! is_finite( (float) $v ) ) {
				return '—';
			}
			return number_format( (float) $v, $decimals, ',', ' ' );
		};

		$axis_terms_detail = isset( $detail['axis_terms'] ) && is_array( $detail['axis_terms'] ) ? $detail['axis_terms'] : array();
		if ( empty( $axis_terms_detail ) && class_exists( 'WSErgo_Settings' ) ) {
			$axis_terms_detail = ( 'city' === $scope )
				? WSErgo_Settings::get_city_macro_axis_terms_resolved()
				: WSErgo_Settings::get_macro_axis_terms_resolved();
		}
		if ( empty( $axis_terms_detail ) && class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			$axis_terms_detail = WSErgo_Country_Macro_Calculator::default_macro_axis_terms();
		}
		$signal_axes_map = array();
		foreach ( $axis_terms_detail as $ax_k => $rows_ax ) {
			if ( ! is_array( $rows_ax ) ) {
				continue;
			}
			foreach ( $rows_ax as $tr_ax ) {
				$sig = sanitize_key( (string) ( $tr_ax['signal'] ?? '' ) );
				if ( $sig === '' ) {
					continue;
				}
				if ( ! isset( $signal_axes_map[ $sig ] ) ) {
					$signal_axes_map[ $sig ] = array();
				}
				$signal_axes_map[ $sig ][ $ax_k ] = true;
			}
		}
		ksort( $signal_axes_map, SORT_STRING );
		$n_formula_signals = count( $signal_axes_map );

		?>
		<div class="wsp-ergo-wrapper" style="margin-top:1.25rem;" id="<?php echo esc_attr( $uid ); ?>">
			<div class="wsp-ergo-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:10px;flex-wrap:wrap;">
				<h3 style="margin:0;font-size:18px;color:var(--wsp-gray-900,#111827);">
					<?php
					echo 'city' === $scope
						? esc_html__( 'Расчёт по CSV с параметрами «Эргономичность города»', 'worldstat-ergonomics' )
						: esc_html__( 'Расчёт по CSV платформы', 'worldstat-ergonomics' );
					?>
				</h3>
				<?php if ( 'country' === $scope && ! empty( $analysis_url ) ) : ?>
					<a class="wsp-btn wsp-btn-sm wsp-btn-outline" href="<?php echo esc_url( $analysis_url ); ?>">
						<span class="dashicons dashicons-chart-bar" style="font-size:16px;line-height:1;"></span> <?php esc_html_e( 'Анализ', 'worldstat-ergonomics' ); ?>
					</a>
				<?php endif; ?>
			</div>

			<?php if ( $year > 0 && $iso3 !== '' ) : ?>
				<p class="wsp-muted" style="margin:0 0 10px;font-size:.9rem;">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: ISO3, 2: year */
							__( 'Код в модели: %1$s, опорный год: %2$d.', 'worldstat-ergonomics' ),
							$iso3,
							$year
						)
					);
					?>
				</p>
			<?php endif; ?>

			<?php
			$axis_labels = array(
				'E'  => __( 'Сводный E', 'worldstat-ergonomics' ),
				'F'  => __( 'Функциональность F', 'worldstat-ergonomics' ),
				'Cm' => __( 'Комфортность Cm', 'worldstat-ergonomics' ),
				'H'  => __( 'Обитаемость H', 'worldstat-ergonomics' ),
				'A'  => __( 'Освояемость A', 'worldstat-ergonomics' ),
				'S'  => __( 'Безопасность S', 'worldstat-ergonomics' ),
				'Ct' => __( 'Управляемость Ct', 'worldstat-ergonomics' ),
			);
			$show_macro_axis_cards = is_array( $scores ) && ! empty( $scores );
			if ( $show_macro_axis_cards ) {
				$has_finite_axis = false;
				foreach ( array_keys( $axis_labels ) as $axis_k ) {
					if ( ! isset( $scores[ $axis_k ] ) || ! is_numeric( $scores[ $axis_k ] ) ) {
						continue;
					}
					if ( is_finite( (float) $scores[ $axis_k ] ) ) {
						$has_finite_axis = true;
						break;
					}
				}
				$show_macro_axis_cards = $has_finite_axis;
			}
			?>
			<?php if ( $show_macro_axis_cards ) : ?>
				<div class="ergo-stats-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;margin-bottom:12px;">
					<?php
					foreach ( $axis_labels as $k => $lab ) {
						$disp = '—';
						if ( isset( $scores[ $k ] ) && is_numeric( $scores[ $k ] ) ) {
							$vv = (float) $scores[ $k ];
							if ( is_finite( $vv ) ) {
								$disp = $fmt( $vv, 1 );
							}
						}
						printf(
							'<div class="ergo-stat-card" style="background:#fff;border:1px solid var(--wsp-border,#e5e7eb);border-radius:12px;padding:12px;"><h4 style="margin:0 0 6px;font-size:.85rem;color:#64748b;">%s</h4><div class="value" style="font-size:1.35rem;font-weight:700;">%s</div></div>',
							esc_html( $lab ),
							esc_html( $disp )
						);
					}
					?>
				</div>
			<?php endif; ?>

			<?php if ( null === $raw ) : ?>
				<p class="wsp-muted">
					<?php
					if ( 'city' === $scope ) {
						esc_html_e( 'Нет строки признаков для этой страны в CSV за опорный год из настроек «Эргономичность города»: не удалось собрать треугольник «население — площадь территории — плотность» (все три значения > 0) или код страны в файлах не совпадает с ISO3. Проверьте год в CSV, поле country_code/iso3 и опорный год на вкладке города в админке.', 'worldstat-ergonomics' );
					} else {
						esc_html_e( 'Нет строки признаков для этой страны: в данных за выбранный опорный год не удалось собрать обязательный треугольник «население — площадь территории — плотность» (все три числа должны быть > 0, часть можно восстановить из двух других). Либо код в CSV не распознан как ISO3 (нужны три латинские буквы в country_code/iso3 или известный ISO2 при наличии страны в каталоге платформы). Проверьте также год в файлах, настройку «Опорный год» для страны и тип набора CSV: «показатели страны», «индикаторы для расчётов» или «объединённо».', 'worldstat-ergonomics' );
					}
					?>
				</p>
				<?php if ( 'city' === $scope ) : ?>
					<p class="wsp-muted" style="margin-top:8px;">
						<?php esc_html_e( 'Шесть измерений (F, Cm, H, A, S, Ct) и карточки осей выше считаются только при наличии этой строки CSV: без неё сводный E и оси не строятся. В таблице городов всегда показан отдельный индекс E города (кварталы / импорт) — это не те же шесть осей CSV.', 'worldstat-ergonomics' ); ?>
					</p>
				<?php endif; ?>
			<?php else : ?>
				<div class="wsergo-macro-detail-views">
					<div class="wsergo-macro-view-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Режим отображения сырых показателей', 'worldstat-ergonomics' ); ?>">
						<button type="button" class="wsergo-macro-view-tab active" role="tab" aria-selected="true" data-wsergo-macro-view="axes"><?php esc_html_e( 'По осям F…Ct', 'worldstat-ergonomics' ); ?></button>
						<button type="button" class="wsergo-macro-view-tab" role="tab" aria-selected="false" data-wsergo-macro-view="themes"><?php echo esc_html( sprintf( /* translators: %d: count of unique indicators in the macro formula */ __( 'Все показатели формулы (%d)', 'worldstat-ergonomics' ), (int) $n_formula_signals ) ); ?></button>
					</div>
					<div class="wsergo-macro-view" data-wsergo-macro-view-panel="axes">
				<div class="ergo-layout">
					<div class="ergo-sidebar">
						<button type="button" class="ergo-vertical-btn active" data-wsergo-macro-target="F"><span class="dashicons dashicons-chart-line"></span> <?php esc_html_e( 'Функциональность F', 'worldstat-ergonomics' ); ?></button>
						<button type="button" class="ergo-vertical-btn" data-wsergo-macro-target="Cm"><span class="dashicons dashicons-admin-home"></span> <?php esc_html_e( 'Комфортность Cm', 'worldstat-ergonomics' ); ?></button>
						<button type="button" class="ergo-vertical-btn" data-wsergo-macro-target="H"><span class="dashicons dashicons-groups"></span> <?php esc_html_e( 'Обитаемость H', 'worldstat-ergonomics' ); ?></button>
						<button type="button" class="ergo-vertical-btn" data-wsergo-macro-target="A"><span class="dashicons dashicons-location-alt"></span> <?php esc_html_e( 'Освояемость A', 'worldstat-ergonomics' ); ?></button>
						<button type="button" class="ergo-vertical-btn" data-wsergo-macro-target="S"><span class="dashicons dashicons-shield"></span> <?php esc_html_e( 'Безопасность S', 'worldstat-ergonomics' ); ?></button>
						<button type="button" class="ergo-vertical-btn" data-wsergo-macro-target="Ct"><span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e( 'Управляемость Ct', 'worldstat-ergonomics' ); ?></button>
					</div>
					<div class="ergo-content">
						<?php
						$axis_order = array( 'F', 'Cm', 'H', 'A', 'S', 'Ct' );
						$axis_leads = array(
							'F'  => __( 'Сырые признаки, из которых после нормализации внутри кластера собирается ось F (часть слагаемых в модели инвертируется, см. «Подробности расчёта»). Список соответствует матрице критериев в админке.', 'worldstat-ergonomics' ),
							'Cm' => __( 'Сырые признаки для оси Cm (часть слагаемых в модели инвертируется). Список соответствует матрице критериев в админке.', 'worldstat-ergonomics' ),
							'H'  => __( 'Сырые признаки для оси H. Список соответствует матрице критериев в админке.', 'worldstat-ergonomics' ),
							'A'  => __( 'Сырые признаки для оси A. Список соответствует матрице критериев в админке.', 'worldstat-ergonomics' ),
							'S'  => __( 'Сырые признаки для оси S. Список соответствует матрице критериев в админке.', 'worldstat-ergonomics' ),
							'Ct' => __( 'Сырые признаки для оси Ct. Список соответствует матрице критериев в админке.', 'worldstat-ergonomics' ),
						);
						foreach ( $axis_order as $ai => $ax ) :
							$terms_ax = isset( $axis_terms_detail[ $ax ] ) && is_array( $axis_terms_detail[ $ax ] ) ? $axis_terms_detail[ $ax ] : array();
							$lead_ax  = $axis_leads[ $ax ] ?? __( 'Сырые признаки для этой оси.', 'worldstat-ergonomics' );
							?>
						<div class="ergo-macro-panel" data-wsergo-macro-panel="<?php echo esc_attr( $ax ); ?>" style="display:<?php echo ( 0 === $ai ) ? 'block' : 'none'; ?>;">
							<p class="wsergo-macro-panel-lead"><?php echo esc_html( $lead_ax ); ?></p>
							<?php if ( empty( $terms_ax ) ) : ?>
							<p class="wsp-muted"><?php esc_html_e( 'Для этой оси в текущей конфигурации нет слагаемых.', 'worldstat-ergonomics' ); ?></p>
							<?php else : ?>
							<ul class="wsergo-macro-kv">
								<?php
								foreach ( $terms_ax as $tr_ax ) :
									$sig = sanitize_key( (string) ( $tr_ax['signal'] ?? '' ) );
									if ( $sig === '' ) {
										continue;
									}
									$dec = self::macro_raw_metric_decimals( $sig );
									?>
								<li><span class="wsergo-macro-kv__label"><?php echo esc_html( $dn( $sig ) ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw[ $sig ] ?? null, $dec ) ); ?></span></li>
									<?php endforeach; ?>
							</ul>
							<?php endif; ?>
						</div>
						<?php endforeach; ?>
					</div>
				</div>
					</div>
					<div class="wsergo-macro-view" data-wsergo-macro-view-panel="themes" style="display:none;">
						<p class="wsergo-macro-panel-lead"><?php echo esc_html( sprintf( /* translators: %d: number of unique technical indicators in the formula */ __( 'Уникальные показатели, участвующие в расчёте осей по текущей формуле из админки (%d). Для каждого указаны оси F–Ct, где он используется.', 'worldstat-ergonomics' ), (int) $n_formula_signals ) ); ?></p>
						<ul class="wsergo-macro-kv">
							<?php
							foreach ( $signal_axes_map as $sig_map => $ax_keys_set ) :
								$axes_for_sig = array_keys( $ax_keys_set );
								usort(
									$axes_for_sig,
									static function ( $a, $b ) use ( $axis_order ) {
										$ia = array_search( $a, $axis_order, true );
										$ib = array_search( $b, $axis_order, true );
										$ia = false === $ia ? 99 : (int) $ia;
										$ib = false === $ib ? 99 : (int) $ib;
										return $ia <=> $ib;
									}
								);
								$axes_str      = implode( ', ', $axes_for_sig );
								$dec_formula   = self::macro_raw_metric_decimals( $sig_map );
								?>
							<li>
								<span class="wsergo-macro-kv__label"><?php echo esc_html( $dn( $sig_map ) ); ?></span>
								<span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw[ $sig_map ] ?? null, $dec_formula ) ); ?></span>
								<?php if ( $axes_str !== '' ) : ?>
								<span class="wsergo-macro-kv__meta" style="display:block;font-size:.82rem;color:#64748b;margin-top:4px;"><?php echo esc_html( sprintf( /* translators: %s: comma-separated axis codes like F, Cm */ __( 'Оси: %s', 'worldstat-ergonomics' ), $axes_str ) ); ?></span>
								<?php endif; ?>
							</li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>
				<script>
				(function(){
					var root = document.getElementById(<?php echo wp_json_encode( $uid ); ?>);
					if (!root) return;
					var storageKey = <?php echo wp_json_encode( 'city' === $scope ? 'wsergo_macro_city_detail_view' : 'wsergo_macro_country_detail_view' ); ?>;
					function applyDetailView(mode) {
						root.querySelectorAll('.wsergo-macro-view-tab').forEach(function(btn){
							var on = btn.getAttribute('data-wsergo-macro-view') === mode;
							btn.classList.toggle('active', on);
							btn.setAttribute('aria-selected', on ? 'true' : 'false');
						});
						root.querySelectorAll('.wsergo-macro-view[data-wsergo-macro-view-panel]').forEach(function(box){
							box.style.display = box.getAttribute('data-wsergo-macro-view-panel') === mode ? 'block' : 'none';
						});
						try { localStorage.setItem(storageKey, mode); } catch (e) {}
					}
					root.querySelectorAll('.wsergo-macro-view-tab').forEach(function(btn){
						btn.addEventListener('click', function(){
							applyDetailView(btn.getAttribute('data-wsergo-macro-view'));
						});
					});
					root.querySelectorAll('[data-wsergo-macro-target]').forEach(function(btn){
						btn.addEventListener('click', function(){
							var layout = btn.closest('.ergo-layout');
							if (!layout || !root.contains(layout)) return;
							var t = btn.getAttribute('data-wsergo-macro-target');
							layout.querySelectorAll('[data-wsergo-macro-target]').forEach(function(b){ b.classList.remove('active'); });
							layout.querySelectorAll('.ergo-macro-panel').forEach(function(p){ p.style.display = 'none'; });
							btn.classList.add('active');
							var p = layout.querySelector('.ergo-macro-panel[data-wsergo-macro-panel="' + t + '"]');
							if (p) p.style.display = 'block';
						});
					});
					var saved = '';
					try { saved = localStorage.getItem(storageKey) || ''; } catch (e) {}
					if (saved === 'themes') applyDetailView('themes');
				})();
				</script>
			<?php endif; ?>

			<?php
			if ( 'city' === $scope ) {
				self::render_city_macro_methodology_details();
			} else {
				self::render_country_macro_methodology_details();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Раскрывающийся блок: макромодель по настройкам вкладки «Эргономичность города».
	 */
	private static function render_city_macro_methodology_details(): void {
		$k = class_exists( 'WSErgo_Settings' ) ? (int) WSErgo_Settings::get_city_macro_k_clusters() : 6;
		$k = max( 2, min( 12, $k ) );
		$ew_defaults = array(
			'F'  => 0.24,
			'Cm' => 0.22,
			'H'  => 0.18,
			'A'  => 0.14,
			'S'  => 0.12,
			'Ct' => 0.10,
		);
		$ew       = class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::get_city_macro_e_axis_weights() : $ew_defaults;
		$ew_order = array( 'F', 'Cm', 'H', 'A', 'S', 'Ct' );
		$e_parts  = array();
		foreach ( $ew_order as $ax_k ) {
			$w = isset( $ew[ $ax_k ] ) ? (float) $ew[ $ax_k ] : (float) ( $ew_defaults[ $ax_k ] ?? 0.0 );
			$e_parts[] = number_format( $w, 2, ',', '' ) . '·' . $ax_k;
		}
		$e_formula_line = '<strong>E</strong> = ' . implode( ' + ', $e_parts );
		?>
		<details class="wsergo-macro-methodology" style="margin-top:1.1rem;border:1px solid var(--wsp-gray-200,#e5e7eb);border-radius:12px;background:#fafafa;overflow:hidden;">
			<summary style="cursor:pointer;list-style:none;padding:12px 16px;font-weight:600;color:var(--wsp-gray-900,#111827);display:flex;align-items:center;gap:8px;user-select:none;" class="wsergo-macro-methodology-summary">
				<span class="dashicons dashicons-media-document" style="font-size:18px;width:18px;height:18px;color:var(--wsp-primary,#2563eb);" aria-hidden="true"></span>
				<?php esc_html_e( 'Подробности расчёта (город)', 'worldstat-ergonomics' ); ?>
			</summary>
			<div style="padding:0 16px 16px;font-size:.92rem;line-height:1.55;color:var(--wsp-gray-800,#1f2937);border-top:1px solid var(--wsp-gray-200,#e5e7eb);">
				<p style="margin:14px 0 10px;">
					<?php esc_html_e( 'Те же шесть CSV платформы, что и для расчёта по стране, но опорный год, число кластеров k, матрица критериев F–Ct, пользовательские производные и веса E берутся из раздела «Эргономичность города» в админке плагина.', 'worldstat-ergonomics' ); ?>
				</p>
				<ol style="margin:0 0 12px;padding-left:1.25rem;">
					<li><?php esc_html_e( 'Стандартизация признаков для k-means — по вектору признаков кластеризации из настроек города (если отмечено меньше двух — используется встроенный набор, как у страны).', 'worldstat-ergonomics' ); ?></li>
					<li><?php echo esc_html( sprintf( /* translators: %d: number of clusters */ __( 'Кластеризация k-means: k = %d.', 'worldstat-ergonomics' ), $k ) ); ?></li>
					<li><?php esc_html_e( 'Нормализация min–max внутри кластера и сборка осей F…Ct — по матрице и весам из настроек города.', 'worldstat-ergonomics' ); ?></li>
				</ol>
				<p style="margin:0 0 10px;font-family:ui-monospace,'Cascadia Code',monospace;font-size:.88rem;background:#fff;padding:10px 12px;border-radius:8px;border:1px solid #e5e7eb;">
					<?php echo wp_kses_post( $e_formula_line ); ?><br />
					<?php esc_html_e( 'Сводный E на карточке — E×100 (шкала 0…100).', 'worldstat-ergonomics' ); ?>
				</p>
			</div>
		</details>
		<?php
	}

	/**
	 * Раскрывающийся блок с формулами и шагами макромодели (под карточкой «Макро»).
	 */
	private static function render_country_macro_methodology_details(): void {
		$k = ( class_exists( 'WSErgo_Settings' ) ) ? (int) WSErgo_Settings::get_macro_k_clusters() : 4;
		$k = max( 1, $k );
		$ew_defaults = array(
			'F'  => 0.24,
			'Cm' => 0.22,
			'H'  => 0.18,
			'A'  => 0.14,
			'S'  => 0.12,
			'Ct' => 0.10,
		);
		$ew       = class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::get_macro_e_axis_weights() : $ew_defaults;
		$ew_order = array( 'F', 'Cm', 'H', 'A', 'S', 'Ct' );
		$e_parts  = array();
		foreach ( $ew_order as $ax_k ) {
			$w = isset( $ew[ $ax_k ] ) ? (float) $ew[ $ax_k ] : (float) ( $ew_defaults[ $ax_k ] ?? 0.0 );
			$e_parts[] = number_format( $w, 2, ',', '' ) . '·' . $ax_k;
		}
		$e_formula_line = '<strong>E</strong> = ' . implode( ' + ', $e_parts );
		?>
		<details class="wsergo-macro-methodology" style="margin-top:1.1rem;border:1px solid var(--wsp-gray-200,#e5e7eb);border-radius:12px;background:#fafafa;overflow:hidden;">
			<summary style="cursor:pointer;list-style:none;padding:12px 16px;font-weight:600;color:var(--wsp-gray-900,#111827);display:flex;align-items:center;gap:8px;user-select:none;" class="wsergo-macro-methodology-summary">
				<span class="dashicons dashicons-media-document" style="font-size:18px;width:18px;height:18px;color:var(--wsp-primary,#2563eb);" aria-hidden="true"></span>
				<?php esc_html_e( 'Подробности расчёта (страна)', 'worldstat-ergonomics' ); ?>
			</summary>
			<div style="padding:0 16px 16px;font-size:.92rem;line-height:1.55;color:var(--wsp-gray-800,#1f2937);border-top:1px solid var(--wsp-gray-200,#e5e7eb);">
				<p style="margin:14px 0 10px;">
					<?php esc_html_e( 'Индекс на вкладке «Эргономичность» при расчёте из CSV строится из шести наборов платформы (demographics, urban_infra, environment, health_comfort, governance_sdg, energy). Сначала объединяются ряды по country_code + year, затем считаются производные показатели, выполняются нормализация и кластеризация.', 'worldstat-ergonomics' ); ?>
				</p>
				<ol style="margin:0 0 12px;padding-left:1.25rem;">
					<li><?php esc_html_e( 'Стандартизация признаков по столбцам: вычитание среднего, деление на σ (при σ≈0 подставляется 1). Число стран в выборке — из текущих загруженных CSV.', 'worldstat-ergonomics' ); ?></li>
					<li><?php echo esc_html( sprintf( /* translators: %d: number of clusters */ __( 'Кластеризация k-means по вектору из %d признаков (pop_density, urban_share_01, transport_dens, forest_cover_01, renewable_energy, life_exp, pm25, gdp_per_energy).', 'worldstat-ergonomics' ), $k ) ); ?></li>
					<li><?php esc_html_e( 'Для показателей из списка нормализации внутри кластера: min–max по странам кластера в сырых значениях → величина в диапазоне 0…1 (при равенстве min и max подставляется 0,5).', 'worldstat-ergonomics' ); ?></li>
					<li><?php esc_html_e( 'Пропуски (нечисловые или бесконечные слагаемые) перед кластеризацией заполняются медианой признака по всей выборке; при расчёте каждой оси F, Cm, H, A, S, Ct взвешенное среднее пересчитывается только по конечным слагаемым (веса нормируются заново).', 'worldstat-ergonomics' ); ?></li>
				</ol>
				<p style="margin:0 0 8px;"><strong><?php esc_html_e( 'Шесть осей (0…1), затем умножение на 100 для отображения', 'worldstat-ergonomics' ); ?></strong></p>
				<ul style="margin:0 0 12px;padding-left:1.2rem;font-family:system-ui,sans-serif;">
					<li><strong>F</strong> — <?php esc_html_e( 'транспорт, авиа, цифровая доступность, энергоэффективность и структура энергобаланса.', 'worldstat-ergonomics' ); ?></li>
					<li><strong>Cm</strong> — <?php esc_html_e( 'экология, ресурсы, здоровье и бытовый комфорт.', 'worldstat-ergonomics' ); ?></li>
					<li><strong>H</strong> — <?php esc_html_e( 'демография, миграция, урбанизация и базовая инфраструктура жизни.', 'worldstat-ergonomics' ); ?></li>
					<li><strong>A</strong> — <?php esc_html_e( 'доступность среды: транспорт, рост урбанизации, агломерации, цифровой доступ.', 'worldstat-ergonomics' ); ?></li>
					<li><strong>S</strong> — <?php esc_html_e( 'безопасность среды и устойчивость: экология, здоровье, долговая и военная нагрузка.', 'worldstat-ergonomics' ); ?></li>
					<li><strong>Ct</strong> — <?php esc_html_e( 'управляемость: представительство, фискальные прокси, энергетическая и долговая структура.', 'worldstat-ergonomics' ); ?></li>
				</ul>
				<p style="margin:0 0 10px;font-family:ui-monospace,'Cascadia Code',monospace;font-size:.88rem;background:#fff;padding:10px 12px;border-radius:8px;border:1px solid #e5e7eb;">
					<?php echo wp_kses_post( $e_formula_line ); ?><br />
					<?php esc_html_e( 'Веса по осям задаются в админке плагина (блок страны → веса E). Сводный E на карточке — E×100 (шкала 0…100). Если после шагов E не конечен или ≤0, индекс не показывается.', 'worldstat-ergonomics' ); ?>
				</p>
				<p class="wsp-muted" style="margin:0;font-size:.85rem;">
					<?php esc_html_e( 'Состав осей F–Ct задаётся матрицей критериев в админке; расчёт соответствует WSErgo_Country_Macro_Calculator.', 'worldstat-ergonomics' ); ?>
				</p>
			</div>
		</details>
		<?php
	}
}
