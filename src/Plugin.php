<?php

namespace WPCOMSpecialProjects\AutoFlickrImporter;

use WPCOMSpecialProjects\AutoFlickrImporter\Tasks\Fetch_Comment_Delta_Task;
use WPCOMSpecialProjects\AutoFlickrImporter\Tasks\Fetch_Latest_Task;
use WPCOMSpecialProjects\AutoFlickrImporter\Tasks\Initial_Import_Task;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class Plugin {
	// region FIELDS AND CONSTANTS

	/**
	 * The background tasks component.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var   BackgroundTasks|null
	 */
	protected ?BackgroundTasks $background_tasks = null;

	// endregion

	// region MAGIC METHODS

	/**
	 * Plugin constructor.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	protected function __construct() {
		/* Empty on purpose. */
	}

	/**
	 * Prevent cloning.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	private function __clone() {
		/* Empty on purpose. */
	}

	/**
	 * Prevent unserializing.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function __wakeup() {
		/* Empty on purpose. */
	}

	// endregion

	// region METHODS

	/**
	 * Returns the singleton instance of the plugin.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  Plugin
	 */
	public static function get_instance(): self {
		static $instance = null;

		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Returns the background tasks component.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  BackgroundTasks
	 */
	public function get_background_tasks(): BackgroundTasks {
		return $this->background_tasks;
	}

	/**
	 * Initializes the plugin components.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function initialize(): void {
		$settings = new Settings();
		$settings->initialize();

		$initial_import_task = new Initial_Import_Task();
		$initial_import_task->initialize();

		$fetch_latest_import_task = new Fetch_Latest_Task();
		$fetch_latest_import_task->initialize();

		$fetch_comment_delta_import_task = new Fetch_Comment_Delta_Task();
		$fetch_comment_delta_import_task->initialize();

		$this->background_tasks = new BackgroundTasks();
		$this->background_tasks->initialize();

		$import_automator = new Import_Automator();
		$import_automator->initialize();
	}

	// endregion

	// region HOOKS

	// endregion
}
