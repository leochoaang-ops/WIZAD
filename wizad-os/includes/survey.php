<?php
/**
 * The Connected Tools Check (Sag's survey on wizad.ca) posts its answers here.
 *
 * POST /wp-json/wos/v1/survey, no sign-in: the form runs in the visitor's
 * browser, so it cannot hold a secret. Instead: only allowed origins, a rate
 * limit per IP, a honeypot field, strict field lists and a size cap. Each
 * step is saved (partial), keyed by the sid WOS returns on the first post.
 * On completion the answers become a lead in Find clients. Only the fields
 * below are kept (BC PIPA).
 */
defined( 'ABSPATH' ) || exit;

define( 'WOS_SURVEY_ROUTE', '/wos/v1/survey' );
define( 'WOS_SURVEY_MAX_BYTES', 20000 );
define( 'WOS_SURVEY_PER_HOUR', 60 );

/** Allowed answers per question. A list = one of; 'num' = a number or "ns"; 'text:N' = text up to N characters; 'tags' = a list of short texts. */
function wos_survey_fields() {
	return array(
		'seg'    => array( 'trades', 'notary', 'law', 'realty', 'dental', 'health', 'money', 'prof', 'food', 'retail', 'personal', 'tourism', 'home', 'auto', 'care', 'other' ),
		'seg_text' => 'text:120',
		'P3'     => array( '1-2', '3-5', '6-20', '20+' ),
		'C1'     => array( 'missed', 'quotes', 'schedule', 'retype', 'chase', 'recall', 'other' ),
		'C1_text' => 'text:160',
		'C2'     => array( '3', '7.5', '15', '30', '45' ),
		'C3'     => 'num',
		'T'      => 'num',
		'R4'     => 'tags',
		'R2'     => array( 'month', '3m', 'year', 'looking' ),
		'R3'     => array( 'yes', 'maybe', 'no' ),
		'P2'     => array( '1000', '3000', '6000', '99999', 'discuss' ),
		'note'   => 'text:600',
	);
}

/** What each answer means, for the lead file. */
function wos_survey_labels() {
	return array(
		'seg' => array( 'Field', array( 'trades' => 'Trades', 'notary' => 'Notary', 'law' => 'Law', 'realty' => 'Real estate', 'dental' => 'Dental', 'health' => 'Health and wellness clinic', 'money' => 'Accounting, bookkeeping or insurance', 'prof' => 'Other professional services', 'food' => 'Food', 'retail' => 'Retail', 'personal' => 'Personal services', 'tourism' => 'Accommodation and tours', 'home' => 'Home and property services', 'auto' => 'Auto', 'care' => 'Childcare and education', 'other' => 'Other' ) ),
		'P3'  => array( 'Team size', array() ),
		'C1'  => array( 'Main challenge', array( 'missed' => 'Missed enquiries', 'quotes' => 'Slow quotes', 'schedule' => 'Scheduling', 'retype' => 'Retyping data', 'chase' => 'Chasing payments', 'recall' => 'Recalls and follow-ups', 'other' => 'Other' ) ),
		'C2'  => array( 'Repeat admin hours / week', array() ),
		'C3'  => array( 'Slow replies / month', array() ),
		'T'   => array( 'Typical job value (CAD)', array() ),
		'R4'  => array( 'Tools today', array() ),
		'R2'  => array( 'Wants to start', array( 'month' => 'This month', '3m' => 'Within 3 months', 'year' => 'This year', 'looking' => 'Just looking' ) ),
		'R3'  => array( 'Can give an hour a week', array() ),
		'P2'  => array( 'Budget', array( '1000' => 'About $1,000', '3000' => 'About $3,000', '6000' => 'About $6,000', '99999' => 'More than $6,000', 'discuss' => 'To discuss' ) ),
		'note' => array( 'Anything else', array() ),
	);
}

function wos_survey_origins() {
	$o = trim( (string) get_option( 'wos_survey_origins', '' ) );
	if ( '' === $o ) {
		// Staging takes test posts from anywhere (the prototype runs on claude.ai); the live site only from wizad.ca.
		$o = wos_demo_allowed() ? '*' : "https://wizad.ca\nhttps://www.wizad.ca";
	}
	return array_filter( array_map( 'trim', preg_split( '/[\s,]+/', $o ) ) );
}

