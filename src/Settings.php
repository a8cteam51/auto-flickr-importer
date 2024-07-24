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

	protected Flickr_OAuth $flickr_oauth;

	/**
	 * Initializes the settings page.
	 *
	 * @return void
	 */
	public function initialize() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		$this->flickr_oauth = new Flickr_OAuth();
		$this->flickr_oauth->handle_oauth();
	}

	/**
	 * Adds the settings page under the Settings menu.
	 *
	 * @return void
	 */
	public function add_settings_page() {
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
	 * @return void
	 */
	public function register_settings() {
		$this->register_setting_section_credentials();
		$this->register_setting_section_importer();
	}

	/**
	 * Registers the setting section credentials.
	 *
	 * @return void
	 */
	public function register_setting_section_credentials() {
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
	 * @return void
	 */
	public function register_setting_section_importer() {
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
	 * @param array $args The field arguments.
	 *
	 * @return void
	 */
	public function text_field_callback( array $args ): void {
		$field = wpcomsp_auto_flickr_importer_get_raw_setting( $args['field'], '' );
		echo wp_kses_post( sprintf( '<input type="text" id="wpcomsp_auto_flickr_importer_%s" name="wpcomsp_auto_flickr_importer_%s" value="%s" />', $args['field'], $args['field'], esc_attr( $field ) ) );
		if ( ! empty( $args['description'] ) ) {
			echo wp_kses_post( sprintf( '<p class="description">%s</p>', $args['description'] ) );
		}
	}

	/**
	 * Displays the password field.
	 *
	 * @param array $args The field arguments.
	 *
	 * @return void
	 */
	public function password_field_callback( array $args ): void {
		$field = wpcomsp_auto_flickr_importer_get_raw_setting( $args['field'], '' );
		echo wp_kses_post( sprintf( '<input type="password" id="wpcomsp_auto_flickr_importer_%s" name="wpcomsp_auto_flickr_importer_%s" value="%s" />', $args['field'], $args['field'], esc_attr( $field ) ) );
		if ( ! empty( $args['description'] ) ) {
			echo wp_kses_post( sprintf( '<p class="description">%s</p>', $args['description'] ) );
		}
	}

	/**
	 * Displays the settings page content.
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
			<form method="post" action="<?php echo esc_url( admin_url( 'options-general.php?page=wpcomsp_auto_flickr_importer-settings&action=flickr_oauth_request' ) ); ?>">
				<?php
				if ( $this->flickr_oauth->token_exist() ) {
					submit_button( 'Re-Authorize Flickr', 'secondary', 'flickr_oauth_request', false );
					echo '<p>' . esc_html__( 'Flickr Account Authorized.', 'auto-flickr-importer' ) . '</p>';
				} elseif ( credentials_exist() ) {
					submit_button( 'Authorize Flickr', 'secondary', 'flickr_oauth_request', false );
					echo '<p>' . esc_html__( 'Authorize your Flickr account to enable auto import.', 'auto-flickr-importer' ) . '</p>';
				} else {
					submit_button( 'Authorize Flickr', 'secondary', 'flickr_oauth_request', false, array( 'disabled' => true ) );
					echo '<p>' . esc_html__( 'Please enter your Flickr API Key and Secret before authorizing.', 'auto-flickr-importer' ) . '</p>';
				}
				?>
			</form>
		</div>
		<?php
	}
}
