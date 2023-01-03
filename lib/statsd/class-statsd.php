<?php

namespace Automattic\VIP;

/**
 * Sends statistics to the stats daemon over UDP
 *
 * Internal use only!
 */

class StatsD {
	/**
	 * Sets one or more timing values
	 *
	 * @param string|array $stats The metric(s) to set.
	 * @param float $time The elapsed time (ms) to log
	 **/
	public function timing( $stats, $time ) {
		$this->update_stats( $stats, $time, 1, 'ms' );
	}

	/**
	 * Sets one or more gauges to a value
	 *
	 * @param string|array $stats The metric(s) to set.
	 * @param float $value The value for the stats.
	 **/
	public function gauge( $stats, $value ) {
		$this->update_stats( $stats, $value, 1, 'g' );
	}

	/**
	 * A "Set" is a count of unique events.
	 * This data type acts like a counter, but supports counting
	 * of unique occurences of values between flushes. The backend
	 * receives the number of unique events that happened since
	 * the last flush.
	 *
	 * The reference use case involved tracking the number of active
	 * and logged in users by sending the current userId of a user
	 * with each request with a key of "uniques" (or similar).
	 *
	 * @param string|array $stats The metric(s) to set.
	 * @param float $value The value for the stats.
	 **/
	public function set( $stats, $value ) {
		$this->update_stats( $stats, $value, 1, 's' );
	}

	/**
	 * Increments one or more stats counters
	 *
	 * @param string|array $stats The metric(s) to increment.
	 * @param float|1 $sample_rate the rate (0-1) for sampling.
	 * @return boolean
	 **/
	public function increment( $stats, $sample_rate = 1 ) {
		$this->update_stats( $stats, 1, $sample_rate, 'c' );
	}

	/**
	 * Decrements one or more stats counters.
	 *
	 * @param string|array $stats The metric(s) to decrement.
	 * @param float|1 $sample_rate the rate (0-1) for sampling.
	 * @return boolean
	 **/
	public function decrement( $stats, $sample_rate = 1 ) {
		$this->update_stats( $stats, -1, $sample_rate, 'c' );
	}

	/**
	 * Updates one or more stats.
	 *
	 * @param string|array $stats The metric(s) to update. Should be either a string or array of metrics.
	 * @param int|1 $delta The amount to increment/decrement each metric by.
	 * @param float|1 $sample_rate the rate (0-1) for sampling.
	 * @param string|c $metric The metric type ("c" for count, "ms" for timing, "g" for gauge, "s" for set)
	 * @return boolean
	 **/
	public function update_stats( $stats, $delta = 1, $sample_rate = 1, $metric = 'c' ) {
		if ( ! is_array( $stats ) ) {
			$stats = array( $stats );
		}

		$data = array();

		foreach ( $stats as $stat ) {
			$data[ $stat ] = "$delta|$metric";
		}

		$this->send( $data, $sample_rate );
	}

	/*
	 * Send the metrics over UDP
	 */
	public function send( $data, $sample_rate = 1 ) {
	// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_trigger_error

		// Disables StatsD logging for test environments
		if ( defined( 'VIP_DISABLE_STATSD' ) && VIP_DISABLE_STATSD ) {
			return;
		}

		// If we don't have server info defined, abort and warn
		if ( ! defined( 'VIP_STATSD_HOST' ) || ! VIP_STATSD_HOST ) {
			if ( true === WPCOM_IS_VIP_ENV ) {
				trigger_error( 'VIP_STATSD_HOST not set, no data sent to statsd', \E_USER_WARNING );
			}
			return;
		}

		if ( ! defined( 'VIP_STATSD_PORT' ) || ! VIP_STATSD_PORT ) {
			if ( true === WPCOM_IS_VIP_ENV ) {
				trigger_error( 'VIP_STATSD_PORT not set, no data sent to statsd', \E_USER_WARNING );
			}
			return;
		}

		// sampling
		$sampled_data = array();

		if ( $sample_rate < 1 ) {
			foreach ( $data as $stat => $value ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand -- don't need a CSPRNG here
				if ( ( mt_rand() / mt_getrandmax() ) <= $sample_rate ) {
					$sampled_data[ $stat ] = "$value|@$sample_rate";
				}
			}
		} else {
			$sampled_data = $data;
		}

		if ( empty( $sampled_data ) ) {
			return;
		}

		$host = VIP_STATSD_HOST;
		$port = VIP_STATSD_PORT;
		$url  = "udp://$host";

		// Wrap this in a try/catch - failures in any of this should logged as warnings
		try {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fsockopen -- false positive, this is a network socket
			$fp = fsockopen( $url, $port, $errno, $errstr );

			if ( ! $fp ) {
				throw new \Exception( "fsockopen: $errstr ($errno)" );
			}

			foreach ( $sampled_data as $stat => $value ) {
				// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fwrite -- false positive, write to a network socket
				if ( false === fwrite( $fp, "$stat:$value" ) ) {
					$escaped_write = addslashes( "$stat:$value" );
					throw new \Exception( "fwrite: failed to write '$escaped_write'" );
				}
			}

			if ( false === fclose( $fp ) ) {
				throw new \Exception( 'fclose: failed to close open file pointer' );
			}
		} catch ( \Exception $e ) {
			$escaped_url = addslashes( $url );
			trigger_error( "Statsd::send exception('$escaped_url'): {$e->getMessage()}", \E_USER_WARNING ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

	// phpcs:enable
	}
}
