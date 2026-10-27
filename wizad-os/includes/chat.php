<?php
/**
 * Ask Claude: a chat inside WOS that reads WOS data.
 *
 * WordPress plugins ship as a zip without Composer, so this calls the
 * Messages API over HTTP with wp_remote_post. The API key is stored in an
 * option that is never shown again after saving (or in the WOS_CLAUDE_KEY
 * constant in wp-config.php, which wins).
 */
defined( 'ABSPATH' ) || exit;

define( 'WOS_CLAUDE_MODEL', 'claude-opus-5-5' );

function wos_claude_key() {
	if ( defined( 'WOS_CLAUDE_KEY' ) && WOS_CLAUDE_KEY ) {
		return WOS_CLAUDE_KEY;
	}
	return (string) get_option( 'wos_claude_key', '' );
}

/** The endpoint, overridable for local tests only. */
function wos_claude_url() {
	return defined( 'WOS_CLAUDE_URL' ) ? WOS_CLAUDE_URL : 'https://api.anthropic.com/v1/messages';
}

/**
 * One Messages API call. Returns array( text, in, out ) or a WP_Error.
 * Chat answers short questions, so effort is low; a refusal falls back to
 * another model on the server (fallbacks: default).
 */
function wos_claude_call( $system, $messages, $scope = '' ) {
	$key = wos_claude_key();
	if ( ! $key ) {
		return new WP_Error( 'wos_no_key', 'No Claude API key yet. Leo adds it in Settings.' );
	}
	$res = wp_remote_post( wos_claude_url(), array(
		'timeout' => 120,
		'headers' => array(
			'content-type'      => 'application/json',
			'x-api-key'         => $key,
			'anthropic-version' => '2026-10-21',
			'anthropic-beta'    => 'server-side-fallback-2026-07-01',
		),
		'body'    => wp_json_encode( array(
			'model'         => WOS_CLAUDE_MODEL,
			'max_tokens'    => 16000,
			'output_config' => array( 'effort' => 'low' ),
			'fallbacks'     => 'default',
			'system'        => $system,
			'messages'      => $messages,
		) ),
	) );
	if ( is_wp_error( $res ) ) {
		return new WP_Error( 'wos_http', 'Could not reach Claude (' . $res->get_error_message() . ').' );
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	$body = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( 200 !== $code ) {
		$msg = isset( $body['error']['message'] ) ? $body['error']['message'] : 'HTTP ' . $code;
		if ( 401 === $code ) {
			$msg = 'The API key was refused. Check it in Settings.';
		} elseif ( 429 === $code || 529 === $code ) {
			$msg = 'Claude is busy. Try again in a minute.';
		}
		return new WP_Error( 'wos_api', $msg );
	}
	if ( isset( $body['stop_reason'] ) && 'refusal' === $body['stop_reason'] ) {
		return new WP_Error( 'wos_refusal', 'Claude declined to answer this one.' );
	}
	$text = '';
	foreach ( isset( $body['content'] ) && is_array( $body['content'] ) ? $body['content'] : array() as $block ) {
		if ( isset( $block['type'] ) && 'text' === $block['type'] ) {
			$text .= $block['text'];
		}
	}
	$in  = isset( $body['usage']['input_tokens'] ) ? (int) $body['usage']['input_tokens'] : 0;
	$out = isset( $body['usage']['output_tokens'] ) ? (int) $body['usage']['output_tokens'] : 0;
	if ( $scope && 0 !== strpos( $scope, 'chat:' ) ) {
		// Calls outside the chat (drafts) still count in the monthly usage.
		wos_chat_store( 'usage:' . $scope, 'assistant', '', $in, $out );
	}
	return array(
		'text' => trim( $text ),
		'in'   => $in,
		'out'  => $out,
	);
}

/* ---------- Chat history ---------- */

function wos_chat_store( $scope, $role, $content, $in = 0, $out = 0 ) {
	global $wpdb;
	$wpdb->insert( wos_table( 'chats' ), array(
		'scope'      => substr( $scope, 0, 40 ),
		'role'       => $role,
		'content'    => $content,
		'user_name'  => 'user' === $role ? wp_get_current_user()->display_name : 'Claude',
		'tokens_in'  => $in,
		'tokens_out' => $out,
		'created_at' => wos_now(),
	) );
}

function wos_chat_history( $scope, $limit = 30 ) {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . wos_table( 'chats' ) . ' WHERE scope = %s ORDER BY id DESC LIMIT %d', $scope, $limit ) );
	return array_reverse( $rows );
}

