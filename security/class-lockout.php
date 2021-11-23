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
		if ( defined( 'VIP_LOCKOUT_STATE' ) && defined( 'VIP_LOCKOUT_MESSAGE' ) ) {
			add_action( 'admin_notices', [ $this, 'add_admin_notice' ], 1 );
			add_action( 'user_admin_notices', [ $this, 'add_admin_notice' ], 1 );

			add_filter( 'user_has_cap', [ $this, 'filter_user_has_cap' ], PHP_INT_MAX, 4 );
			add_filter( 'pre_site_option_site_admins', [ $this, 'filter_site_admin_option' ], PHP_INT_MAX );
			add_filter( 'pre_update_site_option_site_admins', [ $this, 'filter_prevent_site_admin_option_updates' ], PHP_INT_MAX, 2 );
		}
	}

	/**
	 * Add warnings to admin page
	 */
	public function add_admin_notice() {
		if ( defined( 'VIP_LOCKOUT_STATE' ) ) {
			$user = wp_get_current_user();

			switch ( constant( 'VIP_LOCKOUT_STATE' ) ) {
				case 'warning':
					$show_notice = apply_filters( 'vip_lockout_show_notice', $user->has_cap( 'manage_options' ), constant( 'VIP_LOCKOUT_STATE' ), $user );
					if ( $show_notice ) {
						$this->render_warning_notice();

						$this->user_seen_notice( $user );
					}

					break;

				case 'locked':
					$show_notice = apply_filters( 'vip_lockout_show_notice', $user->has_cap( 'edit_posts' ), constant( 'VIP_LOCKOUT_STATE' ), $user );
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
			add_user_meta( $user->ID, self::USER_SEEN_WARNING_KEY, constant( 'VIP_LOCKOUT_STATE' ), true );
			// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- not sure if it is safe to replace with gmdate()
			add_user_meta( $user->ID, self::USER_SEEN_WARNING_TIME_KEY, date( 'Y-m-d H:i:s' ), true );
		}
	}

	protected function render_warning_notice() {
		?>
		<div id="lockout-warning" class="notice-warning wrap clearfix" style="align-items: center;background: #ffffff;border-left-width:4px;border-left-style:solid;border-radius: 6px;display: flex;margin-top: 30px;padding: 30px;line-height: 2em;">
			<div class="dashicons dashicons-warning" style="display:flex;float:left;margin-right:2rem;font-size:38px;align-items:center;margin-left:-20px;color:#ffb900;"></div>
			<div style="display: flex;align-items: center;" >
				<h3><?php echo wp_kses_post( constant( 'VIP_LOCKOUT_MESSAGE' ) ); ?></h3>
			</div>
		</div>
		<?php
	}

	protected function render_locked_notice() {
		?>
		<div id="lockout-warning" class="notice-error wrap clearfix" style="align-items: center;background: #ffffff;border-left-width:4px;border-left-style:solid;border-radius: 6px;display: flex;margin-top: 30px;padding: 30px;line-height: 2em;">
			<div class="dashicons dashicons-warning" style="display:flex;float:left;margin-right:2rem;font-size:38px;align-items:center;margin-left:-20px;color:#dc3232;"></div>
			<div style="display: flex;align-items: center;" >
				<h3><?php echo wp_kses_post( constant( 'VIP_LOCKOUT_MESSAGE' ) ); ?></h3>
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
		if ( defined( 'VIP_LOCKOUT_STATE' ) && 'locked' === constant( 'VIP_LOCKOUT_STATE' ) ) {
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
		if ( defined( 'VIP_LOCKOUT_STATE' ) && 'locked' === constant( 'VIP_LOCKOUT_STATE' ) ) {
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
		if ( defined( 'VIP_LOCKOUT_STATE' ) && 'locked' === constant( 'VIP_LOCKOUT_STATE' ) ) {
			return $old_value;
		}

		return $value;
	}
}

new Lockout();
