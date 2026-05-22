<?php
/**
 * Панель настроек уровня «здание» на странице Эргономичность.
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_Building_Admin_Panel {

	/**
	 * @return array{building:int,yard:int,room:int,with_index:int}
	 */
	private static function count_posts(): array {
		if ( ! class_exists( 'WSErgo_CPT' ) ) {
			return [ 'building' => 0, 'yard' => 0, 'room' => 0, 'with_index' => 0 ];
		}
		$building = (int) wp_count_posts( WSErgo_CPT::SLUG_BUILDING )->publish;
		$yard     = (int) wp_count_posts( WSErgo_CPT::SLUG_YARD )->publish;
		$room     = (int) wp_count_posts( WSErgo_CPT::SLUG_ROOM )->publish;
		// Без тяжёлого meta_query на странице настроек (при большом OSM-импорте может обрывать вывод HTML).
		return [ 'building' => $building, 'yard' => $yard, 'room' => $room, 'with_index' => 0 ];
	}

	public static function render(): void {
		$counts = self::count_posts();
		$agg    = class_exists( 'WSErgo_Settings' ) ? WSErgo_Settings::get_aggregation() : [];
		$weights = class_exists( 'WSErgo_Model' ) ? WSErgo_Model::get_weights() : [];
		$labels  = class_exists( 'WSErgo_Model' ) ? WSErgo_Model::get_dimension_labels() : [];
		$city_agg_url = admin_url( 'admin.php?page=wsergo-settings#ergo-city' );
		?>
		<h2><?php esc_html_e( 'Эргономичность зданий (OSM / Courtyard)', 'worldstat-ergonomics' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'После сканирования OSM Courtyard создаёт или обновляет записи wsp_building и wsp_yard, записывает POI-индикаторы в буфере и пересчитывает сводный индекс E. Помещения wsp_room — отдельный CPT; зоны помещений wsz_zone — уровень «Зона».', 'worldstat-ergonomics' ); ?>
		</p>

		<div class="postbox" style="margin-bottom:16px;">
			<div class="postbox-header"><h2 class="hndle"><?php esc_html_e( 'Статус данных', 'worldstat-ergonomics' ); ?></h2></div>
			<div class="inside">
				<table class="widefat striped" style="max-width:640px;">
					<tbody>
						<tr>
							<td><?php esc_html_e( 'Здания (wsp_building)', 'worldstat-ergonomics' ); ?></td>
							<td><strong><?php echo esc_html( (string) $counts['building'] ); ?></strong></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Придомовые (wsp_yard)', 'worldstat-ergonomics' ); ?></td>
							<td><strong><?php echo esc_html( (string) $counts['yard'] ); ?></strong></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Помещения (wsp_room)', 'worldstat-ergonomics' ); ?></td>
							<td><strong><?php echo esc_html( (string) $counts['room'] ); ?></strong></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Здания с рассчитанным индексом', 'worldstat-ergonomics' ); ?></td>
							<td><strong><?php echo esc_html( (string) $counts['with_index'] ); ?></strong></td>
						</tr>
					</tbody>
				</table>
				<p style="margin:12px 0 0;">
					<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . ( class_exists( 'WSErgo_CPT' ) ? WSErgo_CPT::SLUG_BUILDING : 'wsp_building' ) ) ); ?>"><?php esc_html_e( 'Список зданий', 'worldstat-ergonomics' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . ( class_exists( 'WSErgo_CPT' ) ? WSErgo_CPT::SLUG_ROOM : 'wsp_room' ) ) ); ?>"><?php esc_html_e( 'Список помещений', 'worldstat-ergonomics' ); ?></a>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'options-general.php?page=wsc-settings' ) ); ?>"><?php esc_html_e( 'Настройки Courtyard (OSM)', 'worldstat-ergonomics' ); ?></a>
				</p>
			</div>
		</div>

		<?php if ( class_exists( 'WSErgo_Courtyard_Bridge' ) ) : ?>
		<div class="postbox" style="margin-bottom:16px;">
			<div class="postbox-header"><h2 class="hndle"><?php esc_html_e( 'Индикаторы из буфера (POI)', 'worldstat-ergonomics' ); ?></h2></div>
			<div class="inside">
				<p class="description"><?php esc_html_e( 'Регистрируются при синке здания; используются в формуле здания.', 'worldstat-ergonomics' ); ?></p>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Ключ', 'worldstat-ergonomics' ); ?></th>
							<th><?php esc_html_e( 'Подпись', 'worldstat-ergonomics' ); ?></th>
							<th><?php esc_html_e( 'Критерий', 'worldstat-ergonomics' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( WSErgo_Courtyard_Bridge::POI_INDICATORS as $id => $meta ) : ?>
							<tr>
								<td><code><?php echo esc_html( (string) $id ); ?></code></td>
								<td><?php echo esc_html( (string) ( $meta[0] ?? '' ) ); ?></td>
								<td><?php echo esc_html( (string) ( $meta[1] ?? '' ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php else : ?>
		<div class="notice notice-warning inline"><p><?php esc_html_e( 'Мост Courtyard не загружен. Активируйте WorldStat Courtyard и Ergonomics.', 'worldstat-ergonomics' ); ?></p></div>
		<?php endif; ?>

		<div class="postbox" style="margin-bottom:16px;">
			<div class="postbox-header"><h2 class="hndle"><?php esc_html_e( 'Веса критериев (глобально)', 'worldstat-ergonomics' ); ?></h2></div>
			<div class="inside">
				<p class="description">
					<?php esc_html_e( 'Общие веса для расчёта здания и квартала. Редактирование — во вкладке «Город» → «Агрегация» и «Коэффициенты», либо в метабоксе карточки здания.', 'worldstat-ergonomics' ); ?>
					<a href="<?php echo esc_url( $city_agg_url ); ?>"><?php esc_html_e( 'Открыть агрегацию (Город)', 'worldstat-ergonomics' ); ?></a>
				</p>
				<?php if ( ! empty( $weights ) ) : ?>
					<ul style="margin:0;columns:2;max-width:520px;">
						<?php foreach ( $weights as $key => $w ) : ?>
							<li><code><?php echo esc_html( (string) $key ); ?></code> — <?php echo esc_html( (string) round( (float) $w, 2 ) ); ?>
								<?php if ( isset( $labels[ $key ] ) ) : ?>
									<span class="description">(<?php echo esc_html( (string) $labels[ $key ] ); ?>)</span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ( ! empty( $agg ) ) : ?>
					<p style="margin-top:12px;">
						<strong><?php esc_html_e( 'Агрегация', 'worldstat-ergonomics' ); ?>:</strong>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: room mode, 2: building weight in district */
								__( 'помещение→здание: %1$s; вес здания в квартале: %2$s', 'worldstat-ergonomics' ),
								(string) ( $agg['room_to_building'] ?? 'mean' ),
								(string) ( $agg['w_building_in_district'] ?? '0.5' )
							)
						);
						?>
					</p>
				<?php endif; ?>
			</div>
		</div>

		<div class="postbox">
			<div class="postbox-header"><h2 class="hndle"><?php esc_html_e( 'Как пересчитать', 'worldstat-ergonomics' ); ?></h2></div>
			<div class="inside">
				<ol style="margin:0;padding-left:1.4em;">
					<li><?php esc_html_e( 'Запустите импорт/скан OSM в Courtyard — сработают хуки wsc_building_imported и wsc_buffer_recomputed.', 'worldstat-ergonomics' ); ?></li>
					<li><?php esc_html_e( 'Для жилых зданий выполняется полный синк буфера, POI и индекса.', 'worldstat-ergonomics' ); ?></li>
					<li><?php esc_html_e( 'Для других категорий — откройте здание на карте Courtyard (ensure_building_post).', 'worldstat-ergonomics' ); ?></li>
					<li><?php esc_html_e( 'Ручная правка оценок — в метабоксе «Эргономика здания» у записи wsp_building.', 'worldstat-ergonomics' ); ?></li>
				</ol>
			</div>
		</div>
		<?php
	}
}
