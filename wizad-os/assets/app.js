/* WOS app: the live check on Settings, and the Ask Claude chat. */
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

/* Ask Claude: send a question, show the answer. */
( function () {
	document.querySelectorAll( '.wos-chat' ).forEach( function ( box ) {
		var form = box.querySelector( '.wos-chat-form' );
		if ( ! form ) {
			return;
		}
		var log = box.querySelector( '.wos-chat-log' );
		var input = form.querySelector( 'textarea' );
		var button = form.querySelector( 'button' );
		var add = function ( text, cls ) {
			var el = document.createElement( 'div' );
			el.className = 'wos-msg ' + cls;
			el.textContent = text;
			log.appendChild( el );
			log.scrollTop = log.scrollHeight;
			return el;
		};
		var send = function ( text ) {
			text = text.trim();
			if ( ! text || button.disabled ) {
				return;
			}
			add( text, 'is-me' );
			input.value = '';
			button.disabled = true;
			var chips = box.querySelector( '.wos-chips' );
			if ( chips ) {
				chips.remove();
			}
			var wait = add( 'Thinking…', 'is-claude is-wait' );
			fetch( box.dataset.url, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': box.dataset.nonce },
				body: JSON.stringify( { scope: box.dataset.scope, message: text } )
			} ).then( function ( r ) {
				return r.json().then( function ( j ) {
					return { ok: r.ok, j: j };
				} );
			} ).then( function ( res ) {
				wait.remove();
				if ( res.ok ) {
					add( res.j.reply, 'is-claude' );
				} else {
					add( ( res.j && res.j.message ) || 'Something went wrong. Try again.', 'is-error' );
				}
			} ).catch( function () {
				wait.remove();
				add( 'Could not reach WOS. Check the connection and try again.', 'is-error' );
			} ).then( function () {
				button.disabled = false;
				input.focus();
			} );
		};
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			send( input.value );
		} );
		input.addEventListener( 'keydown', function ( e ) {
			if ( 'Enter' === e.key && ! e.shiftKey ) {
				e.preventDefault();
				send( input.value );
			}
		} );
		box.querySelectorAll( '.wos-chip' ).forEach( function ( chip ) {
			chip.addEventListener( 'click', function () {
				send( chip.textContent );
			} );
		} );
		log.scrollTop = log.scrollHeight;
	} );
}() );
