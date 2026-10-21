<?php
/**
 * Generic list, form, save and delete for the entities in entities.php.
 */
defined( 'ABSPATH' ) || exit;

function wos_get( $entity, $id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . wos_table( $entity ) . ' WHERE id = %d', $id ) );
}

/* ---------- Private receipts ---------- */

function wos_private_dir() {
	$up  = wp_upload_dir();
	$dir = trailingslashit( $up['basedir'] ) . 'wos-private';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	if ( ! file_exists( $dir . '/.htaccess' ) ) {
		file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" );
		file_put_contents( $dir . '/index.php', "<?php // Silence.\n" );
	}
	return $dir;
}

function wos_store_receipt( $field ) {
	if ( empty( $_FILES[ $field ]['name'] ) || UPLOAD_ERR_OK !== $_FILES[ $field ]['error'] ) {
		return '';
	}
	$allowed = array(
		'jpg|jpeg' => 'image/jpeg',
		'png'      => 'image/png',
		'webp'     => 'image/webp',
		'heic'     => 'image/heic',
		'pdf'      => 'application/pdf',
	);
	$check = wp_check_filetype_and_ext( $_FILES[ $field ]['tmp_name'], $_FILES[ $field ]['name'], $allowed );
	if ( empty( $check['ext'] ) ) {
		wos_flash( 'Receipt not saved: use a JPG, PNG, WEBP, HEIC or PDF file.', 'error' );
		return '';
	}
	$dir  = wos_private_dir();
	$name = wp_date( 'Ymd-His' ) . '-' . wp_generate_password( 6, false ) . '.' . $check['ext'];
	if ( ! move_uploaded_file( $_FILES[ $field ]['tmp_name'], $dir . '/' . $name ) ) {
		return '';
	}
	return $name;
}

add_action( 'admin_post_wos_receipt', function () {
	wos_require_cap();
	$name = isset( $_GET['f'] ) ? sanitize_file_name( wp_unslash( $_GET['f'] ) ) : '';
	check_admin_referer( 'wos_receipt_' . $name );
	$path = wos_private_dir() . '/' . $name;
	if ( ! $name || ! file_exists( $path ) ) {
		wp_die( 'Receipt not found.', 404 );
	}
	$type = wp_check_filetype( $path );
	nocache_headers();
	header( 'Content-Type: ' . ( $type['type'] ? $type['type'] : 'application/octet-stream' ) );
	header( 'Content-Disposition: inline; filename="' . $name . '"' );
	header( 'Content-Length: ' . filesize( $path ) );
	readfile( $path );
	exit;
} );

function wos_receipt_link( $name ) {
	if ( ! $name ) {
		return '';
	}
	$url = wp_nonce_url( add_query_arg( array( 'action' => 'wos_receipt', 'f' => $name ), admin_url( 'admin-post.php' ) ), 'wos_receipt_' . $name );
	return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">View</a>';
}

/* ---------- Display ---------- */

function wos_format_value( $f, $value, $row = null ) {
	switch ( $f['type'] ) {
		case 'select':
			return esc_html( wos_label( $f['options'], $value ) );
		case 'ref':
			$opts = wos_ref_cache( $f['ref'] );
			return isset( $opts[ $value ] ) ? esc_html( $opts[ $value ] ) : '—';
		case 'money':
			$cur = ( $row && isset( $row->currency ) && 'amount_cad' !== ( isset( $f['key'] ) ? $f['key'] : '' ) ) ? $row->currency : 'CAD';
			return esc_html( wos_money( $value, $cur ) );
		case 'percent':
			return esc_html( (int) $value . '%' );
		case 'date':
			return $value ? esc_html( wp_date( 'M j, Y', strtotime( $value ) ) ) : '—';
		case 'file':
			return $value ? wos_receipt_link( $value ) : '—';
		default:
			return esc_html( wp_trim_words( (string) $value, 12 ) );
	}
}

function wos_ref_cache( $entity ) {
	static $cache = array();
	if ( ! isset( $cache[ $entity ] ) ) {
		$cache[ $entity ] = wos_ref_options( $entity );
	}
	return $cache[ $entity ];
}

/* ---------- Screens ---------- */

function wos_render_entity_page( $entity ) {
	wos_require_cap();
	$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
	if ( 'edit' === $action || 'new' === $action ) {
		wos_render_form( $entity, isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0 );
	} else {
		wos_render_list( $entity );
	}
}

