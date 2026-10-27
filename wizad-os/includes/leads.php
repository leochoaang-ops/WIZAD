<?php
/**
 * Sprint 0 with a client: assessment, preliminary proposal, intake,
 * discovery summary, price, signed.
 *
 * A lead is one row; the parts of its file (scores, proposal, intake,
 * summary, checklist, log) are JSON columns. The client sees only one
 * page, at /client/<token>: the online intake and the summary to confirm.
 */
defined( 'ABSPATH' ) || exit;

function wos_lead_stages() {
	return array(
		'prospect' => array( 'Prospect', 'Known name, no contact yet' ),
		'lead'     => array( 'Lead', 'Took the assessment, or replied' ),
		's0'       => array( 'Sprint 0', 'Discovery, unpaid' ),
		'won'      => array( 'Won', 'MSA and SOW signed' ),
		'run'      => array( 'Sprint running', 'Paid work under way' ),
		'lost'     => array( 'Not now', 'Closed or referred on' ),
	);
}

/** The Sprint 0 checklist. auto = ticked by WOS when the step happens; the rest are ticked by hand. */
function wos_lead_checks() {
	return array(
		'assessed' => array( 'Assessment answered: level set', 'auto' ),
		'prelim'   => array( 'Preliminary proposal sent (no price)', 'auto' ),
		'intake'   => array( 'Intake: interview or online form', 'auto' ),
		'nda'      => array( 'NDA, if they share confidential data', 'hand' ),
		'summary'  => array( 'Discovery summary confirmed by the client', 'auto' ),
		'price'    => array( 'Proposal and estimate: Leo approves the price', 'auto' ),
		'signed'   => array( 'MSA and SOW signed: the exit gate', 'hand' ),
	);
}

function wos_lead_score_areas() {
	return array(
		'fit'       => 'Solution fit',
		'readiness' => 'Readiness',
		'pay'       => 'Ability to pay',
		'expertise' => 'Our expertise',
	);
}

/** Intake fields: key => array( label, group ). */
function wos_lead_intake_fields() {
	return array(
		'business'  => array( 'Business', 'profile' ),
		'goal'      => array( 'Main goal', 'profile' ),
		'tools'     => array( 'Tools today', 'profile' ),
		'admin_who' => array( 'Who does admin', 'profile' ),
		'maturity'  => array( 'Digital maturity (1 to 5)', 'profile' ),
		'b_enq'     => array( 'Website enquiries / month', 'baseline' ),
		'b_reply'   => array( 'Reply time to an enquiry (days)', 'baseline' ),
		'b_admin'   => array( 'Hours on admin / week', 'baseline' ),
		'b_reach'   => array( 'Social reach / month', 'baseline' ),
		'b_won'     => array( 'Jobs won / quarter', 'baseline' ),
	);
}

function wos_lead_summary_parts() {
	return array(
		'heard' => 'What we heard',
		'goal'  => 'Your goal',
		'today' => 'Where you are today',
		'first' => 'What we suggest first',
		'out'   => 'Not in this step',
	);
}

/* ---------- Data ---------- */

function wos_lead_json_cols() {
	return array( 'scores', 'answers', 'checks', 'prelim', 'intake', 'summary', 'log', 'contacts' );
}

function wos_lead_prepare( $row ) {
	foreach ( wos_lead_json_cols() as $c ) {
		$v       = json_decode( (string) $row->$c, true );
		$row->$c = is_array( $v ) ? $v : array();
	}
	$row->notes     = (string) $row->notes;
	$row->next_date = (string) $row->next_date;
	return $row;
}

function wos_lead_get( $id ) {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . wos_table( 'leads' ) . ' WHERE id = %d', $id ) );
	return $row ? wos_lead_prepare( $row ) : null;
}

function wos_lead_by_token( $token ) {
	global $wpdb;
	if ( ! preg_match( '/^[A-Za-z0-9]{24,40}$/', (string) $token ) ) {
		return null;
	}
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . wos_table( 'leads' ) . ' WHERE token = %s', $token ) );
	return $row ? wos_lead_prepare( $row ) : null;
}

function wos_leads_all() {
	global $wpdb;
	$rows = $wpdb->get_results( 'SELECT * FROM ' . wos_table( 'leads' ) . ' ORDER BY updated_at DESC, id DESC' );
	return array_map( 'wos_lead_prepare', $rows );
}

/** Save columns; arrays are stored as JSON. */
function wos_lead_save( $id, $fields ) {
	global $wpdb;
	foreach ( $fields as $k => $v ) {
		if ( is_array( $v ) ) {
			$fields[ $k ] = wp_json_encode( $v );
		}
	}
	$fields['updated_at'] = wos_now();
	if ( $id ) {
		$wpdb->update( wos_table( 'leads' ), $fields, array( 'id' => (int) $id ) );
		return (int) $id;
	}
	$fields['created_at'] = wos_now();
	$wpdb->insert( wos_table( 'leads' ), $fields );
	return (int) $wpdb->insert_id;
}

