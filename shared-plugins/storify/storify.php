<?php
/*
Plugin Name: Storify
Plugin URI: https://storify.com
Description: Brings the power of Storify, the popular social media storytelling platform to your WordPress site
Version: 1.0.7
Author: Storify
Author URI: https://storify.com

Modified for WPCOM VIP by Rinat Khaziev <rinat@doejo.com>

License: GPL2
*/

/**
 * Main Storify Class, register's WordPress hooks on construct and provide's plugin's main functionality
 * Should not be constructed more than once per page load.
 * All methods should be globally accessible via `$WP_Storify`, or via WP_Storify::$instance
 * @since 1.0
 * @author Benjamin J. Balter ( ben.balter.com | ben@balter.com )
 */
class WP_Storify {

	public $version             = '1.0.7'; //plugin version
	public $version_option      = 'storify_version'; //option key to store current version
	public $login_meta          = '_storify_login'; //key used to store storify login within usermeta
	public $description_meta    = 'storify_description_added'; //postmeta to store if description has been added
	public $create_url          = 'https://storify.com/create'; //URL to create new story via iframe
	public $callback_query_arg  = 'callback'; //query argument to pass callback url via iframe
	public $permalink_query_arg = 'storyPermalink'; //query arg to look for on callback

	//regex to parse permalinks within posts
	public $permalink_regex = '#https?://(www\.)?storify.com/([A-Z0-9-_]+)/([A-Z0-9-]+)(/)?#i';

	//regex to parse permalink from callback
	//(should be nearly identical to $permalink_regex, but with ^ and $ to prevent other strings
	public $permalink_callback_regex = '#^(https?:)?//(www\.)?storify.com/([A-Z0-9-_]+)/([A-Z0-9-]+)(/)?$#i';

	//embed code, %1$s is username, %2$s is story slug
	public $embed_code = '<script src="//storify.com/%1$s/%2$s.js?header=false&sharing=false&border=false"></script>';

	//link to edit story, %1$s is username, %2$s is story slug
	public $edit_link = 'https://storify.com/%1$s/%2$s/edit';

	//URL to story's json data, %1$s is username, %2$s is story slug
	public $story_json = 'https://api.storify.com/v1/stories/%1$s/%2$s';

	//what elements to retrieve when getting story metadata
	public $story_metadata = array( 'title', 'description', 'status', 'thumbnail', 'shortlink'  );

	//URL to noscript Embed code (will parse begining with <body>)
	public $noscript_embed = 'https://storify.com/%1$s/%2$s.html';

	//Link to story to appened within noscript tags, , %1$s is username, %2$s is story slug, %3$s is story title
	public $noscript_link = '<a href="https://storify.com/%1$s/%2$s.html" target="_blank">View the story "%3$s" on Storify</a>';

	//regex to parse noscript version of story from HTML (because HTML returns with <html>,<body>, and <head> tags)
	public $noscript_regex = '#<body>(.*)</body>#ism';

	//url to get userdata, %s = username
	public $userdata_url = 'https://api.storify.com/v1/users/%s';

	//url to get user's stories, %s = username
	public $userstory_url = 'https://api.storify.com/v1/stories/%s';

	//TTL for transient cache in seconds, default (3600) = 1 HR
	public $ttl = '3600';

	static $instance; //allows plugin to be called externally without re-constructing

