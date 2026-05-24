<?php
/**
 * Хуки уровня «зона» (wsz_zone).
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wsz_zone_ergo_summary',
	static function ( int $zone_id ) {
		if ( class_exists( 'WSErgo_Zone_Renderer' ) ) {
			WSErgo_Zone_Renderer::render_single_summary( $zone_id );
		}
	},
	10,
	1
);
