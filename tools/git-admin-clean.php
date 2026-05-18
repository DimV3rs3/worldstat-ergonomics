<?php
/**
 * ╨Ь╨╡╤В╨░╨▒╨╛╨║╤Б╤Л, ╨╜╨░╤Б╤В╤А╨╛╨╣╨║╨╕, ╨╕╨╝╨┐╨╛╤А╤В CSV, ╨┐╨╛╨┤╨╝╨╡╨╜╤О World Statistics.
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
			__( '╨н╤А╨│╨╛╨╜╨╛╨╝╨╕╤З╨╜╨╛╤Б╤В╤М', 'worldstat-ergonomics' ),
			__( '╨н╤А╨│╨╛╨╜╨╛╨╝╨╕╤З╨╜╨╛╤Б╤В╤М', 'worldstat-ergonomics' ),
			'manage_options',
			'wsergo-settings',
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * ╨б╤В╨░╤А╤Л╨╣ ╨┐╤Г╨╜╨║╤В ╨╝╨╡╨╜╤О ┬л╨н╤А╨│╨╛╨╜╨╛╨╝╨╕╨║╨░ ╤Б╤В╤А╨░╨╜┬╗ ╤Г╨▒╤А╨░╨╜ тАФ ╨┐╨╡╤А╨╡╨╜╨░╨┐╤А╨░╨▓╨╗╤П╨╡╨╝ ╨╜╨░ ╨▓╨║╨╗╨░╨┤╨║╤Г ╨╜╨░╤Б╤В╤А╨╛╨╡╨║ ╤Б╤В╤А╨░╨╜╤Л.
	 */
	public function redirect_legacy_countries_menu(): void {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ╤В╨╛╨╗╤М╨║╨╛ ╤А╨╡╨┤╨╕╤А╨╡╨║╤В ╨┐╨╛ slug ╤Б╤В╤А╨░╨╜╨╕╤Ж╤Л.
		if ( isset( $_GET['page'] ) && (string) wp_unslash( $_GET['page'] ) === 'wsergo-countries' ) {
			wp_safe_redirect( admin_url( 'admin.php?page=wsergo-settings#tab-data' ) );
			exit;
		}
	}

	public function register_settings(): void {
		if ( isset( $_GET['page'] ) && (string) $_GET['page'] === 'wsergo-settings' && current_user_can( 'manage_options' ) && class_exists( 'WSErgo_Settings' ) ) {
			$rev  = (int) get_option( 'wsp_csv_files_revision', 0 );
			$last = (int) get_transient( 'wsergo_admin_bindings_sync_rev' );
			if ( $last !== $rev ) {
				WSErgo_Settings::sync_macro_csv_bindings_with_storage();
				WSErgo_Settings::sync_city_macro_csv_bindings_with_storage();
				set_transient( 'wsergo_admin_bindings_sync_rev', $rev, DAY_IN_SECONDS );
			}
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
		if ( class_exists( 'WSErgo_City_Bridge' ) ) {
			register_setting(
				'wsergo_settings',
				WSErgo_City_Bridge::get_city_import_option_key(),
				[
					'type'              => 'string',
					'sanitize_callback' => [ $this, 'sanitize_city_import_ergo_flag' ],
					'default'           => '1',
				]
			);
		}
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
			WSErgo_Settings::OPTION_MACRO_REFERENCE_COUNTRY_POST_ID,
			[
				'type'              => 'integer',
				'sanitize_callback' => [ $this, 'sanitize_macro_reference_country_post_id' ],
				'default'           => 0,
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
		register_setting(
			'wsergo_settings',
			WSErgo_Settings::OPTION_CITY_METHODOLOGY_VERSION,
			[
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '1.2',
			]
		);
		register_setting(
			'wsergo_settings',
			WSErgo_Settings::OPTION_CITY_MODELS,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_city_models' ],
				'default'           => [],
			]
		);
		register_setting(
			'wsergo_settings',
			WSErgo_Settings::OPTION_CITY_ACTIVE_MODEL,
			[
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'default_weighted',
			]
		);
		register_setting(
			'wsergo_settings',
			WSErgo_Settings::OPTION_CITY_COEFFICIENTS,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_city_coefficients' ],
				'default'           => [ 'k_default' => 1.0 ],
			]
		);
		register_setting(
			'wsergo_settings',
			WSErgo_Settings::OPTION_CITY_MACRO_CUSTOM_METRICS,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_city_macro_custom_metrics' ],
				'default'           => [],
			]
		);
		register_setting(
			'wsergo_settings',
			WSErgo_Settings::OPTION_CITY_DATA_LABELS_RU,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_city_data_labels_ru' ],
				'default'           => [],
			]
		);
		register_setting(
			'wsergo_settings',
			WSErgo_Settings::OPTION_CITY_MACRO_CRITERIA_MATRIX,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_city_macro_criteria_matrix' ],
				'default'           => [],
			]
		);
		register_setting(
			'wsergo_settings',
			WSErgo_Settings::OPTION_CITY_MACRO_CRITERIA_WEIGHTS,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_city_macro_criteria_weights' ],
				'default'           => [],
			]
		);
		register_setting(
			'wsergo_settings',
			WSErgo_Settings::OPTION_CITY_MACRO_CRITERIA_INVERTS,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_city_macro_criteria_inverts' ],
				'default'           => [],
			]
		);
		register_setting(
			'wsergo_settings',
			WSErgo_Settings::OPTION_CITY_MACRO_CLUSTER_FEATURES,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_city_macro_cluster_features' ],
				'default'           => [],
			]
		);
		register_setting(
			'wsergo_settings',
			WSErgo_Settings::OPTION_CITY_MACRO_EXTRA_SIGNALS_TEXT,
			[
				'type'              => 'string',
				'sanitize_callback' => [ $this, 'sanitize_macro_extra_signals_text' ],
				'default'           => '',
			]
		);
		register_setting(
			'wsergo_settings',
			WSErgo_Settings::OPTION_CITY_MACRO_REFERENCE_YEAR,
			[
				'type'              => 'integer',
				'sanitize_callback' => [ $this, 'sanitize_macro_reference_year' ],
				'default'           => 2022,
			]
		);
		register_setting(
			'wsergo_settings',
			WSErgo_Settings::OPTION_CITY_MACRO_K_CLUSTERS,
			[
				'type'              => 'integer',
				'sanitize_callback' => [ $this, 'sanitize_macro_k_clusters' ],
				'default'           => 6,
			]
		);
		register_setting(
			'wsergo_settings',
			WSErgo_Settings::OPTION_CITY_MACRO_E_AXIS_WEIGHTS,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_city_macro_e_axis_weights' ],
				'default'           => [],
			]
		);
		register_setting(
			'wsergo_settings',
			WSErgo_Settings::OPTION_CITY_MACRO_CSV_BINDINGS,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_macro_csv_bindings' ],
				'default'           => [],
			]
		);
		register_setting(
			'wsergo_settings',
			WSErgo_Settings::OPTION_CITY_INDEX_SOURCE,
			[
				'type'              => 'string',
				'sanitize_callback' => [ $this, 'sanitize_city_index_source' ],
				'default'           => 'macro_datasets',
			]
		);
	}

	/**
	 * @param mixed $input
	 * @return array<string, string>
	 */
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
		$min = class_exists( 'WorldStat_Platform_Years' ) ? max( 1900, WorldStat_Platform_Years::min() ) : 1900;
		$max = class_exists( 'WorldStat_Platform_Years' ) ? max( 2100, WorldStat_Platform_Years::max() ) : 2100;
		return max( $min, min( $max, $y ) );
	}

	/**
	 * @param mixed $input
	 */
	public function sanitize_macro_k_clusters( $input ): int {
		$k = is_numeric( $input ) ? (int) $input : 6;
		return max( 2, min( 12, $k ) );
	}

	/**
	 * ╨н╤В╨░╨╗╨╛╨╜╨╜╨░╤П ╤Б╤В╤А╨░╨╜╨░ (╨╖╨░╨┐╨╕╤Б╤М ╨║╨░╤В╨░╨╗╨╛╨│╨░) ╨┤╨╗╤П ╨┐╤А╨╡╨▓╤М╤О ╨╝╨░╨║╤А╨╛╨┐╤А╨╕╨╖╨╜╨░╨║╨╛╨▓ ╨╜╨░ ╨▓╨║╨╗╨░╨┤╨║╨╡ ┬л╨д╨╛╤А╨╝╤Г╨╗╨░┬╗.
	 *
	 * @param mixed $input
	 */
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
			$stored = get_option( WSErgo_Settings::OPTION_MACRO_E_AXIS_WEIGHTS, [] );
			$input  = is_array( $stored ) ? $stored : [];
		}
		if ( $input === [] ) {
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
		if ( ! class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			return [];
		}
		// ╨Э╨╡╤В ╨║╨╗╤О╤З╨░ ╨▓ POST (╨▓╤Б╨╡ ╤З╨╡╨║╨▒╨╛╨║╤Б╤Л ╤Б╨╜╤П╤В╤Л) тАФ ╨╜╨╡ ╨┐╨╛╨┤╤Б╤В╨░╨▓╨╗╤П╨╡╨╝ ╤Б╤В╨░╤А╤Л╨╡ ╨╖╨╜╨░╤З╨╡╨╜╨╕╤П ╨╕╨╖ ╨С╨Ф.
		if ( null === $input || false === $input || '' === $input ) {
			$input = [];
		} elseif ( ! is_array( $input ) ) {
			$stored = get_option( WSErgo_Settings::OPTION_MACRO_CLUSTER_FEATURES, null );
			$input  = is_array( $stored ) ? $stored : [];
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
		if ( ! class_exists( 'WSErgo_Settings' ) ) {
			return [];
		}
		if ( ! is_array( $input ) ) {
			$stored = get_option( WSErgo_Settings::OPTION_MACRO_AXIS_TERMS, [] );
			$input  = is_array( $stored ) ? $stored : [];
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
	 * @return array<string, array<string, true>>
	 */
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

	/**
	 * ╨г╨┤╨░╨╗╨╡╨╜╨╕╨╡ ╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╤М╤Б╨║╨╛╨│╨╛ ╨┐╨░╤А╨░╨╝╨╡╤В╤А╨░ (╤В╨╛╨╗╤М╨║╨╛ ╤Б╨╗╨░╨│╨╕ ╨╕╨╖ ╨║╨░╨╗╤М╨║╤Г╨╗╤П╤В╨╛╤А╨░, ╨╜╨╡ ╤Б╤В╨╛╨╗╨▒╤Ж╤Л CSV).
	 */
	public function handle_delete_custom_metric(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '╨Э╨╡╨┤╨╛╤Б╤В╨░╤В╨╛╤З╨╜╨╛ ╨┐╤А╨░╨▓.', 'worldstat-ergonomics' ) );
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
			wp_die( esc_html__( '╨Э╨╡╨┤╨╛╤Б╤В╨░╤В╨╛╤З╨╜╨╛ ╨┐╤А╨░╨▓.', 'worldstat-ergonomics' ) );
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
			wp_die( esc_html__( '╨Э╨╡╨┤╨╛╤Б╤В╨░╤В╨╛╤З╨╜╨╛ ╨┐╤А╨░╨▓.', 'worldstat-ergonomics' ) );
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
	 */
	public function sanitize_city_import_ergo_flag( $input ): string {
		$on = ( is_string( $input ) && $input === '1' ) || $input === 1 || $input === true;
		return $on ? '1' : '0';
	}

	/**
	 * @param mixed $input
	 */
	public function sanitize_city_index_source( $input ): string {
		$mode = is_string( $input ) ? sanitize_key( $input ) : '';
		return $mode === 'macro_datasets' ? 'macro_datasets' : 'macro_datasets';
	}

	/**
	 * @param mixed $input
	 * @return array<int, array{id:string,name:string,leaf_formula:string}>
	 */
	public function sanitize_city_models( $input ): array {
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
				$allowed = WSErgo_Settings::get_city_leaf_formula_allowed_ids();
				$check   = WSErgo_Expression::validate( $formula, $allowed );
				if ( ! $check['ok'] ) {
					add_settings_error(
						'wsergo_settings',
						'wsergo_bad_formula_city',
						sprintf(
							__( '╨Ю╤И╨╕╨▒╨║╨░ ╨▓ ╤Д╨╛╤А╨╝╤Г╨╗╨╡ ╨│╨╛╤А╨╛╨┤╤Б╨║╨╛╨╣ ╨╝╨╛╨┤╨╡╨╗╨╕ ┬л%1$s┬╗: %2$s', 'worldstat-ergonomics' ),
							$id,
							$check['error'] ?? ''
						)
					);
					$prev = get_option( WSErgo_Settings::OPTION_CITY_MODELS, $defaults );
					return is_array( $prev ) && ! empty( $prev ) ? $prev : $defaults;
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
	public function sanitize_city_coefficients( $input ): array {
		return $this->sanitize_coefficients( $input );
	}

	/**
	 * @param mixed $input
	 * @return array<string, float>
	 */
	public function sanitize_city_macro_e_axis_weights( $input ): array {
		return $this->sanitize_macro_e_axis_weights( $input );
	}

	/**
	 * @param mixed $input
	 */
	public function sanitize_city_macro_custom_metrics( $input ): array {
		if ( ! is_array( $input ) ) {
			$stored = get_option( WSErgo_Settings::OPTION_CITY_MACRO_CUSTOM_METRICS, [] );
			$input    = is_array( $stored ) ? $stored : [];
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
				'slug'  => $slug,
				'op'    => $op,
				'key_a' => $ka,
				'key_b' => $kb,
				'const' => $c,
			];
			if ( count( $out ) >= 30 ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * @param mixed $input
	 * @return array<string, string>
	 */
	public function sanitize_city_data_labels_ru( $input ): array {
		$stored = get_option( WSErgo_Settings::OPTION_CITY_DATA_LABELS_RU, [] );
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
			foreach ( WSErgo_Settings::macro_signal_allowlist_city() as $ck ) {
				if ( $ck === '' ) {
					continue;
				}
				if ( array_key_exists( $ck, $input ) && trim( (string) ( $input[ $ck ] ?? '' ) ) === '' ) {
					continue;
				}
				if ( ! isset( $out[ $ck ] ) || (string) $out[ $ck ] === '' ) {
					$out[ $ck ] = WSErgo_Settings::default_city_data_label_ru( $ck );
				}
			}
		}
		return $out;
	}

	/**
	 * @param mixed $input
	 * @return array<string, array<string, true>>
	 */
	public function sanitize_city_macro_criteria_matrix( $input ): array {
		if ( ! class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			return [];
		}
		if ( null === $input || false === $input || '' === $input ) {
			$input = [];
		} elseif ( ! is_array( $input ) ) {
			$stored = get_option( WSErgo_Settings::OPTION_CITY_MACRO_CRITERIA_MATRIX, [] );
			$input  = is_array( $stored ) ? $stored : [];
		}
		$allow = array_flip( WSErgo_Settings::macro_signal_allowlist_city() );
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
	public function sanitize_city_macro_criteria_weights( $input ): array {
		if ( ! class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			return [];
		}
		if ( ! is_array( $input ) ) {
			$stored = get_option( WSErgo_Settings::OPTION_CITY_MACRO_CRITERIA_WEIGHTS, [] );
			$input  = is_array( $stored ) ? $stored : [];
		}
		$allow = array_flip( WSErgo_Settings::macro_signal_allowlist_city() );
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
	public function sanitize_city_macro_criteria_inverts( $input ): array {
		if ( ! class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			return [];
		}
		if ( null === $input || false === $input || '' === $input ) {
			$input = [];
		} elseif ( ! is_array( $input ) ) {
			$stored = get_option( WSErgo_Settings::OPTION_CITY_MACRO_CRITERIA_INVERTS, [] );
			$input  = is_array( $stored ) ? $stored : [];
		}
		$allow = array_flip( WSErgo_Settings::macro_signal_allowlist_city() );
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
	 * @param mixed $input
	 * @return list<string>
	 */
	public function sanitize_city_macro_cluster_features( $input ): array {
		if ( ! class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			return [];
		}
		if ( null === $input || false === $input || '' === $input ) {
			$input = [];
		} elseif ( ! is_array( $input ) ) {
			$stored = get_option( WSErgo_Settings::OPTION_CITY_MACRO_CLUSTER_FEATURES, null );
			$input  = is_array( $stored ) ? $stored : [];
		}
		$allow = array_flip( WSErgo_Settings::macro_signal_allowlist_city() );
		$out   = [];
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
							__( '╨Ю╤И╨╕╨▒╨║╨░ ╨▓ ╤Д╨╛╤А╨╝╤Г╨╗╨╡ ╨╝╨╛╨┤╨╡╨╗╨╕ ┬л%1$s┬╗: %2$s', 'worldstat-ergonomics' ),
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
			wp_die( esc_html__( '╨Э╨╡╨┤╨╛╤Б╤В╨░╤В╨╛╤З╨╜╨╛ ╨┐╤А╨░╨▓.', 'worldstat-ergonomics' ) );
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
			wp_send_json_success( [ 'value' => __( '(╨┐╤Г╤Б╤В╨╛ тАФ ╨▒╤Г╨┤╨╡╤В ╨▓╨╖╨▓╨╡╤И╨╡╨╜╨╜╨╛╨╡ ╤Б╤А╨╡╨┤╨╜╨╡╨╡)', 'worldstat-ergonomics' ) ] );
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

	public function ajax_test_city_formula(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '╨Э╨╡╨┤╨╛╤Б╤В╨░╤В╨╛╤З╨╜╨╛ ╨┐╤А╨░╨▓.', 'worldstat-ergonomics' ) );
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
			wp_send_json_success( [ 'value' => __( '(╨┐╤Г╤Б╤В╨╛ тАФ ╨▒╤Г╨┤╨╡╤В ╨▓╨╖╨▓╨╡╤И╨╡╨╜╨╜╨╛╨╡ ╤Б╤А╨╡╨┤╨╜╨╡╨╡)', 'worldstat-ergonomics' ) ] );
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
			wp_send_json_error( [ 'message' => __( '╨Э╨╡╨┤╨╛╤Б╤В╨░╤В╨╛╤З╨╜╨╛ ╨┐╤А╨░╨▓.', 'worldstat-ergonomics' ) ] );
		}
		if ( ! check_ajax_referer( $nonce_action, 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( '╨б╨╡╤Б╤Б╨╕╤П ╤Г╤Б╤В╨░╤А╨╡╨╗╨░. ╨Ю╨▒╨╜╨╛╨▓╨╕╤В╨╡ ╤Б╤В╤А╨░╨╜╨╕╤Ж╤Г ╨╕ ╨╜╨░╨╢╨╝╨╕╤В╨╡ ┬л╨Ф╨╛╨▒╨░╨▓╨╕╤В╤М┬╗ ╤Б╨╜╨╛╨▓╨░.', 'worldstat-ergonomics' ) ] );
		}
		if ( ! class_exists( 'WSErgo_Settings' ) ) {
			wp_send_json_error( [ 'message' => __( '╨Э╨░╤Б╤В╤А╨╛╨╣╨║╨╕ ╨╜╨╡╨┤╨╛╤Б╤В╤Г╨┐╨╜╤Л.', 'worldstat-ergonomics' ) ] );
		}
		$raw  = isset( $_POST['rule'] ) ? wp_unslash( (string) $_POST['rule'] ) : '';
		$rule = $raw !== '' ? json_decode( $raw, true ) : null;
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $rule ) ) {
			wp_send_json_error( [ 'message' => __( '╨Э╨╡╨║╨╛╤А╤А╨╡╨║╤В╨╜╤Л╨╡ ╨┤╨░╨╜╨╜╤Л╨╡ ╨┐╤А╨░╨▓╨╕╨╗╨░.', 'worldstat-ergonomics' ) ] );
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
			wp_send_json_error( [ 'message' => __( '╨г╨║╨░╨╢╨╕╤В╨╡ ╨╕╤В╨╛╨│╨╛╨▓╤Л╨╣ ╨║╨╗╤О╤З.', 'worldstat-ergonomics' ) ] );
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
				wp_send_json_error( [ 'message' => __( '╨в╨░╨║╨╛╨╣ ╨╕╤В╨╛╨│╨╛╨▓╤Л╨╣ ╨║╨╗╤О╤З ╤Г╨╢╨╡ ╤Б╤Г╤Й╨╡╤Б╤В╨▓╤Г╨╡╤В.', 'worldstat-ergonomics' ) ] );
			}
		}
		if ( count( $existing ) >= 30 ) {
			wp_send_json_error( [ 'message' => __( '╨Ф╨╛╤Б╤В╨╕╨│╨╜╤Г╤В ╨╗╨╕╨╝╨╕╤В ╨┐╤А╨░╨▓╨╕╨╗ (30).', 'worldstat-ergonomics' ) ] );
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
					'message' => __( '╨Я╤А╨░╨▓╨╕╨╗╨╛ ╨╜╨╡ ╨┐╤А╨╕╨╜╤П╤В╨╛: ╨┐╤А╨╛╨▓╨╡╤А╤М╤В╨╡ ╨╗╨░╤В╨╕╨╜╤Б╨║╨╕╨╡ ╨║╨╗╤О╤З╨╕ ╤Б╤В╨╛╨╗╨▒╤Ж╨╛╨▓ A/B ╨╕ ╨╛╨┐╨╡╤А╨░╤Ж╨╕╤О.', 'worldstat-ergonomics' ),
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
		$page_slug        = isset( $_GET['page'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['page'] ) ) : '';
		$is_wsergo_settings = ( $page_slug === 'wsergo-settings' ) || ( false !== strpos( $hook, 'wsergo-settings' ) );
		if ( $is_wsergo_settings ) {
			wp_enqueue_style( 'dashicons' );
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script(
				'chart-js',
				'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
				[],
				'4.4.1',
				true
			);
			wp_enqueue_script(
				'wsergo-settings-country',
				WSERGO_URL . 'assets/js/wsergo-settings-country.js',
				[ 'jquery', 'chart-js' ],
				WSERGO_VERSION,
				true
			);
			wp_enqueue_script(
				'wsergo-cluster-tune',
				WSERGO_URL . 'assets/js/wsergo-cluster-tune.js',
				[ 'jquery' ],
				WSERGO_VERSION,
				true
			);
			wp_localize_script(
				'wsergo-settings-country',
				'wsergoAdmin',
				[
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'wsergo_admin' ),
				]
			);
			wp_localize_script(
				'wsergo-cluster-tune',
				'wsergoClusterTune',
				[
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'wsergo_admin' ),
					'i18n'    => [
						'running'    => __( '╨Р╨╜╨░╨╗╨╕╨╖ ╨┤╨░╨╜╨╜╤Л╤Е ╨╕ ╨┐╨╛╨┤╨▒╨╛╤А ╨┐╨░╤А╨░╨╝╨╡╤В╤А╨╛╨▓тАж', 'worldstat-ergonomics' ),
						'done'       => __( '╨Я╨░╤А╨░╨╝╨╡╤В╤А╤Л ╨┐╤А╨╕╨╝╨╡╨╜╨╡╨╜╤Л ╨║ ╤Д╨╛╤А╨╝╨╡. ╨Э╨░╨╢╨╝╨╕╤В╨╡ ┬л╨б╨╛╤Е╤А╨░╨╜╨╕╤В╤М ╨╜╨░╤Б╤В╤А╨╛╨╣╨║╨╕┬╗ ╨▓╨╜╨╕╨╖╤Г ╤Б╤В╤А╨░╨╜╨╕╤Ж╤Л.', 'worldstat-ergonomics' ),
						'saved'      => __( '╨Я╨░╤А╨░╨╝╨╡╤В╤А╤Л ╤Б╨╛╤Е╤А╨░╨╜╨╡╨╜╤Л, ╨║╤Н╤И ╨┐╨╡╤А╨╡╤Б╤З╤С╤В╨░ ╤Б╨▒╤А╨╛╤И╨╡╨╜.', 'worldstat-ergonomics' ),
						'error'      => __( '╨Э╨╡ ╤Г╨┤╨░╨╗╨╛╤Б╤М ╨▓╤Л╨┐╨╛╨╗╨╜╨╕╤В╤М ╨░╨▓╤В╨╛╨┐╨╛╨┤╨▒╨╛╤А.', 'worldstat-ergonomics' ),
						'loadingUi'  => __( '╨Ч╨░╨│╤А╤Г╨╖╨║╨░ ╤Б╨┐╨╕╤Б╨║╨░ ╨┐╤А╨╕╨╖╨╜╨░╨║╨╛╨▓тАж', 'worldstat-ergonomics' ),
						'cv'         => __( '╤А╨░╨╖╨▒╤А╨╛╤Б (CV)', 'worldstat-ergonomics' ),
						'coverage'   => __( '╨┐╨╛╨║╤А╤Л╤В╨╕╨╡', 'worldstat-ergonomics' ),
						'selected'   => __( '╨▓ ╨┐╨╛╨┤╨▒╨╛╤А╨╡', 'worldstat-ergonomics' ),
					],
				]
			);
			$wsergo_cm_opt_key = class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::OPTION_MACRO_CUSTOM_METRICS : 'wsergo_macro_custom_metrics';
			$wsergo_cm_msgs    = [
				'maxRules'      => __( '╨Э╨╡ ╨▒╨╛╨╗╨╡╨╡ 30 ╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╤М╤Б╨║╨╕╤Е ╨┐╨░╤А╨░╨╝╨╡╤В╤А╨╛╨▓.', 'worldstat-ergonomics' ),
				'needSlug'      => __( '╨г╨║╨░╨╢╨╕╤В╨╡ ╨╕╤В╨╛╨│╨╛╨▓╤Л╨╣ ╨║╨╗╤О╤З (╨╗╨░╤В╨╕╨╜╨╕╤Ж╨░, snake_case).', 'worldstat-ergonomics' ),
				'needAB'        => __( '╨Ф╨╗╤П ╤Н╤В╨╛╨╣ ╨╛╨┐╨╡╤А╨░╤Ж╨╕╨╕ ╨╖╨░╨┤╨░╨╣╤В╨╡ ╨┐╨░╤А╨░╨╝╨╡╤В╤А╤Л A ╨╕ B.', 'worldstat-ergonomics' ),
				'needA'         => __( '╨Ч╨░╨┤╨░╨╣╤В╨╡ ╨┐╨░╤А╨░╨╝╨╡╤В╤А A ╨╕ ╨┐╤А╨╕ ╨╜╨╡╨╛╨▒╤Е╨╛╨┤╨╕╨╝╨╛╤Б╤В╨╕ ╤З╨╕╤Б╨╗╨╛.', 'worldstat-ergonomics' ),
				'duplicateSlug' => __( '╨в╨░╨║╨╛╨╣ ╨╕╤В╨╛╨│╨╛╨▓╤Л╨╣ ╨║╨╗╤О╤З ╤Г╨╢╨╡ ╨╡╤Б╤В╤М. ╨Т╤Л╨▒╨╡╤А╨╕╤В╨╡ ╨┤╤А╤Г╨│╨╛╨╡ ╨╕╨╝╤П ╨╕╨╗╨╕ ╤Г╨┤╨░╨╗╨╕╤В╨╡ ╨┐╤А╨░╨▓╨╕╨╗╨╛ ╨▓ ╨╝╨░╤В╤А╨╕╤Ж╨╡.', 'worldstat-ergonomics' ),
				'ajaxFail'      => __( '╨Э╨╡ ╤Г╨┤╨░╨╗╨╛╤Б╤М ╤Б╨╛╤Е╤А╨░╨╜╨╕╤В╤М ╨┐╨░╤А╨░╨╝╨╡╤В╤А. ╨Я╤А╨╛╨▓╨╡╤А╤М╤В╨╡ ╨║╨╛╨╜╤Б╨╛╨╗╤М ╨╕╨╗╨╕ ╨┐╨╛╨┐╤А╨╛╨▒╤Г╨╣╤В╨╡ ╨╡╤Й╤С ╤А╨░╨╖.', 'worldstat-ergonomics' ),
			];
			wp_localize_script(
				'wsergo-settings-country',
				'wsergoCmSettings',
				[
					'optKey'       => $wsergo_cm_opt_key,
					'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
					'maxRules'     => 30,
					'nonceAppend'  => wp_create_nonce( 'wsergo_append_custom_metric' ),
					'ajaxAction'   => 'wsergo_append_custom_metric',
					'messages'     => $wsergo_cm_msgs,
					'city'         => [
						'optKey'      => class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::OPTION_CITY_MACRO_CUSTOM_METRICS : 'wsergo_city_macro_custom_metrics',
						'nonceAppend' => wp_create_nonce( 'wsergo_append_city_custom_metric' ),
						'ajaxAction'  => 'wsergo_append_city_custom_metric',
						'messages'    => $wsergo_cm_msgs,
					],
				]
			);
			wp_add_inline_script(
				'jquery',
				"jQuery(function($){
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
						var \$f = $('form.wsergo-settings-form');
						\$f.find('.wsergo-tab-panel-country').hide();
						\$f.find('#' + id).show();
					}
					function wsergoActivateCityInnerTab(id){
						if(!id){ return; }
						var \$a = $('.wsergo-city-inner-nav a[data-city-tab=\"'+id+'\"]');
						if(!\$a.length){ return; }
						$('.wsergo-city-inner-nav a').removeClass('nav-tab-active');
						\$a.addClass('nav-tab-active');
						var \$f = $('form.wsergo-settings-form');
						\$f.find('.wsergo-tab-panel-city').hide();
						\$f.find('#' + id).show();
					}
					function wsergoActivateTerritoryInnerTab(id){
						if(!id){ id = 'tab-territory-district'; }
						var \$a = $('.wsergo-territory-inner-nav a[data-territory-tab=\"'+id+'\"]');
						if(!\$a.length){ return; }
						$('.wsergo-territory-inner-nav a').removeClass('nav-tab-active');
						\$a.addClass('nav-tab-active');
						$('#wsergo-panel-territory .wsergo-tab-panel-territory').hide();
						$('#wsergo-panel-territory #' + id).show();
					}
					function wsergoApplyHash(){
						var h = window.location.hash || '';
						if(h === '#ergo-territory' || h.indexOf('#tab-territory-') === 0){
							wsergoActivateScope('territory');
							var tid = (h.indexOf('#tab-territory-') === 0) ? h.replace('#','') : 'tab-territory-district';
							wsergoActivateTerritoryInnerTab(tid);
							return;
						}
						if(h === '#ergo-country'){
							wsergoActivateScope('country');
							wsergoActivateInnerTab('tab-data');
							return;
						}
						if(h === '#ergo-city' || h === '#tab-city-data' || h === '#tab-city-formula'){
							wsergoActivateScope('city');
							var cid = (h === '#tab-city-data' || h === '#tab-city-formula') ? h.replace('#','') : 'tab-city-data';
							wsergoActivateCityInnerTab(cid);
							return;
						}
						wsergoActivateScope('country');
						if(h.indexOf('#tab-') === 0 && h.indexOf('#tab-city-') !== 0){
							wsergoActivateInnerTab(h.replace('#',''));
						} else {
							wsergoActivateInnerTab('tab-data');
						}
					}
					$('.wsergo-ergo-scope-nav a[data-wsergo-scope]').on('click', function(e){
						e.preventDefault();
						var scope = $(this).data('wsergo-scope');
						if(scope === 'country'){
							wsergoActivateScope('country');
							wsergoActivateInnerTab('tab-data');
							wsergoReplaceHash('#ergo-country');
						} else if(scope === 'city'){
							wsergoActivateScope('city');
							wsergoActivateCityInnerTab('tab-city-data');
							wsergoReplaceHash('#ergo-city');
						} else if(scope === 'territory'){
							wsergoActivateScope('territory');
							wsergoActivateTerritoryInnerTab('tab-territory-district');
							wsergoReplaceHash('#ergo-territory');
						}
					});
					$('.wsergo-territory-inner-nav a[data-territory-tab]').on('click', function(e){
						e.preventDefault();
						var id = $(this).data('territory-tab');
						wsergoActivateScope('territory');
						wsergoActivateTerritoryInnerTab(id);
						wsergoReplaceHash('#' + id);
					});
					$('.wsergo-country-inner-nav a[data-tab]').on('click', function(e){
						e.preventDefault();
						var id = $(this).data('tab');
						wsergoActivateScope('country');
						wsergoActivateInnerTab(id);
						wsergoReplaceHash('#' + id);
					});
					$('.wsergo-city-inner-nav a[data-city-tab]').on('click', function(e){
						e.preventDefault();
						var id = $(this).data('city-tab');
						wsergoActivateScope('city');
						wsergoActivateCityInnerTab(id);
						wsergoReplaceHash('#' + id);
					});
					wsergoApplyHash();
					$(window).on('hashchange', function(){ wsergoApplyHash(); });
					$(document).on('click', 'a.wsergo-tab-deep-link[href^=\"#tab-\"]', function(e){
						var href = $(this).attr('href') || '';
						if(href.indexOf('#tab-') !== 0){ return; }
						e.preventDefault();
						if(href.indexOf('#tab-territory-') === 0){
							wsergoActivateScope('territory');
							wsergoActivateTerritoryInnerTab(href.substring(1));
							wsergoReplaceHash(href);
							return;
						}
						if(href.indexOf('#tab-city-') === 0){
							wsergoActivateScope('city');
							wsergoActivateCityInnerTab(href.substring(1));
							wsergoReplaceHash(href);
							return;
						}
						wsergoActivateScope('country');
						wsergoActivateInnerTab(href.substring(1));
						wsergoReplaceHash(href);
					});
					$('#wsergo-test-formula-btn').on('click', function(){
						var formula = $('#wsergo_test_formula_inline').length ? $('#wsergo_test_formula_inline').val() : $('textarea[name=\"wsergo_models[0][leaf_formula]\"]').first().val();
						$.post(ajaxurl, { action:'wsergo_test_formula', nonce:'" . esc_js( wp_create_nonce( 'wsergo_settings_ajax' ) ) . "', formula: formula }, function(r){
							if(r.success){ $('#wsergo-formula-test-result').text('E тЙИ ' + r.data.value); } else { $('#wsergo-formula-test-result').text(r.data.message || 'Error'); }
						});
					});
					$('#wsergo-test-formula-btn-city').on('click', function(){
						var formula = $('#wsergo_test_formula_inline_city').length ? $('#wsergo_test_formula_inline_city').val() : $('textarea[name=\"wsergo_city_models[0][leaf_formula]\"]').first().val();
						$.post(ajaxurl, { action:'wsergo_test_city_formula', nonce:'" . esc_js( wp_create_nonce( 'wsergo_settings_ajax' ) ) . "', formula: formula }, function(r){
							if(r.success){ $('#wsergo-formula-test-result-city').text('E тЙИ ' + r.data.value); } else { $('#wsergo-formula-test-result-city').text(r.data.message || 'Error'); }
						});
					});
					function wsergoMacroParseManual(v){
						var s = (v===null||v===undefined)?'':String(v).trim().replace(',','.');
						if(s===''){ return null; }
						var f = parseFloat(s);
						if(!isFinite(f) || f<=0){ return null; }
						return f;
					}
					function wsergoMacroSigmaResolved(checked, manualVals){
						var n = checked.length;
						var EPS = 1e-9;
						var sumM = 0, k;
						for(k in manualVals){ if(manualVals.hasOwnProperty(k)){ sumM += manualVals[k]; } }
						var nMan = 0;
						for(k in manualVals){ if(manualVals.hasOwnProperty(k)){ nMan++; } }
						var nAuto = n - nMan;
						var weights = {}, i, s, rem, eq, each, eachA;
						if(n===0){ return 0; }
						if(nMan===0){
							eq = n>0 ? 1/n : 0;
							for(i=0;i<n;i++){ weights[checked[i]] = eq; }
						} else if(nAuto===0){
							if(sumM <= 1e-15){
								eq = n>0 ? 1/n : 0;
								for(i=0;i<n;i++){ weights[checked[i]] = eq; }
							} else if(sumM > 1.0 + EPS){
								for(i=0;i<n;i++){
									s = checked[i];
									weights[s] = manualVals[s]!==undefined ? manualVals[s]/sumM : 0;
								}
							} else {
								rem = 1.0 - sumM;
								each = nMan>0 ? rem/nMan : 0;
								for(i=0;i<n;i++){
									s = checked[i];
									weights[s] = manualVals[s]!==undefined ? manualVals[s] + each : 0;
								}
							}
						} else if(sumM > 1.0 + EPS){
							for(i=0;i<n;i++){
								s = checked[i];
								weights[s] = manualVals[s]!==undefined ? manualVals[s]/sumM : 0;
							}
						} else {
							rem = Math.max(0, 1.0 - sumM);
							eachA = nAuto>0 ? rem/nAuto : 0;
							for(i=0;i<n;i++){
								s = checked[i];
								weights[s] = manualVals[s]!==undefined ? manualVals[s] : eachA;
							}
						}
						var total = 0;
						for(i=0;i<n;i++){ total += weights[checked[i]]; }
						return total;
					}
					function wsergoMacroRecalcAxis(\$det){
						var \$inp = \$det.find('tbody input.wsergo-macro-w-input');
						if(!\$inp.length){ return; }
						var checked = [], manualVals = {};
						\$inp.each(function(){
							var sig = \$(this).data('macro-signal') || '';
							checked.push(sig);
							var pv = wsergoMacroParseManual(\$(this).val());
							if(pv!==null){ manualVals[sig]=pv; }
						});
						var tot = wsergoMacroSigmaResolved(checked, manualVals);
						var txt = isFinite(tot) ? tot.toFixed(4) : 'тАФ';
						\$det.find('.wsergo-macro-sum-total').text(txt);
					}
					\$('.wsergo-macro-crit').each(function(){ wsergoMacroRecalcAxis(\$(this)); });
					\$(document).on('input', '.wsergo-macro-w-input', function(){
						wsergoMacroRecalcAxis(\$(this).closest('.wsergo-macro-crit'));
					});
				});"
			);
		}
	}

	public function add_meta_boxes(): void {
		add_meta_box(
			'wsergo_district_data',
			__( '╨н╤А╨│╨╛╨╜╨╛╨╝╨╕╨║╨░ ╨║╨▓╨░╤А╤В╨░╨╗╨░', 'worldstat-ergonomics' ),
			[ $this, 'render_district_metabox' ],
			WSErgo_CPT::SLUG_DISTRICT,
			'normal',
			'high'
		);
		add_meta_box(
			'wsergo_building_data',
			__( '╨н╤А╨│╨╛╨╜╨╛╨╝╨╕╨║╨░ ╨╖╨┤╨░╨╜╨╕╤П', 'worldstat-ergonomics' ),
			[ $this, 'render_building_metabox' ],
			WSErgo_CPT::SLUG_BUILDING,
			'normal',
			'high'
		);
		add_meta_box(
			'wsergo_room_data',
			__( '╨н╤А╨│╨╛╨╜╨╛╨╝╨╕╨║╨░ ╨┐╨╛╨╝╨╡╤Й╨╡╨╜╨╕╤П', 'worldstat-ergonomics' ),
			[ $this, 'render_room_metabox' ],
			WSErgo_CPT::SLUG_ROOM,
			'normal',
			'high'
		);
		add_meta_box(
			'wsergo_yard_data',
			__( '╨Я╤А╨╕╨┤╨╛╨╝╨╛╨▓╨░╤П ╤В╨╡╤А╤А╨╕╤В╨╛╤А╨╕╤П', 'worldstat-ergonomics' ),
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
			<label for="wsergo_city_id"><strong><?php esc_html_e( '╨У╨╛╤А╨╛╨┤', 'worldstat-ergonomics' ); ?></strong></label><br />
			<select name="wsergo_city_id" id="wsergo_city_id" class="widefat" required>
				<option value=""><?php esc_html_e( 'тАФ ╨▓╤Л╨▒╨╡╤А╨╕╤В╨╡ тАФ', 'worldstat-ergonomics' ); ?></option>
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
			<label for="wsergo_geojson"><strong><?php esc_html_e( 'GeoJSON ╨┐╨╛╨╗╨╕╨│╨╛╨╜╨░ (╨╛╨┐╤Ж.)', 'worldstat-ergonomics' ); ?></strong></label>
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
			<label for="wsergo_district_id"><strong><?php esc_html_e( '╨Ъ╨▓╨░╤А╤В╨░╨╗', 'worldstat-ergonomics' ); ?></strong></label><br />
			<select name="wsergo_district_id" id="wsergo_district_id" class="widefat" required>
				<option value=""><?php esc_html_e( 'тАФ ╨▓╤Л╨▒╨╡╤А╨╕╤В╨╡ тАФ', 'worldstat-ergonomics' ); ?></option>
				<?php foreach ( $districts as $d ) : ?>
					<option value="<?php echo esc_attr( (string) $d->ID ); ?>" <?php selected( $district_id, $d->ID ); ?>>
						<?php echo esc_html( $d->post_title . ' (ID ' . $d->ID . ')' ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p class="wsergo-row">
			<label><?php esc_html_e( '╨и╨╕╤А╨╛╤В╨░', 'worldstat-ergonomics' ); ?></label>
			<input type="text" name="wsergo_lat" value="<?php echo esc_attr( (string) get_post_meta( $post->ID, WSErgo_CPT::META_LAT, true ) ); ?>" class="widefat" />
		</p>
		<p class="wsergo-row">
			<label><?php esc_html_e( '╨Ф╨╛╨╗╨│╨╛╤В╨░', 'worldstat-ergonomics' ); ?></label>
			<input type="text" name="wsergo_lng" value="<?php echo esc_attr( (string) get_post_meta( $post->ID, WSErgo_CPT::META_LNG, true ) ); ?>" class="widefat" />
		</p>
		<p class="wsergo-row">
			<label><?php esc_html_e( '╨Р╨┤╤А╨╡╤Б', 'worldstat-ergonomics' ); ?></label>
			<input type="text" name="wsergo_address" value="<?php echo esc_attr( (string) get_post_meta( $post->ID, WSErgo_CPT::META_ADDRESS, true ) ); ?>" class="widefat" />
		</p>
		<p class="wsergo-row">
			<label><?php esc_html_e( '╨У╨╛╨┤ ╨┐╨╛╤Б╤В╤А╨╛╨╣╨║╨╕', 'worldstat-ergonomics' ); ?></label>
			<?php
			$wsergo_y_min = class_exists( 'WorldStat_Platform_Years' ) ? WorldStat_Platform_Years::min() : 1990;
			$wsergo_y_max = class_exists( 'WorldStat_Platform_Years' ) ? WorldStat_Platform_Years::max() : (int) gmdate( 'Y' ) + 10;
			?>
			<input type="number" name="wsergo_year" value="<?php echo esc_attr( (string) get_post_meta( $post->ID, WSErgo_CPT::META_YEAR, true ) ); ?>" class="small-text" min="<?php echo esc_attr( (string) $wsergo_y_min ); ?>" max="<?php echo esc_attr( (string) $wsergo_y_max ); ?>" step="1" />
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
			<label for="wsergo_building_id"><strong><?php esc_html_e( '╨Ч╨┤╨░╨╜╨╕╨╡', 'worldstat-ergonomics' ); ?></strong></label><br />
			<select name="wsergo_building_id" id="wsergo_building_id" class="widefat" required>
				<option value=""><?php esc_html_e( 'тАФ ╨▓╤Л╨▒╨╡╤А╨╕╤В╨╡ тАФ', 'worldstat-ergonomics' ); ?></option>
				<?php foreach ( $buildings as $b ) : ?>
					<option value="<?php echo esc_attr( (string) $b->ID ); ?>" <?php selected( $bid, $b->ID ); ?>><?php echo esc_html( $b->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="wsergo_area"><?php esc_html_e( '╨Я╨╗╨╛╤Й╨░╨┤╤М, ╨╝┬▓ (╨┤╨╗╤П ╨▓╨╡╤Б╨░ ╨┐╤А╨╕ ╨░╨│╤А╨╡╨│╨░╤Ж╨╕╨╕ ╨▓ ╨╖╨┤╨░╨╜╨╕╨╡)', 'worldstat-ergonomics' ); ?></label>
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
			<label for="wsergo_yard_district_id"><strong><?php esc_html_e( '╨Ъ╨▓╨░╤А╤В╨░╨╗', 'worldstat-ergonomics' ); ?></strong></label><br />
			<select name="wsergo_yard_district_id" id="wsergo_yard_district_id" class="widefat" required>
				<option value=""><?php esc_html_e( 'тАФ ╨▓╤Л╨▒╨╡╤А╨╕╤В╨╡ тАФ', 'worldstat-ergonomics' ); ?></option>
				<?php foreach ( $districts as $d ) : ?>
					<option value="<?php echo esc_attr( (string) $d->ID ); ?>" <?php selected( $did, $d->ID ); ?>><?php echo esc_html( $d->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p class="wsergo-row">
			<label><?php esc_html_e( '╨и╨╕╤А╨╛╤В╨░', 'worldstat-ergonomics' ); ?></label>
			<input type="text" name="wsergo_yard_lat" value="<?php echo esc_attr( (string) get_post_meta( $post->ID, WSErgo_CPT::META_LAT, true ) ); ?>" class="widefat" />
		</p>
		<p class="wsergo-row">
			<label><?php esc_html_e( '╨Ф╨╛╨╗╨│╨╛╤В╨░', 'worldstat-ergonomics' ); ?></label>
			<input type="text" name="wsergo_yard_lng" value="<?php echo esc_attr( (string) get_post_meta( $post->ID, WSErgo_CPT::META_LNG, true ) ); ?>" class="widefat" />
		</p>
		<p>
			<label for="wsergo_yard_geojson"><?php esc_html_e( 'GeoJSON (╨╛╨┐╤Ж.)', 'worldstat-ergonomics' ); ?></label>
			<textarea name="wsergo_yard_geojson" id="wsergo_yard_geojson" class="widefat" rows="3"><?php echo esc_textarea( (string) get_post_meta( $post->ID, WSErgo_CPT::META_GEOJSON, true ) ); ?></textarea>
		</p>
		<?php
		$this->render_score_fields( $post->ID, 'yard', true );
	}

	/**
	 * ╨Я╨╛╨╗╤П ╤Б╤Л╤А╤Л╤Е ╨┐╨╛╨║╨░╨╖╨░╤В╨╡╨╗╨╡╨╣ (╨╜╨░╤Б╤В╤А╨░╨╕╨▓╨░╤О╤В╤Б╤П ╨▓ ┬л╨н╤А╨│╨╛╨╜╨╛╨╝╨╕╨║╨░ тЖТ ╨Я╨╛╨║╨░╨╖╨░╤В╨╡╨╗╨╕┬╗).
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
				<strong><?php esc_html_e( '╨б╤Л╤А╤Л╨╡ ╨┐╨╛╨║╨░╨╖╨░╤В╨╡╨╗╨╕', 'worldstat-ergonomics' ); ?></strong>
				<?php esc_html_e( ' тАФ ╨▓╨▓╨╛╨┤ ╨▓ ╤Д╨╕╨╖╨╕╤З╨╡╤Б╨║╨╕╤Е ╨╡╨┤╨╕╨╜╨╕╤Ж╨░╤Е; ╨╜╨╛╤А╨╝╨░╨╗╨╕╨╖╨░╤Ж╨╕╤П ╨▓ 0тАУ100 ╨┐╨╛ min/max ╨╕ ╨╜╨░╨┐╤А╨░╨▓╨╗╨╡╨╜╨╕╤О; ╨▓╨╜╤Г╤В╤А╨╕ ╤Г╤А╨╛╨▓╨╜╤П тАФ ╨▓╨╖╨▓╨╡╤И╨╡╨╜╨╜╨╛╨╡ ╤Б╤А╨╡╨┤╨╜╨╡╨╡. ╨Т DSL ╨┤╨╛╤Б╤В╤Г╨┐╨╜╤Л ╨║╨░╨║ i_id (0тАУ100).', 'worldstat-ergonomics' ); ?>
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
			<?php esc_html_e( '╨б╨▓╨╛╨┤╨╜╤Л╨╣ ╨╕╨╜╨┤╨╡╨║╤Б E (0тАУ100): ╨┐╤Г╤Б╤В╨╛╨╡ ╨┐╨╛╨╗╨╡ тАФ ╤А╨░╤Б╤З╤С╤В ╨┐╨╛ ╨░╨║╤В╨╕╨▓╨╜╨╛╨╣ ╨╝╨╛╨┤╨╡╨╗╨╕ (DSL ╨╕╨╗╨╕ ╨▓╨╖╨▓╨╡╤И╨╡╨╜╨╜╨╛╨╡ ╤Б╤А╨╡╨┤╨╜╨╡╨╡) ╨╕ ╨╕╨╡╤А╨░╤А╤Е╨╕╨╕.', 'worldstat-ergonomics' ); ?>
		</p>
		<p>
			<label for="wsergo_index"><strong><?php esc_html_e( '╨б╨▓╨╛╨┤╨╜╤Л╨╣ ╨╕╨╜╨┤╨╡╨║╤Б E', 'worldstat-ergonomics' ); ?></strong></label>
			<input type="text" name="<?php echo esc_attr( WSErgo_CPT::META_INDEX ); ?>" id="wsergo_index" value="<?php echo esc_attr( (string) get_post_meta( $post_id, WSErgo_CPT::META_INDEX, true ) ); ?>" class="widefat" inputmode="decimal" />
		</p>
		<?php if ( $show_lock ) : ?>
		<p>
			<label>
				<input type="checkbox" name="wsergo_index_locked" value="1" <?php checked( get_post_meta( $post_id, WSErgo_CPT::META_INDEX_LOCKED, true ), '1' ); ?> />
				<?php esc_html_e( '╨Ч╨░╤Д╨╕╨║╤Б╨╕╤А╨╛╨▓╨░╤В╤М ╨╕╨╜╨┤╨╡╨║╤Б (╨╜╨╡ ╨┐╨╡╤А╨╡╤Б╤З╨╕╤В╤Л╨▓╨░╤В╤М ╨░╨▓╤В╨╛╨╝╨░╤В╨╕╤З╨╡╤Б╨║╨╕ ╨╕╨╖ ╨┤╨╛╤З╨╡╤А╨╜╨╕╤Е ╨╛╨▒╤К╨╡╨║╤В╨╛╨▓)', 'worldstat-ergonomics' ); ?>
			</label>
		</p>
		<?php endif; ?>
		<?php $this->render_leaf_indicator_inputs( $post_id ); ?>
		<hr />
		<p class="description">
			<?php esc_html_e( '╨и╨╡╤Б╤В╤М ╤Г╤А╨╛╨▓╨╜╨╡╨╣ (0тАУ100): ╨┐╤А╨╕ ╤Б╨╛╤Е╤А╨░╨╜╨╡╨╜╨╕╨╕, ╨╡╤Б╨╗╨╕ ╨┤╨╗╤П ╤Г╤А╨╛╨▓╨╜╤П ╨╖╨░╨┤╨░╨╜╤Л ╤Б╤Л╤А╤Л╨╡ ╨┐╨╛╨║╨░╨╖╨░╤В╨╡╨╗╨╕ ╨╜╨╕╨╢╨╡, ╨▓ ╨╝╨╡╤В╨░ ╨┐╨╛╨┤╤Б╤В╨░╨▓╨╗╤П╨╡╤В╤Б╤П ╤А╨░╤Б╤З╤С╤В╨╜╨╛╨╡ ╨╖╨╜╨░╤З╨╡╨╜╨╕╨╡ ╨┐╨╛ ╨╝╨╡╤В╨╛╨┤╨╕╨║╨╡ ╨╕╨╖ ╨╜╨░╤Б╤В╤А╨╛╨╡╨║ ┬л╨Я╨╛╨║╨░╨╖╨░╤В╨╡╨╗╨╕┬╗.', 'worldstat-ergonomics' ); ?>
		</p>
		<?php
		$labels = WSErgo_Model::get_dimension_labels();
		foreach ( WSErgo_Model::DIMENSION_KEYS as $dim ) {
			$key = WSErgo_Model::meta_key_for_dimension( $dim );
			$val = get_post_meta( $post_id, $key, true );
			?>
			<p>
				<label for="<?php echo esc_attr( $key ); ?>"><strong><?php echo esc_html( $labels[ $dim ] ); ?></strong> <span class="description">(0тАУ100)</span></label>
				<input type="text" name="<?php echo esc_attr( $key ); ?>" id="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( (string) $val ); ?>" class="widefat" inputmode="decimal" />
			</p>
			<?php
		}
		$ind = (string) get_post_meta( $post_id, WSErgo_CPT::META_INDICATORS_JSON, true );
		$coef = (string) get_post_meta( $post_id, WSErgo_CPT::META_COEFF_OVERRIDES, true );
		?>
		<p>
			<label for="wsergo_indicators_json"><strong><?php esc_html_e( '╨Ы╨╕╤Б╤В╨╛╨▓╤Л╨╡ ╨┐╨╛╨║╨░╨╖╨░╤В╨╡╨╗╨╕ (JSON, ╨╛╨┐╤Ж.)', 'worldstat-ergonomics' ); ?></strong></label>
			<textarea name="wsergo_indicators_json" id="wsergo_indicators_json" class="widefat" rows="3" placeholder="{ }"><?php echo esc_textarea( $ind ); ?></textarea>
		</p>
		<p>
			<label for="wsergo_coeff_overrides_json"><strong><?php esc_html_e( '╨Я╨╡╤А╨╡╨╛╨┐╤А╨╡╨┤╨╡╨╗╨╡╨╜╨╕╨╡ ╨║╨╛╤Н╤Д╤Д╨╕╤Ж╨╕╨╡╨╜╤В╨╛╨▓ k_* (JSON)', 'worldstat-ergonomics' ); ?></strong></label>
			<textarea name="wsergo_coeff_overrides_json" id="wsergo_coeff_overrides_json" class="widefat" rows="2" placeholder='{ &quot;k_climate&quot;: 1.05 }'><?php echo esc_textarea( $coef ); ?></textarea>
		</p>
		<?php
	}

	public function add_import_page(): void {
		add_submenu_page(
			'worldstat',
			__( '╨Ш╨╝╨┐╨╛╤А╤В ╨║╨▓╨░╤А╤В╨░╨╗╨╛╨▓ (CSV)', 'worldstat-ergonomics' ),
			__( '╨Ш╨╝╨┐╨╛╤А╤В ╨║╨▓╨░╤А╤В╨░╨╗╨╛╨▓ (CSV)', 'worldstat-ergonomics' ),
			'manage_options',
			'wsergo-import-districts',
			[ $this, 'render_import_page' ]
		);
	}

	/**
	 * ╨в╨╡╨║╤Б╤В ╨┤╨╗╤П ╨║╨╛╨╗╨╛╨╜╨║╨╕ ┬л╨Я╤А╨╕╨╝╨╡╤А ╨┤╨░╨╜╨╜╤Л╤Е┬╗: ╨╖╨╜╨░╤З╨╡╨╜╨╕╨╡ ╨╕╨╖ ╨║╤Н╤И╨░ ╨╝╨░╨║╤А╨╛╤А╤П╨┤╨░ ╨┐╨╛ ╨║╨╗╤О╤З╤Г ╨┐╤А╨╕╨╖╨╜╨░╨║╨░ ╨╕╨╗╨╕ ┬лтАФ┬╗.
	 *
	 * @param array<string, float>|null $raw_row
	 */
	private function format_macro_signal_example_display( ?array $raw_row, string $signal ): string {
		$signal = trim( (string) $signal );
		if ( $signal === '' || ! is_array( $raw_row ) || ! array_key_exists( $signal, $raw_row ) ) {
			return 'тАФ';
		}
		$v = $raw_row[ $signal ];
		if ( ! is_numeric( $v ) ) {
			return 'тАФ';
		}
		$f = (float) $v;
		if ( ! is_finite( $f ) ) {
			return 'тАФ';
		}
		// %.6g ╨┤╨░╨▓╨░╨╗ ╨╜╨░╤Г╤З╨╜╤Г╤О ╨╜╨╛╤В╨░╤Ж╨╕╤О (6.24e+7) ╨┤╨╗╤П ╨║╤А╤Г╨┐╨╜╤Л╤Е ╤Ж╨╡╨╗╤Л╤Е; ╨▓ CSV ╨╛╨╢╨╕╨┤╨░╨╡╤В╤Б╤П ╨┐╨╛╨╗╨╜╨░╤П ╨╖╨░╨┐╨╕╤Б╤М.
		$rn = round( $f );
		if ( abs( $f - $rn ) < 1e-6 * max( 1.0, abs( $rn ) ) ) {
			return (string) (int) $rn;
		}
		$s = number_format( $f, 12, '.', '' );
		$s = rtrim( rtrim( $s, '0' ), '.' );
		return $s === '' || $s === '-.' ? (string) $f : $s;
	}

	/**
	 * ╨Я╨╛╤Б╤В wsp_city ╨┤╨╗╤П ╨┐╤А╨╡╨▓╤М╤О ╤Б╤Л╤А╤Л╤Е ╨╖╨╜╨░╤З╨╡╨╜╨╕╨╣ ╨▓ ╨░╨┤╨╝╨╕╨╜╨║╨╡: ╨┐╨╡╤А╨▓╤Л╨╣ ╨│╨╛╤А╨╛╨┤ ╤Б ISO2 ╤Н╤В╨░╨╗╨╛╨╜╨╜╨╛╨╣ ╤Б╤В╤А╨░╨╜╤Л, ╨╕╨╜╨░╤З╨╡ ╨┐╨╡╤А╨▓╤Л╨╣ ╨╕╨╖ ╤Б╨┐╨╕╤Б╨║╨░.
	 *
	 * @param int            $macro_ref_country_post_id ID ╨╖╨░╨┐╨╕╤Б╨╕ wsp_country.
	 * @param array<int,\WP_Post> $city_posts            ╨Ю╨┐╤Г╨▒╨╗╨╕╨║╨╛╨▓╨░╨╜╨╜╤Л╨╡ ╨│╨╛╤А╨╛╨┤╨░.
	 */
	private static function resolve_city_preview_post_id_for_admin( int $macro_ref_country_post_id, array $city_posts ): int {
		if ( empty( $city_posts ) ) {
			return 0;
		}
		if ( $macro_ref_country_post_id > 0 ) {
			$iso2 = strtoupper( trim( (string) get_post_meta( $macro_ref_country_post_id, 'wsp_iso_alpha2', true ) ) );
			if ( strlen( $iso2 ) === 2 ) {
				foreach ( $city_posts as $cp ) {
					$ciso = strtoupper( trim( (string) get_post_meta( (int) $cp->ID, 'wscity_country_iso2', true ) ) );
					if ( $ciso === $iso2 ) {
						return (int) $cp->ID;
					}
				}
			}
		}
		return (int) $city_posts[0]->ID;
	}

	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$models       = WSErgo_Settings::get_models();
		$active_id    = (string) get_option( WSErgo_Settings::OPTION_ACTIVE_MODEL, 'default_weighted' );
		$coeffs       = WSErgo_Settings::get_coefficients();
		$active_model = WSErgo_Settings::get_active_model();
		$allowed_txt  = implode( ', ', array_keys( WSErgo_Settings::get_leaf_formula_allowed_ids() ) );
		$macro_year       = WSErgo_Settings::get_macro_reference_year();
		$macro_k          = WSErgo_Settings::get_macro_k_clusters();
		$macro_ref_country_id = WSErgo_Settings::get_macro_reference_country_post_id();
		$macro_e_w        = WSErgo_Settings::get_macro_e_axis_weights();
		$country_posts_for_ref = [];
		if ( class_exists( 'WorldStat_Country_CPT' ) ) {
			$country_posts_for_ref = get_posts(
				[
					'post_type'      => WorldStat_Country_CPT::SLUG,
					'post_status'    => 'publish',
					'posts_per_page' => 500,
					'orderby'        => 'title',
					'order'          => 'ASC',
					'no_found_rows'  => true,
				]
			);
		}
		$stored_cf          = WSErgo_Settings::get_macro_cluster_features();
		$default_cf           = class_exists( 'WSErgo_Country_Macro_Calculator' ) ? WSErgo_Country_Macro_Calculator::default_cluster_features() : [];
		$cf_for_checkboxes    = count( $stored_cf ) >= 2 ? $stored_cf : $default_cf;
		$macro_axis_resolved = WSErgo_Settings::get_macro_axis_terms_resolved();
		$signals_ui          = class_exists( 'WSErgo_Country_Macro_Calculator' ) ? WSErgo_Country_Macro_Calculator::macro_signal_allowlist() : [];
		$macro_axis_labels   = class_exists( 'WSErgo_Country_Macro_Calculator' ) ? WSErgo_Country_Macro_Calculator::macro_axis_labels_ru() : [];
		$macro_extra_signals_text = class_exists( 'WSErgo_Settings' ) ? (string) get_option( WSErgo_Settings::OPTION_MACRO_EXTRA_SIGNALS_TEXT, '' ) : '';
		$data_labels_saved     = class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::get_data_labels_ru() : [];
		$data_label_keys       = class_exists( 'WSErgo_Country_Macro_Calculator' ) ? WSErgo_Country_Macro_Calculator::all_data_label_keys() : [];
		$wsp_csv_has_datasets  = class_exists( 'WSErgo_Country_Macro_Calculator' ) ? WSErgo_Country_Macro_Calculator::has_macro_csv_data_sources() : false;
		$wsergo_custom_metrics_saved = class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::get_macro_custom_metrics() : [];
		$wsergo_custom_slugs_flip = [];
		if ( class_exists( 'WSErgo_Settings' ) ) {
			foreach ( WSErgo_Settings::get_macro_custom_metric_slugs() as $_cms ) {
				$wsergo_custom_slugs_flip[ $_cms ] = true;
			}
		}
		$wsergo_cm_ops = [
			'add'       => __( 'A + B (╤Б╤Г╨╝╨╝╨░)', 'worldstat-ergonomics' ),
			'sub'       => __( 'A тИТ B (╤А╨░╨╖╨╜╨╛╤Б╤В╤М)', 'worldstat-ergonomics' ),
			'mul'       => __( 'A ├Ч B (╨┐╤А╨╛╨╕╨╖╨▓╨╡╨┤╨╡╨╜╨╕╨╡)', 'worldstat-ergonomics' ),
			'div'       => __( 'A / B (╨┤╨╡╨╗╨╡╨╜╨╕╨╡, BтЙа0)', 'worldstat-ergonomics' ),
			'scale_mul' => __( 'A ├Ч ╤З╨╕╤Б╨╗╨╛', 'worldstat-ergonomics' ),
			'scale_add' => __( 'A + ╤З╨╕╤Б╨╗╨╛', 'worldstat-ergonomics' ),
		];
		$wsergo_cm_opt = class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::OPTION_MACRO_CUSTOM_METRICS : 'wsergo_macro_custom_metrics';
		$wsergo_cm_cnt = count(
			array_filter(
				$wsergo_custom_metrics_saved,
				static function ( $r ) {
					return is_array( $r ) && sanitize_key( (string) ( $r['slug'] ?? '' ) ) !== '';
				}
			)
		);
		$macro_criteria_matrix = class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::get_macro_criteria_matrix() : [];
		$macro_criteria_w      = class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::get_macro_criteria_weights() : [];
		$macro_criteria_inv    = class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::get_macro_criteria_inverts() : [];
		$macro_axes_six        = [ 'F', 'Cm', 'H', 'A', 'S', 'Ct' ];
		$ref_macro_raw_row      = null;
		$ref_macro_example_note = '';
		if ( $macro_ref_country_id > 0 ) {
			$iso2_ref = strtoupper( trim( (string) get_post_meta( $macro_ref_country_id, 'wsp_iso_alpha2', true ) ) );
			if ( strlen( $iso2_ref ) === 2 ) {
				$tref = get_the_title( $macro_ref_country_id );
				$ref_macro_example_note = $tref !== '' ? $tref . ' (' . $iso2_ref . ', ' . (int) $macro_year . ')' : '';
			}
		}

		$city_import_opt_key = class_exists( 'WSErgo_City_Bridge' ) ? WSErgo_City_Bridge::get_city_import_option_key() : '';
		$city_import_enabled  = $city_import_opt_key !== '' && get_option( $city_import_opt_key, '1' ) === '1';
		$city_posts_for_ref    = [];
		if ( class_exists( 'WSCities_CPT' ) ) {
			$city_posts_for_ref = get_posts(
				[
					'post_type'              => WSCities_CPT::SLUG,
					'post_status'            => 'publish',
					'posts_per_page'         => 500,
					'orderby'                => 'title',
					'order'                  => 'ASC',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
				]
			);
		}
		$city_preview_post_id = self::resolve_city_preview_post_id_for_admin( (int) $macro_ref_country_id, $city_posts_for_ref );
		$city_field_map_rows = class_exists( 'WSErgo_City_Bridge' ) ? WSErgo_City_Bridge::get_field_map() : [];
		$city_field_map_rows[] = [ 'source' => '', 'indicator_id' => '' ];
		$city_field_map_rows[] = [ 'source' => '', 'indicator_id' => '' ];
		$city_source_choices  = class_exists( 'WSErgo_City_Bridge' ) ? WSErgo_City_Bridge::get_source_choices() : [];
		$city_dim_labels       = WSErgo_Model::get_dimension_labels();
		$indicator_defs_stored = WSErgo_Indicators::get_definitions();
		$indicator_form_rows   = $indicator_defs_stored;
		for ( $__pad = count( $indicator_form_rows ); $__pad < 8; $__pad++ ) {
			$indicator_form_rows[] = [
				'id'          => '',
				'label'       => '',
				'dimension'   => WSErgo_Model::DIM_FUNCTIONALITY,
				'unit'        => '',
				'vmin'        => 0.0,
				'vmax'        => 100.0,
				'direction'   => 'higher_better',
				'weight'      => 1.0,
			];
		}
		$city_preview_raw     = [];
		$city_preview_scores  = [];
		$city_preview_e       = null;
		$city_preview_title   = '';
		if ( $city_preview_post_id > 0 && class_exists( 'WSErgo_City_Bridge' ) && WSErgo_City_Bridge::is_city_import_ergo_enabled() ) {
			$city_preview_raw = WSErgo_City_Bridge::collect_raw_for_city( $city_preview_post_id );
			if ( ! empty( $city_preview_raw ) ) {
				$city_preview_scores = WSErgo_Indicators::build_dimension_scores_from_raw_map( $city_preview_raw );
				$city_preview_e      = WSErgo_Calculator::compute_leaf_from_raw_indicator_map( $city_preview_post_id, $city_preview_raw );
			}
			$city_preview_title = get_the_title( $city_preview_post_id );
		}

		$city_models            = class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::get_city_models() : [];
		$city_active_id        = (string) get_option( WSErgo_Settings::OPTION_CITY_ACTIVE_MODEL, 'default_weighted' );
		$city_coeffs            = WSErgo_Settings::get_city_coefficients();
		$city_active_model      = WSErgo_Settings::get_city_active_model();
		$city_allowed_txt      = implode( ', ', array_keys( WSErgo_Settings::get_city_leaf_formula_allowed_ids() ) );
		$city_macro_year        = WSErgo_Settings::get_city_macro_reference_year();
		$city_macro_k           = WSErgo_Settings::get_city_macro_k_clusters();
		$city_macro_e_w         = WSErgo_Settings::get_city_macro_e_axis_weights();
		$city_macro_criteria_matrix = WSErgo_Settings::get_city_macro_criteria_matrix();
		$city_macro_criteria_w  = WSErgo_Settings::get_city_macro_criteria_weights();
		$city_macro_criteria_inv = WSErgo_Settings::get_city_macro_criteria_inverts();
		$city_macro_axis_resolved = WSErgo_Settings::get_city_macro_axis_terms_resolved();
		$stored_cf_city         = WSErgo_Settings::get_city_macro_cluster_features();
		$default_cf_city        = class_exists( 'WSErgo_Country_Macro_Calculator' ) ? WSErgo_Country_Macro_Calculator::default_cluster_features() : [];
		$cf_for_checkboxes_city = count( $stored_cf_city ) >= 2 ? $stored_cf_city : $default_cf_city;
		$city_macro_extra_signals_text = (string) get_option( WSErgo_Settings::OPTION_CITY_MACRO_EXTRA_SIGNALS_TEXT, '' );
		$data_labels_city_saved = WSErgo_Settings::get_city_data_labels_ru();
		$data_label_keys_city   = class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::all_city_data_label_keys() : [];
		$wsergo_city_custom_metrics_saved = WSErgo_Settings::get_city_macro_custom_metrics();
		$wsergo_cm_opt_city     = WSErgo_Settings::OPTION_CITY_MACRO_CUSTOM_METRICS;
		$wsergo_cm_cnt_city     = count(
			array_filter(
				$wsergo_city_custom_metrics_saved,
				static function ( $r ) {
					return is_array( $r ) && sanitize_key( (string) ( $r['slug'] ?? '' ) ) !== '';
				}
			)
		);
		$wsergo_custom_slugs_flip_city = [];
		foreach ( WSErgo_Settings::get_city_macro_custom_metric_slugs() as $_cms_c ) {
			$wsergo_custom_slugs_flip_city[ $_cms_c ] = true;
		}
		$signals_ui_city        = class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::macro_signal_allowlist_city() : [];
		$ref_city_macro_raw_row      = null;
		$ref_city_macro_example_note = '';
		if ( $macro_ref_country_id > 0 && class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			$iso2_cc = strtoupper( trim( (string) get_post_meta( $macro_ref_country_id, 'wsp_iso_alpha2', true ) ) );
			if ( strlen( $iso2_cc ) === 2 ) {
				$det_city_macro = WSErgo_Country_Macro_Calculator::get_city_macro_detail( $iso2_cc );
				if ( is_array( $det_city_macro ) && isset( $det_city_macro['raw_row'] ) && is_array( $det_city_macro['raw_row'] ) ) {
					$ref_city_macro_raw_row = $det_city_macro['raw_row'];
				}
				$y_city_ex = is_array( $det_city_macro ) && isset( $det_city_macro['year'] ) ? (int) $det_city_macro['year'] : WSErgo_Settings::get_city_macro_reference_year();
				$trefc     = get_the_title( $macro_ref_country_id );
				if ( $trefc !== '' ) {
					$ref_city_macro_example_note = sprintf(
						/* translators: 1: country title, 2: ISO2, 3: city macro reference year */
						__( '%1$s (%2$s), ╨╛╨┐╨╛╤А╨╜╤Л╨╣ ╨│╨╛╨┤ ╨│╨╛╤А╨╛╨┤╨░ %3$d', 'worldstat-ergonomics' ),
						$trefc,
						$iso2_cc,
						$y_city_ex
					);
				} else {
					$ref_city_macro_example_note = $iso2_cc . ', ' . (string) (int) $y_city_ex;
				}
			}
		}

		$matrix_col_short_city = [
			'F'  => __( '╨д╤Г╨╜╨║╤Ж.', 'worldstat-ergonomics' ),
			'Cm' => __( '╨Ъ╨╛╨╝╤Д.', 'worldstat-ergonomics' ),
			'H'  => __( '╨Ю╨▒╨╕╤В.', 'worldstat-ergonomics' ),
			'A'  => __( '╨Ю╤Б╨▓.', 'worldstat-ergonomics' ),
			'S'  => __( '╨С╨╡╨╖╨╛╨┐.', 'worldstat-ergonomics' ),
			'Ct' => __( '╨г╨┐╤А.', 'worldstat-ergonomics' ),
		];
		$city_macro_label_ru = static function ( string $sig ) use ( $data_labels_city_saved ) {
			$sig = sanitize_key( $sig );
			if ( isset( $data_labels_city_saved[ $sig ] ) && (string) $data_labels_city_saved[ $sig ] !== '' ) {
				return (string) $data_labels_city_saved[ $sig ];
			}
			return class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::default_city_data_label_ru( $sig ) : $sig;
		};

		settings_errors();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( '╨н╤А╨│╨╛╨╜╨╛╨╝╨╕╤З╨╜╨╛╤Б╤В╤М', 'worldstat-ergonomics' ); ?></h1>
			<p class="description" style="margin-top:0;">
				<?php esc_html_e( '╨б╤В╤А╨░╨╜╨╛╨▓╤Л╨╣ ╨╕╨╜╨┤╨╡╨║╤Б тАФ ╨╝╨░╨║╤А╨╛╨┤╨░╨╜╨╜╤Л╨╡ CSV, ╨║╨╗╨░╤Б╤В╨╡╤А╨╕╨╖╨░╤Ж╨╕╤П k-means ╨╕ ╤И╨╡╤Б╤В╤М ╨║╤А╨╕╤В╨╡╤А╨╕╨╡╨▓ FтАжCt ╨▓ ╨▒╨╗╨╛╨║╨╡ ┬л╨н╤А╨│╨╛╨╜╨╛╨╝╨╕╤З╨╜╨╛╤Б╤В╤М ╤Б╤В╤А╨░╨╜╤Л┬╗. ╨У╨╛╤А╨╛╨┤╤Б╨║╨╛╨╣ ╨╗╨╕╤Б╤В╨╛╨▓╨╛╨╣ E тАФ ╨┐╨╛ ╨╝╨╡╤В╨░ ╨╕ Blocks & Roads wsp_city, ╨║╨░╤А╤В╨╡ ╨┐╨╛╨╗╨╡╨╣ ╨╕ ╨╗╨╕╤Б╤В╨╛╨▓╤Л╨╝ ╨┐╨╛╨║╨░╨╖╨░╤В╨╡╨╗╤П╨╝; ╨╛╤В╨┤╨╡╨╗╤М╨╜╨╛ ╨┤╨╗╤П ╨│╨╛╤А╨╛╨┤╨░ тАФ ╤Б╨▓╨╛╤П ╨╝╨░╤В╤А╨╕╤Ж╨░ ╨╝╨░╨║╤А╨╛-╨┐╤А╨╕╨╖╨╜╨░╨║╨╛╨▓, ╤Б╨▓╨╛╤П ╨╝╨╛╨┤╨╡╨╗╤М DSL ╨╕ k_* ╨┤╨╗╤П ╤Б╨▒╨╛╤А╨║╨╕ E ╨╕╨╖ ╤И╨╡╤Б╤В╨╕ ╨╛╤Б╨╡╨╣ (╨╜╨░╤Б╤В╤А╨╛╨╣╨║╨╕ ╨╜╨░ ╨▓╨║╨╗╨░╨┤╨║╨░╤Е ╨│╨╛╤А╨╛╨┤╨░). ╨в╨╡╤А╤А╨╕╤В╨╛╤А╨╕╤П тАФ ╨╝╨╛╨┤╨╡╨╗╤М ╤Б╨▓╨╛╨┤╨╜╨╛╨│╨╛ ╨╕╨╜╨┤╨╡╨║╤Б╨░ ╨┤╨╗╤П ╨║╨▓╨░╤А╤В╨░╨╗╨░ (wsp_district) ╨┐╨╛ ╨┤╨░╨╜╨╜╤Л╨╝ wsdistrict_* ╨╕ ╨▓╨╡╤Б╨░╨╝ ╨║╤А╨╕╤В╨╡╤А╨╕╨╡╨▓.', 'worldstat-ergonomics' ); ?>
			</p>

			<h2 class="nav-tab-wrapper wsergo-ergo-scope-nav" style="margin-bottom:4px;">
				<a href="#ergo-country" class="nav-tab nav-tab-active" data-wsergo-scope="country"><?php esc_html_e( '╨н╤А╨│╨╛╨╜╨╛╨╝╨╕╤З╨╜╨╛╤Б╤В╤М ╤Б╤В╤А╨░╨╜╤Л', 'worldstat-ergonomics' ); ?></a>
				<a href="#ergo-city" class="nav-tab" data-wsergo-scope="city"><?php esc_html_e( '╨н╤А╨│╨╛╨╜╨╛╨╝╨╕╤З╨╜╨╛╤Б╤В╤М ╨│╨╛╤А╨╛╨┤╨░', 'worldstat-ergonomics' ); ?></a>
				<a href="#ergo-territory" class="nav-tab" data-wsergo-scope="territory"><?php esc_html_e( '╨н╤А╨│╨╛╨╜╨╛╨╝╨╕╤З╨╜╨╛╤Б╤В╤М ╤В╨╡╤А╤А╨╕╤В╨╛╤А╨╕╨╕', 'worldstat-ergonomics' ); ?></a>
			</h2>

			<form class="wsergo-settings-form" method="post" action="options.php">
				<?php settings_fields( 'wsergo_settings' ); ?>
				<div id="wsergo-cm-store" class="wsergo-cm-store" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
					<?php
					$wsergo_cmi_top = 0;
					foreach ( $wsergo_custom_metrics_saved as $cm_row ) :
						$cm_slug  = isset( $cm_row['slug'] ) ? (string) $cm_row['slug'] : '';
						$cm_op    = isset( $cm_row['op'] ) ? (string) $cm_row['op'] : 'div';
						$cm_ka    = isset( $cm_row['key_a'] ) ? (string) $cm_row['key_a'] : '';
						$cm_kb    = isset( $cm_row['key_b'] ) ? (string) $cm_row['key_b'] : '';
						$cm_const = isset( $cm_row['const'] ) ? (string) $cm_row['const'] : '';
						if ( $cm_slug === '' ) {
							continue;
						}
						?>
					<div class="wsergo-cm-row">
						<input type="hidden" name="<?php echo esc_attr( $wsergo_cm_opt ); ?>[<?php echo (int) $wsergo_cmi_top; ?>][slug]" value="<?php echo esc_attr( $cm_slug ); ?>" />
						<input type="hidden" name="<?php echo esc_attr( $wsergo_cm_opt ); ?>[<?php echo (int) $wsergo_cmi_top; ?>][op]" value="<?php echo esc_attr( $cm_op ); ?>" />
						<input type="hidden" name="<?php echo esc_attr( $wsergo_cm_opt ); ?>[<?php echo (int) $wsergo_cmi_top; ?>][key_a]" value="<?php echo esc_attr( $cm_ka ); ?>" />
						<input type="hidden" name="<?php echo esc_attr( $wsergo_cm_opt ); ?>[<?php echo (int) $wsergo_cmi_top; ?>][key_b]" value="<?php echo esc_attr( $cm_kb ); ?>" />
						<input type="hidden" name="<?php echo esc_attr( $wsergo_cm_opt ); ?>[<?php echo (int) $wsergo_cmi_top; ?>][const]" value="<?php echo esc_attr( $cm_const !== '' ? $cm_const : '0' ); ?>" />
					</div>
						<?php
						++$wsergo_cmi_top;
					endforeach;
					?>
				</div>
				<div id="wsergo-cm-store-city" class="wsergo-cm-store" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
					<?php
					$wsergo_cmi_top_city = 0;
					foreach ( $wsergo_city_custom_metrics_saved as $cm_row_c ) :
						$cm_slug_c  = isset( $cm_row_c['slug'] ) ? (string) $cm_row_c['slug'] : '';
						$cm_op_c    = isset( $cm_row_c['op'] ) ? (string) $cm_row_c['op'] : 'div';
						$cm_ka_c    = isset( $cm_row_c['key_a'] ) ? (string) $cm_row_c['key_a'] : '';
						$cm_kb_c    = isset( $cm_row_c['key_b'] ) ? (string) $cm_row_c['key_b'] : '';
						$cm_const_c = isset( $cm_row_c['const'] ) ? (string) $cm_row_c['const'] : '';
						if ( $cm_slug_c === '' ) {
							continue;
						}
						?>
					<div class="wsergo-cm-row">
						<input type="hidden" name="<?php echo esc_attr( $wsergo_cm_opt_city ); ?>[<?php echo (int) $wsergo_cmi_top_city; ?>][slug]" value="<?php echo esc_attr( $cm_slug_c ); ?>" />
						<input type="hidden" name="<?php echo esc_attr( $wsergo_cm_opt_city ); ?>[<?php echo (int) $wsergo_cmi_top_city; ?>][op]" value="<?php echo esc_attr( $cm_op_c ); ?>" />
						<input type="hidden" name="<?php echo esc_attr( $wsergo_cm_opt_city ); ?>[<?php echo (int) $wsergo_cmi_top_city; ?>][key_a]" value="<?php echo esc_attr( $cm_ka_c ); ?>" />
						<input type="hidden" name="<?php echo esc_attr( $wsergo_cm_opt_city ); ?>[<?php echo (int) $wsergo_cmi_top_city; ?>][key_b]" value="<?php echo esc_attr( $cm_kb_c ); ?>" />
						<input type="hidden" name="<?php echo esc_attr( $wsergo_cm_opt_city ); ?>[<?php echo (int) $wsergo_cmi_top_city; ?>][const]" value="<?php echo esc_attr( $cm_const_c !== '' ? $cm_const_c : '0' ); ?>" />
					</div>
						<?php
						++$wsergo_cmi_top_city;
					endforeach;
					?>
				</div>

			<div id="wsergo-panel-country" class="wsergo-scope-panel">

			<div class="notice notice-info inline" style="margin:12px 0;padding:12px;">
				<p style="margin:.4em 0;"><strong><?php esc_html_e( '╨Т╨║╨╗╨░╨┤╨║╨╕', 'worldstat-ergonomics' ); ?></strong>
					тАФ <a href="#tab-data" class="wsergo-tab-deep-link"><?php esc_html_e( '╨┐╨╡╤А╨╡╨╣╤В╨╕ ╨║ ┬л╨Ф╨░╨╜╨╜╤Л╨╡┬╗', 'worldstat-ergonomics' ); ?></a>
				</p>
				<ol style="margin:.5em 0 .5em 1.2em;list-style:decimal;padding-left:1em;">
					<li><?php esc_html_e( '╨Ф╨░╨╜╨╜╤Л╨╡ тАФ ╨║╨░╨╗╤М╨║╤Г╨╗╤П╤В╨╛╤А ╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╤М╤Б╨║╨╕╤Е ╨┐╨░╤А╨░╨╝╨╡╤В╤А╨╛╨▓ (╨╜╨╛╨▓╤Л╨╡ ╨┐╨╛╨║╨░╨╖╨░╤В╨╡╨╗╨╕ ╨╕╨╖ ╤Б╤В╨╛╨╗╨▒╤Ж╨╛╨▓ CSV), ╨┐╨╛╨┤╨┐╨╕╤Б╨╕ ╨║ ╨┐╤А╨╕╨╖╨╜╨░╨║╨░╨╝, ╨╝╨░╤В╤А╨╕╤Ж╨░ ╨╛╤В╨▒╨╛╤А╨░, ╨▓╨╡╤А╤Б╨╕╤П ╨╝╨╡╤В╨╛╨┤╨╕╨║╨╕, k-means, ╨╛╨┐╨╛╤А╨╜╤Л╨╣ ╨│╨╛╨┤, ╤Н╤В╨░╨╗╨╛╨╜╨╜╨░╤П ╤Б╤В╤А╨░╨╜╨░ ╨┤╨╗╤П ╨┐╤А╨╡╨┐╤А╨╛╤Б╨╝╨╛╤В╤А╨░ ╨╜╨░ ┬л╨д╨╛╤А╨╝╤Г╨╗╨░┬╗.', 'worldstat-ergonomics' ); ?></li>
					<li><?php esc_html_e( '╨д╨╛╤А╨╝╤Г╨╗╨░ тАФ ╨╝╨╛╨┤╨╡╨╗╤М ╨╕ DSL ╨╛╨▒╤К╨╡╨║╤В╨░ ╨▓╨▓╨╡╤А╤Е╤Г ╤Б╤В╤А╨░╨╜╨╕╤Ж╤Л; ╨╝╨░╨║╤А╨╛-╨║╤А╨╕╤В╨╡╤А╨╕╨╕ ╤Б╤В╤А╨░╨╜╤Л: ╨▓╨╡╤Б╨░ ╨╕ ╤Б╤Г╨╝╨╝╤Л. ╨Х╤Б╨╗╨╕ ╨▓╨║╨╗╤О╤З╨╡╨╜╨░ ╨╝╨░╤В╤А╨╕╤Ж╨░ ╨╜╨░ ┬л╨Ф╨░╨╜╨╜╤Л╨╡┬╗, ╤Б╨╛╤Б╤В╨░╨▓ ╨┐╨░╤А╨░╨╝╨╡╤В╤А╨╛╨▓ ╨┐╨╛ ╨║╤А╨╕╤В╨╡╤А╨╕╤П╨╝ ╨╖╨░╨┤╨░╤С╤В╤Б╤П ╤В╨╛╨╗╤М╨║╨╛ ╤В╨░╨╝, ╨░ ╨╜╨░ ┬л╨д╨╛╤А╨╝╤Г╨╗╨░┬╗ тАФ ╨▓╨╡╤Б╨░ ╤Б╨╗╨░╨│╨░╨╡╨╝╤Л╤Е ╨╕ ╨▓╨╡╤Б ╨║╤А╨╕╤В╨╡╤А╨╕╤П ╨▓ E.', 'worldstat-ergonomics' ); ?></li>
				</ol>
			</div>

			<h2 class="nav-tab-wrapper wsergo-country-inner-nav" style="margin-top:8px;">
				<a href="#tab-data" class="nav-tab nav-tab-active" data-tab="tab-data"><?php esc_html_e( '╨Ф╨░╨╜╨╜╤Л╨╡', 'worldstat-ergonomics' ); ?></a>
				<a href="#tab-formula" class="nav-tab" data-tab="tab-formula"><?php esc_html_e( '╨д╨╛╤А╨╝╤Г╨╗╨░', 'worldstat-ergonomics' ); ?></a>
			</h2>

				<div id="tab-data" class="wsergo-tab-panel wsergo-tab-panel-country">
					<h2><?php esc_html_e( '╨Ф╨░╨╜╨╜╤Л╨╡', 'worldstat-ergonomics' ); ?></h2>
					<p class="description"><?php esc_html_e( '╨Ш╤Б╤В╨╛╤З╨╜╨╕╨║╨╕ ╨╕ ╨┐╨░╤А╨░╨╝╨╡╤В╤А╤Л ╨┤╨╗╤П ╤Б╤В╤А╨░╨╜╨╛╨▓╨╛╨│╨╛ ╨╕╨╜╨┤╨╡╨║╤Б╨░ ╨┐╨╛ ╨╝╨░╨║╤А╨╛╨┤╨░╨╜╨╜╤Л╨╝ CSV. ╨Я╨╛╨┤╨┤╨╡╤А╨╢╨╕╨▓╨░╤О╤В╤Б╤П ┬л╨┤╨╗╨╕╨╜╨╜╤Л╨╡┬╗ ╤Д╨░╨╣╨╗╤Л (country_code, year, value) ╨╕ ╤И╨╕╤А╨╛╨║╨╕╨╡ (country_code, year ╨╕ ╨╜╨╡╤Б╨║╨╛╨╗╤М╨║╨╛ ╤З╨╕╤Б╨╗╨╛╨▓╤Л╤Е ╤Б╤В╨╛╨╗╨▒╤Ж╨╛╨▓ тАФ demographics, urban_infra, environment ╨╕ ╤В.╨┤.). ╨Ф╨╗╤П ╨▒╨░╨╖╨╛╨▓╨╛╨│╨╛ ╤В╤А╨╡╤Г╨│╨╛╨╗╤М╨╜╨╕╨║╨░ ╨╜╨░╤Б╨╡╨╗╨╡╨╜╨╕╤П/╨┐╨╗╨╛╤Й╨░╨┤╨╕ ╨┐╨╛-╨┐╤А╨╡╨╢╨╜╨╡╨╝╤Г ╨╜╤Г╨╢╨╜╤Л ╤А╤П╨┤╤Л population_total ╨╕ surface_area_sqkm ╨╗╨╕╨▒╨╛ ╤Б╨╛╨▓╨╝╨╡╤Б╤В╨╕╨╝╤Л╨╡ long-CSV; ╨╕╨╖ ╤И╨╕╤А╨╛╨║╨╕╤Е ╤Д╨░╨╣╨╗╨╛╨▓ ╨┐╨╛╨┤╤В╤П╨│╨╕╨▓╨░╤О╤В╤Б╤П ╨┐╨╗╨╛╤В╨╜╨╛╤Б╤В╤М, ╨┤╨╛╨╗╤П ╨│╨╛╤А╨╛╨┤╤Б╨║╨╛╨│╨╛ ╨╜╨░╤Б╨╡╨╗╨╡╨╜╨╕╤П, ╨╗╨╡╤Б ╨╕ ╨┐╤А╨╛╤З╨╕╨╡ ╨┐╤А╨╕╨╖╨╜╨░╨║╨╕.', 'worldstat-ergonomics' ); ?></p>
					<?php if ( defined( 'WSERGO_URL' ) ) : ?>
					<p class="description">
						<a href="<?php echo esc_url( WSERGO_URL . 'data/ergo-wide-csv-reference.txt' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( '╨б╨┐╤А╨░╨▓╨║╨░ ╨┐╨╛ ╤Б╤В╨╛╨╗╨▒╤Ж╨░╨╝ ╤И╨╕╤А╨╛╨║╨╕╤Е CSV (╨╛╤В╨║╤А╤Л╨▓╨░╨╡╤В╤Б╤П ╨▓ ╨╜╨╛╨▓╨╛╨╣ ╨▓╨║╨╗╨░╨┤╨║╨╡)', 'worldstat-ergonomics' ); ?></a>
					</p>
					<?php endif; ?>

					<h3><?php esc_html_e( '╨Ъ╨░╨╗╤М╨║╤Г╨╗╤П╤В╨╛╤А ╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╤М╤Б╨║╨╕╤Е ╨┐╨░╤А╨░╨╝╨╡╤В╤А╨╛╨▓', 'worldstat-ergonomics' ); ?></h3>
					<p class="description"><?php esc_html_e( '╨Ф╨╛╨▒╨░╨▓╤М╤В╨╡ ╨┐╨╛╨║╨░╨╖╨░╤В╨╡╨╗╤М ╨╕╨╖ ╤Г╨╢╨╡ ╨╖╨░╨│╤А╤Г╨╢╨╡╨╜╨╜╤Л╤Е ╤Б╤В╨╛╨╗╨▒╤Ж╨╛╨▓ CSV ╨╕╨╗╨╕ ╨╕╨╖ ╨┐╤А╨╛╨╕╨╖╨▓╨╛╨┤╨╜╤Л╤Е ╨╝╨╛╨┤╨╡╨╗╨╕. ╨Ш╤В╨╛╨│╨╛╨▓╤Л╨╣ ╨║╨╗╤О╤З тАФ ╨╗╨░╤В╨╕╨╜╨╕╤Ж╨░ (snake_case). ╨Я╨╛ ╨║╨╜╨╛╨┐╨║╨╡ ┬л╨Ф╨╛╨▒╨░╨▓╨╕╤В╤М┬╗ ╨┐╤А╨░╨▓╨╕╨╗╨╛ ╤Б╨╛╤Е╤А╨░╨╜╤П╨╡╤В╤Б╤П ╨▓ ╨▒╨░╨╖╤Г ╤Б╤А╨░╨╖╤Г; ╨╛╤Б╤В╨░╨╗╤М╨╜╤Л╨╡ ╨┐╨╛╨╗╤П ╤Б╤В╤А╨░╨╜╨╕╤Ж╤Л тАФ ╨║╨╜╨╛╨┐╨║╨╛╨╣ ┬л╨б╨╛╤Е╤А╨░╨╜╨╕╤В╤М ╨╜╨░╤Б╤В╤А╨╛╨╣╨║╨╕┬╗ ╨▓╨╜╨╕╨╖╤Г. ╨Э╨░ ╨╛╤З╨╡╨╜╤М ╨┤╨╗╨╕╨╜╨╜╤Л╤Е ╤Д╨╛╤А╨╝╨░╤Е PHP ╨╝╨╛╨╢╨╡╤В ╨╛╨│╤А╨░╨╜╨╕╤З╨╕╨▓╨░╤В╤М ╤З╨╕╤Б╨╗╨╛ ╨┐╨╛╨╗╨╡╨╣ (max_input_vars) тАФ ╨╛╤В╨┤╨╡╨╗╤М╨╜╨╛╨╡ ╤Б╨╛╤Е╤А╨░╨╜╨╡╨╜╨╕╨╡ ╨║╨░╨╗╤М╨║╤Г╨╗╤П╤В╨╛╤А╨░ ╤Н╤В╨╛ ╨╛╨▒╤Е╨╛╨┤╨╕╤В.', 'worldstat-ergonomics' ); ?></p>
					<div class="wsergo-cm-panel" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;padding:12px;border:1px solid #c3c4c7;background:#fff;margin-bottom:10px;max-width:920px;border-radius:4px;">
						<div>
							<label for="wsergo-cm-key-a" class="screen-reader-text"><?php esc_html_e( '╨Я╨░╤А╨░╨╝╨╡╤В╤А A', 'worldstat-ergonomics' ); ?></label>
							<span class="description" style="display:block;margin-bottom:4px;"><?php esc_html_e( '╨Я╨░╤А╨░╨╝╨╡╤В╤А A', 'worldstat-ergonomics' ); ?></span>
							<input type="text" id="wsergo-cm-key-a" class="regular-text code" maxlength="96" autocomplete="off" placeholder="<?php esc_attr_e( '╨║╨╗╤О╤З ╤Б╤В╨╛╨╗╨▒╤Ж╨░', 'worldstat-ergonomics' ); ?>" />
						</div>
						<div>
							<label for="wsergo-cm-op" class="screen-reader-text"><?php esc_html_e( '╨Ю╨┐╨╡╤А╨░╤Ж╨╕╤П', 'worldstat-ergonomics' ); ?></label>
							<span class="description" style="display:block;margin-bottom:4px;"><?php esc_html_e( '╨Ю╨┐╨╡╤А╨░╤Ж╨╕╤П', 'worldstat-ergonomics' ); ?></span>
							<select id="wsergo-cm-op" style="min-width:11em;">
								<?php foreach ( $wsergo_cm_ops as $op_k => $op_lab ) : ?>
									<option value="<?php echo esc_attr( $op_k ); ?>"><?php echo esc_html( $op_lab ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div id="wsergo-cm-wrap-b">
							<label for="wsergo-cm-key-b" class="screen-reader-text"><?php esc_html_e( '╨Я╨░╤А╨░╨╝╨╡╤В╤А B', 'worldstat-ergonomics' ); ?></label>
							<span class="description" style="display:block;margin-bottom:4px;"><?php esc_html_e( '╨Я╨░╤А╨░╨╝╨╡╤В╤А B', 'worldstat-ergonomics' ); ?></span>
							<input type="text" id="wsergo-cm-key-b" class="regular-text code" maxlength="96" autocomplete="off" placeholder="<?php esc_attr_e( '╨║╨╗╤О╤З ╤Б╤В╨╛╨╗╨▒╤Ж╨░', 'worldstat-ergonomics' ); ?>" />
						</div>
						<div id="wsergo-cm-wrap-const" style="display:none;">
							<label for="wsergo-cm-const" class="screen-reader-text"><?php esc_html_e( '╨з╨╕╤Б╨╗╨╛', 'worldstat-ergonomics' ); ?></label>
							<span class="description" style="display:block;margin-bottom:4px;"><?php esc_html_e( '╨з╨╕╤Б╨╗╨╛', 'worldstat-ergonomics' ); ?></span>
							<input type="text" id="wsergo-cm-const" class="small-text" inputmode="decimal" value="0" />
						</div>
						<div style="flex:1;min-width:180px;">
							<label for="wsergo-cm-slug" class="screen-reader-text"><?php esc_html_e( '╨Ш╤В╨╛╨│╨╛╨▓╤Л╨╣ ╨║╨╗╤О╤З', 'worldstat-ergonomics' ); ?></label>
							<span class="description" style="display:block;margin-bottom:4px;"><?php esc_html_e( '╨Ш╤В╨╛╨│╨╛╨▓╤Л╨╣ ╨║╨╗╤О╤З', 'worldstat-ergonomics' ); ?></span>
							<input type="text" id="wsergo-cm-slug" class="regular-text code" maxlength="96" autocomplete="off" placeholder="<?php esc_attr_e( '╨╜╨░╨┐╤А. my_ratio', 'worldstat-ergonomics' ); ?>" />
						</div>
						<div>
							<button type="button" id="wsergo-cm-add" class="button button-primary"><?php esc_html_e( '╨Ф╨╛╨▒╨░╨▓╨╕╤В╤М', 'worldstat-ergonomics' ); ?></button>
						</div>
					</div>
					<p class="description" style="margin-top:0;">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: number of saved custom metric rules. */
								_n( '╨б╨╛╤Е╤А╨░╨╜╨╡╨╜╨╛ ╨┐╤А╨░╨▓╨╕╨╗ ╨▓ ╨║╨░╨╗╤М╨║╤Г╨╗╤П╤В╨╛╤А╨╡: %d.', '╨б╨╛╤Е╤А╨░╨╜╨╡╨╜╨╛ ╨┐╤А╨░╨▓╨╕╨╗ ╨▓ ╨║╨░╨╗╤М╨║╤Г╨╗╤П╤В╨╛╤А╨╡: %d.', $wsergo_cm_cnt, 'worldstat-ergonomics' ),
								(int) $wsergo_cm_cnt
							)
						);
						?>
					</p>
					<input type="hidden" id="wsergo-cm-opt-name" value="<?php echo esc_attr( $wsergo_cm_opt ); ?>" />
					<p class="description"><?php esc_html_e( '╨Я╨╛╤А╤П╨┤╨╛╨║ ╨┤╨╛╨▒╨░╨▓╨╗╨╡╨╜╨╕╤П ╨▓╨░╨╢╨╡╨╜: ╨▓ ╤Б╨╗╨╡╨┤╤Г╤О╤Й╨╡╨╝ ╨┐╤А╨░╨▓╨╕╨╗╨╡ ╨╝╨╛╨╢╨╜╨╛ ╤Б╤Б╤Л╨╗╨░╤В╤М╤Б╤П ╨╜╨░ ╨║╨╗╤О╤З ╨╕╨╖ ╨┐╤А╨╡╨┤╤Л╨┤╤Г╤Й╨╡╨│╨╛. ╨Я╨╛╤Б╨╗╨╡ ╤Б╨╛╤Е╤А╨░╨╜╨╡╨╜╨╕╤П ╨╛╨▒╨╜╨╛╨▓╨╕╤В╤Б╤П ╨║╤Н╤И ╨╝╨░╨║╤А╨╛╨╕╨╜╨┤╨╡╨║╤Б╨░.', 'worldstat-ergonomics' ); ?></p>
					<hr />
					<h3><?php esc_html_e( '╨Я╨╛╨┤╨┐╨╕╤Б╨╕ ╨║ ╨┤╨░╨╜╨╜╤Л╨╝ (╤А╤Г╤Б╤Б╨║╨╕╨╣)', 'worldstat-ergonomics' ); ?></h3>
					<?php if ( ! $wsp_csv_has_datasets && class_exists( 'WorldStat_Uploaded_Csv' ) && empty( $data_label_keys ) ) : ?>
						<div class="notice notice-warning inline" style="margin:10px 0;padding:10px 12px;">
							<p style="margin:0;">
								<?php
								echo esc_html(
									__( '╨Т ╨▒╨░╨╖╨╡ ╨╜╨╡╤В ╨╖╨░╨│╤А╤Г╨╢╨╡╨╜╨╜╤Л╤Е CSV ╨╕ ╨╜╨╡ ╨╖╨░╨┤╨░╨╜╤Л ╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╤М╤Б╨║╨╕╨╡ ╨┐╨░╤А╨░╨╝╨╡╤В╤А╤Л: ╤Б╨┐╨╕╤Б╨╛╨║ ╨║╨╗╤О╤З╨╡╨╣ ╨┐╤Г╤Б╤В. ╨Ч╨░╨│╤А╤Г╨╖╨╕╤В╨╡ CSV ╨╕╨╗╨╕ ╨┤╨╛╨▒╨░╨▓╤М╤В╨╡ ╤Б╤В╤А╨╛╨║╨╕ ╨▓ ╨║╨░╨╗╤М╨║╤Г╨╗╤П╤В╨╛╤А╨╡ ╨▓╤Л╤И╨╡.', 'worldstat-ergonomics' )
								);
								?>
								<?php if ( current_user_can( 'manage_options' ) ) : ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=worldstat-csv' ) ); ?>"><?php esc_html_e( '╨Ф╨░╨╜╨╜╤Л╨╡ CSV', 'worldstat-ergonomics' ); ?></a>
								<?php endif; ?>
							</p>
						</div>
					<?php endif; ?>
					<p class="description"><?php esc_html_e( '╨б╨┐╨╕╤Б╨╛╨║ ╨║╨╗╤О╤З╨╡╨╣ тАФ ╤Б╤В╨╛╨╗╨▒╤Ж╤Л ╨╕╨╖ CSV, ╨┤╨╛╨┐╨╛╨╗╨╜╨╕╤В╨╡╨╗╤М╨╜╤Л╨╡ ╨║╨╗╤О╤З╨╕ ╨╕╨╖ ╨▒╨╗╨╛╨║╨░ ╨╜╨╕╨╢╨╡, ╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╤М╤Б╨║╨╕╨╡ ╨┐╨░╤А╨░╨╝╨╡╤В╤А╤Л ╨╕╨╖ ╨║╨░╨╗╤М╨║╤Г╨╗╤П╤В╨╛╤А╨░. ╨Я╨╛╨┤╨┐╨╕╤Б╤М тАФ ╨╖╨┤╨╡╤Б╤М ╨╕╨╗╨╕ ╨╕╨╝╨┐╨╛╤А╤В ┬л╨Я╨╡╤А╨╡╨▓╨╛╨┤╤Л┬╗; ╨╕╨╜╨░╤З╨╡ ╨╜╨░ ╤Б╨░╨╣╤В╨╡ ╨┐╤А╨╛╤З╨╡╤А╨║.', 'worldstat-ergonomics' ); ?></p>
					<div style="max-height:340px;overflow:auto;border:1px solid #c3c4c7;padding:10px;background:#fff;margin-bottom:12px;">
						<table class="widefat striped" style="margin:0;">
							<thead>
								<tr>
									<th scope="col" style="width:38%;"><?php esc_html_e( '╨Ъ╨╗╤О╤З ╨▓ ╨┤╨░╨╜╨╜╤Л╤Е', 'worldstat-ergonomics' ); ?></th>
									<th scope="col"><?php esc_html_e( '╨Ъ╨░╨║ ╨┐╨╛╨║╨░╨╖╤Л╨▓╨░╤В╤М ╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╤О', 'worldstat-ergonomics' ); ?></th>
								</tr>
							</thead>
							<tbody>
							<?php foreach ( $data_label_keys as $lk ) : ?>
								<?php $ov = isset( $data_labels_saved[ $lk ] ) ? $data_labels_saved[ $lk ] : ''; ?>
								<tr>
									<td><code><?php echo esc_html( $lk ); ?></code></td>
									<td>
										<input type="text" class="widefat" name="<?php echo esc_attr( WSErgo_Settings::OPTION_DATA_LABELS_RU ); ?>[<?php echo esc_attr( $lk ); ?>]" value="<?php echo esc_attr( $ov ); ?>" placeholder="<?php esc_attr_e( '╨Т╨▓╨╡╨┤╨╕╤В╨╡ ╨╛╨▒╨╛╨╖╨╜╨░╤З╨╡╨╜╨╕╨╡ ╨╜╨░ ╤А╤Г╤Б╤Б╨║╨╛╨╝', 'worldstat-ergonomics' ); ?>" maxlength="240" />
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<hr />
					<h3><?php esc_html_e( '╨Ь╨░╤В╤А╨╕╤Ж╨░ ╨║╤А╨╕╤В╨╡╤А╨╕╨╡╨▓ (╤В╨╛╨╗╤М╨║╨╛ ╨╛╤В╨▒╨╛╤А ╨┐╨░╤А╨░╨╝╨╡╤В╤А╨╛╨▓)', 'worldstat-ergonomics' ); ?></h3>
					<p class="description"><?php esc_html_e( '╨Ю╤В╨╝╨╡╤В╤М╤В╨╡ ╨┤╨╗╤П ╨║╨░╨╢╨┤╨╛╨│╨╛ ╨┐╨░╤А╨░╨╝╨╡╤В╤А╨░, ╨║ ╨║╨░╨║╨╕╨╝ ╨╕╨╖ ╤И╨╡╤Б╤В╨╕ ╨║╤А╨╕╤В╨╡╤А╨╕╨╡╨▓ ╤Б╤В╤А╨░╨╜╨╛╨▓╨╛╨│╨╛ ╨╕╨╜╨┤╨╡╨║╤Б╨░ ╨╛╨╜ ╨╛╤В╨╜╨╛╤Б╨╕╤В╤Б╤П. ╨Т╨╡╤Б╨░ ╤Б╨╗╨░╨│╨░╨╡╨╝╤Л╤Е ╨╕ ╨▓╨╡╤Б ╨║╤А╨╕╤В╨╡╤А╨╕╤П ╨▓ E ╨╜╨░╤Б╤В╤А╨░╨╕╨▓╨░╤О╤В╤Б╤П ╨╜╨░ ╨▓╨║╨╗╨░╨┤╨║╨╡ ┬л╨д╨╛╤А╨╝╤Г╨╗╨░┬╗.', 'worldstat-ergonomics' ); ?></p>
					<?php
					$matrix_col_short = [
						'F'  => __( '╨д╤Г╨╜╨║╤Ж.', 'worldstat-ergonomics' ),
						'Cm' => __( '╨Ъ╨╛╨╝╤Д.', 'worldstat-ergonomics' ),
						'H'  => __( '╨Ю╨▒╨╕╤В.', 'worldstat-ergonomics' ),
						'A'  => __( '╨Ю╤Б╨▓.', 'worldstat-ergonomics' ),
						'S'  => __( '╨С╨╡╨╖╨╛╨┐.', 'worldstat-ergonomics' ),
						'Ct' => __( '╨г╨┐╤А.', 'worldstat-ergonomics' ),
					];
					?>
					<div class="wsergo-macro-matrix-wrap" style="max-height:420px;overflow:auto;border:1px solid #c3c4c7;background:#fff;margin-bottom:12px;">
						<table class="widefat striped wsergo-macro-matrix-table" style="margin:0;min-width:680px;border-collapse:separate;border-spacing:0;">
							<thead>
								<tr>
									<th scope="col" class="wsergo-macro-matrix-sticky-corner" style="min-width:220px;"><?php esc_html_e( '╨Я╨░╤А╨░╨╝╨╡╤В╤А', 'worldstat-ergonomics' ); ?></th>
									<?php foreach ( $macro_axes_six as $axk ) : ?>
										<?php
										$th_short = isset( $matrix_col_short[ $axk ] ) ? $matrix_col_short[ $axk ] : $axk;
										$th_full  = isset( $macro_axis_labels[ $axk ] ) ? $macro_axis_labels[ $axk ] : $axk;
										?>
										<th scope="col" class="wsergo-macro-matrix-sticky-head" style="text-align:center;min-width:52px;padding:8px 4px;" title="<?php echo esc_attr( $th_full ); ?>">
											<span class="description"><?php echo esc_html( $th_short ); ?></span>
											<br /><code style="font-size:10px;"><?php echo esc_html( $axk ); ?></code>
										</th>
									<?php endforeach; ?>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $signals_ui as $msig ) : ?>
								<tr>
									<td class="wsergo-macro-matrix-sticky-firstcol">
										<?php if ( isset( $wsergo_custom_slugs_flip[ $msig ] ) ) : ?>
											<?php
											$wsergo_cm_del_url = wp_nonce_url(
												admin_url( 'admin-post.php?action=wsergo_delete_custom_metric&slug=' . rawurlencode( $msig ) ),
												'wsergo_delete_custom_metric'
											);
											?>
											<a href="<?php echo esc_url( $wsergo_cm_del_url ); ?>" class="button button-small" style="margin:0 10px 6px 0;vertical-align:middle;" onclick="return confirm('<?php echo esc_js( __( '╨г╨┤╨░╨╗╨╕╤В╤М ╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╤М╤Б╨║╨╕╨╣ ╨┐╨░╤А╨░╨╝╨╡╤В╤А ╨╕ ╨╡╨│╨╛ ╤Д╨╛╤А╨╝╤Г╨╗╤Г? ╨Ю╤В╨╝╨╡╤В╨║╨╕ ╨▓ ╨╝╨░╤В╤А╨╕╤Ж╨╡ ╨╕ ╨┐╨╛╨┤╨┐╨╕╤Б╤М ╨▒╤Г╨┤╤Г╤В ╤Б╨▒╤А╨╛╤И╨╡╨╜╤Л.', 'worldstat-ergonomics' ) ); ?>');"><?php esc_html_e( '╨г╨┤╨░╨╗╨╕╤В╤М', 'worldstat-ergonomics' ); ?></a>
										<?php endif; ?>
										<strong><?php echo esc_html( class_exists( 'WSErgo_Country_Macro_Calculator' ) ? WSErgo_Country_Macro_Calculator::data_label_ru( $msig ) : $msig ); ?></strong>
										<br /><code class="description"><?php echo esc_html( $msig ); ?></code>
									</td>
									<?php foreach ( $macro_axes_six as $axk ) : ?>
										<?php $m_on = ! empty( $macro_criteria_matrix[ $msig ][ $axk ] ); ?>
										<td style="text-align:center;vertical-align:middle;padding:6px 4px;">
											<input type="checkbox" name="<?php echo esc_attr( WSErgo_Settings::OPTION_MACRO_CRITERIA_MATRIX ); ?>[<?php echo esc_attr( $msig ); ?>][<?php echo esc_attr( $axk ); ?>]" value="1" <?php checked( $m_on ); ?> />
										</td>
									<?php endforeach; ?>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<hr />
					<table class="form-table">
						<tr>
							<th scope="row"><label for="wsergo_methodology_version"><?php esc_html_e( '╨Т╨╡╤А╤Б╨╕╤П ╨╝╨╡╤В╨╛╨┤╨╕╨║╨╕', 'worldstat-ergonomics' ); ?></label></th>
							<td>
								<input name="wsergo_methodology_version" id="wsergo_methodology_version" type="text" value="<?php echo esc_attr( get_option( 'wsergo_methodology_version', '1.2' ) ); ?>" class="regular-text" />
								<p class="description"><?php esc_html_e( '╨г╤Б╨╗╨╛╨▓╨╜╤Л╨╣ ╨╜╨╛╨╝╨╡╤А ╨╕╨╗╨╕ ╨╝╨╡╤В╨║╨░ ╤А╨╡╨┤╨░╨║╤Ж╨╕╨╕ ╨╝╨╡╤В╨╛╨┤╨╕╨║╨╕ (╨╜╨░╨┐╤А╨╕╨╝╨╡╤А 1.2, 2025тАСA). ╨г╨║╨░╨╖╤Л╨▓╨░╨╡╤В╤Б╤П ╨▓ ╨┐╨╛╨┤╨┐╨╕╤Б╤П╤Е ╨║ ╨╕╨╜╨┤╨╡╨║╤Б╤Г ╨╕ ╨╛╤В╤З╤С╤В╨░╤Е, ╤З╤В╨╛╨▒╤Л ╨▒╤Л╨╗╨╛ ╨▓╨╕╨┤╨╜╨╛, ╨┐╨╛ ╨║╨░╨║╨╛╨╣ ╨▓╨╡╤А╤Б╨╕╨╕ ╤Д╨╛╤А╨╝╤Г╨╗ ╨╕ ╨╜╨░╨▒╨╛╤А╨╛╨▓ ╨┤╨░╨╜╨╜╤Л╤Е ╨┐╨╛╨╗╤Г╤З╨╡╨╜╤Л ╨╖╨╜╨░╤З╨╡╨╜╨╕╤П. ╨Я╨╛╨▓╤Л╤И╨░╨╣╤В╨╡ ╨▓╨╡╤А╤Б╨╕╤О ╨┐╤А╨╕ ╨╕╨╖╨╝╨╡╨╜╨╡╨╜╨╕╨╕ ╤Д╨╛╤А╨╝╤Г╨╗, ╨▓╨╡╤Б╨╛╨▓ ╨╛╤Б╨╡╨╣ ╨╕╨╗╨╕ ╤Б╨╛╤Б╤В╨░╨▓╨░ CSV.', 'worldstat-ergonomics' ); ?></p>
							</td>
						</tr>
					</table>
					<hr />
					<h3><?php esc_html_e( '╨Я╤А╨╕╨╖╨╜╨░╨║╨╕ ╨┤╨╗╤П k-means (╨╝╨░╨║╤А╨╛)', 'worldstat-ergonomics' ); ?></h3>
					<p class="description"><?php esc_html_e( '╨Ю╤В╨╝╨╡╤В╤М╤В╨╡ ╨┐╤А╨╕╨╖╨╜╨░╨║╨╕, ╨▓╤Е╨╛╨┤╤П╤Й╨╕╨╡ ╨▓ ╨▓╨╡╨║╤В╨╛╤А ╨║╨╗╨░╤Б╤В╨╡╤А╨╕╨╖╨░╤Ж╨╕╨╕. ╨Э╤Г╨╢╨╜╨╛ ╨╜╨╡ ╨╝╨╡╨╜╤М╤И╨╡ ╨┤╨▓╤Г╤Е; ╨╕╨╜╨░╤З╨╡ ╨╕╤Б╨┐╨╛╨╗╤М╨╖╤Г╨╡╤В╤Б╤П ╨▓╤Б╤В╤А╨╛╨╡╨╜╨╜╤Л╨╣ ╨╜╨░╨▒╨╛╤А ╨┐╨╗╨░╨│╨╕╨╜╨░. ╨Ъ╨╜╨╛╨┐╨║╨░ ┬л╨Р╨▓╤В╨╛╨┐╨╛╨┤╨▒╨╛╤А┬╗ ╨░╨╜╨░╨╗╨╕╨╖╨╕╤А╤Г╨╡╤В ╤А╨░╨╖╨▒╤А╨╛╤Б ╨┐╨╛╨║╨░╨╖╨░╤В╨╡╨╗╨╡╨╣ ╨▓ CSV ╨╕ ╨┐╨╛╨┤╨▒╨╕╤А╨░╨╡╤В ╨╜╨╡╨║╨╛╤А╤А╨╡╨╗╨╕╤А╨╛╨▓╨░╨╜╨╜╤Л╨╡ ╨┐╤А╨╕╨╖╨╜╨░╨║╨╕ ╤Б ╨▓╤Л╤Б╨╛╨║╨╛╨╣ ╨▓╨░╤А╨╕╨░╤В╨╕╨▓╨╜╨╛╤Б╤В╤М╤О, ╨░ ╤В╨░╨║╨╢╨╡ ╨╛╨┐╤В╨╕╨╝╨░╨╗╤М╨╜╨╛╨╡ k (╨╝╨╡╤В╨╛╨┤ ╨╗╨╛╨║╤В╤П).', 'worldstat-ergonomics' ); ?></p>
					<p style="margin:.75em 0;">
						<button type="button" class="button button-primary wsergo-auto-tune-clusters" data-scope="country"><?php esc_html_e( '╨Р╨▓╤В╨╛╨┐╨╛╨┤╨▒╨╛╤А ╨┐╤А╨╕╨╖╨╜╨░╨║╨╛╨▓ ╨╕ k', 'worldstat-ergonomics' ); ?></button>
						<button type="button" class="button wsergo-auto-tune-clusters-save" data-scope="country"><?php esc_html_e( '╨Р╨▓╤В╨╛╨┐╨╛╨┤╨▒╨╛╤А ╨╕ ╤Б╨╛╤Е╤А╨░╨╜╨╕╤В╤М', 'worldstat-ergonomics' ); ?></button>
						<span class="wsergo-auto-tune-status wsp-muted" style="margin-left:8px;"></span>
					</p>
					<div id="wsergo-cluster-features-country" class="wsergo-cluster-features-host" data-scope="country" style="max-height:220px;overflow:auto;border:1px solid #c3c4c7;padding:10px;background:#fff;">
						<p class="wsp-muted wsergo-cluster-features-loading"><?php esc_html_e( '╨Ч╨░╨│╤А╤Г╨╖╨║╨░ ╤Б╨┐╨╕╤Б╨║╨░ ╨┐╤А╨╕╨╖╨╜╨░╨║╨╛╨▓тАж', 'worldstat-ergonomics' ); ?></p>
					</div>
					<div id="wsergo-cluster-tune-report-country" class="wsergo-cluster-tune-report" style="display:none;margin-top:10px;padding:10px;border:1px solid #c3c4c7;background:#fff;max-height:180px;overflow:auto;"></div>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="wsergo_macro_extra_signals"><?php esc_html_e( '╨Ф╨╛╨┐╨╛╨╗╨╜╨╕╤В╨╡╨╗╤М╨╜╤Л╨╡ ╨║╨╗╤О╤З╨╕ ╨┐╤А╨╕╨╖╨╜╨░╨║╨╛╨▓ (wide)', 'worldstat-ergonomics' ); ?></label>
							</th>
							<td>
								<textarea id="wsergo_macro_extra_signals" name="<?php echo esc_attr( WSErgo_Settings::OPTION_MACRO_EXTRA_SIGNALS_TEXT ); ?>" rows="6" class="large-text code"><?php echo esc_textarea( $macro_extra_signals_text ); ?></textarea>
								<p class="description">
									<?php esc_html_e( '╨Я╨╛ ╨╛╨┤╨╜╨╛╨╝╤Г ╨╗╨░╤В╨╕╨╜╤Б╨║╨╛╨╝╤Г ╨║╨╗╤О╤З╤Г ╨▓ ╤Б╤В╤А╨╛╨║╨╡ (╨║╨░╨║ ╨┐╨╛╤Б╨╗╨╡ ╨╜╨╛╤А╨╝╨░╨╗╨╕╨╖╨░╤Ж╨╕╨╕ ╨╖╨░╨│╨╛╨╗╨╛╨▓╨║╨░ ╤Б╤В╨╛╨╗╨▒╤Ж╨░ wide-CSV). ╨Ъ╨╗╤О╤З╨╕ ╨┐╨╛╤П╨▓╨╗╤П╤О╤В╤Б╤П ╨▓ ╤Б╨┐╨╕╤Б╨║╨╡ ╤З╨╡╨║╨▒╨╛╨║╤Б╨╛╨▓ ╨▓╤Л╤И╨╡ ╨╕ ╨▓ ╤Д╨╛╤А╨╝╤Г╨╗╨░╤Е ╨╛╤Б╨╡╨╣; ╨┤╨╛ 50 ╤Б╤В╤А╨╛╨║. ╨Х╤Б╨╗╨╕ ╨╕╨╝╤П ╤Б╤В╨╛╨╗╨▒╤Ж╨░ ╨▓ ╤Д╨░╨╣╨╗╨╡ ╨╜╨╡ ╤Б╨╛╨▓╨┐╨░╨┤╨░╨╡╤В ╤Б ╨║╨╗╤О╤З╨╛╨╝, ╤Б╨╛╨┐╨╛╤Б╤В╨░╨▓╨╗╨╡╨╜╨╕╨╡ ╨┐╨╛-╨┐╤А╨╡╨╢╨╜╨╡╨╝╤Г ╨╖╨░╨┤╨░╤С╤В╤Б╤П ╨▓ ╨║╨╛╨┤╨╡: wide_csv_column_to_signal.', 'worldstat-ergonomics' ); ?>
								</p>
							</td>
						</tr>
					</table>
					<hr />
					<h3><?php esc_html_e( '╨а╨╡╨╢╨╕╨╝ ╤А╨░╤Б╤З╤С╤В╨░ ╨┐╨╛ ╤Б╤В╤А╨░╨╜╨╡ ╨╕ CSV', 'worldstat-ergonomics' ); ?></h3>
					<p class="description">
						<?php esc_html_e( '╨Ш╨╜╨┤╨╡╨║╤Б ╤Б╤В╤А╨░╨╜╤Л ╤Б╤В╤А╨╛╨╕╤В╤Б╤П ╨┐╨╛ ╤Б╤В╤А╨░╨╜╨╛╨▓╤Л╨╝ ╤А╤П╨┤╨░╨╝ ╨╕╨╖ CSV ╨┐╨╗╨░╤В╤Д╨╛╤А╨╝╤Л, ╨╛╨┐╨╛╤А╨╜╨╛╨╝╤Г ╨│╨╛╨┤╤Г ╨╕ ╨║╨╗╨░╤Б╤В╨╡╤А╨╕╨╖╨░╤Ж╨╕╨╕ k-means.', 'worldstat-ergonomics' ); ?>
					</p>
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( '╨Ш╤Б╤В╨╛╤З╨╜╨╕╨║ ╨╕╨╜╨┤╨╡╨║╤Б╨░', 'worldstat-ergonomics' ); ?></th>
							<td>
								<input type="hidden" name="<?php echo esc_attr( WSErgo_Settings::OPTION_COUNTRY_INDEX_SOURCE ); ?>" value="macro_datasets" />
								<p class="description"><?php esc_html_e( '╨Ш╤Б╨┐╨╛╨╗╤М╨╖╤Г╤О╤В╤Б╤П ╤В╨╛╨╗╤М╨║╨╛ ╨╝╨░╨║╤А╨╛╨┤╨░╨╜╨╜╤Л╨╡ (CSV ╨▓ ╤А╨░╨╖╨┤╨╡╨╗╨╡ ┬л╨Ф╨░╨╜╨╜╤Л╨╡ CSV┬╗ ╨┐╨╗╨░╤В╤Д╨╛╤А╨╝╤Л).', 'worldstat-ergonomics' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wsergo_macro_reference_year"><?php esc_html_e( '╨Ю╨┐╨╛╤А╨╜╤Л╨╣ ╨│╨╛╨┤ (╨╝╨░╨║╤А╨╛)', 'worldstat-ergonomics' ); ?></label></th>
							<td>
								<?php
								$mref_min = class_exists( 'WorldStat_Platform_Years' ) ? max( 1900, WorldStat_Platform_Years::min() ) : 1900;
								$mref_max = class_exists( 'WorldStat_Platform_Years' ) ? max( 2100, WorldStat_Platform_Years::max() ) : 2100;
								?>
								<input type="number" id="wsergo_macro_reference_year" name="<?php echo esc_attr( WSErgo_Settings::OPTION_MACRO_REFERENCE_YEAR ); ?>" value="<?php echo esc_attr( (string) $macro_year ); ?>" class="small-text" min="<?php echo esc_attr( (string) $mref_min ); ?>" max="<?php echo esc_attr( (string) $mref_max ); ?>" step="1" />
								<p class="description"><?php esc_html_e( '╨У╨╛╨┤ ╨┤╨╗╤П ╨▓╤Л╨▒╨╛╤А╨║╨╕ ╨╖╨╜╨░╤З╨╡╨╜╨╕╨╣ country_code + year ╨▓ CSV (╨╡╤Б╨╗╨╕ ╨▓ ╤Д╨░╨╣╨╗╨╡ ╨╡╤Б╤В╤М ╤Б╤В╨╛╨╗╨▒╨╡╤Ж ╨│╨╛╨┤╨░).', 'worldstat-ergonomics' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wsergo_macro_k_clusters"><?php esc_html_e( '╨з╨╕╤Б╨╗╨╛ ╨║╨╗╨░╤Б╤В╨╡╤А╨╛╨▓ k-means (╨╝╨░╨║╤А╨╛)', 'worldstat-ergonomics' ); ?></label></th>
							<td>
								<input type="number" id="wsergo_macro_k_clusters" name="<?php echo esc_attr( WSErgo_Settings::OPTION_MACRO_K_CLUSTERS ); ?>" value="<?php echo esc_attr( (string) $macro_k ); ?>" class="small-text" min="2" max="12" step="1" />
								<p class="description"><?php esc_html_e( '╨Э╨╛╤А╨╝╨░╨╗╨╕╨╖╨░╤Ж╨╕╤П minтАУmax ╨▓╤Л╨┐╨╛╨╗╨╜╤П╨╡╤В╤Б╤П ╨▓╨╜╤Г╤В╤А╨╕ ╨║╨╗╨░╤Б╤В╨╡╤А╨░ ╤Б╤В╤А╨░╨╜.', 'worldstat-ergonomics' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wsergo_macro_reference_country"><?php esc_html_e( '╨н╤В╨░╨╗╨╛╨╜╨╜╨░╤П ╤Б╤В╤А╨░╨╜╨░', 'worldstat-ergonomics' ); ?></label></th>
							<td>
								<select id="wsergo_macro_reference_country" name="<?php echo esc_attr( WSErgo_Settings::OPTION_MACRO_REFERENCE_COUNTRY_POST_ID ); ?>">
									<option value="0"><?php esc_html_e( 'тАФ ╨╜╨╡ ╨▓╤Л╨▒╤А╨░╨╜╨░ тАФ', 'worldstat-ergonomics' ); ?></option>
									<?php foreach ( $country_posts_for_ref as $cp ) : ?>
										<?php
										$cid = (int) $cp->ID;
										$cis = strtoupper( trim( (string) get_post_meta( $cid, 'wsp_iso_alpha2', true ) ) );
										if ( strlen( $cis ) !== 2 ) {
											continue;
										}
										?>
										<option value="<?php echo esc_attr( (string) $cid ); ?>" <?php selected( $macro_ref_country_id, $cid ); ?>><?php echo esc_html( get_the_title( $cp ) . ' (' . $cis . ')' ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( '╨Ф╨╗╤П ╨║╨╛╨╗╨╛╨╜╨║╨╕ ┬л╨Я╤А╨╕╨╝╨╡╤А ╨┤╨░╨╜╨╜╤Л╤Е┬╗ ╨╜╨░ ╨▓╨║╨╗╨░╨┤╨║╨╡ ┬л╨д╨╛╤А╨╝╤Г╨╗╨░┬╗: ╨╖╨╜╨░╤З╨╡╨╜╨╕╤П ╨┐╤А╨╕╨╖╨╜╨░╨║╨╛╨▓ ╨╕╨╖ ╨╖╨░╨│╤А╤Г╨╢╨╡╨╜╨╜╤Л╤Е ╨╝╨░╨║╤А╨╛-CSV ╨┤╨╗╤П ╤Н╤В╨╛╨╣ ╤Б╤В╤А╨░╨╜╤Л ╨╕ ╨╛╨┐╨╛╤А╨╜╨╛╨│╨╛ ╨│╨╛╨┤╨░ (╨┤╨╕╨╜╨░╨╝╨╕╤З╨╡╤Б╨║╨╕ ╨╕╨╖ ╨║╤Н╤И╨░ ╤А╨░╤Б╤З╤С╤В╨░).', 'worldstat-ergonomics' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<div id="tab-formula" class="wsergo-tab-panel wsergo-tab-panel-country" style="display:none">
					<h2><?php esc_html_e( '╨д╨╛╤А╨╝╤Г╨╗╨░', 'worldstat-ergonomics' ); ?></h2>

					<h3><?php esc_html_e( '╨Р╨║╤В╨╕╨▓╨╜╨░╤П ╨╝╨╛╨┤╨╡╨╗╤М ╤А╨░╤Б╤З╤С╤В╨░ E', 'worldstat-ergonomics' ); ?></h3>
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( '╨Ь╨╛╨┤╨╡╨╗╤М', 'worldstat-ergonomics' ); ?></th>
							<td>
								<select name="<?php echo esc_attr( WSErgo_Settings::OPTION_ACTIVE_MODEL ); ?>">
									<?php foreach ( $models as $m ) : ?>
										<option value="<?php echo esc_attr( $m['id'] ); ?>" <?php selected( $active_id, $m['id'] ); ?>><?php echo esc_html( $m['name'] ); ?></option>
									<?php endforeach; ?>
								</select>
								<?php if ( $active_model ) : ?>
									<p class="description"><?php echo esc_html( $active_model['leaf_formula'] !== '' ? $active_model['leaf_formula'] : __( '╨Я╤Г╤Б╤В╨░╤П ╤Д╨╛╤А╨╝╤Г╨╗╨░: ╨▓╨╖╨▓╨╡╤И╨╡╨╜╨╜╨╛╨╡ ╤Б╤А╨╡╨┤╨╜╨╡╨╡ ╨┐╨╛ ╤И╨╡╤Б╤В╨╕ ╨╕╨╖╨╝╨╡╤А╨╡╨╜╨╕╤П╨╝ ╨╛╨▒╤К╨╡╨║╤В╨░ (╨║╨░╨║ ╨╖╨░╨┤╨░╨╜╤Л ╨▓╨╡╤Б╨░ ╨▓ ╨║╨░╤А╤В╨╛╤З╨║╨╡ ╨║╨▓╨░╤А╤В╨░╨╗╨░/╨╖╨┤╨░╨╜╨╕╤П).', 'worldstat-ergonomics' ) ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					</table>
					<hr />
					<h3><?php esc_html_e( '╨Ъ╨╛╤Н╤Д╤Д╨╕╤Ж╨╕╨╡╨╜╤В╤Л k_*', 'worldstat-ergonomics' ); ?></h3>
					<p class="description"><?php esc_html_e( '╨У╨╗╨╛╨▒╨░╨╗╤М╨╜╤Л╨╡ ╨╝╨╜╨╛╨╢╨╕╤В╨╡╨╗╨╕ ╨▓ ╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╤М╤Б╨║╨╛╨╣ ╤Д╨╛╤А╨╝╤Г╨╗╨╡ ╤Б╨▓╨╛╨┤╨╜╨╛╨│╨╛ E ╨┐╨╛ ╨╛╨▒╤К╨╡╨║╤В╤Г (k_default ╨╕ ╨┤╤А.). ╨Ю╤В╨┤╨╡╨╗╤М╨╜╨╛ ╨╛╤В ╨▓╨╡╤Б╨╛╨▓ ╨╝╨░╨║╤А╨╛╨║╤А╨╕╤В╨╡╤А╨╕╨╡╨▓ ╤Б╤В╤А╨░╨╜╤Л.', 'worldstat-ergonomics' ); ?></p>
					<table class="form-table">
						<?php foreach ( $coeffs as $k => $v ) : ?>
						<tr>
							<th scope="row"><label><?php echo esc_html( $k ); ?></label></th>
							<td><input type="text" name="wsergo_coefficients[<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( (string) $v ); ?>" class="small-text" /></td>
						</tr>
						<?php endforeach; ?>
					</table>
					<hr />
					<h3><?php esc_html_e( '╨Ь╨╛╨┤╨╡╨╗╨╕ ╨╕ DSL', 'worldstat-ergonomics' ); ?></h3>
					<p class="description">
						<?php esc_html_e( '╨Я╤А╨╛╤Б╤В╨╛╨╣ ╤А╨╡╨╢╨╕╨╝: ╨╛╤Б╤В╨░╨▓╤М╤В╨╡ ╤Д╨╛╤А╨╝╤Г╨╗╤Г ╨┐╤Г╤Б╤В╨╛╨╣ тАФ ╤Б╨▓╨╛╨┤╨╜╤Л╨╣ E ╤Б╤З╨╕╤В╨░╨╡╤В╤Б╤П ╨║╨░╨║ ╨▓╨╖╨▓╨╡╤И╨╡╨╜╨╜╨╛╨╡ ╤Б╤А╨╡╨┤╨╜╨╡╨╡ ╨┐╨╛ ╤И╨╡╤Б╤В╨╕ ╨╛╤Б╤П╨╝ ╨╛╨▒╤К╨╡╨║╤В╨░. ╨б╨▓╨╛╤П ╤Д╨╛╤А╨╝╤Г╨╗╨░: ╨╛╤Б╨╕ ╨╝╨╛╨╢╨╜╨╛ ╨║╨╛╨╝╨▒╨╕╨╜╨╕╤А╨╛╨▓╨░╤В╤М ╤Б ╨║╨╛╤Н╤Д╤Д╨╕╤Ж╨╕╨╡╨╜╤В╨░╨╝╨╕ k_*, ╨┐╨╛╨║╨░╨╖╨░╤В╨╡╨╗╤П╨╝╨╕ i_* ╨╕ ╨▓╨╡╤Б╨░╨╝╨╕ w_*.', 'worldstat-ergonomics' ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( '╨Т ╤Д╨╛╤А╨╝╤Г╨╗╨╡ ╨╕╤Б╨┐╨╛╨╗╤М╨╖╤Г╤О╤В╤Б╤П ╨╛╨▒╨╛╨╖╨╜╨░╤З╨╡╨╜╨╕╤П ╨╛╤Б╨╡╨╣ (╨▒╨░╨╗╨╗╤Л 0тАУ100), ╨▓╨╡╤Б╨░ w_* ╨╕╨╖ ╨║╨░╤А╤В╨╛╤З╨║╨╕ ╨╛╨▒╤К╨╡╨║╤В╨░, ╨┐╨╛╨║╨░╨╖╨░╤В╨╡╨╗╨╕ i_* ╨╕ ╨╝╨╜╨╛╨╢╨╕╤В╨╡╨╗╨╕ k_* ╨╕╨╖ ╤В╨░╨▒╨╗╨╕╤Ж╤Л ╨▓╤Л╤И╨╡.', 'worldstat-ergonomics' ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( '╨Ф╨╛╨┐╤Г╤Б╤В╨╕╨╝╤Л╨╡ ╨╕╨┤╨╡╨╜╤В╨╕╤Д╨╕╨║╨░╤В╨╛╤А╤Л:', 'worldstat-ergonomics' ); ?>
						<code><?php echo esc_html( $allowed_txt ); ?></code>
					</p>
					<p>
						<label for="wsergo_test_formula_inline"><?php esc_html_e( '╨Я╤А╨╛╨▓╨╡╤А╨║╨░ ╤Д╨╛╤А╨╝╤Г╨╗╤Л (╤В╨╡╤Б╤В╨╛╨▓╤Л╨╡ 50 ╨┐╨╛ ╨▓╤Б╨╡╨╝ ╨╛╤Б╤П╨╝)', 'worldstat-ergonomics' ); ?></label><br />
						<textarea id="wsergo_test_formula_inline" class="large-text" rows="2" placeholder="<?php esc_attr_e( '╨Ю╤Б╤В╨░╨▓╤М╤В╨╡ ╨┐╤Г╤Б╤В╤Л╨╝ ╨╕╨╗╨╕ ╨▓╤Б╤В╨░╨▓╤М╤В╨╡ ╨▓╤Л╤А╨░╨╢╨╡╨╜╨╕╨╡ ╨┐╨╛ ╤Б╨┐╨╕╤Б╨║╤Г ╨┤╨╛╨┐╤Г╤Б╤В╨╕╨╝╤Л╤Е ╨╕╨┤╨╡╨╜╤В╨╕╤Д╨╕╨║╨░╤В╨╛╤А╨╛╨▓ ╨╜╨╕╨╢╨╡', 'worldstat-ergonomics' ); ?>"></textarea><br />
						<button type="button" class="button" id="wsergo-test-formula-btn"><?php esc_html_e( '╨Я╤А╨╛╨▓╨╡╤А╨╕╤В╤М', 'worldstat-ergonomics' ); ?></button>
						<span id="wsergo-formula-test-result" class="description"></span>
					</p>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( '╨Ш╨┤╨╡╨╜╤В╨╕╤Д╨╕╨║╨░╤В╨╛╤А', 'worldstat-ergonomics' ); ?></th>
								<th><?php esc_html_e( '╨Э╨░╨╖╨▓╨░╨╜╨╕╨╡', 'worldstat-ergonomics' ); ?></th>
								<th><?php esc_html_e( '╨д╨╛╤А╨╝╤Г╨╗╨░ ╤Б╨▓╨╛╨┤╨╜╨╛╨│╨╛ E (╨┐╤Г╤Б╤В╨╛ = ╨║╨╗╨░╤Б╤Б╨╕╨║╨░)', 'worldstat-ergonomics' ); ?></th>
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
					<p class="description"><?php esc_html_e( '╨з╤В╨╛╨▒╤Л ╨┤╨╛╨▒╨░╨▓╨╕╤В╤М ╨╝╨╛╨┤╨╡╨╗╤М, ╤Б╨╛╤Е╤А╨░╨╜╨╕╤В╨╡ ╤Б╤В╤А╨░╨╜╨╕╤Ж╤Г ╨╕ ╨╛╤В╤А╨╡╨┤╨░╨║╤В╨╕╤А╤Г╨╣╤В╨╡ ╨╝╨░╤Б╤Б╨╕╨▓ ╨▓ ╨С╨Ф ╨╕╨╗╨╕ ╨┤╨╛╨▒╨░╨▓╤М╤В╨╡ ╤Б╤В╤А╨╛╨║╤Г ╤З╨╡╤А╨╡╨╖ ╤Д╨╕╨╗╤М╤В╤А wsergo_default_models.', 'worldstat-ergonomics' ); ?></p>

					<hr />

					<style type="text/css">
						.wsergo-macro-crit { border: 1px solid #c3c4c7; margin-bottom: 8px; border-radius: 4px; background: #fff; }
						.wsergo-macro-crit > summary.wsergo-macro-crit__bar {
							display: flex; flex-wrap: wrap; align-items: center; gap: 8px 20px;
							padding: 10px 12px; cursor: pointer; list-style: none;
						}
						.wsergo-macro-crit > summary.wsergo-macro-crit__bar::-webkit-details-marker { display: none; }
						.wsergo-macro-crit__chev { flex-shrink: 0; width: 20px; height: 20px; font-size: 18px; line-height: 1; transition: transform 0.15s ease; opacity: 0.8; }
						.wsergo-macro-crit[open] > summary .wsergo-macro-crit__chev { transform: rotate(90deg); }
						.wsergo-macro-crit__title { font-weight: 600; min-width: 160px; flex: 1; }
						.wsergo-macro-crit__e input.small-text { max-width: 5.5em; vertical-align: middle; }
						table.wsergo-macro-crit-table { table-layout: fixed; width: 100%; border-collapse: collapse; }
						table.wsergo-macro-crit-table th,
						table.wsergo-macro-crit-table td { vertical-align: middle; word-wrap: break-word; }
						table.wsergo-macro-crit-table col.col-macro-sig { width: 30%; }
						table.wsergo-macro-crit-table col.col-macro-w { width: 14%; }
						table.wsergo-macro-crit-table col.col-macro-inv { width: 14%; }
						table.wsergo-macro-crit-table col.col-macro-ex { width: 42%; }
						table.wsergo-macro-crit-table td.col-macro-inv,
						table.wsergo-macro-crit-table th.col-macro-inv { text-align: center; }
						table.wsergo-macro-crit-table td.col-macro-w input { width: 100%; max-width: 7em; box-sizing: border-box; }
						table.wsergo-macro-crit-table tfoot td { border-top: 1px solid #c3c4c7; padding-top: 8px; font-weight: 600; }
						.wsergo-macro-sum-line { font-weight: 600; }
					</style>
					<?php
					$wsergo_country_macro_formula_visible = $wsp_csv_has_datasets || ! empty( $signals_ui );
					?>
					<?php if ( $wsergo_country_macro_formula_visible ) : ?>
					<h3><?php esc_html_e( '╨Ь╨░╨║╤А╨╛: ╤И╨╡╤Б╤В╤М ╨║╤А╨╕╤В╨╡╤А╨╕╨╡╨▓ ╤Б╤В╤А╨░╨╜╨╛╨▓╨╛╨│╨╛ ╨╕╨╜╨┤╨╡╨║╤Б╨░', 'worldstat-ergonomics' ); ?></h3>
					<?php if ( ! $wsp_csv_has_datasets && ! empty( $signals_ui ) ) : ?>
						<div class="notice notice-info inline" style="margin:0 0 10px 0;padding:10px 12px;">
							<p style="margin:0;"><?php esc_html_e( '╨Т ╤А╨░╨╖╨┤╨╡╨╗╨╡ ┬л╨Ф╨░╨╜╨╜╤Л╨╡ CSV┬╗ ╨╜╨╡╤В ╨╖╨░╨│╤А╤Г╨╢╨╡╨╜╨╜╤Л╤Е ╨╜╨░╨▒╨╛╤А╨╛╨▓ тАФ ┬л╨Я╤А╨╕╨╝╨╡╤А ╨┤╨░╨╜╨╜╤Л╤Е┬╗ ╨╝╨╛╨╢╨╡╤В ╨▒╤Л╤В╤М ╨┐╤Г╤Б╤В╤Л╨╝ ╨┤╨╗╤П ╤Б╤В╨╛╨╗╨▒╤Ж╨╛╨▓ ╤В╨╛╨╗╤М╨║╨╛ ╨╕╨╖ CSV. ╨Т╨╡╤Б╨░ ╨┐╨╛ ╨┐╤А╨╕╨╖╨╜╨░╨║╨░╨╝ ╨╕╨╖ ╨╝╨░╤В╤А╨╕╤Ж╤Л ╤Б╤В╤А╨░╨╜╤Л ╨▓╤Б╤С ╤А╨░╨▓╨╜╨╛ ╤А╨╡╨┤╨░╨║╤В╨╕╤А╤Г╤О╤В╤Б╤П ╨╖╨┤╨╡╤Б╤М.', 'worldstat-ergonomics' ); ?></p>
						</div>
					<?php endif; ?>
					<p class="description">
						<?php esc_html_e( '╨Я╨╛╨║╨░ ╨╜╨░ ┬л╨Ф╨░╨╜╨╜╤Л╨╡┬╗ ╨╜╨╕ ╨╛╨┤╨╜╨░ ╨│╨░╨╗╨╛╤З╨║╨░ ╨╜╨╡ ╤Б╤В╨╛╨╕╤В, ╨┤╨╗╤П ╨▓╤Б╨╡╤Е ╨║╤А╨╕╤В╨╡╤А╨╕╨╡╨▓ ╨┤╨╡╨╣╤Б╤В╨▓╤Г╨╡╤В ╨▓╤Б╤В╤А╨╛╨╡╨╜╨╜╨░╤П ╨╝╨╡╤В╨╛╨┤╨╕╨║╨░. ╨Ъ╨░╨║ ╤В╨╛╨╗╤М╨║╨╛ ╨▓╤Л ╨╛╤В╨╝╨╡╤В╨╕╤В╨╡ ╨┐╨░╤А╨░╨╝╨╡╤В╤А╤Л ╤Г ╨║╤А╨╕╤В╨╡╤А╨╕╤П ╨╕ ╤Б╨╛╤Е╤А╨░╨╜╨╕╤В╨╡ ╨╜╨░╤Б╤В╤А╨╛╨╣╨║╨╕, ╨╛╨╜╨╕ ╨┐╨╛╤П╨▓╤П╤В╤Б╤П ╨╖╨┤╨╡╤Б╤М; ╤Б╨╛╤Б╤В╨░╨▓ ╨╝╨╡╨╜╤П╤В╤М ╨╜╨╡╨╗╤М╨╖╤П тАФ ╤В╨╛╨╗╤М╨║╨╛ ╨▓╨╡╤Б╨░.', 'worldstat-ergonomics' ); ?>
						<a href="#tab-data" class="wsergo-tab-deep-link"><?php esc_html_e( '╨Ъ ╨╝╨░╤В╤А╨╕╤Ж╨╡ ╨╛╤В╨▒╨╛╤А╨░', 'worldstat-ergonomics' ); ?></a>
					</p>
					<?php if ( $ref_macro_example_note !== '' ) : ?>
						<p class="description"><strong><?php esc_html_e( '╨Я╤А╨╕╨╝╨╡╤А ╨┤╨░╨╜╨╜╤Л╤Е', 'worldstat-ergonomics' ); ?>:</strong> <?php echo esc_html( $ref_macro_example_note ); ?> тАФ <?php esc_html_e( '╨╖╨╜╨░╤З╨╡╨╜╨╕╤П ╨╕╨╖ ╨╖╨░╨│╤А╤Г╨╢╨╡╨╜╨╜╤Л╤Е ╨╝╨░╨║╤А╨╛-CSV (╤Б╤Л╤А╨╛╨╣ ╤А╤П╨┤, ╨╛╨┐╨╛╤А╨╜╤Л╨╣ ╨│╨╛╨┤).', 'worldstat-ergonomics' ); ?></p>
					<?php elseif ( $macro_ref_country_id > 0 ) : ?>
						<p class="description"><?php esc_html_e( '╨Ф╨╗╤П ╨▓╤Л╨▒╤А╨░╨╜╨╜╨╛╨╣ ╤Н╤В╨░╨╗╨╛╨╜╨╜╨╛╨╣ ╤Б╤В╤А╨░╨╜╤Л ╨╜╨╡╤В ╤Б╤В╤А╨╛╨║╨╕ ╤Б╤Л╤А╤Л╤Е ╨┐╤А╨╕╨╖╨╜╨░╨║╨╛╨▓ ╨▓ ╨║╤Н╤И╨╡ ╤А╨░╤Б╤З╤С╤В╨░: ╨┐╤А╨╛╨▓╨╡╤А╤М╤В╨╡ ╨║╨╛╨┤ ISO2 ╨▓ ╨║╨░╤А╤В╨╛╤З╨║╨╡ ╤Б╤В╤А╨░╨╜╤Л ╨╕ ╨╜╨░╨╗╨╕╤З╨╕╨╡ ╤Б╤В╤А╨╛╨║ ╨▓ CSV ╨╖╨░ ╨╛╨┐╨╛╤А╨╜╤Л╨╣ ╨│╨╛╨┤.', 'worldstat-ergonomics' ); ?></p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( '╨з╤В╨╛╨▒╤Л ╨╖╨░╨┐╨╛╨╗╨╜╨╕╤В╤М ╤Б╤В╨╛╨╗╨▒╨╡╤Ж ┬л╨Я╤А╨╕╨╝╨╡╤А ╨┤╨░╨╜╨╜╤Л╤Е┬╗, ╨▓╤Л╨▒╨╡╤А╨╕╤В╨╡ ╤Н╤В╨░╨╗╨╛╨╜╨╜╤Г╤О ╤Б╤В╤А╨░╨╜╤Г ╨╜╨░ ╨▓╨║╨╗╨░╨┤╨║╨╡ ┬л╨Ф╨░╨╜╨╜╤Л╨╡┬╗ ╨╕ ╤Б╨╛╤Е╤А╨░╨╜╨╕╤В╨╡ ╨╜╨░╤Б╤В╤А╨╛╨╣╨║╨╕.', 'worldstat-ergonomics' ); ?></p>
					<?php endif; ?>
						<?php
						$criteria_fold_i = 0;
						foreach ( $macro_axes_six as $ax_key ) :
							++$criteria_fold_i;
							$ax_lab = isset( $macro_axis_labels[ $ax_key ] ) ? $macro_axis_labels[ $ax_key ] : $ax_key;
							$picked = [];
							foreach ( $macro_criteria_matrix as $sig => $axes_map ) {
								if ( ! empty( $axes_map[ $ax_key ] ) ) {
									$picked[] = $sig;
								}
							}
							sort( $picked, SORT_STRING );
							$rows_ax = isset( $macro_axis_resolved[ $ax_key ] ) ? $macro_axis_resolved[ $ax_key ] : [];
							?>
					<details class="wsergo-macro-crit" data-macro-axis="<?php echo esc_attr( $ax_key ); ?>" <?php echo 1 === $criteria_fold_i ? 'open' : ''; ?>>
						<summary class="wsergo-macro-crit__bar">
							<span class="dashicons dashicons-arrow-right-alt2 wsergo-macro-crit__chev" aria-hidden="true"></span>
							<span class="wsergo-macro-crit__title"><?php echo esc_html( $ax_lab ); ?></span>
							<span class="wsergo-macro-crit__e">
								<label>
									<span class="description"><?php esc_html_e( '╨Т╨╡╤Б ╨║╤А╨╕╤В╨╡╤А╨╕╤П ╨▓ E', 'worldstat-ergonomics' ); ?></span>
									<input type="text" class="small-text" name="<?php echo esc_attr( WSErgo_Settings::OPTION_MACRO_E_AXIS_WEIGHTS ); ?>[<?php echo esc_attr( $ax_key ); ?>]" value="<?php echo esc_attr( (string) ( $macro_e_w[ $ax_key ] ?? '' ) ); ?>" inputmode="decimal" onclick="event.stopPropagation();" onkeydown="event.stopPropagation();" />
								</label>
							</span>
							<span class="description wsergo-macro-sum-line">
								<span class="description"><?php esc_html_e( '╬г ╨▓╨╡╤Б╨╛╨▓ ╨┐╨░╤А╨░╨╝╨╡╤В╤А╨╛╨▓ (╨┐╨╛╤Б╨╗╨╡ ╤А╨░╤Б╨┐╤А╨╡╨┤╨╡╨╗╨╡╨╜╨╕╤П)', 'worldstat-ergonomics' ); ?></span>
								<strong class="wsergo-macro-sum-total" data-macro-axis="<?php echo esc_attr( $ax_key ); ?>"><?php echo esc_html( sprintf( '%.4f', array_sum( array_map( static function ( $r ) { return (float) ( $r['weight'] ?? 0 ); }, $rows_ax ) ) ) ); ?></strong>
							</span>
						</summary>
						<div style="padding:0 14px 16px 14px;">
							<?php if ( count( $picked ) === 0 ) : ?>
								<p class="description"><?php esc_html_e( '╨Э╨╡╤В ╨╛╤В╨╝╨╡╤З╨╡╨╜╨╜╤Л╤Е ╨┐╨░╤А╨░╨╝╨╡╤В╤А╨╛╨▓ тАФ ╨┤╨╗╤П ╤Н╤В╨╛╨│╨╛ ╨║╤А╨╕╤В╨╡╤А╨╕╤П ╨┐╨╛╨┤╤Б╤В╨░╨▓╨╗╤П╨╡╤В╤Б╤П ╨▓╤Б╤В╤А╨╛╨╡╨╜╨╜╨░╤П ╨╝╨╡╤В╨╛╨┤╨╕╨║╨░ (╨╜╨╕╨╢╨╡ ╤Б╨╛╤Б╤В╨░╨▓ ╨┐╨╛ ╤Г╨╝╨╛╨╗╤З╨░╨╜╨╕╤О).', 'worldstat-ergonomics' ); ?></p>
								<?php if ( ! empty( $rows_ax ) ) : ?>
								<table class="widefat striped wsergo-macro-crit-table" style="margin-top:8px;">
									<colgroup>
										<col class="col-macro-sig" />
										<col class="col-macro-w" />
										<col class="col-macro-inv" />
										<col class="col-macro-ex" />
									</colgroup>
									<thead>
										<tr>
											<th class="col-macro-sig"><?php esc_html_e( '╨Я╤А╨╕╨╖╨╜╨░╨║', 'worldstat-ergonomics' ); ?></th>
											<th class="col-macro-w"><?php esc_html_e( '╨Ф╨╛╨╗╤П (╨╜╨╛╤А╨╝.)', 'worldstat-ergonomics' ); ?></th>
											<th class="col-macro-inv"><?php esc_html_e( '╨Ш╨╜╨▓╨╡╤А╤Б╨╕╤П 1тИТx', 'worldstat-ergonomics' ); ?></th>
											<th class="col-macro-ex"><?php esc_html_e( '╨Я╤А╨╕╨╝╨╡╤А ╨┤╨░╨╜╨╜╤Л╤Е', 'worldstat-ergonomics' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $rows_ax as $rowt ) : ?>
											<?php
											$rsig = isset( $rowt['signal'] ) ? (string) $rowt['signal'] : '';
											$rlab = class_exists( 'WSErgo_Country_Macro_Calculator' ) ? WSErgo_Country_Macro_Calculator::data_label_ru( $rsig ) : $rsig;
											?>
										<tr>
											<td class="col-macro-sig"><?php echo esc_html( $rlab ); ?><br /><code class="description"><?php echo esc_html( $rsig ); ?></code></td>
											<td class="col-macro-w"><?php echo esc_html( sprintf( '%.4f', isset( $rowt['weight'] ) ? (float) $rowt['weight'] : 0.0 ) ); ?></td>
											<td class="col-macro-inv"><?php echo ! empty( $rowt['invert'] ) ? esc_html__( '╨┤╨░', 'worldstat-ergonomics' ) : esc_html__( '╨╜╨╡╤В', 'worldstat-ergonomics' ); ?></td>
											<td class="col-macro-ex"><code class="description"><?php echo esc_html( $this->format_macro_signal_example_display( $ref_macro_raw_row, $rsig ) ); ?></code></td>
										</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
								<?php endif; ?>
							<?php else : ?>
								<table class="widefat striped wsergo-macro-crit-table">
									<colgroup>
										<col class="col-macro-sig" />
										<col class="col-macro-w" />
										<col class="col-macro-inv" />
										<col class="col-macro-ex" />
									</colgroup>
									<thead>
										<tr>
											<th class="col-macro-sig"><?php esc_html_e( '╨Я╤А╨╕╨╖╨╜╨░╨║', 'worldstat-ergonomics' ); ?></th>
											<th class="col-macro-w"><?php esc_html_e( '╨Т╨╡╤Б', 'worldstat-ergonomics' ); ?></th>
											<th class="col-macro-inv"><?php esc_html_e( '╨Ш╨╜╨▓╨╡╤А╤Б╨╕╤П 1тИТx', 'worldstat-ergonomics' ); ?></th>
											<th class="col-macro-ex"><?php esc_html_e( '╨Я╤А╨╕╨╝╨╡╤А ╨┤╨░╨╜╨╜╤Л╤Е', 'worldstat-ergonomics' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $picked as $psig ) : ?>
											<?php
											$row_match = null;
											foreach ( $rows_ax as $rr ) {
												if ( (string) ( $rr['signal'] ?? '' ) === $psig ) {
													$row_match = $rr;
													break;
												}
											}
											$w_stored = isset( $macro_criteria_w[ $psig ][ $ax_key ] ) ? (string) $macro_criteria_w[ $psig ][ $ax_key ] : '';
											$plab     = class_exists( 'WSErgo_Country_Macro_Calculator' ) ? WSErgo_Country_Macro_Calculator::data_label_ru( $psig ) : $psig;
											if ( isset( $macro_criteria_inv[ $psig ] ) && is_array( $macro_criteria_inv[ $psig ] ) && array_key_exists( $ax_key, $macro_criteria_inv[ $psig ] ) ) {
												$inv_checked = (bool) $macro_criteria_inv[ $psig ][ $ax_key ];
											} else {
												$inv_checked = $row_match && ! empty( $row_match['invert'] );
											}
											$inv_name = WSErgo_Settings::OPTION_MACRO_CRITERIA_INVERTS . '[' . $psig . '][' . $ax_key . ']';
											?>
										<tr>
											<td class="col-macro-sig">
												<strong><?php echo esc_html( $plab ); ?></strong>
												<br /><code class="description"><?php echo esc_html( $psig ); ?></code>
											</td>
											<td class="col-macro-w">
												<input type="text" class="small-text wsergo-macro-w-input" data-macro-signal="<?php echo esc_attr( $psig ); ?>" name="<?php echo esc_attr( WSErgo_Settings::OPTION_MACRO_CRITERIA_WEIGHTS ); ?>[<?php echo esc_attr( $psig ); ?>][<?php echo esc_attr( $ax_key ); ?>]" value="<?php echo esc_attr( $w_stored ); ?>" inputmode="decimal" placeholder="<?php esc_attr_e( '╨░╨▓╤В╨╛', 'worldstat-ergonomics' ); ?>" autocomplete="off" />
											</td>
											<td class="col-macro-inv">
												<input type="hidden" name="<?php echo esc_attr( $inv_name ); ?>" value="0" />
												<label><input type="checkbox" name="<?php echo esc_attr( $inv_name ); ?>" value="1" <?php checked( $inv_checked ); ?> /> <?php esc_html_e( '1тИТx', 'worldstat-ergonomics' ); ?></label>
											</td>
											<td class="col-macro-ex"><code class="description"><?php echo esc_html( $this->format_macro_signal_example_display( $ref_macro_raw_row, $psig ) ); ?></code></td>
										</tr>
										<?php endforeach; ?>
									</tbody>
									<tfoot>
										<tr>
											<td class="description col-macro-sig"><?php esc_html_e( '╬г ╨▓╨╡╤Б╨╛╨▓ (╨┐╨╛╤Б╨╗╨╡ ╤А╨░╤Б╨┐╤А╨╡╨┤╨╡╨╗╨╡╨╜╨╕╤П)', 'worldstat-ergonomics' ); ?></td>
											<td class="col-macro-w"><strong class="wsergo-macro-sum-total" data-macro-axis="<?php echo esc_attr( $ax_key ); ?>"><?php echo esc_html( sprintf( '%.4f', array_sum( array_map( static function ( $r ) { return (float) ( $r['weight'] ?? 0 ); }, $rows_ax ) ) ) ); ?></strong></td>
											<td class="col-macro-inv"></td>
											<td class="col-macro-ex"></td>
										</tr>
									</tfoot>
								</table>
							<?php endif; ?>
						</div>
					</details>
						<?php endforeach; ?>
					<?php else : ?>
						<h3><?php esc_html_e( '╨Ь╨░╨║╤А╨╛: ╤И╨╡╤Б╤В╤М ╨║╤А╨╕╤В╨╡╤А╨╕╨╡╨▓ ╤Б╤В╤А╨░╨╜╨╛╨▓╨╛╨│╨╛ ╨╕╨╜╨┤╨╡╨║╤Б╨░', 'worldstat-ergonomics' ); ?></h3>
						<div class="notice notice-warning inline" style="margin:10px 0;padding:12px;">
							<p style="margin:0;">
								<?php esc_html_e( '╨н╤В╨╛╤В ╨▒╨╗╨╛╨║ ╤Б╨║╤А╤Л╤В: ╨╜╨╡╤В ╨┐╤А╨╕╨╖╨╜╨░╨║╨╛╨▓ ╨┤╨╗╤П ╨╝╨░╨║╤А╨╛ (╨╜╨╕ ╤Б╤В╨╛╨╗╨▒╤Ж╨╛╨▓ ╨╕╨╖ CSV, ╨╜╨╕ ╨┤╨╛╨┐╨╛╨╗╨╜╨╕╤В╨╡╨╗╤М╨╜╤Л╤Е ╨║╨╗╤О╤З╨╡╨╣, ╨╜╨╕ ╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╤М╤Б╨║╨╛╨│╨╛ ╨║╨░╨╗╤М╨║╤Г╨╗╤П╤В╨╛╤А╨░ ╤Б╤В╤А╨░╨╜╤Л). ╨Ч╨░╨┤╨░╨╣╤В╨╡ ╨╕╤Б╤В╨╛╤З╨╜╨╕╨║╨╕ ╨╜╨░ ╨▓╨║╨╗╨░╨┤╨║╨╡ ┬л╨Ф╨░╨╜╨╜╤Л╨╡┬╗ ╨╕╨╗╨╕ ╨╖╨░╨│╤А╤Г╨╖╨╕╤В╨╡ CSV.', 'worldstat-ergonomics' ); ?>
							</p>
							<p style="margin:.65em 0 0;">
								<?php esc_html_e( '╨Я╨╛╤Б╨╗╨╡ ╨┐╨╛╤П╨▓╨╗╨╡╨╜╨╕╤П ╨║╨╗╤О╤З╨╡╨╣ ╨▓ ╤Б╨┐╨╕╤Б╨║╨╡ ┬л╨Ф╨░╨╜╨╜╤Л╨╡┬╗ ╨╛╤В╨╝╨╡╤В╤М╤В╨╡ ╨┐╨░╤А╨░╨╝╨╡╤В╤А╤Л ╨▓ ╨╝╨░╤В╤А╨╕╤Ж╨╡ ╨╕ ╤Б╨╛╤Е╤А╨░╨╜╨╕╤В╨╡ ╨╜╨░╤Б╤В╤А╨╛╨╣╨║╨╕ тАФ ╨╖╨┤╨╡╤Б╤М ╨┐╨╛╤П╨▓╤П╤В╤Б╤П ╤А╨╡╨┤╨░╨║╤В╨╕╤А╤Г╨╡╨╝╤Л╨╡ ╨▓╨╡╤Б╨░.', 'worldstat-ergonomics' ); ?>
								<?php if ( current_user_can( 'manage_options' ) ) : ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=worldstat-csv' ) ); ?>"><?php esc_html_e( '╨Ф╨░╨╜╨╜╤Л╨╡ CSV', 'worldstat-ergonomics' ); ?></a>
								<?php endif; ?>
								&nbsp;┬╖&nbsp;
								<a href="#tab-data" class="wsergo-tab-deep-link"><?php esc_html_e( '╨Т╨║╨╗╨░╨┤╨║╨░ ┬л╨Ф╨░╨╜╨╜╤Л╨╡┬╗', 'worldstat-ergonomics' ); ?></a>
							</p>
						</div>
					<?php endif; ?>

				</div>

			</div><!-- #wsergo-panel-country -->

			<div id="wsergo-panel-city" class="wsergo-scope-panel" style="display:none;">
				<div class="notice notice-info inline" style="margin:12px 0;padding:12px;">
					<p style="margin:.4em 0;"><strong><?php esc_html_e( '╨Т╨║╨╗╨░╨┤╨║╨╕', 'worldstat-ergonomics' ); ?></strong>
						тАФ <a href="#tab-city-data" class="wsergo-tab-deep-link"><?php esc_html_e( '╨┐╨╡╤А╨╡╨╣╤В╨╕ ╨║ ┬л╨Ф╨░╨╜╨╜╤Л╨╡┬╗', 'worldstat-ergonomics' ); ?></a>
					</p>
					<ol style="margin:.5em 0 .5em 1.2em;list-style:decimal;padding-left:1em;">
						<li><?php esc_html_e( '╨Ф╨░╨╜╨╜╤Л╨╡ тАФ ╨║╨░╨╗╤М╨║╤Г╨╗╤П╤В╨╛╤А ╨╕ ╨┐╨╛╨┤╨┐╨╕╤Б╨╕ ╨╝╨░╨║╤А╨╛-╨┐╤А╨╕╨╖╨╜╨░╨║╨╛╨▓ ╨┤╨╗╤П ╨│╨╛╤А╨╛╨┤╨░, ╨╝╨░╤В╤А╨╕╤Ж╨░ ╨╛╤В╨▒╨╛╤А╨░ ╨┐╨╛ ╤И╨╡╤Б╤В╨╕ ╨║╤А╨╕╤В╨╡╤А╨╕╤П╨╝, ╨▓╨╡╤А╤Б╨╕╤П ╨╝╨╡╤В╨╛╨┤╨╕╨║╨╕ ╨│╨╛╤А╨╛╨┤╨░, k-means, ╨╛╨┐╨╛╤А╨╜╤Л╨╣ ╨│╨╛╨┤; ╨╖╨░╤В╨╡╨╝ ╨╕╨╝╨┐╨╛╤А╤В, ╨░╨▓╤В╨╛╨╝╨░╤В╨╕╤З╨╡╤Б╨║╨╛╨╡ ╨┐╤А╨╡╨▓╤М╤О ╨╗╨╕╤Б╤В╨╛╨▓╨╛╨│╨╛ E ╨╕ ╨║╨░╤А╤В╨░ ╨┐╨╛╨╗╨╡╨╣ wsp_city тЖТ ╨┐╨╛╨║╨░╨╖╨░╤В╨╡╨╗╤М.', 'worldstat-ergonomics' ); ?></li>
						<li><?php esc_html_e( '╨д╨╛╤А╨╝╤Г╨╗╨░ тАФ ╨░╨║╤В╨╕╨▓╨╜╨░╤П ╨│╨╛╤А╨╛╨┤╤Б╨║╨░╤П ╨╝╨╛╨┤╨╡╨╗╤М DSL, ╨║╨╛╤Н╤Д╤Д╨╕╤Ж╨╕╨╡╨╜╤В╤Л k_*, ╤В╨░╨▒╨╗╨╕╤Ж╨░ ╨╝╨╛╨┤╨╡╨╗╨╡╨╣; ╨▓╨╡╤Б╨░ ╤И╨╡╤Б╤В╨╕ ╨╝╨░╨║╤А╨╛-╨║╤А╨╕╤В╨╡╤А╨╕╨╡╨▓ ╨│╨╛╤А╨╛╨┤╨░; ╨▓╨╜╨╕╨╖╤Г тАФ ╨│╨╗╨╛╨▒╨░╨╗╤М╨╜╤Л╨╡ ╨╛╨┐╤А╨╡╨┤╨╡╨╗╨╡╨╜╨╕╤П ╨╗╨╕╤Б╤В╨╛╨▓╤Л╤Е ╨┐╨╛╨║╨░╨╖╨░╤В╨╡╨╗╨╡╨╣ (╨╜╨╛╤А╨╝╨░╨╗╨╕╨╖╨░╤Ж╨╕╤П ╤Б╤Л╤А╤Л╤Е ╨╖╨╜╨░╤З╨╡╨╜╨╕╨╣).', 'worldstat-ergonomics' ); ?></li>
					</ol>
				</div>

				<h2 class="nav-tab-wrapper wsergo-city-inner-nav" style="margin-top:8px;">
					<a href="#tab-city-data" class="nav-tab nav-tab-active" data-city-tab="tab-city-data"><?php esc_html_e( '╨Ф╨░╨╜╨╜╤Л╨╡', 'worldstat-ergonomics' ); ?></a>
					<a href="#tab-city-formula" class="nav-tab" data-city-tab="tab-city-formula"><?php esc_html_e( '╨д╨╛╤А╨╝╤Г╨╗╨░', 'worldstat-ergonomics' ); ?></a>
				</h2>

				<div id="tab-city-data" class="wsergo-tab-panel wsergo-tab-panel-city">
					<h2><?php esc_html_e( '╨Ф╨░╨╜╨╜╤Л╨╡ (╨│╨╛╤А╨╛╨┤)', 'worldstat-ergonomics' ); ?></h2>
					<p class="description"><?php esc_html_e( '╨Ы╨╕╤Б╤В╨╛╨▓╨╛╨╣ ╨╕╨╜╨┤╨╡╨║╤Б E ╨┤╨╗╤П ╨╖╨░╨┐╨╕╤Б╨╡╨╣ ╨║╨░╤В╨░╨╗╨╛╨│╨░ wsp_city (╨▒╨╡╨╖ ╨║╨▓╨░╤А╤В╨░╨╗╨╛╨▓) ╤Б╤З╨╕╤В╨░╨╡╤В╤Б╤П ╨╕╨╖ ╤Б╤Л╤А╤Л╤Е ╨╖╨╜╨░╤З╨╡╨╜╨╕╨╣ ╨┐╨╛ ╨║╨░╤А╤В╨╡ ╨┐╨╛╨╗╨╡╨╣ ╨╜╨╕╨╢╨╡; ╨┐╨╛╨║╨░╨╖╨░╤В╨╡╨╗╨╕ ╨╜╨╛╤А╨╝╨░╨╗╨╕╨╖╤Г╤О╤В╤Б╤П ╨▓ 0тАУ100 ╨┐╨╛ ╤В╨░╨▒╨╗╨╕╤Ж╨╡ ╨╜╨░ ╨▓╨║╨╗╨░╨┤╨║╨╡ ┬л╨д╨╛╤А╨╝╤Г╨╗╨░┬╗ ╨╕ ╤Б╨╛╨▒╨╕╤А╨░╤О╤В╤Б╤П ╨▓ ╤И╨╡╤Б╤В╤М ╨╕╨╖╨╝╨╡╤А╨╡╨╜╨╕╨╣, ╨╖╨░╤В╨╡╨╝ ╨▓ E ╨┐╨╛ ╨░╨║╤В╨╕╨▓╨╜╨╛╨╣ ╨╝╨╛╨┤╨╡╨╗╨╕.', 'worldstat-ergonomics' ); ?></p>
					<?php if ( ! class_exists( 'WSCities_CPT' ) ) : ?>
						<div class="notice notice-warning inline" style="margin:10px 0;padding:12px;">
							<p style="margin:0;"><?php esc_html_e( '╨Я╨╗╨░╨│╨╕╨╜ ┬л╨У╨╛╤А╨╛╨┤╨░┬╗ (worldstat-cities) ╨╜╨╡ ╨░╨║╤В╨╕╨▓╨╡╨╜: ╨╕╤Б╤В╨╛╤З╨╜╨╕╨║╨╕ ╨╝╨╡╤В╨░ ╨╕ Blocks & Roads ╨╜╨╡╨┤╨╛╤Б╤В╤Г╨┐╨╜╤Л.', 'worldstat-ergonomics' ); ?></p>
						</div>
					<?php endif; ?>

					<h3><?php esc_html_e( '╨Ъ╨░╨╗╤М╨║╤Г╨╗╤П╤В╨╛╤А ╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╤М╤Б╨║╨╕╤Е ╨┐╨░╤А╨░╨╝╨╡╤В╤А╨╛╨▓ (╨╝╨░╨║╤А╨╛ ╨│╨╛╤А╨╛╨┤╨░)', 'worldstat-ergonomics' ); ?></h3>
					<p class="description"><?php esc_html_e( '╨Я╤А╨╛╨╕╨╖╨▓╨╛╨┤╨╜╤Л╨╡ ╨┐╨╛╨║╨░╨╖╨░╤В╨╡╨╗╨╕ ╨╕╨╖ ╤Б╤В╨╛╨╗╨▒╤Ж╨╛╨▓ CSV ╨╕ ╨▓╤Б╤В╤А╨╛╨╡╨╜╨╜╤Л╤Е ╨║╨╗╤О╤З╨╡╨╣ ╨│╨╛╤А╨╛╨┤╨░. ╨Я╨╛ ╨║╨╜╨╛╨┐╨║╨╡ ┬л╨Ф╨╛╨▒╨░╨▓╨╕╤В╤М┬╗ ╨┐╤А╨░╨▓╨╕╨╗╨╛ ╤Б╨╛╤Е╤А╨░╨╜╤П╨╡╤В╤Б╤П ╨▓ ╨▒╨░╨╖╤Г ╤Б╤А╨░╨╖╤Г (╨║╨░╨║ ╤Г ╤Б╤В╤А╨░╨╜╤Л); ╨╛╤Б╤В╨░╨╗╤М╨╜╤Л╨╡ ╨┐╨╛╨╗╤П ╤Б╤В╤А╨░╨╜╨╕╤Ж╤Л тАФ ╨║╨╜╨╛╨┐╨║╨╛╨╣ ┬л╨б╨╛╤Е╤А╨░╨╜╨╕╤В╤М ╨╜╨░╤Б╤В╤А╨╛╨╣╨║╨╕┬╗ ╨▓╨╜╨╕╨╖╤Г. ╨г╤З╨░╤Б╤В╨▓╤Г╤О╤В ╨▓ ╨│╨╛╤А╨╛╨┤╤Б╨║╨╛╨╣ ╨╝╨░╤В╤А╨╕╤Ж╨╡ ╨╕ k-means.', 'worldstat-ergonomics' ); ?></p>
					<div class="wsergo-cm-panel wsergo-cm-panel-city" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;padding:12px;border:1px solid #c3c4c7;background:#fff;margin-bottom:10px;max-width:920px;border-radius:4px;">
						<div>
							<label for="wsergo-cm-key-a-city" class="screen-reader-text"><?php esc_html_e( '╨Я╨░╤А╨░╨╝╨╡╤В╤А A', 'worldstat-ergonomics' ); ?></label>
							<span class="description" style="display:block;margin-bottom:4px;"><?php esc_html_e( '╨Я╨░╤А╨░╨╝╨╡╤В╤А A', 'worldstat-ergonomics' ); ?></span>
							<input type="text" id="wsergo-cm-key-a-city" class="regular-text code" maxlength="96" autocomplete="off" placeholder="<?php esc_attr_e( '╨║╨╗╤О╤З ╤Б╤В╨╛╨╗╨▒╤Ж╨░', 'worldstat-ergonomics' ); ?>" />
						</div>
						<div>
							<label for="wsergo-cm-op-city" class="screen-reader-text"><?php esc_html_e( '╨Ю╨┐╨╡╤А╨░╤Ж╨╕╤П', 'worldstat-ergonomics' ); ?></label>
							<span class="description" style="display:block;margin-bottom:4px;"><?php esc_html_e( '╨Ю╨┐╨╡╤А╨░╤Ж╨╕╤П', 'worldstat-ergonomics' ); ?></span>
							<select id="wsergo-cm-op-city" style="min-width:11em;">
								<?php foreach ( $wsergo_cm_ops as $op_k => $op_lab ) : ?>
									<option value="<?php echo esc_attr( $op_k ); ?>"><?php echo esc_html( $op_lab ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div id="wsergo-cm-wrap-b-city">
							<label for="wsergo-cm-key-b-city" class="screen-reader-text"><?php esc_html_e( '╨Я╨░╤А╨░╨╝╨╡╤В╤А B', 'worldstat-ergonomics' ); ?></label>
							<span class="description" style="display:block;margin-bottom:4px;"><?php esc_html_e( '╨Я╨░╤А╨░╨╝╨╡╤В╤А B', 'worldstat-ergonomics' ); ?></span>
							<input type="text" id="wsergo-cm-key-b-city" class="regular-text code" maxlength="96" autocomplete="off" placeholder="<?php esc_attr_e( '╨║╨╗╤О╤З ╤Б╤В╨╛╨╗╨▒╤Ж╨░', 'worldstat-ergonomics' ); ?>" />
						</div>
						<div id="wsergo-cm-wrap-const-city" style="display:none;">
							<label for="wsergo-cm-const-city" class="screen-reader-text"><?php esc_html_e( '╨з╨╕╤Б╨╗╨╛', 'worldstat-ergonomics' ); ?></label>
							<span class="description" style="display:block;margin-bottom:4px;"><?php esc_html_e( '╨з╨╕╤Б╨╗╨╛', 'worldstat-ergonomics' ); ?></span>
							<input type="text" id="wsergo-cm-const-city" class="small-text" inputmode="decimal" value="0" />
						</div>
						<div style="flex:1;min-width:180px;">
							<label for="wsergo-cm-slug-city" class="screen-reader-text"><?php esc_html_e( '╨Ш╤В╨╛╨│╨╛╨▓╤Л╨╣ ╨║╨╗╤О╤З', 'worldstat-ergonomics' ); ?></label>
							<span class="description" style="display:block;margin-bottom:4px;"><?php esc_html_e( '╨Ш╤В╨╛╨│╨╛╨▓╤Л╨╣ ╨║╨╗╤О╤З', 'worldstat-ergonomics' ); ?></span>
							<input type="text" id="wsergo-cm-slug-city" class="regular-text code" maxlength="96" autocomplete="off" placeholder="<?php esc_attr_e( '╨╜╨░╨┐╤А. my_ratio', 'worldstat-ergonomics' ); ?>" />
						</div>
						<div>
							<button type="button" id="wsergo-cm-add-city" class="button button-primary"><?php esc_html_e( '╨Ф╨╛╨▒╨░╨▓╨╕╤В╤М', 'worldstat-ergonomics' ); ?></button>
						</div>
					</div>
					<?php if ( $wsergo_cm_cnt_city > 0 ) : ?>
					<div style="max-width:920px;margin-bottom:10px;overflow:auto;border:1px solid #c3c4c7;background:#fff;border-radius:4px;">
						<table class="widefat striped" style="margin:0;">
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( '╨Ш╤В╨╛╨│╨╛╨▓╤Л╨╣ ╨║╨╗╤О╤З', 'worldstat-ergonomics' ); ?></th>
									<th scope="col"><?php esc_html_e( '╨Ю╨┐╨╡╤А╨░╤Ж╨╕╤П', 'worldstat-ergonomics' ); ?></th>
									<th scope="col"><?php esc_html_e( 'A', 'worldstat-ergonomics' ); ?></th>
									<th scope="col"><?php esc_html_e( 'B / тАФ', 'worldstat-ergonomics' ); ?></th>
									<th scope="col" style="white-space:nowrap;"><?php esc_html_e( '╨з╨╕╤Б╨╗╨╛', 'worldstat-ergonomics' ); ?></th>
									<th scope="col" style="width:9em;"><?php esc_html_e( '╨Ф╨╡╨╣╤Б╤В╨▓╨╕╨╡', 'worldstat-ergonomics' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $wsergo_city_custom_metrics_saved as $cm_row_rule ) : ?>
									<?php
									if ( ! is_array( $cm_row_rule ) ) {
										continue;
									}
									$rs = sanitize_key( (string) ( $cm_row_rule['slug'] ?? '' ) );
									if ( $rs === '' ) {
										continue;
									}
									$rop = sanitize_key( (string) ( $cm_row_rule['op'] ?? '' ) );
									$op_show = isset( $wsergo_cm_ops[ $rop ] ) ? $wsergo_cm_ops[ $rop ] : $rop;
									$rka = isset( $cm_row_rule['key_a'] ) ? (string) $cm_row_rule['key_a'] : '';
									$rkb = isset( $cm_row_rule['key_b'] ) ? (string) $cm_row_rule['key_b'] : '';
									$rcn = isset( $cm_row_rule['const'] ) && is_numeric( $cm_row_rule['const'] ) ? (float) $cm_row_rule['const'] : 0.0;
									$wsergo_cm_del_url_rule = wp_nonce_url(
										admin_url( 'admin-post.php?action=wsergo_delete_city_custom_metric&slug=' . rawurlencode( $rs ) ),
										'wsergo_delete_city_custom_metric'
									);
									?>
								<tr>
									<td><code><?php echo esc_html( $rs ); ?></code></td>
									<td><?php echo esc_html( $op_show ); ?></td>
									<td><code><?php echo esc_html( $rka ); ?></code></td>
									<td><code><?php echo esc_html( $rkb !== '' ? $rkb : 'тАФ' ); ?></code></td>
									<td><?php echo esc_html( (string) $rcn ); ?></td>
									<td>
										<a href="<?php echo esc_url( $wsergo_cm_del_url_rule ); ?>" class="button button-small" onclick="return confirm('<?php echo esc_js( __( '╨г╨┤╨░╨╗╨╕╤В╤М ╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╤М╤Б╨║╨╕╨╣ ╨┐╨░╤А╨░╨╝╨╡╤В╤А ╨╕ ╨╡╨│╨╛ ╤Д╨╛╤А╨╝╤Г╨╗╤Г? ╨Ю╤В╨╝╨╡╤В╨║╨╕ ╨▓ ╨╝╨░╤В╤А╨╕╤Ж╨╡ ╨╕ ╨┐╨╛╨┤╨┐╨╕╤Б╤М ╨▒╤Г╨┤╤Г╤В ╤Б╨▒╤А╨╛╤И╨╡╨╜╤Л.', 'worldstat-ergonomics' ) ); ?>');"><?php esc_html_e( '╨г╨┤╨░╨╗╨╕╤В╤М', 'worldstat-ergonomics' ); ?></a>
									</td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<?php endif; ?>
					<p class="description" style="margin-top:0;">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: number of saved city custom metric rules. */
								_n( '╨б╨╛╤Е╤А╨░╨╜╨╡╨╜╨╛ ╨┐╤А╨░╨▓╨╕╨╗ ╨▓ ╨║╨░╨╗╤М╨║╤Г╨╗╤П╤В╨╛╤А╨╡ ╨│╨╛╤А╨╛╨┤╨░: %d.', '╨б╨╛╤Е╤А╨░╨╜╨╡╨╜╨╛ ╨┐╤А╨░╨▓╨╕╨╗ ╨▓ ╨║╨░╨╗╤М╨║╤Г╨╗╤П╤В╨╛╤А╨╡ ╨│╨╛╤А╨╛╨┤╨░: %d.', $wsergo_cm_cnt_city, 'worldstat-ergonomics' ),
								(int) $wsergo_cm_cnt_city
							)
						);
						?>
					</p>
					<input type="hidden" id="wsergo-cm-opt-name-city" value="<?php echo esc_attr( $wsergo_cm_opt_city ); ?>" />
					<p class="description"><?php esc_html_e( '╨Я╨╛╤А╤П╨┤╨╛╨║ ╨┤╨╛╨▒╨░╨▓╨╗╨╡╨╜╨╕╤П ╨▓╨░╨╢╨╡╨╜: ╨▓ ╤Б╨╗╨╡╨┤╤Г╤О╤Й╨╡╨╝ ╨┐╤А╨░╨▓╨╕╨╗╨╡ ╨╝╨╛╨╢╨╜╨╛ ╤Б╤Б╤Л╨╗╨░╤В╤М╤Б╤П ╨╜╨░ ╨║╨╗╤О╤З ╨╕╨╖ ╨┐╤А╨╡╨┤╤Л╨┤╤Г╤Й╨╡╨│╨╛.', 'worldstat-ergonomics' ); ?></p>
					<hr />
					<h3><?php esc_html_e( '╨Я╨╛╨┤╨┐╨╕╤Б╨╕ ╨║ ╨┤╨░╨╜╨╜╤Л╨╝ ╨│╨╛╤А╨╛╨┤╨░ (╤А╤Г╤Б╤Б╨║╨╕╨╣)', 'worldstat-ergonomics' ); ?></h3>
					<?php if ( ! $wsp_csv_has_datasets && class_exists( 'WorldStat_Uploaded_Csv' ) && empty( $data_label_keys_city ) ) : ?>
						<div class="notice notice-warning inline" style="margin:10px 0;padding:10px 12px;">
							<p style="margin:0;">
								<?php esc_html_e( '╨Т ╤Е╤А╨░╨╜╨╕╨╗╨╕╤Й╨╡ ╨┐╨╗╨░╤В╤Д╨╛╤А╨╝╤Л ╨╜╨╡╤В ╨╝╨░╨║╤А╨╛-CSV ╨╕ ╨╜╨╡ ╨╖╨░╨┤╨░╨╜╤Л ╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╤М╤Б╨║╨╕╨╡ ╨┐╨░╤А╨░╨╝╨╡╤В╤А╤Л ╨│╨╛╤А╨╛╨┤╨░: ╤Б╨┐╨╕╤Б╨╛╨║ ╨║╨╗╤О╤З╨╡╨╣ ╨┐╤Г╤Б╤В. ╨Ф╨╛╨▒╨░╨▓╤М╤В╨╡ ╨╜╨░╨▒╨╛╤А╤Л (╤З╨░╤Б╤В╨╛ тАФ ╤Б ╨▓╨║╨╗╨░╨┤╨║╨╕ ┬л╨Ф╨░╨╜╨╜╤Л╨╡┬╗ ╨│╨╛╤А╨╛╨┤╨░ ╨╖╨┤╨╡╤Б╤М ╨╢╨╡) ╨╕╨╗╨╕ ╤Б╤В╤А╨╛╨║╨╕ ╨▓ ╨║╨░╨╗╤М╨║╤Г╨╗╤П╤В╨╛╤А╨╡ ╨▓╤Л╤И╨╡.', 'worldstat-ergonomics' ); ?>
							</p>
							<p style="margin:.65em 0 0;">
								<a href="#tab-city-data" class="wsergo-tab-deep-link"><?php esc_html_e( '╨Т╨║╨╗╨░╨┤╨║╨░ ┬л╨Ф╨░╨╜╨╜╤Л╨╡┬╗ ╨│╨╛╤А╨╛╨┤╨░', 'worldstat-ergonomics' ); ?></a>
								<?php if ( current_user_can( 'manage_options' ) ) : ?>
									&nbsp;┬╖&nbsp;
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=worldstat-csv' ) ); ?>"><?php esc_html_e( '╨Ъ╨░╤В╨░╨╗╨╛╨│ ┬л╨Ф╨░╨╜╨╜╤Л╨╡ CSV┬╗', 'worldstat-ergonomics' ); ?></a>
								<?php endif; ?>
							</p>
						</div>
					<?php endif; ?>
					<p class="description"><?php esc_html_e( '╨Ъ╨╗╤О╤З╨╕ тАФ ╤Б╤В╨╛╨╗╨▒╤Ж╤Л CSV, ╨┤╨╛╨┐╨╛╨╗╨╜╨╕╤В╨╡╨╗╤М╨╜╤Л╨╡ ╨║╨╗╤О╤З╨╕ ╨▒╨╗╨╛╨║╨░ ╨╜╨╕╨╢╨╡, ╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╤М╤Б╨║╨╕╨╡ ╨┐╨░╤А╨░╨╝╨╡╤В╤А╤Л ╨║╨░╨╗╤М╨║╤Г╨╗╤П╤В╨╛╤А╨░ ╨│╨╛╤А╨╛╨┤╨░.', 'worldstat-ergonomics' ); ?></p>
					<div style="max-height:340px;overflow:auto;border:1px solid #c3c4c7;padding:10px;background:#fff;margin-bottom:12px;">
						<table class="widefat striped" style="margin:0;">
							<thead>
								<tr>
									<th scope="col" style="width:38%;"><?php esc_html_e( '╨Ъ╨╗╤О╤З ╨▓ ╨┤╨░╨╜╨╜╤Л╤Е', 'worldstat-ergonomics' ); ?></th>
									<th scope="col"><?php esc_html_e( '╨Ъ╨░╨║ ╨┐╨╛╨║╨░╨╖╤Л╨▓╨░╤В╤М ╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╤О', 'worldstat-ergonomics' ); ?></th>
								</tr>
							</thead>
							<tbody>
							<?php foreach ( $data_label_keys_city as $lk_c ) : ?>
								<?php $ov_c = isset( $data_labels_city_saved[ $lk_c ] ) ? $data_labels_city_saved[ $lk_c ] : ''; ?>
								<tr>
									<td><code><?php echo esc_html( $lk_c ); ?></code></td>
									<td>
										<input type="text" class="widefat" name="<?php echo esc_attr( WSErgo_Settings::OPTION_CITY_DATA_LABELS_RU ); ?>[<?php echo esc_attr( $lk_c ); ?>]" value="<?php echo esc_attr( $ov_c ); ?>" placeholder="<?php esc_attr_e( '╨Т╨▓╨╡╨┤╨╕╤В╨╡ ╨╛╨▒╨╛╨╖╨╜╨░╤З╨╡╨╜╨╕╨╡ ╨╜╨░ ╤А╤Г╤Б╤Б╨║╨╛╨╝', 'worldstat-ergonomics' ); ?>" maxlength="240" />
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<hr />
					<h3><?php esc_html_e( '╨Ь╨░╤В╤А╨╕╤Ж╨░ ╨║╤А╨╕╤В╨╡╤А╨╕╨╡╨▓ ╨│╨╛╤А╨╛╨┤╨░ (╤В╨╛╨╗╤М╨║╨╛ ╨╛╤В╨▒╨╛╤А ╨┐╨░╤А╨░╨╝╨╡╤В╤А╨╛╨▓)', 'worldstat-ergonomics' ); ?></h3>
					<p class="description"><?php esc_html_e( '╨Ю╤В╨╝╨╡╤В╤М╤В╨╡ ╨┤╨╗╤П ╨║╨░╨╢╨┤╨╛╨│╨╛ ╨┐╨░╤А╨░╨╝╨╡╤В╤А╨░, ╨║ ╨║╨░╨║╨╕╨╝ ╨╕╨╖ ╤И╨╡╤Б╤В╨╕ ╨║╤А╨╕╤В╨╡╤А╨╕╨╡╨▓ ╨│╨╛╤А╨╛╨┤╤Б╨║╨╛╨│╨╛ ╨╝╨░╨║╤А╨╛-╨╕╨╜╨┤╨╡╨║╤Б╨░ ╨╛╨╜ ╨╛╤В╨╜╨╛╤Б╨╕╤В╤Б╤П. ╨Т╨╡╤Б╨░ ╨╜╨░ ╨▓╨║╨╗╨░╨┤╨║╨╡ ┬л╨д╨╛╤А╨╝╤Г╨╗╨░┬╗ ╨│╨╛╤А╨╛╨┤╨░.', 'worldstat-ergonomics' ); ?></p>
					<div class="wsergo-macro-matrix-wrap" style="max-height:420px;overflow:auto;border:1px solid #c3c4c7;background:#fff;margin-bottom:12px;">
						<table class="widefat striped wsergo-macro-matrix-table" style="margin:0;min-width:680px;border-collapse:separate;border-spacing:0;">
							<thead>
								<tr>
									<th scope="col" class="wsergo-macro-matrix-sticky-corner" style="min-width:220px;"><?php esc_html_e( '╨Я╨░╤А╨░╨╝╨╡╤В╤А', 'worldstat-ergonomics' ); ?></th>
									<?php foreach ( $macro_axes_six as $axk_c ) : ?>
										<?php
										$th_short_c = isset( $matrix_col_short_city[ $axk_c ] ) ? $matrix_col_short_city[ $axk_c ] : $axk_c;
										$th_full_c  = isset( $macro_axis_labels[ $axk_c ] ) ? $macro_axis_labels[ $axk_c ] : $axk_c;
										?>
										<th scope="col" class="wsergo-macro-matrix-sticky-head" style="text-align:center;min-width:52px;padding:8px 4px;" title="<?php echo esc_attr( $th_full_c ); ?>">
											<span class="description"><?php echo esc_html( $th_short_c ); ?></span>
											<br /><code style="font-size:10px;"><?php echo esc_html( $axk_c ); ?></code>
										</th>
									<?php endforeach; ?>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $signals_ui_city as $msig_c ) : ?>
								<tr>
									<td class="wsergo-macro-matrix-sticky-firstcol">
										<?php if ( isset( $wsergo_custom_slugs_flip_city[ $msig_c ] ) ) : ?>
											<?php
											$wsergo_city_cm_cb_label = __( '╨Ю╤В╨╝╨╡╤В╨╕╤В╤М ╨┤╨╗╤П ╤Г╨┤╨░╨╗╨╡╨╜╨╕╤П ╨╕╨╖ ╨║╨░╨╗╤М╨║╤Г╨╗╤П╤В╨╛╤А╨░', 'worldstat-ergonomics' );
											?>
											<label style="display:inline-block;margin:0 0 6px 0;cursor:pointer;" title="<?php echo esc_attr( $wsergo_city_cm_cb_label ); ?>">
												<input type="checkbox" class="wsergo-city-cm-bulk-cb" form="wsergo-bulk-delete-city-cm" name="slugs[]" value="<?php echo esc_attr( $msig_c ); ?>" aria-label="<?php echo esc_attr( $wsergo_city_cm_cb_label ); ?>" />
											</label>
											<?php
											$wsergo_cm_del_url_city = wp_nonce_url(
												admin_url( 'admin-post.php?action=wsergo_delete_city_custom_metric&slug=' . rawurlencode( $msig_c ) ),
												'wsergo_delete_city_custom_metric'
											);
											?>
											<a href="<?php echo esc_url( $wsergo_cm_del_url_city ); ?>" class="button button-small" style="margin:0 10px 6px 0;vertical-align:middle;" onclick="return confirm('<?php echo esc_js( __( '╨г╨┤╨░╨╗╨╕╤В╤М ╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╤М╤Б╨║╨╕╨╣ ╨┐╨░╤А╨░╨╝╨╡╤В╤А ╨╕ ╨╡╨│╨╛ ╤Д╨╛╤А╨╝╤Г╨╗╤Г? ╨Ю╤В╨╝╨╡╤В╨║╨╕ ╨▓ ╨╝╨░╤В╤А╨╕╤Ж╨╡ ╨╕ ╨┐╨╛╨┤╨┐╨╕╤Б╤М ╨▒╤Г╨┤╤Г╤В ╤Б╨▒╤А╨╛╤И╨╡╨╜╤Л.', 'worldstat-ergonomics' ) ); ?>');"><?php esc_html_e( '╨г╨┤╨░╨╗╨╕╤В╤М', 'worldstat-ergonomics' ); ?></a>
										<?php endif; ?>
										<strong><?php echo esc_html( $city_macro_label_ru( $msig_c ) ); ?></strong>
										<br /><code class="description"><?php echo esc_html( $msig_c ); ?></code>
									</td>
									<?php foreach ( $macro_axes_six as $axk_c2 ) : ?>
										<?php $m_on_c = ! empty( $city_macro_criteria_matrix[ $msig_c ][ $axk_c2 ] ); ?>
										<td style="text-align:center;vertical-align:middle;padding:6px 4px;">
											<input type="checkbox" name="<?php echo esc_attr( WSErgo_Settings::OPTION_CITY_MACRO_CRITERIA_MATRIX ); ?>[<?php echo esc_attr( $msig_c ); ?>][<?php echo esc_attr( $axk_c2 ); ?>]" value="1" <?php checked( $m_on_c ); ?> />
										</td>
									<?php endforeach; ?>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<p style="margin:8px 0 0 0;max-width:920px;">
						<label style="margin-right:12px;">
							<input type="checkbox" id="wsergo-city-bulk-cm-all" />
							<?php esc_html_e( '╨Ю╤В╨╝╨╡╤В╨╕╤В╤М ╨▓╤Б╨╡ ╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╤М╤Б╨║╨╕╨╡ ╨┐╨░╤А╨░╨╝╨╡╤В╤А╤Л ╨▓ ╨╝╨░╤В╤А╨╕╤Ж╨╡', 'worldstat-ergonomics' ); ?>
						</label>
						<button type="submit" form="wsergo-bulk-delete-city-cm" class="button">
							<?php esc_html_e( '╨г╨┤╨░╨╗╨╕╤В╤М ╨╛╤В╨╝╨╡╤З╨╡╨╜╨╜╤Л╨╡ ╨┐╨░╤А╨░╨╝╨╡╤В╤А╤Л', 'worldstat-ergonomics' ); ?>
						</button>
					</p>
					<hr />
					<table class="form-table">
						<tr>
							<th scope="row"><label for="wsergo_city_methodology_version"><?php esc_html_e( '╨Т╨╡╤А╤Б╨╕╤П ╨╝╨╡╤В╨╛╨┤╨╕╨║╨╕ (╨│╨╛╤А╨╛╨┤)', 'worldstat-ergonomics' ); ?></label></th>
							<td>
								<input name="<?php echo esc_attr( WSErgo_Settings::OPTION_CITY_METHODOLOGY_VERSION ); ?>" id="wsergo_city_methodology_version" type="text" value="<?php echo esc_attr( get_option( WSErgo_Settings::OPTION_CITY_METHODOLOGY_VERSION, '1.2' ) ); ?>" class="regular-text" />
								<p class="description"><?php esc_html_e( '╨а╨╡╨┤╨░╨║╤Ж╨╕╤П ╨╝╨╡╤В╨╛╨┤╨╕╨║╨╕ ╨╕╨╝╨╡╨╜╨╜╨╛ ╨┤╨╗╤П ╨│╨╛╤А╨╛╨┤╤Б╨║╨╛╨│╨╛ ╨▒╨╗╨╛╨║╨░ (╨╝╨░╨║╤А╨╛ ╨╕ ╨╗╨╕╤Б╤В╨╛╨▓╨╛╨╣ E ╨┐╤А╨╕ ╨╜╨╡╨╛╨▒╤Е╨╛╨┤╨╕╨╝╨╛╤Б╤В╨╕).', 'worldstat-ergonomics' ); ?></p>
							</td>
						</tr>
					</table>
					<hr />
					<h3><?php esc_html_e( '╨Я╤А╨╕╨╖╨╜╨░╨║╨╕ ╨┤╨╗╤П k-means (╨╝╨░╨║╤А╨╛ ╨│╨╛╤А╨╛╨┤╨░)', 'worldstat-ergonomics' ); ?></h3>
					<p class="description"><?php esc_html_e( '╨Т╨╡╨║╤В╨╛╤А ╨║╨╗╨░╤Б╤В╨╡╤А╨╕╨╖╨░╤Ж╨╕╨╕ ╨┤╨╗╤П ╨│╨╛╤А╨╛╨┤╤Б╨║╨╕╤Е ╨╝╨░╨║╤А╨╛-╨╜╨░╤Б╤В╤А╨╛╨╡╨║; ╨╜╨╡ ╨╝╨╡╨╜╤М╤И╨╡ ╨┤╨▓╤Г╤Е ╨┐╤А╨╕╨╖╨╜╨░╨║╨╛╨▓.', 'worldstat-ergonomics' ); ?></p>
					<p style="margin:.75em 0;">
						<button type="button" class="button button-primary wsergo-auto-tune-clusters" data-scope="city"><?php esc_html_e( '╨Р╨▓╤В╨╛╨┐╨╛╨┤╨▒╨╛╤А ╨┐╤А╨╕╨╖╨╜╨░╨║╨╛╨▓ ╨╕ k', 'worldstat-ergonomics' ); ?></button>
						<button type="button" class="button wsergo-auto-tune-clusters-save" data-scope="city"><?php esc_html_e( '╨Р╨▓╤В╨╛╨┐╨╛╨┤╨▒╨╛╤А ╨╕ ╤Б╨╛╤Е╤А╨░╨╜╨╕╤В╤М', 'worldstat-ergonomics' ); ?></button>
						<span class="wsergo-auto-tune-status wsp-muted" style="margin-left:8px;"></span>
					</p>
					<div id="wsergo-cluster-features-city" class="wsergo-cluster-features-host" data-scope="city" style="max-height:220px;overflow:auto;border:1px solid #c3c4c7;padding:10px;background:#fff;">
						<p class="wsp-muted wsergo-cluster-features-loading"><?php esc_html_e( '╨Ч╨░╨│╤А╤Г╨╖╨║╨░ ╤Б╨┐╨╕╤Б╨║╨░ ╨┐╤А╨╕╨╖╨╜╨░╨║╨╛╨▓тАж', 'worldstat-ergonomics' ); ?></p>
					</div>
					<div id="wsergo-cluster-tune-report-city" class="wsergo-cluster-tune-report" style="display:none;margin-top:10px;padding:10px;border:1px solid #c3c4c7;background:#fff;max-height:180px;overflow:auto;"></div>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="wsergo_city_macro_extra_signals"><?php esc_html_e( '╨Ф╨╛╨┐╨╛╨╗╨╜╨╕╤В╨╡╨╗╤М╨╜╤Л╨╡ ╨║╨╗╤О╤З╨╕ ╨┐╤А╨╕╨╖╨╜╨░╨║╨╛╨▓ (wide), ╨│╨╛╤А╨╛╨┤', 'worldstat-ergonomics' ); ?></label>
							</th>
							<td>
								<textarea id="wsergo_city_macro_extra_signals" name="<?php echo esc_attr( WSErgo_Settings::OPTION_CITY_MACRO_EXTRA_SIGNALS_TEXT ); ?>" rows="6" class="large-text code"><?php echo esc_textarea( $city_macro_extra_signals_text ); ?></textarea>
								<p class="description"><?php esc_html_e( '╨Я╨╛ ╨╛╨┤╨╜╨╛╨╝╤Г ╨╗╨░╤В╨╕╨╜╤Б╨║╨╛╨╝╤Г ╨║╨╗╤О╤З╤Г ╨▓ ╤Б╤В╤А╨╛╨║╨╡; ╨┤╨╛ 50 ╤Б╤В╤А╨╛╨║. ╨Ъ╨╗╤О╤З╨╕ ╨┐╨╛╤П╨▓╨╗╤П╤О╤В╤Б╤П ╨▓ ╤Б╨┐╨╕╤Б╨║╨╡ ╤З╨╡╨║╨▒╨╛╨║╤Б╨╛╨▓ ╨╕ ╨▓ allowlist ╨│╨╛╤А╨╛╨┤╨░.', 'worldstat-ergonomics' ); ?></p>
							</td>
						</tr>
					</table>
					<hr />
					<h3><?php esc_html_e( '╨а╨╡╨╢╨╕╨╝ ╤А╨░╤Б╤З╤С╤В╨░ ╨┐╨╛ ╨╝╨░╨║╤А╨╛ (╨│╨╛╤А╨╛╨┤)', 'worldstat-ergonomics' ); ?></h3>
					<p class="description"><?php esc_html_e( '╨Ю╨┐╨╛╤А╨╜╤Л╨╣ ╨│╨╛╨┤ ╨╕ k ╨╛╤В╨╜╨╛╤Б╤П╤В╤Б╤П ╨║ ╨│╨╛╤А╨╛╨┤╤Б╨║╨╕╨╝ ╨╝╨░╨║╤А╨╛-╨╜╨░╤Б╤В╤А╨╛╨╣╨║╨░╨╝. ╨Я╤А╨╕╨╝╨╡╤А ╤Б╤Л╤А╨╛╨│╨╛ ╤А╤П╨┤╨░ ╨┤╨╗╤П ╨║╨╛╨╗╨╛╨╜╨║╨╕ ┬л╨Я╤А╨╕╨╝╨╡╤А ╨┤╨░╨╜╨╜╤Л╤Е┬╗ ╨╜╨░ ┬л╨д╨╛╤А╨╝╤Г╨╗╨░┬╗ ╨▒╨╡╤А╤С╤В╤Б╤П ╨┐╨╛ ISO2 ╤Н╤В╨░╨╗╨╛╨╜╨╜╨╛╨╣ ╤Б╤В╤А╨░╨╜╤Л (╨▓╨║╨╗╨░╨┤╨║╨░ ┬л╨Ф╨░╨╜╨╜╤Л╨╡┬╗ ╤Б╤В╤А╨░╨╜╤Л) ╨╕╨╖ ╨║╤Н╤И╨░ ╨│╨╛╤А╨╛╨┤╤Б╨║╨╛╨│╨╛ ╨╝╨░╨║╤А╨╛-CSV ╨┐╨╛╤Б╨╗╨╡ ╨╖╨░╨│╤А╤Г╨╖╨║╨╕ ╨╜╨░╨▒╨╛╤А╨╛╨▓ ╨▓ ╤Е╤А╨░╨╜╨╕╨╗╨╕╤Й╨╡ ╨┐╨╗╨░╤В╤Д╨╛╤А╨╝╤Л (╨╖╨░╨│╤А╤Г╨╖╨║╨░ ╤Г ╨▓╨░╤Б ╨╝╨╛╨╢╨╡╤В ╨╕╨┤╤В╨╕ ╤Б ╨▓╨║╨╗╨░╨┤╨║╨╕ ┬л╨Ф╨░╨╜╨╜╤Л╨╡┬╗ ╨│╨╛╤А╨╛╨┤╨░ ╨╖╨┤╨╡╤Б╤М ╨╕╨╗╨╕ ╨╕╨╖ ╤А╨░╨╖╨┤╨╡╨╗╨░ ┬л╨Ф╨░╨╜╨╜╤Л╨╡ CSV┬╗).', 'worldstat-ergonomics' ); ?></p>
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( '╨Ш╤Б╤В╨╛╤З╨╜╨╕╨║ ╨╕╨╜╨┤╨╡╨║╤Б╨░ (╨│╨╛╤А╨╛╨┤)', 'worldstat-ergonomics' ); ?></th>
							<td>
								<input type="hidden" name="<?php echo esc_attr( WSErgo_Settings::OPTION_CITY_INDEX_SOURCE ); ?>" value="macro_datasets" />
								<p class="description"><?php esc_html_e( '╨Ш╤Б╨┐╨╛╨╗╤М╨╖╤Г╤О╤В╤Б╤П ╨╝╨░╨║╤А╨╛╨┤╨░╨╜╨╜╤Л╨╡ ╨╕╨╖ ╤Е╤А╨░╨╜╨╕╨╗╨╕╤Й╨░ ╨┐╨╗╨░╤В╤Д╨╛╤А╨╝╤Л (╨╖╨░╨│╤А╤Г╨╢╨╡╨╜╨╜╤Л╨╡ CSV); ╨╕╨╜╤В╨╡╤А╤Д╨╡╨╣╤Б ╨╖╨░╨│╤А╤Г╨╖╨║╨╕ ╨╝╨╛╨╢╨╡╤В ╨▒╤Л╤В╤М ╨╜╨░ ╨▓╨║╨╗╨░╨┤╨║╨╡ ┬л╨Ф╨░╨╜╨╜╤Л╨╡┬╗ ╨│╨╛╤А╨╛╨┤╨░ ╨╕╨╗╨╕ ╨▓ ╤А╨░╨╖╨┤╨╡╨╗╨╡ ┬л╨Ф╨░╨╜╨╜╤Л╨╡ CSV┬╗ тАФ ╨▓ ╨╖╨░╨▓╨╕╤Б╨╕╨╝╨╛╤Б╤В╨╕ ╨╛╤В ╨▓╨░╤И╨╡╨╣ ╤Б╨▒╨╛╤А╨║╨╕.', 'worldstat-ergonomics' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wsergo_city_macro_reference_year"><?php esc_html_e( '╨Ю╨┐╨╛╤А╨╜╤Л╨╣ ╨│╨╛╨┤ (╨╝╨░╨║╤А╨╛, ╨│╨╛╤А╨╛╨┤)', 'worldstat-ergonomics' ); ?></label></th>
							<td>
								<?php
								$mref_min_c = class_exists( 'WorldStat_Platform_Years' ) ? max( 1900, WorldStat_Platform_Years::min() ) : 1900;
								$mref_max_c = class_exists( 'WorldStat_Platform_Years' ) ? max( 2100, WorldStat_Platform_Years::max() ) : 2100;
								?>
								<input type="number" id="wsergo_city_macro_reference_year" name="<?php echo esc_attr( WSErgo_Settings::OPTION_CITY_MACRO_REFERENCE_YEAR ); ?>" value="<?php echo esc_attr( (string) $city_macro_year ); ?>" class="small-text" min="<?php echo esc_attr( (string) $mref_min_c ); ?>" max="<?php echo esc_attr( (string) $mref_max_c ); ?>" step="1" />
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wsergo_city_macro_k_clusters"><?php esc_html_e( '╨з╨╕╤Б╨╗╨╛ ╨║╨╗╨░╤Б╤В╨╡╤А╨╛╨▓ k-means (╨╝╨░╨║╤А╨╛, ╨│╨╛╤А╨╛╨┤)', 'worldstat-ergonomics' ); ?></label></th>
							<td>
								<input type="number" id="wsergo_city_macro_k_clusters" name="<?php echo esc_attr( WSErgo_Settings::OPTION_CITY_MACRO_K_CLUSTERS ); ?>" value="<?php echo esc_attr( (string) $city_macro_k ); ?>" class="small-text" min="2" max="12" step="1" />
							</td>
						</tr>
					</table>
					<hr />

					<?php if ( $city_import_opt_key !== '' ) : ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( '╨а╨░╤Б╤З╤С╤В E ╨┐╨╛ ╨┤╨░╨╜╨╜╤Л╨╝ ╨╕╨╝╨┐╨╛╤А╤В╨░', 'worldstat-ergonomics' ); ?></th>
							<td>
								<input type="hidden" name="<?php echo esc_attr( $city_import_opt_key ); ?>" value="0" />
								<label><input type="checkbox" name="<?php echo esc_attr( $city_import_opt_key ); ?>" value="1" <?php checked( $city_import_enabled ); ?> /> <?php esc_html_e( '╨Т╨║╨╗╤О╤З╨╕╤В╤М ╨┐╨╡╤А╨╡╤Б╤З╤С╤В ╨╕ ╤Б╨╛╤Е╤А╨░╨╜╨╡╨╜╨╕╨╡ wsergo_city_leaf_index ╨┤╨╗╤П ╨│╨╛╤А╨╛╨┤╨╛╨▓ ╨┐╤А╨╕ ╨╕╨╝╨┐╨╛╤А╤В╨╡ ╨╕ ╨┐╨╛ ╨║╨╜╨╛╨┐╨║╨╡ ╨▓ ╤А╨░╨╖╨┤╨╡╨╗╨╡ ┬л╨У╨╛╤А╨╛╨┤╨░┬╗.', 'worldstat-ergonomics' ); ?></label>
								<p class="description"><?php esc_html_e( '╨Э╨╕╨╢╨╡ тАФ ╤Б╤Л╤А╤Л╨╡ ╨╖╨╜╨░╤З╨╡╨╜╨╕╤П ╨╕ ╤А╨░╤Б╤З╤С╤В ╨┐╨╛ ╤В╨╡╨║╤Г╤Й╨╡╨╣ ╨║╨░╤А╤В╨╡ ╨┐╨╛╨╗╨╡╨╣ ╨╕ ╨┐╨╛╨║╨░╨╖╨░╤В╨╡╨╗╤П╨╝ (╨║╨░╨║ ╨┐╨╛╤Б╨╗╨╡ ┬л╨б╨╛╤Е╤А╨░╨╜╨╕╤В╤М ╨╜╨░╤Б╤В╤А╨╛╨╣╨║╨╕┬╗). ╨Я╤А╨╡╨▓╤М╤О ╤Б╤В╤А╨╛╨╕╤В╤Б╤П ╨┐╨╛ ╨┐╨╡╤А╨▓╨╛╨╝╤Г ╨╛╨┐╤Г╨▒╨╗╨╕╨║╨╛╨▓╨░╨╜╨╜╨╛╨╝╤Г ╨│╨╛╤А╨╛╨┤╤Г ╤Б ISO2 ╤Н╤В╨░╨╗╨╛╨╜╨╜╨╛╨╣ ╤Б╤В╤А╨░╨╜╤Л (╨▓╨║╨╗╨░╨┤╨║╨░ ┬л╨Ф╨░╨╜╨╜╤Л╨╡┬╗ ╤Б╤В╤А╨░╨╜╤Л); ╨╡╤Б╨╗╨╕ ╤В╨░╨║╨╛╨│╨╛ ╨│╨╛╤А╨╛╨┤╨░ ╨╜╨╡╤В тАФ ╨┐╨╛ ╨┐╨╡╤А╨▓╨╛╨╝╤Г ╨│╨╛╤А╨╛╨┤╤Г ╨▓ ╨║╨░╤В╨░╨╗╨╛╨│╨╡.', 'worldstat-ergonomics' ); ?></p>
							</td>
						</tr>
					</table>
					<?php endif; ?>

					<?php if ( $city_preview_post_id > 0 && $city_import_enabled ) : ?>
						<?php if ( $city_preview_title !== '' ) : ?>
							<p class="description"><strong><?php esc_html_e( '╨Я╤А╨╡╨▓╤М╤О', 'worldstat-ergonomics' ); ?>:</strong> <?php echo esc_html( $city_preview_title ); ?></p>
						<?php endif; ?>
						<?php if ( empty( $city_preview_raw ) ) : ?>
							<p class="description"><?php esc_html_e( '╨Э╨╡╤В ╤Б╤Л╤А╤Л╤Е ╨╖╨╜╨░╤З╨╡╨╜╨╕╨╣ ╨┐╨╛ ╨║╨░╤А╤В╨╡ ╨┐╨╛╨╗╨╡╨╣: ╨┐╤А╨╛╨▓╨╡╤А╤М╤В╨╡ ╤Б╤В╤А╨╛╨║╨╕ ╤В╨░╨▒╨╗╨╕╤Ж╤Л ╨╜╨╕╨╢╨╡ ╨╕ ╨╜╨░╨╗╨╕╤З╨╕╨╡ ╨╝╨╡╤В╤А╨╕╨║ ╤Г ╨│╨╛╤А╨╛╨┤╨░.', 'worldstat-ergonomics' ); ?></p>
						<?php else : ?>
							<table class="widefat striped" style="max-width:720px;margin-bottom:12px;">
								<thead>
									<tr>
										<th><?php esc_html_e( '╨Я╨╛╨║╨░╨╖╨░╤В╨╡╨╗╤М', 'worldstat-ergonomics' ); ?></th>
										<th><?php esc_html_e( '╨б╤Л╤А╨╛╨╡', 'worldstat-ergonomics' ); ?></th>
										<th><?php esc_html_e( '0тАУ100', 'worldstat-ergonomics' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $city_preview_raw as $ind_id => $rawv ) : ?>
										<?php
										$def_row = null;
										foreach ( $indicator_defs_stored as $d ) {
											if ( isset( $d['id'] ) && (string) $d['id'] === (string) $ind_id ) {
												$def_row = $d;
												break;
											}
										}
										$norm = $def_row ? WSErgo_Indicators::normalize_to_score( $def_row, (float) $rawv ) : null;
										?>
										<tr>
											<td><code><?php echo esc_html( (string) $ind_id ); ?></code></td>
											<td>
												<?php
												$rf = (float) $rawv;
												echo esc_html( is_finite( $rf ) ? (string) $rf : 'тАФ' );
												?>
											</td>
											<td><?php echo $norm !== null ? esc_html( (string) $norm ) : 'тАФ'; ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
							<?php if ( ! empty( $city_preview_scores ) ) : ?>
								<p class="description"><strong><?php esc_html_e( '╨Ю╤Б╨╕ (0тАУ100)', 'worldstat-ergonomics' ); ?>:</strong>
									<?php
									$bits = [];
									foreach ( $city_preview_scores as $dk => $sv ) {
										$lab = isset( $city_dim_labels[ $dk ] ) ? $city_dim_labels[ $dk ] : $dk;
										$bits[] = $lab . ' тЙИ ' . ( is_float( $sv ) ? sprintf( '%.2f', $sv ) : (string) $sv );
									}
									echo esc_html( implode( '; ', $bits ) );
									?>
								</p>
							<?php endif; ?>
							<p class="description"><strong><?php esc_html_e( '╨Ы╨╕╤Б╤В╨╛╨▓╨╛╨╣ E', 'worldstat-ergonomics' ); ?>:</strong> <?php echo $city_preview_e !== null ? esc_html( sprintf( '%.2f', $city_preview_e ) ) : 'тАФ'; ?></p>
						<?php endif; ?>
					<?php elseif ( $city_preview_post_id > 0 && ! $city_import_enabled ) : ?>
						<p class="description"><?php esc_html_e( '╨а╨░╤Б╤З╤С╤В ╨┐╨╛ ╨╕╨╝╨┐╨╛╤А╤В╤Г ╨╛╤В╨║╨╗╤О╤З╤С╨╜ тАФ ╨┐╤А╨╡╨▓╤М╤О ╨╜╨╡╨┤╨╛╤Б╤В╤Г╨┐╨╜╨╛.', 'worldstat-ergonomics' ); ?></p>
					<?php elseif ( $city_import_enabled && $city_preview_post_id <= 0 ) : ?>
						<p class="description"><?php esc_html_e( '╨Э╨╡╤В ╨╛╨┐╤Г╨▒╨╗╨╕╨║╨╛╨▓╨░╨╜╨╜╤Л╤Е ╨│╨╛╤А╨╛╨┤╨╛╨▓ ╨▓ ╨║╨░╤В╨░╨╗╨╛╨│╨╡ тАФ ╨┐╤А╨╡╨▓╤М╤О ╨╗╨╕╤Б╤В╨╛╨▓╨╛╨│╨╛ E ╨╜╨╡╨┤╨╛╤Б╤В╤Г╨┐╨╜╨╛.', 'worldstat-ergonomics' ); ?></p>
					<?php endif; ?>

					<hr />
					<h3><?php esc_html_e( '╨Ъ╨░╤А╤В╨░ ╨┐╨╛╨╗╨╡╨╣ ╨│╨╛╤А╨╛╨┤╨░ тЖТ ╨┐╨╛╨║╨░╨╖╨░╤В╨╡╨╗╤М', 'worldstat-ergonomics' ); ?></h3>
					<p class="description"><?php esc_html_e( '╨Ъ╨░╨╢╨┤╨░╤П ╤Б╤В╤А╨╛╨║╨░: ╨╕╤Б╤В╨╛╤З╨╜╨╕╨║ ╨┤╨░╨╜╨╜╤Л╤Е (╨╝╨╡╤В╨░ wsp_city ╨╕╨╗╨╕ ╨╝╨╡╤В╤А╨╕╨║╨░ Blocks & Roads) ╨╕ id ╨╗╨╕╤Б╤В╨╛╨▓╨╛╨│╨╛ ╨┐╨╛╨║╨░╨╖╨░╤В╨╡╨╗╤П (╨║╨░╨║ ╨▓ ╤В╨░╨▒╨╗╨╕╤Ж╨╡ ┬л╨д╨╛╤А╨╝╤Г╨╗╨░┬╗). ╨Я╤Г╤Б╤В╤Л╨╡ ╤Б╤В╤А╨╛╨║╨╕ ╨┐╤А╨╕ ╤Б╨╛╤Е╤А╨░╨╜╨╡╨╜╨╕╨╕ ╨╛╤В╨▒╤А╨░╤Б╤Л╨▓╨░╤О╤В╤Б╤П.', 'worldstat-ergonomics' ); ?></p>
					<div style="overflow:auto;border:1px solid #c3c4c7;background:#fff;padding:10px;margin-bottom:12px;">
						<table class="widefat striped" style="margin:0;min-width:520px;">
							<thead>
								<tr>
									<th><?php esc_html_e( '╨Ш╤Б╤В╨╛╤З╨╜╨╕╨║', 'worldstat-ergonomics' ); ?></th>
									<th><?php esc_html_e( 'Id ╨┐╨╛╨║╨░╨╖╨░╤В╨╡╨╗╤П', 'worldstat-ergonomics' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $city_field_map_rows as $cfi => $cfrow ) : ?>
									<tr>
										<td>
											<select class="widefat" name="<?php echo esc_attr( WSErgo_Settings::OPTION_CITY_FIELD_MAP ); ?>[<?php echo (int) $cfi; ?>][source]">
												<option value=""><?php esc_html_e( 'тАФ ╨▓╤Л╨▒╨╡╤А╨╕╤В╨╡ тАФ', 'worldstat-ergonomics' ); ?></option>
												<?php foreach ( $city_source_choices as $cv => $clab ) : ?>
													<option value="<?php echo esc_attr( $cv ); ?>" <?php selected( isset( $cfrow['source'] ) ? (string) $cfrow['source'] : '', $cv ); ?>><?php echo esc_html( $clab ); ?></option>
												<?php endforeach; ?>
											</select>
										</td>
										<td><input type="text" class="regular-text code" name="<?php echo esc_attr( WSErgo_Settings::OPTION_CITY_FIELD_MAP ); ?>[<?php echo (int) $cfi; ?>][indicator_id]" value="<?php echo esc_attr( isset( $cfrow['indicator_id'] ) ? (string) $cfrow['indicator_id'] : '' ); ?>" maxlength="96" autocomplete="off" /></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<?php if ( current_user_can( 'manage_options' ) && class_exists( 'WSCities_CPT' ) ) : ?>
						<p class="description"><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . WSCities_CPT::SLUG ) ); ?>"><?php esc_html_e( '╨Т╤Б╨╡ ╨│╨╛╤А╨╛╨┤╨░ (╨║╨░╤В╨░╨╗╨╛╨│)', 'worldstat-ergonomics' ); ?></a></p>
					<?php endif; ?>
				</div>

				<div id="tab-city-formula" class="wsergo-tab-panel wsergo-tab-panel-city" style="display:none;">
					<h2><?php esc_html_e( '╨д╨╛╤А╨╝╤Г╨╗╨░ (╨│╨╛╤А╨╛╨┤)', 'worldstat-ergonomics' ); ?></h2>
					<p class="description"><?php esc_html_e( '╨Ю╤В╨┤╨╡╨╗╤М╨╜╤Л╨╡ ╨╛╤В ╤Б╤В╤А╨░╨╜╤Л: ╨░╨║╤В╨╕╨▓╨╜╨░╤П ╨╝╨╛╨┤╨╡╨╗╤М DSL ╨┤╨╗╤П ╨╗╨╕╤Б╤В╨╛╨▓╨╛╨│╨╛ E ╨│╨╛╤А╨╛╨┤╨░, ╨║╨╛╤Н╤Д╤Д╨╕╤Ж╨╕╨╡╨╜╤В╤Л k_*, ╤В╨░╨▒╨╗╨╕╤Ж╨░ ╨╝╨╛╨┤╨╡╨╗╨╡╨╣ ╨╕ ╨▓╨╡╤Б╨░ ╤И╨╡╤Б╤В╨╕ ╨╝╨░╨║╤А╨╛-╨║╤А╨╕╤В╨╡╤А╨╕╨╡╨▓ (╨╜╨░╤Б╤В╤А╨╛╨╣╨║╨╕ ╨╛╨┐╤Ж╨╕╨╣ wsergo_city_*). ╨Э╨╕╨╢╨╡ тАФ ╨╛╨▒╤Й╨╕╨╡ ╨│╨╗╨╛╨▒╨░╨╗╤М╨╜╤Л╨╡ ╨╛╨┐╤А╨╡╨┤╨╡╨╗╨╡╨╜╨╕╤П ╨╗╨╕╤Б╤В╨╛╨▓╤Л╤Е ╨┐╨╛╨║╨░╨╖╨░╤В╨╡╨╗╨╡╨╣ (╨╜╨╛╤А╨╝╨░╨╗╨╕╨╖╨░╤Ж╨╕╤П ╤Б╤Л╤А╤Л╤Е ╨╖╨╜╨░╤З╨╡╨╜╨╕╨╣ ╨╕╨╖ ╨║╨░╤А╤В╤Л ╨┐╨╛╨╗╨╡╨╣).', 'worldstat-ergonomics' ); ?></p>
					<p class="description">
						<a href="#ergo-country" class="button button-secondary"><?php esc_html_e( '╨Ъ ╤Б╤В╤А╨░╨╜╨╡: ╨▓╨║╨╗╨░╨┤╨║╨╕', 'worldstat-ergonomics' ); ?></a>
						<a href="#tab-formula" class="button button-secondary wsergo-tab-deep-link"><?php esc_html_e( '╨Ъ ╤Б╤В╤А╨░╨╜╨╡: ╤Д╨╛╤А╨╝╤Г╨╗╨░ ╨╕ ╨╝╨░╨║╤А╨╛ ╤Б╤В╤А╨░╨╜╤Л', 'worldstat-ergonomics' ); ?></a>
					</p>

					<h3><?php esc_html_e( '╨Р╨║╤В╨╕╨▓╨╜╨░╤П ╨╝╨╛╨┤╨╡╨╗╤М ╤А╨░╤Б╤З╤С╤В╨░ E (╨│╨╛╤А╨╛╨┤)', 'worldstat-ergonomics' ); ?></h3>
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( '╨Ь╨╛╨┤╨╡╨╗╤М', 'worldstat-ergonomics' ); ?></th>
							<td>
								<select name="<?php echo esc_attr( WSErgo_Settings::OPTION_CITY_ACTIVE_MODEL ); ?>">
									<?php foreach ( $city_models as $m_c ) : ?>
										<option value="<?php echo esc_attr( $m_c['id'] ); ?>" <?php selected( $city_active_id, $m_c['id'] ); ?>><?php echo esc_html( $m_c['name'] ); ?></option>
									<?php endforeach; ?>
								</select>
								<?php if ( $city_active_model ) : ?>
									<p class="description"><?php echo esc_html( $city_active_model['leaf_formula'] !== '' ? $city_active_model['leaf_formula'] : __( '╨Я╤Г╤Б╤В╨░╤П ╤Д╨╛╤А╨╝╤Г╨╗╨░: ╨▓╨╖╨▓╨╡╤И╨╡╨╜╨╜╨╛╨╡ ╤Б╤А╨╡╨┤╨╜╨╡╨╡ ╨┐╨╛ ╤И╨╡╤Б╤В╨╕ ╨╕╨╖╨╝╨╡╤А╨╡╨╜╨╕╤П╨╝ ╨╛╨▒╤К╨╡╨║╤В╨░ (╨║╨░╨║ ╨╖╨░╨┤╨░╨╜╤Л ╨▓╨╡╤Б╨░ ╨▓ ╨║╨░╤А╤В╨╛╤З╨║╨╡ ╨║╨▓╨░╤А╤В╨░╨╗╨░/╨╖╨┤╨░╨╜╨╕╤П).', 'worldstat-ergonomics' ) ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					</table>
					<hr />
					<h3><?php esc_html_e( '╨Ъ╨╛╤Н╤Д╤Д╨╕╤Ж╨╕╨╡╨╜╤В╤Л k_* (╨│╨╛╤А╨╛╨┤)', 'worldstat-ergonomics' ); ?></h3>
					<p class="description"><?php esc_html_e( '╨Ь╨╜╨╛╨╢╨╕╤В╨╡╨╗╨╕ ╨▓ ╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╤М╤Б╨║╨╛╨╣ ╤Д╨╛╤А╨╝╤Г╨╗╨╡ ╤Б╨▓╨╛╨┤╨╜╨╛╨│╨╛ E ╨┐╨╛ ╨╛╨▒╤К╨╡╨║╤В╤Г ╨┤╨╗╤П ╨│╨╛╤А╨╛╨┤╤Б╨║╨╛╨╣ ╨╝╨╛╨┤╨╡╨╗╨╕.', 'worldstat-ergonomics' ); ?></p>
					<table class="form-table">
						<?php foreach ( $city_coeffs as $k_cc => $v_cc ) : ?>
						<tr>
							<th scope="row"><label><?php echo esc_html( $k_cc ); ?></label></th>
							<td><input type="text" name="<?php echo esc_attr( WSErgo_Settings::OPTION_CITY_COEFFICIENTS ); ?>[<?php echo esc_attr( $k_cc ); ?>]" value="<?php echo esc_attr( (string) $v_cc ); ?>" class="small-text" /></td>
						</tr>
						<?php endforeach; ?>
					</table>
					<hr />
					<h3><?php esc_html_e( '╨Ь╨╛╨┤╨╡╨╗╨╕ ╨╕ DSL (╨│╨╛╤А╨╛╨┤)', 'worldstat-ergonomics' ); ?></h3>
					<p class="description">
						<?php esc_html_e( '╨Я╤А╨╛╤Б╤В╨╛╨╣ ╤А╨╡╨╢╨╕╨╝: ╨╛╤Б╤В╨░╨▓╤М╤В╨╡ ╤Д╨╛╤А╨╝╤Г╨╗╤Г ╨┐╤Г╤Б╤В╨╛╨╣ тАФ ╤Б╨▓╨╛╨┤╨╜╤Л╨╣ E ╤Б╤З╨╕╤В╨░╨╡╤В╤Б╤П ╨║╨░╨║ ╨▓╨╖╨▓╨╡╤И╨╡╨╜╨╜╨╛╨╡ ╤Б╤А╨╡╨┤╨╜╨╡╨╡ ╨┐╨╛ ╤И╨╡╤Б╤В╨╕ ╨╛╤Б╤П╨╝ ╨╛╨▒╤К╨╡╨║╤В╨░. ╨б╨▓╨╛╤П ╤Д╨╛╤А╨╝╤Г╨╗╨░: ╨╛╤Б╨╕, k_*, i_* ╨╕ w_*.', 'worldstat-ergonomics' ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( '╨Ф╨╛╨┐╤Г╤Б╤В╨╕╨╝╤Л╨╡ ╨╕╨┤╨╡╨╜╤В╨╕╤Д╨╕╨║╨░╤В╨╛╤А╤Л:', 'worldstat-ergonomics' ); ?>
						<code><?php echo esc_html( $city_allowed_txt ); ?></code>
					</p>
					<p>
						<label for="wsergo_test_formula_inline_city"><?php esc_html_e( '╨Я╤А╨╛╨▓╨╡╤А╨║╨░ ╤Д╨╛╤А╨╝╤Г╨╗╤Л ╨│╨╛╤А╨╛╨┤╨░ (╤В╨╡╤Б╤В╨╛╨▓╤Л╨╡ 50 ╨┐╨╛ ╨▓╤Б╨╡╨╝ ╨╛╤Б╤П╨╝)', 'worldstat-ergonomics' ); ?></label><br />
						<textarea id="wsergo_test_formula_inline_city" class="large-text" rows="2" placeholder="<?php esc_attr_e( '╨Ю╤Б╤В╨░╨▓╤М╤В╨╡ ╨┐╤Г╤Б╤В╤Л╨╝ ╨╕╨╗╨╕ ╨▓╤Б╤В╨░╨▓╤М╤В╨╡ ╨▓╤Л╤А╨░╨╢╨╡╨╜╨╕╨╡ ╨┐╨╛ ╤Б╨┐╨╕╤Б╨║╤Г ╨┤╨╛╨┐╤Г╤Б╤В╨╕╨╝╤Л╤Е ╨╕╨┤╨╡╨╜╤В╨╕╤Д╨╕╨║╨░╤В╨╛╤А╨╛╨▓ ╨╜╨╕╨╢╨╡', 'worldstat-ergonomics' ); ?>"></textarea><br />
						<button type="button" class="button" id="wsergo-test-formula-btn-city"><?php esc_html_e( '╨Я╤А╨╛╨▓╨╡╤А╨╕╤В╤М', 'worldstat-ergonomics' ); ?></button>
						<span id="wsergo-formula-test-result-city" class="description"></span>
					</p>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( '╨Ш╨┤╨╡╨╜╤В╨╕╤Д╨╕╨║╨░╤В╨╛╤А', 'worldstat-ergonomics' ); ?></th>
								<th><?php esc_html_e( '╨Э╨░╨╖╨▓╨░╨╜╨╕╨╡', 'worldstat-ergonomics' ); ?></th>
								<th><?php esc_html_e( '╨д╨╛╤А╨╝╤Г╨╗╨░ ╤Б╨▓╨╛╨┤╨╜╨╛╨│╨╛ E (╨┐╤Г╤Б╤В╨╛ = ╨║╨╗╨░╤Б╤Б╨╕╨║╨░)', 'worldstat-ergonomics' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( array_values( $city_models ) as $i_c => $m_cc ) : ?>
							<tr>
								<td><input type="text" name="<?php echo esc_attr( WSErgo_Settings::OPTION_CITY_MODELS ); ?>[<?php echo esc_attr( (string) $i_c ); ?>][id]" value="<?php echo esc_attr( $m_cc['id'] ); ?>" class="regular-text" /></td>
								<td><input type="text" name="<?php echo esc_attr( WSErgo_Settings::OPTION_CITY_MODELS ); ?>[<?php echo esc_attr( (string) $i_c ); ?>][name]" value="<?php echo esc_attr( $m_cc['name'] ); ?>" class="regular-text" /></td>
								<td><textarea name="<?php echo esc_attr( WSErgo_Settings::OPTION_CITY_MODELS ); ?>[<?php echo esc_attr( (string) $i_c ); ?>][leaf_formula]" class="large-text" rows="2"><?php echo esc_textarea( $m_cc['leaf_formula'] ); ?></textarea></td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p class="description"><?php esc_html_e( '╨з╤В╨╛╨▒╤Л ╨┤╨╛╨▒╨░╨▓╨╕╤В╤М ╨╝╨╛╨┤╨╡╨╗╤М ╨│╨╛╤А╨╛╨┤╨░, ╤Б╨╛╤Е╤А╨░╨╜╨╕╤В╨╡ ╤Б╤В╤А╨░╨╜╨╕╤Ж╤Г ╨╕ ╨╛╤В╤А╨╡╨┤╨░╨║╤В╨╕╤А╤Г╨╣╤В╨╡ ╨╝╨░╤Б╤Б╨╕╨▓ ╨▓ ╨С╨Ф ╨╕╨╗╨╕ ╤А╨░╤Б╤И╨╕╤А╤М╤В╨╡ ╨╜╨░╨▒╨╛╤А ╤З╨╡╤А╨╡╨╖ ╨║╨╛╨┤.', 'worldstat-ergonomics' ); ?></p>

					<hr />
					<?php
					$city_formula_partial = WSERGO_DIR . 'includes/wsergo-settings-city-formula-partial.php';
					if ( is_readable( $city_formula_partial ) ) {
						include $city_formula_partial;
					}
					?>

					<hr />
					<h3><?php esc_html_e( '╨Ы╨╕╤Б╤В╨╛╨▓╤Л╨╡ ╨┐╨╛╨║╨░╨╖╨░╤В╨╡╨╗╨╕ (╨│╨╗╨╛╨▒╨░╨╗╤М╨╜╤Л╨╡ ╨╛╨┐╤А╨╡╨┤╨╡╨╗╨╡╨╜╨╕╤П)', 'worldstat-ergonomics' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Id ╨╕╤Б╨┐╨╛╨╗╤М╨╖╤Г╨╡╤В╤Б╤П ╨▓ DSL ╨║╨░╨║ i_id ╨╕ ╨▓ ╨║╨░╤А╤В╨╡ ╨┐╨╛╨╗╨╡╨╣ ╨│╨╛╤А╨╛╨┤╨░. ╨Т╨╡╤Б╨░ ╨▓╨╜╤Г╤В╤А╨╕ ╨╛╨┤╨╜╨╛╨│╨╛ ╨╕╨╖╨╝╨╡╤А╨╡╨╜╨╕╤П ╨╜╨╛╤А╨╝╨░╨╗╨╕╨╖╤Г╤О╤В╤Б╤П ╨┐╤А╨╕ ╤Б╨╛╤Е╤А╨░╨╜╨╡╨╜╨╕╨╕.', 'worldstat-ergonomics' ); ?></p>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Id', 'worldstat-ergonomics' ); ?></th>
								<th><?php esc_html_e( '╨Я╨╛╨┤╨┐╨╕╤Б╤М', 'worldstat-ergonomics' ); ?></th>
								<th><?php esc_html_e( '╨Ш╨╖╨╝╨╡╤А╨╡╨╜╨╕╨╡', 'worldstat-ergonomics' ); ?></th>
								<th><?php esc_html_e( '╨Х╨┤.', 'worldstat-ergonomics' ); ?></th>
								<th><?php esc_html_e( 'min', 'worldstat-ergonomics' ); ?></th>
								<th><?php esc_html_e( 'max', 'worldstat-ergonomics' ); ?></th>
								<th><?php esc_html_e( '╨Э╨░╨┐╤А╨░╨▓╨╗╨╡╨╜╨╕╨╡', 'worldstat-ergonomics' ); ?></th>
								<th><?php esc_html_e( '╨Т╨╡╤Б', 'worldstat-ergonomics' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( array_values( $indicator_form_rows ) as $ii => $ind ) : ?>
								<tr>
									<td><input type="text" class="regular-text code" name="<?php echo esc_attr( WSErgo_Indicators::OPTION_DEFINITIONS ); ?>[<?php echo (int) $ii; ?>][id]" value="<?php echo esc_attr( (string) ( $ind['id'] ?? '' ) ); ?>" maxlength="96" /></td>
									<td><input type="text" class="widefat" name="<?php echo esc_attr( WSErgo_Indicators::OPTION_DEFINITIONS ); ?>[<?php echo (int) $ii; ?>][label]" value="<?php echo esc_attr( (string) ( $ind['label'] ?? '' ) ); ?>" /></td>
									<td>
										<select name="<?php echo esc_attr( WSErgo_Indicators::OPTION_DEFINITIONS ); ?>[<?php echo (int) $ii; ?>][dimension]">
											<?php foreach ( WSErgo_Model::DIMENSION_KEYS as $dk ) : ?>
												<option value="<?php echo esc_attr( $dk ); ?>" <?php selected( (string) ( $ind['dimension'] ?? '' ), $dk ); ?>><?php echo esc_html( $city_dim_labels[ $dk ] ?? $dk ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
									<td><input type="text" class="small-text" name="<?php echo esc_attr( WSErgo_Indicators::OPTION_DEFINITIONS ); ?>[<?php echo (int) $ii; ?>][unit]" value="<?php echo esc_attr( (string) ( $ind['unit'] ?? '' ) ); ?>" /></td>
									<td><input type="text" class="small-text" name="<?php echo esc_attr( WSErgo_Indicators::OPTION_DEFINITIONS ); ?>[<?php echo (int) $ii; ?>][vmin]" value="<?php echo esc_attr( (string) ( $ind['vmin'] ?? '0' ) ); ?>" inputmode="decimal" /></td>
									<td><input type="text" class="small-text" name="<?php echo esc_attr( WSErgo_Indicators::OPTION_DEFINITIONS ); ?>[<?php echo (int) $ii; ?>][vmax]" value="<?php echo esc_attr( (string) ( $ind['vmax'] ?? '100' ) ); ?>" inputmode="decimal" /></td>
									<td>
										<select name="<?php echo esc_attr( WSErgo_Indicators::OPTION_DEFINITIONS ); ?>[<?php echo (int) $ii; ?>][direction]">
											<option value="higher_better" <?php selected( (string) ( $ind['direction'] ?? '' ), 'higher_better' ); ?>><?php esc_html_e( '╨С╨╛╨╗╤М╤И╨╡ ╨╗╤Г╤З╤И╨╡', 'worldstat-ergonomics' ); ?></option>
											<option value="lower_better" <?php selected( (string) ( $ind['direction'] ?? '' ), 'lower_better' ); ?>><?php esc_html_e( '╨Ь╨╡╨╜╤М╤И╨╡ ╨╗╤Г╤З╤И╨╡', 'worldstat-ergonomics' ); ?></option>
										</select>
									</td>
									<td><input type="text" class="small-text" name="<?php echo esc_attr( WSErgo_Indicators::OPTION_DEFINITIONS ); ?>[<?php echo (int) $ii; ?>][weight]" value="<?php echo esc_attr( (string) ( $ind['weight'] ?? '1' ) ); ?>" inputmode="decimal" /></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>

				<?php submit_button(); ?>
			</form>

			<form id="wsergo-bulk-delete-city-cm" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:none;">
				<?php wp_nonce_field( 'wsergo_bulk_delete_city_custom_metrics' ); ?>
				<input type="hidden" name="action" value="wsergo_bulk_delete_city_custom_metrics" />
			</form>
			<script>
			(function () {
				var all = document.getElementById('wsergo-city-bulk-cm-all');
				if (all) {
					all.addEventListener('change', function () {
						var on = all.checked;
						document.querySelectorAll('.wsergo-city-cm-bulk-cb').forEach(function (cb) { cb.checked = on; });
					});
				}
				var bf = document.getElementById('wsergo-bulk-delete-city-cm');
				if (bf) {
					bf.addEventListener('submit', function (e) {
						var n = document.querySelectorAll('.wsergo-city-cm-bulk-cb:checked').length;
						if (n < 1) {
							e.preventDefault();
							window.alert('<?php echo esc_js( __( '╨Ю╤В╨╝╨╡╤В╤М╤В╨╡ ╤Е╨╛╤В╤П ╨▒╤Л ╨╛╨┤╨╕╨╜ ╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╤М╤Б╨║╨╕╨╣ ╨┐╨░╤А╨░╨╝╨╡╤В╤А ╨▓ ╨╝╨░╤В╤А╨╕╤Ж╨╡.', 'worldstat-ergonomics' ) ); ?>');
							return;
						}
						if (!window.confirm('<?php echo esc_js( __( '╨г╨┤╨░╨╗╨╕╤В╤М ╨▓╤Л╨▒╤А╨░╨╜╨╜╤Л╨╡ ╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╤М╤Б╨║╨╕╨╡ ╨┐╨░╤А╨░╨╝╨╡╤В╤А╤Л ╨│╨╛╤А╨╛╨┤╨░? ╨Я╤А╨░╨▓╨╕╨╗╨░, ╨┐╨╛╨┤╨┐╨╕╤Б╨╕, ╨╛╤В╨╝╨╡╤В╨║╨╕ ╨▓ ╨╝╨░╤В╤А╨╕╤Ж╨╡ ╨╕ ╨▓ k-means ╨▒╤Г╨┤╤Г╤В ╤Б╨▒╤А╨╛╤И╨╡╨╜╤Л.', 'worldstat-ergonomics' ) ); ?>')) {
							e.preventDefault();
						}
					});
				}
			})();
			</script>

			<div id="wsergo-panel-territory" class="wsergo-scope-panel" style="display:none;">
				<div class="notice notice-info inline" style="margin:12px 0;padding:12px;">
					<p style="margin:.4em 0;"><strong><?php esc_html_e( '╨н╤А╨│╨╛╨╜╨╛╨╝╨╕╤З╨╜╨╛╤Б╤В╤М ╤В╨╡╤А╤А╨╕╤В╨╛╤А╨╕╨╕', 'worldstat-ergonomics' ); ?></strong></p>
					<p style="margin:.4em 0;" class="description"><?php esc_html_e( '╨г╤А╨╛╨▓╨╡╨╜╤М ╨║╨▓╨░╤А╤В╨░╨╗╨░ / ╤А╨░╨╣╨╛╨╜╨░ ╨▓ ╨╕╨╡╤А╨░╤А╤Е╨╕╨╕ ╨╛╨▒╤К╨╡╨║╤В╨░: ╨╖╨░╨┐╨╕╤Б╨╕ wsp_district, ╤А╨░╤Б╤И╨╕╤А╨╡╨╜╨╜╤Л╨╡ ╨┤╨░╨╜╨╜╤Л╨╡ wsdistrict_* ╨┐╤А╨╕ ╨░╨║╤В╨╕╨▓╨╜╨╛╨╝ ╨┐╨╗╨░╨│╨╕╨╜╨╡ ┬л╨а╨░╨╣╨╛╨╜╤Л┬╗. ╨Э╨╕╨╢╨╡ тАФ ╨▓╨╡╤Б╨░ ╤И╨╡╤Б╤В╨╕ ╨║╤А╨╕╤В╨╡╤А╨╕╨╡╨▓ ╤Б╨▓╨╛╨┤╨╜╨╛╨│╨╛ ╨╕╨╜╨┤╨╡╨║╤Б╨░ ╨╝╨╛╨┤╨╡╨╗╨╕ ╨╕ ╨╝╨░╤Б╤Б╨╛╨▓╤Л╨╣ ╨┐╨╡╤А╨╡╤Б╤З╤С╤В ╨╝╨╡╤В╤А╨╕╨║.', 'worldstat-ergonomics' ); ?></p>
				</div>
				<?php if ( class_exists( 'WSErgo_District_Tab' ) ) : ?>
				<h2 class="nav-tab-wrapper wsergo-territory-inner-nav" style="margin-top:8px;">
					<?php WSErgo_District_Tab::render_territory_nav_link(); ?>
				</h2>
				<div id="tab-territory-district" class="wsergo-tab-panel-territory">
					<?php WSErgo_District_Tab::render_panel_inner(); ?>
				</div>
				<?php else : ?>
				<p class="description"><?php esc_html_e( '╨Ь╨╛╨┤╤Г╨╗╤М ╨╜╨░╤Б╤В╤А╨╛╨╡╨║ ╤А╨░╨╣╨╛╨╜╨░ ╨╜╨╡╨┤╨╛╤Б╤В╤Г╨┐╨╡╨╜.', 'worldstat-ergonomics' ); ?></p>
				<?php endif; ?>
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
			<h1><?php esc_html_e( '╨Ш╨╝╨┐╨╛╤А╤В ╨║╨▓╨░╤А╤В╨░╨╗╨╛╨▓ (CSV)', 'worldstat-ergonomics' ); ?></h1>
			<?php if ( $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
			<?php endif; ?>
			<p><?php esc_html_e( '╨д╨╛╤А╨╝╨░╤В UTF-8, ╤А╨░╨╖╨┤╨╡╨╗╨╕╤В╨╡╨╗╤М ╨╖╨░╨┐╤П╤В╨░╤П. ╨Я╨╡╤А╨▓╨░╤П ╤Б╤В╤А╨╛╨║╨░ тАФ ╨╖╨░╨│╨╛╨╗╨╛╨▓╨║╨╕:', 'worldstat-ergonomics' ); ?></p>
			<code>title,city_id,functionality,safety,comfort,livability,masterability,manageability,index</code>
			<p class="description"><?php esc_html_e( 'title тАФ ╨╜╨░╨╖╨▓╨░╨╜╨╕╨╡ ╨║╨▓╨░╤А╤В╨░╨╗╨░; city_id тАФ ID ╨│╨╛╤А╨╛╨┤╨░ (wsp_city). ╨Ф╨╛╨┐╤Г╤Б╤В╨╕╨╝╤Л ╨┐╤Б╨╡╨▓╨┤╨╛╨╜╨╕╨╝╤Л: accessibilityтЖТfunctionality, environmentтЖТlivability. ╨Я╤Г╤Б╤В╤Л╨╡ ╤П╤З╨╡╨╣╨║╨╕ ╨┐╤А╨╛╨┐╤Г╤Б╨║╨░╤О╤В╤Б╤П.', 'worldstat-ergonomics' ); ?></p>
			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'wsergo_import_districts', 'wsergo_import_nonce' ); ?>
				<p><input type="file" name="wsergo_csv" accept=".csv,text/csv" required /></p>
				<?php submit_button( __( '╨Ч╨░╨│╤А╤Г╨╖╨╕╤В╤М', 'worldstat-ergonomics' ) ); ?>
			</form>
		</div>
		<?php
	}

	private function process_csv_import( string $tmp_path ): string {
		$fh = fopen( $tmp_path, 'rb' );
		if ( ! $fh ) {
			return __( '╨Э╨╡ ╤Г╨┤╨░╨╗╨╛╤Б╤М ╨┐╤А╨╛╤З╨╕╤В╨░╤В╤М ╤Д╨░╨╣╨╗.', 'worldstat-ergonomics' );
		}
		$header = fgetcsv( $fh );
		if ( ! $header ) {
			fclose( $fh );
			return __( '╨Я╤Г╤Б╤В╨╛╨╣ ╤Д╨░╨╣╨╗.', 'worldstat-ergonomics' );
		}
		$header = array_map( 'trim', array_map( 'strtolower', $header ) );
		$map    = array_flip( $header );

		$need = [ 'title', 'city_id' ];
		foreach ( $need as $k ) {
			if ( ! isset( $map[ $k ] ) ) {
				fclose( $fh );
				return __( '╨Э╨╡╤В ╨╛╨▒╤П╨╖╨░╤В╨╡╨╗╤М╨╜╨╛╨│╨╛ ╤Б╤В╨╛╨╗╨▒╤Ж╨░: title ╨╕╨╗╨╕ city_id.', 'worldstat-ergonomics' );
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
			__( '╨Ш╨╝╨┐╨╛╤А╤В╨╕╤А╨╛╨▓╨░╨╜╨╛ ╨║╨▓╨░╤А╤В╨░╨╗╨╛╨▓: %d.', 'worldstat-ergonomics' ),
			$n
		);
	}
}
