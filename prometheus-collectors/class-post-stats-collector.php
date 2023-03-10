<?php

namespace Automattic\VIP\Prometheus;

use Automattic\VIP\Utils\Context;
use Prometheus\Gauge;
use Prometheus\RegistryInterface;

/**
 * @codeCoverageIgnore
 */
class Post_Stats_Collector implements CollectorInterface {
	private Gauge $post_gauge;

	public const METRIC_OPTION = 'vip-prom-posts';
	private string $blog_id;

	public function initialize( RegistryInterface $registry ): void {
		$this->blog_id = Plugin::get_instance()->get_site_label();

		$this->post_gauge = $registry->getOrRegisterGauge(
			'post',
			'count',
			'Number of posts by type and status',
			[ 'site_id', 'post_type', 'post_status' ]
		);
	}

	public function collect_metrics(): void {
		$metrics = get_option( self::METRIC_OPTION, [] );
		if ( ! $metrics ) {
			return;
		}

		foreach ( $metrics as $type => $metric ) {
			foreach ( $metric as $status => $count ) {
				$this->post_gauge->set( $count, [ $this->blog_id, $type, $status ] );
			}
		}
	}

	/**
	 * Process metrics off the web request path
	 */
	public function process_metrics(): void {
		if ( ! Context::is_wp_cli() ) {
			return;
		}

		$ret   = [];
		$types = get_post_types( [ 'public' => true ] );
		foreach ( $types as $type ) {
			$posts        = wp_count_posts( $type );
			$ret[ $type ] = [];
			foreach ( $posts as $status => $count ) {
				$ret[ $type ][ $status ] = $count;
			}
		}

		update_option( self::METRIC_OPTION, $ret, false );
	}
}
