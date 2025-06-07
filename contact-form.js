jQuery(document).ready(function ($) {
	const form = $('#simple-contact-form');
	const responseBox = $('.form-response');

	// Handle form submission
	form.on('submit', function (e) {
		e.preventDefault();

		// Run reCAPTCHA
		grecaptcha.ready(function () {
			grecaptcha.execute(cf_ajax_object.recaptcha_site_key, { action: 'submit' }).then(function (token) {
				const formData = {
					action: 'send_contact_form',
					nonce: cf_ajax_object.nonce,
					name: $('#cf_name').val(),
					email: $('#cf_email').val(),
					message: $('#cf_message').val(),
					recaptcha_token: token
				};

				// Send AJAX request
				$.post(cf_ajax_object.ajax_url, formData, function (response) {
					responseBox.empty().show();

					if (response.success) {
						// On success - reset form and show success message
						form[0].reset();
						responseBox
							.removeClass('error')
							.addClass('success')
							.html('<strong>Success!</strong> Your message has been sent successfully.');
					} else {
						// On error - show list of errors
						const errorList = '<ul><li>' + response.errors.join('</li><li>') + '</li></ul>';
						responseBox
							.removeClass('success')
							.addClass('error')
							.html('<strong>Errors:</strong>' + errorList);
						console.log(response);
					}
				});
			});
		});
	});
});
