<?php
/**
 * Агрегации и провайдеры для платформы.
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_Data {
	/**
	 * Безопасно получить список городов страны из Cities.
	 *
	 * @return array<int, array<string,mixed>>
	 */
	private static function get_country_cities( string $iso2 ): array {
		if ( ! class_exists( 'WSCities_CPT' ) || ! method_exists( 'WSCities_CPT', 'get_cities_for_country' ) ) {
			return [];
		}
		$cities = WSCities_CPT::get_cities_for_country( strtoupper( $iso2 ) );
		return is_array( $cities ) ? $cities : [];
	}

	/**
	 * Индекс города: при наличии кварталов с E>0 — агрегация по кварталам; иначе — расчёт из данных плагина «Города» (если включено и настроено сопоставление).
	 */
	public static function get_city_ergo_index( int $city_id ): ?float {
		$districts = WSErgo_CPT::get_districts_for_city( $city_id );
		$has_district_index = false;
		foreach ( $districts as $d ) {
			$v = (float) get_post_meta( $d->ID, WSErgo_CPT::META_INDEX, true );
			if ( $v > 0 ) {
				$has_district_index = true;
				break;
			}
		}

		if ( $has_district_index ) {
			$agg  = class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::get_aggregation() : [];
			$mode = isset( $agg['district_to_city'] ) ? (string) $agg['district_to_city'] : 'mean';

			$sum = 0.0;
			$w   = 0.0;
			foreach ( $districts as $d ) {
				$v = (float) get_post_meta( $d->ID, WSErgo_CPT::META_INDEX, true );
				if ( $v <= 0 ) {
					continue;
				}
				if ( 'buildings_weighted' === $mode ) {
					$nb = count( WSErgo_CPT::get_buildings_for_district( $d->ID ) );
					$ny = count( WSErgo_CPT::get_yards_for_district( $d->ID ) );
					$wi = max( 1, $nb + $ny );
				} else {
					$wi = 1.0;
				}
				$sum += $v * $wi;
				$w   += $wi;
			}
			return $w > 0 ? round( $sum / $w, 2 ) : null;
		}

		if ( ! class_exists( 'WSErgo_City_Bridge' ) ) {
			return null;
		}
		$cached = (float) get_post_meta( $city_id, WSErgo_City_Bridge::META_LEAF_INDEX, true );
		if ( $cached > 0 ) {
			return round( $cached, 2 );
		}
		if ( ! WSErgo_City_Bridge::is_city_import_ergo_enabled() ) {
			return null;
		}
		if ( empty( WSErgo_City_Bridge::get_field_map() ) ) {
			return null;
		}
		$computed = WSErgo_City_Bridge::compute_and_store_city_leaf_index( $city_id );
		return $computed !== null && $computed > 0 ? round( $computed, 2 ) : null;
	}

	/**
	 * Регион (строка wscity_region) внутри страны: среднее по городам с весом населения T3.
	 */
	public static function get_region_ergo_index( string $iso2, string $region_name ): ?float {
		$iso2 = strtoupper( $iso2 );
		$r    = trim( $region_name );
		if ( $r === '' ) {
			return null;
		}
		$cities = self::get_country_cities( $iso2 );
		if ( empty( $cities ) ) {
			return null;
		}
		$mode   = 'pop_weighted';
		if ( class_exists( 'WSErgo_Settings' ) ) {
			$agg  = WSErgo_Settings::get_aggregation();
			$mode = isset( $agg['city_to_region'] ) ? (string) $agg['city_to_region'] : 'pop_weighted';
		}

		$w_sum = 0.0;
		$w     = 0.0;
		$vals  = [];
		foreach ( $cities as $c ) {
			$cid = (int) $c['id'];
			$reg = (string) get_post_meta( $cid, 'wscity_region', true );
			if ( trim( $reg ) !== $r ) {
				continue;
			}
			$idx = self::get_city_ergo_index( $cid );
			if ( $idx === null || $idx <= 0 ) {
				continue;
			}
			if ( 'mean' === $mode ) {
				$vals[] = $idx;
				continue;
			}
			$pop = max( 1, (int) ( $c['pop_t3'] ?? 1 ) );
			$w_sum += $idx * $pop;
			$w     += $pop;
		}
		if ( 'mean' === $mode ) {
			return ! empty( $vals ) ? round( array_sum( $vals ) / count( $vals ), 2 ) : null;
		}
		return $w > 0 ? round( $w_sum / $w, 2 ) : null;
	}

	/**
	 * Список уникальных регионов (по городам) для страны.
	 *
	 * @return string[]
	 */
	public static function list_regions_for_country( string $iso2 ): array {
		$cities = self::get_country_cities( strtoupper( $iso2 ) );
		$out    = [];
		foreach ( $cities as $c ) {
			$r = trim( (string) get_post_meta( (int) $c['id'], 'wscity_region', true ) );
			if ( $r !== '' ) {
				$out[ $r ] = true;
			}
		}
		return array_keys( $out );
	}

	/**
	 * Индекс страны: по умолчанию — макромодель по CSV платформы (см. WSErgo_Country_Macro_Calculator);
	 * опционально — агрегация по городам (legacy), см. настройки плагина.
	 */
	public static function get_country_ergo_index( string $iso2 ): float {
		$iso2 = strtoupper( $iso2 );
		if ( class_exists( 'WSErgo_Settings' ) && class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			if ( WSErgo_Settings::get_country_index_source() === 'macro_datasets' ) {
				return WSErgo_Country_Macro_Calculator::get_index_for_iso2( $iso2 );
			}
		}
		return self::get_country_ergo_index_from_cities( $iso2 );
	}

	/**
	 * Рейтинги / массовые выборки: макрорежим — один проход по своду; режим городов — по стране через get_country_ergo_index.
	 *
	 * @param list<string> $iso2_list
	 * @return array<string,float>
	 */
	public static function bulk_country_ergo_index_for_iso2_list( array $iso2_list ): array {
		if ( empty( $iso2_list ) ) {
			return array();
		}
		if ( class_exists( 'WSErgo_Country_Macro_Calculator' ) && class_exists( 'WSErgo_Settings' ) && WSErgo_Settings::get_country_index_source() === 'macro_datasets' ) {
			return WSErgo_Country_Macro_Calculator::get_bulk_macro_indices_for_iso2( $iso2_list );
		}
		$out = array();
		foreach ( $iso2_list as $iso ) {
			$iu = strtoupper( trim( (string) $iso ) );
			if ( $iu === '' ) {
				continue;
			}
			$out[ $iu ] = self::get_country_ergo_index( $iu );
		}
		return $out;
	}

	/**
	 * Legacy: население-взвешенное среднее по городам страны (и вариант через регионы).
	 */
	private static function get_country_ergo_index_from_cities( string $iso2 ): float {
		$iso2   = strtoupper( $iso2 );
		$cities = self::get_country_cities( $iso2 );
		if ( empty( $cities ) ) {
			return 0.0;
		}

		$variation = class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::get_country_variation() : 'city_direct';
		if ( 'regions' === $variation ) {
			$mode = 'pop_weighted';
			if ( class_exists( 'WSErgo_Settings' ) ) {
				$agg  = WSErgo_Settings::get_aggregation();
				$mode = isset( $agg['region_to_country'] ) ? (string) $agg['region_to_country'] : 'pop_weighted';
			}

			$regions = self::list_regions_for_country( $iso2 );
			if ( ! empty( $regions ) ) {
				$sum = 0.0;
				$w   = 0.0;
				$n   = 0;
				foreach ( $regions as $region_name ) {
					$rix = self::get_region_ergo_index( $iso2, $region_name );
					if ( $rix === null || $rix <= 0 ) {
						continue;
					}
					if ( 'mean' === $mode ) {
						$sum += $rix;
						++$n;
						continue;
					}
					$rp = self::get_region_population( $iso2, $region_name );
					if ( $rp <= 0 ) {
						continue;
					}
					$sum += $rix * $rp;
					$w   += $rp;
				}
				if ( 'mean' === $mode && $n > 0 ) {
					return round( $sum / $n, 2 );
				}
				if ( 'mean' !== $mode && $w > 0 ) {
					return round( $sum / $w, 2 );
				}
			}
		}

		// Классический режим (по умолчанию): прямая агрегация по городам.
		$w_sum = 0.0;
		$w     = 0.0;
		foreach ( $cities as $c ) {
			$idx = self::get_city_ergo_index( (int) $c['id'] );
			if ( $idx === null || $idx <= 0 ) {
				continue;
			}
			$pop = max( 1, (int) ( $c['pop_t3'] ?? 1 ) );
			$w_sum += $idx * $pop;
			$w     += $pop;
		}
		return $w > 0 ? round( $w_sum / $w, 2 ) : 0.0;
	}

	/**
	 * Суммарное население региона по городам T3.
	 */
	private static function get_region_population( string $iso2, string $region_name ): int {
		$cities = self::get_country_cities( strtoupper( $iso2 ) );
		$region = trim( $region_name );
		if ( $region === '' ) {
			return 0;
		}
		$sum = 0;
		foreach ( $cities as $c ) {
			$cid = (int) ( $c['id'] ?? 0 );
			if ( $cid <= 0 ) {
				continue;
			}
			$reg = trim( (string) get_post_meta( $cid, 'wscity_region', true ) );
			if ( $reg !== $region ) {
				continue;
			}
			$sum += max( 0, (int) ( $c['pop_t3'] ?? 0 ) );
		}
		return $sum;
	}

	public static function get_country_districts_count( string $iso2 ): int {
		return WSErgo_CPT::count_districts_in_country( strtoupper( $iso2 ) );
	}

	public static function get_country_buildings_count( string $iso2 ): int {
		return WSErgo_CPT::count_buildings_in_country( strtoupper( $iso2 ) );
	}

	/**
	 * Choropleth: ISO2 => индекс (0 если нет данных).
	 */
	public static function get_map_data(): array {
		if ( ! class_exists( 'WorldStat_Country_CPT' ) ) {
			return [];
		}
		$iso_list = array_keys( WorldStat_Country_CPT::get_code_map() );
		if (
			class_exists( 'WSErgo_Country_Macro_Calculator' )
			&& class_exists( 'WSErgo_Settings' )
			&& WSErgo_Settings::get_country_index_source() === 'macro_datasets'
		) {
			$bulk = WSErgo_Country_Macro_Calculator::get_bulk_macro_indices_for_iso2( $iso_list );
			$out = [];
			foreach ( $iso_list as $iso2 ) {
				$out[ $iso2 ] = $bulk[ strtoupper( (string) $iso2 ) ] ?? 0.0;
			}
			return $out;
		}
		$out = [];
		foreach ( $iso_list as $iso2 ) {
			$out[ $iso2 ] = self::get_country_ergo_index( (string) $iso2 );
		}
		return $out;
	}

	/**
	 * @return array<int, array{lat:float,lng:float,title:string,value:mixed,popup:string}>
	 */
	public static function get_all_building_markers(): array {
		$posts = get_posts(
			[
				'post_type'      => WSErgo_CPT::SLUG_BUILDING,
				'post_status'    => 'publish',
				'posts_per_page' => 5000,
				'fields'         => 'ids',
			]
		);
		return self::posts_to_markers( $posts );
	}

	/**
	 * @return array<int, array{lat:float,lng:float,title:string,value:mixed,popup:string}>
	 */
	public static function get_country_building_markers( string $iso2 ): array {
		$buildings = WSErgo_CPT::get_buildings_for_country( strtoupper( $iso2 ) );
		$ids       = wp_list_pluck( $buildings, 'ID' );
		return self::posts_to_markers( $ids );
	}

	/**
	 * @param int[] $post_ids
	 * @return array<int, array<string,mixed>>
	 */
	private static function posts_to_markers( array $post_ids ): array {
		$markers = [];
		foreach ( $post_ids as $post_id ) {
			$lat = (float) get_post_meta( $post_id, WSErgo_CPT::META_LAT, true );
			$lng = (float) get_post_meta( $post_id, WSErgo_CPT::META_LNG, true );
			if ( ! $lat || ! $lng ) {
				continue;
			}
			$title = get_the_title( $post_id );
			$idx   = (float) get_post_meta( $post_id, WSErgo_CPT::META_INDEX, true );
			$markers[] = [
				'lat'   => $lat,
				'lng'   => $lng,
				'title' => $title,
				'value' => $idx,
				'popup' => '<strong>' . esc_html( $title ) . '</strong><br>' . esc_html(
					sprintf(
						/* translators: %s: score */
						__( 'Индекс: %s', 'worldstat-ergonomics' ),
						$idx > 0 ? (string) $idx : '—'
					)
				),
			];
		}
		return $markers;
	}

	/**
	 * Шесть уровней эргономичности для графиков (подпись => балл).
	 *
	 * @return array<string, float>
	 */
	public static function get_subindices_for_post( int $post_id ): array {
		return WSErgo_Model::get_labeled_scores_for_charts( $post_id );
	}
}
