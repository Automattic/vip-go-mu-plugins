<?php
namespace Automattic\VIP\Security;

class User_Last_Seen {
	const LAST_SEEN_META_KEY = 'wpvip_last_seen';
	const LAST_SEEN_CACHE_GROUP = 'wpvip_last_seen';
	const LAST_SEEN_UPDATE_USER_META_CACHE_TTL = MINUTE_IN_SECONDS * 5; // Store last seen once every five minute to avoid too many write DB operations
	const LAST_SEEN_RELEASE_DATE_TIMESTAMP_OPTION_KEY = 'wpvip_last_seen_release_date_timestamp';

	public function init() {
		if ( ! defined( 'VIP_SECURITY_INACTIVE_USERS_ACTION' ) || constant( 'VIP_SECURITY_INACTIVE_USERS_ACTION' ) === 'NO_ACTION' ) {
			return;
		}

		// Use a global cache group to avoid having to the data for each site
		wp_cache_add_global_groups( array( self::LAST_SEEN_CACHE_GROUP ) );

		add_action( 'admin_init', array( $this, 'register_release_date' ) );

		add_filter( 'determine_current_user', array( $this, 'determine_current_user' ), 30, 1 );
		add_filter( 'authenticate', array( $this, 'authenticate' ), 20, 1 );

		if ( in_array( constant( 'VIP_SECURITY_INACTIVE_USERS_ACTION' ), array( 'REPORT', 'BLOCK' ) ) ) {
			add_filter( 'wpmu_users_columns', array( $this, 'add_last_seen_column_head' ) );
			add_filter( 'manage_users_columns', array( $this, 'add_last_seen_column_head' ) );
			add_filter( 'manage_users_custom_column', array( $this, 'add_last_seen_column_date' ), 10, 3 );

			add_filter( 'manage_users_sortable_columns', array( $this, 'add_last_seen_sortable_column' ) );
			add_filter( 'manage_users-network_sortable_columns', array( $this, 'add_last_seen_sortable_column' ) );
			add_filter( 'users_list_table_query_args', array( $this, 'last_seen_order_by_query_args') );
		}

		if ( $this->is_block_action_enabled() ) {
			add_filter( 'views_users', array( $this, 'add_blocked_users_filter' ) );
			add_filter( 'views_users-network', array( $this, 'add_blocked_users_filter' ) );
			add_filter( 'users_list_table_query_args', array( $this, 'last_seen_blocked_users_filter_query_args') );

			add_action( 'admin_init', array( $this, 'last_seen_unblock_action' ) );
		}
	}

	public function determine_current_user( $user_id ) {
		if ( ! $user_id ) {
			return $user_id;
		}

		if ( wp_cache_get( $user_id, self::LAST_SEEN_CACHE_GROUP ) ) {
			// Last seen meta was checked recently
			return $user_id;
		}

		if ( $this->is_block_action_enabled() && $this->is_considered_inactive( $user_id ) ) {
			// Force current user to 0 to avoid recursive calls to this filter
			wp_set_current_user( 0 );

			return new \WP_Error( 'inactive_account', __( '<strong>Error</strong>: Your account has been flagged as inactive. Please contact your site administrator.', 'inactive-account-lockdown' ) );
		}

		if ( wp_cache_add( $user_id, true, self::LAST_SEEN_CACHE_GROUP, self::LAST_SEEN_UPDATE_USER_META_CACHE_TTL ) ) {
			update_user_meta( $user_id, self::LAST_SEEN_META_KEY, time() );
		}

		return $user_id;
	}

	public function authenticate( $user ) {
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		if ( $user->ID && $this->is_block_action_enabled() && $this->is_considered_inactive( $user->ID ) ) {
			return new \WP_Error( 'inactive_account', __( '<strong>Error</strong>: Your account has been flagged as inactive. Please contact your site administrator.', 'inactive-account-lockdown' ) );;
		}

		return $user;
	}

	public function add_last_seen_column_head( $columns ) {
		$columns[ 'last_seen' ] = __( 'Last seen' );
		return $columns;
	}

	public function add_last_seen_sortable_column( $columns ) {
		$columns['last_seen'] = 'last_seen';

		return $columns;
	}