function wos_lead_log( $lead, $text, $by = null ) {
	$log   = $lead->log;
	$log[] = array(
		'at'   => wos_now(),
		'by'   => null === $by ? wp_get_current_user()->display_name : $by,
		'text' => $text,
	);
	$lead->log = $log;
	wos_lead_save( $lead->id, array( 'log' => $log ) );
}

function wos_lead_tick( $lead, $key, $on = true ) {
	$checks         = $lead->checks;
	$checks[ $key ] = $on ? wos_today() : '';
	$lead->checks   = $checks;
	wos_lead_save( $lead->id, array( 'checks' => $checks ) );
}

function wos_lead_token( $lead ) {
	if ( $lead->token ) {
		return $lead->token;
	}
	$lead->token = wp_generate_password( 32, false, false );
	wos_lead_save( $lead->id, array( 'token' => $lead->token ) );
	return $lead->token;
}

function wos_lead_client_url( $lead ) {
	return home_url( '/client/' . wos_lead_token( $lead ) . '/' );
}

function wos_lead_total( $scores ) {
	$t = 0;
	foreach ( array_keys( wos_lead_score_areas() ) as $k ) {
		$t += isset( $scores[ $k ] ) ? (int) $scores[ $k ] : 0;
	}
	return $t;
}

/** A to D from the total, using the thresholds in Settings. */
function wos_lead_level( $scores ) {
	$t = wos_lead_total( $scores );
	if ( $t >= wos_num( 'level_a' ) ) {
		return 'A';
	}
	if ( $t >= wos_num( 'level_b' ) ) {
		return 'B';
	}
	return $t >= wos_num( 'level_c' ) ? 'C' : 'D';
}

function wos_lead_route( $level ) {
	$r = array(
		'A' => array( 'Strong fit. Goes to Sprint 0 (unpaid discovery).', 'Book Sprint 0: a discovery call' ),
		'B' => array( 'Good fit, one gap. One follow-up before Sprint 0.', 'One follow-up call to check timing' ),
		'C' => array( 'Fit is partial. Offer a small start.', 'Offer the small start' ),
		'D' => array( 'Not a fit now. Refer on with a short note.', 'Send a short note and refer on' ),
	);
	return isset( $r[ $level ] ) ? $r[ $level ] : array( 'No assessment yet.', 'Ask them to take the assessment' );
}

/** Steps done, and whether everything before Won is done. */
function wos_lead_progress( $lead ) {
	$done = 0;
	foreach ( array_keys( wos_lead_checks() ) as $k ) {
		if ( ! empty( $lead->checks[ $k ] ) ) {
			$done++;
		}
	}
	return array( $done, count( wos_lead_checks() ) );
}

function wos_lead_hours_state( $lead ) {
	$cap = wos_num( 's0_cap' );
	$h   = (float) $lead->s0_hours;
	if ( $cap > 0 && $h > $cap ) {
		return 'red';
	}
	return $cap > 0 && $h >= $cap * 0.75 ? 'amber' : 'green';
}

/** Draft the preliminary proposal from the assessment, for Tau to finish. */
function wos_lead_prelim_template( $lead ) {
	$route = wos_lead_route( $lead->level );
	return array(
		'see'    => '',
		'sprint' => '',
		'low'    => '',
		'high'   => '',
		'next'   => $route[1],
		'status' => 'draft',
	);
}

/** Start the summary from the intake so the team edits instead of retyping. */
function wos_lead_summary_template( $lead ) {
	$in  = isset( $lead->intake['values'] ) ? $lead->intake['values'] : array();
	$get = function ( $k ) use ( $in ) {
		return isset( $in[ $k ]['v'] ) ? trim( $in[ $k ]['v'] ) : '';
	};
	$today = array();
	foreach ( wos_lead_intake_fields() as $k => $f ) {
		if ( 'baseline' === $f[1] && '' !== $get( $k ) ) {
			$today[] = $f[0] . ': ' . $get( $k );
		}
	}
	return array(
		'heard'  => trim( $get( 'business' ) . ( $get( 'tools' ) ? '. Tools today: ' . $get( 'tools' ) : '' ) . ( $get( 'admin_who' ) ? '. Admin is done by: ' . $get( 'admin_who' ) : '' ) ),
		'goal'   => $get( 'goal' ),
		'today'  => implode( '; ', $today ),
		'first'  => isset( $lead->prelim['sprint'] ) ? $lead->prelim['sprint'] : '',
		'out'    => '',
		'status' => 'draft',
	);
}

/* ---------- Actions from the team (admin-post) ---------- */

function wos_lead_back( $id, $message, $type = 'success', $tab = '' ) {
	wos_flash( $message, $type );
	wp_safe_redirect( $id ? wos_app_url( 'leads/' . (int) $id, $tab ? array( 'tab' => $tab ) : array() ) : wos_app_url( 'leads' ) );
	exit;
}

/** Check the capability and the per-lead nonce, then load the lead. */
function wos_lead_request() {
	wos_require_cap();
	$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	check_admin_referer( 'wos_lead_' . $id );
	$lead = wos_lead_get( $id );
	if ( ! $lead ) {
		wos_lead_back( 0, 'That lead does not exist.', 'error' );
	}
	return $lead;
}

