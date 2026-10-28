<?php
/**
 * Find clients in the app: the pipeline, the assessments and the lead file.
 */
defined( 'ABSPATH' ) || exit;

function wos_app_lead_pill( $text, $kind = 'grey' ) {
	return '<span class="wos-pill is-' . esc_attr( $kind ) . '">' . esc_html( $text ) . '</span>';
}

function wos_app_level_pill( $level ) {
	$k = array( 'A' => 'green', 'B' => 'pink', 'C' => 'amber', 'D' => 'grey' );
	return $level ? wos_app_lead_pill( 'Level ' . $level, isset( $k[ $level ] ) ? $k[ $level ] : 'grey' ) : '';
}

function wos_app_hours_pill( $lead ) {
	$state = wos_lead_hours_state( $lead );
	$kind  = array( 'green' => 'green', 'amber' => 'amber', 'red' => 'red' );
	return wos_app_lead_pill( 'Sprint 0: ' . (float) $lead->s0_hours . ' of ' . wos_num( 's0_cap' ) . ' h', $kind[ $state ] );
}

function wos_app_leads() {
	$tab  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'pipeline'; // phpcs:ignore WordPress.Security.NonceVerification
	$tabs = array(
		'pipeline'    => 'Pipeline',
		'assessments' => 'Assessments',
		'survey'      => 'Survey' . ( ( $owed = count( wos_survey_owed() ) ) ? ' · ' . $owed . ' to answer' : '' ),
		'new'         => '+ New assessment',
	);
	if ( ! isset( $tabs[ $tab ] ) ) {
		$tab = 'pipeline';
	}
	$leads    = wos_leads_all();
	$weighted = 0;
	foreach ( $leads as $l ) {
		if ( in_array( $l->stage, array( 'prospect', 'lead', 's0' ), true ) ) {
			$weighted += (float) $l->value * (int) $l->win / 100;
		}
	}
	?>
	<div class="wos-head wos-head-row">
		<div>
			<div class="wos-eyebrow">5 STAGES · WEIGHTED PIPELINE <?php echo esc_html( wos_money( $weighted ) ); ?></div>
			<h1>Find clients</h1>
		</div>
		<div class="wos-tabs">
			<?php foreach ( $tabs as $k => $label ) : ?>
				<a class="<?php echo $k === $tab ? 'is-on' : ''; ?>" href="<?php echo esc_url( wos_app_url( 'leads', 'pipeline' === $k ? array() : array( 'tab' => $k ) ) ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
	if ( 'new' === $tab ) {
		wos_app_lead_new();
	} elseif ( 'assessments' === $tab ) {
		wos_app_lead_assessments( $leads );
	} elseif ( 'survey' === $tab ) {
		wos_app_lead_survey();
	} else {
		wos_app_lead_pipeline( $leads );
	}
}

function wos_app_lead_pipeline( $leads ) {
	$stages = wos_lead_stages();
	unset( $stages['lost'] );
	?>
	<div class="wos-board-wrap">
		<div class="wos-board">
			<?php foreach ( $stages as $sk => $s ) : ?>
				<?php $in = array_filter( $leads, function ( $l ) use ( $sk ) { return $l->stage === $sk; } ); ?>
				<div class="wos-col is-<?php echo esc_attr( $sk ); ?>">
					<div class="wos-col-head"><span><?php echo esc_html( $s[0] ); ?></span><span class="wos-muted"><?php echo count( $in ); ?></span></div>
					<div class="wos-col-rule"><?php echo esc_html( $s[1] ); ?></div>
					<?php foreach ( $in as $l ) : ?>
						<?php list( $done, $all ) = wos_lead_progress( $l ); ?>
						<a class="wos-lead-card" href="<?php echo esc_url( wos_app_url( 'leads/' . $l->id ) ); ?>">
							<strong><?php echo esc_html( $l->name ); ?></strong>
							<span class="wos-muted wos-small"><?php echo esc_html( trim( $l->contact_name . ' · ' . $l->source, ' ·' ) ); ?></span>
							<span class="wos-mono wos-small"><?php echo (float) $l->value ? esc_html( wos_money( $l->value ) . ' · ' . (int) $l->win . '%' ) : 'Value not set'; ?></span>
							<span class="wos-card-pills"><?php echo wos_app_level_pill( $l->level ); // phpcs:ignore -- escaped inside. ?><?php echo 's0' === $l->stage ? wos_app_hours_pill( $l ) : ''; // phpcs:ignore -- escaped inside. ?></span>
							<span class="wos-small"><?php echo esc_html( $done . '/' . $all . ' steps · ' . ( $l->next_step ? $l->next_step : 'No next step' ) ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
	$lost = array_filter( $leads, function ( $l ) { return 'lost' === $l->stage; } );
	if ( $lost ) :
		?>
		<section class="wos-card wos-pad">
			<h2>Not now</h2>
			<div class="wos-table-wrap">
				<table class="wos-table">
					<thead><tr><th>Business</th><th>Level</th><th>Why, or what next</th><th></th></tr></thead>
					<tbody>
						<?php foreach ( $lost as $l ) : ?>
							<tr data-href="<?php echo esc_url( wos_app_url( 'leads/' . $l->id ) ); ?>">
								<td class="is-who"><a href="<?php echo esc_url( wos_app_url( 'leads/' . $l->id ) ); ?>"><?php echo esc_html( $l->name ); ?></a><span><?php echo esc_html( $l->contact_name ); ?></span></td>
								<td><?php echo wos_app_level_pill( $l->level ); // phpcs:ignore -- escaped inside. ?></td>
								<td><?php echo esc_html( $l->next_step ); ?></td>
								<td class="is-act">Open ›</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</section>
		<?php
	endif;
	if ( ! $leads ) {
		echo '<p class="wos-muted">No leads yet. Add one from an assessment with <a href="' . esc_url( wos_app_url( 'leads', array( 'tab' => 'new' ) ) ) . '">+ New assessment</a>.</p>';
	}
}

function wos_app_lead_assessments( $leads ) {
	$with = array_filter( $leads, function ( $l ) { return '' !== $l->level; } );
	usort( $with, function ( $x, $y ) { return strcmp( $y->created_at, $x->created_at ); } );
	?>
	<section class="wos-card wos-pad">
		<h2>Assessments received</h2>
		<?php if ( ! $with ) : ?>
			<p class="wos-muted">None yet.</p>
		<?php else : ?>
			<div class="wos-label"><?php echo esc_html( count( $with ) . ' received · newest first' ); ?></div>
			<div class="wos-table-wrap">
				<table class="wos-table">
					<thead><tr><th>Business</th><th>Received</th><th>Source</th><th class="num">Score</th><th>Level</th><th>Proposal</th><th></th></tr></thead>
					<tbody>
						<?php foreach ( $with as $l ) : ?>
							<?php
							$sent = isset( $l->prelim['status'] ) && 'sent' === $l->prelim['status'];
							$url  = wos_app_url( 'leads/' . $l->id, array( 'tab' => 'proposal' ) );
							?>
							<tr data-href="<?php echo esc_url( $url ); ?>">
								<td class="is-who"><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $l->name ); ?></a><span><?php echo esc_html( $l->contact_name ); ?></span></td>
								<td><?php echo esc_html( wos_app_short_date( $l->created_at ) ); ?></td>
								<td><?php echo $l->source ? esc_html( $l->source ) : '<span class="wos-empty">Not set</span>'; ?></td>
								<td class="num"><?php echo (int) wos_lead_total( $l->scores ); ?>/40</td>
								<td><?php echo wos_app_level_pill( $l->level ); // phpcs:ignore -- escaped inside. ?></td>
								<td><?php echo wos_app_lead_pill( $sent ? 'Sent ' . wos_app_short_date( $l->prelim['sent_at'] ) : 'Draft', $sent ? 'green' : 'grey' ); // phpcs:ignore -- escaped inside. ?></td>
								<td class="is-act">Open ›</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>
	<?php
}

