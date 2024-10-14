<?php
namespace Automattic\VIP\Security;

use Automattic\VIP\Utils\Context;

class User_Last_Seen {
	const LAST_SEEN_META_KEY                               = 'wpvip_last_seen';
	const LAST_SEEN_IGNORE_INACTIVITY_CHECK_UNTIL_META_KEY = 'wpvip_last_seen_ignore_inactivity_check_until';
	const LAST_SEEN_CACHE_GROUP                            = 'wpvip_last_seen';
	const LAST_SEEN_UPDATE_USER_META_CACHE_TTL             = MINUTE_IN_SECONDS * 5; // Store last seen once every five minute to avoid too many write DB operations
	const LAST_SEEN_RELEASE_DATE_TIMESTAMP_OPTION_KEY      = 'wpvip_last_seen_release_date_timestamp';

	/**
	 * May store inactive account authentication error for application passwords to be used later in rest_authentication_errors
	 *
	 * @var \WP_Error|null
	 */
	private $application_password_authentication_error;

	public function init() {
		if ( ! defined( 'VIP_SECURITY_INACTIVE_USERS_ACTION' ) || constant( 'VIP_SECURITY_INACTIVE_USERS_ACTION' ) === 'NO_ACTION' ) {
			return;
		}

		$this->release_date = get_option( self::LAST_SEEN_RELEASE_DATE_TIMESTAMP_OPTION_KEY );

		// Use a global cache group since users are shared among network sites.
		wp_cache_add_global_groups( array( self::LAST_SEEN_CACHE_GROUP ) );

		add_filter( 'determine_current_user', array( $this, 'record_activity' ), 30, 1 );

		add_action( 'admin_init', array( $this, 'register_release_date' ) );
		add_action( 'set_user_role', array( $this, 'user_promoted' ) );
		add_action( 'vip_support_user_added', function ( $user_id ) {
			$ignore_inactivity_check_until = strtotime( '+2 hours' );

			$this->ignore_inactivity_check_for_user( $user_id, $ignore_inactivity_check_until );
		} );

		if ( in_array( constant( 'VIP_SECURITY_INACTIVE_USERS_ACTION' ), array( 'REPORT', 'BLOCK' ) ) ) {
			add_filter( 'wpmu_users_columns', array( $this, 'add_last_seen_column_head' ) );
			add_filter( 'manage_users_columns', array( $this, 'add_last_seen_column_head' ) );
			add_filter( 'manage_users_custom_column', array( $this, 'add_last_seen_column_date' ), 10, 3 );

			add_filter( 'manage_users_sortable_columns', array( $this, 'add_last_seen_sortable_column' ) );
			add_filter( 'manage_users-network_sortable_columns', array( $this, 'add_last_seen_sortable_column' ) );
			add_filter( 'users_list_table_query_args', array( $this, 'last_seen_order_by_query_args' ) );
		}

		if ( $this->is_block_action_enabled() ) {
			add_filter( 'authenticate', array( $this, 'authenticate' ), 20, 1 );
			add_filter( 'wp_is_application_passwords_available_for_user', array( $this, 'application_password_authentication' ), PHP_INT_MAX, 2 );
			add_filter( 'rest_authentication_errors', array( $this, 'rest_authentication_errors' ), PHP_INT_MAX, 1 );

			add_filter( 'views_users', array( $this, 'add_blocked_users_filter' ) );
			add_filter( 'views_users-network', array( $this, 'add_blocked_users_filter' ) );
			add_filter( 'users_list_table_query_args', array( $this, 'last_seen_blocked_users_filter_query_args' ) );

			add_action( 'admin_init', array( $this, 'last_seen_unblock_action' ) );
		}
	}

