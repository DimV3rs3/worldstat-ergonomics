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
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
		add_action( 'admin_menu', [ $this, 'add_import_page' ], 9 );
		add_action( 'admin_menu', [ $this, 'add_worldstat_submenu' ], 99 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin' ] );
		add_action( 'wp_ajax_wsergo_test_formula', [ $this, 'ajax_test_formula' ] );

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
	 * Старый пункт меню «Эргономика стран» убран — перенаправляем на вкладку настроек страны.
	 */
	public function redirect_legacy_countries_menu(): void {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- только редирект по slug страницы.
		if ( isset( $_GET['page'] ) && (string) wp_unslash( $_GET['page'] ) === 'wsergo-countries' ) {
			wp_safe_redirect( admin_url( 'admin.php?page=wsergo-settings#tab-data' ) );
			exit;
		}
	}

	public function register_settings(): void {
		if ( isset( $_GET['page'] ) && (string) $_GET['page'] === 'wsergo-settings' && current_user_can( 'manage_options' ) && class_exists( 'WSErgo_Settings' ) ) {
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
		$allow = array_flip( WSErgo_Country_Macro_Calculator::macro_signal_allowlist() );
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
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && in_array( $screen->post_type, [ WSErgo_CPT::SLUG_DISTRICT, WSErgo_CPT::SLUG_BUILDING, WSErgo_CPT::SLUG_ROOM, WSErgo_CPT::SLUG_YARD ], true ) ) {
				$load = true;
			}
		}
		if ( ! $load ) {
			return;
		}
		wp_enqueue_style( 'wsergo-admin', WSERGO_URL . 'assets/css/admin.css', [], WSERGO_VERSION );
		if ( false !== strpos( $hook, 'wsergo-settings' ) ) {
			wp_enqueue_script( 'jquery' );
			wp_add_inline_script(
				'jquery',
				"jQuery(function($){
					var wsergoMacroOpt = '" . esc_js( WSErgo_Settings::OPTION_MACRO_AXIS_TERMS ) . "';
					function wsergoReplaceHash(hash){
						if(!window.history || !window.history.replaceState){ return; }
						var base = window.location.pathname + window.location.search;
						window.history.replaceState(null, '', base + hash);
					}
					function wsergoActivateScope(scope){
						if(!scope){ scope = 'country'; }
						$('.wsergo-ergo-scope-nav a').removeClass('nav-tab-active');
						$('.wsergo-ergo-scope-nav a[data-wsergo-scope=\"'+scope+'\"]').addClass('nav-tab-active');
						$('.wsergo-scope-panel').hide();
						$('#wsergo-panel-'+scope).show();
					}
					function wsergoActivateInnerTab(id){
						if(!id){ return; }
						var \$a = $('.wsergo-country-inner-nav a[data-tab=\"'+id+'\"]');
						if(!\$a.length){ return; }
						$('.wsergo-country-inner-nav a').removeClass('nav-tab-active');
						\$a.addClass('nav-tab-active');
						var \$f = $('#wsergo-panel-country form.wsergo-settings-form');
						\$f.find('.wsergo-tab-panel').hide();
						\$f.find('#' + id).show();
					}
					function wsergoApplyHash(){
						var h = window.location.hash || '';
						if(h === '#ergo-city'){
							wsergoActivateScope('city');
							return;
						}
						if(h === '#ergo-territory'){
							wsergoActivateScope('territory');
							return;
						}
						wsergoActivateScope('country');
						if(h.indexOf('#tab-') === 0){
							wsergoActivateInnerTab(h.replace('#',''));
						} else {
							wsergoActivateInnerTab('tab-measure');
						}
					}
					$('.wsergo-ergo-scope-nav a[data-wsergo-scope]').on('click', function(e){
						e.preventDefault();
						var scope = $(this).data('wsergo-scope');
						if(scope === 'country'){
							wsergoActivateScope('country');
							wsergoActivateInnerTab('tab-measure');
							wsergoReplaceHash('#ergo-country');
						} else if(scope === 'city'){
							wsergoActivateScope('city');
							wsergoReplaceHash('#ergo-city');
						} else if(scope === 'territory'){
							wsergoActivateScope('territory');
							wsergoReplaceHash('#ergo-territory');
						}
					});
					$('.wsergo-country-inner-nav a[data-tab]').on('click', function(e){
						e.preventDefault();
						var id = $(this).data('tab');
						wsergoActivateScope('country');
						wsergoActivateInnerTab(id);
						wsergoReplaceHash('#' + id);
					});
					wsergoApplyHash();
					$(window).on('hashchange', function(){ wsergoApplyHash(); });
					$(document).on('click', 'a.wsergo-tab-deep-link[href^=\"#tab-\"]', function(e){
						var href = $(this).attr('href') || '';
						if(href.indexOf('#tab-') !== 0){ return; }
						e.preventDefault();
						wsergoActivateScope('country');
						wsergoActivateInnerTab(href.substring(1));
						wsergoReplaceHash(href);
					});
					function wsergoMacroNextIndex(\$tb, axis){
						var prefix = wsergoMacroOpt + '[' + axis + '][';
						var maxIx = -1;
						\$tb.find('tr').not('.wsergo-macro-term-template').each(function(){
							$(this).find('select[name], input[name]').each(function(){
								var n = this.name || '';
								var p = n.indexOf(prefix);
								if(p === -1){ return; }
								var rest = n.substring(p + prefix.length);
								var m = /^(\d+)\]/.exec(rest);
								if(m){ maxIx = Math.max(maxIx, parseInt(m[1], 10)); }
							});
						});
						return maxIx + 1;
					}
					$(document).on('click', '.wsergo-add-macro-term-row', function(){
						var axis = $(this).data('axis');
						var \$tb = $('tbody.wsergo-macro-axis-tbody[data-axis=\"'+axis+'\"]');
						var \$tpl = \$tb.find('tr.wsergo-macro-term-template').first();
						if(!\$tpl.length){ return; }
						var nextIx = wsergoMacroNextIndex(\$tb, axis);
						var \$n = \$tpl.clone();
						\$n.removeClass('wsergo-macro-term-template').removeAttr('style').removeAttr('aria-hidden').show();
						\$n.find('input, select').prop('disabled', false);
						\$n.find('input, select').each(function(){
							if(this.name){ this.name = this.name.replace(/999999/g, String(nextIx)); }
						});
						\$tb.append(\$n);
					});
					$('#wsergo-test-formula-btn').on('click', function(){
						var formula = $('#wsergo_test_formula_inline').length ? $('#wsergo_test_formula_inline').val() : $('textarea[name=\"wsergo_models[0][leaf_formula]\"]').first().val();
						$.post(ajaxurl, { action:'wsergo_test_formula', nonce:'" . esc_js( wp_create_nonce( 'wsergo_settings_ajax' ) ) . "', formula: formula }, function(r){
							if(r.success){ $('#wsergo-formula-test-result').text('E ≈ ' + r.data.value); } else { $('#wsergo-formula-test-result').text(r.data.message || 'Error'); }
						});
					});
					$('#wsergo-add-indicator-row').on('click', function(){
						var \$tpl = $('#wsergo-indicator-rows tr.wsergo-indicator-template');
						if(!\$tpl.length){ return; }
						var \$n = \$tpl.clone().removeClass('wsergo-indicator-template').show();
						\$n.find('input[type=text]').val('');
						\$n.find('select[name\$=\"[dimension]\"]').each(function(){ this.selectedIndex = 0; });
						\$n.find('select[name\$=\"[direction]\"]').each(function(){ this.selectedIndex = 0; });
						\$n.find('input[name\$=\"[vmin]\"]').val('0');
						\$n.find('input[name\$=\"[vmax]\"]').val('100');
						\$n.find('input[name\$=\"[weight]\"]').val('1');
						\$tpl.before(\$n);
					});
				});"
			);
		}
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
			__( 'Придомовая территория', 'worldstat-ergonomics' ),
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
			<label><?php esc_html_e( 'Долгота', 'worldstat-ergonomics' ); ?></label>
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
	 * Поля сырых показателей (настраиваются в «Эргономика → Показатели»).
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
				<?php esc_html_e( 'Зафиксировать индекс (не пересчитывать автоматически из дочерних объектов)', 'worldstat-ergonomics' ); ?>
			</label>
		</p>
		<?php endif; ?>
		<?php $this->render_leaf_indicator_inputs( $post_id ); ?>
		<hr />
		<p class="description">
			<?php esc_html_e( 'Шесть уровней (0–100): при сохранении, если для уровня заданы сырые показатели ниже, в мета подставляется расчётное значение по методике из настроек «Показатели».', 'worldstat-ergonomics' ); ?>
		</p>
		<?php
		$labels = WSErgo_Model::get_dimension_labels();
		foreach ( WSErgo_Model::DIMENSION_KEYS as $dim ) {
			$key = WSErgo_Model::meta_key_for_dimension( $dim );
			$val = get_post_meta( $post_id, $key, true );
			?>
			<p>
				<label for="<?php echo esc_attr( $key ); ?>"><strong><?php echo esc_html( $labels[ $dim ] ); ?></strong> <span class="description">(0–100)</span></label>
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
		$active_model = WSErgo_Settings::get_active_model();
		$allowed_txt  = implode( ', ', array_keys( WSErgo_Settings::get_leaf_formula_allowed_ids() ) );
		$indicator_defs = get_option( WSErgo_Indicators::OPTION_DEFINITIONS, [] );
		if ( ! is_array( $indicator_defs ) ) {
			$indicator_defs = [];
		}
		$indicator_defs = array_values( $indicator_defs );
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
		$stored_cf          = WSErgo_Settings::get_macro_cluster_features();
		$default_cf           = class_exists( 'WSErgo_Country_Macro_Calculator' ) ? WSErgo_Country_Macro_Calculator::default_cluster_features() : [];
		$cf_for_checkboxes    = count( $stored_cf ) >= 2 ? $stored_cf : $default_cf;
		$macro_axis_resolved = WSErgo_Settings::get_macro_axis_terms_resolved();
		$signals_ui          = class_exists( 'WSErgo_Country_Macro_Calculator' ) ? WSErgo_Country_Macro_Calculator::macro_signal_allowlist() : [];
		$macro_axis_labels   = class_exists( 'WSErgo_Country_Macro_Calculator' ) ? WSErgo_Country_Macro_Calculator::macro_axis_labels_ru() : [];
		$macro_extra_signals_text = class_exists( 'WSErgo_Settings' ) ? (string) get_option( WSErgo_Settings::OPTION_MACRO_EXTRA_SIGNALS_TEXT, '' ) : '';

		settings_errors();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Эргономичность', 'worldstat-ergonomics' ); ?></h1>
			<p class="description" style="margin-top:0;">
				<?php esc_html_e( 'Страновый индекс: CSV в разделе «Данные CSV» (в т.ч. широкие файлы country_code + year + несколько показателей), кластеризация, оси F / Cm / H / A / S / Ct и итоговый E. Рабочие настройки — под «Эргономичность страны»; город и территория пока заглушки.', 'worldstat-ergonomics' ); ?>
			</p>

			<h2 class="nav-tab-wrapper wsergo-ergo-scope-nav" style="margin-bottom:4px;">
				<a href="#ergo-country" class="nav-tab nav-tab-active" data-wsergo-scope="country"><?php esc_html_e( 'Эргономичность страны', 'worldstat-ergonomics' ); ?></a>
				<a href="#ergo-city" class="nav-tab" data-wsergo-scope="city"><?php esc_html_e( 'Эргономичность города', 'worldstat-ergonomics' ); ?></a>
				<a href="#ergo-territory" class="nav-tab" data-wsergo-scope="territory"><?php esc_html_e( 'Эргономичность территории', 'worldstat-ergonomics' ); ?></a>
			</h2>

			<div id="wsergo-panel-country" class="wsergo-scope-panel">

			<div class="notice notice-info inline" style="margin:12px 0;padding:12px;">
				<p style="margin:.4em 0;"><strong><?php esc_html_e( 'Вкладки', 'worldstat-ergonomics' ); ?></strong>
					— <a href="#tab-data" class="wsergo-tab-deep-link"><?php esc_html_e( 'перейти к «Данные»', 'worldstat-ergonomics' ); ?></a>
				</p>
				<ol style="margin:.5em 0 .5em 1.2em;list-style:decimal;padding-left:1em;">
					<li><?php esc_html_e( 'Измерения — веса шести измерений среды (здание/район) и справочные показатели; это не веса макро-осей страны.', 'worldstat-ergonomics' ); ?></li>
					<li><?php esc_html_e( 'Данные — версия методики, признаки k-means, привязка CSV к рядам для страны.', 'worldstat-ergonomics' ); ?></li>
					<li><?php esc_html_e( 'Формула — макро-оси страны (F, Cm, H…), их веса в страновом E из CSV, модели и DSL для объекта.', 'worldstat-ergonomics' ); ?></li>
				</ol>
			</div>

			<h2 class="nav-tab-wrapper wsergo-country-inner-nav" style="margin-top:8px;">
				<a href="#tab-measure" class="nav-tab nav-tab-active" data-tab="tab-measure"><?php esc_html_e( 'Измерения', 'worldstat-ergonomics' ); ?></a>
				<a href="#tab-data" class="nav-tab" data-tab="tab-data"><?php esc_html_e( 'Данные', 'worldstat-ergonomics' ); ?></a>
				<a href="#tab-formula" class="nav-tab" data-tab="tab-formula"><?php esc_html_e( 'Формула', 'worldstat-ergonomics' ); ?></a>
			</h2>

			<form class="wsergo-settings-form" method="post" action="options.php">
				<?php settings_fields( 'wsergo_settings' ); ?>

				<div id="tab-measure" class="wsergo-tab-panel">
					<h2><?php esc_html_e( 'Измерения', 'worldstat-ergonomics' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Доли шести измерений среды (функциональность, безопасность, комфорт, обитаемость, освояемость, управляемость) при сводном E по объекту; после сохранения сумма приводится к 1. В DSL это веса w_functionality, w_safety и т.д. — не путать с весами макро-осей страны (F, Cm, H, A, S, Ct) на вкладке «Формула».', 'worldstat-ergonomics' ); ?></p>
					<table class="form-table">
						<?php foreach ( WSErgo_Model::DIMENSION_KEYS as $dim ) : ?>
						<tr>
							<th scope="row"><label for="w_<?php echo esc_attr( $dim ); ?>"><?php echo esc_html( $labels[ $dim ] ); ?> <span class="description"><?php esc_html_e( '(доля влияния)', 'worldstat-ergonomics' ); ?></span></label></th>
							<td>
								<input name="wsergo_dimension_weights[<?php echo esc_attr( $dim ); ?>]" id="w_<?php echo esc_attr( $dim ); ?>" type="text" value="<?php echo esc_attr( isset( $weights[ $dim ] ) ? (string) $weights[ $dim ] : '' ); ?>" class="small-text" />
							</td>
						</tr>
						<?php endforeach; ?>
					</table>
					<hr />
					<h3><?php esc_html_e( 'Показатели', 'worldstat-ergonomics' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Шкалы сырых величин и веса показателей по осям (справочно для расчётов и отчётов).', 'worldstat-ergonomics' ); ?></p>
					<p>
						<button type="button" class="button" id="wsergo-add-indicator-row"><?php esc_html_e( 'Добавить показатель', 'worldstat-ergonomics' ); ?></button>
					</p>
					<table class="widefat striped" id="wsergo-indicator-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'ID (латиница)', 'worldstat-ergonomics' ); ?></th>
								<th><?php esc_html_e( 'Название', 'worldstat-ergonomics' ); ?></th>
								<th><?php esc_html_e( 'Уровень', 'worldstat-ergonomics' ); ?></th>
								<th><?php esc_html_e( 'Ед.', 'worldstat-ergonomics' ); ?></th>
								<th><?php esc_html_e( 'Мин', 'worldstat-ergonomics' ); ?></th>
								<th><?php esc_html_e( 'Макс', 'worldstat-ergonomics' ); ?></th>
								<th><?php esc_html_e( 'Лучше', 'worldstat-ergonomics' ); ?></th>
								<th><?php esc_html_e( 'Вес', 'worldstat-ergonomics' ); ?></th>
							</tr>
						</thead>
						<tbody id="wsergo-indicator-rows">
							<?php
							$rows_for_form = $indicator_defs;
							if ( empty( $rows_for_form ) ) {
								$rows_for_form[] = [
									'id'        => '',
									'label'     => '',
									'dimension' => WSErgo_Model::DIM_FUNCTIONALITY,
									'unit'      => '',
									'vmin'      => 0.0,
									'vmax'      => 100.0,
									'direction' => 'higher_better',
									'weight'    => 1.0,
								];
							}
							foreach ( array_values( $rows_for_form ) as $idx => $indrow ) :
								$idr = isset( $indrow['id'] ) ? (string) $indrow['id'] : '';
								?>
							<tr>
								<td><input type="text" name="wsergo_indicator_definitions[<?php echo esc_attr( (string) $idx ); ?>][id]" value="<?php echo esc_attr( $idr ); ?>" class="regular-text" pattern="[a-z0-9_\-]+" title="<?php esc_attr_e( 'Латинские буквы, цифры, _-', 'worldstat-ergonomics' ); ?>" /></td>
								<td><input type="text" name="wsergo_indicator_definitions[<?php echo esc_attr( (string) $idx ); ?>][label]" value="<?php echo esc_attr( isset( $indrow['label'] ) ? (string) $indrow['label'] : '' ); ?>" class="regular-text" /></td>
								<td>
									<select name="wsergo_indicator_definitions[<?php echo esc_attr( (string) $idx ); ?>][dimension]">
										<?php foreach ( WSErgo_Model::DIMENSION_KEYS as $dk ) : ?>
											<option value="<?php echo esc_attr( $dk ); ?>" <?php selected( isset( $indrow['dimension'] ) ? (string) $indrow['dimension'] : '', $dk ); ?>><?php echo esc_html( $labels[ $dk ] ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
								<td><input type="text" name="wsergo_indicator_definitions[<?php echo esc_attr( (string) $idx ); ?>][unit]" value="<?php echo esc_attr( isset( $indrow['unit'] ) ? (string) $indrow['unit'] : '' ); ?>" class="small-text" /></td>
								<td><input type="text" name="wsergo_indicator_definitions[<?php echo esc_attr( (string) $idx ); ?>][vmin]" value="<?php echo esc_attr( isset( $indrow['vmin'] ) ? (string) $indrow['vmin'] : '0' ); ?>" class="small-text" /></td>
								<td><input type="text" name="wsergo_indicator_definitions[<?php echo esc_attr( (string) $idx ); ?>][vmax]" value="<?php echo esc_attr( isset( $indrow['vmax'] ) ? (string) $indrow['vmax'] : '100' ); ?>" class="small-text" /></td>
								<td>
									<select name="wsergo_indicator_definitions[<?php echo esc_attr( (string) $idx ); ?>][direction]">
										<option value="higher_better" <?php selected( isset( $indrow['direction'] ) ? (string) $indrow['direction'] : 'higher_better', 'higher_better' ); ?>><?php esc_html_e( '↑ больше', 'worldstat-ergonomics' ); ?></option>
										<option value="lower_better" <?php selected( isset( $indrow['direction'] ) ? (string) $indrow['direction'] : '', 'lower_better' ); ?>><?php esc_html_e( '↓ меньше', 'worldstat-ergonomics' ); ?></option>
									</select>
								</td>
								<td><input type="text" name="wsergo_indicator_definitions[<?php echo esc_attr( (string) $idx ); ?>][weight]" value="<?php echo esc_attr( isset( $indrow['weight'] ) ? (string) $indrow['weight'] : '1' ); ?>" class="small-text" /></td>
							</tr>
							<?php endforeach; ?>
							<tr class="wsergo-indicator-template" style="display:none">
								<td><input type="text" name="wsergo_indicator_definitions[][id]" value="" class="regular-text" /></td>
								<td><input type="text" name="wsergo_indicator_definitions[][label]" value="" class="regular-text" /></td>
								<td>
									<select name="wsergo_indicator_definitions[][dimension]">
										<?php foreach ( WSErgo_Model::DIMENSION_KEYS as $dk ) : ?>
											<option value="<?php echo esc_attr( $dk ); ?>"><?php echo esc_html( $labels[ $dk ] ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
								<td><input type="text" name="wsergo_indicator_definitions[][unit]" value="" class="small-text" /></td>
								<td><input type="text" name="wsergo_indicator_definitions[][vmin]" value="0" class="small-text" /></td>
								<td><input type="text" name="wsergo_indicator_definitions[][vmax]" value="100" class="small-text" /></td>
								<td>
									<select name="wsergo_indicator_definitions[][direction]">
										<option value="higher_better"><?php esc_html_e( '↑ больше', 'worldstat-ergonomics' ); ?></option>
										<option value="lower_better"><?php esc_html_e( '↓ меньше', 'worldstat-ergonomics' ); ?></option>
									</select>
								</td>
								<td><input type="text" name="wsergo_indicator_definitions[][weight]" value="1" class="small-text" /></td>
							</tr>
						</tbody>
					</table>
				</div>

				<div id="tab-data" class="wsergo-tab-panel" style="display:none">
					<h2><?php esc_html_e( 'Данные', 'worldstat-ergonomics' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Источники и параметры для странового индекса по макроданным CSV. Поддерживаются «длинные» файлы (country_code, year, value) и широкие (country_code, year и несколько числовых столбцов — demographics, urban_infra, environment и т.д.). Для базового треугольника населения/площади по-прежнему нужны ряды population_total и surface_area_sqkm либо совместимые long-CSV; из широких файлов подтягиваются плотность, доля городского населения, лес и прочие признаки.', 'worldstat-ergonomics' ); ?></p>
					<?php if ( defined( 'WSERGO_URL' ) ) : ?>
					<p class="description">
						<a href="<?php echo esc_url( WSERGO_URL . 'data/ergo-wide-csv-reference.txt' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Справка по столбцам широких CSV (открывается в новой вкладке)', 'worldstat-ergonomics' ); ?></a>
					</p>
					<?php endif; ?>
					<table class="form-table">
						<tr>
							<th scope="row"><label for="wsergo_methodology_version"><?php esc_html_e( 'Версия методики', 'worldstat-ergonomics' ); ?></label></th>
							<td>
								<input name="wsergo_methodology_version" id="wsergo_methodology_version" type="text" value="<?php echo esc_attr( get_option( 'wsergo_methodology_version', '1.2' ) ); ?>" class="regular-text" />
								<p class="description"><?php esc_html_e( 'Условный номер или метка редакции методики (например 1.2, 2025‑A). Указывается в подписях к индексу и отчётах, чтобы было видно, по какой версии формул и наборов данных получены значения. Повышайте версию при изменении формул, весов осей или состава CSV.', 'worldstat-ergonomics' ); ?></p>
							</td>
						</tr>
					</table>
					<hr />
					<h3><?php esc_html_e( 'Признаки для k-means (макро)', 'worldstat-ergonomics' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Отметьте признаки, входящие в вектор кластеризации. Нужно не меньше двух; иначе используется встроенный набор плагина. Снимите ненужные или добавьте новые из списка.', 'worldstat-ergonomics' ); ?></p>
					<div style="max-height:220px;overflow:auto;border:1px solid #c3c4c7;padding:10px;background:#fff;">
						<?php foreach ( $signals_ui as $sig ) : ?>
							<label style="display:block;margin:.25em 0;">
								<input type="checkbox" name="<?php echo esc_attr( WSErgo_Settings::OPTION_MACRO_CLUSTER_FEATURES ); ?>[]" value="<?php echo esc_attr( $sig ); ?>" <?php checked( in_array( $sig, $cf_for_checkboxes, true ), true ); ?> />
								<code><?php echo esc_html( $sig ); ?></code>
							</label>
						<?php endforeach; ?>
					</div>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="wsergo_macro_extra_signals"><?php esc_html_e( 'Дополнительные ключи признаков (wide)', 'worldstat-ergonomics' ); ?></label>
							</th>
							<td>
								<textarea id="wsergo_macro_extra_signals" name="<?php echo esc_attr( WSErgo_Settings::OPTION_MACRO_EXTRA_SIGNALS_TEXT ); ?>" rows="6" class="large-text code"><?php echo esc_textarea( $macro_extra_signals_text ); ?></textarea>
								<p class="description">
									<?php esc_html_e( 'По одному латинскому ключу в строке (как после нормализации заголовка столбца wide-CSV). Ключи появляются в списке чекбоксов выше и в формулах осей; до 50 строк. Если имя столбца в файле не совпадает с ключом, сопоставление по-прежнему задаётся в коде: wide_csv_column_to_signal.', 'worldstat-ergonomics' ); ?>
								</p>
							</td>
						</tr>
					</table>
					<hr />
					<h3><?php esc_html_e( 'Режим расчёта по стране и CSV', 'worldstat-ergonomics' ); ?></h3>
					<p class="description">
						<?php esc_html_e( 'Индекс страны строится по страновым рядам из CSV платформы, опорному году и кластеризации k-means.', 'worldstat-ergonomics' ); ?>
					</p>
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Источник индекса', 'worldstat-ergonomics' ); ?></th>
							<td>
								<input type="hidden" name="<?php echo esc_attr( WSErgo_Settings::OPTION_COUNTRY_INDEX_SOURCE ); ?>" value="macro_datasets" />
								<p class="description"><?php esc_html_e( 'Используются только макроданные (CSV в разделе «Данные CSV» платформы).', 'worldstat-ergonomics' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wsergo_macro_reference_year"><?php esc_html_e( 'Опорный год (макро)', 'worldstat-ergonomics' ); ?></label></th>
							<td>
								<input type="number" id="wsergo_macro_reference_year" name="<?php echo esc_attr( WSErgo_Settings::OPTION_MACRO_REFERENCE_YEAR ); ?>" value="<?php echo esc_attr( (string) $macro_year ); ?>" class="small-text" min="1900" max="2100" step="1" />
								<p class="description"><?php esc_html_e( 'Год для выборки значений country_code + year в CSV (если в файле есть столбец года).', 'worldstat-ergonomics' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wsergo_macro_k_clusters"><?php esc_html_e( 'Число кластеров k-means (макро)', 'worldstat-ergonomics' ); ?></label></th>
							<td>
								<input type="number" id="wsergo_macro_k_clusters" name="<?php echo esc_attr( WSErgo_Settings::OPTION_MACRO_K_CLUSTERS ); ?>" value="<?php echo esc_attr( (string) $macro_k ); ?>" class="small-text" min="2" max="12" step="1" />
								<p class="description"><?php esc_html_e( 'Нормализация min–max выполняется внутри кластера стран.', 'worldstat-ergonomics' ); ?></p>
							</td>
						</tr>
					</table>
					<hr />
					<h3><?php esc_html_e( 'Привязка базовых рядов к CSV из базы', 'worldstat-ergonomics' ); ?></h3>
					<p class="description">
						<?php esc_html_e( 'Список из девяти показателей фиксированный: это «слоты» движка макро (население, площадь, плотность, дороги и т.д.). Он не меняется от набора файлов — остальные столбцы ваших wide-CSV попадают в расчёт как признаки (см. формулы осей и k-means). Если для слота нет отдельного long-файла, оставьте «авто» или привяжите файл с long-колонкой value; при только wide-демографии без населения/площади плагин может построить условный треугольник по плотности (см. предупреждение на странице страны).', 'worldstat-ergonomics' ); ?>
					</p>
					<?php if ( empty( $csv_files ) ) : ?>
						<p class="description"><?php esc_html_e( 'Нет записей CSV в базе или таблица не создана — загрузите данные в World Statistics → Данные CSV.', 'worldstat-ergonomics' ); ?></p>
					<?php else : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Показатель', 'worldstat-ergonomics' ); ?></th>
								<th><?php esc_html_e( 'Файл из БД (wsp_csv_datasets)', 'worldstat-ergonomics' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							if ( class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
								foreach ( WSErgo_Country_Macro_Calculator::bindable_standard_metric_keys() as $mk ) :
									$bound = isset( $macro_bindings[ $mk ] ) ? (int) $macro_bindings[ $mk ] : 0;
									$lab   = $metric_labels_ru[ $mk ] ?? $mk;
									?>
							<tr>
								<td>
									<strong><?php echo esc_html( $lab ); ?></strong>
									<br /><span class="description"><?php esc_html_e( 'Техимя в данных:', 'worldstat-ergonomics' ); ?> <code><?php echo esc_html( $mk ); ?></code></span>
								</td>
								<td>
									<select name="<?php echo esc_attr( WSErgo_Settings::OPTION_MACRO_CSV_BINDINGS ); ?>[<?php echo esc_attr( $mk ); ?>]" class="widefat">
										<option value="0"><?php esc_html_e( '— авто по имени файла —', 'worldstat-ergonomics' ); ?></option>
										<?php foreach ( $csv_files as $frow ) : ?>
											<?php
											$kid = (string) ( $frow['dataset_kind'] ?? '' );
											if ( class_exists( 'WorldStat_Uploaded_Csv' ) && ! WorldStat_Uploaded_Csv::is_calculation_source_kind( $kid ) ) {
												continue;
											}
											$fid = (int) ( $frow['id'] ?? 0 );
											$fn  = (string) ( $frow['name'] ?? '' );
											?>
										<option value="<?php echo esc_attr( (string) $fid ); ?>" <?php selected( $bound, $fid ); ?>><?php echo esc_html( '#' . $fid . ' — ' . $fn . ' (' . $kid . ')' ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
									<?php
								endforeach;
							}
							?>
						</tbody>
					</table>
					<?php endif; ?>
				</div>

				<div id="tab-formula" class="wsergo-tab-panel" style="display:none">
					<h2><?php esc_html_e( 'Формула', 'worldstat-ergonomics' ); ?></h2>
					<h3><?php esc_html_e( 'Макро: слагаемые по осям F, Cm, H, A, S, Ct', 'worldstat-ergonomics' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Каждая ось — взвешенная сумма нормализованных признаков (0–1). «Инверсия» подставляет (1 − значение). Пустые строки при сохранении отбрасываются; если для оси не осталось ни одного слагаемого, подставляется встроенная методика. На одну ось можно задать до 50 слагаемых; если не хватает строк — нажмите «Добавить слагаемое».', 'worldstat-ergonomics' ); ?></p>
					<?php
					$macro_opt = WSErgo_Settings::OPTION_MACRO_AXIS_TERMS;
					foreach ( $macro_axis_labels as $ax_key => $ax_lab ) :
						$rows_ax    = isset( $macro_axis_resolved[ $ax_key ] ) ? $macro_axis_resolved[ $ax_key ] : [];
						$filled     = is_array( $rows_ax ) ? count( $rows_ax ) : 0;
						$term_slots = min( 35, max( 12, $filled + 5 ) );
						?>
						<h4><?php echo esc_html( $ax_lab ); ?></h4>
						<p><button type="button" class="button wsergo-add-macro-term-row" data-axis="<?php echo esc_attr( $ax_key ); ?>"><?php esc_html_e( 'Добавить слагаемое', 'worldstat-ergonomics' ); ?></button></p>
						<table class="widefat striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Признак', 'worldstat-ergonomics' ); ?></th>
									<th><?php esc_html_e( 'Вес', 'worldstat-ergonomics' ); ?></th>
									<th><?php esc_html_e( 'Инверсия 1−x', 'worldstat-ergonomics' ); ?></th>
								</tr>
							</thead>
							<tbody class="wsergo-macro-axis-tbody" data-axis="<?php echo esc_attr( $ax_key ); ?>">
								<tr class="wsergo-macro-term-template" style="display:none;" aria-hidden="true">
									<td>
										<select name="<?php echo esc_attr( $macro_opt ); ?>[<?php echo esc_attr( $ax_key ); ?>][999999][signal]" class="widefat" disabled>
											<option value=""><?php esc_html_e( '—', 'worldstat-ergonomics' ); ?></option>
											<?php foreach ( $signals_ui as $sig ) : ?>
												<option value="<?php echo esc_attr( $sig ); ?>"><?php echo esc_html( $sig ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
									<td><input type="text" class="small-text" name="<?php echo esc_attr( $macro_opt ); ?>[<?php echo esc_attr( $ax_key ); ?>][999999][weight]" value="" disabled /></td>
									<td><label><input type="checkbox" name="<?php echo esc_attr( $macro_opt ); ?>[<?php echo esc_attr( $ax_key ); ?>][999999][invert]" value="1" disabled /></label></td>
								</tr>
								<?php
								for ( $ri = 0; $ri < $term_slots; $ri++ ) :
									$rowt = isset( $rows_ax[ $ri ] ) ? $rows_ax[ $ri ] : [ 'signal' => '', 'weight' => '', 'invert' => false ];
									?>
								<tr>
									<td>
										<select name="<?php echo esc_attr( $macro_opt ); ?>[<?php echo esc_attr( $ax_key ); ?>][<?php echo (int) $ri; ?>][signal]" class="widefat">
											<option value=""><?php esc_html_e( '—', 'worldstat-ergonomics' ); ?></option>
											<?php foreach ( $signals_ui as $sig ) : ?>
												<option value="<?php echo esc_attr( $sig ); ?>" <?php selected( (string) ( $rowt['signal'] ?? '' ), $sig ); ?>><?php echo esc_html( $sig ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
									<td><input type="text" class="small-text" name="<?php echo esc_attr( $macro_opt ); ?>[<?php echo esc_attr( $ax_key ); ?>][<?php echo (int) $ri; ?>][weight]" value="<?php echo esc_attr( isset( $rowt['weight'] ) ? (string) $rowt['weight'] : '' ); ?>" /></td>
									<td><label><input type="checkbox" name="<?php echo esc_attr( $macro_opt ); ?>[<?php echo esc_attr( $ax_key ); ?>][<?php echo (int) $ri; ?>][invert]" value="1" <?php checked( ! empty( $rowt['invert'] ) ); ?> /></label></td>
								</tr>
								<?php endfor; ?>
							</tbody>
						</table>
					<?php endforeach; ?>
					<hr />
					<h3><?php esc_html_e( 'Веса шести макро-осей в страновом E (из CSV)', 'worldstat-ergonomics' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Только для индекса страны по макроданным (оси F, Cm, H, A, S, Ct). Веса на вкладке «Измерения» задают другой расчёт — сводный E по шести измерениям среды объекта (здание, квартал и т.д.).', 'worldstat-ergonomics' ); ?></p>
					<table class="form-table">
						<?php
						$axis_labels = [ 'F' => 'F', 'Cm' => 'Cm', 'H' => 'H', 'A' => 'A', 'S' => 'S', 'Ct' => 'Ct' ];
						foreach ( $axis_labels as $ax => $short ) :
							?>
						<tr>
							<th scope="row"><label for="wsergo_macro_e_<?php echo esc_attr( $ax ); ?>"><?php echo esc_html( $short ); ?></label></th>
							<td>
								<input type="text" class="small-text" id="wsergo_macro_e_<?php echo esc_attr( $ax ); ?>" name="<?php echo esc_attr( WSErgo_Settings::OPTION_MACRO_E_AXIS_WEIGHTS ); ?>[<?php echo esc_attr( $ax ); ?>]" value="<?php echo esc_attr( (string) ( $macro_e_w[ $ax ] ?? '' ) ); ?>" inputmode="decimal" />
							</td>
						</tr>
						<?php endforeach; ?>
					</table>
					<hr />
					<h3><?php esc_html_e( 'Активная модель расчёта E', 'worldstat-ergonomics' ); ?></h3>
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Модель', 'worldstat-ergonomics' ); ?></th>
							<td>
								<select name="<?php echo esc_attr( WSErgo_Settings::OPTION_ACTIVE_MODEL ); ?>">
									<?php foreach ( $models as $m ) : ?>
										<option value="<?php echo esc_attr( $m['id'] ); ?>" <?php selected( $active_id, $m['id'] ); ?>><?php echo esc_html( $m['name'] ); ?></option>
									<?php endforeach; ?>
								</select>
								<?php if ( $active_model ) : ?>
									<p class="description"><?php echo esc_html( $active_model['leaf_formula'] !== '' ? $active_model['leaf_formula'] : __( 'Пустая формула: взвешенное среднее по шести осям (вкладка «Измерения»).', 'worldstat-ergonomics' ) ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					</table>
					<hr />
					<h3><?php esc_html_e( 'Коэффициенты k_* (DSL)', 'worldstat-ergonomics' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Глобальные множители в пользовательской формуле сводного E по объекту (k_default и др.). Это не веса осей и не веса измерений — отдельная шкала для DSL.', 'worldstat-ergonomics' ); ?></p>
					<table class="form-table">
						<?php foreach ( $coeffs as $k => $v ) : ?>
						<tr>
							<th scope="row"><label><?php echo esc_html( $k ); ?></label></th>
							<td><input type="text" name="wsergo_coefficients[<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( (string) $v ); ?>" class="small-text" /></td>
						</tr>
						<?php endforeach; ?>
					</table>
					<hr />
					<h3><?php esc_html_e( 'Модели и DSL', 'worldstat-ergonomics' ); ?></h3>
					<p class="description">
						<?php esc_html_e( 'Простой режим: оставьте формулу пустой — сводный E считается как взвешенное среднее по шести осям. Режим «своя формула» (DSL) позволяет умножать оси на коэффициенты k_*, использовать нормализованные показатели i_* и веса w_*.', 'worldstat-ergonomics' ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'Переменные: F, S, C, L, O, M — баллы осей 0–100; w_* — веса из вкладки «Измерения»; i_* — нормализованные показатели; k_* — глобальные множители (таблица «Коэффициенты k_*» выше на этой вкладке).', 'worldstat-ergonomics' ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'Допустимые идентификаторы:', 'worldstat-ergonomics' ); ?>
						<code><?php echo esc_html( $allowed_txt ); ?></code>
					</p>
					<p>
						<label for="wsergo_test_formula_inline"><?php esc_html_e( 'Проверка формулы (тестовые 50 по всем осям)', 'worldstat-ergonomics' ); ?></label><br />
						<textarea id="wsergo_test_formula_inline" class="large-text" rows="2" placeholder="(F*w_functionality + S*w_safety + ...) * k_default"></textarea><br />
						<button type="button" class="button" id="wsergo-test-formula-btn"><?php esc_html_e( 'Проверить', 'worldstat-ergonomics' ); ?></button>
						<span id="wsergo-formula-test-result" class="description"></span>
					</p>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'ID', 'worldstat-ergonomics' ); ?></th>
								<th><?php esc_html_e( 'Название', 'worldstat-ergonomics' ); ?></th>
								<th><?php esc_html_e( 'Формула сводного E (пусто = классика)', 'worldstat-ergonomics' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ( array_values( $models ) as $i => $m ) :
								?>
							<tr>
								<td><input type="text" name="wsergo_models[<?php echo esc_attr( (string) $i ); ?>][id]" value="<?php echo esc_attr( $m['id'] ); ?>" class="regular-text" /></td>
								<td><input type="text" name="wsergo_models[<?php echo esc_attr( (string) $i ); ?>][name]" value="<?php echo esc_attr( $m['name'] ); ?>" class="regular-text" /></td>
								<td><textarea name="wsergo_models[<?php echo esc_attr( (string) $i ); ?>][leaf_formula]" class="large-text" rows="2"><?php echo esc_textarea( $m['leaf_formula'] ); ?></textarea></td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p class="description"><?php esc_html_e( 'Чтобы добавить модель, сохраните страницу и отредактируйте массив в БД или добавьте строку через фильтр wsergo_default_models.', 'worldstat-ergonomics' ); ?></p>
				</div>

				<?php submit_button(); ?>
			</form>

			</div><!-- #wsergo-panel-country -->

			<div id="wsergo-panel-city" class="wsergo-scope-panel" style="display:none;">
				<div class="notice notice-info inline" style="margin:12px 0;padding:12px;">
					<p style="margin:.4em 0;"><strong><?php esc_html_e( 'Эргономичность города', 'worldstat-ergonomics' ); ?></strong></p>
					<p style="margin:.4em 0;"><?php esc_html_e( 'Заглушка: отдельный модуль настроек для города будет добавлен позже.', 'worldstat-ergonomics' ); ?></p>
				</div>
			</div>

			<div id="wsergo-panel-territory" class="wsergo-scope-panel" style="display:none;">
				<div class="notice notice-info inline" style="margin:12px 0;padding:12px;">
					<p style="margin:.4em 0;"><strong><?php esc_html_e( 'Эргономичность территории', 'worldstat-ergonomics' ); ?></strong></p>
					<p style="margin:.4em 0;"><?php esc_html_e( 'Заглушка: настройки для территории будут добавлены отдельно.', 'worldstat-ergonomics' ); ?></p>
				</div>
			</div>

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
			return __( 'Пустой файл.', 'worldstat-ergonomics' );
		}
		$header = array_map( 'trim', array_map( 'strtolower', $header ) );
		$map    = array_flip( $header );

		$need = [ 'title', 'city_id' ];
		foreach ( $need as $k ) {
			if ( ! isset( $map[ $k ] ) ) {
				fclose( $fh );
				return __( 'Нет обязательного столбца: title или city_id.', 'worldstat-ergonomics' );
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
}
