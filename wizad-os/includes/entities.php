<?php
/**
 * Field definitions for the records edited through the generic list/form screens.
 * type: text, email, textarea, select (options list), ref (another entity), number, money, date, percent.
 */
defined( 'ABSPATH' ) || exit;

function wos_entities() {
	return array(
		'clients'  => array(
			'singular' => 'Client',
			'plural'   => 'Clients',
			'order'    => 'name ASC',
			'search'   => array( 'code', 'name', 'contact_name', 'email' ),
			'filter'   => array( 'status', 'client_status' ),
			'fields'   => array(
				'code'         => array( 'label' => 'Code', 'type' => 'text', 'list' => true, 'help' => 'Short code used on projects and invoices, e.g. PHG.' ),
				'name'         => array( 'label' => 'Name', 'type' => 'text', 'list' => true, 'required' => true ),
				'status'       => array( 'label' => 'Status', 'type' => 'select', 'options' => 'client_status', 'list' => true ),
				'contact_name' => array( 'label' => 'Contact', 'type' => 'text', 'list' => true ),
				'email'        => array( 'label' => 'Email', 'type' => 'email', 'list' => true ),
				'phone'        => array( 'label' => 'Phone', 'type' => 'text' ),
				'country'      => array( 'label' => 'Country', 'type' => 'text', 'list' => true ),
				'currency'     => array( 'label' => 'Currency', 'type' => 'select', 'options' => 'currency' ),
				'source'       => array( 'label' => 'Source', 'type' => 'text', 'help' => 'Where the client came from: referral, LinkedIn, REV, website…' ),
				'address'      => array( 'label' => 'Billing address', 'type' => 'textarea' ),
				'notes'        => array( 'label' => 'Notes', 'type' => 'textarea' ),
			),
		),
		'deals'    => array(
			'singular' => 'Deal',
			'plural'   => 'Deals',
			'order'    => 'next_step_date IS NULL, next_step_date ASC',
			'search'   => array( 'title', 'next_step' ),
			'filter'   => array( 'stage', 'deal_stage' ),
			'fields'   => array(
				'title'          => array( 'label' => 'Deal', 'type' => 'text', 'list' => true, 'required' => true ),
				'client_id'      => array( 'label' => 'Client', 'type' => 'ref', 'ref' => 'clients', 'list' => true ),
				'stage'          => array( 'label' => 'Stage', 'type' => 'select', 'options' => 'deal_stage', 'list' => true ),
				'value'          => array( 'label' => 'Value', 'type' => 'money', 'list' => true, 'help' => 'Total expected value of the deal.' ),
				'currency'       => array( 'label' => 'Currency', 'type' => 'select', 'options' => 'currency' ),
				'probability'    => array( 'label' => 'Win probability %', 'type' => 'percent', 'list' => true ),
				'next_step'      => array( 'label' => 'Next step', 'type' => 'text', 'list' => true, 'required' => true, 'help' => 'Every open deal has a next step and a date.' ),
				'next_step_date' => array( 'label' => 'Next step date', 'type' => 'date', 'list' => true ),
				'source'         => array( 'label' => 'Source', 'type' => 'text' ),
				'notes'          => array( 'label' => 'Notes', 'type' => 'textarea' ),
			),
		),
		'projects' => array(
			'singular' => 'Project',
			'plural'   => 'Projects',
			'order'    => 'code ASC',
			'search'   => array( 'code', 'name' ),
			'filter'   => array( 'status', 'project_status' ),
			'fields'   => array(
				'code'         => array( 'label' => 'Code', 'type' => 'text', 'list' => true, 'help' => 'e.g. PHG-1, SC-2.' ),
				'name'         => array( 'label' => 'Name', 'type' => 'text', 'list' => true, 'required' => true ),
				'client_id'    => array( 'label' => 'Client', 'type' => 'ref', 'ref' => 'clients', 'list' => true ),
				'deal_id'      => array( 'label' => 'Deal', 'type' => 'ref', 'ref' => 'deals' ),
				'type'         => array( 'label' => 'Type', 'type' => 'select', 'options' => 'project_type', 'list' => true ),
				'status'       => array( 'label' => 'Status', 'type' => 'select', 'options' => 'project_status', 'list' => true ),
				'fee'          => array( 'label' => 'Fee', 'type' => 'money', 'list' => true, 'help' => 'Fixed fee, or the monthly fee for a retainer.' ),
				'currency'     => array( 'label' => 'Currency', 'type' => 'select', 'options' => 'currency' ),
				'hours_agreed' => array( 'label' => 'Hours agreed', 'type' => 'number', 'list' => true ),
				'start_date'   => array( 'label' => 'Start', 'type' => 'date', 'list' => true ),
				'end_date'     => array( 'label' => 'End', 'type' => 'date' ),
				'notes'        => array( 'label' => 'Notes / scope', 'type' => 'textarea' ),
			),
		),
		'ledger'   => array(
			'singular' => 'Ledger entry',
			'plural'   => 'Income and expenses',
			'order'    => 'entry_date DESC, id DESC',
			'search'   => array( 'payee', 'description', 'category' ),
			'filter'   => array( 'type', 'ledger_type' ),
			'fields'   => array(
				'entry_date'  => array( 'label' => 'Date', 'type' => 'date', 'list' => true, 'required' => true ),
				'type'        => array( 'label' => 'Type', 'type' => 'select', 'options' => 'ledger_type', 'list' => true ),
				'category'    => array( 'label' => 'Category (T2125)', 'type' => 'select', 'options' => 'category', 'list' => true ),
				'cost_class'  => array( 'label' => 'Cost class', 'type' => 'select', 'options' => 'cost_class', 'help' => 'For expenses: fixed, variable (direct to a project) or overhead.' ),
				'payee'       => array( 'label' => 'Payee / payer', 'type' => 'text', 'list' => true ),
				'description' => array( 'label' => 'Description', 'type' => 'text', 'list' => true ),
				'amount'      => array( 'label' => 'Amount', 'type' => 'money', 'list' => true, 'required' => true, 'help' => 'Total paid or received, in the currency below.' ),
				'currency'    => array( 'label' => 'Currency', 'type' => 'select', 'options' => 'currency' ),
				'amount_cad'  => array( 'label' => 'Amount (CAD)', 'type' => 'money', 'list' => true, 'readonly' => true ),
				'gst'         => array( 'label' => 'GST included', 'type' => 'money' ),
				'client_id'   => array( 'label' => 'Client', 'type' => 'ref', 'ref' => 'clients' ),
				'project_id'  => array( 'label' => 'Project', 'type' => 'ref', 'ref' => 'projects', 'list' => true ),
				'receipt'     => array( 'label' => 'Receipt', 'type' => 'file', 'list' => true, 'help' => 'Photo or PDF. Stored in a private folder, never public.' ),
			),
		),
	);
}

function wos_entity( $key ) {
	$e = wos_entities();
	return isset( $e[ $key ] ) ? $e[ $key ] : null;
}
