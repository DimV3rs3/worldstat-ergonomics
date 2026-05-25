<?php
/**
 * Регрессия сводного E страны на макропоказатели (глобальная выборка, без кластеров).
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_Country_Regression {

	private const MIN_PAIRS = 8;

	/**
	 * Сигналы для регрессии: отмеченные в матрице критериев + оси F…Ct как баллы 0–100.
	 *
	 * @return list<array{id:string,label:string,kind:string}>
	 */
	public static function regression_indicator_catalog(): array {
		$catalog = array();
		$labels  = class_exists( 'WSErgo_Tier_Classifier' ) ? WSErgo_Tier_Classifier::axis_labels_ru() : array();

		foreach ( array( 'E', 'F', 'Cm', 'H', 'A', 'S', 'Ct' ) as $ax ) {
			$catalog[] = array(
				'id'    => 'axis:' . $ax,
				'label' => ( $labels[ $ax ] ?? $ax ) . ' (0–100)',
				'kind'  => 'axis',
			);
		}

		if ( class_exists( 'WSErgo_Settings' ) && class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			$matrix = WSErgo_Settings::get_macro_criteria_matrix();
			$seen   = array();
			foreach ( $matrix as $sig => $cols ) {
				$sig = sanitize_key( (string) $sig );
				if ( $sig === '' || isset( $seen[ $sig ] ) ) {
					continue;
				}
				$on = false;
				if ( is_array( $cols ) ) {
					foreach ( $cols as $v ) {
						if ( ! empty( $v ) ) {
							$on = true;
							break;
						}
					}
				}
				if ( ! $on ) {
					continue;
				}
				$seen[ $sig ] = true;
				$catalog[]    = array(
					'id'    => 'signal:' . $sig,
					'label' => WSErgo_Country_Macro_Calculator::data_label_ru( $sig ),
					'kind'  => 'signal',
				);
			}
		}

		return $catalog;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function analyze_global_countries(): array {
		if ( ! class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			return array(
				'usable'     => false,
				'notice'     => __( 'Модуль макроданных недоступен.', 'worldstat-ergonomics' ),
				'n_countries' => 0,
				'univariate' => array(),
			);
		}

		$bundle = WSErgo_Country_Macro_Calculator::get_full_bundle();
		$scores = isset( $bundle['scores'] ) && is_array( $bundle['scores'] ) ? $bundle['scores'] : array();
		$raws   = isset( $bundle['raw_rows'] ) && is_array( $bundle['raw_rows'] ) ? $bundle['raw_rows'] : array();
		$meta   = WSErgo_Country_Macro_Calculator::iso3_country_meta_map();

		$ys = array();
		foreach ( $scores as $iso3 => $row ) {
			if ( ! is_array( $row ) || ! isset( $row['E'] ) ) {
				continue;
			}
			$e = (float) $row['E'];
			if ( $e > 0 && is_finite( $e ) ) {
				$ys[ $iso3 ] = $e;
			}
		}

		$n = count( $ys );
		if ( $n < self::MIN_PAIRS ) {
			return array(
				'usable'      => false,
				'notice'      => sprintf(
					/* translators: %d: minimum countries */
					__( 'Для межстрановой регрессии нужно не меньше %d стран с рассчитанным индексом E.', 'worldstat-ergonomics' ),
					self::MIN_PAIRS
				),
				'n_countries' => $n,
				'univariate'  => array(),
			);
		}

		$median_e = self::median_values( array_values( $ys ) );
		$catalog  = self::regression_indicator_catalog();
		$uni      = array();

		foreach ( $catalog as $item ) {
			$id = (string) ( $item['id'] ?? '' );
			if ( $id === '' ) {
				continue;
			}
			$pairs = array();
			foreach ( $ys as $iso3 => $y_val ) {
				$x = self::x_value_for_indicator( $id, $iso3, $scores, $raws );
				if ( $x === null || ! is_finite( $x ) ) {
					continue;
				}
				$m = $meta[ $iso3 ] ?? array( 'iso2' => '', 'name' => $iso3 );
				$pairs[] = array(
					'iso2'    => (string) $m['iso2'],
					'name'    => (string) $m['name'],
					'x'       => $x,
					'y'       => $y_val,
				);
			}
			if ( count( $pairs ) < self::MIN_PAIRS ) {
				continue;
			}
			$reg = self::ols_y_on_x( $pairs );
			if ( $reg === null ) {
				continue;
			}
			$scatter = array();
			foreach ( $pairs as $p ) {
				$scatter[] = array(
					'iso2' => (string) ( $p['iso2'] ?? '' ),
					'name' => (string) ( $p['name'] ?? '' ),
					'x'    => round( (float) $p['x'], 4 ),
					'y'    => round( (float) $p['y'], 4 ),
				);
			}
			$uni[] = array_merge(
				array(
					'id'     => $id,
					'label'  => (string) ( $item['label'] ?? $id ),
					'scatter' => $scatter,
				),
				$reg
			);
		}

		usort(
			$uni,
			static function ( $a, $b ) {
				$ra = isset( $a['r2'] ) ? abs( (float) $a['r2'] ) : 0.0;
				$rb = isset( $b['r2'] ) ? abs( (float) $b['r2'] ) : 0.0;
				return $rb <=> $ra;
			}
		);

		$limit = (int) apply_filters( 'wsergo_country_regression_univariate_limit', 16 );
		if ( $limit < 1 ) {
			$limit = 16;
		}

		return array(
			'usable'          => ! empty( $uni ),
			'notice'          => empty( $uni )
				? __( 'Недостаточно пар «страна — показатель» для регрессии.', 'worldstat-ergonomics' )
				: '',
			'n_countries'     => $n,
			'median_e'        => round( (float) $median_e, 2 ),
			'univariate'      => array_slice( $uni, 0, $limit ),
			'univariate_full' => $uni,
		);
	}

	/**
	 * @param array<string, array<string, float>> $scores
	 * @param array<string, array<string, float>> $raws
	 */
	private static function x_value_for_indicator( string $id, string $iso3, array $scores, array $raws ): ?float {
		if ( 0 === strpos( $id, 'axis:' ) ) {
			$ax = substr( $id, 5 );
			if ( ! isset( $scores[ $iso3 ][ $ax ] ) ) {
				return null;
			}
			$v = (float) $scores[ $iso3 ][ $ax ];
			return ( $v > 0 && is_finite( $v ) ) ? $v : null;
		}
		if ( 0 === strpos( $id, 'signal:' ) ) {
			$sig = substr( $id, 7 );
			if ( $sig === '' || ! isset( $raws[ $iso3 ][ $sig ] ) ) {
				return null;
			}
			$col = array();
			foreach ( $raws as $row ) {
				if ( is_array( $row ) && isset( $row[ $sig ] ) && is_finite( (float) $row[ $sig ] ) ) {
					$col[] = (float) $row[ $sig ];
				}
			}
			if ( count( $col ) < 3 ) {
				return null;
			}
			$min = min( $col );
			$max = max( $col );
			$raw = (float) $raws[ $iso3 ][ $sig ];
			if ( ! is_finite( $raw ) ) {
				return null;
			}
			if ( abs( $max - $min ) < 1e-12 ) {
				return 50.0;
			}
			return max( 0.0, min( 100.0, 100.0 * ( $raw - $min ) / ( $max - $min ) ) );
		}
		return null;
	}

	/**
	 * @param list<array{x:float,y:float}> $pairs
	 * @return ?array{n:int,mean_x:float,mean_y:float,slope:float,intercept:float,r:float,r2:float}
	 */
	private static function ols_y_on_x( array $pairs ): ?array {
		$n = count( $pairs );
		if ( $n < self::MIN_PAIRS ) {
			return null;
		}
		$mx = 0.0;
		$my = 0.0;
		foreach ( $pairs as $p ) {
			$mx += (float) $p['x'];
			$my += (float) $p['y'];
		}
		$mx /= $n;
		$my /= $n;
		$sxx = 0.0;
		$syy = 0.0;
		$sxy = 0.0;
		foreach ( $pairs as $p ) {
			$dx = (float) $p['x'] - $mx;
			$dy = (float) $p['y'] - $my;
			$sxx += $dx * $dx;
			$syy += $dy * $dy;
			$sxy += $dx * $dy;
		}
		if ( $sxx < 1e-8 || $syy < 1e-8 ) {
			return null;
		}
		$slope     = $sxy / $sxx;
		$intercept = $my - $slope * $mx;
		$r         = $sxy / sqrt( $sxx * $syy );
		if ( ! is_finite( $r ) ) {
			return null;
		}
		return array(
			'n'         => $n,
			'mean_x'    => round( $mx, 2 ),
			'mean_y'    => round( $my, 2 ),
			'slope'     => round( $slope, 4 ),
			'intercept' => round( $intercept, 4 ),
			'r'         => round( $r, 4 ),
			'r2'        => round( $r * $r, 4 ),
		);
	}

	/**
	 * @param list<float> $vals
	 */
	private static function median_values( array $vals ): float {
		if ( empty( $vals ) ) {
			return 0.0;
		}
		sort( $vals, SORT_NUMERIC );
		$c = count( $vals );
		$mid = (int) floor( $c / 2 );
		if ( $c % 2 === 1 ) {
			return (float) $vals[ $mid ];
		}
		return ( (float) $vals[ $mid - 1 ] + (float) $vals[ $mid ] ) / 2.0;
	}
}
