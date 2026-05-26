<?php
/**
 * Математическая модель эргономичности квартала / здания (6 уровней).
 *
 * Опирается на структуру из методических материалов: иерархия показателей,
 * агрегирование подуровней (в т.ч. метод AHP для весов — задаётся в настройках).
 * Сводный индекс E: взвешенное среднее нормализованных оценок по шести измерениям:
 *
 * - Функциональность (F) — доступность, функциональное смешение, связность (Acc, Mix, Conn).
 * - Безопасность (S) — преступность, транспорт, среда, экстренные службы (S_crime, S_traffic, S_env, S_emerg).
 * - Комфортность (C) — среда, инфраструктура, эстетика (C_env, C_infra, C_aesthetic).
 * - Обитаемость (L) — в т.ч. стоимость жизни относительно качества среды (L = f(S,F,C,Cost) в документе);
 *   на уровне записи хранится итоговая оценка уровня L по району.
 * - Освояемость (O) — навигация, семантика среды, читаемость (O_nav, O_sem, O_leg).
 * - Управляемость (M) — ответственность, данные/мониторинг, сложность управления (M_resp, M_data, M_complex).
 *
 * Формула плагина: E = (Σ w_i · x_i) / (Σ w_i) только для измерений с x_i > 0; веса w_i — из настроек
 * (по умолчанию равные). Полные листовые показатели можно передать в JSON (wsergo_indicators_json) для
 * последующего расширения расчёта без смены схемы хранения.
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_Model {

	public const DIM_FUNCTIONALITY    = 'functionality';
	public const DIM_SAFETY           = 'safety';
	public const DIM_COMFORT          = 'comfort';
	public const DIM_LIVABILITY       = 'livability';
	public const DIM_MASTERABILITY    = 'masterability';
	public const DIM_MANAGEABILITY    = 'manageability';

	/** Порядок измерений для графиков и форм. */
	public const DIMENSION_KEYS = [
		self::DIM_FUNCTIONALITY,
		self::DIM_SAFETY,
		self::DIM_COMFORT,
		self::DIM_LIVABILITY,
		self::DIM_MASTERABILITY,
		self::DIM_MANAGEABILITY,
	];

	/**
	 * Устаревшие мета-ключи (до v1.1): доступность → functionality, среда → частично livability.
	 */
	public const LEGACY_META_MAP = [
		'wsergo_accessibility' => self::DIM_FUNCTIONALITY,
		'wsergo_safety'        => self::DIM_SAFETY,
		'wsergo_comfort'       => self::DIM_COMFORT,
		'wsergo_environment'   => self::DIM_LIVABILITY,
	];

	/**
	 * Человекочитаемые подписи (RU).
	 */
	public static function get_dimension_labels(): array {
		return [
			self::DIM_FUNCTIONALITY => __( 'Функциональность', 'worldstat-ergonomics' ),
			self::DIM_SAFETY        => __( 'Безопасность', 'worldstat-ergonomics' ),
			self::DIM_COMFORT       => __( 'Комфортность', 'worldstat-ergonomics' ),
			self::DIM_LIVABILITY    => __( 'Обитаемость', 'worldstat-ergonomics' ),
			self::DIM_MASTERABILITY => __( 'Освояемость', 'worldstat-ergonomics' ),
			self::DIM_MANAGEABILITY => __( 'Управляемость', 'worldstat-ergonomics' ),
		];
	}

	/**
	 * Краткие пояснения шести измерений для публичного сравнения городов.
	 *
	 * @return array<string, string>
	 */
	public static function get_dimension_descriptions(): array {
		return [
			self::DIM_FUNCTIONALITY => __( 'Доступность, функциональное смешение и связность городской среды (Acc, Mix, Conn).', 'worldstat-ergonomics' ),
			self::DIM_SAFETY        => __( 'Преступность, транспортная безопасность, экологические риски и доступность экстренных служб.', 'worldstat-ergonomics' ),
			self::DIM_COMFORT       => __( 'Комфорт среды, инфраструктура и эстетика (C_env, C_infra, C_aesthetic).', 'worldstat-ergonomics' ),
			self::DIM_LIVABILITY    => __( 'Обитаемость: качество среды с учётом стоимости жизни и устойчивости проживания.', 'worldstat-ergonomics' ),
			self::DIM_MASTERABILITY => __( 'Освояемость: навигация, семантика среды и читаемость городской структуры.', 'worldstat-ergonomics' ),
			self::DIM_MANAGEABILITY => __( 'Управляемость: ответственность, данные и мониторинг, сложность управления средой.', 'worldstat-ergonomics' ),
		];
	}

	/**
	 * meta_key поста для измерения.
	 */
	public static function meta_key_for_dimension( string $dim ): string {
		$map = [
			self::DIM_FUNCTIONALITY    => 'wsergo_dim_functionality',
			self::DIM_SAFETY           => 'wsergo_dim_safety',
			self::DIM_COMFORT          => 'wsergo_dim_comfort',
			self::DIM_LIVABILITY       => 'wsergo_dim_livability',
			self::DIM_MASTERABILITY    => 'wsergo_dim_masterability',
			self::DIM_MANAGEABILITY    => 'wsergo_dim_manageability',
		];
		return $map[ $dim ] ?? 'wsergo_dim_' . sanitize_key( $dim );
	}

	/**
	 * Веса по умолчанию (равные доли).
	 */
	public static function get_default_weights(): array {
		$w = 1.0 / count( self::DIMENSION_KEYS );
		$out = [];
		foreach ( self::DIMENSION_KEYS as $k ) {
			$out[ $k ] = $w;
		}
		return $out;
	}

	/**
	 * Веса из опций + фильтр wsergo_dimension_weights.
	 */
	public static function get_weights(): array {
		$defaults = self::get_default_weights();
		$stored   = get_option( 'wsergo_dimension_weights', [] );
		if ( ! is_array( $stored ) ) {
			$stored = [];
		}
		$weights = array_merge( $defaults, $stored );
		foreach ( self::DIMENSION_KEYS as $k ) {
			if ( ! isset( $weights[ $k ] ) || (float) $weights[ $k ] <= 0 ) {
				$weights[ $k ] = $defaults[ $k ];
			}
		}
		$sum = array_sum( $weights );
		if ( $sum > 0 ) {
			foreach ( $weights as $k => $v ) {
				$weights[ $k ] = (float) $v / $sum;
			}
		}
		return apply_filters( 'wsergo_dimension_weights', $weights );
	}

	/**
	 * Читает шесть оценок 0–100 из мета.
	 *
	 * @return array<string, float>
	 */
	public static function get_scores_from_post( int $post_id ): array {
		$scores = [];
		foreach ( self::DIMENSION_KEYS as $dim ) {
			$key            = self::meta_key_for_dimension( $dim );
			$scores[ $dim ] = (float) get_post_meta( $post_id, $key, true );
		}
		if ( class_exists( 'WSErgo_Indicators' ) ) {
			$scores = WSErgo_Indicators::merge_computed_dimensions( $post_id, $scores );
		}
		return $scores;
	}

	/**
	 * Сводный индекс E по весам: только измерения с оценкой > 0 участвуют в знаменателе Σ w_i.
	 */
	public static function calculate_composite( array $scores, ?array $weights = null ): ?float {
		$weights = $weights ?? self::get_weights();
		$acc     = 0.0;
		$wsum    = 0.0;
		foreach ( self::DIMENSION_KEYS as $dim ) {
			$x = isset( $scores[ $dim ] ) ? (float) $scores[ $dim ] : 0.0;
			$w = isset( $weights[ $dim ] ) ? (float) $weights[ $dim ] : 0.0;
			if ( $x > 0 && $w > 0 ) {
				$acc  += $w * $x;
				$wsum += $w;
			}
		}
		if ( $wsum <= 0 ) {
			return null;
		}
		$index = $acc / $wsum;
		return apply_filters( 'wsergo_calculate_composite_index', round( $index, 2 ), $scores, $weights );
	}

	/**
	 * Пересчитывает wsergo_index (модель DSL / агрегация иерархии).
	 */
	public static function sync_composite_index( int $post_id ): void {
		if ( class_exists( 'WSErgo_Calculator' ) ) {
			WSErgo_Calculator::compute_and_store_index( $post_id );
			return;
		}
		$idx = get_post_meta( $post_id, WSErgo_CPT::META_INDEX, true );
		if ( $idx !== '' && $idx !== null && (float) $idx > 0 ) {
			return;
		}
		$scores = self::get_scores_from_post( $post_id );
		$calc   = self::calculate_composite( $scores );
		if ( $calc !== null ) {
			update_post_meta( $post_id, WSErgo_CPT::META_INDEX, $calc );
		}
	}

	/**
	 * Пары «подпись для графика» => значение.
	 *
	 * @return array<string, float>
	 */
	public static function get_labeled_scores_for_charts( int $post_id ): array {
		$labels = self::get_dimension_labels();
		$scores = self::get_scores_from_post( $post_id );
		$out    = [];
		foreach ( self::DIMENSION_KEYS as $dim ) {
			$out[ $labels[ $dim ] ] = $scores[ $dim ];
		}
		return $out;
	}

	/**
	 * Однократная миграция мета с четырёх старых полей на шесть измерений.
	 */
	public static function maybe_migrate_legacy_meta(): void {
		if ( get_option( 'wsergo_migrated_dimensions_v11' ) ) {
			return;
		}

		$per_request = 40;
		$state       = get_option( 'wsergo_migrate_v11_state', [] );
		if ( ! is_array( $state ) ) {
			$state = [];
		}
		$post_types = [ WSErgo_CPT::SLUG_DISTRICT, WSErgo_CPT::SLUG_BUILDING ];
		$all_done   = true;

		foreach ( $post_types as $pt ) {
			if ( ! empty( $state[ $pt ] ) ) {
				continue;
			}
			$offset = isset( $state[ $pt . '_offset' ] ) ? (int) $state[ $pt . '_offset' ] : 0;
			$posts  = get_posts(
				[
					'post_type'      => $pt,
					'post_status'    => 'any',
					'posts_per_page' => $per_request,
					'offset'         => $offset,
					'fields'         => 'ids',
					'orderby'        => 'ID',
					'order'          => 'ASC',
				]
			);
			if ( empty( $posts ) ) {
				$state[ $pt ] = true;
				unset( $state[ $pt . '_offset' ] );
				continue;
			}
			foreach ( $posts as $pid ) {
				$copied_any = false;
				foreach ( self::LEGACY_META_MAP as $legacy_key => $dim ) {
					$new_key = self::meta_key_for_dimension( $dim );
					$old_val = get_post_meta( $pid, $legacy_key, true );
					$new_val = get_post_meta( $pid, $new_key, true );
					if ( ( $new_val === '' || $new_val === null || (float) $new_val <= 0 ) && $old_val !== '' && $old_val !== null && (float) $old_val > 0 ) {
						update_post_meta( $pid, $new_key, round( (float) $old_val, 2 ) );
						$copied_any = true;
					}
				}
				if ( $copied_any ) {
					delete_post_meta( $pid, WSErgo_CPT::META_INDEX );
				}
				self::sync_composite_index( (int) $pid );
			}
			$state[ $pt . '_offset' ] = $offset + count( $posts );
			if ( count( $posts ) < $per_request ) {
				$state[ $pt ] = true;
				unset( $state[ $pt . '_offset' ] );
			} else {
				$all_done = false;
			}
		}

		if ( $all_done && ! empty( $state[ WSErgo_CPT::SLUG_DISTRICT ] ) && ! empty( $state[ WSErgo_CPT::SLUG_BUILDING ] ) ) {
			update_option( 'wsergo_migrated_dimensions_v11', '1', false );
			delete_option( 'wsergo_migrate_v11_state' );
			return;
		}
		update_option( 'wsergo_migrate_v11_state', $state, false );
	}

	/**
	 * Однократно: положительный индекс у зданий/кварталов считается зафиксированным (совместимость с каскадом).
	 */
	public static function maybe_migrate_index_lock_v12(): void {
		if ( get_option( 'wsergo_migrated_index_lock_v12' ) ) {
			return;
		}
		if ( ! get_option( 'wsergo_migrated_dimensions_v11' ) ) {
			return;
		}

		$per_request = 80;
		$state       = get_option( 'wsergo_migrate_v12_state', [] );
		if ( ! is_array( $state ) ) {
			$state = [];
		}
		$post_types = [ WSErgo_CPT::SLUG_DISTRICT, WSErgo_CPT::SLUG_BUILDING ];
		$all_done   = true;

		foreach ( $post_types as $pt ) {
			if ( ! empty( $state[ $pt ] ) ) {
				continue;
			}
			$offset = isset( $state[ $pt . '_offset' ] ) ? (int) $state[ $pt . '_offset' ] : 0;
			$posts  = get_posts(
				[
					'post_type'      => $pt,
					'post_status'    => 'any',
					'posts_per_page' => $per_request,
					'offset'         => $offset,
					'fields'         => 'ids',
					'orderby'        => 'ID',
					'order'          => 'ASC',
				]
			);
			if ( empty( $posts ) ) {
				$state[ $pt ] = true;
				unset( $state[ $pt . '_offset' ] );
				continue;
			}
			foreach ( $posts as $pid ) {
				$idx = get_post_meta( $pid, WSErgo_CPT::META_INDEX, true );
				if ( $idx === '' || $idx === null || (float) $idx <= 0 ) {
					continue;
				}
				if ( get_post_meta( $pid, WSErgo_CPT::META_INDEX_LOCKED, true ) === '' ) {
					update_post_meta( $pid, WSErgo_CPT::META_INDEX_LOCKED, '1' );
				}
			}
			$state[ $pt . '_offset' ] = $offset + count( $posts );
			if ( count( $posts ) < $per_request ) {
				$state[ $pt ] = true;
				unset( $state[ $pt . '_offset' ] );
			} else {
				$all_done = false;
			}
		}

		if ( $all_done && ! empty( $state[ WSErgo_CPT::SLUG_DISTRICT ] ) && ! empty( $state[ WSErgo_CPT::SLUG_BUILDING ] ) ) {
			update_option( 'wsergo_migrated_index_lock_v12', '1', false );
			delete_option( 'wsergo_migrate_v12_state' );
			return;
		}
		update_option( 'wsergo_migrate_v12_state', $state, false );
	}
}
