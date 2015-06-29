<?php

interface iLiftField {

	/**
	 * The name of this field as stored in the AWS Domain
	 * @return string
	 */
	public function getName();

	/**
	 * The type of field.
	 * @return string
	 */
	public function getType();

	/**
	 * Sets options for the index field. The IndexFieldType indicates which of
	 * the options will be present. It is invalid to specify options for a type
	 * other than the IndexFieldType.
	 *
	 * @param string $name The name of the option
	 * @param mixed $value
	 * @return iLiftField
	 */
	public function addTypeOption( $name, $value );

	/**
	 * Adds one or more request variables to the public query vars accepted during
	 * a HTTP request.
	 * @param array $request_vars
	 * @return iLiftField
	 */
	public function addPublicRequestVars( $request_vars = array( ) );

	/**
	 * Returns the value to insert in this field for the specified document
	 * @param int $post_id
	 * @return mixed The value that should be set in the document for this field
	 */
	public function getDocumentValue( $post_id );

	/**
	 * Returns the tanslated request variables as key/value array for the given
	 * AWS bolean query value for this field.  Default behavior is to return single
	 * item array with $this->name as the key and the value as the bq as the value.
	 *
	 * @todo Implement full BQ parsing for fallback
	 *
	 * @param string $bq_value
	 * @return array
	 */
	public function bqToRequest( $bq );

	/**
	 * Converts WP Query vars to a label that can be used for controls
	 * @param array $query_vars
	 * @return string the label
	 */
	public function wpToLabel( $query_vars );

	/**
	 * Converts request variables to WP_Query variables.  Variables used by this
	 * field should be sanitized here.
	 * @param array $request_vars
	 * @return array
	 */
	public function requestToWP( $request_vars );
}

abstract class aLiftField implements iLiftField {

	/**
	 *
	 * @var string
	 */
	protected $name;

	/**
	 * The name of this field as stored in the AWS Domain
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * The type of field.
	 * @var string one of 'uint', 'literal', 'text'
	 */
	protected $type;

	/**
	 * Returns the type of this field
	 * @return string
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 *
	 * @var array
	 */
	protected $type_options;

	/**
	 *
	 * @var array
	 */
	protected $request_vars;

	/**
	 *
	 * @var array
	 */
	protected $options;

	/**
	 * Constructor.
	 * @param string $name
	 * @param string $type one of 'uint', 'literal', 'text'
	 * @param array $options Options
	 */
	public function __construct( $name, $type, $options = array( ) ) {
		$this->options = wp_parse_args( $options, array(
			'_built_in' => false
			) );
		$this->name = $name;
		$this->type = $type;
		$this->type_options = array( );
		$this->request_vars = array( );

		//setup actions
		add_action( 'wp_loaded', array( $this, '_registerSearchHooks' ) );
		if ( !$this->options['_built_in'] ) {
			$this->_registerSchemaHooks();
		}
	}

	/**
	 * Sets options for the index field. The IndexFieldType indicates which of
	 * the options will be present. It is invalid to specify options for a type
	 * other than the IndexFieldType.
	 *
	 * @param string $name The name of the option
	 * @param mixed $value
	 * @return iLiftField
	 */
	public function addTypeOption( $name, $value ) {
		$this->type_options[$name] = $value;
		return $this;
	}

	/**
	 * Adds one or more request variables to the public query vars accepted during
	 * a HTTP request.
	 * @param array $request_vars
	 * @return iLiftField
	 */
	public function addPublicRequestVars( $request_vars = array( ) ) {
		$this->request_vars = array_merge( $this->request_vars, ( array ) $request_vars );
		return $this;
	}

	/**
	 * Registers the needed hooks to add this field to the AWS schema and
	 * document submission
	 *
	 * @return iLiftField
	 */
	protected function _registerSchemaHooks() {
		add_filter( 'lift_domain_schema', array( $this, '_appendSchema' ) );
		add_filter( 'lift_post_changes_to_data', array( $this, '_appendFieldToDocument' ), 10, 3 );
		return $this;
	}