function wos_survey_origin_ok( $origin ) {
	$allowed = wos_survey_origins();
	return in_array( '*', $allowed, true ) || in_array( untrailingslashit( (string) $origin ), $allowed, true );
}

function wos_survey_is_route() {
	$route = isset( $GLOBALS['wp']->query_vars['rest_route'] ) ? (string) $GLOBALS['wp']->query_vars['rest_route'] : '';
	return 0 === strpos( untrailingslashit( $route ), WOS_SURVEY_ROUTE );
}

// The survey route is the one REST route that answers without sign-in.
add_filter( 'rest_authentication_errors', function ( $result ) {
	if ( wos_survey_is_route() && ! is_user_logged_in() ) {
		return true;
	}
	return $result;
}, 5 );

// CORS: answer only allowed origins, never with cookies.
add_filter( 'rest_pre_serve_request', function ( $served ) {
	if ( ! wos_survey_is_route() ) {
		return $served;
	}
	header_remove( 'Access-Control-Allow-Credentials' );
	$origin = get_http_origin();
	if ( $origin && wos_survey_origin_ok( $origin ) ) {
		header( 'Access-Control-Allow-Origin: ' . esc_url_raw( $origin ) );
		header( 'Access-Control-Allow-Methods: POST, OPTIONS' );
		header( 'Access-Control-Allow-Headers: Content-Type' );
		header( 'Vary: Origin' );
	} else {
		header_remove( 'Access-Control-Allow-Origin' );
	}
	return $served;
}, 20 );

add_action( 'rest_api_init', function () {
	register_rest_route( 'wos/v1', '/survey', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'wos_survey_receive',
	) );
} );

function wos_survey_error( $code, $message, $status ) {
	return new WP_Error( $code, $message, array( 'status' => $status ) );
}

function wos_survey_receive( WP_REST_Request $req ) {
	$origin = get_http_origin();
	if ( $origin && ! wos_survey_origin_ok( $origin ) ) {
		return wos_survey_error( 'wos_origin', 'Not allowed from this site.', 403 );
	}
	if ( strlen( (string) $req->get_body() ) > WOS_SURVEY_MAX_BYTES ) {
		return wos_survey_error( 'wos_size', 'Too large.', 413 );
	}
	$ip_key = 'wos_svy_' . md5( wos_client_ip() . wp_salt( 'nonce' ) );
	$hits   = (int) get_transient( $ip_key );
	if ( $hits >= WOS_SURVEY_PER_HOUR ) {
		return wos_survey_error( 'wos_rate', 'Too many requests. Try again later.', 429 );
	}
	set_transient( $ip_key, $hits + 1, HOUR_IN_SECONDS );

	$d = $req->get_json_params();
	if ( ! is_array( $d ) ) {
		$d = json_decode( (string) $req->get_body(), true ); // Also accept text/plain bodies (no CORS pre-flight).
	}
	if ( ! is_array( $d ) ) {
		return wos_survey_error( 'wos_json', 'Send JSON.', 400 );
	}
	// Honeypot: people never fill a hidden "website" field.
	if ( ! empty( $d['website'] ) ) {
		return array( 'ok' => true, 'sid' => wp_generate_password( 32, false, false ) );
	}
	return wos_survey_save( $d );
}

