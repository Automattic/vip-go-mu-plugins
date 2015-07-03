<?php
/**
 * Plugin Name: Bit.ly
 * Version: 1.2
 * Author: Micah Ernst, Bradford Campeau-Laurion (Alley Interactive)
 * Description: Uses bit.ly API to get shortened url for a post on publish and saves url as meta data. Based on TIME.com's Bit.ly plugin.
 */
 
if ( defined( 'WP_CLI' ) && WP_CLI )
	require dirname( __FILE__ ) . '/class-wp-cli.php';

class Bitly {
	
	// storing a copy of the api credentials
	var $options;

	// default post types
	var $post_types = array( 'post', 'page' );
	
	/**
	 * Set our options and some hooks
	 */
	function __construct() {
		
		$this->options = $this->get_options();

		add_action( 'admin_menu', array( $this, 'admin_menu') );
		add_action( 'init', array( $this, 'init' ), 99 ); // run later after post_types have been registered
		
		// only hook into the save_post hook if api credentials have been specified
		if( isset( $this->options['api_login'] ) && isset( $this->options['api_key'] ) ) {
			add_action( 'save_post', array( $this, 'save_post' ), 50, 2 );
		}
	}

	/**
	 * Add our post type support and allow post types to be filtered
	 */
	function init() {

		// allow other post types to be supported
		$this->post_types = (array) apply_filters( 'bitly_post_types', $this->post_types );
		
		// default supported post types
		foreach( $this->post_types as $post_type ) {
			add_post_type_support( $post_type, 'bitly' );
		}
	}
	
	/**
	 * Respond to save_post hook and generate a Bitly url
	 *
	 * This happens on save_post rather than publish_post because other plugins
	 * may have dependencies on the Bitly url before the post is published - for example,
	 * Publicize generates the Publicize message on save (regardless of status) and 
	 * relies on Bitly being generated to correctly create the message body
	 *
	 * Bitly link is only generated if it doesn't already exist
	 *
	 * @param int $post_id
	 * @param object $post
	 */
	function save_post( $post_id, $post ) {
		
		if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return;

		if ( ! bitly_is_url_generation_enabled() )
			return;
		
		// only save short urls for the following post types		
		if( !post_type_supports( $post->post_type, 'bitly' ) )	
			return;
		
		// all good, lets make a url
		$this->generate_bitly_url( $post_id );
	}

	/**
	 * Checks the post's status and creates a bitly url if it's publishing for the first time
	 *
	 * @deprecated Deprecated since 1.1, in favor of save_post()
	 * 
	 * @param int $post_id
	 * @param object $post
	 */
	function publish_post( $post_id, $post ) {
		return $this->save_post( $post_id, $post );
	}
	
	/**
	 * Create a bitly url if one doesnt already exist for the passed post id
	 *
	 * @param int $post_id
	 *
	 * @return mixed 
	 */
	function generate_bitly_url( $post_id ) {
		
		extract( $this->options );
		
		$bitly_url = bitly_get_url( $post_id );
		
		if( empty( $bitly_url ) ) {
	
			$permalink = get_permalink( $post_id );
			
			$shortlink = $this->shortlink_for_url( $permalink, $post_id );

			if ( $shortlink )
				update_post_meta( $post_id, 'bitly_url', $shortlink );
		}
		
		return false;
		
	}
	
	/**
	 * Create a bitly url if one doesnt already exist for the current blog
	 *
	 * @return mixed 
	 */
	function generate_bitly_blog_url() {
		extract( $this->options );
		
		$bitly_blog_url = bitly_get_blog_url();
		
		if( empty( $bitly_blog_url ) ) {
			$shortlink = $this->shortlink_for_url( home_url() );

			if ( $shortlink )
				update_option( 'bitly_siteurl', $shortlink );
		}
		
		return false;
		
	}

	function shortlink_for_url( $url, $post_id = null ) {
		extract( $this->options );

		if( !isset( $api_login ) || !isset( $api_key ) ) {
			return false;
		}

		$params = array(
				'login' 	=> $api_login,
				'apiKey' 	=> $api_key,
				'longUrl' 	=> $url,
				'format' 	=> 'json',
		);

		// allow api credentials and other options to be switched
		$params = (array) apply_filters( 'bitly_http_options', $params, $post_id );

		$params = http_build_query( $params );

		$rest_url = 'https://api-ssl.bitly.com/v3/shorten?' . $params;
		
		$response = wp_remote_get( $rest_url );
		
		// if we get a valid response, save the url as meta data for this post
		if( ! is_wp_error( $response ) ) {
			$json = json_decode( wp_remote_retrieve_body( $response ) );

			if( isset( $json->data->url ) )
				return $json->data->url;
		}

		return false;
	}
	
	/**
	 * Wrapper function to get our bitly options
	 */
	function get_options() {
		return wp_parse_args( get_option('bitly_settings'), array(
			'bitly_api_login' => '',
			'bitly_api_key' => ''
		));
	}
	
