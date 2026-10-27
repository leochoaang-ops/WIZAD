<?php
/**
 * The WOS app at /app: one screen per job, outside wp-admin.
 *
 * Signed-out visitors are sent to sign in (security.php); signed-in users
 * without WIZAD OS access get a 403. Screens not built yet show which
 * sprint brings them.
 */
defined( 'ABSPATH' ) || exit;

/** The part of the path after /app/, or null when the request is not for the app. */
function wos_app_path() {
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore -- compared, never printed.
	$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
	$base = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
	if ( $base && 0 === strpos( $path, $base ) ) {
		$path = substr( $path, strlen( $base ) );
	}
	$path = trim( $path, '/' );
	if ( 'app' === $path ) {
		return '';
	}
	if ( 0 === strpos( $path, 'app/' ) ) {
		return trim( substr( $path, 4 ), '/' );
	}
	return null;
}

function wos_app_url( $screen = '', $args = array() ) {
	$url = home_url( '/app/' . ( $screen ? $screen . '/' : '' ) );
	return $args ? add_query_arg( $args, $url ) : $url;
}

/** Screens in the menu: slug => array( label, sprint that brings it, what it will do ). */
function wos_app_screens() {
	return array(
		''          => array( 'Today', '', '' ),
		'decisions' => array( 'Decisions', '', '' ),
		'leads'     => array( 'Find clients', 'Sprint A', 'Leads in 5 stages, the Sprint 0 checklist, and where each lead came from.' ),
		'clients'   => array( 'Clients', 'Sprint A', 'One file per lead and client: profile, baseline, sprints, documents, access and decisions. Documents are made from the contract pack.' ),
		'sprints'   => array( 'Sprints', 'Sprint B', 'Activities by status, hours against budget, and expertise hours.' ),
		'time'      => array( 'Time', 'Sprint B', 'Log hours in a few taps, by person, role and category.' ),
		'content'   => array( 'Content', 'Sprint C', 'The content calendar and the marketing funnel, from reach to clients.' ),
		'money'     => array( 'Money', 'Sprint D', 'The 5 money layers, invoices and labels on every number.' ),
		'settings'  => array( 'Settings', '', '' ),
	);
}

add_action( 'template_redirect', 'wos_app_serve', 1 );

function wos_app_serve() {
	$path = wos_app_path();
	if ( null === $path || ! is_user_logged_in() ) {
		return;
	}
	if ( ! current_user_can( WOS_CAP ) ) {
		wp_die( esc_html__( 'You do not have access to WIZAD OS.', 'wizad-os' ), 'WIZAD OS', array( 'response' => 403 ) );
	}
	if ( current_user_can( 'manage_options' ) && 'off' === wos_two_factor_status() ) {
		wos_flash( 'Turn on two-step sign-in (authenticator app) before using WIZAD OS.', 'warning' );
		wp_safe_redirect( admin_url( 'profile.php#two-factor-options' ) );
		exit;
	}

	$screens = wos_app_screens();
	$screen  = isset( $screens[ $path ] ) ? $path : null;
	status_header( null === $screen ? 404 : 200 );
	nocache_headers();
	wos_app_head( null === $screen ? 'Not found' : $screens[ $screen ][0], $screen );

	if ( null === $screen ) {
		echo '<div class="wos-head"><h1>Not found</h1></div><p>This screen does not exist. <a href="' . esc_url( wos_app_url() ) . '">Back to Today</a></p>';
	} elseif ( '' === $screen ) {
		wos_app_today();
	} elseif ( 'decisions' === $screen ) {
		wos_app_decisions();
	} elseif ( 'settings' === $screen ) {
		wos_app_settings();
	} else {
		wos_app_coming( $screens[ $screen ] );
	}

	wos_app_foot();
	exit;
}