/** Roles offered for the people at a B2B client. */
function wos_lead_contact_roles() {
	return array( 'Assistant', 'Technical', 'Finance / billing', 'Co-founder / partner', 'Other' );
}

/** Other contacts from the form: rows without a name are dropped. */
function wos_lead_contacts_from_post() {
	$rows = isset( $_POST['contacts'] ) && is_array( $_POST['contacts'] ) ? wp_unslash( $_POST['contacts'] ) : array(); // phpcs:ignore -- nonce checked in wos_lead_request(); cleaned below.
	$out  = array();
	foreach ( $rows as $r ) {
		$name = isset( $r['name'] ) ? sanitize_text_field( $r['name'] ) : '';
		if ( '' === $name ) {
			continue;
		}
		$role  = isset( $r['role'] ) ? sanitize_text_field( $r['role'] ) : '';
		$out[] = array(
			'name'  => $name,
			'role'  => in_array( $role, wos_lead_contact_roles(), true ) ? $role : 'Other',
			'email' => isset( $r['email'] ) ? sanitize_email( $r['email'] ) : '',
			'phone' => isset( $r['phone'] ) ? sanitize_text_field( $r['phone'] ) : '',
		);
	}
	return array_slice( $out, 0, 20 );
}

function wos_post_text( $key, $multi = false ) {
	if ( ! isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- checked by the caller.
		return '';
	}
	$v = wp_unslash( $_POST[ $key ] ); // phpcs:ignore -- cleaned on the next line.
	return $multi ? sanitize_textarea_field( $v ) : sanitize_text_field( $v );
}

/* A new lead from an assessment: typed in by the team, or sent by the website later. */
function wos_lead_create_from_assessment( $data, $by ) {
	$scores = array();
	foreach ( array_keys( wos_lead_score_areas() ) as $k ) {
		$scores[ $k ] = isset( $data['scores'][ $k ] ) ? max( 0, min( 10, (int) $data['scores'][ $k ] ) ) : 0;
	}
	$answers = array();
	foreach ( preg_split( '/\r\n|\r|\n/', isset( $data['answers'] ) ? (string) $data['answers'] : '' ) as $line ) {
		$line = trim( $line );
		if ( '' !== $line ) {
			$answers[] = sanitize_text_field( $line );
		}
	}
	$level = wos_lead_level( $scores );
	$route = wos_lead_route( $level );
	$lead  = (object) array( 'level' => $level );
	$id    = wos_lead_save( 0, array(
		'name'         => sanitize_text_field( $data['name'] ),
		'contact_name' => isset( $data['contact_name'] ) ? sanitize_text_field( $data['contact_name'] ) : '',
		'email'        => isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '',
		'phone'        => isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : '',
		'source'       => isset( $data['source'] ) ? sanitize_text_field( $data['source'] ) : '',
		'introducer'   => isset( $data['introducer'] ) ? sanitize_text_field( $data['introducer'] ) : '',
		'owner'        => isset( $data['owner'] ) ? sanitize_text_field( $data['owner'] ) : '',
		'stage'        => 'D' === $level ? 'lost' : 'lead',
		'level'        => $level,
		'scores'       => $scores,
		'answers'      => $answers,
		'checks'       => array( 'assessed' => wos_today() ),
		'prelim'       => wos_lead_prelim_template( $lead ),
		'next_step'    => $route[1],
		'next_date'    => wp_date( 'Y-m-d', strtotime( wos_today() . ' +2 days' ) ),
		'log'          => array( array( 'at' => wos_now(), 'by' => $by, 'text' => 'Assessment received: ' . wos_lead_total( $scores ) . '/40, level ' . $level . '.' ) ),
	) );
	return $id;
}

add_action( 'admin_post_wos_lead_new', function () {
	wos_require_cap();
	check_admin_referer( 'wos_lead_new' );
	$p = wp_unslash( $_POST ); // phpcs:ignore -- each field is cleaned in wos_lead_create_from_assessment().
	if ( empty( $p['name'] ) ) {
		wos_flash( 'A lead needs a business name.', 'warning' );
		wp_safe_redirect( wos_app_url( 'leads', array( 'tab' => 'new' ) ) );
		exit;
	}
	$id = wos_lead_create_from_assessment( $p, wp_get_current_user()->display_name );
	wos_lead_back( $id, 'Lead created from the assessment. Next: finish the preliminary proposal.', 'success', 'proposal' );
} );

add_action( 'admin_post_wos_lead_update', function () {
	$lead   = wos_lead_request();
	$stages = wos_lead_stages();
	$stage  = wos_post_text( 'stage' );
	$fields = array(
		'name'         => wos_post_text( 'name' ),
		'contact_name' => wos_post_text( 'contact_name' ),
		'email'        => sanitize_email( wos_post_text( 'email' ) ),
		'phone'        => wos_post_text( 'phone' ),
		'source'       => wos_post_text( 'source' ),
		'introducer'   => wos_post_text( 'introducer' ),
		'owner'        => wos_post_text( 'owner' ),
		'value'        => (float) wos_post_text( 'value' ),
		'win'          => max( 0, min( 100, (int) wos_post_text( 'win' ) ) ),
		'next_step'    => wos_post_text( 'next_step' ),
		'next_date'    => preg_match( '/^\d{4}-\d{2}-\d{2}$/', wos_post_text( 'next_date' ) ) ? wos_post_text( 'next_date' ) : null,
		'notes'        => wos_post_text( 'notes', true ),
		'contacts'     => wos_lead_contacts_from_post(),
	);
	if ( '' === $fields['name'] ) {
		unset( $fields['name'] );
	}
	// Won only through the exit gate (wos_lead_won).
	if ( isset( $stages[ $stage ] ) && 'won' !== $stage && $stage !== $lead->stage ) {
		$fields['stage'] = $stage;
		wos_lead_log( $lead, 'Moved to ' . $stages[ $stage ][0] . '.' );
	}
	wos_lead_save( $lead->id, $fields );
	wos_lead_back( $lead->id, 'Saved.' );
} );