	/**
	 * Register hooks with WP Core
	 */
	function __construct() {

		self::$instance = &$this;

		//i18n
		add_action( 'init', array( &$this, 'i18n' ) );

		//call hook to add admin menu to admin sidebar
		add_action( 'admin_menu', array( &$this, 'add_menu' ) );
		add_action( 'admin_bar_menu', array( &$this, 'admin_bar' ), 100 );

		//register storify embed handler and callback
		wp_embed_register_handler( 'storify', $this->permalink_regex, array($this, 'embed_handler' ) );

		//register plugin with tinyMCE (WYSIWYG editor)
		add_action( 'admin_init', array( &$this, 'tinymce_register' ) );

		//enqueue css & js
		add_action( 'admin_print_styles', array( &$this, 'enqueue_style' ) );
		add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_script' ) );
		
		// VIP Customization
		add_action( 'wp_ajax_storify_dialog', array( &$this, 'dialog_button' ) );

		//i18n
		add_action( 'admin_enqueue_scripts', array( &$this, 'localize_scripts' ) );

		//sanitization
		add_filter( 'storify_login', array( &$this, 'sanitize_login' ) );
		add_filter( 'storify_permalink', array( &$this, 'sanitize_permalink' ), 20 );

		//callback handler from storify.com
		add_action( 'admin_title', array( &$this, 'callback_handler' ), 999 );
		add_filter( 'template_include', array( &$this, 'callback_redirect' ) );
		add_filter( 'storify_permalink', array( &$this, 'maybe_add_http' ), 0 );
		add_filter( 'storify_tags', array( &$this, 'hashtag_filter' ), 5 );

		//iframe URL
		add_filter( 'storify_iframe_url', array( &$this, 'iframe_url_edit_post_filter' ) );
		add_filter( 'storify_iframe_url', array( &$this, 'iframe_url_add_callback_filter' ), 100 );

		//metabox
		add_action( 'add_meta_boxes', array( &$this, 'add_metabox' ) );

		//noscript embeds
		add_filter( 'storify_embed', array( &$this, 'noscript_link_embed_filter'), 5, 2 );
		add_filter( 'storify_embed', array( &$this, 'noscript_html_embed_filter'), 6, 2 );

		//description, title, tags
		add_filter( 'wp_insert_post_data', array( &$this, 'maybe_add_description' ) );

		//purge cache on update
		add_action( 'storify_edit', array( &$this, 'cache_purge' ), 10, 1 );

		//upgrade DB
		//add_action( 'admin_init', array( &$this, 'upgrade' ) ); // VIP (2012-11-08): disabled as it kills sites with really large numbers of posts

	}

	/**
	 * Callback function to handle the url to embed code replacement
	 * @param array $matches regex matches
	 * @returns string embed code
	 */
	function embed_handler( $matches, $attr, $url, $rawattr ) {

		$story = $this->get_story( $matches, true );

		//not a valid story, just display raw URL
		if ( !$story )
			return $url;

		//allow other plugins to filter and override our embed code
		return apply_filters( 'storify_embed', $story->embed_code, $matches, $attr, $url, $rawattr );

	}


	/**
	 * Filter to add noscript link alternative to embed code
	 * Implemented as filter so that other plugins / developers can modify behavior more easily
	 * @param string $embed the embed code
	 * @param array $matches regex matches ($matches[0] = permalink)
	 * @return string the modified embed code with noscript alternative
	 */
	function noscript_link_embed_filter( $embed, $matches ) {

		$embed .= $this->get_noscript_link( $matches[0] );
		return $embed;

	}


	/**
	 * Filter to add noscript html alternative to embed code
	 * Implemented as filter so that other plugins / developers can modify behavior more easily
	 * @param string $embed the embed code
	 * @param array $matches regex matches ($matches[0] = permalink)
	 * @return string the modified embed code with noscript alternative
	 */
	function noscript_html_embed_filter( $embed, $matches ) {

		$story = $this->get_story( $matches, true );

		if ( !$story )
			return false;

		$embed .= $story->noscript_html;
		return $embed;

	}


	/**
	 * Given a string containing a storify permalink, returns a story object
	 * @param string|array $permalink string containing story permalink, or array of already parsed components
	 * @param bool $extended whether to include noscript embed code, title, and description (makes additional DB/http calls)
	 * @return object|bool story object or false if not a story
	 */
	function get_story( $story, $extended = false ) {

		//if we are given an array (e.g., from embed handler), don't waste time by re-regexing
		if ( is_array( $story ) )
			$matches = $story;

		//return false if permalink does not match regex (aka no permalink found in string)
		else if ( !preg_match( $this->permalink_regex, $story, $matches ) )
				return false;

			$cache_key = 'story_' . md5( $matches[0] );

		//Even if only basic is requested, if extended is cached, function will return extended
		//Under most installs this will be non-persistant, but can exploit APC, etc. if configured
		if ( $cache = wp_cache_get( $cache_key, 'storify' ) )
			return $cache;

		$output = array(    'permalink' => esc_url( $matches[0] ),
			'edit_link' => esc_url( sprintf(
					$this->edit_link,
					$matches[2],
					$matches[3]
				) ),
			'user' => $matches[2],
			'slug' => $matches[3],
			'embed_code' => sprintf(
				$this->embed_code,
				esc_attr( $matches[2] ),
				esc_attr( $matches[3] )
			),
		);

		//only basic data requested, save the calls
		if ( !$extended )
			return apply_filters( 'story_object', (object) $output, $extended, $matches, $story );

		//noscript embed code
		$output['noscript_html'] = $this->get_noscript_html( $matches[0] );

		//get story metadata, or if not possible, return false
		if ( $metadata = $this->get_story_metadata( $matches[0] ) )
			$output = array_merge( $output, $metadata );
		else
			return false;

		$output = apply_filters( 'story_object', (object) $output, $extended, $matches, $story );

		wp_cache_set( $cache_key, $output, 'storify', apply_filters( 'storify_ttl', $this->ttl, 'get_story' ) );

		return $output;
	}


