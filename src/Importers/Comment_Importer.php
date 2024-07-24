<?php

namespace WPCOMSpecialProjects\AutoFlickrImporter\Importers;

use WP_Comment;

defined( 'ABSPATH' ) || exit;

/**
 * Class for importing Flickr data.
 */
class Comment_Importer {

	/**
	 * Start the import process.
	 *
	 * @return void
	 */
	public function start_import(): void {

		$username = wpcomsp_auto_flickr_importer_get_raw_setting( 'username' );

		$flickr_user = wpcomsp_auto_flickr_importer_get_flickr_user_by_username( $username );
		if ( empty( $flickr_user ) || empty( $flickr_user->nsid ) ) {
			exit;
		}

		$photos = wpcomsp_auto_flickr_importer_get_flickr_photos_with_recent_comments( $flickr_user->nsid );

		if ( empty( $photos ) ) {
			return;
		}

		foreach ( $photos as $photo ) {
			$data = wpcomsp_auto_flickr_importer_get_flickr_comments_for_photo( $photo->id );

			foreach ( $data->comment as $comment ) {
				$this->insert_comment_if_not_exists( $flickr_user->nsid, $photo->id, (array) $comment );
			}
		}
	}

	/**
	 * Insert a comment if it does not exist.
	 *
	 * @param string $user_nsid The Flickr user NSID.
	 * @param string $media_id  The media ID.
	 * @param array  $comment   Comment data.
	 *
	 * @return boolean
	 */
	private function insert_comment_if_not_exists( $user_nsid, $media_id, $comment ) {
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
				'user_id'         => $user_nsid === $comment['author'] ? 0 : 0,
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
}
