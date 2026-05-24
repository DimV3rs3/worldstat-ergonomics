<?php
/**
 * Панель настроек уровня «зона помещения» (wsz_zone).
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_Zone_Admin_Panel {

	/**
	 * @return string
	 */
	private static function zone_post_type_slug(): string {
		if ( ! class_exists( 'WSZ_CPT' ) ) {
			return 'wsz_zone';
		}
		$consts = (array) ( new ReflectionClass( 'WSZ_CPT' ) )->getConstants();
		if ( ! empty( $consts['SLUG'] ) && is_string( $consts['SLUG'] ) ) {
			return $consts['SLUG'];
		}
		if ( ! empty( $consts['POST_TYPE'] ) && is_string( $consts['POST_TYPE'] ) ) {
			return $consts['POST_TYPE'];
		}
		return 'wsz_zone';
	}

	public static function render(): void {
		$zone_count = 0;
		$avg        = 0.0;
		if ( class_exists( 'WSZ_Data' ) ) {
			if ( is_callable( [ 'WSZ_Data', 'get_total_zones' ] ) ) {
				$zone_count = (int) WSZ_Data::get_total_zones();
			}
			if ( is_callable( [ 'WSZ_Data', 'get_global_avg_ergonomics' ] ) ) {
				$avg = (float) WSZ_Data::get_global_avg_ergonomics();
			}
		}
		if ( $zone_count <= 0 ) {
			$slug = self::zone_post_type_slug();
			if ( post_type_exists( $slug ) ) {
				$zone_count = (int) wp_count_posts( $slug )->publish;
			}
		}

		$weights = [
			'lighting' => 0.30,
			'safety'   => 0.35,
			'comfort'  => 0.35,
		];
		?>
		<h2><?php esc_html_e( 'Эргономичность зон помещений', 'worldstat-ergonomics' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Зоны wsz_zone — каталог помещений/зон в плагине WorldStat Zones. Индекс wsz_ergonomics считается при импорте CSV из lighting, safety, comfort. CPT wsp_room (иерархия здание→помещение) — отдельно, см. вкладку «Здание».', 'worldstat-ergonomics' ); ?>
		</p>

		<div class="postbox" style="margin-bottom:16px;">
			<div class="postbox-header"><h2 class="hndle"><?php esc_html_e( 'Сводка', 'worldstat-ergonomics' ); ?></h2></div>
			<div class="inside">
				<p>
					<strong><?php esc_html_e( 'Зон в каталоге:', 'worldstat-ergonomics' ); ?></strong>
					<?php echo esc_html( (string) $zone_count ); ?>
				</p>
				<p>
					<strong><?php esc_html_e( 'Средняя эргономика (глобально):', 'worldstat-ergonomics' ); ?></strong>
					<?php echo esc_html( (string) round( $avg, 1 ) ); ?> / 100
				</p>
				<p style="margin:12px 0 0;">
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=worldstat-zones' ) ); ?>"><?php esc_html_e( 'Импорт и каталог зон', 'worldstat-ergonomics' ); ?></a>
					<?php
					$zone_slug = self::zone_post_type_slug();
					if ( post_type_exists( $zone_slug ) ) :
						?>
						<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . $zone_slug ) ); ?>"><?php esc_html_e( 'Все записи wsz_zone', 'worldstat-ergonomics' ); ?></a>
					<?php endif; ?>
				</p>
			</div>
		</div>

		<div class="postbox" style="margin-bottom:16px;">
			<div class="postbox-header"><h2 class="hndle"><?php esc_html_e( 'Формула индекса (при импорте)', 'worldstat-ergonomics' ); ?></h2></div>
			<div class="inside">
				<p class="description"><?php esc_html_e( 'Взвешенное среднее трёх метрик (0–100). Веса заданы в WSZ_Metrics_Calculator.', 'worldstat-ergonomics' ); ?></p>
				<table class="widefat striped" style="max-width:400px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Метрика', 'worldstat-ergonomics' ); ?></th>
							<th><?php esc_html_e( 'Вес', 'worldstat-ergonomics' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$labels = [
							'lighting' => __( 'Освещение', 'worldstat-ergonomics' ),
							'safety'   => __( 'Безопасность', 'worldstat-ergonomics' ),
							'comfort'  => __( 'Комфорт', 'worldstat-ergonomics' ),
						];
						foreach ( $weights as $key => $w ) :
							?>
							<tr>
								<td><?php echo esc_html( $labels[ $key ] ?? $key ); ?></td>
								<td><?php echo esc_html( (string) round( $w * 100 ) ); ?>%</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>

		<?php if ( class_exists( 'WSZ_ML' ) ) : ?>
		<div class="postbox">
			<div class="postbox-header"><h2 class="hndle"><?php esc_html_e( 'ML-анализ (Zones)', 'worldstat-ergonomics' ); ?></h2></div>
			<div class="inside">
				<p class="description">
					<?php esc_html_e( 'Экспорт датасета и запуск ML остаются в плагине Zones (вкладка ML). Ergonomics использует готовый wsz_ergonomics для отображения.', 'worldstat-ergonomics' ); ?>
				</p>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=worldstat-zones' ) ); ?>"><?php esc_html_e( 'Открыть WorldStat Zones', 'worldstat-ergonomics' ); ?></a>
			</div>
		</div>
		<?php endif; ?>
		<?php
	}
}
