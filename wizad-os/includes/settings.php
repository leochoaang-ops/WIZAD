<?php
defined( 'ABSPATH' ) || exit;

function wos_settings_fields() {
	return array(
		'business_name'   => array( 'Trading name', 'text' ),
		'owner_name'      => array( 'Owner', 'text' ),
		'address'         => array( 'Address on invoices', 'textarea' ),
		'email'           => array( 'Email on invoices', 'text' ),
		'website'         => array( 'Website', 'text' ),
		'invoice_prefix'  => array( 'Invoice prefix', 'text' ),
		'payment_details' => array( 'Payment instructions', 'textarea' ),
		'gst_registered'  => array( 'GST registered', 'checkbox' ),
		'gst_number'      => array( 'GST/HST number', 'text' ),
		'alert_email'     => array( 'Email for urgent alerts', 'text' ),
	);
}

function wos_render_settings() {
	wos_require_cap();
	$s = wp_parse_args( get_option( 'wos_settings', array() ), wos_settings_defaults() );
	?>
	<div class="wrap wos">
		<h1>WIZAD OS settings</h1>
		<?php wos_render_flash(); ?>
		<p>Rates, tax and warning levels are set in the app: <a href="<?php echo esc_url( wos_app_url( 'settings' ) ); ?>">Settings</a>.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="wos_settings_save">
			<?php wp_nonce_field( 'wos_settings_save' ); ?>
			<table class="form-table" role="presentation">
			<?php foreach ( wos_settings_fields() as $key => list( $label, $type ) ) : ?>
				<tr><th scope="row"><label for="wos-s-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td>
				<?php if ( 'textarea' === $type ) : ?>
					<textarea id="wos-s-<?php echo esc_attr( $key ); ?>" name="s[<?php echo esc_attr( $key ); ?>]" rows="3" class="large-text"><?php echo esc_textarea( $s[ $key ] ); ?></textarea>
				<?php elseif ( 'checkbox' === $type ) : ?>
					<input id="wos-s-<?php echo esc_attr( $key ); ?>" type="checkbox" name="s[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $s[ $key ], 1 ); ?>>
				<?php else : ?>
					<input id="wos-s-<?php echo esc_attr( $key ); ?>" type="<?php echo 'number' === $type ? 'number' : 'text'; ?>" step="0.01" name="s[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $s[ $key ] ); ?>" class="regular-text">
				<?php endif; ?>
				</td></tr>
			<?php endforeach; ?>
			</table>
			<p class="submit"><button class="button button-primary">Save settings</button></p>
		</form>

		<h2>Starter data</h2>
		<p>Loads WIZAD's current clients, deals, projects and board cards from the financial model (Oct 21, 2026). Amounts marked "placeholder" must be checked. It only runs when there are no clients yet.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="wos_seed">
			<?php wp_nonce_field( 'wos_seed' ); ?>
			<button class="button">Load starter data</button>
		</form>

		<h2>Backup</h2>
		<p>Export every table as CSV from its screen. For a full backup, use Bluehost's daily backup or a backup plugin such as UpdraftPlus (free) to Google Drive.</p>
	</div>
	<?php
}

add_action( 'admin_post_wos_settings_save', function () {
	wos_require_cap();
	check_admin_referer( 'wos_settings_save' );
	$in  = isset( $_POST['s'] ) && is_array( $_POST['s'] ) ? wp_unslash( $_POST['s'] ) : array(); // phpcs:ignore -- cleaned below.
	$out = array();
	foreach ( wos_settings_fields() as $key => list( $label, $type ) ) {
		$v = isset( $in[ $key ] ) ? $in[ $key ] : '';
		switch ( $type ) {
			case 'checkbox':
				$out[ $key ] = $v ? 1 : 0;
				break;
			case 'number':
				$out[ $key ] = (float) $v;
				break;
			case 'textarea':
				$out[ $key ] = sanitize_textarea_field( $v );
				break;
			default:
				$out[ $key ] = sanitize_text_field( $v );
		}
	}
	update_option( 'wos_settings', $out, false );
	wos_flash( 'Settings saved.' );
	wp_safe_redirect( wos_url( 'wos-settings' ) );
	exit;
} );
