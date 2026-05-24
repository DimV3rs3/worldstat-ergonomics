<?php
/**
 * Хуки уровня «страна».
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', [ 'WSErgo_Country_Renderer', 'enqueue_public_assets' ], 20 );
add_action( 'admin_enqueue_scripts', [ 'WSErgo_Country_Admin', 'enqueue_settings_assets' ], 20 );

add_action( 'wp_ajax_wsergo_load_country_city_explorer', [ 'WSErgo_Country_Renderer', 'ajax_load_country_city_explorer' ] );
add_action( 'wp_ajax_nopriv_wsergo_load_country_city_explorer', [ 'WSErgo_Country_Renderer', 'ajax_load_country_city_explorer' ] );
add_action( 'wp_ajax_wsergo_load_country_city_macro', [ 'WSErgo_Country_Renderer', 'ajax_load_country_city_macro' ] );
add_action( 'wp_ajax_nopriv_wsergo_load_country_city_macro', [ 'WSErgo_Country_Renderer', 'ajax_load_country_city_macro' ] );

add_action( 'wp_ajax_wsergo_auto_tune_macro_clusters', 'wsergo_country_ajax_auto_tune_macro_clusters' );
add_action( 'wp_ajax_wsergo_load_cluster_features_ui', 'wsergo_country_ajax_load_cluster_features_ui' );
add_action( 'wp_ajax_wsergo_cluster_preview', 'wsergo_country_ajax_cluster_preview' );

/**
 * AJAX: автоподбор признаков k-means и числа кластеров.
 */
/**
 * Сброс буфера вывода перед JSON (BOM/notice ломают ответ admin-ajax).
 */
function wsergo_country_discard_ajax_output_buffer(): void {
	while ( ob_get_level() > 0 ) {
		ob_end_clean();
	}
}

function wsergo_country_ajax_auto_tune_macro_clusters(): void {
	check_ajax_referer( 'wsergo_admin', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wsergo_country_discard_ajax_output_buffer();
		wp_send_json_error( [ 'message' => __( 'Недостаточно прав.', 'worldstat-ergonomics' ) ] );
	}
	if ( ! class_exists( 'WSErgo_Macro_Cluster_Optimizer' ) || ! class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
		wsergo_country_discard_ajax_output_buffer();
		wp_send_json_error( [ 'message' => __( 'Модуль автоподбора недоступен. Проверьте, что на сервере загружены levels/country/.', 'worldstat-ergonomics' ) ] );
	}
	$scope = isset( $_POST['scope'] ) && (string) wp_unslash( $_POST['scope'] ) === 'city' ? 'city' : 'country';
	$apply = ! empty( $_POST['apply'] );
	try {
		$result = WSErgo_Macro_Cluster_Optimizer::auto_tune( $scope );
	} catch ( Throwable $e ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'wsergo_auto_tune_macro_clusters: ' . $e->getMessage() );
		}
		wsergo_country_discard_ajax_output_buffer();
		wp_send_json_error(
			[
				'message' => __( 'Ошибка при автоподборе. Проверьте debug.log на сервере.', 'worldstat-ergonomics' ),
			]
		);
	}
	if ( empty( $result['ok'] ) ) {
		wsergo_country_discard_ajax_output_buffer();
		wp_send_json_error( [ 'message' => (string) ( $result['message'] ?? __( 'Ошибка подбора.', 'worldstat-ergonomics' ) ) ] );
	}
	if ( $apply && ! empty( $result['features'] ) && isset( $result['k'] ) ) {
		WSErgo_Macro_Cluster_Optimizer::apply_to_options( (array) $result['features'], (int) $result['k'], $scope );
		$result['saved'] = true;
	}
	wsergo_country_discard_ajax_output_buffer();
	wp_send_json_success( $result );
}

/**
 * Разметка чекбоксов k-means (тот же список, что в матрице критериев).
 *
 * @param string       $scope              country|city
 * @param list<string> $signals_ui
 * @param list<string> $cf_for_checkboxes
 * @param string       $opt_name
 */
