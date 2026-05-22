<?php
/**
 * Зона помещения (wsz_zone) — отображение и агрегаты эргономики.
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_Zone_Bridge {

	/**
	 * @return array{score:float,level:string,color:string,lighting:float,safety:float,comfort:float}
	 */
	public static function get_zone_ergonomics( int $zone_id ): array {
		if ( class_exists( 'WSZ_CPT' ) ) {
			$zone = WSZ_CPT::get_product_data( $zone_id );
			if ( is_array( $zone ) && ! empty( $zone ) ) {
				return self::format_from_row( $zone );
			}
		}
		$ergo = (float) get_post_meta( $zone_id, 'wsz_ergonomics', true );
		return self::format_score( $ergo );
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array{score:float,level:string,color:string,lighting:float,safety:float,comfort:float}
	 */
	public static function format_from_row( array $row ): array {
		$out = self::format_score( (float) ( $row['ergonomics'] ?? 0 ) );
		$out['lighting'] = (float) ( $row['lighting'] ?? 0 );
		$out['safety']   = (float) ( $row['safety'] ?? 0 );
		$out['comfort']  = (float) ( $row['comfort'] ?? 0 );
		return $out;
	}

	/**
	 * @return array{score:float,level:string,color:string}
	 */
	public static function format_score( float $ergo ): array {
		if ( $ergo >= 80 ) {
			$level = __( 'Отлично', 'worldstat-ergonomics' );
			$color = '#10b981';
		} elseif ( $ergo >= 60 ) {
			$level = __( 'Хорошо', 'worldstat-ergonomics' );
			$color = '#3b82f6';
		} elseif ( $ergo >= 40 ) {
			$level = __( 'Средне', 'worldstat-ergonomics' );
			$color = '#f59e0b';
		} elseif ( $ergo >= 20 ) {
			$level = __( 'Ниже среднего', 'worldstat-ergonomics' );
			$color = '#f97316';
		} else {
			$level = __( 'Низкий', 'worldstat-ergonomics' );
			$color = '#ef4444';
		}
		return [
			'score' => round( $ergo, 1 ),
			'level' => $level,
			'color' => $color,
		];
	}
}
