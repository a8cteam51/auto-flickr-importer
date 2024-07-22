<?php
/**
 * The auto-flickr-importer bootstrap file.
 *
 * @since       1.0.0
 * @version     1.0.0
 * @author      WordPress.com Special Projects
 * @license     GPL-3.0-or-later
 *
 * @noinspection    ALL
 *
 * @wordpress-plugin
 * Plugin Name:             auto-flickr-importer
 * Plugin URI:              https://wpspecialprojects.wordpress.com
 * Description:             
 * Version:                 1.0.0
 * Requires at least:       6.5
 * Tested up to:            6.5
 * Requires PHP:            8.2
 * Author:                  WordPress.com Special Projects
 * Author URI:              https://wpspecialprojects.wordpress.com
 * License:                 GPL v3 or later
 * License URI:             https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:             auto-flickr-importer
 * Domain Path:             /languages
 * WC requires at least:    8.8
 * WC tested up to:         8.8
 **/

defined( 'ABSPATH' ) || exit;

// Define plugin constants.
function_exists( 'get_plugin_data' ) || require_once ABSPATH . 'wp-admin/includes/plugin.php';
define( 'AUTO_FLICKR_IMPORTER_METADATA', get_plugin_data( __FILE__, false, false ) );

define( 'AUTO_FLICKR_IMPORTER_BASENAME', plugin_basename( __FILE__ ) );
define( 'AUTO_FLICKR_IMPORTER_PATH', plugin_dir_path( __FILE__ ) );
define( 'AUTO_FLICKR_IMPORTER_URL', plugin_dir_url( __FILE__ ) );

// Load plugin translations so they are available even for the error admin notices.
add_action(
	'init',
	static function () {
		load_plugin_textdomain(
			AUTO_FLICKR_IMPORTER_METADATA['TextDomain'],
			false,
			dirname( AUTO_FLICKR_IMPORTER_BASENAME ) . AUTO_FLICKR_IMPORTER_METADATA['DomainPath']
		);
	}
);

// Load the autoloader.
if ( ! is_file( AUTO_FLICKR_IMPORTER_PATH . '/vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		static function () {
			$message      = __( 'It seems like <strong>auto-flickr-importer</strong> is corrupted. Please reinstall!', 'auto-flickr-importer' );
			$html_message = wp_sprintf( '<div class="error notice auto-flickr-importer-error">%s</div>', wpautop( $message ) );
			echo wp_kses_post( $html_message );
		}
	);
	return;
}
require_once AUTO_FLICKR_IMPORTER_PATH . '/vendor/autoload.php';

// Initialize the plugin if system requirements check out.
$auto_flickr_importer_requirements = validate_plugin_requirements( AUTO_FLICKR_IMPORTER_BASENAME );
define( 'AUTO_FLICKR_IMPORTER_REQUIREMENTS', $auto_flickr_importer_requirements );

if ( $auto_flickr_importer_requirements instanceof WP_Error ) {
	add_action(
		'admin_notices',
		static function () use ( $auto_flickr_importer_requirements ) {
			$html_message = wp_sprintf( '<div class="error notice auto-flickr-importer-error">%s</div>', $auto_flickr_importer_requirements->get_error_message() );
			echo wp_kses_post( $html_message );
		}
	);
} else {
	require_once AUTO_FLICKR_IMPORTER_PATH . 'functions.php';
	add_action( 'plugins_loaded', array( auto_flickr_importer_get_plugin_instance(), 'maybe_initialize' ) );
}
