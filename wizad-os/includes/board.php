<?php
/**
 * Delivery Kanban: To do, Doing, Blocked (needs Leo), Done.
 */
defined( 'ABSPATH' ) || exit;

function wos_render_board() {
	global $wpdb;
	wos_require_cap();
	$project = isset( $_GET['project'] ) ? absint( $_GET['project'] ) : 0;
	$edit    = isset( $_GET['card'] ) ? wos_get( 'cards', absint( $_GET['card'] ) ) : null;
	$sql     = 'SELECT c.*, p.code AS project_code FROM ' . wos_table( 'cards' ) . ' c LEFT JOIN ' . wos_table( 'projects' ) . ' p ON p.id = c.project_id';
	$cards   = $project
		? $wpdb->get_results( $wpdb->prepare( $sql . ' WHERE c.project_id = %d ORDER BY c.position, c.id', $project ) )
		: $wpdb->get_results( $sql . ' ORDER BY c.position, c.id' );
	$cols    = wos_options( 'card_status' );
	$by      = array_fill_keys( array_keys( $cols ), array() );
	$cutoff  = wp_date( 'Y-m-d', strtotime( '-14 days' ) );
	foreach ( $cards as $c ) {
		// Done cards leave the board after 14 days; they stay in the database.
		if ( 'done' === $c->status && $c->done_at && substr( $c->done_at, 0, 10 ) < $cutoff ) {
			continue;
		}
		$by[ $c->status ][] = $c;
	}
	$projects = wos_ref_options( 'projects' );
	?>
	<div class="wrap wos">
		<h1 class="wp-heading-inline">Delivery board</h1>
		<hr class="wp-header-end">
		<?php wos_render_flash(); ?>
		<form method="get" class="wos-filters">
			<input type="hidden" name="page" value="wos-board">
			<select name="project" onchange="this.form.submit()">
				<option value="0">All projects</option>
				<?php foreach ( $projects as $k => $v ) : ?>
					<option value="<?php echo (int) $k; ?>" <?php selected( $project, $k ); ?>><?php echo esc_html( $v ); ?></option>
				<?php endforeach; ?>
			</select>
		</form>

		<details class="wos-card-form" <?php echo $edit ? 'open' : ''; ?>>
			<summary class="button"><?php echo $edit ? 'Edit card' : '+ Add card'; ?></summary>
			<?php wos_render_card_form( $edit, $project ); ?>
		</details>

		<div class="wos-board" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wos_card_move' ) ); ?>">
			<?php foreach ( $cols as $status => $label ) : ?>
				<section class="wos-col wos-col-<?php echo esc_attr( $status ); ?>" data-status="<?php echo esc_attr( $status ); ?>">
					<h2><?php echo esc_html( $label ); ?> <span class="count"><?php echo count( $by[ $status ] ); ?></span></h2>
					<div class="wos-col-cards">
						<?php foreach ( $by[ $status ] as $c ) { wos_render_card( $c ); } ?>
					</div>
				</section>
			<?php endforeach; ?>
		</div>
		<p class="description">Drag a card to move it. Moving a card to Blocked asks for the reason. Done cards leave the board after 14 days.</p>
	</div>
	<?php
}

