<?php
/**
 * Настройки уровня «страна» (делегирование в WSErgo_Settings).
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_Country_Settings {

	public static function get_country_variation(): string {
		return class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::get_country_variation() : 'city_direct';
	}

	public static function get_country_index_source(): string {
		return class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::get_country_index_source() : 'macro_datasets';
	}

	public static function get_macro_reference_year(): int {
		return class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::get_macro_reference_year() : 2022;
	}

	public static function get_macro_k_clusters(): int {
		return class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::get_macro_k_clusters() : 6;
	}
}