	/**
	 * Filters hashtags from list of story tags
	 * @param object $story the story
	 * @return object the modified story
	 */
	function hashtag_filter( $tags ) {

		if ( !is_array( $tags ) )
			return $tags;

		foreach ( $tags as &$tag )
			$tag = str_replace( '#', '', $tag );

		return $tags;

	}


	/**
	 * Queries the Storify API for user data
	 * @param string $user the username
	 * @return object|bool json decoded object of response, false on failure
	 * @uses api_query()
	 */
	function get_user_data( $user = null ) {

		if ( $user == null )
			$user = $this->get_login( );

		if ( !$user )
			return false;

		$url = sprintf( $this->userdata_url, $user );

		return $this->api_query( $url );

	}


	/**
	 * Queries the Storify API for a user's recent stories
	 * @param string $user the username
	 * @return object|bool json decoded object of response, false on failure
	 * @uses api_query()
	 */
	function get_user_stories( $user = null ) {

		if ( $user == null )
			$user = $this->get_login( );

		if ( !$user )
			return false;

		$url = sprintf( $this->userstory_url, $user );

		//don't cache because we always want the freshest list of stories possible
		return $this->api_query( $url, true, 0 );
	}


	/**
	 * Retrieves information about an individual story
	 * @param string $permalink the story permalink
	 * @return array the json decoded data
	 * @uses get_story()
	 * @uses api_query()
	 */
	function get_story_data( $permalink ) {

		$story = $this->get_story( $permalink );

		if ( !$story )
			return;

		$url = sprintf( $this->story_json, $story->user, $story->slug );

		return $this->api_query( $url );

	}


	/**
	 * Retrieves story metadata
	 * @param string $permalink the story permalink
	 * @return array the story's metadata
	 * @uses get_story_data
	 * @uses $story_metadata
	 */
	function get_story_metadata( $permalink ) {

		$data = $this->get_story_data( $permalink );

		if ( !$data )
			return false;

		$output = array();

		foreach ( $this->story_metadata as $meta )
			$output[$meta] = ( isset( $data->content->$meta ) ) ? $data->content->$meta : null;

		//hashtags are a subobject of meta, query directly
		$output['hashtags'] = ( isset( $data->content->meta->hashtags ) ) ? $data->content->meta->hashtags : null;
		$output['hashtags'] = apply_filters( 'storify_tags', $output['hashtags'], $permalink );

		return $output;

	}


	/**
	 * Retrieves static (HTML only) version of stories and wraps with noscript tags for SEO purposeses
	 * @param string $story the story permalink
	 * @return string the HTML to embed
	 */
	function get_noscript_html( $story ) {

		$data = $this->get_html( $story );

		//cannot parse noscript from HTML
		if ( preg_match( $this->noscript_regex, $data, $matches ) == false )
			return false;

		$output = '<noscript>';
		$output .= $matches[1];
		$output .= '</noscript>';

		$output = apply_filters( 'storify_noscript_html', $output, $story, $data );

		return $output;

	}


	/**
	 * Returns noscript link to story
	 * @param string $permalink the story permalink
	 * @return string the link, formatted HTML
	 * @uses get_story
	 */
	function get_noscript_link( $permalink ) {

		$story = $this->get_story( $permalink, true );

		if ( !$story )
			return false;

		$link = '<noscript>';
		$link .= sprintf( $this->noscript_link, $story->user, $story->slug, $story->title );
		$link .= '</noscript>';

		$link = apply_filters( 'storify_noscript_link', $link, $permalink, $story );

		return $link;

	}


	/**
	 * Returns the HTML equivalent of a story
	 * @param string $permalink the story permalink
	 * @return string the complete story HTML, including <html>, <head>, and <body> tags.
	 */
	function get_html( $permalink ) {

		$story = $this->get_story( $permalink );

		if ( !$story )
			return;

		$url = sprintf( $this->noscript_embed, $story->user, $story->slug );

		$data = $this->api_query( $url, false );

		return $data;

	}


