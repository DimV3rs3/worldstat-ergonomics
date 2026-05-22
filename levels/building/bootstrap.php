<?php
/**
 * Хуки уровня «здание» — синхронизация с WorldStat Courtyard.
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wsc_building_imported',
	[ 'WSErgo_Courtyard_Bridge', 'on_building_imported' ],
	20,
	2
);

add_action(
	'wsc_buffer_recomputed',
	[ 'WSErgo_Courtyard_Bridge', 'on_buffer_recomputed' ],
	20,
	2
);