/** Answers from the Connected Tools Check, finished or not: where people stop, and the lead each one became. */
function wos_app_lead_survey() {
	$rows  = wos_surveys_all();
	$done  = count( array_filter( $rows, function ( $r ) { return (bool) $r->completed_at; } ) );
	$owed  = wos_survey_owed();
	$ctas  = wos_survey_ctas();
	?>
	<?php if ( $owed ) : ?>
		<section class="wos-card wos-pad">
			<div class="wos-card-head"><h2>Asked for in the report</h2><span class="wos-muted wos-small"><?php echo esc_html( count( $owed ) . ' to answer' ); ?></span></div>
			<p class="wos-muted wos-small">Owners pressed one of these buttons at the end of their report. Each one is a reply we owe.</p>
			<div class="wos-table-wrap">
				<table class="wos-table">
					<thead><tr><th>Person</th><th>Asked for</th><th>When</th><th></th></tr></thead>
					<tbody>
						<?php foreach ( $owed as $r ) : ?>
							<tr<?php echo $r->lead_id ? ' data-href="' . esc_url( wos_app_url( 'leads/' . (int) $r->lead_id ) ) . '"' : ''; ?>>
								<td class="is-who"><a><?php echo esc_html( $r->business ? $r->business : $r->name ); ?></a><span><?php echo esc_html( $r->name ); ?></span></td>
								<td><?php echo wos_app_lead_pill( $ctas[ $r->cta ][0], 'amber' ); // phpcs:ignore -- escaped inside. ?></td>
								<td><?php echo esc_html( wos_app_short_date( $r->cta_at ) ); ?></td>
								<td class="is-act">
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="wos_survey"><input type="hidden" name="do" value="cta_done"><input type="hidden" name="id" value="<?php echo (int) $r->id; ?>"><?php wp_nonce_field( 'wos_survey' ); ?>
										<button class="wos-btn is-ghost is-small" type="submit">Done</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</section>
	<?php endif; ?>
	<section class="wos-card wos-pad">
		<div class="wos-card-head"><h2>Survey answers</h2><span class="wos-muted wos-small"><?php echo esc_html( count( $rows ) . ' started · ' . $done . ' finished' ); ?></span></div>
		<p class="wos-muted wos-small">From the Connected Tools Check on wizad.ca. Each step is saved, so you can see where people stop. A finished survey becomes a lead with its scores.</p>
		<div class="wos-actions"><a class="wos-btn is-ghost is-small" href="<?php echo esc_url( wos_survey_csv_url( 'answers' ) ); ?>">Download for Google Sheets</a></div>
		<?php if ( ! $rows ) : ?>
			<p class="wos-muted">None yet.</p>
		<?php else : ?>
			<div class="wos-table-wrap">
				<table class="wos-table">
					<thead><tr><th>Person</th><th>Started</th><th>Got to</th><th>Source</th><th class="num">Score</th><th>Lead</th><th>Discovery call</th><th></th></tr></thead>
					<tbody>
						<?php foreach ( $rows as $r ) : ?>
							<?php $c = (array) json_decode( (string) $r->computed, true ); ?>
							<tr<?php echo $r->lead_id ? ' data-href="' . esc_url( wos_app_url( 'leads/' . (int) $r->lead_id ) ) . '"' : ''; ?>>
								<td class="is-who"><a><?php echo esc_html( $r->business ? $r->business : ( $r->name ? $r->name : 'Not given yet' ) ); ?></a><span><?php echo esc_html( trim( $r->name . ( $r->lead_ref ? ' · ref ' . $r->lead_ref : '' ), ' ·' ) ); ?></span></td>
								<td><?php echo esc_html( wos_app_short_date( $r->started_at ) ); ?></td>
								<td><?php echo $r->completed_at ? wos_app_lead_pill( 'Finished ' . wos_app_short_date( $r->completed_at ), 'green' ) : wos_app_lead_pill( 'Stopped at ' . wos_survey_step_name( $r ), 'amber' ); // phpcs:ignore -- escaped inside. ?></td>
								<td><?php echo esc_html( $r->utm_source ? $r->utm_source . ( $r->utm_campaign ? ' · ' . $r->utm_campaign : '' ) : 'Direct' ); ?></td>
								<td class="num"><?php echo isset( $c['total'] ) ? (int) $c['total'] . '/100' : '–'; ?></td>
								<td><?php echo $r->lead_id ? wos_app_level_pill( isset( $c['level'] ) ? $c['level'] : '' ) : '<span class="wos-empty">Not yet</span>'; // phpcs:ignore -- escaped inside. ?></td>
								<td>
									<?php if ( $r->completed_at ) : ?>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wos-inline-date">
											<input type="hidden" name="action" value="wos_survey"><input type="hidden" name="do" value="booked"><input type="hidden" name="id" value="<?php echo (int) $r->id; ?>"><?php wp_nonce_field( 'wos_survey' ); ?>
											<input type="date" name="booked_date" value="<?php echo esc_attr( (string) $r->booked_date ); ?>" aria-label="Discovery call booked on" onchange="this.form.submit()">
										</form>
									<?php else : ?>
										<span class="wos-empty">After finishing</span>
									<?php endif; ?>
									<?php if ( $r->cta && isset( $ctas[ $r->cta ] ) ) : ?>
										<span class="wos-muted wos-small">Pressed: <?php echo esc_html( $ctas[ $r->cta ][0] ); ?></span>
									<?php endif; ?>
								</td>
								<td class="is-act">
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Delete these survey answers? Use this for a privacy request.');">
										<input type="hidden" name="action" value="wos_survey"><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?php echo (int) $r->id; ?>"><?php wp_nonce_field( 'wos_survey' ); ?>
										<button class="wos-btn is-text is-small" type="submit">Delete</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>
	<?php
	$codes = wos_survey_codes();
	$tone  = array( 'Not opened' => 'grey', 'Finished' => 'green' );
	?>
	<section class="wos-card wos-pad" id="codes">
		<div class="wos-card-head"><h2>Invite codes</h2><span class="wos-muted wos-small"><?php echo esc_html( count( $codes ) . ' given' ); ?></span></div>
		<p class="wos-muted wos-small">The survey opens only with a code from this list, so only owners we invited can answer. Each code belongs to one prospect.</p>
		<?php if ( $codes ) : ?>
			<div class="wos-table-wrap">
				<table class="wos-table">
					<thead><tr><th>Code</th><th>Business</th><th>Survey</th><th>Given</th><th></th></tr></thead>
					<tbody>
						<?php foreach ( $codes as $l ) : ?>
							<?php $st = wos_survey_code_status( $l ); ?>
							<tr data-href="<?php echo esc_url( wos_app_url( 'leads/' . (int) $l->id ) ); ?>">
								<td class="wos-mono"><?php echo esc_html( $l->ref ); ?></td>
								<td class="is-who"><a href="<?php echo esc_url( wos_app_url( 'leads/' . (int) $l->id ) ); ?>"><?php echo esc_html( $l->name ); ?></a><span><?php echo esc_html( $l->contact_name ); ?></span></td>
								<td><?php echo wos_app_lead_pill( $st, isset( $tone[ $st ] ) ? $tone[ $st ] : 'amber' ); // phpcs:ignore -- escaped inside. ?></td>
								<td><?php echo esc_html( wos_app_short_date( $l->created_at ) ); ?></td>
								<td class="is-act">Open ›</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<div class="wos-actions"><a class="wos-btn is-ghost is-small" href="<?php echo esc_url( wos_survey_csv_url( 'codes' ) ); ?>">Download codes for mail merge</a></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wos-form wos-mt">
			<input type="hidden" name="action" value="wos_survey"><input type="hidden" name="do" value="invite"><?php wp_nonce_field( 'wos_survey' ); ?>
			<label class="wos-field"><span>Add prospects, one per line: Business, Contact name, Email, and optionally field and area codes (e.g. notary, westshore) so the form skips those questions</span><textarea name="list" rows="4" placeholder="Harbour Notary, Ann Lee, ann@harbournotary.ca, notary, westshore"></textarea></label>
			<div class="wos-actions"><button class="wos-btn" type="submit">Add and give codes</button></div>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wos-mt">
			<input type="hidden" name="action" value="wos_survey"><input type="hidden" name="do" value="give"><?php wp_nonce_field( 'wos_survey' ); ?>
			<button class="wos-btn is-text is-small" type="submit">Give a code to every prospect and lead without one</button>
		</form>
	</section>
	<?php if ( current_user_can( 'manage_options' ) ) : ?>
		<section class="wos-card wos-pad">
			<h2>Connection for the form</h2>
			<table class="wos-kv-table">
				<tr><td class="is-l">Endpoint (POST JSON)</td><td class="wos-mono wos-small"><?php echo esc_html( rest_url( 'wos/v1/survey' ) ); ?></td></tr>
				<tr><td class="is-l">Sign-in</td><td>None. The form runs in the visitor's browser, so it cannot hold a secret. Protection: an invite code on the first post, allowed sites only, <?php echo (int) WOS_SURVEY_PER_HOUR; ?> posts per hour per visitor, a hidden honeypot field, and strict answer lists.</td></tr>
			</table>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wos-form wos-mt">
				<input type="hidden" name="action" value="wos_survey"><input type="hidden" name="do" value="origins"><?php wp_nonce_field( 'wos_survey' ); ?>
				<label class="wos-field"><span>Sites allowed to post, one per line (* means any; staging only)</span><textarea name="origins" rows="3"><?php echo esc_textarea( implode( "\n", wos_survey_origins() ) ); ?></textarea></label>
				<div class="wos-actions"><button class="wos-btn is-ghost" type="submit">Save</button></div>
			</form>
		</section>
	<?php endif; ?>
	<?php
}