/** Clean one post and save it. Returns the response array or a WP_Error. */
function wos_survey_save( $d ) {
	global $wpdb;
	$t   = wos_table( 'surveys' );
	$str = function ( $k, $max = 190 ) use ( $d ) {
		return isset( $d[ $k ] ) && is_scalar( $d[ $k ] ) ? mb_substr( sanitize_text_field( (string) $d[ $k ] ), 0, $max ) : '';
	};
	$date = function ( $k ) use ( $d ) {
		$ts = isset( $d[ $k ] ) && is_scalar( $d[ $k ] ) ? strtotime( (string) $d[ $k ] ) : false;
		return $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : null;
	};

	$answers = array();
	$in      = isset( $d['answers'] ) && is_array( $d['answers'] ) ? $d['answers'] : $d;
	foreach ( wos_survey_fields() as $k => $rule ) {
		if ( ! isset( $in[ $k ] ) || '' === $in[ $k ] || null === $in[ $k ] ) {
			continue;
		}
		$v = $in[ $k ];
		if ( is_array( $rule ) ) {
			$v = is_scalar( $v ) ? (string) $v : '';
			if ( ! in_array( $v, $rule, true ) ) {
				return wos_survey_error( 'wos_field', 'Not a valid answer for ' . $k . '.', 400 );
			}
		} elseif ( 'num' === $rule ) {
			if ( 'ns' !== $v && ! is_numeric( $v ) ) {
				return wos_survey_error( 'wos_field', $k . ' must be a number or "ns".', 400 );
			}
			$v = 'ns' === $v ? 'ns' : max( 0, min( 100000000, (float) $v ) );
		} elseif ( 'tags' === $rule ) {
			$v = is_array( $v ) ? array_slice( array_values( array_filter( array_map( function ( $x ) {
				return is_scalar( $x ) ? mb_substr( sanitize_text_field( (string) $x ), 0, 60 ) : '';
			}, $v ) ) ), 0, 20 ) : array();
		} else {
			$v = is_scalar( $v ) ? mb_substr( sanitize_textarea_field( (string) $v ), 0, (int) substr( $rule, 5 ) ) : '';
		}
		$answers[ $k ] = $v;
	}

	$computed = array();
	$cin      = isset( $d['computed'] ) && is_array( $d['computed'] ) ? $d['computed'] : $d;
	foreach ( array( 'solution', 'readiness', 'pay', 'domain' ) as $k ) {
		if ( isset( $cin[ $k ] ) && is_numeric( $cin[ $k ] ) ) {
			$computed[ $k ] = max( 0, min( 3, (float) $cin[ $k ] ) );
		}
	}
	if ( isset( $cin['total'] ) && is_numeric( $cin['total'] ) ) {
		$computed['total'] = max( 0, min( 100, (int) round( $cin['total'] ) ) );
	}
	if ( isset( $cin['level'] ) && in_array( $cin['level'], array( 'A', 'B', 'C', 'D' ), true ) ) {
		$computed['level'] = $cin['level'];
	}
	foreach ( array( 'benefit_low', 'benefit_high' ) as $k ) {
		if ( isset( $cin[ $k ] ) && is_numeric( $cin[ $k ] ) ) {
			$computed[ $k ] = max( 0, (float) $cin[ $k ] );
		}
	}
	if ( isset( $cin['package'] ) && in_array( $cin['package'], array( 'Starter', 'Core', 'Complete' ), true ) ) {
		$computed['package'] = $cin['package'];
	}

	$ref = wos_survey_normal_ref( $str( 'lead_ref', 64 ) );
	if ( '' !== $ref && ! preg_match( '/^[A-Za-z0-9_-]{1,64}$/', $ref ) ) {
		return wos_survey_error( 'wos_ref', 'lead_ref may only hold letters, digits, - and _.', 400 );
	}
	$email = sanitize_email( $str( 'email' ) );
	$row   = array(
		'lead_ref'     => $ref,
		'form_version' => $str( 'form_version', 10 ),
		'stage'        => isset( $d['stage'] ) && is_numeric( $d['stage'] ) ? max( 0, min( 4, (int) $d['stage'] ) ) : 0,
		'name'         => $str( 'name' ),
		'business'     => $str( 'business' ),
		'email'        => $email,
		'utm_source'   => $str( 'utm_source', 120 ),
		'utm_medium'   => $str( 'utm_medium', 120 ),
		'utm_campaign' => $str( 'utm_campaign', 120 ),
		'answers'      => wp_json_encode( $answers ),
		'computed'     => wp_json_encode( $computed ),
		'started_at'   => $date( 'started_at' ),
		'completed_at' => $date( 'completed_at' ),
		'updated_at'   => wos_now(),
	);
	if ( ! empty( $d['consent'] ) && true === filter_var( $d['consent'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) ) {
		$row['consent_at'] = $date( 'consent_at' ) ? $date( 'consent_at' ) : wos_now();
	}

	$sid = $str( 'sid', 40 );
	$old = $sid ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE sid = %s", $sid ) ) : null; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( $sid && ! $old ) {
		return wos_survey_error( 'wos_sid', 'Unknown sid. Start again without one.', 404 );
	}
	// Only invited owners: the first post must carry a code WOS gave out (the lead_ref).
	$invited = $old ? null : wos_survey_invite( $ref );
	if ( ! $old && ! $invited ) {
		return wos_survey_error( 'wos_ref', 'We could not find that code (lead_ref).', 400 );
	}
	if ( $old ) {
		$row['lead_ref'] = $old->lead_ref;
	}
	if ( $old && $old->completed_at ) {
		return array( 'ok' => true, 'sid' => $old->sid, 'stored' => 'already complete' );
	}
	if ( $old ) {
		// A later step never wipes what an earlier one sent.
		$row['answers']  = wp_json_encode( array_merge( (array) json_decode( (string) $old->answers, true ), $answers ) );
		$row['computed'] = wp_json_encode( array_merge( (array) json_decode( (string) $old->computed, true ), $computed ) );
		$row['stage']    = max( (int) $old->stage, $row['stage'] );
		foreach ( array( 'lead_ref', 'form_version', 'name', 'business', 'email', 'utm_source', 'utm_medium', 'utm_campaign' ) as $k ) {
			if ( '' === $row[ $k ] ) {
				$row[ $k ] = $old->$k;
			}
		}
		foreach ( array( 'started_at', 'consent_at' ) as $k ) {
			if ( empty( $row[ $k ] ) ) {
				$row[ $k ] = $old->$k;
			}
		}
		$wpdb->update( $t, $row, array( 'id' => (int) $old->id ) );
		$id = (int) $old->id;
	} else {
		$sid               = wp_generate_password( 32, false, false );
		$row['sid']        = $sid;
		$row['created_at'] = wos_now();
		if ( empty( $row['started_at'] ) ) {
			$row['started_at'] = wos_now();
		}
		$wpdb->insert( $t, $row );
		$id = (int) $wpdb->insert_id;
	}
	$out = array( 'ok' => true, 'sid' => $sid, 'stored' => $row['completed_at'] ? 'complete' : 'step ' . $row['stage'] );
	if ( $invited ) {
		$out['business'] = $invited->name;
	}
	if ( $row['completed_at'] ) {
		$lead_id = wos_survey_to_lead( $id );
		$out['stored'] = $lead_id ? 'complete' : 'complete (lead not created)';
	}
	return $out;
}

