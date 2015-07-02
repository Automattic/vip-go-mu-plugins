<?php

if ( !class_exists( 'O2O_Query_Modifier_Taxonomy' ) ) {
	require_once ( __DIR__ . '/query_modifier.php' );
}

class O2O_Connection_Taxonomy extends aO2O_Connection implements iO2O_Connection {

	private $taxonomy;
	private $previously_cleaned_term_cache = false;
	
	public function get_taxonomy() {
		return $this->taxonomy;
	}

	/**
	 * Returns whether the connection is hierarchical for the given direction
	 * @param string $direction
	 * @return boolean
	 */
	public function is_hierarchical( $direction = 'from' ) {
		if ( $direction == 'to' && count( $this->to_object_types ) === 1 && get_post_type_object( reset( $this->to_object_types ) )->hierarchical ) {
			return true;
		}
		return false;
	}

	public function __construct( $name, $from_object_types, $to_object_types, $args = array( ) ) {
		parent::__construct( $name, $from_object_types, $to_object_types, $args );

		$this->args['from']['sortable'] = false;

		if ( ( $this->args['to']['limit'] <= -1 || $this->args['to']['limit'] > 50 ) && $this->is_sortable( 'to' ) ) {
			//force a limit if the 'to' direction is sortable since we'll have to pull in all 
			//results and reorder to implement paging for user sorts
			$this->args['to']['limit'] = 50;
		}

		$this->taxonomy = 'o2o_' . $name;

		register_taxonomy( $this->taxonomy, $from_object_types, array(
			'rewrite' => false,
			'public' => false,
			'sort' => $this->is_sortable( 'to' ),
			'hierarchical' => $this->is_hierarchical( 'to' )
		) );
	}

	public function init() {
		if ( $this->is_hierarchical( 'to' ) ) {
			add_action( 'post_updated', array( $this, '_on_post_updated' ), 10, 3 );
		}
	}

	public function deinit() {
		if ( $this->is_hierarchical( 'to' ) ) {
			remove_action( 'post_updated', array( $this, '_on_post_updated' ), 10 );
		}
	}

	/**
	 * Relates the given object to the connected_to objects
	 * @param int $object_id ID of the object being connected from
	 * @param array $connected_to_ids
	 * @param bool $append whether to append to the current connected is or overwrite
	 */
	public function set_connected_to( $from_object_id, $connected_to_ids = array( ), $append = false ) {
		global $wpdb;

		if ( !in_array( get_post_type( $from_object_id ), $this->from_object_types ) ) {
			return new WP_Error( 'invalid_post_type', 'The given post type is not valid for this connection type.' );
		}

		$term_ids = array_map( array( $this, 'get_object_termID' ), $connected_to_ids );
		$term_ids = array_filter( $term_ids, function( $term_id ) {
				return ( bool ) $term_id;
			} );

		$current_term_ids = $this->get_connected_terms( $from_object_id );
		if ( $append && $this->is_sortable( 'to' ) ) {
			$term_ids = array_unique( array_merge( $current_term_ids, $term_ids ), SORT_NUMERIC );
			$append = false; //core's append doesn't handle sort
		}

		$result = wp_set_object_terms( $from_object_id, $term_ids, $this->taxonomy, $append );

		wp_cache_delete( $from_object_id, $this->taxonomy . '_relationships_ordered' );
		if ( has_action( 'o2o_set_connected_to' ) ) {
			$original_object_ids = array_map( array( $this, 'get_object_for_term' ), $current_term_ids );
			do_action( 'o2o_set_connected_to', $from_object_id, $connected_to_ids, $this->name, $append, $original_object_ids );
		}
		return is_wp_error( $result ) ? $result : true;
	}

	/**
	 * Returns the IDs which are connected to the given object
	 * @param int $object_id ID of the object from which connections are set
	 * @return array|WP_Error 
	 */
	public function get_connected_to_objects( $from_object_id ) {
		if ( !in_array( get_post_type( $from_object_id ), $this->from_object_types ) ) {
			return new WP_Error( 'invalid_post_type', 'The given post type is not valid for this connection type.' );
		}

		$term_ids = $this->get_connected_terms( $from_object_id );
		$to_object_ids = array_map( array( $this, 'get_object_for_term' ), $term_ids );
		return $to_object_ids;
	}

	/**
	 *
	 * Returns the IDs which are connected from the given object
	 * @param type $object_id 
	 * @return array of connected from object ids
	 * 
	 * @todo add caching
	 */
	public function get_connected_from_objects( $to_object_id ) {
		if ( !in_array( get_post_type( $to_object_id ), $this->to_object_types ) ) {
			return new WP_Error( 'invalid_post_type', 'The given post type is not valid for this connection type.' );
		}

		$term_id = $this->get_object_termID( $to_object_id, false );
		if ( $term_id ) {
			$connected_from_objects = get_objects_in_term( $term_id, $this->taxonomy );
		} else {
			$connected_from_objects = array( );
		}
		return $connected_from_objects;
	}

