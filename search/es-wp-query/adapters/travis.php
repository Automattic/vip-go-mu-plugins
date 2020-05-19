<?php // phpcs:disable
/**
 * ES_WP_Query adapters: Travis CI adapter
 *
 * Code in this file will only ever be run in a Travis CI context when running
 * unit tests.
 *
 * @package ES_WP_Query
 */

/**
 * A generic ES implementation for Travis CI.
 */
class ES_WP_Query extends ES_WP_Query_Wrapper {

	/**
	 * Implements the abstract function query_es from ES_WP_Query_Wrapper.
	 *
	 * @param array $es_args Arguments to pass to the Elasticsearch server.
	 * @access protected
	 * @return array The response from the Elasticsearch server.
	 */
	protected function query_es( $es_args ) {
		global $es_wp_query_travis_doc_type;

		$response = wp_remote_post(
			"http://localhost:9200/es-wp-query-unit-tests/{$es_wp_query_travis_doc_type}/_search",
			array(
				'body'    => wp_json_encode( $es_args ),
				'headers' => array(
					'Content-Type' => 'application/json',
				),
			)
		);
		return json_decode( wp_remote_retrieve_body( $response ), true );
	}
}

/**
 * A class to represent an exception that fires when indexing fails.
 */
class ES_Index_Exception extends Exception {
}

/**
 * Provides a mapping between WordPress fields and Elasticsearch DSL keys.
 *
 * @param array $es_map Additional mappings to layer on top of the default.
 * @return array Mappings to use.
 */
function travis_es_field_map( $es_map ) {
	return wp_parse_args(
		array(
			'post_meta'         => 'post_meta.%s.value',
			'post_author'       => 'post_author.user_id',
			'post_date'         => 'post_date.date',
			'post_date_gmt'     => 'post_date_gmt.date',
			'post_modified'     => 'post_modified.date',
			'post_modified_gmt' => 'post_modified_gmt.date',
		),
		$es_map
	);
}
add_filter( 'es_field_map', 'travis_es_field_map' );

