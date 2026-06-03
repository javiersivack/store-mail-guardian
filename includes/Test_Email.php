<?php
/**
 * Test email sender.
 *
 * @package StoreMailGuardian
 */

namespace SMG;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends and stores test email results.
 */
class Test_Email {
	/**
	 * Last WordPress mail error.
	 *
	 * @var string
	 */
	private $last_error = '';

	/**
	 * Send a test email and persist the result.
	 *
	 * @param string $recipient Recipient email address.
	 * @return array
	 */
	public function send( $recipient ) {
		$recipient = sanitize_email( $recipient );

		if ( ! is_email( $recipient ) ) {
			$result = $this->build_result( $recipient, false, __( 'Invalid recipient email address.', 'store-mail-guardian' ) );
			update_option( SMG_OPTION_LATEST_TEST, $result, false );
			return $result;
		}

		$this->last_error = '';
		add_action( 'wp_mail_failed', array( $this, 'capture_mail_error' ) );

		$subject = sprintf(
			/* translators: %s: Site name. */
			__( 'Store Mail Guardian test from %s', 'store-mail-guardian' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);
		$message = __( 'This is a Store Mail Guardian test email. If you received it, WordPress was able to hand the message to the configured mail transport.', 'store-mail-guardian' );
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		$sent    = wp_mail( $recipient, $subject, $message, $headers );

		remove_action( 'wp_mail_failed', array( $this, 'capture_mail_error' ) );

		$result = $this->build_result( $recipient, (bool) $sent, $sent ? '' : $this->last_error );
		update_option( SMG_OPTION_LATEST_TEST, $result, false );

		return $result;
	}

	/**
	 * Get the latest stored test result.
	 *
	 * @return array
	 */
	public function get_latest_result() {
		$result = get_option( SMG_OPTION_LATEST_TEST, array() );

		return is_array( $result ) ? $result : array();
	}

	/**
	 * Capture a wp_mail failure.
	 *
	 * @param \WP_Error $error Mail error.
	 * @return void
	 */
	public function capture_mail_error( $error ) {
		if ( is_wp_error( $error ) ) {
			$this->last_error = $error->get_error_message();
		}
	}

	/**
	 * Build a stored result payload.
	 *
	 * @param string $recipient Recipient email address.
	 * @param bool   $success Success state.
	 * @param string $error_message Error message.
	 * @return array
	 */
	private function build_result( $recipient, $success, $error_message ) {
		return array(
			'timestamp'     => current_time( 'mysql' ),
			'recipient'     => $recipient,
			'success'       => $success,
			'error_message' => $error_message,
		);
	}
}
