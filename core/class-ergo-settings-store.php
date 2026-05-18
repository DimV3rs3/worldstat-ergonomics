<?php
/**
 * ╨Ю╨┐╤Ж╨╕╨╕: ╨╝╨╛╨┤╨╡╨╗╨╕ DSL, ╨║╨╛╤Н╤Д╤Д╨╕╤Ж╨╕╨╡╨╜╤В╤Л, ╨░╨│╤А╨╡╨│╨░╤Ж╨╕╤П ╨┐╨╛ ╤Г╤А╨╛╨▓╨╜╤П╨╝.
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_Settings {

	public const OPTION_MODELS            = 'wsergo_models';
	public const OPTION_ACTIVE_MODEL       = 'wsergo_active_model_id';
	public const OPTION_COEFFICIENTS      = 'wsergo_coefficients';
	public const OPTION_AGGREGATION       = 'wsergo_aggregation';
	public const OPTION_COUNTRY_VARIATION = 'wsergo_country_variation';
	/** ╨Ш╤Б╤В╨╛╤З╨╜╨╕╨║ ╨╕╨╜╨┤╨╡╨║╤Б╨░ ╤Б╤В╤А╨░╨╜╤Л: ╨╝╨░╨║╤А╨╛╨┤╨░╨╜╨╜╤Л╨╡ (CSV ╨┐╨╗╨░╤В╤Д╨╛╤А╨╝╤Л) ╨╕╨╗╨╕ ╨░╨│╤А╨╡╨│╨░╤Ж╨╕╤П ╨┐╨╛ ╨│╨╛╤А╨╛╨┤╨░╨╝ (legacy). */
	public const OPTION_COUNTRY_INDEX_SOURCE = 'wsergo_country_index_source';
	/** ╨Ю╨┐╨╛╤А╨╜╤Л╨╣ ╨│╨╛╨┤ ╨┤╨╗╤П ╤А╤П╨┤╨╛╨▓ country_code+year ╨▓ CSV ╨╝╨░╨║╤А╨╛╨╝╨╛╨┤╨╡╨╗╨╕. */
	public const OPTION_MACRO_REFERENCE_YEAR = 'wsergo_macro_reference_year';
	/** ID ╨┐╨╛╤Б╤В╨░ wsp_country тАФ ╤Н╤В╨░╨╗╨╛╨╜ ╨┤╨╗╤П ╨║╨╛╨╗╨╛╨╜╨║╨╕ ┬л╨Я╤А╨╕╨╝╨╡╤А ╨┤╨░╨╜╨╜╤Л╤Е┬╗ ╨╜╨░ ╨▓╨║╨╗╨░╨┤╨║╨╡ ┬л╨д╨╛╤А╨╝╤Г╨╗╨░┬╗ ╤Б╤В╤А╨░╨╜╤Л. */
	public const OPTION_MACRO_REFERENCE_COUNTRY_POST_ID = 'wsergo_macro_reference_country_post_id';
	/** ╨з╨╕╤Б╨╗╨╛ ╨║╨╗╨░╤Б╤В╨╡╤А╨╛╨▓ k-means ╨┤╨╗╤П ╨╝╨░╨║╤А╨╛╨╝╨╛╨┤╨╡╨╗╨╕ (╨┐╨╛ ╤Г╨╝╨╛╨╗╤З╨░╨╜╨╕╤О 6). */
	public const OPTION_MACRO_K_CLUSTERS     = 'wsergo_macro_k_clusters';
	/**
	 * ╨п╨▓╨╜╨░╤П ╨┐╤А╨╕╨▓╤П╨╖╨║╨░ ╨▒╨░╨╖╨╛╨▓╨╛╨│╨╛ ╤А╤П╨┤╨░ ╨╝╨░╨║╤А╨╛ (╨║╨╗╤О╤З ingest) тЖТ id ╤Б╤В╤А╨╛╨║╨╕ CSV ╨▓ ╤В╨░╨▒╨╗╨╕╤Ж╨╡ wsp_csv_datasets.
	 * ╨Я╤Г╤Б╤В╨╛╨╡ ╨╖╨╜╨░╤З╨╡╨╜╨╕╨╡ ╨▓ ╤Д╨╛╤А╨╝╨╡ тАФ ╨░╨▓╤В╨╛-╨╛╨┐╤А╨╡╨┤╨╡╨╗╨╡╨╜╨╕╨╡ ╨┐╨╛ ╨╕╨╝╨╡╨╜╨╕ ╤Д╨░╨╣╨╗╨░, ╨║╨░╨║ ╤А╨░╨╜╤М╤И╨╡.
	 */
	public const OPTION_MACRO_CSV_BINDINGS = 'wsergo_macro_csv_bindings';
	/** ╨Т╨╡╤Б╨░ ╤И╨╡╤Б╤В╨╕ ╨╛╤Б╨╡╨╣ ╨╝╨░╨║╤А╨╛ (F, Cm, H, A, S, Ct) ╨┐╤А╨╕ ╤Б╨▒╨╛╤А╨║╨╡ ╨╕╤В╨╛╨│╨╛╨▓╨╛╨│╨╛ E; ╨┐╨╛╤Б╨╗╨╡ ╤Б╨╛╤Е╤А╨░╨╜╨╡╨╜╨╕╤П ╨╜╨╛╤А╨╝╨░╨╗╨╕╨╖╤Г╤О╤В╤Б╤П ╨║ ╤Б╤Г╨╝╨╝╨╡ 1. */
	public const OPTION_MACRO_E_AXIS_WEIGHTS = 'wsergo_macro_e_axis_weights';
	/** ╨Я╤А╨╕╨╖╨╜╨░╨║╨╕ ╨┤╨╗╤П k-means (╤Б╨┐╨╕╤Б╨╛╨║ ╨║╨╗╤О╤З╨╡╨╣ ╨╕╨╖ macro_signal_allowlist); ╨┐╤Г╤Б╤В╨╛ ╨╕╨╗╨╕ ╨╝╨╡╨╜╤М╤И╨╡ ╨┤╨▓╤Г╤Е тАФ ╨▓╤Б╤В╤А╨╛╨╡╨╜╨╜╤Л╨╣ ╨╜╨░╨▒╨╛╤А ╨┐╨╗╨░╨│╨╕╨╜╨░. */
	public const OPTION_MACRO_CLUSTER_FEATURES = 'wsergo_macro_cluster_features';
	/**
	 * ╨Ф╨╛╨┐╨╛╨╗╨╜╨╕╤В╨╡╨╗╤М╨╜╤Л╨╡ ╨║╨╗╤О╤З╨╕ ╤Б╨╕╨│╨╜╨░╨╗╨╛╨▓ ╨╝╨░╨║╤А╨╛ (╨╛╨┤╨╜╨░ ╤Б╤В╤А╨╛╨║╨░ = ╨╛╨┤╨╕╨╜ ╨╗╨░╤В╨╕╨╜╤Б╨║╨╕╨╣ ╨║╨╗╤О╤З), ╨┐╨╛╤П╨▓╨╗╤П╤О╤В╤Б╤П ╨▓ k-means ╨╕ ╨▓ ╤Д╨╛╤А╨╝╤Г╨╗╨░╤Е ╨╛╤Б╨╡╨╣ ╨▒╨╡╨╖ ╨┐╤А╨░╨▓╨║╨╕ PHP.
	 * ╨б╤В╨╛╨╗╨▒╨╡╤Ж wide-CSV ╨┐╨╛╤Б╨╗╨╡ ╨╜╨╛╤А╨╝╨░╨╗╨╕╨╖╨░╤Ж╨╕╨╕ ╨╖╨░╨│╨╛╨╗╨╛╨▓╨║╨░ ╨┤╨╛╨╗╨╢╨╡╨╜ ╤Б╨╛╨▓╨┐╨░╨┤╨░╤В╤М ╤Б ╨║╨╗╤О╤З╨╛╨╝ (╨╕╨╗╨╕ ╨╖╨░╨┤╨░╤В╤М ╤Б╨╛╨┐╨╛╤Б╤В╨░╨▓╨╗╨╡╨╜╨╕╨╡ ╨▓ ╨║╨╛╨┤╨╡ wide_csv_column_to_signal).
	 */
	public const OPTION_MACRO_EXTRA_SIGNALS_TEXT = 'wsergo_macro_extra_signals_text';
	/**
	 * ╨Я╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╤М╤Б╨║╨╕╨╡ ╨┐╤А╨╛╨╕╨╖╨▓╨╛╨┤╨╜╤Л╨╡ ╨┐╨╛╨║╨░╨╖╨░╤В╨╡╨╗╨╕: ╤Д╨╛╤А╨╝╤Г╨╗╤Л ╨╛╤В ╨┤╨▓╤Г╤Е ╤Б╤В╨╛╨╗╨▒╤Ж╨╛╨▓ ╨╕╨╗╨╕ ╨╝╨░╤Б╤И╤В╨░╨▒╨╕╤А╨╛╨▓╨░╨╜╨╕╨╡ ╨╛╨┤╨╜╨╛╨│╨╛ (╤Б╨╝. ╨░╨┤╨╝╨╕╨╜╨║╤Г ┬л╨Ф╨░╨╜╨╜╤Л╨╡┬╗).
	 *
	 * @var string
	 */
	public const OPTION_MACRO_CUSTOM_METRICS = 'wsergo_macro_custom_metrics';
	/**
	 * ╨д╨╛╤А╨╝╤Г╨╗╤Л ╨╝╨░╨║╤А╨╛-╨╛╤Б╨╡╨╣: ╨┤╨╗╤П F, Cm, H, A, S, Ct тАФ ╤Б╨┐╨╕╤Б╨╛╨║ ╤Б╨╗╨░╨│╨░╨╡╨╝╤Л╤Е { signal, invert, weight }.
	 * ╨Я╤Г╤Б╤В╨░╤П ╨╛╤Б╤М ╨▓ ╨╛╨┐╤Ж╨╕╨╕ тАФ ╨┐╨╛╨┤╤Б╤В╨░╨▓╨╗╤П╨╡╤В╤Б╤П ╨▓╤Б╤В╤А╨╛╨╡╨╜╨╜╨░╤П ╨╝╨╡╤В╨╛╨┤╨╕╨║╨░.
	 */
	public const OPTION_MACRO_AXIS_TERMS = 'wsergo_macro_axis_terms';
	/** ╨Я╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╤М╤Б╨║╨╕╨╡ ╤А╤Г╤Б╤Б╨║╨╕╨╡ ╨┐╨╛╨┤╨┐╨╕╤Б╨╕ ╨║ ╤В╨╡╤Е╨╜╨╕╤З╨╡╤Б╨║╨╕╨╝ ╨║╨╗╤О╤З╨░╨╝ ╨┤╨░╨╜╨╜╤Л╤Е (╨┐╨╡╤А╨╡╨╛╨┐╤А╨╡╨┤╨╡╨╗╤П╤О╤В ╨▓╤Б╤В╤А╨╛╨╡╨╜╨╜╤Л╨╡). */
	public const OPTION_DATA_LABELS_RU = 'wsergo_data_labels_ru';
	/** ╨Ь╨░╤В╤А╨╕╤Ж╨░: ╨┐╤А╨╕╨╖╨╜╨░╨║ тЖТ ╨┤╨╗╤П ╨║╨░╨║╨╕╤Е ╨╛╤Б╨╡╨╣ ╨▓╨║╨╗╤О╤З╤С╨╜ (╨║╨╗╤О╤З╨╕ F, Cm, H, A, S, Ct). */
	public const OPTION_MACRO_CRITERIA_MATRIX = 'wsergo_macro_criteria_matrix';
	/** ╨Т╨╡╤Б╨░ ╨▓╨╜╤Г╤В╤А╨╕ ╨╛╤Б╨╕: ╨┐╤А╨╕╨╖╨╜╨░╨║ тЖТ ╨╛╤Б╤М тЖТ ╨▓╨╡╤Б (╨╜╨╡╨╛╨▒╤П╨╖╨░╤В╨╡╨╗╤М╨╜╨╛; ╨┐╤Г╤Б╤В╨╛ = ╨░╨▓╤В╨╛, ╨╛╤Б╤В╨░╤В╨╛╨║ ╨╛╤В 1 ╨┐╨╛╤Б╨╗╨╡ ╤А╤Г╤З╨╜╤Л╤Е). */
	public const OPTION_MACRO_CRITERIA_WEIGHTS = 'wsergo_macro_criteria_weights';
	/** ╨Ш╨╜╨▓╨╡╤А╤Б╨╕╤П 1тИТx: ╨┐╤А╨╕╨╖╨╜╨░╨║ тЖТ ╨╛╤Б╤М тЖТ bool (╨╡╤Б╨╗╨╕ ╨╜╨╡╤В ╨║╨╗╤О╤З╨░ тАФ ╨▒╨╡╤А╤С╤В╤Б╤П ╨╕╨╖ ╨▓╤Б╤В╤А╨╛╨╡╨╜╨╜╨╛╨╣ ╨╝╨╡╤В╨╛╨┤╨╕╨║╨╕). */
	public const OPTION_MACRO_CRITERIA_INVERTS = 'wsergo_macro_criteria_inverts';
	/** ╨б╨╛╨┐╨╛╤Б╤В╨░╨▓╨╗╨╡╨╜╨╕╨╡ ╨┐╨╛╨╗╨╡╨╣ ╨╖╨░╨┐╨╕╤Б╨╕ wsp_city тЖТ id ╨┐╨╛╨║╨░╨╖╨░╤В╨╡╨╗╤П ╤Н╤А╨│╨╛╨╜╨╛╨╝╨╕╨║╨╕. */
	public const OPTION_CITY_FIELD_MAP    = 'wsergo_city_field_map';
	/** Явная привязка базовых городских метрик к CSV-источникам в БД. */
	public const OPTION_CITY_CSV_BINDINGS = 'wsergo_city_csv_bindings';
	/**
	 * @deprecated 1.4.8 ╨а╨░╨╜╤М╤И╨╡ тАФ ╨▓╤Л╨▒╨╛╤А ╤Н╤В╨░╨╗╨╛╨╜╨╜╨╛╨│╨╛ ╨│╨╛╤А╨╛╨┤╨░ ╨┤╨╗╤П ╨┐╤А╨╡╨▓╤М╤О ╨▓ ╨░╨┤╨╝╨╕╨╜╨║╨╡. ╨Я╤А╨╡╨▓╤М╤О ╤Б╤В╤А╨╛╨╕╤В╤Б╤П ╨┐╨╛ ╤Н╤В╨░╨╗╨╛╨╜╨╜╨╛╨╣ ╤Б╤В╤А╨░╨╜╨╡
	 * ╨╕ ╨┐╨╡╤А╨▓╨╛╨╝╤Г ╨│╨╛╤А╨╛╨┤╤Г ╤Н╤В╨╛╨╣ ╤Б╤В╤А╨░╨╜╤Л; ╨╛╨┐╤Ж╨╕╤П ╨▓ ╨С╨Ф ╨╝╨╛╨╢╨╡╤В ╨╛╤Б╤В╨░╤В╤М╤Б╤П, ╨▓ ╤Д╨╛╤А╨╝╨╡ ╨╜╨╡ ╨╕╤Б╨┐╨╛╨╗╤М╨╖╤Г╨╡╤В╤Б╤П.
	 */
	public const OPTION_CITY_REFERENCE_POST_ID = 'wsergo_city_reference_post_id';
	/** ╨Т╨╡╤А╤Б╨╕╤П ╨╝╨╡╤В╨╛╨┤╨╕╨║╨╕ ╨┤╨╗╤П ╨│╨╛╤А╨╛╨┤╤Б╨║╨╛╨│╨╛ ╨▒╨╗╨╛╨║╨░ (╨╛╤В╨┤╨╡╨╗╤М╨╜╨╛ ╨╛╤В ╤Б╤В╤А╨░╨╜╤Л). */
	public const OPTION_CITY_METHODOLOGY_VERSION = 'wsergo_city_methodology_version';
	public const OPTION_CITY_MODELS            = 'wsergo_city_models';
	public const OPTION_CITY_ACTIVE_MODEL      = 'wsergo_city_active_model_id';
	public const OPTION_CITY_COEFFICIENTS      = 'wsergo_city_coefficients';
	public const OPTION_CITY_MACRO_CUSTOM_METRICS = 'wsergo_city_macro_custom_metrics';
	public const OPTION_CITY_DATA_LABELS_RU    = 'wsergo_city_data_labels_ru';
	public const OPTION_CITY_MACRO_CRITERIA_MATRIX = 'wsergo_city_macro_criteria_matrix';
	public const OPTION_CITY_MACRO_CRITERIA_WEIGHTS = 'wsergo_city_macro_criteria_weights';
	public const OPTION_CITY_MACRO_CRITERIA_INVERTS = 'wsergo_city_macro_criteria_inverts';
	public const OPTION_CITY_MACRO_CLUSTER_FEATURES = 'wsergo_city_macro_cluster_features';
	public const OPTION_CITY_MACRO_EXTRA_SIGNALS_TEXT = 'wsergo_city_macro_extra_signals_text';
	public const OPTION_CITY_MACRO_REFERENCE_YEAR = 'wsergo_city_macro_reference_year';
	public const OPTION_CITY_MACRO_K_CLUSTERS   = 'wsergo_city_macro_k_clusters';
	public const OPTION_CITY_MACRO_E_AXIS_WEIGHTS = 'wsergo_city_macro_e_axis_weights';
	public const OPTION_CITY_MACRO_CSV_BINDINGS = 'wsergo_city_macro_csv_bindings';
	/** ╨Ш╤Б╤В╨╛╤З╨╜╨╕╨║ ┬л╨╝╨░╨║╤А╨╛┬╗ ╨┤╨╗╤П ╨│╨╛╤А╨╛╨┤╨░ (╨┐╨╛╨║╨░ ╤В╨╛╨╗╤М╨║╨╛ macro_datasets тАФ ╤В╨╡ ╨╢╨╡ CSV ╨┐╨╗╨░╤В╤Д╨╛╤А╨╝╤Л). */
	public const OPTION_CITY_INDEX_SOURCE        = 'wsergo_city_index_source';

	/**
	 * ╨Ь╨╛╨┤╨╡╨╗╤М ╨┐╨╛ ╤Г╨╝╨╛╨╗╤З╨░╨╜╨╕╤О: ╨┐╤Г╤Б╤В╨░╤П leaf-╤Д╨╛╤А╨╝╤Г╨╗╨░ тАФ ╨╕╤Б╨┐╨╛╨╗╤М╨╖╤Г╨╡╤В╤Б╤П ╨▓╨╖╨▓╨╡╤И╨╡╨╜╨╜╨╛╨╡ ╤Б╤А╨╡╨┤╨╜╨╡╨╡ ╨╕╨╖ WSErgo_Model.
	 *
	 * @return array<int, array{id:string,name:string,leaf_formula:string}>
	 */
	public static function get_default_models(): array {
		return [
			[
				'id'            => 'default_weighted',
				'name'          => __( 'Взвешенное среднее (классика)', 'worldstat-ergonomics' ),
				'leaf_formula'  => '',
			],
		];
	}

	/**
	 * @return array<int, array{id:string,name:string,leaf_formula:string}>
	 */
	/**
	 * Исправление битой UTF-8 в названиях моделей (старые сохранения в option).
	 *
	 * @param array<int, array<string, mixed>> $models
	 * @return array<int, array{id:string,name:string,leaf_formula:string}>
	 */
	private static function normalize_model_names( array $models ): array {
		$defaults = [
			'default_weighted' => __( 'Взвешенное среднее (классика)', 'worldstat-ergonomics' ),
		];
		foreach ( $models as $i => $m ) {
			if ( ! is_array( $m ) ) {
				continue;
			}
			$id = isset( $m['id'] ) ? (string) $m['id'] : '';
			$nm = isset( $m['name'] ) ? (string) $m['name'] : '';
			if ( $id !== '' && isset( $defaults[ $id ] ) && ( $nm === '' || false !== strpos( $nm, '╨' ) || ! preg_match( '/[\x{0400}-\x{04FF}]/u', $nm ) ) ) {
				$models[ $i ]['name'] = $defaults[ $id ];
			}
		}
		return $models;
	}

	public static function get_models(): array {
		$stored = get_option( self::OPTION_MODELS, null );
		if ( ! is_array( $stored ) || ! $stored ) {
			return self::get_default_models();
		}
		return self::normalize_model_names( $stored );
	}

	/**
	 * @return array{id:string,name:string,leaf_formula:string}|null
	 */
	public static function get_active_model(): ?array {
		$id      = (string) get_option( self::OPTION_ACTIVE_MODEL, 'default_weighted' );
		$models  = self::get_models();
		foreach ( $models as $m ) {
			if ( isset( $m['id'] ) && $m['id'] === $id ) {
				return $m;
			}
		}
		return $models[0] ?? null;
	}

	/**
	 * ╨У╨╗╨╛╨▒╨░╨╗╤М╨╜╤Л╨╡ ╨║╨╛╤Н╤Д╤Д╨╕╤Ж╨╕╨╡╨╜╤В╤Л k_* (╤З╨╕╤Б╨╗╨╛).
	 *
	 * @return array<string, float>
	 */
	public static function get_coefficients(): array {
		$stored = get_option( self::OPTION_COEFFICIENTS, [] );
		if ( ! is_array( $stored ) ) {
			$stored = [];
		}
		$out = [ 'k_default' => 1.0 ];
		foreach ( $stored as $k => $v ) {
			$key = sanitize_key( (string) $k );
			if ( $key === '' ) {
				continue;
			}
			if ( substr( $key, 0, 2 ) !== 'k_' ) {
				$key = 'k_' . $key;
			}
			$out[ $key ] = (float) $v;
		}
		return $out;
	}

	/**
	 * @return array{
	 *   room_to_building: string,
	 *   w_building_in_district: float,
	 *   w_yard_in_district: float,
	 *   district_to_city: string,
	 *   city_to_region: string,
	 *   region_to_country: string
	 * }
	 */
	public static function get_aggregation(): array {
		$defaults = [
			'room_to_building'       => 'mean',
			'w_building_in_district' => 0.5,
			'w_yard_in_district'     => 0.5,
			'district_to_city'       => 'mean',
			'city_to_region'         => 'pop_weighted',
			'region_to_country'      => 'pop_weighted',
		];
		$stored = get_option( self::OPTION_AGGREGATION, [] );
		if ( ! is_array( $stored ) ) {
			$stored = [];
		}
		$out = array_merge( $defaults, $stored );
		$sum = (float) $out['w_building_in_district'] + (float) $out['w_yard_in_district'];
		if ( $sum > 0 ) {
			$out['w_building_in_district'] = (float) $out['w_building_in_district'] / $sum;
			$out['w_yard_in_district']     = (float) $out['w_yard_in_district'] / $sum;
		}
		return $out;
	}

	/**
	 * ╨У╨╗╨╛╨▒╨░╨╗╤М╨╜╤Л╨╣ ╤А╨╡╨╢╨╕╨╝ ╤А╨░╤Б╤З╨╡╤В╨░:
	 * - city_direct: ╨│╨╛╤А╨╛╨┤╤Б╨║╨╛╨╣ (╨║╨░╨║ ╨▓ ╨╕╤Б╤Е╨╛╨┤╨╜╨╛╨╣ ╨▓╨╡╤А╤Б╨╕╨╕, ╤Б╤В╤А╨░╨╜╨░ ╨░╨│╤А╨╡╨│╨╕╤А╤Г╨╡╤В╤Б╤П ╨┐╨╛ ╨│╨╛╤А╨╛╨┤╨░╨╝).
	 * - regions: ╤Б╤В╤А╨░╨╜╨╛╨▓╨╛╨╣ (╨▓╨░╤А╨╕╨░╤Ж╨╕╤П 2, ╤Б╤В╤А╨░╨╜╨░ ╨░╨│╤А╨╡╨│╨╕╤А╤Г╨╡╤В╤Б╤П ╤З╨╡╤А╨╡╨╖ ╤А╨╡╨│╨╕╨╛╨╜╤Л).
	 */
	public static function get_country_variation(): string {
		$mode = (string) get_option( self::OPTION_COUNTRY_VARIATION, 'city_direct' );
		return in_array( $mode, [ 'city_direct', 'regions' ], true ) ? $mode : 'city_direct';
	}

	/**
	 * @return string macro_datasets|city_aggregate
	 */
	public static function get_country_index_source(): string {
		$mode = (string) get_option( self::OPTION_COUNTRY_INDEX_SOURCE, 'macro_datasets' );
		return in_array( $mode, [ 'macro_datasets', 'city_aggregate' ], true ) ? $mode : 'macro_datasets';
	}

	public static function get_macro_reference_year(): int {
		$y   = (int) get_option( self::OPTION_MACRO_REFERENCE_YEAR, 2022 );
		$min = class_exists( 'WorldStat_Platform_Years' ) ? max( 1900, WorldStat_Platform_Years::min() ) : 1900;
		$max = class_exists( 'WorldStat_Platform_Years' ) ? max( 2100, WorldStat_Platform_Years::max() ) : 2100;
		return max( $min, min( $max, $y ) );
	}

	public static function get_macro_reference_country_post_id(): int {
		$id = (int) get_option( self::OPTION_MACRO_REFERENCE_COUNTRY_POST_ID, 0 );
		return $id > 0 ? $id : 0;
	}

	public static function get_macro_k_clusters(): int {
		$k = (int) get_option( self::OPTION_MACRO_K_CLUSTERS, 6 );
		return max( 2, min( 12, $k ) );
	}

	/**
	 * ╨Ф╨╛╨┐╤Г╤Б╤В╨╕╨╝╤Л╨╡ ╨║╨╗╤О╤З╨╕ ╨┤╨╗╤П ╨┐╤А╨╕╨▓╤П╨╖╨║╨╕ CSV ╨║ ╨▒╨░╨╖╨╛╨▓╤Л╨╝ ╤А╤П╨┤╨░╨╝ ╨╝╨░╨║╤А╨╛╨╝╨╛╨┤╨╡╨╗╨╕ (╤Б╨╝. WSErgo_Country_Macro_Calculator).
	 *
	 * @return list<string>
	 */
	public static function macro_bindable_metric_keys(): array {
		if ( class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			return WSErgo_Country_Macro_Calculator::bindable_standard_metric_keys();
		}
		return [];
	}

	/**
	 * Ключи городских метрик, которые можно привязать к CSV.
	 *
	 * @return list<string>
	 */
	public static function city_bindable_metric_keys(): array {
		return [
			'pop_t3',
			'density_builtup',
			'density_extent',
			'saturation',
			'openness',
			'proximity',
			'cohesion',
			'builtup_t3',
			'extent_t3',
			'walkability',
			'road_share',
			'road_width',
			'arterial_density',
			'arterial_distance',
			'block_size',
			'intersect_3way',
			'intersect_4way',
			'intersect_4way_share',
			'greenspace_percent',
		];
	}

	/**
	 * Id ╤Б╤В╤А╨╛╨║ wsp_csv_datasets, ╨║╨╛╤В╨╛╤А╤Л╨╡ ╨╝╨╛╨╢╨╜╨╛ ╨╕╤Б╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╤М ╨║╨░╨║ ╨╕╤Б╤В╨╛╤З╨╜╨╕╨║ ╨╝╨░╨║╤А╨╛╤А╤П╨┤╨╛╨▓ (╤В╨╕╨┐ ╨╜╨░╨▒╨╛╤А╨░ ╨╕╨╖ ╤А╨░╤Б╤З╤С╤В╨╜╤Л╤Е).
	 *
	 * @return array<int, true>
	 */
	public static function valid_macro_csv_source_ids(): array {
		if ( ! class_exists( 'WorldStat_Uploaded_Csv' ) ) {
			return [];
		}
		$out = [];
		foreach ( WorldStat_Uploaded_Csv::list_files() as $row ) {
			$id = (int) ( $row['id'] ?? 0 );
			if ( $id < 1 ) {
				continue;
			}
			$kind = isset( $row['dataset_kind'] ) ? (string) $row['dataset_kind'] : '';
			if ( WorldStat_Uploaded_Csv::is_calculation_source_kind( $kind ) ) {
				$out[ $id ] = true;
			}
		}
		return $out;
	}

	/**
	 * ╨Э╨╛╤А╨╝╨░╨╗╨╕╨╖╤Г╨╡╤В ╤Б╨╛╤Е╤А╨░╨╜╤С╨╜╨╜╤Г╤О ╨╛╨┐╤Ж╨╕╤О ╨┐╤А╨╕╨▓╤П╨╖╨╛╨║: ╤В╨╛╨╗╤М╨║╨╛ ╨┤╨╛╨┐╤Г╤Б╤В╨╕╨╝╤Л╨╡ ╨║╨╗╤О╤З╨╕ ╨╕ id, ╤Б╤Г╤Й╨╡╤Б╤В╨▓╤Г╤О╤Й╨╕╨╡ ╨▓ ╨С╨Ф.
	 *
	 * @param array<string, mixed> $raw
	 * @return array<string, int>
	 */
	public static function normalize_macro_csv_bindings_option( array $raw ): array {
		$allowed = array_flip( self::macro_bindable_metric_keys() );
		$valid   = self::valid_macro_csv_source_ids();
		$out     = [];
		foreach ( $raw as $k => $v ) {
			$mk = sanitize_key( (string) $k );
			$id = (int) $v;
			if ( $mk === '' || $id < 1 || ! isset( $allowed[ $mk ] ) || ! isset( $valid[ $id ] ) ) {
				continue;
			}
			$out[ $mk ] = $id;
		}
		ksort( $out );
		return $out;
	}

	/**
	 * ╨г╨┤╨░╨╗╤П╨╡╤В ╨╕╨╖ ╨╛╨┐╤Ж╨╕╨╕ ╨┐╤А╨╕╨▓╤П╨╖╨║╨╕ ╨║ ╨╜╨╡╤Б╤Г╤Й╨╡╤Б╤В╨▓╤Г╤О╤Й╨╕╨╝ CSV ╨╕ ╤Б╨╛╤Е╤А╨░╨╜╤П╨╡╤В ╨┐╤А╨╕ ╨╕╨╖╨╝╨╡╨╜╨╡╨╜╨╕╨╕ (╤Б╨▒╤А╨╛╤Б ╨║╤Н╤И╨░ ╨╝╨░╨║╤А╨╛ ╨┐╤А╨╕ ╤А╨╡╨░╨╗╤М╨╜╨╛╨╣ ╨┐╤А╨░╨▓╨║╨╡ ╨╛╨┐╤Ж╨╕╨╕).
	 * ╨Т╤Л╨╖╤Л╨▓╨░╨╡╤В╤Б╤П ╨┐╤А╨╕ ╤Б╨╝╨╡╨╜╨╡ ╤А╨╡╨▓╨╕╨╖╨╕╨╕ ╤Д╨░╨╣╨╗╨╛╨▓ ╨┐╨╗╨░╤В╤Д╨╛╤А╨╝╤Л ╨╕ ╨┐╤А╨╕ ╨╛╤В╨║╤А╤Л╤В╨╕╨╕ ╤Б╤В╤А╨░╨╜╨╕╤Ж╤Л ╨╜╨░╤Б╤В╤А╨╛╨╡╨║ ╤Н╤А╨│╨╛╨╜╨╛╨╝╨╕╨║╨╕.
	 */
	public static function sync_macro_csv_bindings_with_storage(): void {
		$raw = get_option( self::OPTION_MACRO_CSV_BINDINGS, [] );
		if ( ! is_array( $raw ) ) {
			$raw = [];
		}
		$desired     = self::normalize_macro_csv_bindings_option( $raw );
		$needs_write = false;
		$bindable    = self::macro_bindable_metric_keys();
		$bind_flip   = array_flip( $bindable );

		foreach ( $raw as $k => $v ) {
			$mk = sanitize_key( (string) $k );
			if ( $mk === '' ) {
				continue;
			}
			if ( ! isset( $bind_flip[ $mk ] ) ) {
				$needs_write = true;
				break;
			}
		}

		if ( ! $needs_write ) {
			foreach ( $bindable as $mk ) {
				$in_r = array_key_exists( $mk, $raw );
				$in_d = array_key_exists( $mk, $desired );
				if ( $in_r !== $in_d ) {
					$needs_write = true;
					break;
				}
				if ( $in_r && $in_d && (int) $raw[ $mk ] !== (int) $desired[ $mk ] ) {
					$needs_write = true;
					break;
				}
			}
		}

		if ( ! $needs_write && count( $raw ) !== count( array_intersect_key( $raw, $bind_flip ) ) ) {
			$needs_write = true;
		}

		if ( ! $needs_write ) {
			return;
		}

		$updated = update_option( self::OPTION_MACRO_CSV_BINDINGS, $desired );
		if ( $updated && class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			WSErgo_Country_Macro_Calculator::flush_cache();
		}
	}

	/**
	 * @return array<string, int> metric_key => csv row id (>0)
	 */
	public static function get_macro_csv_bindings(): array {
		$raw = get_option( self::OPTION_MACRO_CSV_BINDINGS, [] );
		if ( ! is_array( $raw ) ) {
			return [];
		}
		return self::normalize_macro_csv_bindings_option( $raw );
	}

	/**
	 * ╨Т╨╡╤Б╨░ ╨╕╤В╨╛╨│╨╛╨▓╨╛╨│╨╛ E ╨┐╨╛ ╤И╨╡╤Б╤В╨╕ ╨╛╤Б╤П╨╝ ╨╝╨░╨║╤А╨╛ (╨┐╨╛╤А╤П╨┤╨╛╨║: F, Cm, H, A, S, Ct).
	 *
	 * @return array{F:float,Cm:float,H:float,A:float,S:float,Ct:float}
	 */
	public static function get_macro_e_axis_weights(): array {
		$defaults = [
			'F'  => 0.24,
			'Cm' => 0.22,
			'H'  => 0.18,
			'A'  => 0.14,
			'S'  => 0.12,
			'Ct' => 0.10,
		];
		$raw = get_option( self::OPTION_MACRO_E_AXIS_WEIGHTS, [] );
		if ( ! is_array( $raw ) ) {
			$raw = [];
		}
		$out = [];
		foreach ( $defaults as $axis => $def ) {
			$w = isset( $raw[ $axis ] ) ? (float) $raw[ $axis ] : $def;
			$out[ $axis ] = $w > 0 ? $w : $def;
		}
		$sum = array_sum( $out );
		if ( $sum <= 0 ) {
			return $defaults;
		}
		foreach ( $out as $axis => $w ) {
			$out[ $axis ] = $w / $sum;
		}
		return $out;
	}

	/**
	 * ╨Т╨╡╤А╤Б╨╕╤П ╨║╨╛╨╜╤Д╨╕╨│╤Г╤А╨░╤Ж╨╕╨╕ ╨╝╨░╨║╤А╨╛ ╨┤╨╗╤П ╨╕╨╜╨▓╨░╨╗╨╕╨┤╨░╤Ж╨╕╨╕ transient (╨▒╨╡╨╖ ╤Б╨╝╨╡╨╜╤Л CSV).
	 */
	public static function macro_config_hash(): string {
		return md5(
			wp_json_encode(
				[
					'b'  => self::get_macro_csv_bindings(),
					'w'  => self::get_macro_e_axis_weights(),
					'cf' => self::get_macro_cluster_features(),
					'es' => self::get_macro_extra_signals_effective(),
					'cx' => self::get_macro_custom_metrics(),
					'cm' => self::get_macro_criteria_matrix(),
					'cw' => self::get_macro_criteria_weights(),
					'ci' => self::get_macro_criteria_inverts(),
				]
			)
		);
	}

	/**
	 * ╨е╤Н╤И ╨╜╨░╤Б╤В╤А╨╛╨╡╨║ ╨╝╨░╨║╤А╨╛╨╝╨╛╨┤╨╡╨╗╨╕ ┬л╨│╨╛╤А╨╛╨┤┬╗ (╨╕╨╜╨▓╨░╨╗╨╕╨┤╨░╤Ж╨╕╤П transient ╨▒╨╡╨╖ ╤Б╨╝╨╡╨╜╤Л CSV).
	 */
	public static function city_macro_config_hash(): string {
		return md5(
			wp_json_encode(
				[
					'b'  => self::get_city_macro_csv_bindings(),
					'w'  => self::get_city_macro_e_axis_weights(),
					'cf' => self::get_city_macro_cluster_features(),
					'es' => self::get_city_macro_extra_signals_effective(),
					'cx' => self::get_city_macro_custom_metrics(),
					'cm' => self::get_city_macro_criteria_matrix(),
					'cw' => self::get_city_macro_criteria_weights(),
					'ci' => self::get_city_macro_criteria_inverts(),
					'yr' => self::get_city_macro_reference_year(),
				]
			)
		);
	}

	/**
	 * ╨Я╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╤М╤Б╨║╨╕╨╡ ╤Д╨╛╤А╨╝╤Г╨╗╤Л ╨┐╤А╨╛╨╕╨╖╨▓╨╛╨┤╨╜╤Л╤Е ╨┐╤А╨╕╨╖╨╜╨░╨║╨╛╨▓ (╨┐╨╛╤Б╨╗╨╡ ╨▓╤Б╤В╤А╨╛╨╡╨╜╨╜╤Л╤Е ╨┐╤А╨╛╨╕╨╖╨▓╨╛╨┤╨╜╤Л╤Е ╨╕╨╖ wide-CSV).
	 *
	 * @return list<array{slug:string,op:string,key_a:string,key_b:string,const:float}>
	 */
	public static function get_macro_custom_metrics(): array {
		$raw = get_option( self::OPTION_MACRO_CUSTOM_METRICS, [] );
		if ( ! is_array( $raw ) ) {
			return [];
		}
		$out = [];
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$slug = sanitize_key( (string) ( $row['slug'] ?? '' ) );
			$op   = sanitize_key( (string) ( $row['op'] ?? '' ) );
			if ( $slug === '' || strlen( $slug ) > 96 || $op === '' ) {
				continue;
			}
			$out[] = array(
				'slug'   => $slug,
				'op'     => $op,
				'key_a'  => sanitize_key( (string) ( $row['key_a'] ?? '' ) ),
				'key_b'  => sanitize_key( (string) ( $row['key_b'] ?? '' ) ),
				'const'  => isset( $row['const'] ) && is_numeric( $row['const'] ) ? (float) $row['const'] : 0.0,
			);
		}
		return $out;
	}

	/**
	 * ╨Ш╤В╨╛╨│╨╛╨▓╤Л╨╡ ╨║╨╗╤О╤З╨╕ ╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╤М╤Б╨║╨╕╤Е ╨┐╨░╤А╨░╨╝╨╡╤В╤А╨╛╨▓ ╨┤╨╗╤П ╤Б╨┐╨╕╤Б╨║╨╛╨▓ ╨╕ ╨╝╨░╤В╤А╨╕╤Ж╤Л.
	 *
	 * @return list<string>
	 */
	public static function get_macro_custom_metric_slugs(): array {
		$out = array();
		foreach ( self::get_macro_custom_metrics() as $row ) {
			if ( ! empty( $row['slug'] ) ) {
				$out[] = (string) $row['slug'];
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * ╨б╨╗╨░╨│╨╕ ╨╕╨╖ POST ╨┐╤А╨╕ ╤Б╨╛╤Е╤А╨░╨╜╨╡╨╜╨╕╨╕ ┬л╨н╤А╨│╨╛╨╜╨╛╨╝╨╕╤З╨╜╨╛╤Б╤В╤М┬╗, ╤З╤В╨╛╨▒╤Л ╨╝╨░╤В╤А╨╕╤Ж╨░ ╨╕ ╨║╤А╨╕╤В╨╡╤А╨╕╨╕ ╨╜╨╡ ╨╛╤В╨▒╤А╨░╤Б╤Л╨▓╨░╨╗╨╕ ╨╜╨╛╨▓╤Л╨╣ ╨║╨╗╤О╤З ╨▓ ╤В╨╛╨╝ ╨╢╨╡ ╨╖╨░╨┐╤А╨╛╤Б╨╡.
	 *
	 * @return list<string>
	 */
	public static function get_macro_custom_metric_slugs_effective(): array {
		$from_db = self::get_macro_custom_metric_slugs();
		if ( ! is_admin() ) {
			return $from_db;
		}
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) !== 'POST' ) {
			return $from_db;
		}
		if ( ! isset( $_POST['option_page'] ) || (string) wp_unslash( $_POST['option_page'] ) !== 'wsergo_settings' ) {
			return $from_db;
		}
		if ( ! isset( $_POST[ self::OPTION_MACRO_CUSTOM_METRICS ] ) || ! is_array( $_POST[ self::OPTION_MACRO_CUSTOM_METRICS ] ) ) {
			return $from_db;
		}
		$post_slugs = [];
		foreach ( wp_unslash( $_POST[ self::OPTION_MACRO_CUSTOM_METRICS ] ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$s = sanitize_key( (string) ( $row['slug'] ?? '' ) );
			if ( $s !== '' ) {
				$post_slugs[] = $s;
			}
		}
		return array_values( array_unique( array_merge( $from_db, $post_slugs ) ) );
	}

	/**
	 * ╨Э╨░╤З╨░╨╗╤М╨╜╨░╤П ╨┐╨╛╨┤╨┐╨╕╤Б╤М ╨┤╨╗╤П ╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╤М╤Б╨║╨╛╨│╨╛ ╨┐╨╛╨║╨░╨╖╨░╤В╨╡╨╗╤П (╨╝╨╛╨╢╨╜╨╛ ╨╛╤В╤А╨╡╨┤╨░╨║╤В╨╕╤А╨╛╨▓╨░╤В╤М ╨▓ ╤В╨░╨▒╨╗╨╕╤Ж╨╡ ╨┐╨╛╨┤╨┐╨╕╤Б╨╡╨╣).
	 */
	public static function default_ru_label_for_custom_metric_slug( string $slug ): string {
		$slug = sanitize_key( $slug );
		if ( $slug === '' ) {
			return '';
		}
		$readable = str_replace( '_', ' ', $slug );
		return sprintf(
			/* translators: %s: technical metric key (latin snake_case). */
			__( '╨Я╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╤М╤Б╨║╨╕╨╣ ╨┐╨░╤А╨░╨╝╨╡╤В╤А: %s', 'worldstat-ergonomics' ),
			$readable
		);
	}

	/**
	 * @return list<string>
	 */
	public static function parse_macro_extra_signals_string( string $raw ): array {
		$raw = trim( $raw );
		if ( $raw === '' ) {
			return [];
		}
		$out = [];
		foreach ( preg_split( '/\r\n|\n|\r/', $raw ) as $line ) {
			$k = sanitize_key( trim( (string) $line ) );
			if ( $k !== '' && strlen( $k ) <= 96 ) {
				$out[] = $k;
			}
			if ( count( $out ) >= 50 ) {
				break;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * @return list<string>
	 */
	public static function get_macro_extra_signals(): array {
		$raw = get_option( self::OPTION_MACRO_EXTRA_SIGNALS_TEXT, '' );
		return self::parse_macro_extra_signals_string( is_string( $raw ) ? $raw : '' );
	}

	/**
	 * ╨Я╤А╨╕ ╤Б╨╛╤Е╤А╨░╨╜╨╡╨╜╨╕╨╕ ╨╜╨░╤Б╤В╤А╨╛╨╡╨║ ╤Н╤А╨│╨╛╨╜╨╛╨╝╨╕╨║╨╕ ╨┐╨╛╨┤╨╝╨╡╤И╨╕╨▓╨░╨╡╤В ╨╖╨╜╨░╤З╨╡╨╜╨╕╨╡ ╨╕╨╖ POST, ╤З╤В╨╛╨▒╤Л ╨▓ ╤В╨╛╨╝ ╨╢╨╡ ╨╖╨░╨┐╤А╨╛╤Б╨╡ ╤З╨╡╨║╨▒╨╛╨║╤Б╤Л k-means ╨╜╨╡ ╨╛╤В╤Д╨╕╨╗╤М╤В╤А╨╛╨▓╨░╨╗╨╕ ╨╜╨╛╨▓╤Л╨╡ ╨║╨╗╤О╤З╨╕.
	 *
	 * @return list<string>
	 */
	public static function get_macro_extra_signals_effective(): array {
		if ( is_admin() && isset( $_POST['option_page'] ) && (string) wp_unslash( $_POST['option_page'] ) === 'wsergo_settings' && isset( $_POST[ self::OPTION_MACRO_EXTRA_SIGNALS_TEXT ] ) && is_string( $_POST[ self::OPTION_MACRO_EXTRA_SIGNALS_TEXT ] ) ) {
			return self::parse_macro_extra_signals_string( wp_unslash( $_POST[ self::OPTION_MACRO_EXTRA_SIGNALS_TEXT ] ) );
		}
		return self::get_macro_extra_signals();
	}

	/**
	 * ╨Т╤Л╨▒╤А╨░╨╜╨╜╤Л╨╡ ╨┐╤А╨╕╨╖╨╜╨░╨║╨╕ ╨║╨╗╨░╤Б╤В╨╡╤А╨╕╨╖╨░╤Ж╨╕╨╕ (╤Б╤Л╤А╨╛╨╣ ╤Б╨┐╨╕╤Б╨╛╨║ ╨╕╨╖ ╨╛╨┐╤Ж╨╕╨╕).
	 *
	 * @return list<string>
	 */
	public static function get_macro_cluster_features(): array {
		$raw = get_option( self::OPTION_MACRO_CLUSTER_FEATURES, null );
		if ( ! is_array( $raw ) ) {
			return [];
		}
		$allow = array_flip( class_exists( 'WSErgo_Country_Macro_Calculator' ) ? WSErgo_Country_Macro_Calculator::macro_signal_allowlist() : [] );
		$out   = array();
		foreach ( $raw as $x ) {
			$k = sanitize_key( (string) $x );
			if ( $k !== '' && isset( $allow[ $k ] ) ) {
				$out[] = $k;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * ╨б╨╛╤Е╤А╨░╨╜╤С╨╜╨╜╤Л╨╡ ╨┐╨╛╨┤╨┐╨╕╤Б╨╕ key тЖТ ╤А╤Г╤Б╤Б╨║╨╕╨╣ ╤В╨╡╨║╤Б╤В (╤В╨╛╨╗╤М╨║╨╛ ╨╜╨╡╨┐╤Г╤Б╤В╤Л╨╡ ╨┐╨╡╤А╨╡╨╛╨┐╤А╨╡╨┤╨╡╨╗╨╡╨╜╨╕╤П).
	 *
	 * @return array<string, string>
	 */
	public static function get_data_labels_ru(): array {
		$raw = get_option( self::OPTION_DATA_LABELS_RU, [] );
		if ( ! is_array( $raw ) ) {
			return [];
		}
		$out = [];
		foreach ( $raw as $k => $v ) {
			$key = sanitize_key( (string) $k );
			if ( $key === '' || strlen( $key ) > 96 ) {
				continue;
			}
			$text = sanitize_text_field( (string) $v );
			if ( $text !== '' ) {
				$out[ $key ] = $text;
			}
		}
		return $out;
	}

	/**
	 * @return array<string, array<string, true>> ╤Б╨╕╨│╨╜╨░╨╗ тЖТ ╤Б╨┐╨╕╤Б╨╛╨║ ╨╛╤Б╨╡╨╣ ╤Б ╨╛╤В╨╝╨╡╤В╨║╨╛╨╣
	 */
	public static function get_macro_criteria_matrix(): array {
		$raw = get_option( self::OPTION_MACRO_CRITERIA_MATRIX, [] );
		if ( ! is_array( $raw ) || ! class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			return [];
		}
		$allow = array_flip( WSErgo_Country_Macro_Calculator::macro_signal_allowlist() );
		$axes  = [ 'F', 'Cm', 'H', 'A', 'S', 'Ct' ];
		$out   = [];
		foreach ( $raw as $sig => $row ) {
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
	 * @return array<string, array<string, float>> ╤Б╨╕╨│╨╜╨░╨╗ тЖТ ╨╛╤Б╤М тЖТ ╨▓╨╡╤Б (╨║╨░╨║ ╨▓╨▓╤С╨╗ ╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╤М, ╨┤╨╛ ╨╜╨╛╤А╨╝╨░╨╗╨╕╨╖╨░╤Ж╨╕╨╕)
	 */
	public static function get_macro_criteria_weights(): array {
		$raw = get_option( self::OPTION_MACRO_CRITERIA_WEIGHTS, [] );
		if ( ! is_array( $raw ) || ! class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			return [];
		}
		$allow = array_flip( WSErgo_Country_Macro_Calculator::macro_signal_allowlist() );
		$axes  = [ 'F', 'Cm', 'H', 'A', 'S', 'Ct' ];
		$out   = [];
		foreach ( $raw as $sig => $row ) {
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
				$w = (float) str_replace( ',', '.', trim( (string) $row[ $ax ] ) );
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
	 * ╨б╨╛╤Е╤А╨░╨╜╤С╨╜╨╜╤Л╨╡ ╨╕╨╜╨▓╨╡╤А╤Б╨╕╨╕ (╤В╨╛╨╗╤М╨║╨╛ ╤П╨▓╨╜╨╛ ╨╖╨░╨┤╨░╨╜╨╜╤Л╨╡ ╨▓ ╨╜╨░╤Б╤В╤А╨╛╨╣╨║╨░╤Е).
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function get_macro_criteria_inverts(): array {
		$raw = get_option( self::OPTION_MACRO_CRITERIA_INVERTS, [] );
		if ( ! is_array( $raw ) || ! class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			return [];
		}
		$allow = array_flip( WSErgo_Country_Macro_Calculator::macro_signal_allowlist() );
		$axes  = [ 'F', 'Cm', 'H', 'A', 'S', 'Ct' ];
		$out   = [];
		foreach ( $raw as $sig => $row ) {
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
	 * ╨Ш╨╜╨▓╨╡╤А╤Б╨╕╤П ╨┐╨╛ ╤Г╨╝╨╛╨╗╤З╨░╨╜╨╕╤О ╨┤╨╗╤П ╨┐╨░╤А╤Л ╨╛╤Б╤М+╨┐╤А╨╕╨╖╨╜╨░╨║ (╨║╨░╨║ ╨▓╨╛ ╨▓╤Б╤В╤А╨╛╨╡╨╜╨╜╨╛╨╣ ╨╝╨╡╤В╨╛╨┤╨╕╨║╨╡).
	 *
	 * @param array<string, list<array{signal:string, invert:bool, weight:float}>> $def
	 */
	private static function default_invert_for_axis_signal( string $axis, string $signal, array $def ): bool {
		$signal = sanitize_key( $signal );
		foreach ( $def[ $axis ] ?? [] as $row ) {
			if ( (string) ( $row['signal'] ?? '' ) === $signal ) {
				return ! empty( $row['invert'] );
			}
		}
		return false;
	}

	/**
	 * ╨Ш╨╜╨▓╨╡╤А╤Б╨╕╤П ╤Б╨╗╨░╨│╨░╨╡╨╝╨╛╨│╨╛: ╨╕╨╖ ╨╜╨░╤Б╤В╤А╨╛╨╡╨║ ╨╕╨╗╨╕ ╨╕╨╖ ╨▓╤Б╤В╤А╨╛╨╡╨╜╨╜╨╛╨╣ ╨╝╨╡╤В╨╛╨┤╨╕╨║╨╕.
	 *
	 * @param array<string, array<string, bool>> $inv_opt
	 * @param array<string, list<array{signal:string, invert:bool, weight:float}>> $def
	 */
	private static function resolve_matrix_invert( string $axis, string $signal, array $inv_opt, array $def ): bool {
		$signal = sanitize_key( $signal );
		if ( isset( $inv_opt[ $signal ] ) && is_array( $inv_opt[ $signal ] ) && array_key_exists( $axis, $inv_opt[ $signal ] ) ) {
			return (bool) $inv_opt[ $signal ][ $axis ];
		}
		return self::default_invert_for_axis_signal( $axis, $signal, $def );
	}

	/**
	 * ╨б╨╗╨░╨│╨░╨╡╨╝╤Л╨╡ ╨┐╨╛ ╨╝╨░╤В╤А╨╕╤Ж╨╡: ╤А╤Г╤З╨╜╤Л╨╡ ╨▓╨╡╤Б╨░ (╨┤╨╛╨╗╨╕ ╨╛╤В 1) ╨╕ ╨╛╤Б╤В╨░╤В╨╛╨║ ╨┐╨╛╤А╨╛╨▓╨╜╤Г ╨╜╨░ ┬л╨░╨▓╤В╨╛┬╗; ╨╕╨╜╨▓╨╡╤А╤Б╨╕╤П ╨╕╨╖ ╨╜╨░╤Б╤В╤А╨╛╨╡╨║ ╨╕╨╗╨╕ ╨╝╨╡╤В╨╛╨┤╨╕╨║╨╕.
	 * ╨Ф╨╗╤П ╨╛╤Б╨╕ ╨▒╨╡╨╖ ╨╛╤В╨╝╨╡╤В╨╛╨║ ╨┐╨╛╨┤╤Б╤В╨░╨▓╨╗╤П╨╡╤В╤Б╤П ╨▓╤Б╤В╤А╨╛╨╡╨╜╨╜╨░╤П ╨╝╨╡╤В╨╛╨┤╨╕╨║╨░ ╨┐╨╛ ╤Г╨╝╨╛╨╗╤З╨░╨╜╨╕╤О.
	 *
	 * @return array<string, list<array{signal:string, invert:bool, weight:float}>>
	 */
	public static function build_axis_terms_from_criteria_matrix(): array {
		if ( ! class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			return [];
		}
		$def       = WSErgo_Country_Macro_Calculator::default_macro_axis_terms();
		$matrix    = self::get_macro_criteria_matrix();
		$w_opt     = self::get_macro_criteria_weights();
		$inv_opt   = self::get_macro_criteria_inverts();
		$axes      = [ 'F', 'Cm', 'H', 'A', 'S', 'Ct' ];
		$out       = [];
		foreach ( $axes as $ax ) {
			$checked = [];
			foreach ( $matrix as $sig => $axm ) {
				if ( isset( $axm[ $ax ] ) && $axm[ $ax ] ) {
					$checked[] = $sig;
				}
			}
			sort( $checked, SORT_STRING );
			$n = count( $checked );
			if ( $n === 0 ) {
				$out[ $ax ] = $def[ $ax ] ?? [];
				continue;
			}
			$manual_vals = [];
			$auto_sigs   = [];
			foreach ( $checked as $sig ) {
				$rw = $w_opt[ $sig ][ $ax ] ?? null;
				if ( $rw !== null && $rw > 0 ) {
					$manual_vals[ $sig ] = (float) $rw;
				} else {
					$auto_sigs[] = $sig;
				}
			}
			$sum_m   = array_sum( $manual_vals );
			$n_auto  = count( $auto_sigs );
			$n_man   = count( $manual_vals );
			$rows    = [];

			if ( $n_man === 0 ) {
				$eq = $n > 0 ? 1.0 / $n : 0.0;
				foreach ( $checked as $sig ) {
					$rows[] = [
						'signal' => $sig,
						'invert' => self::resolve_matrix_invert( $ax, $sig, $inv_opt, $def ),
						'weight' => $eq,
					];
				}
			} elseif ( $n_auto === 0 ) {
				if ( $sum_m <= 1e-15 ) {
					$eq = 1.0 / $n;
					foreach ( $checked as $sig ) {
						$rows[] = [
							'signal' => $sig,
							'invert' => self::resolve_matrix_invert( $ax, $sig, $inv_opt, $def ),
							'weight' => $eq,
						];
					}
				} elseif ( $sum_m > 1.0 + 1e-9 ) {
					foreach ( $manual_vals as $sig => $v ) {
						$rows[] = [
							'signal' => $sig,
							'invert' => self::resolve_matrix_invert( $ax, $sig, $inv_opt, $def ),
							'weight' => $v / $sum_m,
						];
					}
				} else {
					$rem = 1.0 - $sum_m;
					$each = $n_man > 0 ? $rem / $n_man : 0.0;
					foreach ( $manual_vals as $sig => $v ) {
						$rows[] = [
							'signal' => $sig,
							'invert' => self::resolve_matrix_invert( $ax, $sig, $inv_opt, $def ),
							'weight' => $v + $each,
						];
					}
				}
			} elseif ( $sum_m > 1.0 + 1e-9 ) {
				foreach ( $checked as $sig ) {
					if ( isset( $manual_vals[ $sig ] ) ) {
						$wt = $manual_vals[ $sig ] / $sum_m;
					} else {
						$wt = 0.0;
					}
					$rows[] = [
						'signal' => $sig,
						'invert' => self::resolve_matrix_invert( $ax, $sig, $inv_opt, $def ),
						'weight' => $wt,
					];
				}
			} else {
				$rem    = max( 0.0, 1.0 - $sum_m );
				$each_a = $n_auto > 0 ? $rem / $n_auto : 0.0;
				foreach ( $checked as $sig ) {
					$w_sig = isset( $manual_vals[ $sig ] ) ? $manual_vals[ $sig ] : $each_a;
					$rows[] = [
						'signal' => $sig,
						'invert' => self::resolve_matrix_invert( $ax, $sig, $inv_opt, $def ),
						'weight' => $w_sig,
					];
				}
			}

			$out[ $ax ] = $rows;
		}
		return $out;
	}

	/**
	 * ╨Ш╤В╨╛╨│╨╛╨▓╤Л╨╡ ╤Б╨╗╨░╨│╨░╨╡╨╝╤Л╨╡ ╨┐╨╛ ╨║╨░╨╢╨┤╨╛╨╣ ╨╝╨░╨║╤А╨╛-╨╛╤Б╨╕: ╨╝╨░╤В╤А╨╕╤Ж╨░ ┬л╨Ф╨░╨╜╨╜╤Л╨╡┬╗ + ╨▓╨╡╤Б╨░; ╨┐╤А╨╕ ╨╛╤В╤Б╤Г╤В╤Б╤В╨▓╨╕╨╕ ╨╛╤В╨╝╨╡╤В╨╛╨║ ╨┐╨╛ ╨╛╤Б╨╕ тАФ ╨▓╤Б╤В╤А╨╛╨╡╨╜╨╜╨░╤П ╨╝╨╡╤В╨╛╨┤╨╕╨║╨░.
	 *
	 * @return array<string, list<array{signal:string, invert:bool, weight:float}>>
	 */
	public static function get_macro_axis_terms_resolved(): array {
		if ( ! class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			return [];
		}
		return self::build_axis_terms_from_criteria_matrix();
	}

	/**
	 * @param mixed $rows
	 * @return list<array{signal:string, invert:bool, weight:float}>
	 */
	public static function sanitize_macro_axis_terms_rows( $rows ): array {
		if ( ! is_array( $rows ) || ! class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			return [];
		}
		$allow = array_flip( WSErgo_Country_Macro_Calculator::macro_signal_allowlist() );
		$out   = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$sig = sanitize_key( (string) ( $row['signal'] ?? '' ) );
			if ( $sig === '' || ! isset( $allow[ $sig ] ) ) {
				continue;
			}
			$wt = isset( $row['weight'] ) ? (float) $row['weight'] : 0.0;
			if ( $wt <= 0 ) {
				continue;
			}
			$inv = ! empty( $row['invert'] );
			$out[] = array(
				'signal' => $sig,
				'invert' => $inv,
				'weight' => $wt,
			);
			if ( count( $out ) >= 50 ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * ╨Я╤А╨╕╨▓╤П╨╖╨║╨╕ CSV ╨┤╨╗╤П ╨│╨╛╤А╨╛╨┤╤Б╨║╨╛╨│╨╛ ╨▒╨╗╨╛╨║╨░ (╨╛╤З╨╕╤Б╤В╨║╨░ ╨┐╤А╨╕ ╨╛╤В╨║╤А╤Л╤В╨╕╨╕ ╨╜╨░╤Б╤В╤А╨╛╨╡╨║).
	 */
	public static function sync_city_macro_csv_bindings_with_storage(): void {
		$raw = get_option( self::OPTION_CITY_MACRO_CSV_BINDINGS, [] );
		if ( ! is_array( $raw ) ) {
			$raw = [];
		}
		$desired     = self::normalize_macro_csv_bindings_option( $raw );
		$needs_write = false;
		$bindable    = self::macro_bindable_metric_keys();
		$bind_flip   = array_flip( $bindable );
		foreach ( $raw as $k => $v ) {
			$mk = sanitize_key( (string) $k );
			if ( $mk === '' ) {
				continue;
			}
			if ( ! isset( $bind_flip[ $mk ] ) ) {
				$needs_write = true;
				break;
			}
		}
		if ( ! $needs_write ) {
			foreach ( $bindable as $mk ) {
				$in_r = array_key_exists( $mk, $raw );
				$in_d = array_key_exists( $mk, $desired );
				if ( $in_r !== $in_d ) {
					$needs_write = true;
					break;
				}
				if ( $in_r && $in_d && (int) $raw[ $mk ] !== (int) $desired[ $mk ] ) {
					$needs_write = true;
					break;
				}
			}
		}
		if ( ! $needs_write && count( $raw ) !== count( array_intersect_key( $raw, $bind_flip ) ) ) {
			$needs_write = true;
		}
		if ( ! $needs_write ) {
			return;
		}
		$updated = update_option( self::OPTION_CITY_MACRO_CSV_BINDINGS, $desired );
		if ( $updated && class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			WSErgo_Country_Macro_Calculator::flush_cache();
		}
	}

	/**
	 * @return list<string>
	 */
	public static function get_city_macro_extra_signals(): array {
		$raw = get_option( self::OPTION_CITY_MACRO_EXTRA_SIGNALS_TEXT, '' );
		return self::parse_macro_extra_signals_string( is_string( $raw ) ? $raw : '' );
	}

	/**
	 * @return list<string>
	 */
	public static function get_city_macro_extra_signals_effective(): array {
		if ( is_admin() && isset( $_POST['option_page'] ) && (string) wp_unslash( $_POST['option_page'] ) === 'wsergo_settings'
			&& isset( $_POST[ self::OPTION_CITY_MACRO_EXTRA_SIGNALS_TEXT ] ) && is_string( $_POST[ self::OPTION_CITY_MACRO_EXTRA_SIGNALS_TEXT ] ) ) {
			return self::parse_macro_extra_signals_string( wp_unslash( $_POST[ self::OPTION_CITY_MACRO_EXTRA_SIGNALS_TEXT ] ) );
		}
		return self::get_city_macro_extra_signals();
	}

	/**
	 * @return list<string>
	 */
	public static function get_city_macro_custom_metric_slugs(): array {
		$out = [];
		foreach ( self::get_city_macro_custom_metrics() as $row ) {
			if ( ! empty( $row['slug'] ) ) {
				$out[] = (string) $row['slug'];
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * @return list<string>
	 */
	public static function get_city_macro_custom_metric_slugs_effective(): array {
		$from_db = self::get_city_macro_custom_metric_slugs();
		if ( ! is_admin() ) {
			return $from_db;
		}
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) !== 'POST' ) {
			return $from_db;
		}
		if ( ! isset( $_POST['option_page'] ) || (string) wp_unslash( $_POST['option_page'] ) !== 'wsergo_settings' ) {
			return $from_db;
		}
		if ( ! isset( $_POST[ self::OPTION_CITY_MACRO_CUSTOM_METRICS ] ) || ! is_array( $_POST[ self::OPTION_CITY_MACRO_CUSTOM_METRICS ] ) ) {
			return $from_db;
		}
		$post_slugs = [];
		foreach ( wp_unslash( $_POST[ self::OPTION_CITY_MACRO_CUSTOM_METRICS ] ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$s = sanitize_key( (string) ( $row['slug'] ?? '' ) );
			if ( $s !== '' ) {
				$post_slugs[] = $s;
			}
		}
		return array_values( array_unique( array_merge( $from_db, $post_slugs ) ) );
	}

	/**
	 * Id ╨╗╨╕╤Б╤В╨╛╨▓╤Л╤Е ╨┐╨╛╨║╨░╨╖╨░╤В╨╡╨╗╨╡╨╣ ╨╕╨╖ ╨║╨░╤А╤В╤Л ╨┐╨╛╨╗╨╡╨╣ ╨│╨╛╤А╨╛╨┤╨░ (POST ╨┐╤А╨╕ ╤Б╨╛╤Е╤А╨░╨╜╨╡╨╜╨╕╨╕ ╨╜╨░╤Б╤В╤А╨╛╨╡╨║ ╨╕╨╗╨╕ ╨╛╨┐╤Ж╨╕╤П).
	 *
	 * @return list<string>
	 */
	public static function get_city_field_map_indicator_ids_effective(): array {
		$out = [];
		if ( is_admin() && isset( $_POST['option_page'] ) && (string) wp_unslash( $_POST['option_page'] ) === 'wsergo_settings'
			&& isset( $_POST[ self::OPTION_CITY_FIELD_MAP ] ) && is_array( $_POST[ self::OPTION_CITY_FIELD_MAP ] ) ) {
			foreach ( wp_unslash( $_POST[ self::OPTION_CITY_FIELD_MAP ] ) as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$ind = isset( $row['indicator_id'] ) ? sanitize_key( (string) $row['indicator_id'] ) : '';
				if ( $ind !== '' ) {
					$out[] = $ind;
				}
			}
		} elseif ( class_exists( 'WSErgo_City_Bridge' ) ) {
			foreach ( WSErgo_City_Bridge::get_field_map() as $row ) {
				if ( ! is_array( $row ) || empty( $row['indicator_id'] ) ) {
					continue;
				}
				$out[] = sanitize_key( (string) $row['indicator_id'] );
			}
		}
		if ( empty( $out ) && class_exists( 'WSErgo_City_Defaults' ) ) {
			foreach ( WSErgo_City_Defaults::default_city_field_map() as $row ) {
				if ( ! is_array( $row ) || empty( $row['indicator_id'] ) ) {
					continue;
				}
				$out[] = sanitize_key( (string) $row['indicator_id'] );
			}
		}
		$out = array_values( array_unique( array_filter( $out ) ) );
		sort( $out );
		return $out;
	}

	/**
	 * ╨а╨╡╨░╨╗╤М╨╜╨╛ ╨▓╤Б╤В╤А╨╡╤З╨░╤О╤Й╨╕╨╡╤Б╤П ╨║╨╗╤О╤З╨╕ ╨╝╨╡╤В╨░ wscity_* ╤Г ╨╛╨┐╤Г╨▒╨╗╨╕╨║╨╛╨▓╨░╨╜╨╜╤Л╤Е ╨│╨╛╤А╨╛╨┤╨╛╨▓ (╨┐╤А╨╕╨▓╤П╨╖╨║╨░ ╨║ ╨С╨Ф).
	 *
	 * @return list<string>
	 */
	public static function get_city_wscity_meta_keys_from_db(): array {
		if ( ! class_exists( 'WSCities_CPT' ) ) {
			return [];
		}
		global $wpdb;
		$pt      = WSCities_CPT::SLUG;
		$pattern = $wpdb->esc_like( 'wscity_' ) . '%';
		$sql     = $wpdb->prepare(
			"SELECT DISTINCT pm.meta_key FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE p.post_type = %s AND p.post_status = 'publish' AND pm.meta_key LIKE %s
			LIMIT 400",
			$pt,
			$pattern
		);
		$cols = $wpdb->get_col( $sql );
		if ( ! is_array( $cols ) ) {
			return [];
		}
		$out = [];
		foreach ( $cols as $mk ) {
			$k = sanitize_key( (string) $mk );
			if ( $k !== '' && preg_match( '/^wscity_[a-z0-9_]+$/', $k ) ) {
				$out[] = $k;
			}
		}
		$out = array_values( array_unique( $out ) );
		sort( $out );
		return $out;
	}

	/**
	 * ╨Я╨╛╨┤╨┐╨╕╤Б╤М ╨┐╨╛ ╤Г╨╝╨╛╨╗╤З╨░╨╜╨╕╤О ╨┤╨╗╤П ╨║╨╗╤О╤З╨░ ╨│╨╛╤А╨╛╨┤╨░: ╤В╨░╨▒╨╗╨╕╤Ж╨░ ╨┐╨╛╨║╨░╨╖╨░╤В╨╡╨╗╨╡╨╣, ╨╖╨░╤В╨╡╨╝ ╤Б╨┐╤А╨░╨▓╨╛╤З╨╜╨╕╨║ ╨╕╤Б╤В╨╛╤З╨╜╨╕╨║╨╛╨▓, ╨╖╨░╤В╨╡╨╝ ╨╖╨░╨│╨╗╤Г╤И╨║╨░.
	 */
	public static function default_city_data_label_ru( string $key ): string {
		$key = sanitize_key( $key );
		if ( $key === '' ) {
			return '';
		}
		if ( class_exists( 'WSErgo_Indicators' ) ) {
			foreach ( WSErgo_Indicators::get_definitions() as $def ) {
				if ( ! is_array( $def ) ) {
					continue;
				}
				$id = isset( $def['id'] ) ? sanitize_key( (string) $def['id'] ) : '';
				if ( $id === $key ) {
					$lb = isset( $def['label'] ) ? sanitize_text_field( (string) $def['label'] ) : '';
					if ( $lb !== '' ) {
						return $lb;
					}
					break;
				}
			}
		}
		if ( strpos( $key, 'wscity_' ) === 0 && class_exists( 'WSErgo_City_Bridge' ) ) {
			$src     = 'meta:' . $key;
			$choices = WSErgo_City_Bridge::get_source_choices();
			if ( isset( $choices[ $src ] ) && is_string( $choices[ $src ] ) ) {
				return sanitize_text_field( wp_strip_all_tags( $choices[ $src ] ) );
			}
		}
		return self::default_ru_label_for_custom_metric_slug( $key );
	}

	/**
	 * ╨б╨╕╨│╨╜╨░╨╗╤Л ╨┤╨╗╤П ╨╝╨░╤В╤А╨╕╤Ж╤Л ╨│╨╛╤А╨╛╨┤╨░: ╨┐╨╛╨║╨░╨╖╨░╤В╨╡╨╗╨╕/╨╝╨╡╤В╨░ ╨╕╨╖ ╨┤╨░╨╜╨╜╤Л╤Е ╨│╨╛╤А╨╛╨┤╨╛╨▓, ╨┤╨╛╨┐. ╨║╨╗╤О╤З╨╕, ╨║╨░╨╗╤М╨║╤Г╨╗╤П╤В╨╛╤А, ╤Б╤В╨╛╨╗╨▒╤Ж╤Л CSV.
	 *
	 * @return list<string>
	 */
	public static function macro_signal_allowlist_city(): array {
		$merged = [];
		$merged = array_merge( $merged, self::get_city_field_map_indicator_ids_effective() );
		$merged = array_merge( $merged, self::get_city_wscity_meta_keys_from_db() );
		$merged = array_merge( $merged, self::get_city_macro_extra_signals_effective() );
		$merged = array_merge( $merged, self::get_city_macro_custom_metric_slugs_effective() );
		$merged = array_merge( $merged, self::get_city_macro_custom_metric_slugs() );
		if ( class_exists( 'WSErgo_Country_Macro_Calculator' ) && WSErgo_Country_Macro_Calculator::has_macro_csv_data_sources() && class_exists( 'WorldStat_Uploaded_Csv' ) ) {
			$merged = array_merge( $merged, WorldStat_Uploaded_Csv::get_cached_all_metric_column_keys() );
		}
		$merged = array_values( array_unique( array_map( 'sanitize_key', $merged ) ) );
		$merged = array_filter(
			$merged,
			static function ( $x ) {
				return $x !== '';
			}
		);
		sort( $merged );
		return array_values( $merged );
	}

	/**
	 * @return list<string>
	 */
	public static function all_city_data_label_keys(): array {
		$u = self::macro_signal_allowlist_city();
		$u = array_unique( array_map( 'sanitize_key', $u ) );
		$u = array_filter(
			$u,
			static function ( $x ) {
				return $x !== '';
			}
		);
		sort( $u );
		return array_values( $u );
	}

	/**
	 * @return list<array{slug:string,op:string,key_a:string,key_b:string,const:float}>
	 */
	public static function get_city_macro_custom_metrics(): array {
		$raw = get_option( self::OPTION_CITY_MACRO_CUSTOM_METRICS, [] );
		if ( ! is_array( $raw ) ) {
			return [];
		}
		$out = [];
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$slug = sanitize_key( (string) ( $row['slug'] ?? '' ) );
			$op   = sanitize_key( (string) ( $row['op'] ?? '' ) );
			if ( $slug === '' || strlen( $slug ) > 96 || $op === '' ) {
				continue;
			}
			$out[] = [
				'slug'  => $slug,
				'op'    => $op,
				'key_a' => sanitize_key( (string) ( $row['key_a'] ?? '' ) ),
				'key_b' => sanitize_key( (string) ( $row['key_b'] ?? '' ) ),
				'const' => isset( $row['const'] ) && is_numeric( $row['const'] ) ? (float) $row['const'] : 0.0,
			];
		}
		return $out;
	}

	public static function get_city_macro_reference_year(): int {
		$y   = (int) get_option( self::OPTION_CITY_MACRO_REFERENCE_YEAR, 2022 );
		$min = class_exists( 'WorldStat_Platform_Years' ) ? max( 1900, WorldStat_Platform_Years::min() ) : 1900;
		$max = class_exists( 'WorldStat_Platform_Years' ) ? max( 2100, WorldStat_Platform_Years::max() ) : 2100;
		return max( $min, min( $max, $y ) );
	}

	public static function get_city_macro_k_clusters(): int {
		$k = (int) get_option( self::OPTION_CITY_MACRO_K_CLUSTERS, 6 );
		return max( 2, min( 12, $k ) );
	}

	/**
	 * @return array{F:float,Cm:float,H:float,A:float,S:float,Ct:float}
	 */
	public static function get_city_macro_e_axis_weights(): array {
		$defaults = [
			'F'  => 0.24,
			'Cm' => 0.22,
			'H'  => 0.18,
			'A'  => 0.14,
			'S'  => 0.12,
			'Ct' => 0.10,
		];
		$raw = get_option( self::OPTION_CITY_MACRO_E_AXIS_WEIGHTS, [] );
		if ( ! is_array( $raw ) ) {
			$raw = [];
		}
		$out = [];
		foreach ( $defaults as $axis => $def ) {
			$w = isset( $raw[ $axis ] ) ? (float) $raw[ $axis ] : $def;
			$out[ $axis ] = $w > 0 ? $w : $def;
		}
		$sum = array_sum( $out );
		if ( $sum <= 0 ) {
			return $defaults;
		}
		foreach ( $out as $axis => $w ) {
			$out[ $axis ] = $w / $sum;
		}
		return $out;
	}

	/**
	 * @return list<string>
	 */
	public static function get_city_macro_cluster_features(): array {
		$raw = get_option( self::OPTION_CITY_MACRO_CLUSTER_FEATURES, null );
		if ( ! is_array( $raw ) ) {
			return [];
		}
		$allow = array_flip( self::macro_signal_allowlist_city() );
		$out   = [];
		foreach ( $raw as $x ) {
			$k = sanitize_key( (string) $x );
			if ( $k !== '' && isset( $allow[ $k ] ) ) {
				$out[] = $k;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * @return array<string, string>
	 */
	public static function get_city_data_labels_ru(): array {
		$raw = get_option( self::OPTION_CITY_DATA_LABELS_RU, [] );
		if ( ! is_array( $raw ) ) {
			return [];
		}
		$out = [];
		foreach ( $raw as $k => $v ) {
			$key = sanitize_key( (string) $k );
			if ( $key === '' || strlen( $key ) > 96 ) {
				continue;
			}
			$text = sanitize_text_field( (string) $v );
			if ( $text !== '' ) {
				$out[ $key ] = $text;
			}
		}
		return $out;
	}

	/**
	 * @return array<string, array<string, true>>
	 */
	public static function get_city_macro_criteria_matrix(): array {
		$raw = get_option( self::OPTION_CITY_MACRO_CRITERIA_MATRIX, [] );
		if ( ! is_array( $raw ) || ! class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			return [];
		}
		$allow = array_flip( self::macro_signal_allowlist_city() );
		$axes  = [ 'F', 'Cm', 'H', 'A', 'S', 'Ct' ];
		$out   = [];
		foreach ( $raw as $sig => $row ) {
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
	 * @return array<string, array<string, float>>
	 */
	public static function get_city_macro_criteria_weights(): array {
		$raw = get_option( self::OPTION_CITY_MACRO_CRITERIA_WEIGHTS, [] );
		if ( ! is_array( $raw ) || ! class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			return [];
		}
		$allow = array_flip( self::macro_signal_allowlist_city() );
		$axes  = [ 'F', 'Cm', 'H', 'A', 'S', 'Ct' ];
		$out   = [];
		foreach ( $raw as $sig => $row ) {
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
				$w = (float) str_replace( ',', '.', trim( (string) $row[ $ax ] ) );
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
	 * @return array<string, array<string, bool>>
	 */
	public static function get_city_macro_criteria_inverts(): array {
		$raw = get_option( self::OPTION_CITY_MACRO_CRITERIA_INVERTS, [] );
		if ( ! is_array( $raw ) || ! class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			return [];
		}
		$allow = array_flip( self::macro_signal_allowlist_city() );
		$axes  = [ 'F', 'Cm', 'H', 'A', 'S', 'Ct' ];
		$out   = [];
		foreach ( $raw as $sig => $row ) {
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
	 * @return array<string, list<array{signal:string, invert:bool, weight:float}>>
	 */
	public static function build_city_axis_terms_from_criteria_matrix(): array {
		if ( ! class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			return [];
		}
		$def     = WSErgo_Country_Macro_Calculator::default_macro_axis_terms();
		$matrix  = self::get_city_macro_criteria_matrix();
		$w_opt   = self::get_city_macro_criteria_weights();
		$inv_opt = self::get_city_macro_criteria_inverts();
		$axes    = [ 'F', 'Cm', 'H', 'A', 'S', 'Ct' ];
		$out     = [];
		foreach ( $axes as $ax ) {
			$checked = [];
			foreach ( $matrix as $sig => $axm ) {
				if ( isset( $axm[ $ax ] ) && $axm[ $ax ] ) {
					$checked[] = $sig;
				}
			}
			sort( $checked, SORT_STRING );
			$n = count( $checked );
			if ( $n === 0 ) {
				$out[ $ax ] = $def[ $ax ] ?? [];
				continue;
			}
			$manual_vals = [];
			$auto_sigs   = [];
			foreach ( $checked as $sig ) {
				$rw = $w_opt[ $sig ][ $ax ] ?? null;
				if ( $rw !== null && $rw > 0 ) {
					$manual_vals[ $sig ] = (float) $rw;
				} else {
					$auto_sigs[] = $sig;
				}
			}
			$sum_m  = array_sum( $manual_vals );
			$n_auto = count( $auto_sigs );
			$n_man  = count( $manual_vals );
			$rows   = [];

			if ( $n_man === 0 ) {
				$eq = $n > 0 ? 1.0 / $n : 0.0;
				foreach ( $checked as $sig ) {
					$rows[] = [
						'signal' => $sig,
						'invert' => self::resolve_matrix_invert( $ax, $sig, $inv_opt, $def ),
						'weight' => $eq,
					];
				}
			} elseif ( $n_auto === 0 ) {
				if ( $sum_m <= 1e-15 ) {
					$eq = 1.0 / $n;
					foreach ( $checked as $sig ) {
						$rows[] = [
							'signal' => $sig,
							'invert' => self::resolve_matrix_invert( $ax, $sig, $inv_opt, $def ),
							'weight' => $eq,
						];
					}
				} elseif ( $sum_m > 1.0 + 1e-9 ) {
					foreach ( $manual_vals as $sig => $v ) {
						$rows[] = [
							'signal' => $sig,
							'invert' => self::resolve_matrix_invert( $ax, $sig, $inv_opt, $def ),
							'weight' => $v / $sum_m,
						];
					}
				} else {
					$rem  = 1.0 - $sum_m;
					$each = $n_man > 0 ? $rem / $n_man : 0.0;
					foreach ( $manual_vals as $sig => $v ) {
						$rows[] = [
							'signal' => $sig,
							'invert' => self::resolve_matrix_invert( $ax, $sig, $inv_opt, $def ),
							'weight' => $v + $each,
						];
					}
				}
			} elseif ( $sum_m > 1.0 + 1e-9 ) {
				foreach ( $checked as $sig ) {
					if ( isset( $manual_vals[ $sig ] ) ) {
						$wt = $manual_vals[ $sig ] / $sum_m;
					} else {
						$wt = 0.0;
					}
					$rows[] = [
						'signal' => $sig,
						'invert' => self::resolve_matrix_invert( $ax, $sig, $inv_opt, $def ),
						'weight' => $wt,
					];
				}
			} else {
				$rem    = max( 0.0, 1.0 - $sum_m );
				$each_a = $n_auto > 0 ? $rem / $n_auto : 0.0;
				foreach ( $checked as $sig ) {
					$w_sig = isset( $manual_vals[ $sig ] ) ? $manual_vals[ $sig ] : $each_a;
					$rows[] = [
						'signal' => $sig,
						'invert' => self::resolve_matrix_invert( $ax, $sig, $inv_opt, $def ),
						'weight' => $w_sig,
					];
				}
			}
			$out[ $ax ] = $rows;
		}
		return $out;
	}

	/**
	 * @return array<string, list<array{signal:string, invert:bool, weight:float}>>
	 */
	public static function get_city_macro_axis_terms_resolved(): array {
		if ( ! class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			return [];
		}
		return self::build_city_axis_terms_from_criteria_matrix();
	}

	/**
	 * @return array<string, int>
	 */
	public static function get_city_macro_csv_bindings(): array {
		$raw = get_option( self::OPTION_CITY_MACRO_CSV_BINDINGS, [] );
		if ( ! is_array( $raw ) ) {
			return [];
		}
		return self::normalize_macro_csv_bindings_option( $raw );
	}

	/**
	 * @return array<int, array{id:string,name:string,leaf_formula:string}>
	 */
	public static function get_city_models(): array {
		$stored = get_option( self::OPTION_CITY_MODELS, null );
		if ( ! is_array( $stored ) || ! $stored ) {
			return self::get_default_models();
		}
		return self::normalize_model_names( $stored );
	}

	/**
	 * @return array{id:string,name:string,leaf_formula:string}|null
	 */
	public static function get_city_active_model(): ?array {
		$id     = (string) get_option( self::OPTION_CITY_ACTIVE_MODEL, 'default_weighted' );
		$models = self::get_city_models();
		foreach ( $models as $m ) {
			if ( isset( $m['id'] ) && $m['id'] === $id ) {
				return $m;
			}
		}
		return $models[0] ?? null;
	}

	/**
	 * @return array<string, float>
	 */
	public static function get_city_coefficients(): array {
		$stored = get_option( self::OPTION_CITY_COEFFICIENTS, [] );
		if ( ! is_array( $stored ) ) {
			$stored = [];
		}
		$out = [ 'k_default' => 1.0 ];
		foreach ( $stored as $k => $v ) {
			$key = sanitize_key( (string) $k );
			if ( $key === '' ) {
				continue;
			}
			if ( substr( $key, 0, 2 ) !== 'k_' ) {
				$key = 'k_' . $key;
			}
			$out[ $key ] = (float) $v;
		}
		return $out;
	}

	/**
	 * ╨Ш╨┤╨╡╨╜╤В╨╕╤Д╨╕╨║╨░╤В╨╛╤А╤Л DSL ╨┤╨╗╤П ╨│╨╛╤А╨╛╨┤╤Б╨║╨╕╤Е ╨╝╨╛╨┤╨╡╨╗╨╡╨╣ (╤В╨╡ ╨╢╨╡ ╨╛╤Б╨╕ ╨╕ i_*).
	 *
	 * @return array<string, string>
	 */
	public static function get_city_leaf_formula_allowed_ids(): array {
		$labels = WSErgo_Model::get_dimension_labels();
		$ids    = [];
		$short  = [
			'F' => WSErgo_Model::DIM_FUNCTIONALITY,
			'S' => WSErgo_Model::DIM_SAFETY,
			'C' => WSErgo_Model::DIM_COMFORT,
			'L' => WSErgo_Model::DIM_LIVABILITY,
			'O' => WSErgo_Model::DIM_MASTERABILITY,
			'M' => WSErgo_Model::DIM_MANAGEABILITY,
		];
		foreach ( $short as $letter => $dim ) {
			$ids[ $letter ] = $labels[ $dim ];
		}
		foreach ( WSErgo_Model::DIMENSION_KEYS as $dim ) {
			$ids[ $dim ] = $labels[ $dim ];
		}
		$weights = WSErgo_Model::get_weights();
		foreach ( $weights as $dim => $w ) {
			$ids[ 'w_' . $dim ] = 'w_' . $dim;
		}
		foreach ( self::get_city_coefficients() as $k => $v ) {
			$ids[ $k ] = $k;
		}
		if ( class_exists( 'WSErgo_Indicators' ) ) {
			foreach ( WSErgo_Indicators::get_definitions() as $def ) {
				$key         = 'i_' . $def['id'];
				$ids[ $key ] = $def['label'];
			}
		}
		return $ids;
	}

	/**
	 * ╨Ш╨┤╨╡╨╜╤В╨╕╤Д╨╕╨║╨░╤В╨╛╤А╤Л ╨┤╨╗╤П DSL (╨║╨╗╤О╤З => ╨┐╨╛╨┤╨┐╨╕╤Б╤М ╨┤╨╗╤П UI).
	 *
	 * @return array<string, string>
	 */
	public static function get_leaf_formula_allowed_ids(): array {
		$labels = WSErgo_Model::get_dimension_labels();
		$ids    = [];
		$short  = [
			'F' => WSErgo_Model::DIM_FUNCTIONALITY,
			'S' => WSErgo_Model::DIM_SAFETY,
			'C' => WSErgo_Model::DIM_COMFORT,
			'L' => WSErgo_Model::DIM_LIVABILITY,
			'O' => WSErgo_Model::DIM_MASTERABILITY,
			'M' => WSErgo_Model::DIM_MANAGEABILITY,
		];
		foreach ( $short as $letter => $dim ) {
			$ids[ $letter ] = $labels[ $dim ];
		}
		foreach ( WSErgo_Model::DIMENSION_KEYS as $dim ) {
			$ids[ $dim ] = $labels[ $dim ];
		}
		$weights = WSErgo_Model::get_weights();
		foreach ( $weights as $dim => $w ) {
			$ids[ 'w_' . $dim ] = 'w_' . $dim;
		}
		foreach ( self::get_coefficients() as $k => $v ) {
			$ids[ $k ] = $k;
		}
		if ( class_exists( 'WSErgo_Indicators' ) ) {
			foreach ( WSErgo_Indicators::get_definitions() as $def ) {
				$key           = 'i_' . $def['id'];
				$ids[ $key ] = $def['label'];
			}
		}
		return $ids;
	}
}
