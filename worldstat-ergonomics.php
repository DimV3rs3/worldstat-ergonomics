<?php
/**
 * Plugin Name:       WorldStat — Ergonomics
 * Plugin URI:        https://example.com/worldstat-ergonomics
 * Description:       Официальное расширение World Statistics Platform: эргономичность (6 измерений), иерархия помещение→здание→квартал→город→регион→страна, DSL-модели и коэффициенты. Требует платформу и WorldStat Cities.
 * Version:           1.4.7
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

define( 'WSERGO_VERSION', '1.4.7' );
define( 'WSERGO_FILE', __FILE__ );
define( 'WSERGO_DIR', plugin_dir_path( __FILE__ ) );
define( 'WSERGO_URL', plugin_dir_url( __FILE__ ) );

if ( ! class_exists( 'WorldStat_Core' ) ) {
	add_action(
		'admin_notices',
		function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-error"><p><strong>WorldStat Ergonomics</strong> ' . esc_html__( 'требует плагин', 'worldstat-ergonomics' ) . ' <strong>World Statistics Platform</strong>. ' . esc_html__( 'Пока зависимость не активна, расширение не регистрируется в World Statistics → Расширения.', 'worldstat-ergonomics' ) . '</p></div>';
		}
	);
	return;
}

if ( ! class_exists( 'WSCities_CPT' ) ) {
	add_action(
		'admin_notices',
		function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-error"><p><strong>WorldStat Ergonomics</strong> ' . esc_html__( 'требует плагин', 'worldstat-ergonomics' ) . ' <strong>WorldStat Cities</strong>. ' . esc_html__( 'Без него расширение не появится в World Statistics.', 'worldstat-ergonomics' ) . '</p></div>';
		}
	);
	return;
}

require_once WSERGO_DIR . 'includes/class-ergo-expression.php';
require_once WSERGO_DIR . 'includes/class-ergo-model.php';
require_once WSERGO_DIR . 'includes/class-ergo-settings.php';
require_once WSERGO_DIR . 'includes/class-ergo-indicators.php';
require_once WSERGO_DIR . 'includes/class-ergo-cpt.php';
require_once WSERGO_DIR . 'includes/class-ergo-calculator.php';
require_once WSERGO_DIR . 'includes/class-ergo-city-bridge.php';
require_once WSERGO_DIR . 'includes/class-ergo-city-defaults.php';
require_once WSERGO_DIR . 'includes/class-ergo-country-macro-calculator.php';
require_once WSERGO_DIR . 'includes/class-ergo-data.php';
require_once WSERGO_DIR . 'includes/class-ergo-renderer.php';
require_once WSERGO_DIR . 'includes/class-ergo-admin.php';

/**
 * Загрузка переводов на init (до любых __() из register_post_type и т.д.).
 * Колбэк worldstat_init выполняется на plugins_loaded — там нельзя вызывать __().
 */
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

add_action(
	'admin_init',
	static function () {
		if ( class_exists( 'WSErgo_Model' ) ) {
			WSErgo_Model::maybe_migrate_legacy_meta();
			WSErgo_Model::maybe_migrate_index_lock_v12();
		}
	},
	5
);

add_action(
	'update_option_wsp_csv_files_revision',
	static function () {
		if ( class_exists( 'WSErgo_Settings' ) ) {
			WSErgo_Settings::sync_macro_csv_bindings_with_storage();
		}
	},
	10
);

add_action(
	'worldstat_init',
	function () {
		WorldStat_Extensions::register(
			[
				'id'                => 'ergonomics',
				'name'              => 'Ergonomics & Built Environment',
				'version'           => WSERGO_VERSION,
				'author'            => 'Ergonosphera',
				// Строки без __(): worldstat_init срабатывает до init (см. WP 6.7+ и load_plugin_textdomain).
				'description'       => 'Эргономичность среды: 6 измерений, помещения и придомовые территории, агрегаты по городу, региону и стране, DSL-модели.',
				'icon'              => 'dashicons-admin-home',
				'requires_platform' => '1.0.0',
				'depends'           => [ 'cities' ],
			]
		);

		WorldStat_Extensions::add_data_provider(
			'ergonomics',
			[
				'metrics' => [
					'ergo_index' => [
						'label'       => 'Индекс эргономичности (страна)',
						'type'        => 'number',
						'unit'        => 'балл',
						'description' => 'По умолчанию — макроиндекс по CSV платформы (страновые ряды, k-means); альтернатива в настройках — агрегация по городам (население T3, кварталы или импорт Cities).',
						'callback'    => [ 'WSErgo_Data', 'get_country_ergo_index' ],
					],
					'districts_count' => [
						'label'       => 'Кварталов с оценкой',
						'type'        => 'integer',
						'unit'        => '',
						'description' => 'Число кварталов с данными в стране.',
						'callback'    => [ 'WSErgo_Data', 'get_country_districts_count' ],
					],
					'buildings_count' => [
						'label'       => 'Зданий с оценкой',
						'type'        => 'integer',
						'unit'        => '',
						'description' => 'Число зданий с данными в стране.',
						'callback'    => [ 'WSErgo_Data', 'get_country_buildings_count' ],
					],
				],
			]
		);

		WorldStat_Extensions::add_country_tab(
			'ergonomics',
			[
				'title'    => 'Эргономичность',
				'icon'     => 'dashicons-admin-home',
				'callback' => [ 'WSErgo_Renderer', 'render_country_tab' ],
				'priority' => 35,
			]
		);

		WorldStat_Extensions::add_map_layer(
			'ergonomics',
			[
				'label'         => 'Индекс эргономичности',
				'type'          => 'choropleth',
				'color_scale'   => [ '#f0fdf4', '#14532d' ],
				'data_callback' => [ 'WSErgo_Data', 'get_map_data' ],
			]
		);

		WorldStat_Extensions::add_map_markers(
			'ergonomics',
			[
				'label'            => 'Здания (оценка)',
				'icon'             => 'circle',
				'color'            => '#15803d',
				'radius'           => 5,
				'data_callback'    => [ 'WSErgo_Data', 'get_all_building_markers' ],
				'country_callback' => [ 'WSErgo_Data', 'get_country_building_markers' ],
			]
		);
	},
	25
);