	/**
	 * Callback during 'wp_loaded' used to apply any filters that should be applied
	 * after initial construction that.
	 *
	 * @access protected
	 */
	public function _registerSearchHooks() {
		if ( !is_admin() ) {
			if ( count( $this->request_vars ) ) {
				add_filter( 'query_vars', array( $this, '_appendRequestVars' ) );
			}
			add_filter( 'request', array( $this, 'requestToWP' ) );
		}
		add_filter( 'list_search_bq_parameters', array( $this, '_filterCSBooleanQuery' ), 10, 2 );
	}

	/**
	 * Callback to 'lift_domain_schema' to append this field to the schema.
	 * @access protected
	 *
	 * @param array $schema
	 * @return array
	 */
	public function _appendSchema( $schema ) {
		$field = array(
			'field_name' => $this->name,
			'field_type' => $this->type
		);

		if ( count( $this->type_options ) ) {
			$map = array( 'uint' => 'UIntOptions', 'literal' => 'LiteralOptions', 'text' => 'TextOptions' );
			$field[$map[$this->type]] = $this->type_options;
		}

		$schema[] = $field;
		return $schema;
	}

	/**
	 * Callback for 'query_vars' to append any extra needed request variables.
	 * @access protected
	 *
	 * @param array $query_vars
	 * @return array
	 */
	public function _appendRequestVars( $query_vars ) {
		if ( count( $this->request_vars ) )
			$query_vars = array_merge( $query_vars, $this->request_vars );
		return $query_vars;
	}

	/**
	 * Filter callback for 'list_search_bq_parameters' to append new parameters to
	 * the AWS query.
	 * @access protected
	 *
	 * @param array $bq
	 * @param Lift_WP_Query $lift_query
	 * @return array
	 */
	public function _filterCSBooleanQuery( $bq, $lift_query ) {
		$bq[] = $this->wpToBooleanQuery( $lift_query->wp_query->query_vars );
		return $bq;
	}

	/**
	 * Callback to 'lift_post_changes_to_data' to append this field to the document
	 * as it's sent to the domain.
	 * @access protected
	 *
	 * @param array $post_data
	 * @param array $changed_fields Names of
	 * @param int $post_id
	 * @return array
	 */
	public function _appendFieldToDocument( $post_data, $changed_fields, $post_id ) {
		$post_data[$this->name] = $this->getDocumentValue( $post_id );
		return $post_data;
	}

	/**
	 * Returns a boolean query param based on the current WP_Query
	 * @param array $query_vars
	 * @return string The resulting boolean query parameter
	 */
	abstract public function wpToBooleanQuery( $query_vars );

	/**
	 * Returns the tanslated request variables as key/value array for the given
	 * AWS bolean query value for this field.  Default behavior is to return single
	 * item array with $this->name as the key and the value as the bq as the value.
	 *
	 * @param string $bq_value
	 * @return array
	 */
	public function bqToRequest( $bq ) {
		if ( $bq ) {
			$bq_value = explode( ':', $bq );
			return array( $this->name => $bq_value[1] );
		}
		return array( );
	}

	/**
	 * Converts request variables to WP_Query variables.  Variables used by this
	 * field should be sanitized here.
	 * @param array $request_vars
	 * @return array
	 */
	public function requestToWP( $request_vars ) {
		return $request_vars;
	}

}

/**
 * Wrapper to Create a Field for a Taxonomy
 */
class LiftTaxonomyField extends aLiftField {

	protected $taxonomy;

	/**
	 * Constructor.
	 * @param string $taxonomy
	 * @param array $options Options
	 */
	public function __construct( $taxonomy, $options = array( ) ) {
		$this->taxonomy = $taxonomy;
		parent::__construct( "taxonomy_{$taxonomy}_id", 'literal', $options );
		$this->addTypeOption( 'facet', 'true' );

		$this->addPublicRequestVars( $this->name );
		add_action( 'lift_filter_items_' . $this->getName(), array( $this, '_lift_filter_items' ) );
		if ( empty($this->options['_built_in'] ) ) {
			add_filter( 'lift_watched_taxonomies', array( $this, 'filter_watched_taxonomies' ) );
		}
	}

