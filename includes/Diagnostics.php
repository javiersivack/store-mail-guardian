<?php
/**
 * Diagnostic checks for store mail health.
 *
 * @package StoreMailGuardian
 */

namespace SMG;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs diagnostic checks.
 */
class Diagnostics {
	/**
	 * SMTP plugin detector.
	 *
	 * @var SMTP_Detector
	 */
	private $smtp_detector;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->smtp_detector = new SMTP_Detector();
	}

	/**
	 * Get all diagnostic data.
	 *
	 * @return array
	 */
	public function get_report() {
		$woocommerce_active           = $this->is_woocommerce_active();
		$woocommerce_email_available = $this->woocommerce_email_classes_available();
		$enabled_emails              = $woocommerce_active ? $this->get_enabled_woocommerce_emails() : array();
		$sender                      = $this->get_sender_diagnostics();
		$smtp_plugins                = $this->smtp_detector->get_plugins();
		$active_smtp_plugins         = array_filter(
			$smtp_plugins,
			static function ( $plugin ) {
				return ! empty( $plugin['active'] );
			}
		);
		$status                      = $this->calculate_status( $woocommerce_active, $woocommerce_email_available, $enabled_emails, $active_smtp_plugins, $sender );

		return array(
			'overall_status'                     => $status,
			'woocommerce_active'                 => $woocommerce_active,
			'woocommerce_email_classes_available' => $woocommerce_email_available,
			'enabled_woocommerce_emails'         => $enabled_emails,
			'smtp_plugins'                       => $smtp_plugins,
			'active_smtp_plugins'                => array_values( $active_smtp_plugins ),
			'sender'                             => $sender,
			'site_admin_email'                   => get_option( 'admin_email' ),
		);
	}

	/**
	 * Check whether WooCommerce is active.
	 *
	 * @return bool
	 */
	private function is_woocommerce_active() {
		return class_exists( 'WooCommerce' ) || function_exists( 'WC' );
	}

	/**
	 * Check whether WooCommerce email classes are available.
	 *
	 * @return bool
	 */
	private function woocommerce_email_classes_available() {
		return class_exists( 'WC_Emails' ) && class_exists( 'WC_Email' );
	}

	/**
	 * Get enabled WooCommerce emails.
	 *
	 * @return array
	 */
	private function get_enabled_woocommerce_emails() {
		if ( ! function_exists( 'WC' ) || ! class_exists( 'WC_Emails' ) ) {
			return array();
		}

		$mailer = WC()->mailer();

		if ( ! $mailer || ! method_exists( $mailer, 'get_emails' ) ) {
			return array();
		}

		$enabled_emails = array();
		$emails         = $mailer->get_emails();

		foreach ( $emails as $email_id => $email ) {
			if ( ! is_object( $email ) || ! method_exists( $email, 'is_enabled' ) || ! $email->is_enabled() ) {
				continue;
			}

			$enabled_emails[] = array(
				'id'          => $email_id,
				'title'       => isset( $email->title ) ? $email->title : $email_id,
				'recipient'   => isset( $email->recipient ) ? $email->recipient : '',
				'description' => isset( $email->description ) ? $email->description : '',
			);
		}

		return $enabled_emails;
	}

	/**
	 * Get the current WordPress mail sender values.
	 *
	 * @return array
	 */
	private function get_sender_diagnostics() {
		$host = wp_parse_url( network_home_url(), PHP_URL_HOST );

		if ( ! $host ) {
			$host = 'localhost';
		}

		$host       = preg_replace( '/^www\./', '', $host );
		$from_email = (string) apply_filters( 'wp_mail_from', 'wordpress@' . $host );
		$from_name  = (string) apply_filters( 'wp_mail_from_name', 'WordPress' );
		$has_local  = false !== stripos( $from_email, 'localhost' );
		$is_valid   = is_email( $from_email ) && ! $has_local;

		return array(
			'from_email'         => $from_email,
			'from_name'          => $from_name,
			'is_valid'           => (bool) $is_valid,
			'contains_localhost' => $has_local,
			'status'             => $is_valid ? 'healthy' : 'critical',
		);
	}

	/**
	 * Calculate overall status.
	 *
	 * @param bool  $woocommerce_active WooCommerce active state.
	 * @param bool  $woocommerce_email_available WooCommerce email classes state.
	 * @param array $enabled_emails Enabled WooCommerce emails.
	 * @param array $active_smtp_plugins Active SMTP plugins.
	 * @param array $sender Sender diagnostics.
	 * @return string
	 */
	private function calculate_status( $woocommerce_active, $woocommerce_email_available, $enabled_emails, $active_smtp_plugins, $sender ) {
		if ( ! $woocommerce_active || ! $woocommerce_email_available ) {
			return 'critical';
		}

		if ( empty( $sender['is_valid'] ) ) {
			return 'critical';
		}

		if ( empty( $enabled_emails ) || empty( $active_smtp_plugins ) ) {
			return 'warning';
		}

		return 'healthy';
	}
}
