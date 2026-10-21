( function () {
	const board = document.querySelector( '.wos-board' );
	if ( ! board ) {
		return;
	}
	let dragged = null;

	board.addEventListener( 'dragstart', ( e ) => {
		dragged = e.target.closest( '.wos-card' );
		if ( dragged ) {
			dragged.classList.add( 'is-dragging' );
			e.dataTransfer.effectAllowed = 'move';
		}
	} );
	board.addEventListener( 'dragend', () => {
		if ( dragged ) {
			dragged.classList.remove( 'is-dragging' );
		}
		board.querySelectorAll( '.is-over' ).forEach( ( c ) => c.classList.remove( 'is-over' ) );
	} );
	board.addEventListener( 'dragover', ( e ) => {
		const col = e.target.closest( '.wos-col' );
		if ( ! col || ! dragged ) {
			return;
		}
		e.preventDefault();
		col.classList.add( 'is-over' );
		const list = col.querySelector( '.wos-col-cards' );
		const after = [ ...list.querySelectorAll( '.wos-card:not(.is-dragging)' ) ].find(
			( c ) => e.clientY < c.getBoundingClientRect().top + c.offsetHeight / 2
		);
		list.insertBefore( dragged, after || null );
	} );
	board.addEventListener( 'dragleave', ( e ) => {
		const col = e.target.closest( '.wos-col' );
		if ( col && ! col.contains( e.relatedTarget ) ) {
			col.classList.remove( 'is-over' );
		}
	} );
	board.addEventListener( 'drop', ( e ) => {
		const col = e.target.closest( '.wos-col' );
		if ( ! col || ! dragged ) {
			return;
		}
		e.preventDefault();
		const status = col.dataset.status;
		const body = new URLSearchParams( {
			action: 'wos_card_move',
			_ajax_nonce: board.dataset.nonce,
			id: dragged.dataset.id,
			status,
		} );
		if ( status === 'blocked' && ! dragged.querySelector( '.wos-blocker' ) ) {
			const note = window.prompt( 'What does Leo need to do to unblock this?' );
			if ( note === null ) {
				window.location.reload();
				return;
			}
			body.append( 'blocker_note', note );
		}
		col.querySelectorAll( '.wos-card' ).forEach( ( c ) => body.append( 'order[]', c.dataset.id ) );
		fetch( window.wosBoard.ajax, { method: 'POST', credentials: 'same-origin', body } )
			.then( ( r ) => r.json() )
			.then( ( r ) => {
				if ( ! r.success ) {
					throw new Error( 'save failed' );
				}
				window.location.reload();
			} )
			.catch( () => {
				window.alert( 'The card could not be moved. Reload and try again.' );
			} );
	} );
} )();
