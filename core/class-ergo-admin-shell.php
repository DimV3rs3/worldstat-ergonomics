<?php
/**
 * Общая оболочка страницы настроек: вкладки уровней из levels/{id}/admin/panel.php.
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_Admin_Shell {

	public const PAGE_SLUG = 'wsergo-settings';

	/**
	 * Верхняя навигация по зарегистрированным уровням с admin/panel.php.
	 */
	public static function render_scope_nav(): void {
		if ( ! class_exists( 'WSErgo_Level_Registry' ) ) {
			return;
		}
		$levels = WSErgo_Level_Registry::ids_with_admin_panel();
		if ( empty( $levels ) ) {
			return;
		}
		?>
		<h2 class="nav-tab-wrapper wsergo-ergo-scope-nav" style="margin-bottom:4px;">
			<?php
			foreach ( $levels as $i => $level_id ) :
				$active = 0 === $i ? ' nav-tab-active' : '';
				$href   = '#ergo-' . esc_attr( $level_id );
				?>
				<a href="<?php echo esc_url( $href ); ?>" class="nav-tab<?php echo esc_attr( $active ); ?>" data-wsergo-scope="<?php echo esc_attr( $level_id ); ?>">
					<?php echo esc_html( WSErgo_Level_Registry::get_admin_nav_label( $level_id ) ); ?>
				</a>
			<?php endforeach; ?>
		</h2>
		<?php
	}

	/**
	 * Подключить панели всех уровней с admin/panel.php.
	 *
	 * @param array<string, mixed> $vars Переменные для extract в panel.php.
	 * @return bool true, если все панели загружены.
	 */
	public static function render_level_panels( array $vars = [] ): bool {
		if ( ! class_exists( 'WSErgo_Level_Registry' ) ) {
			return false;
		}
		$ok     = true;
		$levels = WSErgo_Level_Registry::ids_with_admin_panel();
		foreach ( $levels as $level_id ) {
			if ( ! WSErgo_Level_Registry::include_admin_panel( $level_id, $vars ) ) {
				$ok = false;
				?>
				<div class="notice notice-error inline" style="margin:12px 0;padding:12px;">
					<p style="margin:0;">
						<?php
						printf(
							/* translators: %s: level id */
							esc_html__( 'Не удалось загрузить панель уровня «%s» (файл admin/panel.php).', 'worldstat-ergonomics' ),
							esc_html( $level_id )
						);
						?>
					</p>
				</div>
				<?php
			}
		}
		if ( ! $ok && ! empty( $levels ) ) {
			echo '<p class="description">' . esc_html__( 'Проверьте, что плагин установлен полностью и каталог levels/ не повреждён.', 'worldstat-ergonomics' ) . '</p>';
		}
		return $ok;
	}
}
