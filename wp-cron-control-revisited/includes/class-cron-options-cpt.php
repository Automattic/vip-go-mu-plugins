<?php

namespace WP_Cron_Control_Revisited;

class Cron_Options_CPT {
	/**
	 * Class instance
	 */
	private static $__instance = null;

	public static function instance() {
		if ( ! is_a( self::$__instance, __CLASS__ ) ) {
			self::$__instance = new self;
		}

		return self::$__instance;
	}

	/**
	 * PLUGIN SETUP
	 */

	/**
	 * Class properties
	 */
	private $post_type   = 'wpccr_events';
	private $post_status = 'inherit';

	/**
	 * Register hooks
	 */
	private function __construct() {
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
		$jobs_posts = get_posts( array(
			'post_type'        => $this->post_type,
			'post_status'      => $this->post_status,
			'suppress_filters' => false,
			'posts_per_page'   => 1000,
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

		uksort( $cron_array, "strnatcasecmp" );

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
						// There are some jobs we never care to run
						if ( is_blocked_event( $action ) ) {
							continue;
						}

						// Check if post exists and bail
						$job_exists = get_posts( array(
							'name'             => sprintf( '%s-%s-%s', $timestamp, md5( $action ), $instance ),
							'post_type'        => $this->post_type,
							'post_status'      => $this->post_status,
							'suppress_filters' => false,
							'posts_per_page'   => 1,
						) );

						// Create a post, if needed
						if ( empty( $job_exists ) ) {
							$job_args = array(
								'action'   => $action,
								'instance' => $instance,
								'args'     => $instance_args,
							);

							wp_insert_post( array(
								'post_title'            => sprintf( '%s | %s | %s', $timestamp, $action, $instance ),
								'post_name'             => sprintf( '%s-%s-%s', $timestamp, md5( $action ), $instance ),
								'post_content_filtered' => maybe_serialize( $job_args ),
								'post_date'             => date( 'Y-m-d H:i:s', $timestamp ),
								'post_date_gmt'         => date( 'Y-m-d H:i:s', $timestamp ),
								'post_type'             => $this->post_type,
								'post_status'           => $this->post_status,
							) );
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
	 * Remove an event's CPT entry
	 *
	 * @param $timestamp  int     Unix timestamp
	 * @param $action     string  name of action used when the event is registered (unhashed)
	 * @param $instance   string  md5 hash of the event's arguments array, which Core uses to index the `cron` option
	 *
	 * @return bool
	 */
	public function delete_event( $timestamp, $action, $instance ) {
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
