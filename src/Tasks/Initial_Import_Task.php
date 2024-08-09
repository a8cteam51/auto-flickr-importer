<?php

namespace WPCOMSpecialProjects\AutoFlickrImporter\Tasks;

use WPCOMSpecialProjects\AutoFlickrImporter\Importers\Photo_Stream_Importer;

defined( 'ABSPATH' ) || exit;

/**
 * Monitor the status of Jetpack connections and backup/monitor functionality for the sites of the WordPress.com Special Projects team.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class Initial_Import_Task extends Abstract_Background_Task {
	// region METHODS

	/**
	 * {@inheritDoc}
	 */
	#[\Override] public static function get_task_name(): string {
		return 'initial_import';
	}

	/**
	 * {@inheritDoc}
	 */
	#[\Override] public static function register_task(): void {
		auto_flickr_importer_schedule_background_task( self::get_task_name(), time() );
	}

	/**
	 * {@inheritDoc}
	 */
	#[\Override] public function generate_queue( array $queue, array $start_args, string $run_id ): array {

		wpcomsp_auto_flickr_importer_update_raw_setting( 'latest_import_time', time() );
		wpcomsp_auto_flickr_importer_update_raw_setting( 'initial_import_running', true );

		$queue[] = array(
			'action' => 'run_initial_import_photosets',
			'page'   => 1,
		);

		return $queue;
	}

	/**
	 * {@inheritDoc}
	 */
	#[\Override] public function process_chunk( array $args, string $run_id ): void {
		$photo_stream_importer = new Photo_Stream_Importer();

		$next_args = $photo_stream_importer->run_import( $args['action'], $args['page'] );

		if ( $next_args ) { // Queue up next page.
			auto_flickr_importer_enqueue_front_into_background_task_queue( $this::get_task_name(), $run_id, $next_args );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	#[\Override] public function cleanup( string $run_id ): void {
		$photo_stream_importer = new Photo_Stream_Importer();
		$photo_stream_importer->run_import( 'run_initial_import_cleanup' );

		wpcomsp_auto_flickr_importer_update_raw_setting( 'initial_import_finished', true );
		wpcomsp_auto_flickr_importer_update_raw_setting( 'import_running', false );
		wpcomsp_auto_flickr_importer_update_raw_setting( 'initial_import_running', false );

		$this->send_import_completed_email();
	}

	/**
	 * Send an email to the site owner when the initial import is complete.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 *
	 * @return void
	 */
	private function send_import_completed_email() {
		// Get the site owner's email address from the WordPress settings
		$site_owner_email = get_option( 'admin_email' );

		// Define the email subject and message
		$subject = __( 'Hey, your Flickr import is complete!', 'auto-flickr-importer' );
		$message = __( 'Your initial Flickr import is complete. You can now view your photos, posts and comments. The importer will auto update the data periodically', 'auto-flickr-importer' );
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		wp_mail( $site_owner_email, $subject, $message, $headers );
	}

	// endregion

	// region HELPERS

	// endregion
}
