<?php

defined( 'ABSPATH' ) || exit;

/**
 * Check if the credentials exist.
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 * @return boolean
 */
function wpcomsp_auto_flickr_importer_credentials_exist(): bool {
	$api_key              = wpcomsp_auto_flickr_importer_get_raw_setting( 'api_key', '' );
	$api_secret           = wpcomsp_auto_flickr_importer_get_raw_setting( 'api_secret', '' );
	$flickr_username      = wpcomsp_auto_flickr_importer_get_raw_setting( 'username', '' );
	$site_author_username = wpcomsp_auto_flickr_importer_get_raw_setting( 'site_author_username', '' );

	if ( empty( $api_key ) || empty( $api_secret ) || empty( $flickr_username ) || empty( $site_author_username ) ) {
		return false;
	}

	return true;
}

/**
 * Get post for Flickr media ID.
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 * @param string $media_id The Flickr media ID.
 *
 * @return WP_Post|null
 */
function wpcomsp_auto_flickr_importer_get_post_for_media_id( string $media_id ): ?\WP_Post {
	$post = get_posts(
		array(
			'post_status' => 'any',
			'meta_key'    => '_flickr_media_id',
			'meta_value'  => $media_id,
		)
	);

	return empty( $post ) ? null : reset( $post );
}

/**
 * Create a post for the Flickr media.
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 * @param string $user_nsid       The Flickr user NSID.
 * @param array  $media           The Flickr media.
 * @param string $content         The post content.
 * @param array  $albums_to_terms The albums to terms.
 *
 * @return integer|WP
 */
function wpcomsp_auto_flickr_importer_create_post_for_media( string $user_nsid, array $media, string $content, array $albums_to_terms ): int|WP_Error {

	$author_username = wpcomsp_auto_flickr_importer_get_raw_setting( 'site_author_username' );

	if ( $author_username ) {
		$author = get_user_by( 'login', $author_username );
	}

	$post_author = ! empty( $author->ID ) ? $author->ID : get_current_user_id();

	return wp_insert_post(
		array(
			'post_status'   => 'publish',
			'post_title'    => $media['title'],
			'post_content'  => wpcomsp_auto_flickr_importer_replace_flickr_links( $user_nsid, $content, $albums_to_terms ),
			'post_author'   => $post_author,
			'post_date'     => gmdate( 'Y-m-d H:i:s', $media['dateupload'] ),
			'post_category' => array_map( static fn( int $album_id ) => $albums_to_terms[ $album_id ], $media['categories'] ),
			'tags_input'    => explode( ' ', $media['tags'] ),
			'meta_input'    => array(
				'_flickr_media_id'  => $media['id'],
				'_flickr_datetaken' => $media['datetaken'],
			),
		),
		true
	);
}

/**
 * Replace Flickr links with WordPress links.
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 * @param string $user_nsid       The Flickr user NSID.
 * @param string $content         The content.
 * @param array  $albums_to_terms The albums to terms.
 *
 * @return string
 */
function wpcomsp_auto_flickr_importer_replace_flickr_links( string $user_nsid, string $content, array $albums_to_terms ): string {
	// albums
	preg_match_all( "/https?:\/\/(?:www.)?flickr.com\/photos\/$user_nsid\/albums\/(\d+)\/?/mui", $content, $matches, PREG_SET_ORDER );
	foreach ( $matches as $album_links ) {
		$content = str_replace(
			$album_links[0],
			get_term_link( $albums_to_terms[ $album_links[1] ] ),
			$content
		);
	}

	// photos
	preg_match_all( "/https?:\/\/(?:www.)?flickr.com\/photos\/$user_nsid\/(\d+)\/?/mui", $content, $matches, PREG_SET_ORDER );
	foreach ( $matches as $photo_links ) {
		$post = wpcomsp_auto_flickr_importer_get_post_for_media_id( $photo_links[1] );
		if ( $post instanceof WP_Post ) {
			$content = str_replace(
				$photo_links[0],
				get_permalink( $post ),
				$content
			);
		}
	}

	// sets like http://www.flickr.com/photos/{username}/sets/28089
	// to be handled manually post-import ...

	return $content;
}

/**
 * Upload Flickr media from server.
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 * @param array   $media   The Flickr media.
 * @param integer $post_id The post ID.
 *
 * @return integer|WP_Error
 */
