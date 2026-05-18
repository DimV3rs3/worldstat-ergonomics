<?php
/**
 * Настройки уровня «город» (делегирование в WSErgo_Settings).
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_City_Settings {

	public static function get_field_map(): array {
		if ( ! class_exists( 'WSErgo_Settings' ) ) {
			return [];
		}
		$rows = get_option( WSErgo_Settings::OPTION_CITY_FIELD_MAP, [] );
		return is_array( $rows ) ? array_values( $rows ) : [];
	}

	public static function get_csv_bindings(): array {
		if ( ! class_exists( 'WSErgo_Settings' ) ) {
			return [];
		}
		$bindings = get_option( WSErgo_Settings::OPTION_CITY_CSV_BINDINGS, [] );
		return is_array( $bindings ) ? $bindings : [];
	}
}
