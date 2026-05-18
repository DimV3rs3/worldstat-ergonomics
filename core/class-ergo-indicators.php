<?php
/**
 * Пользовательские листовые показатели (освещённость, шум и т.д.) → шесть уровней 0–100.
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_Indicators {

	public const OPTION_DEFINITIONS = 'wsergo_indicator_definitions';

	/**
	 * @return array<int, array{id:string,label:string,dimension:string,unit:string,vmin:float,vmax:float,direction:string,weight:float}>
	 */
	public static function get_definitions(): array {
		$stored = get_option( self::OPTION_DEFINITIONS, null );
		if ( ! is_array( $stored ) ) {
			return [];
		}
		$out = [];
		foreach ( $stored as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id = isset( $row['id'] ) ? sanitize_key( (string) $row['id'] ) : '';
			if ( $id === '' ) {
				continue;
			}
			$dim = isset( $row['dimension'] ) ? sanitize_key( (string) $row['dimension'] ) : '';
			if ( ! in_array( $dim, WSErgo_Model::DIMENSION_KEYS, true ) ) {
				continue;
			}
			$dir = isset( $row['direction'] ) && 'lower_better' === $row['direction'] ? 'lower_better' : 'higher_better';
			$out[] = [
				'id'         => $id,
				'label'      => isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : $id,
				'dimension'  => $dim,
				'unit'       => isset( $row['unit'] ) ? sanitize_text_field( (string) $row['unit'] ) : '',
				'vmin'       => isset( $row['vmin'] ) ? (float) str_replace( ',', '.', (string) $row['vmin'] ) : 0.0,
				'vmax'       => isset( $row['vmax'] ) ? (float) str_replace( ',', '.', (string) $row['vmax'] ) : 100.0,
				'direction'  => $dir,
				'weight'     => isset( $row['weight'] ) ? max( 0.0, (float) str_replace( ',', '.', (string) $row['weight'] ) ) : 1.0,
			];
		}
		return $out;
	}

	/**
	 * @return array<string, array<int, array{id:string,label:string,...}>>
	 */
	public static function get_definitions_by_dimension(): array {
		$by = [];
		foreach ( WSErgo_Model::DIMENSION_KEYS as $d ) {
			$by[ $d ] = [];
		}
		foreach ( self::get_definitions() as $def ) {
			$by[ $def['dimension'] ][] = $def;
		}
		return $by;
	}

	public static function meta_key_for_raw( string $indicator_id ): string {
		return 'wsergo_raw_' . sanitize_key( $indicator_id );
	}

	/**
	 * Нормализация сырого значения в 0–100.
	 */
	public static function normalize_to_score( array $def, float $raw ): ?float {
		$vmin = (float) $def['vmin'];
		$vmax = (float) $def['vmax'];
		if ( $vmax <= $vmin ) {
			return null;
		}
		if ( 'lower_better' === $def['direction'] ) {
			$score = 100.0 * ( $vmax - $raw ) / ( $vmax - $vmin );
		} else {
			$score = 100.0 * ( $raw - $vmin ) / ( $vmax - $vmin );
		}
		$score = max( 0.0, min( 100.0, $score ) );
		return round( $score, 2 );
	}

	/**
	 * Взвешенное среднее баллов показателей для одного измерения (только заполненные значения).
	 */
	public static function compute_dimension_from_indicators( int $post_id, string $dimension ): ?float {
		$defs = [];
		foreach ( self::get_definitions() as $d ) {
			if ( $d['dimension'] === $dimension ) {
				$defs[] = $d;
			}
		}
		if ( empty( $defs ) ) {
			return null;
		}

		$parts = [];
		foreach ( $defs as $def ) {
			$key = self::meta_key_for_raw( $def['id'] );
			$raw = get_post_meta( $post_id, $key, true );
			if ( $raw === '' || $raw === null ) {
				continue;
			}
			$rawf = (float) str_replace( ',', '.', (string) $raw );
			$norm = self::normalize_to_score( $def, $rawf );
			if ( $norm === null ) {
				continue;
			}
			$parts[] = [
				'score'  => $norm,
				'weight' => max( 0.000001, (float) $def['weight'] ),
			];
		}

		if ( empty( $parts ) ) {
			return null;
		}

		$wsum = 0.0;
		$acc  = 0.0;
		foreach ( $parts as $p ) {
			$acc  += $p['score'] * $p['weight'];
			$wsum += $p['weight'];
		}
		if ( $wsum <= 0 ) {
			return null;
		}

		return round( $acc / $wsum, 2 );
	}

	/**
	 * Подмешивает рассчитанные по показателям баллы уровней в массив оценок.
	 *
	 * @param array<string, float> $scores
	 * @return array<string, float>
	 */
	public static function merge_computed_dimensions( int $post_id, array $scores ): array {
		foreach ( WSErgo_Model::DIMENSION_KEYS as $dim ) {
			$calc = self::compute_dimension_from_indicators( $post_id, $dim );
			if ( $calc !== null ) {
				$scores[ $dim ] = $calc;
			}
		}
		return $scores;
	}

	/**
	 * Записывает рассчитанные уровни в мета (для графиков и экспорта).
	 */
	public static function sync_dimension_meta_from_indicators( int $post_id ): void {
		foreach ( WSErgo_Model::DIMENSION_KEYS as $dim ) {
			$calc = self::compute_dimension_from_indicators( $post_id, $dim );
			if ( $calc !== null ) {
				update_post_meta( $post_id, WSErgo_Model::meta_key_for_dimension( $dim ), $calc );
			}
		}
	}

	/**
	 * Определения из настроек + встроенные city_* из {@see WSErgo_City_Defaults::default_indicator_definitions()}
	 * для индикаторов, по которым уже есть сырые значения (чтобы расчёт работал при пустой таблице определений в БД).
	 *
	 * @param array<string, float> $raw_by_indicator_id
	 * @return array<int, array{id:string,label:string,dimension:string,unit:string,vmin:float,vmax:float,direction:string,weight:float}>
	 */
	/**
	 * Публичный доступ к объединённым определениям (настройки + city_* по сырым ключам).
	 *
	 * @param array<string, float> $raw_by_indicator_id
	 * @return array<int, array{id:string,label:string,dimension:string,unit:string,vmin:float,vmax:float,direction:string,weight:float}>
	 */
	public static function get_definitions_for_raw_map( array $raw_by_indicator_id ): array {
		return self::definitions_for_raw_map( $raw_by_indicator_id );
	}

	private static function definitions_for_raw_map( array $raw_by_indicator_id ): array {
		$by_id = [];
		foreach ( self::get_definitions() as $d ) {
			if ( ! empty( $d['id'] ) ) {
				$by_id[ (string) $d['id'] ] = $d;
			}
		}
		if ( class_exists( 'WSErgo_City_Defaults' ) ) {
			foreach ( WSErgo_City_Defaults::default_indicator_definitions() as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$id = isset( $row['id'] ) ? sanitize_key( (string) $row['id'] ) : '';
				if ( $id === '' || ! array_key_exists( $id, $raw_by_indicator_id ) ) {
					continue;
				}
				if ( isset( $by_id[ $id ] ) ) {
					continue;
				}
				$dim = isset( $row['dimension'] ) ? sanitize_key( (string) $row['dimension'] ) : '';
				if ( ! in_array( $dim, WSErgo_Model::DIMENSION_KEYS, true ) ) {
					continue;
				}
				$dir = isset( $row['direction'] ) && 'lower_better' === $row['direction'] ? 'lower_better' : 'higher_better';
				$by_id[ $id ] = [
					'id'         => $id,
					'label'      => isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : $id,
					'dimension'  => $dim,
					'unit'       => isset( $row['unit'] ) ? sanitize_text_field( (string) $row['unit'] ) : '',
					'vmin'       => isset( $row['vmin'] ) ? (float) str_replace( ',', '.', (string) $row['vmin'] ) : 0.0,
					'vmax'       => isset( $row['vmax'] ) ? (float) str_replace( ',', '.', (string) $row['vmax'] ) : 100.0,
					'direction'  => $dir,
					'weight'     => isset( $row['weight'] ) ? max( 0.0, (float) str_replace( ',', '.', (string) $row['weight'] ) ) : 1.0,
				];
			}
		}
		return array_values( $by_id );
	}

	/**
	 * @param array<int, array{id:string,label:string,dimension:string,unit:string,vmin:float,vmax:float,direction:string,weight:float}> $defs
	 * @param array<string, float>                                                                                                    $raw_by_indicator_id
	 */
	private static function compute_dimension_from_def_list( array $defs, array $raw_by_indicator_id, string $dimension ): ?float {
		$parts = [];
		foreach ( $defs as $def ) {
			if ( ( $def['dimension'] ?? '' ) !== $dimension ) {
				continue;
			}
			if ( ! isset( $raw_by_indicator_id[ $def['id'] ] ) ) {
				continue;
			}
			$rawf = (float) $raw_by_indicator_id[ $def['id'] ];
			$norm = self::normalize_to_score( $def, $rawf );
			if ( $norm === null ) {
				continue;
			}
			$parts[] = [
				'score'  => $norm,
				'weight' => max( 0.000001, (float) $def['weight'] ),
			];
		}
		if ( empty( $parts ) ) {
			return null;
		}
		$wsum = 0.0;
		$acc  = 0.0;
		foreach ( $parts as $p ) {
			$acc  += $p['score'] * $p['weight'];
			$wsum += $p['weight'];
		}
		return $wsum > 0 ? round( $acc / $wsum, 2 ) : null;
	}

	/**
	 * Взвешенное среднее по измерению из переданных сырых значений (без мета записи).
	 *
	 * @param array<string, float> $raw_by_indicator_id
	 */
	public static function compute_dimension_from_raw_map( array $raw_by_indicator_id, string $dimension ): ?float {
		if ( empty( $raw_by_indicator_id ) ) {
			return null;
		}
		$defs = self::get_definitions_for_raw_map( $raw_by_indicator_id );
		return self::compute_dimension_from_def_list( $defs, $raw_by_indicator_id, $dimension );
	}

	/**
	 * Шесть осей 0–100 из карты сырых значений (только измерения с данными).
	 *
	 * @param array<string, float> $raw_by_indicator_id
	 * @return array<string, float>
	 */
	public static function build_dimension_scores_from_raw_map( array $raw_by_indicator_id ): array {
		if ( empty( $raw_by_indicator_id ) ) {
			return [];
		}
		$defs   = self::get_definitions_for_raw_map( $raw_by_indicator_id );
		$scores = [];
		foreach ( WSErgo_Model::DIMENSION_KEYS as $dim ) {
			$calc = self::compute_dimension_from_def_list( $defs, $raw_by_indicator_id, $dim );
			if ( $calc !== null ) {
				$scores[ $dim ] = $calc;
			}
		}
		return $scores;
	}
}
