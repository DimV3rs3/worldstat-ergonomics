<?php
/**
 * Уведомления о переносе эргономики в плагин Ergonomics и проверка зависимостей расширений.
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_Extension_Notices {

	/** @var array<string, string> */
	private const SCOPE_LABELS = [
		'country'   => 'Страна',
		'city'      => 'Город',
		'territory' => 'Территория (районы)',
		'building'  => 'Здание (Courtyard / OSM)',
		'zone'      => 'Зона (помещения)',
	];

	/** @var array<string, string> */
	private const EXTENSION_PLUGINS = [
		'territory' => 'worldstat-districts/worldstat-districts.php',
		'zone'      => 'worldstat-zone/worldstat-zone.php',
		'building'  => 'worldstat-courtyard/worldstat-courtyard.php',
	];

	/** @var array<string, string> */
	private const EXTENSION_NAMES = [
		'territory' => 'WorldStat Districts',
		'zone'      => 'WorldStat Zones',
		'building'  => 'WorldStat Courtyard',
	];

	public static function settings_url( string $level_id ): string {
		$level_id = sanitize_key( $level_id );
		return admin_url( 'admin.php?page=wsergo-settings#ergo-' . rawurlencode( $level_id ) );
	}

	public static function scope_label( string $level_id ): string {
		$level_id = sanitize_key( $level_id );
		return self::SCOPE_LABELS[ $level_id ] ?? $level_id;
	}

	public static function is_extension_active( string $level_id ): bool {
		$level_id = sanitize_key( $level_id );
		if ( isset( self::EXTENSION_PLUGINS[ $level_id ] ) ) {
			return self::is_plugin_file_active( self::EXTENSION_PLUGINS[ $level_id ] );
		}
		switch ( $level_id ) {
			case 'territory':
				return class_exists( 'WSDistricts_CPT' );
			case 'zone':
				return class_exists( 'WSZ_CPT' );
			case 'building':
				return defined( 'WSC_VERSION' ) || class_exists( 'WSC_Writer' );
			default:
				return true;
		}
	}

	public static function is_plugin_file_active( string $plugin_file ): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( $plugin_file );
	}

	/**
	 * Плашка в расширении: эргономика перенесена в WorldStat Ergonomics.
	 *
	 * @param string $level_id       territory|zone|building
	 * @param string $extension_title Заголовок страницы расширения (опционально).
	 */
	public static function render_moved_notice( string $level_id, string $extension_title = '' ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$level_id   = sanitize_key( $level_id );
		$scope      = self::scope_label( $level_id );
		$ergo_url   = self::settings_url( $level_id );
		$ergo_page  = admin_url( 'admin.php?page=wsergo-settings' );
		$has_ergo   = class_exists( 'WSErgo_Admin_Shell' ) && self::is_plugin_file_active( 'worldstat-ergonomics/worldstat-ergonomics.php' );
		?>
		<div class="notice notice-info wsergo-extension-moved-notice" style="margin: 16px 0 24px; padding: 14px 18px; border-left-color: #2271b1;">
			<p style="margin: 0 0 6px; font-size: 14px;">
				<?php
				if ( $extension_title !== '' ) {
					echo esc_html( sprintf( '«%s»: ', $extension_title ) );
				}
				esc_html_e( 'Данная часть плагина была перенесена в', 'worldstat-ergonomics' );
				?>
				<?php if ( $has_ergo ) : ?>
					<a href="<?php echo esc_url( $ergo_url ); ?>"><strong><?php esc_html_e( 'Эргономичность', 'worldstat-ergonomics' ); ?></strong></a>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: scope name */
							__( ' (раздел «%s»).', 'worldstat-ergonomics' ),
							$scope
						)
					);
					?>
				<?php else : ?>
					<strong><?php esc_html_e( 'Эргономичность', 'worldstat-ergonomics' ); ?></strong>
					<span class="description">
						<?php esc_html_e( '(активируйте плагин WorldStat Ergonomics).', 'worldstat-ergonomics' ); ?>
					</span>
				<?php endif; ?>
			</p>
			<p class="description" style="margin: 0;">
				<?php esc_html_e( 'Импорт и доменные данные остаются в этом расширении; расчёт, настройки и пересчёт — в Ergonomics.', 'worldstat-ergonomics' ); ?>
				<?php if ( $has_ergo ) : ?>
					<a href="<?php echo esc_url( $ergo_page ); ?>"><?php esc_html_e( 'Открыть настройки эргономики', 'worldstat-ergonomics' ); ?></a>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}

	/**
	 * На вкладке Ergonomics: требуемое расширение не установлено или не активно.
	 */
	public static function render_missing_extension_warning( string $level_id ): bool {
		$level_id = sanitize_key( $level_id );
		if ( self::is_extension_active( $level_id ) ) {
			return false;
		}
		$name = self::EXTENSION_NAMES[ $level_id ] ?? $level_id;
		$file = self::EXTENSION_PLUGINS[ $level_id ] ?? '';
		?>
		<div class="notice notice-error inline" style="margin: 12px 0 20px; padding: 14px 18px;">
			<p style="margin: 0 0 8px;">
				<strong><?php esc_html_e( 'Требуемое расширение не активно', 'worldstat-ergonomics' ); ?></strong>
			</p>
			<p style="margin: 0;">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: plugin name, 2: scope */
						__( 'Для раздела «%2$s» нужен плагин %1$s. Установите и активируйте его в разделе «Плагины» — без этого настройки эргономики и данные wsdistrict_* / wsz_* / OSM недоступны.', 'worldstat-ergonomics' ),
						$name,
						self::scope_label( $level_id )
					)
				);
				?>
			</p>
			<?php if ( $file !== '' ) : ?>
				<p class="description" style="margin: 8px 0 0;"><code><?php echo esc_html( $file ); ?></code></p>
			<?php endif; ?>
		</div>
		<?php
		return true;
	}
}