	public function filter_watched_taxonomies( $taxonomies ) {
		return array_merge( $taxonomies, array( $this->taxonomy ) );
	}

	public function getDocumentValue( $post_id ) {
		$terms = get_the_terms( $post_id, $this->taxonomy );
		$value = array( );
		if ( is_array( $terms ) && !empty( $terms ) ) {
			foreach ( $terms as $term ) {
				$value[] = ( string ) $term->term_id;
			}
		}
		return $value;
	}

	/**
	 * Converts request variables to WP_Query variables.  Variables used by this
	 * field should be sanitized here.
	 * @param array $request_vars
	 * @return array
	 */
	public function requestToWP( $request_vars ) {
		if ( isset( $request_vars[$this->name] ) ) {
			if ( !isset( $request_vars['tax_query'] ) ) {
				$request_vars['tax_query'] = array( );
			}
			$request_vars['tax_query'][] = array(
				'operator' => 'AND',
				'taxonomy' => $this->taxonomy,
				'field' => 'id',
				'terms' => $request_vars[$this->name]
			);
			unset( $request_vars[$this->name] ); //cleanup
		}
		return $request_vars;
	}

	public function wpToBooleanQuery( $query_vars ) {
		$bq = '';
		$terms = get_terms( $this->taxonomy );
		$wp_tax_query = $this->parseTaxQuery( $query_vars );
		foreach ( $wp_tax_query->queries as $tax_query ) {
			if ( $tax_query['taxonomy'] == $this->taxonomy && !empty( $tax_query['terms'] ) ) {
				$field = $tax_query['field'];
				if ( $field === 'id' ) {
					$term_ids = $tax_query['terms'];
				} else {
					$term_ids = array( );
					foreach ( $tax_query['terms'] as $term_val ) {
						foreach ( $terms as $term ) {
							if ( $term->$field == $term_val ) {
								$term_ids[] = $term->term_id;
								break;
							}
						}
					}
				}

				$term_expressions = array( );
				foreach ( $term_ids as $term_id ) {
					//note that taxonomies are stored as literal fields and literal fields are strings
					$term_expressions[] = new Lift_Expression_Field( $this->name, $term_id );
				}
				switch ( $tax_query['operator'] ) {
					case 'IN':
						$exp_set = new Lift_Expression_Set( 'or', $term_expressions );
						$bq = ( string ) $exp_set;
						break;
					case 'NOT IN':
						$exp_set = new Lift_Expression_Set( 'or', $term_expressions );
						$not_set = new Lift_Expression_Set( 'not', array( $exp_set ) );
						$bq = ( string ) $not_set;
						break;
					case 'AND':
						$exp_set = new Lift_Expression_Set( 'and', $term_expressions );
						$bq = ( string ) $exp_set;
						break;
				}
				break;
			}
		}
		return $bq;
	}

	/**
	 * @todo find a faster way to find the matching terms;
	 * @param type $query_vars
	 * @return string
	 */
	public function wpToLabel( $query_vars ) {
		$wp_tax_query = $this->parseTaxQuery( $query_vars );

		foreach ( $wp_tax_query->queries as $tax_query ) {
			if ( $tax_query['taxonomy'] == $this->taxonomy && !empty( $tax_query['terms'] ) ) {
				$field = $tax_query['field'];
				if ( $field != 'id' ) {
					$terms = get_terms( $this->taxonomy );
				}

				if ( $field === 'name' ) {
					$term_names = $tax_query['terms'];
				} else {
					$term_names = array( );
					foreach ( $tax_query['terms'] as $term_val ) {
						if ( $field === 'id' ) {
							$term = get_term( $term_val, $this->taxonomy );
							$term_names[] = $term->name;
						} else {
							foreach ( $terms as $term ) {
								if ( $term->$field == $term_val ) {
									$term_names[] = $term->name;
									break;
								}
							}
						}
					}
				}
				return implode( ', ', $term_names );
			}
		}
		return 'Any';
	}

