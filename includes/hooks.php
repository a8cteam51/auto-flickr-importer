<?php

/**
 * Filters the comment author URL to link to the Flickr user profile.
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 * @param string             $comment_author_url The comment author URL.
 * @param integer|WP_Comment $comment_id         The comment ID.
 *
 * @return string
 */
function wpcomsp_auto_flickr_importer_flickr_comment_author_url( string $comment_author_url, int|WP_Comment $comment_id ): string {
	$flickr_user_id = get_comment_meta( $comment_id, '_flickr_author_nsid', true );

	if ( ! empty( $flickr_user_id ) ) {
		return 'https://flickr.com/people/' . $flickr_user_id;
	} else {
		return $comment_author_url;
	}
}
add_filter( 'get_comment_author_url', 'wpcomsp_auto_flickr_importer_flickr_comment_author_url', 10, 2 );
