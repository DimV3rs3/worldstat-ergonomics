<?php
/**
 * Динамическая группировка стран по индексу эргономичности E.
 *
 * @package WorldStatErgonomics
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Пять уровней по эмпирическим перцентилям ранжирования (5% / 25% / 40% / 25% / 5%).
 */
class WSErgo_Tier_Classifier {

	public const TIER_EXCELLENT = 'excellent';
	public const TIER_GOOD      = 'good';
	public const TIER_AVERAGE   = 'average';
	public const TIER_LOW       = 'low';
	public const TIER_CRITICAL  = 'critical';

	/** Доли групп от общей численности стран с индексом E (сверху вниз по рейтингу). */
	private const SHARE_EXCELLENT = 0.05;
	private const SHARE_GOOD      = 0.25;
	private const SHARE_AVERAGE   = 0.40;
	private const SHARE_LOW       = 0.25;
	private const SHARE_CRITICAL  = 0.05;

	/** @var array<string, array{id:string,label:string,share_pct:float}>|null */
	private static $tier_defs = null;

	/** @var array{mu:float,sigma:float,count:int}|null */
	private static $dist_cache = null;

	/** @var array{excellent_min:float,good_min:float,average_min:float,low_min:float,count:int}|null */
	private static $thresholds_cache = null;

	/** @var array<string, string>|null iso2 => tier id */
	private static $iso2_tier_cache = null;

	/**
	 * @return array<string, array{id:string,label:string,share_pct:float}>
	 */
	public static function tier_definitions(): array {
		if ( null !== self::$tier_defs ) {
			return self::$tier_defs;
		}
		self::$tier_defs = array(
			self::TIER_EXCELLENT => array(
				'id'        => self::TIER_EXCELLENT,
				'label'     => __( 'Отличная', 'worldstat-ergonomics' ),
				'share_pct' => self::SHARE_EXCELLENT * 100.0,
			),
			self::TIER_GOOD => array(
				'id'        => self::TIER_GOOD,
				'label'     => __( 'Хорошая', 'worldstat-ergonomics' ),
				'share_pct' => self::SHARE_GOOD * 100.0,
			),
			self::TIER_AVERAGE => array(
				'id'        => self::TIER_AVERAGE,
				'label'     => __( 'Средняя', 'worldstat-ergonomics' ),
				'share_pct' => self::SHARE_AVERAGE * 100.0,
			),
			self::TIER_LOW => array(
				'id'        => self::TIER_LOW,
				'label'     => __( 'Низкая', 'worldstat-ergonomics' ),
				'share_pct' => self::SHARE_LOW * 100.0,
			),
			self::TIER_CRITICAL => array(
				'id'        => self::TIER_CRITICAL,
				'label'     => __( 'Критическая', 'worldstat-ergonomics' ),
				'share_pct' => self::SHARE_CRITICAL * 100.0,
			),
		);
		return self::$tier_defs;
	}

	/**
	 * @return array{mu:float,sigma:float,count:int}
	 */
	public static function get_distribution(): array {
		if ( null !== self::$dist_cache ) {
			return self::$dist_cache;
		}
		$vals = self::collect_index_values();
		if ( count( $vals ) < 3 ) {
			self::$dist_cache = array(
				'mu'    => 50.0,
				'sigma' => 15.0,
				'count' => count( $vals ),
			);
			return self::$dist_cache;
		}
		$mu = array_sum( $vals ) / count( $vals );
		$var = 0.0;
		foreach ( $vals as $v ) {
			$var += ( $v - $mu ) ** 2;
		}
		$var   /= count( $vals );
		$sigma = sqrt( max( $var, 1e-6 ) );
		self::$dist_cache = array(
			'mu'    => $mu,
			'sigma' => $sigma,
			'count' => count( $vals ),
		);
		return self::$dist_cache;
	}

	/**
	 * Пороги E (минимум для попадания в группу при сортировке по убыванию).
	 *
	 * @return array{excellent_min:float,good_min:float,average_min:float,low_min:float,count:int}
	 */
	public static function get_thresholds(): array {
		if ( null !== self::$thresholds_cache ) {
			return self::$thresholds_cache;
		}
		$vals = self::collect_index_values();
		$n    = count( $vals );
		if ( $n < 1 ) {
			self::$thresholds_cache = array(
				'excellent_min' => INF,
				'good_min'      => INF,
				'average_min'   => INF,
				'low_min'       => INF,
				'count'         => 0,
			);
			return self::$thresholds_cache;
		}
		sort( $vals, SORT_NUMERIC );
		self::$thresholds_cache = array(
			'excellent_min' => self::percentile_sorted_asc( $vals, 1.0 - self::SHARE_EXCELLENT ),
			'good_min'      => self::percentile_sorted_asc( $vals, 1.0 - self::SHARE_EXCELLENT - self::SHARE_GOOD ),
			'average_min'   => self::percentile_sorted_asc( $vals, self::SHARE_CRITICAL + self::SHARE_LOW ),
			'low_min'       => self::percentile_sorted_asc( $vals, self::SHARE_CRITICAL ),
			'count'         => $n,
		);
		return self::$thresholds_cache;
	}

