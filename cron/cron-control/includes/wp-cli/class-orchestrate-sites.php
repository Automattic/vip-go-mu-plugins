<?php
/**
 * Execute cron via WP-CLI
 *
 * Not intended for human use, rather it powers the Go-based Runner. Use the `events` command instead.
 *
 * @package a8c_Cron_Control
 */

namespace Automattic\WP\Cron_Control\CLI;

/**
 * Commands used by the Go-based runner to list sites
 */
class Orchestrate_Sites extends \WP_CLI_Command {
	const RUNNER_HOST_HEARTBEAT_KEY = 'a8c_cron_control_host_heartbeats';

	/**
	 * Record a heartbeat
	 *
	 * [--heartbeat-interval=<duration>]
	 * : The polling interval used by the runner to retrieve events and sites
	 *
	 * @param array $args Array of positional arguments.
	 * @param array $assoc_args Array of flags.
	 */
	public function heartbeat( $args, $assoc_args ) {
		$assoc_args = wp_parse_args(
			$assoc_args,
			[
				'heartbeat-interval' => 60,
			]
		);

		$this->do_heartbeat( intval( $assoc_args['heartbeat-interval'] ) );
	}

	/**
	 * List sites
	 */
	public function list() {
		$hosts = $this->get_hosts();

		// Use 2 hosts per site.
		$num_groups = count( $hosts ) / 2;
		if ( $num_groups < 2 ) {
			// Every host runs every site.
			$this->display_sites();
			return;
		}

		$id = array_search( gethostname(), $hosts, true );
		$this->display_sites( $num_groups, $id % $num_groups );
	}

	/**
	 * Display sites.
	 *
	 * @param int $num_groups Number of groups.
	 * @param int $group      Group number.
	 */
	private function display_sites( $num_groups = 1, $group = 0 ) {
		$site_count = get_sites( [ 'count' => 1 ] );
		if ( $site_count > 10000 ) {
			trigger_error( 'Cron-Control: This multisite has more than 10000 subsites, currently unsupported.', E_USER_WARNING );
		}

		// Keep the query simple, then process the results.
		$all_sites = get_sites( [ 'number' => 10000 ] );
		$sites_to_display = [];
		foreach ( $all_sites as $index => $site ) {
			if ( ! ( $index % $num_groups === $group ) ) {
				// The site does not belong to this group.
				continue;
			}

			if ( in_array( '1', array( $site->archived, $site->spam, $site->deleted ), true ) ) {
				// Deactivated subsites don't need cron run on them.
				continue;
			}

			// We just need the url to display.
			$sites_to_display[] = [ 'url' => $this->get_raw_site_url( $site->path, $site->domain ) ];
		}

		\WP_CLI\Utils\format_items( 'json', $sites_to_display, 'url' );
	}

	/**
	 * We can't use the home or siteurl since those don't always match with the `wp_blogs` entry.
	 * And that can lead to "site not found" errors when passed via the `--url` WP-CLI param.
	 * Instead, we construct the URL from data in the `wp_blogs` table.
	 */
	private function get_raw_site_url( string $site_path, string $site_domain ): string {
		$path = ( $site_path && '/' !== $site_path ) ? $site_path : '';
		return $site_domain . $path;
	}

	/**
	 * Updates the watchdog timer and removes stale hosts.
	 *
	 * @param int $heartbeat_interval Heartbeat interval.
	 */
	private function do_heartbeat( $heartbeat_interval = 60 ) {
		if ( defined( 'WPCOM_SANDBOXED' ) && true === WPCOM_SANDBOXED ) {
			return;
		}

		$heartbeats = wp_cache_get( self::RUNNER_HOST_HEARTBEAT_KEY );
		if ( ! $heartbeats ) {
			$heartbeats = [];
		}

		// Remove stale hosts
		// If a host has missed 2 heartbeats, remove it from jobs processing.
		$heartbeats = array_filter(
			$heartbeats,
			function( $timestamp ) use ( $heartbeat_interval ) {
				if ( time() - ( $heartbeat_interval * 2 ) > $timestamp ) {
					return false;
				}

				return true;
			}
		);

		$heartbeats[ gethostname() ] = time();
		wp_cache_set( self::RUNNER_HOST_HEARTBEAT_KEY, $heartbeats );
	}

	/**
	 * Retrieves hosts and their last alive time from the cache.
	 *
	 * @return array Hosts.
	 */
	private function get_hosts() {
		$heartbeats = wp_cache_get( self::RUNNER_HOST_HEARTBEAT_KEY );
		if ( ! $heartbeats ) {
			return [];
		}

		return array_keys( $heartbeats );
	}
}

\WP_CLI::add_command( 'cron-control orchestrate sites', 'Automattic\WP\Cron_Control\CLI\Orchestrate_Sites' );
