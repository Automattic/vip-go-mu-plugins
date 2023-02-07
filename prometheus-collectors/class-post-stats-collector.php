<?php

namespace Automattic\VIP\Prometheus;

use Prometheus\Gauge;
use Prometheus\RegistryInterface;

/**
 * @codeCoverageIgnore
 */
class Post_Stats_Collector implements CollectorInterface {
	private Gauge $post_gauge;

	public function initialize( RegistryInterface $registry ): void {
		$this->post_gauge = $registry->getOrRegisterGauge(
			'post',
			'count',
			'Number of posts by type and status',
			[ 'site_id', 'post_type', 'post_status' ]
		);
	}

	public function collect_metrics(): void {
		$site_id = (string) get_current_blog_id();
		$types   = get_post_types();
		foreach ( $types as $type ) {
			$posts = wp_count_posts( $type );
			foreach ( $posts as $status => $count ) {
				$this->post_gauge->set( $count, [ $site_id, $type, $status ] );
			}
		}
	}
}
