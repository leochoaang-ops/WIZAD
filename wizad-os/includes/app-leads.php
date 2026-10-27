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
	if ( $lost ) {
		echo '<section class="wos-card wos-pad"><h2>Not now</h2>';
		foreach ( $lost as $l ) {
			printf( '<div class="wos-line"><a href="%s">%s</a><span class="wos-muted">%s</span></div>', esc_url( wos_app_url( 'leads/' . $l->id ) ), esc_html( $l->name ), esc_html( $l->next_step ) );
		}
		echo '</section>';
	}
	if ( ! $leads ) {
		echo '<p class="wos-muted">No leads yet. Add one from an assessment with <a href="' . esc_url( wos_app_url( 'leads', array( 'tab' => 'new' ) ) ) . '">+ New assessment</a>.</p>';
	}
}

function wos_app_lead_assessments( $leads ) {
	$with = array_filter( $leads, function ( $l ) { return '' !== $l->level; } );
	echo '<section class="wos-card wos-pad"><h2>Assessments received</h2><p class="wos-muted wos-small">Newest first. Each one became a lead with its score and level.</p>';
	if ( ! $with ) {
		echo '<p class="wos-muted">None yet.</p>';
	}
	foreach ( $with as $l ) {
		$st = isset( $l->prelim['status'] ) && 'sent' === $l->prelim['status'] ? 'Proposal sent' : 'Proposal draft';
		printf(
			'<div class="wos-line"><span><a href="%s"><strong>%s</strong></a><br><span class="wos-muted wos-small">%s · %s · %d/40</span></span><span class="wos-card-pills">%s%s</span></div>',
			esc_url( wos_app_url( 'leads/' . $l->id, array( 'tab' => 'proposal' ) ) ),
			esc_html( $l->name ),
			esc_html( substr( (string) $l->created_at, 0, 10 ) ),
			esc_html( $l->source ? $l->source : 'source not set' ),
			(int) wos_lead_total( $l->scores ),
			wos_app_level_pill( $l->level ), // phpcs:ignore -- escaped inside.
			wos_app_lead_pill( $st, 'sent' === ( isset( $l->prelim['status'] ) ? $l->prelim['status'] : '' ) ? 'green' : 'grey' ) // phpcs:ignore -- escaped inside.
		);
	}
	echo '</section>';
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
	$stages = wos_lead_stages();
	?>
	<div class="wos-head">
		<a href="<?php echo esc_url( wos_app_url( 'leads' ) ); ?>" class="wos-small">Find clients</a>
		<div class="wos-head-row">
			<h1><?php echo esc_html( $lead->name ); ?></h1>
			<span class="wos-card-pills"><?php echo wos_app_lead_pill( $stages[ $lead->stage ][0], 'grey' ) . wos_app_level_pill( $lead->level ) . ( 's0' === $lead->stage ? wos_app_hours_pill( $lead ) : '' ); // phpcs:ignore -- escaped inside. ?></span>
		</div>
		<p class="wos-muted"><?php echo esc_html( trim( implode( ' · ', array_filter( array( $lead->contact_name, $lead->source, $lead->owner ? 'owner: ' . $lead->owner : '' ) ) ) ) ); ?></p>
	</div>
	<div class="wos-tabs">
		<?php foreach ( $tabs as $k => $label ) : ?>
			<a class="<?php echo $k === $tab ? 'is-on' : ''; ?>" href="<?php echo esc_url( wos_app_url( 'leads/' . $lead->id, 'overview' === $k ? array() : array( 'tab' => $k ) ) ); ?>"><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
	</div>
	<?php
	$fn = 'wos_app_lead_tab_' . $tab;
	$fn( $lead );
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
			'Email'  => $lead->email ? '<a href="mailto:' . esc_attr( $lead->email ) . '">' . esc_html( $lead->email ) . '</a>' : $val( '' ),
			'Phone'  => $val( $lead->phone ),
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
						<?php foreach ( $lead->contacts as $c ) : ?>
							<div class="wos-person">
								<strong><?php echo esc_html( $c['name'] ); ?></strong>
								<span class="wos-pill is-grey"><?php echo esc_html( $c['role'] ); ?></span>
								<span class="wos-small"><?php echo $c['email'] ? '<a href="mailto:' . esc_attr( $c['email'] ) . '">' . esc_html( $c['email'] ) . '</a>' : ''; ?><?php echo $c['email'] && $c['phone'] ? ' · ' : ''; ?><?php echo esc_html( $c['phone'] ); ?></span>
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
						<label class="wos-field"><span>Email</span><input type="text" name="email" value="<?php echo esc_attr( $lead->email ); ?>"></label>
						<label class="wos-field"><span>Phone</span><input type="text" name="phone" value="<?php echo esc_attr( $lead->phone ); ?>"></label>
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
							<label class="wos-field"><span>Email</span><input type="text" name="contacts[<?php echo (int) $i; ?>][email]" value="<?php echo esc_attr( $c['email'] ); ?>"></label>
							<label class="wos-field"><span>Phone</span><input type="text" name="contacts[<?php echo (int) $i; ?>][phone]" value="<?php echo esc_attr( $c['phone'] ); ?>"></label>
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
	$srcs    = array( 'interview' => 'Interview', 'client' => 'Client-filled', 'assessment' => 'From assessment' );
	$done    = ! empty( $lead->checks['intake'] );
	$request = ! empty( $lead->intake['requested'] );
	?>
	<?php if ( $request ) : ?>
		<section class="wos-card wos-pad">
			<h2>Online intake for the client</h2>
			<?php if ( ! empty( $lead->intake['client_at'] ) ) : ?>
				<p class="wos-ok-box">The client filled it in on <?php echo esc_html( substr( $lead->intake['client_at'], 0, 10 ) ); ?>. Their answers are below, marked Client-filled.</p>
			<?php else : ?>
				<p class="wos-muted">Send this link to the client. It opens without signing in and shows only the intake questions.</p>
				<input class="wos-copy" type="text" readonly value="<?php echo esc_attr( wos_lead_client_url( $lead ) ); ?>" onclick="this.select()">
			<?php endif; ?>
		</section>
	<?php endif; ?>
	<?php wos_app_lead_form_open( $lead, 'wos_lead_intake', '' ); ?>
	<div class="wos-row wos-top">
		<?php foreach ( array( 'profile' => 'As-is profile', 'baseline' => 'Baseline: 5 numbers' ) as $g => $title ) : ?>
			<section class="wos-card wos-pad wos-col-main">
				<h2><?php echo esc_html( $title ); ?></h2>
				<?php if ( 'baseline' === $g ) : ?><p class="wos-muted wos-small">Measured again at 30, 60 and 90 days.</p><?php endif; ?>
				<?php foreach ( wos_lead_intake_fields() as $k => $f ) : ?>
					<?php
					if ( $f[1] !== $g ) {
						continue;
					}
					$v   = isset( $vals[ $k ]['v'] ) ? $vals[ $k ]['v'] : '';
					$src = isset( $vals[ $k ]['src'] ) ? $vals[ $k ]['src'] : 'interview';
					?>
					<div class="wos-setting">
						<span><?php echo esc_html( $f[0] ); ?></span>
						<input type="text" name="f[<?php echo esc_attr( $k ); ?>][v]" value="<?php echo esc_attr( $v ); ?>" aria-label="<?php echo esc_attr( $f[0] ); ?>">
						<select name="f[<?php echo esc_attr( $k ); ?>][src]" aria-label="<?php echo esc_attr( $f[0] . ' source' ); ?>">
							<?php foreach ( $srcs as $sk => $sl ) : ?><option value="<?php echo esc_attr( $sk ); ?>" <?php selected( $sk, $src ); ?>><?php echo esc_html( $sl ); ?></option><?php endforeach; ?>
						</select>
					</div>
				<?php endforeach; ?>
			</section>
		<?php endforeach; ?>
	</div>
	<div class="wos-actions">
		<button class="wos-btn is-ghost" type="submit" name="do" value="save">Save</button>
		<?php if ( ! $request ) : ?><button class="wos-btn is-ghost" type="submit" name="do" value="online">Let the client fill it online</button><?php endif; ?>
		<?php if ( ! $done ) : ?><button class="wos-btn" type="submit" name="do" value="complete">Intake complete</button><?php else : ?><span class="wos-pill is-green">Complete</span><?php endif; ?>
	</div>
	</form>
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

function wos_app_lead_tab_log( $lead ) {
	echo '<section class="wos-card wos-pad"><h2>Log</h2>';
	if ( ! $lead->log ) {
		echo '<p class="wos-muted">Nothing yet.</p>';
	}
	foreach ( array_reverse( $lead->log ) as $e ) {
		printf( '<div class="wos-line"><span>%s</span><span class="wos-muted wos-small">%s · %s</span></div>', esc_html( $e['text'] ), esc_html( $e['by'] ), esc_html( substr( $e['at'], 0, 16 ) ) );
	}
	echo '</section>';
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
