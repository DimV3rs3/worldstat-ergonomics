<?php

/**

 * Панель настроек уровня «зона».

 *

 * @package WorldStatErgonomics

 */



if ( ! defined( 'ABSPATH' ) ) {

	exit;

}



$wsergo_scope_style = ! empty( $wsergo_scope_hidden ) ? ' style="display:none"' : '';
?>

<div id="wsergo-panel-zone" class="wsergo-scope-panel"<?php echo $wsergo_scope_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

	<?php if ( class_exists( 'WSErgo_Extension_Notices' ) && WSErgo_Extension_Notices::render_missing_extension_warning( 'zone' ) ) : ?>

	<?php elseif ( class_exists( 'WSErgo_Zone_Admin_Panel' ) ) : ?>

		<?php WSErgo_Zone_Admin_Panel::render(); ?>

	<?php else : ?>

		<div class="notice notice-warning inline"><p><?php esc_html_e( 'Модуль зоны недоступен.', 'worldstat-ergonomics' ); ?></p></div>

	<?php endif; ?>

</div>