	/**
	 * Returns the class name of the query_modifier for this type of connection
	 * 
	 * @todo move this to a factory
	 * 
	 * @return O2O_Query_Modifier 
	 */
	public function get_query_modifier() {
		return 'O2O_Query_Modifier_Taxonomy';
	}

	/**
	 * Returns the term_ids for the connected terms to an object
	 * @param intt $object_id
	 * @return array
	 */
	protected function get_connected_terms( $object_id ) {
		$terms = wp_cache_get( $object_id, $this->taxonomy . '_relationships_ordered' );

		if ( false === $terms ) {
			$terms = array_map( 'intval', wp_get_object_terms( $object_id, $this->taxonomy, array( 'orderby' => 'term_order', 'fields' => 'ids' ) ) );
			wp_cache_set( $object_id, $terms, $this->taxonomy . '_relationships_ordered' );
		}

		if ( empty( $terms ) )
			return array( );

		return $terms;
	}

	/**
	 * Returns the term_id for the given from object_id.  A term is shared across all taxonomies
	 * for a single object.
	 * @param int $object_id
	 * @param bool $create Whether to create the term if it doesn't exist
	 * @return int| false if the object isn't a valid object type
	 */
	public function get_object_termID( $object_id, $create = true ) {
		if ( !in_array( get_post_type( $object_id ), $this->to_object_types ) ) {
			return false;
		}
		$term_id = intval( get_post_meta( $object_id, 'o2o_term_id', true ) );
		$term_exists = false;
		if ( $term_id ) {
			$term_exists = wp_cache_get( 'o2o_term_exists_' . $this->taxonomy . '_' . $term_id );
			if ( !$term_exists ) {
				if ( $term_exists = term_exists( $term_id, $this->taxonomy ) ) {
					wp_cache_set( 'o2o_term_exists_' . $this->taxonomy . '_' . $term_id, true );
				}
			}
		}
		if ( !($term_id && $term_exists) && $create ) {
			$term_id = $this->create_term_for_object( $object_id );
		}
		return $term_id;
	}

	/**
	 * Creates the representing term for the object.  A direct insert is used since WP Core
	 * doesn't support inserting terms except for a specific taxonomy.
	 * @global DB $wpdb
	 * @param int $object_id
	 * @return int|WP_Error 
	 */
	protected function create_term_for_object( $object_id ) {
		global $wpdb;

		$name = $slug = 'o2o-post-' . $object_id;
		$object = get_post( $object_id );
		if ( !($term = term_exists( $name, $this->taxonomy )) ) {
			$args = array(
				'slug' => $slug
			);
			$post_type = get_post_type( $object_id );
			if ( $this->is_hierarchical( 'to' ) && in_array( $post_type, $this->to_object_types ) && $object->post_parent ) {
				$args['parent'] = $this->get_object_termID( $object->post_parent );
			}

			$term = wp_insert_term( $slug, $this->taxonomy, $args );
			if ( is_wp_error( $term ) ) {
				return $term;
			}
			if(isset($args['parent']) && $args['parent'] ) {
				//hack to be sure that hierarchy is correct since WP has a bug in clean_term_cache
				//preventing more than one term from being added to the hierarchy in a single request
				if($this->previously_cleaned_term_cache) {
					delete_option("{$this->taxonomy}_children");
				}
				$this->previously_cleaned_term_cache = true;
			}
		}
		$term_id = ( int ) $term['term_id'];

		wp_cache_set( 'o2o_term_exists_' . $this->taxonomy . '_' . $term_id, true );

		add_post_meta( $object_id, 'o2o_term_id', $term_id, true );
		wp_cache_set( 'o2o_object_' . $term_id, $object_id );

		return $term_id;
	}

	/**
	 * Retrieves the object_id for the passed in o2o term_id
	 * @param int $term_id The term should be for an o2o term only
	 * @return int|bool the object_id of the matching term, or false if no object exists 
	 */
	protected static function get_object_for_term( $term_id ) {
		$cache_key = 'o2o_object_' . $term_id;

		if ( !($object_id = wp_cache_get( $cache_key )) ) {
			$posts = get_posts( array( 'meta_query' => array( array( 'key' => 'o2o_term_id', 'value' => $term_id ) ), 'post_type' => get_post_types(), 'post_status' => 'any' ) );
			if ( count( $posts ) === 1 ) {
				$object_id = $posts[0]->ID;
				wp_cache_set( $cache_key, $object_id );
			} else {
				//A real o2o term having anything but 1 object should never happen
				return false;
			}
		}
		return $object_id;
	}

	public function _on_post_updated( $post_id, $post_after, $post_before ) {
		if($post_after->post_parent !== $post_before->post_parent) {
			if( in_array( $post_after->post_type, $this->to_object_types )) {
				$term_id = $this->get_object_termID($post_id);
				$term = get_term($term_id, $this->taxonomy, ARRAY_A);
				if($post_after->parent_post) {
					$parent_term_id = $this->get_object_termID($post_after->parent_post);
				} else {
					$parent_term_id = 0;
				}
				$term['parent'] = $parent_term_id;
				wp_update_term($term_id, $this->taxonomy, $term);
			}
		}
	}

}