function wos_app_lead_new() {
	?>
	<section class="wos-card wos-pad">
		<h2>New lead from an assessment</h2>
		<p class="wos-muted">Type in the Connected Tools Check result. WOS sets the level from the four scores, using the thresholds in Settings (A from <?php echo esc_html( wos_num( 'level_a' ) ); ?>, B from <?php echo esc_html( wos_num( 'level_b' ) ); ?>, C from <?php echo esc_html( wos_num( 'level_c' ) ); ?>, out of 40).</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wos-form wos-form-groups">
			<input type="hidden" name="action" value="wos_lead_new">
			<?php wp_nonce_field( 'wos_lead_new' ); ?>
			<fieldset>
				<legend>Business and contact</legend>
				<label class="wos-field"><span>Business name</span><input type="text" name="name" required maxlength="190"></label>
				<div class="wos-field-row">
					<label class="wos-field"><span>Contact person</span><input type="text" name="contact_name" maxlength="190"></label>
					<label class="wos-field"><span>Email</span><input type="text" name="email" maxlength="190"></label>
					<label class="wos-field"><span>Phone</span><input type="text" name="phone" maxlength="50"></label>
				</div>
			</fieldset>
			<fieldset>
				<legend>Source</legend>
				<div class="wos-field-row">
					<label class="wos-field"><span>Came from</span><input type="text" name="source" maxlength="120" placeholder="LinkedIn post, referral, Google…"></label>
					<label class="wos-field"><span>Introducer</span><input type="text" name="introducer" maxlength="120"></label>
					<label class="wos-field"><span>Owner on the team</span><input type="text" name="owner" maxlength="60" placeholder="Tau"></label>
				</div>
			</fieldset>
			<fieldset>
				<legend>Assessment scores, 0 to 10 each</legend>
				<div class="wos-field-row">
					<?php foreach ( wos_lead_score_areas() as $k => $label ) : ?>
						<label class="wos-field"><span><?php echo esc_html( $label ); ?></span><input type="number" name="scores[<?php echo esc_attr( $k ); ?>]" min="0" max="10" value="5" required></label>
					<?php endforeach; ?>
				</div>
				<label class="wos-field"><span>Their answers, one per line (question: answer)</span><textarea name="answers" rows="5" placeholder="How do enquiries reach you?: phone, email and the web form"></textarea></label>
			</fieldset>
			<div class="wos-actions"><button class="wos-btn" type="submit">Create the lead</button></div>
		</form>
	</section>
	<?php
}

/* ---------- The lead file ---------- */

function wos_app_lead( $id ) {
	$lead = wos_lead_get( $id );
	if ( ! $lead ) {
		echo '<div class="wos-head"><h1>Not found</h1></div><p>This lead does not exist. <a href="' . esc_url( wos_app_url( 'leads' ) ) . '">Back to Find clients</a></p>';
		return;
	}
	$tab  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification
	$tabs = array(
		'overview' => 'Overview',
		'proposal' => 'Assessment and proposal',
		'intake'   => 'Intake',
		'summary'  => 'Discovery summary',
		'price'    => 'Price and won',
		'log'      => 'Log',
	);
	if ( ! isset( $tabs[ $tab ] ) ) {
		$tab = 'overview';
	}
	wos_app_lead_path( $lead, $tab );
	$fn = 'wos_app_lead_tab_' . $tab;
	$fn( $lead );
}

/** The steps on the path to Won: check key => array( title, done word, to-do verb, tab ). */
function wos_app_lead_steps() {
	return array(
		'discover' => array(
			'assessed' => array( 'Assessed', '', 'Score', 'proposal' ),
			'prelim'   => array( 'Proposal', 'Sent', 'Send', 'proposal' ),
			'intake'   => array( 'Intake', 'Done', 'Fill in', 'intake' ),
		),
		'agree'    => array(
			'nda'     => array( 'NDA', 'Signed', 'Sign', 'overview' ),
			'summary' => array( 'Summary', 'Confirmed', 'Confirm', 'summary' ),
		),
		'close'    => array(
			'price'  => array( 'Price', 'Approved', 'Approve', 'price' ),
			'signed' => array( 'MSA + SOW', 'Signed', 'Sign', 'price' ),
		),
	);
}

/** "Oct 17" from a stored date. */
function wos_app_short_date( $date ) {
	$t = strtotime( (string) $date );
	return $t ? wp_date( 'M j', $t ) : '';
}

/** The lead header: name, % to Won, the steps in three groups, the next step. The steps replace the row of tabs. */
function wos_app_lead_path( $lead, $tab ) {
	list( $done, $all ) = wos_lead_progress( $lead );
	$won  = 'won' === $lead->stage || 'run' === $lead->stage;
	$pct  = $won ? 100 : (int) round( $done / max( 1, $all ) * 100 );
	$now  = '';
	$open = '';
	foreach ( wos_app_lead_steps() as $group ) {
		foreach ( $group as $k => $st ) {
			if ( '' === $now && empty( $lead->checks[ $k ] ) ) {
				$now  = $k;
				$open = $st[3];
			}
		}
	}
	$sub = array_filter( array( $lead->contact_name, $lead->source, $lead->owner ? 'owner ' . $lead->owner : '' ) );
	?>
	<section class="wos-card wos-pad wos-path-card">
		<a href="<?php echo esc_url( wos_app_url( 'leads' ) ); ?>" class="wos-small">Find clients</a>
		<div class="wos-path-top">
			<div>
				<h1><?php echo esc_html( $lead->name ); ?></h1>
				<div class="wos-path-sub"><?php echo esc_html( implode( ' · ', $sub ) ); ?><?php echo $sub ? ' ' : ''; ?><span class="wos-card-pills"><?php echo wos_app_level_pill( $lead->level ) . ( 's0' === $lead->stage ? wos_app_hours_pill( $lead ) : '' ) . ( 'lost' === $lead->stage ? wos_app_lead_pill( 'Not now', 'grey' ) : '' ) . ( $won ? wos_app_lead_pill( 'Client', 'green' ) : '' ); // phpcs:ignore -- escaped inside. ?></span></div>
			</div>
			<div class="wos-path-pct"><strong><?php echo (int) $pct; ?>%</strong><span><?php echo esc_html( $won ? 'Won' . ( ! empty( $lead->checks['signed'] ) ? ' ' . wos_app_short_date( $lead->checks['signed'] ) : '' ) : $done . ' of ' . $all . ' steps done' ); ?></span></div>
		</div>
		<nav class="wos-path" aria-label="Path to Won">
			<?php foreach ( wos_app_lead_steps() as $gk => $group ) : ?>
				<div class="wos-path-group is-<?php echo esc_attr( $gk ); ?>">
					<div class="wos-path-label"><?php echo esc_html( strtoupper( $gk ) ); ?></div>
					<div class="wos-path-row">
						<?php foreach ( $group as $k => $st ) : ?>
							<?php
							$is_done = ! empty( $lead->checks[ $k ] );
							$state   = $is_done ? 'is-done' : ( $k === $now && ! $won ? 'is-now' : 'is-todo' );
							if ( $is_done ) {
								$word = 'assessed' === $k ? (int) wos_lead_total( $lead->scores ) . '/40' : $st[1];
								$line = wos_app_short_date( $lead->checks[ $k ] ) . ' · ' . $word;
							} elseif ( 'is-now' === $state ) {
								$line = ( $lead->next_date ? 'By ' . wos_app_short_date( $lead->next_date ) : 'Now' ) . ' · ' . $st[2];
							} else {
								$line = 'To do';
							}
							?>
							<a class="wos-step <?php echo esc_attr( $state . ( $st[3] === $tab && 'overview' !== $tab ? ' is-here' : '' ) ); ?>" href="<?php echo esc_url( wos_app_url( 'leads/' . $lead->id, 'overview' === $st[3] ? array() : array( 'tab' => $st[3] ) ) ); ?>"<?php echo 'is-now' === $state ? ' aria-current="step"' : ''; ?>>
								<b><?php echo esc_html( $st[0] ); ?></b><em><?php echo esc_html( $line ); ?></em>
							</a>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
			<div class="wos-path-group">
				<div class="wos-path-label">&nbsp;</div>
				<div class="wos-won<?php echo $won ? ' is-won' : ''; ?>">WON<?php echo $won && ! empty( $lead->checks['signed'] ) ? '<small>' . esc_html( wos_app_short_date( $lead->checks['signed'] ) ) . '</small>' : ''; ?></div>
			</div>
		</nav>
		<div class="wos-path-track"><i style="width: <?php echo (int) $pct; ?>%"></i></div>
		<div class="wos-path-next">
			<span><span class="wos-path-k"><?php echo $won ? 'CLIENT' : 'NEXT'; ?></span><?php echo esc_html( $won ? 'Won. This lead is now a client.' : ( $lead->next_step ? $lead->next_step : 'No next step set' ) . ( $lead->next_date ? ' · by ' . wos_app_short_date( $lead->next_date ) : '' ) ); ?></span>
			<?php if ( $open && ! $won && $open !== $tab ) : ?>
				<a class="wos-btn is-small" href="<?php echo esc_url( wos_app_url( 'leads/' . $lead->id, 'overview' === $open ? array() : array( 'tab' => $open ) ) ); ?>">Open the step</a>
			<?php endif; ?>
		</div>
		<div class="wos-subnav">
			<a class="<?php echo 'overview' === $tab ? 'is-on' : ''; ?>" href="<?php echo esc_url( wos_app_url( 'leads/' . $lead->id ) ); ?>">Overview</a>
			<a class="<?php echo 'log' === $tab ? 'is-on' : ''; ?>" href="<?php echo esc_url( wos_app_url( 'leads/' . $lead->id, array( 'tab' => 'log' ) ) ); ?>">Log <span class="wos-muted"><?php echo count( $lead->log ); ?></span></a>
		</div>
	</section>
	<?php
}