	public function bqToRequest( $bq ) {
		$query_var = get_taxonomy( $this->taxonomy )->query_var;
		$request = array( $this->name => false );
		$expression = liftBqToExpression( $bq );
		if ( $expression ) {
			$request[$this->name] = $expression->getValue();
		}
		return $request;
	}

	/**
	 * Converts query_vars into a WP_Tax_Query which has a more standardized form
	 * to work with.
	 *
	 * Technically WP_Query::parse_tax_query is marked as protected.  Hopefully
	 * a real factory function is created in core before it actually gets set as such.
	 *
	 * @param type $query_vars
	 * @return WP_Tax_Query
	 */
	protected function parseTaxQuery( $query_vars ) {
		$wp_query = new WP_Query();
		$wp_query->parse_query( $query_vars );
		return $wp_query->tax_query;
	}

	public function _lift_filter_items( $items ) {
		$tax_obj = get_taxonomy( $this->taxonomy );

		$terms = get_terms( $this->taxonomy );
		foreach ( $terms as $term ) {
			$items[] = array( $tax_obj->query_var => $term->slug );
		}
		return $items;
	}

}

/**
 * Custom Field handling for storing postmeta as text
 */
class LiftPostMetaTextField extends aLiftField {

	protected $meta_key;

	/**
	 * Constructor
	 * @param string $taxonomy
	 * @param array $options Options
	 */
	public function __construct( $name, $options = array( ) ) {

		if ( isset( $options['meta_key'] ) ) {
			$this->meta_key = $options['meta_key'];
		}
		parent::__construct( $name, 'text', $options );
		$this->addPublicRequestVars( array( $this->name ) );
	}

	/**
	 * Converts request variables to WP_Query variables.  Variables used by this
	 * field should be sanitized here.
	 * @param array $request_vars
	 * @return array
	 */
	public function requestToWP( $request_vars ) {
		if ( !empty( $request_vars[$this->name] ) ) {
			$sub_meta_query = array(
				'key' => $this->meta_key,
				'value' => $request_vars[$this->name],
				'compare' => 'LIKE'
			);
			if ( !isset( $request_vars['meta_query'] ) ) {
				$request_vars['meta_query'] = array( $sub_meta_query );
			} elseif ( is_array( $request_vars['meta_query'] ) ) {
				$request_vars['meta_query'][] = $sub_meta_query;
			} else {
				$request_vars['meta_query'] = array_merge( $request_vars['meta_query'], $sub_meta_query );
			}
			unset( $request_vars[$this->name] );
		}
		return $request_vars;
	}

	/**
	 * Returns the value to insert in this field for the specified document
	 * @param int $post_id
	 * @return mixed The value that should be set in the document for this field
	 */
	public function getDocumentValue( $post_id ) {
		$meta_value = get_post_meta( $post_id, $this->meta_key, true );
		return ( string ) $meta_value;
	}

	/**
	 * Returns a boolean query param based on the current WP_Query
	 * @param array $query_vars
	 * @return string The resulting boolean query parameter
	 */
	public function wpToBooleanQuery( $query_vars ) {
		$meta_query = new WP_Meta_Query( );
		$meta_query->parse_query_vars( $query_vars );

		if ( count( $meta_query->queries ) > 0 ) {
			$expressionSet = new Lift_Expression_Set( strtolower( $meta_query->relation ) );
			foreach ( $meta_query->queries as $subquery ) {
				if ( $subquery['key'] == $this->meta_key ) {
					if ( $subquery['compare'] === 'LIKE' ) {
						foreach ( ( array ) $subquery['value'] as $value ) {
							$expressionSet->addExpression( new Lift_Expression_Field( $this->name, $value ) );
						}
					} elseif ( $subquery['compare'] === 'NOT LIKE' ) {
						$subExpression = new Lift_Expression_Set( 'NOT' );
						foreach ( ( array ) $subquery['value'] as $value ) {
							$subExpression->addExpression( new Lift_Expression_Field( $this->name, $value ) );
						}
						$expressionSet->addExpression( $subExpression );
					}
				}
			}
			return $expressionSet;
		}

		return '';
	}