function wos_app_head( $title, $current ) {
	$waiting = wos_decisions_count();
	?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( $title ); ?> · WOS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@600;700;800&amp;family=Public+Sans:wght@400;600&amp;family=IBM+Plex+Mono:wght@400;500&amp;display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo esc_url( WOS_URL . 'assets/app.css?v=' . WOS_VERSION ); ?>">
</head>
<body class="wos-app">
<div class="wos-shell">
<nav class="wos-nav" aria-label="Main">
	<a class="wos-brand" href="<?php echo esc_url( wos_app_url() ); ?>">
		<svg viewBox="0 0 332 332" aria-hidden="true"><g transform="translate(0 15)"><path fill="#FFFFFF" d="M0 0H160V172H83V302H0Z"/><rect x="172" y="0" width="160" height="172" fill="#C61A85"/><rect x="95" y="184" width="237" height="118" fill="#FFFFFF"/></g></svg>
		<span>WIZAD</span>
	</a>
	<div class="wos-tagline">WOS · OPERATING SYSTEM</div>
	<?php foreach ( wos_app_screens() as $slug => $s ) : ?>
		<a href="<?php echo esc_url( wos_app_url( $slug ) ); ?>" class="<?php echo $slug === $current ? 'is-current' : ''; ?>"<?php echo $slug === $current ? ' aria-current="page"' : ''; ?>>
			<span><?php echo esc_html( $s[0] ); ?></span>
			<?php if ( 'decisions' === $slug && $waiting ) : ?><span class="wos-badge"><?php echo (int) $waiting; ?></span><?php endif; ?>
			<?php if ( $s[1] ) : ?><span class="wos-soon"><?php echo esc_html( $s[1] ); ?></span><?php endif; ?>
		</a>
	<?php endforeach; ?>
	<div class="wos-nav-foot">
		<span><?php echo esc_html( wp_get_current_user()->display_name ); ?></span>
		<a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">Sign out</a>
	</div>
</nav>
<main class="wos-main">
	<?php
	wos_app_flash();
}

function wos_app_foot() {
	?>
</main>
</div>
<script src="<?php echo esc_url( WOS_URL . 'assets/app.js?v=' . WOS_VERSION ); ?>"></script>
</body>
</html>
	<?php
}

function wos_app_flash() {
	$key = 'wos_flash_' . get_current_user_id();
	$f   = get_transient( $key );
	if ( $f ) {
		delete_transient( $key );
		printf( '<div class="wos-flash is-%s" role="status">%s</div>', esc_attr( $f[1] ), esc_html( $f[0] ) );
	}
}

function wos_app_eyebrow_date() {
	return strtoupper( wp_date( 'l, F j, Y' ) );
}

/* ---------- Today (Sprint 0 version: the decision queue; the other blocks arrive in Sprint D) ---------- */

function wos_app_today() {
	$waiting = wos_decisions_waiting();
	$first   = null;
	foreach ( $waiting as $d ) {
		list( $days, $level ) = wos_decision_age( $d );
		if ( ! $first || $level > $first[1] ) {
			$first = array( $d, $level, $days );
		}
	}
	$hour  = (int) current_time( 'G' );
	$greet = $hour < 12 ? 'Good morning' : ( $hour < 18 ? 'Good afternoon' : 'Good evening' );
	$name  = wp_get_current_user()->first_name ? wp_get_current_user()->first_name : wp_get_current_user()->display_name;
	$n     = count( $waiting );
	?>
	<div class="wos-head">
		<div class="wos-eyebrow"><?php echo esc_html( wos_app_eyebrow_date() ); ?></div>
		<h1><?php echo esc_html( $greet . ', ' . $name . '. ' . ( $n ? $n . ( 1 === $n ? ' decision waits' : ' decisions wait' ) . ' for you.' : 'Nothing waits for you.' ) ); ?></h1>
	</div>
	<div class="wos-row">
		<?php if ( $first ) : ?>
			<section class="wos-hero">
				<div class="wos-eyebrow">DO THIS ONE THING NOW</div>
				<h2><?php echo esc_html( $first[0]->question ); ?></h2>
				<p><?php echo esc_html( wos_decision_age_text( $first[0], $first[2] ) ); ?></p>
				<a class="wos-btn is-light" href="<?php echo esc_url( wos_app_url( 'decisions' ) . '#d' . (int) $first[0]->id ); ?>">Decide now</a>
			</section>
		<?php else : ?>
			<section class="wos-hero is-calm">
				<div class="wos-eyebrow">DO THIS ONE THING NOW</div>
				<h2>Nothing is waiting for you.</h2>
				<p>New decisions from the team appear here first.</p>
			</section>
		<?php endif; ?>
		<?php wos_app_decision_stats(); ?>
	</div>
	<section class="wos-card wos-pad">
		<div class="wos-card-head"><h2>Decisions</h2><a href="<?php echo esc_url( wos_app_url( 'decisions' ) ); ?>">Open all</a></div>
		<?php if ( ! $waiting ) : ?>
			<p class="wos-muted">None waiting.</p>
		<?php endif; ?>
		<?php foreach ( $waiting as $d ) : ?>
			<?php list( $days, $level ) = wos_decision_age( $d ); ?>
			<div class="wos-line"><span><?php echo esc_html( $d->question ); ?></span><?php echo wos_app_age_pill( $days, $level ); // phpcs:ignore -- escaped inside. ?></div>
		<?php endforeach; ?>
	</section>
	<p class="wos-note">Today grows in Sprint D: health score, sprints, pipeline, money, content and time join the decision queue.</p>
	<?php
}

