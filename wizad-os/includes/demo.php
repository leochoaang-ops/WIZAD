<?php
/**
 * Demo data for staging: sample leads at every Sprint 0 step and sample
 * decisions, so the whole flow can be tried. Never offered on production.
 * Only the records made here are tracked and removed by Clear.
 */
defined( 'ABSPATH' ) || exit;

/** Demo data is offered on the Bluehost staging copy and on local or staging environments only. */
function wos_demo_allowed() {
	return false !== strpos( home_url( '/' ), '/staging/' ) || in_array( wp_get_environment_type(), array( 'local', 'development', 'staging' ), true );
}

function wos_demo_ids() {
	$ids = get_option( 'wos_demo_ids', array() );
	return is_array( $ids ) ? $ids + array( 'leads' => array(), 'decisions' => array() ) : array( 'leads' => array(), 'decisions' => array() );
}

/** How many demo records are loaded (0 when none). */
function wos_demo_loaded() {
	$ids = wos_demo_ids();
	return count( $ids['leads'] ) + count( $ids['decisions'] );
}

function wos_demo_days_ago( $n, $time = '09:30:00' ) {
	return wp_date( 'Y-m-d', strtotime( wos_today() . ' -' . (int) $n . ' days' ) ) . ' ' . $time;
}

function wos_demo_intake( $v ) {
	$out = array();
	foreach ( $v as $k => $val ) {
		$out[ $k ] = array( 'v' => $val, 'src' => 'interview' );
	}
	return $out;
}

