<?php
/**
 * Манифест уровня «регион» (будущий).
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'id'       => 'region',
	'label'    => __( 'Регион', 'worldstat-ergonomics' ),
	'prefix'   => 'region',
	'requires' => [],
];