function wos_decision_age_text( $d, $days ) {
	$t = 0 === $days ? 'New today' : 'Waiting ' . $days . ( 1 === $days ? ' day' : ' days' );
	if ( $d->due_date ) {
		$t .= ' · due ' . wp_date( 'M j', strtotime( $d->due_date ) );
	}
	return $t;
}

function wos_app_age_pill( $days, $level, $d = null ) {
	$text = 0 === $days ? 'New today' : $days . ( 1 === $days ? ' day' : ' days' );
	if ( $d ) {
		$text = wos_decision_age_text( $d, $days );
	}
	$cls = array( 'is-grey', 'is-amber', 'is-red' );
	return '<span class="wos-pill ' . esc_attr( $cls[ $level ] ) . '">' . esc_html( $text ) . '</span>';
}

function wos_app_decision_stats() {
	global $wpdb;
	$waiting = wos_decisions_waiting();
	$total   = 0;
	foreach ( $waiting as $d ) {
		$total += wos_decision_age( $d )[0];
	}
	$week = (int) $wpdb->get_var( $wpdb->prepare(
		'SELECT COUNT(*) FROM ' . wos_table( 'decisions' ) . " WHERE status = 'decided' AND decided_at >= %s",
		wp_date( 'Y-m-d 00:00:00', strtotime( '-6 days', current_time( 'timestamp' ) ) ) // phpcs:ignore -- local date wanted.
	) );
	?>
	<div class="wos-stats">
		<div class="wos-stat"><span>Waiting for Leo</span><strong><?php echo count( $waiting ); ?></strong></div>
		<div class="wos-stat"><span>Average days waiting</span><strong><?php echo esc_html( $waiting ? number_format( $total / count( $waiting ), 1 ) : '0' ); ?></strong></div>
		<div class="wos-stat is-dark"><span>Decided in the last 7 days</span><strong><?php echo (int) $week; ?></strong></div>
	</div>
	<?php
}

/* ---------- Decisions ---------- */

function wos_app_decisions() {
	$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'waiting'; // phpcs:ignore WordPress.Security.NonceVerification
	$tabs = array(
		'waiting' => 'Waiting',
		'aside'   => 'Set aside',
		'log'     => 'Decision log',
		'add'     => '+ Add',
	);
	if ( ! isset( $tabs[ $tab ] ) ) {
		$tab = 'waiting';
	}
	?>
	<div class="wos-head wos-head-row">
		<div>
			<div class="wos-eyebrow">ONLY LEO CAN DECIDE THESE</div>
			<h1>Decisions</h1>
		</div>
		<div class="wos-tabs">
			<?php foreach ( $tabs as $k => $label ) : ?>
				<a class="<?php echo $k === $tab ? 'is-on' : ''; ?>" href="<?php echo esc_url( wos_app_url( 'decisions', 'waiting' === $k ? array() : array( 'tab' => $k ) ) ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
	wos_app_decision_stats();
	switch ( $tab ) {
		case 'aside':
			wos_app_decisions_aside();
			break;
		case 'log':
			wos_app_decisions_log();
			break;
		case 'add':
			wos_app_decisions_add();
			break;
		default:
			wos_app_decisions_waiting();
	}
}

