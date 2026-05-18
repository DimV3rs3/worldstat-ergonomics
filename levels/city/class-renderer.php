<?php
/**
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class WSErgo_City_Renderer {


	private static function public_asset_version( string $relative_path ): string {
		$base = defined( 'WSERGO_FILE' ) ? dirname( (string) WSERGO_FILE ) : '';
		$path = $base . '/' . ltrim( $relative_path, '/' );
		if ( $path !== '' && is_readable( $path ) ) {
			return (string) filemtime( $path );
		}
		return defined( 'WSERGO_VERSION' ) ? (string) WSERGO_VERSION : '1';
	}


	public static function enqueue_public_assets(): void {
		if ( ! class_exists( 'WSCities_CPT' ) || ! is_singular( WSCities_CPT::SLUG ) ) {
			return;
		}
		wp_enqueue_style(
			'wsergo-city-public',
			WSErgo_Level_Registry::url( 'city', 'public/assets/css/city-ergo-public.css' ),
			[],
			WSErgo_Level_Registry::asset_version( 'city', 'public/assets/css/city-ergo-public.css' )
		);
	}


	public static function render_compact_card( int $post_id, array $meta ): void {
		unset( $meta );
		if ( ! class_exists( 'WSErgo_City_Data' ) || ! class_exists( 'WSErgo_City_Bridge' ) ) {
			return;
		}
		$show = WSErgo_City_Bridge::is_city_import_ergo_enabled() || count( WSErgo_CPT::get_districts_for_city( $post_id ) ) > 0;
		if ( ! $show ) {
			return;
		}
		$idx = WSErgo_City_Data::get_city_ergo_index( $post_id );
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


	public static function shortcode_city_e( $atts ): string {
		if ( ! class_exists( 'WSErgo_City_Data' ) ) {
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
		$idx = WSErgo_City_Data::get_city_ergo_index( $cid );
		if ( $idx === null || $idx <= 0 ) {
			return '';
		}
		$cls = sanitize_html_class( $a['class'] );
		return '<span class="' . esc_attr( $cls ) . '" title="' . esc_attr__( 'Индекс эргономичности, 0–100', 'worldstat-ergonomics' ) . '"><abbr title="' . esc_attr__( 'Эргономичность', 'worldstat-ergonomics' ) . '">E</abbr>&nbsp;' . esc_html( (string) $idx ) . '</span>';
	}


	public static function render_section( int $post_id, array $meta ): void {
		$districts = WSErgo_CPT::get_districts_for_city( $post_id );
		// Режим «только город» — карточка E выводится через wsp_city_after_stats; здесь только кварталы/карта.
		if ( empty( $districts ) ) {
			return;
		}

		$city_idx = WSErgo_City_Data::get_city_ergo_index( $post_id );
		$ver      = get_option( 'wsergo_methodology_version', '1.0' );
		$iso2     = strtoupper( (string) ( $meta['country_iso2'] ?? get_post_meta( $post_id, 'wscity_country_iso2', true ) ) );
		$country_idx = $iso2 !== '' ? WSErgo_Country_Data::get_country_ergo_index( $iso2 ) : 0.0;
		$country_sub = ( $iso2 !== '' && method_exists( 'WSErgo_Data', 'get_country_import_subindices' ) )
			? WSErgo_Country_Data::get_country_import_subindices( $iso2 )
			: [];

		echo '<div class="wsp-ergo-city-section wsp-container" style="margin-top:2rem;padding-top:2rem;border-top:1px solid var(--wsp-border,#e5e7eb);">';
		echo '<h2 class="wsp-section-title">' . esc_html__( 'Эргономичность города', 'worldstat-ergonomics' ) . '</h2>';
		echo '<div class="wsp-tabs wsergo-city-subtabs">';
		echo '<nav class="wsp-tab-nav" role="tablist" aria-label="' . esc_attr__( 'Подвкладки города', 'worldstat-ergonomics' ) . '">';
		echo '<button class="wsp-tab-btn wsp-tab-active" data-tab="country" role="tab" aria-selected="true">' . esc_html__( 'Страна', 'worldstat-ergonomics' ) . '</button>';
		echo '<button class="wsp-tab-btn" data-tab="cities" role="tab" aria-selected="false">' . esc_html__( 'Города', 'worldstat-ergonomics' ) . '</button>';
		echo '</nav>';
		echo '<div class="wsp-tab-panels">';
		echo '<section class="wsp-tab-panel wsp-tab-panel-active" data-tab="country">';

		WorldStat_UI::stats_grid(
			[
				[
					'label' => __( 'Индекс страны', 'worldstat-ergonomics' ),
					'value' => $country_idx > 0 ? (string) $country_idx : '—',
					'icon'  => 'admin-site',
				],
				[
					'label' => __( 'Код страны', 'worldstat-ergonomics' ),
					'value' => $iso2 !== '' ? $iso2 : '—',
					'icon'  => 'location',
				],
			],
			[ 'columns' => 2 ]
		);

		if ( ! empty( $country_sub ) ) {
			echo '<div class="ergo-stats-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;margin-bottom:12px;">';
			$cards = [
				'functionality' => __( 'Функциональность F', 'worldstat-ergonomics' ),
				'comfort'       => __( 'Комфортность Cm', 'worldstat-ergonomics' ),
				'livability'    => __( 'Обитаемость H', 'worldstat-ergonomics' ),
				'masterability' => __( 'Освояемость A', 'worldstat-ergonomics' ),
				'safety'        => __( 'Безопасность S', 'worldstat-ergonomics' ),
				'manageability' => __( 'Управляемость Ct', 'worldstat-ergonomics' ),
			];
			foreach ( $cards as $key => $label ) {
				$value = isset( $country_sub[ $key ] ) ? (float) $country_sub[ $key ] : NAN;
				printf(
					'<article class="ergo-stat-card" style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:12px;color:#1d2327;"><h4 style="margin:0 0 6px;font-size:13px;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#1d2327;">%s</h4><div class="value" style="font-size:1.35rem;font-weight:700;line-height:1.2;color:#1d2327;">%s</div></article>',
					esc_html( $label ),
					is_finite( $value ) ? esc_html( number_format_i18n( $value, 1 ) ) : '—'
				);
			}
			echo '</div>';
		}

		echo '</section>';
		echo '<section class="wsp-tab-panel" data-tab="cities">';

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
					'allow_html' => true,
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

		echo '</section>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
	}

}
