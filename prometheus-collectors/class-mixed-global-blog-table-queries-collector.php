<?php

namespace Automattic\VIP\Prometheus;

use Prometheus\Counter;
use Prometheus\RegistryInterface;

class Mixed_Global_Blog_Table_Queries_Collector implements CollectorInterface {
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

		$regex = "/(?:FROM|JOIN|UPDATE|INTO|,)\s+`?$wpdb->base_prefix(\d+)?_?(\w+)+?`?/i";

		$matches = [];
		preg_match_all( $regex, $query, $matches, PREG_SET_ORDER );

		$last_global_table = NULL;
		$last_multisite_table = NULL;
		foreach ( $matches as $match ) {
			if ( $match[ 1 ] === '' ) {
				$last_global_table = $match[ 2 ];
			} else {
				$last_multisite_table = $match[ 2 ];
			}
		}

		if ( $last_global_table && $last_multisite_table ) {
			$this->mixed_global_multisite_queries_counter->inc(
				[
					Plugin::get_instance()->get_site_label(),
					$last_global_table,
					$last_multisite_table,
				]
			);
		}
	}

	public function collect_metrics(): void {
		/* Do nothing */
	}

	public function process_metrics(): void {
		/* Do nothing */
	}
}
