<?php
/*
Plugin Name: Post Author Box
Plugin URI: http://wordpress.org/extend/plugins/post-author-box/
Description: Append or prepend author information to a post
Author: Daniel Bachhuber
Version: 1.4
Author URI: http://danielbachhuber.com/
*/

define( 'POSTAUTHORBOX_VERSION', '1.4' );
define( 'POSTAUTHORBOX_FILE_PATH', __FILE__ );

if ( !class_exists( 'Post_Author_Box' ) ) {

class Post_Author_Box {
	
	var $options_group = 'post_author_box_';
	var $options_group_name = 'post_author_box_options';
	var $settings_page = 'post_author_box_settings';
	var $search_tokens = array(
		'%display_name%',
		'%author_link%',
		'%author_posts_link%',
		'%first_name%',
		'%last_name%',
		'%description%',
		'%email%',
		'%avatar%',
		'%jabber%',
		'%aim%',
		'%post_date%',
		'%post_time%',
		'%post_modified_date%',
		'%post_modified_time%',
	);
	var $supported_views = array(
		'post',
		'page',
		'home',
		'category',
		'tag',
		'archive',
		'author',
		'search',
		'feed',
	);
	
	/**
	 * Initialize the Post Author Box plugin
	 */
	function __construct() {

		add_action( 'init', array( $this, 'action_init' ) );
		add_action( 'admin_init', array( $this, 'action_admin_init' ) );

	}
	
	/**
	 * Load our options and add our content filter
	 */
	function action_init() {
		$this->options = get_option( $this->options_group_name );

		$this->search_tokens = apply_filters( 'pab_search_values', $this->search_tokens );

		if ( !is_admin() )
			add_filter( 'the_content', array( $this, 'filter_the_content' ) );
		else 
			add_action( 'admin_menu', array( $this, 'add_admin_menu_items' ) );
	}
	
	/**
	 * Activate or upgrade the plugin if we need to; set up our settings page
	 */
	function action_admin_init() {
		// Install our options if we need to.
		if ( !isset( $this->options['activated_once'] ) || $this->options['activated_once'] != 'on' )
			$this->activate_plugin();
		// Only upgrade if we need to
		if ( version_compare( $this->options['version'], POSTAUTHORBOX_VERSION, '<' ) )
			$this->upgrade();

		$this->register_settings();
	}
	
	/**
	 * Default settings for when the plugin is activated for the first time
	 */ 
	function activate_plugin() {
		$options = $this->options;
		// Something might be wrong if we've already activated the plugin
		if ( $options['activated_once'] == 'on' )
			return;

		$options['activated_once'] = 'on';
		$options['position']['prepend'] = 'off';			
		$options['position']['append'] = 'on';
		$options['version'] = POSTAUTHORBOX_VERSION;
		$options['display_configuration'] = '<p>Contact %display_name% at <a href="mailto:%email%">%email%</a></p>';
		foreach ( $this->supported_views as $supported_view ) {
			if ( $supported_view == 'post' )
				$options['apply_to_views'][$supported_view] = 'on';
			else
				$options['apply_to_views'][$supported_view] = 'off';
		}
		update_option( $this->options_group_name, $options );
		$this->options = $options;
	}
	
	/**
	 * Run an upgrade if we need it
	 */
	function upgrade() {
		$options = $this->options;
		
		// Upgrade prior versions of the plugin to v1.1
		if ( version_compare( $options['version'], '1.1', '<' ) ) {
			// Move the 'enabled' option if set
			if ( isset( $options['enabled'] ) ) {
				if ( $options['enabled'] == 1 ) {
					$options['position'][] = 'prepend';
				} else if ( $options['enabled'] == 2 ) {
					$options['position'][] = 'append';					
				}
				unset( $options['enabled'] );
			}
			// Move the 'apply_to' option if set
			if ( isset( $options['apply_to'] ) ) {
				if ( $options['apply_to'] == 1 ) {
					$options['apply_to_views']['post'] = 'on';
				} else if ( $options['apply_to'] == 2 ) {
					$options['apply_to_views']['page'] = 'on';				
				} else if ( $options['apply_to'] == 3 ) {
					$options['apply_to_views']['post'] = 'on';
					$options['apply_to_views']['page'] = 'on';										
				}
				// Ensure we have values saved regardless for the views to apply to
				foreach ( $this->supported_views as $supported_view ) {
					if ( !isset( $input['apply_to_views'][$supported_view] ) ) {
						$input['apply_to_views'][$supported_view] = 'off';
					}
				}
				unset( $options['apply_to'] );
			}
			$options['version'] = POSTAUTHORBOX_VERSION;
			// Save and reset the global variable
			update_option( $this->options_group_name, $options );
			$this->options = get_option( $this->options_group_name );
		}
		
	}
	
	/**
	 * Any admin menu items we need
	 */
	function add_admin_menu_items() {
		add_submenu_page( 'options-general.php', __( 'Post Author Box Settings', 'post-author-box' ), __( 'Post Author Box', 'post-author-box' ), 'manage_options', 'post-author-box', array( $this, 'settings_page' ) );			
	}
	
	/**
	 * Register all Post Author Box settings
	 */
	function register_settings() {

		register_setting( $this->options_group, $this->options_group_name, array( $this, 'settings_validate' ) );
		add_settings_section( 'post_author_box_default', 'Settings', '__return_false', $this->settings_page );
		add_settings_field( 'enabled', __( 'Enable Post Author Box:', 'post-author-box' ), array( $this, 'settings_enabled_option'), $this->settings_page, 'post_author_box_default' );
		add_settings_field( 'apply_to_views', __( 'Apply to:', 'post-author-box' ), array( $this, 'settings_apply_to_option'), $this->settings_page, 'post_author_box_default' );
		add_settings_field( 'display_configuration', __( 'Display configuration:', 'post-author-box' ), array( $this, 'settings_display_configuration_option'), $this->settings_page, 'post_author_box_default' );	

	}
	
	/**
	 * Where you can configure your Post Author Box
	 */
	function settings_page() {
		?>
		<div class="wrap">
			<div class="icon32" id="icon-options-general"><br/></div>

			<h2><?php _e( 'Post Author Box', 'post-author-box' ); ?></h2>

			<form action="options.php" method="post">

				<?php settings_fields( $this->options_group ); ?>
				<?php do_settings_sections( $this->settings_page ); ?>
				<?php submit_button(); ?>

			</form>
		</div>

	<?php
		
	}

	/**
	 * Setting for whether the post author box is enabled or not
	 */
	function settings_enabled_option() {
		$options = $this->options;
		echo '<p><input type="checkbox" id="prepend" name="' . $this->options_group_name . '[position][prepend]"';
		if ( $options['position']['prepend'] == 'on' ) {
			echo ' checked="checked"';
		}
		echo ' />&nbsp;&nbsp;&nbsp;';
		echo '<label for="prepend">' . __( 'Top of the content', 'post-author-box' ) . '</label></p>';
		echo '<p><input type="checkbox" id="append" name="' . $this->options_group_name . '[position][append]"';
		if ( $options['position']['append'] == 'on' ) {
			echo ' checked="checked"';
		}
		echo ' />&nbsp;&nbsp;&nbsp;';
		echo '<label for="append">' . __( 'Bottom of the content', 'post-author-box' ) . '</label></p>';
	}
	
	/**
	 * Determine whether post author box is applied to a post, page, or both
	 */
	function settings_apply_to_option() {
		$options = $this->options;
		$html_items = array();
		foreach ( $this->supported_views as $supported_view ) {
			$item_html = '<input type="checkbox" id="' . $supported_view . '" name="' . $this->options_group_name . '[apply_to_views][' . $supported_view . ']"';
			if ( $options['apply_to_views'][$supported_view] == 'on' ) {
				$item_html .= ' checked="checked"';
			}
			$item_html .= ' /> <label for="' . $supported_view . '">' . ucfirst( $supported_view ) . '</label>';
			$html_items[] = $item_html;
		}
		echo implode( '&nbsp;&nbsp;&nbsp;', $html_items );
	}
	
	/**
	 * Configure the output of the post author box using tokens
	 */
	function settings_display_configuration_option() {
		$options = $this->options;
		
		echo '<textarea id="display_configuration" name="' . $this->options_group_name . '[display_configuration]"';
		echo ' rows="6" cols="50">' . esc_textarea( $options['display_configuration'] ) . '</textarea><br />';
		echo '<p class="description">' . __( 'Use HTML and tokens to determine the presentation of the author box. Available tokens include:', 'post-author-box' ) . '</p><ul class="description">';
		foreach ( $this->search_tokens as $token ) {
			echo '<li>' . $token . '</li>';
		}
		echo '</ul></p>';

	}
	
	/**
	 * Validation and sanitization on the settings field
	 *
	 * @param array $input Field values from the settings form
	 * @return array $input Validated settings field values
	 */
	function settings_validate( $input ) {
		$options = $this->options;
		
		// Ensure we have values saved regardless for append and prepend
		$options['position']['prepend'] = ( isset( $input['position']['prepend'] ) ) ? 'on' : 'off';
		$options['position']['append'] = ( isset( $input['position']['append'] ) ) ? 'on' : 'off';
		
		// Ensure we have values saved regardless for the views to apply to
		foreach ( $this->supported_views as $supported_view ) {
			$options['apply_to_views'][$supported_view] = ( isset( $input['apply_to_views'][$supported_view] ) ) ? 'on' : 'off';
		}

		// Sanitize input for display_configuration
		$allowable_tags = '<div><p><span><a><img><cite><code><h1><h2><h3><h4><h5><h6><hr><br><b><strong><i><em><ol><ul><blockquote><li>';
		$options['display_configuration'] = strip_tags( $input['display_configuration'], $allowable_tags );
		return $options;
		
	}
	
	/**
	 * Append or prepend the Post Author Box on a post or page
	 *
	 * @param string $the_content Post or page content
	 * @return string $the_content Modified post or page content
	 */
	function filter_the_content( $the_content ) {
		$options = $this->options;
		
		$current_view = false;
		foreach ( $this->supported_views as $supported_view ) {
			if ( $supported_view != 'post' ) {
				$supported_view_conditional = 'is_' . $supported_view;
			} else {
				$supported_view_conditional = 'is_single';
			}
			if ( $supported_view_conditional() ) {
				$current_view = $supported_view;
				break;
			}
		}
		
		// Only process if the functionality is enabled and we should apply it
		if ( $options['apply_to_views'][$current_view] == 'on' ) {
			
			$args = array(
				'echo' => false,
			);
			$post_author_box = post_author_box( $args );
			
			// Append and/or prepend the Post Author Box to the content
			if ( $options['position']['prepend'] == 'on' )
				$the_content = $post_author_box . $the_content;
			if ( $options['position']['append'] == 'on' )
				$the_content .= $post_author_box;
			
		}
		
		return $the_content;
		
	}
	
}

}