function wos_demo_load() {
	$ids = array( 'leads' => array(), 'decisions' => array() );
	$by  = 'Demo data';
	$log = function ( $text, $days ) use ( $by ) {
		return array( 'at' => wos_demo_days_ago( $days ), 'by' => $by, 'text' => $text );
	};

	$leads = array(
		array(
			'name' => 'Harbour Dental (demo)', 'contact_name' => 'Mia Chen', 'email' => 'mia@example.com', 'source' => 'LinkedIn post', 'owner' => 'Tau',
			'stage' => 'lead', 'scores' => array( 'fit' => 7, 'readiness' => 5, 'pay' => 7, 'expertise' => 8 ), 'value' => 2400, 'win' => 30,
			'answers' => array( 'How do enquiries reach you?: phone and a web form', 'Who answers them?: the front desk, when there is time' ),
			'checks' => array( 'assessed' => wos_today() ),
			'prelim' => array( 'see' => '', 'sprint' => '', 'low' => '', 'high' => '', 'next' => 'One follow-up call to check timing', 'status' => 'draft' ),
			'next_step' => 'Write the preliminary proposal', 'days' => 1,
		),
		array(
			'name' => 'Kits Movers (demo)', 'contact_name' => 'Raj Patel', 'email' => 'raj@example.com', 'source' => 'Referral', 'introducer' => 'Sao Chi', 'owner' => 'Tau',
			'stage' => 'lead', 'scores' => array( 'fit' => 9, 'readiness' => 8, 'pay' => 8, 'expertise' => 9 ), 'value' => 3000, 'win' => 50,
			'answers' => array( 'How do enquiries reach you?: phone, email and Facebook', 'What takes the most time?: quotes by hand' ),
			'checks' => array( 'assessed' => wos_demo_days_ago( 5, '' ), 'prelim' => wos_demo_days_ago( 3, '' ) ),
			'prelim' => array( 'see' => 'Enquiries arrive in three places and quotes are written by hand, so replies take two days.', 'sprint' => 'One enquiry inbox and a quote template that fills itself from the enquiry.', 'low' => 16, 'high' => 22, 'next' => 'Book Sprint 0: a discovery call', 'status' => 'sent', 'sent_at' => wos_demo_days_ago( 3 ) ),
			'next_step' => 'They said yes? Start Sprint 0', 'days' => 5,
		),
		array(
			'name' => 'Northshore Renovations (demo)', 'contact_name' => 'Ben Ross', 'email' => 'ben@example.com', 'source' => 'Google', 'owner' => 'Tau',
			'stage' => 's0', 'scores' => array( 'fit' => 8, 'readiness' => 7, 'pay' => 8, 'expertise' => 9 ), 's0_hours' => 1.5, 'value' => 4800, 'win' => 60,
			'checks' => array( 'assessed' => wos_demo_days_ago( 9, '' ), 'prelim' => wos_demo_days_ago( 7, '' ) ),
			'prelim' => array( 'see' => 'Leads come from Google but nobody tracks which ones turn into jobs.', 'sprint' => 'A simple lead tracker and follow-up reminders.', 'low' => 20, 'high' => 28, 'next' => 'Discovery call', 'status' => 'sent', 'sent_at' => wos_demo_days_ago( 7 ) ),
			'intake' => array( 'requested' => wos_demo_days_ago( 1 ), 'values' => wos_demo_intake( array( 'business' => 'Home renovations, 6 staff' ) ) ),
			'token' => true, 'next_step' => 'Waiting for the client to fill the online intake', 'days' => 9,
		),
		array(
			'name' => 'Cedar Physio (demo)', 'contact_name' => 'Ana Lima', 'email' => 'ana@example.com', 'source' => 'Website assessment', 'owner' => 'Tau',
			'stage' => 's0', 'scores' => array( 'fit' => 8, 'readiness' => 8, 'pay' => 7, 'expertise' => 8 ), 's0_hours' => 4.75, 'value' => 3600, 'win' => 70,
			'checks' => array( 'assessed' => wos_demo_days_ago( 14, '' ), 'prelim' => wos_demo_days_ago( 12, '' ), 'intake' => wos_demo_days_ago( 4, '' ) ),
			'prelim' => array( 'see' => 'Bookings are online, but reminders and follow-ups are by phone.', 'sprint' => 'Automatic reminders and a rebooking message after each visit.', 'low' => 18, 'high' => 24, 'next' => 'Discovery call', 'status' => 'sent', 'sent_at' => wos_demo_days_ago( 12 ) ),
			'intake' => array( 'done_at' => wos_demo_days_ago( 4 ), 'values' => wos_demo_intake( array( 'business' => 'Physiotherapy clinic, 2 locations', 'goal' => 'Fewer no-shows and more rebookings', 'tools' => 'Jane App, Gmail, Instagram', 'admin_who' => 'Front desk (2 people)', 'maturity' => '3', 'b_enq' => '40', 'b_reply' => '1', 'b_admin' => '12', 'b_reach' => '2,500', 'b_won' => '90' ) ) ),
			'summary' => array( 'heard' => 'Physiotherapy clinic with 2 locations. Tools today: Jane App, Gmail, Instagram. Admin is done by the front desk.', 'goal' => 'Fewer no-shows and more rebookings.', 'today' => 'About 40 enquiries a month, reply in 1 day, 12 h of admin a week.', 'first' => 'Automatic reminders and a rebooking message after each visit.', 'out' => 'Website redesign and ads.', 'status' => 'sent', 'sent_at' => wos_demo_days_ago( 1 ) ),
			'token' => true, 'next_step' => 'Waiting for the client to confirm the summary', 'days' => 14,
		),
		array(
			'name' => 'Fraser Landscaping (demo)', 'contact_name' => 'Tom Hall', 'email' => 'tom@example.com', 'source' => 'Referral', 'introducer' => 'BNI', 'owner' => 'Tau',
			'contacts' => array( array( 'name' => 'Lisa Hall', 'role' => 'Finance / billing', 'email' => 'accounts@example.com', 'phone' => '604 555 0142' ), array( 'name' => 'Marco Diaz', 'role' => 'Assistant', 'email' => 'marco@example.com', 'phone' => '' ) ),
			'stage' => 's0', 'scores' => array( 'fit' => 9, 'readiness' => 9, 'pay' => 8, 'expertise' => 8 ), 's0_hours' => 6.5, 'value' => 4200, 'win' => 80,
			'checks' => array( 'assessed' => wos_demo_days_ago( 20, '' ), 'prelim' => wos_demo_days_ago( 18, '' ), 'intake' => wos_demo_days_ago( 10, '' ), 'nda' => wos_demo_days_ago( 10, '' ), 'summary' => wos_demo_days_ago( 2, '' ) ),
			'prelim' => array( 'see' => 'Quotes take a week; seasonal crews are booked on a whiteboard.', 'sprint' => 'Online quote requests and a shared crew calendar.', 'low' => 20, 'high' => 26, 'next' => 'Discovery call', 'status' => 'sent', 'sent_at' => wos_demo_days_ago( 18 ) ),
			'intake' => array( 'done_at' => wos_demo_days_ago( 10 ), 'values' => wos_demo_intake( array( 'business' => 'Landscaping, 12 seasonal staff', 'goal' => 'Quotes out in 2 days, not 7', 'tools' => 'Excel, paper, Gmail', 'admin_who' => 'The owner, evenings', 'maturity' => '2', 'b_enq' => '25', 'b_reply' => '7', 'b_admin' => '15', 'b_reach' => '800', 'b_won' => '30' ) ) ),
			'summary' => array( 'heard' => 'Landscaping, 12 seasonal staff. Tools today: Excel, paper, Gmail. Admin is done by the owner, evenings.', 'goal' => 'Quotes out in 2 days, not 7.', 'today' => 'About 25 enquiries a month, reply in 7 days, 15 h of admin a week.', 'first' => 'Online quote requests and a shared crew calendar.', 'out' => 'Accounting and payroll.', 'status' => 'confirmed', 'sent_at' => wos_demo_days_ago( 4 ), 'confirmed_at' => wos_demo_days_ago( 2 ), 'confirmed_by' => 'Tom Hall' ),
			'token' => true, 'next_step' => 'Leo approves the price', 'days' => 20,
		),
		array(
			'name' => 'Granville Bakery (demo)', 'contact_name' => 'Sue Park', 'source' => 'Website assessment', 'owner' => 'Tau',
			'stage' => 'lost', 'scores' => array( 'fit' => 3, 'readiness' => 2, 'pay' => 3, 'expertise' => 4 ),
			'checks' => array( 'assessed' => wos_demo_days_ago( 6, '' ) ),
			'next_step' => 'Referred on to a bookkeeping app partner', 'days' => 6,
		),
	);

	$made = array();
	foreach ( $leads as $l ) {
		$days  = $l['days'];
		$token = ! empty( $l['token'] );
		unset( $l['days'], $l['token'] );
		$l['level'] = wos_lead_level( $l['scores'] );
		$l['log']   = array( $log( 'Assessment received: ' . wos_lead_total( $l['scores'] ) . '/40, level ' . $l['level'] . '.', $days ) );
		$l['checks'] = array_map( function ( $v ) { return trim( $v ); }, $l['checks'] );
		$id = wos_lead_save( 0, $l );
		global $wpdb;
		$wpdb->update( wos_table( 'leads' ), array( 'created_at' => wos_demo_days_ago( $days ) ), array( 'id' => $id ) );
		if ( $token ) {
			wos_lead_token( wos_lead_get( $id ) );
		}
		$ids['leads'][]   = $id;
		$made[ $l['name'] ] = wos_lead_get( $id );
	}

	$fraser = $made['Fraser Landscaping (demo)'];
	$rate   = wos_role_cost( 'lead_consultant' );
	$ids['decisions'][] = wos_decision_create( array(
		'area'        => 'Clients · Tau · ' . $fraser->name,
		'question'    => 'Which price do we send ' . $fraser->name . '?',
		'context'     => 'First sprint: ' . $fraser->summary['first'],
		'why_now'     => 'Spring bookings start in 3 weeks. Tom confirmed the summary and asked for a price this week.',
		'numbers'     => '24 h estimated · allocated cost ' . wos_money( 24 * $rate ) . ' · target margin ' . wos_num( 'margin_target' ) . '% · minimum ' . wos_num( 'margin_min' ) . '%',
		'read_first'  => 'Lead file | ' . wos_app_url( 'leads/' . $fraser->id ) . "\nDiscovery summary | " . wos_app_url( 'leads/' . $fraser->id, array( 'tab' => 'summary' ) ),
		'options'     => array(
			array( 'label' => 'Fixed $2,800 for the sprint', 'result' => 'Clear for them; good margin', 'risk' => 'Overrun is ours', 'margin' => '59%' ),
			array( 'label' => 'Fixed $2,400 for the sprint', 'result' => 'Easier yes before spring', 'risk' => 'Below the target margin', 'margin' => '52%' ),
			array( 'label' => 'Hourly, capped at 26 h', 'result' => 'No overrun risk for us', 'risk' => 'Harder to say yes to', 'margin' => '55% to 60%' ),
		),
		'recommended' => 0,
		'due_date'    => wp_date( 'Y-m-d', strtotime( wos_today() . ' +3 days' ) ),
		'lead_id'     => $fraser->id,
		'kind'        => 'price',
		'added_by'    => 'Tau',
		'created_at'  => wos_demo_days_ago( 1 ),
	) );
	$ids['decisions'][] = wos_decision_create( array(
		'area'        => 'Clients · Tau · ' . $fraser->name,
		'question'    => $fraser->name . ' passed the Sprint 0 cap. Keep going unpaid, or offer a paid sprint?',
		'context'     => 'Sprint 0 is unpaid discovery with a cap of ' . wos_num( 's0_cap' ) . ' h. ' . $fraser->name . ' is now at 6.5 h.',
		'why_now'     => 'More unpaid hours cost WIZAD money; stopping too early cuts discovery short.',
		'numbers'     => '6.5 h used of ' . wos_num( 's0_cap' ) . ' h. Cost so far about ' . wos_money( 6.5 * $rate ) . ' at the Lead Consultant cost rate.',
		'read_first'  => 'Lead file | ' . wos_app_url( 'leads/' . $fraser->id ),
		'options'     => array(
			array( 'label' => 'Finish discovery unpaid, up to 2 more hours', 'result' => 'Keeps the relationship warm', 'risk' => 'More unpaid time' ),
			array( 'label' => 'Offer the rest as a small paid sprint', 'result' => 'Hours are paid from here', 'risk' => 'They may pause' ),
			array( 'label' => 'Stop and send the summary as it is', 'result' => 'No more unpaid time', 'risk' => 'Summary may be thin' ),
		),
		'recommended' => 0,
		'lead_id'     => $fraser->id,
		'kind'        => 's0_cap',
		'added_by'    => 'WIZAD OS',
		'created_at'  => wos_demo_days_ago( 3 ),
	) );
	$ids['decisions'][] = wos_decision_create( array(
		'area'        => 'Marketing · Hana',
		'question'    => 'Do we run the Connected Tools Check as a LinkedIn ad in November?',
		'context'     => 'The assessment brought 4 leads in October from organic posts.',
		'why_now'     => 'The November budget closes on Friday.',
		'numbers'     => 'Test budget $300 · 4 leads in October · 1 lead is worth about $3,000',
		'read_first'  => 'October content report | https://os.wizad.ca/',
		'options'     => array(
			array( 'label' => 'Run a $300 test for 2 weeks', 'result' => 'We learn the cost per lead', 'risk' => '$300 may bring nothing', 'margin' => 'not set' ),
			array( 'label' => 'Stay organic for now', 'result' => 'No spend', 'risk' => 'Slower pipeline', 'margin' => 'not set' ),
		),
		'recommended' => 0,
		'due_date'    => wp_date( 'Y-m-d', strtotime( wos_today() . ' +5 days' ) ),
		'added_by'    => 'Hana',
		'created_at'  => wos_demo_days_ago( 6 ),
	) );
	$ids['decisions'] = array_values( array_filter( array_map( function ( $v ) { return is_wp_error( $v ) ? 0 : (int) $v; }, $ids['decisions'] ) ) );
	update_option( 'wos_demo_ids', $ids, false );
	return $ids;
}

