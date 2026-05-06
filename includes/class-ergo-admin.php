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
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
		add_action( 'admin_menu', [ $this, 'add_import_page' ], 9 );
		add_action( 'admin_menu', [ $this, 'add_worldstat_submenu' ], 99 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin' ] );
		add_action( 'wp_ajax_wsergo_test_formula', [ $this, 'ajax_test_formula' ] );
		add_action( 'wp_ajax_wsergo_append_custom_metric', [ $this, 'ajax_append_custom_metric' ] );

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
		// Ключи, которых не было в POST (например только что добавленный пользовательский параметр), сохраняем из опции.
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
	 * Эталонная страна (запись каталога) для превью макропризнаков на вкладке «Формула».
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
		// Нет ключа в POST (все чекбоксы сняты) — не подставляем старые значения из БД.
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
	 * Пользовательские производные показатели (калькулятор на вкладке «Данные»).
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
	 * Удаление пользовательского параметра (только слаги из калькулятора, не столбцы CSV).
	 */
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

	/**
	 * Добавляет одно правило калькулятора без полной отправки формы (обходит лимит PHP max_input_vars на больших страницах).
	 */
	public function ajax_append_custom_metric(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Недостаточно прав.', 'worldstat-ergonomics' ) ] );
		}
		check_ajax_referer( 'wsergo_append_custom_metric', 'nonce' );
		if ( ! class_exists( 'WSErgo_Settings' ) ) {
			wp_send_json_error( [ 'message' => __( 'Настройки недоступны.', 'worldstat-ergonomics' ) ] );
		}
		$raw = isset( $_POST['rule'] ) ? wp_unslash( (string) $_POST['rule'] ) : '';
		$rule = $raw !== '' ? json_decode( $raw, true ) : null;
		if ( ! is_array( $rule ) ) {
			wp_send_json_error( [ 'message' => __( 'Некорректные данные правила.', 'worldstat-ergonomics' ) ] );
		}
		$row = [
			'slug'   => isset( $rule['slug'] ) ? $rule['slug'] : '',
			'op'     => isset( $rule['op'] ) ? $rule['op'] : '',
			'key_a'  => isset( $rule['key_a'] ) ? $rule['key_a'] : '',
			'key_b'  => isset( $rule['key_b'] ) ? $rule['key_b'] : '',
			'const'  => isset( $rule['const'] ) ? $rule['const'] : 0,
		];
		$new_slug = sanitize_key( (string) $row['slug'] );
		if ( $new_slug === '' ) {
			wp_send_json_error( [ 'message' => __( 'Укажите итоговый ключ.', 'worldstat-ergonomics' ) ] );
		}
		$existing = get_option( WSErgo_Settings::OPTION_MACRO_CUSTOM_METRICS, [] );
		if ( ! is_array( $existing ) ) {
			$existing = [];
		}
		foreach ( $existing as $er ) {
			if ( ! is_array( $er ) ) {
				continue;
			}
			if ( sanitize_key( (string) ( $er['slug'] ?? '' ) ) === $new_slug ) {
				wp_send_json_error( [ 'message' => __( 'Такой итоговый ключ уже существует.', 'worldstat-ergonomics' ) ] );
			}
		}
		if ( count( $existing ) >= 30 ) {
			wp_send_json_error( [ 'message' => __( 'Достигнут лимит правил (30).', 'worldstat-ergonomics' ) ] );
		}
		$merged    = array_merge( $existing, [ $row ] );
		$sanitized = $this->sanitize_macro_custom_metrics( $merged );
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
		update_option( WSErgo_Settings::OPTION_MACRO_CUSTOM_METRICS, $sanitized );
		$this->sync_default_labels_for_custom_metrics( $sanitized );
		wp_send_json_success( [ 'count' => count( $sanitized ) ] );
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
				'wsergo-settings-country',
				WSERGO_URL . 'assets/js/wsergo-settings-country.js',
				[ 'jquery' ],
				WSERGO_VERSION,
				true
			);
			$wsergo_cm_opt_key = class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::OPTION_MACRO_CUSTOM_METRICS : 'wsergo_macro_custom_metrics';
			wp_localize_script(
				'wsergo-settings-country',
				'wsergoCmSettings',
				[
					'optKey'       => $wsergo_cm_opt_key,
					'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
					'maxRules'     => 30,
					'nonceAppend'  => wp_create_nonce( 'wsergo_append_custom_metric' ),
					'ajaxAction'   => 'wsergo_append_custom_metric',
					'messages'     => [
						'maxRules'      => __( 'Не более 30 пользовательских параметров.', 'worldstat-ergonomics' ),
						'needSlug'      => __( 'Укажите итоговый ключ (латиница, snake_case).', 'worldstat-ergonomics' ),
						'needAB'        => __( 'Для этой операции задайте параметры A и B.', 'worldstat-ergonomics' ),
						'needA'         => __( 'Задайте параметр A и при необходимости число.', 'worldstat-ergonomics' ),
						'duplicateSlug' => __( 'Такой итоговый ключ уже есть. Выберите другое имя или удалите правило в матрице.', 'worldstat-ergonomics' ),
						'ajaxFail'      => __( 'Не удалось сохранить параметр. Проверьте консоль или попробуйте ещё раз.', 'worldstat-ergonomics' ),
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
					$('#wsergo-test-formula-btn').on('click', function(){
						var formula = $('#wsergo_test_formula_inline').length ? $('#wsergo_test_formula_inline').val() : $('textarea[name=\"wsergo_models[0][leaf_formula]\"]').first().val();
						$.post(ajaxurl, { action:'wsergo_test_formula', nonce:'" . esc_js( wp_create_nonce( 'wsergo_settings_ajax' ) ) . "', formula: formula }, function(r){
							if(r.success){ $('#wsergo-formula-test-result').text('E ≈ ' + r.data.value); } else { $('#wsergo-formula-test-result').text(r.data.message || 'Error'); }
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
						var txt = isFinite(tot) ? tot.toFixed(4) : '—';
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

	/**
	 * Текст для колонки «Пример данных»: значение из кэша макроряда по ключу признака или «—».
	 *
	 * @param array<string, float>|null $raw_row
	 */
	private function format_macro_signal_example_display( ?array $raw_row, string $signal ): string {
		$signal = trim( (string) $signal );
		if ( $signal === '' || ! is_array( $raw_row ) || ! array_key_exists( $signal, $raw_row ) ) {
			return '—';
		}
		$v = $raw_row[ $signal ];
		if ( ! is_numeric( $v ) ) {
			return '—';
		}
		$f = (float) $v;
		if ( ! is_finite( $f ) ) {
			return '—';
		}
		// %.6g давал научную нотацию (6.24e+7) для крупных целых; в CSV ожидается полная запись.
		$rn = round( $f );
		if ( abs( $f - $rn ) < 1e-6 * max( 1.0, abs( $rn ) ) ) {
			return (string) (int) $rn;
		}
		$s = number_format( $f, 12, '.', '' );
		$s = rtrim( rtrim( $s, '0' ), '.' );
		return $s === '' || $s === '-.' ? (string) $f : $s;
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
			'add'       => __( 'A + B (сумма)', 'worldstat-ergonomics' ),
			'sub'       => __( 'A − B (разность)', 'worldstat-ergonomics' ),
			'mul'       => __( 'A × B (произведение)', 'worldstat-ergonomics' ),
			'div'       => __( 'A / B (деление, B≠0)', 'worldstat-ergonomics' ),
			'scale_mul' => __( 'A × число', 'worldstat-ergonomics' ),
			'scale_add' => __( 'A + число', 'worldstat-ergonomics' ),
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
		$ref_macro_raw_row     = null;
		$ref_macro_example_note = '';
		if ( $macro_ref_country_id > 0 && class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			$iso2_ref = strtoupper( trim( (string) get_post_meta( $macro_ref_country_id, 'wsp_iso_alpha2', true ) ) );
			if ( strlen( $iso2_ref ) === 2 ) {
				$det_ref = WSErgo_Country_Macro_Calculator::get_country_macro_detail( $iso2_ref );
				if ( $det_ref && isset( $det_ref['raw_row'] ) && is_array( $det_ref['raw_row'] ) ) {
					$ref_macro_raw_row = $det_ref['raw_row'];
				}
				$tref = get_the_title( $macro_ref_country_id );
				$ref_macro_example_note = $tref !== '' ? $tref . ' (' . $iso2_ref . ', ' . (int) $macro_year . ')' : '';
			}
		}

		settings_errors();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Эргономичность', 'worldstat-ergonomics' ); ?></h1>
			<p class="description" style="margin-top:0;">
				<?php esc_html_e( 'Страновый индекс: CSV в разделе «Данные CSV» (в т.ч. широкие таблицы с кодом страны, годом и показателями), кластеризация, шесть критериев и итоговый E. Рабочие настройки — в блоке «Эргономичность страны»; разделы «город» и «территория» пока заглушки.', 'worldstat-ergonomics' ); ?>
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
					<li><?php esc_html_e( 'Данные — калькулятор пользовательских параметров (новые показатели из столбцов CSV), подписи к признакам, матрица отбора, версия методики, k-means, опорный год, эталонная страна для препросмотра на «Формула».', 'worldstat-ergonomics' ); ?></li>
					<li><?php esc_html_e( 'Формула — модель и DSL объекта вверху страницы; макро-критерии страны: веса и суммы. Если включена матрица на «Данные», состав параметров по критериям задаётся только там, а на «Формула» — веса слагаемых и вес критерия в E.', 'worldstat-ergonomics' ); ?></li>
				</ol>
			</div>

			<h2 class="nav-tab-wrapper wsergo-country-inner-nav" style="margin-top:8px;">
				<a href="#tab-data" class="nav-tab nav-tab-active" data-tab="tab-data"><?php esc_html_e( 'Данные', 'worldstat-ergonomics' ); ?></a>
				<a href="#tab-formula" class="nav-tab" data-tab="tab-formula"><?php esc_html_e( 'Формула', 'worldstat-ergonomics' ); ?></a>
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

				<div id="tab-data" class="wsergo-tab-panel">
					<h2><?php esc_html_e( 'Данные', 'worldstat-ergonomics' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Источники и параметры для странового индекса по макроданным CSV. Поддерживаются «длинные» файлы (country_code, year, value) и широкие (country_code, year и несколько числовых столбцов — demographics, urban_infra, environment и т.д.). Для базового треугольника населения/площади по-прежнему нужны ряды population_total и surface_area_sqkm либо совместимые long-CSV; из широких файлов подтягиваются плотность, доля городского населения, лес и прочие признаки.', 'worldstat-ergonomics' ); ?></p>
					<?php if ( defined( 'WSERGO_URL' ) ) : ?>
					<p class="description">
						<a href="<?php echo esc_url( WSERGO_URL . 'data/ergo-wide-csv-reference.txt' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Справка по столбцам широких CSV (открывается в новой вкладке)', 'worldstat-ergonomics' ); ?></a>
					</p>
					<?php endif; ?>

					<h3><?php esc_html_e( 'Калькулятор пользовательских параметров', 'worldstat-ergonomics' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Добавьте показатель из уже загруженных столбцов CSV или из производных модели. Итоговый ключ — латиница (snake_case). По кнопке «Добавить» правило сохраняется в базу сразу; остальные поля страницы — кнопкой «Сохранить настройки» внизу. На очень длинных формах PHP может ограничивать число полей (max_input_vars) — отдельное сохранение калькулятора это обходит.', 'worldstat-ergonomics' ); ?></p>
					<div class="wsergo-cm-panel" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;padding:12px;border:1px solid #c3c4c7;background:#fff;margin-bottom:10px;max-width:920px;border-radius:4px;">
						<div>
							<label for="wsergo-cm-key-a" class="screen-reader-text"><?php esc_html_e( 'Параметр A', 'worldstat-ergonomics' ); ?></label>
							<span class="description" style="display:block;margin-bottom:4px;"><?php esc_html_e( 'Параметр A', 'worldstat-ergonomics' ); ?></span>
							<input type="text" id="wsergo-cm-key-a" class="regular-text code" maxlength="96" autocomplete="off" placeholder="<?php esc_attr_e( 'ключ столбца', 'worldstat-ergonomics' ); ?>" />
						</div>
						<div>
							<label for="wsergo-cm-op" class="screen-reader-text"><?php esc_html_e( 'Операция', 'worldstat-ergonomics' ); ?></label>
							<span class="description" style="display:block;margin-bottom:4px;"><?php esc_html_e( 'Операция', 'worldstat-ergonomics' ); ?></span>
							<select id="wsergo-cm-op" style="min-width:11em;">
								<?php foreach ( $wsergo_cm_ops as $op_k => $op_lab ) : ?>
									<option value="<?php echo esc_attr( $op_k ); ?>"><?php echo esc_html( $op_lab ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div id="wsergo-cm-wrap-b">
							<label for="wsergo-cm-key-b" class="screen-reader-text"><?php esc_html_e( 'Параметр B', 'worldstat-ergonomics' ); ?></label>
							<span class="description" style="display:block;margin-bottom:4px;"><?php esc_html_e( 'Параметр B', 'worldstat-ergonomics' ); ?></span>
							<input type="text" id="wsergo-cm-key-b" class="regular-text code" maxlength="96" autocomplete="off" placeholder="<?php esc_attr_e( 'ключ столбца', 'worldstat-ergonomics' ); ?>" />
						</div>
						<div id="wsergo-cm-wrap-const" style="display:none;">
							<label for="wsergo-cm-const" class="screen-reader-text"><?php esc_html_e( 'Число', 'worldstat-ergonomics' ); ?></label>
							<span class="description" style="display:block;margin-bottom:4px;"><?php esc_html_e( 'Число', 'worldstat-ergonomics' ); ?></span>
							<input type="text" id="wsergo-cm-const" class="small-text" inputmode="decimal" value="0" />
						</div>
						<div style="flex:1;min-width:180px;">
							<label for="wsergo-cm-slug" class="screen-reader-text"><?php esc_html_e( 'Итоговый ключ', 'worldstat-ergonomics' ); ?></label>
							<span class="description" style="display:block;margin-bottom:4px;"><?php esc_html_e( 'Итоговый ключ', 'worldstat-ergonomics' ); ?></span>
							<input type="text" id="wsergo-cm-slug" class="regular-text code" maxlength="96" autocomplete="off" placeholder="<?php esc_attr_e( 'напр. my_ratio', 'worldstat-ergonomics' ); ?>" />
						</div>
						<div>
							<button type="button" id="wsergo-cm-add" class="button button-primary"><?php esc_html_e( 'Добавить', 'worldstat-ergonomics' ); ?></button>
						</div>
					</div>
					<p class="description" style="margin-top:0;">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: number of saved custom metric rules. */
								_n( 'Сохранено правил в калькуляторе: %d.', 'Сохранено правил в калькуляторе: %d.', $wsergo_cm_cnt, 'worldstat-ergonomics' ),
								(int) $wsergo_cm_cnt
							)
						);
						?>
					</p>
					<input type="hidden" id="wsergo-cm-opt-name" value="<?php echo esc_attr( $wsergo_cm_opt ); ?>" />
					<p class="description"><?php esc_html_e( 'Порядок добавления важен: в следующем правиле можно ссылаться на ключ из предыдущего. После сохранения обновится кэш макроиндекса.', 'worldstat-ergonomics' ); ?></p>
					<hr />
					<h3><?php esc_html_e( 'Подписи к данным (русский)', 'worldstat-ergonomics' ); ?></h3>
					<?php if ( ! $wsp_csv_has_datasets && class_exists( 'WorldStat_Uploaded_Csv' ) && empty( $data_label_keys ) ) : ?>
						<div class="notice notice-warning inline" style="margin:10px 0;padding:10px 12px;">
							<p style="margin:0;">
								<?php
								echo esc_html(
									__( 'В базе нет загруженных CSV и не заданы пользовательские параметры: список ключей пуст. Загрузите CSV или добавьте строки в калькуляторе выше.', 'worldstat-ergonomics' )
								);
								?>
								<?php if ( current_user_can( 'manage_options' ) ) : ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=worldstat-csv' ) ); ?>"><?php esc_html_e( 'Данные CSV', 'worldstat-ergonomics' ); ?></a>
								<?php endif; ?>
							</p>
						</div>
					<?php endif; ?>
					<p class="description"><?php esc_html_e( 'Список ключей — столбцы из CSV, дополнительные ключи из блока ниже, пользовательские параметры из калькулятора. Подпись — здесь или импорт «Переводы»; иначе на сайте прочерк.', 'worldstat-ergonomics' ); ?></p>
					<div style="max-height:340px;overflow:auto;border:1px solid #c3c4c7;padding:10px;background:#fff;margin-bottom:12px;">
						<table class="widefat striped" style="margin:0;">
							<thead>
								<tr>
									<th scope="col" style="width:38%;"><?php esc_html_e( 'Ключ в данных', 'worldstat-ergonomics' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Как показывать пользователю', 'worldstat-ergonomics' ); ?></th>
								</tr>
							</thead>
							<tbody>
							<?php foreach ( $data_label_keys as $lk ) : ?>
								<?php $ov = isset( $data_labels_saved[ $lk ] ) ? $data_labels_saved[ $lk ] : ''; ?>
								<tr>
									<td><code><?php echo esc_html( $lk ); ?></code></td>
									<td>
										<input type="text" class="widefat" name="<?php echo esc_attr( WSErgo_Settings::OPTION_DATA_LABELS_RU ); ?>[<?php echo esc_attr( $lk ); ?>]" value="<?php echo esc_attr( $ov ); ?>" placeholder="<?php esc_attr_e( 'Введите обозначение на русском', 'worldstat-ergonomics' ); ?>" maxlength="240" />
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<hr />
					<h3><?php esc_html_e( 'Матрица критериев (только отбор параметров)', 'worldstat-ergonomics' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Отметьте для каждого параметра, к каким из шести критериев странового индекса он относится. Веса слагаемых и вес критерия в E настраиваются на вкладке «Формула».', 'worldstat-ergonomics' ); ?></p>
					<?php
					$matrix_col_short = [
						'F'  => __( 'Функц.', 'worldstat-ergonomics' ),
						'Cm' => __( 'Комф.', 'worldstat-ergonomics' ),
						'H'  => __( 'Обит.', 'worldstat-ergonomics' ),
						'A'  => __( 'Осв.', 'worldstat-ergonomics' ),
						'S'  => __( 'Безоп.', 'worldstat-ergonomics' ),
						'Ct' => __( 'Упр.', 'worldstat-ergonomics' ),
					];
					?>
					<div class="wsergo-macro-matrix-wrap" style="max-height:420px;overflow:auto;border:1px solid #c3c4c7;background:#fff;margin-bottom:12px;">
						<table class="widefat striped wsergo-macro-matrix-table" style="margin:0;min-width:680px;border-collapse:separate;border-spacing:0;">
							<thead>
								<tr>
									<th scope="col" class="wsergo-macro-matrix-sticky-corner" style="min-width:220px;"><?php esc_html_e( 'Параметр', 'worldstat-ergonomics' ); ?></th>
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
											<a href="<?php echo esc_url( $wsergo_cm_del_url ); ?>" class="button button-small" style="margin:0 10px 6px 0;vertical-align:middle;" onclick="return confirm('<?php echo esc_js( __( 'Удалить пользовательский параметр и его формулу? Отметки в матрице и подпись будут сброшены.', 'worldstat-ergonomics' ) ); ?>');"><?php esc_html_e( 'Удалить', 'worldstat-ergonomics' ); ?></a>
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
							<label style="display:block;margin:.35em 0;">
								<input type="checkbox" name="<?php echo esc_attr( WSErgo_Settings::OPTION_MACRO_CLUSTER_FEATURES ); ?>[]" value="<?php echo esc_attr( $sig ); ?>" <?php checked( in_array( $sig, $cf_for_checkboxes, true ), true ); ?> />
								<strong><?php echo esc_html( class_exists( 'WSErgo_Country_Macro_Calculator' ) ? WSErgo_Country_Macro_Calculator::data_label_ru( $sig ) : $sig ); ?></strong>
								<code style="margin-left:6px;"><?php echo esc_html( $sig ); ?></code>
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
								<?php
								$mref_min = class_exists( 'WorldStat_Platform_Years' ) ? max( 1900, WorldStat_Platform_Years::min() ) : 1900;
								$mref_max = class_exists( 'WorldStat_Platform_Years' ) ? max( 2100, WorldStat_Platform_Years::max() ) : 2100;
								?>
								<input type="number" id="wsergo_macro_reference_year" name="<?php echo esc_attr( WSErgo_Settings::OPTION_MACRO_REFERENCE_YEAR ); ?>" value="<?php echo esc_attr( (string) $macro_year ); ?>" class="small-text" min="<?php echo esc_attr( (string) $mref_min ); ?>" max="<?php echo esc_attr( (string) $mref_max ); ?>" step="1" />
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
						<tr>
							<th scope="row"><label for="wsergo_macro_reference_country"><?php esc_html_e( 'Эталонная страна', 'worldstat-ergonomics' ); ?></label></th>
							<td>
								<select id="wsergo_macro_reference_country" name="<?php echo esc_attr( WSErgo_Settings::OPTION_MACRO_REFERENCE_COUNTRY_POST_ID ); ?>">
									<option value="0"><?php esc_html_e( '— не выбрана —', 'worldstat-ergonomics' ); ?></option>
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
								<p class="description"><?php esc_html_e( 'Для колонки «Пример данных» на вкладке «Формула»: значения признаков из загруженных макро-CSV для этой страны и опорного года (динамически из кэша расчёта).', 'worldstat-ergonomics' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<div id="tab-formula" class="wsergo-tab-panel" style="display:none">
					<h2><?php esc_html_e( 'Формула', 'worldstat-ergonomics' ); ?></h2>

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
									<p class="description"><?php echo esc_html( $active_model['leaf_formula'] !== '' ? $active_model['leaf_formula'] : __( 'Пустая формула: взвешенное среднее по шести измерениям объекта (как заданы веса в карточке квартала/здания).', 'worldstat-ergonomics' ) ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					</table>
					<hr />
					<h3><?php esc_html_e( 'Коэффициенты k_*', 'worldstat-ergonomics' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Глобальные множители в пользовательской формуле сводного E по объекту (k_default и др.). Отдельно от весов макрокритериев страны.', 'worldstat-ergonomics' ); ?></p>
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
						<?php esc_html_e( 'Простой режим: оставьте формулу пустой — сводный E считается как взвешенное среднее по шести осям объекта. Своя формула: оси можно комбинировать с коэффициентами k_*, показателями i_* и весами w_*.', 'worldstat-ergonomics' ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'В формуле используются обозначения осей (баллы 0–100), веса w_* из карточки объекта, показатели i_* и множители k_* из таблицы выше.', 'worldstat-ergonomics' ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'Допустимые идентификаторы:', 'worldstat-ergonomics' ); ?>
						<code><?php echo esc_html( $allowed_txt ); ?></code>
					</p>
					<p>
						<label for="wsergo_test_formula_inline"><?php esc_html_e( 'Проверка формулы (тестовые 50 по всем осям)', 'worldstat-ergonomics' ); ?></label><br />
						<textarea id="wsergo_test_formula_inline" class="large-text" rows="2" placeholder="<?php esc_attr_e( 'Оставьте пустым или вставьте выражение по списку допустимых идентификаторов ниже', 'worldstat-ergonomics' ); ?>"></textarea><br />
						<button type="button" class="button" id="wsergo-test-formula-btn"><?php esc_html_e( 'Проверить', 'worldstat-ergonomics' ); ?></button>
						<span id="wsergo-formula-test-result" class="description"></span>
					</p>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Идентификатор', 'worldstat-ergonomics' ); ?></th>
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
					<?php if ( $wsp_csv_has_datasets ) : ?>
					<h3><?php esc_html_e( 'Макро: шесть критериев странового индекса', 'worldstat-ergonomics' ); ?></h3>
					<p class="description">
						<?php esc_html_e( 'Пока на «Данные» ни одна галочка не стоит, для всех критериев действует встроенная методика. Как только вы отметите параметры у критерия и сохраните настройки, они появятся здесь; состав менять нельзя — только веса.', 'worldstat-ergonomics' ); ?>
						<a href="#tab-data" class="wsergo-tab-deep-link"><?php esc_html_e( 'К матрице отбора', 'worldstat-ergonomics' ); ?></a>
					</p>
					<?php if ( $ref_macro_example_note !== '' ) : ?>
						<p class="description"><strong><?php esc_html_e( 'Пример данных', 'worldstat-ergonomics' ); ?>:</strong> <?php echo esc_html( $ref_macro_example_note ); ?> — <?php esc_html_e( 'значения из загруженных макро-CSV (сырой ряд, опорный год).', 'worldstat-ergonomics' ); ?></p>
					<?php elseif ( $macro_ref_country_id > 0 ) : ?>
						<p class="description"><?php esc_html_e( 'Для выбранной эталонной страны нет строки сырых признаков в кэше расчёта: проверьте код ISO2 в карточке страны и наличие строк в CSV за опорный год.', 'worldstat-ergonomics' ); ?></p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'Чтобы заполнить столбец «Пример данных», выберите эталонную страну на вкладке «Данные» и сохраните настройки.', 'worldstat-ergonomics' ); ?></p>
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
									<span class="description"><?php esc_html_e( 'Вес критерия в E', 'worldstat-ergonomics' ); ?></span>
									<input type="text" class="small-text" name="<?php echo esc_attr( WSErgo_Settings::OPTION_MACRO_E_AXIS_WEIGHTS ); ?>[<?php echo esc_attr( $ax_key ); ?>]" value="<?php echo esc_attr( (string) ( $macro_e_w[ $ax_key ] ?? '' ) ); ?>" inputmode="decimal" onclick="event.stopPropagation();" onkeydown="event.stopPropagation();" />
								</label>
							</span>
							<span class="description wsergo-macro-sum-line">
								<span class="description"><?php esc_html_e( 'Σ весов параметров (после распределения)', 'worldstat-ergonomics' ); ?></span>
								<strong class="wsergo-macro-sum-total" data-macro-axis="<?php echo esc_attr( $ax_key ); ?>"><?php echo esc_html( sprintf( '%.4f', array_sum( array_map( static function ( $r ) { return (float) ( $r['weight'] ?? 0 ); }, $rows_ax ) ) ) ); ?></strong>
							</span>
						</summary>
						<div style="padding:0 14px 16px 14px;">
							<?php if ( count( $picked ) === 0 ) : ?>
								<p class="description"><?php esc_html_e( 'Нет отмеченных параметров — для этого критерия подставляется встроенная методика (ниже состав по умолчанию).', 'worldstat-ergonomics' ); ?></p>
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
											<th class="col-macro-sig"><?php esc_html_e( 'Признак', 'worldstat-ergonomics' ); ?></th>
											<th class="col-macro-w"><?php esc_html_e( 'Доля (норм.)', 'worldstat-ergonomics' ); ?></th>
											<th class="col-macro-inv"><?php esc_html_e( 'Инверсия 1−x', 'worldstat-ergonomics' ); ?></th>
											<th class="col-macro-ex"><?php esc_html_e( 'Пример данных', 'worldstat-ergonomics' ); ?></th>
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
											<td class="col-macro-inv"><?php echo ! empty( $rowt['invert'] ) ? esc_html__( 'да', 'worldstat-ergonomics' ) : esc_html__( 'нет', 'worldstat-ergonomics' ); ?></td>
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
											<th class="col-macro-sig"><?php esc_html_e( 'Признак', 'worldstat-ergonomics' ); ?></th>
											<th class="col-macro-w"><?php esc_html_e( 'Вес', 'worldstat-ergonomics' ); ?></th>
											<th class="col-macro-inv"><?php esc_html_e( 'Инверсия 1−x', 'worldstat-ergonomics' ); ?></th>
											<th class="col-macro-ex"><?php esc_html_e( 'Пример данных', 'worldstat-ergonomics' ); ?></th>
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
												<input type="text" class="small-text wsergo-macro-w-input" data-macro-signal="<?php echo esc_attr( $psig ); ?>" name="<?php echo esc_attr( WSErgo_Settings::OPTION_MACRO_CRITERIA_WEIGHTS ); ?>[<?php echo esc_attr( $psig ); ?>][<?php echo esc_attr( $ax_key ); ?>]" value="<?php echo esc_attr( $w_stored ); ?>" inputmode="decimal" placeholder="<?php esc_attr_e( 'авто', 'worldstat-ergonomics' ); ?>" autocomplete="off" />
											</td>
											<td class="col-macro-inv">
												<input type="hidden" name="<?php echo esc_attr( $inv_name ); ?>" value="0" />
												<label><input type="checkbox" name="<?php echo esc_attr( $inv_name ); ?>" value="1" <?php checked( $inv_checked ); ?> /> <?php esc_html_e( '1−x', 'worldstat-ergonomics' ); ?></label>
											</td>
											<td class="col-macro-ex"><code class="description"><?php echo esc_html( $this->format_macro_signal_example_display( $ref_macro_raw_row, $psig ) ); ?></code></td>
										</tr>
										<?php endforeach; ?>
									</tbody>
									<tfoot>
										<tr>
											<td class="description col-macro-sig"><?php esc_html_e( 'Σ весов (после распределения)', 'worldstat-ergonomics' ); ?></td>
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
						<h3><?php esc_html_e( 'Макро: шесть критериев странового индекса', 'worldstat-ergonomics' ); ?></h3>
						<div class="notice notice-warning inline" style="margin:10px 0;padding:12px;">
							<p style="margin:0;">
								<?php esc_html_e( 'Этот блок скрыт: в базе нет загруженных CSV. Раньше здесь показывалась «встроенная методика» с таблицей признаков только для справки — без данных она вводила в заблуждение и поля весов/инверсии в этом режиме не редактируются.', 'worldstat-ergonomics' ); ?>
							</p>
							<p style="margin:.65em 0 0;">
								<?php esc_html_e( 'Загрузите хотя бы один набор в разделе платформы «Данные CSV», затем отметьте параметры в матрице на вкладке «Данные» — после сохранения здесь появятся редактируемые веса по признакам.', 'worldstat-ergonomics' ); ?>
								<?php if ( current_user_can( 'manage_options' ) ) : ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=worldstat-csv' ) ); ?>"><?php esc_html_e( 'Данные CSV', 'worldstat-ergonomics' ); ?></a>
								<?php endif; ?>
								&nbsp;·&nbsp;
								<a href="#tab-data" class="wsergo-tab-deep-link"><?php esc_html_e( 'Вкладка «Данные»', 'worldstat-ergonomics' ); ?></a>
							</p>
						</div>
					<?php endif; ?>

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
