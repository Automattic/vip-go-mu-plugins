<?php

/*
  @Name: Lift Batch Queue
  @Description: Add documents to batch queue
 */

if ( !class_exists( 'Lift_Batch_Handler' ) ) {

	class Lift_Batch_Handler {

		/**
		 * Private var to track whether this class was previously initialized
		 * 
		 * @var bool
		 */
		private static $is_initialized = false;

		/**
		 * Option name for the placeholder used to determine the documents
		 * still needed to be queued up for submission after initial install
		 */

		const QUEUE_ALL_MARKER_OPTION = 'lift-queue-all-content-last-id';

		/**
		 * The number of documents to add to the queue at a time when doing the
		 * initial enqueuing of all documents 
		 */
		const QUEUE_ALL_SET_SIZE = 100;

		/**
		 * ID of the hook called by wp_cron when a batch should be processed 
		 */
		const BATCH_CRON_HOOK = 'lift_batch_cron';

		/**
		 * ID of the hook called by wp_cron when a next set of documents should
		 * be added to the queue 
		 */
		const QUEUE_ALL_CRON_HOOK = 'lift_queue_all_cron';

		/**
		 * Name of the custom interval created for batch processing
		 */
		const CRON_INTERVAL = 'lift-cron';

		/**
		 * Name of the transient key used to block multiple processes from 
		 * modifying batches at the same time. 
		 */
		const BATCH_LOCK = 'lift-batch-lock';

		/**
		 * Option name for the option storing the timestamp that the last
		 * batch was run. 
		 */
		const LAST_CRON_TIME_OPTION = 'lift-last-cron-time';

		public static function init() {
			if ( self::$is_initialized )
				return false;

			require_once(__DIR__ . '/lift-update-queue.php');

			Lift_Document_Update_Queue::init();

			self::$is_initialized = true;
		}

		/**
		 * enable the cron 
		 */
		public static function enable_cron( $timestamp = null ) {
			if ( is_null( $timestamp ) )
				$timestamp = time();
			wp_clear_scheduled_hook( self::BATCH_CRON_HOOK );
			wp_schedule_event( $timestamp, self::CRON_INTERVAL, self::BATCH_CRON_HOOK );
		}

		/**
		 * disable the cron 
		 */
		public static function disable_cron() {
			wp_clear_scheduled_hook( self::BATCH_CRON_HOOK );
		}

		/**
		 * is cron enabled?
		 */
		public static function cron_enabled() {
			$enabled = ( bool ) wp_next_scheduled( self::BATCH_CRON_HOOK );

			return $enabled;
		}

		/**
		 * get the last cron run time formatted for the blog's timezone and date/time format. or 'n/a' if not available.
		 *
		 * * @return string date string or 'n/a' 
		 */
		public static function get_last_cron_time() {
			$date_format = sprintf( '%s @ %s', get_option( 'date_format' ), get_option( 'time_format' ) );

			$gmt_offset = 60 * 60 * get_option( 'gmt_offset' );

			if ( ($last_cron_time_raw = get_option( self::LAST_CRON_TIME_OPTION, FALSE ) ) ) {
				return date( $date_format, $last_cron_time_raw + $gmt_offset );
			} else {
				return 'n/a';
			}
		}

		/**
		 * get the next cron run time formatted for the blog's timezone and date/time format. or 'n/a' if not available.
		 *
		 * * @return string date string or 'n/a' 
		 */
		public static function get_next_cron_time() {
			$date_format = sprintf( '%s @ %s', get_option( 'date_format' ), get_option( 'time_format' ) );

			$gmt_offset = 60 * 60 * get_option( 'gmt_offset' );

			if ( ($next_cron_time_raw = wp_next_scheduled( self::BATCH_CRON_HOOK ) ) ) {
				return date( $date_format, $next_cron_time_raw + $gmt_offset );
			} elseif ( self::cron_enabled() ) {
				return date( $date_format, time() + Lift_Search::get_batch_interval() + $gmt_offset );
			} else {
				return 'n/a';
			}
		}

		/**
		 * get a table with the current queue
		 * 
		 * @return string 
		 */
		public static function get_queue_list() {
			$page = (isset( $_GET['paged'] )) ? intval( $_GET['paged'] ) : 1;
			$page = max( 1, $page );

			$update_query = Lift_Document_Update_Queue::query_updates( array(
					'page' => $page,
					'per_page' => 10,
					'queue_ids' => array( Lift_Document_Update_Queue::get_active_queue_id(), Lift_Document_Update_Queue::get_closed_queue_id() )
				) );

			$meta_rows = $update_query->meta_rows;
			$num_pages = $update_query->num_pages;
			$html = '<h3><span class="alignright">Documents in Queue: <strong>' . $update_query->found_rows . '</strong></span>Documents to be Synced</h3>';
			$html .= '<table class="wp-list-table widefat fixed posts">
				<thead>
				<tr>
					<th class="column-date">Queue ID</th>
					<th class="column-title">Post</th>
					<th class="column-author">Last Author</th>
					<th class="column-categories">Time Queued</th>
				</tr>
				</thead>';
			$pages = '';
			if ( count( $meta_rows ) ) {
				foreach ( $meta_rows as $meta_row ) {
					$meta_value = get_post_meta( $meta_row->post_id, $meta_row->meta_key, true );
					switch ( $meta_value['document_type'] ) {
						case 'post';

							$post_id = $meta_value['document_id'];

							$last_user = '';
							if ( $last_id = get_post_meta( $post_id, '_edit_last', true ) ) {
								$last_user = get_userdata( $last_id );
							}

							if ( $meta_value['action'] == 'add' ) {
								$html .= '<tr>';
								$html .= '<td class="column-date">' . $post_id . '</td>';
								$html .= '<td class="column-title"><a href="' . esc_url( get_edit_post_link( $post_id ) ) . '">' . esc_html( get_the_title( $post_id ) ) . '</a></td>';
								$html .= '<td class="column-author">' . (isset( $last_user->display_name ) ? $last_user->display_name : '') . '</td>';
								$html .= '<td class="column-categories">' . mysql2date( 'D. M d Y g:ia', $meta_value['update_date'] ) . '</td>';
								$html .= '</tr>';
							} else {
								$html .= '<tr>';
								$html .= '<td class="column-date">' . $post_id . '</td>';
								$html .= '<td class="column-title">Deleted Post</td>';
								$html .= '<td class="column-author">&nbsp;</td>';
								$html .= '<td class="column-categories">' . mysql2date( 'D. M d Y g:ia', $meta_value['update_date'] ) . '</td>';
								$html .= '</tr>';
							}
						default:
							continue;
					}
				}
				$big = 999999999;
				$pages = '<div class="tablenav bottom"><div class="tablenav-pages"><span class="pagination-links">';
				$pages .= paginate_links( array(
					'base' => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
					'format' => '?paged=%#%',
					'current' => max( 1, $page ),
					'total' => $num_pages
					) );
				$pages .= '</span></div></div>';
			} else {
				$html .= '<tr><td colspan="4">No Posts In Queue</td></tr>';
			}
			$html .= '</table>';
			$html .= $pages;

			return $html;
		}

		/**
		 * queue all posts for indexing. clear the prior cron job.
		 */
		public static function queue_all() {
			update_option( self::QUEUE_ALL_MARKER_OPTION, -1 );
			wp_clear_scheduled_hook( self::QUEUE_ALL_CRON_HOOK );
			wp_schedule_event( time(), self::CRON_INTERVAL, self::QUEUE_ALL_CRON_HOOK );
		}

		/**
		 * Get batch size for queue all
		 * @return integer
		 */
		public static function get_queue_all_set_size() {
			return apply_filters( 'lift_queue_all_set_size', self::QUEUE_ALL_SET_SIZE );
		}

		/**
		 * used by queue_all cron job to process the queue of all posts
		 * 
		 * @global object $wpdb
		 */
		public static function process_queue_all() {
			global $wpdb;

			$id_from = get_option( self::QUEUE_ALL_MARKER_OPTION );

			if ( !$id_from ) {
				wp_clear_scheduled_hook( self::QUEUE_ALL_CRON_HOOK );
				return;
			}

			$post_types = Lift_Search::get_indexed_post_types();

			$query = new WP_Query();
			
			$alter_query = function($where, $wp_query) use ($query, $id_from) {
				global $wpdb;
				if($wp_query === $query) { //make sure we're not messing with any other queries
					//making sure all post_statii are used since wp_query overrides the requested statii
					$where = $wpdb->prepare(" AND post_type in ('" . implode( "','", $wp_query->get('post_type') ) . "') ".
						"AND ID > %d ".
						"AND post_status <> 'auto-draft'", $id_from);
				}
				return $where;
			};
			
			add_filter('posts_where', $alter_query, 10, 2);
			
			$posts = $query->query(array(
				'suppress_filters' => false,
				'post_type' => $post_types,
				'orderby' => 'ID',
				'order' => 'ASC',
				'post_status' => array_diff(get_post_stati(), array('auto-draft')),
				'posts_per_page' => self::get_queue_all_set_size()
			));
			
			remove_filter('posts_where', $alter_query);
			
			if ( empty( $posts ) ) {
				wp_clear_scheduled_hook( self::QUEUE_ALL_CRON_HOOK );
				delete_option( self::QUEUE_ALL_MARKER_OPTION );
				return;
			}

			foreach ( $posts as $post ) {
				Lift_Post_Update_Watcher::queue_entire_post( $post->ID );
			}

			$new_id_from = end( $posts )->ID;

			update_option( self::QUEUE_ALL_MARKER_OPTION, $new_id_from );
		}

		/**
		 * is the batch locked?
		 * 
		 * @return bool 
		 */
		public static function is_batch_locked() {
			$locked = get_transient( self::BATCH_LOCK );

			return $locked;
		}

		/**
		 * is the domain ready for a batch. has to exist and be in a good state
		 * 
		 * @param string $domain_name
		 * @return boolean 
		 */
		public static function ready_for_batch( $domain_name ) {
			$domain_manager = Lift_Search::get_domain_manager();
			return $domain_manager->can_accept_uploads( $domain_name );
		}

		/**
		 * Pulls the next set of items from the queue and sends a batch from it
		 * Callback for Batch Submission Cron 
		 * 
		 * @todo Add locking
		 */
		public static function send_next_batch() {
			if ( !self::ready_for_batch( Lift_Search::get_search_domain_name() ) ) {
				delete_transient( self::BATCH_LOCK );
				Lift_Search::event_log( 'CloudSearch Not Ready for Batch ' . time(), 'The batch is locked or the search domain is either currently processing, needs indexing, or your domain does not have indexes set up.', array( 'send-queue', 'response-false', 'notice' ) );
				return;
			}

			$lock_key = md5( uniqid( microtime() . mt_rand(), true ) );
			if ( !get_transient( self::BATCH_LOCK ) ) {
				set_transient( self::BATCH_LOCK, $lock_key, 300 );
			}

			if ( get_transient( self::BATCH_LOCK ) !== $lock_key ) {
				//another cron has this lock
				return;
			}


			update_option( self::LAST_CRON_TIME_OPTION, time() );

			$closed_queue_id = Lift_Document_Update_Queue::get_closed_queue_id();

			$update_query = Lift_Document_Update_Queue::query_updates( array(
					'per_page' => self::get_queue_all_set_size(),
					'queue_ids' => array( $closed_queue_id )
				) );


			if ( !count( $update_query->meta_rows ) ) {
				//no documents queued up
				Lift_Document_Update_Queue::close_active_queue();
				delete_transient( self::BATCH_LOCK );
				return;
			}

			$batch = new Lift_Batch();
			$batched_meta_keys = array( );

			$blog_id = get_current_blog_id();
			$site_id = lift_get_current_site_id();
			foreach ( $update_query->meta_rows as $meta_row ) {

				$update_data = get_post_meta( $meta_row->post_id, $meta_row->meta_key, true );

				if ( $update_data['document_type'] == 'post' ) {
					$action = $update_data['action'];
					if ( $action == 'add' ) {
						$post = get_post( $update_data['document_id'], ARRAY_A );

						$post_data = array( 'ID' => $update_data['document_id'], 'blog_id' => $blog_id, 'site_id' => $site_id );

						foreach ( Lift_Search::get_indexed_post_fields( $post['post_type'] ) as $field ) {
							$post_data[$field] = isset( $post[$field] ) ? $post[$field] : null;
						}

						$sdf_field_data = apply_filters( 'lift_post_changes_to_data', $post_data, $update_data['fields'], $update_data['document_id'] );
					} else {
						$sdf_field_data = array( 'ID' => intval( $update_data['document_id'] ) );
					}


					$sdf_doc = Lift_Posts_To_SDF::format_post( ( object ) $sdf_field_data, array(
							'action' => $action,
							'time' => time()
						) );
					try {
						$batch->add_document( ( object ) $sdf_doc );

						$batched_meta_keys[] = $meta_row->meta_key;
					} catch ( Lift_Batch_Exception $e ) {
						if ( isset( $e->errors[0]['code'] ) && 500 == $e->errors[0]['code'] ) {
							break;
						}
						Lift_Search::event_log( 'Batch Add Error ' . time(), json_encode( $e ), array( 'batch-add', 'error' ) );

						//@todo log error, stop cron? --- update_option( self::$search_semaphore, 1 );

						continue;
					}
				}
			}

			//send the batch
			$cloud_api = Lift_Search::get_search_api();

			if ( $r = $cloud_api->sendBatch( $batch ) ) {
				if ( $r->status === "success" ) {
					$log_title = "Post Queue Sent ";
					$tag = 'success';

					foreach ( $batched_meta_keys as $meta_key ) {
						delete_post_meta( $closed_queue_id, $meta_key );
					}
				} else {
					$log_title = "Post Queue Send Error ";
					$tag = 'error';
				}
				Lift_Search::event_log( $log_title . time(), json_encode( $r ), array( 'send-queue', 'response-true', $tag ) );

				//@todo delete sent queued items
			} else {
				$messages = $cloud_api->getErrorMessages();
				Lift_Search::event_log( 'Post Queue Error ' . time(), $messages, array( 'send-queue', 'response-false', 'error' ) );
			}
			delete_transient( self::BATCH_LOCK );
		}

		public static function _deactivation_cleanup() {
			delete_option( self::QUEUE_ALL_MARKER_OPTION );
			delete_option( self::LAST_CRON_TIME_OPTION );
			wp_clear_scheduled_hook( self::BATCH_CRON_HOOK );
			delete_transient( self::BATCH_LOCK );
		}

	}

}