if ( defined( 'ES_WP_QUERY_TEST_ENV' ) && ES_WP_QUERY_TEST_ENV ) {

	/**
	 * Verifies that the Elasticsearch server is up and accepting connections.
	 *
	 * @param int $tries The number of retries to attempt.
	 * @param int $sleep The amount of time to sleep between retries.
	 * @return bool True if the server is up, false if not.
	 * @throws ES_Index_Exception If the indexing operation fails.
	 */
	function es_wp_query_verify_es_is_running( $tries = 5, $sleep = 3 ) {
		// Make sure ES is running and responding.
		do {
			$response = wp_remote_get( 'http://localhost:9200/' );
			if ( 200 === intval( wp_remote_retrieve_response_code( $response ) ) ) {
				$body = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( ! empty( $body['version']['number'] ) ) {
					printf( "Elasticsearch is up and running, using version %s.\n", $body['version']['number'] );
					if ( ! defined( 'ES_VERSION' ) ) {
						define( 'ES_VERSION', $body['version']['number'] );
					} elseif ( ES_VERSION !== $body['version']['number'] ) {
						printf( "WARNING! ES_VERSION is set to %s, but Elasticsearch is reporting %s\n", ES_VERSION, $body['version']['number'] );
					}
					break;
				} else {
					sleep( $sleep );
				}
			} else {
				printf( "\nInvalid response from ES (%s), sleeping %d seconds and trying again...\n", wp_remote_retrieve_response_code( $response ), $sleep );
				sleep( $sleep );
			}
		} while ( --$tries );

		// If we didn't end with a 200 status code, bail.
		return travis_es_verify_response_code( $response );
	}

	/**
	 * Indexes test data.
	 *
	 * @throws ES_Index_Exception If the indexing operation fails.
	 */
	function es_wp_query_index_test_data() {
		global $es_wp_query_travis_doc_type;
		$es_wp_query_travis_doc_type = '_doc';

		// Ensure the index is empty.
		wp_remote_request( 'http://localhost:9200/es-wp-query-unit-tests/', array( 'method' => 'DELETE' ) );

		$analyzed       = 'text';
		$not_analyzed   = 'keyword';
		$doc_type_open  = '';
		$doc_type_close = '';
		if ( version_compare( ES_VERSION, '5.0.0', '<' ) ) {
			$analyzed     = 'string';
			$not_analyzed = 'string", "index": "not_analyzed';
		}
		if ( version_compare( ES_VERSION, '6.0.0', '<' ) ) {
			// ES < 6 doesn't support the doc type _doc.
			$es_wp_query_travis_doc_type = 'post';
		}
		if ( version_compare( ES_VERSION, '7.0.0', '<' ) ) {
			$doc_type_open  = sprintf( '"%s": {', $es_wp_query_travis_doc_type );
			$doc_type_close = '}';
		}

		// Add the mapping.
		$response = wp_remote_request(
			'http://localhost:9200/es-wp-query-unit-tests/',
			array(
				'method'  => 'PUT',
				'body'    => sprintf(
					'
						{
							"settings": {
								"analysis": {
									"analyzer": {
										"default": {
											"tokenizer": "standard",
											"filter": [
												"travis_word_delimiter",
												"lowercase",
												"stop",
												"travis_snowball"
											],
											"language": "English"
										}
									},
									"filter": {
										"travis_word_delimiter": {
											"type": "word_delimiter",
											"preserve_original": true
										},
										"travis_snowball": {
											"type": "snowball",
											"language": "English"
										}
									}
								}
							},
							"mappings": {
								%3$s
									"date_detection": false,
									"dynamic_templates": [
										{
											"template_meta": {
												"path_match": "post_meta.*",
												"mapping": {
													"type": "object",
													"properties": {
														"value": {
															"type": "%2$s"
														},
														"analyzed": {
															"type": "%1$s"
														},
														"boolean": {
															"type": "boolean"
														},
														"long": {
															"type": "long"
														},
														"double": {
															"type": "double"
														},
														"date": {
															"format": "yyyy-MM-dd",
															"type": "date"
														},
														"datetime": {
															"format": "yyyy-MM-dd HH:mm:ss",
															"type": "date"
														},
														"time": {
															"format": "HH:mm:ss",
															"type": "date"
														}
													}
												}
											}
										},
										{
											"template_terms": {
												"path_match": "terms.*",
												"mapping": {
													"type": "object",
													"properties": {
														"name": { "type": "%2$s" },
														"term_id": { "type": "long" },
														"term_taxonomy_id": { "type": "long" },
														"slug": { "type": "%2$s" }
													}
												}
											}
										}
									],
									"properties": {
										"post_id": { "type": "long" },
										"post_author": {
											"type": "object",
											"properties": {
												"user_id": { "type": "long" },
												"user_nicename": { "type": "%2$s" }
											}
										},
										"post_title": {
											"type": "%2$s",
											"fields": {
												"analyzed": { "type": "%1$s" }
											}
										},
										"post_excerpt": { "type": "%1$s" },
										"post_content": {
											"type": "%2$s",
											"fields": {
												"analyzed": { "type": "%1$s" }
											}
										},
										"post_status": { "type": "%2$s" },
										"post_name": { "type": "%2$s" },
										"post_parent": { "type": "long" },
										"post_type": { "type": "%2$s" },
										"post_mime_type": { "type": "%2$s" },
										"post_password": { "type": "%2$s" },
										"post_date": {
											"type": "object",
											"properties": {
												"date": { "type": "date", "format": "yyyy-MM-dd HH:mm:ss" },
												"year": { "type": "short" },
												"month": { "type": "byte" },
												"day": { "type": "byte" },
												"hour": { "type": "byte" },
												"minute": { "type": "byte" },
												"second": { "type": "byte" },
												"week": { "type": "byte" },
												"day_of_week": { "type": "byte" },
												"day_of_year": { "type": "short" },
												"seconds_from_day": { "type": "integer" },
												"seconds_from_hour": { "type": "short" }
											}
										},
										"post_date_gmt": {
											"type": "object",
											"properties": {
												"date": { "type": "date", "format": "yyyy-MM-dd HH:mm:ss" },
												"year": { "type": "short" },
												"month": { "type": "byte" },
												"day": { "type": "byte" },
												"hour": { "type": "byte" },
												"minute": { "type": "byte" },
												"second": { "type": "byte" },
												"week": { "type": "byte" },
												"day_of_week": { "type": "byte" },
												"day_of_year": { "type": "short" },
												"seconds_from_day": { "type": "integer" },
												"seconds_from_hour": { "type": "short" }
											}
										},
										"post_modified": {
											"type": "object",
											"properties": {
												"date": { "type": "date", "format": "yyyy-MM-dd HH:mm:ss" },
												"year": { "type": "short" },
												"month": { "type": "byte" },
												"day": { "type": "byte" },
												"hour": { "type": "byte" },
												"minute": { "type": "byte" },
												"second": { "type": "byte" },
												"week": { "type": "byte" },
												"day_of_week": { "type": "byte" },
												"day_of_year": { "type": "short" },
												"seconds_from_day": { "type": "integer" },
												"seconds_from_hour": { "type": "short" }
											}
										},
										"post_modified_gmt": {
											"type": "object",
											"properties": {
												"date": { "type": "date", "format": "yyyy-MM-dd HH:mm:ss" },
												"year": { "type": "short" },
												"month": { "type": "byte" },
												"day": { "type": "byte" },
												"hour": { "type": "byte" },
												"minute": { "type": "byte" },
												"second": { "type": "byte" },
												"week": { "type": "byte" },
												"day_of_week": { "type": "byte" },
												"day_of_year": { "type": "short" },
												"seconds_from_day": { "type": "integer" },
												"seconds_from_hour": { "type": "short" }
											}
										},
										"menu_order" : { "type" : "integer" },
										"terms": { "type": "object" },
										"post_meta": { "type": "object" }
									}
								%4$s
							}
						}
					',
					$analyzed,
					$not_analyzed,
					$doc_type_open,
					$doc_type_close
				),
				'headers' => array(
					'Content-Type' => 'application/json',
				),
			)
		);
		if ( true !== travis_es_verify_response_code( $response ) ) {
			exit( 1 );
		}

		// Index the content.
		$posts = get_posts(
			array(
				'posts_per_page' => -1,
				'post_type'      => array_values( get_post_types() ),
				'post_status'    => array_values( get_post_stati() ),
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		$es_posts = array();
		foreach ( $posts as $post ) {
			$es_posts[] = new Travis_ES_Post( $post );
		}

		$body = array();
		foreach ( $es_posts as $post ) {
			$body[] = '{ "index": { "_id" : ' . $post->data['post_id'] . ' } }';
			$body[] = addcslashes( $post->to_json(), "\n" );
		}

		$response = wp_remote_request(
			"http://localhost:9200/es-wp-query-unit-tests/{$es_wp_query_travis_doc_type}/_bulk",
			array(
				'method'  => 'PUT',
				'body'    => wp_check_invalid_utf8( implode( "\n", $body ), true ) . "\n",
				'headers' => array(
					'Content-Type' => 'application/json',
				),
			)
		);
		travis_es_verify_response_code( $response );

		$itemized_response = json_decode( wp_remote_retrieve_body( $response ) );
		foreach ( (array) $itemized_response->items as $post ) {
			// Status should be 200 or 201, depending on if we're updating or creating respectively.
			if ( ! isset( $post->index->status ) || ! in_array( intval( $post->index->status ), array( 200, 201 ), true ) ) {
				$error_message = "Error indexing post {$post->index->_id}; HTTP response code: {$post->index->status}";
				if ( ! empty( $post->index->error ) ) {
					if ( is_string( $post->index->error ) ) {
						$error_message .= "\n{$post->index->error}";
					} elseif ( ! empty( $post->index->error->reason ) && ! empty( $post->index->error->type ) ) {
						$error_message .= "\n{$post->index->error->type}: {$post->index->error->reason}";
					}
				}
				$error_message .= 'Backtrace:' . travis_es_debug_backtrace_summary();
				throw new ES_Index_Exception( $error_message );
			}
		}

		$response = wp_remote_post(
			'http://localhost:9200/es-wp-query-unit-tests/_refresh',
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
				),
			)
		);
		travis_es_verify_response_code( $response );
	}

	/**
	 * Verifies the Elasticsearch response code.
	 *
	 * @param WP_Error|array $response The response from wp_remote_request.
	 * @return bool
	 * @throws ES_Index_Exception If the indexing fails.
	 */
	function travis_es_verify_response_code( $response ) {
		if ( 200 !== intval( wp_remote_retrieve_response_code( $response ) ) ) {
			$message = [ 'Failed to index posts!' ];
			if ( is_wp_error( $response ) ) {
				$message[] = sprintf( 'Message: %s', $response->get_error_message() );
			} else {
				$message[] = sprintf( 'Response code %s', wp_remote_retrieve_response_code( $response ) );
				$message[] = sprintf( 'Message: %s', wp_remote_retrieve_body( $response ) );
			}
			$message[] = sprintf( 'Backtrace:%s', travis_es_debug_backtrace_summary() );
			throw new ES_Index_Exception( implode( "\n", $message ) );
		}

		return true;
	}

	/**
	 * Provides a backtrace summary for error reporting in Travis tests.
	 *
	 * @return string
	 */
	function travis_es_debug_backtrace_summary() {
		$backtrace = wp_debug_backtrace_summary( null, 0, false );
		$backtrace = array_filter(
			$backtrace,
			function( $call ) {
				return ! preg_match( '/PHPUnit_(TextUI_(Command|TestRunner)|Framework_(TestSuite|TestCase|TestResult))|ReflectionMethod|travis_es_(verify_response_code|debug_backtrace_summary)/', $call );
			}
		);
		return "\n\t" . join( "\n\t", $backtrace );
	}

	/**
	 * Taken from SearchPress.
	 */
	class Travis_ES_Post {

		/**
		 * This stores what will eventually become our JSON.
		 *
		 * @access public
		 * @var array
		 */
		public $data = array();

		/**
		 * A list of users.
		 *
		 * @access protected
		 * @var array
		 */
		protected static $users = array();

		/**
		 * Travis_ES_Post constructor.
		 *
		 * @param WP_Post $post The post object to use.
		 * @access public
		 */
		public function __construct( $post ) {
			if ( is_numeric( $post ) && 0 !== intval( $post ) ) {
				$post = get_post( intval( $post ) );
			}
			if ( ! is_object( $post ) ) {
				return;
			}

			$this->fill( $post );
		}

		/**
		 * Populate this object with all of the post's properties.
		 *
		 * @param WP_Post $post The post to use when filling post properties.
		 * @access public
		 */
		public function fill( $post ) {
			$this->data = array(
				'post_id'        => $post->ID,
				'post_author'    => $this->get_user( $post->post_author ),
				'post_title'     => $post->post_title,
				'post_excerpt'   => $post->post_excerpt,
				'post_content'   => $post->post_content,
				'post_status'    => $post->post_status,
				'post_name'      => $post->post_name,
				'post_parent'    => $post->post_parent,
				'post_type'      => $post->post_type,
				'post_mime_type' => $post->post_mime_type,
				'post_password'  => $post->post_password,
				'terms'          => $this->get_terms( $post ),
				'post_meta'      => $this->get_meta( $post->ID ),
			);
			foreach ( array( 'post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt' ) as $field ) {
				$value = $this->get_date( $post->$field );
				if ( ! empty( $value ) ) {
					$this->data[ $field ] = $value;
				}
			}
		}

		/**
		 * Get post meta for a given post ID.
		 * Some post meta is removed (you can filter it), and serialized data gets unserialized
		 *
		 * @param int $post_id The ID of the post for which to retrieve meta.
		 * @return array 'meta_key' => array( value 1, value 2... )
		 */
		public function get_meta( $post_id ) {
			$meta = (array) get_post_meta( $post_id );

			// Remove a filtered set of meta that we don't want indexed.
			$ignored_meta = array(
				'_edit_lock',
				'_edit_last',
				'_wp_old_slug',
				'_wp_trash_meta_time',
				'_wp_trash_meta_status',
				'_previous_revision',
				'_wpas_done_all',
				'_encloseme',
			);
			foreach ( $ignored_meta as $key ) {
				unset( $meta[ $key ] );
			}

			foreach ( $meta as &$values ) {
				$values = array_map( array( $this, 'cast_meta_types' ), $values );
			}

			return $meta;
		}

		/**
		 * Split the meta values into different types for meta query casting.
		 *
		 * @param  string $value Meta value.
		 * @return array
		 */
		public function cast_meta_types( $value ) {
			$return = array(
				'value'    => $value,
				'analyzed' => $value,
				'boolean'  => (bool) $value,
			);

			if ( is_numeric( $value ) ) {
				$return['long']   = intval( $value );
				$return['double'] = floatval( $value );
			}

			// Correct boolean values.
			if ( ( 'false' === $value ) || ( 'FALSE' === $value ) ) {
				$return['boolean'] = false;
			} elseif ( ( 'true' === $value ) || ( 'TRUE' === $value ) ) {
				$return['boolean'] = true;
			}

			// Add date/time if we have it.
			$time = strtotime( $value );
			if ( false !== $time ) {
				$return['date']     = date( 'Y-m-d', $time );
				$return['datetime'] = date( 'Y-m-d H:i:s', $time );
				$return['time']     = date( 'H:i:s', $time );
			}

			return $return;
		}

		/**
		 * Get all terms across all taxonomies for a given post
		 *
		 * @param WP_Post $post The post to process.
		 * @access public
		 * @return array
		 */
		public function get_terms( $post ) {
			$object_terms = array();
			$taxonomies   = get_object_taxonomies( $post->post_type );
			foreach ( $taxonomies as $taxonomy ) {
				$these_terms = get_the_terms( $post->ID, $taxonomy );
				if ( $these_terms && ! is_wp_error( $these_terms ) ) {
					$object_terms = array_merge( $object_terms, $these_terms );
				}
			}

			if ( empty( $object_terms ) ) {
				return array();
			}

			$terms = array();
			foreach ( (array) $object_terms as $term ) {
				$terms[ $term->taxonomy ][] = array(
					'term_id'          => $term->term_id,
					'term_taxonomy_id' => $term->term_taxonomy_id,
					'slug'             => $term->slug,
					'name'             => $term->name,
				);
			}

			return $terms;
		}


		/**
		 * Parse out the properties of a date.
		 *
		 * @param  string $date  A date, expected to be in mysql format.
		 * @return array|false The parsed date on success, false on failure.
		 */
		public function get_date( $date ) {
			$ts = strtotime( $date );
			if ( $ts <= 0 ) {
				return false;
			}

			return array(
				'date'              => $date,
				'year'              => date( 'Y', $ts ),
				'month'             => date( 'm', $ts ),
				'day'               => date( 'd', $ts ),
				'hour'              => date( 'H', $ts ),
				'minute'            => date( 'i', $ts ),
				'second'            => date( 's', $ts ),
				'week'              => date( 'W', $ts ),
				'day_of_week'       => date( 'N', $ts ),
				'day_of_year'       => date( 'z', $ts ),
				'seconds_from_day'  => mktime( date( 'H', $ts ), date( 'i', $ts ), date( 's', $ts ), 1, 1, 1970 ),
				'seconds_from_hour' => mktime( 0, date( 'i', $ts ), date( 's', $ts ), 1, 1, 1970 ),
			);
		}


		/**
		 * Get information about a post author
		 *
		 * @param int $user_id The user ID to look up.
		 * @access public
		 * @return array
		 */
		public function get_user( $user_id ) {
			if ( empty( self::$users[ $user_id ] ) ) {
				$user = get_userdata( $user_id );
				$data = array( 'user_id' => absint( $user_id ) );
				if ( $user instanceof WP_User ) {
					$data['user_nicename'] = strval( $user->user_nicename );
				} else {
					$data['user_nicename'] = '';
				}
				self::$users[ $user_id ] = $data;
			}

			return self::$users[ $user_id ];
		}


		/**
		 * Return this object as JSON
		 *
		 * @return string
		 */
		public function to_json() {
			return wp_json_encode( $this->data );
		}
	}
}
