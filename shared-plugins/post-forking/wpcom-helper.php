<?php
// Add capabilities to admin + editor + author roles for the Post Forking plugin
add_action( 'init', function() {
	if ( ! function_exists( 'wpcom_vip_add_role_caps' ) ) {
		return;
	}

	wpcom_vip_add_role_caps( 'administrator', array(
		'edit_forks',
		'edit_fork',
		'edit_others_forks',
		'edit_private_forks',
		'edit_published_forks',
		'read_forks',
		'read_private_forks',
		'delete_forks',
		'delete_others_forks',
		'delete_private_forks',
		'delete_published_forks',
		'publish_forks',
	) );

	wpcom_vip_add_role_caps( 'editor', array(
		'edit_forks',
		'edit_fork',
		'edit_others_forks',
		'edit_private_forks',
		'edit_published_forks',
		'read_forks',
		'read_private_forks',
		'delete_forks',
		'delete_others_forks',
		'delete_private_forks',
		'delete_published_forks',
		'publish_forks',
	) );
	wpcom_vip_remove_role_caps( 'editor', array(
		'edit_others_forks',
		'delete_others_forks',
	) );

	wpcom_vip_add_role_caps( 'author', array(
		'edit_forks',
		'edit_private_forks',
		'edit_published_forks',
		'read_forks',
		'read_private_forks',
		'delete_forks',
		'delete_private_forks',
		'delete_published_forks',
		'publish_forks',
	) );

	wpcom_vip_add_role_caps( 'contributor', array(
		'edit_forks',
		'edit_published_forks',
		'read_forks',
		'delete_forks',
	) );
	wpcom_vip_remove_role_caps( 'contributor', array(
		'edit_others_forks',
		'edit_private_forks',
		'read_private_forks',
		'delete_others_forks',
		'delete_private_forks',
		'delete_published_forks',
		'publish_forks',
	) );

} );
