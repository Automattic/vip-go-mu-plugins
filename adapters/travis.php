<?php

/**
 * A generic ES implementation for Travis CI
 */

class ES_WP_Query extends ES_WP_Query_Wrapper {
	protected function query_es( $es_args ) {
		$response = wp_remote_post( 'http://localhost:9200/es-wp-query-unit-tests/post/_search', array( 'body' => json_encode( $es_args ) ) );
		return json_decode( wp_remote_retrieve_body( $response ), true );
	}
}

function travis_es_field_map( $es_map ) {
	return wp_parse_args( array(
		'post_meta'         => 'post_meta.%s.value',
		'post_author'       => 'post_author.user_id',
		'post_date'         => 'post_date.date',
		'post_date_gmt'     => 'post_date_gmt.date',
		'post_modified'     => 'post_modified.date',
		'post_modified_gmt' => 'post_modified_gmt.date',
	), $es_map );
}
add_filter( 'es_field_map', 'travis_es_field_map' );

if ( defined( 'ES_WP_QUERY_TEST_ENV' ) && ES_WP_QUERY_TEST_ENV ) {

	function es_wp_query_index_test_data() {
		// Make sure ES is running and responding
		$tries = 5;
		$sleep = 3;
		do {
			$response = wp_remote_get( 'http://localhost:9200/' );
			if ( '200' == wp_remote_retrieve_response_code( $response ) ) {
				// Looks good!
				break;
			} else {
				printf( "\nInvalid response from ES (%s), sleeping %d seconds and trying again...\n", wp_remote_retrieve_response_code( $response ), $sleep );
				sleep( $sleep );
			}
		} while ( --$tries );

		// If we didn't end with a 200 status code, exit
		travis_es_verify_response_code( $response );

		// Ensure the index is empty
		wp_remote_request( 'http://localhost:9200/es-wp-query-unit-tests/', array( 'method' => 'DELETE' ) );

		// Add the mapping
		$response = wp_remote_request( 'http://localhost:9200/es-wp-query-unit-tests/', array( 'method' => 'PUT', 'body' => '
			{
				"settings": {
					"analysis": {
						"analyzer": {
							"default": {
								"tokenizer": "standard",
								"filter": [
									"standard",
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
					"post": {
						"_all" : { "enabled" : false },
						"date_detection": false,
						"dynamic_templates": [
							{
								"template_meta": {
									"path_match": "post_meta.*",
									"mapping": {
										"type": "object",
										"properties": {
											"value": {
												"type": "string",
												"index": "not_analyzed"
											},
											"analyzed": {
												"type": "string"
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
												"format": "YYYY-MM-dd",
												"type": "date"
											},
											"datetime": {
												"format": "YYYY-MM-dd HH:mm:ss",
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
											"name": { "type": "string", "index": "not_analyzed" },
											"term_id": { "type": "long" },
											"slug": { "type": "string", "index": "not_analyzed" }
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
									"user_nicename": { "type": "string", "index": "not_analyzed" }
								}
							},
							"post_title": {
								"type": "string",
								"index": "not_analyzed",
								"fields": {
									"analyzed": { "type": "string" }
								}
							},
							"post_excerpt": { "type": "string" },
							"post_content": {
								"type": "string",
								"index": "not_analyzed",
								"fields": {
									"analyzed": { "type": "string" }
								}
							},
							"post_status": { "type": "string", "index": "not_analyzed" },
							"post_name": { "type": "string", "index": "not_analyzed" },
							"post_parent": { "type": "long" },
							"post_type": { "type": "string", "index": "not_analyzed" },
							"post_mime_type": { "type": "string", "index": "not_analyzed" },
							"post_password": { "type": "string", "index": "not_analyzed" },
							"post_date": {
								"type": "object",
								"properties": {
									"date": { "type": "date", "format": "YYYY-MM-dd HH:mm:ss" },
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
									"date": { "type": "date", "format": "YYYY-MM-dd HH:mm:ss" },
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
									"date": { "type": "date", "format": "YYYY-MM-dd HH:mm:ss" },
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
									"date": { "type": "date", "format": "YYYY-MM-dd HH:mm:ss" },
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
					}
				}
			}
		' ) );
		travis_es_verify_response_code( $response );

		// Index the content
		$posts = get_posts( array(
			'posts_per_page' => -1,
			'post_type' => 'any',
			'post_status' => array_values( get_post_stati() ),
			'orderby' => 'ID',
			'order' => 'ASC',
		) );

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
			'http://localhost:9200/es-wp-query-unit-tests/post/_bulk',
			array(
				'method' => 'PUT',
				'body' => wp_check_invalid_utf8( implode( "\n", $body ), true ) . "\n"
			)
		);
		travis_es_verify_response_code( $response );

		$itemized_response = json_decode( wp_remote_retrieve_body( $response ) );
		foreach ( (array) $itemized_response->items as $post ) {
			// Status should be 200 or 201, depending on if we're updating or creating respectively
			if ( ! isset( $post->index->status ) || ! in_array( $post->index->status, array( 200, 201 ) ) ) {
				echo "Error indexing post {$post->index->_id}; HTTP response code: {$post->index->status}";
				if ( ! empty( $post->index->error ) ) {
					echo "\n" . $post->index->error;
				}
				exit( 1 );
			}
		}

		$resposne = wp_remote_post( 'http://localhost:9200/es-wp-query-unit-tests/_refresh' );
		travis_es_verify_response_code( $response );
	}

	function travis_es_verify_response_code( $response ) {
		if ( '200' != wp_remote_retrieve_response_code( $response ) ) {
			printf( "Could not index posts!\nResponse code %s\n", wp_remote_retrieve_response_code( $response ) );
			if ( is_wp_error( $response ) ) {
				printf( "Message: %s\n", $response->get_error_message() );
			}
			exit( 1 );
		}
	}

	/**
	* Taken from SearchPress
	*/
	class Travis_ES_Post {
		# This stores what will eventually become our JSON
		public $data = array();

		protected static $users = array();

		function __construct( $post ) {
			if ( is_numeric( $post ) && 0 != intval( $post ) )
				$post = get_post( intval( $post ) );
			if ( ! is_object( $post ) )
				return;

			$this->fill( $post );
		}

		/**
		 * Populate this object with all of the post's properties
		 *
		 * @param object $post
		 * @return void
		 */
		public function fill( $post ) {
			$this->data = array(
				'post_id'           => $post->ID,
				'post_author'       => $this->get_user( $post->post_author ),
				'post_title'        => $post->post_title,
				'post_excerpt'      => $post->post_excerpt,
				'post_content'      => $post->post_content,
				'post_status'       => $post->post_status,
				'post_name'         => $post->post_name,
				'post_parent'       => $post->post_parent,
				'post_type'         => $post->post_type,
				'post_mime_type'    => $post->post_mime_type,
				'post_password'     => $post->post_password,
				'terms'             => $this->get_terms( $post ),
				'post_meta'         => $this->get_meta( $post->ID ),
			);
			foreach ( array( 'post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt' ) as $field ) {
				if ( $value = $this->get_date( $post->$field, $field ) ) {
					$this->data[ $field ] = $value;
				}
			}
		}

		/**
		 * Get post meta for a given post ID.
		 * Some post meta is removed (you can filter it), and serialized data gets unserialized
		 *
		 * @param int $post_id
		 * @return array 'meta_key' => array( value 1, value 2... )
		 */
		public function get_meta( $post_id ) {
			$meta = (array) get_post_meta( $post_id );

			# Remove a filtered set of meta that we don't want indexed
			$ignored_meta = array(
				'_edit_lock',
				'_edit_last',
				'_wp_old_slug',
				'_wp_trash_meta_time',
				'_wp_trash_meta_status',
				'_previous_revision',
				'_wpas_done_all',
				'_encloseme'
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

			// correct boolean values
			if ( ( "false" === $value ) || ( "FALSE" === $value ) ) {
				$return['boolean'] = false;
			} elseif ( ( 'true' === $value ) || ( 'TRUE' === $value ) ) {
				$return['boolean'] = true;
			}

			// add date/time if we have it.
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
		 * @param object $post
		 * @return array
		 */
		public function get_terms( $post ) {
			$object_terms = array();
			$taxonomies = get_object_taxonomies( $post->post_type );
			foreach ( $taxonomies as $taxonomy ) {
				$these_terms = get_the_terms( $post->ID, $taxonomy );
				if ( $these_terms && ! is_wp_error( $these_terms ) ) {
					$object_terms = array_merge( $object_terms, $these_terms );
				}
			}

			if ( empty( $object_terms ) ) {
				return;
			}

			$terms = array();
			foreach ( (array) $object_terms as $term ) {
				$terms[ $term->taxonomy ][] = array(
					'term_id' => $term->term_id,
					'slug'    => $term->slug,
					'name'    => $term->name,
				);
			}

			return $terms;
		}


		/**
		 * Parse out the properties of a date.
		 *
		 * @param  string $date  A date, expected to be in mysql format.
		 * @param  string $field The field for which we're pulling this information.
		 * @return array The parsed date.
		 */
		public function get_date( $date, $field ) {
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
		 * @param int $user_id
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
			return json_encode( $this->data );
		}
	}
}
