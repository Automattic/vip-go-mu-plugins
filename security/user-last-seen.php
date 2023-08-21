<?php
namespace Automattic\VIP\Security;

const LAST_SEEN_META_KEY = 'wpvip_last_seen';
const LAST_SEEN_CACHE_GROUP = 'wpvip_last_seen';
const LAST_SEEN_UPDATE_USER_META_CACHE_TTL = MINUTE_IN_SECONDS * 5; // Store last seen once every five minute to avoid too many write DB operations
const LAST_SEEN_RELEASE_DATE_TIMESTAMP_OPTION_KEY = 'wpvip_last_seen_release_date_timestamp';

// Use a global cache group to avoid having to the data for each site
wp_cache_add_global_groups( array( LAST_SEEN_CACHE_GROUP ) );

// VIP_SECURITY_INACTIVE_USERS_ACTION= undefined || 'NO_ACTION', 'RECORD_LAST_SEEN', 'REPORT', 'BLOCK'
// VIP_SECURITY_CONSIDER_USERS_INACTIVE_AFTER_DAYS = undefined || number

add_filter( 'determine_current_user', function ( $user_id ) {
	if ( ! $user_id ) {
		return $user_id;
	}

	if ( wp_cache_get( $user_id, LAST_SEEN_CACHE_GROUP ) ) {
		// Last seen meta was checked recently
		return $user_id;
	}

	if ( is_considered_inactive( $user_id ) ) {
		// Force current user to 0 to avoid recursive calls to this filter
		wp_set_current_user( 0 );

		return new \WP_Error( 'inactive_account', __( '<strong>Error</strong>: Your account has been flagged as inactive. Please contact your site administrator.', 'inactive-account-lockdown' ) );
	}

	if ( wp_cache_add( $user_id, true, LAST_SEEN_CACHE_GROUP, LAST_SEEN_UPDATE_USER_META_CACHE_TTL ) ) {
		update_user_meta( $user_id, LAST_SEEN_META_KEY, time() );
	}

	return $user_id;
}, 30, 1 );

add_filter( 'authenticate', function( $user ) {
	if ( is_wp_error( $user ) ) {
		return $user;
	}

	if ( $user->ID && is_considered_inactive( $user->ID ) ) {
		return new \WP_Error( 'inactive_account', __( '<strong>Error</strong>: Your account has been flagged as inactive. Please contact your site administrator.', 'inactive-account-lockdown' ) );;
	}

	return $user;
}, 20, 1 );


add_filter( 'wpmu_users_columns', function ( $columns ) {
	$columns['last_seen'] = __( 'Last seen' );
	return $columns;
} );

add_filter( 'manage_users_columns', function ( $columns ) {
	$columns['last_seen'] = __( 'Last seen' );
	return $columns;
} );

add_filter( 'manage_users_sortable_columns', function ( $columns ) {
	$columns['last_seen'] = 'last_seen';

	return $columns;
} );

add_filter( 'manage_users-network_sortable_columns', function ( $columns ) {
	$columns['last_seen'] = 'last_seen';

	return $columns;
} );

add_filter( 'users_list_table_query_args', function ( $vars ) {
	if ( isset( $vars['orderby'] ) && $vars['orderby'] === 'last_seen' ) {
		$vars = array_merge( $vars, array(
			'meta_key' => LAST_SEEN_META_KEY,
			'orderby' => 'meta_value_num'
		) );
	}

	return $vars;
} );

add_filter( 'manage_users_custom_column', function ( $default, $column_name, $user_id ) {
	if ( 'last_seen' !== $column_name ) {
		return $default;
	}

	$last_seen_timestamp = get_user_meta( $user_id, LAST_SEEN_META_KEY, true );

	if ( ! $last_seen_timestamp ) {
		return $default;
	}

	$formatted_date = sprintf(
		__( '%1$s at %2$s' ),
		date_i18n( get_option('date_format'), $last_seen_timestamp ),
		date_i18n( get_option('time_format'), $last_seen_timestamp )
	);

	if ( ! is_considered_inactive( $user_id ) ) {
		return sprintf( '<span>%s</span>', esc_html__( $formatted_date ) );
	}

	$unblock_link = '';
	if ( current_user_can( 'edit_user', $user_id ) ) {
		$url = add_query_arg( array(
			'action' => 'reset_last_seen',
			'user_id' => $user_id,
			'reset_last_seen_nonce' => wp_create_nonce( 'reset_last_seen_action' )
		) );

		$unblock_link = "<div class='row-actions'><span>User blocked due to inactivity. <a class='reset_last_seen_action' href='" . esc_url( $url ) . "'>" . __( 'Unblock' ) . "</a></span></div>";
	}
	return sprintf( '<span class="wp-ui-text-notification">%s</span>' . $unblock_link, esc_html__( $formatted_date ) );
}, 10, 3 );


