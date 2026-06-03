<?php
/**
 * Friendly explanations for mail errors.
 *
 * @package StoreMailGuardian
 */

namespace SMG;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts raw mail errors into practical guidance.
 */
class Error_Guidance {
	/**
	 * Get guidance for a raw mail error.
	 *
	 * @param string $error_message Raw error message.
	 * @return array
	 */
	public function get_guidance( $error_message ) {
		if ( $this->is_localhost_sender_error( $error_message ) ) {
			return array(
				'title'       => __( 'Invalid sender email address', 'store-mail-guardian' ),
				'explanation' => __( 'WordPress is trying to send emails using wordpress@localhost as the From address. This usually happens in local WordPress installations or sites without a proper mail sender configuration. Real email providers will reject this sender address.', 'store-mail-guardian' ),
				'actions'     => array(
					__( 'Configure a valid From Email using an SMTP plugin.', 'store-mail-guardian' ),
					__( 'Use an email from your real domain, for example info@yourdomain.com.', 'store-mail-guardian' ),
					__( 'Install and configure WP Mail SMTP, FluentSMTP, Post SMTP, or Easy WP SMTP.', 'store-mail-guardian' ),
					__( 'For local development, use MailHog, Mailpit, or a local SMTP testing tool.', 'store-mail-guardian' ),
				),
			);
		}

		if ( '' !== trim( $error_message ) ) {
			return array(
				'title'       => __( 'Mail transport reported an error', 'store-mail-guardian' ),
				'explanation' => __( 'WordPress could not hand the message to the configured mail transport. The raw error can help identify whether the problem is sender configuration, SMTP authentication, hosting restrictions, or another mail setup issue.', 'store-mail-guardian' ),
				'actions'     => array(
					__( 'Confirm that the From Email is valid and uses your real domain.', 'store-mail-guardian' ),
					__( 'Check your SMTP plugin settings and authentication credentials.', 'store-mail-guardian' ),
					__( 'Send another test after updating the mail configuration.', 'store-mail-guardian' ),
				),
			);
		}

		return array(
			'title'       => __( 'No detailed error was reported', 'store-mail-guardian' ),
			'explanation' => __( 'WordPress reported that the test email failed, but no detailed error message was available.', 'store-mail-guardian' ),
			'actions'     => array(
				__( 'Configure a dedicated SMTP plugin and send another test.', 'store-mail-guardian' ),
				__( 'Check the WordPress debug log or hosting mail logs for more detail.', 'store-mail-guardian' ),
			),
		);
	}

	/**
	 * Check for the known localhost From address failure.
	 *
	 * @param string $error_message Raw error message.
	 * @return bool
	 */
	private function is_localhost_sender_error( $error_message ) {
		$error_message = strtolower( $error_message );

		return false !== strpos( $error_message, 'invalid address' )
			&& false !== strpos( $error_message, 'from' )
			&& false !== strpos( $error_message, 'wordpress@localhost' );
	}
}