/** A finished survey becomes a lead: the prospect with the same ref or email, or a new one. */
function wos_survey_to_lead( $survey_id ) {
	global $wpdb;
	$s = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . wos_table( 'surveys' ) . ' WHERE id = %d', $survey_id ) );
	if ( ! $s || $s->lead_id ) {
		return $s ? (int) $s->lead_id : 0;
	}
	$a = (array) json_decode( (string) $s->answers, true );
	$c = (array) json_decode( (string) $s->computed, true );
	$scores = array();
	foreach ( array( 'fit' => 'solution', 'readiness' => 'readiness', 'pay' => 'pay', 'expertise' => 'domain' ) as $mine => $theirs ) {
		$scores[ $mine ] = isset( $c[ $theirs ] ) ? (int) round( $c[ $theirs ] / 3 * 10 ) : 0;
	}
	$level = isset( $c['level'] ) ? $c['level'] : wos_lead_level( $scores );
	$lines = array();
	foreach ( wos_survey_labels() as $k => $l ) {
		if ( ! isset( $a[ $k ] ) ) {
			continue;
		}
		$v = $a[ $k ];
		$v = is_array( $v ) ? implode( ', ', $v ) : ( isset( $l[1][ $v ] ) ? $l[1][ $v ] : ( 'ns' === $v ? 'Not sure' : $v ) );
		if ( 'C1' === $k && ! empty( $a['C1_text'] ) ) {
			$v .= ' (' . $a['C1_text'] . ')';
		}
		if ( 'seg' === $k && ! empty( $a['seg_text'] ) ) {
			$v .= ' (' . $a['seg_text'] . ')';
		}
		$lines[] = $l[0] . ': ' . $v;
	}
	if ( isset( $c['total'] ) ) {
		$lines[] = 'Form score: ' . $c['total'] . '/100, level ' . $level . ( isset( $c['package'] ) ? ', fits ' . $c['package'] : '' );
	}
	if ( isset( $c['benefit_low'], $c['benefit_high'] ) ) {
		$lines[] = 'Estimated benefit: ' . wos_money( $c['benefit_low'] ) . ' to ' . wos_money( $c['benefit_high'] ) . ' a month';
	}
	$route  = wos_lead_route( $level );
	$source = 'Connected Tools Check' . ( $s->utm_source ? ' · ' . $s->utm_source : '' );

	$lead = null;
	if ( $s->lead_ref ) {
		$lead = $wpdb->get_row( $wpdb->prepare( 'SELECT id FROM ' . wos_table( 'leads' ) . ' WHERE ref = %s', $s->lead_ref ) );
	}
	if ( ! $lead && $s->email ) {
		$lead = $wpdb->get_row( $wpdb->prepare( 'SELECT id FROM ' . wos_table( 'leads' ) . " WHERE email = %s AND stage IN ('prospect','lead') ORDER BY id DESC", $s->email ) );
	}
	$fields = array(
		'level'   => $level,
		'scores'  => $scores,
		'answers' => $lines,
	);
	$text = 'Assessment received: ' . wos_lead_total( $scores ) . '/40, level ' . $level . ( isset( $c['total'] ) ? ' (form score ' . $c['total'] . '/100)' : '' ) . '.';
	if ( $lead ) {
		$l = wos_lead_get( $lead->id );
		$fields['checks'] = array_merge( $l->checks, array( 'assessed' => wos_today() ) );
		if ( 'prospect' === $l->stage ) {
			$fields['stage'] = 'D' === $level ? 'lost' : 'lead';
		}
		if ( ! $l->email && $s->email ) {
			$fields['email'] = $s->email;
		}
		$fields['next_step'] = $route[1];
		wos_lead_save( $l->id, $fields );
		wos_lead_log( $l, $text, 'Website form', 'assessment' );
		$id = (int) $l->id;
	} else {
		$id = wos_lead_save( 0, array_merge( $fields, array(
			'name'         => $s->business ? $s->business : $s->name,
			'contact_name' => $s->name,
			'email'        => $s->email,
			'source'       => $source,
			'ref'          => $s->lead_ref,
			'stage'        => 'D' === $level ? 'lost' : 'lead',
			'checks'       => array( 'assessed' => wos_today() ),
			'prelim'       => wos_lead_prelim_template( (object) array( 'level' => $level ) ),
			'next_step'    => $route[1],
			'next_date'    => wp_date( 'Y-m-d', strtotime( wos_today() . ' +2 days' ) ),
			'log'          => array( array( 'at' => wos_now(), 'by' => 'Website form', 'text' => $text, 'kind' => 'assessment' ) ),
		) ) );
	}
	$wpdb->update( wos_table( 'surveys' ), array( 'lead_id' => $id ), array( 'id' => (int) $s->id ) );
	return $id;
}

