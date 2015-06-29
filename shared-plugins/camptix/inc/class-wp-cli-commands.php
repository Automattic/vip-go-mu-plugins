<?php
/**
 * CampTix commands for WP-CLI.
 * @since 1.3.2
 */
class CampTix_Command extends WP_CLI_Command {

	/**
	 * Runs the upgrade routine
	 */
	function upgrade( $args, $assoc_args ) {
		global $camptix;
		$options = $camptix->get_options();

		if ( apply_filters( 'camptix_enable_automatic_upgrades', true ) ) {
			WP_CLI::warning( "The 'camptix_enable_automatic_upgrades' filter is set to true. You probably want it set to false; otherwise the upgrade will probably run automatically before you get a chance to run it manually." );
		}

		if ( $options['version'] < $camptix->version ) {
			$camptix->log( 'Running manual upgrade.', 0, null, 'upgrade' );

			if ( $camptix->upgrade( $options['version'] ) ) {
				WP_CLI::success( "The upgrade routine has finished running. Upgraded from {$options['version']} to {$camptix->version}." );
			} else {
				WP_CLI::error( 'The upgrade routine was not able to run. The most likely cause is that another upgrade routine is already in progress.' );
			}
		} else {
			WP_CLI::warning( 'CampTix does not need to upgrade. The upgrade routine was not run.' );
		}
	}
}

WP_CLI::add_command( 'camptix', 'CampTix_Command' );