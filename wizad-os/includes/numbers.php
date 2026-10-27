<?php
/**
 * Numbers Leo can change: rates, tax, thresholds and roles.
 *
 * Nothing here is fixed in code. Each save is a version with an "effective
 * from" date, and wos_num() returns the value that applied on a given date,
 * so a new rate changes the future and never the past.
 */
defined( 'ABSPATH' ) || exit;

/** key => array( label, unit, group, default, note ). */
function wos_number_fields() {
	return array(
		'overhead_hour'    => array( 'Overhead share per hour', '$/h', 'people', 2.5, 'Tools, hosting and insurance, spread over hours worked' ),
		'margin_min'       => array( 'Minimum margin', '%', 'pricing', 40, 'Below this, a quote is flagged red' ),
		'margin_target'    => array( 'Target margin', '%', 'pricing', 60, '' ),
		'gst_rate'         => array( 'GST rate', '%', 'tax', 5, 'Charged once WIZAD is registered' ),
		'pst_rate'         => array( 'PST rate', '%', 'tax', 0, 'BC PST, only on services it applies to' ),
		'fx_usd'           => array( 'CAD per 1 USD', 'rate', 'tax', 1.38, '' ),
		'fx_eur'           => array( 'CAD per 1 EUR', 'rate', 'tax', 1.60, '' ),
		'payment_terms'    => array( 'Payment terms', 'days', 'tax', 15, '' ),
		'late_interest'    => array( 'Late interest', '%/yr', 'tax', 18, 'Stated yearly, as the Interest Act requires' ),
		'budget_yellow'    => array( 'Sprint budget: yellow at', '%', 'warnings', 75, '' ),
		'budget_red'       => array( 'Sprint budget: red at', '%', 'warnings', 90, '' ),
		'decision_yellow'  => array( 'Decision yellow after', 'days', 'warnings', 3, '' ),
		'decision_red'     => array( 'Decision red after', 'days', 'warnings', 7, 'Past its due date is red too' ),
		'aside_max'        => array( 'Longest set-aside', 'days', 'warnings', 14, '' ),
		'health_decisions' => array( 'Health: decisions on time', 'points', 'health', 25, '' ),
		'health_sprints'   => array( 'Health: sprints in budget', 'points', 'health', 25, '' ),
		'health_paid'      => array( 'Health: getting paid', 'points', 'health', 25, '' ),
		'health_pipeline'  => array( 'Health: pipeline moving', 'points', 'health', 25, '' ),
	);
}

function wos_number_groups() {
	return array(
		'people'   => 'People and cost rates',
		'pricing'  => 'Pricing',
		'tax'      => 'Tax and money',
		'warnings' => 'Warnings',
		'health'   => 'Health score',
	);
}

/** The role inventory approved on Oct 27, 2026. Only the first three are active. */
function wos_default_roles() {
	return array(
		'lead_consultant'    => array( 'name' => 'Lead Consultant', 'type' => 'Internal (Leo)', 'status' => 'active', 'rate' => 44.78 ),
		'consultant'         => array( 'name' => 'Consultant', 'type' => 'External', 'status' => 'active', 'rate' => 0 ),
		'associate'          => array( 'name' => 'Associate', 'type' => 'External', 'status' => 'active', 'rate' => 0 ),
		'specialist'         => array( 'name' => 'Specialist / Subject Matter Expert', 'type' => 'External, per job', 'status' => 'ready', 'rate' => 0 ),
		'senior_consultant'  => array( 'name' => 'Senior Consultant', 'type' => 'External', 'status' => 'ready', 'rate' => 0 ),
		'engagement_manager' => array( 'name' => 'Engagement Manager', 'type' => 'Internal or external', 'status' => 'ready', 'rate' => 0 ),
		'analyst'            => array( 'name' => 'Analyst', 'type' => 'External', 'status' => 'ready', 'rate' => 0 ),
		'subcontractor'      => array( 'name' => 'Subcontractor (partner firm)', 'type' => 'External firm', 'status' => 'ready', 'rate' => 0 ),
		'advisor'            => array( 'name' => 'Advisor', 'type' => 'External', 'status' => 'ready', 'rate' => 0 ),
	);
}

/** Roles without rates (name, type, status). Rates live in the numbers versions. */
function wos_roles() {
	$roles = get_option( 'wos_roles' );
	if ( ! is_array( $roles ) || ! $roles ) {
		$roles = array();
		foreach ( wos_default_roles() as $slug => $r ) {
			unset( $r['rate'] );
			$roles[ $slug ] = $r;
		}
	}
	return $roles;
}