	/**
	 * Returns the tanslated request variables as key/value array for the given
	 * AWS bolean query value for this field.  Default behavior is to return single
	 * item array with $this->name as the key and bq as the value.
	 *
	 * @param string $bq_value
	 * @return array
	 */
	public function bqToRequest( $bq ) {
		$request = array( $this->name => false );
		$expression = liftBqToExpression( $bq );
		if ( $expression ) {
			$request[$this->name] = $expression->getValue();
		}
		return $request;
	}

	/**
	 * Convert WP_Query query_var value into a human readable label
	 *
	 * @param array $query_vars WP_Query query vars.
	 * @return string the label based on the given vars.
	 */
	public function wpToLabel( $query_vars ) {
		$meta_query = new WP_Meta_Query( );
		$meta_query->parse_query_vars( $query_vars );
		$label = '';
		if ( count( $meta_query->queries ) > 0 ) {
			$expressionSet = new Lift_Expression_Set( strtolower( $meta_query->relation ) );
			foreach ( $meta_query->queries as $subquery ) {
				if ( $subquery['key'] == $this->meta_key ) {
					$label = ( string ) $subquery->value;
				}
			}
		}

		return $label;
	}

}

/**
 * Wrapper to simplify creating custom fields by using delegate callbacks
 */
class LiftDelegatedField extends aLiftField {

	private $delegates = array( );

	public function delegate( $key, $handler, $args = array( ) ) {
		$this->delegates[$key] = array( 'handler' => $handler, 'args' => $args );
		return $this;
	}

	public function getDelegate( $key ) {
		return isset( $this->delegates[$key] ) ? $this->delegates[$key] : null;
	}

	private function execDelegate( $key, $arg1 = null ) {
		if ( $delegate = $this->getDelegate( $key ) ) {
			$args = array_slice( func_get_args(), 1 );
			array_push( $args, $this, $delegate['args'] );
			return call_user_func_array( $delegate['handler'], $args );
		}
		return null;
	}

	/**
	 * Returns a boolean query param based on the current WP_Query
	 * @param array $query_vars
	 * @return string The resulting boolean query parameter
	 */
	public function wpToBooleanQuery( $query_vars ) {
		return $this->execDelegate( __FUNCTION__, $query_vars );
	}

	/**
	 * Returns the value to insert in this field for the specified document
	 * @param int $post_id
	 * @return mixed The value that should be set in the document for this field
	 */
	public function getDocumentValue( $post_id ) {
		return $this->execDelegate( __FUNCTION__, $post_id );
	}

	/**
	 * Returns the tanslated request variables as key/value array for the given
	 * AWS bolean query value for this field.  Default behavior is to return single
	 * item array with $this->name as the key and the value as the bq as the value.
	 *
	 * @todo Implement full BQ parsing for fallback
	 *
	 * @param string $bq_value
	 * @return array
	 */
	public function bqToRequest( $bq ) {
		if ( $this->getDelegate( __FUNCTION__ ) ) {
			return $this->execDelegate( __FUNCTION__, $bq );
		}
		return parent::bqToRequest( $bq );
	}

	/**
	 *
	 * Converts request variables to WP_Query variables.  Variables used by this
	 * field should be sanitized here.
	 * @param array $request_vars
	 * @return array
	 */
	public function requestToWP( $query_vars ) {
		if ( $this->getDelegate( __FUNCTION__ ) ) {
			return $this->execDelegate( __FUNCTION__, $query_vars );
		}
		return parent::requestToWP( $query_vars );
	}

