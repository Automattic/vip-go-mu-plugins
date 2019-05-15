<?php

class VIP_Two_Factor_Command extends WPCOM_VIP_CLI_Command {
	function report() {
		$fields = array( 'ID', 'display_name' );
		$users = get_users( array( 'fields' => $fields ) );

		$cap = apply_filters( 'wpcom_vip_two_factor_enforcement_cap', 'edit_posts' );
		foreach ( $users as $user ) {
			if ( Two_Factor_Core::is_user_using_two_factor( $user->ID ) ) {
				$user->two_factor_enabled = 'true';
			} else if ( ! user_can( $user->ID, $cap ) ) {
				$user->two_factor_enabled = 'n/a';
			} else {
				$user->two_factor_enabled = 'false';
			}
		}

		$fields[] = 'two_factor_enabled';
		WP_CLI\Utils\format_items( 'table', $users, $fields );
	}
}

WP_CLI::add_command( 'vip two-factor', 'VIP_Two_Factor_Command' );
