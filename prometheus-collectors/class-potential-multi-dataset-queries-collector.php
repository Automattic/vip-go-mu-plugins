<?php

namespace Automattic\VIP\Prometheus;

use Prometheus\Counter;
use Prometheus\RegistryInterface;

class Potential_Multi_Dataset_Queries_Collector implements CollectorInterface {
	private Counter $potential_multi_dataset_queries_collector;

	public function initialize( RegistryInterface $registry ): void {
		$this->potential_multi_dataset_queries_collector = $registry->getOrRegisterCounter(
			'potential_multi_dataset_queries_collector',
			'count',
			'Potential multi dataset queries',
			[ 'site_id', 'global_table_suffix', 'multisite_table_suffix' ]
		);
		add_action( 'query', [ $this, 'query' ], 10, 1 );
	}

	public function query( $query ): void {
		global $wpdb;

		$regex = "/(?:FROM|JOIN|UPDATE|INTO|,)\s+`?$wpdb->base_prefix(\d+)?_?(\w+)+?`?/i";

		$matches = [];
		preg_match_all( $regex, $query, $matches, PREG_SET_ORDER );

		$last_global_table = null;
		$last_blog_table   = null;
		foreach ( $matches as $match ) {
			if ( '' === $match[1] ) {
				$last_global_table = $match[2];
			} else {
				$last_blog_table = $match[2];
			}
		}

		if ( $last_global_table && $last_blog_table ) {
			$this->potential_multi_dataset_queries_collector->inc(
				[
					Plugin::get_instance()->get_site_label(),
					$last_global_table,
					$last_blog_table,
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
