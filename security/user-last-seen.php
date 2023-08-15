<?php
namespace Automattic\VIP\Security;

const LAST_SEEN_META_KEY = 'wpvip_last_seen';
const LAST_SEEN_UPDATE_USER_META_CACHE_TTL = MINUTE_IN_SECONDS * 5; // Store last seen once every five minute to avoid too many write DB operations
const LAST_SEEN_UPDATE_USER_META_CACHE_KEY_PREFIX = 'wpvip_last_seen_update_user_meta_cache_key';
const GROUP = 'wpvip';

add_filter( 'determine_current_user', function ( $user_id ) {
	if ( ! $user_id ) {
		return $user_id;
	}

	$cache_key = LAST_SEEN_UPDATE_USER_META_CACHE_KEY_PREFIX . $user_id;

	if ( wp_cache_get( $cache_key, GROUP ) ) {
		// Last seen meta was checked recently
		return $user_id;
	}

	$is_api_request = ( ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) );

	if ( $is_api_request && is_considered_inactive( $user_id ) ) {
		// To block API requests for inactive requests, we need to return a WP_Error object here
		return new \WP_Error( 'inactive_account', __( '<strong>Error</strong>: Your account has been flagged as inactive. Please contact your site administrator.', 'inactive-account-lockdown' ) );
	}

	if ( wp_cache_add( $cache_key, true, GROUP, LAST_SEEN_UPDATE_USER_META_CACHE_TTL ) ) {
		update_user_meta( $user_id, LAST_SEEN_META_KEY, time() );
	}

	return $user_id;
}, 30, 1 );

add_filter( 'authenticate', function( $user, string $username, string $password ) {
	if ( is_wp_error( $user ) ) {
		return $user;
	}

	if ( $user->ID && is_considered_inactive( $user->ID ) ) {
		return new \WP_Error( 'inactive_account', __( '<strong>Error</strong>: Your account has been flagged as inactive. Please contact your site administrator.', 'inactive-account-lockdown' ) );;
	}

	return $user;
}, 20, 3 );


add_filter( 'manage_users_columns', function ( $cols ) {
	$cols['last_seen'] = __( 'Last seen' );
	return $cols;
} );

add_filter( 'manage_users_custom_column', function ( $default, $column_name, $user_id ) {
	if ( 'last_seen' == $column_name ) {
		$last_seen_timestamp = get_user_meta( $user_id, LAST_SEEN_META_KEY, true );

		if ( $last_seen_timestamp ) {
			$formatted_date = sprintf(
				__( '%1$s at %2$s' ),
				date_i18n( get_option('date_format'), $last_seen_timestamp ),
				date_i18n( get_option('time_format'), $last_seen_timestamp )
			);

			if ( is_considered_inactive( $user_id ) ) {
				$unblock_link = '';
				if ( current_user_can( 'edit_user', array() ) ) {
					$url = add_query_arg( array(
						'action' => 'reset_last_seen',
						'user_id' => $user_id,
					) );

					$unblock_link = "<div class='row-actions'><span>User blocked due to inactivity. <a class='reset_last_seen_action' href='" . esc_url( $url ) . "'>" . __( 'Unblock' ) . "</a></span></div>";
				}
				return sprintf( '<span class="wp-ui-text-notification">%s</span>' . $unblock_link, esc_html__( $formatted_date ) );
			}

			return sprintf( '<span>%s</span>', esc_html__( $formatted_date ) );
		}
	}

	return $default;
}, 10, 3 );

add_action( 'user_row_actions', function ( $actions  ) {
	if( isset($_GET['action'] ) && $_GET['action'] === 'reset_last_seen' ){
		$user_id = $_GET['user_id'];
		delete_user_meta( $user_id, LAST_SEEN_META_KEY );
	}

	return $actions;
}, 10, 1 );

add_filter( 'views_users', function ( $views ) {
	global $wpdb;

	if ( ! defined( 'VIP_CONSIDER_USERS_INACTIVE_AFTER_DAYS' ) ) {
		return $views;
	}

	$count = $wpdb->get_var( 'SELECT COUNT(meta_key) FROM ' . $wpdb->usermeta . ' WHERE meta_key = "' . LAST_SEEN_META_KEY . '" AND meta_value < ' . get_inactivity_timestamp() );

	$view = __( 'Blocked Users' );
	if ( $count ) {
		$class = isset( $_REQUEST[ 'last_seen_filter' ] ) ? 'current' : '';
		$view = '<a class="' . $class . '" href="users.php?last_seen_filter=blocked">' . $view . '</a>';
	}
	$views['blocked_users'] = $view . ' (' . $count . ')';

	return $views;
} );

add_filter( 'users_list_table_query_args', function ( $args ) {
	if ( ! defined( 'VIP_CONSIDER_USERS_INACTIVE_AFTER_DAYS' ) ) {
		return $args;
	}

	if ( isset( $_REQUEST[ 'last_seen_filter' ] ) ) {
		$args[ 'meta_key' ] = LAST_SEEN_META_KEY;
		$args[ 'meta_value' ] = get_inactivity_timestamp();
		$args[ 'meta_type' ] = 'NUMERIC';
		$args[ 'meta_compare' ] = '<';
	}

	return $args;
} );

function is_considered_inactive( $user_id ) {
	if ( ! defined( 'VIP_CONSIDER_USERS_INACTIVE_AFTER_DAYS' ) ) {
		return false;
	}

	$last_seen_timestamp = get_user_meta( $user_id, LAST_SEEN_META_KEY, true );
	if ( ! $last_seen_timestamp ) {
		return false;
	}

	return $last_seen_timestamp < get_inactivity_timestamp();
}

function get_inactivity_timestamp() {
	if ( ! defined( 'VIP_CONSIDER_USERS_INACTIVE_AFTER_DAYS' ) ) {
		return 0;
	}

	return strtotime( sprintf('-%d days', constant( 'VIP_CONSIDER_USERS_INACTIVE_AFTER_DAYS' ) ) ) + LAST_SEEN_UPDATE_USER_META_CACHE_TTL;
}
