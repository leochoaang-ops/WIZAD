<?php
defined( 'ABSPATH' ) || exit;

function wos_table( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'wos_' . $name;
}

function wos_install_schema() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$c = $wpdb->get_charset_collate();

	$sql = array();

	$sql[] = 'CREATE TABLE ' . wos_table( 'clients' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		code varchar(20) NOT NULL DEFAULT '',
		name varchar(190) NOT NULL DEFAULT '',
		contact_name varchar(190) NOT NULL DEFAULT '',
		email varchar(190) NOT NULL DEFAULT '',
		phone varchar(50) NOT NULL DEFAULT '',
		country varchar(60) NOT NULL DEFAULT '',
		currency varchar(3) NOT NULL DEFAULT 'CAD',
		status varchar(20) NOT NULL DEFAULT 'lead',
		source varchar(60) NOT NULL DEFAULT '',
		address text NULL,
		notes text NULL,
		created_at datetime NULL,
		updated_at datetime NULL,
		PRIMARY KEY  (id),
		KEY status (status)
	) $c;";

	$sql[] = 'CREATE TABLE ' . wos_table( 'deals' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		client_id bigint(20) unsigned NOT NULL DEFAULT 0,
		title varchar(190) NOT NULL DEFAULT '',
		stage varchar(20) NOT NULL DEFAULT 'lead',
		value decimal(12,2) NOT NULL DEFAULT 0,
		currency varchar(3) NOT NULL DEFAULT 'CAD',
		probability tinyint(3) unsigned NOT NULL DEFAULT 10,
		next_step varchar(255) NOT NULL DEFAULT '',
		next_step_date date NULL,
		source varchar(60) NOT NULL DEFAULT '',
		notes text NULL,
		created_at datetime NULL,
		updated_at datetime NULL,
		PRIMARY KEY  (id),
		KEY client_id (client_id),
		KEY stage (stage)
	) $c;";

	$sql[] = 'CREATE TABLE ' . wos_table( 'projects' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		code varchar(20) NOT NULL DEFAULT '',
		client_id bigint(20) unsigned NOT NULL DEFAULT 0,
		deal_id bigint(20) unsigned NOT NULL DEFAULT 0,
		name varchar(190) NOT NULL DEFAULT '',
		type varchar(20) NOT NULL DEFAULT 'sprint',
		status varchar(20) NOT NULL DEFAULT 'planned',
		fee decimal(12,2) NOT NULL DEFAULT 0,
		currency varchar(3) NOT NULL DEFAULT 'CAD',
		hours_agreed decimal(8,2) NOT NULL DEFAULT 0,
		start_date date NULL,
		end_date date NULL,
		notes text NULL,
		created_at datetime NULL,
		updated_at datetime NULL,
		PRIMARY KEY  (id),
		KEY client_id (client_id)
	) $c;";

	$sql[] = 'CREATE TABLE ' . wos_table( 'cards' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		project_id bigint(20) unsigned NOT NULL DEFAULT 0,
		title varchar(255) NOT NULL DEFAULT '',
		status varchar(20) NOT NULL DEFAULT 'todo',
		owner varchar(60) NOT NULL DEFAULT '',
		due_date date NULL,
		hours_est decimal(8,2) NOT NULL DEFAULT 0,
		blocker_note varchar(255) NOT NULL DEFAULT '',
		blocked_since date NULL,
		okr varchar(120) NOT NULL DEFAULT '',
		tag varchar(30) NOT NULL DEFAULT '',
		position int(11) NOT NULL DEFAULT 0,
		done_at datetime NULL,
		created_at datetime NULL,
		updated_at datetime NULL,
		PRIMARY KEY  (id),
		KEY status (status)
	) $c;";

	$sql[] = 'CREATE TABLE ' . wos_table( 'invoices' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		number varchar(30) NOT NULL DEFAULT '',
		client_id bigint(20) unsigned NOT NULL DEFAULT 0,
		project_id bigint(20) unsigned NOT NULL DEFAULT 0,
		issue_date date NULL,
		due_date date NULL,
		currency varchar(3) NOT NULL DEFAULT 'CAD',
		subtotal decimal(12,2) NOT NULL DEFAULT 0,
		gst_rate decimal(5,2) NOT NULL DEFAULT 0,
		gst_amount decimal(12,2) NOT NULL DEFAULT 0,
		total decimal(12,2) NOT NULL DEFAULT 0,
		status varchar(20) NOT NULL DEFAULT 'draft',
		paid_date date NULL,
		notes text NULL,
		created_at datetime NULL,
		updated_at datetime NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY number (number),
		KEY client_id (client_id),
		KEY status (status)
	) $c;";

	$sql[] = 'CREATE TABLE ' . wos_table( 'invoice_lines' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		invoice_id bigint(20) unsigned NOT NULL DEFAULT 0,
		description varchar(255) NOT NULL DEFAULT '',
		qty decimal(10,2) NOT NULL DEFAULT 1,
		unit_price decimal(12,2) NOT NULL DEFAULT 0,
		amount decimal(12,2) NOT NULL DEFAULT 0,
		position int(11) NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		KEY invoice_id (invoice_id)
	) $c;";

	$sql[] = 'CREATE TABLE ' . wos_table( 'ledger' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		entry_date date NULL,
		type varchar(10) NOT NULL DEFAULT 'expense',
		category varchar(60) NOT NULL DEFAULT '',
		cost_class varchar(20) NOT NULL DEFAULT '',
		payee varchar(190) NOT NULL DEFAULT '',
		description varchar(255) NOT NULL DEFAULT '',
		amount decimal(12,2) NOT NULL DEFAULT 0,
		currency varchar(3) NOT NULL DEFAULT 'CAD',
		fx_rate decimal(10,4) NOT NULL DEFAULT 1,
		amount_cad decimal(12,2) NOT NULL DEFAULT 0,
		gst decimal(12,2) NOT NULL DEFAULT 0,
		client_id bigint(20) unsigned NOT NULL DEFAULT 0,
		project_id bigint(20) unsigned NOT NULL DEFAULT 0,
		invoice_id bigint(20) unsigned NOT NULL DEFAULT 0,
		receipt varchar(255) NOT NULL DEFAULT '',
		created_at datetime NULL,
		updated_at datetime NULL,
		PRIMARY KEY  (id),
		KEY entry_date (entry_date),
		KEY type (type)
	) $c;";

	$sql[] = 'CREATE TABLE ' . wos_table( 'decisions' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		area varchar(120) NOT NULL DEFAULT '',
		question varchar(255) NOT NULL DEFAULT '',
		context text NULL,
		options longtext NULL,
		recommended tinyint(3) NOT NULL DEFAULT -1,
		due_date date NULL,
		link varchar(255) NOT NULL DEFAULT '',
		why_now text NULL,
		numbers text NULL,
		read_first text NULL,
		lead_id bigint(20) unsigned NOT NULL DEFAULT 0,
		kind varchar(30) NOT NULL DEFAULT '',
		status varchar(20) NOT NULL DEFAULT 'waiting',
		aside_until date NULL,
		answer text NULL,
		answer_option tinyint(3) NOT NULL DEFAULT -1,
		added_by varchar(60) NOT NULL DEFAULT '',
		decided_by varchar(60) NOT NULL DEFAULT '',
		decided_at datetime NULL,
		created_at datetime NULL,
		updated_at datetime NULL,
		PRIMARY KEY  (id),
		KEY status (status)
	) $c;";

	/* Sprint 0 with a client: assessment to signed SOW. The JSON columns hold the parts of the lead file. */
	$sql[] = 'CREATE TABLE ' . wos_table( 'leads' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		name varchar(190) NOT NULL DEFAULT '',
		contact_name varchar(190) NOT NULL DEFAULT '',
		email varchar(190) NOT NULL DEFAULT '',
		phone varchar(50) NOT NULL DEFAULT '',
		contacts longtext NULL,
		source varchar(120) NOT NULL DEFAULT '',
		introducer varchar(120) NOT NULL DEFAULT '',
		owner varchar(60) NOT NULL DEFAULT '',
		stage varchar(20) NOT NULL DEFAULT 'lead',
		level varchar(1) NOT NULL DEFAULT '',
		scores longtext NULL,
		answers longtext NULL,
		value decimal(12,2) NOT NULL DEFAULT 0,
		win tinyint(3) unsigned NOT NULL DEFAULT 20,
		next_step varchar(255) NOT NULL DEFAULT '',
		next_date date NULL,
		checks longtext NULL,
		s0_hours decimal(8,2) NOT NULL DEFAULT 0,
		prelim longtext NULL,
		intake longtext NULL,
		summary longtext NULL,
		token varchar(40) NOT NULL DEFAULT '',
		ref varchar(64) NOT NULL DEFAULT '',
		seg varchar(20) NOT NULL DEFAULT '',
		loc varchar(20) NOT NULL DEFAULT '',
		client_id bigint(20) unsigned NOT NULL DEFAULT 0,
		log longtext NULL,
		notes text NULL,
		created_at datetime NULL,
		updated_at datetime NULL,
		PRIMARY KEY  (id),
		KEY stage (stage),
		KEY token (token),
		KEY ref (ref)
		) $c;";

		$sql[] = 'CREATE TABLE ' . wos_table( 'surveys' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		sid varchar(40) NOT NULL DEFAULT '',
		lead_ref varchar(64) NOT NULL DEFAULT '',
		form_version varchar(10) NOT NULL DEFAULT '',
		source varchar(20) NOT NULL DEFAULT '',
		stage tinyint(3) unsigned NOT NULL DEFAULT 0,
		name varchar(190) NOT NULL DEFAULT '',
		business varchar(190) NOT NULL DEFAULT '',
		email varchar(190) NOT NULL DEFAULT '',
		consent_at datetime NULL,
		utm_source varchar(120) NOT NULL DEFAULT '',
		utm_medium varchar(120) NOT NULL DEFAULT '',
		utm_campaign varchar(120) NOT NULL DEFAULT '',
		answers longtext NULL,
		computed longtext NULL,
		started_at datetime NULL,
		completed_at datetime NULL,
		lead_id bigint(20) unsigned NOT NULL DEFAULT 0,
		booked_date date NULL,
		cta varchar(20) NOT NULL DEFAULT '',
		cta_at datetime NULL,
		cta_done datetime NULL,
		created_at datetime NULL,
		updated_at datetime NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY sid (sid),
		KEY lead_ref (lead_ref)
		) $c;";

	$sql[] = 'CREATE TABLE ' . wos_table( 'chats' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		scope varchar(40) NOT NULL DEFAULT '',
		role varchar(10) NOT NULL DEFAULT 'user',
		content longtext NULL,
		user_name varchar(60) NOT NULL DEFAULT '',
		tokens_in int(11) NOT NULL DEFAULT 0,
		tokens_out int(11) NOT NULL DEFAULT 0,
		created_at datetime NULL,
		PRIMARY KEY  (id),
		KEY scope (scope)
	) $c;";

	foreach ( $sql as $q ) {
		dbDelta( $q );
	}
	update_option( 'wos_db_version', WOS_DB_VERSION );
}
