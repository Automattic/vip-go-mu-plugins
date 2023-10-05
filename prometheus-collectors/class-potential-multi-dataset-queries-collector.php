<?php

namespace Automattic\VIP\Prometheus;

use Prometheus\Counter;
use Prometheus\RegistryInterface;
use function Automattic\VIP\Logstash\log2logstash;

class Potential_Multi_Dataset_Queries_Collector implements CollectorInterface {
	private Counter $potential_multi_dataset_queries_collector;

	public function initialize( RegistryInterface $registry ): void {
		$this->potential_multi_dataset_queries_collector = $registry->getOrRegisterCounter(
			'potential_multi_dataset_queries_collector',
			'count',
			'Potential multi dataset queries',
			[ 'global_table_suffix', 'blog_table_suffix', 'cross_blog_count' ]
		);

		add_action( 'sql_query_log', [ $this, 'sql_query_log' ], 10, 1 );
	}

	public function sql_query_log( $query ): void {
		global $wpdb;

		$regex = "/(?:FROM|JOIN|UPDATE|INTO|,)\s+`?$wpdb->base_prefix(\d+)?_?(\w+)+?`?/i";

		$matches = [];
		preg_match_all( $regex, $query, $matches, PREG_SET_ORDER );

		$last_global_table = null;
		$last_blog_table   = null;
		$blog_ids          = [];
		foreach ( $matches as $match ) {
			if ( '' === $match[1] ) {
				$last_global_table = $match[2];
			} else {
				$blog_ids[ $match[1] ] = true;
				$last_blog_table       = $match[2];
			}
		}

		$blog_ids_count = count( $blog_ids );

		if ( $last_blog_table && ( $last_global_table || $blog_ids_count > 1 ) ) {
			$this->potential_multi_dataset_queries_collector->inc(
				[
					$last_global_table ?? 'null',
					$last_blog_table,
					$blog_ids_count >= 3 ? '3+' : (string) $blog_ids_count,
				]
			);

			if ( ! function_exists( '\Automattic\VIP\Logstash\log2logstash' ) ) {
				return;
			}

			$backtrace = function_exists( 'wp_debug_backtrace_summary' ) ? wp_debug_backtrace_summary( null, 4, false ) : []; // phpcs:ignore
			\Automattic\VIP\Logstash\log2logstash(
				[
					'severity' => 'debug',
					'feature'  => 'potential_multi_dataset_queries',
					'message'  => 'Potential multi dataset query detected',
					'extra'    => [
						'uri'               => isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( $_SERVER['REQUEST_URI'] ) : '',
						'http_method'       => isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( $_SERVER['REQUEST_METHOD'] ) : '',
						'backtrace'         => $backtrace,
						'last_global_table' => $last_global_table,
						'last_blog_table'   => $last_blog_table,
						'blog_ids_count'    => $blog_ids_count,
					],
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
