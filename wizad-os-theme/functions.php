<?php
defined( 'ABSPATH' ) || exit;

define( 'WIZAD_OS_THEME_VERSION', '0.1.1' );

function wizad_os_theme_fonts_url() {
	return 'https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700&family=Public+Sans:wght@400;500;600&display=swap';
}

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'wizad-os-fonts', wizad_os_theme_fonts_url(), array(), null );
	wp_enqueue_style( 'wizad-os-theme', get_stylesheet_uri(), array(), WIZAD_OS_THEME_VERSION );
} );

// A holding page needs none of the front-end extras.
add_action( 'after_setup_theme', function () {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'rest_output_link_wp_head' );
	remove_action( 'wp_head', 'feed_links', 2 );
	remove_action( 'wp_head', 'feed_links_extra', 3 );
	add_theme_support( 'title-tag' );
} );
add_filter( 'show_admin_bar', '__return_false' );

function wizad_os_theme_mark() {
	echo '<svg viewBox="0 0 332 332" role="img" aria-label="WIZAD"><g transform="translate(0 15)"><path fill="var(--slate)" d="M0 0H160V172H83V302H0Z"/><rect x="172" y="0" width="160" height="172" fill="var(--accent)"/><rect x="95" y="184" width="237" height="118" fill="var(--slate)"/></g></svg>';
}

/* The sign-in screen is styled by the WIZAD OS plugin, so it matches the app. */