	/**
	 * Register a submenu page and the settings fields we'll use on that page
	 */
	function admin_menu() {
		
		// reg our section
		add_settings_section( 'api', 'API Credentials', '__return_false', 'bitly-options' );

		// Get login/key
		$login = isset( $this->options['api_login'] ) ? $this->options['api_login'] : '';
		$key   = isset( $this->options['api_key']   ) ? $this->options['api_key']   : '';

		// create an api login and key field
		add_settings_field( 'bitly_api_login', 'API Login', array( $this, 'textfield' ), 'bitly-options', 'api', array(
			'name'  => 'bitly_settings[api_login]',
			'value' => $login,
		));

		add_settings_field( 'show_lede_dates', 'API Key', array( $this, 'textfield' ), 'bitly-options', 'api', array(
			'name'  => 'bitly_settings[api_key]',
			'value' => $key,
		));

		// set our validation callback
		register_setting( 'bitly_settings', 'bitly_settings', array( $this, 'validate_settings' ) );
		
		// create a sub menu page within settings menu page
		add_submenu_page( 'options-general.php', 'Bit.ly Settings', 'Bit.ly', 'edit_theme_options', 'bitly-settings', array( $this, 'settings_page' ) );
	}
	
	/**
	 * Builds a simple text field
	 */
	function textfield( $args ) {
		extract( wp_parse_args( $args, array(
			'name' => null,
			'value' => null,
		)));
		?>
		<input type="text" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text"/>
		<?php
	}
	
	/**
	 * Sanitize the values the user entered on our settings page
	 */
	function validate_settings( $input ) {
		
		$output = array();
		
		$output['api_login'] = sanitize_text_field( $input['api_login'] );
		$output['api_key'] = sanitize_text_field( $input['api_key'] );
		
		return $output;
	}
	
	/**
	 * Build the html for our settings screen
	 */
	function settings_page() {
		?>
		<div class="wrap">
			<div id="icon-options-general" class="icon32"><br></div>
			<h2>Bit.ly Settings</h2>
			<form action="options.php" method="post">
				<?php
				wp_nonce_field( 'bitly_settings', 'bitly_settings_nonce', false );
				settings_fields( 'bitly_settings' );
				do_settings_sections( 'bitly-options' );
				?>
				<p class="submit">
					<input type="submit" name="submit" class="button-primary" value="Save Changes"/>
				</p>
			</form>
		</div>
		<?php
	}
}
$bitly = new Bitly();

/**
 * Helper function to get the short url for a post
 *
 * @param int $post_id
 * @return string $url
 */
function bitly_get_url( $post_id = null ) {

	$post_id = empty( $post_id ) ? get_the_ID() : $post_id;
	
	return get_post_meta( $post_id, 'bitly_url', true );
}

/**
 * Helper function to get the short url for a blog
 * 
 * @return string $url
 */
function bitly_get_blog_url() {
	return get_option( 'bitly_siteurl' );
}

/**
 * Generate short_url for use in bitly_process_posts()
 */
function bitly_generate_short_url( $post_id ) {
	global $bitly;

	if ( ! bitly_is_url_generation_enabled() )
		return false;

	if ( is_object( $bitly ) && is_callable( array( $bitly, 'generate_bitly_url' ) ) )
		return call_user_func( array( $bitly, 'generate_bitly_url' ), $post_id );
	return false;
}

/**
 * Generate a short url for the current blog's homepage
 */
function bitly_generate_blog_short_url() {
	global $bitly;

	if ( ! bitly_is_url_generation_enabled() )
		return false;

	if ( is_object( $bitly ) && is_callable( array( $bitly, 'generate_bitly_blog_url' ) ) )
		return call_user_func( array( $bitly, 'generate_bitly_blog_url' ) );

	return false;
}

/**
 * Filter to replace the default shortlink
 */
function bitly_shortlink( $shortlink, $id, $context ) {

	if ( 'post' == $context || ( 'query' == $context && is_single() ) ) {
		if ( 'query' == $context )
			$id = get_queried_object_id();
		$bitly = bitly_get_url( $id );
		if( $bitly ) $shortlink = esc_url( $bitly );
	} elseif ( 'query' == $context && ( is_home() || is_front_page() ) ) {
		$bitly = bitly_get_blog_url();

		if ( $bitly ) $shortlink = esc_url( $bitly );
	}

	return $shortlink;
}
add_filter( 'pre_get_shortlink', 'bitly_shortlink', 10, 3 );

/**
 * Helper to get available post types
 */
function bitly_get_post_types() {

	global $bitly;

	if( is_object( $bitly ) )
		return $bitly->post_types;
	else
		return array( 'post', 'page' );
}

/**
 * Cron to process all of the posts that don't have bitly urls
 */
