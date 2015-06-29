<?php

if ( !class_exists( 'Lift_Health' ) ) {

	class Lift_Health {

		/**
		 * Combine the remote and local health into one
		 * 
		 * @return array 
		 */
		public static function get_overall_status() {

			$local_status = self::get_local_status();
			$remote_status = self::get_remote_status();

			$reason = array( );

			if ( $local_status['reason'] ) {
				$reason[] = $local_status['reason'];
			}
			if ( $remote_status['reason'] ) {
				$reason[] = $remote_status['reason'];
			}

			$severity = max( $local_status['severity'], $remote_status['severity'] );
			$errors = ( $local_status['errors'] or $remote_status['errors'] );

			$remote_status = $remote_status['status'];
			$local_status = $local_status['status'];

			$results = compact(
				'severity', 'reason', 'errors', 'remote_status', 'local_status'
			);

			return $results;
		}

		/**
		 * checks for error logs posts for lift and uses intervals with count
		 * thresholds. returns the overall stoplight color and an array of data
		 * for the error count, threshold, and light color if met.
		 *
		 * @global int $lift_health_interval
		 * @static
		 * @return array 
		 */
		public static function get_local_status() {
			if ( !Lift_Search::error_logging_enabled() ) {
				return array( 'severity' => 0, 'reason' => '', 'errors' => false, 'status' => 0 );
			}

			$intervals = array(
				60 * 60 => array( 'severity' => 2, 'threshold' => 5 ), // 1 hr
				60 * 30 => array( 'severity' => 1, 'threshold' => 2 ), // 30 mins
			);

			$intervals = apply_filters( 'lift_search_health_checkup_intervals', $intervals );

			$severity = 0;
			$reason = '';
			$errors = false;

			foreach ( $intervals as $interval => $data ) {
				global $lift_health_interval;
				$lift_health_interval = $interval;
				add_filter( 'posts_where', array( __CLASS__, 'filter_posts_where' ) );
				$q = new WP_Query( array(
					'posts_per_page' => 1,
					'post_type' => Voce_Error_Logging::POST_TYPE,
					'tax_query' => array( array(
							'taxonomy' => Voce_Error_Logging::TAXONOMY,
							'field' => 'slug',
							'terms' => array( 'error', 'lift-search' ),
							'operator' => 'AND'
						) ),
					) );
				remove_filter( 'posts_where', array( __CLASS__, 'filter_posts_where' ) );

				$post_count = $q->found_posts;

				if ( $post_count >= $data['threshold'] ) {
					$errors = true;
					$severity = $data['severity'];
					$reason = sprintf( '%d or more errors in the last %s', $data['threshold'], human_time_diff( time() - $interval ) );
				}

				$error_counts[] = array(
					'threshold' => $data['threshold'],
					'count' => $post_count,
					'interval' => $interval,
					'severity' => $severity,
				);
			}

			$results = array(
				'errors' => $errors,
				'severity' => $severity,
				'reason' => $reason,
				'status' => $error_counts,
			);

			return $results;
		}

		/**
		 * used for local health checks to get recent logs
		 * 
		 * @global int $lift_health_interval
		 * @param string $where
		 * @return string 
		 */
		public static function filter_posts_where( $where = '' ) {
			global $lift_health_interval;

			$date = date( 'Y-m-d H:i:s', strtotime( "-$lift_health_interval seconds" ) );
			$where .= " AND post_date_gmt >= '{$date}'";

			return $where;
		}

		/**
		 * use the config API to get the CloudSearch domain/index status
		 * 
		 * @return array 
		 */
		public static function get_remote_status() {
			$domain = Lift_Search::get_domain_manager()->get_domain( Lift_Search::get_search_domain_name() );

			if ( !$domain ) {
				return array(
					'errors' => true,
					'reason' => 'Domain has been deleted or the CloudSearch API request failed.',
					'severity' => 2,
					'status' => array(
						'fatal' => true,
						'text' => 'Domain has been deleted or the CloudSearch API request failed',
					),
				);
			}

			$errors = false;
			$severity = 0;
			$reason = '';

			$text = 'active';

			$pending = (!$domain->Created && !$domain->Deleted );
			$deleting = $domain->Deleted;
			$processing = $domain->Processing;
			$num_searchable_docs = $domain->NumSearchableDocs;
			$needs_indexing = $domain->RequiresIndexDocuments;
			$search_instance_count = $domain->SearchInstanceCount;
			$search_instance_type = $domain->SearchInstanceType;
			$search_partition_count = $domain->SearchPartitionCount;

			if ( $deleting ) {
				$severity = 2;
				$reason = 'CloudSearch domain being deleted';
				$text = 'being deleted';
			} else if ( $needs_indexing || $processing ) {
				if ( 0 == $search_instance_count ) {
					$severity = 1;
					$reason = 'CloudSearch domain loading';
					$text = 'loading';
				} else if ( $needs_indexing ) {
					$severity = 1;
					$reason = 'CloudSearch domain needs indexing';
					$text = 'needs indexing';
				} else {
					$severity = 1;
					$reason = 'CloudSearch domain processing';
					$text = 'processing';
				}
			} else if ( $pending ) {
				$severity = 1;
				$reason = 'CloudSearch domain pending';
				$text = 'pending';
			}

			if ( 0 != $severity ) {
				$errors = true;
			}


			$status = compact(
				'text', 'pending', 'deleting', 'processing', 'num_searchable_docs', 'needs_indexing', 'search_instance_count', 'search_instance_type', 'search_partition_count'
			);

			$status['api_error'] = false;
			$status['text'] = $text;

			$results = array(
				'errors' => $errors,
				'severity' => $severity,
				'reason' => $reason,
				'status' => $status,
			);

			return $results;
		}

	}

}