add_action( 'admin_post_wos_lead_check', function () {
	$lead  = wos_lead_request();
	$key   = wos_post_text( 'check' );
	$all   = wos_lead_checks();
	if ( ! isset( $all[ $key ] ) || 'hand' !== $all[ $key ][1] ) {
		wos_lead_back( $lead->id, 'That step is ticked by WOS when it happens.', 'warning' );
	}
	$on = empty( $lead->checks[ $key ] );
	wos_lead_tick( $lead, $key, $on );
	wos_lead_log( $lead, ( $on ? 'Ticked: ' : 'Unticked: ' ) . $all[ $key ][0] . '.' );
	wos_lead_back( $lead->id, $on ? 'Ticked.' : 'Unticked.' );
} );

add_action( 'admin_post_wos_lead_prelim', function () {
	$lead   = wos_lead_request();
	$prelim = array_merge( wos_lead_prelim_template( $lead ), $lead->prelim );
	foreach ( array( 'see', 'sprint', 'next' ) as $k ) {
		$prelim[ $k ] = wos_post_text( $k, true );
	}
	foreach ( array( 'low', 'high' ) as $k ) {
		$v            = wos_post_text( $k );
		$prelim[ $k ] = '' === $v ? '' : max( 0, (float) $v );
	}
	$send = 'send' === wos_post_text( 'do' );
	if ( $send ) {
		if ( '' === trim( $prelim['see'] ) || '' === trim( $prelim['sprint'] ) || '' === (string) $prelim['low'] || '' === (string) $prelim['high'] ) {
			wos_lead_save( $lead->id, array( 'prelim' => $prelim ) );
			wos_lead_back( $lead->id, 'Fill in what we see, the first sprint and the effort range before marking it sent.', 'warning', 'proposal' );
		}
		$prelim['status']  = 'sent';
		$prelim['sent_at'] = wos_now();
		$prelim['sent_by'] = wp_get_current_user()->display_name;
	}
	wos_lead_save( $lead->id, array( 'prelim' => $prelim ) );
	if ( $send ) {
		wos_lead_tick( $lead, 'prelim' );
		wos_lead_log( $lead, 'Preliminary proposal marked sent.' );
		wos_lead_back( $lead->id, 'Marked sent. Next: start Sprint 0 when they say yes.', 'success', 'proposal' );
	}
	wos_lead_back( $lead->id, 'Draft saved.', 'success', 'proposal' );
} );

/* Ask Claude for a first draft of the preliminary proposal. Needs the API key in Settings. */
add_action( 'admin_post_wos_lead_prelim_draft', function () {
	$lead = wos_lead_request();
	if ( ! wos_claude_key() ) {
		wos_lead_back( $lead->id, 'Add the Claude API key in Settings to draft with Claude.', 'warning', 'proposal' );
	}
	$areas = array();
	foreach ( wos_lead_score_areas() as $k => $label ) {
		$areas[] = $label . ': ' . ( isset( $lead->scores[ $k ] ) ? (int) $lead->scores[ $k ] : 0 ) . '/10';
	}
	$prompt = "Draft a preliminary proposal for this lead. It goes to the client after the team checks it.\n"
		. "Business: {$lead->name}\nLevel: {$lead->level}\nScores: " . implode( ', ', $areas ) . "\nAnswers:\n- " . implode( "\n- ", $lead->answers ) . "\n\n"
		. "Rules: no price and no hourly rate. Plain words a small business owner understands. Use only what the answers say; if something is unknown, leave it out.\n"
		. 'Reply with JSON only, no other text: {"see": "what we see, 2 or 3 sentences", "sprint": "the likely first sprint, one sentence", "low": hours as a number, "high": hours as a number}';
	$r = wos_claude_call( 'You draft documents for WIZAD Consulting, a small digital consultancy in Canada.', array( array( 'role' => 'user', 'content' => $prompt ) ), 'prelim:' . $lead->id );
	if ( is_wp_error( $r ) ) {
		wos_lead_back( $lead->id, 'Claude could not draft it: ' . $r->get_error_message(), 'error', 'proposal' );
	}
	$text = trim( preg_replace( '/^```(?:json)?|```$/m', '', $r['text'] ) );
	$json = json_decode( $text, true );
	if ( ! is_array( $json ) ) {
		wos_lead_back( $lead->id, 'Claude replied in an unexpected form. Nothing was changed.', 'error', 'proposal' );
	}
	$prelim = array_merge( wos_lead_prelim_template( $lead ), $lead->prelim );
	foreach ( array( 'see', 'sprint' ) as $k ) {
		if ( ! empty( $json[ $k ] ) ) {
			$prelim[ $k ] = sanitize_textarea_field( $json[ $k ] );
		}
	}
	foreach ( array( 'low', 'high' ) as $k ) {
		if ( isset( $json[ $k ] ) && is_numeric( $json[ $k ] ) ) {
			$prelim[ $k ] = (float) $json[ $k ];
		}
	}
	$prelim['drafted_by'] = 'Claude';
	wos_lead_save( $lead->id, array( 'prelim' => $prelim ) );
	wos_lead_log( $lead, 'Claude drafted the preliminary proposal.' );
	wos_lead_back( $lead->id, 'Claude drafted it. Check every line before sending.', 'success', 'proposal' );
} );

