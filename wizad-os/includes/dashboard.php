<?php
/**
 * Home screen: what needs Leo, money, pipeline, GST tracker, security.
 */
defined( 'ABSPATH' ) || exit;

/** Income in CAD (before GST) over the current and previous three calendar quarters. */
function wos_gst_tracker() {
	global $wpdb;
	$m     = (int) wp_date( 'n' );
	$y     = (int) wp_date( 'Y' );
	$qm    = (int) ( floor( ( $m - 1 ) / 3 ) * 3 + 1 ); // first month of this quarter
	$start = wp_date( 'Y-m-d', mktime( 0, 0, 0, $qm - 9, 1, $y ) );
	$total = (float) $wpdb->get_var( $wpdb->prepare(
		'SELECT COALESCE(SUM(amount_cad - gst * fx_rate),0) FROM ' . wos_table( 'ledger' ) . " WHERE type = 'income' AND entry_date >= %s",
		$start
	) );
	return array( 'total' => $total, 'since' => $start, 'limit' => 30000, 'warn' => 25000 );
}

function wos_render_dashboard() {
	global $wpdb;
	wos_require_cap();
	$t     = wos_today();
	$year  = wp_date( 'Y' ) . '-01-01';
	$ct    = wos_table( 'cards' );
	$dt    = wos_table( 'deals' );
	$it    = wos_table( 'invoices' );
	$lt    = wos_table( 'ledger' );
	$clt   = wos_table( 'clients' );

	$blocked = $wpdb->get_results( "SELECT * FROM $ct WHERE status = 'blocked' ORDER BY blocked_since ASC" );
	$late_d  = $wpdb->get_results( $wpdb->prepare( "SELECT d.*, c.name AS client_name FROM $dt d LEFT JOIN $clt c ON c.id = d.client_id WHERE d.stage NOT IN ('won','lost') AND d.next_step_date IS NOT NULL AND d.next_step_date <= %s ORDER BY d.next_step_date", $t ) );
	$late_i  = $wpdb->get_results( $wpdb->prepare( "SELECT i.*, c.name AS client_name FROM $it i LEFT JOIN $clt c ON c.id = i.client_id WHERE i.status = 'sent' AND i.due_date < %s ORDER BY i.due_date", $t ) );
	$late_c  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $ct WHERE status IN ('todo','doing') AND due_date IS NOT NULL AND due_date < %s ORDER BY due_date", $t ) );

	$money = $wpdb->get_results( $wpdb->prepare( "SELECT type, SUM(amount_cad) total FROM $lt WHERE entry_date >= %s GROUP BY type", $year ), OBJECT_K );
	$inc   = isset( $money['income'] ) ? (float) $money['income']->total : 0;
	$exp   = isset( $money['expense'] ) ? (float) $money['expense']->total : 0;
	$owed  = 0;
	foreach ( $wpdb->get_results( "SELECT total, currency FROM $it WHERE status = 'sent'" ) as $r ) {
		$owed += $r->total * wos_fx( $r->currency );
	}

	$pipe = array();
	$pv   = 0;
	$pw   = 0;
	foreach ( $wpdb->get_results( "SELECT stage, value, currency, probability FROM $dt WHERE stage NOT IN ('won','lost')" ) as $d ) {
		$cad = $d->value * wos_fx( $d->currency );
		if ( ! isset( $pipe[ $d->stage ] ) ) {
			$pipe[ $d->stage ] = array( 'n' => 0, 'v' => 0, 'w' => 0 );
		}
		$pipe[ $d->stage ]['n']++;
		$pipe[ $d->stage ]['v'] += $cad;
		$pipe[ $d->stage ]['w'] += $cad * $d->probability / 100;
		$pv                     += $cad;
		$pw                     += $cad * $d->probability / 100;
	}
	$gst    = wos_gst_tracker();
	$gstpct = min( 100, $gst['total'] / $gst['limit'] * 100 );
	$needs  = count( $blocked ) + count( $late_d ) + count( $late_i ) + count( $late_c );
	?>
	<div class="wrap wos">
		<h1>WIZAD OS</h1>
		<?php wos_render_flash(); ?>
		<div class="wos-kpis">
			<div class="wos-kpi <?php echo $needs ? 'is-alert' : ''; ?>"><span>Needs Leo</span><strong><?php echo (int) $needs; ?></strong></div>
			<div class="wos-kpi"><span>Income <?php echo esc_html( wp_date( 'Y' ) ); ?></span><strong><?php echo esc_html( wos_money( $inc ) ); ?></strong></div>
			<div class="wos-kpi"><span>Expenses <?php echo esc_html( wp_date( 'Y' ) ); ?></span><strong><?php echo esc_html( wos_money( $exp ) ); ?></strong></div>
			<div class="wos-kpi"><span>Owed to WIZAD</span><strong><?php echo esc_html( wos_money( $owed ) ); ?></strong></div>
			<div class="wos-kpi"><span>Pipeline (weighted)</span><strong><?php echo esc_html( wos_money( $pw ) ); ?></strong></div>
		</div>

		<div class="wos-grid">
			<section class="wos-panel">
				<h2>Needs Leo</h2>
				<?php if ( ! $needs ) : ?><p class="wos-empty">Nothing waiting. Good.</p><?php endif; ?>
				<ul class="wos-list">
					<?php foreach ( $late_i as $i ) : $d = wos_days_between( $i->due_date ); ?>
						<li class="<?php echo $d >= 20 ? 'lvl-2' : 'lvl-1'; ?>"><a href="<?php echo esc_url( wos_url( 'wos-invoices', array( 'action' => 'edit', 'id' => $i->id ) ) ); ?>">Invoice <?php echo esc_html( $i->number ); ?> · <?php echo esc_html( $i->client_name ); ?></a> <span><?php echo (int) $d; ?> days overdue</span></li>
					<?php endforeach; ?>
					<?php foreach ( $blocked as $c ) : ?>
						<li class="lvl-1"><a href="<?php echo esc_url( wos_url( 'wos-board', array( 'card' => $c->id ) ) ); ?>">Blocked: <?php echo esc_html( $c->title ); ?></a> <span><?php echo esc_html( $c->blocker_note ); ?><?php echo $c->blocked_since ? ' · ' . (int) wos_days_between( $c->blocked_since ) . ' d' : ''; ?></span></li>
					<?php endforeach; ?>
					<?php foreach ( $late_d as $d ) : ?>
						<li class="lvl-1"><a href="<?php echo esc_url( wos_url( 'wos-deals', array( 'action' => 'edit', 'id' => $d->id ) ) ); ?>">Deal: <?php echo esc_html( $d->title ); ?></a> <span><?php echo esc_html( $d->next_step ); ?> · due <?php echo esc_html( wp_date( 'M j', strtotime( $d->next_step_date ) ) ); ?></span></li>
					<?php endforeach; ?>
					<?php foreach ( $late_c as $c ) : ?>
						<li class="lvl-1"><a href="<?php echo esc_url( wos_url( 'wos-board', array( 'card' => $c->id ) ) ); ?>">Late: <?php echo esc_html( $c->title ); ?></a> <span>due <?php echo esc_html( wp_date( 'M j', strtotime( $c->due_date ) ) ); ?></span></li>
					<?php endforeach; ?>
				</ul>
			</section>

			<section class="wos-panel">
				<h2>Pipeline</h2>
				<table class="widefat wos-table">
					<thead><tr><th>Stage</th><th>Deals</th><th>Value</th><th>Weighted</th></tr></thead>
					<tbody>
					<?php foreach ( wos_options( 'deal_stage' ) as $k => $label ) : if ( empty( $pipe[ $k ] ) ) { continue; } ?>
						<tr><td><?php echo esc_html( $label ); ?></td><td><?php echo (int) $pipe[ $k ]['n']; ?></td><td><?php echo esc_html( wos_money( $pipe[ $k ]['v'] ) ); ?></td><td><?php echo esc_html( wos_money( $pipe[ $k ]['w'] ) ); ?></td></tr>
					<?php endforeach; ?>
					<?php if ( ! $pipe ) : ?><tr><td colspan="4">No open deals.</td></tr><?php endif; ?>
					</tbody>
					<tfoot><tr><th>Total</th><th></th><th><?php echo esc_html( wos_money( $pv ) ); ?></th><th><?php echo esc_html( wos_money( $pw ) ); ?></th></tr></tfoot>
				</table>
				<p class="description">Values in CAD at the exchange rates in Settings.</p>
			</section>

			<section class="wos-panel">
				<h2>GST tracker</h2>
				<p><strong><?php echo esc_html( wos_money( $gst['total'] ) ); ?></strong> of income since <?php echo esc_html( wp_date( 'M j, Y', strtotime( $gst['since'] ) ) ); ?> (this quarter and the three before).</p>
				<div class="wos-meter <?php echo $gst['total'] >= $gst['warn'] ? 'is-alert' : ''; ?>" role="img" aria-label="<?php echo esc_attr( round( $gstpct ) . '% of the $30,000 small-supplier limit' ); ?>"><span style="width:<?php echo esc_attr( round( $gstpct, 1 ) ); ?>%"></span></div>
				<p class="description">
					<?php if ( wos_setting( 'gst_registered' ) ) : ?>
						WIZAD is GST-registered.
					<?php elseif ( $gst['total'] >= $gst['warn'] ) : ?>
						<strong>Over $25,000: plan GST registration now.</strong> Registration is required once taxable sales pass $30,000 in four consecutive calendar quarters or in one quarter; confirm the date with an accountant.
					<?php else : ?>
						Warning at $25,000; the small-supplier limit is $30,000. Paid invoices count when marked paid.
					<?php endif; ?>
				</p>
			</section>

			<section class="wos-panel">
				<h2>Security</h2>
				<?php
				$tf = wos_two_factor_status();
				$tf_text = array( 'on' => 'On for you', 'off' => 'Off: turn it on in your profile', 'missing' => 'Plugin not installed' );
				?>
				<ul class="wos-list">
					<li class="<?php echo 'on' === $tf ? '' : 'lvl-2'; ?>">Two-step sign-in <span><?php echo esc_html( $tf_text[ $tf ] ); ?></span></li>
					<li class="<?php echo is_ssl() ? '' : 'lvl-1'; ?>">HTTPS <span><?php echo is_ssl() ? 'On' : 'Off'; ?></span></li>
					<li>Search engines <span>Blocked</span></li>
				</ul>
				<h3>Recent sign-ins</h3>
				<ul class="wos-list wos-small">
					<?php foreach ( array_reverse( array_slice( (array) get_option( 'wos_login_log', array() ), -5 ) ) as $l ) : ?>
						<li class="<?php echo $l['ok'] ? '' : 'lvl-1'; ?>"><?php echo esc_html( $l['u'] ); ?> <span><?php echo esc_html( $l['ok'] ? 'OK' : 'Failed' ); ?> · <?php echo esc_html( $l['ip'] ); ?> · <?php echo esc_html( $l['t'] ); ?></span></li>
					<?php endforeach; ?>
				</ul>
			</section>
		</div>
	</div>
	<?php
}
