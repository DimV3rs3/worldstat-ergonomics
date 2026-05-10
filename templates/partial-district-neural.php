<?php
/**
 * Блок модели района на single wsp_district.
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WSErgo_District_Neural' ) ) {
	return;
}

$district_id = isset( $post_id ) ? (int) $post_id : get_the_ID();
if ( $district_id <= 0 ) {
	return;
}

$neural = WSErgo_District_Neural::get_all_neural_metrics( $district_id );
if ( empty( $neural['composite'] ) ) {
	$neural = WSErgo_District_Neural::update_all_neural_metrics( $district_id );
}

$classic = WSErgo_District_Metrics::get_all_metrics( $district_id );

$district_row = null;
if ( class_exists( 'WSDistricts_CPT' ) ) {
	$district_row = WSDistricts_CPT::get_district( $district_id );
}
if ( ! is_array( $district_row ) ) {
	$district_row = [
		'population'  => (int) get_post_meta( $district_id, 'wsdistrict_population', true ),
		'area'        => (float) get_post_meta( $district_id, 'wsdistrict_area', true ),
		'density'     => (float) get_post_meta( $district_id, 'wsdistrict_density', true ),
		'established' => (string) get_post_meta( $district_id, 'wsdistrict_established', true ),
		'lat'         => (float) get_post_meta( $district_id, 'wsdistrict_lat', true ),
		'lng'         => (float) get_post_meta( $district_id, 'wsdistrict_lng', true ),
	];
}

$composite = (float) $neural['composite'];
$comp_color = $composite >= 70 ? '#10b981' : ( $composite >= 50 ? '#3b82f6' : ( $composite >= 30 ? '#f59e0b' : '#ef4444' ) );

$colors = [
	'safety'          => '#8b5cf6',
	'functionality'   => '#3b82f6',
	'comfort'         => '#10b981',
	'livability'      => '#f59e0b',
	'masterability'   => '#06b6d4',
	'manageability'   => '#ef4444',
];

$criteria_list = [
	'safety'        => [ __( 'Безопасность', 'worldstat-ergonomics' ), (float) $neural['safety'], (float) $classic['safety'], $colors['safety'] ],
	'functionality' => [ __( 'Функциональность', 'worldstat-ergonomics' ), (float) $neural['functionality'], (float) $classic['functionality'], $colors['functionality'] ],
	'comfort'       => [ __( 'Комфортность', 'worldstat-ergonomics' ), (float) $neural['comfort'], (float) $classic['comfort'], $colors['comfort'] ],
	'manageability' => [ __( 'Управляемость', 'worldstat-ergonomics' ), (float) $neural['manageability'], (float) $classic['manageability'], $colors['manageability'] ],
	'livability'    => [ __( 'Обитаемость', 'worldstat-ergonomics' ), (float) $neural['livability'], (float) $classic['livability'], $colors['livability'] ],
	'masterability' => [ __( 'Освояемость', 'worldstat-ergonomics' ), (float) $neural['masterability'], (float) $classic['masterability'], $colors['masterability'] ],
];

$has_classic = (bool) array_filter( $classic, static fn( $v ) => (float) $v > 0 );
?>

<section class="wsergo-district-model-section" style="margin: 2rem 0; padding: 1.25rem; background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0;">
	<h2 class="wsp-section-title" style="margin-top: 0;"><?php esc_html_e( 'Модель эргономичности района', 'worldstat-ergonomics' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Оценка по полям wsdistrict_*; при отсутствии данных используются нейтральные значения. Сравнение с «классикой» возможно, если заполнены исходные шесть баллов района.', 'worldstat-ergonomics' ); ?></p>

	<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin: 1rem 0;">
		<?php foreach ( $criteria_list as $row ) : ?>
			<div style="background: #fff; padding: 12px; border-radius: 8px; border-left: 4px solid <?php echo esc_attr( $row[3] ); ?>;">
				<div style="font-size: 12px; color: #64748b;"><?php echo esc_html( $row[0] ); ?></div>
				<div style="font-size: 1.5rem; font-weight: 700; color: <?php echo esc_attr( $row[3] ); ?>;"><?php echo (int) round( $row[1] ); ?></div>
			</div>
		<?php endforeach; ?>
	</div>

	<?php if ( $has_classic ) : ?>
		<h3 style="font-size: 1rem;"><?php esc_html_e( 'Сравнение с исходными баллами', 'worldstat-ergonomics' ); ?></h3>
		<table class="widefat striped" style="background: #fff; margin-top: 8px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Критерий', 'worldstat-ergonomics' ); ?></th>
					<th><?php esc_html_e( 'Модель', 'worldstat-ergonomics' ); ?></th>
					<th><?php esc_html_e( 'Исходный', 'worldstat-ergonomics' ); ?></th>
					<th><?php esc_html_e( 'Δ', 'worldstat-ergonomics' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $criteria_list as $criterion ) : ?>
					<?php
					$diff       = round( $criterion[1] - $criterion[2], 1 );
					$diff_color = $diff > 0 ? '#10b981' : ( $diff < 0 ? '#ef4444' : '#6b7280' );
					$diff_text  = $diff > 0 ? '+' . (string) $diff : (string) $diff;
					?>
					<tr>
						<td><?php echo esc_html( $criterion[0] ); ?></td>
						<td style="font-weight: 600; color: <?php echo esc_attr( $criterion[3] ); ?>;"><?php echo (int) round( $criterion[1] ); ?></td>
						<td><?php echo (int) round( $criterion[2] ); ?></td>
						<td style="color: <?php echo esc_attr( $diff_color ); ?>;"><?php echo esc_html( $diff_text ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<div style="margin-top: 1.25rem; padding: 1rem; text-align: center; background: <?php echo esc_attr( $comp_color ); ?>22; border: 2px solid <?php echo esc_attr( $comp_color ); ?>; border-radius: 12px;">
		<div style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.06em; color: #475569;"><?php esc_html_e( 'Сводный индекс (модель)', 'worldstat-ergonomics' ); ?></div>
		<div style="font-size: 2.5rem; font-weight: 800; color: <?php echo esc_attr( $comp_color ); ?>;"><?php echo (int) round( $composite ); ?><span style="font-size: 1rem;">/100</span></div>
	</div>

	<?php
	$pop   = (int) ( $district_row['population'] ?? 0 );
	$area  = (float) ( $district_row['area'] ?? 0 );
	$dens  = (float) ( $district_row['density'] ?? 0 );
	$est   = (string) ( $district_row['established'] ?? '' );
	if ( $pop || $area || $dens || $est !== '' ) :
		?>
		<h3 style="font-size: 1rem; margin-top: 1.5rem;"><?php esc_html_e( 'Данные района', 'worldstat-ergonomics' ); ?></h3>
		<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px;">
			<?php if ( $pop ) : ?>
				<div style="background: #fff; padding: 10px; border-radius: 8px; text-align: center;">
					<div style="font-weight: 700;"><?php echo esc_html( number_format_i18n( $pop ) ); ?></div>
					<div style="font-size: 12px; color: #64748b;"><?php esc_html_e( 'Население', 'worldstat-ergonomics' ); ?></div>
				</div>
			<?php endif; ?>
			<?php if ( $area ) : ?>
				<div style="background: #fff; padding: 10px; border-radius: 8px; text-align: center;">
					<div style="font-weight: 700;"><?php echo esc_html( number_format_i18n( $area, 1 ) ); ?> <?php esc_html_e( 'га', 'worldstat-ergonomics' ); ?></div>
					<div style="font-size: 12px; color: #64748b;"><?php esc_html_e( 'Площадь', 'worldstat-ergonomics' ); ?></div>
				</div>
			<?php endif; ?>
			<?php if ( $dens ) : ?>
				<div style="background: #fff; padding: 10px; border-radius: 8px; text-align: center;">
					<div style="font-weight: 700;"><?php echo esc_html( number_format_i18n( $dens ) ); ?></div>
					<div style="font-size: 12px; color: #64748b;"><?php esc_html_e( 'Плотность', 'worldstat-ergonomics' ); ?></div>
				</div>
			<?php endif; ?>
			<?php if ( $est !== '' ) : ?>
				<div style="background: #fff; padding: 10px; border-radius: 8px; text-align: center;">
					<div style="font-weight: 700;"><?php echo esc_html( $est ); ?></div>
					<div style="font-size: 12px; color: #64748b;"><?php esc_html_e( 'Основание', 'worldstat-ergonomics' ); ?></div>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</section>