/** Tokens used this month and the cost they come to at the published prices for the model. */
function wos_chat_usage_month() {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT SUM(tokens_in) AS i, SUM(tokens_out) AS o, COUNT(CASE WHEN role = %s THEN 1 END) AS q FROM ' . wos_table( 'chats' ) . ' WHERE created_at >= %s', 'user', current_time( 'Y-m-01 00:00:00' ) ) );
	$in  = $row ? (int) $row->i : 0;
	$out = $row ? (int) $row->o : 0;
	return array(
		'questions' => $row ? (int) $row->q : 0,
		'in'        => $in,
		'out'       => $out,
		'usd'       => $in * 4 / 1000000 + $out * 20 / 1000000,
	);
}

/* ---------- What Claude can see ---------- */

function wos_chat_decision_text( $d, $full = true ) {
	$t = '- Decision #' . $d->id . ' [' . $d->status . '] ' . $d->question;
	if ( $d->area ) {
		$t .= ' (area: ' . $d->area . ')';
	}
	if ( ! $full ) {
		return $t . ( 'decided' === $d->status ? ' -> answer: ' . wos_decision_answer_text( $d ) . ' (' . substr( (string) $d->decided_at, 0, 10 ) . ')' : '' ) . "\n";
	}
	$t .= "\n  Added " . substr( (string) $d->created_at, 0, 10 ) . ' by ' . $d->added_by . ( $d->due_date ? ', due ' . $d->due_date : '' );
	foreach ( array( 'context' => 'Context', 'why_now' => 'Why now', 'numbers' => 'Numbers', 'read_first' => 'Read first' ) as $k => $label ) {
		if ( ! empty( $d->$k ) ) {
			$t .= "\n  " . $label . ': ' . str_replace( "\n", ' / ', $d->$k );
		}
	}
	foreach ( $d->options as $i => $o ) {
		$t .= "\n  Option " . ( $i + 1 ) . ( (int) $d->recommended === $i ? ' (recommended)' : '' ) . ': ' . $o['label'];
		foreach ( array( 'result' => 'gain', 'risk' => 'risk', 'margin' => 'margin' ) as $k => $label ) {
			if ( ! empty( $o[ $k ] ) ) {
				$t .= '; ' . $label . ': ' . $o[ $k ];
			}
		}
	}
	if ( 'decided' === $d->status ) {
		$t .= "\n  Answer: " . wos_decision_answer_text( $d ) . ' by ' . $d->decided_by;
	}
	return $t . "\n";
}

