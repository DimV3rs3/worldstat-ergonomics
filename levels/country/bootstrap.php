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
add_action( 'wp_enqueue_scripts', 'wsergo_enqueue_country_tab_link_script', 25 );

/**
 * Скрипт перехода на вкладку «Сравнение» (#compare).
 */
function wsergo_enqueue_country_tab_link_script(): void {
	if ( ! class_exists( 'WorldStat_Country_CPT' ) || ! is_singular( WorldStat_Country_CPT::SLUG ) ) {
		return;
	}
	if ( ! class_exists( 'WSErgo_Level_Registry' ) ) {
		return;
	}
	wp_enqueue_script(
		'wsergo-country-tab-link',
		WSErgo_Level_Registry::url( 'country', 'public/assets/js/wsergo-country-tab-link.js' ),
		array( 'jquery' ),
		WSErgo_Level_Registry::asset_version( 'country', 'public/assets/js/wsergo-country-tab-link.js' ),
		true
	);
}
add_action( 'admin_enqueue_scripts', [ 'WSErgo_Country_Admin', 'enqueue_settings_assets' ], 20 );

add_action( 'wp_ajax_wsergo_load_country_city_explorer', [ 'WSErgo_Country_Renderer', 'ajax_load_country_city_explorer' ] );
add_action( 'wp_ajax_nopriv_wsergo_load_country_city_explorer', [ 'WSErgo_Country_Renderer', 'ajax_load_country_city_explorer' ] );
add_action( 'wp_ajax_wsergo_country_compare_trends', [ 'WSErgo_Country_Compare_Trends', 'ajax_run' ] );
add_action( 'wp_ajax_nopriv_wsergo_country_compare_trends', [ 'WSErgo_Country_Compare_Trends', 'ajax_run' ] );
add_action( 'wp_ajax_wsergo_country_classification_analysis', 'wsergo_country_ajax_classification_analysis' );
add_action( 'wp_ajax_nopriv_wsergo_country_classification_analysis', 'wsergo_country_ajax_classification_analysis' );

/**
 * AJAX: аналитические выводы по классификации для выбранных стран.
 */
function wsergo_country_ajax_classification_analysis(): void {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( (string) wp_unslash( $_POST['nonce'] ), 'wsergo_country_compare' ) ) {
		wp_send_json_error( array( 'message' => __( 'Ошибка безопасности.', 'worldstat-ergonomics' ) ) );
	}
	$iso2_raw = isset( $_POST['iso2'] ) ? wp_unslash( $_POST['iso2'] ) : array();
	$iso2_list = is_array( $iso2_raw ) ? $iso2_raw : array( $iso2_raw );
	if ( ! class_exists( 'WSErgo_Tier_Classifier' ) ) {
		wp_send_json_error( array( 'message' => __( 'Классификатор недоступен.', 'worldstat-ergonomics' ) ) );
	}
	wp_send_json_success(
		WSErgo_Tier_Classifier::build_compare_classification_analysis( $iso2_list )
	);
}
add_action( 'wp_ajax_wsergo_load_country_city_macro', [ 'WSErgo_Country_Renderer', 'ajax_load_country_city_macro' ] );
add_action( 'wp_ajax_nopriv_wsergo_load_country_city_macro', [ 'WSErgo_Country_Renderer', 'ajax_load_country_city_macro' ] );

