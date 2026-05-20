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
	'id'       => 'territory',
	'label'    => __( 'Территория', 'worldstat-ergonomics' ),
	'prefix'   => 'territory',
	'requires' => [
		'class-metrics.php',
		'class-neural.php',
		'class-neural-renderer.php',
		'class-district-tab.php',
	],
	'bootstrap' => 'bootstrap.php',
];