function wos_demo_clear() {
	global $wpdb;
	$ids = wos_demo_ids();
	// Decisions made from demo leads after loading (price, cap) go too.
	foreach ( $ids['leads'] as $lid ) {
		$lead = wos_lead_get( $lid );
		if ( $lead && $lead->client_id ) {
			$wpdb->delete( wos_table( 'clients' ), array( 'id' => (int) $lead->client_id ) );
		}
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . wos_table( 'decisions' ) . ' WHERE lead_id = %d', $lid ) ) as $did ) {
			$ids['decisions'][] = (int) $did;
		}
		$wpdb->delete( wos_table( 'leads' ), array( 'id' => (int) $lid ) );
		$wpdb->delete( wos_table( 'chats' ), array( 'scope' => 'chat:lead:' . (int) $lid ) );
	}
	foreach ( array_unique( $ids['decisions'] ) as $did ) {
		$wpdb->delete( wos_table( 'decisions' ), array( 'id' => (int) $did ) );
		$wpdb->delete( wos_table( 'chats' ), array( 'scope' => 'chat:decision:' . (int) $did ) );
	}
	delete_option( 'wos_demo_ids' );
}

foreach ( array( 'wos_demo_load', 'wos_demo_clear' ) as $wos_demo_action ) {
	add_action( 'admin_post_' . $wos_demo_action, function () use ( $wos_demo_action ) {
		wos_require_cap();
		check_admin_referer( 'wos_demo' );
		if ( ! wos_demo_allowed() || ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Demo data is only for staging.', 403 );
		}
		if ( 'wos_demo_load' === $wos_demo_action ) {
			if ( wos_demo_loaded() ) {
				wos_flash( 'Demo data is already loaded.', 'warning' );
			} else {
				wos_demo_load();
				wos_flash( 'Demo data loaded: 6 leads and 3 decisions. Start on Find clients.' );
			}
		} else {
			wos_demo_clear();
			wos_flash( 'Demo data removed.' );
		}
		wp_safe_redirect( wos_app_url( 'settings' ) . '#demo' );
		exit;
	} );
}
