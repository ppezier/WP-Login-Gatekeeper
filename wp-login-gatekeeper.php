<?php
/**
 * Plugin Name:       WP Login Gatekeeper
 * Plugin URI:        https://github.com/ppezier/WP-Login-Gatekeeper
 * Description:       Bring Cloudflare Turnstile protection to all native WordPress forms
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Patrick Pézier
 * Author URI:        https://patrick.pezier.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-login-gatekeeper
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Table of contents
 *
 * 1. Bootstrap           — settings include, constants, script enqueue
 * 2. Widget injection    — login_form, register_form, lostpassword_form, resetpass_form
 * 3. Login validation    — authenticate filter (priority 30)
 * 4. Register validation — registration_errors filter
 * 5. Lost-pw validation  — lostpassword_post action
 * 6. Reset-pw validation — validate_password_reset action
 * 7. API helper          — wplg_check_turnstile()
 */

require_once plugin_dir_path( __FILE__ ) . 'includes/settings.php';

define( 'WPLG_SITE_KEY', (string) get_option( 'wplg_site_key', '' ) );
define( 'WPLG_SECRET_KEY', (string) get_option( 'wplg_secret_key', '' ) );

// 1. Enqueue the Cloudflare Turnstile script on all wp-login.php pages.
add_action( 'login_enqueue_scripts', 'wplg_enqueue_turnstile_script' );

/**
 * Enqueues the Cloudflare Turnstile script via the WordPress script API.
 *
 * @since 1.0.0
 * @return void
 */
function wplg_enqueue_turnstile_script(): void {
	wp_enqueue_script(
		'cloudflare-turnstile',
		'https://challenges.cloudflare.com/turnstile/v0/api.js',
		array(),
		null,
		false
	);
	wp_script_add_data( 'cloudflare-turnstile', 'async', true );
	wp_script_add_data( 'cloudflare-turnstile', 'defer', true );
}

// 2. Inject the widget into all native WordPress forms.
add_action( 'login_form',        'wplg_turnstile_widget' );
add_action( 'register_form',     'wplg_turnstile_widget' );
add_action( 'lostpassword_form', 'wplg_turnstile_widget' );
add_action( 'resetpass_form',    'wplg_turnstile_widget' );

/**
 * Renders the Cloudflare Turnstile widget inside WordPress forms.
 *
 * @since 1.0.0
 * @return void
 */
function wplg_turnstile_widget(): void {
	printf(
		'<div style="margin: 12px 0; overflow: hidden;"><div class="cf-turnstile" data-sitekey="%s"></div></div>',
		esc_attr( WPLG_SITE_KEY )
	);
}

// 3. Verify on login form submission.
add_filter( 'authenticate', 'wplg_verify_turnstile_login', 30, 3 );

/**
 * Verifies the Turnstile token on login form submission.
 *
 * @since  1.0.0
 * @param  WP_User|WP_Error|null $user     Authenticated user, error, or null.
 * @param  string                $username Submitted username.
 * @param  string                $password Submitted password.
 * @return WP_User|WP_Error|null
 */
function wplg_verify_turnstile_login( $user, string $username, string $password ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is handled by WordPress core.
	$token = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : '';

	if ( empty( $token ) ) {
		return new WP_Error(
			'turnstile_missing',
			'<strong>' . esc_html__( 'Error', 'wp-login-gatekeeper' ) . '</strong>: ' . esc_html__( 'Please complete the verification.', 'wp-login-gatekeeper' )
		);
	}

	if ( ! wplg_check_turnstile( $token ) ) {
		return new WP_Error(
			'turnstile_failed',
			'<strong>' . esc_html__( 'Error', 'wp-login-gatekeeper' ) . '</strong>: ' . esc_html__( 'Turnstile verification failed. Please try again.', 'wp-login-gatekeeper' )
		);
	}

	return $user;
}

// 4. Verify on registration form submission.
add_filter( 'registration_errors', 'wplg_verify_turnstile_registration', 10, 3 );

