<?php
/**
 * Хуки уровня «город».
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', [ 'WSErgo_City_Renderer', 'enqueue_public_assets' ], 20 );
add_action( 'wp_enqueue_scripts', [ 'WSErgo_City_Explorer', 'enqueue_explorer_assets' ], 21 );

add_action( 'wp_ajax_wsergo_city_explorer_payload', [ 'WSErgo_City_Explorer', 'ajax_explorer_payload' ] );
add_action( 'wp_ajax_nopriv_wsergo_city_explorer_payload', [ 'WSErgo_City_Explorer', 'ajax_explorer_payload' ] );
add_action( 'wp_ajax_wsergo_city_compare', [ 'WSErgo_City_Explorer', 'ajax_compare' ] );
add_action( 'wp_ajax_nopriv_wsergo_city_compare', [ 'WSErgo_City_Explorer', 'ajax_compare' ] );

add_action(
	'init',
	static function () {
		add_shortcode( 'wsergo_city_e', [ 'WSErgo_City_Renderer', 'shortcode_city_e' ] );
	},
	20
);

add_action( 'wsp_city_after_stats', [ 'WSErgo_City_Renderer', 'render_compact_card' ], 10, 2 );
add_action( 'worldstat_after_city', [ 'WSErgo_City_Renderer', 'render_section' ], 10, 2 );

add_action(
	'plugins_loaded',
	static function () {
		if ( class_exists( 'WSErgo_City_Defaults' ) ) {
			WSErgo_City_Defaults::maybe_seed_bundled_city_package();
		}
	},
	35
);

add_action(
	'init',
	static function () {
		if ( ! class_exists( 'WSCities_CPT' ) || ! class_exists( 'WSErgo_City_Bridge' ) ) {
			return;
		}
		register_post_meta(
			WSCities_CPT::SLUG,
			WSErgo_City_Bridge::META_LEAF_INDEX,
			[
				'type'         => 'number',
				'single'       => true,
				'show_in_rest' => false,
			]
		);
	},
	15
);

add_action(
	'save_post_' . WSCities_CPT::SLUG,
	static function ( int $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( class_exists( 'WSErgo_City_Bridge' ) ) {
			WSErgo_City_Bridge::compute_and_store_city_leaf_index( $post_id );
		}
	},
	30,
	1
);
