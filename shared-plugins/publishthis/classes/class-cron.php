<?php
class Publishthis_Cron {

	private $_hook = 'import_publishthis_content';

	function __construct() {
		// Actions
		add_action( $this->_hook, array ( $this, 'import_content' ), 10, 2 );

		// Filters
		add_filter( 'cron_schedules', array ( $this, 'cron_schedules' ) );
	}

	/**
	 *
	 *
	 * @desc Define custom cron schedules
	 * @param unknown $schedules Old cron schedules
	 * @return array $schedules
	 */
	function cron_schedules( $schedules ) {
		$schedules = array_merge( $schedules, array(
			'every_60' => array(
				'interval' => 60,
				'display' => __ ( 'Every 1 minute', 'publishthis' ) ),
			'every_300' => array(
				'interval' => 300,
				'display' => __ ( 'Every 5 minutes', 'publishthis' ) ),
			'every_600' => array(
				'interval' => 600,
				'display' => __ ( 'Every 10 minutes', 'publishthis' ) ),
			'every_900' => array(
				'interval' => 900,
				'display' => __ ( 'Every 15 minutes', 'publishthis' ) ),
			'every_1800' => array(
				'interval' => 1800,
				'display' => __ ( 'Every 30 minutes', 'publishthis' ) ),
			'every_2700' => array(
				'interval' => 2700,
				'display' => __ ( 'Every 45 minutes', 'publishthis' ) ),
			'every_3600' => array(
				'interval' => 3600,
				'display' => __ ( 'Every 60 minutes', 'publishthis' ) ),
			'every_7200' => array(
				'interval' => 7200,
				'display' => __ ( 'Every 2 hours', 'publishthis' ) ),
			'every_21600' => array(
				'interval' => 21600,
				'display' => __ ( 'Every 6 hours', 'publishthis' ) ),
			'every_43200' => array(
				'interval' => 43200,
				'display' => __ ( 'Every 12 hours', 'publishthis' ) ),
			'every_86400' => array(
				'interval' => 86400,
				'display' => __ ( 'Every 24 hours', 'publishthis' ) )
		) );

		return $schedules;
	}

	/**
	 *
	 *
	 * @desc clean Crons. See _clean_cron_array() method
	 */
	function cleanCrons() {
		$this->_clean_cron_array();
	}

	/**
	 *
	 *
	 * @desc Apply cron settings and update cron
	 * NOTE: We need to do some pretty aggressive handling of the cron array.
	 * Otherwise, things can get messy. We don't care if there is currently a lock on, we
	 * need to superseed that. -DM
	 */
	function update() {
		global $publishthis;

		$this->_clean_cron_array();

		// Return here is we want to pause polling.
		if ( $publishthis->get_option ( 'pause_polling' ) ) {
			if ( ! get_option ( 'publishthis_paused_on' ) ) {
				update_option ( 'publishthis_paused_on', time() );
			}
			return;
		}

		// Get Publishing Actions
		// these define the cron times and events that should be run
		$actions = get_posts ( array ( 'numberposts' => 100, 'post_type' => $publishthis->post_type ) );

		// Get timestamp where previous cron was paused on
		$timestamp = ( int ) get_option ( 'publishthis_paused_on' );
		if ( $timestamp ) {
			delete_option ( 'publishthis_paused_on' );
		}

		// Set event for each Publishing Action
		foreach ( $actions as $action ) {
			$poll_interval = get_post_meta ( $action->ID, '_publishthis_poll_interval', true );
			if ( ! $poll_interval ) {
				continue;
			}

			if ( ! $timestamp ) {
				$timestamp = ( time() - $poll_interval ) * 1000;
			}
			$args = array ( $action->ID, $timestamp );

			wp_schedule_event ( time(), "every_{$poll_interval}", $this->_hook, $args );
		}
	}