global $post_author_box;
$post_author_box = new Post_Author_Box();

/**
 * Use the Post Author Box as a template tag
 *
 * @param array $args Arguments to pass to the Post Author Box
 */
function post_author_box( $args = array() ) {
	global $post_author_box, $post;
	
	$defaults = array(
		'echo' => true,
		'display_configuration' => $post_author_box->options['display_configuration'],
		'author' => $post->post_author,
		'post' => $post,
	);
	
	$args = wp_parse_args( $args, $defaults );
	
	// Get the user object
	$user = get_userdata( $args['author'] );

	// All of the various tokens we support
	$search = $post_author_box->search_tokens;			
	
	// Generate the data we need to output the Post Author Box
	$display_name = $user->display_name;
	$author_link = $user->user_url;
	$author_posts_link = get_author_posts_url( $user->ID );
	$first_name = $user->first_name;
	$last_name = $user->last_name;
	$description = $user->description;
	$email = $user->user_email;
	$avatar = get_avatar( $user->ID );
	$jabber = $user->jabber;
	$aim = $user->aim;
	$post_date = get_the_time( get_option( 'date_format' ), $args['post']->ID );
	$post_time = get_the_time( get_option( 'time_format' ), $args['post']->ID );			
	$post_modified_date = get_the_modified_time( get_option( 'date_format' ), $args['post']->ID );
	$post_modified_time = get_the_modified_time( get_option( 'time_format' ), $args['post']->ID );
	
	// Set the data we're replacing with
	$replace = array(
		'%display_name%' => $display_name,
		'%author_link%' => $author_link,
		'%author_posts_link%' => $author_posts_link,
		'%first_name%' => $first_name,
		'%last_name%' => $last_name,
		'%description%' => $description,
		'%email%' => $email,
		'%avatar%' => $avatar,
		'%jabber%' => $jabber,
		'%aim%' => $aim,
		'%post_date%' => $post_date,
		'%post_time%' => $post_time,
		'%post_modified_date%' => $post_modified_date,
		'%post_modified_time%' => $post_modified_time,
	);
	
	// Allow the user to filter replace values
	$replace = apply_filters( 'pab_replace_values', $replace );

	// Do all of our replacements
	$post_author_box_html = $args['display_configuration'];
	foreach ( $search as $token ) {
		$replace_value = $replace[$token];
		$post_author_box_html = str_replace( $token, $replace_value, $post_author_box_html );
	}
	$post_author_box_html = '<div class="post_author_box">' . $post_author_box_html . '</div>';
	
	// Print or return the Post Author Box based on user's preference
	if ( $args['echo'] ) {		
		echo $post_author_box_html;
	} else {
		return $post_author_box_html;
	}
	
}