add_action( 'admin_post_wos_lead_start_s0', function () {
	$lead = wos_lead_request();
	wos_lead_save( $lead->id, array(
		'stage'     => 's0',
		'next_step' => 'Intake: interview or online form',
	) );
	wos_lead_log( $lead, 'Sprint 0 started (unpaid discovery).' );
	wos_lead_back( $lead->id, 'Sprint 0 started. Log the hours you spend; the cap is ' . wos_num( 's0_cap' ) . ' h.', 'success', 'intake' );
} );

add_action( 'admin_post_wos_lead_hours', function () {
	$lead  = wos_lead_request();
	$hours = round( (float) wos_post_text( 'hours' ), 2 );
	$note  = wos_post_text( 'note' );
	if ( $hours <= 0 || $hours > 24 ) {
		wos_lead_back( $lead->id, 'Enter the hours as a number between 0.25 and 24.', 'warning' );
	}
	$before = (float) $lead->s0_hours;
	$after  = $before + $hours;
	wos_lead_save( $lead->id, array( 's0_hours' => $after ) );
	wos_lead_log( $lead, 'Logged ' . $hours . ' h of Sprint 0' . ( $note ? ': ' . $note : '' ) . '. Total ' . $after . ' h.' );
	$cap = wos_num( 's0_cap' );
	if ( $cap > 0 && $before <= $cap && $after > $cap ) {
		wos_decision_create( array(
			'area'        => 'Clients · ' . $lead->owner . ' · ' . $lead->name,
			'question'    => $lead->name . ' passed the Sprint 0 cap. Keep going unpaid, or offer a paid sprint?',
			'context'     => 'Sprint 0 is unpaid discovery with a cap of ' . $cap . ' h. ' . $lead->name . ' is now at ' . $after . ' h.',
			'why_now'     => 'More unpaid hours cost WIZAD money; stopping too early cuts discovery short.',
			'numbers'     => $after . ' h used of ' . $cap . ' h. Cost so far about ' . wos_money( $after * wos_role_cost( 'lead_consultant' ) ) . ' at the Lead Consultant cost rate.',
			'read_first'  => 'Lead file | ' . wos_app_url( 'leads/' . $lead->id ),
			'options'     => array(
				array( 'label' => 'Finish discovery unpaid, up to 2 more hours', 'result' => 'Keeps the relationship warm', 'risk' => 'More unpaid time' ),
				array( 'label' => 'Offer the rest as a small paid sprint', 'result' => 'Hours are paid from here', 'risk' => 'They may pause' ),
				array( 'label' => 'Stop and send the summary as it is', 'result' => 'No more unpaid time', 'risk' => 'Summary may be thin' ),
			),
			'recommended' => 0,
			'lead_id'     => $lead->id,
			'kind'        => 's0_cap',
			'added_by'    => 'WIZAD OS',
		) );
		wos_lead_back( $lead->id, 'Logged. This lead passed the Sprint 0 cap, so a decision went to Leo.', 'warning' );
	}
	wos_lead_back( $lead->id, 'Logged ' . $hours . ' h.' );
} );

add_action( 'admin_post_wos_lead_intake', function () {
	$lead   = wos_lead_request();
	$intake = $lead->intake;
	$vals   = isset( $intake['values'] ) ? $intake['values'] : array();
	$in     = isset( $_POST['f'] ) && is_array( $_POST['f'] ) ? wp_unslash( $_POST['f'] ) : array(); // phpcs:ignore -- cleaned below.
	foreach ( array_keys( wos_lead_intake_fields() ) as $k ) {
		if ( ! isset( $in[ $k ] ) ) {
			continue;
		}
		$v   = sanitize_text_field( isset( $in[ $k ]['v'] ) ? $in[ $k ]['v'] : '' );
		$src = isset( $in[ $k ]['src'] ) && in_array( $in[ $k ]['src'], array( 'interview', 'client', 'assessment' ), true ) ? $in[ $k ]['src'] : 'interview';
		$vals[ $k ] = array( 'v' => $v, 'src' => $src );
	}
	$intake['values'] = $vals;
	$do               = wos_post_text( 'do' );
	if ( 'online' === $do ) {
		$intake['requested'] = wos_now();
		wos_lead_save( $lead->id, array( 'intake' => $intake ) );
		wos_lead_token( $lead );
		wos_lead_log( $lead, 'Online intake link made for the client.' );
		wos_lead_back( $lead->id, 'Link ready. Copy it and send it to the client.', 'success', 'intake' );
	}
	if ( 'complete' === $do ) {
		$intake['done_at'] = wos_now();
		wos_lead_save( $lead->id, array( 'intake' => $intake ) );
		wos_lead_tick( $lead, 'intake' );
		wos_lead_log( $lead, 'Intake marked complete.' );
		wos_lead_back( $lead->id, 'Intake complete. Next: the discovery summary.', 'success', 'summary' );
	}
	wos_lead_save( $lead->id, array( 'intake' => $intake ) );
	wos_lead_back( $lead->id, 'Intake saved.', 'success', 'intake' );
} );