/** Invite codes look like WZ-7K2P: no 0/O or 1/I/L to misread. */
function wos_survey_new_code() {
	global $wpdb;
	$abc = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
	for ( $len = 4; $len <= 6; $len++ ) {
		for ( $try = 0; $try < 30; $try++ ) {
			$code = 'WZ-';
			for ( $i = 0; $i < $len; $i++ ) {
				$code .= $abc[ random_int( 0, strlen( $abc ) - 1 ) ];
			}
			if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . wos_table( 'leads' ) . ' WHERE ref = %s', $code ) ) ) {
				return $code;
			}
		}
	}
	return '';
}

/** People type codes by hand: accept wz7k2p, WZ 7K2P and wz-7k2p. */
function wos_survey_normal_ref( $ref ) {
	$ref = trim( (string) $ref );
	if ( preg_match( '/^wz[\s_-]*([a-z0-9]{4,6})$/i', $ref, $m ) ) {
		return 'WZ-' . strtoupper( $m[1] );
	}
	return $ref;
}

/** The lead a code was given to, or null. */
function wos_survey_invite( $ref ) {
	global $wpdb;
	if ( '' === (string) $ref ) {
		return null;
	}
	return $wpdb->get_row( $wpdb->prepare( 'SELECT id, name, stage FROM ' . wos_table( 'leads' ) . ' WHERE ref = %s ORDER BY id LIMIT 1', $ref ) );
}

/** Leads holding a code, with how far their survey got. */
function wos_survey_codes() {
	global $wpdb;
	$leads = $wpdb->get_results( 'SELECT id, name, contact_name, email, stage, ref, created_at FROM ' . wos_table( 'leads' ) . " WHERE ref <> '' ORDER BY id DESC LIMIT 500" );
	$by    = array();
	foreach ( $wpdb->get_results( 'SELECT lead_ref, stage, completed_at FROM ' . wos_table( 'surveys' ) . " WHERE lead_ref <> '' ORDER BY id" ) as $s ) {
		$by[ strtoupper( $s->lead_ref ) ] = $s;
	}
	foreach ( $leads as $l ) {
		$l->survey = isset( $by[ strtoupper( $l->ref ) ] ) ? $by[ strtoupper( $l->ref ) ] : null;
	}
	return $leads;
}

