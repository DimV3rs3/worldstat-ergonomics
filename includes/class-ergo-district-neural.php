<?php
/**
 * Модель «нейросетевых» оценок района (эвристики по мета wsdistrict_*).
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_District_Neural {

	public const META_SAFETY_NEURAL          = 'wsergo_district_safety_neural';
	public const META_FUNCTIONALITY_NEURAL   = 'wsergo_district_functionality_neural';
	public const META_COMFORT_NEURAL         = 'wsergo_district_comfort_neural';
	public const META_MANAGEABILITY_NEURAL   = 'wsergo_district_manageability_neural';
	public const META_LIVABILITY_NEURAL      = 'wsergo_district_livability_neural';
	public const META_MASTERABILITY_NEURAL   = 'wsergo_district_masterability_neural';
	public const META_COMPOSITE_NEURAL       = 'wsergo_district_composite_neural';
	public const META_NEURAL_VERSION         = 'wsergo_neural_model_version';

	public const OPTION_SAFETY_WEIGHTS         = 'wsergo_neural_safety_weights';
	public const OPTION_FUNCTIONALITY_WEIGHTS  = 'wsergo_neural_functionality_weights';
	public const OPTION_COMFORT_WEIGHTS        = 'wsergo_neural_comfort_weights';
	public const OPTION_MANAGEABILITY_WEIGHTS  = 'wsergo_neural_manageability_weights';
	public const OPTION_LIVABILITY_WEIGHTS     = 'wsergo_neural_livability_weights';
	public const OPTION_MASTERABILITY_WEIGHTS  = 'wsergo_neural_masterability_weights';

	public static function init_neural_tables(): void {
	}

	public static function get_neural_features(): array {
		return apply_filters(
			'wsergo_neural_features',
			[
				'area'                  => 0.12,
				'density'               => 0.15,
				'green_index'           => 0.18,
				'population'            => 0.10,
				'crime_rate'            => 0.20,
				'air_quality'           => 0.22,
				'noise_level'           => 0.14,
				'walkability_score'     => 0.25,
				'pedestrian_demand'     => 0.20,
				'transit_density'       => 0.18,
				'street_connectivity'   => 0.15,
				'amenities_count'       => 0.16,
				'infrastructure_index'  => 0.20,
				'social_index'          => 0.18,
				'economic_index'        => 0.15,
				'comfort_score'         => 0.22,
				'safety_score'          => 0.25,
				'functionality_score'   => 0.22,
			]
		);
	}

	public static function normalize_features( array $features ): array {
		$normalized = [];
		$ranges     = [
			'area'                 => [ 0, 30000 ],
			'density'              => [ 0, 500 ],
			'green_index'          => [ 0, 100 ],
			'population'           => [ 0, 3000000 ],
			'crime_rate'           => [ 0, 100 ],
			'air_quality'          => [ 0, 100 ],
			'noise_level'          => [ 0, 100 ],
			'walkability_score'    => [ 0, 100 ],
			'pedestrian_demand'    => [ 0, 100 ],
			'transit_density'      => [ 0, 100 ],
			'street_connectivity'  => [ 0, 100 ],
			'amenities_count'      => [ 0, 1000 ],
			'infrastructure_index' => [ 0, 100 ],
			'social_index'         => [ 0, 100 ],
			'economic_index'       => [ 0, 100 ],
			'comfort_score'        => [ 0, 100 ],
			'safety_score'         => [ 0, 100 ],
			'functionality_score'  => [ 0, 100 ],
		];

		foreach ( $features as $key => $value ) {
			if ( isset( $ranges[ $key ] ) ) {
				$min = $ranges[ $key ][0];
				$max = $ranges[ $key ][1];
				if ( $max > $min ) {
					$normalized[ $key ] = max( 0, min( 1, ( (float) $value - $min ) / ( $max - $min ) ) );
				} else {
					$normalized[ $key ] = 0.5;
				}
			} else {
				$normalized[ $key ] = is_numeric( $value ) ? (float) $value / 100 : 0.5;
			}
		}

		return $normalized;
	}

	public static function calculate_safety_neural( int $district_id ): float {
		$crime_level = get_post_meta( $district_id, 'wsdistrict_crime_level', true );
		$safety_air  = get_post_meta( $district_id, 'wsdistrict_safety_score', true );
		$walkability = get_post_meta( $district_id, 'wsdistrict_walkability_score', true );
		$density     = (float) get_post_meta( $district_id, 'wsdistrict_density', true );

		$crime_score = 100;
		if ( 'Low' === $crime_level ) {
			$crime_score = 80;
		} elseif ( 'Medium' === $crime_level ) {
			$crime_score = 50;
		} elseif ( 'High' === $crime_level ) {
			$crime_score = 20;
		}

		$safety = round(
			$crime_score * 0.35
			+ ( $walkability ? (float) $walkability : 50 ) * 0.25
			+ ( $safety_air ? (float) $safety_air : 50 ) * 0.20
			+ max( 30, min( 100, 100 - ( $density / 500 ) ) ) * 0.20,
			2
		);

		update_post_meta( $district_id, self::META_SAFETY_NEURAL, $safety );

		return $safety;
	}

	public static function calculate_functionality_neural( int $district_id ): float {
		$walkability       = get_post_meta( $district_id, 'wsdistrict_walkability_score', true );
		$pedestrian_demand = get_post_meta( $district_id, 'wsdistrict_pedestrian_demand_score', true );
		$transit           = get_post_meta( $district_id, 'wsdistrict_transit_score', true );
		$amenities         = (float) get_post_meta( $district_id, 'wsdistrict_amenities_count', true );
		$area              = (float) get_post_meta( $district_id, 'wsdistrict_area', true );

		$acc          = ( $walkability ? (float) $walkability : 50 ) * 0.6 + ( $transit ? (float) $transit : 50 ) * 0.4;
		$density_per_km = $area > 0 ? ( $amenities / ( $area / 100 ) ) : 50;
		$mix          = min( 100, $density_per_km * 2 );
		$conn         = $pedestrian_demand ? (float) $pedestrian_demand : 50;

		$functionality = round( $acc * 0.40 + $mix * 0.35 + $conn * 0.25, 2 );

		update_post_meta( $district_id, self::META_FUNCTIONALITY_NEURAL, $functionality );

		return $functionality;
	}

	public static function calculate_comfort_neural( int $district_id ): float {
		$air_quality = get_post_meta( $district_id, 'wsdistrict_comfort_score', true );
		$green_index = get_post_meta( $district_id, 'wsdistrict_green_index', true );
		$walkability = get_post_meta( $district_id, 'wsdistrict_walkability_score', true );
		$density     = (float) get_post_meta( $district_id, 'wsdistrict_density', true );

		$env       = ( $air_quality ? (float) $air_quality : 50 ) * 0.6 + ( $green_index ? (float) $green_index : 50 ) * 0.4;
		$infra     = $walkability ? (float) $walkability : 50;
		$aesthetic = max( 30, min( 100, 100 - ( $density / 300 ) ) );

		$comfort = round( $env * 0.40 + $infra * 0.35 + $aesthetic * 0.25, 2 );

		update_post_meta( $district_id, self::META_COMFORT_NEURAL, $comfort );

		return $comfort;
	}

	public static function calculate_manageability_neural( int $district_id ): float {
		$density = (float) get_post_meta( $district_id, 'wsdistrict_density', true );
		$area    = (float) get_post_meta( $district_id, 'wsdistrict_area', true );

		$resp    = max( 30, min( 100, 100 - ( $density / 400 ) ) );
		$data    = min( 100, ( $area / 1000 ) * 100 );
		$complex = min( 100, ( $density / 500 ) * 100 );

		$manageability = round( $resp * 0.35 + $data * 0.35 + $complex * 0.30, 2 );

		update_post_meta( $district_id, self::META_MANAGEABILITY_NEURAL, $manageability );

		return $manageability;
	}

	public static function calculate_livability_neural( int $district_id ): float {
		$safety        = (float) get_post_meta( $district_id, self::META_SAFETY_NEURAL, true );
		$functionality = (float) get_post_meta( $district_id, self::META_FUNCTIONALITY_NEURAL, true );
		$comfort       = (float) get_post_meta( $district_id, self::META_COMFORT_NEURAL, true );
		$density       = (float) get_post_meta( $district_id, 'wsdistrict_density', true );

		if ( $safety <= 0 ) {
			$safety = self::calculate_safety_neural( $district_id );
		}
		if ( $functionality <= 0 ) {
			$functionality = self::calculate_functionality_neural( $district_id );
		}
		if ( $comfort <= 0 ) {
			$comfort = self::calculate_comfort_neural( $district_id );
		}

		$cost = min( 100, ( $density / 200 ) * 100 );

		$livability = round( $safety * 0.35 + $functionality * 0.35 + $comfort * 0.20 + $cost * 0.10, 2 );

		update_post_meta( $district_id, self::META_LIVABILITY_NEURAL, $livability );

		return $livability;
	}

	public static function calculate_masterability_neural( int $district_id ): float {
		$walkability       = get_post_meta( $district_id, 'wsdistrict_walkability_score', true );
		$pedestrian_demand = get_post_meta( $district_id, 'wsdistrict_pedestrian_demand_score', true );
		$density           = (float) get_post_meta( $district_id, 'wsdistrict_density', true );

		$nav = $walkability ? (float) $walkability : 50;
		$sem = $pedestrian_demand ? (float) $pedestrian_demand : 50;
		$leg = min( 100, ( $density / 300 ) * 100 );

		$masterability = round( $nav * 0.40 + $sem * 0.35 + $leg * 0.25, 2 );

		update_post_meta( $district_id, self::META_MASTERABILITY_NEURAL, $masterability );

		return $masterability;
	}

	public static function calculate_composite_neural( int $district_id ): float {
		$safety          = self::calculate_safety_neural( $district_id );
		$functionality   = self::calculate_functionality_neural( $district_id );
		$comfort         = self::calculate_comfort_neural( $district_id );
		$manageability   = self::calculate_manageability_neural( $district_id );
		$livability      = self::calculate_livability_neural( $district_id );
		$masterability   = self::calculate_masterability_neural( $district_id );

		$criteria_weights = get_option(
			'wsergo_district_criteria_weights',
			[
				'safety'          => 0.20,
				'functionality'   => 0.20,
				'comfort'         => 0.20,
				'manageability'   => 0.10,
				'livability'      => 0.15,
				'masterability'   => 0.15,
			]
		);

		$sum_w = array_sum( $criteria_weights );
		if ( $sum_w <= 0 ) {
			$criteria_weights = [
				'safety'        => 0.20,
				'functionality' => 0.20,
				'comfort'       => 0.20,
				'manageability' => 0.10,
				'livability'    => 0.15,
				'masterability' => 0.15,
			];
			$sum_w            = 1.0;
		}

		$composite = (
			$safety * (float) ( $criteria_weights['safety'] ?? 0 )
			+ $functionality * (float) ( $criteria_weights['functionality'] ?? 0 )
			+ $comfort * (float) ( $criteria_weights['comfort'] ?? 0 )
			+ $manageability * (float) ( $criteria_weights['manageability'] ?? 0 )
			+ $livability * (float) ( $criteria_weights['livability'] ?? 0 )
			+ $masterability * (float) ( $criteria_weights['masterability'] ?? 0 )
		) / $sum_w;

		$composite = round( max( 0, min( 100, $composite ) ), 2 );

		update_post_meta( $district_id, self::META_COMPOSITE_NEURAL, $composite );
		update_post_meta( $district_id, self::META_NEURAL_VERSION, WSERGO_VERSION );

		return $composite;
	}

	public static function get_all_neural_metrics( int $district_id ): array {
		return [
			'safety'        => (float) get_post_meta( $district_id, self::META_SAFETY_NEURAL, true ),
			'functionality' => (float) get_post_meta( $district_id, self::META_FUNCTIONALITY_NEURAL, true ),
			'comfort'       => (float) get_post_meta( $district_id, self::META_COMFORT_NEURAL, true ),
			'manageability' => (float) get_post_meta( $district_id, self::META_MANAGEABILITY_NEURAL, true ),
			'livability'    => (float) get_post_meta( $district_id, self::META_LIVABILITY_NEURAL, true ),
			'masterability' => (float) get_post_meta( $district_id, self::META_MASTERABILITY_NEURAL, true ),
			'composite'     => (float) get_post_meta( $district_id, self::META_COMPOSITE_NEURAL, true ),
		];
	}

	public static function update_all_neural_metrics( int $district_id ): array {
		$metrics = [
			'safety'        => self::calculate_safety_neural( $district_id ),
			'functionality' => self::calculate_functionality_neural( $district_id ),
			'comfort'       => self::calculate_comfort_neural( $district_id ),
			'manageability' => self::calculate_manageability_neural( $district_id ),
			'livability'    => self::calculate_livability_neural( $district_id ),
			'masterability' => self::calculate_masterability_neural( $district_id ),
		];

		$metrics['composite'] = self::calculate_composite_neural( $district_id );

		return $metrics;
	}
}
