<?php
/**
 * CPT: кварталы, здания, помещения, придомовая территория.
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_CPT {

	public const SLUG_DISTRICT = 'wsp_district';
	public const SLUG_BUILDING = 'wsp_building';
	public const SLUG_ROOM     = 'wsp_room';
	public const SLUG_YARD     = 'wsp_yard';

	public const META_CITY_ID       = 'wsergo_city_id';
	public const META_DISTRICT_ID   = 'wsergo_district_id';
	public const META_BUILDING_ID   = 'wsergo_building_id';

	public const META_INDEX = 'wsergo_index';

	/** '1' — не пересчитывать индекс из дочерних объектов (ручной/зафиксированный). */
	public const META_INDEX_LOCKED = 'wsergo_index_locked';

	/** @deprecated 1.1.0 Используйте wsergo_dim_*; оставлены для совместимости и миграции. */
	public const META_ACCESSIBILITY = 'wsergo_accessibility';
	public const META_SAFETY        = 'wsergo_safety';
	public const META_COMFORT       = 'wsergo_comfort';
	public const META_ENVIRONMENT   = 'wsergo_environment';

	/** JSON листовых показателей для расширенного расчёта (опционально). */
	public const META_INDICATORS_JSON = 'wsergo_indicators_json';

	/** JSON переопределения коэффициентов k_* для записи. */
	public const META_COEFF_OVERRIDES = 'wsergo_coeff_overrides_json';

	/** Площадь помещения, м² (вес при агрегации в здание). */
	public const META_AREA = 'wsergo_area_sqm';

	public const META_LAT = 'wsergo_lat';
	public const META_LNG = 'wsergo_lng';

	public const META_GEOJSON = 'wsergo_geojson';
	public const META_ADDRESS = 'wsergo_address';
	public const META_YEAR    = 'wsergo_year';

	public function __construct() {
		add_action( 'init', [ $this, 'register_post_types' ] );
		add_action( 'init', [ $this, 'register_meta' ] );
		add_action( 'save_post_' . self::SLUG_DISTRICT, [ $this, 'save_district' ], 10, 2 );
		add_action( 'save_post_' . self::SLUG_BUILDING, [ $this, 'save_building' ], 10, 2 );
		add_action( 'save_post_' . self::SLUG_ROOM, [ $this, 'save_room' ], 10, 2 );
		add_action( 'save_post_' . self::SLUG_YARD, [ $this, 'save_yard' ], 10, 2 );
	}

	public static function activate_flush(): void {
		$tmp = new self();
		$tmp->register_post_types();
		flush_rewrite_rules();
	}

	public function register_post_types(): void {
		register_post_type(
			self::SLUG_DISTRICT,
			[
				'labels'             => [
					'name'          => __( 'Кварталы (эргономика)', 'worldstat-ergonomics' ),
					'singular_name' => __( 'Квартал', 'worldstat-ergonomics' ),
					'add_new_item'  => __( 'Добавить квартал', 'worldstat-ergonomics' ),
					'edit_item'     => __( 'Редактировать квартал', 'worldstat-ergonomics' ),
					'all_items'     => __( 'Кварталы', 'worldstat-ergonomics' ),
				],
				'public'             => true,
				'show_ui'            => true,
				'show_in_menu'       => false,
				'menu_icon'          => 'dashicons-location-alt',
				'menu_position'      => 26,
				'show_in_rest'       => true,
				'rest_base'          => 'districts',
				'rewrite'            => [ 'slug' => 'district', 'with_front' => false ],
				'has_archive'        => false,
				'supports'           => [ 'title', 'editor', 'thumbnail' ],
				'capability_type'    => 'post',
			]
		);

		register_post_type(
			self::SLUG_BUILDING,
			[
				'labels'             => [
					'name'          => __( 'Здания (эргономика)', 'worldstat-ergonomics' ),
					'singular_name' => __( 'Здание', 'worldstat-ergonomics' ),
					'add_new_item'  => __( 'Добавить здание', 'worldstat-ergonomics' ),
					'edit_item'     => __( 'Редактировать здание', 'worldstat-ergonomics' ),
					'all_items'     => __( 'Здания', 'worldstat-ergonomics' ),
				],
				'public'             => true,
				'show_ui'            => true,
				'show_in_menu'       => false,
				'show_in_rest'       => true,
				'rest_base'          => 'buildings',
				'rewrite'            => [ 'slug' => 'building', 'with_front' => false ],
				'has_archive'        => false,
				'supports'           => [ 'title', 'editor', 'thumbnail' ],
				'capability_type'    => 'post',
			]
		);

		register_post_type(
			self::SLUG_ROOM,
			[
				'labels'             => [
					'name'          => __( 'Помещения (эргономика)', 'worldstat-ergonomics' ),
					'singular_name' => __( 'Помещение', 'worldstat-ergonomics' ),
					'add_new_item'  => __( 'Добавить помещение', 'worldstat-ergonomics' ),
					'edit_item'     => __( 'Редактировать помещение', 'worldstat-ergonomics' ),
					'all_items'     => __( 'Помещения', 'worldstat-ergonomics' ),
				],
				'public'             => true,
				'show_ui'            => true,
				'show_in_menu'       => false,
				'show_in_rest'       => true,
				'rest_base'          => 'rooms',
				'rewrite'            => [ 'slug' => 'room', 'with_front' => false ],
				'has_archive'        => false,
				'supports'           => [ 'title', 'editor', 'thumbnail' ],
				'capability_type'    => 'post',
			]
		);

		register_post_type(
			self::SLUG_YARD,
			[
				'labels'             => [
					'name'          => __( 'Придомовые территории', 'worldstat-ergonomics' ),
					'singular_name' => __( 'Придомовая территория', 'worldstat-ergonomics' ),
					'add_new_item'  => __( 'Добавить участок', 'worldstat-ergonomics' ),
					'edit_item'     => __( 'Редактировать участок', 'worldstat-ergonomics' ),
					'all_items'     => __( 'Придомовые территории', 'worldstat-ergonomics' ),
				],
				'public'             => true,
				'show_ui'            => true,
				'show_in_menu'       => false,
				'show_in_rest'       => true,
				'rest_base'          => 'yards',
				'rewrite'            => [ 'slug' => 'adjacent-yard', 'with_front' => false ],
				'has_archive'        => false,
				'supports'           => [ 'title', 'editor', 'thumbnail' ],
				'capability_type'    => 'post',
			]
		);
	}

	public function register_meta(): void {
		$dim_types = [ self::SLUG_DISTRICT, self::SLUG_BUILDING, self::SLUG_ROOM, self::SLUG_YARD ];
		$dim_keys  = [];
		foreach ( WSErgo_Model::DIMENSION_KEYS as $dim ) {
			$dim_keys[ WSErgo_Model::meta_key_for_dimension( $dim ) ] = [ 'type' => 'number', 'post_types' => $dim_types ];
		}

		$fields = array_merge(
			[
				self::META_CITY_ID     => [ 'type' => 'integer', 'post_types' => [ self::SLUG_DISTRICT ] ],
				self::META_DISTRICT_ID => [ 'type' => 'integer', 'post_types' => [ self::SLUG_BUILDING, self::SLUG_YARD ] ],
				self::META_BUILDING_ID => [ 'type' => 'integer', 'post_types' => [ self::SLUG_ROOM ] ],
				self::META_INDEX       => [ 'type' => 'number', 'post_types' => $dim_types ],
				self::META_INDEX_LOCKED => [ 'type' => 'string', 'post_types' => [ self::SLUG_DISTRICT, self::SLUG_BUILDING, self::SLUG_ROOM, self::SLUG_YARD ] ],
				self::META_INDICATORS_JSON => [ 'type' => 'string', 'post_types' => [ self::SLUG_DISTRICT, self::SLUG_BUILDING, self::SLUG_ROOM, self::SLUG_YARD ] ],
				self::META_COEFF_OVERRIDES => [ 'type' => 'string', 'post_types' => $dim_types ],
				self::META_ACCESSIBILITY => [ 'type' => 'number', 'post_types' => [ self::SLUG_DISTRICT, self::SLUG_BUILDING ] ],
				self::META_SAFETY        => [ 'type' => 'number', 'post_types' => [ self::SLUG_DISTRICT, self::SLUG_BUILDING ] ],
				self::META_COMFORT       => [ 'type' => 'number', 'post_types' => [ self::SLUG_DISTRICT, self::SLUG_BUILDING ] ],
				self::META_ENVIRONMENT   => [ 'type' => 'number', 'post_types' => [ self::SLUG_DISTRICT, self::SLUG_BUILDING ] ],
				self::META_AREA          => [ 'type' => 'number', 'post_types' => [ self::SLUG_ROOM ] ],
				self::META_LAT       => [ 'type' => 'number', 'post_types' => [ self::SLUG_BUILDING, self::SLUG_YARD ] ],
				self::META_LNG       => [ 'type' => 'number', 'post_types' => [ self::SLUG_BUILDING, self::SLUG_YARD ] ],
				self::META_GEOJSON   => [ 'type' => 'string', 'post_types' => [ self::SLUG_DISTRICT, self::SLUG_YARD ] ],
				self::META_ADDRESS   => [ 'type' => 'string', 'post_types' => [ self::SLUG_BUILDING ] ],
				self::META_YEAR      => [ 'type' => 'integer', 'post_types' => [ self::SLUG_BUILDING ] ],
			],
			$dim_keys
		);

		foreach ( $fields as $key => $cfg ) {
			foreach ( $cfg['post_types'] as $pt ) {
				register_post_meta(
					$pt,
					$key,
					[
						'type'          => $cfg['type'],
						'single'        => true,
						'show_in_rest'  => true,
						'auth_callback' => function () {
							return current_user_can( 'edit_posts' );
						},
					]
				);
			}
		}

		if ( class_exists( 'WSErgo_Indicators' ) ) {
			foreach ( WSErgo_Indicators::get_definitions() as $def ) {
				$mkey = WSErgo_Indicators::meta_key_for_raw( $def['id'] );
				foreach ( $dim_types as $pt ) {
					register_post_meta(
						$pt,
						$mkey,
						[
							'type'          => 'number',
							'single'        => true,
							'show_in_rest'  => true,
							'auth_callback' => function () {
								return current_user_can( 'edit_posts' );
							},
						]
					);
				}
			}
		}
	}

	public function save_district( int $post_id, WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( 'auto-draft' === $post->post_status ) {
			return;
		}
		$this->maybe_fill_index( $post_id );
	}

	public function save_building( int $post_id, WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( 'auto-draft' === $post->post_status ) {
			return;
		}
		$this->maybe_fill_index( $post_id );
		WSErgo_Calculator::bubble_from_building( $post_id );
	}

	public function save_room( int $post_id, WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( 'auto-draft' === $post->post_status ) {
			return;
		}
		WSErgo_Calculator::compute_and_store_index( $post_id );
		WSErgo_Calculator::bubble_from_room( $post_id );
	}

	public function save_yard( int $post_id, WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( 'auto-draft' === $post->post_status ) {
			return;
		}
		WSErgo_Calculator::compute_and_store_index( $post_id );
		WSErgo_Calculator::bubble_from_yard( $post_id );
	}

	private function maybe_fill_index( int $post_id ): void {
		self::recalculate_index_if_needed( $post_id );
	}

	public static function recalculate_index_if_needed( int $post_id ): void {
		WSErgo_Calculator::compute_and_store_index( $post_id );
	}

	/**
	 * @return WP_Post[]
	 */
	public static function get_rooms_for_building( int $building_id ): array {
		return get_posts(
			[
				'post_type'      => self::SLUG_ROOM,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'meta_key'       => self::META_BUILDING_ID,
				'meta_value'     => $building_id,
			]
		);
	}

	/**
	 * @return WP_Post[]
	 */
	public static function get_yards_for_district( int $district_id ): array {
		return get_posts(
			[
				'post_type'      => self::SLUG_YARD,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'meta_key'       => self::META_DISTRICT_ID,
				'meta_value'     => $district_id,
			]
		);
	}

	/**
	 * Кварталы, привязанные к городу.
	 *
	 * @return WP_Post[]
	 */
	public static function get_districts_for_city( int $city_id ): array {
		return get_posts(
			[
				'post_type'      => self::SLUG_DISTRICT,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'meta_key'       => self::META_CITY_ID,
				'meta_value'     => $city_id,
			]
		);
	}

	/**
	 * Здания в квартале.
	 *
	 * @return WP_Post[]
	 */
	public static function get_buildings_for_district( int $district_id ): array {
		return get_posts(
			[
				'post_type'      => self::SLUG_BUILDING,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'meta_key'       => self::META_DISTRICT_ID,
				'meta_value'     => $district_id,
			]
		);
	}

	/**
	 * Все здания страны (по ISO2 через города).
	 *
	 * @return WP_Post[]
	 */
	public static function get_buildings_for_country( string $iso2 ): array {
		$cities       = WSCities_CPT::get_cities_for_country( strtoupper( $iso2 ) );
		$district_ids = [];
		foreach ( $cities as $c ) {
			$district_ids = array_merge( $district_ids, self::get_district_ids_for_city( (int) $c['id'] ) );
		}
		$district_ids = array_unique( array_map( 'intval', $district_ids ) );
		if ( empty( $district_ids ) ) {
			return [];
		}

		return get_posts(
			[
				'post_type'      => self::SLUG_BUILDING,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_query'     => [
					[
						'key'     => self::META_DISTRICT_ID,
						'value'   => $district_ids,
						'compare' => 'IN',
					],
				],
			]
		);
	}

	/**
	 * @return int[]
	 */
	public static function get_district_ids_for_city( int $city_id ): array {
		$districts = self::get_districts_for_city( $city_id );
		return array_map( 'intval', wp_list_pluck( $districts, 'ID' ) );
	}

	public static function count_districts_in_country( string $iso2 ): int {
		$cities = WSCities_CPT::get_cities_for_country( strtoupper( $iso2 ) );
		$n      = 0;
		foreach ( $cities as $c ) {
			$n += count( self::get_districts_for_city( (int) $c['id'] ) );
		}
		return $n;
	}

	public static function count_buildings_in_country( string $iso2 ): int {
		return count( self::get_buildings_for_country( $iso2 ) );
	}
}
