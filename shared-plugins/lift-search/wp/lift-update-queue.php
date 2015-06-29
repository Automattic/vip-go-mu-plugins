<?php

function lift_queue_field_update( $document_id, $field_name, $document_type = 'post' ) {
	return Lift_Document_Update_Queue::queue_field_update( $document_id, $field_name, $document_type );
}

function lift_queue_deletion( $document_id, $document_type = 'post' ) {
	return Lift_Document_Update_Queue::queue_deletion( $document_id, $document_type );
}

class Lift_Document_Update_Queue {

	private static $document_update_docs = array( );

	const STORAGE_POST_TYPE = 'lift_queued_document';
	const QUEUE_IDS_OPTION = 'lift_queue_ids';

	/**
	 * Gives the post_id representing the closed/completed queue.
	 * The closed queue is the one that is currently no longer accepting
	 * new changes and is waiting or in the process of being sent in batches
	 *
	 * @return int 
	 */
	public static function get_closed_queue_id() {
		return self::get_queue_id( 'closed' );
	}

	/**
	 * Gives the post_id representing the current active queue that all
	 * new changes are saved to.  
	 * 
	 * @return int 
	 */
	public static function get_active_queue_id() {
		return self::get_queue_id( 'active' );
	}

	/**
	 * Closes the active queue and creates a new active queue to start
	 * storing new updates
	 * 
	 * @return type 
	 */
	public static function close_active_queue() {
		$lock_name = 'lift_close_active_queue';
		
		$lock_key = md5( uniqid( microtime() . mt_rand(), true ) );
		if ( !get_transient( $lock_name ) ) {
			set_transient( $lock_name, $lock_key, 60 );
		}

		if ( get_transient( $lock_name ) !== $lock_key ) {
			//another server/request has this lock
			return;
		}
		
		
		$active_queue_id = self::get_active_queue_id();
		$closed_queue_id = self::get_closed_queue_id();
		
		update_option( self::QUEUE_IDS_OPTION, array(
			'active' => $closed_queue_id,
			'closed' => $active_queue_id
		));
		
		delete_transient($lock_name);
	}

	private static function get_queue_id( $type ) {
		$queue_ids = get_option( self::QUEUE_IDS_OPTION, array( ) );

		$queue_id = isset( $queue_ids[$type] ) ? $queue_ids[$type] : false;

		if ( !$queue_id || !get_post( $queue_id ) ) {
			//queue post doesn't yet exist, create one
			$queue_id = wp_insert_post( array(
				'post_type' => self::STORAGE_POST_TYPE,
				'post_status' => 'publish',
				'post_title' => 'lift queue post'
				) );

			$queue_ids[$type] = $queue_id;

			update_option( self::QUEUE_IDS_OPTION, $queue_ids );
		}

		return $queue_id;
	}

	/**
	 * Sets a document field to be queued for an update
	 * @param int $document_id
	 * @param string $field_name
	 * @param string $document_type
	 * @return bool 
	 */
	public static function queue_field_update( $document_id, $field_name, $document_type = 'post' ) {
		$doc_update = self::get_queued_document_updates( $document_id, $document_type );
		return $doc_update->add_field( $field_name );
	}

	/**
	 * Queus the document for deletion
	 * @param int $document_id
	 * @param string $document_type
	 * @return bool 
	 */
	public static function queue_deletion( $document_id, $document_type = 'post' ) {
		$doc_update = self::get_queued_document_updates( $document_id, $document_type );
		return $doc_update->set_for_deletion();
	}

	/**
	 * Gets the instance of the LiftUpdateDocument for the given document from the active
	 * batch.  A new instance is created if there isn't yet one.
	 * @param int $document_id
	 * @param string $document_type
	 * @return Lift_Update_Document 
	 */
	public static function get_queued_document_updates( $document_id, $document_type = 'post' ) {
		$key = 'lift_update_' . $document_type . '_' . $document_id;
		if ( isset( self::$document_update_docs[$key] ) ) {
			return self::$document_update_docs[$key];
		}

		if ( is_array( $update_data = get_post_meta( self::get_active_queue_id(), $key ) ) ) {
			$action = isset( $update_data['action'] ) ? $update_data['action'] : 'add';
			$fields = isset( $update_data['fields'] ) ? ( array ) $update_data['fields'] : array( );
			$document_update_doc = new Lift_Update_Document( $document_id, $document_type, $action, $fields );
		} else {
			$document_update_doc = new Lift_Update_Document( $document_id, $document_type );
		}

		self::$document_update_docs[$key] = $document_update_doc;
		return $document_update_doc;
	}

