<?php
/**
 * Update WOS from GitHub with one button, so nobody uploads zip files.
 *
 * Each site follows one branch of the WIZAD repository: staging follows the
 * working branch (test versions), the live site follows main (versions Leo
 * approved and merged). The button only appears when that branch holds a
 * newer version. WordPress's own plugin updater does the work, with its
 * backup and roll-back.
 */
defined( 'ABSPATH' ) || exit;

define( 'WOS_UPDATE_REPO', 'leochoaang-ops/WIZAD' );

/** The branch this site follows. */
function wos_update_branch() {
	$b = (string) get_option( 'wos_update_branch', '' );
	if ( wos_update_valid_branch( $b ) ) {
		return $b;
	}
	return wos_demo_allowed() ? 'claude/project-thread-g52jsz' : 'main';
}

function wos_update_valid_branch( $b ) {
	return (bool) preg_match( '#^[A-Za-z0-9._/-]{1,100}$#', (string) $b ) && false === strpos( $b, '..' );
}

/** Where to read the version and download the package. WOS_UPDATE_BASE points both at a test server. */
function wos_update_urls( $branch ) {
	if ( defined( 'WOS_UPDATE_BASE' ) && WOS_UPDATE_BASE ) {
		return array(
			'raw' => WOS_UPDATE_BASE . '/raw/',
			'zip' => WOS_UPDATE_BASE . '/package.zip',
		);
	}
	return array(
		'raw' => 'https://raw.githubusercontent.com/' . WOS_UPDATE_REPO . '/' . $branch . '/wizad-os/',
		'zip' => 'https://codeload.github.com/' . WOS_UPDATE_REPO . '/zip/refs/heads/' . $branch,
	);
}

/** The version on the branch and what is new in it. Cached for 10 minutes. */
function wos_update_check( $force = false ) {
	$branch = wos_update_branch();
	$cached = get_transient( 'wos_update_check' );
	if ( ! $force && is_array( $cached ) && $cached['branch'] === $branch ) {
		return $cached;
	}
	$urls = wos_update_urls( $branch );
	$out  = array( 'branch' => $branch, 'version' => '', 'notes' => array(), 'error' => '', 'checked_at' => wos_now() );
	$main = wp_remote_get( $urls['raw'] . 'wizad-os.php', array( 'timeout' => 10 ) );
	if ( is_wp_error( $main ) || 200 !== wp_remote_retrieve_response_code( $main ) ) {
		$out['error'] = 'Could not reach GitHub. Try again in a minute.';
	} elseif ( preg_match( '/^\s*\*\s*Version:\s*([0-9]+(?:\.[0-9]+)*)/m', wp_remote_retrieve_body( $main ), $m ) ) {
		$out['version'] = $m[1];
		$readme         = wp_remote_get( $urls['raw'] . 'readme.txt', array( 'timeout' => 10 ) );
		if ( ! is_wp_error( $readme ) && 200 === wp_remote_retrieve_response_code( $readme ) ) {
			// The changelog block for that version: its heading line, then "- " lines up to the next blank line.
			$lines = preg_split( '/\r\n|\n/', wp_remote_retrieve_body( $readme ) );
			$on    = false;
			foreach ( $lines as $line ) {
				if ( 0 === strpos( $line, $out['version'] . ' ' ) ) {
					$on = true;
					continue;
				}
				if ( $on ) {
					if ( '' === trim( $line ) ) {
						break;
					}
					$out['notes'][] = sanitize_text_field( ltrim( $line, '- ' ) );
				}
			}
			$out['notes'] = array_slice( $out['notes'], 0, 12 );
		}
	} else {
		$out['error'] = 'No WOS version found on the branch ' . $branch . '.';
	}
	set_transient( 'wos_update_check', $out, 10 * MINUTE_IN_SECONDS );
	return $out;
}

function wos_update_available( $check ) {
	return $check['version'] && version_compare( $check['version'], WOS_VERSION, '>' );
}

function wos_update_can() {
	return current_user_can( 'update_plugins' ) && current_user_can( WOS_CAP );
}

