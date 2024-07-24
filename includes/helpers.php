<?php

defined( 'ABSPATH' ) || exit;

/**
 * Check if the credentials exist.
 *
 * @return boolean
 */
function wpcomsp_auto_flickr_importer_credentials_exist(): bool {
	$api_key    = wpcomsp_auto_flickr_importer_get_raw_setting( 'api_key', '' );
	$api_secret = wpcomsp_auto_flickr_importer_get_raw_setting( 'api_secret', '' );

	if ( empty( $api_key ) || empty( $api_secret ) ) {
		return false;
	}

	return true;
}

/**
 * Get post for Flickr media ID.
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
		return new WP_Error( '', $sideload['error'] );
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
			$attachment_id = new WP_Error( '', "ERROR CREATING ATTACHMENT FOR {$media['id']}" );
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
 * Custom error log.
 *
 * @param string $log The log.
 *
 * @return void
 */
function wpcomsp_auto_flickr_importer_write_log( string $log ): void {
	if ( true === WP_DEBUG ) {
		if ( is_array( $log ) || is_object( $log ) ) {
			// phpcs:ignore
			error_log( print_r( $log, true ) );
		} else {
			// phpcs:ignore
			error_log( $log );
		}
	}
}
