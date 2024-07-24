<?php

defined( 'ABSPATH' ) || exit;

/**
 * Returns the raw database value of a global setting.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $setting_name  The name of the setting.
 * @param   mixed  $default_value The default value to return if the setting is not set.
 *
 * @return  mixed
 */
function wpcomsp_auto_flickr_importer_get_raw_setting( string $setting_name, $default_value = null ) {
	return get_option( "wpcomsp_auto_flickr_importer_$setting_name", $default_value );
}

/**
 * Updates the value of a global setting.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $setting_name The name of the setting.
 * @param   mixed  $value        The value to set.
 *
 * @return  boolean
 */
function wpcomsp_auto_flickr_importer_update_raw_setting( string $setting_name, $value ): bool {
	return update_option( "wpcomsp_auto_flickr_importer_$setting_name", $value );
}
