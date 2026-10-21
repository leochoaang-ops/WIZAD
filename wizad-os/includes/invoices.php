<?php
/**
 * Invoices: one number sequence per year (WZ-2026-001), printable view, and
 * an income entry in the ledger when an invoice is marked paid.
 */
defined( 'ABSPATH' ) || exit;

function wos_next_invoice_number( $issue_date ) {
	global $wpdb;
	$year   = substr( $issue_date ? $issue_date : wos_today(), 0, 4 );
	$prefix = sanitize_text_field( wos_setting( 'invoice_prefix' ) ) . '-' . $year . '-';
	$last   = $wpdb->get_var( $wpdb->prepare(
		'SELECT number FROM ' . wos_table( 'invoices' ) . ' WHERE number LIKE %s ORDER BY number DESC LIMIT 1',
		$wpdb->esc_like( $prefix ) . '%'
	) );
	$n = $last ? (int) substr( $last, strlen( $prefix ) ) + 1 : 1;
	return $prefix . str_pad( (string) $n, 3, '0', STR_PAD_LEFT );
}

function wos_invoice_lines( $invoice_id ) {
	global $wpdb;
	return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . wos_table( 'invoice_lines' ) . ' WHERE invoice_id = %d ORDER BY position, id', $invoice_id ) );
}

function wos_render_invoices_page() {
	wos_require_cap();
	$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
	if ( in_array( $action, array( 'new', 'edit' ), true ) ) {
		wos_render_invoice_form( isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0 );
		return;
	}
	global $wpdb;
	$rows    = $wpdb->get_results( 'SELECT i.*, c.name AS client_name FROM ' . wos_table( 'invoices' ) . ' i LEFT JOIN ' . wos_table( 'clients' ) . ' c ON c.id = i.client_id ORDER BY i.issue_date DESC, i.id DESC LIMIT 500' );
	$today   = wos_today();
	?>
	<div class="wrap wos">
		<h1 class="wp-heading-inline">Invoices</h1>
		<a class="page-title-action" href="<?php echo esc_url( wos_url( 'wos-invoices', array( 'action' => 'new' ) ) ); ?>">New invoice</a>
		<a class="page-title-action" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'wos_export', 'entity' => 'invoices' ), admin_url( 'admin-post.php' ) ), 'wos_export' ) ); ?>">Export CSV</a>
		<hr class="wp-header-end">
		<?php wos_render_flash(); ?>
		<table class="widefat striped wos-table">
			<thead><tr><th>Number</th><th>Client</th><th>Issued</th><th>Due</th><th>Total</th><th>Status</th><th></th></tr></thead>
			<tbody>
			<?php if ( ! $rows ) : ?><tr><td colspan="7">No invoices yet.</td></tr><?php endif; ?>
			<?php foreach ( $rows as $r ) :
				$overdue = 'sent' === $r->status && $r->due_date && $r->due_date < $today;
				?>
				<tr class="<?php echo $overdue ? 'wos-late' : ''; ?>">
					<td><strong><?php echo esc_html( $r->number ? $r->number : '(draft)' ); ?></strong></td>
					<td><?php echo esc_html( $r->client_name ); ?></td>
					<td><?php echo esc_html( $r->issue_date ); ?></td>
					<td><?php echo esc_html( $r->due_date ); ?><?php echo $overdue ? ' · <strong>' . (int) wos_days_between( $r->due_date ) . ' d late</strong>' : ''; ?></td>
					<td><?php echo esc_html( wos_money( $r->total, $r->currency ) ); ?></td>
					<td><span class="pill pill-<?php echo esc_attr( $r->status ); ?>"><?php echo esc_html( wos_label( 'invoice_status', $r->status ) ); ?></span></td>
					<td class="wos-actions">
						<a href="<?php echo esc_url( wos_url( 'wos-invoices', array( 'action' => 'edit', 'id' => $r->id ) ) ); ?>">Edit</a> ·
						<a href="<?php echo esc_url( wos_invoice_print_url( $r->id ) ); ?>" target="_blank" rel="noopener">Print / PDF</a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

