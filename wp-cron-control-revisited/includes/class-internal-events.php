<?php

namespace WP_Cron_Control_Revisited;

class Internal_Events {
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
	private $internal_jobs           = array();
	private $internal_jobs_schedules = array();

	/**
	 * Register hooks
	 */
	private function __construct() {
		$this->prepare();

		add_action( 'wp_loaded', array( $this, 'schedule_internal_events' ) );
		add_filter( 'cron_schedules', array( $this, 'register_internal_events_schedules' ) );
		add_action( 'wpccrij_force_publish_missed_schedules', array( $this, 'force_publish_missed_schedules' ) );
		add_action( 'wpccrij_confirm_scheduled_posts', array( $this, 'confirm_scheduled_posts' ) );
	}

	/**
	 * Set additional variables required for plugin functionality
	 */
	private function prepare() {
		// Internal jobs
		$this->internal_jobs = array(
			array(
				'schedule' => 'wpccrij_minute',
				'action'   => 'wpccrij_force_publish_missed_schedules',
			),
			array(
				'schedule' => 'wpccrij_ten_minutes',
				'action'   => 'wpccrij_confirm_scheduled_posts',
			),
		);

		$this->internal_jobs_schedules = array(
			'wpccrij_minute' => array(
				'interval' => 1 * MINUTE_IN_SECONDS,
				'display' => __( 'WP Cron Control Revisited internal job - every minute', 'wp-cron-control-revisited' ),
			),
			'wpccrij_ten_minutes' => array(
				'interval' => 10 * MINUTE_IN_SECONDS,
				'display' => __( 'WP Cron Control Revisited internal job - every 10 minutes', 'wp-cron-control-revisited' ),
			),
		);
	}

	/**
	 * Include custom schedules used for internal jobs
	 */
	public function register_internal_events_schedules( $schedules ) {
		return array_merge( $schedules, $this->internal_jobs_schedules );
	}

	/**
	 * Schedule internal jobs
	 */
	public function schedule_internal_events() {
		$when = strtotime( sprintf( '+%d seconds', Main::instance()->job_queue_window_in_seconds ) );

		foreach ( $this->internal_jobs as $job_args ) {
			if ( ! wp_next_scheduled( $job_args['action'] ) ) {
				wp_schedule_event( $when, $job_args['schedule'], $job_args['action'] );
			}
		}
	}

	/**
	 * PLUGIN FUNCTIONALITY
	 */

	/**
	 * Events that are always run, regardless of how many jobs are queued
	 */
	public function is_internal_event( $action ) {
		return in_array( $action, wp_list_pluck( $this->internal_jobs, 'action' ) );
	}

	/**
	 * Allow specific events to be blocked perpetually
	 */
	public function is_blocked_event( $action ) {
		$blocked_hooks = array();

		return in_array( $action, $blocked_hooks );
	}

	/**
	 * Published scheduled posts that miss their schedule
	 */
	public function force_publish_missed_schedules() {
		global $wpdb;

		$missed_posts = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'future' AND post_date <= %s LIMIT 100;", current_time( 'mysql', false ) ) );

		if ( ! empty( $missed_posts ) ) {
			foreach ( $missed_posts as $missed_post ) {
				check_and_publish_future_post( $missed_post );

				do_action( 'wpccr_published_post_that_missed_schedule', $missed_post );
			}
		}
	}

	/**
	 * Ensure scheduled posts have a corresponding cron job to publish them
	 */
	public function confirm_scheduled_posts() {
		global $wpdb;

		$future_posts = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_date FROM {$wpdb->posts} WHERE post_status = 'future' AND post_date > %s LIMIT 100;", current_time( 'mysql', false ) ) );

		if ( ! empty( $future_posts ) ) {
			foreach ( $future_posts as $future_post ) {
				$future_post->ID = absint( $future_post->ID );
				$gmt_time        = strtotime( get_gmt_from_date( $future_post->post_date ) . ' GMT' );
				$timestamp       = wp_next_scheduled( 'publish_future_post', array( $future_post->ID ) );

				if ( false === $timestamp ) {
					wp_schedule_single_event( $gmt_time, 'publish_future_post', array( $future_post->ID ) );

					do_action( 'wpccr_publish_scheduled', $future_post->ID );
				} elseif ( (int) $timestamp !== $gmt_time ) {
					wp_clear_scheduled_hook( 'publish_future_post', array( (int) $future_post->ID ) );
					wp_schedule_single_event( $gmt_time, 'publish_future_post', array( $future_post->ID ) );

					do_action( 'wpccr_publish_rescheduled', $future_post->ID );
				}
			}
		}
	}
}

Internal_Events::instance();
