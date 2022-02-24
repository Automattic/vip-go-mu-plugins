<?php
/**
 * Parsely row actions class
 *
 * @package Parsely
 * @since 2.6.0
 */

declare(strict_types=1);

namespace Parsely\UI;

use Parsely\Parsely;
use Parsely\Dashboard_Link;
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
	 *
	 * @return void
	 */
	public function run(): void {
		/**
		 * Filter whether row action links are enabled or not.
		 *
		 * @since 2.6.0
		 *
		 * @param bool $enabled True if enabled, false if not.
		 */
		if ( apply_filters( 'wp_parsely_enable_row_action_links', true ) ) {
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
	public function row_actions_add_parsely_link( array $actions, WP_Post $post ): array {
		if ( ! Dashboard_Link::can_show_link( $post, $this->parsely ) ) {
			return $actions;
		}

		$url = Dashboard_Link::generate_url( $post, $this->parsely->get_api_key(), 'wp-admin-posts-list', 'wp-admin' );
		if ( '' !== $url ) {
			$actions['find_in_parsely'] = $this->generate_link_to_parsely( $post, $url );
		}

		return $actions;
	}

	/**
	 * Generate the HTML link to Parse.ly.
	 *
	 * @since 2.6.0
	 * @since 3.1.2 Added `url` parameter.
	 *
	 * @param WP_Post $post Which post object or ID to add link to.
	 * @param string  $url Generated URL for the post.
	 * @return string The HTML for the link to Parse.ly.
	 */
	private function generate_link_to_parsely( WP_Post $post, string $url ): string {
		return sprintf(
			'<a href="%1$s" aria-label="%2$s">%3$s</a>',
			esc_url( $url ),
			esc_attr( $this->generate_aria_label_for_post( $post ) ),
			esc_html__( 'Parse.ly&nbsp;Stats', 'wp-parsely' )
		);
	}

	/**
	 * Generate ARIA label content.
	 *
	 * @since 2.6.0
	 *
	 * @param WP_Post $post Which post object or ID to generate the ARIA label for.
	 * @return string ARIA label content.
	 */
	private function generate_aria_label_for_post( WP_Post $post ): string {
		return sprintf(
			/* translators: Post title */
			__( 'Go to Parse.ly stats for "%s"', 'wp-parsely' ),
			$post->post_title
		);
	}
}