function wos_render_list( $entity ) {
	global $wpdb;
	$def   = wos_entity( $entity );
	$page  = 'wos-' . $entity;
	$where = array( '1=1' );
	$args  = array();

	$s = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	if ( $s ) {
		$like = array();
		foreach ( $def['search'] as $col ) {
			$like[] = "$col LIKE %s";
			$args[] = '%' . $wpdb->esc_like( $s ) . '%';
		}
		$where[] = '(' . implode( ' OR ', $like ) . ')';
	}
	list( $fcol, $flist ) = $def['filter'];
	$fval = isset( $_GET[ $fcol ] ) ? sanitize_key( $_GET[ $fcol ] ) : '';
	if ( $fval && isset( wos_options( $flist )[ $fval ] ) ) {
		$where[] = "$fcol = %s";
		$args[]  = $fval;
	}
	$sql  = 'SELECT * FROM ' . wos_table( $entity ) . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY ' . $def['order'] . ' LIMIT 500';
	$rows = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ) ) : $wpdb->get_results( $sql );

	$cols = array_filter( $def['fields'], function ( $f ) {
		return ! empty( $f['list'] );
	} );
	?>
	<div class="wrap wos">
		<h1 class="wp-heading-inline"><?php echo esc_html( $def['plural'] ); ?></h1>
		<a class="page-title-action" href="<?php echo esc_url( wos_url( $page, array( 'action' => 'new' ) ) ); ?>">Add <?php echo esc_html( strtolower( $def['singular'] ) ); ?></a>
		<a class="page-title-action" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'wos_export', 'entity' => $entity ), admin_url( 'admin-post.php' ) ), 'wos_export' ) ); ?>">Export CSV</a>
		<hr class="wp-header-end">
		<?php wos_render_flash(); ?>
		<?php if ( 'ledger' === $entity ) { wos_render_ledger_totals(); } ?>
		<form method="get" class="wos-filters">
			<input type="hidden" name="page" value="<?php echo esc_attr( $page ); ?>">
			<select name="<?php echo esc_attr( $fcol ); ?>">
				<option value="">All</option>
				<?php foreach ( wos_options( $flist ) as $k => $v ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $fval, $k ); ?>><?php echo esc_html( $v ); ?></option>
				<?php endforeach; ?>
			</select>
			<input type="search" name="s" value="<?php echo esc_attr( $s ); ?>" placeholder="Search">
			<button class="button">Filter</button>
		</form>
		<table class="widefat striped wos-table">
			<thead><tr>
				<?php foreach ( $cols as $f ) : ?><th><?php echo esc_html( $f['label'] ); ?></th><?php endforeach; ?>
				<th></th>
			</tr></thead>
			<tbody>
			<?php if ( ! $rows ) : ?>
				<tr><td colspan="<?php echo count( $cols ) + 1; ?>">Nothing here yet.</td></tr>
			<?php endif; ?>
			<?php foreach ( $rows as $r ) : ?>
				<tr class="<?php echo esc_attr( wos_row_class( $entity, $r ) ); ?>">
					<?php foreach ( $cols as $key => $f ) : $f['key'] = $key; ?>
						<td><?php echo wos_format_value( $f, $r->$key, $r ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in wos_format_value. ?></td>
					<?php endforeach; ?>
					<td class="wos-actions">
						<a href="<?php echo esc_url( wos_url( $page, array( 'action' => 'edit', 'id' => $r->id ) ) ); ?>">Edit</a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

function wos_row_class( $entity, $r ) {
	if ( 'deals' === $entity && ! in_array( $r->stage, array( 'won', 'lost' ), true ) && $r->next_step_date && $r->next_step_date < wos_today() ) {
		return 'wos-late';
	}
	return '';
}

function wos_render_form( $entity, $id ) {
	$def  = wos_entity( $entity );
	$row  = $id ? wos_get( $entity, $id ) : null;
	$page = 'wos-' . $entity;
	if ( $id && ! $row ) {
		wp_die( 'Record not found.' );
	}
	$prefill = array();
	foreach ( $def['fields'] as $key => $f ) {
		if ( isset( $_GET[ $key ] ) ) {
			$prefill[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
		}
	}
	?>
	<div class="wrap wos">
		<h1><?php echo esc_html( ( $row ? 'Edit ' : 'New ' ) . strtolower( $def['singular'] ) ); ?></h1>
		<?php wos_render_flash(); ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<input type="hidden" name="action" value="wos_save">
			<input type="hidden" name="entity" value="<?php echo esc_attr( $entity ); ?>">
			<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
			<?php wp_nonce_field( 'wos_save_' . $entity ); ?>
			<table class="form-table" role="presentation">
			<?php foreach ( $def['fields'] as $key => $f ) :
				$val = $row ? $row->$key : ( isset( $prefill[ $key ] ) ? $prefill[ $key ] : wos_field_default( $entity, $key ) );
				$fid = 'wos-' . $key;
				?>
				<tr>
					<th scope="row"><label for="<?php echo esc_attr( $fid ); ?>"><?php echo esc_html( $f['label'] ); ?><?php echo ! empty( $f['required'] ) ? ' *' : ''; ?></label></th>
					<td>
						<?php wos_render_input( $key, $f, $val, $fid ); ?>
						<?php if ( ! empty( $f['help'] ) ) : ?><p class="description"><?php echo esc_html( $f['help'] ); ?></p><?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</table>
			<p class="submit">
				<button class="button button-primary">Save</button>
				<a class="button" href="<?php echo esc_url( wos_url( $page ) ); ?>">Cancel</a>
			</p>
		</form>
		<?php if ( $row ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Delete this record? This cannot be undone.');">
				<input type="hidden" name="action" value="wos_delete">
				<input type="hidden" name="entity" value="<?php echo esc_attr( $entity ); ?>">
				<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
				<?php wp_nonce_field( 'wos_delete_' . $entity . '_' . $id ); ?>
				<button class="button-link wos-delete">Delete</button>
			</form>
		<?php endif; ?>
	</div>
	<?php
}

function wos_field_default( $entity, $key ) {
	$defaults = array(
		'currency'    => 'CAD',
		'entry_date'  => wos_today(),
		'type'        => 'ledger' === $entity ? 'expense' : 'sprint',
		'probability' => 10,
	);
	return isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
}

function wos_render_input( $key, $f, $val, $fid ) {
	$name = 'f[' . $key . ']';
	$req  = ! empty( $f['required'] ) ? ' required' : '';
	switch ( $f['type'] ) {
		case 'textarea':
			printf( '<textarea id="%s" name="%s" rows="4" class="large-text">%s</textarea>', esc_attr( $fid ), esc_attr( $name ), esc_textarea( $val ) );
			break;
		case 'select':
		case 'ref':
			$opts = 'select' === $f['type'] ? wos_options( $f['options'] ) : array( 0 => '—' ) + wos_ref_options( $f['ref'] );
			printf( '<select id="%s" name="%s">', esc_attr( $fid ), esc_attr( $name ) );
			foreach ( $opts as $k => $v ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( (string) $val, (string) $k, false ), esc_html( $v ) );
			}
			echo '</select>';
			break;
		case 'money':
		case 'number':
			if ( ! empty( $f['readonly'] ) ) {
				echo '<input type="text" readonly value="' . esc_attr( $val ) . '" class="regular-text"> <span class="description">Calculated on save from the amount and the exchange rate in Settings.</span>';
				break;
			}
			printf( '<input id="%s" type="number" step="0.01" name="%s" value="%s" class="regular-text"%s>', esc_attr( $fid ), esc_attr( $name ), esc_attr( $val ), $req ); // phpcs:ignore
			break;
		case 'percent':
			printf( '<input id="%s" type="number" min="0" max="100" step="5" name="%s" value="%s" class="small-text"> %%', esc_attr( $fid ), esc_attr( $name ), esc_attr( $val ) );
			break;
		case 'date':
			printf( '<input id="%s" type="date" name="%s" value="%s"%s>', esc_attr( $fid ), esc_attr( $name ), esc_attr( $val ), $req ); // phpcs:ignore
			break;
		case 'file':
			if ( $val ) {
				echo wos_receipt_link( $val ) . ' &nbsp; '; // phpcs:ignore
			}
			printf( '<input id="%s" type="file" name="wos_file_%s" accept="image/*,application/pdf">', esc_attr( $fid ), esc_attr( $key ) );
			break;
		default:
			$type = 'email' === $f['type'] ? 'email' : 'text';
			printf( '<input id="%s" type="%s" name="%s" value="%s" class="regular-text"%s>', esc_attr( $fid ), esc_attr( $type ), esc_attr( $name ), esc_attr( $val ), $req ); // phpcs:ignore
	}
}

function wos_clean_value( $f, $raw ) {
	$raw = is_string( $raw ) ? wp_unslash( $raw ) : $raw;
	switch ( $f['type'] ) {
		case 'textarea':
			return sanitize_textarea_field( $raw );
		case 'email':
			return sanitize_email( $raw );
		case 'select':
			$o = wos_options( $f['options'] );
			return isset( $o[ $raw ] ) ? $raw : (string) key( $o );
		case 'ref':
			return absint( $raw );
		case 'money':
		case 'number':
			return round( (float) $raw, 2 );
		case 'percent':
			return max( 0, min( 100, (int) $raw ) );
		case 'date':
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $raw ) ? $raw : null;
		default:
			return sanitize_text_field( $raw );
	}
}

add_action( 'admin_post_wos_save', function () {
	global $wpdb;
	wos_require_cap();
	$entity = isset( $_POST['entity'] ) ? sanitize_key( $_POST['entity'] ) : '';
	$def    = wos_entity( $entity );
	if ( ! $def ) {
		wp_die( 'Unknown record type.' );
	}
	check_admin_referer( 'wos_save_' . $entity );
	$id    = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	$input = isset( $_POST['f'] ) && is_array( $_POST['f'] ) ? $_POST['f'] : array(); // phpcs:ignore -- cleaned per field below.
	$data  = array();
	foreach ( $def['fields'] as $key => $f ) {
		if ( ! empty( $f['readonly'] ) ) {
			continue;
		}
		if ( 'file' === $f['type'] ) {
			$stored = wos_store_receipt( 'wos_file_' . $key );
			if ( $stored ) {
				$data[ $key ] = $stored;
			}
			continue;
		}
		$data[ $key ] = wos_clean_value( $f, isset( $input[ $key ] ) ? $input[ $key ] : '' );
	}
	if ( 'ledger' === $entity ) {
		$data['fx_rate']    = wos_fx( $data['currency'] );
		$data['amount_cad'] = round( $data['amount'] * $data['fx_rate'], 2 );
	}
	$data['updated_at'] = wos_now();
	if ( $id ) {
		$wpdb->update( wos_table( $entity ), $data, array( 'id' => $id ) );
	} else {
		$data['created_at'] = wos_now();
		$wpdb->insert( wos_table( $entity ), $data );
		$id = $wpdb->insert_id;
	}
	wos_flash( $def['singular'] . ' saved.' );
	wp_safe_redirect( wos_url( 'wos-' . $entity ) );
	exit;
} );

add_action( 'admin_post_wos_delete', function () {
	global $wpdb;
	wos_require_cap();
	$entity = isset( $_POST['entity'] ) ? sanitize_key( $_POST['entity'] ) : '';
	$id     = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	$def    = wos_entity( $entity );
	if ( ! $def ) {
		wp_die( 'Unknown record type.' );
	}
	check_admin_referer( 'wos_delete_' . $entity . '_' . $id );
	$wpdb->delete( wos_table( $entity ), array( 'id' => $id ) );
	wos_flash( $def['singular'] . ' deleted.' );
	wp_safe_redirect( wos_url( 'wos-' . $entity ) );
	exit;
} );

function wos_render_ledger_totals() {
	global $wpdb;
	$year = wp_date( 'Y' );
	$rows = $wpdb->get_results( $wpdb->prepare(
		'SELECT type, SUM(amount_cad) total FROM ' . wos_table( 'ledger' ) . ' WHERE entry_date >= %s GROUP BY type',
		$year . '-01-01'
	), OBJECT_K );
	$in  = isset( $rows['income'] ) ? (float) $rows['income']->total : 0;
	$out = isset( $rows['expense'] ) ? (float) $rows['expense']->total : 0;
	printf(
		'<div class="wos-kpis"><div class="wos-kpi"><span>Income %1$s</span><strong>%2$s</strong></div><div class="wos-kpi"><span>Expenses %1$s</span><strong>%3$s</strong></div><div class="wos-kpi"><span>Profit %1$s</span><strong>%4$s</strong></div></div>',
		esc_html( $year ),
		esc_html( wos_money( $in ) ),
		esc_html( wos_money( $out ) ),
		esc_html( wos_money( $in - $out ) )
	);
}
