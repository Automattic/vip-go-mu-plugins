<?php
namespace Automattic\VIP\Search;

class Cache {
	public function __construct() {
		add_action( 'pre_get_posts', array( $this, 'disable_apc_for_ep_enabled_requests' ), 0 );
	}

	/**
	 * Advanced Post Cache and ElasticPress do not work well together.
	 * APC caches post IDs populates the $wp_query->posts by running `get_post` on each cached post id during `posts_request`,
	 * ElasticPress runs on `posts_pre_query` which fires after `posts_request`. Here we disable APC if this query is offloaded to EP.
	 * 
	 *
	 * On the other hand, if a non-ElasticPress query is run, and we disabled
	 * Advanced Post Cache earlier, we enable it again, to make use of its caching
	 * features.
	 *
	 *
	 * @param WP_Query $query The query to examine.
	 */
	public function disable_apc_for_ep_enabled_requests( &$query ) {
		global $advanced_post_cache_object;

		static $disabled_apc = false;

		if ( ! is_a( $advanced_post_cache_object, 'Advanced_Post_Cache' ) ) {
			return;
		}

		if ( ! is_a( $query, 'WP_Query' ) ) {
			return;
		}

		if ( \ElasticPress\Indexables::factory()->get( 'post' )->elasticpress_enabled( $query ) && ! apply_filters( 'ep_skip_query_integration', false, $query ) ) {
			if ( true === $disabled_apc ) {
				// Already disabled, don't try again.
				return;
			}

			/*
			* An ElasticPress-enabled query is being run. Disable Advanced Post Cache
			* entirely.
			*
			* Note that there is one action-hook that is not deactivated: The switch_blog
			* action is not deactivated, because it might be called in-between
			* ElasticPress-enabled query, and a non-ElasticPress query, and because it
			* does not have an effect on WP_Query()-results directly.
			*/
			remove_filter( 'posts_request', array( $advanced_post_cache_object, 'posts_request' ) );
			remove_filter( 'posts_results', array( $advanced_post_cache_object, 'posts_results' ) );

			remove_filter( 'post_limits_request', array( $advanced_post_cache_object, 'post_limits_request' ), 999 );

			remove_filter( 'found_posts_query', array( $advanced_post_cache_object, 'found_posts_query' ) );
			remove_filter( 'found_posts', array( $advanced_post_cache_object, 'found_posts' ) );

			$disabled_apc = true;
		} else {
			// A non-ES query.
			if ( true === $disabled_apc ) {
				/*
				* Earlier, we disabled Advanced Post Cache
				* entirely, but now a non-ElasticPress query is
				* being run, and in such cases it might be useful
				* to have the Cache enabled. Here we enable
				* it again.
				*/
				$advanced_post_cache_object->__construct();

				$disabled_apc = false;
			}
		}
	}
}