	/**
	 * Performs an API query, handles errors, and json decodes the data
	 * @param string $query the url to query
	 * @param bool $decode whether to json_decode the data
	 * @param int $ttl cache time for data
	 * @return array the json decoded data
	 */
	function api_query( $query, $decode = true, $ttl = null ) {

		if ( $ttl == null )
			$ttl = apply_filters( 'storify_ttl', $this->ttl, 'api_query' );


		$cache_key = 'storify_api_' . md5( $query );

		if ( $data = get_transient( $cache_key ) )
			return ( $decode ) ? json_decode( $data ) : $data;

		$data = wp_remote_get( $query );

		if ( is_wp_error( $data ) )
			return false;

		$data = wp_remote_retrieve_body( $data );

		//if it's plain HTML, cache the raw HTML and return, no need for additional checks
		if ( !$decode ) {
			set_transient( $cache_key, $data, (int) $ttl );
			return $data;
		}

		//if it's JSON, verify that it's valid and that it is not an error before caching/returning
		$decoded = json_decode( $data );

		if ( !$decoded || isset( $decoded->error ) )
			return false;

		set_transient( $cache_key, $data, (int) $ttl );

		return $decoded;

	}


	/**
	 * Gets a user's receny stories
	 * @param string $user the username
	 * @return object|bool json decoded object of response, false on failure
	 */
	function get_recent_stories( $user = null ) {

		$data = $this->get_user_stories( $user );

		if ( !$data )
			return false;

		return $data->content->stories;

	}


	/**
	 * Retrieves user's login from usermeta, if any
	 * @param int $userid the user's ID
	 * @return string|bool the user's login, false on failure
	 */
	function get_login( $userid = null ) {

		if ( $userid == null )
			$userid = get_current_user_id();
		//  use user_attribute on WPCOM 
		return apply_filters( 'storify_login', get_user_attribute( $userid,  $this->login_meta, $userid ), $userid );

	}


	/**
	 * Callback to handle the insert story dialog iframe
	 */
	function insert_story_dialog() { ?>
		<div class="wrap" id="storyDialog">
			<?php screen_icon(); ?>
			<h2><?php _e( 'Insert Story', 'storify' ); ?></h2>
			<?php do_action( 'pre_storify_dialog' ); ?>
			<div id="storyList">
				<?php
		foreach ( $this->get_recent_stories( ) as $row => $story ) { ?>
				<div class="story <?php if ( $row > 4 ) echo "hidden"; ?>" id="row<?php echo $row; ?>">
					<div class="title"><?php echo $story->title; ?></div>
					<div class="description"><?php echo $story->description; ?></div>
					<div class="permalink"><?php echo $story->permalink; ?></div>
					<div class="insertLink"><a href="#"<?php if ($row == 0) echo ' class="mceFocus"';?>>Insert</a></div>
				</div>
				<?php } ?>
			</div>
			<div id="moreLink">
				<a href="#"><?php _e( 'Older Stories', 'storify' ); ?></a>
			</div>
			<div id="logout">
				<?php echo sprintf( __( 'Showing recent stories created by %s.', 'storify' ), $this->get_login() ); ?>
				<a href="#" id="storifyLogout"><?php _e( 'Change account?', 'storify' ); ?></a>
			</div>
			<form id="logoutForm" method="post">
				<?php wp_nonce_field( 'storify_login' ); ?>
				<input type="hidden" name="login" value="0" />
			</form>
			<?php do_action( 'post_storify_dialog' ); ?>
		</div>
		<?php
	}


	/**
	 * Callback to handle the login dialog
	 */
	function login_dialog() { ?>
		<div class="wrap storifyLogin" id="storyDialog">
			<h2><?php _e( 'Storify Login', 'storify' ); ?></h2>
			<p><?php _e( 'Please enter your Storify login to continue', 'storify' ); ?></p>
			<?php do_action( 'pre_storify_login' ); ?>
			<form method="post">
				<?php wp_nonce_field( 'storify_login' ); ?>
				<label for="login" class="screen-reader-text"><?php _e( 'Storify Login:', 'storify' ); ?></label>
				<input type="text" name="login" />
				<input type="submit" class="button-primary" value="Save" />
				<p><?php _e( 'Don\'t have a login? <a href="https://storify.com" target="_blank">Sign up for free</a>.', 'storify' ); ?></p>
			</form>
			<?php do_action( 'post_storify_login' ); ?>
		</div>
	<?php }


