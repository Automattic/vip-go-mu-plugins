<?php

class O2O_Connection_Factory {

	protected $connections;

	public function __construct() {
		$this->connections = array();
	}

	/**
	 * Registers a new O2O_Connection
	 * @param string $name
	 * @param array $from_object_types Object types that can be connected from
	 * @param array $to_object_types Object types that can be connected to
	 * @param array $args
	 * @return O2O_Connection_Taxonomy 
	 */
	public function register( $name, $from_object_types, $to_object_types, $args = array( ) ) {

		if ( !$connection = self::get_connection( $name ) ) {

			$args = wp_parse_args( $args, array(
				'reciprocal' => false,
				) );

			if ( !class_exists( 'O2O_Connection_Taxonomy' ) )
				require_once(__DIR__ . '/connection-types/taxonomy/taxonomy.php');

			$connection = new O2O_Connection_Taxonomy( $name, (array) $from_object_types, (array) $to_object_types, $args );
			$connection->init();
			$this->connections[$name] = $connection;
		}
		return $connection;
	}

	public function add( $connection ) {
		$this->connections[$connection->get_name()] = $connection;
	}

	public function get_connections() {
		return $this->connections;
	}

	/**
	 * Returns the connection for the named instance
	 * @param string $name
	 * @return iO2O_Connection
	 */
	public function get_connection( $name ) {
		if ( isset( $this->connections[$name] ) ) {
			return $this->connections[$name];
		}
		return false;
	}

}

interface iO2O_Connection {

	public function get_name();
	
	public function get_args();

	/**
	 * @return array of connected to post types 
	 */
	public function to();

	/**
	 * @return array of connected from post types 
	 */
	public function from();

	/**
	 * Relates the given object to the connected_to objects
	 * @param int $object_id ID of the object being connected from
	 * @param array $connected_to_ids
	 * @param bool $append whether to append to the current connected is or overwrite
	 */
	public function set_connected_to( $from_object_id, $connected_ids, $append );

	/**
	 * Returns the IDs which are connected to the given object
	 * @param int $object_id ID of the object from which connections are set
	 * @return array|WP_Error 
	 */
	public function get_connected_to_objects( $from_object_id );

	/**
	 *
	 * Returns the IDs which are connected from the given object
	 * @param type $object_id 
	 * @return array of connected from object ids
	 */
	public function get_connected_from_objects( $to_object_id );
	
	/**
	 * Returns whether the connection is user sortable for the given direction
	 * @param string $direction
	 * @return boolean 
	 */
	public function is_sortable($direction = 'from');
	
	/**
	 * Returns whether the connection is hierarchical for the given direction
	 * @param string $direction
	 * @return boolean
	 */
	public function is_hierarchical($direction = 'from');
	
	/**
	 * Returns the class name of the query_modifier for this type of connection
	 * 
	 * @todo move this to a factory
	 * 
	 * @return O2O_Query_Modifier 
	 */
	public function get_query_modifier();
}

abstract class aO2O_Connection implements iO2O_Connection {

	protected $name;
	protected $from_object_types;
	protected $to_object_types;
	protected $args;

	public function __construct( $name, $from_object_types, $to_object_types, $args = array( ) ) {
		$this->name = $name;
		$this->from_object_types = (array) $from_object_types;
		$this->to_object_types = (array) $to_object_types;
		
		$defaults = array(
			'rewrite' => null,
			'to' => array(
				'title' => '',
				'sortable' => false,
				'limit' => -1,
				'labels' => array()
			),
			'from' => array(
				'title' => false,
				'sortable' => false,
				'limit' => -1,
				'labels' => array(),
			),
			'metabox' => array(
				'orderby' => '',
				'order' => '',
				'context' => 'side'
			)
		);
		
		$args = wp_parse_args($args, $defaults);
		
		$args['to'] = wp_parse_args($args['to'], $defaults['to']);
		$args['from'] = wp_parse_args($args['from'], $defaults['from']);
		$args['metabox'] = wp_parse_args($args['metabox'], $defaults['metabox']);
		
		$this->args = $args;
	}

	public function get_name() {
		return $this->name;
	}
	
	public function get_args() {
		return $this->args;
	}

	/**
	 * @return array of connected to post types 
	 */
	public function to() {
		return ( array ) $this->to_object_types;
	}

	/**
	 * @return array of connected to post types 
	 */
	public function from() {
		return ( array ) $this->from_object_types;
	}
	
	/**
	 * Returns whether the connection is user sortable for the given direction
	 * @param string $direction
	 * @return boolean 
	 */
	public function is_sortable( $direction = 'from' ) {
		return (bool) $this->args[$direction]['sortable'];
	}
	
	/**
	 * Returns whether the connection is hierarchical for the given direction
	 * @param string $direction
	 * @return boolean
	 */
	public function is_hierarchical( $direction = 'from' ) {
		return false;
	}
	
	/**
	 * Returns the class name of the query_modifier for this type of connection
	 * 
	 * @todo move this to a factory
	 * 
	 * @return O2O_Query_Modifier 
	 */
	public function get_query_modifier() {
		return 'O2O_Query_Modifier';
	}

}