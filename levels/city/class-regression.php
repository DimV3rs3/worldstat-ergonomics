<?php
/**
 * Регрессия и сводная статистика по листовому E городов одной страны (публичный блок выбора города).
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_City_Regression {

	private const MIN_PAIRS = 5;

	/**
	 * @param array<string, array<string, mixed>> $city_payload id города => результат {@see WSErgo_Data::get_city_leaf_public_detail()}.
	 * @return array<string, mixed>
	 */
	public static function analyze_country_payload( array $city_payload, array $opts = [] ): array {
		$scope = isset( $opts['scope'] ) && (string) $opts['scope'] === 'global' ? 'global' : 'country';
		$ys = [];
		foreach ( $city_payload as $cid => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$le = isset( $row['leaf_e'] ) ? (float) str_replace( ',', '.', (string) $row['leaf_e'] ) : 0.0;
			if ( $le > 0 && is_finite( $le ) ) {
				$ys[ (string) $cid ] = $le;
			}
		}
		$n_leaf = count( $ys );
		if ( $n_leaf < self::MIN_PAIRS ) {
			$notice = $scope === 'global'
				? __( 'Для межстрановой регрессии нужно не меньше пяти городов с рассчитанным листовым E и баллами показателей.', 'worldstat-ergonomics' )
				: __( 'Для регрессии по стране нужно не меньше пяти городов с рассчитанным листовым E и баллами показателей.', 'worldstat-ergonomics' );
			return [
				'usable'          => false,
				'notice'          => $notice,
				'n_leaf'          => $n_leaf,
				'univariate'      => [],
				'univariate_full' => [],
				'peer_stats'      => [],
				'axis_peers'      => [],
				'median_leaf'     => null,
			];
		}

		$median_leaf = self::median_values( array_values( $ys ) );

		$defs_by_id   = [];
		$dim_labels   = class_exists( 'WSErgo_Model' ) ? WSErgo_Model::get_dimension_labels() : [];
		foreach ( class_exists( 'WSErgo_Indicators' ) ? WSErgo_Indicators::get_definitions() : [] as $dr ) {
			if ( is_array( $dr ) && ! empty( $dr['id'] ) ) {
				$defs_by_id[ (string) $dr['id'] ] = $dr;
			}
		}

		$indicator_ids = [];
		foreach ( $city_payload as $row ) {
			if ( ! is_array( $row ) || empty( $row['indicators'] ) || ! is_array( $row['indicators'] ) ) {
				continue;
			}
			foreach ( $row['indicators'] as $ind ) {
				if ( ! is_array( $ind ) || empty( $ind['id'] ) ) {
					continue;
				}
				$indicator_ids[ (string) $ind['id'] ] = true;
			}
		}
		$indicator_ids = array_keys( $indicator_ids );

		$peer_stats   = [];
		$univariate   = [];
		foreach ( $indicator_ids as $iid ) {
			$xs_all = [];
			foreach ( $city_payload as $cid => $row ) {
				if ( ! is_array( $row ) || empty( $row['indicators'] ) ) {
					continue;
				}
				$xv = self::find_indicator_score_value( $row['indicators'], $iid );
				if ( $xv !== null && is_finite( $xv ) ) {
					$xs_all[] = $xv;
				}
			}
			if ( count( $xs_all ) < self::MIN_PAIRS ) {
				continue;
			}
			sort( $xs_all, SORT_NUMERIC );
			$peer_stats[ $iid ] = [
				'median' => self::median_sorted( $xs_all ),
				'p25'    => self::percentile_sorted( $xs_all, 0.25 ),
				'p75'    => self::percentile_sorted( $xs_all, 0.75 ),
				'n'      => count( $xs_all ),
			];

			$pairs = [];
			foreach ( $city_payload as $cid => $row ) {
				if ( ! is_array( $row ) || ! isset( $ys[ (string) $cid ] ) ) {
					continue;
				}
				$xv = self::find_indicator_score_value( $row['indicators'], $iid );
				if ( $xv === null || ! is_finite( $xv ) ) {
					continue;
				}
				$cname   = isset( $row['name'] ) ? (string) $row['name'] : '';
				$country = isset( $row['country_name'] ) ? (string) $row['country_name'] : '';
				$pairs[] = [
					'id'      => (int) $cid,
					'name'    => $cname,
					'country' => $country,
					'x'       => $xv,
					'y'       => $ys[ (string) $cid ],
				];
			}
			if ( count( $pairs ) < self::MIN_PAIRS ) {
				continue;
			}
			$reg = self::ols_y_on_x( $pairs );
			if ( $reg === null ) {
				continue;
			}
			$label = self::find_indicator_label( $city_payload, $iid );
			$def   = $defs_by_id[ $iid ] ?? null;
			$dim_k = is_array( $def ) && isset( $def['dimension'] ) ? (string) $def['dimension'] : '';
			$dim_lab = ( $dim_k !== '' && isset( $dim_labels[ $dim_k ] ) ) ? (string) $dim_labels[ $dim_k ] : $dim_k;

			$scatter = [];
			foreach ( $pairs as $p ) {
				$scatter[] = [
					'id'      => (int) ( $p['id'] ?? 0 ),
					'name'    => (string) ( $p['name'] ?? '' ),
					'country' => (string) ( $p['country'] ?? '' ),
					'x'       => round( (float) $p['x'], 4 ),
					'y'       => round( (float) $p['y'], 4 ),
				];
			}

			$univariate[] = array_merge(
				[
					'id'               => $iid,
					'label'            => $label !== '' ? $label : $iid,
					'dimension_key'    => $dim_k,
					'dimension_label'  => $dim_lab,
					'unit'             => is_array( $def ) ? (string) ( $def['unit'] ?? '' ) : '',
					'description'      => self::indicator_description_text( $def, $iid, $dim_lab ),
					'scatter'          => $scatter,
				],
				$reg
			);
		}

		usort(
			$univariate,
			static function ( $a, $b ) {
				$ra = isset( $a['r2'] ) ? abs( (float) $a['r2'] ) : 0.0;
				$rb = isset( $b['r2'] ) ? abs( (float) $b['r2'] ) : 0.0;
				return $rb <=> $ra;
			}
		);
		$uni_limit = (int) apply_filters( 'wsergo_city_regression_univariate_limit', 12 );
		if ( $uni_limit < 1 ) {
			$uni_limit = 12;
		}
		/** Полный список для рекомендаций на клиенте (не только топ для таблицы). */
		$univariate_full = $univariate;
		$univariate      = array_slice( $univariate, 0, $uni_limit );

		$axis_peers = [];
		foreach ( WSErgo_Model::DIMENSION_KEYS as $dim ) {
			$vals = [];
			foreach ( $city_payload as $row ) {
				if ( ! is_array( $row ) || empty( $row['axes'] ) || ! is_array( $row['axes'] ) ) {
					continue;
				}
				if ( ! isset( $row['axes'][ $dim ] ) || ! is_numeric( $row['axes'][ $dim ] ) ) {
					continue;
				}
				$v = (float) $row['axes'][ $dim ];
				if ( is_finite( $v ) && $v > 0 ) {
					$vals[] = $v;
				}
			}
			if ( count( $vals ) < self::MIN_PAIRS ) {
				continue;
			}
			sort( $vals, SORT_NUMERIC );
			$axis_peers[ $dim ] = [
				'median' => self::median_sorted( $vals ),
				'p25'    => self::percentile_sorted( $vals, 0.25 ),
				'p75'    => self::percentile_sorted( $vals, 0.75 ),
				'n'      => count( $vals ),
			];
		}

		foreach ( [ 'F', 'Cm', 'H', 'A', 'S', 'Ct' ] as $mak ) {
			$vals = [];
			foreach ( $city_payload as $row ) {
				if ( ! is_array( $row ) || empty( $row['axes'] ) || ! is_array( $row['axes'] ) ) {
					continue;
				}
				if ( ! isset( $row['axes'][ $mak ] ) || ! is_numeric( $row['axes'][ $mak ] ) ) {
					continue;
				}
				$v = (float) $row['axes'][ $mak ];
				if ( is_finite( $v ) && $v > 0 ) {
					$vals[] = $v;
				}
			}
			if ( count( $vals ) < self::MIN_PAIRS ) {
				continue;
			}
			sort( $vals, SORT_NUMERIC );
			$axis_peers[ $mak ] = [
				'median' => self::median_sorted( $vals ),
				'p25'    => self::percentile_sorted( $vals, 0.25 ),
				'p75'    => self::percentile_sorted( $vals, 0.75 ),
				'n'      => count( $vals ),
			];
		}

		return [
			'usable'            => true,
			'notice'            => '',
			'n_leaf'            => $n_leaf,
			'median_leaf'       => round( (float) $median_leaf, 2 ),
			'univariate'        => $univariate,
			'univariate_full'   => $univariate_full,
			'peer_stats'        => $peer_stats,
			'axis_peers'        => $axis_peers,
		];
	}

	/**
	 * OLS y ~ x. Элементы $pairs должны содержать ключи x и y (допускаются id, name для графика).
	 *
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
		return [
			'n'          => $n,
			'mean_x'     => round( $mx, 2 ),
			'mean_y'     => round( $my, 2 ),
			'slope'      => round( $slope, 4 ),
			'intercept'  => round( $intercept, 4 ),
			'r'          => round( $r, 4 ),
			'r2'         => round( $r * $r, 4 ),
		];
	}

	/**
	 * @param list<float> $sorted
	 */
	private static function percentile_sorted( array $sorted, float $p ): float {
		$c = count( $sorted );
		if ( $c === 0 ) {
			return 0.0;
		}
		if ( $c === 1 ) {
			return (float) $sorted[0];
		}
		$idx = ( $c - 1 ) * $p;
		$lo  = (int) floor( $idx );
		$hi  = (int) ceil( $idx );
		if ( $lo === $hi ) {
			return (float) $sorted[ $lo ];
		}
		$w = $idx - $lo;
		return (float) $sorted[ $lo ] * ( 1 - $w ) + (float) $sorted[ $hi ] * $w;
	}

	/**
	 * @param list<float> $sorted
	 */
	private static function median_sorted( array $sorted ): float {
		$c = count( $sorted );
		if ( $c === 0 ) {
			return 0.0;
		}
		$mid = (int) floor( $c / 2 );
		if ( $c % 2 === 1 ) {
			return (float) $sorted[ $mid ];
		}
		return ( (float) $sorted[ $mid - 1 ] + (float) $sorted[ $mid ] ) / 2.0;
	}

	/**
	 * @param list<float> $vals
	 */
	private static function median_values( array $vals ): float {
		if ( empty( $vals ) ) {
			return 0.0;
		}
		sort( $vals, SORT_NUMERIC );
		return self::median_sorted( $vals );
	}

	/**
	 * @param mixed $indicators
	 */
	private static function find_indicator_score_value( $indicators, string $id ): ?float {
		if ( ! is_array( $indicators ) ) {
			return null;
		}
		foreach ( $indicators as $ind ) {
			if ( ! is_array( $ind ) || (string) ( $ind['id'] ?? '' ) !== $id ) {
				continue;
			}
			if ( isset( $ind['score_value'] ) && is_numeric( $ind['score_value'] ) ) {
				$v = (float) $ind['score_value'];
				return is_finite( $v ) ? $v : null;
			}
			if ( isset( $ind['score'] ) && $ind['score'] !== null && $ind['score'] !== '' && is_numeric( $ind['score'] ) ) {
				$v = (float) str_replace( ',', '.', (string) $ind['score'] );
				return is_finite( $v ) ? $v : null;
			}
			return null;
		}
		return null;
	}

	/**
	 * Текст для блока «показатель» под таблицей регрессии.
	 *
	 * @param ?array<string,mixed> $def Строка из {@see WSErgo_Indicators::get_definitions()} или null.
	 */
	private static function indicator_description_text( ?array $def, string $iid, string $dimension_label_ru ): string {
		if ( ! is_array( $def ) ) {
			return sprintf(
				/* translators: %s: technical indicator id (slug) */
				__( 'Идентификатор в системе: «%s». В глобальных определениях листовых показателей запись не найдена — в регрессии используется подпись из карты полей городов; нормализация в балл 0–100 задаётся там же, где задано сопоставление полей.', 'worldstat-ergonomics' ),
				$iid
			);
		}
		$dir_txt = isset( $def['direction'] ) && 'lower_better' === $def['direction']
			? __( 'меньше сырое значение — выше балл (показатель «меньше — лучше»)', 'worldstat-ergonomics' )
			: __( 'больше сырое значение — выше балл (показатель «больше — лучше»)', 'worldstat-ergonomics' );
		$unit_show = isset( $def['unit'] ) && (string) $def['unit'] !== ''
			? (string) $def['unit']
			: __( 'не задана', 'worldstat-ergonomics' );
		$dim_show = $dimension_label_ru !== ''
			? $dimension_label_ru
			: ( isset( $def['dimension'] ) ? (string) $def['dimension'] : '—' );

		return sprintf(
			/* translators: 1: human label, 2: dimension name, 3: raw unit, 4: vmin, 5: vmax, 6: direction explanation, 7: id slug */
			__( 'Подпись: «%1$s». Относится к измерению «%2$s». Единица сырого значения: %3$s. Для перевода сырого числа в балл 0–100 используется линейная нормализация на отрезке [%4$s … %5$s]; %6$s. Технический id: %7$s.', 'worldstat-ergonomics' ),
			(string) ( $def['label'] ?? $iid ),
			$dim_show,
			$unit_show,
			(string) ( $def['vmin'] ?? '0' ),
			(string) ( $def['vmax'] ?? '100' ),
			$dir_txt,
			$iid
		);
	}

	private static function find_indicator_label( array $city_payload, string $id ): string {
		foreach ( $city_payload as $row ) {
			if ( ! is_array( $row ) || empty( $row['indicators'] ) ) {
				continue;
			}
			foreach ( $row['indicators'] as $ind ) {
				if ( is_array( $ind ) && (string) ( $ind['id'] ?? '' ) === $id && ! empty( $ind['label'] ) ) {
					return (string) $ind['label'];
				}
			}
		}
		return '';
	}

	/**
	 * Рекомендации по измерениям и показателям на основе сравнения с городами страны и однофакторной регрессии E ~ балл показателя.
	 *
	 * @param array<string, mixed> $city_row  Результат {@see WSErgo_Data::get_city_leaf_public_detail()}.
	 * @param array<string, mixed> $analysis Результат {@see self::analyze_country_payload()}.
	 * @return array<int, string>
	 */
	public static function recommendations_for_city( int $city_id, array $city_row, array $analysis ): array {
		unset( $city_id );
		$out = [];
		if ( empty( $city_row ) ) {
			$out[] = __( 'Нет данных по выбранному городу.', 'worldstat-ergonomics' );
			return $out;
		}
		if ( empty( $analysis['usable'] ) ) {
			$note = isset( $analysis['notice'] ) ? (string) $analysis['notice'] : '';
			if ( $note !== '' ) {
				$out[] = $note;
			} else {
				$out[] = __( 'Недостаточно городов страны с рассчитанным индексом E для регрессионного сравнения. Ниже — фактические показатели выбранного города.', 'worldstat-ergonomics' );
			}
		}

		$labels = class_exists( 'WSErgo_Model' ) ? WSErgo_Model::get_dimension_labels() : [];
		$axes    = isset( $city_row['axes'] ) && is_array( $city_row['axes'] ) ? $city_row['axes'] : [];
		$peers   = isset( $analysis['axis_peers'] ) && is_array( $analysis['axis_peers'] ) ? $analysis['axis_peers'] : [];

		foreach ( class_exists( 'WSErgo_Model' ) ? WSErgo_Model::DIMENSION_KEYS : [] as $dim ) {
			if ( count( $out ) >= 10 ) {
				break;
			}
			if ( ! isset( $axes[ $dim ] ) || ! is_numeric( $axes[ $dim ] ) ) {
				continue;
			}
			$v = (float) $axes[ $dim ];
			if ( $v <= 0 || ! isset( $peers[ $dim ] ) || ! is_array( $peers[ $dim ] ) ) {
				continue;
			}
			$p   = $peers[ $dim ];
			$p25 = isset( $p['p25'] ) ? (float) $p['p25'] : null;
			$p75 = isset( $p['p75'] ) ? (float) $p['p75'] : null;
			$med = isset( $p['median'] ) ? (float) $p['median'] : null;
			if ( $p25 === null || $p75 === null || $med === null || ! is_finite( $p25 ) || ! is_finite( $p75 ) || ! is_finite( $med ) ) {
				continue;
			}
			$lab = isset( $labels[ $dim ] ) ? $labels[ $dim ] : $dim;
			if ( $v < $p25 ) {
				$out[] = sprintf(
					/* translators: 1: dimension name, 2: city score, 3: country peer median */
					__( 'Измерение «%1$s» у выбранного города (%2$s) заметно ниже типичного уровня по городам страны (медиана %3$s). Имеет смысл включить этот блок в программу улучшения среды.', 'worldstat-ergonomics' ),
					$lab,
					number_format_i18n( $v, 1 ),
					number_format_i18n( $med, 1 )
				);
			} elseif ( $v > $p75 ) {
				$out[] = sprintf(
					/* translators: 1: dimension name, 2: city score */
					__( 'Измерение «%1$s» (%2$s) выше верхней квартильной границы по стране — сильная сторона; её можно использовать как опору при планировании остальных направлений.', 'worldstat-ergonomics' ),
					$lab,
					number_format_i18n( $v, 1 )
				);
			}
		}

		if ( ! empty( $analysis['usable'] ) && isset( $analysis['univariate_full'] ) && is_array( $analysis['univariate_full'] ) ) {
			$peer_stats = isset( $analysis['peer_stats'] ) && is_array( $analysis['peer_stats'] ) ? $analysis['peer_stats'] : [];
			$inds       = isset( $city_row['indicators'] ) && is_array( $city_row['indicators'] ) ? $city_row['indicators'] : [];
			foreach ( $analysis['univariate_full'] as $u ) {
				if ( count( $out ) >= 10 ) {
					break;
				}
				if ( ! is_array( $u ) ) {
					continue;
				}
				$r2 = isset( $u['r2'] ) ? abs( (float) $u['r2'] ) : 0.0;
				if ( $r2 < 0.06 ) {
					continue;
				}
				$slope = isset( $u['slope'] ) ? (float) $u['slope'] : 0.0;
				if ( $slope <= 0 ) {
					continue;
				}
				$iid = isset( $u['id'] ) ? (string) $u['id'] : '';
				if ( $iid === '' ) {
					continue;
				}
				$city_score = self::find_indicator_score_value( $inds, $iid );
				if ( $city_score === null || ! is_finite( $city_score ) ) {
					continue;
				}
				$ps = $peer_stats[ $iid ] ?? null;
				if ( ! is_array( $ps ) || ! isset( $ps['median'] ) ) {
					continue;
				}
				$median_peer = (float) $ps['median'];
				if ( ! is_finite( $median_peer ) ) {
					continue;
				}
				if ( $city_score >= $median_peer - 1.0 ) {
					continue;
				}
				$ulab = isset( $u['label'] ) ? (string) $u['label'] : $iid;
				$out[] = sprintf(
					/* translators: 1: indicator label, 2: R², 3: city score 0-100, 4: country median score */
					__( 'По городам страны более высокий балл показателя «%1$s» связан с более высоким E (R² ≈ %2$s). У выбранного города балл %3$s при медиане по стране %4$s — рассмотрите меры, повышающие этот показатель.', 'worldstat-ergonomics' ),
					$ulab,
					number_format_i18n( $r2, 2 ),
					number_format_i18n( $city_score, 1 ),
					number_format_i18n( $median_peer, 1 )
				);
			}
		}

		if ( empty( $out ) ) {
			if ( ! empty( $analysis['usable'] ) ) {
				$out[] = __( 'По выбранному городу и текущей выборке городов страны отклонений от типичных уровней (и устойчивых связей «низкий балл показателя — более низкий E») не выявлено.', 'worldstat-ergonomics' );
			} else {
				$out[] = __( 'Сформируйте полноту данных по городам и пересчитайте индексы: тогда появятся сравнение с медианой страны и регрессионные подсказки.', 'worldstat-ergonomics' );
			}
		}
		return $out;
	}
}