function wos_render_card( $c ) {
	$late    = $c->due_date && $c->due_date < wos_today() && 'done' !== $c->status;
	$blocked = 'blocked' === $c->status;
	$url     = wos_url( 'wos-board', array( 'card' => $c->id ) );
	?>
	<article class="wos-card<?php echo $late ? ' is-late' : ''; ?>" draggable="true" data-id="<?php echo (int) $c->id; ?>">
		<a class="wos-card-title" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $c->title ); ?></a>
		<div class="wos-card-meta">
			<?php if ( $c->project_code ) : ?><span class="pill"><?php echo esc_html( $c->project_code ); ?></span><?php endif; ?>
			<?php if ( $c->tag ) : ?><span class="pill pill-tag"><?php echo esc_html( $c->tag ); ?></span><?php endif; ?>
			<?php if ( $c->owner ) : ?><span><?php echo esc_html( $c->owner ); ?></span><?php endif; ?>
			<?php if ( $c->due_date ) : ?><span class="<?php echo $late ? 'late' : ''; ?>">Due <?php echo esc_html( wp_date( 'M j', strtotime( $c->due_date ) ) ); ?></span><?php endif; ?>
		</div>
		<?php if ( $blocked ) : ?>
			<div class="wos-blocker">
				<?php echo esc_html( $c->blocker_note ? $c->blocker_note : 'Blocked' ); ?>
				<?php if ( $c->blocked_since ) : ?> · <strong><?php echo (int) wos_days_between( $c->blocked_since ); ?> d</strong><?php endif; ?>
			</div>
		<?php endif; ?>
	</article>
	<?php
}

