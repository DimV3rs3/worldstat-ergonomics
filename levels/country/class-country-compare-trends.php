<?php
/**
 * Регрессия показателей по годам для сравнения выбранных стран (как «Аналитика показателей»).
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_Country_Compare_Trends {

	/** Максимум стран на одном графике. */
	private const MAX_CHART_COUNTRIES = 10;

	/** @var list<string> */
	private const CHART_COLORS = array( '#3366cc', '#dc3912', '#ff9900', '#109618', '#990099', '#0099c6', '#dd4477', '#66aa00', '#b82e2e', '#316395' );

	/**
	 * @return list<array{id:string,label:string,slug:string}>
	 */
	public static function get_metric_options_for_post( int $post_id ): array {
		if ( $post_id < 1 || ! class_exists( 'WorldStat_Data' ) ) {
			return array();
		}
		$data = worldstat_platform()->data ?? null;
		if ( ! $data instanceof WorldStat_Data ) {
			return array();
		}
		$out = array();
		foreach ( $data->get_country_grid_items( $post_id ) as $item ) {
			$metric = self::grid_item_to_metric( $item );
			if ( null === $metric ) {
				continue;
			}
			$out[] = array(
				'id'    => (string) $metric['id'],
				'label' => (string) $metric['label'],
				'slug'  => (string) $metric['slug'],
			);
		}
		usort(
			$out,
			static function ( $a, $b ) {
				return strcmp( (string) ( $a['label'] ?? '' ), (string) ( $b['label'] ?? '' ) );
			}
		);
		return $out;
	}

	/**
	 * @param list<string> $iso2_list
	 * @return array<string,mixed>
	 */
	public static function build_comparison( array $iso2_list, string $metric_id ): array {
		$metric_id = sanitize_text_field( $metric_id );
		if ( $metric_id === '' ) {
			return array(
				'ok'      => false,
				'message' => __( 'Выберите показатель.', 'worldstat-ergonomics' ),
			);
		}
		if ( ! class_exists( 'WorldStat_Country_ML' ) || ! class_exists( 'WorldStat_Data' ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Модуль аналитики платформы недоступен.', 'worldstat-ergonomics' ),
			);
		}

		$data = worldstat_platform()->data ?? null;
		if ( ! $data instanceof WorldStat_Data ) {
			return array(
				'ok'      => false,
				'message' => __( 'Модуль данных недоступен.', 'worldstat-ergonomics' ),
			);
		}

		$iso2_list = self::normalize_iso2_list( $iso2_list );
		if ( empty( $iso2_list ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Отметьте хотя бы одну страну в таблице.', 'worldstat-ergonomics' ),
			);
		}
		if ( count( $iso2_list ) > self::MAX_CHART_COUNTRIES ) {
			$iso2_list = array_slice( $iso2_list, 0, self::MAX_CHART_COUNTRIES );
		}

		$ref_slug  = '';
		$ref_label = '';
		foreach ( $iso2_list as $iso2 ) {
			$post_id = class_exists( 'WorldStat_Country_CPT' )
				? (int) WorldStat_Country_CPT::get_post_id_by_code( $iso2 )
				: 0;
			$ref     = self::find_metric_in_country( $data, $post_id, $metric_id, '' );
			if ( null !== $ref ) {
				$ref_slug  = (string) $ref['slug'];
				$ref_label = (string) $ref['label'];
				break;
			}
		}

		/** @var list<array<string,mixed>> $country_results */
		$country_results = array();
		$label_union     = array();
		$metric_label    = $ref_label;

		foreach ( $iso2_list as $iso2 ) {
			$post_id = class_exists( 'WorldStat_Country_CPT' )
				? (int) WorldStat_Country_CPT::get_post_id_by_code( $iso2 )
				: 0;
			$name    = self::country_name( $iso2, $post_id );

			$metric = self::find_metric_in_country( $data, $post_id, $metric_id, $ref_slug );
			if ( null === $metric ) {
				$country_results[] = array(
					'iso2'       => $iso2,
					'name'       => $name,
					'ok'         => false,
					'message'    => __( 'Показатель не найден для этой страны.', 'worldstat-ergonomics' ),
					'regression' => array( 'ok' => false ),
				);
				continue;
			}

			if ( $metric_label === '' ) {
				$metric_label = (string) ( $metric['label'] ?? $metric_id );
			}

			$regression = WorldStat_Country_ML::regression_trend_for_metric( $metric );
			$pct_change = null;
			if ( ! empty( $regression['ok'] ) ) {
				$pct_change = self::regression_pct_change( $metric, $regression );
			}
			$country_results[] = array(
				'iso2'        => $iso2,
				'name'        => $name,
				'ok'          => ! empty( $regression['ok'] ),
				'message'     => isset( $regression['message'] ) ? (string) $regression['message'] : '',
				'regression'  => $regression,
				'pct_change'  => $pct_change,
			);

			if ( ! empty( $regression['ok'] ) && ! empty( $regression['chart']['labels'] ) && is_array( $regression['chart']['labels'] ) ) {
				foreach ( $regression['chart']['labels'] as $yl ) {
					$label_union[ (string) $yl ] = true;
				}
			}
		}

		$ok_rows = array_values(
			array_filter(
				$country_results,
				static function ( $cr ) {
					return ! empty( $cr['ok'] );
				}
			)
		);
		if ( empty( $ok_rows ) ) {
			return array(
				'ok'        => false,
				'message'   => __( 'Недостаточно данных по годам для выбранных стран (нужно ≥4 лет).', 'worldstat-ergonomics' ),
				'countries' => $country_results,
			);
		}

		$years            = array_keys( $label_union );
		sort( $years, SORT_NUMERIC );
		$combined         = self::build_combined_chart( $ok_rows, $years, $metric_label );
		$charts_separate  = self::build_separate_charts( $ok_rows, $metric_label );
		$legend_items     = self::build_legend_items( $combined['datasets'] ?? array() );
		$analysis         = self::build_analysis( $ok_rows, $metric_label );

		$ergo_analysis = class_exists( 'WSErgo_Tier_Classifier' )
			? WSErgo_Tier_Classifier::build_compare_classification_analysis( $iso2_list )
			: array();

		return array(
			'ok'              => true,
			'title'           => sprintf(
				/* translators: %s: metric label */
				__( 'Сравнение: %s', 'worldstat-ergonomics' ),
				$metric_label !== '' ? $metric_label : $metric_id
			),
			'description'     => __( 'Линейный тренд показателя по годам до 2050 г. Сравнение по темпу изменения (%), а не по сумме абсолютных значений разных масштабов.', 'worldstat-ergonomics' ),
			'metric_label'    => $metric_label,
			'countries'       => $country_results,
			'chart'           => $combined,
			'charts_separate' => $charts_separate,
			'legend'          => $legend_items,
			'analysis'        => $analysis,
			'ergo_analysis'   => $ergo_analysis,
			'colors'          => self::CHART_COLORS,
		);
	}

	/**
	 * @param list<string> $iso2_list
	 * @return list<string>
	 */
	private static function normalize_iso2_list( array $iso2_list ): array {
		return array_values(
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
	}

	private static function country_name( string $iso2, int $post_id ): string {
		if ( $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( $post instanceof WP_Post ) {
				return (string) $post->post_title;
			}
		}
		return $iso2;
	}

	/**
	 * @param list<array<string,mixed>> $ok_rows
	 * @param list<string>              $years
	 * @return array<string,mixed>
	 */
	private static function build_combined_chart( array $ok_rows, array $years, string $metric_label ): array {
		$datasets = array();
		$ci       = 0;

		foreach ( $ok_rows as $cr ) {
			if ( empty( $cr['regression']['chart'] ) ) {
				continue;
			}
			$color   = self::CHART_COLORS[ $ci % count( self::CHART_COLORS ) ];
			$country = (string) ( $cr['name'] ?? $cr['iso2'] ?? '' );
			$pair    = self::align_country_series( $cr['regression']['chart'], $years );
			++$ci;

			$datasets[] = array(
				'label'       => $country . ' — ' . __( 'факт', 'worldstat-ergonomics' ),
				'data'        => $pair['actual'],
				'color'       => $color,
				'country'     => $country,
				'iso2'        => (string) ( $cr['iso2'] ?? '' ),
				'series_type' => 'fact',
			);
			$datasets[] = array(
				'label'            => $country . ' — ' . __( 'тренд (OLS)', 'worldstat-ergonomics' ),
				'data'             => $pair['trend'],
				'color'            => $color,
				'borderDash'       => array( 6, 4 ),
				'pointRadius'      => 0,
				'pointHoverRadius' => 4,
				'fill'             => false,
				'country'          => $country,
				'iso2'             => (string) ( $cr['iso2'] ?? '' ),
				'series_type'      => 'trend',
			);
		}

		return array(
			'type'     => 'line',
			'labels'   => $years,
			'datasets' => $datasets,
			'x_label'  => __( 'Год', 'worldstat-ergonomics' ),
			'y_label'  => $metric_label,
			'height'   => 380,
			'legend'   => false,
		);
	}

	/**
	 * @param list<array<string,mixed>> $ok_rows
	 * @return list<array<string,mixed>>
	 */
	private static function build_separate_charts( array $ok_rows, string $metric_label ): array {
		$out = array();
		$ci  = 0;
		foreach ( $ok_rows as $cr ) {
			if ( empty( $cr['regression']['chart'] ) || ! is_array( $cr['regression']['chart'] ) ) {
				continue;
			}
			$color   = self::CHART_COLORS[ $ci % count( self::CHART_COLORS ) ];
			$country = (string) ( $cr['name'] ?? $cr['iso2'] ?? '' );
			$chart   = $cr['regression']['chart'];
			$ds      = array();
			if ( ! empty( $chart['datasets'][0] ) ) {
				$ds[] = array_merge(
					(array) $chart['datasets'][0],
					array(
						'color'       => $color,
						'label'       => __( 'Факт', 'worldstat-ergonomics' ),
						'series_type' => 'fact',
					)
				);
			}
			if ( ! empty( $chart['datasets'][1] ) ) {
				$ds[] = array_merge(
					(array) $chart['datasets'][1],
					array(
						'color'            => $color,
						'borderDash'       => array( 6, 4 ),
						'pointRadius'      => 0,
						'fill'             => false,
						'series_type'      => 'trend',
					)
				);
			}
			$out[] = array(
				'iso2'   => (string) ( $cr['iso2'] ?? '' ),
				'name'   => $country,
				'color'  => $color,
				'stats'  => $cr['regression']['stats'] ?? array(),
				'chart'  => array(
					'type'     => 'line',
					'labels'   => $chart['labels'] ?? array(),
					'datasets' => $ds,
					'x_label'  => __( 'Год', 'worldstat-ergonomics' ),
					'y_label'  => $metric_label,
					'height'   => 260,
					'legend'   => false,
				),
			);
			++$ci;
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $chart
	 * @param list<string>        $years
	 * @return array{actual:list<mixed>,trend:list<mixed>}
	 */
	private static function align_country_series( array $chart, array $years ): array {
		$labels = isset( $chart['labels'] ) && is_array( $chart['labels'] ) ? $chart['labels'] : array();
		$by_year = array();
		$trend_by_year = array();
		if ( ! empty( $chart['datasets'][0]['data'] ) && is_array( $chart['datasets'][0]['data'] ) ) {
			foreach ( $labels as $i => $yl ) {
				$by_year[ (string) $yl ] = $chart['datasets'][0]['data'][ $i ] ?? null;
			}
		}
		if ( ! empty( $chart['datasets'][1]['data'] ) && is_array( $chart['datasets'][1]['data'] ) ) {
			foreach ( $labels as $i => $yl ) {
				$trend_by_year[ (string) $yl ] = $chart['datasets'][1]['data'][ $i ] ?? null;
			}
		}
		$actual = array();
		$trend  = array();
		foreach ( $years as $y ) {
			$actual[] = array_key_exists( $y, $by_year ) ? $by_year[ $y ] : null;
			$trend[]  = array_key_exists( $y, $trend_by_year ) ? $trend_by_year[ $y ] : null;
		}
		return array(
			'actual' => $actual,
			'trend'  => $trend,
		);
	}

	/**
	 * @param list<array<string,mixed>> $datasets
	 * @return list<array{index:int,label:string,color:string,country:string,iso2:string,series_type:string}>
	 */
	private static function build_legend_items( array $datasets ): array {
		$items = array();
		foreach ( $datasets as $i => $ds ) {
			if ( ! is_array( $ds ) ) {
				continue;
			}
			$items[] = array(
				'index'       => (int) $i,
				'label'       => (string) ( $ds['label'] ?? '' ),
				'color'       => (string) ( $ds['color'] ?? '#3366cc' ),
				'country'     => (string) ( $ds['country'] ?? '' ),
				'iso2'        => (string) ( $ds['iso2'] ?? '' ),
				'series_type' => (string) ( $ds['series_type'] ?? '' ),
			);
		}
		return $items;
	}

	/**
	 * @param list<array<string,mixed>> $ok_rows
	 * @return array<string,mixed>
	 */
	private static function format_plain_number( float $n, int $decimals = 2 ): string {
		return number_format( $n, $decimals, ',', ' ' );
	}

	/**
	 * @param array{series:array<int,float>} $metric
	 * @param array<string,mixed>            $regression
	 */
	private static function regression_pct_change( array $metric, array $regression ): ?float {
		$series = $metric['series'] ?? array();
		if ( ! is_array( $series ) || empty( $series ) ) {
			return null;
		}
		ksort( $series, SORT_NUMERIC );
		$base_year = 0;
		$base_val  = 0.0;
		$cutoff    = (int) gmdate( 'Y' ) + 1;
		foreach ( $series as $y => $v ) {
			if ( (int) $y <= $cutoff ) {
				$base_year = (int) $y;
				$base_val  = (float) $v;
			}
		}
		if ( $base_year < 1 ) {
			$base_year = (int) array_key_first( $series );
			$base_val  = (float) reset( $series );
		}
		$st = $regression['stats']['forecast']['value'] ?? null;
		if ( ! is_numeric( $st ) || abs( $base_val ) < 1e-9 ) {
			return null;
		}
		return round( ( ( (float) $st - $base_val ) / abs( $base_val ) ) * 100.0, 1 );
	}

	private static function build_analysis( array $ok_rows, string $metric_label ): array {
		$rows = array();
		foreach ( $ok_rows as $cr ) {
			$st = $cr['regression']['stats'] ?? array();
			if ( ! is_array( $st ) || ! isset( $st['forecast']['value'] ) ) {
				continue;
			}
			$series = array();
			if ( ! empty( $cr['regression']['chart']['datasets'][0]['data'] )
				&& is_array( $cr['regression']['chart']['labels'] ) ) {
				$labels = $cr['regression']['chart']['labels'];
				$data0  = $cr['regression']['chart']['datasets'][0]['data'];
				foreach ( $labels as $i => $yl ) {
					$val = $data0[ $i ] ?? null;
					if ( $val !== null && is_numeric( $val ) ) {
						$series[ (int) $yl ] = (float) $val;
					}
				}
			}
			$base_year = 0;
			$base_val  = 0.0;
			if ( ! empty( $series ) ) {
				ksort( $series, SORT_NUMERIC );
				foreach ( $series as $y => $v ) {
					if ( $y <= (int) gmdate( 'Y' ) + 1 ) {
						$base_year = (int) $y;
						$base_val  = (float) $v;
					}
				}
				if ( $base_year < 1 ) {
					$base_year = (int) array_key_first( $series );
					$base_val  = (float) reset( $series );
				}
			}
			$forecast = (float) $st['forecast']['value'];
			$pct      = null;
			if ( abs( $base_val ) > 1e-9 ) {
				$pct = ( ( $forecast - $base_val ) / abs( $base_val ) ) * 100.0;
			}
			$rows[] = array(
				'name'      => (string) ( $cr['name'] ?? $cr['iso2'] ),
				'iso2'      => (string) ( $cr['iso2'] ?? '' ),
				'r2'        => isset( $st['r2'] ) ? (float) $st['r2'] : 0.0,
				'slope'     => isset( $st['slope'] ) ? (float) $st['slope'] : 0.0,
				'direction' => (string) ( $st['direction'] ?? '' ),
				'forecast'  => $forecast,
				'year'      => (int) ( $st['forecast']['year'] ?? 2050 ),
				'base_year' => $base_year,
				'base_val'  => $base_val,
				'pct'       => $pct,
			);
		}

		if ( empty( $rows ) ) {
			return array(
				'summary'    => '',
				'highlights' => array(),
				'insights'   => array(),
			);
		}

		$with_pct = array_values(
			array_filter(
				$rows,
				static function ( $r ) {
					return $r['pct'] !== null && is_finite( (float) $r['pct'] );
				}
			)
		);
		usort(
			$with_pct,
			static function ( $a, $b ) {
				return ( (float) ( $b['pct'] ?? 0 ) ) <=> ( (float) ( $a['pct'] ?? 0 ) );
			}
		);

		$forecast_sorted = $rows;
		usort(
			$forecast_sorted,
			static function ( $a, $b ) {
				return ( $b['forecast'] ?? 0 ) <=> ( $a['forecast'] ?? 0 );
			}
		);
		$abs_leader = $forecast_sorted[0];

		if ( ! empty( $with_pct ) ) {
			$pct_leader = $with_pct[0];
			$pct_laggard = $with_pct[ count( $with_pct ) - 1 ];
			$summary     = sprintf(
				/* translators: 1: metric, 2: count, 3: country, 4: pct, 5: year */
				__(
					'По показателю «%1$s» сравнены %2$d стран. Наибольший относительный рост к %5$d г. у %3$s (%4$s%% к базе %6$d г.).',
					'worldstat-ergonomics'
				),
				$metric_label,
				count( $rows ),
				$pct_leader['name'],
				self::format_plain_number( (float) $pct_leader['pct'], 1 ),
				(int) $pct_leader['year'],
				(int) $pct_leader['base_year']
			);
		} else {
			$summary = sprintf(
				/* translators: 1: metric, 2: count */
				__( 'По показателю «%1$s» сравнены %2$d стран. Недостаточно данных для расчёта относительного изменения.', 'worldstat-ergonomics' ),
				$metric_label,
				count( $rows )
			);
			$pct_leader  = null;
			$pct_laggard = null;
		}

		$insights = array();
		if ( ! empty( $with_pct ) && count( $with_pct ) > 1 ) {
			$insights[] = sprintf(
				/* translators: 1: country, 2: pct, 3: country, 4: pct */
				__(
					'Разброс темпа изменения: от %1$s (%2$s%%) до %3$s (%4$s%%).',
					'worldstat-ergonomics'
				),
				$pct_laggard['name'],
				self::format_plain_number( (float) $pct_laggard['pct'], 1 ),
				$pct_leader['name'],
				self::format_plain_number( (float) $pct_leader['pct'], 1 )
			);
		}

		$insights[] = sprintf(
			/* translators: 1: country, 2: value, 3: year */
			__(
				'Абсолютный максимум прогноза (не сравнивать страны разного масштаба): %1$s — %2$s к %3$d г.',
				'worldstat-ergonomics'
			),
			$abs_leader['name'],
			self::format_plain_number( (float) $abs_leader['forecast'], 2 ),
			(int) $abs_leader['year']
		);

		$growth  = array_values(
			array_filter(
				$rows,
				static function ( $r ) {
					return ( $r['slope'] ?? 0 ) > 0;
				}
			)
		);
		$decline = array_values(
			array_filter(
				$rows,
				static function ( $r ) {
					return ( $r['slope'] ?? 0 ) < 0;
				}
			)
		);
		if ( count( $growth ) > 0 ) {
			$names = array_slice( array_column( $growth, 'name' ), 0, 4 );
			$insights[] = sprintf(
				__( 'Линейный рост тренда: %s.', 'worldstat-ergonomics' ),
				implode( ', ', $names ) . ( count( $growth ) > 4 ? '…' : '' )
			);
		}
		if ( count( $decline ) > 0 ) {
			$names = array_slice( array_column( $decline, 'name' ), 0, 4 );
			$insights[] = sprintf(
				__( 'Снижение по тренду: %s.', 'worldstat-ergonomics' ),
				implode( ', ', $names ) . ( count( $decline ) > 4 ? '…' : '' )
			);
		}

		$best_r2 = $rows[0];
		foreach ( $rows as $r ) {
			if ( ( $r['r2'] ?? 0 ) > ( $best_r2['r2'] ?? 0 ) ) {
				$best_r2 = $r;
			}
		}
		if ( ( $best_r2['r2'] ?? 0 ) >= 0.5 ) {
			$insights[] = sprintf(
				__( 'Наиболее устойчивый линейный тренд (R² = %2$s) — у %1$s.', 'worldstat-ergonomics' ),
				$best_r2['name'],
				self::format_plain_number( (float) $best_r2['r2'], 3 )
			);
		}

		$highlights = array();
		if ( ! empty( $with_pct ) ) {
			$highlights[] = array(
				'label' => __( 'Макс. рост, %', 'worldstat-ergonomics' ),
				'value' => $pct_leader['name'] . ' — ' . self::format_plain_number( (float) $pct_leader['pct'], 1 ) . '%',
			);
		}
		$highlights[] = array(
			'label' => __( 'Стран в сравнении', 'worldstat-ergonomics' ),
			'value' => (string) count( $rows ),
		);

		return array(
			'summary'    => $summary,
			'highlights' => $highlights,
			'insights'   => $insights,
		);
	}

	/**
	 * @param array<string,mixed> $item
	 * @return array{label:string,series:array<int,float>,id:string,slug:string}|null
	 */
	private static function grid_item_to_metric( array $item ): ?array {
		$yd = $item['years_data'] ?? array();
		if ( ! is_array( $yd ) || count( $yd ) < 4 ) {
			return null;
		}
		$series = array();
		foreach ( $yd as $yk => $yv ) {
			$y = (int) $yk;
			if ( $y <= 0 || ! is_numeric( $yv ) || ! is_finite( (float) $yv ) ) {
				continue;
			}
			$series[ $y ] = (float) $yv;
		}
		if ( count( $series ) < 4 ) {
			return null;
		}
		ksort( $series, SORT_NUMERIC );
		$slug = sanitize_key( (string) ( $item['slug'] ?? '' ) );
		$id   = (string) ( $item['metric_id'] ?? $slug );
		if ( $id === '' && $slug === '' ) {
			return null;
		}
		$raw_label = (string) ( $item['label'] ?? $id );
		$label     = $raw_label;
		if ( class_exists( 'WorldStat_Data' ) && $slug !== '' ) {
			$label = WorldStat_Data::resolve_metric_label( 'csv-country-meta', $slug, $raw_label );
		} elseif ( class_exists( 'WSErgo_Country_Macro_Calculator' ) && $slug !== '' ) {
			$tr = WSErgo_Country_Macro_Calculator::data_label_ru( $slug );
			if ( is_string( $tr ) && $tr !== '' && $tr !== $slug ) {
				$label = $tr;
			}
		}
		return array(
			'id'     => $id,
			'slug'   => $slug,
			'label'  => $label,
			'series' => $series,
		);
	}

	/**
	 * @return array{label:string,series:array<int,float>,id:string,slug:string}|null
	 */
	private static function find_metric_in_country( WorldStat_Data $data, int $post_id, string $metric_id, string $metric_slug = '' ): ?array {
		if ( $post_id < 1 ) {
			return null;
		}
		$metric_slug = sanitize_key( $metric_slug );
		foreach ( $data->get_country_grid_items( $post_id ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$metric = self::grid_item_to_metric( $item );
			if ( null === $metric ) {
				continue;
			}
			if ( $metric_id !== '' && ( $metric['id'] === $metric_id || $metric['slug'] === $metric_id ) ) {
				return $metric;
			}
			if ( $metric_slug !== '' && $metric['slug'] === $metric_slug ) {
				return $metric;
			}
		}
		return null;
	}

	public static function ajax_run(): void {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( (string) wp_unslash( $_POST['nonce'] ), 'wsergo_country_compare' ) ) {
			self::json_error( array( 'message' => __( 'Недействительный nonce.', 'worldstat-ergonomics' ) ) );
		}

		$metric_id = isset( $_POST['metric_id'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['metric_id'] ) ) : '';
		$iso2_raw  = isset( $_POST['iso2'] ) ? wp_unslash( $_POST['iso2'] ) : array();
		if ( ! is_array( $iso2_raw ) ) {
			$iso2_raw = array( $iso2_raw );
		}

		$result = self::build_comparison( $iso2_raw, $metric_id );
		if ( ! empty( $result['ok'] ) ) {
			self::json_success( $result );
		}
		self::json_error( $result );
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private static function json_success( array $data ): void {
		if ( function_exists( 'wsergo_country_discard_ajax_output_buffer' ) ) {
			wsergo_country_discard_ajax_output_buffer();
		}
		wp_send_json_success( $data );
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private static function json_error( array $data ): void {
		if ( function_exists( 'wsergo_country_discard_ajax_output_buffer' ) ) {
			wsergo_country_discard_ajax_output_buffer();
		}
		wp_send_json_error( $data );
	}
}
