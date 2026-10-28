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

/** Allowed answers per question. A list = one of; 'num' = a number or "ns"; 'text' = short text; 'tags' = a list of short texts. */
function wos_survey_fields() {
	return array(
		'seg'    => array( 'trades', 'notary', 'law', 'realty', 'dental', 'other' ),
		'P3'     => array( '1-2', '3-5', '6-20', '20+' ),
		'C1'     => array( 'missed', 'quotes', 'schedule', 'retype', 'chase', 'recall', 'other' ),
		'C1_text' => 'text',
		'C2'     => array( '3', '7.5', '15', '30', '45' ),
		'C3'     => 'num',
		'T'      => 'num',
		'R4'     => 'tags',
		'R2'     => array( 'month', '3m', 'year', 'looking' ),
		'R3'     => array( 'yes', 'maybe', 'no' ),
		'P2'     => array( '1000', '3000', '6000', '99999', 'discuss' ),
	);
}

/** What each answer means, for the lead file. */
function wos_survey_labels() {
	return array(
		'seg' => array( 'Field', array( 'trades' => 'Trades', 'notary' => 'Notary', 'law' => 'Law', 'realty' => 'Real estate', 'dental' => 'Dental', 'other' => 'Other' ) ),
		'P3'  => array( 'Team size', array() ),
		'C1'  => array( 'Main challenge', array( 'missed' => 'Missed enquiries', 'quotes' => 'Slow quotes', 'schedule' => 'Scheduling', 'retype' => 'Retyping data', 'chase' => 'Chasing payments', 'recall' => 'Recalls and follow-ups', 'other' => 'Other' ) ),
		'C2'  => array( 'Repeat admin hours / week', array() ),
		'C3'  => array( 'Slow replies / month', array() ),
		'T'   => array( 'Typical job value (CAD)', array() ),
		'R4'  => array( 'Tools today', array() ),
		'R2'  => array( 'Wants to start', array( 'month' => 'This month', '3m' => 'Within 3 months', 'year' => 'This year', 'looking' => 'Just looking' ) ),
		'R3'  => array( 'Can give an hour a week', array() ),
		'P2'  => array( 'Budget', array( '1000' => 'About $1,000', '3000' => 'About $3,000', '6000' => 'About $6,000', '99999' => 'More than $6,000', 'discuss' => 'To discuss' ) ),
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
			$v = is_scalar( $v ) ? mb_substr( sanitize_textarea_field( (string) $v ), 0, 500 ) : '';
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

	$ref = $str( 'lead_ref', 64 );
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

function wos_surveys_all( $limit = 200 ) {
	global $wpdb;
	return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . wos_table( 'surveys' ) . ' ORDER BY updated_at DESC LIMIT %d', $limit ) );
}

// Delete one survey record (a privacy request), or save the allowed origins.
add_action( 'admin_post_wos_survey', function () {
	wos_require_cap();
	check_admin_referer( 'wos_survey' );
	global $wpdb;
	$do = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
	if ( 'delete' === $do ) {
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
	wp_safe_redirect( wos_app_url( 'leads', array( 'tab' => 'survey' ) ) );
	exit;
} );