/** The hidden fields every lead form needs. */
function wos_app_lead_form_open( $lead, $action, $class = 'wos-form' ) {
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="' . esc_attr( $class ) . '">';
	echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '"><input type="hidden" name="id" value="' . (int) $lead->id . '">';
	wp_nonce_field( 'wos_lead_' . $lead->id );
}

function wos_app_lead_tab_overview( $lead ) {
	$checks = wos_lead_checks();
	list( $done, $all ) = wos_lead_progress( $lead );
	$where = array(
		'assessed' => 'proposal',
		'prelim'   => 'proposal',
		'intake'   => 'intake',
		'summary'  => 'summary',
		'price'    => 'price',
	);
	global $wpdb;
	$open = array_map( 'wos_decision_prepare', $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . wos_table( 'decisions' ) . " WHERE lead_id = %d AND status IN ('waiting','aside') ORDER BY id", $lead->id ) ) );
	?>
	<div class="wos-row wos-top">
		<div class="wos-col-main">
			<section class="wos-card wos-pad">
				<div class="wos-card-head"><h2>Sprint 0 checklist</h2><span class="wos-muted wos-small"><?php echo (int) $done . ' of ' . (int) $all; ?> done</span></div>
				<?php foreach ( $checks as $k => $c ) : ?>
					<?php $on = ! empty( $lead->checks[ $k ] ); ?>
					<div class="wos-check">
						<span class="wos-box<?php echo $on ? ' is-on' : ''; ?>" aria-hidden="true"><?php echo $on ? '✓' : ''; ?></span>
						<span class="wos-check-label"><?php echo esc_html( $c[0] ); ?><?php if ( $on ) : ?> <span class="wos-muted wos-small">· <?php echo esc_html( $lead->checks[ $k ] ); ?></span><?php endif; ?></span>
						<?php if ( 'hand' === $c[1] ) : ?>
							<?php wos_app_lead_form_open( $lead, 'wos_lead_check', 'wos-inline' ); ?>
								<input type="hidden" name="check" value="<?php echo esc_attr( $k ); ?>">
								<button class="wos-btn is-ghost is-small" type="submit"><?php echo $on ? 'Untick' : 'Tick'; ?></button>
							</form>
						<?php elseif ( ! $on && isset( $where[ $k ] ) ) : ?>
							<a class="wos-small" href="<?php echo esc_url( wos_app_url( 'leads/' . $lead->id, array( 'tab' => $where[ $k ] ) ) ); ?>">Go to the step</a>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
				<div class="wos-gate<?php echo $done === $all ? ' is-ok' : ''; ?>"><?php echo esc_html( $done === $all ? ( 'won' === $lead->stage ? 'Won. This lead is a client.' : 'Exit gate passed. Move it to Won on Price and won.' ) : ( $all - $done ) . ' steps left before this lead can move to Won.' ); ?></div>
			</section>
			<?php if ( $open ) : ?>
				<section class="wos-card wos-pad">
					<h2>Waiting for Leo</h2>
					<?php foreach ( $open as $d ) : ?>
						<div class="wos-line"><span><?php echo esc_html( $d->question ); ?></span><a href="<?php echo esc_url( wos_app_url( 'decisions' ) . '#d' . (int) $d->id ); ?>">Open</a></div>
					<?php endforeach; ?>
				</section>
			<?php endif; ?>
			<?php wos_app_lead_details( $lead ); ?>
		</div>
		<div class="wos-col-side">
			<?php if ( in_array( $lead->stage, array( 'lead', 's0' ), true ) ) : ?>
				<section class="wos-card wos-pad">
					<h2>Sprint 0 hours</h2>
					<p class="wos-mono"><?php echo esc_html( (float) $lead->s0_hours . ' of ' . wos_num( 's0_cap' ) . ' h' ); ?></p>
					<?php
					$state = wos_lead_hours_state( $lead );
					$cap   = wos_num( 's0_cap' );
					$notes = array(
						'green' => ( $cap - (float) $lead->s0_hours ) . ' h left in unpaid discovery.',
						'amber' => ( $cap - (float) $lead->s0_hours ) . ' h left. Plan the summary now.',
						'red'   => 'Over the cap. Leo decides how to go on.',
					);
					?>
					<div class="wos-live-note is-<?php echo esc_attr( array( 'green' => 'ok', 'amber' => 'warn', 'red' => 'bad' )[ $state ] ); ?>"><?php echo esc_html( $notes[ $state ] ); ?></div>
					<?php wos_app_lead_form_open( $lead, 'wos_lead_hours' ); ?>
						<div class="wos-field-row">
							<label class="wos-field"><span>Hours</span><input type="number" name="hours" step="0.25" min="0.25" max="24" value="1" required></label>
						</div>
						<label class="wos-field"><span>What for</span><input type="text" name="note" placeholder="Discovery call"></label>
						<div class="wos-actions"><button class="wos-btn is-ghost" type="submit">Log hours</button></div>
					</form>
				</section>
			<?php endif; ?>
			<?php wos_app_chat_panel( 'lead:' . $lead->id, 'Ask Claude about this lead', array( 'What should we do next with this lead?', 'What is missing before we can price it?' ) ); ?>
		</div>
	</div>
	<?php
}

/** A masked email or phone with an eye: the real value is fetched, and logged, only when the eye is pressed. */
function wos_app_private( $lead, $field, $value, $type ) {
	if ( '' === (string) $value ) {
		return '<span class="wos-empty">Not set</span>';
	}
	$mask = 'email' === $type ? wos_mask_email( $value ) : wos_mask_phone( $value );
	return '<span class="wos-private" data-lead="' . (int) $lead->id . '" data-field="' . esc_attr( $field ) . '" data-type="' . esc_attr( $type ) . '"><span class="wos-private-v">' . esc_html( $mask ) . '</span>'
		. wos_app_eye( $type ) . '</span>';
}

function wos_app_eye( $type ) {
	return '<button type="button" class="wos-eye" aria-label="Show the ' . esc_attr( $type ) . '" title="Show"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg></button>';
}

/** An edit-form input for a masked field: blank and masked until the eye is pressed; left closed, the saved value stays. */
function wos_app_private_input( $lead, $name, $field, $value, $type, $label ) {
	if ( '' === (string) $value ) {
		return '<input type="text" name="' . esc_attr( $name ) . '" value="" aria-label="' . esc_attr( $label ) . '">';
	}
	$mask = 'email' === $type ? wos_mask_email( $value ) : wos_mask_phone( $value );
	$keep = ']' === substr( $name, -1 ) ? substr( $name, 0, -1 ) . '_keep]' : $name . '_keep';
	return '<span class="wos-private-in" data-lead="' . (int) $lead->id . '" data-field="' . esc_attr( $field ) . '"><input type="text" name="' . esc_attr( $name ) . '" value="" placeholder="' . esc_attr( $mask ) . '" aria-label="' . esc_attr( $label . ' (hidden; press the eye to show)' ) . '" readonly>'
		. '<input type="hidden" name="' . esc_attr( $keep ) . '" value="1">' . wos_app_eye( $type ) . '</span>';
}

