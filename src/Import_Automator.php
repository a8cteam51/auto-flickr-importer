<?php

namespace WPCOMSpecialProjects\AutoFlickrImporter;

use WPCOMSpecialProjects\AutoFlickrImporter\Importers\Comment_Importer;
use WPCOMSpecialProjects\AutoFlickrImporter\Importers\Photo_Stream_Importer;

defined( 'ABSPATH' ) || exit;

/**
 * Logical node for all integration functionalities.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class Import_Automator {
	// region FIELDS AND CONSTANTS

	// endregion

	// region METHODS

	/**
	 * Initializes the integrations.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function initialize() {
		add_action( 'admin_init', array( $this, 'set_flickr_import_cron' ) );
		add_action( 'run_automate_flickr_import_hook', array( $this, 'run_automate_flickr_import' ) );
	}

	/**
	 * Set the Flickr import cron.
	 *
	 * @return void
	 */
	public function set_flickr_import_cron() {
		if ( function_exists( 'as_next_scheduled_action' ) && false === as_next_scheduled_action( 'run_automate_flickr_import_hook' ) ) {
			as_schedule_recurring_action( time(), 3600, 'run_automate_flickr_import_hook' );
		}
	}

	/**
	 * Run the automate Flickr import.
	 *
	 * @return void
	 */
	public function run_automate_flickr_import() {

		if ( ! wpcomsp_auto_flickr_importer_credentials_exist() ) {
			return;
		}

		$photo_stream_importer = new Photo_Stream_Importer();
		$photo_stream_importer->start_import( 50 );

		$comment_importer = new Comment_Importer();
		$comment_importer->start_import();
	}

	// endregion
}
