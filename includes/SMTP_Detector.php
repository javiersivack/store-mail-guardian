<?php
/**
 * SMTP plugin detection.
 *
 * @package StoreMailGuardian
 */

namespace SMG;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects known SMTP plugins.
 */
class SMTP_Detector {
	/**
	 * Known SMTP plugins.
	 *
	 * @var array
	 */
	private $known_plugins = array(
		'wp-mail-smtp/wp_mail_smtp.php' => 'WP Mail SMTP',
		'fluent-smtp/fluent-smtp.php'   => 'FluentSMTP',
		'post-smtp/postman-smtp.php'    => 'Post SMTP',
		'easy-wp-smtp/easy-wp-smtp.php' => 'Easy WP SMTP',
	);

	/**
	 * Get known plugin install and active states.
	 *
	 * @return array
	 */
	public function get_plugins() {
		$installed_plugins = array();

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( function_exists( 'get_plugins' ) ) {
			$installed_plugins = get_plugins();
		}

		$results = array();

		foreach ( $this->known_plugins as $plugin_file => $name ) {
			$results[] = array(
				'name'      => $name,
				'file'      => $plugin_file,
				'installed' => isset( $installed_plugins[ $plugin_file ] ),
				'active'    => $this->is_active( $plugin_file ),
			);
		}

		return $results;
	}

	/**
	 * Check whether a plugin is active for this site or network.
	 *
	 * @param string $plugin_file Plugin file.
	 * @return bool
	 */
	private function is_active( $plugin_file ) {
		if ( function_exists( 'is_plugin_active' ) && is_plugin_active( $plugin_file ) ) {
			return true;
		}

		return function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( $plugin_file );
	}
}
