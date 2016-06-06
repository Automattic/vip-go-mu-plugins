<?php

class Debug_Bar_WP_Query extends Debug_Bar_Panel {
	function init() {
		$this->title( __('WP Query', 'debug-bar') );
	}

	function prerender() {
		$this->set_visible( defined('SAVEQUERIES') && SAVEQUERIES );
	}

	function render() {
		global $template, $wp_query;
		$queried_object = get_queried_object();
		if ( $queried_object && isset( $queried_object->post_type ) )
			$post_type_object = get_post_type_object( $queried_object->post_type );

		echo "<div id='debug-bar-wp-query'>";
		echo '<h2><span>Queried Object ID:</span>' . get_queried_object_id() . "</h2>\n";

		// Determine the query type. Follows the template loader order.
		$type = '';
		if ( is_404() )
			$type = '404';
		elseif ( is_search() )
			$type = 'Search';
		elseif ( is_tax() )
			$type = 'Taxonomy';
		elseif ( is_front_page() )
			$type = 'Front Page';
		elseif ( is_home() )
			$type = 'Home';
		elseif ( is_attachment() )
			$type = 'Attachment';
		elseif ( is_single() )
			$type = 'Single';
		elseif ( is_page() )
			$type = 'Page';
		elseif ( is_category() )
			$type = 'Category';
		elseif ( is_tag() )
			$type = 'Tag';
		elseif ( is_author() )
			$type = 'Author';
		elseif ( is_date() )
			$type = 'Date';
		elseif ( is_archive() )
			$type = 'Archive';
		elseif ( is_paged() )
			$type = 'Paged';

		if ( !empty($type) )
			echo '<h2><span>Query Type:</span>' . $type . "</h2>\n";

		if ( !empty($template) )
			echo '<h2><span>Query Template:</span>' . basename($template) . "</h2>\n";

		$show_on_front = get_option( 'show_on_front' );
		$page_on_front = get_option( 'page_on_front' );
		$page_for_posts = get_option( 'page_for_posts' );

		echo '<h2><span>Show on Front:</span>' . $show_on_front . "</h2>\n";
		if ( 'page' == $show_on_front ) {
			echo '<h2><span>Page for Posts:</span>' . $page_for_posts . "</h2>\n";
			echo '<h2><span>Page on Front:</span>' . $page_on_front . "</h2>\n";
		}

		if ( isset( $post_type_object ) )
			echo '<h2><span>Post Type:</span>' . $post_type_object->labels->singular_name . "</h2>\n";

		echo '<div class="clear"></div>';

		if ( empty($wp_query->query) )
			$query = 'None';
		else
			$query = http_build_query( $wp_query->query );

		echo '<h3>Query Arguments:</h3>';
		echo '<p>' . esc_html( $query ) . '</p>';

		if ( ! empty($wp_query->request) ) {
			echo '<h3>Query SQL:</h3>';
			echo '<p>' . esc_html( $wp_query->request ) . '</p>';
		}

		if ( ! is_null( $queried_object ) ) {
			echo '<h3>Queried Object:</h3>';
			echo '<ol class="debug-bar-wp-query-list">';
			foreach ($queried_object as $key => $value) {
				// See: http://wordpress.org/support/topic/plugin-debug-bar-custom-post-type-archive-catchable-fatal-error
				// TODO: Fix better
				if ( is_object( $value ) ) {
					echo '<li>' . $key . ' => <ol>';
					foreach ( $value as $_key => $_value )
						echo '<li>' . $_key . ' => ' . $_value . '</li>';
					echo '</ol></li>';
				} else {
					echo '<li>' . $key . ' => ' . $value . '</li>';
				}
			}
			echo '</ol>';
		}
		echo '</div>';
	}
}
