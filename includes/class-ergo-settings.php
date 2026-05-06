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
	/** ID поста wsp_country — эталон для колонки «Пример данных» на вкладке «Формула». */
	public const OPTION_MACRO_REFERENCE_COUNTRY_POST_ID = 'wsergo_macro_reference_country_post_id';
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
	 * Пользовательские производные показатели: формулы от двух столбцов или масштабирование одного (см. админку «Данные»).
	 *
	 * @var string
	 */
	public const OPTION_MACRO_CUSTOM_METRICS = 'wsergo_macro_custom_metrics';
	/**
	 * Формулы макро-осей: для F, Cm, H, A, S, Ct — список слагаемых { signal, invert, weight }.
	 * Пустая ось в опции — подставляется встроенная методика.
	 */
	public const OPTION_MACRO_AXIS_TERMS = 'wsergo_macro_axis_terms';
	/** Пользовательские русские подписи к техническим ключам данных (переопределяют встроенные). */
	public const OPTION_DATA_LABELS_RU = 'wsergo_data_labels_ru';
	/** Матрица: признак → для каких осей включён (ключи F, Cm, H, A, S, Ct). */
	public const OPTION_MACRO_CRITERIA_MATRIX = 'wsergo_macro_criteria_matrix';
	/** Веса внутри оси: признак → ось → вес (необязательно; пусто = авто, остаток от 1 после ручных). */
	public const OPTION_MACRO_CRITERIA_WEIGHTS = 'wsergo_macro_criteria_weights';
	/** Инверсия 1−x: признак → ось → bool (если нет ключа — берётся из встроенной методики). */
	public const OPTION_MACRO_CRITERIA_INVERTS = 'wsergo_macro_criteria_inverts';
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
	 * Версия конфигурации макро для инвалидации transient (без смены CSV).
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
	 * Пользовательские формулы производных признаков (после встроенных производных из wide-CSV).
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
	 * Итоговые ключи пользовательских параметров для списков и матрицы.
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
	 * Слаги из POST при сохранении «Эргономичность», чтобы матрица и критерии не отбрасывали новый ключ в том же запросе.
	 *
	 * @return list<string>
	 */
	public static function get_macro_custom_metric_slugs_effective(): array {
		if ( is_admin() && isset( $_POST['option_page'] ) && (string) wp_unslash( $_POST['option_page'] ) === 'wsergo_settings'
			&& isset( $_POST[ self::OPTION_MACRO_CUSTOM_METRICS ] ) && is_array( $_POST[ self::OPTION_MACRO_CUSTOM_METRICS ] ) ) {
			$slugs = array();
			foreach ( wp_unslash( $_POST[ self::OPTION_MACRO_CUSTOM_METRICS ] ) as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$s = sanitize_key( (string) ( $row['slug'] ?? '' ) );
				if ( $s !== '' ) {
					$slugs[] = $s;
				}
			}
			return array_values( array_unique( $slugs ) );
		}
		return self::get_macro_custom_metric_slugs();
	}

	/**
	 * Начальная подпись для пользовательского показателя (можно отредактировать в таблице подписей).
	 */
	public static function default_ru_label_for_custom_metric_slug( string $slug ): string {
		$slug = sanitize_key( $slug );
		if ( $slug === '' ) {
			return '';
		}
		$readable = str_replace( '_', ' ', $slug );
		return sprintf(
			/* translators: %s: technical metric key (latin snake_case). */
			__( 'Пользовательский параметр: %s', 'worldstat-ergonomics' ),
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
	 * Сохранённые подписи key → русский текст (только непустые переопределения).
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
	 * @return array<string, array<string, true>> сигнал → список осей с отметкой
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
	 * @return array<string, array<string, float>> сигнал → ось → вес (как ввёл пользователь, до нормализации)
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
	 * Сохранённые инверсии (только явно заданные в настройках).
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
	 * Инверсия по умолчанию для пары ось+признак (как во встроенной методике).
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
	 * Инверсия слагаемого: из настроек или из встроенной методики.
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
	 * Слагаемые по матрице: ручные веса (доли от 1) и остаток поровну на «авто»; инверсия из настроек или методики.
	 * Для оси без отметок подставляется встроенная методика по умолчанию.
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
	 * Итоговые слагаемые по каждой макро-оси: матрица «Данные» + веса; при отсутствии отметок по оси — встроенная методика.
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
