<?php

namespace Automattic\VIP\Prometheus;

use Prometheus\Counter;
use Prometheus\RegistryInterface;

const MIXED_GLOBAL_MULTISITE_QUERY_REGEX = '/wp_\d+_(\w+).+? (wp_\D\w*)|(wp_\D\w*).+? wp_\d+_(\w+)/';

/**
 * @codeCoverageIgnore
 */
class Mixed_Global_Multisite_Queries_Collector implements CollectorInterface {
	private Counter $mixed_global_multisite_queries_counter;

	public function initialize( RegistryInterface $registry ): void {
		$this->mixed_global_multisite_queries_counter = $registry->getOrRegisterCounter(
			'mixed_global_multisite_queries',
			'count',
			'Number of SQL queries with mixed global and multisite tables',
			[ 'site_id', 'global_table', 'multisite_table_suffix' ]
		);
		add_action( 'query', [ $this, 'query' ], 10, 1 );
	}

	public function query( $query ): void {
		global $wpdb;

		$regex = "/$wpdb->base_prefix\d+_(\w+).+? ($wpdb->base_prefix\D\w*)|($wpdb->base_prefix\D\w*).+? $wpdb->base_prefix\d+_(\w+)/";

		$matches = [];
		if ( preg_match( $regex, $query, $matches ) ) {
			$global_table = '';
			$multisite_table = '';

			if ( $matches[ 1 ] && $matches[ 2 ] ) {
				$global_table = $matches[ 2 ];
				$multisite_table = $matches[ 1 ];
			} elseif ( $matches[ 3 ] && $matches[ 4 ] ) {
				$global_table = $matches[ 3 ];
				$multisite_table = $matches[ 4 ];
			}

			if ( $global_table !== '' && $multisite_table !== '' ) {
				$this->mixed_global_multisite_queries_counter->inc( [ Plugin::get_instance()->get_site_label(), $global_table, $multisite_table ] );
			}
		}
	}

	public function collect_metrics(): void {
		/* Do nothing */
	}

	public function process_metrics(): void {
		/* Do nothing */
	}
}
