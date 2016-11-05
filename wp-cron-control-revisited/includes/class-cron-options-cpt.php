<?php

namespace WP_Cron_Control_Revisited;

class Cron_Options_CPT extends Singleton {
	/**
	 * PLUGIN SETUP
	 */

	/**
	 * Class properties
	 */
	private $post_type   = 'wpccr_events';
	private $post_status = 'inherit';

	private $posts_to_clean = array();

	/**
	 * Register hooks
	 */
	protected function class_init() {
		// Data storage
		add_action( 'init', array( $this, 'register_post_type' ) );

		// Option interception
		add_filter( 'pre_option_cron', array( $this, 'get_option' ) );
		add_filter( 'pre_update_option_cron', array( $this, 'update_option' ), 10, 2 );
	}

	/**
	 * Register a private post type to store cron events
	 */
	public function register_post_type() {
		register_post_type( $this->post_type, array(
			'label'               => 'Cron Events',
			'public'              => false,
			'rewrite'             => false,
			'export'              => false,
			'exclude_from_search' => true,
		) );

		// Clear caches for any manually-inserted posts, lest stale caches be used
		if ( ! empty( $this->posts_to_clean ) ) {
			foreach ( $this->posts_to_clean as $post_to_clean ) {
				error_log( "Cleaning cron entry # {$post_to_clean}" );
				clean_post_cache( $post_to_clean );
			}
		}
	}

	/**
	 * PLUGIN FUNCTIONALITY
	 */

	/**
	 * Override cron option requests with data from CPT
	 */
	public function get_option( $value ) {
		$cron_array = array(
			'version' => 2, // Core versions the cron array; without this, events will continually requeue
		);

		// Get events to re-render as the cron option
		$jobs_posts = $this->get_jobs( array(
			'post_type'        => $this->post_type,
			'post_status'      => $this->post_status,
			'suppress_filters' => false,
			'posts_per_page'   => 1000,
			'orderby'          => 'date',
			'order'            => 'ASC',
		) );

		// Loop through results and built output Core expects
		if ( ! empty( $jobs_posts ) ) {
			foreach ( $jobs_posts as $jobs_post ) {
				$timestamp = strtotime( $jobs_post->post_date_gmt );

				$job_args = maybe_unserialize( $jobs_post->post_content_filtered );
				if ( ! is_array( $job_args ) ) {
					continue;
				}

				$action   = $job_args['action'];
				$instance = $job_args['instance'];
				$args     = $job_args['args'];

				$cron_array[ $timestamp ][ $action ][ $instance ] = array(
					'schedule' => $args['schedule'],
					'args'     => $args['args'],
				);

				if ( isset( $args['interval'] ) ) {
					$cron_array[ $timestamp ][ $action ][ $instance ]['interval'] = $args['interval'];
				}

			}
		}

		uksort( $cron_array, 'strnatcasecmp' );

		return $cron_array;
	}

	/**
	 * Save cron events in CPT
	 *
	 * By returning $old_value, `cron` option won't be updated
	 */
	public function update_option( $new_value, $old_value ) {
		if ( is_array( $new_value ) && ! empty( $new_value ) ) {
			foreach ( $new_value as $timestamp => $timestamp_events ) {
				// Skip non-event data that Core includes in the option
				if ( ! is_numeric( $timestamp ) ) {
					continue;
				}

				foreach ( $timestamp_events as $action => $action_instances ) {
					foreach ( $action_instances as $instance => $instance_args ) {
						// Check if post exists and bail
						$job_exists = $this->job_exists( array(
							'name'             => sprintf( '%s-%s-%s', $timestamp, md5( $action ), $instance ),
							'post_type'        => $this->post_type,
							'post_status'      => $this->post_status,
							'suppress_filters' => false,
							'posts_per_page'   => 1,
						) );

						// Create a post, if needed
						if ( ! $job_exists ) {
							// Build minimum information needed to create a post
							// Sufficient for `wp_insert_post()`, but requires additional massaging for `$wpdb->insert()`
							$job_post = array(
								'post_title'            => sprintf( '%s | %s | %s', $timestamp, $action, $instance ),
								'post_name'             => sprintf( '%s-%s-%s', $timestamp, md5( $action ), $instance ),
								'post_content_filtered' => maybe_serialize( array(
									'action'   => $action,
									'instance' => $instance,
									'args'     => $instance_args,
								) ),
								'post_date'             => date( 'Y-m-d H:i:s', $timestamp ),
								'post_date_gmt'         => date( 'Y-m-d H:i:s', $timestamp ),
								'post_type'             => $this->post_type,
								'post_status'           => $this->post_status,
							);

							$this->create_job( $job_post );
						}
					}
				}
			}
		}

		return $old_value;
	}

