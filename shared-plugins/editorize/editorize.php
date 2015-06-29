<?php
/**
 * Plugin Name: Editorize
 * Description: Give non-editor users the ability to edit posts
 * License: GPLv2
 */
if ( is_admin() ) {
	add_action( 'admin_init', 'editorize_load_hook' );

	add_action( 'add_meta_boxes', 'editorize_add_meta_box' );
	add_action( 'save_post', 'editorize_save_post' );
}

function editorize_load_hook() {
	global $pagenow;
	if ( ! in_array( $pagenow, array( 'post.php', 'edit.php' ) ) )
		return;

	add_filter( 'user_has_cap', 'editorize_add_author_cap', 10, 3 );
}

function editorize_get_post_editor( $post_id ) {
	return intval( get_post_meta( $post_id, '_post_editor_id', true ) );
}

function editorize_add_meta_box() {
	// Check that current user isn't the post_editor because we grant them edit_others_posts
	$current_post_editor = editorize_get_post_editor( get_the_ID() );
	if ( $current_post_editor == get_current_user_id() || ! current_user_can( 'edit_others_posts' ) )
		return;

	add_meta_box( 'editorize', __( 'Post Editor' ), 'editorize_display_meta_box', 'post', 'side' );
}

function editorize_display_meta_box( $post ) {
	$post_editor = editorize_get_post_editor( $post->ID );
	wp_dropdown_users( array(
		'show_option_none' => __( '-- Select --' ),
		'name' => 'editorize_post_editor',
		'selected' => $post_editor,
		'exclude' => get_current_user_id(),
	) );
	wp_nonce_field( 'editorize_add_editor', 'editorize_nonce', false );
}

function editorize_save_post( $post_id ) {

	if ( ! isset( $_POST['editorize_nonce'] ) || ! wp_verify_nonce( $_POST['editorize_nonce'], 'editorize_add_editor' ) )
		return;

	$current_post_editor = editorize_get_post_editor( $post_id );

	if ( $current_post_editor == get_current_user_id() || ! current_user_can( 'edit_others_posts' ) )
		return;

	$post_editor = ! empty( $_POST['editorize_post_editor'] ) ? intval( $_POST['editorize_post_editor'] ) : 0;

	if ( ! $post_editor || ! is_user_member_of_blog( $post_editor ) )
		delete_post_meta( $post_id, '_post_editor_id' );
	else
		update_post_meta( $post_id, '_post_editor_id', $post_editor );
}

/**
 * Allows marked users to edit the post if they don't have the necessary caps
 * Stolen from Co-Authors Plus
 */
function editorize_add_author_cap( $allcaps, $caps, $args ) {

	// Load the post data:
	$user_id = isset( $args[1] ) ? $args[1] : 0;
	$post_id = isset( $args[2] ) ? $args[2] : 0;

	// TODO: post_type_support
	// Need edit_others_posts to be able edit other user's posts
	if ( ! $post_id && in_array( 'edit_others_posts', $caps ) )
		$post_id = get_the_ID();

	if( ! $post_id )
		return $allcaps;

	$post = get_post( $post_id );

	if( ! $post )
		return $allcaps;

	$post_type_object = get_post_type_object( $post->post_type );

	// Bail out if there's no post type object
	if ( ! is_object( $post_type_object ) )
		return $allcaps;

	// Bail out if we're not asking about a post
	if ( ! in_array( $args[0], array( $post_type_object->cap->edit_post, $post_type_object->cap->edit_others_posts ) ) )
		return $allcaps;

	// Bail out for users who can already edit others posts
	if ( isset( $allcaps[$post_type_object->cap->edit_others_posts] ) && $allcaps[$post_type_object->cap->edit_others_posts] )
		return $allcaps;

	$post_editor = editorize_get_post_editor( $post_id );

	// Finally, double check that the user is a coauthor of the post
	if( $post_editor == $user_id ) {
		foreach($caps as $cap) {
			$allcaps[$cap] = true;
		}
	}

	return $allcaps;
}
