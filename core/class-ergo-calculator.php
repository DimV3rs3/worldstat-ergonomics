<?php
/**
 * Расчёт индекса по модели DSL и агрегация по иерархии.
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_Calculator {

	/**
	 * Зафиксированный индекс не пересчитывается каскадом (для здания/квартала).
	 */
	public static function is_index_locked( int $post_id ): bool {
		return get_post_meta( $post_id, WSErgo_CPT::META_INDEX_LOCKED, true ) === '1';
	}

	/**
	 * Сводный индекс: при блокировке и положительном значении — не меняем; иначе DSL / агрегация детей.
	 */
	public static function compute_and_store_index( int $post_id ): ?float {
		$idx = get_post_meta( $post_id, WSErgo_CPT::META_INDEX, true );
		if ( $idx !== '' && $idx !== null && (float) $idx > 0 && self::is_index_locked( $post_id ) ) {
			return (float) $idx;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return null;
		}

		$type = $post->post_type;
		$val  = null;

		if ( WSErgo_CPT::SLUG_BUILDING === $type ) {
			$val = self::compute_building_index( $post_id );
		} elseif ( WSErgo_CPT::SLUG_DISTRICT === $type ) {
			$val = self::compute_district_index( $post_id );
		} elseif ( WSErgo_CPT::SLUG_ROOM === $type || WSErgo_CPT::SLUG_YARD === $type ) {
			$val = self::compute_leaf_from_dimensions( $post_id );
		} else {
			$val = self::compute_leaf_from_dimensions( $post_id );
		}

		if ( $val !== null ) {
			update_post_meta( $post_id, WSErgo_CPT::META_INDEX, round( $val, 2 ) );
		} else {
			delete_post_meta( $post_id, WSErgo_CPT::META_INDEX );
		}

		return $val;
	}

	/**
	 * Индекс здания: из помещений (настройка) или из шести измерений на здании.
	 */
	public static function compute_building_index( int $building_id ): ?float {
		$rooms = WSErgo_CPT::get_rooms_for_building( $building_id );
		$agg   = WSErgo_Settings::get_aggregation();

		if ( ! empty( $rooms ) ) {
			$indices = [];
			$weights = [];
			foreach ( $rooms as $r ) {
				$ri = self::compute_leaf_from_dimensions( (int) $r->ID );
				if ( $ri === null || $ri <= 0 ) {
					continue;
				}
				$indices[] = $ri;
				$w         = 1.0;
				if ( 'weighted_area' === $agg['room_to_building'] ) {
					$w = max( 0.0001, (float) get_post_meta( $r->ID, WSErgo_CPT::META_AREA, true ) );
				}
				$weights[] = $w;
			}
			if ( empty( $indices ) ) {
				return self::compute_leaf_from_dimensions( $building_id );
			}
			if ( 'median' === $agg['room_to_building'] ) {
				sort( $indices );
				$n = count( $indices );
				$mid = (int) floor( $n / 2 );
				return $n % 2 === 1 ? $indices[ $mid ] : ( $indices[ $mid - 1 ] + $indices[ $mid ] ) / 2;
			}
			if ( 'weighted_area' === $agg['room_to_building'] ) {
				$ws = array_sum( $weights );
				if ( $ws <= 0 ) {
					return self::compute_leaf_from_dimensions( $building_id );
				}
				$s = 0.0;
				foreach ( $indices as $i => $iv ) {
					$s += $iv * $weights[ $i ];
				}
				return round( $s / $ws, 2 );
			}
			// mean.
			return round( array_sum( $indices ) / count( $indices ), 2 );
		}

		return self::compute_leaf_from_dimensions( $building_id );
	}

	/**
	 * Индекс квартала: взвешенная смесь средних по зданиям и по придомовым участкам.
	 */
	public static function compute_district_index( int $district_id ): ?float {
		$buildings = WSErgo_CPT::get_buildings_for_district( $district_id );
		$yards       = WSErgo_CPT::get_yards_for_district( $district_id );
		$agg         = WSErgo_Settings::get_aggregation();

		$b_vals = [];
		foreach ( $buildings as $b ) {
			$v = (float) get_post_meta( $b->ID, WSErgo_CPT::META_INDEX, true );
			if ( $v <= 0 ) {
				$v = self::compute_building_index( (int) $b->ID );
				if ( $v !== null && $v > 0 ) {
					update_post_meta( $b->ID, WSErgo_CPT::META_INDEX, round( $v, 2 ) );
				}
			}
			if ( $v > 0 ) {
				$b_vals[] = $v;
			}
		}

		$y_vals = [];
		foreach ( $yards as $y ) {
			$v = self::compute_leaf_from_dimensions( (int) $y->ID );
			if ( $v !== null && $v > 0 ) {
				update_post_meta( $y->ID, WSErgo_CPT::META_INDEX, round( $v, 2 ) );
				$y_vals[] = $v;
			}
		}

		$mb = ! empty( $b_vals ) ? array_sum( $b_vals ) / count( $b_vals ) : null;
		$my = ! empty( $y_vals ) ? array_sum( $y_vals ) / count( $y_vals ) : null;

		if ( $mb === null && $my === null ) {
			return self::compute_leaf_from_dimensions( $district_id );
		}
		if ( $mb === null ) {
			return round( $my, 2 );
		}
		if ( $my === null ) {
			return round( $mb, 2 );
		}

		$wb = (float) $agg['w_building_in_district'];
		$wy = (float) $agg['w_yard_in_district'];
		return round( $wb * $mb + $wy * $my, 2 );
	}

	/**
	 * Лист: шесть измерений → DSL или взвешенное среднее.
	 */
	public static function compute_leaf_from_dimensions( int $post_id ): ?float {
		$scores = WSErgo_Model::get_scores_from_post( $post_id );
		$model  = WSErgo_Settings::get_active_model();
		$formula = is_array( $model ) ? trim( (string) ( $model['leaf_formula'] ?? '' ) ) : '';

		if ( $formula === '' ) {
			return WSErgo_Model::calculate_composite( $scores );
		}

		$vars = self::build_vars_from_scores( $post_id, $scores );
		try {
			$v = WSErgo_Expression::evaluate( $formula, $vars );
			if ( ! is_finite( $v ) ) {
				return null;
			}
			return round( max( 0, min( 100, $v ) ), 2 );
		} catch ( Exception $e ) {
			return WSErgo_Model::calculate_composite( $scores );
		}
	}

	/**
	 * @param array<string, float> $scores
	 * @return array<string, float>
	 */
	public static function build_vars_from_scores( int $post_id, array $scores ): array {
		$labels  = WSErgo_Model::get_dimension_labels();
		$weights = WSErgo_Model::get_weights();
		$coef    = WSErgo_Settings::get_coefficients();

		$short = [
			'F' => WSErgo_Model::DIM_FUNCTIONALITY,
			'S' => WSErgo_Model::DIM_SAFETY,
			'C' => WSErgo_Model::DIM_COMFORT,
			'L' => WSErgo_Model::DIM_LIVABILITY,
			'O' => WSErgo_Model::DIM_MASTERABILITY,
			'M' => WSErgo_Model::DIM_MANAGEABILITY,
		];

		$vars = [];
		foreach ( WSErgo_Model::DIMENSION_KEYS as $dim ) {
			$x = isset( $scores[ $dim ] ) ? (float) $scores[ $dim ] : 0.0;
			$vars[ $dim ] = $x;
		}
		foreach ( $short as $letter => $dim ) {
			$vars[ $letter ] = isset( $scores[ $dim ] ) ? (float) $scores[ $dim ] : 0.0;
		}
		foreach ( $weights as $dim => $w ) {
			$vars[ 'w_' . $dim ] = (float) $w;
		}

		$override = get_post_meta( $post_id, WSErgo_CPT::META_COEFF_OVERRIDES, true );
		if ( is_string( $override ) && $override !== '' ) {
			$decoded = json_decode( $override, true );
			if ( is_array( $decoded ) ) {
				foreach ( $decoded as $k => $v ) {
					$key = sanitize_key( (string) $k );
					if ( substr( $key, 0, 2 ) !== 'k_' ) {
						$key = 'k_' . $key;
					}
					$coef[ $key ] = (float) $v;
				}
			}
		}

		foreach ( $coef as $k => $v ) {
			$vars[ $k ] = (float) $v;
		}

		if ( class_exists( 'WSErgo_Indicators' ) ) {
			foreach ( WSErgo_Indicators::get_definitions() as $def ) {
				$mkey = WSErgo_Indicators::meta_key_for_raw( $def['id'] );
				$rawm = get_post_meta( $post_id, $mkey, true );
				if ( $rawm === '' || $rawm === null ) {
					$vars[ 'i_' . $def['id'] ] = 0.0;
					continue;
				}
				$rawf = (float) str_replace( ',', '.', (string) $rawm );
				$norm = WSErgo_Indicators::normalize_to_score( $def, $rawf );
				$vars[ 'i_' . $def['id'] ] = $norm !== null ? $norm : 0.0;
			}
		}

		return apply_filters( 'wsergo_dsl_variables', $vars, $post_id, $scores );
	}

	/**
	 * Листовой E из карты сырых показателей (например данные wsp_city без мета wsergo_raw_*).
	 *
	 * @param array<string, float> $raw_by_indicator_id
	 */
	public static function compute_leaf_from_raw_indicator_map( int $context_post_id, array $raw_by_indicator_id ): ?float {
		if ( ! class_exists( 'WSErgo_Indicators' ) ) {
			return null;
		}
		$scores = WSErgo_Indicators::build_dimension_scores_from_raw_map( $raw_by_indicator_id );
		if ( empty( $scores ) ) {
			return null;
		}
		$model   = WSErgo_Settings::get_active_model();
		$formula = is_array( $model ) ? trim( (string) ( $model['leaf_formula'] ?? '' ) ) : '';
		if ( $formula === '' ) {
			return WSErgo_Model::calculate_composite( $scores );
		}
		$vars = self::build_vars_from_scores_with_raw_map( $context_post_id, $scores, $raw_by_indicator_id );
		try {
			$v = WSErgo_Expression::evaluate( $formula, $vars );
			if ( ! is_finite( $v ) ) {
				return WSErgo_Model::calculate_composite( $scores );
			}
			return round( max( 0, min( 100, $v ) ), 2 );
		} catch ( Exception $e ) {
			return WSErgo_Model::calculate_composite( $scores );
		}
	}

	/**
	 * @param array<string, float>              $scores
	 * @param array<string, float> $raw_by_indicator_id
	 * @return array<string, float>
	 */
	public static function build_vars_from_scores_with_raw_map( int $post_id, array $scores, array $raw_by_indicator_id ): array {
		$weights = WSErgo_Model::get_weights();
		$coef    = WSErgo_Settings::get_coefficients();

		$short = [
			'F' => WSErgo_Model::DIM_FUNCTIONALITY,
			'S' => WSErgo_Model::DIM_SAFETY,
			'C' => WSErgo_Model::DIM_COMFORT,
			'L' => WSErgo_Model::DIM_LIVABILITY,
			'O' => WSErgo_Model::DIM_MASTERABILITY,
			'M' => WSErgo_Model::DIM_MANAGEABILITY,
		];

		$vars = [];
		foreach ( WSErgo_Model::DIMENSION_KEYS as $dim ) {
			$x            = isset( $scores[ $dim ] ) ? (float) $scores[ $dim ] : 0.0;
			$vars[ $dim ] = $x;
		}
		foreach ( $short as $letter => $dim ) {
			$vars[ $letter ] = isset( $scores[ $dim ] ) ? (float) $scores[ $dim ] : 0.0;
		}
		foreach ( $weights as $dim => $w ) {
			$vars[ 'w_' . $dim ] = (float) $w;
		}

		$override = get_post_meta( $post_id, WSErgo_CPT::META_COEFF_OVERRIDES, true );
		if ( is_string( $override ) && $override !== '' ) {
			$decoded = json_decode( $override, true );
			if ( is_array( $decoded ) ) {
				foreach ( $decoded as $k => $v ) {
					$key = sanitize_key( (string) $k );
					if ( substr( $key, 0, 2 ) !== 'k_' ) {
						$key = 'k_' . $key;
					}
					$coef[ $key ] = (float) $v;
				}
			}
		}

		foreach ( $coef as $k => $v ) {
			$vars[ $k ] = (float) $v;
		}

		if ( class_exists( 'WSErgo_Indicators' ) ) {
			foreach ( WSErgo_Indicators::get_definitions() as $def ) {
				if ( isset( $raw_by_indicator_id[ $def['id'] ] ) ) {
					$rawf = (float) $raw_by_indicator_id[ $def['id'] ];
					$norm = WSErgo_Indicators::normalize_to_score( $def, $rawf );
					$vars[ 'i_' . $def['id'] ] = $norm !== null ? $norm : 0.0;
				} else {
					$vars[ 'i_' . $def['id'] ] = 0.0;
				}
			}
		}

		return apply_filters( 'wsergo_dsl_variables', $vars, $post_id, $scores );
	}

	/**
	 * Каскад вверх после сохранения дочерней сущности.
	 */
	public static function bubble_from_room( int $room_id ): void {
		$bid = (int) get_post_meta( $room_id, WSErgo_CPT::META_BUILDING_ID, true );
		if ( $bid > 0 ) {
			if ( ! self::is_index_locked( $bid ) ) {
				self::compute_and_store_index( $bid );
			}
			self::bubble_from_building( $bid );
		}
	}

	public static function bubble_from_building( int $building_id ): void {
		$did = (int) get_post_meta( $building_id, WSErgo_CPT::META_DISTRICT_ID, true );
		if ( $did > 0 && ! self::is_index_locked( $did ) ) {
			self::compute_and_store_index( $did );
		}
	}

	public static function bubble_from_yard( int $yard_id ): void {
		$did = (int) get_post_meta( $yard_id, WSErgo_CPT::META_DISTRICT_ID, true );
		if ( $did > 0 && ! self::is_index_locked( $did ) ) {
			self::compute_and_store_index( $did );
		}
	}
}
