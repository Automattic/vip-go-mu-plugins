<?php
/**
 * Fixes compatibility problems with 3rd party plugins
 *
 * @package livepress
 */

class LivePress_Compatibility_Fixes {
	/**
	 * Static instance.
	 *
	 * @static
	 * @access private
	 * @var null
	 */
	private static $instance = null;

	/**
	 * Instance.
	 *
	 * @static
	 * @access public
	 *
	 * @return LivePress_Compatibility_Fixes|null
	 */
	public static function instance() {
		if ( self::$instance == null ) {
			self::$instance = new LivePress_Compatibility_Fixes();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		global $wp_version;
		if ( $wp_version < '4.0' ){
			add_filter( 'embed_oembed_html', array( $this, 'lp_embed_oembed_html' ), 1000, 4 );
		}
		add_filter( 'the_content', array( $this, 'lp_inject_twitter_script' ), 1000 );

		add_filter( 'tm_coschedule_save_post_callback_filter', array( $this, 'tm_coschedule_save_post_callback_filter' ), 10, 2 );
	}

	// Remove twitter scripts embedded in live post
	/**
	 * @param $content
	 * @param $url
	 * @param $attr
	 * @param $post_id
	 *
	 * @return mixed
	 */
	static function lp_embed_oembed_html($content, $url, $attr, $post_id) {
		return preg_replace( '!<script[^>]*twitter[^>]*></script>!i', '', $content );
		return $content;
	}

	/**
	 * Escappe amp HTML.
	 *
	 * @static
	 *
	 * @param $html
	 * @return mixed
	 */
	static function esc_amp_html($html) {
		return preg_replace( '/&(?![a-z]+;|#[0-9]+;)/', '&amp;', $html );
	}

	/**
	 * Patch tweet details.
	 *
	 * @static
	 *
	 * @param       $tweet_details
	 * @param array $options
	 * @return mixed
	 */
	static function patch_tweet_details( $tweet_details, $options = array()) {
		$tweet_details['tweet_text'] = self::esc_amp_html( $tweet_details['tweet_text'] );
		return $tweet_details;
	}

	// Enqueue the Twitter platform script when update contains tweet
	static function lp_inject_twitter_script( $content ) {
		if ( preg_match( '/class="twitter-tweet"/i', $content ) ) {
			wp_enqueue_script( 'platform-twitter', '//platform.twitter.com/widgets.js', array() );
		}
		return $content;
	}


	/**
	 * Called by filter in the CoSchedule Plugin
	 * Returns false to stop an update from being posted to the CoSchedule system
	 * as only parent post needs to be scheduled
	 *
	 * @param bool	$state
	 * @param int	$post_id
	 *
	 * @return bool
	 */
	static function tm_coschedule_save_post_callback_filter( $state, $post_id ){
		$parent_id = wp_get_post_parent_id( abs( $post_id ) );

		if ( LivePress_Updater::instance()->blogging_tools->get_post_live_status( $parent_id ) ){
			$state = false;
		}
		// really make sure that we return a bool
		return ( false === $state ) ? false : true ;
	}
}
