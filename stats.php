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
	private $javascripts;

	function __construct( ) {

		nocache_headers();
		add_action('js_concat_did_items', function( $scripts) {
			if (! isset ( $this->javascripts ) ) {
				$this->javascripts = [];
			}
			$this->javascripts = array_merge( $this->javascripts, $scripts );
		} );

		add_action( 'shutdown', function() {
			if( isset( $this->javascripts ) ) {
				$ratio = $this->calculate_efficiency_ratio( $this->javascripts );
				$this->send_efficiency_stat( $ratio );
			}
		} );
	}


	function calculate_efficiency_ratio( $scripts ) {
		$concats = array_filter( $scripts, function ( $var ) {
			return ( 'concat' === $var[ 'type' ] );
		});
		$total_concats = count( $concats );
		$concats_multiple = array_filter( $concats, function ( $var ) {
			return ( count( $var[ 'paths' ] ) > 1 );
		});
		$total_concats_multiple = count( $concats_multiple );
		return ( $total_concats_multiple / $total_concats );
	}


	function send_efficiency_stat( $ratio ) {

		// Figure out the range
		$ranges = array(
			'10' => '0 to 10 percent',
			'20' => '10 to 20 percent',
			'30' => '20 to 30 percent',
			'40' => '30 to 40 percent',
			'50' => '40 to 50 percent',
			'60' => '50 to 60 percent',
			'70' => '60 to 70 percent',
			'80' => '70 to 80 percent',
			'90' => '80 to 90 percent',
			'100' => '90 to 100 percent',
			'1000' => '100 percent',
		);

		foreach ( $ranges as $range_limit => $range ) {
			if ( ( $ratio * 100 ) < $range_limit ) {
				$ratio_range = $range;
				break;
			}
		}
		// \bump_stats_extras( 'vip-concat-efficiency', $ratio_range );
	}
}

function concat_metrics_init() {
	new Concat_Metrics();
}

add_action( 'init', __NAMESPACE__ . '\\concat_metrics_init' );
