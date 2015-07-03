<?php
/*
Plugin Name: WP Help
Description: Administrators can create detailed, hierarchical documentation for the site's authors and editors, viewable in the WordPress admin.
Version: 1.2
License: GPL
Plugin URI: http://txfx.net/wordpress-plugins/wp-help/
Author: Mark Jaquith
Author URI: http://coveredwebservices.com/
Text Domain: wp-help

==========================================================================

Copyright 2011-2012  Mark Jaquith

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

class CWS_WP_Help_Plugin {
	public static $instance;
	private $options;
	private $admin_base = '';
	private $help_topics_html;
	private $filter_wp_list_pages = false;
	const default_doc = 'cws_wp_help_default_doc';
	const OPTION      = 'cws_wp_help';
	const MENU_SLUG   = 'wp-help-documents';
	const CRON_HOOK   = 'cws_wp_help_update';
	const POST_TYPE   = 'wp-help';

	public function __construct() {
		self::$instance = $this;
		add_action( 'init', array( $this, 'init' ) );
	}

	public function init() {
		// Translations
		load_plugin_textdomain( 'wp-help', false, basename( dirname( __FILE__ ) ) . '/languages' );

		// Options
		$raw_options = get_option( self::OPTION );
		if ( !is_array( $raw_options ) )
			$raw_options = array();
		if ( !isset( $raw_options['key'] ) ) {
			// Normally, we shouldn't set defaults, but the key is a
			// generate-once deal, so we do that now if it does not exist.
			$raw_options['key'] = md5( wp_generate_password( 128, true, true ) );
			update_option( self::OPTION, $raw_options );
		}
		// Now grab the options, with defaults merged in
		$this->options = $this->get_options();

		// Cron job
		if ( !wp_next_scheduled( self::CRON_HOOK ) )
			wp_schedule_event( current_time( 'timestamp' ), 'daily', self::CRON_HOOK );

		// Actions and filters
		add_action( self::CRON_HOOK,                array( $this, 'api_slurp'             )        );
		add_filter( 'map_meta_cap',                 array( $this, 'map_meta_cap'          ), 10, 4 );
		add_action( 'admin_menu',                   array( $this, 'admin_menu'            )        );
		add_action( 'post_submitbox_misc_actions',  array( $this, 'submitbox_actions'     )        );
		add_action( 'save_post',                    array( $this, 'save_post'             )        );
		add_filter( 'post_type_link',               array( $this, 'page_link'             ), 10, 2 );
		add_filter( 'post_updated_messages',        array( $this, 'post_updated_messages' )        );
		add_action( 'admin_init',                   array( $this, 'ajax_listener'         )        );
		add_action( 'wp_ajax_cws_wp_help_settings', array( $this, 'ajax_settings'         )        );
		add_action( 'wp_ajax_cws_wp_help_reorder',  array( $this, 'ajax_reorder'          )        );
		add_action( 'clean_post_cache',             array( $this, 'clean_post_cache'      ), 10, 2 );
		add_action( 'delete_post',                  array( $this, 'delete_post'           )        );
		add_action( 'wp_trash_post',                array( $this, 'delete_post'           )        );
		add_action( 'load-post.php',                array( $this, 'load_post'             )        );
		add_action( 'load-post-new.php',            array( $this, 'load_post_new'         )        );
		add_action( 'wp_dashboard_setup',           array( $this, 'wp_dashboard_setup'    )        );
		add_filter( 'page_css_class',               array( $this, 'page_css_class'        ), 10, 5 );
		add_filter( 'wp_list_pages',                array( $this, 'wp_list_pages'         )        );

		if ( 'dashboard-submenu' != $this->get_option( 'menu_location' ) ) {
			$this->admin_base = 'admin.php';
			if ( 'bottom' != $this->get_option( 'menu_location' ) ) {
				add_filter( 'custom_menu_order', '__return_true' );
				add_filter( 'menu_order', array( $this, 'menu_order' ) );
			}
		} else {
			$this->admin_base = 'index.php';
		}
		add_filter( 'page_attributes_dropdown_pages_args', array( $this, 'page_attributes_dropdown' ), 10, 2 );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'action_links' ) );
		add_filter( 'network_admin_plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'action_links') );

		// menu_order debug
		// add_filter( 'the_title', function( $title, $post_id ) { $post = get_post( $post_id ); return $title . ' [' . $post->menu_order . ']'; }, 10, 2 );

		// Register the wp-help post type
		register_post_type( self::POST_TYPE,
			array(
				'label'        => _x( 'Publishing Help', 'post type label', 'wp-help' ),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => false,
				'hierarchical' => true,
				'supports'     => array( 'title', 'editor', 'revisions', 'page-attributes' ),
				'capabilities' => array(
					'publish_posts'      => 'publish_pages',
					'edit_posts'         => 'publish_pages',
					'edit_others_posts'  => 'publish_pages',
					'delete_posts'       => 'publish_pages',
					'read_private_posts' => 'publish_pages',
					'edit_post'          => 'wp_help_meta_cap',
					'delete_post'        => 'wp_help_meta_cap',
					'read_post'          => apply_filters( 'cws_wp_help_view_documents_cap', 'edit_posts' ),
				),
				'labels' => array (
					'name'               => __( 'Help Documents',                        'wp-help' ),
					'singular_name'      => __( 'Help Document',                         'wp-help' ),
					'add_new_item'       => __( 'Add New Help Document',                 'wp-help' ),
					'edit_item'          => __( 'Edit Help Document',                    'wp-help' ),
					'new_item'           => __( 'New Help Document',                     'wp-help' ),
					'view_item'          => __( 'View Help Document',                    'wp-help' ),
					'search_items'       => __( 'Search Documents',                      'wp-help' ),
					'not_found'          => __( 'No Help Documents Found',               'wp-help' ),
					'not_found_in_trash' => __( 'No Help Documents found in Trash',      'wp-help' ),
					'parent'             => __( 'Parent Help Document',                  'wp-help' ),
					'add_new'            => _x( 'Add New', 'i.e. Add new Help Document', 'wp-help' ),
					'edit'               => _x( 'Edit',    'i.e. Edit Help Document',    'wp-help' ),
					'view'               => _x( 'View',    'i.e. View Help Document',    'wp-help' ),
				)
			)
		);

		// Check for API requests
		if ( isset( $_REQUEST['wp-help-key'] ) && $this->get_option( 'key' ) === $_REQUEST['wp-help-key'] )
			$this->api_request();

		// Debug:
		// $this->api_slurp();
	}

	private function get_option_defaults() {
		return apply_filters( 'cws_wp_help_option_defaults', array(
				'h2'            => _x( 'Publishing Help', 'h2 default title', 'wp-help' ),
				'h3'            => _x( 'Help Topics', 'h3 default title', 'wp-help' ),
				'menu_location' => 'below-dashboard',
		) );
	}

	private function get_options() {
		$raw_options = get_option( self::OPTION );
		if ( !is_array( $raw_options ) )
			$raw_options = array();
		return array_merge( $this->get_option_defaults(), $raw_options );
	}

	private function get_cap( $cap ) {
		return get_post_type_object( self::POST_TYPE )->cap->{$cap};
	}

	public function action_links( $links ) {
		$links['donate'] = '<a href="http://txfx.net/wordpress-plugins/donate">' . __( 'Donate', 'wp-help' ) . '</a>';
		return $links;
	}

	public function wp_dashboard_setup() {
		if ( current_user_can( $this->get_cap( 'read_post' ) ) ) {
			$this->help_topics_html = $this->get_help_topics_html();
			if ( $this->help_topics_html )
				wp_add_dashboard_widget( 'cws-wp-help-dashboard-widget', $this->get_option( 'h2' ), array( $this, 'dashboard_widget' ) );
		}
	}

	public function dashboard_widget() {
		?><style>
		#cws-wp-help-dashboard-listing ul {
			margin: 5px 5px 5px 15px;
			list-style: square;
		}

		#cws-wp-help-dashboard-listing {
			margin: 10px 5px 10px 20px;
			list-style: circle;
		}

		#cws-wp-help-dashboard-listing > ul {
			list-style: square;
		}
		</style><?php
		echo '<ul id="cws-wp-help-dashboard-listing">';
		echo $this->help_topics_html;
		echo '</ul>';
	}

	public function delete_post( $post_id ) {
		if ( self::POST_TYPE === get_post_type( $post_id ) ) {
			// If the default doc was deleted, kill the option
			if ( absint( get_option( self::default_doc ) ) === absint( $post_id ) )
				update_option( self::default_doc, 0 );
		}
	}

	public function page_css_class( $classes, $page, $depth, $args, $current_page ) {
		if ( !$this->filter_wp_list_pages )
			return $classes;
		if ( $this->is_slurped( $page->ID ) )
			$classes[] = 'cws-wp-help-is-slurped';
		else
			$classes[] = 'cws-wp-help-local';
		return $classes;
	}

	public function wp_list_pages( $html ) {
		if ( !$this->filter_wp_list_pages )
			return $html;
		return preg_replace( '#<li [^>]+>#', '$0<img class="sort-handle" src="' . plugins_url( "images/sort.png", __FILE__ ) . '" />', $html );
	}

	public function page_attributes_dropdown( $args, $post ) {
		if ( self::POST_TYPE !== get_post_type( $post ) )
			return $args;
		$pages = get_pages( array( 'post_type' => self::POST_TYPE, 'child_of' => 0, 'parent' => 0, 'post_status' => 'publish', 'hierarchical' => false, 'meta_key' => '_cws_wp_help_slurp_source', 'meta_value' => $this->get_slurp_source_key() ) );
		$args['exclude'] = array();
		foreach ( $pages as $p ) {
			$args['exclude'][] = absint( $p->ID );
		}
		return $args;
	}

	public function clean_post_cache( $post_id, $post ) {
		if ( self::POST_TYPE === $post->post_type )
			wp_cache_delete( 'get_pages', 'posts' ); // See: http://core.trac.wordpress.org/ticket/21279
	}

	private function explain_slurp( $id ) {
		if ( current_user_can( 'manage_options' ) && !current_user_can( 'edit_post', $id ) ) {
			// Post is remote. Explain
			echo ' <small>' . __( '&mdash; Remote document', 'wp-help' ) . '</small>';
		}
	}

	public function load_post() {
		if ( isset( $_GET['post'] ) && self::POST_TYPE === get_post_type( $_GET['post'] ) )
			$this->edit_enqueues();
	}

	public function load_post_new() {
		if ( isset( $_GET['post_type'] ) && self::POST_TYPE === $_GET['post_type'] )
			$this->edit_enqueues();
	}

	private function edit_enqueues() {
		wp_enqueue_script( 'jquery' );
		add_action( 'admin_footer', array( $this, 'edit_page_js' ) );
	}

	public function edit_page_js() {
		?><script>(function($){
			var a = $('.wrap:first h2:first a:first'), i = ' <a href="edit.php?post_type=<?php echo self::POST_TYPE; ?>" class="add-new-h2"><?php echo esc_js( _x( 'Manage', 'verb. Button with limited space', 'wp-help' ) ); ?></a> ';
			if ( a.length )
				a.before(i);
			else
				$('.wrap:first h2:first').append(i);
			$('#parent_id').detach().insertAfter( '.misc-pub-section:last' ).wrap('<div class="misc-pub-section"></div>').before( '<?php echo esc_js( __( 'Parent:', 'wp-help' ) ); ?> ' ).css( 'max-width', '80%' );
			$('#pageparentdiv').hide();
			$('#screen-options-wrap #pageparentdiv-hide').parent('label').hide();
			})(jQuery);</script><?php
	}

	public function map_meta_cap( $caps, $cap, $user_id, $args ) {
		if ( $cap === 'wp_help_meta_cap' ) {
			// If this belongs to the currently connected slurp source, disallow editing
			if ( $this->is_slurped( $args[0] ) )
				$caps = array( 'do_not_allow' );
			else
				$caps = array( $this->get_cap( 'publish_posts' ) );
		}
		return $caps;
	}

	private function get_slurp_source_key() {
		return substr( md5( $this->get_option( 'slurp_url' ) ), 0, 8 );
	}

	private function is_slurped( $post_id ) {
		 return $this->get_slurp_source_key() === get_post_meta( $post_id, '_cws_wp_help_slurp_source', true );
	}

	public function api_slurp() {
		if ( !$this->get_option( 'slurp_url' ) )
			return;
		$result = wp_remote_get( add_query_arg( 'time', time(), $this->get_option( 'slurp_url' ) ) );
		if ( $result['response']['code'] == 200 ) {
			$topics = new WP_Query( array( 'post_type' => self::POST_TYPE, 'posts_per_page' => -1, 'post_status' => 'publish' ) );
			$source_id_to_local_id = array();
			if ( $topics->posts ) {
				foreach ( $topics->posts as $p ) {
					if ( $this->is_slurped( $p->ID ) && $source_id = get_post_meta( $p->ID, '_cws_wp_help_slurp_id', true ) )
						$source_id_to_local_id[$source_id] = $p->ID;
				}
			}
			$posts = json_decode( $result['body'] );
			$source_post_ids = array();
			// First pass: just insert whatever is missing, without fixing post_parent
			foreach ( $posts as $p ) {
				$p = (array) $p;
				$source_post_ids[absint( $p['ID'] )] = absint( $p['ID'] );
				// These things are implied in the API, but we need to set them before inserting locally
				$p['post_type'] = self::POST_TYPE;
				$p['post_status'] = 'publish';
				// $p['menu_order'] += 100000;
				$copy = $p;
				if ( isset( $source_id_to_local_id[$p['ID']] ) ) {
					// Exists. We know the local ID.
					$copy['ID'] = $source_id_to_local_id[$p['ID']];
					wp_update_post( $copy );
				} else {
					// This is new. Insert it.
					unset( $copy['ID'] );
					$new_local_id = wp_insert_post( $copy );

					// Update our lookup table
					$source_id_to_local_id[$p['ID']] = $new_local_id;
					// Update postmeta
					update_post_meta( $new_local_id, '_cws_wp_help_slurp_id', absint( $p['ID'] ) );
					update_post_meta( $new_local_id, '_cws_wp_help_slurp_source', $this->get_slurp_source_key() );
				}
			}
			// Set the default document
			foreach ( $posts as $p ) {
				if ( isset( $p->default ) && isset( $source_id_to_local_id[ $p->ID ] ) ) {
					update_option( self::default_doc, $source_id_to_local_id[ $p->ID ] );
					break;
				}
			}
			// Delete any abandoned posts
			$topics = new WP_Query( array( 'post_type' => self::POST_TYPE, 'posts_per_page' => -1, 'post_status' => 'any', 'meta_query' => array( array( 'key' => '_cws_wp_help_slurp_id', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ) ) ) );
			if ( $topics->posts ) {
				foreach ( $topics->posts as $p ) {
					if ( $source_id = get_post_meta( $p->ID, '_cws_wp_help_slurp_id', true ) ) {
						// This was slurped. Was it absent from the API response? Or was it from a different source?
						if ( !$this->is_slurped( $p->ID ) || !isset( $source_post_ids[absint($source_id)] ) ) {
							// Wasn't in the response. Delete it.
							wp_delete_post( $p->ID );
						}
					}
				}
			}
			// Reparenting and link fixing
			$topics = new WP_Query( array( 'post_type' => self::POST_TYPE, 'posts_per_page' => -1, 'post_status' => 'publish', 'meta_query' => array( array( 'key' => '_cws_wp_help_slurp_id', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ) ) ) );
			if ( $topics->posts ) {
				foreach ( $topics->posts as $p ) {
					$new = array();
					if ( strpos( $p->post_content, 'http://wp-help-link/' ) !== false ) {
						$new['post_content'] = $this->make_links_local( $p->post_content );
						if ( $new['post_content'] === $p->post_content )
							unset( $new['post_content'] );
					}
					$new['post_parent'] = $this->local_id_from_slurp_id( $p->post_parent );
					if ( $new['post_parent'] === $p->post_parent )
						unset( $new['post_parent'] );
					if ( $new ) {
						$new['ID'] = $p->ID;
						wp_update_post( $new );
					}
				}
			}
		}
	}

	private function api_request() {
		die( json_encode( $this->get_topics_for_api() ) );
	}

	public function convert_links_cb( $matches ) {
		if ( preg_match( '#page=wp-help-documents&(amp;)?document=([0-9]+)#', $matches[2], $url ) ) {
			return 'href=' . $matches[1] . 'http://wp-help-link/' . $url[2] . $matches[1];
		}
		return $matches[0];
	}

	private function convert_links( $content ) {
		$content = preg_replace_callback( '#href=(["\'])([^\\1]+)\\1#', array( $this, 'convert_links_cb' ), $content );
		$admin_url = parse_url( admin_url( '/' ) );
		$content = preg_replace( '#(https?)://' . preg_quote( $admin_url['host'] . $admin_url['path'], '#' ) . '#', '', $content );
		return $content;
	}

	public function make_links_local_cb( $matches ) {
		$local_id = $this->local_id_from_slurp_id( $matches[2] );
		if ( $local_id )
			return 'href=' . $matches[1] . get_permalink( absint( $local_id ) ) . $matches[1];
		return $matches[0];
	}

	private function make_links_local( $content ) {
		return preg_replace_callback( '#href=(["\'])http://wp-help-link/([0-9]+)\\1#', array( $this, 'make_links_local_cb' ), $content );
	}

	private function local_id_from_slurp_id( $id ) {
		if ( !$id )
			return false;
		$local = new WP_Query( array( 'post_type' => self::POST_TYPE, 'posts_per_page' => 1, 'post_status' => 'publish', 'meta_query' => array( array( 'key' => '_cws_wp_help_slurp_id', 'value' => $id ) ) ) );

		if ( $local->posts )
			return $local->posts[0]->ID;
		return false;
	}

	private function get_topics_for_api() {
		$topics = new WP_Query( array( 'post_type' => self::POST_TYPE, 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'parent menu_order', 'order' => 'ASC' ) );
		$default_doc = get_option( self::default_doc );
		$menu_order = array();
		foreach ( $topics->posts as $k => $post ) {
			$c =& $topics->posts[$k];
			unset( $c->guid, $c->post_author, $c->comment_count, $c->post_mime_type, $c->post_status, $c->post_type, $c->pinged, $c->to_ping, $c->filter, $c->ping_status, $c->comment_status, $c->post_password );
			if ( isset( $menu_order[$c->post_parent] ) )
				$menu_order[$c->post_parent]++;
			else
				$menu_order[$c->post_parent] = 1;
			$c->menu_order = $menu_order[$c->post_parent];
			if ( !$c->post_parent )
				unset( $c->post_parent );
			$c->post_content = $this->convert_links( $c->post_content );
			if ( $c->ID == $default_doc )
				$c->default = true;
		}
		return $topics->posts;
	}

	public function menu_order( $menu ) {
		$custom_order = array();
		foreach ( $menu as $index => $item ) {
			if ( 'index.php' == $item ) {
				if ( 'below-dashboard' == $this->get_option( 'menu_location' ) )
					$custom_order[] = 'index.php';
				$custom_order[] = self::MENU_SLUG;
				if ( 'above-dashboard' == $this->get_option( 'menu_location' ) )
					$custom_order[] = 'index.php';
			} elseif ( self::MENU_SLUG != $item ) {
				$custom_order[] = $item;
			}
		}
		return $custom_order;
	}

	private function get_option( $key ) {
		if ( 'menu_location' == $key && isset( $_GET['wp-help-preview-menu-location'] ) )
			return $_GET['wp-help-preview-menu-location'];
		return isset( $this->options[$key] ) ? $this->options[$key] : false;
	}

	private function update_options( $options ) {
		$this->options = $options;
		update_option( self::OPTION, $options );
	}

	private function api_url() {
		return home_url( '/?wp-help-key=' . $this->get_option( 'key' ) );
	}

	public function ajax_settings() {
		if ( current_user_can( 'manage_options' ) && check_ajax_referer( 'cws-wp-help-settings' ) ) {
			$error = false;
			$refresh = false;
			$old_menu_location = $this->options['menu_location'];
			$old_slurp_url = $this->options['slurp_url'];
			$this->options['h2'] = stripslashes( $_POST['h2'] );
			$this->options['h3'] = stripslashes( $_POST['h3'] );
			$slurp_url = stripslashes( $_POST['slurp_url'] );
			if ( $slurp_url === $this->api_url() )
				$error = __( 'What are you doing? You&#8217;re going to create an infinite loop!', 'wp-help' );
			elseif ( empty( $slurp_url ) )
				$this->options['slurp_url'] = '';
			elseif ( strpos( $slurp_url, '?wp-help-key=' ) === false )
				$error = __( 'That is not a WP Help URL. Make sure you copied it correctly.', 'wp-help' );
			else
				$this->options['slurp_url'] = esc_url_raw( $slurp_url );
			if ( $this->options['slurp_url'] !== $old_slurp_url && !empty( $this->options['slurp_url'] ) )
				$refresh = true;
			if ( !$error )
				$this->options['menu_location'] = stripslashes( $_POST['menu_location'] );
			$this->update_options( $this->options );
			$result = array(
				'slurp_url' => $this->options['slurp_url'],
				'error' => $error
			);
			if ( $refresh ) {
				$this->api_slurp();
				$result['topics'] = $this->get_help_topics_html( true );
			} elseif ( !empty( $this->options['slurp_url'] ) ) {
				// It didn't change, but we should trigger an update in the background
				wp_schedule_single_event( current_time( 'timestamp' ), self::CRON_HOOK );
			}
			die( json_encode( $result ) );
		} else {
			die( '-1' );
		}
	}

	public function ajax_reorder() {
		if ( current_user_can( $this->get_cap( 'publish_posts' ) ) && check_ajax_referer( 'cws-wp-help-reorder' ) ) {
			$order = array();
			foreach( $_POST['order'] as $o ) {
				$order[] = str_replace( 'page-', '', $o );
			}
			$val = -100000;
			foreach ( $order as $o ) {
				if ( 'cws-wp-help-remote-docs-block' === $o ) {
					$val = 100000;
					continue;
				}
				$val += 10;
				if ( $p = get_page( $o ) ) {
					if ( intval( $p->menu_order ) !== $val )
						wp_update_post( array( 'ID' => $p->ID, 'menu_order' => $val ) );
				}
			}
		}
	}

	public function ajax_listener() {
		if ( !defined( 'DOING_AJAX' ) || !DOING_AJAX || !isset( $_POST['action'] ) || 'wp-link-ajax' != $_POST['action'] )
			return;
		// It's the right kind of request
		// Now to see if it originated from our post type
		$qs = parse_url( $_SERVER['HTTP_REFERER'], PHP_URL_QUERY );
		wp_parse_str( $qs, $vars );
		if ( isset( $vars['post_type'] ) ) {
			$post_type = $vars['post_type'];
		} elseif ( isset( $vars['post'] ) ) {
			$post = get_post( $vars['post'] );
			$post_type = $post->post_type;
		} else {
			// Can't determine post type. Bail.
			return;
		}
		if ( self::POST_TYPE == $post_type ) {
			// Nice! This originated from our post type
			// Now we make our post type public, and initiate a query filter
			// There really should be a better way to do this. :-\
			add_filter( 'pre_get_posts', array( $this, 'only_query_help_docs' ) );
			global $wp_post_types;
			$wp_post_types[self::POST_TYPE]->publicly_queryable = $wp_post_types[self::POST_TYPE]->public = true;
		}
	}

	public function only_query_help_docs( $q ) {
		$q->set( 'post_type', self::POST_TYPE );
	}

	public function admin_menu() {
		if ( 'dashboard-submenu' != $this->get_option( 'menu_location' ) )
			$hook = add_menu_page( $this->get_option( 'h2' ), $this->get_option( 'h2' ), $this->get_cap( 'read_post' ), self::MENU_SLUG, array( $this, 'render_listing_page' ), plugin_dir_url( __FILE__ ) . 'images/icon-16.png' );
		else
			$hook = add_dashboard_page( $this->get_option( 'h2' ), $this->get_option( 'h2' ), $this->get_cap( 'read_post' ), self::MENU_SLUG, array( $this, 'render_listing_page' ) );
		add_action( "load-{$hook}", array( $this, 'enqueue' ) );
	}

	public function submitbox_actions() {
		if ( self::POST_TYPE !== get_post_type() )
			return;
		global $post;
		wp_nonce_field( 'cws-wp-help-save', '_cws_wp_help_nonce', false, true ); ?>
		<div class="misc-pub-section"><input type="checkbox" name="cws_wp_help_make_default_doc" id="cws_wp_help_make_default_doc" <?php checked( $post->ID == get_option( self::default_doc ) ); ?> /> &nbsp;<label for="cws_wp_help_make_default_doc"><?php _e( 'Set as default help document', 'wp-help' ); ?></label></div>
		<?php
	}

	public function save_post( $post_id ) {
		if ( isset( $_POST['_cws_wp_help_nonce'] ) && wp_verify_nonce( $_POST['_cws_wp_help_nonce'], 'cws-wp-help-save' ) ) {
			if ( isset( $_POST['cws_wp_help_make_default_doc'] ) ) {
				// Make it the default_doc
				update_option( self::default_doc, absint( $post_id ) );
			} elseif ( $post_id == get_option( self::default_doc ) ) {
				// Unset
				update_option( self::default_doc, 0 );
			}
		}
		return $post_id;
	}

	public function post_updated_messages( $messages ) {
		global $post_ID, $post;
		$permalink = get_permalink( $post_ID );
		$messages[self::POST_TYPE] = array(
			 0 => '', // Unused. Messages start at index 1.
			 1 => sprintf( __( 'Document updated. <a href="%s">View document</a>', 'wp-help' ), esc_url( $permalink ) ),
			 2 => __( 'Custom field updated.', 'wp-help' ),
			 3 => __( 'Custom field deleted.', 'wp-help' ),
			 4 => __( 'Document updated.', 'wp-help' ),
			 5 => isset( $_GET['revision'] ) ? sprintf( __( 'Document restored to revision from %s', 'wp-help' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			 6 => sprintf( __( 'Document published. <a href="%s">View document</a>', 'wp-help' ), esc_url( $permalink ) ),
			 7 => __( 'Document saved.', 'wp-help' ),
			 8 => sprintf( __( 'Document submitted. <a target="_blank" href="%s">Preview document</a>', 'wp-help' ), esc_url( add_query_arg( 'preview', 'true', $permalink ) ) ),
			 9 => sprintf( __( 'Document scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview document</a>', 'wp-help' ), date_i18n( __( 'M j, Y @ G:i', 'wp-help' ), strtotime( $post->post_date ) ), esc_url( $permalink ) ),
			10 => sprintf( __('Document draft updated. <a target="_blank" href="%s">Preview document</a>', 'wp-help' ), esc_url( add_query_arg( 'preview', 'true', $permalink ) ) ),
		);
		return $messages;
	}

	public function enqueue() {
		$suffix = defined ('SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		wp_enqueue_style( 'cws-wp-help', plugins_url( "css/wp-help$suffix.css", __FILE__ ), array(), '20120721b' );
		wp_enqueue_script( 'cws-wp-help', plugins_url( "js/wp-help$suffix.js", __FILE__ ), array( 'jquery', 'jquery-ui-sortable' ), '20120829' );
		do_action( 'cws_wp_help_load' ); // Use this to enqueue your own styles for things like shortcodes.
	}

	public function maybe_just_menu() {
		if ( isset( $_GET['wp-help-preview-menu-location'] ) )
			add_action( 'in_admin_header', array( $this, 'kill_it' ), 0 );
	}

	public function kill_it() {
		exit();
	}

	public function page_link( $link, $post ) {
		$post = get_post( $post );
		if ( self::POST_TYPE == $post->post_type )
			return admin_url( $this->admin_base . '?page=' . self::MENU_SLUG . '&document=' . absint( $post->ID ) );
		else
			return $link;
	}

	private function get_help_topics_html( $with_sort_handles = false ) {
		if ( $with_sort_handles )
			$this->filter_wp_list_pages = true;
		$output = trim( wp_list_pages( array( 'post_type' => self::POST_TYPE, 'hierarchical' => true, 'echo' => false, 'title_li' => '' ) ) );
		$this->filter_wp_list_pages = false;
		return $output;
	}

	public function render_listing_page() {
		$document_id = absint( isset( $_GET['document'] ) ? $_GET['document'] : get_option( self::default_doc ) );
		if ( $document_id ) : ?>
			<style>
			#cws-wp-help-listing .page-item-<?php echo $document_id; ?> > a {
				font-weight: bold;
			}
			</style>
		<?php endif; ?>
<div class="wrap">
	<?php screen_icon(self::POST_TYPE); ?><div id="cws-wp-help-h2-label-wrap"><input type="text" id="cws-wp-help-h2-label" value="<?php echo esc_attr( $this->get_option( 'h2' ) ); ?>" /></div><img id="cws-wp-help-loading" src="<?php echo plugins_url( '/images/loading.gif', __FILE__ ); ?>" style="display:none;" /><h2><?php echo esc_html( $this->get_option( 'h2' ) ); ?></h2>
	<?php include( dirname( __FILE__ ) . '/templates/list-documents.php' ); ?>
</div>
<?php
	}
}

new CWS_WP_Help_Plugin;
