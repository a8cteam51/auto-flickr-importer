<?php

use WPCOMSpecialProjects\AutoFlickrImporter\BackgroundTasks;

defined( 'ABSPATH' ) || exit;

// region META

/**
 * Retrieves the background tasks instance.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return  BackgroundTasks|null
 */
function auto_flickr_importer_get_background_task_instance(): ?BackgroundTasks {
	return auto_flickr_importer_get_plugin_instance()->get_background_tasks();
}

// endregion

// region METHODS

/**
 * Enqueues a new recurring background task.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param   string  $task_name  The name of the task.
 * @param   integer $timestamp  The timestamp to run the first task at.
 * @param   integer $interval   The interval between runs.
 * @param   array   $start_args Arguments used to start the task.
 *
 * @return  integer|null
 */
function auto_flickr_importer_schedule_recurring_background_task( string $task_name, int $timestamp, int $interval = DAY_IN_SECONDS, array $start_args = array() ): ?int {
	return auto_flickr_importer_get_background_task_instance()?->schedule_recurring_background_task( $task_name, $timestamp, $interval, $start_args );
}

/**
 * Enqueues a new background task to be run at a specific timestamp.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param   string  $task_name  The name of the task.
 * @param   integer $timestamp  The timestamp to run the task at.
 * @param   array   $start_args Arguments used to start the task.
 *
 * @return  integer|null
 */
function auto_flickr_importer_schedule_background_task( string $task_name, int $timestamp, array $start_args = array() ): ?int {
	return auto_flickr_importer_get_background_task_instance()?->schedule_background_task( $task_name, $timestamp, $start_args );
}

/**
 * Enqueues a new background task.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param   string $task_name  The name of the task.
 * @param   array  $start_args Arguments used to start the task.
 *
 * @return  integer|null
 */
function auto_flickr_importer_enqueue_background_task( string $task_name, array $start_args = array() ): ?int {
	return auto_flickr_importer_get_background_task_instance()?->enqueue_background_task( $task_name, $start_args );
}

// endregion

// region HELPERS

/**
 * Generates a hash for the arguments used to start a background task.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param   array $start_args Arguments used to start the task.
 *
 * @return  string
 */
function auto_flickr_importer_generate_background_task_start_args_hash( array $start_args ): string {
	return hash( 'md5', wp_json_encode( $start_args ) );
}

/**
 * Retrieves the ID of the latest run of a background task.
 * The task might be currently running, or it might have already executed and finished.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param   string     $task_name  The name of the task.
 * @param   array|null $start_args Arguments used to start the task.
 *
 * @return  string|null
 */
function auto_flickr_importer_get_latest_background_task_id( string $task_name, ?array $start_args = null ): ?string {
	$option_name = "wpcomsp_bg-task_{$task_name}_latest-run-id";
	if ( ! is_null( $start_args ) ) {
		$args_hash    = auto_flickr_importer_generate_background_task_start_args_hash( $start_args );
		$option_name .= "_$args_hash";
	}

	return get_option( $option_name, null );
}

/**
 * Retrieves the IDs of the previous started runs of a background task.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param   string     $task_name  The name of the task.
 * @param   array|null $start_args Arguments used to start the task.
 *
 * @return  string[]
 */
function auto_flickr_importer_get_previous_started_background_task_ids( string $task_name, ?array $start_args = null ): array {
	$option_name = "wpcomsp_bg-task_{$task_name}_previous-started_run-ids";
	if ( ! is_null( $start_args ) ) {
		$args_hash    = auto_flickr_importer_generate_background_task_start_args_hash( $start_args );
		$option_name .= "_$args_hash";
	}

	return get_option( $option_name, array() );
}

/**
 * Retrieves the ID of the last completed run of a background task.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param   string     $task_name  The name of the task.
 * @param   array|null $start_args Arguments used to start the task.
 *
 * @return  string|null
 */
function auto_flickr_importer_get_last_completed_background_task_id( string $task_name, ?array $start_args = null ): ?string {
	$previous_completed_background_task_ids = auto_flickr_importer_get_previous_completed_background_task_ids( $task_name, $start_args );

	return end( $previous_completed_background_task_ids ) ?: null;
}

/**
 * Retrieves the IDs of the previously completed runs of a background task.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param   string     $task_name  The name of the task.
 * @param   array|null $start_args Arguments used to start the task.
 *
 * @return  string[]
 */
function auto_flickr_importer_get_previous_completed_background_task_ids( string $task_name, ?array $start_args = null ): array {
	$option_name = "wpcomsp_bg-task_{$task_name}_previous-completed_run-ids";
	if ( ! is_null( $start_args ) ) {
		$args_hash    = auto_flickr_importer_generate_background_task_start_args_hash( $start_args );
		$option_name .= "_$args_hash";
	}

	return get_option( $option_name, array() );
}

/**
 * Enqueues a unit of work into a background task's run as the next item to be processed.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param   string $task_name The name of the background task.
 * @param   string $run_id    The ID of the run to queue.
 * @param   array  $args      The arguments to pass to the background task.
 *
 * @return  void
 */
function auto_flickr_importer_enqueue_front_into_background_task_queue( string $task_name, string $run_id, array $args ): void {
	$queue = auto_flickr_importer_get_background_task_queue( $task_name, $run_id ) ?? array();
	array_unshift( $queue, $args );

	update_option( "wpcomsp_bg-task_{$task_name}_run-{$run_id}_queue", $queue, false );
}

/**
 * Retrieves the work queue for a background task's run.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param   string $task_name The name of the task.
 * @param   string $run_id    The ID of the task run.
 *
 * @return  array[]|null
 */
function auto_flickr_importer_get_background_task_queue( string $task_name, string $run_id ): ?array {
	return get_option( "wpcomsp_bg-task_{$task_name}_run-{$run_id}_queue", null );
}

/**
 * Dequeues a unit of work from a background task's run.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param   string $task_name The name of the background task.
 * @param   string $run_id    The ID of the run to queue.
 *
 * @return  array|null
 */
function auto_flickr_importer_dequeue_from_background_task_queue( string $task_name, string $run_id ): ?array {
	$queue = auto_flickr_importer_get_background_task_queue( $task_name, $run_id );
	if ( empty( $queue ) ) {
		$args = null;
		auto_flickr_importer_clear_background_task_queue( $task_name, $run_id );
	} else {
		$args = array_shift( $queue );
		update_option( "wpcomsp_bg-task_{$task_name}_run-{$run_id}_queue", $queue, false );
	}

	return $args;
}

/**
 * Clears the work queue for a background task's run.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param   string $task_name The name of the background task.
 * @param   string $run_id    The ID of the run to queue.
 *
 * @return  void
 */
function auto_flickr_importer_clear_background_task_queue( string $task_name, string $run_id ): void {
	delete_option( "wpcomsp_bg-task_{$task_name}_run-{$run_id}_queue" );
}

// endregion
