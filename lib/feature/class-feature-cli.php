<?php

namespace Automattic\VIP;

use WP_CLI;
use Automattic\VIP\Feature;

// phpcs:ignore WordPressVIPMinimum.Classes.RestrictedExtendClasses.wp_cli
class Feature_CLI_Command extends \WP_CLI_Command {
	/**
	 * List all available VIP features.
	 *
	 * @subcommand list
	 *
	 * ## EXAMPLES
	 *
	 *     wp vip feature list [--format=<table|json|csv|yaml>]
	 */
	public function list_features( $args, $assoc_args ) {
		$format = $assoc_args['format'] ?? 'table';
		if ( ! in_array( $format, [ 'table', 'json', 'csv', 'yaml' ], true ) ) {
			WP_CLI::error( __( '--format only accepts the following values: table, json, csv, yaml' ) );
		}

		$features = Feature::get_features();
		if ( empty( $features ) ) {
			WP_CLI::error( __( 'No features found.' ) );
		}

		$listed_features = [];
		foreach ( $features as $feature ) {
			$listed_features[] = [
				'feature' => $feature,
				'status'  => Feature::is_enabled( $feature ) ? 'enabled' : 'disabled',
			];
		}

		WP_CLI\Utils\format_items( $format, $listed_features, [ 'feature', 'status' ] );
	}

	/**
	 * Get status of an available VIP feature.
	 *
	 * @subcommand get
	 *
	 * ## EXAMPLES
	 *
	 *     wp vip feature get <feature-slug>
	 */
	public function get_feature( $args ) {
		if ( ! isset( $args[0] ) ) {
			WP_CLI::error( 'Missing feature slug. Usage as follows: `wp vip feature get <slug>`' );
		}

		$feature = $args[0];

		$features = Feature::get_features();
		if ( ! in_array( $feature, $features, true ) ) {
			WP_CLI::error( "Invalid feature slug '$feature'" );
		}

		WP_CLI::line( sprintf( '%1$s is %2$s.', $feature, Feature::is_enabled( $feature ) ? 'enabled' : 'disabled' ) );
	}
}

WP_CLI::add_command( 'vip feature', __NAMESPACE__ . '\Feature_CLI_Command' );
