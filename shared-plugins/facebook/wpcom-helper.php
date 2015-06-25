<?php
/**
 * These helper functions leverage use of wpcom's user attributes table instead
 * of core's standard usermeta
 */
add_filter( 'fb_get_user_meta', 'fbwpcom_get_user_meta', 10, 4 );
function fbwpcom_get_user_meta( $original, $user_id, $key, $single ) {
	return get_user_attribute( $user_id, $key, $single );
}

add_filter( 'fb_update_user_meta', 'fbwpcom_update_user_meta', 10, 5 );
function fbwpcom_update_user_meta( $original, $user_id, $meta_key, $meta_value, $prev_value ) {
	update_user_attribute( $user_id, $meta_key, $meta_value, $prev_value );
	return true;
}

add_filter( 'fb_delete_user_meta', 'fbwpcom_delete_user_meta', 10, 4 );
function fbwpcom_delete_user_meta( $original, $user_id, $meta_key, $meta_value ) {
	delete_user_attribute( $user_id, $meta_key, $meta_value );
	return true;
}

add_filter( 'fb_notify_user_of_plugin_conflicts', '__return_false' );