	/**
	 *
	 *
	 * @desc Import data from Manager tool using Publishing Action settings
	 * @param unknown $action_id Publishing Action
	 * @param unknown $timestamp Used to find newly created or published content
	 * This will pull in the curated content for feeds.  Depending on the
	 * publishing action, individual posts will be created from the curated
	 * documents, or, digest posts will be created from the curated documents.
	 */
	function import_content( $action_id, $timestamp ) {
		global $publishthis;

		// Get $action
		$action = get_post ( $action_id );
		if ( ! $action ) {
			return;
		}
		// Get meta
		$meta = get_post_meta ( $action_id );

		// Init $action meta
		$category = $meta ['_publishthis_category'] [0];
		$featured_image = $meta ['_publishthis_featured_image'] [0];
		$maxImageWidth = $meta ['_publishthis_max_image_width'] [0];
		$okToResizePreviews = $meta ['_publishthis_ok_resize_preview'] [0];
		$publishAuthor = $meta ['_publishthis_publish_author'] [0];
		$readMore = $meta ['_publishthis_read_more'] [0];
		$imageAlignment = $meta ['_publishthis_image_alignment'] [0];
		$annotationPlacement = $meta ['_publishthis_annotation_placement'] [0];
		$format = $meta ['_publishthis_content_type_format'] [0];
		$section_id = $meta ['_publishthis_template_section'] [0];
		$post_status = $meta ['_publishthis_content_status'] [0];
		$post_type = $meta ['_publishthis_content_type'] [0];
		$synchronize = $meta ['_publishthis_synchronize'] [0];
		$template_id = $meta ['_publishthis_feed_template'] [0];

		$content_features = array ( "max_image_width" => $maxImageWidth, "ok_resize_previews" => $okToResizePreviews, "publish_author" => $publishAuthor, "read_more" => $readMore, "image_alignment" => $imageAlignment, "annotation_placement" => $annotationPlacement );

		// Get feeds
		$feeds = $publishthis->api->get_feeds_since_timestamp ( $timestamp, $template_id );
		if ( empty ( $feeds ) )
			return;

		/* loop each of our feeds, and then either create individual posts or digests
		  from the curated documents in the feed
		 */

		foreach ( $feeds as $feed ) {
			$curated_content = $publishthis->api->get_section_content ( $feed ['feedId'], $section_id );
			if ( empty ( $curated_content ) )
				continue;

			// Unique set name
			$set_name = '_publishthis_set_' . $template_id . '_' . $section_id . '_' . $feed ['feedId'];

			$post_category = '';
			// Categorize
			// map categories from custom data in a Feed to categories in wordpress
			if ( $category !== '0' ) {
				$result_list = $publishthis->api->get_custom_data_by_feed_id ( $feed ['feedId'], array () );
				foreach ( $result_list as $result ) {
					if ( $result->shortCode == $category ) {
						$post_category = $result->value;

						// Set category to Uncategorized if we received some value, but it is empty
						$uncategorized_term = get_term_by( 'name', 'uncategorized', 'category' );
						if ( empty( $post_category ) && $uncategorized_term ) {
							$post_category = $uncategorized_term->name;
						}

						break;
					}
				}
			}

			// Base $post
			$post = compact( 'post_status', 'post_type' );

			// Combined mode selected - all imported content in single WP post
			if ( $format == 'combined' ) {
				//don't update existed posts if synchronization is turned off
				$post_id = $this->_get_post_by_docid ( $set_name );
				if ( $post_id && ! $synchronize )
					continue;

				//set WP post title
				$post ['post_title'] = $feed ['displayName'];

				//save imported data
				//this is updating a "combined or digest post"
				$this->_update_combined ( $post_id, $set_name, $post_category, $post, $curated_content, $content_features );
			}
			else { // Individual mode selected - import content in separate WP posts
				$new_set_docids = array ();

				// make sure to reverse the array, as the order in the publish
				// this template sections have a defined order. so, the first one in the template
				// section should be marked as most recently published
				foreach ( array_reverse( $curated_content ) as $content ) {
					//don't update existed posts if synchronization is turned off
					$post_id = $this->_get_post_by_docid ( $content->docId );
					if ( $post_id && ! $synchronize )
						continue;

					//save imported data
					$this->_update_individual ( $post_id, $post_category, $post, $content, $featured_image, $content_features );
					$new_set_docids [] = $content->docId;
				}

				if ( $synchronize ) {
					$old_set_docids = get_option ( $set_name );
					if ( is_array( $old_set_docids ) ) {
						$docids = array_diff( $old_set_docids, $new_set_docids );
						$this->_delete_individuals ( $docids );
					}
					update_option ( $set_name, $new_set_docids );
				}
			}
		}
	}

