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
			__( 'Эргономика', 'worldstat-ergonomics' ),
			__( 'Эргономика', 'worldstat-ergonomics' ),
			'manage_options',
			'wsergo-settings',
			[ $this, 'render_settings_page' ]
		);
		add_submenu_page(
			'worldstat',
			__( 'Эргономика стран', 'worldstat-ergonomics' ),
			__( 'Эргономика стран', 'worldstat-ergonomics' ),
			'manage_options',
			'wsergo-countries',
			[ $this, 'render_countries_page' ]
		);
	}

	public function register_settings(): void {
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
					$('.wsergo-tab-nav a').on('click', function(e){
						e.preventDefault();
						var id = $(this).data('tab');
						$('.wsergo-tab-nav a').removeClass('nav-tab-active');
						$(this).addClass('nav-tab-active');
						$('.wsergo-tab-panel').hide();
						$('#'+id).show();
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
					$('#wsergo-add-city-map-row').on('click', function(){
						var \$tpl = $('#wsergo-city-map-rows tr.wsergo-city-map-template');
						if(!\$tpl.length){ return; }
						var \$n = \$tpl.clone().removeClass('wsergo-city-map-template').show();
						\$n.find('select').prop('selectedIndex', 0);
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

	public function render_countries_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$mode = WSErgo_Settings::get_country_variation();
		settings_errors();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Режимы расчета эргономики', 'worldstat-ergonomics' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Переключайте режим плагина: городской (как в начальной версии) или страновой (новая вариация).', 'worldstat-ergonomics' ); ?>
			</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'wsergo_settings' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="wsergo_country_variation"><?php esc_html_e( 'Режим расчета', 'worldstat-ergonomics' ); ?></label></th>
						<td>
							<select id="wsergo_country_variation" name="<?php echo esc_attr( WSErgo_Settings::OPTION_COUNTRY_VARIATION ); ?>">
								<option value="city_direct" <?php selected( $mode, 'city_direct' ); ?>><?php esc_html_e( '1) Городской (как в начальной версии)', 'worldstat-ergonomics' ); ?></option>
								<option value="regions" <?php selected( $mode, 'regions' ); ?>><?php esc_html_e( '2) Страновой (через регионы)', 'worldstat-ergonomics' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Режим 1 сохраняет старую городскую логику. Режим 2 добавляет страновую агрегацию через регионы; для него используются настройки «Города → регион» и «Регионы → страна».', 'worldstat-ergonomics' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
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
		$active_model = WSErgo_Settings::get_active_model();
		$allowed_txt  = implode( ', ', array_keys( WSErgo_Settings::get_leaf_formula_allowed_ids() ) );
		$indicator_defs = get_option( WSErgo_Indicators::OPTION_DEFINITIONS, [] );
		if ( ! is_array( $indicator_defs ) ) {
			$indicator_defs = [];
		}
		$indicator_defs = array_values( $indicator_defs );
		$city_field_map   = get_option( WSErgo_Settings::OPTION_CITY_FIELD_MAP, [] );
		if ( ! is_array( $city_field_map ) ) {
			$city_field_map = [];
		}
		$city_field_map   = array_values( $city_field_map );
		$source_choices   = class_exists( 'WSErgo_City_Bridge' ) ? WSErgo_City_Bridge::get_source_choices() : [];
		$calc_mode        = WSErgo_Settings::get_country_variation();
		$calc_mode_label  = 'regions' === $calc_mode
			? __( '2) Страновой (через регионы)', 'worldstat-ergonomics' )
			: __( '1) Городской (как в начальной версии)', 'worldstat-ergonomics' );

		settings_errors();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Эргономика: настройки', 'worldstat-ergonomics' ); ?></h1>

			<div class="notice notice-info inline" style="margin:12px 0;padding:12px;">
				<p style="margin:.4em 0;"><strong><?php esc_html_e( 'Как это работает', 'worldstat-ergonomics' ); ?></strong></p>
				<p style="margin:.4em 0;">
					<strong><?php esc_html_e( 'Текущий режим:', 'worldstat-ergonomics' ); ?></strong>
					<?php echo esc_html( $calc_mode_label ); ?>
					— <a href="<?php echo esc_url( admin_url( 'admin.php?page=wsergo-countries' ) ); ?>"><?php esc_html_e( 'переключить режим', 'worldstat-ergonomics' ); ?></a>
				</p>
				<ol style="margin:.5em 0 .5em 1.2em;list-style:decimal;padding-left:1em;">
					<li><?php esc_html_e( 'Измерения — доли влияния шести осей на сводный балл E (после сохранения сумма весов приводится к 1).', 'worldstat-ergonomics' ); ?></li>
					<li><?php esc_html_e( 'Показатели — шкалы для сырых величин (шум, ширина дорог…); на объекте вводятся числа в единицах, плагин переводит их в 0–100 по min/max.', 'worldstat-ergonomics' ); ?></li>
					<li><?php esc_html_e( 'Данные города — при отсутствии кварталов можно подставить значения из записи города (импорт Cities) в те же показатели.', 'worldstat-ergonomics' ); ?></li>
					<li><?php esc_html_e( 'Формула E — по умолчанию не нужна: используется взвешенное среднее по шести осям. Своя формула (DSL) — по желанию для экспертов.', 'worldstat-ergonomics' ); ?></li>
					<li><?php esc_html_e( 'Индекс города: если есть кварталы с оценкой — считается по ним; иначе — по данным города и этой карте сопоставления.', 'worldstat-ergonomics' ); ?></li>
				</ol>
			</div>

			<h2 class="nav-tab-wrapper wsergo-tab-nav">
				<a href="#tab-overview" class="nav-tab nav-tab-active" data-tab="tab-overview"><?php esc_html_e( 'Обзор', 'worldstat-ergonomics' ); ?></a>
				<a href="#tab-weights" class="nav-tab" data-tab="tab-weights"><?php esc_html_e( 'Измерения', 'worldstat-ergonomics' ); ?></a>
				<a href="#tab-indicators" class="nav-tab" data-tab="tab-indicators"><?php esc_html_e( 'Показатели', 'worldstat-ergonomics' ); ?></a>
				<a href="#tab-city-import" class="nav-tab" data-tab="tab-city-import"><?php esc_html_e( 'Данные города', 'worldstat-ergonomics' ); ?></a>
				<a href="#tab-models" class="nav-tab" data-tab="tab-models"><?php esc_html_e( 'Формула E', 'worldstat-ergonomics' ); ?></a>
				<a href="#tab-coeff" class="nav-tab" data-tab="tab-coeff"><?php esc_html_e( 'Коэффициенты', 'worldstat-ergonomics' ); ?></a>
				<a href="#tab-agg" class="nav-tab" data-tab="tab-agg"><?php esc_html_e( 'Агрегация', 'worldstat-ergonomics' ); ?></a>
			</h2>

			<form method="post" action="options.php">
				<?php settings_fields( 'wsergo_settings' ); ?>

				<div id="tab-overview" class="wsergo-tab-panel">
					<p class="description">
						<?php esc_html_e( 'Иерархия объектов: помещение → здание → квартал → связь с городом → регион (поле города в Cities) → страна.', 'worldstat-ergonomics' ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'Активная «модель» с пустой формулой — это не отдельный режим из одного пункта: просто классическое взвешенное среднее по шести осям (веса — вкладка «Измерения»), учитываются только оси с баллом > 0.', 'worldstat-ergonomics' ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'Если в городе появятся кварталы с оценкой, индекс города на карте и в отчётах считается по кварталам. Если кварталов нет — включите расчёт по данным импорта на странице «Города» (меню World Statistics) и задайте сопоставление на вкладке «Данные города».', 'worldstat-ergonomics' ); ?>
					</p>
					<table class="form-table">
						<tr>
							<th scope="row"><label for="wsergo_methodology_version"><?php esc_html_e( 'Версия методики', 'worldstat-ergonomics' ); ?></label></th>
							<td>
								<input name="wsergo_methodology_version" id="wsergo_methodology_version" type="text" value="<?php echo esc_attr( get_option( 'wsergo_methodology_version', '1.2' ) ); ?>" class="regular-text" />
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Активная модель расчёта E', 'worldstat-ergonomics' ); ?></th>
							<td>
								<select name="<?php echo esc_attr( WSErgo_Settings::OPTION_ACTIVE_MODEL ); ?>">
									<?php foreach ( $models as $m ) : ?>
										<option value="<?php echo esc_attr( $m['id'] ); ?>" <?php selected( $active_id, $m['id'] ); ?>><?php echo esc_html( $m['name'] ); ?></option>
									<?php endforeach; ?>
								</select>
								<?php if ( $active_model ) : ?>
									<p class="description"><?php echo esc_html( $active_model['leaf_formula'] !== '' ? $active_model['leaf_formula'] : __( 'Пустая формула: взвешенное среднее по шести осям (вкладка «Измерения»). Это режим по умолчанию.', 'worldstat-ergonomics' ) ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					</table>
				</div>

				<div id="tab-weights" class="wsergo-tab-panel" style="display:none">
					<p class="description"><?php esc_html_e( 'Здесь задаётся относительная важность каждой из шести осей (функциональность, безопасность, …) для сводного индекса E. Числа можно вводить в любых пропорциях (например 3 и 1), после сохранения они приводятся к сумме 1. Те же величины доступны в DSL как w_functionality, w_safety и т.д.', 'worldstat-ergonomics' ); ?></p>
					<p class="description"><?php esc_html_e( 'Ось с нулевым весом в формуле не участвует; оси без введённого балла (0) в классическом расчёте тоже не входят в знаменатель.', 'worldstat-ergonomics' ); ?></p>
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
				</div>

				<div id="tab-indicators" class="wsergo-tab-panel" style="display:none">
					<p class="description">
						<?php esc_html_e( 'Шаг 1. Задайте показатель: латинский ID (например street_noise), название, уровень из шести, единицы, минимум и максимум «хорошего» диапазона, направление (чем больше тем лучше или наоборот), относительный вес между показателями одного уровня.', 'worldstat-ergonomics' ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'Шаг 2. На карточке квартала/здания/помещения введите сырое число — плагин переведёт его в балл 0–100 и усреднит с другими показателями этого уровня по весам.', 'worldstat-ergonomics' ); ?>
					</p>
					<p class="description">
						<strong><?php esc_html_e( 'Пример:', 'worldstat-ergonomics' ); ?></strong>
						<?php esc_html_e( 'ID noise_db, уровень «Безопасность», ед. дБ, мин 30 макс 70, «лучше меньше», вес 1 — тогда 40 дБ даст высокий балл, 65 дБ — низкий.', 'worldstat-ergonomics' ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'Веса внутри одного уровня после сохранения нормализуются к сумме 1. Пустой ID строки отбрасывается.', 'worldstat-ergonomics' ); ?>
					</p>
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

				<div id="tab-city-import" class="wsergo-tab-panel" style="display:none">
					<p class="description">
						<?php esc_html_e( 'Когда кварталов с оценкой ещё нет, для каждого города (wsp_city) можно подставить значения из импорта WorldStat Cities в те же показатели, что на вкладке «Показатели». Включите общую опцию на странице «Города» (меню World Statistics) и задайте строки сопоставления ниже.', 'worldstat-ergonomics' ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'После изменения сопоставления снова сохраните блок эргономики на странице «Города» (пересчёт для всех городов) либо пересохраните отдельные города — при включённой опции индекс обновится автоматически.', 'worldstat-ergonomics' ); ?>
					</p>
					<?php if ( empty( $source_choices ) ) : ?>
						<p class="description"><?php esc_html_e( 'Справочник полей города недоступен (плагин Cities не активен).', 'worldstat-ergonomics' ); ?></p>
					<?php else : ?>
					<p>
						<button type="button" class="button" id="wsergo-add-city-map-row"><?php esc_html_e( 'Добавить сопоставление', 'worldstat-ergonomics' ); ?></button>
					</p>
					<table class="widefat striped" id="wsergo-city-map-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Поле данных города', 'worldstat-ergonomics' ); ?></th>
								<th><?php esc_html_e( 'ID показателя эргономики', 'worldstat-ergonomics' ); ?></th>
							</tr>
						</thead>
						<tbody id="wsergo-city-map-rows">
							<?php
							$cm_rows = $city_field_map;
							if ( empty( $cm_rows ) ) {
								$cm_rows[] = [ 'source' => '', 'indicator_id' => '' ];
							}
							foreach ( array_values( $cm_rows ) as $cidx => $crow ) :
								?>
							<tr>
								<td>
									<select name="wsergo_city_field_map[<?php echo esc_attr( (string) $cidx ); ?>][source]" class="widefat">
										<option value=""><?php esc_html_e( '— выберите —', 'worldstat-ergonomics' ); ?></option>
										<?php foreach ( $source_choices as $sval => $slab ) : ?>
											<option value="<?php echo esc_attr( $sval ); ?>" <?php selected( isset( $crow['source'] ) ? (string) $crow['source'] : '', $sval ); ?>><?php echo esc_html( $slab ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
								<td>
									<select name="wsergo_city_field_map[<?php echo esc_attr( (string) $cidx ); ?>][indicator_id]" class="widefat">
										<option value=""><?php esc_html_e( '— выберите —', 'worldstat-ergonomics' ); ?></option>
										<?php foreach ( WSErgo_Indicators::get_definitions() as $idef ) : ?>
											<option value="<?php echo esc_attr( $idef['id'] ); ?>" <?php selected( isset( $crow['indicator_id'] ) ? (string) $crow['indicator_id'] : '', $idef['id'] ); ?>><?php echo esc_html( $idef['label'] . ' (' . $idef['id'] . ')' ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<?php endforeach; ?>
							<tr class="wsergo-city-map-template" style="display:none">
								<td>
									<select name="wsergo_city_field_map[][source]" class="widefat">
										<option value=""><?php esc_html_e( '— выберите —', 'worldstat-ergonomics' ); ?></option>
										<?php foreach ( $source_choices as $sval => $slab ) : ?>
											<option value="<?php echo esc_attr( $sval ); ?>"><?php echo esc_html( $slab ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
								<td>
									<select name="wsergo_city_field_map[][indicator_id]" class="widefat">
										<option value=""><?php esc_html_e( '— выберите —', 'worldstat-ergonomics' ); ?></option>
										<?php foreach ( WSErgo_Indicators::get_definitions() as $idef ) : ?>
											<option value="<?php echo esc_attr( $idef['id'] ); ?>"><?php echo esc_html( $idef['label'] . ' (' . $idef['id'] . ')' ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
						</tbody>
					</table>
					<?php endif; ?>
				</div>

				<div id="tab-models" class="wsergo-tab-panel" style="display:none">
					<p class="description">
						<?php esc_html_e( 'Простой режим: оставьте формулу пустой — сводный E считается как взвешенное среднее по шести осям. Режим «своя формула» (DSL) позволяет умножать оси на коэффициенты k_*, использовать нормализованные показатели i_* и веса w_*.', 'worldstat-ergonomics' ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'Переменные: F, S, C, L, O, M — баллы осей 0–100; w_* — веса из вкладки «Измерения»; i_* — нормализованные показатели; k_* — глобальные множители (вкладка «Коэффициенты»).', 'worldstat-ergonomics' ); ?>
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
								<th><?php esc_html_e( 'Формула сводного E для листа (пусто = классика)', 'worldstat-ergonomics' ); ?></th>
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

				<div id="tab-coeff" class="wsergo-tab-panel" style="display:none">
					<p class="description"><?php esc_html_e( 'Ключи k_* — имена множителей в формуле DSL (например k_default, k_climate). Значение подставляется в выражение как переменная. На отдельной записи квартала/здания коэффициенты можно переопределить JSON в метабоксе «Переопределение коэффициентов».', 'worldstat-ergonomics' ); ?></p>
					<table class="form-table">
						<?php foreach ( $coeffs as $k => $v ) : ?>
						<tr>
							<th scope="row"><label><?php echo esc_html( $k ); ?></label></th>
							<td><input type="text" name="wsergo_coefficients[<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( (string) $v ); ?>" class="small-text" /></td>
						</tr>
						<?php endforeach; ?>
					</table>
					<p class="description"><?php esc_html_e( 'Новые ключи можно добавить вручную в опции или расширить форму кодом; имя должно начинаться с k_.', 'worldstat-ergonomics' ); ?></p>
				</div>

				<div id="tab-agg" class="wsergo-tab-panel" style="display:none">
					<p class="description"><?php esc_html_e( 'Правила объединения дочерних объектов при расчёте родителя. Используйте, когда в иерархии несколько помещений, зданий или кварталов.', 'worldstat-ergonomics' ); ?></p>
					<table class="form-table">
						<tr>
							<th><?php esc_html_e( 'Помещения → здание', 'worldstat-ergonomics' ); ?></th>
							<td>
								<select name="wsergo_aggregation[room_to_building]">
									<option value="mean" <?php selected( $agg['room_to_building'], 'mean' ); ?>><?php esc_html_e( 'Среднее', 'worldstat-ergonomics' ); ?></option>
									<option value="weighted_area" <?php selected( $agg['room_to_building'], 'weighted_area' ); ?>><?php esc_html_e( 'По площади', 'worldstat-ergonomics' ); ?></option>
									<option value="median" <?php selected( $agg['room_to_building'], 'median' ); ?>><?php esc_html_e( 'Медиана', 'worldstat-ergonomics' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Среднее — равный вес помещений; по площади — крупные помещения сильнее влияют; медиана — устойчиво к выбросам.', 'worldstat-ergonomics' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Вес зданий / придомовых в квартале', 'worldstat-ergonomics' ); ?></th>
							<td>
								<input type="text" name="wsergo_aggregation[w_building_in_district]" value="<?php echo esc_attr( (string) $agg['w_building_in_district'] ); ?>" class="small-text" />
								/
								<input type="text" name="wsergo_aggregation[w_yard_in_district]" value="<?php echo esc_attr( (string) $agg['w_yard_in_district'] ); ?>" class="small-text" />
								<p class="description"><?php esc_html_e( 'Две доли (после сохранения нормализуются к сумме 1), как смешивать средний индекс по зданиям и по придомовым участкам в одном квартале.', 'worldstat-ergonomics' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Кварталы → город', 'worldstat-ergonomics' ); ?></th>
							<td>
								<select name="wsergo_aggregation[district_to_city]">
									<option value="mean" <?php selected( $agg['district_to_city'], 'mean' ); ?>><?php esc_html_e( 'Среднее по кварталам', 'worldstat-ergonomics' ); ?></option>
									<option value="buildings_weighted" <?php selected( $agg['district_to_city'], 'buildings_weighted' ); ?>><?php esc_html_e( 'Взвешено по числу зданий и участков', 'worldstat-ergonomics' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Среднее — все кварталы равны; взвешено — квартал с большим числом зданий/дворов вносит больший вклад в индекс города.', 'worldstat-ergonomics' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Города → регион', 'worldstat-ergonomics' ); ?></th>
							<td>
								<select name="wsergo_aggregation[city_to_region]">
									<option value="pop_weighted" <?php selected( $agg['city_to_region'], 'pop_weighted' ); ?>><?php esc_html_e( 'По населению T3', 'worldstat-ergonomics' ); ?></option>
									<option value="mean" <?php selected( $agg['city_to_region'], 'mean' ); ?>><?php esc_html_e( 'Среднее', 'worldstat-ergonomics' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'По населению — крупные города сильнее влияют на региональный показатель.', 'worldstat-ergonomics' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Регионы → страна (справочно)', 'worldstat-ergonomics' ); ?></th>
							<td>
								<select name="wsergo_aggregation[region_to_country]">
									<option value="pop_weighted" <?php selected( $agg['region_to_country'], 'pop_weighted' ); ?>><?php esc_html_e( 'По населению', 'worldstat-ergonomics' ); ?></option>
									<option value="mean" <?php selected( $agg['region_to_country'], 'mean' ); ?>><?php esc_html_e( 'Среднее по регионам', 'worldstat-ergonomics' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Индекс страны на карте в типичной конфигурации считается по городам; это правило для сценария «регион как промежуточный уровень».', 'worldstat-ergonomics' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<?php submit_button(); ?>
			</form>
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
