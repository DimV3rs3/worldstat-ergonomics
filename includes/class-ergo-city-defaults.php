<?php
/**
 * Готовый набор показателей и сопоставления полей города → индикаторы (включение «из коробки»).
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_City_Defaults {

	public const OPTION_BUNDLE_FLAG = 'wsergo_bundled_city_ergo_v1';

	/**
	 * Однократная установка дефолтов для расчёта E по данным импорта Cities.
	 */
	public static function maybe_seed_bundled_city_package(): void {
		if ( get_option( self::OPTION_BUNDLE_FLAG, '' ) === '1' ) {
			return;
		}

		$seeded = false;

		$defs = get_option( WSErgo_Indicators::OPTION_DEFINITIONS, null );
		if ( ! is_array( $defs ) || count( $defs ) === 0 ) {
			update_option( WSErgo_Indicators::OPTION_DEFINITIONS, self::default_indicator_definitions() );
			$seeded = true;
		}

		$map = get_option( WSErgo_Settings::OPTION_CITY_FIELD_MAP, null );
		if ( ! is_array( $map ) || count( $map ) === 0 ) {
			update_option( WSErgo_Settings::OPTION_CITY_FIELD_MAP, self::default_city_field_map() );
			$seeded = true;
		}

		// Включить расчёт по данным городов, если опция ещё ни разу не задавалась.
		$city_import_opt_key = class_exists( 'WSErgo_City_Bridge' )
			? WSErgo_City_Bridge::get_city_import_option_key()
			: ( defined( 'WSCities_CPT::OPTION_ERGO_FROM_IMPORT' ) ? WSCities_CPT::OPTION_ERGO_FROM_IMPORT : 'wsergo_city_import_ergo_enabled' );
		if ( get_option( $city_import_opt_key, null ) === null ) {
			update_option( $city_import_opt_key, '1' );
			$seeded = true;
		}

		update_option( self::OPTION_BUNDLE_FLAG, '1' );

		if ( $seeded && class_exists( 'WSErgo_City_Bridge' ) && WSErgo_City_Bridge::is_city_import_ergo_enabled() ) {
			self::recalc_all_cities_leaf_index();
		}
	}

	/**
	 * При активации плагина — установка пакета и пересчёт (см. maybe_seed_bundled_city_package).
	 */
	public static function on_plugin_activation(): void {
		self::maybe_seed_bundled_city_package();
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function default_indicator_definitions(): array {
		return [
			[
				'id'         => 'city_walkability',
				'label'      => 'Пешеходность (B&R, 1990–2015)',
				'dimension'  => WSErgo_Model::DIM_FUNCTIONALITY,
				'unit'       => '',
				'vmin'       => 0,
				'vmax'       => 1,
				'direction'  => 'higher_better',
				'weight'     => 1,
			],
			[
				'id'         => 'city_intersect_4way',
				'label'      => 'Доля 4-сторонних перекрёстков (%)',
				'dimension'  => WSErgo_Model::DIM_SAFETY,
				'unit'       => '%',
				'vmin'       => 0,
				'vmax'       => 100,
				'direction'  => 'higher_better',
				'weight'     => 1,
			],
			[
				'id'         => 'city_openness',
				'label'      => 'Открытость застройки (фрагментация)',
				'dimension'  => WSErgo_Model::DIM_COMFORT,
				'unit'       => '',
				'vmin'       => 0,
				'vmax'       => 1,
				'direction'  => 'higher_better',
				'weight'     => 1,
			],
			[
				'id'         => 'city_cohesion',
				'label'      => 'Сплочённость (компактность)',
				'dimension'  => WSErgo_Model::DIM_LIVABILITY,
				'unit'       => '',
				'vmin'       => 0,
				'vmax'       => 1,
				'direction'  => 'higher_better',
				'weight'     => 1,
			],
			[
				'id'         => 'city_block_size',
				'label'      => 'Средний размер квартала (га)',
				'dimension'  => WSErgo_Model::DIM_MASTERABILITY,
				'unit'       => 'га',
				'vmin'       => 2,
				'vmax'       => 45,
				'direction'  => 'lower_better',
				'weight'     => 1,
			],
			[
				'id'         => 'city_saturation',
				'label'      => 'Насыщенность застройки',
				'dimension'  => WSErgo_Model::DIM_MANAGEABILITY,
				'unit'       => '',
				'vmin'       => 0,
				'vmax'       => 1,
				'direction'  => 'higher_better',
				'weight'     => 1,
			],
		];
	}

	/**
	 * По одному источнику на измерение; города без Blocks & Roads получат E по мета полям.
	 *
	 * @return array<int, array{source:string, indicator_id:string}>
	 */
	public static function default_city_field_map(): array {
		return [
			[ 'source' => 'br1:walkability:post1990', 'indicator_id' => 'city_walkability' ],
			[ 'source' => 'br1:intersect_4way_share:post1990', 'indicator_id' => 'city_intersect_4way' ],
			[ 'source' => 'meta:wscity_openness', 'indicator_id' => 'city_openness' ],
			[ 'source' => 'meta:wscity_cohesion', 'indicator_id' => 'city_cohesion' ],
			[ 'source' => 'br1:block_size:post1990', 'indicator_id' => 'city_block_size' ],
			[ 'source' => 'meta:wscity_saturation', 'indicator_id' => 'city_saturation' ],
		];
	}

	private static function recalc_all_cities_leaf_index(): void {
		$ids = get_posts(
			[
				'post_type'              => WSCities_CPT::SLUG,
				'post_status'            => 'any',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
			]
		);
		foreach ( $ids as $pid ) {
			WSErgo_City_Bridge::compute_and_store_city_leaf_index( (int) $pid );
		}
	}
}
