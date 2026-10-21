<?php
/**
 * Plugin Name: WIZAD OS
 * Description: WIZAD's internal operating system: clients, deals, projects, Kanban, invoices and ledger. Built for a private install such as os.wizad.ca.
 * Version: 0.1.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: WIZAD
 * License: Proprietary
 * Text Domain: wizad-os
 */

defined( 'ABSPATH' ) || exit;

define( 'WOS_VERSION', '0.1.0' );
define( 'WOS_DB_VERSION', '1' );
define( 'WOS_FILE', __FILE__ );
define( 'WOS_DIR', plugin_dir_path( __FILE__ ) );
define( 'WOS_URL', plugin_dir_url( __FILE__ ) );
define( 'WOS_CAP', 'wos_manage' );

require_once WOS_DIR . 'includes/helpers.php';
require_once WOS_DIR . 'includes/schema.php';
require_once WOS_DIR . 'includes/security.php';
require_once WOS_DIR . 'includes/entities.php';
require_once WOS_DIR . 'includes/crud.php';
require_once WOS_DIR . 'includes/board.php';
require_once WOS_DIR . 'includes/invoices.php';
require_once WOS_DIR . 'includes/dashboard.php';
require_once WOS_DIR . 'includes/settings.php';
require_once WOS_DIR . 'includes/export.php';
require_once WOS_DIR . 'includes/seed.php';
require_once WOS_DIR . 'includes/admin.php';

register_activation_hook( __FILE__, 'wos_activate' );

function wos_activate() {
	// This plugin makes the whole site private; never on the public website.
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	if ( in_array( $host, array( 'wizad.ca', 'www.wizad.ca' ), true ) ) {
		wp_die( 'WIZAD OS makes the whole site private, so it must not run on the public website. Install it on os.wizad.ca instead.', 'WIZAD OS', array( 'back_link' => true ) );
	}
	wos_install_schema();
	$admin = get_role( 'administrator' );
	if ( $admin ) {
		$admin->add_cap( WOS_CAP );
	}
	// A private install: no sign-ups, hidden from search engines.
	update_option( 'users_can_register', 0 );
	update_option( 'blog_public', 0 );
}

add_action( 'plugins_loaded', function () {
	if ( get_option( 'wos_db_version' ) !== WOS_DB_VERSION ) {
		wos_install_schema();
	}
} );