add_action( 'admin_post_wos_lead_summary', function () {
	$lead    = wos_lead_request();
	$summary = $lead->summary ? $lead->summary : wos_lead_summary_template( $lead );
	foreach ( array_keys( wos_lead_summary_parts() ) as $k ) {
		$summary[ $k ] = wos_post_text( $k, true );
	}
	$do = wos_post_text( 'do' );
	if ( 'send' === $do ) {
		$summary['status']  = 'sent';
		$summary['sent_at'] = wos_now();
		wos_lead_save( $lead->id, array( 'summary' => $summary ) );
		wos_lead_token( $lead );
		wos_lead_log( $lead, 'Discovery summary sent to the client to confirm.' );
		wos_lead_back( $lead->id, 'Ready to send. Copy the client link below and send it. Pricing opens when they confirm.', 'success', 'summary' );
	}
	if ( 'confirmed' === $do ) {
		$summary['status']       = 'confirmed';
		$summary['confirmed_at'] = wos_now();
		$summary['confirmed_by'] = 'Marked by ' . wp_get_current_user()->display_name;
		wos_lead_save( $lead->id, array( 'summary' => $summary ) );
		wos_lead_tick( $lead, 'summary' );
		wos_lead_log( $lead, 'Summary marked confirmed by the client (confirmed outside WOS).' );
		wos_lead_back( $lead->id, 'Confirmed. Pricing is open.', 'success', 'price' );
	}
	if ( 'sent' !== ( isset( $summary['status'] ) ? $summary['status'] : '' ) && 'confirmed' !== ( isset( $summary['status'] ) ? $summary['status'] : '' ) ) {
		$summary['status'] = 'draft';
	}
	wos_lead_save( $lead->id, array( 'summary' => $summary ) );
	wos_lead_back( $lead->id, 'Summary saved.', 'success', 'summary' );
} );

/* The price goes to Leo as a decision with its context. Only after the client confirmed the summary. */
add_action( 'admin_post_wos_lead_price', function () {
	$lead = wos_lead_request();
	if ( empty( $lead->checks['summary'] ) ) {
		wos_lead_back( $lead->id, 'Pricing opens after the client confirms the discovery summary.', 'warning', 'price' );
	}
	$hours   = (float) wos_post_text( 'hours' );
	$options = wos_decision_parse_options( wos_post_text( 'options', true ) );
	if ( ! $options ) {
		wos_lead_back( $lead->id, 'Give Leo at least one price option.', 'warning', 'price' );
	}
	$cost = $hours * wos_role_cost( 'lead_consultant' );
	$id   = wos_decision_create( array(
		'area'        => 'Clients · ' . $lead->owner . ' · ' . $lead->name,
		'question'    => 'Which price do we send ' . $lead->name . '?',
		'context'     => isset( $lead->summary['first'] ) ? 'First sprint: ' . $lead->summary['first'] : '',
		'why_now'     => wos_post_text( 'why_now', true ),
		'numbers'     => ( $hours ? $hours . ' h estimated · allocated cost ' . wos_money( $cost ) . ' · target margin ' . wos_num( 'margin_target' ) . '% · minimum ' . wos_num( 'margin_min' ) . '%' : '' ),
		'read_first'  => 'Lead file | ' . wos_app_url( 'leads/' . $lead->id ) . "\nDiscovery summary | " . wos_app_url( 'leads/' . $lead->id, array( 'tab' => 'summary' ) ),
		'options'     => $options,
		'recommended' => max( 0, (int) wos_post_text( 'recommended' ) - 1 ),
		'due_date'    => wos_post_text( 'due_date' ),
		'lead_id'     => $lead->id,
		'kind'        => 'price',
	) );
	if ( is_wp_error( $id ) ) {
		wos_lead_back( $lead->id, $id->get_error_message(), 'error', 'price' );
	}
	wos_lead_save( $lead->id, array( 'next_step' => 'Leo approves the price' ) );
	wos_lead_log( $lead, 'Price options sent to Leo.' );
	wos_lead_back( $lead->id, 'Sent to Leo\'s decision queue.', 'success', 'price' );
} );

