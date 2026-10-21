<?php
/**
 * Starter data from the WIZAD financial model and dashboard (Oct 21, 2026).
 */
defined( 'ABSPATH' ) || exit;

add_action( 'admin_post_wos_seed', function () {
	global $wpdb;
	wos_require_cap();
	check_admin_referer( 'wos_seed' );
	if ( (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . wos_table( 'clients' ) ) > 0 ) {
		wos_flash( 'Starter data not loaded: there are already clients.', 'warning' );
		wp_safe_redirect( wos_url( 'wos-settings' ) );
		exit;
	}
	wos_seed();
	wos_flash( 'Starter data loaded. Check every amount marked "placeholder".' );
	wp_safe_redirect( wos_url( 'wizad-os' ) );
	exit;
} );

function wos_seed() {
	global $wpdb;
	$now = wos_now();
	$ins = function ( $table, $row ) use ( $wpdb, $now ) {
		$row['created_at'] = $now;
		$row['updated_at'] = $now;
		$wpdb->insert( wos_table( $table ), $row );
		return (int) $wpdb->insert_id;
	};

	$sc   = $ins( 'clients', array( 'code' => 'SC', 'name' => 'SAOCHI.de', 'status' => 'active', 'country' => 'Germany', 'currency' => 'CAD', 'notes' => 'Signed. Sprint 2 in progress. Placeholder: confirm fees and invoice currency.' ) );
	$phg  = $ins( 'clients', array( 'code' => 'PHG', 'name' => 'Pivot Homes Group', 'status' => 'active', 'country' => 'Canada', 'currency' => 'CAD', 'notes' => 'B2B retainer from Nov 1, 2026. Plan and price to agree by Oct 31.' ) );
	$vibl = $ins( 'clients', array( 'code' => 'VIBL', 'name' => 'Vancouver Island Bucket List', 'contact_name' => 'Steve', 'status' => 'lead', 'country' => 'Canada', 'currency' => 'CAD' ) );
	$ar   = $ins( 'clients', array( 'code' => 'AR', 'name' => 'Active Rise', 'status' => 'lead', 'country' => 'Canada', 'currency' => 'CAD' ) );

	$d_phg  = $ins( 'deals', array( 'client_id' => $phg, 'title' => 'PHG monthly retainer', 'stage' => 'proposal', 'value' => 38700, 'probability' => 90, 'next_step' => 'Agree plan and price ($3,300 intro, then $3,600)', 'next_step_date' => '2026-10-31', 'notes' => 'Model: Growth plan, 3 months at $3,300 then 8 at $3,600.' ) );
	$d_sc   = $ins( 'deals', array( 'client_id' => $sc, 'title' => 'Sao Chi sprints 1 to 3', 'stage' => 'active', 'value' => 4500, 'probability' => 80, 'next_step' => 'Deliver Sprint 2', 'next_step_date' => '2026-11-15', 'notes' => 'Placeholder values from the financial model.' ) );
	$d_vibl = $ins( 'deals', array( 'client_id' => $vibl, 'title' => 'VIBL project', 'stage' => 'proposal', 'value' => 6125, 'probability' => 50, 'next_step' => "Follow up on Steve's questionnaire", 'next_step_date' => '2026-10-28', 'notes' => 'Options from $2,450 (49 h) to $6,125 (122.5 h).' ) );
	$ins( 'deals', array( 'client_id' => $ar, 'title' => 'Active Rise', 'stage' => 'lead', 'value' => 0, 'probability' => 10, 'next_step' => 'First conversation', 'next_step_date' => '2026-11-05' ) );

	$projects = array(
		array( 'PHG-1', $phg, $d_phg, 'PHG retainer, intro price', 'retainer', 'planned', 3300, 60, '2026-11-01', '2027-01-31', 'Placeholder until the plan is agreed.' ),
		array( 'PHG-2', $phg, $d_phg, 'PHG retainer, standard price', 'retainer', 'planned', 3600, 75, '2027-02-01', '2027-09-30', 'Placeholder until the plan is agreed.' ),
		array( 'PHG-3', $phg, $d_phg, 'BDSV (gift)', 'gift', 'active', 0, 40, '2026-10-01', null, 'Unpaid; log the real hours.' ),
		array( 'PHG-4', $phg, $d_phg, 'Scope 2 R&D', 'rnd', 'planned', 0, 88, '2026-11-01', '2027-09-30', '8 h a month.' ),
		array( 'SC-1', $sc, $d_sc, 'Sao Chi Sprint 1', 'sprint', 'done', 2000, 0, '2026-10-01', '2026-10-31', 'Placeholder fee and hours.' ),
		array( 'SC-2', $sc, $d_sc, 'Sao Chi Sprint 2', 'sprint', 'active', 1500, 0, '2026-11-01', '2026-11-30', 'Placeholder fee and hours.' ),
		array( 'SC-3', $sc, $d_sc, 'Sao Chi Sprint 3', 'sprint', 'planned', 1000, 0, '2027-01-01', '2027-01-31', 'Placeholder; 50% likely.' ),
		array( 'VIBL-1', $vibl, $d_vibl, 'VIBL project', 'sprint', 'planned', 6125, 122.5, '2026-11-01', '2026-12-31', 'Option 4 (Complete); 50% likely.' ),
	);
	$pid = array();
	foreach ( $projects as $p ) {
		$pid[ $p[0] ] = $ins( 'projects', array( 'code' => $p[0], 'client_id' => $p[1], 'deal_id' => $p[2], 'name' => $p[3], 'type' => $p[4], 'status' => $p[5], 'fee' => $p[6], 'hours_agreed' => $p[7], 'start_date' => $p[8], 'end_date' => $p[9], 'notes' => $p[10] ) );
	}

	$cards = array(
		array( 'Agree PHG plan and price', 'PHG-1', 'blocked', '2026-10-31', 'Leo to decide plan and send to PHG', 'Sales' ),
		array( 'VIBL questionnaire from Steve', 'VIBL-1', 'blocked', '2026-10-28', 'Waiting on Steve; Leo to follow up', 'Sales' ),
		array( 'Deliver Sao Chi Sprint 2', 'SC-2', 'doing', '2026-11-30', '', '' ),
		array( 'Open a separate account for WIZAD money', '', 'todo', '2026-10-31', '', 'OS' ),
		array( 'Create os.wizad.ca and install WIZAD OS', '', 'doing', '2026-10-31', '', 'OS' ),
		array( 'Send first PHG invoice', 'PHG-1', 'todo', '2026-11-01', '', '' ),
	);
	foreach ( $cards as $i => $c ) {
		$ins( 'cards', array(
			'title'         => $c[0],
			'project_id'    => $c[1] ? $pid[ $c[1] ] : 0,
			'status'        => $c[2],
			'owner'         => 'Leo',
			'due_date'      => $c[3],
			'blocker_note'  => $c[4],
			'blocked_since' => 'blocked' === $c[2] ? wos_today() : null,
			'tag'           => $c[5],
			'position'      => $i,
		) );
	}
}
