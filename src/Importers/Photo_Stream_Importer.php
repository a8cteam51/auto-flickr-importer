<?php

namespace WPCOMSpecialProjects\AutoFlickrImporter\Importers;

use WP_Filesystem_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Class for scrapping photos from a Flickr account.
 *
 * @since 1.0.0
 * @version 1.0.0
 */
class Photo_Stream_Importer {

	/**
	 * The user ID of the Flickr account to scrap.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 *
	 * @var string|null
	 */
	protected ?string $flickr_user_id = null;

	/**
	 * The maximum number items to import per page.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 *
	 * @var int
	 */
	protected int $per_page = 150;

	/**
	 * The latest timestamp the data was imported at.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 *
	 * @var null|int
	 */
	protected ?int $latest_timestamp = null;

	/**
	 * The path to the data directory.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 */
	const DATA_PATH = AUTO_FLICKR_IMPORTER_UPLOADS_PATH . 'data';

	/**
	 * Run the content download from Flickr.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 *
	 * @param string       $action           The action to run.
	 * @param integer      $page             The page to start from.
	 * @param integer|null $latest_timestamp The latest timestamp to start from.
	 *
	 * @return array|null
	 */
	public function run_import( string $action, int $page = 1, int|null $latest_timestamp = null ): ?array {

		$wp_filesystem = wpcomsp_auto_flickr_importer_initialize_wp_filesystem();

		if ( false === $wp_filesystem ) {
			wpcomsp_auto_flickr_importer_write_log( 'Can\'t initialize WP_Filesystem. Aborting...' );
			return null;
		}

		$username    = wpcomsp_auto_flickr_importer_get_raw_setting( 'username' );
		$flickr_user = wpcomsp_auto_flickr_importer_get_flickr_user_by_username( $username );

		if ( empty( $flickr_user ) || empty( $flickr_user->nsid ) ) {
			wpcomsp_auto_flickr_importer_write_log( 'Can\'t fetch Flickr user data. Aborting...' );
			return null;
		}

		$this->flickr_user_id   = $flickr_user->nsid;
		$this->latest_timestamp = $latest_timestamp;

		if ( $this->latest_timestamp ) {
			if ( ! $this->check_if_new_photos_exist() ) {
				return null;
			}
		}

		wpcomsp_auto_flickr_importer_update_raw_setting( 'import_running', true );

		switch ( $action ) {
			case 'run_initial_import_photosets':
				return $this->download_photosets( $wp_filesystem );
			case 'run_initial_import_media':
				return $this->download_media_data( $wp_filesystem, $page );
			case 'run_initial_import_data':
				return $this->insert_data_from_flickr( $wp_filesystem, $page );
			case 'run_initial_import_cleanup':
				$this->clean_up_files( $wp_filesystem );
		}

		return null;
	}

	/**
	 * Check if new photos exist after a given timestamp.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 *
	 * @return boolean
	 */
	private function check_if_new_photos_exist(): bool {
		$args = array(
			'per_page'        => 1,
			'page'            => 1,
			'min_upload_date' => $this->latest_timestamp,
		);

		$photos = wpcomsp_auto_flickr_importer_get_flickr_photos_for_user(
			$this->flickr_user_id,
			$args
		);

		if ( $photos && 0 < $photos->total ) {
			return true;
		}

		return false;
	}

