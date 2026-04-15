<?php
/**
 * Single building.
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$post_id    = get_the_ID();
$index      = (float) get_post_meta( $post_id, WSErgo_CPT::META_INDEX, true );
$district_id = (int) get_post_meta( $post_id, WSErgo_CPT::META_DISTRICT_ID, true );
$district   = $district_id ? get_post( $district_id ) : null;
$city       = null;
$iso2       = '';
if ( $district ) {
	$city_id = (int) get_post_meta( $district->ID, WSErgo_CPT::META_CITY_ID, true );
	$city    = $city_id ? get_post( $city_id ) : null;
	if ( $city ) {
		$iso2 = strtoupper( (string) get_post_meta( $city->ID, 'wscity_country_iso2', true ) );
	}
}
$country_post = ( $iso2 && class_exists( 'WorldStat_Country_CPT' ) ) ? WorldStat_Country_CPT::get_by_code( $iso2 ) : null;

$lat = (float) get_post_meta( $post_id, WSErgo_CPT::META_LAT, true );
$lng = (float) get_post_meta( $post_id, WSErgo_CPT::META_LNG, true );
$addr = (string) get_post_meta( $post_id, WSErgo_CPT::META_ADDRESS, true );
$year = (int) get_post_meta( $post_id, WSErgo_CPT::META_YEAR, true );

$subs = WSErgo_Data::get_subindices_for_post( $post_id );
$sub_labels = array_keys( $subs );
$sub_vals   = array_values( $subs );
$sub_vals   = array_map( fn( $v ) => $v > 0 ? $v : 0, $sub_vals );

?>

<div class="wsp-country-page wsp-building-page">
	<header class="wsp-country-hero wsp-city-hero">
		<div class="wsp-container">
			<div class="wsp-country-hero-inner">
				<span class="wsp-country-flag" style="font-size:2.5rem" aria-hidden="true">🏢</span>
				<div class="wsp-country-hero-text">
					<h1 class="wsp-country-title"><?php the_title(); ?></h1>
					<p class="wsp-country-subtitle">
						<?php if ( $district ) : ?>
							<a href="<?php echo esc_url( get_permalink( $district ) ); ?>"><?php echo esc_html( get_the_title( $district ) ); ?></a>
						<?php endif; ?>
						<?php if ( $city ) : ?>
							&nbsp;·&nbsp;
							<a href="<?php echo esc_url( get_permalink( $city ) ); ?>"><?php echo esc_html( get_the_title( $city ) ); ?></a>
						<?php endif; ?>
						<?php if ( $country_post ) : ?>
							&nbsp;·&nbsp;
							<a href="<?php echo esc_url( get_permalink( $country_post ) ); ?>"><?php echo esc_html( get_the_title( $country_post ) ); ?></a>
						<?php endif; ?>
					</p>
					<?php if ( $addr ) : ?>
						<span class="wsp-country-code"><?php echo esc_html( $addr ); ?></span>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</header>

	<div class="wsp-container">
		<?php
		$grid = [
			[
				'label' => __( 'Индекс', 'worldstat-ergonomics' ),
				'value' => $index > 0 ? (string) $index : '—',
				'icon'  => 'admin-home',
			],
		];
		if ( $year > 0 ) {
			$grid[] = [
				'label' => __( 'Год', 'worldstat-ergonomics' ),
				'value' => (string) $year,
				'icon'  => 'calendar',
			];
		}
		WorldStat_UI::stats_grid( $grid, [ 'columns' => min( 2, count( $grid ) ) ] );

		if ( array_filter( $sub_vals ) ) {
			WorldStat_UI::chart(
				[
					'type'     => 'bar',
					'title'    => __( 'Шесть уровней эргономичности', 'worldstat-ergonomics' ),
					'labels'   => $sub_labels,
					'datasets' => [
						[
							'label' => __( 'Балл', 'worldstat-ergonomics' ),
							'data'  => $sub_vals,
							'color' => '#15803d',
						],
					],
					'height' => 260,
				]
			);
		}

		if ( get_the_content() ) {
			echo '<div class="wsp-entry-content">';
			the_content();
			echo '</div>';
		}

		if ( $lat && $lng ) {
			echo '<h3 class="wsp-section-title">' . esc_html__( 'На карте', 'worldstat-ergonomics' ) . '</h3>';
			WorldStat_UI::map(
				[
					'lat'     => $lat,
					'lng'     => $lng,
					'zoom'    => 16,
					'height'  => 320,
					'grid'    => true,
					'markers' => [
						[
							'lat'    => $lat,
							'lng'    => $lng,
							'title'  => get_the_title(),
							'popup'  => '<strong>' . esc_html( get_the_title() ) . '</strong>',
							'color'  => '#15803d',
							'radius' => 8,
						],
					],
					'tile_style' => 'carto-light',
				]
			);
		}
		?>
	</div>
</div>

<?php
get_footer();
