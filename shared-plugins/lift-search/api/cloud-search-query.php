<?php

/*

  Example usage:

  $query = new Cloud_Search_Query('post_content:"ratchet"');

  $query->add_facet('post_category');
  $query->add_return_field('id');
  $query->add_rank('post_date_gmt', 'DESC');

  $query_string = $query->get_query_string();

 */

class Cloud_Search_Query {

	protected $facets = array( );
	protected $facet_constraints = array( );
	protected $facet_top_n = array( );
	protected $return_fields = array( );
	protected $size = 10;
	protected $start = 0;
	protected $boolean_query = '';
	protected $ranks = array( );

	public function __construct( $boolean_query = '' ) {
		$this->boolean_query = $boolean_query;
	}

	public function set_boolean_query( $boolean_query ) {
		$this->boolean_query = $boolean_query;
	}

	public function add_facet( $field ) {
		$this->facets = array_merge( $this->facets, ( array ) $field );
	}

	public function add_facet_contraint( $field, $constraints ) {
		$this->facet_constraints[$field] = ( array ) $constraints;
	}

	public function add_facet_top_n( $field, $limit ) {
		$this->facet_top_n[$field] = $limit;
	}

	public function add_return_field( $field ) { // string or array
		$this->return_fields = array_merge( $this->return_fields, ( array ) $field );
	}

	private function __validate_size( $size ) {
		if ( ( int ) $size != $size || ( int ) $size < 0 ) {
			throw new CloudSearchAPIException( 'Size must be a positive integer.', 2 );
		}
	}

	public function set_size( $size = 10 ) {
		$this->__validate_size( $size );
		$this->size = $size;
	}

	private function __validate_start( $start ) {
		if ( ( int ) $start != $start || ( int ) $start < 0 ) {
			throw new CloudSearchAPIException( 'Start must be a positive integer', 1 );
		}
	}

	public function set_start( $start ) {
		$this->__validate_start( $start );
		$this->start = $start;
	}

	public function add_rank( $field, $order ) {
		$order = ('DESC' === strtoupper( $order )) ? 'DESC' : 'ASC';
		$this->ranks[$field] = $order;
	}

	public function get_query_string() {
		$ranks = array( );

		foreach ( $this->ranks as $field => $order ) {
			$ranks[] = ('DESC' === $order) ? "-{$field}" : $field;
		}

		$params = array_filter( array(
			'bq' => $this->boolean_query,
			'facet' => implode( ',', $this->facets ),
			'return-fields' => implode( ',', $this->return_fields ),
			'size' => $this->size,
			'start' => $this->start,
			'rank' => implode( ',', $ranks )
			) );

		if ( count( $this->facet_constraints ) ) {
			foreach ( $this->facet_constraints as $field => $constraints ) {
				$params['facet-' . $field . '-constraints'] = implode( ',', $constraints );
			}
		}

		if ( count( $this->facet_top_n ) ) {
			foreach ( $this->facet_top_n as $field => $limit ) {
				$params['facet-' . $field . '-top-n'] = $limit;
			}
		}
		return http_build_query( $params );
	}

}

class CloudSearchAPIException extends Exception {
	
}