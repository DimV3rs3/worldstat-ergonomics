<?php
/**
 * Данные города (wsp_city) → сырые показатели эргономики и индекс E при отсутствии кварталов.
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_City_Bridge {

	public const META_LEAF_INDEX = 'wsergo_city_leaf_index';
	private const OPTION_ERGO_FROM_IMPORT_FALLBACK = 'wsergo_city_import_ergo_enabled';
	/** @var array<int, array<int, array<string, string>>> */
	private static $csv_rows_cache = [];
	/** @var array<int, array<string, float>> */
	private static $city_metric_cache = [];

	/**
	 * Ключ опции включения расчёта E по импорту городов.
	 * Совместимо со старыми версиями Cities (без OPTION_ERGO_FROM_IMPORT).
	 */
	public static function get_city_import_option_key(): string {
		if ( class_exists( 'WSCities_CPT' ) && defined( 'WSCities_CPT::OPTION_ERGO_FROM_IMPORT' ) ) {
			return WSCities_CPT::OPTION_ERGO_FROM_IMPORT;
		}
		return self::OPTION_ERGO_FROM_IMPORT_FALLBACK;
	}

	/**
	 * Глобальное включение расчёта E по данным импорта Cities (раздел «Города», не карточка города).
	 */
	public static function is_city_import_ergo_enabled(): bool {
		if ( ! class_exists( 'WSCities_CPT' ) ) {
			return false;
		}
		$opt_key = self::get_city_import_option_key();
		return get_option( $opt_key, '1' ) === '1';
	}

	/**
	 * Карта полей из настроек (только сохранённое в БД; для UI формы).
	 *
	 * @return array<int, array{source:string, indicator_id:string}>
	 */
	public static function get_field_map(): array {
		$stored = get_option( WSErgo_Settings::OPTION_CITY_FIELD_MAP, null );
		if ( ! is_array( $stored ) ) {
			return [];
		}
		$out = [];
		foreach ( $stored as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$src = isset( $row['source'] ) ? sanitize_text_field( (string) $row['source'] ) : '';
			$ind = isset( $row['indicator_id'] ) ? sanitize_key( (string) $row['indicator_id'] ) : '';
			if ( $src === '' || $ind === '' ) {
				continue;
			}
			$out[] = [
				'source'       => $src,
				'indicator_id' => $ind,
			];
		}
		return $out;
	}

	/**
	 * Карта для расчёта: при пустой пользовательской карте — встроенный набор (B&R + мета) из {@see WSErgo_City_Defaults::default_city_field_map()}.
	 *
	 * @return array<int, array{source:string, indicator_id:string}>
	 */
	public static function get_effective_field_map(): array {
		$rows = self::get_field_map();
		if ( ! empty( $rows ) ) {
			return (array) apply_filters( 'wsergo_city_effective_field_map', $rows, false );
		}
		if ( class_exists( 'WSErgo_City_Defaults' ) ) {
			$rows = WSErgo_City_Defaults::default_city_field_map();
		} else {
			$rows = [];
		}
		return (array) apply_filters( 'wsergo_city_effective_field_map', $rows, true );
	}

	/**
	 * Справочник источников для UI: value => подпись.
	 *
	 * @return array<string, string>
	 */
	public static function get_source_choices(): array {
		$choices = [];
		if ( ! class_exists( 'WSCities_CPT' ) ) {
			return $choices;
		}
		$meta_labels = [
			'wscity_lat'              => __( 'Широта', 'worldstat-ergonomics' ),
			'wscity_lng'              => __( 'Долгота', 'worldstat-ergonomics' ),
			'wscity_pop_t3'           => __( 'Население T3', 'worldstat-ergonomics' ),
			'wscity_density_builtup'  => __( 'Плотность по застройке (чел/га)', 'worldstat-ergonomics' ),
			'wscity_density_extent'   => __( 'Плотность по экстенту (чел/га)', 'worldstat-ergonomics' ),
			'wscity_saturation'       => __( 'Насыщенность (saturation)', 'worldstat-ergonomics' ),
			'wscity_openness'         => __( 'Открытость (openness)', 'worldstat-ergonomics' ),
			'wscity_proximity'        => __( 'Близость (proximity)', 'worldstat-ergonomics' ),
			'wscity_cohesion'         => __( 'Связность (cohesion)', 'worldstat-ergonomics' ),
			'wscity_builtup_t3'       => __( 'Застройка T3 (га)', 'worldstat-ergonomics' ),
			'wscity_extent_t3'        => __( 'Городской экстент T3 (га)', 'worldstat-ergonomics' ),
		];
		foreach ( $meta_labels as $key => $label ) {
			$choices[ 'meta:' . $key ] = $label . ' (' . $key . ')';
		}

		$br_metrics = [
			'road_share'           => __( 'Доля дорог в застройке', 'worldstat-ergonomics' ),
			'road_width'           => __( 'Средняя ширина дорог (м)', 'worldstat-ergonomics' ),
			'arterial_density'     => __( 'Плотность магистралей', 'worldstat-ergonomics' ),
			'arterial_distance'    => __( 'Расстояние до магистралей (м)', 'worldstat-ergonomics' ),
			'block_size'           => __( 'Размер квартала (га)', 'worldstat-ergonomics' ),
			'walkability'          => __( 'Walkability', 'worldstat-ergonomics' ),
			'intersect_3way'       => __( 'Плотность Т-перекрёстков', 'worldstat-ergonomics' ),
			'intersect_4way'       => __( 'Плотность 4-сторонних перекрёстков', 'worldstat-ergonomics' ),
			'intersect_4way_share' => __( 'Доля 4-сторонних перекрёстков', 'worldstat-ergonomics' ),
		];
		$periods    = [ 'pre1990', 'post1990' ];
		foreach ( $br_metrics as $metric => $mlabel ) {
			foreach ( $periods as $per ) {
				$key                    = 'br1:' . $metric . ':' . $per;
				$choices[ $key ] = $mlabel . ' — ' . $per . ' (Blocks & Roads T1)';
			}
		}
		if ( class_exists( 'WSErgo_Settings' ) ) {
			foreach ( WSErgo_Settings::city_bindable_metric_keys() as $mk ) {
				$choices[ 'csv:' . $mk ] = __( 'CSV (из БД):', 'worldstat-ergonomics' ) . ' ' . $mk;
			}
		}
		return $choices;
	}

	/**
	 * @param int    $city_id ID записи wsp_city.
	 * @param string $source  meta:KEY или br1:metric:period.
	 */
	public static function resolve_source_value( int $city_id, string $source ): ?float {
		if ( $city_id <= 0 ) {
			return null;
		}
		if ( strpos( $source, 'meta:' ) === 0 ) {
			$key = substr( $source, 5 );
			if ( ! preg_match( '/^wscity_[a-z0-9_]+$/', $key ) ) {
				return null;
			}
			$raw = get_post_meta( $city_id, $key, true );
			if ( $raw === '' || $raw === null ) {
				return null;
			}
			$v = (float) str_replace( ',', '.', (string) $raw );
			return is_finite( $v ) ? $v : null;
		}
		if ( strpos( $source, 'br1:' ) === 0 ) {
			$parts = explode( ':', $source, 3 );
			if ( count( $parts ) !== 3 ) {
				return null;
			}
			$metric = $parts[1];
			$period = $parts[2];
			if ( ! class_exists( 'WSCities_CPT' ) ) {
				return null;
			}
			$br = WSCities_CPT::get_blocks_roads( $city_id );
			if ( ! is_array( $br ) || ! isset( $br[ $metric ] ) || ! is_array( $br[ $metric ] ) ) {
				return null;
			}
			$sub = $br[ $metric ];
			if ( ! isset( $sub[ $period ] ) ) {
				return null;
			}
			$v = (float) str_replace( ',', '.', (string) $sub[ $period ] );
			return is_finite( $v ) ? $v : null;
		}
		if ( strpos( $source, 'csv:' ) === 0 ) {
			$metric = sanitize_key( substr( $source, 4 ) );
			if ( $metric === '' ) {
				return null;
			}
			$map = self::collect_csv_metrics_for_city( $city_id );
			return isset( $map[ $metric ] ) ? (float) $map[ $metric ] : null;
		}
		return null;
	}

	/**
	 * Собирает сырые значения показателей по карте сопоставления.
	 *
	 * @return array<string, float> indicator_id => raw
	 */
	public static function collect_raw_for_city( int $city_id ): array {
		$out = [];
		foreach ( self::get_effective_field_map() as $row ) {
			$val = self::resolve_source_value( $city_id, $row['source'] );
			if ( $val === null ) {
				continue;
			}
			$out[ $row['indicator_id'] ] = $val;
		}
		return $out;
	}

	/**
	 * @return array<string, int> metric => dataset id
	 */
	private static function get_city_csv_bindings(): array {
		if ( ! class_exists( 'WSErgo_Settings' ) ) {
			return [];
		}
		$raw = get_option( WSErgo_Settings::OPTION_CITY_CSV_BINDINGS, [] );
		if ( ! is_array( $raw ) ) {
			return [];
		}
		$allowed = array_flip( WSErgo_Settings::city_bindable_metric_keys() );
		$valid   = WSErgo_Settings::valid_macro_csv_source_ids();
		$out     = [];
		foreach ( $raw as $metric => $dataset_id ) {
			$mk = sanitize_key( (string) $metric );
			$id = (int) $dataset_id;
			if ( $mk !== '' && $id > 0 && isset( $allowed[ $mk ] ) && isset( $valid[ $id ] ) ) {
				$out[ $mk ] = $id;
			}
		}
		return $out;
	}

	/**
	 * @return array<string, float> metric => value
	 */
	private static function collect_csv_metrics_for_city( int $city_id ): array {
		if ( isset( self::$city_metric_cache[ $city_id ] ) ) {
			return self::$city_metric_cache[ $city_id ];
		}
		$bindings = self::get_city_csv_bindings();
		if ( empty( $bindings ) || ! class_exists( 'WorldStat_Uploaded_Csv' ) ) {
			self::$city_metric_cache[ $city_id ] = [];
			return [];
		}
		$city_norm = self::normalize_city_name( (string) get_the_title( $city_id ) );
		$iso2      = strtoupper( (string) get_post_meta( $city_id, 'wscity_country_iso2', true ) );
		$out       = [];
		foreach ( $bindings as $metric => $dataset_id ) {
			$val = self::extract_metric_for_city_from_dataset( $dataset_id, $metric, $city_norm, $iso2 );
			if ( $val !== null ) {
				$out[ $metric ] = $val;
			}
		}
		self::$city_metric_cache[ $city_id ] = $out;
		return $out;
	}

	private static function normalize_city_name( string $name ): string {
		$name = function_exists( 'mb_strtolower' ) ? mb_strtolower( $name ) : strtolower( $name );
		$name = trim( $name );
		$name = preg_replace( '/\s+/u', ' ', $name );
		return (string) $name;
	}

	private static function normalize_csv_key( string $key ): string {
		$key = function_exists( 'mb_strtolower' ) ? mb_strtolower( $key ) : strtolower( $key );
		$key = trim( $key );
		$key = str_replace( [ ' ', '-', '.', '/', '\\' ], '_', $key );
		$key = preg_replace( '/_+/', '_', $key );
		return sanitize_key( $key );
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	private static function get_csv_rows( int $dataset_id ): array {
		if ( isset( self::$csv_rows_cache[ $dataset_id ] ) ) {
			return self::$csv_rows_cache[ $dataset_id ];
		}
		$body = (string) WorldStat_Uploaded_Csv::get_body_by_id( $dataset_id );
		if ( $body === '' ) {
			self::$csv_rows_cache[ $dataset_id ] = [];
			return [];
		}
		$lines = preg_split( '/\r\n|\n|\r/', $body );
		if ( ! is_array( $lines ) || count( $lines ) < 2 ) {
			self::$csv_rows_cache[ $dataset_id ] = [];
			return [];
		}
		$header_line = (string) $lines[0];
		$semicolon = substr_count( $header_line, ';' );
		$comma     = substr_count( $header_line, ',' );
		$delimiter = $semicolon > $comma ? ';' : ',';
		$header_raw = str_getcsv( $header_line, $delimiter );
		if ( ! is_array( $header_raw ) || empty( $header_raw ) ) {
			self::$csv_rows_cache[ $dataset_id ] = [];
			return [];
		}
		$headers = [];
		foreach ( $header_raw as $h ) {
			$headers[] = self::normalize_csv_key( (string) $h );
		}
		$out = [];
		for ( $i = 1, $n = count( $lines ); $i < $n; $i++ ) {
			$line = (string) $lines[ $i ];
			if ( trim( $line ) === '' ) {
				continue;
			}
			$cells = str_getcsv( $line, $delimiter );
			if ( ! is_array( $cells ) ) {
				continue;
			}
			$row = [];
			foreach ( $headers as $ix => $key ) {
				$row[ $key ] = isset( $cells[ $ix ] ) ? trim( (string) $cells[ $ix ] ) : '';
			}
			$out[] = $row;
		}
		self::$csv_rows_cache[ $dataset_id ] = $out;
		return $out;
	}

	private static function extract_metric_for_city_from_dataset( int $dataset_id, string $metric, string $city_norm, string $iso2 ): ?float {
		$rows = self::get_csv_rows( $dataset_id );
		if ( empty( $rows ) ) {
			return null;
		}
		$metric_key = self::normalize_csv_key( $metric );
		$best = null;
		$best_year = -1;
		foreach ( $rows as $row ) {
			$city_raw = '';
			foreach ( [ 'city', 'city_name', 'name', 'settlement', 'locality' ] as $k ) {
				if ( isset( $row[ $k ] ) && $row[ $k ] !== '' ) {
					$city_raw = (string) $row[ $k ];
					break;
				}
			}
			if ( $city_raw === '' || self::normalize_city_name( $city_raw ) !== $city_norm ) {
				continue;
			}
			if ( $iso2 !== '' ) {
				$iso_row = '';
				foreach ( [ 'country_iso2', 'iso2', 'country', 'country_code' ] as $ik ) {
					if ( isset( $row[ $ik ] ) && $row[ $ik ] !== '' ) {
						$iso_row = strtoupper( trim( (string) $row[ $ik ] ) );
						break;
					}
				}
				if ( $iso_row !== '' && $iso_row !== $iso2 ) {
					continue;
				}
			}
			$val = null;
			if ( isset( $row[ $metric_key ] ) && $row[ $metric_key ] !== '' ) {
				$v = (float) str_replace( ',', '.', (string) $row[ $metric_key ] );
				$val = is_finite( $v ) ? $v : null;
			} elseif ( isset( $row['value'] ) ) {
				$indicator = isset( $row['indicator'] ) ? self::normalize_csv_key( (string) $row['indicator'] ) : ( isset( $row['metric'] ) ? self::normalize_csv_key( (string) $row['metric'] ) : '' );
				if ( $indicator !== '' && ( $indicator === $metric_key || strpos( $indicator, $metric_key ) !== false ) ) {
					$v = (float) str_replace( ',', '.', (string) $row['value'] );
					$val = is_finite( $v ) ? $v : null;
				}
			}
			if ( $val === null ) {
				continue;
			}
			$year = 0;
			if ( isset( $row['year'] ) && is_numeric( $row['year'] ) ) {
				$year = (int) $row['year'];
			}
			if ( $best === null || $year >= $best_year ) {
				$best = $val;
				$best_year = $year;
			}
		}
		return $best;
	}

	/**
	 * Пересчёт листового E по данным города (без кварталов).
	 */
	public static function compute_and_store_city_leaf_index( int $city_id ): ?float {
		if ( $city_id <= 0 || ! class_exists( 'WSCities_CPT' ) ) {
			return null;
		}
		if ( ! self::is_city_import_ergo_enabled() ) {
			delete_post_meta( $city_id, self::META_LEAF_INDEX );
			return null;
		}
		$raw = self::collect_raw_for_city( $city_id );
		$idx = null;
		if ( ! empty( $raw ) ) {
			$idx = WSErgo_Calculator::compute_leaf_from_raw_indicator_map( $city_id, $raw );
		}
		if ( $idx === null || $idx <= 0 ) {
			$idx = self::compute_leaf_index_meta_fallback( $city_id );
		}
		if ( $idx !== null && $idx > 0 ) {
			update_post_meta( $city_id, self::META_LEAF_INDEX, $idx );
		} else {
			delete_post_meta( $city_id, self::META_LEAF_INDEX );
		}
		return $idx;
	}

	/**
	 * Coverage fallback for cities with sparse ergonomics mapping:
	 * compute E from common imported city meta fields.
	 */
	private static function compute_leaf_index_meta_fallback( int $city_id ): ?float {
		$vals = [];

		$open = self::resolve_source_value( $city_id, 'meta:wscity_openness' );
		if ( $open !== null ) {
			$vals[] = self::norm_higher( $open, 0.0, 1.0 );
		}

		$cohesion = self::resolve_source_value( $city_id, 'meta:wscity_cohesion' );
		if ( $cohesion !== null ) {
			$vals[] = self::norm_higher( $cohesion, 0.0, 1.0 );
		}

		$saturation = self::resolve_source_value( $city_id, 'meta:wscity_saturation' );
		if ( $saturation !== null ) {
			$vals[] = self::norm_higher( $saturation, 0.0, 1.0 );
		}

		$proximity = self::resolve_source_value( $city_id, 'meta:wscity_proximity' );
		if ( $proximity !== null ) {
			$vals[] = self::norm_higher( $proximity, 0.0, 1.0 );
		}

		$dBuilt = self::resolve_source_value( $city_id, 'meta:wscity_density_builtup' );
		if ( $dBuilt !== null ) {
			$vals[] = self::norm_lower( $dBuilt, 20.0, 400.0 );
		}

		$dExtent = self::resolve_source_value( $city_id, 'meta:wscity_density_extent' );
		if ( $dExtent !== null ) {
			$vals[] = self::norm_lower( $dExtent, 10.0, 250.0 );
		}

		$pop = self::resolve_source_value( $city_id, 'meta:wscity_pop_t3' );
		if ( $pop !== null ) {
			$vals[] = self::norm_higher( $pop, 50000.0, 30000000.0 );
		}

		if ( empty( $vals ) ) {
			return null;
		}
		return round( array_sum( $vals ) / count( $vals ), 2 );
	}

	private static function norm_higher( float $value, float $min, float $max ): float {
		if ( $max <= $min ) {
			return 0.0;
		}
		$s = 100.0 * ( $value - $min ) / ( $max - $min );
		return round( max( 0.0, min( 100.0, $s ) ), 2 );
	}

	private static function norm_lower( float $value, float $min, float $max ): float {
		if ( $max <= $min ) {
			return 0.0;
		}
		$s = 100.0 * ( $max - $value ) / ( $max - $min );
		return round( max( 0.0, min( 100.0, $s ) ), 2 );
	}
}
