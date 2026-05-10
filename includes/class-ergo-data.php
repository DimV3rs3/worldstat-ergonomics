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
	 * Данные для публичного блока «город — листовые показатели» (карта полей, нормализация, оси, E).
	 *
	 * @return array{
	 *   id:int,
	 *   name:string,
	 *   url:string,
	 *   e:?string,
	 *   leaf_e:?string,
	 *   axes:array<string,float>,
	 *   indicators:list<array{id:string,label:string,source:string,raw:?string,score:?string}>,
	 *   leaf_notice:string
	 * }
	 */
	public static function get_city_leaf_public_detail( int $city_id ): array {
		$city_id = (int) $city_id;
		$name    = $city_id > 0 ? get_the_title( $city_id ) : '';
		$url     = $city_id > 0 ? (string) get_permalink( $city_id ) : '';
		$e_val   = self::get_city_ergo_index( $city_id );
		$e_str   = $e_val !== null && $e_val > 0 ? (string) $e_val : null;

		$out = [
			'id'           => $city_id,
			'name'         => $name,
			'url'          => $url,
			'e'            => $e_str,
			'leaf_e'       => null,
			'axes'         => [],
			'indicators'   => [],
			'leaf_notice'  => '',
		];

		if ( $city_id <= 0 ) {
			$out['leaf_notice'] = __( 'Город не выбран.', 'worldstat-ergonomics' );
			return $out;
		}

		if ( ! class_exists( 'WSErgo_City_Bridge' ) ) {
			$out['leaf_notice'] = __( 'Модуль городской эргономики недоступен.', 'worldstat-ergonomics' );
			return $out;
		}

		if ( ! WSErgo_City_Bridge::is_city_import_ergo_enabled() || empty( WSErgo_City_Bridge::get_field_map() ) ) {
			$out['leaf_notice'] = __( 'Листовые показатели по карте полей выводятся, если в настройках эргономики включён расчёт по данным импорта и задана карта полей города.', 'worldstat-ergonomics' );
			return $out;
		}

		$raw = WSErgo_City_Bridge::collect_raw_for_city( $city_id );
		$defs_by_id = [];
		foreach ( WSErgo_Indicators::get_definitions() as $def_row ) {
			if ( isset( $def_row['id'] ) ) {
				$defs_by_id[ (string) $def_row['id'] ] = $def_row;
			}
		}

		foreach ( WSErgo_City_Bridge::get_field_map() as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$iid    = isset( $row['indicator_id'] ) ? sanitize_key( (string) $row['indicator_id'] ) : '';
			$source = isset( $row['source'] ) ? (string) $row['source'] : '';
			if ( $iid === '' || $source === '' ) {
				continue;
			}
			$def   = $defs_by_id[ $iid ] ?? null;
			$label = $def ? (string) ( $def['label'] ?? $iid ) : $iid;
			$rawf  = isset( $raw[ $iid ] ) ? (float) $raw[ $iid ] : null;
			$score = null;
			$norm  = null;
			if ( $def !== null && $rawf !== null && array_key_exists( $iid, $raw ) ) {
				$norm = WSErgo_Indicators::normalize_to_score( $def, $rawf );
				$score = $norm !== null ? (string) $norm : null;
			}
			$dir = $def ? (string) ( $def['direction'] ?? 'higher_better' ) : 'higher_better';
			if ( 'lower_better' !== $dir ) {
				$dir = 'higher_better';
			}
			$score_val = ( $norm !== null && is_finite( (float) $norm ) ) ? round( (float) $norm, 4 ) : null;
			$out['indicators'][] = [
				'id'           => $iid,
				'label'        => $label,
				'source'       => $source,
				'raw'          => $rawf !== null && array_key_exists( $iid, $raw ) ? self::format_leaf_public_number( $rawf ) : null,
				'score'        => $score,
				'score_value'  => $score_val,
				'direction'    => $dir,
			];
		}

		$scores = WSErgo_Indicators::build_dimension_scores_from_raw_map( $raw );
		foreach ( $scores as $dim => $sv ) {
			if ( is_numeric( $sv ) ) {
				$out['axes'][ (string) $dim ] = round( (float) $sv, 2 );
			}
		}

		if ( empty( $out['indicators'] ) ) {
			$out['leaf_notice'] = __( 'Карта полей пуста — добавьте строки в настройках эргономики (город).', 'worldstat-ergonomics' );
		} elseif ( empty( $raw ) ) {
			$out['leaf_notice'] = __( 'По текущей карте полей для этого города нет сырых значений (мета или Blocks & Roads).', 'worldstat-ergonomics' );
		}

		if ( ! empty( $raw ) && class_exists( 'WSErgo_Calculator' ) ) {
			$lv = WSErgo_Calculator::compute_leaf_from_raw_indicator_map( $city_id, $raw );
			if ( $lv !== null && $lv > 0 ) {
				$out['leaf_e'] = (string) round( (float) $lv, 2 );
			}
		}

		return $out;
	}

	/**
	 * Короткая строка для вывода сырого числа на сайте.
	 */
	private static function format_leaf_public_number( float $v ): string {
		if ( ! is_finite( $v ) ) {
			return '—';
		}
		$rn = round( $v );
		if ( abs( $v - $rn ) < 1e-6 * max( 1.0, abs( $rn ) ) ) {
			return (string) (int) $rn;
		}
		$s = number_format( $v, 6, '.', '' );
		$s = rtrim( rtrim( $s, '0' ), '.' );
		return $s === '' ? (string) $v : $s;
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
