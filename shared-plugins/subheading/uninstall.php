<?php 

	// Ensure uninstall source is WordPress...
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}
	// Define the key used for storing options...
$tag = 'subheading';
	// Define the post meta key name...
$meta_key = '_'.$tag;
	// Lookup all posts that have subheadings...
$posts = get_posts( array(
	'numberposts' => -1,
	'post_type' => 'any',
	'meta_key' => $meta_key
) );
	// Remove all the subheading values...
foreach ( $posts as $post ) {
	delete_post_meta( $post->ID, $meta_key );
}
	// Finally, remove the subheading options...
delete_option( $tag, null );
