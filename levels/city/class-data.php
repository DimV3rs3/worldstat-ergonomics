<?php
/**
 * Агрегации и провайдеры для платформы.
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_City_Data {
	/**
	 * Подиндексы города, рассчитанные по данным импорта (для таблиц/карточек).
	 *
	 * @return array<string, float>
	 */
	public static function get_city_import_subindices( int $city_id ): array {
		if ( $city_id <= 0 ) {
			return [];
		}
		if ( class_exists( 'WSErgo_City_Bridge' ) && WSErgo_City_Bridge::is_city_import_ergo_enabled() ) {
			// Держим мета актуальными перед чтением.
			WSErgo_City_Bridge::compute_and_store_city_leaf_index( $city_id );
		}

		$keys = [
			'functionality' => WSErgo_Model::meta_key_for_dimension( WSErgo_Model::DIM_FUNCTIONALITY ),
			'safety'        => WSErgo_Model::meta_key_for_dimension( WSErgo_Model::DIM_SAFETY ),
			'comfort'       => WSErgo_Model::meta_key_for_dimension( WSErgo_Model::DIM_COMFORT ),
			'livability'    => WSErgo_Model::meta_key_for_dimension( WSErgo_Model::DIM_LIVABILITY ),
			'masterability' => WSErgo_Model::meta_key_for_dimension( WSErgo_Model::DIM_MASTERABILITY ),
			'manageability' => WSErgo_Model::meta_key_for_dimension( WSErgo_Model::DIM_MANAGEABILITY ),
		];
		$out = [];
		foreach ( $keys as $dim => $meta_key ) {
			$v = (float) get_post_meta( $city_id, $meta_key, true );
			if ( $v > 0 ) {
				$out[ $dim ] = round( $v, 2 );
			}
		}
		if ( ! empty( $out ) ) {
			return $out;
		}

		// Fallback if dimension metas were not explicitly stored by bridge.
		$open      = (float) get_post_meta( $city_id, 'wscity_openness', true );
		$cohesion  = (float) get_post_meta( $city_id, 'wscity_cohesion', true );
		$satur     = (float) get_post_meta( $city_id, 'wscity_saturation', true );
		$proximity = (float) get_post_meta( $city_id, 'wscity_proximity', true );
		$dBuilt    = (float) get_post_meta( $city_id, 'wscity_density_builtup', true );
		$dExtent   = (float) get_post_meta( $city_id, 'wscity_density_extent', true );
		$pop       = (float) get_post_meta( $city_id, 'wscity_pop_t3', true );
		$builtup   = (float) get_post_meta( $city_id, 'wscity_builtup_t3', true );

		$derived = [];
		$derived['functionality'] = self::avg_non_empty(
			self::norm_higher( $proximity, 0.0, 1.0 ),
			self::norm_higher( $pop, 50000.0, 30000000.0 )
		);
		$derived['safety'] = self::avg_non_empty(
			self::norm_lower( $dBuilt, 20.0, 400.0 ),
			self::norm_higher( $cohesion, 0.0, 1.0 )
		);
		$derived['comfort'] = self::avg_non_empty(
			self::norm_higher( $open, 0.0, 1.0 ),
			self::norm_lower( $dExtent, 10.0, 250.0 )
		);
		$derived['livability'] = self::avg_non_empty(
			self::norm_higher( $cohesion, 0.0, 1.0 ),
			self::norm_higher( $satur, 0.0, 1.0 )
		);
		$derived['masterability'] = self::avg_non_empty(
			self::norm_higher( $satur, 0.0, 1.0 ),
			self::norm_lower( $builtup, 500.0, 300000.0 )
		);
		$derived['manageability'] = self::avg_non_empty(
			self::norm_higher( $satur, 0.0, 1.0 ),
			self::norm_higher( $pop, 50000.0, 30000000.0 )
		);

		foreach ( $derived as $dim => $v ) {
			if ( $v === null || $v <= 0 ) {
				continue;
			}
			$out[ $dim ] = round( $v, 2 );
			update_post_meta( $city_id, $keys[ $dim ], $out[ $dim ] );
		}

		return $out;
	}

	private static function norm_higher( float $value, float $min, float $max ): ?float {
		if ( $value <= 0 || $max <= $min ) {
			return null;
		}
		$s = 100.0 * ( $value - $min ) / ( $max - $min );
		return round( max( 0.0, min( 100.0, $s ) ), 2 );
	}

	private static function norm_lower( float $value, float $min, float $max ): ?float {
		if ( $value <= 0 || $max <= $min ) {
			return null;
		}
		$s = 100.0 * ( $max - $value ) / ( $max - $min );
		return round( max( 0.0, min( 100.0, $s ) ), 2 );
	}

	private static function avg_non_empty( ?float ...$vals ): ?float {
		$acc = [];
		foreach ( $vals as $v ) {
			if ( $v !== null && $v > 0 ) {
				$acc[] = $v;
			}
		}
		if ( empty( $acc ) ) {
			return null;
		}
		return round( array_sum( $acc ) / count( $acc ), 2 );
	}

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
		// Согласовано с WSErgo_City_Bridge::compute_and_store_city_leaf_index() и пересчётом в Cities:
		// сохранённая карта полей может быть пустой — тогда WSErgo_City_Bridge::get_effective_field_map() подставляет встроенную карту и city_*-определения.
		$computed = WSErgo_City_Bridge::compute_and_store_city_leaf_index( $city_id );
		return $computed !== null && $computed > 0 ? round( $computed, 2 ) : null;
	}

	/**
	 * Детальный срез по городу для публичного блока: E, оси, листовые показатели (сырое → балл).
	 *
	 * @return array<string, mixed>
	 */
	public static function get_city_leaf_public_detail( int $city_id ): array {
		if ( $city_id <= 0 ) {
			return [];
		}
		$title = html_entity_decode( (string) get_the_title( $city_id ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$leaf  = self::get_city_ergo_index( $city_id );
		$leaf_e = ( $leaf !== null && $leaf > 0 && is_finite( (float) $leaf ) ) ? round( (float) $leaf, 2 ) : 0.0;

		$source_by_ind = [];
		if ( class_exists( 'WSErgo_City_Bridge' ) ) {
			foreach ( WSErgo_City_Bridge::get_effective_field_map() as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$iid = isset( $row['indicator_id'] ) ? sanitize_key( (string) $row['indicator_id'] ) : '';
				$src = isset( $row['source'] ) ? (string) $row['source'] : '';
				if ( $iid !== '' && $src !== '' ) {
					$source_by_ind[ $iid ] = $src;
				}
			}
		}

		$raw = [];
		if ( class_exists( 'WSErgo_City_Bridge' ) && WSErgo_City_Bridge::is_city_import_ergo_enabled() ) {
			WSErgo_City_Bridge::compute_and_store_city_leaf_index( $city_id );
			$raw = WSErgo_City_Bridge::collect_raw_for_city( $city_id );
		}

		$indicators = [];
		$axes       = [];

		if ( ! empty( $raw ) && class_exists( 'WSErgo_Indicators' ) ) {
			$defs = WSErgo_Indicators::get_definitions_for_raw_map( $raw );
			foreach ( $defs as $def ) {
				$id = (string) ( $def['id'] ?? '' );
				if ( $id === '' || ! isset( $raw[ $id ] ) ) {
					continue;
				}
				$rawf = (float) $raw[ $id ];
				$norm = WSErgo_Indicators::normalize_to_score( $def, $rawf );
				$indicators[] = [
					'id'            => $id,
					'label'         => (string) ( $def['label'] ?? $id ),
					'dimension'     => (string) ( $def['dimension'] ?? '' ),
					'unit'          => (string) ( $def['unit'] ?? '' ),
					'raw_value'     => $rawf,
					'score_value'   => $norm !== null ? round( (float) $norm, 2 ) : null,
					'source_field'  => $source_by_ind[ $id ] ?? '',
				];
			}
			$scores = WSErgo_Indicators::build_dimension_scores_from_raw_map( $raw );
			$axes   = self::merge_city_explorer_macro_axis_keys( $scores );
		} else {
			$sub = self::get_city_import_subindices( $city_id );
			foreach ( $sub as $k => $v ) {
				if ( is_numeric( $v ) ) {
					$axes[ (string) $k ] = round( (float) $v, 2 );
				}
			}
			$axes = self::merge_city_explorer_macro_axis_keys( $axes );
		}

		// Те же величины, что в сводной таблице городов страны (E и шесть подиндексов).
		$table_idx = self::get_city_ergo_index( $city_id );
		$table_e   = ( $table_idx !== null && $table_idx > 0 && is_finite( (float) $table_idx ) )
			? round( (float) $table_idx, 2 )
			: null;
		$table_sub = self::get_city_import_subindices( $city_id );

		$country_name = (string) get_post_meta( $city_id, 'wscity_country_name', true );
		$country_iso2 = strtoupper( (string) get_post_meta( $city_id, 'wscity_country_iso2', true ) );

		return [
			'id'                => $city_id,
			'name'              => $title,
			'country_name'      => $country_name,
			'country_iso2'      => $country_iso2,
			'leaf_e'            => $leaf_e,
			'table_e'           => $table_e,
			'table_subindices'  => $table_sub,
			'indicators'        => $indicators,
			'axes'              => $axes,
		];
	}

	/**
	 * @param array<string, float> $scores_by_dimension_or_sub
	 * @return array<string, float>
	 */
	private static function merge_city_explorer_macro_axis_keys( array $scores_by_dimension_or_sub ): array {
		$out = $scores_by_dimension_or_sub;
		$map = [
			WSErgo_Model::DIM_FUNCTIONALITY   => 'F',
			WSErgo_Model::DIM_COMFORT        => 'Cm',
			WSErgo_Model::DIM_LIVABILITY     => 'H',
			WSErgo_Model::DIM_MASTERABILITY  => 'A',
			WSErgo_Model::DIM_SAFETY         => 'S',
			WSErgo_Model::DIM_MANAGEABILITY  => 'Ct',
		];
		foreach ( $map as $dim => $letter ) {
			if ( isset( $out[ $dim ] ) && is_numeric( $out[ $dim ] ) && (float) $out[ $dim ] > 0 ) {
				$out[ $letter ] = round( (float) $out[ $dim ], 2 );
			}
		}
		return $out;
	}

	/**
	 * Данные для блока «город + регрессия + рекомендации» на странице страны (подвкладка «Города»).
	 *
	 * @return array{cities: array<string, array<string, mixed>>, regression: array<string, mixed>}
	 */
	public static function get_country_city_explorer_payload( string $iso2 ): array {
		$iso2 = strtoupper( sanitize_text_field( $iso2 ) );
		$cities = [];
		if ( class_exists( 'WSCities_CPT' ) && method_exists( 'WSCities_CPT', 'get_cities_for_country' ) ) {
			$tmp = WSCities_CPT::get_cities_for_country( $iso2 );
			$cities = is_array( $tmp ) ? $tmp : [];
		}
		$max = (int) apply_filters( 'wsergo_country_city_explorer_max_cities', 300 );
		if ( $max < 1 ) {
			$max = 300;
		}
		if ( count( $cities ) > $max ) {
			$cities = array_slice( $cities, 0, $max );
		}
		$ids = [];
		foreach ( $cities as $c ) {
			$cid = (int) ( $c['id'] ?? 0 );
			if ( $cid > 0 ) {
				$ids[] = $cid;
			}
		}

		return self::build_city_explorer_payload_from_ids(
			array_map( 'intval', array_column( $cities, 'id' ) ),
			[
				'scope'  => 'country',
				'iso2'   => $iso2,
				'notice' => __( 'Для регрессии по стране нужно не меньше пяти городов с рассчитанным листовым E и баллами показателей.', 'worldstat-ergonomics' ),
			]
		);
	}

	/**
	 * Города всех стран с рассчитанным листовым E (для межстранового сравнения).
	 *
	 * @return array{cities: array<string, array<string, mixed>>, regression: array<string, mixed>, scope: string}
	 */
	public static function get_global_city_explorer_payload(): array {
		return self::get_global_city_index();
	}

	/**
	 * Лёгкий список всех городов с E (для поиска и выбора в межстрановом сравнении).
	 *
	 * @return array{cities: array<string, array<string, mixed>>, regression: array<string, mixed>, scope: string, total: int}
	 */
	public static function get_global_city_index(): array {
		global $wpdb;

		if ( ! class_exists( 'WSCities_CPT' ) || ! class_exists( 'WSErgo_City_Bridge' ) ) {
			return [
				'cities'     => [],
				'regression' => [ 'usable' => false, 'notice' => __( 'Модуль городов недоступен.', 'worldstat-ergonomics' ) ],
				'scope'      => 'global',
				'total'      => 0,
			];
		}

		$meta_key = WSErgo_City_Bridge::META_LEAF_INDEX;
		$rows     = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID AS id, p.post_title AS name,
					cn.meta_value AS country_name,
					iso.meta_value AS country_iso2,
					CAST( e.meta_value AS DECIMAL(12,4) ) AS leaf_e
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} e ON e.post_id = p.ID AND e.meta_key = %s
				 LEFT JOIN {$wpdb->postmeta} cn ON cn.post_id = p.ID AND cn.meta_key = 'wscity_country_name'
				 LEFT JOIN {$wpdb->postmeta} iso ON iso.post_id = p.ID AND iso.meta_key = 'wscity_country_iso2'
				 WHERE p.post_type = %s AND p.post_status = 'publish'
				   AND CAST( e.meta_value AS DECIMAL(12,4) ) > 0
				 ORDER BY p.post_title ASC",
				$meta_key,
				WSCities_CPT::SLUG
			),
			ARRAY_A
		);

		$cities = [];
		foreach ( is_array( $rows ) ? $rows : [] as $row ) {
			$cid = (int) ( $row['id'] ?? 0 );
			if ( $cid <= 0 ) {
				continue;
			}
			$leaf = (float) ( $row['leaf_e'] ?? 0 );
			$cities[ (string) $cid ] = [
				'id'           => $cid,
				'name'         => html_entity_decode( (string) ( $row['name'] ?? '' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
				'country_name' => html_entity_decode( (string) ( $row['country_name'] ?? '' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
				'country_iso2' => strtoupper( (string) ( $row['country_iso2'] ?? '' ) ),
				'leaf_e'       => round( $leaf, 2 ),
			];
		}

		$n = count( $cities );
		return [
			'cities'     => $cities,
			'scope'      => 'global',
			'total'      => $n,
			'regression' => [
				'usable'      => $n >= 5,
				'notice'      => $n >= 5 ? '' : __( 'Для регрессии по всем городам нужно не меньше пяти городов с рассчитанным E.', 'worldstat-ergonomics' ),
				'n_leaf'      => $n,
				'median_leaf' => null,
			],
		];
	}

	/**
	 * Полные данные для сравнения 2–4 выбранных городов.
	 *
	 * @param array<int> $city_ids
	 * @return array{cities: array<string, array<string, mixed>>, scope: string}|array{error: string}
	 */
	public static function get_cities_compare_payload( array $city_ids ): array {
		$city_ids = array_values(
			array_unique(
				array_filter(
					array_map( 'intval', $city_ids ),
					static function ( int $id ): bool {
						return $id > 0;
					}
				)
			)
		);
		if ( count( $city_ids ) < 2 ) {
			return [
				'error' => __( 'Укажите минимум два города для сравнения.', 'worldstat-ergonomics' ),
			];
		}
		if ( count( $city_ids ) > 4 ) {
			$city_ids = array_slice( $city_ids, 0, 4 );
		}

		$payload = [];
		foreach ( $city_ids as $cid ) {
			$row = self::get_city_leaf_public_detail( (int) $cid );
			if ( ! empty( $row['id'] ) ) {
				$payload[ (string) (int) $row['id'] ] = $row;
			}
		}

		if ( count( $payload ) < 2 ) {
			return [
				'error' => __( 'Не удалось загрузить данные по выбранным городам. Проверьте, что у них рассчитан индекс E.', 'worldstat-ergonomics' ),
			];
		}

		$country_keys = [];
		foreach ( $payload as $row ) {
			$iso2 = strtoupper( (string) ( $row['country_iso2'] ?? '' ) );
			$key  = $iso2 !== '' ? $iso2 : mb_strtolower( trim( (string) ( $row['country_name'] ?? '' ) ) );
			if ( $key === '' ) {
				continue;
			}
			$country_keys[ $key ] = ( $country_keys[ $key ] ?? 0 ) + 1;
		}
		foreach ( $country_keys as $count ) {
			if ( $count > 1 ) {
				return [
					'error' => __( 'Сравнение возможно только между городами разных стран. Выберите города из разных стран.', 'worldstat-ergonomics' ),
				];
			}
		}

		return [
			'cities' => $payload,
			'scope'  => 'compare',
			'help'   => self::build_compare_help_payload(),
		];
	}

	/**
	 * Справочник измерений и показателей для пояснений в блоке сравнения.
	 *
	 * @return array{dimensions: array<string, string>, indicators: array<string, array<string, mixed>>}
	 */
	private static function build_compare_help_payload(): array {
		$dimensions = class_exists( 'WSErgo_Model' ) ? WSErgo_Model::get_dimension_descriptions() : [];
		$indicators = [];

		if ( class_exists( 'WSErgo_Indicators' ) ) {
			foreach ( WSErgo_Indicators::get_definitions() as $def ) {
				$id = isset( $def['id'] ) ? (string) $def['id'] : '';
				if ( $id === '' ) {
					continue;
				}
				$indicators[ $id ] = [
					'label'     => (string) ( $def['label'] ?? $id ),
					'dimension' => (string) ( $def['dimension'] ?? '' ),
					'unit'      => (string) ( $def['unit'] ?? '' ),
					'vmin'      => (float) ( $def['vmin'] ?? 0 ),
					'vmax'      => (float) ( $def['vmax'] ?? 100 ),
					'direction' => (string) ( $def['direction'] ?? 'higher_better' ),
				];
			}
		}

		if ( class_exists( 'WSErgo_City_Defaults' ) ) {
			foreach ( WSErgo_City_Defaults::default_indicator_definitions() as $def ) {
				if ( ! is_array( $def ) ) {
					continue;
				}
				$id = isset( $def['id'] ) ? sanitize_key( (string) $def['id'] ) : '';
				if ( $id === '' || isset( $indicators[ $id ] ) ) {
					continue;
				}
				$indicators[ $id ] = [
					'label'     => (string) ( $def['label'] ?? $id ),
					'dimension' => (string) ( $def['dimension'] ?? '' ),
					'unit'      => (string) ( $def['unit'] ?? '' ),
					'vmin'      => isset( $def['vmin'] ) ? (float) $def['vmin'] : 0.0,
					'vmax'      => isset( $def['vmax'] ) ? (float) $def['vmax'] : 100.0,
					'direction' => isset( $def['direction'] ) && 'lower_better' === $def['direction'] ? 'lower_better' : 'higher_better',
				];
			}
		}

		return [
			'dimensions' => $dimensions,
			'indicators' => $indicators,
		];
	}

	/**
	 * @param array<int> $city_ids
	 * @param array{scope?: string, iso2?: string, notice?: string} $context
	 * @return array{cities: array<string, array<string, mixed>>, regression: array<string, mixed>, scope: string, iso2?: string}
	 */
	private static function build_city_explorer_payload_from_ids( array $city_ids, array $context = [] ): array {
		$payload = [];
		foreach ( $city_ids as $cid ) {
			$cid = (int) $cid;
			if ( $cid <= 0 ) {
				continue;
			}
			$payload[ (string) $cid ] = self::get_city_leaf_public_detail( $cid );
		}

		$scope = isset( $context['scope'] ) ? (string) $context['scope'] : 'country';
		$regression = class_exists( 'WSErgo_City_Regression' )
			? WSErgo_City_Regression::analyze_country_payload(
				$payload,
				[ 'scope' => $scope ]
			)
			: [
				'usable' => false,
				'notice' => __( 'Модуль анализа недоступен.', 'worldstat-ergonomics' ),
				'n_leaf' => 0,
			];

		if ( ! empty( $context['notice'] ) && empty( $regression['notice'] ) && empty( $regression['usable'] ) ) {
			$regression['notice'] = (string) $context['notice'];
		}

		foreach ( $payload as $cid_str => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$payload[ $cid_str ]['recommendations'] = class_exists( 'WSErgo_City_Regression' )
				? WSErgo_City_Regression::recommendations_for_city( (int) $cid_str, $row, $regression )
				: [];
		}

		$out = [
			'cities'     => $payload,
			'regression' => $regression,
			'scope'      => $scope,
		];
		if ( ! empty( $context['iso2'] ) ) {
			$out['iso2'] = strtoupper( (string) $context['iso2'] );
		}

		return $out;
	}
}
