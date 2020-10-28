<?php

class Debug_Bar_WP_Http_Request {
	public $time_start;
	public $time_end;

	public $request;
	public $result;
	public $stack_trace;

	function __construct( $request ) {
		$this->time_start = microtime( true );
		if ( ! isset( $_GET['fullbody'] ) ) {
			$request['args']['body'] = '[omitted]';
		}
		$this->request = $request;
	}

	function end( $result ) {
		$this->time_end = microtime( true );
		if ( is_array( $result['r'] ) && ! isset( $_GET['fullbody'] ) ) {
			$result['r']['body'] = '[omitted]';
			$result['args']['body'] = '[omitted]';
		}
		$this->result = $result;
		$this->stack_trace = wp_debug_backtrace_summary( null, 0, false );
	}
}

class Debug_Bar_WP_Http extends Debug_Bar_Panel {
	public $requests = array();

	function early_init() {
		add_filter( 'http_request_args', array( $this, 'before_http_request' ), 10, 3 );
		add_action( 'http_api_debug', array( $this, 'after_http_request' ), 10, 5 );
	}

	function before_http_request( $args, $url ) {
		$request = new Debug_Bar_WP_Http_Request( array(
			'args' => $args,
			'url' => $url,
		) );

		$this->requests["$request->time_start"] = $request;
		$args['time_start'] = $request->time_start;

		return $args;
	}

	function after_http_request( $response, $type, $class, $args, $url ) {
		if ( $type !== 'response' ) {
			return;
		}

		$request =& $this->requests["{$args['time_start']}"];
		$request->end( array(
			'r' => $response,
			'class' => $class,
			'args' => $args,
			'url' => $url,
		) );
	}

	function count_errors() {
		return 0;
	}

	function init() {
		$this->title( __( 'WP_Http', 'debug-bar' ) );
	}

	function prerender() {
		$this->set_visible( ! empty( $this->requests ) );
	}

	function debug_bar_classes( $classes ) {
		if ( $this->count_errors() ) {
			$classes[] = 'debug-bar-php-warning-summary';
		}

		return $classes;
	}

	function render() {
		$out = "
		<style>
			#pdbhttp { clear: left; }
			#pdbhttp .err { background-color: #ffebe8; border: 1px solid #c00; }
			#pdbhttp th, #pdbhttp td { padding: 8px; }
			#pdbhttp pre { font-family: monospace; }
		</style>\n";
		$out .= "<script>function pdbhttp_toggle(e) {jQuery(e).toggle();}</script>";
		$out .= "<table id='pdbhttp'>\n";
		$out .= "<thead><tr><th>More</th><th>Start</th><th>Duration</th><th>Method</th><th>URL</th><th>Code</th></tr></thead>\n<tbody>\n";

		$total_time = 0;
		$total_errors = 0;
		foreach ( array_values( $this->requests ) as $i => $r ) {
			$start = $r->time_start - $_SERVER['REQUEST_TIME_FLOAT'];
			$elapsed = $r->time_end - $r->time_start;
			$total_time += $elapsed;

			$class = '';
			$code = '';
			if ( is_wp_error( $r->result['r'] ) || $r->result['r']['response']['code'] >= 400 ) {
				$class = 'err';
				$total_errors++;
				if ( is_wp_error( $r->result['r'] ) ) {
					$code = $r->result['r']->get_error_code();
				} else {
					$code = $r->result['r']['response']['code'];
				}
			}

			$out .= "<tr class='$class'>";
			$out .= "<td><a onclick='pdbhttp_toggle(\"#pdbhttp_$i\")'>Toggle</a></td>";
			$out .= "<td>" . number_format( sprintf( '%0.1f', $start * 1000 ), 1 ) . " ms</td>";
			$out .= "<td>" . number_format( sprintf( '%0.1f', $elapsed * 1000 ), 1 ) . " ms</td>";
			$out .= "<td>{$r->request['args']['method']}</td>";
			$out .= "<td>{$r->request['url']}</td>";
			$out .= "<td>{$code}</td>";
			$out .= "</tr>\n";
			$out .= "<tr id='pdbhttp_$i' style='display: none'><td colspan=5><pre>" . esc_html( print_r( $r, 1 ) ) . "</pre></td></tr>\n";
		}
		$out .= "</tbody>\n</table>\n";

		$heading = '';
		$heading .= '<h2><span>HTTP Requests:</span>' . number_format( count( $this->requests ) ) . "</h2>\n";
		$heading .= '<h2><span>Total Elapsed:</span>' . number_format( sprintf( '%0.1f', $total_time * 1000 ), 1 ) . " ms</h2>\n";
		$heading .= '<h2><span>Errors:</span>' . number_format( $total_errors ) . "</h2>\n";

		if ( isset( $_GET['fullbody'] ) ) {
			$heading .= '<p style="clear:left">Request and response bodies are included. <a href="' . esc_attr( remove_query_arg( 'fullbody' ) ) . '">Reload with those omitted.</a>';
		} else {
			$heading .= '<p style="clear:left">Request and response bodies are omitted. <a href="' . esc_attr( add_query_arg( 'fullbody', 'please' ) ) . '">Reload with those included.</a>';
		}

		$out = $heading . $out;

		echo $out;
	}
}
