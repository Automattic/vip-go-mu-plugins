<?php
/**
 * Plugin Name: Lockouts and Warnings for VIP Go
 * Description: Displays a warning or lockout users from wp-admin
 * Author: Automattic
 * Author URI: http://automattic.com/
 */

class VIP_Lockout {

	/**
	 * VIP_Lockout constructor.
	 */
	public function __construct() {
	    add_action( 'admin_notices', [ $this, 'add_admin_notice' ], 1 );
		add_action( 'user_admin_notices', [ $this, 'add_admin_notice' ], 1 );
	}

	public function add_admin_notice() {
		if ( defined( 'VIP_LOCKOUT_STATE' ) ) {
			$user = wp_get_current_user();

			switch ( VIP_LOCKOUT_STATE ) {
				case 'warning':
                    $this->render_warning_notice( $user );
                    break;

				case 'locked':
				    $this->render_locked_notice();
					break;
			}
		}
	}

	protected function render_warning_notice( WP_User $user ) {
		if ( ! $user->has_cap( 'manage_options' ) ) {
			return;
		}

		?>
		<div id="lockout-warning" class="vp-notice notice-warning wrap clearfix" style="border-left-width:4px;border-left-style:solid;" >
            <div class="dashicons dashicons-warning" style="display:flex;float:left;margin-right:2rem;font-size:38px;align-items:center;margin-left:-20px;color:#ffb900;"></div>
            <div class="vp-message" style="display: flex;lign-items: center;" >
                <h3><?php _e( VIP_LOCKOUT_MESSAGE ); ?></h3>
            </div>
		</div>
		<?php
	}

	protected function render_locked_notice() {
		?>
        <div id="lockout-warning" class="vp-notice notice-error wrap clearfix" style="border-left-width:4px;border-left-style:solid;" >
            <div class="dashicons dashicons-warning" style="display:flex;float:left;margin-right:2rem;font-size:38px;align-items:center;margin-left:-20px;color:#dc3232;"></div>
            <div class="vp-message" style="display: flex;lign-items: center;" >
                <h3><?php _e( VIP_LOCKOUT_MESSAGE ); ?></h3>
            </div>
        </div>
		<?php
	}
}

new VIP_Lockout();