<?php
/**
 * Манифест уровня «территория» (квартал / район).
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'id'        => 'territory',
	'label'     => __( 'Территория', 'worldstat-ergonomics' ),
	'admin_nav' => __( 'Территория (район)', 'worldstat-ergonomics' ),
	'prefix'    => 'territory',
	'requires'  => [
		'class-bridge.php',
		'class-metrics.php',
		'class-neural.php',
		'class-neural-renderer.php',
		'class-district-tab.php',
		'class-renderer.php',
		'class-admin.php',
	],
	'bootstrap' => 'bootstrap.php',
];
