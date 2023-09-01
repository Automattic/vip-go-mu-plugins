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
	private const TFA_STATUS_ENABLED  = 'enabled';
	private const TFA_STATUS_DISABLED = 'disabled';
	private const TFA_STATUS_DEFAULT  = 'unknown';
	private const METRIC_OPTION       = 'vip-prom-users';

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
			foreach ( $counts['two-factor'] as $status => $count ) {
				$this->role_gauge->set( $count, [ $this->blog_id, $role, $status ] );
			}
		}
	}

	public function process_metrics(): void {
		if ( ! Context::is_wp_cli() ) {
			return;
		}

		// Since we bunch everything together under a single label this logic wouldn't make any sense
		if ( is_multisite() && wp_count_sites()['all'] > Plugin::MAX_NETWORK_SITES ) {
			return;
		}

		// Limit this to sites without a huge number of users for now
		if ( wp_is_large_user_count() ) {
			return;
		}

		// Order is not important to us and the query is faster without it
		add_action( 'pre_user_query', [ $this, 'remove_user_query_orderby' ] );

		$users_per_page         = 500;
		$user_counts            = [];
		$current_page           = 1;
		$two_factor_minimum_cap = 'edit_posts';

		do {
			$args = [
				'number'      => $users_per_page,
				'paged'       => $current_page,
				'count_total' => false,
			];

			$users = get_users( $args );

			foreach ( $users as $user ) {
				$two_factor_status = self::TFA_STATUS_DEFAULT;

				// Only get 2fa status for users with the specified cap
				if ( $user->has_cap( $two_factor_minimum_cap ) ) {
					$two_factor_status = \Two_Factor_Core::is_user_using_two_factor( $user->ID ) ? self::TFA_STATUS_ENABLED : self::TFA_STATUS_DISABLED;
				}

				foreach ( $user->roles as $role ) {
					$user_counts[ $role ] ??= [
						'total'      => 0,
						'two-factor' => [
							self::TFA_STATUS_ENABLED  => 0,
							self::TFA_STATUS_DISABLED => 0,
							self::TFA_STATUS_DEFAULT  => 0,
						],
					];
					$user_counts[ $role ]['total']++;
					$user_counts[ $role ]['two-factor'][ $two_factor_status ]++;
				}
			}

			$current_page++;

			// Clear the object cache to avoid memory issues
			vip_reset_local_object_cache();
		} while ( ! empty( $users ) );

		update_option( self::METRIC_OPTION, $user_counts, false );
	}

	public function remove_user_query_orderby( $user_query ) {
		if ( $user_query->query_orderby ) {
			$user_query->query_orderby = '';
		}
	}
}