function bitly_process_posts( $hourly_limit = null ) {
	global $wpdb;

	if ( ! bitly_is_url_generation_enabled() ) {
		bitly_log( 'Bit.ly backfill is currently disabled via code' );

		return;
	}

	// Check if we should even be running this
	$bitly_processed = get_option( 'bitly_processed' );
	if ( ! empty( $bitly_processed ) ) {
		bitly_log( "All bit.ly URLs were processed. Run reset_process_status if you think this is in error and try again." );

		return;
	}

	// Use the default limit if one was not set
	if ( empty( $hourly_limit ) || ! is_numeric( $hourly_limit ) )
		$hourly_limit = apply_filters( 'bitly_hourly_limit', 100 );

	// Generate a shortlink for the homepage, if it doesn't exist
	$blog_shortlink = bitly_get_blog_url();

	if ( ! $blog_shortlink ) {
		bitly_log( "Set short URL for blog" );

		bitly_generate_blog_short_url();
	}

	$post_type_sql = "";

	// get the post types that are supported
	$post_types = bitly_get_post_types();

	// build the sql for querying post types
	if( count( $post_types ) ) {

		foreach( $post_types as $post_type ) {
			$sanitized_post_types[] = $wpdb->prepare( '%s', $post_type );
		}

		$post_type_sql = sprintf( '%s IN ( %s )', "$wpdb->posts.post_type", implode( ',', $sanitized_post_types ) );
	}

	// Only do the query if there's post_type sql
	if( ! empty( $post_type_sql ) ) {
		bitly_log( "Starting to process posts without bit.ly short URLs with a limit of {$hourly_limit}" );
		
		// Get $limit published posts that don't have a bitly url
		// Only query for a maximum of 100 posts at a time
		$processed 	= 0;
		$per_page 	= 100;

		do {
			$query = "
				SELECT $wpdb->posts.ID
				FROM $wpdb->posts
				LEFT JOIN $wpdb->postmeta ON ( $wpdb->posts.ID = $wpdb->postmeta.post_id AND $wpdb->postmeta.meta_key =  'bitly_url' ) 
				WHERE 1=1
				AND ( $post_type_sql )
				AND ( $wpdb->posts.post_status = 'publish' )
				AND ( $wpdb->postmeta.post_id IS NULL )
				GROUP BY $wpdb->posts.ID
				ORDER BY $wpdb->posts.post_date DESC
				LIMIT $per_page
			";
						
			// Get the posts
			$posts = $wpdb->get_results( $query );
			
			// Increment the counter
			$processed += count( $posts );
		
			// This could be empty if there was no $post_type_sql
			if ( ! empty( $posts ) ) {
				// Process these posts
				foreach( $posts as $p ) {
					bitly_log( "Generating short_url for post ID {$p->ID}" );
					
					bitly_generate_short_url( $p->ID );
				}
			} else {
				// Kill our scheduled event
				bitly_log( "No posts were found. Killing the event." );

				bitly_processed();
			}
			
			bitly_log( "Processed {$processed} posts" );

			sleep( 2 );
		} while ( count( $posts ) && $processed < $hourly_limit );
	} else {
		// Kill our scheduled event
		bitly_log( "No bit.ly post types were found. Killing the event." );

		bitly_processed();
	}
	
	// If $per_page isn't equal to the number of posts found on the last run, we should disable this forever
	if ( $per_page != count( $posts ) ) {
		bitly_log( "All bit.ly posts were processed. Killing the event." );

		bitly_processed();
	}
}

// Enable backfill for posts that don't have a bitly url
add_action( 'init', 'bitly_init_post_backfill' );

function bitly_init_post_backfill() {
	if ( ! bitly_is_url_generation_enabled() )
		return;

	add_action( 'bitly_hourly_hook', 'bitly_process_posts' );

	$bitly_processed 	= get_option( 'bitly_processed' );
	$blog_shortlink		= get_option( 'bitly_siteurl' );

	if ( ( ! $bitly_processed || ! $blog_shortlink ) && ! wp_next_scheduled( 'bitly_hourly_hook' ) )
		wp_schedule_event( time() + 30, 'hourly', 'bitly_hourly_hook' );
}

/**
 * Should we actually generate bit.ly urls for posts?
 *
 * Generation can be disabled for dev environments, where generating bit.ly urls would create links
 * to a dev address.
 *
 * @return  bool Boolean indicating if bit.ly urls should be generated
 */
function bitly_is_url_generation_enabled() {
	return (bool) apply_filters( 'bitly_enable_url_generation', true );
}

/**
 * Disables the backfill process from running in the future because no URLs remain
 *
 * @return void
 */
function bitly_processed() {
	update_option( 'bitly_processed', 1 );
	wp_clear_scheduled_hook( 'bitly_hourly_hook' );
}

/**
 * Helper function to log output if the backfill is being executed from a CLI script
 *
 * @param string $message
 * @return void
 */
function bitly_log( $message ) {
	if ( defined( 'WP_CLI' ) && WP_CLI )
		WP_CLI::line( $message );
}