	/**
	 * Initializes needed post type for storage 
	 */
	public static function init() {
		register_post_type( self::STORAGE_POST_TYPE, array(
			'labels' => array(
				'name' => 'Lift Queue',
				'singular_name' => 'Queued Docs'
			),
			'publicly_queryable' => false,
			'public' => false,
			'rewrite' => false,
			'has_archive' => false,
			'query_var' => false,
			'taxonomies' => array( ),
			'show_ui' => defined( 'LIFT_QUEUE_DEBUG' ),
			'can_export' => false,
			'show_in_nav_menus' => false,
			'show_in_menu' => defined( 'LIFT_QUEUE_DEBUG' ),
			'show_in_admin_bar' => false,
			'delete_with_user' => false,
		) );

		add_action( 'shutdown', array( __CLASS__, '_save_updates' ) );

		Lift_Post_Update_Watcher::init();
		Lift_Post_Meta_Update_Watcher::init();
		Lift_Taxonomy_Update_Watcher::init();
	}

	public static function query_updates( $args = array( ) ) {
		global $wpdb;

		$defaults = array(
			'page' => 1,
			'per_page' => 10,
			'queue_ids' => self::get_active_queue_id(),
		);

		extract( $args = wp_parse_args( $args, $defaults ) );

		$page = is_int( $page ) ? max( $page, 1 ) : 1;
		$per_page = intval( $per_page );

		$queue_ids = array_map( 'intval', ( array ) $queue_ids );


		$limit = 'LIMIT ' . ($page - 1) * $per_page . ', ' . $per_page;

		$query = "SELECT SQL_CALC_FOUND_ROWS meta_id, meta_key, post_id FROM $wpdb->postmeta " .
			"WHERE post_id in (" . implode( ',', $queue_ids ) . ") AND meta_key like 'lift_update_%' " .
			"ORDER BY meta_id " .
			$limit;

		$meta_rows = $wpdb->get_results( $query );
		$found_rows = $wpdb->get_var( "SELECT FOUND_ROWS()" );

		return ( object ) array(
				'query_args' => $args,
				'meta_rows' => $meta_rows,
				'found_rows' => $found_rows,
				'num_pages' => ceil( $found_rows / $per_page )
		);
	}

	/**
	 * Callback on shutdown to save any updated documents 
	 */
	public static function _save_updates() {
		foreach ( self::$document_update_docs as $change_doc ) {
			if ( !$change_doc->has_changed ) {
				continue;
			}

			$key = 'lift_update_' . $change_doc->document_type . '_' . $change_doc->document_id;

			$update_data = array(
				'document_id' => $change_doc->document_id,
				'document_type' => $change_doc->document_type,
				'action' => $change_doc->action,
				'fields' => $change_doc->fields,
				'update_date_gmt' => current_time( 'mysql', 1 ),
				'update_date' => current_time( 'mysql' )
			);
			update_post_meta( self::get_active_queue_id(), $key, $update_data );
		}
	}

	public static function _deactivation_cleanup() {
		global $wpdb;

		$batch_post_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts
			WHERE post_type = %s", self::STORAGE_POST_TYPE ) );
		foreach ( $batch_post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}
	}

}

add_action( 'init', array( 'Lift_Document_Update_Queue', 'init' ), 2 );

class Lift_Update_Document {

	public $action;
	public $fields;
	public $document_id;
	public $document_type;
	public $has_changed;

	public function __construct( $document_id, $document_type, $action = 'add', $fields = array( ), $has_changed = false ) {
		$this->document_id = $document_id;
		$this->document_type = $document_type;
		$this->action = $action;
		$this->fields = $fields;
		$this->has_changed = $has_changed;
	}

	public function add_field( $field_name ) {
		if ( $this->action == 'delete' ) {
			return false;
		}

		if ( !in_array( $field_name, $this->fields ) ) {
			$this->fields[] = $field_name;
			$this->has_changed = true;
		}
		return true;
	}

	public function set_for_deletion() {
		if ( $this->action !== 'delete' ) {
			$this->has_changed = true;
			$this->fields = array( );
			$this->action = 'delete';
		}
		return true;
	}

}
