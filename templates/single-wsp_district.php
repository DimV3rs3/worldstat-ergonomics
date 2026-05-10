<?php
/**
 * Single district (квартал).
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$post_id = get_the_ID();
$index   = (float) get_post_meta( $post_id, WSErgo_CPT::META_INDEX, true );
$city_id = (int) get_post_meta( $post_id, WSErgo_CPT::META_CITY_ID, true );
$city    = $city_id ? get_post( $city_id ) : null;
$iso2    = '';
if ( $city ) {
	$iso2 = strtoupper( (string) get_post_meta( $city->ID, 'wscity_country_iso2', true ) );
}
$country_post = ( $iso2 && class_exists( 'WorldStat_Country_CPT' ) ) ? WorldStat_Country_CPT::get_by_code( $iso2 ) : null;

$subs = WSErgo_Data::get_subindices_for_post( $post_id );
$sub_labels = array_keys( $subs );
$sub_vals   = array_values( $subs );
$sub_vals   = array_map( fn( $v ) => $v > 0 ? $v : 0, $sub_vals );

?>

<div class="wsp-country-page wsp-district-page">
	<header class="wsp-country-hero wsp-city-hero">
		<div class="wsp-container">
			<div class="wsp-country-hero-inner">
				<span class="wsp-country-flag" style="font-size:2.5rem" aria-hidden="true">📍</span>
				<div class="wsp-country-hero-text">
					<h1 class="wsp-country-title"><?php the_title(); ?></h1>
					<p class="wsp-country-subtitle">
						<?php if ( $city ) : ?>
							<a href="<?php echo esc_url( get_permalink( $city ) ); ?>"><?php echo esc_html( get_the_title( $city ) ); ?></a>
							<?php if ( $country_post ) : ?>
								&nbsp;·&nbsp;
								<a href="<?php echo esc_url( get_permalink( $country_post ) ); ?>"><?php echo esc_html( get_the_title( $country_post ) ); ?></a>
							<?php endif; ?>
						<?php endif; ?>
					</p>
				</div>
			</div>
		</div>
	</header>

	<div class="wsp-container">
		<?php
		WorldStat_UI::stats_grid(
			[
				[
					'label' => __( 'Сводный индекс', 'worldstat-ergonomics' ),
					'value' => $index > 0 ? (string) $index : '—',
					'icon'  => 'admin-home',
				],
			],
			[ 'columns' => 1 ]
		);

		if ( array_filter( $sub_vals ) ) {
			WorldStat_UI::chart(
				[
					'type'     => 'bar',
					'title'    => __( 'Шесть уровней эргономичности района', 'worldstat-ergonomics' ),
					'labels'   => $sub_labels,
					'datasets' => [
						[
							'label' => __( 'Балл', 'worldstat-ergonomics' ),
							'data'  => $sub_vals,
							'color' => '#15803d',
						],
					],
					'height' => 280,
				]
			);
		}

		$wsergo_neural_partial = defined( 'WSERGO_DIR' ) ? WSERGO_DIR . 'templates/partial-district-neural.php' : '';
		if ( $wsergo_neural_partial && is_readable( $wsergo_neural_partial ) ) {
			include $wsergo_neural_partial;
		}

		if ( get_the_content() ) {
			echo '<div class="wsp-entry-content">';
			the_content();
			echo '</div>';
		}

		$buildings = WSErgo_CPT::get_buildings_for_district( $post_id );
		if ( ! empty( $buildings ) ) {
			echo '<h3 class="wsp-section-title">' . esc_html__( 'Здания', 'worldstat-ergonomics' ) . '</h3>';
			$rows = [];
			foreach ( $buildings as $b ) {
				$bi = (float) get_post_meta( $b->ID, WSErgo_CPT::META_INDEX, true );
				$rows[] = [
					'<a href="' . esc_url( get_permalink( $b ) ) . '">' . esc_html( get_the_title( $b ) ) . '</a>',
					$bi > 0 ? (string) $bi : '—',
				];
			}
			WorldStat_UI::table(
				[
					'headers'    => [ __( 'Здание', 'worldstat-ergonomics' ), __( 'Индекс', 'worldstat-ergonomics' ) ],
					'rows'       => $rows,
					'sortable'   => true,
					'searchable' => false,
				]
			);
		}
		?>
	</div>
</div>

<?php
get_footer();
