<?php
/**
 * Загрузка ядра и уровней.
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$core_files = [
	'class-ergo-expression.php',
	'class-ergo-model.php',
	'class-ergo-tier-classifier.php',
	'class-ergo-settings-store.php',
	'class-ergo-indicators.php',
	'class-ergo-cpt.php',
	'class-ergo-calculator.php',
	'class-ergo-level-registry.php',
	'class-ergo-admin-shell.php',
];

foreach ( $core_files as $file ) {
	$path = WSERGO_DIR . 'core/' . $file;
	if ( is_readable( $path ) ) {
		require_once $path;
	}
}

add_action(
	'init',
	static function () {
		load_plugin_textdomain(
			'worldstat-ergonomics',
			false,
			dirname( plugin_basename( WSERGO_FILE ) ) . '/languages'
		);
	},
	0
);

require_once WSERGO_DIR . 'bootstrap/levels-registry.php';

if ( class_exists( 'WSErgo_Level_Registry' ) ) {
	WSErgo_Level_Registry::boot( wsergo_registered_level_ids() );
}

$core_after_levels = [
	'class-ergo-data.php',
	'class-ergo-renderer.php',
	'class-ergo-admin.php',
];

foreach ( $core_after_levels as $file ) {
	$path = WSERGO_DIR . 'core/' . $file;
	if ( is_readable( $path ) ) {
		require_once $path;
	}
}

require_once WSERGO_DIR . 'bootstrap/hooks.php';
