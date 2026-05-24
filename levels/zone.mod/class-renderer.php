<?php
/**
 * Публичный блок эргономики на странице зоны.
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_Zone_Renderer {

	public static function render_single_summary( int $zone_id ): void {
		if ( ! class_exists( 'WSErgo_Zone_Bridge' ) ) {
			return;
		}
		$ergo = WSErgo_Zone_Bridge::get_zone_ergonomics( $zone_id );
		if ( (float) $ergo['score'] <= 0 ) {
			return;
		}
		$color = esc_attr( (string) $ergo['color'] );
		?>
		<div class="wsergo-zone-summary" style="margin: 20px 0; padding: 20px; border-radius: 12px; border: 2px solid <?php echo $color; ?>; background: <?php echo $color; ?>08;">
			<div style="font-size: 12px; text-transform: uppercase; color: <?php echo $color; ?>;"><?php esc_html_e( 'Эргономичность зоны', 'worldstat-ergonomics' ); ?></div>
			<div style="font-size: 48px; font-weight: bold; color: <?php echo $color; ?>;"><?php echo esc_html( (string) $ergo['score'] ); ?><span style="font-size: 16px;">/100</span></div>
			<div style="font-size: 18px; color: <?php echo $color; ?>;"><?php echo esc_html( (string) $ergo['level'] ); ?></div>
		</div>
		<?php
	}
}
