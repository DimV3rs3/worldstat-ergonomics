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
		$country_mode_label = __( 'режим 1 (городской): напрямую по городам (население T3)', 'worldstat-ergonomics' );
		if ( 'regions' === $country_variation ) {
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

		WorldStat_UI::text_block(
			[
				'content' => sprintf(
					/* translators: 1: country aggregation mode, 2: version */
					__( 'Индекс страны — с учётом правила «Регионы → страна» (%1$s); индекс города — по кварталам (модель DSL или взвешенное среднее по шести измерениям). Регион — по полю «регион» у города. Методика v%2$s.', 'worldstat-ergonomics' ),
					esc_html( $country_mode_label ),
					esc_html( $ver )
				),
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
}