	/**
	 * PLUGIN UTILITY METHODS
	 */

	/**
	 * Retrieve list of jobs, respecting whether or not the CPT is registered
	 */
	private function get_jobs( $args ) {
		// If called before `init`, we need to query directly because post types aren't registered earlier
		if ( did_action( 'init' ) ) {
			return get_posts( $args );
		} else {
			global $wpdb;

			$orderby = 'date' === $args['orderby'] ? 'post_date' : $args['orderby'];

			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s ORDER BY %s %s LIMIT %d;", $args['post_type'], $args['post_status'], $orderby, $args['order'], $args['posts_per_page'] ), 'OBJECT' );
		}
	}

	/**
	 * Check if a job post exists, respecting Core's loading order
	 */
	private function job_exists( $job_post ) {
		// If called before `init`, we need to insert directly because post types aren't registered earlier
		if ( did_action( 'init' ) ) {
			$exists = get_posts( $job_post );
		} else {
			global $wpdb;

			$exists = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = %s AND post_status = %s LIMIT 1;", $job_post['name'], $this->post_type, $this->post_status ) );
		}

		return ! empty( $exists );
	}

	/**
	 * Create a job post, respecting whether or not Core is ready for CPTs
	 */
	private function create_job( $job_post ) {
		// If called before `init`, we need to insert directly because post types aren't registered earlier
		if ( did_action( 'init' ) ) {
			wp_insert_post( $job_post );
		} else {
			global $wpdb;

			// Additional data needed to manually create a post
			$job_post = wp_parse_args( $job_post, array(
				'post_author'       => 0,
				'comment_status'    => 'closed',
				'ping_status'       => 'closed',
				'post_parent'       => 0,
				'post_modified'     => current_time( 'mysql' ),
				'post_modified_gmt' => current_time( 'mysql', true ),
			) );

			// Some sanitization in place of `sanitize_post()`, which we can't use this early
			foreach ( array( 'post_title', 'post_name', 'post_content_filtered' ) as $field ) {
				$job_post[ $field ] = sanitize_text_field( $job_post[ $field ] );
			}

			// Duplicate some processing performed in `wp_insert_post()`
			$charset = $wpdb->get_col_charset( $wpdb->posts, 'post_title' );
			if ( 'utf8' === $charset ) {
				$job_post['post_title'] = wp_encode_emoji( $job_post['post_title'] );
			}

			$job_post = wp_unslash( $job_post );

			// Set this so it isn't empty, even though it serves us no purpose
			$job_post['guid'] = esc_url( add_query_arg( $this->post_type, $job_post['post_name'], home_url( '/' ) ) );

			// Create the post
			$inserted = $wpdb->insert( $wpdb->posts, $job_post );

			// Clear caches for new posts once the post type is registered
			if ( $inserted ) {
				$this->posts_to_clean[] = $wpdb->insert_id;
			}
		}
	}

	/**
	 * Remove an event's CPT entry
	 *
	 * @param $timestamp  int     Unix timestamp
	 * @param $action     string  name of action used when the event is registered (unhashed)
	 * @param $instance   string  md5 hash of the event's arguments array, which Core uses to index the `cron` option
	 *
	 * @return bool
	 */
	public function delete_job( $timestamp, $action, $instance ) {
		$job_exists = get_posts( array(
			'name'             => sprintf( '%s-%s-%s', $timestamp, md5( $action ), $instance ),
			'post_type'        => $this->post_type,
			'post_status'      => $this->post_status,
			'suppress_filters' => false,
			'posts_per_page'   => 1,
		) );

		if ( empty( $job_exists ) ) {
			return false;
		}

		wp_delete_post( $job_exists[0]->ID, true );
		return true;
	}
}

Cron_Options_CPT::instance();
