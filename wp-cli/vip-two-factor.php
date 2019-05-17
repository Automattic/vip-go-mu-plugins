<?php

class VIP_Two_Factor_Command extends WPCOM_VIP_CLI_Command {
	function report() {
		$fields = array( 'ID', 'display_name' );
		$users = get_users();
		$providers = Two_Factor_Core::get_providers();

		$cap = apply_filters( 'wpcom_vip_two_factor_enforcement_cap', 'edit_posts' );
		foreach ( $users as $user ) {
			$user->two_factor_enabled = 'false';
			$user->two_factor_providers = '';

			if ( Two_Factor_Core::is_user_using_two_factor( $user->ID ) ) {
				$user->two_factor_enabled = 'true';

				$user_providers = Two_Factor_Core::get_enabled_providers_for_user( $user );
				$user_providers = array_map( function( $provider ) use ( $providers ) {
					return $providers[ $provider ]->get_label();
				}, $user_providers );
				$user->two_factor_providers = implode( ', ', $user_providers );
			} else if ( ! user_can( $user->ID, $cap ) ) {
				$user->two_factor_enabled = 'n/a';
			}
		}

		$fields[] = 'two_factor_enabled';
		$fields[] = 'two_factor_providers';
		WP_CLI\Utils\format_items( 'table', $users, $fields );
	}
}

WP_CLI::add_command( 'vip two-factor', 'VIP_Two_Factor_Command' );