	public function record_activity( $user_id ) {
		if ( ! $user_id ) {
			return $user_id;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return $user_id;
		}

		if ( $this->is_considered_inactive( $user_id ) ) {
			// User needs to be unblocked first
			return $user_id;
		}

		if ( wp_cache_get( $user_id, self::LAST_SEEN_CACHE_GROUP ) ) {
			// Last seen meta was checked recently
			return $user_id;
		}

		// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined
		if ( wp_cache_add( $user_id, true, self::LAST_SEEN_CACHE_GROUP, self::LAST_SEEN_UPDATE_USER_META_CACHE_TTL ) ) {
			update_user_meta( $user_id, self::LAST_SEEN_META_KEY, time() );
		}

		return $user_id;
	}

	public function authenticate( $user ) {
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		if ( $user->ID && $this->is_considered_inactive( $user->ID ) ) {
			if ( Context::is_xmlrpc_api() ) {
				add_filter('xmlrpc_login_error', function () {
					return new \IXR_Error( 403, __( 'Your account has been flagged as inactive. Please contact your site administrator.', 'wpvip' ) );
				});
			}

			return new \WP_Error( 'inactive_account', __( '<strong>Error</strong>: Your account has been flagged as inactive. Please contact your site administrator.', 'wpvip' ) );
		}

		return $user;
	}

	public function rest_authentication_errors( $status ) {
		if ( is_wp_error( $this->application_password_authentication_error ) ) {
			return $this->application_password_authentication_error;
		}

		return $status;
	}

	/**
	 * @param bool $available True if application password is available, false otherwise.
	 * @param \WP_User $user The user to check.
	 * @return bool
	 */
	public function application_password_authentication( $available, $user ) {
		if ( ! $available || ( $user && ! $user->exists() ) ) {
			return false;
		}

		if ( $this->is_considered_inactive( $user->ID ) ) {
			$this->application_password_authentication_error = new \WP_Error( 'inactive_account', __( 'Your account has been flagged as inactive. Please contact your site administrator.', 'wpvip' ), array( 'status' => 403 ) );

			return false;
		}

		return $available;
	}

	public function add_last_seen_column_head( $columns ) {
		$columns['last_seen'] = __( 'Last seen', 'wpvip' );
		return $columns;
	}

	public function add_last_seen_sortable_column( $columns ) {
		$columns['last_seen'] = 'last_seen';

		return $columns;
	}

	public function last_seen_order_by_query_args( $vars ) {
		if ( isset( $vars['orderby'] ) && 'last_seen' === $vars['orderby'] ) {
			$vars['meta_key'] = self::LAST_SEEN_META_KEY;
			$vars['orderby']  = 'meta_value_num';
		}

		return $vars;
	}

	public function last_seen_blocked_users_filter_query_args( $vars ) {
		if ( isset( $_GET['last_seen_filter'] ) && 'blocked' === $_GET['last_seen_filter'] && isset( $_GET['last_seen_filter_nonce'] ) && wp_verify_nonce( sanitize_text_field( $_GET['last_seen_filter_nonce'] ), 'last_seen_filter' ) ) {
			$vars['meta_key'] = self::LAST_SEEN_META_KEY;
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			$vars['meta_value']   = $this->get_inactivity_timestamp();
			$vars['meta_type']    = 'NUMERIC';
			$vars['meta_compare'] = '<';
		}

		return $vars;
	}

	public function add_last_seen_column_date( $default, $column_name, $user_id ) {
		if ( 'last_seen' !== $column_name ) {
			return $default;
		}

		$last_seen_timestamp = get_user_meta( $user_id, self::LAST_SEEN_META_KEY, true );

		$date = __( 'Indeterminate', 'wpvip' );
		if ( $last_seen_timestamp ) {
			$date = sprintf(
				/* translators: 1: Comment date, 2: Comment time. */
				__( '%1$s at %2$s' ),
				date_i18n( get_option( 'date_format' ), $last_seen_timestamp ),
				date_i18n( get_option( 'time_format' ), $last_seen_timestamp )
			);
		}

		if ( ! $this->is_block_action_enabled() || ! $this->is_considered_inactive( $user_id ) ) {
			return sprintf( '<span>%s</span>', esc_html( $date ) );
		}

		$unblock_link = '';
		if ( current_user_can( 'edit_user', $user_id ) ) {
			$url = add_query_arg( array(
				'action'                => 'reset_last_seen',
				'user_id'               => $user_id,
				'reset_last_seen_nonce' => wp_create_nonce( 'reset_last_seen_action' ),
			) );

			$unblock_link = "<div class='row-actions'><span>User blocked due to inactivity. <a class='reset_last_seen_action' href='" . esc_url( $url ) . "'>" . __( 'Unblock', 'wpvip' ) . '</a></span></div>';
		}
		return sprintf( '<span class="wp-ui-text-notification">%s</span>' . $unblock_link, esc_html( $date ) );
	}

