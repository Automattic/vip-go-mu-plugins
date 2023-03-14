<?php
/**
 * Plugin's constants
 *
 * @package a8c_Cron_Control
 */

namespace Automattic\WP\Cron_Control;

/**
 * Adjustable queue size and concurrency limits, to facilitate scaling
 */
$job_queue_size = 10;
if ( defined( 'CRON_CONTROL_JOB_QUEUE_SIZE' ) && is_numeric( \CRON_CONTROL_JOB_QUEUE_SIZE ) ) {
	$job_queue_size = absint( \CRON_CONTROL_JOB_QUEUE_SIZE );
	$job_queue_size = max( 1, min( $job_queue_size, 250 ) );
}
define( __NAMESPACE__ . '\JOB_QUEUE_SIZE', $job_queue_size );
unset( $job_queue_size );

$job_concurrency_limit = 10;
if ( defined( 'CRON_CONTROL_JOB_CONCURRENCY_LIMIT' ) && is_numeric( \CRON_CONTROL_JOB_CONCURRENCY_LIMIT ) ) {
	$job_concurrency_limit = absint( \CRON_CONTROL_JOB_CONCURRENCY_LIMIT );
	$job_concurrency_limit = max( 1, min( $job_concurrency_limit, 250 ) );
}
define( __NAMESPACE__ . '\JOB_CONCURRENCY_LIMIT', $job_concurrency_limit );
unset( $job_concurrency_limit );

/**
 * Job runtime constraints
 */
const JOB_QUEUE_WINDOW_IN_SECONDS = 30;
const JOB_TIMEOUT_IN_MINUTES      = 10;
const JOB_LOCK_EXPIRY_IN_MINUTES  = 30;

/**
 * Locks
 */
const LOCK_DEFAULT_LIMIT              = 10;
const LOCK_DEFAULT_TIMEOUT_IN_MINUTES = 10;

/**
 * Limit on size of event cache objects
 */
$cache_bucket_size = \MB_IN_BYTES * 0.95;
if ( defined( 'CRON_CONTROL_CACHE_BUCKET_SIZE' ) && is_numeric( \CRON_CONTROL_CACHE_BUCKET_SIZE ) ) {
	$cache_bucket_size = absint( \CRON_CONTROL_CACHE_BUCKET_SIZE );
	$cache_bucket_size = max( 256 * \KB_IN_BYTES, min( $cache_bucket_size, \TB_IN_BYTES ) );
}
define( __NAMESPACE__ . '\CACHE_BUCKET_SIZE', $cache_bucket_size );
unset( $cache_bucket_size );

/**
 * Limit how many buckets can be created, to avoid cache exhaustion
 */
$max_cache_buckets = 5;
if ( defined( 'CRON_CONTROL_MAX_CACHE_BUCKETS' ) && is_numeric( \CRON_CONTROL_MAX_CACHE_BUCKETS ) ) {
	$max_cache_buckets = absint( \CRON_CONTROL_MAX_CACHE_BUCKETS );
	$max_cache_buckets = max( 1, min( $max_cache_buckets, 250 ) );
}
define( __NAMESPACE__ . '\MAX_CACHE_BUCKETS', $max_cache_buckets );
unset( $max_cache_buckets );

/**
 * Consistent time format across plugin
 *
 * Excludes timestamp as UTC is used throughout
 */
const TIME_FORMAT = 'Y-m-d H:i:s';
