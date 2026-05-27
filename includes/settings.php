<?php
/**
 * Admin settings page — Cloudflare Turnstile API keys.
 *
 * Registers a settings page under Settings > WP Login Gatekeeper and stores
 * the two Turnstile keys in wp_options via the WordPress Settings API.
 *
 * @package WP_Login_Gatekeeper
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Add a "Settings" link on the Plugins list page.
add_filter( 'plugin_action_links_wp-login-gatekeeper/wp-login-gatekeeper.php', 'wplg_add_settings_link' );

/**
 * Prepends a "Settings" action link to the plugin row on the Plugins page.
 *
 * @since  1.0.0
 * @param  string[] $links Existing action links.
 * @return string[]
 */
function wplg_add_settings_link( array $links ): array {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		admin_url( 'options-general.php?page=wp-login-gatekeeper' ),
		esc_html__( 'Settings', 'wp-login-gatekeeper' )
	);
	array_unshift( $links, $settings_link );
	return $links;
}

// Register the settings page.
add_action( 'admin_menu', 'wplg_register_settings_page' );

/**
 * Adds a submenu page under the Settings menu.
 *
 * @since  1.0.0
 * @return void
 */
function wplg_register_settings_page(): void {
	add_options_page(
		__( 'WP Login Gatekeeper', 'wp-login-gatekeeper' ),
		__( 'WP Login Gatekeeper', 'wp-login-gatekeeper' ),
		'manage_options',
		'wp-login-gatekeeper',
		'wplg_render_settings_page'
	);
}

// Register settings, sections and fields.
add_action( 'admin_init', 'wplg_register_settings' );

/**
 * Registers the two Turnstile option fields with the Settings API.
 *
 * @since  1.0.0
 * @return void
 */
function wplg_register_settings(): void {
	register_setting(
		'wplg_settings',
		'wplg_site_key',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		)
	);

	register_setting(
		'wplg_settings',
		'wplg_secret_key',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		)
	);

	add_settings_section(
		'wplg_turnstile_keys',
		__( 'Cloudflare Turnstile Keys', 'wp-login-gatekeeper' ),
		'__return_false',
		'wp-login-gatekeeper'
	);

	add_settings_field(
		'wplg_site_key',
		__( 'Site Key', 'wp-login-gatekeeper' ),
		'wplg_render_site_key_field',
		'wp-login-gatekeeper',
		'wplg_turnstile_keys'
	);

	add_settings_field(
		'wplg_secret_key',
		__( 'Secret Key', 'wp-login-gatekeeper' ),
		'wplg_render_secret_key_field',
		'wp-login-gatekeeper',
		'wplg_turnstile_keys'
	);
}

/**
 * Renders the Site Key input field.
 *
 * @since  1.0.0
 * @return void
 */
function wplg_render_site_key_field(): void {
	printf(
		'<input type="text" id="wplg_site_key" name="wplg_site_key" value="%s" class="regular-text" />',
		esc_attr( (string) get_option( 'wplg_site_key', '' ) )
	);
}

/**
 * Renders the Secret Key input field.
 *
 * @since  1.0.0
 * @return void
 */
function wplg_render_secret_key_field(): void {
	printf(
		'<input type="password" id="wplg_secret_key" name="wplg_secret_key" value="%s" class="regular-text" autocomplete="off" />',
		esc_attr( (string) get_option( 'wplg_secret_key', '' ) )
	);
}

/**
 * Renders the full settings page.
 *
 * @since  1.0.0
 * @return void
 */
function wplg_render_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<p>
			<?php
			printf(
				/* translators: %s: Cloudflare Turnstile dashboard URL */
				esc_html__( 'Get your keys from the %s.', 'wp-login-gatekeeper' ),
				'<a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener noreferrer">'
				. esc_html__( 'Cloudflare Turnstile dashboard', 'wp-login-gatekeeper' )
				. '</a>'
			);
			?>
		</p>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'wplg_settings' );
			do_settings_sections( 'wp-login-gatekeeper' );
			submit_button();
			?>
		</form>
	</div>
	<?php
}
