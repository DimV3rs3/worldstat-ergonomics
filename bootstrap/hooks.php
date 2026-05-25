<?php
/**
 * Регистрация в платформе, шаблоны CPT, активация.
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
	static function () {
		WorldStat_Extensions::register(
			[
				'id'                => 'ergonomics',
				'name'              => 'Ergonomics & Built Environment',
				'version'           => WSERGO_VERSION,
				'author'            => 'Ergonosphera',
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

		WorldStat_Extensions::add_country_tab(
			'ergonomics',
			[
				'id'       => 'compare',
				'title'    => 'Сравнение',
				'icon'     => 'dashicons-chart-line',
				'callback' => [ 'WSErgo_Renderer', 'render_country_compare_tab' ],
				'priority' => 36,
			]
		);

		add_filter(
			'worldstat_country_tabs',
			static function ( array $tabs, string $iso2 ): array {
				unset( $iso2 );
				$has_compare = false;
				foreach ( $tabs as $t ) {
					if ( is_array( $t ) && ( $t['id'] ?? '' ) === 'compare' ) {
						$has_compare = true;
						break;
					}
				}
				if ( ! $has_compare ) {
					$tabs[] = [
						'id'       => 'compare',
						'title'    => __( 'Сравнение', 'worldstat-ergonomics' ),
						'icon'     => 'dashicons-chart-line',
						'priority' => 36,
						'is_core'  => false,
					];
				}
				return $tabs;
			},
			10,
			2
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

add_filter(
	'worldstat_single_template',
	static function ( string $template, string $post_type ): string {
		// Шаблон с ML-анализом — в worldstat-districts; эргономика подключается через хуки.
		if ( WSErgo_CPT::SLUG_DISTRICT === $post_type && ! class_exists( 'WSDistricts_CPT' ) ) {
			$path = WSERGO_DIR . 'levels/territory/templates/single-wsp_district.php';
			return file_exists( $path ) ? $path : $template;
		}
		if ( WSErgo_CPT::SLUG_BUILDING === $post_type ) {
			$path = WSERGO_DIR . 'core/templates/single-wsp_building.php';
			return file_exists( $path ) ? $path : $template;
		}
		return $template;
	},
	10,
	2
);

add_filter(
	'worldstat_extension_post_types',
	static function ( array $types ): array {
		$types[] = WSErgo_CPT::SLUG_DISTRICT;
		$types[] = WSErgo_CPT::SLUG_BUILDING;
		$types[] = WSErgo_CPT::SLUG_ROOM;
		$types[] = WSErgo_CPT::SLUG_YARD;
		return $types;
	}
);

register_activation_hook(
	WSERGO_FILE,
	static function () {
		if ( class_exists( 'WSErgo_CPT' ) ) {
			WSErgo_CPT::activate_flush();
		}
		if ( class_exists( 'WSErgo_City_Defaults' ) ) {
			WSErgo_City_Defaults::on_plugin_activation();
		}
		if ( class_exists( 'WSErgo_District_Bridge' ) ) {
			WSErgo_District_Bridge::ensure_default_options();
		}
	}
);