	/**
	 * 
	 * @param array $query_vars
	 * @return string the label
	 */
	public function wpToLabel( $query_vars ) {
		return ( string ) $this->execDelegate( 'wpToLabel', $query_vars );
	}

}

/**
 * Factory method to allow simplified chaining.
 * @param type $name
 * @param type $type
 * @param type $options
 * @return LiftField
 */
function liftDelegatedField( $name, $type, $options = array( ) ) {
	return new LiftDelegatedField( $name, $type, $options );
}

//setup default fields
add_action( 'init', function() {
		$eodTime = strtotime( date( 'Y-m-d 23:59:59' ) );

		$date_facets = array(
			array( 'date_start' => $eodTime - (30 * DAY_IN_SECONDS) ),
			array( 'date_start' => $eodTime - (60 * DAY_IN_SECONDS) ),
			array( 'date_start' => $eodTime - (90 * DAY_IN_SECONDS) )
		);

		$post_date_field = liftDelegatedField( 'post_date_gmt', 'uint', array( '_built_in' => true ) )
			->addPublicRequestVars( array( 'date_start', 'date_end' ) )
			->delegate( 'requestToWP', function($request) {
					if ( isset( $request['date_start'] ) ) {
						$request['date_start'] = intval( $request['date_start'] );
					}
					if ( isset( $request['date_end'] ) ) {
						$request['date_end'] = intval( $request['date_end'] );
					}
					return $request;
				} )
			->delegate( 'wpToBooleanQuery', function($query_vars) {
					$date_start = isset( $query_vars['date_start'] ) ? $query_vars['date_start'] : false;
					$date_end = isset( $query_vars['date_end'] ) ? $query_vars['date_end'] : false;
					$value = '';
					if ( !( $date_start || $date_end ) && !empty( $query_vars['year'] ) ) {
						$year = $query_vars['year'];

						if ( !empty( $query_vars['monthnum'] ) ) {
							$monthnum = sprintf( '%02d', $query_vars['monthnum'] );

							if ( !empty( $query_vars['day'] ) ) {
								// Padding
								$str_date_start = sprintf( '%02d', $query_vars['day'] );

								$str_date_start = $year . '-' . $date_monthnum . '-' . $date_day . ' 00:00:00';
								$str_date_end = $year . '-' . $date_monthnum . '-' . $date_day . ' 23:59:59';
							} else {
								$days_in_month = date( 't', mktime( 0, 0, 0, $monthnum, 14, $year ) ); // 14 = middle of the month so no chance of DST issues

								$str_date_start = $year . '-' . $monthnum . '-01 00:00:00';
								$str_date_end = $year . '-' . $monthnum . '-' . $days_in_month . ' 23:59:59';
							}
						} else {
							$str_date_start = $year . '-01-01 00:00:00';
							$str_date_end = $year . '-12-31 23:59:59';
						}

						$date_start = get_gmt_from_date( $str_date_start );
						$date_end = get_gmt_from_date( $str_date_end );
					}

					if ( $date_start || $date_end )
						$value = "post_date_gmt:{$date_start}..{$date_end}";

					return $value;
				} )
			->delegate( 'wpToLabel', function($query_vars) {
					$min = isset( $query_vars['date_start'] ) ? intval( $query_vars['date_start'] ) : '';
					$max = isset( $query_vars['date_end'] ) ? intval( $query_vars['date_end'] ) : '';
					if ( $min && $max ) {
						return sprintf( __( 'Between %1$s and %2$s ago', 'lift-search' ), human_time_diff( $min ), human_time_diff( $max ) );
					} elseif ( $min ) {
						return sprintf( __( 'Less than %s ago', 'lift-search' ), human_time_diff( $min ) );
					} elseif ( $max ) {
						return sprintf( __( 'More than %s ago', 'lift-search' ), human_time_diff( $max ) );
					} else {
						return "Any Time";
					}
				} )
			->delegate( 'bqToRequest', function($bq, $field, $args) {
				$query_vars = array( 'date_start' => false, 'date_end' => false );
				$bq_parts = explode( ':', $bq );
				if ( count( $bq_parts ) > 1 ) {
					if ( strpos( $bq_parts[1], '..' ) !== false ) {
						list($query_vars['date_start'], $query_vars['date_end']) = explode( '..', $bq_parts[1] );
					} else {
						$query_vars['date_start'] = $bq_parts[1];
					}
				}
				return $query_vars;
			} );

		new LiftSingleSelectFilter( $post_date_field, 'Published', $date_facets );

		$post_type_field = liftDelegatedField( 'post_type', 'literal', array( '_built_in' => true ) )
			->delegate( 'wpToBooleanQuery', function($query_vars) {
					$post_type = isset( $query_vars['post_type'] ) ? $query_vars['post_type'] : '';
					$post_type_expression = '';
					if ( 'any' == $post_type ) {
						$in_search_post_types = get_post_types( array( 'exclude_from_search' => false ) );
						if ( !empty( $in_search_post_types ) ) {
							$post_type_expression = new Lift_Expression_Set();
							foreach ( $in_search_post_types as $_post_type ) {
								$post_type_expression->addExpression( new Lift_Expression_Field( 'post_type', $_post_type ) );
							}
						}
					} elseif ( !empty( $post_type ) && is_array( $post_type ) ) {
						$post_type_expression = new Lift_Expression_Set();
						foreach ( $post_type as $_post_type ) {
							$post_type_expression->addExpression( new Lift_Expression_Field( 'post_type', $_post_type ) );
						}
					} elseif ( !empty( $post_type ) ) {
						$post_type_expression = new Lift_Expression_Field( 'post_type', $post_type );
					}
					return ( string ) $post_type_expression;
				} )
			->delegate( 'wpToLabel', function($query_vars) {
					$label = 'Any';
					if ( !empty( $query_vars['post_type'] ) ) {
						if ( is_array( $query_vars['post_type'] ) ) {
							$labels = array( );
							foreach ( $query_vars['post_type'] as $post_type ) {
								$post_obj = get_post_type_object( $post_type );
								if ( $post_obj ) {
									$labels[] = $post_obj->labels->name;
								} else {
									$labels[] = $post_type;
								}
							}
							$label = implode( ' or ', $labels );
						} else {
							$post_obj = get_post_type_object( $query_vars['post_type'] );
							if ( $query_vars['post_type'] !== 'any' ) {
								if ( $post_obj ) {
									$label = $post_obj->labels->name;
								} else {
									$label = $query_vars['post_type'];
								}
							}
						}
					}
					return $label;
				} )
			->delegate( 'bqToRequest', function($bq, $field, $args) {
				$request = array( 'post_type' => false );
				$expression = liftBqToExpression( $bq );
				if ( $expression ) {
					$post_type = $expression->getValue();
					//only set if this isn't the 'any' value.
					$foo = get_post_types( array( 'exclude_from_search' => false ) );
					$bar = array_diff( $foo, ( array ) $post_type );
					if ( count( array_diff( get_post_types( array( 'exclude_from_search' => false ) ), ( array ) $post_type ) ) ) {
						$request['post_type'] = $post_type;
					}
				}

				return $request;
			} );

		$items = array_map( function($post_type) {
				return array( 'post_type' => $post_type );
			}, get_post_types( array( 'publicly_queryable' => true ) ) );

		new LiftSingleSelectFilter( $post_type_field, 'Type', $items );


		$post_categories_field = new LiftTaxonomyField( 'category', array( '_built_in' => true ) );
		new LiftIntersectFilter( $post_categories_field, 'In Categories', array( ) );

		$post_tags_field = new LiftTaxonomyField( 'post_tag', array( '_built_in' => true ) );
		new LiftIntersectFilter( $post_tags_field, 'Tags', array( ) );
	} );

