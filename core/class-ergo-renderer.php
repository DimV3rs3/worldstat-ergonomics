<?php
/**
 * Фасад рендерера (обратная совместимость).
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_Renderer {

	public static function enqueue_public_assets(): void {
		WSErgo_City_Renderer::enqueue_public_assets();
	}

	public static function ajax_city_explorer_payload(): void {
		WSErgo_City_Explorer::ajax_explorer_payload();
	}

	public static function ajax_city_compare(): void {
		WSErgo_City_Explorer::ajax_compare();
	}

	public static function render_city_compact_card( int $post_id, array $meta ): void {
		WSErgo_City_Renderer::render_compact_card( $post_id, $meta );
	}

	/**
	 * @param array<string, string>|string $atts
	 */
	public static function shortcode_city_e( $atts ): string {
		return WSErgo_City_Renderer::shortcode_city_e( $atts );
	}

	public static function render_country_tab( string $country_code ): void {
		if ( class_exists( 'WSErgo_City_Country_Integration' ) ) {
			WSErgo_City_Country_Integration::render_country_tab( $country_code );
			return;
		}
		WSErgo_Country_Renderer::render_country_tab( $country_code );
	}

	public static function ajax_load_country_city_explorer(): void {
		WSErgo_Country_Renderer::ajax_load_country_city_explorer();
	}

	public static function ajax_load_country_city_macro(): void {
		WSErgo_Country_Renderer::ajax_load_country_city_macro();
	}

	public static function render_city_section( int $post_id, array $meta ): void {
		WSErgo_City_Renderer::render_section( $post_id, $meta );
	}
}
