<?php
defined( 'ABSPATH' ) || exit;

function wos_settings_defaults() {
	return array(
		'business_name'   => 'WIZAD',
		'owner_name'      => 'Leo',
		'address'         => "British Columbia, Canada",
		'email'           => 'info@wizad.ca',
		'website'         => 'wizad.ca',
		'gst_registered'  => 0,
		'gst_number'      => '',
		'gst_rate'        => 5,
		'invoice_prefix'  => 'WZ',
		'payment_terms'   => 15,
		'payment_details' => 'Payment by e-Transfer or bank transfer. Details on request.',
		'fx_usd'          => 1.38,
		'fx_eur'          => 1.60,
		'leo_rate'        => 44.78,
		'alert_email'     => '',
	);
}

function wos_setting( $key ) {
	$s = wp_parse_args( get_option( 'wos_settings', array() ), wos_settings_defaults() );
	return isset( $s[ $key ] ) ? $s[ $key ] : null;
}

function wos_fx( $currency ) {
	switch ( strtoupper( (string) $currency ) ) {
		case 'USD':
			return (float) wos_setting( 'fx_usd' );
		case 'EUR':
			return (float) wos_setting( 'fx_eur' );
		default:
			return 1.0;
	}
}

function wos_money( $amount, $currency = 'CAD' ) {
	$symbol = array( 'CAD' => '$', 'USD' => 'US$', 'EUR' => '€' );
	$s      = isset( $symbol[ $currency ] ) ? $symbol[ $currency ] : $currency . ' ';
	$amount = (float) $amount;
	return ( $amount < 0 ? '−' : '' ) . $s . number_format( abs( $amount ), 2 );
}

function wos_today() {
	return current_time( 'Y-m-d' );
}

function wos_now() {
	return current_time( 'mysql' );
}

function wos_days_between( $from, $to = null ) {
	if ( empty( $from ) ) {
		return 0;
	}
	$to = $to ? $to : wos_today();
	return (int) floor( ( strtotime( $to ) - strtotime( $from ) ) / DAY_IN_SECONDS );
}

function wos_url( $page, $args = array() ) {
	return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
}

function wos_require_cap() {
	if ( ! current_user_can( WOS_CAP ) ) {
		wp_die( esc_html__( 'You do not have access to WIZAD OS.', 'wizad-os' ), 403 );
	}
}

/** Show a one-time notice after a redirect. */
function wos_flash( $message, $type = 'success' ) {
	set_transient( 'wos_flash_' . get_current_user_id(), array( $message, $type ), 60 );
}

function wos_render_flash() {
	$key = 'wos_flash_' . get_current_user_id();
	$f   = get_transient( $key );
	if ( $f ) {
		delete_transient( $key );
		printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $f[1] ), esc_html( $f[0] ) );
	}
}

function wos_options( $list ) {
	$lists = array(
		'client_status' => array(
			'lead'   => 'Lead',
			'active' => 'Active client',
			'past'   => 'Past client',
		),
		'deal_stage'    => array(
			'lead'      => 'Lead',
			'pilot'     => 'Business Pilot',
			'zenith'    => 'Zenith Goal held',
			'proposal'  => 'Proposal sent',
			'signed'    => 'Signed',
			'active'    => 'Active',
			'won'       => 'Closed: won',
			'lost'      => 'Closed: lost',
		),
		'project_type'  => array(
			'sprint'   => 'Sprint (fixed fee)',
			'retainer' => 'Monthly retainer',
			'hourly'   => 'Hourly',
			'rnd'      => 'R&D / internal',
			'gift'     => 'Unpaid / gift',
		),
		'project_status' => array(
			'planned' => 'Planned',
			'active'  => 'Active',
			'on_hold' => 'On hold',
			'done'    => 'Done',
		),
		'card_status'   => array(
			'todo'    => 'To do',
			'doing'   => 'Doing',
			'blocked' => 'Blocked (needs Leo)',
			'done'    => 'Done',
		),
		'invoice_status' => array(
			'draft' => 'Draft',
			'sent'  => 'Sent',
			'paid'  => 'Paid',
			'void'  => 'Void',
		),
		'currency'      => array(
			'CAD' => 'CAD',
			'USD' => 'USD',
			'EUR' => 'EUR',
		),
		'ledger_type'   => array(
			'income'  => 'Income',
			'expense' => 'Expense',
		),
		'cost_class'    => array(
			''         => '—',
			'fixed'    => 'Fixed',
			'variable' => 'Variable (direct)',
			'overhead' => 'Overhead',
		),
		// Categories follow the CRA T2125 lines so the year-end return is a copy.
		'category'      => array(
			'Sales'                    => 'Income: sales of services',
			'Other income'             => 'Income: other',
			'Advertising'              => 'Advertising (8521)',
			'Meals and entertainment'  => 'Meals and entertainment (8523)',
			'Insurance'                => 'Insurance (8690)',
			'Interest and bank'        => 'Interest and bank charges (8710)',
			'Business tax and fees'    => 'Business tax, fees, licences (8760)',
			'Office expenses'          => 'Office expenses (8810)',
			'Supplies'                 => 'Supplies (8811)',
			'Professional fees'        => 'Legal, accounting, professional fees (8860)',
			'Subcontractors'           => 'Management and admin fees / subcontractors (8871)',
			'Rent'                     => 'Rent (8910)',
			'Salaries'                 => 'Salaries and wages (9060)',
			'Travel'                   => 'Travel (9200)',
			'Telephone and utilities'  => 'Telephone and utilities (9220)',
			'Software and hosting'     => 'Other: software, hosting, domains (9270)',
			'Other expenses'           => 'Other expenses (9270)',
		),
	);
	return isset( $lists[ $list ] ) ? $lists[ $list ] : array();
}

function wos_label( $list, $key ) {
	$o = wos_options( $list );
	return isset( $o[ $key ] ) ? $o[ $key ] : $key;
}

/** id => label map for reference fields. */
function wos_ref_options( $entity ) {
	global $wpdb;
	switch ( $entity ) {
		case 'clients':
			$rows = $wpdb->get_results( 'SELECT id, code, name FROM ' . wos_table( 'clients' ) . ' ORDER BY name' );
			$out  = array();
			foreach ( $rows as $r ) {
				$out[ $r->id ] = $r->code ? $r->code . ' · ' . $r->name : $r->name;
			}
			return $out;
		case 'projects':
			$rows = $wpdb->get_results( 'SELECT id, code, name FROM ' . wos_table( 'projects' ) . ' ORDER BY code' );
			$out  = array();
			foreach ( $rows as $r ) {
				$out[ $r->id ] = $r->code ? $r->code . ' · ' . $r->name : $r->name;
			}
			return $out;
		case 'deals':
			$rows = $wpdb->get_results( 'SELECT id, title FROM ' . wos_table( 'deals' ) . ' ORDER BY title' );
			return wp_list_pluck( $rows, 'title', 'id' );
	}
	return array();
}
