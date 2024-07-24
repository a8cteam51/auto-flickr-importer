<?php

namespace WPCOMSpecialProjects\AutoFlickrImporter\Importers;

defined( 'ABSPATH' ) || exit;

/**
 * Class for scrapping photos from a Flickr account.
 */
class Photo_Stream_Importer {
	/**
	 * The user ID of the Flickr account to scrap.
	 *
	 * @var string|null
	 */
	protected ?string $flickr_user_id = null;

	/**
	 * The maximum number of media files to scrap.
	 *
	 * @var int|null
	 */
	protected ?int $limit = null;

	/**
	 * The path to the data directory.
	 */
	const DATA_PATH = AUTO_FLICKR_IMPORTER_PATH . 'data';

	/**
	 * Run the content download from Flickr.
	 *
	 * @param integer|null $limit Limit the number of media files to scrap.
	 *
	 * @return void
	 */
	public function start_import( int $limit = null ): void {
		$this->limit = $limit;

		$initial_import = wpcomsp_auto_flickr_importer_get_raw_setting( 'initial_import' );

		if ( $initial_import ) {
			$this->limit = null;
		}

		$username    = wpcomsp_auto_flickr_importer_get_raw_setting( 'username' );
		$flickr_user = wpcomsp_auto_flickr_importer_get_flickr_user_by_username( $username );

		if ( empty( $flickr_user ) || empty( $flickr_user->nsid ) ) {
			exit;
		}

		$this->flickr_user_id = $flickr_user->nsid;
		$photosets            = wpcomsp_auto_flickr_importer_get_flickr_photosets_for_user( $this->flickr_user_id );

		if ( is_null( $photosets ) ) {
			exit;
		}

		$photosets_data_directory = self::DATA_PATH . '/photosets';
		if ( ! file_exists( $photosets_data_directory ) && ! mkdir( $photosets_data_directory, 0777, true ) && ! is_dir( $photosets_data_directory ) ) {
			exit;
		}

		foreach ( $photosets->photoset as $photoset ) {
			$data_file = $photosets_data_directory . "/{$photoset->id}.json";
			if ( ! file_exists( $data_file ) ) {
				$photos = $this->fetch_photoset_photos( $photoset );
				file_put_contents(
					$data_file,
					wp_json_encode(
						array(
							'photoset' => $photoset,
							'photos'   => $photos,
						),
						JSON_PRETTY_PRINT
					)
				);
			}
		}

		// Download photos/videos information.
		$this->download_media_data();

		// Import the files we just scraped.
		$this->insert_data_from_flickr( $flickr_user->nsid );

		// Clean up files.
		$this->clean_up_files();
	}

	/**
	 * Download the media data from Flickr.
	 *
	 * @return void
	 */
	private function download_media_data(): void {
		$current_page = 1;
		do {
			$extras = array( 'url_o', 'description', 'license', 'date_upload', 'date_taken', 'original_format', 'last_update', 'geo', 'tags', 'machine_tags', 'views', 'media' );
			$extras = implode( ',', $extras );

			$photos = wpcomsp_auto_flickr_importer_get_flickr_photos_for_user(
				$this->flickr_user_id,
				array(
					'extras'   => $extras,
					'per_page' => 500,
					'page'     => $current_page,
				)
			);
			if ( is_null( $photos ) ) {
				exit;
			}

			foreach ( $photos->photo as $photo ) {
				$media_data_directory = self::DATA_PATH . "/media/$photo->media/$photo->id";
				if ( ! file_exists( $media_data_directory ) && ! mkdir( $media_data_directory, 0777, true ) && ! is_dir( $media_data_directory ) ) {
					exit;
				}

				// Save photo meta.
				file_put_contents(
					$media_data_directory . '/meta.json',
					wp_json_encode( $photo, JSON_PRETTY_PRINT )
				);

				// Save photo comments.
				$comments = wpcomsp_auto_flickr_importer_get_flickr_comments_for_photo( $photo->id );
				if ( is_null( $comments ) ) {
					exit;
				}

				file_put_contents(
					$media_data_directory . '/comments.json',
					wp_json_encode( $comments->comment ?? array(), JSON_PRETTY_PRINT )
				);

				// Download photo/video file.
				if ( 'photo' === $photo->media ) {
					$media_url = $photo->url_o;
				} else { // Video.
					$media_sizes = wpcomsp_auto_flickr_importer_get_flickr_photo_sizes( $photo->id );
					if ( is_null( $media_sizes ) ) {
						exit;
					}

					foreach ( $media_sizes->size as $size ) {
						if ( 'video' === $size->media && $size->height === $photo->height_o ) {
							$media_url = $size->source;
							break;
						}
					}
				}

				$media_file = file_get_contents( $media_url );
				if ( empty( $media_file ) ) {
					exit;
				}

				if ( false === file_put_contents( $media_data_directory . '/media.' . $photo->originalformat, $media_file ) ) {
					exit;
				}

				if ( $this->limit ) {
					--$this->limit;
					if ( 0 === $this->limit ) {
						break;
					}
				}
				sleep( 2 ); // Flickr API rate limit. We can make a maximum of 3600 requests per hour or 1 per second.
			}
			++$current_page;
			$has_next_page = 0 !== $this->limit && $photos->page < $photos->pages;
		} while ( $has_next_page );
	}