/** Every version, oldest first. Creates the first one from 0.1.x settings. */
function wos_number_versions() {
	$v = get_option( 'wos_numbers' );
	if ( is_array( $v ) && $v ) {
		return $v;
	}
	$values = array();
	foreach ( wos_number_fields() as $key => $f ) {
		$values[ $key ] = $f[3];
	}
	foreach ( wos_default_roles() as $slug => $r ) {
		$values[ 'rate_' . $slug ] = $r['rate'];
	}
	// Keep what was saved under 0.1.x.
	$old = get_option( 'wos_settings', array() );
	foreach ( array( 'gst_rate', 'fx_usd', 'fx_eur', 'payment_terms' ) as $k ) {
		if ( isset( $old[ $k ] ) && '' !== $old[ $k ] ) {
			$values[ $k ] = (float) $old[ $k ];
		}
	}
	if ( isset( $old['leo_rate'] ) && (float) $old['leo_rate'] > 0 ) {
		$values['rate_lead_consultant'] = (float) $old['leo_rate'];
	}
	$v = array(
		array(
			'from'   => '2026-01-01',
			'values' => $values,
			'by'     => 'WIZAD OS',
			'at'     => wos_now(),
		),
	);
	update_option( 'wos_numbers', $v, false );
	return $v;
}

/** The value of a number on a date (default today). */
function wos_num( $key, $date = null ) {
	$date  = $date ? $date : wos_today();
	$value = null;
	foreach ( wos_number_versions() as $ver ) {
		if ( $ver['from'] <= $date && isset( $ver['values'][ $key ] ) ) {
			$value = $ver['values'][ $key ];
		}
	}
	if ( null === $value ) {
		$fields = wos_number_fields();
		$value  = isset( $fields[ $key ] ) ? $fields[ $key ][3] : 0;
	}
	return (float) $value;
}

/** Cost per hour for a role on a date: its rate plus the overhead share. */
function wos_role_cost( $slug, $date = null ) {
	return wos_num( 'rate_' . $slug, $date ) + wos_num( 'overhead_hour', $date );
}

add_action( 'admin_post_wos_numbers_save', function () {
	wos_require_cap();
	check_admin_referer( 'wos_numbers_save' );
	$in   = isset( $_POST['n'] ) && is_array( $_POST['n'] ) ? wp_unslash( $_POST['n'] ) : array(); // phpcs:ignore -- cast to float below.
	$from = isset( $_POST['from'] ) ? sanitize_text_field( wp_unslash( $_POST['from'] ) ) : '';
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) ) {
		$from = wos_today();
	}

	$values = array();
	foreach ( array_keys( wos_number_fields() ) as $key ) {
		$values[ $key ] = isset( $in[ $key ] ) ? (float) $in[ $key ] : wos_num( $key, $from );
	}
	$roles = wos_roles();
	$rin   = isset( $_POST['role'] ) && is_array( $_POST['role'] ) ? wp_unslash( $_POST['role'] ) : array(); // phpcs:ignore -- cleaned below.
	foreach ( $roles as $slug => $r ) {
		$values[ 'rate_' . $slug ] = isset( $rin[ $slug ]['rate'] ) ? (float) $rin[ $slug ]['rate'] : wos_num( 'rate_' . $slug, $from );
		if ( isset( $rin[ $slug ] ) ) {
			$roles[ $slug ]['status'] = ! empty( $rin[ $slug ]['active'] ) ? 'active' : 'ready';
		}
	}
	update_option( 'wos_roles', $roles, false );

	// One version per date: saving twice on the same date replaces it.
	$versions = array_values( array_filter( wos_number_versions(), function ( $v ) use ( $from ) {
		return $v['from'] !== $from;
	} ) );
	$user       = wp_get_current_user();
	$versions[] = array(
		'from'   => $from,
		'values' => $values,
		'by'     => $user->display_name,
		'at'     => wos_now(),
	);
	usort( $versions, function ( $a, $b ) {
		return strcmp( $a['from'], $b['from'] );
	} );
	update_option( 'wos_numbers', $versions, false );

	wos_flash( 'Saved. The new numbers apply from ' . $from . '; earlier records keep the old ones.' );
	wp_safe_redirect( wos_app_url( 'settings' ) );
	exit;
} );
