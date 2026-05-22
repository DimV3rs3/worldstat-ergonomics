<?php
/**
 * Метабоксы, настройки, импорт CSV, подменю World Statistics.
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_Admin {

	public function __construct() {
		add_action( 'admin_init', [ $this, 'redirect_legacy_countries_menu' ], 1 );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_post_wsergo_delete_custom_metric', [ $this, 'handle_delete_custom_metric' ] );
		add_action( 'admin_post_wsergo_delete_city_custom_metric', [ $this, 'handle_delete_city_custom_metric' ] );
		add_action( 'admin_post_wsergo_bulk_delete_city_custom_metrics', [ $this, 'handle_bulk_delete_city_custom_metrics' ] );
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
		add_action( 'admin_menu', [ $this, 'add_import_page' ], 9 );
		add_action( 'admin_menu', [ $this, 'add_worldstat_submenu' ], 99 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin' ] );
		add_action( 'wp_ajax_wsergo_test_formula', [ $this, 'ajax_test_formula' ] );
		add_action( 'wp_ajax_wsergo_test_city_formula', [ $this, 'ajax_test_city_formula' ] );
		add_action( 'wp_ajax_wsergo_append_custom_metric', [ $this, 'ajax_append_custom_metric' ] );
		add_action( 'wp_ajax_wsergo_append_city_custom_metric', [ $this, 'ajax_append_city_custom_metric' ] );

		add_action( 'save_post_' . WSErgo_CPT::SLUG_DISTRICT, [ $this, 'save_district_meta' ], 5, 2 );
		add_action( 'save_post_' . WSErgo_CPT::SLUG_BUILDING, [ $this, 'save_building_meta' ], 5, 2 );
		add_action( 'save_post_' . WSErgo_CPT::SLUG_ROOM, [ $this, 'save_room_meta' ], 5, 2 );
		add_action( 'save_post_' . WSErgo_CPT::SLUG_YARD, [ $this, 'save_yard_meta' ], 5, 2 );
	}

	public function add_worldstat_submenu(): void {
		add_submenu_page(
			'worldstat',
			__( 'Эргономичность', 'worldstat-ergonomics' ),
			__( 'Эргономичность', 'worldstat-ergonomics' ),
			'manage_options',
			'wsergo-settings',
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Р В Р Р‹Р РЋРІР‚С™Р В Р’В°Р РЋР вЂљР РЋРІР‚в„–Р В РІвЂћвЂ“ Р В РЎвЂ”Р РЋРЎвЂњР В Р вЂ¦Р В РЎвЂќР РЋРІР‚С™ Р В РЎВР В Р’ВµР В Р вЂ¦Р РЋР вЂ№ Р вЂ™Р’В«Р В Р’В­Р РЋР вЂљР В РЎвЂ“Р В РЎвЂўР В Р вЂ¦Р В РЎвЂўР В РЎВР В РЎвЂР В РЎвЂќР В Р’В° Р РЋР С“Р РЋРІР‚С™Р РЋР вЂљР В Р’В°Р В Р вЂ¦Р вЂ™Р’В» Р РЋРЎвЂњР В Р’В±Р РЋР вЂљР В Р’В°Р В Р вЂ¦ Р Р†Р вЂљРІР‚Сњ Р В РЎвЂ”Р В Р’ВµР РЋР вЂљР В Р’ВµР В Р вЂ¦Р В Р’В°Р В РЎвЂ”Р РЋР вЂљР В Р’В°Р В Р вЂ Р В Р’В»Р РЋР РЏР В Р’ВµР В РЎВ Р В Р вЂ¦Р В Р’В° Р В Р вЂ Р В РЎвЂќР В Р’В»Р В Р’В°Р В РўвЂР В РЎвЂќР РЋРЎвЂњ Р В Р вЂ¦Р В Р’В°Р РЋР С“Р РЋРІР‚С™Р РЋР вЂљР В РЎвЂўР В Р’ВµР В РЎвЂќ Р РЋР С“Р РЋРІР‚С™Р РЋР вЂљР В Р’В°Р В Р вЂ¦Р РЋРІР‚в„–.
	 */
	public function redirect_legacy_countries_menu(): void {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Р РЋРІР‚С™Р В РЎвЂўР В Р’В»Р РЋР Р‰Р В РЎвЂќР В РЎвЂў Р РЋР вЂљР В Р’ВµР В РўвЂР В РЎвЂР РЋР вЂљР В Р’ВµР В РЎвЂќР РЋРІР‚С™ Р В РЎвЂ”Р В РЎвЂў slug Р РЋР С“Р РЋРІР‚С™Р РЋР вЂљР В Р’В°Р В Р вЂ¦Р В РЎвЂР РЋРІР‚В Р РЋРІР‚в„–.
		if ( isset( $_GET['page'] ) && (string) wp_unslash( $_GET['page'] ) === 'wsergo-countries' ) {
			wp_safe_redirect( admin_url( 'admin.php?page=wsergo-settings#tab-data' ) );
			exit;
		}
	}

	public function register_settings(): void {
		if ( ! class_exists( 'WSErgo_Settings' ) ) {
			return;
		}
		if ( isset( $_GET['page'] ) && (string) $_GET['page'] === 'wsergo-settings' && current_user_can( 'manage_options' ) ) {
			WSErgo_Settings::sync_macro_csv_bindings_with_storage();
		}
		if ( ! get_option( 'wsergo_methodology_version', null ) ) {
			add_option( 'wsergo_methodology_version', '1.2' );
		}
		register_setting(
			'wsergo_settings',
			'wsergo_methodology_version',
			[
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '1.2',
			]
		);
		register_setting(
			'wsergo_settings',
			'wsergo_dimension_weights',
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_dimension_weights' ],
				'default'           => WSErgo_Model::get_default_weights(),
			]
		);
		register_setting(
			'wsergo_settings',
			WSErgo_Settings::OPTION_MODELS,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_models' ],
				'default'           => WSErgo_Settings::get_default_models(),
			]
		);
		register_setting(
			'wsergo_settings',
			WSErgo_Settings::OPTION_ACTIVE_MODEL,
			[
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'default_weighted',
			]
		);
		register_setting(
			'wsergo_settings',
			WSErgo_Settings::OPTION_COEFFICIENTS,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_coefficients' ],
				'default'           => [ 'k_default' => 1.0 ],
			]
		);
		register_setting(
			'wsergo_settings',
			WSErgo_Settings::OPTION_AGGREGATION,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_aggregation' ],
				'default'           => WSErgo_Settings::get_aggregation(),
			]
		);
		register_setting(
			'wsergo_settings',
			WSErgo_Indicators::OPTION_DEFINITIONS,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_indicator_definitions' ],
				'default'           => [],
			]
		);
		register_setting(
			'wsergo_settings',
			WSErgo_Settings::OPTION_CITY_FIELD_MAP,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_city_field_map' ],
				'default'           => [],
			]
		);
		register_setting(
			'wsergo_settings',
			WSErgo_Settings::OPTION_CITY_CSV_BINDINGS,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_city_csv_bindings' ],
				'default'           => [],
			]
		);
		register_setting(
			'wsergo_settings',
			WSErgo_Settings::OPTION_COUNTRY_VARIATION,
			[
				'type'              => 'string',
				'sanitize_callback' => [ $this, 'sanitize_country_variation' ],
				'default'           => 'city_direct',
			]
		);
		register_setting(
			'wsergo_settings',
			WSErgo_Settings::OPTION_COUNTRY_INDEX_SOURCE,
			[
				'type'              => 'string',
				'sanitize_callback' => [ $this, 'sanitize_country_index_source' ],
				'default'           => 'macro_datasets',
			]
		);
		register_setting(
			'wsergo_settings',
			WSErgo_Settings::OPTION_MACRO_REFERENCE_YEAR,
			[
				'type'              => 'integer',
				'sanitize_callback' => [ $this, 'sanitize_macro_reference_year' ],
				'default'           => 2022,
			]
		);
		register_setting(
			'wsergo_settings',
			WSErgo_Settings::OPTION_MACRO_K_CLUSTERS,
			[
				'type'              => 'integer',
				'sanitize_callback' => [ $this, 'sanitize_macro_k_clusters' ],
				'default'           => 6,
			]
		);
		register_setting(
			'wsergo_settings',
			WSErgo_Settings::OPTION_MACRO_CSV_BINDINGS,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_macro_csv_bindings' ],
				'default'           => [],
			]
		);
		register_setting(
			'wsergo_settings',
			WSErgo_Settings::OPTION_MACRO_E_AXIS_WEIGHTS,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_macro_e_axis_weights' ],
				'default'           => [],
			]
		);
		register_setting(
			'wsergo_settings',
			WSErgo_Settings::OPTION_MACRO_EXTRA_SIGNALS_TEXT,
			[
				'type'              => 'string',
				'sanitize_callback' => [ $this, 'sanitize_macro_extra_signals_text' ],
				'default'           => '',
			]
		);
		register_setting(
			'wsergo_settings',
			WSErgo_Settings::OPTION_MACRO_CLUSTER_FEATURES,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_macro_cluster_features' ],
				'default'           => [],
			]
		);
		register_setting(
			'wsergo_settings',
			WSErgo_Settings::OPTION_MACRO_AXIS_TERMS,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_macro_axis_terms' ],
				'default'           => [],
			]
		);
		register_setting(
			'wsergo_settings',
			WSErgo_Settings::OPTION_MACRO_REFERENCE_COUNTRY_POST_ID,
			[
				'type'              => 'integer',
				'sanitize_callback' => [ $this, 'sanitize_macro_reference_country_post_id' ],
				'default'           => 0,
			]
		);
		register_setting(
			'wsergo_settings',
			WSErgo_Settings::OPTION_DATA_LABELS_RU,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_data_labels_ru' ],
				'default'           => [],
			]
		);
		register_setting(
			'wsergo_settings',
			WSErgo_Settings::OPTION_MACRO_CRITERIA_MATRIX,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_macro_criteria_matrix' ],
				'default'           => [],
			]
		);
		register_setting(
			'wsergo_settings',
			WSErgo_Settings::OPTION_MACRO_CRITERIA_WEIGHTS,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_macro_criteria_weights' ],
				'default'           => [],
			]
		);
		register_setting(
			'wsergo_settings',
			WSErgo_Settings::OPTION_MACRO_CRITERIA_INVERTS,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_macro_criteria_inverts' ],
				'default'           => [],
			]
		);
		register_setting(
			'wsergo_settings',
			WSErgo_Settings::OPTION_MACRO_CUSTOM_METRICS,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_macro_custom_metrics' ],
				'default'           => [],
			]
		);
	}

	/**
	 * @param mixed $input
	 */
	public function sanitize_country_variation( $input ): string {
		$mode = is_string( $input ) ? sanitize_key( $input ) : '';
		return in_array( $mode, [ 'city_direct', 'regions' ], true ) ? $mode : 'city_direct';
	}

	/**
	 * @param mixed $input
	 */
	public function sanitize_country_index_source( $input ): string {
		$mode = is_string( $input ) ? sanitize_key( $input ) : '';
		return in_array( $mode, [ 'macro_datasets', 'city_aggregate' ], true ) ? $mode : 'macro_datasets';
	}

	/**
	 * @param mixed $input
	 */
	public function sanitize_macro_reference_year( $input ): int {
		$y = is_numeric( $input ) ? (int) $input : 2022;
		return max( 1900, min( 2100, $y ) );
	}

	/**
	 * @param mixed $input
	 */
	public function sanitize_macro_k_clusters( $input ): int {
		$k = is_numeric( $input ) ? (int) $input : 6;
		return max( 2, min( 12, $k ) );
	}

	/**
	 * @param mixed $input
	 * @return array<string, int>
	 */
	public function sanitize_macro_csv_bindings( $input ): array {
		if ( ! is_array( $input ) ) {
			return [];
		}
		if ( ! class_exists( 'WSErgo_Country_Macro_Calculator' ) || ! class_exists( 'WSErgo_Settings' ) ) {
			return [];
		}
		$allowed = array_flip( WSErgo_Country_Macro_Calculator::bindable_standard_metric_keys() );
		$valid_ids = WSErgo_Settings::valid_macro_csv_source_ids();
		$out       = [];
		foreach ( $input as $k => $v ) {
			$mk = sanitize_key( (string) $k );
			if ( $mk === '' || ! isset( $allowed[ $mk ] ) ) {
				continue;
			}
			$id = (int) $v;
			if ( $id > 0 && isset( $valid_ids[ $id ] ) ) {
				$out[ $mk ] = $id;
			}
		}
		return $out;
	}

	/**
	 * @param mixed $input
	 * @return array<string, int>
	 */
	public function sanitize_city_csv_bindings( $input ): array {
		if ( ! is_array( $input ) ) {
			return [];
		}
		if ( ! class_exists( 'WSErgo_Settings' ) ) {
			return [];
		}
		$allowed = array_flip( WSErgo_Settings::city_bindable_metric_keys() );
		$valid_ids = WSErgo_Settings::valid_macro_csv_source_ids();
		$out = [];
		foreach ( $input as $k => $v ) {
			$mk = sanitize_key( (string) $k );
			if ( $mk === '' || ! isset( $allowed[ $mk ] ) ) {
				continue;
			}
			$id = (int) $v;
			if ( $id > 0 && isset( $valid_ids[ $id ] ) ) {
				$out[ $mk ] = $id;
			}
		}
		return $out;
	}

	/**
	 * @param mixed $input
	 * @return array<string, float>
	 */
	public function sanitize_macro_e_axis_weights( $input ): array {
		$axes    = [ 'F', 'Cm', 'H', 'A', 'S', 'Ct' ];
		$default = [
			'F'  => 0.25,
			'Cm' => 0.22,
			'H'  => 0.09,
			'A'  => 0.10,
			'S'  => 0.20,
			'Ct' => 0.13,
		];
		if ( ! is_array( $input ) ) {
			return $default;
		}
		$out = [];
		foreach ( $axes as $axis ) {
			$w = isset( $input[ $axis ] ) ? (float) $input[ $axis ] : $default[ $axis ];
			$out[ $axis ] = $w > 0 ? $w : $default[ $axis ];
		}
		$sum = array_sum( $out );
		if ( $sum <= 1e-15 ) {
			return $default;
		}
		foreach ( $out as $axis => $w ) {
			$out[ $axis ] = $w / $sum;
		}
		return $out;
	}

	/**
	 * @param mixed $input
	 * @return list<string>
	 */
	/**
	 * @param mixed $input
	 */
	public function sanitize_macro_extra_signals_text( $input ): string {
		if ( ! class_exists( 'WSErgo_Settings' ) ) {
			return '';
		}
		$s = is_string( $input ) ? $input : '';
		$arr = WSErgo_Settings::parse_macro_extra_signals_string( $s );
		return implode( "\n", $arr );
	}

	public function sanitize_macro_cluster_features( $input ): array {
		if ( ! is_array( $input ) || ! class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			return [];
		}
		$allow = array_flip( WSErgo_Country_Macro_Calculator::macro_cluster_signal_allowlist( 'country' ) );
		$out   = array();
		foreach ( $input as $x ) {
			$k = sanitize_key( (string) $x );
			if ( $k !== '' && isset( $allow[ $k ] ) ) {
				$out[] = $k;
			}
		}
		$out = array_values( array_unique( $out ) );
		return count( $out ) >= 2 ? $out : [];
	}

	/**
	 * @param mixed $input
	 * @return array<string, list<array{signal:string, invert:bool, weight:float}>>
	 */
	public function sanitize_macro_axis_terms( $input ): array {
		if ( ! is_array( $input ) || ! class_exists( 'WSErgo_Settings' ) ) {
			return [];
		}
		$axes = array( 'F', 'Cm', 'H', 'A', 'S', 'Ct' );
		$out  = array();
		foreach ( $axes as $ax ) {
			if ( ! isset( $input[ $ax ] ) || ! is_array( $input[ $ax ] ) ) {
				continue;
			}
			$san = WSErgo_Settings::sanitize_macro_axis_terms_rows( $input[ $ax ] );
			if ( count( $san ) > 0 ) {
				$out[ $ax ] = $san;
			}
		}
		return $out;
	}

	/**
	 * @param mixed $input
	 * @return array<int, array{source:string, indicator_id:string}>
	 */
	public function sanitize_city_field_map( $input ): array {
		if ( ! is_array( $input ) ) {
			return [];
		}
		$out = [];
		foreach ( $input as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$src = isset( $row['source'] ) ? sanitize_text_field( (string) $row['source'] ) : '';
			$ind = isset( $row['indicator_id'] ) ? sanitize_key( (string) $row['indicator_id'] ) : '';
			if ( $src === '' || $ind === '' ) {
				continue;
			}
			$out[] = [
				'source'       => $src,
				'indicator_id' => $ind,
			];
		}
		return array_values( $out );
	}

	/**
	 * @param mixed $input
	 * @return array<int, array<string, mixed>>
	 */
	public function sanitize_indicator_definitions( $input ): array {
		if ( ! is_array( $input ) ) {
			return [];
		}
		$out = [];
		foreach ( $input as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id = isset( $row['id'] ) ? sanitize_key( (string) $row['id'] ) : '';
			if ( $id === '' ) {
				continue;
			}
			$dim = isset( $row['dimension'] ) ? sanitize_key( (string) $row['dimension'] ) : '';
			if ( ! in_array( $dim, WSErgo_Model::DIMENSION_KEYS, true ) ) {
				continue;
			}
			$dir = isset( $row['direction'] ) && 'lower_better' === $row['direction'] ? 'lower_better' : 'higher_better';
			$vmin = isset( $row['vmin'] ) ? (float) str_replace( ',', '.', (string) $row['vmin'] ) : 0.0;
			$vmax = isset( $row['vmax'] ) ? (float) str_replace( ',', '.', (string) $row['vmax'] ) : 100.0;
			if ( $vmax < $vmin ) {
				$t = $vmin;
				$vmin = $vmax;
				$vmax = $t;
			}
			$out[] = [
				'id'        => $id,
				'label'     => isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : $id,
				'dimension' => $dim,
				'unit'      => isset( $row['unit'] ) ? sanitize_text_field( (string) $row['unit'] ) : '',
				'vmin'      => $vmin,
				'vmax'      => $vmax,
				'direction' => $dir,
				'weight'    => isset( $row['weight'] ) ? max( 0.0, (float) str_replace( ',', '.', (string) $row['weight'] ) ) : 1.0,
			];
		}
		$sums = [];
		foreach ( $out as $row ) {
			$d = $row['dimension'];
			if ( ! isset( $sums[ $d ] ) ) {
				$sums[ $d ] = 0.0;
			}
			$sums[ $d ] += max( 0.000001, (float) $row['weight'] );
		}
		foreach ( $out as $i => $row ) {
			$d   = $row['dimension'];
			$sum = $sums[ $d ] ?? 0.0;
			if ( $sum > 0 ) {
				$out[ $i ]['weight'] = round( max( 0.000001, (float) $row['weight'] ) / $sum, 6 );
			}
		}
		return array_values( $out );
	}

	/**
	 * @param mixed $input
	 * @return array<int, array{id:string,name:string,leaf_formula:string}>
	 */
	public function sanitize_models( $input ): array {
		$defaults = WSErgo_Settings::get_default_models();
		if ( ! is_array( $input ) ) {
			return $defaults;
		}
		$out = [];
		foreach ( $input as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id = isset( $row['id'] ) ? sanitize_key( (string) $row['id'] ) : '';
			if ( $id === '' ) {
				continue;
			}
			$formula = isset( $row['leaf_formula'] ) ? (string) $row['leaf_formula'] : '';
			$formula = trim( preg_replace( '/[^\x20-\x7E\n\r\t]/', '', $formula ) );
			if ( $formula !== '' ) {
				$allowed = WSErgo_Settings::get_leaf_formula_allowed_ids();
				$check   = WSErgo_Expression::validate( $formula, $allowed );
				if ( ! $check['ok'] ) {
					add_settings_error(
						'wsergo_settings',
						'wsergo_bad_formula',
						sprintf(
							/* translators: %s: error */
							__( 'Ошибка в формуле модели «%1$s»: %2$s', 'worldstat-ergonomics' ),
							$id,
							$check['error'] ?? ''
						)
					);
					return get_option( 'wsergo_models', $defaults );
				}
			}
			$out[] = [
				'id'           => $id,
				'name'         => isset( $row['name'] ) ? sanitize_text_field( (string) $row['name'] ) : $id,
				'leaf_formula' => $formula,
			];
		}
		return ! empty( $out ) ? $out : $defaults;
	}

	/**
	 * @param mixed $input
	 * @return array<string, float>
	 */
	public function sanitize_coefficients( $input ): array {
		if ( ! is_array( $input ) ) {
			return [ 'k_default' => 1.0 ];
		}
		$out = [ 'k_default' => 1.0 ];
		foreach ( $input as $k => $v ) {
			$key = sanitize_key( (string) $k );
			if ( $key === '' ) {
				continue;
			}
			if ( substr( $key, 0, 2 ) !== 'k_' ) {
				$key = 'k_' . $key;
			}
			$out[ $key ] = (float) str_replace( ',', '.', (string) $v );
		}
		return $out;
	}

	/**
	 * @param mixed $input
	 * @return array<string, mixed>
	 */
	public function sanitize_aggregation( $input ): array {
		$base = WSErgo_Settings::get_aggregation();
		if ( ! is_array( $input ) ) {
			return $base;
		}
		$room_modes = [ 'mean', 'weighted_area', 'median' ];
		$district_m = [ 'mean', 'buildings_weighted' ];
		$city_reg   = [ 'pop_weighted', 'mean' ];

		if ( isset( $input['room_to_building'] ) && in_array( $input['room_to_building'], $room_modes, true ) ) {
			$base['room_to_building'] = $input['room_to_building'];
		}
		if ( isset( $input['district_to_city'] ) && in_array( $input['district_to_city'], $district_m, true ) ) {
			$base['district_to_city'] = $input['district_to_city'];
		}
		if ( isset( $input['city_to_region'] ) && in_array( $input['city_to_region'], $city_reg, true ) ) {
			$base['city_to_region'] = $input['city_to_region'];
		}
		if ( isset( $input['region_to_country'] ) && in_array( $input['region_to_country'], $city_reg, true ) ) {
			$base['region_to_country'] = $input['region_to_country'];
		}
		if ( isset( $input['w_building_in_district'] ) ) {
			$base['w_building_in_district'] = max( 0.0, (float) str_replace( ',', '.', (string) $input['w_building_in_district'] ) );
		}
		if ( isset( $input['w_yard_in_district'] ) ) {
			$base['w_yard_in_district'] = max( 0.0, (float) str_replace( ',', '.', (string) $input['w_yard_in_district'] ) );
		}
		$sum = (float) $base['w_building_in_district'] + (float) $base['w_yard_in_district'];
		if ( $sum > 0 ) {
			$base['w_building_in_district'] /= $sum;
			$base['w_yard_in_district']     /= $sum;
		}
		return $base;
	}

	public function sanitize_dimension_weights( $input ): array {
		$defaults = WSErgo_Model::get_default_weights();
		if ( ! is_array( $input ) ) {
			return $defaults;
		}
		$out = [];
		foreach ( WSErgo_Model::DIMENSION_KEYS as $k ) {
			$out[ $k ] = isset( $input[ $k ] ) ? max( 0.0, (float) str_replace( ',', '.', (string) $input[ $k ] ) ) : $defaults[ $k ];
		}
		$sum = array_sum( $out );
		if ( $sum <= 0 ) {
			return $defaults;
		}
		foreach ( $out as $k => $v ) {
			$out[ $k ] = round( $v / $sum, 6 );
		}
		return $out;
	}

	public function ajax_test_formula(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'worldstat-ergonomics' ) );
		}
		check_ajax_referer( 'wsergo_settings_ajax', 'nonce' );
		$formula = isset( $_POST['formula'] ) ? wp_unslash( (string) $_POST['formula'] ) : '';
		$formula = trim( $formula );
		$allowed = WSErgo_Settings::get_leaf_formula_allowed_ids();
		$vcheck  = WSErgo_Expression::validate( $formula, $allowed );
		if ( ! $vcheck['ok'] ) {
			wp_send_json_error( [ 'message' => $vcheck['error'] ?? '?' ] );
		}
		if ( $formula === '' ) {
			wp_send_json_success( [ 'value' => __( '(пусто — будет взвешенное среднее)', 'worldstat-ergonomics' ) ] );
		}
		$vars = [];
		foreach ( array_keys( $allowed ) as $id ) {
			$vars[ $id ] = 50.0;
		}
		try {
			$val = WSErgo_Expression::evaluate( $formula, $vars );
			wp_send_json_success( [ 'value' => round( $val, 4 ) ] );
		} catch ( Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	public function save_district_meta( int $post_id, WP_Post $post ): void {
		if ( ! isset( $_POST['wsergo_district_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wsergo_district_nonce'] ) ), 'wsergo_save_district' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$city_id = isset( $_POST['wsergo_city_id'] ) ? (int) $_POST['wsergo_city_id'] : 0;
		if ( $city_id > 0 ) {
			update_post_meta( $post_id, WSErgo_CPT::META_CITY_ID, $city_id );
		}

		$this->save_score_fields_from_post( $post_id, true );
		$geo = isset( $_POST['wsergo_geojson'] ) ? wp_unslash( $_POST['wsergo_geojson'] ) : '';
		update_post_meta( $post_id, WSErgo_CPT::META_GEOJSON, sanitize_textarea_field( $geo ) );
	}

	public function save_building_meta( int $post_id, WP_Post $post ): void {
		if ( ! isset( $_POST['wsergo_building_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wsergo_building_nonce'] ) ), 'wsergo_save_building' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$did = isset( $_POST['wsergo_district_id'] ) ? (int) $_POST['wsergo_district_id'] : 0;
		if ( $did > 0 ) {
			update_post_meta( $post_id, WSErgo_CPT::META_DISTRICT_ID, $did );
		}

		$lat = isset( $_POST['wsergo_lat'] ) ? (float) str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST['wsergo_lat'] ) ) ) : 0.0;
		$lng = isset( $_POST['wsergo_lng'] ) ? (float) str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST['wsergo_lng'] ) ) ) : 0.0;
		update_post_meta( $post_id, WSErgo_CPT::META_LAT, $lat );
		update_post_meta( $post_id, WSErgo_CPT::META_LNG, $lng );
		update_post_meta( $post_id, WSErgo_CPT::META_ADDRESS, isset( $_POST['wsergo_address'] ) ? sanitize_text_field( wp_unslash( $_POST['wsergo_address'] ) ) : '' );
		$year = isset( $_POST['wsergo_year'] ) ? (int) $_POST['wsergo_year'] : 0;
		update_post_meta( $post_id, WSErgo_CPT::META_YEAR, $year > 0 ? $year : '' );

		$this->save_score_fields_from_post( $post_id, true );
	}

	public function save_room_meta( int $post_id, WP_Post $post ): void {
		if ( ! isset( $_POST['wsergo_room_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wsergo_room_nonce'] ) ), 'wsergo_save_room' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$bid = isset( $_POST['wsergo_building_id'] ) ? (int) $_POST['wsergo_building_id'] : 0;
		if ( $bid > 0 ) {
			update_post_meta( $post_id, WSErgo_CPT::META_BUILDING_ID, $bid );
		}
		$area = isset( $_POST['wsergo_area'] ) ? (float) str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST['wsergo_area'] ) ) ) : 0.0;
		update_post_meta( $post_id, WSErgo_CPT::META_AREA, $area > 0 ? $area : '' );
		$this->save_score_fields_from_post( $post_id, true );
	}

	public function save_yard_meta( int $post_id, WP_Post $post ): void {
		if ( ! isset( $_POST['wsergo_yard_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wsergo_yard_nonce'] ) ), 'wsergo_save_yard' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$did = isset( $_POST['wsergo_yard_district_id'] ) ? (int) $_POST['wsergo_yard_district_id'] : 0;
		if ( $did > 0 ) {
			update_post_meta( $post_id, WSErgo_CPT::META_DISTRICT_ID, $did );
		}
		$lat = isset( $_POST['wsergo_yard_lat'] ) ? (float) str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST['wsergo_yard_lat'] ) ) ) : 0.0;
		$lng = isset( $_POST['wsergo_yard_lng'] ) ? (float) str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST['wsergo_yard_lng'] ) ) ) : 0.0;
		update_post_meta( $post_id, WSErgo_CPT::META_LAT, $lat );
		update_post_meta( $post_id, WSErgo_CPT::META_LNG, $lng );
		$geo = isset( $_POST['wsergo_yard_geojson'] ) ? wp_unslash( $_POST['wsergo_yard_geojson'] ) : '';
		update_post_meta( $post_id, WSErgo_CPT::META_GEOJSON, sanitize_textarea_field( $geo ) );
		$this->save_score_fields_from_post( $post_id, true );
	}

	private function save_score_fields_from_post( int $post_id, bool $allow_lock ): void {
		if ( isset( $_POST[ WSErgo_CPT::META_INDEX ] ) ) {
			$raw = sanitize_text_field( wp_unslash( $_POST[ WSErgo_CPT::META_INDEX ] ) );
			if ( $raw === '' ) {
				delete_post_meta( $post_id, WSErgo_CPT::META_INDEX );
			} else {
				update_post_meta( $post_id, WSErgo_CPT::META_INDEX, (float) str_replace( ',', '.', $raw ) );
			}
		}

		if ( $allow_lock && isset( $_POST['wsergo_index_locked'] ) && '1' === $_POST['wsergo_index_locked'] ) {
			update_post_meta( $post_id, WSErgo_CPT::META_INDEX_LOCKED, '1' );
		} elseif ( $allow_lock ) {
			delete_post_meta( $post_id, WSErgo_CPT::META_INDEX_LOCKED );
		}

		foreach ( WSErgo_Model::DIMENSION_KEYS as $dim ) {
			$key = WSErgo_Model::meta_key_for_dimension( $dim );
			if ( ! isset( $_POST[ $key ] ) ) {
				continue;
			}
			$raw = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
			if ( $raw === '' ) {
				delete_post_meta( $post_id, $key );
				continue;
			}
			update_post_meta( $post_id, $key, (float) str_replace( ',', '.', $raw ) );
		}

		$allowed_raw = [];
		foreach ( WSErgo_Indicators::get_definitions() as $def ) {
			$allowed_raw[ $def['id'] ] = true;
		}
		if ( isset( $_POST['wsergo_raw'] ) && is_array( $_POST['wsergo_raw'] ) ) {
			foreach ( $_POST['wsergo_raw'] as $rid => $rval ) {
				$rid = sanitize_key( (string) $rid );
				if ( $rid === '' || ! isset( $allowed_raw[ $rid ] ) ) {
					continue;
				}
				$mkey = WSErgo_Indicators::meta_key_for_raw( $rid );
				$rs   = is_string( $rval ) ? sanitize_text_field( wp_unslash( $rval ) ) : '';
				if ( $rs === '' ) {
					delete_post_meta( $post_id, $mkey );
				} else {
					update_post_meta( $post_id, $mkey, (float) str_replace( ',', '.', $rs ) );
				}
			}
		}

		WSErgo_Indicators::sync_dimension_meta_from_indicators( $post_id );

		if ( isset( $_POST['wsergo_indicators_json'] ) ) {
			$json = wp_unslash( $_POST['wsergo_indicators_json'] );
			if ( $json === '' ) {
				delete_post_meta( $post_id, WSErgo_CPT::META_INDICATORS_JSON );
			} else {
				update_post_meta( $post_id, WSErgo_CPT::META_INDICATORS_JSON, sanitize_textarea_field( $json ) );
			}
		}

		if ( isset( $_POST['wsergo_coeff_overrides_json'] ) ) {
			$j = wp_unslash( $_POST['wsergo_coeff_overrides_json'] );
			if ( $j === '' ) {
				delete_post_meta( $post_id, WSErgo_CPT::META_COEFF_OVERRIDES );
			} else {
				update_post_meta( $post_id, WSErgo_CPT::META_COEFF_OVERRIDES, sanitize_textarea_field( $j ) );
			}
		}

		WSErgo_Model::sync_composite_index( $post_id );
	}

	public function enqueue_admin( string $hook ): void {
		$load = ( false !== strpos( $hook, 'wsergo' ) );
		$is_settings_page = ( false !== strpos( $hook, 'wsergo-settings' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Р РЋРІР‚С™Р В РЎвЂўР В Р’В»Р РЋР Р‰Р В РЎвЂќР В РЎвЂў slug Р РЋР С“Р РЋРІР‚С™Р РЋР вЂљР В Р’В°Р В Р вЂ¦Р В РЎвЂР РЋРІР‚В Р РЋРІР‚в„– Р В РўвЂР В Р’В»Р РЋР РЏ enqueue.
		if ( isset( $_GET['page'] ) && 'wsergo-settings' === (string) wp_unslash( $_GET['page'] ) ) {
			$is_settings_page = true;
			$load             = true;
		}
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && 'worldstat_page_wsergo-settings' === $screen->id ) {
				$is_settings_page = true;
				$load             = true;
			}
			if ( $screen && class_exists( 'WSErgo_CPT' ) && in_array( $screen->post_type, [ WSErgo_CPT::SLUG_DISTRICT, WSErgo_CPT::SLUG_BUILDING, WSErgo_CPT::SLUG_ROOM, WSErgo_CPT::SLUG_YARD ], true ) ) {
				$load = true;
			}
		}
		if ( ! $load ) {
			return;
		}
		if ( $is_settings_page ) {
			$this->enqueue_settings_page_assets();
			// Скрипт вкладок встроен в страницу (render_settings_tabs_script); не подключаем лишнее с платформы.
			wp_dequeue_script( 'worldstat-admin' );
			wp_dequeue_style( 'worldstat-admin' );
			return;
		}
		wp_enqueue_style( 'wsergo-admin', WSERGO_URL . 'core/public/assets/css/admin.css', [], WSERGO_VERSION );
	}

	public function add_meta_boxes(): void {
		add_meta_box(
			'wsergo_district_data',
			__( 'Эргономика квартала', 'worldstat-ergonomics' ),
			[ $this, 'render_district_metabox' ],
			WSErgo_CPT::SLUG_DISTRICT,
			'normal',
			'high'
		);
		add_meta_box(
			'wsergo_building_data',
			__( 'Эргономика здания', 'worldstat-ergonomics' ),
			[ $this, 'render_building_metabox' ],
			WSErgo_CPT::SLUG_BUILDING,
			'normal',
			'high'
		);
		add_meta_box(
			'wsergo_room_data',
			__( 'Эргономика помещения', 'worldstat-ergonomics' ),
			[ $this, 'render_room_metabox' ],
			WSErgo_CPT::SLUG_ROOM,
			'normal',
			'high'
		);
		add_meta_box(
			'wsergo_yard_data',
			__( 'Эргономика придомовой', 'worldstat-ergonomics' ),
			[ $this, 'render_yard_metabox' ],
			WSErgo_CPT::SLUG_YARD,
			'normal',
			'high'
		);
	}

	public function render_district_metabox( WP_Post $post ): void {
		wp_nonce_field( 'wsergo_save_district', 'wsergo_district_nonce' );
		$city_id = (int) get_post_meta( $post->ID, WSErgo_CPT::META_CITY_ID, true );

		$cities = get_posts(
			[
				'post_type'      => WSCities_CPT::SLUG,
				'post_status'    => 'any',
				'posts_per_page' => 3000,
				'orderby'        => 'title',
				'order'          => 'ASC',
			]
		);
		?>
		<p>
			<label for="wsergo_city_id"><strong><?php esc_html_e( 'Город', 'worldstat-ergonomics' ); ?></strong></label><br />
			<select name="wsergo_city_id" id="wsergo_city_id" class="widefat" required>
				<option value=""><?php esc_html_e( '— выберите —', 'worldstat-ergonomics' ); ?></option>
				<?php foreach ( $cities as $c ) : ?>
					<option value="<?php echo esc_attr( (string) $c->ID ); ?>" <?php selected( $city_id, $c->ID ); ?>>
						<?php echo esc_html( $c->post_title . ' (ID ' . $c->ID . ')' ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
		$this->render_score_fields( $post->ID, 'district', true );
		$geo = (string) get_post_meta( $post->ID, WSErgo_CPT::META_GEOJSON, true );
		?>
		<p>
			<label for="wsergo_geojson"><strong><?php esc_html_e( 'GeoJSON полигона (опц.)', 'worldstat-ergonomics' ); ?></strong></label>
			<textarea name="wsergo_geojson" id="wsergo_geojson" class="widefat" rows="4" placeholder="{ &quot;type&quot;: &quot;Polygon&quot;, ... }"><?php echo esc_textarea( $geo ); ?></textarea>
		</p>
		<?php
	}

	public function render_building_metabox( WP_Post $post ): void {
		wp_nonce_field( 'wsergo_save_building', 'wsergo_building_nonce' );
		$district_id = (int) get_post_meta( $post->ID, WSErgo_CPT::META_DISTRICT_ID, true );

		$districts = get_posts(
			[
				'post_type'      => WSErgo_CPT::SLUG_DISTRICT,
				'post_status'    => 'any',
				'posts_per_page' => 5000,
				'orderby'        => 'title',
				'order'          => 'ASC',
			]
		);
		?>
		<p>
			<label for="wsergo_district_id"><strong><?php esc_html_e( 'Квартал', 'worldstat-ergonomics' ); ?></strong></label><br />
			<select name="wsergo_district_id" id="wsergo_district_id" class="widefat" required>
				<option value=""><?php esc_html_e( '— выберите —', 'worldstat-ergonomics' ); ?></option>
				<?php foreach ( $districts as $d ) : ?>
					<option value="<?php echo esc_attr( (string) $d->ID ); ?>" <?php selected( $district_id, $d->ID ); ?>>
						<?php echo esc_html( $d->post_title . ' (ID ' . $d->ID . ')' ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p class="wsergo-row">
			<label><?php esc_html_e( 'Широта', 'worldstat-ergonomics' ); ?></label>
			<input type="text" name="wsergo_lat" value="<?php echo esc_attr( (string) get_post_meta( $post->ID, WSErgo_CPT::META_LAT, true ) ); ?>" class="widefat" />
		</p>
		<p class="wsergo-row">
			<label><?php esc_html_e( 'Долгота', 'worldstat-ergonomics' ); ?></label>
			<input type="text" name="wsergo_lng" value="<?php echo esc_attr( (string) get_post_meta( $post->ID, WSErgo_CPT::META_LNG, true ) ); ?>" class="widefat" />
		</p>
		<p class="wsergo-row">
			<label><?php esc_html_e( 'Адрес', 'worldstat-ergonomics' ); ?></label>
			<input type="text" name="wsergo_address" value="<?php echo esc_attr( (string) get_post_meta( $post->ID, WSErgo_CPT::META_ADDRESS, true ) ); ?>" class="widefat" />
		</p>
		<p class="wsergo-row">
			<label><?php esc_html_e( 'Год постройки', 'worldstat-ergonomics' ); ?></label>
			<input type="number" name="wsergo_year" value="<?php echo esc_attr( (string) get_post_meta( $post->ID, WSErgo_CPT::META_YEAR, true ) ); ?>" class="small-text" min="0" />
		</p>
		<?php
		$this->render_score_fields( $post->ID, 'building', true );
	}

	public function render_room_metabox( WP_Post $post ): void {
		wp_nonce_field( 'wsergo_save_room', 'wsergo_room_nonce' );
		$bid = (int) get_post_meta( $post->ID, WSErgo_CPT::META_BUILDING_ID, true );
		$buildings = get_posts(
			[
				'post_type'      => WSErgo_CPT::SLUG_BUILDING,
				'post_status'    => 'any',
				'posts_per_page' => 8000,
				'orderby'        => 'title',
				'order'          => 'ASC',
			]
		);
		?>
		<p>
			<label for="wsergo_building_id"><strong><?php esc_html_e( 'Здание', 'worldstat-ergonomics' ); ?></strong></label><br />
			<select name="wsergo_building_id" id="wsergo_building_id" class="widefat" required>
				<option value=""><?php esc_html_e( '— выберите —', 'worldstat-ergonomics' ); ?></option>
				<?php foreach ( $buildings as $b ) : ?>
					<option value="<?php echo esc_attr( (string) $b->ID ); ?>" <?php selected( $bid, $b->ID ); ?>><?php echo esc_html( $b->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="wsergo_area"><?php esc_html_e( 'Площадь, м² (для веса при агрегации в здание)', 'worldstat-ergonomics' ); ?></label>
			<input type="text" name="wsergo_area" id="wsergo_area" class="widefat" value="<?php echo esc_attr( (string) get_post_meta( $post->ID, WSErgo_CPT::META_AREA, true ) ); ?>" inputmode="decimal" />
		</p>
		<?php
		$this->render_score_fields( $post->ID, 'room', true );
	}

	public function render_yard_metabox( WP_Post $post ): void {
		wp_nonce_field( 'wsergo_save_yard', 'wsergo_yard_nonce' );
		$did = (int) get_post_meta( $post->ID, WSErgo_CPT::META_DISTRICT_ID, true );
		$districts = get_posts(
			[
				'post_type'      => WSErgo_CPT::SLUG_DISTRICT,
				'post_status'    => 'any',
				'posts_per_page' => 5000,
				'orderby'        => 'title',
				'order'          => 'ASC',
			]
		);
		?>
		<p>
			<label for="wsergo_yard_district_id"><strong><?php esc_html_e( 'Квартал', 'worldstat-ergonomics' ); ?></strong></label><br />
			<select name="wsergo_yard_district_id" id="wsergo_yard_district_id" class="widefat" required>
				<option value=""><?php esc_html_e( '— выберите —', 'worldstat-ergonomics' ); ?></option>
				<?php foreach ( $districts as $d ) : ?>
					<option value="<?php echo esc_attr( (string) $d->ID ); ?>" <?php selected( $did, $d->ID ); ?>><?php echo esc_html( $d->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p class="wsergo-row">
			<label><?php esc_html_e( 'Широта', 'worldstat-ergonomics' ); ?></label>
			<input type="text" name="wsergo_yard_lat" value="<?php echo esc_attr( (string) get_post_meta( $post->ID, WSErgo_CPT::META_LAT, true ) ); ?>" class="widefat" />
		</p>
		<p class="wsergo-row">
			<label><?php esc_html_e( 'Широта', 'worldstat-ergonomics' ); ?></label>
			<input type="text" name="wsergo_yard_lng" value="<?php echo esc_attr( (string) get_post_meta( $post->ID, WSErgo_CPT::META_LNG, true ) ); ?>" class="widefat" />
		</p>
		<p>
			<label for="wsergo_yard_geojson"><?php esc_html_e( 'GeoJSON (опц.)', 'worldstat-ergonomics' ); ?></label>
			<textarea name="wsergo_yard_geojson" id="wsergo_yard_geojson" class="widefat" rows="3"><?php echo esc_textarea( (string) get_post_meta( $post->ID, WSErgo_CPT::META_GEOJSON, true ) ); ?></textarea>
		</p>
		<?php
		$this->render_score_fields( $post->ID, 'yard', true );
	}

	/**
	 * Р В РЎСџР В РЎвЂўР В Р’В»Р РЋР РЏ Р РЋР С“Р РЋРІР‚в„–Р РЋР вЂљР РЋРІР‚в„–Р РЋРІР‚В¦ Р В РЎвЂ”Р В РЎвЂўР В РЎвЂќР В Р’В°Р В Р’В·Р В Р’В°Р РЋРІР‚С™Р В Р’ВµР В Р’В»Р В Р’ВµР В РІвЂћвЂ“ (Р В Р вЂ¦Р В Р’В°Р РЋР С“Р РЋРІР‚С™Р РЋР вЂљР В Р’В°Р В РЎвЂР В Р вЂ Р В Р’В°Р РЋР вЂ№Р РЋРІР‚С™Р РЋР С“Р РЋР РЏ Р В Р вЂ  Р вЂ™Р’В«Р В Р’В­Р РЋР вЂљР В РЎвЂ“Р В РЎвЂўР В Р вЂ¦Р В РЎвЂўР В РЎВР В РЎвЂР В РЎвЂќР В Р’В° Р Р†РІР‚В РІР‚в„ў Р В РЎСџР В РЎвЂўР В РЎвЂќР В Р’В°Р В Р’В·Р В Р’В°Р РЋРІР‚С™Р В Р’ВµР В Р’В»Р В РЎвЂР вЂ™Р’В»).
	 */
	private function render_leaf_indicator_inputs( int $post_id ): void {
		$by = WSErgo_Indicators::get_definitions_by_dimension();
		$any = false;
		foreach ( $by as $rows ) {
			if ( ! empty( $rows ) ) {
				$any = true;
				break;
			}
		}
		if ( ! $any ) {
			return;
		}
		$labels = WSErgo_Model::get_dimension_labels();
		?>
		<div class="wsergo-leaf-indicators">
			<p class="description">
				<strong><?php esc_html_e( 'Сырые показатели', 'worldstat-ergonomics' ); ?></strong>
				<?php esc_html_e( ' — ввод в физических единицах; нормализация в 0–100 по min/max и направлению; внутри уровня — взвешенное среднее. В DSL доступны как i_id (0–100).', 'worldstat-ergonomics' ); ?>
			</p>
			<?php
			foreach ( WSErgo_Model::DIMENSION_KEYS as $dim ) {
				$rows = $by[ $dim ] ?? [];
				if ( empty( $rows ) ) {
					continue;
				}
				?>
				<fieldset class="wsergo-dim-group" style="margin:1em 0;padding:8px;border:1px solid #ccd0d4;">
					<legend><strong><?php echo esc_html( $labels[ $dim ] ); ?></strong></legend>
					<?php
					foreach ( $rows as $def ) {
						$mkey = WSErgo_Indicators::meta_key_for_raw( $def['id'] );
						$val  = get_post_meta( $post_id, $mkey, true );
						$uid  = 'wsergo_raw_' . $def['id'];
						?>
						<p class="wsergo-row">
							<label for="<?php echo esc_attr( $uid ); ?>">
								<?php echo esc_html( $def['label'] ); ?>
								<?php if ( $def['unit'] !== '' ) : ?>
									<span class="description">(<?php echo esc_html( $def['unit'] ); ?>)</span>
								<?php endif; ?>
							</label><br />
							<input type="text" name="wsergo_raw[<?php echo esc_attr( $def['id'] ); ?>]" id="<?php echo esc_attr( $uid ); ?>" value="<?php echo esc_attr( (string) $val ); ?>" class="widefat" inputmode="decimal" />
						</p>
						<?php
					}
					?>
				</fieldset>
				<?php
			}
			?>
		</div>
		<?php
	}

	private function render_score_fields( int $post_id, string $ctx, bool $show_lock ): void {
		?>
		<p class="description">
			<?php esc_html_e( 'Сводный индекс E (0–100): пустое поле — расчёт по активной модели (DSL или взвешенное среднее) и иерархии.', 'worldstat-ergonomics' ); ?>
		</p>
		<p>
			<label for="wsergo_index"><strong><?php esc_html_e( 'Сводный индекс E', 'worldstat-ergonomics' ); ?></strong></label>
			<input type="text" name="<?php echo esc_attr( WSErgo_CPT::META_INDEX ); ?>" id="wsergo_index" value="<?php echo esc_attr( (string) get_post_meta( $post_id, WSErgo_CPT::META_INDEX, true ) ); ?>" class="widefat" inputmode="decimal" />
		</p>
		<?php if ( $show_lock ) : ?>
		<p>
			<label>
				<input type="checkbox" name="wsergo_index_locked" value="1" <?php checked( get_post_meta( $post_id, WSErgo_CPT::META_INDEX_LOCKED, true ), '1' ); ?> />
				<?php esc_html_e( 'Сводный индекс E (0–100): пустое поле — расчёт по активной модели (DSL или взвешенное среднее) и иерархии.', 'worldstat-ergonomics' ); ?>
			</label>
		</p>
		<?php endif; ?>
		<?php $this->render_leaf_indicator_inputs( $post_id ); ?>
		<hr />
		<p class="description">
			<?php esc_html_e( 'Сводный индекс E (0–100): пустое поле — расчёт по активной модели (DSL или взвешенное среднее) и иерархии.', 'worldstat-ergonomics' ); ?>
		</p>
		<?php
		$labels = WSErgo_Model::get_dimension_labels();
		foreach ( WSErgo_Model::DIMENSION_KEYS as $dim ) {
			$key = WSErgo_Model::meta_key_for_dimension( $dim );
			$val = get_post_meta( $post_id, $key, true );
			?>
			<p>
				<label for="<?php echo esc_attr( $key ); ?>"><strong><?php echo esc_html( $labels[ $dim ] ); ?></strong> <span class="description">(0Р Р†Р вЂљРІР‚Сљ100)</span></label>
				<input type="text" name="<?php echo esc_attr( $key ); ?>" id="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( (string) $val ); ?>" class="widefat" inputmode="decimal" />
			</p>
			<?php
		}
		$ind = (string) get_post_meta( $post_id, WSErgo_CPT::META_INDICATORS_JSON, true );
		$coef = (string) get_post_meta( $post_id, WSErgo_CPT::META_COEFF_OVERRIDES, true );
		?>
		<p>
			<label for="wsergo_indicators_json"><strong><?php esc_html_e( 'Листовые показатели (JSON, опц.)', 'worldstat-ergonomics' ); ?></strong></label>
			<textarea name="wsergo_indicators_json" id="wsergo_indicators_json" class="widefat" rows="3" placeholder="{ }"><?php echo esc_textarea( $ind ); ?></textarea>
		</p>
		<p>
			<label for="wsergo_coeff_overrides_json"><strong><?php esc_html_e( 'Переопределение коэффициентов k_* (JSON)', 'worldstat-ergonomics' ); ?></strong></label>
			<textarea name="wsergo_coeff_overrides_json" id="wsergo_coeff_overrides_json" class="widefat" rows="2" placeholder='{ &quot;k_climate&quot;: 1.05 }'><?php echo esc_textarea( $coef ); ?></textarea>
		</p>
		<?php
	}

	public function add_import_page(): void {
		add_submenu_page(
			'worldstat',
			__( 'Импорт кварталов (CSV)', 'worldstat-ergonomics' ),
			__( 'Импорт кварталов (CSV)', 'worldstat-ergonomics' ),
			'manage_options',
			'wsergo-import-districts',
			[ $this, 'render_import_page' ]
		);
	}

	/**
	 * Р В Р Р‹Р В РЎвЂќР РЋР вЂљР В РЎвЂР В РЎвЂ”Р РЋРІР‚С™Р РЋРІР‚в„– Р В Р вЂ Р В РЎвЂќР В Р’В»Р В Р’В°Р В РўвЂР В РЎвЂўР В РЎвЂќ Р В Р вЂ¦Р В Р’В°Р РЋР С“Р РЋРІР‚С™Р РЋР вЂљР В РЎвЂўР В Р’ВµР В РЎвЂќ (Р В Р вЂ Р РЋРІР‚в„–Р В Р’В·Р РЋРІР‚в„–Р В Р вЂ Р В Р’В°Р В Р’ВµР РЋРІР‚С™Р РЋР С“Р РЋР РЏ Р В РЎвЂР В Р’В· render_settings_page Р Р†Р вЂљРІР‚Сњ Р В РЎвЂ“Р В Р’В°Р РЋР вЂљР В Р’В°Р В Р вЂ¦Р РЋРІР‚С™Р В РЎвЂР РЋР вЂљР В РЎвЂўР В Р вЂ Р В Р’В°Р В Р вЂ¦Р В Р вЂ¦Р В Р’В°Р РЋР РЏ Р В Р’В·Р В Р’В°Р В РЎвЂ“Р РЋР вЂљР РЋРЎвЂњР В Р’В·Р В РЎвЂќР В Р’В°).
	 */
	private function enqueue_settings_page_assets(): void {
		$js_path = WSERGO_DIR . 'core/public/assets/js/admin-settings.js';
		$js_ver  = is_readable( $js_path ) ? (string) filemtime( $js_path ) : WSERGO_VERSION;
		wp_enqueue_style( 'wsergo-admin', WSERGO_URL . 'core/public/assets/css/admin.css', [], WSERGO_VERSION );
		wp_enqueue_script(
			'wsergo-admin-settings',
			WSERGO_URL . 'core/public/assets/js/admin-settings.js',
			[ 'jquery' ],
			$js_ver,
			true
		);
		wp_localize_script(
			'wsergo-admin-settings',
			'wsergoAdminSettings',
			[
				'macroAxisOpt' => WSErgo_Settings::OPTION_MACRO_AXIS_TERMS,
				'formulaNonce' => wp_create_nonce( 'wsergo_settings_ajax' ),
			]
		);
		wp_enqueue_script( 'jquery' );
		if ( class_exists( 'WSErgo_Country_Admin' ) ) {
			WSErgo_Country_Admin::enqueue_settings_assets( 'worldstat_page_wsergo-settings' );
		}
		if ( class_exists( 'WSErgo_Level_Registry' ) ) {
			$js_rel = 'public/assets/js/district-tab-admin.js';
			$js_path = WSErgo_Level_Registry::path( 'territory', $js_rel );
			if ( is_readable( $js_path ) ) {
				wp_enqueue_script(
					'wsergo-district-tab-admin',
					WSErgo_Level_Registry::url( 'territory', $js_rel ),
					[ 'jquery', 'chart-js' ],
					WSErgo_Level_Registry::asset_version( 'territory', $js_rel ),
					true
				);
				wp_localize_script(
					'wsergo-district-tab-admin',
					'wsergoDistrictTab',
					[
						'ajaxUrl' => admin_url( 'admin-ajax.php' ),
						'nonce'   => wp_create_nonce( 'wsergo_settings_ajax' ),
						'i18n'    => [
							'saved'          => __( 'Веса сохранены.', 'worldstat-ergonomics' ),
							'confirmRetrain' => __( 'Пересчитать метрики для всех записей wsp_district? На больших сайтах это может занять время.', 'worldstat-ergonomics' ),
							'running'        => __( 'Выполняется пересчёт…', 'worldstat-ergonomics' ),
						],
					]
				);
			}
		}
	}

	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$weights      = get_option( 'wsergo_dimension_weights', WSErgo_Model::get_default_weights() );
		if ( ! is_array( $weights ) ) {
			$weights = WSErgo_Model::get_default_weights();
		}
		$labels       = WSErgo_Model::get_dimension_labels();
		$models       = WSErgo_Settings::get_models();
		$active_id    = (string) get_option( WSErgo_Settings::OPTION_ACTIVE_MODEL, 'default_weighted' );
		$coeffs       = WSErgo_Settings::get_coefficients();
		$agg          = WSErgo_Settings::get_aggregation();
		if ( ! is_array( $agg ) ) {
			$agg = [];
		}
		$agg = array_merge(
			[
				'room_to_building'       => 'mean',
				'w_building_in_district' => 0.5,
				'w_yard_in_district'     => 0.5,
				'district_to_city'       => 'mean',
				'city_to_region'         => 'pop_weighted',
				'region_to_country'      => 'pop_weighted',
			],
			$agg
		);
		$active_model = WSErgo_Settings::get_active_model();
		$allowed_txt  = implode( ', ', array_keys( WSErgo_Settings::get_leaf_formula_allowed_ids() ) );
		$indicator_defs = get_option( WSErgo_Indicators::OPTION_DEFINITIONS, [] );
		if ( ! is_array( $indicator_defs ) ) {
			$indicator_defs = [];
		}
		$indicator_defs = array_values( $indicator_defs );
		$city_field_map = get_option( WSErgo_Settings::OPTION_CITY_FIELD_MAP, [] );
		if ( ! is_array( $city_field_map ) ) {
			$city_field_map = [];
		}
		$city_field_map = array_values( $city_field_map );
		$city_csv_bindings = get_option( WSErgo_Settings::OPTION_CITY_CSV_BINDINGS, [] );
		if ( ! is_array( $city_csv_bindings ) ) {
			$city_csv_bindings = [];
		}
		$source_choices = class_exists( 'WSErgo_City_Bridge' ) ? WSErgo_City_Bridge::get_source_choices() : [];
		$macro_year       = WSErgo_Settings::get_macro_reference_year();
		$macro_k          = WSErgo_Settings::get_macro_k_clusters();
		$macro_bindings   = WSErgo_Settings::get_macro_csv_bindings();
		$macro_e_w        = WSErgo_Settings::get_macro_e_axis_weights();
		$csv_files = [];
		if ( class_exists( 'WorldStat_Uploaded_Csv' ) && WorldStat_Uploaded_Csv::table_exists() ) {
			$csv_files = WorldStat_Uploaded_Csv::list_files();
		}
		$metric_labels_ru = class_exists( 'WSErgo_Country_Macro_Calculator' )
			? WSErgo_Country_Macro_Calculator::standard_metric_labels_ru()
			: [];
		$stored_cf           = WSErgo_Settings::get_macro_cluster_features();
		$cf_for_checkboxes   = count( $stored_cf ) >= 2 ? $stored_cf : [];
		$macro_axis_resolved = WSErgo_Settings::get_macro_axis_terms_resolved();
		$signals_ui          = class_exists( 'WSErgo_Country_Macro_Calculator' ) ? WSErgo_Country_Macro_Calculator::macro_cluster_signal_allowlist( 'country' ) : [];
		$macro_axis_labels   = class_exists( 'WSErgo_Country_Macro_Calculator' ) ? WSErgo_Country_Macro_Calculator::macro_axis_labels_ru() : [];
		$macro_extra_signals_text = class_exists( 'WSErgo_Settings' ) ? (string) get_option( WSErgo_Settings::OPTION_MACRO_EXTRA_SIGNALS_TEXT, '' ) : '';

		if ( class_exists( 'WSErgo_Country_Admin' ) ) {
			$wsergo_panel_vars = WSErgo_Country_Admin::prepare_panel_vars(
				[
					'models'                   => $models,
					'active_id'                => $active_id,
					'coeffs'                   => $coeffs,
					'agg'                      => $agg,
					'active_model'             => $active_model,
					'allowed_txt'              => $allowed_txt,
					'macro_year'               => $macro_year,
					'macro_k'                  => $macro_k,
					'macro_bindings'           => $macro_bindings,
					'macro_e_w'                => $macro_e_w,
					'stored_cf'                => $stored_cf,
					'cf_for_checkboxes'        => $cf_for_checkboxes,
					'macro_axis_resolved'      => $macro_axis_resolved,
					'signals_ui'               => $signals_ui,
					'macro_axis_labels'        => $macro_axis_labels,
					'macro_extra_signals_text' => $macro_extra_signals_text,
				]
			);
		} else {
			$wsergo_panel_vars = [];
		}

		$wsergo_panel_vars = array_merge(
			compact(
			'weights',
			'labels',
			'models',
			'active_id',
			'coeffs',
			'agg',
			'active_model',
			'allowed_txt',
			'indicator_defs',
			'city_field_map',
			'city_csv_bindings',
			'source_choices',
			'macro_year',
			'macro_k',
			'macro_bindings',
			'macro_e_w',
			'csv_files',
			'metric_labels_ru',
			'stored_cf',
			'cf_for_checkboxes',
			'macro_axis_resolved',
			'signals_ui',
			'macro_axis_labels',
			'macro_extra_signals_text'
			),
			$wsergo_panel_vars
		);

		settings_errors();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Эргономичность', 'worldstat-ergonomics' ); ?></h1>
			<p class="description" style="margin-top:0;">
				<?php esc_html_e( 'Настройки по уровням подключаются автоматически из levels/{id}/admin/panel.php (см. bootstrap/levels-registry.php).', 'worldstat-ergonomics' ); ?>
			</p>

			<?php
			if ( class_exists( 'WSErgo_Admin_Shell' ) ) {
				WSErgo_Admin_Shell::render_scope_nav();
				WSErgo_Admin_Shell::render_settings_tabs_script();
				WSErgo_Admin_Shell::render_level_panels( $wsergo_panel_vars );
			} else {
				?>
				<div class="notice notice-error inline" style="margin:12px 0;padding:12px;">
					<p style="margin:0;"><?php esc_html_e( 'Класс WSErgo_Admin_Shell не загружен — перезагрузите плагин.', 'worldstat-ergonomics' ); ?></p>
				</div>
				<?php
			}
			?>

		</div>
		<?php
	}

	public function render_import_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$notice = '';
		if ( isset( $_POST['wsergo_import_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wsergo_import_nonce'] ) ), 'wsergo_import_districts' ) ) {
			if ( ! empty( $_FILES['wsergo_csv']['tmp_name'] ) ) {
				$notice = $this->process_csv_import( sanitize_text_field( wp_unslash( $_FILES['wsergo_csv']['tmp_name'] ) ) );
			}
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Импорт кварталов (CSV)', 'worldstat-ergonomics' ); ?></h1>
			<?php if ( $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
			<?php endif; ?>
			<p><?php esc_html_e( 'Формат UTF-8, разделитель запятая. Первая строка — заголовки:', 'worldstat-ergonomics' ); ?></p>
			<code>title,city_id,functionality,safety,comfort,livability,masterability,manageability,index</code>
			<p class="description"><?php esc_html_e( 'title — название квартала; city_id — ID города (wsp_city). Допустимы псевдонимы: accessibility→functionality, environment→livability. Пустые ячейки пропускаются.', 'worldstat-ergonomics' ); ?></p>
			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'wsergo_import_districts', 'wsergo_import_nonce' ); ?>
				<p><input type="file" name="wsergo_csv" accept=".csv,text/csv" required /></p>
				<?php submit_button( __( 'Загрузить', 'worldstat-ergonomics' ) ); ?>
			</form>
		</div>
		<?php
	}

	private function process_csv_import( string $tmp_path ): string {
		$fh = fopen( $tmp_path, 'rb' );
		if ( ! $fh ) {
			return __( 'Не удалось прочитать файл.', 'worldstat-ergonomics' );
		}
		$header = fgetcsv( $fh );
		if ( ! $header ) {
			fclose( $fh );
			return __( 'Не удалось прочитать файл.', 'worldstat-ergonomics' );
		}
		$header = array_map( 'trim', array_map( 'strtolower', $header ) );
		$map    = array_flip( $header );

		$need = [ 'title', 'city_id' ];
		foreach ( $need as $k ) {
			if ( ! isset( $map[ $k ] ) ) {
				fclose( $fh );
				return __( 'Не удалось прочитать файл.', 'worldstat-ergonomics' );
			}
		}

		$n = 0;
		while ( ( $row = fgetcsv( $fh ) ) !== false ) {
			$title   = isset( $row[ $map['title'] ] ) ? trim( (string) $row[ $map['title'] ] ) : '';
			$city_id = isset( $row[ $map['city_id'] ] ) ? (int) $row[ $map['city_id'] ] : 0;
			if ( $title === '' || $city_id <= 0 ) {
				continue;
			}
			$post_id = wp_insert_post(
				[
					'post_type'   => WSErgo_CPT::SLUG_DISTRICT,
					'post_title'  => $title,
					'post_status' => 'publish',
				],
				true
			);
			if ( is_wp_error( $post_id ) ) {
				continue;
			}
			update_post_meta( $post_id, WSErgo_CPT::META_CITY_ID, $city_id );

			$csv_priority = [
				WSErgo_Model::DIM_FUNCTIONALITY    => [ 'functionality', 'accessibility' ],
				WSErgo_Model::DIM_SAFETY           => [ 'safety' ],
				WSErgo_Model::DIM_COMFORT          => [ 'comfort' ],
				WSErgo_Model::DIM_LIVABILITY       => [ 'livability', 'environment' ],
				WSErgo_Model::DIM_MASTERABILITY    => [ 'masterability' ],
				WSErgo_Model::DIM_MANAGEABILITY    => [ 'manageability' ],
			];
			foreach ( $csv_priority as $dim => $aliases ) {
				foreach ( $aliases as $csv ) {
					if ( ! isset( $map[ $csv ] ) ) {
						continue;
					}
					$v = trim( (string) $row[ $map[ $csv ] ] );
					if ( $v === '' ) {
						continue;
					}
					update_post_meta( $post_id, WSErgo_Model::meta_key_for_dimension( $dim ), (float) str_replace( ',', '.', $v ) );
					break;
				}
			}
			if ( isset( $map['index'] ) ) {
				$iv = trim( (string) $row[ $map['index'] ] );
				if ( $iv !== '' ) {
					update_post_meta( $post_id, WSErgo_CPT::META_INDEX, (float) str_replace( ',', '.', $iv ) );
					update_post_meta( $post_id, WSErgo_CPT::META_INDEX_LOCKED, '1' );
				}
			}
			WSErgo_CPT::recalculate_index_if_needed( $post_id );
			$n++;
		}
		fclose( $fh );

		return sprintf(
			/* translators: %d: count */
			__( 'Импортировано кварталов: %d.', 'worldstat-ergonomics' ),
			$n
		);
	}

	public function sanitize_data_labels_ru( $input ): array {
		$stored = get_option( WSErgo_Settings::OPTION_DATA_LABELS_RU, [] );
		if ( ! is_array( $stored ) ) {
			$stored = [];
		}
		if ( ! is_array( $input ) ) {
			$input = [];
		}
		$out = [];
		foreach ( $input as $k => $v ) {
			$key = sanitize_key( (string) $k );
			if ( $key === '' || strlen( $key ) > 96 ) {
				continue;
			}
			$text = sanitize_text_field( (string) $v );
			if ( $text === '' ) {
				continue;
			}
			if ( function_exists( 'mb_substr' ) ) {
				$text = mb_substr( $text, 0, 240 );
			} else {
				$text = substr( $text, 0, 240 );
			}
			$out[ $key ] = $text;
		}
		// ╨Ъ╨╗╤О╤З╨╕, ╨║╨╛╤В╨╛╤А╤Л╤Е ╨╜╨╡ ╨▒╤Л╨╗╨╛ ╨▓ POST (╨╜╨░╨┐╤А╨╕╨╝╨╡╤А ╤В╨╛╨╗╤М╨║╨╛ ╤З╤В╨╛ ╨┤╨╛╨▒╨░╨▓╨╗╨╡╨╜╨╜╤Л╨╣ ╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╤М╤Б╨║╨╕╨╣ ╨┐╨░╤А╨░╨╝╨╡╤В╤А), ╤Б╨╛╤Е╤А╨░╨╜╤П╨╡╨╝ ╨╕╨╖ ╨╛╨┐╤Ж╨╕╨╕.
		foreach ( $stored as $k => $v ) {
			$key = sanitize_key( (string) $k );
			if ( $key === '' || strlen( $key ) > 96 || array_key_exists( $key, $input ) ) {
				continue;
			}
			$text = sanitize_text_field( (string) $v );
			if ( $text === '' ) {
				continue;
			}
			if ( function_exists( 'mb_substr' ) ) {
				$text = mb_substr( $text, 0, 240 );
			} else {
				$text = substr( $text, 0, 240 );
			}
			if ( ! isset( $out[ $key ] ) ) {
				$out[ $key ] = $text;
			}
		}
		if ( class_exists( 'WSErgo_Settings' ) ) {
			foreach ( WSErgo_Settings::get_macro_custom_metric_slugs_effective() as $cs ) {
				if ( $cs === '' ) {
					continue;
				}
				if ( ! isset( $out[ $cs ] ) || (string) $out[ $cs ] === '' ) {
					$out[ $cs ] = WSErgo_Settings::default_ru_label_for_custom_metric_slug( $cs );
				}
			}
		}
		return $out;
	}
	public function sanitize_macro_reference_country_post_id( $input ): int {
		$id = is_numeric( $input ) ? (int) $input : 0;
		if ( $id <= 0 ) {
			return 0;
		}
		if ( ! class_exists( 'WorldStat_Country_CPT' ) ) {
			return 0;
		}
		$p = get_post( $id );
		if ( ! $p || $p->post_type !== WorldStat_Country_CPT::SLUG ) {
			return 0;
		}
		if ( $p->post_status !== 'publish' ) {
			return 0;
		}
		$iso2 = strtoupper( trim( (string) get_post_meta( $id, 'wsp_iso_alpha2', true ) ) );
		return strlen( $iso2 ) === 2 ? $id : 0;
	}
	public function sanitize_macro_criteria_matrix( $input ): array {
		if ( ! class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			return [];
		}
		if ( null === $input || false === $input || '' === $input ) {
			$input = [];
		} elseif ( ! is_array( $input ) ) {
			$stored = get_option( WSErgo_Settings::OPTION_MACRO_CRITERIA_MATRIX, [] );
			$input  = is_array( $stored ) ? $stored : [];
		}
		$allow = array_flip( WSErgo_Country_Macro_Calculator::macro_signal_allowlist() );
		$axes  = [ 'F', 'Cm', 'H', 'A', 'S', 'Ct' ];
		$out   = [];
		foreach ( $input as $sig => $row ) {
			$k = sanitize_key( (string) $sig );
			if ( $k === '' || ! isset( $allow[ $k ] ) ) {
				continue;
			}
			if ( ! is_array( $row ) ) {
				continue;
			}
			foreach ( $axes as $ax ) {
				if ( ! empty( $row[ $ax ] ) ) {
					if ( ! isset( $out[ $k ] ) ) {
						$out[ $k ] = [];
					}
					$out[ $k ][ $ax ] = true;
				}
			}
		}
		return $out;
	}

	/**
	 * @param mixed $input
	 * @return array<string, array<string, float>>
	 */
	public function sanitize_macro_criteria_weights( $input ): array {
		if ( ! class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			return [];
		}
		if ( ! is_array( $input ) ) {
			$stored = get_option( WSErgo_Settings::OPTION_MACRO_CRITERIA_WEIGHTS, [] );
			$input  = is_array( $stored ) ? $stored : [];
		}
		$allow = array_flip( WSErgo_Country_Macro_Calculator::macro_signal_allowlist() );
		$axes  = [ 'F', 'Cm', 'H', 'A', 'S', 'Ct' ];
		$out   = [];
		foreach ( $input as $sig => $row ) {
			$k = sanitize_key( (string) $sig );
			if ( $k === '' || ! isset( $allow[ $k ] ) ) {
				continue;
			}
			if ( ! is_array( $row ) ) {
				continue;
			}
			foreach ( $axes as $ax ) {
				if ( ! isset( $row[ $ax ] ) ) {
					continue;
				}
				$s = trim( (string) $row[ $ax ] );
				if ( $s === '' ) {
					continue;
				}
				$w = (float) str_replace( ',', '.', $s );
				if ( $w < 0 ) {
					$w = 0.0;
				}
				if ( ! isset( $out[ $k ] ) ) {
					$out[ $k ] = [];
				}
				$out[ $k ][ $ax ] = $w;
			}
		}
		return $out;
	}

	/**
	 * @param mixed $input
	 * @return array<string, array<string, bool>>
	 */
	public function sanitize_macro_criteria_inverts( $input ): array {
		if ( ! class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			return [];
		}
		if ( null === $input || false === $input || '' === $input ) {
			$input = [];
		} elseif ( ! is_array( $input ) ) {
			$stored = get_option( WSErgo_Settings::OPTION_MACRO_CRITERIA_INVERTS, [] );
			$input  = is_array( $stored ) ? $stored : [];
		}
		$allow = array_flip( WSErgo_Country_Macro_Calculator::macro_signal_allowlist() );
		$axes  = [ 'F', 'Cm', 'H', 'A', 'S', 'Ct' ];
		$out   = [];
		foreach ( $input as $sig => $row ) {
			$k = sanitize_key( (string) $sig );
			if ( $k === '' || ! isset( $allow[ $k ] ) ) {
				continue;
			}
			if ( ! is_array( $row ) ) {
				continue;
			}
			foreach ( $axes as $ax ) {
				if ( ! array_key_exists( $ax, $row ) ) {
					continue;
				}
				if ( ! isset( $out[ $k ] ) ) {
					$out[ $k ] = [];
				}
				$out[ $k ][ $ax ] = filter_var( $row[ $ax ], FILTER_VALIDATE_BOOLEAN );
			}
		}
		return $out;
	}

	/**
	 * ╨Я╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╤М╤Б╨║╨╕╨╡ ╨┐╤А╨╛╨╕╨╖╨▓╨╛╨┤╨╜╤Л╨╡ ╨┐╨╛╨║╨░╨╖╨░╤В╨╡╨╗╨╕ (╨║╨░╨╗╤М╨║╤Г╨╗╤П╤В╨╛╤А ╨╜╨░ ╨▓╨║╨╗╨░╨┤╨║╨╡ ┬л╨Ф╨░╨╜╨╜╤Л╨╡┬╗).
	 *
	 * @param mixed $input
	 * @return list<array{slug:string,op:string,key_a:string,key_b:string,const:float}>
	 */
	public function sanitize_macro_custom_metrics( $input ): array {
		if ( ! is_array( $input ) ) {
			$stored = get_option( WSErgo_Settings::OPTION_MACRO_CUSTOM_METRICS, [] );
			$input  = is_array( $stored ) ? $stored : [];
		}
		$ops_ok = [ 'add', 'sub', 'mul', 'div', 'scale_mul', 'scale_add' ];
		$out    = [];
		$seen   = [];
		foreach ( $input as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$slug = sanitize_key( (string) ( $row['slug'] ?? '' ) );
			if ( $slug === '' || strlen( $slug ) > 96 ) {
				continue;
			}
			if ( isset( $seen[ $slug ] ) ) {
				continue;
			}
			$op = sanitize_key( (string) ( $row['op'] ?? '' ) );
			if ( ! in_array( $op, $ops_ok, true ) ) {
				continue;
			}
			$ka = sanitize_key( (string) ( $row['key_a'] ?? '' ) );
			$kb = sanitize_key( (string) ( $row['key_b'] ?? '' ) );
			$cr = $row['const'] ?? '';
			$c  = is_numeric( $cr ) ? (float) str_replace( ',', '.', (string) $cr ) : 0.0;
			if ( in_array( $op, [ 'add', 'sub', 'mul', 'div' ], true ) ) {
				if ( $ka === '' || $kb === '' ) {
					continue;
				}
			} elseif ( in_array( $op, [ 'scale_mul', 'scale_add' ], true ) ) {
				if ( $ka === '' ) {
					continue;
				}
			}
			$seen[ $slug ] = true;
			$out[]         = [
				'slug'   => $slug,
				'op'     => $op,
				'key_a'  => $ka,
				'key_b'  => $kb,
				'const'  => $c,
			];
			if ( count( $out ) >= 30 ) {
				break;
			}
		}
		return $out;
	}

	public function handle_delete_custom_metric(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'worldstat-ergonomics' ) );
		}
		check_admin_referer( 'wsergo_delete_custom_metric' );
		$slug = isset( $_GET['slug'] ) ? sanitize_key( (string) wp_unslash( $_GET['slug'] ) ) : '';
		if ( $slug === '' || ! class_exists( 'WSErgo_Settings' ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=wsergo-settings#tab-data' ) );
			exit;
		}
		$allowed = array_flip( WSErgo_Settings::get_macro_custom_metric_slugs() );
		if ( ! isset( $allowed[ $slug ] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=wsergo-settings#tab-data' ) );
			exit;
		}
		$defs = WSErgo_Settings::get_macro_custom_metrics();
		$defs = array_values(
			array_filter(
				$defs,
				static function ( $row ) use ( $slug ) {
					if ( ! is_array( $row ) ) {
						return false;
					}
					return sanitize_key( (string) ( $row['slug'] ?? '' ) ) !== $slug;
				}
			)
		);
		update_option( WSErgo_Settings::OPTION_MACRO_CUSTOM_METRICS, $defs );

		$opt_matrix = WSErgo_Settings::OPTION_MACRO_CRITERIA_MATRIX;
		$opt_w      = WSErgo_Settings::OPTION_MACRO_CRITERIA_WEIGHTS;
		$opt_i      = WSErgo_Settings::OPTION_MACRO_CRITERIA_INVERTS;
		foreach ( [ $opt_matrix, $opt_w, $opt_i ] as $opt ) {
			$arr = get_option( $opt, [] );
			if ( is_array( $arr ) && isset( $arr[ $slug ] ) ) {
				unset( $arr[ $slug ] );
				update_option( $opt, $arr );
			}
		}
		$labels = get_option( WSErgo_Settings::OPTION_DATA_LABELS_RU, [] );
		if ( is_array( $labels ) && isset( $labels[ $slug ] ) ) {
			unset( $labels[ $slug ] );
			update_option( WSErgo_Settings::OPTION_DATA_LABELS_RU, $labels );
		}
		$cf = get_option( WSErgo_Settings::OPTION_MACRO_CLUSTER_FEATURES, [] );
		if ( is_array( $cf ) ) {
			$cf = array_values(
				array_filter(
					$cf,
					static function ( $x ) use ( $slug ) {
						return sanitize_key( (string) $x ) !== $slug;
					}
				)
			);
			update_option( WSErgo_Settings::OPTION_MACRO_CLUSTER_FEATURES, $cf );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=wsergo-settings&settings-updated=1#tab-data' ) );
		exit;
	}

	/**
	 * ╨г╨┤╨░╨╗╤П╨╡╤В ╨╛╨┤╨╕╨╜ ╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╤М╤Б╨║╨╕╨╣ ╨┐╨░╤А╨░╨╝╨╡╤В╤А ╨│╨╛╤А╨╛╨┤╨░ (╨║╨░╨╗╤М╨║╤Г╨╗╤П╤В╨╛╤А): ╨┐╤А╨░╨▓╨╕╨╗╨╛, ╨╝╨░╤В╤А╨╕╤Ж╨░, ╨┐╨╛╨┤╨┐╨╕╤Б╤М, k-means.
	 */
	private function remove_city_custom_metric_slug( string $slug ): bool {
		$slug = sanitize_key( $slug );
		if ( $slug === '' || ! class_exists( 'WSErgo_Settings' ) ) {
			return false;
		}
		$allowed = array_flip( WSErgo_Settings::get_city_macro_custom_metric_slugs() );
		if ( ! isset( $allowed[ $slug ] ) ) {
			return false;
		}
		$defs = WSErgo_Settings::get_city_macro_custom_metrics();
		$defs = array_values(
			array_filter(
				$defs,
				static function ( $row ) use ( $slug ) {
					if ( ! is_array( $row ) ) {
						return false;
					}
					return sanitize_key( (string) ( $row['slug'] ?? '' ) ) !== $slug;
				}
			)
		);
		update_option( WSErgo_Settings::OPTION_CITY_MACRO_CUSTOM_METRICS, $defs );

		$opt_matrix = WSErgo_Settings::OPTION_CITY_MACRO_CRITERIA_MATRIX;
		$opt_w      = WSErgo_Settings::OPTION_CITY_MACRO_CRITERIA_WEIGHTS;
		$opt_i      = WSErgo_Settings::OPTION_CITY_MACRO_CRITERIA_INVERTS;
		foreach ( [ $opt_matrix, $opt_w, $opt_i ] as $opt ) {
			$arr = get_option( $opt, [] );
			if ( is_array( $arr ) && isset( $arr[ $slug ] ) ) {
				unset( $arr[ $slug ] );
				update_option( $opt, $arr );
			}
		}
		$labels = get_option( WSErgo_Settings::OPTION_CITY_DATA_LABELS_RU, [] );
		if ( is_array( $labels ) && isset( $labels[ $slug ] ) ) {
			unset( $labels[ $slug ] );
			update_option( WSErgo_Settings::OPTION_CITY_DATA_LABELS_RU, $labels );
		}
		$cf = get_option( WSErgo_Settings::OPTION_CITY_MACRO_CLUSTER_FEATURES, [] );
		if ( is_array( $cf ) ) {
			$cf = array_values(
				array_filter(
					$cf,
					static function ( $x ) use ( $slug ) {
						return sanitize_key( (string) $x ) !== $slug;
					}
				)
			);
			update_option( WSErgo_Settings::OPTION_CITY_MACRO_CLUSTER_FEATURES, $cf );
		}
		return true;
	}

	/**
	 * ╨г╨┤╨░╨╗╨╡╨╜╨╕╨╡ ╨│╨╛╤А╨╛╨┤╤Б╨║╨╛╨│╨╛ ╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╤М╤Б╨║╨╛╨│╨╛ ╨┐╨░╤А╨░╨╝╨╡╤В╤А╨░ (╨║╨░╨╗╤М╨║╤Г╨╗╤П╤В╨╛╤А ┬л╨Ф╨░╨╜╨╜╤Л╨╡┬╗ ╨│╨╛╤А╨╛╨┤╨░).
	 */
	public function handle_delete_city_custom_metric(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'worldstat-ergonomics' ) );
		}
		check_admin_referer( 'wsergo_delete_city_custom_metric' );
		$slug = isset( $_GET['slug'] ) ? sanitize_key( (string) wp_unslash( $_GET['slug'] ) ) : '';
		if ( $slug === '' ) {
			wp_safe_redirect( admin_url( 'admin.php?page=wsergo-settings#tab-city-data' ) );
			exit;
		}
		$this->remove_city_custom_metric_slug( $slug );
		wp_safe_redirect( admin_url( 'admin.php?page=wsergo-settings&settings-updated=1#tab-city-data' ) );
		exit;
	}

	/**
	 * ╨Ь╨░╤Б╤Б╨╛╨▓╨╛╨╡ ╤Г╨┤╨░╨╗╨╡╨╜╨╕╨╡ ╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╤М╤Б╨║╨╕╤Е ╨┐╨░╤А╨░╨╝╨╡╤В╤А╨╛╨▓ ╨│╨╛╤А╨╛╨┤╨░ (╤З╨╡╨║╨▒╨╛╨║╤Б╤Л ╨▓ ╨╝╨░╤В╤А╨╕╤Ж╨╡ / ╤В╨░╨▒╨╗╨╕╤Ж╨╡ ╨┐╤А╨░╨▓╨╕╨╗).
	 */
	public function handle_bulk_delete_city_custom_metrics(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'worldstat-ergonomics' ) );
		}
		check_admin_referer( 'wsergo_bulk_delete_city_custom_metrics' );
		$slugs = isset( $_POST['slugs'] ) && is_array( $_POST['slugs'] ) ? wp_unslash( $_POST['slugs'] ) : [];
		if ( ! is_array( $slugs ) || empty( $slugs ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=wsergo-settings#tab-city-data' ) );
			exit;
		}
		$allowed = array_flip( WSErgo_Settings::get_city_macro_custom_metric_slugs() );
		foreach ( $slugs as $s ) {
			$k = sanitize_key( (string) $s );
			if ( $k !== '' && isset( $allowed[ $k ] ) ) {
				$this->remove_city_custom_metric_slug( $k );
			}
		}
		wp_safe_redirect( admin_url( 'admin.php?page=wsergo-settings&settings-updated=1#tab-city-data' ) );
		exit;
	}

	public function ajax_test_city_formula(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'worldstat-ergonomics' ) );
		}
		check_ajax_referer( 'wsergo_settings_ajax', 'nonce' );
		$formula = isset( $_POST['formula'] ) ? wp_unslash( (string) $_POST['formula'] ) : '';
		$formula = trim( $formula );
		$allowed = WSErgo_Settings::get_city_leaf_formula_allowed_ids();
		$vcheck  = WSErgo_Expression::validate( $formula, $allowed );
		if ( ! $vcheck['ok'] ) {
			wp_send_json_error( [ 'message' => $vcheck['error'] ?? '?' ] );
		}
		if ( $formula === '' ) {
			wp_send_json_success( [ 'value' => __( '(пусто — будет взвешенное среднее)', 'worldstat-ergonomics' ) ] );
		}
		$vars = [];
		foreach ( array_keys( $allowed ) as $id ) {
			$vars[ $id ] = 50.0;
		}
		try {
			$val = WSErgo_Expression::evaluate( $formula, $vars );
			wp_send_json_success( [ 'value' => round( $val, 4 ) ] );
		} catch ( Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	/**
	 * ╨Ю╨▒╤Й╨░╤П ╨╗╨╛╨│╨╕╨║╨░ ╨║╨░╨╗╤М╨║╤Г╨╗╤П╤В╨╛╤А╨░ (╤Б╤В╤А╨░╨╜╨░ ╨╕ ╨│╨╛╤А╨╛╨┤): ╤А╨░╨╖╨▒╨╛╤А rule, merge, sanitize, update_option, ╨┐╨╛╨┤╨┐╨╕╤Б╨╕ ╨┐╨╛ ╤Г╨╝╨╛╨╗╤З╨░╨╜╨╕╤О.
	 *
	 * @param callable( mixed ): array $sanitize_cb sanitize_*_macro_custom_metrics
	 * @param callable( array ): void   $sync_labels_cb
	 */
	private function ajax_append_custom_metric_impl( string $option_key, string $nonce_action, callable $sanitize_cb, callable $sync_labels_cb ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Недостаточно прав.', 'worldstat-ergonomics' ) ] );
		}
		if ( ! check_ajax_referer( $nonce_action, 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Недостаточно прав.', 'worldstat-ergonomics' ) ] );
		}
		if ( ! class_exists( 'WSErgo_Settings' ) ) {
			wp_send_json_error( [ 'message' => __( 'Недостаточно прав.', 'worldstat-ergonomics' ) ] );
		}
		$raw  = isset( $_POST['rule'] ) ? wp_unslash( (string) $_POST['rule'] ) : '';
		$rule = $raw !== '' ? json_decode( $raw, true ) : null;
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $rule ) ) {
			wp_send_json_error( [ 'message' => __( 'Недостаточно прав.', 'worldstat-ergonomics' ) ] );
		}
		$c_raw = isset( $rule['const_val'] ) ? $rule['const_val'] : ( $rule['const'] ?? 0 );
		$row   = [
			'slug'  => isset( $rule['slug'] ) ? $rule['slug'] : '',
			'op'    => isset( $rule['op'] ) ? $rule['op'] : '',
			'key_a' => isset( $rule['key_a'] ) ? $rule['key_a'] : '',
			'key_b' => isset( $rule['key_b'] ) ? $rule['key_b'] : '',
			'const' => is_numeric( $c_raw ) ? (float) $c_raw : 0.0,
		];
		$new_slug = sanitize_key( (string) $row['slug'] );
		if ( $new_slug === '' ) {
			wp_send_json_error( [ 'message' => __( 'Недостаточно прав.', 'worldstat-ergonomics' ) ] );
		}
		$existing = get_option( $option_key, [] );
		if ( ! is_array( $existing ) ) {
			$existing = [];
		}
		foreach ( $existing as $er ) {
			if ( ! is_array( $er ) ) {
				continue;
			}
			if ( sanitize_key( (string) ( $er['slug'] ?? '' ) ) === $new_slug ) {
				wp_send_json_error( [ 'message' => __( 'Недостаточно прав.', 'worldstat-ergonomics' ) ] );
			}
		}
		if ( count( $existing ) >= 30 ) {
			wp_send_json_error( [ 'message' => __( 'Недостаточно прав.', 'worldstat-ergonomics' ) ] );
		}
		$merged    = array_merge( $existing, [ $row ] );
		$sanitized = call_user_func( $sanitize_cb, $merged );
		$present   = false;
		foreach ( $sanitized as $r ) {
			if ( is_array( $r ) && isset( $r['slug'] ) && sanitize_key( (string) $r['slug'] ) === $new_slug ) {
				$present = true;
				break;
			}
		}
		if ( ! $present ) {
			wp_send_json_error(
				[
					'message' => __( 'Правило не принято: проверьте латинские ключи столбцов A/B и операцию.', 'worldstat-ergonomics' ),
				]
			);
		}
		update_option( $option_key, $sanitized );
		call_user_func( $sync_labels_cb, $sanitized );
		wp_send_json_success( [ 'count' => count( $sanitized ) ] );
	}

	/**
	 * ╨Ф╨╛╨▒╨░╨▓╨╗╤П╨╡╤В ╨╛╨┤╨╜╨╛ ╨┐╤А╨░╨▓╨╕╨╗╨╛ ╨║╨░╨╗╤М╨║╤Г╨╗╤П╤В╨╛╤А╨░ ╨▒╨╡╨╖ ╨┐╨╛╨╗╨╜╨╛╨╣ ╨╛╤В╨┐╤А╨░╨▓╨║╨╕ ╤Д╨╛╤А╨╝╤Л (╨╛╨▒╤Е╨╛╨┤╨╕╤В ╨╗╨╕╨╝╨╕╤В PHP max_input_vars ╨╜╨░ ╨▒╨╛╨╗╤М╤И╨╕╤Е ╤Б╤В╤А╨░╨╜╨╕╤Ж╨░╤Е).
	 */
	public function ajax_append_custom_metric(): void {
		$this->ajax_append_custom_metric_impl(
			WSErgo_Settings::OPTION_MACRO_CUSTOM_METRICS,
			'wsergo_append_custom_metric',
			[ $this, 'sanitize_macro_custom_metrics' ],
			[ $this, 'sync_default_labels_for_custom_metrics' ]
		);
	}

	/**
	 * ╨У╨╛╤А╨╛╨┤╤Б╨║╨╛╨╣ ╨║╨░╨╗╤М╨║╤Г╨╗╤П╤В╨╛╤А: ╤В╨░ ╨╢╨╡ ╤Ж╨╡╨┐╨╛╤З╨║╨░, ╤З╤В╨╛ {@see ajax_append_custom_metric()}, ╨╛╨┐╤Ж╨╕╨╕ OPTION_CITY_*.
	 */
	public function ajax_append_city_custom_metric(): void {
		$this->ajax_append_custom_metric_impl(
			WSErgo_Settings::OPTION_CITY_MACRO_CUSTOM_METRICS,
			'wsergo_append_city_custom_metric',
			[ $this, 'sanitize_city_macro_custom_metrics' ],
			[ $this, 'sync_default_labels_for_city_custom_metrics' ]
		);
	}

	/**
	 * @param list<array{slug:string,op:string,key_a:string,key_b:string,const:float}> $defs
	 */
	private function sync_default_labels_for_custom_metrics( array $defs ): void {
		if ( ! class_exists( 'WSErgo_Settings' ) ) {
			return;
		}
		$labels = get_option( WSErgo_Settings::OPTION_DATA_LABELS_RU, [] );
		if ( ! is_array( $labels ) ) {
			$labels = [];
		}
		foreach ( $defs as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$s = sanitize_key( (string) ( $row['slug'] ?? '' ) );
			if ( $s === '' ) {
				continue;
			}
			if ( ! isset( $labels[ $s ] ) || (string) $labels[ $s ] === '' ) {
				$labels[ $s ] = WSErgo_Settings::default_ru_label_for_custom_metric_slug( $s );
			}
		}
		update_option( WSErgo_Settings::OPTION_DATA_LABELS_RU, $labels );
	}

	/**
	 * @param list<array{slug:string,op:string,key_a:string,key_b:string,const:float}> $defs
	 */
	private function sync_default_labels_for_city_custom_metrics( array $defs ): void {
		if ( ! class_exists( 'WSErgo_Settings' ) ) {
			return;
		}
		$labels = get_option( WSErgo_Settings::OPTION_CITY_DATA_LABELS_RU, [] );
		if ( ! is_array( $labels ) ) {
			$labels = [];
		}
		foreach ( $defs as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$s = sanitize_key( (string) ( $row['slug'] ?? '' ) );
			if ( $s === '' ) {
				continue;
			}
			if ( ! isset( $labels[ $s ] ) || (string) $labels[ $s ] === '' ) {
				$labels[ $s ] = WSErgo_Settings::default_city_data_label_ru( $s );
			}
		}
		update_option( WSErgo_Settings::OPTION_CITY_DATA_LABELS_RU, $labels );
	}
}
