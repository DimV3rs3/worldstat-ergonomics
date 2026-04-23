<?php
/**
 * Индекс эргономичности страны по макроданным (CSV World Statistics Platform).
 * Страновой индекс по макроданным платформы: k-means, нормализация внутри кластеров, шесть измерений и итог E.
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_Country_Macro_Calculator {

	/** Новый ключ — старый кэш без diag/raw_rows больше не читается (избегает фаталов при несовпадении формата). */
	private const TRANSIENT_KEY = 'wsergo_macro_scores_bundle_v11';

	/** Инкремент при изменении логики расчёта — сбрасывает устаревший transient без смены CSV. */
	private const SCORE_BUNDLE_LOGIC = 15;

	/** @var array<string, string>|null ISO2 => ISO3 из data/countries.json платформы */
	private static $iso2_to_iso3_file_cache = null;

	/** Признаки для k-means (вектор для кластеризации стран). */
	private const CLUSTER_FEATURES = [
		'pop_dens',
		'urban_share',
		'built_share',
		'forest_share',
		'big_city_ratio',
		'big_city_dens',
		'urban_cases_500k',
		'avg_urban_dens_500k',
		'pct_urban_500k',
		'rail_dens',
		'sustainable_cities',
		'sdg_index_score',
		'road_dens',
		'industry_innovation_infrastructure',
		'forest_area_per_capita',
		'transport_dens',
		'urban_land_per_urban_pop',
	];

	/** Показатели, для которых min–max делается внутри кластера. */
	private const NORMALIZE_WITHIN_CLUSTER = [
		'pop_dens',
		'urban_share',
		'built_share',
		'forest_share',
		'big_city_ratio',
		'big_city_dens',
		'urban_cases_500k',
		'avg_urban_dens_500k',
		'pct_urban_500k',
		'rail_dens',
		'road_dens',
		'forest_area_per_capita',
		'transport_dens',
		'urban_land_per_urban_pop',
		'sustainable_cities',
		'sdg_index_score',
		'industry_innovation_infrastructure',
		'peace_justice',
		// Доп. признаки из широких CSV (country_code, year, несколько столбцов).
		'urban_growth_pct',
		'elec_access',
		'broadband_per100',
		'internet_pct',
		'mobile_per100',
		'lpi_score',
		'lpi_infra',
		'air_passengers',
		'container_teu',
		'protected_pct',
		'pm25_ug_m3',
		'ghg_per_capita',
		'renew_energy_pct',
		'clean_cooking_pct',
		'water_prod_usd_m3',
		'arable_pct',
		'life_exp_years',
		'mort_u5_per1000',
		'mort_infant_per1000',
		'mort_neo_per1000',
		'sanitation_safe_pct',
		'water_safe_pct',
		'uhc_index',
		'cpi_business',
		'cpi_corruption',
		'homicide_per100k',
		'rnd_gdp_pct',
		'hi_tech_exp_pct',
		'energy_use_kg_oil_cap',
		'energy_int_mj_gdp_ppp',
		'gdp_per_energy_ppp',
		'renew_elec_pct',
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
		delete_transient( 'wsergo_macro_scores_bundle' );
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
				array( 'signal' => 'pop_dens', 'invert' => true, 'weight' => 0.20 ),
				array( 'signal' => 'urban_share', 'invert' => false, 'weight' => 0.15 ),
				array( 'signal' => 'rail_dens', 'invert' => false, 'weight' => 0.20 ),
				array( 'signal' => 'avg_urban_dens_500k', 'invert' => false, 'weight' => 0.15 ),
				array( 'signal' => 'urban_cases_500k', 'invert' => false, 'weight' => 0.13 ),
				array( 'signal' => 'road_dens', 'invert' => false, 'weight' => 0.12 ),
				array( 'signal' => 'sdg9', 'invert' => false, 'weight' => 0.05 ),
			),
			'Cm' => array(
				array( 'signal' => 'forest_share', 'invert' => false, 'weight' => 0.30 ),
				array( 'signal' => 'built_share', 'invert' => true, 'weight' => 0.25 ),
				array( 'signal' => 'big_city_dens', 'invert' => true, 'weight' => 0.20 ),
				array( 'signal' => 'forest_area_per_capita', 'invert' => false, 'weight' => 0.10 ),
				array( 'signal' => 'pop_dens', 'invert' => true, 'weight' => 0.10 ),
				array( 'signal' => 'sdg11', 'invert' => false, 'weight' => 0.05 ),
			),
			'H'  => array(
				array( 'signal' => 'urban_share', 'invert' => false, 'weight' => 0.20 ),
				array( 'signal' => 'forest_share', 'invert' => false, 'weight' => 0.20 ),
				array( 'signal' => 'pop_dens', 'invert' => true, 'weight' => 0.15 ),
				array( 'signal' => 'pct_urban_500k', 'invert' => false, 'weight' => 0.15 ),
				array( 'signal' => 'sdg11', 'invert' => false, 'weight' => 0.10 ),
				array( 'signal' => 'big_city_ratio', 'invert' => true, 'weight' => 0.10 ),
				array( 'signal' => 'transport_dens', 'invert' => false, 'weight' => 0.05 ),
				array( 'signal' => 'urban_land_per_urban_pop', 'invert' => false, 'weight' => 0.05 ),
			),
			'A'  => array(
				array( 'signal' => 'urban_share', 'invert' => false, 'weight' => 0.25 ),
				array( 'signal' => 'urban_cases_500k', 'invert' => false, 'weight' => 0.20 ),
				array( 'signal' => 'big_city_ratio', 'invert' => true, 'weight' => 0.20 ),
				array( 'signal' => 'sdg11', 'invert' => false, 'weight' => 0.15 ),
				array( 'signal' => 'urban_land_per_urban_pop', 'invert' => false, 'weight' => 0.10 ),
				array( 'signal' => 'avg_urban_dens_500k', 'invert' => true, 'weight' => 0.05 ),
				array( 'signal' => 'built_share', 'invert' => false, 'weight' => 0.05 ),
			),
			'S'  => array(
				array( 'signal' => 'pop_dens', 'invert' => false, 'weight' => 0.25 ),
				array( 'signal' => 'forest_share', 'invert' => false, 'weight' => 0.20 ),
				array( 'signal' => 'big_city_dens', 'invert' => false, 'weight' => 0.20 ),
				array( 'signal' => 'sdg_index', 'invert' => true, 'weight' => 0.15 ),
				array( 'signal' => 'sdg16', 'invert' => false, 'weight' => 0.10 ),
				array( 'signal' => 'built_share', 'invert' => true, 'weight' => 0.05 ),
				array( 'signal' => 'rail_dens', 'invert' => false, 'weight' => 0.05 ),
			),
			'Ct' => array(
				array( 'signal' => 'avg_urban_dens_500k', 'invert' => true, 'weight' => 0.25 ),
				array( 'signal' => 'urban_cases_500k', 'invert' => false, 'weight' => 0.20 ),
				array( 'signal' => 'built_share', 'invert' => false, 'weight' => 0.15 ),
				array( 'signal' => 'sdg11', 'invert' => false, 'weight' => 0.15 ),
				array( 'signal' => 'pop_dens', 'invert' => true, 'weight' => 0.10 ),
				array( 'signal' => 'transport_dens', 'invert' => false, 'weight' => 0.10 ),
				array( 'signal' => 'big_city_ratio', 'invert' => true, 'weight' => 0.05 ),
			),
		);
	}

	/**
	 * Встроенные ключи сигналов (k-means, нормализация, формулы по умолчанию), без пользовательских дополнений.
	 *
	 * @return list<string>
	 */
	public static function macro_signal_builtin_slist(): array {
		$aliases = array( 'sdg11', 'sdg9', 'sdg16', 'sdg_index' );
		$merged  = array_merge( self::CLUSTER_FEATURES, self::NORMALIZE_WITHIN_CLUSTER, $aliases );
		$merged  = array_values( array_unique( $merged ) );
		sort( $merged );
		return $merged;
	}

	/**
	 * Сигналы, допустимые в формулах макро-осей и в списке признаков кластеризации (встроенные + из настроек «Доп. ключи»).
	 *
	 * Новые столбцы wide: имя после нормализации заголовка = ключ в строке признаков; чтобы ключ появился здесь, задайте его в
	 * {@see WSErgo_Settings::OPTION_MACRO_EXTRA_SIGNALS_TEXT} или добавьте сопоставление в {@see self::wide_csv_column_to_signal()}.
	 *
	 * @return list<string>
	 */
	public static function macro_signal_allowlist(): array {
		$merged = self::macro_signal_builtin_slist();
		if ( class_exists( 'WSErgo_Settings' ) ) {
			$merged = array_merge( $merged, WSErgo_Settings::get_macro_extra_signals_effective() );
		}
		$merged = array_values( array_unique( $merged ) );
		sort( $merged );
		return $merged;
	}

	/**
	 * Подписи для UI (ось макро → человекочитаемое имя).
	 *
	 * @return array<string, string>
	 */
	public static function macro_axis_labels_ru(): array {
		return array(
			'F'  => __( 'Функциональность (F)', 'worldstat-ergonomics' ),
			'Cm' => __( 'Комфорт (Cm)', 'worldstat-ergonomics' ),
			'H'  => __( 'Здоровье / среда (H)', 'worldstat-ergonomics' ),
			'A'  => __( 'Доступность (A)', 'worldstat-ergonomics' ),
			'S'  => __( 'Безопасность / устойчивость (S)', 'worldstat-ergonomics' ),
			'Ct' => __( 'Управляемость (Ct)', 'worldstat-ergonomics' ),
		);
	}

	/**
	 * Встроенный набор признаков для k-means (если в настройках не задано ≥2 своих).
	 *
	 * @return list<string>
	 */
	public static function default_cluster_features(): array {
		return self::CLUSTER_FEATURES;
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
			return array(
				'y'        => (int) $bund['y'],
				'r'        => (int) $bund['r'],
				'lv'       => (int) $bund['lv'],
				'ch'       => (string) ( $bund['ch'] ?? '' ),
				'scores'   => $bund['scores'],
				'diag'     => $bund['diag'],
				'raw_rows' => $bund['raw_rows'],
			);
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
		return $full;
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
		return array(
			'iso3'        => $iso3,
			'iso2'        => $iso2,
			'year'        => $y,
			'scores'      => isset( $scores[ $iso3 ] ) && is_array( $scores[ $iso3 ] ) ? $scores[ $iso3 ] : null,
			'diagnostics' => isset( $diags[ $iso3 ] ) && is_array( $diags[ $iso3 ] ) ? $diags[ $iso3 ] : array(),
			'raw_row'     => isset( $raws[ $iso3 ] ) && is_array( $raws[ $iso3 ] ) ? $raws[ $iso3 ] : null,
		);
	}

	/**
	 * Подписи стандартных рядов CSV (для сообщений о пропусках).
	 *
	 * @return array<string, string>
	 */
	public static function standard_metric_labels_ru(): array {
		return array(
			'population_total'           => __( 'Население, чел.', 'worldstat-ergonomics' ),
			'surface_area_sqkm'        => __( 'Площадь территории, км²', 'worldstat-ergonomics' ),
			'population_density_per_km2' => __( 'Плотность населения на км²', 'worldstat-ergonomics' ),
			'urban_share_percent'      => __( 'Доля городского населения, %', 'worldstat-ergonomics' ),
			'urban_land_area_sqkm'     => __( 'Площадь городской застройки, км²', 'worldstat-ergonomics' ),
			'forest_percentage'        => __( 'Лесной покров, %', 'worldstat-ergonomics' ),
			'largest_city_population'  => __( 'Население крупнейшего города', 'worldstat-ergonomics' ),
			'railway_length'           => __( 'Железные дороги, км', 'worldstat-ergonomics' ),
			'road_length'              => __( 'Дороги, км', 'worldstat-ergonomics' ),
		);
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

	private static function iso2_to_iso3( string $iso2 ): string {
		if ( ! class_exists( 'WorldStat_Country_CPT' ) ) {
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
	 * Признаки k-means: из настроек или встроенный список.
	 *
	 * @return list<string>
	 */
	private static function get_effective_cluster_features(): array {
		if ( ! class_exists( 'WSErgo_Settings' ) ) {
			return self::CLUSTER_FEATURES;
		}
		$custom = WSErgo_Settings::get_macro_cluster_features();
		if ( count( $custom ) < 2 ) {
			return self::CLUSTER_FEATURES;
		}
		$allow = array_flip( self::macro_signal_allowlist() );
		$out   = array();
		foreach ( $custom as $f ) {
			$f = sanitize_key( (string) $f );
			if ( $f !== '' && isset( $allow[ $f ] ) ) {
				$out[] = $f;
			}
		}
		$out = array_values( array_unique( $out ) );
		return count( $out ) >= 2 ? $out : self::CLUSTER_FEATURES;
	}

	/**
	 * Столбцы для min–max внутри кластера (базовый список + признаки из формул осей + признаки кластера).
	 *
	 * @return list<string>
	 */
	private static function collect_normalization_columns(): array {
		$cols = self::NORMALIZE_WITHIN_CLUSTER;
		$axis_terms = class_exists( 'WSErgo_Settings' )
			? WSErgo_Settings::get_macro_axis_terms_resolved()
			: self::default_macro_axis_terms();
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

		$cluster_feats = self::get_effective_cluster_features();

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
		$labels = self::kmeans( $scaled, $k, 80 );

		$n = count( $keys );
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

			$axis_terms = class_exists( 'WSErgo_Settings' )
				? WSErgo_Settings::get_macro_axis_terms_resolved()
				: self::default_macro_axis_terms();

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

			$ew = class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::get_macro_e_axis_weights() : array(
				'F'  => 0.25,
				'Cm' => 0.22,
				'H'  => 0.09,
				'A'  => 0.10,
				'S'  => 0.20,
				'Ct' => 0.13,
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
	 * @param list<list<float>> $X уже масштабированные строки
	 * @return list<int>
	 */
	private static function kmeans( array $X, int $k, int $max_iter ): array {
		$n = count( $X );
		$d = count( $X[0] );
		$k = max( 1, min( $k, $n ) );
		$centroids = array();
		for ( $c = 0; $c < $k; $c++ ) {
			$centroids[] = $X[ $c % $n ];
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
				if ( $labels[ $i ] !== $best_c ) {
					$labels[ $i ] = $best_c;
					$changed        = true;
				}
			}
			$sums   = array();
			$counts = array_fill( 0, $k, 0 );
			for ( $c = 0; $c < $k; $c++ ) {
				$sums[ $c ] = array_fill( 0, $d, 0.0 );
			}
			for ( $i = 0; $i < $n; $i++ ) {
				$c = $labels[ $i ];
				++$counts[ $c ];
				for ( $j = 0; $j < $d; $j++ ) {
					$sums[ $c ][ $j ] += $X[ $i ][ $j ];
				}
			}
			for ( $c = 0; $c < $k; $c++ ) {
				if ( $counts[ $c ] < 1 ) {
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
				$key = self::metric_key_for_standard_csv_row( $id, $name );
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
		if ( isset( $h['country_code'], $h['year'] ) ) {
			$has_long_value = isset( $h['value'] ) || isset( $h['obs_value'] ) || isset( $h['indicator_value'] ) || isset( $h['val'] );
			$skip           = array( 'country_code' => true, 'year' => true, 'iso3' => true, 'iso' => true, 'cca3' => true, 'country' => true, 'country_name' => true );
			$data_cols      = 0;
			foreach ( array_keys( $h ) as $k ) {
				if ( ! isset( $skip[ $k ] ) ) {
					++$data_cols;
				}
			}
			if ( ! $has_long_value && $data_cols >= 1 ) {
				return 'wide_panel';
			}
		}
		return 'standard';
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
			$cc = strtoupper( trim( (string) ( $map['country_code'] ?? $map['iso3'] ?? $map['cca3'] ?? '' ) ) );
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
		if ( $val <= 0 || ! is_finite( $val ) ) {
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
				}
				if ( isset( $cols['urban_share'] ) && is_finite( (float) $cols['urban_share'] ) ) {
					self::wide_panel_seed_metric_if_absent( $standard, 'urban_share_percent', $iso3, $y, (float) $cols['urban_share'] * 100.0 );
				}
				if ( isset( $cols['forest_share'] ) && is_finite( (float) $cols['forest_share'] ) ) {
					self::wide_panel_seed_metric_if_absent( $standard, 'forest_percentage', $iso3, $y, (float) $cols['forest_share'] * 100.0 );
				}
			}
		}
	}

	/**
	 * Заголовки вроде «Country Code», «Indicator value» → country_code, indicator_value.
	 */
	private static function normalize_csv_header_key( string $col ): string {
		$col = trim( $col );
		$c   = strtolower( str_replace( array( "\t", ' ', '-' ), '_', $col ) );
		$c   = (string) preg_replace( '/_+/', '_', $c );
		return (string) preg_replace( '/[^a-z0-9_]/', '', $c );
	}

	/**
	 * Явная привязка id CSV в БД к ключу ряда (иначе — по имени файла).
	 */
	private static function metric_key_for_standard_csv_row( int $file_id, string $filename ): ?string {
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
		return self::metric_key_from_filename( $filename );
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
		if ( strpos( $stem, 'road' ) !== false && strpos( $stem, 'length' ) !== false ) {
			return 'road_length';
		}
		if ( strpos( $stem, 'rail' ) !== false && strpos( $stem, 'length' ) !== false ) {
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
			$cc = strtoupper( trim( (string) ( $map['country_code'] ?? $map['iso3'] ?? $map['iso'] ?? '' ) ) );
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
			$cc = strtoupper( trim( (string) ( $map['country_code'] ?? $map['cca3'] ?? $map['iso3'] ?? '' ) ) );
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