	/**
	 * Download the photosets data from Flickr.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 *
	 * @param WP_Filesystem_Base $wp_filesystem The WP Filesystem object.
	 *
	 * @return array|null
	 */
	private function download_photosets( WP_Filesystem_Base $wp_filesystem ): ?array {

		$photosets = wpcomsp_auto_flickr_importer_get_flickr_photosets_for_user( $this->flickr_user_id );

		if ( ! is_null( $photosets ) ) {
			$photosets_data_directory = self::DATA_PATH . '/photosets';

			if ( ! file_exists( $photosets_data_directory ) ) {
				if ( ! wp_mkdir_p( $photosets_data_directory ) ) {
					wpcomsp_auto_flickr_importer_write_log( 'Can\'t create directory: ' . $photosets_data_directory );
					wpcomsp_auto_flickr_importer_update_raw_setting( 'import_running', false );
					return null;
				}
			}

			foreach ( $photosets->photoset as $photoset ) {
				$data_file = $photosets_data_directory . "/{$photoset->id}.json";
				if ( ! file_exists( $data_file ) ) {
					$photos = $this->fetch_photoset_photos( $photoset );
					$data   = wp_json_encode(
						array(
							'photoset' => $photoset,
							'photos'   => $photos,
						),
						JSON_PRETTY_PRINT
					);

					// Write the data to the file
					if ( ! $wp_filesystem->put_contents( $data_file, $data, FS_CHMOD_FILE ) ) {
						wpcomsp_auto_flickr_importer_write_log( 'Failed to write data to file: ' . $data_file );
					}
				}
			}
		}

		return $this->next_args( 'run_initial_import_media' );
	}

	/**
	 * Fetch the photos for a photoset.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 *
	 * @param object $photoset The photoset object.
	 *
	 * @return array|void
	 */
	private function fetch_photoset_photos( object $photoset ): ?array {
		$photos       = array();
		$current_page = 1;

		do {
			$photoset_photos = wpcomsp_auto_flickr_importer_get_flickr_photos_for_photoset(
				$photoset->id,
				array(
					'per_page' => 500,
					'page'     => $current_page,
				)
			);

			if ( is_null( $photoset_photos ) ) {
				break;
			}

			++$current_page;
			$has_next_page = $photoset_photos->page < $photoset_photos->pages;
		} while ( $has_next_page );

		return array_merge( ...$photos );
	}

	/**
	 * Download the media data from Flickr.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 *
	 * @param WP_Filesystem_Base $wp_filesystem The WP Filesystem object.
	 * @param integer            $page          The page number.
	 *
	 * @return array|null
	 */
	private function download_media_data( WP_Filesystem_Base $wp_filesystem, int $page ): ?array {

		$extras = array( 'url_o', 'description', 'license', 'date_upload', 'date_taken', 'original_format', 'last_update', 'geo', 'tags', 'machine_tags', 'views', 'media' );
		$extras = implode( ',', $extras );

		$args = array(
			'extras'   => $extras,
			'per_page' => $this->per_page,
			'page'     => $page,
		);

		if ( $this->latest_timestamp ) {
			$args['min_upload_date'] = $this->latest_timestamp;
		}

		wpcomsp_auto_flickr_importer_update_raw_setting( 'latest_import_time', time() );

		$photos = wpcomsp_auto_flickr_importer_get_flickr_photos_for_user(
			$this->flickr_user_id,
			$args
		);

		if ( is_null( $photos ) ) {
			return $this->next_args( 'initial_import_data' );
		}

		foreach ( $photos->photo as $photo ) {
			$media_data_directory = self::DATA_PATH . "/media/$photo->media/$photo->id";

			if ( ! file_exists( $media_data_directory ) ) {
				if ( ! wp_mkdir_p( $media_data_directory ) ) {
					wpcomsp_auto_flickr_importer_write_log( 'Can\'t create directory' . $media_data_directory );
					continue;
				}
			}

			// Write the data to the file
			if ( ! $wp_filesystem->put_contents( $media_data_directory . '/meta.json', wp_json_encode( $photo, JSON_PRETTY_PRINT ), FS_CHMOD_FILE ) ) {
				wpcomsp_auto_flickr_importer_write_log( 'Failed to write data to file: ' . $media_data_directory . '/meta.json' );
				continue;
			}

			// Save photo comments.
			$comments = wpcomsp_auto_flickr_importer_get_flickr_comments_for_photo( $photo->id );
			if ( ! is_null( $comments ) ) {
				// Write the data to the file
				if ( ! $wp_filesystem->put_contents( $media_data_directory . '/comments.json', wp_json_encode( $comments->comment ?? array(), JSON_PRETTY_PRINT ), FS_CHMOD_FILE ) ) {
					wpcomsp_auto_flickr_importer_write_log( 'Failed to write data to file: ' . $media_data_directory . '/comments.json' );
				}
			}

			$media_url = '';

			// Download photo/video file.
			if ( 'photo' === $photo->media ) {
				$media_url = $photo->url_o;
			} else { // Video.
				$media_sizes = wpcomsp_auto_flickr_importer_get_flickr_photo_sizes( $photo->id );
				if ( ! is_null( $media_sizes ) ) {
					foreach ( $media_sizes->size as $size ) {
						if ( 'video' === $size->media && $size->height === $photo->height_o ) {
							$media_url = $size->source;
							break;
						}
					}
				}
			}

			$media_file = wpcomsp_auto_flickr_importer_get_remote_file( $media_url );
			if ( empty( $media_file ) ) {
				continue;
			}

			// Write the data to the file
			if ( ! $wp_filesystem->put_contents( $media_data_directory . '/media.' . $photo->originalformat, $media_file, FS_CHMOD_FILE ) ) {
				wpcomsp_auto_flickr_importer_write_log( 'Failed to write data to file: ' . $media_data_directory . '/media.' . $photo->originalformat );
				continue;
			}

			sleep( 1 ); // Flickr API rate limit. We can make a maximum of 3600 requests per hour or 1 per second.
		}

		if ( $page < $photos->pages ) {
			++$page;

			return $this->next_args( 'run_initial_import_media', $page );
		}

		return $this->next_args( 'run_initial_import_data' );
	}

