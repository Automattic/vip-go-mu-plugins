<?php
/**
 * Manage autoloaded options
 */
class Option_Autoload extends WPCOM_VIP_CLI_Command {

	/**
	 * Set autoload for option
	 *
	 * ## OPTIONS
	 *
	 * <option>
	 * : Name of option
	 *
	 * <yn>
	 * : yes or no
	 * ---
	 * options:
	 *   - yes
	 *   - no
	 * ---
	 *
	 *
	 * ## EXAMPLES
	 *
	 *     # Set autoload value to 'no'for 'home'
	 *     $ wp option autoload set home no
	 *     Success: Autoload changed. Cache flushed.
	 *
	 */
	public function set( $args, $assoc_args ) {

		list( $option, $yn ) = $args;

		$yn = $this->validate_yn( $yn );

		wp_protect_special_option( $option );

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$option_autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload from {$wpdb->options} where option_name = %s", $option ) );

		if ( is_null( $option_autoload ) ) {
			WP_CLI::error( 'Option does not exist.' );
		}

		if ( $option_autoload === $yn ) {
			WP_CLI::error( sprintf( "Option autoload already set to '%s'.", $yn ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->get_results( $wpdb->prepare( "UPDATE {$wpdb->options} SET autoload = %s where option_name = %s", $yn, $option ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$check_option = $wpdb->get_var( $wpdb->prepare( "SELECT autoload from {$wpdb->options} where option_name = %s", $option ) );

		if ( $check_option === $option_autoload ) {
			WP_CLI::error( 'Option not updated.' );
		}

		$alloptions_before = wp_cache_get( 'alloptions', 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		wp_load_alloptions(); // populate cache
		$alloptions_after = wp_cache_get( 'alloptions', 'options' );

		$cache_success = false;
		switch ( $check_option ) {
			case 'no':
				$cache_success = ( isset( $alloptions_before[ $option ] ) ) && ( ! isset( $alloptions_after[ $option ] ) );
				break;
			case 'yes':
				$cache_success = ( ! isset( $alloptions_before[ $option ] ) ) && ( isset( $alloptions_after[ $option ] ) );
				break;
		}

		WP_CLI::success( sprintf(
			'Autoload changed. %s',
			WP_CLI::colorize( $cache_success ? 'Cache flushed.' : '%rCache flush failed.%n' )
		) );

	}

	/**
	 * Get autoload for option
	 *
	 * ## OPTIONS
	 *
	 * <option>
	 * : Name of option
	 *
	 * [--format=<format>]
	 * : Format to use for the output. One of table, csv or json.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 *   - count
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Get autoload value for 'home'
	 *     $ wp option autoload get home
	 *     yes
	 *
	 *     # Get autoload value for 'home' as json
	 *     $ wp option autoload get home --format=json
	 *     "yes"
	 *
	 */
	public function get( $args, $assoc_args ) {

		list( $option ) = $args;

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$option_autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload from {$wpdb->options} where option_name = %s", $option ) );

		if ( is_null( $option_autoload ) ) {
			WP_CLI::error( 'Option does not exist.' );
		}

		WP_CLI::print_value( $option_autoload, $assoc_args );

	}

	/**
	 * List all autoloaded (or not) options
	 *
	 * ## OPTIONS
	 *
	 * [<yn>]
	 * : yes or no
	 * ---
	 * default: yes
	 * options:
	 *   - yes
	 *   - no
	 * ---
	 *
	 * [--format=<format>]
	 * : Format to use for the output. One of table, csv or json.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 *   - count
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # List all un-autoloaded options
	 *     $ wp option autoload list no
	 *     +-------------------+
	 *     | option_name       |
	 *     +-------------------+
	 *     | moderation_keys   |
	 *     | recently_edited   |
	 *     | blacklist_keys    |
	 *     | uninstall_plugins |
	 *     +-------------------+
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {

		list( $yn ) = $args;
		$yn         = $this->validate_yn( $yn, 'yes' );

		// option list uses on/off ¯\_(ツ)_/¯
		$yn = 'yes' === $yn ? 'on' : 'off';

		WP_CLI::run_command(
			[ 'option', 'list' ],
			[
				'autoload' => $yn,
				'fields'   => 'option_name',
				'format'   => $assoc_args['format'],
			]
		);

		WP_CLI::log( "Try 'wp option list' for more control." );
	}

	/**
	 * Refresh alloptions cache
	 *
	 * Alias to `wp cache delete alloptions options`
	 *
	 * ## OPTIONS
	 *
	 * none
	 *
	 * ## EXAMPLES
	 *
	 *     # Flush alloptions cache
	 *     $ wp option autoload refresh
	 *     Success: Object deleted.
	 *
	 */
	public function refresh( $args, $assoc_args ) {

		WP_CLI::run_command( [ 'cache', 'delete', 'alloptions', 'options' ] );

	}

	/**
	 * Validate <yn> field
	 *
	 * The doc block restrictions don't work for positional arguments,
	 * this validates the input value
	 */
	private function validate_yn( $yn, $default = false ) {
		if ( 'yes' === $yn || 'no' === $yn ) {
			return $yn;
		}
		if ( $default && true === $yn ) { // default for empty option parameter (e.g. `wp option autoload list`)
			return $default;
		}
		WP_CLI::error( "Invalid <yn>. Please specify 'yes' or 'no'." );
	}
}

WP_CLI::add_command( 'option autoload', 'Option_Autoload' );