/** Run the update. Returns array( ok, message ). */
function wos_update_run( $by ) {
	$check = wos_update_check( true );
	if ( $check['error'] ) {
		return array( false, $check['error'] );
	}
	if ( ! wos_update_available( $check ) ) {
		return array( false, 'WOS ' . WOS_VERSION . ' is already the latest version on this site.' );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/misc.php';
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

	$plugin  = plugin_basename( WOS_FILE );
	$from    = WOS_VERSION;
	$package = wos_update_urls( $check['branch'] )['zip'];
	$active  = is_plugin_active( $plugin );

	// Offer the package to WordPress's updater for this one run only.
	$offer = function ( $t ) use ( $plugin, $check, $package ) {
		if ( ! is_object( $t ) ) {
			$t = new stdClass();
		}
		if ( ! isset( $t->response ) || ! is_array( $t->response ) ) {
			$t->response = array();
		}
		$t->response[ $plugin ] = (object) array(
			'slug'        => dirname( $plugin ),
			'plugin'      => $plugin,
			'new_version' => $check['version'],
			'package'     => $package,
			'url'         => 'https://github.com/' . WOS_UPDATE_REPO,
		);
		return $t;
	};
	// GitHub packs the whole repository; the plugin is the wizad-os folder inside it.
	$pick = function ( $source, $remote_source, $upgrader, $hook_extra = array() ) use ( $plugin ) {
		if ( is_wp_error( $source ) || ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $plugin ) {
			return $source;
		}
		$inner = trailingslashit( $source ) . 'wizad-os/';
		if ( is_dir( $inner ) && file_exists( $inner . 'wizad-os.php' ) ) {
			return $inner;
		}
		return file_exists( trailingslashit( $source ) . 'wizad-os.php' ) ? $source : new WP_Error( 'wos_package', 'The download does not contain WOS.' );
	};
	add_filter( 'site_transient_update_plugins', $offer );
	add_filter( 'upgrader_source_selection', $pick, 10, 4 );
	$skin     = new WP_Ajax_Upgrader_Skin();
	$upgrader = new Plugin_Upgrader( $skin );
	$result   = $upgrader->upgrade( $plugin );
	remove_filter( 'site_transient_update_plugins', $offer );
	remove_filter( 'upgrader_source_selection', $pick, 10 );

	// WordPress switches the plugin off while it swaps the files; switch it back on.
	if ( $active && ! is_plugin_active( $plugin ) ) {
		$list   = (array) get_option( 'active_plugins', array() );
		$list[] = $plugin;
		update_option( 'active_plugins', array_values( array_unique( $list ) ) );
	}
	delete_transient( 'wos_update_check' );

	$ok   = true === $result;
	$errs = $skin->get_error_messages();
	if ( ! $ok && ! $errs && is_wp_error( $result ) ) {
		$errs = $result->get_error_message();
	}
	$msg = $ok ? 'WOS updated from ' . $from . ' to ' . $check['version'] . '.' : 'The update did not finish; WOS ' . $from . ' is still running. ' . wp_strip_all_tags( is_array( $errs ) ? implode( ' ', $errs ) : (string) $errs );
	$log = get_option( 'wos_update_log', array() );
	$log = is_array( $log ) ? $log : array();
	array_unshift( $log, array( 'at' => wos_now(), 'by' => $by, 'from' => $from, 'to' => $check['version'], 'ok' => $ok, 'msg' => $msg ) );
	update_option( 'wos_update_log', array_slice( $log, 0, 20 ), false );
	return array( $ok, $msg );
}

add_action( 'admin_post_wos_update', function () {
	if ( ! wos_update_can() ) {
		wp_die( 'Only an administrator can update WOS.', 403 );
	}
	check_admin_referer( 'wos_update' );
	$do = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
	if ( 'check' === $do ) {
		$c = wos_update_check( true );
		wos_flash( $c['error'] ? $c['error'] : ( wos_update_available( $c ) ? 'WOS ' . $c['version'] . ' is ready to install.' : 'WOS ' . WOS_VERSION . ' is the latest version.' ), $c['error'] ? 'warning' : 'success' );
	} else {
		list( $ok, $msg ) = wos_update_run( wp_get_current_user()->display_name );
		wos_flash( $msg, $ok ? 'success' : 'error' );
	}
	wp_safe_redirect( wos_app_url( 'settings' ) . '#update' );
	exit;
} );

// The same, for Claude working from the WIZAD project: GET checks, POST installs.
add_action( 'rest_api_init', function () {
	register_rest_route( 'wos/v1', '/update', array(
		array(
			'methods'             => 'GET',
			'permission_callback' => 'wos_update_can',
			'callback'            => function () {
				$c = wos_update_check( true );
				return array( 'running' => WOS_VERSION, 'branch' => $c['branch'], 'latest' => $c['version'], 'available' => wos_update_available( $c ), 'error' => $c['error'] );
			},
		),
		array(
			'methods'             => 'POST',
			'permission_callback' => 'wos_update_can',
			'callback'            => function () {
				list( $ok, $msg ) = wos_update_run( wp_get_current_user()->display_name . ' (via Claude)' );
				return new WP_REST_Response( array( 'ok' => $ok, 'message' => $msg ), $ok ? 200 : 500 );
			},
		),
	) );
} );
