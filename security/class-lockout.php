<?php
/**
 * Plugin Name: Lockouts and Warnings for VIP Go
 * Description: Displays a warning or lockout users from wp-admin
 * Author: Automattic
 * Author URI: http://automattic.com/
 */

namespace Automattic\VIP\Security;

class Lockout {

	const USER_SEEN_WARNING_KEY = 'seen_lockout_warning';

	const USER_SEEN_WARNING_TIME_KEY = 'seen_lockout_warning_time';

	const ACCOUNT_STATUS_NORMAL   = 'normal';
	const ACCOUNT_STATUS_WARNING  = 'warned';
	const ACCOUNT_STATUS_LOCK     = 'locked';
	const ACCOUNT_STATUS_SHUTDOWN = 'shutdown';

	/**
	 * @var array Default user capabilities for locked state
	 */
	public $locked_cap = [
		'read'    => true,
		'level_0' => true,
	];

	/**
	 * Lockout constructor.
	 */
	public function __construct() {
		if ( $this->get_lockout_state() ) {
			add_action( 'admin_notices', [ $this, 'add_admin_notice' ], 1 );
			add_action( 'user_admin_notices', [ $this, 'add_admin_notice' ], 1 );

			add_filter( 'user_has_cap', [ $this, 'filter_user_has_cap' ], PHP_INT_MAX, 4 );
			add_filter( 'pre_site_option_site_admins', [ $this, 'filter_site_admin_option' ], PHP_INT_MAX );
			add_filter( 'pre_update_site_option_site_admins', [ $this, 'filter_prevent_site_admin_option_updates' ], PHP_INT_MAX, 2 );
		}
	}

	private function get_lockout_state() {
		// VIP_ACCOUNT_STATUS has precedence over VIP_LOCKOUT_STATE
		if ( defined( 'VIP_ACCOUNT_STATUS' ) && constant( 'VIP_ACCOUNT_STATUS' ) !== self::ACCOUNT_STATUS_NORMAL ) {
			return constant( 'VIP_ACCOUNT_STATUS' );
		}

		return defined( 'VIP_LOCKOUT_STATE' ) ? constant( 'VIP_LOCKOUT_STATE' ) : false;
	}

	private function get_lockout_message() {
		// If the account is locked, use the proper lockout message
		if ( defined( 'VIP_ACCOUNT_STATUS' ) && constant( 'VIP_ACCOUNT_STATUS' ) !== self::ACCOUNT_STATUS_NORMAL ) {
			switch ( $this->get_lockout_state() ) {
				case self::ACCOUNT_STATUS_WARNING:
					return 'Payment for this WordPress VIP account is overdue and access will be suspended soon.<br />
Please contact <a href="mailto:accounts@wpvip.com">accounts@wpvip.com</a> to settle your bill.';
				case self::ACCOUNT_STATUS_LOCK:
				case self::ACCOUNT_STATUS_SHUTDOWN:
					return 'Payment for this WordPress VIP account is overdue and access has been suspended.<br />
Please contact <a href="mailto:accounts@wpvip.com">accounts@wpvip.com</a> to settle your bill and restore access.';
			}
		}
		// Otherwise, read it from VIP_LOCKOUT_MESSAGE constant
		return defined( 'VIP_LOCKOUT_MESSAGE' ) ? constant( 'VIP_LOCKOUT_MESSAGE' ) : false;
	}

	/**
	 * Add warnings to admin page
	 */
	public function add_admin_notice() {
		$lockout_state = $this->get_lockout_state();
		if ( $lockout_state ) {
			$user = wp_get_current_user();

			switch ( $lockout_state ) {
				case self::ACCOUNT_STATUS_WARNING:
					$has_caps    = isset( $user->allcaps['manage_options'] ) && true === $user->allcaps['manage_options'];
					$show_notice = apply_filters( 'vip_lockout_show_notice', $has_caps || is_automattician(), $lockout_state, $user );

					if ( $show_notice ) {
						$this->render_warning_notice();

						$this->user_seen_notice( $user );
					}

					break;

				case self::ACCOUNT_STATUS_LOCK:
				case self::ACCOUNT_STATUS_SHUTDOWN:
					$has_caps    = isset( $user->allcaps['edit_posts'] ) && true === $user->allcaps['edit_posts'];
					$show_notice = apply_filters( 'vip_lockout_show_notice', $has_caps || is_automattician(), $lockout_state, $user );

					if ( $show_notice ) {
						$this->render_locked_notice();

						$this->user_seen_notice( $user );
					}

					break;
			}
		}
	}

