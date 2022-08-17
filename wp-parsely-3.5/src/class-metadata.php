<?php
/**
 * Metadata class
 *
 * @package Parsely
 * @since   3.3.0
 */

declare(strict_types=1);

namespace Parsely;

use Parsely\Metadata\Author_Archive_Builder;
use Parsely\Metadata\Category_Builder;
use Parsely\Metadata\Date_Builder;
use Parsely\Metadata\Front_Page_Builder;
use Parsely\Metadata\Page_Builder;
use Parsely\Metadata\Page_For_Posts_Builder;
use Parsely\Metadata\Paginated_Front_Page_Builder;
use Parsely\Metadata\Post_Builder;
use Parsely\Metadata\Tag_Builder;
use WP_Post;

/**
 * Generates and inserts metadata readable by the Parse.ly Crawler.
 *
 * @since 1.0.0
 * @since 3.3.0 Logic extracted from Parsely\Parsely class to separate file/class.
 */
class Metadata {
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
	 * Creates Parse.ly metadata object from post metadata.
	 *
	 * @param WP_Post $post object.
	 *
	 * @return array<string, mixed>
	 */
	public function construct_metadata( WP_Post $post ): array {
		$options           = $this->parsely->get_options();
		$queried_object_id = get_queried_object_id();

		if ( is_front_page() ) {
			if ( ! is_paged() ) {
				$builder = new Front_Page_Builder( $this->parsely );
			} else {
				$builder = new Paginated_Front_Page_Builder( $this->parsely );
			}
		} elseif ( 'page' === get_option( 'show_on_front' ) && ! get_option( 'page_on_front' ) ) {
			$builder = new Front_Page_Builder( $this->parsely );
		} elseif (
			is_home() && (
				! ( 'page' === get_option( 'show_on_front' ) && ! get_option( 'page_on_front' ) ) ||
				$queried_object_id && (int) get_option( 'page_for_posts' ) === $queried_object_id
			)
		) {
			$builder = new Page_For_Posts_Builder( $this->parsely );
		} elseif ( is_author() ) {
			$builder = new Author_Archive_Builder( $this->parsely );
		} elseif ( is_category() || is_post_type_archive() || is_tax() ) {
			$builder = new Category_Builder( $this->parsely );
		} elseif ( is_date() ) {
			$builder = new Date_Builder( $this->parsely );
		} elseif ( is_tag() ) {
			$builder = new Tag_Builder( $this->parsely );
		} elseif ( in_array( get_post_type( $post ), $options['track_post_types'], true ) && Parsely::post_has_trackable_status( $post ) ) {
			$builder = new Post_Builder( $this->parsely, $post );
		} elseif ( in_array( get_post_type( $post ), $options['track_page_types'], true ) && Parsely::post_has_trackable_status( $post ) ) {
			$builder = new Page_Builder( $this->parsely, $post );
		}

		if ( isset( $builder ) ) {
			$parsely_page = $builder->get_metadata();
		} else {
			$parsely_page = array();
		}

		/**
		 * Filters the structured metadata.
		 *
		 * @param array $parsely_page Existing structured metadata for a page.
		 * @param WP_Post $post Post object.
		 * @param array $options The Parse.ly options.
		 *
		 * @since 2.5.0
		 */
		$filtered = apply_filters( 'wp_parsely_metadata', $parsely_page, $post, $options );
		if ( is_array( $filtered ) ) {
			return $filtered;
		}

		return array();
	}
}