function wpcomsp_auto_flickr_importer_upload_media_from_server( array $media, int $post_id ): int|WP_Error {
	// Clone the image because the upload process deletes it.
	$path = $media['media_path'];

	$dir = pathinfo( $path, PATHINFO_DIRNAME );
	$ext = pathinfo( $path, PATHINFO_EXTENSION );

	$copy_filename = sanitize_file_name( $media['title'] ) . '.' . $ext;
	copy( $path, $dir . '/' . $copy_filename );
	$path = $dir . '/' . $copy_filename;

	// "Upload" the image.
	$file = array(
		'name'     => $copy_filename,
		'type'     => mime_content_type( $path ),
		'tmp_name' => $path,
		'size'     => filesize( $path ),
	);

	if ( ! function_exists( 'wp_handle_sideload' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	$sideload = wp_handle_sideload(
		$file,
		array(
			'test_form' => false, // no needs to check 'action' parameter
		),
		gmdate( 'Y/m', $media['dateupload'] )
	);

	if ( ! empty( $sideload['error'] ) ) {
		return new WP_Error( '400', $sideload['error'] . ' | Media: ' . wp_json_encode( $media ) . ' | Post ID: ' . $post_id );
	}

	// Create attachment post.
	$attachment_id = wp_insert_attachment(
		array(
			'guid'           => $sideload['url'],
			'post_mime_type' => $sideload['type'],
			'post_title'     => basename( $sideload['file'] ),
			'post_content'   => '',
			'post_date'      => gmdate( 'Y-m-d H:i:s', $media['dateupload'] ),
			'post_status'    => 'inherit',
		),
		$sideload['file'],
		$post_id
	);
	if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
		if ( ! is_wp_error( $attachment_id ) ) {
			$attachment_id = new WP_Error( '400', "ERROR CREATING ATTACHMENT FOR {$media['id']}" );
		}

		return $attachment_id;
	}

	if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	wp_update_attachment_metadata(
		$attachment_id,
		wp_generate_attachment_metadata( $attachment_id, $sideload['file'] )
	);

	return $attachment_id;
}

/**
 * Replace Flickr comment photos.
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 * @param string $content The content.
 *
 * @return string
 */
function wpcomsp_auto_flickr_importer_replace_flickr_comment_photos( string $content ): string {
	//todo handle private images
	/* Won't work without an API key because these images seem to be private so they return an error code 1: Photo not found;
	preg_match_all( '/static\.?flickr\.com/.+?/(.+?)_/', $content, $matches, PREG_SET_ORDER );
	foreach ( $matches as $photo_id ) {
		$flickr_rest_url  = "https://www.flickr.com/services/rest/?method=flickr.photos.getSizes&api_key=XX&photo_id={$photo_id}&format=json&nojsoncallback=1";
		$flickr_file_json = wp_remote_retrieve_body( wp_remote_get( $flickr_rest_url ) );
	}
	*/

	return $content;
}

/**
 * Insert a comment if it does not exist.
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 * @param string $user_nsid The Flickr user NSID.
 * @param string $media_id  The media ID.
 * @param array  $comment   Comment data.
 *
 * @return boolean
 */
function wpcomsp_insert_comment_if_not_exists( string $user_nsid, string $media_id, array $comment ): bool {
	$post = wpcomsp_auto_flickr_importer_get_post_for_media_id( $media_id );

	if ( empty( $post ) ) {
		return false;
	}

	$post_id = $post->ID;

	if ( comment_exists( $comment['authorname'], gmdate( 'Y-m-d H:i:s', $comment['datecreate'] ) ) ) {
		return false;
	}

	$result = wp_insert_comment(
		array(
			'comment_date'    => gmdate( 'Y-m-d H:i:s', $comment['datecreate'] ),
			'comment_content' => $comment['_content'],
			'comment_author'  => $comment['authorname'],
			'user_id'         => $user_nsid === $comment['author'] ? $user_nsid : 0,
			'comment_post_ID' => $post_id,
			'comment_meta'    => array(
				'_flickr_comment_id'      => $comment['id'],
				'_flickr_author_nsid'     => $comment['author'],
				'_flickr_author_realname' => $comment['realname'],
			),
		)
	);

	return ! empty( $result );
}

/**
 * Gets data from a remote file.
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 * @param string $file_url The file URL.
 *
 * @return string|null
 */
function wpcomsp_auto_flickr_importer_get_remote_file( string $file_url ): ?string {

	$response = wp_remote_get( $file_url );

	// Check for errors
	if ( is_wp_error( $response ) ) {
		// Handle the error
		$error_message = $response->get_error_message();
		wpcomsp_auto_flickr_importer_write_log( 'Unable to fetch the file: ' . $file_url . ' | Error: ' . $error_message );

		return null;
	}

	// Retrieve the body of the response
	$body = wp_remote_retrieve_body( $response );

	if ( empty( $body ) ) {
		wpcomsp_auto_flickr_importer_write_log( 'The file is empty or could not be read: ' . $file_url . ' | Error: ' . wp_json_encode( $response ) );
		return null;
	}

	return $body;
}

/**
 * Gets data from a local file.
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 * @param string $file_url The file URL.
 *
 * @return array|null
 */
function wpcomsp_auto_flickr_importer_get_local_file( string $file_url ): ?array {

	global $wp_filesystem;

	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	WP_Filesystem();

	$file_contents = $wp_filesystem->get_contents( $file_url );

	if ( false === $file_contents ) {
		wpcomsp_auto_flickr_importer_write_log( 'Unable to fetch the file: ' . $file_url );
		return null;
	}

	$file_contents_decoded = json_decode( $file_contents, true );

	if ( json_last_error() !== JSON_ERROR_NONE ) {
		wpcomsp_auto_flickr_importer_write_log( 'Failed to decode JSON. ' . json_last_error_msg() );

		return null;
	}

	return $file_contents_decoded;
}

/**
 * Custom error log.
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 * @param string|array|object $log The log.
 *
 * @return void
 */
function wpcomsp_auto_flickr_importer_write_log( string|array|object $log ): void {
	if ( is_array( $log ) || is_object( $log ) ) {
		// phpcs:ignore
		error_log( 'Flickr Importer: ' . print_r( $log, true ) );
	} else {
		// phpcs:ignore
		error_log( 'Flickr Importer: ' . $log );
	}
}

/**
 * Initializes the WP_Filesystem API and returns the file system object.
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 * @return WP_Filesystem_Base|false The WP_Filesystem object on success, false on failure.
 */
function wpcomsp_auto_flickr_importer_initialize_wp_filesystem(): WP_Filesystem_Base|false {
	global $wp_filesystem;

	// Load the required file for WP_Filesystem if not already loaded
	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	// Initialize the WP_Filesystem
	if ( WP_Filesystem() ) {
		return $wp_filesystem;
	}

	// If initialization fails, return false
	return false;
}
