<?php
/**
 * Append AngelList companies to post content
 *
 * @since 1.0
 */
class AngelList_Content {

	/**
	 * Attach to WP load action
	 *
	 * @since 1.0
	 */
	public function __construct() {
		add_action( 'wp', array( &$this, 'on_wp_load' ) );
	}

	/**
	 * Wait for WordPress to load so we can use query functions
	 * If a single post page and post meta exists then build AngelList summary
	 *
	 * @since 1.0
	 */
	public function on_wp_load() {
		global $post, $content_width;

		if ( ! isset( $post ) || ! is_single() )
			return;

		// exclude themes below a certain width such as mobile
		if ( isset( $content_width ) && $content_width < 351 )
			return;

		$post_id = absint( $post->ID );
		if ( $post_id < 1 )
			return;

		$companies = get_post_meta( $post_id, 'angellist-companies', true );
		if ( empty( $companies ) )
			return;
		$this->company_ids = array();
		foreach ( $companies as $company ) {
			if ( array_key_exists( 'id', $company ) )
				$this->company_ids[] = absint( $company['id'] );
		}
		unset( $companies );

		// this should not happen
		if ( empty( $this->company_ids ) )
			return;

		add_action( 'wp_head', array( 'AngelList_Content', 'enqueue_styles' ), 1 );
		add_action( 'wp_enqueue_scripts', array( 'AngelList_Content', 'enqueue_scripts' ) );
		add_filter( 'the_content', array( &$this, 'content' ), 12 );
	}

	/**
	 * Queue a stylesheet for AngelList company content
	 *
	 * @since 1.0
	 * @uses wp_enqueue_style()
	 */
	public static function enqueue_styles() {
		wp_enqueue_style( 'angellist-companies', plugins_url( 'static/css/angellist-companies.css', __FILE__ ), array(), '1.3.1' );
	}

	/**
	 * Queue scripts in footer
	 *
	 * @since 1.1
	 * @uses wp_enqueue_script()
	 */
	public static function enqueue_scripts() {
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'angellist', plugins_url( 'static/js/angellist' . ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min' ) . '.js', __FILE__ ), array( 'jquery' ), '1.2', true );
	}

	/**
	 * Build a cache key
	 *
	 * @since 1.0
	 * @param int $post_id post identifier
	 * @param bool $ssl specify SSL for HTTPS external assets
	 * @return string cache key
	 */
	public static function cache_key( $post_id, $ssl=false ) {
		$cache_key_parts = array( 'angellist-companies', 'v1.2.1' );

		// differentiate between posts on different sites
		if ( is_multisite() ) {
			$blog_id = absint( get_current_blog_id() );
			if ( $blog_id > 0 )
				$cache_key_parts[] = 's' . $blog_id;
			unset( $blog_id );
		}

		$cache_key_parts[] = 'p' . $post_id;
		if ( $ssl )
			$cache_key_parts[] = 'ssl';
		return implode( '-', $cache_key_parts );
	}

	/**
	 * Generate new AngelList content
	 *
	 * @since 1.0
	 * @param array $company_ids AngelList company identifiers
	 * @return string HTML markup
	 */
	public static function generate_content( array $company_ids ) {
		if ( empty( $company_ids ) )
			return '';

		$html = '';
		if ( ! class_exists( 'AngelList_Company' ) )
			require_once( dirname( __FILE__ ) . '/templates/company.php' );
		foreach ( $company_ids as $company_id ) {
			$company = new AngelList_Company( $company_id );
			if ( isset( $company->html ) )
				$html .= $company->html;
			else if ( ! isset( $company->name ) || ! isset( $company->profile_url ) )
				continue;
			else
				$html .= $company->render();
		}

		if ( $html )
			$html = '<ol id="angellist-companies">' . $html . '</ol>';

		return $html;
	}

	/**
	 * Append AngelList content to post content
	 *
	 * @since 1.0
	 * @param string post content
	 * @return string post content with AngelList content appended
	 */
	public function content( $content ) {
		global $post;

		if ( ! ( in_the_loop() && isset( $post ) && $content && isset( $this->company_ids ) && is_array( $this->company_ids ) ) )
			return $content;

		$post_id = absint( $post->ID );
		if ( $post_id < 1 )
			return $content;

		$cache_key = AngelList_Content::cache_key( $post_id, is_ssl() );
		$angellist_content = get_transient( $cache_key );
		if ( empty( $angellist_content ) ) {
			$angellist_content = AngelList_Content::generate_content( $this->company_ids );

			// store markup for one hour.
			// speeds up page generation, takes heat off the AngelList API server
			if ( $angellist_content )
				set_transient( $cache_key, $angellist_content, 3600 );
		}
		unset( $cache_key );

		// add a newline to separate new content from the last line of a post
		// avoids possible errors with oEmbed or other single-line detected content on the last line when priority < 10
		if ( $angellist_content )
			return $content . "\n" . $angellist_content;

		return $content;
	}
}
?>