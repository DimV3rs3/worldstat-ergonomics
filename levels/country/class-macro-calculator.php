<?php
/**
 * Индекс эргономичности страны по макроданным (CSV World Statistics Platform).
 * Страновой индекс по макроданным платформы:
 * - k-means: кластеризация стран по признакам (настройки k-means),
 * - затем min–max нормализация нужных столбцов ВНУТРИ каждого кластера,
 * - после чего считаются оси F–Ct, сводный E и дальше классификация/регрессия.
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_Country_Macro_Calculator {

	/** Один результат get_full_bundle() на HTTP-запрос (рейтинг/карта иначе многократно дергают transient/память). */
	private static ?array $runtime_full_bundle = null;

	/** Новый ключ — старый кэш без diag/raw_rows больше не читается (избегает фаталов при несовпадении формата). */
	private const TRANSIENT_KEY = 'wsergo_macro_scores_bundle_v11';

	/** Кэш макро-E по настройкам вкладки «Эргономичность города» (другой год/k/оси/веса). */
	private const TRANSIENT_KEY_CITY = 'wsergo_macro_city_scores_bundle_v1';

	/** Инкремент при изменении логики расчёта — сбрасывает устаревший transient без смены CSV. */
	private const SCORE_BUNDLE_LOGIC = 25;

	/** Версия логики city-bundle (отдельно от странового свода). */
	private const CITY_SCORE_BUNDLE_LOGIC = 4;

	/** @var array<string, mixed>|null */
	private static ?array $runtime_city_bundle = null;

	/** @var array<string, string>|null ISO2 => ISO3 из data/countries.json платформы */
	private static $iso2_to_iso3_file_cache = null;

	/** @var array<string, array{iso2:string,name:string}>|null ISO3 => метаданные страны */
	private static $iso3_country_meta_cache = null;

	/** Признаки для k-means (вектор для кластеризации стран). */
	private const CLUSTER_FEATURES = [
		'pop_density__psn_per_km_sq',
		'urban_share_01',
		'transport_dens',
		'forest_cover_01',
		'renewable_energy__ptc',
		'life_exp_total__years',
		'pm25_exposure__mcg_per_m3',
		'gdp_per_energy__ppp_per_kgoe',
	];

	/** Показатели, для которых min–max делается внутри кластера. */
	private const NORMALIZE_WITHIN_CLUSTER = [
		'transport_dens',
		'air_departures__cnt',
		'air_passengers__psn',
		'internet_users__ptc',
		'broadband__per_100_psn',
		'mobile_subs__per_100_psn',
		'secure_servers__per_1m_psn',
		'gdp_per_energy__ppp_per_kgoe',
		'clean_elec_share__ptc',
		'fossil_elec_share__ptc',
		'energy_use_per_cap__kgoe',
		'pop_in_1m_aggl__ptc',
		'urban_share_01',
		'pop_density__psn_per_km_sq',
		'pm25_exposure__mcg_per_m3',
		'co2_per_capita__tonnes_per_psn',
		'renewable_energy__ptc',
		'freshwater_renew_per_cap__m3',
		'protected_terrestrial__ptc',
		'forest_cover_01',
		'forest_per_capita_m2',
		'agri_pressure',
		'life_exp_total__years',
		'health_system_capacity',
		'wASH_access_index',
		'access_electricity__ptc',
		'infect_and_stress_burden',
		'substance_burden',
		'fixed_phone__per_100_psn',
		'alcohol_total__liters_per_cap',
		'tobacco_adult__ptc',
		'age_dependency_proxy',
		'fertility_rate__births_per_woman',
		'net_migration__psn',
		'big_city_ratio',
		'urban_pop_growth__ptc',
		'digital_access_index',
		'debt_stress',
		'military_exp__ptc_gdp',
		'women_parliament_seats__ptc',
		'fiscal_transparency_proxy',
		'tax_revenue__ptc_gdp',
		'rent_fuels',
		'net_oda_received__ptc_gni',
	];

	/** Ключи стандартных рядов (country_code, year, value). */
	private const STANDARD_METRIC_KEYS = [
		'population_total',
		'surface_area_sqkm',
		'population_density_per_km2',
		'urban_share_percent',
		'urban_land_area_sqkm',
		'forest_percentage',
		'largest_city_population',
		'railway_length',
		'road_length',
	];

	public static function flush_cache(): void {
		delete_transient( self::TRANSIENT_KEY );
		delete_transient( self::TRANSIENT_KEY_CITY );
		delete_transient( 'wsergo_macro_scores_bundle' );
		self::$runtime_full_bundle = null;
		self::$runtime_city_bundle  = null;
		if ( class_exists( 'WSErgo_Tier_Classifier' ) ) {
			WSErgo_Tier_Classifier::flush_runtime_cache();
		}
		if ( class_exists( 'WSErgo_Settings' ) ) {
			$rev = (int) get_option( 'wsp_csv_files_revision', 0 );
			delete_transient( 'wsergo_macro_allowlist_v2_' . $rev . '_' . substr( WSErgo_Settings::macro_config_hash(), 0, 12 ) );
		}
	}

	/**
	 * Ключи базовых рядов (country_code + year + value), которые можно явно привязать к строке CSV в БД.
	 *
	 * @return list<string>
	 */
	public static function bindable_standard_metric_keys(): array {
		return self::STANDARD_METRIC_KEYS;
	}

	/**
	 * Встроенные формулы макро-осей (нормализованные признаки 0–1, веса не обязаны суммироваться в 1 — перенормируются при расчёте).
	 *
	 * @return array<string, list<array{signal:string, invert:bool, weight:float}>>
	 */
	public static function default_macro_axis_terms(): array {
		return array(
			'F'  => array(
				array( 'signal' => 'transport_dens', 'invert' => false, 'weight' => 0.1120 ),
				array( 'signal' => 'air_departures__cnt', 'invert' => false, 'weight' => 0.0840 ),
				array( 'signal' => 'air_passengers__psn', 'invert' => false, 'weight' => 0.0840 ),
				array( 'signal' => 'internet_users__ptc', 'invert' => false, 'weight' => 0.0960 ),
				array( 'signal' => 'broadband__per_100_psn', 'invert' => false, 'weight' => 0.0800 ),
				array( 'signal' => 'mobile_subs__per_100_psn', 'invert' => false, 'weight' => 0.0800 ),
				array( 'signal' => 'secure_servers__per_1m_psn', 'invert' => false, 'weight' => 0.0640 ),
				array( 'signal' => 'gdp_per_energy__ppp_per_kgoe', 'invert' => false, 'weight' => 0.0840 ),
				array( 'signal' => 'clean_elec_share__ptc', 'invert' => false, 'weight' => 0.0600 ),
				array( 'signal' => 'fossil_elec_share__ptc', 'invert' => true, 'weight' => 0.0600 ),
				array( 'signal' => 'energy_use_per_cap__kgoe', 'invert' => true, 'weight' => 0.0360 ),
				array( 'signal' => 'pop_in_1m_aggl__ptc', 'invert' => false, 'weight' => 0.0560 ),
				array( 'signal' => 'urban_share_01', 'invert' => false, 'weight' => 0.0480 ),
				array( 'signal' => 'pop_density__psn_per_km_sq', 'invert' => true, 'weight' => 0.0560 ),
			),
			'Cm' => array(
				array( 'signal' => 'pm25_exposure__mcg_per_m3', 'invert' => true, 'weight' => 0.1020 ),
				array( 'signal' => 'co2_per_capita__tonnes_per_psn', 'invert' => true, 'weight' => 0.0850 ),
				array( 'signal' => 'renewable_energy__ptc', 'invert' => false, 'weight' => 0.0680 ),
				array( 'signal' => 'freshwater_renew_per_cap__m3', 'invert' => false, 'weight' => 0.0510 ),
				array( 'signal' => 'protected_terrestrial__ptc', 'invert' => false, 'weight' => 0.0340 ),
				array( 'signal' => 'forest_cover_01', 'invert' => false, 'weight' => 0.1100 ),
				array( 'signal' => 'forest_per_capita_m2', 'invert' => false, 'weight' => 0.0770 ),
				array( 'signal' => 'agri_pressure', 'invert' => true, 'weight' => 0.0330 ),
				array( 'signal' => 'life_exp_total__years', 'invert' => false, 'weight' => 0.0780 ),
				array( 'signal' => 'health_system_capacity', 'invert' => false, 'weight' => 0.0650 ),
				array( 'signal' => 'wASH_access_index', 'invert' => false, 'weight' => 0.0650 ),
				array( 'signal' => 'infect_and_stress_burden', 'invert' => true, 'weight' => 0.0312 ),
				array( 'signal' => 'substance_burden', 'invert' => true, 'weight' => 0.0208 ),
				array( 'signal' => 'fixed_phone__per_100_psn', 'invert' => false, 'weight' => 0.0720 ),
				array( 'signal' => 'alcohol_total__liters_per_cap', 'invert' => true, 'weight' => 0.0630 ),
				array( 'signal' => 'tobacco_adult__ptc', 'invert' => true, 'weight' => 0.0450 ),
			),
			'H'  => array(
				array( 'signal' => 'life_exp_total__years', 'invert' => false, 'weight' => 0.1350 ),
				array( 'signal' => 'age_dependency_proxy', 'invert' => true, 'weight' => 0.0750 ),
				array( 'signal' => 'fertility_rate__births_per_woman', 'invert' => true, 'weight' => 0.0450 ),
				array( 'signal' => 'net_migration__psn', 'invert' => false, 'weight' => 0.0450 ),
				array( 'signal' => 'urban_share_01', 'invert' => false, 'weight' => 0.0840 ),
				array( 'signal' => 'pop_in_1m_aggl__ptc', 'invert' => false, 'weight' => 0.0700 ),
				array( 'signal' => 'big_city_ratio', 'invert' => false, 'weight' => 0.0560 ),
				array( 'signal' => 'pop_density__psn_per_km_sq', 'invert' => true, 'weight' => 0.0700 ),
				array( 'signal' => 'forest_cover_01', 'invert' => false, 'weight' => 0.0840 ),
				array( 'signal' => 'protected_terrestrial__ptc', 'invert' => false, 'weight' => 0.0840 ),
				array( 'signal' => 'renewable_energy__ptc', 'invert' => false, 'weight' => 0.0720 ),
				array( 'signal' => 'wASH_access_index', 'invert' => false, 'weight' => 0.1080 ),
				array( 'signal' => 'access_electricity__ptc', 'invert' => false, 'weight' => 0.0720 ),
			),
			'A'  => array(
				array( 'signal' => 'transport_dens', 'invert' => false, 'weight' => 0.1575 ),
				array( 'signal' => 'air_passengers__psn', 'invert' => false, 'weight' => 0.1050 ),
				array( 'signal' => 'air_departures__cnt', 'invert' => false, 'weight' => 0.0875 ),
				array( 'signal' => 'urban_share_01', 'invert' => false, 'weight' => 0.1050 ),
				array( 'signal' => 'urban_pop_growth__ptc', 'invert' => false, 'weight' => 0.1050 ),
				array( 'signal' => 'pop_in_1m_aggl__ptc', 'invert' => false, 'weight' => 0.0900 ),
				array( 'signal' => 'big_city_ratio', 'invert' => false, 'weight' => 0.1000 ),
				array( 'signal' => 'pop_density__psn_per_km_sq', 'invert' => true, 'weight' => 0.1000 ),
				array( 'signal' => 'digital_access_index', 'invert' => false, 'weight' => 0.1500 ),
			),
			'S'  => array(
				array( 'signal' => 'pm25_exposure__mcg_per_m3', 'invert' => true, 'weight' => 0.1600 ),
				array( 'signal' => 'co2_per_capita__tonnes_per_psn', 'invert' => true, 'weight' => 0.1600 ),
				array( 'signal' => 'life_exp_total__years', 'invert' => false, 'weight' => 0.1190 ),
				array( 'signal' => 'infect_and_stress_burden', 'invert' => true, 'weight' => 0.1190 ),
				array( 'signal' => 'health_system_capacity', 'invert' => false, 'weight' => 0.1020 ),
				array( 'signal' => 'forest_cover_01', 'invert' => false, 'weight' => 0.0880 ),
				array( 'signal' => 'protected_terrestrial__ptc', 'invert' => false, 'weight' => 0.0770 ),
				array( 'signal' => 'freshwater_renew_per_cap__m3', 'invert' => false, 'weight' => 0.0550 ),
				array( 'signal' => 'debt_stress', 'invert' => true, 'weight' => 0.0720 ),
				array( 'signal' => 'military_exp__ptc_gdp', 'invert' => true, 'weight' => 0.0480 ),
			),
			'Ct' => array(
				array( 'signal' => 'women_parliament_seats__ptc', 'invert' => false, 'weight' => 0.1520 ),
				array( 'signal' => 'fiscal_transparency_proxy', 'invert' => false, 'weight' => 0.1330 ),
				array( 'signal' => 'tax_revenue__ptc_gdp', 'invert' => false, 'weight' => 0.0950 ),
				array( 'signal' => 'rent_fuels', 'invert' => true, 'weight' => 0.1400 ),
				array( 'signal' => 'clean_elec_share__ptc', 'invert' => false, 'weight' => 0.0840 ),
				array( 'signal' => 'gdp_per_energy__ppp_per_kgoe', 'invert' => false, 'weight' => 0.0560 ),
				array( 'signal' => 'net_oda_received__ptc_gni', 'invert' => false, 'weight' => 0.2000 ),
				array( 'signal' => 'debt_stress', 'invert' => true, 'weight' => 0.0980 ),
				array( 'signal' => 'urban_pop_growth__ptc', 'invert' => false, 'weight' => 0.0420 ),
			),
		);
	}

	/**
	 * Сигналы для матрицы, подписей и санитизации: только столбцы из сохранённых CSV (+ слаги из import_metric_slugs) и строки из
	 * «Дополнительные ключи признаков». Без жёстко прошитого набора — иначе при одном датасете в списке оказывались десятки
	 * чужих показателей.
	 *
	 * Встроенные признаки расчёта по-прежнему используются в коде осей/k-means; в админке они не подмешиваются автоматически.
	 *
	 * @return list<string>
	 */
	public static function macro_signal_allowlist(): array {
		$merged = array();
		if ( class_exists( 'WSErgo_Settings' ) ) {
			$merged = array_merge( $merged, WSErgo_Settings::get_macro_extra_signals_effective() );
			$merged = array_merge( $merged, WSErgo_Settings::get_macro_custom_metric_slugs_effective() );
			$merged = array_merge( $merged, WSErgo_Settings::get_macro_custom_metric_slugs() );
		}
		$has_uploaded = self::has_macro_csv_data_sources();
		if ( $has_uploaded && class_exists( 'WorldStat_Uploaded_Csv' ) ) {
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
	 * Кэшированный список признаков для админки (без повторного обхода CSV на каждый запрос).
	 *
	 * @return list<string>
	 */
	public static function get_cached_macro_signal_allowlist(): array {
		$rev = (int) get_option( 'wsp_csv_files_revision', 0 );
		$key = 'wsergo_macro_allowlist_v2_' . $rev;
		if ( class_exists( 'WSErgo_Settings' ) ) {
			$key .= '_' . substr( WSErgo_Settings::macro_config_hash(), 0, 12 );
		}
		$cached = get_transient( $key );
		if ( is_array( $cached ) && ( [] !== $cached || ! self::has_macro_csv_data_sources() ) ) {
			return $cached;
		}
		$list = self::macro_signal_allowlist();
		if ( [] !== $list ) {
			set_transient( $key, $list, 6 * HOUR_IN_SECONDS );
		}
		return $list;
	}

	/**
	 * Список признаков для k-means и матрицы критериев (CSV, доп. ключи, пользовательские).
	 *
	 * @param string $scope country|city
	 * @return list<string>
	 */
	public static function macro_cluster_signal_allowlist( string $scope = 'country' ): array {
		if ( 'city' === $scope && class_exists( 'WSErgo_Settings' ) ) {
			return WSErgo_Settings::macro_signal_allowlist_city();
		}
		return self::get_cached_macro_signal_allowlist();
	}

	/**
	 * В БД платформы есть хотя бы один сохранённый CSV — показывать ключи из файлов и матрицу.
	 */
	public static function has_macro_csv_data_sources(): bool {
		if ( ! class_exists( 'WorldStat_Uploaded_Csv' ) ) {
			return false;
		}
		if ( ! WorldStat_Uploaded_Csv::table_exists() ) {
			return false;
		}
		return WorldStat_Uploaded_Csv::has_any_stored_datasets();
	}

	/**
	 * Подписи для UI (ось макро → человекочитаемое имя).
	 *
	 * @return array<string, string>
	 */
	public static function macro_axis_labels_ru(): array {
		return array(
			'F'  => __( 'Функциональность', 'worldstat-ergonomics' ),
			'Cm' => __( 'Комфортность', 'worldstat-ergonomics' ),
			'H'  => __( 'Обитаемость', 'worldstat-ergonomics' ),
			'A'  => __( 'Освояемость', 'worldstat-ergonomics' ),
			'S'  => __( 'Безопасность', 'worldstat-ergonomics' ),
			'Ct' => __( 'Управляемость', 'worldstat-ergonomics' ),
		);
	}

	/**
	 * @deprecated Используйте macro_cluster_signal_allowlist(); встроенный набор больше не подмешивается в UI.
	 * @return list<string>
	 */
	public static function default_cluster_features(): array {
		return self::CLUSTER_FEATURES;
	}

	/**
	 * Резервный набор признаков k-means из allowlist и фактических рядов CSV (без встроенного CLUSTER_FEATURES).
	 *
	 * @param array<string, array<string, float>> $rows
	 * @param list<string>                        $allowlist
	 * @return list<string>
	 */
	public static function fallback_cluster_features_from_rows( array $rows, array $allowlist ): array {
		$n = count( $rows );
		if ( $n < 1 || count( $allowlist ) < 2 ) {
			return array();
		}
		$scored = array();
		foreach ( $allowlist as $sig ) {
			$sig = sanitize_key( (string) $sig );
			if ( $sig === '' ) {
				continue;
			}
			$cnt = 0;
			foreach ( $rows as $row ) {
				if ( is_array( $row ) && isset( $row[ $sig ] ) && is_finite( (float) $row[ $sig ] ) ) {
					$cnt++;
				}
			}
			$cov = $cnt / $n;
			if ( $cov >= 0.25 ) {
				$scored[ $sig ] = $cov;
			}
		}
		if ( count( $scored ) < 2 ) {
			return array();
		}
		arsort( $scored, SORT_NUMERIC );
		return array_slice( array_keys( $scored ), 0, 8 );
	}

	/**
	 * @return array<string, array{E:float, F:float, Cm:float, H:float, A:float, S:float, Ct:float}>
	 */
	public static function get_all_scores(): array {
		$b = self::get_full_bundle();
		return ( isset( $b['scores'] ) && is_array( $b['scores'] ) ) ? $b['scores'] : array();
	}

	/**
	 * Полный кэш макрорасчёта: сводные баллы, сырые признаки по ISO3, диагностика.
	 *
	 * @return array{y:int,r:int,lv:int,scores:array,diag:array<string,array<string,mixed>>,raw_rows:array<string,array<string,float>>}
	 */
	public static function get_full_bundle(): array {
		if ( self::$runtime_full_bundle !== null ) {
			return self::$runtime_full_bundle;
		}
		$defaults = array(
			'y'         => 0,
			'r'         => 0,
			'lv'        => self::SCORE_BUNDLE_LOGIC,
			'ch'        => '',
			'scores'    => array(),
			'diag'      => array(),
			'raw_rows'  => array(),
		);
		if ( ! class_exists( 'WSErgo_Settings' ) ) {
			return $defaults;
		}
		$year = WSErgo_Settings::get_macro_reference_year();
		$rev  = (int) get_option( 'wsp_csv_files_revision', 0 );
		$ch   = class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::macro_config_hash() : '';
		$bund = get_transient( self::TRANSIENT_KEY );
		if (
			is_array( $bund )
			&& isset( $bund['y'], $bund['r'], $bund['lv'], $bund['scores'], $bund['diag'], $bund['raw_rows'] )
			&& (int) $bund['y'] === $year
			&& (int) $bund['r'] === $rev
			&& (int) $bund['lv'] === self::SCORE_BUNDLE_LOGIC
			&& (string) ( $bund['ch'] ?? '' ) === $ch
			&& is_array( $bund['scores'] )
			&& is_array( $bund['diag'] )
			&& is_array( $bund['raw_rows'] )
		) {
			self::$runtime_full_bundle = array(
				'y'        => (int) $bund['y'],
				'r'        => (int) $bund['r'],
				'lv'       => (int) $bund['lv'],
				'ch'       => (string) ( $bund['ch'] ?? '' ),
				'scores'   => $bund['scores'],
				'diag'     => $bund['diag'],
				'raw_rows' => $bund['raw_rows'],
			);
			return self::$runtime_full_bundle;
		}
		$full = self::compute_full_bundle( $year );
		if ( ! is_array( $full ) ) {
			$full = array();
		}
		$full['y']        = $year;
		$full['r']        = $rev;
		$full['lv']       = self::SCORE_BUNDLE_LOGIC;
		$full['ch']       = class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::macro_config_hash() : '';
		$full['scores']   = isset( $full['scores'] ) && is_array( $full['scores'] ) ? $full['scores'] : array();
		$full['diag']     = isset( $full['diag'] ) && is_array( $full['diag'] ) ? $full['diag'] : array();
		$full['raw_rows'] = isset( $full['raw_rows'] ) && is_array( $full['raw_rows'] ) ? $full['raw_rows'] : array();
		set_transient( self::TRANSIENT_KEY, $full, HOUR_IN_SECONDS * 6 );
		self::$runtime_full_bundle = $full;
		return self::$runtime_full_bundle;
	}

	/**
	 * Полный кэш макрорасчёта по настройкам «Эргономичность города» (опорный год города, свои k и оси).
	 *
	 * @return array{y:int,r:int,lv:int,ch:string,scores:array,diag:array<string,array<string,mixed>>,raw_rows:array<string,array<string,float>>}
	 */
	public static function get_city_full_bundle(): array {
		if ( self::$runtime_city_bundle !== null ) {
			return self::$runtime_city_bundle;
		}
		$defaults = array(
			'y'        => 0,
			'r'        => 0,
			'lv'       => self::CITY_SCORE_BUNDLE_LOGIC,
			'ch'       => '',
			'scores'   => array(),
			'diag'     => array(),
			'raw_rows' => array(),
		);
		if ( ! class_exists( 'WSErgo_Settings' ) ) {
			return $defaults;
		}
		$year = WSErgo_Settings::get_city_macro_reference_year();
		$rev  = (int) get_option( 'wsp_csv_files_revision', 0 );
		$ch   = WSErgo_Settings::city_macro_config_hash();
		$bund = get_transient( self::TRANSIENT_KEY_CITY );
		if (
			is_array( $bund )
			&& isset( $bund['y'], $bund['r'], $bund['lv'], $bund['scores'], $bund['diag'], $bund['raw_rows'] )
			&& (int) $bund['y'] === $year
			&& (int) $bund['r'] === $rev
			&& (int) $bund['lv'] === self::CITY_SCORE_BUNDLE_LOGIC
			&& (string) ( $bund['ch'] ?? '' ) === $ch
			&& is_array( $bund['scores'] )
			&& is_array( $bund['diag'] )
			&& is_array( $bund['raw_rows'] )
		) {
			self::$runtime_city_bundle = array(
				'y'        => (int) $bund['y'],
				'r'        => (int) $bund['r'],
				'lv'       => (int) $bund['lv'],
				'ch'       => (string) ( $bund['ch'] ?? '' ),
				'scores'   => $bund['scores'],
				'diag'     => $bund['diag'],
				'raw_rows' => $bund['raw_rows'],
			);
			return self::$runtime_city_bundle;
		}
		$full = self::compute_full_bundle_city( $year );
		if ( ! is_array( $full ) ) {
			$full = array();
		}
		$full['y']        = $year;
		$full['r']        = $rev;
		$full['lv']       = self::CITY_SCORE_BUNDLE_LOGIC;
		$full['ch']       = WSErgo_Settings::city_macro_config_hash();
		$full['scores']   = isset( $full['scores'] ) && is_array( $full['scores'] ) ? $full['scores'] : array();
		$full['diag']     = isset( $full['diag'] ) && is_array( $full['diag'] ) ? $full['diag'] : array();
		$full['raw_rows'] = isset( $full['raw_rows'] ) && is_array( $full['raw_rows'] ) ? $full['raw_rows'] : array();
		set_transient( self::TRANSIENT_KEY_CITY, $full, HOUR_IN_SECONDS * 6 );
		self::$runtime_city_bundle = $full;
		return self::$runtime_city_bundle;
	}

	/**
	 * Макро-E и сырой ряд по настройкам вкладки «Эргономичность города» (для строки страны в CSV).
	 *
	 * @return array{iso3:string,iso2:string,year:int,scores:?array,diagnostics:array<string,mixed>,raw_row:?array<string,float>,axis_terms:array,e_axis_weights:array}|null
	 */
	public static function get_city_macro_detail( string $iso2 ): ?array {
		if ( ! class_exists( 'WSErgo_Settings' ) || WSErgo_Settings::get_country_index_source() !== 'macro_datasets' ) {
			return null;
		}
		$iso2 = strtoupper( sanitize_text_field( $iso2 ) );
		if ( strlen( $iso2 ) !== 2 ) {
			return null;
		}
		$iso3 = self::iso2_to_iso3( $iso2 );
		if ( strlen( $iso3 ) !== 3 ) {
			return null;
		}
		$bundle = self::get_city_full_bundle();
		$y      = (int) ( $bundle['y'] ?? WSErgo_Settings::get_city_macro_reference_year() );
		$scores = ( isset( $bundle['scores'] ) && is_array( $bundle['scores'] ) ) ? $bundle['scores'] : array();
		$diags  = ( isset( $bundle['diag'] ) && is_array( $bundle['diag'] ) ) ? $bundle['diag'] : array();
		$raws   = ( isset( $bundle['raw_rows'] ) && is_array( $bundle['raw_rows'] ) ) ? $bundle['raw_rows'] : array();
		$axis_terms = WSErgo_Settings::get_city_macro_axis_terms_resolved();
		if ( ! is_array( $axis_terms ) || empty( $axis_terms ) ) {
			$axis_terms = self::default_macro_axis_terms();
		}
		$e_w = WSErgo_Settings::get_city_macro_e_axis_weights();

		$row_scores = isset( $scores[ $iso3 ] ) && is_array( $scores[ $iso3 ] ) ? $scores[ $iso3 ] : null;
		if ( is_array( $row_scores ) ) {
			foreach ( array( 'E', 'F', 'Cm', 'H', 'A', 'S', 'Ct' ) as $axis_key ) {
				if ( ! array_key_exists( $axis_key, $row_scores ) ) {
					continue;
				}
				$rv = $row_scores[ $axis_key ];
				if ( is_numeric( $rv ) ) {
					$row_scores[ $axis_key ] = (float) $rv;
				}
			}
		}

		return array(
			'iso3'           => $iso3,
			'iso2'           => $iso2,
			'year'           => $y,
			'scores'         => $row_scores,
			'diagnostics'    => isset( $diags[ $iso3 ] ) && is_array( $diags[ $iso3 ] ) ? $diags[ $iso3 ] : array(),
			'raw_row'        => isset( $raws[ $iso3 ] ) && is_array( $raws[ $iso3 ] ) ? $raws[ $iso3 ] : null,
			'axis_terms'     => is_array( $axis_terms ) ? $axis_terms : array(),
			'e_axis_weights' => is_array( $e_w ) ? $e_w : array(),
		);
	}

	/**
	 * Данные для вкладки «Эргономичность» (макрорежим): баллы, сырые признаки, списки пропусков.
	 *
	 * @return array{iso3:string,iso2:string,year:int,scores:?array,diagnostics:array<string,mixed>,raw_row:?array<string,float>}|null
	 */
	public static function get_country_macro_detail( string $iso2 ): ?array {
		if ( ! class_exists( 'WSErgo_Settings' ) || WSErgo_Settings::get_country_index_source() !== 'macro_datasets' ) {
			return null;
		}
		$iso2 = strtoupper( sanitize_text_field( $iso2 ) );
		if ( strlen( $iso2 ) !== 2 ) {
			return null;
		}
		$iso3 = self::iso2_to_iso3( $iso2 );
		if ( strlen( $iso3 ) !== 3 ) {
			return null;
		}
		$bundle = self::get_full_bundle();
		$y      = (int) ( $bundle['y'] ?? ( class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::get_macro_reference_year() : 2022 ) );
		$scores = ( isset( $bundle['scores'] ) && is_array( $bundle['scores'] ) ) ? $bundle['scores'] : array();
		$diags  = ( isset( $bundle['diag'] ) && is_array( $bundle['diag'] ) ) ? $bundle['diag'] : array();
		$raws   = ( isset( $bundle['raw_rows'] ) && is_array( $bundle['raw_rows'] ) ) ? $bundle['raw_rows'] : array();
		$axis_terms = class_exists( 'WSErgo_Settings' )
			? WSErgo_Settings::get_macro_axis_terms_resolved()
			: self::default_macro_axis_terms();
		$e_w        = class_exists( 'WSErgo_Settings' )
			? WSErgo_Settings::get_macro_e_axis_weights()
			: array(
				'F'  => 0.24,
				'Cm' => 0.22,
				'H'  => 0.18,
				'A'  => 0.14,
				'S'  => 0.12,
				'Ct' => 0.10,
			);

		return array(
			'iso3'         => $iso3,
			'iso2'         => $iso2,
			'year'         => $y,
			'scores'       => isset( $scores[ $iso3 ] ) && is_array( $scores[ $iso3 ] ) ? $scores[ $iso3 ] : null,
			'diagnostics'  => isset( $diags[ $iso3 ] ) && is_array( $diags[ $iso3 ] ) ? $diags[ $iso3 ] : array(),
			'raw_row'      => isset( $raws[ $iso3 ] ) && is_array( $raws[ $iso3 ] ) ? $raws[ $iso3 ] : null,
			'axis_terms'   => is_array( $axis_terms ) ? $axis_terms : array(),
			'e_axis_weights' => is_array( $e_w ) ? $e_w : array(),
		);
	}

	/**
	 * Встроенного словаря переводов нет — только опция пользователя и файл «Переводы» в админке CSV.
	 *
	 * @return array<string, string>
	 */
	public static function standard_metric_labels_ru(): array {
		return array();
	}

	/**
	 * Встроенные русские подписи для технических ключей (обзор страны, эргономика).
	 * Переопределяются опцией админки и CSV «Переводы».
	 *
	 * @return array<string, string>
	 */
	public static function macro_signal_ru_defaults(): array {
		static $cache = null;
		if ( is_array( $cache ) ) {
			return $cache;
		}
		$cache = array(
			// Целые наборы CSV (имя файла / агрегат при импорте).
			'demographics'     => __( 'Демография', 'worldstat-ergonomics' ),
			'urban_infra'      => __( 'Городская инфраструктура и транспорт', 'worldstat-ergonomics' ),
			'environment'      => __( 'Окружающая среда', 'worldstat-ergonomics' ),
			'health_comfort'   => __( 'Здоровье и комфорт', 'worldstat-ergonomics' ),
			'governance_sdg'   => __( 'Управление и цели устойчивого развития', 'worldstat-ergonomics' ),
			'energy'           => __( 'Энергетика', 'worldstat-ergonomics' ),
			// Базовые ряды страны (long CSV).
			'population_total'              => __( 'Население', 'worldstat-ergonomics' ),
			'surface_area_sqkm'             => __( 'Площадь территории, км²', 'worldstat-ergonomics' ),
			'population_density_per_km2'    => __( 'Плотность населения на км²', 'worldstat-ergonomics' ),
			'urban_share_percent'           => __( 'Доля городского населения, %', 'worldstat-ergonomics' ),
			'urban_land_area_sqkm'          => __( 'Площадь урбанизированных территорий, км²', 'worldstat-ergonomics' ),
			'forest_percentage'             => __( 'Леса, % территории', 'worldstat-ergonomics' ),
			'largest_city_population'       => __( 'Население крупнейшего города', 'worldstat-ergonomics' ),
			'railway_length'                => __( 'Железные дороги, км', 'worldstat-ergonomics' ),
			'road_length'                   => __( 'Дороги, км', 'worldstat-ergonomics' ),
			// Частые импорты / синонимы без двойного подчёркивания.
			'co2_annual_tonnes'             => __( 'Годовые выбросы CO₂, тонн', 'worldstat-ergonomics' ),
			'agri_land_ptc'                 => __( 'Сельхозугодья, % территории', 'worldstat-ergonomics' ),
			'agri_land__ptc'                => __( 'Сельхозугодья, % территории', 'worldstat-ergonomics' ),
			'access_clean_cooking_ptc'      => __( 'Доступ к чистой кухонной энергии, %', 'worldstat-ergonomics' ),
			'access_clean_cooking__ptc'     => __( 'Доступ к чистой кухонной энергии, %', 'worldstat-ergonomics' ),
			'access_drinking_water_basic_ptc' => __( 'Доступ к базовой питьевой воде, %', 'worldstat-ergonomics' ),
			'access_drinking_water_basic__ptc' => __( 'Доступ к базовой питьевой воде, %', 'worldstat-ergonomics' ),
			'access_electricity_ptc'        => __( 'Доступ к электричеству, %', 'worldstat-ergonomics' ),
			'access_electricity__ptc'       => __( 'Доступ к электричеству, %', 'worldstat-ergonomics' ),
			'access_sanitation_basic_ptc'   => __( 'Доступ к базовой санитарии, %', 'worldstat-ergonomics' ),
			'access_sanitation_basic__ptc'  => __( 'Доступ к базовой санитарии, %', 'worldstat-ergonomics' ),
			// Признаки макромодели (канонические ключи).
			'transport_dens'                => __( 'Плотность транспортной сети', 'worldstat-ergonomics' ),
			'air_departures__cnt'           => __( 'Взлёты и посадки воздушных судов, шт.', 'worldstat-ergonomics' ),
			'air_passengers__psn'           => __( 'Авиапассажиры (перевезено)', 'worldstat-ergonomics' ),
			'internet_users__ptc'           => __( 'Доля пользователей интернета, %', 'worldstat-ergonomics' ),
			'broadband__per_100_psn'        => __( 'Широкополосный доступ на 100 человек', 'worldstat-ergonomics' ),
			'mobile_subs__per_100_psn'      => __( 'Абоненты мобильной связи на 100 человек', 'worldstat-ergonomics' ),
			'secure_servers__per_1m_psn'    => __( 'Защищённые интернет-серверы на 1 млн человек', 'worldstat-ergonomics' ),
			'gdp_per_energy__ppp_per_kgoe'  => __( 'ВВП на единицу энергии (ППС), $/кг нефтяного экв.', 'worldstat-ergonomics' ),
			'clean_elec_share__ptc'         => __( 'Доля «чистой» электроэнергии, %', 'worldstat-ergonomics' ),
			'fossil_elec_share__ptc'        => __( 'Доля электроэнергии из ископаемого топлива, %', 'worldstat-ergonomics' ),
			'energy_use_per_cap__kgoe'     => __( 'Энергопотребление на душу населения, кг нефтяного экв.', 'worldstat-ergonomics' ),
			'pop_in_1m_aggl__ptc'           => __( 'Население в агломерациях >1 млн, %', 'worldstat-ergonomics' ),
			'urban_share_01'                => __( 'Доля городского населения (доля 0–1)', 'worldstat-ergonomics' ),
			'pop_density__psn_per_km_sq'    => __( 'Плотность населения, чел./км²', 'worldstat-ergonomics' ),
			'pm25_exposure__mcg_per_m3'     => __( 'Воздействие PM2.5, мкг/м³', 'worldstat-ergonomics' ),
			'co2_per_capita__tonnes_per_psn' => __( 'Выбросы CO₂ на душу населения, т/год', 'worldstat-ergonomics' ),
			'renewable_energy__ptc'         => __( 'Возобновляемая энергия, %', 'worldstat-ergonomics' ),
			'freshwater_renew_per_cap__m3'   => __( 'Внутренние возобновляемые водные ресурсы на душу, м³/год', 'worldstat-ergonomics' ),
			'protected_terrestrial__ptc'    => __( 'Охраняемые наземные территории, %', 'worldstat-ergonomics' ),
			'forest_cover_01'               => __( 'Лесной покров (доля 0–1)', 'worldstat-ergonomics' ),
			'forest_per_capita_m2'          => __( 'Лесная площадь на душу населения, м²', 'worldstat-ergonomics' ),
			'agri_pressure'                 => __( 'Нагрузка сельхозугодий (прокси)', 'worldstat-ergonomics' ),
			'life_exp_total__years'         => __( 'Ожидаемая продолжительность жизни, лет', 'worldstat-ergonomics' ),
			'health_system_capacity'        => __( 'Ёмкость системы здравоохранения (прокси)', 'worldstat-ergonomics' ),
			'wASH_access_index'             => __( 'Индекс доступа к воде, санитарии и гигиене', 'worldstat-ergonomics' ),
			'infect_and_stress_burden'      => __( 'Бремя инфекций и стресса (прокси)', 'worldstat-ergonomics' ),
			'substance_burden'              => __( 'Бремя вредных веществ (прокси)', 'worldstat-ergonomics' ),
			'fixed_phone__per_100_psn'      => __( 'Фиксированная телефония на 100 человек', 'worldstat-ergonomics' ),
			'alcohol_total__liters_per_cap' => __( 'Потребление алкоголя на душу, л/год', 'worldstat-ergonomics' ),
			'tobacco_adult__ptc'            => __( 'Доля курящих среди взрослых, %', 'worldstat-ergonomics' ),
			'age_dependency_proxy'          => __( 'Зависимость населения (прокси)', 'worldstat-ergonomics' ),
			'fertility_rate__births_per_woman' => __( 'Рождаемость, рожд. на женщину', 'worldstat-ergonomics' ),
			'net_migration__psn'            => __( 'Чистая миграция, чел.', 'worldstat-ergonomics' ),
			'big_city_ratio'                => __( 'Доля населения в крупных городах (прокси)', 'worldstat-ergonomics' ),
			'urban_pop_growth__ptc'         => __( 'Рост городского населения, %', 'worldstat-ergonomics' ),
			'digital_access_index'          => __( 'Индекс цифрового доступа', 'worldstat-ergonomics' ),
			'debt_stress'                   => __( 'Долговая нагрузка (прокси)', 'worldstat-ergonomics' ),
			'military_exp__ptc_gdp'         => __( 'Военные расходы, % ВВП', 'worldstat-ergonomics' ),
			'women_parliament_seats__ptc'   => __( 'Доля женщин в парламенте, %', 'worldstat-ergonomics' ),
			'fiscal_transparency_proxy'     => __( 'Фискальная прозрачность (прокси)', 'worldstat-ergonomics' ),
			'tax_revenue__ptc_gdp'          => __( 'Налоговые поступления, % ВВП', 'worldstat-ergonomics' ),
			'rent_fuels'                    => __( 'Доля ренты от топлива (прокси)', 'worldstat-ergonomics' ),
			'net_oda_received__ptc_gni'     => __( 'Чистая ОПР, полученная, % ВНД', 'worldstat-ergonomics' ),
		);
		return $cache;
	}

	/**
	 * Варианты ключа для словаря: canonical с «__» и импорт с одним «_» перед суффиксом единицы.
	 *
	 * @return list<string>
	 */
	public static function data_label_ru_candidate_keys( string $key ): array {
		$key = sanitize_key( $key );
		$out = array();
		if ( $key !== '' ) {
			$out[] = $key;
		}
		if ( strpos( $key, '__' ) === false ) {
			$pairs = array(
				'_psn_per_km_sq'    => '__psn_per_km_sq',
				'_tonnes_per_psn'   => '__tonnes_per_psn',
				'_liters_per_cap'   => '__liters_per_cap',
				'_births_per_woman' => '__births_per_woman',
				'_ppp_per_kgoe'     => '__ppp_per_kgoe',
				'_per_kgoe'         => '__per_kgoe',
				'_per_100_psn'      => '__per_100_psn',
				'_per_1m_psn'       => '__per_1m_psn',
				'_mcg_per_m3'       => '__mcg_per_m3',
				'_ptc_gdp'          => '__ptc_gdp',
				'_ptc_gni'          => '__ptc_gni',
				'_ptc'              => '__ptc',
				'_psn'              => '__psn',
				'_cnt'              => '__cnt',
			);
			foreach ( $pairs as $single => $dbl ) {
				$len = strlen( $single );
				if ( strlen( $key ) > $len && substr( $key, -$len ) === $single ) {
					$out[] = substr( $key, 0, -$len ) . $dbl;
				}
			}
		} else {
			$collapsed = str_replace( '__', '_', $key );
			if ( $collapsed !== $key ) {
				$out[] = $collapsed;
			}
		}
		return array_values( array_unique( array_filter( $out ) ) );
	}

	/**
	 * Человекочитаемая подпись: опции эргономики и импорт «Переводы» (world-statistics-platform); иначе читаемый ключ.
	 */
	public static function data_label_ru( string $key ): string {
		$key = sanitize_key( $key );
		if ( $key === '' ) {
			return '—';
		}
		$candidates = self::data_label_ru_candidate_keys( $key );
		if ( class_exists( 'WSErgo_Settings' ) ) {
			$custom = WSErgo_Settings::get_data_labels_ru();
			foreach ( $candidates as $try_key ) {
				if ( isset( $custom[ $try_key ] ) && $custom[ $try_key ] !== '' ) {
					return $custom[ $try_key ];
				}
			}
		}
		$defaults = self::macro_signal_ru_defaults();
		foreach ( $candidates as $try_key ) {
			if ( isset( $defaults[ $try_key ] ) && $defaults[ $try_key ] !== '' ) {
				return $defaults[ $try_key ];
			}
		}
		$human = str_replace( '_', ' ', $key );
		if ( function_exists( 'mb_convert_case' ) && preg_match( '/[a-z]/i', $human ) ) {
			return mb_convert_case( $human, MB_CASE_TITLE, 'UTF-8' );
		}
		return $human !== '' ? ucwords( $human ) : '—';
	}

	/**
	 * Словаря по умолчанию нет — placeholder задаётся в шаблоне админки.
	 */
	public static function default_data_label_ru( string $key ): string {
		return '';
	}

	/**
	 * Ключи для таблицы подписей: то же, что {@see macro_signal_allowlist()} (столбцы из CSV + доп. ключи).
	 *
	 * @return list<string>
	 */
	public static function all_data_label_keys(): array {
		$u = self::macro_signal_allowlist();
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

	public static function get_index_for_iso2( string $iso2 ): float {
		$iso2 = strtoupper( sanitize_text_field( $iso2 ) );
		if ( strlen( $iso2 ) !== 2 ) {
			return 0.0;
		}
		$iso3 = self::iso2_to_iso3( $iso2 );
		if ( $iso3 === '' ) {
			return 0.0;
		}
		$all = self::get_all_scores();
		$row = $all[ $iso3 ] ?? null;
		if ( is_array( $row ) && isset( $row['E'] ) && is_finite( (float) $row['E'] ) ) {
			return round( (float) $row['E'], 2 );
		}
		return 0.0;
	}

	/**
	 * Макроиндекс E для списка ISO2 за один проход по сводке (рейтинги без N× декодирования transient).
	 *
	 * @param list<string> $iso2_codes
	 * @return array<string,float> ключ — ISO2 в верхнем регистре
	 */
	public static function get_bulk_macro_indices_for_iso2( array $iso2_codes ): array {
		if ( empty( $iso2_codes ) || ! class_exists( 'WorldStat_Country_CPT' ) ) {
			return array();
		}
		if ( class_exists( 'WSErgo_Settings' ) && WSErgo_Settings::get_country_index_source() !== 'macro_datasets' ) {
			return array();
		}
		$all = self::get_all_scores();
		$out = array();
		if ( empty( $all ) ) {
			foreach ( $iso2_codes as $raw ) {
				$iso2 = strtoupper( sanitize_text_field( (string) $raw ) );
				if ( strlen( $iso2 ) !== 2 ) {
					continue;
				}
				$out[ $iso2 ] = 0.0;
			}
			return $out;
		}
		foreach ( $iso2_codes as $raw ) {
			$iso2 = strtoupper( sanitize_text_field( (string) $raw ) );
			if ( strlen( $iso2 ) !== 2 ) {
				continue;
			}
			$iso3 = self::iso2_to_iso3( $iso2 );
			if ( $iso3 === '' ) {
				$out[ $iso2 ] = 0.0;
				continue;
			}
			$row = $all[ $iso3 ] ?? null;
			if ( is_array( $row ) && isset( $row['E'] ) && is_finite( (float) $row['E'] ) ) {
				$out[ $iso2 ] = round( (float) $row['E'], 2 );
				continue;
			}
			$out[ $iso2 ] = 0.0;
		}
		return $out;
	}

	private static function iso2_to_iso3( string $iso2 ): string {
		if ( ! class_exists( 'WorldStat_Country_CPT' ) ) {
			return self::iso2_to_iso3_from_countries_json( $iso2 );
		}
		$iso2 = strtoupper( trim( $iso2 ) );
		if ( strlen( $iso2 ) !== 2 || ! ctype_alpha( $iso2 ) ) {
			return '';
		}
		$post_id = 0;
		if ( method_exists( 'WorldStat_Country_CPT', 'get_post_id_by_code' ) ) {
			$post_id = (int) WorldStat_Country_CPT::get_post_id_by_code( $iso2 );
		}
		if ( $post_id > 0 ) {
			$iso3 = strtoupper( (string) get_post_meta( $post_id, 'wsp_iso_alpha3', true ) );
			if ( strlen( $iso3 ) === 3 && ctype_alpha( $iso3 ) ) {
				return $iso3;
			}
			return self::iso2_to_iso3_from_countries_json( $iso2 );
		}
		$post = WorldStat_Country_CPT::get_by_code( $iso2 );
		if ( ! $post ) {
			return self::iso2_to_iso3_from_countries_json( $iso2 );
		}
		$iso3 = strtoupper( (string) get_post_meta( $post->ID, 'wsp_iso_alpha3', true ) );
		if ( strlen( $iso3 ) === 3 && ctype_alpha( $iso3 ) ) {
			return $iso3;
		}
		return self::iso2_to_iso3_from_countries_json( $iso2 );
	}

	/**
	 * Если в БД у страны пустой wsp_iso_alpha3 — подставляем ISO3 из справочника платформы (data/countries.json).
	 */
	private static function iso2_to_iso3_from_countries_json( string $iso2 ): string {
		$iso2 = strtoupper( trim( $iso2 ) );
		if ( strlen( $iso2 ) !== 2 || ! ctype_alpha( $iso2 ) ) {
			return '';
		}
		if ( self::$iso2_to_iso3_file_cache === null ) {
			self::$iso2_to_iso3_file_cache = array();
			$path = '';
			if ( defined( 'WSP_DATA_DIR' ) ) {
				$path = (string) WSP_DATA_DIR . 'countries.json';
			}
			if ( ( $path === '' || ! is_readable( $path ) ) && defined( 'WP_PLUGIN_DIR' ) ) {
				$path = (string) WP_PLUGIN_DIR . 'world-statistics-platform/data/countries.json';
			}
			if ( $path !== '' && is_readable( $path ) ) {
				$fh = fopen( $path, 'rb' );
				if ( $fh ) {
					while ( ( $line = fgets( $fh ) ) !== false ) {
						$line = trim( (string) $line );
						if ( $line === '' ) {
							continue;
						}
						$o = json_decode( $line, true );
						if ( is_array( $o ) && isset( $o['iso2'], $o['iso3'] ) ) {
							$a2 = strtoupper( (string) $o['iso2'] );
							$a3 = strtoupper( (string) $o['iso3'] );
							if ( strlen( $a2 ) === 2 && strlen( $a3 ) === 3 && ctype_alpha( $a2 ) && ctype_alpha( $a3 ) ) {
								self::$iso2_to_iso3_file_cache[ $a2 ] = $a3;
							}
						}
					}
					fclose( $fh );
				}
			}
		}
		return self::$iso2_to_iso3_file_cache[ $iso2 ] ?? '';
	}

	/**
	 * Палитра кластеров для админ-визуализации (до 12).
	 *
	 * @return list<string>
	 */
	public static function cluster_palette(): array {
		return array(
			'#2563eb',
			'#16a34a',
			'#dc2626',
			'#9333ea',
			'#ea580c',
			'#0891b2',
			'#ca8a04',
			'#db2777',
			'#4f46e5',
			'#059669',
			'#b45309',
			'#7c3aed',
		);
	}

	/**
	 * ISO3 => { iso2, name } из countries.json платформы.
	 *
	 * @return array<string, array{iso2:string,name:string}>
	 */
	/**
	 * Публичная обёртка для классификатора и UI.
	 */
	public static function iso2_to_iso3_public( string $iso2 ): string {
		return self::iso2_to_iso3( strtoupper( sanitize_text_field( $iso2 ) ) );
	}

	public static function iso3_country_meta_map(): array {
		if ( self::$iso3_country_meta_cache !== null ) {
			return self::$iso3_country_meta_cache;
		}
		$out  = array();
		$path = defined( 'WSP_DATA_DIR' ) ? (string) WSP_DATA_DIR . 'countries.json' : '';
		if ( ( $path === '' || ! is_readable( $path ) ) && defined( 'WP_PLUGIN_DIR' ) ) {
			$path = (string) WP_PLUGIN_DIR . 'world-statistics-platform/data/countries.json';
		}
		if ( $path !== '' && is_readable( $path ) ) {
			$raw = file_get_contents( $path );
			$arr = is_string( $raw ) ? json_decode( $raw, true ) : null;
			if ( is_array( $arr ) ) {
				foreach ( $arr as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$a3 = strtoupper( (string) ( $row['iso3'] ?? '' ) );
					$a2 = strtoupper( (string) ( $row['iso2'] ?? '' ) );
					if ( strlen( $a3 ) !== 3 || strlen( $a2 ) !== 2 ) {
						continue;
					}
					$name       = (string) ( $row['name_ru'] ?? $row['name_en'] ?? $a3 );
					$out[ $a3 ] = array(
						'iso2' => $a2,
						'name' => $name,
					);
				}
			}
		}
		self::$iso3_country_meta_cache = $out;
		return $out;
	}

	/**
	 * ISO3 по записи страны в CPT (ISO2 или meta wsp_iso_alpha3).
	 */
	public static function iso3_for_country_post_id( int $post_id ): string {
		if ( $post_id <= 0 ) {
			return '';
		}
		$iso2 = strtoupper( trim( (string) get_post_meta( $post_id, 'wsp_iso_alpha2', true ) ) );
		$iso3 = '';
		if ( strlen( $iso2 ) === 2 ) {
			$iso3 = self::iso2_to_iso3( $iso2 );
		}
		if ( strlen( $iso3 ) !== 3 ) {
			$iso3 = strtoupper( trim( (string) get_post_meta( $post_id, 'wsp_iso_alpha3', true ) ) );
		}
		return ( strlen( $iso3 ) === 3 && ctype_alpha( $iso3 ) ) ? $iso3 : '';
	}

	/**
	 * Сырой ряд признаков страны за опорный год (для админки «Формула» → «Пример данных»).
	 *
	 * @return array<string, float>|null
	 */
	public static function raw_row_for_country_post_id( int $post_id, ?int $year = null ): ?array {
		$iso3 = self::iso3_for_country_post_id( $post_id );
		if ( $iso3 === '' ) {
			return null;
		}
		if ( $year === null ) {
			$year = class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::get_macro_reference_year() : 2022;
		}
		$year = max( 1960, min( (int) gmdate( 'Y' ) + 1, (int) $year ) );

		$raws = array();
		if ( class_exists( 'WSErgo_Settings' ) && $year === WSErgo_Settings::get_macro_reference_year() ) {
			$bundle = self::get_full_bundle();
			$raws   = isset( $bundle['raw_rows'] ) && is_array( $bundle['raw_rows'] ) ? $bundle['raw_rows'] : array();
		} else {
			$full = self::compute_full_bundle( $year );
			$raws = isset( $full['raw_rows'] ) && is_array( $full['raw_rows'] ) ? $full['raw_rows'] : array();
		}

		return isset( $raws[ $iso3 ] ) && is_array( $raws[ $iso3 ] ) ? $raws[ $iso3 ] : null;
	}

	/**
	 * Русские подписи агрегатов World Bank / регионов в CSV (не страны ISO).
	 *
	 * @return array<string, string>
	 */
	public static function wb_aggregate_labels_ru(): array {
		return array(
			'AFE' => __( 'Восточная и южная Африка', 'worldstat-ergonomics' ),
			'AFW' => __( 'Западная и центральная Африка', 'worldstat-ergonomics' ),
			'ARB' => __( 'Арабский мир', 'worldstat-ergonomics' ),
			'CEB' => __( 'Центральная Европа и Балтия', 'worldstat-ergonomics' ),
			'CHI' => __( 'Нормандские острова', 'worldstat-ergonomics' ),
			'CSS' => __( 'Малые государства Карибского бассейна', 'worldstat-ergonomics' ),
			'EAP' => __( 'Восточная Азия и Тихоокеанский регион (без стран с высоким доходом)', 'worldstat-ergonomics' ),
			'EAR' => __( 'Ранняя демографическая дивиденда', 'worldstat-ergonomics' ),
			'EAS' => __( 'Восточная Азия и Тихоокеанский регион', 'worldstat-ergonomics' ),
			'ECA' => __( 'Европа и Центральная Азия (без стран с высоким доходом)', 'worldstat-ergonomics' ),
			'ECS' => __( 'Европа и Центральная Азия', 'worldstat-ergonomics' ),
			'EMU' => __( 'Еврозона', 'worldstat-ergonomics' ),
			'EUU' => __( 'Европейский союз', 'worldstat-ergonomics' ),
			'FCS' => __( 'Хрупкие и конфликтные ситуации', 'worldstat-ergonomics' ),
			'HIC' => __( 'Страны с высоким доходом', 'worldstat-ergonomics' ),
			'HKG' => __( 'Гонконг', 'worldstat-ergonomics' ),
			'HPC' => __( 'Страны с высокой долговой нагрузкой (ИППП)', 'worldstat-ergonomics' ),
			'IBD' => __( 'МБР (только)', 'worldstat-ergonomics' ),
			'IBT' => __( 'МБР и ММА', 'worldstat-ergonomics' ),
			'IDA' => __( 'ММА (всего)', 'worldstat-ergonomics' ),
			'IDB' => __( 'ММА (смешанные страны)', 'worldstat-ergonomics' ),
			'IDX' => __( 'ММА (только)', 'worldstat-ergonomics' ),
			'INX' => __( 'Не классифицировано', 'worldstat-ergonomics' ),
			'LAC' => __( 'Латинская Америка и Карибы (без стран с высоким доходом)', 'worldstat-ergonomics' ),
			'LCN' => __( 'Латинская Америка и Карибы', 'worldstat-ergonomics' ),
			'LDC' => __( 'Наименее развитые страны (классификация ООН)', 'worldstat-ergonomics' ),
			'LIC' => __( 'Страны с низким доходом', 'worldstat-ergonomics' ),
			'LMC' => __( 'Страны с доходом ниже среднего', 'worldstat-ergonomics' ),
			'LMY' => __( 'Страны с низким и средним доходом', 'worldstat-ergonomics' ),
			'LTE' => __( 'Поздняя демографическая дивиденда', 'worldstat-ergonomics' ),
			'MAC' => __( 'Макао', 'worldstat-ergonomics' ),
			'MIC' => __( 'Страны со средним доходом', 'worldstat-ergonomics' ),
			'MNA' => __( 'Ближний Восток и Северная Африка (без стран с высоким доходом)', 'worldstat-ergonomics' ),
			'NAC' => __( 'Северная Америка', 'worldstat-ergonomics' ),
			'OED' => __( 'ОЭСР', 'worldstat-ergonomics' ),
			'OSS' => __( 'Прочие малые государства', 'worldstat-ergonomics' ),
			'PRE' => __( 'Преддемографическая дивиденда', 'worldstat-ergonomics' ),
			'PSE' => __( 'Палестина', 'worldstat-ergonomics' ),
			'PSS' => __( 'Тихоокеанские малые государства', 'worldstat-ergonomics' ),
			'PST' => __( 'Постдемографическая дивиденда', 'worldstat-ergonomics' ),
			'SAS' => __( 'Южная Азия', 'worldstat-ergonomics' ),
			'SSA' => __( 'Тропическая Африка южнее Сахары (без стран с высоким доходом)', 'worldstat-ergonomics' ),
			'SSF' => __( 'Тропическая Африка южнее Сахары', 'worldstat-ergonomics' ),
			'SST' => __( 'Малые государства', 'worldstat-ergonomics' ),
			'TEA' => __( 'Восточная Азия и Тихоокеанский регион (МБР и ММА)', 'worldstat-ergonomics' ),
			'TEC' => __( 'Европа и Центральная Азия (МБР и ММА)', 'worldstat-ergonomics' ),
			'TLA' => __( 'Латинская Америка и Карибы (МБР и ММА)', 'worldstat-ergonomics' ),
			'TMN' => __( 'Ближний Восток и Северная Африка (МБР и ММА)', 'worldstat-ergonomics' ),
			'TSA' => __( 'Южная Азия (МБР и ММА)', 'worldstat-ergonomics' ),
			'TSS' => __( 'Тропическая Африка южнее Сахары (МБР и ММА)', 'worldstat-ergonomics' ),
			'TWN' => __( 'Тайвань', 'worldstat-ergonomics' ),
			'UMC' => __( 'Страны с доходом выше среднего', 'worldstat-ergonomics' ),
			'WLD' => __( 'Мир', 'worldstat-ergonomics' ),
			'XKX' => __( 'Косово', 'worldstat-ergonomics' ),
		);
	}

	/**
	 * Метаданные строки CSV по ISO3: страна (iso2 + русское имя) или агрегат.
	 *
	 * @return array{iso2:string,name:string,iso3:string,is_aggregate:bool}
	 */
	public static function country_meta_for_iso3( string $iso3 ): array {
		$iso3 = strtoupper( sanitize_key( $iso3 ) );
		if ( strlen( $iso3 ) !== 3 || ! ctype_alpha( $iso3 ) ) {
			return array(
				'iso2'         => '',
				'name'         => $iso3,
				'iso3'         => $iso3,
				'is_aggregate' => true,
			);
		}

		$map = self::iso3_country_meta_map();
		if ( isset( $map[ $iso3 ] ) ) {
			return array(
				'iso2'         => (string) $map[ $iso3 ]['iso2'],
				'name'         => (string) $map[ $iso3 ]['name'],
				'iso3'         => $iso3,
				'is_aggregate' => false,
			);
		}

		$agg = self::wb_aggregate_labels_ru();
		if ( isset( $agg[ $iso3 ] ) ) {
			return array(
				'iso2'         => '',
				'name'         => (string) $agg[ $iso3 ],
				'iso3'         => $iso3,
				'is_aggregate' => true,
			);
		}

		$from_cpt = self::country_meta_from_cpt_iso3( $iso3 );
		if ( is_array( $from_cpt ) ) {
			return $from_cpt;
		}

		$resolved = self::resolve_iso3_display_name_ru( $iso3 );
		if ( $resolved !== '' && $resolved !== $iso3 ) {
			return array(
				'iso2'         => '',
				'name'         => $resolved,
				'iso3'         => $iso3,
				'is_aggregate' => true,
			);
		}

		return array(
			'iso2'         => '',
			'name'         => $iso3,
			'iso3'         => $iso3,
			'is_aggregate' => true,
		);
	}

	/**
	 * Русское имя по ISO3, если код не попал в каталог стран и агрегатов WB.
	 */
	private static function resolve_iso3_display_name_ru( string $iso3 ): string {
		$iso3 = strtoupper( $iso3 );
		if ( strlen( $iso3 ) !== 3 ) {
			return '';
		}
		$path = defined( 'WSP_DATA_DIR' ) ? (string) WSP_DATA_DIR . 'countries.json' : '';
		if ( ( $path === '' || ! is_readable( $path ) ) && defined( 'WP_PLUGIN_DIR' ) ) {
			$path = (string) WP_PLUGIN_DIR . 'world-statistics-platform/data/countries.json';
		}
		if ( $path !== '' && is_readable( $path ) ) {
			$raw = file_get_contents( $path );
			$arr = is_string( $raw ) ? json_decode( $raw, true ) : null;
			if ( is_array( $arr ) ) {
				foreach ( $arr as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					if ( strtoupper( (string) ( $row['iso3'] ?? '' ) ) !== $iso3 ) {
						continue;
					}
					$name = (string) ( $row['name_ru'] ?? $row['name_en'] ?? '' );
					if ( $name !== '' ) {
						return $name;
					}
				}
			}
		}
		return '';
	}

	/**
	 * @return array{iso2:string,name:string,iso3:string,is_aggregate:bool}|null
	 */
	private static function country_meta_from_cpt_iso3( string $iso3 ): ?array {
		if ( ! class_exists( 'WorldStat_Country_CPT' ) ) {
			return null;
		}
		$posts = get_posts(
			array(
				'post_type'              => WorldStat_Country_CPT::SLUG,
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					array(
						'key'   => 'wsp_iso_alpha3',
						'value' => $iso3,
					),
				),
				'fields'                 => 'ids',
			)
		);
		if ( empty( $posts ) ) {
			return null;
		}
		$post_id = (int) $posts[0];
		$iso2  = strtoupper( (string) get_post_meta( $post_id, 'wsp_iso_alpha2', true ) );
		$title = get_the_title( $post_id );
		if ( strlen( $iso2 ) !== 2 ) {
			$from_json = self::iso3_country_meta_map()[ $iso3 ] ?? null;
			if ( is_array( $from_json ) && strlen( (string) ( $from_json['iso2'] ?? '' ) ) === 2 ) {
				$iso2 = (string) $from_json['iso2'];
			}
		}
		if ( strlen( $iso2 ) !== 2 ) {
			return null;
		}
		return array(
			'iso2'         => $iso2,
			'name'         => $title !== '' ? (string) $title : $iso3,
			'iso3'         => $iso3,
			'is_aggregate' => false,
		);
	}

	/**
	 * Превью k-means для админки (текущие признаки и k из формы).
	 *
	 * @param list<string> $features
	 * @return array<string, mixed>
	 */
	public static function preview_macro_clusters( string $scope, array $features, int $k ): array {
		$scope = ( 'city' === $scope ) ? 'city' : 'country';
		$allow = array_flip( self::macro_cluster_signal_allowlist( $scope ) );

		$clean = array();
		foreach ( $features as $sig ) {
			$sig = sanitize_key( (string) $sig );
			if ( $sig !== '' && isset( $allow[ $sig ] ) ) {
				$clean[ $sig ] = true;
			}
		}
		$features = array_keys( $clean );
		if ( count( $features ) < 2 ) {
			return array(
				'ok'      => false,
				'message' => __( 'Отметьте не меньше двух признаков для кластеризации.', 'worldstat-ergonomics' ),
			);
		}

		$bundle = ( 'city' === $scope ) ? self::get_city_full_bundle() : self::get_full_bundle();
		$rows   = isset( $bundle['raw_rows'] ) && is_array( $bundle['raw_rows'] ) ? $bundle['raw_rows'] : array();
		if ( count( $rows ) < 2 ) {
			return array(
				'ok'      => false,
				'message' => __( 'Недостаточно стран в CSV за опорный год.', 'worldstat-ergonomics' ),
			);
		}

		$run = self::run_kmeans_on_rows( $rows, $features, $k );
		if ( empty( $run['ok'] ) ) {
			return $run;
		}

		$countries  = array();
		$choropleth = array();
		$sizes      = array_fill( 0, (int) $run['k'], 0 );

		foreach ( $run['keys'] as $i => $iso3 ) {
			$cid  = (int) ( $run['labels'][ $i ] ?? 0 );
			$disp = $cid + 1;
			$meta = self::country_meta_for_iso3( (string) $iso3 );
			$pt           = $run['scaled'][ $i ] ?? array();
			$countries[]  = array(
				'iso3'    => $iso3,
				'iso2'    => (string) $meta['iso2'],
				'name'    => (string) $meta['name'],
				'cluster' => $disp,
				'x'       => isset( $pt[0] ) ? round( (float) $pt[0], 4 ) : 0.0,
				'y'       => isset( $pt[1] ) ? round( (float) $pt[1], 4 ) : 0.0,
			);
			if ( $cid >= 0 && $cid < (int) $run['k'] ) {
				++$sizes[ $cid ];
			}
			if ( strlen( (string) $meta['iso2'] ) === 2 ) {
				$choropleth[ (string) $meta['iso2'] ] = $disp;
			}
		}

		usort(
			$countries,
			static function ( $a, $b ) {
				$c = ( (int) $a['cluster'] ) <=> ( (int) $b['cluster'] );
				return 0 !== $c ? $c : strcmp( (string) $a['name'], (string) $b['name'] );
			}
		);

		$feat_labels = array();
		foreach ( $features as $f ) {
			$feat_labels[ $f ] = self::data_label_ru( $f );
		}

		$by_cl     = array();
		$feat_sums = array();
		$global_sum = array_fill_keys( $features, 0.0 );
		$global_n   = array_fill_keys( $features, 0 );
		foreach ( $run['keys'] as $i => $iso3 ) {
			$cid  = (int) ( $run['labels'][ $i ] ?? 0 );
			$disp = $cid + 1;
			$row  = isset( $rows[ $iso3 ] ) && is_array( $rows[ $iso3 ] ) ? $rows[ $iso3 ] : array();
			if ( ! isset( $by_cl[ $disp ] ) ) {
				$by_cl[ $disp ] = array();
			}
			$meta = self::country_meta_for_iso3( (string) $iso3 );
			$by_cl[ $disp ][] = array(
				'iso2' => (string) $meta['iso2'],
				'iso3' => (string) $iso3,
				'name' => (string) $meta['name'],
			);
			if ( ! isset( $feat_sums[ $disp ] ) ) {
				$feat_sums[ $disp ] = array_fill_keys( $features, 0.0 );
				$feat_sums[ $disp ]['_n'] = 0;
			}
			$has_vals = false;
			foreach ( $features as $feat ) {
				$v = isset( $row[ $feat ] ) ? (float) $row[ $feat ] : NAN;
				if ( ! is_finite( $v ) ) {
					continue;
				}
				$feat_sums[ $disp ][ $feat ] += $v;
				$global_sum[ $feat ] += $v;
				++$global_n[ $feat ];
				$has_vals = true;
			}
			if ( $has_vals ) {
				++$feat_sums[ $disp ]['_n'];
			}
		}
		$global_mean = array();
		foreach ( $features as $feat ) {
			$gn = (int) ( $global_n[ $feat ] ?? 0 );
			$global_mean[ $feat ] = $gn > 0 ? (float) $global_sum[ $feat ] / $gn : 0.0;
		}
		ksort( $by_cl, SORT_NUMERIC );
		$clusters = array();
		foreach ( $by_cl as $cl_num => $list ) {
			usort(
				$list,
				static function ( $a, $b ) {
					return strcmp( (string) ( $a['name'] ?? '' ), (string) ( $b['name'] ?? '' ) );
				}
			);
			$profile = self::describe_cluster_feature_profile( $features, $feat_sums[ $cl_num ] ?? array(), $global_mean );
			$clusters[] = array(
				'cluster'   => (int) $cl_num,
				'count'     => count( $list ),
				'profile'   => (string) ( $profile['label'] ?? '' ),
				'insight'   => (string) ( $profile['insight'] ?? '' ),
				'countries' => $list,
			);
		}
		$insights = self::build_cluster_summary_insights( $clusters, (int) $run['k'], array_values( $feat_labels ) );

		return array(
			'ok'             => true,
			'k'              => (int) $run['k'],
			'features'       => $features,
			'feature_labels' => $feat_labels,
			'axis_x'         => $features[0],
			'axis_y'         => $features[1] ?? $features[0],
			'countries'      => $countries,
			'clusters'       => $clusters,
			'insights'       => $insights,
			'choropleth'     => $choropleth,
			'cluster_sizes'  => array_values( $sizes ),
			'colors'         => array_slice( self::cluster_palette(), 0, (int) $run['k'] ),
			'year'           => (int) ( $bundle['y'] ?? 0 ),
			'n_countries'    => count( $countries ),
		);
	}

	/**
	 * Сводка кластеризации для админки (используется для нормализации внутри кластеров,
	 * поэтому влияет на индекс E и классификацию на сайте после сохранения настроек).
	 *
	 * @return array{
	 *   ok:bool,
	 *   k:int,
	 *   features:list<string>,
	 *   feature_labels:list<string>,
	 *   clusters:list<array{cluster:int,count:int,countries:list<array{iso2:string,name:string}>}>
	 * }
	 */
	public static function get_cluster_assignment_summary( string $scope = 'country' ): array {
		$scope = ( 'city' === $scope ) ? 'city' : 'country';
		$empty = array(
			'ok'             => false,
			'k'              => 0,
			'features'       => array(),
			'feature_labels' => array(),
			'clusters'       => array(),
		);
		if ( ! class_exists( 'WSErgo_Settings' ) ) {
			return $empty;
		}
		$features = ( 'city' === $scope )
			? WSErgo_Settings::get_city_macro_cluster_features()
			: WSErgo_Settings::get_macro_cluster_features();
		if ( count( $features ) < 2 ) {
			$features = self::CLUSTER_FEATURES;
		}
		$k = ( 'city' === $scope )
			? WSErgo_Settings::get_city_macro_k_clusters()
			: WSErgo_Settings::get_macro_k_clusters();
		$k = max( 2, min( 12, $k ) );

		$bundle = ( 'city' === $scope ) ? self::get_city_full_bundle() : self::get_full_bundle();
		$rows   = isset( $bundle['raw_rows'] ) && is_array( $bundle['raw_rows'] ) ? $bundle['raw_rows'] : array();
		if ( count( $rows ) < 2 ) {
			return $empty;
		}

		$run = self::run_kmeans_on_rows( $rows, $features, $k );
		if ( empty( $run['ok'] ) ) {
			return $empty;
		}

		$by_cl      = array();
		$feat_sums  = array();
		$global_sum = array_fill_keys( $features, 0.0 );
		$global_n   = array_fill_keys( $features, 0 );
		foreach ( $run['keys'] as $i => $iso3 ) {
			$cid  = (int) ( $run['labels'][ $i ] ?? 0 );
			$disp = $cid + 1;
			$meta = self::country_meta_for_iso3( (string) $iso3 );
			if ( ! isset( $by_cl[ $disp ] ) ) {
				$by_cl[ $disp ] = array();
			}
			$by_cl[ $disp ][] = array(
				'iso2' => (string) $meta['iso2'],
				'iso3' => (string) $iso3,
				'name' => (string) $meta['name'],
			);
			$row = isset( $rows[ $iso3 ] ) && is_array( $rows[ $iso3 ] ) ? $rows[ $iso3 ] : array();
			if ( ! isset( $feat_sums[ $disp ] ) ) {
				$feat_sums[ $disp ] = array_fill_keys( $features, 0.0 );
				$feat_sums[ $disp ]['_n'] = 0;
			}
			$has_vals = false;
			foreach ( $features as $feat ) {
				$v = isset( $row[ $feat ] ) ? (float) $row[ $feat ] : NAN;
				if ( ! is_finite( $v ) ) {
					continue;
				}
				$feat_sums[ $disp ][ $feat ] += $v;
				$global_sum[ $feat ] += $v;
				++$global_n[ $feat ];
				$has_vals = true;
			}
			if ( $has_vals ) {
				++$feat_sums[ $disp ]['_n'];
			}
		}
		ksort( $by_cl, SORT_NUMERIC );
		$global_mean = array();
		foreach ( $features as $feat ) {
			$gn = (int) ( $global_n[ $feat ] ?? 0 );
			$global_mean[ $feat ] = $gn > 0 ? (float) $global_sum[ $feat ] / $gn : 0.0;
		}

		$clusters = array();
		foreach ( $by_cl as $cl_num => $list ) {
			usort(
				$list,
				static function ( $a, $b ) {
					return strcmp( (string) ( $a['name'] ?? '' ), (string) ( $b['name'] ?? '' ) );
				}
			);
			$profile = self::describe_cluster_feature_profile( $features, $feat_sums[ $cl_num ] ?? array(), $global_mean );
			$clusters[] = array(
				'cluster'   => (int) $cl_num,
				'count'     => count( $list ),
				'profile'   => (string) ( $profile['label'] ?? '' ),
				'insight'   => (string) ( $profile['insight'] ?? '' ),
				'countries' => $list,
			);
		}

		$feat_labels = array();
		foreach ( $features as $f ) {
			$feat_labels[] = self::data_label_ru( $f );
		}

		$insights = self::build_cluster_summary_insights( $clusters, $k, $feat_labels );

		return array(
			'ok'             => true,
			'k'              => (int) $run['k'],
			'features'       => array_values( $features ),
			'feature_labels' => $feat_labels,
			'insights'       => $insights,
			'clusters'       => $clusters,
		);
	}

	/**
	 * @param list<string>              $features
	 * @param array<string, float|int>  $sums
	 * @param array<string, float>      $global_mean
	 * @return array{label:string,insight:string}
	 */
	private static function describe_cluster_feature_profile( array $features, array $sums, array $global_mean ): array {
		$n = (int) ( $sums['_n'] ?? 0 );
		if ( $n < 1 ) {
			return array(
				'label'   => __( 'Недостаточно данных', 'worldstat-ergonomics' ),
				'insight' => '',
			);
		}
		$diffs = array();
		foreach ( $features as $feat ) {
			$mean = (float) ( $sums[ $feat ] ?? 0 ) / $n;
			$diffs[] = array(
				'feat'  => $feat,
				'label' => self::data_label_ru( $feat ),
				'diff'  => $mean - (float) ( $global_mean[ $feat ] ?? 0 ),
				'mean'  => $mean,
			);
		}
		usort(
			$diffs,
			static function ( $a, $b ) {
				return ( $b['diff'] ?? 0 ) <=> ( $a['diff'] ?? 0 );
			}
		);
		$strong = $diffs[0];
		$weak   = $diffs[ count( $diffs ) - 1 ];
		$label  = sprintf(
			__( 'Выше среднего: %1$s; ниже: %2$s', 'worldstat-ergonomics' ),
			(string) ( $strong['label'] ?? '' ),
			(string) ( $weak['label'] ?? '' )
		);
		$insight = sprintf(
			__( 'Среднее по кластеру: %1$s — %2$s; %3$s — %4$s (относительно общей выборки).', 'worldstat-ergonomics' ),
			(string) ( $strong['label'] ?? '' ),
			number_format( (float) ( $strong['mean'] ?? 0 ), 2, ',', ' ' ),
			(string) ( $weak['label'] ?? '' ),
			number_format( (float) ( $weak['mean'] ?? 0 ), 2, ',', ' ' )
		);
		return array(
			'label'   => $label,
			'insight' => $insight,
		);
	}

	/**
	 * @param list<array{cluster:int,count:int,profile?:string,insight?:string}> $clusters
	 * @param list<string>                                                       $feat_labels
	 * @return list<string>
	 */
	private static function build_cluster_summary_insights( array $clusters, int $k, array $feat_labels ): array {
		$insights = array();
		if ( empty( $clusters ) ) {
			return $insights;
		}
		$total = 0;
		$max   = $clusters[0];
		$min   = $clusters[0];
		foreach ( $clusters as $cl ) {
			$cnt = (int) ( $cl['count'] ?? 0 );
			$total += $cnt;
			if ( $cnt > (int) ( $max['count'] ?? 0 ) ) {
				$max = $cl;
			}
			if ( $cnt < (int) ( $min['count'] ?? 0 ) ) {
				$min = $cl;
			}
		}
		$feat_str = implode( ', ', $feat_labels );
		$insights[] = sprintf(
			__( 'По признакам (%1$s) выделено %2$d кластеров, всего %3$d стран с данными.', 'worldstat-ergonomics' ),
			$feat_str !== '' ? $feat_str : '—',
			$k,
			$total
		);
		$insights[] = sprintf(
			__( 'Самый крупный — кластер %1$d (%2$d стран): %3$s.', 'worldstat-ergonomics' ),
			(int) ( $max['cluster'] ?? 0 ),
			(int) ( $max['count'] ?? 0 ),
			(string) ( $max['profile'] ?? '' )
		);
		if ( (int) ( $min['cluster'] ?? 0 ) !== (int) ( $max['cluster'] ?? 0 ) ) {
			$insights[] = sprintf(
				__( 'Самый компактный — кластер %1$d (%2$d стран): %3$s.', 'worldstat-ergonomics' ),
				(int) ( $min['cluster'] ?? 0 ),
				(int) ( $min['count'] ?? 0 ),
				(string) ( $min['profile'] ?? '' )
			);
		}
		$insights[] = __( 'Кластеризация по CSV используется для нормализации внутри кластеров и влияет на индекс E и классификацию на сайте (после сохранения настроек).', 'worldstat-ergonomics' );
		return $insights;
	}

	/**
	 * @param array<string, array<string, float>> $rows
	 * @param list<string>                        $features
	 * @return array<string, mixed>
	 */
	private static function run_kmeans_on_rows( array $rows, array $features, int $k ): array {
		$medians = array();
		foreach ( $features as $feat ) {
			$col = array();
			foreach ( $rows as $r ) {
				if ( is_array( $r ) && isset( $r[ $feat ] ) && is_finite( (float) $r[ $feat ] ) ) {
					$col[] = (float) $r[ $feat ];
				}
			}
			$medians[ $feat ] = self::median_floats( $col );
		}

		$matrix = array();
		$keys   = array();
		foreach ( $rows as $iso3 => $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$vec = array();
			foreach ( $features as $feat ) {
				$v = isset( $r[ $feat ] ) ? (float) $r[ $feat ] : NAN;
				if ( ! is_finite( $v ) ) {
					$v = $medians[ $feat ];
				}
				$vec[] = $v;
			}
			$keys[]   = (string) $iso3;
			$matrix[] = $vec;
		}

		if ( count( $matrix ) < 2 ) {
			return array(
				'ok'      => false,
				'message' => __( 'Недостаточно стран с данными для кластеризации.', 'worldstat-ergonomics' ),
			);
		}

		$scaled = self::standard_scale_rows( $matrix );
		$k      = max( 2, min( 12, $k, count( $scaled ) ) );
		$labels = array();
		if ( class_exists( 'WSErgo_Macro_Cluster_Optimizer' ) ) {
			$labels = WSErgo_Macro_Cluster_Optimizer::best_cluster_labels( $scaled, $k );
		}
		if ( empty( $labels ) || count( $labels ) !== count( $scaled ) ) {
			$labels = self::kmeans( $scaled, $k, 80 );
			if ( class_exists( 'WSErgo_Macro_Cluster_Optimizer' )
				&& ! WSErgo_Macro_Cluster_Optimizer::is_partition_valid_for_labels( $labels, $k ) ) {
				return array(
					'ok'      => false,
					'message' => sprintf(
						/* translators: %d: cluster count k */
						__( 'Не удалось разбить страны на %d кластеров с ограничениями (в каждом ≥10 стран, крупнейший <60%%). Уменьшите k или смените признаки.', 'worldstat-ergonomics' ),
						$k
					),
				);
			}
		}

		return array(
			'ok'     => true,
			'keys'   => $keys,
			'labels' => $labels,
			'scaled' => $scaled,
			'k'      => $k,
		);
	}

	/**
	 * Признаки k-means: из настроек (≥2 отмеченных) или встроенный список.
	 *
	 * @return list<string>
	 */
	private static function get_effective_cluster_features( array $rows = array() ): array {
		$allow = array_flip( self::macro_cluster_signal_allowlist( 'country' ) );
		$saved = array();
		if ( class_exists( 'WSErgo_Settings' ) ) {
			foreach ( WSErgo_Settings::get_macro_cluster_features() as $sig ) {
				$sig = sanitize_key( (string) $sig );
				if ( $sig !== '' && isset( $allow[ $sig ] ) ) {
					$saved[] = $sig;
				}
			}
			$saved = array_values( array_unique( $saved ) );
			if ( count( $saved ) >= 2 ) {
				return $saved;
			}
		}
		if ( [] !== $rows ) {
			$fb = self::fallback_cluster_features_from_rows( $rows, array_keys( $allow ) );
			if ( count( $fb ) >= 2 ) {
				return $fb;
			}
		}
		return array();
	}

	/**
	 * Столбцы для min–max внутри кластера (базовый список + признаки из формул осей + признаки кластера).
	 *
	 * @return list<string>
	 */
	/**
	 * Формулы осей F–Ct из матрицы критериев и весов админки (fallback — встроенная методика).
	 *
	 * @return array<string, list<array{signal:string, invert:bool, weight:float}>>
	 */
	private static function get_effective_macro_axis_terms(): array {
		if ( class_exists( 'WSErgo_Settings' ) ) {
			$res = WSErgo_Settings::get_macro_axis_terms_resolved();
			if ( is_array( $res ) ) {
				return $res;
			}
		}
		return self::default_macro_axis_terms();
	}

	private static function collect_normalization_columns(): array {
		$cols = self::NORMALIZE_WITHIN_CLUSTER;
		$axis_terms = self::get_effective_macro_axis_terms();
		foreach ( $axis_terms as $rows ) {
			foreach ( $rows as $row ) {
				$sig = sanitize_key( (string) ( $row['signal'] ?? '' ) );
				if ( $sig !== '' ) {
					$cols[] = $sig;
				}
			}
		}
		foreach ( self::get_effective_cluster_features() as $cf ) {
			$cols[] = $cf;
		}
		return array_values( array_unique( $cols ) );
	}

	/**
	 * @return list<string>
	 */
	private static function get_city_effective_cluster_features( array $rows = array() ): array {
		$allow = array_flip( self::macro_cluster_signal_allowlist( 'city' ) );
		$saved = array();
		if ( class_exists( 'WSErgo_Settings' ) ) {
			foreach ( WSErgo_Settings::get_city_macro_cluster_features() as $sig ) {
				$sig = sanitize_key( (string) $sig );
				if ( $sig !== '' && isset( $allow[ $sig ] ) ) {
					$saved[] = $sig;
				}
			}
			$saved = array_values( array_unique( $saved ) );
			if ( count( $saved ) >= 2 ) {
				return $saved;
			}
		}
		if ( [] !== $rows ) {
			$fb = self::fallback_cluster_features_from_rows( $rows, array_keys( $allow ) );
			if ( count( $fb ) >= 2 ) {
				return $fb;
			}
		}
		return array();
	}

	/**
	 * @return array<string, list<array{signal:string, invert:bool, weight:float}>>
	 */
	private static function get_city_effective_macro_axis_terms(): array {
		if ( class_exists( 'WSErgo_Settings' ) ) {
			$res = WSErgo_Settings::get_city_macro_axis_terms_resolved();
			if ( is_array( $res ) && ! empty( $res ) ) {
				return $res;
			}
		}
		return self::default_macro_axis_terms();
	}

	private static function collect_normalization_columns_city(): array {
		$cols       = self::NORMALIZE_WITHIN_CLUSTER;
		$axis_terms = self::get_city_effective_macro_axis_terms();
		foreach ( $axis_terms as $rows ) {
			foreach ( $rows as $row ) {
				$sig = sanitize_key( (string) ( $row['signal'] ?? '' ) );
				if ( $sig !== '' ) {
					$cols[] = $sig;
				}
			}
		}
		foreach ( self::get_city_effective_cluster_features() as $cf ) {
			$cols[] = $cf;
		}
		return array_values( array_unique( $cols ) );
	}

	/**
	 * Производные по формулам вкладки «Эргономичность города».
	 *
	 * @param array<string, array<string, float>> $rows
	 */
	private static function apply_city_custom_metrics_to_rows( array &$rows ): void {
		if ( ! class_exists( 'WSErgo_Settings' ) ) {
			return;
		}
		$defs = WSErgo_Settings::get_city_macro_custom_metrics();
		if ( empty( $defs ) ) {
			return;
		}
		$ops_bin = array( 'add' => true, 'sub' => true, 'mul' => true, 'div' => true );
		foreach ( $rows as $iso3 => &$row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			foreach ( $defs as $def ) {
				$slug = isset( $def['slug'] ) ? sanitize_key( (string) $def['slug'] ) : '';
				$op   = isset( $def['op'] ) ? sanitize_key( (string) $def['op'] ) : '';
				if ( $slug === '' || $op === '' ) {
					continue;
				}
				$ka = isset( $def['key_a'] ) ? sanitize_key( (string) $def['key_a'] ) : '';
				$kb = isset( $def['key_b'] ) ? sanitize_key( (string) $def['key_b'] ) : '';
				$c  = isset( $def['const'] ) && is_numeric( $def['const'] ) ? (float) $def['const'] : 0.0;
				$va = self::finite_or_nan( isset( $row[ $ka ] ) ? (float) $row[ $ka ] : NAN );
				$vb = self::finite_or_nan( isset( $row[ $kb ] ) ? (float) $row[ $kb ] : NAN );
				$res = NAN;
				if ( isset( $ops_bin[ $op ] ) ) {
					if ( $ka === '' || $kb === '' ) {
						continue;
					}
					if ( $op === 'add' && is_finite( $va ) && is_finite( $vb ) ) {
						$res = $va + $vb;
					} elseif ( $op === 'sub' && is_finite( $va ) && is_finite( $vb ) ) {
						$res = $va - $vb;
					} elseif ( $op === 'mul' && is_finite( $va ) && is_finite( $vb ) ) {
						$res = $va * $vb;
					} elseif ( $op === 'div' ) {
						$res = self::safe_div( $va, $vb );
					}
				} elseif ( $op === 'scale_mul' ) {
					if ( $ka === '' || ! is_finite( $va ) ) {
						continue;
					}
					$res = $va * $c;
				} elseif ( $op === 'scale_add' ) {
					if ( $ka === '' || ! is_finite( $va ) ) {
						continue;
					}
					$res = $va + $c;
				} else {
					continue;
				}
				$row[ $slug ] = $res;
			}
			unset( $row );
		}
	}

	/**
	 * @param \Closure(string): ?float $g
	 * @param list<array{signal:string, invert:bool, weight:float}> $term_rows
	 * @return array{0: list<float>, 1: list<float>}
	 */
	private static function macro_axis_vals_and_weights( \Closure $g, array $term_rows ): array {
		$vals = array();
		$wts  = array();
		foreach ( $term_rows as $row ) {
			$sig = sanitize_key( (string) ( $row['signal'] ?? '' ) );
			$wt  = (float) ( $row['weight'] ?? 0 );
			if ( $sig === '' || $wt <= 0 ) {
				continue;
			}
			$inv = ! empty( $row['invert'] );
			$raw = $g( $sig );
			$vals[] = ( null === $raw ) ? NAN : ( $inv ? ( 1.0 - $raw ) : $raw );
			$wts[]  = $wt;
		}
		return array( $vals, $wts );
	}

	/**
	 * @param \Closure(string): ?float $g
	 * @param list<array{signal:string, invert:bool, weight:float}> $term_rows
	 */
	private static function compute_macro_axis_from_terms( \Closure $g, array $term_rows ): float {
		list( $vals, $wts ) = self::macro_axis_vals_and_weights( $g, $term_rows );
		return self::weighted_sum_finite( $vals, $wts );
	}

	/**
	 * @return array{scores:array<string,array<string,float>>,diag:array<string,array<string,mixed>>,raw_rows:array<string,array<string,float>>}
	 */
	private static function compute_full_bundle( int $target_year ): array {
		$empty = array(
			'scores'   => array(),
			'diag'     => array(),
			'raw_rows' => array(),
		);
		if ( ! class_exists( 'WorldStat_Uploaded_Csv' ) ) {
			return $empty;
		}

		$ingest = self::ingest_uploaded_csvs();
		$built  = self::build_feature_rows( $ingest, $target_year );
		if ( ! is_array( $built ) ) {
			return $empty;
		}
		$rows = isset( $built['rows'] ) && is_array( $built['rows'] ) ? $built['rows'] : array();
		$diag = isset( $built['diagnostics'] ) && is_array( $built['diagnostics'] ) ? $built['diagnostics'] : array();
		if ( count( $rows ) < 1 ) {
			return $empty;
		}
		self::apply_newdata_derived_metrics_to_rows( $rows );
		self::apply_user_custom_metrics_to_rows( $rows );

		$cluster_feats = self::get_effective_cluster_features( $rows );

		$medians = array();
		foreach ( $cluster_feats as $feat ) {
			$col = array();
			foreach ( $rows as $r ) {
				if ( isset( $r[ $feat ] ) && is_finite( (float) $r[ $feat ] ) ) {
					$col[] = (float) $r[ $feat ];
				}
			}
			$medians[ $feat ] = self::median_floats( $col );
		}

		$matrix              = array();
		$keys                = array();
		$cluster_median_fill = array();
		foreach ( $rows as $iso3 => $r ) {
			$vec  = array();
			$fill = array();
			foreach ( $cluster_feats as $feat ) {
				$v = isset( $r[ $feat ] ) ? (float) $r[ $feat ] : NAN;
				if ( ! is_finite( $v ) ) {
					$v = $medians[ $feat ];
					$fill[] = $feat;
				}
				$vec[] = $v;
			}
			$keys[]                = $iso3;
			$matrix[]              = $vec;
			$cluster_median_fill[ $iso3 ] = $fill;
		}

		if ( count( $matrix ) < 1 ) {
			return $empty;
		}

		$scaled = self::standard_scale_rows( $matrix );
		$k      = min( WSErgo_Settings::get_macro_k_clusters(), count( $scaled ) );
		$labels = array();
		if ( class_exists( 'WSErgo_Macro_Cluster_Optimizer' ) ) {
			$labels = WSErgo_Macro_Cluster_Optimizer::best_cluster_labels( $scaled, $k );
		}
		if ( empty( $labels ) || count( $labels ) !== count( $scaled ) ) {
			$labels = self::kmeans( $scaled, $k, 80 );
		}

		$n = count( $keys );
		for ( $i = 0; $i < $n; $i++ ) {
			$iso3 = $keys[ $i ];
			if ( ! isset( $diag[ $iso3 ] ) || ! is_array( $diag[ $iso3 ] ) ) {
				$diag[ $iso3 ] = array();
			}
			$diag[ $iso3 ]['kmeans_cluster'] = (int) ( $labels[ $i ] ?? 0 ) + 1;
			$diag[ $iso3 ]['kmeans_k']         = $k;
		}
		$norm_cols = array();
		foreach ( self::collect_normalization_columns() as $col ) {
			$vals = array();
			for ( $i = 0; $i < $n; $i++ ) {
				$iso3   = $keys[ $i ];
				$vals[] = isset( $rows[ $iso3 ][ $col ] ) ? (float) $rows[ $iso3 ][ $col ] : NAN;
			}
			$norm_cols[ $col ] = self::normalize_within_clusters_1d( $vals, $labels, $k );
		}

		if ( isset( $norm_cols['sustainable_cities'] ) ) {
			$norm_cols['sdg11'] = $norm_cols['sustainable_cities'];
		}
		if ( isset( $norm_cols['industry_innovation_infrastructure'] ) ) {
			$norm_cols['sdg9'] = $norm_cols['industry_innovation_infrastructure'];
		}
		if ( isset( $norm_cols['peace_justice'] ) ) {
			$norm_cols['sdg16'] = $norm_cols['peace_justice'];
		}
		if ( isset( $norm_cols['sdg_index_score'] ) ) {
			$norm_cols['sdg_index'] = $norm_cols['sdg_index_score'];
		}

		$out = array();
		for ( $i = 0; $i < $n; $i++ ) {
			$iso3 = $keys[ $i ];
			$g    = static function ( $name ) use ( $norm_cols, $i ) {
				if ( ! isset( $norm_cols[ $name ][ $i ] ) ) {
					return null;
				}
				$v = (float) $norm_cols[ $name ][ $i ];
				return is_finite( $v ) ? $v : null;
			};

			$axis_terms = self::get_effective_macro_axis_terms();

			list( $f_vals, $f_w ) = self::macro_axis_vals_and_weights( $g, $axis_terms['F'] );
			$F                   = self::compute_macro_axis_from_terms( $g, $axis_terms['F'] );
			list( $cm_vals, $cm_w ) = self::macro_axis_vals_and_weights( $g, $axis_terms['Cm'] );
			$Cm                    = self::compute_macro_axis_from_terms( $g, $axis_terms['Cm'] );
			list( $h_vals, $h_w ) = self::macro_axis_vals_and_weights( $g, $axis_terms['H'] );
			$H                   = self::compute_macro_axis_from_terms( $g, $axis_terms['H'] );
			list( $a_vals, $a_w ) = self::macro_axis_vals_and_weights( $g, $axis_terms['A'] );
			$A                   = self::compute_macro_axis_from_terms( $g, $axis_terms['A'] );
			list( $s_vals, $s_w ) = self::macro_axis_vals_and_weights( $g, $axis_terms['S'] );
			$S                   = self::compute_macro_axis_from_terms( $g, $axis_terms['S'] );
			list( $ct_vals, $ct_w ) = self::macro_axis_vals_and_weights( $g, $axis_terms['Ct'] );
			$Ct                    = self::compute_macro_axis_from_terms( $g, $axis_terms['Ct'] );

			$ew = class_exists( 'WSErgo_Settings' )
				? WSErgo_Settings::get_macro_e_axis_weights()
				: array(
					'F'  => 0.24,
					'Cm' => 0.22,
					'H'  => 0.18,
					'A'  => 0.14,
					'S'  => 0.12,
					'Ct' => 0.10,
				);
			$E = self::weighted_sum_finite(
				array( $F, $Cm, $H, $A, $S, $Ct ),
				array( $ew['F'], $ew['Cm'], $ew['H'], $ew['A'], $ew['S'], $ew['Ct'] )
			);

			if ( ! isset( $diag[ $iso3 ] ) || ! is_array( $diag[ $iso3 ] ) ) {
				$diag[ $iso3 ] = array();
			}
			$fill = $cluster_median_fill[ $iso3 ] ?? array();
			if ( ! empty( $fill ) ) {
				$diag[ $iso3 ]['cluster_median_imputed_features'] = $fill;
			}
			$diag[ $iso3 ]['axis_weight_used'] = array(
				'F'  => self::finite_weight_fraction( $f_vals, $f_w ),
				'Cm' => self::finite_weight_fraction( $cm_vals, $cm_w ),
				'H'  => self::finite_weight_fraction( $h_vals, $h_w ),
				'A'  => self::finite_weight_fraction( $a_vals, $a_w ),
				'S'  => self::finite_weight_fraction( $s_vals, $s_w ),
				'Ct' => self::finite_weight_fraction( $ct_vals, $ct_w ),
			);

			if ( ! is_finite( $E ) || $E <= 0 ) {
				$diag[ $iso3 ]['index_unavailable'] = true;
				continue;
			}

			$out[ $iso3 ] = array(
				'E'  => $E * 100.0,
				'F'  => is_finite( $F ) ? $F * 100.0 : 0.0,
				'Cm' => is_finite( $Cm ) ? $Cm * 100.0 : 0.0,
				'H'  => is_finite( $H ) ? $H * 100.0 : 0.0,
				'A'  => is_finite( $A ) ? $A * 100.0 : 0.0,
				'S'  => is_finite( $S ) ? $S * 100.0 : 0.0,
				'Ct' => is_finite( $Ct ) ? $Ct * 100.0 : 0.0,
			);
		}

		return array(
			'scores'   => $out,
			'diag'     => $diag,
			'raw_rows' => $rows,
		);
	}

	/**
	 * Тот же CSV-пайплайн, что и {@see compute_full_bundle()}, но кластеризация, нормализация, оси F–Ct и веса E — из настроек «Эргономичность города».
	 *
	 * @return array{scores:array<string,array<string,float>>,diag:array<string,array<string,mixed>>,raw_rows:array<string,array<string,float>>}
	 */
	private static function compute_full_bundle_city( int $target_year ): array {
		$empty = array(
			'scores'   => array(),
			'diag'     => array(),
			'raw_rows' => array(),
		);
		if ( ! class_exists( 'WorldStat_Uploaded_Csv' ) || ! class_exists( 'WSErgo_Settings' ) ) {
			return $empty;
		}

		$ingest = self::ingest_uploaded_csvs();
		$built  = self::build_feature_rows( $ingest, $target_year );
		if ( ! is_array( $built ) ) {
			return $empty;
		}
		$rows = isset( $built['rows'] ) && is_array( $built['rows'] ) ? $built['rows'] : array();
		$diag = isset( $built['diagnostics'] ) && is_array( $built['diagnostics'] ) ? $built['diagnostics'] : array();
		if ( count( $rows ) < 1 ) {
			return $empty;
		}
		self::apply_newdata_derived_metrics_to_rows( $rows );
		self::apply_user_custom_metrics_to_rows( $rows );
		self::apply_city_custom_metrics_to_rows( $rows );

		$cluster_feats = self::get_city_effective_cluster_features( $rows );

		$medians = array();
		foreach ( $cluster_feats as $feat ) {
			$col = array();
			foreach ( $rows as $r ) {
				if ( isset( $r[ $feat ] ) && is_finite( (float) $r[ $feat ] ) ) {
					$col[] = (float) $r[ $feat ];
				}
			}
			$medians[ $feat ] = self::median_floats( $col );
		}

		$matrix              = array();
		$keys                = array();
		$cluster_median_fill = array();
		foreach ( $rows as $iso3 => $r ) {
			$vec  = array();
			$fill = array();
			foreach ( $cluster_feats as $feat ) {
				$v = isset( $r[ $feat ] ) ? (float) $r[ $feat ] : NAN;
				if ( ! is_finite( $v ) ) {
					$v      = $medians[ $feat ];
					$fill[] = $feat;
				}
				$vec[] = $v;
			}
			$keys[]                       = $iso3;
			$matrix[]                     = $vec;
			$cluster_median_fill[ $iso3 ] = $fill;
		}

		if ( count( $matrix ) < 1 ) {
			return $empty;
		}

		$scaled = self::standard_scale_rows( $matrix );
		$k      = min( WSErgo_Settings::get_city_macro_k_clusters(), count( $scaled ) );
		$labels = array();
		if ( class_exists( 'WSErgo_Macro_Cluster_Optimizer' ) ) {
			$labels = WSErgo_Macro_Cluster_Optimizer::best_cluster_labels( $scaled, $k );
		}
		if ( empty( $labels ) || count( $labels ) !== count( $scaled ) ) {
			$labels = self::kmeans( $scaled, $k, 80 );
		}

		$n = count( $keys );
		for ( $i = 0; $i < $n; $i++ ) {
			$iso3 = $keys[ $i ];
			if ( ! isset( $diag[ $iso3 ] ) || ! is_array( $diag[ $iso3 ] ) ) {
				$diag[ $iso3 ] = array();
			}
			$diag[ $iso3 ]['kmeans_cluster'] = (int) ( $labels[ $i ] ?? 0 ) + 1;
			$diag[ $iso3 ]['kmeans_k']         = $k;
		}
		$norm_cols = array();
		foreach ( self::collect_normalization_columns_city() as $col ) {
			$vals = array();
			for ( $i = 0; $i < $n; $i++ ) {
				$iso3   = $keys[ $i ];
				$vals[] = isset( $rows[ $iso3 ][ $col ] ) ? (float) $rows[ $iso3 ][ $col ] : NAN;
			}
			$norm_cols[ $col ] = self::normalize_within_clusters_1d( $vals, $labels, $k );
		}

		if ( isset( $norm_cols['sustainable_cities'] ) ) {
			$norm_cols['sdg11'] = $norm_cols['sustainable_cities'];
		}
		if ( isset( $norm_cols['industry_innovation_infrastructure'] ) ) {
			$norm_cols['sdg9'] = $norm_cols['industry_innovation_infrastructure'];
		}
		if ( isset( $norm_cols['peace_justice'] ) ) {
			$norm_cols['sdg16'] = $norm_cols['peace_justice'];
		}
		if ( isset( $norm_cols['sdg_index_score'] ) ) {
			$norm_cols['sdg_index'] = $norm_cols['sdg_index_score'];
		}

		$out = array();
		for ( $i = 0; $i < $n; $i++ ) {
			$iso3 = $keys[ $i ];
			$g    = static function ( $name ) use ( $norm_cols, $i ) {
				if ( ! isset( $norm_cols[ $name ][ $i ] ) ) {
					return null;
				}
				$v = (float) $norm_cols[ $name ][ $i ];
				return is_finite( $v ) ? $v : null;
			};

			$axis_terms = self::get_city_effective_macro_axis_terms();

			list( $f_vals, $f_w ) = self::macro_axis_vals_and_weights( $g, $axis_terms['F'] ?? array() );
			$F                   = self::compute_macro_axis_from_terms( $g, $axis_terms['F'] ?? array() );
			list( $cm_vals, $cm_w ) = self::macro_axis_vals_and_weights( $g, $axis_terms['Cm'] ?? array() );
			$Cm                    = self::compute_macro_axis_from_terms( $g, $axis_terms['Cm'] ?? array() );
			list( $h_vals, $h_w ) = self::macro_axis_vals_and_weights( $g, $axis_terms['H'] ?? array() );
			$H                   = self::compute_macro_axis_from_terms( $g, $axis_terms['H'] ?? array() );
			list( $a_vals, $a_w ) = self::macro_axis_vals_and_weights( $g, $axis_terms['A'] ?? array() );
			$A                   = self::compute_macro_axis_from_terms( $g, $axis_terms['A'] ?? array() );
			list( $s_vals, $s_w ) = self::macro_axis_vals_and_weights( $g, $axis_terms['S'] ?? array() );
			$S                   = self::compute_macro_axis_from_terms( $g, $axis_terms['S'] ?? array() );
			list( $ct_vals, $ct_w ) = self::macro_axis_vals_and_weights( $g, $axis_terms['Ct'] ?? array() );
			$Ct                    = self::compute_macro_axis_from_terms( $g, $axis_terms['Ct'] ?? array() );

			$ew = WSErgo_Settings::get_city_macro_e_axis_weights();
			$E  = self::weighted_sum_finite(
				array( $F, $Cm, $H, $A, $S, $Ct ),
				array( $ew['F'], $ew['Cm'], $ew['H'], $ew['A'], $ew['S'], $ew['Ct'] )
			);

			if ( ! isset( $diag[ $iso3 ] ) || ! is_array( $diag[ $iso3 ] ) ) {
				$diag[ $iso3 ] = array();
			}
			$fill = $cluster_median_fill[ $iso3 ] ?? array();
			if ( ! empty( $fill ) ) {
				$diag[ $iso3 ]['cluster_median_imputed_features'] = $fill;
			}
			$diag[ $iso3 ]['axis_weight_used'] = array(
				'F'  => self::finite_weight_fraction( $f_vals, $f_w ),
				'Cm' => self::finite_weight_fraction( $cm_vals, $cm_w ),
				'H'  => self::finite_weight_fraction( $h_vals, $h_w ),
				'A'  => self::finite_weight_fraction( $a_vals, $a_w ),
				'S'  => self::finite_weight_fraction( $s_vals, $s_w ),
				'Ct' => self::finite_weight_fraction( $ct_vals, $ct_w ),
			);

			if ( ! is_finite( $E ) || $E <= 0 ) {
				$diag[ $iso3 ]['index_unavailable'] = true;
				continue;
			}

			$out[ $iso3 ] = array(
				'E'  => $E * 100.0,
				'F'  => is_finite( $F ) ? $F * 100.0 : 0.0,
				'Cm' => is_finite( $Cm ) ? $Cm * 100.0 : 0.0,
				'H'  => is_finite( $H ) ? $H * 100.0 : 0.0,
				'A'  => is_finite( $A ) ? $A * 100.0 : 0.0,
				'S'  => is_finite( $S ) ? $S * 100.0 : 0.0,
				'Ct' => is_finite( $Ct ) ? $Ct * 100.0 : 0.0,
			);
		}

		return array(
			'scores'   => $out,
			'diag'     => $diag,
			'raw_rows' => $rows,
		);
	}

	/**
	 * Производные столбцы D1..D19 для новой формулы (calculates.txt).
	 *
	 * @param array<string, array<string, float>> $rows
	 */
	private static function apply_newdata_derived_metrics_to_rows( array &$rows ): void {
		foreach ( $rows as $iso3 => $row ) {
			$pop = self::finite_or_nan( $row['pop_total__psn'] ?? NAN );
			$land = self::finite_or_nan( $row['land_area__km_sq'] ?? NAN );

			$urban_share = self::safe_div( $row['urban_pop_share__ptc'] ?? NAN, 100.0 );
			$big_city_ratio = self::safe_div( $row['largest_city_pop__psn'] ?? NAN, $pop );
			$road_dens = self::safe_div( self::finite_or_zero( $row['road_length__km'] ?? NAN ) * 1000.0, $land );
			$rail_dens = self::safe_div( self::finite_or_zero( $row['railway_length__km'] ?? NAN ) * 1000.0, $land );
			$forest_cover = self::safe_div( $row['forest_area__ptc'] ?? NAN, 100.0 );
			$forest_area_km2 = is_finite( $land ) && is_finite( $forest_cover ) ? ( $land * $forest_cover ) : NAN;
			$forest_per_cap_m2 = self::safe_div( $forest_area_km2 * 1000000.0, $pop );
			$agri_pressure = self::safe_div( $row['agri_land__ptc'] ?? NAN, 100.0 );
			$age_dep = self::safe_div( self::finite_or_zero( $row['pop_age0_14__ptc'] ?? NAN ) + self::finite_or_zero( $row['pop_age65_up__ptc'] ?? NAN ), 100.0 );
			$clean_elec = self::finite_or_zero( $row['electricity_hydro__ptc'] ?? NAN ) + self::finite_or_zero( $row['electricity_nuclear__ptc'] ?? NAN );
			$fossil_elec = self::finite_or_zero( $row['electricity_coal__ptc'] ?? NAN ) + self::finite_or_zero( $row['electricity_oil__ptc'] ?? NAN ) + self::finite_or_zero( $row['electricity_gas__ptc'] ?? NAN );
			$secure = self::finite_or_nan( $row['secure_servers__per_1m_psn'] ?? NAN );
			if ( is_finite( $secure ) ) {
				$secure = min( $secure, 2000.0 );
			}
			$digital_access = (
				self::finite_or_zero( $row['internet_users__ptc'] ?? NAN ) +
				self::finite_or_zero( $row['broadband__per_100_psn'] ?? NAN ) +
				self::finite_or_zero( $row['mobile_subs__per_100_psn'] ?? NAN ) +
				self::finite_or_zero( $secure )
			) / 4.0;
			$health_capacity = self::finite_or_zero( $row['hosp_beds__per_1000_psn'] ?? NAN ) + self::finite_or_zero( $row['physicians__per_1000_psn'] ?? NAN );
			$wash = (
				self::finite_or_zero( $row['access_drinking_water_basic__ptc'] ?? NAN ) +
				self::finite_or_zero( $row['access_sanitation_basic__ptc'] ?? NAN ) +
				self::finite_or_zero( $row['access_clean_cooking__ptc'] ?? NAN ) +
				self::finite_or_zero( $row['access_electricity__ptc'] ?? NAN )
			) / 4.0;
			$infect = self::finite_or_zero( $row['malaria_incidence__per_1000'] ?? NAN ) +
				( self::finite_or_zero( $row['tuberculosis_incidence__per_100k'] ?? NAN ) / 100.0 ) +
				( self::finite_or_zero( $row['hiv_prevalence__ptc'] ?? NAN ) * 10.0 ) +
				( self::finite_or_zero( $row['suicide_rate__per_100k'] ?? NAN ) / 10.0 );
			$substance = self::finite_or_zero( $row['alcohol_total__liters_per_cap'] ?? NAN ) + ( self::finite_or_zero( $row['tobacco_adult__ptc'] ?? NAN ) / 10.0 );
			$fiscal = self::safe_div( $row['tax_revenue__ptc_gdp'] ?? NAN, max( self::finite_or_zero( $row['gov_expense__ptc_gdp'] ?? NAN ), 1e-6 ) );
			$debt = self::safe_div( $row['external_debt__ptc_gni'] ?? NAN, 100.0 );
			$rent_fuels = self::finite_or_zero( $row['coal_rents__ptc_gdp'] ?? NAN ) + self::finite_or_zero( $row['gas_rents__ptc_gdp'] ?? NAN );

			$transport = NAN;
			if ( is_finite( $road_dens ) || is_finite( $rail_dens ) ) {
				$transport = self::finite_or_zero( $road_dens ) + self::finite_or_zero( $rail_dens );
			}

			$row['urban_share_01'] = $urban_share;
			$row['big_city_ratio'] = $big_city_ratio;
			$row['road_dens_km_per_km2'] = $road_dens;
			$row['rail_dens_km_per_km2'] = $rail_dens;
			$row['transport_dens'] = $transport;
			$row['forest_cover_01'] = $forest_cover;
			$row['forest_area_km2'] = $forest_area_km2;
			$row['forest_per_capita_m2'] = $forest_per_cap_m2;
			$row['agri_pressure'] = $agri_pressure;
			$row['age_dependency_proxy'] = $age_dep;
			$row['clean_elec_share__ptc'] = $clean_elec;
			$row['fossil_elec_share__ptc'] = $fossil_elec;
			$row['digital_access_index'] = $digital_access;
			$row['health_system_capacity'] = $health_capacity;
			$row['wASH_access_index'] = $wash;
			$row['infect_and_stress_burden'] = $infect;
			$row['substance_burden'] = $substance;
			$row['fiscal_transparency_proxy'] = $fiscal;
			$row['debt_stress'] = $debt;
			$row['rent_fuels'] = $rent_fuels;

			$rows[ $iso3 ] = $row;
		}
	}

	/**
	 * Пользовательские формулы из настроек (после встроенных производных).
	 *
	 * @param array<string, array<string, float>> $rows
	 */
	private static function apply_user_custom_metrics_to_rows( array &$rows ): void {
		if ( ! class_exists( 'WSErgo_Settings' ) ) {
			return;
		}
		$defs = WSErgo_Settings::get_macro_custom_metrics();
		if ( empty( $defs ) ) {
			return;
		}
		$ops_bin = array( 'add' => true, 'sub' => true, 'mul' => true, 'div' => true );
		foreach ( $rows as $iso3 => &$row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			foreach ( $defs as $def ) {
				$slug = isset( $def['slug'] ) ? sanitize_key( (string) $def['slug'] ) : '';
				$op   = isset( $def['op'] ) ? sanitize_key( (string) $def['op'] ) : '';
				if ( $slug === '' || $op === '' ) {
					continue;
				}
				$ka = isset( $def['key_a'] ) ? sanitize_key( (string) $def['key_a'] ) : '';
				$kb = isset( $def['key_b'] ) ? sanitize_key( (string) $def['key_b'] ) : '';
				$c  = isset( $def['const'] ) && is_numeric( $def['const'] ) ? (float) $def['const'] : 0.0;
				$va = self::finite_or_nan( isset( $row[ $ka ] ) ? (float) $row[ $ka ] : NAN );
				$vb = self::finite_or_nan( isset( $row[ $kb ] ) ? (float) $row[ $kb ] : NAN );
				$res = NAN;
				if ( isset( $ops_bin[ $op ] ) ) {
					if ( $ka === '' || $kb === '' ) {
						continue;
					}
					if ( $op === 'add' && is_finite( $va ) && is_finite( $vb ) ) {
						$res = $va + $vb;
					} elseif ( $op === 'sub' && is_finite( $va ) && is_finite( $vb ) ) {
						$res = $va - $vb;
					} elseif ( $op === 'mul' && is_finite( $va ) && is_finite( $vb ) ) {
						$res = $va * $vb;
					} elseif ( $op === 'div' ) {
						$res = self::safe_div( $va, $vb );
					}
				} elseif ( $op === 'scale_mul' ) {
					if ( $ka === '' || ! is_finite( $va ) ) {
						continue;
					}
					$res = $va * $c;
				} elseif ( $op === 'scale_add' ) {
					if ( $ka === '' || ! is_finite( $va ) ) {
						continue;
					}
					$res = $va + $c;
				} else {
					continue;
				}
				$row[ $slug ] = $res;
			}
			unset( $row );
		}
	}

	/**
	 * @param list<float> $values
	 * @param list<int>   $labels
	 * @return list<float>
	 */
	/**
	 * Min–max по всей выборке стран (0…1), без кластеров — для осей E, регрессии и классификации.
	 *
	 * @param list<float> $values
	 * @return list<float>
	 */
	private static function normalize_global_minmax_1d( array $values ): array {
		$n = count( $values );
		if ( $n < 1 ) {
			return array();
		}
		$finite = array();
		foreach ( $values as $i => $v ) {
			if ( is_finite( (float) $v ) ) {
				$finite[ $i ] = (float) $v;
			}
		}
		if ( empty( $finite ) ) {
			return array_fill( 0, $n, NAN );
		}
		$min = min( $finite );
		$max = max( $finite );
		$out = array_fill( 0, $n, NAN );
		foreach ( $values as $i => $v ) {
			if ( ! is_finite( (float) $v ) ) {
				continue;
			}
			if ( abs( $max - $min ) < 1e-12 ) {
				$out[ $i ] = 0.5;
			} else {
				$out[ $i ] = ( (float) $v - $min ) / ( $max - $min );
			}
		}
		return $out;
	}

	/**
	 * Min–max нормализация ОТДЕЛЬНО внутри каждого кластера (labels → бакеты 0..k-1).
	 *
	 * @param list<float> $values
	 * @param list<int>   $labels
	 * @return list<float>
	 */
	private static function normalize_within_clusters_1d( array $values, array $labels, int $k ): array {
		$n       = count( $values );
		$buckets = array();
		for ( $c = 0; $c < $k; $c++ ) {
			$buckets[ $c ] = array();
		}
		for ( $i = 0; $i < $n; $i++ ) {
			$c                    = (int) ( $labels[ $i ] ?? 0 );
			$c                    = max( 0, min( $k - 1, $c ) );
			$buckets[ $c ][ $i ] = $values[ $i ];
		}
		$out = array_fill( 0, $n, NAN );
		foreach ( $buckets as $bucket ) {
			if ( empty( $bucket ) ) {
				continue;
			}
			$finite = array();
			foreach ( $bucket as $i => $v ) {
				if ( is_finite( (float) $v ) ) {
					$finite[ $i ] = (float) $v;
				}
			}
			if ( empty( $finite ) ) {
				continue;
			}
			$min = min( $finite );
			$max = max( $finite );
			foreach ( $bucket as $i => $v ) {
				if ( ! is_finite( (float) $v ) ) {
					$out[ $i ] = NAN;
					continue;
				}
				if ( abs( $max - $min ) < 1e-12 ) {
					$out[ $i ] = 0.5;
				} else {
					$out[ $i ] = ( (float) $v - $min ) / ( $max - $min );
				}
			}
		}
		return $out;
	}

	/**
	 * @param list<list<float>> $X
	 * @return list<list<float>>
	 */
	private static function standard_scale_rows( array $X ): array {
		$n = count( $X );
		$d = count( $X[0] );
		$mean = array_fill( 0, $d, 0.0 );
		for ( $i = 0; $i < $n; $i++ ) {
			for ( $j = 0; $j < $d; $j++ ) {
				$mean[ $j ] += $X[ $i ][ $j ];
			}
		}
		for ( $j = 0; $j < $d; $j++ ) {
			$mean[ $j ] /= $n;
		}
		$var = array_fill( 0, $d, 0.0 );
		for ( $i = 0; $i < $n; $i++ ) {
			for ( $j = 0; $j < $d; $j++ ) {
				$diff       = $X[ $i ][ $j ] - $mean[ $j ];
				$var[ $j ] += $diff * $diff;
			}
		}
		$std = array();
		for ( $j = 0; $j < $d; $j++ ) {
			$std[ $j ] = sqrt( $var[ $j ] / max( 1, $n - 1 ) );
			if ( $std[ $j ] < 1e-12 ) {
				$std[ $j ] = 1.0;
			}
		}
		$out = array();
		for ( $i = 0; $i < $n; $i++ ) {
			$row = array();
			for ( $j = 0; $j < $d; $j++ ) {
				$row[] = ( $X[ $i ][ $j ] - $mean[ $j ] ) / $std[ $j ];
			}
			$out[] = $row;
		}
		return $out;
	}

	/**
	 * K-means для матрицы признаков (используется автоподбором и превью).
	 *
	 * @param list<list<float>> $X
	 * @return list<int>
	 */
	public static function kmeans_labels_for_matrix( array $X, int $k, int $max_iter = 80 ): array {
		return self::kmeans( $X, $k, $max_iter );
	}

	/**
	 * K-means++: разнесённые начальные центроиды.
	 *
	 * @param list<list<float>> $X
	 * @return list<list<float>>
	 */
	private static function kmeans_init_centroids_pp( array $X, int $k ): array {
		$n = count( $X );
		$d = count( $X[0] ?? array() );
		$k = max( 1, min( $k, $n ) );
		if ( $n < 1 || $d < 1 ) {
			return array();
		}
		$centroids   = array();
		$first       = function_exists( 'wp_rand' ) ? wp_rand( 0, $n - 1 ) : mt_rand( 0, $n - 1 );
		$centroids[] = $X[ $first ];
		$dists_sq    = array_fill( 0, $n, INF );
		while ( count( $centroids ) < $k ) {
			$last = $centroids[ count( $centroids ) - 1 ];
			for ( $i = 0; $i < $n; $i++ ) {
				$nd = self::euclid_sq( $X[ $i ], $last );
				if ( $nd < $dists_sq[ $i ] ) {
					$dists_sq[ $i ] = $nd;
				}
			}
			$sum = array_sum( $dists_sq );
			if ( $sum < 1e-12 ) {
				$centroids[] = $X[ count( $centroids ) % $n ];
				continue;
			}
			$rand_max = function_exists( 'mt_getrandmax' ) ? (int) mt_getrandmax() : 1000000;
			$r        = ( function_exists( 'wp_rand' ) ? wp_rand( 0, $rand_max ) : mt_rand( 0, $rand_max ) ) / max( 1, $rand_max ) * $sum;
			$pick     = 0;
			$acc      = 0.0;
			for ( $i = 0; $i < $n; $i++ ) {
				$acc += $dists_sq[ $i ];
				if ( $acc >= $r ) {
					$pick = $i;
					break;
				}
			}
			$centroids[] = $X[ $pick ];
		}
		return $centroids;
	}

	/**
	 * K-means (k-means++), назначение по ближайшему центроиду — естественные кластеры по данным.
	 *
	 * @param list<list<float>> $X уже масштабированные строки
	 * @return list<int>
	 */
	private static function kmeans( array $X, int $k, int $max_iter ): array {
		$n = count( $X );
		$d = count( $X[0] ?? array() );
		if ( $n < 1 || $d < 1 ) {
			return array();
		}
		$k         = max( 1, min( $k, $n ) );
		$centroids = self::kmeans_init_centroids_pp( $X, $k );
		if ( count( $centroids ) < $k ) {
			return array_fill( 0, $n, 0 );
		}
		$labels = array_fill( 0, $n, 0 );
		for ( $it = 0; $it < $max_iter; $it++ ) {
			$changed = false;
			for ( $i = 0; $i < $n; $i++ ) {
				$best_c = 0;
				$best_d = INF;
				for ( $c = 0; $c < $k; $c++ ) {
					$dist = self::euclid_sq( $X[ $i ], $centroids[ $c ] );
					if ( $dist < $best_d ) {
						$best_d = $dist;
						$best_c = $c;
					}
				}
				if ( (int) $labels[ $i ] !== $best_c ) {
					$labels[ $i ] = $best_c;
					$changed      = true;
				}
			}
			$sums   = array();
			$counts = array_fill( 0, $k, 0 );
			for ( $c = 0; $c < $k; $c++ ) {
				$sums[ $c ] = array_fill( 0, $d, 0.0 );
			}
			for ( $i = 0; $i < $n; $i++ ) {
				$c = (int) $labels[ $i ];
				++$counts[ $c ];
				for ( $j = 0; $j < $d; $j++ ) {
					$sums[ $c ][ $j ] += $X[ $i ][ $j ];
				}
			}
			for ( $c = 0; $c < $k; $c++ ) {
				if ( $counts[ $c ] < 1 ) {
					$centroids[ $c ] = $X[ $c % $n ];
					continue;
				}
				for ( $j = 0; $j < $d; $j++ ) {
					$centroids[ $c ][ $j ] = $sums[ $c ][ $j ] / $counts[ $c ];
				}
			}
			if ( ! $changed ) {
				break;
			}
		}
		return $labels;
	}

	/**
	 * @param list<float> $a
	 * @param list<float> $b
	 */
	private static function euclid_sq( array $a, array $b ): float {
		$s = 0.0;
		$d = count( $a );
		for ( $j = 0; $j < $d; $j++ ) {
			$diff = $a[ $j ] - $b[ $j ];
			$s   += $diff * $diff;
		}
		return $s;
	}

	/**
	 * @param list<float> $vals
	 * @param list<float> $w
	 */
	private static function weighted_sum( array $vals, array $w ): float {
		foreach ( $vals as $v ) {
			if ( ! is_finite( $v ) ) {
				return NAN;
			}
		}
		$s = 0.0;
		for ( $i = 0; $i < count( $vals ); $i++ ) {
			$s += $vals[ $i ] * ( $w[ $i ] ?? 0 );
		}
		return $s;
	}

	/**
	 * Взвешенное среднее только по конечным слагаемым; веса перенормируются.
	 *
	 * @param list<float> $vals
	 * @param list<float> $w
	 */
	private static function weighted_sum_finite( array $vals, array $w ): float {
		$s  = 0.0;
		$sw = 0.0;
		$n  = count( $vals );
		for ( $i = 0; $i < $n; $i++ ) {
			$v = $vals[ $i ];
			if ( ! is_finite( $v ) ) {
				continue;
			}
			$wi = (float) ( $w[ $i ] ?? 0 );
			if ( $wi <= 0 ) {
				continue;
			}
			$s  += $v * $wi;
			$sw += $wi;
		}
		return $sw > 1e-15 ? $s / $sw : NAN;
	}

	/**
	 * Доля суммарного веса, приходящаяся на конечные слагаемые (0–1).
	 *
	 * @param list<float> $vals
	 * @param list<float> $w
	 */
	private static function finite_weight_fraction( array $vals, array $w ): float {
		$tw = 0.0;
		$fw = 0.0;
		$n  = count( $vals );
		for ( $i = 0; $i < $n; $i++ ) {
			$wi = (float) ( $w[ $i ] ?? 0 );
			if ( $wi <= 0 ) {
				continue;
			}
			$tw += $wi;
			if ( isset( $vals[ $i ] ) && is_finite( (float) $vals[ $i ] ) ) {
				$fw += $wi;
			}
		}
		return $tw > 1e-15 ? $fw / $tw : 0.0;
	}

	/**
	 * @param list<float> $vals
	 */
	private static function median_floats( array $vals ): float {
		$vals = array_values(
			array_filter(
				$vals,
				static function ( $v ) {
					return is_finite( (float) $v );
				}
			)
		);
		sort( $vals, SORT_NUMERIC );
		$n = count( $vals );
		if ( $n < 1 ) {
			return 0.0;
		}
		$m = intdiv( $n, 2 );
		if ( $n % 2 === 1 ) {
			return (float) $vals[ $m ];
		}
		return ( (float) $vals[ $m - 1 ] + (float) $vals[ $m ] ) / 2.0;
	}

	/**
	 * @param mixed $v
	 */
	private static function finite_or_nan( $v ): float {
		$f = (float) $v;
		return is_finite( $f ) ? $f : NAN;
	}

	/**
	 * @param mixed $v
	 */
	private static function finite_or_zero( $v ): float {
		$f = self::finite_or_nan( $v );
		return is_finite( $f ) ? $f : 0.0;
	}

	/**
	 * @param mixed $num
	 * @param mixed $den
	 */
	private static function safe_div( $num, $den ): float {
		$n = self::finite_or_nan( $num );
		$d = self::finite_or_nan( $den );
		if ( ! is_finite( $n ) || ! is_finite( $d ) || abs( $d ) < 1e-12 ) {
			return NAN;
		}
		return $n / $d;
	}

	/**
	 * @return array{
	 *   standard: array<string, array<string, array<int, float>>>,
	 *   sdg: array<string, array<int, array<string, float>>>,
	 *   worldua: array<string, array{big_city_dens:float, urban_cases_500k:float, avg_urban_dens_500k:float, urban_pop_500k_sum:float}>,
	 *   wide: array<string, array<int, array<string, float>>>
	 * }
	 */
	private static function ingest_uploaded_csvs(): array {
		$standard = array();
		$sdg      = array();
		$worldua  = array();
		$wide     = array();
		$sdg_map  = array();

		$files = WorldStat_Uploaded_Csv::list_files();
		usort(
			$files,
			static function ( $a, $b ) {
				$na = strtolower( (string) ( $a['name'] ?? '' ) );
				$nb = strtolower( (string) ( $b['name'] ?? '' ) );
				$pa = ( strpos( $na, 'sdg' ) !== false ) ? 0 : 1;
				$pb = ( strpos( $nb, 'sdg' ) !== false ) ? 0 : 1;
				if ( $pa !== $pb ) {
					return $pa <=> $pb;
				}
				return strcmp( $na, $nb );
			}
		);

		foreach ( $files as $file_row ) {
			$kind = (string) ( $file_row['dataset_kind'] ?? WorldStat_Uploaded_Csv::KIND_COUNTRY );
			if ( class_exists( 'WorldStat_Uploaded_Csv' ) && method_exists( 'WorldStat_Uploaded_Csv', 'is_calculation_source_kind' ) ) {
				if ( ! WorldStat_Uploaded_Csv::is_calculation_source_kind( $kind ) ) {
					continue;
				}
			} elseif ( $kind !== WorldStat_Uploaded_Csv::KIND_COUNTRY && $kind !== WorldStat_Uploaded_Csv::KIND_INDICATOR ) {
				continue;
			}
			$id = (int) ( $file_row['id'] ?? 0 );
			if ( $id < 1 ) {
				continue;
			}
			$body = WorldStat_Uploaded_Csv::get_body_by_id( $id );
			if ( $body === '' ) {
				continue;
			}
			$name = (string) ( $file_row['name'] ?? '' );
			$type = self::detect_csv_type( $body, $name );
			if ( $type === 'sdg' ) {
				$parsed = self::parse_sdg_csv( $body );
				foreach ( $parsed['rows'] as $iso3 => $by_year ) {
					if ( ! isset( $sdg[ $iso3 ] ) ) {
						$sdg[ $iso3 ] = array();
					}
					foreach ( $by_year as $y => $cols ) {
						$sdg[ $iso3 ][ (int) $y ] = array_merge( $sdg[ $iso3 ][ (int) $y ] ?? array(), $cols );
					}
				}
				$sdg_map = array_merge( $sdg_map, $parsed['name_to_iso3'] );
			} elseif ( $type === 'worldua' ) {
				$worldua = self::parse_worldua_csv( $body, $sdg_map );
			} elseif ( $type === 'wide_panel' ) {
				$parsed = self::parse_wide_panel_csv( $body );
				foreach ( $parsed as $iso3 => $by_year ) {
					if ( ! isset( $wide[ $iso3 ] ) ) {
						$wide[ $iso3 ] = array();
					}
					foreach ( $by_year as $y => $cols ) {
						$y = (int) $y;
						if ( ! isset( $wide[ $iso3 ][ $y ] ) ) {
							$wide[ $iso3 ][ $y ] = array();
						}
						foreach ( $cols as $sig => $v ) {
							$wide[ $iso3 ][ $y ][ $sig ] = $v;
						}
					}
				}
				self::wide_panel_seed_standard_metrics( $parsed, $standard );
			} else {
				$key = self::metric_key_for_standard_csv_row( $id, $name, $body );
				if ( $key === null ) {
					continue;
				}
				$parsed = self::parse_standard_metric_csv( $body );
				foreach ( $parsed as $iso3 => $by_year ) {
					if ( ! isset( $standard[ $key ] ) ) {
						$standard[ $key ] = array();
					}
					foreach ( $by_year as $y => $val ) {
						$standard[ $key ][ $iso3 ][ (int) $y ] = $val;
					}
				}
			}
		}

		return array(
			'standard' => $standard,
			'sdg'      => $sdg,
			'worldua'  => $worldua,
			'wide'     => $wide,
		);
	}

	private static function detect_csv_type( string $body, string $filename ): string {
		$low = strtolower( $filename );
		if ( strpos( $low, 'worldua' ) !== false || strpos( $low, 'urban_areas' ) !== false ) {
			return 'worldua';
		}
		$snippet = strtolower( substr( $body, 0, 12288 ) );
		if ( strpos( $snippet, 'sdg_index_score' ) !== false && strpos( $snippet, 'country_code' ) !== false ) {
			return 'sdg';
		}
		$first = '';
		foreach ( preg_split( "/\r\n|\n|\r/", $body ) as $line ) {
			$line = trim( (string) $line );
			if ( $line !== '' ) {
				$first = strtolower( $line );
				break;
			}
		}
		if ( strpos( $first, 'geography' ) !== false && strpos( $first, 'population estimate' ) !== false ) {
			return 'worldua';
		}
		$h = self::csv_header_normalized_keys( $body );
		$has_country = isset( $h['country_code'] ) || isset( $h['coutry_code'] ) || isset( $h['countrycode'] ) || isset( $h['iso3'] ) || isset( $h['cca3'] );
		$has_year = isset( $h['year'] ) || isset( $h['yr'] ) || isset( $h['timeperiod'] ) || isset( $h['time_period'] );
		if ( $has_country && $has_year ) {
			$has_long_value = isset( $h['value'] ) || isset( $h['obs_value'] ) || isset( $h['indicator_value'] ) || isset( $h['val'] );
			$skip           = array(
				'country_code'   => true,
				'year'           => true,
				'iso3'           => true,
				'iso'            => true,
				'cca3'           => true,
				'country'        => true,
				'country_name'   => true,
				'coutry_code'    => true,
				'countrycode'    => true,
				'time'           => true,
				'timeperiod'     => true,
				'time_period'    => true,
				'yr'             => true,
				'value'          => true,
				'obs_value'      => true,
				'indicator_value' => true,
				'val'            => true,
				'footnote'       => true,
				'footnotes'      => true,
				'source'         => true,
				'datasource'     => true,
				'lastupdatedate' => true,
			);
			$data_cols = 0;
			foreach ( array_keys( $h ) as $k ) {
				if ( ! isset( $skip[ $k ] ) ) {
					++$data_cols;
				}
			}
			if ( ! $has_long_value && $data_cols >= 1 ) {
				return 'wide_panel';
			}
			// Есть колонка value, но одновременно несколько числовых показателей — широкая панель (новые выгрузки).
			if ( $has_long_value && $data_cols >= 2 ) {
				return 'wide_panel';
			}
			// Сигнатура новых country-панелей без колонки value.
			if ( self::csv_header_suggests_wide_country_panel( $h ) ) {
				return 'wide_panel';
			}
		}
		return 'standard';
	}

	/**
	 * Заголовки в духе demographics.csv / environment.csv — всегда обрабатывать как wide_panel.
	 *
	 * @param array<string, true> $h
	 */
	private static function csv_header_suggests_wide_country_panel( array $h ): bool {
		$markers = array(
			'pop_total__psn',
			'pop_density__psn_per_km_sq',
			'pop_urban__psn',
			'land_area__km_sq',
			'forest_area__ptc',
			'railway_length__km',
			'road_length__km',
			'urban_pop_share__ptc',
			'largest_city_pop__psn',
			'energy_use_per_cap__kgoe',
			'gdp_per_energy__ppp_per_kgoe',
			'access_electricity__ptc',
			'internet_users__ptc',
			'military_exp__ptc_gdp',
			'tax_revenue__ptc_gdp',
		);
		foreach ( $markers as $m ) {
			if ( isset( $h[ $m ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Нормализованные ключи первой строки CSV (заголовок).
	 *
	 * @return array<string, true>
	 */
	private static function csv_header_normalized_keys( string $body ): array {
		foreach ( preg_split( "/\r\n|\n|\r/", $body ) as $line ) {
			$line = trim( (string) $line );
			if ( $line === '' ) {
				continue;
			}
			$row = str_getcsv( $line );
			$out  = array();
			foreach ( $row as $colname ) {
				$k = self::normalize_csv_header_key( (string) $colname );
				if ( $k !== '' ) {
					$out[ $k ] = true;
				}
			}
			return $out;
		}
		return array();
	}

	/**
	 * CSV «в ширину»: country_code, year и несколько числовых столбцов (как demographics.csv, governance_sdg.csv).
	 *
	 * @return array<string, array<int, array<string, float>>>
	 */
	private static function parse_wide_panel_csv( string $body ): array {
		$out         = array();
		$header_done = false;
		$h           = array();
		foreach ( preg_split( "/\r\n|\n|\r/", $body ) as $line ) {
			$line = trim( (string) $line );
			if ( $line === '' ) {
				continue;
			}
			$row = str_getcsv( $line );
			if ( ! $header_done ) {
				$h = array();
				foreach ( $row as $ci => $colname ) {
					$h[ $ci ] = self::normalize_csv_header_key( (string) $colname );
				}
				$header_done = true;
				continue;
			}
			$map = array();
			foreach ( $h as $i => $col ) {
				$map[ $col ] = $row[ $i ] ?? '';
			}
			$cc = strtoupper( trim( (string) ( $map['country_code'] ?? $map['coutry_code'] ?? $map['countrycode'] ?? $map['iso3'] ?? $map['cca3'] ?? '' ) ) );
			$cc = self::resolve_country_token_to_iso3( $cc );
			if ( strlen( $cc ) !== 3 ) {
				continue;
			}
			$year_raw = trim( (string) ( $map['year'] ?? $map['yr'] ?? '' ) );
			$year     = (int) $year_raw;
			if ( $year <= 0 && $year_raw !== '' && preg_match( '/^(\d{4})/', $year_raw, $ym ) ) {
				$year = (int) $ym[1];
			}
			if ( $year <= 0 ) {
				continue;
			}
			$skip = array(
				'country_code' => true,
				'year'         => true,
				'iso3'         => true,
				'iso'          => true,
				'cca3'         => true,
				'country'      => true,
				'country_name' => true,
			);
			$row_out = array();
			foreach ( $h as $col ) {
				if ( $col === '' || isset( $skip[ $col ] ) ) {
					continue;
				}
				$raw = str_replace( array( ' ', ',' ), array( '', '.' ), trim( (string) ( $map[ $col ] ?? '' ) ) );
				if ( $raw === '' || ! is_numeric( $raw ) ) {
					continue;
				}
				$val = (float) $raw;
				$sig = self::wide_csv_column_to_signal( $col );
				if ( $sig === '' ) {
					continue;
				}
				if ( self::wide_csv_value_to_unit_interval( $col, $val ) ) {
					$val = $val / 100.0;
				}
				$row_out[ $sig ] = $val;
			}
			if ( ! empty( $row_out ) ) {
				if ( ! isset( $out[ $cc ] ) ) {
					$out[ $cc ] = array();
				}
				$out[ $cc ][ $year ] = isset( $out[ $cc ][ $year ] ) ? array_merge( $out[ $cc ][ $year ], $row_out ) : $row_out;
			}
		}
		return $out;
	}

	/**
	 * Имя столбца CSV → ключ признака в строке макро (как в build_feature_rows).
	 */
	private static function wide_csv_column_to_signal( string $col ): string {
		static $map = null;
		if ( null === $map ) {
			$map = array(
				'pop_dens_km2'              => 'pop_dens',
				'urban_pct'                 => 'urban_share',
				'urban_land_per_urban'      => 'urban_land_per_urban_pop',
				'big_city_ratio'            => 'big_city_ratio',
				'pct_urban_500k'            => 'pct_urban_500k',
				'urban_growth_pct'          => 'urban_growth_pct',
				'road_dens'                 => 'road_dens',
				'rail_dens'                 => 'rail_dens',
				'transport_dens'            => 'transport_dens',
				'elec_access_pct'           => 'elec_access',
				'broadband_per100'          => 'broadband_per100',
				'internet_pct'              => 'internet_pct',
				'mobile_per100'             => 'mobile_per100',
				'lpi_score'                 => 'lpi_score',
				'lpi_infra'                 => 'lpi_infra',
				'air_passengers'            => 'air_passengers',
				'container_teu'             => 'container_teu',
				'forest_pct'                => 'forest_share',
				'protected_pct'             => 'protected_pct',
				'pm25_ug_m3'                => 'pm25_ug_m3',
				'ghg_per_capita'            => 'ghg_per_capita',
				'renew_energy_pct'          => 'renew_energy_pct',
				'clean_cooking_pct'         => 'clean_cooking_pct',
				'water_prod_usd_m3'         => 'water_prod_usd_m3',
				'arable_pct'                => 'arable_pct',
				'life_exp_years'            => 'life_exp_years',
				'mort_u5_per1000'           => 'mort_u5_per1000',
				'mort_infant_per1000'       => 'mort_infant_per1000',
				'mort_neo_per1000'          => 'mort_neo_per1000',
				'sanitation_safe_pct'       => 'sanitation_safe_pct',
				'water_safe_pct'            => 'water_safe_pct',
				'uhc_index'                 => 'uhc_index',
				'sdg_index'                 => 'sdg_index_score',
				'sdg9_score'                => 'industry_innovation_infrastructure',
				'sdg11_score'               => 'sustainable_cities',
				'sdg16_score'               => 'peace_justice',
				'cpi_business'              => 'cpi_business',
				'cpi_corruption'            => 'cpi_corruption',
				'homicide_per100k'          => 'homicide_per100k',
				'rnd_gdp_pct'               => 'rnd_gdp_pct',
				'hi_tech_exp_pct'           => 'hi_tech_exp_pct',
				'energy_use_kg_oil_cap'     => 'energy_use_kg_oil_cap',
				'energy_int_mj_gdp_ppp'     => 'energy_int_mj_gdp_ppp',
				'gdp_per_energy_ppp'        => 'gdp_per_energy_ppp',
				'renew_elec_pct'            => 'renew_elec_pct',
			);
		}
		return $map[ $col ] ?? $col;
	}

	/**
	 * Значения в процентах 0–100 → в доли 0–1 для согласованности с urban_share / forest_share.
	 *
	 * @param float $val сырое число из CSV
	 */
	private static function wide_csv_value_to_unit_interval( string $col, float $val ): bool {
		$pct_cols = array(
			'urban_pct'            => true,
			'forest_pct'           => true,
			'sanitation_safe_pct'  => true,
			'water_safe_pct'       => true,
			'protected_pct'        => true,
			'renew_energy_pct'     => true,
			'clean_cooking_pct'    => true,
			'arable_pct'           => true,
			'elec_access_pct'      => true,
			'internet_pct'         => true,
			'renew_elec_pct'       => true,
		);
		if ( ! isset( $pct_cols[ $col ] ) ) {
			return false;
		}
		if ( $val > 1.0001 || $val < -0.0001 ) {
			return true;
		}
		return false;
	}

	/**
	 * @param array<int, array<string, float>> $by_year
	 * @return array<string, float>
	 */
	private static function pick_wide_year_row( array $by_year, int $target_year ): array {
		if ( empty( $by_year ) ) {
			return array();
		}
		$best_y = null;
		$best    = array();
		foreach ( $by_year as $y => $cols ) {
			$y = (int) $y;
			if ( $y > $target_year ) {
				continue;
			}
			if ( $best_y === null || $y > $best_y ) {
				$best_y = $y;
				$best   = is_array( $cols ) ? $cols : array();
			}
		}
		if ( $best_y !== null ) {
			return $best;
		}
		foreach ( $by_year as $y => $cols ) {
			$y = (int) $y;
			if ( $y <= 0 ) {
				continue;
			}
			if ( $best_y === null || $y > $best_y ) {
				$best_y = $y;
				$best   = is_array( $cols ) ? $cols : array();
			}
		}
		return $best;
	}

	/**
	 * Подмешивает признаки из широких CSV в строку страны (после базовой сборки).
	 *
	 * @param array<string, float> $row
	 * @param array<int, array<string, float>> $by_year
	 */
	private static function apply_wide_panel_overrides( array &$row, array $by_year, int $target_year ): void {
		$w = self::pick_wide_year_row( $by_year, $target_year );
		foreach ( $w as $sig => $val ) {
			if ( ! is_finite( $val ) ) {
				continue;
			}
			$row[ $sig ] = $val;
		}
	}

	/**
	 * Подставляет значение стандартного ряда из wide, если для (iso3, год) ещё нет long-данных.
	 *
	 * @param array<string, array<string, array<int, float>>> $standard
	 */
	private static function wide_panel_seed_metric_if_absent( array &$standard, string $metric_key, string $iso3, int $year, float $val ): void {
		if ( ! is_finite( $val ) ) {
			return;
		}
		if ( $val < 0 ) {
			return;
		}
		if ( $val <= 0 && 'urban_share_percent' !== $metric_key ) {
			return;
		}
		if ( isset( $standard[ $metric_key ][ $iso3 ][ $year ] ) ) {
			return;
		}
		if ( ! isset( $standard[ $metric_key ] ) ) {
			$standard[ $metric_key ] = array();
		}
		if ( ! isset( $standard[ $metric_key ][ $iso3 ] ) ) {
			$standard[ $metric_key ][ $iso3 ] = array();
		}
		$standard[ $metric_key ][ $iso3 ][ $year ] = $val;
	}

	/**
	 * Подставляет в стандартные ряды значения из широкого CSV (плотность, урбанизация, лес, население, площадь по типовым столбцам).
	 *
	 * @param array<string, array<int, array<string, float>>>  $parsed
	 * @param array<string, array<string, array<int, float>>> $standard
	 */
	private static function wide_panel_seed_standard_metrics( array $parsed, array &$standard ): void {
		$pop_cols = array(
			'population_total',
			'total_population',
			'pop_total',
			'population',
			'sp_pop_totl',
			'sppoptotl',
			'pop',
			'tot_pop',
			'pop_tot',
			'pop_total__psn',
		);
		$area_cols = array(
			'surface_area_sqkm',
			'land_area_sqkm',
			'land_area',
			'country_area',
			'country_area_sqkm',
			'area_sq_km',
			'areasqkm',
			'surface_area',
			'geographic_area',
			'ag_land_sqkm',
			'land_sq_km',
			'land_area__km_sq',
		);
		foreach ( $parsed as $iso3 => $by_year ) {
			foreach ( $by_year as $y => $cols ) {
				$y = (int) $y;
				if ( ! is_array( $cols ) ) {
					continue;
				}
				foreach ( $pop_cols as $ck ) {
					if ( isset( $cols[ $ck ] ) && is_finite( (float) $cols[ $ck ] ) ) {
						self::wide_panel_seed_metric_if_absent( $standard, 'population_total', $iso3, $y, (float) $cols[ $ck ] );
						break;
					}
				}
				foreach ( $area_cols as $ck ) {
					if ( isset( $cols[ $ck ] ) && is_finite( (float) $cols[ $ck ] ) ) {
						self::wide_panel_seed_metric_if_absent( $standard, 'surface_area_sqkm', $iso3, $y, (float) $cols[ $ck ] );
						break;
					}
				}
				if ( isset( $cols['pop_dens'] ) && is_finite( (float) $cols['pop_dens'] ) ) {
					self::wide_panel_seed_metric_if_absent( $standard, 'population_density_per_km2', $iso3, $y, (float) $cols['pop_dens'] );
				} elseif ( isset( $cols['population_density_per_km2'] ) && is_finite( (float) $cols['population_density_per_km2'] ) ) {
					self::wide_panel_seed_metric_if_absent( $standard, 'population_density_per_km2', $iso3, $y, (float) $cols['population_density_per_km2'] );
				} elseif ( isset( $cols['pop_density__psn_per_km_sq'] ) && is_finite( (float) $cols['pop_density__psn_per_km_sq'] ) ) {
					self::wide_panel_seed_metric_if_absent( $standard, 'population_density_per_km2', $iso3, $y, (float) $cols['pop_density__psn_per_km_sq'] );
				}
				if ( isset( $cols['urban_share'] ) && is_finite( (float) $cols['urban_share'] ) ) {
					self::wide_panel_seed_metric_if_absent( $standard, 'urban_share_percent', $iso3, $y, (float) $cols['urban_share'] * 100.0 );
				} elseif ( isset( $cols['urban_pop_share__ptc'] ) && is_finite( (float) $cols['urban_pop_share__ptc'] ) ) {
					self::wide_panel_seed_metric_if_absent( $standard, 'urban_share_percent', $iso3, $y, (float) $cols['urban_pop_share__ptc'] );
				} elseif (
					isset( $cols['pop_urban__psn'], $cols['pop_total__psn'] )
					&& is_finite( (float) $cols['pop_total__psn'] )
					&& is_finite( (float) $cols['pop_urban__psn'] )
				) {
					$pt = (float) $cols['pop_total__psn'];
					$pu = (float) $cols['pop_urban__psn'];
					if ( $pt > 0.0 && $pu >= 0.0 ) {
						self::wide_panel_seed_metric_if_absent( $standard, 'urban_share_percent', $iso3, $y, 100.0 * $pu / $pt );
					}
				}
				if ( isset( $cols['forest_share'] ) && is_finite( (float) $cols['forest_share'] ) ) {
					self::wide_panel_seed_metric_if_absent( $standard, 'forest_percentage', $iso3, $y, (float) $cols['forest_share'] * 100.0 );
				} elseif ( isset( $cols['forest_area__ptc'] ) && is_finite( (float) $cols['forest_area__ptc'] ) ) {
					self::wide_panel_seed_metric_if_absent( $standard, 'forest_percentage', $iso3, $y, (float) $cols['forest_area__ptc'] );
				}
				if ( isset( $cols['largest_city_pop__psn'] ) && is_finite( (float) $cols['largest_city_pop__psn'] ) ) {
					self::wide_panel_seed_metric_if_absent( $standard, 'largest_city_population', $iso3, $y, (float) $cols['largest_city_pop__psn'] );
				}
				if ( isset( $cols['railway_length__km'] ) && is_finite( (float) $cols['railway_length__km'] ) ) {
					self::wide_panel_seed_metric_if_absent( $standard, 'railway_length', $iso3, $y, (float) $cols['railway_length__km'] );
				}
				if ( isset( $cols['road_length__km'] ) && is_finite( (float) $cols['road_length__km'] ) ) {
					self::wide_panel_seed_metric_if_absent( $standard, 'road_length', $iso3, $y, (float) $cols['road_length__km'] );
				}
			}
		}
	}

	/**
	 * Заголовки вроде «Country Code», «Indicator value» → country_code, indicator_value.
	 */
	public static function normalize_csv_header_key( string $col ): string {
		$col = trim( $col );
		$col = preg_replace( '/^\xEF\xBB\xBF/', '', $col );
		$c   = strtolower( str_replace( array( "\t", ' ', '-' ), '_', $col ) );
		return (string) preg_replace( '/[^a-z0-9_]/', '', $c );
	}

	/**
	 * Явная привязка id CSV в БД к ключу ряда; иначе эвристика по заголовку/столбцам; затем по имени файла.
	 */
	private static function metric_key_for_standard_csv_row( int $file_id, string $filename, string $body = '' ): ?string {
		if ( $file_id > 0 && class_exists( 'WSErgo_Settings' ) ) {
			$bindings = WSErgo_Settings::get_macro_csv_bindings();
			foreach ( $bindings as $metric_key => $bound_id ) {
				if ( (int) $bound_id === $file_id ) {
					$allowed = array_flip( self::STANDARD_METRIC_KEYS );
					if ( isset( $allowed[ $metric_key ] ) ) {
						return $metric_key;
					}
				}
			}
		}
		if ( $body !== '' ) {
			$from_body = self::infer_standard_metric_key_from_csv_body( $body );
			if ( $from_body !== null ) {
				return $from_body;
			}
		}
		return self::metric_key_from_filename( $filename );
	}

	/**
	 * Long-CSV: определить единственный базовый ряд по коду/названию индикатора или по одному «смысловому» столбцу в заголовке.
	 */
	private static function infer_standard_metric_key_from_csv_body( string $body ): ?string {
		$code = self::csv_sample_dominant_column_value(
			$body,
			array(
				'indicator_code',
				'series_code',
				'series_id',
				'indicator_id',
				'wb_series_code',
				'itemcode',
				'variable_code',
				'indicatorcode',
			)
		);
		if ( $code !== null && $code !== '' ) {
			$mk = self::world_bank_style_series_code_to_metric_key( strtoupper( trim( $code ) ) );
			if ( $mk !== null ) {
				return $mk;
			}
		}
		$name = self::csv_sample_dominant_column_value(
			$body,
			array(
				'indicator_name',
				'series_name',
				'variable_name',
				'indicator',
			)
		);
		if ( $name !== null && $name !== '' ) {
			$mk = self::indicator_label_text_to_metric_key( $name );
			if ( $mk !== null ) {
				return $mk;
			}
		}
		return self::infer_standard_metric_key_from_header_columns_only( $body );
	}

	/**
	 * Если в заголовке ровно один распознаваемый столбец показателя (при long-колонке value) — привязать к нему.
	 */
	private static function infer_standard_metric_key_from_header_columns_only( string $body ): ?string {
		$h = self::csv_header_normalized_keys( $body );
		if ( empty( $h ) ) {
			return null;
		}
		$has_value = isset( $h['value'] ) || isset( $h['obs_value'] ) || isset( $h['indicator_value'] ) || isset( $h['val'] );
		if ( ! $has_value ) {
			return null;
		}
		$map   = self::standard_metric_header_synonym_to_key();
		$found = array();
		foreach ( array_keys( $h ) as $col ) {
			if ( isset( $map[ $col ] ) ) {
				$found[ $map[ $col ] ] = true;
			}
		}
		if ( count( $found ) === 1 ) {
			foreach ( array_keys( $found ) as $mk ) {
				return $mk;
			}
		}
		return null;
	}

	/**
	 * @return array<string, string> нормализованное имя столбца → ключ STANDARD_METRIC_KEYS
	 */
	private static function standard_metric_header_synonym_to_key(): array {
		static $out = null;
		if ( is_array( $out ) ) {
			return $out;
		}
		$out = array();
		foreach ( self::STANDARD_METRIC_KEYS as $k ) {
			$out[ $k ] = $k;
		}
		$groups = array(
			'population_total'               => array(
				'pop_total__psn',
				'pop_tot',
				'total_pop',
				'population',
				'sp_pop_totl',
				'sppoptotl',
			),
			'surface_area_sqkm'              => array(
				'land_area__km_sq',
				'land_area_sqkm',
				'land_area',
				'surface_area',
				'ag_land_sqkm',
				'country_area',
				'areasqkm',
			),
			'population_density_per_km2'   => array(
				'pop_density__psn_per_km_sq',
				'pop_dens',
				'pop_density',
				'en_pop_dnst',
			),
			'urban_share_percent'            => array(
				'urban_pop_share__ptc',
				'urban_share',
				'sp_urb_totl_in_zs',
				'urbanization',
			),
			'urban_land_area_sqkm'           => array(
				'urban_land',
				'sp_urb_land',
			),
			'forest_percentage'              => array(
				'forest_area__ptc',
				'forest_share',
				'ag_lnd_frst_zs',
			),
			'largest_city_population'        => array(
				'largest_city_pop__psn',
				'en_urb_lcty',
				'largest_city_pop',
			),
			'railway_length'                 => array(
				'railway_length__km',
				'is_rrs_totl_km',
				'rail_km',
			),
			'road_length'                    => array(
				'road_length__km',
				'is_rod_totl_km',
				'road_km',
			),
		);
		foreach ( $groups as $metric => $synonyms ) {
			foreach ( $synonyms as $s ) {
				$out[ $s ] = $metric;
			}
		}
		return $out;
	}

	/**
	 * Частые коды рядов WDI / совместимых выгрузок → ключ макро-ряда.
	 */
	private static function world_bank_style_series_code_to_metric_key( string $code ): ?string {
		static $map = null;
		if ( null === $map ) {
			$map = array(
				'SP.POP.TOTL'       => 'population_total',
				'AG.LND.TOTL.K2'    => 'surface_area_sqkm',
				'EN.POP.DNST'       => 'population_density_per_km2',
				'SP.URB.TOTL.IN.ZS' => 'urban_share_percent',
				'AG.LND.FRST.ZS'    => 'forest_percentage',
				'EN.URB.LCTY'       => 'largest_city_population',
				'IS.RRS.TOTL.KM'    => 'railway_length',
				'IS.ROD.TOTL.KM'    => 'road_length',
				'SP.URB.LAND'       => 'urban_land_area_sqkm',
			);
		}
		return $map[ $code ] ?? null;
	}

	/**
	 * Текстовое название индикатора (англ.) → ключ ряда.
	 */
	private static function indicator_label_text_to_metric_key( string $label ): ?string {
		$t = strtolower( $label );
		if ( strpos( $t, 'largest' ) !== false && strpos( $t, 'cit' ) !== false ) {
			return 'largest_city_population';
		}
		if ( strpos( $t, 'population' ) !== false && strpos( $t, 'dens' ) !== false ) {
			return 'population_density_per_km2';
		}
		if ( strpos( $t, 'population' ) !== false && strpos( $t, 'total' ) !== false ) {
			return 'population_total';
		}
		if ( strpos( $t, 'land area' ) !== false || strpos( $t, 'surface area' ) !== false || ( strpos( $t, 'area' ) !== false && strpos( $t, 'sq' ) !== false ) ) {
			return 'surface_area_sqkm';
		}
		if ( strpos( $t, 'urban' ) !== false && strpos( $t, 'pop' ) !== false && strpos( $t, '%' ) !== false ) {
			return 'urban_share_percent';
		}
		if ( strpos( $t, 'forest' ) !== false && ( strpos( $t, '%' ) !== false || strpos( $t, 'cover' ) !== false ) ) {
			return 'forest_percentage';
		}
		if ( strpos( $t, 'urban' ) !== false && strpos( $t, 'land' ) !== false ) {
			return 'urban_land_area_sqkm';
		}
		if ( strpos( $t, 'rail' ) !== false ) {
			return 'railway_length';
		}
		if ( strpos( $t, 'road' ) !== false && strpos( $t, 'length' ) !== false ) {
			return 'road_length';
		}
		return null;
	}

	/**
	 * До max_lines строк данных: уникальные значения в первом найденном столбце из $candidates; при ровно одном — вернуть его.
	 *
	 * @param list<string> $candidates нормализованные имена столбцов
	 */
	private static function csv_sample_dominant_column_value( string $body, array $candidates, int $max_lines = 120 ): ?string {
		$lines = preg_split( "/\r\n|\n|\r/", $body );
		$h       = array();
		$started = false;
		$idx     = -1;
		$seen    = array();
		$n       = 0;
		foreach ( $lines as $line ) {
			$line = trim( (string) $line );
			if ( $line === '' ) {
				continue;
			}
			$row = str_getcsv( $line );
			if ( ! $started ) {
				foreach ( $row as $ci => $colname ) {
					$h[ $ci ] = self::normalize_csv_header_key( (string) $colname );
				}
				foreach ( $h as $i => $hn ) {
					if ( in_array( $hn, $candidates, true ) ) {
						$idx = (int) $i;
						break;
					}
				}
				if ( $idx < 0 ) {
					return null;
				}
				$started = true;
				continue;
			}
			$cell = trim( (string) ( $row[ $idx ] ?? '' ) );
			if ( $cell === '' ) {
				continue;
			}
			$seen[ $cell ] = true;
			if ( count( $seen ) > 1 ) {
				return null;
			}
			++$n;
			if ( $n >= $max_lines ) {
				break;
			}
		}
		if ( count( $seen ) !== 1 ) {
			return null;
		}
		foreach ( array_keys( $seen ) as $one ) {
			return $one;
		}
		return null;
	}

	private static function metric_key_from_filename( string $filename ): ?string {
		$stem = strtolower( pathinfo( $filename, PATHINFO_FILENAME ) );
		$stem = preg_replace( '/[^a-z0-9_]+/', '_', $stem );
		$stem = (string) preg_replace( '/_+/', '_', trim( $stem, '_' ) );

		$aliases = array(
			'population'                 => 'population_total',
			'pop_total'                  => 'population_total',
			'total_population'           => 'population_total',
			'surface_area'               => 'surface_area_sqkm',
			'area'                       => 'surface_area_sqkm',
			'land_area'                  => 'surface_area_sqkm',
			'territory'                  => 'surface_area_sqkm',
			'pop_density'                => 'population_density_per_km2',
			'density'                    => 'population_density_per_km2',
			'urbanization'               => 'urban_share_percent',
			'urban_pct'                  => 'urban_share_percent',
			'urban_population_share'     => 'urban_share_percent',
			'urban_land'                 => 'urban_land_area_sqkm',
			'forest'                     => 'forest_percentage',
			'forest_cover'               => 'forest_percentage',
			'largest_city'               => 'largest_city_population',
			'city_largest_pop'           => 'largest_city_population',
			'rail'                       => 'railway_length',
			'railways'                   => 'railway_length',
			'roads'                      => 'road_length',
		);
		if ( isset( $aliases[ $stem ] ) ) {
			return $aliases[ $stem ];
		}
		foreach ( self::STANDARD_METRIC_KEYS as $key ) {
			if ( $stem === $key || strpos( $stem, $key ) !== false ) {
				return $key;
			}
		}
		if ( strpos( $stem, 'population' ) !== false && strpos( $stem, 'total' ) !== false ) {
			return 'population_total';
		}
		if ( strpos( $stem, 'pop' ) !== false && strpos( $stem, 'total' ) !== false && strpos( $stem, 'urban' ) === false ) {
			return 'population_total';
		}
		if ( strpos( $stem, 'pop' ) !== false && strpos( $stem, 'dens' ) !== false ) {
			return 'population_density_per_km2';
		}
		if ( strpos( $stem, 'urban' ) !== false && strpos( $stem, 'share' ) !== false ) {
			return 'urban_share_percent';
		}
		if ( strpos( $stem, 'urban' ) !== false && strpos( $stem, 'land' ) !== false ) {
			return 'urban_land_area_sqkm';
		}
		if ( strpos( $stem, 'road' ) !== false && strpos( $stem, 'length' ) !== false ) {
			return 'road_length';
		}
		if ( strpos( $stem, 'rail' ) !== false && strpos( $stem, 'length' ) !== false ) {
			return 'railway_length';
		}
		if ( strpos( $stem, 'road' ) !== false && strpos( $stem, 'km' ) !== false ) {
			return 'road_length';
		}
		if ( strpos( $stem, 'rail' ) !== false && strpos( $stem, 'km' ) !== false ) {
			return 'railway_length';
		}
		return null;
	}

	/**
	 * @return array<string, array<int, float>>
	 */
	private static function parse_standard_metric_csv( string $body ): array {
		$out         = array();
		$header_done = false;
		$h           = array();
		foreach ( preg_split( "/\r\n|\n|\r/", $body ) as $line ) {
			$line = trim( (string) $line );
			if ( $line === '' ) {
				continue;
			}
			$row = str_getcsv( $line );
			if ( ! $header_done ) {
				$h = array();
				foreach ( $row as $ci => $colname ) {
					$h[ $ci ] = self::normalize_csv_header_key( (string) $colname );
				}
				$header_done = true;
				continue;
			}
			$map = array();
			foreach ( $h as $i => $col ) {
				$map[ $col ] = $row[ $i ] ?? '';
			}
			$cc = strtoupper( trim( (string) ( $map['country_code'] ?? $map['coutry_code'] ?? $map['countrycode'] ?? $map['iso3'] ?? $map['iso'] ?? '' ) ) );
			$cc = self::resolve_country_token_to_iso3( $cc );
			if ( strlen( $cc ) !== 3 ) {
				continue;
			}
			$year_raw = trim( (string) ( $map['year'] ?? $map['yr'] ?? $map['time'] ?? '' ) );
			$year     = (int) $year_raw;
			if ( $year <= 0 && $year_raw !== '' && preg_match( '/^(\d{4})/', $year_raw, $ym ) ) {
				$year = (int) $ym[1];
			}
			if ( $year <= 0 ) {
				continue;
			}
			$val_cell = $map['value'] ?? $map['obs_value'] ?? $map['obs_value_wb'] ?? $map['val'] ?? $map['indicator_value'] ?? '';
			$raw      = str_replace( array( ' ', ',' ), array( '', '.' ), (string) $val_cell );
			if ( ! is_numeric( $raw ) ) {
				continue;
			}
			$val             = (float) $raw;
			$out[ $cc ][ $year ] = $val;
		}
		return $out;
	}

	/**
	 * @return array{rows: array<string, array<int, array<string, float>>>, name_to_iso3: array<string, string>}
	 */
	private static function parse_sdg_csv( string $body ): array {
		$rows        = array();
		$name_map    = array();
		$header_done = false;
		$h           = array();
		$need        = array( 'sdg_index_score', 'sustainable_cities', 'industry_innovation_infrastructure', 'peace_justice' );
		foreach ( preg_split( "/\r\n|\n|\r/", $body ) as $line ) {
			$line = trim( (string) $line );
			if ( $line === '' ) {
				continue;
			}
			$row = str_getcsv( $line );
			if ( ! $header_done ) {
				$h = array();
				foreach ( $row as $ci => $colname ) {
					$h[ $ci ] = self::normalize_csv_header_key( (string) $colname );
				}
				$header_done = true;
				continue;
			}
			$map = array();
			foreach ( $h as $i => $col ) {
				$map[ $col ] = $row[ $i ] ?? '';
			}
			$cc = strtoupper( trim( (string) ( $map['country_code'] ?? $map['coutry_code'] ?? $map['countrycode'] ?? $map['cca3'] ?? $map['iso3'] ?? '' ) ) );
			$cc = self::resolve_country_token_to_iso3( $cc );
			if ( strlen( $cc ) !== 3 ) {
				continue;
			}
			$year_raw = trim( (string) ( $map['year'] ?? $map['timeperiod'] ?? $map['time_period'] ?? '' ) );
			$year     = (int) $year_raw;
			if ( $year <= 0 && $year_raw !== '' && preg_match( '/(\d{4})/', $year_raw, $ym ) ) {
				$year = (int) $ym[1];
			}
			if ( $year <= 0 ) {
				continue;
			}
			$cn = strtolower( trim( (string) ( $map['country'] ?? $map['country_name'] ?? '' ) ) );
			if ( $cn !== '' ) {
				$nk               = self::normalize_name_key( $cn );
				$name_map[ $nk ] = $cc;
			}
			$cols = array();
			foreach ( $need as $col ) {
				if ( ! isset( $map[ $col ] ) ) {
					continue;
				}
				$raw = str_replace( ',', '.', trim( (string) $map[ $col ] ) );
				if ( is_numeric( $raw ) ) {
					$cols[ $col ] = (float) $raw;
				}
			}
			if ( empty( $cols ) ) {
				continue;
			}
			$rows[ $cc ][ $year ] = $cols;
		}
		return array(
			'rows'         => $rows,
			'name_to_iso3' => $name_map,
		);
	}

	private static function normalize_name_key( string $name ): string {
		return (string) preg_replace( '/[^a-z0-9]+/i', '', strtolower( $name ) );
	}

	/**
	 * Код страны в CSV: ISO3 как есть, ISO2 — через запись wsp_country (wsp_iso_alpha3).
	 */
	private static function resolve_country_token_to_iso3( string $token ): string {
		$token = strtoupper( trim( $token ) );
		if ( strlen( $token ) === 3 && ctype_alpha( $token ) ) {
			return $token;
		}
		if ( strlen( $token ) === 2 && ctype_alpha( $token ) && class_exists( 'WorldStat_Country_CPT' ) ) {
			$post = WorldStat_Country_CPT::get_by_code( $token );
			if ( $post ) {
				$iso3 = strtoupper( (string) get_post_meta( $post->ID, 'wsp_iso_alpha3', true ) );
				if ( strlen( $iso3 ) === 3 && ctype_alpha( $iso3 ) ) {
					return $iso3;
				}
			}
		}
		return '';
	}

	/**
	 * @param array<string, string> $sdg_name_map normalized country name => ISO3
	 * @return array<string, array{big_city_dens:float, urban_cases_500k:float, avg_urban_dens_500k:float, urban_pop_500k_sum:float}>
	 */
	private static function parse_worldua_csv( string $body, array $sdg_name_map ): array {
		$aliases = array(
			'unitedstates'   => 'USA',
			'russia'         => 'RUS',
			'southkorea'     => 'KOR',
			'northkorea'     => 'PRK',
			'iran'           => 'IRN',
			'turkey'         => 'TUR',
			'congodemrep'    => 'COD',
			'congorep'       => 'COG',
			'ivorycoast'     => 'CIV',
			'czechrepublic'  => 'CZE',
			'uae'            => 'ARE',
		);

		$rows_by_cc = array();
		$header_done = false;
		$idx_geo = $idx_pop = $idx_dens = -1;
		foreach ( preg_split( "/\r\n|\n|\r/", $body ) as $line ) {
			$line = trim( (string) $line );
			if ( $line === '' ) {
				continue;
			}
			$row = str_getcsv( $line );
			if ( ! $header_done ) {
				foreach ( $row as $i => $col ) {
					$c = strtolower( trim( (string) $col ) );
					if ( $c === 'geography' ) {
						$idx_geo = $i;
					}
					if ( $c === 'population estimate' ) {
						$idx_pop = $i;
					}
					if ( $c === 'per square kilometer' ) {
						$idx_dens = $i;
					}
				}
				$header_done = true;
				continue;
			}
			if ( $idx_geo < 0 || $idx_pop < 0 || $idx_dens < 0 ) {
				break;
			}
			$geo = strtolower( trim( (string) ( $row[ $idx_geo ] ?? '' ) ) );
			if ( $geo === '' ) {
				continue;
			}
			$cc = self::map_worldua_geography_to_iso3( $geo, $sdg_name_map, $aliases );
			if ( $cc === '' ) {
				continue;
			}
			$pop  = (float) str_replace( ',', '', (string) ( $row[ $idx_pop ] ?? '' ) );
			$dens = (float) str_replace( ',', '.', (string) ( $row[ $idx_dens ] ?? '' ) );
			if ( ! isset( $rows_by_cc[ $cc ] ) ) {
				$rows_by_cc[ $cc ] = array();
			}
			$rows_by_cc[ $cc ][] = array( 'pop' => $pop, 'dens' => $dens );
		}

		$out = array();
		foreach ( $rows_by_cc as $cc => $cities ) {
			usort(
				$cities,
				static function ( $a, $b ) {
					return ( $b['pop'] <=> $a['pop'] );
				}
			);
			$big_city_dens = isset( $cities[0] ) ? (float) $cities[0]['dens'] : 0.0;
			$urban_cases   = 0;
			$dens_sum      = 0.0;
			$pop_500k      = 0.0;
			foreach ( $cities as $c ) {
				if ( $c['pop'] >= 500000 ) {
					++$urban_cases;
					$dens_sum += $c['dens'];
					$pop_500k += $c['pop'];
				}
			}
			$avg_500k = $urban_cases > 0 ? $dens_sum / $urban_cases : 0.0;
			$out[ $cc ] = array(
				'big_city_dens'         => $big_city_dens,
				'urban_cases_500k'      => (float) $urban_cases,
				'avg_urban_dens_500k'   => $avg_500k,
				'urban_pop_500k_sum'    => $pop_500k,
			);
		}
		return $out;
	}

	/**
	 * @param array<string, string> $sdg_name_map
	 * @param array<string, string> $aliases
	 */
	private static function map_worldua_geography_to_iso3( string $geo, array $sdg_name_map, array $aliases ): string {
		$nk = self::normalize_name_key( $geo );
		if ( isset( $sdg_name_map[ $nk ] ) ) {
			return $sdg_name_map[ $nk ];
		}
		if ( isset( $aliases[ $nk ] ) ) {
			return $aliases[ $nk ];
		}
		return '';
	}

	/**
	 * @param array $ingest ingest_uploaded_csvs()
	 * @return array{rows: array<string, array<string, float>>, diagnostics: array<string, array<string, mixed>>}
	 */
	private static function build_feature_rows( array $ingest, int $target_year ): array {
		$standard = $ingest['standard'];
		$sdg      = $ingest['sdg'];
		$worldua  = $ingest['worldua'];
		$wide     = isset( $ingest['wide'] ) && is_array( $ingest['wide'] ) ? $ingest['wide'] : array();

		$iso3s = array();
		foreach ( array_keys( $sdg ) as $cc ) {
			$iso3s[ $cc ] = true;
		}
		foreach ( array_keys( $worldua ) as $cc ) {
			$iso3s[ $cc ] = true;
		}
		foreach ( $standard as $by_country ) {
			foreach ( array_keys( $by_country ) as $cc ) {
				$iso3s[ $cc ] = true;
			}
		}
		foreach ( array_keys( $wide ) as $cc ) {
			$iso3s[ $cc ] = true;
		}

		$out  = array();
		$diag = array();
		foreach ( array_keys( $iso3s ) as $iso3 ) {
			$iso3 = strtoupper( $iso3 );
			if ( strlen( $iso3 ) !== 3 ) {
				continue;
			}

			$g = static function ( string $metric ) use ( $standard, $iso3, $target_year ): ?float {
				if ( ! isset( $standard[ $metric ][ $iso3 ] ) ) {
					return null;
				}
				return self::pick_year_value( $standard[ $metric ][ $iso3 ], $target_year );
			};

			$raw_csv = array();
			foreach ( self::STANDARD_METRIC_KEYS as $mk ) {
				$raw_csv[ $mk ] = $g( $mk );
			}
			$wide_year_row = self::pick_wide_year_row( $wide[ $iso3 ] ?? array(), $target_year );
			if ( null === $raw_csv['population_total'] && isset( $wide_year_row['pop_total__psn'] ) && is_finite( (float) $wide_year_row['pop_total__psn'] ) ) {
				$raw_csv['population_total'] = (float) $wide_year_row['pop_total__psn'];
			}
			if ( null === $raw_csv['surface_area_sqkm'] && isset( $wide_year_row['land_area__km_sq'] ) && is_finite( (float) $wide_year_row['land_area__km_sq'] ) ) {
				$raw_csv['surface_area_sqkm'] = (float) $wide_year_row['land_area__km_sq'];
			}
			if ( null === $raw_csv['population_density_per_km2'] && isset( $wide_year_row['pop_density__psn_per_km_sq'] ) && is_finite( (float) $wide_year_row['pop_density__psn_per_km_sq'] ) ) {
				$raw_csv['population_density_per_km2'] = (float) $wide_year_row['pop_density__psn_per_km_sq'];
			}

			$pop  = $raw_csv['population_total'];
			$area = $raw_csv['surface_area_sqkm'];
			$pden = $raw_csv['population_density_per_km2'];
			$ush  = $raw_csv['urban_share_percent'];
			$ulnd = $raw_csv['urban_land_area_sqkm'];
			$frp  = $raw_csv['forest_percentage'];
			$lc   = $raw_csv['largest_city_population'];
			$rail = $raw_csv['railway_length'];
			$road = $raw_csv['road_length'];

			$triangle_derived = array();
			if ( null !== $pop && null !== $area && null === $pden && $area > 0 ) {
				$pden               = $pop / $area;
				$triangle_derived[] = 'population_density_per_km2';
			} elseif ( null !== $pop && null !== $pden && $pden > 0 && null === $area ) {
				$area                = $pop / $pden;
				$triangle_derived[]  = 'surface_area_sqkm';
			} elseif ( null !== $area && null !== $pden && $pden > 0 && null === $pop ) {
				$pop                = $area * $pden;
				$triangle_derived[] = 'population_total';
			} elseif ( null !== $pden && $pden > 0 && null === $pop && null === $area ) {
				// Учебные wide-наборы: только pop_dens_km2 — без реального населения/площади. Условный масштаб с pop/area = pden,
				// чтобы строились признаки и кластеризация; абсолютные величины «на душу» и km² застройки к реальным странам не привязаны.
				$pop                = 1.0e9;
				$area               = $pop / $pden;
				$triangle_derived[] = 'population_total';
				$triangle_derived[] = 'surface_area_sqkm';
			}
			if ( null === $pop || null === $area || null === $pden || $pop <= 0 || $area <= 0 || $pden <= 0 ) {
				continue;
			}

			$synthetic_triangle_baseline = (
				in_array( 'population_total', $triangle_derived, true )
				&& in_array( 'surface_area_sqkm', $triangle_derived, true )
				&& null === $raw_csv['population_total']
				&& null === $raw_csv['surface_area_sqkm']
			);

			$missing_series = array();
			foreach ( self::STANDARD_METRIC_KEYS as $mk ) {
				if ( null === $raw_csv[ $mk ] && ! in_array( $mk, $triangle_derived, true ) ) {
					$missing_series[] = $mk;
				}
			}

			$urban_share = ( null === $ush ) ? NAN : ( $ush / 100.0 );
			$built_share = ( null === $ulnd || $area <= 0 ) ? NAN : ( $ulnd / $area );
			$forest_share = ( null === $frp ) ? NAN : ( $frp / 100.0 );
			$big_city_ratio = ( null === $lc || $pop <= 0 ) ? NAN : ( $lc / $pop );
			$rail_dens      = ( null === $rail || $area <= 0 ) ? NAN : ( ( $rail * 1000.0 ) / $area );
			$road_dens      = ( null === $road || $area <= 0 ) ? NAN : ( ( $road * 1000.0 ) / $area );
			if ( is_finite( $rail_dens ) && is_finite( $road_dens ) ) {
				$transport_dens = $rail_dens + $road_dens;
			} elseif ( is_finite( $rail_dens ) ) {
				$transport_dens = $rail_dens;
			} elseif ( is_finite( $road_dens ) ) {
				$transport_dens = $road_dens;
			} else {
				$transport_dens = NAN;
			}
			// Площадь страны в км², доля леса в % → лесная площадь на душу в м²/чел. (раньше ошибочно оставались км²/чел при подписи «м²»).
			$forest_area_per_capita = ( null === $frp || $pop <= 0 ) ? NAN : ( ( ( $frp * $area ) / 100.0 ) / $pop ) * 1_000_000.0;

			$sdg_row   = self::pick_sdg_row( $sdg[ $iso3 ] ?? array(), $target_year );
			$sdg_absent = ( null === $sdg_row );
			if ( ! is_array( $sdg_row ) ) {
				$sdg_row = array();
			}

			$wu = $worldua[ $iso3 ] ?? array(
				'big_city_dens'         => 0.0,
				'urban_cases_500k'    => 0.0,
				'avg_urban_dens_500k' => 0.0,
				'urban_pop_500k_sum'  => 0.0,
			);

			$pop_dens = ( ( $pop / $area ) + $pden ) / 2.0;
			$urban_denom              = ( is_finite( $urban_share ) && $urban_share > 0 ) ? ( $urban_share * $pop ) : NAN;
			// urban_land_area_sqkm / число городских жителей → м² на городского жителя (раньше оставалось в км² при подписи «м²»).
			$urban_land_per_urban_pop = ( is_finite( $urban_denom ) && $urban_denom > 0 && null !== $ulnd ) ? ( ( $ulnd / $urban_denom ) * 1_000_000.0 ) : NAN;
			$pct_urban_500k           = $pop > 0 ? ( $wu['urban_pop_500k_sum'] / $pop ) : 0.0;

			$row = array(
				'pop_dens'                           => $pop_dens,
				'urban_share'                        => $urban_share,
				'built_share'                        => $built_share,
				'forest_share'                       => $forest_share,
				'big_city_ratio'                     => $big_city_ratio,
				'big_city_dens'                      => (float) $wu['big_city_dens'],
				'urban_cases_500k'                   => (float) $wu['urban_cases_500k'],
				'avg_urban_dens_500k'                => (float) $wu['avg_urban_dens_500k'],
				'pct_urban_500k'                     => $pct_urban_500k,
				'rail_dens'                          => $rail_dens,
				'sustainable_cities'                 => (float) ( $sdg_row['sustainable_cities'] ?? NAN ),
				'sdg_index_score'                    => (float) ( $sdg_row['sdg_index_score'] ?? NAN ),
				'road_dens'                          => $road_dens,
				'industry_innovation_infrastructure' => (float) ( $sdg_row['industry_innovation_infrastructure'] ?? NAN ),
				'forest_area_per_capita'             => $forest_area_per_capita,
				'transport_dens'                     => $transport_dens,
				'urban_land_per_urban_pop'           => $urban_land_per_urban_pop,
				'peace_justice'                      => (float) ( $sdg_row['peace_justice'] ?? NAN ),
			);

			self::apply_wide_panel_overrides( $row, $wide[ $iso3 ] ?? array(), $target_year );

			$td = isset( $row['transport_dens'] ) ? (float) $row['transport_dens'] : NAN;
			if ( ! is_finite( $td ) && isset( $row['road_dens'], $row['rail_dens'] ) && is_finite( (float) $row['road_dens'] ) && is_finite( (float) $row['rail_dens'] ) ) {
				$row['transport_dens'] = (float) $row['road_dens'] + (float) $row['rail_dens'];
			}

			$nan_feats = array();
			foreach ( self::get_effective_cluster_features() as $feat ) {
				if ( ! isset( $row[ $feat ] ) || ! is_finite( (float) $row[ $feat ] ) ) {
					$nan_feats[] = $feat;
				}
			}

			$diag[ $iso3 ] = array(
				'missing_csv_metrics'      => $missing_series,
				'triangle_derived_metrics' => $triangle_derived,
				'sdg_row_absent'           => $sdg_absent,
				'features_nonfinite'       => $nan_feats,
			);
			if ( $synthetic_triangle_baseline ) {
				$diag[ $iso3 ]['triangle_dimensionless_baseline'] = true;
			}

			$out[ $iso3 ] = $row;
		}
		return array(
			'rows'         => $out,
			'diagnostics' => $diag,
		);
	}

	/**
	 * @param array<int, float> $by_year
	 */
	private static function pick_year_value( array $by_year, int $target_year ): ?float {
		if ( empty( $by_year ) ) {
			return null;
		}
		$best_y = null;
		$best_v = null;
		foreach ( $by_year as $y => $v ) {
			$y = (int) $y;
			if ( $y > $target_year ) {
				continue;
			}
			if ( $best_y === null || $y > $best_y ) {
				$best_y = $y;
				$best_v = (float) $v;
			}
		}
		if ( $best_v !== null && is_finite( $best_v ) ) {
			return $best_v;
		}
		// Нет года ≤ target (например, в CSV только 2023+, а опорный 2022) — берём самый новый доступный год.
		foreach ( $by_year as $y => $v ) {
			$y = (int) $y;
			if ( $y <= 0 ) {
				continue;
			}
			if ( $best_y === null || $y > $best_y ) {
				$best_y = $y;
				$best_v = (float) $v;
			}
		}
		return ( $best_v !== null && is_finite( $best_v ) ) ? $best_v : null;
	}

	/**
	 * @param array<int, array<string, float>> $by_year
	 * @return array<string, float>|null
	 */
	private static function pick_sdg_row( array $by_year, int $target_year ): ?array {
		if ( empty( $by_year ) ) {
			return null;
		}
		$best_y = null;
		$best   = null;
		foreach ( $by_year as $y => $row ) {
			$y = (int) $y;
			if ( $y > $target_year ) {
				continue;
			}
			if ( $best_y === null || $y > $best_y ) {
				$best_y = $y;
				$best   = $row;
			}
		}
		if ( is_array( $best ) && ! empty( $best ) ) {
			return $best;
		}
		foreach ( $by_year as $y => $row ) {
			$y = (int) $y;
			if ( $y <= 0 || ! is_array( $row ) ) {
				continue;
			}
			if ( $best_y === null || $y > $best_y ) {
				$best_y = $y;
				$best   = $row;
			}
		}
		return is_array( $best ) && ! empty( $best ) ? $best : null;
	}
}
