<?php

/**
 * Utility class for mapping WP Post objects to valid SDF
 */
class Lift_Posts_To_SDF {

	public static $document_id_prefix;

	/**
	 * Mapping of fields on a WP post object and their SDF processing callbacks
	 *
	 * @var array $post_fields
	 */
	private static $post_fields = array(
		'ID' => 'intval',
		'post_content' => array( __CLASS__, '__strip_tags_shortcodes' ),
		'post_date_gmt' => 'strtotime',
		'post_excerpt' => array( __CLASS__, '__strip_tags_shortcodes' ),
		'post_author' => 'intval',
		'post_category' => array( __CLASS__, '__format_post_categories' ),
		'tags_input' => array( __CLASS__, '__format_post_tags' )
	);

	/**
	 * Strip all shortcodes and HTML tags
	 *
	 * @param $content
	 * @return string
	 */
	public static function __strip_tags_shortcodes( $content ) {
		return strip_tags( strip_shortcodes( $content ) );
	}

	/**
	 * Returns category IDs as ints
	 *
	 * @param $cats - "post_categories" Post object property
	 * @return array
	 */
	public static function __format_post_categories( $cats ) {
		return array_map( 'intval', array_values( $cats ) );
	}

	/**
	 * Returns tag IDs as ints
	 *
	 * @param $tags - "tags_input" Post object property
	 * @return array
	 */
	public static function __format_post_tags( $tags ) {
		// @TODO: performance
		return array_map( function( $tag ) {
					$term = get_term_by( 'name', $tag, 'post_tag' );
					return ( int ) $term->term_id;
				}, $tags );
	}

	/**
	 * Get the ID prefix for AWS documents
	 *
	 * @return string
	 */
	public static function get_document_id_prefix() {

		// generate prefix if we haven't already
		if ( empty( self::$document_id_prefix ) ) {

			if ( is_multisite() ) {
				$site = get_current_site();
				self::$document_id_prefix = $site->id;
			} else {
				self::$document_id_prefix = 1;
			}

			self::$document_id_prefix .= '_' . get_current_blog_id() . '_';

			self::$document_id_prefix = apply_filters( 'lift_document_id_prefix', self::$document_id_prefix );
		}

		return self::$document_id_prefix;
	}

	/**
	 * Helper method to turn a WP_Query return into an initial Batch Load
	 *
	 * @param array $args WP_Query args
	 * @return array formatted posts
	 */
	public static function get_posts_for_batch_add( $args = array( ) ) {

		$posts = get_posts( $args );

		foreach ( $posts as $i => $post ) {
			$posts[$i] = self::format_post( $post->ID, array( 'action' => 'add', 'time' => time() ) );
		}

		return $posts;
	}

	/**
	 * WP Post -> SDF array
	 *
	 * @param $post
	 * @param $data - document properties (action, time)
	 * @return array
	 */
	public static function format_post( $post, $data ) {
		$valid_types = array( 'add', 'delete' );

		// Only valid actions
		if ( !in_array( $data['action'], $valid_types ) ) {
			return false;
		}

		// Any non-negative number
		if ( $data['time'] == ( int ) $data['time'] && $data['time'] < 0 ) {
			return false;
		}

		// $post must be post ID or object
		if ( !is_numeric( $post ) && !is_object( $post ) ) {
			return false;
		}

		$fields = array( );
		$_post = is_numeric( $post ) ? get_post( $post, ARRAY_A ) : ( array ) $post;

		if ( 'add' === $data['action'] ) {

			$field_names = apply_filters( 'lift_document_fields', array_keys( $_post ), $_post['ID'] );

			foreach ( $field_names as $field ) {
				$value = isset( $_post[$field] ) ? $_post[$field] : null;

				if ( isset( self::$post_fields[$field] ) ) {
					$value = call_user_func( self::$post_fields[$field], $value );
				}

				$value = apply_filters( 'lift_document_field_' . $field, $value, $_post['ID'] );

				if ( is_string( $value ) ) {

					$value = trim( str_replace( array( "\n", "\r", "\f" ), ' ', $value ) );

					$value = preg_replace( '/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $value );
				}

				$fields[$field] = $value;
			}

			$fields = array_change_key_case( $fields );

			$fields = apply_filters( 'lift_document_fields_result', $fields, $_post['ID'] );
		}

		$document = array(
			'type' => $data['action'],
			'id' => self::get_document_id_prefix() . $_post['ID'],
			'version' => $data['time'],
		);

		if ( $data['action'] == 'add' ) {
			$document['lang'] = 'en';
			$document['fields'] = $fields;
		}

		$document = apply_filters( 'lift_document', $document, $_post['ID'], $data['action'] );

		if(isset($document['fields'])) {
			$document['fields'] = ( object ) array_filter( $document['fields'], function( $value ) {
						return !is_null( $value );
					} );
		}

		return $document;
	}

}