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

	foreach ( $sql as $q ) {
		dbDelta( $q );
	}
	update_option( 'wos_db_version', WOS_DB_VERSION );
}