	/**
	 * Sanitizes user's login prior to saving
	 * @param string $login the raw login
	 * @return string the sanitized login
	 */
	function sanitize_login( $login ) {

		return preg_replace( '/([^A-Z0-9_])/i', '', (string) $login );

	}


	/**
	 * Listens for permalink_query_arg and inserts into new post
	 * note: being run on admin_title hook because must be afer $post is established
	 * but before headers are sent so it can redirect
	 */
	function callback_handler( $title ) {

		global $post;
		global $pagenow;

		if ( !$post || !$pagenow || !isset( $_GET[ $this->permalink_query_arg ] ) )
			return $title;

		//cap check
		if ( !current_user_can( 'edit_posts' ) )
			return $title;


		$permalink = apply_filters( 'storify_permalink', $_GET[ $this->permalink_query_arg ] );

		do_action( 'storify_edit', $permalink, $post->ID, $post );

		//updating an existing post, no need to continue
		if ( $pagenow != 'post-new.php' )
			return $title;

		//make API call to get story title and description
		$story = $this->get_story( $permalink, true );

		//API call failed, can't continue
		if ( !$story )
			wp_die( __( 'An error occurred while publishing the story', 'storify' ) );

		//the returned permalink is valid, the given may not be
		$post->post_content = apply_filters( 'storify_callback_permalink', $story->permalink, $story, $post->ID );

		//api call failed, don't cause errors
		if ( isset( $story->title ) ) {

			$post->post_title = $story->title;
			$post->post_name = $story->title;

		}

		//set tags if we've got 'em and this is a post
		if ( $post->post_type == 'post' && !empty( $story->hashtags ) && apply_filters( 'storify_add_tags', true, $story, $post ) )
			wp_set_post_tags( $post->ID, $story->hashtags );

		//save changes
		wp_update_post( $post );
		$msg = 1;

		//user can publish, publish and set proper message
		//note: external devs can filter storify_auto_publish, returning false, to prevent publishing
		if ( current_user_can( 'publish_posts' ) && apply_filters( 'storify_auto_publish', true ) ) {
			wp_publish_post( $post->ID );
			$msg = 6;
		}

		do_action( 'storify_publish', $permalink, $post->ID, $post, $msg );

		//we just published or drafted, so redirect user to post.php, not post-new, and add proper message
		wp_redirect( admin_url( 'post.php?post=' . $post->ID . '&action=edit&message=' . $msg ) );
		exit();
	}


	/**
	 * Clear object and API cache on story update so subsequent events are based on the most recent data
	 * @param string $permalink the story permalink
	 * @param bool $prime whether to prime the cache after flushing
	 * @uses get_story()
	 */
	function cache_purge( $permalink, $prime = true ) {

		$story = $this->get_story( $permalink );

		if ( !$story )
			return false;

		//object cache ( result of get_story() )
		wp_cache_delete( 'story_' . md5( $permalink ), 'storify' );

		//API caches ( used by get_story_metadata() and get_noscript_html() )
		$noscript_url = sprintf( $this->noscript_embed, $story->user, $story->slug );
		delete_transient( 'storify_api_' . md5( $noscript_url ) );

		$metadata_url = sprintf( $this->story_json, $story->user, $story->slug );
		delete_transient( 'storify_api_' . md5( $metadata_url ) );

		if ( !$prime )
			return;

		//prime the cache now, rather than making the API calls on a user's first page load
		$this->get_story( $permalink, true );

	}


	/**
	 * On post save, conditionally prepends permlaink with story description
	 * Used because by default, embed code excludes description
	 * Implemented as a filter to allow developers to short-circuit if necessary
	 * @param array $post the post array
	 * @return array $post the post array
	 * @since 1.0.4
	 */
	function maybe_add_description( $post_arr ) {
		global $post;

		if ( wp_is_post_revision( $post_arr) )
			return $post_arr;

		if ( !$this->is_storify_post( $post_arr ) )
			return $post_arr;

		//post array does not have ID, but global $post should
		if ( get_post_meta( $post->ID, $this->description_meta, true ) )
			return $post_arr;

		$story = $this->get_story( $post_arr['post_content'], true );

		//gracefully die if there was an API error
		if ( !isset( $story->description ) || !$story->description )
			return $post_arr;

		$permalink = '<p>' . $story->description . '</p>' . "\r\n" . $story->permalink;

		//put description immediately before permalink in case post has other content
		$post_arr['post_content'] = str_replace( $story->permalink, $permalink, $post_arr['post_content'] );

		if ( empty( $post_arr['post_excerpt'] ) )
			$post_arr['post_excerpt'] = $story->description;

		//post array does not have ID, but global $post should
		update_post_meta( $post->ID, $this->description_meta, true );

		return $post_arr;

	}