/** One spreadsheet row per survey, in words, for Google Sheets or Excel. */
function wos_survey_csv_rows() {
	$labels = wos_survey_labels();
	$steps  = array( 'Opened', 'Business', 'Challenge', 'Readiness', 'Contact' );
	$rows   = array();
	foreach ( array_reverse( wos_surveys_all( 5000 ) ) as $r ) {
		$a   = (array) json_decode( (string) $r->answers, true );
		$c   = (array) json_decode( (string) $r->computed, true );
		$row = array(
			'Code'      => $r->lead_ref,
			'Started'   => (string) $r->started_at,
			'Finished'  => (string) $r->completed_at,
			'Got to'    => $r->completed_at ? 'Finished' : ( isset( $steps[ (int) $r->stage ] ) ? $steps[ (int) $r->stage ] : 'Step ' . (int) $r->stage ),
			'Name'      => $r->name,
			'Business'  => $r->business,
			'Email'     => $r->email,
			'Consent'   => (string) $r->consent_at,
			'Source'    => $r->utm_source,
			'Medium'    => $r->utm_medium,
			'Campaign'  => $r->utm_campaign,
		);
		foreach ( $labels as $k => $l ) {
			$v = isset( $a[ $k ] ) ? $a[ $k ] : '';
			$v = is_array( $v ) ? implode( ', ', $v ) : ( isset( $l[1][ $v ] ) ? $l[1][ $v ] : ( 'ns' === $v ? 'Not sure' : $v ) );
			$row[ $l[0] ] = $v;
			if ( 'seg' === $k ) {
				$row['Field in their words'] = isset( $a['seg_text'] ) ? $a['seg_text'] : '';
			}
			if ( 'C1' === $k ) {
				$row['Challenge in their words'] = isset( $a['C1_text'] ) ? $a['C1_text'] : '';
			}
		}
		$row['Score /100']        = isset( $c['total'] ) ? $c['total'] : '';
		$row['Level']             = isset( $c['level'] ) ? $c['level'] : '';
		$row['Package']           = isset( $c['package'] ) ? $c['package'] : '';
		$row['Benefit low / mo']  = isset( $c['benefit_low'] ) ? $c['benefit_low'] : '';
		$row['Benefit high / mo'] = isset( $c['benefit_high'] ) ? $c['benefit_high'] : '';
		$row['Lead in WOS']       = $r->lead_id ? wos_app_url( 'leads/' . (int) $r->lead_id ) : '';
		$rows[] = $row;
	}
	return $rows;
}

function wos_survey_csv_send( $name, $rows, $head ) {
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="wos-' . $name . '-' . wos_today() . '.csv"' );
	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" ); // So Excel reads UTF-8; Google Sheets ignores it.
	fputcsv( $out, $head );
	foreach ( $rows as $r ) {
		// Stop spreadsheet formula injection from typed answers.
		fputcsv( $out, array_map( function ( $v ) {
			$v = (string) $v;
			return preg_match( '/^[=+\-@\t\r]/', $v ) && ! is_numeric( $v ) ? "'" . $v : $v;
		}, array_values( $r ) ) );
	}
	fclose( $out );
	exit;
}

function wos_survey_csv_url( $what ) {
	return wp_nonce_url( add_query_arg( array( 'action' => 'wos_survey', 'do' => 'csv', 'what' => $what ), admin_url( 'admin-post.php' ) ), 'wos_survey' );
}

function wos_survey_code_status( $l ) {
	if ( ! $l->survey ) {
		return 'Not opened';
	}
	$steps = array( 'Opened', 'Business', 'Challenge', 'Readiness', 'Contact' );
	return $l->survey->completed_at ? 'Finished' : 'Stopped at ' . ( isset( $steps[ (int) $l->survey->stage ] ) ? $steps[ (int) $l->survey->stage ] : 'step ' . (int) $l->survey->stage );
}

function wos_surveys_all( $limit = 200 ) {
	global $wpdb;
	return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . wos_table( 'surveys' ) . ' ORDER BY updated_at DESC LIMIT %d', $limit ) );
}

