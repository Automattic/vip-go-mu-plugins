<?php
/**
 * Auto-apply Co-Authors Plus template tags on themes that are properly using the_author()
 * and the_author_posts_link()
 */
$wpcom_coauthors_plus_auto_apply_themes = array(
		'premium/portfolio',
		'premium/zuki',
		'pub/editor',
	);
if ( in_array( get_option( 'template' ), $wpcom_coauthors_plus_auto_apply_themes ) )
	add_filter( 'coauthors_auto_apply_template_tags', '__return_true' );

/**
 * If Co-Authors Plus is enabled on an Enterprise site and hasn't yet been integrated with the theme
 * show an admin notice
 */
if ( function_exists( 'Enterprise' ) ) {
	if ( Enterprise()->is_enabled() && ! in_array( get_option( 'template' ), $wpcom_coauthors_plus_auto_apply_themes ) )
		add_action( 'admin_notices', function() {

			// Allow this to be short-circuted in mu-plugins
			if ( ! apply_filters( 'wpcom_coauthors_show_enterprise_notice', true ) )
				return;

			echo '<div class="error"><p>' . __( "Co-Authors Plus isn't yet integrated with your theme. Please contact support to make it happen." ) . '</p></div>';
		} );
}

/**
 * We want to let Elasticsearch know that it should search the author taxonomy's name as a search field
 * See: https://elasticsearchp2.wordpress.com/2015/01/08/in-36757-z-vanguard-says-they/
 *
 * @param $es_wp_query_args The ElasticSearch Query Parameters
 * @param $query
 *
 * @return mixed
 */
function co_author_plus_es_support( $es_wp_query_args, $query ){
	if ( empty( $es_wp_query_args['query_fields'] ) ) {
		$es_wp_query_args['query_fields'] = array( 'title', 'content', 'author', 'tag', 'category' );
	}

	// Search CAP author names
	$es_wp_query_args['query_fields'][] = 'taxonomy.author.name';

	// Filter based on CAP names
	if ( !empty( $query->query['author'] ) ) {
		$es_wp_query_args['terms']['author'] = 'cap-' . $query->query['author'];
	}

	return $es_wp_query_args;
}
add_filter('wpcom_elasticsearch_wp_query_args', 'co_author_plus_es_support', 10, 2 );

