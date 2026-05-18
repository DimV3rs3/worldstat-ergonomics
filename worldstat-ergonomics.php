<?php
/**
 * Plugin Name:       WorldStat — Ergonomics
 * Plugin URI:        https://example.com/worldstat-ergonomics
 * Description:       Официальное расширение World Statistics Platform: эргономичность (6 измерений), иерархия помещение→здание→квартал→город→регион→страна, DSL-модели и коэффициенты. Требует платформу и WorldStat Cities.
 * Version:           1.5.1
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Requires Plugins:  world-statistics-platform, worldstat-cities
 * Author:            Ergonosphera
 * License:           GPL v2 or later
 * Text Domain:       worldstat-ergonomics
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'WSERGO_FILE' ) && realpath( (string) WSERGO_FILE ) !== realpath( __FILE__ ) ) {
	return;
}

// Второй экземпляр плагина (например worldstat-ergonomics-cities) не должен грузить классы повторно.
if ( class_exists( 'WSErgo_Admin', false ) ) {
	return;
}

if ( ! defined( 'WSERGO_VERSION' ) ) {
	define( 'WSERGO_VERSION', '1.5.1' );
}
if ( ! defined( 'WSERGO_FILE' ) ) {
	define( 'WSERGO_FILE', __FILE__ );
}
if ( ! defined( 'WSERGO_DIR' ) ) {
	define( 'WSERGO_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'WSERGO_URL' ) ) {
	define( 'WSERGO_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! class_exists( 'WorldStat_Core' ) ) {
	add_action(
		'admin_notices',
		static function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-error"><p><strong>WorldStat Ergonomics</strong> ' . esc_html__( 'требует плагин', 'worldstat-ergonomics' ) . ' <strong>World Statistics Platform</strong>.</p></div>';
		}
	);
	return;
}

if ( ! class_exists( 'WSCities_CPT' ) ) {
	add_action(
		'admin_notices',
		static function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-error"><p><strong>WorldStat Ergonomics</strong> ' . esc_html__( 'требует плагин', 'worldstat-ergonomics' ) . ' <strong>WorldStat Cities</strong>.</p></div>';
		}
	);
	return;
}

$wsergo_bootstrap = WSERGO_DIR . 'bootstrap/load.php';
if ( ! is_readable( $wsergo_bootstrap ) ) {
	add_action(
		'admin_notices',
		static function () use ( $wsergo_bootstrap ) {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-error"><p><strong>WorldStat Ergonomics</strong> ';
			echo esc_html__(
				'неполная установка: отсутствует bootstrap/load.php. Скопируйте каталоги bootstrap/, core/ и levels/ из репозитория плагина.',
				'worldstat-ergonomics'
			);
			echo ' <code>' . esc_html( str_replace( ABSPATH, '', $wsergo_bootstrap ) ) . '</code></p></div>';
		}
	);
	return;
}
require_once $wsergo_bootstrap;
