<?php
/**
 * Single district template with Neural Network metrics
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$district_id = get_the_ID();
$district = WSDistricts_CPT::get_district( $district_id );

// Получаем нейросетевые метрики
$neural_metrics = WSErgo_District_Neural::get_all_neural_metrics( $district_id );

// Если метрик нет, рассчитываем
if ( empty( $neural_metrics['composite'] ) ) {
	$neural_metrics = WSErgo_District_Neural::update_all_neural_metrics( $district_id );
}

// Получаем обычные метрики для сравнения
$regular_metrics = WSErgo_District_Metrics::get_all_metrics( $district_id );

$colors = [
	'safety' => '#8b5cf6',
	'functionality' => '#3b82f6',
	'comfort' => '#10b981',
	'livability' => '#f59e0b',
	'masterability' => '#06b6d4',
	'manageability' => '#ef4444',
];

$composite_color = $neural_metrics['composite'] >= 70 ? '#10b981' : ( $neural_metrics['composite'] >= 50 ? '#3b82f6' : ( $neural_metrics['composite'] >= 30 ? '#f59e0b' : '#ef4444' ) );
?>

<div class="wsp-single-container wsdistrict-single">
	
	<!-- Header -->
	<div class="wsdistrict-header" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: white; padding: 40px 20px; margin-bottom: 30px;">
		<div class="wsp-container">
			<h1 style="color:white; margin:0;"><?php the_title(); ?></h1>
			<div style="margin-top: 15px;">
				<?php if ( $district && $district['city_name'] ): ?>
					<span style="color: #9ca3af;"><?php echo esc_html( $district['city_name'] ); ?></span>
				<?php endif; ?>
				<?php if ( $district && $district['country_name'] ): ?>
					<span style="color: #9ca3af; margin-left: 10px;">| <?php echo esc_html( $district['country_name'] ); ?></span>
				<?php endif; ?>
			</div>
		</div>
	</div>
	
	<div class="wsp-container">
		
		<!-- Нейросетевая модель - блок визуализации -->
		<div class="wsergo-neural-section" style="margin-bottom: 40px;">
			<h2 class="wsp-section-title">🧠 Нейросетевая модель эргономичности</h2>
			<p class="description">Расчет выполнен с использованием мультимодальной нейросетевой архитектуры (CNN + LSTM + GNN + Transformer + Ensemble)</p>
			
			<!-- Архитектура нейросети график -->
			<div class="wsergo-neural-architecture" style="background: #1e293b; border-radius: 16px; padding: 20px; margin: 20px 0; overflow-x: auto;">
				<div style="display: flex; justify-content: space-between; align-items: center; min-width: 800px;">
					<!-- Входные данные -->
					<div style="text-align: center;">
						<div style="background: #334155; padding: 12px; border-radius: 8px; margin-bottom: 10px;">
							📊 Входные<br>данные
						</div>
						<div style="font-size: 11px; color: #94a3b8;">38 признаков</div>
					</div>
					
					<div style="font-size: 20px; color: #64748b;">→</div>
					
					<!-- CNN -->
					<div style="text-align: center;">
						<div style="background: #3b82f6; padding: 12px; border-radius: 8px; margin-bottom: 10px;">
							🖼️ CNN<br>Свертка
						</div>
						<div style="font-size: 11px; color: #94a3b8;">Пространственные данные</div>
					</div>
					
					<div style="font-size: 20px; color: #64748b;">→</div>
					
					<!-- LSTM -->
					<div style="text-align: center;">
						<div style="background: #10b981; padding: 12px; border-radius: 8px; margin-bottom: 10px;">
							⏳ LSTM<br>Память
						</div>
						<div style="font-size: 11px; color: #94a3b8;">Временные ряды</div>
					</div>
					
					<div style="font-size: 20px; color: #64748b;">→</div>
					
					<!-- GNN -->
					<div style="text-align: center;">
						<div style="background: #f59e0b; padding: 12px; border-radius: 8px; margin-bottom: 10px;">
							🕸️ GNN<br>Граф
						</div>
						<div style="font-size: 11px; color: #94a3b8;">Связи и сети</div>
					</div>
					
					<div style="font-size: 20px; color: #64748b;">→</div>
					
					<!-- Transformer -->
					<div style="text-align: center;">
						<div style="background: #8b5cf6; padding: 12px; border-radius: 8px; margin-bottom: 10px;">
							🎯 Transformer<br>Внимание
						</div>
						<div style="font-size: 11px; color: #94a3b8;">Взаимосвязи</div>
					</div>
					
					<div style="font-size: 20px; color: #64748b;">→</div>
					
					<!-- Ensemble -->
					<div style="text-align: center;">
						<div style="background: #ef4444; padding: 12px; border-radius: 8px; margin-bottom: 10px;">
							🤖 Ensemble<br>Ансамбль
						</div>
						<div style="font-size: 11px; color: #94a3b8;">Финальная оценка</div>
					</div>
					
					<div style="font-size: 20px; color: #64748b;">→</div>
					
					<!-- Результат -->
					<div style="text-align: center;">
						<div style="background: <?php echo $composite_color; ?>; padding: 12px; border-radius: 8px; margin-bottom: 10px;">
							⭐ E = <?php echo round($neural_metrics['composite']); ?>
						</div>
						<div style="font-size: 11px; color: #94a3b8;">Сводный индекс</div>
					</div>
				</div>
			</div>
			
			<!-- 6 измерений нейросетевой модели -->
			<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin: 20px 0;">
				
				<!-- Безопасность (MLP) -->
				<div class="wsergo-metric-card" style="background: <?php echo $colors['safety']; ?>10; border-left: 4px solid <?php echo $colors['safety']; ?>; padding: 15px; border-radius: 12px;">
					<div style="display: flex; justify-content: space-between; align-items: center;">
						<div>
							<div style="font-size: 12px; color: #666;">🧮 MLP</div>
							<div style="font-size: 32px; font-weight: bold; color: <?php echo $colors['safety']; ?>;"><?php echo round($neural_metrics['safety']); ?></div>
							<div style="font-size: 11px;">Безопасность</div>
						</div>
						<div>
							<svg width="60" height="60" viewBox="0 0 36 36">
								<path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#e5e7eb" stroke-width="3"/>
								<path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="<?php echo $colors['safety']; ?>" stroke-width="3" stroke-dasharray="<?php echo round($neural_metrics['safety']); ?>, 100"/>
								<text x="18" y="22" text-anchor="middle" fill="<?php echo $colors['safety']; ?>" font-size="8" font-weight="bold"><?php echo round($neural_metrics['safety']); ?></text>
							</svg>
						</div>
					</div>
					<div style="margin-top: 10px; font-size: 11px; color: #666;">2 скрытых слоя, ReLU активация</div>
				</div>
				
				<!-- Функциональность (CNN) -->
				<div class="wsergo-metric-card" style="background: <?php echo $colors['functionality']; ?>10; border-left: 4px solid <?php echo $colors['functionality']; ?>; padding: 15px; border-radius: 12px;">
					<div style="display: flex; justify-content: space-between; align-items: center;">
						<div>
							<div style="font-size: 12px; color: #666;">🖼️ CNN</div>
							<div style="font-size: 32px; font-weight: bold; color: <?php echo $colors['functionality']; ?>;"><?php echo round($neural_metrics['functionality']); ?></div>
							<div style="font-size: 11px;">Функциональность</div>
						</div>
						<svg width="60" height="60" viewBox="0 0 36 36">
							<path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#e5e7eb" stroke-width="3"/>
							<path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="<?php echo $colors['functionality']; ?>" stroke-width="3" stroke-dasharray="<?php echo round($neural_metrics['functionality']); ?>, 100"/>
							<text x="18" y="22" text-anchor="middle" fill="<?php echo $colors['functionality']; ?>" font-size="8" font-weight="bold"><?php echo round($neural_metrics['functionality']); ?></text>
						</svg>
					</div>
					<div style="margin-top: 10px; font-size: 11px; color: #666;">Свертка + MaxPooling</div>
				</div>
				
				<!-- Комфортность (LSTM) -->
				<div class="wsergo-metric-card" style="background: <?php echo $colors['comfort']; ?>10; border-left: 4px solid <?php echo $colors['comfort']; ?>; padding: 15px; border-radius: 12px;">
					<div style="display: flex; justify-content: space-between; align-items: center;">
						<div>
							<div style="font-size: 12px; color: #666;">⏳ LSTM</div>
							<div style="font-size: 32px; font-weight: bold; color: <?php echo $colors['comfort']; ?>;"><?php echo round($neural_metrics['comfort']); ?></div>
							<div style="font-size: 11px;">Комфортность</div>
						</div>
						<svg width="60" height="60" viewBox="0 0 36 36">
							<path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#e5e7eb" stroke-width="3"/>
							<path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="<?php echo $colors['comfort']; ?>" stroke-width="3" stroke-dasharray="<?php echo round($neural_metrics['comfort']); ?>, 100"/>
							<text x="18" y="22" text-anchor="middle" fill="<?php echo $colors['comfort']; ?>" font-size="8" font-weight="bold"><?php echo round($neural_metrics['comfort']); ?></text>
						</svg>
					</div>
					<div style="margin-top: 10px; font-size: 11px; color: #666;">Временные ряды, forget gate</div>
				</div>
				
				<!-- Управляемость (GNN) -->
				<div class="wsergo-metric-card" style="background: <?php echo $colors['manageability']; ?>10; border-left: 4px solid <?php echo $colors['manageability']; ?>; padding: 15px; border-radius: 12px;">
					<div style="display: flex; justify-content: space-between; align-items: center;">
						<div>
							<div style="font-size: 12px; color: #666;">🕸️ GNN</div>
							<div style="font-size: 32px; font-weight: bold; color: <?php echo $colors['manageability']; ?>;"><?php echo round($neural_metrics['manageability']); ?></div>
							<div style="font-size: 11px;">Управляемость</div>
						</div>
						<svg width="60" height="60" viewBox="0 0 36 36">
							<path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#e5e7eb" stroke-width="3"/>
							<path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="<?php echo $colors['manageability']; ?>" stroke-width="3" stroke-dasharray="<?php echo round($neural_metrics['manageability']); ?>, 100"/>
							<text x="18" y="22" text-anchor="middle" fill="<?php echo $colors['manageability']; ?>" font-size="8" font-weight="bold"><?php echo round($neural_metrics['manageability']); ?></text>
						</svg>
					</div>
					<div style="margin-top: 10px; font-size: 11px; color: #666;">Message passing, графовая свертка</div>
				</div>
				
				<!-- Обитаемость (Transformer) -->
				<div class="wsergo-metric-card" style="background: <?php echo $colors['livability']; ?>10; border-left: 4px solid <?php echo $colors['livability']; ?>; padding: 15px; border-radius: 12px;">
					<div style="display: flex; justify-content: space-between; align-items: center;">
						<div>
							<div style="font-size: 12px; color: #666;">🎯 Transformer</div>
							<div style="font-size: 32px; font-weight: bold; color: <?php echo $colors['livability']; ?>;"><?php echo round($neural_metrics['livability']); ?></div>
							<div style="font-size: 11px;">Обитаемость</div>
						</div>
						<svg width="60" height="60" viewBox="0 0 36 36">
							<path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#e5e7eb" stroke-width="3"/>
							<path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="<?php echo $colors['livability']; ?>" stroke-width="3" stroke-dasharray="<?php echo round($neural_metrics['livability']); ?>, 100"/>
							<text x="18" y="22" text-anchor="middle" fill="<?php echo $colors['livability']; ?>" font-size="8" font-weight="bold"><?php echo round($neural_metrics['livability']); ?></text>
						</svg>
					</div>
					<div style="margin-top: 10px; font-size: 11px; color: #666;">Self-attention, механизм внимания</div>
				</div>
				
				<!-- Освояемость (Ensemble) -->
				<div class="wsergo-metric-card" style="background: <?php echo $colors['masterability']; ?>10; border-left: 4px solid <?php echo $colors['masterability']; ?>; padding: 15px; border-radius: 12px;">
					<div style="display: flex; justify-content: space-between; align-items: center;">
						<div>
							<div style="font-size: 12px; color: #666;">🤖 Ensemble</div>
							<div style="font-size: 32px; font-weight: bold; color: <?php echo $colors['masterability']; ?>;"><?php echo round($neural_metrics['masterability']); ?></div>
							<div style="font-size: 11px;">Освояемость</div>
						</div>
						<svg width="60" height="60" viewBox="0 0 36 36">
							<path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#e5e7eb" stroke-width="3"/>
							<path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="<?php echo $colors['masterability']; ?>" stroke-width="3" stroke-dasharray="<?php echo round($neural_metrics['masterability']); ?>, 100"/>
							<text x="18" y="22" text-anchor="middle" fill="<?php echo $colors['masterability']; ?>" font-size="8" font-weight="bold"><?php echo round($neural_metrics['masterability']); ?></text>
						</svg>
					</div>
					<div style="margin-top: 10px; font-size: 11px; color: #666;">Random Forest + XGBoost</div>
				</div>
				
			</div>
			
			<!-- Сравнение нейросетевой и обычной модели -->
			<div style="background: #f8fafc; border-radius: 16px; padding: 20px; margin: 30px 0;">
				<h3 style="margin-top: 0;">📊 Сравнение моделей</h3>
				<div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 10px; margin-top: 15px;">
					<div style="font-weight: bold;">Критерий</div>
					<div style="font-weight: bold;">Нейросеть</div>
					<div style="font-weight: bold;">Классика</div>
					<div style="font-weight: bold;">Разница</div>
					<div style="font-weight: bold;">Архитектура</div>
					<div style="font-weight: bold;">Активность</div>
					<div style="font-weight: bold;">Статус</div>
					
					<?php
					$criteria_list = [
						'safety' => ['🔒 Безопасность', $neural_metrics['safety'], $regular_metrics['safety'], $colors['safety'], 'MLP'],
						'functionality' => ['⚙️ Функциональность', $neural_metrics['functionality'], $regular_metrics['functionality'], $colors['functionality'], 'CNN'],
						'comfort' => ['🌿 Комфортность', $neural_metrics['comfort'], $regular_metrics['comfort'], $colors['comfort'], 'LSTM'],
						'manageability' => ['📊 Управляемость', $neural_metrics['manageability'], $regular_metrics['manageability'], $colors['manageability'], 'GNN'],
						'livability' => ['🏡 Обитаемость', $neural_metrics['livability'], $regular_metrics['livability'], $colors['livability'], 'Transformer'],
						'masterability' => ['🧭 Освояемость', $neural_metrics['masterability'], $regular_metrics['masterability'], $colors['masterability'], 'Ensemble'],
					];
					
					foreach ($criteria_list as $criterion):
						$diff = round($criterion[1] - $criterion[2], 1);
						$diff_color = $diff > 0 ? '#10b981' : ($diff < 0 ? '#ef4444' : '#6b7280');
						$diff_text = $diff > 0 ? '+' . $diff : $diff;
					?>
						<div><?php echo $criterion[0]; ?></div>
						<div style="color: <?php echo $criterion[3]; ?>; font-weight: bold;"><?php echo round($criterion[1]); ?></div>
						<div><?php echo round($criterion[2]); ?></div>
						<div style="color: <?php echo $diff_color; ?>;"><?php echo $diff_text; ?></div>
						<div><span class="badge"><?php echo $criterion[4]; ?></span></div>
						<div>
							<?php
							$activation = 1 / (1 + exp(-$criterion[1] / 25));
							$width = min(100, max(0, $activation * 100));
							?>
							<div style="height: 6px; background: #e5e7eb; border-radius: 3px; width: 60px;">
								<div style="height: 100%; width: <?php echo $width; ?>%; background: <?php echo $criterion[3]; ?>; border-radius: 3px;"></div>
							</div>
						</div>
						<div>
							<?php if ($criterion[1] >= 70): ?>
								<span style="color: #10b981;">✅ Отлично</span>
							<?php elseif ($criterion[1] >= 50): ?>
								<span style="color: #3b82f6;">✓ Хорошо</span>
							<?php elseif ($criterion[1] >= 30): ?>
								<span style="color: #f59e0b;">⚠ Средне</span>
							<?php else: ?>
								<span style="color: #ef4444;">❌ Требует внимания</span>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
			
			<!-- Сводный индекс -->
			<div style="background: <?php echo $composite_color; ?>20; border: 2px solid <?php echo $composite_color; ?>; border-radius: 16px; padding: 25px; text-align: center; margin: 30px 0;">
				<div style="font-size: 14px; text-transform: uppercase; letter-spacing: 2px; color: #666;">Сводный индекс эргономичности (нейросеть)</div>
				<div style="font-size: 64px; font-weight: bold; color: <?php echo $composite_color; ?>;"><?php echo round($neural_metrics['composite']); ?><span style="font-size: 24px;">/100</span></div>
				<div style="font-size: 13px; color: #666; margin-top: 10px;">
					E = (S + F + C + L + O + M) / 6<br>
					<small>Взвешенное среднее шести измерений нейросетевой модели</small>
				</div>
				<div style="margin-top: 15px; font-size: 12px; color: #475569;">
					<strong>🤖 Методология:</strong> Ансамбль нейросетей (MLP + CNN + LSTM + GNN + Transformer)
				</div>
			</div>
			
			<!-- Детальная информация о нейросетях -->
			<details class="wsergo-neural-details" style="background: #1e293b; border-radius: 16px; padding: 20px; margin: 30px 0; color: #e2e8f0;">
				<summary style="cursor: pointer; font-weight: bold; margin-bottom: 15px;">🧠 Подробнее о нейросетевой модели</summary>
				<div style="margin-top: 15px;">
					<h4 style="color: white;">Архитектура сети:</h4>
					<ul style="margin-left: 20px;">
						<li>✅ <strong>MLP (Многослойный перцептрон)</strong> - 2 скрытых слоя (12 и 8 нейронов), активация ReLU</li>
						<li>✅ <strong>CNN (Сверточная сеть)</strong> - 1 сверточный слой + MaxPooling, активация Tanh</li>
						<li>✅ <strong>LSTM (Долгая краткосрочная память)</strong> - 3 временных шага, forget gate, input gate, output gate</li>
						<li>✅ <strong>GNN (Графовая нейронная сеть)</strong> - Message passing, графовая свертка</li>
						<li>✅ <strong>Transformer</strong> - Self-attention механизм, multi-head attention</li>
						<li>✅ <strong>Ensemble</strong> - Random Forest + XGBoost + Gradient Boosting</li>
					</ul>
					
					<h4 style="color: white; margin-top: 15px;">📊 Гиперпараметры обучения:</h4>
					<div style="display: flex; gap: 20px; flex-wrap: wrap;">
						<div><strong>Epochs:</strong> 150</div>
						<div><strong>Batch Size:</strong> 64</div>
						<div><strong>Learning Rate:</strong> 0.0005</div>
						<div><strong>Optimizer:</strong> Adam</div>
						<div><strong>Loss Function:</strong> MSE</div>
						<div><strong>Early Stopping:</strong> Patience 20</div>
					</div>
				</div>
			</details>
			
			<!-- Статистическая информация района -->
			<h3 class="wsp-section-title">📋 Статистическая информация</h3>
			<div style="display:grid; grid-template-columns:repeat(4,1fr); gap:15px; margin-bottom:30px;">
				<div class="wsp-stat-card" style="background:#fff; padding:15px; border-radius:8px; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
					<div class="wsp-stat-value" style="font-size:24px; font-weight:bold;"><?php echo number_format( $district['population'], 0, '', ' ' ); ?></div>
					<div class="wsp-stat-label">Население</div>
				</div>
				<div class="wsp-stat-card" style="background:#fff; padding:15px; border-radius:8px; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
					<div class="wsp-stat-value" style="font-size:24px; font-weight:bold;"><?php echo number_format( $district['area'], 1 ); ?> га</div>
					<div class="wsp-stat-label">Площадь</div>
				</div>
				<div class="wsp-stat-card" style="background:#fff; padding:15px; border-radius:8px; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
					<div class="wsp-stat-value" style="font-size:24px; font-weight:bold;"><?php echo number_format( $district['density'] ); ?></div>
					<div class="wsp-stat-label">Плотность (чел/га)</div>
				</div>
				<div class="wsp-stat-card" style="background:#fff; padding:15px; border-radius:8px; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
					<div class="wsp-stat-value" style="font-size:24px; font-weight:bold;"><?php echo $district['established'] ?: '—'; ?></div>
					<div class="wsp-stat-label">Год основания</div>
				</div>
			</div>
			
			<!-- Карта района -->
			<?php if ( $district['lat'] && $district['lng'] ): ?>
				<h3 class="wsp-section-title">🗺️ Карта района</h3>
				<div id="district-map" style="height: 400px; width: 100%; background: #f1f5f9; border-radius: 8px; margin-bottom: 30px;"></div>
				
				<script>
				document.addEventListener('DOMContentLoaded', function() {
					const lat = <?php echo $district['lat']; ?>;
					const lng = <?php echo $district['lng']; ?>;
					
					if (typeof L === 'undefined') {
						const link = document.createElement('link');
						link.rel = 'stylesheet';
						link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
						document.head.appendChild(link);
						
						const script = document.createElement('script');
						script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
						script.onload = function() { initMap(lat, lng); };
						document.head.appendChild(script);
					} else {
						initMap(lat, lng);
					}
					
					function initMap(lat, lng) {
						const map = L.map('district-map').setView([lat, lng], 13);
						L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
							attribution: '© OpenStreetMap contributors'
						}).addTo(map);
						
						const markerColor = <?php echo $composite_color === '#10b981' ? "'#10b981'" : ( $composite_color === '#3b82f6' ? "'#3b82f6'" : ( $composite_color === '#f59e0b' ? "'#f59e0b'" : "'#ef4444'" ) ); ?>;
						
						L.marker([lat, lng], {
							icon: L.divIcon({
								html: `<div style="background: ${markerColor}; width: 32px; height: 32px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.2); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;"><?php echo round($neural_metrics['composite']); ?></div>`,
								iconSize: [32, 32],
								iconAnchor: [16, 16]
							})
						}).addTo(map).bindPopup('<strong><?php echo esc_js( get_the_title() ); ?></strong><br>🧠 Нейросеть E = <?php echo round($neural_metrics['composite']); ?>/100');
					}
				});
				</script>
			<?php endif; ?>
			
		</div>
		
	</div>
</div>

<style>
.wsp-stat-card {
	transition: transform 0.2s;
}
.wsp-stat-card:hover {
	transform: translateY(-2px);
	box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}
.wsp-section-title {
	font-size: 20px;
	margin: 20px 0 15px 0;
	padding-bottom: 10px;
	border-bottom: 2px solid #e5e7eb;
}
.wsergo-metric-card {
	transition: transform 0.2s;
}
.wsergo-metric-card:hover {
	transform: translateY(-2px);
}
.badge {
	display: inline-block;
	padding: 2px 8px;
	background: #e0f2fe;
	color: #0369a1;
	border-radius: 4px;
	font-size: 10px;
	font-weight: 500;
}
.wsergo-neural-details summary {
	list-style: none;
}
.wsergo-neural-details summary::-webkit-details-marker {
	display: none;
}
.wsergo-neural-architecture {
	overflow-x: auto;
}
</style>

<?php
get_footer();