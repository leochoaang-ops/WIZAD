/* WOS app: the live check on Settings. */
( function () {
	var box = document.getElementById( 'wos-live' );
	if ( ! box ) {
		return;
	}
	var val = function ( key ) {
		var el = document.querySelector( '[data-live="' + key + '"]' );
		return el ? parseFloat( el.value ) || 0 : 0;
	};
	var out = function ( key, text, cls ) {
		var el = box.querySelector( '[data-out="' + key + '"]' );
		el.textContent = text;
		if ( cls !== undefined ) {
			el.className = 'wos-live-note ' + cls;
		}
	};
	var money = function ( n ) {
		return '$' + n.toLocaleString( 'en-CA', { minimumFractionDigits: 2, maximumFractionDigits: 2 } );
	};
	var update = function () {
		var hour = val( 'rate' ) + val( 'overhead_hour' );
		var alloc = hour * 20;
		var margin = ( 2400 - alloc ) / 2400 * 100;
		out( 'hour', money( hour ) );
		out( 'alloc', money( alloc ) );
		out( 'margin', margin.toFixed( 0 ) + '%' );
		if ( margin < val( 'margin_min' ) ) {
			out( 'note', 'Below the minimum margin: WOS warns before a quote goes out.', 'is-bad' );
		} else if ( margin < val( 'margin_target' ) ) {
			out( 'note', 'Above the minimum, below the target.', 'is-warn' );
		} else {
			out( 'note', 'At or above the target margin.', 'is-ok' );
		}
		out( 'total', money( 2400 * ( 1 + ( val( 'gst_rate' ) + val( 'pst_rate' ) ) / 100 ) ) );
	};
	document.addEventListener( 'input', update );
	update();
}() );
