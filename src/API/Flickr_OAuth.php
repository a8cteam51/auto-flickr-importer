<?php

namespace WPCOMSpecialProjects\AutoFlickrImporter\API;

defined( 'ABSPATH' ) || exit;


final class Flickr_OAuth {
	// region FIELDS AND CONSTANTS

	const FLICKR_BASE_URL = 'https://www.flickr.com/services/oauth/';

	public function handle_oauth() {

		if ( ! isset( $_GET['page'] ) || 'wpcomsp_auto_flickr_importer-settings' !== $_GET['page'] ) {
			return;
		}

		if ( isset( $_GET['action'] ) && 'flickr_oauth_request' === $_GET['action'] ) {
			$this->flickr_oauth_request();
		}

		// Check if the necessary GET parameters are present
		if ( isset( $_GET['oauth_token'] ) && isset( $_GET['oauth_verifier'] ) ) {
			$this->flickr_oauth_callback_handler();
		}
	}

	public function token_exist() {
		$token        = wpcomsp_auto_flickr_importer_get_raw_setting( 'token', '' );
		$token_secret = wpcomsp_auto_flickr_importer_get_raw_setting( 'token_secret', '' );

		if ( empty( $token ) || empty( $token_secret ) ) {
			return false;
		}

		return true;
	}

	private function flickr_oauth_request() {
		$api_key      = wpcomsp_auto_flickr_importer_get_raw_setting( 'api_key', '' );
		$api_secret   = wpcomsp_auto_flickr_importer_get_raw_setting( 'api_secret', '' );
		$callback_url = admin_url( 'options-general.php?page=wpcomsp_auto_flickr_importer-settings&action=flickr_oauth_callback' );

		$params = array(
			'oauth_nonce'            => md5( mt_rand() ),
			'oauth_timestamp'        => time(),
			'oauth_consumer_key'     => $api_key,
			'oauth_signature_method' => 'HMAC-SHA1',
			'oauth_version'          => '1.0',
			'oauth_callback'         => urlencode( $callback_url ),
		);

		$signature                 = $this->sign_request( 'request_token', $params, $api_secret );
		$params['oauth_signature'] = $signature;

		$response = $this->send_request( self::FLICKR_BASE_URL . 'request_token', $params );

		if ( is_wp_error( $response ) || $response['response']['code'] !== 200 ) {
			wp_die( 'Failed to obtain an token and secret.' );
		}

		$response_data = wp_remote_retrieve_body( $response );

		// Array to hold the parsed data
		$parsed_data = array();

		// Parse the query string into the array
		parse_str( $response_data, $parsed_data );

		if ( ! isset( $parsed_data['oauth_callback_confirmed'] ) || ! isset( $parsed_data['oauth_token'] ) || ! isset( $parsed_data['oauth_token_secret'] ) ) {
			wp_die( 'Failed to obtain an token and secret.' );
		}

		// Now you can access the values using their keys
		$oauth_callback_confirmed = $parsed_data['oauth_callback_confirmed'];
		$oauth_token              = $parsed_data['oauth_token'];
		$oauth_token_secret       = $parsed_data['oauth_token_secret'];

		if ( false === boolval( $oauth_callback_confirmed ) ) {
			wp_die( 'Oauth callback is incorrect.' );
		}

		set_transient( 'flickr_oauth_token_secret_' . $oauth_token, $oauth_token_secret );

		$params                = array();
		$params['oauth_token'] = $oauth_token;
		$params['perms']       = 'read';

		$authorization_url = self::FLICKR_BASE_URL . 'authorize?' . http_build_query( $params );

		wp_redirect( $authorization_url );
		exit;
	}

	private function sign_request( $endpoint, $params, $consumer_secret, $token_secret = '' ) {
		$base_info     = $this->build_base_string( self::FLICKR_BASE_URL . $endpoint, 'GET', $params );
		$composite_key = urlencode( $consumer_secret ) . '&' . urlencode( $token_secret );
		$signature     = base64_encode( hash_hmac( 'sha1', $base_info, $composite_key, true ) );
		return $signature;
	}

	private function build_base_string( $base_uri, $method, $params ) {
		ksort( $params );
		$base_string_parts = array();
		foreach ( $params as $key => $value ) {
			$base_string_parts[] = "$key=" . rawurlencode( $value );
		}
		return $method . '&' . rawurlencode( $base_uri ) . '&' . rawurlencode( implode( '&', $base_string_parts ) );
	}

	private function flickr_oauth_callback_handler() {

		$request_token = sanitize_text_field( $_GET['oauth_token'] );
		$verifier      = sanitize_text_field( $_GET['oauth_verifier'] );

		// Retrieve the token secret from a temporary store, this should have been saved during the request_token step
		$token_secret = get_transient( 'flickr_oauth_token_secret_' . $request_token );
		if ( ! $token_secret ) {
			wp_die( 'Invalid token or token expired.' );
		}

		$api_key    = wpcomsp_auto_flickr_importer_get_raw_setting( 'api_key', '' );
		$api_secret = wpcomsp_auto_flickr_importer_get_raw_setting( 'api_secret', '' );

		$params = array(
			'oauth_nonce'            => md5( mt_rand() ),
			'oauth_timestamp'        => time(),
			'oauth_consumer_key'     => $api_key,
			'oauth_token'            => $request_token,
			'oauth_signature_method' => 'HMAC-SHA1',
			'oauth_version'          => '1.0',
			'oauth_verifier'         => $verifier,
		);

		$signature                 = $this->sign_request( 'access_token', $params, $api_secret, $token_secret );
		$params['oauth_signature'] = $signature;

		$url      = self::FLICKR_BASE_URL . 'access_token';
		$response = $this->send_request( $url, $params );

		if ( is_wp_error( $response ) || $response['response']['code'] !== 200 ) {
			wp_die( 'Failed to obtain an access token.' );
		}

		parse_str( wp_remote_retrieve_body( $response ), $result );

		if ( isset( $result['oauth_token'] ) && isset( $result['oauth_token_secret'] ) ) {
			// Store the access token and secret in a secure place such as options
			wpcomsp_auto_flickr_importer_update_raw_setting( 'token', $result['oauth_token'] );
			wpcomsp_auto_flickr_importer_update_raw_setting( 'token_secret', $result['oauth_token_secret'] );

			// Redirect back to the settings page or display a success message
			wp_redirect( admin_url( 'options-general.php?page=wpcomsp_auto_flickr_importer-settings&success=true' ) );
			exit;
		} else {
			wp_die( 'Failed to obtain an access token.' );
		}
	}

	private function send_request( $url, $params ) {
		$url = add_query_arg( $params, $url );
		return wp_remote_get( $url, array( 'sslverify' => false ) );
	}
}
