<?php /*








THIS PLUGIN IS NOT READY FOR WIDESPREAD USAGE YET!
IT IS STILL BEING TESTED, BUT FEEL FREE TO PLAY WITH IT IN YOUR DEV ENVIRONMENT









**************************************************************************

Plugin Name:  Internacional
Description:  Internationalization for your posts. Multiple languages on one blog. Based on concepts by <a href="http://blog.flickr.com/">Flickr</a>.
Author:       Automattic
Author URI:   http://automattic.com/

**************************************************************************/

class Internacional {
	public $taxonomy_name        = 'internacional_language'; // Bad idea to change this after plugin usage
	public $cookie_name          = 'internacional_language'; // See $use_cookie var below

	private $language_detection   = false; // Attempt to display posts in the user's browser language by default
	private $use_cookie           = false; // Uses a cookie to keep the user's language preference
	private $debug                = false; // Output debug text?

	// These will be set later
	public $default_language     = 0; // Term ID, not an object
	public $fake_all_term;
	public $current_language     = false; // This one's an object
	public $current_url_parts    = array();
	public $real_request_uri;
	public $languages            = array(); // Don't modify this directly
	public $internal_query_count = 0;
	public $disable_query_filter = false;
	public $tax_sql_cache        = array();


	/**
	 * Plugin setup
	 */
	public function __construct() {
		// Requires 3.1+
		if ( ! function_exists( 'get_tax_sql' ) )
			return;

		add_action( 'wp_loaded',                      array( &$this, 'wp_loaded' ) );

		// Add the language to all generated links so we don't have to redirect to add it
		add_filter( 'post_link',                      array( &$this, 'add_language_to_post_link'), 10, 2 );
		add_filter( 'page_link',                      array( &$this, 'add_language_to_post_link'), 10, 2 );
		add_filter( 'year_link',                      array( &$this, 'add_language_to_url' ) );
		add_filter( 'month_link',                     array( &$this, 'add_language_to_url' ) );
		add_filter( 'day_link',                       array( &$this, 'add_language_to_url' ) );
		add_filter( 'feed_link',                      array( &$this, 'add_language_to_url' ) );
		add_filter( 'get_pagenum_link',               array( &$this, 'add_language_to_url' ) );
		add_filter( 'term_link',                      array( &$this, 'add_language_to_url' ) );

		// Prevent redirects to the real current URL (WordPress won't realize it's doing it due to the URL hackery)
		add_filter( 'redirect_canonical',             array( &$this, 'cancel_redirect_to_self' ), 2 );

		// Language changing widget
		add_action( 'widgets_init',                   array( &$this, 'register_sidebar_widget' ) );

		// Admin-only stuff
		if ( is_admin() ) {
			// Meta box stuff for the Write screen in the admin area
			add_action( 'add_meta_boxes',             array( &$this, 'add_meta_boxes' ) );
			add_action( 'save_post',                  array( &$this, 'save_meta_box_results' ), 10, 2 );

			// Additional columns to the posts/pages tables the admin area
			add_filter( 'manage_posts_columns',       array( &$this, 'add_custom_columns' ) );
			add_action( 'manage_posts_custom_column', array( &$this, 'output_custom_columns_content' ), 10, 2 );
			add_filter( 'manage_pages_columns',       array( &$this, 'add_custom_columns' ) );
			add_action( 'manage_pages_custom_column', array( &$this, 'output_custom_columns_content' ), 10, 2 );

			// Register a new settings section
			add_action( 'admin_init',                 array( &$this, 'register_setting_section' ) );

			// Add filter dropdown to manage posts page in the admin area
			add_action( 'restrict_manage_posts',      array( &$this, 'filter_by_language_dropdown' ) );
		}

		// Frontend-only stuff
		if ( ! is_admin() ) {
			// Change the theme's language setting dynamically
			add_filter( 'locale',                     array( &$this, 'change_locale' ), 999 );

			// List out what other languages a post is available in
			// This is temporary and will be replaced with a template function probably
			add_filter( 'the_content',                array( &$this, 'list_translations' ), 99 );

			// Limit posts to the current language on the front end
			add_filter( 'posts_join',                 array( &$this, 'join_in_taxonomy_table' ) );
			add_filter( 'posts_where',                array( &$this, 'limit_where_by_language' ) );
			add_filter( 'getarchives_join',           array( &$this, 'join_in_taxonomy_table' ) );
			add_filter( 'getarchives_where',          array( &$this, 'limit_where_by_language' ) );
			add_filter( 'get_previous_post_join',     array( &$this, 'join_in_taxonomy_table_p' ) );
			add_filter( 'get_previous_post_where',    array( &$this, 'limit_where_by_language' ) );
			add_filter( 'get_next_post_join',         array( &$this, 'join_in_taxonomy_table_p' ) );
			add_filter( 'get_next_post_where',        array( &$this, 'limit_where_by_language' ) );
		}

		// Debug
		if ( $this->debug ) {
			add_filter( 'posts_request',              array( &$this, 'debug_var_dump' ) );
			//add_filter( 'posts_request',              array( &$this, 'debug_query' ) );
			//add_filter( 'wp_redirect',                array( &$this, 'debug_wp_redirect' ) );
		}

		// Store the language using a custom taxonomy
		register_taxonomy(
			$this->taxonomy_name,
			'post',
			array(
				'labels' => array(
					'name'                       => _x( 'Languages', 'plural taxonomy name', 'internacional' ),
					'singular_name'              => _x( 'Language', 'singular taxonomy name', 'internacional' ),
					'search_items'               => __( 'Search Languages', 'internacional' ),
					'popular_items'              => __( 'Popular Languages', 'internacional' ),
					'all_items'                  => __( 'All Languages', 'internacional' ),
					'parent_item'                => __( 'Parent Languages', 'internacional' ),
					'parent_item_colon'          => __( 'Parent Language:', 'internacional' ),
					'edit_item'                  => __( 'Edit Language', 'internacional' ), 
					'update_item'                => __( 'Update Language', 'internacional' ),
					'add_new_item'               => __( 'Add New Language', 'internacional' ),
					'new_item_name'              => __( 'New Language Name', 'internacional' ),
					'separate_items_with_commas' => __( 'Please enter only one language', 'internacional' ),
					'add_or_remove_items'        => __( 'Add or remove languages', 'internacional' ),
					'choose_from_most_used'      => __( 'Choose from the most used languages', 'internacional' ),
				),
				'show_ui' => false, // We'll make our own
			)
		);

		// Set up the languages
		// Since this runs so early (for now), it can't be filtered
		// A better solution will come in a future version
		$this->languages = array(
			'en' => 'English',
			'ca' => 'Catalan',
			'es' => 'Español',
			'de' => 'Deutsch',
			'fr' => 'Français',
		);

		// Create the above languages if they don't exist, but only in the admin area for performance reasons
		if ( is_admin() ) {
			foreach ( $this->languages as $lang_slug => $language ) {
				if ( ! $this->get_term( $lang_slug ) ) {
					$this->create_new_language_term( $language, $lang_slug );
				}
			}
		}

		// Figure out the default language
		if ( ! get_option( 'internacional_default_language' ) || ! $default_language = $this->get_term( intval( get_option( 'internacional_default_language' ) ) ) ) {
			// Get the first language in the languages array to use it as the default
			foreach ( $this->languages as $lang_slug => $language ) {
				break;
			}

			// Get the term for that language, creating it if need be
			if ( $default_language = $this->get_term( $lang_slug ) ) {
				$this->default_language = (int) $default_language->term_id;
			} else {
				if ( ! $default_language = $this->create_new_language_term( $language, $lang_slug ) )
					return; // Hopefully this never happens

				$this->default_language = (int) $default_language['term_id'];
			}

			update_option( 'internacional_default_language', $this->default_language );
		} else {
			$this->default_language = (int) $default_language->term_id;
		}

		// The rest of this stuff is frontend only
		if ( is_admin() )
			return;

		// A fake term to make life easier
		$this->fake_all_term = (object) array(
			'term_id'          => '0',
			'name'             => 'All',
			'slug'             => 'all',
			'term_group'       => '0',
			'term_taxonomy_id' => '0',
			'taxonomy'         => $this->taxonomy_name,
			'description'      => '',
			'parent'           => '0',
			'count'            => '0',
		);

		// URL crazy stuff, see function's phpdoc
		$this->do_url_hackery();
	}

