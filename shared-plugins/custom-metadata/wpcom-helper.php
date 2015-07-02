<?php

if ( is_admin() && ! empty( $_GET['page'] ) && 'grofiles-user-settings' == $_GET['page'] ) {
	// Run init_metadata right after the normal admin_init for custom metadata
	add_action( 'admin_init', array( $custom_metadata_manager, 'init_metadata' ), 1001 );
}

// Force sanitize the value if a callback is not set
add_filter( 'custom_metadata_manager_get_sanitize_callback', function( $callback, $field ) {
	if ( empty( $callback ) )
		$callback = '_wpcom_vip_custom_metadata_force_sanitize';
	return $callback;
}, 999, 3 );

function _wpcom_vip_custom_metadata_force_sanitize( $field_slug, $field, $object_type, $object_id, $value ) {
	if ( is_array( $value ) )
		$value = array_map( 'wp_filter_post_kses', $value );
	else
		$value = wp_filter_post_kses( $value );

	return $value;
}

// Force user data to be saved as user attributes instead of user meta
add_filter( 'custom_metadata_manager_get_save_callback', function( $callback, $field, $object_type ) {
	if ( 'user' == $object_type )
		$callback = '_wpcom_vip_custom_metadata_save_user_data_as_attributes';
	return $callback;
}, 999, 3 );

function _wpcom_vip_custom_metadata_save_user_data_as_attributes( $object_type, $object_id, $field_slug, $value ) {
	if ( ! empty( $value ) ) {
		update_user_attribute( $object_id, $field_slug, $value );
	} else {
		delete_user_attribute( $object_id, $field_slug );
	}
}

// Force user data to be saved as user attributes instead of user meta
add_filter( 'custom_metadata_manager_get_value_callback', function( $callback, $field, $object_type ) {
	if ( 'user' == $object_type )
		return '_wpcom_vip_custom_metadata_get_user_data_as_attributes';

	return false;
}, 999, 3 );

function _wpcom_vip_custom_metadata_get_user_data_as_attributes( $object_type, $object_id, $field_slug ) {
	return get_user_attribute( $object_id, $field_slug );
}
