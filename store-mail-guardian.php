<?php
/**
 * Plugin Name: Store Mail Guardian
 * Description: Checks whether WooCommerce transactional emails may be failing and lets store owners send a test email.
 * Version: 0.1.0
 * Author: Sealion Studios
 * Text Domain: store-mail-guardian
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package StoreMailGuardian
 */

namespace SMG;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SMG_VERSION', '0.1.0' );
define( 'SMG_PLUGIN_FILE', __FILE__ );
define( 'SMG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SMG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SMG_OPTION_LATEST_TEST', 'smg_latest_test_email_result' );

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = __NAMESPACE__ . '\\';

		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$relative_class = substr( $class_name, strlen( $prefix ) );
		$file           = SMG_PLUGIN_DIR . 'includes/' . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		Plugin::instance()->init();
	}
);