add_action( 'wp_ajax_wsergo_auto_tune_macro_clusters', 'wsergo_country_ajax_auto_tune_macro_clusters' );
add_action( 'wp_ajax_wsergo_load_cluster_features_ui', 'wsergo_country_ajax_load_cluster_features_ui' );
add_action( 'wp_ajax_wsergo_cluster_preview', 'wsergo_country_ajax_cluster_preview' );
add_action( 'wp_ajax_wsergo_macro_reference_examples', 'wsergo_country_ajax_macro_reference_examples' );

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
			error_log( 'wsergo_auto_tune_macro_clusters: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
		}
		wsergo_country_discard_ajax_output_buffer();
		$msg = __( 'Ошибка при автоподборе. Проверьте debug.log на сервере.', 'worldstat-ergonomics' );
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ) {
			$msg .= ' (' . $e->getMessage() . ')';
		}
		wp_send_json_error(
			[
				'message' => $msg,
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
/**
 * Табы кластеров + прокручиваемый список стран.
 *
 * @param list<array{cluster:int,count:int,profile?:string,insight?:string,countries:list<array{iso2:string,name:string}>}> $clusters
 * @param list<string>                                                                                                 $colors
 */
function wsergo_render_cluster_tabs_block( array $clusters, array $colors, string $block_id ): void {
	if ( empty( $clusters ) ) {
		return;
	}
	$first = (int) ( $clusters[0]['cluster'] ?? 1 );
	?>
	<div class="wsergo-cluster-tabs" id="<?php echo esc_attr( $block_id ); ?>" data-wsergo-cluster-tabs="1">
		<div class="wsergo-cluster-tabs__bar" role="tablist">
			<?php foreach ( $clusters as $idx => $cl ) : ?>
				<?php
				$cl_num  = (int) ( $cl['cluster'] ?? 0 );
				$cnt     = (int) ( $cl['count'] ?? 0 );
				$color   = (string) ( $colors[ $idx ] ?? '#2271b1' );
				$active  = ( 0 === $idx ) ? ' is-active' : '';
				?>
				<button type="button" class="wsergo-cluster-tabs__tab<?php echo esc_attr( $active ); ?>" role="tab"
					aria-selected="<?php echo 0 === $idx ? 'true' : 'false'; ?>"
					data-cluster="<?php echo esc_attr( (string) $cl_num ); ?>"
					style="--wsergo-tab-color:<?php echo esc_attr( $color ); ?>">
					<span class="wsergo-cluster-tabs__tab-dot" aria-hidden="true"></span>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: cluster number, 2: country count */
							__( 'Кластер %1$d · %2$d', 'worldstat-ergonomics' ),
							$cl_num,
							$cnt
						)
					);
					?>
				</button>
			<?php endforeach; ?>
		</div>
		<?php foreach ( $clusters as $idx => $cl ) : ?>
			<?php
			$cl_num  = (int) ( $cl['cluster'] ?? 0 );
			$profile = (string) ( $cl['profile'] ?? '' );
			$insight = (string) ( $cl['insight'] ?? '' );
			$list    = isset( $cl['countries'] ) && is_array( $cl['countries'] ) ? $cl['countries'] : array();
			$hidden  = ( 0 !== $idx ) ? ' hidden' : '';
			?>
			<div class="wsergo-cluster-tabs__panel<?php echo esc_attr( $hidden ); ?>" role="tabpanel" data-cluster-panel="<?php echo esc_attr( (string) $cl_num ); ?>">
				<?php if ( $profile !== '' ) : ?>
					<p class="wsergo-cluster-tabs__profile"><?php echo esc_html( $profile ); ?></p>
				<?php endif; ?>
				<?php if ( $insight !== '' ) : ?>
					<p class="wsergo-cluster-tabs__insight"><?php echo esc_html( $insight ); ?></p>
				<?php endif; ?>
				<div class="wsergo-cluster-tabs__scroll">
					<ul class="wsergo-cluster-tabs__countries">
						<?php foreach ( $list as $c ) : ?>
							<li>
								<span class="wsergo-cluster-tabs__country-name"><?php echo esc_html( (string) ( $c['name'] ?? '' ) ); ?></span>
								<?php if ( ! empty( $c['iso2'] ) ) : ?>
									<code><?php echo esc_html( (string) $c['iso2'] ); ?></code>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
	<script>
	(function () {
		var root = document.getElementById(<?php echo wp_json_encode( $block_id ); ?>);
		if (!root || root.getAttribute('data-wsergo-tabs-bound') === '1') {
			return;
		}
		root.setAttribute('data-wsergo-tabs-bound', '1');
		function showCluster(num) {
			root.querySelectorAll('.wsergo-cluster-tabs__tab').forEach(function (btn) {
				var on = btn.getAttribute('data-cluster') === String(num);
				btn.classList.toggle('is-active', on);
				btn.setAttribute('aria-selected', on ? 'true' : 'false');
			});
			root.querySelectorAll('[data-cluster-panel]').forEach(function (panel) {
				panel.classList.toggle('hidden', panel.getAttribute('data-cluster-panel') !== String(num));
			});
		}
		root.querySelectorAll('.wsergo-cluster-tabs__tab').forEach(function (btn) {
			btn.addEventListener('click', function () {
				showCluster(btn.getAttribute('data-cluster'));
			});
		});
		showCluster(<?php echo (int) $first; ?>);
	})();
	</script>
	<?php
}

