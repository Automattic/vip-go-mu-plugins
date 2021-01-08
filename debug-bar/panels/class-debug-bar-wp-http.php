<?php

class Debug_Bar_WP_Http extends Debug_Bar_Panel {
	public $requests = [];

	public $time_limit = 250; // milliseconds
	public $total_time = 0;
	public $num_errors = 0;

	function early_init() {
		add_filter( 'http_request_args', [ $this, 'before_http_request' ], 10, 3 );
		add_filter( 'http_api_debug', [ $this, 'after_http_request' ], 10, 5 );
	}

	function before_http_request( $args, $url ) {
		$args['time_start'] = microtime( true );

		$this->requests["{$args['time_start']}"] = [
			'url' => $url,
			'args' => $args
		];

		return $args;
	}

	function after_http_request( $response, $type, $class, $args, $url ) {
		if ( $type !== 'response' ) {
			return;
		}

		$args['time_stop'] = microtime( true );

		$args['duration'] = $args['time_stop'] - $args['time_start'];
		$args['duration'] *= 1000;

		$this->total_time += $args['duration'];

		if ( $this->is_request_error( $response ) ) {
			$this->num_errors++;
		} else {
			if ( ! isset( $_GET['fullbody'] ) ) {
				$response['body'] = '[omitted]';
				unset( $response['http_response'] );
			}
		}

		$this->requests["{$args['time_start']}"] = array_merge(
			$this->requests["{$args['time_start']}"],
			[
				'r' => $response,
				'class' => $class,
				'args' => $args,
				'url' => $url,
				'stack_trace' => wp_debug_backtrace_summary( null, 0, false ),
			]
		);
	}

	function is_request_error( $response ) {
		if (
			is_wp_error( $response )
			|| $response['response']['code'] >= 400
		) {
			return true;
		}

		return false;
	}

	function init() {
		$this->title( __( 'WP_Http', 'debug-bar' ) );
	}

	function prerender() {
		$this->set_visible( ! empty( $this->requests ) );
	}

	function debug_bar_classes( $classes ) {
		if (
			$this->num_errors > 0
			|| $this->total_time > $this->time_limit
		) {
			$classes[] = 'debug-bar-php-warning-summary';
		}
		return $classes;
	}

	function render() {
		$num_requests = number_format( count( $this->requests ) );
		$elapsed = number_format( $this->total_time, 1 );
		$num_errors = number_format( $this->num_errors );

		if ( isset( $_GET['fullbody'] ) ) {
			$fullbody = '<p style="clear:left">Request and response bodies are included. <a href="' . esc_attr( remove_query_arg( 'fullbody' ) ) . '">Reload with those omitted.</a>';
		} else {
			$fullbody = '<p style="clear:left">Request and response bodies are omitted. <a href="' . esc_attr( add_query_arg( 'fullbody', 'please' ) ) . '">Reload with those included.</a>';
		}

		$css_errors = '';
		if (
			$this->num_errors > 0
			|| $this->total_time > $this->time_limit
		) {
			$css_errors = "#wp-admin-bar-debug-bar-Debug_Bar_WP_Http, #debug-menu-link-Debug_Bar_WP_Http { background-color: #d00 !important; background-image: -moz-linear-gradient(bottom,#f44,#d00) !important; background-image: -webkit-gradient(linear,left bottom,left top,from(#f44),to(#d00)) important; }\n";
		}

		$elapsed_class = '';
		if ( $this->total_time > $this->time_limit ) {
			$elapsed_class = 'debug_bar_http_error';
		}

		$errors_class = '';
		if ( $this->num_errors > 0 ) {
			$errors_class = 'debug_bar_http_error';
		}

		$out =<<<HTML
<style>
	#debug_bar_http { clear: left; }
	#debug_bar_http .err, .debug_bar_http_error { background-color: #ffebe8; border: 1px solid #c00 !important; }
	#debug_bar_http th, #debug_bar_http td { padding: 8px; }
	#debug_bar_http pre { font-family: monospace; }
	{$css_errors}
</style>

<script>
function debug_bar_http_toggle( id ) {
	var e = document.getElementById( id );
	if ( e.style.display === "" ) {
		e.style.display = "none";
	} else {
		e.style.display = "";
	}
}
</script>

<h2><span>HTTP Requests:</span> {$num_requests}</h2>
<h2 class="{$elapsed_class}"><span>Total Elapsed:</span> {$elapsed} ms</h2>
<h2 class="{$errors_class}"><span>Errors:</span> {$num_errors}</h2>

{$fullbody}

<table id="debug_bar_http">
	<thead>
		<tr>
			<th>More</th>
			<th>Start</th>
			<th>Duration</th>
			<th>Method</th>
			<th>URL</th>
			<th>Code</th>
		</tr>
	</thead>
	<tbody>
HTML;

		foreach( $this->requests as $i => $r ) {
			$class = '';
			if (
				$this->is_request_error( $r['r'] )
				|| $r['args']['duration'] > $this->time_limit
			) {
				$class = 'err';
			}

			$start = $r['args']['time_start'] - $_SERVER['REQUEST_TIME_FLOAT'];
			$start *= 1000;
			$start = number_format( $start, 1 );

			$duration = number_format( $r['args']['duration'], 1 );
			$method = esc_html( $r['args']['method'] );
			$url = esc_html( $r['url'] );

			if ( is_wp_error( $r['r'] ) ) {
				$code = esc_html( $r['r']->get_error_code() );
			} else {
				$code = esc_html( $r['r']['response']['code'] );
			}

			$details = esc_html( print_r( $r, true ) );

			$record_id = 'debug_bar_http_record_' . md5( $i );
			$out .=<<<HTML
		<tr class="{$class}">
			<td><a onclick="debug_bar_http_toggle( '{$record_id}' );">Toggle</a></td>
			<td>{$start} ms</td>
			<td>{$duration} ms</td>
			<td>{$method}</td>
			<td>{$url}</td>
			<td>{$code}</td>
		</tr>

		<tr id="{$record_id}" style="display: none">
			<td colspan="5"><pre>{$details}</pre></td>
		</tr>
HTML;
		}

		$out .=<<<HTML
	</tbody>
</table>
HTML;

		echo $out;
	}
}
