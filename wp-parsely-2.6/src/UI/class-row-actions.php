<?php
/**
 * Parsely row actions class
 *
 * @package Parsely
 * @since 2.6.0
 */

namespace Parsely\UI;

use Parsely;
use WP_Post;

/**
 * Handle the post/page row actions.
 *
 * @since 2.6.0
 */
final class Row_Actions {
	/**
	 * Instance of Parsely class.
	 *
	 * @var Parsely
	 */
	private $parsely;

	/**
	 * Constructor.
	 *
	 * @param Parsely $parsely Instance of Parsely class.
	 */
	public function __construct( Parsely $parsely ) {
		$this->parsely = $parsely;
	}

	/**
	 * Register action and filter hook callbacks.
	 *
	 * @since 2.6.0
	 */
	public function run() {
		/**
		 * Filter whether row action links are enabled or not.
		 *
		 * @since 2.6.0
		 *
		 * @param bool $enabled True if enabled, false if not.
		 */
		if ( apply_filters( 'wp_parsely_enable_row_action_links', false ) ) {
			add_filter( 'post_row_actions', array( $this, 'row_actions_add_parsely_link' ), 10, 2 );
			add_filter( 'page_row_actions', array( $this, 'row_actions_add_parsely_link' ), 10, 2 );
		}
	}

	/**
	 * Include a link to the statistics page for an article in the wp-admin Posts List.
	 *
	 * If the post object is the "front page," this will include the main dashboard link instead.
	 *
	 * @since 2.6.0
	 *
	 * @see https://developer.wordpress.org/reference/hooks/page_row_actions/
	 * @see https://developer.wordpress.org/reference/hooks/post_row_actions/
	 *
	 * @param array<string, string> $actions The existing list of actions.
	 * @param WP_Post               $post    The individual post object the actions apply to.
	 *
	 * @return array<string, string> The amended list of actions.
	 */
	public function row_actions_add_parsely_link( $actions, WP_Post $post ) {
		if ( $this->cannot_show_parsely_link( $actions, $post ) ) {
			return $actions;
		}

		$actions['find_in_parsely'] = $this->generate_link_to_parsely( $post );

		return $actions;
	}

	/**
	 * Determine whether Parse.ly row action link should be shown or not.
	 *
	 * @since 2.6.0
	 *
	 * @param array   $actions Existing row actions.
	 * @param WP_Post $post    Which post object or ID to check.
	 * @return bool True if the link cannot be shown, false if the link can be shown.
	 */
	private function cannot_show_parsely_link( $actions, WP_Post $post ) {
		return ! is_array( $actions ) ||
			! Parsely::post_has_trackable_status( $post ) ||
			! Parsely::post_has_viewable_type( $post ) ||
			$this->parsely->api_key_is_missing();
	}

	/**
	 * Generate the HTML link to Parse.ly.
	 *
	 * @since 2.6.0
	 *
	 * @param WP_Post $post Which post object or ID to add link to.
	 * @return string The HTML for the link to Parse.ly.
	 */
	private function generate_link_to_parsely( WP_Post $post ) {
		return sprintf(
			'<a href="%1$s" aria-label="%2$s">%3$s</a>',
			esc_url( $this->generate_url( $post, $this->parsely->get_api_key() ) ),
			esc_attr( $this->generate_aria_label_for_post( $post ) ),
			esc_html__( 'Parse.ly&nbsp;Stats', 'wp-parsely' )
		);
	}

	/**
	 * Generate the URL for the link.
	 *
	 * @since 2.6.0
	 *
	 * @param WP_Post $post   Which post object or ID to check.
	 * @param string  $apikey API key or empty string.
	 * @return string
	 */
	private function generate_url( WP_Post $post, $apikey ) {
		$query_args = array(
			'url'          => rawurlencode( get_permalink( $post ) ),
			'utm_campaign' => 'wp-admin-posts-list',
			'utm_medium'   => 'wp-parsely',
			'utm_source'   => 'wp-admin',
		);

		$base_url = trailingslashit( 'https://dash.parsely.com/' . $apikey ) . 'find';

		return add_query_arg( $query_args, $base_url );
	}

	/**
	 * Generate ARIA label content.
	 *
	 * @since 2.6.0
	 *
	 * @param WP_Post $post Which post object or ID to generate the ARIA label for.
	 * @return string ARIA label content.
	 */
	private function generate_aria_label_for_post( $post ) {
		return sprintf(
			/* translators: Post title */
			__( 'Go to Parse.ly stats for "%s"', 'wp-parsely' ),
			$post->post_title
		);
	}
}
