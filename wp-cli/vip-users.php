<?php
class VIP_Go_Users_Command extends WPCOM_VIP_CLI_Command {
	/**
	 * Changes the WordPress username (user_login)
	 *
	 * A lot of this functionality is borrowed or inspired from https://wordpress.org/plugins/username-changer/
	 *
	 * ## OPTIONS
	 *
	 * <username>
	 * : The existing username of the user to rename.
	 *
	 * <newname>
	 * : The new username to use.
	 *
	 * [--dry-run=<true>]
	 * : Do a dry run by default, change to false to make permanent changes.
	 *
	 * 	 * eg.: `wp vip-go-users change-username jsmith johns`
	 *
	 * @subcommand change-username
	 */
	public function change_username( $args, $assoc_args ) {
		// If --dry-run is not set, then it will default to true
		// Must set --dry-run explicitly to false to run this command
		if ( isset( $assoc_args['dry-run'] ) ) {
			$dry_run = 'false' === $assoc_args['dry-run'] ? false : true;
		} else {
			$dry_run = true;
		}

		list( $old_username, $new_username ) = $args;

		if ( username_exists( $new_username ) ) {
			WP_CLI::error( 'New username already exists!' );
		}

		// One last sanity check to ensure the user exists
		$user_id = username_exists( $old_username );
		if ( $user_id ) {
			if ( ! $dry_run ) {
				global $wpdb;
				// Update username!
				$query_user_login = $wpdb->prepare( "UPDATE $wpdb->users SET user_login = %s WHERE user_login = %s", $new_username, $old_username );

				if ( false !== $wpdb->query( $query_user_login ) ) {
					// Update user_nicename
					$query_user_nicename = $wpdb->prepare( "UPDATE $wpdb->users SET user_nicename = %s WHERE user_login = %s AND user_nicename = %s", $new_username, $new_username, $old_username );
					$wpdb->query( $query_user_nicename );

					// Update display_name
					$query_display_name = $wpdb->prepare( "UPDATE $wpdb->users SET display_name = %s WHERE user_login = %s AND display_name = %s", $new_username, $new_username, $old_username );
					$wpdb->query( $query_display_name );

					// Update nickname
					$nickname = get_user_meta( $user_id, 'nickname', true );
					if ( $nickname === $old_username ) {
						update_user_meta( $user_id, 'nickname', $new_username );
					}

					// If the user is a Super Admin, update their permissions
					if ( is_multisite() && is_super_admin( $user_id ) ) {
						grant_super_admin( $user_id );
					}

					// Reassign Coauthor Attribution
					if ( $this->is_plugin_installed( 'co-authors-plus/co-authors-plus.php' ) ) {
						$this->reassign_cap( $old_username, $new_username, $dry_run );
					}

					clean_user_cache( $user_id );

					WP_CLI::success( 'Username "' . $old_username . '" changed to "' . $new_username . '"' );
				} else {
					WP_CLI::error( 'Username change failed!' );
				}
			} else {
				WP_CLI::log( '[DRY-RUN] Username "' . $old_username . '" changed to "' . $new_username . '"' );
				// Reassign Coauthor Attribution
				if ( $this->is_plugin_installed( 'co-authors-plus.php' ) ) {
					$this->reassign_cap( $old_username, $new_username, $dry_run );
				}
			}
		} else {
			WP_CLI::error( 'Old username does not exists!' );
		}
	}

	function reassign_cap( $old_username, $new_username, $dry_run = true ) {
		global $coauthors_plus;

		$posts_per_page = 100;
		$paged = 1;
		$count = 0;

		if ( ! $dry_run ) {
			$current_term = get_term_by( 'name', $old_username, $coauthors_plus->coauthor_taxonomy );

			if ( false !== $current_term ) {
				wp_delete_term( $current_term->term_id, $coauthors_plus->coauthor_taxonomy );
			}
		}

		do {
			$coauthor_posts = get_posts( array(
				'suppress_filters' => false,
				'posts_per_page'   => $posts_per_page,
				'paged'            => $paged,
				'post_type'        => get_post_types(),
				'tax_query'        => array(
					array(
						'taxonomy' => $coauthors_plus->coauthor_taxonomy,
						'field'    => 'name',
						'terms'    => $old_username,
					),
				)
			) );

			foreach ( $coauthor_posts as $coauthor_post ) {
				if ( ! $dry_run ) {
					$coauthors_plus->add_coauthors( $coauthor_post->ID, array( $new_username ), true );
				}
				$count++;
			}

			WP_CLI::line( 'Reassigned ' . $count . ' Co-Authors Plus posts.' );

			// Pause
			sleep( 3 );

			// Free up memory
			$this->stop_the_insanity();
			$paged++;

		} while ( count( $coauthor_posts ) );
	}

	function is_plugin_installed( $plugin = false ) {
		$is_plugin_active = false;

		if ( $plugin ) {
			$active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins' ) );

			$is_plugin_active = array_filter( $active_plugins, function( $active_plugin ) use ( $plugin ) {
				if ( false !== strpos( $active_plugin, $plugin ) ) {
					return true;
				}
				return false;
			} );
		}

		return $is_plugin_active;
	}
}
WP_CLI::add_command( 'vip-go-users', 'VIP_Go_Users_Command' );