/**
 * Сохранённая сводка кластеров (настройки), только админка.
 *
 * @param string $scope country|city
 */
function wsergo_render_admin_cluster_summary( string $scope ): void {
	$scope = ( 'city' === $scope ) ? 'city' : 'country';
	if ( ! class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
		return;
	}
	$summary = WSErgo_Country_Macro_Calculator::get_cluster_assignment_summary( $scope );
	if ( empty( $summary['ok'] ) || empty( $summary['clusters'] ) ) {
		echo '<p class="description wsergo-cluster-admin-empty">';
		esc_html_e( 'Сводка кластеров: недостаточно данных CSV или не выбраны признаки k-means (нужно ≥2).', 'worldstat-ergonomics' );
		echo '</p>';
		return;
	}
	$feat_labels = isset( $summary['feature_labels'] ) && is_array( $summary['feature_labels'] ) ? $summary['feature_labels'] : array();
	$feat_str    = implode( ', ', $feat_labels );
	$k           = (int) ( $summary['k'] ?? 0 );
	$uid         = 'wsergo-cluster-admin-' . $scope;
	$clusters    = $summary['clusters'];
	$insights    = isset( $summary['insights'] ) && is_array( $summary['insights'] ) ? $summary['insights'] : array();
	$palette     = array( '#2271b1', '#00a32a', '#dba617', '#d63638', '#8c4bff', '#135e96', '#b32d2e', '#3582c4' );
	?>
	<div class="wsergo-cluster-admin" id="<?php echo esc_attr( $uid ); ?>">
		<header class="wsergo-cluster-admin__header">
			<div class="wsergo-cluster-admin__header-main">
				<h4 class="wsergo-cluster-admin__title"><?php esc_html_e( 'Кластеризация по CSV', 'worldstat-ergonomics' ); ?></h4>
				<p class="wsergo-cluster-admin__meta">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: feature labels, 2: k */
							__( 'Признаки: %1$s · k = %2$d', 'worldstat-ergonomics' ),
							$feat_str !== '' ? $feat_str : '—',
							$k
						)
					);
					?>
				</p>
			</div>
			<span class="wsergo-cluster-admin__badge"><?php esc_html_e( 'Справочно', 'worldstat-ergonomics' ); ?></span>
		</header>

		<?php if ( ! empty( $insights ) ) : ?>
			<section class="wsergo-cluster-admin__conclusions" aria-label="<?php esc_attr_e( 'Выводы по кластеризации', 'worldstat-ergonomics' ); ?>">
				<h5 class="wsergo-cluster-admin__conclusions-title"><?php esc_html_e( 'Выводы', 'worldstat-ergonomics' ); ?></h5>
				<ul class="wsergo-cluster-admin__conclusions-list">
					<?php foreach ( $insights as $line ) : ?>
						<li><?php echo esc_html( (string) $line ); ?></li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>

		<?php
		$tab_colors = array();
		foreach ( $clusters as $idx => $cl ) {
			$tab_colors[] = $palette[ $idx % count( $palette ) ];
		}
		wsergo_render_cluster_tabs_block( $clusters, $tab_colors, $uid . '-tabs' );
		?>
	</div>
	<?php
}

/**
 * Нижний ряд: таблица стран по кластерам (табы) и блок аналитики (обновляется из превью AJAX).
 *
 * @param string $scope country|city
 */