	/**
	 * Insert scraped data into the WordPress database.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 *
	 * @param WP_Filesystem_Base $wp_filesystem The WP Filesystem object.
	 * @param integer            $page          The page number.
	 *
	 * @return array|null
	 */
	private function insert_data_from_flickr( WP_Filesystem_Base $wp_filesystem, int $page ): ?array {
		$albums = array();
		$photos = array();
		$videos = array();

		// Parse all the data files.
		if ( file_exists( self::DATA_PATH . '/albums.json' ) ) {
			$albums = wpcomsp_auto_flickr_importer_get_local_file( self::DATA_PATH . '/albums.json' );
		} else {
			$album_files = glob( self::DATA_PATH . '/photosets/*' );

			if ( $album_files ) {
				foreach ( $album_files as $album_file ) {
					$album = wpcomsp_auto_flickr_importer_get_local_file( $album_file );

					if ( is_null( $album ) ) {
						continue;
					}

					$albums[ $album['photoset']['id'] ] = $album;
				}

				// Write the data to the file
				if ( ! $wp_filesystem->put_contents( self::DATA_PATH . '/albums.json', wp_json_encode( $albums ), FS_CHMOD_FILE ) ) {
					wpcomsp_auto_flickr_importer_write_log( 'Failed to write data to file: ' . self::DATA_PATH . '/albums.json' );
				}
			}
		}

		if ( file_exists( self::DATA_PATH . '/media/photos.json' ) ) {
			$photos = wpcomsp_auto_flickr_importer_get_local_file( self::DATA_PATH . '/media/photos.json' );
		} else {
			$photo_folders = glob( self::DATA_PATH . '/media/photo/*', GLOB_ONLYDIR );

			if ( $photo_folders ) {
				foreach ( $photo_folders as $photo_folder ) {
					$meta = wpcomsp_auto_flickr_importer_get_local_file( $photo_folder . '/meta.json' );

					if ( is_null( $meta ) ) {
						continue;
					}

					$comments = wpcomsp_auto_flickr_importer_get_local_file( $photo_folder . '/comments.json' );

					$media_path = glob( $photo_folder . '/media.*' );

					if ( empty( $media_path[0] ) ) {
						wpcomsp_auto_flickr_importer_write_log( 'No media path found for photo ' . $meta['id'] );
						continue;
					}

					$media_path = $media_path[0];

					$categories = array();
					foreach ( $albums as $id => $album ) {
						$album_media = $album['photos'];
						foreach ( $album_media as $media ) {
							if ( $media['id'] === $meta['id'] ) {
								$categories[] = $id;
								continue 2;
							}
						}
					}

					$photos[ $meta['id'] ] = array_merge(
						$meta,
						array(
							'categories' => $categories,
							'comments'   => $comments,
							'media_path' => $media_path,
						)
					);
				}

				usort( $photos, fn( $a, $b ) => $a['dateupload'] <=> $b['dateupload'] ); // "oldest" to "latest"
				// Write the data to the file
				if ( ! $wp_filesystem->put_contents( self::DATA_PATH . '/media/photos.json', wp_json_encode( $photos ), FS_CHMOD_FILE ) ) {
					wpcomsp_auto_flickr_importer_write_log( 'Failed to write data to file: ' . self::DATA_PATH . '/media/photos.json' );
				}
			}
		}

		if ( file_exists( self::DATA_PATH . '/media/videos.json' ) ) {
			$videos = wpcomsp_auto_flickr_importer_get_local_file( self::DATA_PATH . '/media/videos.json' );
		} else {
			$video_folders = glob( self::DATA_PATH . '/media/video/*', GLOB_ONLYDIR );

			if ( $video_folders ) {
				foreach ( $video_folders as $video_folder ) {
					$meta = wpcomsp_auto_flickr_importer_get_local_file( $video_folder . '/meta.json' );

					if ( is_null( $meta ) ) {
						continue;
					}

					$comments = wpcomsp_auto_flickr_importer_get_local_file( $video_folder . '/comments.json' );

					$media_path = glob( $video_folder . '/media.*' );
					$media_path = $media_path[0];

					$categories = array();
					foreach ( $albums as $id => $album ) {
						$album_media = $album['photos'];
						foreach ( $album_media as $media ) {
							if ( $media['id'] === $meta['id'] ) {
								$categories[] = $id;
								continue 2;
							}
						}
					}

					$videos[ $meta['id'] ] = array_merge(
						$meta,
						array(
							'categories' => $categories,
							'comments'   => $comments,
							'media_path' => $media_path,
						)
					);
				}

				usort( $videos, fn( $a, $b ) => $a['dateupload'] <=> $b['dateupload'] ); // "oldest" to "latest"
				// Write the data to the file
				if ( ! $wp_filesystem->put_contents( self::DATA_PATH . '/media/videos.json', wp_json_encode( $videos ), FS_CHMOD_FILE ) ) {
					wpcomsp_auto_flickr_importer_write_log( 'Failed to write data to file: ' . self::DATA_PATH . '/media/videos.json' );
				}
			}
		}

		$albums_to_terms = array();
		foreach ( $albums as $id => $album ) {
			$term = get_term_by( 'name', $album['photoset']['title']['_content'], 'category' );
			if ( ! $term ) {
				$term = wp_insert_term(
					$album['photoset']['title']['_content'],
					'category',
					array(
						'description' => $album['photoset']['description']['_content'],
					)
				);
				update_term_meta( $term['term_id'], '_flickr_album_id', $id );

				$term = get_term_by( 'id', $term['term_id'], 'category' );
			}

			$albums_to_terms[ $id ] = $term->term_id;
		}

		$videos_imported = false;

		$total_pages_videos = ceil( count( $videos ) / $this->per_page );

		if ( $page > $total_pages_videos ) {
			$videos_imported = true;
		}

		$videos = array_slice( $videos, ( $page - 1 ) * $this->per_page, $this->per_page );

		// Import videos.
		if ( $videos ) {
			foreach ( $videos as $video ) {
				if ( ! empty( wpcomsp_auto_flickr_importer_get_post_for_media_id( $video['id'] ) ) ) {
					wpcomsp_auto_flickr_importer_write_log( "Skipping video {$video['id']}..." );
					continue; // Already imported.
				}

				$post_id       = wpcomsp_auto_flickr_importer_create_post_for_media( $this->flickr_user_id, $video, $video['description']['_content'], $albums_to_terms );
				$attachment_id = wpcomsp_auto_flickr_importer_upload_media_from_server( $video, $post_id );
				if ( is_wp_error( $attachment_id ) ) {
					$attachment_id = wp_json_encode( $attachment_id );
					wpcomsp_auto_flickr_importer_write_log( "Error uploading video attachment {$attachment_id}..." );
					continue;
				}

				wp_update_post(
					array(
						'ID'           => $post_id,
						'post_content' => '<!-- wp:video {"id":' . $attachment_id . '} -->
						<figure class="wp-block-video"><video controls src="' . wp_get_attachment_url( $attachment_id ) . '" playsinline></video></figure>
						<!-- /wp:video -->' . wpcomsp_auto_flickr_importer_replace_flickr_links( $this->flickr_user_id, $video['description']['_content'], $albums_to_terms ),
					)
				);
			}
		}

		$total_pages = ceil( count( $photos ) / $this->per_page );

		if ( $page > $total_pages && $videos_imported ) {
			return null;
		}

		$photos = array_slice( $photos, ( $page - 1 ) * $this->per_page, $this->per_page );

		foreach ( $photos as $photo ) {
			$post_content = wpautop( $photo['description']['_content'] );
			$post_content = '<!-- wp:post-featured-image {"align":"full"} /-->' . $post_content;

			if ( ! empty( wpcomsp_auto_flickr_importer_get_post_for_media_id( $photo['id'] ) ) ) {
				wpcomsp_auto_flickr_importer_write_log( "Skipping photo {$photo['id']}..." );
				continue;
			}

			$post_id       = wpcomsp_auto_flickr_importer_create_post_for_media( $this->flickr_user_id, $photo, $post_content, $albums_to_terms );
			$attachment_id = wpcomsp_auto_flickr_importer_upload_media_from_server( $photo, $post_id );
			if ( is_wp_error( $attachment_id ) ) {
				$attachment_id = wp_json_encode( $attachment_id );
				wpcomsp_auto_flickr_importer_write_log( "Error uploading photo attachment {$attachment_id}..." );
				continue;
			}

			set_post_thumbnail( $post_id, $attachment_id );

			// Ensure we're starting with a clean comments slate.
			$post_comments = get_comments( array( 'post_id' => $post_id ) );
			foreach ( $post_comments as $comment ) {
				wp_delete_comment( $comment, true );
			}

			foreach ( $photo['comments'] as $comment ) {
				$result = wpcomsp_insert_comment_if_not_exists( $this->flickr_user_id, $photo['id'], $photo['comments'] );

				if ( false === $result ) {
					wpcomsp_auto_flickr_importer_write_log( 'Failed to import comment ' . $comment['id'] . " on post $post_id<br/><br/>" );
				}
			}
		}

		++$page;
		return $this->next_args( 'run_initial_import_data', $page );
	}