function wos_chat_lead_text( $l, $full = true ) {
	$stages = wos_lead_stages();
	list( $done, $all ) = wos_lead_progress( $l );
	$t = '- Lead #' . $l->id . ' ' . $l->name . ': stage ' . ( isset( $stages[ $l->stage ] ) ? $stages[ $l->stage ][0] : $l->stage ) . ', level ' . ( $l->level ? $l->level : '-' ) . ', checklist ' . $done . '/' . $all . ', Sprint 0 hours ' . (float) $l->s0_hours . ', next: ' . $l->next_step . ( $l->next_date ? ' (' . $l->next_date . ')' : '' );
	if ( ! $full ) {
		return $t . "\n";
	}
	$t .= "\n  Contact: " . $l->contact_name . ' · source: ' . $l->source . ' · introducer: ' . $l->introducer . ' · owner: ' . $l->owner . ' · value ' . (float) $l->value . ' at ' . (int) $l->win . '%';
	if ( $l->scores ) {
		$parts = array();
		foreach ( wos_lead_score_areas() as $k => $label ) {
			$parts[] = $label . ' ' . ( isset( $l->scores[ $k ] ) ? (int) $l->scores[ $k ] : 0 );
		}
		$t .= "\n  Scores: " . implode( ', ', $parts ) . ' (total ' . wos_lead_total( $l->scores ) . '/40)';
	}
	if ( $l->answers ) {
		$t .= "\n  Assessment answers: " . implode( ' / ', $l->answers );
	}
	foreach ( wos_lead_checks() as $k => $c ) {
		$t .= "\n  [" . ( empty( $l->checks[ $k ] ) ? ' ' : 'x' ) . '] ' . $c[0];
	}
	if ( ! empty( $l->prelim['see'] ) ) {
		$t .= "\n  Preliminary proposal (" . $l->prelim['status'] . '): ' . $l->prelim['see'] . ' First sprint: ' . $l->prelim['sprint'] . ' Effort ' . $l->prelim['low'] . '-' . $l->prelim['high'] . ' h.';
	}
	if ( ! empty( $l->intake['values'] ) ) {
		$f = wos_lead_intake_fields();
		foreach ( $l->intake['values'] as $k => $v ) {
			if ( isset( $f[ $k ] ) && '' !== $v['v'] ) {
				$t .= "\n  Intake · " . $f[ $k ][0] . ': ' . $v['v'] . ' (' . $v['src'] . ')';
			}
		}
	}
	if ( ! empty( $l->summary['status'] ) ) {
		$t .= "\n  Discovery summary (" . $l->summary['status'] . '):';
		foreach ( wos_lead_summary_parts() as $k => $label ) {
			if ( ! empty( $l->summary[ $k ] ) ) {
				$t .= ' ' . $label . ': ' . $l->summary[ $k ] . '.';
			}
		}
	}
	if ( $l->notes ) {
		$t .= "\n  Notes: " . str_replace( "\n", ' / ', $l->notes );
	}
	foreach ( array_slice( $l->log, -12 ) as $e ) {
		$t .= "\n  Log " . substr( $e['at'], 0, 10 ) . ' ' . $e['by'] . ': ' . $e['text'];
	}
	return $t . "\n";
}

/** Everything Claude may read for one chat. The scope's own record comes in full; the rest as one line each. */
function wos_chat_context( $scope ) {
	$focus_d = 0 === strpos( $scope, 'decision:' ) ? (int) substr( $scope, 9 ) : 0;
	$focus_l = 0 === strpos( $scope, 'lead:' ) ? (int) substr( $scope, 5 ) : 0;
	if ( $focus_d ) {
		$d = wos_decision_get( $focus_d );
		if ( $d && $d->lead_id ) {
			$focus_l = (int) $d->lead_id;
		}
	}
	$c  = 'Today is ' . wos_today() . ". WIZAD OS data follows.\n\n";
	$c .= "SETTINGS (current values)\n";
	foreach ( wos_number_fields() as $k => $f ) {
		$c .= '- ' . $f[0] . ': ' . wos_num( $k ) . ' ' . $f[1] . "\n";
	}
	foreach ( wos_roles() as $slug => $r ) {
		if ( 'active' === $r['status'] ) {
			$c .= '- Cost rate ' . $r['name'] . ': ' . wos_num( 'rate_' . $slug ) . " \$/h\n";
		}
	}
	$c .= "\nDECISIONS WAITING FOR LEO\n";
	foreach ( wos_decisions_waiting() as $d ) {
		$c .= wos_chat_decision_text( $d, $d->id === $focus_d || ! $focus_d );
	}
	if ( $focus_d ) {
		$d = wos_decision_get( $focus_d );
		if ( $d && 'waiting' !== $d->status ) {
			$c .= wos_chat_decision_text( $d );
		}
	}
	$c .= "\nRECENT DECISION LOG\n";
	foreach ( wos_decisions_log( 20 ) as $d ) {
		$c .= wos_chat_decision_text( $d, false );
	}
	$c .= "\nLEADS\n";
	foreach ( wos_leads_all() as $l ) {
		$c .= wos_chat_lead_text( $l, (int) $l->id === $focus_l );
	}
	return $c;
}

