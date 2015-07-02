<?php

/*
Plugin Name: Formategory
Plugin URI: http://www.chrisfinke.com/wordpress/plugins/formategory/
Description: Formats posts based on their categories.
Version: 2.0
Author: Christopher Finke
Author URI: http://www.chrisfinke.com/
*/

define( 'FORMATEGORY_VERSION', '2.0' );

class FORMATEGORY {

	static function init() {

		load_plugin_textdomain( 'formategory', false, dirname( __FILE__ ) . "/languages/" );

		register_post_type( 'formategory_template',
			array(
				'label' => __( 'Category Templates', 'formategory' ),
				'labels' => array(
					'name' => __( 'Category Templates', 'formategory' ),
					'singular_name' => __( 'Category Template', 'formategory' ),
					'add_new' => __( 'Add New Template', 'formategory' ),
					'add_new_item' => __( 'Add New Template', 'formategory' ),
					'edit_item' => __( 'Edit Category Template', 'formategory' ),
					'new_item' => __( 'New Category Template', 'formategory' ),
					'view_item' => '',
					'search_items' => __( 'Search Category Templates', 'formategory' ),
					'not_found' => __( 'No category templates found', 'formategory' ),
					'not_found' => __( 'No category templates found in Trash', 'formategory' ),
				),
				'show_ui' => true,
				'supports' => array(
					'editor',
					'author',
					'revisions'
				),
				'taxonomies' => array( 'category' ),
				'menu_position' => 5
			)
		);
	}
	
	static function admin_init() {

		$installed_version = get_option( 'formategory_version' );

		if ( FORMATEGORY_VERSION != $installed_version ) {
			update_option( 'formategory_version', FORMATEGORY_VERSION );
		}

		add_filter( 'the_title', array( 'FORMATEGORY', 'template_title' ), 1, 2 );
		
		add_action( 'admin_enqueue_scripts', array( 'FORMATEGORY', 'admin_enqueue_scripts' ) );
		
		add_action( 'add_meta_boxes', array( 'FORMATEGORY', 'add_helper_meta_box' ) );
	}
	
	static function admin_enqueue_scripts() {
		wp_enqueue_script( 'formategory-template-editing', plugins_url( 'js/admin-edit.js', __FILE__ ), 'jquery', FORMATEGORY_VERSION, true );
		wp_enqueue_style( 'formategory-admin', plugins_url( 'css/admin.css', __FILE__ ), array(), FORMATEGORY_VERSION );
	}
	
	static function add_helper_meta_box() {
		add_meta_box( 'formategory_placeholders', __( 'Template Placeholders', 'formategory' ), array( 'FORMATEGORY', 'print_helper_meta_box' ), 'formategory_template', 'side', 'high' );
	}

	static function print_helper_meta_box() {
		?>
		<p>
			<?php esc_html_e( 'Use these buttons to insert placeholders for post data in your template.', 'formategory' ); ?>
		</p>
		<button class="formategory-placeholder button button-highlighted" data-placeholder="the_content"><?php esc_html_e( 'Post Content', 'formategory' ); ?></button>
		<button class="formategory-placeholder button button-highlighted" data-placeholder="the_title"><?php esc_html_e( 'Post Title', 'formategory' ); ?></button>
		<?php
	}
	
	static function format( $content ) {
		global $post, $wpdb;

		if ( ! in_the_loop() )
			return $content;

		static $templates;
		if ( is_null( $templates ) ) {
			$templates_query = new WP_Query( array( 'posts_per_page' => 50, 'post_type' => 'formategory_template', 'suppress_filters' => true, 'update_meta_cache' => false, 'no_found_rows' => true ) );
			$templates = array();
			if ( ! empty( $templates_query->posts ) ) {
				foreach( $templates_query->posts as $template ) {
					$template_obj = new stdClass;
					$template_obj->ID = $template->ID;
					$template_obj->post_content = $template->post_content;
					$template_obj->categories = wp_list_pluck( get_the_terms( $template->ID, 'category' ), 'term_id' );
					$templates[] = $template_obj;
				}
			}
		}

		$post_terms = wp_list_pluck( get_the_terms( $post->ID, 'category' ), 'term_id' );
		
		if ( ! empty( $post_terms ) ) {
			$templates_applied = array();
			
			foreach ( $post_terms as $term_id ) {
				$templates_to_apply = array();
				foreach( $templates as $template ) {
					if ( in_array( $term_id, $template->categories ) )
						$templates_to_apply[] = $template;
				}
				
				if ( ! empty( $templates_to_apply ) ) {
					foreach ( $templates_to_apply as $template ) {
						if ( ! isset( $templates_applied[$template->ID] ) ) {
							$templates_applied[$template->ID] = true;
							
							if ( $template->post_content ) {
								$content = preg_replace( "/{{\s*the_content\s*}}/s", $content, $template->post_content );
								$content = preg_replace( "/{{\s*the_title\s*}}/s", $post->post_title, $content );
							}
						}
					}
				}
			}
		}

		return $content;
	}

	/**
	 * When displaying the title of a template "post" in the admin, show the categories instead.
	 */
	static function template_title( $title, $id ) {
		$post = get_post( $id );
		
		if ( 'formategory_template' == $post->post_type ) {
			$categories = wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) );
			
			return implode( ', ', $categories );
		}
		
		return $title;
	}
	
	static function action_links( $links, $file ) {
		if ( $file == plugin_basename( dirname(__FILE__) . '/formategory.php' ) ) {
			$links[] = '<a href="edit.php?post_type=formategory_template">' . esc_html__( 'Templates', 'formategory' ) .' </a>';
		}

		return $links;
	}
}

add_action( 'init', array( 'FORMATEGORY', 'init' ) );
add_action( 'admin_init', array( 'FORMATEGORY', 'admin_init' ) );

add_filter( 'the_content', array( 'FORMATEGORY', 'format' ), 1 );
add_filter( 'plugin_action_links', array( 'FORMATEGORY', 'action_links' ), 10, 2 );