function wos_invoice_print_url( $id ) {
	return wp_nonce_url( add_query_arg( array( 'action' => 'wos_invoice_print', 'id' => $id ), admin_url( 'admin-post.php' ) ), 'wos_invoice_print_' . $id );
}

function wos_render_invoice_form( $id ) {
	$inv   = $id ? wos_get( 'invoices', $id ) : null;
	$lines = $inv ? wos_invoice_lines( $id ) : array();
	$issue = $inv ? $inv->issue_date : wos_today();
	$due   = $inv ? $inv->due_date : wp_date( 'Y-m-d', strtotime( '+' . (int) wos_setting( 'payment_terms' ) . ' days' ) );
	$pre_c = isset( $_GET['client_id'] ) ? absint( $_GET['client_id'] ) : 0;
	$pre_p = isset( $_GET['project_id'] ) ? absint( $_GET['project_id'] ) : 0;
	while ( count( $lines ) < 5 ) {
		$lines[] = (object) array( 'description' => '', 'qty' => '', 'unit_price' => '' );
	}
	?>
	<div class="wrap wos">
		<h1><?php echo $inv ? esc_html( 'Invoice ' . ( $inv->number ? $inv->number : '(draft)' ) ) : 'New invoice'; ?></h1>
		<?php wos_render_flash(); ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="wos_invoice_save">
			<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
			<?php wp_nonce_field( 'wos_invoice_save' ); ?>
			<table class="form-table" role="presentation">
				<tr><th>Client *</th><td><select name="client_id" required>
					<option value="">—</option>
					<?php foreach ( wos_ref_options( 'clients' ) as $k => $v ) : ?>
						<option value="<?php echo (int) $k; ?>" <?php selected( $inv ? (int) $inv->client_id : $pre_c, (int) $k ); ?>><?php echo esc_html( $v ); ?></option>
					<?php endforeach; ?>
				</select></td></tr>
				<tr><th>Project</th><td><select name="project_id">
					<option value="0">—</option>
					<?php foreach ( wos_ref_options( 'projects' ) as $k => $v ) : ?>
						<option value="<?php echo (int) $k; ?>" <?php selected( $inv ? (int) $inv->project_id : $pre_p, (int) $k ); ?>><?php echo esc_html( $v ); ?></option>
					<?php endforeach; ?>
				</select></td></tr>
				<tr><th>Issue date</th><td><input type="date" name="issue_date" value="<?php echo esc_attr( $issue ); ?>" required></td></tr>
				<tr><th>Due date</th><td><input type="date" name="due_date" value="<?php echo esc_attr( $due ); ?>" required></td></tr>
				<tr><th>Currency</th><td><select name="currency">
					<?php foreach ( wos_options( 'currency' ) as $k => $v ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $inv ? $inv->currency : 'CAD', $k ); ?>><?php echo esc_html( $v ); ?></option>
					<?php endforeach; ?>
				</select></td></tr>
				<tr><th>Status</th><td><select name="status">
					<?php foreach ( wos_options( 'invoice_status' ) as $k => $v ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $inv ? $inv->status : 'draft', $k ); ?>><?php echo esc_html( $v ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description">Marking an invoice <strong>Paid</strong> adds the income to the ledger on the paid date.</p></td></tr>
				<tr><th>Paid date</th><td><input type="date" name="paid_date" value="<?php echo esc_attr( $inv ? $inv->paid_date : '' ); ?>"></td></tr>
			</table>
			<h2>Lines</h2>
			<table class="widefat wos-lines">
				<thead><tr><th>Description</th><th style="width:90px">Qty</th><th style="width:140px">Unit price</th></tr></thead>
				<tbody>
				<?php foreach ( $lines as $l ) : ?>
					<tr>
						<td><input type="text" name="line_desc[]" value="<?php echo esc_attr( $l->description ); ?>" class="large-text"></td>
						<td><input type="number" step="0.01" name="line_qty[]" value="<?php echo esc_attr( $l->qty ); ?>"></td>
						<td><input type="number" step="0.01" name="line_price[]" value="<?php echo esc_attr( $l->unit_price ); ?>"></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description"><?php echo wos_setting( 'gst_registered' ) ? esc_html( 'GST at ' . wos_setting( 'gst_rate' ) . '% is added to the total.' ) : 'WIZAD is not GST-registered, so no GST is charged. Change this in Settings once registered.'; ?></p>
			<table class="form-table"><tr><th>Notes on the invoice</th><td><textarea name="notes" rows="3" class="large-text"><?php echo esc_textarea( $inv ? $inv->notes : '' ); ?></textarea></td></tr></table>
			<p class="submit">
				<button class="button button-primary">Save invoice</button>
				<?php if ( $inv ) : ?><a class="button" href="<?php echo esc_url( wos_invoice_print_url( $id ) ); ?>" target="_blank" rel="noopener">Print / PDF</a><?php endif; ?>
				<a class="button" href="<?php echo esc_url( wos_url( 'wos-invoices' ) ); ?>">Back</a>
			</p>
		</form>
	</div>
	<?php
}

add_action( 'admin_post_wos_invoice_save', function () {
	global $wpdb;
	wos_require_cap();
	check_admin_referer( 'wos_invoice_save' );
	$id  = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	$old = $id ? wos_get( 'invoices', $id ) : null;

	$date = function ( $k ) {
		$v = isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : '';
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : null;
	};
	$status = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : 'draft';
	if ( ! isset( wos_options( 'invoice_status' )[ $status ] ) ) {
		$status = 'draft';
	}
	$currency = isset( $_POST['currency'] ) ? sanitize_key( $_POST['currency'] ) : 'cad';
	$currency = strtoupper( $currency );
	if ( ! isset( wos_options( 'currency' )[ $currency ] ) ) {
		$currency = 'CAD';
	}

	$descs  = isset( $_POST['line_desc'] ) ? (array) wp_unslash( $_POST['line_desc'] ) : array();
	$qtys   = isset( $_POST['line_qty'] ) ? (array) $_POST['line_qty'] : array();
	$prices = isset( $_POST['line_price'] ) ? (array) $_POST['line_price'] : array();
	$lines  = array();
	foreach ( $descs as $i => $d ) {
		$d = sanitize_text_field( $d );
		$q = isset( $qtys[ $i ] ) && '' !== $qtys[ $i ] ? (float) $qtys[ $i ] : 1;
		$p = isset( $prices[ $i ] ) ? (float) $prices[ $i ] : 0;
		if ( '' === $d && ! $p ) {
			continue;
		}
		$lines[] = array( 'description' => $d, 'qty' => $q, 'unit_price' => round( $p, 2 ), 'amount' => round( $q * $p, 2 ), 'position' => $i );
	}
	$subtotal = array_sum( wp_list_pluck( $lines, 'amount' ) );
	$rate     = wos_setting( 'gst_registered' ) ? (float) wos_setting( 'gst_rate' ) : 0;
	$gst      = round( $subtotal * $rate / 100, 2 );

	$data = array(
		'client_id'  => isset( $_POST['client_id'] ) ? absint( $_POST['client_id'] ) : 0,
		'project_id' => isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0,
		'issue_date' => $date( 'issue_date' ),
		'due_date'   => $date( 'due_date' ),
		'currency'   => $currency,
		'subtotal'   => $subtotal,
		'gst_rate'   => $rate,
		'gst_amount' => $gst,
		'total'      => $subtotal + $gst,
		'status'     => $status,
		'paid_date'  => $date( 'paid_date' ),
		'notes'      => sanitize_textarea_field( wp_unslash( isset( $_POST['notes'] ) ? $_POST['notes'] : '' ) ),
		'updated_at' => wos_now(),
	);
	if ( 'paid' === $status && ! $data['paid_date'] ) {
		$data['paid_date'] = wos_today();
	}
	// A number is given when the invoice leaves draft, and never changes after.
	if ( ( ! $old || ! $old->number ) && 'draft' !== $status ) {
		$data['number'] = wos_next_invoice_number( $data['issue_date'] );
	}
	if ( $old ) {
		$wpdb->update( wos_table( 'invoices' ), $data, array( 'id' => $id ) );
	} else {
		$data['created_at'] = wos_now();
		$wpdb->insert( wos_table( 'invoices' ), $data );
		$id = $wpdb->insert_id;
	}
	$wpdb->delete( wos_table( 'invoice_lines' ), array( 'invoice_id' => $id ) );
	foreach ( $lines as $l ) {
		$wpdb->insert( wos_table( 'invoice_lines' ), array_merge( $l, array( 'invoice_id' => $id ) ) );
	}
	wos_sync_invoice_income( $id );
	wos_flash( 'Invoice saved.' );
	wp_safe_redirect( wos_url( 'wos-invoices', array( 'action' => 'edit', 'id' => $id ) ) );
	exit;
} );

/** One income ledger entry per paid invoice; removed if the invoice is no longer paid. */
function wos_sync_invoice_income( $id ) {
	global $wpdb;
	$inv      = wos_get( 'invoices', $id );
	$existing = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . wos_table( 'ledger' ) . ' WHERE invoice_id = %d', $id ) );
	if ( ! $inv || 'paid' !== $inv->status ) {
		if ( $existing ) {
			$wpdb->delete( wos_table( 'ledger' ), array( 'id' => $existing ) );
		}
		return;
	}
	$client = wos_get( 'clients', $inv->client_id );
	$fx     = wos_fx( $inv->currency );
	$data   = array(
		'entry_date'  => $inv->paid_date,
		'type'        => 'income',
		'category'    => 'Sales',
		'payee'       => $client ? $client->name : '',
		'description' => 'Invoice ' . $inv->number,
		'amount'      => $inv->total,
		'currency'    => $inv->currency,
		'fx_rate'     => $fx,
		'amount_cad'  => round( $inv->total * $fx, 2 ),
		'gst'         => $inv->gst_amount,
		'client_id'   => $inv->client_id,
		'project_id'  => $inv->project_id,
		'invoice_id'  => $id,
		'updated_at'  => wos_now(),
	);
	if ( $existing ) {
		$wpdb->update( wos_table( 'ledger' ), $data, array( 'id' => $existing ) );
	} else {
		$data['created_at'] = wos_now();
		$wpdb->insert( wos_table( 'ledger' ), $data );
	}
}