	public function last_seen_order_by_query_args( $vars ) {
		if ( isset( $vars['orderby'] ) && $vars['orderby'] === 'last_seen' ) {
			$vars[ 'meta_key' ] = self::LAST_SEEN_META_KEY;
			$vars[ 'orderby' ] = 'meta_value_num';
		}

		return $vars;
	}

	public function last_seen_blocked_users_filter_query_args($vars ) {
		if ( isset( $_REQUEST[ 'last_seen_filter' ] ) && $_REQUEST[ 'last_seen_filter' ] === 'blocked' ) {
			$vars[ 'meta_key' ] = self::LAST_SEEN_META_KEY;
			$vars[ 'meta_value' ] = $this->get_inactivity_timestamp();
			$vars[ 'meta_type' ] = 'NUMERIC';
			$vars[ 'meta_compare' ] = '<';
		}

		return $vars;
	}

	public function add_last_seen_column_date( $default, $column_name, $user_id ) {
		if ( 'last_seen' !== $column_name ) {
			return $default;
		}

		$last_seen_timestamp = get_user_meta( $user_id, self::LAST_SEEN_META_KEY, true );

		if ( ! $last_seen_timestamp ) {
			return $default;
		}

		$formatted_date = sprintf(
			__( '%1$s at %2$s' ),
			date_i18n( get_option('date_format'), $last_seen_timestamp ),
			date_i18n( get_option('time_format'), $last_seen_timestamp )
		);

		if ( ! $this->is_block_action_enabled() || ! $this->is_considered_inactive( $user_id ) ) {
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
	}

	public function add_blocked_users_filter( $views ) {
		$blog_id = is_network_admin() ? null : get_current_blog_id();

		$users_query = new \WP_User_Query(
			array(
				'blog_id' => $blog_id,
				'fields'  => 'ID',
				'meta_key' => self::LAST_SEEN_META_KEY,
				'meta_value' => $this->get_inactivity_timestamp(),
				'meta_type' => 'NUMERIC',
				'meta_compare' => '<',
				'count_total' => true,
			),
		);
		$count      = (int) $users_query->get_total();

		$view = __( 'Blocked Users' );
		if ( $count ) {
			$class = isset( $_REQUEST[ 'last_seen_filter' ] ) ? 'current' : '';

			$url = add_query_arg( array(
				'last_seen_filter' => 'blocked',
			) );

			$view = '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $view ) . '</a>';
		}
		$views['blocked_users'] = $view . ' (' . $count . ')';

		return $views;
	}

	public function last_seen_unblock_action() {
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

		if ( ! $error && ! delete_user_meta( $user_id, self::LAST_SEEN_META_KEY ) ) {
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
	}

	public function register_release_date() {
		if ( ! get_option( self::LAST_SEEN_RELEASE_DATE_TIMESTAMP_OPTION_KEY ) ) {
			// Right after the first admin_init, set the release date timestamp
			// to be used as a fallback for users that never logged in before.
			add_option( self::LAST_SEEN_RELEASE_DATE_TIMESTAMP_OPTION_KEY, time() );
		}
	}

	public function is_considered_inactive( $user_id ) {
		$last_seen_timestamp = get_user_meta( $user_id, self::LAST_SEEN_META_KEY, true );
		if ( $last_seen_timestamp ) {
			return $last_seen_timestamp < $this->get_inactivity_timestamp();
		}

		$release_date_timestamp = get_option( self::LAST_SEEN_RELEASE_DATE_TIMESTAMP_OPTION_KEY );
		if ( $release_date_timestamp ) {
			return $release_date_timestamp < $this->get_inactivity_timestamp();
		}

		// Release date is not defined yed, so we can't consider the user inactive.
		return false;
	}

	public function get_inactivity_timestamp() {
		return strtotime( sprintf('-%d days', constant( 'VIP_SECURITY_CONSIDER_USERS_INACTIVE_AFTER_DAYS' ) ) ) + self::LAST_SEEN_UPDATE_USER_META_CACHE_TTL;
	}

	private function is_block_action_enabled() {
		return defined( 'VIP_SECURITY_CONSIDER_USERS_INACTIVE_AFTER_DAYS' ) &&
			defined( 'VIP_SECURITY_INACTIVE_USERS_ACTION' ) &&
			constant( 'VIP_SECURITY_INACTIVE_USERS_ACTION' ) === 'BLOCK';
	}
}