/* When Leo answers a decision about a lead, the lead file moves on. */
add_action( 'wos_decision_decided', function ( $d ) {
	if ( ! $d || ! $d->lead_id ) {
		return;
	}
	$lead = wos_lead_get( $d->lead_id );
	if ( ! $lead ) {
		return;
	}
	$answer = wos_decision_answer_text( $d );
	if ( 'price' === $d->kind ) {
		wos_lead_tick( $lead, 'price' );
		wos_lead_save( $lead->id, array( 'next_step' => 'Send the proposal, then MSA and SOW' ) );
		wos_lead_log( $lead, 'Leo approved the price: ' . $answer, $d->decided_by );
	} else {
		wos_lead_log( $lead, 'Leo decided "' . $d->question . '": ' . $answer, $d->decided_by );
	}
} );

/* The exit gate: every step done, then the lead becomes a client. */
add_action( 'admin_post_wos_lead_won', function () {
	global $wpdb;
	$lead = wos_lead_request();
	list( $done, $all ) = wos_lead_progress( $lead );
	if ( $done < $all ) {
		wos_lead_back( $lead->id, ( $all - $done ) . ' steps left before this lead can move to Won.', 'warning', 'price' );
	}
	if ( ! $lead->client_id ) {
		$wpdb->insert( wos_table( 'clients' ), array(
			'name'         => $lead->name,
			'contact_name' => $lead->contact_name,
			'email'        => $lead->email,
			'phone'        => $lead->phone,
			'status'       => 'active',
			'source'       => substr( $lead->source, 0, 60 ),
			'notes'        => 'From WOS lead #' . $lead->id . '.' . implode( '', array_map( function ( $c ) {
				return "\n" . $c['name'] . ' (' . $c['role'] . ')' . ( $c['email'] ? ' ' . $c['email'] : '' ) . ( $c['phone'] ? ' ' . $c['phone'] : '' );
			}, $lead->contacts ) ),
			'created_at'   => wos_now(),
			'updated_at'   => wos_now(),
		) );
		$lead->client_id = (int) $wpdb->insert_id;
	}
	wos_lead_save( $lead->id, array(
		'stage'     => 'won',
		'client_id' => $lead->client_id,
		'win'       => 100,
		'next_step' => 'Plan Sprint 1 from the SOW',
	) );
	wos_lead_log( $lead, 'Won. Client record created.' );
	wos_lead_back( $lead->id, 'Won. ' . $lead->name . ' is now a client.' );
} );

/* ---------- The client's page: /client/<token> ---------- */

function wos_client_path_token() {
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore -- matched against a strict pattern.
	$path = trim( (string) wp_parse_url( $uri, PHP_URL_PATH ), '/' );
	$base = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
	if ( $base && 0 === strpos( $path, $base . '/' ) ) {
		$path = substr( $path, strlen( $base ) + 1 );
	}
	return preg_match( '#^client/([A-Za-z0-9]{24,40})$#', $path, $m ) ? $m[1] : null;
}

// Before the private-site check (priority 0): this page is reachable without signing in, by its secret link only.
add_action( 'template_redirect', function () {
	$token = wos_client_path_token();
	if ( null === $token ) {
		return;
	}
	$lead = wos_lead_by_token( $token );
	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow' );
	if ( ! $lead ) {
		status_header( 404 );
		wos_client_page( null, 'This link is not valid any more. Please ask WIZAD for a new one.' );
		exit;
	}
	$msg = '';
	if ( 'POST' === ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) && isset( $_POST['wos_client_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wos_client_nonce'] ) ), 'wos_client_' . $token ) ) {
		$msg = wos_client_handle_post( $lead );
		$lead = wos_lead_get( $lead->id );
	}
	wos_client_page( $lead, $msg );
	exit;
}, -1 );

function wos_client_handle_post( $lead ) {
	$do = wos_post_text( 'do' );
	if ( 'intake' === $do && ! empty( $lead->intake['requested'] ) && empty( $lead->intake['client_at'] ) ) {
		$intake = $lead->intake;
		$vals   = isset( $intake['values'] ) ? $intake['values'] : array();
		$in     = isset( $_POST['f'] ) && is_array( $_POST['f'] ) ? wp_unslash( $_POST['f'] ) : array(); // phpcs:ignore -- cleaned below.
		foreach ( array_keys( wos_lead_intake_fields() ) as $k ) {
			if ( isset( $in[ $k ] ) && '' !== trim( (string) $in[ $k ] ) ) {
				$vals[ $k ] = array( 'v' => sanitize_text_field( $in[ $k ] ), 'src' => 'client' );
			}
		}
		$intake['values']    = $vals;
		$intake['client_at'] = wos_now();
		wos_lead_save( $lead->id, array( 'intake' => $intake, 'next_step' => 'Check the client\'s intake answers' ) );
		wos_lead_log( $lead, 'The client filled in the online intake.', 'Client' );
		return 'Thank you. Your answers reached WIZAD.';
	}
	$status = isset( $lead->summary['status'] ) ? $lead->summary['status'] : '';
	if ( 'confirm' === $do && 'sent' === $status ) {
		$summary                 = $lead->summary;
		$summary['status']       = 'confirmed';
		$summary['confirmed_at'] = wos_now();
		$summary['confirmed_by'] = 'Client, online';
		wos_lead_save( $lead->id, array( 'summary' => $summary, 'next_step' => 'Price: send options to Leo' ) );
		wos_lead_tick( $lead, 'summary' );
		wos_lead_log( $lead, 'The client confirmed the discovery summary.', 'Client' );
		return 'Thank you for confirming. WIZAD will send the proposal next.';
	}
	if ( 'fix' === $do && 'sent' === $status ) {
		$note                    = wos_post_text( 'note', true );
		$summary                 = $lead->summary;
		$summary['status']       = 'changes';
		$summary['client_note']  = $note;
		wos_lead_save( $lead->id, array( 'summary' => $summary, 'next_step' => 'The client asked for changes to the summary' ) );
		wos_lead_log( $lead, 'The client asked for changes to the summary: ' . $note, 'Client' );
		return 'Thank you. We will fix the summary and send it again.';
	}
	return '';
}

