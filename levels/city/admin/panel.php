<?php
/**
 * Панель настроек «Эргономичность города».
 * Подключается из core/class-ergo-admin.php (переменные из render_settings_page).
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$wsergo_scope_style = ! empty( $wsergo_scope_hidden ) ? ' style="display:none"' : '';
?>
			<div id="wsergo-panel-city" class="wsergo-scope-panel"<?php echo $wsergo_scope_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<div class="notice notice-info inline" style="margin:12px 0;padding:12px;">
					<p style="margin:.4em 0;"><strong><?php esc_html_e( 'Эргономичность города', 'worldstat-ergonomics' ); ?></strong></p>
					<p style="margin:.4em 0;"><?php esc_html_e( 'Отдельные вкладки для городского уровня: измерения, данные и формула.', 'worldstat-ergonomics' ); ?></p>
				</div>
				<form class="wsergo-settings-form" method="post" action="options.php">
					<?php settings_fields( 'wsergo_settings' ); ?>
					<h2 class="nav-tab-wrapper wsergo-city-inner-nav" style="margin-top:8px;">
						<a href="#city-tab-overview" class="nav-tab nav-tab-active" data-city-tab="city-tab-overview"><?php esc_html_e( 'Обзор', 'worldstat-ergonomics' ); ?></a>
						<a href="#city-tab-measure" class="nav-tab" data-city-tab="city-tab-measure"><?php esc_html_e( 'Измерения', 'worldstat-ergonomics' ); ?></a>
						<a href="#city-tab-indicators" class="nav-tab" data-city-tab="city-tab-indicators"><?php esc_html_e( 'Показатели', 'worldstat-ergonomics' ); ?></a>
						<a href="#city-tab-data" class="nav-tab" data-city-tab="city-tab-data"><?php esc_html_e( 'Данные города', 'worldstat-ergonomics' ); ?></a>
						<a href="#city-tab-formula" class="nav-tab" data-city-tab="city-tab-formula"><?php esc_html_e( 'Формула E', 'worldstat-ergonomics' ); ?></a>
						<a href="#city-tab-coeff" class="nav-tab" data-city-tab="city-tab-coeff"><?php esc_html_e( 'Коэффициенты', 'worldstat-ergonomics' ); ?></a>
						<a href="#city-tab-agg" class="nav-tab" data-city-tab="city-tab-agg"><?php esc_html_e( 'Агрегация', 'worldstat-ergonomics' ); ?></a>
					</h2>

					<div id="city-tab-overview" class="wsergo-city-tab-panel">
						<p class="description">
							<?php esc_html_e( 'Городской уровень использует ту же методологию и тот же движок расчёта, что и старая версия: веса измерений, показатели, карта данных города и DSL-формула E.', 'worldstat-ergonomics' ); ?>
						</p>
						<p class="description">
							<?php esc_html_e( 'Если у города есть кварталы с оценкой — индекс города агрегируется по кварталам. Если кварталов нет — используется расчёт по данным Cities и вашим сопоставлениям.', 'worldstat-ergonomics' ); ?>
						</p>
						<table class="form-table">
							<tr>
								<th scope="row"><label for="wsergo_city_methodology_version_overview"><?php esc_html_e( 'Версия методики', 'worldstat-ergonomics' ); ?></label></th>
								<td>
									<input name="wsergo_methodology_version" id="wsergo_city_methodology_version_overview" type="text" value="<?php echo esc_attr( get_option( 'wsergo_methodology_version', '1.2' ) ); ?>" class="regular-text" />
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Активная модель расчёта E', 'worldstat-ergonomics' ); ?></th>
								<td>
									<select name="<?php echo esc_attr( WSErgo_Settings::OPTION_ACTIVE_MODEL ); ?>">
										<?php foreach ( $models as $m ) : ?>
											<option value="<?php echo esc_attr( $m['id'] ); ?>" <?php selected( $active_id, $m['id'] ); ?>><?php echo esc_html( $m['name'] ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
						</table>
					</div>

					<div id="city-tab-measure" class="wsergo-city-tab-panel" style="display:none;">
						<p class="description"><?php esc_html_e( 'Введите веса шести измерений для расчёта городского E (после сохранения сумма приводится к 1).', 'worldstat-ergonomics' ); ?></p>
						<table class="form-table">
							<?php foreach ( WSErgo_Model::DIMENSION_KEYS as $dim ) : ?>
								<tr>
									<th scope="row"><label for="w_city_<?php echo esc_attr( $dim ); ?>"><?php echo esc_html( $labels[ $dim ] ); ?> <span class="description"><?php esc_html_e( '(доля влияния)', 'worldstat-ergonomics' ); ?></span></label></th>
									<td>
										<input name="wsergo_dimension_weights[<?php echo esc_attr( $dim ); ?>]" id="w_city_<?php echo esc_attr( $dim ); ?>" type="text" value="<?php echo esc_attr( isset( $weights[ $dim ] ) ? (string) $weights[ $dim ] : '' ); ?>" class="small-text" />
									</td>
								</tr>
							<?php endforeach; ?>
						</table>
					</div>

					<div id="city-tab-indicators" class="wsergo-city-tab-panel" style="display:none;">
						<p class="description"><?php esc_html_e( 'Шкалы сырых величин и веса показателей по шести измерениям, используемые в городском расчёте.', 'worldstat-ergonomics' ); ?></p>
						<p>
							<button type="button" class="button" id="wsergo-city-add-indicator-row"><?php esc_html_e( 'Добавить показатель', 'worldstat-ergonomics' ); ?></button>
						</p>
						<table class="widefat striped" id="wsergo-city-indicator-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'ID (латиница)', 'worldstat-ergonomics' ); ?></th>
									<th><?php esc_html_e( 'Название', 'worldstat-ergonomics' ); ?></th>
									<th><?php esc_html_e( 'Уровень', 'worldstat-ergonomics' ); ?></th>
									<th><?php esc_html_e( 'Ед.', 'worldstat-ergonomics' ); ?></th>
									<th><?php esc_html_e( 'Мин', 'worldstat-ergonomics' ); ?></th>
									<th><?php esc_html_e( 'Макс', 'worldstat-ergonomics' ); ?></th>
									<th><?php esc_html_e( 'Лучше', 'worldstat-ergonomics' ); ?></th>
									<th><?php esc_html_e( 'Вес', 'worldstat-ergonomics' ); ?></th>
								</tr>
							</thead>
							<tbody id="wsergo-city-indicator-rows">
								<?php
								$city_rows_for_form = $indicator_defs;
								if ( empty( $city_rows_for_form ) ) {
									$city_rows_for_form[] = [
										'id'        => '',
										'label'     => '',
										'dimension' => WSErgo_Model::DIM_FUNCTIONALITY,
										'unit'      => '',
										'vmin'      => 0.0,
										'vmax'      => 100.0,
										'direction' => 'higher_better',
										'weight'    => 1.0,
									];
								}
								// UI fallback: always render enough rows for quick city setup
								// even when "Add indicator" button fails in browser.
								$min_rows = 6;
								while ( count( $city_rows_for_form ) < $min_rows ) {
									$city_rows_for_form[] = [
										'id'        => '',
										'label'     => '',
										'dimension' => WSErgo_Model::DIM_FUNCTIONALITY,
										'unit'      => '',
										'vmin'      => 0.0,
										'vmax'      => 100.0,
										'direction' => 'higher_better',
										'weight'    => 1.0,
									];
								}
								foreach ( array_values( $city_rows_for_form ) as $idx => $indrow ) :
									$idr = isset( $indrow['id'] ) ? (string) $indrow['id'] : '';
									?>
									<tr>
										<td><input type="text" name="wsergo_indicator_definitions[<?php echo esc_attr( (string) $idx ); ?>][id]" value="<?php echo esc_attr( $idr ); ?>" class="regular-text" pattern="[a-z0-9_\-]+" /></td>
										<td><input type="text" name="wsergo_indicator_definitions[<?php echo esc_attr( (string) $idx ); ?>][label]" value="<?php echo esc_attr( isset( $indrow['label'] ) ? (string) $indrow['label'] : '' ); ?>" class="regular-text" /></td>
										<td>
											<select name="wsergo_indicator_definitions[<?php echo esc_attr( (string) $idx ); ?>][dimension]">
												<?php foreach ( WSErgo_Model::DIMENSION_KEYS as $dk ) : ?>
													<option value="<?php echo esc_attr( $dk ); ?>" <?php selected( isset( $indrow['dimension'] ) ? (string) $indrow['dimension'] : '', $dk ); ?>><?php echo esc_html( $labels[ $dk ] ); ?></option>
												<?php endforeach; ?>
											</select>
										</td>
										<td><input type="text" name="wsergo_indicator_definitions[<?php echo esc_attr( (string) $idx ); ?>][unit]" value="<?php echo esc_attr( isset( $indrow['unit'] ) ? (string) $indrow['unit'] : '' ); ?>" class="small-text" /></td>
										<td><input type="text" name="wsergo_indicator_definitions[<?php echo esc_attr( (string) $idx ); ?>][vmin]" value="<?php echo esc_attr( isset( $indrow['vmin'] ) ? (string) $indrow['vmin'] : '0' ); ?>" class="small-text" /></td>
										<td><input type="text" name="wsergo_indicator_definitions[<?php echo esc_attr( (string) $idx ); ?>][vmax]" value="<?php echo esc_attr( isset( $indrow['vmax'] ) ? (string) $indrow['vmax'] : '100' ); ?>" class="small-text" /></td>
										<td>
											<select name="wsergo_indicator_definitions[<?php echo esc_attr( (string) $idx ); ?>][direction]">
												<option value="higher_better" <?php selected( isset( $indrow['direction'] ) ? (string) $indrow['direction'] : 'higher_better', 'higher_better' ); ?>><?php esc_html_e( '↑ больше', 'worldstat-ergonomics' ); ?></option>
												<option value="lower_better" <?php selected( isset( $indrow['direction'] ) ? (string) $indrow['direction'] : '', 'lower_better' ); ?>><?php esc_html_e( '↓ меньше', 'worldstat-ergonomics' ); ?></option>
											</select>
										</td>
										<td><input type="text" name="wsergo_indicator_definitions[<?php echo esc_attr( (string) $idx ); ?>][weight]" value="<?php echo esc_attr( isset( $indrow['weight'] ) ? (string) $indrow['weight'] : '1' ); ?>" class="small-text" /></td>
									</tr>
								<?php endforeach; ?>
								<tr class="wsergo-city-indicator-template" style="display:none">
									<td><input type="text" name="wsergo_indicator_definitions[][id]" value="" class="regular-text" /></td>
									<td><input type="text" name="wsergo_indicator_definitions[][label]" value="" class="regular-text" /></td>
									<td>
										<select name="wsergo_indicator_definitions[][dimension]">
											<?php foreach ( WSErgo_Model::DIMENSION_KEYS as $dk ) : ?>
												<option value="<?php echo esc_attr( $dk ); ?>"><?php echo esc_html( $labels[ $dk ] ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
									<td><input type="text" name="wsergo_indicator_definitions[][unit]" value="" class="small-text" /></td>
									<td><input type="text" name="wsergo_indicator_definitions[][vmin]" value="0" class="small-text" /></td>
									<td><input type="text" name="wsergo_indicator_definitions[][vmax]" value="100" class="small-text" /></td>
									<td>
										<select name="wsergo_indicator_definitions[][direction]">
											<option value="higher_better"><?php esc_html_e( '↑ больше', 'worldstat-ergonomics' ); ?></option>
											<option value="lower_better"><?php esc_html_e( '↓ меньше', 'worldstat-ergonomics' ); ?></option>
										</select>
									</td>
									<td><input type="text" name="wsergo_indicator_definitions[][weight]" value="1" class="small-text" /></td>
								</tr>
							</tbody>
						</table>
					</div>

					<div id="city-tab-data" class="wsergo-city-tab-panel" style="display:none;">
						<h3><?php esc_html_e( 'Методика расчёта (город)', 'worldstat-ergonomics' ); ?></h3>
						<p class="description"><?php esc_html_e( 'Индекс города собирается по городским метрикам, нормализации показателей и активной формуле E. Привязка CSV ниже задаёт источники рядов из БД для каждого базового city-сигнала.', 'worldstat-ergonomics' ); ?></p>
						<table class="form-table">
							<tr>
								<th scope="row"><label for="wsergo_city_methodology_version"><?php esc_html_e( 'Версия методики (город)', 'worldstat-ergonomics' ); ?></label></th>
								<td>
									<input id="wsergo_city_methodology_version" type="text" value="<?php echo esc_attr( get_option( 'wsergo_methodology_version', '1.2' ) ); ?>" class="regular-text" readonly />
									<p class="description"><?php esc_html_e( 'Сейчас используется общая версия методики; отдельная версия для города может быть добавлена позже.', 'worldstat-ergonomics' ); ?></p>
								</td>
							</tr>
						</table>

						<h3><?php esc_html_e( 'Привязка городских метрик к CSV из базы', 'worldstat-ergonomics' ); ?></h3>
						<p class="description"><?php esc_html_e( 'Назначьте для каждой базовой метрики города файл из wsp_csv_datasets. Это позволяет явно контролировать, из какого CSV берётся ряд.', 'worldstat-ergonomics' ); ?></p>
						<?php if ( empty( $csv_files ) ) : ?>
							<p class="description"><?php esc_html_e( 'Нет записей CSV в базе. Загрузите файлы в World Statistics → Данные CSV.', 'worldstat-ergonomics' ); ?></p>
						<?php else : ?>
							<table class="widefat striped">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Городская метрика', 'worldstat-ergonomics' ); ?></th>
										<th><?php esc_html_e( 'Файл из БД (wsp_csv_datasets)', 'worldstat-ergonomics' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( WSErgo_Settings::city_bindable_metric_keys() as $mk ) : ?>
										<?php $bound = isset( $city_csv_bindings[ $mk ] ) ? (int) $city_csv_bindings[ $mk ] : 0; ?>
										<tr>
											<td><code><?php echo esc_html( $mk ); ?></code></td>
											<td>
												<select name="<?php echo esc_attr( WSErgo_Settings::OPTION_CITY_CSV_BINDINGS ); ?>[<?php echo esc_attr( $mk ); ?>]" class="widefat">
													<option value="0"><?php esc_html_e( '— авто/не задано —', 'worldstat-ergonomics' ); ?></option>
													<?php foreach ( $csv_files as $frow ) : ?>
														<?php
														$kid = (string) ( $frow['dataset_kind'] ?? '' );
														if ( class_exists( 'WorldStat_Uploaded_Csv' ) && ! WorldStat_Uploaded_Csv::is_calculation_source_kind( $kid ) ) {
															continue;
														}
														$fid = (int) ( $frow['id'] ?? 0 );
														$fn  = (string) ( $frow['name'] ?? '' );
														?>
														<option value="<?php echo esc_attr( (string) $fid ); ?>" <?php selected( $bound, $fid ); ?>><?php echo esc_html( '#' . $fid . ' — ' . $fn . ' (' . $kid . ')' ); ?></option>
													<?php endforeach; ?>
												</select>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>
						<hr />
						<h3><?php esc_html_e( 'Сопоставление полей города и показателей эргономики', 'worldstat-ergonomics' ); ?></h3>
						<p class="description"><?php esc_html_e( 'Укажите, какие поля из данных города участвуют в расчёте индикаторов (включая данные Blocks & Roads).', 'worldstat-ergonomics' ); ?></p>
						<?php if ( empty( $source_choices ) ) : ?>
							<p class="description"><?php esc_html_e( 'Справочник полей города недоступен (плагин Cities не активен).', 'worldstat-ergonomics' ); ?></p>
						<?php else : ?>
							<p>
								<button type="button" class="button" id="wsergo-add-city-map-row"><?php esc_html_e( 'Добавить сопоставление', 'worldstat-ergonomics' ); ?></button>
							</p>
							<table class="widefat striped" id="wsergo-city-map-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Поле данных города', 'worldstat-ergonomics' ); ?></th>
										<th><?php esc_html_e( 'ID показателя эргономики', 'worldstat-ergonomics' ); ?></th>
									</tr>
								</thead>
								<tbody id="wsergo-city-map-rows">
									<?php
									$cm_rows = $city_field_map;
									if ( empty( $cm_rows ) ) {
										$cm_rows[] = [ 'source' => '', 'indicator_id' => '' ];
									}
									// UI fallback: pre-render enough rows for quick setup
									// when dynamic add button is blocked by browser scripts.
									$min_map_rows = 6;
									while ( count( $cm_rows ) < $min_map_rows ) {
										$cm_rows[] = [ 'source' => '', 'indicator_id' => '' ];
									}
									foreach ( array_values( $cm_rows ) as $cidx => $crow ) :
										?>
										<tr>
											<td>
												<select name="wsergo_city_field_map[<?php echo esc_attr( (string) $cidx ); ?>][source]" class="widefat">
													<option value=""><?php esc_html_e( '— выберите —', 'worldstat-ergonomics' ); ?></option>
													<?php foreach ( $source_choices as $sval => $slab ) : ?>
														<option value="<?php echo esc_attr( $sval ); ?>" <?php selected( isset( $crow['source'] ) ? (string) $crow['source'] : '', $sval ); ?>><?php echo esc_html( $slab ); ?></option>
													<?php endforeach; ?>
												</select>
											</td>
											<td>
												<select name="wsergo_city_field_map[<?php echo esc_attr( (string) $cidx ); ?>][indicator_id]" class="widefat">
													<option value=""><?php esc_html_e( '— выберите —', 'worldstat-ergonomics' ); ?></option>
													<?php foreach ( WSErgo_Indicators::get_definitions() as $idef ) : ?>
														<option value="<?php echo esc_attr( $idef['id'] ); ?>" <?php selected( isset( $crow['indicator_id'] ) ? (string) $crow['indicator_id'] : '', $idef['id'] ); ?>><?php echo esc_html( $idef['label'] . ' (' . $idef['id'] . ')' ); ?></option>
													<?php endforeach; ?>
												</select>
											</td>
										</tr>
									<?php endforeach; ?>
									<tr class="wsergo-city-map-template" style="display:none">
										<td>
											<select name="wsergo_city_field_map[][source]" class="widefat">
												<option value=""><?php esc_html_e( '— выберите —', 'worldstat-ergonomics' ); ?></option>
												<?php foreach ( $source_choices as $sval => $slab ) : ?>
													<option value="<?php echo esc_attr( $sval ); ?>"><?php echo esc_html( $slab ); ?></option>
												<?php endforeach; ?>
											</select>
										</td>
										<td>
											<select name="wsergo_city_field_map[][indicator_id]" class="widefat">
												<option value=""><?php esc_html_e( '— выберите —', 'worldstat-ergonomics' ); ?></option>
												<?php foreach ( WSErgo_Indicators::get_definitions() as $idef ) : ?>
													<option value="<?php echo esc_attr( $idef['id'] ); ?>"><?php echo esc_html( $idef['label'] . ' (' . $idef['id'] . ')' ); ?></option>
												<?php endforeach; ?>
											</select>
										</td>
									</tr>
								</tbody>
							</table>
						<?php endif; ?>
						<p class="description" style="margin-top:10px;"><?php esc_html_e( 'После обновления сопоставлений запустите массовый пересчёт на странице World Statistics → Города.', 'worldstat-ergonomics' ); ?></p>
					</div>

					<div id="city-tab-formula" class="wsergo-city-tab-panel" style="display:none;">
						<p class="description"><?php esc_html_e( 'Задайте активную модель и формулу DSL для расчёта E на городском уровне.', 'worldstat-ergonomics' ); ?></p>
						<p class="description">
							<?php esc_html_e( 'Переменные: F, S, C, L, O, M — баллы осей 0–100; w_* — веса из вкладки «Измерения»; i_* — нормализованные показатели; k_* — коэффициенты ниже.', 'worldstat-ergonomics' ); ?>
						</p>
						<p class="description">
							<?php esc_html_e( 'Допустимые идентификаторы:', 'worldstat-ergonomics' ); ?>
							<code><?php echo esc_html( $allowed_txt ); ?></code>
						</p>
						<table class="form-table">
							<tr>
								<th scope="row"><?php esc_html_e( 'Активная модель расчёта E', 'worldstat-ergonomics' ); ?></th>
								<td>
									<select name="<?php echo esc_attr( WSErgo_Settings::OPTION_ACTIVE_MODEL ); ?>">
										<?php foreach ( $models as $m ) : ?>
											<option value="<?php echo esc_attr( $m['id'] ); ?>" <?php selected( $active_id, $m['id'] ); ?>><?php echo esc_html( $m['name'] ); ?></option>
										<?php endforeach; ?>
									</select>
									<?php if ( $active_model ) : ?>
										<p class="description"><?php echo esc_html( $active_model['leaf_formula'] !== '' ? $active_model['leaf_formula'] : __( 'Пустая формула: взвешенное среднее по шести осям.', 'worldstat-ergonomics' ) ); ?></p>
									<?php endif; ?>
								</td>
							</tr>
						</table>
						<p>
							<label for="wsergo_city_test_formula_inline"><?php esc_html_e( 'Проверка формулы (тестовые 50 по всем осям)', 'worldstat-ergonomics' ); ?></label><br />
							<textarea id="wsergo_city_test_formula_inline" class="large-text" rows="2" placeholder="(F*w_functionality + S*w_safety + ...) * k_default"></textarea><br />
							<button type="button" class="button" id="wsergo-city-test-formula-btn"><?php esc_html_e( 'Проверить', 'worldstat-ergonomics' ); ?></button>
							<span id="wsergo-city-formula-test-result" class="description"></span>
						</p>
						<table class="widefat striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'ID', 'worldstat-ergonomics' ); ?></th>
									<th><?php esc_html_e( 'Название', 'worldstat-ergonomics' ); ?></th>
									<th><?php esc_html_e( 'Формула сводного E (пусто = классика)', 'worldstat-ergonomics' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( array_values( $models ) as $i => $m ) : ?>
									<tr>
										<td><input type="text" name="wsergo_models[<?php echo esc_attr( (string) $i ); ?>][id]" value="<?php echo esc_attr( $m['id'] ); ?>" class="regular-text" /></td>
										<td><input type="text" name="wsergo_models[<?php echo esc_attr( (string) $i ); ?>][name]" value="<?php echo esc_attr( $m['name'] ); ?>" class="regular-text" /></td>
										<td><textarea name="wsergo_models[<?php echo esc_attr( (string) $i ); ?>][leaf_formula]" class="large-text" rows="2"><?php echo esc_textarea( $m['leaf_formula'] ); ?></textarea></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>

					<div id="city-tab-coeff" class="wsergo-city-tab-panel" style="display:none;">
						<p class="description"><?php esc_html_e( 'Коэффициенты k_* для DSL-формулы городского E.', 'worldstat-ergonomics' ); ?></p>
						<table class="form-table">
							<?php foreach ( $coeffs as $k => $v ) : ?>
								<tr>
									<th scope="row"><label><?php echo esc_html( $k ); ?></label></th>
									<td><input type="text" name="wsergo_coefficients[<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( (string) $v ); ?>" class="small-text" /></td>
								</tr>
							<?php endforeach; ?>
						</table>
					</div>

					<div id="city-tab-agg" class="wsergo-city-tab-panel" style="display:none;">
						<p class="description"><?php esc_html_e( 'Правила агрегации уровней в городе (как в старой версии): помещения → здания → кварталы → город.', 'worldstat-ergonomics' ); ?></p>
						<table class="form-table">
							<tr>
								<th><?php esc_html_e( 'Помещения → здание', 'worldstat-ergonomics' ); ?></th>
								<td>
									<select name="wsergo_aggregation[room_to_building]">
										<option value="mean" <?php selected( $agg['room_to_building'], 'mean' ); ?>><?php esc_html_e( 'Среднее', 'worldstat-ergonomics' ); ?></option>
										<option value="weighted_area" <?php selected( $agg['room_to_building'], 'weighted_area' ); ?>><?php esc_html_e( 'По площади', 'worldstat-ergonomics' ); ?></option>
										<option value="median" <?php selected( $agg['room_to_building'], 'median' ); ?>><?php esc_html_e( 'Медиана', 'worldstat-ergonomics' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Вес зданий / придомовых в квартале', 'worldstat-ergonomics' ); ?></th>
								<td>
									<input type="text" name="wsergo_aggregation[w_building_in_district]" value="<?php echo esc_attr( (string) $agg['w_building_in_district'] ); ?>" class="small-text" />
									/
									<input type="text" name="wsergo_aggregation[w_yard_in_district]" value="<?php echo esc_attr( (string) $agg['w_yard_in_district'] ); ?>" class="small-text" />
								</td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Кварталы → город', 'worldstat-ergonomics' ); ?></th>
								<td>
									<select name="wsergo_aggregation[district_to_city]">
										<option value="mean" <?php selected( $agg['district_to_city'], 'mean' ); ?>><?php esc_html_e( 'Среднее по кварталам', 'worldstat-ergonomics' ); ?></option>
										<option value="buildings_weighted" <?php selected( $agg['district_to_city'], 'buildings_weighted' ); ?>><?php esc_html_e( 'Взвешено по числу зданий и участков', 'worldstat-ergonomics' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Города → регион', 'worldstat-ergonomics' ); ?></th>
								<td>
									<select name="wsergo_aggregation[city_to_region]">
										<option value="pop_weighted" <?php selected( $agg['city_to_region'], 'pop_weighted' ); ?>><?php esc_html_e( 'По населению T3', 'worldstat-ergonomics' ); ?></option>
										<option value="mean" <?php selected( $agg['city_to_region'], 'mean' ); ?>><?php esc_html_e( 'Среднее', 'worldstat-ergonomics' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Регионы → страна', 'worldstat-ergonomics' ); ?></th>
								<td>
									<select name="wsergo_aggregation[region_to_country]">
										<option value="pop_weighted" <?php selected( $agg['region_to_country'], 'pop_weighted' ); ?>><?php esc_html_e( 'По населению', 'worldstat-ergonomics' ); ?></option>
										<option value="mean" <?php selected( $agg['region_to_country'], 'mean' ); ?>><?php esc_html_e( 'Среднее по регионам', 'worldstat-ergonomics' ); ?></option>
									</select>
								</td>
							</tr>
						</table>
					</div>
					<?php submit_button( __( 'Сохранить настройки города', 'worldstat-ergonomics' ) ); ?>
				</form>
			</div>
