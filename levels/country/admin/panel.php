<?php
/**
 * Панель настроек «Эргономичность страны».
 * @package WorldStatErgonomics
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! isset( $wsergo_custom_metrics_saved ) || ! is_array( $wsergo_custom_metrics_saved ) ) {
	$wsergo_custom_metrics_saved = [];
}
if ( ! isset( $signals_ui ) || ! is_array( $signals_ui ) ) {
	$signals_ui = [];
}
if ( ! isset( $data_label_keys ) || ! is_array( $data_label_keys ) ) {
	$data_label_keys = [];
}
if ( ! isset( $wsergo_cm_ops ) || ! is_array( $wsergo_cm_ops ) ) {
	$wsergo_cm_ops = [];
}
$wsergo_scope_style = ! empty( $wsergo_scope_hidden ) ? ' style="display:none"' : '';
?>
			<div id="wsergo-panel-country" class="wsergo-scope-panel"<?php echo $wsergo_scope_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<form class="wsergo-settings-form" method="post" action="options.php">
					<?php settings_fields( 'wsergo_settings' ); ?>
				<div id="wsergo-cm-store" class="wsergo-cm-store" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
					<?php
					$wsergo_cmi_top = 0;
					foreach ( $wsergo_custom_metrics_saved as $cm_row ) :
						$cm_slug  = isset( $cm_row['slug'] ) ? (string) $cm_row['slug'] : '';
						$cm_op    = isset( $cm_row['op'] ) ? (string) $cm_row['op'] : 'div';
						$cm_ka    = isset( $cm_row['key_a'] ) ? (string) $cm_row['key_a'] : '';
						$cm_kb    = isset( $cm_row['key_b'] ) ? (string) $cm_row['key_b'] : '';
						$cm_const = isset( $cm_row['const'] ) ? (string) $cm_row['const'] : '';
						if ( $cm_slug === '' ) {
							continue;
						}
						?>
					<div class="wsergo-cm-row">
						<input type="hidden" name="<?php echo esc_attr( $wsergo_cm_opt ); ?>[<?php echo (int) $wsergo_cmi_top; ?>][slug]" value="<?php echo esc_attr( $cm_slug ); ?>" />
						<input type="hidden" name="<?php echo esc_attr( $wsergo_cm_opt ); ?>[<?php echo (int) $wsergo_cmi_top; ?>][op]" value="<?php echo esc_attr( $cm_op ); ?>" />
						<input type="hidden" name="<?php echo esc_attr( $wsergo_cm_opt ); ?>[<?php echo (int) $wsergo_cmi_top; ?>][key_a]" value="<?php echo esc_attr( $cm_ka ); ?>" />
						<input type="hidden" name="<?php echo esc_attr( $wsergo_cm_opt ); ?>[<?php echo (int) $wsergo_cmi_top; ?>][key_b]" value="<?php echo esc_attr( $cm_kb ); ?>" />
						<input type="hidden" name="<?php echo esc_attr( $wsergo_cm_opt ); ?>[<?php echo (int) $wsergo_cmi_top; ?>][const]" value="<?php echo esc_attr( $cm_const !== '' ? $cm_const : '0' ); ?>" />
					</div>
						<?php
						++$wsergo_cmi_top;
					endforeach;
					?>
				</div>

			<div class="notice notice-info inline" style="margin:12px 0;padding:12px;">
				<p style="margin:.4em 0;"><strong><?php esc_html_e( 'Вкладки', 'worldstat-ergonomics' ); ?></strong>
					— <a href="#tab-data" class="wsergo-tab-deep-link"><?php esc_html_e( 'перейти к «Данные»', 'worldstat-ergonomics' ); ?></a>
				</p>
				<ol style="margin:.5em 0 .5em 1.2em;list-style:decimal;padding-left:1em;">
					<li><?php esc_html_e( 'Данные — калькулятор пользовательских параметров (новые показатели из столбцов CSV), подписи к признакам, матрица отбора, версия методики, k-means, опорный год, эталонная страна для препросмотра на «Формула».', 'worldstat-ergonomics' ); ?></li>
					<li><?php esc_html_e( 'Формула — модель и DSL объекта вверху страницы; макро-критерии страны: веса и суммы. Если включена матрица на «Данные», состав параметров по критериям задаётся только там, а на «Формула» — веса слагаемых и вес критерия в E.', 'worldstat-ergonomics' ); ?></li>
				</ol>
			</div>

			<h2 class="nav-tab-wrapper wsergo-country-inner-nav" style="margin-top:8px;">
				<a href="#tab-data" class="nav-tab nav-tab-active" data-tab="tab-data"><?php esc_html_e( 'Данные', 'worldstat-ergonomics' ); ?></a>
				<a href="#tab-formula" class="nav-tab" data-tab="tab-formula"><?php esc_html_e( 'Формула', 'worldstat-ergonomics' ); ?></a>
			</h2>

				<div id="tab-data" class="wsergo-tab-panel wsergo-tab-panel-country">
					<h2><?php esc_html_e( 'Данные', 'worldstat-ergonomics' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Источники и параметры для странового индекса по макроданным CSV. Поддерживаются «длинные» файлы (country_code, year, value) и широкие (country_code, year и несколько числовых столбцов — demographics, urban_infra, environment и т.д.). Для базового треугольника населения/площади по-прежнему нужны ряды population_total и surface_area_sqkm либо совместимые long-CSV; из широких файлов подтягиваются плотность, доля городского населения, лес и прочие признаки.', 'worldstat-ergonomics' ); ?></p>
					<?php if ( defined( 'WSERGO_URL' ) ) : ?>
					<p class="description">
						<a href="<?php echo esc_url( WSERGO_URL . 'data/ergo-wide-csv-reference.txt' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Справка по столбцам широких CSV (открывается в новой вкладке)', 'worldstat-ergonomics' ); ?></a>
					</p>
					<?php endif; ?>

					<h3><?php esc_html_e( 'Калькулятор пользовательских параметров', 'worldstat-ergonomics' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Добавьте показатель из уже загруженных столбцов CSV или из производных модели. Итоговый ключ — латиница (snake_case). По кнопке «Добавить» правило сохраняется в базу сразу; остальные поля страницы — кнопкой «Сохранить настройки» внизу. На очень длинных формах PHP может ограничивать число полей (max_input_vars) — отдельное сохранение калькулятора это обходит.', 'worldstat-ergonomics' ); ?></p>
					<div class="wsergo-cm-panel" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;padding:12px;border:1px solid #c3c4c7;background:#fff;margin-bottom:10px;max-width:920px;border-radius:4px;">
						<div>
							<label for="wsergo-cm-key-a" class="screen-reader-text"><?php esc_html_e( 'Параметр A', 'worldstat-ergonomics' ); ?></label>
							<span class="description" style="display:block;margin-bottom:4px;"><?php esc_html_e( 'Параметр A', 'worldstat-ergonomics' ); ?></span>
							<input type="text" id="wsergo-cm-key-a" class="regular-text code" maxlength="96" autocomplete="off" placeholder="<?php esc_attr_e( 'ключ столбца', 'worldstat-ergonomics' ); ?>" />
						</div>
						<div>
							<label for="wsergo-cm-op" class="screen-reader-text"><?php esc_html_e( 'Операция', 'worldstat-ergonomics' ); ?></label>
							<span class="description" style="display:block;margin-bottom:4px;"><?php esc_html_e( 'Операция', 'worldstat-ergonomics' ); ?></span>
							<select id="wsergo-cm-op" style="min-width:11em;">
								<?php foreach ( $wsergo_cm_ops as $op_k => $op_lab ) : ?>
									<option value="<?php echo esc_attr( $op_k ); ?>"><?php echo esc_html( $op_lab ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div id="wsergo-cm-wrap-b">
							<label for="wsergo-cm-key-b" class="screen-reader-text"><?php esc_html_e( 'Параметр B', 'worldstat-ergonomics' ); ?></label>
							<span class="description" style="display:block;margin-bottom:4px;"><?php esc_html_e( 'Параметр B', 'worldstat-ergonomics' ); ?></span>
							<input type="text" id="wsergo-cm-key-b" class="regular-text code" maxlength="96" autocomplete="off" placeholder="<?php esc_attr_e( 'ключ столбца', 'worldstat-ergonomics' ); ?>" />
						</div>
						<div id="wsergo-cm-wrap-const" style="display:none;">
							<label for="wsergo-cm-const" class="screen-reader-text"><?php esc_html_e( 'Число', 'worldstat-ergonomics' ); ?></label>
							<span class="description" style="display:block;margin-bottom:4px;"><?php esc_html_e( 'Число', 'worldstat-ergonomics' ); ?></span>
							<input type="text" id="wsergo-cm-const" class="small-text" inputmode="decimal" value="0" />
						</div>
						<div style="flex:1;min-width:180px;">
							<label for="wsergo-cm-slug" class="screen-reader-text"><?php esc_html_e( 'Итоговый ключ', 'worldstat-ergonomics' ); ?></label>
							<span class="description" style="display:block;margin-bottom:4px;"><?php esc_html_e( 'Итоговый ключ', 'worldstat-ergonomics' ); ?></span>
							<input type="text" id="wsergo-cm-slug" class="regular-text code" maxlength="96" autocomplete="off" placeholder="<?php esc_attr_e( 'напр. my_ratio', 'worldstat-ergonomics' ); ?>" />
						</div>
						<div>
							<button type="button" id="wsergo-cm-add" class="button button-primary"><?php esc_html_e( 'Добавить', 'worldstat-ergonomics' ); ?></button>
						</div>
					</div>
					<p class="description" style="margin-top:0;">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: number of saved custom metric rules. */
								_n( 'Сохранено правил в калькуляторе: %d.', 'Сохранено правил в калькуляторе: %d.', $wsergo_cm_cnt, 'worldstat-ergonomics' ),
								(int) $wsergo_cm_cnt
							)
						);
						?>
					</p>
					<input type="hidden" id="wsergo-cm-opt-name" value="<?php echo esc_attr( $wsergo_cm_opt ); ?>" />
					<p class="description"><?php esc_html_e( 'Порядок добавления важен: в следующем правиле можно ссылаться на ключ из предыдущего. После сохранения обновится кэш макроиндекса.', 'worldstat-ergonomics' ); ?></p>
					<hr />
					<h3><?php esc_html_e( 'Подписи к данным (русский)', 'worldstat-ergonomics' ); ?></h3>
					<?php if ( ! $wsp_csv_has_datasets && class_exists( 'WorldStat_Uploaded_Csv' ) && empty( $data_label_keys ) ) : ?>
						<div class="notice notice-warning inline" style="margin:10px 0;padding:10px 12px;">
							<p style="margin:0;">
								<?php
								echo esc_html(
									__( 'В базе нет загруженных CSV и не заданы пользовательские параметры: список ключей пуст. Загрузите CSV или добавьте строки в калькуляторе выше.', 'worldstat-ergonomics' )
								);
								?>
								<?php if ( current_user_can( 'manage_options' ) ) : ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=worldstat-csv' ) ); ?>"><?php esc_html_e( 'Данные CSV', 'worldstat-ergonomics' ); ?></a>
								<?php endif; ?>
							</p>
						</div>
					<?php endif; ?>
					<p class="description"><?php esc_html_e( 'Список ключей — столбцы из CSV, дополнительные ключи из блока ниже, пользовательские параметры из калькулятора. Подпись — здесь или импорт «Переводы»; иначе на сайте прочерк.', 'worldstat-ergonomics' ); ?></p>
					<div style="max-height:340px;overflow:auto;border:1px solid #c3c4c7;padding:10px;background:#fff;margin-bottom:12px;">
						<table class="widefat striped" style="margin:0;">
							<thead>
								<tr>
									<th scope="col" style="width:38%;"><?php esc_html_e( 'Ключ в данных', 'worldstat-ergonomics' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Как показывать пользователю', 'worldstat-ergonomics' ); ?></th>
								</tr>
							</thead>
							<tbody>
							<?php foreach ( $data_label_keys as $lk ) : ?>
								<?php $ov = isset( $data_labels_saved[ $lk ] ) ? $data_labels_saved[ $lk ] : ''; ?>
								<tr>
									<td><code><?php echo esc_html( $lk ); ?></code></td>
									<td>
										<input type="text" class="widefat" name="<?php echo esc_attr( WSErgo_Settings::OPTION_DATA_LABELS_RU ); ?>[<?php echo esc_attr( $lk ); ?>]" value="<?php echo esc_attr( $ov ); ?>" placeholder="<?php esc_attr_e( 'Введите обозначение на русском', 'worldstat-ergonomics' ); ?>" maxlength="240" />
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<hr />
					<h3><?php esc_html_e( 'Матрица критериев (только отбор параметров)', 'worldstat-ergonomics' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Отметьте для каждого параметра, к каким из шести критериев странового индекса он относится. Веса слагаемых и вес критерия в E настраиваются на вкладке «Формула».', 'worldstat-ergonomics' ); ?></p>
					<?php
					$matrix_col_short = [
						'F'  => __( 'Функц.', 'worldstat-ergonomics' ),
						'Cm' => __( 'Комф.', 'worldstat-ergonomics' ),
						'H'  => __( 'Обит.', 'worldstat-ergonomics' ),
						'A'  => __( 'Осв.', 'worldstat-ergonomics' ),
						'S'  => __( 'Безоп.', 'worldstat-ergonomics' ),
						'Ct' => __( 'Упр.', 'worldstat-ergonomics' ),
					];
					?>
					<div class="wsergo-macro-matrix-wrap" style="max-height:420px;overflow:auto;border:1px solid #c3c4c7;background:#fff;margin-bottom:12px;">
						<table class="widefat striped wsergo-macro-matrix-table" style="margin:0;min-width:680px;border-collapse:separate;border-spacing:0;">
							<thead>
								<tr>
									<th scope="col" class="wsergo-macro-matrix-sticky-corner" style="min-width:220px;"><?php esc_html_e( 'Параметр', 'worldstat-ergonomics' ); ?></th>
									<?php foreach ( $macro_axes_six as $axk ) : ?>
										<?php
										$th_short = isset( $matrix_col_short[ $axk ] ) ? $matrix_col_short[ $axk ] : $axk;
										$th_full  = isset( $macro_axis_labels[ $axk ] ) ? $macro_axis_labels[ $axk ] : $axk;
										?>
										<th scope="col" class="wsergo-macro-matrix-sticky-head" style="text-align:center;min-width:52px;padding:8px 4px;" title="<?php echo esc_attr( $th_full ); ?>">
											<span class="description"><?php echo esc_html( $th_short ); ?></span>
											<br /><code style="font-size:10px;"><?php echo esc_html( $axk ); ?></code>
										</th>
									<?php endforeach; ?>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $signals_ui as $msig ) : ?>
								<tr>
									<td class="wsergo-macro-matrix-sticky-firstcol">
										<?php if ( isset( $wsergo_custom_slugs_flip[ $msig ] ) ) : ?>
											<?php
											$wsergo_cm_del_url = wp_nonce_url(
												admin_url( 'admin-post.php?action=wsergo_delete_custom_metric&slug=' . rawurlencode( $msig ) ),
												'wsergo_delete_custom_metric'
											);
											?>
											<a href="<?php echo esc_url( $wsergo_cm_del_url ); ?>" class="button button-small" style="margin:0 10px 6px 0;vertical-align:middle;" onclick="return confirm('<?php echo esc_js( __( 'Удалить пользовательский параметр и его формулу? Отметки в матрице и подпись будут сброшены.', 'worldstat-ergonomics' ) ); ?>');"><?php esc_html_e( 'Удалить', 'worldstat-ergonomics' ); ?></a>
										<?php endif; ?>
										<strong><?php echo esc_html( class_exists( 'WSErgo_Country_Macro_Calculator' ) ? WSErgo_Country_Macro_Calculator::data_label_ru( $msig ) : $msig ); ?></strong>
										<br /><code class="description"><?php echo esc_html( $msig ); ?></code>
									</td>
									<?php foreach ( $macro_axes_six as $axk ) : ?>
										<?php $m_on = ! empty( $macro_criteria_matrix[ $msig ][ $axk ] ); ?>
										<td style="text-align:center;vertical-align:middle;padding:6px 4px;">
											<input type="checkbox" name="<?php echo esc_attr( WSErgo_Settings::OPTION_MACRO_CRITERIA_MATRIX ); ?>[<?php echo esc_attr( $msig ); ?>][<?php echo esc_attr( $axk ); ?>]" value="1" <?php checked( $m_on ); ?> />
										</td>
									<?php endforeach; ?>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<hr />
					<table class="form-table">
						<tr>
							<th scope="row"><label for="wsergo_methodology_version"><?php esc_html_e( 'Версия методики', 'worldstat-ergonomics' ); ?></label></th>
							<td>
								<input name="wsergo_methodology_version" id="wsergo_methodology_version" type="text" value="<?php echo esc_attr( get_option( 'wsergo_methodology_version', '1.2' ) ); ?>" class="regular-text" />
								<p class="description"><?php esc_html_e( 'Условный номер или метка редакции методики (например 1.2, 2025‑A). Указывается в подписях к индексу и отчётах, чтобы было видно, по какой версии формул и наборов данных получены значения. Повышайте версию при изменении формул, весов осей или состава CSV.', 'worldstat-ergonomics' ); ?></p>
							</td>
						</tr>
					</table>
					<hr />
					<h3><?php esc_html_e( 'Признаки для k-means (макро)', 'worldstat-ergonomics' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Те же параметры, что в матрице критериев выше (столбцы CSV и пользовательские). Отметьте не меньше двух признаков для вектора кластеризации и нормализации внутри кластеров. «Автоподбор» анализирует разброс и корреляции в данных и подбирает подходящий набор признаков и число кластеров k (метод локтя).', 'worldstat-ergonomics' ); ?></p>
					<p style="margin:.75em 0;">
						<button type="button" class="button button-primary wsergo-auto-tune-clusters" data-scope="country"><?php esc_html_e( 'Автоподбор признаков и k', 'worldstat-ergonomics' ); ?></button>
						<button type="button" class="button wsergo-auto-tune-clusters-save" data-scope="country"><?php esc_html_e( 'Автоподбор и сохранить', 'worldstat-ergonomics' ); ?></button>
						<span class="wsergo-auto-tune-status wsp-muted" style="margin-left:8px;"></span>
					</p>
					<div id="wsergo-cluster-features-country" class="wsergo-cluster-features-host" data-scope="country" data-wsergo-features-inline="1" style="max-height:220px;overflow:auto;border:1px solid #c3c4c7;padding:10px;background:#fff;">
						<?php
						if ( function_exists( 'wsergo_render_cluster_features_checkboxes' ) ) {
							wsergo_render_cluster_features_checkboxes(
								'country',
								$signals_ui,
								$cf_for_checkboxes,
								WSErgo_Settings::OPTION_MACRO_CLUSTER_FEATURES
							);
						}
						?>
					</div>
					<div id="wsergo-cluster-tune-report-country" class="wsergo-cluster-tune-report" style="display:none;margin-top:10px;padding:10px;border:1px solid #c3c4c7;background:#fff;max-height:180px;overflow:auto;"></div>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="wsergo_macro_extra_signals"><?php esc_html_e( 'Дополнительные ключи признаков (wide)', 'worldstat-ergonomics' ); ?></label>
							</th>
							<td>
								<textarea id="wsergo_macro_extra_signals" name="<?php echo esc_attr( WSErgo_Settings::OPTION_MACRO_EXTRA_SIGNALS_TEXT ); ?>" rows="6" class="large-text code"><?php echo esc_textarea( $macro_extra_signals_text ); ?></textarea>
								<p class="description">
									<?php esc_html_e( 'По одному латинскому ключу в строке (как после нормализации заголовка столбца wide-CSV). Ключи появляются в списке чекбоксов выше и в формулах осей; до 50 строк. Если имя столбца в файле не совпадает с ключом, сопоставление по-прежнему задаётся в коде: wide_csv_column_to_signal.', 'worldstat-ergonomics' ); ?>
								</p>
							</td>
						</tr>
					</table>
					<hr />
					<h3><?php esc_html_e( 'Режим расчёта по стране и CSV', 'worldstat-ergonomics' ); ?></h3>
					<p class="description">
						<?php esc_html_e( 'Индекс страны строится по страновым рядам из CSV платформы, опорному году и кластеризации k-means.', 'worldstat-ergonomics' ); ?>
					</p>
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Источник индекса', 'worldstat-ergonomics' ); ?></th>
							<td>
								<input type="hidden" name="<?php echo esc_attr( WSErgo_Settings::OPTION_COUNTRY_INDEX_SOURCE ); ?>" value="macro_datasets" />
								<p class="description"><?php esc_html_e( 'Используются только макроданные (CSV в разделе «Данные CSV» платформы).', 'worldstat-ergonomics' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wsergo_macro_reference_year"><?php esc_html_e( 'Опорный год (макро)', 'worldstat-ergonomics' ); ?></label></th>
							<td>
								<?php
								$mref_min = class_exists( 'WorldStat_Platform_Years' ) ? max( 1900, WorldStat_Platform_Years::min() ) : 1900;
								$mref_max = class_exists( 'WorldStat_Platform_Years' ) ? max( 2100, WorldStat_Platform_Years::max() ) : 2100;
								?>
								<input type="number" id="wsergo_macro_reference_year" name="<?php echo esc_attr( WSErgo_Settings::OPTION_MACRO_REFERENCE_YEAR ); ?>" value="<?php echo esc_attr( (string) $macro_year ); ?>" class="small-text" min="<?php echo esc_attr( (string) $mref_min ); ?>" max="<?php echo esc_attr( (string) $mref_max ); ?>" step="1" />
								<p class="description"><?php esc_html_e( 'Год для выборки значений country_code + year в CSV (если в файле есть столбец года).', 'worldstat-ergonomics' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wsergo_macro_k_clusters"><?php esc_html_e( 'Число кластеров k-means (макро)', 'worldstat-ergonomics' ); ?></label></th>
							<td>
								<input type="number" id="wsergo_macro_k_clusters" name="<?php echo esc_attr( WSErgo_Settings::OPTION_MACRO_K_CLUSTERS ); ?>" value="<?php echo esc_attr( (string) $macro_k ); ?>" class="small-text" min="2" max="12" step="1" />
								<p class="description"><?php esc_html_e( 'Нормализация min–max выполняется внутри кластера стран.', 'worldstat-ergonomics' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wsergo_macro_reference_country"><?php esc_html_e( 'Эталонная страна', 'worldstat-ergonomics' ); ?></label></th>
							<td>
								<select id="wsergo_macro_reference_country" name="<?php echo esc_attr( WSErgo_Settings::OPTION_MACRO_REFERENCE_COUNTRY_POST_ID ); ?>">
									<option value="0"><?php esc_html_e( '— не выбрана —', 'worldstat-ergonomics' ); ?></option>
									<?php foreach ( $country_posts_for_ref as $cp ) : ?>
										<?php
										$cid = (int) $cp->ID;
										$cis = strtoupper( trim( (string) get_post_meta( $cid, 'wsp_iso_alpha2', true ) ) );
										if ( strlen( $cis ) !== 2 ) {
											continue;
										}
										?>
										<option value="<?php echo esc_attr( (string) $cid ); ?>" <?php selected( $macro_ref_country_id, $cid ); ?>><?php echo esc_html( get_the_title( $cp ) . ' (' . $cis . ')' ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Для колонки «Пример данных» на вкладке «Формула»: значения признаков из загруженных макро-CSV для этой страны и опорного года (динамически из кэша расчёта).', 'worldstat-ergonomics' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<div id="tab-formula" class="wsergo-tab-panel wsergo-tab-panel-country" style="display:none">
					<h2><?php esc_html_e( 'Формула', 'worldstat-ergonomics' ); ?></h2>

					<h3><?php esc_html_e( 'Активная модель расчёта E', 'worldstat-ergonomics' ); ?></h3>
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Модель', 'worldstat-ergonomics' ); ?></th>
							<td>
								<select name="<?php echo esc_attr( WSErgo_Settings::OPTION_ACTIVE_MODEL ); ?>">
									<?php foreach ( $models as $m ) : ?>
										<option value="<?php echo esc_attr( $m['id'] ); ?>" <?php selected( $active_id, $m['id'] ); ?>><?php echo esc_html( $m['name'] ); ?></option>
									<?php endforeach; ?>
								</select>
								<?php if ( $active_model ) : ?>
									<p class="description"><?php echo esc_html( $active_model['leaf_formula'] !== '' ? $active_model['leaf_formula'] : __( 'Пустая формула: взвешенное среднее по шести измерениям объекта (как заданы веса в карточке квартала/здания).', 'worldstat-ergonomics' ) ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					</table>
					<hr />
					<h3><?php esc_html_e( 'Коэффициенты k_*', 'worldstat-ergonomics' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Глобальные множители в пользовательской формуле сводного E по объекту (k_default и др.). Отдельно от весов макрокритериев страны.', 'worldstat-ergonomics' ); ?></p>
					<table class="form-table">
						<?php foreach ( $coeffs as $k => $v ) : ?>
						<tr>
							<th scope="row"><label><?php echo esc_html( $k ); ?></label></th>
							<td><input type="text" name="wsergo_coefficients[<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( (string) $v ); ?>" class="small-text" /></td>
						</tr>
						<?php endforeach; ?>
					</table>
					<hr />
					<h3><?php esc_html_e( 'Модели и DSL', 'worldstat-ergonomics' ); ?></h3>
					<p class="description">
						<?php esc_html_e( 'Простой режим: оставьте формулу пустой — сводный E считается как взвешенное среднее по шести осям объекта. Своя формула: оси можно комбинировать с коэффициентами k_*, показателями i_* и весами w_*.', 'worldstat-ergonomics' ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'В формуле используются обозначения осей (баллы 0–100), веса w_* из карточки объекта, показатели i_* и множители k_* из таблицы выше.', 'worldstat-ergonomics' ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'Допустимые идентификаторы:', 'worldstat-ergonomics' ); ?>
						<code><?php echo esc_html( $allowed_txt ); ?></code>
					</p>
					<p>
						<label for="wsergo_test_formula_inline"><?php esc_html_e( 'Проверка формулы (тестовые 50 по всем осям)', 'worldstat-ergonomics' ); ?></label><br />
						<textarea id="wsergo_test_formula_inline" class="large-text" rows="2" placeholder="<?php esc_attr_e( 'Оставьте пустым или вставьте выражение по списку допустимых идентификаторов ниже', 'worldstat-ergonomics' ); ?>"></textarea><br />
						<button type="button" class="button" id="wsergo-test-formula-btn"><?php esc_html_e( 'Проверить', 'worldstat-ergonomics' ); ?></button>
						<span id="wsergo-formula-test-result" class="description"></span>
					</p>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Идентификатор', 'worldstat-ergonomics' ); ?></th>
								<th><?php esc_html_e( 'Название', 'worldstat-ergonomics' ); ?></th>
								<th><?php esc_html_e( 'Формула сводного E (пусто = классика)', 'worldstat-ergonomics' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ( array_values( $models ) as $i => $m ) :
								?>
							<tr>
								<td><input type="text" name="wsergo_models[<?php echo esc_attr( (string) $i ); ?>][id]" value="<?php echo esc_attr( $m['id'] ); ?>" class="regular-text" /></td>
								<td><input type="text" name="wsergo_models[<?php echo esc_attr( (string) $i ); ?>][name]" value="<?php echo esc_attr( $m['name'] ); ?>" class="regular-text" /></td>
								<td><textarea name="wsergo_models[<?php echo esc_attr( (string) $i ); ?>][leaf_formula]" class="large-text" rows="2"><?php echo esc_textarea( $m['leaf_formula'] ); ?></textarea></td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p class="description"><?php esc_html_e( 'Чтобы добавить модель, сохраните страницу и отредактируйте массив в БД или добавьте строку через фильтр wsergo_default_models.', 'worldstat-ergonomics' ); ?></p>

					<hr />

					<style type="text/css">
						.wsergo-macro-crit { border: 1px solid #c3c4c7; margin-bottom: 8px; border-radius: 4px; background: #fff; }
						.wsergo-macro-crit > summary.wsergo-macro-crit__bar {
							display: flex; flex-wrap: wrap; align-items: center; gap: 8px 20px;
							padding: 10px 12px; cursor: pointer; list-style: none;
						}
						.wsergo-macro-crit > summary.wsergo-macro-crit__bar::-webkit-details-marker { display: none; }
						.wsergo-macro-crit__chev { flex-shrink: 0; width: 20px; height: 20px; font-size: 18px; line-height: 1; transition: transform 0.15s ease; opacity: 0.8; }
						.wsergo-macro-crit[open] > summary .wsergo-macro-crit__chev { transform: rotate(90deg); }
						.wsergo-macro-crit__title { font-weight: 600; min-width: 160px; flex: 1; }
						.wsergo-macro-crit__e input.small-text { max-width: 5.5em; vertical-align: middle; }
						table.wsergo-macro-crit-table { table-layout: fixed; width: 100%; border-collapse: collapse; }
						table.wsergo-macro-crit-table th,
						table.wsergo-macro-crit-table td { vertical-align: middle; word-wrap: break-word; }
						table.wsergo-macro-crit-table col.col-macro-sig { width: 30%; }
						table.wsergo-macro-crit-table col.col-macro-w { width: 14%; }
						table.wsergo-macro-crit-table col.col-macro-inv { width: 14%; }
						table.wsergo-macro-crit-table col.col-macro-ex { width: 42%; }
						table.wsergo-macro-crit-table td.col-macro-inv,
						table.wsergo-macro-crit-table th.col-macro-inv { text-align: center; }
						table.wsergo-macro-crit-table td.col-macro-w input { width: 100%; max-width: 7em; box-sizing: border-box; }
						table.wsergo-macro-crit-table tfoot td { border-top: 1px solid #c3c4c7; padding-top: 8px; font-weight: 600; }
						.wsergo-macro-sum-line { font-weight: 600; }
					</style>
					<?php
					$wsergo_country_macro_formula_visible = $wsp_csv_has_datasets || ! empty( $signals_ui );
					?>
					<?php if ( $wsergo_country_macro_formula_visible ) : ?>
					<h3><?php esc_html_e( 'Макро: шесть критериев странового индекса', 'worldstat-ergonomics' ); ?></h3>
					<?php if ( ! $wsp_csv_has_datasets && ! empty( $signals_ui ) ) : ?>
						<div class="notice notice-info inline" style="margin:0 0 10px 0;padding:10px 12px;">
							<p style="margin:0;"><?php esc_html_e( 'В разделе «Данные CSV» нет загруженных наборов — «Пример данных» может быть пустым для столбцов только из CSV. Веса по признакам из матрицы страны всё равно редактируются здесь.', 'worldstat-ergonomics' ); ?></p>
						</div>
					<?php endif; ?>
					<p class="description">
						<?php esc_html_e( 'Пока на «Данные» ни одна галочка не стоит, для всех критериев действует встроенная методика. Как только вы отметите параметры у критерия и сохраните настройки, они появятся здесь; состав менять нельзя — только веса.', 'worldstat-ergonomics' ); ?>
						<a href="#tab-data" class="wsergo-tab-deep-link"><?php esc_html_e( 'К матрице отбора', 'worldstat-ergonomics' ); ?></a>
					</p>
					<?php if ( $ref_macro_example_note !== '' ) : ?>
						<p class="description"><strong><?php esc_html_e( 'Пример данных', 'worldstat-ergonomics' ); ?>:</strong> <?php echo esc_html( $ref_macro_example_note ); ?> — <?php esc_html_e( 'значения из загруженных макро-CSV (сырой ряд, опорный год).', 'worldstat-ergonomics' ); ?></p>
					<?php elseif ( $macro_ref_country_id > 0 ) : ?>
						<p class="description"><?php esc_html_e( 'Для выбранной эталонной страны нет строки сырых признаков в кэше расчёта: проверьте код ISO2 в карточке страны и наличие строк в CSV за опорный год.', 'worldstat-ergonomics' ); ?></p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'Чтобы заполнить столбец «Пример данных», выберите эталонную страну на вкладке «Данные» и сохраните настройки.', 'worldstat-ergonomics' ); ?></p>
					<?php endif; ?>
						<?php
						$criteria_fold_i = 0;
						foreach ( $macro_axes_six as $ax_key ) :
							++$criteria_fold_i;
							$ax_lab = isset( $macro_axis_labels[ $ax_key ] ) ? $macro_axis_labels[ $ax_key ] : $ax_key;
							$picked = [];
							foreach ( $macro_criteria_matrix as $sig => $axes_map ) {
								if ( ! empty( $axes_map[ $ax_key ] ) ) {
									$picked[] = $sig;
								}
							}
							sort( $picked, SORT_STRING );
							$rows_ax = isset( $macro_axis_resolved[ $ax_key ] ) ? $macro_axis_resolved[ $ax_key ] : [];
							?>
					<details class="wsergo-macro-crit" data-macro-axis="<?php echo esc_attr( $ax_key ); ?>" <?php echo 1 === $criteria_fold_i ? 'open' : ''; ?>>
						<summary class="wsergo-macro-crit__bar">
							<span class="dashicons dashicons-arrow-right-alt2 wsergo-macro-crit__chev" aria-hidden="true"></span>
							<span class="wsergo-macro-crit__title"><?php echo esc_html( $ax_lab ); ?></span>
							<span class="wsergo-macro-crit__e">
								<label>
									<span class="description"><?php esc_html_e( 'Вес критерия в E', 'worldstat-ergonomics' ); ?></span>
									<input type="text" class="small-text" name="<?php echo esc_attr( WSErgo_Settings::OPTION_MACRO_E_AXIS_WEIGHTS ); ?>[<?php echo esc_attr( $ax_key ); ?>]" value="<?php echo esc_attr( (string) ( $macro_e_w[ $ax_key ] ?? '' ) ); ?>" inputmode="decimal" onclick="event.stopPropagation();" onkeydown="event.stopPropagation();" />
								</label>
							</span>
							<span class="description wsergo-macro-sum-line">
								<span class="description"><?php esc_html_e( 'Σ весов параметров (после распределения)', 'worldstat-ergonomics' ); ?></span>
								<strong class="wsergo-macro-sum-total" data-macro-axis="<?php echo esc_attr( $ax_key ); ?>"><?php echo esc_html( sprintf( '%.4f', array_sum( array_map( static function ( $r ) { return (float) ( $r['weight'] ?? 0 ); }, $rows_ax ) ) ) ); ?></strong>
							</span>
						</summary>
						<div style="padding:0 14px 16px 14px;">
							<?php if ( count( $picked ) === 0 ) : ?>
								<p class="description"><?php esc_html_e( 'Нет отмеченных параметров — для этого критерия подставляется встроенная методика (ниже состав по умолчанию).', 'worldstat-ergonomics' ); ?></p>
								<?php if ( ! empty( $rows_ax ) ) : ?>
								<table class="widefat striped wsergo-macro-crit-table" style="margin-top:8px;">
									<colgroup>
										<col class="col-macro-sig" />
										<col class="col-macro-w" />
										<col class="col-macro-inv" />
										<col class="col-macro-ex" />
									</colgroup>
									<thead>
										<tr>
											<th class="col-macro-sig"><?php esc_html_e( 'Признак', 'worldstat-ergonomics' ); ?></th>
											<th class="col-macro-w"><?php esc_html_e( 'Доля (норм.)', 'worldstat-ergonomics' ); ?></th>
											<th class="col-macro-inv"><?php esc_html_e( 'Инверсия 1−x', 'worldstat-ergonomics' ); ?></th>
											<th class="col-macro-ex"><?php esc_html_e( 'Пример данных', 'worldstat-ergonomics' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $rows_ax as $rowt ) : ?>
											<?php
											$rsig = isset( $rowt['signal'] ) ? (string) $rowt['signal'] : '';
											$rlab = class_exists( 'WSErgo_Country_Macro_Calculator' ) ? WSErgo_Country_Macro_Calculator::data_label_ru( $rsig ) : $rsig;
											?>
										<tr>
											<td class="col-macro-sig"><?php echo esc_html( $rlab ); ?><br /><code class="description"><?php echo esc_html( $rsig ); ?></code></td>
											<td class="col-macro-w"><?php echo esc_html( sprintf( '%.4f', isset( $rowt['weight'] ) ? (float) $rowt['weight'] : 0.0 ) ); ?></td>
											<td class="col-macro-inv"><?php echo ! empty( $rowt['invert'] ) ? esc_html__( 'да', 'worldstat-ergonomics' ) : esc_html__( 'нет', 'worldstat-ergonomics' ); ?></td>
											<td class="col-macro-ex"><code class="description"><?php echo esc_html( WSErgo_Country_Admin::format_macro_signal_example_display( $ref_macro_raw_row, $rsig ) ); ?></code></td>
										</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
								<?php endif; ?>
							<?php else : ?>
								<table class="widefat striped wsergo-macro-crit-table">
									<colgroup>
										<col class="col-macro-sig" />
										<col class="col-macro-w" />
										<col class="col-macro-inv" />
										<col class="col-macro-ex" />
									</colgroup>
									<thead>
										<tr>
											<th class="col-macro-sig"><?php esc_html_e( 'Признак', 'worldstat-ergonomics' ); ?></th>
											<th class="col-macro-w"><?php esc_html_e( 'Вес', 'worldstat-ergonomics' ); ?></th>
											<th class="col-macro-inv"><?php esc_html_e( 'Инверсия 1−x', 'worldstat-ergonomics' ); ?></th>
											<th class="col-macro-ex"><?php esc_html_e( 'Пример данных', 'worldstat-ergonomics' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $picked as $psig ) : ?>
											<?php
											$row_match = null;
											foreach ( $rows_ax as $rr ) {
												if ( (string) ( $rr['signal'] ?? '' ) === $psig ) {
													$row_match = $rr;
													break;
												}
											}
											$w_stored = isset( $macro_criteria_w[ $psig ][ $ax_key ] ) ? (string) $macro_criteria_w[ $psig ][ $ax_key ] : '';
											$plab     = class_exists( 'WSErgo_Country_Macro_Calculator' ) ? WSErgo_Country_Macro_Calculator::data_label_ru( $psig ) : $psig;
											if ( isset( $macro_criteria_inv[ $psig ] ) && is_array( $macro_criteria_inv[ $psig ] ) && array_key_exists( $ax_key, $macro_criteria_inv[ $psig ] ) ) {
												$inv_checked = (bool) $macro_criteria_inv[ $psig ][ $ax_key ];
											} else {
												$inv_checked = $row_match && ! empty( $row_match['invert'] );
											}
											$inv_name = WSErgo_Settings::OPTION_MACRO_CRITERIA_INVERTS . '[' . $psig . '][' . $ax_key . ']';
											?>
										<tr>
											<td class="col-macro-sig">
												<strong><?php echo esc_html( $plab ); ?></strong>
												<br /><code class="description"><?php echo esc_html( $psig ); ?></code>
											</td>
											<td class="col-macro-w">
												<input type="text" class="small-text wsergo-macro-w-input" data-macro-signal="<?php echo esc_attr( $psig ); ?>" name="<?php echo esc_attr( WSErgo_Settings::OPTION_MACRO_CRITERIA_WEIGHTS ); ?>[<?php echo esc_attr( $psig ); ?>][<?php echo esc_attr( $ax_key ); ?>]" value="<?php echo esc_attr( $w_stored ); ?>" inputmode="decimal" placeholder="<?php esc_attr_e( 'авто', 'worldstat-ergonomics' ); ?>" autocomplete="off" />
											</td>
											<td class="col-macro-inv">
												<input type="hidden" name="<?php echo esc_attr( $inv_name ); ?>" value="0" />
												<label><input type="checkbox" name="<?php echo esc_attr( $inv_name ); ?>" value="1" <?php checked( $inv_checked ); ?> /> <?php esc_html_e( '1−x', 'worldstat-ergonomics' ); ?></label>
											</td>
											<td class="col-macro-ex"><code class="description"><?php echo esc_html( WSErgo_Country_Admin::format_macro_signal_example_display( $ref_macro_raw_row, $psig ) ); ?></code></td>
										</tr>
										<?php endforeach; ?>
									</tbody>
									<tfoot>
										<tr>
											<td class="description col-macro-sig"><?php esc_html_e( 'Σ весов (после распределения)', 'worldstat-ergonomics' ); ?></td>
											<td class="col-macro-w"><strong class="wsergo-macro-sum-total" data-macro-axis="<?php echo esc_attr( $ax_key ); ?>"><?php echo esc_html( sprintf( '%.4f', array_sum( array_map( static function ( $r ) { return (float) ( $r['weight'] ?? 0 ); }, $rows_ax ) ) ) ); ?></strong></td>
											<td class="col-macro-inv"></td>
											<td class="col-macro-ex"></td>
										</tr>
									</tfoot>
								</table>
							<?php endif; ?>
						</div>
					</details>
						<?php endforeach; ?>
					<?php else : ?>
						<h3><?php esc_html_e( 'Макро: шесть критериев странового индекса', 'worldstat-ergonomics' ); ?></h3>
						<div class="notice notice-warning inline" style="margin:10px 0;padding:12px;">
							<p style="margin:0;">
								<?php esc_html_e( 'Этот блок скрыт: нет признаков для макро (ни столбцов из CSV, ни дополнительных ключей, ни пользовательского калькулятора страны). Задайте источники на вкладке «Данные» или загрузите CSV.', 'worldstat-ergonomics' ); ?>
							</p>
							<p style="margin:.65em 0 0;">
								<?php esc_html_e( 'После появления ключей в списке «Данные» отметьте параметры в матрице и сохраните настройки — здесь появятся редактируемые веса.', 'worldstat-ergonomics' ); ?>
								<?php if ( current_user_can( 'manage_options' ) ) : ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=worldstat-csv' ) ); ?>"><?php esc_html_e( 'Данные CSV', 'worldstat-ergonomics' ); ?></a>
								<?php endif; ?>
								&nbsp;·&nbsp;
								<a href="#tab-data" class="wsergo-tab-deep-link"><?php esc_html_e( 'Вкладка «Данные»', 'worldstat-ergonomics' ); ?></a>
							</p>
						</div>
					<?php endif; ?>

				</div>

					<?php submit_button(); ?>
				</form>
			</div><!-- #wsergo-panel-country -->