function wos_app_decisions_waiting() {
	$waiting = wos_decisions_waiting();
	if ( ! $waiting ) {
		echo '<section class="wos-ok-box">Nothing is waiting for you. Good.</section>';
		return;
	}
	$max = wp_date( 'Y-m-d', strtotime( wos_today() . ' +' . (int) wos_num( 'aside_max' ) . ' days' ) );
	$min = wp_date( 'Y-m-d', strtotime( wos_today() . ' +1 day' ) );
	foreach ( $waiting as $d ) :
		list( $days, $level ) = wos_decision_age( $d );
		$action = admin_url( 'admin-post.php' );
		?>
		<article class="wos-card wos-decision" id="d<?php echo (int) $d->id; ?>">
			<div class="wos-decision-top">
				<span class="wos-muted"><?php echo esc_html( trim( $d->area . ( $d->added_by ? ' · added by ' . $d->added_by : '' ), ' ·' ) ); ?></span>
				<?php echo wos_app_age_pill( $days, $level, $d ); // phpcs:ignore -- escaped inside. ?>
			</div>
			<form class="wos-decision-body" method="post" action="<?php echo esc_url( $action ); ?>">
				<input type="hidden" name="action" value="wos_decision_decide">
				<input type="hidden" name="id" value="<?php echo (int) $d->id; ?>">
				<?php wp_nonce_field( 'wos_decision_' . $d->id ); ?>
				<h2><?php echo esc_html( $d->question ); ?></h2>
				<?php if ( $d->context ) : ?><p class="wos-muted"><?php echo nl2br( esc_html( $d->context ) ); ?></p><?php endif; ?>
				<?php if ( $d->options ) : ?>
					<fieldset class="wos-options">
						<legend class="screen-reader-text">Options</legend>
						<?php foreach ( $d->options as $i => $o ) : ?>
							<label class="wos-option">
								<input type="radio" name="option" value="<?php echo (int) $i; ?>" <?php checked( $i, (int) $d->recommended ); ?>>
								<span><strong><?php echo esc_html( $o['label'] ); ?></strong><?php if ( $o['result'] ) : ?><span><?php echo esc_html( $o['result'] ); ?></span><?php endif; ?><?php if ( (int) $d->recommended === $i ) : ?><em>Recommended</em><?php endif; ?></span>
							</label>
						<?php endforeach; ?>
					</fieldset>
				<?php endif; ?>
				<details class="wos-own"<?php echo $d->options ? '' : ' open'; ?>>
					<summary>Write my own answer</summary>
					<label class="wos-field"><span><?php echo $d->options ? 'Your answer (used with or instead of the option)' : 'Your answer'; ?></span><textarea name="answer" rows="3"></textarea></label>
				</details>
				<div class="wos-actions">
					<button class="wos-btn" type="submit">Decide</button>
					<?php if ( $d->link ) : ?><a class="wos-link" href="<?php echo esc_url( $d->link ); ?>" target="_blank" rel="noopener">Open the work</a><?php endif; ?>
				</div>
			</form>
			<div class="wos-decision-more">
				<form method="post" action="<?php echo esc_url( $action ); ?>" class="wos-inline">
					<input type="hidden" name="action" value="wos_decision_aside">
					<input type="hidden" name="id" value="<?php echo (int) $d->id; ?>">
					<?php wp_nonce_field( 'wos_decision_' . $d->id ); ?>
					<label>Set aside until <input type="date" name="until" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" value="<?php echo esc_attr( wp_date( 'Y-m-d', strtotime( wos_today() . ' +7 days' ) ) ); ?>" required></label>
					<button class="wos-btn is-ghost" type="submit">Set aside</button>
				</form>
				<form method="post" action="<?php echo esc_url( $action ); ?>" class="wos-inline" onsubmit="return confirm('Withdraw this decision? It stays in the log as withdrawn.');">
					<input type="hidden" name="action" value="wos_decision_withdraw">
					<input type="hidden" name="id" value="<?php echo (int) $d->id; ?>">
					<?php wp_nonce_field( 'wos_decision_' . $d->id ); ?>
					<button class="wos-btn is-text" type="submit">Withdraw</button>
				</form>
			</div>
		</article>
		<?php
	endforeach;
}

