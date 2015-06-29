<?php

class O2O_Admin {

	protected $connection_factory;

	public function __construct( $connection_factory ) {
		$this->connection_factory = $connection_factory;
	}

	public function init() {
		if ( ! class_exists( 'Post_Selection_UI' ) ) {
			@include_once(__DIR__ . '/post-selection-ui/post-selection-ui.php');
		}
		Post_Selection_UI::init();
		add_action( 'add_meta_boxes', array( $this, '__action_add_meta_box' ), 10, 2 );
		add_action( 'save_post', array( $this, '__action_save_post' ) );
	}

	public function __action_add_meta_box( $post_type, $post ) {
		foreach ( $this->connection_factory->get_connections() as $connection ) {
			$connection_args = $connection->get_args();
			if ( in_array( $post_type, $connection->from() ) ) {
				add_meta_box( $connection->get_name(), isset( $connection_args['to']['labels']['name'] ) ? $connection_args['to']['labels']['name'] : 'Items', array( $this, 'meta_box' ), $post_type, $connection_args['metabox']['context'], 'low', array( 'connection' => $connection->get_name(), 'direction' => 'to' ) );
			} elseif ( $connection_args['reciprocal'] && in_array( $post_type, $connection->to() ) ) {
				add_meta_box( $connection->get_name(), isset( $connection_args['from']['labels']['name'] ) ? $connection_args['from']['labels']['name'] : 'Items', array( $this, 'meta_box' ), $post_type, $connection_args['metabox']['context'], 'low', array( 'connection' => $connection->get_name(), 'direction' => 'from' ) );
			}
		}
	}

	public function meta_box( $post, $metabox ) {

		$connection_name = $metabox['args']['connection'];
		$direction = $metabox['args']['direction'];

		$connection = $this->connection_factory->get_connection( $connection_name );

		$connection_args = $connection->get_args();

		$selected = ($direction == 'to') ? $connection->get_connected_to_objects( $post->ID ) : $connection->get_connected_from_objects( $post->ID );

		$args = array(
			'post_type' => ($direction == 'to') ? $connection->to() : $connection->from(),
			'selected' => $selected,
			'sortable' => $connection->is_sortable( $direction ),
			'labels' => $connection_args[$direction]['labels'],
			'orderby' => $connection_args['metabox']['orderby'],
			'order' => $connection_args['metabox']['order'],
			'limit' => $connection_args[$direction]['limit']
		);

		$args = apply_filters( "o2o_{$connection_name}_psu_args", $args, $direction );

		echo post_selection_ui( $connection_name . '_' . $direction, $args );


		wp_nonce_field( 'set_' . $connection->get_name() . '_' . $direction . '_' . $post->ID, $connection->get_name() . '_' . $direction . '_nonce' );
	}

	public function __action_save_post( $post_id ) {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return $post_id;
		}
		$post_type = get_post_type( $post_id );

		foreach ( $this->connection_factory->get_connections() as $connection ) {
			$connection_args = $connection->get_args();

			if ( isset( $_POST[$connection->get_name() . '_' . 'to'] ) &&
				isset( $_POST[$connection->get_name() . '_to_nonce'] ) &&
				wp_verify_nonce( $_POST[$connection->get_name() . '_to_nonce'], 'set_' . $connection->get_name() . '_to_' . $post_id ) ) {

				if ( in_array( $post_type, $connection->from() ) ) {
					$to_ids = empty( $_POST[$connection->get_name() . '_to'] ) ? array( ) : array_map( 'intval', explode( ',', $_POST[$connection->get_name() . '_to'] ) );
					$connection->set_connected_to( $post_id, $to_ids );
				}
			}

			if ( $connection_args['reciprocal'] && isset( $_POST[$connection->get_name() . '_' . 'from'] ) &&
				isset( $_POST[$connection->get_name() . '_from_nonce'] ) &&
				wp_verify_nonce( $_POST[$connection->get_name() . '_from_nonce'], 'set_' . $connection->get_name() . '_from_' . $post_id ) ) {

				if ( in_array( $post_type, $connection->to() ) ) {
					$from_ids = empty( $_POST[$connection->get_name() . '_from'] ) ? array( ) : array_map( 'intval', explode( ',', $_POST[$connection->get_name() . '_from'] ) );

					$current_from_ids = $connection->get_connected_from_objects( $post_id );


					//remove this post from all the posts this item was removed from
					foreach ( array_diff( $current_from_ids, $from_ids ) as $from_id_to_remove ) {
						$current_to_ids = $connection->get_connected_to_objects( $from_id_to_remove );
						if ( false !== ($offset = array_search( $post_id, $current_to_ids ) ) ) {
							array_splice( $current_to_ids, $offset, 1 );
						}
						$connection->set_connected_to( $from_id_to_remove, $current_to_ids );
					}

					//add this post to all of the currently selected items
					foreach ( array_diff( $from_ids, $current_from_ids ) as $from_id_to_add ) {
						$connection->set_connected_to( $from_id_to_add, array( $post_id ), true );
					}
				}
			}
		}
	}

}