function wos_render_card_form( $c, $project ) {
	$projects = array( 0 => '—' ) + wos_ref_options( 'projects' );
	$v        = function ( $k, $d = '' ) use ( $c ) {
		return $c ? $c->$k : $d;
	};
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wos-inline-form">
		<input type="hidden" name="action" value="wos_card_save">
		<input type="hidden" name="id" value="<?php echo (int) $v( 'id', 0 ); ?>">
		<?php wp_nonce_field( 'wos_card_save' ); ?>
		<label>Title * <input type="text" name="title" required value="<?php echo esc_attr( $v( 'title' ) ); ?>" class="regular-text"></label>
		<label>Project
			<select name="project_id">
				<?php foreach ( $projects as $k => $label ) : ?>
					<option value="<?php echo (int) $k; ?>" <?php selected( (int) $v( 'project_id', $project ), (int) $k ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label>Column
			<select name="status">
				<?php foreach ( wos_options( 'card_status' ) as $k => $label ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $v( 'status', 'todo' ), $k ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label>Owner <input type="text" name="owner" value="<?php echo esc_attr( $v( 'owner', 'Leo' ) ); ?>"></label>
		<label>Due <input type="date" name="due_date" value="<?php echo esc_attr( $v( 'due_date' ) ); ?>"></label>
		<label>Hours (est.) <input type="number" step="0.5" name="hours_est" value="<?php echo esc_attr( $v( 'hours_est', 0 ) ); ?>" class="small-text"></label>
		<label>Tag <input type="text" name="tag" value="<?php echo esc_attr( $v( 'tag' ) ); ?>" placeholder="OS, Sales…" class="small-text"></label>
		<label>Key result <input type="text" name="okr" value="<?php echo esc_attr( $v( 'okr' ) ); ?>" placeholder="e.g. KR 1.2"></label>
		<label class="wide">Blocker: what Leo must do <input type="text" name="blocker_note" value="<?php echo esc_attr( $v( 'blocker_note' ) ); ?>" class="large-text"></label>
		<p>
			<button class="button button-primary">Save card</button>
			<?php if ( $c ) : ?>
				<a class="button" href="<?php echo esc_url( wos_url( 'wos-board' ) ); ?>">Cancel</a>
				<button class="button-link wos-delete" name="delete" value="1" onclick="return confirm('Delete this card?');">Delete</button>
			<?php endif; ?>
		</p>
	</form>
	<?php
}

/** Keeps blocked_since and done_at in step with the column. */
function wos_card_status_fields( $old, $new ) {
	$data = array( 'status' => $new );
	if ( 'blocked' === $new && 'blocked' !== $old ) {
		$data['blocked_since'] = wos_today();
	}
	if ( 'blocked' !== $new ) {
		$data['blocked_since'] = null;
	}
	if ( 'done' === $new && 'done' !== $old ) {
		$data['done_at'] = wos_now();
	}
	if ( 'done' !== $new ) {
		$data['done_at'] = null;
	}
	return $data;
}

add_action( 'admin_post_wos_card_save', function () {
	global $wpdb;
	wos_require_cap();
	check_admin_referer( 'wos_card_save' );
	$id  = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	$old = $id ? wos_get( 'cards', $id ) : null;
	if ( $id && ! empty( $_POST['delete'] ) ) {
		$wpdb->delete( wos_table( 'cards' ), array( 'id' => $id ) );
		wos_flash( 'Card deleted.' );
		wp_safe_redirect( wos_url( 'wos-board' ) );
		exit;
	}
	$status = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : 'todo';
	if ( ! isset( wos_options( 'card_status' )[ $status ] ) ) {
		$status = 'todo';
	}
	$due  = isset( $_POST['due_date'] ) ? sanitize_text_field( wp_unslash( $_POST['due_date'] ) ) : '';
	$data = array_merge(
		array(
			'title'        => sanitize_text_field( wp_unslash( isset( $_POST['title'] ) ? $_POST['title'] : '' ) ),
			'project_id'   => isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0,
			'owner'        => sanitize_text_field( wp_unslash( isset( $_POST['owner'] ) ? $_POST['owner'] : '' ) ),
			'due_date'     => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $due ) ? $due : null,
			'hours_est'    => isset( $_POST['hours_est'] ) ? round( (float) $_POST['hours_est'], 2 ) : 0,
			'tag'          => sanitize_text_field( wp_unslash( isset( $_POST['tag'] ) ? $_POST['tag'] : '' ) ),
			'okr'          => sanitize_text_field( wp_unslash( isset( $_POST['okr'] ) ? $_POST['okr'] : '' ) ),
			'blocker_note' => sanitize_text_field( wp_unslash( isset( $_POST['blocker_note'] ) ? $_POST['blocker_note'] : '' ) ),
			'updated_at'   => wos_now(),
		),
		wos_card_status_fields( $old ? $old->status : '', $status )
	);
	if ( $old && $old->status === $status ) {
		unset( $data['blocked_since'], $data['done_at'] );
	}
	if ( $id ) {
		$wpdb->update( wos_table( 'cards' ), $data, array( 'id' => $id ) );
	} else {
		$data['created_at'] = wos_now();
		$data['position']   = (int) $wpdb->get_var( 'SELECT COALESCE(MAX(position),0)+1 FROM ' . wos_table( 'cards' ) );
		$wpdb->insert( wos_table( 'cards' ), $data );
	}
	wos_flash( 'Card saved.' );
	wp_safe_redirect( wos_url( 'wos-board' ) );
	exit;
} );

add_action( 'wp_ajax_wos_card_move', function () {
	global $wpdb;
	check_ajax_referer( 'wos_card_move' );
	if ( ! current_user_can( WOS_CAP ) ) {
		wp_send_json_error( 'forbidden', 403 );
	}
	$id     = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	$status = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : '';
	$order  = isset( $_POST['order'] ) ? array_map( 'absint', (array) $_POST['order'] ) : array();
	$card   = wos_get( 'cards', $id );
	if ( ! $card || ! isset( wos_options( 'card_status' )[ $status ] ) ) {
		wp_send_json_error( 'bad request', 400 );
	}
	$data = wos_card_status_fields( $card->status, $status );
	if ( $card->status === $status ) {
		unset( $data['blocked_since'], $data['done_at'] );
	}
	if ( isset( $_POST['blocker_note'] ) ) {
		$data['blocker_note'] = sanitize_text_field( wp_unslash( $_POST['blocker_note'] ) );
	}
	$data['updated_at'] = wos_now();
	$wpdb->update( wos_table( 'cards' ), $data, array( 'id' => $id ) );
	foreach ( $order as $pos => $cid ) {
		$wpdb->update( wos_table( 'cards' ), array( 'position' => $pos ), array( 'id' => $cid ) );
	}
	wp_send_json_success();
} );