function wsergo_render_cluster_features_checkboxes( string $scope, array $signals_ui, array $cf_for_checkboxes, string $opt_name ): void {
	echo '<div class="wsergo-cluster-features-list" data-scope="' . esc_attr( $scope ) . '">';
	if ( empty( $signals_ui ) ) {
		echo '<p class="description">';
		esc_html_e( 'Нет параметров из CSV. Загрузите наборы в World Statistics (раздел «Данные CSV») или добавьте пользовательские параметры выше — они появятся и в матрице критериев, и здесь.', 'worldstat-ergonomics' );
		echo '</p>';
	} else {
		foreach ( $signals_ui as $sig ) {
			$checked = in_array( $sig, $cf_for_checkboxes, true );
			echo '<label style="display:block;margin:.35em 0;">';
			echo '<input type="checkbox" name="' . esc_attr( $opt_name ) . '[]" value="' . esc_attr( $sig ) . '"';
			echo $checked ? ' checked="checked"' : '';
			echo ' /> ';
			echo '<strong>' . esc_html( class_exists( 'WSErgo_Country_Macro_Calculator' ) ? WSErgo_Country_Macro_Calculator::data_label_ru( $sig ) : $sig ) . '</strong>';
			echo ' <code style="margin-left:6px;">' . esc_html( $sig ) . '</code>';
			echo '</label>';
		}
	}
	echo '</div>';
}

/**
 * AJAX: чекбоксы признаков k-means (обновление списка после добавления параметра).
 */
function wsergo_country_ajax_load_cluster_features_ui(): void {
	check_ajax_referer( 'wsergo_admin', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => __( 'Недостаточно прав.', 'worldstat-ergonomics' ) ] );
	}
	if ( ! class_exists( 'WSErgo_Country_Macro_Calculator' ) || ! class_exists( 'WSErgo_Settings' ) ) {
		wsergo_country_discard_ajax_output_buffer();
		wp_send_json_error( [ 'message' => __( 'Модуль макроданных недоступен. Проверьте, что на сервере загружены levels/country/.', 'worldstat-ergonomics' ) ] );
	}
	$scope     = isset( $_POST['scope'] ) && (string) wp_unslash( $_POST['scope'] ) === 'city' ? 'city' : 'country';
	$opt_name  = ( 'city' === $scope )
		? WSErgo_Settings::OPTION_CITY_MACRO_CLUSTER_FEATURES
		: WSErgo_Settings::OPTION_MACRO_CLUSTER_FEATURES;
	$stored_cf = ( 'city' === $scope )
		? WSErgo_Settings::get_city_macro_cluster_features()
		: WSErgo_Settings::get_macro_cluster_features();
	$cf_for_checkboxes = count( $stored_cf ) >= 2 ? $stored_cf : [];
	$signals_ui        = WSErgo_Country_Macro_Calculator::macro_cluster_signal_allowlist( $scope );

	ob_start();
	wsergo_render_cluster_features_checkboxes( $scope, $signals_ui, $cf_for_checkboxes, $opt_name );
	$html = ob_get_clean();
	wsergo_country_discard_ajax_output_buffer();
	wp_send_json_success( [ 'html' => $html ] );
}

/**
 * Блок превью кластеризации (карта / график) рядом со списком признаков.
 *
 * @param string $scope country|city
 */
