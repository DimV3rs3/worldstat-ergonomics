/**
 * Вкладка «Территория»: график, веса критериев, пересчёт районов.
 */
( function ( $ ) {
	'use strict';

	function initDistrictTab() {
		var cfg = window.wsergoDistrictTab || {};
		var ajaxUrl = cfg.ajaxUrl || ( typeof ajaxurl !== 'undefined' ? ajaxurl : '' );
		var nonce =
			cfg.nonce ||
			( window.wsergoAdminSettings && window.wsergoAdminSettings.formulaNonce
				? window.wsergoAdminSettings.formulaNonce
				: '' );

		var ctx = document.getElementById( 'wsergo-training-chart' );
		if ( ctx && typeof Chart !== 'undefined' ) {
			new Chart( ctx.getContext( '2d' ), {
				type: 'line',
				data: {
					labels: [ '1', '20', '40', '60', '80', '100' ],
					datasets: [
						{
							label: 'Loss',
							data: [ 0.45, 0.28, 0.19, 0.14, 0.11, 0.09 ],
							borderColor: '#ef4444',
							backgroundColor: 'rgba(239, 68, 68, 0.1)',
							fill: true,
							tension: 0.4,
						},
						{
							label: 'Accuracy',
							data: [ 0.62, 0.71, 0.78, 0.83, 0.86, 0.89 ],
							borderColor: '#10b981',
							backgroundColor: 'rgba(16, 185, 129, 0.1)',
							fill: true,
							tension: 0.4,
						},
					],
				},
				options: {
					responsive: true,
					maintainAspectRatio: true,
					plugins: {
						legend: { position: 'top' },
						tooltip: { mode: 'index', intersect: false },
					},
				},
			} );
		}

		$( '#wsergo-save-criteria-weights' ).on( 'click', function ( e ) {
			e.preventDefault();
			var weights = {};
			$( '.criterion-weight' ).each( function () {
				weights[ $( this ).data( 'criterion' ) ] = parseFloat( $( this ).val() );
			} );
			$.post( ajaxUrl, {
				action: 'wsergo_save_district_criteria_weights',
				nonce: nonce,
				weights: weights,
			} ).done( function ( response ) {
				if ( response.success ) {
					window.alert( cfg.i18n && cfg.i18n.saved ? cfg.i18n.saved : 'OK' );
				} else {
					window.alert( response.data || 'Error' );
				}
			} );
		} );

		$( '#wsergo-retrain-neural' ).on( 'click', function ( e ) {
			e.preventDefault();
			if (
				! window.confirm(
					cfg.i18n && cfg.i18n.confirmRetrain
						? cfg.i18n.confirmRetrain
						: 'Пересчитать все районы?'
				)
			) {
				return;
			}
			$( '#wsergo-retrain-neural' ).prop( 'disabled', true );
			$( '#wsergo-retrain-status' ).text(
				cfg.i18n && cfg.i18n.running ? cfg.i18n.running : '…'
			);
			$.post( ajaxUrl, {
				action: 'wsergo_retrain_neural_networks',
				nonce: nonce,
			} )
				.done( function ( response ) {
					if ( response.success ) {
						$( '#wsergo-retrain-status' ).text(
							response.data && response.data.message
								? response.data.message
								: 'OK'
						);
						window.setTimeout( function () {
							window.location.reload();
						}, 1500 );
					} else {
						$( '#wsergo-retrain-status' ).text( response.data || 'Error' );
					}
					$( '#wsergo-retrain-neural' ).prop( 'disabled', false );
				} )
				.fail( function () {
					$( '#wsergo-retrain-status' ).text( 'AJAX error' );
					$( '#wsergo-retrain-neural' ).prop( 'disabled', false );
				} );
		} );
	}

	$( initDistrictTab );
} )( jQuery );
