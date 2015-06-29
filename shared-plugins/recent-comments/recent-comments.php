<?php
/*
Plugin Name: Recent Comments
Plugin URI:  http://wordpress.org/extend/plugins/recent-comments/
Description: Retrieves a list of the most recent comments.
Version:     2.0
Author:      Automattic
Author URI:  http://automattic.com/
*/


// Fetch the latest comments. Works like get_comments(), but accepts some additional arguments relating to posts.
function get_most_recent_comments( $args = null ) {
	global $most_recent_comments_args;

	// You can pass any of these arguments as well as any argument supported by get_comments()
	$defaults = array(
		'passworded_posts' => false, // Include password protected posts?
		'showpings'        => false, // Include pingbacks and trackbacks?
		'post_types'       => array( 'post', 'page' ),     // Array of post types to use
		'post_statuses'    => array( 'publish', 'static' ), // Array of post statuses to include

		// Standard get_comments() args
		'number'           => 5,
		'status'           => 'approve', // Hide comments in moderation
	);

	$most_recent_comments_args = wp_parse_args( $args, $defaults );

	// Create the cache key
	$key = md5( serialize( $most_recent_comments_args ) );
	$last_changed = wp_cache_get( 'last_changed', 'comment' );
	if ( ! $last_changed ) {
		$last_changed = time();
		wp_cache_set( 'last_changed', $last_changed, 'comment' );
	}
	$cache_key = "most_recent_comments:$key:$last_changed";

	// Check to see if we already have results for this request
	if ( $cache = wp_cache_get( $cache_key, 'comment' ) ) {
		return $cache;
	}

	// Modify the get_comments() SQL query
	add_filter( 'comments_clauses', '_mrc_modify_comments_clauses' );

	// Get the comments
	// The custom arguments will be ignored by get_comments()
	$comments = get_comments( $most_recent_comments_args );

	// Remove the get_comments() SQL query filter
	remove_filter( 'comments_clauses', '_mrc_modify_comments_clauses' );

	// Cache these results
	wp_cache_add( $cache_key, $comments, 'comment' );

	return $comments;
}

// Output an unordered list (<ul>) of the most recent comments
function list_most_recent_comments( $args = null ) {

	// These are in addition to the args of get_most_recent_comments() and get_comments()
	$defaults = array(
		'excerpt_words'    => 0, // The Number of words to display from the comment
		'excerpt_chars'    => 0, // Or the nmber of characters to display from the comment
		'comment_format'   => 0, // Pick from some pre-defined formats
	);

	$r = wp_parse_args( $args, $defaults );

	$comments = get_most_recent_comments( $r );

	$output = "<ul>\n";

	if ( $comments ) {
		$idx = 0;
		foreach ( $comments as $comment ) {
			$comment_author = stripslashes( $comment->comment_author );
			if ( empty( $comment_author ) )
				$comment_author = __( 'anonymous' );

			$comment_content = strip_tags( $comment->comment_content );
			$comment_content = stripslashes( $comment_content );

			if ( 0 != $r['excerpt_words'] ) {
				$words = explode( ' ', $comment_content ); 
				$comment_content = implode( ' ', array_slice( $words, 0, $r['excerpt_words'] ) );
			} elseif ( 0 != $r['excerpt_chars'] ) {
				$comment_content = substr( $comment_content, 0, $r['excerpt_chars'] );
			}

			$comment_permalink = get_comment_link( $comment->comment_ID );

			if ( 1 == $r['comment_format'] ) {
				$post_title = stripslashes( $comment->post_title );
				$post_id= stripslashes( $comment->post_id );
				$url = $comment->comment_author_url;

				$idx++;
				if ( 1 == $idx % 2 )
					$before = '<li class="statsclass1">';
				else
					$before = '<li class="statsclass2">';

				$output .= "$before<a href='$comment_permalink'>$comment_author</a> on <a href='" . get_permalink( $comment->ID ) . "'>$post_title</a>$after";
			} else {
				$idx++;
				if ( 1 == $idx % 2 )
					$before = "<li class='statsclass1'>";
				else
					$before = "<li class='statsclass2'>";
	
				$output .= "$before<strong>$comment_author:</strong> <a href='$comment_permalink' title='" . sprintf( __( 'View the entire comment by %s' ), $comment_author ) . "'>$comment_content</a>$after";
			}
		}

		$output = convert_smilies( $output );
	} else {
		$output .= '<li>' . __( 'None Found' ) . '</li>';
	}

	$output .= "</ul>\n";

	echo $output;
}


// Deprecated, use list_most_recent_comments() instead
function most_recent_comments( $no_comments = 5, $comment_lenth = 5, $deprecated1 = null, $deprecated2 = null, $show_pass_post = false, $comment_style = 0, $hide_pingbacks_trackbacks = false ) {
	$args = array(
		'number'           => $no_comments,
		'passworded_posts' => $show_pass_post,
		'excerpt_words'    => $comment_lenth,
		'comment_format'   => $comment_style,
	);
	$args['showpings'] = ! $hide_pingbacks_trackbacks; // Inverse

	list_most_recent_comments( $args );
}


// Helper function for get_most_recent_comments(), you don't need to use this function
function _mrc_modify_comments_clauses( $clauses ) {
	global $wpdb, $most_recent_comments_args;

	// Join in the posts table
	$clauses['join'] .= " LEFT JOIN $wpdb->posts ON $wpdb->posts.ID = $wpdb->comments.comment_post_ID ";

	// Controls showing comments on password protected posts or not
	if ( empty( $most_recent_comments_args['passworded_posts'] ) )
		$clauses['where'] .= " AND $wpdb->posts.post_password = ''";

	// Controls what post_types to use
	if ( ! empty( $most_recent_comments_args['post_types'] ) && is_array( $most_recent_comments_args['post_types'] ) ) {
		$post_types = array();

		foreach ( $most_recent_comments_args['post_types'] as $post_type ) {
			if ( post_type_exists( $post_type ) ) {
				$post_types[] = $post_type;
			}
		}

		if ( ! empty( $post_types ) ) {
			$post_types = array_map( 'esc_sql', $post_types );
			$clauses['where'] .= " AND $wpdb->posts.post_type IN ( '" . implode( "', '", $post_types ) . "' )";
		}
	}

	// Controls what post statuses to use
	if ( ! empty( $most_recent_comments_args['post_statuses'] ) && is_array( $most_recent_comments_args['post_statuses'] ) ) {
		$most_recent_comments_args['post_statuses'] = array_map( 'esc_sql', $most_recent_comments_args['post_statuses'] );
		$clauses['where'] .= " AND $wpdb->posts.post_status IN ( '" . implode( "', '", $most_recent_comments_args['post_statuses'] ) . "' )";
	}

	return $clauses;
}

?>