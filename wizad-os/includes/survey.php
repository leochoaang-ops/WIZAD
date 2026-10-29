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
define( 'WOS_SURVEY_OPEN_PER_HOUR', 5 );
define( 'WOS_SURVEY_OPEN_PER_DAY', 100 );
define( 'WOS_SURVEY_OPEN_MIN_SECONDS', 60 );

/**
 * Allowed answers per question. A list = one of; array( 'many' => list, 'max' => n, 'only' => code ) = several of,
 * where 'only' can't go with others; 'num' = a number or "ns"; 'text:N' = text up to N characters; 'tags' = short texts.
 * C1 is form v5; C1m and the questions after it are form v6.
 */
function wos_survey_fields() {
	return array(
		'loc'     => array( 'westshore', 'sooke', 'core', 'peninsula', 'island' ),
		'seg'     => array( 'trades', 'notary', 'law', 'realty', 'dental', 'health', 'money', 'prof', 'food', 'retail', 'personal', 'tourism', 'home', 'auto', 'care', 'other' ),
		'seg_text' => 'text:120',
		'P3'      => array( '1-2', '3-5', '6-20', '20+' ),
		'C1'      => array( 'missed', 'quotes', 'schedule', 'retype', 'chase', 'recall', 'other' ),
		'C1m'     => array( 'many' => wos_survey_c1m(), 'max' => 8, 'only' => 'none' ),
		'C1top'   => array( 'missed', 'quotes', 'schedule', 'retype', 'chase', 'recall', 'other' ),
		'C1_text' => 'text:160',
		'C2'      => array( '3', '7.5', '15', '30', '45', 'ns' ),
		'C3'      => 'num',
		'T'       => 'num',
		'R4'      => 'tags',
		'fit'     => array( 'poor', 'partly', 'mostly', 'fully' ),
		'usage'   => array( 'little', 'half', 'most', 'ns' ),
		'barrier' => array( 'many' => wos_survey_barriers(), 'max' => 2, 'only' => 'fine' ),
		'spend'   => array( 'none', 'u100', '100-500', '500-1500', '1500+', 'ns' ),
		'R2'      => array( 'month', '3m', 'year', 'looking', 'no' ),
		'R3'      => array( 'yes', 'maybe', 'no' ),
		'P2'      => array( '1000', '3000', '6000', '99999', 'discuss' ),
		'note'    => 'text:600',
	);
}

function wos_survey_c1m() {
	return array( 'missed', 'chase', 'retype', 'schedule', 'quotes', 'recall', 'other', 'none' );
}

function wos_survey_barriers() {
	return array( 'time', 'complex', 'connect', 'staff', 'setup', 'cost', 'fine', 'other' );
}

/** What the report's buttons ask for: label, and the follow-up we owe (empty when the booking itself is the follow-up). */
function wos_survey_ctas() {
	return array(
		'book'      => array( 'Book a discovery call', '' ),
		'book_link' => array( 'Booking link', '' ),
		'next_step' => array( 'Send the next step', 'Send the next step they asked for in the report' ),
		'guide'     => array( 'Send the guide', 'Send the guide they asked for in the report' ),
		'referral'  => array( 'Referral', 'Follow up the referral from the report' ),
	);
}

/** Report requests we still owe a reply to. */
function wos_survey_owed() {
	global $wpdb;
	return $wpdb->get_results( 'SELECT * FROM ' . wos_table( 'surveys' ) . " WHERE cta IN ('next_step','guide','referral') AND cta_done IS NULL ORDER BY cta_at" );
}

/** The step a form stopped at, by form version. */
function wos_survey_step_name( $s ) {
	$steps = 'v6' === $s->form_version ? array( 'Business', 'Past month', 'Cost', 'Tools', 'Timing', 'Report' ) : array( 'Opened', 'Business', 'Challenge', 'Readiness', 'Contact' );
	return isset( $steps[ (int) $s->stage ] ) ? $steps[ (int) $s->stage ] : 'step ' . (int) $s->stage;
}

