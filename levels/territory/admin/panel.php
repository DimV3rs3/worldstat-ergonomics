<?php
/**
 * Панель настроек «Эргономичность территории» (подключается из core/class-ergo-admin.php).
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div id="wsergo-panel-territory" class="wsergo-scope-panel">
	<?php
	if ( class_exists( 'WSErgo_District_Tab' ) && method_exists( 'WSErgo_District_Tab', 'render_panel_inner' ) ) {
		WSErgo_District_Tab::render_panel_inner();
	} else {
		?>
		<div class="notice notice-info inline" style="margin:12px 0;padding:12px;">
			<p style="margin:.4em 0;"><?php esc_html_e( 'Модуль территории недоступен.', 'worldstat-ergonomics' ); ?></p>
		</div>
		<?php
	}
	?>
</div>