	/**
	 * Fetch the photos for a photoset.
	 *
	 * @param object $photoset The photoset object.
	 *
	 * @return array|void
	 */
	private function fetch_photoset_photos( $photoset ): ?array {
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
				exit;
			}

			$photos[] = $photoset_photos->photo;
			++$current_page;
			$has_next_page = $photoset_photos->page < $photoset_photos->pages;
		} while ( $has_next_page );

		return array_merge( ...$photos );
	}

	/**
	 * Insert scraped data into the WordPress database.
	 *
	 * @param string $user_nsid The Flickr user NSID.
	 *
	 * @return void
	 */
	private function insert_data_from_flickr( string $user_nsid ): void {
		$albums = array();
		$photos = array();
		$videos = array();

		// Parse all the data files.
		if ( file_exists( self::DATA_PATH . '/albums.json' ) ) {
			$albums = file_get_contents( self::DATA_PATH . '/albums.json' );
			$albums = json_decode( $albums, true );
		} else {
			$album_files = glob( self::DATA_PATH . '/photosets/*' );

			if ( $album_files ) {
				foreach ( $album_files as $album_file ) {
					$album = file_get_contents( $album_file );
					$album = json_decode( $album, true );

					$albums[ $album['photoset']['id'] ] = $album;
				}

				file_put_contents( self::DATA_PATH . '/albums.json', wp_json_encode( $albums ) );
			}
		}

		if ( file_exists( self::DATA_PATH . '/media/photos.json' ) ) {
			$photos = file_get_contents( self::DATA_PATH . '/media/photos.json' );
			$photos = json_decode( $photos, true );
		} else {
			$photo_folders = glob( self::DATA_PATH . '/media/photo/*', GLOB_ONLYDIR );

			if ( $photo_folders ) {
				foreach ( $photo_folders as $photo_folder ) {
					$meta = file_get_contents( $photo_folder . '/meta.json' );
					$meta = json_decode( $meta, true );

					$comments = file_get_contents( $photo_folder . '/comments.json' );
					$comments = json_decode( $comments, true );
					if ( isset( $comments['comments']['comment'] ) ) {
						$comments = $comments['comments']['comment']; // Somehow this happened ... whatever, we'll just fix it here.
					}

					$media_path = glob( $photo_folder . '/media.*' );
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
				file_put_contents( self::DATA_PATH . '/media/photos.json', wp_json_encode( $photos ) );
			}
		}

		if ( file_exists( self::DATA_PATH . '/media/videos.json' ) ) {
			$videos = file_get_contents( self::DATA_PATH . '/media/videos.json' );
			$videos = json_decode( $videos, true );
		} else {
			$video_folders = glob( self::DATA_PATH . '/media/video/*', GLOB_ONLYDIR );

			if ( $video_folders ) {
				foreach ( $video_folders as $video_folder ) {
					$meta = file_get_contents( $video_folder . '/meta.json' );
					$meta = json_decode( $meta, true );

					$comments = file_get_contents( $video_folder . '/comments.json' );
					$comments = json_decode( $comments, true );

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
				file_put_contents( self::DATA_PATH . '/media/videos.json', wp_json_encode( $videos ) );
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

		//todo implement pagination
		// Paginate because this seems to take a while.
		//      $page     = absint( $_GET['page'] ?? 1 );
		//      $per_page = absint( $_GET['per_page'] ?? 100 );

		// Import videos.
		if ( $videos ) {
			//todo implement pagination
			//          if ( 1 === $page ) {
			foreach ( $videos as $video ) {
				if ( ! empty( wpcomsp_auto_flickr_importer_get_post_for_media_id( $video['id'] ) ) ) {
					wpcomsp_auto_flickr_importer_write_log( "Flickr Importer: Skipping video {$video['id']}..." );
					continue; // Already imported.
				}

				$post_id       = wpcomsp_auto_flickr_importer_create_post_for_media( $user_nsid, $video, $video['description']['_content'], $albums_to_terms );
				$attachment_id = wpcomsp_auto_flickr_importer_upload_media_from_server( $video, $post_id );
				if ( is_wp_error( $attachment_id ) ) {
					wpcomsp_auto_flickr_importer_write_log( "Flickr Importer: Error uploading video attachment {$attachment_id}..." );
					continue;
				}

				wp_update_post(
					array(
						'ID'           => $post_id,
						'post_content' => '<!-- wp:video {"id":' . $attachment_id . '} -->
						<figure class="wp-block-video"><video controls src="' . wp_get_attachment_url( $attachment_id ) . '" playsinline></video></figure>
						<!-- /wp:video -->' . wpcomsp_auto_flickr_importer_replace_flickr_links( $user_nsid, $video['description']['_content'], $albums_to_terms ),
					)
				);
			}
			//todo implement pagination
			//          }
		}

		//todo implement pagination
		// Import photos.
		//      $total_pages = ceil( count( $photos ) / $per_page );
		//      $photos      = array_slice( $photos, ( $page - 1 ) * $per_page, $per_page );

		foreach ( $photos as $photo ) {
			$post_content = wpautop( $photo['description']['_content'] );
			$post_content = '<!-- wp:post-featured-image {"align":"full"} /-->' . $post_content;

			if ( ! empty( wpcomsp_auto_flickr_importer_get_post_for_media_id( $photo['id'] ) ) ) {
				wpcomsp_auto_flickr_importer_write_log( "Flickr Importer: Skipping photo {$photo['id']}..." );
				continue; // Already imported.
			}

			$post_id       = wpcomsp_auto_flickr_importer_create_post_for_media( $user_nsid, $photo, $post_content, $albums_to_terms );
			$attachment_id = wpcomsp_auto_flickr_importer_upload_media_from_server( $photo, $post_id );
			if ( is_wp_error( $attachment_id ) ) {
				wpcomsp_auto_flickr_importer_write_log( "Flickr Importer: Error uploading photo attachment {$attachment_id}..." );
				continue;
			}

			set_post_thumbnail( $post_id, $attachment_id );

			// Ensure we're starting with a clean comments slate.
			$post_comments = get_comments( array( 'post_id' => $post_id ) );
			foreach ( $post_comments as $comment ) {
				wp_delete_comment( $comment, true );
			}

			$demo_import = true;
			foreach ( $photo['comments'] as $comment ) {
				$author_extra = $user_nsid === $comment['author'] && false !== strpos( $comment['_content'], 'live.staticflickr.com' );

				if ( $demo_import || $author_extra ) {
					$result = wp_insert_comment(
						array(
							'comment_date'    => gmdate( 'Y-m-d H:i:s', $comment['datecreate'] ),
							'comment_content' => wpcomsp_auto_flickr_importer_replace_flickr_comment_photos( $comment['_content'] ),
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
					if ( false === $result ) {
						wpcomsp_auto_flickr_importer_write_log( 'Flickr Importer: Failed to import comment ' . $comment['id'] . " on post $post_id<br/><br/>" );
					}
				}
			}

			if ( null === $this->limit ) {
				wpcomsp_auto_flickr_importer_update_raw_setting( 'initial_import', true );
			}
		}
	}

	/**
	 * Clean up the files after the import.
	 *
	 * @return true|\WP_Error
	 */
	public function clean_up_files() {
		global $wp_filesystem;

		// Include the file containing WP_Filesystem
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// Initialize the WordPress filesystem, no more using 'file-put-contents' function
		$access_type = get_filesystem_method();
		if ( 'direct' === $access_type ) {
			$creds = request_filesystem_credentials( site_url() . '/wp-admin/', '', false, false, array() );

			// Initialize the WP filesystem, no more using file_put_contents function
			if ( ! WP_Filesystem( $creds ) ) {
				// The credentials were not available or incorrect
				return new \WP_Error( 'filesystem_error', 'Cannot initialize the file system' );
			}

			if ( $wp_filesystem->exists( self::DATA_PATH ) ) {
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
}
