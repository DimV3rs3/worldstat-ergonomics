<?php
/**
 * Публичный вывод эргономики района.
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_Territory_Renderer {

	public static function render_main_card( int $district_id ): void {
		if ( ! class_exists( 'WSErgo_District_Bridge' ) ) {
			return;
		}
		$ergo = WSErgo_District_Bridge::get_ergonomics_index( $district_id );
		if ( (float) $ergo['score'] <= 0 ) {
			return;
		}
		$color = esc_attr( (string) $ergo['color'] );
		$score = (float) $ergo['score'];
		?>
		<div class="wsergo-main-card wsergo-district-main-card" style="background: linear-gradient(135deg, <?php echo $color; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>20 0%, <?php echo $color; ?>05 100%); border-radius: 20px; padding: 30px; margin-bottom: 30px; text-align: center; border: 2px solid <?php echo $color; ?>;">
			<div style="font-size: 14px; text-transform: uppercase; letter-spacing: 2px; color: <?php echo $color; ?>;"><?php esc_html_e( 'Индекс эргономичности', 'worldstat-ergonomics' ); ?></div>
			<div style="font-size: 72px; font-weight: bold; color: <?php echo $color; ?>; line-height: 1;"><?php echo esc_html( (string) $score ); ?></div>
			<div style="font-size: 24px; font-weight: 500; color: <?php echo $color; ?>;"><?php echo esc_html( (string) $ergo['level'] ); ?></div>
			<div style="margin-top: 15px; padding: 10px 20px; background: rgba(0,0,0,0.05); border-radius: 30px; display: inline-block;">
				<?php
				if ( $score >= 60 ) {
					esc_html_e( '🌟 Отличная эргономика среды', 'worldstat-ergonomics' );
				} elseif ( $score >= 40 ) {
					esc_html_e( '📈 Средний уровень, есть потенциал для улучшения', 'worldstat-ergonomics' );
				} else {
					esc_html_e( '⚠️ Требуется внимание к развитию среды', 'worldstat-ergonomics' );
				}
				?>
			</div>
		</div>
		<?php
	}

	public static function render_neural_partial( int $district_id ): void {
		if ( ! WSErgo_District_Bridge::is_neural_active() || ! class_exists( 'WSErgo_District_Neural' ) ) {
			return;
		}
		$post_id = $district_id;
		$path    = WSErgo_Level_Registry::path( 'territory', 'templates/partial-district-neural.php' );
		if ( is_readable( $path ) ) {
			include $path;
		}
	}

	/**
	 * @param array<string, mixed>|WP_Post $atts
	 */
	public static function shortcode_district_ergo_index( $atts ): string {
		$atts = shortcode_atts( [ 'id' => 0 ], is_array( $atts ) ? $atts : [], 'district_ergo_index' );
		$district_id = (int) $atts['id'] ?: (int) get_the_ID();
		$slug        = class_exists( 'WSErgo_District_Bridge' ) ? WSErgo_District_Bridge::district_post_type() : 'wsp_district';
		if ( $district_id <= 0 || get_post_type( $district_id ) !== $slug ) {
			return '';
		}
		$ergo = WSErgo_District_Bridge::get_ergonomics_index( $district_id );
		if ( (float) $ergo['score'] <= 0 ) {
			return '';
		}
		ob_start();
		?>
		<div class="wsergo-shortcode" style="display: inline-block; background: <?php echo esc_attr( (string) $ergo['color'] ); ?>; color: white; padding: 8px 16px; border-radius: 30px; font-size: 14px; font-weight: bold;">
			🏆 <?php esc_html_e( 'Эргономичность', 'worldstat-ergonomics' ); ?>: <?php echo esc_html( (string) $ergo['score'] ); ?> <?php esc_html_e( 'баллов', 'worldstat-ergonomics' ); ?> (<?php echo esc_html( (string) $ergo['level'] ); ?>)
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