	/**
	 * If WordPress 404's, try manually finding ther query arg in the URL and redirect
	 *
	 * The problem is that apache urldecodes the permalink prior to passing to mod-rewrite
	 * Thus, the http:// breaks the URL and causes WP to 404 out
	 * Therefore, WP cannot process the permalink callback on certain configurations
	 * On 404, this manually takes the entire query, URL decodes it, and looks for the query arg
	 * If the query arg is found, the http:// is removed, and the user is redirected to post-new
	 *
	 * See generally, http://fgiasson.com/blog/index.php/2006/07/19/hack_for_the_encoding_of_url_into_url_pr/,
	 * and http://stackoverflow.com/questions/3235219/urlencoded-forward-slash-is-breaking-url
	 *
	 * The function hooks into template redirect (prior to the 404 being served), and
	 * transparently returns the original template if the query arg is not found
	 *
	 * @param string $template the default template
	 * @return string the template
	 * @uses $permalink_query_arg
	 */
	function callback_redirect( $template ) {

		//not a 404, kick
		if ( !is_404() )
			return $template;

		//url decode entire query b/c $_GET will be empty
		$url = urldecode( $_SERVER['REQUEST_URI'] );

		//location of first question mark
		$qpos = strpos( $url, '?' );

		//look for query arg in query
		if ( $qpos === false || stripos( $url, $this->permalink_query_arg ) === false )
			return $template;

		//split query arg at first ? and parse
		$url = substr( $url, $qpos + 1 );
		$args = wp_parse_args( $url );

		//if our query arg isn't a query arg, return
		if ( !array_key_exists( $this->permalink_query_arg, $args ) )
			return $template;

		//strip http:// from permalink
		$permalink = str_replace( 'http://', '', $args[ $this->permalink_query_arg ] );

		//301 redirect to self, but without http:// in query arg
		wp_redirect( add_query_arg( $this->permalink_query_arg, $permalink, admin_url( 'post-new.php' ) ), 301 );
		exit();

	}


	/**
	 * Prepends http:// to a URL if not already there
	 */
	function maybe_add_http( $url ) {

		if ( strpos( $url, '//' ) !== false )
			return $url;

		return '//' . $url;

	}


	/**
	 * Sanitizes permalink from callback
	 * @param string $permalink the permalink
	 * @return string the sanitized peramlink
	 */
	function sanitize_permalink( $permalink ) {

		//verify permalink is nothing but the permalink
		if ( preg_match( $this->permalink_callback_regex, $permalink ) != 1 )
			return;

		return $permalink;

	}


	/**
	 * Looks for storify permalink within a post
	 * @param int|obj $post the post
	 * @return string|bool permalink if storify post, otherwise false
	 */
	function is_storify_post( $post = null ) {

		//if no post is given, grab the global object
		if ( $post == null )
			global $post;

		//support post arrays for save/update callbacks
		if ( is_array( $post ) )
			$post = (object) $post;

		//if post is given as an int, grab the obj
		if ( !is_object( $post ) )
			$post = get_post( $post );

		//post doesn't exist
		if ( !$post )
			return false;

		if ( preg_match( $this->permalink_regex, $post->post_content ) )
			return true;

		return false;

	}


	/**
	 * Conditionally adds metabox to storify posts
	 */
	function add_metabox() {

		if ( !$this->is_storify_post() )
			return;

		global $post;
		if ( !$post )
			return; //prevent errors

		add_meta_box( 'storify', 'Storify', array( &$this, 'metabox' ), $post->post_type, 'side', 'high' );

	}


	/**
	 * Callback to display metabox on post edit screen
	 */
	function metabox( $post ) {
?>
 		<a href="<?php echo add_query_arg( 'post', $post->ID, admin_url( 'admin.php?page=storify' ) ); ?>">Edit Story</a>
 		<?php
	}


	/**
	 * Returns edit link for story given a post
	 * @param int|obj $post the post
	 * @return string|bool either the link or false on failure
	 */
	function get_edit_link( $post ) {

		//if no post is given, grab the global object
		if ( $post == null )
			global $post;

		//if post is given as an int, grab the obj
		if ( !is_object( $post ) )
			$post = get_post( $post );

		//post doesn't exist
		if ( !$post )
			return false;

		if ( !$this->is_storify_post( $post ) )
			return;

		$story = $this->get_story( $post->post_content );
		return apply_filters( 'storify_edit_link', $story->edit_link, $story->user, $story->slug, $post->ID );

	}


