<?php

namespace WPCOMSpecialProjects\AutoFlickrImporter;

use WPCOMSpecialProjects\AutoFlickrImporter\API\Flickr_OAuth;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the registration and retrieval of plugin settings.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class Settings {

	/**
	 * Initializes the settings page.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 *
	 * @return void
	 */
	public function initialize(): void {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Adds the settings page under the Settings menu.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 *
	 * @return void
	 */
	public function add_settings_page(): void {
		add_options_page(
			__( 'Flickr Auto Importer', 'auto-flickr-importer' ),
			__( 'Flickr Auto Importer', 'auto-flickr-importer' ),
			'manage_options',
			'wpcomsp_auto_flickr_importer-settings',
			array( $this, 'settings_page_callback' )
		);
	}

	/**
	 * Registers the settings.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 *
	 * @return void
	 */
	public function register_settings(): void {
		$this->register_setting_section_credentials();
		$this->register_setting_section_importer();
	}

	/**
	 * Registers the setting section credentials.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 *
	 * @return void
	 */
	public function register_setting_section_credentials(): void {
		register_setting( 'wpcomsp_auto_flickr_importer_settings_group', 'wpcomsp_auto_flickr_importer_api_key' );
		register_setting( 'wpcomsp_auto_flickr_importer_settings_group', 'wpcomsp_auto_flickr_importer_api_secret' );

		add_settings_section(
			'wpcomsp_auto_flickr_importer_settings_credentials_section',
			__( 'Flickr Credentials', 'auto-flickr-importer' ),
			'',
			'wpcomsp_auto_flickr_importer-settings'
		);

		add_settings_field(
			'wpcomsp_auto_flickr_importer_api_key_field',
			__( 'Flickr API Key', 'auto-flickr-importer' ),
			array( $this, 'password_field_callback' ),
			'wpcomsp_auto_flickr_importer-settings',
			'wpcomsp_auto_flickr_importer_settings_credentials_section',
			array( 'field' => 'api_key' )
		);

		add_settings_field(
			'wpcomsp_auto_flickr_importer_api_secret_field',
			__( 'Flickr API Secret', 'auto-flickr-importer' ),
			array( $this, 'password_field_callback' ),
			'wpcomsp_auto_flickr_importer-settings',
			'wpcomsp_auto_flickr_importer_settings_credentials_section',
			array( 'field' => 'api_secret' )
		);
	}

	/**
	 * Registers the setting section importer.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 *
	 * @return void
	 */
	public function register_setting_section_importer(): void {
		register_setting( 'wpcomsp_auto_flickr_importer_settings_group', 'wpcomsp_auto_flickr_importer_site_author_username' );
		register_setting( 'wpcomsp_auto_flickr_importer_settings_group', 'wpcomsp_auto_flickr_importer_username' );

		add_settings_section(
			'wpcomsp_auto_flickr_importer_settings_importer_section',
			__( 'Flickr Importer', 'auto-flickr-importer' ),
			'',
			'wpcomsp_auto_flickr_importer-settings'
		);

		add_settings_field(
			'wpcomsp_auto_flickr_importer_username_field',
			__( 'Flickr Username', 'auto-flickr-importer' ),
			array( $this, 'text_field_callback' ),
			'wpcomsp_auto_flickr_importer-settings',
			'wpcomsp_auto_flickr_importer_settings_importer_section',
			array( 'field' => 'username' )
		);

		add_settings_field(
			'wpcomsp_auto_flickr_importer_site_author_username_field',
			__( 'Site Author Username', 'auto-flickr-importer' ),
			array( $this, 'text_field_callback' ),
			'wpcomsp_auto_flickr_importer-settings',
			'wpcomsp_auto_flickr_importer_settings_importer_section',
			array(
				'field'       => 'site_author_username',
				'description' => __( 'This is the username of the author that will be assigned to the imported posts.', 'auto-flickr-importer' ),
			)
		);
	}

	/**
	 * Displays the text field.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 *
	 * @param array $args The field arguments.
	 *
	 * @return void
	 */
	public function text_field_callback( array $args ): void {
		$field = wpcomsp_auto_flickr_importer_get_raw_setting( $args['field'], '' );
		echo wp_kses(
			sprintf( '<input type="text" id="wpcomsp_auto_flickr_importer_%s" name="wpcomsp_auto_flickr_importer_%s" value="%s" />', $args['field'], $args['field'], esc_attr( $field ) ),
			array(
				'input' => array(
					'type'  => array(),
					'id'    => array(),
					'name'  => array(),
					'value' => array(),
				),
			)
		);
		if ( ! empty( $args['description'] ) ) {
			echo wp_kses_post( sprintf( '<p class="description">%s</p>', $args['description'] ) );
		}
	}

	/**
	 * Displays the password field.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 *
	 * @param array $args The field arguments.
	 *
	 * @return void
	 */
	public function password_field_callback( array $args ): void {
		$field = wpcomsp_auto_flickr_importer_get_raw_setting( $args['field'], '' );
		echo wp_kses(
			sprintf( '<input type="password" id="wpcomsp_auto_flickr_importer_%s" name="wpcomsp_auto_flickr_importer_%s" value="%s" />', $args['field'], $args['field'], esc_attr( $field ) ),
			array(
				'input' => array(
					'type'  => array(),
					'id'    => array(),
					'name'  => array(),
					'value' => array(),
				),
			)
		);
		if ( ! empty( $args['description'] ) ) {
			echo wp_kses_post( sprintf( '<p class="description">%s</p>', $args['description'] ) );
		}
	}

	/**
	 * Renders the initial import action.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 *
	 * @return void
	 */
	private function render_initial_import_action(): void {

		$attributes = array();
		if ( ! wpcomsp_auto_flickr_importer_credentials_exist() ) {
			$attributes['disabled'] = true;
			submit_button( 'Run Initial Flickr Import', 'secondary', 'flickr_initial_import', false, $attributes );
			echo '<p>' . esc_html__( 'Please enter your Flickr API Key, Secret and Importer data before you can run the initial import.', 'auto-flickr-importer' ) . '</p>';
			return;
		}

		if ( ! empty( wpcomsp_auto_flickr_importer_get_raw_setting( 'initial_import_running' ) ) ) {
			$attributes['disabled'] = true;
			submit_button( 'Initial Flickr Import Running', 'secondary', 'flickr_initial_import', false, $attributes );
			echo '<p>' . esc_html__( 'The initial import is running you will get an email when it\'s finished.', 'auto-flickr-importer' ) . '</p>';
			return;
		}

		if ( ! empty( wpcomsp_auto_flickr_importer_get_raw_setting( 'initial_import_finished' ) ) ) {
			$action  = '<input type="hidden" name="action" value="re-run_initial_import">';
			$action .= wp_nonce_field( 'initial_import_action' );
			$action .= '<h3>' . esc_html__( 'Initial Import finished', 'auto-flickr-importer' ) . '</h3>';
			$action .= '<p>' . esc_html__( 'The initial import is finished. Flickr data is periodically auto updated.', 'auto-flickr-importer' ) . '</p>';
			$action .= get_submit_button( 'Re-run Flickr Initial Import', 'secondary', 'flickr_initial_import', false, $attributes );
			$action .= '<p><span style="color: red">' . esc_html__( 'Warning: ', 'auto-flickr-importer' ) . '</span>' . esc_html__( 'Re-running the import will try to import all of the Flickr data again.', 'auto-flickr-importer' ) . '</p>';
			echo $action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		$action  = '<input type="hidden" name="action" value="run_initial_import">';
		$action .= wp_nonce_field( 'initial_import_action' );
		$action .= get_submit_button( 'Run Flickr Import', 'secondary', 'flickr_initial_import', false );
		$action .= '<p>' . esc_html__( 'Run the initial Flickr import. After the initial import is finished the data will be periodically updated. The import could take a few hours depending on the number of images.', 'auto-flickr-importer' ) . '</p>';
		echo $action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Displays the settings page content.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 *
	 * @return void
	 */
	public function settings_page_callback() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Flickr Settings', 'auto-flickr-importer' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'wpcomsp_auto_flickr_importer_settings_group' );
				do_settings_sections( 'wpcomsp_auto_flickr_importer-settings' );
				submit_button();
				?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'options-general.php?page=wpcomsp_auto_flickr_importer-settings' ) ); ?>">
			<?php
			$this->render_initial_import_action();
			?>
			</form>
		</div>
		<?php
	}
}
