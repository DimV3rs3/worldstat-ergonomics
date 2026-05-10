<?php
/**
 * Шорткод и разметка для нейросетевых метрик района.
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_District_Neural_Renderer {

	public static function shortcode_district_neural( $atts ): string {
		$a = shortcode_atts(
			[
				'id'    => '0',
				'show'  => 'composite',
				'class' => 'wsergo-neural-badge',
			],
			is_array( $atts ) ? $atts : [],
			'wsergo_district_neural'
		);

		$district_id = (int) $a['id'];
		if ( $district_id <= 0 && is_singular( WSErgo_CPT::SLUG_DISTRICT ) ) {
			$district_id = get_the_ID();
		}

		if ( $district_id <= 0 ) {
			return '';
		}

		$metrics = WSErgo_District_Neural::get_all_neural_metrics( $district_id );
		if ( empty( $metrics ) || (float) ( $metrics['composite'] ?? 0 ) <= 0 ) {
			return '';
		}

		$cls = sanitize_html_class( $a['class'] );

		if ( 'all' === $a['show'] ) {
			$color = self::get_score_color( (float) $metrics['composite'] );
			$html  = '<div class="' . esc_attr( $cls ) . '">';
			$html .= '<div class="wsergo-neural-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; padding: 15px; background: #f8fafc; border-radius: 12px;">';
			$html .= '<div><div style="font-size: 11px; color: #666;">' . esc_html__( 'Безопасность', 'worldstat-ergonomics' ) . '</div><div style="font-size: 18px; font-weight: bold;">' . (int) round( (float) $metrics['safety'] ) . '</div></div>';
			$html .= '<div><div style="font-size: 11px; color: #666;">' . esc_html__( 'Функциональность', 'worldstat-ergonomics' ) . '</div><div style="font-size: 18px; font-weight: bold;">' . (int) round( (float) $metrics['functionality'] ) . '</div></div>';
			$html .= '<div><div style="font-size: 11px; color: #666;">' . esc_html__( 'Комфортность', 'worldstat-ergonomics' ) . '</div><div style="font-size: 18px; font-weight: bold;">' . (int) round( (float) $metrics['comfort'] ) . '</div></div>';
			$html .= '<div><div style="font-size: 11px; color: #666;">' . esc_html__( 'Управляемость', 'worldstat-ergonomics' ) . '</div><div style="font-size: 18px; font-weight: bold;">' . (int) round( (float) $metrics['manageability'] ) . '</div></div>';
			$html .= '<div><div style="font-size: 11px; color: #666;">' . esc_html__( 'Обитаемость', 'worldstat-ergonomics' ) . '</div><div style="font-size: 18px; font-weight: bold;">' . (int) round( (float) $metrics['livability'] ) . '</div></div>';
			$html .= '<div><div style="font-size: 11px; color: #666;">' . esc_html__( 'Освояемость', 'worldstat-ergonomics' ) . '</div><div style="font-size: 18px; font-weight: bold;">' . (int) round( (float) $metrics['masterability'] ) . '</div></div>';
			$html .= '<div style="grid-column: span 3; margin-top: 10px; padding-top: 10px; border-top: 1px solid #e5e7eb; text-align: center;"><span style="font-size: 14px; color: ' . esc_attr( $color ) . '; font-weight: bold;">' . esc_html__( 'Сводный индекс', 'worldstat-ergonomics' ) . ': ' . (int) round( (float) $metrics['composite'] ) . '</span></div>';
			$html .= '</div></div>';
			return $html;
		}

		$value = isset( $metrics[ $a['show'] ] ) ? (float) $metrics[ $a['show'] ] : (float) $metrics['composite'];
		$color = self::get_score_color( $value );

		return '<span class="' . esc_attr( $cls ) . '" title="' . esc_attr__( 'Оценка модели (0–100)', 'worldstat-ergonomics' ) . '" style="color: ' . esc_attr( $color ) . '; font-weight: bold;">' . (int) round( $value ) . '</span>';
	}

	private static function get_score_color( float $score ): string {
		if ( $score >= 70 ) {
			return '#10b981';
		}
		if ( $score >= 50 ) {
			return '#3b82f6';
		}
		if ( $score >= 30 ) {
			return '#f59e0b';
		}
		return '#ef4444';
	}
}
