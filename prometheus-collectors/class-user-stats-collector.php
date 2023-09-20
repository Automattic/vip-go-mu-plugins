<?php

namespace Automattic\VIP\Prometheus;

use Automattic\VIP\Utils\Context;

use Prometheus\Gauge;
use Prometheus\RegistryInterface;
use Two_Factor_Core;

class User_Stats_Collector implements CollectorInterface {
	private Gauge $users_gauge;

	private string $blog_id;
	private const MAX_USERS_FOR_COUNT     = 1_000_000;
	private const MAX_USERS_FOR_2FA_STATS = 5_000;
	private const METRIC_OPTION           = 'vip-prom-users';
	public const TFA_STATUS_ENABLED       = 'enabled';
	public const TFA_STATUS_DISABLED      = 'disabled';
	public const TFA_STATUS_DEFAULT       = 'unknown';

	public function initialize( RegistryInterface $registry ): void {
		$this->blog_id = Plugin::get_instance()->get_site_label();

		$this->users_gauge = $registry->getOrRegisterGauge(
			'user',
			'count',
			'Number of users by role and 2FA status',
			[ 'network_site_id', 'role', 'tfa_status' ]
		);
	}

	public function collect_metrics(): void {
		$metrics = get_option( self::METRIC_OPTION, [] );

		if ( empty( $metrics ) ) {
			return;
		}

		foreach ( $metrics as $role => $counts ) {
			foreach ( $counts as $tfa_status => $count ) {
				$this->users_gauge->set( $count, [ $this->blog_id, $role, $tfa_status ] );
			}
		}
	}

	public function process_metrics(): void {
		global $wpdb;

		if ( ! Context::is_wp_cli() ) {
			return;
		}

		// Since we bunch everything together under a single label this logic wouldn't make any sense.
		if ( is_multisite() && wp_count_sites()['all'] > Plugin::MAX_NETWORK_SITES ) {
			return;
		}

		// count_users() is expensive so skip collecting this metric if there are too many users.
		// https://developer.wordpress.org/reference/functions/count_users/
		if ( get_user_count() > self::MAX_USERS_FOR_COUNT ) {
			return;
		}

		$user_count           = count_users();
		$formatted_user_count = $this->enrich_user_count( $user_count );

		// Get the count of users with 2FA meta before deciding to include these stats. get_users() can create slow queries on sites with many users.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$two_factor_user_count = $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(user_id) FROM {$wpdb->usermeta} WHERE meta_key = %s", Two_Factor_Core::ENABLED_PROVIDERS_USER_META_KEY )
		);

		$include_2fa_stats = $two_factor_user_count < self::MAX_USERS_FOR_2FA_STATS;

		if ( $include_2fa_stats ) {
			$formatted_user_count = $this->enrich_user_count_with_2fa( $user_count );
		}

		update_option( self::METRIC_OPTION, $formatted_user_count, false );
	}

	/**
	 * Assigns the user count for each role a default 2FA status.
	 *
	 * @param array $user_count The user count array returned by count_users().
	 * @param string $default_2fa_status The default 2FA status to set.
	 * @return array The user count by role.
	 */
	public function enrich_user_count( $user_count, $default_2fa_status = self::TFA_STATUS_DEFAULT ) {
		$user_count_by_role = [];

		foreach ( $user_count['avail_roles'] as $role => $value ) {
			$user_count_by_role[ $role ] = array_merge( [
				self::TFA_STATUS_DISABLED => 0,
				self::TFA_STATUS_ENABLED  => 0,
				self::TFA_STATUS_DEFAULT  => 0,
			], [ $default_2fa_status => $value ] );
		}

		return $user_count_by_role;
	}

	/**
	 * Splits the user count from count_users() by 2FA status.
	 *
	 * @param array $user_count The user count array returned by count_users().
	 * @return array The user count by role with 2FA status.
	 */
	public function enrich_user_count_with_2fa( $user_count ) {
		$user_count_by_role = $this->enrich_user_count( $user_count, self::TFA_STATUS_DISABLED );
		$two_factor_users   = $this->get_2fa_users();

		foreach ( $two_factor_users as $user ) {
			// Users might have the 2FA meta set to an invalid provider so we need to check if they are actually using 2FA.
			$is_two_factor_enabled_for_user = $this->is_user_using_two_factor( $user->ID );

			foreach ( $user->roles as $role ) {
				if ( $is_two_factor_enabled_for_user ) {
					$user_count_by_role[ $role ][ self::TFA_STATUS_ENABLED ]++;
					$user_count_by_role[ $role ][ self::TFA_STATUS_DISABLED ]--;
				}
			}
		}

		return $user_count_by_role;
	}

	/**
	 * Checks if a user is using 2FA.
	 *
	 * @param int $user_id The user ID.
	 * @return bool True if the user is using 2FA, false otherwise.
	 */
	public function is_user_using_two_factor( $user_id ) {
		return Two_Factor_Core::is_user_using_two_factor( $user_id );
	}

	/**
	 * Get all users with 2FA meta.
	 *
	 * @see https://developer.wordpress.org/reference/functions/get_users/
	 * @return array The users with 2FA meta.
	 */
	public function get_2fa_users() {
		$users = get_users( [
			'count_total' => false,
			'number'      => -1,
			'meta_key'    => Two_Factor_Core::ENABLED_PROVIDERS_USER_META_KEY,
		] );

		return $users;
	}
}