	/**
	 * If user can add posts, append link to add new story to new content menu,
	 * If post contains a story, and user can edit, add link to edit story under the edit post admin bar menu
	 */
	function admin_bar() {
		global $wp_admin_bar;
		global $post;

		if ( !is_admin_bar_showing() )
			return;

		if ( current_user_can( 'edit_posts' ) )
			$wp_admin_bar->add_menu( array(
					'parent'=> 'new-content',
					'id'    => 'storify-add',
					'title' => __( 'Storify', 'storify' ),
					'href'  => admin_url( 'admin.php?page=storify' ),
				) );

		if ( !is_single() )
			return;

		if ( !current_user_can( 'edit_post', $post->ID ) )
			return;

		if ( !$this->is_storify_post() )
			return;

		if ( $post->post_author != get_current_user_id() && !current_user_can( 'edit_others_posts' ) )
			return;

		$wp_admin_bar->add_menu( array(
				'parent'=> 'edit',
				'id'    => 'storify-edit',
				'title' => __( 'Edit Story', 'storify' ),
				'href'  => add_query_arg( 'post', $post->ID, admin_url( 'admin.php?page=storify' ) ),
			) );

	}


	/**
	 * Adds Storify admin menu to sidebar, registers admin_panel as output callback
	 */
	function add_menu() {
		add_menu_page( __( 'Storify', 'storify' ), __( 'Storify', 'storify' ), 'edit_posts', 'storify', array($this, 'admin_panel'), plugins_url( 'img/logo.png', __FILE__ ) );
	}


	/**
	 * Callback function to output admin menu and iframe
	 */
	function admin_panel() {
		$url = apply_filters( 'storify_iframe_url', $this->create_url );
?>
		<div class="wrap">
			<?php do_action( 'pre_storify_iframe', $url ); ?>
			<iframe id="storify" src="<?php echo $url; ?>"></iframe>
			<?php do_action( 'post_storify_iframe', $url ); ?>
		</div>
	<?php }


	/**
	 * Modifies iframe behavior to point to edit URL when postID is passed as query arg
	 * @param string $url the standard URL
	 * @return string the modified url
	 */
	function iframe_url_edit_post_filter( $url ) {

		if ( isset( $_GET['post'] ) && $this->is_storify_post( $_GET['post'] ) )
			return $this->get_edit_link( $_GET['post'] );

		return $url;
	}


	/**
	 * Adds callback to iframe URL
	 * @param string $url the URL
	 * @return string the URL with the callback
	 */
	function iframe_url_add_callback_filter( $url ) {

		$url = add_query_arg( 'hideBackToProfileBtn', 'true', $url );

		//editing an existing story, callback should be to edit post, passing the post ID
		//note, we append an & to the end, because storify begins its callback with ?
		//and otherwise the URL would break
		if ( isset( $_GET['post'] ) && $this->is_storify_post( $_GET['post'] ) )
			$callback = admin_url( 'post.php?post=' . (int) $_GET['post'] . '&action=edit&' );

		//new story, callback to post new
		else
			$callback = admin_url( 'post-new.php');

		$url = add_query_arg( $this->callback_query_arg, urlencode( $callback ), $url);

		return $url;

	}


	/**
	 * Adds hooks to register plugin with tinyMCE
	 */
	function tinymce_register() {

		if ( !current_user_can('edit_posts') && !current_user_can('edit_pages') )
			return;
		
		if ( get_user_attribute( get_current_user_id(), 'rich_editing' ) == 'true' && user_can_richedit()) {
			add_filter( 'mce_external_plugins', array( &$this, 'add_tinymce_plugin' ) );
			add_filter( 'mce_buttons', array( &$this, 'add_tinymce_button' ) );
		}
		
		add_action('wp_ajax_dialog_button', array( &$this, 'dialog_button' ) );

	}
	
