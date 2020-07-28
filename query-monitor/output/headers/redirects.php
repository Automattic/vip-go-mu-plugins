<?php
/**
 * HTTP redirects output for HTTP headers.
 *
 * @package query-monitor
 */
class QM_Output_Headers_Redirects extends QM_Output_Headers {

	public function get_output() {

		$data = $this->collector->get_data();
		$headers = array();

		if ( empty( $data['trace'] ) ) {
			return array();
		}

		$headers['Redirect-Trace'] = implode( ', ', $data['trace']->get_stack() );
		return $headers;

	}

}

function register_qm_output_headers_redirects( array $output, QM_Collectors $collectors ) {
	if ( $collector = QM_Collectors::get( 'redirects' ) ) {
		$output['redirects'] = new QM_Output_Headers_Redirects( $collector );
	}
	return $output;
}

add_filter( 'qm/outputter/headers', 'register_qm_output_headers_redirects', 140, 2 );
