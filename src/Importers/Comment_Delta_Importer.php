<?php

namespace WPCOMSpecialProjects\AutoFlickrImporter\Importers;

defined( 'ABSPATH' ) || exit;

/**
 * Class for importing Flickr comment data.
 *
 * @since 1.0.0
 * @version 1.0.0
 */
class Comment_Delta_Importer {

	/**
	 * The maximum number items to import per page.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 *
	 * @var int
	 */
	protected int $per_page = 300;

	/**
	 * Run the comment delta import.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 *
	 * @param integer $page The page number.
	 *
	 * @return array|null
	 */
	public function run_import( int $page = 1 ): ?array {
		global $wpdb;
		$args = array();

		$username = wpcomsp_auto_flickr_importer_get_raw_setting( 'username' );

		$flickr_user = wpcomsp_auto_flickr_importer_get_flickr_user_by_username( $username );
		if ( empty( $flickr_user ) || empty( $flickr_user->nsid ) ) {
			return null;
		}

		// Calculate the offset
		$offset = ( $page - 1 ) * $this->per_page;

		// Get the total number of photos
		$total_photos = $wpdb->get_var( "SELECT COUNT(DISTINCT(meta_value)) FROM $wpdb->postmeta WHERE meta_key = '_flickr_media_id' ORDER BY meta_value" );

		// Calculate the total number of pages
		$total_pages = ceil( $total_photos / $this->per_page );

		// Fetch the photos with pagination
		$photos = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT(meta_value) FROM $wpdb->postmeta WHERE meta_key = '_flickr_media_id' ORDER BY meta_value LIMIT %d OFFSET %d",
				$this->per_page,
				$offset
			)
		);

		if ( empty( $photos ) ) {
			return null;
		}

		foreach ( $photos as $photo ) {
			$data = wpcomsp_auto_flickr_importer_get_flickr_comments_for_photo( $photo->meta_value, $args );
			if ( ! empty( $data->comment ) ) {
				foreach ( $data->comment as $comment ) {
					wpcomsp_insert_comment_if_not_exists( $flickr_user->nsid, $photo->meta_value, (array) $comment );
				}
			}

			sleep( 0.5 ); // Sleep for half a second to avoid rate limiting
		}

		if ( $page < $total_pages ) {
			++$page;
			return $this->next_args( $page );
		}

		return null;
	}

	/**
	 * Create the next arguments for the import.
	 *
	 * @param integer $page The page number.
	 *
	 * @return array
	 */
	private function next_args( int $page = 1 ): array {
		return array(
			'page'             => $page,
			'latest_timestamp' => '',
		);
	}
}
