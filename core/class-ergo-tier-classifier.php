<?php
/**
 * Классификация эргономичности стран.
 *
 * По каждой оси: динамическая растяжка min–max выборки + 5% поля сверху/снизу, затем 5 уровней.
 * Итог: взвешенный балл и уровни шести критериев (динамическая шкала выборки), связанные в общий уровень.
 * Профили: k-means по 6 осям с весами критериев.
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_Tier_Classifier {

	public const SCORE_KEYS = array( 'E', 'F', 'Cm', 'H', 'A', 'S', 'Ct' );

	public const CRITERIA_KEYS = array( 'F', 'Cm', 'H', 'A', 'S', 'Ct' );

	/** Поля к min/max выборки (5% диапазона с каждой стороны). */
	private const SCALE_PADDING_RATIO = 0.05;

	/** Доля взвешенного балла в итоговом уровне (остальное — уровни критериев). */
	private const OVERALL_BLEND_STRETCH = 0.40;

	/** Пороги относительной позиции на растянутой шкале (5 классов). */
	private const REL_EXCELLENT = 0.80;
	private const REL_GOOD      = 0.60;
	private const REL_AVERAGE   = 0.40;
	private const REL_POOR      = 0.20;

	/** @var array<string, mixed>|null */
	private static $global_context_cache = null;

	/** @var array<string, mixed>|null */
	private static $axis_clusters_cache = null;

	public static function flush_runtime_cache(): void {
		self::$global_context_cache = null;
		self::$axis_clusters_cache  = null;
	}

	public static function axis_labels_ru(): array {
		return array(
			'E'  => __( 'Сводный E', 'worldstat-ergonomics' ),
			'F'  => __( 'Функциональность', 'worldstat-ergonomics' ),
			'Cm' => __( 'Комфортность', 'worldstat-ergonomics' ),
			'H'  => __( 'Обитаемость', 'worldstat-ergonomics' ),
			'A'  => __( 'Освояемость', 'worldstat-ergonomics' ),
			'S'  => __( 'Безопасность', 'worldstat-ergonomics' ),
			'Ct' => __( 'Управляемость', 'worldstat-ergonomics' ),
		);
	}

	/**
	 * @return array<string, float>
	 */
	public static function get_criteria_weights(): array {
		$out = array();
		foreach ( self::CRITERIA_KEYS as $k ) {
			$out[ $k ] = 1.0;
		}
		if ( class_exists( 'WSErgo_Settings' ) && method_exists( 'WSErgo_Settings', 'get_macro_e_axis_weights' ) ) {
			$stored = WSErgo_Settings::get_macro_e_axis_weights();
			if ( is_array( $stored ) && ! empty( $stored ) ) {
				$sum = 0.0;
				foreach ( self::CRITERIA_KEYS as $k ) {
					$w = isset( $stored[ $k ] ) ? (float) $stored[ $k ] : 0.0;
					if ( $w > 0 && is_finite( $w ) ) {
						$out[ $k ] = $w;
						$sum += $w;
					}
				}
				if ( $sum > 0 ) {
					return $out;
				}
			}
		}
		return $out;
	}

	/**
	 * @param array<string, float> $scores
	 */
	public static function weighted_criteria_score( array $scores ): float {
		$weights = self::get_criteria_weights();
		$sum     = 0.0;
		$wsum    = 0.0;
		foreach ( self::CRITERIA_KEYS as $k ) {
			if ( ! isset( $scores[ $k ] ) ) {
				continue;
			}
			$v = (float) $scores[ $k ];
			if ( $v <= 0 || ! is_finite( $v ) ) {
				continue;
			}
			$w = (float) ( $weights[ $k ] ?? 1.0 );
			if ( $w <= 0 ) {
				continue;
			}
			$sum  += $v * $w;
			$wsum += $w;
		}
		return $wsum > 0 ? $sum / $wsum : 0.0;
	}

	public static function composite_classification_score( array $scores ): float {
		$w = self::weighted_criteria_score( $scores );
		return $w > 0 ? $w : 0.0;
	}

	/**
	 * @deprecated
	 */
	public static function classify_index( float $index, ?array $thresholds = null ): array {
		unset( $thresholds );
		$ctx = self::get_global_context();
		if ( isset( $ctx['overall_scale'] ) ) {
			return self::tier_from_relative( self::relative_position( $index, $ctx['overall_scale'] ) );
		}
		return self::tier_from_relative( 0.5 );
	}

	/**
	 * @deprecated
	 */
	public static function get_thresholds(): array {
		return array(
			'excellent_min' => self::REL_EXCELLENT * 100,
			'good_min'      => self::REL_GOOD * 100,
			'average_min'   => self::REL_AVERAGE * 100,
			'poor_min'      => self::REL_POOR * 100,
			'n'             => 0,
		);
	}

	/**
	 * @deprecated
	 */
	public static function get_axis_thresholds( string $axis_key ): array {
		unset( $axis_key );
		return self::get_thresholds();
	}

	/**
	 * @param array<string, float> $scores
	 * @param string               $iso3
	 */
	public static function classify_country_scores( array $scores, string $iso3 = '' ): ?array {
		$ctx = self::get_global_context();
		$iso3 = strtoupper( sanitize_key( $iso3 ) );

		$axes      = array();
		$axis_vals = array();
		foreach ( self::SCORE_KEYS as $k ) {
			if ( ! isset( $scores[ $k ] ) ) {
				continue;
			}
			$v = (float) $scores[ $k ];
			if ( $v > 0 && is_finite( $v ) ) {
				$axes[ $k ]      = round( $v, 1 );
				$axis_vals[ $k ] = $v;
			}
		}
		if ( empty( $axis_vals ) ) {
			return null;
		}

		$axis_tiers   = array();
		$axis_scales  = $ctx['axis_scales'] ?? array();
		$axis_labels  = self::axis_labels_ru();
		foreach ( $axis_vals as $k => $v ) {
			$scale = isset( $axis_scales[ $k ] ) ? $axis_scales[ $k ] : null;
			if ( is_array( $scale ) ) {
				$rel = self::relative_position( $v, $scale );
				$axis_tiers[ $k ] = self::tier_from_relative( $rel );
				$axis_tiers[ $k ]['relative'] = round( $rel * 100, 1 );
			} else {
				$axis_tiers[ $k ] = self::tier_from_relative( 0.5 );
			}
		}

		$weighted = self::weighted_criteria_score( $axis_vals );
		if ( $weighted <= 0 ) {
			return null;
		}

		$overall_scale = $ctx['overall_scale'] ?? null;
		$rel_overall   = is_array( $overall_scale )
			? self::relative_position( $weighted, $overall_scale )
			: 0.5;
		$criteria_rel = self::criteria_mean_relative( $axis_tiers );
		$blend_rel    = self::OVERALL_BLEND_STRETCH * $rel_overall + ( 1.0 - self::OVERALL_BLEND_STRETCH ) * $criteria_rel;
		$tier         = self::resolve_overall_tier( $blend_rel, $rel_overall, $axis_tiers );

		$overall_rank = self::tier_rank( (string) $tier['slug'] );
		$weak         = array();
		foreach ( self::CRITERIA_KEYS as $k ) {
			if ( ! isset( $axis_tiers[ $k ]['slug'] ) || $axis_tiers[ $k ]['slug'] === '' ) {
				continue;
			}
			if ( self::tier_rank( (string) $axis_tiers[ $k ]['slug'] ) < $overall_rank ) {
				$weak[] = $k;
			}
		}
		$weak_labels = array();
		foreach ( $weak as $k ) {
			$weak_labels[] = (string) ( $axis_labels[ $k ] ?? $k );
		}

		$scale_hint = '';
		if ( is_array( $overall_scale ) ) {
			$scale_hint = sprintf(
				'%s–%s',
				number_format( (float) $overall_scale['low'], 1, ',', ' ' ),
				number_format( (float) $overall_scale['high'], 1, ',', ' ' )
			);
		}

		$criteria_tier = self::tier_from_relative( $criteria_rel );
		$reason        = sprintf(
			/* translators: 1: tier, 2: weighted score, 3: criteria tier label, 4: scale range */
			__( 'Итог «%1$s»: взвеш. балл %2$s, сводка по критериям — «%3$s» (шкала %4$s).', 'worldstat-ergonomics' ),
			$tier['label'],
			number_format( $weighted, 1, ',', ' ' ),
			$criteria_tier['label'],
			$scale_hint
		);
		if ( ! empty( $weak_labels ) ) {
			$reason .= ' ' . sprintf( __( 'Слабее итога: %s.', 'worldstat-ergonomics' ), implode( ', ', $weak_labels ) );
		}

		return array_merge(
			$tier,
			array(
				'composite'         => round( $weighted, 2 ),
				'weighted_score'    => round( $weighted, 2 ),
				'relative_overall'  => round( $blend_rel * 100, 1 ),
				'criteria_summary'  => (string) $criteria_tier['label'],
				'tier_stretch_slug' => (string) self::tier_from_relative( $rel_overall )['slug'],
				'axes'              => $axes,
				'axis_tiers'        => $axis_tiers,
				'limiting_axes'     => $weak,
				'limiting_labels'   => $weak_labels,
				'tier_reason'       => $reason,
			)
		);
	}

	public static function get_tier_for_iso2( string $iso2 ): ?array {
		$iso2 = strtoupper( sanitize_key( $iso2 ) );
		if ( $iso2 === '' || ! class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			return null;
		}
		$iso3 = WSErgo_Country_Macro_Calculator::iso2_to_iso3_public( $iso2 );
		if ( strlen( $iso3 ) !== 3 ) {
			return null;
		}
		$all    = WSErgo_Country_Macro_Calculator::get_all_scores();
		$scores = isset( $all[ $iso3 ] ) && is_array( $all[ $iso3 ] ) ? $all[ $iso3 ] : array();
		if ( empty( $scores ) ) {
			return null;
		}
		$tier = self::classify_country_scores( $scores, $iso3 );
		if ( null === $tier ) {
			return null;
		}
		$tier['index'] = isset( $tier['axes']['E'] ) ? (float) $tier['axes']['E'] : (float) $tier['composite'];
		$clusters = self::get_axis_profile_clusters();
		if ( isset( $clusters['assignments'][ $iso3 ] ) ) {
			$cid = (int) $clusters['assignments'][ $iso3 ];
			$tier['cluster_id']    = $cid;
			$tier['cluster_label'] = (string) ( $clusters['profiles'][ $cid ]['label'] ?? '' );
		}
		return $tier;
	}

	public static function get_global_classification_rows(): array {
		if ( ! class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			return array();
		}
		$ctx      = self::get_global_context();
		$all      = WSErgo_Country_Macro_Calculator::get_all_scores();
		$clusters = self::get_axis_profile_clusters();
		$rows     = array();

		foreach ( $all as $iso3 => $scores ) {
			if ( ! is_array( $scores ) ) {
				continue;
			}
			$classified = self::classify_country_scores( $scores, (string) $iso3 );
			if ( null === $classified ) {
				continue;
			}
			$m = class_exists( 'WSErgo_Country_Macro_Calculator' )
				? WSErgo_Country_Macro_Calculator::country_meta_for_iso3( (string) $iso3 )
				: array( 'iso2' => '', 'name' => $iso3, 'is_aggregate' => true );
			if ( ! empty( $m['is_aggregate'] ) ) {
				continue;
			}
			$cid = isset( $clusters['assignments'][ $iso3 ] ) ? (int) $clusters['assignments'][ $iso3 ] : -1;
			$rows[] = array(
				'iso2'              => (string) $m['iso2'],
				'name'              => (string) $m['name'],
				'composite'         => (float) $classified['composite'],
				'relative_overall'  => (float) ( $classified['relative_overall'] ?? 0 ),
				'tier'              => array(
					'slug'  => (string) $classified['slug'],
					'label' => (string) $classified['label'],
					'color' => (string) $classified['color'],
				),
				'axes'              => $classified['axes'],
				'axis_tiers'        => $classified['axis_tiers'],
				'limiting_axes'     => $classified['limiting_axes'],
				'limiting_labels'   => $classified['limiting_labels'],
				'tier_reason'       => (string) $classified['tier_reason'],
				'cluster_id'        => $cid,
				'cluster_label'     => $cid >= 0 ? (string) ( $clusters['profiles'][ $cid ]['label'] ?? '' ) : '',
			);
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				return ( (float) ( $b['composite'] ?? 0 ) ) <=> ( (float) ( $a['composite'] ?? 0 ) );
			}
		);
		return $rows;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function get_global_context(): array {
		if ( is_array( self::$global_context_cache ) ) {
			return self::$global_context_cache;
		}

		self::$global_context_cache = array(
			'axis_scales'   => array(),
			'overall_scale' => null,
			'n_countries'   => 0,
		);

		if ( ! class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			return self::$global_context_cache;
		}

		$all = WSErgo_Country_Macro_Calculator::get_all_scores();
		if ( empty( $all ) ) {
			return self::$global_context_cache;
		}

		$axis_values = array();
		foreach ( self::SCORE_KEYS as $k ) {
			$axis_values[ $k ] = array();
		}
		$weighted_by_iso3 = array();

		foreach ( $all as $iso3 => $scores ) {
			if ( ! is_array( $scores ) ) {
				continue;
			}
			$w = self::weighted_criteria_score( $scores );
			if ( $w > 0 ) {
				$weighted_by_iso3[ (string) $iso3 ] = $w;
			}
			foreach ( self::SCORE_KEYS as $k ) {
				if ( isset( $scores[ $k ] ) ) {
					$v = (float) $scores[ $k ];
					if ( $v > 0 && is_finite( $v ) ) {
						$axis_values[ $k ][] = $v;
					}
				}
			}
		}

		$axis_scales = array();
		foreach ( self::SCORE_KEYS as $k ) {
			if ( count( $axis_values[ $k ] ?? array() ) >= 3 ) {
				$axis_scales[ $k ] = self::build_stretched_scale( $axis_values[ $k ] );
			}
		}

		$overall_scale = count( $weighted_by_iso3 ) >= 3
			? self::build_stretched_scale( array_values( $weighted_by_iso3 ) )
			: null;

		self::$global_context_cache = array(
			'axis_scales'   => $axis_scales,
			'overall_scale' => $overall_scale,
			'n_countries'   => count( $weighted_by_iso3 ),
		);

		return self::$global_context_cache;
	}

	/**
	 * @param list<float> $values
	 * @return array{low:float,high:float,min:float,max:float,span:float,n:int}
	 */
	private static function build_stretched_scale( array $values ): array {
		$values = array_values(
			array_filter(
				$values,
				static function ( $v ) {
					return is_finite( (float) $v );
				}
			)
		);
		$n = count( $values );
		if ( $n < 1 ) {
			return array(
				'low'  => 0.0,
				'high' => 100.0,
				'min'  => 0.0,
				'max'  => 100.0,
				'span' => 100.0,
				'n'    => 0,
			);
		}
		$min  = (float) min( $values );
		$max  = (float) max( $values );
		$span = max( $max - $min, 1.0 );
		$pad  = $span * self::SCALE_PADDING_RATIO;
		return array(
			'low'  => $min - $pad,
			'high' => $max + $pad,
			'min'  => $min,
			'max'  => $max,
			'span' => $span,
			'n'    => $n,
		);
	}

	/**
	 * @param array{low:float,high:float} $scale
	 */
	private static function relative_position( float $value, array $scale ): float {
		$low  = (float) ( $scale['low'] ?? 0 );
		$high = (float) ( $scale['high'] ?? 100 );
		$rng  = $high - $low;
		if ( $rng < 1e-9 ) {
			return 0.5;
		}
		return max( 0.0, min( 1.0, ( $value - $low ) / $rng ) );
	}

	/**
	 * @return array{slug:string,label:string,color:string}
	 */
	private static function tier_from_relative( float $rel ): array {
		if ( $rel >= self::REL_EXCELLENT ) {
			$slug = 'excellent';
		} elseif ( $rel >= self::REL_GOOD ) {
			$slug = 'good';
		} elseif ( $rel >= self::REL_AVERAGE ) {
			$slug = 'average';
		} elseif ( $rel >= self::REL_POOR ) {
			$slug = 'poor';
		} else {
			$slug = 'critical';
		}
		return self::tier_meta( $slug );
	}

	/**
	 * Средняя относительная позиция по уровням шести критериев (0–1).
	 *
	 * @param array<string, array{slug:string}> $axis_tiers
	 */
	private static function criteria_mean_relative( array $axis_tiers ): float {
		$sum = 0.0;
		$n   = 0;
		foreach ( self::CRITERIA_KEYS as $k ) {
			if ( empty( $axis_tiers[ $k ]['slug'] ) ) {
				continue;
			}
			$sum += (float) self::tier_rank( (string) $axis_tiers[ $k ]['slug'] );
			++$n;
		}
		return $n > 0 ? ( $sum / $n ) / 4.0 : 0.5;
	}

	/**
	 * Итоговый уровень: баланс взвешенного балла и критериев, с сохранением «Отлично» при сильном профиле.
	 *
	 * @param array<string, array{slug:string}> $axis_tiers
	 * @return array{slug:string,label:string,color:string}
	 */
	private static function resolve_overall_tier( float $blend_rel, float $rel_weighted, array $axis_tiers ): array {
		$stretch_rank = self::tier_rank( (string) self::tier_from_relative( $rel_weighted )['slug'] );
		$worst_rank   = 4;
		foreach ( self::CRITERIA_KEYS as $k ) {
			if ( empty( $axis_tiers[ $k ]['slug'] ) ) {
				continue;
			}
			$worst_rank = min( $worst_rank, self::tier_rank( (string) $axis_tiers[ $k ]['slug'] ) );
		}
		$blend_rank = (int) round( 0.55 * self::tier_rank( (string) self::tier_from_relative( $blend_rel )['slug'] ) + 0.45 * $worst_rank );
		$blend_rank = max( 0, min( 4, $blend_rank ) );

		if ( $stretch_rank >= 4 && $worst_rank >= 2 && $blend_rel >= self::REL_GOOD ) {
			$blend_rank = max( $blend_rank, 4 );
		} elseif ( $stretch_rank >= 3 && $worst_rank >= 2 && $blend_rel >= self::REL_AVERAGE ) {
			$blend_rank = max( $blend_rank, 3 );
		}

		return self::tier_from_relative( $blend_rank / 4.0 );
	}

	public static function get_axis_profile_clusters( int $k = 4 ): array {
		if ( is_array( self::$axis_clusters_cache ) ) {
			return self::$axis_clusters_cache;
		}

		self::$axis_clusters_cache = array(
			'assignments' => array(),
			'profiles'    => array(),
		);

		if ( ! class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			return self::$axis_clusters_cache;
		}

		$weights = self::get_criteria_weights();
		$wsum    = array_sum( $weights );
		if ( $wsum <= 0 ) {
			$wsum = (float) count( self::CRITERIA_KEYS );
		}
		$norm_w = array();
		foreach ( self::CRITERIA_KEYS as $k ) {
			$norm_w[] = (float) ( ( $weights[ $k ] ?? 1.0 ) / $wsum );
		}

		$all  = WSErgo_Country_Macro_Calculator::get_all_scores();
		$keys = array();
		$vecs = array();
		foreach ( $all as $iso3 => $scores ) {
			if ( ! is_array( $scores ) ) {
				continue;
			}
			$vec = array();
			$ok  = true;
			foreach ( self::CRITERIA_KEYS as $i => $axis ) {
				if ( ! isset( $scores[ $axis ] ) || (float) $scores[ $axis ] <= 0 ) {
					$ok = false;
					break;
				}
				$vec[] = (float) $scores[ $axis ] * sqrt( $norm_w[ $i ] );
			}
			if ( ! $ok ) {
				continue;
			}
			$keys[] = (string) $iso3;
			$vecs[] = $vec;
		}

		$n = count( $vecs );
		if ( $n < 2 ) {
			return self::$axis_clusters_cache;
		}
		$k = max( 2, min( $k, $n ) );

		$km     = self::kmeans_weighted( $vecs, $k, 40, $norm_w );
		$labels = $km['labels'];
		$cents  = $km['centroids'];
		$axis_labels = self::axis_labels_ru();

		$profiles = array();
		for ( $c = 0; $c < $k; $c++ ) {
			$members = 0;
			foreach ( $labels as $lab ) {
				if ( (int) $lab === $c ) {
					++$members;
				}
			}
			$raw_centroid = array();
			foreach ( self::CRITERIA_KEYS as $i => $axis ) {
				$nw = $norm_w[ $i ] > 1e-9 ? $norm_w[ $i ] : 1.0;
				$raw_centroid[] = (float) ( ( $cents[ $c ][ $i ] ?? 0 ) / sqrt( $nw ) );
			}
			$profiles[ $c ] = array(
				'label'    => self::describe_cluster_centroid( $raw_centroid, $axis_labels ),
				'n'        => $members,
				'centroid' => $raw_centroid,
			);
		}

		$assignments = array();
		foreach ( $keys as $i => $iso3 ) {
			$assignments[ $iso3 ] = (int) ( $labels[ $i ] ?? 0 );
		}

		self::$axis_clusters_cache = array(
			'assignments' => $assignments,
			'profiles'    => $profiles,
		);
		return self::$axis_clusters_cache;
	}

	/**
	 * @param list<list<float>> $X
	 * @param list<float>      $dim_weights нормализованные веса осей
	 * @return array{labels:list<int>,centroids:list<list<float>>}
	 */
	private static function kmeans_weighted( array $X, int $k, int $max_iter, array $dim_weights ): array {
		$n = count( $X );
		$m = count( $X[0] ?? array() );
		$k = max( 2, min( $k, $n ) );
		$w = array();
		for ( $j = 0; $j < $m; $j++ ) {
			$w[ $j ] = (float) ( $dim_weights[ $j ] ?? ( 1.0 / max( 1, $m ) ) );
		}

		$idx = range( 0, $n - 1 );
		mt_srand( crc32( wp_json_encode( array( $n, $k, $m ) ) ?: (string) $n ) );
		shuffle( $idx );

		$centroids = array();
		for ( $i = 0; $i < $k; ++$i ) {
			$centroids[] = $X[ $idx[ $i ] ];
		}

		$labels = array_fill( 0, $n, 0 );
		for ( $iter = 0; $iter < $max_iter; ++$iter ) {
			for ( $i = 0; $i < $n; ++$i ) {
				$best      = 0;
				$best_dist = INF;
				for ( $c = 0; $c < $k; ++$c ) {
					$dist = 0.0;
					for ( $j = 0; $j < $m; ++$j ) {
						$d = $X[ $i ][ $j ] - $centroids[ $c ][ $j ];
						$dist += $w[ $j ] * $d * $d;
					}
					if ( $dist < $best_dist ) {
						$best_dist = $dist;
						$best      = $c;
					}
				}
				$labels[ $i ] = $best;
			}

			$new_centroids = array_fill( 0, $k, array_fill( 0, $m, 0.0 ) );
			$counts        = array_fill( 0, $k, 0 );
			for ( $i = 0; $i < $n; ++$i ) {
				$c = (int) $labels[ $i ];
				++$counts[ $c ];
				for ( $j = 0; $j < $m; ++$j ) {
					$new_centroids[ $c ][ $j ] += $X[ $i ][ $j ];
				}
			}
			for ( $c = 0; $c < $k; ++$c ) {
				if ( $counts[ $c ] > 0 ) {
					for ( $j = 0; $j < $m; ++$j ) {
						$new_centroids[ $c ][ $j ] /= $counts[ $c ];
					}
				} else {
					$new_centroids[ $c ] = $centroids[ $c ];
				}
			}
			$centroids = $new_centroids;
		}

		return array(
			'labels'    => $labels,
			'centroids' => $centroids,
		);
	}

	public static function build_compare_classification_analysis( array $iso2_list ): array {
		$iso2_list = array_values(
			array_unique(
				array_filter(
					array_map(
						static function ( $c ) {
							$c = strtoupper( sanitize_text_field( (string) $c ) );
							return strlen( $c ) === 2 ? $c : '';
						},
						$iso2_list
					)
				)
			)
		);
		if ( empty( $iso2_list ) ) {
			return array(
				'summary'    => '',
				'insights'   => array(),
				'highlights' => array(),
			);
		}

		$rows_by_iso = array();
		foreach ( self::get_global_classification_rows() as $row ) {
			$iso = strtoupper( (string) ( $row['iso2'] ?? '' ) );
			if ( $iso !== '' ) {
				$rows_by_iso[ $iso ] = $row;
			}
		}

		$selected = array();
		foreach ( $iso2_list as $iso2 ) {
			if ( isset( $rows_by_iso[ $iso2 ] ) ) {
				$selected[] = $rows_by_iso[ $iso2 ];
			}
		}
		if ( empty( $selected ) ) {
			return array(
				'summary'    => __( 'Нет данных эргономичности для выбранных стран.', 'worldstat-ergonomics' ),
				'insights'   => array(),
				'highlights' => array(),
			);
		}

		$axis_labels = self::axis_labels_ru();
		$summary     = sprintf(
			__( 'Сравнение %1$d стран: по каждой оси — динамическая шкала выборки; итог согласован с уровнями шести критериев (веса E) и взвешенным баллом.', 'worldstat-ergonomics' ),
			count( $selected )
		);

		usort(
			$selected,
			static function ( $a, $b ) {
				return ( (float) ( $b['composite'] ?? 0 ) ) <=> ( (float) ( $a['composite'] ?? 0 ) );
			}
		);

		$insights    = array();
		$tier_counts = array();
		$weak_axis   = array();
		foreach ( $selected as $row ) {
			$ts = (string) ( $row['tier']['slug'] ?? '' );
			if ( $ts !== '' ) {
				$tier_counts[ $ts ] = ( $tier_counts[ $ts ] ?? 0 ) + 1;
			}
			foreach ( (array) ( $row['limiting_axes'] ?? array() ) as $ax ) {
				$ax = (string) $ax;
				if ( $ax !== '' ) {
					$weak_axis[ $ax ] = ( $weak_axis[ $ax ] ?? 0 ) + 1;
				}
			}
		}

		$n_sel = count( $selected );
		if ( $n_sel <= 8 ) {
			foreach ( $tier_counts as $slug => $cnt ) {
				$m          = self::tier_meta( $slug );
				$insights[] = sprintf( __( 'Итог «%1$s» — %2$d стр.', 'worldstat-ergonomics' ), $m['label'], $cnt );
			}
		} elseif ( $n_sel <= 15 && count( $tier_counts ) > 1 ) {
			$parts = array();
			foreach ( $tier_counts as $slug => $cnt ) {
				$m       = self::tier_meta( $slug );
				$parts[] = $m['label'] . ' — ' . $cnt;
			}
			$insights[] = sprintf( __( 'Разброс итоговых уровней: %s.', 'worldstat-ergonomics' ), implode( '; ', $parts ) );
		}

		if ( ! empty( $weak_axis ) && $n_sel <= 8 ) {
			arsort( $weak_axis );
			$top_ax = array_key_first( $weak_axis );
			if ( is_string( $top_ax ) && $top_ax !== '' ) {
				$insights[] = sprintf(
					__( 'Чаще слабее итога: %1$s (%2$d из %3$d).', 'worldstat-ergonomics' ),
					(string) ( $axis_labels[ $top_ax ] ?? $top_ax ),
					(int) $weak_axis[ $top_ax ],
					count( $selected )
				);
			}
		}

		$highlights = array(
			array(
				'label' => __( 'Лидер по взвеш. баллу', 'worldstat-ergonomics' ),
				'value' => (string) ( $selected[0]['name'] ?? '' ) . ' — ' . number_format( (float) ( $selected[0]['composite'] ?? 0 ), 1, ',', ' ' ),
			),
			array(
				'label' => __( 'Итоговый уровень лидера', 'worldstat-ergonomics' ),
				'value' => (string) ( $selected[0]['tier']['label'] ?? '—' ),
			),
		);
		if ( count( $selected ) > 1 ) {
			$last = $selected[ count( $selected ) - 1 ];
			$highlights[] = array(
				'label' => __( 'Ниже по взвеш. баллу', 'worldstat-ergonomics' ),
				'value' => (string) ( $last['name'] ?? '' ) . ' — ' . number_format( (float) ( $last['composite'] ?? 0 ), 1, ',', ' ' ) . ' («' . (string) ( $last['tier']['label'] ?? '—' ) . '»)',
			);
		}

		return array(
			'summary'    => $summary,
			'insights'   => $insights,
			'highlights' => $highlights,
		);
	}

	/**
	 * @param list<float>          $centroid
	 * @param array<string,string> $axis_labels
	 */
	private static function describe_cluster_centroid( array $centroid, array $axis_labels ): string {
		if ( count( $centroid ) < count( self::CRITERIA_KEYS ) ) {
			return __( 'Смешанный профиль', 'worldstat-ergonomics' );
		}
		$pairs = array();
		foreach ( self::CRITERIA_KEYS as $i => $key ) {
			$pairs[] = array(
				'label' => (string) ( $axis_labels[ $key ] ?? $key ),
				'val'   => (float) ( $centroid[ $i ] ?? 0 ),
			);
		}
		usort(
			$pairs,
			static function ( $a, $b ) {
				return ( $b['val'] ?? 0 ) <=> ( $a['val'] ?? 0 );
			}
		);
		return sprintf(
			__( 'Сильнее: %1$s; слабее: %2$s', 'worldstat-ergonomics' ),
			$pairs[0]['label'] ?? '',
			$pairs[ count( $pairs ) - 1 ]['label'] ?? ''
		);
	}

	private static function tier_rank( string $slug ): int {
		$map = array(
			'critical'  => 0,
			'poor'      => 1,
			'average'   => 2,
			'good'      => 3,
			'excellent' => 4,
		);
		return $map[ $slug ] ?? 2;
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
}
