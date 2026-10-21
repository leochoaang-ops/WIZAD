<?php
/**
 * The only template: a holding page with a sign-in button.
 */
defined( 'ABSPATH' ) || exit;
$signed_in = is_user_logged_in();
$target    = admin_url( 'admin.php?page=wizad-os' );
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title>WIZAD OS</title>
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<div class="hold">
	<main class="hold-main">
		<div class="hold-brand">
			<?php wizad_os_theme_mark(); ?>
			<div class="name">WIZAD <span>OS</span></div>
		</div>
		<h1>WIZAD's internal workspace</h1>
		<p class="hold-lede">Clients, projects, delivery and finance in one place. Access is for the WIZAD team only.</p>
		<a class="hold-btn" href="<?php echo esc_url( $signed_in ? $target : wp_login_url( $target ) ); ?>">
			<?php echo $signed_in ? 'Open WIZAD OS' : 'Sign in'; ?>
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
		</a>
		<p class="hold-note">
			<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
			<span>Private system protected by two-step sign-in. Sign-ins are logged. Looking for WIZAD? Visit <a href="https://wizad.ca">wizad.ca</a>.</span>
		</p>
	</main>
	<footer class="hold-foot">© <?php echo esc_html( wp_date( 'Y' ) ); ?> WIZAD</footer>
</div>
<?php wp_footer(); ?>
</body>
</html>
