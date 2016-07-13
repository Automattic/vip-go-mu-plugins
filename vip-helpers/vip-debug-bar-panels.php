<?php

class WPCOM_VIP_Debug_Bar_Queries extends Debug_Bar_Panel {
	function init() {
		$this->title( __('Queries', 'debug-bar') );
	}

	function prerender() {
		$this->set_visible( defined( 'SAVEQUERIES' ) && SAVEQUERIES );
	}

	function render() {
		global $wpdb, $wp_object_cache, $timestart;

		$out        = '';
		$total_time = 0;

		if ( ! empty($wpdb->queries) ) {
			$show_many = isset( $_GET['debug_queries'] );

			if ( count( $wpdb->queries ) > 500 && ! $show_many ) {
				$out .= "<p>There are too many queries to show easily! <a href='" . esc_url(add_query_arg('debug_queries', 'true')) . "'>Show them anyway</a>.</p>";
			}

			$out .= '<ol class="wpd-queries">';

			$counter = 0;

			foreach ( $wpdb->queries as $q ) {
				$total_time += $q['elapsed'];

				if ( ! $show_many && ++$counter > 500 ) {
					continue;
				}

				// ts is the absolute time at which each query was executed
				$ts = explode( ' ', $q['microtime'] );
				$ts = $ts[0] + $ts[1];

				$table = $wpdb->get_table_from_query( $q['query'] );

				if ( isset( $q['connection']['elapsed'] ) ) {
					$connected = "Connected to {$q['connection']['host']}:{$q['connection']['port']} ({$q['connection']['name']}) in " . sprintf('%0.2f', 1000 * $q['connection']['elapsed']) . "ms";
				} else {
					$connected = "Reused connection to {$q['connection']['name']}";
				}

				$out .= '<li>';
				$out .= esc_html( $q['query'] );
				$out .= '<br/>';
				$out .= esc_html( $connected );
				$out .= '<div class="qdebug">' . esc_html( $q['debug'] ) . ' <span>#' . absint( $counter ) . ' (' . number_format( sprintf( '%0.1f', $q['elapsed'] * 1000), 1, '.', ',' ) . 'ms @ ' . sprintf( '%0.2f', 1000 * ( $ts - $timestart ) ) . 'ms)</span></div>';
				$out .= '</li>' . PHP_EOL;
			}
			$out .= '</ol>';
		} else {
			$out .= '<p><strong>There are no queries on this page, you won the prize!!! :)</strong></p>';
		}

		$num_queries = '';

		if ( $wpdb->num_queries ) {
			$num_queries = '<h2><span>Total Queries:</span>' . number_format( $wpdb->num_queries ) . "</h2>\n";
		}

		$query_time = '<h2><span>Total query time:</span>' . number_format( sprintf( '%0.1f', $total_time * 1000 ), 1 ) . "ms</h2>\n";

		$memory_usage  = '<h2><span>Peak Memory Used:</span>' . number_format( memory_get_peak_usage( ) ) . " bytes</h2>\n";
		$memcache_time = '<h2><span>Total memcache query time:</span>' .
			number_format( sprintf( '%0.1f', $wp_object_cache->time_total * 1000 ), 1, '.', ',' ) . "ms</h2>\n";

		$out = $num_queries . $query_time . $memory_usage . $memcache_time . $out;

		echo $out;
	}
}