/** What each answer means, for the lead file. */
function wos_survey_labels() {
	$c1 = array( 'missed' => 'Missed enquiries', 'quotes' => 'Slow quotes', 'schedule' => 'Scheduling', 'retype' => 'Retyping data', 'chase' => 'Chasing documents or payments', 'recall' => 'Recalls and follow-ups', 'other' => 'Other', 'none' => 'None of these' );
	return array(
		'loc'     => array( 'Based in', array( 'westshore' => 'West Shore', 'sooke' => 'Sooke', 'core' => 'Victoria core', 'peninsula' => 'Saanich Peninsula', 'island' => 'Elsewhere on the Island' ) ),
		'seg'     => array( 'Field', array( 'trades' => 'Trades', 'notary' => 'Notary', 'law' => 'Law', 'realty' => 'Real estate', 'dental' => 'Dental', 'health' => 'Health and wellness clinic', 'money' => 'Accounting, bookkeeping or insurance', 'prof' => 'Other professional services', 'food' => 'Food', 'retail' => 'Retail', 'personal' => 'Personal services', 'tourism' => 'Accommodation and tours', 'home' => 'Home and property services', 'auto' => 'Auto', 'care' => 'Childcare and education', 'other' => 'Other' ) ),
		'P3'      => array( 'Team size', array() ),
		'C1'      => array( 'Main challenge', $c1 ),
		'C1m'     => array( 'Came up in the past 30 days', $c1 ),
		'C1top'   => array( 'Cost the most', $c1 ),
		'C2'      => array( 'Repeat admin hours / week', array() ),
		'C3'      => array( 'Slow replies / month', array() ),
		'T'       => array( 'Typical job value (CAD)', array() ),
		'R4'      => array( 'Tools today', array() ),
		'fit'     => array( 'Tools fit the work', array( 'poor' => 'Poorly', 'partly' => 'Partly', 'mostly' => 'Mostly', 'fully' => 'Fully' ) ),
		'usage'   => array( 'Software really used', array( 'little' => 'A little', 'half' => 'About half', 'most' => 'Most of it' ) ),
		'barrier' => array( 'In the way', array( 'time' => 'No time to learn', 'complex' => 'Too complicated', 'connect' => "Tools don't connect", 'staff' => "Staff don't use them", 'setup' => 'Set up wrong', 'cost' => 'Too costly', 'fine' => 'Nothing, they work fine', 'other' => 'Other' ) ),
		'spend'   => array( 'Software spend / month', array( 'none' => 'Nothing', 'u100' => 'Under $100', '100-500' => '$100 to $500', '500-1500' => '$500 to $1,500', '1500+' => 'More than $1,500' ) ),
		'R2'      => array( 'Wants to start', array( 'month' => 'Within a month', '3m' => 'In 1 to 3 months', 'year' => 'Later this year', 'looking' => 'Just exploring', 'no' => 'Not looking now' ) ),
		'R3'      => array( 'Can give an hour a week', array( 'yes' => 'Yes', 'maybe' => 'Probably', 'no' => 'Not right now' ) ),
		'P2'      => array( 'Budget', array( '1000' => 'Under $1,500', '3000' => '$1,500 to $3,000', '6000' => '$3,000 to $6,000', '99999' => 'More, if the case is clear', 'discuss' => 'To discuss' ) ),
		'note'    => array( 'Anything else', array() ),
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
		if ( is_array( $rule ) && isset( $rule['many'] ) ) {
			$v = is_array( $v ) ? array_values( array_unique( array_map( 'strval', array_filter( $v, 'is_scalar' ) ) ) ) : array();
			if ( array_diff( $v, $rule['many'] ) || count( $v ) > $rule['max'] || ( count( $v ) > 1 && in_array( $rule['only'], $v, true ) ) ) {
				return wos_survey_error( 'wos_field', 'Not a valid answer for ' . $k . '.', 400 );
			}
		} elseif ( is_array( $rule ) ) {
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
	foreach ( array( 'C2_imputed', 'C3_imputed', 'T_imputed', 'problem_any', 'costly', 'gap' ) as $k ) {
		if ( isset( $cin[ $k ] ) && is_scalar( $cin[ $k ] ) ) {
			$computed[ $k ] = $cin[ $k ] ? 1 : 0;
		}
	}
	if ( isset( $cin['main_challenge'] ) && in_array( $cin['main_challenge'], wos_survey_c1m(), true ) ) {
		$computed['main_challenge'] = $cin['main_challenge'];
	}

	$ref = wos_survey_normal_ref( $str( 'lead_ref', 64 ) );
	if ( '' !== $ref && ! preg_match( '/^[A-Za-z0-9_-]{1,64}$/', $ref ) ) {
		return wos_survey_error( 'wos_ref', 'lead_ref may only hold letters, digits, - and _.', 400 );
	}
	$email = sanitize_email( $str( 'email' ) );
	$row   = array(
		'lead_ref'     => $ref,
		'form_version' => $str( 'form_version', 10 ),
		'source'       => in_array( $str( 'source', 20 ), array( 'interviewed', 'invited', 'open' ), true ) ? $str( 'source', 20 ) : '',
		'stage'        => isset( $d['stage'] ) && is_numeric( $d['stage'] ) ? max( 0, min( 9, (int) $d['stage'] ) ) : 0,
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
	// Invited owners bring a code WOS gave out (the lead_ref). The open link (QR, posts) starts
	// without one, only while Leo has it switched on, and within tighter limits.
	$open    = ! $old && '' === $ref && 'open' === $row['source'];
	$invited = $old || $open ? null : wos_survey_invite( $ref );
	if ( $open ) {
		$closed = wos_survey_open_check();
		if ( $closed ) {
			return $closed;
		}
	} elseif ( ! $old && ! $invited ) {
		return wos_survey_error( 'wos_ref', 'We could not find that code (lead_ref).', 400 );
	}
	$is_open = $open || ( $old && '' === $old->lead_ref && 'open' === $old->source );
	if ( $is_open && $row['completed_at'] && '' === $row['business'] && ( ! $old || '' === $old->business ) ) {
		return wos_survey_error( 'wos_business', 'A business name is needed to finish.', 400 );
	}
	if ( $old ) {
		$row['lead_ref'] = $old->lead_ref;
	} elseif ( $invited ) {
		// Invited: start from what WOS already holds, so the form need not ask again.
		foreach ( array( 'business' => 'name', 'name' => 'contact_name', 'email' => 'email' ) as $mine => $theirs ) {
			if ( '' === $row[ $mine ] ) {
				$row[ $mine ] = $invited->$theirs;
			}
		}
	}
	if ( $old && $old->completed_at ) {
		// After the report: the button they pressed (book a call, ask for a guide...). Only this is taken.
		$cta = $str( 'cta', 20 );
		if ( isset( wos_survey_ctas()[ $cta ] ) ) {
			$at = $date( 'cta_at' ) ? $date( 'cta_at' ) : wos_now();
			$wpdb->update( $t, array( 'cta' => $cta, 'cta_at' => $at, 'cta_done' => null, 'updated_at' => wos_now() ), array( 'id' => (int) $old->id ) );
			$l = $old->lead_id ? wos_lead_get( $old->lead_id ) : null;
			if ( $l ) {
				wos_lead_log( $l, 'Asked from the report: ' . wos_survey_ctas()[ $cta ][0] . '.', 'Website form', 'client' );
				if ( wos_survey_ctas()[ $cta ][1] ) {
					wos_lead_save( $l->id, array( 'next_step' => wos_survey_ctas()[ $cta ][1], 'next_date' => wos_today() ) );
				}
			}
			return array( 'ok' => true, 'sid' => $old->sid, 'stored' => 'cta' );
		}
		return array( 'ok' => true, 'sid' => $old->sid, 'stored' => 'already complete' );
	}
	if ( $old ) {
		// A later step never wipes what an earlier one sent.
		$row['answers']  = wp_json_encode( array_merge( (array) json_decode( (string) $old->answers, true ), $answers ) );
		$row['computed'] = wp_json_encode( array_merge( (array) json_decode( (string) $old->computed, true ), $computed ) );
		$row['stage']    = max( (int) $old->stage, $row['stage'] );
		foreach ( array( 'lead_ref', 'form_version', 'source', 'name', 'business', 'email', 'utm_source', 'utm_medium', 'utm_campaign' ) as $k ) {
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
		// What we already know about an invited business, so the form can skip those questions.
		// The email goes back masked: whoever holds a code sees whether it is theirs, not the address itself.
		$out['business']     = $invited->name;
		$out['contact_name'] = $invited->contact_name;
		if ( $invited->email ) {
			$out['email_masked'] = wos_mask_email( $invited->email );
		}
		foreach ( array( 'seg', 'loc' ) as $k ) {
			if ( $invited->$k ) {
				$out[ $k ] = $invited->$k;
			}
		}
	}
	if ( $row['completed_at'] ) {
		// A bot fills a form in seconds; a person takes minutes. Too fast: kept, flagged, no lead.
		$took = $old ? strtotime( wos_now() ) - strtotime( $old->created_at ) : 0;
		if ( $is_open && $took < WOS_SURVEY_OPEN_MIN_SECONDS ) {
			$c = (array) json_decode( (string) $wpdb->get_var( $wpdb->prepare( "SELECT computed FROM $t WHERE id = %d", $id ) ), true ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->update( $t, array( 'computed' => wp_json_encode( array_merge( $c, array( 'suspect' => 1 ) ) ) ), array( 'id' => $id ) );
			$out['stored'] = 'complete';
			return $out;
		}
		$lead_id = wos_survey_to_lead( $id );
		$out['stored'] = $lead_id ? 'complete' : 'complete (lead not created)';
		if ( $lead_id && $is_open ) {
			$out['code'] = (string) $wpdb->get_var( $wpdb->prepare( "SELECT lead_ref FROM $t WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
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
		$v = wos_survey_words( $a[ $k ], $l[1] );
		if ( in_array( $k, array( 'C1', 'C1m' ), true ) && ! empty( $a['C1_text'] ) ) {
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

	$source = 'Connected Tools Check' . ( 'open' === $s->source ? ' · open link' : '' ) . ( $s->utm_source ? ' · ' . $s->utm_source : '' );

	$lead = null;
	if ( $s->lead_ref ) {
		$lead = $wpdb->get_row( $wpdb->prepare( 'SELECT id FROM ' . wos_table( 'leads' ) . ' WHERE ref = %s', $s->lead_ref ) );
	}
	if ( ! $lead && $s->email ) {
		$lead = $wpdb->get_row( $wpdb->prepare( 'SELECT id FROM ' . wos_table( 'leads' ) . " WHERE email = %s AND stage IN ('prospect','lead') ORDER BY id DESC", $s->email ) );
	}
	if ( '' === $s->lead_ref && 'open' === $s->source ) {
		// The open link: the business gets its code now, to come back with (the one it already has, if we know it).
		$known       = $lead ? (string) $wpdb->get_var( $wpdb->prepare( 'SELECT ref FROM ' . wos_table( 'leads' ) . ' WHERE id = %d', $lead->id ) ) : '';
		$s->lead_ref = $known ? $known : wos_survey_new_code();
		$wpdb->update( wos_table( 'surveys' ), array( 'lead_ref' => $s->lead_ref ), array( 'id' => (int) $s->id ) );
		if ( $lead && ! $known ) {
			wos_lead_save( $lead->id, array( 'ref' => $s->lead_ref ) );
		}
	}
	$fields = array(
		'seg'     => isset( $a['seg'] ) ? $a['seg'] : '',
		'loc'     => isset( $a['loc'] ) ? $a['loc'] : '',
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

/** The open link is off until Leo switches it on. Returns a WP_Error when a new open start is not allowed. */
function wos_survey_open_check() {
	if ( ! get_option( 'wos_survey_open' ) ) {
		return wos_survey_error( 'wos_closed', 'The open link is closed. Ask us for a code.', 403 );
	}
	$ip  = 'wos_svyo_' . md5( wos_client_ip() . wp_salt( 'nonce' ) );
	$day = 'wos_svyo_day_' . gmdate( 'Ymd' );
	if ( (int) get_transient( $ip ) >= WOS_SURVEY_OPEN_PER_HOUR || (int) get_transient( $day ) >= WOS_SURVEY_OPEN_PER_DAY ) {
		return wos_survey_error( 'wos_rate', 'Too many new starts. Try again later.', 429 );
	}
	set_transient( $ip, (int) get_transient( $ip ) + 1, HOUR_IN_SECONDS );
	set_transient( $day, (int) get_transient( $day ) + 1, DAY_IN_SECONDS );
	return null;
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
	return $wpdb->get_row( $wpdb->prepare( 'SELECT id, name, contact_name, email, stage, seg, loc FROM ' . wos_table( 'leads' ) . ' WHERE ref = %s ORDER BY id LIMIT 1', $ref ) );
}

/** Leads holding a code, with how far their survey got. */
function wos_survey_codes() {
	global $wpdb;
	$leads = $wpdb->get_results( 'SELECT id, name, contact_name, email, stage, ref, created_at FROM ' . wos_table( 'leads' ) . " WHERE ref <> '' ORDER BY id DESC LIMIT 500" );
	$by    = array();
	foreach ( $wpdb->get_results( 'SELECT lead_ref, stage, form_version, completed_at FROM ' . wos_table( 'surveys' ) . " WHERE lead_ref <> '' ORDER BY id" ) as $s ) {
		$by[ strtoupper( $s->lead_ref ) ] = $s;
	}
	foreach ( $leads as $l ) {
		$l->survey = isset( $by[ strtoupper( $l->ref ) ] ) ? $by[ strtoupper( $l->ref ) ] : null;
	}
	return $leads;
}

/** An answer in words: a code, a list of codes, or "ns". */
function wos_survey_words( $v, $map ) {
	if ( is_array( $v ) ) {
		return implode( ', ', array_map( function ( $x ) use ( $map ) {
			return isset( $map[ $x ] ) ? $map[ $x ] : $x;
		}, $v ) );
	}
	return isset( $map[ $v ] ) ? $map[ $v ] : ( 'ns' === $v ? 'Not sure' : $v );
}

/**
 * One row per survey for analysis in Google Sheets or Excel, coded as in the research design (4.1):
 * answers keep their codes, "Not sure" is an empty cell with an *_imputed flag, and each option of a
 * multi-choice question is its own 0/1 column (empty when the question was not asked).
 */
function wos_survey_csv_rows() {
	$surveys = array_reverse( wos_surveys_all( 5000 ) );
	$tools   = array();
	foreach ( $surveys as $r ) {
		$a = (array) json_decode( (string) $r->answers, true );
		foreach ( isset( $a['R4'] ) ? (array) $a['R4'] : array() as $t ) {
			$tools[ $t ] = true;
		}
	}
	ksort( $tools );
	$multi = array( 'C1m' => wos_survey_c1m(), 'R4' => array_keys( $tools ), 'barrier' => wos_survey_barriers() );
	$cell  = function ( $a, $k ) {
		return isset( $a[ $k ] ) && 'ns' !== $a[ $k ] && ! is_array( $a[ $k ] ) ? $a[ $k ] : '';
	};
	$rows = array();
	foreach ( $surveys as $r ) {
		$a   = (array) json_decode( (string) $r->answers, true );
		$c   = (array) json_decode( (string) $r->computed, true );
		$row = array(
			'code'         => $r->lead_ref,
			'source'       => $r->source,
			'form_version' => $r->form_version,
			'started_at'   => (string) $r->started_at,
			'completed_at' => (string) $r->completed_at,
			'got_to'       => $r->completed_at ? 'Finished' : wos_survey_step_name( $r ),
			'name'         => $r->name,
			'business'     => $r->business,
			'email'        => $r->email,
			'consent_at'   => (string) $r->consent_at,
			'utm_source'   => $r->utm_source,
			'utm_medium'   => $r->utm_medium,
			'utm_campaign' => $r->utm_campaign,
		);
		foreach ( array( 'loc', 'seg', 'seg_text', 'P3', 'C1' ) as $k ) {
			$row[ $k ] = $cell( $a, $k );
		}
		foreach ( $multi as $k => $opts ) {
			if ( 'R4' === $k ) {
				$row['C1top']   = $cell( $a, 'C1top' );
				$row['C1_text'] = $cell( $a, 'C1_text' );
				foreach ( array( 'C2', 'C3', 'T' ) as $n ) {
					$row[ $n ]              = $cell( $a, $n );
					$row[ $n . '_imputed' ] = isset( $c[ $n . '_imputed' ] ) ? $c[ $n . '_imputed' ] : ( isset( $a[ $n ] ) ? ( 'ns' === $a[ $n ] ? 1 : 0 ) : '' );
				}
			}
			foreach ( $opts as $o ) {
				$row[ $k . '_' . $o ] = isset( $a[ $k ] ) && is_array( $a[ $k ] ) ? ( in_array( $o, $a[ $k ], true ) ? 1 : 0 ) : '';
			}
			if ( 'R4' === $k ) {
				$row['fit']   = $cell( $a, 'fit' );
				$row['usage'] = $cell( $a, 'usage' );
			}
		}
		foreach ( array( 'spend', 'R2', 'R3', 'P2', 'note' ) as $k ) {
			$row[ $k ] = $cell( $a, $k );
		}
		foreach ( array( 'problem_any', 'costly', 'gap', 'main_challenge', 'total', 'level', 'package', 'benefit_low', 'benefit_high', 'suspect' ) as $k ) {
			$row[ $k ] = isset( $c[ $k ] ) ? $c[ $k ] : '';
		}
		$row['cta']              = $r->cta;
		$row['cta_at']           = (string) $r->cta_at;
		$row['discovery_booked'] = $r->completed_at ? ( $r->booked_date ? 1 : 0 ) : '';
		$row['booked_date']      = (string) $r->booked_date;
		$row['lead_in_wos']      = $r->lead_id ? wos_app_url( 'leads/' . (int) $r->lead_id ) : '';
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
	return $l->survey->completed_at ? 'Finished' : 'Stopped at ' . wos_survey_step_name( $l->survey );
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
		// One business per line: Business, Contact name, Email, field, area. Each becomes a prospect with a code.
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
				'seg'          => isset( $p[3] ) && in_array( strtolower( $p[3] ), wos_survey_fields()['seg'], true ) ? strtolower( $p[3] ) : '',
				'loc'          => isset( $p[4] ) && in_array( strtolower( $p[4] ), wos_survey_fields()['loc'], true ) ? strtolower( $p[4] ) : '',
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
	} elseif ( 'cta_done' === $do ) {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$wpdb->update( wos_table( 'surveys' ), array( 'cta_done' => wos_now() ), array( 'id' => $id ) );
		$s = $wpdb->get_row( $wpdb->prepare( 'SELECT lead_id, cta FROM ' . wos_table( 'surveys' ) . ' WHERE id = %d', $id ) );
		if ( $s && $s->lead_id && ( $l = wos_lead_get( $s->lead_id ) ) && isset( wos_survey_ctas()[ $s->cta ] ) ) {
			wos_lead_log( $l, 'Done: ' . wos_survey_ctas()[ $s->cta ][0] . '.', null, 'sent' );
		}
		wos_flash( 'Marked done.' );
	} elseif ( 'booked' === $do ) {
		// The report ends with "Book a 20-minute discovery call": record when they booked (the real-demand measure).
		$id   = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$date = isset( $_POST['booked_date'] ) ? sanitize_text_field( wp_unslash( $_POST['booked_date'] ) ) : '';
		$date = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : null;
		$wpdb->update( wos_table( 'surveys' ), array( 'booked_date' => $date ), array( 'id' => $id ) );
		$s = $wpdb->get_row( $wpdb->prepare( 'SELECT lead_id FROM ' . wos_table( 'surveys' ) . ' WHERE id = %d', $id ) );
		if ( $date && $s && $s->lead_id && ( $l = wos_lead_get( $s->lead_id ) ) ) {
			wos_lead_log( $l, 'Discovery call booked for ' . $date . '.', null, 'client' );
		}
		wos_flash( $date ? 'Discovery call recorded.' : 'Booking cleared.' );
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
	} elseif ( 'open' === $do && current_user_can( 'manage_options' ) ) {
		$on = ! empty( $_POST['on'] );
		update_option( 'wos_survey_open', $on ? 1 : 0, false );
		wos_flash( $on ? 'Open link on: anyone with the link can take the survey.' : 'Open link off: only invite codes work.' );
	}
	wp_safe_redirect( wos_app_url( 'leads', array( 'tab' => 'survey' ) ) . ( in_array( $do, array( 'invite', 'give' ), true ) ? '#codes' : '' ) );
	exit;
} );
