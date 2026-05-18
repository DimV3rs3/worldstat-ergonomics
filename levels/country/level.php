<?php
/**
 * Манифест уровня «страна».
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'id'       => 'country',
	'label'    => __( 'Страна', 'worldstat-ergonomics' ),
	'admin_nav' => __( 'Эргономичность страны', 'worldstat-ergonomics' ),
	'prefix'   => 'country',
	'requires' => [
		'class-settings.php',
		'class-macro-calculator.php',
		'class-macro-cluster-optimizer.php',
		'class-macro-recommendations.php',
		'class-data.php',
		'class-renderer.php',
		'class-admin.php',
		'ajax.php',
	],
	'bootstrap' => 'bootstrap.php',
];
