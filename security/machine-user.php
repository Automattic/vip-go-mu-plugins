<?php

namespace Automattic\VIP\Security;

const USER_MODIFICATION_CAPS = [
	'edit_user',
	'delete_user',
	'remove_user',
	'promote_user',
];

// Don't allow the VIP machine user to be edited or removed.
function prevent_machine_user_mods( $caps, $requested_cap, $user_id, $args ) {
	if ( in_array( $requested_cap, USER_MODIFICATION_CAPS, true ) ) {
		// args[0] is who we're performing the action on.
		$user_to_edit_id = $args[0];
		$user_to_edit    = get_userdata( $user_to_edit_id );

		if ( WPCOM_VIP_MACHINE_USER_LOGIN === $user_to_edit->user_login ) {
			return [ 'do_not_allow' ];
		}
	}

	return $caps;
}
// Use map_meta_cap instead of user_has_cap so we can restrict superadmins as well.
// WP_User::has_cap runs the user_has_cap filter after the superadmin check, which makes it impossible to use for restricting them.
add_filter( 'map_meta_cap', __NAMESPACE__ . '\prevent_machine_user_mods', PHP_INT_MAX, 4 );
