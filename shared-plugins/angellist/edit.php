<?php
/**
 * Add a meta box to the edit post and new post page
 *
 * @since 1.0
 */
class AngelList_Post_Meta_Box {
	/**
	 * HTML ID of the meta box
	 *
	 * @var string
	 * @since 1.0
	 */
	const BASE_ID = 'angellist-company-selector';

	/**
	 * Share a hashed secret between the post page and the save page to prove source
	 *
	 * @var string
	 * @since 1.0
	 */
	const NONCE = 'angellist-company-selector-postmeta-nonce';

	/**
	 * Load product selector meta box on post edit screen load if no stop tags present
	 *
	 * @since 1.0
	 */
	public static function init() {
		add_action( 'wp_ajax_angellist-search', array( 'AngelList_Post_Meta_Box', 'search' ) );

		foreach ( array( 'post.php', 'post-new.php' ) as $action ) {
			add_action( 'load-' . $action, array( 'AngelList_Post_Meta_Box', 'load' ) );
		}
	}

	/**
	 * Attach actions when post loaded
	 *
	 * @since 1.0
	 */
	public static function load() {
		add_action( 'add_meta_boxes', array( 'AngelList_Post_Meta_Box', 'add_meta_boxes' ) );
		add_action( 'save_post', array( 'AngelList_Post_Meta_Box', 'process_saved_data' ) );
		add_action( 'updated_postmeta', array( 'AngelList_Post_Meta_Box', 'clear_cache' ), 10, 4 );
		foreach ( array( 'post-new.php', 'post.php' ) as $page ) {
			add_action( 'admin_print_scripts-' . $page, array( 'AngelList_Post_Meta_Box', 'enqueue_scripts' ) );
			add_action( 'admin_print_styles-' . $page, array( 'AngelList_Post_Meta_Box', 'enqueue_styles') );
			add_action( 'admin_head-' . $page, array( 'AngelList_Post_Meta_Box', 'add_help_tab' ) );
		}
	}

	/**
	 * Add meta box to edit post
	 *
	 * @since 1.0
	 * @uses add_meta_box()
	 */
	public static function add_meta_boxes() {
		add_meta_box( AngelList_Post_Meta_Box::BASE_ID, __( 'AngelList companies', 'angellist' ), array( 'AngelList_Post_Meta_Box', 'company_selector' ), 'post', 'side' );
	}

