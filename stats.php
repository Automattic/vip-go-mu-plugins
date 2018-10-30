<?php

/**
 * Plugin Name: VIP Stats
 * Description: Basic VIP stats functions.
 * Author: Automattic
 * License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

namespace Automattic\VIP\Stats;

// Limit tracking to production
if ( true === WPCOM_IS_VIP_ENV && false === WPCOM_SANDBOXED ) {
	add_action( 'async_transition_post_status', __NAMESPACE__ . '\track_publish_post', 9999, 2 );
}

/**
 * Count publish events regardless of post type
 */
function track_publish_post( $new_status, $old_status ) {
	if ( defined( 'WP_IMPORTING' ) && true === WP_IMPORTING ) {
		return;
	}

	if ( 'publish' !== $new_status || 'publish' === $old_status ) {
		return;
	}

	$pixel = add_query_arg( array(
		'v'                     => 'wpcom-no-pv',
		'x_vip-go-publish-post' => FILES_CLIENT_SITE_ID,
	), 'http://pixel.wp.com/b.gif' );

	wp_remote_get( $pixel, array(
		'blocking' => false,
		'timeout'  => 1,
	) );
}

class Concat_Metrics {
	private $javascripts = [];

	function __construct() {
		add_action( 'js_concat_did_items', [ $this, 'add_scripts' ] );
		add_action( 'shutdown', [ $this, 'do_stats_on_shutdown' ] );
	}

	public function add_scripts( $scripts ) {
		if ( empty( $scripts ) ) {
			return;
		}

		$this->javascripts = array_merge( $this->javascripts, $scripts );
	}

	public function do_stats_on_shutdown() {
		if ( ! empty( $this->javascripts ) ) {
			$ratio = $this->calculate_efficiency_ratio( $this->javascripts );
			$this->send_efficiency_stat( $ratio );
		}
	}

	private function calculate_efficiency_ratio( $scripts ) {

		$groups = array_reduce( $scripts, function ( $groups, $var ) {
			if ( 'concat' === $var['type'] ) {
				$num_scripts = count( $var['paths'] );
				$groups['total'] += $num_scripts;
				array_push( $groups['size'], $num_scripts );
			} elseif ( 'do_item' === $var['do_item'] ) {
				// do_item are individual scripts
				$groups['total'] += 1;
				array_push( $groups['size'], 1 );
			}
			return $groups;
		}, ['total' => 0, 'size' => []] );


		return ( array_sum( $groups['size'] ) / count( $groups['size'] ) ) / $groups['total'];
	}

	private function send_efficiency_stat( $ratio ) {
		// Figure out the range
		$ranges = array(
			'10' => '0 to 10 percent',
			'20' => '11 to 20 percent',
			'30' => '21 to 30 percent',
			'40' => '31 to 40 percent',
			'50' => '41 to 50 percent',
			'60' => '51 to 60 percent',
			'70' => '61 to 70 percent',
			'80' => '71 to 80 percent',
			'90' => '81 to 90 percent',
			'100' => '91 to 100 percent',
			'1000' => '101 percent',
		);

		foreach ( $ranges as $range_limit => $range ) {
			if ( ( $ratio * 100 ) <= $range_limit ) {
				$ratio_range = $range;
				break;
			}
		}

		$main_stat_name = 'vip-go-concat-efficiency';
		if ( is_user_logged_in() ) {
			$split_stat_name = $main_stat_name . '-login';
		} else {
			$split_stat_name = $main_stat_name . '-logout';
		}

		$pixel = add_query_arg( array(
			'v' => 'wpcom-no-pv',
			'x_' . $main_stat_name => $ratio_range,
			'x_' . $split_stat_name => $ratio_range,
		), 'http://pixel.wp.com/b.gif' );

		// phpcs:disable WordPress.VIP.RestrictedFunctions
		wp_remote_get( $pixel, array(
			'blocking' => false,
			'timeout'  => 1,
		) );
		// phpcs:enable WordPress.VIP.RestrictedFunctions
	}
}

function concat_metrics_init() {
	new Concat_Metrics();
}

add_action( 'init', __NAMESPACE__ . '\\concat_metrics_init' );
