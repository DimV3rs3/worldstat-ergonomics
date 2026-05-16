<?php
/**
 * Рекомендации по улучшению эргономичности на основе показателей и весов модели.
 *
 * @package WorldStatErgonomics
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_Macro_Recommendations {

	/**
	 * @return list<array{signal:string,label:string,axes:list<string>,axis_labels:string,gap:float,problem:float,impact:float,hint:string}>
	 */
	public static function analyze_country( string $iso2, string $scope = 'country' ): array {
		$scope = ( 'city' === $scope ) ? 'city' : 'country';
		if ( ! class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			return array();
		}
		$detail = ( 'city' === $scope )
			? WSErgo_Country_Macro_Calculator::get_city_macro_detail( $iso2 )
			: WSErgo_Country_Macro_Calculator::get_country_macro_detail( $iso2 );
		if ( ! is_array( $detail ) || empty( $detail['raw_row'] ) || ! is_array( $detail['raw_row'] ) ) {
			return array();
		}

		$bundle = ( 'city' === $scope )
			? WSErgo_Country_Macro_Calculator::get_city_full_bundle()
			: WSErgo_Country_Macro_Calculator::get_full_bundle();

		$raw_rows = isset( $bundle['raw_rows'] ) && is_array( $bundle['raw_rows'] ) ? $bundle['raw_rows'] : array();
		$scores   = isset( $bundle['scores'] ) && is_array( $bundle['scores'] ) ? $bundle['scores'] : array();
		if ( count( $raw_rows ) < 5 ) {
			return array();
		}

		$raw        = $detail['raw_row'];
		$axis_scores = isset( $detail['scores'] ) && is_array( $detail['scores'] ) ? $detail['scores'] : array();
		$axis_terms  = isset( $detail['axis_terms'] ) && is_array( $detail['axis_terms'] ) ? $detail['axis_terms'] : array();
		$e_w         = isset( $detail['e_axis_weights'] ) && is_array( $detail['e_axis_weights'] ) ? $detail['e_axis_weights'] : array();

		$benchmarks = self::benchmarks_from_high_ergo_countries( $raw_rows, $scores );
		$axis_labels  = self::axis_labels_map();

		/** @var array<string, array<string, mixed>> */
		$by_signal = array();

		foreach ( array( 'F', 'Cm', 'H', 'A', 'S', 'Ct' ) as $ax ) {
			$axis_score = isset( $axis_scores[ $ax ] ) ? (float) $axis_scores[ $ax ] : NAN;
			$axis_w     = isset( $e_w[ $ax ] ) ? (float) $e_w[ $ax ] : 0.0;
			if ( $axis_w <= 0 ) {
				continue;
			}
			$terms = isset( $axis_terms[ $ax ] ) && is_array( $axis_terms[ $ax ] ) ? $axis_terms[ $ax ] : array();
			foreach ( $terms as $term ) {
				$sig = sanitize_key( (string) ( $term['signal'] ?? '' ) );
				$wt  = (float) ( $term['weight'] ?? 0 );
				if ( $sig === '' || $wt <= 0 ) {
					continue;
				}
				$inv = ! empty( $term['invert'] );
				$cur = isset( $raw[ $sig ] ) ? (float) $raw[ $sig ] : NAN;
				if ( ! is_finite( $cur ) ) {
					continue;
				}
				$bench = $benchmarks[ $sig ] ?? null;
				if ( null === $bench || ! is_finite( (float) $bench ) ) {
					continue;
				}
				$bench = (float) $bench;
				$gap   = $inv ? ( $cur - $bench ) : ( $bench - $cur );
				if ( $gap <= 0 ) {
					continue;
				}
				$denom = max( abs( $bench ), 1e-9 );
				$rel   = min( 3.0, $gap / $denom );
				$axis_weak = is_finite( $axis_score ) ? ( 1.0 + max( 0.0, ( 60.0 - $axis_score ) / 100.0 ) ) : 1.0;
				$impact      = $rel * $wt * $axis_w * $axis_weak;

				if ( ! isset( $by_signal[ $sig ] ) ) {
					$label = class_exists( 'WSErgo_Country_Macro_Calculator' )
						? WSErgo_Country_Macro_Calculator::data_label_ru( $sig )
						: $sig;
					$by_signal[ $sig ] = array(
						'signal'       => $sig,
						'label'        => $label,
						'invert'       => $inv,
						'axes'         => array(),
						'gap'          => $gap,
						'problem'      => $rel,
						'impact'       => 0.0,
						'bench'        => $bench,
						'current'      => $cur,
					);
				}
				if ( ! in_array( $ax, $by_signal[ $sig ]['axes'], true ) ) {
					$by_signal[ $sig ]['axes'][] = $ax;
				}
				$by_signal[ $sig ]['impact'] += $impact;
				if ( $rel > (float) $by_signal[ $sig ]['problem'] ) {
					$by_signal[ $sig ]['problem'] = $rel;
					$by_signal[ $sig ]['gap']    = $gap;
				}
			}
		}

		$recs = array();
		foreach ( $by_signal as $row ) {
			if ( (float) $row['impact'] < 0.003 ) {
				continue;
			}
			$ax_codes = $row['axes'];
			sort( $ax_codes, SORT_STRING );
			$ax_names = array();
			foreach ( $ax_codes as $code ) {
				$ax_names[] = $axis_labels[ $code ] ?? $code;
			}
			$recs[] = array(
				'signal'      => $row['signal'],
				'label'       => $row['label'],
				'axes'        => $ax_codes,
				'axis_labels' => implode( ', ', $ax_names ),
				'gap'         => round( (float) $row['gap'], 4 ),
				'problem'     => round( (float) $row['problem'], 4 ),
				'impact'      => round( (float) $row['impact'], 4 ),
				'hint'        => self::hint_for_signal( (bool) $row['invert'] ),
			);
		}

		usort(
			$recs,
			static function ( $a, $b ) {
				$c = ( $b['problem'] <=> $a['problem'] );
				if ( 0 !== $c ) {
					return $c;
				}
				return ( $b['impact'] <=> $a['impact'] );
			}
		);

		return array_slice( $recs, 0, 12 );
	}

	/**
	 * Медианы сырых показателей по странам с высокой эргономичностью (отличная + хорошая, верхние ~30%).
	 *
	 * @param array<string, array<string, float>> $raw_rows
	 * @param array<string, array<string, float>> $scores
	 * @return array<string, float>
	 */
	private static function benchmarks_from_high_ergo_countries( array $raw_rows, array $scores ): array {
		$ref_rows = array();
		$good_min = INF;

		if ( class_exists( 'WSErgo_Tier_Classifier' ) ) {
			$thr      = WSErgo_Tier_Classifier::get_thresholds();
			$good_min = (float) ( $thr['good_min'] ?? INF );
		}

		foreach ( $raw_rows as $iso3 => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$e = isset( $scores[ $iso3 ]['E'] ) ? (float) $scores[ $iso3 ]['E'] : 0.0;
			if ( $e >= $good_min && $good_min < INF ) {
				$ref_rows[] = $row;
			}
		}

		if ( count( $ref_rows ) < 5 ) {
			return self::percentiles_by_signal( $raw_rows, 0.75 );
		}

		return self::medians_by_signal( $ref_rows );
	}

	/**
	 * @param list<array<string, float>> $rows
	 * @return array<string, float>
	 */
	private static function medians_by_signal( array $rows ): array {
		$cols = array();
		foreach ( $rows as $row ) {
			foreach ( $row as $sig => $v ) {
				$sig = sanitize_key( (string) $sig );
				if ( $sig === '' || ! is_numeric( $v ) || ! is_finite( (float) $v ) ) {
					continue;
				}
				if ( ! isset( $cols[ $sig ] ) ) {
					$cols[ $sig ] = array();
				}
				$cols[ $sig ][] = (float) $v;
			}
		}
		$out = array();
		foreach ( $cols as $sig => $vals ) {
			if ( count( $vals ) < 3 ) {
				continue;
			}
			sort( $vals, SORT_NUMERIC );
			$out[ $sig ] = self::percentile_sorted( $vals, 0.5 );
		}
		return $out;
	}

	/**
	 * @param array<string, array<string, float>> $raw_rows
	 * @return array<string, float>
	 */
	private static function percentiles_by_signal( array $raw_rows, float $p ): array {
		$cols = array();
		foreach ( $raw_rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			foreach ( $row as $sig => $v ) {
				$sig = sanitize_key( (string) $sig );
				if ( $sig === '' || ! is_numeric( $v ) || ! is_finite( (float) $v ) ) {
					continue;
				}
				if ( ! isset( $cols[ $sig ] ) ) {
					$cols[ $sig ] = array();
				}
				$cols[ $sig ][] = (float) $v;
			}
		}
		$out = array();
		foreach ( $cols as $sig => $vals ) {
			if ( count( $vals ) < 3 ) {
				continue;
			}
			sort( $vals, SORT_NUMERIC );
			$out[ $sig ] = self::percentile_sorted( $vals, $p );
		}
		return $out;
	}

	/**
	 * @return array<string, string>
	 */
	private static function axis_labels_map(): array {
		return array(
			'F'  => __( 'Функциональность F', 'worldstat-ergonomics' ),
			'Cm' => __( 'Комфортность Cm', 'worldstat-ergonomics' ),
			'H'  => __( 'Обитаемость H', 'worldstat-ergonomics' ),
			'A'  => __( 'Освояемость A', 'worldstat-ergonomics' ),
			'S'  => __( 'Безопасность S', 'worldstat-ergonomics' ),
			'Ct' => __( 'Управляемость Ct', 'worldstat-ergonomics' ),
		);
	}

	/**
	 * @param list<float> $sorted
	 */
	private static function percentile_sorted( array $sorted, float $p ): float {
		$n = count( $sorted );
		if ( $n < 1 ) {
			return NAN;
		}
		if ( $n === 1 ) {
			return $sorted[0];
		}
		$p   = max( 0.0, min( 1.0, $p ) );
		$idx = $p * ( $n - 1 );
		$lo  = (int) floor( $idx );
		$hi  = (int) ceil( $idx );
		if ( $lo === $hi ) {
			return $sorted[ $lo ];
		}
		$frac = $idx - $lo;
		return $sorted[ $lo ] * ( 1.0 - $frac ) + $sorted[ $hi ] * $frac;
	}

	private static function hint_for_signal( bool $invert ): string {
		if ( $invert ) {
			return __( 'Значение хуже, чем у стран с высокой эргономичностью; в модели показатель инвертирован (меньше — лучше).', 'worldstat-ergonomics' );
		}
		return __( 'Значение ниже, чем у стран с высокой эргономичностью (группы «отличная» и «хорошая»).', 'worldstat-ergonomics' );
	}
}