	/**
	 * Queue up a script for use in the post meta box
	 *
	 * @since 1.0
	 * @uses wp_enqueue_script()
	 * @param string hook name. scope the enqueue to just the admin pages we care about
	 */
	public static function enqueue_scripts() {
		wp_enqueue_script( 'jquery' );

		wp_enqueue_script( AngelList_Post_Meta_Box::BASE_ID . '-js', plugins_url( 'static/js/angellist-company-selector' . ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min' ) . '.js', __FILE__ ), array( 'jquery-ui-widget', 'jquery-ui-autocomplete', 'jquery-ui-sortable' ), '1.3', true );
	}

	/**
	 * Queue a CSS stylesheet to load with the post page.
	 *
	 * @since 1.0
	 * @uses wp_enqueue_style()
	 */
	public static function enqueue_styles() {
		wp_enqueue_style( AngelList_Post_Meta_Box::BASE_ID . '-css', plugins_url( 'static/css/angellist-company-selector.css', __FILE__ ), array(), '1.3' );
	}

	/**
	 * Build the markup used in the post box
	 *
	 * @since 1.0
	 * @uses wp_nonce_field()
	 */
	public static function company_selector() {
		global $post;

		// verify request
		wp_nonce_field( plugin_basename( __FILE__ ), AngelList_Post_Meta_Box::NONCE );

		if ( ! empty( $post ) && isset( $post->ID ) )
			$post_id = absint( $post->ID );
		else
			$post_id = 0;

		echo '<div id="' . AngelList_Post_Meta_Box::BASE_ID .  '-results">';
		$company_ids = array();

		if ( $post_id > 0 ) {
			$html = '';
			$companies = maybe_unserialize( get_post_meta( $post_id, 'angellist-companies', true ) );
			if ( ! empty( $companies ) && is_array( $companies ) ) {
				$num_companies = count( $companies );
				for ( $i=0; $i < $num_companies; $i++ ) {
					$company = $companies[$i];
					if ( empty( $company ) || ! array_key_exists( 'id', $company ) || ! array_key_exists( 'name', $company ) )
						continue;
					$company['id'] = absint( $company['id'] );
					if ( in_array( $company['id'], $company_ids ) )
						continue;
					$company_ids[] = $company['id'];
					$html .= '<li>' . esc_html( $company['name'] );
					$html .= '<input type="hidden" name="angellist-company[' . $i . '][id]" class="angellist-company-id" value="' . esc_attr( $company['id'] ) . '">';
					$html .= '<input type="hidden" name="angellist-company[' . $i . '][name]" value="' . esc_attr( $company['name'] ) . '">';
					$html .= '</li>';
					unset( $company );
				}
			}
			if ( $html )
				echo '<ol id="' . AngelList_Post_Meta_Box::BASE_ID . '-companies">' . $html . '</ol>';
			else
				echo '<p id="' . AngelList_Post_Meta_Box::BASE_ID . '-placeholder">' . esc_html( _x( 'No companies stored.', 'No AngelList companies associated with this post.', 'angellist' ) ) . '</p>';
			unset( $companies );
			unset( $html );
		}

		if ( $post_id < 1 )
			echo '<noscript><p id="' . AngelList_Post_Meta_Box::BASE_ID . '-placeholder">' . esc_html( __( 'JavaScript required to add AngelList companies.', 'angellist' ) ) . '</p></noscript>';

		echo '</div>';

		echo '<script type="text/javascript">jQuery( "#' . AngelList_Post_Meta_Box::BASE_ID . '" ).one( "' . AngelList_Post_Meta_Box::BASE_ID . '-onload", function(){';
		foreach ( array(
			'search_url' => admin_url( 'admin-ajax.php' ),
			'company_ids' => $company_ids,
			'labels' => array(
				'remove' => __( 'Delete', 'angellist' ),
				'no_results' => __( 'No results found.', 'angellist' ),
				'search' => __( 'Add a company:', 'angellist' ),
				'search_placeholder' => __( 'Start typing…', 'angellist' )
			)
		) as $var => $value ) {
			if ( ! empty( $value ) )
				echo 'angellist.company_selector.' . $var . '=' . json_encode( $value ) . ';';
		}
		echo '});</script>';
	}

	/**
	 * Save companies added and arranged in the AngelList meta box
	 * If AngelList meta box is not present then nonce will not be present and the function will return.
	 *
	 * @since 1.0
	 * @param int post_id post identifier
	 */
	public static function process_saved_data( $post_id ) {
		// do not process on autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return;

		$post_id = absint( $post_id );
		if ( $post_id < 1 || wp_is_post_revision( $post_id ) != false )
			return;

		// verify the request came from the meta box by checking for our nonce
		if ( ! array_key_exists( AngelList_Post_Meta_Box::NONCE, $_POST ) || ! wp_verify_nonce( $_POST[AngelList_Post_Meta_Box::NONCE], plugin_basename(__FILE__) ) )
			return;

		// check permissions
		if ( ! current_user_can( 'edit_post', $post_id ) )
			return;

		/* Is the post box hidden?
		 * A bit tricky since we might want to populate data so it's visible and not disabled when they remove the post box from their hidden list
		 * If we are able to detect the box was never shown then stop processing. Especially if we were thinking about processing tags separately.
		 */
		$hidden_post_boxes = maybe_unserialize( get_user_option( 'metaboxhidden_post' ) );
		if ( ! empty( $hidden_post_boxes ) && is_array( $hidden_post_boxes ) && in_array( AngelList_Post_Meta_Box::BASE_ID, $hidden_post_boxes, true ) )
			return;
		unset( $hidden_post_boxes );

		$companies = array();
		if ( array_key_exists( 'angellist-company', $_POST ) && is_array( $_POST['angellist-company'] ) ) {
			$processed_company_ids = array();
			foreach ( $_POST['angellist-company'] as $company ) {
				if ( ! isset( $company['id'] ) || ! isset( $company['name'] ) )
					continue;
				$company_id = absint( $company['id'] );
				if ( $company_id < 1 || in_array( $company_id, $processed_company_ids, true ) )
					continue;
				$companies[] = array( 'id' => $company_id, 'name' => trim( sanitize_text_field( $company['name'] ) ) );
				$processed_company_ids[] = $company_id;
				unset( $company_id );
			}
			unset( $processed_company_ids );
		}
		update_post_meta( $post_id, 'angellist-companies', $companies );

		if ( ! empty( $companies ) )
			self::ping( $post_id, $companies );
	}

	/**
	 * Ping AngelList when a post is published
	 * AngelList may associate the article with a "press" mention in its company profiles
	 * Sets a post meta key of 'angellist-notified' to enforce one ping per post
	 *
	 * @since 1.1
	 * @param int $post_id post identifier
	 * @param array $companies AngelList companies stored with the post
	 */
	public static function ping( $post_id, array $companies ) {
		if ( ! is_int( $post_id ) || $post_id < 1 || empty( $companies ) )
			return;

		$post = get_post( $post_id );

		// only notify for public post types
		$post_type = get_post_type( $post );
		if ( ! $post_type )
			return;
		$post_type_object = get_post_type_object( $post_type );
		if ( ! ( $post_type_object && isset( $post_type_object->public ) && $post_type_object->public ) )
			return;
		unset( $post_type );
		unset( $post_type_object );

		// only notify for public posts
		$post_status = get_post_status( $post_id );
		if ( ! $post_status )
			return;
		$post_status_object = get_post_status_object( $post_status );
		if ( ! ( $post_status_object && isset( $post_status_object->public ) && $post_status_object->public !== true ) )
			return;
		unset( $post_status );
		unset( $post_status_object );

		// only notify AngelList once per post, even if list of companies has changed
		$notify_once_meta_key = 'angellist-notified';
		if ( get_post_meta( $post_id, $notify_once_meta_key, true ) )
			return;

		// absolute URIs only. no internal Intranet URIs
		$post_url = esc_url_raw( get_permalink( $post_id ), array( 'http', 'https' ) );
		if ( ! $post_url )
			return;

		// allow a publisher to short-circuit pings
		// also ties into the blog_public, cutting off the ping request if robots blocked
		$ping_url = 'https://angel.co/embed/post_published/';
		if ( ! apply_filters( 'option_ping_sites', array( $ping_url ), $post_id ) )
			return;

		$http_args = array(
			'redirection' => 0,
			'httpversion' => '1.1',
			'blocking' => false
		);
		foreach( $companies as $company ) {
			if ( ! ( array_key_exists( 'id', $company ) && array_key_exists( 'name', $company ) ) )
				return;

			// fire and forget
			wp_remote_get( $ping_url . '?' . http_build_query( array(
				'type' => 'Startup',
				'name' => $company['name'],
				'id' => $company['id'],
				'perma_link' => $post_url
			), '', '&' ), $http_args );
		}
		add_post_meta( $post_id, $notify_once_meta_key, '1', true );
	}

	/**
	 * Clear the transient cache when postmeta updated with new values
	 *
	 * @since 1.0
	 * @see update_metadata()
	 * @param $meta_id meta_id value from database row
	 * @param int $post_id post identifier
	 * @param string $meta_key the key of the updated field
	 * @param string $meta_value the new value
	 */
	public static function clear_cache( $meta_id, $post_id, $meta_key, $meta_value ) {
		if ( $meta_key !== 'angellist-companies' )
			return;
		$post_id = absint( $post_id );
		if ( $post_id < 1 )
			return;

		if ( ! class_exists( 'AngelList_Content' ) )
			include_once( dirname(__FILE__) . '/content.php' );

		// key differs based on is_ssl boolean value. trigger both
		foreach ( array( true, false ) as $ssl ) {
			delete_transient( AngelList_Content::cache_key( $post_id, $ssl ) );
		}
	}

	/**
	 * Display help documentation in edit and add post screens
	 * Hide help documentation if user has hidden the referenced metabox at the time of pageload
	 *
	 * @since 1.0
	 */
	public static function add_help_tab() {
		$screen = get_current_screen();

		$hidden_post_boxes = maybe_unserialize( get_user_option( 'metaboxhidden_post' ) );
		// do not display help tab if meta box hidden
		if ( ! empty( $hidden_post_boxes ) && is_array( $hidden_post_boxes ) && in_array( AngelList_Post_Meta_Box::BASE_ID, $hidden_post_boxes, true ) )
			return;
		unset( $hidden_post_boxes );

		$screen->add_help_tab( array(
			'id' => AngelList_Post_Meta_Box::BASE_ID . '-help',
			'title' => 'AngelList',
			'content' => '<p>' . esc_html( __( 'Search for a company by name.', 'angellist' ) ) . '</p><p>' . esc_html( __( 'Select a matching company from the result list.', 'angellist' ) ) . '</p><p>' . esc_html( __( 'Rearrange companies in the order you would like them to appear in your post by dragging a company name to its new position.', 'angellist' ) ) . '</p><p>' . esc_html( sprintf( __( 'Remove a company from the list by clicking the %s button.', 'angellist' ), 'X' ) ) . '</p>'
		) );
	}

	/**
	 * Search AngelList companies by freeform text
	 *
	 * @since 1.0
	 */
	public static function search() {
		// GET only
		if ( array_key_exists( 'REQUEST_METHOD', $_SERVER ) && $_SERVER['REQUEST_METHOD'] !== 'GET' ) {
			header( 'HTTP/1.1 405 Method Not Allowed', true, 405 );
			header( 'Allow: GET', true );
			if ( defined( 'DOING_AJAX' ) && DOING_AJAX )
				wp_die();
			else
				die;
		}

		// allow only logged-on users with the capability to see an edit post screen to access our API proxy
		if ( ! current_user_can( 'edit_posts' ) )
			self::reject_message( new WP_Error( 403, __( 'Cheatin\' uh?' ) ) );

		if ( ! array_key_exists( 'q', $_GET ) )
			self::reject_message( new WP_Error( 400, 'Search string needed. Use q query parameter.' ) );

		$search_term = trim( sanitize_text_field( $_GET['q'] ) );
		if ( empty( $search_term ) )
			self::reject_message( new WP_Error( 400, 'No search string provided.' ) );

		if ( ! class_exists( 'AngelList_Search' ) )
			require_once( dirname(__FILE__) . '/search/class-angellist-search.php' );

		$companies = AngelList_Search::startups( $search_term );
		if ( is_wp_error( $companies ) )
			self::reject_message( $companies );
		else
			wp_send_json( $companies );
	}

	/**
	 * Echo a JSON error message, set a HTTP status, and exit
	 *
	 * @since 1.0
	 * @param WP_Error $error error code of HTTP status int. error message echoed in JSON
	 */
	public static function reject_message( WP_Error $error ) {
		status_header( $error->get_error_code() );
		wp_send_json( array( 'error' => $error->get_error_message() ) );
	}
}
