<?php
/**
 * Админ: настройки нейро-эргономики районов, метабокс, AJAX вкладки территории.
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_Territory_Admin {

	public static function init(): void {
		add_action( 'admin_post_wsergo_toggle_settings', [ __CLASS__, 'handle_toggle_settings' ] );
		add_action( 'wp_ajax_wsergo_save_district_criteria_weights', [ __CLASS__, 'ajax_save_criteria_weights' ] );
		add_action( 'wp_ajax_wsergo_retrain_neural_networks', [ __CLASS__, 'ajax_retrain_all' ] );
		add_action( 'add_meta_boxes', [ __CLASS__, 'register_metaboxes' ], 20 );
	}

	public static function handle_toggle_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied', 'worldstat-ergonomics' ) );
		}
		check_admin_referer( 'wsergo_toggle' );

		if ( isset( $_POST['ergo_enabled'] ) ) {
			$new_status = (int) $_POST['ergo_enabled'];
			update_option( WSErgo_District_Bridge::OPTION_NEURAL_ACTIVE, (bool) $new_status );
			if ( $new_status && class_exists( 'WSErgo_District_Bridge' ) ) {
				update_option( WSErgo_District_Bridge::OPTION_LAST_UPDATE, current_time( 'mysql' ) );
			}
		}

		if ( isset( $_POST['recalc_ergo'] ) && class_exists( 'WSErgo_District_Bridge' ) ) {
			WSErgo_District_Bridge::update_all_districts();
		}

		$redirect = admin_url( 'admin.php?page=worldstat-districts' );
		if ( ! empty( $_POST['redirect_to'] ) ) {
			$custom = esc_url_raw( wp_unslash( (string) $_POST['redirect_to'] ) );
			if ( $custom !== '' && wp_validate_redirect( $custom, false ) ) {
				$redirect = $custom;
			}
		} elseif ( wp_get_referer() ) {
			$redirect = wp_get_referer();
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Панель управления нейро-эргономикой на вкладке «Территория».
	 */
	public static function render_scope_toolbar(): void {
		if ( ! class_exists( 'WSErgo_District_Bridge' ) ) {
			return;
		}
		$active     = WSErgo_District_Bridge::is_neural_active();
		$last       = (string) get_option( WSErgo_District_Bridge::OPTION_LAST_UPDATE, '—' );
		$redirect   = admin_url( 'admin.php?page=wsergo-settings#ergo-territory' );
		$post_url   = admin_url( 'admin-post.php' );
		$dist_slug  = WSErgo_District_Bridge::district_post_type();
		$district_n = (int) wp_count_posts( $dist_slug )->publish;
		?>
		<div class="wsergo-territory-toolbar postbox" style="margin: 0 0 20px;">
			<div class="inside" style="padding: 14px 18px;">
				<p style="margin: 0 0 12px;">
					<strong><?php esc_html_e( 'Нейро-эргономика районов', 'worldstat-ergonomics' ); ?></strong>
					—
					<?php if ( $active ) : ?>
						<span style="color:#10b981;"><?php esc_html_e( 'включена', 'worldstat-ergonomics' ); ?></span>
					<?php else : ?>
						<span style="color:#b45309;"><?php esc_html_e( 'выключена', 'worldstat-ergonomics' ); ?></span>
					<?php endif; ?>
					<span class="description" style="margin-left:8px;">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: district count, 2: last update */
								__( 'Районов: %1$d · последний пересчёт: %2$s', 'worldstat-ergonomics' ),
								$district_n,
								$last
							)
						);
						?>
					</span>
				</p>
				<p style="margin: 0; display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
					<form method="post" action="<?php echo esc_url( $post_url ); ?>" style="display:inline;">
						<?php wp_nonce_field( 'wsergo_toggle' ); ?>
						<input type="hidden" name="action" value="wsergo_toggle_settings" />
						<input type="hidden" name="ergo_enabled" value="1" />
						<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect ); ?>" />
						<button type="submit" class="button" <?php disabled( $active ); ?>><?php esc_html_e( 'Включить', 'worldstat-ergonomics' ); ?></button>
					</form>
					<form method="post" action="<?php echo esc_url( $post_url ); ?>" style="display:inline;">
						<?php wp_nonce_field( 'wsergo_toggle' ); ?>
						<input type="hidden" name="action" value="wsergo_toggle_settings" />
						<input type="hidden" name="ergo_enabled" value="0" />
						<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect ); ?>" />
						<button type="submit" class="button" <?php disabled( ! $active ); ?>><?php esc_html_e( 'Выключить', 'worldstat-ergonomics' ); ?></button>
					</form>
					<form method="post" action="<?php echo esc_url( $post_url ); ?>" style="display:inline;" onsubmit="return confirm('<?php echo esc_js( __( 'Пересчитать все районы?', 'worldstat-ergonomics' ) ); ?>');">
						<?php wp_nonce_field( 'wsergo_toggle' ); ?>
						<input type="hidden" name="action" value="wsergo_toggle_settings" />
						<input type="hidden" name="recalc_ergo" value="1" />
						<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect ); ?>" />
						<button type="submit" class="button button-primary" <?php disabled( ! $active ); ?>><?php esc_html_e( 'Пересчитать все районы', 'worldstat-ergonomics' ); ?></button>
					</form>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=worldstat-districts' ) ); ?>"><?php esc_html_e( 'Импорт районов (Districts)', 'worldstat-ergonomics' ); ?></a>
				</p>
			</div>
		</div>
		<?php
	}

	public static function ajax_save_criteria_weights(): void {
		check_ajax_referer( 'wsergo_settings_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Access denied' );
		}
		$raw = isset( $_POST['weights'] ) ? wp_unslash( $_POST['weights'] ) : [];
		if ( ! is_array( $raw ) ) {
			wp_send_json_error( 'Invalid weights' );
		}
		$out = [];
		foreach ( $raw as $key => $val ) {
			$k = sanitize_key( (string) $key );
			if ( $k === '' ) {
				continue;
			}
			$out[ $k ] = max( 0.0, (float) $val );
		}
		update_option( 'wsergo_district_criteria_weights', $out );
		wp_send_json_success( [ 'saved' => true ] );
	}

	public static function ajax_retrain_all(): void {
		check_ajax_referer( 'wsergo_settings_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Access denied' );
		}
		if ( ! class_exists( 'WSErgo_District_Bridge' ) ) {
			wp_send_json_error( 'Bridge missing' );
		}
		$n = WSErgo_District_Bridge::update_all_districts();
		wp_send_json_success(
			[
				'message' => sprintf(
					/* translators: %d: number of districts */
					__( 'Пересчитано районов: %d', 'worldstat-ergonomics' ),
					$n
				),
			]
		);
	}

	public static function register_metaboxes(): void {
		if ( ! class_exists( 'WSDistricts_CPT' ) || ! WSErgo_District_Bridge::is_neural_active() ) {
			return;
		}
		add_meta_box(
			'wsdistrict_ergonomics',
			'🧠 ' . __( 'Нейро-эргономика района', 'worldstat-ergonomics' ),
			[ __CLASS__, 'render_ergo_metabox' ],
			WSDistricts_CPT::SLUG,
			'side',
			'high'
		);
	}

	/**
	 * @param WP_Post $post
	 */
	public static function render_ergo_metabox( $post ): void {
		$comfort       = (float) get_post_meta( $post->ID, 'wsdistrict_comfort_score', true );
		$safety        = (float) get_post_meta( $post->ID, 'wsdistrict_safety_score', true );
		$functionality = (float) get_post_meta( $post->ID, 'wsdistrict_functionality_score', true );
		$walkability   = (float) get_post_meta( $post->ID, 'wsdistrict_walkability_score', true );
		$air_class     = get_post_meta( $post->ID, 'wsdistrict_air_quality_class', true );
		$crime_level   = get_post_meta( $post->ID, 'wsdistrict_crime_level', true );

		$air_color = '#6b7280';
		$air_text  = __( 'Нет данных', 'worldstat-ergonomics' );
		if ( $air_class === 'Good' ) {
			$air_color = '#10b981';
			$air_text  = __( 'Хорошее', 'worldstat-ergonomics' );
		} elseif ( $air_class === 'Moderate' ) {
			$air_color = '#f59e0b';
			$air_text  = __( 'Среднее', 'worldstat-ergonomics' );
		} elseif ( $air_class === 'Poor' ) {
			$air_color = '#ef4444';
			$air_text  = __( 'Плохое', 'worldstat-ergonomics' );
		}

		$crime_color = '#6b7280';
		$crime_text  = __( 'Нет данных', 'worldstat-ergonomics' );
		if ( $crime_level === 'Low' ) {
			$crime_color = '#10b981';
			$crime_text  = __( 'Низкий', 'worldstat-ergonomics' );
		} elseif ( $crime_level === 'Medium' ) {
			$crime_color = '#f59e0b';
			$crime_text  = __( 'Средний', 'worldstat-ergonomics' );
		} elseif ( $crime_level === 'High' ) {
			$crime_color = '#ef4444';
			$crime_text  = __( 'Высокий', 'worldstat-ergonomics' );
		}
		?>
		<div style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); padding: 15px; border-radius: 8px;">
			<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
				<div style="text-align: center;">
					<div style="font-size: 11px; color: #666;"><?php esc_html_e( 'Качество воздуха', 'worldstat-ergonomics' ); ?></div>
					<div style="font-size: 18px; font-weight: bold; color: <?php echo esc_attr( $air_color ); ?>;"><?php echo esc_html( $air_text ); ?></div>
				</div>
				<div style="text-align: center;">
					<div style="font-size: 11px; color: #666;"><?php esc_html_e( 'Преступность', 'worldstat-ergonomics' ); ?></div>
					<div style="font-size: 18px; font-weight: bold; color: <?php echo esc_attr( $crime_color ); ?>;"><?php echo esc_html( $crime_text ); ?></div>
				</div>
			</div>
			<?php
			self::render_bar( __( 'Комфортность', 'worldstat-ergonomics' ), $comfort, '#10b981' );
			self::render_bar( __( 'Безопасность', 'worldstat-ergonomics' ), $safety, '#8b5cf6' );
			self::render_bar( __( 'Функциональность', 'worldstat-ergonomics' ), $functionality, '#f59e0b' );
			?>
			<div style="margin-top: 12px; padding-top: 10px; border-top: 1px solid rgba(0,0,0,0.1);">
				<div style="font-size: 11px; color: #666;"><?php esc_html_e( 'Пешеходная доступность', 'worldstat-ergonomics' ); ?></div>
				<div style="font-size: 16px; font-weight: bold; color: #8b5cf6;"><?php echo esc_html( (string) round( $walkability ) ); ?>/100</div>
			</div>
			<div style="margin-top: 10px; font-size: 10px; color: #666; text-align: center;">
				<?php esc_html_e( 'Модель территории (WorldStat Ergonomics)', 'worldstat-ergonomics' ); ?>
			</div>
		</div>
		<?php
		$ergo = WSErgo_District_Bridge::get_ergonomics_index( $post->ID );
		if ( (float) $ergo['score'] > 0 ) {
			$color = esc_attr( (string) $ergo['color'] );
			?>
			<div style="margin-top: 12px; padding: 12px; background: <?php echo $color; ?>10; border-radius: 8px; border-left: 4px solid <?php echo $color; ?>;">
				<strong style="color: <?php echo $color; ?>;"><?php esc_html_e( 'Сводный индекс', 'worldstat-ergonomics' ); ?>:</strong>
				<?php echo esc_html( (string) $ergo['score'] ); ?> — <?php echo esc_html( (string) $ergo['level'] ); ?>
			</div>
			<?php
		}
	}

	private static function render_bar( string $label, float $value, string $bar_color ): void {
		$value = max( 0, min( 100, $value ) );
		?>
		<div style="margin-bottom: 10px;">
			<div style="font-size: 11px; color: #666;"><?php echo esc_html( $label ); ?></div>
			<div style="height: 6px; background: #e5e7eb; border-radius: 3px; margin-top: 4px;">
				<div style="width: <?php echo esc_attr( (string) $value ); ?>%; height: 100%; background: <?php echo esc_attr( $bar_color ); ?>; border-radius: 3px;"></div>
			</div>
			<div style="text-align: right; font-size: 12px; font-weight: bold;"><?php echo esc_html( (string) round( $value ) ); ?>/100</div>
		</div>
		<?php
	}

}