	/**
	 *
	 *
	 * @desc Clean cron array
	 */
	private function _clean_cron_array() {
		//retrive all crons
		$crons = _get_cron_array ();
		if ( ! is_array( $crons ) ) {
			return;
		}

		$local_time = microtime( true );
		$doing_wp_cron = sprintf( '%.22F', $local_time );
		set_transient ( 'doing_cron', $doing_wp_cron );

		foreach ( $crons as $timestamp => $cronhooks ) {
			foreach ( $cronhooks as $hook => $keys ) {
				if ( $hook == $this->_hook ) {
					unset ( $crons [$timestamp] [$hook] );
				}
			}

			if ( empty ( $crons [$timestamp] ) ) {
				unset ( $crons [$timestamp] );
			}
		}

		//update cron with new array
		_set_cron_array ( $crons );
		delete_transient ( 'doing_cron' );
	}

	/**
	 *
	 *
	 * @desc Delete WP posts by docid
	 * @param unknown $docids Array of posts docid values
	 */
	private function _delete_individuals( $docids ) {
		foreach ( $docids as $docid ) {
			$post_id = $this->_get_post_by_docid ( $docid );
			if ( $post_id ) {
				wp_delete_post ( $post_id, true );
			}
		}
	}

	/**
	 *
	 *
	 * @desc Upload imported image, get attachment ID
	 * @param unknown $post_id Post ID
	 * @param unknown $url     Image url
	 * @param unknown $title   Post title
	 * @return Attachment ID or WP error object
	 */
	private function _get_attachment_id( $post_id, $url, $title ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		if ( ! empty ( $url ) ) {
			$tmp = download_url ( $url );

			// Set variables for storage
			// fix file filename for query strings
			preg_match( '/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $url, $matches );
			$file_array ['name'] = basename( $matches [0] );
			$file_array ['tmp_name'] = $tmp;

			// If error storing temporarily, unlink
			if ( is_wp_error ( $tmp ) ) {
				@unlink( $file_array ['tmp_name'] );
				$file_array ['tmp_name'] = '';
			}

			// do the validation and storage stuff
			$id = media_handle_sideload ( $file_array, $post_id, $title );
			if ( is_wp_error ( $id ) ) {
				@unlink( $file_array ['tmp_name'] );
			}

			return $id;
		}
	}

	/**
	 *
	 *
	 * @desc Get post ID by specified docid value
	 * @param unknown $docid
	 */
	private function _get_post_by_docid( $docid ) {
		global $wpdb;

		$post_id = $wpdb->get_var ( $wpdb->prepare ( "
			SELECT pm.post_id
			FROM $wpdb->postmeta pm
			WHERE pm.meta_key = '_publishthis_docid' AND pm.meta_value = %s
		", $docid ) );

		return ( $post_id ) ? $post_id : 0;
	}

	/**
	 *
	 *
	 * @desc Save import content as a single post (combined mode)
	 * @param unknown $post_id          WP Post ID
	 * @param unknown $docid            docid linked to this post
	 * @param unknown $category         WP Post category
	 * @param unknown $post             WP Post
	 * @param unknown $curated_content  Imported content
	 * @param unknown $content_features Additional content info
	 */
	private function _update_combined( $post_id, $docid, $category, $post, $curated_content, $content_features ) {
		global $publishthis;

		// Content
		$post['post_content'] = $post_content = '';

		// Generate html output
		foreach ( $curated_content as $content ) {
			$GLOBALS['pt_content'] = $content;
			$GLOBALS['pt_content_features'] = $content_features;
			ob_start();
			$publishthis->load_template( 'combined.php' );
			$post_content .= ob_get_clean();
		}
		unset ( $GLOBALS['pt_content'] );
		unset ( $GLOBALS['pt_content_features'] );

		// Set post author
		if ( is_numeric( $content_features["publish_author"] ) ) {
			if ( intval( $content_features["publish_author"] ) >= 0 ) {
				$post['post_author'] = $content_features["publish_author"];
			}
		}

		$post['post_content'] = $post_content;

		// Manage category
		if ( $category && $post ['post_type'] == 'post' ) {
			// try to get existed category
			$term = get_term_by ( 'name', $category, 'category' );

			//category found and it wasn't changed
			if ( $term && $term->name == $category ) {
				$post ['post_category'] = array ( $term->term_id );
			} else {
				require_once ABSPATH . 'wp-admin/includes/taxonomy.php';
				$category_id = wp_insert_category ( array ( 'cat_name' => $category ), true );
				$post ['post_category'] = array ( $category_id );
			}
		}

		// Add / update post
		if ( $post_id ) {
			$post['ID'] = $post_id;
			wp_update_post( $post );
		} else {
			$post_id = wp_insert_post( $post );
			update_post_meta( $post_id, '_imported', '1' );
			update_post_meta( $post_id, '_publishthis_docid', $docid );
		}

		// Add / update meta
		update_post_meta( $post_id, '_publishthis_raw', $content );
	}

	/**
	 *
	 *
	 * @desc Save import content as a separate post (individual mode)
	 * @param unknown $post_id          WP Post ID
	 * @param unknown $category         WP Post category
	 * @param unknown $post             WP Post
	 * @param unknown $content          Imported content
	 * @param unknown $featured_image   Flag that shows set featured image or not
	 * @param unknown $content_features Additional content info
	 */
	private function _update_individual( $post_id, $category, $post, $content, $featured_image = false, $content_features ) {
		global $publishthis;

		// Set post author
		if ( is_numeric( $content_features ["publish_author"] ) ) {
			if ( intval( $content_features ["publish_author"] ) >= 0 ) {
				$post ['post_author'] = $content_features ["publish_author"];
			}
		}
		// Set post title
		$post ['post_title'] = ! empty ( $content->title ) ? $content->title : '';
		$post ['post_content'] = '';

		// Set Content
		$GLOBALS ['pt_content'] = $content;
		$GLOBALS ['pt_content_features'] = $content_features;

		// Generate html output
		ob_start();
		$publishthis->load_template ( 'individual.php' );
		$post ['post_content'] = ob_get_clean();
		unset ( $GLOBALS ['pt_content'] );
		unset ( $GLOBALS ['pt_content_features'] );

		// Manage category
		if ( $category && $post ['post_type'] == 'post' ) {
			// try to get existed category
			$term = get_term_by ( 'name', $category, 'category' );

			//category found and it wasn't changed
			if ( $term && $term->name == $category ) {
				$post ['post_category'] = array ( $term->term_id );
			} else {
				require_once ABSPATH . 'wp-admin/includes/taxonomy.php';
				$category_id = wp_insert_category ( array ( 'cat_name' => $category ), true );
				$post ['post_category'] = array ( $category_id );
			}
		}

		// Add / update post
		if ( $post_id ) {
			$post ['ID'] = $post_id;
			wp_update_post ( $post );
		} else {
			$post_id = wp_insert_post ( $post );
			update_post_meta ( $post_id, '_imported', '1' );
			update_post_meta ( $post_id, '_publishthis_docid', $content->docId );
		}

		// Set post Formats
		if ( $post ['post_type'] == 'post' && isset ( $content->contentType ) ) {
			switch ( $content->contentType ) {
			case 'video' :
				set_post_format ( $post_id, 'video' );
				break;
			case 'photo' :
				set_post_format ( $post_id, 'image' );
				break;
			case 'tweet' :
				set_post_format ( $post_id, 'status' );
				break;
			case 'article' :
			case 'text' :
			default :
				break;
			}
		}

		// Download and set featured image
		if ( $featured_image && ! empty ( $content->imageUrl ) ) {

			// first, get the key from this post to see if we have all ready
			// uploaded this photo
			$strImageKey = "pt-img-key";

			try {
				$strImageKey = $content->imageUrl . "-" . strval( $content_features ['max_image_width'] ) . "-" . strval( $content_features ['ok_resize_previews'] );
			} catch ( Exception $ex ) {
				$publishthis->log->add ( $ex->getMessage () );
			}

			if ( "1" == get_post_meta ( $post_id, $strImageKey, true ) ) {
				// do nothing, as we have all ready uploaded
			} else {
				$strImageUrl = $publishthis->utils->getResizedPhotoUrl ( $content->imageUrl, $content_features ['max_image_width'], $content_features ['ok_resize_previews'] );

				$thumbnail_id = $this->_get_attachment_id ( $post_id, $strImageUrl, $post ['post_title'] );

				if ( ! is_wp_error ( $thumbnail_id ) ) {
					update_post_meta ( $post_id, '_thumbnail_id', $thumbnail_id );
				}
				// set this so we do not try to upload again
				update_post_meta ( $post_id, $strImageKey, "1" );
			}

		}

		// Add / update meta
		update_post_meta ( $post_id, '_publishthis_raw', $content );
	}
}
