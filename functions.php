<?php

defined( 'ABSPATH' ) || exit;

use WPCOMSpecialProjects\AutoFlickrImporter\Plugin;

// region

/**
 * Returns the plugin's main class instance.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return  Plugin
 */
function auto_flickr_importer_get_plugin_instance(): Plugin {
	return Plugin::get_instance();
}

/**
 * Returns the plugin's slug.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return  string
 */
function auto_flickr_importer_get_plugin_slug(): string {
	return sanitize_key( AUTO_FLICKR_IMPORTER_METADATA['TextDomain'] );
}

// endregion

//region OTHERS

require AUTO_FLICKR_IMPORTER_PATH . 'includes/assets.php';
require AUTO_FLICKR_IMPORTER_PATH . 'includes/settings.php';
require AUTO_FLICKR_IMPORTER_PATH . 'includes/background-tasks.php';
require AUTO_FLICKR_IMPORTER_PATH . 'includes/flickr-helpers.php';
require AUTO_FLICKR_IMPORTER_PATH . 'includes/helpers.php';
require AUTO_FLICKR_IMPORTER_PATH . 'includes/hooks.php';

// endregion
