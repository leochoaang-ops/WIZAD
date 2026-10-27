<?php
defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () {
	add_menu_page( 'WIZAD OS', 'WIZAD OS', WOS_CAP, 'wizad-os', 'wos_render_dashboard', 'dashicons-chart-area', 2 );
	$home = add_submenu_page( 'wizad-os', 'Home', 'Home', WOS_CAP, 'wizad-os', 'wos_render_dashboard' );
	// The app is home now. The old dashboard (income, expenses, GST) stays one click away until Sprint D.
	add_action( 'load-' . $home, function () {
		if ( empty( $_GET['classic'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- read-only view switch.
			wp_safe_redirect( wos_app_url() );
			exit;
		}
	} );
	add_submenu_page( 'wizad-os', 'Old dashboard', 'Old dashboard', WOS_CAP, 'admin.php?page=wizad-os&classic=1' );
	add_submenu_page( 'wizad-os', 'Delivery board', 'Board', WOS_CAP, 'wos-board', 'wos_render_board' );
	foreach ( array( 'clients', 'deals', 'projects' ) as $e ) {
		$def = wos_entity( $e );
		add_submenu_page( 'wizad-os', $def['plural'], $def['plural'], WOS_CAP, 'wos-' . $e, function () use ( $e ) {
			wos_render_entity_page( $e );
		} );
	}
	add_submenu_page( 'wizad-os', 'Invoices', 'Invoices', WOS_CAP, 'wos-invoices', 'wos_render_invoices_page' );
	add_submenu_page( 'wizad-os', 'Income and expenses', 'Ledger', WOS_CAP, 'wos-ledger', function () {
		wos_render_entity_page( 'ledger' );
	} );
	add_submenu_page( 'wizad-os', 'Settings', 'Settings', WOS_CAP, 'wos-settings', 'wos_render_settings' );
	$open = add_submenu_page( 'wizad-os', 'Open the app', 'Open the app →', WOS_CAP, 'wos-open-app', '__return_null' );
	add_action( 'load-' . $open, function () {
		wp_safe_redirect( wos_app_url() );
		exit;
	} );

	// This install is only for WIZAD OS: hide the blog screens.
	remove_menu_page( 'edit.php' );
	remove_menu_page( 'edit-comments.php' );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( false === strpos( $hook, 'wizad-os' ) && false === strpos( $hook, 'wos-' ) ) {
		return;
	}
	wp_enqueue_style( 'wos-admin', WOS_URL . 'assets/admin.css', array(), WOS_VERSION );
	if ( false !== strpos( $hook, 'wos-board' ) ) {
		wp_enqueue_script( 'wos-board', WOS_URL . 'assets/board.js', array(), WOS_VERSION, true );
		wp_localize_script( 'wos-board', 'wosBoard', array( 'ajax' => admin_url( 'admin-ajax.php' ) ) );
	}
} );

/* Land on the app after signing in, and from the old dashboard. */
add_filter( 'login_redirect', function ( $to, $requested, $user ) {
	if ( $user instanceof WP_User && user_can( $user, WOS_CAP ) && ( ! $requested || false !== strpos( $requested, 'wp-admin/' ) && false === strpos( $requested, 'page=' ) && false === strpos( $requested, 'profile.php' ) ) ) {
		return wos_app_url();
	}
	return $to;
}, 10, 3 );

add_action( 'load-index.php', function () {
	if ( current_user_can( WOS_CAP ) ) {
		wp_safe_redirect( wos_app_url() );
		exit;
	}
} );