function wos_app_decisions_aside() {
	$rows = wos_decisions_aside();
	echo '<section class="wos-card wos-pad">';
	if ( ! $rows ) {
		echo '<p class="wos-muted">Nothing is set aside.</p>';
	}
	foreach ( $rows as $d ) {
		printf(
			'<div class="wos-line"><span><strong>%s</strong><br><span class="wos-muted">%s</span></span><span class="wos-pill is-grey">Back on %s</span></div>',
			esc_html( $d->question ),
			esc_html( $d->area ),
			esc_html( wp_date( 'M j', strtotime( $d->aside_until ) ) )
		);
	}
	echo '</section>';
}

function wos_app_decisions_log() {
	$rows = wos_decisions_log();
	echo '<section class="wos-card wos-pad">';
	if ( ! $rows ) {
		echo '<p class="wos-muted">No decisions yet.</p>';
	}
	foreach ( $rows as $d ) {
		printf(
			'<div class="wos-line"><span><strong>%s</strong><br><span class="wos-muted">%s</span></span><span class="wos-mono wos-muted">%s</span></div>',
			esc_html( $d->question ),
			esc_html( wos_decision_answer_text( $d ) . ( $d->decided_by ? ' · ' . $d->decided_by : '' ) ),
			esc_html( substr( (string) $d->decided_at, 0, 10 ) )
		);
	}
	echo '</section>';
}

function wos_app_decisions_add() {
	?>
	<section class="wos-card wos-pad">
		<h2>Add a decision for Leo</h2>
		<p class="wos-muted">Only for a choice that only Leo can make. Give options and say which one you recommend.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wos-form">
			<input type="hidden" name="action" value="wos_decision_add">
			<?php wp_nonce_field( 'wos_decision_add' ); ?>
			<label class="wos-field"><span>Question</span><input type="text" name="question" required maxlength="255" placeholder="Which retainer plan and price do we offer PHG?"></label>
			<label class="wos-field"><span>Area · owner</span><input type="text" name="area" maxlength="120" placeholder="Clients · Tau · Pivot Homes Group"></label>
			<label class="wos-field"><span>Context</span><textarea name="context" rows="3" placeholder="What Leo needs to know to choose."></textarea></label>
			<label class="wos-field"><span>Options, one per line: label | what happens</span><textarea name="options" rows="4" placeholder="Growth plan: $3,300 for 3 months | Matches the financial model&#10;Talk to PHG first | Decision moves to after the meeting"></textarea></label>
			<div class="wos-field-row">
				<label class="wos-field"><span>Recommended option (number)</span><input type="number" name="recommended" min="1" max="6" value="1"></label>
				<label class="wos-field"><span>Due date</span><input type="date" name="due_date"></label>
			</div>
			<label class="wos-field"><span>Link to the work (optional)</span><input type="url" name="link" placeholder="https://"></label>
			<div class="wos-actions"><button class="wos-btn" type="submit">Add to the queue</button></div>
		</form>
	</section>
	<?php
}

/* ---------- Settings ---------- */

