<?php
/**
 * Панель настроек «Эргономичность территории» (подключается из core/class-ergo-admin.php).
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wsergo_scope_style = ! empty( $wsergo_scope_hidden ) ? ' style="display:none"' : '';
?>
<div id="wsergo-panel-territory" class="wsergo-scope-panel"<?php echo $wsergo_scope_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php
	if ( class_exists( 'WSErgo_Extension_Notices' ) && WSErgo_Extension_Notices::render_missing_extension_warning( 'territory' ) ) {
		// Расширение Districts не активно — только предупреждение.
	} elseif ( class_exists( 'WSErgo_District_Tab' ) && method_exists( 'WSErgo_District_Tab', 'render_panel_inner' ) ) {
		if ( class_exists( 'WSErgo_Territory_Admin' ) ) {
			WSErgo_Territory_Admin::render_scope_toolbar();
		}
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
