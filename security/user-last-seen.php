<?php

const LAST_SEEN_UPDATE_USER_META_CACHE_TTL = 60; // Store last seen once per minute to avoid too much write DB operations
const LAST_SEEN_UPDATE_USER_META_CACHE_KEY_PREFIX = 'vip_last_seen_update_user_meta_cache_key';

add_action( 'set_current_user', 'update_user_last_seen', 10, 0 );
add_filter ('manage_users_columns', 'users_columns' ) ;
add_filter ('manage_users_custom_column', 'users_custom_column', 10, 3 ) ;

function update_user_last_seen() {
	global $current_user;

	$cache_key = LAST_SEEN_UPDATE_USER_META_CACHE_KEY_PREFIX . $current_user->ID;

	try {
		if ( wp_cache_get( $cache_key ) ) {
			return;
		}

		update_user_meta( $current_user->ID, 'vip_last_seen', time() );

		wp_cache_set( $cache_key, true, null, LAST_SEEN_UPDATE_USER_META_CACHE_TTL );
	} catch ( \Exception $e ) {
		trigger_error(
			sprintf( 'failed to update user last seen meta', esc_html( $e->getMessage() ) ),
			E_USER_WARNING
		);
	}
}

function users_columns ($cols) {
	$cols['last_seen'] = __( 'Last seen' ) ;
	return $cols;
}

function users_custom_column ( $default, $column_name, $user_id ) {
	if ( 'last_seen' == $column_name ) {
		$last_seen_timestamp = get_user_meta( $user_id, 'vip_last_seen', true );

		if ( $last_seen_timestamp ) {
			$formatted_date = sprintf(
				__( '%1$s at %2$s' ),
				date_i18n( get_option('date_format'), $last_seen_timestamp ),
				date_i18n( get_option('time_format'), $last_seen_timestamp )
			);

			if ( defined( 'VIP_CONSIDER_USERS_INACTIVE_AFTER_DAYS' ) ) {
				$diff = ( new DateTime( sprintf('@%s', $last_seen_timestamp ) ) )->diff( new DateTime() );

				if ( $diff->days >= constant( 'VIP_CONSIDER_USERS_INACTIVE_AFTER_DAYS' ) ) {
					return sprintf( '<span class="wp-ui-text-notification">%s</span>', esc_html__( $formatted_date ) );
				}
			}

			return sprintf( '<span>%s</span>', esc_html__( $formatted_date ) );
		}
	}

	return $default;
}
