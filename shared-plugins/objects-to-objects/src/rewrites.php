<?php

/**
 * Rewrite handler for O2O 
 */
class O2O_Rewrites {

	protected $connection_factory;

	public function __construct( $connection_factory ) {
		$this->connection_factory = $connection_factory;
	}

	/**
	 * Initialization method.  Adds any needed hooks to activate rewrite rules 
	 */
	public function init() {
		add_filter( 'query_vars', array( $this, 'filter_query_vars' ) );
		add_action( 'delete_option_rewrite_rules', array( $this, 'add_rewrite_rules' ), 11 );
	}

	public function deinit() {
		remove_filter( 'query_vars', array( $this, 'filter_query_vars' ) );
		remove_action( 'delete_option_rewrite_rules', array( $this, 'add_rewrite_rules' ), 11 );
	}

	/**
	 * Filters the query_vars filter to add rewrite rule based variables
	 * @param array $query_vars
	 * @return array 
	 */
	public function filter_query_vars( $query_vars ) {
		return array_merge( $query_vars, array( 'connection_name', 'connected_name', 'connection_dir' ) );
	}

	/**
	 * Generates the rewrite rules and adds them through the rewrite API based
	 * off of the connection arguments
	 * @throws Exception 
	 */
	public function add_rewrite_rules() {
		global $wp_rewrite;

		foreach ( $this->connection_factory->get_connections() as $connection ) {
			$args = $connection->get_args();
			if ( !empty( $args['rewrite'] ) ) {
				$base_direction = $args['rewrite'] == 'to' ? 'from' : 'to';
				$attached_direction = $base_direction == 'to' ? 'from' : 'to';
				$connection_name = $connection->get_name();
				foreach ( $connection->$base_direction() as $base_post_type ) {

					//get the connected to post's permastructure
					if ( $base_post_type === 'post' ) { //stupid posts, always break the rules
						$base_post_type_root = trailingslashit( $wp_rewrite->permalink_structure );
						if ( 0 === strpos( $base_post_type_root, '/' ) )
							$base_post_type_root = substr( $base_post_type_root, 1 );
					} elseif ( $base_post_type === 'page' ) {
						$base_post_type_root = $wp_rewrite->get_page_permastruct();
					} else {
						$base_post_type_obj = get_post_type_object( $base_post_type );
						$base_post_type_root = $wp_rewrite->get_extra_permastruct( $base_post_type_obj->query_var );
					}
					$base_post_type_root = trailingslashit( str_replace( $wp_rewrite->rewritecode, $wp_rewrite->rewritereplace, $base_post_type_root ) );

					//create the connected from post's permastructure
					if ( count( $connection->$attached_direction() ) === 1 ) {
						$attached_types = $connection->$attached_direction();
						$connected_post_type = $attached_types[0];
						if ( $connected_post_type === 'post' ) { //stupid posts, always break the rules
							$connected_post_type_root = trailingslashit( $wp_rewrite->front );
						} elseif ( $connected_post_type === 'page' ) {
							if ( WP_DEBUG ) {
								trigger_error( "Rewrites where pages are the child connection are not supported." );
							}
							continue;
						} else {
							$connected_post_type_root = $wp_rewrite->get_extra_permastruct( $connected_post_type );
							$connected_post_type_root = trailingslashit( substr( $connected_post_type_root, 0, strpos( $connected_post_type_root, '%' ) ) );
						}
						if ( 0 === strpos( $connected_post_type_root, '/' ) ) {
							$connected_post_type_root = substr( $connected_post_type_root, 1 );
						}
					} else {
						if ( WP_DEBUG ) {
							trigger_error( "Rewrites to multiple post type connections not yet implemented" );
						}
						continue;
					}

					//now add the new rules
					$connected_post_type_obj = get_post_type_object( $connected_post_type );

					if ( !isset( $connected_post_type_obj->rewrite['feeds'] ) || $connected_post_type_obj->rewrite['feeds'] === true ) {
						add_rewrite_rule( $base_post_type_root . $connected_post_type_root . 'feed/(feed|rdf|rss|rss2|atom)/?$', $wp_rewrite->index . '?connection_name=' . $connection_name . '&connected_name=$matches[1]&feed=$matches[2]&connection_dir=' . $attached_direction, 'top' );
						add_rewrite_rule( $base_post_type_root . $connected_post_type_root . '(feed|rdf|rss|rss2|atom)/?$', $wp_rewrite->index . '?connection_name=' . $connection_name . '&connected_name=$matches[1]&feed=$matches[2]&connection_dir=' . $attached_direction, 'top' );
					}

					if ( !isset( $connected_post_type_obj->rewrite['pages'] ) || $connected_post_type_obj->rewrite['pages'] === true ) {
						add_rewrite_rule( $base_post_type_root . $connected_post_type_root . 'page/?([0-9]{1,})/?$', $wp_rewrite->index . '?connection_name=' . $connection_name . '&connected_name=$matches[1]&paged=$matches[2]&connection_dir=' . $attached_direction, 'top' );
					}
					add_rewrite_rule( $base_post_type_root . $connected_post_type_root . '?$', $wp_rewrite->index . '?connection_name=' . $connection_name . '&connected_name=$matches[1]&connection_dir=' . $attached_direction, 'top' );
				}
			}
		}
	}

}