<?php
/**
 * Private-site hardening for an internal install.
 *
 * - Every page, feed and REST route needs a signed-in user.
 * - 5 failed sign-ins from one IP lock that IP out for 15 minutes.
 * - Sessions last 12 hours; no XML-RPC, no sign-ups, no search indexing.
 * - Admins must use two-step sign-in when the "Two Factor" plugin is active.
 * - A sign-in log, and an urgent email when an admin signs in from a new IP.
 */
defined( 'ABSPATH' ) || exit;

if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', true );
}

define( 'WOS_MAX_FAILS', 5 );
define( 'WOS_LOCKOUT_SECONDS', 15 * MINUTE_IN_SECONDS );
define( 'WOS_SESSION_SECONDS', 12 * HOUR_IN_SECONDS );

function wos_client_ip() {
	// REMOTE_ADDR only: forwarded headers can be forged by the visitor.
	return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
}

/* Sign-in required everywhere except the plain home page, which the theme shows as a holding page to everyone. */
add_action( 'template_redirect', function () {
	if ( is_front_page() && empty( $_GET ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	if ( ! is_user_logged_in() ) {
		auth_redirect();
	}
	wp_safe_redirect( wos_url( 'wizad-os' ) );
	exit;
}, 0 );

add_filter( 'rest_authentication_errors', function ( $result ) {
	if ( ! empty( $result ) ) {
		return $result;
	}
	if ( ! is_user_logged_in() ) {
		return new WP_Error( 'wos_private', 'This site is private.', array( 'status' => 401 ) );
	}
	return $result;
} );

add_filter( 'xmlrpc_enabled', '__return_false' );
add_action( 'init', function () {
	if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
		status_header( 403 );
		exit;
	}
}, 0 );
add_filter( 'xmlrpc_methods', '__return_empty_array' );
add_filter( 'wp_headers', function ( $headers ) {
	unset( $headers['X-Pingback'] );
	return $headers;
} );
add_filter( 'pre_option_users_can_register', '__return_zero' );
add_filter( 'pre_option_blog_public', '__return_zero' );
add_filter( 'robots_txt', function () {
	return "User-agent: *\nDisallow: /\n";
}, 99 );

add_action( 'send_headers', 'wos_security_headers' );
add_action( 'admin_init', 'wos_security_headers' );
add_action( 'login_init', 'wos_security_headers' );
function wos_security_headers() {
	if ( headers_sent() ) {
		return;
	}
	header( 'X-Robots-Tag: noindex, nofollow', true );
	header( 'X-Frame-Options: SAMEORIGIN', true );
	header( 'X-Content-Type-Options: nosniff', true );
	header( 'Referrer-Policy: same-origin', true );
}

/* Short sessions, also with "Remember me". */
add_filter( 'auth_cookie_expiration', function () {
	return WOS_SESSION_SECONDS;
}, 99 );

/* Same message for a wrong user name and a wrong password. */
add_filter( 'login_errors', function ( $error ) {
	if ( false !== strpos( (string) $error, 'too many' ) ) {
		return $error;
	}
	return '<strong>Error:</strong> the user name or password is not correct.';
} );

/* Lockout after repeated failures. */
function wos_lockout_key( $ip ) {
	return 'wos_fail_' . md5( $ip );
}

add_filter( 'authenticate', function ( $user ) {
	$ip    = wos_client_ip();
	$fails = (int) get_transient( wos_lockout_key( $ip ) );
	if ( $fails >= WOS_MAX_FAILS ) {
		return new WP_Error( 'wos_locked', '<strong>Error:</strong> too many failed sign-ins. Try again in 15 minutes.' );
	}
	return $user;
}, 30 );

add_action( 'wp_login_failed', function ( $username ) {
	$ip    = wos_client_ip();
	$key   = wos_lockout_key( $ip );
	$fails = (int) get_transient( $key ) + 1;
	set_transient( $key, $fails, WOS_LOCKOUT_SECONDS );
	wos_log_login( sanitize_user( $username ), $ip, false );
} );

add_action( 'wp_login', function ( $user_login, $user ) {
	$ip = wos_client_ip();
	delete_transient( wos_lockout_key( $ip ) );
	wos_log_login( $user_login, $ip, true );

	$known = (array) get_user_meta( $user->ID, 'wos_known_ips', true );
	if ( ! in_array( $ip, $known, true ) ) {
		if ( ! empty( $known ) && user_can( $user, 'manage_options' ) ) {
			$to = wos_setting( 'alert_email' ) ? wos_setting( 'alert_email' ) : $user->user_email;
			wp_mail(
				$to,
				'URGENT · WIZAD OS: admin sign-in from a new IP',
				sprintf( "User %s signed in to %s from a new IP address: %s at %s.\n\nIf this was not you, change the password now and sign out all sessions from the profile page.", $user_login, home_url(), $ip, wos_now() )
			);
		}
		$known[] = $ip;
		update_user_meta( $user->ID, 'wos_known_ips', array_slice( array_values( array_filter( $known ) ), -20 ) );
	}
}, 10, 2 );

function wos_log_login( $user, $ip, $ok ) {
	$log   = (array) get_option( 'wos_login_log', array() );
	$log[] = array(
		't'  => wos_now(),
		'u'  => $user,
		'ip' => $ip,
		'ok' => $ok ? 1 : 0,
	);
	update_option( 'wos_login_log', array_slice( $log, -100 ), false );
}

/* Two-step sign-in is required for admins once the Two Factor plugin is active. */
function wos_two_factor_status( $user_id = 0 ) {
	if ( ! class_exists( 'Two_Factor_Core' ) ) {
		return 'missing';
	}
	$user_id = $user_id ? $user_id : get_current_user_id();
	return Two_Factor_Core::is_user_using_two_factor( $user_id ) ? 'on' : 'off';
}

add_action( 'admin_init', function () {
	if ( wp_doing_ajax() || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( 'off' !== wos_two_factor_status() ) {
		return;
	}
	global $pagenow;
	if ( in_array( $pagenow, array( 'profile.php', 'user-edit.php' ), true ) ) {
		return;
	}
	wos_flash( 'Turn on two-step sign-in (authenticator app) before using WIZAD OS.', 'warning' );
	wp_safe_redirect( admin_url( 'profile.php#two-factor-options' ) );
	exit;
} );

add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	global $pagenow;
	if ( 'profile.php' === $pagenow ) {
		wos_render_flash();
	}
	if ( 'missing' === wos_two_factor_status() ) {
		echo '<div class="notice notice-warning"><p><strong>WIZAD OS:</strong> install and activate the free <em>Two Factor</em> plugin (Plugins → Add New → search "Two Factor") to protect this private site with two-step sign-in.</p></div>';
	}
	if ( ! is_ssl() && false === strpos( home_url(), 'localhost' ) ) {
		echo '<div class="notice notice-error"><p><strong>WIZAD OS:</strong> this site is not on HTTPS. Turn on SSL for the domain in Bluehost before entering real data.</p></div>';
	}
} );
