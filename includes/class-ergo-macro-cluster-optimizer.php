<?php
/**
 * Автоподбор признаков k-means и числа кластеров по макроданным CSV.
 *
 * @package WorldStatErgonomics
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_Macro_Cluster_Optimizer {

	private const MIN_FEATURES = 3;
	private const MAX_FEATURES = 8;
	private const MIN_COVERAGE = 0.45;
	private const MAX_CORR     = 0.85;

	/**
	 * @return array{
	 *   ok:bool,
	 *   features:list<string>,
	 *   k:int,
	 *   message:string,
	 *   feature_report:list<array{signal:string,label:string,cv:float,coverage:float,score:float,selected:bool}>
	 * }
	 */
	public static function auto_tune( string $scope = 'country' ): array {
		$scope = ( 'city' === $scope ) ? 'city' : 'country';
		if ( ! class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Калькулятор макроданных недоступен.', 'worldstat-ergonomics' ),
			);
		}

		$bundle = ( 'city' === $scope )
			? WSErgo_Country_Macro_Calculator::get_city_full_bundle()
			: WSErgo_Country_Macro_Calculator::get_full_bundle();

		$rows = isset( $bundle['raw_rows'] ) && is_array( $bundle['raw_rows'] ) ? $bundle['raw_rows'] : array();
		$n    = count( $rows );
		if ( $n < 8 ) {
			return array(
				'ok'      => false,
				'message' => __( 'Недостаточно стран с данными в CSV для автоподбора (нужно ≥8).', 'worldstat-ergonomics' ),
			);
		}

		$allowlist = ( 'city' === $scope && class_exists( 'WSErgo_Settings' ) )
			? WSErgo_Settings::macro_signal_allowlist_city()
			: WSErgo_Country_Macro_Calculator::get_cached_macro_signal_allowlist();

		$defaults = WSErgo_Country_Macro_Calculator::default_cluster_features();
		$candidates = array_values( array_unique( array_merge( $defaults, $allowlist ) ) );

		$scored = self::score_features( $rows, $candidates );
		if ( count( $scored ) < 2 ) {
			$selected = array_slice( $defaults, 0, min( self::MAX_FEATURES, count( $defaults ) ) );
		} else {
			$selected = self::select_uncorrelated_features( $rows, $scored );
		}

		if ( count( $selected ) < 2 ) {
			$selected = array_slice( array_keys( $scored ), 0, min( self::MAX_FEATURES, count( $scored ) ) );
		}
		if ( count( $selected ) < 2 ) {
			$selected = $defaults;
		}

		$matrix = self::build_matrix( $rows, $selected );
		if ( count( $matrix ) < 8 ) {
			return array(
				'ok'      => false,
				'message' => __( 'Слишком много пропусков в выбранных признаках.', 'worldstat-ergonomics' ),
			);
		}

		$scaled = self::standard_scale_rows( $matrix );
		$k      = self::optimal_k_elbow( $scaled );

		$report = self::build_feature_report( $scored, $selected );

		return array(
			'ok'             => true,
			'features'       => array_values( $selected ),
			'k'              => $k,
			'message'        => sprintf(
				/* translators: 1: feature count, 2: k */
				__( 'Подобрано %1$d признаков и k = %2$d по %3$d странам.', 'worldstat-ergonomics' ),
				count( $selected ),
				$k,
				count( $matrix )
			),
			'feature_report' => $report,
			'countries_used' => count( $matrix ),
		);
	}

	/**
	 * @param array<string, array<string, float>> $rows
	 * @param list<string>                        $candidates
	 * @return array<string, array{cv:float,coverage:float,std:float,score:float}>
	 */
	private static function score_features( array $rows, array $candidates ): array {
		$n      = count( $rows );
		$scored = array();
		foreach ( $candidates as $sig ) {
			$sig = sanitize_key( (string) $sig );
			if ( $sig === '' ) {
				continue;
			}
			$vals = array();
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) || ! isset( $row[ $sig ] ) ) {
					continue;
				}
				$v = (float) $row[ $sig ];
				if ( is_finite( $v ) ) {
					$vals[] = $v;
				}
			}
			$cnt = count( $vals );
			if ( $cnt < max( 5, (int) ceil( $n * self::MIN_COVERAGE ) ) ) {
				continue;
			}
			$coverage = $cnt / (float) $n;
			$mean     = array_sum( $vals ) / $cnt;
			$var      = 0.0;
			foreach ( $vals as $v ) {
				$var += ( $v - $mean ) ** 2;
			}
			$var /= $cnt;
			$std  = sqrt( max( $var, 0.0 ) );
			if ( $std < 1e-12 ) {
				continue;
			}
			$cv    = $std / max( abs( $mean ), 1e-9 );
			$score = $cv * $coverage;
			$scored[ $sig ] = array(
				'cv'       => $cv,
				'coverage' => $coverage,
				'std'      => $std,
				'score'    => $score,
			);
		}
		uasort(
			$scored,
			static function ( $a, $b ) {
				return ( $b['score'] <=> $a['score'] );
			}
		);
		return $scored;
	}

	/**
	 * @param array<string, array<string, float>>     $rows
	 * @param array<string, array{cv:float,...}> $scored
	 * @return list<string>
	 */
	private static function select_uncorrelated_features( array $rows, array $scored ): array {
		$selected = array();
		foreach ( array_keys( $scored ) as $sig ) {
			if ( count( $selected ) >= self::MAX_FEATURES ) {
				break;
			}
			$ok = true;
			foreach ( $selected as $prev ) {
				$c = self::pearson_correlation( $rows, $sig, $prev );
				if ( is_finite( $c ) && abs( $c ) > self::MAX_CORR ) {
					$ok = false;
					break;
				}
			}
			if ( $ok ) {
				$selected[] = $sig;
			}
		}
		if ( count( $selected ) < self::MIN_FEATURES ) {
			foreach ( array_keys( $scored ) as $sig ) {
				if ( in_array( $sig, $selected, true ) ) {
					continue;
				}
				$selected[] = $sig;
				if ( count( $selected ) >= self::MIN_FEATURES ) {
					break;
				}
			}
		}
		return $selected;
	}

	/**
	 * @param array<string, array<string, float>> $rows
	 * @param list<string>                        $features
	 * @return list<list<float>>
	 */
	private static function build_matrix( array $rows, array $features ): array {
		$matrix = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$vec = array();
			$ok  = true;
			foreach ( $features as $sig ) {
				if ( ! isset( $row[ $sig ] ) || ! is_finite( (float) $row[ $sig ] ) ) {
					$ok = false;
					break;
				}
				$vec[] = (float) $row[ $sig ];
			}
			if ( $ok ) {
				$matrix[] = $vec;
			}
		}
		return $matrix;
	}

	/**
	 * @param list<list<float>> $X
	 */
	private static function optimal_k_elbow( array $X ): int {
		$n     = count( $X );
		$k_min = 2;
		$k_max = min( 12, max( $k_min, (int) round( sqrt( $n ) ) ) );
		if ( $n <= $k_max ) {
			$k_max = max( $k_min, $n - 1 );
		}

		$wcss = array();
		for ( $k = $k_min; $k <= $k_max; $k++ ) {
			$labels     = self::kmeans( $X, $k, 45 );
			$wcss[ $k ] = self::within_cluster_ss( $X, $labels, $k );
		}

		$best_k  = min( 6, $k_max );
		$max_imp = 0.0;
		for ( $k = $k_min; $k < $k_max; $k++ ) {
			$imp = ( $wcss[ $k ] ?? 0.0 ) - ( $wcss[ $k + 1 ] ?? 0.0 );
			if ( $imp > $max_imp ) {
				$max_imp = $imp;
			}
		}
		if ( $max_imp < 1e-12 ) {
			return $best_k;
		}
		$thresh = $max_imp * 0.12;
		for ( $k = $k_min; $k < $k_max; $k++ ) {
			$imp = ( $wcss[ $k ] ?? 0.0 ) - ( $wcss[ $k + 1 ] ?? 0.0 );
			if ( $imp < $thresh ) {
				return max( $k_min, $k );
			}
		}
		return $best_k;
	}

	/**
	 * @param list<list<float>> $X
	 * @param list<int>         $labels
	 */
	private static function within_cluster_ss( array $X, array $labels, int $k ): float {
		$n = count( $X );
		$d = count( $X[0] ?? array() );
		if ( $n < 1 || $d < 1 ) {
			return 0.0;
		}
		$sums   = array_fill( 0, $k, array_fill( 0, $d, 0.0 ) );
		$counts = array_fill( 0, $k, 0 );
		for ( $i = 0; $i < $n; $i++ ) {
			$c = (int) ( $labels[ $i ] ?? 0 );
			if ( $c < 0 || $c >= $k ) {
				continue;
			}
			++$counts[ $c ];
			for ( $j = 0; $j < $d; $j++ ) {
				$sums[ $c ][ $j ] += $X[ $i ][ $j ];
			}
		}
		$wcss = 0.0;
		for ( $i = 0; $i < $n; $i++ ) {
			$c = (int) ( $labels[ $i ] ?? 0 );
			if ( $counts[ $c ] < 1 ) {
				continue;
			}
			for ( $j = 0; $j < $d; $j++ ) {
				$cent = $sums[ $c ][ $j ] / $counts[ $c ];
				$diff = $X[ $i ][ $j ] - $cent;
				$wcss += $diff * $diff;
			}
		}
		return $wcss;
	}

	/**
	 * @param array<string, array{cv:float,coverage:float,std:float,score:float}> $scored
	 * @param list<string>                                                          $selected
	 * @return list<array{signal:string,label:string,cv:float,coverage:float,score:float,selected:bool}>
	 */
	private static function build_feature_report( array $scored, array $selected ): array {
		$sel_flip = array_flip( $selected );
		$report   = array();
		$i        = 0;
		foreach ( $scored as $sig => $meta ) {
			if ( $i >= 20 ) {
				break;
			}
			$report[] = array(
				'signal'    => $sig,
				'label'     => class_exists( 'WSErgo_Country_Macro_Calculator' )
					? WSErgo_Country_Macro_Calculator::data_label_ru( $sig )
					: $sig,
				'cv'        => round( (float) $meta['cv'], 3 ),
				'coverage'  => round( (float) $meta['coverage'] * 100.0, 1 ),
				'score'     => round( (float) $meta['score'], 3 ),
				'selected'  => isset( $sel_flip[ $sig ] ),
			);
			++$i;
		}
		return $report;
	}

	/**
	 * @param array<string, array<string, float>> $rows
	 */
	private static function pearson_correlation( array $rows, string $sig_a, string $sig_b ): float {
		$xs = array();
		$ys = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( ! isset( $row[ $sig_a ], $row[ $sig_b ] ) ) {
				continue;
			}
			$x = (float) $row[ $sig_a ];
			$y = (float) $row[ $sig_b ];
			if ( ! is_finite( $x ) || ! is_finite( $y ) ) {
				continue;
			}
			$xs[] = $x;
			$ys[] = $y;
		}
		$n = count( $xs );
		if ( $n < 5 ) {
			return 0.0;
		}
		$mx = array_sum( $xs ) / $n;
		$my = array_sum( $ys ) / $n;
		$num = 0.0;
		$dx  = 0.0;
		$dy  = 0.0;
		for ( $i = 0; $i < $n; $i++ ) {
			$ax = $xs[ $i ] - $mx;
			$ay = $ys[ $i ] - $my;
			$num += $ax * $ay;
			$dx  += $ax * $ax;
			$dy  += $ay * $ay;
		}
		$den = sqrt( max( $dx * $dy, 1e-18 ) );
		return $num / $den;
	}

	/**
	 * @param list<list<float>> $matrix
	 * @return list<list<float>>
	 */
	private static function standard_scale_rows( array $matrix ): array {
		$n = count( $matrix );
		$d = count( $matrix[0] ?? array() );
		if ( $n < 1 || $d < 1 ) {
			return $matrix;
		}
		$mean = array_fill( 0, $d, 0.0 );
		$std  = array_fill( 0, $d, 1.0 );
		for ( $j = 0; $j < $d; $j++ ) {
			$col = array();
			for ( $i = 0; $i < $n; $i++ ) {
				$col[] = $matrix[ $i ][ $j ];
			}
			$mean[ $j ] = array_sum( $col ) / $n;
			$v          = 0.0;
			foreach ( $col as $v_i ) {
				$v += ( $v_i - $mean[ $j ] ) ** 2;
			}
			$v         /= $n;
			$std[ $j ]  = sqrt( max( $v, 1e-12 ) );
		}
		$out = array();
		for ( $i = 0; $i < $n; $i++ ) {
			$row = array();
			for ( $j = 0; $j < $d; $j++ ) {
				$row[] = ( $matrix[ $i ][ $j ] - $mean[ $j ] ) / $std[ $j ];
			}
			$out[] = $row;
		}
		return $out;
	}

	/**
	 * @param list<list<float>> $X
	 * @return list<int>
	 */
	private static function kmeans( array $X, int $k, int $max_iter ): array {
		$n = count( $X );
		if ( $n < 1 ) {
			return array();
		}
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
					$dist = 0.0;
					for ( $j = 0; $j < $d; $j++ ) {
						$diff = $X[ $i ][ $j ] - $centroids[ $c ][ $j ];
						$dist += $diff * $diff;
					}
					if ( $dist < $best_d ) {
						$best_d = $dist;
						$best_c = $c;
					}
				}
				if ( $labels[ $i ] !== $best_c ) {
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
	 * Сохранить подобранные значения в опции WordPress.
	 *
	 * @param list<string> $features
	 */
	public static function apply_to_options( array $features, int $k, string $scope = 'country' ): void {
		if ( ! class_exists( 'WSErgo_Settings' ) ) {
			return;
		}
		$scope = ( 'city' === $scope ) ? 'city' : 'country';
		$clean = array();
		$allow = ( 'city' === $scope )
			? array_flip( WSErgo_Settings::macro_signal_allowlist_city() )
			: array_flip( WSErgo_Country_Macro_Calculator::get_cached_macro_signal_allowlist() );
		foreach ( $features as $f ) {
			$f = sanitize_key( (string) $f );
			if ( $f !== '' && isset( $allow[ $f ] ) ) {
				$clean[] = $f;
			}
		}
		if ( count( $clean ) < 2 ) {
			return;
		}
		$k = max( 2, min( 12, $k ) );
		if ( 'city' === $scope ) {
			update_option( WSErgo_Settings::OPTION_CITY_MACRO_CLUSTER_FEATURES, $clean );
			update_option( WSErgo_Settings::OPTION_CITY_MACRO_K_CLUSTERS, $k );
		} else {
			update_option( WSErgo_Settings::OPTION_MACRO_CLUSTER_FEATURES, $clean );
			update_option( WSErgo_Settings::OPTION_MACRO_K_CLUSTERS, $k );
		}
		if ( class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			WSErgo_Country_Macro_Calculator::flush_cache();
		}
	}
}
