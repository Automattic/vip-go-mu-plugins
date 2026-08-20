<?php

namespace Automattic\Memcached;

/**
 * Helps keep track of cache-related stats.
 *
 * TODO: Once public access to these methods/properties is deprecated in the main class,
 * we can cleanup the data structures internally here.
*/
class Stats {
	/** @var array<string,int> */
	public array $stats = [];

	/**
	 * @psalm-var array<string, array<array{0: string, 1: string|string[], 2: int|null, 3: float|null, 4: string, 5: string, 6: string|null }>>
	 */
	public array $group_ops = [];

	public float $time_total = 0;
	public int $size_total   = 0;

	public float $slow_op_microseconds = 0.005; // 5 ms

	private string $key_salt;

	public function __construct( string $key_salt ) {
		$this->key_salt = $key_salt;

		$this->stats = [
			'get'          => 0,
			'get_local'    => 0,
			'get_multi'    => 0,
			'set'          => 0,
			'set_local'    => 0,
			'add'          => 0,
			'delete'       => 0,
			'delete_local' => 0,
			'slow-ops'     => 0,
		];
	}

	/*
	|--------------------------------------------------------------------------
	| Stat tracking.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Keep stats for a memcached operation.
	 *
	 * @param string $op The operation taking place, such as "set" or "get".
	 * @param string|string[] $keys The memcached key/keys involved in the operation.
	 * @param string $group The group the keys are in.
	 * @param ?int $size The size of the data invovled in the operation.
	 * @param ?float $time The time the operation took.
	 * @param string $comment Extra notes about the operation.
	 *
	 * @return void
	 */
	public function group_ops_stats( $op, $keys, $group, $size = null, $time = null, $comment = '' ) {
		$this->increment_stat( $op );

		// Don't keep further details about the local operations.
		if ( false !== strpos( $op, '_local' ) ) {
			return;
		}

		if ( ! is_null( $size ) ) {
			$this->size_total += $size;
		}

		if ( ! is_null( $time ) ) {
			$this->time_total += $time;
		}

		$keys = $this->strip_memcached_keys( $keys );

		if ( $time > $this->slow_op_microseconds && 'get_multi' !== $op ) {
			$this->increment_stat( 'slow-ops' );

			/** @psalm-var string|null $backtrace */
			$backtrace                     = function_exists( 'wp_debug_backtrace_summary' ) ? wp_debug_backtrace_summary() : null; // phpcs:ignore
			$this->group_ops['slow-ops'][] = array( $op, $keys, $size, $time, $comment, $group, $backtrace );
		}

		$this->group_ops[ $group ][] = array( $op, $keys, $size, $time, $comment );
	}