add_action( 'admin_init', function () {
	$admin_notices_hook_name = is_network_admin() ? 'network_admin_notices' : 'admin_notices';

	if ( isset( $_GET['reset_last_seen_success'] ) && $_GET['reset_last_seen_success'] === '1' ) {
		add_action( $admin_notices_hook_name, function() {
			$class = 'notice notice-success is-dismissible';
			$error = __( 'User unblocked.', 'inactive-account-lockdown' );

			printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $error ) );
		} );
	}

	if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'reset_last_seen' ) {
		return;
	}

	$user_id = absint( $_GET['user_id'] );

	$error = null;
	if ( ! wp_verify_nonce( $_GET['reset_last_seen_nonce'], 'reset_last_seen_action' ) ) {
		$error = __( 'Unable to verify your request', 'inactive-account-lockdown' );
	}

	if ( ! get_userdata( $user_id) ) {
		$error = __( 'User not found.', 'inactive-account-lockdown' );
	}

	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		$error = __( 'You do not have permission to unblock this user.', 'inactive-account-lockdown' );
	}

	if ( ! $error && ! delete_user_meta( $user_id, LAST_SEEN_META_KEY ) ) {
		$error = __( 'Unable to unblock user.', 'inactive-account-lockdown' );
	}

	if ( $error ) {
		add_action( $admin_notices_hook_name, function() use ( $error ) {
			$class = 'notice notice-error is-dismissible';

			printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $error ) );
		} );
		return;
	}

	$url = remove_query_arg( array(
		'action',
		'user_id',
		'reset_last_seen_nonce',
	) );

	$url = add_query_arg( array(
		'reset_last_seen_success' => 1,
	), $url );

	wp_redirect( $url );
	exit();
} );

add_action( 'admin_init', function () {
	if ( ! get_option( LAST_SEEN_RELEASE_DATE_TIMESTAMP_OPTION_KEY ) ) {
		// Right after the first admin_init, set the release date timestamp
		// to be used as a fallback for users that never logged in before.
		add_option( LAST_SEEN_RELEASE_DATE_TIMESTAMP_OPTION_KEY, time() );
	}
} );

function is_considered_inactive( $user_id ) {
	if ( ! defined( 'VIP_SECURITY_CONSIDER_USERS_INACTIVE_AFTER_DAYS' ) || ! constant( 'VIP_SECURITY_INACTIVE_USERS_ACTION' ) !== 'BLOCK' ) {
		return false;
	}

	$last_seen_timestamp = get_user_meta( $user_id, LAST_SEEN_META_KEY, true );
	if ( $last_seen_timestamp ) {
		return $last_seen_timestamp < get_inactivity_timestamp();
	}

	$release_date_timestamp = get_option( LAST_SEEN_RELEASE_DATE_TIMESTAMP_OPTION_KEY );
	if ( $release_date_timestamp ) {
		return $release_date_timestamp < get_inactivity_timestamp();
	}

	// Release date is not defined yed, so we can't consider the user inactive.
	return false;
}

function get_inactivity_timestamp() {
	if ( ! defined( 'VIP_SECURITY_CONSIDER_USERS_INACTIVE_AFTER_DAYS' ) ) {
		return 0;
	}

	return strtotime( sprintf('-%d days', constant( 'VIP_SECURITY_CONSIDER_USERS_INACTIVE_AFTER_DAYS' ) ) ) + LAST_SEEN_UPDATE_USER_META_CACHE_TTL;
}
