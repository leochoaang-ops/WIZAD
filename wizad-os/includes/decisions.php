<?php
/**
 * Sprint 0: the decision queue.
 *
 * Any choice only Leo can make waits here with its options and a
 * recommendation. Leo answers in one tap or in his own words, or sets it
 * aside to a date. Every answer goes to the decision log.
 */
defined( 'ABSPATH' ) || exit;

/** A decision row with its options decoded. */
function wos_decision_prepare( $row ) {
	$row->options = json_decode( (string) $row->options, true );
	if ( ! is_array( $row->options ) ) {
		$row->options = array();
	}
	return $row;
}

function wos_decision_get( $id ) {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . wos_table( 'decisions' ) . ' WHERE id = %d', $id ) );
	return $row ? wos_decision_prepare( $row ) : null;
}

/** Set-aside decisions whose date has come go back to the queue. */
function wos_decisions_wake() {
	global $wpdb;
	$wpdb->query( $wpdb->prepare(
		'UPDATE ' . wos_table( 'decisions' ) . " SET status = 'waiting', aside_until = NULL, updated_at = %s WHERE status = 'aside' AND aside_until <= %s",
		wos_now(),
		wos_today()
	) );
}

/** Waiting decisions, oldest first. */
function wos_decisions_waiting() {
	global $wpdb;
	wos_decisions_wake();
	$rows = $wpdb->get_results( 'SELECT * FROM ' . wos_table( 'decisions' ) . " WHERE status = 'waiting' ORDER BY created_at ASC, id ASC" );
	return array_map( 'wos_decision_prepare', $rows );
}

function wos_decisions_aside() {
	global $wpdb;
	$rows = $wpdb->get_results( 'SELECT * FROM ' . wos_table( 'decisions' ) . " WHERE status = 'aside' ORDER BY aside_until ASC" );
	return array_map( 'wos_decision_prepare', $rows );
}

function wos_decisions_log( $limit = 100 ) {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . wos_table( 'decisions' ) . " WHERE status IN ('decided','withdrawn') ORDER BY decided_at DESC, id DESC LIMIT %d", $limit ) );
	return array_map( 'wos_decision_prepare', $rows );
}

function wos_decisions_count() {
	global $wpdb;
	wos_decisions_wake();
	return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . wos_table( 'decisions' ) . " WHERE status = 'waiting'" );
}

/** Days waiting and how urgent: 0 normal, 1 yellow, 2 red. */
function wos_decision_age( $d ) {
	$days  = max( 0, wos_days_between( substr( (string) $d->created_at, 0, 10 ) ) );
	$level = 0;
	if ( $days > wos_num( 'decision_yellow' ) ) {
		$level = 1;
	}
	if ( $days > wos_num( 'decision_red' ) || ( $d->due_date && $d->due_date < wos_today() ) ) {
		$level = 2;
	}
	return array( $days, $level );
}

/** The answer in words, for the log. */
function wos_decision_answer_text( $d ) {
	if ( 'withdrawn' === $d->status ) {
		return 'Withdrawn' . ( $d->answer ? ': ' . $d->answer : '' );
	}
	$parts = array();
	if ( $d->answer_option >= 0 && isset( $d->options[ $d->answer_option ]['label'] ) ) {
		$parts[] = $d->options[ $d->answer_option ]['label'];
	}
	if ( $d->answer ) {
		$parts[] = $d->answer;
	}
	return implode( ' · ', $parts );
}

/** Options typed one per line as "Label | what happens". */
function wos_decision_parse_options( $text ) {
	$out = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		$bits  = array_map( 'trim', explode( '|', $line, 2 ) );
		$out[] = array(
			'label'  => sanitize_text_field( $bits[0] ),
			'result' => isset( $bits[1] ) ? sanitize_text_field( $bits[1] ) : '',
		);
	}
	return array_slice( $out, 0, 6 );
}

