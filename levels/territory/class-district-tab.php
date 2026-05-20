<?php
/**
 * Вкладка настроек: модель района (веса, массовый пересчёт).
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_District_Tab {

	/**
	 * Подвкладка внутри «Эргономичность территории».
	 */
	public static function render_territory_nav_link(): void {
		?>
		<a href="#tab-territory-district" class="nav-tab nav-tab-active" data-territory-tab="tab-territory-district">
			<?php esc_html_e( 'Район (квартал)', 'worldstat-ergonomics' ); ?>
		</a>
		<?php
	}

	public static function render_panel_inner(): void {
		$neural_version  = WSERGO_VERSION;
		$last_training   = get_option( 'wsergo_neural_last_training', '—' );
		$training_stats  = get_option(
			'wsergo_neural_training_stats',
			[
				'accuracy'       => 0.87,
				'loss'           => 0.12,
				'epochs'         => 100,
				'batch_size'     => 32,
				'learning_rate'  => 0.001,
			]
		);
		$features = WSErgo_District_Neural::get_neural_features();
		?>
			<h2><?php esc_html_e( 'Модель эргономичности района', 'worldstat-ergonomics' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Эвристический расчёт по мета-полям wsdistrict_* (плагин «Районы»). Без них значения берутся из нейтральных подстановок — для сравнения подключите WorldStat Districts и заполните карточки районов.', 'worldstat-ergonomics' ); ?>
			</p>

			<div class="postbox" style="margin-bottom: 20px;">
				<div class="postbox-header">
					<h2 class="hndle"><?php esc_html_e( 'Признаки (справочно)', 'worldstat-ergonomics' ); ?></h2>
				</div>
				<div class="inside">
					<table class="widefat striped" style="margin-top: 15px;">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Признак', 'worldstat-ergonomics' ); ?></th>
								<th><?php esc_html_e( 'Вес', 'worldstat-ergonomics' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $features as $feature => $weight ) : ?>
								<tr>
									<td><code><?php echo esc_html( (string) $feature ); ?></code></td>
									<td><?php echo esc_html( (string) round( (float) $weight, 2 ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>

			<div class="postbox" style="margin-bottom: 20px;">
				<div class="postbox-header">
					<h2 class="hndle"><?php esc_html_e( 'Статус пересчёта', 'worldstat-ergonomics' ); ?></h2>
				</div>
				<div class="inside">
					<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 25px;">
						<div>
							<span class="description"><?php esc_html_e( 'Версия плагина', 'worldstat-ergonomics' ); ?></span>
							<div style="font-size: 24px; font-weight: bold;"><?php echo esc_html( $neural_version ); ?></div>
						</div>
						<div>
							<span class="description"><?php esc_html_e( 'Последний массовый пересчёт', 'worldstat-ergonomics' ); ?></span>
							<div style="font-size: 18px;"><?php echo esc_html( (string) $last_training ); ?></div>
						</div>
						<div>
							<span class="description">Accuracy</span>
							<div style="font-size: 24px; font-weight: bold; color: #10b981;"><?php echo esc_html( (string) round( (float) $training_stats['accuracy'] * 100, 1 ) ); ?>%</div>
						</div>
						<div>
							<span class="description">Loss</span>
							<div style="font-size: 24px; font-weight: bold; color: #ef4444;"><?php echo esc_html( (string) round( (float) $training_stats['loss'], 3 ) ); ?></div>
						</div>
					</div>
					<p class="description"><?php esc_html_e( 'Показатели accuracy/loss при массовом пересчёте — условные метки для интерфейса; фактически выполняется пересчёт формул по всем записям wsp_district.', 'worldstat-ergonomics' ); ?></p>
				</div>
			</div>

			<div class="postbox" style="margin-bottom: 20px;">
				<div class="postbox-header">
					<h2 class="hndle"><?php esc_html_e( 'Веса шести критериев (сводный индекс)', 'worldstat-ergonomics' ); ?></h2>
				</div>
				<div class="inside">
					<p class="description"><?php esc_html_e( 'Взвешенное среднее; сумма весов может быть любой — нормализация при расчёте выполняется автоматически.', 'worldstat-ergonomics' ); ?></p>
					<?php
					$criteria_weights = get_option(
						'wsergo_district_criteria_weights',
						[
							'safety'        => 0.20,
							'functionality' => 0.20,
							'comfort'       => 0.20,
							'manageability' => 0.10,
							'livability'    => 0.15,
							'masterability' => 0.15,
						]
					);
					?>
					<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin: 20px 0;">
						<div style="background: #f8fafc; padding: 15px; border-radius: 8px;">
							<div style="font-size: 14px; color: #666;"><?php esc_html_e( 'Безопасность', 'worldstat-ergonomics' ); ?></div>
							<input type="number" step="0.01" class="criterion-weight" data-criterion="safety" value="<?php echo esc_attr( (string) $criteria_weights['safety'] ); ?>" style="width: 80px; font-size: 20px;" />
						</div>
						<div style="background: #f8fafc; padding: 15px; border-radius: 8px;">
							<div style="font-size: 14px; color: #666;"><?php esc_html_e( 'Функциональность', 'worldstat-ergonomics' ); ?></div>
							<input type="number" step="0.01" class="criterion-weight" data-criterion="functionality" value="<?php echo esc_attr( (string) $criteria_weights['functionality'] ); ?>" style="width: 80px; font-size: 20px;" />
						</div>
						<div style="background: #f8fafc; padding: 15px; border-radius: 8px;">
							<div style="font-size: 14px; color: #666;"><?php esc_html_e( 'Комфортность', 'worldstat-ergonomics' ); ?></div>
							<input type="number" step="0.01" class="criterion-weight" data-criterion="comfort" value="<?php echo esc_attr( (string) $criteria_weights['comfort'] ); ?>" style="width: 80px; font-size: 20px;" />
						</div>
						<div style="background: #f8fafc; padding: 15px; border-radius: 8px;">
							<div style="font-size: 14px; color: #666;"><?php esc_html_e( 'Управляемость', 'worldstat-ergonomics' ); ?></div>
							<input type="number" step="0.01" class="criterion-weight" data-criterion="manageability" value="<?php echo esc_attr( (string) $criteria_weights['manageability'] ); ?>" style="width: 80px;" />
						</div>
						<div style="background: #f8fafc; padding: 15px; border-radius: 8px;">
							<div style="font-size: 14px; color: #666;"><?php esc_html_e( 'Обитаемость', 'worldstat-ergonomics' ); ?></div>
							<input type="number" step="0.01" class="criterion-weight" data-criterion="livability" value="<?php echo esc_attr( (string) $criteria_weights['livability'] ); ?>" style="width: 80px;" />
						</div>
						<div style="background: #f8fafc; padding: 15px; border-radius: 8px;">
							<div style="font-size: 14px; color: #666;"><?php esc_html_e( 'Освояемость', 'worldstat-ergonomics' ); ?></div>
							<input type="number" step="0.01" class="criterion-weight" data-criterion="masterability" value="<?php echo esc_attr( (string) $criteria_weights['masterability'] ); ?>" style="width: 80px;" />
						</div>
					</div>
					<div style="text-align: right;">
						<button type="button" id="wsergo-save-criteria-weights" class="button button-primary"><?php esc_html_e( 'Сохранить веса', 'worldstat-ergonomics' ); ?></button>
					</div>
				</div>
			</div>

			<div class="postbox">
				<div class="postbox-header">
					<h2 class="hndle"><?php esc_html_e( 'График (пример)', 'worldstat-ergonomics' ); ?></h2>
				</div>
				<div class="inside">
					<canvas id="wsergo-training-chart" style="height: 300px; width: 100%; max-width: 100%;"></canvas>
				</div>
			</div>

			<div class="wsergo-actions" style="margin-top: 20px; text-align: right;">
				<button type="button" id="wsergo-retrain-neural" class="button button-primary">
					<?php esc_html_e( 'Пересчитать все районы', 'worldstat-ergonomics' ); ?>
				</button>
				<span id="wsergo-retrain-status" style="margin-left: 15px;"></span>
			</div>

		<style>
		.criterion-weight { font-size: 24px; font-weight: bold; text-align: center; }
		</style>
		<script>
		jQuery(function($) {
			const ctx = document.getElementById('wsergo-training-chart');
			if (ctx && typeof Chart !== 'undefined') {
				new Chart(ctx.getContext('2d'), {
					type: 'line',
					data: {
						labels: ['1', '20', '40', '60', '80', '100'],
						datasets: [{
							label: 'Loss',
							data: [0.45, 0.28, 0.19, 0.14, 0.11, 0.09],
							borderColor: '#ef4444',
							backgroundColor: 'rgba(239, 68, 68, 0.1)',
							fill: true,
							tension: 0.4
						}, {
							label: 'Accuracy',
							data: [0.62, 0.71, 0.78, 0.83, 0.86, 0.89],
							borderColor: '#10b981',
							backgroundColor: 'rgba(16, 185, 129, 0.1)',
							fill: true,
							tension: 0.4
						}]
					},
					options: {
						responsive: true,
						maintainAspectRatio: true,
						plugins: {
							legend: { position: 'top' },
							tooltip: { mode: 'index', intersect: false }
						}
					}
				});
			}
			$('#wsergo-save-criteria-weights').on('click', function(e) {
				e.preventDefault();
				var weights = {};
				$('.criterion-weight').each(function() {
					weights[$(this).data('criterion')] = parseFloat($(this).val());
				});
				$.ajax({
					url: wsergoAdmin.ajaxUrl,
					type: 'POST',
					data: {
						action: 'wsergo_save_district_criteria_weights',
						nonce: wsergoAdmin.nonce,
						weights: weights
					},
					success: function(response) {
						if (response.success) {
							alert('<?php echo esc_js( __( 'Веса сохранены.', 'worldstat-ergonomics' ) ); ?>');
						} else {
							alert(response.data || 'Error');
						}
					}
				});
			});
			$('#wsergo-retrain-neural').on('click', function(e) {
				e.preventDefault();
				if (!confirm('<?php echo esc_js( __( 'Пересчитать метрики для всех записей wsp_district? На больших сайтах это может занять время.', 'worldstat-ergonomics' ) ); ?>')) return;
				$('#wsergo-retrain-neural').prop('disabled', true);
				$('#wsergo-retrain-status').text('<?php echo esc_js( __( 'Выполняется пересчёт…', 'worldstat-ergonomics' ) ); ?>');
				$.ajax({
					url: wsergoAdmin.ajaxUrl,
					type: 'POST',
					data: {
						action: 'wsergo_retrain_neural_networks',
						nonce: wsergoAdmin.nonce
					},
					success: function(response) {
						if (response.success) {
							$('#wsergo-retrain-status').text(response.data && response.data.message ? response.data.message : 'OK');
							setTimeout(function() { location.reload(); }, 1500);
						} else {
							$('#wsergo-retrain-status').text(response.data || 'Error');
						}
						$('#wsergo-retrain-neural').prop('disabled', false);
					},
					error: function() {
						$('#wsergo-retrain-status').text('AJAX error');
						$('#wsergo-retrain-neural').prop('disabled', false);
					}
				});
			});
		});
		</script>
		<?php
	}
}