function wos_client_page( $lead, $msg ) {
	$token = $lead ? $lead->token : '';
	?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>WIZAD Consulting</title>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@700;800&amp;family=Public+Sans:wght@400;600&amp;display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo esc_url( WOS_URL . 'assets/app.css?v=' . WOS_VERSION ); ?>">
</head>
<body class="wos-app wos-client">
<main class="wos-client-main">
	<div class="wos-eyebrow">WIZAD CONSULTING</div>
	<?php if ( $msg ) : ?><div class="wos-flash" role="status"><?php echo esc_html( $msg ); ?></div><?php endif; ?>
	<?php if ( ! $lead ) : ?>
		<?php echo $msg ? '' : ''; ?>
	<?php else : ?>
		<h1><?php echo esc_html( $lead->name ); ?></h1>
		<?php
		$shown = false;
		if ( ! empty( $lead->intake['requested'] ) && empty( $lead->intake['client_at'] ) ) :
			$shown = true;
			$vals  = isset( $lead->intake['values'] ) ? $lead->intake['values'] : array();
			?>
			<section class="wos-card wos-pad">
				<h2>A few questions before we meet</h2>
				<p class="wos-muted">About 5 minutes. Leave out anything you do not know; we can fill it in together.</p>
				<form method="post" class="wos-form">
					<?php wp_nonce_field( 'wos_client_' . $token, 'wos_client_nonce' ); ?>
					<input type="hidden" name="do" value="intake">
					<?php foreach ( wos_lead_intake_fields() as $k => $f ) : ?>
						<label class="wos-field"><span><?php echo esc_html( $f[0] ); ?></span><input type="text" name="f[<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( isset( $vals[ $k ]['v'] ) ? $vals[ $k ]['v'] : '' ); ?>"></label>
					<?php endforeach; ?>
					<div class="wos-actions"><button class="wos-btn" type="submit">Send my answers</button></div>
				</form>
			</section>
		<?php endif; ?>
		<?php
		$status = isset( $lead->summary['status'] ) ? $lead->summary['status'] : '';
		if ( in_array( $status, array( 'sent', 'confirmed', 'changes' ), true ) ) :
			$shown = true;
			?>
			<article class="wos-card wos-pad wos-summary-doc">
				<div class="wos-eyebrow">DISCOVERY SUMMARY</div>
				<?php foreach ( wos_lead_summary_parts() as $k => $label ) : ?>
					<?php if ( ! empty( $lead->summary[ $k ] ) ) : ?>
						<p><strong><?php echo esc_html( $label ); ?>.</strong> <?php echo nl2br( esc_html( $lead->summary[ $k ] ) ); ?></p>
					<?php endif; ?>
				<?php endforeach; ?>
				<?php if ( 'sent' === $status ) : ?>
					<p class="wos-muted">Please check this page. If something is wrong, tell us and we fix it before we send a price.</p>
					<form method="post" class="wos-actions">
						<?php wp_nonce_field( 'wos_client_' . $token, 'wos_client_nonce' ); ?>
						<input type="hidden" name="do" value="confirm">
						<button class="wos-btn" type="submit">This is right: I confirm</button>
					</form>
					<details class="wos-own">
						<summary>Something is wrong</summary>
						<form method="post" class="wos-form">
							<?php wp_nonce_field( 'wos_client_' . $token, 'wos_client_nonce' ); ?>
							<input type="hidden" name="do" value="fix">
							<label class="wos-field"><span>What should we change?</span><textarea name="note" rows="4" required></textarea></label>
							<div class="wos-actions"><button class="wos-btn is-ghost" type="submit">Send</button></div>
						</form>
					</details>
				<?php elseif ( 'confirmed' === $status ) : ?>
					<p class="wos-ok-box">Confirmed on <?php echo esc_html( substr( (string) $lead->summary['confirmed_at'], 0, 10 ) ); ?>. Thank you.</p>
				<?php else : ?>
					<p class="wos-muted">We are fixing this page and will send it again.</p>
				<?php endif; ?>
			</article>
		<?php endif; ?>
		<?php if ( ! $shown && ! $msg ) : ?>
			<p>Nothing is waiting for you here right now. Thank you.</p>
		<?php endif; ?>
	<?php endif; ?>
	<p class="wos-note">WIZAD Consulting · this page is private to you; please do not share the link.</p>
</main>
</body>
</html>
	<?php
}