add_action( 'admin_post_wos_invoice_print', function () {
	wos_require_cap();
	$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	check_admin_referer( 'wos_invoice_print_' . $id );
	$inv = wos_get( 'invoices', $id );
	if ( ! $inv ) {
		wp_die( 'Invoice not found.' );
	}
	$client = wos_get( 'clients', $inv->client_id );
	$lines  = wos_invoice_lines( $id );
	$cur    = $inv->currency;
	?>
	<!doctype html>
	<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( 'Invoice ' . $inv->number ); ?></title>
	<style>
		body{font:14px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;color:#1d2327;background:#f0f0f1;margin:0}
		.page{max-width:800px;margin:24px auto;background:#fff;padding:48px;box-shadow:0 1px 3px rgba(0,0,0,.1)}
		header{display:flex;justify-content:space-between;gap:24px;border-bottom:2px solid #1d2327;padding-bottom:16px}
		h1{margin:0;font-size:28px;letter-spacing:.04em}
		.muted{color:#646970}
		.meta{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin:24px 0}
		table{width:100%;border-collapse:collapse}
		th,td{text-align:left;padding:10px 8px;border-bottom:1px solid #dcdcde;vertical-align:top}
		th:not(:first-child),td:not(:first-child){text-align:right}
		.totals td{border:0;padding:4px 8px}.totals .grand td{font-size:18px;font-weight:700;border-top:2px solid #1d2327;padding-top:10px}
		.bar{max-width:800px;margin:16px auto 0;text-align:right}
		button{font:inherit;padding:8px 16px;cursor:pointer}
		@media print{body{background:#fff}.page{box-shadow:none;margin:0;padding:0}.bar{display:none}}
		@media (max-width:640px){.page{padding:20px}.meta{grid-template-columns:1fr}header{flex-direction:column}}
	</style></head><body>
	<div class="bar"><button onclick="window.print()">Print or save as PDF</button></div>
	<div class="page">
		<header>
			<div><h1>INVOICE</h1><div class="muted"><?php echo esc_html( $inv->number ? $inv->number : 'DRAFT' ); ?></div></div>
			<div style="text-align:right"><strong><?php echo esc_html( wos_setting( 'business_name' ) ); ?></strong><br>
				<?php echo nl2br( esc_html( wos_setting( 'owner_name' ) . "\n" . wos_setting( 'address' ) ) ); ?><br>
				<?php echo esc_html( wos_setting( 'email' ) ); ?> · <?php echo esc_html( wos_setting( 'website' ) ); ?>
				<?php if ( wos_setting( 'gst_registered' ) && wos_setting( 'gst_number' ) ) : ?><br>GST/HST no. <?php echo esc_html( wos_setting( 'gst_number' ) ); ?><?php endif; ?>
			</div>
		</header>
		<div class="meta">
			<div><div class="muted">Bill to</div><strong><?php echo esc_html( $client ? $client->name : '' ); ?></strong><br>
				<?php echo $client ? nl2br( esc_html( trim( $client->contact_name . "\n" . $client->address ) ) ) : ''; ?></div>
			<div style="text-align:right"><div><span class="muted">Issued</span> <?php echo esc_html( wp_date( 'F j, Y', strtotime( $inv->issue_date ) ) ); ?></div>
				<div><span class="muted">Due</span> <?php echo esc_html( wp_date( 'F j, Y', strtotime( $inv->due_date ) ) ); ?></div>
				<div><span class="muted">Currency</span> <?php echo esc_html( $cur ); ?></div></div>
		</div>
		<table>
			<thead><tr><th>Description</th><th>Qty</th><th>Unit price</th><th>Amount</th></tr></thead>
			<tbody>
			<?php foreach ( $lines as $l ) : ?>
				<tr><td><?php echo esc_html( $l->description ); ?></td><td><?php echo esc_html( rtrim( rtrim( number_format( $l->qty, 2 ), '0' ), '.' ) ); ?></td><td><?php echo esc_html( wos_money( $l->unit_price, $cur ) ); ?></td><td><?php echo esc_html( wos_money( $l->amount, $cur ) ); ?></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<table class="totals" style="margin-top:12px">
			<tr><td></td><td>Subtotal</td><td><?php echo esc_html( wos_money( $inv->subtotal, $cur ) ); ?></td></tr>
			<?php if ( (float) $inv->gst_rate > 0 ) : ?><tr><td></td><td>GST <?php echo esc_html( (float) $inv->gst_rate ); ?>%</td><td><?php echo esc_html( wos_money( $inv->gst_amount, $cur ) ); ?></td></tr><?php endif; ?>
			<tr class="grand"><td></td><td>Total due</td><td><?php echo esc_html( wos_money( $inv->total, $cur ) ); ?></td></tr>
		</table>
		<?php if ( $inv->notes ) : ?><p><?php echo nl2br( esc_html( $inv->notes ) ); ?></p><?php endif; ?>
		<p class="muted"><?php echo nl2br( esc_html( wos_setting( 'payment_details' ) ) ); ?></p>
	</div></body></html>
	<?php
	exit;
} );
