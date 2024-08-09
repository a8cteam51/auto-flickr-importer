<?php

namespace WPCOMSpecialProjects\AutoFlickrImporter;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the lifecycle of background tasks.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class BackgroundTasks {

	/**
	 * Bumped action scheduler time limit.
	 */
	private const ACTION_SCHEDULER_TIME_LIMIT = 1600;

	// region METHODS

	/**
	 * Initializes the task steps hooks.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function initialize(): void {
		add_action( 'auto_flickr_importer/start_background_task', array( $this, 'start_background_task' ), 10, 2 );
		add_action( 'auto_flickr_importer/continue_background_task', array( $this, 'continue_background_task' ), 10, 2 );
		add_action( 'auto_flickr_importer/run_background_task', array( $this, 'run_background_task' ), 10, 3 );
		add_action( 'auto_flickr_importer/cleanup_background_task', array( $this, 'cleanup_background_task' ), 10, 2 );

		add_filter( 'action_scheduler_queue_runner_time_limit', array( $this, 'increase_time_limit' ) );
	}

	/**
	 * Increases the time limit for the background task runner.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 *
	 * @return integer
	 */
	public function increase_time_limit() {
		return self::ACTION_SCHEDULER_TIME_LIMIT;
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
	public function schedule_recurring_background_task( string $task_name, int $timestamp, int $interval = DAY_IN_SECONDS, array $start_args = array() ): ?int {
		$data = array(
			'task_name' => $task_name,
			'args'      => $start_args,
		);
		if ( ! as_has_scheduled_action( 'auto_flickr_importer/start_background_task', $data ) ) {
			$result = as_schedule_recurring_action( $timestamp, $interval, 'auto_flickr_importer/start_background_task', $data );
			if ( 0 === $result ) {
				throw new \RuntimeException( 'Failed to schedule recurring background task.' );
			}
		} else {
			$result = null;
		}

		return $result;
	}

	/**
	 * Schedules a new background task to be run at a specific timestamp.
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
	public function schedule_background_task( string $task_name, int $timestamp, array $start_args ): ?int {
		return $this->schedule_background_task_action( 'start', $timestamp, $task_name, null, $start_args );
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
	public function enqueue_background_task( string $task_name, array $start_args = array() ): ?int {
		return $this->enqueue_background_task_action( 'start', $task_name, null, $start_args );
	}

	// endregion

	// region HOOKS

	/**
	 * Starts a new run of a background task.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string $task_name  The name of the task.
	 * @param   array  $start_args Optional arguments for the task.
	 *
	 * @return  void
	 */
	public function start_background_task( string $task_name, array $start_args ): void {
		$this->stop_background_task( $task_name, $start_args );

		$run_id = $this->generate_new_background_task_id( $task_name, $start_args );
		$this->generate_background_task_queue( $task_name, $run_id, $start_args );
		$this->continue_background_task( $task_name, $run_id );
	}

	/**
	 * Continues a background task that was previously started.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string $task_name The name of the task.
	 * @param   string $run_id    The ID of the task run.
	 *
	 * @return  void
	 */
	public function continue_background_task( string $task_name, string $run_id ): void {
		$next_args = auto_flickr_importer_dequeue_from_background_task_queue( $task_name, $run_id );

		$next_action = is_null( $next_args ) ? 'cleanup' : 'run';
		$this->enqueue_background_task_action( $next_action, $task_name, $run_id, $next_args );
	}

	/**
	 * Performs a unit of work of a background task.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string $task_name The name of the task.
	 * @param   string $run_id    The ID of the task run.
	 * @param   array  $args      The arguments for the chunk of work.
	 *
	 * @return void
	 */
	public function run_background_task( string $task_name, string $run_id, array $args ): void {
		$start_args    = $this->get_background_task_start_args( $task_name, $run_id );
		$latest_run_id = auto_flickr_importer_get_latest_background_task_id( $task_name, $start_args );
		if ( $latest_run_id !== $run_id ) {
			wpcomsp_auto_flickr_importer_write_log( 'Background task: ' . $task_name . $run_id . ' Skipping old event.' );
			return;
		}

		do_action( "auto_flickr_importer/run_background_task/$task_name", $args, $run_id, $task_name );
		$this->schedule_background_task_action( 'continue', time() + MINUTE_IN_SECONDS, $task_name, $run_id );
	}

	/**
	 * Cleans up a background task that has finished running.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string $task_name The name of the task.
	 * @param   string $run_id    The ID of the task run.
	 *
	 * @return  void
	 */
	public function cleanup_background_task( string $task_name, string $run_id ): void {
		$start_args    = $this->get_background_task_start_args( $task_name, $run_id );
		$latest_run_id = auto_flickr_importer_get_latest_background_task_id( $task_name, $start_args );
		if ( $latest_run_id !== $run_id ) {
			wpcomsp_auto_flickr_importer_write_log( 'Background task ' . $task_name . $run_id . ' Skipping old event.' );
			return;
		}

		do_action( "auto_flickr_importer/cleanup_background_task/$task_name", $run_id, $task_name );
		$this->save_previous_completed_background_task_id( $task_name, $run_id );
	}

	/**
	 * Stops any current run of a given background task.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string $task_name  The name of the task.
	 * @param   array  $start_args Arguments used to start the task.
	 *
	 * @return  void
	 */
	public function stop_background_task( string $task_name, array $start_args ): void {
		$latest_run_id = auto_flickr_importer_get_latest_background_task_id( $task_name, $start_args );
		$this->unschedule_background_task_action( 'start', $task_name, $latest_run_id, $start_args );

		if ( ! is_null( $latest_run_id ) ) { // Probably very first run if null.
			$this->unschedule_background_task_actions_all( $task_name, $latest_run_id );
			auto_flickr_importer_clear_background_task_queue( $task_name, $latest_run_id );
		}
	}

	// endregion

	// region HELPERS

	/**
	 * Generates a new ID for a background task.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string $task_name  The name of the task.
	 * @param   array  $start_args Arguments used to start the task.
	 *
	 * @return  string
	 */
	protected function generate_new_background_task_id( string $task_name, array $start_args = array() ): string {
		$run_id    = wp_generate_uuid4();
		$args_hash = auto_flickr_importer_generate_background_task_start_args_hash( $start_args );

		update_option( "wpcomsp_bg-task_{$task_name}_latest-run-id", $run_id, false );
		update_option( "wpcomsp_bg-task_{$task_name}_latest-run-id_$args_hash", $run_id, false );
		update_option( "wpcomsp_bg-task_{$task_name}_run-{$run_id}_start-args", $start_args, false );

		$this->save_previous_started_background_task_id( $task_name, $run_id );
		return $run_id;
	}

	/**
	 * Generates the work queue for a background task's run.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string $task_name  The name of the task.
	 * @param   string $run_id     The ID of the task run.
	 * @param   array  $start_args Optional arguments for the task.
	 *
	 * @return  void
	 */
	protected function generate_background_task_queue( string $task_name, string $run_id, array $start_args = array() ): void {
		$queue = apply_filters( "auto_flickr_importer/background_task_queue/$task_name", array(), $start_args, $run_id, $task_name );
		update_option( "wpcomsp_bg-task_{$task_name}_run-{$run_id}_queue", $queue, false );
	}

	/**
	 * Retrieves the arguments used to start a given run of a background task.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string $task_name The name of the task.
	 * @param   string $run_id    The ID of the task run.
	 *
	 * @return  array|null
	 */
	protected function get_background_task_start_args( string $task_name, string $run_id ): ?array {
		return get_option( "wpcomsp_bg-task_{$task_name}_run-{$run_id}_start-args", null );
	}

	/**
	 * Stores the ID of a started run of a background task.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string  $task_name     The name of the task.
	 * @param   string  $run_id        The ID of the task run.
	 * @param   integer $max_list_size The maximum number of IDs to keep in the list. Default is 30.
	 *
	 * @return  void
	 */
	protected function save_previous_started_background_task_id( string $task_name, string $run_id, int $max_list_size = 30 ): void {
		$start_args = $this->get_background_task_start_args( $task_name, $run_id );
		if ( is_null( $start_args ) ) {
			wpcomsp_auto_flickr_importer_write_log( 'Background task ' . $task_name . $run_id . ' Missing start args.' );
			return;
		}

		$previous_task_ids_for_all  = auto_flickr_importer_get_previous_started_background_task_ids( $task_name );
		$previous_task_ids_for_args = auto_flickr_importer_get_previous_started_background_task_ids( $task_name, $start_args );

		$previous_task_ids_for_all[] = $run_id;
		while ( count( $previous_task_ids_for_all ) > $max_list_size ) { // phpcs:ignore Squiz.PHP.DisallowSizeFunctionsInLoops.Found
			array_shift( $previous_task_ids_for_all );
		}

		$previous_task_ids_for_args[] = $run_id;
		while ( count( $previous_task_ids_for_args ) > $max_list_size ) { // phpcs:ignore Squiz.PHP.DisallowSizeFunctionsInLoops.Found
			array_shift( $previous_task_ids_for_args );
		}

		$args_hash = auto_flickr_importer_generate_background_task_start_args_hash( $start_args );
		update_option( "wpcomsp_bg-task_{$task_name}_previous-started_run-ids", $previous_task_ids_for_all, false );
		update_option( "wpcomsp_bg-task_{$task_name}_previous-started_run-ids_$args_hash", $previous_task_ids_for_args, false );
	}

	/**
	 * Stores the ID of a completed run of a background task.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string  $task_name     The name of the task.
	 * @param   string  $run_id        The ID of the task run.
	 * @param   integer $max_list_size The maximum number of IDs to keep in the list. Default is 30.
	 *
	 * @return  void
	 */
	protected function save_previous_completed_background_task_id( string $task_name, string $run_id, int $max_list_size = 30 ): void {
		$start_args = $this->get_background_task_start_args( $task_name, $run_id );
		if ( is_null( $start_args ) ) {
			wpcomsp_auto_flickr_importer_write_log( 'Background task ' . $task_name . $run_id . ' Missing start args.' );
			return;
		}

		$previous_task_ids_for_all  = auto_flickr_importer_get_previous_completed_background_task_ids( $task_name );
		$previous_task_ids_for_args = auto_flickr_importer_get_previous_completed_background_task_ids( $task_name, $start_args );

		$previous_task_ids_for_all[] = $run_id;
		while ( count( $previous_task_ids_for_all ) > $max_list_size ) { // phpcs:ignore Squiz.PHP.DisallowSizeFunctionsInLoops.Found
			array_shift( $previous_task_ids_for_all );
		}

		$previous_task_ids_for_args[] = $run_id;
		while ( count( $previous_task_ids_for_args ) > $max_list_size ) { // phpcs:ignore Squiz.PHP.DisallowSizeFunctionsInLoops.Found
			array_shift( $previous_task_ids_for_args );
		}

		$args_hash = auto_flickr_importer_generate_background_task_start_args_hash( $start_args );
		update_option( "wpcomsp_bg-task_{$task_name}_previous-completed_run-ids", $previous_task_ids_for_all, false );
		update_option( "wpcomsp_bg-task_{$task_name}_previous-completed_run-ids_$args_hash", $previous_task_ids_for_args, false );
	}

	// endregion

	// region WRAPPERS

	/**
	 * Enqueues a new background task action.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string      $action    The action to enqueue.
	 * @param   string      $task_name The name of the task.
	 * @param   string|null $run_id    The ID of the task run.
	 * @param   array|null  $args      The arguments for the task.
	 *
	 * @return  integer|null
	 */
	protected function enqueue_background_task_action( string $action, string $task_name, ?string $run_id = null, ?array $args = null ): ?int {
		$data  = $this->prepare_background_task_action_data( $action, $task_name, $run_id, $args );
		$group = is_null( $run_id ) ? '' : "$task_name|$run_id";

		if ( ! as_has_scheduled_action( "auto_flickr_importer/{$action}_background_task", $data, $group ) ) {
			$result = as_enqueue_async_action( "auto_flickr_importer/{$action}_background_task", $data, $group );
			if ( 0 === $result ) {
				throw new \RuntimeException( 'Failed to enqueue background task action.' );
			}
		} else {
			$result = null;
		}

		return $result;
	}

	/**
	 * Schedules a new background task action.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string      $action    The action to schedule.
	 * @param   integer     $timestamp The timestamp to run the action.
	 * @param   string      $task_name The name of the task.
	 * @param   string|null $run_id    The ID of the task run.
	 * @param   array|null  $args      The arguments for the task.
	 *
	 * @return  integer|null
	 */
	protected function schedule_background_task_action( string $action, int $timestamp, string $task_name, ?string $run_id = null, ?array $args = null ): ?int {
		$data  = $this->prepare_background_task_action_data( $action, $task_name, $run_id, $args );
		$group = is_null( $run_id ) ? '' : "$task_name|$run_id";

		if ( ! as_has_scheduled_action( "auto_flickr_importer/{$action}_background_task", $data, $group ) ) {
			$result = as_schedule_single_action( $timestamp, "auto_flickr_importer/{$action}_background_task", $data, $group );
			if ( 0 === $result ) {
				throw new \RuntimeException( 'Failed to schedule background task action.' );
			}
		} else {
			$result = null;
		}

		return $result;
	}

	/**
	 * Unschedules a background task action.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string      $action    The action to unschedule.
	 * @param   string      $task_name The name of the task.
	 * @param   string|null $run_id    The ID of the task run.
	 * @param   array|null  $args      The arguments for the task.
	 *
	 * @return  void
	 */
	protected function unschedule_background_task_action( string $action, string $task_name, ?string $run_id = null, ?array $args = null ): void {
		$data  = $this->prepare_background_task_action_data( $action, $task_name, $run_id, $args );
		$group = is_null( $run_id ) ? '' : "$task_name|$run_id";

		as_unschedule_all_actions( "auto_flickr_importer/{$action}_background_task", $data, $group );
	}

	/**
	 * Unschedules all background task actions for a given task run.
	 * Does not unschedule the initial start action since it is not part of the group.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string $task_name The name of the task.
	 * @param   string $run_id    The ID of the task run.
	 *
	 * @return  void
	 */
	protected function unschedule_background_task_actions_all( string $task_name, string $run_id ): void {
		$group = "$task_name|$run_id";
		as_unschedule_all_actions( '', array(), $group );
	}

	/**
	 * Prepares the data for a background task action and performs a few confidence checks.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string      $action    The action to prepare data for.
	 * @param   string      $task_name The name of the task.
	 * @param   string|null $run_id    The ID of the task run.
	 * @param   array|null  $args      The arguments for the task.
	 *
	 * @return  array
	 */
	protected function prepare_background_task_action_data( string $action, string $task_name, ?string $run_id = null, ?array $args = null ): array {
		if ( ! \in_array( $action, array( 'start', 'run', 'continue', 'cleanup' ), true ) ) {
			throw new \InvalidArgumentException( 'Invalid action.' );
		}
		if ( 'start' !== $action && \is_null( $run_id ) ) {
			throw new \InvalidArgumentException( 'Missing run ID.' );
		}

		return \array_filter(
			array(
				'task_name' => $task_name,
				'run_id'    => $run_id,
				'args'      => match ( $action ) {
					'start' => $args ?? array(),
					default => $args,
				},
			),
			static fn( $value ) => ! \is_null( $value )
		);
	}

	// endregion
}
