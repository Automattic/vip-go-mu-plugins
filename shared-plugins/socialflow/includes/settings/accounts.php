<?php
/**
 * Holds the SocialFlow Admin Message settings class
 *
 * @package SocialFlow
 */
class SocialFlow_Admin_Settings_Accounts extends SocialFlow_Admin_Settings_Page {

	/**
	 * Add actions to manipulate messages
	 */
	function __construct() {
		global $socialflow;

		$this->slug = 'accounts';

		// Do nothing if application is not authorized
		if ( !$socialflow->is_authorized() )
			return;

		// add save filter
		add_filter( 'sf_save_settings', array( $this, 'save_settings' ) );

		// add save filter
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		// Add update notice
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * This is callback for admin_menu action fired in construct
	 *
	 * @since 2.1
	 * @access public
	 */
	function admin_menu() {
		
		add_submenu_page( 
			'socialflow',
			esc_attr__( 'Account Settings', 'socialflow' ),
			esc_attr__( 'Account Settings', 'socialflow' ),
			'manage_options',
			$this->slug,
			array( $this, 'page' )
		);
	}

	/**
	 * Render admin page with all accounts
	 */
	function page() {
		global $socialflow; ?>
		<div class="wrap socialflow">
			<h2><?php esc_html_e( 'Account Settings', 'socialflow' ); ?></h2>

			<form action="options.php" method="post">
				<?php $this->display_settings(); ?>
					<p><input type="submit" name="submit" class="button-primary" value="<?php esc_attr_e( 'Save Changes', 'socialflow' ) ?>" /></p>
				<?php settings_fields( 'socialflow' ); ?>
				<input type="hidden" value="accounts" name="socialflow-page" />
			</form>
		</div>
		<?php
	}

	/**
	 * Outputs HTML for "Accounts" settings tab
	 *
	 * @since 2.0
	 * @access public
	 */
	function display_settings() {
		global $socialflow;

		// Get only publishing accounts
		$accounts = $socialflow->accounts->get( array( array( 'key' => 'service_type', 'value' => 'publishing' ) ) ); ?>

		<?php if ( !$accounts ) : ?>
			<p><?php esc_html_e( "You don't have any accounts for publishing on SocialFlow.", 'socialflow' ) ?></p>
		<?php return; endif; ?>

		<table cellspacing="0" class="wp-list-table widefat fixed sf-accounts">
			<thead><tr>
				<th style="width:200px" class="manage-column column-username" id="username" scope="col">
					<span><?php esc_html_e( 'Username', 'socialflow' ) ?></span>
				</th>
				<th class="manage-column column-account-type" id="account-type" scope="col">
					<span><?php esc_html_e( 'Account type', 'socialflow' ) ?></span>
				</th>
				<th scope="col">
					<span><?php esc_html_e( 'Enable Account in Plugin', 'socialflow' ) ?></span>
				</th>
				<th scope="col">
					<span><?php esc_html_e( 'Send to by Default', 'socialflow' ) ?></span>
				</th>
			</tr></thead>

			<tbody class="list:user">
				<?php foreach ( $accounts as $account_id => $account ) : ?>
				<?php $show = in_array( $account_id, $socialflow->options->get( 'show', array() ) );
					  $send = in_array( $account_id, $socialflow->options->get( 'send', array() ) ); ?>
				<tr class="alternate">
					<td class="username column-username">
						<img width="32" height="32" class="avatar avatar-32 photo" src="<?php echo esc_url( $account['avatar'] ); ?>" alt="" />
						<strong><?php echo esc_html( $account['name'] ); ?></strong>
					</td>
					<td class="name column-account-type"><?php echo esc_html( ucfirst(str_replace('_', ' ', $account['account_type'] ) ) ); ?></td>
					<td style="padding: 9px 0 22px 15px;">
						<input type="checkbox" value="<?php echo esc_attr( $account['client_service_id'] ) ?>" class="sf-account-show" name="socialflow[show][]" <?php checked( true, $show ); ?> />
					</td>
					<td style="padding: 9px 0 22px 15px;">
						<input type="checkbox" value="<?php echo esc_attr( $account['client_service_id'] ); ?>" class="sf-account-send" name="socialflow[send][]" <?php checked( true, $send ); ?> />
					</td>
				</tr>
				<?php endforeach ?>
			</tbody>
		</table>
		
		<?php
	}

	/**
	 * Sanitizes settings
	 *
	 * Callback for "sf_save_settings" hook in method SocialFlow_Admin::save_settings()
	 *
	 * @see SocialFlow_Admin::save_settings()
	 * @since 2.0
	 * @access public
	 *
	 * @param string|array $settings Settings passed in from filter
	 * @return string|array Sanitized settings
	 */
	function save_settings( $settings ) {
		global $socialflow;
		$data = $_POST['socialflow'];

		if ( $socialflow->is_page( $this->slug ) ) {
			$settings['show'] = array_map( 'absint', $data['show'] );
			$settings['send'] = array_map( 'absint', $data['send'] );
		}

		return $settings;
	}
}