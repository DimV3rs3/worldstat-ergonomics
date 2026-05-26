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

	/** Минимум признаков после автоподбора (2D + большое k даёт одиночки). */
	private const MIN_FEATURES    = 4;
	private const MAX_FEATURES    = 8;
	/** Минимальная доля стран с числом по признаку (для отбора кандидатов). */
	private const MIN_COVERAGE    = 0.20;
	private const MAX_CORR        = 0.85;
	private const MIN_COUNTRIES   = 3;
	private const IDEAL_COUNTRIES = 8;
	/** Перезапуски k-means на финальном шаге автоподбора. */
	private const KMEANS_RESTARTS_FINAL = 3;
	/** Перезапуски при переборе признаков / k (быстрая оценка без силуэта). */
	private const KMEANS_RESTARTS_PROBE = 2;
	/** Сколько топ-признаков по CV участвуют в переборе. */
	private const TUNE_TOP_CANDIDATES = 28;
	/** Доля стран в одном кластере, выше — сильный штраф при автоподборе. */
	private const MAX_DOMINANT_CLUSTER_SHARE = 0.60;
	/** Суммарная доля двух крупнейших кластеров, выше — штраф («два мешка»). */
	private const MAX_TOP2_CLUSTER_SHARE = 0.72;
	/** Минимальный размер «нормального» кластера (доля от n, не меньше 3 стран). */
	private const MIN_CLUSTER_SIZE_FRAC = 0.025;
	/** Жёсткий минимум размера кластера (чтобы не было кластеров < 10 стран). */
	private const MIN_CLUSTER_SIZE_ABS = 10;
	/** Жёсткий минимум k для автоподбора (чтобы не было «2 мешка»). */
	private const MIN_K_AUTOTUNE = 4;

	/** @var array<string, list<list<float>>> Кэш стандартизованных матриц по набору признаков. */
	private static $scaled_matrix_cache = array();

	/**
	 * Быстрый поиск валидной пары (features,k) под жёсткие ограничения.
	 *
	 * @param array<string, array<string, float>> $rows
	 * @param array<string, array{cv:float,coverage:float,std:float,score:float}> $scored
	 * @return array{features:list<string>,k:int,quality:float}|null
	 */
	private static function search_best_valid_solution_fast( array $rows, array $scored ): ?array {
		$start_ts = microtime( true );
		$time_budget_s = 6.5;

		$pool = array_slice( array_keys( $scored ), 0, max( self::TUNE_TOP_CANDIDATES, self::MIN_FEATURES ) );
		$pool = array_values( array_unique( array_map( 'sanitize_key', $pool ) ) );
		if ( count( $pool ) < self::MIN_FEATURES ) {
			return null;
		}

		$candidates = array();
		$seed = self::select_features_for_separation( $rows, $scored );
		if ( count( $seed ) >= self::MIN_FEATURES ) {
			$candidates[] = $seed;
		}
		$alt = self::select_uncorrelated_features( $rows, $scored );
		if ( count( $alt ) >= self::MIN_FEATURES ) {
			$candidates[] = $alt;
		}

		$tries = 120;
		for ( $t = 0; $t < $tries; $t++ ) {
			if ( microtime( true ) - $start_ts > $time_budget_s ) {
				break;
			}
			$want = random_int( self::MIN_FEATURES, min( self::MAX_FEATURES, count( $pool ) ) );
			$tmp  = $pool;
			shuffle( $tmp );
			$set = array_slice( $tmp, 0, $want );
			sort( $set );
			$candidates[] = $set;
		}

		$best   = null;
		$best_q = -INF;

		foreach ( $candidates as $features ) {
			if ( microtime( true ) - $start_ts > $time_budget_s ) {
				break;
			}
			$d = count( $features );
			if ( $d < self::MIN_FEATURES ) {
				continue;
			}
			$X = self::get_scaled_matrix_for_features( $rows, $features );
			if ( null === $X ) {
				continue;
			}
			$n = count( $X );
			$k_target = self::MIN_K_AUTOTUNE; // сейчас жёстко хотим k=4 как базовый сценарий.
			$k_max    = self::max_k_allowed( $n, $d );
			$k_list   = array( $k_target );
			for ( $kk = $k_target + 1; $kk <= $k_max; $kk++ ) {
				$k_list[] = $kk;
			}

			foreach ( $k_list as $k ) {
				if ( microtime( true ) - $start_ts > $time_budget_s ) {
					break;
				}
				$labels = self::kmeans_best_labels( $X, $k, 1, true, $d );
				if ( empty( $labels ) ) {
					continue;
				}
				$q = self::cluster_quality_score_fast( $X, $labels, $k, $d );
				if ( ! is_finite( $q ) ) {
					continue;
				}

				$counts = self::cluster_counts( $labels, $k );
				$max_c  = (int) max( $counts );
				$min_c  = (int) min( $counts );
				$balance = $min_c / max( 1, $max_c );
				$max_share = $max_c / (float) max( 1, $n );

				// Усиливаем цель «примерно равномерно», но не стерильно:
				// - стимулируем баланс,
				// - слегка штрафуем за слишком «стерильную» равномерность только когда fit плохой.
				$q += $balance * 0.18 + ( 1.0 - $max_share ) * 0.10;
				$q += max( 0, $d - self::MIN_FEATURES ) * 0.012;

				if ( $q > $best_q ) {
					$best_q = $q;
					$best   = array(
						'features' => array_values( $features ),
						'k'        => (int) $k,
						'quality'  => $q,
					);
				}
			}
		}

		return $best;
	}

	/**
	 * Проверка жёстких ограничений на форму разбиения.
	 *
	 * @param list<int> $counts
	 */
	private static function is_partition_valid( array $counts, int $n, int $k ): bool {
		if ( $n < 1 || $k < 2 ) {
			return false;
		}
		$non_empty = 0;
		foreach ( $counts as $c ) {
			if ( (int) $c > 0 ) {
				++$non_empty;
			}
		}
		if ( $non_empty < $k ) {
			return false;
		}
		$max_c = (int) max( $counts );
		$min_c = (int) min( $counts );
		if ( $min_c < self::MIN_CLUSTER_SIZE_ABS ) {
			return false;
		}
		$max_share = $max_c / (float) $n;
		if ( $max_share > self::MAX_DOMINANT_CLUSTER_SHARE ) {
			return false;
		}
		return true;
	}

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
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 90 );
		}
		self::clear_tune_cache();

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

		$allowlist = WSErgo_Country_Macro_Calculator::macro_cluster_signal_allowlist( $scope );
		if ( count( $allowlist ) < 2 ) {
			return array(
				'ok'      => false,
				'message' => __( 'Нет параметров из CSV для автоподбора. Загрузите данные в World Statistics и обновите страницу.', 'worldstat-ergonomics' ),
			);
		}

		if ( $n < self::MIN_COUNTRIES ) {
			return array(
				'ok'      => false,
				'message' => sprintf(
					/* translators: %d: minimum country count */
					__( 'Недостаточно стран с данными в CSV для автоподбора (нужно ≥%d).', 'worldstat-ergonomics' ),
					self::MIN_COUNTRIES
				),
			);
		}

		$partial_tune = $n < self::IDEAL_COUNTRIES;
		$candidates   = array_values( array_unique( array_map( 'sanitize_key', $allowlist ) ) );

		$scored = self::score_features( $rows, $candidates );
		if ( count( $scored ) < 2 && class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			$fallback = WSErgo_Country_Macro_Calculator::fallback_cluster_features_from_rows( $rows, $candidates );
			if ( count( $fallback ) >= 2 ) {
				$scored = self::score_features( $rows, $fallback );
				if ( count( $scored ) < 2 ) {
					foreach ( $fallback as $sig ) {
						if ( ! isset( $scored[ $sig ] ) ) {
							$scored[ $sig ] = array(
								'cv'       => 0.0,
								'coverage' => 0.25,
								'std'      => 1.0,
								'score'    => 0.0,
							);
						}
					}
				}
			}
		}
		if ( count( $scored ) < 2 ) {
			return array(
				'ok'      => false,
				'message' => __( 'В загруженных CSV нет достаточного числа числовых показателей для автоподбора. Проверьте опорный год и состав файлов.', 'worldstat-ergonomics' ),
			);
		}

		$solution = self::search_best_valid_solution_fast( $rows, $scored );
		if ( null === $solution ) {
			return array(
				'ok'      => false,
				'message' => __( 'Автоподбор не нашёл разбиение, удовлетворяющее ограничениям (k ≥ 4, крупнейший кластер < 60%, каждый кластер ≥ 10 стран). Попробуйте другой год/набор признаков или отметьте признаки вручную.', 'worldstat-ergonomics' ),
			);
		}

		$selected = $solution['features'];
		$k        = (int) $solution['k'];

		$matrix   = self::build_matrix_imputed( $rows, $selected );
		$matrix_n = count( $matrix );
		if ( $matrix_n < self::MIN_COUNTRIES ) {
			$matrix   = self::build_matrix( $rows, $selected );
			$matrix_n = count( $matrix );
		}
		if ( $matrix_n < self::MIN_COUNTRIES ) {
			return array(
				'ok'      => false,
				'message' => __( 'Слишком много пропусков в выбранных признаках. Отметьте другие параметры вручную или дополните CSV.', 'worldstat-ergonomics' ),
			);
		}

		$scaled = self::standard_scale_rows( $matrix );
		$report = self::build_feature_report( $scored, $selected );

		$balance_note = '';
		$labels       = self::kmeans_best_labels( $scaled, $k, self::KMEANS_RESTARTS_FINAL, false, count( $selected ) );
		if ( empty( $labels ) ) {
			// Если для выбранного k не нашлось валидного разбиения — пробуем соседние k.
			$k_max_try = self::max_k_allowed( $matrix_n, count( $selected ) );
			for ( $try = self::MIN_K_AUTOTUNE; $try <= $k_max_try; $try++ ) {
				if ( $try === $k ) {
					continue;
				}
				$trial = self::kmeans_best_labels( $scaled, $try, self::KMEANS_RESTARTS_FINAL, false, count( $selected ) );
				if ( ! empty( $trial ) ) {
					$k      = $try;
					$labels = $trial;
					break;
				}
			}
		}
		if ( ! empty( $labels ) ) {
			$sil     = self::silhouette_avg( $scaled, $labels, $k );
			$balance = self::cluster_balance_ratio( $labels, $k );
			$counts = self::cluster_counts( $labels, $k );
			$shape  = self::partition_shape_stats( $counts, count( $labels ) );
			$balance_note = sprintf(
				/* translators: 1: silhouette, 2: balance %%, 3: effective clusters, 4: top-2 share %% */
				__( ' Наглядность: силуэт %1$s, равномерность %2$s%%, содержательных кластеров %3$d, две крупнейшие — %4$s%% стран.', 'worldstat-ergonomics' ),
				(string) round( $sil, 2 ),
				(string) round( $balance * 100.0, 0 ),
				(int) $shape['effective'],
				(string) round( $shape['top2_share'] * 100.0, 0 )
			);
		}

		$message = sprintf(
			/* translators: 1: feature count, 2: k, 3: country count */
			__( 'Подобрано %1$d признаков и k = %2$d по %3$d странам (признаки и k выбраны для наиболее различимых кластеров на карте/графике; ограничения: ≤60%% стран в крупнейшем кластере и ≥10 стран в каждом кластере).', 'worldstat-ergonomics' ),
			count( $selected ),
			$k,
			$matrix_n
		) . $balance_note;
		if ( empty( $labels ) ) {
			$message .= ' ' . __( 'Предупреждение: на финальном шаге k-means не удалось воспроизвести валидное разбиение (редко). Нажмите «Автоподбор» ещё раз.', 'worldstat-ergonomics' );
		}
		if ( $partial_tune ) {
			$message .= ' ' . sprintf(
				/* translators: %d: recommended minimum country count */
				__( '(Для надёжного подбора желательно ≥%d стран.)', 'worldstat-ergonomics' ),
				self::IDEAL_COUNTRIES
			);
		}

		return array(
			'ok'             => true,
			'features'       => array_values( $selected ),
			'k'              => $k,
			'message'        => $message,
			'feature_report' => $report,
			'countries_used' => $matrix_n,
			'partial'        => $partial_tune,
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
			$min_cnt = self::min_values_required_for_feature( $n );
			if ( $cnt < $min_cnt ) {
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
	 * Жадный подбор признаков: максимизируем различимость кластеров (силуэт), а не только CV.
	 *
	 * @param array<string, array<string, float>>                    $rows
	 * @param array<string, array{cv:float,coverage:float,...}> $scored
	 * @return list<string>
	 */
	private static function clear_tune_cache(): void {
		self::$scaled_matrix_cache = array();
	}

	/**
	 * @param list<string> $features
	 * @return list<list<float>>|null
	 */
	private static function get_scaled_matrix_for_features( array $rows, array $features ): ?array {
		if ( count( $features ) < 2 ) {
			return null;
		}
		$sorted = array_values( $features );
		sort( $sorted, SORT_STRING );
		$key = implode( '|', $sorted );
		if ( isset( self::$scaled_matrix_cache[ $key ] ) ) {
			return self::$scaled_matrix_cache[ $key ];
		}
		$matrix = self::build_matrix_imputed( $rows, $features );
		if ( count( $matrix ) < self::MIN_COUNTRIES ) {
			$matrix = self::build_matrix( $rows, $features );
		}
		if ( count( $matrix ) < self::MIN_COUNTRIES ) {
			return null;
		}
		$X = self::standard_scale_rows( $matrix );
		self::$scaled_matrix_cache[ $key ] = $X;
		return $X;
	}

	/**
	 * Несколько значений k вокруг √n для быстрого перебора.
	 *
	 * @return list<int>
	 */
	/**
	 * Верхняя граница k: меньше при малом числе признаков (2D не тянет k=12).
	 */
	private static function max_k_allowed( int $n, int $num_features ): int {
		$d        = max( 2, $num_features );
		$sqrt_cap = max( 3, (int) floor( sqrt( max( 4, $n ) ) / 1.35 ) );
		$dim_cap  = 1 + (int) floor( 1.6 * $d );
		$size_cap = (int) floor( $n / max( 1, self::MIN_CLUSTER_SIZE_ABS ) );
		return max( self::MIN_K_AUTOTUNE, min( 8, $sqrt_cap, $dim_cap, max( self::MIN_K_AUTOTUNE, $n - 1 ), max( self::MIN_K_AUTOTUNE, $size_cap ) ) );
	}

	/**
	 * @return list<int>
	 */
	private static function probe_k_values( int $n, int $num_features ): array {
		$k_max = self::max_k_allowed( $n, $num_features );
		$mid   = max( self::MIN_K_AUTOTUNE, (int) round( sqrt( max( 4, $n ) ) / 1.5 ) );
		$want  = array( $mid - 1, $mid, $mid + 1 );
		$out   = array();
		foreach ( $want as $k ) {
			if ( $k >= self::MIN_K_AUTOTUNE && $k <= $k_max ) {
				$out[ $k ] = true;
			}
		}
		if ( empty( $out ) ) {
			$out[ min( $k_max, max( self::MIN_K_AUTOTUNE, $mid ) ) ] = true;
		}
		return array_keys( $out );
	}

	/**
	 * Добрать некоррелированные признаки до MIN_FEATURES.
	 *
	 * @param array<string, array{cv:float,...}> $scored
	 * @param list<string>                     $selected
	 * @return list<string>
	 */
	private static function ensure_min_features( array $rows, array $scored, array $selected ): array {
		$selected = array_values( array_unique( array_map( 'sanitize_key', $selected ) ) );
		if ( count( $selected ) >= self::MIN_FEATURES ) {
			return $selected;
		}
		foreach ( array_keys( $scored ) as $sig ) {
			if ( in_array( $sig, $selected, true ) ) {
				continue;
			}
			$ok = true;
			foreach ( $selected as $prev ) {
				$r = self::pearson_correlation( $rows, $sig, $prev );
				if ( is_finite( $r ) && abs( $r ) > self::MAX_CORR ) {
					$ok = false;
					break;
				}
			}
			if ( $ok ) {
				$selected[] = $sig;
			}
			if ( count( $selected ) >= self::MIN_FEATURES ) {
				break;
			}
		}
		return $selected;
	}

	/**
	 * Стартовый набор: топ по CV, попарно некоррелированные.
	 *
	 * @param list<string> $pool
	 * @return list<string>
	 */
	private static function seed_uncorrelated_from_pool( array $rows, array $pool, int $target ): array {
		$selected = array();
		foreach ( $pool as $sig ) {
			if ( count( $selected ) >= $target ) {
				break;
			}
			$ok = true;
			foreach ( $selected as $prev ) {
				$r = self::pearson_correlation( $rows, $sig, $prev );
				if ( is_finite( $r ) && abs( $r ) > self::MAX_CORR ) {
					$ok = false;
					break;
				}
			}
			if ( $ok ) {
				$selected[] = $sig;
			}
		}
		return $selected;
	}

	/**
	 * @param list<int> $counts
	 * @return array{micro:int,singletons:int,min_size:int,effective:int,top2_share:float}
	 */
	private static function partition_shape_stats( array $counts, int $n ): array {
		$n        = max( 1, $n );
		$min_size = max( self::MIN_CLUSTER_SIZE_ABS, (int) ceil( $n * self::MIN_CLUSTER_SIZE_FRAC ) );
		$micro    = 0;
		$singletons = 0;
		$effective  = 0;
		$sorted     = $counts;
		rsort( $sorted, SORT_NUMERIC );
		foreach ( $counts as $c ) {
			if ( $c <= 0 ) {
				continue;
			}
			if ( $c === 1 ) {
				++$singletons;
			}
			if ( $c < $min_size ) {
				++$micro;
			} else {
				++$effective;
			}
		}
		$top2 = ( ( $sorted[0] ?? 0 ) + ( $sorted[1] ?? 0 ) ) / (float) $n;
		return array(
			'micro'       => $micro,
			'singletons'  => $singletons,
			'min_size'    => $min_size,
			'effective'   => $effective,
			'top2_share'  => $top2,
		);
	}

	/**
	 * Штраф за «два мешка», одиночки и слишком большое k относительно числа признаков.
	 *
	 * @param list<int> $counts
	 */
	private static function partition_shape_penalty( array $counts, int $n, int $k, int $num_features ): float {
		$shape = self::partition_shape_stats( $counts, $n );
		$p     = 0.0;

		if ( $shape['top2_share'] > self::MAX_TOP2_CLUSTER_SHARE ) {
			$p += ( $shape['top2_share'] - self::MAX_TOP2_CLUSTER_SHARE ) * 3.5;
		}
		$p += $shape['singletons'] * 0.1;
		if ( $shape['micro'] >= 2 ) {
			$p += 0.12 * ( $shape['micro'] - 1 );
		}
		if ( $k >= 5 && $shape['singletons'] > 0 ) {
			$p += 0.18;
		}
		$want_effective = max( 2, min( $k, (int) floor( sqrt( max( 4, $n ) ) / 2 ) ) );
		if ( $shape['effective'] < $want_effective && $k > $shape['effective'] + 1 ) {
			$p += 0.15 * ( $k - $shape['effective'] );
		}
		$k_cap = self::max_k_allowed( $n, $num_features );
		if ( $k > $k_cap ) {
			$p += 0.25 * ( $k - $k_cap );
		}

		return $p;
	}

	private static function select_features_for_separation( array $rows, array $scored ): array {
		$candidates = array_keys( $scored );
		if ( count( $candidates ) < 2 ) {
			return array();
		}

		$top_n = min( self::TUNE_TOP_CANDIDATES, count( $candidates ) );
		$pool  = array_slice( $candidates, 0, $top_n );

		$seed_target = min( self::MIN_FEATURES, count( $pool ), self::MAX_FEATURES );
		$selected    = self::seed_uncorrelated_from_pool( $rows, $pool, max( 2, $seed_target ) );
		if ( count( $selected ) < 2 ) {
			return array();
		}

		$best_global = self::evaluate_feature_set_quality_fast( $rows, $selected );

		$improved = true;
		while ( $improved && count( $selected ) < self::MAX_FEATURES ) {
			$improved = false;
			$best_add = null;
			$best_q   = $best_global;
			$margin   = count( $selected ) < self::MIN_FEATURES ? 0.0 : 0.008;

			foreach ( $pool as $sig ) {
				if ( in_array( $sig, $selected, true ) ) {
					continue;
				}
				$ok_corr = true;
				foreach ( $selected as $prev ) {
					$r = self::pearson_correlation( $rows, $sig, $prev );
					if ( is_finite( $r ) && abs( $r ) > self::MAX_CORR ) {
						$ok_corr = false;
						break;
					}
				}
				if ( ! $ok_corr ) {
					continue;
				}

				$trial = array_merge( $selected, array( $sig ) );
				$q     = self::evaluate_feature_set_quality_fast( $rows, $trial );
				if ( $q > $best_q + $margin ) {
					$best_q   = $q;
					$best_add = $sig;
				}
			}

			if ( null !== $best_add ) {
				$selected[]  = $best_add;
				$best_global = $best_q;
				$improved    = true;
			} elseif ( count( $selected ) < self::MIN_FEATURES ) {
				$selected = self::ensure_min_features( $rows, $scored, $selected );
				break;
			}
		}

		return self::ensure_min_features( $rows, $scored, $selected );
	}

	/**
	 * Быстрая оценка набора признаков (без O(n²) силуэта).
	 *
	 * @param array<string, array<string, float>> $rows
	 * @param list<string>                        $features
	 */
	private static function evaluate_feature_set_quality_fast( array $rows, array $features ): float {
		$X = self::get_scaled_matrix_for_features( $rows, $features );
		if ( null === $X ) {
			return -1.0;
		}
		$n    = count( $X );
		$d    = count( $features );
		$best = -1.0;
		foreach ( self::probe_k_values( $n, $d ) as $k ) {
			$labels = self::kmeans_best_labels( $X, $k, self::KMEANS_RESTARTS_PROBE, true, $d );
			$q      = self::cluster_quality_score_fast( $X, $labels, $k, $d );
			if ( $q > $best ) {
				$best = $q;
			}
		}
		$dim_bonus = max( 0, $d - self::MIN_FEATURES ) * 0.012;
		return $best + $dim_bonus;
	}

	/**
	 * @param list<list<float>> $X
	 * @return list<int>
	 */
	private static function kmeans_best_labels( array $X, int $k, int $restarts, bool $use_fast_score, int $num_features = 0 ): array {
		$n = count( $X );
		if ( $n < 1 ) {
			return array();
		}
		$k            = max( 1, min( $k, $n ) );
		$restarts     = max( 1, $restarts );
		$num_features = max( 2, $num_features > 0 ? $num_features : count( $X[0] ?? array() ) );
		$best_labels  = null;
		$best_quality = -INF;

		for ( $r = 0; $r < $restarts; $r++ ) {
			$labels = class_exists( 'WSErgo_Country_Macro_Calculator' )
				? WSErgo_Country_Macro_Calculator::kmeans_labels_for_matrix( $X, $k, 50 )
				: self::kmeans_legacy( $X, $k, 50 );
			$counts = self::cluster_counts( $labels, $k );
			if ( ! self::is_partition_valid( $counts, $n, $k ) ) {
				continue;
			}
			$q      = $use_fast_score
				? self::cluster_quality_score_fast( $X, $labels, $k, $num_features )
				: self::cluster_quality_score( $X, $labels, $k, $num_features );
			if ( $q > $best_quality ) {
				$best_quality = $q;
				$best_labels  = $labels;
			}
		}

		if ( null === $best_labels ) {
			// Нет ни одного разбиения, удовлетворяющего жёстким ограничениям.
			return array();
		}
		return $best_labels;
	}

	/**
	 * Быстрая оценка без силуэта (WCSS + доля крупнейшего кластера).
	 *
	 * @param list<list<float>> $X
	 * @param list<int>         $labels
	 */
	private static function cluster_quality_score_fast( array $X, array $labels, int $k, int $num_features = 0 ): float {
		$n = count( $X );
		if ( $n < $k + 1 || $k < 2 ) {
			return -1.0;
		}

		$num_features = max( 2, $num_features > 0 ? $num_features : count( $X[0] ?? array() ) );
		$counts       = self::cluster_counts( $labels, $k );
		$max_c        = max( $counts );
		$min_c        = min( $counts );
		$max_share    = $max_c / (float) $n;
		$balance      = $min_c / max( 1, $max_c );
		$shape        = self::partition_shape_stats( $counts, $n );

		if ( ! self::is_partition_valid( $counts, $n, $k ) ) {
			return -INF;
		}

		if ( $max_share > 0.85 ) {
			return -1.0 + ( 1.0 - $max_share );
		}

		$wcss = self::within_cluster_ss( $X, $labels, $k );
		$fit  = 1.0 / ( 1.0 + $wcss / max( 1.0, (float) $n ) );

		$penalty = self::partition_shape_penalty( $counts, $n, $k, $num_features );

		$effective_bonus = min( 0.12, $shape['effective'] / max( 1.0, (float) $k ) * 0.12 );

		return max( 0.0, $fit * 0.5 + ( 1.0 - $max_share ) * 0.22 + $balance * 0.13 + $effective_bonus - $penalty );
	}

	/**
	 * Сводная оценка разбиения для автоподбора (выше = нагляднее на карте).
	 *
	 * @param list<list<float>> $X
	 * @param list<int>         $labels
	 */
	private static function cluster_quality_score( array $X, array $labels, int $k, int $num_features = 0 ): float {
		$n = count( $X );
		if ( $n < $k + 1 || $k < 2 ) {
			return -1.0;
		}

		$num_features = max( 2, $num_features > 0 ? $num_features : count( $X[0] ?? array() ) );
		$counts       = self::cluster_counts( $labels, $k );
		$max_c        = max( $counts );
		$min_c        = min( $counts );
		$max_share    = $max_c / (float) $n;
		if ( ! self::is_partition_valid( $counts, $n, $k ) ) {
			return -INF;
		}

		$non_empty    = 0;
		foreach ( $counts as $cnt ) {
			if ( $cnt > 0 ) {
				++$non_empty;
			}
		}
		if ( $non_empty < $k ) {
			return -1.0;
		}

		if ( $max_share > 0.82 ) {
			return -1.0 + ( 1.0 - $max_share );
		}

		$sil     = self::silhouette_avg( $X, $labels, $k );
		$balance = $min_c / max( 1, $max_c );
		$shape   = self::partition_shape_stats( $counts, $n );

		$penalty = self::partition_shape_penalty( $counts, $n, $k, $num_features );
		if ( $shape['singletons'] > 0 && $k >= 4 ) {
			$penalty += 0.08 * $shape['singletons'];
		}

		$effective_bonus = min( 0.1, $shape['effective'] / max( 1.0, (float) $k ) * 0.1 );

		return max(
			0.0,
			$sil * 0.55 + ( 1.0 - $max_share ) * 0.2 + $balance * 0.12 + $effective_bonus - $penalty
		);
	}

	/**
	 * @param list<int> $labels
	 * @return list<int>
	 */
	private static function cluster_counts( array $labels, int $k ): array {
		$counts = array_fill( 0, max( 1, $k ), 0 );
		foreach ( $labels as $lab ) {
			$c = (int) $lab;
			if ( $c >= 0 && $c < $k ) {
				++$counts[ $c ];
			}
		}
		return $counts;
	}

	/**
	 * Средний коэффициент силуэта (−1…1, выше — кластеры различимее).
	 *
	 * @param list<list<float>> $X
	 * @param list<int>         $labels
	 */
	private static function silhouette_avg( array $X, array $labels, int $k ): float {
		$n = count( $X );
		if ( $n < 3 || $k < 2 ) {
			return 0.0;
		}

		$dists = array();
		for ( $i = 0; $i < $n; $i++ ) {
			$dists[ $i ] = array();
			for ( $j = $i + 1; $j < $n; $j++ ) {
				$d = sqrt( self::euclid_sq( $X[ $i ], $X[ $j ] ) );
				$dists[ $i ][ $j ] = $d;
				$dists[ $j ][ $i ] = $d;
			}
		}

		$clusters = array_fill( 0, $k, array() );
		for ( $i = 0; $i < $n; $i++ ) {
			$c = (int) ( $labels[ $i ] ?? 0 );
			if ( $c >= 0 && $c < $k ) {
				$clusters[ $c ][] = $i;
			}
		}

		$sum_s = 0.0;
		$cnt_s = 0;
		for ( $i = 0; $i < $n; $i++ ) {
			$c    = (int) ( $labels[ $i ] ?? 0 );
			$same = $clusters[ $c ] ?? array();
			if ( count( $same ) <= 1 ) {
				continue;
			}

			$a = 0.0;
			foreach ( $same as $j ) {
				if ( $j === $i ) {
					continue;
				}
				$a += $dists[ $i ][ $j ];
			}
			$a /= ( count( $same ) - 1 );

			$b = INF;
			for ( $c2 = 0; $c2 < $k; $c2++ ) {
				if ( $c2 === $c || count( $clusters[ $c2 ] ) < 1 ) {
					continue;
				}
				$other = 0.0;
				foreach ( $clusters[ $c2 ] as $j ) {
					$other += $dists[ $i ][ $j ];
				}
				$other /= count( $clusters[ $c2 ] );
				if ( $other < $b ) {
					$b = $other;
				}
			}
			if ( ! is_finite( $b ) ) {
				continue;
			}
			$den = max( $a, $b, 1e-12 );
			$sum_s += ( $b - $a ) / $den;
			++$cnt_s;
		}

		return $cnt_s > 0 ? $sum_s / $cnt_s : 0.0;
	}

	/**
	 * @param list<float> $a
	 * @param list<float> $b
	 */
	private static function euclid_sq( array $a, array $b ): float {
		$s = 0.0;
		$d = min( count( $a ), count( $b ) );
		for ( $j = 0; $j < $d; $j++ ) {
			$diff = $a[ $j ] - $b[ $j ];
			$s   += $diff * $diff;
		}
		return $s;
	}

	/**
	 * @param list<list<float>> $X
	 */
	private static function optimal_k_for_separation( array $X, int $num_features ): int {
		$n        = count( $X );
		$k_min    = self::MIN_K_AUTOTUNE;
		$num_features = max( 2, $num_features );
		$k_max    = self::max_k_allowed( $n, $num_features );
		if ( $n <= $k_max ) {
			$k_max = max( $k_min, $n - 1 );
		}

		$fast_scores = array();
		for ( $k = $k_min; $k <= $k_max; $k++ ) {
			$labels = self::kmeans_best_labels( $X, $k, self::KMEANS_RESTARTS_PROBE, true, $num_features );
			if ( empty( $labels ) ) {
				continue;
			}
			$q = self::cluster_quality_score_fast( $X, $labels, $k, $num_features );
			if ( ! is_finite( $q ) ) {
				continue;
			}
			$fast_scores[ $k ] = $q;
		}
		if ( empty( $fast_scores ) ) {
			return $k_min;
		}
		arsort( $fast_scores, SORT_NUMERIC );
		$finalists = array_slice( array_keys( $fast_scores ), 0, 3 );

		$best_k = $finalists[0] ?? min( 5, $k_max );
		$best_q = -INF;
		foreach ( $finalists as $k ) {
			$labels = self::kmeans_best_labels( $X, $k, self::KMEANS_RESTARTS_FINAL, false, $num_features );
			if ( empty( $labels ) ) {
				continue;
			}
			$q      = self::cluster_quality_score( $X, $labels, $k, $num_features );
			if ( $q > $best_q ) {
				$best_q = $q;
				$best_k = $k;
			}
		}
		if ( ! is_finite( $best_q ) ) {
			return $k_min;
		}

		return max( $k_min, $best_k );
	}

	/**
	 * @param array<string, array<string, float>> $rows
	 * @param list<string>                        $features
	 * @return list<list<float>>
	 */
	/**
	 * Минимум стран с числом по признаку (адаптивно: 20% выборки, не меньше 3 и не больше 45%).
	 */
	private static function min_values_required_for_feature( int $n ): int {
		if ( $n < 1 ) {
			return self::MIN_COUNTRIES;
		}
		$by_pct = (int) ceil( $n * self::MIN_COVERAGE );
		return max( self::MIN_COUNTRIES, min( $by_pct, (int) ceil( $n * 0.45 ) ) );
	}

	/**
	 * Матрица с подстановкой медианы по столбцу для пропусков (если признак есть хотя бы у 50% стран).
	 *
	 * @param array<string, array<string, float>> $rows
	 * @param list<string>                        $features
	 * @return list<list<float>>
	 */
	private static function build_matrix_imputed( array $rows, array $features ): array {
		if ( count( $features ) < 2 ) {
			return array();
		}
		$n       = count( $rows );
		$medians = array();
		foreach ( $features as $sig ) {
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
			if ( count( $vals ) < max( 2, (int) ceil( $n * 0.5 ) ) ) {
				return array();
			}
			sort( $vals, SORT_NUMERIC );
			$mid            = (int) floor( ( count( $vals ) - 1 ) / 2 );
			$medians[ $sig ] = $vals[ $mid ];
		}
		$matrix = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$vec       = array();
			$real_cnt  = 0;
			foreach ( $features as $sig ) {
				if ( isset( $row[ $sig ] ) && is_finite( (float) $row[ $sig ] ) ) {
					$vec[] = (float) $row[ $sig ];
					++$real_cnt;
				} elseif ( isset( $medians[ $sig ] ) ) {
					$vec[] = (float) $medians[ $sig ];
				} else {
					$real_cnt = -1;
					break;
				}
			}
			if ( $real_cnt >= max( 2, (int) ceil( count( $features ) * 0.5 ) ) ) {
				$matrix[] = $vec;
			}
		}
		return $matrix;
	}

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
	 * min(размеры кластеров) / max(размеры) ∈ (0, 1]; 1 = идеально равномерно.
	 *
	 * @param list<int> $labels
	 */
	private static function cluster_balance_ratio( array $labels, int $k ): float {
		$k      = max( 1, $k );
		$counts = array_fill( 0, $k, 0 );
		foreach ( $labels as $lab ) {
			$c = (int) $lab;
			if ( $c >= 0 && $c < $k ) {
				++$counts[ $c ];
			}
		}
		$min_c = min( $counts );
		$max_c = max( $counts );
		if ( $max_c < 1 ) {
			return 0.0;
		}
		return $min_c / $max_c;
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
	 * Устаревший k-means без квот (только fallback, если калькулятор недоступен).
	 *
	 * @param list<list<float>> $X
	 * @return list<int>
	 */
	private static function kmeans_legacy( array $X, int $k, int $max_iter ): array {
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
	/**
	 * Лучшее разбиение k-means++ из нескольких перезапусков (для превью и расчёта).
	 *
	 * @param list<list<float>> $X стандартизованная матрица
	 * @return list<int>
	 */
	public static function best_cluster_labels( array $X, int $k ): array {
		return self::kmeans_best_labels( $X, $k, self::KMEANS_RESTARTS_FINAL, true );
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
		$allow = array_flip( WSErgo_Country_Macro_Calculator::macro_cluster_signal_allowlist( $scope ) );
		foreach ( $features as $f ) {
			$f = sanitize_key( (string) $f );
			if ( $f !== '' && isset( $allow[ $f ] ) ) {
				$clean[] = $f;
			}
		}
		if ( count( $clean ) < 2 ) {
			return;
		}
		if ( count( $clean ) < self::MIN_FEATURES ) {
			return;
		}
		$k = max( self::MIN_K_AUTOTUNE, min( self::max_k_allowed( 300, count( $clean ) ), $k ) );
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
