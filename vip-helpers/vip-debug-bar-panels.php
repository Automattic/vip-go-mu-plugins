<?php

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- needs refactoring

class WPCOM_VIP_Debug_Bar_Queries extends Debug_Bar_Panel {
	public function init() {
		$this->title( __( 'Queries', 'debug-bar' ) );
	}

	public function prerender() {
		$this->set_visible( defined( 'SAVEQUERIES' ) && SAVEQUERIES );
	}

	public function render() {
		/** @var wpdb $wpdb */
		global $wpdb, $wp_object_cache, $timestart;

		$out        = '';
		$total_time = 0;

		if ( ! empty( $wpdb->queries ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce is not available
			$show_many = isset( $_GET['debug_queries'] );

			if ( count( $wpdb->queries ) > 500 && ! $show_many ) {
				$out .= "<p>There are too many queries to show easily! <a href='" . esc_url( add_query_arg( 'debug_queries', 'true' ) ) . "'>Show them anyway</a>.</p>";
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

				if ( isset( $q['connection']['elapsed'] ) ) {
					$connected = "Connected to {$q['connection']['host']}:{$q['connection']['port']} ({$q['connection']['name']}) in " . sprintf( '%0.2f', 1000 * $q['connection']['elapsed'] ) . 'ms';
				} else {
					$connected = "Reused connection to {$q['connection']['name']}";
				}

				$out .= '<li>';
				$out .= esc_html( $q['query'] );
				$out .= '<br/>';
				$out .= esc_html( $connected );
				$out .= '<div class="qdebug">' . esc_html( $q['debug'] ) . ' <span>#' . absint( $counter ) . ' (' . number_format( sprintf( '%0.1f', $q['elapsed'] * 1000 ), 1, '.', ',' ) . 'ms @ ' . sprintf( '%0.2f', 1000 * ( $ts - $timestart ) ) . 'ms)</span></div>';
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

		$memory_usage = '<h2><span>Peak Memory Used:</span>' . number_format( memory_get_peak_usage() ) . " bytes</h2>\n";

		$memcache_time = '';
		if ( property_exists( $wp_object_cache, 'time_total' ) ) {
			$memcache_time = number_format( sprintf( '%0.1f', $wp_object_cache->time_total * 1000 ), 1, '.', ',' );
			$memcache_time = '<h2><span>Total memcache query time:</span>' . $memcache_time . "ms</h2>\n";
		}

		$out = $num_queries . $query_time . $memory_usage . $memcache_time . $out;

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- no unescaped user-controlled input
		echo $out;
	}
}

class WPCOM_VIP_Debug_Bar_Memcached extends Debug_Bar_Panel {
	public function init() {
		$this->title( __( 'Memcache', 'debug-bar' ) );
	}

	public function prerender() {
		$this->set_visible( true );
	}

	public function render() {
		global $wp_object_cache;
		ob_start();

		echo "<div id='memcache-stats'>";

		$wp_object_cache->stats();

		echo '</div>';

		$out = ob_get_clean();
		$out = str_replace( '&nbsp;', '', $out );

		// // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $out;
	}
}

class WPCOM_VIP_Debug_Bar_Query_Summary extends Debug_Bar_Panel {
	public function init() {
		$this->title( __( 'Query Summary', 'debug-bar' ) );
	}

	public function prerender() {
		$this->set_visible( true );
	}

	public function render() {
		global $wpdb;

		$query_types       = array();
		$query_type_counts = array();

		if ( is_array( $wpdb->queries ) ) {
			$count = count( $wpdb->queries );

			for ( $i = 0; $i < $count; ++$i ) {
				$query = '';

				if ( is_array( $wpdb->queries[ $i ] ) && array_key_exists( 'query', $wpdb->queries[ $i ] ) ) {
					$query = $wpdb->queries[ $i ]['query'];
					$query = preg_replace( '#\s+#', ' ', $query );
					$query = str_replace( '\"', '', $query );
					$query = str_replace( "\'", '', $query );
					$query = preg_replace( '#wp_\d+_#', 'wp_?_', $query );
					$query = preg_replace( "#'[^']*'#", "'?'", $query );
					$query = preg_replace( '#"[^"]*"#', "'?'", $query );
					$query = preg_replace( '#in ?\([^)]*\)#i', 'in(?)', $query );
					$query = preg_replace( '#= ?\d+ ?#', '= ? ', $query );
					$query = preg_replace( '#\d+(, ?)?#', '?\1', $query );

					$query = preg_replace( '#\s+#', ' ', $query );
				}

				if ( ! isset( $query_types[ $query ] ) ) {
					$query_types[ $query ] = 0;
				}

				if ( ! isset( $query_type_counts[ $query ] ) ) {
					$query_type_counts[ $query ] = 0;
				}

				$query_type_counts[ $query ]++;

				if ( is_array( $wpdb->queries[ $i ] ) && array_key_exists( 'elapsed', $wpdb->queries[ $i ] ) ) {
					$query_types[ $query ] += $wpdb->queries[ $i ]['elapsed'];
				}
			}
		}

		arsort( $query_types );

		$query_time   = array_sum( $query_types );
		$out          = '<pre style="overflow:auto;">';
		$count        = 0;
		$max_time_len = 0;

		foreach ( $query_types as $q => $t ) {
			$count++;

			$query_time_pct = 0;
			if ( $query_time ) {
				$query_time_pct = ( $t / $query_time );
			}

			$max_time_len = max( $max_time_len, strlen( sprintf( '%0.2f', $t * 1000 ) ) );

			if ( $query_time_pct >= .3 ) {
				$color = 'red';
			} elseif ( $query_time_pct >= .1 ) {
				$color = 'orange';
			} else {
				$color = 'green';
			}

			$out .= sprintf(
				"<span style='color:%s;'>%s queries for %sms &raquo; %s</span>\r\n",
				$color,
				str_pad( $query_type_counts[ $q ], 5, ' ', STR_PAD_LEFT ),
				str_pad( sprintf( '%0.2f', $t * 1000 ), $max_time_len, ' ', STR_PAD_LEFT ),
				esc_html( $q )
			);
		}

		$out .= '</pre>';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $out;
	}
}

class WPCOM_VIP_Debug_Bar_DB_Connections extends Debug_Bar_Panel {
	public function init() {
		$this->title( __( 'DB Connections', 'debug-bar' ) );
	}

	public function prerender() {
		$this->set_visible( true );
	}

	public function render() {
		foreach ( $GLOBALS as $global ) {
			if ( ! is_object( $global ) || ! get_class( $global ) || ! is_a( $global, 'wpdb' ) ) {
				continue;
			}

			if ( ! property_exists( $global, 'db_connections' ) ) {
				break;
			}

			if ( is_array( $global->db_connections ) && count( $global->db_connections ) ) {
				$elapsed = 0;

				foreach ( $global->db_connections as $conn ) {
					if ( isset( $conn['elapsed'] ) ) {
						$elapsed += $conn['elapsed'];
					}
				}
				?>
				<h2><span>Total connection time:</span> <?php echo number_format( sprintf( '%0.1f', $elapsed * 1000 ), 1 ); ?>ms</h2>
				<h2><span>Total connections:</span> <?php echo count( $global->db_connections ); ?></h2>
				<?php
				$keys = array_keys( reset( $global->db_connections ) );
				?>
				<table style="clear:both; font-size: 130%" cellspacing="8px">
				<thead>
					<tr>
				<?php	foreach ( $keys as $key ) { ?>
						<th scope="col" style="text-align: center; font-size: 120%; border-bottom: 1px solid black"><?php echo esc_html( $key ); ?></th>
	<?php	} ?>
					</tr>
				</thead>
				<tbody style="text-align: right">
				<?php	foreach ( $global->db_connections as $conn ) { ?>
					<tr>
					<?php	foreach ( $keys as $key ) { ?>
						<td>
						<?php
						$value = isset( $conn[ $key ] ) ? $conn[ $key ] : '-';

						switch ( $key ) {
							case 'elapsed':
								printf( '%0.1fms', $value * 1000 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- number

								break;

							default:
								if ( true === $value ) {
									echo 'true';
								} elseif ( false === $value ) {
									echo 'false';
								} else {
									echo esc_html( print_r( $value, 1 ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
								}
						}
						?>
						</td>
	<?php	} ?>
					</tr>
	<?php	} ?>
				</tbody>
				</table>
				<?php	
			}
		}
	}
}

class WPCOM_VIP_Debug_Bar_PHP extends Debug_Bar_PHP {
	public function init() {
		$this->title( __( 'Notices / Warnings', 'debug-bar' ) );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		$this->real_error_handler = set_error_handler( array( $this, 'error_handler' ) );
	}
}

// Tracks remote requests made using the WordPress HTTP API and displays their results
class WPCOM_VIP_Debug_Bar_Remote_Requests extends Debug_Bar_Panel {
	public $ignore_urls = array(
		'http://127.0.0.1/wp-cron.php?doing_wp_cron',
	);

	public $requests        = array();
	public $status_counts   = array();
	public $current_request = array();

	public function init() {
		$this->title( __( 'Remote Requests', 'debug-bar' ) );

		// phpcs:ignore WordPressVIPMinimum.Hooks.RestrictedHooks.http_request_args
		add_filter( 'http_request_args', array( $this, 'log_http_requests' ), 99, 2 ); // after all args have been set up
		add_action( 'http_api_debug', array( $this, 'log_http_request_result' ), 0, 5 ); // as soon as it's complete

		$this->status_counts = array(
			'Informational (1xx)'    => 0,
			'Success (2xx)'          => 0,
			'Multiple Choices (3xx)' => 0,
			'Client Error (4xx)'     => 0,
			'Server Error (5xx)'     => 0,
			'Unknown'                => 0,
		);

	}

	public function prerender() {
		if ( empty( $this->requests ) ) {
			$this->set_visible( false );
		}
	}

	public function render() {
		global $wp;
		?>
		<div id='debug-bar-remote-requests'>

			<h2>
				<span>Total Time</span>
				<?php echo number_format( $this->total_time, 2 ) . 's'; ?>
			</h2>

			<?php foreach ( $this->status_counts as $status_type => $status_count ) : ?>
				<h2>
					<span><?php echo esc_html( $status_type ); ?></span>
					<?php echo intval( $status_count ); ?>
				</h2>
			<?php endforeach; ?>
			<div class="clear"></div>

			<h3 style="font-family: georgia, times, serif; font-size: 22px;">Remote Requests:</h3>

			<?php if ( ! empty( $this->requests ) ) : ?>
				<table style="clear:both; font-size: 130%" cellspacing="8px">
					<thead>
						<tr>
								<th scope="col" style="text-align: center; font-size: 120%; border-bottom: 1px solid black">status</th>
								<th scope="col" style="text-align: center; font-size: 120%; border-bottom: 1px solid black">url</th>
								<th scope="col" style="text-align: center; font-size: 120%; border-bottom: 1px solid black">time</th>
								<th scope="col" style="text-align: center; font-size: 120%; border-bottom: 1px solid black">message</th>
							</tr>
					</thead>
					<tbody>
					<?php 
					foreach ( $this->requests as $url => $requests ) :
						foreach ( $requests as $request ) : 
							?>
							<tr>
								<td><strong><?php echo esc_html( $request['status'] ); ?></strong></td>
								<td><code><?php echo esc_url( $url ); ?></code></td>
								<td><?php echo ( -1 < $request['time'] ) ? number_format( $request['time'], 2 ) . 's' : 'unknown'; ?></td>
								<td><?php echo esc_html( $request['message'] ); ?></td>
							</tr>
							<tr>
								<td colspan="4">
									<?php echo esc_html( $request['backtrace'] ); ?>
								</td>
							</tr>
							<?php 
						endforeach;
					endforeach; 
					?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	public function log_http_requests( $args, $url ) {
		if ( ! in_array( $url, $this->ignore_urls ) ) {
			if ( ! isset( $this->requests[ $url ] ) ) {
				$this->requests[ $url ] = array();
			}
		}

		// Store the current request so we can track times
		$this->current_request = array(
			'url'   => $url,
			'start' => microtime( true ),
		);

		return $args;
	}

	public function log_http_request_result( $response, $context, $transport, $args, $url ) {
		if ( 'response' != $context ) {
			return;
		}

		if ( ! isset( $this->total_time ) ) {
			$this->total_time = 0;
		}

		// We don't have an easy way to match exact requests initiated with those completed since $args can be changed before it gets to us here
		if ( isset( $this->current_request['url'] ) && $url == $this->current_request['url'] ) {
			$time_elapsed = microtime( true ) - $this->current_request['start'];
		} else {
			$time_elapsed = -1; // hm, some other request got in the way
		}

		// clear the values
		$this->current_request = array();

		if ( ! $response || is_wp_error( $response ) ) {
			$message = is_wp_error( $response ) ? $response->get_error_message() : 'Something clearly went very wrong...';

			$status = 'fail';
		} else {
			$message = $response['response']['message'];

			$status = $response['response']['code'];
		}

		$this->requests[ $url ][] = array(
			'message'   => $message,
			'status'    => $status,
			'time'      => $time_elapsed,
			'backtrace' => wp_debug_backtrace_summary(),    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_wp_debug_backtrace_summary
		);

		if ( -1 < $time_elapsed ) {
			$this->total_time += $time_elapsed;
		}

		// Prevent debug notice if no request counts exist yet
		if ( empty( $this->status_counts['Total Requests'] ) ) {
			$this->status_counts['Total Requests'] = 0;
		}

		$this->status_counts['Total Requests']++;

		switch ( substr( $status, 0, 1 ) ) {
			case 1:
				$this->status_counts['Informational (1xx)']++;
				break;
			case 2:
				$this->status_counts['Success (2xx)']++;
				break;
			case 3:
				$this->status_counts['Multiple Choices (3xx)']++;
				break;
			case 4:
				$this->status_counts['Client Error (4xx)']++;
				break;
			case 5:
				$this->status_counts['Server Error (5xx)']++;
				break;
			default:
				$this->status_counts['Unknown']++;
				break;
		}
	}
}
