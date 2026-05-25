/**
 * Scatter + OLS для межстрановой регрессии (E ~ показатель).
 */
(function ( window ) {
	'use strict';

	function clamp( v, lo, hi ) {
		return Math.max( lo, Math.min( hi, v ) );
	}

	function normalizeHighlight( highlightIso2 ) {
		if ( Array.isArray( highlightIso2 ) ) {
			var out = {};
			var i;
			for ( i = 0; i < highlightIso2.length; i++ ) {
				out[ String( highlightIso2[ i ] || '' ).toUpperCase() ] = true;
			}
			return out;
		}
		if ( highlightIso2 ) {
			return { [ String( highlightIso2 ).toUpperCase() ]: true };
		}
		return {};
	}

	function dataBounds( scatter, slope, intercept ) {
		var i, x, y;
		var xmin = 100;
		var xmax = 0;
		var ymin = 100;
		var ymax = 0;
		for ( i = 0; i < scatter.length; i++ ) {
			x = scatter[ i ].x;
			y = scatter[ i ].y;
			if ( isFinite( x ) ) {
				xmin = Math.min( xmin, x );
				xmax = Math.max( xmax, x );
			}
			if ( isFinite( y ) ) {
				ymin = Math.min( ymin, y );
				ymax = Math.max( ymax, y );
			}
		}
		var xPad = Math.max( 3, ( xmax - xmin ) * 0.08 );
		var yPad = Math.max( 2, ( ymax - ymin ) * 0.08 );
		xmin = clamp( xmin - xPad, 0, 100 );
		xmax = clamp( xmax + xPad, 0, 100 );
		ymin = clamp( ymin - yPad, 0, 100 );
		ymax = clamp( ymax + yPad, 0, 100 );
		if ( isFinite( slope ) && isFinite( intercept ) ) {
			y = slope * xmin + intercept;
			if ( isFinite( y ) ) {
				ymin = Math.min( ymin, y );
				ymax = Math.max( ymax, y );
			}
			y = slope * xmax + intercept;
			if ( isFinite( y ) ) {
				ymin = Math.min( ymin, y );
				ymax = Math.max( ymax, y );
			}
		}
		if ( ymax - ymin < 1e-6 ) {
			ymax = ymin + 1;
		}
		if ( xmax - xmin < 1e-6 ) {
			xmax = xmin + 1;
		}
		return { xmin: xmin, xmax: xmax, ymin: ymin, ymax: ymax };
	}

	/**
	 * @param {HTMLCanvasElement} canvas
	 * @param {HTMLElement|null} cap
	 * @param {object} model
	 * @param {string|string[]} highlightIso2
	 * @param {{xAxis:string,yAxis:string,highlightPrefix?:string}} labels
	 */
	function drawCountryRegressionScatter( canvas, cap, model, highlightIso2, labels ) {
		if ( ! canvas || ! canvas.getContext ) {
			return;
		}
		var ctx = canvas.getContext( '2d' );
		if ( ! ctx || ! model || ! model.scatter || model.scatter.length < 2 ) {
			if ( cap ) {
				cap.textContent = '';
			}
			return;
		}

		var wrap = canvas.parentElement;
		var cssW = Math.max( 280, wrap && wrap.clientWidth ? wrap.clientWidth : 600 );
		var cssH = 280;
		var dpr = window.devicePixelRatio || 1;
		canvas.style.width = cssW + 'px';
		canvas.style.height = cssH + 'px';
		canvas.width = Math.floor( cssW * dpr );
		canvas.height = Math.floor( cssH * dpr );
		ctx.setTransform( dpr, 0, 0, dpr, 0, 0 );
		ctx.clearRect( 0, 0, cssW, cssH );

		var padL = 44;
		var padR = 16;
		var padT = 16;
		var padB = 40;
		var plotW = cssW - padL - padR;
		var plotH = cssH - padT - padB;

		var b = dataBounds( model.scatter, model.slope, model.intercept );
		function tx( x ) {
			return padL + ( ( x - b.xmin ) / ( b.xmax - b.xmin ) ) * plotW;
		}
		function ty( y ) {
			return padT + ( 1 - ( y - b.ymin ) / ( b.ymax - b.ymin ) ) * plotH;
		}

		ctx.fillStyle = '#f9fafb';
		ctx.fillRect( padL, padT, plotW, plotH );
		ctx.strokeStyle = '#d1d5db';
		ctx.lineWidth = 1;
		ctx.strokeRect( padL, padT, plotW, plotH );

		ctx.fillStyle = '#6b7280';
		ctx.font = '11px system-ui,sans-serif';
		ctx.textAlign = 'center';
		ctx.fillText( labels.xAxis || 'x', padL + plotW / 2, cssH - 12 );
		ctx.save();
		ctx.translate( 14, padT + plotH / 2 );
		ctx.rotate( -Math.PI / 2 );
		ctx.fillText( labels.yAxis || 'E', 0, 0 );
		ctx.restore();

		var x0 = b.xmin;
		var x1 = b.xmax;
		var y0 = model.slope * x0 + model.intercept;
		var y1 = model.slope * x1 + model.intercept;
		ctx.strokeStyle = '#2563eb';
		ctx.lineWidth = 2;
		ctx.beginPath();
		ctx.moveTo( tx( x0 ), ty( y0 ) );
		ctx.lineTo( tx( x1 ), ty( y1 ) );
		ctx.stroke();

		var hiMap = normalizeHighlight( highlightIso2 );
		var i, p, hx, hy, hi, color;
		for ( i = 0; i < model.scatter.length; i++ ) {
			p = model.scatter[ i ];
			hx = tx( p.x );
			hy = ty( p.y );
			hi = hiMap[ String( p.iso2 || '' ).toUpperCase() ];
			color = hi ? '#b91c1c' : '#94a3b8';
			if ( hi ) {
				ctx.beginPath();
				ctx.arc( hx, hy, 9, 0, 2 * Math.PI );
				ctx.strokeStyle = color;
				ctx.lineWidth = 2;
				ctx.stroke();
			}
			ctx.beginPath();
			ctx.arc( hx, hy, hi ? 5 : 3.5, 0, 2 * Math.PI );
			ctx.fillStyle = color;
			ctx.fill();
		}

		if ( cap ) {
			var r2 = model.r2 != null ? model.r2 : '—';
			var n = model.n != null ? model.n : model.scatter.length;
			var eq = 'E ≈ ' + String( model.slope ) + '·x + ' + String( model.intercept );
			var capText =
				( model.label || '' ) +
				' — n=' +
				n +
				', R²=' +
				r2 +
				', r=' +
				( model.r != null ? model.r : '—' ) +
				'. ' +
				eq;
			if ( labels.highlightPrefix && Object.keys( hiMap ).length ) {
				var names = [];
				for ( i = 0; i < model.scatter.length; i++ ) {
					p = model.scatter[ i ];
					if ( hiMap[ String( p.iso2 || '' ).toUpperCase() ] ) {
						names.push( p.name || p.iso2 );
					}
				}
				if ( names.length ) {
					capText = labels.highlightPrefix + names.slice( 0, 8 ).join( ', ' )
						+ ( names.length > 8 ? '…' : '' ) + '. ' + capText;
				}
			}
			cap.textContent = capText;
		}
	}

	window.wsergoDrawCountryRegressionScatter = drawCountryRegressionScatter;
})( window );
