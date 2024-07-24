<?php

namespace WPCOMSpecialProjects\AutoFlickrImporter\API;

defined( 'ABSPATH' ) || exit;

/**
 * Performs the calls to the Flickr API and parses the responses.
 */
final class Flickr_API_Helper {
	// region FIELDS AND CONSTANTS

	/**
	 * The base URL for the Flickr API.
	 *
	 * @link    https://www.flickr.com/services/api/request.rest.html
	 */
	private const BASE_URL = 'https://api.flickr.com/services/rest/';

	// endregion

	// region METHODS

	/**
	 * Calls a given endpoint on the Flickr API and returns the response.
	 *
	 * @param   string $endpoint  The endpoint to call.
	 * @param   array  $arguments The arguments to send with the request.
	 * @param   string $method    The HTTP method to use. One of 'GET', 'POST', 'PUT', 'DELETE'.
	 * @param   array  $params    The parameters to send with the request.
	 *
	 * @return  object|object[]|null
	 */
	public static function call_api( string $endpoint, array $arguments, string $method = 'GET', array $params = array() ) {
		$url = self::get_request_url( $endpoint, $arguments );

		$args = array(
			'method'  => $method,
			'timeout' => 60,
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'body'    => empty( $params ) ? null : wp_json_encode( $params ),
		);

		// Choose the correct function based on method
		if ( 'GET' === $method ) {
			$response = wp_remote_get( $url, $args );
		} else {
			$response = wp_remote_request( $url, $args );
		}

		if ( is_wp_error( $response ) ) {
			wpcomsp_auto_flickr_importer_write_log( 'Error calling Flickr API: ' . $response->get_error_message() );
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body );

		if ( $data && 'ok' === $data->stat ) {
			return $data;
		}

		return null;
	}

	// endregion

	// region HELPERS

	/**
	 * Prepares the fully qualified request URL for the given endpoint.
	 *
	 * @param   string $endpoint  The endpoint to call.
	 * @param   array  $arguments The arguments to send with the request.
	 *
	 * @return  string
	 */
	private static function get_request_url( string $endpoint, array $arguments ): string {
		// Retrieve API key and token from the database
		$api_key = wpcomsp_auto_flickr_importer_get_raw_setting( 'api_key' );

		$params = array(
			'method'         => $endpoint,
			'api_key'        => $api_key,
			'format'         => 'json',
			'nojsoncallback' => 1,
		) + $arguments;

		return add_query_arg( $params, self::BASE_URL );
	}

	// endregion
}