/** Create a decision. Returns its id or a WP_Error. */
function wos_decision_create( $data ) {
	global $wpdb;
	$question = isset( $data['question'] ) ? sanitize_text_field( $data['question'] ) : '';
	if ( '' === $question ) {
		return new WP_Error( 'wos_question', 'A decision needs a question.' );
	}
	$options = array();
	foreach ( isset( $data['options'] ) && is_array( $data['options'] ) ? $data['options'] : array() as $o ) {
		$label = sanitize_text_field( is_array( $o ) && isset( $o['label'] ) ? $o['label'] : (string) $o );
		if ( '' !== $label ) {
			$options[] = array(
				'label'  => $label,
				'result' => is_array( $o ) && isset( $o['result'] ) ? sanitize_text_field( $o['result'] ) : '',
			);
		}
	}
	$rec = isset( $data['recommended'] ) ? (int) $data['recommended'] : -1;
	if ( $rec < 0 || $rec >= count( $options ) ) {
		$rec = -1;
	}
	$due  = isset( $data['due_date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $data['due_date'] ) ? $data['due_date'] : null;
	$user = wp_get_current_user();
	$wpdb->insert( wos_table( 'decisions' ), array(
		'area'        => isset( $data['area'] ) ? sanitize_text_field( $data['area'] ) : '',
		'question'    => $question,
		'context'     => isset( $data['context'] ) ? sanitize_textarea_field( $data['context'] ) : '',
		'options'     => wp_json_encode( array_slice( $options, 0, 6 ) ),
		'recommended' => $rec,
		'due_date'    => $due,
		'link'        => isset( $data['link'] ) ? esc_url_raw( $data['link'] ) : '',
		'status'      => 'waiting',
		'added_by'    => isset( $data['added_by'] ) && '' !== $data['added_by'] ? sanitize_text_field( $data['added_by'] ) : $user->display_name,
		'created_at'  => isset( $data['created_at'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $data['created_at'] ) ? $data['created_at'] : wos_now(),
		'updated_at'  => wos_now(),
	) );
	return (int) $wpdb->insert_id;
}

function wos_decision_update( $id, $fields ) {
	global $wpdb;
	$fields['updated_at'] = wos_now();
	return $wpdb->update( wos_table( 'decisions' ), $fields, array( 'id' => (int) $id ) );
}

/* Form actions. Each one checks the capability and a nonce, then returns to /app/decisions. */

function wos_decision_back( $message, $type = 'success', $tab = '' ) {
	wos_flash( $message, $type );
	wp_safe_redirect( wos_app_url( 'decisions', $tab ? array( 'tab' => $tab ) : array() ) );
	exit;
}

add_action( 'admin_post_wos_decision_add', function () {
	wos_require_cap();
	check_admin_referer( 'wos_decision_add' );
	$p  = wp_unslash( $_POST ); // phpcs:ignore -- each field is cleaned in wos_decision_create().
	$id = wos_decision_create( array(
		'area'        => isset( $p['area'] ) ? $p['area'] : '',
		'question'    => isset( $p['question'] ) ? $p['question'] : '',
		'context'     => isset( $p['context'] ) ? $p['context'] : '',
		'options'     => wos_decision_parse_options( isset( $p['options'] ) ? $p['options'] : '' ),
		'recommended' => isset( $p['recommended'] ) && '' !== $p['recommended'] ? (int) $p['recommended'] - 1 : -1,
		'due_date'    => isset( $p['due_date'] ) ? $p['due_date'] : '',
		'link'        => isset( $p['link'] ) ? $p['link'] : '',
	) );
	if ( is_wp_error( $id ) ) {
		wos_decision_back( $id->get_error_message(), 'error' );
	}
	wos_decision_back( 'Added to the queue.' );
} );

add_action( 'admin_post_wos_decision_decide', function () {
	wos_require_cap();
	$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	check_admin_referer( 'wos_decision_' . $id );
	$d = wos_decision_get( $id );
	if ( ! $d || 'decided' === $d->status ) {
		wos_decision_back( 'That decision is not waiting any more.', 'warning' );
	}
	$opt    = isset( $_POST['option'] ) && '' !== $_POST['option'] ? (int) $_POST['option'] : -1;
	$answer = isset( $_POST['answer'] ) ? sanitize_textarea_field( wp_unslash( $_POST['answer'] ) ) : '';
	if ( $opt >= count( $d->options ) ) {
		$opt = -1;
	}
	if ( $opt < 0 && '' === $answer ) {
		wos_decision_back( 'Pick an option or write your answer.', 'warning' );
	}
	wos_decision_update( $id, array(
		'status'        => 'decided',
		'answer_option' => $opt,
		'answer'        => $answer,
		'decided_by'    => wp_get_current_user()->display_name,
		'decided_at'    => wos_now(),
		'aside_until'   => null,
	) );
	wos_decision_back( 'Decided. It is in the decision log.' );
} );

add_action( 'admin_post_wos_decision_aside', function () {
	wos_require_cap();
	$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	check_admin_referer( 'wos_decision_' . $id );
	$until = isset( $_POST['until'] ) ? sanitize_text_field( wp_unslash( $_POST['until'] ) ) : '';
	$max   = wp_date( 'Y-m-d', strtotime( wos_today() . ' +' . (int) wos_num( 'aside_max' ) . ' days' ) );
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $until ) || $until <= wos_today() ) {
		wos_decision_back( 'Pick a date after today.', 'warning' );
	}
	if ( $until > $max ) {
		$until = $max;
	}
	wos_decision_update( $id, array(
		'status'      => 'aside',
		'aside_until' => $until,
	) );
	wos_decision_back( 'Set aside. It comes back to the queue on ' . $until . '.' );
} );

add_action( 'admin_post_wos_decision_withdraw', function () {
	wos_require_cap();
	$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	check_admin_referer( 'wos_decision_' . $id );
	wos_decision_update( $id, array(
		'status'     => 'withdrawn',
		'decided_by' => wp_get_current_user()->display_name,
		'decided_at' => wos_now(),
	) );
	wos_decision_back( 'Withdrawn. It is in the log as withdrawn.', 'success', 'log' );
} );

/*
 * REST, for team members who add decisions from their own tools.
 * Needs a signed-in user with WIZAD OS access (an application password works).
 */
add_action( 'rest_api_init', function () {
	register_rest_route( 'wos/v1', '/decisions', array(
		array(
			'methods'             => 'GET',
			'permission_callback' => function () {
				return current_user_can( WOS_CAP );
			},
			'callback'            => function () {
				return rest_ensure_response( array(
					'waiting' => wos_decisions_waiting(),
					'aside'   => wos_decisions_aside(),
				) );
			},
		),
		array(
			'methods'             => 'POST',
			'permission_callback' => function () {
				return current_user_can( WOS_CAP );
			},
			'callback'            => function ( WP_REST_Request $req ) {
				$id = wos_decision_create( $req->get_json_params() ? $req->get_json_params() : $req->get_params() );
				if ( is_wp_error( $id ) ) {
					$id->add_data( array( 'status' => 400 ) );
					return $id;
				}
				return rest_ensure_response( wos_decision_get( $id ) );
			},
		),
	) );
} );
