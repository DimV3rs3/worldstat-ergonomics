<?php
/**
 * Манифест уровня «здание / придомовая» (мост Courtyard → wsp_building / wsp_yard).
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'id'        => 'building',
	'label'     => __( 'Здание', 'worldstat-ergonomics' ),
	'admin_nav' => __( 'Здание (OSM)', 'worldstat-ergonomics' ),
	'prefix'    => 'building',
	'requires'  => [
		'class-courtyard-bridge.php',
		'class-admin-panel.php',
	],
	'bootstrap' => 'bootstrap.php',
];
