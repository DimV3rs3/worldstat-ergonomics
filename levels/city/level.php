<?php
/**
 * Манифест уровня «город».
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'id'       => 'city',
	'label'    => __( 'Город', 'worldstat-ergonomics' ),
	'prefix'   => 'city',
	'requires' => [
		'class-settings.php',
		'class-bridge.php',
		'class-defaults.php',
		'class-regression.php',
		'class-data.php',
		'class-explorer.php',
		'class-renderer.php',
		'ajax.php',
	],
	'bootstrap' => 'bootstrap.php',
];
