<?php

namespace Automattic\VIP\Helpers;

use WP_Error;

class User_Cleanup {
	/**
	 * @param string|null|false $emails_string
	 * @return array
	 */
	public static function parse_emails_string( $emails_string ) {
		$emails = [];

		$emails_string = trim( (string) $emails_string );

		if ( false !== strpos( $emails_string, ',' ) ) {
			$emails_raw = explode( ',', $emails_string );
		} else {
			$emails_raw = [ $emails_string ];
		}

		foreach ( $emails_raw as $email_raw ) {
			$email_raw = trim( $email_raw );

			$email_filtered = filter_var( $email_raw, FILTER_VALIDATE_EMAIL );

			if ( ! empty( $email_filtered ) ) {
				$emails[] = $email_filtered;
			}
		}

		return $emails;
	}

	public static function split_email( $email ) {
		list( $email_username, $email_hostname ) = explode( '@', $email );

		// Strip off everything after the +, if it exists so that we can run a wildcard match
		list( $email_username, ) = explode( '+', $email_username );

		return [ $email_username, $email_hostname ];
	}

	public static function fetch_user_ids_for_emails( $emails ) {
		global $wpdb;

		$email_sql_where_array = [];
		foreach ( $emails as $email ) {
			$email_username_hostname_split = self::split_email( $email );

			list( $email_username, $email_hostname ) = $email_username_hostname_split;

			$username_wildcard = $wpdb->esc_like( $email_username . '+' ) . '%';
			$hostname_wildcard = '%' . $wpdb->esc_like( '@' . $email_hostname );

			// phpcs:disable Squiz.PHP.CommentedOutCode.Found
			$email_sql_where_array[] = $wpdb->prepare(
				'( user_email = %s OR ( user_email LIKE %s AND user_email LIKE %s ) )',
				$email, // search for exact match
				$username_wildcard, // search for `username+*`
				$hostname_wildcard  // search for `*@example.com`
			);
			// phpcs:enable
		}

		$email_sql_where = implode( ' OR ', $email_sql_where_array );

		//phpcs:disable WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users -- gotta do the direct query for this :)
		//phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- already escaped earlier
		$sql = "SELECT ID FROM {$wpdb->users}
			LEFT JOIN {$wpdb->usermeta} ON {$wpdb->users}.ID = {$wpdb->usermeta}.user_id
			WHERE meta_key LIKE 'wp_%%capabilities'
			AND ( {$email_sql_where} )";
		//phpcs:enable WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_col( $sql );
		//phpcs:enable
	}

	public static function revoke_super_admin_for_users( $user_ids ) {
		$results = [];

		foreach ( $user_ids as $user_id ) {
			$revoked = false;

			if ( is_super_admin( $user_id ) ) {
				$revoked = revoke_super_admin( $user_id );
			}

			$results[ $user_id ] = $revoked;
		}

		return $results;
	}

	public static function revoke_roles_for_users( $user_ids ) {
		$results = [];

		foreach ( $user_ids as $user_id ) {
			$user = get_userdata( $user_id );

			if ( ! $user ) {
				$results[ $user_id ] = new WP_Error( 'not-found', 'User not found' );
				continue;
			}

			$user->remove_all_caps();

			$results[ $user_id ] = true;
		}

		return $results;
	}
}
