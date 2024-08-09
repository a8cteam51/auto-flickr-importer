<?php

namespace WPCOMSpecialProjects\AutoFlickrImporter\Tasks;

defined( 'ABSPATH' ) || exit;

/**
 * The base class for all background tasks.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
abstract class Abstract_Background_Task {
	// region METHODS

	/**
	 * Returns the name of the task.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  string
	 */
	abstract public static function get_task_name(): string;

	/**
	 * Registers the background task.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	abstract public static function register_task(): void;

	/**
	 * Initializes the background task.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function initialize(): void {
		\add_filter( "auto_flickr_importer/background_task_queue/{$this::get_task_name()}", array( $this, 'generate_queue' ), 10, 3 );
		\add_action( "auto_flickr_importer/run_background_task/{$this::get_task_name()}", array( $this, 'process_chunk' ), 10, 2 );
		\add_action( "auto_flickr_importer/cleanup_background_task/{$this::get_task_name()}", array( $this, 'cleanup' ) );
	}

	// endregion

	// region HOOKS

	/**
	 * Generates the work queue for the background task.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   array  $queue      The current queue.
	 * @param   array  $start_args The arguments to start the task.
	 * @param   string $run_id     The ID of the current run.
	 *
	 * @return  array
	 */
	public function generate_queue( array $queue, array $start_args, string $run_id ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$queue[] = $start_args; // Add the start arguments to the queue. Useful as a default for tasks that only have one chunk.
		return $queue;
	}

	/**
	 * Performs a chunk of the background task.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   array  $args   The arguments for the current chunk.
	 * @param   string $run_id The ID of the current run.
	 *
	 * @return  void
	 */
	abstract public function process_chunk( array $args, string $run_id ): void;

	/**
	 * Cleans up the background task.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string $run_id The ID of the current run.
	 *
	 * @return  void
	 */
	public function cleanup( string $run_id ): void {
		// Do nothing by default. Override in child classes.
	}

	// endregion
}
