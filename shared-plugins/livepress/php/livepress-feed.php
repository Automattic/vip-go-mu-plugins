<?php
/**
 * The feeds served from LivePress!
 */
class LivePress_Feed {
	/**
	 * @access private
	 * @var string $name
	 */
	private $name;

	/**
	 * Constructor.
	 *
	 * @param string $name The post identifier.
	 */
	public function  __construct( $name ) {
		$this->name = $name;
		if ( is_single() ) {
			add_action( 'wp_print_scripts', array( &$this, 'feed_link' ), 4 );
		}
	}

	/**
	 * Initialize.
	 *
	 * @static
	 */
	public static function initialize() {
		new LivePress_Feed('push-rss');
	}

	/**
	 * Print the feed link tag.
	 */
	public function feed_link() {
		$post_title = self::feed_title();
		$feed_link  = LivePress_Updater::instance()->get_current_post_feed_link();

		if ( count( $feed_link ) > 0 ) {
			$tag  = '<link rel="alternate" type="' . esc_attr( feed_content_type( 'rss2' ) ) . '" ';
			$tag .= 'title="' . esc_attr( get_bloginfo( 'name' ) ) . ' &raquo; ' . esc_attr( $post_title ) . '" ';
			$tag .= 'href="' . esc_url( $feed_link[0] ) . '" />' . "\n";
			echo $tag;
		}
	}

	/**
	 * Feed title.
	 *
	 * @static
	 *
	 * @return string
	 */
	public static function feed_title() {
		global $post;
		return $post->post_title . ' Post Updates Feed';
	}
}
