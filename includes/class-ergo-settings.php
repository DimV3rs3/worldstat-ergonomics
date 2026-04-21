<?php
/**
 * Опции: модели DSL, коэффициенты, агрегация по уровням.
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_Settings {

	public const OPTION_MODELS            = 'wsergo_models';
	public const OPTION_ACTIVE_MODEL       = 'wsergo_active_model_id';
	public const OPTION_COEFFICIENTS      = 'wsergo_coefficients';
	public const OPTION_AGGREGATION       = 'wsergo_aggregation';
	public const OPTION_COUNTRY_VARIATION = 'wsergo_country_variation';
	/** Источник индекса страны: макроданные (CSV платформы) или агрегация по городам (legacy). */
	public const OPTION_COUNTRY_INDEX_SOURCE = 'wsergo_country_index_source';
	/** Опорный год для рядов country_code+year в CSV макромодели. */
	public const OPTION_MACRO_REFERENCE_YEAR = 'wsergo_macro_reference_year';
	/** Число кластеров k-means для макромодели (по умолчанию 6). */
	public const OPTION_MACRO_K_CLUSTERS     = 'wsergo_macro_k_clusters';
	/** Сопоставление полей записи wsp_city → id показателя эргономики. */
	public const OPTION_CITY_FIELD_MAP    = 'wsergo_city_field_map';

	/**
	 * Модель по умолчанию: пустая leaf-формула — используется взвешенное среднее из WSErgo_Model.
	 *
	 * @return array<int, array{id:string,name:string,leaf_formula:string}>
	 */
	public static function get_default_models(): array {
		return [
			[
				'id'            => 'default_weighted',
				'name'          => 'Взвешенное среднее (классика)',
				'leaf_formula'  => '',
			],
		];
	}

	/**
	 * @return array<int, array{id:string,name:string,leaf_formula:string}>
	 */
	public static function get_models(): array {
		$stored = get_option( self::OPTION_MODELS, null );
		if ( ! is_array( $stored ) || ! $stored ) {
			return self::get_default_models();
		}
		return $stored;
	}

	/**
	 * @return array{id:string,name:string,leaf_formula:string}|null
	 */
	public static function get_active_model(): ?array {
		$id      = (string) get_option( self::OPTION_ACTIVE_MODEL, 'default_weighted' );
		$models  = self::get_models();
		foreach ( $models as $m ) {
			if ( isset( $m['id'] ) && $m['id'] === $id ) {
				return $m;
			}
		}
		return $models[0] ?? null;
	}

	/**
	 * Глобальные коэффициенты k_* (число).
	 *
	 * @return array<string, float>
	 */
	public static function get_coefficients(): array {
		$stored = get_option( self::OPTION_COEFFICIENTS, [] );
		if ( ! is_array( $stored ) ) {
			$stored = [];
		}
		$out = [ 'k_default' => 1.0 ];
		foreach ( $stored as $k => $v ) {
			$key = sanitize_key( (string) $k );
			if ( $key === '' ) {
				continue;
			}
			if ( substr( $key, 0, 2 ) !== 'k_' ) {
				$key = 'k_' . $key;
			}
			$out[ $key ] = (float) $v;
		}
		return $out;
	}

	/**
	 * @return array{
	 *   room_to_building: string,
	 *   w_building_in_district: float,
	 *   w_yard_in_district: float,
	 *   district_to_city: string,
	 *   city_to_region: string,
	 *   region_to_country: string
	 * }
	 */
	public static function get_aggregation(): array {
		$defaults = [
			'room_to_building'       => 'mean',
			'w_building_in_district' => 0.5,
			'w_yard_in_district'     => 0.5,
			'district_to_city'       => 'mean',
			'city_to_region'         => 'pop_weighted',
			'region_to_country'      => 'pop_weighted',
		];
		$stored = get_option( self::OPTION_AGGREGATION, [] );
		if ( ! is_array( $stored ) ) {
			$stored = [];
		}
		$out = array_merge( $defaults, $stored );
		$sum = (float) $out['w_building_in_district'] + (float) $out['w_yard_in_district'];
		if ( $sum > 0 ) {
			$out['w_building_in_district'] = (float) $out['w_building_in_district'] / $sum;
			$out['w_yard_in_district']     = (float) $out['w_yard_in_district'] / $sum;
		}
		return $out;
	}

	/**
	 * Глобальный режим расчета:
	 * - city_direct: городской (как в исходной версии, страна агрегируется по городам).
	 * - regions: страновой (вариация 2, страна агрегируется через регионы).
	 */
	public static function get_country_variation(): string {
		$mode = (string) get_option( self::OPTION_COUNTRY_VARIATION, 'city_direct' );
		return in_array( $mode, [ 'city_direct', 'regions' ], true ) ? $mode : 'city_direct';
	}

	/**
	 * @return string macro_datasets|city_aggregate
	 */
	public static function get_country_index_source(): string {
		$mode = (string) get_option( self::OPTION_COUNTRY_INDEX_SOURCE, 'macro_datasets' );
		return in_array( $mode, [ 'macro_datasets', 'city_aggregate' ], true ) ? $mode : 'macro_datasets';
	}

	public static function get_macro_reference_year(): int {
		$y = (int) get_option( self::OPTION_MACRO_REFERENCE_YEAR, 2022 );
		return max( 1900, min( 2100, $y ) );
	}

	public static function get_macro_k_clusters(): int {
		$k = (int) get_option( self::OPTION_MACRO_K_CLUSTERS, 6 );
		return max( 2, min( 12, $k ) );
	}

	/**
	 * Идентификаторы для DSL (ключ => подпись для UI).
	 *
	 * @return array<string, string>
	 */
	public static function get_leaf_formula_allowed_ids(): array {
		$labels = WSErgo_Model::get_dimension_labels();
		$ids    = [];
		$short  = [
			'F' => WSErgo_Model::DIM_FUNCTIONALITY,
			'S' => WSErgo_Model::DIM_SAFETY,
			'C' => WSErgo_Model::DIM_COMFORT,
			'L' => WSErgo_Model::DIM_LIVABILITY,
			'O' => WSErgo_Model::DIM_MASTERABILITY,
			'M' => WSErgo_Model::DIM_MANAGEABILITY,
		];
		foreach ( $short as $letter => $dim ) {
			$ids[ $letter ] = $labels[ $dim ];
		}
		foreach ( WSErgo_Model::DIMENSION_KEYS as $dim ) {
			$ids[ $dim ] = $labels[ $dim ];
		}
		$weights = WSErgo_Model::get_weights();
		foreach ( $weights as $dim => $w ) {
			$ids[ 'w_' . $dim ] = 'w_' . $dim;
		}
		foreach ( self::get_coefficients() as $k => $v ) {
			$ids[ $k ] = $k;
		}
		if ( class_exists( 'WSErgo_Indicators' ) ) {
			foreach ( WSErgo_Indicators::get_definitions() as $def ) {
				$key           = 'i_' . $def['id'];
				$ids[ $key ] = $def['label'];
			}
		}
		return $ids;
	}
}
