<?php
/**
 * Район (wsp_district) + мета wsdistrict_* → индекс эргономичности и нейро-метрики.
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_District_Bridge {

	public const OPTION_NEURAL_ACTIVE   = 'wsergo_neural_active';
	public const OPTION_AUTO_CALCULATE  = 'wsergo_auto_calculate';
	public const OPTION_LAST_UPDATE     = 'wsergo_last_update';

	/**
	 * CPT района: плагин Districts или эргономика.
	 */
	public static function district_post_type(): string {
		if ( class_exists( 'WSDistricts_CPT' ) ) {
			return WSDistricts_CPT::SLUG;
		}
		return WSErgo_CPT::SLUG_DISTRICT;
	}

	public static function is_neural_active(): bool {
		return (bool) get_option( self::OPTION_NEURAL_ACTIVE, true );
	}

	public static function is_auto_calculate(): bool {
		return (bool) get_option( self::OPTION_AUTO_CALCULATE, true );
	}

	public static function ensure_default_options(): void {
		if ( get_option( self::OPTION_NEURAL_ACTIVE, null ) === null ) {
			add_option( self::OPTION_NEURAL_ACTIVE, true );
		}
		if ( get_option( self::OPTION_AUTO_CALCULATE, null ) === null ) {
			add_option( self::OPTION_AUTO_CALCULATE, true );
		}
	}

	/**
	 * @return array{score:float,level:string,color:string,scores:array<string,float>}
	 */
	public static function get_ergonomics_index( int $district_id ): array {
		$scores = [
			'comfort'         => (float) get_post_meta( $district_id, 'wsdistrict_comfort_score', true ),
			'safety'          => (float) get_post_meta( $district_id, 'wsdistrict_safety_score', true ),
			'functionality'   => (float) get_post_meta( $district_id, 'wsdistrict_functionality_score', true ),
			'masterability'   => (float) get_post_meta( $district_id, 'wsdistrict_masterability_score', true ),
			'livability'      => (float) get_post_meta( $district_id, 'wsdistrict_livability_score', true ),
			'manageability'   => (float) get_post_meta( $district_id, 'wsdistrict_manageability_score', true ),
		];

		$weights = get_option(
			'wsergo_district_criteria_weights',
			[
				'comfort'         => 0.25,
				'safety'          => 0.25,
				'functionality'   => 0.20,
				'masterability'   => 0.10,
				'livability'      => 0.10,
				'manageability'   => 0.10,
			]
		);
		if ( ! is_array( $weights ) ) {
			$weights = [];
		}

		$total_score  = 0.0;
		$total_weight = 0.0;

		foreach ( $scores as $key => $score ) {
			if ( $score <= 0 ) {
				continue;
			}
			$w = isset( $weights[ $key ] ) ? (float) $weights[ $key ] : 0.0;
			if ( $w <= 0 ) {
				continue;
			}
			$total_score  += $score * $w;
			$total_weight += $w;
		}

		$ergo_index = $total_weight > 0 ? round( $total_score / $total_weight, 1 ) : 0.0;

		if ( $ergo_index >= 80 ) {
			$level = __( 'Отлично', 'worldstat-ergonomics' );
			$color = '#10b981';
		} elseif ( $ergo_index >= 60 ) {
			$level = __( 'Хорошо', 'worldstat-ergonomics' );
			$color = '#3b82f6';
		} elseif ( $ergo_index >= 40 ) {
			$level = __( 'Средне', 'worldstat-ergonomics' );
			$color = '#f59e0b';
		} elseif ( $ergo_index >= 20 ) {
			$level = __( 'Ниже среднего', 'worldstat-ergonomics' );
			$color = '#f97316';
		} else {
			$level = __( 'Низкий', 'worldstat-ergonomics' );
			$color = '#ef4444';
		}

		return [
			'score'  => (float) $ergo_index,
			'level'  => $level,
			'color'  => $color,
			'scores' => $scores,
		];
	}

	public static function update_district( int $district_id ): void {
		if ( ! self::is_neural_active() || ! class_exists( 'WSErgo_District_Neural' ) ) {
			return;
		}
		WSErgo_District_Neural::update_all_neural_metrics( $district_id );
	}

	/**
	 * @return int Число обработанных записей.
	 */
	public static function update_all_districts(): int {
		$ids = get_posts(
			[
				'post_type'      => self::district_post_type(),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
		);
		$count = 0;
		foreach ( $ids as $id ) {
			self::update_district( (int) $id );
			++$count;
		}
		update_option( self::OPTION_LAST_UPDATE, current_time( 'mysql' ) );
		update_option(
			'wsergo_neural_last_training',
			current_time( 'mysql' )
		);
		return $count;
	}

	public static function on_district_saved( int $post_id ): void {
		if ( ! self::is_neural_active() || ! self::is_auto_calculate() ) {
			return;
		}
		if ( get_post_type( $post_id ) !== self::district_post_type() ) {
			return;
		}
		self::update_district( $post_id );
	}
}