function wsergo_render_cluster_kmeans_bottom_row( string $scope ): void {
	$scope = ( 'city' === $scope ) ? 'city' : 'country';
	?>
	<div class="wsergo-cluster-kmeans-bottom" data-scope="<?php echo esc_attr( $scope ); ?>">
		<div class="wsergo-cluster-kmeans-panel wsergo-cluster-kmeans-countries" data-scope="<?php echo esc_attr( $scope ); ?>">
			<h4 class="wsergo-cluster-kmeans-panel__title"><?php esc_html_e( 'Страны по кластерам', 'worldstat-ergonomics' ); ?></h4>
			<div id="wsergo-cluster-countries-<?php echo esc_attr( $scope ); ?>" class="wsergo-cluster-kmeans-panel__body" aria-live="polite">
				<p class="wsergo-cluster-panel-empty description"><?php esc_html_e( 'Отметьте не меньше двух признаков — список обновится автоматически.', 'worldstat-ergonomics' ); ?></p>
			</div>
		</div>
		<div class="wsergo-cluster-kmeans-panel wsergo-cluster-kmeans-analysis" data-scope="<?php echo esc_attr( $scope ); ?>">
			<h4 class="wsergo-cluster-kmeans-panel__title"><?php esc_html_e( 'Аналитика кластеризации', 'worldstat-ergonomics' ); ?></h4>
			<div id="wsergo-cluster-analysis-<?php echo esc_attr( $scope ); ?>" class="wsergo-cluster-kmeans-panel__body" aria-live="polite">
				<p class="wsergo-cluster-panel-empty description"><?php esc_html_e( 'Выводы появятся после расчёта кластеров.', 'worldstat-ergonomics' ); ?></p>
			</div>
		</div>
	</div>
	<?php
}

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
		</div>
		<div class="wsergo-cluster-viz-legend"></div>
		<p class="wsergo-cluster-viz-status description"><?php esc_html_e( 'Отметьте не меньше двух признаков — превью обновится автоматически.', 'worldstat-ergonomics' ); ?></p>
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

/**
 * AJAX: примеры сырых признаков для эталонной страны и года (вкладка «Формула»).
 */
function wsergo_country_ajax_macro_reference_examples(): void {
	check_ajax_referer( 'wsergo_admin', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wsergo_country_discard_ajax_output_buffer();
		wp_send_json_error( [ 'message' => __( 'Недостаточно прав.', 'worldstat-ergonomics' ) ] );
	}
	if ( ! class_exists( 'WSErgo_Country_Macro_Calculator' ) || ! class_exists( 'WSErgo_Country_Admin' ) ) {
		wsergo_country_discard_ajax_output_buffer();
		wp_send_json_error( [ 'message' => __( 'Модуль макроданных недоступен.', 'worldstat-ergonomics' ) ] );
	}

	$post_id = isset( $_POST['country_id'] ) ? (int) $_POST['country_id'] : 0;
	$year    = isset( $_POST['year'] ) ? (int) $_POST['year'] : 0;
	if ( $year < 1960 && class_exists( 'WSErgo_Settings' ) ) {
		$year = WSErgo_Settings::get_macro_reference_year();
	}

	$raw  = null;
	$note = '';
	if ( $post_id > 0 ) {
		$raw = WSErgo_Country_Macro_Calculator::raw_row_for_country_post_id( $post_id, $year );
		$iso2 = strtoupper( trim( (string) get_post_meta( $post_id, 'wsp_iso_alpha2', true ) ) );
		$tref = get_the_title( $post_id );
		if ( $tref !== '' || strlen( $iso2 ) === 2 ) {
			$note = ( $tref !== '' ? $tref : $iso2 );
			if ( strlen( $iso2 ) === 2 ) {
				$note .= ' (' . $iso2 . ', ' . (int) $year . ')';
			} else {
				$note .= ' (' . (int) $year . ')';
			}
		}
	}

	$signals = array();
	if ( is_array( $raw ) ) {
		foreach ( array_keys( $raw ) as $sig ) {
			$signals[ (string) $sig ] = WSErgo_Country_Admin::format_macro_signal_example_display( $raw, (string) $sig );
		}
	}
	if ( class_exists( 'WSErgo_Country_Macro_Calculator' ) ) {
		foreach ( WSErgo_Country_Macro_Calculator::macro_cluster_signal_allowlist( 'country' ) as $sig ) {
			$sig = (string) $sig;
			if ( ! isset( $signals[ $sig ] ) ) {
				$signals[ $sig ] = WSErgo_Country_Admin::format_macro_signal_example_display( $raw, $sig );
			}
		}
	}

	wsergo_country_discard_ajax_output_buffer();
	wp_send_json_success(
		[
			'note'    => $note,
			'year'    => (int) $year,
			'has_row' => is_array( $raw ),
			'values'  => $signals,
		]
	);
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