	/**
	 * Mark that user has seen warning
	 *
	 * @param \WP_User $user
	 */
	protected function user_seen_notice( \WP_User $user ) {
		$seen_warning = get_user_meta( $user->ID, self::USER_SEEN_WARNING_KEY, true );

		if ( ! $seen_warning ) {
			add_user_meta( $user->ID, self::USER_SEEN_WARNING_KEY, $this->get_lockout_state(), true );
			// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- not sure if it is safe to replace with gmdate()
			add_user_meta( $user->ID, self::USER_SEEN_WARNING_TIME_KEY, date( 'Y-m-d H:i:s' ), true );
		}
	}

	protected function render_warning_notice() {
		?>
		<div id="lockout-warning" class="notice-warning wrap clearfix" style="align-items: center;background: #ffffff;border-left-width:4px;border-left-style:solid;border-radius: 6px;display: flex;margin-top: 30px;padding: 30px;line-height: 2em;">
			<div class="dashicons dashicons-warning" style="display:flex;float:left;margin-right:2rem;font-size:38px;align-items:center;margin-left:-20px;color:#ffb900;"></div>
			<div style="display: flex;align-items: center;" >
				<h3><?php echo wp_kses_post( $this->get_lockout_message() ); ?></h3>
			</div>
		</div>
		<?php
	}

	protected function render_locked_notice() {
		?>
		<div id="lockout-warning" class="notice-error wrap clearfix" style="align-items: center;background: #ffffff;border-left-width:4px;border-left-style:solid;border-radius: 6px;display: flex;margin-top: 30px;padding: 30px;line-height: 2em;">
			<div class="dashicons dashicons-warning" style="display:flex;float:left;margin-right:2rem;font-size:38px;align-items:center;margin-left:-20px;color:#dc3232;"></div>
			<div style="display: flex;align-items: center;" >
				<h3><?php echo wp_kses_post( $this->get_lockout_message() ); ?></h3>
			</div>
		</div>
		<?php
	}

	/**
	 * Filter the result of user capability check
	 *
	 * If site is in lockout mode then all user will only have capabilities of a subscriber.
	 *
	 * @param array $user_caps
	 * @param array $caps
	 * @param array $args
	 * @param \WP_User $user
	 *
	 * @return array
	 */
	public function filter_user_has_cap( $user_caps, $caps, $args, $user ) {
		$lockout_state = $this->get_lockout_state();
		if ( $lockout_state && in_array( $lockout_state, [ self::ACCOUNT_STATUS_LOCK, self::ACCOUNT_STATUS_SHUTDOWN ] ) ) {
			if ( is_automattician( $user->ID ) ) {
				return $user_caps;
			}

			$subscriber = get_role( 'subscriber' );
			if ( null !== $subscriber ) {
				$this->locked_cap = $subscriber->capabilities;
			}

			return array_intersect_key( $user_caps, (array) $this->locked_cap );
		}

		return $user_caps;
	}

	/**
	 * Filter site admin options
	 *
	 * Ensure that site admin is empty if site is in `locked` state
	 *
	 * @param   mixed   $pre_option
	 * @param   string  $option
	 * @param   int     $network_id
	 * @param   mixed   $default
	 *
	 * @return  array
	 */
	public function filter_site_admin_option( $pre_option ) {
		$lockout_state = $this->get_lockout_state();
		if ( $lockout_state && in_array( $lockout_state, [ self::ACCOUNT_STATUS_LOCK, self::ACCOUNT_STATUS_SHUTDOWN ] ) ) {
			if ( is_automattician() ) {
				return $pre_option;
			}

			return [];
		}

		return $pre_option;
	}

	/**
	 * Don't allow updates to the site_admins option.
	 *
	 * When a site is locked, we filter the site_admins list to limit super powers
	 * to VIP Support users. However, if (grant|revoke)_super_admin are called, that
	 * ends up clearing out the list, which is not ideal.
	 *
	 * Instead, just block updates to the option if a site is locked.
	 */
	public function filter_prevent_site_admin_option_updates( $value, $old_value ) {
		$lockout_state = $this->get_lockout_state();
		if ( $lockout_state && in_array( $lockout_state, [ self::ACCOUNT_STATUS_LOCK, self::ACCOUNT_STATUS_SHUTDOWN ] ) ) {
			return $old_value;
		}

		return $value;
	}
}

new Lockout();
