<?php
/**
 * Хуки уровня «территория».
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	static function () {
		if ( class_exists( 'WSErgo_District_Neural_Renderer' ) ) {
			add_shortcode( 'wsergo_district_neural', [ 'WSErgo_District_Neural_Renderer', 'shortcode_district_neural' ] );
		}
	},
	20
);
