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
class Fetch_Latest_Task extends Abstract_Background_Task {
	// region METHODS

	/**
	 * {@inheritDoc}
	 */
	#[\Override] public static function get_task_name(): string {
		return 'fetch_latest_import';
	}

	/**
	 * {@inheritDoc}
	 */
	#[\Override] public static function register_task(): void {
		auto_flickr_importer_schedule_recurring_background_task( self::get_task_name(), time(), 60 * 60 );
	}

	/**
	 * {@inheritDoc}
	 */
	#[\Override] public function generate_queue( array $queue, array $start_args, string $run_id ): array {

		$latest_import_time = wpcomsp_auto_flickr_importer_get_raw_setting( 'latest_import_time' );

		// Don't run if comment delta is running because of Flickr API rate limits.
		$comment_delta_running = wpcomsp_auto_flickr_importer_get_raw_setting( 'comment_delta_running' );

		if ( null === $latest_import_time || $comment_delta_running ) {
			return array();
		}

		wpcomsp_auto_flickr_importer_update_raw_setting( 'current_latest_import_time', $latest_import_time );

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

		if ( empty( $args ) ) {
			return;
		}

		$photo_stream_importer = new Photo_Stream_Importer();

		$latest_timestamp = wpcomsp_auto_flickr_importer_get_raw_setting( 'current_latest_import_time' );

		if ( null === $latest_timestamp ) {
			return;
		}

		$next_args = $photo_stream_importer->run_import( $args['action'], $args['page'], $latest_timestamp );

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

		wpcomsp_auto_flickr_importer_update_raw_setting( 'import_running', false );
	}

	// endregion

	// region HELPERS

	// endregion
}
