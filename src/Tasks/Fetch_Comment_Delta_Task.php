<?php

namespace WPCOMSpecialProjects\AutoFlickrImporter\Tasks;

use WPCOMSpecialProjects\AutoFlickrImporter\Importers\Comment_Delta_Importer;

defined( 'ABSPATH' ) || exit;

/**
 * Monitor the status of Jetpack connections and backup/monitor functionality for the sites of the WordPress.com Special Projects team.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class Fetch_Comment_Delta_Task extends Abstract_Background_Task {
	// region METHODS

	/**
	 * {@inheritDoc}
	 */
	#[\Override] public static function get_task_name(): string {
		return 'fetch_comment_delta_import';
	}

	/**
	 * {@inheritDoc}
	 */
	#[\Override] public static function register_task(): void {
		auto_flickr_importer_schedule_recurring_background_task( self::get_task_name(), time() + 60 * 60 * 12, 60 * 60 * 12 );
	}

	/**
	 * {@inheritDoc}
	 */
	#[\Override] public function generate_queue( array $queue, array $start_args, string $run_id ): array {

		$queue[] = array(
			'page' => 1,
		);

		return $queue;
	}

	/**
	 * {@inheritDoc}
	 */
	#[\Override] public function process_chunk( array $args, string $run_id ): void {

		$comment_delta_importer = new Comment_Delta_Importer();

		$next_args = $comment_delta_importer->run_import( $args['page'] );

		if ( $next_args ) { // Queue up next page.
			auto_flickr_importer_enqueue_front_into_background_task_queue( $this::get_task_name(), $run_id, $next_args );
		}
	}

	// endregion

	// region HELPERS

	// endregion
}