	/**
	 * Stuff that needs to run a bit later
	 */
	public function wp_loaded() {
		// Allow the new text blocks in the admin area to be translated
		load_plugin_textdomain( 'internacional', false, '/internacional/localization' );
	}

	/**
	 * Takes care of all of the URL hackery
	 *
	 * Parses the URL for a language slug
	 * Adds a language slug to the URL if there isn't one
	 * Removes the language slug from the URL internally so WordPress doesn't see it
	 */
	public function do_url_hackery() {
		if ( $this->current_language )
			return false;

		// Figure out the requested URL path relative to the WordPress install
		$this->real_request_uri  = is_ssl() ? 'https://' : 'http://';
		$this->real_request_uri .= $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		$this->real_request_uri = str_replace( home_url( '/' ), '', $this->real_request_uri );

		// Extract the language from the URL
		$urlparts = explode( '/', $this->real_request_uri );

		// Let's try and figure out if the current URL starts with a language slug
		if ( !empty( $urlparts[0] ) ) {
			if ( 'all' == $urlparts[0] ) {
				$this->current_language = $this->fake_all_term;
			} elseif ( $this->current_language = $this->get_term( $urlparts[0] ) ) {
				// Already got all we need out of the if()
			}
		}

		// If there was no language set in the URL, come up with a default
		if ( ! $this->current_language ) {

			// See if the user has a default language stored in a cookie
			if ( $this->use_cookie && ! empty( $_COOKIE[$this->cookie_name] ) && $language = $this->get_term( $_COOKIE[$this->cookie_name] ) )
				$this->current_language = $language;

			// See if the user has a preferred language set in their browser
			if ( ! $this->current_language && $this->language_detection && ! empty( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) && preg_match_all( '/([a-z]{1,8}(?:-[a-z]{1,8})?)(?:;q=([0-9.]+))?/', $_SERVER['HTTP_ACCEPT_LANGUAGE'], $userlangs ) ) {
				foreach ( $userlangs[1] as $userlang_slug ) {
					if ( $userlang = $this->get_term( $userlang_slug ) ) {
						$this->current_language = $userlang;
						break;
					}
				}
			}

			// Default to the default language
			if ( ! $this->current_language )
				$this->current_language = $this->get_term( $this->default_language );

			if ( ! $this->current_language || is_wp_error( $this->current_language ) )
				return;

			// Queue up a redirect to add the language into the URL
			add_action( 'template_redirect', array( &$this, 'add_language_into_url_via_redirect' ) );
		} else {
			// Okay, so there's a language slug in the URL. Now we have to hide it from WordPress.
			array_shift( $urlparts );
			$_SERVER['REQUEST_URI'] = str_replace( ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'], '', home_url( '/' . implode( '/', $urlparts ) ) );
		}

		// Store the current language in a cookie for 30 days
		// TODO: I think this needs fixing (currently WP.com wide I think). Fix domain or use blogid.
		if ( $this->use_cookie )
			setcookie( $this->cookie_name, $this->current_language->slug, time() + 2592000, COOKIEPATH, COOKIE_DOMAIN );

		//$this->debug_var_dump( $this->current_language );
		$this->debug_var_dump( 'INTERNAL REQUEST_URI: ' . $_SERVER['REQUEST_URI'] );
		$this->debug_var_dump( get_locale() );
	}


	/**
	 * Redirects to the current URL but with the language in it
	 */
	public function add_language_into_url_via_redirect() {
		// Temporarily strip the QUERY_STRING
		$request_uri = ( ! empty( $_SERVER['QUERY_STRING'] ) ) ? str_replace( '?' . $_SERVER['QUERY_STRING'], '', $this->real_request_uri ) : $this->real_request_uri;

		// Explode out the URL
		$urlparts = explode( '/', $request_uri );

		// Add in the language
		array_unshift( $urlparts, $this->current_language->slug );

		// Re-build the URL
		$redirect_to = trailingslashit( home_url( '/' . implode( '/', $urlparts ) ) );
		if ( ! empty( $_SERVER['QUERY_STRING'] ) )
			$redirect_to .= '?' . $_SERVER['QUERY_STRING'];

		wp_redirect( $redirect_to );
		exit();
	}


	/**
	 * At times redirect_canonical() will attempt to redirect from the URL it thinks it's at to
	 * the URL that is actually in the address bar. This function prevents those redirect loops.
	 *
	 * @param string $redirect_url The URL that is planned on being redirected to.
	 * @return string|false The $redirect_url value if the redirect is okay, false if it matches the real current URL.
	 */
	public function cancel_redirect_to_self( $redirect_url ) {
		if ( home_url( $this->real_request_uri ) === $redirect_url )
			return false;

		return $redirect_url;
	}

	/**
	 * Adds a language slug into the URL (after the site's root, before the relative URL)
	 *
	 * The language slug will be the post's language if it has one, otherwise it'll be the default language
	 *
	 * @param string $url The existing post URL.
	 * @param object|int $post A post object or ID.
	 * @return string The post URL with a language slug added into it.
	 */
	public function add_language_to_post_link( $url, $post ) {
		if ( ! $post = get_post( $post ) )
			return $url;

		if ( ! $language = $this->get_post_language( $post->ID ) )
			$language = $this->get_term( $this->default_language );

		return $this->add_language_to_url( $url, $language->slug );
	}

	/**
	 * Adds a language slug into a URL (after the site's root, before the relative URL)
	 *
	 * @param string $url A URL.
	 * @param string $language_slug Optional. A language slug. Defaults to the default language.
	 * @return string The modified URL with the language slug in it.
	 */
	public function add_language_to_url( $url, $language_slug = null ) {
		if ( ! $language_slug )
			$language_slug = $this->current_language->slug;

		$url = str_replace( home_url( '/' ), '', $url );
		$urlparts = explode( '/', $url );
		array_unshift( $urlparts, $language_slug );
		$url = trailingslashit( home_url( '/' . implode( '/', $urlparts ) ) );

		return $url;
	}

	/**
	 * Changes the locale setting based on the current post language being displayed
	 *
	 * @param string $locale The current locale.
	 * @return string The modified locale.
	 */
	public function change_locale( $locale ) {
		if ( $this->current_language && $this->current_language !== $this->fake_all_term && ! empty( $this->languages[$this->current_language->slug] ) )
			$locale = $this->current_language->slug;

		return $locale;
	}

	/**
	 * A helper function to determine if the database queries should be altered to limit the shown data to a single language
	 *
	 * @return boolean Whether or not the database queries should be modified
	 */
	public function should_modify_query() {
		global $wp;

		$this->internal_query_count++;

		if (
			   $this->disable_query_filter // Used internally to disable the language filters
			|| ! $this->current_language // No language? Then there's no way we can filter out posts
			|| $this->current_language === $this->fake_all_term // The "all" language means show all posts, so don't filter
			|| is_preview() // Leave previews alone so they'll always work
			|| ( $this->internal_query_count <= 2 && ( ! empty( $wp->query_vars['name'] ) || ! empty( $wp->query_vars['pagename'] ) ) ) // If post or page, skip the first query (lets old no-language items not 404)
		) {
			return false;
		}

		return true;
	}

	/**
	 * Modifies a query by JOIN'ing in the taxonomy tables, but only if needed (determined by Internacional::should_modify_query() )
	 * 
	 * @param string $join The passed existing JOIN query part.
	 * @param string $post_table_name Optional. An alternate name (alias) for the posts database table. Be careful, this isn't validated.
	 * @return string A potentially modified JOIN query part.
	 */
	public function join_in_taxonomy_table( $join, $post_table_name = null ) {
		global $wpdb;

		if ( ! $this->should_modify_query() )
			return $join;

		// Some queries use an alias for the posts database table name
		if ( ! $post_table_name )
			$post_table_name = $wpdb->posts;

		$join .= $this->get_tax_sql_cached( $post_table_name, 'join' );

		return $join;
	}

	/**
	 * Modifies a query by JOIN'ing in the taxonomy tables, but only if needed (determined by Internacional::should_modify_query() )
	 * The previous/next post link queries use "p" instead of the actual posts database table name.
	 * 
	 * @param string $join The passed existing JOIN query part.
	 * @return string A potentially modified JOIN query part.
	 */
	public function join_in_taxonomy_table_p( $join ) {
		return $this->join_in_taxonomy_table( $join, 'p' );
	}

	/**
	 * Modifies the WHERE part of a query by limiting the results to the current language
	 * 
	 * @param string $where The passed existing WHERE query part.
	 * @return string A potentially modified WHERE query part.
	 */
	public function limit_where_by_language( $where ) {
		global $wpdb;

		if ( ! $this->should_modify_query() )
			return $where;

		$where .= $this->get_tax_sql_cached( $wpdb->posts, 'where' );

		return $where;
	}

	/*
	 * A caching wrapper for get_tax_sql()
	 *
	 * @param string $post_table_name The name of the table
	 * @param string $type The query type desired ("where" or "join")
	 * @return array The WHERE and the JOIN query parts. See get_tax_sql().
	 */
	public function get_tax_sql_cached( $post_table_name, $type ) {
		if ( ! empty( $this->tax_sql_cache[$post_table_name] ) )
			return $this->tax_sql_cache[$post_table_name][$type];

		$this->tax_sql_cache[$post_table_name] = get_tax_sql(
			array(
				array(
					'taxonomy' => $this->taxonomy_name,
					'terms' => $this->current_language->term_id,
					'field' => 'term_id',
				),
			),
			$post_table_name,
			'ID'
		);

		return $this->tax_sql_cache[$post_table_name][$type];
	}

	/**
	 * Meta box manipulation for the write screens
	 *
	 * Removes a default taxonomy meta box, replaces it with another one, and adds an additional meta box
	 */
	public function add_meta_boxes() {
		// Already done for the admin area, but let's be really sure (some type of front end UI or something?)
		wp_enqueue_script( 'jquery' );

		foreach ( array( 'post', 'page' ) as $type ) {
			// Language picker
			add_meta_box( 'internacional-language-picker', _x( 'Post Language', 'title of language meta box on write screen', 'internacional' ), array( &$this, 'language_picker_meta_box' ), $type, 'side' );

			// Post relationship picker (mark a post as either being new or a translation of another)
			add_meta_box( 'internacional-relationship-picker', _x( 'Post Relationship', 'title of relationship meta box on write screen', 'internacional' ), array( &$this, 'relationship_picker_meta_box' ), $type, 'normal', 'high' );
		}
	}

	/**
	 * Outputs the contents of the language picker meta box for the write screen
	 */
	public function language_picker_meta_box() {
		global $post;

		$get_terms_args = array( 'hide_empty' => false );

		// Default language
		if ( $default_language = $this->get_term( $this->default_language ) ) {
			$get_terms_args['exclude'] = $this->default_language;
			$default_language = array( $default_language );
		}

		$languages = get_terms( $this->taxonomy_name, $get_terms_args );

		// If there's a default language, push it onto the front of the array
		// It won't appear twice as we excluded it already
		if ( ! empty( $get_terms_args['exclude'] ) )
			$languages = array_merge( $default_language, $languages );

		echo '<p><select name="' . esc_attr( $this->taxonomy_name ) . '_picker" style="width:100%">';
		foreach ( $languages as $language ) {
			echo '<option value="' . esc_attr( $language->term_id ) . '"';
			if ( true === is_object_in_term( $post->ID, $this->taxonomy_name,  $language->term_id ) )
				echo ' selected="selected"';
			echo '>' . esc_html( $language->name ) . '</option>';
		}
		echo '</select></p>';
	}

	/**
	 * Outputs the contents of the relationship picker meta box for the write screen
	 */
	public function relationship_picker_meta_box() {
		global $post;

		$relationship_type = ( get_post_meta( $post->ID, '_internacional_relationship', true ) ) ? 'translation' : 'new';

		?>

		<script type="text/javascript">
			jQuery(document).ready(function($){
				$('#internacional_translation_dropdown').click(function(){
					$('#internacional_translation_radio').attr('checked', 'checked');
				});
			});
		</script>

		<p><label><input type="radio" name="internacional_relationship_type" value="new"<?php checked( 'new', $relationship_type ); ?> /><?php _e( "This is new content. It's not a translation of something else.", 'internacional' ); ?></label></p>

		<p><label><input type="radio" name="internacional_relationship_type" id="internacional_translation_radio" value="translation"<?php checked( 'translation', $relationship_type ); ?> /><?php _e( 'This is a translation of the following existing item:', 'internacional' ); ?></label></p>

		<p>
			<select name="internacional_parent_post" id="internacional_translation_dropdown" style="width:100%">
<?php
				$recent_posts = get_posts( array(
					// get_term() works as expected on WP.org, but for some reason on WP.com the "internacional-" prefix can be missing (weird caching?)
					$this->taxonomy_name => str_replace( 'internacional-internacional-', 'internacional-', 'internacional-' . get_term( $this->default_language, $this->taxonomy_name )->slug ),
					'post_type' => $post->post_type,
					'posts_per_page' => 50,
					'exclude' => $post->ID,
				) );

				foreach ( $recent_posts as $recent_post ) {
					echo '<option value="' . esc_attr( $recent_post->ID ) . '"' . selected( $recent_post->ID, get_post_meta( $post->ID, '_internacional_relationship', true ), false ) . '>' . esc_html( $recent_post->post_title ) . "</option>\n";
				}
?>
			</select>
		</p>

<?php
	}

	/**
	 * Saves the results of the custom meta boxes
	 */
	public function save_meta_box_results( $post_ID, $post ) {

		// For now, we only support posts/pages and non-AJAX updates
		if ( ! in_array( $post->post_type, array( 'post', 'page' ) ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) )
			return;


		# Language selection

		$new_language = 0; // Will short circuit term_exists() to return false if not changed

		// Quick edit with the tag-style language entry box
		// This can allow the setting of multiple languages, pick just the first one
		if ( ! empty( $_POST['tax_input'][$this->taxonomy_name] ) ) {
			$languages = wp_get_object_terms( $post_ID, $this->taxonomy_name, array( 'fields' => 'ids' ) );
			if ( ! empty( $languages[0] ) )
				$new_language = (int) $languages[0];
		}

		// Write screen with custom meta box
		elseif ( ! empty( $_POST[$this->taxonomy_name . '_picker'] ) ) {
			$new_language = (int) $_POST[$this->taxonomy_name . '_picker'];
		}

		if ( ! term_exists( $new_language, $this->taxonomy_name ) )
			$new_language = $this->default_language;

		// This will set the language AND remove any other already set language(s)
		wp_set_object_terms( $post_ID, $new_language, $this->taxonomy_name );


		# Post relationship

		// This meta box will be missing for quick edit, so don't clear an existing value out
		if ( ! empty( $_POST['internacional_relationship_type'] ) ) {
			$parent_post = 0;

			if ( 'translation' == $_POST['internacional_relationship_type'] && !empty( $_POST['internacional_parent_post'] ) )
				$parent_post = (int) $_POST['internacional_parent_post'];

			update_post_meta( $post_ID, '_internacional_relationship', $parent_post );
		}
	}

	/**
	 * Adds new columns on the administrative post listing
	 * 
	 * @param array $columns Existing columns.
	 * @return array An array of columns with the new ones added.
	 */
	public function add_custom_columns( $columns ) {
		// Slice off the date and comments columns
		$end_columns = array_slice( $columns, -2 );
		array_pop( $columns );
		array_pop( $columns );

		// Add the two new columns
		$columns['internacional_language'] = _x( 'Language', 'singular taxonomy name', 'internacional' );
		$columns['internacional_parent']   = __( 'Translation Of', 'internacional' );

		// Add the date and comments columns back
		$columns = array_merge( $columns, $end_columns );

		return $columns;
	}

	/**
	 * Outputs the contents of the custom columns
	 */
	public function output_custom_columns_content( $column_name, $post_ID ) {
		switch ( $column_name ) {
			case 'internacional_language':
				if ( $language = $this->get_post_language( $post_ID ) ) {
					$post = get_post( $post_ID );
					$url = admin_url( 'edit.php' );
					$url = add_query_arg( 'post_type', $post->post_type, $url );
					$url = add_query_arg( $this->taxonomy_name, $language->slug, $url );
					echo '<a href="' . esc_url( $url ) . '">' . esc_html( $language->name ) . '</a>';
				} else {
					echo _x( 'Unknown', 'for the "Language" post column', 'internacional' );
				}
				break;
			case 'internacional_parent':
				$parent_ID = get_post_meta( $post_ID, '_internacional_relationship', true );
				if ( $parent_ID && $parent = get_post( $parent_ID ) ) {
					echo '<a href="' . esc_url( get_edit_post_link( $parent->ID, 'raw' ) ) . '">' . esc_html( $parent->post_title ) . '</a>';
				} else {
					echo _x( 'N/A', 'not applicable, for the "Translation Of" post column', 'internacional' );
				}
				break;
		}
	}

	/**
	 * Adds an additional filter dropdown for language to the manage posts page in the admin area
	 */
	public function filter_by_language_dropdown() {
		$languages = get_terms( $this->taxonomy_name, array( 'hide_empty' => true, 'hierarchical' => false ) );

		echo '<select name="' . esc_attr( $this->taxonomy_name ) . '" id="' . esc_attr( $this->taxonomy_name ) . '" class="postform">' . "\n";
		echo '	<option value="">' . esc_html__( 'View all languages', 'internacional' ) . "</option>\n";

		foreach ( (array) $languages as $language )
			echo '	<option value="' . esc_attr( $language->slug ) . '"' . selected( get_query_var( $this->taxonomy_name ), $language->slug, false ) . '>' . esc_html( $language->name ) . "</option>\n";

		echo "</select>\n";
	}

	/**
	 * Adds a new section to Settings -> Reading
	 */
	public function register_setting_section() {
		// The new section
		add_settings_section(
			'internacional_reading',
			_x( 'Default Post Language', '', 'internacional' ),
			array( &$this, 'settings_section' ),
			'reading'
		);

		// Add a new field to the above section
		add_settings_field(
			'internacional_default_language',
			'<label for="internacional_default_language">' . __( 'Default Post Language', 'internacional' ) . '</label>',
			array( &$this, 'settings_field_language' ),
			'reading',
			'internacional_reading'
		);

		// Tell WordPress to save our setting
		register_setting( 'reading', 'internacional_default_language', 'intval' );
	}

	/**
	 * Outputs the new settings section for selecting the default language
	 */
	public function settings_section() {
		echo '<p>' . __( 'This controls what language should be selected by default on the Write screen and what language posts should be displayed by default on the front end.', 'internacional' ) . '</p>';
	}

	/**
	 * Outputs the new settings section for selecting the default language
	 */
	public function settings_field_language() {
		echo '<select name="internacional_default_language" id="internacional_default_language" class="postform">' . "\n";

		foreach ( $this->languages as $lang_slug => $language ) {
			if ( ! $term = $this->get_term( $lang_slug ) )
				continue;

			echo '	<option value="' . esc_attr( $term->term_id ) . '"' . selected( $this->default_language, $term->term_id, false ) . '>' . esc_html( $language ) . "</option>\n";
		}

		echo "</select>\n";
	}

	/**
	 * Registers a sidebar widget
	 */
	public function register_sidebar_widget() {
		register_widget( 'Internacional_Language_Selection_Widget' );
	}

	/**
	 * Adds a list of other versions of a post to the end of the passed string (i.e. the_content)
	 * 
	 * @param string $content The post content
	 * @return string The post content with a list of available post languages at the end of it
	 */
	public function list_translations( $content ) {
		global $post;

		if ( ! $translations = $this->get_translations( $post->ID ) )
			return $content;

		if ( count( $translations ) < 2 )
			return $content;

		$links = array();

		foreach ( $translations as $language_ID => $translation_post_ID ) {
			if ( ! $language = $this->get_term( $language_ID )->name )
				continue;

			if ( $translation_post_ID == $post->ID )
				$links[$language] = esc_html( $language );
			else
				$links[$language] = '<a href="' . esc_url( get_permalink( $translation_post_ID ) ) . '">' . esc_html( $language ) . '</a>';
		}

		uksort( $links, 'strcasecmp' );

		$translation_links = '<p class="translations-list">' . sprintf( __( 'Read this in: %s', 'internacional' ), implode( ', ', $links ) ) . '</p>';

		return $content . $translation_links;
	}

	/**
	 * Fetches a list of all versions of a post ID
	 * 
	 * @param int $post_ID A post's ID
	 * @return array|false False if the post is not a translation, otherwise an array in the format language term ID => translated post ID
	 */
	public function get_translations( $post_ID ) {
		if ( ! $post = get_post( $post_ID ) )
			return false;

		if ( false === ( $this_post_relationship = get_post_meta( $post->ID, '_internacional_relationship', true ) ) )
			return false;

		// If it's 0, then it's not a translation
		if ( 0 == $this_post_relationship ) {
			$master_post = $post->ID;

			// Add this master post to the list of translations
			if ( ! $language = $this->get_post_language( $post->ID ) )
				return false;
			$translations[$language->term_id] = $post->ID;
		} else {
			if ( ! $master_post = get_post_meta( $post->ID, '_internacional_relationship', true ) ) 
				return false;

			// Add the master post to the list of translations
			// $post->ID will get added below from the get_posts() call
			if ( ! $language = $this->get_post_language( $master_post ) )
				return false;
			$translations[$language->term_id] = $master_post;
		}

		// Get all translations
		$this->disable_query_filter = true;
		$translations_of_this_post = get_posts( array(
			'numberposts'      => -1,
			'post_type'        => $post->post_type,
			'meta_key'         => '_internacional_relationship',
			'meta_value'       => $master_post,
			'suppress_filters' => false,
		) );
		$this->disable_query_filter = false;

		foreach ( $translations_of_this_post as $a_translation ) {
			if ( ! $language = $this->get_post_language( $a_translation->ID ) )
				continue;

			$translations[$language->term_id] = $a_translation->ID;
		}

		return $translations;
	}

	/**
	 * Get the language of a post ID
	 * 
	 * @param int $post_ID A post's ID
	 * @return object|false The language term object for the given post ID or false if the post doesn't have a language value set.
	 */
	public function get_post_language( $post_ID ) {
		$language = wp_get_object_terms( $post_ID, $this->taxonomy_name );
		if ( ! empty( $language[0] ) )
			return $this->strip_slug_prefix( $language[0] );

		return false;
	}

	/**
	 * Fetches a taxonomy term by it's slug or ID. This plugin's term slugs are prefixed to avoid collisions, so this function just makes life easier.
	 *
	 * @param string|int $slug The non-prefixed slug to look for or the term's ID
	 * @return mixed Term Row from database. Will return false if $taxonomy does not exist or $term was not found.
	 */
	public function get_term( $value ) {
		if ( is_int( $value ) )
			$term = get_term( $value, $this->taxonomy_name );
		else
			$term = get_term_by( 'slug', 'internacional-' . $value, $this->taxonomy_name );

		if ( ! $term || is_wp_error( $term ) )
			return false;

		return $this->strip_slug_prefix( $term );
	}

	/**
	 * Strips the prefix out of a slug
	 *
	 * @param string|object $slug Either a full term object or a slug string
	 * @return string|object The passed variable but with the prefix stripped off the slug
	 */
	public function strip_slug_prefix( $slug ) {
		if ( is_object( $slug ) )
			$slug->slug = str_replace( 'internacional-', '', $slug->slug );
		elseif ( is_string( $slug ) )
			$slug = str_replace( 'internacional-', '', $slug );

		return $slug;
	}

	/**
	 * Creates a new language term. This plugin's term slugs are prefixed to avoid collisions, so this function just makes life easier.
	 *
	 * @param string $name The language's name
	 * @param string $slug The non-prefixed slug to create for
	 * @return array|false The Term ID and Term Taxonomy ID or false on error
	 */
	public function create_new_language_term( $name, $slug ) {
		$term = wp_insert_term( $name, $this->taxonomy_name, array( 'slug' => 'internacional-' . $slug ) );

		return ( ! $term || is_wp_error( $term ) ) ? false : $term;
	}

	/**
	 * var_dump() the passed variable. Used for debugging filters.
	 *
	 * @param mixed $var Any variable
	 * @return mixed The passed variable
	 */
	public function debug_var_dump( $var ) {
		if ( $this->debug ) {
			if ( is_string( $var ) && 512 < strlen( $var ) )
				echo '<code>' . esc_html( $var ) . '</code>';
			else
				var_dump( $var );
		}

		return $var;
	}

	/**
	 * Output some text for debugging queries
	 *
	 * @param string $query The query
	 * @return string The exact value passed to the function
	 */
	public function debug_query( $query ) {
		if ( $this->debug ) {
			$this->debug_var_dump( $query );
			var_dump( xdebug_get_function_stack() );
		}

		return $query;
	}

	/**
	 * Output some text about where a redirect attempt was made to for debugging purposes
	 *
	 * @param string $redirect_to The URL that will be redirected to
	 * @return string|null If debug is off then the original URL will be returned, otherwise this function will exit()
	 */
	public function debug_wp_redirect( $redirect_to ) {
		if ( $this->debug ) {
			echo "<p>Redirect: <a href='$redirect_to'>$redirect_to</a></p>";
			var_dump( xdebug_get_function_stack() );
			exit();
		}
	}
}

$internacional = & new Internacional();


/**
 * A widget for outputting the list of all available languages
 */
class Internacional_Language_Selection_Widget extends WP_Widget {

