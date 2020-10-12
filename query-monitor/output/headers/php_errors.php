<?php
/**
 * PHP error output for HTTP headers.
 *
 * @package query-monitor
 */
class QM_Output_Headers_PHP_Errors extends QM_Output_Headers {

	/**
	 * Collector instance.
	 *
	 * @var QM_Collector_PHP_Errors Collector.
	 */
	protected $collector;

	public function get_output() {

		$data    = $this->collector->get_data();
		$headers = array();

		if ( empty( $data['errors'] ) ) {
			return array();
		}

		$count = 0;

		foreach ( $data['errors'] as $type => $errors ) {

			foreach ( $errors as $error_key => $error ) {

				$count++;

				# @TODO we should calculate the component during process() so we don't need to do it
				# separately in each output.
				if ( $error['trace'] ) {
					$component = $error['trace']->get_component()->name;
					$stack     = $error['trace']->get_stack();
				} else {
					$component = __( 'Unknown', 'query-monitor' );
					$stack     = array();
				}

				$output_error = array(
					'key'       => $error_key,
					'type'      => $error['type'],
					'message'   => $error['message'],
					'file'      => QM_Util::standard_dir( $error['file'], '' ),
					'line'      => $error['line'],
					'stack'     => $stack,
					'component' => $component,
				);

				$key             = sprintf( 'error-%d', $count );
				$headers[ $key ] = json_encode( $output_error );

			}
		}

		return array_merge(
			array(
				'error-count' => $count,
			),
			$headers
		);
	}

}

function register_qm_output_headers_php_errors( array $output, QM_Collectors $collectors ) {
	$collector = QM_Collectors::get( 'php_errors' );
	if ( $collector ) {
		$output['php_errors'] = new QM_Output_Headers_PHP_Errors( $collector );
	}
	return $output;
}

add_filter( 'qm/outputter/headers', 'register_qm_output_headers_php_errors', 110, 2 );
