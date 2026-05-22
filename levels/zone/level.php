<?php
/**
 * Манифест уровня «зона помещения» (worldstat-zone).
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'id'        => 'zone',
	'label'     => __( 'Зона', 'worldstat-ergonomics' ),
	'admin_nav' => __( 'Зона (помещение)', 'worldstat-ergonomics' ),
	'prefix'    => 'zone',
	'requires'  => [
		'class-bridge.php',
		'class-renderer.php',
		'class-admin-panel.php',
	],
	'bootstrap' => 'bootstrap.php',
];