	/**
	 * Clean up the files after the import.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 *
	 * @param WP_Filesystem_Base $wp_filesystem The WP Filesystem object.
	 *
	 * @return true|\WP_Error
	 */
	public function clean_up_files( WP_Filesystem_Base $wp_filesystem ): bool|\WP_Error {

		// Initialize the WordPress filesystem, no more using 'file-put-contents' function
		$access_type = get_filesystem_method();
		if ( 'direct' === $access_type ) {
			$creds = request_filesystem_credentials( site_url() . '/wp-admin/', '', false, false, array() );

			// Initialize the WP filesystem, no more using file_put_contents function
			if ( ! WP_Filesystem( $creds ) ) {
				// The credentials were not available or incorrect
				return new \WP_Error( 'filesystem_error', 'Cannot initialize the file system' );
			}

			if ( file_exists( self::DATA_PATH ) ) {
				if ( $wp_filesystem->rmdir( self::DATA_PATH, true ) ) {
					return true; // Successfully removed the directory
				} else {
					return new \WP_Error( 'filesystem_error', 'Failed to remove the directory' );
				}
			} else {
				return new \WP_Error( 'filesystem_error', 'The directory does not exist' );
			}
		} else {
			// Direct file I/O is not available, handle as needed
			return new \WP_Error( 'filesystem_error', 'Direct file access not available' );
		}
	}

	/**
	 * Create the next arguments for the import.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 *
	 * @param string  $action The action to run.
	 * @param integer $page   The page to start from.
	 *
	 * @return array
	 */
	private function next_args( string $action, int $page = 1 ) {
		return array(
			'action'           => $action,
			'page'             => $page,
			'latest_timestamp' => $this->latest_timestamp,
		);
	}
}