	/**
	 * Register the widget with WordPress
	 */
	public function Internacional_Language_Selection_Widget() {
		$widget_ops = array( 'classname' => 'internacional_language_selection_widget', 'description' => __( 'Allow users to pick their language.', 'internacional' ) );
		$this->WP_Widget( 'internacional_language_selection_widget', __( 'Post Language Selection', 'internacional' ), $widget_ops );
	}

	/**
	 * Output the content of the widget
	 */
	public function widget( $args, $instance ) {
		global $internacional;

		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? 'Blog Language' : $instance['title'], $instance );

		// Widget output
		echo $args['before_widget'];

		if ( !empty( $title ) )
			echo $args['before_title'] . $title . $args['after_title'];


		$get_terms_args = array( 'hide_empty' => true );

		// Default language
		if ( $default_language = $internacional->get_term( $internacional->default_language ) ) {
			$get_terms_args['exclude'] = $internacional->default_language;
			$default_language = array( $default_language );
		}

		$languages = get_terms( $internacional->taxonomy_name, $get_terms_args );

		// If there's a default language, push it onto the front of the array
		// It won't appear twice as we excluded it already
		if ( ! empty( $get_terms_args['exclude'] ) )
			$languages = array_merge( $default_language, $languages );

		// Add "All" to the very beginning
		$languages = array_merge( array( $internacional->fake_all_term ), $languages );

		echo '<p>';
		foreach ( $languages as $language ) {
			$language = $internacional->strip_slug_prefix( $language );

			if ( $language->term_id == $internacional->current_language->term_id )
				echo esc_html( $language->name ) . ' ';
			else
				echo '<a href="' . esc_url( home_url( '/' . $language->slug . '/' ) ) . '">' . esc_html( $language->name ) . '</a> ';
		}
		echo '</p>';


		echo $args['after_widget'];
	}
}

?>