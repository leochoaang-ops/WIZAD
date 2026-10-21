<?php
defined( 'ABSPATH' ) || exit;

add_action( 'admin_post_wos_export', function () {
	global $wpdb;
	wos_require_cap();
	check_admin_referer( 'wos_export' );
	$entity  = isset( $_GET['entity'] ) ? sanitize_key( $_GET['entity'] ) : '';
	$allowed = array( 'clients', 'deals', 'projects', 'cards', 'invoices', 'invoice_lines', 'ledger' );
	if ( ! in_array( $entity, $allowed, true ) ) {
		wp_die( 'Unknown table.' );
	}
	$rows = $wpdb->get_results( 'SELECT * FROM ' . wos_table( $entity ) . ' ORDER BY id', ARRAY_A );
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="wizad-os-' . $entity . '-' . wos_today() . '.csv"' );
	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" ); // So Excel reads UTF-8.
	if ( $rows ) {
		fputcsv( $out, array_keys( $rows[0] ) );
		foreach ( $rows as $r ) {
			// Stop spreadsheet formula injection from text fields.
			fputcsv( $out, array_map( function ( $v ) {
				return is_string( $v ) && preg_match( '/^[=+\-@]/', $v ) && ! is_numeric( $v ) ? "'" . $v : $v;
			}, $r ) );
		}
	}
	fclose( $out );
	exit;
} );
