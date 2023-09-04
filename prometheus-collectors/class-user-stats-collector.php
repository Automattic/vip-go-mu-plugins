<?php

namespace Automattic\VIP\Prometheus;

use Automattic\VIP\Utils\Context;

use Prometheus\Gauge;
use Prometheus\RegistryInterface;

/**
 * @codeCoverageIgnore
 */
class User_Stats_Collector implements CollectorInterface {
	private Gauge $role_gauge;

	private string $blog_id;
	private const TFA_STATUS_ENABLED      = 'enabled';
	private const TFA_STATUS_DISABLED     = 'disabled';
	private const TFA_STATUS_DEFAULT      = 'unknown';
	private const METRIC_OPTION           = 'vip-prom-users';
	private const MAX_USERS_FOR_COUNT     = 1_000_000;
	private const MAX_USERS_FOR_2FA_STATS = 10_000;

	public function initialize( RegistryInterface $registry ): void {
		$this->blog_id = Plugin::get_instance()->get_site_label();

		$this->role_gauge = $registry->getOrRegisterGauge(
			'user',
			'count',
			'Number of users by role',
			[ 'site_id', 'role', 'tfa_status' ]
		);
	}

	public function collect_metrics(): void {
		$metrics = get_option( self::METRIC_OPTION, [] );
		if ( ! $metrics ) {
			return;
		}

		foreach ( $metrics as $role => $counts ) {
			foreach ( $counts as $status => $count ) {
				$this->role_gauge->set( $count, [ $this->blog_id, $role, $status ] );
			}
		}
	}

	public function process_metrics(): void {
		global $wpdb;

		if ( ! Context::is_wp_cli() ) {
			return;
		}

		// Since we bunch everything together under a single label this logic wouldn't make any sense
		if ( is_multisite() && wp_count_sites()['all'] > Plugin::MAX_NETWORK_SITES ) {
			return;
		}

		// count_users() is intensive so skip collecting this metric if there are too many users
		if ( get_user_count() > self::MAX_USERS_FOR_COUNT ) {
			return;
		}

		// Get user count by role
		$user_counts = count_users();

		// Get the count of users with 2FA meta to make sure we're not querying too many users with `get_users()`.
		// This includes users on any network site, not just the current site.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$two_factor_user_count = $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(user_id) FROM {$wpdb->usermeta} WHERE meta_key = %s", \Two_Factor_Core::ENABLED_PROVIDERS_USER_META_KEY )
		);

		$include_2fa_stats = $two_factor_user_count < self::MAX_USERS_FOR_2FA_STATS;

		// Prepare the default response
		foreach ( $user_counts['avail_roles'] as $role => $value ) {
			$ret[ $role ] = [
				$include_2fa_stats ? self::TFA_STATUS_DISABLED : self::TFA_STATUS_DEFAULT => $value,
			];
		}

		// If there aren't too many users with the 2FA meta, include 2FA stats
		if ( $include_2fa_stats ) {
			// Order is not important to us and the query is faster without the order by clause
			add_action( 'pre_user_query', [ $this, 'remove_user_query_orderby' ] );

			$users = get_users( [
				'count_total' => false,
				'number'      => -1,
				'meta_key'    => \Two_Factor_Core::ENABLED_PROVIDERS_USER_META_KEY,
			] );

			foreach ( $users as $user ) {
				// Double-check that 2FA is enabled for this user (the meta could be set to an invalid provider)
				$two_factor_enabled = \Two_Factor_Core::is_user_using_two_factor( $user->ID );

				foreach ( $user->roles as $role ) {
					if ( $two_factor_enabled ) {
						$ret[ $role ][ self::TFA_STATUS_ENABLED ] ??= 0;
						$ret[ $role ][ self::TFA_STATUS_ENABLED ]++;

						if ( isset( $ret[ $role ][ self::TFA_STATUS_DISABLED ] ) ) {
							$ret[ $role ][ self::TFA_STATUS_DISABLED ]--;
						}
					}
				}
			}
		}

		update_option( self::METRIC_OPTION, $ret, false );
	}

	public function remove_user_query_orderby( $user_query ) {
		if ( $user_query->query_orderby ) {
			$user_query->query_orderby = '';
		}
	}
}