	/**
	 * Increment the stat counter for a memcached operation.
	 *
	 * @param string $field The stat field/group being incremented.
	 * @param int $num Amount to increment by.
	 *
	 * @return void
	 */
	public function increment_stat( $field, $num = 1 ) {
		if ( ! isset( $this->stats[ $field ] ) ) {
			$this->stats[ $field ] = $num;
		} else {
			$this->stats[ $field ] += $num;
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Utils.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Key format: key_salt:flush_number:table_prefix:key_name
	 *
	 * We want to strip the `key_salt:flush_number` part to not leak the memcached keys.
	 * If `key_salt` is set we strip `'key_salt:flush_number`, otherwise just strip the `flush_number` part.
	 *
	 * @param string|string[] $keys
	 * @return string|string[]
	 */
	public function strip_memcached_keys( $keys ) {
		$keys = is_array( $keys ) ? $keys : [ $keys ];

		foreach ( $keys as $index => $value ) {
			$offset = 0;

			// Strip off the key salt piece.
			if ( ! empty( $this->key_salt ) ) {
				$salt_piece = strpos( $value, ':' );
				$offset     = false === $salt_piece ? 0 : $salt_piece + 1;
			}

			// Strip off the flush number.
			$flush_piece    = strpos( $value, ':', $offset );
			$start          = false === $flush_piece ? $offset : $flush_piece;
			$keys[ $index ] = substr( $value, $start + 1 );
		}

		if ( 1 === count( $keys ) ) {
			return $keys[0];
		}

		return $keys;
	}

	/*
	|--------------------------------------------------------------------------
	| Stats markup output.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Returns the collected raw stats.
	 */
	public function get_stats(): array {
		$stats = [
			'operation_counts' => $this->stats,
			'operations'       => [],
			'groups'           => [],
			'slow-ops'         => [],
			'slow-ops-groups'  => [],
			'totals'           => [
				'query_time' => $this->time_total,
				'size'       => $this->size_total,
			],
		];

		foreach ( $this->group_ops as $cache_group => $dataset ) {
			$cache_group = empty( $cache_group ) ? 'default' : $cache_group;

			foreach ( $dataset as $data ) {
				$operation = $data[0];
				$op        = [
					'key'    => $data[1],
					'size'   => $data[2],
					'time'   => $data[3],
					'group'  => $cache_group,
					'result' => $data[4],
				];

				if ( 'slow-ops' === $cache_group ) {
					$key             = 'slow-ops';
					$groups_key      = 'slow-ops-groups';
					$op['group']     = $data[5];
					$op['backtrace'] = $data[6];
				} else {
					$key        = 'operations';
					$groups_key = 'groups';
				}

				$stats[ $key ][ $operation ][] = $op;
				if ( ! in_array( $op['group'], $stats[ $groups_key ], true ) ) {
					$stats[ $groups_key ][] = $op['group'];
				}
			}
		}

		return $stats;
	}

	public function stats(): void {
		$this->js_toggle();

		$total_query_time = number_format_i18n( (float) sprintf( '%0.1f', $this->time_total * 1000 ), 1 ) . ' ms';

		$total_size = size_format( $this->size_total, 2 );
		$total_size = false === $total_size ? '0 B' : $total_size;

		echo '<h2><span>Total memcached query time:</span>' . esc_html( $total_query_time ) . '</h2>';
		echo "\n";
		echo '<h2><span>Total memcached size:</span>' . esc_html( $total_size ) . '</h2>';
		echo "\n";

		foreach ( $this->stats as $stat => $n ) {
			if ( empty( $n ) ) {
				continue;
			}

			echo '<h2>';
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $this->colorize_debug_line( "$stat $n" );
			echo '</h2>';
		}

		echo "<ul class='debug-menu-links' style='clear:left;font-size:14px;'>\n";
		$groups = array_keys( $this->group_ops );
		usort( $groups, 'strnatcasecmp' );

		$active_group = $groups[0];
		// Always show `slow-ops` first
		if ( in_array( 'slow-ops', $groups ) ) {
			$slow_ops_key = array_search( 'slow-ops', $groups );
			$slow_ops     = $groups[ $slow_ops_key ];
			unset( $groups[ $slow_ops_key ] );
			array_unshift( $groups, $slow_ops );
			$active_group = 'slow-ops';
		}

		$total_ops    = 0;
		$group_titles = array();
		foreach ( $groups as $group ) {
			$group_name = empty( $group ) ? 'default' : $group;
			$group_size = (int) array_sum( array_map( fn( $op ) => $op[2], $this->group_ops[ $group ] ) );
			$group_time = (float) sprintf( '%0.1f', array_sum( array_map( fn( $op ) => $op[3], $this->group_ops[ $group ] ) ) * 1000 );

			$group_ops              = count( $this->group_ops[ $group ] );
			$group_size             = size_format( $group_size, 2 );
			$group_time             = number_format_i18n( $group_time, 1 );
			$total_ops             += $group_ops;
			$group_title            = "{$group_name} [$group_ops][$group_size][{$group_time} ms]";
			$group_titles[ $group ] = $group_title;
			echo "\t<li><a href='#' onclick='memcachedToggleVisibility( \"object-cache-stats-menu-target-" . esc_js( $group_name ) . "\", \"object-cache-stats-menu-target-\" );'>" . esc_html( $group_title ) . "</a></li>\n";
		}
		echo "</ul>\n";

		echo "<div id='object-cache-stats-menu-targets'>\n";
		foreach ( $groups as $group ) {
			$group_name = empty( $group ) ? 'default' : $group;

			$current = $active_group == $group ? 'style="display: block"' : 'style="display: none"';
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo "<div id='object-cache-stats-menu-target-" . esc_attr( $group_name ) . "' class='object-cache-stats-menu-target' $current>\n";
			echo '<h3>' . esc_html( $group_titles[ $group ] ) . '</h3>' . "\n";
			echo "<pre>\n";
			foreach ( $this->group_ops[ $group ] as $index => $arr ) {
				echo esc_html( sprintf( '%3d ', $index ) );
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $this->get_group_ops_line( $index, $arr );
			}
			echo "</pre>\n";
			echo '</div>';
		}

		echo '</div>';
	}

	public function js_toggle(): void {
		echo "
		<script>
		function memcachedToggleVisibility( id, hidePrefix ) {
			var element = document.getElementById( id );
			if ( ! element ) {
				return;
			}

			// Hide all element with `hidePrefix` if given. Used to display only one element at a time.
			if ( hidePrefix ) {
				var groupStats = document.querySelectorAll( '[id^=\"' + hidePrefix + '\"]' );
				groupStats.forEach(
					function ( element ) {
					    element.style.display = 'none';
					}
				);
			}

			// Toggle the one we clicked.
			if ( 'none' === element.style.display ) {
				element.style.display = 'block';
			} else {
				element.style.display = 'none';
			}
		}
		</script>
		";
	}

	/**
	 * @param string $line
	 * @param string $trailing_html
	 * @return string
	 */
	public function colorize_debug_line( $line, $trailing_html = '' ) {
		$colors = array(
			'get'          => 'green',
			'get_local'    => 'lightgreen',
			'get_multi'    => 'fuchsia',
			'get_multiple' => 'navy',
			'set'          => 'purple',
			'set_local'    => 'orchid',
			'add'          => 'blue',
			'delete'       => 'red',
			'delete_local' => 'tomato',
			'slow-ops'     => 'crimson',
		);

		$cmd = substr( $line, 0, (int) strpos( $line, ' ' ) );

		// Start off with a neutral default color, and use a more specific one if possible.
		$color_for_cmd = isset( $colors[ $cmd ] ) ? $colors[ $cmd ] : 'brown';

		$cmd2 = "<span style='color:" . esc_attr( $color_for_cmd ) . "; font-weight: bold;'>" . esc_html( $cmd ) . '</span>';

		return $cmd2 . esc_html( substr( $line, strlen( $cmd ) ) ) . "$trailing_html\n";
	}

	/**
	 * @param string|int $index
	 * @param array $arr
	 * @psalm-param array{0: string, 1: string|string[], 2: int|null, 3: float|null, 4: string, 5: string, 6: string|null } $arr
	 *
	 * @return string
	 */
	public function get_group_ops_line( $index, $arr ) {
		// operation
		$line = "{$arr[0]} ";

		// keys
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		$json_encoded_key = json_encode( $arr[1] );
		$line            .= $json_encoded_key . ' ';

		// comment
		if ( ! empty( $arr[4] ) ) {
			$line .= "{$arr[4]} ";
		}

		// size
		if ( isset( $arr[2] ) ) {
			$size = size_format( $arr[2], 2 );

			if ( $size ) {
				$line .= '(' . $size . ') ';
			}
		}

		// time
		if ( isset( $arr[3] ) ) {
			$line .= '(' . number_format_i18n( (float) sprintf( '%0.1f', $arr[3] * 1000 ), 1 ) . ' ms)';
		}

		// backtrace
		$bt_link = '';
		if ( isset( $arr[6] ) ) {
			$key_hash = md5( $index . $json_encoded_key );
			$bt_link  = " <small><a href='#' onclick='memcachedToggleVisibility( \"object-cache-stats-debug-$key_hash\" );'>Toggle Backtrace</a></small>";
			$bt_link .= "<pre id='object-cache-stats-debug-$key_hash' style='display:none'>" . esc_html( $arr[6] ) . '</pre>';
		}

		return $this->colorize_debug_line( $line, $bt_link );
	}
}
