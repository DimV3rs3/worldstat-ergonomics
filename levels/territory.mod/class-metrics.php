<?php
/**
 * Классические (не «нейросетевые») шесть измерений района из мета wsdistrict_*.
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_District_Metrics {

	/**
	 * @param int $district_id ID записи wsp_district.
	 * @return array{safety:float,functionality:float,comfort:float,manageability:float,livability:float,masterability:float}
	 */
	public static function get_all_metrics( int $district_id ): array {
		return [
			'safety'          => (float) get_post_meta( $district_id, 'wsdistrict_safety_score', true ),
			'functionality'   => (float) get_post_meta( $district_id, 'wsdistrict_functionality_score', true ),
			'comfort'         => (float) get_post_meta( $district_id, 'wsdistrict_comfort_score', true ),
			'manageability'   => (float) get_post_meta( $district_id, 'wsdistrict_manageability_score', true ),
			'livability'      => (float) get_post_meta( $district_id, 'wsdistrict_livability_score', true ),
			'masterability'   => (float) get_post_meta( $district_id, 'wsdistrict_masterability_score', true ),
		];
	}
}
