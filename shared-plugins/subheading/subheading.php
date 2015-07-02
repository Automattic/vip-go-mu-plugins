<?php
/*
Plugin Name: SubHeading
Plugin URI: http://wordpress.org/extend/plugins/subheading/
Description: Adds the ability to show a subheading for posts, pages and custom post types. To display subheadings place <code>&lt;?php the_subheading(); ?&gt;</code> in your template file. 
Version: 1.7.3
Author: StvWhtly
Author URI: http://stv.whtly.com
*/
if ( ! class_exists( 'SubHeading' ) ) {
	class SubHeading
	{
		/**
		 * User friendly name used to identify the plugin.
		 * @var string
		 */
		var $name = 'SubHeading';
		
		/**
		 * Tag identifier used in database and field names.
		 * @var string
		 */
		var $tag = 'subheading';
		
		/**
		 * Post meta field key name, assigned in the constructor.
		 * @var string
		 */
		var $meta_key = null;
		
		/**
		 * List of options to determine plugin behavior.
		 * @var array
		 */
		var $options = array();
		
		/**
		 * Initiate the plugin by setting the default values and assigning any
		 * required actions and filters.
		 */
		function SubHeading()
		{
			$this->options = apply_filters('subheading_options', $this->get_options() );
			$this->meta_key = '_' . $this->tag;
			if ( is_admin() ) {
				add_action( 'admin_menu', array( &$this, 'meta' ) );
				add_action( 'save_post', array( &$this, 'save' ) );
				add_action( 'admin_init', array( &$this, 'settings_init' ) );
				if ( isset( $this->options['lists'] ) ) {
					add_action( 'admin_enqueue_scripts', array( &$this, 'admin' ), 10, 1 );
				}
				add_filter( 'plugin_row_meta', array( &$this, 'settings_meta' ), 10, 2 );
				register_activation_hook( __FILE__, array( &$this, 'activate' ) );
				$this->upgrade();
			} else {
				add_filter( 'the_title_rss', array( &$this, 'rss' ) );
				add_filter( 'the_subheading', array( &$this, 'build' ), 1 );
				add_filter( 'the_content', array( &$this, 'append' ) );
				if ( isset( $this->options['search'] ) ) {
					add_action( 'posts_where_request', array( &$this, 'search' ) );
				}
			}
		}
		
		/**
		 * Performed only during the activation process.
		 */
		function activate()
		{
			update_option( $this->tag, array( &$this, 'get_options' ) );
		}
		
		/**
		 * Attempt to upgrade from versions < 1.6, when functionality to add
		 * subheadings to any public post type was added.
		 */
		function upgrade()
		{
			if ( isset( $this->options['posts'] ) ) {
				$this->options['post_types'][] = 'post';
				unset( $this->options['posts'] );
				update_option( $this->tag, $this->options );
			}
		}
		
		/**
		 * Get the a list of options to use by the plugin.
		 * 
		 * @return array
		 */
		function get_options()
		{
			$options = get_option( $this->tag, array() );
			if ( empty( $options ) ) {
				$options = array(
					'search' => 1,
					'post_types' => array( 'page' ),
					'rss' => 1,
					'lists' => 1,
					'append' => 1,
					'before' => '<h3>',
					'after' => '</h3>'
				);
			}
			return $options;
		}
		
		/**
		 * Output a subheading based on the given parameters, used by both  
		 * the_subheading and get_the_subheading functions.
		 * 
		 * @return mixed String containing the subheading if returned, or null if echoed.
		 */
		function build( $args )
		{
			extract( $args );
			if ( $value = $this->value( $id ) ) {
				if ( isset( $this->options['tags'] ) ) {
					$value = do_shortcode( $value );
				}
				$subheading = $before . $value . $after;
				if ( $display == true ) {
					echo $subheading;
				} else {
					return $subheading;
				}
			}
			return null;
		}
		
		/**
		 * Determine which post types the subheading should be displayed on.
		 */
		function meta()
		{
			if ( isset( $this->options['post_types'] ) && is_array( $this->options['post_types'] ) ) {
				foreach ( $this->options['post_types'] AS $type ) {
					$this->meta_box( $type );
				}
			}
		}
		
		/**
		 * Add the subheading meta box to the given post type.
		 * 
		 * @param string $type Post type to add the meta box to.
		 */
		function meta_box( $type = 'page' )
		{
			add_meta_box(
				$this->tag . '_postbox',
				$this->name, array( &$this, 'panel' ),
				$type,
				'normal',
				'high'
			);
		}
		
		/**
		 * Display the subheading meta box panel by outputting the 'panel.php'
		 * file, this is repositioned using JavaScript in the reposition option
		 * is set.
		 */
		function panel()
		{
			include_once( 'panel.php' );
		}
		
		/**
		 * Save a subheading value as post meta data when the post is saved.
		 * 
		 * @param int $post_id ID of the post being saved.
		 */
		function save( $post_id )
		{
			global $post_type;
			if ( ! isset( $_POST[$this->tag.'nonce'] ) || ! wp_verify_nonce( $_POST[$this->tag . 'nonce'], 'wp_' . $this->tag ) ) {
				return $post_id;
			}
			$post_type_object = get_post_type_object( $post_type );
			if ( ! current_user_can( $post_type_object->cap->edit_post, $post_id ) ) {
				return $post_id;
			}
			$subheading = wp_kses( $_POST[$this->tag . '_value'], $this->allowed_tags() );
			if ( empty( $subheading ) ) {
				delete_post_meta( $post_id, $this->meta_key, $subheading );
			} else if ( ! update_post_meta( $post_id, $this->meta_key, $subheading ) ){
				add_post_meta( $post_id, $this->meta_key, $subheading, true );
			}
		}
		
		/**
		 * Defines which tags can be used within a subheading, making use of
		 * the subheading_tags filter to allow the tags to be modified.
		 * 
		 * @return array List of valid tags and attributes.
		 */
		function allowed_tags()
		{
			global $allowedtags;
			$tags = $allowedtags;
			return apply_filters( 'subheading_tags', $tags );
		}
		
		/**
		 * Fetches the subheading for the current of given post.
		 * 
		 * @param int|false $id If false, the global post id will be used.
		 * @return string The subheading text value.
		 */
		function value( $id = false )
		{
			$value = get_post_meta( ( $id !== false ? $id : get_the_ID() ), $this->meta_key, true );
			return apply_filters( 'subheading', $value );
		}
		
		/**
		 * If the RSS option is set, append the subheading to the RSS feed post
		 * titles.
		 * 
		 * @param string $title Current post title.
		 * @return string Post title with the subheading appended.
		 */
		function rss( $title )
		{
			if ( isset( $this->options['rss'] ) && $subheading = $this->value()) {
				return $title . ' - ' . esc_html( strip_tags( $subheading ) );
			}
			return $title;
		}
		
		/**
		 * If the auto append option is set, attempt to add the subheading to
		 * the beginning of the post content. This also uses the before and 
		 * after options if they are defined.
		 * 
		 * @param string $content Post content to prepend the value to.
		 * @return string Content with the subheading added.
		 */
		function append( $content )
		{
			if ( $this->is_main_query() && isset( $this->options['append'] ) && $subheading = $this->value() ) {
				if ( isset($this->options['before'] ) && isset( $this->options['after'] ) ) {
					return $this->options['before'] . $subheading . $this->options['after'] . $content;
				}
				return wpautop( $subheading ) . $content;
			}
			return $content;
		}
		
		/**
		 * Allow admin specific functionality to be added, includes adding the
		 * JavaScript and subheadings to post management pages.
		 * 
		 * @param string $hook
		 */
		function admin( $hook )
		{
			if ( in_array( $hook, array( 'edit.php', 'edit-pages.php', 'options-reading.php' ) ) ) {
				wp_enqueue_script( $this->name, plugins_url( $this->tag.'/admin.js' , dirname( __FILE__ ) ) );
				if ( isset( $this->options['post_types'] ) && is_array( $this->options['post_types'] ) ) {
					foreach ( $this->options['post_types'] AS $post_type ) {
						if ( in_array( $post_type, array( 'post', 'page' )) ) {
							$post_type .= 's';
						}
						add_filter( 'manage_'.$post_type.'_columns', array( &$this, 'column_heading' ) );
						add_filter( 'manage_'.$post_type.'_custom_column', array( &$this, 'column_value' ), 10, 2 );
					}
				}
			}
		}
		
		/**
		 * Add the subheading column tp the management lists.
		 * 
		 * @param array $columns List of existing columns
		 * @return array Columns with ne subheading column added.
		 */
		function column_heading( $columns )
		{
			$columns[$this->tag] = $this->name;
			return $columns;
		}
		
		/**
		 * Output the subheading values on the admin management lists.
		 * 
		 * @param string $column ID of the column.
		 * @param int $post_id Post ID of the current row.
		 */
		function column_value( $column, $post_id )
		{
			if ( $column == $this->tag ) {
				$value = $this->build( array(
					'id' => $post_id,
					'before' => '',
					'after' => '',
					'display' => false
				) );
				echo strip_tags( $value );
			}
		}
		
		/**
		 * Initiate the admin setting options, by adding the options management
		 * to the global reading settings page.
		 */
		function settings_init()
		{
			$description = 'Configuration options for the <a href="http://wordpress.org/extend/plugins/' . $this->tag . '/" target="_blank">' . $this->name . '</a> plugin.';
		 	add_settings_field(
		 		$this->tag . '_settings',
				$this->name . ' <div class="description">' . $description . '</div>',
				array(&$this, 'settings_fields'),
				'reading',
				'default'
		 	);
		 	register_setting(
		 		'reading',
		 		$this->tag,
		 		array(&$this, 'settings_validate')
		 	);
		}
		
		/**
		 * Validate the settings defined on the admin settings page.
		 * 
		 * @param array $inputs List of settings passed from the settings upon saved.
		 * @return array Valid settings that should be saves.
		 */
		function settings_validate( $inputs )
		{
			if ( is_array( $inputs ) ) {
				foreach ( $inputs AS $key => $input ) {
					if ( in_array( $key, array( 'before', 'after', 'post_types' ) ) ) {
						if ( 'post_types' == $key ) {
							$post_types = array();
							$settings_post_types = $this->settings_post_types( 'names' );
							foreach ( $inputs[$key] AS $post_type ) {
								if ( in_array( $post_type, $settings_post_types ) ) {
									$post_types[] = $post_type;
								}
							}
							$inputs[$key] = $post_types;
						} else {
							$inputs[$key] = wp_kses( $inputs[$key], $this->settings_allowed_tags() );
						}
						if ( empty( $inputs[$key] ) ) {
							unset( $inputs[$key] );
						}
					} else {
						$inputs[$key] = ( $inputs[$key] == 1 ? 1 : 0 );
					}
				}
				return $inputs;
			}
		}
		
		/**
		 * Defines which tags are allowed to be entered into the settings
		 * fields (Before and After). These can be modified using the
		 * subheading_settings_tags filter.
		 * 
		 * @return array List of allowed tags and attributes.
		 */
		function settings_allowed_tags()
		{
			global $allowedtags;
			$settings_tags = $allowedtags;
			$additional_tags = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p' );
			foreach ( $additional_tags AS $tag ) {
				$settings_tags[$tag] = array(
					'class' => array(),
					'id' => array()
				);
			}
			return apply_filters( 'subheading_settings_tags', $settings_tags );
		}
		
		/**
		 * Add all the options fields to the admins settings page.
		 */
		function settings_fields()
		{
			$fields = array(
				'search' => 'Allow search to find matches based on subheading values.',
				'rss' => 'Append to RSS feeds.',
				'reposition' => 'Prevent reposition of input under the title when editing.',
				'lists' => 'Display on admin edit lists.',
				'tags' => 'Apply shortcode filters.',
				'lists' => 'Display on admin edit lists.',
				'append' => array(
					'description' => 'Automatically display subheadings before post content.',
					'break' => false
				),
				'before' => array(
					'description' => 'Before:',
					'value' => ( array_key_exists( 'before', $this->options ) ? esc_attr( $this->options['before'] ) : '' ),
					'type' => 'text',
					'break' => false,
					'prepend' => true
				),
				'after' => array(
					'description' => 'After:',
					'value' => ( array_key_exists( 'after', $this->options ) ? esc_attr( $this->options['after'] ) : '' ),
					'type' => 'text',
					'prepend' => true
				),
			);
			$post_types = $this->settings_post_types();
			unset( $post_types['attachment'] );
			foreach ( $post_types AS $id => $post_type ) {
				$fields['post_type_'.$id] = array(
					'description' => 'Enable on ' . $post_type->labels->name . '.',
					'name' => $this->tag . '[post_types][]',
					'value' => $id,
					'options' => ( isset( $this->options['post_types'] ) ? $this->options['post_types'] : array() )
				);
			}
			foreach ( $fields AS $id => $field ) {
				if ( ! is_array( $field ) ) {
					$field = array( 'description' => $field );
				}
				if ( ! isset( $field['options'] ) ) {
					$field['options'] = $this->options;
				}
				?>
				<label>
					<?php if ( isset( $field['prepend'] ) && $field['prepend'] === true ) : ?>
					<?php _e( $field['description'] ); ?>
					<?php endif; ?>
					<input name="<?php _e( isset( $field['name'] ) ? $field['name'] : $this->tag . '[' . $id . ']' ); ?>"
						type="<?php _e( isset( $field['type'] ) ? $field['type'] : 'checkbox' ); ?>"
						id="<?php _e( $this->tag . '_' . $id ); ?>"
						value="<?php _e( isset( $field['value'] ) ? $field['value'] : 1 ); ?>"
						<?php if ( is_array( $field['options'] ) && array_key_exists( $id, $field['options'] ) || ( isset( $field['value'] ) && in_array( $field['value'], $field['options'] ) ) )  { echo 'checked="checked"'; } ?> />
					<?php if ( ! isset( $field['prepend'] ) || $field['prepend'] == false ) : ?>
					<?php _e( $field['description'] ); ?>
					<?php endif; ?>
				</label>
				<?php if ( ! isset( $field['break'] ) || $field['break'] === true ) : ?><br /><?php endif; ?>
				<?php
			}
		}
		
		/**
		 * Append a list to the settings page to link to the settings page from
		 * the plugins list.
		 * 
		 * @param array $links List of existing links.
		 * @param string $file Name of the plugin file.
		 * @return array Links containing newly added settings link.
		 */
		function settings_meta( $links, $file )
		{
			$plugin = plugin_basename( __FILE__ );
			if ( $file == $plugin ) {
				return array_merge(
					$links,
					array( '<a href="'.admin_url('options-reading.php').'">Settings</a>' )
				);
			}
			return $links;
		}
		
		/**
		 * Fetch a list of available post types that subheadings can be added.
		 * 
		 * @param string $output Data to return for the post types.
		 * @return array List of available post types.
		 */
		function settings_post_types( $output = 'objects' )
		{
			return get_post_types( array( 'public' => true ), $output );
		}
		
		/**
		 * Allow the subheadings to be searched when performing a search.
		 * 
		 * @param string $where Current where query string.
		 * @return string Modified query.
		 */
		function search( $where )
		{
			if ( is_search() ) {
				global $wpdb, $wp;
				$where = preg_replace(
					"/\({$wpdb->posts}.post_title (LIKE '%{$wp->query_vars['s']}%')\)/i",
					"$0 OR (subheading_postmeta.meta_value $1)",
					$where
				);
				add_filter( 'posts_join_request', array( &$this, 'search_join' ) );
			}
			return $where;
		}
		
		/**
		 * Add the post_meta table to the search query.
		 * 
		 * @param string $join Current join statement.
		 * @return string Modified join.
		 */
		function search_join( $join )
		{
			global $wpdb;
			return $join .= " LEFT JOIN $wpdb->postmeta as subheading_postmeta ON (subheading_postmeta.meta_key = '_{$this->tag}' AND $wpdb->posts.ID = subheading_postmeta.post_id) ";
		}
		
		/**
		 * Check that the current query is the main query.
		 * @return boolean True if we are in the main query, otherwise False.
		 */
		function is_main_query() {
			if ( function_exists( 'is_main_query' ) && is_main_query() ) {
				return true;
			} else {
				global $query, $wp_the_query;
				if ( $query === $wp_the_query ) {
					return true;
				}
			}
			return false;
		}
		
	}
	/**
	 * Create a new SubHeading object.
	 */
	$subHeading = new SubHeading();
	
	/**
	 * Output a subheading value with some basic options.
	 * 
	 * @param string $before Text to output before.
	 * @param string $after Text to output after.
	 * @param boolean $display True to output the value or False for it to be returned.
	 * @param int $id ID of the post to lookup.
	 * @return mixed String if display is True, otherwise null.
	 */
	function the_subheading( $before = '', $after = '', $display = true, $id = false )
	{
		return apply_filters(
			'the_subheading',
			array(
				'before' => $before,
				'after' => $after,
				'display' => $display,
				'id' => $id
			)
		);
	}
	
	/**
	 * Return a subheading value using some basic options.
	 * 
	 * @param int $id Post ID to return the subheading for.
	 * @param string $before Text to output before.
	 * @param string $after Text to output after.
	 * @return string The subheading text value.
	 */
	function get_the_subheading( $id = false, $before = '', $after = '' )
	{
		return apply_filters(
			'the_subheading',
			array(
				'before' => $before,
				'after' => $after,
				'display' => false,
				'id' => $id
			)
		);
	}
	
}