new WSErgo_CPT();
new WSErgo_Admin();

add_action(
	'update_option_wsp_csv_files_revision',
	static function () {
		if ( class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			WSErgo_Country_Macro_Calculator::flush_cache();
		}
	}
);
add_action(
	'update_option_' . WSErgo_Settings::OPTION_MACRO_REFERENCE_YEAR,
	static function () {
		if ( class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			WSErgo_Country_Macro_Calculator::flush_cache();
		}
	}
);
add_action(
	'update_option_' . WSErgo_Settings::OPTION_MACRO_K_CLUSTERS,
	static function () {
		if ( class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			WSErgo_Country_Macro_Calculator::flush_cache();
		}
	}
);
add_action(
	'update_option_' . WSErgo_Settings::OPTION_COUNTRY_INDEX_SOURCE,
	static function () {
		if ( class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			WSErgo_Country_Macro_Calculator::flush_cache();
		}
	}
);
add_action(
	'update_option_' . WSErgo_Settings::OPTION_MACRO_CSV_BINDINGS,
	static function () {
		if ( class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			WSErgo_Country_Macro_Calculator::flush_cache();
		}
	}
);
add_action(
	'update_option_' . WSErgo_Settings::OPTION_MACRO_E_AXIS_WEIGHTS,
	static function () {
		if ( class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			WSErgo_Country_Macro_Calculator::flush_cache();
		}
	}
);
add_action(
	'update_option_' . WSErgo_Settings::OPTION_MACRO_CLUSTER_FEATURES,
	static function () {
		if ( class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			WSErgo_Country_Macro_Calculator::flush_cache();
		}
	}
);
add_action(
	'update_option_' . WSErgo_Settings::OPTION_MACRO_AXIS_TERMS,
	static function () {
		if ( class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			WSErgo_Country_Macro_Calculator::flush_cache();
		}
	}
);
add_action(
	'update_option_' . WSErgo_Settings::OPTION_MACRO_CRITERIA_MATRIX,
	static function () {
		if ( class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			WSErgo_Country_Macro_Calculator::flush_cache();
		}
	}
);
add_action(
	'update_option_' . WSErgo_Settings::OPTION_MACRO_CRITERIA_WEIGHTS,
	static function () {
		if ( class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			WSErgo_Country_Macro_Calculator::flush_cache();
		}
	}
);
add_action(
	'update_option_' . WSErgo_Settings::OPTION_MACRO_CRITERIA_INVERTS,
	static function () {
		if ( class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			WSErgo_Country_Macro_Calculator::flush_cache();
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		if ( class_exists( 'WSErgo_City_Defaults' ) ) {
			WSErgo_City_Defaults::maybe_seed_bundled_city_package();
		}
	},
	35
);

add_action( 'wp_enqueue_scripts', [ 'WSErgo_Renderer', 'enqueue_public_assets' ], 20 );

add_action(
	'init',
	static function () {
		add_shortcode( 'wsergo_city_e', [ 'WSErgo_Renderer', 'shortcode_city_e' ] );
	},
	20
);

add_action( 'wsp_city_after_stats', [ 'WSErgo_Renderer', 'render_city_compact_card' ], 10, 2 );
add_action( 'worldstat_after_city', [ 'WSErgo_Renderer', 'render_city_section' ], 10, 2 );

add_filter(
	'worldstat_single_template',
	function ( string $template, string $post_type ): string {
		if ( WSErgo_CPT::SLUG_DISTRICT === $post_type ) {
			$path = WSERGO_DIR . 'templates/single-wsp_district.php';
			return file_exists( $path ) ? $path : $template;
		}
		if ( WSErgo_CPT::SLUG_BUILDING === $post_type ) {
			$path = WSERGO_DIR . 'templates/single-wsp_building.php';
			return file_exists( $path ) ? $path : $template;
		}
		return $template;
	},
	10,
	2
);

add_filter(
	'worldstat_extension_post_types',
	function ( array $types ): array {
		$types[] = WSErgo_CPT::SLUG_DISTRICT;
		$types[] = WSErgo_CPT::SLUG_BUILDING;
		$types[] = WSErgo_CPT::SLUG_ROOM;
		$types[] = WSErgo_CPT::SLUG_YARD;
		return $types;
	}
);

add_action(
	'init',
	static function () {
		if ( ! class_exists( 'WSCities_CPT' ) || ! class_exists( 'WSErgo_City_Bridge' ) ) {
			return;
		}
		register_post_meta(
			WSCities_CPT::SLUG,
			WSErgo_City_Bridge::META_LEAF_INDEX,
			[
				'type'         => 'number',
				'single'       => true,
				'show_in_rest' => false,
			]
		);
	},
	15
);

add_action(
	'save_post_' . WSCities_CPT::SLUG,
	static function ( int $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( class_exists( 'WSErgo_City_Bridge' ) ) {
			WSErgo_City_Bridge::compute_and_store_city_leaf_index( $post_id );
		}
	},
	30,
	1
);

register_activation_hook(
	WSERGO_FILE,
	function () {
		if ( class_exists( 'WSErgo_CPT' ) ) {
			WSErgo_CPT::activate_flush();
		}
		if ( class_exists( 'WSErgo_City_Defaults' ) ) {
			WSErgo_City_Defaults::on_plugin_activation();
		}
	}
);
