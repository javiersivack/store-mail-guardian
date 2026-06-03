<?php
/**
 * Plugin coordinator.
 *
 * @package StoreMailGuardian
 */

namespace SMG;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Boots admin functionality.
 */
class Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Admin page controller.
	 *
	 * @var Admin_Page
	 */
	private $admin_page;

	/**
	 * Get the plugin instance.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		if ( is_admin() ) {
			$this->admin_page = new Admin_Page(
				new Diagnostics(),
				new Test_Email()
			);
			$this->admin_page->init();
		}
	}
}