	public function add_blocked_users_filter( $views ) {
		$blog_id = is_network_admin() ? null : get_current_blog_id();

		$users_query = new \WP_User_Query(
			array(
				'blog_id'      => $blog_id,
				'fields'       => 'ID',
				'meta_key'     => self::LAST_SEEN_META_KEY,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'meta_value'   => $this->get_inactivity_timestamp(),
				'meta_type'    => 'NUMERIC',
				'meta_compare' => '<',
				'count_total'  => false,
				'number'       => 1, // To minimize the query time, we only need to know if there are any blocked users to show the link
			),
		);

		$views['blocked_users'] = __( 'Blocked Users', 'wpvip' );

		if ( ! $users_query->get_results() ) {
			return $views;
		}

		$url = add_query_arg( array(
			'last_seen_filter'       => 'blocked',
			'last_seen_filter_nonce' => wp_create_nonce( 'last_seen_filter' ),
		) );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$class = isset( $_GET['last_seen_filter'] ) ? 'current' : '';

		$view = '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $views['blocked_users'] ) . '</a>';

		$views['blocked_users'] = $view;

		return $views;
	}

	public function last_seen_unblock_action() {
		$admin_notices_hook_name = is_network_admin() ? 'network_admin_notices' : 'admin_notices';

		if ( isset( $_GET['reset_last_seen_success'] ) && '1' === $_GET['reset_last_seen_success'] ) {
			add_action( $admin_notices_hook_name, function () {
				$class = 'notice notice-success is-dismissible';
				$error = __( 'User unblocked.', 'wpvip' );

				printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $error ) );
			} );
		}

		if ( ! isset( $_GET['user_id'], $_GET['action'] ) || 'reset_last_seen' !== $_GET['action'] ) {
			return;
		}

		$user_id = absint( $_GET['user_id'] );

		$error = null;
		if ( ! isset( $_GET['reset_last_seen_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_GET['reset_last_seen_nonce'] ), 'reset_last_seen_action' ) ) {
			$error = __( 'Unable to verify your request', 'wpvip' );
		}

		if ( ! get_userdata( $user_id ) ) {
			$error = __( 'User not found.', 'wpvip' );
		}

		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			$error = __( 'You do not have permission to unblock this user.', 'wpvip' );
		}

		$ignore_inactivity_check_until = strtotime( '+2 days' );
		if ( ! $error && ! $this->ignore_inactivity_check_for_user( $user_id, $ignore_inactivity_check_until ) ) {
			$error = __( 'Unable to unblock user.', 'wpvip' );
		}

		if ( $error ) {
			add_action( $admin_notices_hook_name, function () use ( $error ) {
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

		wp_safe_redirect( $url );
		exit();
	}

	public function ignore_inactivity_check_for_user( $user_id, $until_timestamp = null ) {
		if ( ! $until_timestamp ) {
			$until_timestamp = strtotime( '+2 days' );
		}

		return update_user_meta( $user_id, self::LAST_SEEN_IGNORE_INACTIVITY_CHECK_UNTIL_META_KEY, $until_timestamp );
	}

	public function user_promoted( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			throw new \Exception( 'User not found' );
		}

		if ( ! $this->user_with_elevated_capabilities( $user ) ) {
			return;
		}

		$this->ignore_inactivity_check_for_user( $user_id );
	}

	public function register_release_date() {
		if ( ! wp_doing_ajax() && ! get_option( self::LAST_SEEN_RELEASE_DATE_TIMESTAMP_OPTION_KEY ) ) {
			// Right after the first admin_init, set the release date timestamp
			// to be used as a fallback for users that never logged in before.
			add_option( self::LAST_SEEN_RELEASE_DATE_TIMESTAMP_OPTION_KEY, time(), '', 'no' );
		}
	}

	public function is_considered_inactive( $user_id ) {
		if ( ! $this->should_check_user_last_seen( $user_id ) ) {
			return false;
		}

		$ignore_inactivity_check_until = get_user_meta( $user_id, self::LAST_SEEN_IGNORE_INACTIVITY_CHECK_UNTIL_META_KEY, true );
		if ( $ignore_inactivity_check_until && $ignore_inactivity_check_until > time() ) {
			return false;
		}

		$last_seen_timestamp = get_user_meta( $user_id, self::LAST_SEEN_META_KEY, true );
		if ( $last_seen_timestamp ) {
			return $last_seen_timestamp < $this->get_inactivity_timestamp();
		}

		$release_date_timestamp = get_option( self::LAST_SEEN_RELEASE_DATE_TIMESTAMP_OPTION_KEY );
		if ( $release_date_timestamp ) {
			return $release_date_timestamp < $this->get_inactivity_timestamp();
		}

		// Release date is not defined yet, so we can't consider the user inactive.
		return false;
	}

	private function get_inactivity_timestamp() {
		$days = defined( 'VIP_SECURITY_CONSIDER_USERS_INACTIVE_AFTER_DAYS' ) ? absint( constant( 'VIP_SECURITY_CONSIDER_USERS_INACTIVE_AFTER_DAYS' ) ) : 90;

		return strtotime( sprintf( '-%d days', $days ) ) + self::LAST_SEEN_UPDATE_USER_META_CACHE_TTL;
	}

	private function is_block_action_enabled() {
		return defined( 'VIP_SECURITY_INACTIVE_USERS_ACTION' ) && constant( 'VIP_SECURITY_INACTIVE_USERS_ACTION' ) === 'BLOCK';
	}

	private function should_check_user_last_seen( $user_id ) {
		/**
		 * Filters the users that should be skipped when checking/recording the last seen.
		 *
		 * @param array $skip_users The list of user IDs to skip.
		 */
		$skip_users = apply_filters( 'vip_security_last_seen_skip_users', array() );
		if ( in_array( $user_id, $skip_users ) ) {
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			throw new \Exception( sprintf( 'User #%d found', esc_html( $user_id ) ) );
		}

		if ( $user->user_registered && strtotime( $user->user_registered ) > $this->get_inactivity_timestamp() ) {
			return false;
		}

		return $this->user_with_elevated_capabilities( $user );
	}

	private function user_with_elevated_capabilities( $user ) {
		/**
		 * Filters the last seen elevated capabilities that are used to determine if the last seen should be checked.
		 *
		 * @param array $elevated_capabilities The elevated capabilities.
		 */
		$elevated_capabilities = apply_filters( 'vip_security_last_seen_elevated_capabilities', [
			'edit_posts',
			'delete_posts',
			'publish_posts',
			'edit_pages',
			'delete_pages',
			'publish_pages',
			'edit_others_posts',
			'edit_others_pages',
			'manage_options',
			'edit_users',
			'promote_users',
			'activate_plugins',
			'manage_network',
		] );

		// Prevent infinite loops inside user_can() due to other security logic.
		if ( is_automattician( $user->ID ) ) {
			return true;
		}

		foreach ( $elevated_capabilities as $elevated_capability ) {
			if ( user_can( $user, $elevated_capability ) ) {
				return true;
			}
		}

		return false;
	}
}