function wos_app_settings() {
	$fields   = wos_number_fields();
	$groups   = wos_number_groups();
	$roles    = wos_roles();
	$versions = wos_number_versions();
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="wos_numbers_save">
	<?php wp_nonce_field( 'wos_numbers_save' ); ?>
	<div class="wos-head wos-head-row">
		<div>
			<div class="wos-eyebrow">EVERY NUMBER IS AN INPUT</div>
			<h1>Settings</h1>
		</div>
		<div class="wos-save">
			<label>Effective from <input type="date" name="from" value="<?php echo esc_attr( wos_today() ); ?>" required></label>
			<button class="wos-btn" type="submit">Save</button>
		</div>
	</div>
	<div class="wos-row wos-top">
		<div class="wos-col-main">
			<?php foreach ( $groups as $g => $glabel ) : ?>
				<section class="wos-card wos-pad">
					<h2><?php echo esc_html( $glabel ); ?></h2>
					<?php if ( 'people' === $g ) : ?>
						<div class="wos-table-wrap">
						<table class="wos-table">
							<thead><tr><th>Role</th><th>Type</th><th>Active</th><th class="num">Cost rate $/h</th></tr></thead>
							<tbody>
							<?php foreach ( $roles as $slug => $r ) : ?>
								<tr>
									<td><?php echo esc_html( $r['name'] ); ?></td>
									<td class="wos-muted"><?php echo esc_html( $r['type'] ); ?></td>
									<td><input type="hidden" name="role[<?php echo esc_attr( $slug ); ?>][active]" value="0"><input type="checkbox" aria-label="<?php echo esc_attr( $r['name'] . ' active' ); ?>" name="role[<?php echo esc_attr( $slug ); ?>][active]" value="1" <?php checked( 'active', $r['status'] ); ?>></td>
									<td class="num"><input class="wos-num" type="number" step="0.01" min="0" name="role[<?php echo esc_attr( $slug ); ?>][rate]" value="<?php echo esc_attr( wos_num( 'rate_' . $slug ) ); ?>" <?php echo 'lead_consultant' === $slug ? 'data-live="rate"' : ''; ?> aria-label="<?php echo esc_attr( $r['name'] . ' cost rate' ); ?>"></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
						</div>
						<p class="wos-muted wos-small">Ready roles exist but stay off the time form until they are active. 0 means the rate is not set yet.</p>
					<?php endif; ?>
					<?php foreach ( $fields as $key => $f ) : ?>
						<?php
						if ( $f[2] !== $g ) {
							continue;
						}
						?>
						<label class="wos-setting">
							<span><?php echo esc_html( $f[0] ); ?><?php if ( $f[4] ) : ?><small><?php echo esc_html( $f[4] ); ?></small><?php endif; ?></span>
							<input class="wos-num" type="number" step="any" name="n[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( wos_num( $key ) ); ?>" data-live="<?php echo esc_attr( $key ); ?>">
							<em><?php echo esc_html( $f[1] ); ?></em>
						</label>
					<?php endforeach; ?>
				</section>
			<?php endforeach; ?>
		</div>
		<div class="wos-col-side">
			<section class="wos-dark wos-pad" id="wos-live">
				<div class="wos-eyebrow">LIVE CHECK · A 20-HOUR SPRINT AT $2,400</div>
				<div class="wos-kv"><span>Cost per hour (Lead Consultant)</span><strong data-out="hour">–</strong></div>
				<div class="wos-kv"><span>Allocated cost</span><strong data-out="alloc">–</strong></div>
				<div class="wos-kv"><span>Margin</span><strong data-out="margin">–</strong></div>
				<div class="wos-live-note" data-out="note"></div>
				<div class="wos-kv is-gap"><span>Invoice with GST and PST</span><strong data-out="total">–</strong></div>
			</section>
			<section class="wos-card wos-pad">
				<h2>History</h2>
				<p class="wos-muted wos-small">Each save applies from its date. Records keep the numbers that applied on their own date.</p>
				<?php foreach ( array_reverse( $versions ) as $v ) : ?>
					<div class="wos-line"><span>From <strong><?php echo esc_html( $v['from'] ); ?></strong></span><span class="wos-muted"><?php echo esc_html( $v['by'] . ' · ' . substr( $v['at'], 0, 16 ) ); ?></span></div>
				<?php endforeach; ?>
			</section>
		</div>
	</div>
	</form>
	<?php
}

/* ---------- Screens still to come ---------- */

function wos_app_coming( $screen ) {
	?>
	<div class="wos-head">
		<div class="wos-eyebrow"><?php echo esc_html( strtoupper( 'Coming in ' . $screen[1] ) ); ?></div>
		<h1><?php echo esc_html( $screen[0] ); ?></h1>
	</div>
	<section class="wos-card wos-pad">
		<p><?php echo esc_html( $screen[2] ); ?></p>
		<p class="wos-muted">This screen is designed and approved; it is built in <?php echo esc_html( $screen[1] ); ?>. Until then the older screens stay in <a href="<?php echo esc_url( wos_url( 'wizad-os' ) ); ?>">WordPress admin</a>.</p>
	</section>
	<?php
}