	/**
	 * z-оценка индекса E (для справки в UI).
	 */
	public static function z_score_for_index( float $e ): float {
		$dist = self::get_distribution();
		if ( $dist['sigma'] <= 1e-9 ) {
			return 0.0;
		}
		return ( $e - $dist['mu'] ) / $dist['sigma'];
	}

	/**
	 * @return array{id:string,label:string,z:float,share_pct:float}
	 */
	public static function classify_index( float $e ): array {
		$tier = self::tier_from_index( $e );
		return array(
			'id'        => $tier['id'],
			'label'     => $tier['label'],
			'z'         => round( self::z_score_for_index( $e ), 3 ),
			'share_pct' => $tier['share_pct'],
		);
	}

	/**
	 * @return array{id:string,label:string,share_pct:float}
	 */
	private static function tier_from_index( float $e ): array {
		$defs = self::tier_definitions();
		if ( ! is_finite( $e ) || $e <= 0 ) {
			return $defs[ self::TIER_CRITICAL ];
		}
		$t = self::get_thresholds();
		if ( $t['count'] < 1 ) {
			return $defs[ self::TIER_AVERAGE ];
		}
		if ( $e >= $t['excellent_min'] ) {
			return $defs[ self::TIER_EXCELLENT ];
		}
		if ( $e >= $t['good_min'] ) {
			return $defs[ self::TIER_GOOD ];
		}
		if ( $e >= $t['average_min'] ) {
			return $defs[ self::TIER_AVERAGE ];
		}
		if ( $e >= $t['low_min'] ) {
			return $defs[ self::TIER_LOW ];
		}
		return $defs[ self::TIER_CRITICAL ];
	}

	/**
	 * @return array{id:string,label:string,z:float,share_pct:float}|null
	 */
	public static function get_tier_for_iso2( string $iso2 ): ?array {
		$iso2 = strtoupper( sanitize_text_field( $iso2 ) );
		if ( strlen( $iso2 ) !== 2 ) {
			return null;
		}
		if ( null === self::$iso2_tier_cache ) {
			self::build_iso2_cache();
		}
		$tier_id = self::$iso2_tier_cache[ $iso2 ] ?? null;
		if ( null === $tier_id ) {
			return null;
		}
		$e = class_exists( 'WSErgo_Data' ) ? WSErgo_Data::get_country_ergo_index( $iso2 ) : 0.0;
		if ( $e <= 0 ) {
			return null;
		}
		return self::classify_index( $e );
	}

	private static function build_iso2_cache(): void {
		self::$iso2_tier_cache = array();
		if ( ! class_exists( 'WorldStat_Country_CPT' ) || ! class_exists( 'WSErgo_Data' ) ) {
			return;
		}
		$iso_list = array_keys( WorldStat_Country_CPT::get_code_map() );
		$indices  = WSErgo_Data::bulk_country_ergo_index_for_iso2_list( $iso_list );
		foreach ( $indices as $iso2 => $e ) {
			$e = (float) $e;
			if ( ! is_finite( $e ) || $e <= 0 ) {
				continue;
			}
			$tier = self::tier_from_index( $e );
			self::$iso2_tier_cache[ strtoupper( (string) $iso2 ) ] = $tier['id'];
		}
	}

	/**
	 * @return list<float>
	 */
	private static function collect_index_values(): array {
		$vals = array();
		if ( ! class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			return $vals;
		}
		foreach ( WSErgo_Country_Macro_Calculator::get_all_scores() as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['E'] ) ) {
				continue;
			}
			$e = (float) $row['E'];
			if ( is_finite( $e ) && $e > 0 ) {
				$vals[] = $e;
			}
		}
		return $vals;
	}

	/**
	 * @param list<float> $sorted_asc значения по возрастанию
	 */
	private static function percentile_sorted_asc( array $sorted_asc, float $p ): float {
		$n = count( $sorted_asc );
		if ( $n < 1 ) {
			return NAN;
		}
		if ( $n === 1 ) {
			return $sorted_asc[0];
		}
		$p   = max( 0.0, min( 1.0, $p ) );
		$idx = $p * ( $n - 1 );
		$lo  = (int) floor( $idx );
		$hi  = (int) ceil( $idx );
		if ( $lo === $hi ) {
			return $sorted_asc[ $lo ];
		}
		$frac = $idx - $lo;
		return $sorted_asc[ $lo ] * ( 1.0 - $frac ) + $sorted_asc[ $hi ] * $frac;
	}

	/**
	 * Сброс runtime-кэша (после пересчёта макробандла).
	 */
	public static function flush_runtime_cache(): void {
		self::$dist_cache         = null;
		self::$thresholds_cache   = null;
		self::$iso2_tier_cache    = null;
	}
}
