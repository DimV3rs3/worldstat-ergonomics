<?php
/**
 * Категории эргономичности стран по глобальному распределению индекса E.
 *
 * Доли: Отлично 5%, Хорошо 25%, Средне 40%, Плохо 25%, Критически 5%.
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_Tier_Classifier {

	/** @var array<string, float>|null */
	private static $thresholds_cache = null;

	/**
	 * Сброс кэша порогов (после пересчёта макро / смены источника индекса).
	 */
	public static function flush_runtime_cache(): void {
		self::$thresholds_cache = null;
	}

	/**
	 * Пороги индекса E по перцентилям выборки стран (0–100).
	 *
	 * @return array{excellent_min:float,good_min:float,average_min:float,poor_min:float,n:int}
	 */
	public static function get_thresholds(): array {
		if ( is_array( self::$thresholds_cache ) ) {
			return self::$thresholds_cache;
		}

		$values = self::collect_all_country_indices();
		$n      = count( $values );

		if ( $n < 5 ) {
			self::$thresholds_cache = array(
				'excellent_min' => 80.0,
				'good_min'      => 65.0,
				'average_min'   => 45.0,
				'poor_min'      => 25.0,
				'n'             => $n,
			);
			return self::$thresholds_cache;
		}

		sort( $values, SORT_NUMERIC );
		self::$thresholds_cache = array(
			'excellent_min' => self::percentile_sorted( $values, 0.95 ),
			'good_min'      => self::percentile_sorted( $values, 0.70 ),
			'average_min'   => self::percentile_sorted( $values, 0.30 ),
			'poor_min'      => self::percentile_sorted( $values, 0.05 ),
			'n'             => $n,
		);

		return self::$thresholds_cache;
	}

	/**
	 * @param float              $index
	 * @param array<string,float>|null $thresholds
	 * @return array{slug:string,label:string,color:string}
	 */
	public static function classify_index( float $index, ?array $thresholds = null ): array {
		if ( $index <= 0 || ! is_finite( $index ) ) {
			return array(
				'slug'  => '',
				'label' => '—',
				'color' => '',
			);
		}

		$t = is_array( $thresholds ) ? $thresholds : self::get_thresholds();

		if ( $index >= (float) ( $t['excellent_min'] ?? INF ) ) {
			$slug = 'excellent';
		} elseif ( $index >= (float) ( $t['good_min'] ?? INF ) ) {
			$slug = 'good';
		} elseif ( $index >= (float) ( $t['average_min'] ?? INF ) ) {
			$slug = 'average';
		} elseif ( $index >= (float) ( $t['poor_min'] ?? INF ) ) {
			$slug = 'poor';
		} else {
			$slug = 'critical';
		}

		return self::tier_meta( $slug );
	}

	/**
	 * Категория для ISO2 (с учётом текущего источника индекса страны).
	 *
	 * @return array{slug:string,label:string,color:string,index?:float}|null
	 */
	public static function get_tier_for_iso2( string $iso2 ): ?array {
		$iso2 = strtoupper( sanitize_key( $iso2 ) );
		if ( $iso2 === '' || ! class_exists( 'WSErgo_Country_Data' ) ) {
			return null;
		}

		$index = (float) WSErgo_Country_Data::get_country_ergo_index( $iso2 );
		if ( $index <= 0 || ! is_finite( $index ) ) {
			return null;
		}

		$tier          = self::classify_index( $index );
		$tier['index'] = $index;

		return $tier;
	}

	/**
	 * @return array{slug:string,label:string,color:string}
	 */
	private static function tier_meta( string $slug ): array {
		$labels = array(
			'excellent' => __( 'Отлично', 'worldstat-ergonomics' ),
			'good'      => __( 'Хорошо', 'worldstat-ergonomics' ),
			'average'   => __( 'Средне', 'worldstat-ergonomics' ),
			'poor'      => __( 'Плохо', 'worldstat-ergonomics' ),
			'critical'  => __( 'Критически', 'worldstat-ergonomics' ),
		);
		$colors = array(
			'excellent' => '#059669',
			'good'      => '#16a34a',
			'average'   => '#ca8a04',
			'poor'      => '#ea580c',
			'critical'  => '#dc2626',
		);

		return array(
			'slug'  => $slug,
			'label' => $labels[ $slug ] ?? $slug,
			'color' => $colors[ $slug ] ?? '#6b7280',
		);
	}

	/**
	 * @return list<float>
	 */
	private static function collect_all_country_indices(): array {
		$iso2_list = array();
		if ( class_exists( 'WorldStat_Data' ) ) {
			$countries = WorldStat_Data::get_countries( array( 'per_page' => 500 ) );
			foreach ( $countries as $row ) {
				if ( ! empty( $row['iso2'] ) ) {
					$iso2_list[] = strtoupper( (string) $row['iso2'] );
				}
			}
		}
		$iso2_list = array_values( array_unique( array_filter( $iso2_list ) ) );
		if ( [] === $iso2_list || ! class_exists( 'WSErgo_Country_Data' ) ) {
			return array();
		}

		$bulk   = WSErgo_Country_Data::bulk_country_ergo_index_for_iso2_list( $iso2_list );
		$values = array();
		foreach ( $bulk as $v ) {
			$f = (float) $v;
			if ( $f > 0 && is_finite( $f ) ) {
				$values[] = $f;
			}
		}

		return $values;
	}

	/**
	 * @param list<float> $sorted
	 */
	private static function percentile_sorted( array $sorted, float $p ): float {
		$n = count( $sorted );
		if ( $n < 1 ) {
			return 0.0;
		}
		if ( $n === 1 ) {
			return (float) $sorted[0];
		}
		$p     = max( 0.0, min( 1.0, $p ) );
		$idx   = ( $n - 1 ) * $p;
		$lower = (int) floor( $idx );
		$upper = (int) ceil( $idx );
		if ( $lower === $upper ) {
			return (float) $sorted[ $lower ];
		}
		$weight = $idx - $lower;

		return (float) ( $sorted[ $lower ] * ( 1 - $weight ) + $sorted[ $upper ] * $weight );
	}
}
