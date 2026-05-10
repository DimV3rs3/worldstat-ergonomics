<?php
/**
 * Блок «шесть макро-критериев» на вкладке «Формула (город)».
 * Подключается из {@see WSErgo_Admin::render_settings_page()} — использует локальные переменные метода
 * ($wsp_csv_has_datasets, $signals_ui_city, $macro_axes_six, …).
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
					<?php
					$wsergo_city_macro_formula_visible = $wsp_csv_has_datasets || ! empty( $signals_ui_city );
					?>
					<?php if ( $wsergo_city_macro_formula_visible ) : ?>
					<h3><?php esc_html_e( 'Макро: шесть критериев городского индекса', 'worldstat-ergonomics' ); ?></h3>
					<?php if ( ! $wsp_csv_has_datasets && ! empty( $signals_ui_city ) ) : ?>
						<div class="notice notice-info inline" style="margin:0 0 10px 0;padding:10px 12px;">
							<p style="margin:0;"><?php esc_html_e( 'В хранилище платформы пока нет макро-наборов (столбцы из загруженных CSV). Столбец «Пример данных» ниже для таких ключей может быть пустым; загрузку наборов вы можете делать с привычного места (часто — вкладка «Данные» города на этой же странице или раздел «Данные CSV»). Веса и инверсии по матрице города здесь задаются в любом случае.', 'worldstat-ergonomics' ); ?></p>
						</div>
					<?php endif; ?>
					<p class="description">
						<?php esc_html_e( 'Пока на «Данные» города ни одна галочка не стоит, для всех критериев действует встроенная методика. После отметок в городской матрице и сохранения здесь появятся веса; состав менять нельзя — только веса.', 'worldstat-ergonomics' ); ?>
						<a href="#tab-city-data" class="wsergo-tab-deep-link"><?php esc_html_e( 'К матрице отбора города', 'worldstat-ergonomics' ); ?></a>
					</p>
					<?php if ( is_array( $ref_city_macro_raw_row ) && ! empty( $ref_city_macro_raw_row ) && $ref_city_macro_example_note !== '' ) : ?>
						<p class="description"><strong><?php esc_html_e( 'Пример данных', 'worldstat-ergonomics' ); ?>:</strong> <?php echo esc_html( $ref_city_macro_example_note ); ?> — <?php esc_html_e( 'значения из городского макро-CSV для ISO2 эталонной страны (кэш расчёта).', 'worldstat-ergonomics' ); ?></p>
					<?php elseif ( $macro_ref_country_id > 0 ) : ?>
						<p class="description"><?php esc_html_e( 'Нет сырого макро-ряда для ISO2 эталонной страны: проверьте год/CSV и привязки столбцов или выберите другую эталонную страну на вкладке «Данные» страны.', 'worldstat-ergonomics' ); ?></p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'Чтобы заполнить столбец «Пример данных», выберите эталонную страну на вкладке «Данные» страны и сохраните настройки.', 'worldstat-ergonomics' ); ?></p>
					<?php endif; ?>
						<?php
						$criteria_fold_i_city = 0;
						foreach ( $macro_axes_six as $ax_key ) :
							++$criteria_fold_i_city;
							$ax_lab = isset( $macro_axis_labels[ $ax_key ] ) ? $macro_axis_labels[ $ax_key ] : $ax_key;
							$picked_c = [];
							foreach ( $city_macro_criteria_matrix as $sig => $axes_map ) {
								if ( ! empty( $axes_map[ $ax_key ] ) ) {
									$picked_c[] = $sig;
								}
							}
							sort( $picked_c, SORT_STRING );
							$rows_ax_c = isset( $city_macro_axis_resolved[ $ax_key ] ) ? $city_macro_axis_resolved[ $ax_key ] : [];
							?>
					<details class="wsergo-macro-crit" data-macro-axis="<?php echo esc_attr( $ax_key ); ?>" <?php echo 1 === $criteria_fold_i_city ? 'open' : ''; ?>>
						<summary class="wsergo-macro-crit__bar">
							<span class="dashicons dashicons-arrow-right-alt2 wsergo-macro-crit__chev" aria-hidden="true"></span>
							<span class="wsergo-macro-crit__title"><?php echo esc_html( $ax_lab ); ?></span>
							<span class="wsergo-macro-crit__e">
								<label>
									<span class="description"><?php esc_html_e( 'Вес критерия в E', 'worldstat-ergonomics' ); ?></span>
									<input type="text" class="small-text" name="<?php echo esc_attr( WSErgo_Settings::OPTION_CITY_MACRO_E_AXIS_WEIGHTS ); ?>[<?php echo esc_attr( $ax_key ); ?>]" value="<?php echo esc_attr( (string) ( $city_macro_e_w[ $ax_key ] ?? '' ) ); ?>" inputmode="decimal" onclick="event.stopPropagation();" onkeydown="event.stopPropagation();" />
								</label>
							</span>
							<span class="description wsergo-macro-sum-line">
								<span class="description"><?php esc_html_e( 'Σ весов параметров (после распределения)', 'worldstat-ergonomics' ); ?></span>
								<strong class="wsergo-macro-sum-total" data-macro-axis="<?php echo esc_attr( $ax_key ); ?>"><?php echo esc_html( sprintf( '%.4f', array_sum( array_map( static function ( $r ) { return (float) ( $r['weight'] ?? 0 ); }, $rows_ax_c ) ) ) ); ?></strong>
							</span>
						</summary>
						<div style="padding:0 14px 16px 14px;">
							<?php if ( count( $picked_c ) === 0 ) : ?>
								<p class="description"><?php esc_html_e( 'Нет отмеченных параметров — для этого критерия подставляется встроенная методика (ниже состав по умолчанию).', 'worldstat-ergonomics' ); ?></p>
								<?php if ( ! empty( $rows_ax_c ) ) : ?>
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
										<?php foreach ( $rows_ax_c as $rowt ) : ?>
											<?php
											$rsig = isset( $rowt['signal'] ) ? (string) $rowt['signal'] : '';
											$rlab = is_callable( $city_macro_label_ru ) ? $city_macro_label_ru( $rsig ) : $rsig;
											?>
										<tr>
											<td class="col-macro-sig"><?php echo esc_html( $rlab ); ?><br /><code class="description"><?php echo esc_html( $rsig ); ?></code></td>
											<td class="col-macro-w"><?php echo esc_html( sprintf( '%.4f', isset( $rowt['weight'] ) ? (float) $rowt['weight'] : 0.0 ) ); ?></td>
											<td class="col-macro-inv"><?php echo ! empty( $rowt['invert'] ) ? esc_html__( 'да', 'worldstat-ergonomics' ) : esc_html__( 'нет', 'worldstat-ergonomics' ); ?></td>
											<td class="col-macro-ex"><code class="description"><?php echo esc_html( $this->format_macro_signal_example_display( $ref_city_macro_raw_row, $rsig ) ); ?></code></td>
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
										<?php foreach ( $picked_c as $psig ) : ?>
											<?php
											$row_match = null;
											foreach ( $rows_ax_c as $rr ) {
												if ( (string) ( $rr['signal'] ?? '' ) === $psig ) {
													$row_match = $rr;
													break;
												}
											}
											$w_stored = isset( $city_macro_criteria_w[ $psig ][ $ax_key ] ) ? (string) $city_macro_criteria_w[ $psig ][ $ax_key ] : '';
											$plab     = is_callable( $city_macro_label_ru ) ? $city_macro_label_ru( $psig ) : $psig;
											if ( isset( $city_macro_criteria_inv[ $psig ] ) && is_array( $city_macro_criteria_inv[ $psig ] ) && array_key_exists( $ax_key, $city_macro_criteria_inv[ $psig ] ) ) {
												$inv_checked = (bool) $city_macro_criteria_inv[ $psig ][ $ax_key ];
											} else {
												$inv_checked = $row_match && ! empty( $row_match['invert'] );
											}
											$inv_name = WSErgo_Settings::OPTION_CITY_MACRO_CRITERIA_INVERTS . '[' . $psig . '][' . $ax_key . ']';
											?>
										<tr>
											<td class="col-macro-sig">
												<strong><?php echo esc_html( $plab ); ?></strong>
												<br /><code class="description"><?php echo esc_html( $psig ); ?></code>
											</td>
											<td class="col-macro-w">
												<input type="text" class="small-text wsergo-macro-w-input" data-macro-signal="<?php echo esc_attr( $psig ); ?>" name="<?php echo esc_attr( WSErgo_Settings::OPTION_CITY_MACRO_CRITERIA_WEIGHTS ); ?>[<?php echo esc_attr( $psig ); ?>][<?php echo esc_attr( $ax_key ); ?>]" value="<?php echo esc_attr( $w_stored ); ?>" inputmode="decimal" placeholder="<?php esc_attr_e( 'авто', 'worldstat-ergonomics' ); ?>" autocomplete="off" />
											</td>
											<td class="col-macro-inv">
												<input type="hidden" name="<?php echo esc_attr( $inv_name ); ?>" value="0" />
												<label><input type="checkbox" name="<?php echo esc_attr( $inv_name ); ?>" value="1" <?php checked( $inv_checked ); ?> /> <?php esc_html_e( '1−x', 'worldstat-ergonomics' ); ?></label>
											</td>
											<td class="col-macro-ex"><code class="description"><?php echo esc_html( $this->format_macro_signal_example_display( $ref_city_macro_raw_row, $psig ) ); ?></code></td>
										</tr>
										<?php endforeach; ?>
									</tbody>
									<tfoot>
										<tr>
											<td class="description col-macro-sig"><?php esc_html_e( 'Σ весов (после распределения)', 'worldstat-ergonomics' ); ?></td>
											<td class="col-macro-w"><strong class="wsergo-macro-sum-total" data-macro-axis="<?php echo esc_attr( $ax_key ); ?>"><?php echo esc_html( sprintf( '%.4f', array_sum( array_map( static function ( $r ) { return (float) ( $r['weight'] ?? 0 ); }, $rows_ax_c ) ) ) ); ?></strong></td>
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
						<h3><?php esc_html_e( 'Макро: шесть критериев городского индекса', 'worldstat-ergonomics' ); ?></h3>
						<div class="notice notice-warning inline" style="margin:10px 0;padding:12px;">
							<p style="margin:0;">
								<?php esc_html_e( 'Этот блок скрыт: нет ни одного признака для городского макро (ни CSV, ни карты полей, ни мета городов, ни доп. ключей/калькулятора). Задайте источники на вкладке «Данные» города или загрузите CSV.', 'worldstat-ergonomics' ); ?>
							</p>
							<p style="margin:.65em 0 0;">
								<a href="#tab-city-data" class="wsergo-tab-deep-link"><?php esc_html_e( 'Вкладка «Данные» города (матрица, калькулятор)', 'worldstat-ergonomics' ); ?></a>
								<?php if ( current_user_can( 'manage_options' ) ) : ?>
									&nbsp;·&nbsp;
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=worldstat-csv' ) ); ?>"><?php esc_html_e( 'Каталог «Данные CSV» платформы', 'worldstat-ergonomics' ); ?></a>
								<?php endif; ?>
							</p>
						</div>
					<?php endif; ?>
