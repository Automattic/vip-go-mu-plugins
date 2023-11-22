<?php
/**
 * PHP error output for HTTP headers.
 *
 * @package query-monitor
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class QM_Output_Headers_Limited_PHP_Errors extends QM_Output_Headers {

	/**
	 * Collector instance.
	 *
	 * @var QM_Collector_PHP_Errors Collector.
	 */
	protected $collector;

	/**
	 * @return array<string, mixed>
	 */
	public function get_output() {
		/** @var QM_Data_PHP_Errors $data */
		$data    = $this->collector->get_data();
		$headers = array();

		if ( empty( $data->errors ) ) {
			return array();
		}

		$count = 0;

		foreach ( $data->errors as $type => $errors ) {

			foreach ( $errors as $error_key => $error ) {

				// phpcs:ignore Universal.Operators.DisallowStandalonePostIncrementDecrement.PostIncrementFound
				$count++;

				$stack = array();

				if ( ! empty( $error['filtered_trace'] ) ) {
					$stack = array_column( $error['filtered_trace'], 'display' );
				}

				$output_error = array(
					'key'       => $error_key,
					'type'      => $error['type'],
					'message'   => $error['message'],
					'file'      => QM_Util::standard_dir( $error['file'], '' ),
					'line'      => $error['line'],
					'stack'     => $stack,
					'component' => $error['component']->name,
				);

				$key             = sprintf( 'error-%d', $count );
				$headers[ $key ] = json_encode( $output_error ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode

			}
		}

		// VIP: hack to avoid failed requests due to headers being too large
		// these are subject to change at any time
		// All of the values are padded to make sure failsafe is triggered earlier
		$max_header_length       = 10 * 1024;  // padded
		$max_total_header_length = 30 * 1024;  // padded
		$max_total_headers       = 50;        // padded

		$current_headers_length = strlen( join( "\n", headers_list() ) );
		$current_headers_count  = count( headers_list() );

		// Any single header too long, truncate it
		foreach ( $headers as $key => $value ) {
			if ( strlen( $value ) + strlen( $key ) > $max_header_length ) {
				$headers[ $key ] = substr( $value, 0, $max_header_length );
			}
		}

		// Too many headers, slice the array
		if ( $current_headers_count + $count > $max_total_headers ) {
			$headers = array_slice( $headers, 0, $max_total_headers - $current_headers_count );
		}

		// Wholesale remove QM error headers if we're still over the limit
		$max_qm_headers_length = $max_total_header_length - $current_headers_length;
		if ( strlen( join( "\n", $headers ) ) > $max_qm_headers_length ) {
			$headers = [ 'errors-truncated' => 'Too many errors, check your Application performance monitor for details.' ];
		}
		// End VIP Hack

		return array_merge(
			array(
				'error-count' => $count,
			),
			$headers
		);
	}
}

/**
 * @param array<string, QM_Output> $output
 * @param QM_Collectors $collectors
 * @return array<string, QM_Output>
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed,Universal.Files.SeparateFunctionsFromOO.Mixed
 */
function vip_register_qm_output_headers_php_errors( array $output, QM_Collectors $collectors ) {
	$collector = QM_Collectors::get( 'php_errors' );
	if ( $collector ) {
		$output['php_errors'] = new QM_Output_Headers_Limited_PHP_Errors( $collector );
	}
	return $output;
}

add_filter( 'qm/outputter/headers', 'vip_register_qm_output_headers_php_errors', 111, 2 );
