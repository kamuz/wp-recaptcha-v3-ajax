<?php
/**
 * GeneratePress Child Theme Functions
 * reCAPTCHA v3 + AJAX Contact Form.
 *
 * @package GeneratePressChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly to protect the file.
}

// Define reCAPTCHA keys as constants for easier reuse.
define( 'RECAPTCHA_SITE_KEY', '6Lex41QrAAAAALEYoruQWzw3NPw3NjuFgTfYqzKm' );
define( 'RECAPTCHA_SECRET_KEY', '6Lex41QrAAAAAJgAEnHedWa0a4HQ_EhT6cfv0OO4' );

/**
 * Enqueue parent theme stylesheet.
 *
 * @return void
 */
function gp_child_enqueue_parent_style() {
	wp_enqueue_style(
		'generatepress-parent',
		get_template_directory_uri() . '/style.css',
		array(),
		filemtime( get_template_directory() . '/style.css' ) // Use file modification time for cache busting.
	);
}
add_action( 'wp_enqueue_scripts', 'gp_child_enqueue_parent_style' );

/**
 * Enqueue Google reCAPTCHA and AJAX contact form scripts on singular pages only.
 *
 * @return void
 */
function gp_child_enqueue_recaptcha_and_ajax_scripts() {
	if ( ! is_singular() ) {
		return; // Only load on single post/page views.
	}

	// Load Google reCAPTCHA API with the site key.
	wp_enqueue_script(
		'google-recaptcha',
		'https://www.google.com/recaptcha/api.js?render=' . RECAPTCHA_SITE_KEY,
		false,
		'1.0.0',
		true
	);

	// Load custom JavaScript for AJAX form submission, dependent on jQuery.
	wp_enqueue_script(
		'contact-form-ajax',
		get_stylesheet_directory_uri() . '/contact-form.js',
		array( 'jquery' ),
		filemtime( get_stylesheet_directory() . '/contact-form.js' ), // Cache busting by file time.
		true
	);

	// Pass variables (AJAX URL, site key, nonce) to JS.
	wp_localize_script(
		'contact-form-ajax',
		'cf_ajax_object',
		array(
			'ajax_url'           => admin_url( 'admin-ajax.php' ),
			'recaptcha_site_key' => RECAPTCHA_SITE_KEY,
			'nonce'              => wp_create_nonce( 'contact_form_nonce' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'gp_child_enqueue_recaptcha_and_ajax_scripts' );

/**
 * Handle AJAX contact form submission.
 *
 * Verifies nonce, validates form input, verifies reCAPTCHA token,
 * sends email to admin, and returns JSON response.
 *
 * @return void
 */
function handle_ajax_contact_form() {
	// Verify nonce for security.
	check_ajax_referer( 'contact_form_nonce', 'nonce' );

	$response = array(
		'success' => false,
		'errors'  => array(),
	);

	// Sanitize and get POST inputs.
	$name    = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
	$email   = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$message = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
	$token   = sanitize_text_field( wp_unslash( $_POST['recaptcha_token'] ?? '' ) );

	// Validate required fields.
	if ( ! $name ) {
		$response['errors'][] = 'Name is required.';
	}
	if ( ! $email || ! is_email( $email ) ) {
		$response['errors'][] = 'Valid email is required.';
	}
	if ( ! $message ) {
		$response['errors'][] = 'Message is required.';
	}

	// Get user IP address for reCAPTCHA verification.
	$remote_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

	// Verify reCAPTCHA response by sending POST request to Google.
	$verify = wp_remote_post(
		'https://www.google.com/recaptcha/api/siteverify',
		array(
			'body' => array(
				'secret'   => RECAPTCHA_SECRET_KEY,
				'response' => $token,
				'remoteip' => $remote_ip,
			),
		)
	);

	if ( is_wp_error( $verify ) ) {
		// Log error when HTTP request to reCAPTCHA fails.
		error_log( 'reCAPTCHA request failed: ' . $verify->get_error_message() );

		$response['errors'][] = 'reCAPTCHA request failed.';
	} else {
		// Decode JSON response from Google.
		$body = json_decode( wp_remote_retrieve_body( $verify ), true );

		if ( empty( $body['success'] ) || $body['score'] < 0.5 ) {
			// Log failed verification with score (or N/A if score missing).
			error_log( 'reCAPTCHA verification failed. Score: ' . ( $body['score'] ?? 'N/A' ) );

			$response['errors'][] = 'reCAPTCHA verification failed.';
		} else {
			// Log successful verification with score.
			error_log( 'reCAPTCHA verification succeeded. Score: ' . $body['score'] );
		}
	}

	if ( ! empty( $response['errors'] ) ) {
		wp_send_json( $response );
	}

	// Prepare and send email.
	$to      = get_option( 'admin_email' );
	$subject = 'Contact Form: ' . $name;
	$body    = "Name: $name\nEmail: $email\n\nMessage:\n$message";
	$headers = array( 'Reply-To: ' . $email );

	if ( wp_mail( $to, $subject, $body, $headers ) ) {
		$response['success'] = true;
	} else {
		$response['errors'][] = 'Failed to send message. Please try again.';
	}

	wp_send_json( $response );
}
add_action( 'wp_ajax_send_contact_form', 'handle_ajax_contact_form' );
add_action( 'wp_ajax_nopriv_send_contact_form', 'handle_ajax_contact_form' );

/**
 * Shortcode callback to output the contact form HTML.
 *
 * @return string HTML content of the contact form.
 */
function simple_contact_form_shortcode() {
	ob_start(); ?>
	<div class="simple-contact-form">
		<div class="form-response"></div>
		<form id="simple-contact-form">
			<div class="form-group">
				<label for="cf_name">Name *</label>
				<input type="text" id="cf_name" name="name">
			</div>

			<div class="form-group">
				<label for="cf_email">Email *</label>
				<input type="email" id="cf_email" name="email">
			</div>

			<div class="form-group">
				<label for="cf_message">Message *</label>
				<textarea id="cf_message" name="message" rows="5"></textarea>
			</div>

			<input type="hidden" name="recaptcha_token" id="recaptcha_token" value="">

			<p class="recaptcha-info">
				This site is protected by reCAPTCHA and the Google
				<a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Privacy Policy</a> and
				<a href="https://policies.google.com/terms" target="_blank" rel="noopener">Terms of Service</a> apply.
			</p>

			<div class="form-group">
				<button type="submit">Send Message</button>
			</div>
		</form>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'simple_contact_form', 'simple_contact_form_shortcode' );