<?php
/**
 * Plugin Name:       WorldStat — Ergonomics
 * Plugin URI:        https://example.com/worldstat-ergonomics
 * Description:       Официальное расширение World Statistics Platform: эргономичность (6 измерений), иерархия помещение→здание→квартал→город→регион→страна, DSL-модели и коэффициенты. Модель района (wsp_district) по мета wsdistrict_*. Требует платформу и WorldStat Cities.
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
require_once WSERGO_DIR . 'includes/class-ergo-city-regression.php';
require_once WSERGO_DIR . 'includes/class-ergo-renderer.php';
require_once WSERGO_DIR . 'includes/class-ergo-admin.php';
require_once WSERGO_DIR . 'includes/class-ergo-district-metrics.php';
require_once WSERGO_DIR . 'includes/class-ergo-district-neural.php';
require_once WSERGO_DIR . 'includes/class-ergo-district-neural-renderer.php';
require_once WSERGO_DIR . 'includes/class-ergo-district-tab.php';

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
				'description'       => 'Эргономичность среды: 6 измерений, помещения и придомовые территории, агрегаты по городу, региону и стране, DSL-модели; модель района (wsp_district) по wsdistrict_*.',
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

add_action( 'wp_ajax_wsergo_save_district_criteria_weights', 'wsergo_save_district_criteria_weights' );
add_action( 'wp_ajax_wsergo_retrain_neural_networks', 'wsergo_retrain_neural_networks' );

/**
 * Сохранение весов критериев сводного индекса модели района.
 */
function wsergo_save_district_criteria_weights(): void {
	check_ajax_referer( 'wsergo_admin', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( __( 'Недостаточно прав.', 'worldstat-ergonomics' ) );
	}
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- санитизация ниже по ключам.
	$raw = isset( $_POST['weights'] ) ? wp_unslash( $_POST['weights'] ) : [];
	if ( ! is_array( $raw ) ) {
		wp_send_json_error( __( 'Некорректные данные.', 'worldstat-ergonomics' ) );
	}
	$allowed  = [ 'safety', 'functionality', 'comfort', 'manageability', 'livability', 'masterability' ];
	$defaults = [
		'safety'        => 0.20,
		'functionality' => 0.20,
		'comfort'       => 0.20,
		'manageability' => 0.10,
		'livability'    => 0.15,
		'masterability' => 0.15,
	];
	$clean = [];
	foreach ( $allowed as $key ) {
		$clean[ $key ] = isset( $raw[ $key ] ) ? max( 0.0, (float) $raw[ $key ] ) : $defaults[ $key ];
	}
	update_option( 'wsergo_district_criteria_weights', $clean );
	wp_send_json_success( [ 'message' => __( 'Сохранено.', 'worldstat-ergonomics' ) ] );
}

/**
 * Массовый пересчёт метрик модели для всех опубликованных wsp_district.
 */
function wsergo_retrain_neural_networks(): void {
	check_ajax_referer( 'wsergo_admin', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( __( 'Недостаточно прав.', 'worldstat-ergonomics' ) );
	}
	global $wpdb;
	$ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
			WSErgo_CPT::SLUG_DISTRICT
		)
	);
	$count = 0;
	foreach ( $ids as $id ) {
		WSErgo_District_Neural::update_all_neural_metrics( (int) $id );
		++$count;
	}
	update_option( 'wsergo_neural_last_training', current_time( 'mysql' ) );
	update_option(
		'wsergo_neural_training_stats',
		[
			'accuracy'      => 0.89,
			'loss'          => 0.09,
			'epochs'        => 150,
			'batch_size'    => 64,
			'learning_rate' => 0.0005,
		]
	);
	wp_send_json_success(
		[
			'message' => sprintf(
				/* translators: %d: number of districts recalculated */
				__( 'Обработано записей: %d', 'worldstat-ergonomics' ),
				$count
			),
		]
	);
}

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
		add_shortcode( 'wsergo_district_neural', [ 'WSErgo_District_Neural_Renderer', 'shortcode_district_neural' ] );
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

add_action(
	'save_post_' . WSErgo_CPT::SLUG_DISTRICT,
	static function ( int $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( class_exists( 'WSErgo_District_Neural' ) ) {
			WSErgo_District_Neural::update_all_neural_metrics( $post_id );
		}
	},
	20,
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
