<?php
/**
 * Опции: модели DSL, коэффициенты, агрегация по уровням.
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
	/** Источник индекса страны: макроданные (CSV платформы) или агрегация по городам (legacy). */
	public const OPTION_COUNTRY_INDEX_SOURCE = 'wsergo_country_index_source';
	/** Опорный год для рядов country_code+year в CSV макромодели. */
	public const OPTION_MACRO_REFERENCE_YEAR = 'wsergo_macro_reference_year';
	/** Число кластеров k-means для макромодели (по умолчанию 6). */
	public const OPTION_MACRO_K_CLUSTERS     = 'wsergo_macro_k_clusters';
	/**
	 * Явная привязка базового ряда макро (ключ ingest) → id строки CSV в таблице wsp_csv_datasets.
	 * Пустое значение в форме — авто-определение по имени файла, как раньше.
	 */
	public const OPTION_MACRO_CSV_BINDINGS = 'wsergo_macro_csv_bindings';
	/** Веса шести осей макро (F, Cm, H, A, S, Ct) при сборке итогового E; после сохранения нормализуются к сумме 1. */
	public const OPTION_MACRO_E_AXIS_WEIGHTS = 'wsergo_macro_e_axis_weights';
	/** Признаки для k-means (список ключей из macro_signal_allowlist); пусто или меньше двух — встроенный набор плагина. */
	public const OPTION_MACRO_CLUSTER_FEATURES = 'wsergo_macro_cluster_features';
	/**
	 * Дополнительные ключи сигналов макро (одна строка = один латинский ключ), появляются в k-means и в формулах осей без правки PHP.
	 * Столбец wide-CSV после нормализации заголовка должен совпадать с ключом (или задать сопоставление в коде wide_csv_column_to_signal).
	 */
	public const OPTION_MACRO_EXTRA_SIGNALS_TEXT = 'wsergo_macro_extra_signals_text';
	/**
	 * Формулы макро-осей: для F, Cm, H, A, S, Ct — список слагаемых { signal, invert, weight }.
	 * Пустая ось в опции — подставляется встроенная методика.
	 */
	public const OPTION_MACRO_AXIS_TERMS = 'wsergo_macro_axis_terms';
	/** Сопоставление полей записи wsp_city → id показателя эргономики. */
	public const OPTION_CITY_FIELD_MAP    = 'wsergo_city_field_map';

	/**
	 * Модель по умолчанию: пустая leaf-формула — используется взвешенное среднее из WSErgo_Model.
	 *
	 * @return array<int, array{id:string,name:string,leaf_formula:string}>
	 */
	public static function get_default_models(): array {
		return [
			[
				'id'            => 'default_weighted',
				'name'          => 'Взвешенное среднее (классика)',
				'leaf_formula'  => '',
			],
		];
	}

	/**
	 * @return array<int, array{id:string,name:string,leaf_formula:string}>
	 */
	public static function get_models(): array {
		$stored = get_option( self::OPTION_MODELS, null );
		if ( ! is_array( $stored ) || ! $stored ) {
			return self::get_default_models();
		}
		return $stored;
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
	 * Глобальные коэффициенты k_* (число).
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
	 * Глобальный режим расчета:
	 * - city_direct: городской (как в исходной версии, страна агрегируется по городам).
	 * - regions: страновой (вариация 2, страна агрегируется через регионы).
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
		$y = (int) get_option( self::OPTION_MACRO_REFERENCE_YEAR, 2022 );
		return max( 1900, min( 2100, $y ) );
	}

	public static function get_macro_k_clusters(): int {
		$k = (int) get_option( self::OPTION_MACRO_K_CLUSTERS, 6 );
		return max( 2, min( 12, $k ) );
	}

	/**
	 * Допустимые ключи для привязки CSV к базовым рядам макромодели (см. WSErgo_Country_Macro_Calculator).
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
	 * Id строк wsp_csv_datasets, которые можно использовать как источник макрорядов (тип набора из расчётных).
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
	 * Нормализует сохранённую опцию привязок: только допустимые ключи и id, существующие в БД.
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
	 * Удаляет из опции привязки к несуществующим CSV и сохраняет при изменении (сброс кэша макро при реальной правке опции).
	 * Вызывается при смене ревизии файлов платформы и при открытии страницы настроек эргономики.
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
	 * Веса итогового E по шести осям макро (порядок: F, Cm, H, A, S, Ct).
	 *
	 * @return array{F:float,Cm:float,H:float,A:float,S:float,Ct:float}
	 */
	public static function get_macro_e_axis_weights(): array {
		$defaults = [
			'F'  => 0.25,
			'Cm' => 0.22,
			'H'  => 0.09,
			'A'  => 0.10,
			'S'  => 0.20,
			'Ct' => 0.13,
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
	 * Версия конфигурации макро для инвалидации transient (без смены CSV).
	 */
	public static function macro_config_hash(): string {
		return md5(
			wp_json_encode(
				[
					'b'  => self::get_macro_csv_bindings(),
					'w'  => self::get_macro_e_axis_weights(),
					'cf' => self::get_macro_cluster_features(),
					'at' => get_option( self::OPTION_MACRO_AXIS_TERMS, [] ),
					'es' => self::get_macro_extra_signals_effective(),
				]
			)
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
			if ( $k !== '' && strlen( $k ) <= 64 ) {
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
	 * При сохранении настроек эргономики подмешивает значение из POST, чтобы в том же запросе чекбоксы k-means не отфильтровали новые ключи.
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
	 * Выбранные признаки кластеризации (сырой список из опции).
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
	 * Итоговые слагаемые по каждой макро-оси (из опции или встроенные по умолчанию).
	 *
	 * @return array<string, list<array{signal:string, invert:bool, weight:float}>>
	 */
	public static function get_macro_axis_terms_resolved(): array {
		if ( ! class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			return [];
		}
		$def  = WSErgo_Country_Macro_Calculator::default_macro_axis_terms();
		$axes = array( 'F', 'Cm', 'H', 'A', 'S', 'Ct' );
		$raw  = get_option( self::OPTION_MACRO_AXIS_TERMS, [] );
		if ( ! is_array( $raw ) ) {
			$raw = [];
		}
		$out = array();
		foreach ( $axes as $ax ) {
			if ( isset( $raw[ $ax ] ) && is_array( $raw[ $ax ] ) && count( $raw[ $ax ] ) > 0 ) {
				$san = self::sanitize_macro_axis_terms_rows( $raw[ $ax ] );
				$out[ $ax ] = count( $san ) > 0 ? $san : ( $def[ $ax ] ?? array() );
			} else {
				$out[ $ax ] = $def[ $ax ] ?? array();
			}
		}
		return $out;
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
	 * Идентификаторы для DSL (ключ => подпись для UI).
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