function wsergo_render_cluster_viz_host( string $scope ): void {
	$scope = ( 'city' === $scope ) ? 'city' : 'country';
	?>
	<div id="wsergo-cluster-viz-<?php echo esc_attr( $scope ); ?>" class="wsergo-cluster-viz-host" data-scope="<?php echo esc_attr( $scope ); ?>">
		<div class="wsergo-cluster-viz-toolbar">
			<strong><?php esc_html_e( 'Превью кластеризации', 'worldstat-ergonomics' ); ?></strong>
			<button type="button" class="button button-small wsergo-cluster-viz-mode is-active" data-mode="map"><?php esc_html_e( 'Карта', 'worldstat-ergonomics' ); ?></button>
			<button type="button" class="button button-small wsergo-cluster-viz-mode" data-mode="chart"><?php esc_html_e( 'График', 'worldstat-ergonomics' ); ?></button>
		</div>
		<div class="wsergo-cluster-viz-map-pane">
			<div class="wsergo-cluster-viz-map" role="img" aria-label="<?php esc_attr_e( 'Карта кластеров стран', 'worldstat-ergonomics' ); ?>"></div>
		</div>
		<div class="wsergo-cluster-viz-chart-pane" style="display:none;">
			<div class="wsergo-cluster-viz-chart-wrap">
				<canvas class="wsergo-cluster-viz-chart-doughnut" height="130"></canvas>
				<canvas class="wsergo-cluster-viz-chart-scatter" height="200"></canvas>
			</div>
			<div class="wsergo-cluster-viz-table-wrap"></div>
		</div>
		<div class="wsergo-cluster-viz-legend"></div>
		<p class="wsergo-cluster-viz-status description" style="margin:.5em 0 0;"><?php esc_html_e( 'Отметьте не меньше двух признаков — превью обновится автоматически.', 'worldstat-ergonomics' ); ?></p>
	</div>
	<?php
}

/**
 * AJAX: превью k-means по текущим чекбоксам и k (без сохранения настроек).
 */
function wsergo_country_ajax_cluster_preview(): void {
	check_ajax_referer( 'wsergo_admin', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wsergo_country_discard_ajax_output_buffer();
		wp_send_json_error( [ 'message' => __( 'Недостаточно прав.', 'worldstat-ergonomics' ) ] );
	}
	if ( ! class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
		wsergo_country_discard_ajax_output_buffer();
		wp_send_json_error( [ 'message' => __( 'Калькулятор макроданных недоступен.', 'worldstat-ergonomics' ) ] );
	}
	$scope = isset( $_POST['scope'] ) && (string) wp_unslash( $_POST['scope'] ) === 'city' ? 'city' : 'country';
	$features = isset( $_POST['features'] ) && is_array( $_POST['features'] )
		? array_map( 'sanitize_key', wp_unslash( $_POST['features'] ) )
		: array();
	$k = isset( $_POST['k'] ) ? (int) $_POST['k'] : 0;
	if ( $k < 2 && class_exists( 'WSErgo_Settings' ) ) {
		$k = ( 'city' === $scope )
			? WSErgo_Settings::get_city_macro_k_clusters()
			: WSErgo_Settings::get_macro_k_clusters();
	}
	$result = WSErgo_Country_Macro_Calculator::preview_macro_clusters( $scope, $features, $k );
	wsergo_country_discard_ajax_output_buffer();
	if ( empty( $result['ok'] ) ) {
		wp_send_json_error( [ 'message' => (string) ( $result['message'] ?? __( 'Ошибка превью.', 'worldstat-ergonomics' ) ) ] );
	}
	wp_send_json_success( $result );
}

$wsergo_country_macro_flush_options = [
	WSErgo_Settings::OPTION_MACRO_REFERENCE_YEAR,
	WSErgo_Settings::OPTION_MACRO_K_CLUSTERS,
	WSErgo_Settings::OPTION_COUNTRY_INDEX_SOURCE,
	WSErgo_Settings::OPTION_MACRO_CSV_BINDINGS,
	WSErgo_Settings::OPTION_MACRO_E_AXIS_WEIGHTS,
	WSErgo_Settings::OPTION_MACRO_CLUSTER_FEATURES,
	WSErgo_Settings::OPTION_MACRO_AXIS_TERMS,
	WSErgo_Settings::OPTION_MACRO_CRITERIA_MATRIX,
	WSErgo_Settings::OPTION_MACRO_CRITERIA_WEIGHTS,
	WSErgo_Settings::OPTION_MACRO_CRITERIA_INVERTS,
];

foreach ( $wsergo_country_macro_flush_options as $wsergo_opt ) {
	add_action(
		'update_option_' . $wsergo_opt,
		static function () {
			if ( class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
				WSErgo_Country_Macro_Calculator::flush_cache();
			}
		}
	);
}

add_action(
	'update_option_wsp_csv_files_revision',
	static function () {
		if ( class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
			WSErgo_Country_Macro_Calculator::flush_cache();
		}
	},
	20
);
