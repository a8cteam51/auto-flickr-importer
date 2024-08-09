<?php

namespace WPCOMSpecialProjects\AutoFlickrImporter;

use WPCOMSpecialProjects\AutoFlickrImporter\Tasks\Fetch_Comment_Delta_Task;
use WPCOMSpecialProjects\AutoFlickrImporter\Tasks\Fetch_Latest_Task;
use WPCOMSpecialProjects\AutoFlickrImporter\Tasks\Initial_Import_Task;

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
	public function initialize(): void {
		add_action( 'admin_init', array( $this, 'set_flickr_import_cron' ) );
		add_action( 'run_automate_flickr_import_hook', array( $this, 'run_automate_flickr_import' ) );
	}

	/**
	 * Set the Flickr import cron.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 *
	 * @return void
	 */
	public function set_flickr_import_cron(): void {

		$this->start_automated_flickr_import_tasks();

		if ( isset( $_POST['_wpnonce'], $_POST['action'] ) && wp_verify_nonce( wp_unslash( $_POST['_wpnonce'] ), 'initial_import_action' ) && 'run_initial_import' === $_POST['action'] ) {
			$this->run_initial_import();
		}

		if ( isset( $_POST['_wpnonce'], $_POST['action'] ) && wp_verify_nonce( wp_unslash( $_POST['_wpnonce'] ), 'initial_import_action' ) && 're-run_initial_import' === $_POST['action'] ) {
			$this->run_initial_import( true );
		}
	}

	/**
	 * Run the automate Flickr import.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 *
	 * @return void
	 */
	private function start_automated_flickr_import_tasks(): void {

		if ( ! wpcomsp_auto_flickr_importer_credentials_exist() ) {
			return;
		}

		$initial_import_finished = wpcomsp_auto_flickr_importer_get_raw_setting( 'initial_import_finished' );
		$latest_timestamp        = wpcomsp_auto_flickr_importer_get_raw_setting( 'latest_import_time' );

		if ( false === boolval( $initial_import_finished ) || null === $latest_timestamp ) {
			return;
		}

		Fetch_Latest_Task::register_task();
		Fetch_Comment_Delta_Task::register_task();
	}

	/**
	 * Runs the initial import.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 *
	 * @param boolean $re_run Whether to re-run the initial import.
	 *
	 * @return void
	 */
	private function run_initial_import( bool $re_run = false ): void {

		if ( ! wpcomsp_auto_flickr_importer_credentials_exist() ) {
			return;
		}

		$initial_import_finished = wpcomsp_auto_flickr_importer_get_raw_setting( 'initial_import_finished' );

		if ( true === boolval( $initial_import_finished ) && false === $re_run ) {
			return;
		}

		if ( $re_run ) {
			wpcomsp_auto_flickr_importer_update_raw_setting( 'initial_import_finished', false );
		}

		Initial_Import_Task::register_task();
	}

	// endregion
}