	/**
	 * rinatkhaziev: This is modified version of dialog.php
	 *
	 */
	function dialog_button() {
		define( 'IFRAME_REQUEST' , true );
		if ( 	!current_user_can( 'edit_posts' ) )
			wp_die( __("You are not allowed to be here") ); //native WP string, no need to i18n
			
		@header('Content-Type: ' . get_option('html_type') . '; charset=' . get_option('blog_charset'));

		wp_enqueue_script( 'tiny_mce_popup.js', includes_url( 'js/tinymce/tiny_mce_popup.js' ) );	
			
		if ( 	isset( $_POST['login'] ) 
				&& isset( $_POST['_wpnonce'] ) 
				&& wp_verify_nonce( $_POST['_wpnonce'], 'storify_login' ) 
			) {
			
			$login = apply_filters( 'storify_login', $_POST['login'] );
			if ( $login )
				update_user_attribute( get_current_user_id(), $this->login_meta, $login );
			else
				delete_user_attribute( get_current_user_id(), $this->login_meta );
			
		}
		
		$callback = ( $this->get_login() ) ? 'insert_story_dialog' : 'login_dialog';
		
		wp_iframe( array( &$this, $callback ) );
		exit;
	}

	/**
	 * Callback to filter tinyMCE button array and insert storify button
	 */
	function add_tinymce_button( $buttons ) {

		if ( !$this->should_enqueue() )
			return $buttons;

		array_push( $buttons, "separator", "storify" );
		return $buttons;
	}


	/**
	 * Callback to register plugin with tinyMCE
	 */
	function add_tinymce_plugin( $plugins ) {

		if ( !$this->should_enqueue() )
			return $plugins;

		$suffix = ( WP_DEBUG ) ? '.dev' : '';
		$plugins['storify'] = plugins_url( 'js/storify.tinymce' . $suffix . '.js', __FILE__ );
		return $plugins;
	}


	/**
	 * Registers style sheet
	 */
	function enqueue_style() {

		if ( !$this->should_enqueue() )
			return;

		wp_enqueue_style( 'storify', plugins_url( 'css/storify.css', __FILE__ ) );
	}


	/**
	 * Registers Javascript file(s)
	 *
	 * Modified by rinatkhaziev:
	 * Added tiny_mce_popup.js enqueue call from dialog.php
	 */
	function enqueue_script() {

		if ( !$this->should_enqueue() )
			return;

		$suffix = ( WP_DEBUG ) ? '.dev' : '';
		wp_enqueue_script( 'storify', plugins_url( 'js/storify' . $suffix . '.js', __FILE__ ), array( 'jquery'), filemtime( dirname( __FILE__ ) . '/js/storify' . $suffix . '.js' ), true );

	}


	/**
	 * Checks whether storify JS/CSS should be enqueued on the given page
	 * Allows for conditional loading of resources and saves HTTP calls
	 * @since 1.0.2
	 */
	function should_enqueue() {

		//pre 3.1, just enqueue on all admin pages b/c get_current_screen hasn't been invented yet
		if ( !function_exists( 'get_current_screen' ) )
			return true;

		$screen = get_current_screen();

		//screens to enqueue JS on
		$screens = apply_filters( 'storify_screens', array( 'toplevel_page_storify', 'post' ) );

		if ( in_array( $screen->base, $screens ) )
			return true;

		if ( defined( 'IFRAME_REQUEST' ) && IFRAME_REQUEST )
			return true;

		return false;

	}


	/**
	 * JSON encodes localization data for scripts
	 */
	function localize_scripts() {

		$data = array(
			'pluginUrl' => plugins_url( '/', __FILE__ ),
			'desc' => __( 'Insert story from your Storify account', 'storify' ),
			'dialogTitle' => __( 'Storify &mdash; Insert Story', 'storify' ),
		);

		$data['iframe'] = ( defined( 'IFRAME_REQUEST' ) && IFRAME_REQUEST );

		wp_localize_script( 'storify', 'storify', $data );
	}


	/**
	 * Makes the plugin translation friendly
	 * @since 1.0.1
	 */
	function i18n() {
		load_plugin_textdomain( 'storify', null, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}


	/**
	 * Upgrade the database on plugin upgrades
	 */
	function upgrade() {

		$db_version = get_option( $this->version_option );

		if ( $db_version == $this->version )
			return;

		//1.0.4 upgrade
		//loop through all previosly published stories and add post meta
		//prevents description from being added on subsequent updates
		if ( $db_version < '1.0.4' ) {
		
			$posts = get_posts( array( 'numberposts' => -1 ) );
		
			foreach ( $posts as $post ) {
		
				if ( !$this->is_storify_post( $post ) )
					continue;
		
				update_post_meta( $post->ID, $this->description_meta, true );
		
			}
		}
		
		//incremement DB version number
		update_option( $this->version_option, $this->version );

	}


}


$WP_Storify = new WP_Storify();