/** Details: grouped and read-only first; the form opens only to edit. */
function wos_app_lead_details( $lead ) {
	$stages = wos_lead_stages();
	$val    = function ( $v, $empty = 'Not set' ) {
		return '' !== trim( (string) $v ) ? esc_html( $v ) : '<span class="wos-empty">' . esc_html( $empty ) . '</span>';
	};
	$weighted = (float) $lead->value * (int) $lead->win / 100;
	$groups   = array(
		'Founder (main contact)' => array(
			'Person' => $val( $lead->contact_name ),
			'Email'  => wos_app_private( $lead, 'email', $lead->email, 'email' ),
			'Phone'  => wos_app_private( $lead, 'phone', $lead->phone, 'phone' ),
		),
		'Source'    => array(
			'Came from'  => $val( $lead->source ),
			'Introducer' => $val( $lead->introducer, 'None' ),
			'Owner'      => $val( $lead->owner ),
		),
		'Deal'      => array(
			'Stage'    => esc_html( $stages[ $lead->stage ][0] ),
			'Value'    => (float) $lead->value ? '<span class="wos-mono">' . esc_html( wos_money( $lead->value ) ) . '</span>' : $val( '' ),
			'Chance'   => '<span class="wos-mono">' . (int) $lead->win . '%</span>' . ( (float) $lead->value ? ' <span class="wos-muted wos-small">· weighted ' . esc_html( wos_money( $weighted ) ) . '</span>' : '' ),
		),
		'Next step' => array(
			'What' => $val( $lead->next_step ),
			'By'   => $lead->next_date ? esc_html( wp_date( 'D M j, Y', strtotime( $lead->next_date ) ) ) : $val( '', 'No date' ),
		),
	);
	?>
	<section class="wos-card wos-pad">
		<div class="wos-card-head"><h2>Details</h2><button type="button" class="wos-btn is-ghost is-small" data-toggle="wos-edit-<?php echo (int) $lead->id; ?>">Edit</button></div>
		<div class="wos-groups" id="wos-view-<?php echo (int) $lead->id; ?>">
			<?php foreach ( $groups as $title => $rows ) : ?>
				<div class="wos-group">
					<h3><?php echo esc_html( $title ); ?></h3>
					<dl>
						<?php foreach ( $rows as $k => $v ) : ?>
							<dt><?php echo esc_html( $k ); ?></dt><dd><?php echo $v; // phpcs:ignore -- escaped above. ?></dd>
						<?php endforeach; ?>
					</dl>
				</div>
			<?php endforeach; ?>
			<?php if ( $lead->contacts ) : ?>
				<div class="wos-group is-wide">
					<h3>Other contacts</h3>
					<div class="wos-people">
						<?php foreach ( $lead->contacts as $ci => $c ) : ?>
							<div class="wos-person">
								<div class="wos-person-top"><strong><?php echo esc_html( $c['name'] ); ?></strong><span class="wos-pill is-grey"><?php echo esc_html( $c['role'] ); ?></span></div>
								<?php if ( $c['email'] ) : ?><div><?php echo wos_app_private( $lead, 'c' . (int) $ci . 'email', $c['email'], 'email' ); // phpcs:ignore -- escaped inside. ?></div><?php endif; ?>
								<?php if ( $c['phone'] ) : ?><div><?php echo wos_app_private( $lead, 'c' . (int) $ci . 'phone', $c['phone'], 'phone' ); // phpcs:ignore -- escaped inside. ?></div><?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
			<?php if ( '' !== trim( $lead->notes ) ) : ?>
				<div class="wos-group is-wide"><h3>Notes</h3><p><?php echo nl2br( esc_html( $lead->notes ) ); ?></p></div>
			<?php endif; ?>
		</div>
		<div id="wos-edit-<?php echo (int) $lead->id; ?>" hidden>
			<?php wos_app_lead_form_open( $lead, 'wos_lead_update', 'wos-form wos-form-groups' ); ?>
				<fieldset>
					<legend>Business and founder (main contact)</legend>
					<label class="wos-field"><span>Business name</span><input type="text" name="name" value="<?php echo esc_attr( $lead->name ); ?>"></label>
					<div class="wos-field-row">
						<label class="wos-field"><span>Founder</span><input type="text" name="contact_name" value="<?php echo esc_attr( $lead->contact_name ); ?>"></label>
						<div class="wos-field"><span>Email</span><?php echo wos_app_private_input( $lead, 'email', 'email', $lead->email, 'email', 'Email' ); // phpcs:ignore -- escaped inside. ?></div>
						<div class="wos-field"><span>Phone</span><?php echo wos_app_private_input( $lead, 'phone', 'phone', $lead->phone, 'phone', 'Phone' ); // phpcs:ignore -- escaped inside. ?></div>
					</div>
				</fieldset>
				<fieldset class="wos-contacts">
					<legend>Other contacts</legend>
					<?php
					$rows   = $lead->contacts;
					$rows[] = array( 'name' => '', 'role' => '', 'email' => '', 'phone' => '' );
					foreach ( $rows as $i => $c ) :
						?>
						<div class="wos-field-row wos-contact-row">
							<label class="wos-field"><span>Name</span><input type="text" name="contacts[<?php echo (int) $i; ?>][name]" value="<?php echo esc_attr( $c['name'] ); ?>"></label>
							<label class="wos-field"><span>Role</span><select name="contacts[<?php echo (int) $i; ?>][role]">
								<?php foreach ( wos_lead_contact_roles() as $r ) : ?><option <?php selected( $r, $c['role'] ); ?>><?php echo esc_html( $r ); ?></option><?php endforeach; ?>
							</select></label>
							<div class="wos-field"><span>Email</span><?php echo wos_app_private_input( $lead, 'contacts[' . (int) $i . '][email]', 'c' . (int) $i . 'email', $c['email'], 'email', 'Email' ); // phpcs:ignore -- escaped inside. ?></div>
							<div class="wos-field"><span>Phone</span><?php echo wos_app_private_input( $lead, 'contacts[' . (int) $i . '][phone]', 'c' . (int) $i . 'phone', $c['phone'], 'phone', 'Phone' ); // phpcs:ignore -- escaped inside. ?></div>
						</div>
					<?php endforeach; ?>
					<p class="wos-muted wos-small">To remove someone, clear the name. <button type="button" class="wos-btn is-text is-small" data-add-contact>+ Add another person</button></p>
				</fieldset>
				<fieldset>
					<legend>Source</legend>
					<div class="wos-field-row">
						<label class="wos-field"><span>Came from</span><input type="text" name="source" value="<?php echo esc_attr( $lead->source ); ?>"></label>
						<label class="wos-field"><span>Introducer</span><input type="text" name="introducer" value="<?php echo esc_attr( $lead->introducer ); ?>"></label>
						<label class="wos-field"><span>Owner on the team</span><input type="text" name="owner" value="<?php echo esc_attr( $lead->owner ); ?>"></label>
					</div>
				</fieldset>
				<fieldset>
					<legend>Deal</legend>
					<div class="wos-field-row">
						<label class="wos-field"><span>Stage</span><select name="stage">
							<?php foreach ( $stages as $k => $st ) : ?>
								<?php if ( 'won' === $k && 'won' !== $lead->stage ) { continue; } ?>
								<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $k, $lead->stage ); ?>><?php echo esc_html( $st[0] ); ?></option>
							<?php endforeach; ?>
						</select></label>
						<label class="wos-field"><span>Value $</span><input type="number" step="0.01" min="0" name="value" value="<?php echo esc_attr( (float) $lead->value ); ?>"></label>
						<label class="wos-field"><span>Chance to win %</span><input type="number" min="0" max="100" name="win" value="<?php echo esc_attr( (int) $lead->win ); ?>"></label>
					</div>
				</fieldset>
				<fieldset>
					<legend>Next step</legend>
					<div class="wos-field-row">
						<label class="wos-field is-grow"><span>What happens next</span><input type="text" name="next_step" value="<?php echo esc_attr( $lead->next_step ); ?>"></label>
						<label class="wos-field"><span>By</span><input type="date" name="next_date" value="<?php echo esc_attr( $lead->next_date ); ?>"></label>
					</div>
					<label class="wos-field"><span>Notes</span><textarea name="notes" rows="3"><?php echo esc_textarea( $lead->notes ); ?></textarea></label>
				</fieldset>
				<div class="wos-actions"><button class="wos-btn" type="submit">Save</button><button class="wos-btn is-text" type="button" data-toggle="wos-edit-<?php echo (int) $lead->id; ?>">Cancel</button></div>
			</form>
		</div>
	</section>
	<?php
}