// Delete one survey record (a privacy request), or save the allowed origins.
add_action( 'admin_post_wos_survey', function () {
	wos_require_cap();
	check_admin_referer( 'wos_survey' );
	global $wpdb;
	$do = isset( $_REQUEST['do'] ) ? sanitize_key( wp_unslash( $_REQUEST['do'] ) ) : '';
	if ( 'csv' === $do ) {
		if ( 'codes' === ( isset( $_REQUEST['what'] ) ? sanitize_key( wp_unslash( $_REQUEST['what'] ) ) : '' ) ) {
			$rows = array();
			foreach ( wos_survey_codes() as $l ) {
				$rows[] = array( $l->ref, $l->name, $l->contact_name, $l->email, wos_survey_code_status( $l ), wos_app_url( 'leads/' . (int) $l->id ) );
			}
			wos_survey_csv_send( 'invite-codes', $rows, array( 'Code', 'Business', 'Contact', 'Email', 'Survey', 'Lead in WOS' ) );
		}
		$rows = wos_survey_csv_rows();
		wos_survey_csv_send( 'survey-answers', $rows, $rows ? array_keys( $rows[0] ) : array( 'No answers yet' ) );
	}
	if ( 'invite' === $do ) {
		// One business per line: Business, Contact name, Email. Each becomes a prospect with a code.
		$raw  = isset( $_POST['list'] ) ? sanitize_textarea_field( wp_unslash( $_POST['list'] ) ) : '';
		$made = 0;
		foreach ( array_slice( preg_split( '/\r\n|\n/', $raw ), 0, 200 ) as $line ) {
			$p = array_map( 'trim', str_getcsv( $line ) );
			if ( '' === $p[0] ) {
				continue;
			}
			$code = wos_survey_new_code();
			if ( ! $code ) {
				break;
			}
			wos_lead_save( 0, array(
				'name'         => mb_substr( $p[0], 0, 190 ),
				'contact_name' => isset( $p[1] ) ? mb_substr( $p[1], 0, 190 ) : '',
				'email'        => isset( $p[2] ) ? sanitize_email( $p[2] ) : '',
				'source'       => 'Survey invite',
				'stage'        => 'prospect',
				'ref'          => $code,
				'checks'       => array(),
				'log'          => array( array( 'at' => wos_now(), 'by' => wp_get_current_user()->display_name, 'text' => 'Survey code ' . $code . ' given.', 'kind' => 'link' ) ),
			) );
			$made++;
		}
		wos_flash( $made ? $made . ' prospect' . ( 1 === $made ? '' : 's' ) . ' added, each with a survey code.' : 'Nothing added. Put one business per line.', $made ? 'success' : 'warning' );
	} elseif ( 'give' === $do ) {
		$ids  = $wpdb->get_col( 'SELECT id FROM ' . wos_table( 'leads' ) . " WHERE ref = '' AND stage IN ('prospect','lead') LIMIT 500" );
		foreach ( $ids as $id ) {
			$code = wos_survey_new_code();
			if ( $code ) {
				$l = wos_lead_get( $id );
				wos_lead_save( $id, array( 'ref' => $code ) );
				wos_lead_log( $l, 'Survey code ' . $code . ' given.', null, 'link' );
			}
		}
		wos_flash( count( $ids ) ? count( $ids ) . ' lead' . ( 1 === count( $ids ) ? '' : 's' ) . ' given a survey code.' : 'Every prospect and lead already has a code.' );
	} elseif ( 'delete' === $do ) {
		$wpdb->delete( wos_table( 'surveys' ), array( 'id' => isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 ) );
		wos_flash( 'Survey answers deleted. A lead made from them stays; delete it in its file if needed.' );
	} elseif ( 'origins' === $do && current_user_can( 'manage_options' ) ) {
		$raw = isset( $_POST['origins'] ) ? sanitize_textarea_field( wp_unslash( $_POST['origins'] ) ) : '';
		$ok  = array();
		foreach ( preg_split( '/[\s,]+/', $raw ) as $o ) {
			if ( '*' === $o || preg_match( '#^https://[a-z0-9.-]+(:\d+)?$#i', $o ) ) {
				$ok[] = $o;
			}
		}
		update_option( 'wos_survey_origins', implode( "\n", $ok ), false );
		wos_flash( 'Allowed sites saved.' );
	}
	wp_safe_redirect( wos_app_url( 'leads', array( 'tab' => 'survey' ) ) . ( in_array( $do, array( 'invite', 'give' ), true ) ? '#codes' : '' ) );
	exit;
} );
