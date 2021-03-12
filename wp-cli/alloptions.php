<?php

/**
 * Helpers for interacting with alloptions
 *
 * @package wp-cli
 * @subpackage commands/wordpressdotcom
 */

class VIP_Go_Alloptions extends WPCOM_VIP_CLI_Command {
	/**
	 * Get a list of big options listed in biggest size to smallest
	 *
	 * @subcommand find
	 * @synopsis [--big] [--format=<table>]
	 */
	public function find( $args, $assoc_args ) {
		$defaults = array(
			'big'    => false,
			'format' => 'table',
		);

		$assoc_args = wp_parse_args( $assoc_args, $defaults );

		$options    = array();
		$alloptions = wp_load_alloptions( true );
		$total_size = 0;

		foreach ( $alloptions as $name => $val ) {
			$size       = mb_strlen( $val );
			$total_size += $size;

			// find big options only
			if ( $assoc_args['big'] && $size < 500 ) {
				continue;
			}

			$option = new stdClass();

			$option->name = $name;
			$option->size = $size;

			$options[] = $option;
		}

		// sort by size
		usort( $options, function( $arr1, $arr2 ) {
			if ( $arr1->size === $arr2->size ) {
				return 0;
			}

			return ( $arr1->size < $arr2->size ) ? -1 : 1;
		});

		$options = array_reverse( $options );

		WP_CLI::line();

		if ( $assoc_args['big'] ) {
			WP_CLI::success( sprintf( 'Big options for %s - Blog ID %d:', home_url(), get_current_blog_id() ) );
		} else {
			WP_CLI::success( sprintf( 'All options for %s - Blog ID %d:', home_url(), get_current_blog_id() ) );
		}

		WP_CLI\Utils\format_items( $assoc_args['format'], $options, array( 'name', 'size' ) );

		WP_CLI::line( sprintf( 'Total size of all option values for this blog: %s', size_format( $total_size ) ) );
		WP_CLI::line( sprintf( 'Size of serialized alloptions for this blog: %s',   size_format( strlen( serialize( $alloptions ) ) ) ) );
		WP_CLI::line( "\tuse `wp option get <option_name>` to view a big option" );
		WP_CLI::line( "\tuse `wp option delete <option_name>` to delete a big option" );
		WP_CLI::line( "\tuse `wp option autoload set <option_name> no` to disable autoload for option" );
		$active = $this->get_active_ack();
		if ( $active ) {
			WP_CLI::log( $active );
		}
	}

	/**
	 * Acknowledge alloptions size to suppress alert
	 *
	 * ## OPTIONS
	 *
	 * --reason=<reason>
	 * : Cite relevant ticket or reason
	 *
	 * [--hours=<hours>]
	 * : Hours to silence alert. Default 24. Min 1, Max 168 (1 week)
	 *
	 * [--yes]
	 * : Skip confirmation prompt
	 *
	 * @subcommand ack
	 */
	public function ack( $args = [], $assoc_args = [] ) {

		$reason = WP_CLI\Utils\get_flag_value(
			$assoc_args,
			'reason',
			'no reason given'
		);

		$hours = WP_CLI\Utils\get_flag_value(
			$assoc_args,
			'hours',
			24
		);
		$hours = max( min( intval( $hours ), 168 ), 1 );

		WP_CLI::log( sprintf( 'Reason: %s', $reason ) );
		WP_CLI::log( sprintf( 'Hours: %d', $hours ) );

		$expiry = time() + ( $hours * HOUR_IN_SECONDS );

		if ( $active = $this->get_active_ack() ) {
			WP_CLI::log( $active );
			WP_CLI::confirm( 'Replace existing ack?', $assoc_args );
		} else {
			WP_CLI::confirm( 'Proceed to ack alloptions size?', $assoc_args );
		}

		update_option( 'vip_suppress_alloptions_alert', compact( 'reason', 'expiry' ), 'no' );
	}

	/**
	 * Helper: Get Active Ack
	 *
	 * @return string|bool Colorized string of Ack status. False if not set/active
	 */
	private function get_active_ack() {
		if ( wpcom_vip_alloptions_size_is_acked() ) {

			$stat = get_option( 'vip_suppress_alloptions_alert', [] );

			return WP_CLI::colorize( sprintf(
				'%%GActive ack!%%n VIP alerts silenced until %s (in %s). Reason: %s',
				gmdate('Y-m-d H:i', $stat['expiry'] ),
				human_time_diff( $stat['expiry'] ),
				$stat['reason']
			) );
		}

		return false;
	}

	/**
	 * Remove ack flag, expired or not.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip confirmation prompt
	 *
	 * @subcommand unack
	 */
	public function unack( $args = [], $assoc_args = [] ) {

		$this->ack_stat();

		WP_CLI::confirm( 'Proceed with ack removal?', $assoc_args );

		$deleted = delete_option( 'vip_suppress_alloptions_alert' );
		if ( $deleted ) {
			WP_CLI::success( 'Ack removed.' );
		} else {
			WP_CLI::error( 'Ack not removed.' );
		}
	}

	/**
	 * See ack-stat for alloptions
	 *
	 * ## OPTIONS
	 *
	 * @subcommand ack-stat
	 */
	public function ack_stat( $args = [], $assoc_args = [] ) {

		$stat = get_option( 'vip_suppress_alloptions_alert', [] );

		if ( ! $stat ) {
			WP_CLI::log( 'No current ack on alloptions' );
			return;
		}

		if ( ! $log = $this->get_active_ack() ) {
			$log = WP_CLI::colorize( sprintf(
				'%%RAck expired%%n. Reason: %s',
				$stat['reason']
			) );
		}


		WP_CLI::log( $log );
	}

	/**
	 * Test wpcom_vip_alloptions_size_is_acked()
	 *
	 * ## OPTIONS
	 *
	 * @subcommand ack-test
	 */
	public function ack_test( $args = [], $assoc_args = [] ) {
		WP_CLI::log( 'wpcom_vip_alloptions_size_is_acked() value:' );
		var_dump( wpcom_vip_alloptions_size_is_acked() );
	}

}

WP_CLI::add_command( 'vip alloptions', 'VIP_Go_Alloptions' );