function wos_chat_system( $scope ) {
	$about = 'today' === $scope ? 'Leo is on the Today screen.' : ( 0 === strpos( $scope, 'decision:' ) ? 'Leo is looking at decision #' . (int) substr( $scope, 9 ) . ' and wants to understand it before choosing.' : 'Leo is looking at lead #' . (int) substr( $scope, 5 ) . '.' );
	return "You are Claude inside WIZAD OS (WOS), the internal system of WIZAD Consulting, a small digital consultancy in Canada run by Leo (Lead Consultant). {$about}\n"
		. "Answer from the WOS data below. Say which record an answer comes from (for example: Source: lead #3, intake). If the data does not hold the answer, say so plainly and say what would need to be added; never invent numbers, names or dates.\n"
		. "When Leo is choosing, lay out what each option means for him and say which you would pick and why, in a few sentences. Keep answers short and in plain words. Reply in the language Leo writes in.\n"
		. "You cannot change anything in WOS from this chat; tell Leo which screen to use if he wants something changed.\n\n"
		. wos_chat_context( $scope );
}

/* ---------- REST: the chat panel posts here ---------- */

function wos_chat_scope_ok( $scope ) {
	return 'today' === $scope || preg_match( '/^(decision|lead):\d+$/', $scope );
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'wos/v1', '/chat', array(
		array(
			'methods'             => 'POST',
			'permission_callback' => function () {
				return current_user_can( WOS_CAP );
			},
			'callback'            => function ( WP_REST_Request $req ) {
				$scope   = (string) $req->get_param( 'scope' );
				$message = trim( sanitize_textarea_field( (string) $req->get_param( 'message' ) ) );
				if ( ! wos_chat_scope_ok( $scope ) || '' === $message ) {
					return new WP_Error( 'wos_chat', 'Write a question first.', array( 'status' => 400 ) );
				}
				$messages = array();
				foreach ( wos_chat_history( 'chat:' . $scope, 20 ) as $m ) {
					if ( '' !== (string) $m->content ) {
						$messages[] = array( 'role' => $m->role, 'content' => $m->content );
					}
				}
				// The API needs turns to alternate, starting with the user.
				while ( $messages && 'user' !== $messages[0]['role'] ) {
					array_shift( $messages );
				}
				if ( $messages && 'user' === end( $messages )['role'] ) {
					array_pop( $messages );
				}
				$messages[] = array( 'role' => 'user', 'content' => $message );
				wos_chat_store( 'chat:' . $scope, 'user', $message );
				$r = wos_claude_call( wos_chat_system( $scope ), $messages, 'chat:' . $scope );
				if ( is_wp_error( $r ) ) {
					return new WP_Error( 'wos_chat', $r->get_error_message(), array( 'status' => 502 ) );
				}
				wos_chat_store( 'chat:' . $scope, 'assistant', $r['text'], $r['in'], $r['out'] );
				return rest_ensure_response( array( 'reply' => $r['text'] ) );
			},
		),
	) );
} );

/* ---------- Settings: the key ---------- */

add_action( 'admin_post_wos_claude_key', function () {
	wos_require_cap();
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Only an administrator can change the API key.', 403 );
	}
	check_admin_referer( 'wos_claude_key' );
	if ( ! empty( $_POST['remove'] ) ) {
		delete_option( 'wos_claude_key' );
		wos_flash( 'The Claude API key was removed. Ask Claude is off.' );
	} else {
		$key = isset( $_POST['key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['key'] ) ) ) : '';
		if ( ! preg_match( '/^sk-ant-[A-Za-z0-9_\-]{20,}$/', $key ) ) {
			wos_flash( 'That does not look like an Anthropic API key (it starts with sk-ant-). Nothing was saved.', 'warning' );
		} else {
			update_option( 'wos_claude_key', $key, false );
			wos_flash( 'Saved. Ask Claude is on.' );
		}
	}
	wp_safe_redirect( wos_app_url( 'settings' ) . '#claude' );
	exit;
} );
