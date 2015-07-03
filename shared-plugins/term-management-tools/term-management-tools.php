<?php
/*
Plugin Name: Term Management Tools
Version: 1.1.3-alpha
Description: Allows you to merge terms and set term parents in bulk
Author: scribu
Author URI: http://scribu.net/
Plugin URI: http://scribu.net/wordpress/term-management-tools
Text Domain: term-management-tools
Domain Path: /lang
*/

class Term_Management_Tools {

	static function init() {
		add_action( 'load-edit-tags.php', array( __CLASS__, 'handler' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );

		load_plugin_textdomain( 'term-management-tools', '', basename( dirname( __FILE__ ) ) . '/lang' );
	}

	private static function get_actions( $taxonomy ) {
		$actions = array(
			'merge'        => __( 'Merge', 'term-management-tools' ),
			'change_tax'   => __( 'Change taxonomy', 'term-management-tools' ),
		);

		if ( is_taxonomy_hierarchical( $taxonomy ) ) {
			$actions = array_merge( array(
				'set_parent' => __( 'Set parent', 'term-management-tools' ),
			), $actions );
		}

		return $actions;
	}

	static function handler() {
		$defaults = array(
			'taxonomy' => 'post_tag',
			'delete_tags' => false,
			'action' => false,
			'action2' => false
		);

		$data = shortcode_atts( $defaults, $_REQUEST );

		$tax = get_taxonomy( $data['taxonomy'] );
		if ( !$tax )
			return;

		if ( !current_user_can( $tax->cap->manage_terms ) )
			return;

		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'script' ) );
		add_action( 'admin_footer', array( __CLASS__, 'inputs' ) );

		$action = false;
		foreach ( array( 'action', 'action2' ) as $key ) {
			if ( $data[ $key ] && '-1' != $data[ $key ] ) {
				$action = $data[ $key ];
			}
		}

		if ( !$action )
			return;