/**
 * Verifies the Turnstile token on registration form submission.
 *
 * @since  1.0.0
 * @param  WP_Error $errors               WordPress error object.
 * @param  string   $sanitized_user_login  Sanitized username.
 * @param  string   $user_email            Submitted email address.
 * @return WP_Error
 */
function wplg_verify_turnstile_registration( WP_Error $errors, string $sanitized_user_login, string $user_email ): WP_Error {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is handled by WordPress core.
	$token = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : '';

	if ( empty( $token ) ) {
		$errors->add(
			'turnstile_missing',
			'<strong>' . esc_html__( 'Error', 'wp-login-gatekeeper' ) . '</strong>: ' . esc_html__( 'Please complete the verification.', 'wp-login-gatekeeper' )
		);
		return $errors;
	}

	if ( ! wplg_check_turnstile( $token ) ) {
		$errors->add(
			'turnstile_failed',
			'<strong>' . esc_html__( 'Error', 'wp-login-gatekeeper' ) . '</strong>: ' . esc_html__( 'Turnstile verification failed. Please try again.', 'wp-login-gatekeeper' )
		);
	}

	return $errors;
}

// 5. Verify on lost password form submission.
add_action( 'lostpassword_post', 'wplg_verify_turnstile_lostpassword' );

/**
 * Verifies the Turnstile token on lost password form submission.
 *
 * @since  1.0.0
 * @param  WP_Error $errors WordPress error object.
 * @return void
 */
function wplg_verify_turnstile_lostpassword( WP_Error $errors ): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is handled by WordPress core.
	$token = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : '';

	if ( empty( $token ) ) {
		$errors->add(
			'turnstile_missing',
			'<strong>' . esc_html__( 'Error', 'wp-login-gatekeeper' ) . '</strong>: ' . esc_html__( 'Please complete the verification.', 'wp-login-gatekeeper' )
		);
		return;
	}

	if ( ! wplg_check_turnstile( $token ) ) {
		$errors->add(
			'turnstile_failed',
			'<strong>' . esc_html__( 'Error', 'wp-login-gatekeeper' ) . '</strong>: ' . esc_html__( 'Turnstile verification failed. Please try again.', 'wp-login-gatekeeper' )
		);
	}
}

// 6. Verify on password reset form submission.
add_action( 'validate_password_reset', 'wplg_verify_turnstile_resetpassword', 10, 2 );

/**
 * Verifies the Turnstile token on password reset form submission.
 *
 * @since  1.0.0
 * @param  WP_Error        $errors WordPress error object.
 * @param  WP_User|WP_Error $user   User being reset.
 * @return void
 */
function wplg_verify_turnstile_resetpassword( WP_Error $errors, $user ): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is handled by WordPress core.
	$token = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : '';

	if ( empty( $token ) ) {
		$errors->add(
			'turnstile_missing',
			'<strong>' . esc_html__( 'Error', 'wp-login-gatekeeper' ) . '</strong>: ' . esc_html__( 'Please complete the verification.', 'wp-login-gatekeeper' )
		);
		return;
	}

	if ( ! wplg_check_turnstile( $token ) ) {
		$errors->add(
			'turnstile_failed',
			'<strong>' . esc_html__( 'Error', 'wp-login-gatekeeper' ) . '</strong>: ' . esc_html__( 'Turnstile verification failed. Please try again.', 'wp-login-gatekeeper' )
		);
	}
}

// 7. API helper

/**
 * Verifies the Turnstile token against the Cloudflare API.
 *
 * @since  1.0.0
 * @param  string $token Token returned by the Turnstile widget.
 * @return bool True if verification succeeds, false otherwise.
 */
function wplg_check_turnstile( string $token ): bool {
	$remote_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

	$response = wp_remote_post(
		'https://challenges.cloudflare.com/turnstile/v0/siteverify',
		array(
			'body' => array(
				'secret'   => WPLG_SECRET_KEY,
				'response' => $token,
				'remoteip' => $remote_ip,
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return false;
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	return is_array( $body ) && ! empty( $body['success'] );
}
