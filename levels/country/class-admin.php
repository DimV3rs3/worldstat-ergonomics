<?php
/**
 * Админка уровня «страна»: переменные панели и вспомогательные форматтеры.
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_Country_Admin {

	/**
	 * @param array<string, mixed> $shared Из render_settings_page (модели, коэффициенты, …).
	 * @return array<string, mixed>
	 */
	public static function prepare_panel_vars( array $shared = [] ): array {
		$models       = isset( $shared['models'] ) && is_array( $shared['models'] ) ? $shared['models'] : ( class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::get_models() : [] );
		$active_id    = isset( $shared['active_id'] ) ? (string) $shared['active_id'] : ( class_exists( 'WSErgo_Settings' ) ? (string) get_option( WSErgo_Settings::OPTION_ACTIVE_MODEL, 'default_weighted' ) : 'default_weighted' );
		$coeffs       = isset( $shared['coeffs'] ) && is_array( $shared['coeffs'] ) ? $shared['coeffs'] : ( class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::get_coefficients() : [] );
		$active_model = class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::get_active_model() : null;
		$allowed_txt  = isset( $shared['allowed_txt'] ) ? (string) $shared['allowed_txt'] : implode( ', ', array_keys( class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::get_leaf_formula_allowed_ids() : [] ) );

		$macro_year           = isset( $shared['macro_year'] ) ? (int) $shared['macro_year'] : ( class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::get_macro_reference_year() : 2022 );
		$macro_k              = isset( $shared['macro_k'] ) ? (int) $shared['macro_k'] : ( class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::get_macro_k_clusters() : 6 );
		$macro_ref_country_id = class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::get_macro_reference_country_post_id() : 0;
		$macro_e_w            = isset( $shared['macro_e_w'] ) && is_array( $shared['macro_e_w'] ) ? $shared['macro_e_w'] : ( class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::get_macro_e_axis_weights() : [] );

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

		$stored_cf           = isset( $shared['stored_cf'] ) && is_array( $shared['stored_cf'] ) ? $shared['stored_cf'] : ( class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::get_macro_cluster_features() : [] );
		$cf_for_checkboxes   = isset( $shared['cf_for_checkboxes'] ) && is_array( $shared['cf_for_checkboxes'] ) ? $shared['cf_for_checkboxes'] : ( count( $stored_cf ) >= 2 ? $stored_cf : [] );
		$macro_axis_resolved = isset( $shared['macro_axis_resolved'] ) && is_array( $shared['macro_axis_resolved'] ) ? $shared['macro_axis_resolved'] : ( class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::get_macro_axis_terms_resolved() : [] );
		$signals_ui          = isset( $shared['signals_ui'] ) && is_array( $shared['signals_ui'] ) ? $shared['signals_ui'] : ( class_exists( 'WSErgo_Country_Macro_Calculator' ) ? WSErgo_Country_Macro_Calculator::macro_cluster_signal_allowlist( 'country' ) : [] );
		$macro_axis_labels   = isset( $shared['macro_axis_labels'] ) && is_array( $shared['macro_axis_labels'] ) ? $shared['macro_axis_labels'] : ( class_exists( 'WSErgo_Country_Macro_Calculator' ) ? WSErgo_Country_Macro_Calculator::macro_axis_labels_ru() : [] );
		$macro_extra_signals_text = isset( $shared['macro_extra_signals_text'] ) ? (string) $shared['macro_extra_signals_text'] : ( class_exists( 'WSErgo_Settings' ) ? (string) get_option( WSErgo_Settings::OPTION_MACRO_EXTRA_SIGNALS_TEXT, '' ) : '' );

		$data_labels_saved = class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::get_data_labels_ru() : [];
		$data_label_keys   = class_exists( 'WSErgo_Country_Macro_Calculator' ) ? WSErgo_Country_Macro_Calculator::all_data_label_keys() : [];
		$wsp_csv_has_datasets  = class_exists( 'WSErgo_Country_Macro_Calculator' ) ? WSErgo_Country_Macro_Calculator::has_macro_csv_data_sources() : false;
		$wsergo_custom_metrics_saved = class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::get_macro_custom_metrics() : [];
		$wsergo_custom_slugs_flip    = [];
		if ( class_exists( 'WSErgo_Settings' ) ) {
			foreach ( WSErgo_Settings::get_macro_custom_metric_slugs() as $_cms ) {
				$wsergo_custom_slugs_flip[ $_cms ] = true;
			}
		}
		$wsergo_cm_ops = [
			'add'       => __( 'A + B (сумма)', 'worldstat-ergonomics' ),
			'sub'       => __( 'A − B (разность)', 'worldstat-ergonomics' ),
			'mul'       => __( 'A × B (произведение)', 'worldstat-ergonomics' ),
			'div'             => __( 'A / B (деление, B≠0)', 'worldstat-ergonomics' ),
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
		$ref_macro_raw_row      = null;
		$ref_macro_example_note = '';
		if ( $macro_ref_country_id > 0 ) {
			$iso2_ref = strtoupper( trim( (string) get_post_meta( $macro_ref_country_id, 'wsp_iso_alpha2', true ) ) );
			if ( strlen( $iso2_ref ) === 2 ) {
				$tref = get_the_title( $macro_ref_country_id );
				$ref_macro_example_note = $tref !== '' ? $tref . ' (' . $iso2_ref . ', ' . (int) $macro_year . ')' : '';
			}
		}

		return array_merge(
			$shared,
			compact(
				'models',
				'active_id',
				'coeffs',
				'active_model',
				'allowed_txt',
				'macro_year',
				'macro_k',
				'macro_ref_country_id',
				'macro_e_w',
				'country_posts_for_ref',
				'stored_cf',
				'cf_for_checkboxes',
				'macro_axis_resolved',
				'signals_ui',
				'macro_axis_labels',
				'macro_extra_signals_text',
				'data_labels_saved',
				'data_label_keys',
				'wsp_csv_has_datasets',
				'wsergo_custom_metrics_saved',
				'wsergo_custom_slugs_flip',
				'wsergo_cm_ops',
				'wsergo_cm_opt',
				'wsergo_cm_cnt',
				'macro_criteria_matrix',
				'macro_criteria_w',
				'macro_criteria_inv',
				'macro_axes_six',
				'ref_macro_raw_row',
				'ref_macro_example_note'
			)
		);
	}

	public static function format_macro_signal_example_display( ?array $raw_row, string $signal ): string {
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
		$rn = round( $f );
		if ( abs( $f - $rn ) < 1e-6 * max( 1.0, abs( $rn ) ) ) {
			return (string) (int) $rn;
		}
		$s = number_format( $f, 12, '.', '' );
		$s = rtrim( rtrim( $s, '0' ), '.' );
		return $s === '' || $s === '-.' ? (string) $f : $s;
	}

	/**
	 * @param string $hook Admin page hook suffix.
	 */
	public static function enqueue_settings_assets( string $hook ): void {
		$is_settings = ( false !== strpos( $hook, 'wsergo-settings' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['page'] ) && 'wsergo-settings' === (string) wp_unslash( $_GET['page'] ) ) {
			$is_settings = true;
		}
		if ( ! $is_settings ) {
			return;
		}

		$js_country = 'public/assets/js/wsergo-settings-country.js';
		$js_tune    = 'public/assets/js/wsergo-cluster-tune.js';
		$url_country = class_exists( 'WSErgo_Level_Registry' ) ? WSErgo_Level_Registry::url( 'country', $js_country ) : WSERGO_URL . 'levels/country/' . $js_country;
		$url_tune    = class_exists( 'WSErgo_Level_Registry' ) ? WSErgo_Level_Registry::url( 'country', $js_tune ) : WSERGO_URL . 'levels/country/' . $js_tune;
		$ver_country = class_exists( 'WSErgo_Level_Registry' ) ? WSErgo_Level_Registry::asset_version( 'country', $js_country ) : WSERGO_VERSION;
		$ver_tune    = class_exists( 'WSErgo_Level_Registry' ) ? WSErgo_Level_Registry::asset_version( 'country', $js_tune ) : WSERGO_VERSION;

		wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', [], '4.4.1', true );
		wp_enqueue_script( 'wsergo-settings-country', $url_country, [ 'jquery', 'chart-js' ], $ver_country, true );
		wp_enqueue_script( 'wsergo-cluster-tune', $url_tune, [ 'jquery' ], $ver_tune, true );
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
					'running'   => __( 'Анализ данных и подбор параметров…', 'worldstat-ergonomics' ),
					'done'      => __( 'Параметры применены к форме. Нажмите «Сохранить настройки» внизу страницы.', 'worldstat-ergonomics' ),
					'saved'     => __( 'Параметры сохранены, кэш пересчёта сброшен.', 'worldstat-ergonomics' ),
					'error'     => __( 'Не удалось выполнить автоподбор.', 'worldstat-ergonomics' ),
					'loadingUi' => __( 'Загрузка списка признаков…', 'worldstat-ergonomics' ),
					'cv'        => __( 'разброс (CV)', 'worldstat-ergonomics' ),
					'coverage'  => __( 'покрытие', 'worldstat-ergonomics' ),
					'selected'  => __( 'в подборе', 'worldstat-ergonomics' ),
				],
			]
		);
		$wsergo_cm_msgs = [
			'maxRules'      => __( 'Не более 30 пользовательских параметров.', 'worldstat-ergonomics' ),
			'needSlug'      => __( 'Укажите итоговый ключ (латиница, snake_case).', 'worldstat-ergonomics' ),
			'needAB'        => __( 'Для этой операции задайте параметры A и B.', 'worldstat-ergonomics' ),
			'needA'         => __( 'Задайте параметр A и при необходимости число.', 'worldstat-ergonomics' ),
			'duplicateSlug' => __( 'Такой итоговый ключ уже есть. Выберите другое имя или удалите правило в матрице.', 'worldstat-ergonomics' ),
			'ajaxFail'      => __( 'Не удалось сохранить параметр. Проверьте консоль или попробуйте ещё раз.', 'worldstat-ergonomics' ),
		];
		wp_localize_script(
			'wsergo-settings-country',
			'wsergoCmSettings',
			[
				'optKey'      => WSErgo_Settings::OPTION_MACRO_CUSTOM_METRICS,
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'maxRules'    => 30,
				'nonceAppend' => wp_create_nonce( 'wsergo_append_custom_metric' ),
				'ajaxAction'  => 'wsergo_append_custom_metric',
				'messages'    => $wsergo_cm_msgs,
				'city'        => [
					'optKey'      => WSErgo_Settings::OPTION_CITY_MACRO_CUSTOM_METRICS,
					'nonceAppend' => wp_create_nonce( 'wsergo_append_city_custom_metric' ),
					'ajaxAction'  => 'wsergo_append_city_custom_metric',
					'messages'    => $wsergo_cm_msgs,
				],
			]
		);
	}
}
