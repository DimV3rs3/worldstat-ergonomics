<?php
/**
 * Фасад данных (обратная совместимость): делегирует уровням city / country.
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_Data {

	public static function get_city_import_subindices( int $city_id ): array {
		return WSErgo_City_Data::get_city_import_subindices( $city_id );
	}

	public static function get_country_import_subindices( string $iso2 ): array {
		return WSErgo_Country_Data::get_country_import_subindices( $iso2 );
	}

	/**
	 * @param list<string> $iso2_list
	 * @return array<string,float>
	 */
	public static function bulk_country_ergo_index_for_iso2_list( array $iso2_list ): array {
		return WSErgo_Country_Data::bulk_country_ergo_index_for_iso2_list( $iso2_list );
	}

	public static function get_city_ergo_index( int $city_id ): ?float {
		return WSErgo_City_Data::get_city_ergo_index( $city_id );
	}

	/**
	 * @return array<int, float>
	 */
	public static function get_country_city_indices( string $iso2 ): array {
		return WSErgo_Country_Data::get_country_city_indices( $iso2 );
	}

	public static function get_region_ergo_index( string $iso2, string $region_name ): ?float {
		return WSErgo_Country_Data::get_region_ergo_index( $iso2, $region_name );
	}

	/**
	 * @return string[]
	 */
	public static function list_regions_for_country( string $iso2 ): array {
		return WSErgo_Country_Data::list_regions_for_country( $iso2 );
	}

	public static function get_country_ergo_index( string $iso2 ): float {
		return WSErgo_Country_Data::get_country_ergo_index( $iso2 );
	}

	public static function get_country_districts_count( string $iso2 ): int {
		return WSErgo_Country_Data::get_country_districts_count( $iso2 );
	}

	public static function get_country_buildings_count( string $iso2 ): int {
		return WSErgo_Country_Data::get_country_buildings_count( $iso2 );
	}

	/**
	 * @return array<string, float>
	 */
	public static function get_map_data(): array {
		return WSErgo_Country_Data::get_map_data();
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_all_building_markers(): array {
		return WSErgo_Country_Data::get_all_building_markers();
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_country_building_markers( string $iso2 ): array {
		return WSErgo_Country_Data::get_country_building_markers( $iso2 );
	}

	/**
	 * @return array<string, float>
	 */
	public static function get_subindices_for_post( int $post_id ): array {
		return WSErgo_Country_Data::get_subindices_for_post( $post_id );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_city_leaf_public_detail( int $city_id ): array {
		return WSErgo_City_Data::get_city_leaf_public_detail( $city_id );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_country_city_explorer_payload( string $iso2 ): array {
		return WSErgo_City_Data::get_country_city_explorer_payload( $iso2 );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_global_city_explorer_payload(): array {
		return WSErgo_City_Data::get_global_city_explorer_payload();
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_global_city_index(): array {
		return WSErgo_City_Data::get_global_city_index();
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_cities_compare_payload( array $city_ids ): array {
		return WSErgo_City_Data::get_cities_compare_payload( $city_ids );
	}
}