		self::delegate_handling( $action, $data['taxonomy'], $data['delete_tags'] );
	}

	protected static function delegate_handling( $action, $taxonomy, $term_ids ) {
		if ( empty( $term_ids ) )
			return;

		foreach ( array_keys( self::get_actions( $taxonomy ) ) as $key ) {
			if ( 'bulk_' . $key == $action ) {
				check_admin_referer( 'bulk-tags' );
				$r = call_user_func( array( __CLASS__, 'handle_' . $key ), $term_ids, $taxonomy );
				break;
			}
		}

		if ( !isset( $r ) )
			return;

		if ( $referer = wp_get_referer() && false !== strpos( $referer, 'edit-tags.php' ) ) {
			$location = $referer;
		} else {
			$location = add_query_arg( 'taxonomy', $taxonomy, 'edit-tags.php' );
		}

		wp_redirect( add_query_arg( 'message', $r ? 'tmt-updated' : 'tmt-error', $location ) );
		die;
	}

	static function notice() {
		if ( !isset( $_GET['message'] ) )
			return;

		switch ( $_GET['message'] ) {
		case  'tmt-updated':
			echo '<div id="message" class="updated"><p>' . __( 'Terms updated.', 'term-management-tools' ) . '</p></div>';
			break;

		case 'tmt-error':
			echo '<div id="message" class="error"><p>' . __( 'Terms not updated.', 'term-management-tools' ) . '</p></div>';
			break;
		}
	}

	static function handle_merge( $term_ids, $taxonomy ) {
		$term_name = $_REQUEST['bulk_to_tag'];

		if ( !$term = term_exists( $term_name, $taxonomy ) )
			$term = wp_insert_term( $term_name, $taxonomy );

		if ( is_wp_error( $term ) )
			return false;

		$to_term = $term['term_id'];

		$to_term_obj = get_term( $to_term, $taxonomy );

		foreach ( $term_ids as $term_id ) {
			if ( $term_id == $to_term )
				continue;

			$old_term = get_term( $term_id, $taxonomy );

			$ret = wp_delete_term( $term_id, $taxonomy, array( 'default' => $to_term, 'force_default' => true ) );
			if ( is_wp_error( $ret ) ) {
				continue;
			}

			do_action( 'term_management_tools_term_merged', $to_term_obj, $old_term );
		}

		return true;
	}

	static function handle_set_parent( $term_ids, $taxonomy ) {
		$parent_id = $_REQUEST['parent'];

		foreach ( $term_ids as $term_id ) {
			if ( $term_id == $parent_id )
				continue;

			$ret = wp_update_term( $term_id, $taxonomy, array( 'parent' => $parent_id ) );

			if ( is_wp_error( $ret ) )
				return false;
		}

		return true;
	}

	static function handle_change_tax( $term_ids, $taxonomy ) {
		global $wpdb;

		$new_tax = $_POST['new_tax'];

		if ( !taxonomy_exists( $new_tax ) )
			return false;

		if ( $new_tax == $taxonomy )
			return false;

		$tt_ids = array();
		foreach ( $term_ids as $term_id ) {
			$term = get_term( $term_id, $taxonomy );

			if ( $term->parent ) {
				$wpdb->update( $wpdb->term_taxonomy,
					array( 'parent' => 0 ),
					array( 'term_taxonomy_id' => $term->term_taxonomy_id )
				);
			}

			$tt_ids[] = $term->term_taxonomy_id;

			if ( is_taxonomy_hierarchical( $taxonomy ) ) {
				$child_terms = get_terms( $taxonomy, array(
					'child_of' => $term_id,
					'hide_empty' => false
				) );
				$tt_ids = array_merge( $tt_ids, wp_list_pluck( $child_terms, 'term_taxonomy_id' ) );
			}
		}
		$tt_ids = implode( ',', array_map( 'absint', $tt_ids ) );

		$wpdb->query( $wpdb->prepare( "
			UPDATE $wpdb->term_taxonomy SET taxonomy = %s WHERE term_taxonomy_id IN ($tt_ids)
		", $new_tax ) );

		if ( is_taxonomy_hierarchical( $taxonomy ) && !is_taxonomy_hierarchical( $new_tax ) ) {
			$wpdb->query( "UPDATE $wpdb->term_taxonomy SET parent = 0 WHERE term_taxonomy_id IN ($tt_ids)" );
		}

		delete_option( "{$taxonomy}_children" );
		delete_option( "{$new_tax}_children" );

		return true;
	}

	static function script() {
		global $taxonomy;

		$js_dev = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '.dev' : '';

		wp_enqueue_script( 'term-management-tools', plugins_url( "script$js_dev.js", __FILE__ ), array( 'jquery' ), '1.1' );

		wp_localize_script( 'term-management-tools', 'tmtL10n', self::get_actions( $taxonomy ) );
	}

	static function inputs() {
		global $taxonomy;

		foreach ( array_keys( self::get_actions( $taxonomy ) ) as $key ) {
			echo "<div id='tmt-input-$key' style='display:none'>\n";
			call_user_func( array( __CLASS__, 'input_' . $key ), $taxonomy );
			echo "</div>\n";
		}
	}

	static function input_merge( $taxonomy ) {
		printf( __( 'into: %s', 'term-management-tools' ), '<input name="bulk_to_tag" type="text" size="20"></input>' );
	}

	static function input_change_tax( $taxonomy ) {
		$tax_list = get_taxonomies( array( 'show_ui' => true ), 'objects' );
?>
		<select class="postform" name="new_tax">
<?php
		foreach ( $tax_list as $new_tax => $tax_obj ) {
			if ( $new_tax == $taxonomy )
				continue;

			echo "<option value='$new_tax'>$tax_obj->label</option>\n";
		}
?>
		</select>
<?php
	}

	static function input_set_parent( $taxonomy ) {
		wp_dropdown_categories( array(
			'hide_empty' => 0,
			'hide_if_empty' => false,
			'name' => 'parent',
			'orderby' => 'name',
			'taxonomy' => $taxonomy,
			'hierarchical' => true,
			'show_option_none' => __( 'None', 'term-management-tools' )
		) );
	}
}

Term_Management_Tools::init();

