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

	/**
	 * Стили для страницы города (бейдж и карточка E).
	 */
	public static function enqueue_public_assets(): void {
		if ( ! class_exists( 'WSCities_CPT' ) || ! is_singular( WSCities_CPT::SLUG ) ) {
			return;
		}
		wp_enqueue_style(
			'wsergo-city-public',
			WSERGO_URL . 'assets/css/city-ergo-public.css',
			[],
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
		if ( 'macro_datasets' === $index_source ) {
			$macro_y = class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::get_macro_reference_year() : 2022;
			$country_mode_label = sprintf(
				/* translators: %d: reference year */
				__( 'макроданные CSV платформы (опорный год %d, без агрегации по городам)', 'worldstat-ergonomics' ),
				(int) $macro_y
			);
		} elseif ( 'regions' === $country_variation ) {
			$country_mode = isset( $agg['region_to_country'] ) ? (string) $agg['region_to_country'] : 'pop_weighted';
			$country_mode_label = 'mean' === $country_mode
				? __( 'режим 2 (страновой): через регионы, среднее по регионам', 'worldstat-ergonomics' )
				: __( 'режим 2 (страновой): через регионы, взвешивание по населению', 'worldstat-ergonomics' );
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

		if ( 'macro_datasets' === $index_source && class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			$macro_detail = WSErgo_Country_Macro_Calculator::get_country_macro_detail( $iso2 );
			if ( is_array( $macro_detail ) ) {
				self::render_country_macro_ergo_panel( $macro_detail );
			}
		}

		$explain = 'macro_datasets' === $index_source
			? sprintf(
				/* translators: 1: macro mode label, 2: methodology version */
				__( 'Индекс страны в первой плитке — %1$s. Ниже: индексы городов по кварталам или импорту Cities (не смешиваются с макро-E). Методика v%2$s.', 'worldstat-ergonomics' ),
				esc_html( $country_mode_label ),
				esc_html( $ver )
			)
			: sprintf(
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

		$rows = [];
		foreach ( $cities as $c ) {
			$city_idx = WSErgo_Data::get_city_ergo_index( (int) $c['id'] );
			$dcount   = count( WSErgo_CPT::get_districts_for_city( (int) $c['id'] ) );
			$link     = get_permalink( $c['id'] );
			$name     = '<a href="' . esc_url( $link ) . '">' . esc_html( $c['name'] ) . '</a>';
			$rows[]   = [
				$name,
				$city_idx !== null ? (string) $city_idx : '—',
				(string) $dcount,
			];
		}

		WorldStat_UI::table(
			[
				'headers'    => [
					__( 'Город', 'worldstat-ergonomics' ),
					__( 'Индекс E', 'worldstat-ergonomics' ),
					__( 'Кварталов', 'worldstat-ergonomics' ),
				],
				'rows'       => $rows,
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
	 * @param array<string,mixed> $detail get_country_macro_detail().
	 */
	private static function render_country_macro_ergo_panel( array $detail ): void {
		$labels = class_exists( 'WSErgo_Country_Macro_Calculator' ) ? WSErgo_Country_Macro_Calculator::standard_metric_labels_ru() : array();
		$uid = 'wsergo-macro-' . ( function_exists( 'wp_unique_id' ) ? wp_unique_id() : uniqid( '', true ) );

		$analysis_url = '';
		if ( class_exists( 'WorldStat_Pages' ) ) {
			$analysis_url = WorldStat_Pages::get_page_url( 'analysis' );
		}
		if ( ! $analysis_url ) {
			$analysis_url = home_url( '/analysis-data/' );
		}

		$diag       = isset( $detail['diagnostics'] ) && is_array( $detail['diagnostics'] ) ? $detail['diagnostics'] : array();
		$raw        = isset( $detail['raw_row'] ) && is_array( $detail['raw_row'] ) ? $detail['raw_row'] : null;
		$scores     = isset( $detail['scores'] ) && is_array( $detail['scores'] ) ? $detail['scores'] : null;
		$year       = (int) ( $detail['year'] ?? 0 );
		$iso3       = (string) ( $detail['iso3'] ?? '' );

		$fmt = static function ( $v, int $decimals = 2 ): string {
			if ( ! is_numeric( $v ) || ! is_finite( (float) $v ) ) {
				return '—';
			}
			return number_format( (float) $v, $decimals, ',', ' ' );
		};

		$missing = isset( $diag['missing_csv_metrics'] ) && is_array( $diag['missing_csv_metrics'] ) ? $diag['missing_csv_metrics'] : array();
		$derived = isset( $diag['triangle_derived_metrics'] ) && is_array( $diag['triangle_derived_metrics'] ) ? $diag['triangle_derived_metrics'] : array();
		$sdg_abs = ! empty( $diag['sdg_row_absent'] );
		$cl_imp  = isset( $diag['cluster_median_imputed_features'] ) && is_array( $diag['cluster_median_imputed_features'] ) ? $diag['cluster_median_imputed_features'] : array();
		$axis_u  = isset( $diag['axis_weight_used'] ) && is_array( $diag['axis_weight_used'] ) ? $diag['axis_weight_used'] : array();
		$idx_bad    = ! empty( $diag['index_unavailable'] );
		$dimless_tri = ! empty( $diag['triangle_dimensionless_baseline'] );

		?>
		<div class="wsp-ergo-wrapper" style="margin-top:1.25rem;" id="<?php echo esc_attr( $uid ); ?>">
			<div class="wsp-ergo-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:10px;flex-wrap:wrap;">
				<h3 style="margin:0;font-size:18px;color:var(--wsp-gray-900,#111827);"><?php esc_html_e( 'Макро: расчёт по CSV платформы', 'worldstat-ergonomics' ); ?></h3>
				<?php if ( ! empty( $analysis_url ) ) : ?>
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

			<?php if ( ! empty( $missing ) || ! empty( $derived ) || $sdg_abs || ! empty( $cl_imp ) || ! empty( $axis_u ) || $idx_bad || $dimless_tri ) : ?>
				<div class="notice notice-info" style="margin:0 0 12px;padding:10px 12px;border-left:4px solid #2271b1;background:#f0f6fc;">
					<?php if ( $idx_bad && ( null === $scores || ! isset( $scores['E'] ) ) ) : ?>
						<p style="margin:0 0 .5em;"><strong><?php esc_html_e( 'Сводный индекс E не выведен', 'worldstat-ergonomics' ); ?></strong> — <?php esc_html_e( 'недостаточно конечных значений по осям после нормализации.', 'worldstat-ergonomics' ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $missing ) ) : ?>
						<p style="margin:0 0 .5em;"><strong><?php esc_html_e( 'Нет данных в CSV за выбранный год (или файл не загружен):', 'worldstat-ergonomics' ); ?></strong></p>
						<ul style="margin:0 0 .5em 1.1em;">
							<?php foreach ( $missing as $mk ) : ?>
								<li><?php echo esc_html( $labels[ $mk ] ?? $mk ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<?php if ( ! empty( $derived ) ) : ?>
						<p style="margin:0 0 .5em;">
							<strong><?php esc_html_e( 'Восстановлено из связки население — площадь — плотность:', 'worldstat-ergonomics' ); ?></strong>
							<?php
							$parts = array();
							foreach ( $derived as $dk ) {
								$parts[] = $labels[ $dk ] ?? $dk;
							}
							echo esc_html( implode( '; ', $parts ) );
							?>
						</p>
					<?php endif; ?>
					<?php if ( $dimless_tri ) : ?>
						<p style="margin:0 0 .5em;">
							<strong><?php esc_html_e( 'Базовый треугольник по плотности', 'worldstat-ergonomics' ); ?></strong>
							<?php esc_html_e( '— в CSV нет населения и площади территории; для расчёта подставлен условный масштаб с той же плотностью. Индекс и кластеры осмысленны для сравнения стран по форме профиля (SDG, инфраструктура, демография в wide); абсолютные «на душу» и доли застройки от площади страны к реальным км² не привязаны, пока не загрузите реальные population_total и surface_area_sqkm.', 'worldstat-ergonomics' ); ?>
						</p>
					<?php endif; ?>
					<?php if ( $sdg_abs ) : ?>
						<p style="margin:0 0 .5em;"><?php esc_html_e( 'Строка SDG за год не найдена: показатели SDG не подставляются; оси считаются только по доступным нормализованным слагаемым.', 'worldstat-ergonomics' ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $cl_imp ) ) : ?>
						<p style="margin:0 0 .5em;">
							<strong><?php esc_html_e( 'Кластеризация (k-means):', 'worldstat-ergonomics' ); ?></strong>
							<?php esc_html_e( 'для вектора позиции страны пропущенные признаки заменены медианой по выборке из загруженных стран.', 'worldstat-ergonomics' ); ?>
							<code style="font-size:.85em;"><?php echo esc_html( implode( ', ', $cl_imp ) ); ?></code>
						</p>
					<?php endif; ?>
					<?php if ( ! empty( $axis_u ) && is_array( $axis_u ) ) : ?>
						<?php
						$axis_keys = array( 'F', 'Cm', 'H', 'A', 'S', 'Ct' );
						$min_axis_frac = 1.0;
						foreach ( $axis_keys as $ax ) {
							if ( isset( $axis_u[ $ax ] ) && is_numeric( $axis_u[ $ax ] ) ) {
								$min_axis_frac = min( $min_axis_frac, (float) $axis_u[ $ax ] );
							}
						}
						?>
						<?php if ( $min_axis_frac >= 0.999 ) : ?>
							<p style="margin:0;">
								<strong><?php esc_html_e( 'Полнота данных по осям F–Ct', 'worldstat-ergonomics' ); ?></strong>
								<?php esc_html_e( '— все слагаемые в взвешенных суммах для каждой оси были конечными, поэтому доля «использованного» веса по каждой оси равна 100%. Это не «оценка качества жизни», а техническая метрика: ни одного пропущенного слагаемого, которое пришлось бы выкинуть из среднего.', 'worldstat-ergonomics' ); ?>
							</p>
						<?php else : ?>
							<p style="margin:0;"><strong><?php esc_html_e( 'Доля веса осей с реальными данными (остальное пропущено в среднем):', 'worldstat-ergonomics' ); ?></strong>
								<?php
								$bits = array();
								foreach ( $axis_keys as $ax ) {
									if ( isset( $axis_u[ $ax ] ) && is_numeric( $axis_u[ $ax ] ) ) {
										$p = round( 100.0 * (float) $axis_u[ $ax ] );
										$bits[] = $ax . ' ' . $p . '%';
									}
								}
								echo esc_html( implode( '; ', $bits ) );
								?>
							</p>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( is_array( $scores ) && isset( $scores['E'] ) && is_finite( (float) $scores['E'] ) ) : ?>
				<div class="ergo-stats-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;margin-bottom:12px;">
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
					foreach ( $axis_labels as $k => $lab ) {
						if ( ! isset( $scores[ $k ] ) ) {
							continue;
						}
						$v = (float) $scores[ $k ];
						if ( ! is_finite( $v ) ) {
							continue;
						}
						printf(
							'<div class="ergo-stat-card" style="background:#fff;border:1px solid var(--wsp-border,#e5e7eb);border-radius:12px;padding:12px;"><h4 style="margin:0 0 6px;font-size:.85rem;color:#64748b;">%s</h4><div class="value" style="font-size:1.35rem;font-weight:700;">%s</div></div>',
							esc_html( $lab ),
							esc_html( $fmt( $v, 1 ) )
						);
					}
					?>
				</div>
			<?php endif; ?>

			<?php if ( null === $raw ) : ?>
				<p class="wsp-muted"><?php esc_html_e( 'Нет строки признаков для этой страны: в макроданных за выбранный опорный год не удалось собрать обязательный треугольник «население — площадь территории — плотность» (все три числа должны быть > 0, часть можно восстановить из двух других). Либо код в CSV не распознан как ISO3 (нужны три латинские буквы в country_code/iso3 или известный ISO2 при наличии страны в каталоге платформы). Проверьте также год в файлах, настройку «Опорный год (макро)» и тип набора CSV: «показатели страны», «индикаторы для расчётов» или «объединённо».', 'worldstat-ergonomics' ); ?></p>
			<?php else : ?>
				<div class="wsergo-macro-detail-views">
					<div class="wsergo-macro-view-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Режим отображения сырых показателей', 'worldstat-ergonomics' ); ?>">
						<button type="button" class="wsergo-macro-view-tab active" role="tab" aria-selected="true" data-wsergo-macro-view="axes"><?php esc_html_e( 'По осям F…Ct', 'worldstat-ergonomics' ); ?></button>
						<button type="button" class="wsergo-macro-view-tab" role="tab" aria-selected="false" data-wsergo-macro-view="themes"><?php esc_html_e( 'По темам данных', 'worldstat-ergonomics' ); ?></button>
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
						<div class="ergo-macro-panel" data-wsergo-macro-panel="F" style="display:block;">
							<p class="wsergo-macro-panel-lead"><?php esc_html_e( 'Сырые признаки, из которых после нормализации внутри кластера собирается ось F (часть слагаемых в модели инвертируется, см. «Подробности расчёта»).', 'worldstat-ergonomics' ); ?></p>
							<ul class="wsergo-macro-kv">
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Транспортная плотность', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['transport_dens'] ?? null, 4 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Авиавылеты (число)', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['air_departures__cnt'] ?? null, 0 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Авиапассажиры', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['air_passengers__psn'] ?? null, 0 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Интернет-пользователи, %', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['internet_users__ptc'] ?? null, 2 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'ШПД на 100 чел.', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['broadband__per_100_psn'] ?? null, 2 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Мобильные подписки на 100 чел.', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['mobile_subs__per_100_psn'] ?? null, 2 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Безопасные серверы на 1 млн', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['secure_servers__per_1m_psn'] ?? null, 2 ) ); ?></span></li>
							</ul>
						</div>
						<div class="ergo-macro-panel" data-wsergo-macro-panel="Cm" style="display:none;">
							<p class="wsergo-macro-panel-lead"><?php esc_html_e( 'Сырые признаки для оси Cm (часть слагаемых в модели инвертируется).', 'worldstat-ergonomics' ); ?></p>
							<ul class="wsergo-macro-kv">
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'PM2.5 (мкг/м³)', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['pm25_exposure__mcg_per_m3'] ?? null, 2 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'CO2 на душу (тонн)', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['co2_per_capita__tonnes_per_psn'] ?? null, 2 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Доля ВИЭ, %', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['renewable_energy__ptc'] ?? null, 2 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Лесной покров (0-1)', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['forest_cover_01'] ?? null, 3 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Лес на душу (м²)', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['forest_per_capita_m2'] ?? null, 1 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Индекс доступа к базовым услугам (WASH+энергия)', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['wASH_access_index'] ?? null, 2 ) ); ?></span></li>
							</ul>
						</div>
						<div class="ergo-macro-panel" data-wsergo-macro-panel="H" style="display:none;">
							<p class="wsergo-macro-panel-lead"><?php esc_html_e( 'Сырые признаки для оси H.', 'worldstat-ergonomics' ); ?></p>
							<ul class="wsergo-macro-kv">
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Ожидаемая продолжительность жизни', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['life_exp_total__years'] ?? null, 2 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Демографическая нагрузка (0-1)', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['age_dependency_proxy'] ?? null, 3 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Рождаемость (на женщину)', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['fertility_rate__births_per_woman'] ?? null, 2 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Миграция (чел.)', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['net_migration__psn'] ?? null, 0 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Доля городского населения (0-1)', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['urban_share_01'] ?? null, 3 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Доля населения в агломерациях >1M, %', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['pop_in_1m_aggl__ptc'] ?? null, 2 ) ); ?></span></li>
							</ul>
						</div>
						<div class="ergo-macro-panel" data-wsergo-macro-panel="A" style="display:none;">
							<p class="wsergo-macro-panel-lead"><?php esc_html_e( 'Сырые признаки для оси A.', 'worldstat-ergonomics' ); ?></p>
							<ul class="wsergo-macro-kv">
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Транспортная плотность', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['transport_dens'] ?? null, 4 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Авиапассажиры', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['air_passengers__psn'] ?? null, 0 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Авиавылеты (число)', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['air_departures__cnt'] ?? null, 0 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Доля городского населения (0-1)', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['urban_share_01'] ?? null, 3 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Рост городского населения, %', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['urban_pop_growth__ptc'] ?? null, 2 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Цифровой индекс доступа', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['digital_access_index'] ?? null, 2 ) ); ?></span></li>
							</ul>
						</div>
						<div class="ergo-macro-panel" data-wsergo-macro-panel="S" style="display:none;">
							<p class="wsergo-macro-panel-lead"><?php esc_html_e( 'Сырые признаки для оси S.', 'worldstat-ergonomics' ); ?></p>
							<ul class="wsergo-macro-kv">
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'PM2.5 (мкг/м³)', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['pm25_exposure__mcg_per_m3'] ?? null, 2 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'CO2 на душу (тонн)', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['co2_per_capita__tonnes_per_psn'] ?? null, 2 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Ожидаемая продолжительность жизни', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['life_exp_total__years'] ?? null, 2 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Нагрузка болезней/стресса', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['infect_and_stress_burden'] ?? null, 2 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Мощность системы здравоохранения', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['health_system_capacity'] ?? null, 2 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Долговой стресс (0-1)', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['debt_stress'] ?? null, 3 ) ); ?></span></li>
							</ul>
						</div>
						<div class="ergo-macro-panel" data-wsergo-macro-panel="Ct" style="display:none;">
							<p class="wsergo-macro-panel-lead"><?php esc_html_e( 'Сырые признаки для оси Ct.', 'worldstat-ergonomics' ); ?></p>
							<ul class="wsergo-macro-kv">
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Женщины в парламенте, %', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['women_parliament_seats__ptc'] ?? null, 2 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Фискальная прозрачность (proxy)', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['fiscal_transparency_proxy'] ?? null, 3 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Налоговые доходы (% ВВП)', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['tax_revenue__ptc_gdp'] ?? null, 2 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Рента ископаемого топлива', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['rent_fuels'] ?? null, 2 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Чистая электроэнергия, %', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['clean_elec_share__ptc'] ?? null, 2 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'ODA, % ВНД', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['net_oda_received__ptc_gni'] ?? null, 2 ) ); ?></span></li>
							</ul>
						</div>
					</div>
				</div>
					</div>
					<div class="wsergo-macro-view" data-wsergo-macro-view-panel="themes" style="display:none;">
				<div class="ergo-layout">
					<div class="ergo-sidebar">
						<button type="button" class="ergo-vertical-btn active" data-wsergo-macro-target="roads"><span class="dashicons dashicons-car"></span> <?php esc_html_e( 'Транспорт', 'worldstat-ergonomics' ); ?></button>
						<button type="button" class="ergo-vertical-btn" data-wsergo-macro-target="urban"><span class="dashicons dashicons-building"></span> <?php esc_html_e( 'Городская среда', 'worldstat-ergonomics' ); ?></button>
						<button type="button" class="ergo-vertical-btn" data-wsergo-macro-target="green"><span class="dashicons dashicons-chart-area"></span> <?php esc_html_e( 'Зелёный каркас', 'worldstat-ergonomics' ); ?></button>
						<button type="button" class="ergo-vertical-btn" data-wsergo-macro-target="biodiversity"><span class="dashicons dashicons-heart"></span> <?php esc_html_e( 'Биоразнообразие', 'worldstat-ergonomics' ); ?></button>
						<button type="button" class="ergo-vertical-btn" data-wsergo-macro-target="industry"><span class="dashicons dashicons-hammer"></span> <?php esc_html_e( 'Промышленность', 'worldstat-ergonomics' ); ?></button>
						<button type="button" class="ergo-vertical-btn" data-wsergo-macro-target="tech"><span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e( 'Инновации и инфраструктура', 'worldstat-ergonomics' ); ?></button>
					</div>
					<div class="ergo-content">
						<div class="ergo-macro-panel" data-wsergo-macro-panel="roads" style="display:block;">
							<p class="wsergo-macro-panel-lead"><?php esc_html_e( 'Плотность транспортной сети (сырые признаки после агрегации по территории).', 'worldstat-ergonomics' ); ?></p>
							<ul class="wsergo-macro-kv">
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Ж/д плотность', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['rail_dens_km_per_km2'] ?? null, 4 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Дорожная плотность', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['road_dens_km_per_km2'] ?? null, 4 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Суммарная транспортная плотность', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['transport_dens'] ?? null, 4 ) ); ?></span></li>
							</ul>
						</div>
						<div class="ergo-macro-panel" data-wsergo-macro-panel="urban" style="display:none;">
							<ul class="wsergo-macro-kv">
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Доля городского населения (0-1)', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['urban_share_01'] ?? null, 3 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Население крупнейшего города / население страны', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['big_city_ratio'] ?? null, 3 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Население в агломерациях >1M, %', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['pop_in_1m_aggl__ptc'] ?? null, 2 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Рост городского населения, %', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['urban_pop_growth__ptc'] ?? null, 2 ) ); ?></span></li>
							</ul>
						</div>
						<div class="ergo-macro-panel" data-wsergo-macro-panel="green" style="display:none;">
							<ul class="wsergo-macro-kv">
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Лесной покров (0-1)', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['forest_cover_01'] ?? null, 3 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Лесная площадь на душу населения, м²/чел.', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['forest_per_capita_m2'] ?? null, 1 ) ); ?></span></li>
							</ul>
						</div>
						<div class="ergo-macro-panel" data-wsergo-macro-panel="biodiversity" style="display:none;">
							<p class="wsergo-macro-panel-lead"><?php esc_html_e( 'Прокси устойчивости природной среды в новой модели.', 'worldstat-ergonomics' ); ?></p>
							<ul class="wsergo-macro-kv">
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Охраняемые территории, %', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['protected_terrestrial__ptc'] ?? null, 2 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Агронагрузка (0-1)', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['agri_pressure'] ?? null, 3 ) ); ?></span></li>
							</ul>
						</div>
						<div class="ergo-macro-panel" data-wsergo-macro-panel="industry" style="display:none;">
							<ul class="wsergo-macro-kv">
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Энергопотребление на душу', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['energy_use_per_cap__kgoe'] ?? null, 2 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'ВВП на единицу энергии', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['gdp_per_energy__ppp_per_kgoe'] ?? null, 2 ) ); ?></span></li>
							</ul>
						</div>
						<div class="ergo-macro-panel" data-wsergo-macro-panel="tech" style="display:none;">
							<ul class="wsergo-macro-kv">
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Интернет-пользователи, %', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['internet_users__ptc'] ?? null, 2 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'ШПД на 100 чел.', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['broadband__per_100_psn'] ?? null, 2 ) ); ?></span></li>
								<li><span class="wsergo-macro-kv__label"><?php esc_html_e( 'Цифровой индекс доступа', 'worldstat-ergonomics' ); ?></span><span class="wsergo-macro-kv__val"><?php echo esc_html( $fmt( $raw['digital_access_index'] ?? null, 2 ) ); ?></span></li>
							</ul>
						</div>
					</div>
				</div>
					</div>
				</div>
				<div style="margin-top:1rem;padding:.8rem 1rem;background:#eef2ff;border-radius:12px;font-size:.9rem;color:#1e3a8a;">
					<span class="dashicons dashicons-database" style="vertical-align:text-bottom;"></span>
					<?php esc_html_e( 'Значения «—» означают отсутствие исходного ряда за опорный год; расчёт осей выполняется по оставшимся слагаемым.', 'worldstat-ergonomics' ); ?>
				</div>
				<script>
				(function(){
					var root = document.getElementById(<?php echo wp_json_encode( $uid ); ?>);
					if (!root) return;
					var storageKey = 'wsergo_macro_country_detail_view';
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

			<?php self::render_country_macro_methodology_details(); ?>
		</div>
		<?php
	}

	/**
	 * Раскрывающийся блок с формулами и шагами макромодели (под карточкой «Макро»).
	 */
	private static function render_country_macro_methodology_details(): void {
		$k = ( class_exists( 'WSErgo_Settings' ) ) ? (int) WSErgo_Settings::get_macro_k_clusters() : 4;
		$k = max( 1, $k );
		?>
		<details class="wsergo-macro-methodology" style="margin-top:1.1rem;border:1px solid var(--wsp-gray-200,#e5e7eb);border-radius:12px;background:#fafafa;overflow:hidden;">
			<summary style="cursor:pointer;list-style:none;padding:12px 16px;font-weight:600;color:var(--wsp-gray-900,#111827);display:flex;align-items:center;gap:8px;user-select:none;" class="wsergo-macro-methodology-summary">
				<span class="dashicons dashicons-media-document" style="font-size:18px;width:18px;height:18px;color:var(--wsp-primary,#2563eb);" aria-hidden="true"></span>
				<?php esc_html_e( 'Подробности расчёта (макромодель)', 'worldstat-ergonomics' ); ?>
			</summary>
			<div style="padding:0 16px 16px;font-size:.92rem;line-height:1.55;color:var(--wsp-gray-800,#1f2937);border-top:1px solid var(--wsp-gray-200,#e5e7eb);">
				<p style="margin:14px 0 10px;">
					<?php esc_html_e( 'Индекс на вкладке «Эргономичность» в режиме макроданных строится только из шести новых CSV, загруженных в платформу (demographics, urban_infra, environment, health_comfort, governance_sdg, energy). Сначала объединяются ряды по country_code + year, затем считаются производные показатели, выполняются нормализация и кластеризация.', 'worldstat-ergonomics' ); ?>
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
					<strong>E</strong> = 0,24·F + 0,22·Cm + 0,18·H + 0,14·A + 0,12·S + 0,10·Ct<br />
					<?php esc_html_e( 'Сводный E на карточке — E×100 (шкала 0…100). Если после шагов E не конечен или ≤0, индекс не показывается.', 'worldstat-ergonomics' ); ?>
				</p>
				<p class="wsp-muted" style="margin:0;font-size:.85rem;">
					<?php esc_html_e( 'Текст соответствует коду WSErgo_Country_Macro_Calculator; при смене логики в плагине сверяйте с файлом class-ergo-country-macro-calculator.php.', 'worldstat-ergonomics' ); ?>
				</p>
			</div>
		</details>
		<?php
	}
}