function wos_app_lead_tab_proposal( $lead ) {
	$p     = array_merge( wos_lead_prelim_template( $lead ), $lead->prelim );
	$route = wos_lead_route( $lead->level );
	$sent  = 'sent' === $p['status'];
	?>
	<div class="wos-row wos-top">
		<div class="wos-col-side">
			<section class="wos-card wos-pad">
				<div class="wos-card-head"><h2>Assessment</h2><span class="wos-muted">Score <strong class="wos-mono"><?php echo (int) wos_lead_total( $lead->scores ); ?></strong> / 40</span></div>
				<?php foreach ( wos_lead_score_areas() as $k => $label ) : ?>
					<?php $v = isset( $lead->scores[ $k ] ) ? (int) $lead->scores[ $k ] : 0; ?>
					<div class="wos-score"><span><?php echo esc_html( $label ); ?></span><strong class="wos-mono"><?php echo (int) $v; ?>/10</strong><span class="wos-bar"><span style="width: <?php echo (int) $v * 10; ?>%"></span></span></div>
				<?php endforeach; ?>
				<div class="wos-route is-<?php echo esc_attr( strtolower( $lead->level ) ); ?>"><strong>Level <?php echo esc_html( $lead->level ? $lead->level : '–' ); ?>:</strong> <?php echo esc_html( $route[0] ); ?></div>
				<?php if ( $lead->answers ) : ?>
					<h3 class="wos-h3">Their answers</h3>
					<ul class="wos-list"><?php foreach ( $lead->answers as $a ) : ?><li><?php echo esc_html( $a ); ?></li><?php endforeach; ?></ul>
				<?php endif; ?>
			</section>
		</div>
		<div class="wos-col-main">
			<section class="wos-card wos-pad">
				<div class="wos-card-head"><h2>Preliminary proposal</h2><?php echo wos_app_lead_pill( $sent ? 'Sent ' . substr( (string) $p['sent_at'], 0, 10 ) : 'Draft · check before it goes', $sent ? 'green' : 'grey' ); // phpcs:ignore -- escaped inside. ?></div>
				<p class="wos-muted wos-small">No price: what we see, the likely first sprint and an effort range only.<?php echo ! empty( $p['drafted_by'] ) ? ' First draft by Claude: check every line.' : ''; ?></p>
				<?php wos_app_lead_form_open( $lead, 'wos_lead_prelim' ); ?>
					<label class="wos-field"><span>What we see</span><textarea name="see" rows="3"><?php echo esc_textarea( $p['see'] ); ?></textarea></label>
					<label class="wos-field"><span>Likely first sprint</span><textarea name="sprint" rows="2"><?php echo esc_textarea( $p['sprint'] ); ?></textarea></label>
					<div class="wos-field-row">
						<label class="wos-field"><span>Effort from (hours)</span><input type="number" step="0.5" min="0" name="low" value="<?php echo esc_attr( $p['low'] ); ?>"></label>
						<label class="wos-field"><span>Effort to (hours)</span><input type="number" step="0.5" min="0" name="high" value="<?php echo esc_attr( $p['high'] ); ?>"></label>
					</div>
					<label class="wos-field"><span>Next step</span><textarea name="next" rows="2"><?php echo esc_textarea( $p['next'] ); ?></textarea></label>
					<div class="wos-actions">
						<button class="wos-btn is-ghost" type="submit" name="do" value="save">Save draft</button>
						<?php if ( ! $sent ) : ?><button class="wos-btn" type="submit" name="do" value="send">Checked · mark as sent</button><?php endif; ?>
					</div>
				</form>
				<?php if ( ! $sent ) : ?>
					<?php if ( wos_claude_key() ) : ?>
						<?php wos_app_lead_form_open( $lead, 'wos_lead_prelim_draft', 'wos-inline' ); ?>
							<button class="wos-btn is-text" type="submit">Draft it with Claude</button>
						</form>
					<?php endif; ?>
				<?php endif; ?>
			</section>
			<?php if ( $p['see'] ) : ?>
				<section class="wos-card wos-pad">
					<h2>Text to send</h2>
					<p class="wos-muted wos-small">WOS does not send email. Copy this into your email to the client.</p>
					<textarea class="wos-copy" rows="9" readonly><?php
					echo esc_textarea(
						'Hi ' . ( $lead->contact_name ? $lead->contact_name : 'there' ) . ",\n\nThank you for taking the Connected Tools Check. Here is what we see.\n\n"
						. $p['see'] . "\n\nA likely first sprint: " . $p['sprint'] . "\nEffort: about " . $p['low'] . ' to ' . $p['high'] . " hours.\n\nNext step: " . $p['next'] . "\n\nWIZAD Consulting"
					);
					?></textarea>
				</section>
			<?php endif; ?>
			<?php if ( 'lead' === $lead->stage ) : ?>
				<section class="wos-card wos-pad">
					<h2>They said yes?</h2>
					<?php wos_app_lead_form_open( $lead, 'wos_lead_start_s0', 'wos-inline' ); ?>
						<button class="wos-btn" type="submit">Start Sprint 0</button><span class="wos-muted wos-small">Unpaid discovery, capped at <?php echo esc_html( wos_num( 's0_cap' ) ); ?> h.</span>
					</form>
				</section>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

function wos_app_lead_tab_intake( $lead ) {
	$vals    = isset( $lead->intake['values'] ) ? $lead->intake['values'] : array();
	$srcs    = array( 'interview' => 'Interview', 'client' => 'Client', 'assessment' => 'Assessment' );
	$done    = ! empty( $lead->checks['intake'] );
	$request = ! empty( $lead->intake['requested'] );
	$fields  = wos_lead_intake_fields();
	$units   = array();
	foreach ( wos_lead_intake_ask() as $k => $a ) {
		$units[ $k ] = 'number' === $a[2] ? $a[3] : '';
	}
	$empty   = ! array_filter( $vals, function ( $v ) { return '' !== (string) $v['v']; } );
	$src_tag = function ( $k ) use ( $vals, $srcs ) {
		$sk = isset( $vals[ $k ]['src'] ) && isset( $srcs[ $vals[ $k ]['src'] ] ) ? $vals[ $k ]['src'] : 'interview';
		return '<span class="wos-src is-' . esc_attr( $sk ) . '">' . esc_html( $srcs[ $sk ] ) . '</span>';
	};
	$v = function ( $k ) use ( $vals ) {
		return isset( $vals[ $k ]['v'] ) ? (string) $vals[ $k ]['v'] : '';
	};
	?>
	<?php if ( $request ) : ?>
		<section class="wos-card wos-pad">
			<h2>Online intake for the client</h2>
			<?php if ( ! empty( $lead->intake['client_at'] ) ) : ?>
				<p class="wos-ok-box">The client filled it in on <?php echo esc_html( wos_app_short_date( $lead->intake['client_at'] ) ); ?>. Their answers are tagged Client below.</p>
			<?php else : ?>
				<p class="wos-muted">Send this link to the client. It opens without signing in and shows only the intake questions.</p>
				<input class="wos-copy" type="text" readonly value="<?php echo esc_attr( wos_lead_client_url( $lead ) ); ?>" onclick="this.select()">
			<?php endif; ?>
		</section>
	<?php endif; ?>
	<section class="wos-card wos-pad">
		<div class="wos-card-head">
			<h2>Intake</h2>
			<span class="wos-card-pills"><?php echo $done ? wos_app_lead_pill( 'Complete · ' . wos_app_short_date( $lead->checks['intake'] ), 'green' ) : wos_app_lead_pill( 'Not complete', 'grey' ); // phpcs:ignore -- escaped inside. ?><button type="button" class="wos-btn is-ghost is-small" data-toggle="wos-intake-edit" data-hide="wos-intake-view"<?php echo $empty ? ' hidden' : ''; ?>>Edit</button></span>
		</div>
		<div id="wos-intake-view"<?php echo $empty ? ' hidden' : ''; ?>>
			<div class="wos-two">
				<div>
					<div class="wos-label">As-is profile</div>
					<table class="wos-kv-table">
						<?php foreach ( $fields as $k => $f ) : ?>
							<?php if ( 'profile' !== $f[1] ) { continue; } ?>
							<tr><td class="is-l"><?php echo esc_html( preg_replace( '/ \(1 to 5\)$/', '', $f[0] ) ); ?></td><td><?php echo '' !== $v( $k ) ? esc_html( $v( $k ) . ( 'maturity' === $k ? ' / 5' : '' ) ) : '<span class="wos-empty">Not set</span>'; ?></td><td class="is-src"><?php echo '' !== $v( $k ) ? $src_tag( $k ) : ''; // phpcs:ignore -- escaped inside. ?></td></tr>
						<?php endforeach; ?>
					</table>
				</div>
				<div>
					<div class="wos-label">Baseline · measured again at 30, 60, 90 days</div>
					<table class="wos-kv-table">
						<thead><tr><th>Number</th><th class="num">Start</th><th class="num">30 d</th><th class="num">60 d</th><th class="num">90 d</th><th></th></tr></thead>
						<?php foreach ( $fields as $k => $f ) : ?>
							<?php if ( 'baseline' !== $f[1] ) { continue; } ?>
							<tr>
								<td class="is-l"><?php echo esc_html( $f[0] ); ?></td>
								<td class="num"><?php echo '' !== $v( $k ) ? esc_html( $v( $k ) ) : '<span class="wos-dash">–</span>'; ?></td>
								<?php foreach ( array( 'd30', 'd60', 'd90' ) as $d ) : ?>
									<td class="num"><?php echo ! empty( $vals[ $k ][ $d ] ) ? esc_html( $vals[ $k ][ $d ] ) : '<span class="wos-dash">–</span>'; ?></td>
								<?php endforeach; ?>
								<td class="is-src"><?php echo '' !== $v( $k ) ? $src_tag( $k ) : ''; // phpcs:ignore -- escaped inside. ?></td>
							</tr>
						<?php endforeach; ?>
					</table>
				</div>
			</div>
			<div class="wos-legend">Source: <span class="wos-src is-interview">Interview</span> <span class="wos-src is-client">Client</span> filled online <span class="wos-src is-assessment">Assessment</span></div>
		</div>
		<div id="wos-intake-edit"<?php echo $empty ? '' : ' hidden'; ?>>
			<?php wos_app_lead_form_open( $lead, 'wos_lead_intake', '' ); ?>
				<div class="wos-two">
					<?php foreach ( array( 'profile' => 'As-is profile', 'baseline' => 'Baseline (start values)' ) as $g => $title ) : ?>
						<div>
							<div class="wos-label"><?php echo esc_html( $title ); ?></div>
							<table class="wos-kv-table is-edit">
								<?php foreach ( $fields as $k => $f ) : ?>
									<?php
									if ( $f[1] !== $g ) {
										continue;
									}
									$src = isset( $vals[ $k ]['src'] ) ? $vals[ $k ]['src'] : 'interview';
									?>
									<tr>
										<td class="is-l"><label for="in-<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $f[0] ); ?></label><?php echo $units[ $k ] ? '<small>' . esc_html( $units[ $k ] ) . '</small>' : ''; ?></td>
										<td><input type="text" id="in-<?php echo esc_attr( $k ); ?>" name="f[<?php echo esc_attr( $k ); ?>][v]" value="<?php echo esc_attr( $v( $k ) ); ?>"<?php echo 'baseline' === $g || 'maturity' === $k ? ' class="wos-num" inputmode="decimal"' : ''; ?>></td>
										<td><select name="f[<?php echo esc_attr( $k ); ?>][src]" aria-label="<?php echo esc_attr( $f[0] . ': source' ); ?>">
											<?php foreach ( $srcs as $sk => $sl ) : ?><option value="<?php echo esc_attr( $sk ); ?>" <?php selected( $sk, $src ); ?>><?php echo esc_html( $sl ); ?></option><?php endforeach; ?>
										</select></td>
									</tr>
								<?php endforeach; ?>
							</table>
						</div>
					<?php endforeach; ?>
				</div>
				<div class="wos-actions wos-mt">
					<button class="wos-btn" type="submit" name="do" value="save">Save</button>
					<?php if ( ! $request ) : ?><button class="wos-btn is-ghost" type="submit" name="do" value="online">Let the client fill it online</button><?php endif; ?>
					<?php if ( ! $empty ) : ?><button class="wos-btn is-text" type="button" data-toggle="wos-intake-edit" data-hide="wos-intake-view">Cancel</button><?php endif; ?>
				</div>
			</form>
		</div>
		<?php if ( ! $done && ! $empty ) : ?>
			<?php wos_app_lead_form_open( $lead, 'wos_lead_intake', 'wos-inline wos-mt' ); ?>
				<input type="hidden" name="do" value="complete">
				<button class="wos-btn" type="submit">Intake complete</button><span class="wos-muted wos-small">Next: the discovery summary.</span>
			</form>
		<?php endif; ?>
	</section>
	<?php
}

function wos_app_lead_tab_summary( $lead ) {
	$s      = $lead->summary ? array_merge( wos_lead_summary_template( $lead ), $lead->summary ) : wos_lead_summary_template( $lead );
	$status = isset( $s['status'] ) ? $s['status'] : 'draft';
	$labels = array(
		'draft'     => array( 'Draft', 'grey' ),
		'sent'      => array( 'Sent · waiting for the client', 'amber' ),
		'changes'   => array( 'The client asked for changes', 'red' ),
		'confirmed' => array( 'Confirmed by the client', 'green' ),
	);
	?>
	<div class="wos-row wos-top">
		<section class="wos-card wos-pad wos-col-main">
			<div class="wos-card-head"><h2>Discovery summary</h2><?php echo wos_app_lead_pill( $labels[ $status ][0], $labels[ $status ][1] ); // phpcs:ignore -- escaped inside. ?></div>
			<p class="wos-muted wos-small">One page the client confirms before we price. Started from the intake; edit freely.</p>
			<?php if ( 'changes' === $status && ! empty( $s['client_note'] ) ) : ?>
				<div class="wos-flash is-warning">The client wrote: <?php echo esc_html( $s['client_note'] ); ?></div>
			<?php endif; ?>
			<?php wos_app_lead_form_open( $lead, 'wos_lead_summary' ); ?>
				<?php foreach ( wos_lead_summary_parts() as $k => $label ) : ?>
					<label class="wos-field"><span><?php echo esc_html( $label ); ?></span><textarea name="<?php echo esc_attr( $k ); ?>" rows="3" <?php disabled( 'confirmed', $status ); ?>><?php echo esc_textarea( isset( $s[ $k ] ) ? $s[ $k ] : '' ); ?></textarea></label>
				<?php endforeach; ?>
				<?php if ( 'confirmed' !== $status ) : ?>
					<div class="wos-actions">
						<button class="wos-btn is-ghost" type="submit" name="do" value="save">Save</button>
						<button class="wos-btn" type="submit" name="do" value="send"><?php echo 'draft' === $status ? 'Send to the client' : 'Send again'; ?></button>
						<?php if ( 'sent' === $status ) : ?><button class="wos-btn is-text" type="submit" name="do" value="confirmed">They confirmed by email</button><?php endif; ?>
					</div>
				<?php endif; ?>
			</form>
		</section>
		<div class="wos-col-side">
			<section class="wos-card wos-pad">
				<h2>Steps</h2>
				<?php
				$steps = array(
					'Summary drafted from the intake' => true,
					'Sent to the client'              => in_array( $status, array( 'sent', 'changes', 'confirmed' ), true ),
					'Client confirmed'                => 'confirmed' === $status,
					'Price: Leo approves'             => ! empty( $lead->checks['price'] ),
				);
				foreach ( $steps as $label => $on ) :
					?>
					<div class="wos-check"><span class="wos-box<?php echo $on ? ' is-on' : ''; ?>" aria-hidden="true"><?php echo $on ? '✓' : ''; ?></span><span class="wos-check-label"><?php echo esc_html( $label ); ?></span></div>
				<?php endforeach; ?>
				<?php if ( in_array( $status, array( 'sent', 'changes' ), true ) ) : ?>
					<p class="wos-muted wos-small">Client link (opens without signing in):</p>
					<input class="wos-copy" type="text" readonly value="<?php echo esc_attr( wos_lead_client_url( $lead ) ); ?>" onclick="this.select()">
				<?php endif; ?>
				<p class="wos-muted wos-small"><?php echo esc_html( 'confirmed' === $status ? 'Confirmed ' . substr( (string) $s['confirmed_at'], 0, 10 ) . ' · ' . $s['confirmed_by'] . '. Pricing is open.' : 'Pricing stays closed until the client confirms.' ); ?></p>
			</section>
		</div>
	</div>
	<?php
}

function wos_app_lead_tab_price( $lead ) {
	list( $done, $all ) = wos_lead_progress( $lead );
	$open = ! empty( $lead->checks['summary'] );
	?>
	<div class="wos-row wos-top">
		<section class="wos-card wos-pad wos-col-main">
			<h2>Price: Leo decides</h2>
			<?php if ( ! $open ) : ?>
				<p class="wos-muted">Pricing opens after the client confirms the discovery summary.</p>
			<?php elseif ( ! empty( $lead->checks['price'] ) ) : ?>
				<p class="wos-ok-box">Leo approved the price on <?php echo esc_html( $lead->checks['price'] ); ?>. See the log for the answer.</p>
			<?php else : ?>
				<p class="wos-muted wos-small">This goes to Leo's decision queue with the summary, the numbers and a link back here. Options one per line: label | what we gain | the risk | margin.</p>
				<?php wos_app_lead_form_open( $lead, 'wos_lead_price' ); ?>
					<div class="wos-field-row">
						<label class="wos-field"><span>Estimated hours for the first sprint</span><input type="number" step="0.5" min="0" name="hours" value="<?php echo esc_attr( isset( $lead->prelim['high'] ) ? $lead->prelim['high'] : '' ); ?>"></label>
						<label class="wos-field"><span>Answer needed by</span><input type="date" name="due_date"></label>
					</div>
					<label class="wos-field"><span>Why now</span><textarea name="why_now" rows="2" placeholder="They asked for a price this week."></textarea></label>
					<label class="wos-field"><span>Price options</span><textarea name="options" rows="4" placeholder="Fixed $2,400 for the sprint | Clear for them | Overrun is ours | 52%&#10;Fixed $2,800 | Better margin | May slow the yes | 59%"></textarea></label>
					<label class="wos-field"><span>Recommended option (number)</span><input type="number" name="recommended" min="1" max="6" value="1"></label>
					<div class="wos-actions"><button class="wos-btn" type="submit">Send to Leo</button></div>
				</form>
			<?php endif; ?>
		</section>
		<section class="wos-card wos-pad wos-col-side">
			<h2>Won</h2>
			<p class="wos-muted"><?php echo esc_html( $done === $all ? 'Every step is done.' : ( $all - $done ) . ' steps left: the NDA (if needed) and the signed MSA and SOW are ticked by hand on Overview.' ); ?></p>
			<?php if ( 'won' === $lead->stage ) : ?>
				<p class="wos-ok-box">Won. Client record #<?php echo (int) $lead->client_id; ?>.</p>
			<?php else : ?>
				<?php wos_app_lead_form_open( $lead, 'wos_lead_won', 'wos-inline' ); ?>
					<button class="wos-btn" type="submit" <?php disabled( $done < $all ); ?>>Move to Won</button>
				</form>
			<?php endif; ?>
		</section>
	</div>
	<?php
}

/** One icon and colour per kind of work in the log. */
function wos_app_log_icon( $kind ) {
	$p = array(
		'check'  => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
		'x'      => '<path d="M6 6l12 12M18 6L6 18"/>',
		'send'   => '<path d="M4 12l16-8-6 16-3-7z"/>',
		'link'   => '<path d="M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/>',
		'pen'    => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="M13 7l4 4"/>',
		'doc'    => '<path d="M7 3h8l4 4v14H7z"/><path d="M15 3v4h4"/><path d="M10 13l2 2 4-4"/>',
		'clock'  => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
		'money'  => '<path d="M12 3v18M16.5 7.5c-1-1.2-2.6-1.8-4.5-1.8-2.5 0-4 1.3-4 3s1.5 2.5 4 3 4 1.3 4 3-1.5 3-4 3c-2 0-3.6-.7-4.6-2"/>',
		'clip'   => '<path d="M9 4h6v3H9z"/><path d="M7 5H5v16h14V5h-2"/><path d="M9 12h6M9 16h4"/>',
		'note'   => '<path d="M5 5h14v10H9l-4 4z"/>',
		'star'   => '<path d="M12 3l2.6 5.6 6 .7-4.5 4.1 1.2 6L12 16.4 6.7 19.4l1.2-6L3.4 9.3l6-.7z"/>',
		'eye'    => '<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/>',
		'person' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/>',
	);
	$k = array(
		'approved'   => array( 'check', '#1D7347' ),
		'decided'    => array( 'check', '#1D7347' ),
		'rejected'   => array( 'x', '#B42318' ),
		'sent'       => array( 'send', '#2563A8' ),
		'link'       => array( 'link', '#2563A8' ),
		'client'     => array( 'person', '#2563A8' ),
		'signed'     => array( 'pen', '#7A3FB0' ),
		'confirmed'  => array( 'doc', '#7A3FB0' ),
		'hours'      => array( 'clock', '#B7791F' ),
		'invoice'    => array( 'money', '#0F766E' ),
		'assessment' => array( 'clip', '#C61A85' ),
		'won'        => array( 'star', '#C61A85' ),
		'viewed'     => array( 'eye', '#627583' ),
		'edit'       => array( 'pen', '#627583' ),
		'note'       => array( 'note', '#627583' ),
	);
	$i = isset( $k[ $kind ] ) ? $k[ $kind ] : $k['note'];
	return array( $i[1], '<svg viewBox="0 0 24 24" aria-hidden="true">' . $p[ $i[0] ] . '</svg>' );
}

function wos_app_lead_tab_log( $lead ) {
	$kinds  = wos_lead_log_kinds();
	$groups = array(
		'all'       => 'All',
		'decisions' => 'Decisions',
		'client'    => 'Client',
		'documents' => 'Documents',
		'hours'     => 'Hours',
		'other'     => 'Other',
	);
	?>
	<section class="wos-card wos-pad">
		<div class="wos-card-head wos-log-head">
			<h2>Log</h2>
			<?php if ( $lead->log ) : ?>
				<div class="wos-filter" role="group" aria-label="Show">
					<?php foreach ( $groups as $gk => $gl ) : ?>
						<button type="button" class="<?php echo 'all' === $gk ? 'is-on' : ''; ?>" data-log-filter="<?php echo esc_attr( $gk ); ?>" aria-pressed="<?php echo 'all' === $gk ? 'true' : 'false'; ?>"><?php echo esc_html( $gl ); ?></button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php if ( ! $lead->log ) : ?>
			<p class="wos-muted">Nothing yet.</p>
		<?php else : ?>
			<div class="wos-tl">
				<?php
				$day   = '';
				$today = substr( wos_now(), 0, 10 );
				foreach ( array_reverse( $lead->log ) as $e ) :
					$kind = wos_lead_log_kind( $e );
					list( $color, $svg ) = wos_app_log_icon( $kind );
					$d = substr( (string) $e['at'], 0, 10 );
					if ( $d !== $day ) :
						$day = $d;
						?>
						<div class="wos-tl-day"><?php echo esc_html( strtoupper( ( $d === $today ? 'Today · ' : '' ) . wos_app_short_date( $d ) ) ); ?></div>
					<?php endif; ?>
					<?php
					// "Ticked: NDA, if they share confidential data." reads better as its subject alone.
					$text  = preg_replace( '/^Ticked: ([^,.]+).*$/', '$1 ticked', (string) $e['text'] );
					$parts = explode( ': ', $text, 2 );
					$title = rtrim( $parts[0], '.' );
					$desc  = isset( $parts[1] ) ? $parts[1] : '';
					?>
					<div class="wos-tl-ev<?php echo in_array( $kind, array( 'approved', 'won' ), true ) ? ' is-big' : ''; ?>" style="--c: <?php echo esc_attr( $color ); ?>" data-group="<?php echo esc_attr( $kinds[ $kind ][1] ); ?>">
						<span class="wos-tl-ic"><?php echo $svg; // phpcs:ignore -- fixed markup. ?></span>
						<div class="wos-tl-w"><?php echo esc_html( $title ); ?><span class="wos-tl-t"><?php echo esc_html( $kinds[ $kind ][0] ); ?></span></div>
						<?php if ( '' !== $desc ) : ?><div class="wos-tl-d"><?php echo esc_html( $desc ); ?></div><?php endif; ?>
						<div class="wos-tl-m"><b><?php echo esc_html( substr( (string) $e['at'], 11, 5 ) ); ?></b><?php echo esc_html( $e['by'] ); ?></div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>
	<?php
}

/* ---------- Ask Claude panel ---------- */

function wos_app_chat_panel( $scope, $title, $suggest = array() ) {
	$has_key = (bool) wos_claude_key();
	if ( ! $has_key ) {
		return; // Ask Claude is optional; without a key the panel stays out of the way.
	}
	$history = wos_chat_history( 'chat:' . $scope, 20 );
	?>
	<section class="wos-chat" data-scope="<?php echo esc_attr( $scope ); ?>" data-url="<?php echo esc_url( rest_url( 'wos/v1/chat' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
		<div class="wos-chat-head"><span><?php echo esc_html( $title ); ?></span><span class="wos-chat-tag">Reads WOS data</span></div>
		<div class="wos-chat-log" aria-live="polite">
			<div class="wos-msg is-claude">Ask me anything about this. I read the decisions, leads and numbers in WOS, and say where each answer comes from.</div>
			<?php foreach ( $history as $m ) : ?>
				<?php if ( '' !== (string) $m->content ) : ?>
					<div class="wos-msg <?php echo 'user' === $m->role ? 'is-me' : 'is-claude'; ?>"><?php echo esc_html( $m->content ); ?></div>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
		<?php if ( $has_key ) : ?>
			<?php if ( $suggest && ! $history ) : ?>
				<div class="wos-chips">
					<?php foreach ( $suggest as $q ) : ?><button type="button" class="wos-chip"><?php echo esc_html( $q ); ?></button><?php endforeach; ?>
				</div>
			<?php endif; ?>
			<form class="wos-chat-form">
				<label class="screen-reader-text" for="q-<?php echo esc_attr( sanitize_key( $scope ) ); ?>">Your question</label>
				<textarea id="q-<?php echo esc_attr( sanitize_key( $scope ) ); ?>" rows="2" placeholder="Ask anything about this"></textarea>
				<button class="wos-btn" type="submit">Send</button>
			</form>
		<?php else : ?>
			<p class="wos-chat-off">Ask Claude is off until the API key is added in <a href="<?php echo esc_url( wos_app_url( 'settings' ) . '#claude' ); ?>">Settings</a>.</p>
		<?php endif; ?>
	</section>
	<?php
}
