<?php
/**
 * Хуки уровня «территория» (район / wsp_district).
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'plugins_loaded',
	static function () {
		if ( class_exists( 'WSErgo_District_Bridge' ) ) {
			WSErgo_District_Bridge::ensure_default_options();
		}
		if ( class_exists( 'WSErgo_Territory_Admin' ) ) {
			WSErgo_Territory_Admin::init();
		}
	},
	25
);

add_action(
	'init',
	static function () {
		if ( class_exists( 'WSErgo_District_Neural_Renderer' ) ) {
			add_shortcode( 'wsergo_district_neural', [ 'WSErgo_District_Neural_Renderer', 'shortcode_district_neural' ] );
		}
		if ( class_exists( 'WSErgo_Territory_Renderer' ) ) {
			add_shortcode( 'district_ergo_index', [ 'WSErgo_Territory_Renderer', 'shortcode_district_ergo_index' ] );
		}
	},
	20
);

add_action(
	'wsp_district_ergo_main_card',
	static function ( int $district_id ) {
		if ( class_exists( 'WSErgo_Territory_Renderer' ) ) {
			WSErgo_Territory_Renderer::render_main_card( $district_id );
		}
	},
	10,
	1
);

add_action(
	'wsp_district_ergo_extras',
	static function ( int $district_id ) {
		if ( class_exists( 'WSErgo_Territory_Renderer' ) ) {
			WSErgo_Territory_Renderer::render_neural_partial( $district_id );
		}
	},
	10,
	1
);

$territory_slug = class_exists( 'WSErgo_District_Bridge' ) ? WSErgo_District_Bridge::district_post_type() : WSErgo_CPT::SLUG_DISTRICT;

add_action(
	'save_post_' . $territory_slug,
	static function ( int $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( class_exists( 'WSErgo_District_Bridge' ) ) {
			WSErgo_District_Bridge::on_district_saved( $post_id );
		}
	},
	30,
	1
);
