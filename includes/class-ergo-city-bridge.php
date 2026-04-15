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
		return null;
	}

	/**
	 * Собирает сырые значения показателей по карте сопоставления.
	 *
	 * @return array<string, float> indicator_id => raw
	 */
	public static function collect_raw_for_city( int $city_id ): array {
		$out = [];
		foreach ( self::get_field_map() as $row ) {
			$val = self::resolve_source_value( $city_id, $row['source'] );
			if ( $val === null ) {
				continue;
			}
			$out[ $row['indicator_id'] ] = $val;
		}
		return $out;
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
		if ( empty( $raw ) ) {
			delete_post_meta( $city_id, self::META_LEAF_INDEX );
			return null;
		}
		$idx = WSErgo_Calculator::compute_leaf_from_raw_indicator_map( $city_id, $raw );
		if ( $idx !== null && $idx > 0 ) {
			update_post_meta( $city_id, self::META_LEAF_INDEX, $idx );
		} else {
			delete_post_meta( $city_id, self::META_LEAF_INDEX );
		}
		return $idx;
	}
}
