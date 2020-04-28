<?php

/**
 * Show information about Elasticsearch queries run during the request
 *
 * ES queries should be saved into the global `$wp_elasticsearch_queries_log`, with
 * a format like:
 *
 * $logged = array(
 *     'args'          => $args, // Args passed to ES, json decoded
 *     'response'      => $response, // ES results, json decoded
 *     'response_code' => $response_code, // HTTP response code from ES
 *     'elapsed_time'  => $elapsed_time, // Total roundtrip time for the request, including network
 *     'es_time'       => $took, // How long ES took to run the request
 *     'url'           => $service_url, // url to the ES server
 *     'backtrace'     => wp_debug_backtrace_summary(), // Optional backtrace
 * );
 */
class Debug_Bar_Elasticsearch extends Debug_Bar_Panel {
	function init() {
		$this->title( __( 'Elasticsearch', 'debug-bar' ) );
	}

	function prerender() {
		global $wp_elasticsearch_queries_log;

		$this->set_visible( defined( 'SAVEQUERIES' ) && SAVEQUERIES && ! empty( $wp_elasticsearch_queries_log ) );
	}

	function render() {
		global $wp_elasticsearch_queries_log;

		$out = '';

		$total_time = 0;
		$total_engine_time = 0;

		// ES query deets
		if ( ! empty( $wp_elasticsearch_queries_log ) ) {
			$out .= '<ol class="wpd-queries">';

			foreach ( $wp_elasticsearch_queries_log as $q ) {
				$total_time        += $q['elapsed_time'];
				$total_engine_time += $q['es_time'];

				$out .= "<li>";
				$out .= '<h3>Request:</h3>';
				$out .= '<textarea readonly rows=20 cols=100>' . esc_textarea( json_encode( $q['args'], JSON_PRETTY_PRINT ) ) . '</textarea>';

				$out .= '<h3>Response:</h3>';
				$out .= 'ES time: ' . number_format( sprintf( '%0.1f', $q['es_time'] ), 1 ) . 'ms<br />';
				$out .= 'Roundtrip time: ' . number_format( sprintf( '%0.1f', $q['elapsed_time'] ), 1 ) . 'ms<br />';

				$out .= '<textarea readonly rows=20 cols=100>' . esc_textarea( json_encode( $q['response'], JSON_PRETTY_PRINT ) ) . '</textarea>';

				if ( $q['backtrace'] ) {
					$out .= '<h3>Backtrace:</h3>';

					$out .= '<span class="debug-note">' . esc_html( $q['backtrace'] ) . '</span><br /><br />';
				}

				$out .= '<h3>Curl:</h3>';

				$curl_cmd = sprintf( 'curl -X POST "%s" -d \'%s\'',
					$q['url'],
					json_encode( $q['args'] )
				);

				$out .= '<span class="debug-note">' . esc_html( $curl_cmd ) . '</span>';

				$out .= "</li>";
			}

			$out .= '</ol>';

			// ES query summary
			$summary = '<h2><span>Total Queries:</span>' . number_format( count( $wp_elasticsearch_queries_log ) ) . "</h2>\n";
			$summary .= '<h2><span>Total query time:</span>' . number_format( sprintf( '%0.1f', $total_time ), 1 ) . " ms</h2>\n";
			$summary .= '<h2><span>Total ES engine time:</span>' . number_format( sprintf( '%0.1f', $total_engine_time ), 1 ) . " ms</h2>\n";

			$out = $summary . $out;
		} else {
			if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES )
				$out .= "<p><strong>" . __( 'There are no queries on this page.', 'debug-bar' ) . "</strong></p>";
			else
				$out .= "<p><strong>" . __( 'SAVEQUERIES must be defined to show the query log.', 'debug-bar' ) . "</strong></p>";
		}

		echo $out;
	}
}
